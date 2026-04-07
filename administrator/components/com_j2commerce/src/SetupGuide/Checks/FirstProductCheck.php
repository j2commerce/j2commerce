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

class FirstProductCheck extends AbstractSetupCheck
{
    public function getId(): string
    {
        return 'first_product';
    }

    public function getGroup(): string
    {
        return 'catalog';
    }

    public function getGroupOrder(): int
    {
        return 400;
    }

    public function isDismissible(): bool
    {
        return false;
    }

    public function getLabel(): string
    {
        return Text::_('COM_J2COMMERCE_SETUP_GUIDE_CHECK_FIRST_PRODUCT');
    }

    public function getDescription(): string
    {
        return Text::_('COM_J2COMMERCE_SETUP_GUIDE_CHECK_FIRST_PRODUCT_DESC');
    }

    public function check(): SetupCheckResult
    {
        $db    = $this->getDatabase();
        $query = $db->getQuery(true)
            ->select('COUNT(*)')
            ->from($db->quoteName('#__j2commerce_products'))
            ->where($db->quoteName('enabled') . ' = 1');

        $count = (int) $db->setQuery($query)->loadResult();

        return $count > 0
            ? new SetupCheckResult('pass', Text::sprintf('COM_J2COMMERCE_SETUP_GUIDE_CHECK_FIRST_PRODUCT_PASS', $count))
            : new SetupCheckResult('fail', Text::_('COM_J2COMMERCE_SETUP_GUIDE_CHECK_FIRST_PRODUCT_FAIL'));
    }

    public function getDetailView(): string
    {
        $productsUrl = 'index.php?option=com_j2commerce&view=products';

        return '<h5>' . Text::_('COM_J2COMMERCE_SETUP_GUIDE_CHECK_FIRST_PRODUCT') . '</h5>'
            . '<p>' . Text::_('COM_J2COMMERCE_SETUP_GUIDE_CHECK_FIRST_PRODUCT_DESC') . '</p>'
            . '<a href="' . $productsUrl . '" class="btn btn-primary w-100 mb-2">'
            . Text::_('COM_J2COMMERCE_SETUP_GUIDE_ACTION_MANAGE_PRODUCTS')
            . '</a>'
            . '<button type="button" class="btn btn-outline-info w-100 button-start-guidedtour" data-gt-uid="com_j2commerce.creating-product">'
            . '<span class="icon-map-signs me-1" aria-hidden="true"></span> '
            . Text::_('COM_J2COMMERCE_SETUP_GUIDE_START_GUIDED_TOUR')
            . '</button>';
    }

    public function getGuidedTourUid(): ?string
    {
        return 'com_j2commerce.creating-product';
    }
}
