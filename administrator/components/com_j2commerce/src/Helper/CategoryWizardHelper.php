<?php

/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace J2Commerce\Component\J2commerce\Administrator\Helper;

defined('_JEXEC') or die;

use Joomla\CMS\Application\ApplicationHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Table\Table;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;

/**
 * Business logic for the Product Category Wizard.
 * Creates categories, articles, products, variants, options, and menu items.
 */
final class CategoryWizardHelper
{
    /**
     * Create a com_content category under a parent.
     *
     * @return int The new category ID (0 on failure)
     */
    public static function createCategory(string $title, int $parentId, DatabaseInterface $db): int
    {
        $alias = self::uniqueAlias(ApplicationHelper::stringURLSafe($title), 'categories', $db);
        $now   = date('Y-m-d H:i:s');

        $table = Table::getInstance('Category');
        $table->extension        = 'com_content';
        $table->title            = $title;
        $table->alias            = $alias;
        $table->published        = 1;
        $table->access           = 1;
        $table->language         = '*';
        $table->description      = '';
        $table->note             = '';
        $table->metadesc         = '';
        $table->metakey          = '';
        $table->metadata         = '';
        $table->params           = '{}';
        $table->created_time     = $now;
        $table->modified_time    = $now;
        $table->created_user_id  = 0;
        $table->modified_user_id = 0;
        $table->hits             = 0;
        $table->version          = 1;
        $table->setLocation($parentId, 'last-child');

        if ($table->check() && $table->store()) {
            return (int) $table->id;
        }

        return 0;
    }

    /**
     * Find an existing com_content "Shop" root category (alias = 'shop', parent_id = 1)
     * or create one if it doesn't exist.
     *
     * @return int The category ID (0 on failure)
     */
    public static function findOrCreateShopCategory(string $name, DatabaseInterface $db): int
    {
        $baseAlias = ApplicationHelper::stringURLSafe($name ?: 'Shop');
        $ext       = 'com_content';

        // Look for an existing category with this alias under the root
        $query = $db->getQuery(true)
            ->select($db->quoteName('id'))
            ->from($db->quoteName('#__categories'))
            ->where($db->quoteName('extension') . ' = :ext')
            ->where($db->quoteName('alias') . ' = :alias')
            ->where($db->quoteName('parent_id') . ' = 1')
            ->bind(':ext', $ext)
            ->bind(':alias', $baseAlias);

        $existing = (int) $db->setQuery($query)->loadResult();

        if ($existing > 0) {
            return $existing;
        }

        return self::createCategory($name ?: 'Shop', 1, $db);
    }

    /**
     * Create a Joomla article and workflow association.
     *
     * @return int The new article ID (0 on failure)
     */
    public static function createArticle(string $title, int $catId, DatabaseInterface $db): int
    {
        $now   = date('Y-m-d H:i:s');
        $alias = self::uniqueAlias(ApplicationHelper::stringURLSafe($title), 'content', $db);

        $article                   = new \stdClass();
        $article->title            = $title;
        $article->alias            = $alias;
        $article->introtext        = '';
        $article->fulltext         = '';
        $article->state            = 1;
        $article->catid            = $catId;
        $article->created          = $now;
        $article->created_by       = 0;
        $article->created_by_alias = '';
        $article->modified         = $now;
        $article->modified_by      = 0;
        $article->images           = '{}';
        $article->urls             = '{}';
        $article->attribs          = '{}';
        $article->version          = 1;
        $article->ordering         = 0;
        $article->metakey          = '';
        $article->metadesc         = '';
        $article->access           = 1;
        $article->hits             = 0;
        $article->metadata         = '{}';
        $article->featured         = 0;
        $article->language         = '*';
        $article->note             = '';
        $article->asset_id         = 0;
        $article->publish_up       = null;
        $article->publish_down     = null;
        $article->checked_out      = null;
        $article->checked_out_time = null;

        $db->insertObject('#__content', $article);
        $articleId = (int) $db->insertid();

        if ($articleId <= 0) {
            return 0;
        }

        // Workflow association
        $stageId = self::getDefaultWorkflowStageId($db);

        if ($stageId > 0) {
            try {
                $assoc            = new \stdClass();
                $assoc->item_id   = $articleId;
                $assoc->stage_id  = $stageId;
                $assoc->extension = 'com_content.article';
                $db->insertObject('#__workflow_associations', $assoc);
            } catch (\Exception) {
                // Ignore duplicates
            }
        }

        return $articleId;
    }

    /**
     * Create a J2Commerce product and master variant.
     *
     * @param string $productType 'simple', 'variable', or 'downloadable'
     * @return int The new product ID (0 on failure)
     */
    public static function createProduct(int $articleId, string $productType, DatabaseInterface $db): int
    {
        $now        = date('Y-m-d H:i:s');
        $hasOptions = in_array($productType, ['simple', 'variable'], true) ? 0 : 0;

        $product                    = new \stdClass();
        $product->visibility        = 1;
        $product->product_source    = 'com_content';
        $product->product_source_id = $articleId;
        $product->product_type      = $productType;
        $product->main_tag          = '';
        $product->taxprofile_id     = 0;
        $product->manufacturer_id   = 0;
        $product->vendor_id         = 0;
        $product->has_options       = $hasOptions;
        $product->addtocart_text    = '';
        $product->enabled           = 1;
        $product->plugins           = '';
        $product->params            = '{}';
        $product->created_on        = $now;
        $product->created_by        = 0;
        $product->modified_on       = $now;
        $product->modified_by       = 0;
        $product->up_sells          = '';
        $product->cross_sells       = '';
        $product->productfilter_ids = '';
        $product->hits              = 0;

        $db->insertObject('#__j2commerce_products', $product);
        $productId = (int) $db->insertid();

        if ($productId <= 0) {
            return 0;
        }

        // Create master variant
        $shipping = $productType === 'downloadable' ? 0 : 1;

        $variant                     = new \stdClass();
        $variant->product_id         = $productId;
        $variant->is_master          = 1;
        $variant->sku                = 'PROD-' . str_pad((string) $productId, 4, '0', STR_PAD_LEFT);
        $variant->upc                = '';
        $variant->price              = '0.00000';
        $variant->pricing_calculator = 'standard';
        $variant->shipping           = $shipping;
        $variant->params             = '{}';
        $variant->length             = '0.00000';
        $variant->width              = '0.00000';
        $variant->height             = '0.00000';
        $variant->length_class_id    = 0;
        $variant->weight             = '0.00000';
        $variant->weight_class_id    = 0;
        $variant->created_on         = $now;
        $variant->created_by         = 0;
        $variant->modified_on        = $now;
        $variant->modified_by        = 0;
        $variant->manage_stock       = 0;
        $variant->quantity_restriction = 0;
        $variant->min_out_qty        = null;
        $variant->use_store_config_min_out_qty = 1;
        $variant->min_sale_qty       = null;
        $variant->use_store_config_min_sale_qty = 1;
        $variant->max_sale_qty       = null;
        $variant->use_store_config_max_sale_qty = 1;
        $variant->notify_qty         = null;
        $variant->use_store_config_notify_qty = 1;
        $variant->availability       = 1;
        $variant->sold               = '0.0000';
        $variant->allow_backorder    = 0;
        $variant->isdefault_variant  = 1;

        $db->insertObject('#__j2commerce_variants', $variant);

        return $productId;
    }

    /**
     * Update the has_options flag on a product (after options are added).
     */
    public static function setHasOptions(int $productId, DatabaseInterface $db): void
    {
        $query = $db->getQuery(true)
            ->update($db->quoteName('#__j2commerce_products'))
            ->set($db->quoteName('has_options') . ' = 1')
            ->where($db->quoteName('j2commerce_product_id') . ' = :id')
            ->bind(':id', $productId, ParameterType::INTEGER);

        $db->setQuery($query)->execute();
    }

    /**
     * Find an existing Option by name or create a new one.
     *
     * @return int The option ID (0 on failure)
     */
    public static function findOrCreateOption(string $optionName, DatabaseInterface $db): int
    {
        // Try to find by exact option_name (case-insensitive)
        $query = $db->getQuery(true)
            ->select($db->quoteName('j2commerce_option_id'))
            ->from($db->quoteName('#__j2commerce_options'))
            ->where('LOWER(' . $db->quoteName('option_name') . ') = LOWER(:name)')
            ->bind(':name', $optionName)
            ->setLimit(1);

        $existing = (int) $db->setQuery($query)->loadResult();

        if ($existing > 0) {
            return $existing;
        }

        // Create new option
        $uniqueName = strtolower(preg_replace('/[^a-z0-9]+/i', '_', $optionName));
        $uniqueName = self::uniqueOptionUniqueName($uniqueName, $db);

        $count = (int) $db->setQuery(
            $db->getQuery(true)->select('COUNT(*)')->from($db->quoteName('#__j2commerce_options'))
        )->loadResult();

        $opt                    = new \stdClass();
        $opt->type              = 'select';
        $opt->option_unique_name = $uniqueName;
        $opt->option_name       = $optionName;
        $opt->ordering          = $count + 1;
        $opt->enabled           = 1;
        $opt->option_params     = '{}';

        $db->insertObject('#__j2commerce_options', $opt);
        $optionId = (int) $db->insertid();

        return $optionId > 0 ? $optionId : 0;
    }

    /**
     * Link an option to a product and create its product option values.
     * Also creates the global optionvalues if they don't exist.
     *
     * @param bool $isVariant Whether this option defines variants (for variable products)
     * @return array List of created product_optionvalue IDs
     */
    public static function createProductOptionValues(
        int $productId,
        int $optionId,
        array $values,
        DatabaseInterface $db,
        int $ordering = 1,
        bool $isVariant = false
    ): array {
        // Link option to product
        $po             = new \stdClass();
        $po->option_id  = $optionId;
        $po->parent_id  = 0;
        $po->product_id = $productId;
        $po->ordering   = $ordering;
        $po->required   = 1;
        $po->is_variant = $isVariant ? 1 : 0;

        $db->insertObject('#__j2commerce_product_options', $po);
        $productOptionId = (int) $db->insertid();

        if ($productOptionId <= 0) {
            return [];
        }

        $createdIds = [];

        foreach ($values as $i => $valueName) {
            $valueName = trim((string) $valueName);

            if ($valueName === '') {
                continue;
            }

            // Find or create the global optionvalue
            $ovId = self::findOrCreateOptionValue($optionId, $valueName, $i + 1, $db);

            $pov                                    = new \stdClass();
            $pov->productoption_id                  = $productOptionId;
            $pov->optionvalue_id                    = $ovId;
            $pov->parent_optionvalue                = '';
            $pov->product_optionvalue_price         = '0.00000000';
            $pov->product_optionvalue_prefix        = '+';
            $pov->product_optionvalue_weight        = '0.00000000';
            $pov->product_optionvalue_weight_prefix = '+';
            $pov->product_optionvalue_sku           = '';
            $pov->product_optionvalue_default       = $i === 0 ? 1 : 0;
            $pov->ordering                          = $i;
            $pov->product_optionvalue_attribs       = '{}';

            $db->insertObject('#__j2commerce_product_optionvalues', $pov);
            $povId = (int) $db->insertid();

            if ($povId > 0) {
                $createdIds[] = $povId;
            }
        }

        return $createdIds;
    }

    /**
     * Create a J2Commerce menu item with params JSON.
     *
     * @return int The new menu item ID (0 on failure)
     */
    public static function createMenuItem(
        string $title,
        string $link,
        array $params,
        DatabaseInterface $db
    ): int {
        // Find or create the j2commerce menu type
        $menutype = 'j2commerce';
        $query = $db->getQuery(true)
            ->select($db->quoteName('id'))
            ->from($db->quoteName('#__menu_types'))
            ->where($db->quoteName('menutype') . ' = :menutype')
            ->bind(':menutype', $menutype);

        $menuTypeExists = (int) $db->setQuery($query)->loadResult();

        if (!$menuTypeExists) {
            $menuType              = new \stdClass();
            $menuType->menutype    = 'j2commerce';
            $menuType->title       = 'J2Commerce';
            $menuType->description = '';
            $menuType->client_id   = 0;
            $db->insertObject('#__menu_types', $menuType);
        }

        // Look up com_j2commerce extension_id
        $element    = 'com_j2commerce';
        $typeStr    = 'component';
        $clientId   = 1;
        $query = $db->getQuery(true)
            ->select($db->quoteName('extension_id'))
            ->from($db->quoteName('#__extensions'))
            ->where($db->quoteName('element') . ' = :element')
            ->where($db->quoteName('type') . ' = :type')
            ->where($db->quoteName('client_id') . ' = :clientId')
            ->bind(':element', $element)
            ->bind(':type', $typeStr)
            ->bind(':clientId', $clientId, ParameterType::INTEGER);

        $componentId = (int) $db->setQuery($query)->loadResult();

        $alias = ApplicationHelper::stringURLSafe($title);
        $alias = self::uniqueMenuAlias($alias, $db);

        $menuItem               = new \stdClass();
        $menuItem->menutype     = 'j2commerce';
        $menuItem->title        = $title;
        $menuItem->alias        = $alias;
        $menuItem->path         = $alias;
        $menuItem->link         = $link;
        $menuItem->type         = 'component';
        $menuItem->published    = 1;
        $menuItem->parent_id    = 1;
        $menuItem->level        = 1;
        $menuItem->component_id = $componentId;
        $menuItem->access       = 1;
        $menuItem->params       = json_encode($params);
        $menuItem->img          = ' ';
        $menuItem->lft          = 0;
        $menuItem->rgt          = 0;
        $menuItem->home         = 0;
        $menuItem->language     = '*';
        $menuItem->client_id    = 0;

        $db->insertObject('#__menu', $menuItem);
        $menuItemId = (int) $db->insertid();

        if ($menuItemId > 0) {
            try {
                $table = Table::getInstance('Menu');
                $table->rebuild();
            } catch (\Throwable) {
                // Non-fatal: menu item was still created
            }
        }

        return $menuItemId;
    }

    /**
     * Detect if YOOtheme is the active site template.
     */
    public static function isYooThemeActive(): bool
    {
        $db       = Factory::getContainer()->get(DatabaseInterface::class);
        $template = 'yootheme';
        $clientId = 0;
        $home     = 1;
        $query = $db->getQuery(true)
            ->select('COUNT(*)')
            ->from($db->quoteName('#__template_styles'))
            ->where($db->quoteName('template') . ' = :template')
            ->where($db->quoteName('client_id') . ' = :clientId')
            ->where($db->quoteName('home') . ' = :home')
            ->bind(':template', $template)
            ->bind(':clientId', $clientId, ParameterType::INTEGER)
            ->bind(':home', $home, ParameterType::INTEGER);

        return (int) $db->setQuery($query)->loadResult() > 0;
    }

    /**
     * Get available J2Commerce subtemplates.
     * Returns 'bootstrap5' at minimum; adds 'uikit' if app_uikit is enabled.
     */
    public static function getAvailableSubtemplates(): array
    {
        $subtemplates = ['bootstrap5'];

        // Check if the uikit app plugin is enabled
        $db      = Factory::getContainer()->get(DatabaseInterface::class);
        $type    = 'plugin';
        $folder  = 'j2commerce';
        $element = 'app_uikit';
        $query = $db->getQuery(true)
            ->select($db->quoteName('enabled'))
            ->from($db->quoteName('#__extensions'))
            ->where($db->quoteName('type') . ' = :type')
            ->where($db->quoteName('folder') . ' = :folder')
            ->where($db->quoteName('element') . ' = :element')
            ->bind(':type', $type)
            ->bind(':folder', $folder)
            ->bind(':element', $element);

        $enabled = $db->setQuery($query)->loadResult();

        if ($enabled !== null && (int) $enabled === 1) {
            $subtemplates[] = 'uikit';
        }

        return $subtemplates;
    }

    /**
     * Get the default workflow stage ID for article creation.
     */
    public static function getDefaultWorkflowStageId(DatabaseInterface $db): int
    {
        $query = $db->getQuery(true)
            ->select($db->quoteName('ws.id'))
            ->from($db->quoteName('#__workflow_stages', 'ws'))
            ->join('INNER', $db->quoteName('#__workflows', 'w') . ' ON ' . $db->quoteName('w.id') . ' = ' . $db->quoteName('ws.workflow_id'))
            ->where($db->quoteName('w.default') . ' = 1')
            ->where($db->quoteName('ws.default') . ' = 1')
            ->setLimit(1);

        return (int) $db->setQuery($query)->loadResult();
    }

    // =========================================================================
    // Private helpers
    // =========================================================================

    /**
     * Generate a unique alias for a table by appending -2, -3, etc. as needed.
     */
    private static function uniqueAlias(string $base, string $table, DatabaseInterface $db): string
    {
        $alias    = $base ?: 'item';
        $tableMap = [
            'categories' => ['#__categories', 'alias'],
            'content'    => ['#__content', 'alias'],
        ];

        if (!isset($tableMap[$table])) {
            return $alias;
        }

        [$tableName, $col] = $tableMap[$table];
        $suffix            = 2;
        $current           = $alias;

        while (true) {
            $check = $db->getQuery(true)
                ->select('COUNT(*)')
                ->from($db->quoteName($tableName))
                ->where($db->quoteName($col) . ' = :alias')
                ->bind(':alias', $current);

            if ((int) $db->setQuery($check)->loadResult() === 0) {
                return $current;
            }

            $current = $alias . '-' . $suffix;
            $suffix++;
        }
    }

    private static function uniqueMenuAlias(string $base, DatabaseInterface $db): string
    {
        $alias  = $base ?: 'shop';
        $suffix = 2;
        $current = $alias;

        while (true) {
            $check = $db->getQuery(true)
                ->select('COUNT(*)')
                ->from($db->quoteName('#__menu'))
                ->where($db->quoteName('alias') . ' = :alias')
                ->where($db->quoteName('parent_id') . ' = 1')
                ->where($db->quoteName('client_id') . ' = 0')
                ->bind(':alias', $current);

            if ((int) $db->setQuery($check)->loadResult() === 0) {
                return $current;
            }

            $current = $alias . '-' . $suffix;
            $suffix++;
        }
    }

    private static function uniqueOptionUniqueName(string $base, DatabaseInterface $db): string
    {
        $name   = $base ?: 'option';
        $suffix = 2;
        $current = $name;

        while (true) {
            $check = $db->getQuery(true)
                ->select('COUNT(*)')
                ->from($db->quoteName('#__j2commerce_options'))
                ->where($db->quoteName('option_unique_name') . ' = :name')
                ->bind(':name', $current);

            if ((int) $db->setQuery($check)->loadResult() === 0) {
                return $current;
            }

            $current = $name . '_' . $suffix;
            $suffix++;
        }
    }

    /**
     * Find or create a global option value row in #__j2commerce_optionvalues.
     */
    private static function findOrCreateOptionValue(int $optionId, string $valueName, int $ordering, DatabaseInterface $db): int
    {
        $query = $db->getQuery(true)
            ->select($db->quoteName('j2commerce_optionvalue_id'))
            ->from($db->quoteName('#__j2commerce_optionvalues'))
            ->where($db->quoteName('option_id') . ' = :optId')
            ->where('LOWER(' . $db->quoteName('optionvalue_name') . ') = LOWER(:name)')
            ->bind(':optId', $optionId, ParameterType::INTEGER)
            ->bind(':name', $valueName)
            ->setLimit(1);

        $existing = (int) $db->setQuery($query)->loadResult();

        if ($existing > 0) {
            return $existing;
        }

        $ov                      = new \stdClass();
        $ov->option_id           = $optionId;
        $ov->optionvalue_name    = $valueName;
        $ov->optionvalue_image   = '';
        $ov->ordering            = $ordering;

        $db->insertObject('#__j2commerce_optionvalues', $ov);

        return (int) $db->insertid();
    }
}
