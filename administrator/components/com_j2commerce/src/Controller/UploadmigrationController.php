<?php

/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace J2Commerce\Component\J2commerce\Administrator\Controller;

\defined('_JEXEC') or die;

use Joomla\CMS\Access\Exception\NotAllowed;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Router\Route;

class UploadmigrationController extends BaseController
{
    /**
     * Relocate the files the scan found movable.
     *
     * core.admin rather than an edit action: this moves customer files across the order
     * history, which is the same weight as Configuration and the template overrides.
     */
    public function migrate(): void
    {
        $this->checkToken();

        if (!$this->app->getIdentity()->authorise('core.admin', 'com_j2commerce')) {
            throw new NotAllowed(Text::_('JLIB_APPLICATION_ERROR_ACCESS_FORBIDDEN'), 403);
        }

        $result = $this->getModel('Uploadmigration', 'Administrator', ['ignore_request' => true])->migrate();

        $this->app->enqueueMessage(
            Text::sprintf(
                'COM_J2COMMERCE_UPLOAD_MIGRATION_RESULT',
                $result['moved'],
                $result['skipped'],
                $result['orphan'],
                $result['failed']
            ),
            $result['failed'] > 0 ? 'warning' : 'success'
        );

        foreach ($result['notes'] as $name) {
            // Escaped for the same reason the report table escapes it: the name comes off disk.
            $this->app->enqueueMessage(
                Text::sprintf('COM_J2COMMERCE_UPLOAD_MIGRATION_SOURCE_REMAINS', htmlspecialchars($name, ENT_QUOTES, 'UTF-8')),
                'warning'
            );
        }

        $this->setRedirect(Route::_('index.php?option=com_j2commerce&view=uploadmigration', false));
    }
}
