<?php

/**
 * @package     J2Commerce
 * @subpackage  plg_j2commerce_app_diagnostics
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace J2Commerce\Plugin\J2Commerce\AppDiagnostics\Field;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use J2Commerce\Component\J2commerce\Administrator\Helper\J2CommerceHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Form\FormField;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\Version;

/**
 * Diagnostic information display field.
 *
 * @since  6.0.0
 */
class DiagnosticField extends FormField
{
    protected $type = 'Diagnostic';

    protected function getInput(): string
    {
        $diagnostics = $this->getInfo();
        $cronKey     = J2CommerceHelper::config()->get('queue_key', '');

        $html = [];

        if ((int) $diagnostics['memory_limit'] < 64) {
            $html[] = '<div class="alert alert-danger">';
            $html[] = Text::_('PLG_J2COMMERCE_APP_DIAGNOSTICS_MINIMUM_MEMORY_LIMIT_WARNING');
            $html[] = '</div>';
        }

        $html[] = '<div class="table-responsive">';
        $html[] = '<table class="table">';
        $html[] = '<caption class="visually-hidden">' . Text::_('PLG_J2COMMERCE_APP_DIAGNOSTICS_BASIC') . '</caption>';
        $html[] = '<thead>';
        $html[] = '<tr>';
        $html[] = '<th scope="col" class="w-30">' . Text::_('PLG_J2COMMERCE_APP_DIAGNOSTICS_TABLE_SETTING') . '</th>';
        $html[] = '<th scope="col">' . Text::_('PLG_J2COMMERCE_APP_DIAGNOSTICS_TABLE_VALUE') . '</th>';
        $html[] = '</tr>';
        $html[] = '</thead>';
        $html[] = '<tbody>';

        $rows = [
            'PLG_J2COMMERCE_APP_DIAGNOSTICS_PHP_BUILT_ON'       => php_uname(),
            'PLG_J2COMMERCE_APP_DIAGNOSTICS_WEB_SERVER'         => $diagnostics['server'],
            'PLG_J2COMMERCE_APP_DIAGNOSTICS_PHP_VERSION'        => $diagnostics['phpversion'],
            'PLG_J2COMMERCE_APP_DIAGNOSTICS_JOOMLA_VERSION'     => $diagnostics['version'],
            'PLG_J2COMMERCE_APP_DIAGNOSTICS_J2COMMERCE_VERSION' => $diagnostics['j2c_version'],
            'PLG_J2COMMERCE_APP_DIAGNOSTICS_MEMORY_LIMIT'       => $diagnostics['memory_limit'],
            'PLG_J2COMMERCE_APP_DIAGNOSTICS_CURL'               => $diagnostics['curl'],
            'PLG_J2COMMERCE_APP_DIAGNOSTICS_JSON'               => $diagnostics['json'],
            'PLG_J2COMMERCE_APP_DIAGNOSTICS_ERROR_REPORTING'    => $diagnostics['error_reporting'],
            'PLG_J2COMMERCE_APP_DIAGNOSTICS_CACHE'              => $diagnostics['caching'],
            'PLG_J2COMMERCE_APP_DIAGNOSTICS_CACHE_PLUGIN'       => $diagnostics['plg_cache_enabled'],
            'PLG_J2COMMERCE_APP_DIAGNOSTICS_DB_VERSION'         => $diagnostics['dbversion'],
            'PLG_J2COMMERCE_APP_DIAGNOSTICS_DB_COLLATION'       => $diagnostics['dbcollation'],
        ];

        foreach ($rows as $label => $value) {
            $html[] = '<tr>';
            $html[] = '<th scope="row">' . Text::_($label) . '</th>';
            $html[] = '<td>' . htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8') . '</td>';
            $html[] = '</tr>';
        }

        // Cron URL row
        $cronUrl = rtrim(Uri::root(), '/') . '/index.php?option=com_j2commerce&view=crons&task=cron&cron_secret='
            . $cronKey . '&command=clear_cart&clear_time=1440';

        $html[] = '<tr>';
        $html[] = '<th scope="row">' . Text::_('PLG_J2COMMERCE_APP_DIAGNOSTICS_CLEAR_CART_CRON') . '</th>';
        $html[] = '<td><code>' . htmlspecialchars($cronUrl, ENT_QUOTES, 'UTF-8') . '</code></td>';
        $html[] = '</tr>';

        $html[] = '</tbody>';
        $html[] = '</table>';
        $html[] = '</div>';

        return implode("\n", $html);
    }

    protected function getLabel(): string
    {
        return '';
    }

    /**
     * Get diagnostic information.
     *
     * @return  array<string, mixed>
     *
     * @since   6.0.0
     */
    private function getInfo(): array
    {
        $version = new Version();
        $db      = Factory::getContainer()->get('DatabaseDriver');
        $config  = Factory::getApplication()->getConfig();

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
     * Get J2Commerce version from manifest.
     *
     * @return  string
     *
     * @since   6.0.0
     */
    private function getJ2CommerceVersion(): string
    {
        $db    = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->getQuery(true)
            ->select($db->quoteName('manifest_cache'))
            ->from($db->quoteName('#__extensions'))
            ->where($db->quoteName('element') . ' = ' . $db->quote('com_j2commerce'));

        $db->setQuery($query);
        $result = $db->loadResult();

        if ($result) {
            $manifest = json_decode($result);
            return $manifest->version ?? '';
        }

        return '';
    }
}
