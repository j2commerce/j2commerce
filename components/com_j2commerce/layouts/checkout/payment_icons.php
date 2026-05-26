<?php
/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use J2Commerce\Component\J2commerce\Site\Service\ProductLayoutService;

/**
 * Framework-neutral shim. Delegates to the shared layout resolver, which picks
 * the active subtemplate's markup (layouts/app_bootstrap5|app_uikit/checkout/).
 * Kept for backward compatibility with callers that target this path directly.
 *
 * @var array $displayData
 */
echo ProductLayoutService::renderLayout('checkout.payment_icons', $displayData);
