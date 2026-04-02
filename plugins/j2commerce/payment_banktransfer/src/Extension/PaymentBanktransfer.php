<?php

/**
 * @package     J2Commerce
 * @subpackage  plg_j2commerce_payment_banktransfer
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace J2Commerce\Plugin\J2Commerce\PaymentBanktransfer\Extension;

use J2Commerce\Component\J2commerce\Administrator\Helper\OrderHistoryHelper;
use J2Commerce\Component\J2commerce\Administrator\Library\Plugins\Base;
use J2Commerce\Component\J2commerce\Administrator\Library\Plugins\Payment;
use J2Commerce\Component\J2commerce\Administrator\Library\Plugins\PluginLayoutTrait;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Language;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\FileLayout;
use Joomla\CMS\Log\Log;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Session\Session;
use Joomla\Database\DatabaseAwareTrait;
use Joomla\Database\DatabaseInterface;
use Joomla\Event\DispatcherInterface;
use Joomla\Event\Event;
use Joomla\Event\SubscriberInterface;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

final class PaymentBanktransfer extends CMSPlugin implements SubscriberInterface
{
    use DatabaseAwareTrait;
    use PluginLayoutTrait;

    protected $autoloadLanguage = true;

    protected $_element = 'payment_banktransfer';

    protected $_type = 'j2commerce';

    private ?Payment $payment = null;

    private ?Base $base = null;

    /** Stored for lazy init of Payment/Base helpers. */
    private DispatcherInterface $pluginDispatcher;
    private array $pluginConfig;

    public function __construct(
        DispatcherInterface $dispatcher,
        array $config,
        private Language $language,
        DatabaseInterface $db
    ) {
        parent::__construct($dispatcher, $config);

        $this->pluginDispatcher = $dispatcher;
        $this->pluginConfig = $config;
        $this->setDatabase($db);
    }

    public static function getSubscribedEvents(): array
    {
        return [
            'onJ2CommerceAcceptSubscriptionPayment'  => 'onAcceptSubscriptionPayment',
            'onJ2CommerceProcessRenewalPayment'      => 'onProcessRenewalPayment',
            'onJ2CommerceCalculateFees'              => 'onCalculateFees',
            'onJ2CommerceGetPaymentPlugins'          => 'onGetPaymentPlugins',
            'onJ2CommercePrePayment'                 => 'onPrePayment',
            'onJ2CommercePostPayment'                => 'onPostPayment',
        ];
    }

    public function onAcceptSubscriptionPayment(Event $event): void
    {
        $element = $event->getArguments()[0] ?? null;

        if ($element !== $this->_element) {
            return;
        }

        $result = $event->getArgument('result', []);
        $result[] = true;
        $event->setArgument('result', $result);
    }

    public function onProcessRenewalPayment(Event $event): void
    {
        // Bank transfer requires manual confirmation — no auto-renewal processing
    }

    public function onCalculateFees(Event $event): void
    {
        $args = $event->getArguments();
        $element = $args[0] ?? null;
        $order = $args[1] ?? null;

        if ($element !== $this->_element || $order === null) {
            return;
        }

        $paymentMethod = '';

        if (method_exists($order, 'get_payment_method')) {
            $paymentMethod = $order->get_payment_method();
        } elseif (isset($order->orderpayment_type)) {
            $paymentMethod = $order->orderpayment_type;
        }

        if ($paymentMethod !== $this->_element) {
            return;
        }

        $total = $order->order_subtotal + $order->order_shipping + $order->order_shipping_tax;
        $surcharge = 0.0;
        $surchargePercent = (float) $this->params->get('surcharge_percent', 0);
        $surchargeFixed = (float) $this->params->get('surcharge_fixed', 0);

        if ($surchargePercent > 0) {
            $surcharge += ($total * $surchargePercent) / 100;
        }

        if ($surchargeFixed > 0) {
            $surcharge += $surchargeFixed;
        }

        if ($surcharge > 0) {
            $name = $this->params->get('surcharge_name', Text::_('COM_J2COMMERCE_CART_SURCHARGE'));
            $taxClassId = $this->params->get('surcharge_tax_class_id', '');
            $taxable = ($taxClassId > 0);
            $order->add_fee($name, round($surcharge, 2), $taxable, $taxClassId);
        }
    }

    public function onGetPaymentPlugins(Event $event): void
    {
        $result = $event->getArgument('result', []);
        $result[] = [
            'element' => $this->_element,
            'name'    => Text::_($this->params->get('display_name', 'PLG_J2COMMERCE_PAYMENT_BANKTRANSFER')),
            'image'   => $this->params->get('display_image', ''),
        ];
        $event->setArgument('result', $result);
    }

    public function onPrePayment(Event $event): void
    {
        $args = $event->getArguments();
        $element = $args[0] ?? '';
        $data = $args[1] ?? [];

        if ($element !== $this->_element) {
            return;
        }

        $result = $event->getArgument('result', []);
        $result[] = $this->prePayment($data);
        $event->setArgument('result', $result);
    }

    public function onPostPayment(Event $event): void
    {
        $args = $event->getArguments();
        $element = $args[0] ?? '';
        $data = $args[1] ?? [];

        if ($element !== $this->_element) {
            return;
        }

        $result = $event->getArgument('result', []);
        $result[] = $this->postPayment((object) $data);
        $event->setArgument('result', $result);
    }

    private function getPayment(): Payment
    {
        if ($this->payment === null) {
            $this->payment = new Payment($this->pluginDispatcher, $this->pluginConfig);
            $this->payment->_element = $this->_name;
        }

        return $this->payment;
    }

    private function getBase(): Base
    {
        if ($this->base === null) {
            $this->base = new Base($this->pluginDispatcher, $this->pluginConfig);
        }

        return $this->base;
    }

    private function createOrderTable(): object
    {
        return Factory::getApplication()
            ->bootComponent('com_j2commerce')
            ->getMVCFactory()
            ->createTable('Order', 'Administrator');
    }

    private function prePayment(array $data): string
    {
        $this->ensureLanguageLoaded();

        $vars = new \stdClass();
        $vars->order_id = $data['order_id'];
        $vars->orderpayment_id = $data['orderpayment_id'];
        $vars->orderpayment_amount = $data['orderpayment_amount'];
        $vars->orderpayment_type = $this->_element;
        $vars->bank_information = $this->params->get('bank_details', '');

        $vars->display_name = Text::_($this->params->get('display_name', 'PLG_J2COMMERCE_PAYMENT_BANKTRANSFER'));
        $vars->onbeforepayment_text = $this->params->get('onbeforepayment', '');
        $vars->button_text = $this->params->get('button_text', Text::_('COM_J2COMMERCE_PLACE_ORDER'));

        $order = $this->createOrderTable();
        $order->load(['order_id' => $vars->order_id]);

        $vars->hash = $this->getPayment()->generateHash($order);

        $layoutPath = JPATH_PLUGINS . '/' . $this->_type . '/' . $this->_element . '/tmpl';

        return (new FileLayout('prepayment', $layoutPath))->render(['vars' => $vars]);
    }

    private function postPayment(object $data): string
    {
        $this->ensureLanguageLoaded();

        $app = Factory::getApplication();
        $vars = new \stdClass();
        $paction = $app->getInput()->getString('paction');

        $layoutPath = JPATH_PLUGINS . '/' . $this->_type . '/' . $this->_element . '/tmpl';

        return match ($paction) {
            'display' => $this->postPaymentDisplay($vars, $layoutPath),
            'process' => $this->postPaymentProcess($data),
            default => $this->postPaymentError($vars, $layoutPath),
        };
    }

    private function postPaymentDisplay(\stdClass $vars, string $layoutPath): string
    {
        $vars->onafterpayment_text = $this->params->get('onafterpayment', '');
        $html = (new FileLayout('postpayment', $layoutPath))->render(['vars' => $vars]);
        $html .= $this->getBase()->_displayArticle();

        return $html;
    }

    private function postPaymentProcess(object $data): string
    {
        if (!Session::checkToken()) {
            return json_encode(['error' => Text::_('JINVALID_TOKEN')]);
        }

        return json_encode($this->processPayment());
    }

    private function postPaymentError(\stdClass $vars, string $layoutPath): string
    {
        $vars->message = $this->params->get('onerrorpayment', Text::_('PLG_J2COMMERCE_PAYMENT_BANKTRANSFER_ORDER_NOT_FOUND'));

        return (new FileLayout('message', $layoutPath))->render(['vars' => $vars]);
    }

    private function processPayment(): array
    {
        $app = Factory::getApplication();
        $orderId = $app->getInput()->getString('order_id');
        $json = [];

        $order = $this->createOrderTable();

        if (!$order->load(['order_id' => $orderId])) {
            $json['error'] = $this->params->get('onerrorpayment', Text::_('PLG_J2COMMERCE_PAYMENT_BANKTRANSFER_ORDER_NOT_FOUND'));

            return $json;
        }

        if ($order->orderpayment_type !== $this->_element || !$this->getPayment()->validateHash($order)) {
            $json['error'] = $this->params->get('onerrorpayment', Text::_('PLG_J2COMMERCE_PAYMENT_BANKTRANSFER_ORDER_NOT_FOUND'));

            return $json;
        }

        $bankDetails = $this->params->get('bank_details', '');

        if (\strlen($bankDetails) > 5) {
            $sanitized = htmlspecialchars($bankDetails, ENT_QUOTES, 'UTF-8');
            $html = '<br>' . $sanitized;
            $array = json_decode($order->order_params ?? '{}', true) ?: [];
            $array[$this->_element] = $html;
            $order->order_params = json_encode($array);

            if ($this->params->get('enable_bank_transfer_strip_tags', 0)) {
                $html = strip_tags(preg_replace('#<br\s*/?>#i', "\n", $html));
            }

            $order->customer_note = ($order->customer_note ?? '') . $html;
        }

        // Set status and save everything in a single store() call
        $orderStateId = (int) $this->params->get('payment_status', 4);
        $order->order_state_id = $orderStateId;

        if (!$order->store()) {
            Log::add('Bank transfer order save failed: ' . $order->getError(), Log::ERROR, 'com_j2commerce');
            $json['error'] = Text::_('PLG_J2COMMERCE_PAYMENT_BANKTRANSFER_ORDER_NOT_FOUND');

            return $json;
        }

        OrderHistoryHelper::add(
            orderId: $order->order_id,
            comment: Text::sprintf('COM_J2COMMERCE_ORDER_HISTORY_PAYMENT_RECEIVED', Text::_('PLG_J2COMMERCE_PAYMENT_BANKTRANSFER')),
            orderStateId: $orderStateId,
        );

        $json['success'] = $this->params->get('onafterpayment', '');
        $json['redirect'] = $this->getPayment()->getReturnUrl();

        return $json;
    }

    private function ensureLanguageLoaded(): void
    {
        static $loaded = false;

        if (!$loaded) {
            $this->language->load('com_j2commerce', JPATH_ADMINISTRATOR);
            $loaded = true;
        }
    }
}