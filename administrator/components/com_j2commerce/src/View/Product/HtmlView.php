<?php
/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace J2Commerce\Component\J2commerce\Administrator\View\Product;

\defined('_JEXEC') or die;

use J2Commerce\Component\J2commerce\Administrator\View\AdminAssetsTrait;
use Joomla\CMS\Factory;
use Joomla\CMS\Helper\ContentHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\GenericDataException;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\ToolbarHelper;

/**
 * Product edit view class.
 *
 * @since  6.0.3
 */
class HtmlView extends BaseHtmlView
{
    use AdminAssetsTrait;
    /**
     * The Form object
     *
     * @var  \Joomla\CMS\Form\Form
     * @since  6.0.3
     */
    protected $form;

    /**
     * The active item
     *
     * @var  object
     * @since  6.0.3
     */
    protected $item;

    /**
     * The model state
     *
     * @var  \Joomla\Registry\Registry
     * @since  6.0.3
     */
    protected $state;

    /**
     * Display the view
     *
     * @param   string  $tpl  The name of the template file to parse
     *
     * @return  void
     *
     * @since   6.0.3
     */
    public function display($tpl = null): void
    {
        if (!$this->getCurrentUser()->authorise('j2commerce.viewproducts', 'com_j2commerce')) {
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
     * @since   6.0.3
     */
    protected function addToolbar(): void
    {
        Factory::getApplication()->getInput()->set('hidemainmenu', true);

        $isNew      = empty($this->item->j2commerce_product_id);
        $canDo      = ContentHelper::getActions('com_j2commerce');
        $user       = Factory::getApplication()->getIdentity();
        $checkedOut = !(\is_null($this->item->checked_out) || $this->item->checked_out == $user->id);
        $toolbar    = $this->getDocument()->getToolbar();

        ToolbarHelper::title(
            $isNew ? Text::_('COM_J2COMMERCE_TOOLBAR_NEW') : Text::_('COM_J2COMMERCE_TOOLBAR_EDIT'),
            'fa-solid fa-tags'
        );

        if (!$checkedOut && ($canDo->get('core.edit') || $canDo->get('core.create'))) {
            $toolbar->apply('product.apply');
            $toolbar->save('product.save');
        }

        if (!$checkedOut && $canDo->get('core.create')) {
            $toolbar->save2new('product.save2new');
        }

        if (!$isNew && $canDo->get('core.create')) {
            $toolbar->save2copy('product.save2copy');
        }

        $toolbar->cancel('product.cancel', $isNew ? 'JTOOLBAR_CANCEL' : 'JTOOLBAR_CLOSE');

        $toolbar->help('Product');
    }
}