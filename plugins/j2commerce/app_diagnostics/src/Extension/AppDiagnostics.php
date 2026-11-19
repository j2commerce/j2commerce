<?php

/**
 * @package     J2Commerce
 * @subpackage  plg_j2commerce_app_diagnostics
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace J2Commerce\Plugin\J2Commerce\AppDiagnostics\Extension;

use J2Commerce\Component\J2commerce\Administrator\Helper\CartHelper;
use J2Commerce\Component\J2commerce\Administrator\Helper\J2CommerceHelper;
use J2Commerce\Component\J2commerce\Administrator\Helper\OrderStatusHelper;
use J2Commerce\Component\J2commerce\Administrator\Helper\PaymentRuleHelper;
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
use Joomla\Database\ParameterType;
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

    private const CART_GC_BATCH_SIZE = 1000;

    /** Seconds; kept well under common max_execution_time and proxy timeouts. */
    private const CART_GC_TIME_BUDGET = 20;

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
     * History notes written by the Require Payment for Confirmed setting over the last 30 days,
     * grouped by the order's payment method. Markers live in orderhistories.params as
     * "payment_rule":"would_park" | "parked" | "override".
     */
    public function getPaymentRuleInfo(): array
    {
        $db        = $this->getDatabase();
        $since     = Factory::getDate('now -30 days')->toSql();
        $wouldPark = '%"payment_rule":"would_park"%';
        $parked    = '%"payment_rule":"parked"%';
        $override  = '%"payment_rule":"override"%';
        $anyMarker = '%"payment_rule"%';

        $query = $db->getQuery(true)
            ->select([
                $db->quoteName('o.orderpayment_type', 'method'),
                'SUM(' . $db->quoteName('h.params') . ' LIKE :wouldPark) AS ' . $db->quoteName('would_park'),
                'SUM(' . $db->quoteName('h.params') . ' LIKE :parked) AS ' . $db->quoteName('parked'),
                'SUM(' . $db->quoteName('h.params') . ' LIKE :override) AS ' . $db->quoteName('override'),
            ])
            ->from($db->quoteName('#__j2commerce_orderhistories', 'h'))
            ->join('INNER', $db->quoteName('#__j2commerce_orders', 'o') . ' ON ' . $db->quoteName('o.order_id') . ' = ' . $db->quoteName('h.order_id'))
            ->where($db->quoteName('h.created_on') . ' >= :since')
            ->where($db->quoteName('h.params') . ' LIKE :anyMarker')
            ->group($db->quoteName('o.orderpayment_type'))
            ->order($db->quoteName('o.orderpayment_type'))
            ->bind(':wouldPark', $wouldPark)
            ->bind(':parked', $parked)
            ->bind(':override', $override)
            ->bind(':since', $since)
            ->bind(':anyMarker', $anyMarker);

        $rows = [];

        foreach ($db->setQuery($query)->loadObjectList() ?: [] as $row) {
            $rows[] = [
                'method'     => (string) $row->method,
                'would_park' => (int) $row->would_park,
                'parked'     => (int) $row->parked,
                'override'   => (int) $row->override,
            ];
        }

        return ['mode' => PaymentRuleHelper::mode(), 'rows' => $rows];
    }

    /**
     * Order types a payment setting should point at, inferred from the words in its name;
     * null when the name carries none of them. Order matters: "refund_order_status" is a
     * refund setting even though a status is what it holds.
     */
    public static function expectedStatusTypes(string $param): ?array
    {
        $tokens = explode('_', strtolower($param));
        $has    = static fn (string ...$words): bool => array_intersect($tokens, $words) !== [];

        return match (true) {
            $has('refund', 'refunds', 'refunded')                     => ['refunded'],
            $has('cancel', 'cancelled', 'canceled', 'void', 'voided') => ['cancelled'],
            $has('failed', 'fail', 'declined', 'decline')             => ['failed'],
            $has('pending')                                           => ['open', 'new'],
            $has('authorized', 'authorised')                          => ['open', 'approved'],
            $param === 'payment_status' || $has('paid', 'confirmed')  => ['complete'],
            default                                                   => null,
        };
    }

    /**
     * Every saved order-status value on every installed payment plugin, enabled or not. Primary
     * source is the manifest's Orderstatus fields; saved params ending "_status" that the
     * manifest does not declare are the fallback. Read-only.
     */
    public function collectPaymentStatusSettings(): array
    {
        $db     = $this->getDatabase();
        $type   = 'plugin';
        $folder = 'j2commerce';
        $like   = 'payment\\_%';
        $query  = $db->getQuery(true)
            ->select($db->quoteName(['name', 'element', 'enabled', 'params']))
            ->from($db->quoteName('#__extensions'))
            ->where($db->quoteName('type') . ' = :type')
            ->where($db->quoteName('folder') . ' = :folder')
            ->where($db->quoteName('element') . ' LIKE :like')
            ->order($db->quoteName('element'))
            ->bind(':type', $type)
            ->bind(':folder', $folder)
            ->bind(':like', $like);

        $settings = [];

        foreach ($db->setQuery($query)->loadObjectList() ?: [] as $plugin) {
            $saved    = json_decode((string) $plugin->params, true);
            $saved    = \is_array($saved) ? $saved : [];
            $declared = [];

            foreach ($this->readManifestFields((string) $plugin->element) as $field) {
                $declared[$field['key']] = true;

                if ($field['type'] === 'orderstatus') {
                    $this->pushStatusValues($settings, $plugin, $field['key'], $field['label'], $saved);
                }
            }

            foreach (array_keys($saved) as $key) {
                $key = (string) $key;

                // *change_status is a Yes/No switch (e.g. refund_change_status), not a status id.
                if (isset($declared[$key]) || !str_ends_with($key, '_status') || str_ends_with($key, 'change_status')) {
                    continue;
                }

                $this->pushStatusValues($settings, $plugin, $key, '', $saved);
            }
        }

        return $settings;
    }

    private function readManifestFields(string $element): array
    {
        $file = JPATH_PLUGINS . '/j2commerce/' . $element . '/' . $element . '.xml';

        if (!is_file($file)) {
            return [];
        }

        $previous = libxml_use_internal_errors(true);
        $xml      = simplexml_load_file($file);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if ($xml === false) {
            return [];
        }

        $fields = [];

        foreach ($xml->xpath('//field[@name]') ?: [] as $node) {
            $path = [];

            foreach ($node->xpath('ancestor::fields[@name]') ?: [] as $group) {
                if ((string) $group['name'] !== 'params') {
                    $path[] = (string) $group['name'];
                }
            }

            $path[]   = (string) $node['name'];
            $fields[] = [
                'key'   => implode('.', $path),
                'type'  => strtolower((string) $node['type']),
                'label' => (string) $node['label'],
            ];
        }

        return $fields;
    }

    private function pushStatusValues(array &$settings, object $plugin, string $key, string $label, array $saved): void
    {
        $value = $saved;

        foreach (explode('.', $key) as $part) {
            if (!\is_array($value) || !\array_key_exists($part, $value)) {
                return;
            }

            $value = $value[$part];
        }

        foreach ((array) $value as $candidate) {
            if (!\is_int($candidate) && !(\is_string($candidate) && ctype_digit($candidate))) {
                continue;
            }

            if ((int) $candidate > 0) {
                $settings[] = [
                    'plugin'  => (string) $plugin->name,
                    'element' => (string) $plugin->element,
                    'enabled' => (bool) $plugin->enabled,
                    'param'   => $key,
                    'label'   => $label,
                    'value'   => (int) $candidate,
                ];
            }
        }
    }

    /**
     * Pure check of collected settings against the order status table, so it can be fed a
     * planted params set. $typeOf resolves a status id to its type; default is the core helper.
     * $statuses is keyed by status id.
     */
    public function evaluatePaymentStatusSettings(array $settings, array $statuses, ?callable $typeOf = null): array
    {
        $typeOf ??= OrderStatusHelper::getType(...);
        $found    = [];

        foreach ($settings as $setting) {
            $id       = (int) $setting['value'];
            $expected = self::expectedStatusTypes((string) $setting['param']);
            $base     = $setting + ['status_name' => '', 'actual_type' => null, 'expected' => $expected ?? []];

            if (!isset($statuses[$id])) {
                $found[] = ['severity' => 'warning', 'problem' => 'missing'] + $base;

                continue;
            }

            $base['status_name'] = (string) $statuses[$id]['name'];
            $base['actual_type'] = $typeOf($id);

            if ((int) $statuses[$id]['enabled'] !== 1) {
                $found[] = ['severity' => 'notice', 'problem' => 'disabled'] + $base;
            }

            if ($expected === null) {
                $found[] = ['severity' => 'info', 'problem' => 'unclassified'] + $base;
            } elseif (!\in_array($base['actual_type'], $expected, true)) {
                $found[] = ['severity' => 'warning', 'problem' => 'wrong_type'] + $base;
            }
        }

        return $found;
    }

    public function getPaymentStatusInfo(): array
    {
        $db    = $this->getDatabase();
        $query = $db->getQuery(true)
            ->select($db->quoteName(['j2commerce_orderstatus_id', 'orderstatus_name', 'enabled']))
            ->from($db->quoteName('#__j2commerce_orderstatuses'));

        $statuses = [];

        foreach ($db->setQuery($query)->loadObjectList() ?: [] as $row) {
            $statuses[(int) $row->j2commerce_orderstatus_id] = ['name' => (string) $row->orderstatus_name, 'enabled' => (int) $row->enabled];
        }

        $settings = $this->collectPaymentStatusSettings();

        return [
            'settings' => \count($settings),
            'plugins'  => \count(array_unique(array_column($settings, 'element'))),
            'findings' => $this->evaluatePaymentStatusSettings($settings, $statuses),
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
        $deadline = microtime(true) + self::CART_GC_TIME_BUDGET;
        $lastId   = 0;
        $cleared  = 0;

        // Bounded batches under a time budget: a backlog of hundreds of thousands of carts
        // otherwise becomes one IN() list past the placeholder limit and one unbounded DELETE,
        // the request is killed mid-run, and a scheduler task left locked holds every other task.
        // Whatever the budget does not reach is picked up on the next run.
        do {
            $query = $db->getQuery(true)
                ->select($db->quoteName('j2commerce_cart_id'))
                ->from($db->quoteName('#__j2commerce_carts'))
                ->where($db->quoteName('j2commerce_cart_id') . ' > :lastId')
                ->where($db->quoteName('cart_type') . ' = :cartType')
                ->where($db->quoteName('modified_on') . ' <= :cutoff')
                ->order($db->quoteName('j2commerce_cart_id') . ' ASC')
                ->setLimit(self::CART_GC_BATCH_SIZE)
                ->bind(':lastId', $lastId, ParameterType::INTEGER)
                ->bind(':cartType', $cartType)
                ->bind(':cutoff', $cutoffDate);

            try {
                $cartIds = array_map('intval', $db->setQuery($query)->loadColumn());

                if ($cartIds === []) {
                    break;
                }

                $this->deleteCartBatch($cartIds);
            } catch (\Exception $e) {
                Log::add('clear_cart: batch failed: ' . $e->getMessage(), Log::ERROR, 'com_j2commerce');
                break;
            }

            $lastId = end($cartIds);
            $cleared += \count($cartIds);
        } while (\count($cartIds) === self::CART_GC_BATCH_SIZE && microtime(true) < $deadline);

        if ($cleared > 0) {
            Log::add(\sprintf('clear_cart: cleared %d outdated cart(s).', $cleared), Log::INFO, 'com_j2commerce');
            CartHelper::flushCartCounts();
        }
    }

    /** @param int[] $cartIds */
    private function deleteCartBatch(array $cartIds): void
    {
        $db = $this->getDatabase();

        $db->transactionStart();

        try {
            $db->setQuery(
                $db->getQuery(true)
                    ->delete($db->quoteName('#__j2commerce_cartitems'))
                    ->whereIn($db->quoteName('cart_id'), $cartIds)
            )->execute();

            $db->setQuery(
                $db->getQuery(true)
                    ->delete($db->quoteName('#__j2commerce_carts'))
                    ->whereIn($db->quoteName('j2commerce_cart_id'), $cartIds)
            )->execute();

            $db->transactionCommit();
        } catch (\Exception $e) {
            $db->transactionRollback();

            throw $e;
        }
    }
}
