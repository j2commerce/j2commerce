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

use J2Commerce\Component\J2commerce\Administrator\Helper\CustomFieldHelper;
use Joomla\CMS\Application\CMSApplication;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\AdminController;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Joomla\CMS\Router\Route;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;
use Joomla\Input\Input;
use Joomla\Utilities\ArrayHelper;

/**
 * Customfields list controller class.
 *
 * @since  6.0.4
 */
class CustomfieldsController extends AdminController
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
    public function getModel($name = 'Customfield', $prefix = 'Administrator', $config = ['ignore_request' => true])
    {
        return parent::getModel($name, $prefix, $config);
    }

    /**
     * Batch update display settings for selected custom fields.
     *
     * Core areas (billing, shipping, etc.) update DB columns directly.
     * Plugin areas update the field_display JSON column.
     *
     * @return  void
     *
     * @since   6.2.0
     */
    public function batch(): void
    {
        $this->checkToken();

        $pks = $this->input->post->get('cid', [], 'array');
        $pks = ArrayHelper::toInteger($pks);
        $pks = array_filter($pks);

        if (empty($pks)) {
            $this->setMessage(Text::_('JLIB_HTML_PLEASE_MAKE_A_SELECTION_FROM_THE_LIST'), 'warning');
            $this->setRedirect(Route::_('index.php?option=com_j2commerce&view=customfields', false));
            return;
        }

        $db = Factory::getContainer()->get(DatabaseInterface::class);

        // Core display areas — map to direct DB columns
        $coreAreas   = ['billing', 'shipping', 'payment', 'register', 'guest', 'guest_shipping'];
        $coreUpdates = [];

        foreach ($coreAreas as $area) {
            $val = $this->input->post->getString('batch_display_' . $area, '');
            if ($val !== '') {
                $coreUpdates['field_display_' . $area] = (int) $val;
            }
        }

        // Plugin display areas — stored in field_display JSON
        $pluginAreas   = CustomFieldHelper::getRegisteredAreas();
        $pluginUpdates = [];

        foreach ($pluginAreas as $area) {
            $key = $area['key'] ?? '';
            if ($key === '') {
                continue;
            }
            $val = $this->input->post->getString('batch_plugin_' . $key, '');
            if ($val !== '') {
                $pluginUpdates[$key] = (int) $val;
            }
        }

        if (empty($coreUpdates) && empty($pluginUpdates)) {
            $this->setMessage(Text::_('COM_J2COMMERCE_BATCH_NOCHANGE_MSG'), 'notice');
            $this->setRedirect(Route::_('index.php?option=com_j2commerce&view=customfields', false));
            return;
        }

        $updated    = 0;
        $user       = $this->app->getIdentity();
        $modifiedOn = Factory::getDate()->toSql();
        $userId     = (int) $user->id;

        foreach ($pks as $pk) {
            // Apply core column updates
            if (!empty($coreUpdates)) {
                $query = $db->getQuery(true)
                    ->update($db->quoteName('#__j2commerce_customfields'))
                    ->set($db->quoteName('modified_on') . ' = :modified_on')
                    ->set($db->quoteName('modified_by') . ' = :modified_by')
                    ->where($db->quoteName('j2commerce_customfield_id') . ' = ' . $pk)
                    ->bind(':modified_on', $modifiedOn)
                    ->bind(':modified_by', $userId, ParameterType::INTEGER);

                foreach ($coreUpdates as $col => $val) {
                    $query->set($db->quoteName($col) . ' = ' . $val);
                }

                $db->setQuery($query);
                $db->execute();
            }

            // Apply plugin area updates to field_display JSON
            if (!empty($pluginUpdates)) {
                $query = $db->getQuery(true)
                    ->select($db->quoteName('field_display'))
                    ->from($db->quoteName('#__j2commerce_customfields'))
                    ->where($db->quoteName('j2commerce_customfield_id') . ' = ' . $pk);
                $db->setQuery($query);
                $currentJson = (string) $db->loadResult();

                $display = json_decode($currentJson, true) ?: [];

                foreach ($pluginUpdates as $key => $val) {
                    if (!isset($display[$key]) || !\is_array($display[$key])) {
                        $display[$key] = ['enabled' => 0, 'ordering' => 0];
                    }
                    $display[$key]['enabled'] = $val;
                }

                $newJson = json_encode($display);

                $update = $db->getQuery(true)
                    ->update($db->quoteName('#__j2commerce_customfields'))
                    ->set($db->quoteName('field_display') . ' = :field_display')
                    ->set($db->quoteName('modified_on') . ' = :modified_on')
                    ->set($db->quoteName('modified_by') . ' = :modified_by')
                    ->where($db->quoteName('j2commerce_customfield_id') . ' = ' . $pk)
                    ->bind(':field_display', $newJson)
                    ->bind(':modified_on', $modifiedOn)
                    ->bind(':modified_by', $userId, ParameterType::INTEGER);
                $db->setQuery($update);
                $db->execute();
            }

            $updated++;
        }

        $this->setMessage(Text::plural('COM_J2COMMERCE_N_ITEMS_UPDATED', $updated));
        $this->setRedirect(Route::_('index.php?option=com_j2commerce&view=customfields', false));
    }

    public function saveOrderAjax(): void
    {
        $pks   = $this->input->post->get('cid', [], 'array');
        $order = $this->input->post->get('order', [], 'array');

        $pks   = ArrayHelper::toInteger($pks);
        $order = ArrayHelper::toInteger($order);

        $model  = $this->getModel();
        $return = $model->saveorder($pks, $order);

        if ($return) {
            echo '1';
        }

        Factory::getApplication()->close();
    }
}
