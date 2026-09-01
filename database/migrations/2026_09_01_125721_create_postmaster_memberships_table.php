<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One subscriber's relationship with one list.
 *
 * A real table with a model rather than a bare pivot, because it carries the
 * consent record — and consent is given per list, so it cannot live on the
 * subscriber and survive them joining a second one.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('postmaster_memberships', function (Blueprint $table): void {
            $table->ulid('id')->primary();

            $table->foreignUlid('list_id')->constrained('postmaster_lists')->cascadeOnDelete();
            $table->foreignUlid('subscriber_id')->constrained('postmaster_subscribers')->cascadeOnDelete();

            $table->string('status')->index();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('unsubscribed_at')->nullable();

            // "one-click", "form" or "admin". Which one matters in a dispute.
            $table->string('unsubscribed_via')->nullable();

            /*
             * ── The consent record ──────────────────────────────────────────
             *
             * When, from where, and how somebody came to be on this list. Not
             * decoration: it is the artifact produced when a complaint is
             * disputed, and what CAN-SPAM and GDPR expect a sender to show.
             *
             * The user agent is truncated at 255 because a real one can be
             * longer than the column, and losing the tail is better than losing
             * the row.
             */
            $table->timestamp('subscribed_at')->nullable();
            $table->string('consent_ip', 45)->nullable();
            $table->string('consent_user_agent', 255)->nullable();
            $table->string('consent_source')->nullable();

            /*
             * ── Confirmation ────────────────────────────────────────────────
             *
             * The token is stored HASHED. A confirmation link is a bearer
             * credential: anyone reading the table should not be able to
             * confirm somebody else's subscription, and a leaked backup should
             * not hand over working links.
             *
             * Single-use and expiring — see `confirmation_expires_at`.
             */
            $table->string('confirmation_token', 64)->nullable()->index();
            $table->timestamp('confirmation_sent_at')->nullable();
            $table->timestamp('confirmation_expires_at')->nullable();

            /*
             * ── Unsubscribe ─────────────────────────────────────────────────
             *
             * DELIBERATELY NOT single-use and DELIBERATELY NOT expiring, unlike
             * the confirmation token above.
             *
             * This link lives in every message ever sent to this person, and
             * mail gets read months later. A single-use token would break the
             * second click, and an expiring one would break the whole archive —
             * turning a legally required control into a dead link, which is
             * both a compliance failure and the fastest way to earn a spam
             * complaint instead.
             *
             * It is stored in the clear for the same reason: the link has to be
             * reproducible for every future send, so there is nothing to
             * compare a hash against. It grants exactly one capability —
             * leaving a list — which is a capability we would honour from
             * anybody who asked anyway.
             */
            $table->string('unsubscribe_token', 64)->unique();

            $table->timestamps();

            // One membership per subscriber per list.
            $table->unique(['list_id', 'subscriber_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('postmaster_memberships');
    }
};
