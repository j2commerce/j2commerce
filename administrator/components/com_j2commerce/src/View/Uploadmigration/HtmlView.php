<?php

/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace J2Commerce\Component\J2commerce\Administrator\View\Uploadmigration;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use J2Commerce\Component\J2commerce\Administrator\Helper\J2CommerceHelper;
use J2Commerce\Component\J2commerce\Administrator\Helper\MenuHelper;
use J2Commerce\Component\J2commerce\Administrator\Model\UploadmigrationModel;
use J2Commerce\Component\J2commerce\Administrator\View\AdminAssetsTrait;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\ToolbarHelper;

class HtmlView extends BaseHtmlView
{
    use AdminAssetsTrait;

    protected string $navbar = '';

    /** @var array{root: ?string, root_display: string, folders: array<string, string>, entries: array<int, array<string, mixed>>, counts: array<string, int>} */
    protected array $scan = [];

    public function display($tpl = null)
    {
        // The same level the action carries (UploadmigrationController::migrate). The report
        // names the stored files themselves, so reading it is the weightier half of the screen,
        // not the lighter one.
        if (!Factory::getApplication()->getIdentity()->authorise('core.admin', 'com_j2commerce')) {
            J2CommerceHelper::denyAccess();
            return;
        }

        $this->loadAdminAssets();

        $this->navbar = LayoutHelper::render(
            'navbar.default',
            ['items' => MenuHelper::getMenuItems(), 'active' => MenuHelper::getActiveView()],
            JPATH_COMPONENT_ADMINISTRATOR . '/layouts'
        );

        $this->scan = $this->getModel()->scan();

        $this->addToolbar();

        parent::display($tpl);
    }

    protected function addToolbar(): void
    {
        ToolbarHelper::title(Text::_('COM_J2COMMERCE_UPLOAD_MIGRATION'), 'move fa-solid fa-truck-ramp-box');

        $toolbar = $this->getDocument()->getToolbar();

        $actionable = ($this->scan['counts'][UploadmigrationModel::STATE_REASSOCIATE] ?? 0)
            + ($this->scan['counts'][UploadmigrationModel::STATE_MOVABLE] ?? 0);

        if ($actionable > 0 && Factory::getApplication()->getIdentity()->authorise('core.admin', 'com_j2commerce')) {
            $toolbar->confirmButton('move', 'COM_J2COMMERCE_UPLOAD_MIGRATION_TOOLBAR_MOVE', 'uploadmigration.migrate')
                ->message('COM_J2COMMERCE_UPLOAD_MIGRATION_CONFIRM')
                ->icon('fa-solid fa-truck-ramp-box')
                ->listCheck(false);
        }

        $toolbar->help(Text::_('COM_J2COMMERCE_UPLOAD_MIGRATION'), true, 'https://docs.j2commerce.com/v6/setup/upload-migration/');
    }
}
