<?php
/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use J2Commerce\Component\J2commerce\Administrator\Field\MultiImageUploaderField;
use J2Commerce\Component\J2commerce\Administrator\Helper\J2CommerceHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\FileLayout;
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\Uri\Uri;

$item = $displayData['product'];
$formPrefix = $displayData['form_prefix'] ?? 'jform[attribs][j2commerce]';

// Defaults for Joomla core layout fields to prevent PHP 8.4 undefined variable warnings
$textFieldDefaults = ['value' => '', 'onchange' => '', 'disabled' => false, 'readonly' => false, 'dataAttribute' => '', 'hint' => '', 'required' => false, 'autofocus' => false, 'spellcheck' => false, 'addonBefore' => '', 'addonAfter' => '', 'dirname' => '', 'charcounter' => false, 'options' => []];

$product_params = json_decode($item->params);
$productId = $item->j2commerce_product_id ?? 0;

// Load MultiImageUploader assets
MultiImageUploaderField::loadAssetsStatic();

// Build files data from existing productfiles
$filesData = [];
$siteRoot = rtrim(Uri::root(), '/') . '/';

// Strip repeated Uri::root() prefixes from a stored file path
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

// Load existing product files from database
if ($productId) {
    $db = Factory::getContainer()->get('DatabaseDriver');
    $query = $db->getQuery(true)
        ->select('*')
        ->from($db->quoteName('#__j2commerce_productfiles'))
        ->where($db->quoteName('product_id') . ' = :productId')
        ->order($db->quoteName('j2commerce_productfile_id') . ' ASC')
        ->bind(':productId', $productId, \Joomla\Database\ParameterType::INTEGER);

    $db->setQuery($query);
    $existingFiles = $db->loadObjectList();

    foreach ($existingFiles as $file) {
        $path = $stripBaseUrl($file->product_file_save_name);

        $filesData[] = [
            'id'          => $file->j2commerce_productfile_id,
            'name'        => $file->product_file_display_name ?: basename($path),
            'path'        => $path,
            'url'         => $siteRoot . $path,
            'download_total' => $file->download_total ?? 0,
        ];
    }
}

$layoutPath = JPATH_ADMINISTRATOR . '/components/com_j2commerce/layouts';
?>

<div class="j2commerce-product-files">
    <fieldset class="options-form">
        <legend><?php echo Text::_('COM_J2COMMERCE_PRODUCT_TAB_FILES'); ?></legend>
        <div class="form-grid">
            <div class="control-group">
                <div class="control-label">
                    <label id="j2commerce-product-files-lbl" for="j2commerce-product-files"><?php echo Text::_('COM_J2COMMERCE_PRODUCT_FILES'); ?></label>
                </div>
                <div class="controls">
                    <?php
                    echo (new FileLayout('field.multiimageuploader', $layoutPath))->render([
                        'id'       => 'j2commerce-uppy-files',
                        'name'     => 'uppymedia_files',
                        'value'    => $filesData,
                        'options'  => [
                            'maxFileSize'      => 100 * 1024 * 1024, // 100MB for downloadable files
                            'allowedFileTypes' => null, // null = allow all file types
                            'enableCompression' => false,
                            'enableImageEditor' => false,
                            'autoThumbnail'     => false,
                            'directory'         => 'images/downloads',
                            'multiple'          => true,
                            'endpoint'          => 'index.php?option=com_j2commerce&task=multiimageuploader.upload&format=json',
                            'formPrefix'        => $formPrefix,
                            'fileMode'          => true, // Flag for file mode vs image mode
                        ],
                        'required' => false,
                        'disabled' => false,
                        'readonly' => false,
                    ]);
                    ?>
                </div>
            </div>

            <div class="control-group">
                <div class="control-label">
                    <label id="j2commerce-product-download_limit-lbl" for="j2commerce-product-download_limit"><?php echo Text::_('COM_J2COMMERCE_PRODUCT_FILE_DOWNLOAD_LIMIT'); ?></label>
                </div>
                <div class="controls">
                    <?php echo LayoutHelper::render('joomla.form.field.text', [
                        'name'  => $formPrefix . '[params][download_limit]',
                        'id'    => 'j2commerce-product-download_limit',
                        'value' => $product_params->download_limit ?? '',
                        'class' => 'form-control',
                        'type'  => 'number',
                    ] + $textFieldDefaults); ?>
                    <small class="form-text text-muted"><?php echo Text::_('COM_J2COMMERCE_PRODUCT_FILE_DOWNLOAD_LIMIT_DESC'); ?></small>
                </div>
            </div>

            <div class="control-group">
                <div class="control-label">
                    <label id="j2commerce-product-download_expiry-lbl" for="j2commerce-product-download_expiry"><?php echo Text::_('COM_J2COMMERCE_PRODUCT_FILE_DOWNLOAD_EXPIRY'); ?></label>
                </div>
                <div class="controls">
                    <div class="input-group">
                        <span class="input-group-text"><?php echo Text::_('COM_J2COMMERCE_DAYS'); ?></span>
                        <?php echo LayoutHelper::render('joomla.form.field.text', [
                            'name'  => $formPrefix . '[params][download_expiry]',
                            'id'    => 'j2commerce-product-download_expiry',
                            'value' => $product_params->download_expiry ?? '',
                            'class' => 'form-control',
                            'type'  => 'number',
                        ] + $textFieldDefaults); ?>
                    </div>
                    <small class="form-text text-muted"><?php echo Text::_('COM_J2COMMERCE_PRODUCT_FILE_DOWNLOAD_EXPIRY_DESC'); ?></small>
                </div>
            </div>

            <?php echo J2CommerceHelper::plugin()->eventWithHtml('AfterProductFilesEdit', [$this, $item, $formPrefix])->getArgument('html', ''); ?>
        </div>
    </fieldset>

    <div class="alert alert-info mt-3">
        <h2 class="alert-heading fs-4"><?php echo Text::_('COM_J2COMMERCE_QUICK_HELP'); ?></h2>
        <p class="mb-1"><?php echo Text::_('COM_J2COMMERCE_PRODUCT_FILES_HELP_TEXT'); ?></p>
        <ul class="mb-0">
            <li><?php echo Text::_('COM_J2COMMERCE_PRODUCT_FILES_HELP_DOWNLOAD_LIMIT'); ?></li>
            <li><?php echo Text::_('COM_J2COMMERCE_PRODUCT_FILES_HELP_DOWNLOAD_EXPIRY'); ?></li>
        </ul>
    </div>
</div>

<script type="text/javascript">
document.addEventListener('DOMContentLoaded', function() {
    // Initialize file sync handler for downloadable products
    const filesWrapper = document.getElementById('j2commerce-uppy-files-wrapper');

    if (filesWrapper) {
        // Create hidden inputs for file data when form is submitted
        const form = filesWrapper.closest('form');

        if (form) {
            form.addEventListener('submit', function() {
                const hiddenInput = document.getElementById('j2commerce-uppy-files');
                if (hiddenInput) {
                    try {
                        const files = JSON.parse(hiddenInput.value || '[]');

                        // Remove existing file inputs
                        const existingInputs = form.querySelectorAll('input[name^="<?php echo $formPrefix; ?>[files]"]');
                        existingInputs.forEach(function(input) {
                            input.remove();
                        });

                        // Create inputs for each file
                        files.forEach(function(file, index) {
                            // File ID (for existing files)
                            if (file.id) {
                                const idInput = document.createElement('input');
                                idInput.type = 'hidden';
                                idInput.name = '<?php echo $formPrefix; ?>[files][' + index + '][id]';
                                idInput.value = file.id;
                                form.appendChild(idInput);
                            }

                            // Display name
                            const nameInput = document.createElement('input');
                            nameInput.type = 'hidden';
                            nameInput.name = '<?php echo $formPrefix; ?>[files][' + index + '][display_name]';
                            nameInput.value = file.name || '';
                            form.appendChild(nameInput);

                            // File path
                            const pathInput = document.createElement('input');
                            pathInput.type = 'hidden';
                            pathInput.name = '<?php echo $formPrefix; ?>[files][' + index + '][path]';
                            pathInput.value = file.path || '';
                            form.appendChild(pathInput);

                            // Download total
                            const totalInput = document.createElement('input');
                            totalInput.type = 'hidden';
                            totalInput.name = '<?php echo $formPrefix; ?>[files][' + index + '][download_total]';
                            totalInput.value = file.download_total || 0;
                            form.appendChild(totalInput);
                        });
                    } catch (e) {
                        console.error('Error parsing files data:', e);
                    }
                }
            });
        }
    }
});
</script>
