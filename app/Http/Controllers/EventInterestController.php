<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreEventInterestRequest;
use App\Models\Event;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;

/**
 * The notify-me capture on a closed event page (R2.7, card 3.4).
 *
 * Fixes the biggest hole in the current site: registration is shut for most of
 * the year and the page is a dead end (doc 00).
 */
class EventInterestController extends Controller
{
    public function store(StoreEventInterestRequest $request, Event $event): RedirectResponse
    {
        // Case-insensitive, because a person who signs up twice with `Dana@`
        // and `dana@` should not be told they are already on the list *and*
        // then be mailed twice. `updateOrCreate` on the lowercased address is
        // both the dedupe and the "we heard you the first time".
        $event->interests()->updateOrCreate(
            ['email' => Str::lower($request->string('email')->trim()->value())],
            ['organization_name' => $request->input('organization_name')],
        );

        return back()->with('status', __(
            'Thanks — we will email you as soon as registration for :event opens.',
            ['event' => $event->name],
        ));
    }
}
