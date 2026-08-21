<?php

/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

defined('_JEXEC') or die;

use J2Commerce\Component\J2commerce\Administrator\Helper\ConfigHelper;
use J2Commerce\Component\J2commerce\Administrator\Helper\CurrencyHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\Database\ParameterType;

$app  = Factory::getApplication();
$user = $app->getIdentity();

// Greeting based on time of day (using Joomla global config timezone)
$tz   = new DateTimeZone($app->get('offset', 'UTC'));
$now  = new DateTime('now', $tz);
$hour = (int) $now->format('G');

if ($hour >= 5 && $hour < 12) {
    $greeting     = Text::_('COM_J2COMMERCE_GREETING_MORNING');
    $greetingIcon = 'fa-solid fa-sun';
} elseif ($hour >= 12 && $hour < 17) {
    $greeting     = Text::_('COM_J2COMMERCE_GREETING_AFTERNOON');
    $greetingIcon = 'fa-solid fa-cloud-sun';
} else {
    $greeting     = Text::_('COM_J2COMMERCE_GREETING_EVENING');
    $greetingIcon = 'fa-solid fa-moon';
}

// First name from full name
$firstName = explode(' ', trim($user->name))[0];

// Session counts passed via $displayData
$registeredCount = (int) ($displayData['registered'] ?? 0);
$guestCount      = (int) ($displayData['guests'] ?? 0);

// Today's sales total for the configured dashboard order statuses, scoped to store timezone
// Convert store-local day boundaries to UTC for correct comparison against UTC created_on
$utcTz      = new \DateTimeZone('UTC');
$todayStart = (new \DateTimeImmutable($now->format('Y-m-d') . ' 00:00:00', $tz))
    ->setTimezone($utcTz)->format('Y-m-d H:i:s');
$todayEnd   = (new \DateTimeImmutable($now->format('Y-m-d') . ' 23:59:59', $tz))
    ->setTimezone($utcTz)->format('Y-m-d H:i:s');

$normalType = 'normal';
$db    = Factory::getContainer()->get(\Joomla\Database\DatabaseInterface::class);
$query = $db->getQuery(true)
    ->select('COALESCE(SUM(' . $db->quoteName('order_total') . '), 0)')
    ->from($db->quoteName('#__j2commerce_orders'))
    ->whereIn($db->quoteName('order_state_id'), ConfigHelper::getDashboardOrderStatuses(), ParameterType::INTEGER)
    ->where($db->quoteName('order_type') . ' = :order_type')
    ->where($db->quoteName('created_on') . ' >= :today_start')
    ->where($db->quoteName('created_on') . ' <= :today_end')
    ->bind(':order_type', $normalType)
    ->bind(':today_start', $todayStart)
    ->bind(':today_end', $todayEnd);

$db->setQuery($query);
$salesTotal     = (float) $db->loadResult();
$salesFormatted = CurrencyHelper::format($salesTotal);
$salesHtml      = '<span class="text-success">' . htmlspecialchars($salesFormatted, ENT_QUOTES, 'UTF-8') . '</span>';
$salesLabel     = Text::sprintf('COM_J2COMMERCE_GREETING_SALES_TODAY', $salesHtml);

// Build optional visitor parts
$visitorParts = [];
if ($registeredCount > 0) {
    $customerLabel  = $registeredCount === 1
        ? Text::sprintf('COM_J2COMMERCE_GREETING_CUSTOMER_1', $registeredCount)
        : Text::sprintf('COM_J2COMMERCE_GREETING_CUSTOMER_N', $registeredCount);
    $visitorParts[] = '<span class="text-info">' . $customerLabel . '</span>';
}
if ($guestCount > 0) {
    $guestLabel     = $guestCount === 1
        ? Text::sprintf('COM_J2COMMERCE_GREETING_GUEST_1', $guestCount)
        : Text::sprintf('COM_J2COMMERCE_GREETING_GUEST_N', $guestCount);
    $visitorParts[] = '<span class="text-warning">' . $guestLabel . '</span>';
}

// Compose message dynamically based on which visitor counts are non-zero
$name = htmlspecialchars($firstName, ENT_QUOTES, 'UTF-8');
if (empty($visitorParts)) {
    $body = Text::sprintf('COM_J2COMMERCE_GREETING_BODY_SALES_ONLY', $salesLabel);
} elseif (count($visitorParts) === 1) {
    $body = Text::sprintf('COM_J2COMMERCE_GREETING_BODY_SALES_ONE_GROUP', $salesLabel, $visitorParts[0]);
} else {
    $body = Text::sprintf('COM_J2COMMERCE_GREETING_BODY_SALES_TWO_GROUPS', $salesLabel, $visitorParts[0], $visitorParts[1]);
}
?>
<div class="j2commerce-navbar-top mb-2 pb-1">
    <span class="<?php echo $greetingIcon; ?> me-1"></span>
    <?php echo '<strong class="me-1">'.$greeting . ' ' . $name . '!</strong> ' . $body; ?>
</div>
