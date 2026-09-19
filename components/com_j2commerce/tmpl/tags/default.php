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
use J2Commerce\Component\J2commerce\Site\Service\ProductLayoutService;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;

/** @var \J2Commerce\Component\J2commerce\Site\View\Tags\HtmlView $this */

$params      = $this->params;
$activeMenu  = Factory::getApplication()->getMenu()->getActive();
$itemId      = $activeMenu ? (int) $activeMenu->id : 0;
$displayMode = $this->displayMode;

$renderProducts = function (array $products, string $columnClass) use ($params, $itemId): void {
    echo '<div class="row g-3">';

    foreach ($products as $product) {
        $itemHtml = ProductLayoutService::renderProductItem($product, $params, ProductLayoutService::CONTEXT_LIST . '.tag', $itemId);

        // A product type with no registered layout renders nothing — skip the wrapper too.
        if (trim($itemHtml) !== '') {
            echo '<div class="' . $columnClass . '">' . $itemHtml . '</div>';
        }
    }

    echo '</div>';
};
?>
<div class="j2commerce j2commerce-tags <?php echo $this->escape($params->get('pageclass_sfx', '')); ?>">
    <?php echo J2CommerceHelper::plugin()->eventWithHtml('BeforeTagsView', [$this])->getArgument('html', ''); ?>
    <div class="container">
        <?php if ($params->get('show_page_heading')) : ?>
            <div class="page-header mb-3">
                <h1><?php echo $this->escape($params->get('page_heading')); ?></h1>
            </div>
        <?php endif; ?>

        <?php echo J2CommerceHelper::modules()->loadPosition('j2commerce-tags-top'); ?>

        <?php if ($params->get('show_tag_description', 1) && !empty($this->parent->description)) : ?>
            <div class="tag-desc mb-4">
                <?php echo $this->parent->description; ?>
            </div>
        <?php endif; ?>

        <?php if (empty($this->items) && empty($this->products) && empty($this->trendingProducts)) : ?>
            <div class="alert alert-info">
                <?php echo Text::_('COM_J2COMMERCE_NO_TAGS_FOUND'); ?>
            </div>
        <?php else : ?>
            <?php echo $this->loadTemplate('grid'); ?>

            <?php if ($displayMode === 'products' && !empty($this->products)) : ?>
                <h2><?php echo Text::_('COM_J2COMMERCE_PRODUCTS'); ?></h2>
                <?php $renderProducts($this->products, $this->getProductColumnClass()); ?>
            <?php elseif ($displayMode === 'tags_popular') : ?>
                <?php echo J2CommerceHelper::plugin()->eventWithHtml('BeforeTagsTrendingProducts', [$this])->getArgument('html', ''); ?>
                <?php echo J2CommerceHelper::modules()->loadPosition('j2commerce-tags-middle'); ?>
                <?php if (!empty($this->trendingProducts)) : ?>
                    <h2><?php echo Text::_('COM_J2COMMERCE_TRENDING_PRODUCTS'); ?></h2>
                    <?php $renderProducts($this->trendingProducts, $this->getPopularColumnClass()); ?>
                <?php endif; ?>
                <?php echo J2CommerceHelper::plugin()->eventWithHtml('AfterTagsTrendingProducts', [$this])->getArgument('html', ''); ?>
            <?php endif; ?>
        <?php endif; ?>

        <?php echo J2CommerceHelper::modules()->loadPosition('j2commerce-tags-bottom'); ?>
    </div>
</div>
