<?php
/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2025 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;
?>
<div class="row">
    <div class="col-sm-12">
        <?php
        $hasShortDesc = $this->params->get('item_show_sdesc') && !empty(trim(strip_tags($this->item->product_short_desc ?? '')));
        $hasLongDesc  = $this->params->get('item_show_ldesc') && !empty(trim(strip_tags($this->item->product_long_desc ?? '')));
        ?>
        <?php if ($hasShortDesc || $hasLongDesc) : ?>
            <div class="product-description">
                <?php echo $this->loadTemplate('sdesc'); ?>
                <?php echo $this->loadTemplate('ldesc'); ?>
            </div>
        <?php endif;?>

        <?php if ($this->params->get('item_show_product_specification')) : ?>
            <div class="product-specs">
                <?php echo $this->loadTemplate('specs'); ?>
            </div>
        <?php endif;?>

        <?php $productfilters = $this->item->productfilters ?? []; ?>
        <?php if ($this->params->get('item_show_product_filters') && !empty($productfilters)) : ?>
            <div class="product-filters mt-3">
                <?php foreach ($productfilters as $group) : ?>
                    <div class="mb-3">
                        <h6 class="fw-bold mb-2"><?php echo htmlspecialchars($group['group_name'], ENT_QUOTES, 'UTF-8'); ?></h6>
                        <div class="d-flex flex-wrap gap-2">
                            <?php foreach ($group['filters'] as $filter) : ?>
                                <span class="badge bg-light text-dark border"><?php echo htmlspecialchars($filter->filter_name, ENT_QUOTES, 'UTF-8'); ?></span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif;?>
    </div>
</div>
