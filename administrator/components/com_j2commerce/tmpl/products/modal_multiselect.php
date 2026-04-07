<?php
/**
 * @package     J2Commerce
 * @subpackage  lib_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use J2Commerce\Component\J2commerce\Administrator\Field\ProductTypeField;
use J2Commerce\Component\J2commerce\Administrator\Helper\CurrencyHelper;
use J2Commerce\Component\J2commerce\Administrator\Helper\ImageHelper;
use Joomla\CMS\Button\PublishedButton;
use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Multilanguage;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;
use Joomla\CMS\Uri\Uri;


/** @var \Joomla\Component\J2commerce\Administrator\View\Products\HtmlView $this */

$app = Factory::getApplication();

if ($app->isClient('site')) {
    Session::checkToken('get') or die(Text::_('JINVALID_TOKEN'));
}

// Load the library language
Factory::getApplication()->getLanguage()->load('lib_j2commerce', JPATH_SITE);

/** @var \Joomla\CMS\WebAsset\WebAssetManager $wa */
$wa = $this->getDocument()->getWebAssetManager();

HTMLHelper::_('bootstrap.tooltip', '.hasTooltip');

// @todo: Use of Function and Editor is deprecated and should be removed in 6.0. It stays only for backward compatibility.
$function  = $app->getInput()->getCmd('function', 'jSelectItemMultiCallback');
$editor    = $app->getInput()->getCmd('editor', '');
$listOrder = $this->escape($this->state->get('list.ordering'));
$listDirn  = $this->escape($this->state->get('list.direction'));
$onclick   = $this->escape($function);
$multilang = Multilanguage::isEnabled();

if (!empty($editor)) {
    // This view is used also in com_menus. Load the xtd script only if the editor is set!
    $this->getDocument()->addScriptOptions('xtd-products', ['editor' => $editor]);
    $onclick = "jSelectItemMultiCallback";
}

Text::script('LIB_J2COMMERCE_ITEM_SELECT');
Text::script('LIB_J2COMMERCE_ITEM_UNSELECT');
Text::script('LIB_J2COMMERCE_ITEM_SELECT_MODAL_SELECTION_DONE_LABEL');

// Register and use the modal multi-select list script from library
$wa->registerAndUseScript(
    'lib_j2commerce.modal-multiselect-list',
    'lib_j2commerce/modal-multiselect-list.min.js'
);

// Check for a default image file
$default_image = 'images/products/product_default_thumb.webp';

$getImagePath = function(?string $imagePath): string {
    if (empty($imagePath)) {
        return '';
    }
    // Strip the Joomla image adapter suffix (everything after #)
    $cleanPath = explode('#', $imagePath)[0];
    return $cleanPath;
};
$user = Factory::getApplication()->getIdentity();
$userId = $user->id;
$canChangeState = $user->authorise('core.edit.state', 'com_content');
?>
<div class="container-popup">

    <form action="<?php echo Route::_('index.php?option=com_j2commerce&view=products&layout=modal_multiselect&tmpl=component&editor=' . $editor . '&function=' . $function . '&' . Session::getFormToken() . '=1'); ?>" method="post" name="adminForm" id="adminForm">

        <?php echo LayoutHelper::render('joomla.searchtools.default', ['view' => $this]); ?>

        <?php if (empty($this->items)) : ?>
            <div class="alert alert-info">
                <span class="icon-info-circle" aria-hidden="true"></span><span class="visually-hidden"><?php echo Text::_('INFO'); ?></span>
                <?php echo Text::_('JGLOBAL_NO_MATCHING_RESULTS'); ?>
            </div>
        <?php else : ?>
            <!-- Multi-select controls -->
            <div class="row mb-3">
                <div class="col text-end">
                    <button type="button"
                            id="clear-selection-btn"
                            class="btn btn-outline-danger ms-2"
                            style="display: none;"
                            title="<?php echo Text::_('LIB_J2COMMERCE_ITEM_SELECT_MODAL_SELECTION_CLEAR_LABEL'); ?>">
                        <span class="icon-trash" aria-hidden="true"></span> <?php echo Text::_('LIB_J2COMMERCE_ITEM_SELECT_MODAL_SELECTION_CLEAR_LABEL'); ?>
                    </button>
                    <button type="button"
                            id="done-btn"
                            class="btn btn-success ms-2"
                            data-content-select
                            data-content-type="com_j2commerce.product"
                            data-function="<?php echo $this->escape($onclick); ?>"
                            data-button-close="true">
                        <span id="done-btn-text"><?php echo Text::_('LIB_J2COMMERCE_ITEM_SELECT_MODAL_SELECTION_DONE_LABEL'); ?></span>
                        <span class="badge bg-light text-dark ms-1 px-2" id="selected-count-badge" style="display: none;">0</span>
                    </button>
                </div>
            </div>

            <table class="table table-sm">
                <caption class="visually-hidden">
                    <?php echo Text::_('COM_J2COMMERCE_PRODUCTS'); ?>,
                        <span id="orderedBy"><?php echo Text::_('JGLOBAL_SORTED_BY'); ?> </span>,
                        <span id="filteredBy"><?php echo Text::_('JGLOBAL_FILTERED_BY'); ?></span>
                </caption>
                <thead>
                    <tr>
                        <td class="w-1 text-center">
                            <?php echo HTMLHelper::_('grid.checkall'); ?>
                        </td>
                        <th scope="col" class="w-1 text-center">
                            <?php echo HTMLHelper::_('searchtools.sort', 'JSTATUS', 'c.state', $listDirn, $listOrder); ?>
                        </th>
                        <th scope="col" class="w-5 text-center d-none d-md-table-cell">
                            <?php echo HTMLHelper::_('searchtools.sort', 'COM_J2COMMERCE_HEADING_PRODUCT_ID', 'a.j2commerce_product_id', $listDirn, $listOrder); ?>
                        </th>
                        <th scope="col">
                            <?php echo HTMLHelper::_('searchtools.sort', 'COM_J2COMMERCE_HEADING_PRODUCT_NAME', 'c.title', $listDirn, $listOrder); ?>
                        </th>
                        <th scope="col" class="w-10 d-none d-md-table-cell">
                            <?php echo HTMLHelper::_('searchtools.sort', 'COM_J2COMMERCE_HEADING_SKU', 'v.sku', $listDirn, $listOrder); ?>
                        </th>
                        <th scope="col" class="w-10 d-none d-md-table-cell">
                            <?php echo HTMLHelper::_('searchtools.sort', 'COM_J2COMMERCE_HEADING_PRICE', 'v.price', $listDirn, $listOrder); ?>
                        </th>
                        <th scope="col" class="w-5 text-center d-none d-md-table-cell">
                            <?php echo Text::_('COM_J2COMMERCE_HEADING_SHIPPING'); ?>
                        </th>
                        <th scope="col" class="w-5 d-none d-md-table-cell text-center">
                            <?php echo Text::_('COM_J2COMMERCE_HEADING_ARTICLE_ID'); ?>
                        </th>
                    </tr>
                </thead>
                <tbody>
                <?php
                $states = [
                    0  => ['icon' => 'icon-unpublish', 'label' => Text::_('JDISABLED')],
                    1  => ['icon' => 'icon-publish', 'label' => Text::_('JENABLED')],
                ];
                ?>
                <?php foreach ($this->items as $i => $item) :
                    $baseUrl = rtrim(Uri::root(), '/');
                    $lang = '';
                    if (!empty($item->language) && $multilang) {
                        $tag = \strlen($item->language);
                        if ($tag == 5) {
                            $lang = \substr($item->language, 0, 2);
                        } elseif ($tag == 6) {
                            $lang = \substr($item->language, 0, 3);
                        }
                    }
                    $thumbImage = $getImagePath($item->thumb_image);
                    $hasImage = !empty($thumbImage);
                    $productTypeLabel = ProductTypeField::getProductTypes()[$item->product_type ?? 'simple'] ?? ucfirst($item->product_type ?? 'simple');
                    $articleStateText = ($item->article_state == 1) ? Text::_('JPUBLISHED') : Text::_('JUNPUBLISHED');
                    $articleStateClass = ($item->article_state == 1) ? 'text-success' : 'text-danger';
                    $taxProfileText = !empty($item->tax_profile_name) ? $this->escape($item->tax_profile_name) : Text::_('JNONE');
                    // Check if user can check in this article
                    $canCheckin = $user->authorise('core.manage', 'com_checkin')
                        || $item->checked_out == $userId
                        || empty($item->checked_out);
                    ?>
                    <tr class="row<?php echo $i % 2; ?>">
                        <td class="text-center">
                            <?php //echo HTMLHelper::_('grid.id', $i, $item->j2commerce_product_id, false, 'cid', 'cb', $item->product_name ?? Text::_('COM_J2COMMERCE_NO_PRODUCT_NAME')); ?>
                            <input type="checkbox"
                                   class="item-checkbox form-check-input"
                                   id="item-checkbox-<?php echo $item->j2commerce_product_id; ?>"
                                   data-id="<?php echo $item->j2commerce_product_id; ?>"
                                   data-title="<?php echo $this->escape($item->product_name); ?>">
                        </td>
                        <td class="text-center">
                            <?php
                            // Render the article status toggle button
                            $options = [
                                'task_prefix' => 'products.',
                                'disabled'    => !$canChangeState,
                                'id'          => 'state-' . $item->j2commerce_product_id,
                            ];

                            echo (new PublishedButton())->render((int) $item->article_state, $i, $options, (string) $item->article_state);
                            ?>
                        </td>
                        <td class="text-center d-none d-md-table-cell">
                            <?php echo (int) $item->j2commerce_product_id; ?>
                        </td>
                        <td>
                            <div class="d-flex align-items-start">
                                <?php if ($hasImage) : ?>
                                    <div class="me-3 flex-shrink-0">
                                        <img src="<?php echo Uri::root() . $thumbImage; ?>"
                                             alt="<?php echo $this->escape($item->product_name); ?>"
                                             class="img-fluid"
                                             style="width: 100px; height: 100px; object-fit: cover;">
                                    </div>
                                <?php endif; ?>
                                <div class="flex-grow-1">
                                    <?php if (!empty($item->checked_out)) : ?>
                                        <?php echo HTMLHelper::_('jgrid.checkedout', $i, $item->editor, $item->checked_out_time, 'products.', $canCheckin); ?>
                                    <?php endif; ?>
                                    <label for="item-checkbox-<?php echo $item->j2commerce_product_id; ?>"
                                           class="item-label"
                                           style="cursor: pointer;"
                                           data-bs-toggle="tooltip"
                                           data-bs-placement="top"
                                           title="<?php echo Text::_('LIB_J2COMMERCE_ITEM_SELECT'); ?>">
                                        <?php echo $this->escape($item->product_name); ?>
                                    </label>

                                    <div class="small">
                                        <div><?php echo Text::_('COM_J2COMMERCE_PRODUCT_TYPE'); ?>: <strong><?php echo $this->escape($productTypeLabel); ?></strong></div>
                                        <div><?php echo Text::_('COM_J2COMMERCE_VISIBLE_IN_STOREFRONT'); ?>: <strong><?php echo $item->visibility ? Text::_('JYES') : Text::_('JNO'); ?></strong></div>
                                        <div><?php echo Text::_('COM_J2COMMERCE_ARTICLE'); ?>: <strong class="<?php echo $articleStateClass; ?>"><?php echo $articleStateText; ?></strong></div>
                                        <div><?php echo Text::_('COM_J2COMMERCE_TAX_PROFILE'); ?>: <strong><?php echo $taxProfileText; ?></strong></div>
                                    </div>
                                </div>
                            </div>
                        </td>
                        <td class="d-none d-md-table-cell">
                            <?php if ($item->has_options == 1) : ?>
                                <small class="text-muted"><?php echo Text::_('COM_J2COMMERCE_VARIANTS'); ?></small>
                            <?php else : ?>
                                <span class="font-monospace"><?php echo $this->escape($item->sku ?? '-'); ?></span>
                            <?php endif; ?>
                        </td>
                        <td class="d-none d-md-table-cell">
                            <?php echo CurrencyHelper::format((float) ($item->price ?? 0)); ?>
                        </td>
                        <td class="text-center d-none d-md-table-cell">
                            <?php if ($item->shipping) : ?>
                                <span class="badge text-bg-success"><?php echo Text::_('COM_J2COMMERCE_ENABLED'); ?></span>
                            <?php else : ?>
                                <span class="badge text-bg-danger"><?php echo Text::_('COM_J2COMMERCE_DISABLED'); ?></span>
                            <?php endif; ?>
                        </td>
                        <td class="text-center d-none d-md-table-cell">
                            <?php echo (int) $item->product_source_id; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php /*foreach ($this->items as $i => $item) : */?><!--
                    <?php
/*                    $lang = '';
                    if (!empty($item->language) && $multilang) {
                        $tag = \strlen($item->language);
                        if ($tag == 5) {
                            $lang = \substr($item->language, 0, 2);
                        } elseif ($tag == 6) {
                            $lang = \substr($item->language, 0, 3);
                        }
                    }

                    $productName = !empty($item->product_name) ? $item->product_name : 'Product #' . $item->j2commerce_product_id;
                    */?>
                    <tr class="row<?php /*echo $i % 2; */?>">
                        <td class="text-center">
                            <input type="checkbox"
                                   class="item-checkbox form-check-input"
                                   id="item-checkbox-<?php /*echo $item->j2commerce_product_id; */?>"
                                   data-id="<?php /*echo $item->j2commerce_product_id; */?>"
                                   data-title="<?php /*echo $this->escape($productName); */?>">
                        </td>
                        <td class="text-center tbody-icon">
                            <span class="<?php /*echo $states[$this->escape($item->enabled ?? 1)]['icon']; */?>" aria-hidden="true" title="<?php /*echo $states[$this->escape($item->enabled ?? 1)]['label']; */?>"></span>
                        </td>
                        <td>
                            <?php /*echo (int) $item->j2commerce_product_id; */?>
                        </td>
                        <th scope="row" class="nowrap has-context">
                            <?php
/*                            $imagePath = $imageDefaultPath;
                            if ($item->thumb_image) {
                                $imagePath = HTMLHelper::cleanImageURL($item->thumb_image)->url;
                                $imagePath = ImageHelper::getInstance()->getImageUrl($imagePath);
                            }
                            */?>
                            <div class="d-block d-lg-flex">
                                <div class="flex-shrink-0">
                                    <?php /*if ($imagePath) : */?>
                                        <img src="<?php /*echo $this->escape($imagePath); */?>"
                                             class="img-fluid j2commerce-product-thumb-image d-none d-lg-inline-block"
                                             alt="<?php /*echo $this->escape($productName); */?>"
                                             style="max-width: 60px; max-height: 60px; object-fit: cover;">
                                    <?php /*endif; */?>
                                </div>
                                <div class="flex-grow-1 ms-lg-3 mt-2 mt-lg-0">
                                    <div>
                                        <label for="item-checkbox-<?php /*echo $item->j2commerce_product_id; */?>"
                                                class="item-label"
                                                style="cursor: pointer;"
                                                data-bs-toggle="tooltip"
                                                data-bs-placement="top"
                                                title="<?php /*echo Text::_('LIB_J2COMMERCE_ITEM_SELECT'); */?>">
                                            <?php /*echo $this->escape($productName); */?>
                                        </label>
                                        <?php
/*                                        $productTypeKey = 'COM_J2COMMERCE_PRODUCT_TYPE_' . strtoupper($item->product_type ?? 'simple');
                                        $taxProfileText = !empty($item->tax_profile_name) ? $this->escape($item->tax_profile_name) : Text::_('JNONE');
                                        */?>
                                        <br>
                                        <small class="text-muted">
                                            <strong><?php /*echo Text::_('COM_J2COMMERCE_PRODUCT_TYPE'); */?>:</strong> <?php /*echo Text::_($productTypeKey); */?><br>
                                            <strong><?php /*echo Text::_('COM_J2COMMERCE_VISIBLE_IN_STOREFRONT'); */?>:</strong> <?php /*echo $item->visibility ? Text::_('JYES') : Text::_('JNO'); */?><br>
                                            <strong><?php /*echo Text::_('COM_J2COMMERCE_TAX_PROFILE'); */?>:</strong> <?php /*echo $taxProfileText; */?>
                                        </small>
                                    </div>
                                </div>
                            </div>
                        </th>
                        <td class="d-none d-md-table-cell">
                            <?php /*if ($item->has_options == 1) : */?>
                                <small class="text-muted"><?php /*echo Text::_('COM_J2COMMERCE_VARIANTS'); */?></small>
                            <?php /*else : */?>
                                <span class="font-monospace"><?php /*echo $this->escape($item->sku ?? ''); */?></span>
                            <?php /*endif; */?>
                        </td>
                        <td class="d-none d-md-table-cell">
                            <?php /*if (!empty($item->price)) : */?>
                                <?php /*echo number_format((float) $item->price, 2); */?>
                            <?php /*else : */?>
                                <span class="text-muted">-</span>
                            <?php /*endif; */?>
                        </td>
                        <td class="d-none d-lg-table-cell">
                            <?php /*if (!empty($item->product_source)) : */?>
                                <?php /*echo $this->escape($item->product_source); */?>
                            <?php /*else : */?>
                                <span class="text-muted">-</span>
                            <?php /*endif; */?>
                        </td>
                        <td class="d-none d-lg-table-cell">
                            <?php /*echo (int) $item->product_source_id; */?>
                        </td>
                    </tr>
                --><?php /*endforeach; */?>
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
