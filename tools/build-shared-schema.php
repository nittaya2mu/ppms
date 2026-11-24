<?php

declare(strict_types=1);

/**
 * Generates database/schema_shared_hosting.sql from database/schema.sql.
 *
 * Free/shared hosts such as InfinityFree grant only ALTER, CREATE, DELETE, DROP, INDEX,
 * INSERT, SELECT, UPDATE and LOCK TABLES: no TRIGGER and no REFERENCES privilege.
 * The generated file therefore omits foreign keys and triggers. The same rules are
 * enforced in PHP (App\Core\IntegrityGuard and App\Services\Rules\*), so the application
 * behaves the same; only the database-level second line of defence is missing.
 *
 * Usage: php tools/build-shared-schema.php
 * Run it after every change to schema.sql (tests check the two files are in sync).
 */

$root = dirname(__DIR__);
$source = file_get_contents($root . '/database/schema.sql');
if ($source === false) {
    fwrite(STDERR, "Cannot read database/schema.sql\n");
    exit(1);
}

echo build_shared_schema($source, $root . '/database/schema_shared_hosting.sql');

function build_shared_schema(string $source, ?string $target = null): string
{
    // 0. Replace the source's leading comment block with our own header (added in step 4).
    $sql = preg_replace('/\A(--[^\n]*\n)+/', '', $source);

    // 1. Drop the trigger section entirely.
    $sql = preg_replace('/^DELIMITER \$\$.*?^DELIMITER ;\s*$/ms', '', $sql);

    // 2. Drop ALTER TABLE ... ADD CONSTRAINT fk_... statements (circular FKs).
    $sql = preg_replace('/^ALTER TABLE \w+\s+ADD CONSTRAINT fk_.*?;\s*$/ms', '', $sql);

    // 3. Drop inline FK constraint lines and comments about them, then fix trailing commas.
    $out = [];
    foreach (preg_split('/\R/', $sql) as $line) {
        $trim = trim($line);
        if (preg_match('/^CONSTRAINT fk_\w+\s+FOREIGN KEY/', $trim) || str_starts_with($trim, '-- fk to')) {
            continue;
        }
        if (str_starts_with($trim, ') ENGINE=')) {
            // Remove the comma from the last real column/index line before the closing parenthesis.
            for ($i = count($out) - 1; $i >= 0; $i--) {
                $prev = trim($out[$i]);
                if ($prev === '' || str_starts_with($prev, '--')) {
                    continue;
                }
                $out[$i] = preg_replace('/,(\s*(--.*)?)$/', '$1', $out[$i]);
                break;
            }
        }
        $out[] = $line;
    }
    $sql = implode("\n", $out);

    // 4. Header.
    $header = "-- =============================================================================\n"
        . "-- GENERATED FILE - do not edit. Source: database/schema.sql\n"
        . "-- Regenerate with: php tools/build-shared-schema.php\n"
        . "--\n"
        . "-- Variant for shared hosting WITHOUT trigger / foreign-key privileges\n"
        . "-- (e.g. InfinityFree). Foreign keys and triggers are omitted; the same business\n"
        . "-- rules are enforced by the PHP application layer.\n"
        . "--\n"
        . "-- Create the (empty) database in the hosting control panel, select it in\n"
        . "-- phpMyAdmin, then Import this file, then seed.sql.\n"
        . "-- Re-importing DROPS ALL PPMS TABLES AND DATA in the selected database.\n"
        . "-- =============================================================================\n\n";
    $sql = $header . preg_replace("/\n{3,}/", "\n\n", trim($sql)) . "\n";

    if ($target !== null) {
        file_put_contents($target, $sql);
        $fk = substr_count($source, 'FOREIGN KEY');
        $tr = preg_match_all('/^CREATE TRIGGER/m', $source);
        return sprintf("Wrote %s (removed %d foreign keys, %d triggers)\n", basename($target), $fk, $tr);
    }
    return $sql;
}
