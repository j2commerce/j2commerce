<?php

/**
 * @package     J2Commerce
 * @subpackage  build
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

// build/ is excluded from every package so these scripts never reach an install,
// but on a dev or CI box whose docroot is the Joomla root they are served like
// any other file. Without this the only thing stopping a web hit is
// register_argc_argv being off — an accident, not a control.
function requireCli(): void
{
    if (PHP_SAPI !== 'cli') {
        http_response_code(403);
        exit(1);
    }
}
