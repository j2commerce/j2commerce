<?php

declare(strict_types=1);

/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace J2Commerce\Component\J2commerce\Administrator\SetupGuide;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\Database\DatabaseInterface;
use Joomla\Registry\Registry;

\defined('_JEXEC') or die;

abstract class AbstractSetupCheck implements SetupCheckInterface
{
    protected function getParams(): Registry
    {
        return ComponentHelper::getParams('com_j2commerce');
    }

    protected function getDatabase(): DatabaseInterface
    {
        return Factory::getContainer()->get(DatabaseInterface::class);
    }

    public function isDismissed(): bool
    {
        return (bool) $this->getParams()->get('setup_dismissed_' . $this->getId(), false);
    }

    public function isDismissible(): bool
    {
        return true;
    }

    public function getActions(): array
    {
        return [];
    }

    public function getGuidedTourUid(): ?string
    {
        return null;
    }
}
