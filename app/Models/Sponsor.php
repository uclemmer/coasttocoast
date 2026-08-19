<?php

namespace App\Models;

use Database\Factories\SponsorFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An organization that underwrites the fair.
 *
 * @property int $id
 * @property string $name
 * @property string|null $website
 * @property string|null $logo_path
 * @property int $sort_order
 */
class Sponsor extends Model
{
    /** @use HasFactory<SponsorFactory> */
    use HasFactory;

    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
        ];
    }

    /**
     * @return HasMany<SponsorStaff, $this>
     */
    public function staff(): HasMany
    {
        return $this->hasMany(SponsorStaff::class)->orderBy('sort_order')->orderBy('name');
    }

    /**
     * Display order. Hand-ordered rather than alphabetical because sponsors
     * pay for billing position, and ties fall back to name so the list is
     * stable across page loads.
     *
     * @param  Builder<Sponsor>  $query
     */
    #[Scope]
    protected function ordered(Builder $query): void
    {
        $query->orderBy('sort_order')->orderBy('name');
    }
}
