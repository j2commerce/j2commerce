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

use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\AdminController;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;

class AppsController extends AdminController
{
    protected $text_prefix = 'COM_J2COMMERCE';

    public function getModel($name = 'App', $prefix = 'Administrator', $config = ['ignore_request' => true])
    {
        return parent::getModel($name, $prefix, $config);
    }

    public function checkin()
    {
        Session::checkToken() or jexit(Text::_('JINVALID_TOKEN'));

        $cid = (array) $this->input->get('cid', [], 'int');

        if (empty($cid)) {
            $this->setMessage(Text::_('COM_J2COMMERCE_NO_ITEM_SELECTED'), 'warning');
        } else {
            $model = $this->getModel('App', 'Administrator');
            $cid   = array_map('intval', $cid);

            try {
                $model->checkin($cid);
                $this->setMessage(Text::plural('COM_J2COMMERCE_N_ITEMS_CHECKED_IN', \count($cid)));
            } catch (\Exception $e) {
                $this->setMessage($e->getMessage(), 'error');
            }
        }

        $this->setRedirect(Route::_('index.php?option=com_j2commerce&view=apps', false));
    }

    public function refresh()
    {
        Session::checkToken() or jexit(Text::_('JINVALID_TOKEN'));

        try {
            $cache = \Joomla\CMS\Factory::getCache('com_plugins', '');
            $cache->clean();

            $this->setMessage(Text::_('COM_J2COMMERCE_APPS_CACHE_REFRESHED'));
        } catch (\Exception $e) {
            $this->setMessage(Text::_('COM_J2COMMERCE_APPS_CACHE_REFRESH_FAILED') . ': ' . $e->getMessage(), 'error');
        }

        $this->setRedirect(Route::_('index.php?option=com_j2commerce&view=apps', false));
    }
}
