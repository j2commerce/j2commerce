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

use J2Commerce\Component\J2commerce\Administrator\Helper\ImageHelper;
use J2Commerce\Component\J2commerce\Site\Helper\RouteHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

/** @var \J2Commerce\Component\J2commerce\Site\View\Tags\HtmlView $this */

if (empty($this->items)) {
    return;
}

$params     = $this->params;
$activeMenu = Factory::getApplication()->getMenu()->getActive();
?>
<div class="j2commerce-tag-grid row g-4 mb-4">
    <?php foreach ($this->items as $tag) :
        $tagUrl = Route::_(RouteHelper::getTagRouteInContext((int) $tag->id, $activeMenu));
    ?>
        <div class="<?php echo $this->getColumnClass(); ?> j2commerce-tag-col">
            <?php if ($params->get('show_tag_image', 1) && !empty($tag->image)) : ?>
                <a href="<?php echo $tagUrl; ?>" class="j2commerce-tag-link d-block mb-2">
                    <?php echo ImageHelper::getProductImage($tag->image, 300, 'html', 300, 'img-fluid', $tag->image_alt ?: $tag->title); ?>
                </a>
            <?php endif; ?>
            <h2 class="j2commerce-tag-title h4">
                <a href="<?php echo $tagUrl; ?>"><?php echo $this->escape($tag->title); ?></a>
            </h2>
            <?php if ($params->get('show_product_count', 1)) : ?>
                <span class="small text-body-secondary j2commerce-tag-product-count">
                    <?php echo Text::plural('COM_J2COMMERCE_N_PRODUCTS', $tag->product_count); ?>
                </span>
            <?php endif; ?>
            <?php if (!empty($tag->children)) : ?>
                <ul class="j2commerce-tag-children list-unstyled mt-2 small">
                    <?php foreach ($tag->children as $child) : ?>
                        <li>
                            <a href="<?php echo Route::_(RouteHelper::getTagRouteInContext((int) $child->id, $activeMenu)); ?>"><?php echo $this->escape($child->title); ?></a>
                            <?php if ($params->get('show_product_count', 1) && $child->product_count > 0) : ?>
                                <span class="text-body-secondary">(<?php echo (int) $child->product_count; ?>)</span>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
</div>
