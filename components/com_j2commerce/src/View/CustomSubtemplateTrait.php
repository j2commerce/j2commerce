<?php

/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace J2Commerce\Component\J2commerce\Site\View;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Layout\FileLayout;

trait CustomSubtemplateTrait
{
    protected string $sublayout = '';

    /**
     * HTML already rendered by a template plugin or custom subtemplate,
     * pending display via displayPrebuilt().
     *
     * @var    string
     *
     * @since  6.6.6
     */
    public string $renderedHtml = '';

    /**
     * Route HTML a template plugin or a custom subtemplate already rendered
     * back through parent::display(), instead of echoing it directly.
     *
     * A view that overrides display() to short-circuit with `echo $html; return;`
     * skips Joomla's own onBeforeDisplay/onAfterDisplay events and its
     * template-override lookup for that request -- both of which third-party
     * integrations (e.g. YOOtheme Pro) rely on. Calling this instead keeps that
     * intact: it hands $html to the "prebuilt" layout and lets
     * parent::display() run normally.
     *
     * The layout is reset to 'default' first because a plugin may have changed
     * it (e.g. to 'view') while building $html, and loadTemplate() builds the
     * filename it looks for from the view's current layout.
     *
     * Every view that calls this needs its own tmpl/<view>/default_prebuilt.php
     * containing nothing but `echo $this->renderedHtml;`.
     *
     * @param   string  $html  The already-rendered HTML to output.
     *
     * @return  void
     *
     * @since   6.6.6
     */
    protected function displayPrebuilt(string $html): void
    {
        $this->renderedHtml = $html;
        $this->setLayout('default');
        parent::display('prebuilt');
    }

    protected function renderCustomSubtemplate(): ?string
    {
        // A subtemplate is a single folder name, the shape TemplatelistField lists.
        if ($this->sublayout !== '' && !preg_match('/^[A-Za-z0-9_-]+$/', $this->sublayout)) {
            return null;
        }

        $template     = Factory::getApplication()->getTemplate();
        $overridePath = JPATH_SITE . '/templates/' . $template . '/html/com_j2commerce/templates/' . $this->sublayout;

        if (!is_dir($overridePath)) {
            $overridePath = JPATH_SITE . '/templates/' . $template . '/html/com_j2commerce/' . $this->sublayout;
        }

        if (!is_dir($overridePath)) {
            return null;
        }

        $this->addTemplatePath($overridePath);
        $this->addTemplatePath(JPATH_SITE . '/templates/' . $template . '/html/com_j2commerce');

        try {
            $result = $this->loadTemplate();

            if ($result instanceof \Exception) {
                return $this->renderMissingTemplateFallback($this->getLayout() . '.php');
            }

            return $result;
        } catch (\Throwable $e) {
            return $this->renderMissingTemplateFallback($this->getLayout() . '.php');
        }
    }

    protected function renderMissingTemplateFallback(string $filename): string
    {
        $layout = new FileLayout(
            'fallback.missing_template',
            JPATH_ROOT . '/components/com_j2commerce/layouts'
        );

        return $layout->render([
            'filename'    => $filename,
            'subtemplate' => $this->sublayout,
            'viewContext' => $this->getName(),
        ]);
    }
}
