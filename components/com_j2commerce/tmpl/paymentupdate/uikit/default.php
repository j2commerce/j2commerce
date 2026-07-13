<?php
/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use J2Commerce\Component\J2commerce\Administrator\Helper\J2CommerceHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;

/** @var \J2Commerce\Component\J2commerce\Site\View\Paymentupdate\HtmlView $this */

$wa = $this->getDocument()->getWebAssetManager();
$wa->registerAndUseScript(
    'com_j2commerce.paymentupdate',
    'media/com_j2commerce/js/site/paymentupdate.js',
    [],
    ['defer' => true]
);
$wa->registerAndUseStyle(
    'com_j2commerce.paymentupdate.css',
    'media/com_j2commerce/css/site/paymentupdate.css'
);

Text::script('COM_J2COMMERCE_PAYMENTUPDATE_ERR_NETWORK');
Text::script('COM_J2COMMERCE_ERR_GENERIC');

$storeLogo = J2CommerceHelper::config()->get('store_logo');
if (\is_string($storeLogo)) {
    $storeLogo = json_decode($storeLogo);
}
$logoFile = $storeLogo->imagefile ?? '';
$logoAlt  = $storeLogo->alt_text ?? '';
?>
<div class="j2commerce j2commerce-paymentupdate">
    <div class="uk-container uk-padding" style="max-width: 560px; margin: 0 auto;">
        <?php if ($logoFile) : ?>
            <div class="uk-text-center uk-margin-medium-bottom">
                <img src="<?php echo Uri::root() . htmlspecialchars($logoFile, ENT_QUOTES, 'UTF-8'); ?>"
                     alt="<?php echo htmlspecialchars($logoAlt, ENT_QUOTES, 'UTF-8'); ?>"
                     style="max-height: 60px;">
            </div>
        <?php endif; ?>

        <?php if ($this->context === null) : ?>
            <div class="uk-alert uk-alert-danger uk-text-center" uk-alert>
                <?php echo Text::_('COM_J2COMMERCE_PAYMENTUPDATE_ERR_UNAUTHORIZED'); ?>
            </div>
        <?php else : ?>
            <?php $summary = (array) ($this->context['summary'] ?? []); ?>
            <div class="uk-card uk-card-default uk-card-body">
                <h1 class="uk-h4 uk-margin-small-bottom"><?php echo Text::_('COM_J2COMMERCE_PAYMENTUPDATE_HEADING'); ?></h1>

                <?php if (!empty($summary['title'])) : ?>
                    <p class="uk-margin-remove-bottom uk-text-bold"><?php echo htmlspecialchars((string) $summary['title'], ENT_QUOTES, 'UTF-8'); ?></p>
                <?php endif; ?>
                <?php if (!empty($summary['currentCardLabel'])) : ?>
                    <p class="uk-text-meta uk-margin-remove-bottom">
                        <?php echo Text::sprintf('COM_J2COMMERCE_PAYMENTUPDATE_CURRENT_METHOD', htmlspecialchars((string) $summary['currentCardLabel'], ENT_QUOTES, 'UTF-8')); ?>
                    </p>
                <?php endif; ?>
                <?php if (!empty($summary['nextChargeOn'])) : ?>
                    <p class="uk-text-meta uk-margin-medium-bottom">
                        <?php echo Text::sprintf('COM_J2COMMERCE_PAYMENTUPDATE_NEXT_CHARGE', htmlspecialchars((string) $summary['nextChargeOn'], ENT_QUOTES, 'UTF-8')); ?>
                    </p>
                <?php endif; ?>

                <div class="j2c-paymentupdate-error uk-alert uk-alert-danger uk-hidden" uk-alert></div>
                <div class="j2c-paymentupdate-success uk-alert uk-alert-success uk-hidden" uk-alert></div>

                <?php // Wrapper is a DIV, not a <form> — each gateway partial renders its
                      // own <form>, and nesting forms makes the browser drop the inner tag,
                      // killing the gateway JS (getElementById fails → native GET submit). ?>
                <div id="j2c-paymentupdate-form"
                     data-submit-url="<?php echo Route::_('index.php?option=com_j2commerce&task=paymentupdate.submit', false); ?>"
                     data-csrf-token="<?php echo htmlspecialchars($this->csrfToken, ENT_QUOTES, 'UTF-8'); ?>"
                >
                    <?php if (empty($this->paymentMethods)) : ?>
                        <div class="uk-alert uk-alert-warning" uk-alert>
                            <?php echo Text::_('COM_J2COMMERCE_PAYMENTUPDATE_NO_METHODS'); ?>
                        </div>
                    <?php else : ?>
                        <div class="payment-methods-group uk-margin-bottom" role="radiogroup"
                             aria-label="<?php echo Text::_('COM_J2COMMERCE_PAYMENT_METHOD', true); ?>">
                            <?php foreach ($this->paymentMethods as $i => $method) :
                                $element    = $method['element'] ?? '';
                                $name       = $method['name'] ?? $element;
                                $isSelected = $element === ($this->context['currentMethod'] ?? '') || \count($this->paymentMethods) === 1;
                            ?>
                                <label class="payment-method-item uk-flex uk-flex-middle uk-padding-small uk-margin-small-bottom"
                                       for="paymentupdate-method-<?php echo $i; ?>" style="gap: 12px; border: 1px solid #e5e5e5; border-radius: 4px;">
                                    <input class="uk-radio uk-flex-none j2c-paymentupdate-method" type="radio"
                                           name="selected_method" value="<?php echo htmlspecialchars((string) $element, ENT_QUOTES, 'UTF-8'); ?>"
                                           id="paymentupdate-method-<?php echo $i; ?>" <?php echo $isSelected ? 'checked' : ''; ?>>
                                    <div class="uk-text-bold uk-flex-1"><?php echo htmlspecialchars((string) $name, ENT_QUOTES, 'UTF-8'); ?></div>
                                    <?php echo J2CommerceHelper::getPaymentCardIcons((string) $element); ?>
                                </label>
                                <div class="j2c-paymentupdate-cardform uk-margin-small-top uk-margin-bottom<?php echo $isSelected ? '' : ' uk-hidden'; ?>"
                                     data-method="<?php echo htmlspecialchars((string) $element, ENT_QUOTES, 'UTF-8'); ?>">
                                    <?php echo $this->cardForms[$element] ?? ''; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>
