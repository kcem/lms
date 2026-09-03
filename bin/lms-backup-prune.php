#!/usr/bin/env php
<?php

/*
 * Prune old LMS database backups (created by bin/lms-backup.php).
 *
 * Retention policy:
 *   - keep the 30 newest backups (daily),
 *   - for older ones keep only the newest backup per calendar month (monthly),
 *   - delete anything older than 5 years.
 *
 * The timestamp is taken from the filename (lms-<unixtime>-<dbversion>.sql.gz),
 * matching LMS::DatabaseCreate naming. Intended to run from the LMS daemon
 * shortly after the daily backup.
 */

$script_parameters = [
    'dry-run' => null,
];

$script_help = <<<EOF
    --dry-run    list what would be deleted, delete nothing.
EOF;

require_once('script-options.php');

$dryRun = isset($options['dry-run']);

$backupDir = ConfigHelper::getConfig('directories.backup_dir', '/var/www/backups');

$files = glob(rtrim($backupDir, '/') . '/lms-*.sql.gz');
if (empty($files)) {
    echo "Prune: no backups found in " . $backupDir . ".\n";
    exit(0);
}

$entries = [];
foreach ($files as $path) {
    if (preg_match('/lms-(\d+)-/', basename($path), $m)) {
        $entries[] = ['path' => $path, 'ts' => (int) $m[1]];
    }
}
if (empty($entries)) {
    echo "Prune: no matching backups (lms-<timestamp>-...) in " . $backupDir . ".\n";
    exit(0);
}

// newest first
usort($entries, function ($a, $b) {
    return $b['ts'] <=> $a['ts'];
});

$fiveYearsAgo = time() - (5 * 365 + 1) * 86400;
$dailyKeep = 30;

$keep = [];
$monthlySeen = [];

foreach ($entries as $i => $e) {
    if ($e['ts'] < $fiveYearsAgo) {
        continue; // older than 5 years -> not kept
    }
    if ($i < $dailyKeep) {
        $keep[$e['path']] = true; // among the 30 newest -> daily
        continue;
    }
    // older than the 30 newest: keep one per month (newest, seen first)
    $month = gmdate('Y-m', $e['ts']);
    if (!isset($monthlySeen[$month])) {
        $monthlySeen[$month] = true;
        $keep[$e['path']] = true;
    }
}

$deleted = 0;
foreach ($entries as $e) {
    if (empty($keep[$e['path']])) {
        if ($dryRun) {
            echo "would delete: " . basename($e['path']) . "\n";
            $deleted++;
        } elseif (@unlink($e['path'])) {
            $deleted++;
        }
    }
}

echo "Prune: kept " . count($keep) . ", "
    . ($dryRun ? "would delete " : "deleted ") . $deleted
    . " (30 daily + 1/month, 5y retention).\n";

exit(0);
