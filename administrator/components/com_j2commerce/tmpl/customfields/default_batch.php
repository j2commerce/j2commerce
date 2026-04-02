<?php
/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * Batch modal for bulk-updating custom field display settings.
 * Core areas (billing, shipping, etc.) update DB columns directly.
 * Plugin areas (via GetCustomFieldDisplayAreas event) update field_display JSON.
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

defined('_JEXEC') or die;

use J2Commerce\Component\J2commerce\Administrator\Helper\CustomFieldHelper;
use Joomla\CMS\Language\Text;

// Core display areas — these map to direct DB columns
$coreAreas = [
    'billing'         => 'COM_J2COMMERCE_FIELD_DISPLAY_BILLING',
    'shipping'        => 'COM_J2COMMERCE_FIELD_DISPLAY_SHIPPING',
    'payment'         => 'COM_J2COMMERCE_FIELD_DISPLAY_PAYMENT',
    'register'        => 'COM_J2COMMERCE_FIELD_DISPLAY_REGISTER',
    'guest'           => 'COM_J2COMMERCE_FIELD_DISPLAY_GUEST',
    'guest_shipping'  => 'COM_J2COMMERCE_FIELD_DISPLAY_GUEST_SHIPPING',
];

// Plugin-registered display areas (e.g., vendor_application from vendormanagement)
$pluginAreas = CustomFieldHelper::getRegisteredAreas();

$noChange = Text::_('COM_J2COMMERCE_BATCH_NOCHANGE');
?>

<div class="modal fade" id="collapseModal" tabindex="-1" aria-labelledby="collapseModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="collapseModalLabel"><?php echo Text::_('COM_J2COMMERCE_BATCH_DISPLAY_TITLE'); ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?php echo Text::_('JCLOSE'); ?>"></button>
            </div>
            <div class="modal-body px-4 pt-4 pb-2">
                <p class="text-muted small mb-3"><?php echo Text::_('COM_J2COMMERCE_BATCH_DISPLAY_DESC'); ?></p>

                <h6 class="fw-bold mb-2"><?php echo Text::_('COM_J2COMMERCE_BATCH_DISPLAY_CORE_HEADING'); ?></h6>
                <div class="row">
                    <?php foreach ($coreAreas as $areaKey => $labelKey) : ?>
                        <?php $safeKey = htmlspecialchars($areaKey, ENT_QUOTES, 'UTF-8'); ?>
                        <div class="form-group col-md-6 col-lg-4 mb-3">
                            <label for="batch_display_<?php echo $safeKey; ?>">
                                <?php echo Text::_($labelKey); ?>
                            </label>
                            <select name="batch_display_<?php echo $safeKey; ?>"
                                    id="batch_display_<?php echo $safeKey; ?>"
                                    class="form-select form-select-sm">
                                <option value=""><?php echo $noChange; ?></option>
                                <option value="1"><?php echo Text::_('JYES'); ?></option>
                                <option value="0"><?php echo Text::_('JNO'); ?></option>
                            </select>
                        </div>
                    <?php endforeach; ?>
                </div>

                <?php if (!empty($pluginAreas)) : ?>
                    <hr class="my-3">
                    <h6 class="fw-bold mb-2"><?php echo Text::_('COM_J2COMMERCE_BATCH_DISPLAY_PLUGIN_HEADING'); ?></h6>
                    <div class="row">
                        <?php foreach ($pluginAreas as $area) : ?>
                            <?php
                            $areaKey  = $area['key'] ?? '';
                            $areaName = $area['label'] ?? $areaKey;
                            if ($areaKey === '') {
                                continue;
                            }
                            ?>
                            <div class="form-group col-md-6 col-lg-4 mb-3">
                                <label for="batch_plugin_<?php echo htmlspecialchars($areaKey, ENT_QUOTES, 'UTF-8'); ?>">
                                    <?php echo Text::_($areaName); ?>
                                </label>
                                <select name="batch_plugin_<?php echo htmlspecialchars($areaKey, ENT_QUOTES, 'UTF-8'); ?>"
                                        id="batch_plugin_<?php echo htmlspecialchars($areaKey, ENT_QUOTES, 'UTF-8'); ?>"
                                        class="form-select form-select-sm">
                                    <option value=""><?php echo $noChange; ?></option>
                                    <option value="1"><?php echo Text::_('JYES'); ?></option>
                                    <option value="0"><?php echo Text::_('JNO'); ?></option>
                                </select>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?php echo Text::_('JCANCEL'); ?></button>
                <joomla-toolbar-button task="customfields.batch">
                    <button type="button" class="btn btn-primary"><?php echo Text::_('JGLOBAL_BATCH_PROCESS'); ?></button>
                </joomla-toolbar-button>
            </div>
        </div>
    </div>
</div>
