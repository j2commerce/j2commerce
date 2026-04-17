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

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\AdminController;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;
use Joomla\Database\ParameterType;
use Joomla\Utilities\ArrayHelper;

class AdvancedpricingController extends AdminController
{
    protected $text_prefix = 'COM_J2COMMERCE';

    public function getModel($name = 'Productprice', $prefix = 'Administrator', $config = ['ignore_request' => true])
    {
        return parent::getModel($name, $prefix, $config);
    }

    public function batch(): void
    {
        $this->checkToken();

        $cids = $this->input->get('cid', [], 'array');
        $cids = ArrayHelper::toInteger($cids);
        $cids = array_filter($cids);

        if (empty($cids)) {
            $this->setMessage(Text::_('JGLOBAL_NO_ITEM_SELECTED'), 'warning');
            $this->setRedirect(Route::_('index.php?option=com_j2commerce&view=advancedpricing', false));
            return;
        }

        $db = Factory::getContainer()->get('DatabaseDriver');

        try {
            $batchGroupId  = $this->input->post->getString('batch_customer_group_id', '');
            $batchDateFrom = $this->input->post->getString('batch_date_from', '');
            $batchDateTo   = $this->input->post->getString('batch_date_to', '');

            if ($batchGroupId !== '') {
                $groupId = (int) $batchGroupId;
                $query   = $db->getQuery(true)
                    ->update($db->quoteName('#__j2commerce_product_prices'))
                    ->set($db->quoteName('customer_group_id') . ' = :groupId')
                    ->bind(':groupId', $groupId, ParameterType::INTEGER)
                    ->whereIn($db->quoteName('j2commerce_productprice_id'), $cids);

                $db->setQuery($query);
                $db->execute();
            }

            if ($batchDateFrom !== '') {
                $dateFrom = Factory::getDate($batchDateFrom)->toSql();
                $query    = $db->getQuery(true)
                    ->update($db->quoteName('#__j2commerce_product_prices'))
                    ->set($db->quoteName('date_from') . ' = :dateFrom')
                    ->bind(':dateFrom', $dateFrom)
                    ->whereIn($db->quoteName('j2commerce_productprice_id'), $cids);

                $db->setQuery($query);
                $db->execute();
            }

            if ($batchDateTo !== '') {
                $dateTo = Factory::getDate($batchDateTo)->toSql();
                $query  = $db->getQuery(true)
                    ->update($db->quoteName('#__j2commerce_product_prices'))
                    ->set($db->quoteName('date_to') . ' = :dateTo')
                    ->bind(':dateTo', $dateTo)
                    ->whereIn($db->quoteName('j2commerce_productprice_id'), $cids);

                $db->setQuery($query);
                $db->execute();
            }

            $this->setMessage(Text::sprintf('COM_J2COMMERCE_N_ITEMS_UPDATED', \count($cids)));
        } catch (\Exception $e) {
            $this->setMessage($e->getMessage(), 'error');
        }

        $this->setRedirect(Route::_('index.php?option=com_j2commerce&view=advancedpricing', false));
    }

    public function clearDates(): void
    {
        $this->checkToken();

        $cids = $this->input->get('cid', [], 'array');
        $cids = ArrayHelper::toInteger($cids);
        $cids = array_filter($cids);

        if (empty($cids)) {
            $this->setMessage(Text::_('JGLOBAL_NO_ITEM_SELECTED'), 'warning');
            $this->setRedirect(Route::_('index.php?option=com_j2commerce&view=advancedpricing', false));
            return;
        }

        $db = Factory::getContainer()->get('DatabaseDriver');

        try {
            $query = $db->getQuery(true)
                ->update($db->quoteName('#__j2commerce_product_prices'))
                ->set($db->quoteName('date_from') . ' = NULL')
                ->set($db->quoteName('date_to') . ' = NULL')
                ->whereIn($db->quoteName('j2commerce_productprice_id'), $cids);

            $db->setQuery($query);
            $db->execute();

            $this->setMessage(Text::sprintf('COM_J2COMMERCE_N_ITEMS_UPDATED', $db->getAffectedRows()));
        } catch (\Exception $e) {
            $this->setMessage($e->getMessage(), 'error');
        }

        $this->setRedirect(Route::_('index.php?option=com_j2commerce&view=advancedpricing', false));
    }

    public function ajaxSavePrice(): void
    {
        if (!$this->validateAjaxToken()) {
            $this->sendJsonResponse(false, Text::_('JINVALID_TOKEN'));
            return;
        }

        $priceId = $this->input->post->getInt('productprice_id', 0);
        $price   = $this->input->post->getFloat('price', 0.0);

        if ($priceId <= 0) {
            $this->sendJsonResponse(false, Text::_('COM_J2COMMERCE_ERROR_INVALID_PRICE_ID'));
            return;
        }

        if ($price < 0) {
            $this->sendJsonResponse(false, Text::_('COM_J2COMMERCE_ERROR_INVALID_PRICE'));
            return;
        }

        $db = Factory::getContainer()->get('DatabaseDriver');

        try {
            $query = $db->getQuery(true)
                ->update($db->quoteName('#__j2commerce_product_prices'))
                ->set($db->quoteName('price') . ' = :price')
                ->where($db->quoteName('j2commerce_productprice_id') . ' = :id')
                ->bind(':price', $price, ParameterType::STRING)
                ->bind(':id', $priceId, ParameterType::INTEGER);

            $db->setQuery($query);
            $db->execute();

            $this->sendJsonResponse(true, Text::_('COM_J2COMMERCE_MSG_PRICE_SAVED'), ['price' => $price]);
        } catch (\Exception $e) {
            $this->sendJsonResponse(false, $e->getMessage());
        }
    }

    /** Validates CSRF token without triggering Joomla's hidden redirect on new sessions. */
    private function validateAjaxToken(): bool
    {
        $token = Session::getFormToken();

        if ($token === $this->input->server->get('HTTP_X_CSRF_TOKEN', '', 'alnum')) {
            return true;
        }

        return (bool) $this->input->post->get($token, '', 'alnum');
    }

    private function sendJsonResponse(bool $success, string $message, array $data = []): void
    {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => $success, 'message' => $message, 'data' => $data]);
        Factory::getApplication()->close();
    }
}
