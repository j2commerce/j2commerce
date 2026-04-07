<?php

/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace J2Commerce\Component\J2commerce\Administrator\View\Advancedpricing;

\defined('_JEXEC') or die;

use J2Commerce\Component\J2commerce\Administrator\Helper\J2CommerceHelper;
use J2Commerce\Component\J2commerce\Administrator\Helper\MenuHelper;
use J2Commerce\Component\J2commerce\Administrator\View\AdminAssetsTrait;
use Joomla\CMS\Helper\ContentHelper;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\MVC\View\GenericDataException;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Session\Session;
use Joomla\CMS\Toolbar\ToolbarHelper;

class HtmlView extends BaseHtmlView
{
    use AdminAssetsTrait;

    protected $items;
    protected $pagination;
    protected $state;
    public $filterForm;
    public $activeFilters;

    public function display($tpl = null): void
    {
        if (!J2CommerceHelper::canAccess('j2commerce.viewproducts')) {
            throw new \Exception(Text::_('JLIB_APPLICATION_ERROR_ACCESS_FORBIDDEN'), 403);
        }

        $this->loadAdminAssets();
        $this->navbar = $this->getNavbar();

        $model               = $this->getModel();
        $this->items         = $model->getItems();
        $this->pagination    = $model->getPagination();
        $this->state         = $model->getState();
        $this->filterForm    = $model->getFilterForm();
        $this->activeFilters = $model->getActiveFilters();

        if (\count($errors = $this->get('Errors'))) {
            throw new GenericDataException(implode("\n", $errors), 500);
        }

        $wa = $this->getDocument()->getWebAssetManager();
        $wa->registerAndUseScript(
            'com_j2commerce.admin.advancedpricing',
            'media/com_j2commerce/js/administrator/admin-advancedpricing.js',
            [],
            ['defer' => true]
        );
        $wa->registerAndUseStyle(
            'com_j2commerce.admin.advancedpricing.css',
            'media/com_j2commerce/css/administrator/admin-advancedpricing.css'
        );

        $this->getDocument()->addScriptOptions('csrf.token', Session::getFormToken());

        HTMLHelper::_('bootstrap.modal', 'collapseModal');

        $this->addToolbar();
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
        ToolbarHelper::title(Text::_('COM_J2COMMERCE_ADVANCED_PRICING'), 'fas fa-tags');

        $canDo   = ContentHelper::getActions('com_j2commerce');
        $toolbar = $this->getDocument()->getToolbar();

        if ($canDo->get('core.edit')) {
            $toolbar->standardButton('batch')
                ->text('COM_J2COMMERCE_BATCH')
                ->icon('icon-square')
                ->listCheck(true)
                ->onclick("bootstrap.Modal.getOrCreateInstance(document.getElementById('collapseModal')).show(); return false;");
        }

        if ($canDo->get('core.edit')) {
            $toolbar->standardButton('clearDates')
                ->text('COM_J2COMMERCE_BATCH_CLEAR_DATES')
                ->icon('fa-solid fa-calendar-minus')
                ->task('advancedpricing.clearDates')
                ->listCheck(true);
        }

        if ($canDo->get('core.delete')) {
            $toolbar->delete('advancedpricing.delete')
                ->text('JTOOLBAR_DELETE')
                ->message('JGLOBAL_CONFIRM_DELETE')
                ->listCheck(true);
        }

        $toolbar->linkButton('products')
            ->text('COM_J2COMMERCE_PRODUCTS')
            ->url('index.php?option=com_j2commerce&view=products')
            ->icon('icon-cart');

        if ($canDo->get('core.admin') || $canDo->get('core.options')) {
            $toolbar->preferences('com_j2commerce');
        }
    }
}
