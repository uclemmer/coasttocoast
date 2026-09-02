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
 * @property string|null $organization_sort_name
 * @property Carbon|null $notified_at
 */
class EventInterest extends Model
{
    /** @use HasFactory<EventInterestFactory> */
    use HasFactory;

    protected $guarded = ['id'];

    /**
     * Keep `organization_sort_name` in step with the name the visitor typed.
     *
     * The staff interest list alphabetizes on it, through the same
     * `Organization::sortName()` the roster and the delivery table use — one
     * alphabet across all three (doc 10, D-10-c). Null when the signup skipped
     * the optional organization field, so those rows sort first.
     */
    protected static function booted(): void
    {
        static::saving(function (EventInterest $interest): void {
            if ($interest->isDirty('organization_name')) {
                $interest->organization_sort_name = $interest->organization_name === null
                    ? null
                    : Organization::sortName($interest->organization_name);
            }
        });
    }

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
