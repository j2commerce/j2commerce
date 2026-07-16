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
use Joomla\CMS\Log\Log;
use Tobscure\JsonApi\AbstractSerializer;
use Tobscure\JsonApi\Collection;
use Tobscure\JsonApi\Exception\InvalidParameterException;
use Tobscure\JsonApi\Resource;

class VouchersController extends J2CommerceApiController
{
    protected $contentType = 'vouchers';

    protected $default_view = 'vouchers';

    /** Manual balance adjustment (credit/debit/correction) — issue #1299 T5.2, admin only. */
    public function adjust()
    {
        $this->assertCan(['core.edit']);

        $id = $this->getRouteId();

        if ($id <= 0) {
            throw new InvalidParameterException('JLIB_FORM_VALIDATE_FIELD_INVALID', 400, null, 'id');
        }

        $adjustmentType = (string) $this->input->json->getWord('adjustment_type', '');
        $amount         = (float) $this->input->json->getFloat('amount', 0.0);
        $reason         = (string) $this->input->json->getString('reason', '');
        $note           = (string) $this->input->json->getString('note', '') ?: null;

        /** @var \J2Commerce\Component\J2commerce\Administrator\Model\VoucherModel $model */
        $model = $this->getModel('Voucher');

        try {
            $newBalance = $model->adjustBalance($id, $adjustmentType, $amount, $reason, $note);
        } catch (\InvalidArgumentException | \RuntimeException $e) {
            throw new InvalidParameterException($e->getMessage(), 400, null, 'amount');
        } catch (\Throwable $e) {
            Log::add('API voucher balance adjustment failed for voucher ' . $id . ': ' . $e->getMessage(), Log::ERROR, 'com_j2commerce');

            throw new \RuntimeException('COM_J2COMMERCE_VOUCHER_ADJUSTMENT_FAILED', 500);
        }

        $this->logBalanceAdjustment($id, $adjustmentType, $amount, $newBalance);

        return $this->emit((object) [
            'id'                => $id,
            'remaining_balance' => $newBalance,
        ]);
    }

    /** Registered-user's own gift cards — issue #1299 T5.3. Ownership from session identity only. */
    public function mine()
    {
        $user = $this->app->getIdentity();

        if (!$user || $user->guest || (int) $user->id === 0) {
            throw new NotAllowed('JGLOBAL_YOU_MUST_LOGIN_FIRST', 403);
        }

        /** @var \J2Commerce\Component\J2commerce\Site\Model\MyprofileModel $profileModel */
        $profileModel = $this->app->bootComponent('com_j2commerce')
            ->getMVCFactory()->createModel('Myprofile', 'Site', ['ignore_request' => true]);

        $cards = $profileModel->getCustomerGiftCards((int) $user->id, (string) $user->email, false);

        $items = array_map(static fn (object $card): object => (object) [
            'id'                => (int) $card->j2commerce_voucher_id,
            'masked_code'       => $card->masked_code,
            'voucher_value'     => $card->voucher_value,
            'remaining_balance' => $card->remaining_balance,
            'valid_from'        => $card->valid_from,
            'valid_to'          => $card->valid_to,
            'derived_status'    => $card->derived_status,
        ], $cards);

        return $this->emitCollection($items);
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

    /** Mirrors the admin User Actions Log trail (VoucherController::logBalanceAdjustment). */
    private function logBalanceAdjustment(int $id, string $type, float $amount, float $newBalance): void
    {
        $delta = match ($type) {
            'credit'     => abs($amount),
            'debit'      => -abs($amount),
            'correction' => $amount,
            default      => 0.0,
        };

        \Joomla\CMS\Plugin\PluginHelper::importPlugin('actionlog');
        $this->app->getDispatcher()->dispatch(
            'onJ2CommerceVoucherBalanceAdjusted',
            new \Joomla\Event\Event('onJ2CommerceVoucherBalanceAdjusted', [$id, $type, $delta, $newBalance, 'API adjustment'])
        );
    }

    private function serializer(): AbstractSerializer
    {
        return new class ($this->contentType) extends AbstractSerializer {
            public function __construct(protected $type)
            {
            }

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
    }

    private function emit(object $data): static
    {
        $this->app->getDocument()->setData(new Resource($data, $this->serializer()));

        return $this;
    }

    private function emitCollection(array $items): static
    {
        $this->app->getDocument()->setData(new Collection($items, $this->serializer()));

        return $this;
    }
}
