<?php

/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace J2Commerce\Component\J2commerce\Administrator\Controller;

\defined('_JEXEC') or die;

use J2Commerce\Component\J2commerce\Administrator\Helper\DatabaseHealthHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Log\Log;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Response\JsonResponse;
use Joomla\CMS\Session\Session;
use Joomla\Database\DatabaseInterface;

class DatabasehealthController extends BaseController
{
    private function jsonError(string $message, int $status = 400): void
    {
        $this->app->setHeader('Content-Type', 'application/json; charset=utf-8');
        $this->app->setHeader('status', (string) $status);
        echo new JsonResponse(null, $message, true);
        $this->app->close();
    }

    private function jsonSuccess(mixed $data, string $message = ''): void
    {
        $this->app->setHeader('Content-Type', 'application/json; charset=utf-8');
        echo new JsonResponse($data, $message);
        $this->app->close();
    }

    /** A token is anti-forgery only — client, guest status and permission are checked independently. */
    private function requireAdmin(): bool
    {
        $user = $this->app->getIdentity();

        if (!$this->app->isClient('administrator') || $user === null || $user->guest || !$user->authorise('core.admin', 'com_j2commerce')) {
            $this->jsonError(Text::_('JLIB_APPLICATION_ERROR_ACCESS_FORBIDDEN'), 403);

            return false;
        }

        return true;
    }

    public function getStatus(): void
    {
        if (!$this->requireAdmin()) {
            return;
        }

        try {
            $this->jsonSuccess(DatabaseHealthHelper::getResults());
        } catch (\Throwable $e) {
            Log::add('Database health getStatus failed: ' . $e->getMessage(), Log::ERROR, 'com_j2commerce');
            $this->jsonError(Text::_('COM_J2COMMERCE_ERR_GENERIC'), 500);
        }
    }

    public function runAction(): void
    {
        if (!Session::checkToken()) {
            $this->jsonError(Text::_('JINVALID_TOKEN'), 403);

            return;
        }

        if (!$this->requireAdmin()) {
            return;
        }

        $checkId = $this->input->getCmd('checkId', '');

        if ($checkId === '') {
            $this->jsonError(Text::_('JGLOBAL_NO_ITEM_SELECTED'));

            return;
        }

        try {
            $result = DatabaseHealthHelper::runFix($checkId);
            $this->jsonSuccess($result, Text::sprintf('COM_J2COMMERCE_DATABASE_HEALTH_FIX_RESULT', $result['fixed'], $result['remaining']));
        } catch (\InvalidArgumentException $e) {
            $this->jsonError(Text::_('JGLOBAL_NO_ITEM_SELECTED'), 404);
        } catch (\Throwable $e) {
            Log::add('Database health runAction failed for "' . $checkId . '": ' . $e->getMessage(), Log::ERROR, 'com_j2commerce');
            $this->jsonError(Text::_('COM_J2COMMERCE_ERR_GENERIC'), 500);
        }
    }

    /**
     * Deletes one product from the products_without_master_variant Review modal, through the
     * standard ProductModel delete path (ProductTable::deleteChildRecords() cascade) — never
     * a raw DELETE against #__j2commerce_products.
     */
    public function deleteProduct(): void
    {
        if (!Session::checkToken()) {
            $this->jsonError(Text::_('JINVALID_TOKEN'), 403);

            return;
        }

        if (!$this->requireAdmin()) {
            return;
        }

        $productId = $this->input->getInt('id', 0);

        if ($productId <= 0) {
            $this->jsonError(Text::_('JGLOBAL_NO_ITEM_SELECTED'));

            return;
        }

        try {
            $model = Factory::getApplication()->bootComponent('com_j2commerce')
                ->getMVCFactory()
                ->createModel('Product', 'Administrator', ['ignore_request' => true]);

            $pks = [$productId];

            if ($model === null || !$model->delete($pks)) {
                $this->jsonError(Text::_('COM_J2COMMERCE_ERR_GENERIC'));

                return;
            }

            $db = Factory::getContainer()->get(DatabaseInterface::class);

            $this->jsonSuccess(
                ['remaining' => DatabaseHealthHelper::countProductsWithoutMasterVariant($db)],
                Text::_('COM_J2COMMERCE_DATABASE_HEALTH_PRODUCT_DELETED')
            );
        } catch (\Throwable $e) {
            Log::add('Database health deleteProduct failed for product ' . $productId . ': ' . $e->getMessage(), Log::ERROR, 'com_j2commerce');
            $this->jsonError(Text::_('COM_J2COMMERCE_ERR_GENERIC'), 500);
        }
    }
}
