<?php

/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace J2Commerce\Component\J2commerce\Administrator\Dispatcher;

\defined('_JEXEC') or die;

use J2Commerce\Component\J2commerce\Administrator\Helper\J2CommerceHelper;
use Joomla\CMS\Dispatcher\ComponentDispatcher;

/**
 * ComponentDispatcher class for com_j2commerce
 *
 * Handles component dispatch and loads required admin assets.
 *
 * @since  6.0.0
 */
class Dispatcher extends ComponentDispatcher
{
    /**
     * The extension namespace
     *
     * @var    string
     */
    protected $namespace = 'J2Commerce\\Component\\J2commerce';

    /**
     * Dispatch the controller.
     *
     * Loads J2Commerce admin scripts and CSS before dispatching.
     *
     * @return void
     * @since  6.0.0
     */
    public function dispatch(): void
    {
        // Load J2Commerce admin assets (JS and CSS)
        $strapper = J2CommerceHelper::strapper();
        $strapper->addJS();
        $strapper->addCSS();

        // Continue with normal dispatch
        parent::dispatch();
    }
}
