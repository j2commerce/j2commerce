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

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;
use Joomla\CMS\Language\Text;

class OverridesController extends BaseController
{
    private function requireSuperUser(): void
    {
        if (!Factory::getApplication()->getIdentity()->authorise('core.admin')) {
            throw new \RuntimeException(Text::_('JLIB_APPLICATION_ERROR_ACCESS_FORBIDDEN'), 403);
        }
    }

    public function createOverride(): void
    {
        $this->requireSuperUser();
        Session::checkToken('get') || Session::checkToken() || jexit(Text::_('JINVALID_TOKEN'));

        $plugin = $this->input->get('plugin', '', 'cmd');
        $file = $this->input->get('file', '', 'base64');

        /** @var \J2Commerce\Component\J2commerce\Administrator\Model\OverridesModel $model */
        $model = $this->getModel('Overrides', 'Administrator');

        if ($model->createOverride($plugin, $file)) {
            $this->setMessage(Text::_('COM_J2COMMERCE_OVERRIDE_CREATED'));
            $this->setRedirect(
                Route::_(
                    'index.php?option=com_j2commerce&view=overrides&plugin=' . urlencode($plugin)
                    . '&file=' . urlencode($file) . '&tab=editor',
                    false
                )
            );
        } else {
            $this->setMessage(Text::_('COM_J2COMMERCE_OVERRIDE_CREATE_FAILED') . ': ' . $model->getError(), 'error');
            $this->setRedirect(Route::_('index.php?option=com_j2commerce&view=overrides', false));
        }
    }

    public function revertOverride(): void
    {
        $this->requireSuperUser();
        Session::checkToken('get') || Session::checkToken() || jexit(Text::_('JINVALID_TOKEN'));

        $plugin = $this->input->get('plugin', '', 'cmd');
        $file = $this->input->get('file', '', 'base64');

        /** @var \J2Commerce\Component\J2commerce\Administrator\Model\OverridesModel $model */
        $model = $this->getModel('Overrides', 'Administrator');

        if ($model->revertOverride($plugin, $file)) {
            $this->setMessage(Text::_('COM_J2COMMERCE_OVERRIDE_REVERTED'));
        } else {
            $this->setMessage(Text::_('COM_J2COMMERCE_OVERRIDE_REVERT_FAILED') . ': ' . $model->getError(), 'error');
        }

        $this->setRedirect(Route::_('index.php?option=com_j2commerce&view=overrides', false));
    }

    public function save(): void
    {
        $this->requireSuperUser();
        Session::checkToken() || jexit(Text::_('JINVALID_TOKEN'));

        $data = $this->input->post->get('jform', [], 'array');
        $plugin = $this->input->get('plugin', '', 'cmd');
        $file = $this->input->get('file', '', 'base64');

        /** @var \J2Commerce\Component\J2commerce\Administrator\Model\OverridesModel $model */
        $model = $this->getModel('Overrides', 'Administrator');

        $saved = false;

        if (!empty($plugin) && !empty($file)) {
            // Detect file ID format: subtemplate editor uses "type:folder:path",
            // tree editor uses raw relative paths (no colons)
            $decoded = base64_decode($file);
            if (str_contains($decoded, ':')) {
                $saved = $model->saveSource($plugin, $file, $data['source'] ?? '');
            } else {
                $saved = $model->saveSourceByPath($file, $data['source'] ?? '');
            }
        } elseif (!empty($file)) {
            $saved = $model->saveSourceByPath($file, $data['source'] ?? '');
        }

        if ($saved) {
            $this->setMessage(Text::_('COM_J2COMMERCE_OVERRIDE_SAVED'));
        } else {
            $this->setMessage(Text::_('COM_J2COMMERCE_OVERRIDE_SAVE_FAILED') . ': ' . $model->getError(), 'error');
        }

        $redirect = 'index.php?option=com_j2commerce&view=overrides&tab=editor';
        if ($plugin) {
            $redirect .= '&plugin=' . urlencode($plugin);
        }
        if ($file) {
            $redirect .= '&file=' . urlencode($file);
        }

        $this->setRedirect(Route::_($redirect, false));
    }

    public function apply(): void
    {
        $this->save();
    }

    public function cancel(): void
    {
        $this->setRedirect(Route::_('index.php?option=com_j2commerce&view=overrides', false));
    }

}
