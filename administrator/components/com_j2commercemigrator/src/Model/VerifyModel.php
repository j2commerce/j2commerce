<?php

/**
 * @package     J2Commerce
 * @subpackage  com_j2commercemigrator
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace J2Commerce\Component\J2commercemigrator\Administrator\Model;

defined('_JEXEC') or die;

use J2Commerce\Component\J2commercemigrator\Administrator\Service\AdapterRegistry;
use J2Commerce\Component\J2commercemigrator\Administrator\Service\ConnectionManager;
use J2Commerce\Component\J2commercemigrator\Administrator\Service\VerificationService;
use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;

/**
 * Verify model — runs post-migration integrity checks via VerificationService.
 */
class VerifyModel extends BaseDatabaseModel
{
    /** Runs all verification checks for the given adapter and returns a report. */
    public function runAll(string $adapterKey): array
    {
        $db       = $this->getDatabase();
        $app      = Factory::getApplication();
        $registry = new AdapterRegistry();
        $adapter  = $registry->get($adapterKey);

        if (!$adapter) {
            return ['error' => "Unknown adapter: {$adapterKey}"];
        }

        $connMgr = new ConnectionManager($app, $db);
        $service = new VerificationService($db, $connMgr->getReader());

        return $service->runAll($adapter);
    }
}
