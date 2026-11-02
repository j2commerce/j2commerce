<?php

/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace J2Commerce\Component\J2commerce\Administrator\Api;

\defined('_JEXEC') or die;

use J2Commerce\Component\J2commerce\Administrator\Service\ProductOptionService;
use J2Commerce\Component\J2commerce\Administrator\Service\ProductService;
use J2Commerce\Component\J2commerce\Administrator\Service\VariantMatrixService;

/**
 * The API handler of a type whose options are price/weight modifiers on one master variant
 * (simple, configurable, downloadable). A matrix type overrides the public api*() methods and
 * keeps the services and the per-payload memo.
 */
trait ProductApiOptionsTrait
{
    /** @var \WeakMap<ProductPayload, array<string, mixed>>|null  Validated documents, built once per payload. */
    private ?\WeakMap $apiDocuments = null;

    public function apiValidateCreate(ProductPayload $payload): void
    {
        $this->apiOptionsDocument($payload);
    }

    public function apiCreate(ProductPayload $payload): int
    {
        $options = $this->apiOptionsDocument($payload);
        $row     = $this->apiProductRow($payload);

        if ($options !== []) {
            $row['has_options'] = 1;
        }

        $id = (int) (new ProductService($this->mvcFactory))->saveProduct($row);

        if ($id > 0 && $options !== []) {
            $this->optionService()->writeOptions($id, $options, 0);
        }

        return $id;
    }

    public function apiValidatePatch(int $productId, ProductPayload $payload): void
    {
        $this->apiOptionsPatch($productId, $payload);
    }

    public function apiPatch(int $productId, ProductPayload $payload): void
    {
        if ($this->apiNeedsProductSave($payload)) {
            (new ProductService($this->mvcFactory))->saveProduct($this->apiProductRow($payload));
        }

        $patch = $this->apiOptionsPatch($productId, $payload);

        if ($patch !== null) {
            $service = $this->optionService();
            $service->applyOptionsPatch($productId, $patch, 0, $this->variantService());
            $service->syncHasOptions($productId);
        }
    }

    public function apiRead(object $product): array
    {
        return [];
    }

    /** Whether a value may link to values of a parent option (configurable). */
    protected function apiOptionParents(): bool
    {
        return false;
    }

    /** The saveProduct() row; a type with keys of its own (downloadable files) folds them in here. */
    protected function apiProductRow(ProductPayload $payload): array
    {
        return $payload->productRow();
    }

    protected function apiNeedsProductSave(ProductPayload $payload): bool
    {
        return $payload->needsProductSave();
    }

    /** @return list<array>  The normalised create-time `options` document. */
    protected function apiOptionsDocument(ProductPayload $payload): array
    {
        return $this->apiMemo($payload, 'options', fn (): array => $this->optionService()
            ->validateOptionsDocument($payload->get('options'), '/options', true, $this->apiOptionParents(), false));
    }

    /** The normalised patch-time `options` document, or null when the key was not sent. */
    protected function apiOptionsPatch(int $productId, ProductPayload $payload): ?array
    {
        if (!$payload->has('options')) {
            return null;
        }

        return $this->apiMemo($payload, 'options', fn (): array => $this->optionService()
            ->validateOptionsPatch($productId, $payload->get('options'), '/options', true, $this->apiOptionParents()));
    }

    /** Validate and apply share one build: the document is kept per payload, under `$key`. */
    protected function apiMemo(ProductPayload $payload, string $key, callable $build): array
    {
        $this->apiDocuments ??= new \WeakMap();
        $documents = $this->apiDocuments[$payload] ?? [];

        if (!\array_key_exists($key, $documents)) {
            $documents[$key]              = $build();
            $this->apiDocuments[$payload] = $documents;
        }

        return $documents[$key];
    }

    protected function optionService(): ProductOptionService
    {
        return new ProductOptionService($this->mvcFactory);
    }

    protected function variantService(): VariantMatrixService
    {
        return new VariantMatrixService($this->mvcFactory);
    }
}
