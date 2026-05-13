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
?>
<div class="j2commerce-checkout-confirm">

<?php if (empty($errors)) : ?>
    <div class="uk-margin-bottom">
        <label for="customer_note" class="uk-form-label"><?php echo Text::_('COM_J2COMMERCE_CHECKOUT_CUSTOMER_NOTE'); ?></label>
        <textarea name="customer_note" id="customer_note" class="uk-textarea" rows="3"></textarea>
    </div>

    <?php echo J2CommerceHelper::plugin()->eventWithHtml('BeforeCheckoutConfirm', [$this]); ?>

    <?php if ($showTerms === 1 && $termsDisplayType === 'checkbox') : ?>
        <div class="uk-margin-bottom">
            <label class="uk-flex uk-flex-middle">
                <input class="uk-checkbox uk-margin-small-right" type="checkbox" name="tos_check" value="1" id="tos_check">
                <span>
                    <?php if ($termsUrl !== '') : ?>
                        <?php echo Text::sprintf(
                            'COM_J2COMMERCE_CHECKOUT_AGREE_TERMS_LINK',
                            '<a href="' . htmlspecialchars($termsUrl) . '" target="_blank" rel="noopener">'
                                . htmlspecialchars(Text::_('COM_J2COMMERCE_CHECKOUT_TERMS_AND_CONDITIONS'))
                                . '</a>'
                        ); ?>
                    <?php else : ?>
                        <?php echo Text::_('COM_J2COMMERCE_CHECKOUT_AGREE_TERMS'); ?>
                    <?php endif; ?>
                </span>
            </label>
        </div>
    <?php elseif ($showTerms === 1 && $termsDisplayType === 'link' && $termsUrl !== '') : ?>
        <div class="j2commerce-terms-link uk-margin-bottom">
            <?php echo Text::sprintf(
                'COM_J2COMMERCE_CHECKOUT_AGREE_TERMS_LINK',
                '<a href="' . htmlspecialchars($termsUrl) . '" target="_blank" rel="noopener">'
                    . htmlspecialchars(Text::_('COM_J2COMMERCE_CHECKOUT_TERMS_AND_CONDITIONS'))
                    . '</a>'
            ); ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($pluginHtml)) : ?>
        <h5><?php echo Text::_('COM_J2COMMERCE_PAYMENT_METHOD'); ?></h5>
        <div class="payment uk-margin-bottom">
            <?php echo $pluginHtml; ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($freeRedirect)) : ?>
        <form action="<?php echo Route::_('index.php?option=com_j2commerce&task=checkout.confirmPayment'); ?>" method="post">
            <button type="submit" class="uk-button uk-button-primary uk-button-large">
                <?php echo Text::_('COM_J2COMMERCE_PLACE_ORDER'); ?>
            </button>
            <input type="hidden" name="option" value="com_j2commerce">
            <input type="hidden" name="task" value="checkout.confirmPayment">
            <input type="hidden" name="customer_note" value="" class="j2commerce-customer-note-sync">
            <input type="hidden" name="tos_check" value="" class="j2commerce-tos-sync">
        </form>
    <?php endif; ?>
<?php else : ?>
    <div class="uk-alert uk-alert-danger" uk-alert>
        <?php echo implode('<br>', array_map('htmlspecialchars', $errors)); ?>
    </div>
<?php endif; ?>

<?php echo J2CommerceHelper::plugin()->eventWithHtml('AfterCheckoutConfirm', [$this]); ?>

</div>
