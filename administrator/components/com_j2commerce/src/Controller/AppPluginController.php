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

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\Session\Session;
use Joomla\Event\Event;

/**
 * Generic App Plugin dispatcher controller.
 *
 * Routes admin requests to j2commerce app plugins via events, allowing each
 * plugin to handle its own views and AJAX operations independently.
 *
 * @since  6.0.0
 */
class AppPluginController extends BaseController
{
    public function display($cachable = false, $urlparams = []): static
    {
        $this->input->set('view', 'appplugin');

        return parent::display($cachable, $urlparams);
    }

    public function ajax(): void
    {
        $plugin = $this->input->getCmd('plugin', '');
        $action = $this->input->getCmd('action', '');

        $method = $this->input->getMethod();

        if ($method === 'GET') {
            if (!Session::checkToken('get')) {
                $this->sendJsonError(Text::_('JINVALID_TOKEN'));
                return;
            }
        } else {
            if (!Session::checkToken()) {
                $this->sendJsonError(Text::_('JINVALID_TOKEN'));
                return;
            }
        }

        if (empty($plugin) || empty($action)) {
            $this->sendJsonError(Text::_('COM_J2COMMERCE_ERR_INVALID_REQUEST'));
            return;
        }

        PluginHelper::importPlugin('j2commerce');

        $event = new Event('onJ2CommerceAppPluginAjax', [
            'plugin' => $plugin,
            'action' => $action,
            'input'  => $this->input,
        ]);

        Factory::getApplication()->getDispatcher()->dispatch('onJ2CommerceAppPluginAjax', $event);

        $jsonResult = $event->getArgument('jsonResult', null);

        if ($jsonResult !== null) {
            $this->sendJsonResponse($jsonResult);
        } else {
            $this->sendJsonError($event->getArgument('jsonError', Text::_('COM_J2COMMERCE_ERR_NO_HANDLER')));
        }
    }

    private function sendJsonResponse(mixed $data): void
    {
        $app = Factory::getApplication();
        $app->setHeader('Content-Type', 'application/json; charset=utf-8');
        $app->sendHeaders();

        echo json_encode(['success' => true, 'data' => $data]);
        $app->close();
    }

    private function sendJsonError(string $message): void
    {
        $app = Factory::getApplication();
        $app->setHeader('Content-Type', 'application/json; charset=utf-8');
        $app->sendHeaders();

        echo json_encode(['success' => false, 'message' => $message]);
        $app->close();
    }
}
