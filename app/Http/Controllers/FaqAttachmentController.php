<?php

namespace App\Http\Controllers;

use App\Models\FaqItem;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Serves the file attached to a published FAQ answer — the signed W-9 above
 * all (doc 11's owner queue).
 *
 * **A route rather than a public-disk URL, deliberately** (docs/10, D-9-c).
 * `Storage::disk('public')->url()` would have been one line, but it hands out a
 * URL that nginx serves directly and for ever: unpublishing the question would
 * not withdraw the file, and a signed W-9 carries the fair's EIN and an
 * authorised signature. Going through PHP costs a boot per download — a rare
 * event on a page like this — and buys the publish check.
 *
 * Still unauthenticated: the FAQ is public and the W-9 is meant for any college
 * that asks. What this closes is the gap between "the coordinator took it down"
 * and "it stopped being downloadable".
 */
class FaqAttachmentController extends Controller
{
    public function __invoke(FaqItem $faqItem): StreamedResponse
    {
        // 404 rather than 403 throughout: whether a draft question exists is
        // not something a visitor needs confirmed, and it matches how an
        // unpublished fair is hidden (doc 10, D-5.4-c).
        abort_unless($faqItem->is_published && $faqItem->hasAttachment(), 404);

        $disk = Storage::disk(FaqItem::ATTACHMENT_DISK);

        // The row can outlive the file — a restore from a database backup
        // without the storage directory, or a file removed by hand. Without
        // this the visitor gets a 500 on a link the page itself rendered.
        abort_unless($disk->exists((string) $faqItem->attachment_path), 404);

        return $disk->download(
            (string) $faqItem->attachment_path,
            $faqItem->attachmentDownloadName(),
        );
    }
}
