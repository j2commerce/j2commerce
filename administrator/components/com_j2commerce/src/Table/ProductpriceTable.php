<?php

/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace J2Commerce\Component\J2commerce\Administrator\Table;

\defined('_JEXEC') or die;

use Joomla\CMS\Table\Table;
use Joomla\Database\DatabaseDriver;

/**
 * Product Price Table
 *
 * @since  6.0.0
 */
class ProductpriceTable extends Table
{
    /**
     * Constructor
     *
     * @param   DatabaseDriver  $db  Database connector object
     *
     * @since  6.0.0
     */
    public function __construct(DatabaseDriver $db)
    {
        $this->typeAlias = 'com_j2commerce.productprice';

        parent::__construct('#__j2commerce_product_prices', 'j2commerce_productprice_id', $db);

    }

    /**
     * Overloaded bind function to pre-process the params.
     *
     * @param   array  $array   Named array
     * @param   mixed  $ignore  Optional array or list of parameters to ignore
     *
     * @return  null|string  null is operation was satisfactory, otherwise returns an error
     *
     * @see     JTable:bind
     * @since   1.5
     */
    public function bind($array, $ignore = '')
    {
        // Support for user_group
        if (isset($array['user_group'])) {
            if (\is_array($array['user_group'])) {
                $array['user_group'] = implode(',', $array['user_group']);
            }
        } else {
            if (strpos($array['user_group'], ',') != false) {
                $array['user_group'] = explode(',', $array['user_group']);
            } elseif (\strlen($array['user_group']) == 0) {
                $array['user_group'] = '';
            }
        }

        // Support for brand_ids
        if (isset($array['brand_ids'])) {
            if (\is_array($array['brand_ids'])) {
                $array['brand_ids'] = implode(',', $array['brand_ids']);
            }
        } else {
            if (strpos($array['brand_ids'], ',') != false) {
                $array['brand_ids'] = explode(',', $array['brand_ids']);
            } elseif (\strlen($array['brand_ids']) == 0) {
                $array['brand_ids'] = '';
            }
        }

        // Support for product_category
        if (isset($array['product_category'])) {
            if (\is_array($array['product_category'])) {
                $array['product_category'] = implode(',', $array['product_category']);
            }
        } else {
            if (strpos($array['product_category'], ',') != false) {
                $array['product_category'] = explode(',', $array['product_category']);
            } elseif (\strlen($array['product_category']) == 0) {
                $array['product_category'] = '';
            }
        }

        return parent::bind($array, $ignore);
    }

    /**
     * Method to perform sanity checks on the Table instance properties to ensure
     * they are safe to store in the database.
     *
     * @return  boolean  True if the instance is sane and able to be stored in the database.
     *
     * @since  6.0.0
     */
    public function check()
    {
        try {
            parent::check();
        } catch (\Exception $e) {
            $this->setError($e->getMessage());
            return false;
        }


        return true;
    }

}
