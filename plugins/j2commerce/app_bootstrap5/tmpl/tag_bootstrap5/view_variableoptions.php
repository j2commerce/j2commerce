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
use Joomla\Registry\Registry;

/** @var \J2Commerce\Component\J2commerce\Site\View\Product\HtmlView $this */

$options = $this->product->options ?? [];

if (empty($options)) {
    return;
}

$platform         = J2CommerceHelper::platform();
$productId        = (int) $this->product->j2commerce_product_id;
$product_helper   = J2CommerceHelper::product();
$showOptionImages = (int) ($this->params->get('image_for_product_options', 0) ?? 0);
$esc              = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');

$mediaParams = ComponentHelper::getParams('com_media');
$uploadMaxMB = (float) $mediaParams->get('upload_maxsize', 0);
$fileExts    = strtolower((string) $mediaParams->get('restrict_uploads_extensions', ''));
$imageExts   = strtolower((string) $mediaParams->get('image_extensions', 'bmp,gif,jpg,png,jpeg,webp,avif'));
$uploadAjax  = Route::_('index.php?option=com_j2commerce&view=carts&task=carts.upload&product_id=' . $productId, false);
?>
<div class="options" id="variable-options-<?php echo $productId; ?>">
    <?php foreach ($options as $option) : ?>
        <?php if (!empty($option['parent_id'])) continue; ?>
        <?php $defaultOptionValueId = $this->product->default_option_selections[$option['productoption_id']] ?? ''; ?>

        <?php echo J2CommerceHelper::plugin()->eventWithHtml('BeforeDisplaySingleProductOption', [$this->product, &$option, $this->context])->getArgument('html', ''); ?>

        <?php if ($option['type'] === 'select' && !empty($option['optionvalue'])) : ?>
            <div id="option-<?php echo $option['productoption_id']; ?>" class="option mb-3">
                <label class="form-label fw-semibold pb-1 mb-2">
                    <?php echo $esc(Text::_($option['option_name'])); ?>
                    <?php if ($option['required']) : ?>
                        <span class="text-danger">*</span>
                    <?php endif; ?>
                </label>
                <select name="product_option[<?php echo $option['productoption_id']; ?>]"
                        class="form-select"
                        data-product-id="<?php echo $productId; ?>"
                        data-option-id="<?php echo $option['productoption_id']; ?>"
                        onchange="doAjaxPrice(<?php echo $productId; ?>, 'option-<?php echo $option['productoption_id']; ?>')">
                    <option value="*"><?php echo $esc(Text::_('COM_J2COMMERCE_CHOOSE')); ?></option>
                    <?php foreach ($option['optionvalue'] as $optionValue) : ?>
                        <option value="<?php echo $optionValue['product_optionvalue_id']; ?>"<?php echo ($defaultOptionValueId == $optionValue['product_optionvalue_id']) ? ' selected' : ''; ?>
                            <?php echo $optionValue['product_optionvalue_attribs'] ?? ''; ?>>
                            <?php echo $esc(Text::_($optionValue['optionvalue_name'])); ?>
                            <?php if ($optionValue['product_optionvalue_price'] > 0 && $this->params->get('product_option_price', 1)) : ?>
                                (
                                <?php if ($this->params->get('product_option_price_prefix', 1)) : ?>
                                    <?php echo $optionValue['product_optionvalue_prefix']; ?>
                                <?php endif; ?>
                                <?php echo $product_helper->displayPrice($optionValue['product_optionvalue_price'], $this->product, $this->params, 'products.view.option'); ?>
                                )
                            <?php endif; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        <?php endif; ?>

        <?php if ($option['type'] === 'radio' && !empty($option['optionvalue'])) : ?>
            <div id="option-<?php echo $option['productoption_id']; ?>" class="option mb-3">
                <label class="form-label fw-semibold pb-1 mb-2">
                    <?php echo $esc(Text::_($option['option_name'])); ?>:
                    <?php if ($option['required']) : ?>
                        <span class="text-danger">*</span>
                    <?php endif; ?>
                    <span class="fw-normal fs-sm ms-1" id="radioOption<?php echo $option['productoption_id']; ?>"></span>
                </label>
                <div class="j2commerce-radio-options d-flex flex-wrap gap-2" data-binded-label="#radioOption<?php echo $option['productoption_id']; ?>">
                    <?php foreach ($option['optionvalue'] as $optionValue) : ?>
                        <input
                            type="radio"
                            name="product_option[<?php echo $option['productoption_id']; ?>]"
                            value="<?php echo $optionValue['product_optionvalue_id']; ?>"
                            id="option-value-<?php echo $optionValue['product_optionvalue_id']; ?>"
                            class="btn-check"
                            onclick="doAjaxPrice(<?php echo $productId; ?>, 'option-<?php echo (int) $option['productoption_id']; ?>')"
                            <?php echo ($defaultOptionValueId == $optionValue['product_optionvalue_id']) ? 'checked' : ''; ?>
                            autocomplete="off"
                            data-product-id="<?php echo $productId; ?>"
                            data-option-id="<?php echo (int) $option['productoption_id']; ?>"
                            <?php echo $optionValue['product_optionvalue_attribs'] ?? ''; ?>
                        />

                        <?php if ($showOptionImages && !empty($optionValue['optionvalue_image'])) : ?>
                            <label class="btn btn-image p-0 form-check-label border-2" for="option-value-<?php echo $optionValue['product_optionvalue_id']; ?>" data-label="<?php echo $esc(Text::_($optionValue['optionvalue_name'])); ?>">
                                <img class="optionvalue-image me-1" src="<?php echo Uri::root(true) . '/' . $esc($optionValue['optionvalue_image']); ?>" alt="<?php echo $esc(Text::_($optionValue['optionvalue_name'])); ?>" width="56" style="width:56px;" />
                                <span class="visually-hidden"><?php echo $esc(Text::_($optionValue['optionvalue_name'])); ?></span>
                            </label>
                        <?php else : ?>
                            <label class="btn btn-sm btn-outline-secondary form-check-label border-2" for="option-value-<?php echo $optionValue['product_optionvalue_id']; ?>" data-label="<?php echo $esc(Text::_($optionValue['optionvalue_name'])); ?>">
                                <?php echo $esc(Text::_($optionValue['optionvalue_name'])); ?>
                                <?php if ($optionValue['product_optionvalue_price'] > 0 && $this->params->get('product_option_price', 1)) : ?>
                                    <?php if ($this->params->get('product_option_price_prefix', 1)) : ?>
                                        <?php echo $optionValue['product_optionvalue_prefix']; ?>
                                    <?php endif; ?>
                                    <?php echo $product_helper->displayPrice($optionValue['product_optionvalue_price'], $this->product, $this->params, 'products.view.option'); ?>
                                <?php endif; ?>
                            </label>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($option['type'] === 'color' && !empty($option['optionvalue'])) : ?>
            <div id="option-<?php echo (int) $option['productoption_id']; ?>" class="option mb-3">
                <label class="form-label fw-semibold pb-1 mb-2">
                    <?php echo $esc(Text::_($option['option_name'])); ?>:
                    <?php if ($option['required']) : ?>
                        <span class="text-danger">*</span>
                    <?php endif; ?>
                    <span class="fw-normal fs-sm ms-1" id="colorOption<?php echo (int) $option['productoption_id']; ?>"></span>
                </label>
                <div class="j2commerce-color-options d-flex flex-wrap gap-2" data-binded-label="#colorOption<?php echo (int) $option['productoption_id']; ?>">
                    <?php foreach ($option['optionvalue'] as $optionValue) : ?>
                        <input
                            type="radio"
                            name="product_option[<?php echo (int) $option['productoption_id']; ?>]"
                            value="<?php echo (int) $optionValue['product_optionvalue_id']; ?>"
                            id="option-value-<?php echo (int) $optionValue['product_optionvalue_id']; ?>"
                            class="btn-check"
                            autocomplete="off"
                            onclick="doAjaxPrice(<?php echo $productId; ?>, 'option-<?php echo (int) $option['productoption_id']; ?>')"
                            data-product-id="<?php echo $productId; ?>"
                            data-option-id="<?php echo (int) $option['productoption_id']; ?>"
                            <?php echo ($defaultOptionValueId == $optionValue['product_optionvalue_id']) ? 'checked' : ''; ?>
                            <?php echo $optionValue['product_optionvalue_attribs'] ?? ''; ?>
                        />
                        <label for="option-value-<?php echo (int) $optionValue['product_optionvalue_id']; ?>" class="btn btn-color fs-xl" title="<?php echo $esc(Text::_($optionValue['optionvalue_name'])); ?>" data-label="<?php echo $esc(Text::_($optionValue['optionvalue_name'])); ?>" style="color:<?php echo $esc($optionValue['optionvalue_image']); ?>;">
                            <span class="visually-hidden"><?php echo $esc(Text::_($optionValue['optionvalue_name'])); ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($option['type'] === 'checkbox' && !empty($option['optionvalue'])) : ?>
            <div id="option-<?php echo $option['productoption_id']; ?>" class="option mb-3">
                <label class="form-label fw-semibold pb-1 mb-2">
                    <?php echo $esc(Text::_($option['option_name'])); ?>:
                    <?php if ($option['required']) : ?>
                        <span class="text-danger">*</span>
                    <?php endif; ?>
                </label>
                <div class="d-flex flex-wrap gap-2">
                    <?php foreach ($option['optionvalue'] as $optionValue) : ?>
                        <div class="form-check">
                            <input type="checkbox"
                                   name="product_option[<?php echo $option['productoption_id']; ?>][]"
                                   value="<?php echo $optionValue['product_optionvalue_id']; ?>"
                                   id="option-value-<?php echo $optionValue['product_optionvalue_id']; ?>"
                                   class="form-check-input"
                                   onchange="doAjaxPrice(<?php echo $productId; ?>, 'option-<?php echo $option['productoption_id']; ?>')"
                                   data-product-id="<?php echo $productId; ?>"
                                   data-option-id="<?php echo $option['productoption_id']; ?>" />
                            <?php if ($showOptionImages && !empty($optionValue['optionvalue_image'])) : ?>
                                <img class="optionvalue-image me-1"
                                     src="<?php echo Uri::root(true) . '/' . $esc($optionValue['optionvalue_image']); ?>"
                                     alt="<?php echo $esc(Text::_($optionValue['optionvalue_name'])); ?>"
                                     width="32" />
                            <?php endif; ?>
                            <label class="form-check-label" for="option-value-<?php echo $optionValue['product_optionvalue_id']; ?>">
                                <?php echo $esc(Text::_($optionValue['optionvalue_name'])); ?>
                                <?php if ($optionValue['product_optionvalue_price'] > 0 && $this->params->get('product_option_price', 1)) : ?>
                                    (
                                    <?php if ($this->params->get('product_option_price_prefix', 1)) : ?>
                                        <?php echo $optionValue['product_optionvalue_prefix']; ?>
                                    <?php endif; ?>
                                    <?php echo $product_helper->displayPrice($optionValue['product_optionvalue_price'], $this->product, $this->params, 'products.view.option'); ?>
                                    )
                                <?php endif; ?>
                            </label>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($option['type'] === 'text') : ?>
            <?php $text_option_params = $platform->getRegistry($option['option_params']); ?>
            <div id="option-<?php echo $option['productoption_id']; ?>" class="option mb-3">
                <label class="form-label fw-semibold pb-1 mb-2" for="option-input-<?php echo $option['productoption_id']; ?>">
                    <?php echo $esc(Text::_($option['option_name'])); ?>
                    <?php if ($option['required']) : ?>
                        <span class="text-danger">*</span>
                    <?php endif; ?>
                </label>
                <input type="text"
                       id="option-input-<?php echo $option['productoption_id']; ?>"
                       class="form-control"
                       name="product_option[<?php echo $option['productoption_id']; ?>]"
                       value="<?php echo $esc($option['optionvalue'] ?? ''); ?>"
                       placeholder="<?php echo $esc($text_option_params->get('place_holder', '')); ?>" />
            </div>
        <?php endif; ?>

        <?php if ($option['type'] === 'textarea') : ?>
            <div id="option-<?php echo $option['productoption_id']; ?>" class="option mb-3">
                <label class="form-label fw-semibold pb-1 mb-2" for="option-textarea-<?php echo $option['productoption_id']; ?>">
                    <?php echo $esc(Text::_($option['option_name'])); ?>
                    <?php if ($option['required']) : ?>
                        <span class="text-danger">*</span>
                    <?php endif; ?>
                </label>
                <textarea id="option-textarea-<?php echo $option['productoption_id']; ?>"
                          class="form-control"
                          name="product_option[<?php echo $option['productoption_id']; ?>]"
                          cols="20" rows="5"><?php echo $esc($option['optionvalue'] ?? ''); ?></textarea>
            </div>
        <?php endif; ?>

        <?php if ($option['type'] === 'file') : ?>
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

        <?php if ($option['type'] === 'image') : ?>
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

        <?php if ($option['type'] === 'date') : ?>
            <?php $element_date = 'j2commerce_date_' . $option['productoption_id']; ?>
            <div id="option-<?php echo $option['productoption_id']; ?>" class="option mb-3">
                <label class="form-label fw-semibold pb-1 mb-2" for="<?php echo $element_date; ?>">
                    <?php echo $esc(Text::_($option['option_name'])); ?>
                    <?php if ($option['required']) : ?>
                        <span class="text-danger">*</span>
                    <?php endif; ?>
                </label>
                <?php echo J2CommerceHelper::strapper()->addDatePicker(
                    'product_option[' . $option['productoption_id'] . ']',
                    $element_date,
                    (string) ($option['optionvalue'] ?? ''),
                    $option['option_params'],
                    (bool) $option['required']
                ); ?>
            </div>
        <?php endif; ?>

        <?php if ($option['type'] === 'datetime') : ?>
            <?php $element_datetime = 'j2commerce_datetime_' . $option['productoption_id']; ?>
            <div id="option-<?php echo $option['productoption_id']; ?>" class="option mb-3">
                <label class="form-label fw-semibold pb-1 mb-2" for="<?php echo $element_datetime; ?>">
                    <?php echo $esc(Text::_($option['option_name'])); ?>
                    <?php if ($option['required']) : ?>
                        <span class="text-danger">*</span>
                    <?php endif; ?>
                </label>
                <?php echo J2CommerceHelper::strapper()->addDateTimePicker(
                    'product_option[' . $option['productoption_id'] . ']',
                    $element_datetime,
                    (string) ($option['optionvalue'] ?? ''),
                    $option['option_params'],
                    (bool) $option['required']
                ); ?>
            </div>
        <?php endif; ?>

        <?php if ($option['type'] === 'time') : ?>
            <div id="option-<?php echo $option['productoption_id']; ?>" class="option mb-3">
                <label class="form-label fw-semibold pb-1 mb-2" for="j2commerce_time_<?php echo $option['productoption_id']; ?>">
                    <?php echo $esc(Text::_($option['option_name'])); ?>
                    <?php if ($option['required']) : ?>
                        <span class="text-danger">*</span>
                    <?php endif; ?>
                </label>
                <input type="text"
                       id="j2commerce_time_<?php echo $option['productoption_id']; ?>"
                       class="form-control j2commerce_time"
                       name="product_option[<?php echo $option['productoption_id']; ?>]"
                       value="<?php echo $esc($option['optionvalue'] ?? ''); ?>" />
            </div>
        <?php endif; ?>

        <?php echo J2CommerceHelper::plugin()->eventWithHtml('AfterDisplaySingleProductOption', [$this->product, $option, $this->context])->getArgument('html', ''); ?>

        <div id="ChildOptions<?php echo $option['productoption_id']; ?>"></div>

    <?php endforeach; ?>
</div>

<?php /* File/image upload widgets are handled by media/com_j2commerce/js/site/option-upload-fields.js */ ?>
