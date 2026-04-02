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

use J2Commerce\Component\J2commerce\Administrator\Helper\CurrencyHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;

// Self-register assets (once per request, no matter how many instances)
static $assetsRegistered = false;
if (!$assetsRegistered) {
    $doc = Factory::getApplication()->getDocument();
    $wa  = $doc->getWebAssetManager();
    $wa->registerAndUseScript('com_j2commerce.coupon-voucher', 'media/com_j2commerce/js/site/coupon-voucher.js', [], ['defer' => true], ['core']);
    $wa->registerAndUseStyle('com_j2commerce.coupon-voucher.css', 'media/com_j2commerce/css/site/coupon-voucher.css');
    $doc->addScriptOptions('j2commerce.couponVoucher', [
        'baseUrl'   => Route::_('index.php?option=com_j2commerce', false),
        'csrfToken' => Session::getFormToken(),
        'strings'   => [
            'enterCoupon'  => Text::_('COM_J2COMMERCE_ENTER_COUPON_CODE'),
            'applyCoupon'  => Text::_('COM_J2COMMERCE_APPLY_COUPON'),
            'couponCode'   => Text::_('COM_J2COMMERCE_COUPON_CODE'),
            'removeCoupon' => Text::_('COM_J2COMMERCE_REMOVE_COUPON'),
            'enterVoucher' => Text::_('COM_J2COMMERCE_ENTER_VOUCHER_CODE'),
            'applyVoucher' => Text::_('COM_J2COMMERCE_APPLY_VOUCHER'),
            'voucherCode'  => Text::_('COM_J2COMMERCE_VOUCHER_CODE'),
            'removeVoucher'=> Text::_('COM_J2COMMERCE_REMOVE_VOUCHER'),
            'remove'       => Text::_('COM_J2COMMERCE_REMOVE'),
        ],
    ]);
    Text::script('COM_J2COMMERCE_LOADING');
    $assetsRegistered = true;
}

$couponCode    = $displayData['couponCode'] ?? '';
$formId        = $displayData['formId'] ?? 'j2c-coupon';
$variant       = $displayData['variant'] ?? 'inline';
$accordionId   = $displayData['accordionId'] ?? '';
$expanded      = !empty($displayData['expanded']);
$showDiscount  = !empty($displayData['showDiscount']);
$hasCoupon     = !empty($couponCode);

$discountLabel = '';
if ($hasCoupon && $showDiscount) {
    static $couponCache = [];

    if (!isset($couponCache[$couponCode])) {
        $couponModel = Factory::getApplication()->bootComponent('com_j2commerce')
            ->getMVCFactory()->createModel('Coupon', 'Administrator', ['ignore_request' => true]);
        $couponCache[$couponCode] = $couponModel?->getCouponByCode($couponCode);
    }

    $couponRecord = $couponCache[$couponCode];

    if ($couponRecord) {
        $isPercentage = str_contains($couponRecord->value_type, 'percentage');
        $discountLabel = $isPercentage
            ? Text::sprintf('COM_J2COMMERCE_COUPON_DISCOUNT_PERCENTAGE', rtrim(rtrim(number_format((float) $couponRecord->value, 2), '0'), '.'))
            : Text::sprintf('COM_J2COMMERCE_COUPON_DISCOUNT_FIXED', CurrencyHelper::format((float) $couponRecord->value));
    }
}

?>
<?php if ($variant === 'accordion') : ?>
<div class="accordion-item">
    <h2 class="accordion-header">
        <button class="accordion-button <?php echo $expanded ? '' : 'collapsed'; ?>"
                type="button"
                data-bs-toggle="collapse"
                data-bs-target="#<?php echo $formId; ?>-collapse"
                aria-expanded="<?php echo $expanded ? 'true' : 'false'; ?>"
                aria-controls="<?php echo $formId; ?>-collapse">
            <?php echo Text::_('COM_J2COMMERCE_COUPON_CODE'); ?>
            <?php if ($hasCoupon) : ?>
                <span class="badge bg-success ms-2 j2c-coupon-badge"><?php echo htmlspecialchars($couponCode, ENT_QUOTES, 'UTF-8'); ?></span>
                <?php if ($discountLabel) : ?>
                    <small class="text-muted ms-1"><?php echo htmlspecialchars($discountLabel, ENT_QUOTES, 'UTF-8'); ?></small>
                <?php endif; ?>
            <?php endif; ?>
        </button>
    </h2>
    <div id="<?php echo $formId; ?>-collapse"
         class="accordion-collapse collapse <?php echo $expanded ? 'show' : ''; ?>"
         <?php if ($accordionId) : ?>data-bs-parent="#<?php echo $accordionId; ?>"<?php endif; ?>>
        <div class="accordion-body">
<?php endif; ?>

<div class="j2c-coupon-form" id="<?php echo $formId; ?>" data-type="coupon">
    <?php if ($hasCoupon) : ?>
        <div class="d-flex align-items-center justify-content-between py-1">
            <span>
                <span class="badge bg-success">
                    <span class="icon-tag me-1" aria-hidden="true"></span><?php echo htmlspecialchars($couponCode, ENT_QUOTES, 'UTF-8'); ?>
                </span>
                <?php if ($discountLabel) : ?>
                    <small class="text-muted ms-1"><?php echo htmlspecialchars($discountLabel, ENT_QUOTES, 'UTF-8'); ?></small>
                <?php endif; ?>
            </span>
            <button type="button" class="btn btn-sm btn-link text-danger p-0 j2c-remove-coupon"
                    title="<?php echo Text::_('COM_J2COMMERCE_REMOVE_COUPON'); ?>">
                <span class="icon-times" aria-hidden="true"></span>
                <?php echo Text::_('COM_J2COMMERCE_REMOVE'); ?>
            </button>
        </div>
    <?php else : ?>
        <div class="j2c-coupon-input-wrap">
            <div class="input-group">
                <div class="input-group_inner">
                    <input type="text" name="coupon" class="form-control" placeholder="<?php echo Text::_('COM_J2COMMERCE_ENTER_COUPON_CODE'); ?>" aria-label="<?php echo Text::_('COM_J2COMMERCE_COUPON_CODE'); ?>" />
                </div>
                <button type="button" class="btn btn-outline-secondary j2c-apply-coupon">
                    <?php echo Text::_('COM_J2COMMERCE_APPLY_COUPON'); ?>
                </button>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php if ($variant === 'accordion') : ?>
        </div>
    </div>
</div>
<?php endif; ?>
