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

use Joomla\CMS\Language\Text;

defined('_JEXEC') or die;

class CheckoutPageCheck extends AbstractMenuItemCheck
{
    public function getId(): string
    {
        return 'checkout_page';
    }

    public function getLabel(): string
    {
        return Text::_('COM_J2COMMERCE_SETUP_GUIDE_CHECK_CHECKOUT_PAGE');
    }

    public function getDescription(): string
    {
        return Text::_('COM_J2COMMERCE_SETUP_GUIDE_CHECK_CHECKOUT_PAGE_DESC');
    }

    protected function getViewPattern(): string
    {
        return 'option=com_j2commerce&view=checkout';
    }

    protected function getMenuLink(): string
    {
        return 'index.php?option=com_j2commerce&view=checkout';
    }

    protected function getMenuTitle(): string
    {
        return Text::_('COM_J2COMMERCE_SETUP_GUIDE_MENU_TITLE_CHECKOUT');
    }
}
