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

use Joomla\CMS\Factory;
use Joomla\CMS\Layout\FileLayout;
use Joomla\Registry\Registry;

\defined('_JEXEC') or die;

/**
 * Resolves where an extension's themed partials may be overridden, so plugins and modules
 * stop each inventing their own answer.
 *
 * `FileLayout::getDefaultIncludePaths()` ranks the constructor's `$basePath` above every
 * template rung and never adds an `html/plg_*` path, so an extension that hands its own
 * tmpl dir to the constructor can never reach a template override. Building the search
 * path here and handing it to `setIncludePaths()` is what makes overrides reachable.
 *
 * @since  6.6.2
 */
final class SubtemplateHelper
{
    /** Used when nothing is selected, and as the last themed rung before the bare base path. */
    public const FALLBACK = 'bootstrap5';

    /**
     * View-scope prefixes a subtemplate name may carry. A menu item selects one variant of a
     * subtemplate per view (`tag_uikit`, `categories_bootstrap5`), and all of them belong to
     * the same owning app plugin.
     */
    private const SCOPE_PREFIXES = '/^(categories_tag_|categories_|tag_)/';

    /**
     * The subtemplate this request renders in, sanitised but with its view scope intact.
     *
     * `auto` and an empty value both mean "inherit the active menu item's choice". The result
     * is concatenated into a filesystem path, so it is reduced to the characters a folder name
     * may use rather than trusted as stored — the value is administrator-set, not public, but
     * it is still a stored string reaching a path.
     *
     * @since   6.6.2
     */
    public static function subtemplate(Registry $params): string
    {
        $subtemplate = (string) $params->get('subtemplate', 'auto');

        if ($subtemplate === '' || $subtemplate === 'auto') {
            $subtemplate = self::fromActiveMenu();
        }

        $subtemplate = preg_replace('/[^a-z0-9_-]/', '', strtolower($subtemplate)) ?? '';

        if (str_starts_with($subtemplate, 'app_')) {
            $subtemplate = substr($subtemplate, 4);
        }

        return $subtemplate === '' ? self::FALLBACK : $subtemplate;
    }

    /**
     * A subtemplate name with its `app_` and view-scope prefixes removed.
     *
     * Callers that need the owning plugin folder rather than a tmpl subfolder compose it from
     * this — `ProductLayoutService::mapSubtemplateToPluginFolder()` is the one in core. Keeping
     * the stripping here is what stops a second, divergent copy of it appearing: a name like
     * `tag_superstore` that keeps its prefix composes a folder nothing ships, and the caller is
     * then left with no include paths at all.
     *
     * @since   6.6.2
     */
    public static function normalize(string $subtemplate): string
    {
        if (str_starts_with($subtemplate, 'app_')) {
            $subtemplate = substr($subtemplate, 4);
        }

        return preg_replace(self::SCOPE_PREFIXES, '', $subtemplate) ?? $subtemplate;
    }

    /**
     * A FileLayout for one of an extension's partials, searching template overrides ahead of
     * the extension's own tmpl tree.
     *
     * @param   string    $layoutId   Layout file name, without the extension
     * @param   string    $extension  Override folder beneath a template's html/, e.g.
     *                                'plg_j2commerce_app_reviews' or 'mod_j2commerce_products'
     * @param   string    $basePath   The extension's own tmpl directory
     * @param   Registry  $params     Params carrying the 'subtemplate' setting
     *
     * @since   6.6.2
     */
    public static function layout(string $layoutId, string $extension, string $basePath, Registry $params): FileLayout
    {
        $layout = new FileLayout($layoutId);
        $layout->setIncludePaths(self::includePaths($extension, $basePath, $params));

        return $layout;
    }

    /**
     * The ordered layout search path, first hit winning.
     *
     * Both the raw subtemplate and its normalised form are tried, so an override folder keeps
     * resolving under whichever spelling its author used. Non-existent folders are left in the
     * list: `Path::find()` skips them, and probing each would cost a stat per render for a path
     * that is nearly always present.
     *
     * @return  string[]
     *
     * @since   6.6.2
     */
    public static function includePaths(string $extension, string $basePath, Registry $params): array
    {
        $extension   = trim($extension, '/');
        $basePath    = rtrim(str_replace('\\', '/', $basePath), '/');
        $subtemplate = self::subtemplate($params);
        $normalised  = self::normalize($subtemplate);
        $names       = [$subtemplate, $normalised, self::FALLBACK];
        $paths       = [];

        foreach (self::siteTemplates() as $template) {
            $overrideRoot = JPATH_THEMES . '/' . $template . '/html/' . $extension;

            $paths[] = $overrideRoot . '/' . $subtemplate;
            $paths[] = $overrideRoot . '/' . $normalised;
            $paths[] = $overrideRoot;
        }

        foreach ($names as $name) {
            $paths[] = $basePath . '/' . $name;
        }

        $paths[] = $basePath;

        return array_values(array_unique($paths));
    }

    /**
     * The active site template and, for a child template, its parent — so a child inherits the
     * parent's overrides instead of duplicating them. Empty in the administrator, where
     * JPATH_THEMES is the admin template tree and these overrides do not belong.
     *
     * @return  string[]
     *
     * @since   6.6.2
     */
    private static function siteTemplates(): array
    {
        try {
            $app = Factory::getApplication();

            if (!$app->isClient('site')) {
                return [];
            }

            $template = $app->getTemplate(true);
        } catch (\Throwable) {
            return [];
        }

        return array_values(array_filter([$template->template ?? '', $template->parent ?? '']));
    }

    /** The active menu item's subtemplate, or an empty string when there is no usable one. */
    private static function fromActiveMenu(): string
    {
        try {
            $app = Factory::getApplication();

            if (!$app->isClient('site')) {
                return '';
            }

            return (string) ($app->getMenu()?->getActive()?->getParams()->get('subtemplate') ?? '');
        } catch (\Throwable) {
            return '';
        }
    }
}
