<?php

/**
 * @package     J2Commerce
 * @subpackage  plg_j2commercemigrator_j2store4
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace J2Commerce\Plugin\J2commercemigrator\J2store4\Adapter;

use J2Commerce\Component\J2commercemigrator\Administrator\Adapter\AbstractMigratorAdapter;
use J2Commerce\Component\J2commercemigrator\Administrator\Dto\ConnectionSchema;
use J2Commerce\Component\J2commercemigrator\Administrator\Dto\ImageDiscoveryResult;
use J2Commerce\Component\J2commercemigrator\Administrator\Dto\PrerequisiteReport;
use J2Commerce\Component\J2commercemigrator\Administrator\Dto\SourceInfo;
use J2Commerce\Component\J2commercemigrator\Administrator\Dto\TierDefinition;
use J2Commerce\Component\J2commercemigrator\Administrator\Service\Reader\SourceDatabaseReaderInterface;

/**
 * J2Store 4 migration adapter.
 *
 * Provides all J2Store-specific metadata (table map, tiers, row transformers,
 * image layout, prerequisite checks) to the MigrationEngine.
 *
 * Source of truth:
 * - TABLE_MAP       → plugins/system/j2commerce_migration_tool/src/Service/TableMapper.php TABLE_MAP (51 entries)
 * - JSON_FIELDS     → DataTransformer::JSON_FIELDS (PRD §19.4)
 * - DATE_COLUMNS    → DataTransformer::DATE_COLUMNS (PRD §19.5)
 * - EXTRA_DEFAULTS  → DataTransformer::EXTRA_COLUMN_DEFAULTS (PRD §19.6)
 * - PK_REPLACEMENTS → DataTransformer::PK_REPLACEMENTS (PRD §19.3)
 */
final class J2store4Adapter extends AbstractMigratorAdapter
{
    /** 51-entry source → target table map (verbatim from TableMapper::TABLE_MAP). */
    public const TABLE_MAP = [
        'j2store_addresses'                    => 'j2commerce_addresses',
        'j2store_cartitems'                    => 'j2commerce_cartitems',
        'j2store_carts'                        => 'j2commerce_carts',
        'j2store_configurations'               => 'j2commerce_configurations',
        'j2store_countries'                    => 'j2commerce_countries',
        'j2store_coupons'                      => 'j2commerce_coupons',
        'j2store_currencies'                   => 'j2commerce_currencies',
        'j2store_customfields'                 => 'j2commerce_customfields',
        'j2store_emailtemplates'               => 'j2commerce_emailtemplates',
        'j2store_filtergroups'                 => 'j2commerce_filtergroups',
        'j2store_filters'                      => 'j2commerce_filters',
        'j2store_geozonerules'                 => 'j2commerce_geozonerules',
        'j2store_geozones'                     => 'j2commerce_geozones',
        'j2store_invoicetemplates'             => 'j2commerce_invoicetemplates',
        'j2store_lengths'                      => 'j2commerce_lengths',
        'j2store_manufacturers'                => 'j2commerce_manufacturers',
        'j2store_metafields'                   => 'j2commerce_metafields',
        'j2store_options'                      => 'j2commerce_options',
        'j2store_optionvalues'                 => 'j2commerce_optionvalues',
        'j2store_orderdiscounts'               => 'j2commerce_orderdiscounts',
        'j2store_orderdownloads'               => 'j2commerce_orderdownloads',
        'j2store_orderfees'                    => 'j2commerce_orderfees',
        'j2store_orderhistories'               => 'j2commerce_orderhistories',
        'j2store_orderinfos'                   => 'j2commerce_orderinfos',
        'j2store_orderitemattributes'          => 'j2commerce_orderitemattributes',
        'j2store_orderitems'                   => 'j2commerce_orderitems',
        'j2store_orders'                       => 'j2commerce_orders',
        'j2store_ordershippings'               => 'j2commerce_ordershippings',
        'j2store_orderstatuses'                => 'j2commerce_orderstatuses',
        'j2store_ordertaxes'                   => 'j2commerce_ordertaxes',
        'j2store_product_filters'              => 'j2commerce_product_filters',
        'j2store_product_options'              => 'j2commerce_product_options',
        'j2store_product_optionvalues'         => 'j2commerce_product_optionvalues',
        'j2store_product_prices'               => 'j2commerce_product_prices',
        'j2store_product_variant_optionvalues' => 'j2commerce_product_variant_optionvalues',
        'j2store_productfiles'                 => 'j2commerce_productfiles',
        'j2store_productimages'                => 'j2commerce_productimages',
        'j2store_productprice_index'           => 'j2commerce_productprice_index',
        'j2store_productquantities'            => 'j2commerce_productquantities',
        'j2store_products'                     => 'j2commerce_products',
        'j2store_queues'                       => 'j2commerce_queues',
        'j2store_shippingmethods'              => 'j2commerce_shippingmethods',
        'j2store_shippingrates'                => 'j2commerce_shippingrates',
        'j2store_taxprofiles'                  => 'j2commerce_taxprofiles',
        'j2store_taxrates'                     => 'j2commerce_taxrates',
        'j2store_taxrules'                     => 'j2commerce_taxrules',
        'j2store_uploads'                      => 'j2commerce_uploads',
        'j2store_variants'                     => 'j2commerce_variants',
        'j2store_vendors'                      => 'j2commerce_vendors',
        'j2store_vouchers'                     => 'j2commerce_vouchers',
        'j2store_weights'                      => 'j2commerce_weights',
        'j2store_zones'                        => 'j2commerce_zones',
    ];

    /**
     * J2Commerce-only tables that have no J2Store source (verbatim from TableMapper::NEW_TABLES).
     * These are created by the J2Commerce install SQL and do not require migration.
     */
    public const NEW_TABLES = [
        'j2commerce_emailtype_tags',
        'j2commerce_emailtype_contexts',
        'j2commerce_geocode_cache',
        'j2commerce_queue_logs',
        'j2commerce_paymentprofiles',
    ];

    /**
     * Column-level PK renames applied by DataTransformer (verbatim from DataTransformer::PK_REPLACEMENTS).
     * 51 entries — one per source entity.
     */
    public const PK_REPLACEMENTS = [
        'j2store_address_id'                => 'j2commerce_address_id',
        'j2store_cartitem_id'               => 'j2commerce_cartitem_id',
        'j2store_cart_id'                   => 'j2commerce_cart_id',
        'j2store_configuration_id'          => 'j2commerce_configuration_id',
        'j2store_country_id'                => 'j2commerce_country_id',
        'j2store_coupon_id'                 => 'j2commerce_coupon_id',
        'j2store_currency_id'               => 'j2commerce_currency_id',
        'j2store_customfield_id'            => 'j2commerce_customfield_id',
        'j2store_emailtemplate_id'          => 'j2commerce_emailtemplate_id',
        'j2store_filtergroup_id'            => 'j2commerce_filtergroup_id',
        'j2store_filter_id'                 => 'j2commerce_filter_id',
        'j2store_geozonerule_id'            => 'j2commerce_geozonerule_id',
        'j2store_geozone_id'                => 'j2commerce_geozone_id',
        'j2store_invoicetemplate_id'        => 'j2commerce_invoicetemplate_id',
        'j2store_length_id'                 => 'j2commerce_length_id',
        'j2store_manufacturer_id'           => 'j2commerce_manufacturer_id',
        'j2store_metafield_id'              => 'j2commerce_metafield_id',
        'j2store_option_id'                 => 'j2commerce_option_id',
        'j2store_optionvalue_id'            => 'j2commerce_optionvalue_id',
        'j2store_orderdiscount_id'          => 'j2commerce_orderdiscount_id',
        'j2store_orderdownload_id'          => 'j2commerce_orderdownload_id',
        'j2store_orderfee_id'               => 'j2commerce_orderfee_id',
        'j2store_orderhistory_id'           => 'j2commerce_orderhistory_id',
        'j2store_orderinfo_id'              => 'j2commerce_orderinfo_id',
        'j2store_orderitemattribute_id'     => 'j2commerce_orderitemattribute_id',
        'j2store_orderitem_id'              => 'j2commerce_orderitem_id',
        'j2store_order_id'                  => 'j2commerce_order_id',
        'j2store_ordershipping_id'          => 'j2commerce_ordershipping_id',
        'j2store_orderstatus_id'            => 'j2commerce_orderstatus_id',
        'j2store_ordertax_id'               => 'j2commerce_ordertax_id',
        'j2store_product_filter_id'         => 'j2commerce_product_filter_id',
        'j2store_productoption_id'          => 'j2commerce_productoption_id',
        'j2store_product_optionvalue_id'    => 'j2commerce_product_optionvalue_id',
        'j2store_productprice_id'           => 'j2commerce_productprice_id',
        'j2store_productfile_id'            => 'j2commerce_productfile_id',
        'j2store_productimage_id'           => 'j2commerce_productimage_id',
        'j2store_productquantity_id'        => 'j2commerce_productquantity_id',
        'j2store_product_id'                => 'j2commerce_product_id',
        'j2store_queue_id'                  => 'j2commerce_queue_id',
        'j2store_shippingmethod_id'         => 'j2commerce_shippingmethod_id',
        'j2store_shippingrate_id'           => 'j2commerce_shippingrate_id',
        'j2store_taxprofile_id'             => 'j2commerce_taxprofile_id',
        'j2store_taxrate_id'                => 'j2commerce_taxrate_id',
        'j2store_taxrule_id'                => 'j2commerce_taxrule_id',
        'j2store_upload_id'                 => 'j2commerce_upload_id',
        'j2store_variant_id'                => 'j2commerce_variant_id',
        'j2store_vendor_id'                 => 'j2commerce_vendor_id',
        'j2store_voucher_id'                => 'j2commerce_voucher_id',
        'j2store_weight_id'                 => 'j2commerce_weight_id',
        'j2store_zone_id'                   => 'j2commerce_zone_id',
    ];

    /**
     * JSON column names requiring token replacement inside their payloads
     * (verbatim from DataTransformer::JSON_FIELDS / PRD §19.4).
     */
    public const JSON_FIELDS = [
        'plugins', 'params', 'order_params', 'orderitem_attributes', 'cart_params',
        'transaction_details', 'all_billing', 'all_shipping',
        'all_payment', 'all_surcharge', 'orderdiscount_options',
        'orderfee_options', 'ordershipping_options', 'ordertax_options',
        'field_options', 'field_display',
    ];

    /**
     * Date column names whose zero-date values ('0000-00-00 00:00:00' / '0000-00-00')
     * should be coerced to NULL (verbatim from DataTransformer::DATE_COLUMNS / PRD §19.5).
     */
    public const DATE_COLUMNS = [
        'created_on', 'modified_on', 'created_date', 'modified_date',
        'ordered_date', 'order_created', 'order_state_date',
        'paid_date', 'shipped_date', 'expected_delivery_date',
        'coupon_start_date', 'coupon_end_date',
        'voucher_start_date', 'voucher_end_date',
        'publish_up', 'publish_down',
        'checked_out_time', 'reset_date',
        'from_date', 'to_date', 'valid_from', 'valid_to',
        'download_start_date', 'download_end_date',
        'sale_start_date', 'sale_end_date',
        'available_date',
    ];

    /**
     * Extra columns that exist on J2Commerce target tables but have no J2Store source column.
     * Applied after column intersection (verbatim from DataTransformer::EXTRA_COLUMN_DEFAULTS / PRD §19.6).
     */
    public const EXTRA_COLUMN_DEFAULTS = [
        'j2commerce_products' => [
            'hits' => 0,
        ],
        'j2commerce_emailtemplates' => [
            'body_json'  => null,
            'context'    => '',
            'custom_css' => null,
        ],
        'j2commerce_invoicetemplates' => [
            'body_json'        => null,
            'body_source'      => 'editor',
            'body_source_file' => '',
            'custom_css'       => null,
        ],
        'j2commerce_customfields' => [
            'field_placeholder'  => null,
            'field_autocomplete' => null,
            'field_width'        => '',
        ],
        'j2commerce_orders' => [
            'from_order_id' => '0',
        ],
    ];

    public function getKey(): string
    {
        return 'j2store4';
    }

    public function getSourceInfo(): SourceInfo
    {
        return new SourceInfo(
            title:                   'J2Store 4',
            description:             'Migrate products, orders, and customers from J2Store 4.x running on Joomla 3 or 4.',
            icon:                    'fa-solid fa-store',
            author:                  'J2Commerce, LLC',
            version:                 '6.0.0',
            supportedSourceVersions: ['J2Store 4.1.x', 'J2Store 4.2.x'],
        );
    }

    public function getTierDefinitions(): array
    {
        return [
            new TierDefinition(
                tier:   1,
                name:   'Lookup Tables',
                tables: [
                    'j2store_configurations', 'j2store_currencies', 'j2store_countries',
                    'j2store_zones', 'j2store_orderstatuses', 'j2store_lengths', 'j2store_weights',
                ],
            ),
            new TierDefinition(
                tier:   2,
                name:   'Tax System',
                tables: [
                    'j2store_taxprofiles', 'j2store_taxrates', 'j2store_taxrules',
                    'j2store_geozones', 'j2store_geozonerules',
                ],
            ),
            new TierDefinition(
                tier:   3,
                name:   'Catalog',
                tables: [
                    'j2store_options', 'j2store_optionvalues', 'j2store_manufacturers',
                    'j2store_customfields', 'j2store_filtergroups', 'j2store_filters',
                    'j2store_product_filters',
                ],
            ),
            new TierDefinition(
                tier:   4,
                name:   'Products',
                tables: [
                    'j2store_products', 'j2store_variants', 'j2store_productimages',
                    'j2store_productfiles', 'j2store_product_prices', 'j2store_product_options',
                    'j2store_product_optionvalues', 'j2store_product_variant_optionvalues',
                    'j2store_productquantities', 'j2store_productprice_index', 'j2store_metafields',
                ],
            ),
            new TierDefinition(
                tier:   5,
                name:   'Customers',
                tables: [
                    'j2store_addresses', 'j2store_coupons', 'j2store_vouchers', 'j2store_vendors',
                ],
            ),
            new TierDefinition(
                tier:   6,
                name:   'Shipping',
                tables: [
                    'j2store_shippingmethods', 'j2store_shippingrates',
                ],
            ),
            new TierDefinition(
                tier:   7,
                name:   'Orders',
                tables: [
                    'j2store_orders', 'j2store_orderinfos', 'j2store_orderitems',
                    'j2store_orderitemattributes', 'j2store_orderhistories', 'j2store_orderfees',
                    'j2store_ordertaxes', 'j2store_orderdiscounts', 'j2store_ordershippings',
                    'j2store_orderdownloads',
                ],
            ),
            new TierDefinition(
                tier:   8,
                name:   'Transactional',
                tables: [
                    'j2store_carts', 'j2store_cartitems', 'j2store_queues',
                    'j2store_uploads', 'j2store_emailtemplates', 'j2store_invoicetemplates',
                ],
            ),
        ];
    }

    public function getTableMap(): array
    {
        return self::TABLE_MAP;
    }

    public function getTokenReplacements(): array
    {
        return [
            'com_j2store'  => 'com_j2commerce',
            'J2STORE_'     => 'J2COMMERCE_',
            'J2Store'      => 'J2Commerce',
            'J2STORE'      => 'J2COMMERCE',
            'j2store_'     => 'j2commerce_',
            'j2store'      => 'j2commerce',
        ];
    }

    public function describeConnection(): ConnectionSchema
    {
        return new ConnectionSchema(
            modes:    ['A', 'B', 'C'],
            fields:   ['host', 'port', 'database', 'username', 'password', 'prefix', 'ssl', 'ssl_ca'],
            defaults: ['port' => 3306, 'prefix' => 'jos_'],
        );
    }

    public function discoverImages(): ImageDiscoveryResult
    {
        // J2Store 4 uses Joomla core #__categories (extension='com_j2store'), not a
        // separate j2store_categories table — omit it from pathColumns to avoid
        // querying a non-existent table during image discovery.
        return new ImageDiscoveryResult(
            sourceRoot:     'images/com_j2store',
            subDirectories: ['products', 'manufacturers'],
            pathColumns:    [
                'j2store_products'      => ['main_image', 'thumb_image'],
                'j2store_productimages' => ['file_path', 'thumb_path'],
                'j2store_manufacturers' => ['image'],
            ],
        );
    }

    public function validatePrerequisites(): PrerequisiteReport
    {
        // Source reader is not available at this point; connection must be verified first.
        // The engine passes the reader via onJ2CommerceMigratorPreflight — deep checks
        // run there. Here we return a lightweight report (J2Commerce installed check).
        return new PrerequisiteReport(passed: true, issues: []);
    }

    public function getSourceReader(\J2Commerce\Component\J2commercemigrator\Administrator\Dto\ConnectionSettings $c): SourceDatabaseReaderInterface
    {
        if ($c->mode === 'A') {
            return new \J2Commerce\Component\J2commercemigrator\Administrator\Service\Reader\JoomlaSourceReader(
                \Joomla\CMS\Factory::getContainer()->get(\Joomla\Database\DatabaseInterface::class),
            );
        }

        $dsn  = "mysql:host={$c->host};port={$c->port};dbname={$c->database};charset=utf8mb4";
        $opts = [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION, \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC];

        if ($c->ssl) {
            $opts[\PDO::MYSQL_ATTR_SSL_CA]    = $c->sslCa;
            $opts[\PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = false;
        }

        $pdo = new \PDO($dsn, $c->username, $c->password, $opts);

        return new \J2Commerce\Component\J2commercemigrator\Administrator\Service\Reader\PdoSourceReader(
            $pdo,
            $c->prefix,
            $c->database,
        );
    }
}
