<?php
/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace J2Commerce\Component\J2commerce\Administrator\View\Customer;

\defined('_JEXEC') or die;

use J2Commerce\Component\J2commerce\Administrator\View\AdminAssetsTrait;
use Joomla\CMS\Factory;
use Joomla\CMS\Helper\ContentHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\GenericDataException;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\ToolbarHelper;

/**
 * Customer edit view class.
 *
 * @since  6.0.7
 */
class HtmlView extends BaseHtmlView
{
    use AdminAssetsTrait;
    /**
     * The form object.
     *
     * @var    \Joomla\CMS\Form\Form
     * @since  6.0.7
     */
    protected $form;

    /**
     * The active item.
     *
     * @var    object
     * @since  6.0.7
     */
    protected $item;

    /**
     * Model state object.
     *
     * @var    \Joomla\Registry\Registry
     * @since  6.0.7
     */
    protected $state;

    /**
     * Display the view.
     *
     * @param   string  $tpl  The name of the template file to parse.
     *
     * @return  void
     *
     * @since   6.0.7
     */
    public function display($tpl = null): void
    {
        if (!$this->getCurrentUser()->authorise('j2commerce.vieworders', 'com_j2commerce')) {
            throw new \Exception(Text::_('JLIB_APPLICATION_ERROR_ACCESS_FORBIDDEN'), 403);
        }

        $this->loadAdminAssets();

        $model = $this->getModel();

        $this->form  = $model->getForm();
        $this->item  = $model->getItem();
        $this->state = $model->getState();

        // Check for errors
        if (\count($errors = $this->get('Errors'))) {
            throw new GenericDataException(implode("\n", $errors), 500);
        }

        $this->addToolbar();

        parent::display($tpl);
    }

    /**
     * Add the page title and toolbar.
     *
     * @return  void
     *
     * @since   6.0.7
     */
    protected function addToolbar(): void
    {
        Factory::getApplication()->getInput()->set('hidemainmenu', true);

        $isNew      = ($this->item->j2commerce_address_id == 0);
        $canDo      = ContentHelper::getActions('com_j2commerce');
        $user       = Factory::getApplication()->getIdentity();
        $checkedOut = !(\is_null($this->item->checked_out) || $this->item->checked_out == $user->id);
        $toolbar    = $this->getDocument()->getToolbar();

        ToolbarHelper::title(
            $isNew ? Text::_('COM_J2COMMERCE_CUSTOMER_NEW') : Text::_('COM_J2COMMERCE_CUSTOMER_EDIT'),
            'user'
        );

        if (!$checkedOut && ($canDo->get('core.edit') || $canDo->get('core.create'))) {
            $toolbar->apply('customer.apply');
            $toolbar->save('customer.save');
        }

        if (!$checkedOut && $canDo->get('core.create')) {
            $toolbar->save2new('customer.save2new');
        }

        $toolbar->cancel('customer.cancel', $isNew ? 'JTOOLBAR_CANCEL' : 'JTOOLBAR_CLOSE');

        ToolbarHelper::help('Customers', true, 'https://docs.j2commerce.com/sales/customers');
    }
}
