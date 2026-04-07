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

use Joomla\CMS\MVC\Controller\FormController;

/**
 * Metafields Controller
 *
 * @since  6.0.0
 */
class MetafieldsController extends FormController
{
    /**
     * The prefix to use with controller messages.
     *
     * @var    string
     * @since  6.0.0
     */
    protected $text_prefix = 'COM_J2COMMERCE_METAFIELDS';

    /**
     * The URL view list variable.
     *
     * @var    string
     * @since  6.0.0
     */
    protected $view_list = 'metafields';

    /**
     * Function that allows child controller access to model data after the data has been saved.
     *
     * @param   \Joomla\CMS\MVC\Model\BaseDatabaseModel  $model      The data model object.
     * @param   array                                     $validData  The validated data.
     *
     * @return  void
     *
     * @since  6.0.0
     */
    protected function postSaveHook(\Joomla\CMS\MVC\Model\BaseDatabaseModel $model, $validData = [])
    {
        $app  = $this->app;
        $task = $app->input->get('task');

        if ($task === 'save') {
            $app->setUserState('com_j2commerce.edit.coupon.id', null);
            $this->setMessage(\Joomla\CMS\Language\Text::_('COM_J2COMMERCE_COUPON_SAVE_SUCCESS'));
        }
    }
}
