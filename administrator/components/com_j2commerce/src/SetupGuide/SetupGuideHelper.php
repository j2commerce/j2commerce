<?php

declare(strict_types=1);

/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace J2Commerce\Component\J2commerce\Administrator\SetupGuide;

use J2Commerce\Component\J2commerce\Administrator\SetupGuide\Checks\AdminEmailCheck;
use J2Commerce\Component\J2commerce\Administrator\SetupGuide\Checks\CartPageCheck;
use J2Commerce\Component\J2commerce\Administrator\SetupGuide\Checks\CategoryMenuCheck;
use J2Commerce\Component\J2commerce\Administrator\SetupGuide\Checks\CheckoutPageCheck;
use J2Commerce\Component\J2commerce\Administrator\SetupGuide\Checks\ConfirmationPageCheck;
use J2Commerce\Component\J2commerce\Administrator\SetupGuide\Checks\ContentPluginCheck;
use J2Commerce\Component\J2commerce\Administrator\SetupGuide\Checks\CountriesCheck;
use J2Commerce\Component\J2commerce\Administrator\SetupGuide\Checks\DownloadIdCheck;
use J2Commerce\Component\J2commerce\Administrator\SetupGuide\Checks\FirstProductCheck;
use J2Commerce\Component\J2commerce\Administrator\SetupGuide\Checks\MyProfilePageCheck;
use J2Commerce\Component\J2commerce\Administrator\SetupGuide\Checks\PaymentMethodCheck;
use J2Commerce\Component\J2commerce\Administrator\SetupGuide\Checks\ShippingMethodCheck;
use J2Commerce\Component\J2commerce\Administrator\SetupGuide\Checks\StoreAddressCheck;
use J2Commerce\Component\J2commerce\Administrator\SetupGuide\Checks\StoreLogoCheck;
use J2Commerce\Component\J2commerce\Administrator\SetupGuide\Checks\SystemPluginCheck;
use J2Commerce\Component\J2commerce\Administrator\SetupGuide\Checks\TaxProfileCheck;
use J2Commerce\Component\J2commerce\Administrator\SetupGuide\Checks\TimezoneCheck;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\Event\Event;

\defined('_JEXEC') or die;

class SetupGuideHelper
{
    private static array $builtinChecks = [
        StoreAddressCheck::class,
        AdminEmailCheck::class,
        StoreLogoCheck::class,
        TimezoneCheck::class,
        SystemPluginCheck::class,
        ContentPluginCheck::class,
        DownloadIdCheck::class,
        CheckoutPageCheck::class,
        CartPageCheck::class,
        MyProfilePageCheck::class,
        ConfirmationPageCheck::class,
        CategoryMenuCheck::class,
        FirstProductCheck::class,
        PaymentMethodCheck::class,
        ShippingMethodCheck::class,
        TaxProfileCheck::class,
        CountriesCheck::class,
    ];

    /** @return SetupCheckInterface[] */
    public static function getChecks(): array
    {
        $checks = array_map(fn (string $class) => new $class(), self::$builtinChecks);

        try {
            $event = new Event('onJ2CommerceGetSetupChecks', ['checks' => $checks]);
            Factory::getApplication()->getDispatcher()->dispatch('onJ2CommerceGetSetupChecks', $event);
            $checks = $event->getArgument('checks', $checks);
        } catch (\Throwable) {
            // Plugin errors must not break the guide
        }

        return $checks;
    }

    public static function getGroupedResults(): array
    {
        $checks  = self::getChecks();
        $groups  = [];
        $passed  = 0;
        $total   = 0;

        foreach ($checks as $check) {
            $groupId = $check->getGroup();

            if (!isset($groups[$groupId])) {
                $groups[$groupId] = [
                    'id'     => $groupId,
                    'label'  => Text::_(self::getGroupLabel($groupId)),
                    'order'  => $check->getGroupOrder(),
                    'checks' => [],
                    'passed' => 0,
                    'total'  => 0,
                ];
            }

            $result    = $check->check();
            $dismissed = $check->isDismissed();
            $isPassed  = $result->status === 'pass' || $dismissed;

            $groups[$groupId]['checks'][] = [
                'id'            => $check->getId(),
                'label'         => $check->getLabel(),
                'description'   => $check->getDescription(),
                'status'        => $result->status,
                'message'       => $result->message,
                'data'          => $result->data,
                'dismissed'     => $dismissed,
                'dismissible'   => $check->isDismissible(),
                'actions'       => $check->getActions(),
                'guidedTourUid' => $check->getGuidedTourUid(),
            ];

            $groups[$groupId]['total']++;
            $total++;

            if ($isPassed) {
                $groups[$groupId]['passed']++;
                $passed++;
            }
        }

        usort($groups, fn (array $a, array $b) => $a['order'] <=> $b['order']);

        return [
            'groups'   => array_values($groups),
            'progress' => self::buildProgress($passed, $total),
        ];
    }

    public static function isComplete(): bool
    {
        foreach (self::getChecks() as $check) {
            $result = $check->check();

            if ($result->status !== 'pass' && !$check->isDismissed()) {
                return false;
            }
        }

        return true;
    }

    public static function getProgress(): array
    {
        $checks = self::getChecks();
        $passed = 0;
        $total  = \count($checks);

        foreach ($checks as $check) {
            $result = $check->check();

            if ($result->status === 'pass' || $check->isDismissed()) {
                $passed++;
            }
        }

        return self::buildProgress($passed, $total);
    }

    public static function findCheck(string $checkId): ?SetupCheckInterface
    {
        foreach (self::getChecks() as $check) {
            if ($check->getId() === $checkId) {
                return $check;
            }
        }

        return null;
    }

    public static function getGroupLabel(string $groupId): string
    {
        return match ($groupId) {
            'store_identity'      => 'COM_J2COMMERCE_SETUP_GUIDE_GROUP_STORE_IDENTITY',
            'system_requirements' => 'COM_J2COMMERCE_SETUP_GUIDE_GROUP_SYSTEM_REQUIREMENTS',
            'storefront_pages'    => 'COM_J2COMMERCE_SETUP_GUIDE_GROUP_STOREFRONT_PAGES',
            'catalog'             => 'COM_J2COMMERCE_SETUP_GUIDE_GROUP_CATALOG',
            'payments_shipping'   => 'COM_J2COMMERCE_SETUP_GUIDE_GROUP_PAYMENTS_SHIPPING',
            'tax'                 => 'COM_J2COMMERCE_SETUP_GUIDE_GROUP_TAX',
            'localization'        => 'COM_J2COMMERCE_SETUP_GUIDE_GROUP_LOCALIZATION',
            default               => $groupId,
        };
    }

    private static function buildProgress(int $passed, int $total): array
    {
        return [
            'passed'  => $passed,
            'total'   => $total,
            'percent' => $total > 0 ? round(($passed / $total) * 100, 1) : 0.0,
        ];
    }
}
