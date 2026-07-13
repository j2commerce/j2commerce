<?php

/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace J2Commerce\Component\J2commerce\Site\Controller;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use J2Commerce\Component\J2commerce\Administrator\Helper\J2CommerceHelper;
use J2Commerce\Component\J2commerce\Administrator\Helper\UtilitiesHelper;
use J2Commerce\Component\J2commerce\Site\Helper\PaymentupdateContextHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;

/**
 * Standalone universal payment-method-update surface. Never creates or
 * touches an order — the vaulted card/profile is applied directly against
 * the driving extension's entity (e.g. a subscription).
 *
 * @since  6.5.0
 */
class PaymentupdateController extends BaseController
{
    public function display($cachable = false, $urlparams = []): static
    {
        UtilitiesHelper::sendNoCacheHeaders();

        // Force the chrome-free hosted layout (Shopify-style) for every entry
        // mode — logged-in link, emailed token link, or a manually typed URL —
        // without needing a dedicated template. Joomla's own tmpl=component
        // renders the component output with no site menu/modules/footer.
        if ($this->input->getCmd('tmpl') !== 'component') {
            $query         = $this->app->getInput()->getArray();
            $query['tmpl'] = 'component';

            $this->app->redirect(Route::_('index.php?' . http_build_query($query), false));

            return $this;
        }

        return parent::display($cachable, $urlparams);
    }

    /**
     * AJAX: ask the selected gateway to relink its own renewal profile against
     * the resolved context's entity (onJ2CommerceSetSubscriptionRenewalProfile),
     * then hand off to the extension to persist the generic change. Re-resolves
     * the context from raw request data — never trusts a client-supplied
     * context blob across requests (same auth gate as display()).
     */
    public function submit(): void
    {
        if (!Session::checkToken('post')) {
            $this->jsonResponse(['success' => false, 'error' => Text::_('JINVALID_TOKEN')]);
            return;
        }

        $context = PaymentupdateContextHelper::resolve($this->app);

        if ($context === null) {
            $this->jsonResponse(['success' => false, 'error' => Text::_('COM_J2COMMERCE_PAYMENTUPDATE_ERR_UNAUTHORIZED')]);
            return;
        }

        $selectedMethod = $this->input->getCmd('selected_method', '');
        $eligible       = (array) ($context['eligibleMethods'] ?? []);

        if ($selectedMethod === '' || !\in_array($selectedMethod, $eligible, true)) {
            $this->jsonResponse(['success' => false, 'error' => Text::_('COM_J2COMMERCE_PAYMENTUPDATE_ERR_METHOD_NOT_ELIGIBLE')]);
            return;
        }

        // Durable saved-method id (e.g. a Stripe pm_... id), posted by the
        // gateway's own card form — empty for a nonce-based new-card flow.
        // Whatever OTHER fields the form posted (nonce/opaque data, an
        // alternate saved-card key, …) travel unchanged in cardInput; each
        // gateway parses its own keys from it.
        $profileId = $this->input->post->getString('profile_id', '');
        $cardInput = $this->input->post->getArray();

        // Relink event: the gateway's own renewal-profile handler owns
        // tokenization (vault a new card, or accept a posted saved-profile id
        // as-is) and rewiring its own renewal linkage. Same contract the
        // in-portal saved-card picker already drives.
        $relinkEvent = J2CommerceHelper::plugin()->event('SetSubscriptionRenewalProfile', [
            'element'         => $selectedMethod,
            'subscription_id' => (int) $context['entityId'],
            'user_id'         => (int) $context['ownerUserId'],
            'profile_id'      => $profileId,
            'cardInput'       => $cardInput,
        ]);

        $relinkResult = null;
        $gatewayError = '';

        foreach ((array) $relinkEvent->getArgument('result', []) as $result) {
            if (!\is_array($result)) {
                continue;
            }

            if (!empty($result['success'])) {
                $relinkResult = $result;
                break;
            }

            if (!empty($result['error'])) {
                $gatewayError = (string) $result['error'];
            }
        }

        if ($relinkResult === null) {
            $this->jsonResponse([
                'success' => false,
                'error'   => $gatewayError !== '' ? $gatewayError : Text::_('COM_J2COMMERCE_ERR_GENERIC'),
            ]);
            return;
        }

        // Extension persists the generic change (payment_method column,
        // history, notification email) and consumes any guest token — core
        // never touches subscription-specific storage. Fall back to the posted
        // profile id when a gateway returns a minimal {success:true} (e.g. a
        // saved-card relink) so the selected-renewal-card metafield still
        // resolves to the right card.
        J2CommerceHelper::plugin()->event('PaymentMethodUpdated', [
            'context'     => $context,
            'newMethod'   => $selectedMethod,
            'profileInfo' => [
                'profileId' => (string) ($relinkResult['profileId'] ?? $profileId),
                'label'     => (string) ($relinkResult['label'] ?? ''),
            ],
        ]);

        $this->jsonResponse([
            'success'  => true,
            'redirect' => Route::_($context['redirectUrl'], false),
            'label'    => (string) ($relinkResult['label'] ?? ''),
        ]);
    }

    private function jsonResponse(array $json): void
    {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
            header('Cache-Control: no-store, no-cache, must-revalidate');
        }

        echo json_encode($json);
        $this->app->close();
    }
}
