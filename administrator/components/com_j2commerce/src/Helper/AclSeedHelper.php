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
        'j2commerce.exportorders',
        'j2commerce.viewproducts',
        'j2commerce.editproducts',
        'j2commerce.viewreports',
        'j2commerce.viewsetup',
        'j2commerce.editsetup',
    ];

    /** Extension-params key marking the seed as done; canAccess() reads it too. */
    public const ACL_SEED_FLAG = 'acl_custom_actions_seeded';

    /**
     * Bump whenever an action is added to CUSTOM_ACL_ACTIONS.
     *
     * ACL_SEED_FLAG alone cannot express this: it is already 1 everywhere, so a new action
     * would never be seeded, and canAccess() would resolve it to false for every group that
     * is not a Super User — silently removing access the merchant never changed. The version
     * lets the seed run again for the actions that have no rule yet, and the pass is a no-op
     * for the ones that already do.
     *
     * 2 — 6.5.0 added j2commerce.editproducts and j2commerce.editsetup.
     * 3 — 6.5.0 added j2commerce.exportorders.
     */
    public const SEED_VERSION = 3;

    /** Extension-params key holding the version of CUSTOM_ACL_ACTIONS last seeded. */
    public const SEED_VERSION_FLAG = 'acl_seed_version';

    /** Seed version each action first shipped in. Anything not listed shipped at version 1. */
    private const ACTION_SEED_VERSION = [
        'j2commerce.editproducts' => 2,
        'j2commerce.editsetup'    => 2,
        'j2commerce.exportorders' => 3,
    ];

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

        $params  = new Registry($extension->params);
        $pending = self::pendingActions($params);

        if (!$pending) {
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

            foreach ($pending as $action) {
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
            $rulesJson     = json_encode($rules, JSON_FORCE_OBJECT);
            $expectedRules = (string) $asset->rules;
            $assetId       = (int) $asset->id;

            // The row was read before the Access work above, so an administrator may have
            // saved Options → Permissions since. Only write over the value this ran against;
            // a mismatch means their save would be lost, so leave the flag unset and let the
            // next request start over from the row they wrote.
            $updateAsset = $db->getQuery(true)
                ->update($db->quoteName('#__assets'))
                ->set($db->quoteName('rules') . ' = :rules')
                ->where($db->quoteName('id') . ' = :id')
                ->where('CAST(' . $db->quoteName('rules') . ' AS BINARY) = :expected')
                ->bind(':rules', $rulesJson)
                ->bind(':expected', $expectedRules)
                ->bind(':id', $assetId, ParameterType::INTEGER);

            $db->setQuery($updateAsset)->execute();

            if ($db->getAffectedRows() === 0) {
                $log('ACL SEED: asset rules changed while seeding — aborted, flag left unset');

                return false;
            }

            Access::clearStatics();
        }

        // Re-read the params before setting the flag: they were loaded before the Access work
        // and writing that copy back would revert any component setting saved since. A failed
        // compare here can leave rules written without the flag, which is harmless — the next
        // run resolves those actions as already set, grants nothing and sets the flag then.
        if (!self::writeSeedFlag($db, (int) $extension->extension_id)) {
            $log('ACL SEED: extension params changed while seeding — flag left unset');

            return false;
        }

        $log("ACL SEED: {$granted} explicit allow(s) written");

        return true;
    }

    /**
     * The actions this site has not been seeded for yet.
     *
     * Per action rather than a single global "is the seed current" answer, because the two
     * are not the same thing once a version is added. A merchant who set one of the older
     * actions back to Inherited leaves no rule behind — Joomla's RulesFilter drops an empty
     * identity and Rules omits an action with no identities — which is indistinguishable
     * from never-configured. Re-running the whole list on a version bump would write that
     * allow back and hand the group access the merchant had deliberately removed.
     */
    public static function pendingActions(Registry $params): array
    {
        $seeded = (int) $params->get(self::SEED_VERSION_FLAG, 0);

        // Seeded before the version marker existed: everything shipped at version 1 is done.
        if ($seeded === 0 && (int) $params->get(self::ACL_SEED_FLAG, 0) === 1) {
            $seeded = 1;
        }

        return array_values(array_filter(
            self::CUSTOM_ACL_ACTIONS,
            static fn (string $action): bool => (self::ACTION_SEED_VERSION[$action] ?? 1) > $seeded
        ));
    }

    /** Is this one action still awaiting its seed? The core.manage fallback keys on this. */
    public static function isPendingSeed(string $action, Registry $params): bool
    {
        return \in_array($action, self::pendingActions($params), true);
    }

    /** Is there anything left to seed? The component boot asks this. */
    public static function needsSeeding(Registry $params): bool
    {
        return self::pendingActions($params) !== [];
    }

    /** Set the seed flag on the current params. False when the row changed since it was read. */
    private static function writeSeedFlag(DatabaseInterface $db, int $extensionId): bool
    {
        $query = $db->getQuery(true)
            ->select($db->quoteName('params'))
            ->from($db->quoteName('#__extensions'))
            ->where($db->quoteName('extension_id') . ' = :id')
            ->bind(':id', $extensionId, ParameterType::INTEGER);
        $db->setQuery($query);
        $current = (string) $db->loadResult();

        $params = new Registry($current);
        $params->set(self::ACL_SEED_FLAG, 1);
        $params->set(self::SEED_VERSION_FLAG, self::SEED_VERSION);

        return self::writeExtensionParams($db, $params, $extensionId, $current);
    }

    /**
     * Write params only while the stored value still matches $expected, byte for byte.
     *
     * The comparison is CAST to BINARY because the default utf8mb4_unicode_ci is
     * case-insensitive, accent-insensitive and PAD SPACE: a plain = accepts a concurrent save
     * that differs only in letter case, accents or trailing whitespace and overwrites it.
     * utf8mb4_bin is PAD SPACE too, and the NO PAD utf8mb4_0900_bin does not exist on MariaDB.
     */
    private static function writeExtensionParams(
        DatabaseInterface $db,
        Registry $params,
        int $extensionId,
        string $expected
    ): bool {
        $paramsJson = $params->toString();

        $query = $db->getQuery(true)
            ->update($db->quoteName('#__extensions'))
            ->set($db->quoteName('params') . ' = :params')
            ->where($db->quoteName('extension_id') . ' = :id')
            ->where('CAST(' . $db->quoteName('params') . ' AS BINARY) = :expected')
            ->bind(':params', $paramsJson)
            ->bind(':expected', $expected)
            ->bind(':id', $extensionId, ParameterType::INTEGER);

        $db->setQuery($query)->execute();

        return $db->getAffectedRows() > 0;
    }
}
