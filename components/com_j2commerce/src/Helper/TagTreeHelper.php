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
use Joomla\CMS\Language\Multilanguage;
use Joomla\Database\DatabaseInterface;

/**
 * The published Joomla tag tree, as the Product Tags View, its router and its breadcrumbs read it.
 *
 * A tag only counts when every ancestor up to the root is published as well, the same rule the
 * Categories API applies to a category nested under an unpublished parent.
 */
final class TagTreeHelper
{
    public const ROOT_ID = 1;

    /** @var array<int, object>|null */
    private static ?array $tags = null;

    public static function get(int $id): ?object
    {
        return self::all()[$id] ?? null;
    }

    /** @return object[] Direct children in tree order. */
    public static function children(int $id): array
    {
        return array_values(array_filter(self::all(), static fn (object $tag): bool => $tag->parent_id === $id));
    }

    /** True when $id is $ancestorId itself or sits anywhere below it. */
    public static function isWithin(int $id, int $ancestorId): bool
    {
        $tag      = self::get($id);
        $ancestor = self::get($ancestorId);

        return $tag && $ancestor && $tag->lft >= $ancestor->lft && $tag->rgt <= $ancestor->rgt;
    }

    /** @return int[] Tags up to $levels below $id; 0 levels returns none. */
    public static function descendantIds(int $id, int $levels): array
    {
        $tag = self::get($id);

        if (!$tag || $levels < 1) {
            return [];
        }

        $ids = [];

        foreach (self::all() as $candidate) {
            if ($candidate->lft > $tag->lft && $candidate->rgt < $tag->rgt && $candidate->level <= $tag->level + $levels) {
                $ids[] = $candidate->id;
            }
        }

        return $ids;
    }

    /**
     * The tags from just below $ancestorId down to $id, top first. Empty when $id is $ancestorId,
     * null when $id is not inside it.
     *
     * @return object[]|null
     */
    public static function pathBelow(int $ancestorId, int $id): ?array
    {
        if (!self::isWithin($id, $ancestorId)) {
            return null;
        }

        $path = [];

        for ($tag = self::get($id); $tag && $tag->id !== $ancestorId; $tag = self::get($tag->parent_id)) {
            $path[] = $tag;
        }

        return array_reverse($path);
    }

    /** A child of $parentId by alias, preferring the request language when aliases repeat across languages. */
    public static function childByAlias(int $parentId, string $alias): ?object
    {
        $best     = null;
        $bestRank = \PHP_INT_MAX;

        foreach (self::children($parentId) as $tag) {
            if ($tag->alias !== $alias) {
                continue;
            }

            $rank = self::languageRank($tag);

            if ($rank < $bestRank) {
                $best     = $tag;
                $bestRank = $rank;
            }
        }

        return $best;
    }

    /** True when the tag and each of its ancestors are in one of $viewLevels and in the request language. */
    public static function isViewable(int $id, array $viewLevels): bool
    {
        $viewLevels = array_map('intval', $viewLevels);
        $tag        = self::get($id);

        for (; $tag && $tag->id !== self::ROOT_ID; $tag = self::get($tag->parent_id)) {
            if (!\in_array($tag->access, $viewLevels, true) || self::languageRank($tag) > 1) {
                return false;
            }
        }

        return $tag !== null;
    }

    /** 0 for the request language, 1 for All, 2 for another language; everything ranks 0 on a monolingual site. */
    private static function languageRank(object $tag): int
    {
        if (!Multilanguage::isEnabled()) {
            return 0;
        }

        if ($tag->language === Factory::getApplication()->getLanguage()->getTag()) {
            return 0;
        }

        return \in_array($tag->language, ['*', ''], true) ? 1 : 2;
    }

    /** @return array<int, object> Keyed by id, in tree order. */
    private static function all(): array
    {
        if (self::$tags !== null) {
            return self::$tags;
        }

        $db    = Factory::getContainer()->get(DatabaseInterface::class);
        $query = $db->getQuery(true)
            ->select($db->quoteName([
                'id', 'parent_id', 'lft', 'rgt', 'level', 'title', 'alias',
                'description', 'images', 'access', 'language', 'metadesc', 'metakey', 'params',
            ]))
            ->from($db->quoteName('#__tags'))
            ->where($db->quoteName('published') . ' = 1')
            ->order($db->quoteName('lft') . ' ASC');

        self::$tags = [];

        foreach ($db->setQuery($query)->loadObjectList() as $row) {
            foreach (['id', 'parent_id', 'lft', 'rgt', 'level', 'access'] as $key) {
                $row->$key = (int) $row->$key;
            }

            // Tree order visits a parent before its children, so an unpublished ancestor is
            // already missing by the time its descendants are reached.
            if ($row->id === self::ROOT_ID || isset(self::$tags[$row->parent_id])) {
                self::$tags[$row->id] = $row;
            }
        }

        return self::$tags;
    }
}
