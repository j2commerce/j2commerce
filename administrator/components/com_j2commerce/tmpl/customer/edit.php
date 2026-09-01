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

use J2Commerce\Component\J2commerce\Administrator\Helper\J2htmlHelper;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;
use Joomla\CMS\Uri\Uri;

/** @var \J2Commerce\Component\J2commerce\Administrator\View\Customer\HtmlView $this */

$wa = $this->getDocument()->getWebAssetManager();
$wa->useScript('keepalive')
    ->useScript('form.validate');

HTMLHelper::_('bootstrap.modal');

$wa->registerAndUseScript(
    'com_j2commerce.dom',
    'media/com_j2commerce/js/site/j2commerce-dom.js',
    [],
    ['defer' => true]
);
$wa->registerAndUseScript(
    'com_j2commerce.customer-addresses',
    'com_j2commerce/administrator/customer-addresses.js',
    [],
    ['defer' => true],
    ['com_j2commerce.dom']
);

$baseUri     = Uri::base(true);
$formToken   = Session::getFormToken();
$targetUser  = (int) ($this->item->user_id ?? 0);
$currentId   = (int) ($this->item->j2commerce_address_id ?? 0);

$this->getDocument()->addScriptOptions('com_j2commerce.customer_addresses', [
    'token'         => $formToken,
    'formUrl'       => $baseUri . '/index.php?option=com_j2commerce&task=customer.ajaxGetAddressForm&format=raw',
    'saveUrl'       => $baseUri . '/index.php?option=com_j2commerce&task=customer.ajaxSaveAddress&format=raw',
    'deleteUrl'     => $baseUri . '/index.php?option=com_j2commerce&task=customer.ajaxDeleteAddress&format=raw',
    'relinkUrl'     => $baseUri . '/index.php?option=com_j2commerce&task=customer.ajaxRelinkUser&format=raw',
    'zonesUrl'      => $baseUri . '/index.php?option=com_j2commerce&task=ajax.getZones&response=json',
    'userId'        => $targetUser,
    'currentId'     => $currentId,
    'cardMode'      => $this->useCardMode,
    'strings'       => [
        'confirmDelete' => Text::_('COM_J2COMMERCE_CUSTOMER_ADDRESSES_DELETE_CONFIRM', true),
        'confirmRelink' => Text::_('COM_J2COMMERCE_CUSTOMER_USER_RELINK_CONFIRM', true),
        'loading'       => Text::_('COM_J2COMMERCE_LOADING', true),
        'genericError'  => Text::_('COM_J2COMMERCE_CUSTOMER_ADDRESSES_SAVE_ERROR', true),
        'unnamed'       => Text::_('COM_J2COMMERCE_CUSTOMER_ADDRESSES_UNNAMED', true),
        'typeLabel'     => Text::_('COM_J2COMMERCE_FIELD_ADDRESS_TYPE', true),
        'cardActions'   => Text::_('COM_J2COMMERCE_CUSTOMER_ADDRESSES_CARD_ACTIONS', true),
        'editAria'      => Text::_('COM_J2COMMERCE_CUSTOMER_ADDRESSES_EDIT_ARIA', true),
        'deleteAria'    => Text::_('COM_J2COMMERCE_CUSTOMER_ADDRESSES_DELETE_ARIA', true),
    ],
]);

$coreFields = [
    'first_name', 'last_name', 'email', 'company', 'type',
    'address_1', 'address_2', 'city', 'zip',
    'country_id', 'zone_id', 'phone_1', 'phone_2', 'tax_number',
];

?>
<form action="<?php echo Route::_('index.php?option=com_j2commerce&layout=edit&id=' . (int) $this->item->j2commerce_address_id); ?>" method="post" name="adminForm" id="customer-form" class="form-validate">

    <div class="main-card">
        <?php if ($this->useCardMode) : ?>
            <div class="p-3">
                <div class="row">
                    <div class="col-lg-9">
                    <fieldset id="fieldset-customer-addresses" class="options-form">
                        <legend><?php echo Text::_('COM_J2COMMERCE_FIELDSET_CUSTOMER_ADDRESSES'); ?></legend>

                        <ul class="row g-3 list-unstyled mb-0" id="j2commerce-address-cards" role="list" aria-label="<?php echo Text::_('COM_J2COMMERCE_FIELDSET_CUSTOMER_ADDRESSES'); ?>">
                            <?php foreach ($this->addresses as $addr) :
                                $cardId   = (int) $addr->j2commerce_address_id;
                                $fullName = trim(($addr->first_name ?? '') . ' ' . ($addr->last_name ?? ''));
                                $typeText = (string) ($addr->type ?? 'billing');
                                $headingId = 'j2commerce-address-card-heading-' . $cardId;
                                ?>
                                <li class="col-md-6 col-xl-4 j2commerce-address-card-wrap" data-address-id="<?php echo $cardId; ?>">
                                    <article class="card h-100 rounded-1 shadow-sm border" aria-labelledby="<?php echo $headingId; ?>">
                                        <header class="card-header d-flex justify-content-between align-items-center">
                                            <span class="<?php echo J2htmlHelper::badgeClass('badge text-bg-info'); ?> text-uppercase">
                                                <span class="visually-hidden"><?php echo Text::_('COM_J2COMMERCE_FIELD_ADDRESS_TYPE'); ?>:</span>
                                                <?php echo $this->escape($typeText); ?>
                                            </span>
                                            <div class="btn-group btn-group-sm" role="group" aria-label="<?php echo $this->escape(Text::sprintf('COM_J2COMMERCE_CUSTOMER_ADDRESSES_CARD_ACTIONS', $fullName)); ?>">
                                                <button type="button" class="btn btn-outline-secondary j2commerce-address-edit" data-address-id="<?php echo $cardId; ?>" aria-label="<?php echo $this->escape(Text::sprintf('COM_J2COMMERCE_CUSTOMER_ADDRESSES_EDIT_ARIA', $fullName)); ?>">
                                                    <span class="icon-edit" aria-hidden="true"></span>
                                                </button>
                                                <button type="button" class="btn btn-outline-danger j2commerce-address-delete" data-address-id="<?php echo $cardId; ?>" aria-label="<?php echo $this->escape(Text::sprintf('COM_J2COMMERCE_CUSTOMER_ADDRESSES_DELETE_ARIA', $fullName)); ?>">
                                                    <span class="icon-trash" aria-hidden="true"></span>
                                                </button>
                                            </div>
                                        </header>
                                        <div class="card-body">
                                            <h3 class="card-title h6 mb-2" id="<?php echo $headingId; ?>">
                                                <?php echo $this->escape($fullName !== '' ? $fullName : Text::_('COM_J2COMMERCE_CUSTOMER_ADDRESSES_UNNAMED')); ?>
                                            </h3>
                                            <address class="mb-0">
                                                <?php if (!empty($addr->company)) : ?>
                                                    <?php echo $this->escape($addr->company); ?><br>
                                                <?php endif; ?>
                                                <?php echo $this->escape($addr->address_1 ?? ''); ?><br>
                                                <?php if (!empty($addr->address_2)) : ?>
                                                    <?php echo $this->escape($addr->address_2); ?><br>
                                                <?php endif; ?>
                                                <?php echo $this->escape($addr->city ?? ''); ?><?php if (!empty($addr->zip)) : ?>, <?php echo $this->escape($addr->zip); ?><?php endif; ?><br>
                                                <?php if (!empty($addr->zone_name)) : ?>
                                                    <?php echo $this->escape($addr->zone_name); ?>,
                                                <?php endif; ?>
                                                <?php echo $this->escape($addr->country_name ?? ''); ?>
                                                <?php if (!empty($addr->phone_1)) : ?>
                                                    <br>
                                                    <span class="icon-phone" aria-hidden="true"></span>
                                                    <a href="tel:<?php echo $this->escape((string) $addr->phone_1); ?>"><?php echo $this->escape($addr->phone_1); ?></a>
                                                <?php endif; ?>
                                                <?php if (!empty($addr->email)) : ?>
                                                    <br>
                                                    <span class="icon-envelope" aria-hidden="true"></span>
                                                    <a href="mailto:<?php echo $this->escape((string) $addr->email); ?>"><?php echo $this->escape($addr->email); ?></a>
                                                <?php endif; ?>
                                            </address>
                                        </div>
                                    </article>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </fieldset>
                </div>
                <div class="col-lg-3">
                    <fieldset id="fieldset-user" class="options-form">
                        <legend><?php echo Text::_('COM_J2COMMERCE_FIELD_USER'); ?></legend>
                        <div class="form-grid">
                            <?php echo $this->form->renderField('user_id'); ?>
                        </div>
                        <p class="form-text small text-body-secondary mt-2">
                            <?php echo Text::_('COM_J2COMMERCE_CUSTOMER_USER_RELINK_NOTE'); ?>
                        </p>
                    </fieldset>
                </div>
            </div>
            </div>
        <?php else : ?>
            <?php echo HTMLHelper::_('uitab.startTabSet', 'myTab', ['active' => 'details', 'recall' => true, 'breakpoint' => 768]); ?>

            <?php echo HTMLHelper::_('uitab.addTab', 'myTab', 'details', Text::_('COM_J2COMMERCE_FIELDSET_CUSTOMER_DETAILS')); ?>
            <div class="row">
                <div class="col-lg-9">
                    <fieldset id="fieldset-customer" class="options-form">
                        <legend><?php echo Text::_('COM_J2COMMERCE_FIELDSET_CUSTOMER_DETAILS'); ?></legend>
                        <div class="form-grid">
                            <?php echo $this->form->renderField('first_name'); ?>
                            <?php echo $this->form->renderField('last_name'); ?>
                            <?php echo $this->form->renderField('email'); ?>
                            <?php echo $this->form->renderField('company'); ?>
                        </div>
                    </fieldset>
                </div>
                <div class="col-lg-3">
                    <fieldset id="fieldset-user" class="options-form">
                        <legend><?php echo Text::_('COM_J2COMMERCE_FIELD_USER'); ?></legend>
                        <div class="form-grid">
                            <?php echo $this->form->renderField('user_id'); ?>
                        </div>
                    </fieldset>
                </div>
            </div>
            <?php echo HTMLHelper::_('uitab.endTab'); ?>

            <?php echo HTMLHelper::_('uitab.addTab', 'myTab', 'address', Text::_('COM_J2COMMERCE_FIELDSET_ADDRESS')); ?>
            <div class="row">
                <div class="col-lg-12">
                    <fieldset id="fieldset-address" class="options-form">
                        <legend><?php echo Text::_('COM_J2COMMERCE_FIELDSET_ADDRESS'); ?></legend>
                        <div class="form-grid">
                            <?php echo $this->form->renderField('type'); ?>
                            <?php echo $this->form->renderField('address_1'); ?>
                            <?php echo $this->form->renderField('address_2'); ?>
                            <?php echo $this->form->renderField('city'); ?>
                            <?php echo $this->form->renderField('zip'); ?>
                            <?php echo $this->form->renderField('country_id'); ?>
                            <?php echo $this->form->renderField('zone_id'); ?>
                            <?php echo $this->form->renderField('phone_1'); ?>
                            <?php echo $this->form->renderField('phone_2'); ?>
                            <?php echo $this->form->renderField('tax_number'); ?>

                            <?php
                            // Render any additional (custom) address fields after tax_number.
                            foreach ($this->addressCustomFields as $field) {
                                if (\in_array($field->field_namekey, $coreFields, true)) {
                                    continue;
                                }

                                if ($this->form->getField($field->field_namekey)) {
                                    echo $this->form->renderField($field->field_namekey);
                                }
                            }
                            ?>
                        </div>
                    </fieldset>
                </div>
            </div>
            <?php echo HTMLHelper::_('uitab.endTab'); ?>

            <?php echo HTMLHelper::_('uitab.endTabSet'); ?>
        <?php endif; ?>
    </div>

    <input type="hidden" name="task" value="">
    <?php echo $this->form->renderField('j2commerce_address_id'); ?>
    <?php echo HTMLHelper::_('form.token'); ?>
</form>

<?php if ($this->useCardMode) : ?>
<div class="modal fade" id="j2commerce-address-modal" tabindex="-1" aria-labelledby="j2commerce-address-modal-label" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="j2commerce-address-modal-label">
                    <?php echo Text::_('COM_J2COMMERCE_CUSTOMER_ADDRESSES_ADD'); ?>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?php echo Text::_('JCANCEL'); ?>"></button>
            </div>
            <div class="modal-body p-3">
                <div class="j2commerce-address-modal-placeholder text-center py-5">
                    <span class="spinner-border" role="status" aria-hidden="true"></span>
                    <p class="mt-2 mb-0"><?php echo Text::_('COM_J2COMMERCE_LOADING'); ?></p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <?php echo Text::_('JCANCEL'); ?>
                </button>
                <button type="button" class="btn btn-primary j2commerce-address-save">
                    <?php echo Text::_('JSAVE'); ?>
                </button>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>
