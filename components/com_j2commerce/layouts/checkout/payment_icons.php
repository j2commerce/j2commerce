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

use J2Commerce\Component\J2commerce\Administrator\Helper\SubtemplateHelper;
use J2Commerce\Component\J2commerce\Site\Service\ProductLayoutService;
use Joomla\CMS\Application\SiteApplication;
use Joomla\CMS\Factory;

/**
 * Framework-neutral shim. Delegates to the shared layout resolver, which picks
 * the active subtemplate's markup (layouts/app_bootstrap5|app_uikit/checkout/).
 * Kept for backward compatibility with callers that target this path directly.
 *
 * @var array $displayData
 */
// J2CommerceHelper::getPaymentCardIcons() passes no framework, so resolve it the way the
// checkout-family views pick their tmpl folder. A bootstrap5 guess would lead the chain on a
// UIkit store, because buildFolderChain() puts the fallback first when the active folder is a
// framework folder.
$app          = Factory::getApplication();
$rawFramework = $displayData['framework']
    ?? ($app instanceof SiteApplication ? SubtemplateHelper::framework($app->getParams()) : '');
$framework    = ($rawFramework === 'uikit3' || $rawFramework === 'uikit') ? 'uikit' : 'bootstrap5';

// The framework folder is a fallback, not an override — see product/quantity.php.
echo ProductLayoutService::renderLayout('checkout.payment_icons', $displayData, [$framework]);
