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

use J2Commerce\Component\J2commerce\Administrator\Helper\ProductHelper;
use J2Commerce\Component\J2commerce\Site\Service\ProductLayoutService;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\Registry\Registry;

/** @var \J2Commerce\Component\J2commerce\Site\View\Product\HtmlView $this */
$app = Factory::getApplication();
$wa = $app->getDocument()->getWebAssetManager();
$upSells = ProductHelper::getUpSells($this->product);
$total = count($upSells);

if ($total === 0) {
    return;
}

$columns  = (int) $this->params->get('item_related_product_columns', 4);
$colClass = 'col-md-' . (int) round(12 / $columns);
$counter  = 0;
?>
<div class="j2commerce-upsells-container mt-5 pt-4 border-top">
    <h3 class="text-center mb-5"><?php echo Text::_('COM_J2COMMERCE_RELATED_PRODUCTS_UPSELLS'); ?></h3>

    <?php foreach ($upSells as $product) : ?>
        <?php
        if (!($product->params instanceof Registry)) {
            $product->params = new Registry($product->params ?? '{}');
        }

        $rowcount = ($counter % $columns) + 1;
        if ($rowcount === 1) :
            $row = (int) ($counter / $columns);
            ?>
            <div class="j2commerce-upsells-row row-<?php echo $row; ?> row g-4 mb-4">
        <?php endif; ?>

        <div class="<?php echo $colClass; ?>">
            <?php echo ProductLayoutService::renderProductItem($product,$this->params,ProductLayoutService::CONTEXT_UPSELL); ?>
        </div>

        <?php $counter++; ?>
        <?php if ($rowcount === $columns || $counter === $total) : ?>
            </div>
        <?php endif; ?>
    <?php endforeach; ?>
    <?php if ($this->params->get('list_enable_quickview', 0)) : ?>
        <?php
        $wa->registerAndUseScript('com_j2commerce.vendor.fancybox', 'media/com_j2commerce/vendor/fancybox/js/fancybox.umd.js', [], ['defer' => true]);
        $wa->registerAndUseStyle('com_j2commerce.vendor.fancybox.css', 'media/com_j2commerce/vendor/fancybox/css/fancybox.css');
        $scriptName = 'com_j2commerce.fancybox.init';
        if (!$wa->assetExists('script', $scriptName)) {
            $wa->registerScript($scriptName,'',[],['defer' => true],['com_j2commerce.vendor.fancybox']);
            $inlineScript = "document.addEventListener('DOMContentLoaded', () => {
                if (typeof Fancybox !== 'undefined') {
                    Fancybox.bind('[data-fancybox]', {
                        animated: true,
                        showClass: 'f-zoomInUp',
                        hideClass: 'f-zoomOutDown',
                        mainClass: 'j2commerce-quickview-fancybox',
                        iframe: {css: {width: '960px',height: '80vh',},},
                        Toolbar: {display: {left: [],middle: [],right: ['close'],},},
                    });
                }
            });";
            $wa->addInlineScript($inlineScript,[],[],[$scriptName]);
        }
        ?>
    <?php endif; ?>
</div>
