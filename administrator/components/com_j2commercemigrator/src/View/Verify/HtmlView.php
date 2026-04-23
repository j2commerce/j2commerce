<?php

/**
 * @package     J2Commerce
 * @subpackage  com_j2commercemigrator
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace J2Commerce\Component\J2commercemigrator\Administrator\View\Verify;

defined('_JEXEC') or die;

use J2Commerce\Component\J2commercemigrator\Administrator\Service\ConnectionManager;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Session\Session;
use Joomla\Database\DatabaseInterface;

class HtmlView extends BaseHtmlView
{
    public string $adapterKey = '';
    public bool   $connected  = false;

    public function display($tpl = null): void
    {
        $app     = Factory::getApplication();
        $db      = Factory::getContainer()->get(DatabaseInterface::class);
        $connMgr = new ConnectionManager($app, $db);

        $this->adapterKey = $app->getInput()->getCmd('adapter', '');
        $this->connected  = $connMgr->isReady();

        $this->setToolbar();
        $this->loadAssets();

        parent::display($tpl);
    }

    private function setToolbar(): void
    {
        $toolbar = $this->getDocument()->getToolbar();
        $toolbar->title(Text::_('COM_J2COMMERCEMIGRATOR_VIEW_VERIFY_TITLE'), 'fa-solid fa-circle-check');
        $toolbar->link(
            Text::_('COM_J2COMMERCEMIGRATOR_TOOLBAR_DASHBOARD'),
            'index.php?option=com_j2commercemigrator',
            'fa-solid fa-house'
        );
    }

    private function loadAssets(): void
    {
        $wa = $this->getDocument()->getWebAssetManager();
        $wa->registerAndUseStyle(
            'com_j2commercemigrator.migrator',
            'media/com_j2commercemigrator/css/administrator/migrator.css'
        );
        $wa->registerAndUseScript(
            'com_j2commercemigrator.migrator',
            'media/com_j2commercemigrator/js/administrator/migrator.js',
            [],
            ['defer' => true]
        );

        $this->getDocument()->addScriptOptions('com_j2commercemigrator.config', [
            'token'      => Session::getFormToken(),
            'adapterKey' => $this->adapterKey,
            'apiUrl'     => 'index.php?option=com_j2commercemigrator&task=api.run',
        ]);
    }
}
