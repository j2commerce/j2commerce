<?php

/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace J2Commerce\Component\J2commerce\Site\Dispatcher;

\defined('_JEXEC') or die;

use J2Commerce\Component\J2commerce\Administrator\Helper\J2CommerceHelper;
use Joomla\CMS\Dispatcher\ComponentDispatcher;
use Joomla\CMS\Factory;

/**
 * ComponentDispatcher class for com_j2commerce
 *
 * Handles component dispatch and loads required frontend assets.
 *
 * @since  6.0.0
 */
class Dispatcher extends ComponentDispatcher
{
    /**
     * Dispatch the controller.
     *
     * Loads J2Commerce frontend scripts and CSS before dispatching,
     * but skips asset loading for AJAX requests since they don't need
     * HTML document assets.
     *
     * @return void
     * @since  6.0.0
     */
    public function dispatch(): void
    {
        $app   = Factory::getApplication();
        $input = $app->getInput();

        // Skip asset loading for AJAX requests (ajax=1 parameter or JSON format)
        $isAjax = $input->getInt('ajax', 0) === 1
            || $input->getCmd('format', 'html') === 'json'
            || $input->getCmd('format', 'html') === 'raw';

        if (!$isAjax) {
            // Load J2Commerce frontend assets (JS and CSS) for non-AJAX requests
            $strapper = J2CommerceHelper::strapper();
            $strapper->addJS();
            $strapper->addCSS();
        }

        // Allow plugins to claim frontend views they render entirely via
        // onAfterDispatch, bypassing the MVC chain (no View class needed).
        $view = $input->getCmd('view', '');

        if ($view !== '' && $app->isClient('site')) {
            $claimed = J2CommerceHelper::plugin()->eventWithArray(
                'BeforeDispatchView',
                [$view]
            );

            if (\in_array(true, $claimed, true)) {
                return;
            }
        }

        parent::dispatch();
    }
}
