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

use Joomla\CMS\Language\Text;
use Joomla\CMS\Table\Table;
use Joomla\Database\DatabaseDriver;

/**
 * Product File Table
 *
 * @since  6.0.0
 */
class ProductfileTable extends Table
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
        $this->typeAlias = 'com_j2commerce.productfile';

        parent::__construct('#__j2commerce_productfiles', 'j2commerce_productfile_id', $db);
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

        // Check for valid display name
        if (trim($this->product_file_display_name) == '') {
            throw new \UnexpectedValueException(
                Text::_('COM_J2COMMERCE_WARNING_PROVIDE_VALID_DISPLAY_NAME')
            );

            return false;
        }

        // Check for valid save name
        if (trim($this->product_file_save_name) == '') {
            throw new \UnexpectedValueException(
                Text::_('COM_J2COMMERCE_WARNING_PROVIDE_VALID_SAVE_NAME')
            );

            return false;
        }

        //check variant id exists
        if (empty($this->product_id)) {
            $this->setError(Text::_('COM_J2COMMERCE_VARIANT_ID_MISSING'));
            $result = false;
        }

        // Check for valid product ID
        if (!isset($this->product_id) || (int) $this->product_id <= 0) {
            throw new \UnexpectedValueException(Text::_('COM_J2COMMERCE_WARNING_PROVIDE_VALID_PRODUCT_ID'));
            return false;
        }

        // Check for duplicate display name within the same product
        $db    = $this->getDbo();
        $query = $db->getQuery(true)
            ->select($db->quoteName('j2commerce_productfile_id'))
            ->from($db->quoteName('#__j2commerce_productfiles'))
            ->where($db->quoteName('product_file_display_name') . ' = ' . $db->quote($this->product_file_display_name))
            ->where($db->quoteName('product_id') . ' = ' . (int) $this->product_id);

        if ($this->j2commerce_productfile_id) {
            $query->where($db->quoteName('j2commerce_productfile_id') . ' != ' . (int) $this->j2commerce_productfile_id);
        }

        $db->setQuery($query);

        if ($db->loadResult()) {
            throw new \UnexpectedValueException(
                Text::_('COM_J2COMMERCE_ERROR_PRODUCTFILE_DISPLAY_NAME_EXISTS')
            );
            return false;
        }

        // Set default values
        if (!isset($this->download_total)) {
            $this->download_total = 0;
        }

        return true;
    }
}
