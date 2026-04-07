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

use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\AdminController;
use Joomla\CMS\Router\Route;
use Joomla\Database\DatabaseInterface;

class QueuelogsController extends AdminController
{
    protected $text_prefix = 'COM_J2COMMERCE';

    public function getModel($name = 'Queuelog', $prefix = 'Administrator', $config = ['ignore_request' => true])
    {
        return parent::getModel($name, $prefix, $config);
    }

    public function purgeOld(): bool
    {
        $this->checkToken();

        if (!$this->app->getIdentity()->authorise('core.delete', 'com_j2commerce')) {
            $this->app->enqueueMessage(Text::_('JLIB_APPLICATION_ERROR_ACCESS_FORBIDDEN'), 'error');
            $this->setRedirect(Route::_('index.php?option=com_j2commerce&view=queuelogs', false));
            return false;
        }

        $days = $this->input->getInt('older_than_days', 90);
        $days = max(1, $days);

        $db        = \Joomla\CMS\Factory::getContainer()->get(DatabaseInterface::class);
        $threshold = (new \DateTime())->modify("-{$days} days")->format('Y-m-d H:i:s');
        $query     = $db->getQuery(true)
            ->delete($db->quoteName('#__j2commerce_queue_logs'))
            ->where($db->quoteName('created_on') . ' < :threshold')
            ->bind(':threshold', $threshold);

        $db->setQuery($query)->execute();
        $count = $db->getAffectedRows();

        $this->app->enqueueMessage(Text::sprintf('COM_J2COMMERCE_QUEUE_LOGS_N_ITEMS_PURGED', $count));
        $this->setRedirect(Route::_('index.php?option=com_j2commerce&view=queuelogs', false));
        return true;
    }
}
