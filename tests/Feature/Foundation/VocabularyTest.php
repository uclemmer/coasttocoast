<?php

use Illuminate\Support\Facades\Schema;
use Symfony\Component\Finder\Finder;

/*
 * The entity is an Organization, never a School (renamed 2026-09-01, doc 17).
 *
 * The schema and the model had carried the organization name since card 1.2,
 * but the vocabulary around them had not: a thousand-odd occurrences of
 * "school" survived in copy, docblocks, local variables and test fixtures, so
 * the UI said one thing and the column said another. The rename closed that.
 *
 * It needs a test rather than a note because nothing about writing `$school`
 * for an Organization fails — it compiles, it renders, the suite stays green,
 * and the drift comes back one file at a time.
 *
 * The four sponsoring preparatory schools and the fair's high-school visitors
 * are a genuinely different referent and keep the word. That is the whole
 * reason this cannot be a plain `grep -c school`: the allow-list below is the
 * distinction, and it is deliberately phrase-shaped rather than file-shaped so
 * a new sponsor line passes and a new `$school` variable does not.
 */

/** Phrases where "school" is not the Organization entity. */
const NON_ENTITY_SENSES = [
    // The four sponsoring preparatory schools (doc 00) and their assets.
    '/Baylor\s+School|baylorschool/i',
    '/McCallie\s+School/i',
    '/Girls\s+Preparatory\s+School/i',
    '/Sewanee\s+School/i',
    '/prep(?:aratory)?\s+schools?/i',
    '/sponsor(?:ing|-)?\s*schools?/i',
    '/(?:four|4)\s+schools?/i',
    '/school\'s\s+name/i',
    '/the\s+school\s+→\s+Logo/iu',
    '/puts\s+a\s+school\s+second/i',

    // The visitors the fair is for: high schoolers and their parents.
    '/high\s+school/i',
];

/** @return array<int, string> */
function entitySenseUsesOfSchool(string $source): array
{
    $stripped = preg_replace(NON_ENTITY_SENSES, ' ', $source) ?? $source;

    preg_match_all('/^.*\bschools?\b.*$/mi', $stripped, $matches);

    return array_map(trim(...), $matches[0]);
}

it('calls the entity an organization everywhere in the source', function () {
    $files = Finder::create()
        ->files()
        ->in([app_path(), config_path(), database_path(), resource_path('views'), base_path('routes'), base_path('tests')])
        ->name(['*.php', '*.blade.php'])
        // This file quotes the word in its allow-list; scanning it would flag
        // the patterns that exist to stop the flagging.
        ->notName('VocabularyTest.php');

    $offenders = [];

    foreach ($files as $file) {
        foreach (entitySenseUsesOfSchool((string) $file->getContents()) as $line) {
            $offenders[] = $file->getRelativePathname().': '.$line;
        }
    }

    expect($offenders)->toBe([], "\"school\" still means the organization in:\n".implode("\n", $offenders));
});

it('names no table or column after a school', function () {
    // The schema was already organization-named before the rename, and the
    // point of asserting it is that it stays that way: a migration adding a
    // `school_id` would sail past the source check above by being a column.
    $offenders = [];

    foreach (Schema::getTableListing(schema: null, schemaQualified: false) as $table) {
        if (str_contains(strtolower($table), 'school')) {
            $offenders[] = $table;
        }

        foreach (Schema::getColumnListing($table) as $column) {
            if (str_contains(strtolower($column), 'school')) {
                $offenders[] = $table.'.'.$column;
            }
        }
    }

    expect($offenders)->toBe([], 'school-named schema: '.implode(', ', $offenders));
});
