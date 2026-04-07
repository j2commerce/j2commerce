<?php

declare(strict_types=1);

/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace J2Commerce\Component\J2commerce\Administrator\SetupGuide\Checks;

use J2Commerce\Component\J2commerce\Administrator\SetupGuide\AbstractSetupCheck;
use J2Commerce\Component\J2commerce\Administrator\SetupGuide\SetupCheckResult;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;

\defined('_JEXEC') or die;

class TimezoneCheck extends AbstractSetupCheck
{
    public function getId(): string
    {
        return 'timezone';
    }

    public function getGroup(): string
    {
        return 'store_identity';
    }

    public function getGroupOrder(): int
    {
        return 100;
    }

    public function getLabel(): string
    {
        return Text::_('COM_J2COMMERCE_SETUP_GUIDE_CHECK_TIMEZONE');
    }

    public function getDescription(): string
    {
        return Text::_('COM_J2COMMERCE_SETUP_GUIDE_CHECK_TIMEZONE_DESC');
    }

    public function check(): SetupCheckResult
    {
        $timezone = Factory::getApplication()->get('offset', 'UTC');

        // If the user has confirmed their timezone, it's always a pass
        if ($this->isTimezoneConfirmed()) {
            return new SetupCheckResult(
                'pass',
                Text::sprintf('COM_J2COMMERCE_SETUP_GUIDE_CHECK_TIMEZONE_PASS', $timezone),
                ['timezone' => $timezone]
            );
        }

        // Unconfirmed — show warning so the user reviews and confirms
        return new SetupCheckResult(
            'warning',
            Text::_('COM_J2COMMERCE_SETUP_GUIDE_CHECK_TIMEZONE_WARNING'),
            ['timezone' => $timezone]
        );
    }

    private function isTimezoneConfirmed(): bool
    {
        // Uses the same key as the dismiss system: setup_dismissed_{id}
        return (bool) $this->getParams()->get('setup_dismissed_timezone', false);
    }

    public function getDetailView(): string
    {
        $timezone     = Factory::getApplication()->get('offset', 'UTC');
        $globalConfig = 'index.php?option=com_config';

        try {
            $tz      = new \DateTimeZone($timezone);
            $now     = new \DateTime('now', $tz);
            $timeStr = $now->format('g:i A');
        } catch (\Exception) {
            $timeStr = '';
        }

        $storeTzLabel = Text::_('COM_J2COMMERCE_SETUP_GUIDE_CHECK_TIMEZONE_STORE_TIME');
        $localTzLabel = Text::_('COM_J2COMMERCE_SETUP_GUIDE_CHECK_TIMEZONE_LOCAL_TIME');
        $matchMsg     = Text::_('COM_J2COMMERCE_SETUP_GUIDE_CHECK_TIMEZONE_MATCH');
        $mismatchMsg  = Text::_('COM_J2COMMERCE_SETUP_GUIDE_CHECK_TIMEZONE_MISMATCH');
        $escapedTz    = htmlspecialchars($timezone, ENT_QUOTES, 'UTF-8');

        $html = '<h5>' . Text::_('COM_J2COMMERCE_SETUP_GUIDE_CHECK_TIMEZONE') . '</h5>'
            . '<p>' . Text::_('COM_J2COMMERCE_SETUP_GUIDE_CHECK_TIMEZONE_DESC') . '</p>';

        // Container with data attribute for JS initialization (innerHTML doesn't run <script>)
        $html .= '<div id="j2c-tz-clocks" data-store-tz="' . $escapedTz . '"'
            . ' data-match-msg="' . htmlspecialchars($matchMsg, ENT_QUOTES, 'UTF-8') . '"'
            . ' data-mismatch-msg="' . htmlspecialchars($mismatchMsg, ENT_QUOTES, 'UTF-8') . '">';

        // Dual clock display: Store Time and Local Time side by side
        $html .= '<div class="row g-3 my-3">';

        // Store Time (from Joomla global config)
        $html .= '<div class="col-6">'
            . '<div class="text-center p-3 rounded" style="background:rgba(255,255,255,.1);">'
            . '<span class="fa-regular fa-clock" style="font-size:2rem;" aria-hidden="true"></span>'
            . '<p class="fw-bold fs-4 mb-0 mt-2" id="j2c-store-time">' . ($timeStr !== '' ? $timeStr : '--:--') . '</p>'
            . '<p class="small text-body-secondary mb-1">' . $storeTzLabel . '</p>'
            . '<p class="small mb-0 fw-semibold">' . $escapedTz . '</p>'
            . '</div>'
            . '</div>';

        // Local Time (detected from browser via Intl API)
        $html .= '<div class="col-6">'
            . '<div class="text-center p-3 rounded" style="background:rgba(255,255,255,.1);">'
            . '<span class="fa-regular fa-clock" style="font-size:2rem;" aria-hidden="true"></span>'
            . '<p class="fw-bold fs-4 mb-0 mt-2" id="j2c-local-time">--:--</p>'
            . '<p class="small text-body-secondary mb-1">' . $localTzLabel . '</p>'
            . '<p class="small mb-0 fw-semibold" id="j2c-local-tz-name">…</p>'
            . '</div>'
            . '</div>';

        $html .= '</div>';

        // Match/mismatch indicator (hidden by default, shown by JS)
        $html .= '<div id="j2c-tz-match" class="alert alert-success small py-2 d-none">'
            . '<span class="fa-solid fa-circle-check me-1"></span><span class="j2c-tz-msg"></span>'
            . '</div>'
            . '<div id="j2c-tz-mismatch" class="alert alert-warning small py-2 d-none">'
            . '<span class="fa-solid fa-triangle-exclamation me-1"></span><span class="j2c-tz-msg"></span>'
            . '</div>';

        $html .= '</div>'; // close #j2c-tz-clocks

        // "Confirm Timezone" button — uses the dismiss endpoint to mark as confirmed
        if (!$this->isTimezoneConfirmed()) {
            $html .= '<button type="button" class="btn btn-success w-100 mt-2" data-setup-dismiss="timezone">'
                . '<span class="fa-solid fa-circle-check me-1"></span>'
                . Text::_('COM_J2COMMERCE_SETUP_GUIDE_CHECK_TIMEZONE_CONFIRM')
                . '</button>';
        }

        $html .= '<a href="' . $globalConfig . '" class="btn btn-outline-light btn-sm w-100 mt-2">'
            . Text::_('COM_J2COMMERCE_SETUP_GUIDE_CHECK_TIMEZONE_CHANGE')
            . '</a>';

        return $html;
    }

    public function isDismissed(): bool
    {
        // Never report as "dismissed" — we use the same param key for confirmation
        // but want the green checkmark (pass), not the gray minus (dismissed).
        return false;
    }

    public function isDismissible(): bool
    {
        // Must return true so the dismiss endpoint accepts the confirm request.
        // The default X button won't show because once confirmed, check() returns 'pass'
        // and the JS only renders the dismiss button when status !== 'pass'.
        return true;
    }
}
