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

/**
 * Only the core statuses are checked. A merchant-defined status is allowed to carry no type —
 * null is a first-class "no core semantics" state — so counting those would raise a warning
 * a merchant could never clear.
 */
class OrderStatusTypeCheck extends AbstractSetupCheck
{
    public function getId(): string
    {
        return 'orderstatustypes';
    }

    public function getGroup(): string
    {
        return 'localization';
    }

    public function getGroupOrder(): int
    {
        return 750;
    }

    public function getLabel(): string
    {
        return Text::_('COM_J2COMMERCE_SETUP_GUIDE_CHECK_ORDERSTATUS_TYPES');
    }

    public function getDescription(): string
    {
        return Text::_('COM_J2COMMERCE_SETUP_GUIDE_CHECK_ORDERSTATUS_TYPES_DESC');
    }

    public function check(): SetupCheckResult
    {
        $unmapped = $this->getUnmappedCoreStatuses();

        if ($unmapped === []) {
            return new SetupCheckResult(
                'pass',
                Text::_('COM_J2COMMERCE_SETUP_GUIDE_CHECK_ORDERSTATUS_TYPES_PASS')
            );
        }

        return new SetupCheckResult(
            'fail',
            Text::sprintf('COM_J2COMMERCE_SETUP_GUIDE_CHECK_ORDERSTATUS_TYPES_FAIL', \count($unmapped)),
            ['unmapped' => $unmapped]
        );
    }

    /**
     * Core statuses with no lifecycle type assigned.
     *
     * @return list<string>  Status names, already resolved for display.
     */
    private function getUnmappedCoreStatuses(): array
    {
        $db    = $this->getDatabase();
        $query = $db->getQuery(true)
            ->select($db->quoteName('orderstatus_name'))
            ->from($db->quoteName('#__j2commerce_orderstatuses'))
            ->where($db->quoteName('orderstatus_core') . ' = 1')
            ->where(
                '(' . $db->quoteName('orderstatus_type') . ' IS NULL'
                . ' OR ' . $db->quoteName('orderstatus_type') . " = '')"
            )
            ->order($db->quoteName('ordering') . ' ASC');

        $names = $db->setQuery($query)->loadColumn() ?: [];

        return array_map(static fn ($name): string => Text::_((string) $name), $names);
    }

    public function getDetailView(): string
    {
        $statusesUrl = 'index.php?option=com_j2commerce&view=orderstatuses';
        $unmapped    = $this->getUnmappedCoreStatuses();

        $html = '<h5>' . Text::_('COM_J2COMMERCE_SETUP_GUIDE_CHECK_ORDERSTATUS_TYPES') . '</h5>'
            . '<p>' . Text::_('COM_J2COMMERCE_SETUP_GUIDE_CHECK_ORDERSTATUS_TYPES_DESC') . '</p>'
            . '<p>' . Text::_('COM_J2COMMERCE_SETUP_GUIDE_CHECK_ORDERSTATUS_TYPES_WHY') . '</p>';

        if ($unmapped !== []) {
            $html .= '<p class="mb-1"><strong>'
                . Text::_('COM_J2COMMERCE_SETUP_GUIDE_CHECK_ORDERSTATUS_TYPES_UNMAPPED_HEADING')
                . '</strong></p><ul class="mb-3">';

            foreach ($unmapped as $name) {
                $html .= '<li>' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '</li>';
            }

            $html .= '</ul>';
        }

        return $html
            . '<a href="' . $statusesUrl . '" class="btn btn-primary w-100 mb-2">'
            . Text::_('COM_J2COMMERCE_SETUP_GUIDE_ACTION_MAP_ORDERSTATUS_TYPES')
            . '</a>';
    }
}
