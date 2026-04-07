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

use J2Commerce\Component\J2commerce\Administrator\Helper\CurrencyHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Log\Log;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Response\JsonResponse;
use Joomla\CMS\Session\Session;

class AnalyticsController extends BaseController
{
    public function getData(): void
    {
        // POST only
        if ($this->input->getMethod() !== 'POST') {
            $this->app->setHeader('Content-Type', 'application/json; charset=utf-8');
            $this->app->setHeader('status', '405');
            echo new JsonResponse(null, 'Method Not Allowed', true);
            $this->app->close();
            return;
        }

        // CSRF
        if (!Session::checkToken('post')) {
            $this->app->setHeader('Content-Type', 'application/json; charset=utf-8');
            $this->app->setHeader('status', '403');
            echo new JsonResponse(null, 'Invalid Token', true);
            $this->app->close();
            return;
        }

        // ACL
        $user = $this->app->getIdentity();

        if (!$user->authorise('core.manage', 'com_j2commerce')) {
            $this->app->setHeader('Content-Type', 'application/json; charset=utf-8');
            $this->app->setHeader('status', '403');
            echo new JsonResponse(null, 'Access Denied', true);
            $this->app->close();
            return;
        }

        // Validate dates
        $from        = $this->input->getString('from', '');
        $to          = $this->input->getString('to', '');
        $datePattern = '/^\d{4}-\d{2}-\d{2}$/';

        if (!preg_match($datePattern, $from) || !preg_match($datePattern, $to)) {
            $to   = date('Y-m-d');
            $from = date('Y-m-d', strtotime('-30 days'));
        }

        // Append time components for full day coverage
        $fromDate = $from . ' 00:00:00';
        $toDate   = $to . ' 23:59:59';

        try {
            /** @var \J2Commerce\Component\J2commerce\Administrator\Model\AnalyticsModel $model */
            $model = $this->getModel('Analytics', 'Administrator');

            $totalRevenue      = $model->getTotalRevenue($fromDate, $toDate);
            $averageOrderValue = $model->getAverageOrderValue($fromDate, $toDate);
            $topProducts       = $model->getTopProducts($fromDate, $toDate);

            // Format top product revenue
            foreach ($topProducts as $product) {
                $product->formatted_revenue = CurrencyHelper::format((float) $product->total_revenue);
            }

            $sessionsData    = $model->getSessionsByHourAvg($fromDate, $toDate);
            $conversionsData = $model->getConversionsByHourAvg($fromDate, $toDate);

            $data = [
                'totalRevenue'         => $totalRevenue,
                'orderCount'           => $model->getOrderCount($fromDate, $toDate),
                'averageOrderValue'    => $averageOrderValue,
                'itemsSold'            => $model->getItemsSold($fromDate, $toDate),
                'revenueByDay'         => $model->getRevenueByDay($fromDate, $toDate),
                'ordersByDay'          => $model->getOrdersByDay($fromDate, $toDate),
                'topProducts'          => $topProducts,
                'statusDistribution'   => $model->getOrderStatusDistribution($fromDate, $toDate),
                'checkoutFunnel'       => $model->getCheckoutFunnel($fromDate, $toDate),
                'previousPeriod'       => $model->getPreviousPeriodData($fromDate, $toDate),
                'from'                 => $from,
                'to'                   => $to,
                'formattedRevenue'     => CurrencyHelper::format($totalRevenue),
                'formattedAOV'         => CurrencyHelper::format($averageOrderValue),
                'currencySymbol'       => CurrencyHelper::getSymbol(),
                'currencyPosition'     => CurrencyHelper::getSymbolPosition(),
                'sessionsAvgByHour'    => $sessionsData['hourly'],
                'sessionsTotal'        => $sessionsData['total'],
                'conversionsAvgByHour' => $conversionsData['hourly'],
                'conversionsTotal'     => $conversionsData['total'],
                'conversionBreakdown'  => $model->getConversionBreakdown($fromDate, $toDate),
                'deviceTypes'          => $model->getDeviceTypes($fromDate, $toDate),
            ];

            $this->app->setHeader('Content-Type', 'application/json; charset=utf-8');
            echo new JsonResponse($data);
        } catch (\Exception $e) {
            Log::add($e->getMessage(), Log::ERROR, 'com_j2commerce');
            $this->app->setHeader('Content-Type', 'application/json; charset=utf-8');
            $this->app->setHeader('status', '500');
            echo new JsonResponse(null, Text::_('COM_J2COMMERCE_ERROR_INTERNAL'), true);
        }

        $this->app->close();
    }
}
