<?php

/**
 * @package     J2Commerce
 * @subpackage  Plugin.J2Commerce.PaymentMoneyorder
 *
 * @copyright   Copyright (C) 2024-2026 J2Commerce, LLC. All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

declare(strict_types=1);

namespace J2Commerce\Plugin\J2Commerce\PaymentMoneyorder\Extension;

use J2Commerce\Component\J2commerce\Administrator\Helper\OrderHistoryHelper;
use J2Commerce\Component\J2commerce\Administrator\Library\Plugins\Base;
use J2Commerce\Component\J2commerce\Administrator\Library\Plugins\Payment;
use J2Commerce\Component\J2commerce\Administrator\Library\Plugins\PluginLayoutTrait;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Language;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Session\Session;
use Joomla\Database\DatabaseAwareTrait;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;
use Joomla\Event\DispatcherInterface;
use Joomla\Event\Event;
use Joomla\Event\EventInterface;
use Joomla\Event\SubscriberInterface;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Money Order Payment Plugin for J2Commerce
 *
 * Provides money order/check payment method with custom payment instructions
 *
 * @since  6.0.0
 */
final class PaymentMoneyorder extends CMSPlugin implements SubscriberInterface
{
    use DatabaseAwareTrait;
    use PluginLayoutTrait;

    protected $autoloadLanguage = true;

    /**
     * Plugin element name - no type declaration on inherited property
     */
    protected $_name = 'payment_moneyorder';

    /**
     * Plugin type - no type declaration on inherited property
     */
    protected $_type = 'j2commerce';

    private Language $language;

    private Payment $payment;

    private Base $base;

    public function __construct(
        DispatcherInterface $dispatcher,
        array $config,
        Language $language,
        DatabaseInterface $db
    ) {
        parent::__construct($dispatcher, $config);

        $this->language = $language;
        $this->setDatabase($db);

        $this->language->load('com_j2commerce', JPATH_ADMINISTRATOR);
        $this->payment           = new Payment($dispatcher, $config);
        $this->payment->_element = $this->_name;
        $this->base              = new Base($dispatcher, $config);
    }

    public static function getSubscribedEvents(): array
    {
        return [
            'onJ2CommerceAcceptSubscriptionPayment' => 'onAcceptSubscriptionPayment',
            'onJ2CommerceCalculateFees'             => 'onCalculateFees',
            'onJ2CommerceGetPaymentOptions'         => 'onGetPaymentOptions',
            'onJ2CommerceGetPaymentPlugins'         => 'onGetPaymentPlugins',
            'onJ2CommercePrePayment'                => 'onPrePayment',
            'onJ2CommercePostPayment'               => 'onPostPayment',
        ];
    }

    public function onAcceptSubscriptionPayment(Event $event): void
    {
        if (!($event instanceof EventInterface)) {
            return;
        }

        $args    = $event->getArguments();
        $element = $args[0] ?? null;

        if ($element !== $this->_name) {
            return;
        }

        $event->setArgument('result', true);
    }

    public function onCalculateFees(Event $event): void
    {
        if (!($event instanceof EventInterface)) {
            return;
        }

        $args    = $event->getArguments();
        $element = $args[0] ?? null;
        $order   = $args[1] ?? null;

        if ($element !== $this->_name || $order === null) {
            return;
        }

        // Check if this plugin is the selected payment method
        $paymentMethod = '';

        if (method_exists($order, 'get_payment_method')) {
            $paymentMethod = $order->get_payment_method();
        } elseif (isset($order->orderpayment_type)) {
            $paymentMethod = $order->orderpayment_type;
        }

        if ($paymentMethod !== $this->_name) {
            return;
        }

        $total            = $order->order_subtotal + $order->order_shipping + $order->order_shipping_tax;
        $surcharge        = 0.0;
        $surchargePercent = (float) $this->params->get('surcharge_percent', 0);
        $surchargeFixed   = (float) $this->params->get('surcharge_fixed', 0);

        if ($surchargePercent > 0 || $surchargeFixed > 0) {
            if ($surchargePercent > 0) {
                $surcharge += ($total * $surchargePercent) / 100;
            }

            if ($surchargeFixed > 0) {
                $surcharge += $surchargeFixed;
            }

            $name       = $this->params->get('surcharge_name', Text::_('COM_J2COMMERCE_CART_SURCHARGE'));
            $taxClassId = $this->params->get('surcharge_tax_class_id', '');
            $taxable    = !empty($taxClassId) && (int) $taxClassId > 0;

            if ($surcharge > 0) {
                $order->add_fee($name, round($surcharge, 2), $taxable, $taxClassId);
            }
        }
    }

    public function onGetPaymentOptions(Event $event): void
    {
        if (!($event instanceof EventInterface)) {
            return;
        }

        $args    = $event->getArguments();
        $element = $args[0] ?? null;
        $order   = $args[1] ?? null;

        if ($element !== $this->_name || $order === null) {
            return;
        }

        $found = true;

        $order->setAddress();
        $address   = $order->getBillingAddress();
        $geozoneId = (int) $this->params->get('geozone_id', 0);

        if ($geozoneId > 0) {
            $found = $this->checkGeozone($geozoneId, $address);
        }

        $event->setArgument('result', $found);
    }

    /**
     * Return payment plugin metadata for checkout display.
     *
     * Joomla 6 dispatcher discards return values — must set result on Event object.
     */
    public function onGetPaymentPlugins(Event $event): void
    {
        $result   = $event->getArgument('result', []);
        $result[] = [
            'element' => $this->_name,
            'name'    => Text::_($this->params->get('display_name', 'PLG_J2COMMERCE_PAYMENT_MONEYORDER')),
            'image'   => $this->params->get('display_image', ''),
        ];
        $event->setArgument('result', $result);
    }

    /**
     * Return payment form HTML for the confirm step.
     */
    public function onPrePayment(Event $event): void
    {
        $args    = $event->getArguments();
        $element = $args[0] ?? '';
        $data    = $args[1] ?? [];

        if ($element !== $this->_name) {
            return;
        }

        $result   = $event->getArgument('result', []);
        $result[] = $this->_prePayment($data);
        $event->setArgument('result', $result);
    }

    /**
     * Handle post-payment processing.
     */
    public function onPostPayment(Event $event): void
    {
        $args    = $event->getArguments();
        $element = $args[0] ?? '';
        $data    = $args[1] ?? [];

        if ($element !== $this->_name) {
            return;
        }

        $result   = $event->getArgument('result', []);
        $result[] = $this->_postPayment((object) $data);
        $event->setArgument('result', $result);
    }

    /**
     * Get the OrderModel via MVC factory.
     *
     * @return  object  The Order model instance.
     *
     * @since   6.0.0
     */
    private function getOrderModel(): object
    {
        return Factory::getApplication()
            ->bootComponent('com_j2commerce')
            ->getMVCFactory()
            ->createModel('Order', 'Administrator', ['ignore_request' => true]);
    }

    private function checkGeozone(int $geozoneId, array $address): bool
    {
        $db    = $this->getDatabase();
        $query = $db->getQuery(true);

        $countryId = (int) ($address['country_id'] ?? 0);
        $zoneId    = (int) ($address['zone_id'] ?? 0);

        $query->select($db->quoteName('gz.j2commerce_geozone_id'))
            ->from($db->quoteName('#__j2commerce_geozones', 'gz'))
            ->innerJoin(
                $db->quoteName('#__j2commerce_geozonerules', 'gzr')
                . ' ON ' . $db->quoteName('gzr.geozone_id') . ' = ' . $db->quoteName('gz.j2commerce_geozone_id')
            )
            ->where($db->quoteName('gz.j2commerce_geozone_id') . ' = :geozoneId')
            ->where($db->quoteName('gzr.country_id') . ' = :countryId')
            ->where('(' . $db->quoteName('gzr.zone_id') . ' = 0 OR ' . $db->quoteName('gzr.zone_id') . ' = :zoneId)')
            ->bind(':geozoneId', $geozoneId, ParameterType::INTEGER)
            ->bind(':countryId', $countryId, ParameterType::INTEGER)
            ->bind(':zoneId', $zoneId, ParameterType::INTEGER);

        $db->setQuery($query);
        $result = $db->loadResult();

        return !empty($result);
    }

    public function _prePayment(array $data): string
    {
        $vars = new \stdClass();

        $vars->order_id            = $data['order_id'];
        $vars->orderpayment_id     = $data['orderpayment_id'] ?? 0;
        $vars->orderpayment_amount = $data['orderpayment_amount'];
        $vars->orderpayment_type   = $this->_name;

        $vars->display_name           = Text::_($this->params->get('display_name', 'PLG_J2COMMERCE_PAYMENT_MONEYORDER'));
        $vars->display_image          = $this->params->get('display_image', '');
        $vars->onbeforepayment_text   = $this->params->get('onbeforepayment', '');
        $vars->moneyorder_information = $this->params->get('moneyorder_information', '');
        $vars->button_text            = $this->params->get('button_text', Text::_('COM_J2COMMERCE_PLACE_ORDER'));

        // Load order via Table using the order_id string column (not the integer PK)
        $order = Factory::getApplication()
            ->bootComponent('com_j2commerce')
            ->getMVCFactory()
            ->createTable('Order', 'Administrator');
        $order->load(['order_id' => $vars->order_id]);

        $vars->hash = $this->payment->generateHash($order);

        return $this->_getLayout('prepayment', $vars);
    }

    public function _postPayment(object $data): string
    {
        $app     = Factory::getApplication();
        $vars    = new \stdClass();
        $paction = $app->input->getString('paction');
        $html    = '';

        switch ($paction) {
            case 'display':
                $vars->onafterpayment_text = Text::_($this->params->get('onafterpayment', ''));
                $html                      = $this->_getLayout('postpayment', $vars);
                $html .= $this->base->_displayArticle();
                break;

            case 'process':
                Session::checkToken() or die('Invalid Token');
                $result = $this->_process($data);

                // Return JSON for the controller to output and handle event dispatch
                return json_encode($result);

            default:
                $vars->message = Text::_($this->params->get('onerrorpayment', ''));
                $html          = $this->_getLayout('message', $vars);
                break;
        }

        return $html;
    }

    public function _process(object $data): array
    {
        $app     = Factory::getApplication();
        $orderId = $app->input->getString('order_id');
        $json    = [];

        // Load order via Table using the order_id string column (not the integer PK)
        $order = Factory::getApplication()
            ->bootComponent('com_j2commerce')
            ->getMVCFactory()
            ->createTable('Order', 'Administrator');

        if (!$order->load(['order_id' => $orderId])) {
            $json['error'] = $this->params->get('onerrorpayment', Text::_('PLG_J2COMMERCE_PAYMENT_MONEYORDER_ORDER_NOT_FOUND'));
            return $json;
        }

        if ($order->orderpayment_type !== $this->_name || !$this->payment->validateHash($order)) {
            $json['error'] = $this->params->get('onerrorpayment', Text::_('PLG_J2COMMERCE_PAYMENT_MONEYORDER_INVALID_REQUEST'));
            return $json;
        }

        $moneyorderInformation = $this->params->get('moneyorder_information', '');

        if (\strlen($moneyorderInformation) > 5) {
            $html = '<br>';
            $html .= '<strong>' . Text::_('PLG_J2COMMERCE_PAYMENT_MONEYORDER_INSTRUCTIONS') . '</strong>';
            $html .= '<br>';
            $html .= $moneyorderInformation;

            if ($this->params->get('enable_strip_tags', 0)) {
                $html = strip_tags(preg_replace('#<br\s*/?>#i', "\n", $html));
            }

            $order->customer_note = $order->customer_note . $html;
        }

        $orderStateId = (int) $this->params->get('payment_status', 4);

        if ($orderStateId === 1) {
            $order->order_state_id = 1;
        } else {
            $order->order_state_id = $orderStateId;
        }

        if ($order->store()) {
            OrderHistoryHelper::add(
                orderId: $order->order_id,
                comment: Text::sprintf('COM_J2COMMERCE_ORDER_HISTORY_PAYMENT_RECEIVED', Text::_('PLG_J2COMMERCE_PAYMENT_MONEYORDER')),
                orderStateId: (int) $order->order_state_id,
            );

            $json['success']  = Text::_($this->params->get('onafterpayment', ''));
            $json['redirect'] = $this->payment->getReturnUrl();
        } else {
            $json['error'] = $order->getError();
        }

        return $json;
    }

    public function _renderForm(array $data): string
    {
        $vars                   = new \stdClass();
        $vars->onselection_text = $this->params->get('onselection', '');
        return $this->_getLayout('form', $vars);
    }

    protected function _getLayout(string $layout, ?\stdClass $vars = null): string
    {
        return $this->resolvePluginLayout($layout, ['vars' => $vars]);
    }
}
