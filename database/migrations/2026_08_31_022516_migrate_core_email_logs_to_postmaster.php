<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Move the email log from laravel-core to uclemmer/laravel-postmaster.
 *
 * Core `0.5.0` removed its email log; the feature now lives in
 * `uclemmer/laravel-postmaster`, which owns the same row widened with the
 * delivery half core never had. Every column core wrote keeps its name, so this
 * is a copy rather than a transform — the added columns are all nullable except
 * `open_count`, which has a database default of 0.
 *
 * `resent_from_id` is a self-referencing foreign key, and it is why this runs in
 * two passes instead of one INSERT..SELECT. A single pass would have to insert
 * parents before children, which is only true if the rows happen to come back
 * in creation order; copying the links afterwards is correct whatever order the
 * driver chooses.
 *
 * The source table is dropped at the end. Core no longer ships the migration
 * that created it, so leaving it would be a table nothing creates on a fresh
 * install and nothing reads on an existing one.
 */
return new class extends Migration
{
    /**
     * Columns core wrote, all of which exist in `postmaster_messages` under the
     * same names. `resent_from_id` is deliberately absent — see the class
     * docblock.
     *
     * @var array<int, string>
     */
    protected array $columns = [
        'id', 'message_id', 'mailer', 'subject',
        'from', 'to', 'cc', 'bcc', 'reply_to',
        'html_body', 'text_body', 'attachments', 'headers',
        'status', 'error', 'mailable_class', 'sent_at',
        'created_at', 'updated_at',
    ];

    public function up(): void
    {
        // A fresh install has no core table to migrate: core's stub is gone, so
        // nothing created one. Not an error — just nothing to do.
        if (! Schema::hasTable('core_email_logs')) {
            return;
        }

        if (! Schema::hasTable('postmaster_messages')) {
            throw new RuntimeException(
                'postmaster_messages does not exist. Publish the package migrations '
                .'(vendor:publish --tag=postmaster-migrations) and run them before this one.'
            );
        }

        $columns = implode(', ', array_map(fn (string $c): string => '"'.$c.'"', $this->columns));

        DB::statement("insert into postmaster_messages ({$columns}) select {$columns} from core_email_logs");

        // Second pass: the self-referencing links, now that every row exists.
        DB::table('core_email_logs')
            ->whereNotNull('resent_from_id')
            ->orderBy('id')
            ->chunk(500, function ($rows): void {
                foreach ($rows as $row) {
                    DB::table('postmaster_messages')
                        ->where('id', $row->id)
                        ->update(['resent_from_id' => $row->resent_from_id]);
                }
            });

        Schema::drop('core_email_logs');
    }

    /**
     * Deliberately irreversible.
     *
     * Rolling back would mean recreating a table whose defining migration left
     * with core 0.5.0 — this migration would have to carry a second copy of
     * core's schema, which would then be the only place it existed and would
     * rot. Restore from a backup instead.
     */
    public function down(): void
    {
        throw new RuntimeException(
            'Irreversible: core 0.5.0 removed the core_email_logs schema. Restore from a backup.'
        );
    }
};
