<?php

/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace J2Commerce\Component\J2commerce\Administrator\Controller;

\defined('_JEXEC') or die;

use Joomla\CMS\MVC\Controller\AdminController;

/**
 * Orderhistories list controller class.
 *
 * @since  6.0.0
 */
class OrderhistoriesController extends AdminController
{
    /**
     * Constructor
     *
     * @param   array  $config  An optional associative array of configuration settings.
     *
     * @since  6.0.0
     */
    public function getModel($name = 'Orderhistories', $prefix = '', $config = ['ignore_request' => true])
    {
        $modelName = '\\J2Commerce\\Component\\J2commerce\\Administrator\\Model\\' . $name . 'Model';

        if (class_exists($modelName)) {
            $model = new $modelName($config);
            return $model;
        }

        return parent::getModel($name, $prefix, $config);
    }
}
