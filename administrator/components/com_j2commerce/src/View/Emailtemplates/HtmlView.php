<?php
/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace J2Commerce\Component\J2commerce\Administrator\View\Emailtemplates;

defined('_JEXEC') or die;

use J2Commerce\Component\J2commerce\Administrator\Helper\MenuHelper;
use J2Commerce\Component\J2commerce\Administrator\Model\EmailtemplatesModel;
use J2Commerce\Component\J2commerce\Administrator\View\AdminAssetsTrait;
use Joomla\CMS\Factory;
use Joomla\CMS\Form\Form;
use Joomla\CMS\Helper\ContentHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\MVC\View\GenericDataException;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Pagination\Pagination;
use Joomla\CMS\Toolbar\Toolbar;
use Joomla\CMS\Toolbar\ToolbarHelper;


/**
 * View for J2Commerce email templates
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
     * @var  Pagination
     */
    protected $pagination;

    /**
     * The model state
     *
     * @var  \Joomla\Registry\Registry
     */
    protected $state;

    /**
     * Form object for search filters
     *
     * @var  Form
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
     * Execute and display a template script.
     *
     * @param   string  $tpl  The name of the template file to parse; automatically searches through the template paths.
     *
     * @return  void
     *
     * @since   6.0.0
     */
    public function display($tpl = null)
    {
        $this->loadAdminAssets();

        /** @var EmailtemplatesModel $model */
        $this->navbar = $this->getNavbar();

        $model = $this->getModel();
        $this->items         = $model->getItems();
        $this->pagination    = $model->getPagination();
        $this->state         = $model->getState();
        $this->filterForm    = $model->getFilterForm();
        $this->activeFilters = $model->getActiveFilters();

        $requestedLayout = Factory::getApplication()->getInput()->get('layout', '');

        if ((!is_array($this->items) || !\count($this->items)) && !$requestedLayout && $this->isEmptyState = $this->getModel()->getIsEmptyState()) {
            $this->setLayout('emptystate');
        }

        // Check for errors.
        if (\count($errors = $model->getErrors())) {
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
        // Get the toolbar object instance using modern Joomla 5 approach
        $toolbar = Factory::getApplication()->getDocument()->getToolbar();
        $user    = $this->getCurrentUser();
        $canDo   = ContentHelper::getActions('com_j2commerce', 'emailtemplate');

        ToolbarHelper::title(Text::_('COM_J2COMMERCE_EMAILTEMPLATES_MANAGER'), 'envelope');

        if ($canDo->get('core.create')) {
            $toolbar->addNew('emailtemplate.add');
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

                $childBar->publish('emailtemplates.publish')->listCheck(true);
                $childBar->unpublish('emailtemplates.unpublish')->listCheck(true);
                $childBar->trash('emailtemplates.trash')->listCheck(true);
            }

            if ($canDo->get('core.delete') && $this->state->get('filter.enabled') == -2) {
                $toolbar->delete('emailtemplates.delete')
                    ->text('JTOOLBAR_DELETE')
                    ->message('JGLOBAL_CONFIRM_DELETE')
                    ->listCheck(true);
            }

            $toolbar->standardButton('export', 'COM_J2COMMERCE_EMAILTEMPLATE_EXPORT', 'emailtemplates.export')
                ->icon('icon-download')
                ->listCheck(true);
        }

        $toolbar->standardButton('import', 'COM_J2COMMERCE_EMAILTEMPLATE_IMPORT', 'emailtemplates.import')
            ->icon('icon-upload')
            ->listCheck(false);

        if ($user->authorise('core.admin', 'com_j2commerce') || $user->authorise('core.options', 'com_j2commerce')) {
            $toolbar->preferences('com_j2commerce');
        }

        // Add help button
        $toolbar->help('J2Commerce_Email_Templates', false, 'https://docs.j2commerce.com/design/email-templates');
    }
}
