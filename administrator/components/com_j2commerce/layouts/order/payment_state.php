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

use Joomla\CMS\Language\Text;

// Payment indicator: the badge is rendered hidden when it adds nothing to the order status, and always carries a word plus an icon, never colour alone.
// The word sets its own text-body-emphasis color: Atum's light-mode text-bg-* pairs measure 2.09:1 to 3.96:1, and plain text-body drops the dark pairs below 4.5:1.
// $displayData: indicator (OrderRecordedPaymentHelper::indicator()), id (optional), compactConflict (list rows: a short warning instead of the badge).
$indicator = $displayData['indicator'];
$id        = (string) ($displayData['id'] ?? '');
$show      = (bool) $indicator['show'];
?>
<span class="j2c-payment-state"<?php echo $id !== '' ? ' id="' . htmlspecialchars($id, ENT_QUOTES, 'UTF-8') . '" tabindex="-1"' : ''; ?> data-j2c-payment-state="<?php echo htmlspecialchars($indicator['state'], ENT_QUOTES, 'UTF-8'); ?>">
    <span class="<?php echo htmlspecialchars($indicator['class'], ENT_QUOTES, 'UTF-8'); ?>" data-j2c-payment-badge<?php echo $show ? '' : ' hidden'; ?>>
        <span class="visually-hidden"><?php echo htmlspecialchars(Text::_('COM_J2COMMERCE_PAYMENT_STATE'), ENT_QUOTES, 'UTF-8'); ?>: </span><span class="<?php echo htmlspecialchars($indicator['icon'], ENT_QUOTES, 'UTF-8'); ?> me-1" data-j2c-payment-icon aria-hidden="true"></span><span class="text-body-emphasis" data-j2c-payment-label><?php echo htmlspecialchars($indicator['label'], ENT_QUOTES, 'UTF-8'); ?></span>
    </span>
    <small class="text-body-secondary ms-1" data-j2c-payment-secondary<?php echo $show && $indicator['secondary'] !== '' ? '' : ' hidden'; ?>><?php echo htmlspecialchars($indicator['secondary'], ENT_QUOTES, 'UTF-8'); ?></small>
    <?php if (!empty($displayData['compactConflict']) && $indicator['conflict']) : ?>
        <?php $noPayment = htmlspecialchars(Text::_('COM_J2COMMERCE_PAYMENT_STATE_NO_PAYMENT_RECORDED'), ENT_QUOTES, 'UTF-8'); ?>
        <button type="button" class="clickTooltip btn btn-link p-0 border-0 align-baseline text-warning-emphasis ms-1" data-bs-toggle="tooltip" data-bs-trigger="click" data-bs-placement="top" title="<?php echo $noPayment; ?>" data-j2c-payment-conflict-compact>
            <span class="fa-solid fa-triangle-exclamation" aria-hidden="true"></span><span class="visually-hidden"><?php echo htmlspecialchars(Text::_('WARNING'), ENT_QUOTES, 'UTF-8'); ?>: <?php echo $noPayment; ?></span>
        </button>
    <?php endif; ?>
</span>
