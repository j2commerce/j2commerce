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
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;

/** @var \J2Commerce\Component\J2commerce\Site\View\Product\HtmlView $this */

$platform       = J2CommerceHelper::platform();
$options        = $this->product->options;
$productId      = (int) $this->product->j2commerce_product_id;
$product_helper = J2CommerceHelper::product();
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
        <div id="option-<?php echo $option['productoption_id']; ?>" class="option uk-margin-small-bottom">
            <?php if ($option['required']) : ?>
            <span class="uk-text-danger">*</span>
            <?php endif; ?>
            <label class="uk-form-label uk-text-bold"><?php echo Text::_($option['option_name']); ?>:</label>
            <select
                class="uk-select j2commerce-option-select"
                name="product_option[<?php echo $option['productoption_id']; ?>]"
                data-product-id="<?php echo $productId; ?>"
                data-option-id="<?php echo $option['productoption_id']; ?>">
                <option value=""><?php echo Text::_('COM_J2COMMERCE_ADDTOCART_SELECT'); ?></option>
                <?php foreach ($option['optionvalue'] as $option_value) : ?>
                    <?php $checked = $option_value['product_optionvalue_default'] ? 'selected="selected"' : ''; ?>
                    <option <?php echo $checked; ?> value="<?php echo $option_value['product_optionvalue_id']; ?>">
                        <?php echo stripslashes(Text::_($option_value['optionvalue_name'])); ?>
                        <?php if ($option_value['product_optionvalue_price'] > 0 && $this->params->get('product_option_price', 1)) : ?>
                        (
                        <?php if ($this->params->get('product_option_price_prefix', 1)) : ?>
                            <?php echo $option_value['product_optionvalue_prefix']; ?>
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
        <div id="option-<?php echo $option['productoption_id']; ?>" class="option uk-margin-small-bottom">
            <?php if ($option['required']) : ?>
            <span class="uk-text-danger">*</span>
            <?php endif; ?>
            <label class="uk-form-label uk-text-bold"><?php echo Text::_($option['option_name']); ?>:</label>
            <?php foreach ($option['optionvalue'] as $option_value) : ?>
                <label class="uk-flex uk-flex-middle" style="gap:.5rem;">
                    <?php $checked = $option_value['product_optionvalue_default'] ? 'checked="checked"' : ''; ?>
                    <input <?php echo $checked; ?> type="radio"
                        autocomplete="off"
                        name="product_option[<?php echo $option['productoption_id']; ?>]"
                        value="<?php echo $option_value['product_optionvalue_id']; ?>"
                        id="option-value-<?php echo $option_value['product_optionvalue_id']; ?>"
                        class="uk-radio j2commerce-option-radio"
                        data-product-id="<?php echo $productId; ?>"
                        data-option-id="<?php echo $option['productoption_id']; ?>" />

                    <?php if ($this->params->get('image_for_product_options', 0) && isset($option_value['optionvalue_image']) && !empty($option_value['optionvalue_image'])) : ?>
                        <img class="optionvalue-image-<?php echo $option_value['product_optionvalue_id']; ?> uk-border-rounded"
                             src="<?php echo Uri::root(true) . '/' . $option_value['optionvalue_image']; ?>" alt="" />
                    <?php endif; ?>
                    <?php echo stripslashes($this->escape(Text::_($option_value['optionvalue_name']))); ?>
                    <?php if ($option_value['product_optionvalue_price'] > 0 && $this->params->get('product_option_price', 1)) : ?>
                        (
                        <?php if ($this->params->get('product_option_price_prefix', 1)) : ?>
                            <?php echo $option_value['product_optionvalue_prefix']; ?>
                        <?php endif; ?>
                        <?php echo $product_helper->displayPrice($option_value['product_optionvalue_price'], $this->product, $this->params, 'products.view.option'); ?>
                        )
                    <?php endif; ?>
                </label>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <?php if ($option['type'] == 'checkbox') : ?>
        <div id="option-<?php echo $option['productoption_id']; ?>" class="option uk-margin-small-bottom">
            <?php if ($option['required']) : ?>
            <span class="uk-text-danger">*</span>
            <?php endif; ?>
            <label class="uk-form-label uk-text-bold"><?php echo Text::_($option['option_name']); ?>:</label>
            <?php foreach ($option['optionvalue'] as $option_value) : ?>
                <label class="uk-flex uk-flex-middle" style="gap:.5rem;">
                    <input type="checkbox"
                        class="uk-checkbox j2commerce-option-checkbox"
                        name="product_option[<?php echo $option['productoption_id']; ?>][]"
                        value="<?php echo $option_value['product_optionvalue_id']; ?>"
                        id="option-value-<?php echo $option_value['product_optionvalue_id']; ?>"
                        data-product-id="<?php echo $productId; ?>"
                        data-option-id="<?php echo $option['productoption_id']; ?>" />
                    <?php if ($this->params->get('image_for_product_options', 0) && isset($option_value['optionvalue_image']) && !empty($option_value['optionvalue_image'])) : ?>
                        <img class="optionvalue-image-<?php echo $option_value['product_optionvalue_id']; ?> uk-border-rounded"
                             src="<?php echo Uri::root(true) . '/' . $option_value['optionvalue_image']; ?>" alt="" />
                    <?php endif; ?>
                    <?php echo stripslashes($this->escape(Text::_($option_value['optionvalue_name']))); ?>
                    <?php if ($option_value['product_optionvalue_price'] > 0 && $this->params->get('product_option_price', 1)) : ?>
                        (
                        <?php if ($this->params->get('product_option_price_prefix', 1)) : ?>
                            <?php echo $option_value['product_optionvalue_prefix']; ?>
                        <?php endif; ?>
                        <?php echo $product_helper->displayPrice($option_value['product_optionvalue_price'], $this->product, $this->params, 'products.view.option'); ?>
                        )
                    <?php endif; ?>
                </label>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <?php if ($option['type'] == 'text') : ?>
            <?php $text_option_params = $platform->getRegistry($option['option_params']); ?>
            <div id="option-<?php echo $option['productoption_id']; ?>" class="option uk-margin-small-bottom">
                <?php if ($option['required']) : ?>
                <span class="uk-text-danger">*</span>
                <?php endif; ?>
                <label class="uk-form-label uk-text-bold"><?php echo Text::_($option['option_name']); ?>:</label>
                <input type="text"
                    class="uk-input"
                    name="product_option[<?php echo $option['productoption_id']; ?>]"
                    value="<?php echo $option['optionvalue']; ?>"
                    placeholder="<?php echo $text_option_params->get('place_holder', ''); ?>" />
            </div>
        <?php endif; ?>

        <?php if ($option['type'] == 'textarea') : ?>
            <div id="option-<?php echo $option['productoption_id']; ?>" class="option uk-margin-small-bottom">
                <?php if ($option['required']) : ?>
                <span class="uk-text-danger">*</span>
                <?php endif; ?>
                <label class="uk-form-label uk-text-bold"><?php echo $this->escape(Text::_($option['option_name'])); ?>:</label>
                <textarea
                    class="uk-textarea"
                    name="product_option[<?php echo $option['productoption_id']; ?>]"
                    cols="20" rows="5"><?php echo $option['optionvalue']; ?></textarea>
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
                'framework'       => 'uikit',
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
                'framework'       => 'uikit',
            ]); ?>
        <?php endif; ?>

        <?php if ($option['type'] == 'date') : ?>
            <?php $element_date = 'j2commerce_date_' . $option['productoption_id']; ?>
            <div id="option-<?php echo $option['productoption_id']; ?>" class="option uk-margin-small-bottom">
                <?php if ($option['required']) : ?>
                <span class="uk-text-danger">*</span>
                <?php endif; ?>
                <label class="uk-form-label uk-text-bold" for="<?php echo $element_date; ?>"><?php echo Text::_($option['option_name']); ?>:</label>
                <?php echo J2CommerceHelper::strapper()->addDatePicker(
                    'product_option[' . $option['productoption_id'] . ']',
                    $element_date,
                    (string) ($option['optionvalue'] ?? ''),
                    $option['option_params'],
                    (bool) $option['required']
                ); ?>
            </div>
        <?php endif; ?>

        <?php if ($option['type'] == 'datetime') : ?>
            <?php $element_datetime = 'j2commerce_datetime_' . $option['productoption_id']; ?>
            <div id="option-<?php echo $option['productoption_id']; ?>" class="option uk-margin-small-bottom">
                <?php if ($option['required']) : ?>
                <span class="uk-text-danger">*</span>
                <?php endif; ?>
                <label class="uk-form-label uk-text-bold" for="<?php echo $element_datetime; ?>"><?php echo Text::_($option['option_name']); ?>:</label>
                <?php echo J2CommerceHelper::strapper()->addDateTimePicker(
                    'product_option[' . $option['productoption_id'] . ']',
                    $element_datetime,
                    (string) ($option['optionvalue'] ?? ''),
                    $option['option_params'],
                    (bool) $option['required']
                ); ?>
            </div>
        <?php endif; ?>

        <?php if ($option['type'] == 'time') : ?>
            <div id="option-<?php echo $option['productoption_id']; ?>" class="option uk-margin-small-bottom">
                <?php if ($option['required']) : ?>
                <span class="uk-text-danger">*</span>
                <?php endif; ?>
                <label class="uk-form-label uk-text-bold"><?php echo Text::_($option['option_name']); ?>:</label>
                <input type="text"
                    class="uk-input j2commerce_time"
                    name="product_option[<?php echo $option['productoption_id']; ?>]"
                    value="<?php echo $option['optionvalue']; ?>" />
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
