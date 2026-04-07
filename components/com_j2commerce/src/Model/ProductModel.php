<?php

/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace J2Commerce\Component\J2commerce\Site\Model;

\defined('_JEXEC') or die;

use J2Commerce\Component\J2commerce\Administrator\Helper\ProductHelper;
use J2Commerce\Component\J2commerce\Administrator\Table\ProductTable;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Multilanguage;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;
use Joomla\Registry\Registry;

/**
 * Product Model for site frontend
 *
 * Loads a fully hydrated product object using ProductHelper::getFullProduct()
 * for display on the single product view page.
 *
 * @since  6.0.0
 */
class ProductModel extends BaseDatabaseModel
{
    /**
     * Model context string.
     *
     * @var   string
     * @since 6.0.0
     */
    protected $_context = 'com_j2commerce.product';

    /**
     * Cached product items indexed by product ID.
     *
     * @var   array
     * @since 6.0.0
     */
    protected $_item = [];

    /**
     * Method to auto-populate the model state.
     *
     * Note. Calling getState in this method will result in recursion.
     *
     * @return void
     *
     * @since   6.0.0
     */
    protected function populateState(): void
    {
        $app = Factory::getApplication();

        // Load state from the request.
        $pk = $app->getInput()->getInt('id', 0);
        $this->setState('product.id', $pk);

        // Load the parameters.
        $params = $app->getParams();
        $this->setState('params', $params);

        // Set language filter state
        $this->setState('filter.language', Multilanguage::isEnabled());
    }

    /**
     * Method to get a fully hydrated product object.
     *
     * Uses ProductHelper::getFullProduct() to load all related data including:
     * - Product images (main, thumb, additional)
     * - Manufacturer data
     * - Article data (product_name, product_short_desc, product_long_desc)
     * - Variants
     * - Product options
     *
     * @param   integer|null  $pk  The id of the product. If null, uses state.
     *
     * @return  object|null  Product data object on success, null on failure
     *
     * @throws  \Exception  If product not found
     *
     * @since   6.0.0
     */
    public function getItem(?int $pk = null): ?object
    {
        $pk = $pk ?: (int) $this->getState('product.id');

        if ($pk <= 0) {
            return null;
        }

        // Check cache first
        if (isset($this->_item[$pk])) {
            return $this->_item[$pk];
        }

        // Get fully hydrated product from ProductHelper
        $product = ProductHelper::getFullProduct($pk, true, true);

        if (!$product) {
            throw new \Exception(Text::_('COM_J2COMMERCE_ERROR_PRODUCT_NOT_FOUND'), 404);
        }

        // Merge component params with product params if needed
        $params = $this->getState('params');

        if ($params instanceof Registry) {
            // Decode product params if stored as JSON string
            $productParams = new Registry($product->params ?? '{}');

            // Merge - component params take precedence
            $productParams->merge($params);
            $product->params = $productParams;
        }

        // Cache and return
        $this->_item[$pk] = $product;

        return $product;
    }

    /**
     * Increment the hit counter for the product.
     *
     * @param   int  $pk  Optional primary key of the product to increment.
     *
     * @return  bool  True on success.
     */
    public function hit(int $pk = 0): bool
    {
        $hitcount = Factory::getApplication()->getInput()->getInt('hitcount', 1);

        if ($hitcount) {
            $pk = $pk ?: (int) $this->getState('product.id');

            if ($pk > 0) {
                $table = new ProductTable($this->getDatabase());
                $table->hit($pk);
            }
        }

        return true;
    }
}
