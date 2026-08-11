<?php

/*
 * 1. Check fo a config file
 *   a. If not exists don't include odm-init
 *   b. If exists, include odm-init
 */

require __DIR__ . '/../application/vendor/autoload.php';
// Create simple request object to replace Zend Diactoros
$request = (object) [
    'method' => $_SERVER['REQUEST_METHOD'] ?? 'GET',
    'uri' => $_SERVER['REQUEST_URI'] ?? '/',
    'attributes' => []
];

// Simple method to add attributes to request object
$request->withAttribute = function($key, $val) use ($request) {
    $request->attributes[$key] = $val;
    return $request;
};

$request->getAttribute = function($key) use ($request) {
    return $request->attributes[$key] ?? null;
};

// Configure session for Docker environment (before any session_start)
if (getenv('IS_DOCKER')) {
    ini_set('session.cookie_domain', '');
    ini_set('session.cookie_path', '/');
    // Only force the Secure flag off when the request genuinely isn't
    // HTTPS. Docker deployments are commonly reached through a
    // TLS-terminating reverse proxy — unconditionally disabling this
    // (the previous behavior) silently weakened cookie security for
    // exactly the deployment path most likely to be internet-facing,
    // and fought against .htaccess's own "session.cookie_secure On".
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
    ini_set('session.cookie_secure', $isHttps);
    ini_set('session.cookie_httponly', true);
    ini_set('session.cookie_samesite', 'Lax');
    ini_set('session.use_strict_mode', true);
}

set_include_path(get_include_path() . PATH_SEPARATOR .'../application/');
set_include_path(get_include_path() . PATH_SEPARATOR .'../application/controllers/helpers');
set_include_path(get_include_path() . PATH_SEPARATOR .'../application/models');
set_include_path(get_include_path() . PATH_SEPARATOR .'../application/includes/smarty/');

spl_autoload_register(function ($class) {
    include $class . '.class.php';
});

// Load installer classes
require_once __DIR__ . '/../application/installer/ConfigManager.php';
require_once __DIR__ . '/../application/installer/DatabaseManager.php';

// @TODO: Re-enable aura router
// Temporarily disable Aura Router due to dependency issues
// $routerContainer = new Aura\Router\RouterContainer();
// $map = $routerContainer->getMap();

$configExists = true;
$configManager = new ConfigManager();
if ($configManager->configExists()) {
    $configManager->loadConfig();
} else {
    $configExists = false;
    if (false === strpos($_SERVER['REQUEST_URI'], 'setup-config')
        && false === strpos($_SERVER['REQUEST_URI'], 'installer')) {
        header('Location: /installer/setup-config');
        exit;
    }
}

if ($configExists) {
    try {
        $dbManager = new DatabaseManager(APP_DB_HOST, APP_DB_NAME, APP_DB_USER, APP_DB_PASS);
        $pdo = $dbManager->connect();
    } catch (PDOException $e) {
        print "Error!: " . $e->getMessage() . "<br/>";
        die();
    }

    $prefix = $GLOBALS['CONFIG']['db_prefix'] ?? 'odm_';
    $odmsysExists = $dbManager->odmsysTableExists($prefix);

    if ($odmsysExists) {
        $requestUri = $_SERVER['REQUEST_URI'] ?? '/';
        $path = parse_url($requestUri, PHP_URL_PATH);
        $path = trim($path, '/');

        if (strpos($path, 'install/') !== 0 && strpos($path, 'installer') !== 0) {
            require '../application/version.php';
            require '../application/models/Settings.class.php';

            $current_db_version = Settings::get_db_version($pdo);

            if ($current_db_version !== $GLOBALS['CONFIG']['required_db_version']) {
                header('Location: /installer');
                exit;
            }
        }

        require '../application/controllers/helpers/functions.php';
        require '../application/odm-init.php';
    } else {
        if (!function_exists('callPluginMethod')) {
            function callPluginMethod($method, $args = '') {
                return;
            }
        }
    }
}

// CSRF protector library removed - will be replaced with better implementation
require '../application/version.php';
require '../application/models/classHeaders.php';
require '../application/models/File.class.php';
require '../application/controllers/helpers/crumb.php';
require '../application/controllers/helpers/udf_functions.php';

// Temporarily use simple routing fallback instead of complex Aura Router setup
$requestUri = $_SERVER['REQUEST_URI'] ?? '/';
$requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';

// Simple routing - extract the path without query string
$path = parse_url($requestUri, PHP_URL_PATH);
$path = trim($path, '/');

// Default to index if path is empty
if (empty($path)) {
    $path = 'index';
}

// $path comes straight from the raw request URI with no other validation,
// and gets built directly into an include()'d filesystem path below —
// reject anything that isn't a plain controller-name-shaped segment (or the
// installer's own sub-paths, same shape) before it ever reaches
// file_exists()/include(). Don't rely on the front-end web server happening
// to reject ".."-bearing URLs; the PHP code must be safe on its own.
$validControllerName = '[A-Za-z0-9_\-]+';
$isValidPath = preg_match('#^' . $validControllerName . '$#', $path) === 1
    || preg_match('#^install(er)?(/' . $validControllerName . ')*$#', $path) === 1;
if (!$isValidPath) {
    http_response_code(404);
    echo '<h1>404 - Page Not Found</h1>';
    exit;
}

// Validate session user still exists in database
// Session is already started by CsrfProtection::__construct() in odm-init.php
if (isset($_SESSION['uid']) && !User::exists($_SESSION['uid'], $pdo)) {
    session_destroy();
    header('Location: index');
    exit;
}

// Simple controller mapping
$controllerFile = "../application/controllers/{$path}.php";
if (file_exists($controllerFile)) {
    include($controllerFile);
} else {
    // Handle special cases
    if (strpos($path, 'installer') === 0) {
        require '../application/installer/InstallerController.php';
    } elseif (strpos($path, 'install/') === 0) {
        $installPath = str_replace('install/', '', $path);
        $installFile = "../application/controllers/install/{$installPath}.php";
        if (file_exists($installFile)) {
            include($installFile);
        } else {
            echo '<h1>404 - Page Not Found</h1>';
        }
    } else {
        echo '<h1>404 - Page Not Found</h1>';
    }
}

// Router code removed - using simple include-based routing above
// exit; statement already handled in the simple routing section above
