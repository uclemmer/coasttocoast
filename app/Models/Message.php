<?php

namespace App\Models;

use App\Enums\Audience;
use App\Enums\MessageChannel;
use Database\Factories\MessageFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A campaign the coordinator composes and sends (doc 07 section 3).
 *
 * The audience is stored as a rule, not as a list: `SendEventBroadcast`
 * resolves it when the message actually fires, so a note scheduled to "lapsed
 * schools" reaches whoever is lapsed at that moment (doc 07 section 2 rule 6).
 * The resolved people are then frozen into `message_recipients`.
 *
 * @property int $id
 * @property int|null $event_id
 * @property string $subject
 * @property string|null $email_body
 * @property string|null $sms_body
 * @property array<int, string> $channels
 * @property Audience $audience
 * @property array<string, mixed>|null $audience_filters
 * @property Carbon|null $scheduled_for
 * @property Carbon|null $sent_at
 * @property int $created_by
 */
class Message extends Model
{
    /** @use HasFactory<MessageFactory> */
    use HasFactory;

    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'channels' => 'array',
            'audience' => Audience::class,
            'audience_filters' => 'array',
            'scheduled_for' => 'datetime',
            'sent_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Event, $this>
     */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return HasMany<MessageRecipient, $this>
     */
    public function recipients(): HasMany
    {
        return $this->hasMany(MessageRecipient::class);
    }

    /**
     * The channels as enum cases. Stored as plain strings in a JSON column
     * because Filament's checkbox list round-trips scalars, and cast back here
     * so callers never compare raw strings.
     *
     * @return Attribute<array<int, MessageChannel>, never>
     */
    protected function channelCases(): Attribute
    {
        return Attribute::get(fn (): array => array_values(array_filter(
            array_map(
                fn (string $channel): ?MessageChannel => MessageChannel::tryFrom($channel),
                $this->channels ?? [],
            ),
        )));
    }

    public function usesChannel(MessageChannel $channel): bool
    {
        return in_array($channel, $this->channel_cases, strict: true);
    }

    public function isSent(): bool
    {
        return $this->sent_at !== null;
    }

    /**
     * The reference event the audience resolves against: the one chosen on the
     * message, or the active fair when none was.
     */
    public function referenceEvent(): ?Event
    {
        return $this->event ?? Event::active();
    }

    /**
     * Messages whose scheduled moment has arrived and which have not gone out.
     *
     * @param  Builder<Message>  $query
     */
    #[Scope]
    protected function dueToSend(Builder $query, ?Carbon $at = null): void
    {
        $query->whereNull('sent_at')
            ->whereNotNull('scheduled_for')
            ->where('scheduled_for', '<=', $at ?? Carbon::now());
    }
}
