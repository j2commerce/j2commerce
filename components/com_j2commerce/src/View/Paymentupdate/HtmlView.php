<?php

/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace J2Commerce\Component\J2commerce\Site\View\Paymentupdate;

\defined('_JEXEC') or die;

use J2Commerce\Component\J2commerce\Administrator\Helper\J2CommerceHelper;
use J2Commerce\Component\J2commerce\Administrator\Helper\UtilitiesHelper;
use J2Commerce\Component\J2commerce\Site\Helper\PaymentupdateContextHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Session\Session;
use Joomla\Event\Event;

/**
 * Universal payment-method-update surface. NEVER creates or loads an order —
 * the resolved context (subscription, or any future entity type) is the only
 * thing rendered against.
 *
 * @since  6.5.0
 */
class HtmlView extends BaseHtmlView
{
    public ?object $params       = null;
    public ?array $context       = null;
    public array $paymentMethods = [];
    public array $cardForms      = [];
    public string $csrfToken     = '';

    public function display($tpl = null): void
    {
        $app = Factory::getApplication();

        UtilitiesHelper::sendNoCacheHeaders();

        $this->params = $app->getParams();

        $this->context = PaymentupdateContextHelper::resolve($app);

        $this->registerFrameworkTemplatePaths($app);
        $this->_prepareDocument();

        if ($this->context === null) {
            parent::display($tpl);
            return;
        }

        $this->csrfToken = Session::getFormToken();

        // Gateway JS (Accept.js loader, card-form mounting) only registers its
        // asset in each plugin's onJ2CommerceCheckoutStart listener — fire it
        // here exactly like the real checkout view does, or card forms render
        // as dead markup with no JS behind them.
        $app->getDispatcher()->dispatch('onJ2CommerceCheckoutStart', new Event('onJ2CommerceCheckoutStart', []));

        $this->paymentMethods = $this->loadEligiblePaymentMethods();
        $this->cardForms      = $this->loadCardForms($this->paymentMethods);

        parent::display($tpl);
    }

    /**
     * Reuses the SAME GetPaymentPlugins listing checkout uses, filtered to the
     * context's eligibleMethods. Also fires the dedicated
     * onJ2CommerceGetPaymentUpdateMethods event so a gateway can further
     * restrict/annotate its own entry without core needing to know why.
     *
     * @return array<int, array<string, mixed>>
     */
    private function loadEligiblePaymentMethods(): array
    {
        $eligible = (array) ($this->context['eligibleMethods'] ?? []);

        if ($eligible === []) {
            return [];
        }

        $all = [];
        foreach (J2CommerceHelper::plugin()->eventWithArray('GetPaymentPlugins', []) as $result) {
            if (\is_array($result) && isset($result['element'])) {
                $all[] = $result;
            } elseif (\is_array($result)) {
                $all = array_merge($all, $result);
            }
        }

        $filtered = array_values(array_filter(
            $all,
            static fn (array $m): bool => \in_array((string) ($m['element'] ?? ''), $eligible, true)
        ));

        $methodsEvent = J2CommerceHelper::plugin()->event('GetPaymentUpdateMethods', [
            'context' => $this->context,
            'methods' => $filtered,
        ]);
        $refined = $methodsEvent->getArgument('methods');

        return \is_array($refined) ? $refined : $filtered;
    }

    /**
     * Renders each eligible gateway's own card-form markup via the SAME
     * PrePayment event checkout uses — mode/context/token flags let a
     * gateway's shared checkout JS branch its submit target without
     * duplicating the form/JS for this surface.
     *
     * @param   array<int, array<string, mixed>>  $methods
     * @return  array<string, string>  element => rendered HTML
     */
    private function loadCardForms(array $methods): array
    {
        $forms = [];
        $app   = Factory::getApplication();

        foreach ($methods as $method) {
            $element = (string) ($method['element'] ?? '');

            if ($element === '') {
                continue;
            }

            $paymentValues = [
                'order_id'            => 0,
                'orderpayment_id'     => 0,
                'orderpayment_amount' => 0.0,
                'order'               => null,
                'mode'                => 'paymentupdate',
                'context_key'         => $app->getInput()->getString('context', ''),
                'token'               => $app->getInput()->getString('token', ''),
            ];

            $html = '';
            foreach (J2CommerceHelper::plugin()->eventWithArray('PrePayment', [$element, $paymentValues]) as $result) {
                if (\is_string($result)) {
                    $html .= $result;
                }
            }

            $forms[$element] = $html;
        }

        return $forms;
    }

    /** Resolve and register the per-menu-item framework folder (bootstrap5/uikit). */
    private function registerFrameworkTemplatePaths(\Joomla\CMS\Application\CMSApplicationInterface $app): void
    {
        $framework = (string) $this->params->get('framework', 'bootstrap5');
        $framework = preg_replace('/[^a-zA-Z0-9_-]/', '', $framework) ?? '';

        $viewName = $this->getName();
        $template = $app->getTemplate();

        $compRoot = JPATH_COMPONENT . '/tmpl/' . $viewName;
        $tplRoot  = JPATH_THEMES . '/' . $template . '/html/com_j2commerce/' . $viewName;

        $candidate = ($framework !== '' && is_dir($compRoot . '/' . $framework)) ? $framework : 'bootstrap5';

        if (is_dir($compRoot . '/' . $candidate)) {
            $this->addTemplatePath($compRoot . '/' . $candidate);
        }

        if (is_dir($tplRoot)) {
            $this->addTemplatePath($tplRoot);
        }

        if (is_dir($tplRoot . '/' . $candidate)) {
            $this->addTemplatePath($tplRoot . '/' . $candidate);
        }
    }

    private function _prepareDocument(): void
    {
        $this->getDocument()->setTitle(Text::_('COM_J2COMMERCE_PAYMENTUPDATE_PAGE_TITLE'));
        $this->getDocument()->setMetaData('robots', 'noindex, nofollow');
    }
}
