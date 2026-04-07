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

    protected function renderCustomSubtemplate(): ?string
    {
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
