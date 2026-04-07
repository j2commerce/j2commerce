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

use J2Commerce\Component\J2commerce\Administrator\SetupGuide\SetupGuideHelper;
use Joomla\CMS\Application\ApplicationHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Response\JsonResponse;
use Joomla\CMS\Session\Session;
use Joomla\CMS\Table\Table;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;
use Joomla\Registry\Registry;

class SetupguideController extends BaseController
{
    private function getDb(): DatabaseInterface
    {
        return Factory::getContainer()->get(DatabaseInterface::class);
    }

    private function jsonError(string $message, int $status = 400): void
    {
        $this->app->setHeader('Content-Type', 'application/json; charset=utf-8');
        $this->app->setHeader('status', (string) $status);
        echo new JsonResponse(null, $message, true);
        $this->app->close();
    }

    private function jsonSuccess(mixed $data, string $message = ''): void
    {
        $this->app->setHeader('Content-Type', 'application/json; charset=utf-8');
        echo new JsonResponse($data, $message);
        $this->app->close();
    }

    private function requireAdmin(): bool
    {
        if (!$this->app->getIdentity()->authorise('core.admin', 'com_j2commerce')) {
            $this->jsonError(Text::_('JLIB_APPLICATION_ERROR_ACCESS_FORBIDDEN'), 403);
            return false;
        }

        return true;
    }

    public function getStatus(): void
    {
        if (!$this->requireAdmin()) {
            return;
        }

        try {
            $data = SetupGuideHelper::getGroupedResults();
            $this->jsonSuccess($data);
        } catch (\Throwable $e) {
            $this->jsonError($e->getMessage(), 500);
        }
    }

    public function getDetail(): void
    {
        if (!$this->requireAdmin()) {
            return;
        }

        $checkId = $this->input->getString('checkId', '');

        if ($checkId === '') {
            $this->jsonError(Text::_('JGLOBAL_NO_ITEM_SELECTED'));
            return;
        }

        try {
            $check = SetupGuideHelper::findCheck($checkId);

            if ($check === null) {
                $this->jsonError(Text::_('JGLOBAL_NO_ITEM_SELECTED'), 404);
                return;
            }

            $this->jsonSuccess(['html' => $check->getDetailView()]);
        } catch (\Throwable $e) {
            $this->jsonError($e->getMessage(), 500);
        }
    }

    public function runAction(): void
    {
        if (!Session::checkToken()) {
            $this->jsonError(Text::_('JINVALID_TOKEN'), 403);
            return;
        }

        if (!$this->requireAdmin()) {
            return;
        }

        $checkId = $this->input->getString('checkId', '');
        $action  = $this->input->getString('action', '');
        $params  = json_decode($this->input->getRaw('params', '{}'), true) ?? [];

        try {
            match ($action) {
                'enable_plugin'     => $this->handleEnablePlugin($params['folder'] ?? '', $params['element'] ?? ''),
                'create_menu_item'  => $this->handleCreateMenuItem($params['link'] ?? '', $params['title'] ?? ''),
                'publish_menu_item' => $this->handlePublishMenuItem((int) ($params['menuItemId'] ?? 0)),
                'save_param'        => $this->handleSaveParam($params['param_name'] ?? '', $params['param_value'] ?? ''),
                default             => throw new \InvalidArgumentException('Unknown action: ' . $action),
            };

            $this->jsonSuccess(null, Text::_('JLIB_APPLICATION_SAVE_SUCCESS'));
        } catch (\Throwable $e) {
            $this->jsonError($e->getMessage(), 500);
        }
    }

    public function dismiss(): void
    {
        if (!Session::checkToken()) {
            $this->jsonError(Text::_('JINVALID_TOKEN'), 403);
            return;
        }

        $checkId = $this->input->getString('checkId', '');

        if ($checkId === '') {
            $this->jsonError(Text::_('JGLOBAL_NO_ITEM_SELECTED'));
            return;
        }

        try {
            $check = SetupGuideHelper::findCheck($checkId);

            if ($check === null) {
                $this->jsonError(Text::_('JGLOBAL_NO_ITEM_SELECTED'), 404);
                return;
            }

            if (!$check->isDismissible()) {
                $this->jsonError(Text::_('JLIB_APPLICATION_ERROR_ACCESS_FORBIDDEN'), 403);
                return;
            }

            $db    = $this->getDb();
            $query = $db->getQuery(true)
                ->select([$db->quoteName('extension_id'), $db->quoteName('params')])
                ->from($db->quoteName('#__extensions'))
                ->where($db->quoteName('element') . ' = ' . $db->quote('com_j2commerce'))
                ->where($db->quoteName('type') . ' = ' . $db->quote('component'));

            $ext = $db->setQuery($query)->loadObject();

            if (!$ext) {
                $this->jsonError('Extension record not found', 500);
                return;
            }

            $params = new Registry($ext->params);
            $params->set('setup_dismissed_' . $checkId, true);
            $paramsJson = $params->toString();

            $updateQuery = $db->getQuery(true)
                ->update($db->quoteName('#__extensions'))
                ->set($db->quoteName('params') . ' = :params')
                ->where($db->quoteName('extension_id') . ' = :id')
                ->bind(':params', $paramsJson)
                ->bind(':id', $ext->extension_id, ParameterType::INTEGER);

            $db->setQuery($updateQuery)->execute();

            Factory::getApplication()->bootComponent('com_j2commerce');

            $this->jsonSuccess(null, Text::_('JLIB_APPLICATION_SAVE_SUCCESS'));
        } catch (\Throwable $e) {
            $this->jsonError($e->getMessage(), 500);
        }
    }

    private function handleEnablePlugin(string $folder, string $element): void
    {
        $allowed = [
            'system/j2commerce',
            'content/j2commerce',
        ];

        if (!\in_array($folder . '/' . $element, $allowed, true)) {
            throw new \InvalidArgumentException('Plugin not in allowlist: ' . $folder . '/' . $element);
        }

        $db    = $this->getDb();
        $query = $db->getQuery(true)
            ->update($db->quoteName('#__extensions'))
            ->set($db->quoteName('enabled') . ' = 1')
            ->where($db->quoteName('type') . ' = ' . $db->quote('plugin'))
            ->where($db->quoteName('folder') . ' = :folder')
            ->where($db->quoteName('element') . ' = :element')
            ->bind(':folder', $folder)
            ->bind(':element', $element);

        $db->setQuery($query)->execute();

        Factory::getCache()->clean('_system');
    }

    private function handleCreateMenuItem(string $link, string $title): void
    {
        if ($link === '' || $title === '') {
            throw new \InvalidArgumentException('Menu item link and title are required');
        }

        $db = $this->getDb();

        // Find or create the j2commerce menu type
        $query = $db->getQuery(true)
            ->select($db->quoteName('id'))
            ->from($db->quoteName('#__menu_types'))
            ->where($db->quoteName('menutype') . ' = ' . $db->quote('j2commerce'));

        $menuTypeId = $db->setQuery($query)->loadResult();

        if (!$menuTypeId) {
            $menuType              = new \stdClass();
            $menuType->menutype    = 'j2commerce';
            $menuType->title       = 'J2Commerce';
            $menuType->description = '';
            $menuType->client_id   = 0;
            $db->insertObject('#__menu_types', $menuType);
        }

        // Look up com_j2commerce extension_id
        $query = $db->getQuery(true)
            ->select($db->quoteName('extension_id'))
            ->from($db->quoteName('#__extensions'))
            ->where($db->quoteName('element') . ' = ' . $db->quote('com_j2commerce'))
            ->where($db->quoteName('type') . ' = ' . $db->quote('component'))
            ->where($db->quoteName('client_id') . ' = 1');

        $componentId = (int) $db->setQuery($query)->loadResult();

        $alias = ApplicationHelper::stringURLSafe($title);

        // If a menu item with this alias already exists at the same level, append -2
        $query = $db->getQuery(true)
            ->select('COUNT(*)')
            ->from($db->quoteName('#__menu'))
            ->where($db->quoteName('alias') . ' = ' . $db->quote($alias))
            ->where($db->quoteName('parent_id') . ' = 1')
            ->where($db->quoteName('client_id') . ' = 0')
            ->where($db->quoteName('language') . ' = ' . $db->quote('*'));

        if ((int) $db->setQuery($query)->loadResult() > 0) {
            $alias .= '-2';
        }

        $menuItem               = new \stdClass();
        $menuItem->menutype     = 'j2commerce';
        $menuItem->title        = $title;
        $menuItem->alias        = $alias;
        $menuItem->path         = $alias;
        $menuItem->link         = $link;
        $menuItem->type         = 'component';
        $menuItem->published    = 1;
        $menuItem->parent_id    = 1;
        $menuItem->level        = 1;
        $menuItem->component_id = $componentId;
        $menuItem->access       = 1;
        $menuItem->params       = '{}';
        $menuItem->img          = ' ';
        $menuItem->lft          = 0;
        $menuItem->rgt          = 0;
        $menuItem->home         = 0;
        $menuItem->language     = '*';
        $menuItem->client_id    = 0;

        $db->insertObject('#__menu', $menuItem);

        $table = Table::getInstance('Menu');
        $table->rebuild();
    }

    private function handleSaveParam(string $paramName, string $paramValue): void
    {
        $allowlist = ['downloadid'];

        if (!\in_array($paramName, $allowlist, true)) {
            throw new \InvalidArgumentException(Text::_('COM_J2COMMERCE_SETUP_GUIDE_PARAM_NOT_ALLOWED'));
        }

        $paramValue = trim($paramValue);
        $db         = $this->getDb();
        $query      = $db->getQuery(true)
            ->select([$db->quoteName('extension_id'), $db->quoteName('params')])
            ->from($db->quoteName('#__extensions'))
            ->where($db->quoteName('element') . ' = ' . $db->quote('com_j2commerce'))
            ->where($db->quoteName('type') . ' = ' . $db->quote('component'));

        $ext = $db->setQuery($query)->loadObject();

        if (!$ext) {
            throw new \RuntimeException('Extension record not found');
        }

        $params = new Registry($ext->params);
        $params->set($paramName, $paramValue);
        $paramsJson = $params->toString();

        $updateQuery = $db->getQuery(true)
            ->update($db->quoteName('#__extensions'))
            ->set($db->quoteName('params') . ' = :params')
            ->where($db->quoteName('extension_id') . ' = :id')
            ->bind(':params', $paramsJson)
            ->bind(':id', $ext->extension_id, ParameterType::INTEGER);

        $db->setQuery($updateQuery)->execute();

        Factory::getApplication()->bootComponent('com_j2commerce');
    }

    private function handlePublishMenuItem(int $menuItemId): void
    {
        if ($menuItemId <= 0) {
            throw new \InvalidArgumentException('Invalid menu item ID');
        }

        $db    = $this->getDb();
        $query = $db->getQuery(true)
            ->update($db->quoteName('#__menu'))
            ->set($db->quoteName('published') . ' = 1')
            ->where($db->quoteName('id') . ' = :id')
            ->bind(':id', $menuItemId, ParameterType::INTEGER);

        $db->setQuery($query)->execute();
    }
}
