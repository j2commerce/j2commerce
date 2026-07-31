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

use J2Commerce\Component\J2commerce\Administrator\Helper\UploadHelper;
use J2Commerce\Component\J2commerce\Administrator\SetupGuide\AbstractSetupCheck;
use J2Commerce\Component\J2commerce\Administrator\SetupGuide\SetupCheckResult;
use Joomla\CMS\Language\Text;

\defined('_JEXEC') or die;

class UploadThrottleCheck extends AbstractSetupCheck
{
    public function getId(): string
    {
        return 'upload_throttle';
    }

    public function getGroup(): string
    {
        return 'system_requirements';
    }

    public function getGroupOrder(): int
    {
        return 200;
    }

    /** Not dismissible: the throttle it reports is silent by design and the fix is deterministic. */
    public function isDismissible(): bool
    {
        return false;
    }

    public function getLabel(): string
    {
        return Text::_('COM_J2COMMERCE_SETUP_GUIDE_CHECK_UPLOAD_THROTTLE');
    }

    public function getDescription(): string
    {
        return Text::_('COM_J2COMMERCE_SETUP_GUIDE_CHECK_UPLOAD_THROTTLE_DESC');
    }

    public function check(): SetupCheckResult
    {
        return UploadHelper::hasClientIpColumn()
            ? new SetupCheckResult('pass', Text::_('COM_J2COMMERCE_SETUP_GUIDE_CHECK_UPLOAD_THROTTLE_PASS'))
            : new SetupCheckResult('warning', Text::_('COM_J2COMMERCE_SETUP_GUIDE_CHECK_UPLOAD_THROTTLE_WARNING'));
    }

    public function getDetailView(): string
    {
        $active = UploadHelper::hasClientIpColumn();

        $html = '<h5>' . Text::_('COM_J2COMMERCE_SETUP_GUIDE_CHECK_UPLOAD_THROTTLE') . '</h5>'
            . '<p>' . Text::_('COM_J2COMMERCE_SETUP_GUIDE_CHECK_UPLOAD_THROTTLE_DESC') . '</p>';

        if ($active) {
            return $html
                . '<div class="alert alert-success small py-2">'
                . '<span class="fa-solid fa-circle-check me-1" aria-hidden="true"></span>'
                . Text::_('COM_J2COMMERCE_SETUP_GUIDE_CHECK_UPLOAD_THROTTLE_PASS')
                . '</div>'
                . '<p class="small text-body-secondary mb-0">'
                . Text::_('COM_J2COMMERCE_SETUP_GUIDE_CHECK_UPLOAD_THROTTLE_LIMIT') . ' '
                . UploadHelper::HOURLY_UPLOAD_LIMIT
                . '</p>';
        }

        return $html
            . '<div class="alert alert-warning small py-2">'
            . '<span class="fa-solid fa-triangle-exclamation me-1" aria-hidden="true"></span>'
            . Text::_('COM_J2COMMERCE_SETUP_GUIDE_CHECK_UPLOAD_THROTTLE_WARNING')
            . '</div>'
            . '<p>' . Text::_('COM_J2COMMERCE_SETUP_GUIDE_CHECK_UPLOAD_THROTTLE_FIX') . '</p>'
            . '<a href="index.php?option=com_installer&view=database" class="btn btn-primary w-100 mb-3">'
            . Text::_('COM_J2COMMERCE_SETUP_GUIDE_ACTION_DATABASE_FIX')
            . '</a>'
            . '<p class="small mb-1">' . Text::_('COM_J2COMMERCE_SETUP_GUIDE_CHECK_UPLOAD_THROTTLE_MANUAL') . '</p>'
            . '<pre class="small mb-0"><code>'
            . htmlspecialchars($this->getRepairSql(), ENT_QUOTES, 'UTF-8')
            . '</code></pre>';
    }

    /** Mirrors the 6.5.0-2026-07-30 update, with the real table prefix so it can be pasted as-is. */
    private function getRepairSql(): string
    {
        $sql = "ALTER TABLE `#__j2commerce_uploads`\n"
            . "    ADD COLUMN `client_ip` varchar(64) NOT NULL DEFAULT ''"
            . " COMMENT 'Salted SHA-256 throttle key — u: user id, i: client IP; not reversible'"
            . " AFTER `file_size`;\n\n"
            . "ALTER TABLE `#__j2commerce_uploads`\n"
            . '    ADD INDEX `idx_client_ip` (`client_ip`, `created_on`);';

        return $this->getDatabase()->replacePrefix($sql);
    }
}
