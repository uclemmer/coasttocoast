<?php

namespace App\Support;

/**
 * Phone numbers in, E.164 out.
 *
 * Twilio will not accept anything else, and a rep typing `(423) 757-2845` is
 * not going to type `+14237572845` instead. Normalising on save rather than
 * validating strictly is the difference between "we texted them" and "we had
 * their number all along and the format was wrong".
 *
 * US-centric by design: this is a Chattanooga fair and every number in it is a
 * North American one. A number that already carries a `+` is trusted as-is, so
 * an international rep is not mangled.
 */
final class Phone
{
    /**
     * @return string|null null when there is nothing usable to send to
     */
    public static function normalize(?string $input): ?string
    {
        if (blank($input)) {
            return null;
        }

        $trimmed = trim($input);

        // Already international: keep the country code the person gave us.
        if (str_starts_with($trimmed, '+')) {
            $digits = preg_replace('/\D/', '', $trimmed) ?? '';

            return $digits === '' ? null : '+'.$digits;
        }

        $digits = preg_replace('/\D/', '', $trimmed) ?? '';

        return match (strlen($digits)) {
            10 => '+1'.$digits,
            // 1XXXXXXXXXX — someone typed the country code without the plus.
            11 => str_starts_with($digits, '1') ? '+'.$digits : null,
            default => null,
        };
    }

    /**
     * Whether this input can be turned into something sendable. Used by the
     * validation rule so the message arrives before the save, not after.
     */
    public static function isValid(?string $input): bool
    {
        return blank($input) || self::normalize($input) !== null;
    }

    /**
     * `+14237572845` back to `(423) 757-2845` for display. Anything that is
     * not a US number is returned unchanged rather than mangled.
     */
    public static function forHumans(?string $e164): ?string
    {
        if (blank($e164)) {
            return null;
        }

        if (! preg_match('/^\+1(\d{3})(\d{3})(\d{4})$/', $e164, $parts)) {
            return $e164;
        }

        return "({$parts[1]}) {$parts[2]}-{$parts[3]}";
    }
}
