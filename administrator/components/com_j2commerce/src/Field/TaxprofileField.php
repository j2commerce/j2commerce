<?php

/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace J2Commerce\Component\J2commerce\Administrator\Field;

\defined('_JEXEC') or die;

use J2Commerce\Component\J2commerce\Administrator\Helper\J2CommerceHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Form\Field\ListField;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Log\Log;
use Joomla\Database\DatabaseInterface;

/**
 * TaxProfile field - provides a dropdown of enabled tax profiles from the database.
 *
 * @since  6.0.7
 */
class TaxprofileField extends ListField
{
    protected $type = 'Taxprofile';

    public function getOptions(): array
    {
        $options = parent::getOptions();

        // Empty value = no tax profile. The label is configurable via the
        // `nonelabel` attribute so each form can express its own semantics
        // (product: "Not Taxable"; category default: "Use Global"). Listed
        // first so a freshly saved form defaults to it.
        $noneLabel = (string) ($this->element['nonelabel'] ?? '');
        $noneLabel = $noneLabel !== '' ? $noneLabel : 'COM_J2COMMERCE_NOT_TAXABLE';
        array_unshift($options, HTMLHelper::_('select.option', '', Text::_($noneLabel)));

        try {
            $db = Factory::getContainer()->get(DatabaseInterface::class);

            $query = $db->getQuery(true)
                ->select($db->quoteName(['j2commerce_taxprofile_id', 'taxprofile_name']))
                ->from($db->quoteName('#__j2commerce_taxprofiles'))
                ->where($db->quoteName('enabled') . ' = 1')
                ->order($db->quoteName('taxprofile_name') . ' ASC');

            $db->setQuery($query);
            $profiles = $db->loadObjectList() ?: [];

            // Let plugins inject virtual tax profiles (e.g. app_taxmanager tax
            // classes, app_avalaratax) via the same seam TaxprofilesModel uses.
            $event    = J2CommerceHelper::plugin()->event('AfterGetTaxprofiles', ['result' => $profiles]);
            $merged   = $event->getEventResult();
            $profiles = \is_array($merged) ? $merged : $profiles;

            $seenIds = [];

            foreach ($profiles as $profile) {
                if (!isset($profile->j2commerce_taxprofile_id, $profile->taxprofile_name)) {
                    continue;
                }

                // Match the SQL above: disabled profiles (plugin-injected rows carry
                // their own `enabled` flag) are not selectable.
                if (isset($profile->enabled) && !(int) $profile->enabled) {
                    continue;
                }

                // A plugin-injected profile reusing a real profile's ID would silently
                // shadow it on save — skip the duplicate instead of listing it twice.
                $id = (int) $profile->j2commerce_taxprofile_id;

                if (isset($seenIds[$id])) {
                    continue;
                }

                $seenIds[$id] = true;
                $options[]    = HTMLHelper::_('select.option', $profile->j2commerce_taxprofile_id, $profile->taxprofile_name);
            }
        } catch (\Exception $e) {
            Log::add($e->getMessage(), Log::ERROR, 'com_j2commerce');
            Factory::getApplication()->enqueueMessage(
                Text::_('JERROR_AN_ERROR_HAS_OCCURRED'),
                'error'
            );
        }

        return $options;
    }
}
