<?php
/**
 * @package     J2Commerce
 * @subpackage  Plugin.J2Commerce.PaymentMoneyorder
 *
 * @copyright   Copyright (C) 2024-2026 J2Commerce, LLC. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use J2Commerce\Component\J2commerce\Administrator\Helper\J2htmlHelper;

/** @var array $displayData */
$vars = $displayData['vars'];
?>

<?php if (!empty($vars->onselection_text)): ?>
    <div class="j2commerce-payment-selection-text">
        <?php echo J2htmlHelper::merchantText($vars->onselection_text); ?>
    </div>
<?php endif; ?>
