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

use J2Commerce\Component\J2commerce\Api\Controller\J2CommerceApiController;
use Joomla\CMS\Access\Exception\NotAllowed;
use Tobscure\JsonApi\AbstractSerializer;
use Tobscure\JsonApi\Exception\InvalidParameterException;
use Tobscure\JsonApi\Resource;

/**
 * Warehouse fulfilment endpoints (issue #1187, Gaps 1–3).
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
        $this->assertCan(['core.fulfilment', 'core.edit', 'core.manage', 'j2commerce.vieworders']);

        $pk    = ((int) $id) ?: $this->getRouteId();
        $order = $this->getModel('Order')->getItem($pk);

        if ($order === false || empty($order->j2commerce_order_id)) {
            throw new \Joomla\Router\Exception\RouteNotFoundException('JGLOBAL_ITEM_NOT_FOUND', 404);
        }

        $info     = $order->orderinfo     ?? null;
        $shipping = $order->ordershipping ?? null;

        $data = (object) [
            'id'                 => (int) $order->j2commerce_order_id,
            'order_id'           => $order->order_id,
            'order_state_id'     => (int) ($order->order_state_id ?? 0),
            'order_state'        => $order->order_state ?? '',
            'shipping_first_name'  => $info->shipping_first_name  ?? '',
            'shipping_middle_name' => $info->shipping_middle_name ?? '',
            'shipping_last_name'   => $info->shipping_last_name   ?? '',
            'shipping_company'     => $info->shipping_company     ?? '',
            'shipping_phone_1'     => $info->shipping_phone_1     ?? '',
            'shipping_phone_2'     => $info->shipping_phone_2     ?? '',
            'shipping_address_1'   => $info->shipping_address_1   ?? '',
            'shipping_address_2'   => $info->shipping_address_2   ?? '',
            'shipping_city'        => $info->shipping_city        ?? '',
            'shipping_zip'         => $info->shipping_zip         ?? '',
            'shipping_zone_name'   => $info->shipping_zone_name   ?? '',
            'shipping_country_name' => $info->shipping_country_name ?? '',
            'shipping_zone_id'      => (int) ($info->shipping_zone_id    ?? 0),
            'shipping_country_id'   => (int) ($info->shipping_country_id ?? 0),
            'shipping_tax_number'   => $info->shipping_tax_number ?? '',
            'ordershipping_name'        => $shipping->ordershipping_name        ?? '',
            'ordershipping_code'        => $shipping->ordershipping_code        ?? '',
            'ordershipping_type'        => $shipping->ordershipping_type        ?? '',
            'ordershipping_tracking_id' => $shipping->ordershipping_tracking_id ?? '',
        ];

        return $this->emit($data);
    }

    /** Gap 2 — event-safe status change (history row + customer email + download grants). */
    public function changeStatus()
    {
        $this->assertCan(['core.fulfilment', 'core.edit']);

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
        $this->assertCan(['core.fulfilment', 'core.edit']);

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

    /** ApiApplication injects the :id route var into input->post (not top-level input) for POST requests. */
    private function getRouteId(): int
    {
        $id = $this->input->getInt('id', 0);

        return $id > 0 ? $id : $this->input->post->getInt('id', 0);
    }

    private function assertCan(array $actions): void
    {
        $user = $this->app->getIdentity();

        foreach ($actions as $action) {
            if ($user && $user->authorise($action, 'com_j2commerce')) {
                return;
            }
        }

        throw new NotAllowed('JLIB_APPLICATION_ERROR_EDIT_NOT_PERMITTED', 403);
    }

    private function emit(object $data): static
    {
        $serializer = new class extends AbstractSerializer {
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
