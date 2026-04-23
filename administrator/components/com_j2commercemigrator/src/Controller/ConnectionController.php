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

use J2Commerce\Component\J2commercemigrator\Administrator\Service\ConnectionManager;
use J2Commerce\Component\J2commercemigrator\Administrator\Helper\MigrationLogger;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Session\Session;

/**
 * Handles source-connection lifecycle: verifyConnection, clearConnection, getConnection.
 */
class ConnectionController extends BaseController
{
    protected $default_view = 'connection';

    /**
     * Displays the connection configuration view.
     */
    public function display($cachable = false, $urlparams = []): static
    {
        $this->enforceAcl();
        return parent::display($cachable, $urlparams);
    }

    /**
     * POST: verifies PDO connection and probes the source schema.
     * Responds with JSON.
     */
    public function verifyConnection(): void
    {
        $this->enforceAcl();
        $this->enforceToken();

        $app   = Factory::getApplication();
        $input = $app->getInput();

        if ($input->getMethod() !== 'POST') {
            $this->sendJson(['success' => false, 'error' => Text::_('COM_J2COMMERCEMIGRATOR_ERR_GENERIC'), 'category' => 'post_required']);
            return;
        }

        $creds = [
            'mode'     => $input->get('mode', 'A', 'cmd'),
            'host'     => $input->get('host', '', 'string'),
            'port'     => $input->getInt('port', 3306),
            'database' => $input->get('database', '', 'string'),
            'username' => $input->get('username', '', 'string'),
            'password' => $input->get('password', '', 'raw'),
            'prefix'   => $input->get('prefix', 'jos_', 'string'),
            'ssl'      => $input->getBool('ssl', false),
            'ssl_ca'   => $input->get('ssl_ca', '', 'string'),
        ];

        try {
            $connMgr = new ConnectionManager($app, $this->getDatabase());
            $this->sendJson($connMgr->verify($creds));
        } catch (\Throwable $e) {
            (new MigrationLogger())->error('ConnectionController::verifyConnection: ' . $e->getMessage());
            $this->sendJson(['success' => false, 'error' => Text::_('COM_J2COMMERCEMIGRATOR_ERR_GENERIC')]);
        }
    }

    /**
     * POST: drops session credentials.
     * Responds with JSON.
     */
    public function clearConnection(): void
    {
        $this->enforceAcl();
        $this->enforceToken();

        try {
            $app     = Factory::getApplication();
            $connMgr = new ConnectionManager($app, $this->getDatabase());
            $connMgr->clear();
            $this->sendJson(['success' => true, 'data' => []]);
        } catch (\Throwable $e) {
            (new MigrationLogger())->error('ConnectionController::clearConnection: ' . $e->getMessage());
            $this->sendJson(['success' => false, 'error' => Text::_('COM_J2COMMERCEMIGRATOR_ERR_GENERIC')]);
        }
    }

    /**
     * GET: returns session-stored connection metadata.
     * Responds with JSON.
     */
    public function getConnection(): void
    {
        $this->enforceAcl();

        try {
            $app     = Factory::getApplication();
            $connMgr = new ConnectionManager($app, $this->getDatabase());

            $this->sendJson([
                'success' => true,
                'data'    => [
                    'status'       => $connMgr->getStatus(),
                    'pdoAvailable' => extension_loaded('pdo_mysql'),
                ],
            ]);
        } catch (\Throwable $e) {
            (new MigrationLogger())->error('ConnectionController::getConnection: ' . $e->getMessage());
            $this->sendJson(['success' => false, 'error' => Text::_('COM_J2COMMERCEMIGRATOR_ERR_GENERIC')]);
        }
    }

    private function enforceAcl(): void
    {
        $user = Factory::getApplication()->getIdentity();

        if (!$user || !$user->authorise('core.manage', 'com_j2commercemigrator')) {
            $this->sendJson(['error' => Text::_('JLIB_APPLICATION_ERROR_ACCESS_FORBIDDEN')]);
        }
    }

    private function enforceToken(): void
    {
        if (!Session::checkToken('get') && !Session::checkToken('post')) {
            $this->sendJson(['error' => Text::_('JINVALID_TOKEN')]);
        }
    }

    private function sendJson(array $data): void
    {
        if (!array_key_exists('success', $data)) {
            if (array_key_exists('error', $data)) {
                $normalized = ['success' => false, 'error' => $data['error']];

                if (array_key_exists('category', $data)) {
                    $normalized['category'] = $data['category'];
                }

                $data = $normalized;
            } else {
                $data = ['success' => true, 'data' => $data];
            }
        }

        $app = Factory::getApplication();
        $app->setHeader('Content-Type', 'application/json; charset=utf-8');
        echo json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        $app->close();
    }
}
