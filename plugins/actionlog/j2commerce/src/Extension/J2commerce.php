<?php

/**
 * @package     J2Commerce
 * @subpackage  plg_actionlog_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace J2Commerce\Plugin\Actionlog\J2commerce\Extension;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Mail\Exception\MailDisabledException;
use Joomla\CMS\User\UserFactoryAwareTrait;
use Joomla\Component\Actionlogs\Administrator\Helper\ActionlogsHelper;
use Joomla\Component\Actionlogs\Administrator\Plugin\ActionLogPlugin;
use Joomla\Database\DatabaseAwareTrait;
use Joomla\Database\ParameterType;
use Joomla\Event\Event;
use Joomla\Event\SubscriberInterface;

final class J2commerce extends ActionLogPlugin implements SubscriberInterface
{
    use DatabaseAwareTrait;
    use UserFactoryAwareTrait;

    private const EXTENSION = 'com_j2commerce';

    private const PRIORITY_BROWSE   = 1;
    private const PRIORITY_ACTION   = 2;
    private const PRIORITY_SUCCESS  = 3;
    private const PRIORITY_WARNING  = 4;
    private const PRIORITY_CRITICAL = 5;

    private static bool $paymentSectionLogged = false;

    public static function getSubscribedEvents(): array
    {
        return [
            // Cart events
            'onJ2CommerceAfterAddToCart'          => 'onAfterAddToCart',
            'onJ2CommerceAfterRemoveFromCart'     => 'onAfterRemoveFromCart',
            'onJ2CommerceAfterUpdateCartQuantity' => 'onAfterUpdateCartQuantity',
            'onJ2CommerceAfterClearCart'          => 'onAfterClearCart',
            // Checkout funnel events
            'onJ2CommerceCheckoutStart'            => 'onCheckoutStart',
            'onJ2CommerceCheckoutLogin'            => 'onCheckoutLogin',
            'onJ2CommerceCheckoutBillingComplete'  => 'onCheckoutBillingComplete',
            'onJ2CommerceCheckoutShippingComplete' => 'onCheckoutShippingComplete',
            'onJ2CommerceGetPaymentPlugins'        => 'onGetPaymentPlugins',
            'onJ2CommerceAfterOrderValidate'       => 'onAfterOrderValidate',
            'onJ2CommerceAfterPayment'             => 'onAfterPayment',
            // Order admin events
            'onJ2CommerceAfterOrderStatusChange' => 'onAfterOrderStatusChange',
        ];
    }

    // ---------------------------------------------------------------
    // Cart event handlers
    // ---------------------------------------------------------------

    public function onAfterAddToCart(Event $event): void
    {
        if (!$this->params->get('log_cart_events', 1)) {
            return;
        }

        $args        = $this->extractArgs($event, 3);
        $productName = (string) ($args[0] ?? '');
        $productId   = (int) ($args[1] ?? 0);
        $quantity    = (int) ($args[2] ?? 1);

        $langKey = 'PLG_ACTIONLOG_J2COMMERCE_CART_ADD';
        $message = $this->buildMessage($langKey, [
            'product_name' => $productName,
            'product_id'   => $productId,
            'quantity'     => $quantity,
        ]);

        $this->addLog([$message], $langKey, self::EXTENSION, $this->getUserId());
        $this->checkEmailNotification($langKey, self::PRIORITY_BROWSE, $message);
    }

    public function onAfterRemoveFromCart(Event $event): void
    {
        if (!$this->params->get('log_cart_events', 1)) {
            return;
        }

        $args        = $this->extractArgs($event, 2);
        $productName = (string) ($args[0] ?? 'Unknown Product');
        $productId   = (int) ($args[1] ?? 0);

        $langKey = 'PLG_ACTIONLOG_J2COMMERCE_CART_REMOVE';
        $message = $this->buildMessage($langKey, [
            'product_name' => $productName,
            'product_id'   => $productId,
        ]);

        $this->addLog([$message], $langKey, self::EXTENSION, $this->getUserId());
        $this->checkEmailNotification($langKey, self::PRIORITY_BROWSE, $message);
    }

    public function onAfterUpdateCartQuantity(Event $event): void
    {
        if (!$this->params->get('log_cart_events', 1)) {
            return;
        }

        $args        = $this->extractArgs($event, 3);
        $productName = (string) ($args[0] ?? 'Unknown Product');
        $oldQty      = (int) ($args[1] ?? 0);
        $newQty      = (int) ($args[2] ?? 0);

        $langKey = 'PLG_ACTIONLOG_J2COMMERCE_CART_UPDATE_QTY';
        $message = $this->buildMessage($langKey, [
            'product_name' => $productName,
            'old_qty'      => $oldQty,
            'new_qty'      => $newQty,
        ]);

        $this->addLog([$message], $langKey, self::EXTENSION, $this->getUserId());
        $this->checkEmailNotification($langKey, self::PRIORITY_BROWSE, $message);
    }

    public function onAfterClearCart(Event $event): void
    {
        if (!$this->params->get('log_cart_events', 1)) {
            return;
        }

        $langKey = 'PLG_ACTIONLOG_J2COMMERCE_CART_CLEAR';
        $message = $this->buildMessage($langKey);

        $this->addLog([$message], $langKey, self::EXTENSION, $this->getUserId());
        $this->checkEmailNotification($langKey, self::PRIORITY_BROWSE, $message);
    }

    // ---------------------------------------------------------------
    // Checkout funnel event handlers
    // ---------------------------------------------------------------

    public function onCheckoutStart(Event $event): void
    {
        if (!$this->params->get('log_checkout_events', 1)) {
            return;
        }

        $langKey = 'PLG_ACTIONLOG_J2COMMERCE_CHECKOUT_START';
        $message = $this->buildMessage($langKey);

        $this->addLog([$message], $langKey, self::EXTENSION, $this->getUserId());
        $this->checkEmailNotification($langKey, self::PRIORITY_ACTION, $message);
    }

    public function onCheckoutLogin(Event $event): void
    {
        if (!$this->params->get('log_checkout_events', 1)) {
            return;
        }

        $langKey = 'PLG_ACTIONLOG_J2COMMERCE_CHECKOUT_LOGIN';
        $message = $this->buildMessage($langKey);

        $this->addLog([$message], $langKey, self::EXTENSION, $this->getUserId());
        $this->checkEmailNotification($langKey, self::PRIORITY_ACTION, $message);
    }

    public function onCheckoutBillingComplete(Event $event): void
    {
        if (!$this->params->get('log_checkout_events', 1)) {
            return;
        }

        $langKey = 'PLG_ACTIONLOG_J2COMMERCE_CHECKOUT_BILLING';
        $message = $this->buildMessage($langKey);

        $this->addLog([$message], $langKey, self::EXTENSION, $this->getUserId());
        $this->checkEmailNotification($langKey, self::PRIORITY_ACTION, $message);
    }

    public function onCheckoutShippingComplete(Event $event): void
    {
        if (!$this->params->get('log_checkout_events', 1)) {
            return;
        }

        $langKey = 'PLG_ACTIONLOG_J2COMMERCE_CHECKOUT_SHIPPING';
        $message = $this->buildMessage($langKey);

        $this->addLog([$message], $langKey, self::EXTENSION, $this->getUserId());
        $this->checkEmailNotification($langKey, self::PRIORITY_ACTION, $message);
    }

    public function onGetPaymentPlugins(Event $event): void
    {
        if (!$this->params->get('log_checkout_events', 1)) {
            return;
        }

        if (self::$paymentSectionLogged) {
            return;
        }

        self::$paymentSectionLogged = true;

        $langKey = 'PLG_ACTIONLOG_J2COMMERCE_CHECKOUT_PAYMENT';
        $message = $this->buildMessage($langKey);

        $this->addLog([$message], $langKey, self::EXTENSION, $this->getUserId());
        $this->checkEmailNotification($langKey, self::PRIORITY_ACTION, $message);
    }

    public function onAfterOrderValidate(Event $event): void
    {
        if (!$this->params->get('log_checkout_events', 1)) {
            return;
        }

        $langKey = 'PLG_ACTIONLOG_J2COMMERCE_CHECKOUT_CONFIRM';
        $message = $this->buildMessage($langKey);

        $this->addLog([$message], $langKey, self::EXTENSION, $this->getUserId());
        $this->checkEmailNotification($langKey, self::PRIORITY_SUCCESS, $message);
    }

    // ---------------------------------------------------------------
    // Order event handlers
    // ---------------------------------------------------------------

    public function onAfterPayment(Event $event): void
    {
        if (!$this->params->get('log_order_events', 1)) {
            return;
        }

        $args       = $this->extractArgs($event, 1);
        $orderTable = $args[0] ?? null;

        if ($orderTable === null) {
            return;
        }

        $orderId      = $orderTable->order_id ?? '';
        $orderStateId = (int) ($orderTable->order_state_id ?? 0);
        $statusName   = $this->getOrderStatusName($orderStateId);

        // Determine priority based on order state
        $failedStates  = [3, 6]; // Failed, Cancelled
        $warningStates = [5];   // New
        $successStates = [1, 2, 7]; // Confirmed, Processed, Shipped

        if (\in_array($orderStateId, $failedStates, true)) {
            $priority = self::PRIORITY_WARNING;
            $langKey  = 'PLG_ACTIONLOG_J2COMMERCE_ORDER_PAYMENT_FAILED';
        } elseif (\in_array($orderStateId, $warningStates, true)) {
            $priority = self::PRIORITY_WARNING;
            $langKey  = 'PLG_ACTIONLOG_J2COMMERCE_ORDER_PAYMENT_NEW';
        } elseif (\in_array($orderStateId, $successStates, true)) {
            $priority = self::PRIORITY_SUCCESS;
            $langKey  = 'PLG_ACTIONLOG_J2COMMERCE_ORDER_PAYMENT_SUCCESS';
        } else {
            $priority = self::PRIORITY_ACTION;
            $langKey  = 'PLG_ACTIONLOG_J2COMMERCE_ORDER_PAYMENT_NEW';
        }

        $message = $this->buildMessage($langKey, [
            'order_id'    => $orderId,
            'status_name' => $statusName,
            'state_id'    => $orderStateId,
        ]);

        $this->addLog([$message], $langKey, self::EXTENSION, $this->getUserId());
        $this->checkEmailNotification($langKey, $priority, $message);
    }

    public function onAfterOrderStatusChange(Event $event): void
    {
        if (!$this->params->get('log_order_events', 1)) {
            return;
        }

        $args       = $this->extractArgs($event, 3);
        $orderId    = (string) ($args[0] ?? '');
        $oldStateId = (int) ($args[1] ?? 0);
        $newStateId = (int) ($args[2] ?? 0);

        $oldStatusName = $this->getOrderStatusName($oldStateId);
        $newStatusName = $this->getOrderStatusName($newStateId);

        $failedStates = [3, 6];
        $priority     = \in_array($newStateId, $failedStates, true)
            ? self::PRIORITY_WARNING
            : self::PRIORITY_ACTION;

        $langKey = 'PLG_ACTIONLOG_J2COMMERCE_ORDER_STATUS_CHANGE';
        $message = $this->buildMessage($langKey, [
            'order_id'        => $orderId,
            'old_status_name' => $oldStatusName,
            'new_status_name' => $newStatusName,
            'old_state_id'    => $oldStateId,
            'new_state_id'    => $newStateId,
        ]);

        $this->addLog([$message], $langKey, self::EXTENSION, $this->getUserId());
        $this->checkEmailNotification($langKey, $priority, $message);
    }

    // ---------------------------------------------------------------
    // Private helpers
    // ---------------------------------------------------------------

    private function buildMessage(string $titleKey, array $args = []): array
    {
        $userId   = $this->getUserId();
        $identity = $this->getApplication()->getIdentity();

        $username = ($userId > 0 && $identity !== null)
            ? $identity->username
            : Text::_('PLG_ACTIONLOG_J2COMMERCE_GUEST');

        $accountlink = ($userId > 0)
            ? 'index.php?option=com_users&task=user.edit&id=' . $userId
            : '';

        return array_merge([
            'userid'      => $userId,
            'username'    => $username,
            'accountlink' => $accountlink,
            'action'      => $titleKey,
            'device_type' => $this->detectDeviceType(),
        ], $args);
    }

    /**
     * Detect device type from the User-Agent header.
     *
     * @return  string  'Desktop', 'Mobile', or 'Tablet'
     */
    private function detectDeviceType(): string
    {
        $ua = $this->getApplication()->input->server->getString('HTTP_USER_AGENT', '');

        if ($ua === '') {
            return 'Unknown';
        }

        // Tablet patterns (check before mobile — many tablets also match mobile)
        if (preg_match('/iPad|Android(?!.*Mobile)|Tablet|PlayBook|Silk/i', $ua)) {
            return 'Tablet';
        }

        // Mobile patterns
        if (preg_match('/Mobile|iPhone|iPod|Android.*Mobile|webOS|BlackBerry|Opera Mini|IEMobile/i', $ua)) {
            return 'Mobile';
        }

        return 'Desktop';
    }

    private function getUserId(): int
    {
        $identity = $this->getApplication()->getIdentity();

        return ($identity !== null) ? (int) $identity->id : 0;
    }

    private function extractArgs(Event $event, int $count): array
    {
        $args = [];

        for ($i = 0; $i < $count; $i++) {
            try {
                $args[] = $event->getArgument($i);
            } catch (\InvalidArgumentException) {
                $args[] = null;
            }
        }

        return $args;
    }

    private function getOrderStatusName(int $orderStateId): string
    {
        // Ensure component language is loaded (status names are language constants)
        $this->getApplication()->getLanguage()->load('com_j2commerce', JPATH_ADMINISTRATOR);

        $db    = $this->getDatabase();
        $query = $db->getQuery(true)
            ->select($db->quoteName('orderstatus_name'))
            ->from($db->quoteName('#__j2commerce_orderstatuses'))
            ->where($db->quoteName('j2commerce_orderstatus_id') . ' = :id')
            ->bind(':id', $orderStateId, ParameterType::INTEGER);

        $db->setQuery($query);

        try {
            $name = $db->loadResult();
        } catch (\RuntimeException) {
            return 'Unknown';
        }

        return $name ? Text::_($name) : 'Unknown';
    }

    private function checkEmailNotification(string $langKey, int $priority, array $message): void
    {
        $threshold = (int) $this->params->get('notification_level', 4);

        if ($priority < $threshold) {
            return;
        }

        $usergroups = $this->params->get('notification_usergroups', []);

        if (empty($usergroups)) {
            return;
        }

        if (\is_string($usergroups)) {
            $usergroups = json_decode($usergroups, true) ?: [];
        }

        if (!\is_array($usergroups) || $usergroups === []) {
            return;
        }

        $usergroups = array_map('intval', $usergroups);

        $db    = $this->getDatabase();
        $query = $db->getQuery(true)
            ->select('DISTINCT ' . $db->quoteName('u.email'))
            ->from($db->quoteName('#__users', 'u'))
            ->join('INNER', $db->quoteName('#__user_usergroup_map', 'm')
                . ' ON ' . $db->quoteName('m.user_id') . ' = ' . $db->quoteName('u.id'))
            ->where($db->quoteName('u.block') . ' = 0')
            ->whereIn($db->quoteName('m.group_id'), $usergroups);

        $db->setQuery($query);

        try {
            $emails = $db->loadColumn();
        } catch (\RuntimeException) {
            return;
        }

        if (empty($emails)) {
            return;
        }

        // Build a fake log object for ActionlogsHelper::getHumanReadableLogMessage()
        $log                       = new \stdClass();
        $log->message_language_key = strtoupper($langKey);
        $log->message              = json_encode($message);

        ActionlogsHelper::loadActionLogPluginsLanguage();
        $readableMessage = ActionlogsHelper::getHumanReadableLogMessage($log, false);

        $app     = $this->getApplication();
        $subject = Text::sprintf('PLG_ACTIONLOG_J2COMMERCE_EMAIL_SUBJECT', $app->get('sitename', 'J2Commerce'));
        $body    = Text::sprintf('PLG_ACTIONLOG_J2COMMERCE_EMAIL_BODY', $readableMessage, $app->get('sitename', 'J2Commerce'));

        foreach ($emails as $email) {
            try {
                $mailer = Factory::getMailer();
                $mailer->addRecipient($email);
                $mailer->setSubject($subject);
                $mailer->setBody($body);
                $mailer->isHtml(false);
                $mailer->Send();
            } catch (MailDisabledException|\Exception) {
                // Silently ignore mail failures to avoid disrupting user flow
                continue;
            }
        }
    }
}
