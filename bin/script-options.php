<?php

/*
 * LMS version 1.11-git
 *
 *  (C) Copyright 2001-2024 LMS Developers
 *
 *  Please, see the doc/AUTHORS for more information about authors!
 *
 *  This program is free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License Version 2 as
 *  published by the Free Software Foundation.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  You should have received a copy of the GNU General Public License
 *  along with this program; if not, write to the Free Software
 *  Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA 02111-1307,
 *  USA.
 *
 *  $Id$
 */

ini_set('error_reporting', E_ALL & ~E_NOTICE & ~E_DEPRECATED);

define('SCRIPT_COPYRIGHT_INFO', '(c) 2001-2024 LMS Developers');

$long_to_shorts = array();
$short_to_longs = array();
foreach ($script_parameters as $long => $short) {
    $long = str_replace(':', '', $long);
    if (isset($short)) {
        $short = str_replace(':', '', $short);
        $short_to_longs[$short] = $long;
    }
    $long_to_shorts[$long] = $short;
}

$options = getopt(
    implode(
        '',
        array_filter(
            array_values($script_parameters),
            function ($value) {
                return isset($value);
            }
        )
    ),
    array_keys($script_parameters)
);

foreach (array_flip(array_filter($long_to_shorts, function ($value) {
    return isset($value);
})) as $short => $long) {
    if (array_key_exists($short, $options)) {
        $options[$long] = $options[$short];
        unset($options[$short]);
    }
}

foreach ($argv as $arg) {
    if (strpos($arg, '-') !== 0) {
        continue;
    }

    if (strpos($arg, '-', 1) === 1) {
        $option = preg_replace('/^--/', '', $arg);
        if (!isset($long_to_shorts[$option])) {
            die('Fatal error: unsupported option \'' . $arg . '\'!' . PHP_EOL);
        } else {
            if (!isset($options[$option])) {
                die('Fatal error: option \'' . $arg . '\' requires parameter!' . PHP_EOL);
            }
        }
    } else {
        $option = substr(preg_replace('/^-/', '', $arg), 0, 1);
        if (!isset($short_to_longs[$option])) {
            die('Fatal error: unsupported option \'-' . $option . '\'!' . PHP_EOL);
        } else {
            if (!isset($options[$short_to_longs[$option]])) {
                die('Fatal error: option \'-' . $option . '\' requires parameter!' . PHP_EOL);
            }
        }
    }
}

$script_name = basename($argv[0]);

if (isset($options['version'])) {
    echo $script_name . PHP_EOL
        . SCRIPT_COPYRIGHT_INFO . PHP_EOL
        . PHP_EOL;
    exit(0);
}

if (isset($options['help'])) {
    echo $script_name . PHP_EOL
        . SCRIPT_COPYRIGHT_INFO . PHP_EOL
        . PHP_EOL
        . $script_help . PHP_EOL;
    exit(0);
}

$quiet = isset($options['quiet']);
if (!$quiet) {
    echo $script_name . PHP_EOL
        . SCRIPT_COPYRIGHT_INFO . PHP_EOL;
}

if (isset($options['config-file'])) {
    $CONFIG_FILE = $options['config-file'];
} else {
    $CONFIG_FILE = DIRECTORY_SEPARATOR . 'etc' . DIRECTORY_SEPARATOR . 'lms' . DIRECTORY_SEPARATOR . 'lms.ini';
}

if (!$quiet) {
    echo 'Using file ' . $CONFIG_FILE . ' as config.' . PHP_EOL;
}

if (!is_readable($CONFIG_FILE)) {
    die('Unable to read configuration file \'' . $CONFIG_FILE . '\'!' . PHP_EOL);
}

define('CONFIG_FILE', $CONFIG_FILE);

$CONFIG = (array) parse_ini_file($CONFIG_FILE, true);

// Check for configuration vars and set default values
$CONFIG['directories']['sys_dir'] = (!isset($CONFIG['directories']['sys_dir']) ? getcwd() : $CONFIG['directories']['sys_dir']);
$CONFIG['directories']['lib_dir'] = (!isset($CONFIG['directories']['lib_dir']) ? $CONFIG['directories']['sys_dir'] . DIRECTORY_SEPARATOR . 'lib' : $CONFIG['directories']['lib_dir']);
$CONFIG['directories']['doc_dir'] = (!isset($CONFIG['directories']['doc_dir']) ? $CONFIG['directories']['sys_dir'] . DIRECTORY_SEPARATOR . 'documents' : $CONFIG['directories']['doc_dir']);
$CONFIG['directories']['smarty_compile_dir'] = (!isset($CONFIG['directories']['smarty_compile_dir']) ? $CONFIG['directories']['sys_dir'] . DIRECTORY_SEPARATOR . 'templates_c' : $CONFIG['directories']['smarty_compile_dir']);
$CONFIG['directories']['smarty_templates_dir'] = (!isset($CONFIG['directories']['smarty_templates_dir']) ? $CONFIG['directories']['sys_dir'] . DIRECTORY_SEPARATOR . 'templates' : $CONFIG['directories']['smarty_templates_dir']);
$CONFIG['directories']['plugin_dir'] = (!isset($CONFIG['directories']['plugin_dir']) ? $CONFIG['directories']['sys_dir'] . DIRECTORY_SEPARATOR . 'plugins' : $CONFIG['directories']['plugin_dir']);
$CONFIG['directories']['plugins_dir'] = $CONFIG['directories']['plugin_dir'];

define('SYS_DIR', $CONFIG['directories']['sys_dir']);
define('LIB_DIR', $CONFIG['directories']['lib_dir']);
define('DOC_DIR', $CONFIG['directories']['doc_dir']);
define('SMARTY_COMPILE_DIR', $CONFIG['directories']['smarty_compile_dir']);
define('SMARTY_TEMPLATES_DIR', $CONFIG['directories']['smarty_templates_dir']);
define('PLUGIN_DIR', $CONFIG['directories']['plugin_dir']);
define('PLUGINS_DIR', $CONFIG['directories']['plugin_dir']);

// Load autoloader
$composer_autoload_path = SYS_DIR . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';
if (file_exists($composer_autoload_path)) {
    require_once $composer_autoload_path;
} else {
    die("Composer autoload not found. Run 'composer install' command from LMS directory and try again. More information at https://getcomposer.org/" . PHP_EOL);
}

// Init database

$DB = null;

try {
    $DB = LMSDB::getInstance();
} catch (Exception $ex) {
    trigger_error($ex->getMessage(), E_USER_WARNING);
    // can't work without database
    die("Fatal error: cannot connect to database!" . PHP_EOL);
}

// Include required files (including sequence is important)

require_once(LIB_DIR . DIRECTORY_SEPARATOR . 'common.php');
require_once(LIB_DIR . DIRECTORY_SEPARATOR . 'language.php');
require_once(LIB_DIR . DIRECTORY_SEPARATOR . 'definitions.php');