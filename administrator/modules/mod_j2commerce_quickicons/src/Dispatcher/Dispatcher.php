<?php

/**
 * @package     J2Commerce
 * @subpackage  mod_j2commerce_quickicons
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace J2Commerce\Module\J2commerceQuickicons\Administrator\Dispatcher;

\defined('_JEXEC') or die;

use Joomla\CMS\Dispatcher\AbstractModuleDispatcher;
use Joomla\CMS\Factory;
use Joomla\CMS\Helper\HelperFactoryAwareInterface;
use Joomla\CMS\Helper\HelperFactoryAwareTrait;

class Dispatcher extends AbstractModuleDispatcher implements HelperFactoryAwareInterface
{
    use HelperFactoryAwareTrait;

    protected function getLayoutData(): array
    {
        $data = parent::getLayoutData();

        // Load component language — button labels use COM_J2COMMERCE_* keys
        // which aren't available when this module renders outside com_j2commerce context
        Factory::getApplication()->getLanguage()->load('com_j2commerce', JPATH_ADMINISTRATOR);

        $data['buttons'] = $this->getHelperFactory()
            ->getHelper('QuickIconsHelper')
            ->getButtons($data['params']);

        return $data;
    }
}
