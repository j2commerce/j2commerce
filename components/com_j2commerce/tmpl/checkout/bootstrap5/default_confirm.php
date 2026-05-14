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
use Joomla\Component\Content\Site\Helper\RouteHelper;

/** @var \J2Commerce\Component\J2commerce\Site\View\Checkout\HtmlView $this */

$errors           = $this->errors ?? [];
$showPayment      = $this->showPayment ?? true;
$showTerms        = (int) ($this->showTerms ?? 0);
$termsDisplayType = (string) ($this->termsDisplayType ?? 'link');
$termsArticleId   = (int) ($this->termsArticleId ?? 0);
$pluginHtml       = $this->plugin_html ?? '';
$freeRedirect     = $this->free_redirect ?? '';
$termsUrl         = $showTerms && $termsArticleId
    ? Route::_(RouteHelper::getArticleRoute($termsArticleId))
    : '';
$termsText        = trim((string) ($this->termsText ?? ''));
$showCustomerNote = (bool) ($this->showCustomerNote ?? true);

?>
<div class="j2commerce-checkout-confirm">

<?php if (empty($errors)) : ?>

    <?php echo J2CommerceHelper::plugin()->eventWithHtml('BeforeCheckoutConfirm', [$this]); ?>

    <?php if ($showTerms === 1 && $termsDisplayType === 'checkbox') : ?>
        <div class="j2commerce-terms-box mb-3">
            <div class="form-check">
                <input class="form-check-input" type="checkbox" name="tos_check" value="1" id="tos_check">
                <label class="form-check-label" for="tos_check">
                    <?php if ($termsText !== '') : ?>
                        <?php // Raw HTML — admin-controlled via filter="raw" ?>
                        <?php echo $termsText; ?>
                    <?php elseif ($termsUrl !== '') : ?>
                        <?php echo Text::sprintf(
                            'COM_J2COMMERCE_CHECKOUT_AGREE_TERMS_LINK',
                            '<a href="' . htmlspecialchars($termsUrl) . '" target="_blank" rel="noopener">'
                                . htmlspecialchars(Text::_('COM_J2COMMERCE_CHECKOUT_TERMS_AND_CONDITIONS'))
                                . '</a>'
                        ); ?>
                    <?php else : ?>
                        <?php echo Text::_('COM_J2COMMERCE_CHECKOUT_AGREE_TERMS'); ?>
                    <?php endif; ?>
                </label>
            </div>
        </div>
    <?php elseif ($showTerms === 1 && $termsDisplayType === 'link' && ($termsUrl !== '' || $termsText !== '')) : ?>
        <div class="j2commerce-terms-link mb-3">
            <?php if ($termsText !== '') : ?>
                <?php // Raw HTML — admin-controlled via filter="raw" ?>
                <?php echo $termsText; ?>
            <?php else : ?>
                <?php echo Text::sprintf(
                    'COM_J2COMMERCE_CHECKOUT_AGREE_TERMS_LINK',
                    '<a href="' . htmlspecialchars($termsUrl) . '" target="_blank" rel="noopener">'
                        . htmlspecialchars(Text::_('COM_J2COMMERCE_CHECKOUT_TERMS_AND_CONDITIONS'))
                        . '</a>'
                ); ?>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <?php if ($showCustomerNote) : ?>
        <div class="j2commerce-customer-note mb-3">
            <label for="customer_note" class="form-label"><?php echo Text::_('COM_J2COMMERCE_CHECKOUT_CUSTOMER_NOTE'); ?></label>
            <textarea name="customer_note" id="customer_note" class="form-control" rows="2"></textarea>
        </div>
    <?php endif; ?>

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
            <input type="hidden" name="tos_check" value="" class="j2commerce-tos-sync">
        </form>
    <?php endif; ?>
<?php else : ?>
    <div class="alert alert-danger">
        <?php echo implode('<br>', array_map('htmlspecialchars', $errors)); ?>
    </div>
<?php endif; ?>

<?php echo J2CommerceHelper::plugin()->eventWithHtml('AfterCheckoutConfirm', [$this]); ?>

</div>
