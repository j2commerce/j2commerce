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

/**
 * A voucher was refused for a reason this component decided, carrying a message it wrote.
 *
 * VoucherModel dispatches VoucherIsValid from inside the same try that wraps its own
 * validators, so subscriber code — and anything a subscriber calls — can raise from there
 * too. \DomainException alone cannot tell the two apart: it is the class an SPL-idiomatic
 * plugin would reach for, and Joomla\Http\Exception\UnexpectedResponseException already
 * extends it. Only a type this component owns can be thrown solely by this component.
 *
 * @since  6.5.1
 */
final class VoucherRejection extends \DomainException
{
}
