<?php

/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace J2Commerce\Component\J2commerce\Administrator\View\Databasehealthproducts;

\defined('_JEXEC') or die;

use Joomla\CMS\Form\Form;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Pagination\Pagination;
use Joomla\Registry\Registry;

class HtmlView extends BaseHtmlView
{
    protected array $items = [];
    protected ?Pagination $pagination = null;
    protected Registry $state;
    protected int $total = 0;
    public ?Form $filterForm = null;
    public array $activeFilters = [];

    public function display($tpl = null): void
    {
        $user = $this->getCurrentUser();

        // This screen only ever deletes products, so it is held to the same core.admin gate
        // as the rest of the Database Health card and its endpoints.
        if ($user->guest || !$user->authorise('core.admin', 'com_j2commerce')) {
            throw new \Exception(Text::_('JLIB_APPLICATION_ERROR_ACCESS_FORBIDDEN'), 403);
        }

        $model = $this->getModel();

        $this->items         = $model->getItems();
        $this->pagination    = $model->getPagination();
        $this->state         = $model->getState();
        $this->total         = (int) $model->getTotal();
        $this->filterForm    = $model->getFilterForm();
        $this->activeFilters = $model->getActiveFilters();

        parent::display($tpl);
    }
}
