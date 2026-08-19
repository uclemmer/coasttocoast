<?php

namespace App\Models;

use Database\Factories\SponsorStaffFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A named person listed under a sponsor on the public Sponsors page.
 *
 * @property int $id
 * @property int $sponsor_id
 * @property string $name
 * @property string|null $title
 * @property int $sort_order
 */
class SponsorStaff extends Model
{
    /** @use HasFactory<SponsorStaffFactory> */
    use HasFactory;

    /**
     * Laravel would pluralise this to `sponsor_staffs`. The table is named for
     * the English, not the convention.
     */
    protected $table = 'sponsor_staff';

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
     * @return BelongsTo<Sponsor, $this>
     */
    public function sponsor(): BelongsTo
    {
        return $this->belongsTo(Sponsor::class);
    }
}
