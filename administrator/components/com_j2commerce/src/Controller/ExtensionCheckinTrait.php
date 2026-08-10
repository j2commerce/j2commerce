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

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Session\Session;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;

/**
 * Check-in for the four lists backed by #__extensions.
 *
 * FormModel::checkin() already refuses a row held by someone else, but AdminModel::checkin()
 * returns false at the first refusal, so one such row in a selection abandoned the rest — and
 * the four controllers reported the submitted count as released either way. Narrowing the ids
 * first releases what the caller may release, and makes the reported count the real one.
 */
trait ExtensionCheckinTrait
{
    public function checkin()
    {
        Session::checkToken() or jexit(Text::_('JINVALID_TOKEN'));

        $cid = array_filter(array_map('intval', (array) $this->input->get('cid', [], 'int')));

        if (empty($cid)) {
            $this->setMessage(Text::_('COM_J2COMMERCE_NO_ITEM_SELECTED'), 'warning');
        } else {
            $cid = $this->releasableCheckins($cid);

            try {
                if ($cid !== []) {
                    $this->getModel()->checkin($cid);
                }

                $this->setMessage(Text::plural('COM_J2COMMERCE_N_ITEMS_CHECKED_IN', \count($cid)));
            } catch (\Exception $e) {
                $this->setMessage($e->getMessage(), 'error');
            }
        }

        $this->setRedirect($this->getRedirectUrlToList());
    }

    /**
     * The rows this user may release: any checked-out row when they hold core.manage on
     * com_checkin, otherwise only the rows holding their own id. Same test FormModel::checkin()
     * applies per row, asked once up front so a refusal narrows the batch instead of ending it.
     */
    private function releasableCheckins(array $cid): array
    {
        $user   = $this->app->getIdentity();
        $userId = (int) $user->id;

        $db    = Factory::getContainer()->get(DatabaseInterface::class);
        $query = $db->getQuery(true)
            ->select($db->quoteName('extension_id'))
            ->from($db->quoteName('#__extensions'))
            ->whereIn($db->quoteName('extension_id'), $cid)
            ->where($db->quoteName('checked_out') . ' > 0');

        if (!$user->authorise('core.manage', 'com_checkin')) {
            $query->where($db->quoteName('checked_out') . ' = :userId')
                ->bind(':userId', $userId, ParameterType::INTEGER);
        }

        $db->setQuery($query);

        return array_map('intval', (array) $db->loadColumn());
    }
}
