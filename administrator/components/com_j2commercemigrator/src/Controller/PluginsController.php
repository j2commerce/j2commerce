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

use J2Commerce\Component\J2commercemigrator\Administrator\Service\AdapterRegistry;
use J2Commerce\Component\J2commercemigrator\Administrator\Helper\MigrationLogger;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Session\Session;

/**
 * Adapter plugin grid: listAdapters, publish, unpublish.
 * Publish/unpublish delegate state changes to com_plugins via Joomla's native AJAX.
 */
class PluginsController extends BaseController
{
    protected $default_view = 'plugins';

    public function display($cachable = false, $urlparams = []): static
    {
        $this->enforceAcl();
        return parent::display($cachable, $urlparams);
    }

    /**
     * GET: lists all registered adapter plugins with their metadata.
     */
    public function listAdapters(): void
    {
        $this->enforceAcl();

        try {
            $registry = new AdapterRegistry();
            $adapters = [];

            foreach ($registry->getAll() as $adapter) {
                $info       = $adapter->getSourceInfo();
                $adapters[] = [
                    'key'         => $adapter->getKey(),
                    'title'       => $info->title,
                    'description' => $info->description,
                    'icon'        => $info->icon,
                    'author'      => $info->author,
                    'version'     => $info->version,
                ];
            }

            $this->sendJson(['success' => true, 'data' => ['adapters' => $adapters]]);
        } catch (\Throwable $e) {
            $this->handleError('PluginsController::listAdapters', $e);
        }
    }

    /**
     * POST: publishes the specified migrator adapter plugin.
     * Delegates to com_plugins state change so Joomla handles extension state natively.
     */
    public function publish(): void
    {
        $this->enforceAcl();
        $this->enforceToken();

        $this->delegateToPluginsComponent(1);
    }

    /**
     * POST: unpublishes the specified migrator adapter plugin.
     */
    public function unpublish(): void
    {
        $this->enforceAcl();
        $this->enforceToken();

        $this->delegateToPluginsComponent(0);
    }

    /**
     * Delegates a publish/unpublish state change to the com_plugins component.
     */
    private function delegateToPluginsComponent(int $state): void
    {
        try {
            $app   = Factory::getApplication();
            $input = $app->getInput();
            $extId = $input->getInt('extension_id', 0);

            if ($extId <= 0) {
                $this->sendJson(['success' => false, 'error' => Text::_('COM_J2COMMERCEMIGRATOR_ERR_GENERIC')]);
                return;
            }

            $user = $app->getIdentity();

            if (!$user->authorise('core.edit.state', 'com_plugins')) {
                $this->sendJson(['success' => false, 'error' => Text::_('JLIB_APPLICATION_ERROR_ACCESS_FORBIDDEN')]);
                return;
            }

            /** @var \Joomla\CMS\MVC\Factory\MVCFactoryInterface $factory */
            $factory = $app->bootComponent('com_plugins')->getMVCFactory();
            $model   = $factory->createModel('Plugin', 'Administrator', ['ignore_request' => true]);

            $model->publish([$extId], $state);

            $this->sendJson(['success' => true, 'data' => ['state' => $state]]);
        } catch (\Throwable $e) {
            $this->handleError('PluginsController::delegateToPluginsComponent', $e);
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

    private function handleError(string $context, \Throwable $e): void
    {
        (new MigrationLogger())->error($context . ': ' . $e->getMessage());

        if (\defined('JDEBUG') && JDEBUG) {
            $this->sendJson(['error' => $e->getMessage()]);
        } else {
            $this->sendJson(['error' => Text::_('COM_J2COMMERCEMIGRATOR_ERR_GENERIC')]);
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
