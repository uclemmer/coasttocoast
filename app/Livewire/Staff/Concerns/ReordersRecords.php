<?php

namespace App\Livewire\Staff\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Hand-ordering, shared by the staff screens that have it (docs/13).
 *
 * Extracted at the third use — sponsors, the staff listed under a sponsor, and
 * the FAQ — rather than at the first, because the shape was not obvious until
 * two of them existed.
 *
 * WHY IT REWRITES THE WHOLE COLUMN rather than doing arithmetic on two rows.
 * `sort_order` is not guaranteed to be dense or unique: every ordered scope in
 * this app breaks ties on a second column (`name`, `id`), so two rows may
 * legitimately share a number, and swapping their values would then be a no-op.
 * Renumbering from the resulting order is cheap at these sizes and leaves the
 * column dense, which makes the next move unambiguous.
 *
 * AUTHORIZATION IS THE CALLER'S. This trait moves rows; it does not decide who
 * may. Every staff screen authorises explicitly because Filament used to do it
 * implicitly and a missed check is silent — hiding one inside a shared helper
 * would be exactly the wrong place for it.
 */
trait ReordersRecords
{
    /**
     * Move one record one place up (-1) or down (+1) within an ordered set.
     *
     * @param  Collection<int, Model>  $ordered  the full set, already in order
     * @return bool whether anything moved — false at the ends of the list, or
     *              when the id is not in the set
     */
    protected function reorderWithin(Collection $ordered, int $recordId, int $offset): bool
    {
        $ordered = $ordered->values();
        $position = $ordered->search(fn (Model $record): bool => $record->getKey() === $recordId);

        if ($position === false) {
            return false;
        }

        $target = $position + $offset;

        if ($target < 0 || $target >= $ordered->count()) {
            return false;
        }

        $rows = $ordered->all();
        [$rows[$position], $rows[$target]] = [$rows[$target], $rows[$position]];

        foreach ($rows as $index => $record) {
            $record->forceFill(['sort_order' => $index + 1])->save();
        }

        return true;
    }
}
