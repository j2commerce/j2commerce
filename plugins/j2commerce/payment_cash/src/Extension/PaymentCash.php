<?php

/**
 * @package     J2Commerce
 * @subpackage  plg_j2commerce_payment_cash
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace J2Commerce\Plugin\J2Commerce\PaymentCash\Extension;

use J2Commerce\Component\J2commerce\Administrator\Helper\OrderHistoryHelper;
use J2Commerce\Component\J2commerce\Administrator\Library\Plugins\Base;
use J2Commerce\Component\J2commerce\Administrator\Library\Plugins\Payment;
use J2Commerce\Component\J2commerce\Administrator\Library\Plugins\PluginLayoutTrait;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Language;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Log\Log;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Session\Session;
use Joomla\Database\DatabaseAwareTrait;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;
use Joomla\Event\DispatcherInterface;
use Joomla\Event\Event;
use Joomla\Event\SubscriberInterface;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

final class PaymentCash extends CMSPlugin implements SubscriberInterface
{
    use DatabaseAwareTrait;
    use PluginLayoutTrait;

    protected $autoloadLanguage = true;

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
        $element = $event->getArguments()[0] ?? null;

        if ($element !== $this->_name) {
            return;
        }

        $event->setArgument('result', true);
    }

    public function onCalculateFees(Event $event): void
    {
        $args = $event->getArguments();
        $element = $args[0] ?? null;
        $order = $args[1] ?? null;

        if ($element !== $this->_name || $order === null) {
            return;
        }

        $paymentMethod = '';

        if (method_exists($order, 'get_payment_method')) {
            $paymentMethod = $order->get_payment_method();
        } elseif (isset($order->orderpayment_type)) {
            $paymentMethod = $order->orderpayment_type;
        }

        if ($paymentMethod !== $this->_name) {
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
            $taxable = !empty($taxClassId) && (int) $taxClassId > 0;
            $order->add_fee($name, round($surcharge, 2), $taxable, $taxClassId);
        }
    }

    public function onGetPaymentOptions(Event $event): void
    {
        $args = $event->getArguments();
        $element = $args[0] ?? null;
        $order = $args[1] ?? null;

        if ($element !== $this->_name || $order === null) {
            return;
        }

        $found = true;

        $order->setAddress();
        $address = $order->getBillingAddress();
        $geozoneId = (int) $this->params->get('geozone_id', 0);

        if ($geozoneId > 0) {
            $found = $this->checkGeozone($geozoneId, $address);
        }

        if ($found) {
            $found = $this->checkSubtotalLimits($order->order_subtotal);
        }

        $event->setArgument('result', $found);
    }

    /** Joomla 6 dispatcher discards return values — result must be set on the Event. */
    public function onGetPaymentPlugins(Event $event): void
    {
        $result = $event->getArgument('result', []);
        $result[] = [
            'element' => $this->_name,
            'name'    => Text::_($this->params->get('display_name', 'PLG_J2COMMERCE_PAYMENT_CASH')),
            'image'   => $this->params->get('display_image', ''),
        ];
        $event->setArgument('result', $result);
    }

    public function onPrePayment(Event $event): void
    {
        $args = $event->getArguments();
        $element = $args[0] ?? '';
        $data = $args[1] ?? [];

        if ($element !== $this->_name) {
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

        if ($element !== $this->_name) {
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

    private function checkGeozone(int $geozoneId, array $address): bool
    {
        $db = $this->getDatabase();
        $query = $db->getQuery(true);

        $countryId = (int) ($address['country_id'] ?? 0);
        $zoneId = (int) ($address['zone_id'] ?? 0);

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

        return !empty($db->loadResult());
    }

    private function checkSubtotalLimits(float $subtotal): bool
    {
        $minSubtotal = (float) $this->params->get('min_subtotal', 0);
        $maxSubtotal = (float) $this->params->get('max_subtotal', -1);

        if ($minSubtotal > 0 && $subtotal < $minSubtotal) {
            return false;
        }

        if ($maxSubtotal >= 0 && $subtotal > $maxSubtotal) {
            return false;
        }

        return true;
    }

    private function prePayment(array $data): string
    {
        $this->ensureLanguageLoaded();

        $vars = new \stdClass();
        $vars->order_id = $data['order_id'];
        $vars->orderpayment_id = $data['orderpayment_id'] ?? 0;
        $vars->orderpayment_amount = $data['orderpayment_amount'];
        $vars->orderpayment_type = $this->_name;

        $vars->display_name = Text::_($this->params->get('display_name', 'PLG_J2COMMERCE_PAYMENT_CASH'));
        $vars->display_image = $this->params->get('display_image', '');
        $vars->onbeforepayment_text = $this->params->get('onbeforepayment', '');
        $vars->button_text = $this->params->get('button_text', Text::_('COM_J2COMMERCE_PLACE_ORDER'));

        $order = $this->createOrderTable();
        $order->load(['order_id' => $vars->order_id]);

        $vars->hash = $this->getPayment()->generateHash($order);

        return $this->getLayout('prepayment', $vars);
    }

    private function postPayment(object $data): string
    {
        $this->ensureLanguageLoaded();

        $app = Factory::getApplication();
        $paction = $app->getInput()->getString('paction');

        return match ($paction) {
            'display' => $this->postPaymentDisplay(),
            'process' => $this->postPaymentProcess(),
            default => $this->postPaymentError(),
        };
    }

    private function postPaymentDisplay(): string
    {
        $vars = new \stdClass();
        $vars->onafterpayment_text = $this->params->get('onafterpayment', '');

        return $this->getLayout('postpayment', $vars) . $this->getBase()->_displayArticle();
    }

    private function postPaymentProcess(): string
    {
        if (!Session::checkToken()) {
            return json_encode(['error' => Text::_('JINVALID_TOKEN')]);
        }

        return json_encode($this->processPayment());
    }

    private function postPaymentError(): string
    {
        $vars = new \stdClass();
        $vars->message = $this->params->get('onerrorpayment', Text::_('PLG_J2COMMERCE_PAYMENT_CASH_ORDER_NOT_FOUND'));

        return $this->getLayout('message', $vars);
    }

    private function processPayment(): array
    {
        $app = Factory::getApplication();
        $orderId = $app->getInput()->getString('order_id');
        $json = [];

        $order = $this->createOrderTable();

        if (!$order->load(['order_id' => $orderId])) {
            $json['error'] = $this->params->get('onerrorpayment', Text::_('PLG_J2COMMERCE_PAYMENT_CASH_ORDER_NOT_FOUND'));

            return $json;
        }

        if ($order->orderpayment_type !== $this->_name || !$this->getPayment()->validateHash($order)) {
            $json['error'] = $this->params->get('onerrorpayment', Text::_('PLG_J2COMMERCE_PAYMENT_CASH_INVALID_REQUEST'));

            return $json;
        }

        // Set status and save in a single store() call
        $orderStateId = (int) $this->params->get('payment_status', 4);
        $order->order_state_id = $orderStateId;

        if (!$order->store()) {
            Log::add('Cash payment order save failed: ' . $order->getError(), Log::ERROR, 'com_j2commerce');
            $json['error'] = Text::_('PLG_J2COMMERCE_PAYMENT_CASH_ORDER_NOT_FOUND');

            return $json;
        }

        OrderHistoryHelper::add(
            orderId: $order->order_id,
            comment: Text::sprintf('COM_J2COMMERCE_ORDER_HISTORY_PAYMENT_RECEIVED', Text::_('PLG_J2COMMERCE_PAYMENT_CASH')),
            orderStateId: $orderStateId,
        );

        $json['success'] = $this->params->get('onafterpayment', '');
        $json['redirect'] = $this->getPayment()->getReturnUrl();

        return $json;
    }

    private function getLayout(string $layout, ?\stdClass $vars = null): string
    {
        return $this->resolvePluginLayout($layout, ['vars' => $vars]);
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
