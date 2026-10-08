<?php

use App\Livewire\Staff\Faq\Edit as EditFaqItem;
use App\Models\FaqItem;
use App\Models\Message;
use App\Models\MessageRecipient;
use App\Notifications\CampaignMessage;
use App\Support\Markdown;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\File;

/*
 * Staff-written Markdown renders with raw HTML escaped and unsafe links
 * dropped, on every surface that shows it (docs/24 §5).
 *
 * Laravel's `Str::markdown()` escapes neither by default: until 2026-10-08 a
 * pasted `<img onerror>` or `javascript:` link reached the public FAQ and
 * recipients' inboxes live. Each surface is asserted separately, because one
 * of them quietly going back to `Str::markdown()` is exactly the regression
 * this guards against — and the last test fails if any view or class does.
 */

const HOSTILE_MARKDOWN = "Park **behind** the centre. <div class=\"x\">box</div>\n\n"
    ."<img src=x onerror=alert(1)>\n\n"
    .'[map](https://maps.example.com) [trap](javascript:alert(2))';

function expectRenderedSafely(string $html): void
{
    expect($html)
        // Markdown still renders.
        ->toContain('<strong>behind</strong>')
        ->toContain('<a href="https://maps.example.com">map</a>')
        // Raw HTML arrives as text.
        ->toContain('&lt;img src=x onerror=alert(1)&gt;')
        ->toContain('&lt;div class="x"&gt;box&lt;/div&gt;')
        ->not->toContain('<img src=x')
        ->not->toContain('<div class="x">')
        // An unsafe link keeps its text and loses its target.
        ->not->toContain('javascript:');
}

it('escapes raw HTML and drops unsafe links, and still renders markdown', function () {
    expectRenderedSafely(Markdown::render(HOSTILE_MARKDOWN));

    expect(Markdown::render(null))->toBe('');
});

it('renders a public FAQ answer safely', function () {
    FaqItem::factory()->create(['question' => 'Where do we park?', 'answer' => HOSTILE_MARKDOWN]);

    expectRenderedSafely($this->get('/faq')->assertOk()->getContent());
});

it('previews a FAQ answer exactly as the public page will show it', function () {
    $this->actingAs(coordinator());

    $preview = livewire(EditFaqItem::class)
        ->set('answer', HOSTILE_MARKDOWN)
        ->instance()
        ->preview();

    expectRenderedSafely($preview);
});

it('renders a campaign email safely', function () {
    $message = Message::factory()->create(['email_body' => HOSTILE_MARKDOWN]);
    $recipient = MessageRecipient::factory()->for($message)->create();

    $html = (string) (new CampaignMessage($message, $recipient))
        ->toMail(new AnonymousNotifiable)
        ->render();

    expectRenderedSafely($html);
});

it('renders a campaign safely on the staff page that shows it', function () {
    $this->actingAs(coordinator());
    $message = Message::factory()->create(['email_body' => HOSTILE_MARKDOWN]);

    expectRenderedSafely($this->get('/staff/messages/'.$message->id)->assertOk()->getContent());
});

it('leaves no view or class rendering markdown around the helper', function () {
    $offenders = collect([...File::allFiles(app_path()), ...File::allFiles(resource_path('views'))])
        ->filter(fn ($file) => str_contains($file->getContents(), 'Str::markdown('))
        ->reject(fn ($file) => $file->getRealPath() === realpath(app_path('Support/Markdown.php')))
        ->map(fn ($file) => $file->getRelativePathname())
        ->values()
        ->all();

    expect($offenders)->toBe([]);
});
