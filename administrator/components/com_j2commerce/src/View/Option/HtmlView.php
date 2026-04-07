<?php

/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace J2Commerce\Component\J2commerce\Administrator\View\Option;

\defined('_JEXEC') or die;

use J2Commerce\Component\J2commerce\Administrator\Helper\J2CommerceHelper;
use J2Commerce\Component\J2commerce\Administrator\View\AdminAssetsTrait;
use Joomla\CMS\Factory;
use Joomla\CMS\Helper\ContentHelper;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\GenericDataException;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\ToolbarHelper;

/**
 * Option View
 *
 * @since  6.0.0
 */
class HtmlView extends BaseHtmlView
{
    use AdminAssetsTrait;

    /**
     * The Form object
     *
     * @var  \Joomla\CMS\Form\Form
     */
    protected $form;

    /**
     * The active item
     *
     * @var  object
     */
    protected $item;

    /**
     * The model state
     *
     * @var  \Joomla\CMS\Object\CMSObject
     */
    protected $state;

    /**
     * Display the view
     *
     * @param   string  $tpl  The name of the template file to parse
     *
     * @return  void
     *
     * @throws  \Exception
     * @since  6.0.0
     */
    public function display($tpl = null)
    {
        if (!J2CommerceHelper::canAccess('j2commerce.viewproducts')) {
            throw new \Exception(Text::_('JLIB_APPLICATION_ERROR_ACCESS_FORBIDDEN'), 403);
        }

        $this->loadAdminAssets();

        $model       = $this->getModel();
        $this->item  = $model->getItem();
        $this->state = $model->getState();
        $this->form  = $model->getForm();

        // Check for errors.
        if (\is_array($errors = $model->getErrors()) && \count($errors)) {
            throw new GenericDataException(implode("\n", $errors), 500);
        }

        // Debug: Ensure item data is properly loaded
        if (!$this->item) {
            throw new GenericDataException('Failed to load option item', 500);
        }

        $this->addToolbar();

        // Bootstrap modal JS required for image picker modals in option value subforms
        HTMLHelper::_('bootstrap.modal');

        parent::display($tpl);
    }

    /**
     * Add the page title and toolbar.
     *
     * @return  void
     *
     * @since  6.0.0
     */
    protected function addToolbar()
    {
        Factory::getApplication()->input->set('hidemainmenu', true);

        $user = Factory::getApplication()->getIdentity();
        // Check both possible ID fields to determine if this is a new record
        $isNew = (empty($this->item->j2commerce_option_id) || $this->item->j2commerce_option_id == 0)
                 && (empty($this->item->id) || $this->item->id == 0);
        $canDo      = ContentHelper::getActions('com_j2commerce', 'option', $this->item->j2commerce_option_id ?? 0);
        $checkedOut = !(($this->item->checked_out ?? null) === null || ($this->item->checked_out ?? 0) == $user->id);

        ToolbarHelper::title(
            Text::_('COM_J2COMMERCE_OPTION') . ': ' . ($isNew ? Text::_('JTOOLBAR_NEW') : Text::_('JTOOLBAR_EDIT')),
            'fa-solid fa-list-ol'
        );

        // Only show save buttons when the item is not checked out by another user.
        if (!$checkedOut && ($canDo->get('core.edit') || $canDo->get('core.create'))) {
            ToolbarHelper::apply('option.apply');
            ToolbarHelper::save('option.save');

            if ($canDo->get('core.create')) {
                ToolbarHelper::save2new('option.save2new');
            }
        }

        // If an existing item, can save to a copy.
        if (!$isNew && $canDo->get('core.create')) {
            ToolbarHelper::save2copy('option.save2copy');
        }

        if ($isNew) {
            ToolbarHelper::cancel('option.cancel');
        } else {
            ToolbarHelper::cancel('option.cancel', 'JTOOLBAR_CLOSE');
        }

        ToolbarHelper::help('Option', false, 'https://docs.j2commerce.com/v6/catalog/options');
    }
}
