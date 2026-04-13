<?php
/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use J2Commerce\Component\J2commerce\Administrator\Field\MultiImageUploaderField;
use J2Commerce\Component\J2commerce\Administrator\Helper\J2CommerceHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\FileLayout;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;
use Joomla\Registry\Registry;

/** @var array $displayData */

// Initialize display data with null safety
$product           = $displayData['product'] ?? null;
$formPrefix        = $displayData['form_prefix'] ?? '';
$variantList       = $displayData['variant_list'] ?? [];
$variantPagination = $displayData['variant_pagination'] ?? null;
$weights           = $displayData['weights'] ?? [];
$lengths           = $displayData['lengths'] ?? [];

// Early return if no variants
if (empty($variantList)) {
    echo '<div class="alert alert-info">' . $this->escape(Text::_('COM_J2COMMERCE_NO_RESULTS_FOUND')) . '</div>';
    return;
}

// Initialize platform and load behavior
$platform = J2CommerceHelper::platform();
$platform->loadExtra('behavior.modal');

// Initialize Bootstrap 5 components (loads JS; data-bs-* attributes handle behavior)
HTMLHelper::_('bootstrap.collapse');
HTMLHelper::_('bootstrap.tooltip', '.hasTooltip');

// Constants for styling
$btnClass = 'btn-sm';
$starIcon = 'far fa-regular fa-star';

$wa = Factory::getApplication()->getDocument()->getWebAssetManager();
$style = '.variant-item .variant-button{color:inherit;position:relative;}.variant-item .variant-button:focus,.variant-item .variant-button:active,.variant-button:not(.collapsed){box-shadow:none;background-color:transparent;}.variant-item .variant-button:hover,.variant-item .variant-button:focus,.variant-button:not(.collapsed){color:var(--accordion-active-color);}.variant-item .variant-button:after{width:1rem;height:1rem;background-size:1rem;margin-left:auto;margin-right:1rem;color:var(--accordion-active-color);position:absolute;left:0.5rem;}.variant-item .control-group .control-label{font-size:0.825rem;font-weight:500;}.j2commerce-variant-general .input-group>.input-group-text{border-radius:var(--border-radius-sm);}.j2commerce-variant-general .input-group>.form-control{border-radius:var(--border-radius-sm);}.j2commerce-variant-form .control-group{margin-bottom:0.5rem;}.j2commerce-variant-form .control-label{font-size:0.825rem;font-weight:500;}.j2commerce-variant-form .form-control,.j2commerce-variant-form .form-select{}.j2commerce-variant-main-image .uppymedia-field{margin-top:0.5rem;}.j2commerce-variant-main-image .uppymedia-preview{gap:0.5rem;--uppymedia-main-image-size:200px;}.j2commerce-variant-main-image .uppymedia-image{max-width:200px;}.j2commerce-variant-main-image .uppymedia-image img{max-height:200px;}.j2commerce-variant-main-image .uppymedia-empty-state{padding:1rem;}.j2commerce-variant-main-image .uppymedia-empty-inner .btn{font-size:0.8rem;}.j2commerce-variant-main-image .uppymedia-add-btn{font-size:0.8rem;}';
$wa->addInlineStyle($style, [], []);

// Register MultiImageUploader assets once for all variant galleries
MultiImageUploaderField::loadAssetsStatic();

$layoutPath = JPATH_ADMINISTRATOR . '/components/com_j2commerce/layouts';
$siteRoot       = rtrim(Uri::root(), '/') . '/';


$stripBaseUrl = function (string $value) use ($siteRoot): string {
    while (str_starts_with($value, $siteRoot)) {
        $value = substr($value, strlen($siteRoot));
    }
    $siteRootNoSlash = rtrim($siteRoot, '/');
    while (str_starts_with($value, $siteRootNoSlash)) {
        $value = ltrim(substr($value, strlen($siteRootNoSlash)), '/');
    }
    return $value;
};


$extractPath = function (string $imageValue) use ($stripBaseUrl): array {
    $path = $imageValue;
    $width = 0;
    $height = 0;

    if (str_contains($imageValue, '#')) {
        $path = explode('#', $imageValue, 2)[0];
    }

    $path = $stripBaseUrl($path);

    if (preg_match('/width=(\d+)/', $imageValue, $m)) {
        $width = (int) $m[1];
    }
    if (preg_match('/height=(\d+)/', $imageValue, $m)) {
        $height = (int) $m[1];
    }

    return ['path' => $path, 'width' => $width, 'height' => $height];
};


$enableInventory = (int) J2CommerceHelper::config()->get('enable_inventory', 1);


$counter   = 0;
$canChange = true;

foreach ($variantList as $variant) :
    if ((int) ($variant->is_master ?? 0) === 1) {
        continue;
    }

    $variantId = (int) ($variant->j2commerce_variant_id ?? 0);
    $prefix    = $formPrefix . '[variable][' . $variantId . ']';

    $variantForms = J2CommerceHelper::getVariantForms('flexivariable', $variant, $prefix);


    $paramData        = $platform->getRegistry($variant->params ?? '{}');
    $variantMainImage = $paramData->get('variant_main_image', '');
    $isMainAsThumb    = (int) $paramData->get('is_main_as_thum', 0);

    $variantGallery = [];
    $variantImagesRaw = $paramData->get('variant_images', null);

    if (!empty($variantImagesRaw)) {
        if (\is_string($variantImagesRaw)) {
            $variantImagesRaw = json_decode($variantImagesRaw, true);
        }
        if (\is_object($variantImagesRaw)) {
            $variantImagesRaw = json_decode(json_encode($variantImagesRaw), true);
        }
        if (\is_array($variantImagesRaw)) {
            foreach ($variantImagesRaw as &$galleryItem) {
                if (\is_object($galleryItem)) {
                    $galleryItem = (array) $galleryItem;
                }
            }
            unset($galleryItem);
            $variantGallery = $variantImagesRaw;
        }
    } elseif (!empty($variantMainImage)) {
        $info = $extractPath($variantMainImage);
        $variantGallery[] = [
            'name'       => basename($info['path']),
            'path'       => $info['path'],
            'url'        => $siteRoot . $info['path'],
            'thumb_url'  => $siteRoot . $info['path'],
            'alt_text'   => '',
            'width'      => $info['width'],
            'height'     => $info['height'],
        ];
    }

    $variantCsvIds = $variant->variant_name_ids ?? $variant->variant_name ?? '';
    $rawVariantNames = J2CommerceHelper::product()->getVariantNamesByCSV($variantCsvIds);
    $variantNames    = $this->escape($rawVariantNames);
    $parts           = preg_split('/,(?!\d{3})/', $variantNames);
    $boldParts       = array_map(fn($part) => '<b>' . trim($part) . '</b>', $parts);
    $formattedNames  = implode(' - ', $boldParts);

    $productId        = (int) ($variant->product_id ?? 0);
    $isDefaultVariant = (int) ($variant->isdefault_variant ?? 0);
    $sku              = $this->escape($variant->sku ?? '');
?>
    <div class="variant-item border mb-3 rounded-3 px-3 py-2 text-subdued" data-variant-id="<?php echo $variantId; ?>">
        <div class="accordion-header d-flex align-items-center justify-content-start">
            <input type="hidden"
                   name="<?php echo $this->escape($prefix); ?>[isdefault_variant]"
                   value="<?php echo $isDefaultVariant; ?>"
                   id="isdefault_<?php echo $variantId; ?>" />

            <input id="cid<?php echo $variantId; ?>"
                   class="me-2"
                   type="checkbox"
                   name="vid[]"
                   value="<?php echo $variantId; ?>"
                   aria-label="<?php echo Text::sprintf('COM_J2COMMERCE_SELECT_VARIANT_N', $variantId); ?>" />

            <button class="accordion-button variant-button collapsed p-0 small ps-3"
                    type="button"
                    data-bs-toggle="collapse"
                    data-bs-target="#collapse<?php echo $variantId; ?>"
                    aria-expanded="false"
                    aria-controls="collapse<?php echo $variantId; ?>">
                <span class="variant__id fw-bold me-1 ms-4">(#<?php echo $variantId; ?>)</span>
                <?php echo $formattedNames; ?>
                <?php if (!empty($sku)) : ?>
                    <span class="variant__sku ms-2">(<?php echo $sku; ?>)</span>
                <?php endif; ?>
            </button>

            <?php if ($isDefaultVariant) : ?>
                <a id="default-variant-<?php echo $variantId; ?>"
                   class="btn hasTooltip <?php echo $btnClass; ?> me-2"
                   title="<?php echo $this->escape(Text::_('COM_J2COMMERCE_PRODUCT_VARIANT_UNSET_DEFAULT')); ?>"
                   href="javascript:void(0);"
                   onclick="return listVariableItemTask(<?php echo $variantId; ?>, 'unsetDefault', <?php echo $productId; ?>)"
                   role="button"
                   aria-label="<?php echo $this->escape(Text::_('COM_J2COMMERCE_PRODUCT_VARIANT_UNSET_DEFAULT')); ?>">
                    <span class="icon-featured" aria-hidden="true"></span>
                </a>
            <?php else : ?>
                <a id="default-variant-<?php echo $variantId; ?>"
                   class="btn hasTooltip <?php echo $btnClass; ?> me-2"
                   title="<?php echo $this->escape(Text::_('COM_J2COMMERCE_PRODUCT_VARIANT_SET_DEFAULT')); ?>"
                   href="javascript:void(0);"
                   onclick="return listVariableItemTask(<?php echo $variantId; ?>, 'setDefault', <?php echo $productId; ?>)"
                   role="button"
                   aria-label="<?php echo $this->escape(Text::_('COM_J2COMMERCE_PRODUCT_VARIANT_SET_DEFAULT')); ?>">
                    <span class="<?php echo $starIcon; ?>" aria-hidden="true"></span>
                </a>
            <?php endif; ?>

            <button type="button"
                    class="btn btn-danger <?php echo $btnClass; ?> j2commerce-delete-variant"
                    data-variant-id="<?php echo $variantId; ?>"
                    aria-label="<?php echo $this->escape(Text::sprintf('COM_J2COMMERCE_DELETE_VARIANT_N', $variantId)); ?>">
                <span class="icon icon-trash" aria-hidden="true"></span>
            </button>
        </div>

        <div id="collapse<?php echo $variantId; ?>" class="collapse">
            <div class="accordion-body">
                <div class="row">
                    <div class="col-lg-8">
                        <div class="row">
                            <!-- General Settings -->
                            <div class="col-lg-6 j2commerce-variant-general">
                                <fieldset class="options-form px-3">
                                    <legend class="mb-0"><?php echo $this->escape(Text::_('COM_J2COMMERCE_PRODUCT_TAB_GENERAL')); ?></legend>
                                    <div class="j2commerce-variant-form">
                                        <?php if (isset($variantForms['general'])) : ?>
                                            <?php foreach ($variantForms['general']->getFieldset('variant_general') as $field) : ?>
                                                <?php echo $field->renderField(['class' => 'stack']); ?>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </div>
                                    <?php echo J2CommerceHelper::plugin()->eventWithHtml('AfterDisplayVariantGeneral', [$this, $variant, $prefix])->getArgument('html', ''); ?>
                                </fieldset>
                            </div>

                            <!-- Shipping Settings -->
                            <div class="col-lg-6 j2commerce-variant-shipping">
                                <fieldset class="options-form px-3">
                                    <legend class="mb-0"><?php echo $this->escape(Text::_('COM_J2COMMERCE_PRODUCT_TAB_SHIPPING')); ?></legend>
                                    <div class="j2commerce-variant-form">
                                        <?php if (isset($variantForms['shipping'])) : ?>
                                            <?php foreach ($variantForms['shipping']->getFieldset('variant_shipping') as $field) : ?>
                                                <?php echo $field->renderField(['class' => 'stack']); ?>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </div>
                                    <?php echo J2CommerceHelper::plugin()->eventWithHtml('AfterDisplayVariantShipping', [$this, $variant, $prefix])->getArgument('html', ''); ?>
                                </fieldset>
                            </div>

                            <div class="col-12 j2commerce-variant-main-image">
                                <fieldset class="options-form px-3">
                                    <legend class="mb-0"><?php echo $this->escape(Text::_('COM_J2COMMERCE_PRODUCT_IMAGES')); ?></legend>
                                    <?php
                                    $uppyId = 'j2commerce-variant-uppy-' . $variantId;
                                    echo (new FileLayout('field.multiimageuploader', $layoutPath))->render([
                                        'id'       => $uppyId,
                                        'name'     => $prefix . '[variant_images]',
                                        'value'    => $variantGallery,
                                        'options'  => [
                                            'maxFileSize'       => 10 * 1024 * 1024,
                                            'allowedFileTypes'  => ['image/*'],
                                            'enableCompression' => true,
                                            'enableImageEditor' => true,
                                            'autoThumbnail'     => true,
                                            'directory'         => 'images',
                                            'multiple'          => true,
                                            'endpoint'          => 'index.php?option=com_j2commerce&task=multiimageuploader.upload&format=json',
                                            'formPrefix'        => $prefix,
                                        ],
                                        'required' => false,
                                        'disabled' => false,
                                        'readonly' => false,
                                    ]);
                                    ?>
                                    <div class="j2commerce-variant-form mt-2">
                                        <?php if (isset($variantForms['image'])) : ?>
                                            <?php foreach ($variantForms['image']->getFieldset('variant_image') as $field) : ?>
                                                <?php echo $field->renderField(['class' => 'no-stack']); ?>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </div>
                                    <?php echo J2CommerceHelper::plugin()->eventWithHtml('AfterDisplayVariantImage', [$this, $variant, $prefix])->getArgument('html', ''); ?>
                                </fieldset>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-4 j2commerce-variant-inventory">
                        <fieldset class="options-form px-3">
                            <legend class="mb-0"><?php echo $this->escape(Text::_('COM_J2COMMERCE_PRODUCT_TAB_INVENTORY')); ?></legend>

                                <?php if ($enableInventory === 0) : ?>
                                    <div class="alert alert-warning d-flex align-items-center" role="alert">
                                        <span class="fa-solid fa-exclamation-triangle flex-shrink-0 me-2" aria-hidden="true"></span>
                                        <div>
                                            <?php echo Text::sprintf('COM_J2COMMERCE_PRODUCT_INVENTORY_WARNING',Route::_('index.php?option=com_config&view=component&component=com_j2commerce')); ?>
                                        </div>
                                    </div>
                                <?php endif; ?>

                                <div class="j2commerce-variant-form">
                                    <?php if (isset($variantForms['inventory'])) : ?>
                                        <?php
                                        $noStackFields = ['use_store_config_notify_qty', 'use_store_config_max_sale_qty', 'use_store_config_min_sale_qty'];
                                        foreach ($variantForms['inventory']->getFieldset('variant_inventory') as $field) :
                                            $options = in_array($field->fieldname, $noStackFields, true) ? [] : ['class' => 'stack'];
                                        ?>
                                            <?php echo $field->renderField($options); ?>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>

                            <?php echo J2CommerceHelper::plugin()->eventWithHtml('AfterDisplayVariantInventory', [$this, $variant, $prefix])->getArgument('html', ''); ?>
                        </fieldset>
                    </div>

                    <div class="col-12">
                        <?php echo J2CommerceHelper::plugin()->eventWithHtml('AfterDisplayVariantForm', [$this, $variant, $prefix])->getArgument('html', ''); ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php $counter++; ?>
<?php endforeach; ?>
