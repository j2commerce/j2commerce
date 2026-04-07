<?php

/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace J2Commerce\Component\J2commerce\Administrator\Helper;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;

/**
 * Cart helper class.
 *
 * Provides static methods for cart-related operations including
 * subtotal calculations, tax calculations, weight totals, and cart management.
 *
 * @since  6.0.0
 */
class CartHelper
{
    /**
     * Singleton instance
     *
     * @var   CartHelper|null
     * @since 6.0.0
     */
    private static ?CartHelper $instance = null;

    /**
     * Cached database instance
     *
     * @var   DatabaseInterface|null
     * @since 6.0.0
     */
    private static ?DatabaseInterface $db = null;

    /**
     * Error message storage
     *
     * @var   string
     * @since 6.0.0
     */
    private string $error = '';

    /**
     * Constructor
     *
     * @param   array  $config  Configuration array
     *
     * @since   6.0.0
     */
    public function __construct(array $config = [])
    {
        // Constructor for future extensibility
    }

    /**
     * Get singleton instance
     *
     * @param   array  $config  Configuration array
     *
     * @return  self
     *
     * @since   6.0.0
     */
    public static function getInstance(array $config = []): self
    {
        if (self::$instance === null) {
            self::$instance = new self($config);
        }

        return self::$instance;
    }

    /**
     * Get the database instance
     *
     * @return  DatabaseInterface
     *
     * @since   6.0.0
     */
    private static function getDatabase(): DatabaseInterface
    {
        if (self::$db === null) {
            self::$db = Factory::getContainer()->get(DatabaseInterface::class);
        }

        return self::$db;
    }

    /**
     * Set the error message
     *
     * @param   string  $error  Error message
     *
     * @return  void
     *
     * @since   6.0.0
     */
    private function setError(string $error): void
    {
        $this->error = $error;
    }

    /**
     * Get the error message
     *
     * @return  string
     *
     * @since   6.0.0
     */
    public function getError(): string
    {
        return $this->error;
    }

    // =========================================================================
    // CART CALCULATION METHODS
    // =========================================================================

    /**
     * Calculate cart subtotal from items.
     *
     * @param   array  $items  Array of cart item objects with product_subtotal property.
     *
     * @return  float  Cart subtotal.
     *
     * @since   6.0.0
     */
    public function getSubtotal(array $items): float
    {
        $subtotal = 0.0;

        if (empty($items)) {
            return $subtotal;
        }

        foreach ($items as $item) {
            if (isset($item->product_subtotal)) {
                $subtotal += (float) $item->product_subtotal;
            }
        }

        return $subtotal;
    }

    /**
     * Calculate cart tax total from items.
     *
     * @param   array  $items  Array of cart item objects with taxes property.
     *
     * @return  float  Cart tax total.
     *
     * @since   6.0.0
     */
    public function getCartTaxTotal(array $items): float
    {
        $taxTotal = 0.0;

        if (empty($items)) {
            return $taxTotal;
        }

        foreach ($items as $item) {
            if (isset($item->taxes, $item->taxes->taxtotal)) {
                $taxTotal += (float) $item->taxes->taxtotal;
            }
        }

        return $taxTotal;
    }

    /**
     * Get aggregated tax data from cart/order items.
     *
     * Groups taxes by tax rate ID and calculates totals.
     *
     * @param   array  $items  Array of item objects with taxes property.
     *
     * @return  array  Associative array of tax data keyed by tax rate ID.
     *
     * @since   6.0.0
     */
    public static function getTaxes(array $items): array
    {
        $taxData = [];

        foreach ($items as $item) {
            if (empty($item->orderitem_taxprofile_id)) {
                continue;
            }

            if (!isset($item->taxes) || !isset($item->taxes->taxes)) {
                continue;
            }

            $taxRates = $item->taxes->taxes;
            $quantity = (int) ($item->orderitem_quantity ?? 1);

            foreach ($taxRates as $taxRateId => $taxRate) {
                $amount = (float) ($taxRate['amount'] ?? 0);

                if (!isset($taxData[$taxRateId])) {
                    $taxData[$taxRateId] = [
                        'name'  => $taxRate['name'] ?? '',
                        'rate'  => (float) ($taxRate['rate'] ?? 0),
                        'total' => $amount * $quantity,
                    ];
                } else {
                    $taxData[$taxRateId]['name']  = $taxRate['name'] ?? '';
                    $taxData[$taxRateId]['rate']  = (float) ($taxRate['rate'] ?? 0);
                    $taxData[$taxRateId]['total'] += $amount * $quantity;
                }
            }
        }

        return $taxData;
    }

    /**
     * Calculate cart total weight from items.
     *
     * Only includes items with shipping enabled.
     *
     * @param   array  $items  Array of cart item objects with weight_total property.
     *
     * @return  float  Cart total weight.
     *
     * @since   6.0.0
     */
    public function getCartTotalWeight(array $items): float
    {
        $weightTotal = 0.0;

        if (empty($items)) {
            return $weightTotal;
        }

        foreach ($items as $item) {
            // Only include items where shipping is enabled
            if (isset($item->shipping) && $item->shipping == 1) {
                $weightTotal += (float) ($item->weight_total ?? 0);
            }
        }

        return $weightTotal;
    }

    // =========================================================================
    // CART ITEM MANAGEMENT METHODS
    // =========================================================================

    /**
     * Remove a cart item by ID.
     *
     * Placeholder method for cart item removal.
     *
     * @param   int  $cartId  Cart ID to remove.
     *
     * @return  bool  True on success.
     *
     * @since   6.0.0
     * @deprecated Use CartModel::removeItem() instead.
     */
    public function removeCartItem(int $cartId): bool
    {
        // TODO: Implement via CartModel when available
        return true;
    }

    /**
     * Get product image by type and product ID.
     *
     * Placeholder method for image retrieval.
     *
     * @param   string  $type       Image type.
     * @param   int     $productId  Product ID.
     *
     * @return  string  Image path or empty string.
     *
     * @since   6.0.0
     * @deprecated Use ProductHelper::getImage() instead.
     */
    public function getImage(string $type, int $productId): string
    {
        // TODO: Implement via ProductHelper when available
        return '';
    }

    // =========================================================================
    // CART RESET AND SESSION MANAGEMENT METHODS
    // =========================================================================

    /**
     * Reset cart when user logs in.
     *
     * Merges guest cart items with existing user cart.
     *
     * @param   string  $sessionId  Old session ID (guest session).
     * @param   int     $userId     User ID to associate with cart.
     *
     * @return  void
     *
     * @since   6.0.0
     */
    public function resetCart(string $guestSessionId, int $userId): void
    {
        $db           = self::getDatabase();
        $newSessionId = Factory::getApplication()->getSession()->getId();

        // Load guest cart (user_id=0 only — avoid picking up a logged-in user's cart)
        $guestCart = $this->loadCartBySession($guestSessionId, 'cart', true);

        if (!$guestCart) {
            return;
        }

        $guestCartId = (int) $guestCart->j2commerce_cart_id;
        $userCart    = $this->loadCartByUserId($userId, 'cart');

        if (!$userCart) {
            // No existing user cart — just claim the guest cart
            $modifiedOn = Factory::getDate()->toSql();

            $query = $db->getQuery(true)
                ->update($db->quoteName('#__j2commerce_carts'))
                ->set($db->quoteName('user_id') . ' = :userId')
                ->set($db->quoteName('session_id') . ' = :sessionId')
                ->set($db->quoteName('modified_on') . ' = :modifiedOn')
                ->where($db->quoteName('j2commerce_cart_id') . ' = :cartId')
                ->bind(':userId', $userId, ParameterType::INTEGER)
                ->bind(':sessionId', $newSessionId)
                ->bind(':modifiedOn', $modifiedOn)
                ->bind(':cartId', $guestCartId, ParameterType::INTEGER);

            try {
                $db->setQuery($query);
                $db->execute();
            } catch (\Throwable $e) {
                Factory::getApplication()->enqueueMessage($e->getMessage(), 'notice');
            }

            return;
        }

        $userCartId = (int) $userCart->j2commerce_cart_id;

        if ($guestCartId === $userCartId) {
            // Same cart — just update session ID
            $modifiedOn = Factory::getDate()->toSql();

            $query = $db->getQuery(true)
                ->update($db->quoteName('#__j2commerce_carts'))
                ->set($db->quoteName('session_id') . ' = :sessionId')
                ->set($db->quoteName('modified_on') . ' = :modifiedOn')
                ->where($db->quoteName('j2commerce_cart_id') . ' = :cartId')
                ->bind(':sessionId', $newSessionId)
                ->bind(':modifiedOn', $modifiedOn)
                ->bind(':cartId', $guestCartId, ParameterType::INTEGER);

            try {
                $db->setQuery($query);
                $db->execute();
            } catch (\Throwable $e) {
                Factory::getApplication()->enqueueMessage($e->getMessage(), 'notice');
            }

            return;
        }

        // Different carts — merge guest items into user cart
        try {
            $this->updateCartitemEntry($guestCart, $userCart);
        } catch (\Throwable $e) {
            Factory::getApplication()->enqueueMessage($e->getMessage(), 'notice');

            return;
        }

        // Update session on user's cart
        $modifiedOn = Factory::getDate()->toSql();

        $query = $db->getQuery(true)
            ->update($db->quoteName('#__j2commerce_carts'))
            ->set($db->quoteName('session_id') . ' = :sessionId')
            ->set($db->quoteName('modified_on') . ' = :modifiedOn')
            ->where($db->quoteName('j2commerce_cart_id') . ' = :cartId')
            ->bind(':sessionId', $newSessionId)
            ->bind(':modifiedOn', $modifiedOn)
            ->bind(':cartId', $userCartId, ParameterType::INTEGER);

        try {
            $db->setQuery($query);
            $db->execute();
        } catch (\Throwable $e) {
            Factory::getApplication()->enqueueMessage($e->getMessage(), 'notice');
        }

        // Delete the now-empty guest cart record
        $this->deleteSessionCartItems($guestSessionId);
    }

    /**
     * Update/merge cart item entries.
     *
     * Moves items from old cart to existing user cart.
     *
     * @param   object  $currentCart   Source cart object.
     * @param   object  $existingCart  Destination cart object.
     *
     * @return  bool  True on success.
     *
     * @since   6.0.0
     */
    public function updateCartitemEntry(object $currentCart, object $existingCart): bool
    {
        $db         = self::getDatabase();
        $srcCartId  = (int) $currentCart->j2commerce_cart_id;
        $destCartId = (int) $existingCart->j2commerce_cart_id;

        // Get items from source cart
        $items = $this->getCartItems($srcCartId);

        if (empty($items)) {
            return true;
        }

        foreach ($items as $item) {
            // Check if item already exists in destination cart
            $productOptions = $item->product_options ?? '';
            $existingItem   = $this->findCartItem(
                $destCartId,
                (int) $item->product_id,
                (int) $item->variant_id,
                $productOptions
            );

            $itemId = (int) $item->j2commerce_cartitem_id;

            if ($existingItem) {
                // Item exists in destination: merge quantity, then delete source item
                $existingItemId = (int) $existingItem->j2commerce_cartitem_id;
                $newQty         = (float) $existingItem->product_qty + (float) $item->product_qty;

                $query = $db->getQuery(true)
                    ->update($db->quoteName('#__j2commerce_cartitems'))
                    ->set($db->quoteName('product_qty') . ' = :qty')
                    ->where($db->quoteName('j2commerce_cartitem_id') . ' = :itemId')
                    ->bind(':qty', $newQty)
                    ->bind(':itemId', $existingItemId, ParameterType::INTEGER);

                $db->setQuery($query);
                $db->execute();

                // Delete the source item after successful merge
                $this->deleteCartItem($itemId);
            } else {
                // Item does not exist in destination: move it by updating cart_id
                $query = $db->getQuery(true)
                    ->update($db->quoteName('#__j2commerce_cartitems'))
                    ->set($db->quoteName('cart_id') . ' = :destCartId')
                    ->where($db->quoteName('j2commerce_cartitem_id') . ' = :itemId')
                    ->bind(':destCartId', $destCartId, ParameterType::INTEGER)
                    ->bind(':itemId', $itemId, ParameterType::INTEGER);

                $db->setQuery($query);
                $db->execute();
            }
        }

        return true;
    }

    /**
     * Delete a cart item by ID.
     *
     * @param   int  $cartitemId  Cart item ID.
     *
     * @return  bool  True on success, false on failure.
     *
     * @since   6.0.0
     */
    public function deleteCartItem(int $cartitemId): bool
    {
        $db    = self::getDatabase();
        $query = $db->getQuery(true);

        $query->delete($db->quoteName('#__j2commerce_cartitems'))
            ->where($db->quoteName('j2commerce_cartitem_id') . ' = :cartitemId')
            ->bind(':cartitemId', $cartitemId, ParameterType::INTEGER);

        try {
            $db->setQuery($query);
            $db->execute();
        } catch (\Throwable $e) {
            $this->setError($e->getMessage());
            return false;
        }

        return true;
    }

    /**
     * Update session ID for user's carts.
     *
     * @param   int     $userId     User ID.
     * @param   string  $sessionId  New session ID.
     *
     * @return  bool  True on success, false on failure.
     *
     * @since   6.0.0
     */
    public function updateSession(int $userId, string $sessionId): bool
    {
        $db    = self::getDatabase();
        $query = $db->getQuery(true);

        $query->update($db->quoteName('#__j2commerce_carts'))
            ->set($db->quoteName('session_id') . ' = :sessionId')
            ->where($db->quoteName('user_id') . ' = :userId')
            ->bind(':sessionId', $sessionId)
            ->bind(':userId', $userId, ParameterType::INTEGER);

        try {
            $db->setQuery($query);
            $db->execute();
        } catch (\Throwable $e) {
            $this->setError($e->getMessage());
            return false;
        }

        return true;
    }

    /**
     * Delete cart items for guest session (user_id <= 0).
     *
     * @param   string  $sessionId  Session ID.
     *
     * @return  bool  True on success, false on failure.
     *
     * @since   6.0.0
     */
    public function deleteSessionCartItems(string $sessionId): bool
    {
        $db    = self::getDatabase();
        $query = $db->getQuery(true);

        $query->delete($db->quoteName('#__j2commerce_carts'))
            ->where($db->quoteName('session_id') . ' = :sessionId')
            ->where($db->quoteName('user_id') . ' <= 0')
            ->bind(':sessionId', $sessionId);

        try {
            $db->setQuery($query);
            $db->execute();
        } catch (\Throwable $e) {
            $this->setError($e->getMessage());
            return false;
        }

        return true;
    }

    /**
     * Empty/delete cart after order completion.
     *
     * @param   string  $orderId  Order ID (the string order_id, not the primary key).
     *
     * @return  bool  True on success.
     *
     * @since   6.0.0
     */
    public static function emptyCart(string $orderId): bool
    {
        $db = self::getDatabase();

        // Load order to get cart_id
        $query = $db->getQuery(true)
            ->select($db->quoteName(['j2commerce_order_id', 'cart_id']))
            ->from($db->quoteName('#__j2commerce_orders'))
            ->where($db->quoteName('order_id') . ' = :orderId')
            ->bind(':orderId', $orderId);

        $db->setQuery($query);
        $order = $db->loadObject();

        if (!$order || empty($order->cart_id)) {
            return false;
        }

        $cartId = (int) $order->cart_id;

        // Trigger plugin event before emptying cart
        // TODO: Implement plugin events when J2Commerce plugin system is ready
        // J2Commerce::plugin()->event('BeforeEmptyCart', [$cart]);

        // Delete cart items first
        $deleteItems = $db->getQuery(true)
            ->delete($db->quoteName('#__j2commerce_cartitems'))
            ->where($db->quoteName('cart_id') . ' = :cartId')
            ->bind(':cartId', $cartId, ParameterType::INTEGER);

        $db->setQuery($deleteItems);
        $db->execute();

        // Delete cart record
        $deleteCart = $db->getQuery(true)
            ->delete($db->quoteName('#__j2commerce_carts'))
            ->where($db->quoteName('j2commerce_cart_id') . ' = :cartId')
            ->bind(':cartId', $cartId, ParameterType::INTEGER);

        $db->setQuery($deleteCart);
        $db->execute();

        // Clear cart cookie
        self::getInstance()->clearCartCookie();

        // Trigger plugin event after emptying cart
        // TODO: Implement plugin events when J2Commerce plugin system is ready
        // J2Commerce::plugin()->event('AfterEmptyCart', [$cart]);

        return true;
    }

    // =========================================================================
    // CART LOADING HELPER METHODS
    // =========================================================================

    /**
     * Load cart by session ID.
     *
     * @param   string  $sessionId  Session ID.
     * @param   string  $cartType   Cart type.
     *
     * @return  object|null  Cart object or null.
     *
     * @since   6.0.0
     */
    private function loadCartBySession(string $sessionId, string $cartType = 'cart', bool $guestOnly = false): ?object
    {
        $db    = self::getDatabase();
        $query = $db->getQuery(true);

        $query->select('*')
            ->from($db->quoteName('#__j2commerce_carts'))
            ->where($db->quoteName('session_id') . ' = :sessionId')
            ->where($db->quoteName('cart_type') . ' = :cartType')
            ->bind(':sessionId', $sessionId)
            ->bind(':cartType', $cartType);

        if ($guestOnly) {
            $query->where($db->quoteName('user_id') . ' <= 0');
        }

        $db->setQuery($query);

        return $db->loadObject() ?: null;
    }

    /**
     * Load cart by user ID.
     *
     * @param   int     $userId    User ID.
     * @param   string  $cartType  Cart type.
     *
     * @return  object|null  Cart object or null.
     *
     * @since   6.0.0
     */
    private function loadCartByUserId(int $userId, string $cartType = 'cart'): ?object
    {
        $db    = self::getDatabase();
        $query = $db->getQuery(true);

        $query->select('*')
            ->from($db->quoteName('#__j2commerce_carts'))
            ->where($db->quoteName('user_id') . ' = :userId')
            ->where($db->quoteName('cart_type') . ' = :cartType')
            ->bind(':userId', $userId, ParameterType::INTEGER)
            ->bind(':cartType', $cartType);

        $db->setQuery($query);

        return $db->loadObject() ?: null;
    }

    /**
     * Get cart items for a cart.
     *
     * @param   int  $cartId  Cart ID.
     *
     * @return  array  Array of cart item objects.
     *
     * @since   6.0.0
     */
    private function getCartItems(int $cartId): array
    {
        $db    = self::getDatabase();
        $query = $db->getQuery(true);

        $query->select('*')
            ->from($db->quoteName('#__j2commerce_cartitems'))
            ->where($db->quoteName('cart_id') . ' = :cartId')
            ->bind(':cartId', $cartId, ParameterType::INTEGER);

        $db->setQuery($query);

        return $db->loadObjectList() ?: [];
    }

    /**
     * Find a specific cart item in a cart.
     *
     * @param   int     $cartId          Cart ID.
     * @param   int     $productId       Product ID.
     * @param   int     $variantId       Variant ID.
     * @param   string  $productOptions  Product options JSON string.
     *
     * @return  object|null  Cart item object or null.
     *
     * @since   6.0.0
     */
    private function findCartItem(int $cartId, int $productId, int $variantId, string $productOptions = ''): ?object
    {
        $db    = self::getDatabase();
        $query = $db->getQuery(true);

        $query->select('*')
            ->from($db->quoteName('#__j2commerce_cartitems'))
            ->where($db->quoteName('cart_id') . ' = :cartId')
            ->where($db->quoteName('product_id') . ' = :productId')
            ->where($db->quoteName('variant_id') . ' = :variantId')
            ->where($db->quoteName('product_options') . ' = :options')
            ->bind(':cartId', $cartId, ParameterType::INTEGER)
            ->bind(':productId', $productId, ParameterType::INTEGER)
            ->bind(':variantId', $variantId, ParameterType::INTEGER)
            ->bind(':options', $productOptions);

        $db->setQuery($query);

        return $db->loadObject() ?: null;
    }

    // =========================================================================
    // UTILITY METHODS
    // =========================================================================

    /**
     * Get cart count for current user/session.
     *
     * @return  int  Number of items in cart.
     *
     * @since   6.0.0
     */
    public static function getCartItemCount(): int
    {
        $app     = Factory::getApplication();
        $user    = $app->getIdentity();
        $session = $app->getSession();

        $db    = self::getDatabase();
        $query = $db->getQuery(true);

        $query->select('SUM(' . $db->quoteName('ci.product_qty') . ') AS item_count')
            ->from($db->quoteName('#__j2commerce_carts', 'c'))
            ->join(
                'LEFT',
                $db->quoteName('#__j2commerce_cartitems', 'ci') . ' ON ' .
                $db->quoteName('ci.cart_id') . ' = ' . $db->quoteName('c.j2commerce_cart_id')
            )
            ->where($db->quoteName('c.cart_type') . ' = ' . $db->quote('cart'));

        if ($user && $user->id > 0) {
            $userId = $user->id;
            $query->where($db->quoteName('c.user_id') . ' = :userId')
                ->bind(':userId', $userId, ParameterType::INTEGER);
        } else {
            $sessionId = $session->getId();
            $query->where($db->quoteName('c.session_id') . ' = :sessionId')
                ->bind(':sessionId', $sessionId);
        }

        $db->setQuery($query);

        return (int) $db->loadResult();
    }

    /**
     * Get cart total for current user/session.
     *
     * @return  float  Cart total.
     *
     * @since   6.0.0
     */
    public static function getCartTotal(): float
    {
        // TODO: Implement full cart total calculation including taxes, shipping, discounts
        // For now, return 0. This requires integration with CartModel.
        return 0.0;
    }

    /**
     * Check if cart is empty for current user/session.
     *
     * @return  bool  True if cart is empty.
     *
     * @since   6.0.0
     */
    public static function isCartEmpty(): bool
    {
        return self::getCartItemCount() === 0;
    }

    /**
     * Get cart for current user or session.
     *
     * This method retrieves the cart based on user ID (if logged in)
     * or session ID (for guest users). For guests, it also checks a
     * cookie-based cart ID as a fallback when sessions change.
     *
     * @param   int   $cartId          Optional cart ID to load specific cart.
     * @param   bool  $needCreateCart  Whether to create a cart if none exists.
     *
     * @return  object|null  Cart object or null if not found.
     *
     * @since   6.0.6
     */
    public function getCart(int $cartId = 0, bool $needCreateCart = true): ?object
    {
        $app     = Factory::getApplication();
        $user    = $app->getIdentity();
        $session = $app->getSession();
        $db      = self::getDatabase();

        // If cart ID is provided, load specific cart
        if ($cartId > 0) {
            $query = $db->getQuery(true)
                ->select('*')
                ->from($db->quoteName('#__j2commerce_carts'))
                ->where($db->quoteName('j2commerce_cart_id') . ' = :cartId')
                ->bind(':cartId', $cartId, ParameterType::INTEGER);

            $db->setQuery($query);
            $cart = $db->loadObject();

            if ($cart) {
                return $cart;
            }
        }

        $cartType = 'cart';
        $cart     = null;

        // Try to load cart by user ID if logged in
        if ($user && $user->id > 0) {
            $cart = $this->loadCartByUserId($user->id, $cartType);

            // Also check for session-based cart to merge
            $sessionId = $session->getId();
            if ($sessionId) {
                $sessionCart = $this->loadCartBySession($sessionId, $cartType);

                if ($sessionCart && (!$cart || $sessionCart->j2commerce_cart_id !== ($cart->j2commerce_cart_id ?? 0))) {
                    // If user cart exists and session cart is different, we would merge
                    // For now, prefer user cart
                    if (!$cart && $sessionCart) {
                        // Update session cart to be associated with user
                        $updateUserId = $user->id;
                        $updateCartId = (int) $sessionCart->j2commerce_cart_id;
                        $updateQuery  = $db->getQuery(true)
                            ->update($db->quoteName('#__j2commerce_carts'))
                            ->set($db->quoteName('user_id') . ' = :userId')
                            ->where($db->quoteName('j2commerce_cart_id') . ' = :cartId')
                            ->bind(':userId', $updateUserId, ParameterType::INTEGER)
                            ->bind(':cartId', $updateCartId, ParameterType::INTEGER);

                        $db->setQuery($updateQuery);
                        $db->execute();

                        $sessionCart->user_id = $user->id;
                        $cart                 = $sessionCart;
                    }
                }
            }
        } else {
            // Guest user - load by session (guest carts only)
            $sessionId = $session->getId();

            if ($sessionId) {
                $cart = $this->loadCartBySession($sessionId, $cartType, true);
            }

            // If not found by session, try to find by cookie
            if (!$cart) {
                $cart = $this->loadCartByCookie($cartType, $sessionId);
            }
        }

        // Create new cart if needed and not found
        if (!$cart && $needCreateCart) {
            $cart = $this->createCart();
        }

        // Set cart cookie for persistence across session changes
        if ($cart) {
            $this->setCartCookie((int) $cart->j2commerce_cart_id);
        }

        return $cart;
    }

    /**
     * Load cart by cookie-stored cart ID.
     *
     * This is a fallback mechanism for guest users when their session changes.
     * If the cart is found via cookie, we update its session_id to match the current session.
     *
     * @param   string  $cartType   Cart type.
     * @param   string  $sessionId  Current session ID to update the cart with.
     *
     * @return  object|null  Cart object or null.
     *
     * @since   6.0.6
     */
    private function loadCartByCookie(string $cartType = 'cart', string $sessionId = ''): ?object
    {
        $app = Factory::getApplication();

        // Get cart ID from cookie
        $cookieCartId = (int) $app->getInput()->cookie->getInt('j2commerce_cart_id', 0);

        if ($cookieCartId <= 0) {
            return null;
        }

        $db = self::getDatabase();

        // Load cart by ID and verify it's a guest cart of the correct type
        $query = $db->getQuery(true)
            ->select('*')
            ->from($db->quoteName('#__j2commerce_carts'))
            ->where($db->quoteName('j2commerce_cart_id') . ' = :cartId')
            ->where($db->quoteName('cart_type') . ' = :cartType')
            ->where($db->quoteName('user_id') . ' = 0')
            ->bind(':cartId', $cookieCartId, ParameterType::INTEGER)
            ->bind(':cartType', $cartType);

        $db->setQuery($query);
        $cart = $db->loadObject();

        if (!$cart) {
            // Cookie points to invalid cart, clear it
            $this->clearCartCookie();

            return null;
        }

        // Update the cart's session_id to match current session
        if (!empty($sessionId) && $cart->session_id !== $sessionId) {
            $modifiedOn       = Factory::getDate()->toSql();
            $cookieCartIdBind = (int) $cart->j2commerce_cart_id;
            $updateQuery      = $db->getQuery(true)
                ->update($db->quoteName('#__j2commerce_carts'))
                ->set($db->quoteName('session_id') . ' = :sessionId')
                ->set($db->quoteName('modified_on') . ' = :modifiedOn')
                ->where($db->quoteName('j2commerce_cart_id') . ' = :cartId')
                ->bind(':sessionId', $sessionId)
                ->bind(':modifiedOn', $modifiedOn)
                ->bind(':cartId', $cookieCartIdBind, ParameterType::INTEGER);

            try {
                $db->setQuery($updateQuery);
                $db->execute();
                $cart->session_id = $sessionId;
            } catch (\Throwable $e) {
                // Log but don't fail - cart is still usable
            }
        }

        return $cart;
    }

    /**
     * Set cart ID cookie for persistence across session changes.
     *
     * @param   int  $cartId  Cart ID to store.
     *
     * @return  void
     *
     * @since   6.0.6
     */
    private function setCartCookie(int $cartId): void
    {
        if ($cartId <= 0) {
            return;
        }

        $app = Factory::getApplication();

        // Check if cookie already set with same value
        $existingCartId = (int) $app->getInput()->cookie->getInt('j2commerce_cart_id', 0);

        if ($existingCartId === $cartId) {
            return;
        }

        // Set cookie for 30 days
        $expires = time() + (30 * 24 * 60 * 60);
        $path    = $app->get('cookie_path', '/');
        $domain  = $app->get('cookie_domain', '');
        $secure  = $app->isHttpsForced();

        setcookie('j2commerce_cart_id', (string) $cartId, $expires, $path, $domain, $secure, true);
    }

    /**
     * Clear cart cookie.
     *
     * @return  void
     *
     * @since   6.0.6
     */
    public function clearCartCookie(): void
    {
        $app    = Factory::getApplication();
        $path   = $app->get('cookie_path', '/');
        $domain = $app->get('cookie_domain', '');

        setcookie('j2commerce_cart_id', '', time() - 3600, $path, $domain, false, true);
    }

    /**
     * Create a new cart for current user/session.
     *
     * @return  object|null  The new cart object or null on failure.
     *
     * @since   6.0.6
     */
    private function createCart(): ?object
    {
        $app     = Factory::getApplication();
        $user    = $app->getIdentity();
        $session = $app->getSession();
        $db      = self::getDatabase();

        $userId    = ($user && $user->id > 0) ? $user->id : 0;
        $sessionId = $session->getId();
        $now       = Factory::getDate()->toSql();
        $ip        = $app->getInput()->server->get('REMOTE_ADDR', '', 'string');
        $browser   = $app->getInput()->server->get('HTTP_USER_AGENT', '', 'string');
        $cartType  = 'cart';

        $columns = [
            'user_id',
            'session_id',
            'cart_type',
            'created_on',
            'modified_on',
            'customer_ip',
            'cart_browser',
            'cart_voucher',
            'cart_coupon',
            'cart_params',
            'cart_analytics',
        ];

        $empty = '';

        $query = $db->getQuery(true)
            ->insert($db->quoteName('#__j2commerce_carts'))
            ->columns($db->quoteName($columns))
            ->values(
                ':userId, :sessionId, :cartType, :createdOn, :modifiedOn, ' .
                ':ip, :browser, :voucher, :coupon, :params, :analytics'
            )
            ->bind(':userId', $userId, ParameterType::INTEGER)
            ->bind(':sessionId', $sessionId)
            ->bind(':cartType', $cartType)
            ->bind(':createdOn', $now)
            ->bind(':modifiedOn', $now)
            ->bind(':ip', $ip)
            ->bind(':browser', $browser)
            ->bind(':voucher', $empty)
            ->bind(':coupon', $empty)
            ->bind(':params', $empty)
            ->bind(':analytics', $empty);

        try {
            $db->setQuery($query);
            $db->execute();
            $cartId = (int) $db->insertid();

            // Return the newly created cart
            return $this->getCart($cartId, false);
        } catch (\Throwable $e) {
            $this->setError($e->getMessage());
            return null;
        }
    }
}
