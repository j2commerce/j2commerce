<?php

/**
 * @package     J2Commerce
 * @subpackage  mod_j2commerce_menu
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace J2Commerce\Module\J2commerceMenu\Administrator\Dispatcher;

\defined('_JEXEC') or die;

use Joomla\CMS\Dispatcher\AbstractModuleDispatcher;
use Joomla\CMS\HTML\HTMLHelper;

class Dispatcher extends AbstractModuleDispatcher
{
    public function dispatch(): void
    {
        $user = $this->getApplication()->getIdentity();

        if (!$user?->authorise('core.manage', 'com_j2commerce')) {
            return;
        }

        $lang = $this->getApplication()->getLanguage();
        $lang->load('mod_j2commerce_menu', JPATH_ADMINISTRATOR . '/modules/mod_j2commerce_menu');
        $lang->load('mod_j2commerce_menu', JPATH_ADMINISTRATOR);

        try {
            $this->getApplication()->bootComponent('com_j2commerce');
        } catch (\Throwable $e) {
            return;
        }

        $wa = $this->getApplication()->getDocument()->getWebAssetManager();
        $wa->useScript('metismenujs');

        HTMLHelper::_('bootstrap.offcanvas', '#j2commerceOffcanvas');

        parent::dispatch();
    }

    protected function getLayoutData(): array
    {
        $data = parent::getLayoutData();

        // MenuHelper is available after bootComponent in dispatch()
        $data['menuItems']   = \J2Commerce\Component\J2commerce\Administrator\Helper\MenuHelper::getMenuItems();
        $data['currentView'] = $this->getApplication()->getInput()->get('view', 'dashboard');

        return $data;
    }
}
