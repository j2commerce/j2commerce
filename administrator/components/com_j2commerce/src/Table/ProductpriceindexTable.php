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

use Joomla\CMS\Language\Text;
use Joomla\CMS\Table\Table;
use Joomla\Database\DatabaseDriver;

/**
 * Product Price Index table class.
 *
 * Stores min/max price indexes for flexivariable products to enable
 * efficient price range filtering and display.
 *
 * @since  6.0.0
 */
class ProductpriceindexTable extends Table
{
    /**
     * Constructor.
     *
     * @param   DatabaseDriver  $db  Database connector object
     *
     * @since   6.0.0
     */
    public function __construct(DatabaseDriver $db)
    {
        // Note: product_id is the primary key for this table (not auto_increment)
        parent::__construct('#__j2commerce_productprice_index', 'product_id', $db);
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

        // product_id is required
        if (empty($this->product_id)) {
            $this->setError(Text::sprintf('COM_J2COMMERCE_ERR_FIELD_REQUIRED', 'Product ID'));
            return false;
        }

        // Set defaults for price values
        if (!isset($this->min_price)) {
            $this->min_price = 0;
        }

        if (!isset($this->max_price)) {
            $this->max_price = 0;
        }

        return true;
    }
}
