<?php
/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace J2Commerce\Component\J2commerce\Administrator\View\Invoicetemplates;

defined('_JEXEC') or die;

use J2Commerce\Component\J2commerce\Administrator\Helper\MenuHelper;
use J2Commerce\Component\J2commerce\Administrator\View\AdminAssetsTrait;
use Joomla\CMS\Factory;
use Joomla\CMS\Helper\ContentHelper;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\MVC\View\GenericDataException;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\Toolbar;
use Joomla\CMS\Toolbar\ToolbarHelper;

/**
 * Invoicetemplates View
 *
 * @since  6.0.0
 */
class HtmlView extends BaseHtmlView
{
    use AdminAssetsTrait;

    /**
     * An array of items
     *
     * @var  array
     */
    protected $items;

    /**
     * The pagination object
     *
     * @var  \Joomla\CMS\Pagination\Pagination
     */
    protected $pagination;

    /**
     * The model state
     *
     * @var   \Joomla\CMS\Object\CMSObject
     */
    protected $state;

    /**
     * Form object for search filters
     *
     * @var  \Joomla\CMS\Form\Form
     */
    public $filterForm;

    /**
     * The active search filters
     *
     * @var  array
     */
    public $activeFilters;

    /**
     * Is this view an Empty State
     *
     * @var   boolean
     * @since 6.0.0
     */
    private $isEmptyState = false;

    /**
     * Display the view
     *
     * @param   string  $tpl  The name of the template file to parse
     *
     * @return  void
     *
     * @throws  \Exception
     */
    public function display($tpl = null)
    {
        $this->loadAdminAssets();

        $this->navbar = $this->getNavbar();

        $model = $this->getModel();
        $this->items = $model->getItems();
        $this->pagination = $model->getPagination();
        $this->state = $model->getState();
        $this->filterForm = $model->getFilterForm();
        $this->activeFilters = $model->getActiveFilters();

        if ((!is_array($this->items) || !\count($this->items)) && $this->isEmptyState = $this->get('IsEmptyState')) {
            $this->setLayout('emptystate');
        }

        // Check for errors.
        if (is_array($errors = $this->get('Errors')) && \count($errors)) {
            throw new GenericDataException(implode("\n", $errors), 500);
        }

        $this->addToolbar();

        parent::display($tpl);
    }

    /**
     * Get navbar configuration
     *
     * @return string
     */
    protected function getNavbar(): string
    {
        $displayData = [
            'items' => MenuHelper::getMenuItems(),
            'active' => MenuHelper::getActiveView()
        ];

        return LayoutHelper::render('navbar.default', $displayData, JPATH_COMPONENT_ADMINISTRATOR . '/layouts');
    }

    /**
     * Add the page title and toolbar.
     *
     * @return  void
     *
     * @since   6.0.0
     */
    protected function addToolbar()
    {
        $canDo = ContentHelper::getActions('com_j2commerce', 'invoicetemplate');
        $user  = Factory::getApplication()->getIdentity();

        // Get the toolbar object instance
        $toolbar = Factory::getApplication()->getDocument()->getToolbar();

        ToolbarHelper::title(Text::_('COM_J2COMMERCE_INVOICETEMPLATES_MANAGER'), 'fa-solid fa-print');

        if ($canDo->get('core.create')) {
            $toolbar->addNew('invoicetemplate.add');
        }

        if (!$this->isEmptyState) {
            if ($canDo->get('core.edit.state')) {
                $dropdown = $toolbar->dropdownButton('status-group')
                    ->text('JTOOLBAR_CHANGE_STATUS')
                    ->toggleSplit(false)
                    ->icon('icon-ellipsis-h')
                    ->buttonClass('btn btn-action')
                    ->listCheck(true);

                $childBar = $dropdown->getChildToolbar();

                $childBar->publish('invoicetemplates.publish')->listCheck(true);
                $childBar->unpublish('invoicetemplates.unpublish')->listCheck(true);

                if ($this->state->get('filter.enabled') != -2) {
                    $childBar->trash('invoicetemplates.trash')->listCheck(true);
                }
            }
            if ($canDo->get('core.create')) {
                $toolbar->standardButton('duplicate')
                    ->text('JTOOLBAR_DUPLICATE')
                    ->task('invoicetemplates.duplicate')
                    ->icon('icon-copy')
                    ->listCheck(true);
            }


            if ($this->state->get('filter.enabled') === -2 && $canDo->get('core.delete')) {
                $toolbar->delete('invoicetemplates.delete')
                    ->text('JTOOLBAR_EMPTY_TRASH')
                    ->message('JGLOBAL_CONFIRM_DELETE')
                    ->listCheck(true);
            }
        }


        if ($user->authorise('core.admin', 'com_j2commerce') || $user->authorise('core.options', 'com_j2commerce')) {
            $toolbar->preferences('com_j2commerce');
        }

        $toolbar->help('Invoice_Templates', true,'https://docs.j2commerce.com/design/invoice-template-pro-feature');
    }

    /**
     * Returns an array of fields the table can be sorted by
     *
     * @return  array  Array containing the field name to sort by as the key and display text as value
     *
     * @since   6.0.0
     */
    protected function getSortFields()
    {
        return [
            'a.ordering' => Text::_('JGRID_HEADING_ORDERING'),
            'a.enabled' => Text::_('JSTATUS'),
            'a.title' => Text::_('COM_J2COMMERCE_INVOICETEMPLATE_TITLE'),
            'a.invoice_type' => Text::_('COM_J2COMMERCE_INVOICETEMPLATE_TYPE'),
            'a.language' => Text::_('JGRID_HEADING_LANGUAGE'),
            'a.j2commerce_invoicetemplate_id' => Text::_('JGRID_HEADING_ID')
        ];
    }
}
