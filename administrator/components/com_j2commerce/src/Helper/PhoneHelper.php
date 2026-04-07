<?php

/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace J2Commerce\Component\J2commerce\Administrator\Helper;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Uri\Uri;
use Joomla\Database\DatabaseInterface;

class PhoneHelper
{
    private const DIAL_DATA = [
        'AF' => ['code' => '93',   'min' => 9,  'max' => 9],
        'AL' => ['code' => '355',  'min' => 3,  'max' => 9],
        'DZ' => ['code' => '213',  'min' => 8,  'max' => 9],
        'AS' => ['code' => '1',    'min' => 7,  'max' => 10],
        'AD' => ['code' => '376',  'min' => 6,  'max' => 9],
        'AO' => ['code' => '244',  'min' => 9,  'max' => 9],
        'AR' => ['code' => '54',   'min' => 10, 'max' => 10],
        'AM' => ['code' => '374',  'min' => 8,  'max' => 8],
        'AU' => ['code' => '61',   'min' => 5,  'max' => 15],
        'AT' => ['code' => '43',   'min' => 4,  'max' => 13],
        'AZ' => ['code' => '994',  'min' => 8,  'max' => 9],
        'BH' => ['code' => '973',  'min' => 8,  'max' => 8],
        'BD' => ['code' => '880',  'min' => 6,  'max' => 10],
        'BY' => ['code' => '375',  'min' => 9,  'max' => 10],
        'BE' => ['code' => '32',   'min' => 8,  'max' => 9],
        'BZ' => ['code' => '501',  'min' => 7,  'max' => 7],
        'BJ' => ['code' => '229',  'min' => 8,  'max' => 8],
        'BT' => ['code' => '975',  'min' => 7,  'max' => 8],
        'BO' => ['code' => '591',  'min' => 8,  'max' => 8],
        'BA' => ['code' => '387',  'min' => 8,  'max' => 8],
        'BW' => ['code' => '267',  'min' => 7,  'max' => 8],
        'BR' => ['code' => '55',   'min' => 10, 'max' => 10],
        'BN' => ['code' => '673',  'min' => 7,  'max' => 7],
        'BG' => ['code' => '359',  'min' => 7,  'max' => 9],
        'BF' => ['code' => '226',  'min' => 8,  'max' => 8],
        'BI' => ['code' => '257',  'min' => 8,  'max' => 8],
        'KH' => ['code' => '855',  'min' => 8,  'max' => 8],
        'CM' => ['code' => '237',  'min' => 8,  'max' => 8],
        'CA' => ['code' => '1',    'min' => 10, 'max' => 10],
        'CV' => ['code' => '238',  'min' => 7,  'max' => 7],
        'CF' => ['code' => '236',  'min' => 8,  'max' => 8],
        'TD' => ['code' => '235',  'min' => 8,  'max' => 8],
        'CL' => ['code' => '56',   'min' => 8,  'max' => 9],
        'CN' => ['code' => '86',   'min' => 5,  'max' => 12],
        'CO' => ['code' => '57',   'min' => 8,  'max' => 10],
        'KM' => ['code' => '269',  'min' => 7,  'max' => 7],
        'CG' => ['code' => '242',  'min' => 9,  'max' => 9],
        'CR' => ['code' => '506',  'min' => 8,  'max' => 8],
        'HR' => ['code' => '385',  'min' => 8,  'max' => 12],
        'CU' => ['code' => '53',   'min' => 6,  'max' => 8],
        'CY' => ['code' => '357',  'min' => 8,  'max' => 11],
        'CZ' => ['code' => '420',  'min' => 4,  'max' => 12],
        'DK' => ['code' => '45',   'min' => 8,  'max' => 8],
        'DJ' => ['code' => '253',  'min' => 6,  'max' => 6],
        'DO' => ['code' => '1',    'min' => 7,  'max' => 10],
        'EC' => ['code' => '593',  'min' => 8,  'max' => 8],
        'EG' => ['code' => '20',   'min' => 7,  'max' => 9],
        'SV' => ['code' => '503',  'min' => 7,  'max' => 8],
        'GQ' => ['code' => '240',  'min' => 9,  'max' => 9],
        'ER' => ['code' => '291',  'min' => 7,  'max' => 7],
        'EE' => ['code' => '372',  'min' => 7,  'max' => 10],
        'ET' => ['code' => '251',  'min' => 9,  'max' => 9],
        'FJ' => ['code' => '679',  'min' => 7,  'max' => 7],
        'FI' => ['code' => '358',  'min' => 5,  'max' => 12],
        'FR' => ['code' => '33',   'min' => 9,  'max' => 9],
        'GA' => ['code' => '241',  'min' => 6,  'max' => 7],
        'GM' => ['code' => '220',  'min' => 7,  'max' => 7],
        'GE' => ['code' => '995',  'min' => 9,  'max' => 9],
        'DE' => ['code' => '49',   'min' => 6,  'max' => 13],
        'GH' => ['code' => '233',  'min' => 5,  'max' => 9],
        'GI' => ['code' => '350',  'min' => 8,  'max' => 8],
        'GR' => ['code' => '30',   'min' => 10, 'max' => 10],
        'GL' => ['code' => '299',  'min' => 6,  'max' => 6],
        'GT' => ['code' => '502',  'min' => 8,  'max' => 8],
        'GN' => ['code' => '224',  'min' => 8,  'max' => 8],
        'GW' => ['code' => '245',  'min' => 7,  'max' => 7],
        'GY' => ['code' => '592',  'min' => 7,  'max' => 7],
        'HT' => ['code' => '509',  'min' => 8,  'max' => 8],
        'HN' => ['code' => '504',  'min' => 8,  'max' => 8],
        'HK' => ['code' => '852',  'min' => 4,  'max' => 9],
        'HU' => ['code' => '36',   'min' => 8,  'max' => 9],
        'IS' => ['code' => '354',  'min' => 7,  'max' => 9],
        'IN' => ['code' => '91',   'min' => 7,  'max' => 10],
        'ID' => ['code' => '62',   'min' => 5,  'max' => 10],
        'IR' => ['code' => '98',   'min' => 6,  'max' => 10],
        'IQ' => ['code' => '964',  'min' => 8,  'max' => 10],
        'IE' => ['code' => '353',  'min' => 7,  'max' => 11],
        'IL' => ['code' => '972',  'min' => 8,  'max' => 9],
        'IT' => ['code' => '39',   'min' => 6,  'max' => 11],
        'JM' => ['code' => '1',    'min' => 7,  'max' => 10],
        'JP' => ['code' => '81',   'min' => 5,  'max' => 13],
        'JO' => ['code' => '962',  'min' => 5,  'max' => 9],
        'KZ' => ['code' => '7',    'min' => 10, 'max' => 10],
        'KE' => ['code' => '254',  'min' => 6,  'max' => 10],
        'KR' => ['code' => '82',   'min' => 8,  'max' => 11],
        'KW' => ['code' => '965',  'min' => 7,  'max' => 8],
        'KG' => ['code' => '996',  'min' => 9,  'max' => 9],
        'LA' => ['code' => '856',  'min' => 8,  'max' => 10],
        'LV' => ['code' => '371',  'min' => 7,  'max' => 8],
        'LB' => ['code' => '961',  'min' => 7,  'max' => 8],
        'LS' => ['code' => '266',  'min' => 8,  'max' => 8],
        'LR' => ['code' => '231',  'min' => 7,  'max' => 8],
        'LY' => ['code' => '218',  'min' => 8,  'max' => 9],
        'LI' => ['code' => '423',  'min' => 7,  'max' => 9],
        'LT' => ['code' => '370',  'min' => 8,  'max' => 8],
        'LU' => ['code' => '352',  'min' => 4,  'max' => 11],
        'MO' => ['code' => '853',  'min' => 7,  'max' => 8],
        'MG' => ['code' => '261',  'min' => 9,  'max' => 10],
        'MW' => ['code' => '265',  'min' => 7,  'max' => 8],
        'MY' => ['code' => '60',   'min' => 7,  'max' => 9],
        'MV' => ['code' => '960',  'min' => 7,  'max' => 7],
        'ML' => ['code' => '223',  'min' => 8,  'max' => 8],
        'MT' => ['code' => '356',  'min' => 8,  'max' => 8],
        'MR' => ['code' => '222',  'min' => 7,  'max' => 7],
        'MU' => ['code' => '230',  'min' => 7,  'max' => 7],
        'MX' => ['code' => '52',   'min' => 10, 'max' => 10],
        'MD' => ['code' => '373',  'min' => 8,  'max' => 8],
        'MC' => ['code' => '377',  'min' => 5,  'max' => 9],
        'MN' => ['code' => '976',  'min' => 7,  'max' => 8],
        'ME' => ['code' => '382',  'min' => 4,  'max' => 12],
        'MA' => ['code' => '212',  'min' => 9,  'max' => 9],
        'MZ' => ['code' => '258',  'min' => 8,  'max' => 9],
        'MM' => ['code' => '95',   'min' => 7,  'max' => 9],
        'NA' => ['code' => '264',  'min' => 6,  'max' => 10],
        'NP' => ['code' => '977',  'min' => 8,  'max' => 9],
        'NL' => ['code' => '31',   'min' => 9,  'max' => 9],
        'NZ' => ['code' => '64',   'min' => 3,  'max' => 10],
        'NI' => ['code' => '505',  'min' => 8,  'max' => 8],
        'NE' => ['code' => '227',  'min' => 8,  'max' => 8],
        'NG' => ['code' => '234',  'min' => 7,  'max' => 10],
        'MK' => ['code' => '389',  'min' => 8,  'max' => 8],
        'NO' => ['code' => '47',   'min' => 5,  'max' => 8],
        'OM' => ['code' => '968',  'min' => 7,  'max' => 8],
        'PK' => ['code' => '92',   'min' => 8,  'max' => 11],
        'PA' => ['code' => '507',  'min' => 7,  'max' => 8],
        'PG' => ['code' => '675',  'min' => 4,  'max' => 11],
        'PY' => ['code' => '595',  'min' => 5,  'max' => 9],
        'PE' => ['code' => '51',   'min' => 8,  'max' => 11],
        'PH' => ['code' => '63',   'min' => 8,  'max' => 10],
        'PL' => ['code' => '48',   'min' => 6,  'max' => 9],
        'PT' => ['code' => '351',  'min' => 9,  'max' => 11],
        'QA' => ['code' => '974',  'min' => 3,  'max' => 8],
        'RO' => ['code' => '40',   'min' => 9,  'max' => 9],
        'RU' => ['code' => '7',    'min' => 10, 'max' => 10],
        'RW' => ['code' => '250',  'min' => 9,  'max' => 9],
        'SA' => ['code' => '966',  'min' => 8,  'max' => 9],
        'SN' => ['code' => '221',  'min' => 9,  'max' => 9],
        'RS' => ['code' => '381',  'min' => 4,  'max' => 12],
        'SC' => ['code' => '248',  'min' => 7,  'max' => 7],
        'SL' => ['code' => '232',  'min' => 8,  'max' => 8],
        'SG' => ['code' => '65',   'min' => 8,  'max' => 12],
        'SK' => ['code' => '421',  'min' => 4,  'max' => 9],
        'SI' => ['code' => '386',  'min' => 8,  'max' => 8],
        'SO' => ['code' => '252',  'min' => 5,  'max' => 8],
        'ZA' => ['code' => '27',   'min' => 9,  'max' => 9],
        'ES' => ['code' => '34',   'min' => 9,  'max' => 9],
        'LK' => ['code' => '94',   'min' => 9,  'max' => 9],
        'SD' => ['code' => '249',  'min' => 9,  'max' => 9],
        'SR' => ['code' => '597',  'min' => 6,  'max' => 7],
        'SE' => ['code' => '46',   'min' => 7,  'max' => 13],
        'CH' => ['code' => '41',   'min' => 4,  'max' => 12],
        'SY' => ['code' => '963',  'min' => 8,  'max' => 10],
        'TW' => ['code' => '886',  'min' => 8,  'max' => 9],
        'TJ' => ['code' => '992',  'min' => 9,  'max' => 9],
        'TZ' => ['code' => '255',  'min' => 9,  'max' => 9],
        'TH' => ['code' => '66',   'min' => 8,  'max' => 9],
        'TG' => ['code' => '228',  'min' => 8,  'max' => 8],
        'TO' => ['code' => '676',  'min' => 5,  'max' => 7],
        'TT' => ['code' => '1',    'min' => 7,  'max' => 10],
        'TN' => ['code' => '216',  'min' => 8,  'max' => 8],
        'TR' => ['code' => '90',   'min' => 10, 'max' => 10],
        'TM' => ['code' => '993',  'min' => 8,  'max' => 8],
        'UG' => ['code' => '256',  'min' => 9,  'max' => 9],
        'UA' => ['code' => '380',  'min' => 9,  'max' => 9],
        'AE' => ['code' => '971',  'min' => 8,  'max' => 9],
        'GB' => ['code' => '44',   'min' => 7,  'max' => 11],
        'US' => ['code' => '1',    'min' => 10, 'max' => 10],
        'UY' => ['code' => '598',  'min' => 4,  'max' => 11],
        'UZ' => ['code' => '998',  'min' => 9,  'max' => 9],
        'VU' => ['code' => '678',  'min' => 5,  'max' => 7],
        'VE' => ['code' => '58',   'min' => 10, 'max' => 10],
        'VN' => ['code' => '84',   'min' => 7,  'max' => 10],
        'YE' => ['code' => '967',  'min' => 6,  'max' => 9],
        'ZM' => ['code' => '260',  'min' => 9,  'max' => 9],
        'ZW' => ['code' => '263',  'min' => 5,  'max' => 10],
        // NANP territories (all +1)
        'AG' => ['code' => '1',    'min' => 7,  'max' => 10],
        'AI' => ['code' => '1',    'min' => 7,  'max' => 10],
        'BB' => ['code' => '1',    'min' => 7,  'max' => 10],
        'BM' => ['code' => '1',    'min' => 7,  'max' => 10],
        'BS' => ['code' => '1',    'min' => 7,  'max' => 10],
        'DM' => ['code' => '1',    'min' => 7,  'max' => 10],
        'GD' => ['code' => '1',    'min' => 7,  'max' => 10],
        'GU' => ['code' => '1',    'min' => 7,  'max' => 10],
        'KN' => ['code' => '1',    'min' => 7,  'max' => 10],
        'KY' => ['code' => '1',    'min' => 7,  'max' => 10],
        'LC' => ['code' => '1',    'min' => 7,  'max' => 10],
        'MP' => ['code' => '1',    'min' => 7,  'max' => 10],
        'MS' => ['code' => '1',    'min' => 7,  'max' => 10],
        'PR' => ['code' => '1',    'min' => 7,  'max' => 10],
        'SX' => ['code' => '1',    'min' => 7,  'max' => 10],
        'TC' => ['code' => '1',    'min' => 7,  'max' => 10],
        'VC' => ['code' => '1',    'min' => 7,  'max' => 10],
        'VG' => ['code' => '1',    'min' => 7,  'max' => 10],
        'VI' => ['code' => '1',    'min' => 7,  'max' => 10],
    ];

    private const CONTINENT_MAP = [
        'Africa' => [
            'DZ','AO','BJ','BW','BF','BI','CM','CV','CF','TD','KM','CD','CG','CI','DJ','EG',
            'GQ','ER','ET','GA','GM','GH','GN','GW','KE','LS','LR','LY','MG','MW','ML','MR',
            'MU','MA','MZ','NA','NE','NG','RW','SN','SC','SL','SO','ZA','SD','TZ','TG','TN',
            'UG','ZM','ZW',
        ],
        'Americas' => [
            'AG','AI','AR','BB','BS','BZ','BM','BO','BR','CA','CL','CO','CR','CU','DM','DO',
            'EC','SV','GD','GT','GU','GY','HT','HN','JM','MX','KN','KY','LC','MP','MS','NI',
            'PA','PY','PE','PR','SX','TC','TT','US','UY','VE','VC','VG','VI',
        ],
        'Asia' => [
            'AF','AM','AZ','BH','BD','BT','BN','KH','CN','GE','HK','IN','ID','IR','IQ','IL',
            'JP','JO','KZ','KR','KW','KG','LA','LB','MO','MY','MV','MN','MM','NP','OM','PK',
            'PH','QA','SA','SG','LK','SY','TW','TJ','TH','TM','AE','UZ','VN','YE',
        ],
        'Europe' => [
            'AL','AD','AT','BY','BE','BA','BG','HR','CY','CZ','DK','EE','FI','FR','DE','GI',
            'GL','GR','HU','IS','IE','IT','LV','LI','LT','LU','MT','MD','MC','ME','MK','NL',
            'NO','PL','PT','RO','RU','RS','SK','SI','ES','SE','CH','TR','UA','GB',
        ],
        'Oceania' => [
            'AU','FJ','KI','MH','FM','NR','NZ','PW','PG','WS','SB','TO','TV','VU',
        ],
    ];

    public static function getContinentMap(): array
    {
        return self::CONTINENT_MAP;
    }

    public static function getDialData(): array
    {
        return self::DIAL_DATA;
    }

    public static function getDialCode(string $iso2): ?string
    {
        return self::DIAL_DATA[strtoupper($iso2)]['code'] ?? null;
    }

    public static function getNationalLengths(string $iso2): array
    {
        $data = self::DIAL_DATA[strtoupper($iso2)] ?? null;

        return $data ? ['min' => $data['min'], 'max' => $data['max']] : ['min' => 1, 'max' => 15];
    }

    public static function toE164(string $iso2, string $national): string
    {
        $code = self::DIAL_DATA[strtoupper($iso2)]['code'] ?? '';
        return '+' . $code . preg_replace('/\D/', '', $national);
    }

    /**
     * Normalize a raw phone string by stripping common separators (space, dash,
     * paren, dot, non-breaking space) while preserving a leading + if present.
     *
     * Does NOT force E.164 formatting: legacy values entered without a country
     * code (e.g. "555-555-0000" or "0113 2667042") are preserved as clean
     * digit strings so the frontend parser can handle them with the correct
     * address-country context.
     */
    public static function normalize(string $raw): string
    {
        $raw = trim($raw);
        if ($raw === '') {
            return '';
        }

        $hasPlus = str_starts_with($raw, '+');
        $digits  = preg_replace('/\D/', '', $raw);

        if ($digits === '') {
            return '';
        }

        return $hasPlus ? '+' . $digits : $digits;
    }

    public static function parseE164(string $e164, string $preferredIso = 'US'): array
    {
        $preferredIso = strtoupper($preferredIso);

        if (!str_starts_with($e164, '+')) {
            // Legacy value (typically saved from admin with separators). Strip
            // non-digits and treat as a national number under the preferred
            // country. Caller is responsible for passing the address's
            // country_id ISO2 as $preferredIso so the number is interpreted
            // correctly.
            return [
                'iso2'     => $preferredIso,
                'code'     => self::DIAL_DATA[$preferredIso]['code'] ?? '1',
                'national' => preg_replace('/\D/', '', $e164),
            ];
        }

        $digits = substr($e164, 1);

        // Try preferred country first to resolve shared-code ambiguity (e.g. +1 = US/CA/JM)
        $preferredCode = self::DIAL_DATA[$preferredIso]['code'] ?? '';
        if ($preferredCode !== '' && str_starts_with($digits, $preferredCode)) {
            return [
                'iso2'     => $preferredIso,
                'code'     => $preferredCode,
                'national' => substr($digits, \strlen($preferredCode)),
            ];
        }

        // Longest prefix match (3 → 2 → 1 digits)
        for ($len = 3; $len >= 1; $len--) {
            $prefix = substr($digits, 0, $len);
            foreach (self::DIAL_DATA as $iso => $data) {
                if ($data['code'] === $prefix) {
                    return [
                        'iso2'     => $iso,
                        'code'     => $prefix,
                        'national' => substr($digits, $len),
                    ];
                }
            }
        }

        return ['iso2' => $preferredIso, 'code' => '', 'national' => $digits];
    }

    /**
     * @param  string[]|null  $allowedIso2  When provided, only return countries whose ISO2 code is in this list.
     */
    public static function getCountryListForDropdown(?array $allowedIso2 = null): array
    {
        $db    = Factory::getContainer()->get(DatabaseInterface::class);
        $query = $db->getQuery(true)
            ->select($db->quoteName(['country_name', 'country_isocode_2']))
            ->from($db->quoteName('#__j2commerce_countries'))
            ->where($db->quoteName('enabled') . ' = 1')
            ->order($db->quoteName('country_name') . ' ASC');

        $db->setQuery($query);
        $countries = $db->loadObjectList();

        $filter = $allowedIso2 !== null ? array_flip(array_map('strtoupper', $allowedIso2)) : null;

        $result = [];
        foreach ($countries as $country) {
            $iso  = $country->country_isocode_2;
            $dial = self::DIAL_DATA[$iso] ?? null;
            if (!$dial) {
                continue;
            }
            if ($filter !== null && !isset($filter[$iso])) {
                continue;
            }
            $result[] = [
                'iso2'    => $iso,
                'name'    => $country->country_name,
                'code'    => $dial['code'],
                'min'     => $dial['min'],
                'max'     => $dial['max'],
                'flagUrl' => self::getFlagUrl($iso),
            ];
        }

        return $result;
    }

    public static function isoToEmoji(string $iso2): string
    {
        $iso2 = strtoupper($iso2);
        $flag = '';
        for ($i = 0; $i < 2; $i++) {
            $flag .= mb_chr(0x1F1E6 + \ord($iso2[$i]) - \ord('A'));
        }
        return $flag;
    }

    private const FLAG_MAP = [
        'AF' => 'af', 'AL' => 'al', 'DZ' => 'ar', 'AR' => 'ar', 'AM' => 'hy',
        'AU' => 'en_au', 'AT' => 'at', 'AZ' => 'az', 'BD' => 'bn', 'BY' => 'be',
        'BE' => 'belg', 'BA' => 'bs', 'BR' => 'br', 'BG' => 'bg', 'KH' => 'km',
        'CA' => 'ca', 'CL' => 'es_co', 'CN' => 'zh', 'CO' => 'es_co', 'HR' => 'hr',
        'CY' => 'cy', 'CZ' => 'cz', 'DK' => 'dk', 'EE' => 'et', 'FI' => 'fi',
        'FR' => 'fr', 'GE' => 'ka', 'DE' => 'de', 'GR' => 'el', 'HK' => 'hk',
        'HU' => 'hu', 'IS' => 'is', 'IN' => 'hi', 'ID' => 'id', 'IR' => 'fa',
        'IQ' => 'ku', 'IE' => 'ga_ie', 'IL' => 'he', 'IT' => 'it', 'JP' => 'ja',
        'KZ' => 'kk_kz', 'KR' => 'ko', 'LA' => 'lo', 'LV' => 'lv', 'LT' => 'lt',
        'MK' => 'mk', 'MY' => 'ms_my', 'MN' => 'mn', 'NL' => 'nl', 'NZ' => 'en_nz',
        'NO' => 'no', 'PK' => 'ur', 'PH' => 'fil_ph', 'PL' => 'pl', 'PT' => 'pt',
        'RO' => 'ro', 'RU' => 'ru', 'SA' => 'ar_aa', 'RS' => 'sr', 'SK' => 'sk',
        'SI' => 'sl', 'ZA' => 'af_za', 'ES' => 'es', 'LK' => 'si', 'SE' => 'sv',
        'CH' => 'ch', 'SY' => 'sy', 'TW' => 'tw', 'TH' => 'th', 'TR' => 'tr',
        'UA' => 'uk', 'GB' => 'en_gb', 'US' => 'us', 'UZ' => 'uz', 'VN' => 'vi',
    ];

    public static function getFlagUrl(string $iso2): string
    {
        $iso2 = strtoupper($iso2);
        $file = self::FLAG_MAP[$iso2] ?? strtolower($iso2);
        $path = JPATH_ROOT . '/media/mod_languages/images/' . $file . '.gif';

        if (file_exists($path)) {
            return Uri::root() . 'media/mod_languages/images/' . $file . '.gif';
        }

        return '';
    }
}
