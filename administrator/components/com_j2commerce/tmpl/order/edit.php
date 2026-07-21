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

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;

/** @var \J2Commerce\Component\J2commerce\Administrator\View\Order\HtmlView $this */

$wa = $this->getDocument()->getWebAssetManager();
$wa->useScript('keepalive')
    ->useScript('form.validate');

$item     = $this->item;
$orderRef = $this->isNew ? Text::_('COM_J2COMMERCE_NEW_ORDER') : '#' . ($item->order_id ?? '');

$navFooter = static function (bool $showBack, bool $showNext): string {
    $html = '<div class="j2c-wizard-foot d-flex align-items-center justify-content-between p-3 border-top">';
    $html .= $showBack
        ? '<button type="button" class="btn btn-outline-secondary" data-j2c-nav="prev"><span class="fa-solid fa-chevron-left me-1" aria-hidden="true"></span> ' . Text::_('JPREVIOUS') . '</button>'
        : '<span></span>';
    $html .= $showNext
        ? '<button type="button" class="btn btn-primary" data-j2c-nav="next">' . Text::_('JNEXT') . ' <span class="fa-solid fa-chevron-right ms-1" aria-hidden="true"></span></button>'
        : '<span></span>';

    return $html . '</div>';
};

$steps = [
    ['key' => 'basic',            'icon' => 'fa-file-invoice',        'label' => Text::_('COM_J2COMMERCE_TAB_BASIC'),             'prev' => false, 'next' => true],
    ['key' => 'billing',          'icon' => 'fa-file-invoice-dollar', 'label' => Text::_('COM_J2COMMERCE_TAB_BILLING_ADDRESS'),  'prev' => true,  'next' => true],
    ['key' => 'shipping',         'icon' => 'fa-location-dot',        'label' => Text::_('COM_J2COMMERCE_TAB_SHIPPING_ADDRESS'), 'prev' => true,  'next' => true],
    ['key' => 'items',            'icon' => 'fa-cart-shopping',       'label' => Text::_('COM_J2COMMERCE_TAB_ITEMS'),             'prev' => true,  'next' => true],
    ['key' => 'payment_shipping', 'icon' => 'fa-truck-fast',          'label' => Text::_('COM_J2COMMERCE_TAB_PAYMENT_SHIPPING'), 'prev' => true,  'next' => true],
    ['key' => 'summary',          'icon' => 'fa-receipt',             'label' => Text::_('COM_J2COMMERCE_TAB_ORDER_SUMMARY'),    'prev' => true,  'next' => false],
];
$stepCount = \count($steps);

?>
<form action="<?php echo Route::_('index.php?option=com_j2commerce&layout=edit&id=' . (int) $item->j2commerce_order_id); ?>"
      method="post" name="adminForm" id="adminForm" class="form-validate j2c-order-wizard"
      data-order-id="<?php echo (int) $item->j2commerce_order_id; ?>"
      data-token="<?php echo $this->escape(Session::getFormToken()); ?>"
      data-currency="<?php echo $this->escape($item->currency_code ?? ''); ?>">

    <?php echo HTMLHelper::_('uitab.startTabSet', 'orderTab', ['active' => 'basic', 'recall' => true, 'breakpoint' => 768]); ?>

    <?php foreach ($steps as $n => $step) : ?>
        <?php echo HTMLHelper::_('uitab.addTab', 'orderTab', $step['key'], $step['label']); ?>
            <div class="card j2c-wizard-card border shadow-sm overflow-hidden mb-3">
                <div class="j2c-wizard-head d-flex align-items-center gap-3 p-3 border-bottom bg-white">
                    <span class="j2c-icon-tile bg-primary-subtle text-primary">
                        <span class="fa-solid <?php echo $this->escape($step['icon']); ?>" aria-hidden="true"></span>
                    </span>
                    <div>
                        <div class="j2c-wizard-title fw-bold"><?php echo $this->escape($orderRef); ?> &middot; <?php echo $this->escape($step['label']); ?></div>
                        <div class="j2c-wizard-sub text-body-secondary"><?php echo Text::sprintf('COM_J2COMMERCE_WIZARD_STEP_X_OF_Y', $n + 1, $stepCount); ?></div>
                    </div>
                </div>
                <div class="j2c-wizard-body">
                    <?php echo $this->loadTemplate($step['key']); ?>
                </div>
                <?php echo $navFooter($step['prev'], $step['next']); ?>
            </div>
        <?php echo HTMLHelper::_('uitab.endTab'); ?>
    <?php endforeach; ?>

    <?php echo HTMLHelper::_('uitab.endTabSet'); ?>

    <input type="hidden" name="task" value="">
    <input type="hidden" name="boxchecked" value="0">
    <input type="hidden" name="id" value="<?php echo (int) $item->j2commerce_order_id; ?>">
    <?php echo HTMLHelper::_('form.token'); ?>
</form>

<?php if (!$this->isNew && (int) ($item->user_id ?? 0) > 0) : ?>
<?php // Outside #adminForm: the fetched fragment contains its own <form> (nested forms are invalid). ?>
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
                <div class="text-center py-5">
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
