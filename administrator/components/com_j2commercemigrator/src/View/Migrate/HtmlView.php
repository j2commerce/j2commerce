<?php

/**
 * @package     J2Commerce
 * @subpackage  com_j2commercemigrator
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace J2Commerce\Component\J2commercemigrator\Administrator\View\Migrate;

use J2Commerce\Component\J2commercemigrator\Administrator\Adapter\MigratorAdapterInterface;
use J2Commerce\Component\J2commercemigrator\Administrator\Service\AdapterRegistry;
use J2Commerce\Component\J2commercemigrator\Administrator\Service\ConnectionManager;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Session\Session;
use Joomla\Database\DatabaseInterface;

class HtmlView extends BaseHtmlView
{
    public ?MigratorAdapterInterface $adapter = null;
    public string $adapterKey = '';
    public bool $connected = false;
    public array $connectionStatus = [];

    public function display($tpl = null): void
    {
        $app        = Factory::getApplication();
        $db         = Factory::getContainer()->get(DatabaseInterface::class);
        $registry   = new AdapterRegistry();
        $connMgr    = new ConnectionManager($app, $db);

        $this->adapterKey      = $app->getInput()->getCmd('adapter', '');
        $this->adapter         = $registry->get($this->adapterKey);
        $this->connected       = $connMgr->isReady();
        $this->connectionStatus = $connMgr->getStatus();

        $this->setToolbar();
        $this->loadAssets();

        parent::display($tpl);
    }

    private function setToolbar(): void
    {
        $toolbar = $this->getDocument()->getToolbar();
        $toolbar->title(Text::_('COM_J2COMMERCEMIGRATOR_VIEW_MIGRATE_TITLE'), 'fa-solid fa-right-left');
        $toolbar->link(Text::_('COM_J2COMMERCEMIGRATOR_TOOLBAR_DASHBOARD'), 'index.php?option=com_j2commercemigrator', 'fa-solid fa-house');
    }

    private function loadAssets(): void
    {
        Text::script('COM_J2COMMERCEMIGRATOR_PREFLIGHT_BLOCKED');
        Text::script('COM_J2COMMERCEMIGRATOR_PREFLIGHT_NO_CHECKS');
        Text::script('COM_J2COMMERCEMIGRATOR_PREFLIGHT_WARNINGS');
        Text::script('COM_J2COMMERCEMIGRATOR_CONNECTION_BTN_VERIFY');
        Text::script('COM_J2COMMERCEMIGRATOR_CONNECTION_BTN_VERIFYING');

        $wa = $this->getDocument()->getWebAssetManager();
        $wa->registerAndUseStyle('com_j2commercemigrator.migrator', 'media/com_j2commercemigrator/css/administrator/migrator.css');
        $wa->registerAndUseScript('com_j2commercemigrator.migrator', 'media/com_j2commercemigrator/js/administrator/migrator.js', [], ['defer' => true]);
        $wa->registerAndUseScript('com_j2commercemigrator.migrator-run', 'media/com_j2commercemigrator/js/administrator/migrator-run.js', [], ['defer' => true]);
        $wa->registerAndUseScript('com_j2commercemigrator.migrator-connection', 'media/com_j2commercemigrator/js/administrator/migrator-connection.js', [], ['defer' => true]);

        // Pass data to JS
        $token = Session::getFormToken();
        $this->getDocument()->addScriptOptions('com_j2commercemigrator.config', [
            'token'      => $token,
            'adapterKey' => $this->adapterKey,
            'apiUrl'     => 'index.php?option=com_j2commercemigrator&task=api.run',
        ]);
    }
}
