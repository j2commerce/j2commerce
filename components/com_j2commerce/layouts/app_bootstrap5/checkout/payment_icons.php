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

use Joomla\CMS\Language\Text;

$element   = $displayData['element'] ?? '';
$cardTypes = $displayData['card_types'] ?? [];

if (empty($cardTypes)) {
    return;
}
?>
<div id="<?php echo htmlspecialchars($element); ?>_payment_icons"
     class="checkout-payment-icons d-flex align-items-center justify-content-end gap-1 gap-sm-2">
    <?php foreach ($cardTypes as $icon) : ?>
        <div class="payment-icon payment-icon-<?php echo htmlspecialchars($icon['type']); ?>">
            <img src="<?php echo htmlspecialchars($icon['url']); ?>"
                 alt="<?php echo htmlspecialchars($icon['type'] . ' ' . Text::_('COM_J2COMMERCE_PAYMENT_OPTION')); ?>"
                 class="payment-plugin-image">
        </div>
    <?php endforeach; ?>
</div>
