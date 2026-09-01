<?php

namespace App\Models;

use Database\Factories\FaqItemFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * One question on the public FAQ page.
 *
 * @property int $id
 * @property string $question
 * @property string $answer
 * @property string|null $attachment_path
 * @property string|null $attachment_name
 * @property int $sort_order
 * @property bool $is_published
 */
class FaqItem extends Model
{
    /** @use HasFactory<FaqItemFactory> */
    use HasFactory;

    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_published' => 'boolean',
        ];
    }

    /**
     * The disk FAQ attachments live on.
     *
     * Private, not `public`. The download goes through a route that checks the
     * question is published, so unpublishing a question actually withdraws its
     * file — on the public disk the URL would keep serving for ever, and a
     * signed W-9 carries the fair's EIN and an authorised signature. See
     * docs/10, D-9-c.
     */
    public const ATTACHMENT_DISK = 'local';

    public const ATTACHMENT_DIRECTORY = 'faq-attachments';

    public function hasAttachment(): bool
    {
        return filled($this->attachment_path);
    }

    /**
     * What to call the file when a visitor saves it.
     *
     * Falls back to the stored basename: an older row, or one written by a
     * fixture, may have a path and no remembered name, and a download with no
     * filename at all is worse than an ugly one.
     */
    public function attachmentDownloadName(): string
    {
        return $this->attachment_name ?: basename((string) $this->attachment_path);
    }

    /**
     * What the public page shows, in the coordinator's chosen order.
     *
     * @param  Builder<FaqItem>  $query
     */
    #[Scope]
    protected function published(Builder $query): void
    {
        $query->where('is_published', true)->orderBy('sort_order')->orderBy('id');
    }
}
