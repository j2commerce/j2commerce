<?php

/**
 * @package     J2Commerce
 * @subpackage  mod_j2commerce_relatedproducts
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

\defined('_JEXEC') or die;

use J2Commerce\Module\RelatedProducts\Site\Helper\RelatedProductsHelper;

class ModJ2commerceRelatedproductsHelper
{
    public static function getRelatedHtmlAjax(): string
    {
        return RelatedProductsHelper::getRelatedHtmlAjax();
    }
}
