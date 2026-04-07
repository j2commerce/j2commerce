<?php

/**
 * @package     J2Commerce
 * @subpackage  Plugin.J2Commerce.AppUikit
 *
 * @copyright   Copyright (C) 2025-2026 J2Commerce, LLC. All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

namespace J2Commerce\Plugin\J2Commerce\AppUikit\Extension;

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
 * UIkit Layout Plugin for J2Commerce
 *
 * Provides UIkit templates for product list and detail views
 *
 * @since  6.0.0
 */
final class AppUikit extends CMSPlugin implements SubscriberInterface
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
    protected $_element = 'app_uikit';

    /**
     * Returns an array of events this subscriber will listen to.
     *
     * @return  array
     *
     * @since   6.0.0
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
        ];
    }

    /**
     * Add template folder names to the list
     *
     * @param   Event  $event  The event object
     *
     * @return  void
     *
     * @since   6.0.0
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
            'name'     => 'uikit',
            'contexts' => ['products', 'product', 'producttags', 'categories'],
        ];
        $folders[] = [
            'name'     => 'tag_uikit',
            'contexts' => ['producttags'],
        ];
        $folders[] = [
            'name'     => 'categories_uikit',
            'contexts' => ['categories'],
        ];

        $event->setArgument('folders', $folders);
    }

    /**
     * Render product list view with UIkit template
     *
     * @param   Event  $event  The event object
     *
     * @return  void
     *
     * @since   6.0.0
     */
    public function onViewProductListHtml($event)
    {
        if ($event instanceof EventInterface || $event instanceof Event) {
            $args      = $event->getArguments();
            $view_html = &$args[0];
            $view      = &$args[1];
            $model     = $args[2] ?? null;
        } else {
            return;
        }

        // Check if this plugin should handle the template rendering
        if (!$this->shouldHandleTemplate($view, 'uikit')) {
            return;
        }

        try {
            $view   = $this->setTemplatePath($view);
            $result = $view->loadTemplate();

            if ($result instanceof \Exception) {
                Factory::getApplication()->enqueueMessage($result->getMessage(), 'error');
                return;
            }

            $view_html = $result;
        } catch (\Exception $e) {
            Factory::getApplication()->enqueueMessage($e->getMessage(), 'error');
        }
    }

    /**
     * Render tagged product list view with UIkit template
     *
     * @param   Event  $event  The event object
     *
     * @return  void
     *
     * @since   6.0.0
     */
    public function onViewProductListTagHtml($event)
    {
        if ($event instanceof EventInterface || $event instanceof Event) {
            $args      = $event->getArguments();
            $view_html = &$args[0];
            $view      = &$args[1];
            $model     = $args[2] ?? null;
        } else {
            return;
        }

        // Check if this plugin should handle the template rendering
        // Accept both 'uikit' and 'tag_uikit' as valid subtemplates
        if (!$this->shouldHandleTemplate($view, 'uikit', 'tag_uikit')) {
            return;
        }

        try {
            $view   = $this->setTemplatePath($view, 'tag_uikit');
            $result = $view->loadTemplate();

            if ($result instanceof \Exception) {
                Factory::getApplication()->enqueueMessage($result->getMessage(), 'error');
                return;
            }

            $view_html = $result;
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

        if (!$this->shouldHandleTemplate($view, 'uikit', 'categories_uikit')) {
            return;
        }

        try {
            $view   = $this->setTemplatePath($view, 'categories_uikit');
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
     * Render single product view with UIkit template
     *
     * @param   Event  $event  The event object
     *
     * @return  void
     *
     * @since   6.0.0
     */
    public function onViewProductHtml($event)
    {
        if ($event instanceof EventInterface || $event instanceof Event) {
            $args      = $event->getArguments();
            $view_html = &$args[0];
            $view      = &$args[1];
            $model     = $args[2] ?? null;
        } else {
            return;
        }

        // Check if this plugin should handle the template rendering
        if (!$this->shouldHandleTemplate($view, 'uikit')) {
            return;
        }

        $view->setLayout('view');

        try {
            $view   = $this->setTemplatePath($view);
            $result = $view->loadTemplate();

            if ($result instanceof \Exception) {
                Factory::getApplication()->enqueueMessage($result->getMessage(), 'error');
                return;
            }

            $view_html = $result;
        } catch (\Exception $e) {
            Factory::getApplication()->enqueueMessage($e->getMessage(), 'error');
        }
    }

    /**
     * Render tagged single product view with UIkit template
     *
     * @param   Event  $event  The event object
     *
     * @return  void
     *
     * @since   6.0.0
     */
    public function onViewProductTagHtml($event)
    {
        if ($event instanceof EventInterface || $event instanceof Event) {
            $args      = $event->getArguments();
            $view_html = &$args[0];
            $view      = &$args[1];
            $model     = $args[2] ?? null;
        } else {
            return;
        }

        // Check if this plugin should handle the template rendering
        // Accept both 'uikit' and 'tag_uikit' as valid subtemplates
        if (!$this->shouldHandleTemplate($view, 'uikit', 'tag_uikit')) {
            return;
        }

        $view->setLayout('view');

        try {
            $view   = $this->setTemplatePath($view, 'tag_uikit');
            $result = $view->loadTemplate();

            if ($result instanceof \Exception) {
                Factory::getApplication()->enqueueMessage($result->getMessage(), 'error');
                return;
            }

            $view_html = $result;
        } catch (\Exception $e) {
            Factory::getApplication()->enqueueMessage($e->getMessage(), 'error');
        }
    }

    /**
     * Check if this plugin should handle template rendering based on subtemplate selection
     *
     * @param   object  $view               The view object
     * @param   string  $primaryTemplate    Primary template name to match (e.g., 'uikit')
     * @param   string  $alternateTemplate  Optional alternate template name (e.g., 'tag_uikit')
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

        // If no specific subtemplate is set, defer to Bootstrap 5 as the default layout
        if (empty($subtemplate)) {
            return false;
        }

        // Check if the subtemplate matches our primary or alternate template
        return $subtemplate === $primaryTemplate
            || (!empty($alternateTemplate) && $subtemplate === $alternateTemplate);
    }

    /**
     * Get layout with variables
     *
     * @param   string     $layout  Layout name
     * @param   \stdClass  $vars    Variables to pass to layout
     *
     * @return  string
     *
     * @since   6.0.0
     */
    protected function _getLayout($layout, $vars = null)
    {
        $layoutPath = JPATH_PLUGINS . '/' . $this->_type . '/' . $this->_name . '/tmpl';

        $fileLayout = new FileLayout($layout, $layoutPath);

        return $fileLayout->render($vars);
    }

    /**
     * Set template paths for the view
     *
     * @param   object  $view     The view object
     * @param   string  $default  Default template folder name
     *
     * @return  object  The modified view object
     *
     * @since   6.0.0
     */
    protected function setTemplatePath($view, $default = 'uikit')
    {
        $app = J2CommerceHelper::platform()->application();

        if (!\defined('DS')) {
            \define('DS', DIRECTORY_SEPARATOR);
        }

        // Look for template files in plugin folders
        $view->addTemplatePath(
            JPATH_SITE . DS . 'plugins' . DS . 'j2commerce' . DS . $this->_element . DS . 'tmpl' . DS . $default
        );

        // Look for overrides in template folder (J2Commerce template structure)
        $view->addTemplatePath(
            JPATH_SITE . DS . 'templates' . DS . $app->getTemplate() . DS . 'html' . DS . 'com_j2commerce' . DS . 'templates'
        );
        $view->addTemplatePath(
            JPATH_SITE . DS . 'templates' . DS . $app->getTemplate() . DS . 'html' . DS . 'com_j2commerce' . DS . 'templates' . DS . $default
        );

        // Look for overrides in template folder (Joomla template structure)
        $view->addTemplatePath(
            JPATH_SITE . DS . 'templates' . DS . $app->getTemplate() . DS . 'html' . DS . 'com_j2commerce' . DS . $default
        );
        $view->addTemplatePath(
            JPATH_SITE . DS . 'templates' . DS . $app->getTemplate() . DS . 'html' . DS . 'com_j2commerce'
        );

        // Look for specific J2Commerce theme files
        if (isset($view->params) && $view->params->get('subtemplate')) {
            $view->addTemplatePath(
                JPATH_SITE . DS . 'templates' . DS . $app->getTemplate() . DS . 'html' . DS . 'com_j2commerce' . DS . 'templates' . DS . $view->params->get('subtemplate')
            );
            $view->addTemplatePath(
                JPATH_SITE . DS . 'templates' . DS . $app->getTemplate() . DS . 'html' . DS . 'com_j2commerce' . DS . $view->params->get('subtemplate')
            );
        }

        return $view;
    }

    /**
     * Escape HTML special characters
     *
     * @param   string  $var  String to escape
     *
     * @return  string
     *
     * @since   6.0.0
     */
    public function escape($var)
    {
        return htmlspecialchars_decode($var, ENT_COMPAT);
    }
}
