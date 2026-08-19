<?php

namespace App\Models;

use Database\Factories\EventInterestFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Somebody who asked to be told when registration opens (R2.7).
 *
 * No account and no organization: this is the person who finds the site the
 * week after registration closed, and asking them to sign up first is how the
 * lead is lost.
 *
 * @property int $id
 * @property int $event_id
 * @property string $email
 * @property string|null $organization_name
 * @property Carbon|null $notified_at
 */
class EventInterest extends Model
{
    /** @use HasFactory<EventInterestFactory> */
    use HasFactory;

    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'notified_at' => 'datetime',
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
     * Rows the announcement action has not reached yet. Re-running the action
     * must be a no-op (card 6.5), so this scope - not the whole table - is
     * what it sends to.
     *
     * @param  Builder<EventInterest>  $query
     */
    #[Scope]
    protected function unnotified(Builder $query): void
    {
        $query->whereNull('notified_at');
    }
}
