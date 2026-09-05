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

use J2Commerce\Component\J2commerce\Administrator\Helper\OrderStatusHelper;
use Joomla\CMS\Application\CMSApplication;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Log\Log;
use Joomla\CMS\MVC\Controller\AdminController;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Joomla\CMS\Session\Session;
use Joomla\Input\Input;

/**
 * Order Statuses list controller class.
 *
 * @since  6.0.7
 */
class OrderstatusesController extends AdminController
{
    use WriteAccessTrait;

    protected string $writeAction = 'j2commerce.editsetup';

    /**
     * The prefix to use with controller messages.
     *
     * IMPORTANT: Use general 'COM_J2COMMERCE' prefix so bulk action messages
     * like N_ITEMS_PUBLISHED use shared language strings, not view-specific ones.
     *
     * @var    string
     * @since  6.0.7
     */
    protected $text_prefix = 'COM_J2COMMERCE';

    /**
     * Constructor.
     *
     * @param   array                     $config   An optional associative array of configuration settings.
     * @param   MVCFactoryInterface|null  $factory  The factory.
     * @param   CMSApplication|null       $app      The Application for the dispatcher
     * @param   Input|null                $input    Input
     *
     * @since   6.0.7
     */
    public function __construct($config = [], ?MVCFactoryInterface $factory = null, ?CMSApplication $app = null, ?Input $input = null)
    {
        parent::__construct($config, $factory, $app, $input);
    }

    /**
     * Proxy for getModel.
     *
     * @param   string  $name    The model name. Optional.
     * @param   string  $prefix  The class prefix. Optional.
     * @param   array   $config  The array of possible config values. Optional.
     *
     * @return  \Joomla\CMS\MVC\Model\BaseDatabaseModel
     *
     * @since   6.0.7
     */
    public function getModel($name = 'Orderstatus', $prefix = 'Administrator', $config = ['ignore_request' => true])
    {
        return parent::getModel($name, $prefix, $config);
    }

    /** Save one row's status type from the list's inline Apply button. */
    public function ajaxSaveType(): void
    {
        if (!$this->validateAjaxToken()) {
            $this->sendJson(['success' => false, 'message' => Text::_('JINVALID_TOKEN')]);
            return;
        }

        $identity = $this->app->getIdentity();

        if (!$identity || $identity->guest || !$identity->authorise('core.edit', 'com_j2commerce')) {
            $this->sendJson(['success' => false, 'message' => Text::_('JLIB_APPLICATION_ERROR_ACCESS_FORBIDDEN')]);
            return;
        }

        $id   = $this->input->post->getInt('orderstatus_id', 0);
        $type = trim($this->input->post->getString('orderstatus_type', ''));

        if ($id < 1 || ($type !== '' && !OrderStatusHelper::isValidType($type))) {
            $this->sendJson(['success' => false, 'message' => Text::_('COM_J2COMMERCE_ERROR_INVALID_REQUEST')]);
            return;
        }

        try {
            if ($this->getModel('Orderstatuses')->saveTypes([$id => $type]) === 0) {
                $this->sendJson(['success' => false, 'message' => Text::_('JERROR_AN_ERROR_HAS_OCCURRED')]);
                return;
            }

            $this->sendJson(['success' => true, 'message' => Text::_('COM_J2COMMERCE_STATUS_TYPE_SAVED')]);
        } catch (\Throwable $e) {
            Log::add('Failed to save order status type: ' . $e->getMessage(), Log::ERROR, 'com_j2commerce');
            $this->sendJson(['success' => false, 'message' => Text::_('JERROR_AN_ERROR_HAS_OCCURRED')]);
        }
    }

    protected function validateAjaxToken(): bool
    {
        $token       = Session::getFormToken();
        $headerToken = (string) $this->input->server->get('HTTP_X_CSRF_TOKEN', '', 'alnum');

        if ($headerToken !== '' && hash_equals($token, $headerToken)) {
            return true;
        }

        return $this->input->post->get($token, '', 'alnum') === '1';
    }

    /** JSON exit for the AJAX tasks. close() is exit(), so the headers flush first. */
    private function sendJson(mixed $data): void
    {
        $body = json_encode($data, JSON_THROW_ON_ERROR);

        $this->app->setHeader('Content-Type', 'application/json; charset=utf-8', true);
        $this->app->setHeader('Cache-Control', 'no-store, no-cache, must-revalidate', true);
        $this->app->setHeader('X-Content-Type-Options', 'nosniff', true);
        $this->app->sendHeaders();

        echo $body;
        $this->app->close();
    }
}
