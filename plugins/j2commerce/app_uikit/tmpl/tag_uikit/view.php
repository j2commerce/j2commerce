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
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\Registry\Registry;

/** @var \J2Commerce\Component\J2commerce\Site\View\Product\HtmlView $this */

$app = Factory::getApplication();
$wa = $app->getDocument()->getWebAssetManager();
$productParams = $this->product->params ?? null;
$productCssClass = ($productParams instanceof Registry) ? $productParams->get('product_css_class', '') : '';

$viewParams = $this->params ?? null;
$showPageHeading = ($viewParams instanceof Registry) ? (bool) $viewParams->get('item_show_page_heading', false) : false;
$pageHeading = ($viewParams instanceof Registry) ? $viewParams->get('page_heading', '') : '';
$showBackTo = ($viewParams instanceof Registry) ? (bool) $viewParams->get('item_show_back_to', 0) : false;

$isQuickview = $app->getInput()->getCmd('tmpl', '') === 'component';
?>
<div class="j2commerce j2commerce-single-product <?php echo $this->escape($this->product->product_type ?? ''); ?> detail uikit uk-padding-small <?php echo $this->escape($productCssClass); ?>">
    <?php if ($showPageHeading) : ?>
        <div class="page-header">
            <h1><?php echo $this->escape($pageHeading); ?></h1>
        </div>
    <?php endif; ?>

    <?php echo J2CommerceHelper::modules()->loadPosition('j2commerce-single-product-top'); ?>

    <?php if ($showBackTo && isset($this->back_link) && !empty($this->back_link)) : ?>
        <div class="j2commerce-view-back-button uk-margin-small-bottom">
            <a href="<?php echo $this->escape($this->back_link); ?>" class="j2commerce-product-back-btn uk-button uk-button-small uk-button-default">
                <span uk-icon="icon: arrow-left"></span> <?php echo Text::_('J2STORE_PRODUCT_BACK_TO') . ' ' . $this->escape($this->back_link_title ?? ''); ?>
            </a>
        </div>
    <?php endif; ?>

    <?php echo $this->loadTemplate($this->product->product_type ?? 'simple'); ?>

    <?php echo J2CommerceHelper::plugin()->eventWithHtml('AfterProductDisplay', [$this->product, $this])->getArgument('html', ''); ?>

    <?php echo J2CommerceHelper::modules()->loadPosition('j2commerce-single-product-bottom'); ?>
</div>
