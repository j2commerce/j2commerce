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

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Filter\OutputFilter;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Associations;
use Joomla\CMS\Language\LanguageAssociations;
use Joomla\CMS\Language\LanguageHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;
use Joomla\Component\Content\Site\Helper\RouteHelper as ContentRouteHelper;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;

/**
 * Article helper class for J2Commerce.
 *
 * Provides static methods for retrieving and displaying Joomla articles
 * and categories, with support for multilingual associations and Falang.
 *
 * @since  6.0.0
 */
class ArticleHelper
{
    /**
     * Singleton instance
     *
     * @var   ArticleHelper|null
     * @since 6.0.0
     */
    protected static ?ArticleHelper $instance = null;

    /**
     * Get the singleton instance
     *
     * @param   array|null  $properties  Optional properties (unused, for interface compatibility)
     *
     * @return  ArticleHelper
     *
     * @since   6.0.0
     */
    public static function getInstance(?array $properties = null): ArticleHelper
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Cached database instance
     *
     * @var   DatabaseInterface|null
     * @since 6.0.0
     */
    private static ?DatabaseInterface $db = null;

    /**
     * Static article cache
     *
     * @var   array
     * @since 6.0.0
     */
    private static array $articleCache = [];

    /**
     * Static article-by-alias cache
     *
     * @var   array
     * @since 6.0.0
     */
    private static array $articleByAliasCache = [];

    /**
     * Static category cache
     *
     * @var   array
     * @since 6.0.0
     */
    private static array $categoryCache = [];

    /**
     * Get the database instance
     *
     * @return  DatabaseInterface
     *
     * @since   6.0.0
     */
    private static function getDatabase(): DatabaseInterface
    {
        if (self::$db === null) {
            self::$db = Factory::getContainer()->get(DatabaseInterface::class);
        }

        return self::$db;
    }

    // =========================================================================
    // ARTICLE DISPLAY METHODS
    // =========================================================================

    /**
     * Display article content by ID.
     *
     * Retrieves an article by ID, handles multilingual associations,
     * and optionally processes content plugins.
     *
     * @param   int   $articleId       The article ID.
     * @param   bool  $prepareContent  Whether to run content plugins.
     *
     * @return  string  The processed article HTML content or empty string.
     *
     * @since   6.0.0
     */
    public static function display(int $articleId, bool $prepareContent = false): string
    {
        if ($articleId < 1) {
            return '';
        }

        // Try to get language-associated article
        $associatedId = self::getAssociatedArticle($articleId);

        if ($associatedId > 0) {
            $articleId = $associatedId;
        }

        $article = self::getArticle($articleId);

        // Return empty if article not found
        if (!$article || empty($article->id)) {
            return '';
        }

        // Clean title ampersands
        $article->title = OutputFilter::ampReplace($article->title);

        // Combine introtext and fulltext
        $text = trim($article->introtext ?? '');
        $fulltext = trim($article->fulltext ?? '');

        if (!empty($fulltext)) {
            $text .= "\r\n\r\n" . $fulltext;
        }

        // Optionally process with content plugins
        if ($prepareContent) {
            return HTMLHelper::_('content.prepare', $text);
        }

        return $text;
    }

    // =========================================================================
    // ARTICLE RETRIEVAL METHODS
    // =========================================================================

    public static function article($name, $value, $options)
    {
        $platform = J2CommerceHelper::platform();

        $allowClear     = true;
        $allowSelect    = true;
        $languages = LanguageHelper::getContentLanguages(array(0, 1), false);
        $app = $platform->application();
        // Load language
        Factory::getApplication()->getLanguage()->load('com_content', JPATH_ADMINISTRATOR);

        // The active article id field.
        $value = (int) $value ?: '';
        $id = isset($options['id']) && !empty($options['id']) ? $options['id']: $name;
        $required = (int)isset($options['required']) && !empty($options['required']) ? $options['required']: false;
        $modalId = 'Article_' . $id;

        $wa = Factory::getApplication()->getDocument()->getWebAssetManager();
        // Add the modal field script to the document head.
        $wa->useScript('field.modal-fields');

        // Script to proxy the select modal function to the modal-fields.js file.
        if ($allowSelect)
        {
            static $scriptSelect = null;

            if (is_null($scriptSelect))
            {
                $scriptSelect = array();
            }

            if (!isset($scriptSelect[$id]))
            {
                $wa->addInlineScript("
				window.jSelectJ2Article_" . $id . " = function (id, title, catid, object, url, language) {
					window.processModalSelect('Article', '" . $id . "', id, title, catid, object, url, language);
					document.body.classList.remove('modal-open');
                    document.querySelectorAll('.modal-backdrop').forEach(el => el.remove());
				}",
                    [],
                    ['type' => 'module']
                );
                Text::script('JGLOBAL_ASSOCIATIONS_PROPAGATE_FAILED');

                $scriptSelect[$id] = true;
            }
        }

        // Setup variables for display.
        $linkArticles = 'index.php?option=com_content&amp;view=articles&amp;layout=modal&amp;tmpl=component&amp;' . Session::getFormToken() . '=1';
        $urlSelect = $linkArticles . '&amp;function=jSelectJ2Article_' . $id;
        if ($value)
        {
            $db    = Factory::getContainer()->get('DatabaseDriver');
            $query = $db->getQuery(true)
                ->select($db->quoteName('title'))
                ->from($db->quoteName('#__content'))
                ->where($db->quoteName('id') . ' = :value')
                ->bind(':value', $value);
            $db->setQuery($query);

            try
            {
                $title = $db->loadResult();
            }
            catch (\RuntimeException $e)
            {
                Factory::getApplication()->enqueueMessage($e->getMessage(), 'error');
            }
        }

        $title = empty($title) ? Text::_('COM_CONTENT_SELECT_AN_ARTICLE') : htmlspecialchars($title, ENT_QUOTES, 'UTF-8');

        $html = '<span class="input-group">';
        $html .= '<input class="form-control" id="' . $id . '_name" type="text" value="' . $title . '" readonly size="35">';

        // Select article button
        if ($allowSelect)
        {
            $html .= '<button'
                . ' class="btn btn-primary' . ($value ? ' hidden' : '') . '"'
                . ' id="' . $id . '_select"'
                . ' data-bs-toggle="modal"'
                . ' type="button"'
                . ' data-bs-target="#ModalSelect' . $modalId . '">'
                . '<span class="icon-file" aria-hidden="true"></span> ' . Text::_('JSELECT')
                . '</button>';
        }
        // Clear article button
        if ($allowClear)
        {
            $html .= '<button'
                . ' class="btn btn-secondary' . ($value ? '' : ' hidden') . '"'
                . ' id="' . $id . '_clear"'
                . ' type="button"'
                . ' onclick="window.processModalParent(\'' . $id . '\'); return false;">'
                . '<span class="icon-times" aria-hidden="true"></span> ' . Text::_('JCLEAR')
                . '</button>';
        }

        $html .= '</span>';
        $modalTitle    = Text::_('COM_CONTENT_SELECT_AN_ARTICLE');
        // Select article modal
        if ($allowSelect)
        {
            $html .= \Joomla\CMS\HTML\HTMLHelper::_(
                'bootstrap.renderModal',
                'ModalSelect' . $modalId,
                array(
                    'title'       => $modalTitle,
                    'url'         => $urlSelect,
                    'height'      => '400px',
                    'width'       => '800px',
                    'bodyHeight'  => 70,
                    'modalWidth'  => 80,
                    'footer'      => '<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">'
                        . Text::_('JLIB_HTML_BEHAVIOR_CLOSE') . '</button>',
                )
            );
        }


        $class = $required ? ' class="required modal-value"' : '';
        $html .= '<input type="hidden" id="' . $id . '_id" ' . $class . ' data-required="' . (int) $required . '" name="' . $name
            . '" data-text="' . htmlspecialchars(Text::_('COM_CONTENT_SELECT_AN_ARTICLE'), ENT_COMPAT, 'UTF-8') . '" value="' . $value . '">';

        return $html;
    }
    /**
     * Get an article by ID.
     *
     * @param   int  $id  The article ID.
     *
     * @return  object|null  Article object or null if not found.
     *
     * @since   6.0.0
     */
    public static function getArticle(int $id): ?object
    {
        if ($id < 1) {
            return null;
        }

        if (isset(self::$articleCache[$id])) {
            return self::$articleCache[$id];
        }

        $db = self::getDatabase();
        $query = $db->getQuery(true);

        $query->select('*')
            ->from($db->quoteName('#__content'))
            ->where($db->quoteName('id') . ' = :id')
            ->bind(':id', $id, ParameterType::INTEGER);

        $db->setQuery($query);
        $article = $db->loadObject();

        self::$articleCache[$id] = $article ?: null;

        return self::$articleCache[$id];
    }

    /**
     * Get an article by alias.
     *
     * Supports Falang multilingual extension if enabled.
     *
     * @param   string  $alias       The article alias.
     * @param   array   $categories  Optional category IDs to filter by.
     *
     * @return  object|null  Article object or null if not found.
     *
     * @since   6.0.0
     */
    public static function getArticleByAlias(string $alias, array $categories = []): ?object
    {
        if (empty($alias)) {
            return null;
        }

        if (isset(self::$articleByAliasCache[$alias])) {
            return self::$articleByAliasCache[$alias];
        }

        $contentId = 0;

        // Check Falang support if enabled
        if (self::isFalangInstalled()) {
            // TODO: Check J2Commerce config for 'enable_falang_support' when available
            $enableFalangSupport = false;

            if ($enableFalangSupport) {
                $contentId = self::loadFalangContentId($alias);
            }
        }

        $db = self::getDatabase();
        $query = $db->getQuery(true);

        $query->select('*')
            ->from($db->quoteName('#__content'));

        if ($contentId > 0) {
            // If Falang found a content ID, use it directly
            $query->where($db->quoteName('id') . ' = :contentId')
                ->bind(':contentId', $contentId, ParameterType::INTEGER);
        } else {
            // Search by alias with language filtering
            $query->where($db->quoteName('alias') . ' = :alias')
                ->bind(':alias', $alias);

            // Add language filter
            $tag = Factory::getApplication()->getLanguage()->getTag();

            if (!empty($tag) && $tag !== '*') {
                $query->where($db->quoteName('language') . ' IN (' . $db->quote($tag) . ',' . $db->quote('*') . ')');
            }
        }

        // Note: Category filtering commented out as it could affect stores with multiple categories
        // Uncomment and sanitize if needed in the future
        // if (!empty($categories)) {
        //     $categories = array_map('intval', $categories);
        //     $query->whereIn($db->quoteName('catid'), $categories);
        // }

        $db->setQuery($query);

        try {
            $article = $db->loadObject();
        } catch (\Exception $e) {
            $article = null;
        }

        self::$articleByAliasCache[$alias] = $article;

        return self::$articleByAliasCache[$alias];
    }

    /**
     * Get the SEF link to an article.
     *
     * @param   int  $id  The article ID.
     *
     * @return  string  The SEF URL or empty string.
     *
     * @since   6.0.0
     */
    public static function getArticleLink(int $id): string
    {
        if ($id < 1) {
            return '';
        }

        $article = self::getArticle($id);

        if (!$article) {
            return '';
        }

        return Route::_(
            ContentRouteHelper::getArticleRoute(
                (int) $article->id,
                (int) $article->catid,
                $article->language ?? ''
            )
        );
    }

    // =========================================================================
    // LANGUAGE ASSOCIATION METHODS
    // =========================================================================

    /**
     * Get the associated article for the current language.
     *
     * @param   int     $id   The article ID.
     * @param   string  $tag  Optional language tag (defaults to current).
     *
     * @return  int  The associated article ID or original ID if none found.
     *
     * @since   6.0.0
     */
    public static function getAssociatedArticle(int $id, string $tag = ''): int
    {
        if ($id < 1) {
            return $id;
        }

        if (empty($tag)) {
            $tag = Factory::getApplication()->getLanguage()->getTag();
        }

        $associations = self::getAssociations($id, 'article', $tag);

        if (isset($associations[$tag])) {
            return (int) $associations[$tag];
        }

        return $id;
    }

    /**
     * Get language associations for an article.
     *
     * @param   int     $id          The article ID.
     * @param   string  $view        The view type ('article').
     * @param   string  $currentTag  The language tag to filter by.
     *
     * @return  array  Associative array of language tag => article ID.
     *
     * @since   6.0.0
     */
    public static function getAssociations(int $id, string $view = 'article', string $currentTag = ''): array
    {
        if ($id < 1 || $view !== 'article') {
            return [];
        }

        // Check if associations are enabled
        if (!Associations::isEnabled()) {
            return [];
        }

        $user = Factory::getApplication()->getIdentity();
        $groups = implode(',', $user->getAuthorisedViewLevels());

        if (empty($currentTag)) {
            $currentTag = Factory::getApplication()->getLanguage()->getTag();
        }

        $associations = LanguageAssociations::getAssociations(
            'com_content',
            '#__content',
            'com_content.item',
            $id
        );

        $result = [];

        foreach ($associations as $tag => $item) {
            // Only process the requested language tag
            if ($tag !== $currentTag) {
                continue;
            }

            // Extract article ID from association
            $arrId = explode(':', (string) $item->id);
            $assocId = (int) $arrId[0];

            if ($assocId < 1) {
                continue;
            }

            // Verify the associated article is accessible
            $db = self::getDatabase();
            $query = $db->getQuery(true)
                ->select($db->quoteName('state'))
                ->from($db->quoteName('#__content'))
                ->where($db->quoteName('id') . ' = :assocId')
                ->where($db->quoteName('access') . ' IN (' . $groups . ')')
                ->bind(':assocId', $assocId, ParameterType::INTEGER);

            $db->setQuery($query);
            $state = (int) $db->loadResult();

            if ($state > 0) {
                $result[$tag] = $assocId;
            }
        }

        return $result;
    }

    // =========================================================================
    // FALANG INTEGRATION METHODS
    // =========================================================================

    /**
     * Check if Falang is installed and enabled.
     *
     * @return  bool  True if Falang is available.
     *
     * @since   6.0.0
     */
    public static function isFalangInstalled(): bool
    {
        if (!ComponentHelper::isInstalled('com_falang')) {
            return false;
        }

        return ComponentHelper::isEnabled('com_falang');
    }

    /**
     * Get content ID from Falang tables by alias.
     *
     * @param   string  $alias  The article alias.
     *
     * @return  int  The content ID or 0 if not found.
     *
     * @since   6.0.0
     */
    public static function loadFalangContentId(string $alias): int
    {
        if (empty($alias)) {
            return 0;
        }

        $db = self::getDatabase();
        $query = $db->getQuery(true);

        $referenceTable = 'content';
        $referenceField = 'alias';
        $published = 1;

        $query->select($db->quoteName('reference_id'))
            ->from($db->quoteName('#__falang_content'))
            ->where($db->quoteName('reference_table') . ' = :refTable')
            ->where($db->quoteName('reference_field') . ' = :refField')
            ->where($db->quoteName('published') . ' = :published')
            ->where($db->quoteName('value') . ' = :alias')
            ->bind(':refTable', $referenceTable)
            ->bind(':refField', $referenceField)
            ->bind(':published', $published, ParameterType::INTEGER)
            ->bind(':alias', $alias);

        $db->setQuery($query);
        $contentId = (int) $db->loadResult();

        return $contentId;
    }

    /**
     * Get translated alias from Falang by article ID and language.
     *
     * @param   int  $id      The article ID.
     * @param   int  $langId  The Falang language ID.
     *
     * @return  string  The translated alias or empty string.
     *
     * @since   6.0.0
     */
    public static function loadFalangAliasById(int $id, int $langId): string
    {
        if ($id < 1 || $langId < 1) {
            return '';
        }

        // Get default site language
        $params = ComponentHelper::getParams('com_languages');
        $defaultLang = $params->get('site', 'en-GB');
        $defaultLangId = self::getLanguageIdByTag($defaultLang);

        $db = self::getDatabase();
        $query = $db->getQuery(true);

        // If requesting default language, get original_text; otherwise get translated value
        if ($defaultLangId === $langId) {
            $query->select($db->quoteName('original_text'));
        } else {
            $query->select($db->quoteName('value'));
        }

        $referenceTable = 'content';
        $referenceField = 'alias';
        $published = 1;

        $query->from($db->quoteName('#__falang_content'))
            ->where($db->quoteName('reference_table') . ' = :refTable')
            ->where($db->quoteName('reference_field') . ' = :refField')
            ->where($db->quoteName('published') . ' = :published')
            ->where($db->quoteName('reference_id') . ' = :id')
            ->bind(':refTable', $referenceTable)
            ->bind(':refField', $referenceField)
            ->bind(':published', $published, ParameterType::INTEGER)
            ->bind(':id', $id, ParameterType::INTEGER);

        // Only filter by language_id for non-default languages
        if ($defaultLangId !== $langId) {
            $query->where($db->quoteName('language_id') . ' = :langId')
                ->bind(':langId', $langId, ParameterType::INTEGER);
        }

        $db->setQuery($query);

        return $db->loadResult() ?: '';
    }

    /**
     * Get Falang language ID from language tag.
     *
     * This is a helper method to map Joomla language tags to Falang language IDs.
     *
     * @param   string  $tag  The language tag (e.g., 'en-GB').
     *
     * @return  int  The Falang language ID or 0 if not found.
     *
     * @since   6.0.0
     */
    public static function getLanguageIdByTag(string $tag): int
    {
        if (empty($tag)) {
            return 0;
        }

        // Check if Falang tables exist
        if (!self::isFalangInstalled()) {
            return 0;
        }

        $db = self::getDatabase();
        $query = $db->getQuery(true);

        $query->select($db->quoteName('lang_id'))
            ->from($db->quoteName('#__falang_languages'))
            ->where($db->quoteName('lang_code') . ' = :tag')
            ->bind(':tag', $tag);

        $db->setQuery($query);

        return (int) $db->loadResult();
    }

    // =========================================================================
    // CATEGORY METHODS
    // =========================================================================

    /**
     * Get a category by ID.
     *
     * @param   int  $id  The category ID.
     *
     * @return  object|null  Category object or null if not found.
     *
     * @since   6.0.0
     */
    public static function getCategoryById(int $id): ?object
    {
        if ($id < 1) {
            return null;
        }

        if (isset(self::$categoryCache[$id])) {
            return self::$categoryCache[$id];
        }

        $db = self::getDatabase();
        $query = $db->getQuery(true);

        $query->select('*')
            ->from($db->quoteName('#__categories'))
            ->where($db->quoteName('id') . ' = :id')
            ->bind(':id', $id, ParameterType::INTEGER);

        $db->setQuery($query);
        $category = $db->loadObject();

        self::$categoryCache[$id] = $category ?: null;

        return self::$categoryCache[$id];
    }

    /**
     * Get all published categories for com_content.
     *
     * @return  array  Array of category objects.
     *
     * @since   6.0.0
     */
    public static function getContentCategories(): array
    {
        $db = self::getDatabase();
        $query = $db->getQuery(true);

        $extension = 'com_content';
        $published = 1;

        $query->select($db->quoteName(['id', 'title', 'alias', 'parent_id', 'level', 'path']))
            ->from($db->quoteName('#__categories'))
            ->where($db->quoteName('extension') . ' = :extension')
            ->where($db->quoteName('published') . ' = :published')
            ->order($db->quoteName('lft') . ' ASC')
            ->bind(':extension', $extension)
            ->bind(':published', $published, ParameterType::INTEGER);

        $db->setQuery($query);

        return $db->loadObjectList() ?: [];
    }

    // =========================================================================
    // UTILITY METHODS
    // =========================================================================

    /**
     * Clear all static caches.
     *
     * Useful for testing or when articles have been modified.
     *
     * @return  void
     *
     * @since   6.0.0
     */
    public static function clearCache(): void
    {
        self::$articleCache = [];
        self::$articleByAliasCache = [];
        self::$categoryCache = [];
    }
}
