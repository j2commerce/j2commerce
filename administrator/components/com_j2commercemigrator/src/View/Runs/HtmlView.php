<?php

/**
 * @package     J2Commerce
 * @subpackage  com_j2commercemigrator
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace J2Commerce\Component\J2commercemigrator\Administrator\View\Runs;

use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Pagination\Pagination;

class HtmlView extends BaseHtmlView
{
    public array $runs = [];

    public ?Pagination $pagination = null;

    public function display($tpl = null): void
    {
        $model            = $this->getModel('Runs');
        $this->runs       = $model->getItems();
        $this->pagination = $model->getPagination();
        $this->state      = $model->getState();

        $this->setToolbar();

        parent::display($tpl);
    }

    private function setToolbar(): void
    {
        $toolbar = $this->getDocument()->getToolbar();
        $toolbar->title(Text::_('COM_J2COMMERCEMIGRATOR_VIEW_RUNS_TITLE'), 'fa-solid fa-clock-rotate-left');
        $toolbar->link(Text::_('COM_J2COMMERCEMIGRATOR_TOOLBAR_DASHBOARD'), 'index.php?option=com_j2commercemigrator', 'fa-solid fa-house');
    }
}
