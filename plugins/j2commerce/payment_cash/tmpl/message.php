<?php

/**
 * @package     J2Commerce
 * @subpackage  plg_j2commerce_payment_cash
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

\defined('_JEXEC') or die;

use J2Commerce\Component\J2commerce\Administrator\Helper\J2htmlHelper;

/** @var array $displayData */
$vars = $displayData['vars'];
?>

<?php if (!empty($vars->message)): ?>
    <div class="alert alert-warning j2commerce-payment-message">
        <?php echo J2htmlHelper::merchantText($vars->message); ?>
    </div>
<?php endif; ?>
