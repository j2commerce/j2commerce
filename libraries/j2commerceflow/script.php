<?php

/**
 * @package     J2Commerce
 * @subpackage  lib_j2commerceflow
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

use Joomla\CMS\Factory;
use Joomla\CMS\Installer\InstallerAdapter;
use Joomla\CMS\Installer\InstallerScriptInterface;
use Joomla\CMS\Language\Text;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

return new class () implements InstallerScriptInterface {
    // Install-record copy only — script.php runs install-time only. The runtime
    // version source is J2Commerce\Library\Flow\Version::VERSION.
    private const LIB_VERSION = '6.4.0';

    private string $minimumPhp    = '8.1.0';
    private string $minimumJoomla = '6.0.0';

    public function install(InstallerAdapter $adapter): bool
    {
        return true;
    }

    public function update(InstallerAdapter $adapter): bool
    {
        return true;
    }

    public function uninstall(InstallerAdapter $adapter): bool
    {
        return true;
    }

    public function preflight(string $type, InstallerAdapter $adapter): bool
    {
        if (version_compare(\PHP_VERSION, $this->minimumPhp, '<')) {
            Factory::getApplication()->enqueueMessage(
                Text::sprintf('LIB_J2COMMERCEFLOW_ERR_PHP_VERSION', $this->minimumPhp),
                'error'
            );

            return false;
        }

        if (version_compare(JVERSION, $this->minimumJoomla, '<')) {
            Factory::getApplication()->enqueueMessage(
                Text::sprintf('JLIB_INSTALLER_MINIMUM_JOOMLA', $this->minimumJoomla),
                'error'
            );

            return false;
        }

        return true;
    }

    public function postflight(string $type, InstallerAdapter $adapter): bool
    {
        return true;
    }
};
