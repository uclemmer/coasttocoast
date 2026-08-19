<?php

namespace App\Models;

use App\Enums\DeliveryStatus;
use Database\Factories\MessageRecipientFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use UClemmer\LaravelCore\EmailLog\EmailLog;

/**
 * One frozen line of a campaign's delivery list (doc 07 sections 2 and 4).
 *
 * The ULID key is not decoration: it travels out as the `X-CTC-Recipient-Id`
 * header and comes back through laravel-core's `EmailLogged` event, and a
 * sequential integer in a mail header is guessable by anyone who receives one
 * campaign.
 *
 * Every foreign key is nullable by design - interest-list recipients have no
 * school and no account, lapsed recipients have no current registration, and
 * the generic admissions_email fallback has a school but nobody behind it. The
 * snapshots are what was actually used, so a later profile edit cannot rewrite
 * the record of who we mailed.
 *
 * @property string $id
 * @property int $message_id
 * @property int|null $registration_id
 * @property int|null $user_id
 * @property int|null $organization_id
 * @property string|null $organization_name
 * @property string|null $name
 * @property string $email
 * @property string|null $phone
 * @property DeliveryStatus $email_status
 * @property DeliveryStatus $sms_status
 * @property string|null $email_log_id
 * @property string|null $error
 */
class MessageRecipient extends Model
{
    /** @use HasFactory<MessageRecipientFactory> */
    use HasFactory, HasUlids;

    /**
     * The header this id rides out on. Named for the app rather than reusing
     * core's `X-Core-Email-Log-Id`, which the package sets for its own purpose
     * on the same message.
     */
    public const HEADER = 'X-CTC-Recipient-Id';

    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_status' => DeliveryStatus::class,
            'sms_status' => DeliveryStatus::class,
        ];
    }

    /**
     * @return BelongsTo<Message, $this>
     */
    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class);
    }

    /**
     * @return BelongsTo<Registration, $this>
     */
    public function registration(): BelongsTo
    {
        return $this->belongsTo(Registration::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * The laravel-core log row for this send, linked by the EmailLogged
     * listener. No database-level foreign key: `core:prune-email-logs` deletes
     * these on a schedule and must not be blocked by campaign history, nor
     * take it down with it.
     *
     * @return BelongsTo<EmailLog, $this>
     */
    public function emailLog(): BelongsTo
    {
        return $this->belongsTo(EmailLog::class, 'email_log_id');
    }

    /**
     * The authoritative email status.
     *
     * When a log row is linked, it wins - it is what the transport actually
     * reported, and `core:prune-email-logs` keeps it honest by promoting stale
     * `sending` rows to `failed`. The local column is the fallback for rows
     * with no log: SMS-only recipients, or an environment with email logging
     * turned off (doc 07 section 4 rule 3).
     */
    public function resolvedEmailStatus(): DeliveryStatus
    {
        $log = $this->email_log_id ? $this->emailLog : null;

        return $log instanceof EmailLog
            ? DeliveryStatus::fromEmailLog($log->status->value)
            : $this->email_status;
    }
}
