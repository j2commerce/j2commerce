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
use J2Commerce\Component\J2commerce\Administrator\Helper\SampleDataHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Log\Log;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Response\JsonResponse;
use Joomla\CMS\Session\Session;

class DashboardController extends BaseController
{
    public function getLiveUsers(): void
    {
        if ($this->app->getInput()->getMethod() !== 'POST') {
            throw new \RuntimeException('Method not allowed', 405);
        }

        Session::checkToken('post') or die('Invalid Token');

        $user = $this->app->getIdentity();

        if (!$user->authorise('core.manage', 'com_j2commerce')) {
            throw new \RuntimeException('Forbidden', 403);
        }

        /** @var \J2Commerce\Component\J2commerce\Administrator\Model\DashboardModel $model */
        $model = $this->getModel('Dashboard', 'Administrator', ['ignore_request' => true]);
        $data  = $model->getLiveUsers();

        echo new JsonResponse($data);
        $this->app->close();
    }

    public function getData(): void
    {
        if ($this->input->getMethod() !== 'POST') {
            $this->app->setHeader('Content-Type', 'application/json; charset=utf-8');
            $this->app->setHeader('status', '405');
            echo new JsonResponse(null, 'Method Not Allowed', true);
            $this->app->close();
            return;
        }

        if (!Session::checkToken('post')) {
            $this->app->setHeader('Content-Type', 'application/json; charset=utf-8');
            $this->app->setHeader('status', '403');
            echo new JsonResponse(null, 'Invalid Token', true);
            $this->app->close();
            return;
        }

        if (!$this->app->getIdentity()->authorise('core.manage', 'com_j2commerce')) {
            $this->app->setHeader('Content-Type', 'application/json; charset=utf-8');
            $this->app->setHeader('status', '403');
            echo new JsonResponse(null, 'Access Denied', true);
            $this->app->close();
            return;
        }

        $from        = $this->input->getString('from', '');
        $to          = $this->input->getString('to', '');
        $datePattern = '/^\d{4}-\d{2}-\d{2}$/';

        $tz      = $this->app->getConfig()->get('offset', 'UTC');
        $storeTz = new \DateTimeZone($tz);
        $utcTz   = new \DateTimeZone('UTC');

        if (!preg_match($datePattern, $from) || !preg_match($datePattern, $to)) {
            $now  = new \DateTimeImmutable('now', $storeTz);
            $to   = $now->format('Y-m-d');
            $from = $now->modify('-29 days')->format('Y-m-d');
        }

        // Convert store-local day boundaries to UTC for SQL queries
        $fromLocal = new \DateTimeImmutable($from . ' 00:00:00', $storeTz);
        $toLocal   = new \DateTimeImmutable($to . ' 23:59:59', $storeTz);
        $fromDate  = $fromLocal->setTimezone($utcTz)->format('Y-m-d H:i:s');
        $toDate    = $toLocal->setTimezone($utcTz)->format('Y-m-d H:i:s');

        try {
            /** @var \J2Commerce\Component\J2commerce\Administrator\Model\AnalyticsModel $analyticsModel */
            $analyticsModel = $this->getModel('Analytics', 'Administrator');

            $totalRevenue = $analyticsModel->getTotalRevenue($fromDate, $toDate);
            $breakdown    = $analyticsModel->getConversionBreakdown($fromDate, $toDate);

            $data = [
                'totalRevenue'     => $totalRevenue,
                'orderCount'       => $analyticsModel->getOrderCount($fromDate, $toDate),
                'conversionRate'   => (float) ($breakdown['overallRate'] ?? 0.0),
                'totalSessions'    => (int) ($breakdown['totalSessions'] ?? 0),
                'revenueByDay'     => $analyticsModel->getRevenueByDay($fromDate, $toDate),
                'previousPeriod'   => $analyticsModel->getPreviousPeriodData($fromDate, $toDate),
                'from'             => $from,
                'to'               => $to,
                'formattedRevenue' => CurrencyHelper::format($totalRevenue),
                'currencySymbol'   => CurrencyHelper::getSymbol(),
                'currencyPosition' => CurrencyHelper::getSymbolPosition(),
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

    public function loadSampleData(): void
    {
        if (!Session::checkToken('post')) {
            $this->app->setHeader('Content-Type', 'application/json; charset=utf-8');
            $this->app->setHeader('status', '403');
            echo new JsonResponse(null, Text::_('COM_J2COMMERCE_ERROR_INVALID_TOKEN'), true);
            $this->app->close();
            return;
        }

        if (!$this->app->getIdentity()->authorise('core.admin', 'com_j2commerce')) {
            $this->app->setHeader('Content-Type', 'application/json; charset=utf-8');
            $this->app->setHeader('status', '403');
            echo new JsonResponse(null, Text::_('COM_J2COMMERCE_ERROR_ACCESS_DENIED'), true);
            $this->app->close();
            return;
        }

        try {
            $db      = Factory::getContainer()->get(\Joomla\Database\DatabaseInterface::class);
            $summary = (new SampleDataHelper($db))->load('standard');
            $this->app->setHeader('Content-Type', 'application/json; charset=utf-8');
            echo new JsonResponse($summary, Text::_('COM_J2COMMERCE_DASHBOARD_SAMPLEDATA_LOADED'));
        } catch (\Throwable $e) {
            Log::add($e->getMessage(), Log::ERROR, 'com_j2commerce');
            $this->app->setHeader('Content-Type', 'application/json; charset=utf-8');
            $this->app->setHeader('status', '500');
            echo new JsonResponse(null, Text::_('COM_J2COMMERCE_ERROR_INTERNAL'), true);
        }

        $this->app->close();
    }

    public function removeSampleData(): void
    {
        if (!Session::checkToken('post')) {
            $this->app->setHeader('Content-Type', 'application/json; charset=utf-8');
            $this->app->setHeader('status', '403');
            echo new JsonResponse(null, Text::_('COM_J2COMMERCE_ERROR_INVALID_TOKEN'), true);
            $this->app->close();
            return;
        }

        if (!$this->app->getIdentity()->authorise('core.admin', 'com_j2commerce')) {
            $this->app->setHeader('Content-Type', 'application/json; charset=utf-8');
            $this->app->setHeader('status', '403');
            echo new JsonResponse(null, Text::_('COM_J2COMMERCE_ERROR_ACCESS_DENIED'), true);
            $this->app->close();
            return;
        }

        try {
            $db      = Factory::getContainer()->get(\Joomla\Database\DatabaseInterface::class);
            $summary = (new SampleDataHelper($db))->remove();
            $this->app->setHeader('Content-Type', 'application/json; charset=utf-8');
            echo new JsonResponse($summary, Text::_('COM_J2COMMERCE_DASHBOARD_SAMPLEDATA_REMOVED'));
        } catch (\Throwable $e) {
            Log::add($e->getMessage(), Log::ERROR, 'com_j2commerce');
            $this->app->setHeader('Content-Type', 'application/json; charset=utf-8');
            $this->app->setHeader('status', '500');
            echo new JsonResponse(null, Text::_('COM_J2COMMERCE_ERROR_INTERNAL'), true);
        }

        $this->app->close();
    }
}
