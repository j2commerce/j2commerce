<?php
/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Session\Session;

/** @var \J2Commerce\Component\J2commerce\Site\View\Myprofile\HtmlView $this */

$groupedMethods = $this->paymentMethodsGrouped;
$csrfToken = Session::getFormToken();
?>

<div class="j2commerce-payment-methods" data-csrf-token="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
    <h2 class="uk-h4 uk-margin-bottom"><?php echo Text::_('COM_J2COMMERCE_PAYMENT_METHODS_TITLE'); ?></h2>

    <?php if (empty($groupedMethods)) : ?>
        <div class="uk-alert uk-alert-primary" uk-alert role="alert">
            <span uk-icon="icon: info" class="uk-margin-small-right" aria-hidden="true"></span>
            <?php echo Text::_('COM_J2COMMERCE_PAYMENT_METHODS_NO_SAVED'); ?>
        </div>
    <?php else : ?>
        <div class="j2commerce-payment-providers uk-margin-bottom">
            <?php foreach ($groupedMethods as $provider => $methods) :
                $providerName = \J2Commerce\Component\J2commerce\Administrator\Helper\PaymentMethodsHelper::getProviderDisplayName($provider);
                $value = strtolower($providerName);
                $value = str_replace('.', '', $value);
                $value = preg_replace('/\s+/', '_', $value);
                $providerNameClass = preg_replace('/[^a-z0-9_-]/', '', $value);

                ?>
                <div class="j2commerce-payment-provider provider-<?php echo $providerNameClass;?>">
                    <div class="j2commerce-payment-methods">
                        <?php foreach ($methods as $method) :
                            $lastFour = htmlspecialchars($method->last4, ENT_QUOTES, 'UTF-8');
                            ?>
                            <div class="j2commerce-payment-method uk-margin-bottom">
                                <div class="uk-card uk-card-default uk-card-small j2commerce-payment-card" data-provider="<?php echo htmlspecialchars($method->provider, ENT_QUOTES, 'UTF-8'); ?>" data-method-id="<?php echo (int) $method->id; ?>">
                                    <div class="uk-card-body uk-flex uk-flex-between uk-flex-middle">
                                        <div class="j2commerce-payment-method-details uk-flex uk-flex-middle" style="gap: 12px;">
                                            <?php if($method->getBrandIcon()):?>
                                                <div class="j2commerce-card-icon">
                                                    <img src="<?php echo htmlspecialchars($method->getBrandIcon(), ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars(ucfirst($method->brand), ENT_QUOTES, 'UTF-8'); ?>" style="max-height: 50px; width: auto;" loading="lazy">
                                                </div>
                                            <?php endif;?>
                                            <div>
                                                <h3 class="uk-margin-remove uk-text-small"><?php echo htmlspecialchars(ucfirst($method->brand), ENT_QUOTES, 'UTF-8'); ?> <?php if($lastFour): echo Text::sprintf('COM_J2COMMERCE_PAYMENT_METHODS_ENDING_IN',$lastFour); endif;?>
                                                <?php if ($method->isDefault) : ?>
                                                    <span class="uk-badge uk-margin-small-left">
                                                        <?php echo Text::_('COM_J2COMMERCE_PAYMENT_METHODS_DEFAULT'); ?>
                                                    </span>
                                                <?php endif; ?>
                                                </h3>
                                                <?php if ($method->getFormattedExpiry()) : ?>
                                                    <small class="uk-display-block">
                                                        <?php echo Text::sprintf('COM_J2COMMERCE_PAYMENT_METHODS_EXPIRES', $method->getFormattedExpiry()); ?>
                                                    </small>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <div>
                                        <div class="uk-inline" id="paymentDropdownContainer<?php echo (int) $method->id; ?>">
                                           <button type="button" class="uk-button uk-button-small uk-button-default" aria-label="<?php echo Text::_('COM_J2COMMERCE_ACTIONS'); ?>">
                                              <span uk-icon="icon: more-vertical" aria-hidden="true"></span>
                                           </button>
                                           <div uk-dropdown="mode: click; pos: bottom-right">
                                              <ul class="uk-nav uk-dropdown-nav">
                                              <?php if ($method->canDelete()) : ?>
                                                <li>
                                                    <a role="button" class="j2commerce-delete-card-btn uk-text-danger" href="#" data-provider="<?php echo htmlspecialchars($method->provider, ENT_QUOTES, 'UTF-8'); ?>" data-method-id="<?php echo (int) $method->id; ?>">
                                                        <span uk-icon="icon: trash" class="uk-margin-small-right" aria-hidden="true"></span>
                                                        <?php echo Text::_('JACTION_DELETE'); ?>
                                                    </a>
                                                </li>
                                              <?php endif; ?>
                                               <?php if ($method->canSetDefault() && !$method->isDefault) : ?>
                                                   <li>
                                                       <a role="button" class="j2commerce-set-default-btn" href="#" data-provider="<?php echo htmlspecialchars($method->provider, ENT_QUOTES, 'UTF-8'); ?>" data-method-id="<?php echo (int) $method->id; ?>">
                                                           <span uk-icon="icon: star" class="uk-margin-small-right" aria-hidden="true"></span>
                                                           <?php echo Text::_('COM_J2COMMERCE_PAYMENT_METHODS_SET_DEFAULT'); ?>
                                                      </a>
                                                   </li>
                                               <?php endif; ?>
                                              </ul>
                                           </div>
                                        </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
