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

class TaxProfileCheck extends AbstractSetupCheck
{
    public function getId(): string
    {
        return 'tax_profile';
    }

    public function getGroup(): string
    {
        return 'tax';
    }

    public function getGroupOrder(): int
    {
        return 600;
    }

    public function getLabel(): string
    {
        return Text::_('COM_J2COMMERCE_SETUP_GUIDE_CHECK_TAX_PROFILE');
    }

    public function getDescription(): string
    {
        return Text::_('COM_J2COMMERCE_SETUP_GUIDE_CHECK_TAX_PROFILE_DESC');
    }

    public function check(): SetupCheckResult
    {
        $db    = $this->getDatabase();
        $query = $db->getQuery(true)
            ->select('COUNT(*)')
            ->from($db->quoteName('#__j2commerce_taxprofiles'))
            ->where($db->quoteName('enabled') . ' = 1');

        $count = (int) $db->setQuery($query)->loadResult();

        return $count > 0
            ? new SetupCheckResult('pass', Text::sprintf('COM_J2COMMERCE_SETUP_GUIDE_CHECK_TAX_PROFILE_PASS', $count))
            : new SetupCheckResult('fail', Text::_('COM_J2COMMERCE_SETUP_GUIDE_CHECK_TAX_PROFILE_FAIL'));
    }

    public function getDetailView(): string
    {
        $taxUrl = 'index.php?option=com_j2commerce&view=taxprofiles';

        return '<h5>' . Text::_('COM_J2COMMERCE_SETUP_GUIDE_CHECK_TAX_PROFILE') . '</h5>'
            . '<p>' . Text::_('COM_J2COMMERCE_SETUP_GUIDE_CHECK_TAX_PROFILE_DESC') . '</p>'
            . '<p>' . Text::_('COM_J2COMMERCE_SETUP_GUIDE_CHECK_TAX_PROFILE_DISMISS_NOTE') . '</p>'
            . '<a href="' . $taxUrl . '" class="btn btn-primary w-100">'
            . Text::_('COM_J2COMMERCE_SETUP_GUIDE_ACTION_MANAGE_TAX_PROFILES')
            . '</a>';
    }
}
