<?php
/**
 * @package     J2Commerce
 * @subpackage  Plugin.J2Commerce.AppGuidedbuilder
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use J2Commerce\Component\J2commerce\Administrator\Helper\CurrencyHelper;
use J2Commerce\Component\J2commerce\Administrator\Helper\J2CommerceHelper;
use Joomla\CMS\Language\Text;

$basePrice   = (float) ($this->basePrice ?? 0);
$breakdown   = $this->breakdown ?? [];
$total       = (float) ($this->total ?? 0);
$steps       = $this->steps ?? [];
$selections  = $this->selections ?? [];

echo J2CommerceHelper::plugin()->eventWithHtml('BeforeRenderingProductPrice', [$this->product, $this->context]);
?>
<div class="gb-review-price-card">
    <div class="gb-review-price-header">
        <span uk-icon="icon: receipt"></span>
        <?php echo Text::_('PLG_J2COMMERCE_APP_GUIDEDBUILDER_PRICE_BREAKDOWN'); ?>
    </div>

    <ul class="gb-price-breakdown-list">
        <li class="gb-price-line-item">
            <span class="gb-price-line-label"><?php echo Text::_('PLG_J2COMMERCE_APP_GUIDEDBUILDER_BASE_PRICE'); ?></span>
            <span class="gb-price-line-amount"><?php echo CurrencyHelper::format($basePrice); ?></span>
        </li>

        <?php foreach ($breakdown as $item): ?>
        <?php
        $modifier = (float) ($item['modifier'] ?? 0);
        $prefix   = $item['prefix'] ?? '+';
        $label    = htmlspecialchars($item['label'] ?? '', ENT_QUOTES, 'UTF-8');
        $sign     = $modifier >= 0 ? '+' : '-';
        $absPrice = CurrencyHelper::format(abs($modifier));
        $addonClass = $modifier === 0 ? ' gb-price-addon' : '';
        ?>
        <li class="gb-price-line-item">
            <span class="gb-price-line-label"><?php echo $label; ?></span>
            <span class="gb-price-line-amount<?php echo $addonClass; ?>"><?php echo $sign . $absPrice; ?></span>
        </li>
        <?php endforeach; ?>
    </ul>

    <hr class="gb-price-divider">

    <div class="gb-price-subtotals">
        <div class="gb-price-line-item">
            <span class="gb-price-line-label uk-text-bold"><?php echo Text::_('PLG_J2COMMERCE_APP_GUIDEDBUILDER_SUBTOTAL'); ?></span>
            <span class="gb-price-line-amount"><?php echo CurrencyHelper::format($total); ?></span>
        </div>
        <div class="gb-price-line-item">
            <span class="gb-price-line-label"><?php echo Text::_('PLG_J2COMMERCE_APP_GUIDEDBUILDER_SHIPPING'); ?></span>
            <span class="gb-price-line-muted"><?php echo Text::_('PLG_J2COMMERCE_APP_GUIDEDBUILDER_CALCULATED_AT_CHECKOUT'); ?></span>
        </div>
        <div class="gb-price-line-item">
            <span class="gb-price-line-label"><?php echo Text::_('PLG_J2COMMERCE_APP_GUIDEDBUILDER_TAX'); ?></span>
            <span class="gb-price-line-muted"><?php echo Text::_('PLG_J2COMMERCE_APP_GUIDEDBUILDER_CALCULATED_AT_CHECKOUT'); ?></span>
        </div>
    </div>

    <hr class="gb-price-divider">

    <div class="gb-price-total-row">
        <span class="gb-price-total-label"><?php echo Text::_('PLG_J2COMMERCE_APP_GUIDEDBUILDER_ESTIMATED_TOTAL'); ?></span>
        <span class="gb-price-total-amount"><?php echo CurrencyHelper::format($total); ?></span>
    </div>
</div>

<?php echo J2CommerceHelper::plugin()->eventWithHtml('AfterRenderingProductPrice', [$this->product, $this->context]); ?>