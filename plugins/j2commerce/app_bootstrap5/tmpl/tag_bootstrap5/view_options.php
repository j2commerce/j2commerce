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

use J2Commerce\Component\J2commerce\Administrator\Helper\ImageHelper;
use J2Commerce\Component\J2commerce\Site\Service\ProductLayoutService;
use J2Commerce\Component\J2commerce\Administrator\Helper\J2CommerceHelper;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Language\Text;
use J2Commerce\Component\J2commerce\Administrator\Helper\J2htmlHelper;
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\Router\Route;

/** @var \J2Commerce\Component\J2commerce\Site\View\Product\HtmlView $this */

$platform       = J2CommerceHelper::platform();
$options        = $this->product->options;
$productId      = (int) $this->product->j2commerce_product_id;
$product_helper = J2CommerceHelper::product();
$ajax_url       = Route::_('index.php', false);
$esc            = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');

$mediaParams = ComponentHelper::getParams('com_media');
$uploadMaxMB = (float) $mediaParams->get('upload_maxsize', 0);
$fileExts    = strtolower((string) $mediaParams->get('restrict_uploads_extensions', ''));
$imageExts   = strtolower((string) $mediaParams->get('image_extensions', 'bmp,gif,jpg,png,jpeg,webp,avif'));
$uploadAjax  = Route::_('index.php?option=com_j2commerce&view=carts&task=carts.upload&product_id=' . $productId, false);
?>
<?php if ($options) : ?>

<div class="options">
    <?php foreach ($options as $option) : ?>

        <?php echo J2CommerceHelper::plugin()->eventWithHtml('BeforeDisplaySingleProductOption', [$this->product, &$option, $this->context])->getArgument('html', ''); ?>

        <?php if ($option['type'] == 'select') : ?>
        <!-- select -->
        <div id="option-<?php echo (int) $option['productoption_id']; ?>" class="option mb-3">
            <?php if ($option['required']) : ?>
            <span class="required text-danger">*</span>
            <?php endif; ?>
            <label class="form-label fw-bold"><?php echo $esc(J2htmlHelper::translateKey($option['option_name'])); ?>:</label>
<?php echo ProductLayoutService::renderLayout('productoption.description', [
    'description' => $option['option_description'] ?? '',
    'id'          => 'option-desc-' . (int) $option['productoption_id'],
]); ?>
            <select<?php echo ProductLayoutService::optionDescribedBy($option); ?>
                name="product_option[<?php echo (int) $option['productoption_id']; ?>]"
                class="form-select j2commerce-option-select"
                data-product-id="<?php echo $productId; ?>"
                data-option-id="<?php echo (int) $option['productoption_id']; ?>">
                <option value=""><?php echo Text::_('COM_J2COMMERCE_ADDTOCART_SELECT'); ?></option>
                <?php foreach ($option['optionvalue'] as $option_value) : ?>
                    <?php $checked = $option_value['product_optionvalue_default'] ? 'selected="selected"' : ''; ?>
                    <option <?php echo $checked; ?> value="<?php echo (int) $option_value['product_optionvalue_id']; ?>">
                        <?php echo stripslashes($this->escape(J2htmlHelper::translateKey($option_value['optionvalue_name']))); ?>
                        <?php if ($option_value['product_optionvalue_price'] > 0 && $this->params->get('product_option_price', 1)) : ?>
                        (
                        <?php if ($this->params->get('product_option_price_prefix', 1)) : ?>
                            <?php echo $esc($option_value['product_optionvalue_prefix']); ?>
                        <?php endif; ?>
                        <?php echo $product_helper->displayPrice($option_value['product_optionvalue_price'], $this->product, $this->params, 'products.view.option'); ?>
                        )
                        <?php endif; ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php endif; ?>

        <?php if ($option['type'] == 'radio') : ?>
        <!-- radio -->
        <fieldset id="option-<?php echo (int) $option['productoption_id']; ?>" class="option mb-3 j2commerce-option-group"<?php echo ProductLayoutService::optionDescribedBy($option); ?>>
            <?php if ($option['required']) : ?>
            <span class="required text-danger">*</span>
            <?php endif; ?>
            <legend class="form-label fw-bold"><?php echo $esc(J2htmlHelper::translateKey($option['option_name'])); ?>:</legend>
<?php echo ProductLayoutService::renderLayout('productoption.description', [
    'description' => $option['option_description'] ?? '',
    'id'          => 'option-desc-' . (int) $option['productoption_id'],
]); ?>
            <?php foreach ($option['optionvalue'] as $option_value) : ?>
                <div class="form-check">
                    <?php $checked = $option_value['product_optionvalue_default'] ? 'checked="checked"' : ''; ?>
                    <input <?php echo $checked; ?> type="radio"
                        autocomplete="off"
                        name="product_option[<?php echo (int) $option['productoption_id']; ?>]"
                        value="<?php echo (int) $option_value['product_optionvalue_id']; ?>"
                        id="option-value-<?php echo (int) $option_value['product_optionvalue_id']; ?>"
                        class="form-check-input j2commerce-option-radio"
                        data-product-id="<?php echo $productId; ?>"
                        data-option-id="<?php echo (int) $option['productoption_id']; ?>" />

                    <?php if ($this->params->get('image_for_product_options', 0) && isset($option_value['optionvalue_image']) && !empty($option_value['optionvalue_image'])) : ?>
                        <img class="optionvalue-image-<?php echo (int) $option_value['product_optionvalue_id']; ?> img-thumbnail"
                             src="<?php echo $esc(ImageHelper::getImageUrl($option_value['optionvalue_image'])); ?>" alt="" />
                    <?php endif; ?>
                    <label class="form-check-label" for="option-value-<?php echo (int) $option_value['product_optionvalue_id']; ?>">
                        <?php echo stripslashes($this->escape(J2htmlHelper::translateKey($option_value['optionvalue_name']))); ?>
                        <?php if ($option_value['product_optionvalue_price'] > 0 && $this->params->get('product_option_price', 1)) : ?>
                            (
                            <?php if ($this->params->get('product_option_price_prefix', 1)) : ?>
                                <?php echo $esc($option_value['product_optionvalue_prefix']); ?>
                            <?php endif; ?>
                            <?php echo $product_helper->displayPrice($option_value['product_optionvalue_price'], $this->product, $this->params, 'products.view.option'); ?>
                            )
                        <?php endif; ?>
                    </label>
                </div>
            <?php endforeach; ?>
        </fieldset>
        <?php endif; ?>

        <?php if ($option['type'] == 'checkbox') : ?>
        <!-- checkbox -->
        <fieldset id="option-<?php echo (int) $option['productoption_id']; ?>" class="option mb-3 j2commerce-option-group"<?php echo ProductLayoutService::optionDescribedBy($option); ?>>
            <?php if ($option['required']) : ?>
            <span class="required text-danger">*</span>
            <?php endif; ?>
            <legend class="form-label fw-bold"><?php echo $esc(J2htmlHelper::translateKey($option['option_name'])); ?>:</legend>
<?php echo ProductLayoutService::renderLayout('productoption.description', [
    'description' => $option['option_description'] ?? '',
    'id'          => 'option-desc-' . (int) $option['productoption_id'],
]); ?>
            <?php foreach ($option['optionvalue'] as $option_value) : ?>
                <div class="form-check">
                    <input<?php echo !empty($option_value['product_optionvalue_default']) ? ' checked="checked"' : ''; ?> type="checkbox"
                        name="product_option[<?php echo (int) $option['productoption_id']; ?>][]"
                        value="<?php echo (int) $option_value['product_optionvalue_id']; ?>"
                        id="option-value-<?php echo (int) $option_value['product_optionvalue_id']; ?>"
                        class="form-check-input j2commerce-option-checkbox"
                        data-product-id="<?php echo $productId; ?>"
                        data-option-id="<?php echo (int) $option['productoption_id']; ?>" />
                    <?php if ($this->params->get('image_for_product_options', 0) && isset($option_value['optionvalue_image']) && !empty($option_value['optionvalue_image'])) : ?>
                        <img class="optionvalue-image-<?php echo (int) $option_value['product_optionvalue_id']; ?> img-thumbnail"
                             src="<?php echo $esc(ImageHelper::getImageUrl($option_value['optionvalue_image'])); ?>" alt="" />
                    <?php endif; ?>
                    <label class="form-check-label" for="option-value-<?php echo (int) $option_value['product_optionvalue_id']; ?>">
                        <?php echo stripslashes($this->escape(J2htmlHelper::translateKey($option_value['optionvalue_name']))); ?>
                        <?php if ($option_value['product_optionvalue_price'] > 0 && $this->params->get('product_option_price', 1)) : ?>
                            (
                            <?php if ($this->params->get('product_option_price_prefix', 1)) : ?>
                                <?php echo $esc($option_value['product_optionvalue_prefix']); ?>
                            <?php endif; ?>
                            <?php echo $product_helper->displayPrice($option_value['product_optionvalue_price'], $this->product, $this->params, 'products.view.option'); ?>
                            )
                        <?php endif; ?>
                    </label>
                </div>
            <?php endforeach; ?>
        </fieldset>
        <?php endif; ?>

        <?php if (in_array($option['type'], ['text', 'number', 'email', 'url'], true)) : ?>
            <?php $text_option_params = $platform->getRegistry($option['option_params'] ?? '{}'); ?>
            <!-- text / number / email / url -->
            <div id="option-<?php echo (int) $option['productoption_id']; ?>" class="option mb-3">
                <?php if ($option['required']) : ?>
                <span class="required text-danger">*</span>
                <?php endif; ?>
                <label class="form-label fw-bold"><?php echo $esc(J2htmlHelper::translateKey($option['option_name'])); ?>:</label>
<?php echo ProductLayoutService::renderLayout('productoption.description', [
    'description' => $option['option_description'] ?? '',
    'id'          => 'option-desc-' . (int) $option['productoption_id'],
]); ?>
                <input<?php echo ProductLayoutService::optionDescribedBy($option); ?> type="<?php echo $esc($option['type']); ?>" class="form-control"
                       name="product_option[<?php echo (int) $option['productoption_id']; ?>]"
                       value="<?php echo $esc($option['optionvalue'] ?? ''); ?>"
                       placeholder="<?php echo $esc((string) $text_option_params->get('place_holder', '')); ?>" />
            </div>
        <?php endif; ?>

        <?php if ($option['type'] == 'textarea') : ?>
            <!-- textarea -->
            <div id="option-<?php echo (int) $option['productoption_id']; ?>" class="option mb-3">
                <?php if ($option['required']) : ?>
                <span class="required text-danger">*</span>
                <?php endif; ?>
                <label class="form-label fw-bold"><?php echo $this->escape(J2htmlHelper::translateKey($option['option_name'])); ?>:</label>
<?php echo ProductLayoutService::renderLayout('productoption.description', [
    'description' => $option['option_description'] ?? '',
    'id'          => 'option-desc-' . (int) $option['productoption_id'],
]); ?>
                <textarea<?php echo ProductLayoutService::optionDescribedBy($option); ?>
                    name="product_option[<?php echo (int) $option['productoption_id']; ?>]"
                    class="form-control"
                    cols="20" rows="5"><?php echo $esc($option['optionvalue'] ?? ''); ?></textarea>
            </div>
        <?php endif; ?>

        <?php if ($option['type'] == 'file') : ?>
            <?php echo LayoutHelper::render('productoption.upload_file', [
                'productOptionId' => (int) $option['productoption_id'],
                'productId'       => $productId,
                'required'        => (bool) $option['required'],
                'optionName'      => (string) $option['option_name'],
                'ajaxUrl'         => $uploadAjax,
                'maxSizeMB'       => $uploadMaxMB,
                'allowedExts'     => $fileExts,
                'framework'       => 'bs5',
            ]); ?>
        <?php endif; ?>

        <?php if ($option['type'] == 'image') : ?>
            <?php echo LayoutHelper::render('productoption.upload_image', [
                'productOptionId' => (int) $option['productoption_id'],
                'productId'       => $productId,
                'required'        => (bool) $option['required'],
                'optionName'      => (string) $option['option_name'],
                'ajaxUrl'         => $uploadAjax,
                'maxSizeMB'       => $uploadMaxMB,
                'allowedExts'     => $imageExts,
                'framework'       => 'bs5',
            ]); ?>
        <?php endif; ?>

        <?php if ($option['type'] == 'date') : ?>
            <?php $element_date = 'j2commerce_date_' . (int) $option['productoption_id']; ?>
            <!-- date -->
            <div id="option-<?php echo (int) $option['productoption_id']; ?>" class="option mb-3">
                <?php if ($option['required']) : ?>
                <span class="required text-danger">*</span>
                <?php endif; ?>
                <label class="form-label fw-bold" for="<?php echo $element_date; ?>"><?php echo $esc(J2htmlHelper::translateKey($option['option_name'])); ?>:</label>
<?php echo ProductLayoutService::renderLayout('productoption.description', [
    'description' => $option['option_description'] ?? '',
    'id'          => 'option-desc-' . (int) $option['productoption_id'],
]); ?>
                <?php echo J2CommerceHelper::strapper()->addDatePicker(
                    'product_option[' . (int) $option['productoption_id'] . ']',
                    $element_date,
                    (string) ($option['optionvalue'] ?? ''),
                    $option['option_params'],
                    (bool) $option['required'],
                    trim((string) ($option['option_description'] ?? '')) !== ''
                        ? 'option-desc-' . (int) $option['productoption_id']
                        : ''
                ); ?>
            </div>
        <?php endif; ?>

        <?php if ($option['type'] == 'datetime') : ?>
            <?php $element_datetime = 'j2commerce_datetime_' . (int) $option['productoption_id']; ?>
            <!-- datetime -->
            <div id="option-<?php echo (int) $option['productoption_id']; ?>" class="option mb-3">
                <?php if ($option['required']) : ?>
                <span class="required text-danger">*</span>
                <?php endif; ?>
                <label class="form-label fw-bold" for="<?php echo $element_datetime; ?>"><?php echo $esc(J2htmlHelper::translateKey($option['option_name'])); ?>:</label>
<?php echo ProductLayoutService::renderLayout('productoption.description', [
    'description' => $option['option_description'] ?? '',
    'id'          => 'option-desc-' . (int) $option['productoption_id'],
]); ?>
                <?php echo J2CommerceHelper::strapper()->addDateTimePicker(
                    'product_option[' . (int) $option['productoption_id'] . ']',
                    $element_datetime,
                    (string) ($option['optionvalue'] ?? ''),
                    $option['option_params'],
                    (bool) $option['required'],
                    trim((string) ($option['option_description'] ?? '')) !== ''
                        ? 'option-desc-' . (int) $option['productoption_id']
                        : ''
                ); ?>
            </div>
        <?php endif; ?>

        <?php echo J2CommerceHelper::plugin()->eventWithHtml('AfterDisplaySingleProductOption', [$this->product, $option, $this->context])->getArgument('html', ''); ?>

    <?php endforeach; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    // Handle select option changes
    document.querySelectorAll('.j2commerce-option-select').forEach(select => {
        select.addEventListener('change', function() {
            const productId = this.dataset.productId;
            const optionId = this.dataset.optionId;
            if (typeof doAjaxPrice === 'function') {
                doAjaxPrice(productId, '#option-' + optionId);
            }
        });
    });

    // Handle radio option changes
    document.querySelectorAll('.j2commerce-option-radio').forEach(radio => {
        radio.addEventListener('change', function() {
            const productId = this.dataset.productId;
            const optionId = this.dataset.optionId;
            if (typeof doAjaxPrice === 'function') {
                doAjaxPrice(productId, '#option-' + optionId);
            }
        });
    });

    // Handle checkbox option changes
    document.querySelectorAll('.j2commerce-option-checkbox').forEach(checkbox => {
        checkbox.addEventListener('click', function() {
            const productId = this.dataset.productId;
            const optionId = this.dataset.optionId;
            if (typeof doAjaxPrice === 'function') {
                doAjaxPrice(productId, '#option-' + optionId + ' input:checkbox');
            }
        });
    });

    // File/image upload widgets are handled by media/com_j2commerce/js/site/option-upload-fields.js
});
</script>
<?php endif; ?>
