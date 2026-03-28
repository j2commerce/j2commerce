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

use Joomla\CMS\Application\CMSApplication;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\AdminController;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Joomla\CMS\Response\JsonResponse;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;
use Joomla\Event\Event;
use Joomla\Input\Input;

/**
 * Currencies list controller class.
 *
 * @since  6.0.4
 */
class CurrenciesController extends AdminController
{
    /**
     * The prefix to use with controller messages.
     *
     * IMPORTANT: Use general 'COM_J2COMMERCE' prefix so bulk action messages
     * like N_ITEMS_PUBLISHED use shared language strings, not view-specific ones.
     *
     * @var    string
     * @since  6.0.4
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
     * @since   6.0.4
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
     * @since   6.0.4
     */
    public function getModel($name = 'Currency', $prefix = 'Administrator', $config = ['ignore_request' => true])
    {
        return parent::getModel($name, $prefix, $config);
    }

    /**
     * Preserve tmpl parameter in list redirects (modal support).
     *
     * @return  string
     *
     * @since   6.1.5
     */
    protected function getRedirectToListAppend()
    {
        $tmpl = $this->input->getCmd('tmpl', '');

        return $tmpl ? '&tmpl=' . $tmpl : '';
    }

    public function updateRates(): void
    {
        $this->checkToken();

        if (!$this->app->getIdentity()->authorise('core.manage', 'com_j2commerce')) {
            throw new \RuntimeException(Text::_('JLIB_APPLICATION_ERROR_ACCESS_FORBIDDEN'), 403);
        }

        if (!PluginHelper::isEnabled('j2commerce', 'app_currencyupdater')) {
            $this->app->enqueueMessage(
                Text::_('COM_J2COMMERCE_CURRENCY_RATES_PLUGIN_DISABLED'),
                'error'
            );
            $this->setRedirect(Route::_('index.php?option=com_j2commerce&view=currencies', false));

            return;
        }

        PluginHelper::importPlugin('j2commerce');

        $dispatcher = $this->app->getDispatcher();
        $event = new Event('onJ2CommerceUpdateCurrencies', []);
        $dispatcher->dispatch('onJ2CommerceUpdateCurrencies', $event);

        $results = $event->getArgument('result', null);

        if ($results && !empty($results['errors'])) {
            foreach ($results['errors'] as $error) {
                $this->app->enqueueMessage($error, 'warning');
            }
        }

        if ($results && $results['updated'] > 0) {
            $this->app->enqueueMessage(
                Text::sprintf('COM_J2COMMERCE_CURRENCY_RATES_UPDATED', $results['updated']),
                'success'
            );
        } elseif (!$results || $results['updated'] === 0) {
            $this->app->enqueueMessage(
                Text::_('COM_J2COMMERCE_CURRENCY_RATES_UPDATE_FAILED'),
                'error'
            );
        }

        $this->setRedirect(Route::_('index.php?option=com_j2commerce&view=currencies', false));
    }

    public function disableAllExcept(): void
    {
        Session::checkToken('post') or die('Invalid Token');

        if (!$this->app->getIdentity()->authorise('core.manage', 'com_j2commerce')) {
            $this->app->setHeader('status', 403);
            echo new JsonResponse(null, Text::_('JLIB_APPLICATION_ERROR_ACCESS_FORBIDDEN'), true);
            $this->app->close();
        }

        $currencyCode = $this->input->post->getString('currency_code', '');
        if (!preg_match('/^[A-Z]{3}$/', $currencyCode)) {
            $this->app->setHeader('status', 400);
            echo new JsonResponse(null, 'Invalid currency code.', true);
            $this->app->close();
        }

        $db = $this->app->getContainer()->get(DatabaseInterface::class);

        // Disable all except the selected currency
        $query = $db->getQuery(true)
            ->update($db->quoteName('#__j2commerce_currencies'))
            ->set($db->quoteName('enabled') . ' = 0')
            ->where($db->quoteName('currency_code') . ' != :code')
            ->bind(':code', $currencyCode);
        $db->setQuery($query);
        $db->execute();
        $disabledCount = $db->getAffectedRows();

        // Ensure the default currency stays enabled
        $query = $db->getQuery(true)
            ->update($db->quoteName('#__j2commerce_currencies'))
            ->set($db->quoteName('enabled') . ' = 1')
            ->where($db->quoteName('currency_code') . ' = :code')
            ->bind(':code', $currencyCode);
        $db->setQuery($query);
        $db->execute();

        $message = Text::sprintf('COM_J2COMMERCE_CURRENCY_DISABLE_SUCCESS', $disabledCount);
        echo new JsonResponse(null, $message);
        $this->app->close();
    }
}