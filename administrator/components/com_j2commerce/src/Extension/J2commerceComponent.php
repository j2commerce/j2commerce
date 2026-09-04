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
use J2Commerce\Component\J2commerce\Administrator\Helper\StockCommittedSeedHelper;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Component\Router\RouterServiceInterface;
use Joomla\CMS\Component\Router\RouterServiceTrait;
use Joomla\CMS\Extension\BootableExtensionInterface;
use Joomla\CMS\Extension\MVCComponent;
use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLRegistryAwareTrait;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Schemaorg\SchemaorgServiceInterface;
use Joomla\CMS\Schemaorg\SchemaorgServiceTrait;
use Psr\Container\ContainerInterface;

/**
 * Component class for com_j2commerce
 *
 * @since  6.0.0
 */
class J2commerceComponent extends MVCComponent implements
    BootableExtensionInterface,
    RouterServiceInterface,
    SchemaorgServiceInterface
{
    use HTMLRegistryAwareTrait;
    use RouterServiceTrait;
    use SchemaorgServiceTrait;

    /**
     * Both plg_system_schemaorg and the schemaorg plugins gate every dispatch on the
     * component implementing SchemaorgServiceInterface, so this list is what makes the
     * Schema tab appear on the product form and the #__schemaorg overrides reach the page.
     */
    public function getSchemaorgContexts(): array
    {
        Factory::getLanguage()->load('com_j2commerce', JPATH_ADMINISTRATOR);

        return [
            'com_j2commerce.product' => Text::_('COM_J2COMMERCE'),
        ];
    }

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
        $this->seedStockCommitted();
    }

    /**
     * Joomla's Database -> Fix adds the stock_committed column and advances #__schemas without
     * running the installer script, so the postflight seed never happens on that path. Until it
     * does, every pre-existing order reads as uncommitted while its stock is already deducted,
     * and the next holding-to-holding status change deducts it a second time.
     *
     * Same gate as the ACL seed: an administrator request from a holder of core.admin.
     */
    private function seedStockCommitted(): void
    {
        $app = Factory::getApplication();

        if (!$app->isClient('administrator')) {
            return;
        }

        if ((int) ComponentHelper::getParams('com_j2commerce')->get(StockCommittedSeedHelper::SEED_FLAG, 0) === 1) {
            return;
        }

        $user = $app->getIdentity();

        if (!$user || !$user->authorise('core.admin', 'com_j2commerce')) {
            return;
        }

        // Never throws; a failure leaves the marker unset and the next request retries.
        StockCommittedSeedHelper::ensureSeeded();
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

        if (!AclSeedHelper::needsSeeding(ComponentHelper::getParams('com_j2commerce'))) {
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
