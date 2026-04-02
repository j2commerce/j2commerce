<?php
/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace J2Commerce\Component\J2commerce\Administrator\View\Filtergroup;

defined('_JEXEC') or die;

use J2Commerce\Component\J2commerce\Administrator\View\AdminAssetsTrait;
use Joomla\CMS\Factory;
use Joomla\CMS\Helper\ContentHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\GenericDataException;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\ToolbarHelper;
use J2Commerce\Component\J2commerce\Administrator\Helper\J2CommerceHelper;

/**
 * Filtergroup View
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

        $model = $this->getModel();
        $this->item = $model->getItem();
        $this->state = $model->getState();
        $this->form = $model->getForm();

        // Check for errors.
        if (is_array($errors = $model->getErrors()) && \count($errors)) {
            throw new GenericDataException(implode("\n", $errors), 500);
        }

        // Validate required data
        if (!$this->item) {
            throw new GenericDataException(Text::_('COM_J2COMMERCE_FILTERGROUP_ERROR_ITEM_NOT_FOUND'), 500);
        }

        if (!$this->form) {
            throw new GenericDataException(Text::_('COM_J2COMMERCE_FILTERGROUP_ERROR_FORM_NOT_LOADED'), 500);
        }

        $this->addToolbar();

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
        $isNew = (empty($this->item->j2commerce_filtergroup_id) || $this->item->j2commerce_filtergroup_id == 0)
                 && (empty($this->item->id) || $this->item->id == 0);

        $canDo      = ContentHelper::getActions('com_j2commerce', 'filtergroup', $this->item->j2commerce_filtergroup_id ?? 0);
        $checkedOut = !(($this->item->checked_out ?? null) === null || ($this->item->checked_out ?? 0) == $user->id);

        ToolbarHelper::title(
            Text::_('COM_J2COMMERCE_FILTERGROUP') . ': ' . ($isNew ? Text::_('JTOOLBAR_NEW') : Text::_('JTOOLBAR_EDIT')),
            'filter'
        );

        // Only show save buttons when the item is not checked out by another user.
        if (!$checkedOut && ($canDo->get('core.edit') || $canDo->get('core.create'))) {
            ToolbarHelper::apply('filtergroup.apply');
            ToolbarHelper::save('filtergroup.save');

            if ($canDo->get('core.create')) {
                ToolbarHelper::save2new('filtergroup.save2new');
            }
        }

        // If an existing item, can save to a copy.
        if (!$isNew && $canDo->get('core.create')) {
            ToolbarHelper::save2copy('filtergroup.save2copy');
        }

        if ($isNew) {
            ToolbarHelper::cancel('filtergroup.cancel');
        } else {
            ToolbarHelper::cancel('filtergroup.cancel', 'JTOOLBAR_CLOSE');
        }

        ToolbarHelper::help('Filtergroup');
    }
}
