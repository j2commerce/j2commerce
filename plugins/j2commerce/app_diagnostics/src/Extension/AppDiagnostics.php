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
use Joomla\CMS\Log\Log;
use Joomla\CMS\Mail\MailerFactoryInterface;
use Joomla\CMS\Mail\MailHelper;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\Session\Session;
use Joomla\CMS\Version;
use Joomla\Database\DatabaseAwareTrait;
use Joomla\Event\Event;
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
            'onAjaxApp_diagnostics'   => 'onAjaxAppDiagnostics',
        ];
    }

    /**
     * Reports the mail transport Joomla builds from the SAVED configuration — the one
     * every component email uses. Global Configuration's "Send Test Mail" does NOT test
     * this: it builds a throwaway Registry from the values currently in the form.
     *
     * @return  array<string, mixed>
     *
     * @since   6.2.3
     */
    public function getMailInfo(): array
    {
        $config = $this->getApplication()->getConfig();
        $mailer = Factory::getContainer()->get(MailerFactoryInterface::class)->createMailer();

        $saved    = (string) $config->get('mailer', '');
        $built    = (string) $mailer->Mailer;
        $mailfrom = (string) $config->get('mailfrom', '');
        $fromname = (string) $config->get('fromname', '');

        // Measures what the shipped send path actually produces rather than asserting it:
        // a non-empty Sender here means mail() is handed a -f envelope-sender argument,
        // which some hosts refuse outright.
        $probe = Factory::getContainer()->get(MailerFactoryInterface::class)->createMailer();

        if (MailHelper::isEmailAddress($mailfrom)) {
            $probe->setSender([$mailfrom, $fromname, false]);
        }

        return [
            'mailonline'      => (bool) $config->get('mailonline', 1),
            'saved_mailer'    => $saved,
            'built_mailer'    => $built,
            'silent_fallback' => $saved === 'smtp' && $built !== 'smtp',
            'mailfrom'        => $mailfrom,
            'fromname'        => $fromname,
            'smtphost'        => (string) $config->get('smtphost', ''),
            'smtpport'        => (string) $config->get('smtpport', ''),
            'smtpsecure'      => (string) $config->get('smtpsecure', ''),
            'smtpauth'        => (bool) $config->get('smtpauth', 0),
            'smtpuser_set'    => (string) $config->get('smtpuser', '') !== '',
            'smtppass_set'    => (string) $config->get('smtppass', '') !== '',
            'sendmail_path'   => (string) \ini_get('sendmail_path'),
            'force_extra'     => (string) \ini_get('mail.force_extra_parameters'),
            'envelope_sender' => (string) $probe->Sender,
        ];
    }

    /**
     * Admin-only AJAX surface. Every task independently passes all three gates: CSRF token,
     * administrator client, and core.admin — a token is anti-forgery, never authorisation.
     *
     * @since   6.2.3
     */
    public function onAjaxAppDiagnostics(Event $event): void
    {
        $app  = $this->getApplication();
        $task = $app->getInput()->getCmd('task', '');

        if (!Session::checkToken()) {
            $event->addResult(['success' => false, 'message' => Text::_('JINVALID_TOKEN')]);

            return;
        }

        $user = $app->getIdentity();

        if (!$app->isClient('administrator') || $user === null || $user->guest || !$user->authorise('core.admin')) {
            $event->addResult(['success' => false, 'message' => Text::_('JLIB_APPLICATION_ERROR_ACCESS_FORBIDDEN')]);

            return;
        }

        $event->addResult(match ($task) {
            'mailtest' => $this->ajaxMailTest(),
            default    => ['success' => false, 'message' => Text::_('JLIB_APPLICATION_ERROR_ACCESS_FORBIDDEN')],
        });
    }

    /**
     * Sends two probes through PHP's mail() — one with the -f envelope-sender argument and
     * one without — to establish whether this host refuses it. Both go to the signed-in
     * administrator's own address, so the endpoint can never relay to a chosen recipient.
     *
     * @return  array<string, mixed>
     *
     * @since   6.2.3
     */
    private function ajaxMailTest(): array
    {
        $info = $this->getMailInfo();

        if ($info['built_mailer'] !== 'mail') {
            return [
                'success' => false,
                'skipped' => true,
                'message' => Text::sprintf('PLG_J2COMMERCE_APP_DIAGNOSTICS_MAIL_TEST_NOT_APPLICABLE', $info['built_mailer']),
            ];
        }

        $to   = (string) $this->getApplication()->getIdentity()->email;
        $from = $info['mailfrom'];

        if (!MailHelper::isEmailAddress($to) || !MailHelper::isEmailAddress($from)) {
            return ['success' => false, 'message' => Text::_('PLG_J2COMMERCE_APP_DIAGNOSTICS_MAIL_TEST_NO_ADDRESS')];
        }

        $headers = 'From: ' . $from . "\r\n" . 'Content-Type: text/plain; charset=utf-8';

        $withoutF = mail($to, '[A] ' . Text::_('PLG_J2COMMERCE_APP_DIAGNOSTICS_MAIL_TEST_SUBJECT_A'), 'A', $headers);
        $withF    = mail($to, '[B] ' . Text::_('PLG_J2COMMERCE_APP_DIAGNOSTICS_MAIL_TEST_SUBJECT_B'), 'B', $headers, '-f' . $from);

        $verdict = match (true) {
            $withoutF && !$withF  => 'rejects_f',
            $withoutF && $withF   => 'both_ok',
            !$withoutF && !$withF => 'both_fail',
            default               => 'only_f',
        };

        Log::add(\sprintf('app_diagnostics mail test: without-f=%s with-f=%s', $withoutF ? 'ok' : 'fail', $withF ? 'ok' : 'fail'), Log::INFO, 'com_j2commerce');

        return [
            'success'   => true,
            'without_f' => $withoutF,
            'with_f'    => $withF,
            'verdict'   => Text::_('PLG_J2COMMERCE_APP_DIAGNOSTICS_MAIL_TEST_VERDICT_' . strtoupper($verdict)),
            'sent_to'   => $to,
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
        Log::addLogger(['text_file' => 'com_j2commerce.php'], Log::ALL, ['com_j2commerce']);

        // Retention is an administrative setting, so the configured term is the only source.
        $daysOld   = (int) J2CommerceHelper::config()->get('clear_outdated_cart_data_term', 90);
        $clearTime = $daysOld * 1440; // Convert days to minutes

        // The cart columns are stored UTC, so the cutoff has to be UTC too.
        $cutoffDate = Factory::getDate('now -' . $clearTime . ' minutes')->toSql();

        $db       = $this->getDatabase();
        $cartType = 'cart';

        // Get old cart IDs
        $query = $db->getQuery(true)
            ->select($db->quoteName('j2commerce_cart_id'))
            ->from($db->quoteName('#__j2commerce_carts'))
            ->where($db->quoteName('cart_type') . ' = :cartType')
            ->where($db->quoteName('modified_on') . ' <= :cutoff')
            ->bind(':cartType', $cartType)
            ->bind(':cutoff', $cutoffDate);

        $db->setQuery($query);
        $cartIds = $db->loadColumn();

        if (empty($cartIds)) {
            return;
        }

        // Delete cart items belonging to expired carts
        $query = $db->getQuery(true)
            ->delete($db->quoteName('#__j2commerce_cartitems'))
            ->whereIn($db->quoteName('cart_id'), $cartIds);

        try {
            $db->setQuery($query)->execute();
        } catch (\Exception $e) {
            Log::add('clear_cart: delete cartitems failed: ' . $e->getMessage(), Log::ERROR, 'com_j2commerce');
        }

        // Delete expired carts
        $query = $db->getQuery(true)
            ->delete($db->quoteName('#__j2commerce_carts'))
            ->where($db->quoteName('cart_type') . ' = :cartType')
            ->where($db->quoteName('modified_on') . ' <= :cutoff')
            ->bind(':cartType', $cartType)
            ->bind(':cutoff', $cutoffDate);

        try {
            $db->setQuery($query)->execute();
        } catch (\Exception $e) {
            Log::add('clear_cart: delete carts failed: ' . $e->getMessage(), Log::ERROR, 'com_j2commerce');
        }
    }
}
