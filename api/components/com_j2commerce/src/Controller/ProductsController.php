<?php

/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace J2Commerce\Component\J2commerce\Api\Controller;

\defined('_JEXEC') or die;

use Joomla\CMS\Filter\InputFilter;
use J2Commerce\Component\J2commerce\Api\Controller\J2CommerceApiController;

class ProductsController extends J2CommerceApiController
{
    protected $contentType = 'products';

    protected $default_view = 'products';

    public function getModel($name = '', $prefix = '', $config = [])
    {
        // Use API-layer ProductModel (article + jcfields) for single-item requests
        if (strtolower($name) === 'product') {
            $prefix = 'Api';
        }

        return parent::getModel($name, $prefix, $config);
    }

    public function displayList()
    {
        $apiFilterInfo = $this->input->get('filter', [], 'array');
        $filter = InputFilter::getInstance();

        if (\array_key_exists('search', $apiFilterInfo)) {
            $this->modelState->set('filter.search', $filter->clean($apiFilterInfo['search'], 'STRING'));
        }

        if (\array_key_exists('category', $apiFilterInfo)) {
            $this->modelState->set('filter.category_id', $filter->clean($apiFilterInfo['category'], 'INT'));
        }

        if (\array_key_exists('manufacturer', $apiFilterInfo)) {
            $this->modelState->set('filter.manufacturer_id', $filter->clean($apiFilterInfo['manufacturer'], 'INT'));
        }

        if (\array_key_exists('product_type', $apiFilterInfo)) {
            $this->modelState->set('filter.product_type', $filter->clean($apiFilterInfo['product_type'], 'STRING'));
        }

        if (\array_key_exists('enabled', $apiFilterInfo)) {
            $this->modelState->set('filter.state', $filter->clean($apiFilterInfo['enabled'], 'INT'));
        }

        if (\array_key_exists('sku', $apiFilterInfo)) {
            $this->modelState->set('filter.search', 'sku:' . $filter->clean($apiFilterInfo['sku'], 'STRING'));
        }

        if (\array_key_exists('visibility', $apiFilterInfo)) {
            $this->modelState->set('filter.visibility', $filter->clean($apiFilterInfo['visibility'], 'INT'));
        }

        $apiListInfo = $this->input->get('list', [], 'array');

        if (\array_key_exists('ordering', $apiListInfo)) {
            $this->modelState->set('list.ordering', $filter->clean($apiListInfo['ordering'], 'STRING'));
        }

        if (\array_key_exists('direction', $apiListInfo)) {
            $this->modelState->set('list.direction', $filter->clean($apiListInfo['direction'], 'STRING'));
        }

        return parent::displayList();
    }
}
