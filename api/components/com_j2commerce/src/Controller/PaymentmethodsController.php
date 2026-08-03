<?php

/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace J2Commerce\Component\J2commerce\Api\Controller;

\defined('_JEXEC') or die;


class PaymentmethodsController extends J2CommerceApiController
{
    protected $contentType = 'paymentmethods';

    protected $default_view = 'paymentmethods';

    protected string $readAction = 'j2commerce.viewsetup';

    protected string $writeAction = 'j2commerce.editsetup';
}
