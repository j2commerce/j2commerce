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
<?php if ($this->params->get('item_show_product_manufacturer_name', 1) && !empty($this->product->manufacturer)) : ?>
    <div class="manufacturer-brand">
        <?php echo Text::_('COM_J2COMMERCE_PRODUCT_MANUFACTURER_NAME'); ?>:
        <?php if (isset($this->product->brand_desc_id) && !empty($this->product->brand_desc_id)) : ?>
            <?php $url = J2CommerceHelper::article()->getArticleLink($this->product->brand_desc_id); ?>
            <a href="<?php echo $url; ?>" target="_blank"><?php echo $this->escape($this->product->manufacturer); ?></a>
        <?php else : ?>
            <?php echo $this->escape($this->product->manufacturer); ?>
        <?php endif; ?>
    </div>
<?php endif; ?>
