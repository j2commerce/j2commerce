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

use J2Commerce\Component\J2commerce\Administrator\Field\ProductTypeField;
use J2Commerce\Component\J2commerce\Administrator\Helper\CurrencyHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Multilanguage;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;


/** @var \J2Commerce\Component\J2commerce\Administrator\View\Products\HtmlView $this */

$app = Factory::getApplication();
$user = $app->getIdentity();
$input = $app->getInput();
$fieldId = $input->get('field_id', '', 'CMD');
$listOrder = $this->escape($this->state->get('list.ordering'));
$listDirn = $this->escape($this->state->get('list.direction'));
$multilang = Multilanguage::isEnabled();

/** @var \Joomla\CMS\WebAsset\WebAssetManager $wa */
$wa = $this->getDocument()->getWebAssetManager();
$wa->useScript('core')
    ->useScript('multiselect')
    ->useScript('modal-content-select');

// Register and use the modal multi-select list script from library
$wa->registerAndUseScript(
    'com_j2commerce.modal-products',
    'com_j2commerce/administrator/modal-products.min.js'
);

// Helper function to extract clean image path from Joomla image string
$getImagePath = function (?string $imagePath): string {
    if (empty($imagePath)) {
        return '';
    }
    // Strip the Joomla image adapter suffix (everything after #)
    $cleanPath = explode('#', $imagePath)[0];
    return $cleanPath;
};
?>
<div class="container-popup">
    <form action="<?php echo Route::_('index.php?option=com_j2commerce&view=products&layout=modal&tmpl=component&field_id=' . urlencode($fieldId)); ?>" method="post" name="adminForm" id="adminForm">

        <?php echo LayoutHelper::render('joomla.searchtools.default', ['view' => $this]); ?>

        <?php if (empty($this->items)) : ?>
            <div class="alert alert-info">
                <span class="icon-info-circle" aria-hidden="true"></span><span class="visually-hidden"><?php echo Text::_('INFO'); ?></span>
                <?php echo Text::_('JGLOBAL_NO_MATCHING_RESULTS'); ?>
            </div>
        <?php else : ?>
            <table class="table table-sm itemList align-middle" id="productsList">
                <caption class="visually-hidden">
                    <?php echo Text::_('COM_J2COMMERCE_PRODUCTS'); ?>,
                    <span id="orderedBy"><?php echo Text::_('JGLOBAL_SORTED_BY'); ?> </span>,
                    <span id="filteredBy"><?php echo Text::_('JGLOBAL_FILTERED_BY'); ?></span>
                </caption>
                <thead>
                <tr>
                    <th scope="col" class="w-1 text-center">
                        <?php echo HTMLHelper::_('searchtools.sort', 'JSTATUS', 'a.enabled', $listDirn, $listOrder); ?>
                    </th>
                    <th scope="col" class="w-5 text-center d-none d-md-table-cell">
                        <?php echo HTMLHelper::_('searchtools.sort', 'COM_J2COMMERCE_HEADING_PRODUCT_ID', 'a.j2commerce_product_id', $listDirn, $listOrder); ?>
                    </th>
                    <th scope="col">
                        <?php echo HTMLHelper::_('searchtools.sort', 'COM_J2COMMERCE_HEADING_PRODUCT_NAME', 'b.title', $listDirn, $listOrder); ?>
                    </th>
                    <th scope="col" class="w-10 d-none d-md-table-cell">
                        <?php echo HTMLHelper::_('searchtools.sort', 'COM_J2COMMERCE_HEADING_SKU', 'v.sku', $listDirn, $listOrder); ?>
                    </th>
                    <th scope="col" class="w-10 d-none d-md-table-cell">
                        <?php echo HTMLHelper::_('searchtools.sort', 'COM_J2COMMERCE_HEADING_PRICE', 'v.price', $listDirn, $listOrder); ?>
                    </th>
                    <th scope="col" class="w-5 d-none d-lg-table-cell text-center">
                        <?php echo Text::_('COM_J2COMMERCE_HEADING_ARTICLE_ID'); ?>
                    </th>
                </tr>
                </thead>
                <tbody>
                <?php
                $states = [
                    0 => ['icon' => 'icon-unpublish', 'label' => Text::_('JDISABLED')],
                    1 => ['icon' => 'icon-publish', 'label' => Text::_('JENABLED')],
                ];
                ?>
                <?php foreach ($this->items as $i => $item) :
                    $baseUrl = rtrim(Uri::root(), '/');
                    $thumbImage = $getImagePath($item->thumb_image);
                    $hasImage = !empty($thumbImage);
                    $productName = !empty($item->product_name) ? $item->product_name : 'Product #' . $item->j2commerce_product_id;
                    $productTypeLabel = ProductTypeField::getProductTypes()[$item->product_type ?? 'simple'] ?? ucfirst($item->product_type ?? 'simple');
                    $taxProfileText = !empty($item->tax_profile_name) ? $this->escape($item->tax_profile_name) : Text::_('COM_J2COMMERCE_NOT_TAXABLE');

                    $lang = '';
                    if (!empty($item->language) && $multilang) {
                        $tag = \strlen($item->language);
                        if ($tag == 5) {
                            $lang = \substr($item->language, 0, 2);
                        } elseif ($tag == 6) {
                            $lang = \substr($item->language, 0, 3);
                        }
                    }
                    ?>
                    <tr class="row<?php echo $i % 2; ?>">
                        <td class="text-center tbody-icon">
                            <span class="<?php echo $states[$this->escape($item->enabled ?? 1)]['icon']; ?>" aria-hidden="true" title="<?php echo $states[$this->escape($item->enabled ?? 1)]['label']; ?>"></span>
                        </td>
                        <td class="text-center d-none d-md-table-cell">
                            <?php echo (int) $item->j2commerce_product_id; ?>
                        </td>
                        <td>
                            <?php
                            $attribs = 'data-content-select data-content-type="com_j2commerce.product"'
                                . ' data-id="' . $item->j2commerce_product_id . '"'
                                . ' data-title="' . $this->escape($productName) . '"';
                            ?>
                            <div class="d-flex align-items-start">
                                <?php if ($hasImage) : ?>
                                    <div class="me-3 flex-shrink-0">
                                        <a class="select-link" href="javascript:void(0)" <?php echo $attribs; ?>>
                                            <img src="<?php echo Uri::root() . $thumbImage; ?>"
                                                 alt="<?php echo $this->escape($productName); ?>"
                                                 class="img-fluid"
                                                 style="width: 80px; height: 80px; object-fit: cover;">
                                        </a>
                                    </div>
                                <?php endif; ?>
                                <div class="flex-grow-1">
                                    <a class="select-link fw-semibold" href="javascript:void(0)" <?php echo $attribs; ?>>
                                        <?php echo $this->escape($productName); ?>
                                    </a>
                                    <div class="small text-muted">
                                        <div><?php echo Text::_('COM_J2COMMERCE_PRODUCT_TYPE'); ?>: <strong><?php echo $this->escape($productTypeLabel); ?></strong></div>
                                        <div><?php echo Text::_('COM_J2COMMERCE_VISIBLE_IN_STOREFRONT'); ?>: <strong><?php echo $item->visibility ? Text::_('JYES') : Text::_('JNO'); ?></strong></div>
                                        <div><?php echo Text::_('COM_J2COMMERCE_TAX_PROFILE'); ?>: <strong><?php echo $taxProfileText; ?></strong></div>
                                    </div>
                                </div>
                            </div>
                        </td>
                        <td class="d-none d-md-table-cell">
                            <?php if ($item->has_options == 1) : ?>
                                <small class="text-muted"><?php echo Text::_('COM_J2COMMERCE_PRODUCT_HAS_VARIANTS'); ?></small>
                            <?php else : ?>
                                <span class="font-monospace"><?php echo $this->escape($item->sku ?? '-'); ?></span>
                            <?php endif; ?>
                        </td>
                        <td class="d-none d-md-table-cell">
                            <?php echo CurrencyHelper::format((float) ($item->price ?? 0)); ?>
                        </td>
                        <td class="text-center d-none d-lg-table-cell">
                            <?php echo (int) $item->product_source_id; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>

            <?php // load the pagination. ?>
            <?php echo $this->pagination->getListFooter(); ?>

        <?php endif; ?>

        <input type="hidden" name="task" value="">
        <input type="hidden" name="forcedLanguage" value="<?php echo $app->getInput()->get('forcedLanguage', '', 'CMD'); ?>">
        <?php echo HTMLHelper::_('form.token'); ?>
    </form>
</div>
