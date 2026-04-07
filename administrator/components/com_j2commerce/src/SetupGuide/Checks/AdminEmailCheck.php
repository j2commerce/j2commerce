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
use Joomla\CMS\Language\Text;

\defined('_JEXEC') or die;

class AdminEmailCheck extends AbstractSetupCheck
{
    public function getId(): string
    {
        return 'admin_email';
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
        return Text::_('COM_J2COMMERCE_SETUP_GUIDE_CHECK_ADMIN_EMAIL');
    }

    public function getDescription(): string
    {
        return Text::_('COM_J2COMMERCE_SETUP_GUIDE_CHECK_ADMIN_EMAIL_DESC');
    }

    public function check(): SetupCheckResult
    {
        if (!empty($this->getParams()->get('admin_email', ''))) {
            return new SetupCheckResult('pass', Text::_('COM_J2COMMERCE_SETUP_GUIDE_CHECK_ADMIN_EMAIL_PASS'));
        }

        return new SetupCheckResult('fail', Text::_('COM_J2COMMERCE_SETUP_GUIDE_CHECK_ADMIN_EMAIL_FAIL'));
    }

    public function getDetailView(): string
    {
        $configUrl = 'index.php?option=com_config&view=component&component=com_j2commerce';
        $email     = $this->getParams()->get('admin_email', '');

        $html = '<h5>' . Text::_('COM_J2COMMERCE_SETUP_GUIDE_CHECK_ADMIN_EMAIL') . '</h5>'
            . '<p>' . Text::_('COM_J2COMMERCE_SETUP_GUIDE_CHECK_ADMIN_EMAIL_DESC') . '</p>';

        if ($email !== '') {
            $html .= '<p class="text-muted small mb-1">' . Text::_('COM_J2COMMERCE_SETUP_GUIDE_CURRENT_VALUE') . '</p>'
                . '<p class="fw-semibold">' . htmlspecialchars($email) . '</p>';
        } else {
            $html .= '<p class="small text-danger">' . Text::_('COM_J2COMMERCE_SETUP_GUIDE_NOT_SET') . '</p>';
        }

        $html .= '<a href="' . $configUrl . '" class="btn btn-primary w-100">'
            . Text::_('COM_J2COMMERCE_SETUP_GUIDE_ACTION_CONFIGURE')
            . '</a>';

        return $html;
    }
}
