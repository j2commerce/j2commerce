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
 * Weights list controller class.
 *
 * @since  6.0.2
 */
class WeightsController extends AdminController
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
    public function getModel($name = 'Weight', $prefix = 'Administrator', $config = ['ignore_request' => true])
    {
        return parent::getModel($name, $prefix, $config);
    }

    /**
     * Sync weight conversion values relative to the configured default weight unit.
     *
     * Sets the default weight unit value to 1.0 and recalculates all other
     * weight values using standard conversion factors. Unknown units are
     * scaled proportionally from the previous base.
     *
     * @return  void
     *
     * @since   6.0.3
     */
    public function syncValues(): void
    {
        Session::checkToken('get') || Session::checkToken() || jexit(Text::_('JINVALID_TOKEN'));

        $redirectUrl = Route::_('index.php?option=com_j2commerce&view=weights', false);

        // ACL check
        $user = $this->app->getIdentity();

        if (!$user->authorise('core.edit', 'com_j2commerce')) {
            $this->setMessage(Text::_('JLIB_APPLICATION_ERROR_EDITSTATE_NOT_PERMITTED'), 'error');
            $this->setRedirect($redirectUrl);

            return;
        }

        $defaultId = ConfigHelper::getDefaultWeightClassId();

        // Standard weights: how many grams per 1 unit
        $gramsPerUnit = [
            'kg' => 1000.0,
            'g'  => 1.0,
            'mg' => 0.001,
            'oz' => 28.3495231,
            'lb' => 453.59237,
            't'  => 1000000.0,
        ];

        $db         = Factory::getContainer()->get('DatabaseDriver');
        $modifiedOn = Factory::getDate()->toSql();
        $userId     = (int) $user->id;

        // Load all weight records
        $query = $db->getQuery(true)
            ->select([
                $db->quoteName('j2commerce_weight_id'),
                $db->quoteName('weight_title'),
                $db->quoteName('weight_unit'),
                $db->quoteName('weight_value'),
            ])
            ->from($db->quoteName('#__j2commerce_weights'));
        $weights = $db->setQuery($query)->loadObjectList();

        if (empty($weights)) {
            $this->setMessage(Text::_('COM_J2COMMERCE_WEIGHTS_SYNC_NO_ITEMS'), 'warning');
            $this->setRedirect($redirectUrl);

            return;
        }

        // Find the default weight
        $defaultWeight = null;

        foreach ($weights as $weight) {
            if ((int) $weight->j2commerce_weight_id === $defaultId) {
                $defaultWeight = $weight;
                break;
            }
        }

        if ($defaultWeight === null) {
            $this->setMessage(Text::sprintf('COM_J2COMMERCE_WEIGHTS_SYNC_DEFAULT_NOT_FOUND', $defaultId), 'error');
            $this->setRedirect($redirectUrl);

            return;
        }

        $defaultUnit = strtolower(trim($defaultWeight->weight_unit));

        // Check if we know the default unit
        if (!isset($gramsPerUnit[$defaultUnit])) {
            // Unknown default unit — just rebase proportionally
            $oldDefaultValue = (float) $defaultWeight->weight_value;

            if ($oldDefaultValue <= 0.0) {
                $this->setMessage(Text::_('COM_J2COMMERCE_WEIGHTS_SYNC_INVALID_DEFAULT'), 'error');
                $this->setRedirect($redirectUrl);

                return;
            }

            foreach ($weights as $weight) {
                $formattedValue = number_format((float) $weight->weight_value / $oldDefaultValue, 8, '.', '');
                $weightId       = (int) $weight->j2commerce_weight_id;
                $update         = $db->getQuery(true)
                    ->update($db->quoteName('#__j2commerce_weights'))
                    ->set($db->quoteName('weight_value') . ' = :value')
                    ->set($db->quoteName('modified_on') . ' = :modified_on')
                    ->set($db->quoteName('modified_by') . ' = :modified_by')
                    ->where($db->quoteName('j2commerce_weight_id') . ' = :id')
                    ->bind(':value', $formattedValue)
                    ->bind(':modified_on', $modifiedOn)
                    ->bind(':modified_by', $userId, ParameterType::INTEGER)
                    ->bind(':id', $weightId, ParameterType::INTEGER);
                $db->setQuery($update)->execute();
            }

            $this->setMessage(Text::sprintf('COM_J2COMMERCE_WEIGHTS_SYNC_SUCCESS', $defaultWeight->weight_title));
            $this->setRedirect($redirectUrl);

            return;
        }

        // Known default unit — use standard conversion factors
        $gramsPerDefault = $gramsPerUnit[$defaultUnit];

        foreach ($weights as $weight) {
            $unit = strtolower(trim($weight->weight_unit));

            if (isset($gramsPerUnit[$unit])) {
                $newValue = $gramsPerDefault / $gramsPerUnit[$unit];
            } else {
                $oldDefaultValue = (float) $defaultWeight->weight_value;

                if ($oldDefaultValue > 0.0) {
                    $newValue = (float) $weight->weight_value / $oldDefaultValue;
                } else {
                    continue;
                }
            }

            $formattedValue = number_format($newValue, 8, '.', '');
            $weightId       = (int) $weight->j2commerce_weight_id;
            $update         = $db->getQuery(true)
                ->update($db->quoteName('#__j2commerce_weights'))
                ->set($db->quoteName('weight_value') . ' = :value')
                ->set($db->quoteName('modified_on') . ' = :modified_on')
                ->set($db->quoteName('modified_by') . ' = :modified_by')
                ->where($db->quoteName('j2commerce_weight_id') . ' = :id')
                ->bind(':value', $formattedValue)
                ->bind(':modified_on', $modifiedOn)
                ->bind(':modified_by', $userId, ParameterType::INTEGER)
                ->bind(':id', $weightId, ParameterType::INTEGER);
            $db->setQuery($update)->execute();
        }

        $this->setMessage(Text::sprintf('COM_J2COMMERCE_WEIGHTS_SYNC_SUCCESS', $defaultWeight->weight_title));
        $this->setRedirect($redirectUrl);
    }
}
