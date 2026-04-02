<?php
/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace J2Commerce\Component\J2commerce\Administrator\View\Currency;

\defined('_JEXEC') or die;

use J2Commerce\Component\J2commerce\Administrator\View\AdminAssetsTrait;
use Joomla\CMS\Factory;
use Joomla\CMS\Form\Form;
use Joomla\CMS\Helper\ContentHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\GenericDataException;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\Toolbar;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Joomla\Registry\Registry;

/**
 * Currency edit view class.
 *
 * @since  6.0.4
 */
class HtmlView extends BaseHtmlView
{
    use AdminAssetsTrait;

    /**
     * The Form object
     *
     * @var    Form|null
     * @since  6.0.4
     */
    protected $form;

    /**
     * The active item
     *
     * @var    object
     * @since  6.0.4
     */
    protected $item;

    /**
     * The model state
     *
     * @var    Registry
     * @since  6.0.4
     */
    protected $state;

    /**
     * Display the view.
     *
     * @param   string  $tpl  The name of the template file to parse.
     *
     * @return  void
     *
     * @since   6.0.4
     */
    public function display($tpl = null): void
    {
        $this->loadAdminAssets();

        $model = $this->getModel();
        $model->setUseExceptions(true);

        $this->form  = $model->getForm();
        $this->item  = $model->getItem();
        $this->state = $model->getState();

        $this->addToolbar();

        parent::display($tpl);
    }

    /**
     * Add the page title and toolbar.
     *
     * @return  void
     *
     * @since   6.0.4
     */
    protected function addToolbar(): void
    {
        Factory::getApplication()->getInput()->set('hidemainmenu', true);

        $isNew      = ($this->item->j2commerce_currency_id == 0);
        $canDo      = ContentHelper::getActions('com_j2commerce');
        $user       = Factory::getApplication()->getIdentity();
        $checkedOut = !(($this->item->checked_out ?? null) === null || ($this->item->checked_out ?? 0) == $user->id);
        $toolbar    = $this->getDocument()->getToolbar();

        // Title: "New Currency" or "Edit Currency"
        $title = $isNew ? Text::_('COM_J2COMMERCE_TOOLBAR_NEW') . ' ' . Text::_('COM_J2COMMERCE_CURRENCY') : Text::_('COM_J2COMMERCE_TOOLBAR_EDIT') . ' ' . Text::_('COM_J2COMMERCE_CURRENCY');
        ToolbarHelper::title($title, 'fas fa-solid fa-dollar fa-dollar-sign');

        // Only show save buttons when the item is not checked out by another user.
        if (!$checkedOut && ($canDo->get('core.edit') || $canDo->get('core.create'))) {
            $toolbar->apply('currency.apply');
        }

        if ($canDo->get('core.edit.state')) {
            // Dropdown save group with configure() callback
            $saveGroup = $toolbar->dropdownButton('save-group');
            $saveGroup->configure(
                function (Toolbar $childBar) use ($canDo, $isNew, $checkedOut) {
                    if (!$checkedOut && ($canDo->get('core.edit') || $canDo->get('core.create'))) {
                        $childBar->save('currency.save');
                    }

                    if ($canDo->get('core.create')) {
                        $childBar->save2new('currency.save2new');
                    }

                    if (!$isNew && $canDo->get('core.create')) {
                        $childBar->save2copy('currency.save2copy');
                    }
                }
            );
        }

        $toolbar->cancel('currency.cancel', $isNew ? 'JTOOLBAR_CANCEL' : 'JTOOLBAR_CLOSE');
        $toolbar->divider();
        ToolbarHelper::help('Currencies', true, 'https://docs.j2commerce.com/set-up/currency');
    }
}
