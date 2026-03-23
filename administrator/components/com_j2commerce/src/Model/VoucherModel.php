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

use Exception;
use J2Commerce\Component\J2commerce\Administrator\Helper\CartHelper;
use J2Commerce\Component\J2commerce\Administrator\Helper\J2CommerceHelper;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Form\Form;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Mail\MailerFactoryInterface;
use Joomla\CMS\MVC\Model\AdminModel;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\Table\Table;
use Joomla\Database\ParameterType;

/**
 * Voucher item model class.
 *
 * @since  6.0.6
 */
class VoucherModel extends AdminModel
{
    /**
     * The type alias for this content type.
     *
     * @var    string
     * @since  6.0.6
     */
    public $typeAlias = 'com_j2commerce.voucher';

    /**
     * The prefix to use with controller messages.
     *
     * @var    string
     * @since  6.0.6
     */
    protected $text_prefix = 'COM_J2COMMERCE_VOUCHER';

    /**
     * The voucher object for validation operations.
     *
     * @var    object|null
     * @since  6.0.6
     */
    public $voucher = null;

    /**
     * Cached voucher history data.
     *
     * @var    array
     * @since  6.0.6
     */
    protected array $history = [];

    /**
     * Override populateState to read 'id' from URL (standard Joomla convention),
     * not 'j2commerce_voucher_id' (the table's column name).
     *
     * @return  void
     *
     * @since   6.0.6
     */
    protected function populateState(): void
    {
        $app = Factory::getApplication();

        $pk = $app->getInput()->getInt('id', 0);
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
        $form = $this->loadForm('com_j2commerce.voucher', 'voucher', ['control' => 'jform', 'load_data' => $loadData]);

        if (empty($form)) {
            return false;
        }

        return $form;
    }

    /**
     * Method to get a table object, load it if necessary.
     *
     * @param   string  $name     The table name. Optional.
     * @param   string  $prefix   The class prefix. Optional.
     * @param   array   $options  Configuration array for model. Optional.
     *
     * @return  Table  A Table object
     *
     * @since   6.0.6
     */
    public function getTable($name = 'Voucher', $prefix = 'Administrator', $options = []): Table
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
        $data = $app->getUserState('com_j2commerce.edit.voucher.data', []);

        if (empty($data)) {
            $data = $this->getItem();
            if ((int) $this->getState($this->getName() . '.id') === 0) {
                $data = is_array($data) ? $data : (array) $data;

                $data['enabled']       = $app->getInput()->getInt('enabled', 1);
                $data['voucher_code']  = $this->generateVoucherCode();
                $data['voucher_value'] = '0';
                $data['voucher_type']  = 'giftcard';

                $now = Factory::getDate()->toSql();
                $data['created_on'] = $now;
                $data['created_by'] = Factory::getApplication()->getIdentity()->id;
            }
        }

        return $data;
    }

    /**
     * Prepare and sanitise the table prior to saving.
     *
     * @param   Table  $table  The Table object
     *
     * @return  void
     *
     * @since   6.0.6
     */
    protected function prepareTable($table): void
    {
        // Trim and uppercase voucher code
        if (!empty($table->voucher_code)) {
            $table->voucher_code = strtoupper(trim($table->voucher_code));
        }

        // Trim email
        if (!empty($table->email_to)) {
            $table->email_to = trim($table->email_to);
        }

        // Trim subject
        if (!empty($table->subject)) {
            $table->subject = trim($table->subject);
        }

        // Set default subject if empty
        if (empty($table->subject)) {
            $table->subject = 'Gift Voucher';
        }

        // Set default voucher type if empty
        if (empty($table->voucher_type)) {
            $table->voucher_type = 'giftcard';
        }

        // Ensure voucher_value is numeric
        if (isset($table->voucher_value)) {
            $table->voucher_value = (float) $table->voucher_value;
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
        // Handle published/enabled alias
        if (isset($data['published'])) {
            $data['enabled'] = $data['published'];
            unset($data['published']);
        }

        // Handle date fields - convert empty strings to null
        if (isset($data['valid_from']) && empty($data['valid_from'])) {
            $data['valid_from'] = null;
        }

        if (isset($data['valid_to']) && empty($data['valid_to'])) {
            $data['valid_to'] = null;
        }

        // Set created fields for new items
        if (empty($data['j2commerce_voucher_id']) || $data['j2commerce_voucher_id'] == 0) {
            $data['created_on'] = Factory::getDate()->toSql();
            $data['created_by'] = Factory::getApplication()->getIdentity()->id;
        }

        // Include the content plugins for the on save events
        PluginHelper::importPlugin('content');

        // Alter the voucher code for save as copy
        $app = Factory::getApplication();
        if ($app->getInput()->get('task') == 'save2copy') {
            $origTable = clone $this->getTable();
            $origTable->load($app->getInput()->getInt('j2commerce_voucher_id'));

            if ($data['voucher_code'] == $origTable->voucher_code) {
                $data['voucher_code'] = $this->generateNewVoucherCode($data['voucher_code']);
            }

            $data['enabled'] = 0;
        }

        if (parent::save($data)) {
            // Set the ID for redirect after save
            $table = $this->getTable();
            if ($table && isset($table->j2commerce_voucher_id)) {
                $this->setState($this->getName() . '.j2commerce_voucher_id', $table->j2commerce_voucher_id);
            }

            return true;
        }

        return false;
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
        // Include the content plugins for the change of state event
        PluginHelper::importPlugin('content');

        return parent::publish($pks, $value);
    }

    /**
     * Method to adjust the ordering of a row.
     *
     * @param   array|int  $pks    The ID of the primary key(s) to move.
     * @param   integer    $delta  Increment, usually +1 or -1
     *
     * @return  boolean|null  False on failure or error, true otherwise.
     *
     * @since   6.0.6
     */
    public function reorder($pks, $delta = 0): ?bool
    {
        $table  = $this->getTable();
        $pks    = (array) $pks;
        $result = true;

        $allowed = true;

        foreach ($pks as $i => $pk) {
            $table->reset();

            if ($table->load($pk)) {
                // Access checks
                if (!$this->canEditState($table)) {
                    unset($pks[$i]);
                    $allowed = false;

                    continue;
                }

                $where = [];

                if (!$table->move($delta, $where)) {
                    throw new \RuntimeException($table->getError());
                }
            } else {
                throw new \RuntimeException($table->getError());
            }
        }

        if ($allowed === false && empty($pks)) {
            $result = null;
        }

        // Clear the component's cache
        $this->cleanCache();

        return $result;
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

        // Access checks
        if (!$user->authorise('core.create', 'com_j2commerce')) {
            throw new Exception(Text::_('JERROR_CORE_CREATE_NOT_PERMITTED'));
        }

        $table = $this->getTable();

        foreach ($pks as $pk) {
            if ($table->load($pk, true)) {
                // Reset the id to create a new record
                $table->j2commerce_voucher_id = 0;
                $table->enabled = 0;
                $table->voucher_code = $this->generateNewVoucherCode($table->voucher_code);

                if (!$table->check() || !$table->store()) {
                    throw new Exception($table->getError());
                }
            } else {
                throw new Exception($table->getError());
            }
        }

        // Clear options cache
        $this->cleanCache();

        return true;
    }

    /**
     * Send a single voucher email.
     *
     * @param   int  $pk  The voucher ID.
     *
     * @return  bool  True on success, false on failure.
     *
     * @since   6.0.6
     */
    public function send(int $pk): bool
    {
        $item = $this->getItem($pk);

        if (!$item || empty($item->email_to)) {
            return false;
        }

        $mailer = Factory::getContainer()->get(MailerFactoryInterface::class)->createMailer();

        $mailer->setSender([
            Factory::getApplication()->get('mailfrom'),
            Factory::getApplication()->get('fromname')
        ]);
        $mailer->setSubject($item->subject);
        $mailer->isHtml(true);
        $mailer->addRecipient($item->email_to);
        $mailer->setBody($item->email_body);

        return $mailer->Send() === true;
    }

    /**
     * Send voucher emails to multiple recipients.
     *
     * @param   array  $cids  Array of voucher IDs to send.
     *
     * @return  bool  True on success, false if any failed.
     *
     * @since   6.0.6
     */
    public function sendVouchers(array $cids): bool
    {
        $config = Factory::getApplication()->getConfig();
        $emailHelper = J2CommerceHelper::email();

        $mailfrom = $config->get('mailfrom');
        $fromname = $config->get('fromname');

        $failed = 0;

        foreach ($cids as $cid) {
            $voucherTable = $this->getTable();
            $voucherTable->load($cid);

            $mailer = Factory::getContainer()->get(MailerFactoryInterface::class)->createMailer();
            $mailer->setSender([$mailfrom, $fromname]);
            $mailer->isHtml(true);
            $mailer->addRecipient($voucherTable->email_to);
            $mailer->setSubject($voucherTable->subject);

            // Parse inline images before setting the body
            $emailHelper->processInlineImages($voucherTable->email_body, $mailer);
            $mailer->setBody($voucherTable->email_body);

            // Allow plugins to modify
            J2CommerceHelper::plugin()->event('BeforeSendVoucher', [$voucherTable, &$mailer]);

            if ($mailer->Send() !== true) {
                $this->setError(Text::sprintf('COM_J2COMMERCE_VOUCHERS_SENDING_FAILED_TO_RECEIPIENT', $voucherTable->email_to));
                $failed++;
            }

            J2CommerceHelper::plugin()->event('AfterSendVoucher', [$voucherTable, &$mailer]);
            $mailer = null;
        }

        return $failed === 0;
    }

    /**
     * Generate a unique voucher code.
     *
     * @param   int  $length  Length of the code.
     *
     * @return  string  The voucher code.
     *
     * @since   6.0.6
     */
    protected function generateVoucherCode(int $length = 8): string
    {
        $characters       = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $charactersLength = strlen($characters);
        $randomString     = '';

        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[random_int(0, $charactersLength - 1)];
        }

        // Check if the code already exists
        $table = $this->getTable();
        if ($table->load(['voucher_code' => $randomString])) {
            // If it exists, generate a new one
            return $this->generateVoucherCode($length);
        }

        return $randomString;
    }

    /**
     * Generate a new voucher code for copy.
     *
     * @param   string  $voucherCode  The original voucher code.
     *
     * @return  string  The new voucher code.
     *
     * @since   6.0.6
     */
    protected function generateNewVoucherCode(string $voucherCode): string
    {
        $table   = $this->getTable();
        $newCode = $voucherCode;

        $i = 1;
        while ($table->load(['voucher_code' => $newCode])) {
            $newCode = $voucherCode . '_' . $i;
            $i++;
            $table->reset();
        }

        return $newCode;
    }

    /**
     * Get voucher usage history with order details.
     *
     * @param   int|null  $voucherId  The voucher ID.
     *
     * @return  array  Array of voucher usage data.
     *
     * @since   6.0.6
     */
    public function getVoucherHistory(?int $voucherId = null): array
    {
        if ($voucherId === null) {
            $voucherId = (int) $this->getState($this->getName() . '.id');
        }

        if (!$voucherId) {
            return [];
        }

        $params = $this->getState('params');
        $db     = $this->getDatabase();
        $query  = $db->getQuery(true);

        $query->select($db->quoteName([
            'o.j2commerce_order_id',
            'o.order_id',
            'o.user_email',
            'o.created_on',
            'o.invoice_number',
            'o.invoice_prefix'
        ]));

        if ($params && $params->get('config_including_tax', 0)) {
            $query->select('ROUND(SUM(' . $db->quoteName('od.discount_amount') . ') + SUM(' . $db->quoteName('od.discount_tax') . '), 2) AS ' . $db->quoteName('total'));
        } else {
            $query->select('ROUND(SUM(' . $db->quoteName('od.discount_amount') . '), 2) AS ' . $db->quoteName('total'));
        }

        $query->from($db->quoteName('#__j2commerce_orderdiscounts', 'od'))
            ->join(
                'LEFT',
                $db->quoteName('#__j2commerce_orders', 'o'),
                $db->quoteName('od.order_id') . ' = ' . $db->quoteName('o.order_id')
            )
            ->where($db->quoteName('o.order_state_id') . ' != 5')
            ->where($db->quoteName('od.discount_entity_id') . ' = :voucherId')
            ->where($db->quoteName('od.discount_type') . ' = :discountType')
            ->group($db->quoteName('od.discount_entity_id'))
            ->order($db->quoteName('o.created_on') . ' DESC')
            ->bind(':voucherId', $voucherId, ParameterType::INTEGER)
            ->bind(':discountType', $discountType = 'voucher');

        try {
            $db->setQuery($query);

            return $db->loadObjectList() ?: [];
        } catch (\RuntimeException $e) {
            $this->setError($e->getMessage());

            return [];
        }
    }

    /**
     * Get the total amount used from a voucher.
     *
     * @param   int  $voucherId  The voucher ID.
     *
     * @return  float|null  The total used amount or null.
     *
     * @since   6.0.6
     */
    public function getVoucherHistoryTotal(int $voucherId): ?float
    {
        if (!isset($this->history[$voucherId])) {
            $db     = $this->getDatabase();
            $query  = $db->getQuery(true);
            $config = J2CommerceHelper::config();

            if ($config->get('config_including_tax', 0)) {
                $query->select('ROUND(SUM(' . $db->quoteName('discount_amount') . ') + SUM(' . $db->quoteName('discount_tax') . '), 2) AS ' . $db->quoteName('total'));
            } else {
                $query->select('ROUND(SUM(' . $db->quoteName('discount_amount') . '), 2) AS ' . $db->quoteName('total'));
            }

            $discountType = 'voucher';

            $query->from($db->quoteName('#__j2commerce_orderdiscounts', 'od'))
                ->join(
                    'LEFT',
                    $db->quoteName('#__j2commerce_orders', 'o'),
                    $db->quoteName('od.order_id') . ' = ' . $db->quoteName('o.order_id')
                )
                ->where($db->quoteName('o.order_state_id') . ' != 5')
                ->where($db->quoteName('od.discount_entity_id') . ' = :voucherId')
                ->where($db->quoteName('od.discount_type') . ' = :discountType')
                ->group($db->quoteName('od.discount_entity_id'))
                ->bind(':voucherId', $voucherId, ParameterType::INTEGER)
                ->bind(':discountType', $discountType);

            $db->setQuery($query);
            $this->history[$voucherId] = $db->loadResult();
        }

        return $this->history[$voucherId] !== null ? (float) $this->history[$voucherId] : null;
    }

    /**
     * Get the admin voucher history total, optionally excluding an order.
     *
     * @param   int     $voucherId  The voucher ID.
     * @param   string  $orderId    Optional order ID to exclude.
     *
     * @return  float|null  The total used amount or null.
     *
     * @since   6.0.6
     */
    public function getAdminVoucherHistoryTotal(int $voucherId, string $orderId = ''): ?float
    {
        $cacheKey = $voucherId . '_' . $orderId;

        if (!isset($this->history[$cacheKey])) {
            $db     = $this->getDatabase();
            $query  = $db->getQuery(true);
            $config = J2CommerceHelper::config();

            if ($config->get('config_including_tax', 0)) {
                $query->select('ROUND(SUM(' . $db->quoteName('discount_amount') . ') + SUM(' . $db->quoteName('discount_tax') . '), 2) AS ' . $db->quoteName('total'));
            } else {
                $query->select('ROUND(SUM(' . $db->quoteName('discount_amount') . '), 2) AS ' . $db->quoteName('total'));
            }

            $discountType = 'voucher';

            $query->from($db->quoteName('#__j2commerce_orderdiscounts', 'od'))
                ->join(
                    'LEFT',
                    $db->quoteName('#__j2commerce_orders', 'o'),
                    $db->quoteName('od.order_id') . ' = ' . $db->quoteName('o.order_id')
                )
                ->where($db->quoteName('o.order_state_id') . ' != 5')
                ->where($db->quoteName('od.discount_entity_id') . ' = :voucherId')
                ->where($db->quoteName('od.discount_type') . ' = :discountType')
                ->group($db->quoteName('od.discount_entity_id'))
                ->bind(':voucherId', $voucherId, ParameterType::INTEGER)
                ->bind(':discountType', $discountType);

            if ($orderId) {
                $query->where($db->quoteName('o.order_id') . ' != :orderId')
                    ->bind(':orderId', $orderId);
            }

            $db->setQuery($query);
            $this->history[$cacheKey] = $db->loadResult();
        }

        return $this->history[$cacheKey] !== null ? (float) $this->history[$cacheKey] : null;
    }

    /**
     * Remove voucher from session and cart.
     *
     * @return  void
     *
     * @since   6.0.6
     */
    public function removeVoucher(): void
    {
        $session = Factory::getApplication()->getSession();
        $session->clear('voucher', 'j2commerce');

        // Clear voucher from cart table
        $cartHelper = CartHelper::getInstance();
        $cartTable  = $cartHelper->getCart();

        if (isset($cartTable->j2commerce_cart_id) && !empty($cartTable->j2commerce_cart_id)) {
            $db    = $this->getDatabase();
            $query = $db->getQuery(true);

            $emptyVoucher = '';
            $cartId = (int) $cartTable->j2commerce_cart_id;

            $query->update($db->quoteName('#__j2commerce_carts'))
                ->set($db->quoteName('cart_voucher') . ' = :emptyVoucher')
                ->where($db->quoteName('j2commerce_cart_id') . ' = :cartId')
                ->bind(':emptyVoucher', $emptyVoucher)
                ->bind(':cartId', $cartId, ParameterType::INTEGER);

            $db->setQuery($query);
            $db->execute();
        }
    }

    /**
     * Validate the voucher for checkout.
     *
     * @return  bool  True if valid, false otherwise.
     *
     * @since   6.0.6
     */
    public function isValid(): bool
    {
        try {
            $this->validateEnabled();
            $this->validateExists();
            $this->validateUsageLimit();
            $this->validateExpiryDate();

            // Allow plugins to run their own validation
            $results = J2CommerceHelper::plugin()->eventWithArray('VoucherIsValid', [$this]);

            if (\in_array(false, $results, false)) {
                throw new Exception(Text::_('COM_J2COMMERCE_VOUCHER_NOT_APPLICABLE'));
            }
        } catch (Exception $e) {
            $this->setError($e->getMessage());
            Factory::getApplication()->enqueueMessage($e->getMessage(), 'warning');
            $this->removeVoucher();

            return false;
        }

        return true;
    }

    /**
     * Validate the voucher for admin order editing.
     *
     * @param   object  $order  The order object.
     *
     * @return  bool  True if valid, false otherwise.
     *
     * @since   6.0.6
     */
    public function isAdminValid(object $order): bool
    {
        try {
            $this->validateEnabled();
            $this->validateExists();
            $this->validateAdminUsageLimit($order);
            $this->validateExpiryDate();

            // Allow plugins to run their own validation
            $results = J2CommerceHelper::plugin()->eventWithArray('VoucherIsValid', [$this]);

            if (\in_array(false, $results, false)) {
                throw new Exception(Text::_('COM_J2COMMERCE_VOUCHER_NOT_APPLICABLE'));
            }
        } catch (Exception $e) {
            $this->setError($e->getMessage());
            Factory::getApplication()->enqueueMessage($e->getMessage(), 'warning');
            $this->removeVoucher();

            return false;
        }

        return true;
    }

    /**
     * Validate that vouchers are enabled in configuration.
     *
     * @return  void
     *
     * @throws  Exception  If vouchers are not enabled.
     *
     * @since   6.0.6
     */
    private function validateEnabled(): void
    {
        $params = J2CommerceHelper::config();

        if ($params->get('enable_voucher', 0) == 0) {
            throw new Exception(Text::_('COM_J2COMMERCE_VOUCHER_NOT_ENABLED'));
        }
    }

    /**
     * Validate that the voucher exists.
     *
     * @return  void
     *
     * @throws  Exception  If voucher does not exist.
     *
     * @since   6.0.6
     */
    private function validateExists(): void
    {
        if (!$this->voucher) {
            throw new Exception(Text::_('COM_J2COMMERCE_VOUCHER_DOES_NOT_EXIST'));
        }
    }

    /**
     * Validate the voucher usage limit.
     *
     * @return  void
     *
     * @throws  Exception  If usage limit has been reached.
     *
     * @since   6.0.6
     */
    private function validateUsageLimit(): void
    {
        $total  = $this->getVoucherHistoryTotal($this->voucher->j2commerce_voucher_id);
        $amount = $this->voucher->voucher_value - ($total ?? 0);

        if ($amount <= 0) {
            throw new Exception(Text::_('COM_J2COMMERCE_VOUCHER_USAGE_LIMIT_HAS_REACHED'));
        }
    }

    /**
     * Validate the admin voucher usage limit, excluding the current order.
     *
     * @param   object  $order  The order object.
     *
     * @return  void
     *
     * @throws  Exception  If usage limit has been reached.
     *
     * @since   6.0.6
     */
    private function validateAdminUsageLimit(object $order): void
    {
        $orderId = $order->order_id ?? '';
        $total   = $this->getAdminVoucherHistoryTotal($this->voucher->j2commerce_voucher_id, $orderId);
        $amount  = $this->voucher->voucher_value - ($total ?? 0);

        if ($amount <= 0) {
            throw new Exception(Text::_('COM_J2COMMERCE_VOUCHER_USAGE_LIMIT_HAS_REACHED'));
        }
    }

    /**
     * Validate the voucher expiry date.
     *
     * @return  void
     *
     * @throws  Exception  If voucher has expired or is not yet valid.
     *
     * @since   6.0.6
     */
    private function validateExpiryDate(): void
    {
        $db       = $this->getDatabase();
        $nullDate = $db->getNullDate();
        $tz       = Factory::getApplication()->getConfig()->get('offset');
        $now      = Factory::getDate('now', $tz)->format('Y-m-d', true);

        $validFrom = Factory::getDate($this->voucher->valid_from, $tz)->format('Y-m-d', true);
        $validTo   = Factory::getDate($this->voucher->valid_to, $tz)->format('Y-m-d', true);

        $fromValid = ($this->voucher->valid_from == $nullDate || empty($this->voucher->valid_from) || $validFrom <= $now);
        $toValid   = ($this->voucher->valid_to == $nullDate || empty($this->voucher->valid_to) || $validTo >= $now);

        if (!$fromValid || !$toValid) {
            throw new Exception(Text::_('COM_J2COMMERCE_VOUCHER_HAS_EXPIRED'));
        }
    }

    /**
     * Calculate the discount amount for a cart item.
     *
     * @param   float   $price     The item price.
     * @param   object  $cartitem  The cart item object.
     * @param   object  $order     The order object.
     * @param   bool    $single    Whether to calculate for a single item.
     *
     * @return  float  The discount amount.
     *
     * @since   6.0.6
     */
    public function getDiscountAmount(float $price, object $cartitem, object $order, bool $single = true): float
    {
        $platform = J2CommerceHelper::platform();

        if ($platform->isClient('administrator')) {
            $voucherHistoryTotal = $this->getAdminVoucherHistoryTotal(
                $this->voucher->j2commerce_voucher_id,
                $order->order_id ?? ''
            );
        } else {
            $voucherHistoryTotal = $this->getVoucherHistoryTotal($this->voucher->j2commerce_voucher_id);
        }

        if ($voucherHistoryTotal) {
            $amount = $this->voucher->voucher_value - $voucherHistoryTotal;
        } else {
            $amount = $this->voucher->voucher_value;
        }

        // Calculate discount percentage based on item proportion
        $params        = J2CommerceHelper::config();
        $productHelper = J2CommerceHelper::product();
        $cartItemQty   = $cartitem->orderitem_quantity;

        $discountPercent = 0;
        $actualPrice     = ($cartitem->orderitem_price + $cartitem->orderitem_option_price);

        if ($params->get('config_including_tax', 0)) {
            $priceForDiscount = $productHelper->get_price_including_tax(
                ($actualPrice * $cartItemQty),
                $cartitem->orderitem_taxprofile_id
            );
            $discountPercent = ($priceForDiscount) / $order->subtotal;
        } else {
            $priceForDiscount = $productHelper->get_price_excluding_tax(
                ($actualPrice * $cartItemQty),
                $cartitem->orderitem_taxprofile_id
            );
            $discountPercent = ($priceForDiscount) / $order->subtotal_ex_tax;
        }

        $discount = ($amount * $discountPercent) / $cartItemQty;

        // Allow plugins to modify the discount
        J2CommerceHelper::plugin()->event('GetVoucherDiscountAmount', [$discount, $price, $cartitem, $order, $this, $single]);

        return $discount;
    }

    /**
     * Calculate the admin discount amount for a price.
     *
     * @param   float  $price  The price.
     *
     * @return  float  The discount amount.
     *
     * @since   6.0.6
     */
    public function getAdminDiscountAmount(float $price): float
    {
        $voucherHistoryTotal = $this->getVoucherHistoryTotal($this->voucher->j2commerce_voucher_id);

        if ($voucherHistoryTotal) {
            $amount = $this->voucher->voucher_value - $voucherHistoryTotal;
        } else {
            $amount = $this->voucher->voucher_value;
        }

        if ($price > $amount) {
            $discount = $amount;
        } else {
            $discount = $amount - $price;
        }

        return $discount;
    }

    /**
     * Get a voucher by its code.
     *
     * @param   string  $code  The voucher code.
     *
     * @return  object|null  The voucher object or null if not found.
     *
     * @since   6.0.6
     */
    public function getVoucherByCode(string $code): ?object
    {
        $db    = $this->getDatabase();
        $query = $db->getQuery(true);

        $enabled = 1;

        $query->select('*')
            ->from($db->quoteName('#__j2commerce_vouchers'))
            ->where($db->quoteName('voucher_code') . ' = :code')
            ->where($db->quoteName('enabled') . ' = :enabled')
            ->bind(':code', $code)
            ->bind(':enabled', $enabled, ParameterType::INTEGER);

        $db->setQuery($query);

        return $db->loadObject() ?: null;
    }

    /**
     * Get voucher information by code with validation.
     *
     * @param   string  $code  The voucher code.
     *
     * @return  object|false  The voucher info object or false on failure.
     *
     * @since   6.0.6
     */
    public function getVoucher(string $code): object|false
    {
        $status = true;

        $voucher = $this->getVoucherByCode($code);

        if (!$voucher) {
            return false;
        }

        // Check for remaining value
        $voucherTotal = $this->getVoucherHistoryTotal($voucher->j2commerce_voucher_id);

        if ($voucherTotal !== null) {
            $amount = $voucher->voucher_value - $voucherTotal;
        } else {
            $amount = $voucher->voucher_value;
        }

        if ($amount <= 0) {
            return false;
        }

        return (object) [
            'voucher_id'       => $voucher->j2commerce_voucher_id,
            'voucher_code'     => $voucher->voucher_code,
            'voucher_to_email' => $voucher->email_to,
            'message'          => $voucher->email_body,
            'amount'           => $amount,
            'enabled'          => $voucher->enabled,
            'created_on'       => $voucher->created_on,
        ];
    }

    /**
     * Set voucher in session and cart.
     *
     * @param   string  $postVoucher  The voucher code.
     *
     * @return  void
     *
     * @since   6.0.6
     */
    public function setVoucher(string $postVoucher): void
    {
        J2CommerceHelper::plugin()->event('BeforeSetVoucher', [&$postVoucher]);

        $session = Factory::getApplication()->getSession();
        $session->set('voucher', $postVoucher, 'j2commerce');

        // Update cart table
        $cartHelper = CartHelper::getInstance();
        $cartTable  = $cartHelper->getCart();

        if (isset($cartTable->j2commerce_cart_id) && !empty($cartTable->j2commerce_cart_id)) {
            $db    = $this->getDatabase();
            $query = $db->getQuery(true);

            $query->update($db->quoteName('#__j2commerce_carts'))
                ->set($db->quoteName('cart_voucher') . ' = :voucher')
                ->where($db->quoteName('j2commerce_cart_id') . ' = :cartId')
                ->bind(':voucher', $postVoucher)
                ->bind(':cartId', $cartTable->j2commerce_cart_id, ParameterType::INTEGER);

            $db->setQuery($query);
            $db->execute();
        }

        J2CommerceHelper::plugin()->event('AfterSetVoucher', [&$postVoucher, &$cartTable]);
    }

    /**
     * Get voucher from session or cart.
     *
     * @return  string  The voucher code.
     *
     * @since   6.0.6
     */
    public function getVoucherCode(): string
    {
        $session    = Factory::getApplication()->getSession();
        $cartHelper = CartHelper::getInstance();
        $cartTable  = $cartHelper->getCart();

        if (isset($cartTable->cart_voucher) && !empty($cartTable->cart_voucher)) {
            $session->set('voucher', $cartTable->cart_voucher, 'j2commerce');
            $voucherCode = $cartTable->cart_voucher;
        } else {
            $voucherCode = $session->get('voucher', '', 'j2commerce');
        }

        return $voucherCode;
    }

    /**
     * Check if a voucher is set in session.
     *
     * @return  bool  True if voucher exists in session.
     *
     * @since   6.0.6
     */
    public function hasVoucher(): bool
    {
        $session    = Factory::getApplication()->getSession();
        $cartHelper = CartHelper::getInstance();
        $cartTable  = $cartHelper->getCart();

        if (isset($cartTable->cart_voucher) && !empty($cartTable->cart_voucher)) {
            $session->set('voucher', $cartTable->cart_voucher, 'j2commerce');
        }

        return $session->has('voucher', 'j2commerce');
    }

    // =========================================================================
    // Legacy method aliases for backward compatibility
    // =========================================================================

    /**
     * @deprecated  Use removeVoucher() instead
     * @since       6.0.6
     */
    public function remove_voucher(): void
    {
        $this->removeVoucher();
    }

    /**
     * @deprecated  Use getVoucherHistoryTotal() instead
     * @since       6.0.6
     */
    public function get_voucher_history(int $voucherId): ?float
    {
        return $this->getVoucherHistoryTotal($voucherId);
    }

    /**
     * @deprecated  Use getAdminVoucherHistoryTotal() instead
     * @since       6.0.6
     */
    public function get_admin_voucher_history(int $voucherId, string $orderId = ''): ?float
    {
        return $this->getAdminVoucherHistoryTotal($voucherId, $orderId);
    }

    /**
     * @deprecated  Use isValid() instead
     * @since       6.0.6
     */
    public function is_valid(): bool
    {
        return $this->isValid();
    }

    /**
     * @deprecated  Use isAdminValid() instead
     * @since       6.0.6
     */
    public function is_admin_valid(object $order): bool
    {
        return $this->isAdminValid($order);
    }

    /**
     * @deprecated  Use getDiscountAmount() instead
     * @since       6.0.6
     */
    public function get_discount_amount(float $price, object $cartitem, object $order, bool $single = true): float
    {
        return $this->getDiscountAmount($price, $cartitem, $order, $single);
    }

    /**
     * @deprecated  Use getAdminDiscountAmount() instead
     * @since       6.0.6
     */
    public function get_admin_discount_amount(float $price): float
    {
        return $this->getAdminDiscountAmount($price);
    }

    /**
     * @deprecated  Use setVoucher() instead
     * @since       6.0.6
     */
    public function set_voucher(string $postVoucher): void
    {
        $this->setVoucher($postVoucher);
    }

    /**
     * @deprecated  Use getVoucherCode() instead
     * @since       6.0.6
     */
    public function get_voucher(): string
    {
        return $this->getVoucherCode();
    }

    /**
     * @deprecated  Use hasVoucher() instead
     * @since       6.0.6
     */
    public function has_voucher(): bool
    {
        return $this->hasVoucher();
    }
}
