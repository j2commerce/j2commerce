<?php

/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace J2Commerce\Component\J2commerce\Site\Helper;

use J2Commerce\Component\J2commerce\Administrator\Helper\J2CommerceHelper;
use J2Commerce\Component\J2commerce\Site\Event\CheckoutStepInterface;
use Joomla\CMS\Language\Text;

\defined('_JEXEC') or die;

class CheckoutStepsHelper
{
    private const POSITION_ORDER = [
        'after_billing'  => 10,
        'after_shipping' => 20,
        'before_payment' => 30,
        'before_confirm' => 40,
    ];

    public static function getSteps(array $context = []): array
    {
        $event = J2CommerceHelper::plugin()->event('GetCheckoutSteps', [
            'result'  => [],
            'context' => $context,
        ]);

        $steps = array_filter(
            $event->getArgument('result', []),
            static fn ($step) => $step instanceof CheckoutStepInterface && $step->shouldShow($context)
        );

        usort($steps, static function (CheckoutStepInterface $a, CheckoutStepInterface $b): int {
            $posA = self::POSITION_ORDER[$a->getStepPosition()] ?? 50;
            $posB = self::POSITION_ORDER[$b->getStepPosition()] ?? 50;

            return $posA !== $posB ? $posA <=> $posB : $a->getStepPriority() <=> $b->getStepPriority();
        });

        return $steps;
    }

    public static function getStepsForPosition(string $position, array $context = []): array
    {
        return array_values(array_filter(
            self::getSteps($context),
            static fn (CheckoutStepInterface $step) => $step->getStepPosition() === $position
        ));
    }

    public static function renderSteps(string $position, array $context = []): string
    {
        $html = '';

        foreach (self::getStepsForPosition($position, $context) as $step) {
            $html .= $step->render($context);
        }

        return $html;
    }

    public static function getHeading(string $position, array $context = []): string
    {
        $steps = self::getStepsForPosition($position, $context);

        if (\count($steps) === 1) {
            return Text::_($steps[0]->getStepName());
        }

        if (\count($steps) > 1) {
            return Text::_('COM_J2COMMERCE_CHECKOUT_ADDITIONAL_OPTIONS');
        }

        return '';
    }
}
