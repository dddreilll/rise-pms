<?php

/*
 *---------------------------------------------------------------
 * CHECK PHP VERSION
 *---------------------------------------------------------------
 */

$minPhpVersion = '8.1'; // If you update this, don't forget to update `spark`.
if (version_compare(PHP_VERSION, $minPhpVersion, '<')) {
    $message = sprintf(
        'Your PHP version must be %s or higher to run CodeIgniter. Current version: %s',
        $minPhpVersion,
        PHP_VERSION
    );

    header('HTTP/1.1 503 Service Unavailable.', true, 503);
    echo $message;

    exit(1);
}


// Default to the web installer (Hostinger / fresh shared hosting).
// The installer rewrites this to "installed" after a successful run.
$app_state = 'pre_installation';

$riseEnv = static function (string $key): string {
    $value = getenv($key);
    if ($value === false || $value === '') {
        $value = $_ENV[$key] ?? $_SERVER[$key] ?? '';
    }

    return ($value === '' || $value === false) ? '' : (string) $value;
};

// Docker Compose / Railway: DB is configured via environment — skip the installer.
$appInstalled = strtolower($riseEnv('APP_INSTALLED'));
if ($riseEnv('MYSQLHOST') !== '' || in_array($appInstalled, ['1', 'true', 'yes', 'on'], true)) {
    $app_state = 'installed';
}

// Shared hosting after manual install: Database.php no longer has installer placeholders.
$dbConfigPath = __DIR__ . '/app/Config/Database.php';
if (is_file($dbConfigPath)) {
    $dbConfigContents = file_get_contents($dbConfigPath);
    if ($dbConfigContents !== false && strpos($dbConfigContents, 'enter_hostname') === false) {
        $app_state = 'installed';
    }
}

// we don't want to access the main project before installation. redirect to installation page
if ($app_state === 'pre_installation') {
    $domain = $_SERVER['HTTP_HOST'] . $_SERVER['SCRIPT_NAME'];

    $domain = preg_replace('/index.php.*/', '', $domain); //remove everything after index.php
    if (!empty($_SERVER['HTTPS'])) {
        $domain = 'https://' . $domain;
    } else {
        $domain = 'http://' . $domain;
    }

    header("Location: $domain./install/index.php");
    exit;
}

/*
 *---------------------------------------------------------------
 * SET THE CURRENT DIRECTORY
 *---------------------------------------------------------------
 */

// Path to the front controller (this file)
define('FCPATH', __DIR__ . DIRECTORY_SEPARATOR);

// Ensure the current directory is pointing to the front controller's directory
if (getcwd() . DIRECTORY_SEPARATOR !== FCPATH) {
    chdir(FCPATH);
}

/*
 *---------------------------------------------------------------
 * BOOTSTRAP THE APPLICATION
 *---------------------------------------------------------------
 * This process sets up the path constants, loads and registers
 * our autoloader, along with Composer's, loads our constants
 * and fires up an environment-specific bootstrapping.
 */

// LOAD OUR PATHS CONFIG FILE
// This is the line that might need to be changed, depending on your folder structure.
require FCPATH . 'app/Config/Paths.php';
// ^^^ Change this line if you move your application folder

$paths = new Config\Paths();

// LOAD THE FRAMEWORK BOOTSTRAP FILE
require $paths->systemDirectory . '/Boot.php';

exit(CodeIgniter\Boot::bootWeb($paths));
