<?php
/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace J2Commerce\Component\J2commerce\Administrator\Table;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Table\Table;
use Joomla\Database\DatabaseDriver;
use Joomla\Database\ParameterType;

/**
 * Filtergroup Table
 *
 * @since  6.0.0
 */
class FiltergroupTable extends Table
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
        $this->typeAlias = 'com_j2commerce.filtergroup';

        parent::__construct('#__j2commerce_filtergroups', 'j2commerce_filtergroup_id', $db);

        $this->setColumnAlias('published', 'enabled');
    }

    /**
     * Method to perform sanity checks on the Table instance properties to ensure
     * they are safe to store in the database.
     *
     * @return  boolean  True if the instance is sane and able to be stored in the database.
     *
     * @throws  \Exception
     * @since  6.0.0
     */
    public function check()
    {
        parent::check();

        // Check for a group name.
        if (trim($this->group_name) == '') {
            throw new \InvalidArgumentException(Text::_('COM_J2COMMERCE_FILTERGROUP_ERROR_NAME'));
        }

        // Verify that the group name is unique
        if ($this->group_name) {
            $db = Factory::getContainer()->get('DatabaseDriver');
            $query = $db->getQuery(true)
                ->select('COUNT(*)')
                ->from($db->quoteName('#__j2commerce_filtergroups'))
                ->where($db->quoteName('group_name') . ' = :name')
                ->bind(':name', $this->group_name);

            if ($this->j2commerce_filtergroup_id) {
                $query->where($db->quoteName('j2commerce_filtergroup_id') . ' != :id')
                    ->bind(':id', $this->j2commerce_filtergroup_id, ParameterType::INTEGER);
            }

            $db->setQuery($query);
            $duplicate = (int) $db->loadResult();

            if ($duplicate > 0) {
                throw new \InvalidArgumentException(Text::_('COM_J2COMMERCE_FILTERGROUP_ERROR_NAME_EXISTS'));
            }
        }

        return true;
    }

    /**
     * Method to store a row in the database from the Table instance properties.
     *
     * @param   boolean  $updateNulls  True to update fields even if they are null.
     *
     * @return  boolean  True on success.
     *
     * @since  6.0.0
     */
    public function store($updateNulls = true)
    {
        $date = Factory::getDate()->toSql();
        $user = Factory::getApplication()->getIdentity();

        if (!(int) $this->j2commerce_filtergroup_id) {
            if (empty($this->created_on) || $this->created_on === '0000-00-00 00:00:00') {
                $this->created_on = $date;
            }

            if (empty($this->created_by)) {
                $this->created_by = (int) $user->id;
            }

            // Set default enabled value for new records
            if (!isset($this->enabled)) {
                $this->enabled = 1;
            }

            // Set default ordering value for new records
            if (!isset($this->ordering)) {
                $this->ordering = $this->getNextOrder();
            }
        }

        $this->modified_on = $date;
        $this->modified_by = (int) $user->id;

        return parent::store($updateNulls);
    }

}
