<?php

namespace App\Console\Commands;

use App\Models\Organization;
use Illuminate\Console\Command;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Download each organization's logo from its own website, for the roster.
 *
 * `organizations.logo_path` is a file on the `public` disk — the roster and the
 * admin panel both render it through `Storage::disk('public')->url()` — so a
 * remote URL cannot be stored in it. Something has to fetch the bytes, and this
 * is that something. It is a command rather than part of
 * `AdmissionsOfficeSeeder` for exactly that reason: a seeder that reached out
 * to 157 institutions' web servers would be a surprising thing for
 * `db:seed` to do.
 *
 * ## Why the logo URL is discovered rather than recorded
 *
 * `admissions-offices.json` carries a `logo_source` — the institution's own
 * site — and not a direct link to an image file. That is deliberate. A
 * hand-collected list of 157 logo URLs would be 157 guesses at a path that
 * changes whenever a university touches its stylesheet, and the failure would
 * be silent: a stale URL 404s and the roster tile falls back to a letter.
 *
 * Instead the command reads the institution's own metadata:
 *
 *   1. `apple-touch-icon` — square, usually 180px, and usually the mark alone.
 *   2. `og:image` — the image the institution nominates for sharing.
 *   3. `<link rel="icon">` — the favicon as declared.
 *   4. `/favicon.ico` — the last resort.
 *
 * **`apple-touch-icon` is deliberately ahead of `og:image`**, which is the
 * opposite of what a link preview does, and the reason is what a first run
 * turned up: Clemson's `og:image` is an aerial photograph of the campus. Most
 * institutions nominate a hero photo for sharing, because that is what sharing
 * is for — a roster tile wants the mark, and the touch icon is the one thing on
 * a page that is reliably the mark on its own.
 *
 * **`--dry-run` prints what it resolved and downloads nothing**, which is the
 * way to collect the URLs as a reviewable list before committing to the files.
 * Use it: this is an accelerator, not an oracle. An institution with no touch
 * icon falls through to `og:image` and lands on a photograph — Clemson does,
 * today — and the fix for a bad guess is the coordinator uploading a real logo
 * in `/staff/organizations`, which is what that field was always for.
 *
 * ## What it will not do
 *
 * It will not overwrite a logo that is already there — a coordinator's upload
 * beats a scraped favicon, so an organization with a `logo_path` is skipped
 * unless `--force` says otherwise. It refuses anything that is not an image, and
 * anything over 2 MB. It follows the institution's own published metadata and
 * nothing else; there is no crawling.
 *
 * These are third-party marks, used to identify organizations that chose to
 * attend the fair. That is the same use the roster's existing upload field is
 * for. If an institution objects, deleting the row's logo in
 * `/staff/organizations` is the whole remedy.
 */
class FetchOrganizationLogos extends Command
{
    protected $signature = 'fair:fetch-organization-logos
        {--dry-run : Resolve and report each logo URL, downloading nothing}
        {--force : Replace logos that are already set}
        {--only= : Limit to organizations whose name contains this}';

    protected $description = 'Fetch organization logos from their own sites into storage, for the roster.';

    protected const MAX_BYTES = 2 * 1024 * 1024;

    protected const DISK = 'public';

    protected const DIRECTORY = 'organization-logos';

    /** @var array<string, int> */
    protected array $tally = [
        'downloaded' => 0,
        'resolved (dry run)' => 0,
        'already had one' => 0,
        'recovered from disk' => 0,
        'no logo found' => 0,
        'unreachable' => 0,
    ];

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $force = (bool) $this->option('force');
        $only = $this->option('only');

        if ($dryRun) {
            $this->warn('Dry run — resolving URLs only, nothing will be downloaded.');
        }

        $sources = $this->sources();

        $organizations = Organization::query()
            ->when(is_string($only) && $only !== '', fn ($query) => $query->where('name', 'like', "%{$only}%"))
            ->orderBy('name')
            ->get();

        foreach ($organizations as $organization) {
            $source = $sources[$organization->name] ?? null;

            if ($source === null) {
                continue;
            }

            if (filled($organization->logo_path) && ! $force) {
                $this->tally['already had one']++;

                continue;
            }

            $this->fetchFor($organization, $source, $dryRun);
        }

        $this->newLine();
        foreach ($this->tally as $label => $count) {
            $this->line(sprintf('%-22s %d', $label, $count));
        }

        return self::SUCCESS;
    }

    protected function fetchFor(Organization $organization, string $source, bool $dryRun): void
    {
        try {
            $url = $this->resolveLogoUrl($source);
        } catch (ConnectionException|RuntimeException $e) {
            if ($this->recover($organization, "{$source} unreachable ({$e->getMessage()})", $dryRun)) {
                return;
            }

            $this->warn("{$organization->name}: {$source} unreachable ({$e->getMessage()}).");
            $this->tally['unreachable']++;

            return;
        }

        if ($url === null) {
            $this->warn("{$organization->name}: no logo declared at {$source}.");
            $this->tally['no logo found']++;

            return;
        }

        if ($dryRun) {
            $this->line(sprintf('%-46s %s', Str::limit($organization->name, 44), $url));
            $this->tally['resolved (dry run)']++;

            return;
        }

        $this->download($organization, $url);
    }

    /**
     * Read the institution's own metadata for the image it nominates.
     */
    protected function resolveLogoUrl(string $source): ?string
    {
        $response = Http::timeout(15)
            ->withHeaders(['User-Agent' => 'CoastToCoastCollegeFair/1.0 (roster logo fetch)'])
            ->get($source);

        if (! $response->successful()) {
            throw new RuntimeException("HTTP {$response->status()}");
        }

        $html = $response->body();

        foreach ([
            '/<link[^>]+rel=["\']apple-touch-icon[^"\']*["\'][^>]+href=["\']([^"\']+)["\']/i',
            '/<meta[^>]+property=["\']og:image["\'][^>]+content=["\']([^"\']+)["\']/i',
            '/<meta[^>]+content=["\']([^"\']+)["\'][^>]+property=["\']og:image["\']/i',
            '/<link[^>]+rel=["\'][^"\']*\bicon\b[^"\']*["\'][^>]+href=["\']([^"\']+)["\']/i',
        ] as $pattern) {
            if (preg_match($pattern, $html, $matches) === 1) {
                return $this->absolute($matches[1], (string) $response->effectiveUri());
            }
        }

        return $this->absolute('/favicon.ico', (string) $response->effectiveUri());
    }

    /**
     * Resolve a possibly-relative href against the page it was found on.
     */
    protected function absolute(string $href, string $base): string
    {
        $href = html_entity_decode(trim($href), ENT_QUOTES | ENT_HTML5);

        if (str_starts_with($href, 'http://') || str_starts_with($href, 'https://')) {
            return $href;
        }

        $parts = parse_url($base);
        $origin = ($parts['scheme'] ?? 'https').'://'.($parts['host'] ?? '');

        if (str_starts_with($href, '//')) {
            return ($parts['scheme'] ?? 'https').':'.$href;
        }

        return $origin.'/'.ltrim($href, '/');
    }

    protected function download(Organization $organization, string $url): void
    {
        try {
            $response = Http::timeout(20)
                ->withHeaders(['User-Agent' => 'CoastToCoastCollegeFair/1.0 (roster logo fetch)'])
                ->get($url);
        } catch (ConnectionException $e) {
            if ($this->recover($organization, "{$url} unreachable ({$e->getMessage()})")) {
                return;
            }

            $this->warn("{$organization->name}: {$url} unreachable ({$e->getMessage()}).");
            $this->tally['unreachable']++;

            return;
        }

        $type = Str::before((string) $response->header('Content-Type'), ';');

        if (! $response->successful() || ! str_starts_with($type, 'image/')) {
            if ($this->recover($organization, "{$url} is not an image ({$type})")) {
                return;
            }

            $this->warn("{$organization->name}: {$url} is not an image ({$type}).");
            $this->tally['no logo found']++;

            return;
        }

        $body = $response->body();

        if (strlen($body) > self::MAX_BYTES) {
            if ($this->recover($organization, "{$url} is larger than 2 MB")) {
                return;
            }

            $this->warn("{$organization->name}: {$url} is larger than 2 MB — skipped.");
            $this->tally['no logo found']++;

            return;
        }

        $path = self::DIRECTORY.'/'.Str::slug($organization->name).'.'.$this->extensionFor($type, $url);

        Storage::disk(self::DISK)->put($path, $body);

        $organization->forceFill(['logo_path' => $path])->save();

        $this->line(sprintf('%-46s %s', Str::limit($organization->name, 44), $path));
        $this->tally['downloaded']++;
    }

    /**
     * Fall back to a copy this organization already has on disk.
     *
     * A fetch failing is not the same as there being no logo. Roughly one site
     * in twenty answers a scripted request with a 403 or a 406 one day and
     * serves the file the next — Rice and North Carolina Outward Bound both did
     * exactly that — so treating a refusal as "no logo" throws away a good file
     * that is already sitting in storage.
     *
     * That matters because `logo_path` is a database column and the files are
     * not: any reseed nulls all of them while every file survives, so the
     * refetch afterwards is where the loss happens, to a different arbitrary
     * handful each time. Without this the command is lossy by design and the
     * loss is invisible — a null column looks exactly like an institution that
     * publishes nothing.
     *
     * Filenames are the organization's slug, so finding the file needs no
     * record of what was downloaded before.
     */
    protected function recover(Organization $organization, string $why, bool $dryRun = false): bool
    {
        $existing = $this->existingFile($organization);

        if ($existing === null) {
            return false;
        }

        $this->warn("{$organization->name}: {$why} — kept the copy already on disk ({$existing}).");
        $this->tally['recovered from disk']++;

        if (! $dryRun) {
            $organization->forceFill(['logo_path' => $existing])->save();
        }

        return true;
    }

    /**
     * The best file already stored for this organization, richest format first
     * — a favicon is the last resort here for the same reason it is last in
     * `resolveLogoUrl()`.
     */
    protected function existingFile(Organization $organization): ?string
    {
        $slug = Str::slug($organization->name);

        foreach (['svg', 'png', 'webp', 'jpg', 'gif', 'ico'] as $extension) {
            $path = self::DIRECTORY.'/'.$slug.'.'.$extension;

            if (Storage::disk(self::DISK)->exists($path)) {
                return $path;
            }
        }

        return null;
    }

    /**
     * Trust the served content type over the URL, which is often a CDN path
     * with no extension at all.
     */
    protected function extensionFor(string $contentType, string $url): string
    {
        return match ($contentType) {
            'image/png' => 'png',
            'image/jpeg' => 'jpg',
            'image/svg+xml' => 'svg',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
            'image/x-icon', 'image/vnd.microsoft.icon' => 'ico',
            default => pathinfo((string) parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION) ?: 'png',
        };
    }

    /**
     * Each organization's own site, from the admissions office research
     * (`AdmissionsOfficeSeeder`), keyed by the name it is seeded under.
     *
     * @return array<string, string>
     */
    protected function sources(): array
    {
        $path = base_path('database/seeders/data/admissions-offices.json');

        if (! is_readable($path)) {
            throw new RuntimeException("The admissions office data is missing: {$path}");
        }

        /** @var array<string, array<string, string|null>> $offices */
        $offices = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);

        return collect($offices)
            ->map(fn (array $office): ?string => $office['logo_source'] ?? null)
            ->filter()
            ->all();
    }
}
