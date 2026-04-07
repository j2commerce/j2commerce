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

class StoreLogoCheck extends AbstractSetupCheck
{
    public function getId(): string
    {
        return 'store_logo';
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
        return Text::_('COM_J2COMMERCE_SETUP_GUIDE_CHECK_STORE_LOGO');
    }

    public function getDescription(): string
    {
        return Text::_('COM_J2COMMERCE_SETUP_GUIDE_CHECK_STORE_LOGO_DESC');
    }

    public function check(): SetupCheckResult
    {
        $logo = $this->getParams()->get('store_logo', '');

        // store_logo can be a string path or a JSON object with imagefile key
        $hasLogo = false;
        if (\is_string($logo) && $logo !== '') {
            $hasLogo = true;
        } elseif (\is_object($logo) || \is_array($logo)) {
            $obj     = (object) $logo;
            $hasLogo = !empty($obj->imagefile);
        }

        if ($hasLogo) {
            return new SetupCheckResult('pass', Text::_('COM_J2COMMERCE_SETUP_GUIDE_CHECK_STORE_LOGO_PASS'));
        }

        return new SetupCheckResult('warning', Text::_('COM_J2COMMERCE_SETUP_GUIDE_CHECK_STORE_LOGO_WARNING'));
    }

    public function getDetailView(): string
    {
        $configUrl = 'index.php?option=com_config&view=component&component=com_j2commerce';
        $logo      = $this->getParams()->get('store_logo', '');
        $logoPath  = '';

        if (\is_string($logo) && $logo !== '') {
            $logoPath = $logo;
        } elseif (\is_object($logo) || \is_array($logo)) {
            $obj      = (object) $logo;
            $logoPath = $obj->imagefile ?? '';
        }

        $html = '<h5>' . Text::_('COM_J2COMMERCE_SETUP_GUIDE_CHECK_STORE_LOGO') . '</h5>'
            . '<p>' . Text::_('COM_J2COMMERCE_SETUP_GUIDE_CHECK_STORE_LOGO_DESC') . '</p>';

        if ($logoPath !== '') {
            $html .= '<p class="text-muted small mb-2">' . Text::_('COM_J2COMMERCE_SETUP_GUIDE_CURRENT_VALUE') . '</p>'
                . '<p><img src="' . htmlspecialchars($logoPath) . '" alt="" style="max-width:120px;max-height:60px;" class="border rounded p-1"></p>';
        } else {
            $html .= '<p class="text-muted small">' . Text::_('COM_J2COMMERCE_SETUP_GUIDE_CHECK_STORE_LOGO_OPTIONAL') . '</p>';
        }

        $html .= '<a href="' . $configUrl . '" class="btn btn-secondary w-100">'
            . Text::_('COM_J2COMMERCE_SETUP_GUIDE_ACTION_CONFIGURE')
            . '</a>';

        return $html;
    }
}
