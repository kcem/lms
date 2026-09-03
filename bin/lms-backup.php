#!/usr/bin/env php
<?php

/*
 * LMS database backup — same mechanism as the web UI (LMS::DatabaseCreate / DBDump).
 *
 * Default (daily): DatabaseCreate(true) -> lms-<timestamp>-<DBVERSION>.sql.gz.
 *   On a DB without TERYT, the empty location_* and stats tables are skipped
 *   automatically by DBDump (it skips empty tables and, with stats=false, stats),
 *   so daily backups stay small. Pruned by bin/lms-backup-prune.php.
 *
 * --full (monthly): DBDump(..., stats=true) into the full/ subdirectory as
 *   lms-<timestamp>-<DBVERSION>.sql.gz. A complete dump (includes stats and any TERYT
 *   data). It keeps the standard name so the LMS UI can restore it (the UI only accepts
 *   lms-<digits>-<digits>.sql.gz); the separate full/ dir keeps it out of the daily
 *   prune (which operates on backup_dir only). No retention on full backups yet.
 *
 * Intended to run from the LMS daemon (daily without --full, monthly with --full).
 */

$script_parameters = [
    'stats' => null,
    'full' => null,
];

$script_help = <<<EOF
    --stats    include the stats table in the daily dump (default: skipped).
    --full     complete monthly backup (includes stats/TERYT), named lms-full-<ts>-...
EOF;

require_once('script-options.php');

$SYSLOG = SYSLOG::getInstance();
$AUTH = null;
$LMS = new LMS($DB, $AUTH, $SYSLOG);
$LMS->setPluginManager(LMSPluginManager::getInstance());

$full = isset($options['full']);
$includeStats = $full || isset($options['stats']);

$backupDir = ConfigHelper::getConfig('directories.backup_dir', '/var/www/backups');
if (!is_dir($backupDir)) {
    @mkdir($backupDir, 0775, true);
}

if ($full) {
    // Monthly full backup: standard name (UI-restorable) in a separate full/ dir
    // so the daily prune (backup_dir only) leaves it alone.
    $fullDir = $backupDir . DIRECTORY_SEPARATOR . 'full';
    if (!is_dir($fullDir)) {
        @mkdir($fullDir, 0775, true);
    }
    $filename = $fullDir . DIRECTORY_SEPARATOR . 'lms-' . time() . '-' . DBVERSION . '.sql.gz';
    $result = $LMS->DBDump($filename, true, true);
    $label = "full → full/" . basename($filename) . " (stats=yes)";
} else {
    // Daily backup — same as the web UI.
    $result = $LMS->DatabaseCreate(true, $includeStats);
    $label = "daily (gzipped, stats=" . ($includeStats ? "yes" : "no") . ")";
}

if ($result === false) {
    fwrite(STDERR, "LMS backup FAILED.\n");
    exit(1);
}

echo "LMS backup created in " . $backupDir . " — " . $label . ".\n";
exit(0);
