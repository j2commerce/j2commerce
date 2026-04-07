<?php

/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace J2Commerce\Component\J2commerce\Administrator\View\Productoptionvalues;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use J2Commerce\Component\J2commerce\Administrator\Helper\J2CommerceHelper;
use J2Commerce\Component\J2commerce\Administrator\Helper\ProductHelper;
use J2Commerce\Component\J2commerce\Administrator\View\AdminAssetsTrait;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\Database\ParameterType;

/**
 * Product Option Values View
 *
 * Modal view for managing option values assigned to a product option.
 *
 * @since  6.0.0
 */
class HtmlView extends BaseHtmlView
{
    use AdminAssetsTrait;

    /**
     * The product object
     *
     * @var  object
     */
    public $product;

    /**
     * The product option object
     *
     * @var  object
     */
    public $productOption;

    /**
     * The product ID
     *
     * @var  int
     */
    public $productId;

    /**
     * The product option ID
     *
     * @var  int
     */
    public $productOptionId;

    /**
     * Available option values from master option
     *
     * @var  array
     */
    public $optionValues = [];

    /**
     * Product option values (assigned to this product option)
     *
     * @var  array
     */
    public $productOptionValues = [];

    /**
     * Parent option values (for dependent options)
     *
     * @var  array
     */
    public $parentOptionValues = [];

    /**
     * Form field name prefix
     *
     * @var  string
     */
    public $prefix = 'jform[poption_value]';

    /**
     * Display the view
     *
     * @param   string  $tpl  The name of the template file to parse
     *
     * @return  void
     *
     * @throws  \Exception
     */
    public function display($tpl = null): void
    {
        if (!J2CommerceHelper::canAccess('j2commerce.viewproducts')) {
            throw new \Exception(Text::_('JLIB_APPLICATION_ERROR_ACCESS_FORBIDDEN'), 403);
        }

        $this->loadAdminAssets();

        $app = Factory::getApplication();
        $db  = Factory::getContainer()->get('DatabaseDriver');

        $this->productId       = $app->getInput()->getInt('product_id', 0);
        $this->productOptionId = $app->getInput()->getInt('productoption_id', 0);

        if (!$this->productId || !$this->productOptionId) {
            $app->enqueueMessage(Text::_('COM_J2COMMERCE_INVALID_PRODUCT_OR_OPTION'), 'error');
            return;
        }

        // Get product using MVC factory
        $mvcFactory   = $app->bootComponent('com_j2commerce')->getMVCFactory();
        $productModel = $mvcFactory->createModel('Product', 'Administrator', ['ignore_request' => true]);

        if (!$productModel) {
            throw new \RuntimeException('Unable to load Product model');
        }

        $this->product = $productModel->getItem($this->productId);

        if (!$this->product) {
            $app->enqueueMessage(Text::_('COM_J2COMMERCE_PRODUCT_NOT_FOUND'), 'error');
            return;
        }

        // Get product option details
        $query = $db->getQuery(true);
        $query->select([
                $db->quoteName('po.j2commerce_productoption_id'),
                $db->quoteName('po.option_id'),
                $db->quoteName('po.parent_id'),
                $db->quoteName('po.required'),
                $db->quoteName('po.is_variant'),
                $db->quoteName('po.product_id'),
            ])
            ->select([
                $db->quoteName('o.option_name'),
                $db->quoteName('o.option_unique_name'),
                $db->quoteName('o.type'),
            ])
            ->from($db->quoteName('#__j2commerce_product_options', 'po'))
            ->leftJoin(
                $db->quoteName('#__j2commerce_options', 'o') .
                ' ON ' . $db->quoteName('po.option_id') . ' = ' . $db->quoteName('o.j2commerce_option_id')
            )
            ->where($db->quoteName('po.j2commerce_productoption_id') . ' = :poId')
            ->bind(':poId', $this->productOptionId, ParameterType::INTEGER);

        $db->setQuery($query);
        $this->productOption = $db->loadObject();

        if (!$this->productOption) {
            $app->enqueueMessage(Text::_('COM_J2COMMERCE_PRODUCT_OPTION_NOT_FOUND'), 'error');
            return;
        }

        // Get all available option values for this option (from master option)
        // Note: bind() requires a variable reference, can't use object property directly
        $optionId = (int) $this->productOption->option_id;
        $query    = $db->getQuery(true);
        $query->select([
                $db->quoteName('j2commerce_optionvalue_id'),
                $db->quoteName('optionvalue_name'),
            ])
            ->from($db->quoteName('#__j2commerce_optionvalues'))
            ->where($db->quoteName('option_id') . ' = :optionId')
            ->order($db->quoteName('ordering') . ' ASC')
            ->bind(':optionId', $optionId, ParameterType::INTEGER);

        $db->setQuery($query);
        $this->optionValues = $db->loadObjectList() ?: [];

        // Get existing product option values
        $query = $db->getQuery(true);
        $query->select([
                $db->quoteName('pov.j2commerce_product_optionvalue_id'),
                $db->quoteName('pov.productoption_id'),
                $db->quoteName('pov.optionvalue_id'),
                $db->quoteName('pov.parent_optionvalue'),
                $db->quoteName('pov.product_optionvalue_price'),
                $db->quoteName('pov.product_optionvalue_prefix'),
                $db->quoteName('pov.product_optionvalue_weight'),
                $db->quoteName('pov.product_optionvalue_weight_prefix'),
                $db->quoteName('pov.product_optionvalue_sku'),
                $db->quoteName('pov.product_optionvalue_default'),
                $db->quoteName('pov.ordering'),
                $db->quoteName('pov.product_optionvalue_attribs'),
            ])
            ->select($db->quoteName('ov.optionvalue_name'))
            ->from($db->quoteName('#__j2commerce_product_optionvalues', 'pov'))
            ->leftJoin(
                $db->quoteName('#__j2commerce_optionvalues', 'ov') .
                ' ON ' . $db->quoteName('pov.optionvalue_id') . ' = ' . $db->quoteName('ov.j2commerce_optionvalue_id')
            )
            ->where($db->quoteName('pov.productoption_id') . ' = :poId')
            ->order($db->quoteName('pov.ordering') . ' ASC')
            ->bind(':poId', $this->productOptionId, ParameterType::INTEGER);

        $db->setQuery($query);
        $this->productOptionValues = $db->loadObjectList() ?: [];

        // Get parent option values if applicable
        if (!empty($this->productOption->parent_id)) {
            $this->parentOptionValues = ProductHelper::getProductOptionValues((int) $this->productOption->parent_id);
        }

        parent::display($tpl);
    }
}
