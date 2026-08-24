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
use J2Commerce\Plugin\J2Commerce\AppDiagnostics\Extension\AppDiagnostics;
use Joomla\CMS\Factory;
use Joomla\CMS\Form\FormField;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\Session\Session;
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

        // Cron URL row. ComponentDispatcher routes on a dotted task, so cron.execute is the
        // form that reaches CronController; the retention window comes from the store settings.
        $esc      = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
        $cronBase = rtrim(Uri::root(), '/')
            . '/index.php?option=com_j2commerce&task=cron.execute&command=clear_cart&cron_secret=';
        $maskedUrl = $cronBase . ($cronKey === '' ? '' : str_repeat('*', 12));

        // The complete URL carries the queue key, which is configured on the component Options
        // form. This block is reached at a different level, so the value is emitted only where
        // the caller also holds that form's capability.
        $user       = Factory::getApplication()->getIdentity();
        $maySeeKey  = $user !== null && !$user->guest
            && ($user->authorise('core.admin') || $user->authorise('core.options', 'com_j2commerce'));

        $html[] = '<tr>';
        $html[] = '<th scope="row">' . Text::_('PLG_J2COMMERCE_APP_DIAGNOSTICS_CLEAR_CART_CRON') . '</th>';
        $html[] = '<td class="d-flex align-items-center gap-2 flex-wrap">';

        if ($maySeeKey) {
            $html[] = '<code id="j2c-diag-cron-url" data-cron-url="' . $esc($cronBase . $cronKey)
                . '" data-cron-masked="' . $esc($maskedUrl) . '">' . $esc($maskedUrl) . '</code>';
            $html[] = '<button type="button" class="btn btn-sm btn-secondary" id="j2c-diag-cron-toggle"'
                . ' data-label-show="' . $esc(Text::_('JSHOW')) . '"'
                . ' data-label-hide="' . $esc(Text::_('JHIDE')) . '">' . $esc(Text::_('JSHOW')) . '</button>';
        } else {
            $html[] = '<code>' . $esc($maskedUrl) . '</code>';
        }

        $html[] = '</td>';
        $html[] = '</tr>';

        $html[] = '</tbody>';
        $html[] = '</table>';
        $html[] = '</div>';

        $html[] = $this->renderMailSection();
        $html[] = <<<'HTML'
<script>
document.addEventListener('DOMContentLoaded', function () {
    'use strict';

    const url = document.getElementById('j2c-diag-cron-url');
    const btn = document.getElementById('j2c-diag-cron-toggle');

    if (!url || !btn) {
        return;
    }

    btn.addEventListener('click', function () {
        const revealed = btn.dataset.state === 'shown';

        url.textContent = revealed ? url.dataset.cronMasked : url.dataset.cronUrl;
        btn.textContent = revealed ? btn.dataset.labelShow : btn.dataset.labelHide;
        btn.dataset.state = revealed ? 'hidden' : 'shown';
    });
});
</script>
HTML;

        return implode("\n", $html);
    }

    /**
     * Mail transport panel. Global Configuration's "Send Test Mail" builds a throwaway
     * config from the form, so it can pass while every component email fails — this
     * reports the transport actually built from the SAVED configuration.
     *
     * @since   6.2.3
     */
    private function renderMailSection(): string
    {
        $plugin = $this->getDiagnosticsPlugin();

        if ($plugin === null) {
            return '';
        }

        $mail = $plugin->getMailInfo();
        $esc  = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
        $none = Text::_('JNONE');

        $rows = [
            'PLG_J2COMMERCE_APP_DIAGNOSTICS_MAIL_ONLINE'   => $mail['mailonline'] ? Text::_('JENABLED') : Text::_('JDISABLED'),
            'PLG_J2COMMERCE_APP_DIAGNOSTICS_MAIL_SAVED'    => $mail['saved_mailer'] !== '' ? $mail['saved_mailer'] : $none,
            'PLG_J2COMMERCE_APP_DIAGNOSTICS_MAIL_BUILT'    => $mail['built_mailer'],
            'PLG_J2COMMERCE_APP_DIAGNOSTICS_MAIL_FROM'     => $mail['mailfrom'] !== '' ? $mail['mailfrom'] : $none,
            'PLG_J2COMMERCE_APP_DIAGNOSTICS_MAIL_ENVELOPE' => $mail['envelope_sender'] !== ''
                ? Text::sprintf('PLG_J2COMMERCE_APP_DIAGNOSTICS_MAIL_ENVELOPE_SENT', $mail['envelope_sender'])
                : Text::_('PLG_J2COMMERCE_APP_DIAGNOSTICS_MAIL_ENVELOPE_NOT_SENT'),
        ];

        if ($mail['built_mailer'] === 'smtp' || $mail['saved_mailer'] === 'smtp') {
            $rows['PLG_J2COMMERCE_APP_DIAGNOSTICS_MAIL_SMTP_HOST'] = $mail['smtphost'] !== ''
                ? $mail['smtphost'] . ':' . $mail['smtpport']
                : $none;
            $rows['PLG_J2COMMERCE_APP_DIAGNOSTICS_MAIL_SMTP_SECURE'] = $mail['smtpsecure'] !== '' ? $mail['smtpsecure'] : $none;
            $rows['PLG_J2COMMERCE_APP_DIAGNOSTICS_MAIL_SMTP_AUTH']   = $mail['smtpauth']
                ? Text::_('JENABLED') . ' (' . Text::_($mail['smtpuser_set'] ? 'JYES' : 'JNO') . '/'
                    . Text::_($mail['smtppass_set'] ? 'JYES' : 'JNO') . ')'
                : Text::_('JDISABLED');
        }

        if ($mail['built_mailer'] === 'mail') {
            $rows['PLG_J2COMMERCE_APP_DIAGNOSTICS_MAIL_SENDMAIL_PATH'] = $mail['sendmail_path'] !== '' ? $mail['sendmail_path'] : $none;
            $rows['PLG_J2COMMERCE_APP_DIAGNOSTICS_MAIL_FORCE_EXTRA']   = $mail['force_extra'] !== '' ? $mail['force_extra'] : $none;
        }

        $html   = [];
        $html[] = '<h3 class="mt-4">' . Text::_('PLG_J2COMMERCE_APP_DIAGNOSTICS_MAIL_HEADING') . '</h3>';
        $html[] = '<p class="text-body-secondary">' . Text::_('PLG_J2COMMERCE_APP_DIAGNOSTICS_MAIL_INTRO') . '</p>';

        if (!$mail['mailonline']) {
            $html[] = '<div class="alert alert-warning">' . Text::_('JLIB_MAIL_FUNCTION_OFFLINE') . '</div>';
        }

        if ($mail['silent_fallback']) {
            $html[] = '<div class="alert alert-danger">' . Text::_('PLG_J2COMMERCE_APP_DIAGNOSTICS_MAIL_SILENT_FALLBACK') . '</div>';
        }

        $html[] = '<div class="table-responsive">';
        $html[] = '<table class="table">';
        $html[] = '<caption class="visually-hidden">' . Text::_('PLG_J2COMMERCE_APP_DIAGNOSTICS_MAIL_HEADING') . '</caption>';
        $html[] = '<thead><tr>';
        $html[] = '<th scope="col" class="w-30">' . Text::_('PLG_J2COMMERCE_APP_DIAGNOSTICS_TABLE_SETTING') . '</th>';
        $html[] = '<th scope="col">' . Text::_('PLG_J2COMMERCE_APP_DIAGNOSTICS_TABLE_VALUE') . '</th>';
        $html[] = '</tr></thead>';
        $html[] = '<tbody>';

        foreach ($rows as $label => $value) {
            $html[] = '<tr><th scope="row">' . Text::_($label) . '</th><td>' . $esc($value) . '</td></tr>';
        }

        $html[] = '</tbody></table></div>';

        if ($mail['built_mailer'] === 'mail') {
            $html[] = $this->renderMailTestControl();
        }

        return implode("\n", $html);
    }

    /**
     * On-demand -f probe. Sends only to the signed-in administrator's own address, so the
     * endpoint cannot be pointed at a chosen recipient.
     *
     * @since   6.2.3
     */
    private function renderMailTestControl(): string
    {
        $app = Factory::getApplication();
        $doc = $app->getDocument();

        $doc->getWebAssetManager()->registerAndUseScript(
            'plg_j2commerce_app_diagnostics.admin',
            'media/plg_j2commerce_app_diagnostics/js/administrator/diagnostics.js',
            [],
            ['defer' => true],
            ['core']
        );

        $doc->addScriptOptions('plg_j2commerce_app_diagnostics', [
            // Uri::base, not Uri::root — site and administrator hold separate sessions, so an
            // admin form token is not valid against the site com_ajax entry point.
            'url'   => Uri::base(true) . '/index.php?option=com_ajax&group=j2commerce&plugin=app_diagnostics&format=json',
            'token' => Session::getFormToken(),
        ]);

        Text::script('PLG_J2COMMERCE_APP_DIAGNOSTICS_MAIL_TEST_RUNNING');
        Text::script('PLG_J2COMMERCE_APP_DIAGNOSTICS_MAIL_TEST_ERROR');
        Text::script('PLG_J2COMMERCE_APP_DIAGNOSTICS_MAIL_TEST_WITHOUT_F');
        Text::script('PLG_J2COMMERCE_APP_DIAGNOSTICS_MAIL_TEST_WITH_F');
        Text::script('JYES');
        Text::script('JNO');

        $html   = [];
        $html[] = '<p class="text-body-secondary">' . Text::_('PLG_J2COMMERCE_APP_DIAGNOSTICS_MAIL_TEST_DESC') . '</p>';
        $html[] = '<button type="button" class="btn btn-secondary" id="j2c-diag-mailtest">'
            . Text::_('PLG_J2COMMERCE_APP_DIAGNOSTICS_MAIL_TEST_BUTTON') . '</button>';
        $html[] = '<div id="j2c-diag-mailtest-result" class="mt-3" role="status" aria-live="polite"></div>';

        return implode("\n", $html);
    }

    private function getDiagnosticsPlugin(): ?AppDiagnostics
    {
        $plugin = Factory::getApplication()->bootPlugin('app_diagnostics', 'j2commerce');

        return $plugin instanceof AppDiagnostics ? $plugin : null;
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
