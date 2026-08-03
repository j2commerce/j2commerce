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

use Joomla\CMS\Language\Text;
use J2Commerce\Component\J2commerce\Administrator\Helper\J2CommerceHelper;
use J2Commerce\Component\J2commerce\Site\Service\ProductLayoutService;

$crossSells = $this->cross_sells ?? [];
$total = count($crossSells);

if ($total === 0) {
    return;
}

$platform = J2CommerceHelper::platform();
$columns = (int) $this->params->get('item_related_product_columns', 3);
$counter = 0;
?>
<div class="j2commerce-crosssells-container">
    <h3><?php echo Text::_('COM_J2COMMERCE_RELATED_PRODUCTS_CROSS_SELLS'); ?></h3>

    <div class="uk-grid uk-grid-medium uk-child-width-1-<?php echo $columns; ?>@s" uk-grid>
    <?php foreach ($crossSells as $product): ?>
        <?php
        $product->params = $platform->getRegistry($product->params ?? '{}');

        $itemHtml = ProductLayoutService::renderProductItem(
            $product,
            $this->params,
            ProductLayoutService::CONTEXT_CROSSSELL,
            0
        );

        // A product type with no registered layout renders nothing — skip the wrapper too.
        if (trim($itemHtml) === '') {
            continue;
        }
        ?>
        <div>
            <?php echo $itemHtml; ?>
        </div>

        <?php $counter++; ?>
    <?php endforeach; ?>
    </div>
</div>
