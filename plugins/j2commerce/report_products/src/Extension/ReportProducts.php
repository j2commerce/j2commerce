<?php

/**
 * @package     J2Commerce
 * @subpackage  Plugin.J2Commerce.ReportProducts
 *
 * @copyright   Copyright (C) 2024-2026 J2Commerce, LLC. All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

declare(strict_types=1);

namespace J2Commerce\Plugin\J2Commerce\ReportProducts\Extension;

use J2Commerce\Component\J2commerce\Administrator\Helper\J2CommerceHelper;
use J2Commerce\Plugin\J2Commerce\ReportProducts\Model\ReportproductsModel;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Language;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;
use Joomla\Database\DatabaseAwareTrait;
use Joomla\Database\DatabaseInterface;
use Joomla\Event\DispatcherInterface;
use Joomla\Event\Event;
use Joomla\Event\SubscriberInterface;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Products Report Plugin for J2Commerce
 *
 * Provides product sales report with bar chart, filtering, and CSV export.
 * Fully self-contained — model, filter XML, and templates all live in the plugin directory.
 *
 * @since  6.0.0
 */
final class ReportProducts extends CMSPlugin implements SubscriberInterface
{
    use DatabaseAwareTrait;

    /**
     * @var  boolean
     * @since  6.0.0
     */
    protected $autoloadLanguage = true;

    /**
     * Plugin element name
     *
     * @var  string
     * @since  6.0.0
     */
    private string $_element = 'report_products';

    /**
     * Language instance
     *
     * @var  Language
     * @since  6.0.0
     */
    private Language $language;

    /**
     * Constructor
     *
     * @param  DispatcherInterface  $dispatcher  The dispatcher
     * @param  array                $config      Plugin configuration
     * @param  Language             $language    The language object
     * @param  DatabaseInterface    $db          The database object
     *
     * @since  6.0.0
     */
    public function __construct(
        DispatcherInterface $dispatcher,
        array $config,
        Language $language,
        DatabaseInterface $db
    ) {
        parent::__construct($dispatcher, $config);

        $this->language = $language;
        $this->setDatabase($db);
    }

    /**
     * Returns events this subscriber listens to.
     *
     * @return  array
     * @since  6.0.0
     */
    public static function getSubscribedEvents(): array
    {
        return [
            // New dispatcher events (ReportpluginController pattern)
            'onJ2CommerceReportPluginView'   => 'onReportPluginView',
            'onJ2CommerceReportPluginAjax'   => 'onReportPluginAjax',
            'onJ2CommerceReportPluginExport' => 'onReportPluginExport',
            // Legacy events (backwards compatibility)
            'onJ2CommerceGetReportView'     => 'onGetReportView',
            'onJ2CommerceGetReportExported' => 'onGetReportExported',
        ];
    }

    /**
     * Render the report view (legacy route).
     *
     * @param  Event  $event  The event object. Arg[0] = plugin row from #__extensions.
     *
     * @return  void
     * @since  6.0.0
     */
    public function onGetReportView(Event $event): void
    {
        $args = $event->getArguments();
        $row  = $args[0] ?? null;

        if (!$this->isMe($row)) {
            return;
        }

        $html = $this->renderReport();

        $result   = $event->getArgument('result', []);
        $result[] = $html;
        $event->setArgument('result', $result);
    }

    /**
     * Export report data as CSV (legacy route).
     *
     * @param  Event  $event  The event object.
     *
     * @return  void
     * @since  6.0.0
     */
    public function onGetReportExported(Event $event): void
    {
        $args = $event->getArguments();
        $row  = $args[0] ?? null;

        if (!$this->isMe($row)) {
            return;
        }

        $result   = $event->getArgument('result', []);
        $result[] = $this->buildExportData();
        $event->setArgument('result', $result);
    }

    /**
     * Handle the ReportpluginController view event.
     *
     * Sets the page title, adds toolbar buttons, instantiates the plugin's
     * ListModel directly, and passes model artifacts + rendered HTML via event arguments.
     *
     * @param  Event  $event  The event object with plugin, pluginview, toolbar, input args.
     *
     * @return  void
     * @since  6.0.0
     */
    public function onReportPluginView(Event $event): void
    {
        $plugin = $event->getArgument('plugin', '');

        if ($plugin !== $this->_element) {
            return;
        }

        // Set page title via event
        $event->setArgument('title', Text::_('PLG_J2COMMERCE_REPORT_PRODUCTS'));

        // Add Export CSV toolbar button
        $toolbar = $event->getArgument('toolbar');

        if ($toolbar) {
            $exportUrl = Route::_(
                'index.php?option=com_j2commerce&task=reportplugin.exportCsv&plugin=' . $this->_element
                . '&' . Session::getFormToken() . '=1',
                false
            );
            $toolbar->linkButton('export', 'PLG_J2COMMERCE_REPORT_PRODUCTS_EXPORT_CSV')
                ->url($exportUrl)
                ->icon('icon-download');
        }

        // Load plugin language so filter form labels resolve correctly
        $this->language->load(
            'plg_j2commerce_' . $this->_element,
            JPATH_PLUGINS . '/j2commerce/' . $this->_element,
            null,
            true
        );

        // Instantiate model directly — self-contained in plugin directory
        $model = $this->createModel();

        // Pass model artifacts to HtmlView via event args
        $items = $model->getItems();

        $event->setArgument('filterForm', $model->getFilterForm());
        $event->setArgument('activeFilters', $model->getActiveFilters());
        $event->setArgument('state', $model->getState());
        $event->setArgument('pagination', $model->getPagination());
        $event->setArgument('items', $items);

        // Build chart data + template variables
        $vars             = new \stdClass();
        $vars->items      = $items;
        $vars->currency   = J2CommerceHelper::currency();
        $vars->listOrder  = $model->getState('list.ordering', 'total_final_price_with_tax');
        $vars->listDirn   = $model->getState('list.direction', 'DESC');

        $vars->chartLabels = [];
        $vars->chartValues = [];

        foreach ($items as $item) {
            $vars->chartLabels[] = $item->orderitem_name;
            $vars->chartValues[] = round((float) $item->total_final_price_with_tax, 2);
        }

        // Register Chart.js before template rendering — use registerAndUseScript
        // with direct path (same pattern as Analytics view) for reliable loading
        if (!empty($vars->chartLabels)) {
            Factory::getApplication()->getDocument()->getWebAssetManager()
                ->registerAndUseScript(
                    'chartjs',
                    'media/com_j2commerce/vendor/chartjs/js/chart.umd.min.js',
                    [],
                    ['defer' => false]
                );
        }

        $html = $this->renderTemplate('report', $vars);

        $result   = $event->getArgument('result', []);
        $result[] = $html;
        $event->setArgument('result', $result);
    }

    /**
     * Handle AJAX requests from the report view.
     *
     * @param  Event  $event  The event object with plugin, action, input args.
     *
     * @return  void
     * @since  6.0.0
     */
    public function onReportPluginAjax(Event $event): void
    {
        $plugin = $event->getArgument('plugin', '');

        if ($plugin !== $this->_element) {
            return;
        }

        $action = $event->getArgument('action', '');

        if ($action === 'getChartData') {
            $model = $this->createModel();

            $items  = $model->getItems();
            $labels = [];
            $values = [];

            foreach ($items as $item) {
                $labels[] = $item->orderitem_name;
                $values[] = round((float) $item->total_final_price_with_tax, 2);
            }

            $event->setArgument('jsonResult', [
                'labels' => $labels,
                'values' => $values,
            ]);
        }
    }

    /**
     * Handle CSV export via the ReportpluginController route.
     *
     * @param  Event  $event  The event object with plugin, input args.
     *
     * @return  void
     * @since  6.0.0
     */
    public function onReportPluginExport(Event $event): void
    {
        $plugin = $event->getArgument('plugin', '');

        if ($plugin !== $this->_element) {
            return;
        }

        $result   = $event->getArgument('result', []);
        $result[] = $this->buildExportData();
        $event->setArgument('result', $result);
    }

    /**
     * Build the export data array for CSV output.
     *
     * Uses the ReportproductsModel with pagination removed for full export.
     *
     * @return  array  Array of stdClass rows (data rows + totals row).
     * @since  6.0.0
     */
    private function buildExportData(): array
    {
        $model = $this->createModel();

        // Force populateState() before overriding limit — prevents it from resetting our value
        $model->getState();
        $model->setState('list.limit', 0);
        $items    = $model->getItems();
        $currency = J2CommerceHelper::currency();

        $name         = Text::_('PLG_J2COMMERCE_REPORT_PRODUCTS_PRODUCT_NAME');
        $quantity     = Text::_('PLG_J2COMMERCE_REPORT_PRODUCTS_TOTAL_QUANTITY');
        $discountText = Text::_('PLG_J2COMMERCE_REPORT_PRODUCTS_DISCOUNT');
        $taxText      = Text::_('PLG_J2COMMERCE_REPORT_PRODUCTS_TAX');
        $withoutTax   = Text::_('PLG_J2COMMERCE_REPORT_PRODUCTS_WITHOUT_TAX');
        $withTax      = Text::_('PLG_J2COMMERCE_REPORT_PRODUCTS_WITH_TAX');
        $totalText    = Text::_('PLG_J2COMMERCE_REPORT_PRODUCTS_TOTAL');

        $export          = [];
        $qtyTotal        = 0;
        $discountTotal   = 0.0;
        $totalWithoutTax = 0.0;
        $totalWithTax    = 0.0;
        $totalTax        = 0.0;

        foreach ($items as $item) {
            $qtyTotal += (int) $item->total_qty;
            $discountTotal += (float) $item->total_item_discount + (float) $item->total_item_discount_tax;
            $totalWithoutTax += (float) $item->total_final_price_without_tax;
            $totalWithTax += (float) $item->total_final_price_with_tax;
            $totalTax += (float) $item->total_item_tax;

            $csvRow                = new \stdClass();
            $csvRow->$name         = $item->orderitem_name . ', ' . Text::_('PLG_J2COMMERCE_REPORT_PRODUCTS_SKU') . ': ' . $item->orderitem_sku;
            $csvRow->$quantity     = $item->total_qty;
            $csvRow->$discountText = $currency->format((float) $item->total_item_discount + (float) $item->total_item_discount_tax);
            $csvRow->$taxText      = $currency->format((float) $item->total_item_tax);
            $csvRow->$withoutTax   = $currency->format((float) $item->total_final_price_without_tax);
            $csvRow->$withTax      = $currency->format((float) $item->total_final_price_with_tax);
            $export[]              = $csvRow;
        }

        // Totals row
        $finalRow                = new \stdClass();
        $finalRow->$name         = $totalText;
        $finalRow->$quantity     = $qtyTotal;
        $finalRow->$discountText = $currency->format($discountTotal);
        $finalRow->$taxText      = $currency->format($totalTax);
        $finalRow->$withoutTax   = $currency->format($totalWithoutTax);
        $finalRow->$withTax      = $currency->format($totalWithTax);
        $export[]                = $finalRow;

        return $export;
    }

    /**
     * Create the plugin's report model instance.
     *
     * Instantiates the model directly from the plugin namespace (not via MVC factory),
     * making this plugin fully self-contained without component model files.
     *
     * @return  ReportproductsModel
     *
     * @since   6.0.0
     */
    private function createModel(): ReportproductsModel
    {
        $model = new ReportproductsModel(['ignore_request' => false]);
        $model->setDatabase($this->getDatabase());

        return $model;
    }

    /**
     * Render a plugin template file with output buffering.
     *
     * @param  string     $layout       The template layout name (without .php).
     * @param  \stdClass  $displayData  The data to pass to the template.
     *
     * @return  string  The rendered HTML.
     * @since  6.0.0
     */
    private function renderTemplate(string $layout, \stdClass $displayData): string
    {
        // Prevent path traversal
        $layout = basename($layout);
        $path   = JPATH_PLUGINS . '/j2commerce/' . $this->_element . '/tmpl/' . $layout . '.php';

        if (!is_file($path)) {
            return '';
        }

        ob_start();
        include $path;

        return ob_get_clean();
    }

    /**
     * Render the full report HTML (legacy route).
     *
     * @return  string
     * @since  6.0.0
     */
    private function renderReport(): string
    {
        $app = Factory::getApplication();

        // Use plugin model directly for legacy route too
        $model = $this->createModel();

        $items = $model->getItems();

        $vars           = new \stdClass();
        $vars->items    = $items;
        $vars->currency = J2CommerceHelper::currency();
        $vars->reportId = $app->getInput()->getInt('id', 0);

        // Build chart data
        $vars->chartLabels = [];
        $vars->chartValues = [];

        foreach ($items as $item) {
            $vars->chartLabels[] = $item->orderitem_name;
            $vars->chartValues[] = round((float) $item->total_final_price_with_tax, 2);
        }

        $vars->listOrder = $model->getState('list.ordering', 'total_final_price_with_tax');
        $vars->listDirn  = $model->getState('list.direction', 'DESC');

        $vars->formAction = Route::_('index.php?option=com_j2commerce&view=reports&task=view&id=' . $vars->reportId);

        // Register Chart.js before template rendering
        if (!empty($vars->chartLabels)) {
            $app->getDocument()->getWebAssetManager()
                ->registerAndUseScript(
                    'chartjs',
                    'media/com_j2commerce/vendor/chartjs/js/chart.umd.min.js',
                    [],
                    ['defer' => false]
                );
        }

        // Render template via output buffering
        $layoutFile  = PluginHelper::getLayoutPath('j2commerce', $this->_element);
        $displayData = $vars;

        ob_start();
        include $layoutFile;

        return ob_get_clean();
    }

    /**
     * Check if this plugin matches the given report element.
     *
     * @param  mixed  $row  Plugin row object or string element name
     *
     * @return  bool
     * @since  6.0.0
     */
    private function isMe($row): bool
    {
        if (\is_object($row) && !empty($row->element)) {
            return $row->element === $this->_element;
        }

        if (\is_string($row)) {
            return $row === $this->_element;
        }

        return false;
    }
}
