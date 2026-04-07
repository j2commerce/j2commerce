<?php

/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace J2Commerce\Component\J2commerce\Administrator\Table;

\defined('_JEXEC') or die;

use Joomla\CMS\Table\Table;
use Joomla\CMS\Uri\Uri;
use Joomla\Database\DatabaseDriver;

/**
 * Product Image table class.
 *
 * Manages product images (main, thumbnail, and additional images).
 *
 * @since  6.0.0
 */
class ProductimageTable extends Table
{
    /**
     * An array of key names to be JSON encoded in the bind method.
     *
     * @var    array
     * @since  6.0.0
     */
    protected $_jsonEncode = [
        'additional_images',
        'additional_images_alt',
        'additional_thumb_images',
        'additional_thumb_images_alt',
        'additional_tiny_images',
        'additional_tiny_images_alt',
    ];

    /**
     * Constructor.
     *
     * @param   DatabaseDriver  $db  Database connector object
     *
     * @since   6.0.0
     */
    public function __construct(DatabaseDriver $db)
    {
        parent::__construct('#__j2commerce_productimages', 'j2commerce_productimage_id', $db);
    }

    /**
     * Overloaded check method to ensure data integrity.
     *
     * @return  boolean  True on success.
     *
     * @since   6.0.0
     */
    public function check(): bool
    {
        try {
            parent::check();
        } catch (\Exception $e) {
            $this->setError($e->getMessage());
            return false;
        }

        // Normalize image paths: strip any absolute URL prefix so only relative paths are stored.
        // Prevents corruption from repeated Uri::root() prepending on each save cycle.
        $imageFields = ['main_image', 'thumb_image', 'tiny_image'];
        foreach ($imageFields as $field) {
            if (!empty($this->$field)) {
                $this->$field = $this->stripBaseUrl($this->$field);
            }
        }

        // Set default empty strings for varchar fields
        if (empty($this->main_image_alt)) {
            $this->main_image_alt = '';
        }

        if (empty($this->thumb_image_alt)) {
            $this->thumb_image_alt = '';
        }

        if (empty($this->tiny_image_alt)) {
            $this->tiny_image_alt = '';
        }

        return true;
    }

    /**
     * Strip repeated site base URL prefixes from an image path, preserving the #joomlaImage:// suffix.
     */
    private function stripBaseUrl(string $value): string
    {
        if (empty($value)) {
            return $value;
        }

        $root = rtrim(Uri::root(), '/') . '/';

        // Separate the path from the #joomlaImage:// fragment (if present)
        $fragment = '';
        if (str_contains($value, '#')) {
            [$value, $fragment] = explode('#', $value, 2);
            $fragment           = '#' . $fragment;
        }

        // Remove all occurrences of the base URL prefix
        while (str_starts_with($value, $root)) {
            $value = substr($value, \strlen($root));
        }

        return $value . $fragment;
    }

    /**
     * Save a product image record.
     *
     * @param   array|object  $src        The data to save.
     * @param   string        $orderingFilter  Filter for the ordering column.
     * @param   array|string  $ignore     Properties to ignore.
     *
     * @return  boolean  True on success.
     *
     * @since   6.0.0
     */
    public function save($src, $orderingFilter = '', $ignore = ''): bool
    {
        // Convert object to array
        if (\is_object($src)) {
            $src = (array) $src;
        }

        // Check if we're updating an existing record
        if (!empty($src['j2commerce_productimage_id'])) {
            $this->load($src['j2commerce_productimage_id']);
        } elseif (!empty($src['product_id'])) {
            // Try to load by product_id (one image record per product)
            $this->load(['product_id' => $src['product_id']]);
        }

        // Repair double-encoded JSON fields.  Valid JSON strings are left
        // as-is so bind() skips them (it only encodes arrays).  Only
        // double-encoded values (json_decode returns a string instead of
        // an array) are unwrapped one level to get the real JSON string.
        foreach ($this->_jsonEncode as $field) {
            if (isset($src[$field]) && \is_string($src[$field])) {
                $decoded = json_decode($src[$field], true);
                if (\is_string($decoded)) {
                    // Double-encoded: the outer decode yielded a string.
                    // That inner string is the correct JSON — use it.
                    $src[$field] = $decoded;
                }
                // Otherwise it decoded to array/null — it was a valid
                // JSON string already. Leave it as the original string
                // so bind() passes it through unchanged.
            }
        }

        // Bind the data — $_jsonEncode handles json_encode() for array fields
        if (!$this->bind($src, $ignore)) {
            return false;
        }

        // Check the data
        if (!$this->check()) {
            return false;
        }

        // Store the record
        if (!$this->store()) {
            return false;
        }

        return true;
    }
}
