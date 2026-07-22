<?php

/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace J2Commerce\Component\J2commerce\Administrator\View\Orders;

\defined('_JEXEC') or die;

use J2Commerce\Component\J2commerce\Administrator\Helper\J2CommerceHelper;
use J2Commerce\Component\J2commerce\Administrator\Helper\MenuHelper;
use J2Commerce\Component\J2commerce\Administrator\View\AdminAssetsTrait;
use Joomla\CMS\Factory;
use Joomla\CMS\Form\Form;
use Joomla\CMS\Helper\ContentHelper;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\MVC\View\GenericDataException;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\ToolbarHelper;

class HtmlView extends BaseHtmlView
{
    use AdminAssetsTrait;

    protected $items = [];
    protected $pagination;
    protected $state;
    public $filterForm;
    public $activeFilters = [];
    protected $isModal    = false;
    private $isEmptyState = false;
    public string $navbar;
    public array $orderStatuses         = [];
    public ?Form $exportForm            = null;
    public int $exportCount             = 0;
    public bool $hasPackingSlipTemplate = false;
    public bool $canDelete              = false;

    public function display($tpl = null): void
    {
        if (!J2CommerceHelper::canAccess('j2commerce.vieworders')) {
            throw new \Exception(Text::_('JLIB_APPLICATION_ERROR_ACCESS_FORBIDDEN'), 403);
        }

        $this->loadAdminAssets();
        $this->navbar = $this->getNavbar();

        $model = $this->getModel();
        $model->setUseExceptions(true);

        $this->items                  = $model->getItems();
        $this->pagination             = $model->getPagination();
        $this->state                  = $model->getState();
        $this->filterForm             = $model->getFilterForm();
        $this->activeFilters          = $model->getActiveFilters();
        $this->orderStatuses          = $this->loadOrderStatuses();
        $this->exportForm             = $this->buildExportForm();
        $this->exportCount            = (int) $model->getOrdersTotal();
        $this->hasPackingSlipTemplate = $this->hasPackingSlipTemplate();
        $this->canDelete              = $this->getCurrentUser()->authorise('core.delete', 'com_j2commerce');

        if ((!\is_array($this->items) || !\count($this->items)) && $this->isEmptyState = $model->getIsEmptyState()) {
            $this->setLayout('emptystate');
        }

        if (\is_array($errors = $model->getErrors()) && \count($errors)) {
            throw new GenericDataException(implode("\n", $errors), 500);
        }

        $this->isModal = Factory::getApplication()->getInput()->get('layout') === 'modal';

        if (!$this->isModal) {
            $this->addToolbar();
        }

        // Load payment plugin language files so Text::_() can translate plugin names
        $this->loadPaymentPluginLanguages();

        // Initialize Bootstrap 5 click-only tooltips
        HTMLHelper::_('bootstrap.tooltip', '.clickTooltip', ['trigger' => 'click']);

        // Register admin order list JS
        $wa = $this->getDocument()->getWebAssetManager();
        $wa->registerAndUseScript(
            'com_j2commerce.admin-order-list',
            'media/com_j2commerce/js/administrator/admin-order-list.js',
            [],
            ['defer' => true]
        );
        $wa->registerAndUseStyle(
            'com_j2commerce.admin-order',
            'media/com_j2commerce/css/administrator/admin-order.css',
            [],
            ['version' => '6.0.8']
        );

        if (!$this->isModal && !$this->isEmptyState) {
            $wa->registerAndUseScript(
                'com_j2commerce.admin-order-export',
                'media/com_j2commerce/js/administrator/admin-order-export.js',
                [],
                ['defer' => true]
            );

            Text::script('COM_J2COMMERCE_EXPORT_CALCULATING');
            Text::script('COM_J2COMMERCE_N_ORDERS_WILL_BE_EXPORTED');
            Text::script('COM_J2COMMERCE_N_ORDERS_WILL_BE_EXPORTED_1');
        }

        parent::display($tpl);
    }

    protected function getNavbar(): string
    {
        $displayData = [
            'items'  => MenuHelper::getMenuItems(),
            'active' => MenuHelper::getActiveView(),
        ];

        return LayoutHelper::render('navbar.default', $displayData, JPATH_COMPONENT_ADMINISTRATOR . '/layouts');
    }

    protected function addToolbar(): void
    {
        $canDo   = ContentHelper::getActions('com_j2commerce', 'order');
        $user    = $this->getCurrentUser();
        $toolbar = $this->getDocument()->getToolbar();

        ToolbarHelper::title(Text::_('COM_J2COMMERCE_ORDERS'), 'fa-solid fa-list-alt');

        $canEditOrders = J2CommerceHelper::canAccess('j2commerce.editorders');

        if ($canEditOrders && $canDo->get('core.create')) {
            $toolbar->linkButton('new')
                ->text('COM_J2COMMERCE_NEW_ORDER')
                ->url('index.php?option=com_j2commerce&view=order&layout=edit&id=0')
                ->icon('icon-new');
        }

        if (!$this->isEmptyState) {
            // Bulk Actions popup (Change Status, Print Packing Slips, Delete)
            if ($canEditOrders && $canDo->get('core.edit.state')) {
                $toolbar->popupButton('batch', 'COM_J2COMMERCE_BULK_ACTIONS')
                    ->popupType('inline')
                    ->textHeader(Text::_('COM_J2COMMERCE_BATCH_TITLE'))
                    ->url('#joomla-dialog-batch')
                    ->modalWidth('800px')
                    ->modalHeight('fit-content')
                    ->listCheck(true)
                    ->icon('icon-ellipsis-h');
            }

            // Export button — opens an offcanvas panel instead of submitting a task,
            // so standardButton (which auto-submits via Joomla.submitbutton) can't be used.
            if ($canEditOrders && $canDo->get('core.edit')) {
                $toolbar->customButton('export')
                    ->html(
                        '<joomla-toolbar-button>'
                        . '<button type="button" class="btn btn-sm btn-secondary"'
                        . ' data-bs-toggle="offcanvas" data-bs-target="#orderExportOffcanvas"'
                        . ' aria-controls="orderExportOffcanvas">'
                        . '<span class="icon-download" aria-hidden="true"></span> '
                        . htmlspecialchars(Text::_('COM_J2COMMERCE_EXPORT'), ENT_QUOTES, 'UTF-8')
                        . '</button>'
                        . '</joomla-toolbar-button>'
                    );
            }
        }

        if ($user->authorise('core.admin', 'com_j2commerce') || $user->authorise('core.options', 'com_j2commerce')) {
            $toolbar->preferences('com_j2commerce');
        }

        ToolbarHelper::help(Text::_('COM_J2COMMERCE_ORDERS'), true, 'https://docs.j2commerce.com/v6/sales/orders');
    }

    protected function loadOrderStatuses(): array
    {
        $db    = Factory::getContainer()->get(\Joomla\Database\DatabaseInterface::class);
        $query = $db->getQuery(true)
            ->select([
                $db->quoteName('j2commerce_orderstatus_id'),
                $db->quoteName('orderstatus_name'),
                $db->quoteName('orderstatus_cssclass'),
            ])
            ->from($db->quoteName('#__j2commerce_orderstatuses'))
            ->where($db->quoteName('enabled') . ' = 1')
            ->order($db->quoteName('ordering') . ' ASC');

        $db->setQuery($query);

        return $db->loadObjectList() ?: [];
    }

    /**
     * Build the export offcanvas form, pre-filled from the current filter-bar state.
     */
    protected function buildExportForm(): Form
    {
        $form = new Form('com_j2commerce.export_orders', ['control' => '']);
        $form->loadFile(JPATH_ADMINISTRATOR . '/components/com_j2commerce/forms/export_orders.xml');

        $data = [
            'search'      => (string) $this->state->get('filter.search', ''),
            'since'       => (string) $this->state->get('filter.since', ''),
            'until'       => (string) $this->state->get('filter.until', ''),
            'coupon_code' => (string) $this->state->get('filter.coupon_code', ''),
        ];

        foreach (['from_j2commerce_order_id', 'to_j2commerce_order_id'] as $intField) {
            $value = (int) $this->state->get('filter.' . $intField, 0);

            if ($value > 0) {
                $data[$intField] = $value;
            }
        }

        foreach (['amount_from', 'amount_to'] as $floatField) {
            $value = (float) $this->state->get('filter.' . $floatField, 0);

            if ($value > 0) {
                $data[$floatField] = $value;
            }
        }

        $paymentType = $this->state->get('filter.payment_type');

        if (\is_string($paymentType) && $paymentType !== '') {
            $data['payment_type'] = [$paymentType];
        }

        $orderStateId = (int) $this->state->get('filter.order_state_id', 0);

        if ($orderStateId > 0) {
            $data['order_state_id'] = [$orderStateId];
        }

        $userId = (int) $this->state->get('filter.user_id', 0);

        if ($userId > 0) {
            $data['users'] = [$userId];
        }

        $form->bind($data);

        return $form;
    }

    protected function loadPaymentPluginLanguages(): void
    {
        $lang = Factory::getApplication()->getLanguage();
        $db   = Factory::getContainer()->get(\Joomla\Database\DatabaseInterface::class);

        $query = $db->getQuery(true)
            ->select($db->quoteName('element'))
            ->from($db->quoteName('#__extensions'))
            ->where($db->quoteName('type') . ' = ' . $db->quote('plugin'))
            ->where($db->quoteName('folder') . ' = ' . $db->quote('j2commerce'))
            ->where($db->quoteName('element') . ' LIKE ' . $db->quote('payment_%'));
        $db->setQuery($query);

        foreach ($db->loadColumn() ?: [] as $element) {
            $ext = 'plg_j2commerce_' . $element;
            $lang->load($ext . '.sys', JPATH_ADMINISTRATOR)
                || $lang->load($ext . '.sys', JPATH_PLUGINS . '/j2commerce/' . $element);
            $lang->load($ext, JPATH_ADMINISTRATOR)
                || $lang->load($ext, JPATH_PLUGINS . '/j2commerce/' . $element);
        }
    }

    private function hasPackingSlipTemplate(): bool
    {
        $db    = Factory::getContainer()->get(\Joomla\Database\DatabaseInterface::class);
        $query = $db->getQuery(true)
            ->select('1')
            ->from($db->quoteName('#__j2commerce_invoicetemplates'))
            ->where($db->quoteName('invoice_type') . ' = ' . $db->quote('packingslip'))
            ->where($db->quoteName('enabled') . ' = 1')
            ->setLimit(1);
        $db->setQuery($query);

        return (bool) $db->loadResult();
    }
}
