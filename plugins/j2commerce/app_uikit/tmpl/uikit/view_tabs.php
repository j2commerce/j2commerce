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

$productfilters = $this->product->productfilters ?? [];
$hasShortDesc = $this->params->get('item_show_sdesc') && !empty(trim(strip_tags($this->product->product_short_desc ?? '')));
$hasLongDesc  = $this->params->get('item_show_ldesc') && !empty(trim(strip_tags($this->product->product_long_desc ?? '')));
$hasDescription = $hasShortDesc || $hasLongDesc;
$set_specification_active = !$hasDescription;
?>

<div class="j2commerce-product-tabs uk-margin-top">
    <ul uk-tab class="uk-flex-center">
        <?php if ($hasDescription) : ?>
            <li class="uk-active"><a href="#"><?php echo Text::_('COM_J2COMMERCE_PRODUCT_DESCRIPTION'); ?></a></li>
        <?php endif; ?>

        <?php if ($this->params->get('item_show_product_specification')) : ?>
            <li<?php echo $set_specification_active ? ' class="uk-active"' : ''; ?>><a href="#"><?php echo Text::_('COM_J2COMMERCE_PRODUCT_SPECIFICATIONS'); ?></a></li>
        <?php endif; ?>

        <?php echo J2CommerceHelper::plugin()->eventWithHtml('AfterRenderingTabLink', [$this->product])->getArgument('html', ''); ?>
    </ul>

    <ul class="uk-switcher uk-margin">
        <?php if ($this->params->get('item_show_ldesc')) : ?>
            <li>
                <?php echo $this->loadTemplate('ldesc'); ?>
            </li>
        <?php endif; ?>

        <?php if ($this->params->get('item_show_product_specification')) : ?>
            <li>
                <?php echo $this->loadTemplate('specs'); ?>
            </li>
        <?php endif; ?>

        <?php echo J2CommerceHelper::plugin()->eventWithHtml('AfterRenderingTabContent', [$this->product])->getArgument('html', ''); ?>
    </ul>
</div>
