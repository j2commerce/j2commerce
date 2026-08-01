<?php

/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace J2Commerce\Component\J2commerce\Administrator\Extension;

\defined('_JEXEC') or die;

use J2Commerce\Component\J2commerce\Administrator\Helper\AclSeedHelper;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Component\Router\RouterServiceInterface;
use Joomla\CMS\Component\Router\RouterServiceTrait;
use Joomla\CMS\Extension\BootableExtensionInterface;
use Joomla\CMS\Extension\MVCComponent;
use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLRegistryAwareTrait;
use Joomla\CMS\Language\Text;
use Psr\Container\ContainerInterface;

/**
 * Component class for com_j2commerce
 *
 * @since  6.0.0
 */
class J2commerceComponent extends MVCComponent implements
    BootableExtensionInterface,
    RouterServiceInterface
{
    use HTMLRegistryAwareTrait;
    use RouterServiceTrait;

    /**
     * Booting the extension. This is the function to set up the environment of the extension like
     * registering new class loaders, etc.
     *
     * @param   ContainerInterface  $container  The container
     *
     * @return  void
     *
     * @since   6.0.0
     */
    public function boot(ContainerInterface $container): void
    {
        // CSS assets are loaded via AdminAssetsTrait in each HtmlView
        $this->seedCustomAclActions();
    }

    /**
     * The installer's postflight seeds the custom actions, but a site deployed by git pull,
     * rsync or FTP never runs it, and canAccess() falls back to core.manage until the flag is
     * set — so access.xml cannot restrict anyone. Re-attempt here so such a site converges.
     *
     * Restricted to a holder of core.admin on com_j2commerce — the same capability com_config
     * requires to edit this asset's rules by hand, so the write grants nothing new. Note that
     * is not only Super Users: setDefaultAcl() grants core.admin to Administrator as well.
     */
    private function seedCustomAclActions(): void
    {
        $app = Factory::getApplication();

        if (!$app->isClient('administrator')) {
            return;
        }

        if ((int) ComponentHelper::getParams('com_j2commerce')->get(AclSeedHelper::ACL_SEED_FLAG, 0) === 1) {
            return;
        }

        $user = $app->getIdentity();

        if (!$user || !$user->authorise('core.admin', 'com_j2commerce')) {
            return;
        }

        try {
            $seeded = AclSeedHelper::ensureSeeded();
        } catch (\Throwable $e) {
            $seeded = false;
        }

        if (!$seeded) {
            $app->enqueueMessage(Text::_('COM_J2COMMERCE_INSTALL_ACL_SEED_FAILED'), 'warning');
        }
    }
}
