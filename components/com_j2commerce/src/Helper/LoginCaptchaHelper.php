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

use Joomla\CMS\Captcha\Captcha;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Log\Log;

final class LoginCaptchaHelper
{
    /** User state holding the guest order form values after a failed captcha. */
    public const GUEST_FORM_STATE = 'com_j2commerce.myprofile.guest_form';

    /**
     * Null when no captcha applies. An empty setting follows the Global Configuration captcha.
     *
     * A missing plugin, or a disabled provider plugin, does not throw: it renders nothing and its answer never
     * verifies, so callers still fail closed.
     *
     * @throws \RuntimeException only if the plugin's own boot throws; callers fail closed, as core CaptchaRule does.
     */
    public static function forMyprofile(): ?Captcha
    {
        $plugin = (string) ComponentHelper::getParams('com_j2commerce')->get('myprofile_login_captcha', '');

        if ($plugin === '') {
            $plugin = (string) Factory::getApplication()->get('captcha', '');
        }

        if ($plugin === '' || $plugin === '0') {
            return null;
        }

        try {
            return Captcha::getInstance($plugin, ['namespace' => 'j2commerce_myprofile']);
        } catch (\RuntimeException $e) {
            Log::add('My Profile captcha "' . $plugin . '" could not be loaded: ' . $e->getMessage(), Log::WARNING, 'com_j2commerce');

            throw $e;
        }
    }
}
