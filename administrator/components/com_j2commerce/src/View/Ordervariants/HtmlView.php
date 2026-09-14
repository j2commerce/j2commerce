<?php

/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace J2Commerce\Component\J2commerce\Administrator\View\Ordervariants;

\defined('_JEXEC') or die;

use J2Commerce\Component\J2commerce\Administrator\Helper\J2CommerceHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\GenericDataException;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;

/** The Update Variant picker the order editor loads into its modal. */
class HtmlView extends BaseHtmlView
{
    public $items = [];

    public $pagination;

    public $state;

    public $filterForm;

    public $activeFilters = [];

    public ?object $line = null;

    public function display($tpl = null): void
    {
        $user = Factory::getApplication()->getIdentity();

        // The same gate as the order editor's own endpoints (OrderController::checkOrderEditAccess()).
        if (!$user || $user->guest || !$user->authorise('core.edit', 'com_j2commerce') || !J2CommerceHelper::canAccess('j2commerce.editorders')) {
            J2CommerceHelper::denyAccess();
        }

        /** @var \J2Commerce\Component\J2commerce\Administrator\Model\OrdervariantsModel $model */
        $model      = $this->getModel();
        $this->line = $model->getOrderLine();

        if ($this->line === null) {
            throw new GenericDataException(Text::_('COM_J2COMMERCE_ERROR_INVALID_REQUEST'), 404);
        }

        $this->items         = $model->getItems() ?: [];
        $this->pagination    = $model->getPagination();
        $this->state         = $model->getState();
        $this->filterForm    = $model->getFilterForm();
        $this->activeFilters = $model->getActiveFilters();

        $this->setLayout('modal');

        parent::display($tpl);
    }
}
