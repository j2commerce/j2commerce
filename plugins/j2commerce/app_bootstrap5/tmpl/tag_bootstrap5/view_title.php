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

/** @var \J2Commerce\Component\J2commerce\Site\View\Product\HtmlView $this */

?>
<?php echo J2CommerceHelper::plugin()->eventWithHtml('BeforeProductTitle', [$this->product, J2CommerceHelper::utilities()->getContext('view_title')])->getArgument('html', ''); ?>
<?php if ($this->params->get('item_show_title', 1)) : ?>
    <h<?php echo $this->params->get('item_title_headertag', '2'); ?> class="product-title">
        <?php echo $this->escape($this->product->product_name); ?>
    </h<?php echo $this->params->get('item_title_headertag', '2'); ?>>
<?php endif; ?>
