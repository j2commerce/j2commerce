<?php

/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace J2Commerce\Component\J2commerce\Administrator\View;

\defined('_JEXEC') or die;

use Joomla\CMS\Layout\LayoutHelper;

trait AdminAssetsTrait
{
    protected function loadAdminAssets(): void
    {
        $wa = $this->getDocument()->getWebAssetManager();
        $wa->registerAndUseStyle(
            'com_j2commerce.admin.css',
            'media/com_j2commerce/css/administrator/j2commerce_admin.css',
            [],
            ['version' => '6.0.0']
        );

        $this->footer = $this->getFooter();
    }

    protected function getFooter(): string
    {
        return LayoutHelper::render('footer.default', [], JPATH_COMPONENT_ADMINISTRATOR . '/layouts');
    }
}
