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

use J2Commerce\Component\J2commerce\Administrator\Helper\J2CommerceHelper;
use Joomla\CMS\Application\CMSApplicationInterface;
use Joomla\CMS\Uri\Uri;

/**
 * Shared context resolution for the universal payment-method-update surface
 * (view=paymentupdate). Both the display view and the submit AJAX task call
 * resolve() so the exact same auth gate applies to both — the request is
 * never trusted to carry a previously-resolved context across requests.
 *
 * @since  6.5.0
 */
final class PaymentupdateContextHelper
{
    /**
     * Dispatches onJ2CommerceGetPaymentUpdateContext and returns the resolved
     * context array, or null if unauthorized/not found. NEVER leaks WHY a
     * context failed to resolve — callers show one generic error.
     *
     * @return array{entityType:string,entityId:int,ownerUserId:int,eligibleMethods:string[],currentMethod:string,redirectUrl:string,summary:array,tokenId:int}|null
     */
    public static function resolve(CMSApplicationInterface $app): ?array
    {
        $input         = $app->getInput();
        $contextKey    = $input->getString('context', '');
        $token         = $input->getString('token', '');
        $user          = $app->getIdentity();
        $requestUserId = ($user && !$user->guest) ? (int) $user->id : 0;

        if ($contextKey === '' && $token === '') {
            return null;
        }

        $event = J2CommerceHelper::plugin()->event('GetPaymentUpdateContext', [
            'contextKey'    => $contextKey,
            'token'         => $token,
            'requestUserId' => $requestUserId,
        ]);

        $context = $event->getArgument('result');

        if (!\is_array($context) || empty($context['entityType']) || (int) ($context['ownerUserId'] ?? 0) <= 0) {
            return null;
        }

        $context['redirectUrl'] = self::sanitizeRedirect((string) ($context['redirectUrl'] ?? ''));

        return $context;
    }

    /**
     * Confines an extension-supplied redirect to an on-site route — never an
     * open redirect. Falls back to myprofile when the supplied URL points
     * off-site or is empty.
     */
    private static function sanitizeRedirect(string $url): string
    {
        if ($url === '') {
            return 'index.php?option=com_j2commerce&view=myprofile';
        }

        // Absolute URLs must share this site's host; relative/local URLs are fine.
        if (preg_match('#^https?://#i', $url)) {
            $siteHost = Uri::getInstance()->getHost();
            $urlHost  = Uri::getInstance($url)->getHost();

            if ($urlHost === '' || strcasecmp($urlHost, $siteHost) !== 0) {
                return 'index.php?option=com_j2commerce&view=myprofile';
            }

            return $url;
        }

        if (str_starts_with($url, '//') || str_starts_with($url, '\\\\')) {
            return 'index.php?option=com_j2commerce&view=myprofile';
        }

        return $url;
    }
}
