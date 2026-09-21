<?php

/**
 * @package     J2Commerce
 * @subpackage  plg_j2commerce_payment_banktransfer
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

\defined('_JEXEC') or die;

use J2Commerce\Component\J2commerce\Administrator\Helper\J2htmlHelper;

extract((array) $displayData);
?>

<?php echo J2htmlHelper::merchantText($vars->message); ?>