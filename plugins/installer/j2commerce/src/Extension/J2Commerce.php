<?php

/**
 * @package     Joomla.Plugin
 * @subpackage  plg_installer_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace J2Commerce\Plugin\Installer\J2Commerce\Extension;

\defined('_JEXEC') or die;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Event\Installer\BeforePackageDownloadEvent;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Uri\Uri;
use Joomla\Event\SubscriberInterface;

final class J2Commerce extends CMSPlugin implements SubscriberInterface
{
    protected $autoloadLanguage = true;

    public static function getSubscribedEvents(): array
    {
        return [
            'onInstallerBeforePackageDownload' => 'onInstallerBeforePackageDownload',
        ];
    }

    public function onInstallerBeforePackageDownload(BeforePackageDownloadEvent $event): bool
    {
        $url     = $event->getUrl();
        $headers = $event->getHeaders();

        if (preg_match('/j2commerce\.com\/j2commerce\//', $url) == false) {
            return false;
        }

        // dlid used by third-party services - the url already contains the dlid - do not go through the pre-checks
        if (strpos($url, 'dlid=') !== false) {
            return true;
        }

        // we will append the dlid to the url, if there is one, for every extension being updated by J2Commerce
        $store      = ComponentHelper::getParams('com_j2commerce');
        $downloadId = trim((string) $store->get('downloadid', ''));

        $configLink = '<a href="' . Uri::base() . 'index.php?option=com_config&view=component&component=com_j2commerce">'
            . Text::_('PLG_INSTALLER_J2COMMERCE_MESSAGE_CONFIG_LINK_UPDATES_TAB')
            . '</a>';

        if ($downloadId) {
            $uri = new Uri($url);
            $uri->setVar('dlid', $downloadId);
            $url = $uri->render();
            $event->updateUrl($url);

            $this->getApplication()->enqueueMessage(Text::sprintf('PLG_INSTALLER_J2COMMERCE_MESSAGE_DOWNLOAD_ID_VALIDATION', $configLink));
        } else {
            $accountLink = '<a href="https://www.j2commerce.com/my-account" target="_blank">J2Commerce.com</a>';

            $this->getApplication()->enqueueMessage(Text::sprintf('PLG_INSTALLER_J2COMMERCE_MESSAGE_DOWNLOAD_ID_MISSING', $configLink, $accountLink));
        }

        $event->updateHeaders($headers);

        return true;
    }
}
