<?php

declare(strict_types=1);

/**
 * Builds a deployment zip for shared hosting (InfinityFree):
 *
 *   php tools/package.php [output.zip]
 *
 * Layout of the zip:
 *   htdocs/     upload the CONTENTS of this folder into the host's htdocs/
 *   database/   import into the database with phpMyAdmin (never uploaded to htdocs)
 *   README-DEPLOY.md
 *
 * Excludes tests, tools, docs, vendor, git files and config/local.php (secrets).
 */

$root = dirname(__DIR__);
$out = $argv[1] ?? $root . '/build/ppms-infinityfree.zip';

if (!class_exists(ZipArchive::class)) {
    fwrite(STDERR, "PHP zip extension is required (ZipArchive)\n");
    exit(1);
}
@mkdir(dirname($out), 0775, true);
@unlink($out);

$zip = new ZipArchive();
if ($zip->open($out, ZipArchive::CREATE) !== true) {
    fwrite(STDERR, "Cannot create $out\n");
    exit(1);
}

$appEntries = ['.htaccess', 'app', 'config', 'public', 'resources', 'routes', 'storage'];
$skip = [
    'config/local.php',            // contains credentials
    'storage/uploads/',            // runtime data
    'storage/logs/',
    'storage/cache/',
];
$count = 0;

foreach ($appEntries as $entry) {
    $path = $root . '/' . $entry;
    $files = is_dir($path)
        ? new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS))
        : [new SplFileInfo($path)];
    foreach ($files as $file) {
        $rel = ltrim(str_replace($root, '', $file->getPathname()), '/');
        $isGitkeep = basename($rel) === '.gitkeep';
        foreach ($skip as $s) {
            if (($rel === $s || str_starts_with($rel, $s)) && !$isGitkeep) {
                continue 2;
            }
        }
        $zip->addFile($file->getPathname(), 'htdocs/' . $rel);
        $count++;
    }
}

foreach (['schema_shared_hosting.sql', 'seed.sql', 'seed_demo.sql'] as $sql) {
    $zip->addFile($root . '/database/' . $sql, 'database/' . $sql);
    $count++;
}
$zip->addFile($root . '/docs/deploy-infinityfree.md', 'README-DEPLOY.md');
$count++;

$zip->close();
printf("Wrote %s (%d files, %.1f KB)\n", $out, $count, filesize($out) / 1024);
