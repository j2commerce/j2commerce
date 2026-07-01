<?php

/**
 * PHPUnit bootstrap for the J2Commerce test suite.
 *
 * Two modes:
 *   1. Unit tests   – only the Composer autoloader is needed. The PSR-4 map in
 *                     composer.json points the J2Commerce namespaces at the real
 *                     src/ folders, so pure classes (helpers, value objects,
 *                     calculators) load without a running Joomla.
 *   2. Integration  – set the JOOMLA_TESTS_BASE env var to a full Joomla 6
 *                     checkout. We then load Joomla's framework so Models,
 *                     Tables and the service container are available.
 */

declare(strict_types=1);

error_reporting(E_ALL);

/*
 * Every J2Commerce source file opens with `\defined('_JEXEC') or die;`.
 * If _JEXEC is not defined, autoloading any of those files during a unit test
 * silently kills the process. Define it up front, before the autoloader runs.
 */
if (!defined('_JEXEC')) {
    define('_JEXEC', 1);
}

$autoload = dirname(__DIR__) . '/vendor/autoload.php';

if (!is_file($autoload)) {
    fwrite(STDERR, "Run 'composer install' first — vendor/autoload.php is missing.\n");
    exit(1);
}

require $autoload;

/*
 * Optional Joomla bootstrap for the Integration suite.
 * Unit tests skip this entirely.
 */
$joomlaBase = getenv('JOOMLA_TESTS_BASE') ?: '';

if ($joomlaBase !== '' && is_dir($joomlaBase)) {
    if (!defined('JPATH_BASE')) {
        define('JPATH_BASE', $joomlaBase);
    }

    // Joomla's own definitions + framework autoloader.
    require_once $joomlaBase . '/includes/defines.php';
    require_once $joomlaBase . '/includes/framework.php';

    // Make the extension's namespaces resolvable inside the Joomla runtime too.
    \JLoader::registerNamespace(
        'J2Commerce\\Component\\J2commerce\\Administrator',
        $joomlaBase . '/administrator/components/com_j2commerce/src'
    );
    \JLoader::registerNamespace(
        'J2Commerce\\Component\\J2commerce\\Site',
        $joomlaBase . '/components/com_j2commerce/src'
    );
}
