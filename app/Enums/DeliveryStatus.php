<?php

namespace App\Enums;

/**
 * Per-recipient, per-channel delivery state on `message_recipients`.
 *
 * For email this is normally a *fallback*: once laravel-core's EmailLog is
 * enabled the authoritative status is derived from the linked `core_email_logs`
 * row (doc 07 §4 rule 3), and the local column only answers for rows with no
 * log — SMS-only recipients, or an environment with logging off. The vocabulary
 * matches core's (`sending|sent|failed`) so the two can be read as one value,
 * plus `Skipped` for recipients we deliberately did not contact (no phone, or
 * not opted in to SMS).
 */
enum DeliveryStatus: string
{
    case Pending = 'pending';
    case Sending = 'sending';
    case Sent = 'sent';
    case Failed = 'failed';
    case Skipped = 'skipped';

    public function getLabel(): string
    {
        return match ($this) {
            self::Pending => __('Queued'),
            self::Sending => __('Sending'),
            self::Sent => __('Sent'),
            self::Failed => __('Failed'),
            self::Skipped => __('Not sent'),
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Pending => 'gray',
            self::Sending => 'warning',
            self::Sent => 'success',
            self::Failed => 'danger',
            self::Skipped => 'gray',
        };
    }

    /**
     * Translate laravel-core's EmailLog status string into ours. Core uses
     * `sending|sent|failed`; anything unrecognised degrades to `Pending`
     * rather than throwing, because a delivery table must render even if the
     * package adds a status we have not seen.
     */
    public static function fromEmailLog(?string $status): self
    {
        return match ($status) {
            'sending' => self::Sending,
            'sent' => self::Sent,
            'failed' => self::Failed,
            default => self::Pending,
        };
    }
}
