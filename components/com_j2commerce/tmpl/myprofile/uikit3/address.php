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

use J2Commerce\Component\J2commerce\Administrator\Helper\CustomFieldHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

/** @var \J2Commerce\Component\J2commerce\Site\View\Myprofile\HtmlView $this */

$address = $this->address;
$fields  = $this->customFields;
$isNew   = !$address || empty($address->j2commerce_address_id);
$addressId = $isNew ? 0 : (int) $address->j2commerce_address_id;
$rawType = $address->type ?? Factory::getApplication()->getInput()->getString('type', 'billing');
$type    = \in_array($rawType, ['billing', 'shipping'], true) ? $rawType : 'billing';
?>

<div class="j2commerce j2commerce-address-edit">
    <div class="uk-margin-bottom">
        <a href="<?php echo Route::_('index.php?option=com_j2commerce&view=myprofile#addresses-pane'); ?>" class="uk-button uk-button-small uk-button-default">
            &larr; <?php echo Text::_('COM_J2COMMERCE_BACK_TO_PROFILE'); ?>
        </a>
    </div>

    <div class="uk-card uk-card-default">
        <div class="uk-card-header">
            <h4 class="uk-card-title uk-margin-remove"><?php echo $isNew ? Text::_('COM_J2COMMERCE_ADDRESS_ADD') : Text::_('COM_J2COMMERCE_ADDRESS_EDIT'); ?></h4>
        </div>
        <div class="uk-card-body">
            <form id="j2commerce-address-form" method="post" novalidate
                  data-country-id="<?php echo (int) ($address->country_id ?? 0); ?>"
                  data-zone-id="<?php echo (int) ($address->zone_id ?? 0); ?>">
                <div class="uk-margin-bottom">
                    <label for="j2c-address-type" class="uk-form-label"><?php echo Text::_('COM_J2COMMERCE_ADDRESS_TYPE'); ?></label>
                    <select name="type" id="j2c-address-type" class="uk-select" style="max-width:200px;">
                        <option value="billing" <?php echo $type === 'billing' ? 'selected' : ''; ?>><?php echo Text::_('COM_J2COMMERCE_BILLING_ADDRESS'); ?></option>
                        <option value="shipping" <?php echo $type === 'shipping' ? 'selected' : ''; ?>><?php echo Text::_('COM_J2COMMERCE_SHIPPING_ADDRESS'); ?></option>
                    </select>
                </div>

                <div class="uk-grid uk-grid-small" uk-grid>
                    <?php foreach ($fields as $field): ?>
                    <?php
                    $namekey = $field->field_namekey;
                    $value = '';
                    if ($address && isset($address->$namekey)) {
                        $value = (string) $address->$namekey;
                    } elseif ($isNew && $namekey === 'email' && $this->user && $this->user->email) {
                        $value = (string) $this->user->email;
                    }
                    echo CustomFieldHelper::renderField($field, $value, [], 'uikit');
                    ?>
                    <?php endforeach; ?>
                </div>

                <input type="hidden" name="address_id" value="<?php echo $addressId; ?>">
                <input type="hidden" name="j2commerce_address_id" value="<?php echo $addressId; ?>">
                <?php echo HTMLHelper::_('form.token'); ?>

                <div class="uk-margin-medium-top">
                    <button type="submit" class="uk-button uk-button-primary"><?php echo Text::_('JAPPLY'); ?></button>
                    <a href="<?php echo Route::_('index.php?option=com_j2commerce&view=myprofile#addresses-pane'); ?>"
                       class="uk-button uk-button-default"><?php echo Text::_('JCANCEL'); ?></a>
                </div>
            </form>
        </div>
    </div>
</div>
