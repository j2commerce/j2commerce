<?php
/**
 * @package     J2Commerce
 * @subpackage  plg_j2commerce_app_boxbuilderproduct
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use J2Commerce\Component\J2commerce\Administrator\Helper\J2CommerceHelper;

$images = $this->loadTemplate('images');
J2CommerceHelper::plugin()->event('BeforeDisplayImages', [&$images, $this, 'com_j2commerce.products.list.uikit']);
echo $images;
?>
<?php echo $this->loadTemplate('title'); ?>
<?php if (isset($this->product->event->afterDisplayTitle)): ?>
    <?php echo $this->product->event->afterDisplayTitle; ?>
<?php endif; ?>

<?php if (isset($this->product->event->beforeDisplayContent)): ?>
    <?php echo $this->product->event->beforeDisplayContent; ?>
<?php endif; ?>

<?php echo $this->loadTemplate('description'); ?>

<?php echo $this->loadTemplate('price'); ?>

<?php if ($this->params->get('list_show_product_sku', 1)): ?>
    <?php echo $this->loadTemplate('sku'); ?>
<?php endif; ?>

<?php if ($this->params->get('list_show_product_stock', 1) && J2CommerceHelper::product()->managing_stock($this->product->variant)): ?>
    <?php echo $this->loadTemplate('stock'); ?>
<?php endif; ?>

<?php if ($this->params->get('catalog_mode', 0) == 0): ?>
    <form action="<?php echo $this->product->cart_form_action; ?>"
          method="post"
          class="j2commerce-addtocart-form"
          id="j2commerce-addtocart-form-<?php echo $this->product->j2commerce_product_id; ?>"
          name="j2commerce-addtocart-form-<?php echo $this->product->j2commerce_product_id; ?>"
          data-product_id="<?php echo $this->product->j2commerce_product_id; ?>"
          data-product_type="<?php echo $this->product->product_type; ?>"
          enctype="multipart/form-data">

        <a href="<?php echo $this->product->product_link; ?>"
           class="<?php echo $this->params->get('choosebtn_class', 'uk-button uk-button-primary'); ?>">
            <?php echo Text::_('COM_J2COMMERCE_VIEW_PRODUCT_DETAILS'); ?>
        </a>

    </form>
<?php endif; ?>

<?php if (isset($this->product->event->afterDisplayContent)): ?>
    <?php echo $this->product->event->afterDisplayContent; ?>
<?php endif; ?>
