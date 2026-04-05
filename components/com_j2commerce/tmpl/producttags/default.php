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

/** @var \J2Commerce\Component\J2commerce\Site\View\Producttags\HtmlView $this */
?>
<div class="j2commerce j2commerce-products j2commerce-product-list j2commerce-producttags <?php echo $this->escape($this->params->get('pageclass_sfx', '')); ?>">
    <div class="container">
        <?php if ($this->params->get('show_page_heading')) : ?>
            <div class="page-header">
                <h1><?php echo $this->escape($this->params->get('page_heading', '')); ?></h1>
            </div>
        <?php endif; ?>

        <?php echo J2CommerceHelper::modules()->loadposition('j2commerce-products-top'); ?>

        <?php if (isset($this->sublayout) && !empty($this->sublayout)) : ?>
            <?php echo $this->loadTemplate($this->sublayout); ?>
        <?php else : ?>
            <?php if (empty($this->items)) : ?>
                <div class="alert alert-info">
                    <?php echo Text::_('COM_J2COMMERCE_NO_PRODUCTS_FOUND'); ?>
                </div>
            <?php else : ?>
                <div class="row g-4">
                    <?php foreach ($this->items as $product) :
                        $this->product = $product;
                    ?>
                        <div class="<?php echo $this->getColumnClass(); ?>">
                            <?php echo $this->loadTemplate('item'); ?>
                        </div>
                    <?php endforeach; ?>
                </div>

                <?php if ($this->pagination->pagesTotal > 1) : ?>
                    <div class="j2commerce-pagination mt-4">
                        <?php echo $this->pagination->getPagesLinks(); ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        <?php endif; ?>

        <?php echo J2CommerceHelper::plugin()->eventWithHtml('AfterProductListDisplay', [&$result, &$this, &$this->items])->getArgument('html'); ?>

        <?php echo J2CommerceHelper::modules()->loadposition('j2commerce-products-bottom'); ?>
    </div>
</div>
