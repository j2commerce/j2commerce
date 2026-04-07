<?php
/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

defined('_JEXEC') or die;

use J2Commerce\Component\J2commerce\Administrator\Helper\J2CommerceHelper;
use J2Commerce\Component\J2commerce\Site\Service\ProductLayoutService;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;

$productType = strtolower($displayData['product']->product_type ?? 'simple');
$layoutContext = 'category';

// Core product type layout mappings
$layoutMap = [
    'simple'           => "list.{$layoutContext}.item_simple",
    'variable'         => "list.{$layoutContext}.item_variable",
    'configurable'     => "list.{$layoutContext}.item_configurable",
    'downloadable'     => "list.{$layoutContext}.item_downloadable",
    'flexivariable'    => "list.{$layoutContext}.item_flexivariable",
];

// Allow app plugins to register their product type layouts
$event = J2CommerceHelper::plugin()->event('GetProductTypeLayouts', [&$layoutMap, $layoutContext]);

if (!isset($layoutMap[$productType])) {
    if (Factory::getApplication()->getIdentity()->authorise('core.manage', 'com_j2commerce')) {
        echo '<div class="uk-alert uk-alert-warning" uk-alert>' . Text::sprintf('COM_J2COMMERCE_ERR_UNKNOWN_PRODUCT_TYPE_LAYOUT', $this->escape($productType)) . '</div>';
    }

    return;
}

echo ProductLayoutService::renderLayout($layoutMap[$productType], $displayData);
