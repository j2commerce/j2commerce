<?php

/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace J2Commerce\Component\J2commerce\Administrator\Model;

\defined('_JEXEC') or die;

use Joomla\CMS\Form\Form;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Joomla\CMS\MVC\Model\ListModel;
use Joomla\Database\QueryInterface;

/**
 * Base model for J2Commerce report plugins.
 *
 * Report plugins extend this class instead of ListModel directly. It provides:
 * - Form path resolution so plugins can store filter XML in their own directory
 * - Common date filter helper used by most report plugins
 * - GROUP BY-compatible getTotal() override
 *
 * Usage in plugin model constructor:
 *   parent::__construct($config);
 *   $this->setFormPath(JPATH_PLUGINS . '/j2commerce/report_example/forms');
 *
 * @since  6.0.0
 */
abstract class BaseReportModel extends ListModel
{
    /**
     * Additional form paths for plugin filter forms.
     *
     * @var    array
     * @since  6.0.0
     */
    protected array $formPaths = [];

    /**
     * Constructor.
     *
     * Sets $this->option before parent to prevent BaseDatabaseModel from
     * guessing it via ComponentHelper::getComponentName(), which fails for
     * plugin namespaces that lack the \Component segment.
     *
     * @param   array                          $config   Model configuration array.
     * @param   MVCFactoryInterface|null       $factory  The MVC factory (optional).
     *
     * @since   6.0.0
     */
    public function __construct($config = [], ?MVCFactoryInterface $factory = null)
    {
        // Pre-set option so BaseDatabaseModel skips getComponentName() —
        // plugin namespaces lack \Component and produce garbled option/context.
        $this->option = 'com_j2commerce';

        parent::__construct($config, $factory);
    }

    /**
     * Register a plugin's forms directory for filter form discovery.
     *
     * Call this in the plugin model constructor after parent::__construct().
     * The path will be added to Joomla's Form::addFormPath() before loading
     * the filter form, allowing plugin-owned XML to be found.
     *
     * @param   string  $path  Absolute path to forms directory
     *
     * @return  void
     *
     * @since   6.0.0
     */
    public function setFormPath(string $path): void
    {
        if (!\in_array($path, $this->formPaths, true)) {
            $this->formPaths[] = $path;
        }
    }

    /**
     * Get the filter form with plugin form paths registered.
     *
     * Overrides parent to add plugin form paths before loading form,
     * allowing plugins to store their filter XML in the plugin directory
     * instead of the component's forms/ directory.
     *
     * @param   array  $data      Data to bind to the form.
     * @param   bool   $loadData  Load form data from model state.
     *
     * @return  Form|null
     *
     * @since   6.0.0
     */
    public function getFilterForm($data = [], $loadData = true)
    {
        foreach ($this->formPaths as $path) {
            Form::addFormPath($path);
        }

        return parent::getFilterForm($data, $loadData);
    }

    /**
     * Apply date range filter based on preset or custom range.
     *
     * Common helper for report plugins to filter by date presets:
     * today, this_week, this_month, this_year, last_7day, last_month,
     * last_year, and custom (reads from_date / to_date from model state).
     *
     * @param   QueryInterface  $query     The query object.
     * @param   string          $dateType  The date filter type from state.
     * @param   string          $column    Fully qualified date column (e.g. 'o.created_on').
     *
     * @return  void
     *
     * @since   6.0.0
     */
    protected function applyDateFilter(QueryInterface $query, string $dateType, string $column): void
    {
        if (empty($dateType)) {
            return;
        }

        $db = $this->getDatabase();

        switch ($dateType) {
            case 'today':
                $todayStart = date('Y-m-d') . ' 00:00:00';
                $todayEnd   = date('Y-m-d') . ' 23:59:59';
                $query->where($db->quoteName($column) . ' BETWEEN :todayStart AND :todayEnd')
                    ->bind(':todayStart', $todayStart)
                    ->bind(':todayEnd', $todayEnd);
                break;

            case 'this_week':
                $weekStart = date('Y-m-d', strtotime('monday this week')) . ' 00:00:00';
                $weekEnd   = date('Y-m-d', strtotime('sunday this week')) . ' 23:59:59';
                $query->where($db->quoteName($column) . ' BETWEEN :weekStart AND :weekEnd')
                    ->bind(':weekStart', $weekStart)
                    ->bind(':weekEnd', $weekEnd);
                break;

            case 'this_month':
                $monthStart = date('Y-m-01') . ' 00:00:00';
                $monthEnd   = date('Y-m-t') . ' 23:59:59';
                $query->where($db->quoteName($column) . ' BETWEEN :monthStart AND :monthEnd')
                    ->bind(':monthStart', $monthStart)
                    ->bind(':monthEnd', $monthEnd);
                break;

            case 'this_year':
                $yearStart = date('Y') . '-01-01 00:00:00';
                $yearEnd   = date('Y') . '-12-31 23:59:59';
                $query->where($db->quoteName($column) . ' BETWEEN :yearStart AND :yearEnd')
                    ->bind(':yearStart', $yearStart)
                    ->bind(':yearEnd', $yearEnd);
                break;

            case 'last_7day':
                $start = date('Y-m-d', strtotime('-7 days')) . ' 00:00:00';
                $end   = date('Y-m-d') . ' 23:59:59';
                $query->where($db->quoteName($column) . ' BETWEEN :last7Start AND :last7End')
                    ->bind(':last7Start', $start)
                    ->bind(':last7End', $end);
                break;

            case 'last_month':
                $start = date('Y-m-01', strtotime('first day of last month')) . ' 00:00:00';
                $end   = date('Y-m-t', strtotime('last day of last month')) . ' 23:59:59';
                $query->where($db->quoteName($column) . ' BETWEEN :lastMonthStart AND :lastMonthEnd')
                    ->bind(':lastMonthStart', $start)
                    ->bind(':lastMonthEnd', $end);
                break;

            case 'last_year':
                $lastYear      = (string) ((int) date('Y') - 1);
                $lastYearStart = $lastYear . '-01-01 00:00:00';
                $lastYearEnd   = $lastYear . '-12-31 23:59:59';
                $query->where($db->quoteName($column) . ' BETWEEN :lastYearStart AND :lastYearEnd')
                    ->bind(':lastYearStart', $lastYearStart)
                    ->bind(':lastYearEnd', $lastYearEnd);
                break;

            case 'custom':
                $fromDate = $this->getState('filter.order_from_date', '');
                $toDate   = $this->getState('filter.order_to_date', '');

                if (
                    !empty($fromDate) && !empty($toDate)
                    && preg_match('/^\d{4}-\d{2}-\d{2}$/', $fromDate)
                    && preg_match('/^\d{4}-\d{2}-\d{2}$/', $toDate)
                ) {
                    $fromDateFull = $fromDate . ' 00:00:00';
                    $toDateFull   = $toDate . ' 23:59:59';
                    $query->where($db->quoteName($column) . ' BETWEEN :customStart AND :customEnd')
                        ->bind(':customStart', $fromDateFull)
                        ->bind(':customEnd', $toDateFull);
                }
                break;
        }
    }
}
