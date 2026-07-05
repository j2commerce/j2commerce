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
<div class="j2commerce-product-quickview position-absolute bottom-0 end-0 mb-2 me-2 mb-lg-3 me-lg-3">
    <a data-fancybox data-type="iframe" class="btn btn-dark btn-sm j2commerce-quickview-btn" data-src="<?php echo $quickviewUrl; ?>" href="javascript:" title="<?php echo Text::_('COM_J2COMMERCE_PRODUCT_QUICKVIEW'); ?>">
        <span class="fa-solid fa-eye" aria-hidden="true"></span>
    </a>
</div>
