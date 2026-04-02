<?php
/**
 * @package     J2Commerce
 * @subpackage  Plugin.J2Commerce.AppCurrencyupdater
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace J2Commerce\Plugin\J2Commerce\AppCurrencyupdater\Extension;

\defined('_JEXEC') or die;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\Http\HttpFactory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\Database\DatabaseAwareTrait;
use Joomla\Event\Event;
use Joomla\Event\SubscriberInterface;

final class AppCurrencyupdater extends CMSPlugin implements SubscriberInterface
{
    use DatabaseAwareTrait;

    protected $autoloadLanguage = true;

    private const ICON_CURRENCIES = [
        'USD', 'EUR', 'JPY', 'GBP', 'CHF', 'CNY', 'INR', 'AUD', 'CAD', 'KRW',
        'SGD', 'HKD', 'NOK', 'SEK', 'NZD', 'MXN', 'ZAR', 'BRL', 'RUB', 'TRY',
        'IDR', 'THB', 'MYR', 'PHP', 'TWD', 'AED', 'SAR', 'PLN', 'CZK', 'DKK',
    ];

    public static function getSubscribedEvents(): array
    {
        return [
            'onJ2CommerceUpdateCurrencies' => 'onUpdateCurrencies',
        ];
    }

    public function onUpdateCurrencies(Event $event): void
    {
        $result = $this->updateAllRates();
        $event->setArgument('result', $result);
    }

    public static function getCurrencyIconPath(string $currencyCode): string
    {
        $code     = strtolower($currencyCode);
        $basePath = 'media/plg_j2commerce_app_currencyupdater/images/currency';

        if (!\in_array(strtoupper($currencyCode), self::ICON_CURRENCIES, true)) {
            return '';
        }

        foreach (['webp', 'svg'] as $ext) {
            $file = JPATH_ROOT . '/' . $basePath . '/' . $code . '.' . $ext;
            if (file_exists($file)) {
                return $basePath . '/' . $code . '.' . $ext;
            }
        }

        return '';
    }

    public static function getCurrencyIconHtml(string $currencyCode, int $size = 24): string
    {
        $path = self::getCurrencyIconPath($currencyCode);
        if ($path === '') {
            return '';
        }

        $code = htmlspecialchars(strtoupper($currencyCode), ENT_QUOTES, 'UTF-8');
        $src  = htmlspecialchars($path, ENT_QUOTES, 'UTF-8');

        return '<img src="' . $src . '" alt="' . $code . '" width="' . $size . '" height="' . $size . '" class="j2commerce-currency-icon" loading="lazy">';
    }

    private function updateAllRates(): array
    {
        $result = ['updated' => 0, 'failed' => 0, 'errors' => []];

        $baseCurrency = $this->getStoreCurrency();
        if (empty($baseCurrency)) {
            $result['errors'][] = Text::_('PLG_J2COMMERCE_APP_CURRENCYUPDATER_ERROR_NO_BASE_CURRENCY');
            return $result;
        }

        $targetCodes = $this->getEnabledCurrencyCodes($baseCurrency);
        if (empty($targetCodes)) {
            $result['errors'][] = Text::_('PLG_J2COMMERCE_APP_CURRENCYUPDATER_ERROR_NO_CURRENCIES');
            return $result;
        }

        $rates = $this->fetchBulkRates($baseCurrency, $targetCodes);

        if (empty($rates)) {
            $result['failed'] = \count($targetCodes);
            return $result;
        }

        // Pin base currency — API rates are relative to base, so its own rate is not in the response
        $allRates = [$baseCurrency => 1.0];

        foreach ($targetCodes as $code) {
            if (isset($rates[$code]) && (float) $rates[$code] > 0) {
                $allRates[$code] = (float) $rates[$code];
                $result['updated']++;
            } else {
                $result['failed']++;
            }
        }

        $this->batchUpdateRates($allRates);

        return $result;
    }

    private function batchUpdateRates(array $rates): void
    {
        if (empty($rates)) {
            return;
        }

        $db = $this->getDatabase();
        $now = Factory::getDate()->toSql();
        $codes = array_keys($rates);

        $caseLines = [];
        foreach ($rates as $code => $value) {
            $caseLines[] = 'WHEN ' . $db->quote($code) . ' THEN ' . $db->quote((string) $value);
        }

        $inList = implode(',', array_map(fn(string $code) => $db->quote($code), $codes));

        $query = $db->getQuery(true)
            ->update($db->quoteName('#__j2commerce_currencies'))
            ->set($db->quoteName('currency_value') . ' = CASE ' . $db->quoteName('currency_code') . ' '
                . implode(' ', $caseLines) . ' END')
            ->set($db->quoteName('modified_on') . ' = ' . $db->quote($now))
            ->where($db->quoteName('currency_code') . ' IN (' . $inList . ')');

        try {
            $db->setQuery($query)->execute();
        } catch (\Throwable $e) {
            $this->surfaceError('database', $e->getMessage());
        }
    }

    private function getEnabledCurrencyCodes(string $baseCurrency): array
    {
        $db = $this->getDatabase();
        $query = $db->getQuery(true)
            ->select($db->quoteName('currency_code'))
            ->from($db->quoteName('#__j2commerce_currencies'))
            ->where($db->quoteName('enabled') . ' = 1')
            ->where($db->quoteName('currency_code') . ' != :base')
            ->bind(':base', $baseCurrency);

        return $db->setQuery($query)->loadColumn();
    }

    private function fetchBulkRates(string $baseCurrency, array $targetCodes): array
    {
        $apiType = $this->params->get('currency_converter_api_type', 'frankfurter');

        return match ($apiType) {
            'exchangerate_api'  => $this->fetchBulkFromExchangerateApi($baseCurrency, $targetCodes),
            'currencyapi'       => $this->fetchBulkFromCurrencyApi($baseCurrency, $targetCodes),
            'openexchangerates'   => $this->fetchBulkFromOpenExchangeRates($baseCurrency, $targetCodes),
            'exchangerate_host' => $this->fetchRatesFromEndpoint(
                "https://api.exchangerate.host/latest?base={$baseCurrency}&symbols=" . implode(',', $targetCodes),
                'ExchangeRate.host',
                'error'
            ),
            default => $this->fetchBulkFromFrankfurter($baseCurrency, $targetCodes),
        };
    }

    private function fetchBulkFromFrankfurter(string $base, array $codes): array
    {
        $source = 'Frankfurter';
        $url = "https://api.frankfurter.dev/v1/latest?from={$base}&to=" . implode(',', $codes);

        try {
            $http = $this->createHttp();
            $response = $http->get($url, [], 30);
            $statusCode = $response->getStatusCode();
            $body = (string) $response->getBody();

            // Bulk request succeeded — parse and report any missing currencies
            if ($statusCode === 200) {
                $data = json_decode($body, false, 512, JSON_THROW_ON_ERROR);

                if (!isset($data->rates) || !\is_object($data->rates)) {
                    $this->surfaceError($source, $data->message ?? 'No rates in response');
                    return [];
                }

                $rates = [];
                foreach ($data->rates as $code => $rate) {
                    $rates[(string) $code] = (float) $rate;
                }

                // Report currencies that were requested but missing from the response
                $missing = array_diff($codes, array_keys($rates));
                if (!empty($missing)) {
                    $this->getApplication()->enqueueMessage(
                        Text::sprintf(
                            'PLG_J2COMMERCE_APP_CURRENCYUPDATER_ERROR_UNSUPPORTED_CURRENCIES',
                            implode(', ', $missing)
                        ),
                        'warning'
                    );
                }

                return $rates;
            }

            // 404 means one or more currencies are not supported by Frankfurter
            if ($statusCode === 404) {
                // Single currency — report it directly as unsupported
                if (\count($codes) === 1) {
                    $this->getApplication()->enqueueMessage(
                        Text::sprintf(
                            'PLG_J2COMMERCE_APP_CURRENCYUPDATER_ERROR_UNSUPPORTED_CURRENCIES',
                            $codes[0]
                        ),
                        'warning'
                    );
                    return [];
                }

                // Multiple currencies — retry individually to salvage supported ones
                $rates = [];
                $unsupported = [];

                foreach ($codes as $code) {
                    $singleUrl = "https://api.frankfurter.dev/v1/latest?from={$base}&to={$code}";
                    $singleResponse = $http->get($singleUrl, [], 30);

                    if ($singleResponse->getStatusCode() === 200) {
                        $singleData = json_decode((string) $singleResponse->getBody(), false, 512, JSON_THROW_ON_ERROR);

                        if (isset($singleData->rates->$code)) {
                            $rates[$code] = (float) $singleData->rates->$code;
                        }
                    } else {
                        $unsupported[] = $code;
                    }
                }

                if (!empty($unsupported)) {
                    $this->getApplication()->enqueueMessage(
                        Text::sprintf(
                            'PLG_J2COMMERCE_APP_CURRENCYUPDATER_ERROR_UNSUPPORTED_CURRENCIES',
                            implode(', ', $unsupported)
                        ),
                        'warning'
                    );
                }

                return $rates;
            }

            // Other error
            $this->surfaceError($source, "HTTP {$statusCode}: " . substr($body, 0, 200));
            return [];
        } catch (\Throwable $e) {
            $this->surfaceError($source, $e->getMessage());
            return [];
        }
    }

    private function fetchRatesFromEndpoint(string $url, string $source, string $errorProperty = 'message'): array
    {
        try {
            $http = $this->createHttp();
            $response = $http->get($url, [], 30);
            $statusCode = $response->getStatusCode();
            $body = (string) $response->getBody();

            if ($statusCode !== 200) {
                $this->surfaceError($source, "HTTP {$statusCode}: " . substr($body, 0, 200));
                return [];
            }

            $data = json_decode($body, false, 512, JSON_THROW_ON_ERROR);

            if (!isset($data->rates) || !\is_object($data->rates)) {
                $error = $data->$errorProperty ?? 'No rates in response';
                $this->surfaceError($source, \is_string($error) ? $error : json_encode($error));
                return [];
            }

            $rates = [];
            foreach ($data->rates as $code => $rate) {
                $rates[(string) $code] = (float) $rate;
            }

            return $rates;
        } catch (\Throwable $e) {
            $this->surfaceError($source, $e->getMessage());
            return [];
        }
    }

    private function fetchBulkFromExchangerateApi(string $base, array $codes): array
    {
        $apiKey = $this->params->get('exchangerate_api_key', '');

        if (empty($apiKey)) {
            $this->surfaceError('ExchangeRate-API', Text::sprintf('PLG_J2COMMERCE_APP_CURRENCYUPDATER_ERROR_MISSING_API_KEY', 'ExchangeRate-API'));
            return [];
        }

        $url = "https://v6.exchangerate-api.com/v6/{$apiKey}/latest/{$base}";

        try {
            $http = $this->createHttp();
            $response = $http->get($url, [], 30);
            $statusCode = $response->getStatusCode();
            $body = (string) $response->getBody();

            if ($statusCode !== 200) {
                $this->surfaceError('ExchangeRate-API', "HTTP {$statusCode}: " . substr($body, 0, 200));
                return [];
            }

            $data = json_decode($body, false, 512, JSON_THROW_ON_ERROR);

            if (($data->result ?? '') !== 'success' || !isset($data->conversion_rates)) {
                $error = $data->{'error-type'} ?? 'Unknown error';
                $this->surfaceError('ExchangeRate-API', (string) $error);
                return [];
            }

            $rates = [];
            foreach ($codes as $code) {
                if (isset($data->conversion_rates->$code)) {
                    $rates[$code] = (float) $data->conversion_rates->$code;
                }
            }

            return $rates;
        } catch (\Throwable $e) {
            $this->surfaceError('ExchangeRate-API', $e->getMessage());
            return [];
        }
    }

    private function fetchBulkFromCurrencyApi(string $base, array $codes): array
    {
        $apiKey = $this->params->get('currencyapi_key', '');

        if (empty($apiKey)) {
            $this->surfaceError('CurrencyAPI', Text::sprintf('PLG_J2COMMERCE_APP_CURRENCYUPDATER_ERROR_MISSING_API_KEY', 'CurrencyAPI'));
            return [];
        }

        $symbols = implode(',', $codes);
        $url = "https://api.currencyapi.com/v3/latest?base_currency={$base}&currencies={$symbols}";
        $headers = ['apikey' => $apiKey];

        try {
            $http = $this->createHttp();
            $response = $http->get($url, $headers, 30);
            $statusCode = $response->getStatusCode();
            $body = (string) $response->getBody();

            if ($statusCode !== 200) {
                $this->surfaceError('CurrencyAPI', "HTTP {$statusCode}: " . substr($body, 0, 200));
                return [];
            }

            $data = json_decode($body, false, 512, JSON_THROW_ON_ERROR);

            if (!isset($data->data) || !\is_object($data->data)) {
                $error = $data->message ?? 'No data in response';
                $this->surfaceError('CurrencyAPI', (string) $error);
                return [];
            }

            $rates = [];
            foreach ($codes as $code) {
                if (isset($data->data->$code->value)) {
                    $rates[$code] = (float) $data->data->$code->value;
                }
            }

            return $rates;
        } catch (\Throwable $e) {
            $this->surfaceError('CurrencyAPI', $e->getMessage());
            return [];
        }
    }

    private function fetchBulkFromOpenExchangeRates(string $base, array $codes): array
    {
        $source = 'Open Exchange Rates';
        $appId  = $this->params->get('openexchangerates_app_id', '');

        if (empty($appId)) {
            $this->surfaceError($source, Text::sprintf('PLG_J2COMMERCE_APP_CURRENCYUPDATER_ERROR_MISSING_API_KEY', $source));
            return [];
        }

        // Always fetch USD-based rates (free plan only supports USD base)
        // Then mathematically rebase to store currency if needed
        $allCodes = $codes;
        $needsRebase = strtoupper($base) !== 'USD';

        if ($needsRebase && !\in_array($base, $allCodes, true)) {
            $allCodes[] = $base;
        }

        $symbols = implode(',', $allCodes);
        $url = "https://openexchangerates.org/api/latest.json?app_id={$appId}&symbols={$symbols}&prettyprint=0";

        try {
            $http     = $this->createHttp();
            $response = $http->get($url, [], 30);
            $status   = $response->getStatusCode();
            $body     = (string) $response->getBody();

            if ($status !== 200) {
                $decoded = json_decode($body, false);
                $msg = $decoded->description ?? ("HTTP {$status}: " . substr($body, 0, 200));
                $this->surfaceError($source, (string) $msg);
                return [];
            }

            $data = json_decode($body, false, 512, JSON_THROW_ON_ERROR);

            if (!isset($data->rates) || !\is_object($data->rates)) {
                $this->surfaceError($source, $data->description ?? 'No rates in response');
                return [];
            }

            // If store base is USD, return rates directly
            if (!$needsRebase) {
                $rates = [];
                foreach ($codes as $code) {
                    if (isset($data->rates->$code)) {
                        $rates[$code] = (float) $data->rates->$code;
                    }
                }
                return $rates;
            }

            // Rebase: divide all rates by the store currency's USD rate
            $baseRate = (float) ($data->rates->$base ?? 0);
            if ($baseRate <= 0) {
                $this->surfaceError($source, Text::sprintf(
                    'PLG_J2COMMERCE_APP_CURRENCYUPDATER_ERROR_BASE_NOT_AVAILABLE',
                    $base,
                    $source
                ));
                return [];
            }

            $rates = [];
            foreach ($codes as $code) {
                if (isset($data->rates->$code)) {
                    $rates[$code] = (float) $data->rates->$code / $baseRate;
                }
            }

            return $rates;
        } catch (\Throwable $e) {
            $this->surfaceError($source, $e->getMessage());
            return [];
        }
    }

    private function createHttp(): \Joomla\Http\Http
    {
        return (new HttpFactory())->getHttp(['userAgent' => 'J2Commerce/6.0 (+https://www.j2commerce.com)']);
    }

    private function getStoreCurrency(): string
    {
        return ComponentHelper::getParams('com_j2commerce')->get('config_currency', 'USD');
    }

    private function surfaceError(string $source, string $message): void
    {
        $this->getApplication()->enqueueMessage(
            Text::sprintf('PLG_J2COMMERCE_APP_CURRENCYUPDATER_ERROR_API_RESPONSE', $source, $message),
            'warning'
        );
    }
}
