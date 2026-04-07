<?php

/**
 * @package     J2Commerce
 * @subpackage  plg_j2commerce_app_diagnostics
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace J2Commerce\Plugin\J2Commerce\AppDiagnostics\Extension;

use J2Commerce\Component\J2commerce\Administrator\Helper\J2CommerceHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\Version;
use Joomla\Database\DatabaseAwareTrait;
use Joomla\Event\SubscriberInterface;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * J2Commerce Diagnostics App Plugin
 *
 * Provides system diagnostics and maintenance utilities for J2Commerce.
 *
 * @since  6.0.0
 */
final class AppDiagnostics extends CMSPlugin implements SubscriberInterface
{
    use DatabaseAwareTrait;

    protected $autoloadLanguage = true;

    public static function getSubscribedEvents(): array
    {
        return [
            'onJ2CommerceProcessCron' => 'onJ2CommerceProcessCron',
        ];
    }

    /**
     * Get system diagnostic information.
     *
     * @return  array<string, mixed>
     *
     * @since   6.0.0
     */
    public function getInfo(): array
    {
        $version = new Version();
        $db      = $this->getDatabase();
        $config  = $this->getApplication()->getConfig();

        $server      = $_SERVER['SERVER_SOFTWARE'] ?? getenv('SERVER_SOFTWARE') ?: '';
        $caching     = $config->get('caching');
        $cachePlugin = PluginHelper::isEnabled('system', 'cache');

        return [
            'php'               => php_uname(),
            'dbversion'         => $db->getVersion(),
            'dbcollation'       => $db->getCollation(),
            'phpversion'        => phpversion(),
            'server'            => $server,
            'sapi_name'         => php_sapi_name(),
            'version'           => $version->getLongVersion(),
            'useragent'         => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'j2c_version'       => $this->getJ2CommerceVersion(),
            'is_pro'            => J2CommerceHelper::isPro(),
            'curl'              => \function_exists('curl_version') ? Text::_('JENABLED') : Text::_('JDISABLED'),
            'json'              => \function_exists('json_encode') ? Text::_('JENABLED') : Text::_('JDISABLED'),
            'error_reporting'   => $config->get('error_reporting'),
            'caching'           => $caching ? Text::_('JENABLED') : Text::_('JDISABLED'),
            'plg_cache_enabled' => $cachePlugin ? Text::_('JENABLED') : Text::_('JDISABLED'),
            'memory_limit'      => \ini_get('memory_limit'),
        ];
    }

    /**
     * Get J2Commerce component version.
     *
     * @return  string
     *
     * @since   6.0.0
     */
    public function getJ2CommerceVersion(): string
    {
        $db    = $this->getDatabase();
        $query = $db->getQuery(true)
            ->select($db->quoteName('manifest_cache'))
            ->from($db->quoteName('#__extensions'))
            ->where($db->quoteName('element') . ' = :element')
            ->bind(':element', $element);

        $element = 'com_j2commerce';
        $db->setQuery($query);
        $result = $db->loadResult();

        if ($result) {
            $manifest = json_decode($result);
            return $manifest->version ?? '';
        }

        return '';
    }

    /**
     * Handle cron commands.
     *
     * @param   object  $event  The cron event
     *
     * @return  void
     *
     * @since   6.0.0
     */
    public function onJ2CommerceProcessCron($event): void
    {
        $command = $event->getArgument('command', '');

        if ($command === 'clear_cart') {
            $this->clearOutdatedCartData();
        }
    }

    /**
     * Clear outdated cart data.
     *
     * @return  void
     *
     * @since   6.0.0
     */
    public function clearOutdatedCartData(): void
    {
        $app       = $this->getApplication();
        $clearTime = $app->getInput()->getInt('clear_time', 0);

        if ($clearTime <= 0) {
            $j2params  = J2CommerceHelper::config();
            $daysOld   = $j2params->get('clear_outdated_cart_data_term', 90);
            $clearTime = $daysOld * 1440; // Convert to minutes
        }

        $tz         = $app->get('offset');
        $cutoffDate = Factory::getDate('now -' . $clearTime . ' minutes', $tz)->toSql(true);

        $db = $this->getDatabase();

        // Get old cart IDs
        $query = $db->getQuery(true)
            ->select($db->quoteName('j2commerce_cart_id'))
            ->from($db->quoteName('#__j2commerce_carts'))
            ->where($db->quoteName('cart_type') . ' = :cartType')
            ->where($db->quoteName('created_on') . ' <= :cutoff')
            ->bind(':cartType', $cartType)
            ->bind(':cutoff', $cutoffDate);

        $cartType = 'cart';
        $db->setQuery($query);
        $cartIds = $db->loadColumn();

        if (empty($cartIds)) {
            return;
        }

        // Delete cart items
        $query = $db->getQuery(true)
            ->delete($db->quoteName('#__j2commerce_cartitems'))
            ->whereIn($db->quoteName('cart_id'), $cartIds);

        try {
            $db->setQuery($query)->execute();
        } catch (\Exception $e) {
            // Log error but continue
        }

        // Delete carts
        $query = $db->getQuery(true)
            ->delete($db->quoteName('#__j2commerce_carts'))
            ->where($db->quoteName('cart_type') . ' = :cartType')
            ->where($db->quoteName('created_on') . ' <= :cutoff')
            ->bind(':cartType', $cartType)
            ->bind(':cutoff', $cutoffDate);

        try {
            $db->setQuery($query)->execute();
        } catch (\Exception $e) {
            // Log error
        }
    }
}
