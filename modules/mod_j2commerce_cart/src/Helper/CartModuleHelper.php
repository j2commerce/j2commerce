<?php

/**
 * @package     J2Commerce
 * @subpackage  mod_j2commerce_cart
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace J2Commerce\Module\Cart\Site\Helper;

\defined('_JEXEC') or die;

/**
 * Helper class for the cart module.
 *
 * Required by Joomla's HelperFactory service provider.
 * Cart data retrieval is handled directly in the Dispatcher using
 * the admin CartModel and OrderHelper (same approach as the cart page).
 *
 * @since  6.0.0
 */
class CartModuleHelper
{
}
