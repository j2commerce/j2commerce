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

use J2Commerce\Component\J2commerce\Administrator\Helper\J2CommerceHelper;
use Joomla\CMS\Language\Text;

/** @var \J2Commerce\Component\J2commerce\Site\View\Product\HtmlView $this */

?>
<div class="j2commerce-product-stock-container">
    <?php if (J2CommerceHelper::product()->managing_stock($this->product->variant)) : ?>
        <?php if ($this->product->variant->availability) : ?>
            <?php $stockText = J2CommerceHelper::product()->displayStock($this->product->variant, $this->params); ?>
            <span class="<?php echo $stockText !== '' ? 'in-stock' : 'd-none'; ?>">
                <?php echo $stockText; ?>
            </span>
        <?php else : ?>
            <span class="out-of-stock">
                <?php echo Text::_('COM_J2COMMERCE_OUT_OF_STOCK'); ?>
            </span>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php if ($this->product->variant->allow_backorder == 2 && !$this->product->variant->availability) : ?>
    <span class="backorder-notification">
        <?php echo Text::_('COM_J2COMMERCE_BACKORDER_NOTIFICATION'); ?>
    </span>
<?php endif; ?>
