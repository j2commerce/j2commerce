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

$hasShortDesc = $this->params->get('item_show_sdesc') && !empty(trim(strip_tags($this->product->product_short_desc ?? '')));
$hasLongDesc  = $this->params->get('item_show_ldesc') && !empty(trim(strip_tags($this->product->product_long_desc ?? '')));
$hasDescription = $hasShortDesc || $hasLongDesc;
$set_specification_active = !$hasDescription;
?>
<ul class="uk-accordion uk-margin-large-top" id="j2CommerceAccordion" uk-accordion>
    <?php if ($hasDescription) : ?>
        <li class="uk-open">
            <a class="uk-accordion-title" href="#">
                <span class="uk-text-capitalize uk-text-bold"><?php echo Text::_('COM_J2COMMERCE_PRODUCT_DESCRIPTION'); ?></span>
            </a>
            <div class="uk-accordion-content">
                <?php echo $this->loadTemplate('ldesc'); ?>
            </div>
        </li>
    <?php endif; ?>
    <?php if ($this->params->get('item_show_product_specification')) : ?>
        <li<?php echo isset($set_specification_active) && $set_specification_active ? ' class="uk-open"' : ''; ?>>
            <a class="uk-accordion-title" href="#">
                <span class="uk-text-capitalize uk-text-bold"><?php echo Text::_('COM_J2COMMERCE_PRODUCT_SPECIFICATIONS'); ?></span>
            </a>
            <div class="uk-accordion-content">
                <?php echo $this->loadTemplate('specs'); ?>
            </div>
        </li>
    <?php endif; ?>
    <?php if ($this->params->get('item_show_product_filters') && !empty($productfilters)) : ?>
        <li>
            <a class="uk-accordion-title" href="#">
                <span class="uk-text-capitalize uk-text-bold"><?php echo Text::_('COM_J2COMMERCE_PRODUCT_FILTERS'); ?></span>
            </a>
            <div class="uk-accordion-content">
                <?php foreach ($productfilters as $group) : ?>
                    <div class="uk-margin-small-bottom">
                        <h6 class="uk-text-bold uk-margin-small-bottom"><?php echo htmlspecialchars($group['group_name'], ENT_QUOTES, 'UTF-8'); ?></h6>
                        <div class="uk-flex uk-flex-wrap" style="gap:.5rem;">
                            <?php foreach ($group['filters'] as $filter) : ?>
                                <span class="uk-label"><?php echo htmlspecialchars($filter->filter_name, ENT_QUOTES, 'UTF-8'); ?></span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </li>
    <?php endif; ?>
    <?php echo J2CommerceHelper::plugin()->eventWithHtml('AfterRenderingTab', [$this->product]); ?>
</ul>
