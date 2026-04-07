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

use J2Commerce\Component\J2commerce\Administrator\Helper\ConfigHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\AdminController;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;
use Joomla\Database\ParameterType;

/**
 * Lengths list controller class.
 *
 * @since  6.0.2
 */
class LengthsController extends AdminController
{
    /**
     * The prefix to use with controller messages.
     *
     * IMPORTANT: Use general 'COM_J2COMMERCE' prefix so bulk action messages
     * like N_ITEMS_PUBLISHED use shared language strings, not view-specific ones.
     *
     * @var    string
     * @since  6.0.2
     */
    protected $text_prefix = 'COM_J2COMMERCE';

    /**
     * Proxy for getModel.
     *
     * @param   string  $name    The model name. Optional.
     * @param   string  $prefix  The class prefix. Optional.
     * @param   array   $config  The array of possible config values. Optional.
     *
     * @return  \Joomla\CMS\MVC\Model\BaseDatabaseModel
     *
     * @since   6.0.2
     */
    public function getModel($name = 'Length', $prefix = 'Administrator', $config = ['ignore_request' => true])
    {
        return parent::getModel($name, $prefix, $config);
    }

    /**
     * Sync length conversion values relative to the configured default length unit.
     *
     * Sets the default length unit value to 1.0 and recalculates all other
     * length values using standard conversion factors. Unknown units are
     * scaled proportionally from the previous base.
     *
     * @return  void
     *
     * @since   6.0.3
     */
    public function syncValues(): void
    {
        Session::checkToken('get') || Session::checkToken() || jexit(Text::_('JINVALID_TOKEN'));

        $redirectUrl = Route::_('index.php?option=com_j2commerce&view=lengths', false);

        // ACL check
        $user = $this->app->getIdentity();

        if (!$user->authorise('core.edit', 'com_j2commerce')) {
            $this->setMessage(Text::_('JLIB_APPLICATION_ERROR_EDITSTATE_NOT_PERMITTED'), 'error');
            $this->setRedirect($redirectUrl);

            return;
        }

        $defaultId = ConfigHelper::getDefaultLengthClassId();

        // Standard lengths: how many millimetres per 1 unit
        $mmPerUnit = [
            'mm' => 1.0,
            'cm' => 10.0,
            'm'  => 1000.0,
            'km' => 1000000.0,
            'in' => 25.4,
            'ft' => 304.8,
            'yd' => 914.4,
            'mi' => 1609344.0,
        ];

        $db         = Factory::getContainer()->get('DatabaseDriver');
        $modifiedOn = Factory::getDate()->toSql();
        $userId     = (int) $user->id;

        // Load all length records
        $query = $db->getQuery(true)
            ->select([
                $db->quoteName('j2commerce_length_id'),
                $db->quoteName('length_title'),
                $db->quoteName('length_unit'),
                $db->quoteName('length_value'),
            ])
            ->from($db->quoteName('#__j2commerce_lengths'));
        $lengths = $db->setQuery($query)->loadObjectList();

        if (empty($lengths)) {
            $this->setMessage(Text::_('COM_J2COMMERCE_LENGTHS_SYNC_NO_ITEMS'), 'warning');
            $this->setRedirect($redirectUrl);

            return;
        }

        // Find the default length
        $defaultLength = null;

        foreach ($lengths as $length) {
            if ((int) $length->j2commerce_length_id === $defaultId) {
                $defaultLength = $length;
                break;
            }
        }

        if ($defaultLength === null) {
            $this->setMessage(Text::sprintf('COM_J2COMMERCE_LENGTHS_SYNC_DEFAULT_NOT_FOUND', $defaultId), 'error');
            $this->setRedirect($redirectUrl);

            return;
        }

        $defaultUnit = strtolower(trim($defaultLength->length_unit));

        // Check if we know the default unit
        if (!isset($mmPerUnit[$defaultUnit])) {
            // Unknown default unit — just rebase proportionally
            $oldDefaultValue = (float) $defaultLength->length_value;

            if ($oldDefaultValue <= 0.0) {
                $this->setMessage(Text::_('COM_J2COMMERCE_LENGTHS_SYNC_INVALID_DEFAULT'), 'error');
                $this->setRedirect($redirectUrl);

                return;
            }

            foreach ($lengths as $length) {
                $formattedValue = number_format((float) $length->length_value / $oldDefaultValue, 8, '.', '');
                $lengthId       = (int) $length->j2commerce_length_id;
                $update         = $db->getQuery(true)
                    ->update($db->quoteName('#__j2commerce_lengths'))
                    ->set($db->quoteName('length_value') . ' = :value')
                    ->set($db->quoteName('modified_on') . ' = :modified_on')
                    ->set($db->quoteName('modified_by') . ' = :modified_by')
                    ->where($db->quoteName('j2commerce_length_id') . ' = :id')
                    ->bind(':value', $formattedValue)
                    ->bind(':modified_on', $modifiedOn)
                    ->bind(':modified_by', $userId, ParameterType::INTEGER)
                    ->bind(':id', $lengthId, ParameterType::INTEGER);
                $db->setQuery($update)->execute();
            }

            $this->setMessage(Text::sprintf('COM_J2COMMERCE_LENGTHS_SYNC_SUCCESS', $defaultLength->length_title));
            $this->setRedirect($redirectUrl);

            return;
        }

        // Known default unit — use standard conversion factors
        $mmPerDefault = $mmPerUnit[$defaultUnit];

        foreach ($lengths as $length) {
            $unit = strtolower(trim($length->length_unit));

            if (isset($mmPerUnit[$unit])) {
                $newValue = $mmPerDefault / $mmPerUnit[$unit];
            } else {
                $oldDefaultValue = (float) $defaultLength->length_value;

                if ($oldDefaultValue > 0.0) {
                    $newValue = (float) $length->length_value / $oldDefaultValue;
                } else {
                    continue;
                }
            }

            $formattedValue = number_format($newValue, 8, '.', '');
            $lengthId       = (int) $length->j2commerce_length_id;
            $update         = $db->getQuery(true)
                ->update($db->quoteName('#__j2commerce_lengths'))
                ->set($db->quoteName('length_value') . ' = :value')
                ->set($db->quoteName('modified_on') . ' = :modified_on')
                ->set($db->quoteName('modified_by') . ' = :modified_by')
                ->where($db->quoteName('j2commerce_length_id') . ' = :id')
                ->bind(':value', $formattedValue)
                ->bind(':modified_on', $modifiedOn)
                ->bind(':modified_by', $userId, ParameterType::INTEGER)
                ->bind(':id', $lengthId, ParameterType::INTEGER);
            $db->setQuery($update)->execute();
        }

        $this->setMessage(Text::sprintf('COM_J2COMMERCE_LENGTHS_SYNC_SUCCESS', $defaultLength->length_title));
        $this->setRedirect($redirectUrl);
    }
}
