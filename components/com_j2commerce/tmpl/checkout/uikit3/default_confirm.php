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

use J2Commerce\Component\J2commerce\Administrator\Helper\J2CommerceHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

/** @var \J2Commerce\Component\J2commerce\Site\View\Checkout\HtmlView $this */

$errors = $this->errors ?? [];
$showPayment = $this->showPayment ?? true;
$showTerms = $this->showTerms ?? 0;
$termsDisplayType = $this->termsDisplayType ?? 'link';
$pluginHtml = $this->plugin_html ?? '';
$freeRedirect = $this->free_redirect ?? '';
?>
<div class="j2commerce-checkout-confirm">

<?php if (empty($errors)) : ?>
    <div class="mb-3">
        <label for="customer_note" class="form-label"><?php echo Text::_('COM_J2COMMERCE_CHECKOUT_CUSTOMER_NOTE'); ?></label>
        <textarea name="customer_note" id="customer_note" class="form-control" rows="3"></textarea>
    </div>

    <?php echo J2CommerceHelper::plugin()->eventWithHtml('BeforeCheckoutConfirm', [$this]); ?>

    <?php if (!empty($pluginHtml)) : ?>
        <h5><?php echo Text::_('COM_J2COMMERCE_PAYMENT_METHOD'); ?></h5>
        <div class="payment mb-3">
            <?php echo $pluginHtml; ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($freeRedirect)) : ?>
        <form action="<?php echo Route::_('index.php?option=com_j2commerce&task=checkout.confirmPayment'); ?>" method="post">
            <button type="submit" class="btn btn-primary btn-lg">
                <?php echo Text::_('COM_J2COMMERCE_PLACE_ORDER'); ?>
            </button>
            <input type="hidden" name="option" value="com_j2commerce">
            <input type="hidden" name="task" value="checkout.confirmPayment">
            <input type="hidden" name="customer_note" value="" class="j2commerce-customer-note-sync">
        </form>
    <?php endif; ?>

    <?php if ($showTerms && $termsDisplayType === 'checkbox') : ?>
        <div class="form-check mb-3">
            <input class="form-check-input" type="checkbox" name="tos_check" value="1" id="tos_check">
            <label class="form-check-label" for="tos_check">
                <?php echo Text::_('COM_J2COMMERCE_CHECKOUT_AGREE_TERMS'); ?>
            </label>
        </div>
    <?php endif; ?>
<?php else : ?>
    <div class="alert alert-danger">
        <?php echo implode('<br>', array_map('htmlspecialchars', $errors)); ?>
    </div>
<?php endif; ?>

<?php echo J2CommerceHelper::plugin()->eventWithHtml('AfterCheckoutConfirm', [$this]); ?>

</div>
