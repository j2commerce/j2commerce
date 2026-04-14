<?php
/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use J2Commerce\Component\J2commerce\Administrator\Helper\J2CommerceHelper;
use Joomla\CMS\Language\Text;


Text::script('COM_J2COMMERCE_INSTOCK');
Text::script('COM_J2COMMERCE_NOTINSTOCK');
Text::script('COM_J2COMMERCE_AVAILABLE');
?>
<div class="j2commerce j2commerce-single-product product-<?php echo $this->item->j2commerce_product_id; ?> <?php echo $this->item->product_type; ?> detail <?php echo $this->params->get('product_css_class', ''); ?>">
    <div class="container">
        <?php if ($this->params->get('show_page_heading')) : ?>
            <div class="page-header">
                <h1><?php echo $this->params->get('page_heading', ''); ?></h1>
            </div>
        <?php endif; ?>

        <?php echo J2CommerceHelper::modules()->loadposition('j2commerce-single-product-top'); ?>

        <?php if($this->params->get('item_show_back_to',0) && isset($this->back_link) && !empty($this->back_link)):?>
            <div class="j2commerce-view-back-button">
                <a href="<?php echo $this->back_link; ?>" class="j2commerce-product-back-btn btn btn-small btn-info">
                    <span class="fa fa-chevron-left" aria-hidden="true"></span> <?php echo Text::_('COM_J2COMMERCE_PRODUCT_BACK_TO').' '.$this->back_link_title; ?>
                </a>
            </div>
        <?php endif;?>

        <?php if (isset($this->sublayout) && !empty($this->sublayout)) : ?>
            <?php echo $this->loadTemplate($this->sublayout); ?>
        <?php else : ?>
            <?php echo $this->loadTemplate($this->item->product_type); ?>
        <?php endif; ?>
    </div>
    <?php if ($this->params->get('item_show_product_upsells', 0) && isset($this->up_sells) && count($this->up_sells) > 0) : ?>
        <?php echo $this->loadTemplate('upsells'); ?>
    <?php endif;?>

    <?php if ($this->params->get('item_show_product_cross_sells', 0) && isset($this->cross_sells) && count($this->cross_sells) > 0) : ?>
        <?php echo $this->loadTemplate('crosssells'); ?>
    <?php endif;?>

    <?php echo J2CommerceHelper::plugin()->eventWithHtml('AfterProductDisplay', [&$result, &$this, &$this->item])->getArgument('html'); ?>

    <?php echo J2CommerceHelper::modules()->loadposition('j2commerce-single-product-bottom'); ?>
</div>

