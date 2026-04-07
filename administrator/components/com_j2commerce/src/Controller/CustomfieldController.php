<?php

/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace J2Commerce\Component\J2commerce\Administrator\Controller;

\defined('_JEXEC') or die;

use Joomla\CMS\MVC\Controller\FormController;

/**
 * Customfield Controller
 *
 * Handles single custom field item CRUD operations.
 * Most functionality is inherited from Joomla's FormController.
 *
 * @since  6.0.0
 */
class CustomfieldController extends FormController
{
    /**
     * The prefix to use with controller messages.
     *
     * @var    string
     * @since  6.0.0
     */
    protected $text_prefix = 'COM_J2COMMERCE_CUSTOMFIELD';

    /**
     * The primary key name for the table.
     * Required for J2Commerce tables which use j2commerce_*_id format.
     *
     * CRITICAL: This MUST match the primary key defined in CustomfieldTable.
     *
     * @var    string
     * @since  6.0.0
     */
    protected $key = 'j2commerce_customfield_id';

    /**
     * Method to edit an existing record.
     *
     * CRITICAL: Override required because parent uses table's primary key name
     * as URL variable by default, but Joomla standard URLs use 'id'.
     *
     * @param   string  $key     The name of the primary key of the URL variable.
     * @param   string  $urlVar  The name of the URL variable for the id.
     *
     * @return  boolean  True if access level check passes, false otherwise.
     *
     * @since   6.0.0
     */
    public function edit($key = null, $urlVar = 'id')
    {
        return parent::edit($key, $urlVar);
    }

    /**
     * Method to save a record.
     *
     * CRITICAL: Override required because parent uses table's primary key name
     * as URL variable by default, but Joomla standard URLs use 'id'.
     *
     * @param   string  $key     The name of the primary key of the URL variable.
     * @param   string  $urlVar  The name of the URL variable for the id.
     *
     * @return  boolean  True if successful, false otherwise.
     *
     * @since   6.0.0
     */
    public function save($key = null, $urlVar = 'id')
    {
        return parent::save($key, $urlVar);
    }

    /**
     * Method to cancel an edit.
     *
     * CRITICAL: Override required because parent uses table's primary key name
     * as URL variable by default, but Joomla standard URLs use 'id'.
     *
     * @param   string  $key  The name of the primary key of the URL variable.
     *
     * @return  boolean  True if access level checks pass, false otherwise.
     *
     * @since   6.0.0
     */
    public function cancel($key = 'id')
    {
        return parent::cancel($key);
    }
}
