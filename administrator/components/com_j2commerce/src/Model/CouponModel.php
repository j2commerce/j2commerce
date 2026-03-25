<?php
/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace J2Commerce\Component\J2commerce\Administrator\Model;

\defined('_JEXEC') or die;

use J2Commerce\Component\J2commerce\Administrator\Helper\J2CommerceHelper;
use Exception;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Form\Form;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Model\AdminModel;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\Table\Table;
use Joomla\Database\ParameterType;
use Joomla\String\StringHelper;

/**
 * Coupon item model class.
 *
 * @since  6.0.6
 */
class CouponModel extends AdminModel
{
    /**
     * The type alias for this content type.
     *
     * @var    string
     * @since  6.0.6
     */
    public $typeAlias = 'com_j2commerce.coupon';

    /**
     * The prefix to use with controller messages.
     *
     * @var    string
     * @since  6.0.6
     */
    protected $text_prefix = 'COM_J2COMMERCE_COUPON';

    /**
     * The coupon object for validation
     *
     * @var    object|null
     * @since  6.0.6
     */
    public $coupon = null;

    /**
     * Limit usage to X items
     *
     * @var    string
     * @since  6.0.6
     */
    public $limit_usage_to_x_items = '';

    /**
     * The coupon code from session/cart
     *
     * @var    string
     * @since  6.0.6
     */
    public string $code = '';

    /**
     * Method to auto-populate the model state.
     *
     * CRITICAL: This override is required because the parent AdminModel::populateState()
     * looks for a URL parameter named after the table's primary key (j2commerce_coupon_id),
     * but Joomla's standard convention uses 'id' as the URL parameter.
     *
     * Also supports j2commerce_coupon_id for history layout URLs.
     *
     * @return  void
     *
     * @since   6.0.6
     */
    protected function populateState(): void
    {
        $app = Factory::getApplication();

        // Check for 'id' first (standard Joomla), then 'j2commerce_coupon_id' (for history links)
        $pk = $app->getInput()->getInt('id', 0);

        if ($pk === 0) {
            $pk = $app->getInput()->getInt('j2commerce_coupon_id', 0);
        }

        $this->setState($this->getName() . '.id', $pk);

        $params = ComponentHelper::getParams('com_j2commerce');
        $this->setState('params', $params);
    }

    /**
     * Method to get the record form.
     *
     * @param   array    $data      Data for the form.
     * @param   boolean  $loadData  True if the form is to load its own data (default case), false if not.
     *
     * @return  Form|false  A Form object on success, false on failure
     *
     * @since   6.0.6
     */
    public function getForm($data = [], $loadData = true): Form|false
    {
        $form = $this->loadForm('com_j2commerce.coupon', 'coupon', ['control' => 'jform', 'load_data' => $loadData]);

        if (empty($form)) {
            return false;
        }

        // Modify the form based on access controls
        if (!$this->canEditState((object) $data)) {
            $form->setFieldAttribute('ordering', 'disabled', 'true');
            $form->setFieldAttribute('enabled', 'disabled', 'true');
            $form->setFieldAttribute('ordering', 'filter', 'unset');
            $form->setFieldAttribute('enabled', 'filter', 'unset');
        }

        return $form;
    }

    /**
     * Method to get a table object.
     *
     * @param   string  $name     The table name. Optional.
     * @param   string  $prefix   The class prefix. Optional.
     * @param   array   $options  Configuration array for model. Optional.
     *
     * @return  Table  A Table object
     *
     * @since   6.0.6
     */
    public function getTable($name = 'Coupon', $prefix = 'Administrator', $options = []): Table
    {
        return parent::getTable($name, $prefix, $options);
    }

    /**
     * Method to get the data that should be injected in the form.
     *
     * @return  mixed  The data for the form.
     *
     * @since   6.0.6
     */
    protected function loadFormData(): mixed
    {
        $app  = Factory::getApplication();
        $data = $app->getUserState('com_j2commerce.edit.coupon.data', []);

        if (empty($data)) {
            $data = $this->getItem();

            // Prime some default values for new records
            // Note: $data is a stdClass from getItem(), so use property assignment, not set()
            if ($this->getState($this->getName() . '.id') == 0) {
                $data->enabled = $app->getInput()->getInt('enabled', 1);
            }
        }

        $this->preprocessData('com_j2commerce.coupon', $data);

        return $data;
    }

    /**
     * Method to get a single record.
     *
     * @param   integer  $pk  The id of the primary key.
     *
     * @return  mixed  Object on success, false on failure.
     *
     * @since   6.0.6
     */
    public function getItem($pk = null): mixed
    {
        if ($pk === null) {
            $pk = $this->getState($this->getName() . '.id');
        }

        if ($item = parent::getItem($pk)) {
            // Handle string fields that might be null
            $stringFields = ['users', 'mycategory', 'brand_ids', 'products', 'user_group', 'product_category'];
            foreach ($stringFields as $field) {
                if (isset($item->$field)) {
                    $item->$field = \is_string($item->$field) ? $item->$field : '';
                }
            }

            // Ensure date fields are properly formatted
            if (isset($item->valid_from) && ($item->valid_from === '0000-00-00 00:00:00' || $item->valid_from === null)) {
                $item->valid_from = '';
            }

            if (isset($item->valid_to) && ($item->valid_to === '0000-00-00 00:00:00' || $item->valid_to === null)) {
                $item->valid_to = '';
            }
        }

        return $item;
    }

    /**
     * Initialize coupon from session/cart.
     *
     * Loads the coupon code from session/cart and populates
     * the $coupon property with the coupon data. This method
     * MUST be called before any validation methods.
     *
     * @return  bool  True if coupon was initialized, false if no coupon.
     *
     * @since   6.0.6
     */
    public function init(): bool
    {
        // Get coupon code from session/cart
        $this->code = $this->getCouponFromCart();

        if (empty($this->code)) {
            return false;
        }

        static $couponsets = [];

        if (!isset($couponsets[$this->code])) {
            $coupon = $this->getCouponByCode($this->code);
            $couponsets[$this->code] = $coupon;
        }

        $this->coupon = $couponsets[$this->code];

        if (isset($this->coupon->max_quantity)) {
            $this->limit_usage_to_x_items = (string) $this->coupon->max_quantity;
        }

        return true;
    }

    /**
     * Get coupon code from cart or session.
     *
     * First checks the cart table for cart_coupon, then falls back to session.
     *
     * @return  string  The coupon code or empty string.
     *
     * @since   6.0.6
     */
    protected function getCouponFromCart(): string
    {
        $app = Factory::getApplication();
        $session = $app->getSession();

        try {
            // Get the CartModel via MVC Factory
            /** @var CartModel $cartModel */
            $cartModel = $app->bootComponent('com_j2commerce')
                ->getMVCFactory()
                ->createModel('Cart', 'Administrator', ['ignore_request' => true]);

            if ($cartModel) {
                $cart = $cartModel->getCart(0, false);

                if (isset($cart->cart_coupon) && !empty($cart->cart_coupon)) {
                    // Sync cart coupon to session
                    $session->set('coupon', $cart->cart_coupon, 'j2commerce');

                    return $cart->cart_coupon;
                }
            }
        } catch (\Exception $e) {
            // Fall through to session
        }

        return $session->get('coupon', '', 'j2commerce');
    }

    /**
     * Prepare and sanitize the table before saving.
     *
     * @param   Table  $table  The Table object.
     *
     * @return  void
     *
     * @since   6.0.6
     */
    protected function prepareTable($table): void
    {
        // Trim coupon name
        if (!empty($table->coupon_name)) {
            $table->coupon_name = trim($table->coupon_name);
        }

        // Trim and uppercase coupon code
        if (!empty($table->coupon_code)) {
            $table->coupon_code = strtoupper(trim($table->coupon_code));
        }

        // Ensure value is numeric
        if (empty($table->value) || !is_numeric($table->value)) {
            $table->value = 0;
        }

        // Determine user timezone offset – mirrors CalendarField's USER_UTC filter logic.
        // The calendar widget displays stored UTC dates in the user's timezone and submits
        // values in user-local time, so we must convert back to UTC before storing.
        $app    = Factory::getApplication();
        $offset = $app->getIdentity()->getParam('timezone', $app->get('offset'));

        // Ensure dates are properly set
        if (empty($table->valid_from) || $table->valid_from === '0000-00-00 00:00:00') {
            $table->valid_from = null;
        } else {
            try {
                // Treat the submitted value as user-local time and store as UTC
                $table->valid_from  = Factory::getDate($table->valid_from, $offset)->toSql();
            } catch (Exception $e) {
                $table->valid_from  = null;
            }
        }

        if (empty($table->valid_to) || $table->valid_to === '0000-00-00 00:00:00') {
            $table->valid_to = null;
        } else {
            try {
                // Treat the submitted value as user-local time and store as UTC
                $table->valid_to  = Factory::getDate($table->valid_to, $offset)->toSql();
            } catch (Exception $e) {
                $table->valid_to  = null;
            }
        }
    }

    /**
     * Method to save the form data.
     *
     * @param   array  $data  The form data.
     *
     * @return  boolean  True on success, False on error.
     *
     * @since   6.0.6
     */
    public function save($data): bool
    {
        $data['coupon_code'] = strtoupper(trim($data['coupon_code'] ?? ''));

        // Map published to enabled if set
        if (isset($data['published'])) {
            $data['enabled'] = $data['published'];
            unset($data['published']);
        }

        // Ensure numeric fields are properly formatted
        if (isset($data['value'])) {
            $data['value'] = (float) $data['value'];
        }

        if (isset($data['max_value'])) {
            $data['max_value'] = (string) $data['max_value'];
        }

        if (isset($data['min_subtotal'])) {
            $data['min_subtotal'] = (string) $data['min_subtotal'];
        }

        // Handle checkbox fields
        $data['free_shipping'] = isset($data['free_shipping']) ? (int) $data['free_shipping'] : 0;
        $data['logged'] = isset($data['logged']) ? (int) $data['logged'] : 0;

        // Handle integer fields
        $data['max_uses'] = isset($data['max_uses']) ? (int) $data['max_uses'] : 0;
        $data['max_customer_uses'] = isset($data['max_customer_uses']) ? (int) $data['max_customer_uses'] : 0;

        // Handle date fields
        if (isset($data['valid_from']) && empty($data['valid_from'])) {
            $data['valid_from'] = null;
        }

        if (isset($data['valid_to']) && empty($data['valid_to'])) {
            $data['valid_to'] = null;
        }

        if( isset($data['valid_from']) && ($data['valid_from'] !== null) && isset($data['valid_to']) && ($data['valid_to'] !== null) && ($data['valid_from'] >= $data['valid_to'] )) {
            $this->setError(Text::_("COM_J2COMMERCE_COUPON_VALID_FROM_DATE_GREATER_THAN_VALID_TO_DATE"));
            return false;
        }

        // Set default value_type if not provided
        if (!isset($data['value_type']) || empty($data['value_type'])) {
            $data['value_type'] = 'fixed_cart';
        }

        // Include the content plugins for the on save events
        PluginHelper::importPlugin('content');

        // Handle save2copy
        $app = Factory::getApplication();
        if ($app->getInput()->get('task') === 'save2copy') {
            $origTable = clone $this->getTable();
            $origTable->load($app->getInput()->getInt('id'));

            if ($data['coupon_name'] === $origTable->coupon_name) {
                [$name] = $this->generateNewTitle(null, null, $data['coupon_name']);
                $data['coupon_name'] = $name;
            }

            if ($data['coupon_code'] === $origTable->coupon_code) {
                $data['coupon_code'] = $this->generateNewCouponCode($data['coupon_code']);
            }

            $data['enabled'] = 0;
        }

        return parent::save($data);
    }

    /**
     * Method to change the published state of one or more records.
     *
     * @param   array    &$pks   A list of the primary keys to change.
     * @param   integer  $value  The value of the published state.
     *
     * @return  boolean  True on success.
     *
     * @since   6.0.6
     */
    public function publish(&$pks, $value = 1): bool
    {
        PluginHelper::importPlugin('content');

        return parent::publish($pks, $value);
    }

    /**
     * Method to duplicate one or more records.
     *
     * @param   array  $pks  A list of the primary keys to duplicate.
     *
     * @return  boolean  True on success, false on failure.
     *
     * @since   6.0.6
     */
    public function duplicate(array &$pks): bool
    {
        $user = $this->getCurrentUser();

        if (!$user->authorise('core.create', 'com_j2commerce')) {
            throw new Exception(Text::_('JERROR_CORE_CREATE_NOT_PERMITTED'));
        }

        $table = $this->getTable();

        foreach ($pks as $pk) {
            if ($table->load($pk, true)) {
                $table->j2commerce_coupon_id = 0;
                $table->enabled = 0;

                [$name] = $this->generateNewTitle(null, null, $table->coupon_name);
                $table->coupon_name = $name;

                $table->coupon_code = $this->generateNewCouponCode($table->coupon_code);

                if (!$table->check() || !$table->store()) {
                    throw new Exception($table->getError());
                }
            } else {
                throw new Exception($table->getError());
            }
        }

        $this->cleanCache();

        return true;
    }

    /**
     * Method to generate a new coupon name.
     *
     * @param   integer|null  $categoryId  The id of the category (unused but required for parent signature).
     * @param   string|null   $alias       The alias (unused but required for parent signature).
     * @param   string        $title       The title.
     *
     * @return  array  Contains the modified title and alias.
     *
     * @since   6.0.6
     */
    protected function generateNewTitle($categoryId, $alias, $title): array
    {
        $table = $this->getTable();

        while ($table->load(['coupon_name' => $title])) {
            $title = StringHelper::increment($title);
        }

        return [$title, $alias];
    }

    /**
     * Method to generate a new coupon code.
     *
     * @param   string  $couponCode  The original coupon code.
     *
     * @return  string  The new coupon code.
     *
     * @since   6.0.6
     */
    protected function generateNewCouponCode(string $couponCode): string
    {
        $table = $this->getTable();
        $newCode = $couponCode;
        $i = 1;

        while ($table->load(['coupon_code' => $newCode])) {
            $newCode = $couponCode . '_' . $i;
            $i++;
            $table->reset();
        }

        return $newCode;
    }

    /**
     * Get detailed coupon usage history for display.
     *
     * Returns order details for all orders that used this coupon,
     * including order information, customer email, and discount amount.
     *
     * @param   int  $couponId  The coupon ID. Defaults to current item.
     *
     * @return  array  Array of order objects with usage details.
     *
     * @since   6.0.6
     */
    public function getCouponUsageDetails(int $couponId = 0): array
    {
        if ($couponId === 0) {
            $couponId = (int) $this->getState($this->getName() . '.id');
        }

        if ($couponId === 0) {
            return [];
        }

        $db = $this->getDatabase();
        $query = $db->getQuery(true);

        $query->select([
                $db->quoteName('o.j2commerce_order_id'),
                $db->quoteName('o.order_id'),
                $db->quoteName('o.invoice_prefix'),
                $db->quoteName('o.invoice_number'),
                $db->quoteName('o.user_email'),
                $db->quoteName('o.created_on'),
                $db->quoteName('od.discount_amount', 'total'),
                $db->quoteName('od.discount_code'),
            ])
            ->from($db->quoteName('#__j2commerce_orderdiscounts', 'od'))
            ->join(
                'INNER',
                $db->quoteName('#__j2commerce_orders', 'o') . ' ON ' . $db->quoteName('od.order_id') . ' = ' . $db->quoteName('o.order_id')
            )
            ->where($db->quoteName('od.discount_entity_id') . ' = :couponId')
            ->where($db->quoteName('od.discount_type') . ' = ' . $db->quote('coupon'))
            ->where($db->quoteName('o.order_state_id') . ' != 5')
            ->order($db->quoteName('o.created_on') . ' DESC')
            ->bind(':couponId', $couponId, ParameterType::INTEGER);

        $db->setQuery($query);

        return $db->loadObjectList() ?: [];
    }

    /**
     * Get coupon usage count.
     *
     * @param   int     $couponId  The coupon ID.
     * @param   string  $userId    Optional user ID to filter.
     *
     * @return  int  The number of times the coupon has been used.
     *
     * @since   6.0.6
     */
    public function getCouponHistory(int $couponId, string $userId = ''): int
    {
        static $history = [];

        if (!isset($history[$couponId][$userId])) {
            $db = $this->getDatabase();
            $query = $db->getQuery(true);

            $query->select('COUNT(*) AS total')
                ->from($db->quoteName('#__j2commerce_orderdiscounts', 'od'))
                ->join('LEFT', $db->quoteName('#__j2commerce_orders', 'o') . ' ON od.order_id = o.order_id')
                ->where($db->quoteName('o.order_state_id') . ' != 5')
                ->where($db->quoteName('od.discount_entity_id') . ' = :couponId')
                ->where($db->quoteName('od.discount_type') . ' = ' . $db->quote('coupon'))
                ->bind(':couponId', $couponId, ParameterType::INTEGER);

            if (!empty($userId)) {
                $userIdInt = (int) $userId;
                $query->where($db->quoteName('od.user_id') . ' = :userId')
                    ->bind(':userId', $userIdInt, ParameterType::INTEGER);
            }

            $db->setQuery($query);
            $history[$couponId][$userId] = (int) $db->loadResult();
        }

        return $history[$couponId][$userId];
    }

    /**
     * Check if coupon is valid for an order.
     *
     * @param   object  $order  The order object.
     *
     * @return  bool  True if valid.
     *
     * @since   6.0.6
     */
    public function isValid(object $order): bool
    {
        try {
            // Trigger before validation plugin event
            $app = Factory::getApplication();
            PluginHelper::importPlugin('j2commerce');

            $couponStatus = false;
            J2CommerceHelper::plugin()->event('onJ2CommerceBeforeCouponIsValid', [$this, $order, &$couponStatus]);

            if ($couponStatus) {
                return true;
            }

            $this->validateEnabled();
            $this->validateExists();
            $this->validateUsageLimit();
            $this->validateUserLogged();
            $this->validateUsers();
            $this->validateUserGroup();
            $this->validateUserUsageLimit();
            $this->validateExpiryDate();
            $this->validateMinimumAmount($order);
            $this->validateProductIds();

            // Trigger validation plugin event - allow plugins to run their own validation
            $results = J2CommerceHelper::plugin()->eventWithArray('CouponIsValid', [$this, $order]);

            if (\in_array(false, $results, true)) {
                throw new Exception(Text::_('COM_J2COMMERCE_COUPON_NOT_APPLICABLE'));
            }
        } catch (Exception $e) {
            $this->setError($e->getMessage());
            Factory::getApplication()->enqueueMessage($e->getMessage(), 'warning');
            $this->removeCoupon();

            return false;
        }

        return true;
    }

    /**
     * Check if coupon is valid for admin.
     *
     * @param   object  $order  The order object.
     *
     * @return  bool  True if valid.
     *
     * @since   6.0.6
     */
    public function isAdminValid(object $order): bool
    {
        try {
            $this->validateEnabled();
            $this->validateExists();
            $this->validateExpiryDate();
            $this->validateMinimumAmount($order);
            $this->validateAdminProductIds($order);

            $results = J2CommerceHelper::plugin()->eventWithArray('CouponIsValid', [$this, $order]);
            if (\in_array(false, $results, true)) {
                throw new Exception(Text::_('COM_J2COMMERCE_COUPON_NOT_APPLICABLE'));
            }
        } catch (Exception $e) {
            $this->setError($e->getMessage());
            Factory::getApplication()->enqueueMessage($e->getMessage(), 'warning');
            $this->removeCoupon();

            return false;
        }

        return true;
    }

    /**
     * Check if coupon is valid for cart.
     *
     * @return  bool  True if valid for cart.
     *
     * @since   6.0.6
     */
    public function isValidForCart(): bool
    {
        return $this->isType(['fixed_cart', 'percentage_cart']);
    }

    /**
     * Check if coupon is valid for a product.
     *
     * @param   object  $product  The product object.
     *
     * @return  bool  True if valid for product.
     *
     * @since   6.0.6
     */
    public function isValidForProduct(object $product): bool
    {
        if (!$this->isType(['fixed_product', 'percentage_product'])) {
            return false;
        }

        $valid = false;
        $couponProducts = $this->getSelectedProducts();
        $couponCategories = [];
        $brands = [];

        if (!empty($this->coupon->product_category)) {
            $couponCategories = explode(',', $this->coupon->product_category);
        }

        if (!empty($this->coupon->brand_ids)) {
            $brands = explode(',', $this->coupon->brand_ids);
        }

        // No restrictions - all items discounted
        if (!\count($couponCategories) && !\count($couponProducts) && !\count($brands)) {
            return true;
        }

        // Check products
        if (\count($couponProducts) > 0) {
            if (\in_array($product->product_id, $couponProducts)) {
                $valid = true;
            }
        }

        // Check categories
        if (\count($couponCategories) > 0 && isset($product->product_source) && $product->product_source === 'com_content') {
            $db = $this->getDatabase();
            $query = $db->getQuery(true);
            $query->select('catid')
                ->from($db->quoteName('#__content'))
                ->where($db->quoteName('id') . ' = :sourceId')
                ->bind(':sourceId', $product->product_source_id, ParameterType::INTEGER);
            $db->setQuery($query);
            $catId = $db->loadResult();

            if ($catId && \in_array($catId, $couponCategories)) {
                $valid = true;
            }
        }

        // Check brands
        if (\count($brands) > 0) {
            $manufacturerId = $product->cartitem->manufacturer_id ?? '';
            if (!empty($manufacturerId) && \in_array($manufacturerId, $brands)) {
                $valid = true;
            }
        }

        return $valid;
    }

    /**
     * Validate coupon is enabled in config.
     *
     * @return  void
     *
     * @throws  Exception
     * @since   6.0.6
     */
    private function validateEnabled(): void
    {
        $params = ComponentHelper::getParams('com_j2commerce');

        if ($params->get('enable_coupon', 0) == 0) {
            throw new Exception(Text::_('COM_J2COMMERCE_COUPON_NOT_EXIST'));
        }
    }

    /**
     * Validate coupon exists.
     *
     * @return  void
     *
     * @throws  Exception
     * @since   6.0.6
     */
    private function validateExists(): void
    {
        if (!$this->coupon) {
            throw new Exception(Text::_('COM_J2COMMERCE_COUPON_NOT_EXIST'));
        }
    }

    /**
     * Validate coupon usage limit.
     *
     * @return  void
     *
     * @throws  Exception
     * @since   6.0.6
     */
    private function validateUsageLimit(): void
    {
        $total = $this->getCouponHistory($this->coupon->j2commerce_coupon_id);

        if ($this->coupon->max_uses > 0 && ($total >= $this->coupon->max_uses)) {
            throw new Exception(Text::_('COM_J2COMMERCE_COUPON_USAGE_LIMIT_REACHED'));
        }
    }

    /**
     * Validate user is logged in if required.
     *
     * @return  void
     *
     * @throws  Exception
     * @since   6.0.6
     */
    private function validateUserLogged(): void
    {
        $user = Factory::getApplication()->getIdentity();

        if ($this->coupon->logged && !$user->id) {
            throw new Exception(Text::_('COM_J2COMMERCE_COUPON_LOGIN_REQUIRED'));
        }
    }

    /**
     * Validate user is allowed to use coupon.
     *
     * @return  void
     *
     * @throws  Exception
     * @since   6.0.6
     */
    private function validateUsers(): void
    {
        $user = Factory::getApplication()->getIdentity();

        if (!empty($this->coupon->users)) {
            if ($user->id <= 0) {
                throw new Exception(Text::_('COM_J2COMMERCE_COUPON_NOT_APPLICABLE'));
            }

            $users = explode(',', $this->coupon->users);

            if (\count($users) && !\in_array($user->id, $users)) {
                throw new Exception(Text::_('COM_J2COMMERCE_COUPON_NOT_APPLICABLE'));
            }
        }
    }

    /**
     * Validate user group.
     *
     * @return  void
     *
     * @throws  Exception
     * @since   6.0.6
     */
    private function validateUserGroup(): void
    {
        $user = Factory::getApplication()->getIdentity();

        if (!empty($this->coupon->user_group) && \count($user->groups)) {
            $allowedGroups = explode(',', $this->coupon->user_group);

            if (!\count(array_intersect($allowedGroups, $user->groups))) {
                throw new Exception(Text::_('COM_J2COMMERCE_COUPON_NOT_APPLICABLE'));
            }
        }
    }

    /**
     * Validate user usage limit.
     *
     * @return  void
     *
     * @throws  Exception
     * @since   6.0.6
     */
    private function validateUserUsageLimit(): void
    {
        $user = Factory::getApplication()->getIdentity();

        if ($user->id) {
            $customerTotal = $this->getCouponHistory($this->coupon->j2commerce_coupon_id, (string) $user->id);

            if ($this->coupon->max_customer_uses > 0 && ($customerTotal >= $this->coupon->max_customer_uses)) {
                throw new Exception(Text::_('COM_J2COMMERCE_COUPON_USER_USAGE_LIMIT_REACHED'));
            }
        }
    }

    /**
     * Validate coupon expiry date.
     *
     * @return  void
     *
     * @throws  Exception
     * @since   6.0.6
     */
    private function validateExpiryDate(): void
    {
        $db = $this->getDatabase();
        $nullDate = $db->getNullDate();

        // Dates are stored in UTC (converted on save via USER_UTC logic).
        // Compare everything in UTC so the check is timezone-independent.
        $now = Factory::getDate('now')->toSql();

        $validFrom = $this->coupon->valid_from;
        $validTo = $this->coupon->valid_to;

        $isValidFrom = (empty($valid_from) || $validFrom === $nullDate || Factory::getDate($valid_from)->toSql() <= $now);
        $isValidTo = (empty($valid_to) || $validTo === $nullDate || Factory::getDate($valid_to)->toSql() >= $now);

        if (!$isValidFrom || !$isValidTo) {
            throw new Exception(Text::_('COM_J2COMMERCE_COUPON_HAS_EXPIRED'));
        }
    }

    /**
     * Validate minimum order amount.
     *
     * @param   object  $order  The order object.
     *
     * @return  void
     *
     * @throws  Exception
     * @since   6.0.6
     */
    private function validateMinimumAmount(object $order): void
    {
        if (isset($this->coupon->min_subtotal) && (float) $this->coupon->min_subtotal > 0) {
            $subtotal = $order->order_subtotal ?? $order->subtotal ?? 0;

            if ((float) $this->coupon->min_subtotal > (float) $subtotal) {
                throw new Exception(Text::sprintf('COM_J2COMMERCE_COUPON_MINIMUM_NOT_MET', $this->coupon->min_subtotal));
            }
        }
    }

    /**
     * Validate coupon is valid for products in cart.
     *
     * @return  void
     *
     * @throws  Exception
     * @since   6.0.6
     */
    private function validateProductIds(): void
    {
        $couponProducts = $this->getSelectedProducts();
        $couponCategories = [];

        if (!empty($this->coupon->product_category)) {
            $couponCategories = explode(',', $this->coupon->product_category);
        }

        // If no restrictions, coupon is valid for all products
        if (!\count($couponCategories) && !\count($couponProducts) && empty($this->coupon->brand_ids)) {
            return;
        }

        $validForCart = false;
        $app = Factory::getApplication();
        $db = $this->getDatabase();

        try {
            /** @var CartModel $cartModel */
            $cartModel = $app->bootComponent('com_j2commerce')
                ->getMVCFactory()
                ->createModel('Cart', 'Administrator', ['ignore_request' => true]);

            if ($cartModel) {
                $cartItems = $cartModel->getItems();

                if (!\count($cartItems)) {
                    return;
                }

                // Check product categories
                if (\count($couponCategories) > 0) {
                    foreach ($cartItems as $cartItem) {
                        $query = $db->getQuery(true);
                        $productId = (int) $cartItem->product_id;
                        $query->select($db->quoteName('c.catid'))
                            ->from($db->quoteName('#__j2commerce_products', 'p'))
                            ->join('INNER', $db->quoteName('#__content', 'c') . ' ON ' . $db->quoteName('c.id') . ' = ' . $db->quoteName('p.product_source_id'))
                            ->where($db->quoteName('p.j2commerce_product_id') . ' = :productId')
                            ->bind(':productId', $productId, ParameterType::INTEGER);
                        $db->setQuery($query);
                        $catId = $db->loadResult();

                        if ($catId && \in_array($catId, $couponCategories)) {
                            $validForCart = true;
                            break;
                        }
                    }
                }

                // Check products
                if (\count($couponProducts) > 0 && !$validForCart) {
                    foreach ($cartItems as $cartItem) {
                        if (\in_array($cartItem->product_id, $couponProducts)) {
                            $validForCart = true;
                            break;
                        }
                    }
                }

                // Check manufacturers/brands
                if (!empty($this->coupon->brand_ids) && !$validForCart) {
                    $brandIds = explode(',', $this->coupon->brand_ids);

                    foreach ($cartItems as $cartItem) {
                        $manufacturerId = $cartItem->manufacturer_id ?? '';

                        if (!empty($manufacturerId) && \in_array($manufacturerId, $brandIds)) {
                            $validForCart = true;
                            break;
                        }
                    }
                }

                if (!$validForCart) {
                    throw new Exception(Text::_('COM_J2COMMERCE_COUPON_NOT_VALID_FOR_PRODUCT'));
                }
            }
        } catch (\Exception $e) {
            if ($e->getMessage() === Text::_('COM_J2COMMERCE_COUPON_NOT_VALID_FOR_PRODUCT')) {
                throw $e;
            }
            // If cart model fails, skip validation
        }
    }

    /**
     * Validate coupon is valid for products in admin order.
     *
     * @param   object  $order  The order object with getItems() method.
     *
     * @return  void
     *
     * @throws  Exception  If coupon is not valid for any order products.
     *
     * @since   6.0.6
     */
    private function validateAdminProductIds(object $order): void
    {
        $couponProducts = $this->getSelectedProducts();
        $couponCategories = [];
        $validForCart = false;

        if (!empty($this->coupon->product_category)) {
            $couponCategories = explode(',', $this->coupon->product_category);
        }

        // If no restrictions, coupon is valid for all products
        if (!\count($couponCategories) && !\count($couponProducts) && empty($this->coupon->brand_ids)) {
            return;
        }

        $app = Factory::getApplication();

        // Only validate in admin
        if (!$app->isClient('administrator')) {
            return;
        }

        // Get order items
        $orderItems = method_exists($order, 'getItems') ? $order->getItems() : [];

        if (empty($orderItems)) {
            return;
        }

        $db = $this->getDatabase();

        // Check product categories
        if (\count($couponCategories) > 0) {
            foreach ($orderItems as $orderItem) {
                // Load product to get source info
                try {
                    /** @var ProductsModel $productModel */
                    $productModel = $app->bootComponent('com_j2commerce')
                        ->getMVCFactory()
                        ->createModel('Products', 'Administrator', ['ignore_request' => true]);

                    if ($productModel) {
                        $productModel->setState('filter.product_id', $orderItem->product_id);
                        $products = $productModel->getItems();
                        $product = $products[0] ?? null;

                        if ($product && $product->product_source === 'com_content') {
                            $query = $db->getQuery(true);
                            $sourceId = (int) $product->product_source_id;
                            $query->select('catid')
                                ->from($db->quoteName('#__content'))
                                ->where($db->quoteName('id') . ' = :sourceId')
                                ->bind(':sourceId', $sourceId, ParameterType::INTEGER);
                            $db->setQuery($query);
                            $catId = $db->loadResult();

                            if ($catId && \in_array($catId, $couponCategories)) {
                                $validForCart = true;
                                break;
                            }
                        }
                    }
                } catch (\Exception $e) {
                    // Skip this item
                    continue;
                }
            }
        }

        // Check products
        if (\count($couponProducts) > 0 && !$validForCart) {
            foreach ($orderItems as $orderItem) {
                if (\in_array($orderItem->product_id, $couponProducts)) {
                    $validForCart = true;
                    break;
                }
            }
        }

        // Check manufacturers/brands
        if (!empty($this->coupon->brand_ids) && !$validForCart) {
            $brandIds = explode(',', $this->coupon->brand_ids);

            foreach ($orderItems as $orderItem) {
                $manufacturerId = $orderItem->manufacturer_id ?? '';

                if (!empty($manufacturerId) && \in_array($manufacturerId, $brandIds)) {
                    $validForCart = true;
                    break;
                }
            }
        }

        if (!$validForCart) {
            throw new Exception(Text::_('COM_J2COMMERCE_COUPON_NOT_VALID_FOR_PRODUCT'));
        }
    }

    /**
     * Get selected products from coupon.
     *
     * @return  array  Array of product IDs.
     *
     * @since   6.0.6
     */
    public function getSelectedProducts(): array
    {
        $products = [];

        if (!empty($this->coupon->products)) {
            $products = explode(',', $this->coupon->products);
        }

        return $products;
    }

    /**
     * Get coupon by code.
     *
     * @param   string  $code  The coupon code.
     *
     * @return  object|null  The coupon object or null.
     *
     * @since   6.0.6
     */
    public function getCouponByCode(string $code): ?object
    {
        $db = $this->getDatabase();
        $query = $db->getQuery(true);

        $query->select('*')
            ->from($db->quoteName('#__j2commerce_coupons'))
            ->where($db->quoteName('coupon_code') . ' = :code')
            ->where($db->quoteName('enabled') . ' = 1')
            ->bind(':code', $code);

        $db->setQuery($query);

        return $db->loadObject();
    }

    /** Batch variant — single query for multiple codes, keyed by coupon_code. */
    public function getCouponsByCodes(array $codes): array
    {
        $codes = array_filter($codes);

        if (empty($codes)) {
            return [];
        }

        $db    = $this->getDatabase();
        $query = $db->getQuery(true)
            ->select($db->quoteName(['coupon_code', 'value_type', 'value']))
            ->from($db->quoteName('#__j2commerce_coupons'))
            ->whereIn($db->quoteName('coupon_code'), $codes, ParameterType::STRING)
            ->where($db->quoteName('enabled') . ' = 1');

        return $db->setQuery($query)->loadObjectList('coupon_code');
    }

    /**
     * Check coupon type.
     *
     * @param   string|array  $type  Type or array of types to check.
     *
     * @return  bool  True if coupon matches type.
     *
     * @since   6.0.6
     */
    public function isType(string|array $type): bool
    {
        if (!$this->coupon) {
            return false;
        }

        if (\is_array($type)) {
            return \in_array($this->coupon->value_type, $type);
        }

        return $this->coupon->value_type === $type;
    }

    /**
     * Get discount amount for cart item.
     *
     * @param   float        $discountingAmount  The amount to discount.
     * @param   object|null  $cartItem           The cart item.
     * @param   object       $order              The order object.
     * @param   bool         $single             Whether this is for a single item.
     *
     * @return  float  The discount amount.
     *
     * @since   6.0.6
     */
    public function getDiscountAmount(float $discountingAmount, ?object $cartItem, object $order, bool $single = true): float
    {
        $discount = 0.0;
        $cartItemQty = $cartItem === null ? 1 : ($cartItem->orderitem_quantity ?? 1);

        if ($this->isType(['percentage_product', 'percentage_cart'])) {
            // Percentage based discount
            $discount = $this->coupon->value * ($discountingAmount / 100);
        } elseif ($this->isType('fixed_cart') && $cartItem !== null && isset($order->subtotal_ex_tax) && $order->subtotal_ex_tax > 0) {
            // Fixed cart discount - proportionally distributed
            $params = ComponentHelper::getParams('com_j2commerce');
            $actualPrice = ($cartItem->orderitem_price ?? 0) + ($cartItem->orderitem_option_price ?? 0);
            $priceForDiscount = $actualPrice * $cartItemQty;

            if ($params->get('config_including_tax', 0)) {
                $discountPercent = $priceForDiscount / ($order->subtotal ?? 1);
            } else {
                $discountPercent = $priceForDiscount / ($order->subtotal_ex_tax ?? 1);
            }

            $discount = ($this->coupon->value * $discountPercent) / $cartItemQty;
        } elseif ($this->isType('fixed_product')) {
            // Fixed product discount
            $discount = min($this->coupon->value, $discountingAmount);
            $discount = $single ? $discount : $discount * $cartItemQty;
        }

        // Cap discount at discounting amount
        $discount = min($discount, $discountingAmount);

        // Handle limit_usage_to_x_items
        if ($this->isType(['percentage_product', 'fixed_product']) && $discountingAmount > 0) {
            if ($this->limit_usage_to_x_items === '' || $this->limit_usage_to_x_items == 0) {
                $limitUsageQty = $cartItemQty;
            } else {
                $limitUsageQty = min((int) $this->limit_usage_to_x_items, $cartItemQty);
                $this->limit_usage_to_x_items = (string) max(0, (int) $this->limit_usage_to_x_items - $limitUsageQty);
            }

            if ($single) {
                $discount = ($discount * $limitUsageQty) / $cartItemQty;
            } else {
                $discount = ($discount / $cartItemQty) * $limitUsageQty;
            }
        }

        // Handle free shipping
        if (!empty($this->coupon->free_shipping) && method_exists($order, 'allow_free_shipping')) {
            $order->allow_free_shipping();
        }
        J2CommerceHelper::plugin()->event('onJ2CommerceGetCouponDiscountAmount', [
            &$discount,
            $discountingAmount,
            $cartItem,
            $order,
            $this,
            $single,
        ]);

        return $discount;
    }

    /**
     * Get coupon discount types.
     *
     * Third-party developers can add new coupon value types via the
     * onJ2CommerceGetCouponDiscountTypes plugin event.
     *
     * @return  array  Array of discount types.
     *
     * @since   6.0.6
     */
    public function getCouponDiscountTypes(): array
    {
        $list = [
            'percentage_cart'    => Text::_('COM_J2COMMERCE_VALUE_TYPE_PERCENTAGE_CART'),
            'fixed_cart'         => Text::_('COM_J2COMMERCE_VALUE_TYPE_FIXED_CART'),
            'percentage_product' => Text::_('COM_J2COMMERCE_VALUE_TYPE_PERCENTAGE_PRODUCT'),
            'fixed_product'      => Text::_('COM_J2COMMERCE_VALUE_TYPE_FIXED_PRODUCT'),
        ];

        J2CommerceHelper::plugin()->event('GetCouponDiscountTypes', [&$list]);

        return $list;
    }

    /**
     * Get current coupon from session/cart.
     *
     * First checks the cart table for cart_coupon, then falls back to session.
     * This ensures consistency between cart and session storage.
     *
     * @return  string  The coupon code.
     *
     * @since   6.0.6
     */
    public function getCoupon(): string
    {
        return $this->getCouponFromCart();
    }

    /**
     * Check if session/cart has a coupon.
     *
     * Syncs the cart_coupon field to session if found.
     *
     * @return  bool  True if coupon exists.
     *
     * @since   6.0.6
     */
    public function hasCoupon(): bool
    {
        $app = Factory::getApplication();
        $session = $app->getSession();

        try {
            /** @var CartModel $cartModel */
            $cartModel = $app->bootComponent('com_j2commerce')
                ->getMVCFactory()
                ->createModel('Cart', 'Administrator', ['ignore_request' => true]);

            if ($cartModel) {
                $cart = $cartModel->getCart(0, false);

                if (isset($cart->cart_coupon) && !empty($cart->cart_coupon)) {
                    // Sync to session
                    $session->set('coupon', $cart->cart_coupon, 'j2commerce');

                    return true;
                }
            }
        } catch (\Exception $e) {
            // Fall through to session check
        }

        return $session->has('coupon', 'j2commerce');
    }

    /**
     * Set coupon in session and cart table.
     *
     * Updates both the session and the cart table to maintain
     * consistency across the application.
     *
     * @param   string  $couponCode  The coupon code.
     *
     * @return  void
     *
     * @since   6.0.6
     */
    public function setCoupon(string $couponCode = ''): void
    {
        $app = Factory::getApplication();
        $session = $app->getSession();

        // Set in session
        $session->set('coupon', $couponCode, 'j2commerce');

        // Also update cart table
        try {
            /** @var CartModel $cartModel */
            $cartModel = $app->bootComponent('com_j2commerce')
                ->getMVCFactory()
                ->createModel('Cart', 'Administrator', ['ignore_request' => true]);

            if ($cartModel) {
                $cart = $cartModel->getCart(0, false);

                if (isset($cart->j2commerce_cart_id) && !empty($cart->j2commerce_cart_id)) {
                    $db = $this->getDatabase();
                    $query = $db->getQuery(true);
                    $cartId = (int) $cart->j2commerce_cart_id;

                    $query->update($db->quoteName('#__j2commerce_carts'))
                        ->set($db->quoteName('cart_coupon') . ' = :coupon')
                        ->where($db->quoteName('j2commerce_cart_id') . ' = :cartId')
                        ->bind(':coupon', $couponCode)
                        ->bind(':cartId', $cartId, ParameterType::INTEGER);

                    $db->setQuery($query);
                    $db->execute();
                }
            }
        } catch (\Exception $e) {
            // Log error but don't fail - session is already set
        }
    }

    /**
     * Remove coupon from session and cart table.
     *
     * Clears the coupon from both session and cart table
     * to maintain consistency.
     *
     * @return  void
     *
     * @since   6.0.6
     */
    public function removeCoupon(): void
    {
        $app = Factory::getApplication();

        // Clear from session
        $app->getSession()->clear('coupon', 'j2commerce');

        // Also clear from cart table
        try {
            /** @var CartModel $cartModel */
            $cartModel = $app->bootComponent('com_j2commerce')
                ->getMVCFactory()
                ->createModel('Cart', 'Administrator', ['ignore_request' => true]);

            if ($cartModel) {
                $cart = $cartModel->getCart(0, false);

                if (isset($cart->j2commerce_cart_id) && !empty($cart->j2commerce_cart_id)) {
                    $db = $this->getDatabase();
                    $query = $db->getQuery(true);
                    $emptyCoupon = '';
                    $cartId = (int) $cart->j2commerce_cart_id;

                    $query->update($db->quoteName('#__j2commerce_carts'))
                        ->set($db->quoteName('cart_coupon') . ' = :coupon')
                        ->where($db->quoteName('j2commerce_cart_id') . ' = :cartId')
                        ->bind(':coupon', $emptyCoupon)
                        ->bind(':cartId', $cartId, ParameterType::INTEGER);

                    $db->setQuery($query);
                    $db->execute();
                }
            }
        } catch (\Exception $e) {
            // Log error but don't fail - session is already cleared
        }
    }
}
