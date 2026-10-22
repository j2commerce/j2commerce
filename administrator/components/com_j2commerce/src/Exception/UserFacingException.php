<?php

/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace J2Commerce\Component\J2commerce\Administrator\Exception;

\defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\Log\Log;

/** Carries a message the throwing code wrote for the person at the screen; every other throwable stays server-side. */
final class UserFacingException extends \RuntimeException
{
    public static function messageFor(\Throwable $e): string
    {
        if ($e instanceof self) {
            return $e->getMessage();
        }

        Log::add($e->getMessage(), Log::ERROR, 'com_j2commerce');

        return Text::_('COM_J2COMMERCE_ERR_GENERIC');
    }
}
