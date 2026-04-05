<?php
/**
 * @package     J2Commerce
 * @subpackage  mod_j2commerce_products
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

defined('_JEXEC') or die;

use J2Commerce\Component\J2commerce\Administrator\Helper\J2CommerceHelper;
use J2Commerce\Component\J2commerce\Site\Service\ProductLayoutService;

/** @var \Joomla\Registry\Registry $params */
/** @var array $products */
/** @var int $itemId */
/** @var int $moduleId */
/** @var string $layoutType */

if (empty($products)) {
    return;
}

// Route to slider template if configured
if ($layoutType === 'slider') {
    require __DIR__ . '/slider.php';
    return;
}

// Grid layout
$columns = (int) $params->get('list_no_of_columns', 3);

$colClass = match ($columns) {
    2       => 'col-6 col-md-6',
    4       => 'col-6 col-md-3',
    6       => 'col-6 col-md-2',
    default => 'col-6 col-md-4',
};

echo J2CommerceHelper::plugin()->eventWithHtml('BeforeViewProductListDisplay', [$products])->getArgument('html', '');
echo J2CommerceHelper::modules()->loadPosition('j2commerce-product-list-top');
?>
<div class="j2commerce-products-module mod-j2commerce-products-<?php echo $moduleId; ?> j2commerce">
    <div class="row g-3">
        <?php foreach ($products as $product) : ?>
            <div class="<?php echo $colClass; ?>">
                <?php echo ProductLayoutService::renderProductItem(
                    $product,
                    $params,
                    ProductLayoutService::CONTEXT_MODULE,
                    $itemId
                ); ?>
            </div>
        <?php endforeach; ?>
    </div>
</div>
<?php
echo J2CommerceHelper::modules()->loadPosition('j2commerce-product-list-bottom');
echo J2CommerceHelper::plugin()->eventWithHtml('AfterViewProductListDisplay', [$products])->getArgument('html', '');
