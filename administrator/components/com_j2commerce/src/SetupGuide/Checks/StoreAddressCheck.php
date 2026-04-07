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

class StoreAddressCheck extends AbstractSetupCheck
{
    private const REQUIRED_FIELDS = [
        'store_name'      => 'COM_J2COMMERCE_SETUP_GUIDE_CHECK_STORE_ADDRESS_FIELD_NAME',
        'store_address_1' => 'COM_J2COMMERCE_SETUP_GUIDE_CHECK_STORE_ADDRESS_FIELD_ADDRESS',
        'store_city'      => 'COM_J2COMMERCE_SETUP_GUIDE_CHECK_STORE_ADDRESS_FIELD_CITY',
        'store_zip'       => 'COM_J2COMMERCE_SETUP_GUIDE_CHECK_STORE_ADDRESS_FIELD_ZIP',
    ];

    public function getId(): string
    {
        return 'store_address';
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
        return Text::_('COM_J2COMMERCE_SETUP_GUIDE_CHECK_STORE_ADDRESS');
    }

    public function getDescription(): string
    {
        return Text::_('COM_J2COMMERCE_SETUP_GUIDE_CHECK_STORE_ADDRESS_DESC');
    }

    public function check(): SetupCheckResult
    {
        $params  = $this->getParams();
        $missing = [];

        foreach (self::REQUIRED_FIELDS as $param => $langKey) {
            if (empty($params->get($param, ''))) {
                $missing[] = Text::_($langKey);
            }
        }

        if (empty($missing)) {
            return new SetupCheckResult('pass', Text::_('COM_J2COMMERCE_SETUP_GUIDE_CHECK_STORE_ADDRESS_PASS'));
        }

        return new SetupCheckResult(
            'fail',
            Text::sprintf('COM_J2COMMERCE_SETUP_GUIDE_CHECK_STORE_ADDRESS_FAIL', implode(', ', $missing)),
            ['missing_fields' => $missing]
        );
    }

    public function getDetailView(): string
    {
        $configUrl = 'index.php?option=com_config&view=component&component=com_j2commerce';
        $params    = $this->getParams();

        $html = '<h5>' . Text::_('COM_J2COMMERCE_SETUP_GUIDE_CHECK_STORE_ADDRESS') . '</h5>'
            . '<p>' . Text::_('COM_J2COMMERCE_SETUP_GUIDE_CHECK_STORE_ADDRESS_DESC') . '</p>'
            . '<p class="text-muted small mb-1">' . Text::_('COM_J2COMMERCE_SETUP_GUIDE_CURRENT_VALUES') . '</p>'
            . '<table class="table table-sm table-bordered mb-3"><tbody>';

        foreach (self::REQUIRED_FIELDS as $param => $langKey) {
            $value = $params->get($param, '');
            $html .= '<tr><td class="small fw-semibold">' . Text::_($langKey) . '</td>'
                . '<td class="small">' . ($value !== '' ? htmlspecialchars($value) : '<em class="text-muted">' . Text::_('COM_J2COMMERCE_SETUP_GUIDE_NOT_SET') . '</em>') . '</td></tr>';
        }

        $html .= '</tbody></table>'
            . '<a href="' . $configUrl . '" class="btn btn-primary w-100">'
            . Text::_('COM_J2COMMERCE_SETUP_GUIDE_ACTION_CONFIGURE')
            . '</a>';

        return $html;
    }
}
