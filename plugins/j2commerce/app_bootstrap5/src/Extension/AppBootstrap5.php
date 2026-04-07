<?php

/**
 * @package     J2Commerce
 * @subpackage  Plugin.J2Commerce.AppBootstrap5
 *
 * @copyright   Copyright (C) 2025 J2Commerce, LLC. All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

namespace J2Commerce\Plugin\J2Commerce\AppBootstrap5\Extension;

use J2Commerce\Component\J2commerce\Administrator\Helper\J2CommerceHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Layout\FileLayout;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\Database\DatabaseAwareTrait;
use Joomla\Event\Event;
use Joomla\Event\EventInterface;
use Joomla\Event\SubscriberInterface;
use Joomla\Registry\Registry;

\defined('_JEXEC') or die;

/**
 * Bootstrap 5 Layout Plugin for J2Commerce
 *
 * Provides Bootstrap 5 templates for product list and detail views
 *
 * @since  6.0.0
 */
final class AppBootstrap5 extends CMSPlugin implements SubscriberInterface
{
    use DatabaseAwareTrait;

    /**
     * Load the language file on instantiation.
     *
     * @var    boolean
     * @since  6.0.0
     */
    protected $autoloadLanguage = true;

    /**
     * Plugin element name (for backward compatibility)
     *
     * @var    string
     * @since  6.0.0
     */
    protected $_element = 'app_bootstrap5';

    /**
     * Flag to track if site assets have been loaded
     *
     * @var    bool
     * @since  6.0.0
     */
    protected $assetsLoaded = false;

    /**
     * Returns an array of events this subscriber will listen to.
     *
     * @return  array
     *
     * @since   5.0.0
     */
    public static function getSubscribedEvents(): array
    {
        return [
            'onJ2CommerceTemplateFolderList'     => 'onTemplateFolderList',
            'onJ2CommerceViewProductListHtml'    => 'onViewProductListHtml',
            'onJ2CommerceViewProductListTagHtml' => 'onViewProductListTagHtml',
            'onJ2CommerceViewProductHtml'        => 'onViewProductHtml',
            'onJ2CommerceViewProductTagHtml'     => 'onViewProductTagHtml',
            'onJ2CommerceViewCategoryListHtml'   => 'onViewCategoryListHtml',
            'onJ2CommerceAfterAddCSS'            => 'onAfterAddCSS',
            'onJ2CommerceAfterAddJS'             => 'onAfterAddJS',
        ];
    }

    /**
     * Add template folder names to the list
     *
     * @param   Event  $event  The event object
     *
     * @return  void
     *
     * @since   5.0.0
     */
    public function onTemplateFolderList($event): void
    {
        if (!($event instanceof EventInterface) && !($event instanceof Event)) {
            return;
        }

        $folders = $event->getArgument('folders', []);
        if (!\is_array($folders)) {
            $folders = [];
        }

        $folders[] = [
            'name'     => 'bootstrap5',
            'contexts' => ['products', 'product', 'producttags', 'categories'],
        ];
        $folders[] = [
            'name'     => 'tag_bootstrap5',
            'contexts' => ['producttags'],
        ];
        $folders[] = [
            'name'     => 'categories_bootstrap5',
            'contexts' => ['categories'],
        ];

        $event->setArgument('folders', $folders);
    }

    public function onAfterAddCSS(Event $event): void
    {
        if (!Factory::getApplication()->isClient('site')) {
            return;
        }

        $wa = Factory::getApplication()->getDocument()->getWebAssetManager();
        $wa->registerAndUseStyle(
            'com_j2commerce.bs5.css',
            'media/com_j2commerce/css/site/j2commerce_bs5.css'
        );
    }

    public function onAfterAddJS(Event $event): void
    {
        // Register BS5-specific JS here when needed, e.g.:
        // $wa = Factory::getApplication()->getDocument()->getWebAssetManager();
        // $wa->registerAndUseScript('com_j2commerce.bs5.js', 'media/com_j2commerce/js/site/j2commerce_bs5.js', [], ['defer' => true]);
    }

    /**
     * Render product list view with Bootstrap 5 template
     *
     * @param   Event  $event  The event object
     *
     * @return  void
     *
     * @since   5.0.0
     */
    public function onViewProductListHtml($event)
    {
        if ($event instanceof EventInterface || $event instanceof Event) {
            $args  = $event->getArguments();
            $view  = $args[1] ?? null;
            $model = $args[2] ?? null;
        } else {
            return;
        }

        // Check if this plugin should handle the template rendering
        if (!$this->shouldHandleTemplate($view, 'bootstrap5')) {
            return;
        }

        try {
            $view   = $this->setTemplatePath($view);
            $result = $view->loadTemplate();

            if ($result instanceof \Exception) {
                Factory::getApplication()->enqueueMessage($result->getMessage(), 'error');
                return;
            }

            // Use setArgument with 'html' key to properly return the rendered HTML
            // The 'html' argument is a mutable argument in J2Commerce's PluginEvent class
            $event->setArgument('html', $result);
        } catch (\Exception $e) {
            Factory::getApplication()->enqueueMessage($e->getMessage(), 'error');
        }
    }

    /**
     * Render tagged product list view with Bootstrap 5 template
     *
     * @param   Event  $event  The event object
     *
     * @return  void
     *
     * @since   5.0.0
     */
    public function onViewProductListTagHtml($event)
    {
        if ($event instanceof EventInterface || $event instanceof Event) {
            $args  = $event->getArguments();
            $view  = $args[1] ?? null;
            $model = $args[2] ?? null;
        } else {
            return;
        }

        // Check if this plugin should handle the template rendering
        // Accept both 'bootstrap5' and 'tag_bootstrap5' as valid subtemplates
        if (!$this->shouldHandleTemplate($view, 'bootstrap5', 'tag_bootstrap5')) {
            return;
        }

        // Load site CSS and JavaScript assets
        $this->loadSiteAssets(true);

        try {
            $view   = $this->setTemplatePath($view, 'tag_bootstrap5');
            $result = $view->loadTemplate();

            if ($result instanceof \Exception) {
                Factory::getApplication()->enqueueMessage($result->getMessage(), 'error');
                return;
            }

            // Use setArgument with 'html' key to properly return the rendered HTML
            // The 'html' argument is a mutable argument in J2Commerce's PluginEvent class
            $event->setArgument('html', $result);
        } catch (\Exception $e) {
            Factory::getApplication()->enqueueMessage($e->getMessage(), 'error');
        }
    }

    public function onViewCategoryListHtml($event)
    {
        if ($event instanceof EventInterface || $event instanceof Event) {
            $args  = $event->getArguments();
            $view  = $args[1] ?? null;
            $model = $args[2] ?? null;
        } else {
            return;
        }

        if (!$this->shouldHandleTemplate($view, 'bootstrap5', 'categories_bootstrap5')) {
            return;
        }

        $this->loadSiteAssets();

        try {
            $view   = $this->setTemplatePath($view, 'categories_bootstrap5');
            $result = $view->loadTemplate();

            if ($result instanceof \Exception) {
                Factory::getApplication()->enqueueMessage($result->getMessage(), 'error');
                return;
            }

            $event->setArgument('html', $result);
        } catch (\Exception $e) {
            Factory::getApplication()->enqueueMessage($e->getMessage(), 'error');
        }
    }

    /**
     * Render single product view with Bootstrap 5 template
     *
     * @param   Event  $event  The event object
     *
     * @return  void
     *
     * @since   5.0.0
     */
    public function onViewProductHtml($event)
    {
        if ($event instanceof EventInterface || $event instanceof Event) {
            $args  = $event->getArguments();
            $view  = $args[1] ?? null;
            $model = $args[2] ?? null;
        } else {
            return;
        }

        // Check if this plugin should handle the template rendering
        if (!$this->shouldHandleTemplate($view, 'bootstrap5')) {
            return;
        }

        // Load site CSS and JavaScript assets
        $this->loadSiteAssets();

        $view->setLayout('view');

        try {
            $view   = $this->setTemplatePath($view);
            $result = $view->loadTemplate();

            if ($result instanceof \Exception) {
                Factory::getApplication()->enqueueMessage($result->getMessage(), 'error');
                return;
            }

            // Use setArgument with 'html' key to properly return the rendered HTML
            // The 'html' argument is a mutable argument in J2Commerce's PluginEvent class
            $event->setArgument('html', $result);
        } catch (\Exception $e) {
            Factory::getApplication()->enqueueMessage($e->getMessage(), 'error');
        }
    }

    /**
     * Render tagged single product view with Bootstrap 5 template
     *
     * @param   Event  $event  The event object
     *
     * @return  void
     *
     * @since   5.0.0
     */
    public function onViewProductTagHtml($event)
    {
        if ($event instanceof EventInterface || $event instanceof Event) {
            $args  = $event->getArguments();
            $view  = $args[1] ?? null;
            $model = $args[2] ?? null;
        } else {
            return;
        }

        // Check if this plugin should handle the template rendering
        // Accept both 'bootstrap5' and 'tag_bootstrap5' as valid subtemplates
        if (!$this->shouldHandleTemplate($view, 'bootstrap5', 'tag_bootstrap5')) {
            return;
        }

        // Load site CSS and JavaScript assets
        $this->loadSiteAssets();

        $view->setLayout('view');

        try {
            $view   = $this->setTemplatePath($view, 'tag_bootstrap5');
            $result = $view->loadTemplate();

            if ($result instanceof \Exception) {
                Factory::getApplication()->enqueueMessage($result->getMessage(), 'error');
                return;
            }

            // Use setArgument with 'html' key to properly return the rendered HTML
            // The 'html' argument is a mutable argument in J2Commerce's PluginEvent class
            $event->setArgument('html', $result);
        } catch (\Exception $e) {
            Factory::getApplication()->enqueueMessage($e->getMessage(), 'error');
        }
    }

    /**
     * Load site CSS and JavaScript assets
     *
     * @param   bool  $withFilters  Whether to load filter-specific assets
     *
     * @return  void
     *
     * @since   6.0.0
     */
    protected function loadSiteAssets(bool $withFilters = false): void
    {
        if ($this->assetsLoaded) {
            return;
        }

        J2CommerceHelper::strapper()->addJS();
        J2CommerceHelper::strapper()->addCSS();

        $this->assetsLoaded = true;
    }

    /**
     * Set template path on the view object
     *
     * @param   object  $view         The view object
     * @param   string  $templateDir  Optional template subdirectory name
     *
     * @return  object  The modified view object
     *
     * @since   6.0.0
     */
    protected function setTemplatePath($view, string $templateDir = 'bootstrap5')
    {
        $template = Factory::getApplication()->getTemplate();

        // Plugin source (lowest priority — last added is searched first due to LIFO)
        $view->addTemplatePath(JPATH_PLUGINS . '/j2commerce/app_bootstrap5/tmpl/' . $templateDir);

        // Template override paths (higher priority)
        $overridePath = JPATH_SITE . '/templates/' . $template . '/html/com_j2commerce/templates/' . $templateDir;
        if (is_dir($overridePath)) {
            $view->addTemplatePath($overridePath);
        }

        return $view;
    }

    /**
     * Check if this plugin should handle template rendering based on subtemplate selection
     *
     * @param   object  $view               The view object
     * @param   string  $primaryTemplate    Primary template name to match (e.g., 'bootstrap5')
     * @param   string  $alternateTemplate  Optional alternate template name (e.g., 'tag_bootstrap5')
     *
     * @return  boolean  True if this plugin should handle rendering, false otherwise
     *
     * @since   6.0.0
     */
    protected function shouldHandleTemplate($view, string $primaryTemplate, string $alternateTemplate = ''): bool
    {
        // Get the subtemplate from menu item params
        $subtemplate = '';

        if (isset($view->params) && $view->params instanceof Registry) {
            $subtemplate = $view->params->get('subtemplate', '');
        }

        // If no specific subtemplate is set, this plugin should handle it (default behavior)
        if (empty($subtemplate)) {
            return true;
        }

        // Check if the subtemplate matches our primary template
        if ($subtemplate === $primaryTemplate) {
            return true;
        }

        // Check if the subtemplate matches our alternate template (for tag variants)
        if (!empty($alternateTemplate) && $subtemplate === $alternateTemplate) {
            return true;
        }

        // Subtemplate is set but doesn't match - another plugin should handle it
        return false;
    }

    /**
     * Get layout with variables
     *
     * @param   string     $layout  Layout name
     * @param   \stdClass  $vars    Variables to pass to layout
     *
     * @return  string
     *
     * @since   5.0.0
     */
    protected function _getLayout($layout, $vars = null)
    {
        $layoutPath = JPATH_PLUGINS . '/' . $this->_type . '/' . $this->_name . '/tmpl';

        $fileLayout = new FileLayout($layout, $layoutPath);

        return $fileLayout->render($vars);
    }
}
