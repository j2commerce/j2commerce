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

use J2Commerce\Component\J2commerce\Api\Model\ProductModel;
use Joomla\CMS\Filter\InputFilter;
use Tobscure\JsonApi\Exception\InvalidParameterException;

class ProductsController extends J2CommerceApiController
{
    protected $contentType = 'products';

    protected $default_view = 'products';

    protected string $readAction = 'j2commerce.viewproducts';

    protected string $writeAction = 'j2commerce.editproducts';

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
        $filter        = InputFilter::getInstance();

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

    /** POST products/:id/variants: the body is one `variants.upsert` entry without an id; answers the product. */
    public function createVariant(): void
    {
        $this->assertAllowed($this->readAction);
        $this->assertAllowed($this->writeAction, 'core.create');

        $productId = $this->routeInt('id');
        $entry     = $this->bodyObject();

        if (\array_key_exists('id', $entry)) {
            throw new InvalidParameterException('A new variant has no id; use PATCH products/:id/variants/:vid to change one.', 400, null, 'id');
        }

        $this->productModel()->applyVariantDocument($productId, ['upsert' => [$entry]]);

        $this->app->setHeader('status', 201);
        $this->displayItem($productId);
    }

    /** PATCH products/:id/variants/:vid — the body is the entry's fields; answers with the product. */
    public function updateVariant(): void
    {
        $this->assertAllowed($this->readAction);
        $this->assertAllowed($this->writeAction, 'core.edit');

        $productId = $this->routeInt('id');
        $entry     = $this->bodyObject();

        if (\array_key_exists('id', $entry)) {
            throw new InvalidParameterException('The variant id comes from the route, not the body.', 400, null, 'id');
        }

        $this->productModel()->applyVariantDocument($productId, ['upsert' => [['id' => $this->routeInt('vid')] + $entry]]);

        $this->displayItem($productId);
    }

    /** DELETE products/:id/variants/:vid — 409 when the variant appears on an order. */
    public function deleteVariant(): void
    {
        $this->assertAllowed($this->writeAction, 'core.delete');

        $this->productModel()->applyVariantDocument($this->routeInt('id'), ['delete' => [$this->routeInt('vid')]]);

        $this->app->setHeader('status', 204);
    }

    private function productModel(): ProductModel
    {
        return $this->getModel('Product', 'Api', ['ignore_request' => true]);
    }

    /** A route var lands in the POST input on a POST request and in the main input otherwise. */
    private function routeInt(string $key): int
    {
        $id = $this->input->getInt($key, 0) ?: $this->input->post->getInt($key, 0);

        if ($id <= 0) {
            throw new InvalidParameterException('JLIB_FORM_VALIDATE_FIELD_INVALID', 400, null, $key);
        }

        return $id;
    }

    /** @return array<string, mixed> */
    private function bodyObject(): array
    {
        $body = json_decode((string) $this->input->json->getRaw(), true);

        if (!\is_array($body) || ($body !== [] && array_is_list($body))) {
            throw new InvalidParameterException('The request body must be a JSON object.', 400);
        }

        return $body;
    }
}
