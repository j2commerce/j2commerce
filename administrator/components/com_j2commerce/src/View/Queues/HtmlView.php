<?php

/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace J2Commerce\Component\J2commerce\Administrator\View\Queues;

\defined('_JEXEC') or die;

use J2Commerce\Component\J2commerce\Administrator\Helper\MenuHelper;
use J2Commerce\Component\J2commerce\Administrator\View\AdminAssetsTrait;
use Joomla\CMS\Form\Form;
use Joomla\CMS\Helper\ContentHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Pagination\Pagination;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Joomla\Registry\Registry;

class HtmlView extends BaseHtmlView
{
    use AdminAssetsTrait;

    protected array $items = [];
    protected ?Pagination $pagination;
    protected Registry $state;
    public ?Form $filterForm;
    public array $activeFilters = [];
    private bool $isEmptyState  = false;

    public function display($tpl = null): void
    {
        $this->loadAdminAssets();
        $this->navbar = $this->getNavbar();

        $model = $this->getModel();
        $model->setUseExceptions(true);

        $this->items         = $model->getItems();
        $this->pagination    = $model->getPagination();
        $this->state         = $model->getState();
        $this->filterForm    = $model->getFilterForm();
        $this->activeFilters = $model->getActiveFilters();

        if (!\count($this->items) && $this->isEmptyState = $model->getIsEmptyState()) {
            $this->setLayout('emptystate');
        }

        $this->addToolbar();

        parent::display($tpl);
    }

    protected function getNavbar(): string
    {
        return LayoutHelper::render('navbar.default', [
            'items'  => MenuHelper::getMenuItems(),
            'active' => MenuHelper::getActiveView(),
        ], JPATH_COMPONENT_ADMINISTRATOR . '/layouts');
    }

    protected function addToolbar(): void
    {
        $canDo   = ContentHelper::getActions('com_j2commerce');
        $toolbar = $this->getDocument()->getToolbar();

        ToolbarHelper::title(Text::_('COM_J2COMMERCE_QUEUES'), 'fa-solid fa-list-check');

        if (!$this->isEmptyState) {
            $filteredQueueType = $this->state->get('filter.queue_type', '');

            if ($filteredQueueType !== '' && $canDo->get('core.edit.state')) {
                $toolbar->standardButton('processNow', 'COM_J2COMMERCE_TOOLBAR_PROCESS_NOW', 'queues.processNow')
                    ->icon('fa-solid fa-bolt')
                    ->listCheck(false);
            }

            if ($canDo->get('core.edit.state')) {
                $toolbar->standardButton('retryFailed', 'COM_J2COMMERCE_TOOLBAR_RETRY_FAILED', 'queues.retryFailed')
                    ->icon('fa-solid fa-rotate-right')
                    ->listCheck(true);
            }

            if ($canDo->get('core.delete')) {
                $toolbar->delete('queues.delete', 'JTOOLBAR_DELETE')->listCheck(true);

                $dropdown = $toolbar->dropdownButton('purge-group')
                    ->text('COM_J2COMMERCE_QUEUE_PURGE')
                    ->toggleSplit(false)
                    ->icon('icon-ellipsis-h')
                    ->buttonClass('btn btn-action');

                $childBar = $dropdown->getChildToolbar();
                $childBar->standardButton('purgeCompleted', 'COM_J2COMMERCE_TOOLBAR_PURGE_COMPLETED', 'queues.purgeCompleted')
                    ->icon('fa-solid fa-broom');
                $childBar->standardButton('purgeDead', 'COM_J2COMMERCE_TOOLBAR_PURGE_DEAD', 'queues.purgeDead')
                    ->icon('fa-solid fa-skull');
            }
        }

        $toolbar->linkButton('queuelogs')
            ->text('COM_J2COMMERCE_QUEUE_LOGS')
            ->url('index.php?option=com_j2commerce&view=queuelogs')
            ->icon('fa-solid fa-clock-rotate-left');

        if ($canDo->get('core.admin') || $canDo->get('core.options')) {
            ToolbarHelper::preferences('com_j2commerce');
        }

        ToolbarHelper::help('Queue_Hub', true, 'https://docs.j2commerce.com/v6/configuration/queue-hub');
    }
}
