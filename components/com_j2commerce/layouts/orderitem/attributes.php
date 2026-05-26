<?php
/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

\defined('_JEXEC') or die;

use J2Commerce\Component\J2commerce\Administrator\Helper\J2CommerceHelper;
use J2Commerce\Component\J2commerce\Administrator\Helper\OrderItemAttributeHelper;
use J2Commerce\Component\J2commerce\Site\Service\ProductLayoutService;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;

/**
 * @var array  $displayData
 * @var array  $displayData['attributes']  Array of attribute objects
 * @var object $displayData['item']        Order/cart item for context
 * @var string $displayData['context']     admin_order|admin_edit|cart|checkout|confirmation|myprofile|drawer|email|cart_module
 * @var string $displayData['variant']     full|compact|inline
 * @var string $displayData['framework']   bootstrap5|uikit3 (default bootstrap5)
 */

$attributes = $displayData['attributes'] ?? [];
$item       = $displayData['item'] ?? null;
$context    = $displayData['context'] ?? 'cart';
$variant    = $displayData['variant'] ?? 'full';
$rawFramework = ($displayData['framework'] ?? 'bootstrap5');
$framework  = $rawFramework === 'uikit3' ? 'uikit3' : 'bootstrap5';

if (empty($attributes)) {
    return;
}

$grouped = OrderItemAttributeHelper::groupAndDeduplicate($attributes);
if (empty($grouped)) {
    return;
}

$event = J2CommerceHelper::plugin()->event('RenderOrderItemAttributes', [
    $item,
    $grouped,
    $context,
    $variant,
]);
$typeRenderers = $event->getArgument('typeRenderers', []);

$isAdminContext = ($context === 'admin_order' || $context === 'admin_edit');

$buildDownloadUrl = static function (string $mangled): string {
    return Route::_('index.php?option=com_j2commerce&task=orderfile.download'
        . '&file=' . urlencode($mangled)
        . '&' . Session::getFormToken() . '=1');
};

// Pass computed values into $displayData so framework files stay purely presentational
$displayData['grouped']         = $grouped;
$displayData['typeRenderers']   = $typeRenderers;
$displayData['isAdminContext']  = $isAdminContext;
$displayData['buildDownloadUrl'] = $buildDownloadUrl;
$displayData['framework']       = $framework;

// Normalize override key
$override = ($framework === 'uikit3') ? 'uikit' : 'bootstrap5';

ProductLayoutService::setSubtemplateOverride($override);
try {
    echo ProductLayoutService::renderLayout('orderitem.attributes', $displayData);
} finally {
    ProductLayoutService::clearSubtemplateOverride();
}
