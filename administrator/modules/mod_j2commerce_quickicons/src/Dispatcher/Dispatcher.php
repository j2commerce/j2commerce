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
use Joomla\CMS\Helper\HelperFactoryAwareInterface;
use Joomla\CMS\Helper\HelperFactoryAwareTrait;

class Dispatcher extends AbstractModuleDispatcher implements HelperFactoryAwareInterface
{
    use HelperFactoryAwareTrait;

    protected function getLayoutData(): array
    {
        $data = parent::getLayoutData();

        $data['buttons'] = $this->getHelperFactory()
            ->getHelper('QuickIconsHelper')
            ->getButtons($data['params']);

        // Button labels use COM_J2COMMERCE_* keys, which are not loaded when this
        // module renders outside a com_j2commerce request.
        if ($data['buttons'] !== []) {
            $this->app->getLanguage()->load('com_j2commerce', JPATH_ADMINISTRATOR);
        }

        return $data;
    }
}
