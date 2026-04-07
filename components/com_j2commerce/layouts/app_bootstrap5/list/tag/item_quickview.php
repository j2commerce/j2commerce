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

use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use J2Commerce\Component\J2commerce\Site\Helper\RouteHelper;

extract($displayData);

if (!$showQuickview) {
    return;
}

$quickviewUrl = Route::_(
    RouteHelper::getProductRoute((int) $product->j2commerce_product_id) . '&tmpl=component'
);
?>
<div class="j2commerce-product-quickview">
    <a data-fancybox
       data-type="iframe"
       class="btn btn-secondary j2commerce-quickview-btn"
       data-src="<?php echo $quickviewUrl; ?>"
       href="javascript:;">
        <span class="fa fa-eye" aria-hidden="true"></span>
        <?php echo Text::_('COM_J2COMMERCE_PRODUCT_QUICKVIEW'); ?>
    </a>
</div>
