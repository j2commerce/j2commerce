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

/** A product-type behavior the products API writes through; the `api` prefix keeps clear of the `on*` lifecycle. */
interface ProductApiHandlerInterface
{
    /** Keys this type accepts on create and patch, for docs and for the allow-list the model applies. */
    public function apiSchema(): ProductApiSchema;

    /** @throws ProductApiException  400 with one pointer per problem; nothing written. */
    public function apiValidateCreate(ProductPayload $payload): void;

    /** Runs inside the model's transaction, never its own; returns the new product id. @throws ProductApiException */
    public function apiCreate(ProductPayload $payload): int;

    /** @throws ProductApiException */
    public function apiValidatePatch(int $productId, ProductPayload $payload): void;

    /** Runs inside the model's transaction, never its own. @throws ProductApiException */
    public function apiPatch(int $productId, ProductPayload $payload): void;

    /** Type-specific block merged into GET as `type_data`; variants and options are rendered by core. */
    public function apiRead(object $product): array;
}
