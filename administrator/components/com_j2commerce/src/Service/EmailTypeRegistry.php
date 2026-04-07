<?php

/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace J2Commerce\Component\J2commerce\Administrator\Service;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\Database\DatabaseInterface;

/**
 * Registry for email types and their tags.
 *
 * Email types are registered via onJ2CommerceRegisterEmailTypes event.
 * Tags are stored in database and merged with plugin definitions.
 *
 * @since  6.1.0
 */
class EmailTypeRegistry
{
    /**
     * Registered email types.
     *
     * @var    array
     * @since  6.1.0
     */
    protected array $types = [];

    /**
     * Cached tag definitions from database.
     *
     * @var    array|null
     * @since  6.1.0
     */
    protected ?array $tagsCache = null;

    /**
     * Cached context definitions from database.
     *
     * @var    array|null
     * @since  6.1.0
     */
    protected ?array $contextsCache = null;

    /**
     * Database instance.
     *
     * @var    DatabaseInterface
     * @since  6.1.0
     */
    protected DatabaseInterface $db;

    /**
     * Flag to track if types have been loaded from plugins.
     *
     * @var    bool
     * @since  6.1.0
     */
    protected bool $pluginsLoaded = false;

    /**
     * Constructor.
     *
     * @param   DatabaseInterface  $db  The database instance.
     *
     * @since   6.1.0
     */
    public function __construct(DatabaseInterface $db)
    {
        $this->db = $db;

        // Register core email types
        $this->registerCoreTypes();
    }

    /**
     * Register core J2Commerce email types.
     *
     * @return  void
     *
     * @since   6.1.0
     */
    protected function registerCoreTypes(): void
    {
        $this->types['transactional'] = [
            'type'        => 'transactional',
            'label'       => 'COM_J2COMMERCE_EMAILTYPE_TRANSACTIONAL',
            'description' => 'COM_J2COMMERCE_EMAILTYPE_TRANSACTIONAL_DESC',
            'icon'        => 'fa-solid fa-envelope',
            'contexts'    => [
                'order_confirmed'  => 'COM_J2COMMERCE_EMAILTYPE_CONTEXT_ORDER_CONFIRMED',
                'order_cancelled'  => 'COM_J2COMMERCE_EMAILTYPE_CONTEXT_ORDER_CANCELLED',
                'order_shipped'    => 'COM_J2COMMERCE_EMAILTYPE_CONTEXT_ORDER_SHIPPED',
                'order_refunded'   => 'COM_J2COMMERCE_EMAILTYPE_CONTEXT_ORDER_REFUNDED',
                'payment_received' => 'COM_J2COMMERCE_EMAILTYPE_CONTEXT_PAYMENT_RECEIVED',
            ],
            'tags'            => $this->getCoreTags(),
            'default_subject' => 'COM_J2COMMERCE_EMAILTEMPLATE_SUBJECT_DEFAULT',
            'default_body'    => 'COM_J2COMMERCE_EMAILTEMPLATE_BODY_DEFAULT',
            'receiver_types'  => ['customer', 'admin', '*'],
        ];
    }

    /**
     * Get core transactional email tags.
     *
     * @return  array
     *
     * @since   6.1.0
     */
    protected function getCoreTags(): array
    {
        return [
            'ORDER_ID' => [
                'label'       => 'COM_J2COMMERCE_EMAILTEMPLATE_TAG_ORDERID',
                'description' => 'COM_J2COMMERCE_EMAILTEMPLATE_TAG_ORDERID_DESC',
                'group'       => 'order',
            ],
            'ORDER_DATE' => [
                'label'       => 'COM_J2COMMERCE_EMAILTEMPLATE_TAG_ORDERDATE',
                'description' => 'COM_J2COMMERCE_EMAILTEMPLATE_TAG_ORDERDATE_DESC',
                'group'       => 'order',
            ],
            'ORDER_STATUS' => [
                'label'       => 'COM_J2COMMERCE_EMAILTEMPLATE_TAG_ORDERSTATUS',
                'description' => 'COM_J2COMMERCE_EMAILTEMPLATE_TAG_ORDERSTATUS_DESC',
                'group'       => 'order',
            ],
            'ORDER_TOTAL' => [
                'label'       => 'COM_J2COMMERCE_EMAILTEMPLATE_TAG_ORDERAMOUNT',
                'description' => 'COM_J2COMMERCE_EMAILTEMPLATE_TAG_ORDERAMOUNT_DESC',
                'group'       => 'order',
            ],
            'ORDER_SUBTOTAL' => [
                'label'       => 'COM_J2COMMERCE_EMAILTEMPLATE_TAG_SUBTOTAL',
                'description' => 'COM_J2COMMERCE_EMAILTEMPLATE_TAG_SUBTOTAL_DESC',
                'group'       => 'order',
            ],
            'ORDER_TAX' => [
                'label'       => 'COM_J2COMMERCE_EMAILTEMPLATE_TAG_TAX_AMOUNT',
                'description' => 'COM_J2COMMERCE_EMAILTEMPLATE_TAG_TAX_AMOUNT_DESC',
                'group'       => 'order',
            ],
            'ORDER_SHIPPING' => [
                'label'       => 'COM_J2COMMERCE_EMAILTEMPLATE_TAG_SHIPPING_AMOUNT',
                'description' => 'COM_J2COMMERCE_EMAILTEMPLATE_TAG_SHIPPING_AMOUNT_DESC',
                'group'       => 'order',
            ],
            'ORDER_DISCOUNT' => [
                'label'       => 'COM_J2COMMERCE_EMAILTEMPLATE_TAG_DISCOUNT_AMOUNT',
                'description' => 'COM_J2COMMERCE_EMAILTEMPLATE_TAG_DISCOUNT_AMOUNT_DESC',
                'group'       => 'order',
            ],
            'ORDER_ITEMS' => [
                'label'       => 'COM_J2COMMERCE_EMAILTEMPLATE_TAG_ITEMS',
                'description' => 'COM_J2COMMERCE_EMAILTEMPLATE_TAG_ITEMS_DESC',
                'group'       => 'order',
            ],
            'CUSTOMER_NAME' => [
                'label'       => 'COM_J2COMMERCE_EMAILTEMPLATE_SHORTCODE_CUSTOMER_NAME',
                'description' => 'COM_J2COMMERCE_EMAILTEMPLATE_SHORTCODE_CUSTOMER_NAME_DESC',
                'group'       => 'customer',
            ],
            'CUSTOMER_EMAIL' => [
                'label'       => 'COM_J2COMMERCE_EMAILTEMPLATE_SHORTCODE_CUSTOMER_EMAIL',
                'description' => 'COM_J2COMMERCE_EMAILTEMPLATE_SHORTCODE_CUSTOMER_EMAIL_DESC',
                'group'       => 'customer',
            ],
            'BILLING_ADDRESS' => [
                'label'       => 'COM_J2COMMERCE_EMAILTEMPLATE_TAG_BILLING_ADDRESS',
                'description' => 'COM_J2COMMERCE_EMAILTEMPLATE_TAG_BILLING_ADDRESS_DESC',
                'group'       => 'customer',
            ],
            'SHIPPING_ADDRESS' => [
                'label'       => 'COM_J2COMMERCE_EMAILTEMPLATE_TAG_SHIPPING_ADDRESS',
                'description' => 'COM_J2COMMERCE_EMAILTEMPLATE_TAG_SHIPPING_ADDRESS_DESC',
                'group'       => 'customer',
            ],
            'PAYMENT_METHOD' => [
                'label'       => 'COM_J2COMMERCE_EMAILTEMPLATE_SHORTCODE_PAYMENT_METHOD',
                'description' => 'COM_J2COMMERCE_EMAILTEMPLATE_SHORTCODE_PAYMENT_METHOD_DESC',
                'group'       => 'payment',
            ],
            'SHIPPING_METHOD' => [
                'label'       => 'COM_J2COMMERCE_EMAILTEMPLATE_SHORTCODE_SHIPPING_METHOD',
                'description' => 'COM_J2COMMERCE_EMAILTEMPLATE_SHORTCODE_SHIPPING_METHOD_DESC',
                'group'       => 'shipping',
            ],
            'SITE_NAME' => [
                'label'       => 'COM_J2COMMERCE_EMAILTEMPLATE_SHORTCODE_SITE_NAME',
                'description' => 'COM_J2COMMERCE_EMAILTEMPLATE_SHORTCODE_SITE_NAME_DESC',
                'group'       => 'store',
            ],
            'SITE_URL' => [
                'label'       => 'COM_J2COMMERCE_EMAILTEMPLATE_SHORTCODE_STORE_URL',
                'description' => 'COM_J2COMMERCE_EMAILTEMPLATE_SHORTCODE_STORE_URL_DESC',
                'group'       => 'store',
            ],
        ];
    }

    /**
     * Register an email type.
     *
     * @param   string  $type    Unique identifier
     * @param   array   $config  Type configuration
     *
     * @return  void
     *
     * @since   6.1.0
     */
    public function registerType(string $type, array $config): void
    {
        // Merge with existing config if type already registered
        if (isset($this->types[$type])) {
            $this->types[$type] = array_merge($this->types[$type], $config);
        } else {
            $this->types[$type] = $config;
        }
    }

    /**
     * Get all registered email types.
     *
     * @param   bool  $loadPlugins  Whether to load plugin types via event
     *
     * @return  array
     *
     * @since   6.1.0
     */
    public function getTypes(bool $loadPlugins = true): array
    {
        if ($loadPlugins && !$this->pluginsLoaded) {
            $this->loadPluginTypes();
        }

        return $this->types;
    }

    /**
     * Get type configuration.
     *
     * @param   string  $type  Type identifier
     *
     * @return  array|null
     *
     * @since   6.1.0
     */
    public function getType(string $type): ?array
    {
        if (!isset($this->types[$type])) {
            $this->loadPluginTypes();
        }

        return $this->types[$type] ?? null;
    }

    /**
     * Get available tags for an email type.
     *
     * @param   string  $type  Type identifier
     *
     * @return  array  Tag definitions
     *
     * @since   6.1.0
     */
    public function getTagsForType(string $type): array
    {
        $typeConfig = $this->getType($type);

        if (!$typeConfig) {
            return [];
        }

        $tags = $typeConfig['tags'] ?? [];

        // Merge with database-stored tags
        $dbTags = $this->getTagsFromDatabase($type);

        foreach ($dbTags as $tagName => $tagConfig) {
            if (!isset($tags[$tagName])) {
                $tags[$tagName] = $tagConfig;
            }
        }

        return $tags;
    }

    /**
     * Get contexts for an email type.
     *
     * @param   string  $type  Type identifier
     *
     * @return  array  Context definitions
     *
     * @since   6.1.0
     */
    public function getContextsForType(string $type): array
    {
        $typeConfig = $this->getType($type);

        if (!$typeConfig) {
            return [];
        }

        $contexts = $typeConfig['contexts'] ?? [];

        // Merge with database-stored contexts
        $dbContexts = $this->getContextsFromDatabase($type);

        foreach ($dbContexts as $contextName => $contextConfig) {
            if (!isset($contexts[$contextName])) {
                $contexts[$contextName] = $contextConfig;
            }
        }

        return $contexts;
    }

    /**
     * Check if type is registered.
     *
     * @param   string  $type  Type identifier
     *
     * @return  bool
     *
     * @since   6.1.0
     */
    public function hasType(string $type): bool
    {
        if (!isset($this->types[$type])) {
            $this->loadPluginTypes();
        }

        return isset($this->types[$type]);
    }

    /**
     * Get all valid email type identifiers.
     *
     * @return  array
     *
     * @since   6.1.0
     */
    public function getTypeIdentifiers(): array
    {
        return array_keys($this->getTypes());
    }

    /**
     * Load email types from plugins via event.
     *
     * @return  void
     *
     * @since   6.1.0
     */
    protected function loadPluginTypes(): void
    {
        if ($this->pluginsLoaded) {
            return;
        }

        try {
            PluginHelper::importPlugin('j2commerce');
            $app = Factory::getApplication();
            $app->getDispatcher()->dispatch(
                'onJ2CommerceRegisterEmailTypes',
                new \Joomla\Event\Event('onJ2CommerceRegisterEmailTypes', ['registry' => $this])
            );
        } catch (\Exception $e) {
            // Application may not be available during CLI operations
        }

        $this->pluginsLoaded = true;
    }

    /**
     * Get tags from database for a specific email type.
     *
     * @param   string  $emailType  The email type
     *
     * @return  array
     *
     * @since   6.1.0
     */
    protected function getTagsFromDatabase(string $emailType): array
    {
        if ($this->tagsCache === null) {
            $this->loadTagsFromDatabase();
        }

        return $this->tagsCache[$emailType] ?? [];
    }

    /**
     * Load all tags from database.
     *
     * @return  void
     *
     * @since   6.1.0
     */
    protected function loadTagsFromDatabase(): void
    {
        $this->tagsCache = [];

        $query = $this->db->getQuery(true)
            ->select('*')
            ->from($this->db->quoteName('#__j2commerce_emailtype_tags'))
            ->order($this->db->quoteName('ordering') . ' ASC');

        $this->db->setQuery($query);

        try {
            $rows = $this->db->loadObjectList();

            foreach ($rows as $row) {
                $emailType = $row->email_type;

                if (!isset($this->tagsCache[$emailType])) {
                    $this->tagsCache[$emailType] = [];
                }

                $this->tagsCache[$emailType][$row->tag_name] = [
                    'label'       => $row->tag_label,
                    'description' => $row->tag_description ?? '',
                    'group'       => $row->tag_group ?? 'general',
                ];
            }
        } catch (\Exception $e) {
            // Table may not exist yet during initial installation
        }
    }

    /**
     * Get contexts from database for a specific email type.
     *
     * @param   string  $emailType  The email type
     *
     * @return  array
     *
     * @since   6.1.0
     */
    protected function getContextsFromDatabase(string $emailType): array
    {
        if ($this->contextsCache === null) {
            $this->loadContextsFromDatabase();
        }

        return $this->contextsCache[$emailType] ?? [];
    }

    /**
     * Load all contexts from database.
     *
     * @return  void
     *
     * @since   6.1.0
     */
    protected function loadContextsFromDatabase(): void
    {
        $this->contextsCache = [];

        $query = $this->db->getQuery(true)
            ->select('*')
            ->from($this->db->quoteName('#__j2commerce_emailtype_contexts'))
            ->order($this->db->quoteName('ordering') . ' ASC');

        $this->db->setQuery($query);

        try {
            $rows = $this->db->loadObjectList();

            foreach ($rows as $row) {
                $emailType = $row->email_type;

                if (!isset($this->contextsCache[$emailType])) {
                    $this->contextsCache[$emailType] = [];
                }

                $this->contextsCache[$emailType][$row->context] = [
                    'label'       => $row->label,
                    'description' => $row->description ?? '',
                ];
            }
        } catch (\Exception $e) {
            // Table may not exist yet during initial installation
        }
    }

    /**
     * Store tags to database for an email type.
     *
     * @param   string  $emailType  The email type
     * @param   array   $tags       Tag definitions
     *
     * @return  void
     *
     * @since   6.1.0
     */
    public function storeTagsForType(string $emailType, array $tags): void
    {
        // Clear cache
        $this->tagsCache = null;

        $ordering = 0;

        foreach ($tags as $tagName => $tagConfig) {
            $ordering++;

            $query = $this->db->getQuery(true)
                ->insert($this->db->quoteName('#__j2commerce_emailtype_tags'))
                ->columns([
                    $this->db->quoteName('email_type'),
                    $this->db->quoteName('tag_name'),
                    $this->db->quoteName('tag_label'),
                    $this->db->quoteName('tag_description'),
                    $this->db->quoteName('tag_group'),
                    $this->db->quoteName('ordering'),
                ])
                ->values(
                    $this->db->quote($emailType) . ', ' .
                    $this->db->quote($tagName) . ', ' .
                    $this->db->quote($tagConfig['label'] ?? '') . ', ' .
                    $this->db->quote($tagConfig['description'] ?? '') . ', ' .
                    $this->db->quote($tagConfig['group'] ?? 'general') . ', ' .
                    (int) $ordering
                );

            $this->db->setQuery($query);

            try {
                $this->db->execute();
            } catch (\Exception $e) {
                // Ignore duplicate key errors
            }
        }
    }

    /**
     * Store contexts to database for an email type.
     *
     * @param   string  $emailType  The email type
     * @param   array   $contexts   Context definitions
     *
     * @return  void
     *
     * @since   6.1.0
     */
    public function storeContextsForType(string $emailType, array $contexts): void
    {
        // Clear cache
        $this->contextsCache = null;

        $ordering = 0;

        foreach ($contexts as $contextName => $contextConfig) {
            $ordering++;

            $query = $this->db->getQuery(true)
                ->insert($this->db->quoteName('#__j2commerce_emailtype_contexts'))
                ->columns([
                    $this->db->quoteName('email_type'),
                    $this->db->quoteName('context'),
                    $this->db->quoteName('label'),
                    $this->db->quoteName('description'),
                    $this->db->quoteName('ordering'),
                ])
                ->values(
                    $this->db->quote($emailType) . ', ' .
                    $this->db->quote($contextName) . ', ' .
                    $this->db->quote($contextConfig['label'] ?? '') . ', ' .
                    $this->db->quote($contextConfig['description'] ?? '') . ', ' .
                    (int) $ordering
                );

            $this->db->setQuery($query);

            try {
                $this->db->execute();
            } catch (\Exception $e) {
                // Ignore duplicate key errors
            }
        }
    }

    /**
     * Delete all tags for an email type.
     *
     * @param   string  $emailType  The email type
     *
     * @return  void
     *
     * @since   6.1.0
     */
    public function deleteTagsForType(string $emailType): void
    {
        $query = $this->db->getQuery(true)
            ->delete($this->db->quoteName('#__j2commerce_emailtype_tags'))
            ->where($this->db->quoteName('email_type') . ' = :emailType')
            ->bind(':emailType', $emailType);

        $this->db->setQuery($query);

        try {
            $this->db->execute();
        } catch (\Exception $e) {
            // Table may not exist
        }

        // Clear cache
        $this->tagsCache = null;
    }

    /**
     * Delete all contexts for an email type.
     *
     * @param   string  $emailType  The email type
     *
     * @return  void
     *
     * @since   6.1.0
     */
    public function deleteContextsForType(string $emailType): void
    {
        $query = $this->db->getQuery(true)
            ->delete($this->db->quoteName('#__j2commerce_emailtype_contexts'))
            ->where($this->db->quoteName('email_type') . ' = :emailType')
            ->bind(':emailType', $emailType);

        $this->db->setQuery($query);

        try {
            $this->db->execute();
        } catch (\Exception $e) {
            // Table may not exist
        }

        // Clear cache
        $this->contextsCache = null;
    }

    /**
     * Get tags grouped by category for UI display.
     *
     * @param   string  $emailType  The email type
     *
     * @return  array  Tags grouped by 'group' key
     *
     * @since   6.1.0
     */
    public function getGroupedTagsForType(string $emailType): array
    {
        $tags    = $this->getTagsForType($emailType);
        $grouped = [];

        foreach ($tags as $tagName => $tagConfig) {
            $group = $tagConfig['group'] ?? 'general';

            if (!isset($grouped[$group])) {
                $grouped[$group] = [];
            }

            $grouped[$group][$tagName] = $tagConfig;
        }

        return $grouped;
    }
}
