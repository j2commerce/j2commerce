<?php
/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 *
 * Edit-mode companion template for list/category/item_stock.php
 * Outputs tokenized HTML for the Visual Builder sub-layout editor.
 */

declare(strict_types=1);

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;

extract($displayData);

$stockText  = $stockStatus ?? Text::_('COM_J2COMMERCE_IN_STOCK');
$stockClass = ($stockStatus === 'Out of Stock') ? 'out-of-stock' : 'in-stock';
?>
<j2c-conditional data-condition="$showStock">
    <div class="j2commerce-product-stock small p-2 text-center <?php echo $stockClass; ?>">
        <j2c-token data-token="STOCK_STATUS"><?php echo htmlspecialchars($stockText, ENT_QUOTES, 'UTF-8'); ?></j2c-token>
    </div>
</j2c-conditional>
