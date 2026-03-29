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
use Joomla\CMS\Pagination\Pagination;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Joomla\Registry\Registry;
use J2Commerce\Component\J2commerce\Administrator\Helper\J2CommerceHelper;

class HtmlView extends BaseHtmlView
{
    use AdminAssetsTrait;

    protected $items = [];
    protected $pagination;
    protected $state;
    public $filterForm;
    public $activeFilters = [];
    protected $isModal = false;
    private $isEmptyState = false;
    public string $navbar;
    public array $orderStatuses = [];
    public bool $hasPackingSlipTemplate = false;
    public bool $canDelete = false;

    public function display($tpl = null): void
    {
        if (!J2CommerceHelper::canAccess('j2commerce.vieworders')) {
            throw new \Exception(Text::_('JLIB_APPLICATION_ERROR_ACCESS_FORBIDDEN'), 403);
        }

        $this->loadAdminAssets();
        $this->navbar = $this->getNavbar();

        $model = $this->getModel();
        $model->setUseExceptions(true);

        $this->items         = $model->getItems();
        $this->pagination    = $model->getPagination();
        $this->state         = $model->getState();
        $this->filterForm    = $model->getFilterForm();
        $this->activeFilters = $model->getActiveFilters();
        $this->orderStatuses = $this->loadOrderStatuses();
        $this->hasPackingSlipTemplate = $this->hasPackingSlipTemplate();
        $this->canDelete = $this->getCurrentUser()->authorise('core.delete', 'com_j2commerce');

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
            ['version' => '6.0.7']
        );

        parent::display($tpl);
    }

    protected function getNavbar(): string
    {
        $displayData = [
            'items' => MenuHelper::getMenuItems(),
            'active' => MenuHelper::getActiveView()
        ];

        return LayoutHelper::render('navbar.default', $displayData, JPATH_COMPONENT_ADMINISTRATOR . '/layouts');
    }

    protected function addToolbar(): void
    {
        $canDo = ContentHelper::getActions('com_j2commerce', 'order');
        $user  = $this->getCurrentUser();
        $toolbar = $this->getDocument()->getToolbar();

        ToolbarHelper::title(Text::_('COM_J2COMMERCE_ORDERS'), 'fa-solid fa-list-alt');

        $canEditOrders = J2CommerceHelper::canAccess('j2commerce.editorders');

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

            // Export button
            if ($canEditOrders && $canDo->get('core.edit')) {
                $toolbar->standardButton('export')
                    ->text('COM_J2COMMERCE_EXPORT')
                    ->task('orders.export')
                    ->listCheck(true)
                    ->icon('icon-download');
            }
        }

        if ($user->authorise('core.admin', 'com_j2commerce') || $user->authorise('core.options', 'com_j2commerce')) {
            $toolbar->preferences('com_j2commerce');
        }

        ToolbarHelper::help('Orders', true, 'https://docs.j2commerce.com/sales/orders');
    }

    protected function loadOrderStatuses(): array
    {
        $db = Factory::getContainer()->get(\Joomla\Database\DatabaseInterface::class);
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

    protected function loadPaymentPluginLanguages(): void
    {
        $lang = Factory::getApplication()->getLanguage();
        $db = Factory::getContainer()->get(\Joomla\Database\DatabaseInterface::class);

        $query = $db->getQuery(true)
            ->select($db->quoteName('element'))
            ->from($db->quoteName('#__extensions'))
            ->where($db->quoteName('type') . ' = ' . $db->quote('plugin'))
            ->where($db->quoteName('folder') . ' = ' . $db->quote('j2commerce'))
            ->where($db->quoteName('element') . ' LIKE ' . $db->quote('payment_%'));
        $db->setQuery($query);

        foreach ($db->loadColumn() ?: [] as $element) {
            $lang->load('plg_j2commerce_' . $element, JPATH_ADMINISTRATOR);
            $lang->load('plg_j2commerce_' . $element, JPATH_PLUGINS . '/j2commerce/' . $element);
        }
    }

    private function hasPackingSlipTemplate(): bool
    {
        $db = Factory::getContainer()->get(\Joomla\Database\DatabaseInterface::class);
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
