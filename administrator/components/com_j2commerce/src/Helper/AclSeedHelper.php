<?php

/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace J2Commerce\Component\J2commerce\Administrator\Helper;

\defined('_JEXEC') or die;

use Joomla\CMS\Access\Access;
use Joomla\CMS\Factory;
use Joomla\CMS\Log\Log;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;
use Joomla\Registry\Registry;

/**
 * One-time, idempotent seeding of the custom ACL actions declared in access.xml.
 *
 * Every group that can already manage com_j2commerce gets an explicit allow on each custom
 * action it does not yet resolve, so existing staff keep the order, product, report and setup
 * screens once J2CommerceHelper::canAccess() stops falling back to core.manage. Groups holding
 * an explicit deny are left alone; Super Users need no rule because User::authorise()
 * short-circuits on core.admin.
 *
 * Runs from the installer's postflight and again from the component boot, because a site
 * deployed by git pull, rsync or FTP never runs postflight and would otherwise keep the
 * fallback — and therefore an unenforceable access.xml — indefinitely.
 *
 * @since  6.2.0
 */
class AclSeedHelper
{
    /** The custom actions declared in access.xml. */
    public const CUSTOM_ACL_ACTIONS = [
        'j2commerce.vieworders',
        'j2commerce.editorders',
        'j2commerce.viewproducts',
        'j2commerce.viewreports',
        'j2commerce.viewsetup',
    ];

    /** Extension-params key marking the seed as done; canAccess() reads it too. */
    public const ACL_SEED_FLAG = 'acl_custom_actions_seeded';

    /**
     * Seed the custom actions unless the flag is already set.
     *
     * @return  bool  True when the flag is set on return, false when the seed could not complete
     *                and the core.manage fallback therefore stays active.
     */
    public static function ensureSeeded(?callable $logger = null): bool
    {
        $log = $logger ?? static function (string $message): void {
            Log::add($message, Log::DEBUG, 'com_j2commerce');
        };

        $db = Factory::getContainer()->get(DatabaseInterface::class);

        $extQuery = $db->getQuery(true)
            ->select([$db->quoteName('extension_id'), $db->quoteName('params')])
            ->from($db->quoteName('#__extensions'))
            ->where($db->quoteName('element') . ' = ' . $db->quote('com_j2commerce'))
            ->where($db->quoteName('type') . ' = ' . $db->quote('component'));
        $db->setQuery($extQuery);
        $extension = $db->loadObject();

        if (!$extension) {
            $log('ACL SEED: com_j2commerce extension row not found — skipped');

            return false;
        }

        $params = new Registry($extension->params);

        if ((int) $params->get(self::ACL_SEED_FLAG, 0) === 1) {
            $log('ACL SEED: already seeded — skipped');

            return true;
        }

        $assetQuery = $db->getQuery(true)
            ->select([$db->quoteName('id'), $db->quoteName('rules')])
            ->from($db->quoteName('#__assets'))
            ->where($db->quoteName('name') . ' = ' . $db->quote('com_j2commerce'));
        $db->setQuery($assetQuery);
        $asset = $db->loadObject();

        if (!$asset) {
            $log('ACL SEED: com_j2commerce asset row not found — retry on next update');

            return false;
        }

        $trimmedRules = trim((string) $asset->rules);

        if ($trimmedRules === '') {
            // Legitimately empty — a fresh install before any permission is set.
            $rules = [];
        } else {
            $decoded = json_decode($trimmedRules, true);

            if (!\is_array($decoded)) {
                // Rebuilding from an empty array here would silently drop core.fulfilment
                // and every other rule on the row. Leave it alone, leave the flag unset,
                // and let a human look at it.
                $log('ACL SEED: com_j2commerce asset rules are not valid JSON — aborted, row left untouched');

                return false;
            }

            $rules = $decoded;
        }

        $groupQuery = $db->getQuery(true)
            ->select($db->quoteName('id'))
            ->from($db->quoteName('#__usergroups'));
        $db->setQuery($groupQuery);
        $groupIds = array_map('intval', (array) $db->loadColumn());

        // Rules are about to be read through the Access layer; drop anything cached
        // from earlier in this request so the resolution reflects the stored row.
        Access::clearStatics();

        $granted = 0;

        foreach ($groupIds as $groupId) {
            if (Access::checkGroup($groupId, 'core.manage', 'com_j2commerce') !== true) {
                continue;
            }

            foreach (self::CUSTOM_ACL_ACTIONS as $action) {
                // null = nothing set anywhere in the group's path. false = an explicit
                // deny an administrator entered; honour it rather than overwrite it.
                if (Access::checkGroup($groupId, $action, 'com_j2commerce') !== null) {
                    continue;
                }

                $rules[$action][(string) $groupId] = 1;
                $granted++;
            }
        }

        if ($granted > 0) {
            // FORCE_OBJECT so a per-action identity map can never serialise as a
            // JSON array, which Access would then read back with the wrong keys.
            $rulesJson = json_encode($rules, JSON_FORCE_OBJECT);
            $assetId   = (int) $asset->id;

            $updateAsset = $db->getQuery(true)
                ->update($db->quoteName('#__assets'))
                ->set($db->quoteName('rules') . ' = :rules')
                ->where($db->quoteName('id') . ' = :id')
                ->bind(':rules', $rulesJson)
                ->bind(':id', $assetId, ParameterType::INTEGER);

            $db->setQuery($updateAsset)->execute();
            Access::clearStatics();
        }

        $params->set(self::ACL_SEED_FLAG, 1);
        self::writeExtensionParams($db, $params, (int) $extension->extension_id);

        $log("ACL SEED: {$granted} explicit allow(s) written");

        return true;
    }

    private static function writeExtensionParams(DatabaseInterface $db, Registry $params, int $extensionId): void
    {
        $paramsJson = $params->toString();

        $query = $db->getQuery(true)
            ->update($db->quoteName('#__extensions'))
            ->set($db->quoteName('params') . ' = :params')
            ->where($db->quoteName('extension_id') . ' = :id')
            ->bind(':params', $paramsJson)
            ->bind(':id', $extensionId, ParameterType::INTEGER);

        $db->setQuery($query)->execute();
    }
}
