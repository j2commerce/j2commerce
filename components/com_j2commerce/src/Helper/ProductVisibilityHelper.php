<?php

/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace J2Commerce\Component\J2commerce\Site\Helper;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;

/**
 * Single source of truth for whether a product may be shown on the frontend.
 *
 * Every frontend surface that resolves a product from request input routes
 * through here, so the model, the controller tasks and the schema plugin
 * cannot drift apart.
 */
final class ProductVisibilityHelper
{
    private static array $cache = [];

    /**
     * Whether the current identity may preview products the shop does not publish yet.
     *
     * Component scope, matching com_content's ArticlesModel::populateState(). core.edit.own
     * is deliberately excluded — core grants an owner the edit button, not the preview.
     */
    public static function isEditor(): bool
    {
        $user = Factory::getApplication()->getIdentity();

        return $user && ($user->authorise('core.edit', 'com_j2commerce') || $user->authorise('core.edit.state', 'com_j2commerce'));
    }

    /**
     * Carries the same predicates as the product listings, except p.visibility —
     * that hides a product from the catalog only, so a hidden product stays
     * reachable by direct link.
     *
     * Users who may edit content skip the state and publish-window conditions so
     * unpublished products stay previewable, but never the view-access levels.
     *
     * $allowUnpublishedPreview is that skip, made explicit. It defaults to true because
     * previewing draft work is the whole point on a RENDER surface. A caller that is not
     * rendering the catalogue — one that emails product details out, hands them to a third
     * party, or otherwise acts on the product rather than showing it — should pass false:
     * there, a single core.edit or core.edit.state grant would otherwise drop five predicates
     * at once, and "may preview a draft" is not the same permission as "may have it sent".
     */
    public static function isViewable(int $productId, bool $allowUnpublishedPreview = true): bool
    {
        if ($productId <= 0) {
            return false;
        }

        $user     = Factory::getApplication()->getIdentity();
        $groups   = $user ? $user->getAuthorisedViewLevels() : [1];
        $isEditor = $allowUnpublishedPreview && self::isEditor();

        // Keyed on identity, not just the product: the answer below embeds both the authorised
        // view levels and the editor outcome, so a request that resolves more than one identity
        // (console, scheduled task, webservices) would otherwise serve the first caller's answer
        // to every later one. ProductsModel and ProducttagsModel already key their caches this way.
        $cacheKey = $productId . '|' . (int) $isEditor . '|' . implode(',', $groups);

        if (isset(self::$cache[$cacheKey])) {
            return self::$cache[$cacheKey];
        }

        $db    = Factory::getContainer()->get(DatabaseInterface::class);
        $query = $db->getQuery(true)
            ->select('1')
            ->from($db->quoteName('#__j2commerce_products', 'p'))
            ->join(
                'INNER',
                $db->quoteName('#__content', 'c')
                . ' ON ' . $db->quoteName('c.id') . ' = ' . $db->quoteName('p.product_source_id')
                . ' AND ' . $db->quoteName('p.product_source') . ' = ' . $db->quote('com_content')
            )
            ->join(
                'LEFT',
                $db->quoteName('#__categories', 'cat')
                . ' ON ' . $db->quoteName('cat.id') . ' = ' . $db->quoteName('c.catid')
            )
            ->where($db->quoteName('p.j2commerce_product_id') . ' = :pk')
            ->whereIn($db->quoteName('c.access'), $groups)
            ->whereIn($db->quoteName('cat.access'), $groups)
            ->bind(':pk', $productId, ParameterType::INTEGER);

        if (!$isEditor) {
            $nowDate = Factory::getDate()->toSql();

            $query->where($db->quoteName('p.enabled') . ' = 1')
                ->where($db->quoteName('c.state') . ' = 1')
                ->where($db->quoteName('cat.published') . ' = 1')
                ->where('(' . $db->quoteName('c.publish_up') . ' IS NULL OR ' . $db->quoteName('c.publish_up') . ' <= :publishUp)')
                ->where('(' . $db->quoteName('c.publish_down') . ' IS NULL OR ' . $db->quoteName('c.publish_down') . ' >= :publishDown)')
                ->bind(':publishUp', $nowDate)
                ->bind(':publishDown', $nowDate);
        }

        return self::$cache[$cacheKey] = (bool) $db->setQuery($query, 0, 1)->loadResult();
    }
}
