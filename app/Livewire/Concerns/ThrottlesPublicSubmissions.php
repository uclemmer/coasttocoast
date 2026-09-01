<?php

namespace App\Livewire\Concerns;

use Illuminate\Support\Facades\RateLimiter;

/**
 * The abuse defences shared by the two public forms — the contact form and the
 * "tell me when registration opens" capture.
 *
 * Both are unauthenticated, both write to the database, and one of them sends
 * two emails per submission. laravel-core's own contact route carries defences
 * like these, but a Livewire submit never touches that route, so they are ours
 * (doc 10, D-8-d).
 *
 * Extracted 2026-08-19 when the ordering below was corrected. The two forms had
 * identical copies of this logic, which is exactly how one of them ends up
 * fixed and the other does not.
 */
trait ThrottlesPublicSubmissions
{
    /**
     * Submissions allowed per IP per hour.
     *
     * The plain POST route behind the interest form carries `throttle:5,60` to
     * match — it is the non-JavaScript path to the same table, and a limit that
     * only guards the JavaScript path is not a limit. `PublicSiteTest` asserts
     * the two agree, because nothing else connects them.
     */
    public const MAX_ATTEMPTS_PER_HOUR = 5;

    public const DECAY_SECONDS = 3600;

    /**
     * The honeypot.
     *
     * Rendered off-screen rather than `type="hidden"` — bots skip hidden inputs
     * and fill visible ones, so it has to be present and visually hidden. It
     * lives here so both forms cannot disagree about the field name, which is
     * the one thing a bot author would need to know.
     */
    public string $website = '';

    /**
     * Whether this submission should be dropped, having recorded the attempt.
     *
     * The order matters and is the whole point of this method.
     *
     * The limiter is checked first, then **incremented before the honeypot is
     * examined**, so a submission that trips the honeypot spends its allowance
     * like any other. Counting only successes — which is what both forms used
     * to do — meant a bot that filled the honeypot was told "something went
     * wrong" and could retry for ever, booting the framework every time. A
     * visitor never fills that field, so charging for it costs them nothing.
     *
     * Validation failures are still uncounted, deliberately: they run before
     * this method is reached, and somebody mistyping their address three times
     * should not burn an hour's allowance.
     */
    protected function rejectedAsAbuse(string $bucket, string $errorField, string $throttleMessage): bool
    {
        $key = $bucket.':'.request()->ip();

        if (RateLimiter::tooManyAttempts($key, maxAttempts: self::MAX_ATTEMPTS_PER_HOUR)) {
            $this->addError($errorField, $throttleMessage);

            return true;
        }

        RateLimiter::hit($key, decaySeconds: self::DECAY_SECONDS);

        if (filled($this->website)) {
            // Deliberately vague, and deliberately the same shape of error a
            // real fault would produce: a bot should not learn which field
            // caught it.
            $this->addError($errorField, __('Something went wrong. Please try again.'));

            return true;
        }

        return false;
    }
}
