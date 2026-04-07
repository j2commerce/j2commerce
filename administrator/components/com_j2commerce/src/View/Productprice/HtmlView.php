<?php

/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace J2Commerce\Component\J2commerce\Administrator\View\Productprice;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use J2Commerce\Component\J2commerce\Administrator\Helper\J2CommerceHelper;
use J2Commerce\Component\J2commerce\Administrator\View\AdminAssetsTrait;
use Joomla\CMS\Factory;
use Joomla\CMS\Helper\ContentHelper;
use Joomla\CMS\Helper\UserGroupsHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\ToolbarHelper;

/**
 * Product Price View
 *
 * @since  6.0.0
 */
class HtmlView extends BaseHtmlView
{
    use AdminAssetsTrait;

    /**
     * The \Joomla\CMS\Form\Form object
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
     * @var  object
     */
    protected $state;

    protected $variant_id;
    protected $prices;
    protected $groups;

    /**
     * Display the view
     *
     * @param   string  $tpl  The name of the template file to parse
     *
     * @return  void
     *
     * @throws  \Exception
     */
    public function display($tpl = null)
    {
        if (!J2CommerceHelper::canAccess('j2commerce.viewproducts')) {
            throw new \Exception(Text::_('JLIB_APPLICATION_ERROR_ACCESS_FORBIDDEN'), 403);
        }

        $this->loadAdminAssets();

        $app              = Factory::getApplication();
        $this->variant_id = $app->input->getInt('variant_id', 0);

        if (!$this->variant_id) {
            $app->enqueueMessage('Invalid variant ID', 'error');
            return;
        }

        // Get the model explicitly using MVC factory
        $mvcFactory = $app->bootComponent('com_j2commerce')->getMVCFactory();
        $model      = $mvcFactory->createModel('Productprice', 'Administrator', ['ignore_request' => true]);

        if (!$model) {
            throw new \RuntimeException('Unable to load Productprice model');
        }

        // Get the form
        $this->form = $model->getForm();

        // Get the item (or create a new one)
        $this->item = $model->getItem();

        // Set variant_id for new items
        if (empty($this->item->j2commerce_productprice_id)) {
            $this->item->variant_id = $this->variant_id;
        }

        $this->state = $model->getState();

        // Get existing prices for this variant
        //$productPrices = $mvcFactory->createModel('Productprices', 'Administrator', ['ignore_request' => true]);
        $this->prices = $this->getExistingPrices($this->variant_id);
        //$this->prices = $productPrices->getPricesByVariantId($this->variant_id);

        // Get user groups
        $this->groups = UserGroupsHelper::getInstance()->getAll();

        // Force productpricing layout
        $this->setLayout('productpricing');

        // Only add toolbar if not in component/modal view
        if ($app->input->get('tmpl') !== 'component') {
            $this->addToolbar();
        }

        parent::display($tpl);
    }

    /**
     * Get existing prices for a variant
     *
     * @param int $variant_id The variant ID
     *
     * @return array List of price records
     * @since 5.0.0
     */
    protected function getExistingPrices(int $variant_id): array
    {
        if (!$variant_id) {
            return [];
        }

        $db = Factory::getContainer()->get('DatabaseDriver');

        $query = $db->getQuery(true)
            ->select('*')
            ->from($db->quoteName('#__j2commerce_product_prices'))
            ->where($db->quoteName('variant_id') . ' = '.$this->variant_id)
            ->order($db->quoteName('j2commerce_productprice_id') . ' ASC ');

        $db->setQuery($query);

        try {
            return $db->loadObjectList() ?: [];
        } catch (\Exception $e) {
            Factory::getApplication()->enqueueMessage($e->getMessage(), 'error');
            return [];
        }
    }

    /**
     * Add the page title and toolbar.
     *
     * @return  void
     *
     * @throws  \Exception
     *
     * @since  6.0.0
     */
    protected function addToolbar()
    {
        Factory::getApplication()->input->set('hidemainmenu', true);

        $canDo = ContentHelper::getActions('com_j2commerce', 'productprice');

        // Get the toolbar object instance
        $toolbar = Factory::getApplication()->getDocument()->getToolbar();

        $title = Text::_('COM_J2COMMERCE_ADVANCED_PRICING');
        ToolbarHelper::title($title, 'fa fa-solid fa-dollar-sign');

        // Simplified toolbar for pricing modal
        if ($canDo->get('core.create') || $canDo->get('core.edit')) {
            $toolbar->apply('productprice.apply');
            $toolbar->save('productprice.save');
        }

        $toolbar->cancel('productprice.cancel', 'JTOOLBAR_CLOSE');
    }
}
