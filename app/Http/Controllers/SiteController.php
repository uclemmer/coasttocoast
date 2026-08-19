<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\FaqItem;
use App\Models\Sponsor;
use App\Services\RosterService;
use App\Support\ContentBlocks;
use Illuminate\Contracts\View\View;

/**
 * The public site (Phase 8).
 *
 * Thin on purpose: each action gathers what its view needs and returns it. The
 * rules live where they already did — `RosterService` decides what appears on
 * a roster, `Event::active()` decides which fair the site is about, and
 * laravel-core's Content module owns the editable copy.
 *
 * The rosters and the two forms are Livewire components rather than actions
 * here; everything on this class renders once and does not change until the
 * coordinator changes it.
 */
class SiteController extends Controller
{
    public function home(): View
    {
        return view('site.home', [
            'fair' => Event::active(),
            'sponsors' => Sponsor::query()->ordered()->get(),
            // The landing page's prose is editable copy seeded with the
            // design's words, not strings in the template. The headline and
            // the section titles stay in the template — they are display type,
            // sized and cropped to the layout.
            'heroBody' => ContentBlocks::render('home.hero'),
            'registrationIntro' => ContentBlocks::render('home.for_representatives'),
        ]);
    }

    public function about(): View
    {
        return view('site.about', [
            'body' => ContentBlocks::render('about.body'),
        ]);
    }

    public function sponsors(): View
    {
        return view('site.sponsors', [
            'intro' => ContentBlocks::render('sponsors.intro'),
            'sponsors' => Sponsor::query()->ordered()->with('staff')->get(),
        ]);
    }

    public function faq(): View
    {
        return view('site.faq', [
            'items' => FaqItem::query()->published()->get(),
        ]);
    }

    public function contact(): View
    {
        return view('site.contact', [
            'intro' => ContentBlocks::render('contact.intro'),
        ]);
    }

    public function event(Event $event, RosterService $roster): View
    {
        // Unpublished fairs do not exist as far as the public is concerned —
        // 404 rather than 403, which would confirm the draft exists
        // (doc 10, D-5.4-c).
        abort_unless($event->is_published, 404);

        return view('site.event', ['fair' => $event]);
    }
}
