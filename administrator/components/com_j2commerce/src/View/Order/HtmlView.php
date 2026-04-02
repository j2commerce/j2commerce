<?php
/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace J2Commerce\Component\J2commerce\Administrator\View\Order;

\defined('_JEXEC') or die;

use J2Commerce\Component\J2commerce\Administrator\Helper\CurrencyHelper;
use J2Commerce\Component\J2commerce\Administrator\View\AdminAssetsTrait;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Form\Form;
use Joomla\CMS\Helper\ContentHelper;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\GenericDataException;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Joomla\Registry\Registry;
use J2Commerce\Component\J2commerce\Administrator\Helper\J2CommerceHelper;

class HtmlView extends BaseHtmlView
{
    use AdminAssetsTrait;

    protected $form;
    protected $item;
    protected $state;
    protected array $orderStatuses = [];
    protected string $dateFormat = 'Y-m-d H:i:s';
    protected int $customerDays = 0;
    protected int $totalSales = 0;
    protected string $currencySymbol = '';
    protected string $currencyCode = '';
    protected bool $hasPackingSlip = false;
    protected ?Registry $params = null;

    public function display($tpl = null): void
    {
        if (!J2CommerceHelper::canAccess('j2commerce.vieworders')) {
            throw new \Exception(Text::_('JLIB_APPLICATION_ERROR_ACCESS_FORBIDDEN'), 403);
        }

        $this->loadAdminAssets();

        $model = $this->getModel();
        $model->setUseExceptions(true);

        $this->form  = $model->getForm();
        $this->item  = $model->getItem();
        $this->state = $model->getState();
        $this->orderStatuses = $this->getOrderStatuses();
        $this->params = ComponentHelper::getParams('com_j2commerce');

        $this->dateFormat = $this->params->get('date_format', 'Y-m-d H:i:s');

        // Currency formatting
        $this->currencyCode = $this->item->currency_code ?? '';
        $this->currencySymbol = CurrencyHelper::getSymbol($this->currencyCode) ?: $this->currencyCode;

        if (!empty($this->item->user_id) && (int) $this->item->user_id > 0) {
            $firstOrder = $model->getFirstOrderDate((int) $this->item->user_id);
            $this->customerDays = $firstOrder ? (new \DateTime())->diff(new \DateTime($firstOrder))->days : 0;
            $this->totalSales = $model->getOrderCountByUser((int) $this->item->user_id);
        }

        // Check if any published packing slip template exists
        $db = Factory::getContainer()->get(\Joomla\Database\DatabaseInterface::class);
        $psQuery = $db->getQuery(true)
            ->select('1')
            ->from($db->quoteName('#__j2commerce_invoicetemplates'))
            ->where($db->quoteName('invoice_type') . ' = ' . $db->quote('packingslip'))
            ->where($db->quoteName('enabled') . ' = 1')
            ->setLimit(1);
        $db->setQuery($psQuery);
        $this->hasPackingSlip = (bool) $db->loadResult();

        if (\is_array($errors = $model->getErrors()) && \count($errors)) {
            throw new GenericDataException(implode("\n", $errors), 500);
        }

        // Load payment plugin language files for translated names
        $this->loadPaymentPluginLanguages();

        // Bootstrap 5 collapse for address "View More"
        HTMLHelper::_('bootstrap.collapse', 'billingAddressCollapse');
        HTMLHelper::_('bootstrap.collapse', 'shippingAddressCollapse');

        // Bootstrap 5 modal for transaction details
        HTMLHelper::_('bootstrap.modal', 'transactionDetailsModal');

        $wa = $this->getDocument()->getWebAssetManager();
        $wa->registerAndUseStyle(
            'com_j2commerce.admin-order',
            'media/com_j2commerce/css/administrator/admin-order.css',
            [],
            ['version' => '6.0.7']
        );

        $layout = Factory::getApplication()->getInput()->getString('layout', 'view');

        if ($layout === 'edit') {
            $this->setLayout('edit');
            $wa->registerAndUseScript(
                'com_j2commerce.admin-order-edit',
                'media/com_j2commerce/js/administrator/admin-order-edit.js',
                [],
                ['defer' => true]
            );
        } elseif ($layout === 'packingslip') {
            $this->setLayout('packingslip');
        } elseif ($layout === 'invoice') {
            $this->setLayout('invoice');
        } else {
            $this->setLayout('view');
            $wa->registerAndUseScript(
                'com_j2commerce.admin-order-view',
                'media/com_j2commerce/js/administrator/admin-order.js',
                [],
                ['defer' => true]
            );
            Text::script('JACTION_DELETE');
        }

        $this->addToolbar($layout);

        parent::display($tpl);
    }

    protected function addToolbar(string $layout = 'view'): void
    {
        Factory::getApplication()->getInput()->set('hidemainmenu', true);

        $canDo         = ContentHelper::getActions('com_j2commerce');
        $canEditOrders = J2CommerceHelper::canAccess('j2commerce.editorders');
        $user          = Factory::getApplication()->getIdentity();
        $checkedOut    = !(($this->item->checked_out ?? null) === null || ($this->item->checked_out ?? 0) == $user->id);
        $toolbar       = $this->getDocument()->getToolbar();

        $orderDisplay = $this->item->invoice ?? $this->item->order_id ?? Text::_('COM_J2COMMERCE_ORDER');

        if ($layout === 'edit') {
            ToolbarHelper::title(
                Text::_('COM_J2COMMERCE_CREATE_EDIT_ORDER') . ': #' . $orderDisplay,
                'fa-solid fa-list-alt'
            );

            $toolbar->back('JTOOLBAR_BACK', 'index.php?option=com_j2commerce&view=order&layout=view&id=' . (int) $this->item->j2commerce_order_id);
            $toolbar->cancel('order.cancel', 'JTOOLBAR_CLOSE');

            if (!$checkedOut && $canEditOrders && $canDo->get('core.edit')) {
                $toolbar->apply('order.apply');
                $toolbar->save('order.save');
            }
        } else {
            ToolbarHelper::title(
                Text::_('COM_J2COMMERCE_ORDER') . ': #' . $orderDisplay,
                'fa-solid fa-list-alt'
            );

            $toolbar->cancel('order.cancel', 'JTOOLBAR_CLOSE');

            // TODO: Re-enable Edit button in a future release when order editing is fully implemented
            // if ($canEditOrders && $canDo->get('core.edit')) {
            //     $toolbar->linkButton('edit')
            //         ->text('JTOOLBAR_EDIT')
            //         ->url('index.php?option=com_j2commerce&view=order&layout=edit&id=' . (int) $this->item->j2commerce_order_id)
            //         ->icon('icon-pencil-alt');
            // }

            $toolbar->linkButton('print')
                ->text('COM_J2COMMERCE_PRINT_INVOICE')
                ->url('index.php?option=com_j2commerce&view=order&layout=invoice&tmpl=component&id=' . (int) $this->item->j2commerce_order_id)
                ->icon('icon-print')
                ->attributes(['target' => '_blank']);

            if ($this->hasPackingSlip) {
                $toolbar->linkButton('packingslip')
                    ->text('COM_J2COMMERCE_PRINT_PACKING_SLIP')
                    ->url('index.php?option=com_j2commerce&task=order.packingSlip&id=' . (int) $this->item->j2commerce_order_id)
                    ->icon('icon-list')
                    ->attributes(['target' => '_blank']);
            }
        }

        $toolbar->divider();
        ToolbarHelper::help('Orders', true, 'https://docs.j2commerce.com/sales/orders');
    }

    protected function getOrderStatuses(): array
    {
        $db = Factory::getContainer()->get(\Joomla\Database\DatabaseInterface::class);
        $query = $db->getQuery(true)
            ->select([
                $db->quoteName('j2commerce_orderstatus_id', 'value'),
                $db->quoteName('orderstatus_name', 'text'),
                $db->quoteName('orderstatus_cssclass', 'cssclass'),
            ])
            ->from($db->quoteName('#__j2commerce_orderstatuses'))
            ->where($db->quoteName('enabled') . ' = 1')
            ->order($db->quoteName('ordering') . ' ASC');

        $db->setQuery($query);

        return $db->loadObjectList() ?: [];
    }

    protected function loadPaymentPluginLanguages(): void
    {
        $lang = Factory::getLanguage();

        foreach (PluginHelper::getPlugin('j2commerce') as $plugin) {
            if (str_starts_with($plugin->name, 'payment_')) {
                $lang->load('plg_j2commerce_' . $plugin->name, JPATH_ADMINISTRATOR);
                $lang->load('plg_j2commerce_' . $plugin->name, JPATH_PLUGINS . '/j2commerce/' . $plugin->name);
            }
        }
    }
}
