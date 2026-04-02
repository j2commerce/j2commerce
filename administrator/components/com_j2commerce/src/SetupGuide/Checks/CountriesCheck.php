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

defined('_JEXEC') or die;

class CountriesCheck extends AbstractSetupCheck
{
    public function getId(): string
    {
        return 'countries';
    }

    public function getGroup(): string
    {
        return 'localization';
    }

    public function getGroupOrder(): int
    {
        return 700;
    }

    public function getLabel(): string
    {
        return Text::_('COM_J2COMMERCE_SETUP_GUIDE_CHECK_COUNTRIES');
    }

    public function getDescription(): string
    {
        return Text::_('COM_J2COMMERCE_SETUP_GUIDE_CHECK_COUNTRIES_DESC');
    }

    public function check(): SetupCheckResult
    {
        $db    = $this->getDatabase();
        $query = $db->getQuery(true)
            ->select('COUNT(*)')
            ->from($db->quoteName('#__j2commerce_countries'))
            ->where($db->quoteName('enabled') . ' = 1');

        $count = (int) $db->setQuery($query)->loadResult();

        return new SetupCheckResult('pass', Text::sprintf('COM_J2COMMERCE_SETUP_GUIDE_CHECK_COUNTRIES_PASS', $count));
    }

    public function getDetailView(): string
    {
        $countriesUrl = 'index.php?option=com_j2commerce&view=countries';

        return '<h5>' . Text::_('COM_J2COMMERCE_SETUP_GUIDE_CHECK_COUNTRIES') . '</h5>'
            . '<p>' . Text::_('COM_J2COMMERCE_SETUP_GUIDE_CHECK_COUNTRIES_DESC') . '</p>'
            . '<p>' . Text::_('COM_J2COMMERCE_SETUP_GUIDE_CHECK_COUNTRIES_REVIEW_NOTE') . '</p>'
            . '<a href="' . $countriesUrl . '" class="btn btn-primary w-100 mb-2">'
            . Text::_('COM_J2COMMERCE_SETUP_GUIDE_ACTION_MANAGE_COUNTRIES')
            . '</a>'
            . '<button type="button" class="btn btn-outline-info w-100 button-start-guidedtour" data-gt-uid="com_j2commerce.managing-countries">'
            . '<span class="icon-map-signs me-1" aria-hidden="true"></span> '
            . Text::_('COM_J2COMMERCE_SETUP_GUIDE_START_GUIDED_TOUR')
            . '</button>';
    }

    public function getGuidedTourUid(): ?string
    {
        return 'com_j2commerce.managing-countries';
    }
}
