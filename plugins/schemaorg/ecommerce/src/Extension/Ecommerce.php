<?php

declare(strict_types=1);

/**
 * @package     J2Commerce
 * @subpackage  plg_schemaorg_ecommerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Joomla\Plugin\Schemaorg\Ecommerce\Extension;

use Joomla\CMS\Event\Plugin\System\Schemaorg\BeforeCompileHeadEvent;
use Joomla\CMS\Event\Plugin\System\Schemaorg\PrepareFormEvent;
use Joomla\CMS\Event\Plugin\System\Schemaorg\PrepareSaveEvent;
use Joomla\CMS\Form\Form;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\Schemaorg\SchemaorgPluginTrait;
use Joomla\CMS\Schemaorg\SchemaorgPrepareDateTrait;
use Joomla\CMS\Schemaorg\SchemaorgPrepareImageTrait;
use Joomla\CMS\Uri\Uri;
use Joomla\Event\Priority;
use Joomla\Event\SubscriberInterface;
use Joomla\Plugin\Schemaorg\Ecommerce\Event\BreadcrumbSchemaPrepareEvent;
use Joomla\Plugin\Schemaorg\Ecommerce\Event\FormPrepareEvent;
use Joomla\Plugin\Schemaorg\Ecommerce\Event\ItemListSchemaPrepareEvent;
use Joomla\Plugin\Schemaorg\Ecommerce\Event\OffersSchemaPrepareEvent;
use Joomla\Plugin\Schemaorg\Ecommerce\Event\OrganizationSchemaPrepareEvent;
use Joomla\Plugin\Schemaorg\Ecommerce\Event\ProductSchemaPrepareEvent;
use Joomla\Plugin\Schemaorg\Ecommerce\Event\ReviewsSchemaPrepareEvent;
use Joomla\Plugin\Schemaorg\Ecommerce\Event\VariantSchemaPrepareEvent;
use Joomla\Plugin\Schemaorg\Ecommerce\Helper\J2CommerceSchemaHelper;
use Joomla\Registry\Registry;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

final class Ecommerce extends CMSPlugin implements SubscriberInterface
{
    use SchemaorgPluginTrait;
    use SchemaorgPrepareDateTrait;
    use SchemaorgPrepareImageTrait;

    public $autoloadLanguage = true;

    protected $pluginName = 'Ecommerce';

    private ?J2CommerceSchemaHelper $helper = null;

    public static function getSubscribedEvents(): array
    {
        return [
            'onSchemaPrepareForm'       => 'onSchemaPrepareForm',
            'onSchemaBeforeCompileHead' => ['onSchemaBeforeCompileHead', Priority::BELOW_NORMAL],
            'onSchemaPrepareSave'       => 'onSchemaPrepareSave',
        ];
    }

    private function getHelper(): J2CommerceSchemaHelper
    {
        return $this->helper ??= J2CommerceSchemaHelper::getInstance();
    }

    public function onSchemaPrepareForm(PrepareFormEvent $event): void
    {
        $form    = $event->getForm();
        $context = $form->getName();

        if (!$this->isSupported($context)) {
            return;
        }

        $this->addSchemaType($event);

        $form->addFieldPath(JPATH_PLUGINS . '/' . $this->_type . '/' . $this->_name . '/src/Field');

        $formPath = JPATH_PLUGINS . '/' . $this->_type . '/' . $this->_name . '/forms/schemaorg.xml';

        if (is_file($formPath)) {
            $form->loadFile($formPath);
        }

        $itemId = $this->getApplication()->getInput()->getInt('id', 0);
        $this->dispatchFormEvent($form, $context, $itemId > 0 ? $itemId : null);
    }

    public function onSchemaBeforeCompileHead(BeforeCompileHeadEvent $event): void
    {
        $debugMode = (bool) $this->params->get('debug_mode', 0);
        $debugInfo = [];

        $schema = $event->getSchema();
        $graph  = $schema->get('@graph');

        if ($graph instanceof Registry) {
            $graph = $graph->toArray();
        } elseif (!\is_array($graph)) {
            $graph = [];
        }

        $context           = $this->getCurrentProductContext();
        $hasEcommerceEntry = false;
        $hasProductEntry   = false;

        foreach ($graph as $entry) {
            $type = $entry['@type'] ?? null;

            if ($type === 'Ecommerce') {
                $hasEcommerceEntry = true;
            } elseif (\in_array($type, ['Product', 'ProductGroup'], true)) {
                $hasProductEntry = true;
            }
        }

        foreach ($graph as &$entry) {
            if (($entry['@type'] ?? null) === 'Ecommerce') {
                $entry             = $this->buildProductSchema($entry);
                $hasEcommerceEntry = true;
            }
        }

        unset($entry);

        if (!$hasEcommerceEntry && !$hasProductEntry && $context['type'] !== null && $context['id'] !== null) {
            $helper = $this->getHelper();

            if ($helper->isJ2CommerceAvailable()) {
                $product = match ($context['type']) {
                    'article' => $helper->getProductByArticleId($context['id']),
                    'product' => $helper->getProductById($context['id']),
                    default   => null,
                };

                if ($product !== null) {
                    $productSchema = $this->buildProductSchema([]);

                    if (!empty($productSchema['@type'])) {
                        $productSchema['@id'] = Uri::root() . '#/schema/Product/' . $product->j2commerce_product_id;

                        foreach ($graph as $graphEntry) {
                            if (($graphEntry['@type'] ?? null) === 'WebPage' && isset($graphEntry['@id'])) {
                                $productSchema['isPartOf'] = ['@id' => $graphEntry['@id']];
                                break;
                            }
                        }

                        $graph[] = $productSchema;

                        if ($debugMode) {
                            $debugInfo[] = 'Product schema injected for product_id=' . $product->j2commerce_product_id;
                        }
                    }
                }
            }
        }

        $schema->set('@graph', $graph);

        if ($debugMode && !empty($debugInfo)) {
            $this->getApplication()->getDocument()->addCustomTag(
                '<!-- Ecommerce Schema Debug: ' . implode(' | ', $debugInfo) . ' -->'
            );
        }
    }

    public function onSchemaPrepareSave(PrepareSaveEvent $event): void
    {
        $subject = $event->getData();

        if (empty($subject->schemaType) || $subject->schemaType !== 'Ecommerce' || !isset($subject->schema)) {
            return;
        }

        $schema        = new Registry($subject->schema);
        $ecommerceData = $schema->get('Ecommerce');

        if (empty($ecommerceData)) {
            return;
        }

        if ($ecommerceData instanceof Registry) {
            $ecommerceData = $ecommerceData->toArray();
        } elseif (\is_object($ecommerceData)) {
            $ecommerceData = (array) $ecommerceData;
        }

        $ecommerceData = $this->validateSchemaData($ecommerceData);
        $ecommerceData = $this->cleanSchemaData($ecommerceData);

        $schema->set('Ecommerce', $ecommerceData);
        $subject->schema = $schema->toString();

        $event->setArgument('subject', $subject);
    }

    /** @return array{type: string|null, id: int|null} */
    private function getCurrentProductContext(): array
    {
        $input  = $this->getApplication()->getInput();
        $option = $input->getCmd('option');
        $view   = $input->getCmd('view');
        $id     = $input->getInt('id', 0);

        if ($option === 'com_content' && $view === 'article' && $id > 0) {
            return ['type' => 'article', 'id' => $id];
        }

        if ($option === 'com_j2commerce') {
            if ($view === 'product' && $id > 0) {
                return ['type' => 'product', 'id' => $id];
            }

            $productId = $input->getInt('product_id', 0);

            if ($productId > 0) {
                return ['type' => 'product', 'id' => $productId];
            }
        }

        return ['type' => null, 'id' => null];
    }

    protected function buildProductSchema(array $entry): array
    {
        $helper  = $this->getHelper();
        $product = null;

        if ($helper->isJ2CommerceAvailable()) {
            $context = $this->getCurrentProductContext();

            $product = match ($context['type']) {
                'article' => $context['id'] ? $helper->getProductByArticleId($context['id']) : null,
                'product' => $context['id'] ? $helper->getProductById($context['id']) : null,
                default   => null,
            };
        }

        $isVariable   = $product && $helper->isVariableProduct($product);
        $variantCount = \count($product->variants ?? []);

        if ($isVariable && $variantCount > 1) {
            return $this->buildProductGroupSchema($entry, $product);
        }

        return $this->buildSimpleProductSchema($entry, $product);
    }

    private function buildSimpleProductSchema(array $entry, ?object $product): array
    {
        $helper = $this->getHelper();

        $schema = [
            '@type' => 'Product',
            'name'  => !empty($entry['name']) ? $entry['name'] : ($product ? $helper->getProductName($product) : ''),
        ];

        if (!empty($entry['description'])) {
            $schema['description'] = strip_tags($entry['description']);
        } elseif ($product) {
            $description = $helper->getProductDescription($product, 5000);

            if (!empty($description)) {
                $schema['description'] = $description;
            }
        }

        $images = $this->buildImagesArray($entry, $product);

        if (!empty($images)) {
            $schema['image'] = \count($images) === 1 ? $images[0] : $images;
        }

        $schema['sku'] = !empty($entry['sku'])
            ? $entry['sku']
            : ($product?->variant?->sku ?? '');

        if (!empty($entry['gtin'])) {
            $schema['gtin'] = $entry['gtin'];
        } elseif (!empty($product?->variant?->upc)) {
            $schema['gtin'] = $product->variant->upc;
        }

        if (!empty($entry['mpn'])) {
            $schema['mpn'] = $entry['mpn'];
        }

        $schema['brand']  = $this->buildBrandSchema($entry, $product);
        $schema['offers'] = $this->buildOffersSchema($entry, $product);

        if ($product) {
            $schema['url'] = $helper->getProductUrl($product);
        }

        $schema = $this->cleanSchemaData($schema);

        $context   = $this->getCurrentProductContext();
        $articleId = $context['type'] === 'article' ? $context['id'] : null;

        $schema = $this->dispatchProductEvent($schema, $product, $articleId);

        if ($product) {
            $schema = $this->dispatchReviewsEvent($schema, (int) $product->j2commerce_product_id, $articleId);
        }

        return $schema;
    }

    private function buildProductGroupSchema(array $entry, object $product): array
    {
        $helper = $this->getHelper();

        $schema = [
            '@type'          => 'ProductGroup',
            'productGroupID' => 'pg-' . $product->j2commerce_product_id,
            'name'           => !empty($entry['name']) ? $entry['name'] : $helper->getProductName($product),
        ];

        if (!empty($entry['description'])) {
            $schema['description'] = strip_tags($entry['description']);
        } else {
            $description = $helper->getProductDescription($product, 5000);

            if (!empty($description)) {
                $schema['description'] = $description;
            }
        }

        $images = $this->buildImagesArray($entry, $product);

        if (!empty($images)) {
            $schema['image'] = \count($images) === 1 ? $images[0] : $images;
        }

        $schema['brand'] = $this->buildBrandSchema($entry, $product);
        $schema['url']   = $helper->getProductUrl($product);

        $variesBy = $this->buildVariesByArray($product);

        if (!empty($variesBy)) {
            $schema['variesBy'] = $variesBy;
        }

        $variants = $this->buildVariantSchemas($product, $entry);

        if (!empty($variants)) {
            $schema['hasVariant'] = $variants;
        }

        return $this->cleanSchemaData($schema);
    }

    private function buildImagesArray(array $entry, ?object $product): array
    {
        $images = [];

        if (!empty($entry['image'])) {
            $images[] = $this->prepareImage($entry['image']);
        }

        if (!empty($entry['additionalImages']) && \is_array($entry['additionalImages'])) {
            foreach ($entry['additionalImages'] as $img) {
                if (!empty($img['image'])) {
                    $images[] = $this->prepareImage($img['image']);
                }
            }
        }

        if (empty($images) && $product) {
            $images = array_merge($images, $this->getHelper()->getAllProductImages($product));
        }

        return array_unique(array_filter($images));
    }

    private function buildBrandSchema(array $entry, ?object $product): ?array
    {
        if (!empty($entry['brand']['name'])) {
            return ['@type' => 'Brand', 'name' => $entry['brand']['name']];
        }

        if (!empty($product?->manufacturer?->company)) {
            return ['@type' => 'Brand', 'name' => $product->manufacturer->company];
        }

        $defaultBrand = $this->params->get('default_brand', '');

        if (!empty($defaultBrand)) {
            return ['@type' => 'Brand', 'name' => $defaultBrand];
        }

        return null;
    }

    private function buildOffersSchema(array $entry, ?object $product): array
    {
        $helper = $this->getHelper();

        $offer = ['@type' => 'Offer'];

        if (!empty($entry['offers']['price'])) {
            $offer['price'] = (string) number_format((float) $entry['offers']['price'], 2, '.', '');
        } elseif ($product?->variant) {
            $offer['price'] = (string) number_format($helper->getProductPrice($product->variant), 2, '.', '');
        }

        $offer['priceCurrency'] = !empty($entry['offers']['priceCurrency'])
            ? $entry['offers']['priceCurrency']
            : $helper->getCurrencyCode();

        if (!empty($entry['offers']['availability'])) {
            $offer['availability'] = $entry['offers']['availability'];
        } elseif ($product?->variant) {
            $offer['availability'] = $helper->mapAvailability($product->variant);
        } else {
            $offer['availability'] = 'https://schema.org/InStock';
        }

        if (!empty($entry['offers']['url'])) {
            $offer['url'] = $entry['offers']['url'];
        } elseif ($product) {
            $offer['url'] = $helper->getProductUrl($product);
        }

        if (!empty($entry['offers']['priceValidUntil'])) {
            $offer['priceValidUntil'] = $this->prepareDate($entry['offers']['priceValidUntil']);
        }

        $seller = $this->buildSellerSchema($entry);

        if ($seller) {
            $offer['seller'] = $seller;
        }

        if ($product?->variant) {
            $offer = $this->dispatchOffersEvent($offer, $product->variant, (int) $product->j2commerce_product_id);
        }

        return $offer;
    }

    private function buildSellerSchema(array $entry): ?array
    {
        if (!empty($entry['offers']['seller']['name'])) {
            return ['@type' => 'Organization', 'name' => $entry['offers']['seller']['name']];
        }

        $sellerName = $this->params->get('organization_name', '') ?: $this->getHelper()->getStoreName();

        return !empty($sellerName)
            ? ['@type' => 'Organization', 'name' => $sellerName]
            : null;
    }

    private function buildVariesByArray(object $product): array
    {
        $helper  = $this->getHelper();
        $options = $helper->getVariantOptions((int) $product->j2commerce_product_id);

        return array_unique(array_map([$helper, 'mapOptionToSchemaProperty'], $options));
    }

    private function buildVariantSchemas(object $product, array $entry): array
    {
        $helper    = $this->getHelper();
        $variants  = [];
        $productId = (int) $product->j2commerce_product_id;

        foreach ($product->variants as $variant) {
            if ((int) $variant->is_master === 1) {
                continue;
            }

            $variantSchema = [
                '@type' => 'Product',
                'name'  => $helper->getProductName($product) . (!empty($variant->variant_name) ? ' - ' . $variant->variant_name : ''),
            ];

            if (!empty($variant->sku)) {
                $variantSchema['sku'] = $variant->sku;
            }

            if (!empty($variant->upc)) {
                $variantSchema['gtin'] = $variant->upc;
            }

            $variantOffer = [
                '@type'         => 'Offer',
                'price'         => (string) number_format((float) $variant->price, 2, '.', ''),
                'priceCurrency' => $helper->getCurrencyCode(),
                'availability'  => $helper->mapAvailability($variant),
            ];

            $seller = $this->buildSellerSchema($entry);

            if ($seller) {
                $variantOffer['seller'] = $seller;
            }

            $variantSchema['offers'] = $this->dispatchOffersEvent($variantOffer, $variant, $productId);
            $variantSchema           = $this->dispatchVariantEvent($variantSchema, $variant, $productId, $product);

            $variants[] = $variantSchema;
        }

        return $variants;
    }

    private function validateSchemaData(array $data): array
    {
        if (isset($data['offers']['price'])) {
            $data['offers']['price'] = (string) number_format((float) $data['offers']['price'], 2, '.', '');
        }

        if (isset($data['offers']['priceCurrency'])) {
            $data['offers']['priceCurrency'] = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $data['offers']['priceCurrency']), 0, 3));
        }

        if (isset($data['gtin'])) {
            $data['gtin'] = preg_replace('/[^0-9]/', '', $data['gtin']);
        }

        $validAvailability = [
            'https://schema.org/InStock',
            'https://schema.org/OutOfStock',
            'https://schema.org/PreOrder',
            'https://schema.org/BackOrder',
            'https://schema.org/Discontinued',
            'https://schema.org/LimitedAvailability',
            'https://schema.org/OnlineOnly',
            'https://schema.org/SoldOut',
        ];

        if (isset($data['offers']['availability']) && !\in_array($data['offers']['availability'], $validAvailability, true)) {
            $data['offers']['availability'] = 'https://schema.org/InStock';
        }

        return $data;
    }

    protected function cleanSchemaData(array $data): array
    {
        $cleaned = [];

        foreach ($data as $key => $value) {
            if ($value === '' || $value === null) {
                continue;
            }

            if (\is_array($value)) {
                $value = $this->cleanSchemaData($value);

                if (empty($value)) {
                    continue;
                }
            }

            $cleaned[$key] = $value;
        }

        return $cleaned;
    }

    protected function dispatchProductEvent(array $schema, ?object $product, ?int $articleId = null): array
    {
        $event = new ProductSchemaPrepareEvent(
            'onJ2CommerceSchemaProductPrepare',
            [
                'subject'   => $schema,
                'product'   => $product,
                'articleId' => $articleId,
            ]
        );

        $this->getApplication()->getDispatcher()->dispatch('onJ2CommerceSchemaProductPrepare', $event);

        return $event->getSchema();
    }

    protected function dispatchReviewsEvent(array $schema, int $productId, ?int $articleId = null): array
    {
        PluginHelper::importPlugin('j2commerce');

        $event = new ReviewsSchemaPrepareEvent(
            'onJ2CommerceSchemaReviewsPrepare',
            [
                'subject'   => $schema,
                'productId' => $productId,
                'articleId' => $articleId,
            ]
        );

        $this->getApplication()->getDispatcher()->dispatch('onJ2CommerceSchemaReviewsPrepare', $event);

        return $event->getSchema();
    }

    protected function dispatchOffersEvent(array $offerSchema, object $variant, int $productId): array
    {
        $event = new OffersSchemaPrepareEvent(
            'onJ2CommerceSchemaOffersPrepare',
            [
                'subject'   => $offerSchema,
                'variant'   => $variant,
                'productId' => $productId,
            ]
        );

        $this->getApplication()->getDispatcher()->dispatch('onJ2CommerceSchemaOffersPrepare', $event);

        return $event->getSchema();
    }

    protected function dispatchVariantEvent(
        array $variantSchema,
        object $variant,
        int $productId,
        object $parentProduct,
    ): array {
        $event = new VariantSchemaPrepareEvent(
            'onJ2CommerceSchemaVariantPrepare',
            [
                'subject'        => $variantSchema,
                'variant'        => $variant,
                'productId'      => $productId,
                'parentProduct'  => $parentProduct,
                'variantOptions' => [],
            ]
        );

        $this->getApplication()->getDispatcher()->dispatch('onJ2CommerceSchemaVariantPrepare', $event);

        return $event->getSchema();
    }

    protected function dispatchFormEvent(Form $form, string $context, ?int $itemId = null): void
    {
        $event = new FormPrepareEvent(
            'onJ2CommerceSchemaFormPrepare',
            [
                'subject' => $form,
                'context' => $context,
                'itemId'  => $itemId,
            ]
        );

        $this->getApplication()->getDispatcher()->dispatch('onJ2CommerceSchemaFormPrepare', $event);
    }

    protected function dispatchOrganizationEvent(array $schema): array
    {
        $event = new OrganizationSchemaPrepareEvent(
            'onJ2CommerceSchemaOrganizationPrepare',
            [
                'subject' => $schema,
            ]
        );

        $this->getApplication()->getDispatcher()->dispatch('onJ2CommerceSchemaOrganizationPrepare', $event);

        return $event->getSchema();
    }

    protected function dispatchBreadcrumbEvent(array $schema, ?int $productId = null, ?int $categoryId = null): array
    {
        $event = new BreadcrumbSchemaPrepareEvent(
            'onJ2CommerceSchemaBreadcrumbPrepare',
            [
                'subject'    => $schema,
                'productId'  => $productId,
                'categoryId' => $categoryId,
            ]
        );

        $this->getApplication()->getDispatcher()->dispatch('onJ2CommerceSchemaBreadcrumbPrepare', $event);

        return $event->getSchema();
    }

    protected function dispatchItemListEvent(
        array $schema,
        int $categoryId,
        ?object $category = null,
        int $page = 1,
        int $limit = 20,
    ): array {
        $event = new ItemListSchemaPrepareEvent(
            'onJ2CommerceSchemaItemListPrepare',
            [
                'subject'    => $schema,
                'categoryId' => $categoryId,
                'category'   => $category,
                'page'       => $page,
                'limit'      => $limit,
            ]
        );

        $this->getApplication()->getDispatcher()->dispatch('onJ2CommerceSchemaItemListPrepare', $event);

        return $event->getSchema();
    }
}
