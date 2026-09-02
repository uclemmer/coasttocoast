<?php

namespace App\Console\Commands;

use App\Models\Organization;
use Illuminate\Console\Command;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use UClemmer\LaravelCore\Support\LikeTerm;

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

    /**
     * Below this, a roster tile is upscaling and it shows. Not a rejection —
     * a small mark still beats a letter — but it is counted so there is a
     * worklist rather than a vague sense that some tiles look soft.
     */
    protected const MIN_DIMENSION = 96;

    /**
     * Stop looking once something is at least this big. 180 is the modern
     * `apple-touch-icon`, so most sites cost one request.
     */
    protected const GOOD_ENOUGH = 180;

    /**
     * Wider than this is a banner or a photograph rather than a mark.
     * Mississippi State's og:image is 2400x800.
     */
    protected const MAX_ASPECT = 2.0;

    /**
     * A site declaring more icons than this is not hiding a better one further
     * down the list, and every entry is a request to somebody else's server.
     */
    protected const MAX_CANDIDATES = 6;

    protected const DISK = 'public';

    protected const DIRECTORY = 'organization-logos';

    /** @var array<string, int> */
    protected array $tally = [
        'downloaded' => 0,
        'resolved (dry run)' => 0,
        'already had one' => 0,
        'recovered from disk' => 0,
        'stored but small' => 0,
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
            /*
             * Escaped like every other name filter here. `--only` is an
             * operator's shorthand for "organizations whose name contains
             * this", not a pattern language — nothing documents a wildcard, so
             * an operator typing one should get the character they typed.
             */
            ->when(
                is_string($only) && $only !== '',
                fn ($query) => $query->whereRaw(LikeTerm::clause('name'), [LikeTerm::contains((string) $only)]),
            )
            ->orderBy('sort_name')
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
            $candidates = $this->candidates($source);
            $url = $candidates[0]['url'] ?? null;
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

        $this->download($organization, $candidates);
    }

    /**
     * Read the institution's own metadata for the image it nominates.
     *
     * Returns the best candidate by DECLARED size. `candidates()` explains why
     * there is more than one and why taking the first was the bug.
     */
    protected function resolveLogoUrl(string $source): ?string
    {
        return $this->candidates($source)[0]['url'] ?? null;
    }

    /**
     * Every image the page nominates, best first.
     *
     * **Taking the first declared icon was wrong, and it cost 40 logos.** A site
     * that supports iOS properly declares an `apple-touch-icon` per device
     * generation — Auburn ships 57, 72, 76, 114, 120 and 144 — and the smallest
     * is conventionally written first, because the list is historical. The old
     * code matched one pattern, took match [0] and committed to it, so Auburn's
     * roster tile was a 57px image upscaled into a space four times its size,
     * with a 144 sitting in the same `<head>`.
     *
     * So: collect them all, read the `sizes` attribute, and order by it. An
     * `apple-touch-icon` with no `sizes` is treated as 180, which is the modern
     * single-icon convention and the reason it is usually the unsized one.
     *
     * `og:image` stays in the list and stays late. It is what the institution
     * nominates for a link preview, which is a photograph far more often than a
     * mark — Auburn's is a picture of Samford Hall, Mississippi State's is a
     * 2400x800 banner. `storeBest()` rejects those on shape rather than here on
     * rank, because a few institutions do nominate their mark.
     *
     * @return list<array{url: string, declared: int}>
     */
    protected function candidates(string $source): array
    {
        $response = Http::timeout(15)
            ->withHeaders(['User-Agent' => 'CoastToCoastCollegeFair/1.0 (roster logo fetch)'])
            ->get($source);

        if (! $response->successful()) {
            throw new RuntimeException("HTTP {$response->status()}");
        }

        $html = $response->body();
        $base = (string) $response->effectiveUri();

        $touch = $this->linksWithSizes($html, 'apple-touch-icon', 180);
        $icons = $this->linksWithSizes($html, 'icon', 0);
        $masks = $this->linksWithSizes($html, 'mask-icon', 0);

        $sharing = [];

        foreach ([
            '/<meta[^>]+property=["\']og:image["\'][^>]+content=["\']([^"\']+)["\']/i',
            '/<meta[^>]+content=["\']([^"\']+)["\'][^>]+property=["\']og:image["\']/i',
        ] as $pattern) {
            if (preg_match($pattern, $html, $matches) === 1) {
                $sharing[] = ['href' => $matches[1], 'declared' => 0];

                break;
            }
        }

        // A vector never needs upscaling, so it outranks every raster size.
        foreach ($masks as $index => $mask) {
            $masks[$index]['declared'] = 100000;
        }

        $ordered = [
            ...$this->bySize($masks),
            ...$this->bySize($touch),
            ...$this->bySize($icons),
            ...$sharing,
            ['href' => '/favicon.ico', 'declared' => 0],
        ];

        $candidates = [];

        foreach ($ordered as $candidate) {
            $url = $this->absolute($candidate['href'], $base);

            // Sites repeat the same href across several rel values.
            if (! isset($candidates[$url])) {
                $candidates[$url] = ['url' => $url, 'declared' => $candidate['declared']];
            }
        }

        return array_values($candidates);
    }

    /**
     * Every `<link rel="...">` of one kind, with its declared square size.
     *
     * @return list<array{href: string, declared: int}>
     */
    protected function linksWithSizes(string $html, string $rel, int $whenUnsized): array
    {
        preg_match_all('/<link[^>]+>/i', $html, $tags);

        $found = [];

        foreach ($tags[0] as $tag) {
            if (preg_match('/rel=["\']([^"\']*)["\']/i', $tag, $relMatch) !== 1) {
                continue;
            }

            $values = preg_split('/\s+/', strtolower(trim($relMatch[1]))) ?: [];

            // `rel="shortcut icon"` is two tokens and means `icon`.
            if (! in_array(strtolower($rel), $values, true)) {
                continue;
            }

            if (preg_match('/href=["\']([^"\']+)["\']/i', $tag, $hrefMatch) !== 1) {
                continue;
            }

            $declared = $whenUnsized;

            if (preg_match('/sizes=["\'](\d+)x(\d+)["\']/i', $tag, $sizeMatch) === 1) {
                $declared = min((int) $sizeMatch[1], (int) $sizeMatch[2]);
            }

            $found[] = ['href' => $hrefMatch[1], 'declared' => $declared];
        }

        return $found;
    }

    /**
     * Largest declared first, stable for equal sizes.
     *
     * @param  list<array{href: string, declared: int}>  $links
     * @return list<array{href: string, declared: int}>
     */
    protected function bySize(array $links): array
    {
        usort($links, fn (array $a, array $b): int => $b['declared'] <=> $a['declared']);

        return $links;
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

    /**
     * Try the candidates in order and keep the best one.
     *
     * It stops as soon as something reaches `GOOD_ENOUGH`, so a site declaring
     * its 180px icon first costs exactly one request. Only a site whose best
     * declared icon is small pays for the whole walk, and it is capped.
     *
     * @param  list<array{url: string, declared: int}>  $candidates
     */
    protected function download(Organization $organization, array $candidates): void
    {
        $best = null;
        $lastFailure = null;

        foreach (array_slice($candidates, 0, self::MAX_CANDIDATES) as $candidate) {
            $found = $this->evaluate($organization, $candidate['url']);

            if (is_string($found)) {
                $lastFailure = $found;

                continue;
            }

            if ($best === null || $found['score'] > $best['score']) {
                $best = $found;
            }

            if ($best['score'] >= self::GOOD_ENOUGH) {
                break;
            }
        }

        if ($best === null) {
            if ($this->recover($organization, $lastFailure ?? 'nothing usable was declared')) {
                return;
            }

            $this->warn("{$organization->name}: {$lastFailure}.");
            $this->tally['no logo found']++;

            return;
        }

        $path = self::DIRECTORY.'/'.Str::slug($organization->name).'.'.$this->extensionFor($best['type'], $best['url']);

        Storage::disk(self::DISK)->put($path, $best['body']);
        $this->forgetSupersededFiles($organization, $path);

        $organization->forceFill(['logo_path' => $path])->save();

        $this->line(sprintf('%-46s %-52s %s', Str::limit($organization->name, 44), $path, $best['size']));
        $this->tally['downloaded']++;

        if ($best['score'] < self::MIN_DIMENSION) {
            // Kept, because a small mark beats a letter, but counted: these are
            // the rows worth a coordinator's time in /staff/organizations.
            $this->tally['stored but small']++;
        }
    }

    /**
     * Fetch one candidate and score it, or describe why it is unusable.
     *
     * @return array{url: string, body: string, type: string, score: int, size: string}|string
     */
    protected function evaluate(Organization $organization, string $url): array|string
    {
        try {
            $response = Http::timeout(20)
                ->withHeaders(['User-Agent' => 'CoastToCoastCollegeFair/1.0 (roster logo fetch)'])
                ->get($url);
        } catch (ConnectionException $e) {
            return "{$url} unreachable ({$e->getMessage()})";
        }

        $type = Str::before((string) $response->header('Content-Type'), ';');

        if (! $response->successful() || ! str_starts_with($type, 'image/')) {
            return "{$url} is not an image ({$type})";
        }

        $body = $response->body();

        if (strlen($body) > self::MAX_BYTES) {
            return "{$url} is larger than 2 MB";
        }

        $measured = $this->measure($body);

        if ($measured === null) {
            // Unjudgeable, not unusable — an SVG has no pixel size and is the
            // best logo there is. Scores below any decent raster so a measured
            // one wins, and above nothing at all so it is still stored.
            return ['url' => $url, 'body' => $body, 'type' => $type, 'score' => self::MIN_DIMENSION, 'size' => 'unmeasured'];
        }

        [$width, $height] = $measured;
        $ratio = min($width, $height) > 0 ? max($width, $height) / min($width, $height) : 99;

        if ($ratio > self::MAX_ASPECT) {
            // A banner or a photograph, not a mark. This is what the
            // institution nominates for a link preview, and it is why og:image
            // ranks late rather than being trusted.
            return "{$url} is {$width}x{$height}, too wide to be a logo";
        }

        return [
            'url' => $url,
            'body' => $body,
            'type' => $type,
            'score' => min($width, $height),
            'size' => "{$width}x{$height}",
        ];
    }

    /**
     * The largest square-ish edge of an image, or null when it cannot be read.
     *
     * **Unmeasurable is not the same as unusable.** An SVG has no pixel size at
     * all and is the best possible logo; some servers send a format GD cannot
     * parse. Those score neutrally and are still stored — this measurement
     * ranks candidates and rejects obvious banners, and anything it cannot
     * judge it does not reject.
     *
     * @return array{0: int, 1: int}|null
     */
    protected function measure(string $body): ?array
    {
        $ico = $this->measureIco($body);

        if ($ico !== null) {
            return $ico;
        }

        $size = @getimagesizefromstring($body);

        return $size === false ? null : [$size[0], $size[1]];
    }

    /**
     * The largest frame in an ICO, or null if it is not one.
     *
     * An ICO is a container: `favicon.ico` routinely holds 16, 32, 48, 128 and
     * 256 in one file, and a browser picks the frame it wants. `getimagesize()`
     * reports the FIRST directory entry, which is conventionally the smallest —
     * so measuring an ICO the ordinary way says 16x16 about a file whose real
     * content is 256x256. Bard, Trinity and the Air Force Academy all read as
     * unusable that way and are all fine.
     *
     * @return array{0: int, 1: int}|null
     */
    protected function measureIco(string $body): ?array
    {
        if (strlen($body) < 6) {
            return null;
        }

        $header = @unpack('vreserved/vtype/vcount', substr($body, 0, 6));

        if (! is_array($header) || $header['reserved'] !== 0 || $header['type'] !== 1 || $header['count'] < 1) {
            return null;
        }

        if (strlen($body) < 6 + $header['count'] * 16) {
            return null;
        }

        $best = null;

        for ($index = 0; $index < $header['count']; $index++) {
            $entry = substr($body, 6 + $index * 16, 16);

            // A zero byte means 256 — the field is one byte and 256 does not fit.
            $width = ord($entry[0]) ?: 256;
            $height = ord($entry[1]) ?: 256;

            if ($best === null || min($width, $height) > min($best[0], $best[1])) {
                $best = [$width, $height];
            }
        }

        return $best;
    }

    /**
     * Delete this organization's other logo files.
     *
     * The stored name is `<slug>.<extension>`, so a run that picks a different
     * format leaves the old file behind — and that is not merely untidy.
     * `existingFile()` prefers richer formats, so a superseded
     * `rice-university.webp` would outrank the `.ico` that replaced it, and the
     * next refusal would relink the 1500x600 banner this command had just
     * rejected on shape. The fallback would resurrect exactly what the quality
     * check threw out.
     *
     * Found by combining the two features, which is where this class of bug
     * lives: each was right on its own.
     */
    protected function forgetSupersededFiles(Organization $organization, string $keep): void
    {
        $slug = Str::slug($organization->name);

        foreach (['svg', 'png', 'webp', 'jpg', 'gif', 'ico'] as $extension) {
            $path = self::DIRECTORY.'/'.$slug.'.'.$extension;

            if ($path !== $keep && Storage::disk(self::DISK)->exists($path)) {
                Storage::disk(self::DISK)->delete($path);
            }
        }
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
