<?php

/**
 * @package     Joomla.Plugin
 * @subpackage  Schemaorg.ecommerce
 *
 * @copyright   (C) 2024-2026 J2Commerce, LLC All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Joomla\Plugin\Schemaorg\Ecommerce\Event;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Event triggered when preparing BreadcrumbList schema data.
 *
 * This event allows plugins to modify the breadcrumb navigation schema,
 * add custom breadcrumb items, or modify the category hierarchy path.
 *
 * Event name: onJ2CommerceSchemaBreadcrumbPrepare
 *
 * Example usage in a plugin:
 * ```php
 * public function onBreadcrumbPrepare(BreadcrumbSchemaPrepareEvent $event): void
 * {
 *     // Add a custom breadcrumb item
 *     $event->addBreadcrumbItem('Custom Category', 'https://example.com/category', 2);
 *
 *     // Or modify existing items
 *     $items = $event->getBreadcrumbItems();
 *     $items[0]['name'] = 'Home Store';
 *     $event->setBreadcrumbItems($items);
 * }
 * ```
 *
 * @since  6.0.0
 */
class BreadcrumbSchemaPrepareEvent extends AbstractSchemaEvent
{
    /**
     * Setter for the subject argument (breadcrumb schema data).
     *
     * @param   array  $value  The value to set
     *
     * @return  array
     *
     * @since   6.0.0
     */
    protected function onSetSubject(array $value): array
    {
        return $value;
    }

    /**
     * Get the product ID if applicable.
     *
     * @return  int|null  The product ID or null
     *
     * @since   6.0.0
     */
    public function getProductId(): ?int
    {
        return $this->arguments['productId'] ?? null;
    }

    /**
     * Get the category ID if applicable.
     *
     * @return  int|null  The category ID or null
     *
     * @since   6.0.0
     */
    public function getCategoryId(): ?int
    {
        return $this->arguments['categoryId'] ?? null;
    }

    /**
     * Get the breadcrumb items (itemListElement).
     *
     * @return  array  Array of ListItem schema objects
     *
     * @since   6.0.0
     */
    public function getBreadcrumbItems(): array
    {
        return $this->getSchemaProperty('itemListElement', []);
    }

    /**
     * Set the breadcrumb items.
     *
     * @param   array  $items  Array of ListItem schema objects
     *
     * @return  void
     *
     * @since   6.0.0
     */
    public function setBreadcrumbItems(array $items): void
    {
        $this->setSchemaProperty('itemListElement', $items);
    }

    /**
     * Add a breadcrumb item.
     *
     * @param   string  $name      The item name
     * @param   string  $url       The item URL
     * @param   int     $position  The position in the list (1-indexed)
     *
     * @return  void
     *
     * @since   6.0.0
     */
    public function addBreadcrumbItem(string $name, string $url, int $position): void
    {
        $items = $this->getBreadcrumbItems();

        $items[] = [
            '@type'    => 'ListItem',
            'position' => $position,
            'name'     => $name,
            'item'     => $url,
        ];

        // Sort by position
        usort($items, function ($a, $b) {
            return ($a['position'] ?? 0) <=> ($b['position'] ?? 0);
        });

        $this->setBreadcrumbItems($items);
    }

    /**
     * Insert a breadcrumb item at a specific position, shifting others.
     *
     * @param   string  $name      The item name
     * @param   string  $url       The item URL
     * @param   int     $position  The position to insert at
     *
     * @return  void
     *
     * @since   6.0.0
     */
    public function insertBreadcrumbItem(string $name, string $url, int $position): void
    {
        $items = $this->getBreadcrumbItems();

        // Shift positions of items at or after the insert position
        foreach ($items as &$item) {
            if (($item['position'] ?? 0) >= $position) {
                $item['position'] = ($item['position'] ?? 0) + 1;
            }
        }

        unset($item);

        // Add the new item
        $items[] = [
            '@type'    => 'ListItem',
            'position' => $position,
            'name'     => $name,
            'item'     => $url,
        ];

        // Sort by position
        usort($items, function ($a, $b) {
            return ($a['position'] ?? 0) <=> ($b['position'] ?? 0);
        });

        $this->setBreadcrumbItems($items);
    }

    /**
     * Remove a breadcrumb item by position.
     *
     * @param   int  $position  The position to remove
     *
     * @return  void
     *
     * @since   6.0.0
     */
    public function removeBreadcrumbItem(int $position): void
    {
        $items      = $this->getBreadcrumbItems();
        $newItems   = [];
        $adjustment = 0;

        foreach ($items as $item) {
            if (($item['position'] ?? 0) === $position) {
                $adjustment = 1;
                continue;
            }

            if ($adjustment > 0 && ($item['position'] ?? 0) > $position) {
                $item['position'] = ($item['position'] ?? 0) - 1;
            }

            $newItems[] = $item;
        }

        $this->setBreadcrumbItems($newItems);
    }

    /**
     * Get the number of breadcrumb items.
     *
     * @return  int  The count of items
     *
     * @since   6.0.0
     */
    public function getBreadcrumbCount(): int
    {
        return \count($this->getBreadcrumbItems());
    }
}
