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
 * Zone Controller
 *
 * Handles single zone item CRUD operations.
 * Most functionality is inherited from Joomla's FormController.
 *
 * @since  6.0.3
 */
class ZoneController extends FormController
{
    /**
     * The prefix to use with controller messages.
     *
     * @var    string
     * @since  6.0.3
     */
    protected $text_prefix = 'COM_J2COMMERCE_ZONE';

    /**
     * The primary key name for the table.
     * Required for J2Commerce tables which use j2commerce_*_id format.
     *
     * @var    string
     * @since  6.0.3
     */
    protected $key = 'j2commerce_zone_id';

    public function edit($key = null, $urlVar = 'id')
    {
        return parent::edit($key, $urlVar);
    }

    public function save($key = null, $urlVar = 'id')
    {
        return parent::save($key, $urlVar);
    }

    public function cancel($key = 'id')
    {
        return parent::cancel($key);
    }
}
