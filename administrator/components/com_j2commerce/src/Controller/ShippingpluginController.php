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
use Joomla\CMS\Helper\ContentHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;
use Joomla\Event\Event;

/**
 * Generic Shipping Plugin dispatcher controller.
 *
 * Routes admin requests to shipping plugins via events, allowing each plugin
 * to handle its own views, tasks, and AJAX operations independently.
 *
 * @since  6.0.0
 */
class ShippingpluginController extends BaseController
{
    /**
     * Display the shipping plugin view.
     *
     * @param   bool   $cachable   If true, the view output will be cached.
     * @param   array  $urlparams  An array of safe URL parameters.
     *
     * @return  static  This object to support chaining.
     *
     * @since   6.0.0
     */
    public function display($cachable = false, $urlparams = []): static
    {
        $this->input->set('view', 'shippingplugin');

        return parent::display($cachable, $urlparams);
    }

    /**
     * Save a shipping method via the plugin.
     *
     * @return  void
     *
     * @since   6.0.0
     */
    public function save(): void
    {
        $this->checkToken();
        $this->checkPermission('core.edit');

        $plugin = $this->input->getCmd('plugin', '');
        $data   = $this->input->post->get('jform', [], 'array');

        $result = $this->fireTaskEvent('save', $plugin, [
            'data' => $data,
        ]);

        $this->handleTaskResult($result, $plugin, 'methods');
    }

    /**
     * Save and continue editing (apply) via the plugin.
     *
     * @return  void
     *
     * @since   6.0.0
     */
    public function apply(): void
    {
        $this->checkToken();
        $this->checkPermission('core.edit');

        $plugin = $this->input->getCmd('plugin', '');
        $data   = $this->input->post->get('jform', [], 'array');

        $result = $this->fireTaskEvent('apply', $plugin, [
            'data' => $data,
        ]);

        $this->handleTaskResult($result, $plugin, 'method');
    }

    /**
     * Cancel editing and return to the list view.
     *
     * @return  void
     *
     * @since   6.0.0
     */
    public function cancel(): void
    {
        $this->checkToken();

        $plugin  = $this->input->getCmd('plugin', '');
        $listUrl = Route::_(
            'index.php?option=com_j2commerce&view=shippingplugin&plugin=' . $plugin . '&pluginview=methods',
            false
        );

        $this->setRedirect($listUrl);
    }

    /**
     * Publish shipping methods via the plugin.
     *
     * @return  void
     *
     * @since   6.0.0
     */
    public function publish(): void
    {
        $this->checkToken();
        $this->checkPermission('core.edit.state');

        $plugin = $this->input->getCmd('plugin', '');
        $cid    = array_map('intval', (array) $this->input->get('cid', [], 'array'));
        $cid    = array_filter($cid);

        $result = $this->fireTaskEvent('publish', $plugin, [
            'ids'   => $cid,
            'value' => 1,
        ]);

        $this->handleTaskResult($result, $plugin, 'methods');
    }

    /**
     * Unpublish shipping methods via the plugin.
     *
     * @return  void
     *
     * @since   6.0.0
     */
    public function unpublish(): void
    {
        $this->checkToken();
        $this->checkPermission('core.edit.state');

        $plugin = $this->input->getCmd('plugin', '');
        $cid    = array_map('intval', (array) $this->input->get('cid', [], 'array'));
        $cid    = array_filter($cid);

        $result = $this->fireTaskEvent('unpublish', $plugin, [
            'ids'   => $cid,
            'value' => 0,
        ]);

        $this->handleTaskResult($result, $plugin, 'methods');
    }

    /**
     * Delete shipping methods via the plugin.
     *
     * @return  void
     *
     * @since   6.0.0
     */
    public function delete(): void
    {
        $this->checkToken();
        $this->checkPermission('core.delete');

        $plugin = $this->input->getCmd('plugin', '');
        $cid    = array_map('intval', (array) $this->input->get('cid', [], 'array'));
        $cid    = array_filter($cid);

        $result = $this->fireTaskEvent('delete', $plugin, [
            'ids' => $cid,
        ]);

        $this->handleTaskResult($result, $plugin, 'methods');
    }

    /**
     * Handle AJAX requests from shipping plugins.
     *
     * @return  void
     *
     * @since   6.0.0
     */
    public function ajax(): void
    {
        $plugin = $this->input->getCmd('plugin', '');
        $action = $this->input->getCmd('action', '');

        // Webhook callbacks are authenticated by HMAC, not session token.
        $isWebhook = $action === 'processWebhook'
            && isset($_SERVER['HTTP_X_EASYSHIP_SIGNATURE']);

        if (!$isWebhook) {
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
        }

        if (empty($plugin) || empty($action)) {
            $this->sendJsonError(Text::_('COM_J2COMMERCE_ERR_INVALID_REQUEST'));
            return;
        }

        PluginHelper::importPlugin('j2commerce');

        $event = new Event('onJ2CommerceShippingPluginAjax', [
            'plugin' => $plugin,
            'action' => $action,
            'input'  => $this->input,
        ]);

        Factory::getApplication()->getDispatcher()->dispatch('onJ2CommerceShippingPluginAjax', $event);

        $jsonResult = $event->getArgument('jsonResult', null);

        if ($jsonResult !== null) {
            $this->sendJsonResponse($jsonResult);
        } else {
            $this->sendJsonError($event->getArgument('jsonError', Text::_('COM_J2COMMERCE_ERR_NO_HANDLER')));
        }
    }

    /**
     * Check ACL permission and throw 403 if denied.
     *
     * @param   string  $action  The permission action (e.g., 'core.edit', 'core.delete').
     *
     * @return  void
     *
     * @throws  \RuntimeException  If the user doesn't have the required permission.
     *
     * @since   6.0.0
     */
    private function checkPermission(string $action): void
    {
        $canDo = ContentHelper::getActions('com_j2commerce');

        if (!$canDo->get($action)) {
            throw new \RuntimeException(Text::_('JLIB_APPLICATION_ERROR_ACCESS_FORBIDDEN'), 403);
        }
    }

    /**
     * Fire a task event and return the result.
     *
     * @param   string  $task    The task name (save, delete, publish, etc.).
     * @param   string  $plugin  The plugin element name.
     * @param   array   $extra   Additional event arguments.
     *
     * @return  array   The event result with 'redirect', 'message', 'type' keys.
     *
     * @since   6.0.0
     */
    private function fireTaskEvent(string $task, string $plugin, array $extra = []): array
    {
        PluginHelper::importPlugin('j2commerce');

        $eventArgs = array_merge([
            'task'   => $task,
            'plugin' => $plugin,
            'input'  => $this->input,
        ], $extra);

        $event = new Event('onJ2CommerceShippingPluginTask', $eventArgs);

        Factory::getApplication()->getDispatcher()->dispatch('onJ2CommerceShippingPluginTask', $event);

        return [
            'redirect' => $event->getArgument('redirect', ''),
            'message'  => $event->getArgument('message', ''),
            'type'     => $event->getArgument('messageType', 'success'),
            'id'       => $event->getArgument('id', 0),
        ];
    }

    /**
     * Handle the result of a task event by redirecting with a message.
     *
     * @param   array   $result       The task result array.
     * @param   string  $plugin       The plugin element name.
     * @param   string  $defaultView  Default pluginview to redirect to ('methods' or 'method').
     *
     * @return  void
     *
     * @since   6.0.0
     */
    private function handleTaskResult(array $result, string $plugin, string $defaultView): void
    {
        $redirect = $result['redirect'];

        if (empty($redirect)) {
            $redirect = 'index.php?option=com_j2commerce&view=shippingplugin&plugin=' . $plugin
                . '&pluginview=' . $defaultView;

            if ($defaultView === 'method' && !empty($result['id'])) {
                $redirect .= '&id=' . (int) $result['id'];
            }
        }

        if (!empty($result['message'])) {
            $this->setMessage($result['message'], $result['type']);
        }

        $this->setRedirect(Route::_($redirect, false));
    }

    /**
     * Send a JSON success response and close.
     *
     * @param   mixed  $data  The response data.
     *
     * @return  void
     *
     * @since   6.0.0
     */
    private function sendJsonResponse(mixed $data): void
    {
        $app = Factory::getApplication();
        $app->setHeader('Content-Type', 'application/json; charset=utf-8');
        $app->sendHeaders();

        echo json_encode(['success' => true, 'data' => $data]);
        $app->close();
    }

    /**
     * Send a JSON error response and close.
     *
     * @param   string  $message  The error message.
     *
     * @return  void
     *
     * @since   6.0.0
     */
    private function sendJsonError(string $message): void
    {
        $app = Factory::getApplication();
        $app->setHeader('Content-Type', 'application/json; charset=utf-8');
        $app->sendHeaders();

        echo json_encode(['success' => false, 'message' => $message]);
        $app->close();
    }
}
