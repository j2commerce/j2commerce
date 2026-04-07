<?php

/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace J2Commerce\Component\J2commerce\Administrator\View\Apps;

\defined('_JEXEC') or die;

use J2Commerce\Component\J2commerce\Administrator\Helper\MenuHelper;
use J2Commerce\Component\J2commerce\Administrator\View\AdminAssetsTrait;
use Joomla\CMS\Factory;
use Joomla\CMS\Helper\ContentHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\MVC\View\GenericDataException;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Joomla\CMS\Uri\Uri;

/**
 * Apps View
 *
 * @since  6.0.0
 */
class HtmlView extends BaseHtmlView
{
    use AdminAssetsTrait;

    protected $items;
    protected $pagination;
    protected $state;
    public $filterForm;
    public $activeFilters;
    private $isEmptyState = false;

    public function display($tpl = null)
    {
        $this->loadAdminAssets();
        $this->loadAppPluginLanguages();

        $this->navbar   = $this->getNavbar();
        $this->appCards = $this->getAppCards();

        $model               = $this->getModel();
        $this->items         = $model->getItems();
        $this->pagination    = $model->getPagination();
        $this->state         = $model->getState();
        $this->filterForm    = $model->getFilterForm();
        $this->activeFilters = $model->getActiveFilters();

        if ((!\is_array($this->items) || !\count($this->items)) && $this->isEmptyState = $model->getIsEmptyState()) {
            $this->setLayout('emptystate');
        }

        // Check for errors.
        if (\is_array($errors = $model->getErrors()) && \count($errors)) {
            throw new GenericDataException(implode("\n", $errors), 500);
        }

        $this->addToolbar();

        parent::display($tpl);
    }

    protected function getAppCards(): string
    {
        $displayData = [];

        return LayoutHelper::render('app.default', $displayData, JPATH_COMPONENT_ADMINISTRATOR . '/layouts');
    }

    protected function getNavbar(): string
    {
        $displayData = [
            'items'  => MenuHelper::getMenuItems(),
            'active' => MenuHelper::getActiveView(),
        ];

        return LayoutHelper::render('navbar.default', $displayData, JPATH_COMPONENT_ADMINISTRATOR . '/layouts');
    }

    protected function addToolbar()
    {
        $canDo = ContentHelper::getActions('com_j2commerce', 'app');
        $user  = Factory::getApplication()->getIdentity();

        // Get the toolbar object instance
        $toolbar = Factory::getApplication()->getDocument()->getToolbar();

        ToolbarHelper::title(Text::_('COM_J2COMMERCE_APPS'), 'fa-solid fa-puzzle-piece');

        if (!$this->isEmptyState) {
            if ($canDo->get('core.edit.state')) {
                $dropdown = $toolbar->dropdownButton('status-group')
                    ->text('JTOOLBAR_CHANGE_STATUS')
                    ->toggleSplit(false)
                    ->icon('icon-ellipsis-h')
                    ->buttonClass('btn btn-action')
                    ->listCheck(true);

                $childBar = $dropdown->getChildToolbar();

                $childBar->publish('apps.publish')->listCheck(true);
                $childBar->unpublish('apps.unpublish')->listCheck(true);

                if ($canDo->get('core.admin')) {
                    $childBar->checkin('apps.checkin')->listCheck(true);
                }
            }
        }

        if ($user->authorise('core.manage', 'com_installer')) {
            $toolbar->link(
                'COM_J2COMMERCE_TOOLBAR_INSTALL_APP',
                Uri::base() . 'index.php?option=com_installer&view=install'
            )
                ->icon('fa-solid fa-download')
                ->buttonClass('btn btn-primary');
        }

        if ($user->authorise('core.admin', 'com_j2commerce') || $user->authorise('core.options', 'com_j2commerce')) {
            $toolbar->preferences('com_j2commerce');
        }
        $toolbar->help('Apps', true, 'https://docs.j2commerce.com/v6/apps-and-extensions/');
    }

    protected function loadAppPluginLanguages(): void
    {
        $lang = Factory::getApplication()->getLanguage();
        $db   = Factory::getContainer()->get(\Joomla\Database\DatabaseInterface::class);

        // Load native j2commerce app plugin languages
        $query = $db->getQuery(true)
            ->select($db->quoteName('element'))
            ->from($db->quoteName('#__extensions'))
            ->where($db->quoteName('type') . ' = ' . $db->quote('plugin'))
            ->where($db->quoteName('folder') . ' = ' . $db->quote('j2commerce'))
            ->where($db->quoteName('element') . ' LIKE ' . $db->quote('app_%'));
        $db->setQuery($query);

        foreach ($db->loadColumn() ?: [] as $element) {
            $lang->load('plg_j2commerce_' . $element, JPATH_ADMINISTRATOR)
                || $lang->load('plg_j2commerce_' . $element, JPATH_PLUGINS . '/j2commerce/' . $element);
        }

        // Load external plugin languages
        $model       = $this->getModel();
        $externalIds = $model->getRegisteredExternalAppIds();

        if (!empty($externalIds)) {
            $query = $db->getQuery(true)
                ->select([$db->quoteName('element'), $db->quoteName('folder')])
                ->from($db->quoteName('#__extensions'))
                ->whereIn($db->quoteName('extension_id'), $externalIds);
            $db->setQuery($query);

            foreach ($db->loadObjectList() ?: [] as $ext) {
                $extName = 'plg_' . $ext->folder . '_' . $ext->element;
                $lang->load($extName, JPATH_ADMINISTRATOR)
                    || $lang->load($extName, JPATH_PLUGINS . '/' . $ext->folder . '/' . $ext->element);
            }
        }
    }
}
