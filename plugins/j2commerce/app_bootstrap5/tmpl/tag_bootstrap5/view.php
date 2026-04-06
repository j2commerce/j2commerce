<?php
/**
 * @package     J2Commerce
 * @subpackage  plg_j2commerce_app_bootstrap5
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 *
 * Bootstrap 5 layout of product detail
 */

declare(strict_types=1);

defined('_JEXEC') or die;

use J2Commerce\Component\J2commerce\Administrator\Helper\J2CommerceHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\Registry\Registry;

/** @var \J2Commerce\Component\J2commerce\Site\View\Product\HtmlView $this */

$app = Factory::getApplication();
$wa = $app->getDocument()->getWebAssetManager();
// Safe access to product params
$productParams = $this->product->params ?? null;
$productCssClass = ($productParams instanceof Registry) ? $productParams->get('product_css_class', '') : '';

// Safe access to view params
$viewParams = $this->params ?? null;
$showPageHeading = ($viewParams instanceof Registry) ? (bool) $viewParams->get('item_show_page_heading', false) : false;
$pageHeading = ($viewParams instanceof Registry) ? $viewParams->get('page_heading', '') : '';
$showBackTo = ($viewParams instanceof Registry) ? (bool) $viewParams->get('item_show_back_to', 0) : false;

// Detect quickview context (tmpl=component inside an iframe)
$isQuickview = $app->getInput()->getCmd('tmpl', '') === 'component';
?>
<div class="j2commerce j2commerce-single-product <?php echo $this->escape($this->product->product_type ?? ''); ?> detail bootstrap5 py-4 <?php echo $this->escape($productCssClass); ?>">
    <?php if ($showPageHeading) : ?>
        <div class="page-header">
            <h1><?php echo $this->escape($pageHeading); ?></h1>
        </div>
    <?php endif; ?>

    <?php echo J2CommerceHelper::modules()->loadPosition('j2commerce-single-product-top'); ?>

    <?php if ($showBackTo && isset($this->back_link) && !empty($this->back_link)) : ?>
        <div class="j2commerce-view-back-button mb-3">
            <a href="<?php echo $this->escape($this->back_link); ?>" class="j2commerce-product-back-btn btn btn-sm btn-info">
                <i class="fa-solid fa-chevron-left"></i> <?php echo Text::_('COM_J2COMMERCE_PRODUCT_BACK_TO') . ' ' . $this->escape($this->back_link_title ?? ''); ?>
            </a>
        </div>
    <?php endif; ?>

    <?php echo $this->loadTemplate($this->product->product_type ?? 'simple'); ?>

    <?php echo J2CommerceHelper::plugin()->eventWithHtml('AfterProductDisplay', [$this->product, $this])->getArgument('html', ''); ?>

    <?php echo J2CommerceHelper::modules()->loadPosition('j2commerce-single-product-bottom'); ?>
</div>
