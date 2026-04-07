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
use J2Commerce\Component\J2commerce\Administrator\Helper\J2CommerceHelper;

extract($displayData);

$productHelper = J2CommerceHelper::product();
$chooseBtnClass = $params->get('choosebtn_class', 'btn btn-success');

// Advanced variable products always redirect to product page for option selection in list view
?>
<div class="j2commerce-advancedvariable-options">
    <?php if ($showCart && $productHelper->canShowCart($params)): ?>
        <a href="<?php echo htmlspecialchars($productLink ?? '', ENT_QUOTES, 'UTF-8'); ?>" class="<?php echo $chooseBtnClass; ?>">
            <?php echo Text::_('COM_J2COMMERCE_CART_CHOOSE_OPTIONS'); ?>
        </a>
    <?php endif; ?>
</div>
