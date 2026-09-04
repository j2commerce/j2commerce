<?php

/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace J2Commerce\Component\J2commerce\Api\Controller;

\defined('_JEXEC') or die;

use J2Commerce\Component\J2commerce\Administrator\Helper\J2CommerceHelper;
use Joomla\CMS\Access\Exception\NotAllowed;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Log\Log;
use Joomla\CMS\MVC\Controller\Exception\Save;
use Tobscure\JsonApi\AbstractSerializer;
use Tobscure\JsonApi\Exception\InvalidParameterException;
use Tobscure\JsonApi\Resource;

/**
 * Warehouse fulfilment endpoints (issue #1187, Gaps 1–4).
 *
 * Reuses the admin OrderModel; emits flat JSON via inline serializers.
 */
class OrderfulfilmentController extends J2CommerceApiController
{
    protected $contentType = 'orderfulfilment';

    protected $default_view = 'orderfulfilment';

    /** Gap 3 — order detail with ship-to block, chosen method and tracking. */
    public function displayItem($id = null)
    {
        $this->assertCan(['core.fulfilment', 'core.edit', 'core.manage'], 'j2commerce.vieworders');

        $pk    = ((int) $id) ?: $this->getRouteId();
        $order = $this->getModel('Order')->getItem($pk);

        if ($order === false || empty($order->j2commerce_order_id)) {
            throw new \Joomla\Router\Exception\RouteNotFoundException('JGLOBAL_ITEM_NOT_FOUND', 404);
        }

        $info     = $order->orderinfo ?? null;
        $shipping = $order->ordershipping ?? null;

        // order_state is deprecated: it is served from the joined status name, so it can no
        // longer disagree with order_state_id. Integrators should read order_state_id.
        $data = (object) [
            'id'                        => (int) $order->j2commerce_order_id,
            'order_id'                  => $order->order_id,
            'order_state_id'            => (int) ($order->order_state_id ?? 0),
            'order_state'               => $order->orderstatus_name ?? '',
            'shipping_first_name'       => $info->shipping_first_name ?? '',
            'shipping_middle_name'      => $info->shipping_middle_name ?? '',
            'shipping_last_name'        => $info->shipping_last_name ?? '',
            'shipping_company'          => $info->shipping_company ?? '',
            'shipping_phone_1'          => $info->shipping_phone_1 ?? '',
            'shipping_phone_2'          => $info->shipping_phone_2 ?? '',
            'shipping_address_1'        => $info->shipping_address_1 ?? '',
            'shipping_address_2'        => $info->shipping_address_2 ?? '',
            'shipping_city'             => $info->shipping_city ?? '',
            'shipping_zip'              => $info->shipping_zip ?? '',
            'shipping_zone_name'        => $info->shipping_zone_name ?? '',
            'shipping_country_name'     => $info->shipping_country_name ?? '',
            'shipping_zone_id'          => (int) ($info->shipping_zone_id ?? 0),
            'shipping_country_id'       => (int) ($info->shipping_country_id ?? 0),
            'shipping_tax_number'       => $info->shipping_tax_number ?? '',
            'ordershipping_name'        => $shipping->ordershipping_name ?? '',
            'ordershipping_code'        => $shipping->ordershipping_code ?? '',
            'ordershipping_type'        => $shipping->ordershipping_type ?? '',
            'ordershipping_tracking_id' => $shipping->ordershipping_tracking_id ?? '',
        ];

        return $this->emit($data);
    }

    /** Gap 2 — event-safe status change (history row + customer email + download grants). */
    public function changeStatus()
    {
        // editorders is required, not an alternative: the four admin routes that reach the
        // same sink all pair it with core.edit, and a status change here moves stock, grants
        // downloads and emails the customer.
        $this->assertCan(['core.fulfilment', 'core.edit'], 'j2commerce.editorders');

        $pk       = $this->getRouteId();
        $statusId = $this->input->json->getInt('status_id', 0);
        $notify   = (bool) $this->input->json->get('notify', false, 'BOOLEAN');
        $comment  = (string) $this->input->json->getString('comment', '');

        if ($statusId <= 0) {
            throw new InvalidParameterException('JLIB_FORM_VALIDATE_FIELD_INVALID', 400, null, 'status_id');
        }

        $ok = $this->getModel('Order')->updateOrderStatus($pk, $statusId, $notify, $comment);

        return $this->emit((object) [
            'id'             => $pk,
            'order_state_id' => $statusId,
            'notify'         => $notify,
            'success'        => $ok,
        ]);
    }

    /** Gap 1 — write ordershipping_tracking_id. */
    public function saveTracking()
    {
        // Same conjunct as changeStatus(): the admin twin (OrderController::ajaxSaveTracking()
        // -> checkOrderEditAccess() -> checkAjaxAccess('core.edit', 'j2commerce.editorders'))
        // has required editorders since #1433/#1438, and this route writes the same column.
        $this->assertCan(['core.fulfilment', 'core.edit'], 'j2commerce.editorders');

        $pk         = $this->getRouteId();
        $trackingId = trim((string) $this->input->json->getString('tracking_id', ''));

        if ($trackingId === '') {
            throw new InvalidParameterException('JLIB_FORM_VALIDATE_FIELD_REQUIRED', 400, null, 'tracking_id');
        }

        $ok = $this->getModel('Order')->saveTrackingNumber($pk, $trackingId);

        return $this->emit((object) [
            'id'                        => $pk,
            'ordershipping_tracking_id' => $trackingId,
            'success'                   => $ok,
        ]);
    }

    /**
     * Gap 4 — buy a shipping label through whichever J2Commerce plugin can serve this order.
     *
     * Body: {"shipping_element": string (optional, '' = any capable plugin),
     * "options": {"test_label": bool, "service_code": string}}.
     *
     * The handler contract is frozen: subject = the admin order object, element = requested
     * plugin element, options = the sanitised request array; a handler that cannot serve the
     * order returns nothing, and the first non-empty result wins.
     */
    public function createLabel()
    {
        $this->assertCan(['core.fulfilment', 'core.edit'], 'j2commerce.editorders');

        $pk    = $this->getRouteId();
        $model = $this->getModel('Order');
        $order = $model->getItem($pk);

        if ($order === false || empty($order->j2commerce_order_id)) {
            throw new \Joomla\Router\Exception\RouteNotFoundException('JGLOBAL_ITEM_NOT_FOUND', 404);
        }

        if (!$model->isShippable($pk)) {
            throw new Save(Text::_('COM_J2COMMERCE_API_LABEL_NOT_SHIPPABLE'), 409);
        }

        // A label costs the merchant real money, so the order state decides, not the caller.
        // order_type pairs with the state everywhere else that asks whether an order is real
        // (OrdersModel::cancelUnpaidOrders(), OrderModel::getOrdersByUser()), and the state
        // saveOrder() writes before any payment is attempted is the one that would otherwise
        // let an abandoned checkout buy a label. PENDING stays eligible: the offline payment
        // methods legitimately ship before the money lands.
        // Matching seeded status names is a stopgap — see #2127 for the semantic status type.
        if ((string) ($order->order_type ?? 'normal') !== 'normal') {
            throw new Save(Text::_('COM_J2COMMERCE_API_LABEL_ORDER_CLOSED'), 409);
        }

        $closedStates = ['J2COMMERCE_NEW', 'J2COMMERCE_FAILED', 'J2COMMERCE_CANCELLED'];

        if (\in_array((string) ($order->orderstatus_name ?? ''), $closedStates, true)) {
            throw new Save(Text::_('COM_J2COMMERCE_API_LABEL_ORDER_CLOSED'), 409);
        }

        // Claim the tracking slot BEFORE the carrier is called. The stored tracking number is
        // the only thing standing between a retry and a second billable label, so reading it
        // here and writing it after the purchase would leave the whole round-trip unguarded —
        // and an order whose checkout never wrote a shipping row has nowhere to store it at
        // all, which is why a failed claim is a refusal rather than a warning.
        if (!$model->claimLabelSlot($pk)) {
            throw new Save(Text::_('COM_J2COMMERCE_API_LABEL_EXISTS'), 409);
        }

        $rawOptions = (array) $this->input->json->get('options', [], 'ARRAY');

        try {
            $event = J2CommerceHelper::plugin()->event('CreateShippingLabel', [
                'subject' => $order,
                'element' => trim((string) $this->input->json->getString('shipping_element', '')),
                'options' => [
                    'test_label'   => (bool) ($rawOptions['test_label'] ?? false),
                    'service_code' => mb_substr(trim((string) ($rawOptions['service_code'] ?? '')), 0, 64),
                ],
            ]);

            $raw    = $event->getArgument('result');
            $result = $this->firstLabelResult($raw);
        } catch (\Throwable $e) {
            $model->releaseLabelSlot($pk);

            throw $e;
        }

        if ($result === null) {
            // Nothing answered at all, so nothing was bought and the slot goes back.
            if ($raw === null) {
                $model->releaseLabelSlot($pk);

                throw new Save(Text::_('COM_J2COMMERCE_API_LABEL_NOT_SUPPORTED'), 501);
            }

            // Something answered, but not with a tracking number this route can store. It may
            // already have paid a carrier, so the slot stays held: releasing it here would let
            // a retry buy a second label. Clearing it is a decision for a person with the
            // order in front of them.
            Log::add(
                \sprintf('Shipping label handler returned an unusable result for order %d; tracking slot left held.', $pk),
                Log::ERROR,
                'com_j2commerce'
            );

            throw new Save(Text::_('COM_J2COMMERCE_API_LABEL_RESULT_INVALID'), 502);
        }

        $trackingId = (string) $result['tracking_id'];

        // The label has been bought by this point. If the write does not land, report it in
        // the payload rather than as an error: a failed response would hide a purchased label
        // and invite the retry that buys another one. See #2128 for the response contract.
        $saved = $model->saveTrackingNumber($pk, $trackingId);

        if (!$saved) {
            Log::add(
                \sprintf('Label %s bought for order %d but tracking could not be stored.', $trackingId, $pk),
                Log::ERROR,
                'com_j2commerce'
            );
        }

        return $this->emit((object) [
            'id'             => $pk,
            'tracking_id'    => $trackingId,
            'carrier'        => (string) ($result['carrier'] ?? ''),
            'label_format'   => (string) ($result['label_format'] ?? 'pdf'),
            'label_base64'   => (string) ($result['label_base64'] ?? ''),
            'tracking_saved' => $saved,
        ]);
    }

    /**
     * First usable label out of the event result, whichever way the handler set it.
     *
     * PluginEvent::addResult() appends, so a handler using the codebase's prevailing idiom
     * leaves a list; setArgument() assigns flat. Reading only the flat shape would throw the
     * "no plugin can do this" branch after the carrier had already been paid.
     */
    private function firstLabelResult($result): ?array
    {
        if (!\is_array($result)) {
            return null;
        }

        if ($this->hasTrackingId($result)) {
            return $result;
        }

        foreach ($result as $candidate) {
            if (\is_array($candidate) && $this->hasTrackingId($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * The scalar test is not pedantry: a handler returning an object or array here would throw
     * on the string cast further down, after the label had been bought and outside the block
     * that hands the slot back.
     */
    private function hasTrackingId(array $candidate): bool
    {
        return isset($candidate['tracking_id'])
            && \is_scalar($candidate['tracking_id'])
            && (string) $candidate['tracking_id'] !== '';
    }

    /**
     * ApiApplication injects the :id route var into input->post (not top-level input) for POST
     * requests, so post is read first: $this->input covers $_REQUEST, and reading it first let
     * a ?id= query string decide which order a POST route acted on instead of the URL segment.
     */
    private function getRouteId(): int
    {
        $id = $this->input->post->getInt('id', 0);

        return $id > 0 ? $id : $this->input->getInt('id', 0);
    }

    /**
     * Any one of $actions, and — when given — $requires as well.
     *
     * $requires is separate because it must not become another alternative: ApiDispatcher
     * overrides dispatch() without calling checkAccess(), so unlike the admin surface there
     * is no core.manage floor here, and a capability listed alongside a j2commerce.* action
     * would let a caller past a deny the merchant configured deliberately.
     *
     * For the same reason every alternative except core.fulfilment counts only behind
     * core.manage: core.edit is inherited from the root asset and the j2commerce.* actions
     * were written by the ACL seed, so either can outlive the core.manage revoke that removes
     * the group from the administrator. core.fulfilment is declared for these four routes
     * alone, is not inherited from the root asset, and is seeded to Administrator (group 7)
     * only — script.j2commerce.php grants it there and AclSeedHelper::CUSTOM_ACL_ACTIONS
     * omits it, so an upgrade never widens it. A warehouse token granted it deliberately
     * therefore keeps working without component management rights.
     */
    private function assertCan(array $actions, string $requires = ''): void
    {
        $user    = $this->app->getIdentity();
        $allowed = false;

        foreach ($actions as $action) {
            if (!$user || !$user->authorise($action, 'com_j2commerce')) {
                continue;
            }

            if ($action === 'core.fulfilment' || $user->authorise('core.manage', 'com_j2commerce')) {
                $allowed = true;
                break;
            }
        }

        if (!$allowed || ($requires !== '' && !J2CommerceHelper::canAccess($requires))) {
            throw new NotAllowed('JLIB_APPLICATION_ERROR_EDIT_NOT_PERMITTED', 403);
        }
    }

    private function emit(object $data): static
    {
        $serializer = new class () extends AbstractSerializer {
            protected $type = 'orderfulfilment';

            public function getId($model): string
            {
                return (string) ($model->id ?? '0');
            }

            public function getAttributes($model, ?array $fields = null): array
            {
                $attrs = (array) $model;
                unset($attrs['id']);

                return $attrs;
            }
        };

        $this->app->getDocument()->setData(new Resource($data, $serializer));

        return $this;
    }
}
