<?php

/**
 * @package     J2Commerce
 * @subpackage  com_j2commercemigrator
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace J2Commerce\Component\J2commercemigrator\Administrator\Controller;

defined('_JEXEC') or die;

use J2Commerce\Component\J2commercemigrator\Administrator\Service\IdmapRepository;
use J2Commerce\Component\J2commercemigrator\Administrator\Helper\MigrationLogger;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\AdminController;
use Joomla\CMS\Session\Session;

/**
 * CRUD list for migration run history + idmap cleanup.
 */
class RunsController extends AdminController
{
    protected $text_prefix = 'COM_J2COMMERCEMIGRATOR';

    protected $key = 'j2commerce_migrator_run_id';

    public function getModel($name = 'Run', $prefix = 'Administrator', $config = ['ignore_request' => true]): object|bool
    {
        return parent::getModel($name, $prefix, $config);
    }

    /**
     * POST: drops the #__j2commerce_migrator_idmap cross-reference table.
     * Responds with JSON.
     */
    public function cleanupIdMap(): void
    {
        $user = Factory::getApplication()->getIdentity();

        if (!$user || !$user->authorise('core.manage', 'com_j2commercemigrator')) {
            $this->sendJson(['error' => Text::_('JLIB_APPLICATION_ERROR_ACCESS_FORBIDDEN')]);
            return;
        }

        if (!Session::checkToken('get') && !Session::checkToken('post')) {
            $this->sendJson(['error' => Text::_('JINVALID_TOKEN')]);
            return;
        }

        try {
            $repo = new IdmapRepository($this->getDatabase());
            $repo->dropAll();
            $this->sendJson(['ok' => true]);
        } catch (\Throwable $e) {
            (new MigrationLogger())->error('RunsController::cleanupIdMap', $e->getMessage());
            $this->sendJson(['ok' => false, 'error' => Text::_('COM_J2COMMERCEMIGRATOR_ERR_GENERIC')]);
        }
    }

    private function sendJson(array $data): void
    {
        $app = Factory::getApplication();
        $app->setHeader('Content-Type', 'application/json; charset=utf-8');
        echo json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        $app->close();
    }
}
