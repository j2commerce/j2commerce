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
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;

/** @var \J2Commerce\Component\J2commerce\Site\View\Myprofile\HtmlView $this */

HTMLHelper::_('bootstrap.dropdown', '.dropdown-toggle', []);

$groupedMethods = $this->paymentMethodsGrouped;
$csrfToken = Session::getFormToken();
?>

<div class="j2commerce-payment-methods" data-csrf-token="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
    <h2 class="mb-4 fs-4"><?php echo Text::_('COM_J2COMMERCE_PAYMENT_METHODS_TITLE'); ?></h2>

    <?php // One entry point for new cards regardless of how many gateways are enabled —
          // the Payment Update page handles gateway choice and tokenization itself.
          // paymentMethodsAddCardHtml (onJ2CommercePaymentMethodsAddCard) stays a
          // capability signal for tab visibility; provider widgets are not rendered here. ?>
    <div class="j2commerce-payment-methods-add mb-4">
        <a class="btn btn-primary" href="<?php echo Route::_('index.php?option=com_j2commerce&view=paymentupdate'); ?>">
            <span class="fa-solid fa-plus me-1" aria-hidden="true"></span>
            <?php echo Text::_('COM_J2COMMERCE_PAYMENT_METHODS_ADD_NEW'); ?>
        </a>
    </div>

    <?php if (empty($groupedMethods)) : ?>
        <div class="alert alert-info" role="alert">
            <span class="fa-solid fa-info-circle me-2" aria-hidden="true"></span>
            <?php echo Text::_('COM_J2COMMERCE_PAYMENT_METHODS_NO_SAVED'); ?>
        </div>
    <?php else : ?>
        <div class="j2commerce-payment-providers mb-4">
            <?php foreach ($groupedMethods as $provider => $methods) :
                $providerName = \J2Commerce\Component\J2commerce\Administrator\Helper\PaymentMethodsHelper::getProviderDisplayName($provider);
                $value = strtolower($providerName);
                $value = str_replace('.', '', $value);
                $value = preg_replace('/\s+/', '_', $value);
                $providerNameClass = preg_replace('/[^a-z0-9_-]/', '', $value);

                ?>
                <div class="j2commerce-payment-provider provider-<?php echo $providerNameClass;?>">
                    <div class="j2commerce-payment-methods">
                        <?php foreach ($methods as $i => $method) :
                            $lastFour = htmlspecialchars($method->last4, ENT_QUOTES, 'UTF-8');
                            // Method ids are provider strings (pm_…, cst_…) — never int-cast them.
                            $methodId   = htmlspecialchars($method->id, ENT_QUOTES, 'UTF-8');
                            $dropdownId = 'paymentDropdown' . $providerNameClass . (int) $i;
                            ?>
                            <div class="j2commerce-payment-method mb-3">
                                <div class="border py-3 px-4 rounded-3 mb-3 j2commerce-payment-card" data-provider="<?php echo htmlspecialchars($method->provider, ENT_QUOTES, 'UTF-8'); ?>" data-method-id="<?php echo $methodId; ?>">
                                    <div class="j2commerce-payment-method-inner d-flex justify-content-between align-items-center">
                                        <div class="j2commerce-payment-method-details d-flex align-items-center">
                                            <?php if($method->getBrandIcon()):?>
                                                <div class="j2commerce-card-icon me-3">
                                                    <img src="<?php echo htmlspecialchars($method->getBrandIcon(), ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars(ucfirst($method->brand), ENT_QUOTES, 'UTF-8'); ?>" class="img-fluid me-3" style="max-height: 50px;width: auto;" loading="lazy">
                                                </div>
                                            <?php endif;?>
                                            <div>
                                                <h3 class="mb-0 fs-6"><?php echo htmlspecialchars(ucfirst($method->brand), ENT_QUOTES, 'UTF-8'); ?> <?php if($lastFour): echo Text::sprintf('COM_J2COMMERCE_PAYMENT_METHODS_ENDING_IN',$lastFour); endif;?>
                                                <?php if ($method->isDefault) : ?>
                                                    <span class="badge text-bg-info ms-2">
                                                        <?php echo Text::_('COM_J2COMMERCE_PAYMENT_METHODS_DEFAULT'); ?>
                                                    </span>
                                                <?php endif; ?>
                                                <?php foreach ($method->badges as $badge) : ?>
                                                    <?php if (!empty($badge['label'])) : ?>
                                                        <span class="badge <?php echo htmlspecialchars($badge['class'] ?? 'text-bg-secondary', ENT_QUOTES, 'UTF-8'); ?> ms-2">
                                                            <?php echo htmlspecialchars($badge['label'], ENT_QUOTES, 'UTF-8'); ?>
                                                        </span>
                                                    <?php endif; ?>
                                                <?php endforeach; ?>
                                                </h3>
                                                <?php if ($method->getFormattedExpiry()) : ?>
                                                    <small class="d-block">
                                                        <?php echo Text::sprintf('COM_J2COMMERCE_PAYMENT_METHODS_EXPIRES', $method->getFormattedExpiry()); ?>
                                                    </small>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <div class="d-flex align-items-center">
                                        <span class="dropdown dropstart">
                                           <button type="button" class="btn btn-link text-reset dropdown-toggle p-0" id="<?php echo $dropdownId; ?>" data-bs-toggle="dropdown" data-bs-offset="-20,20" aria-expanded="false" aria-label="<?php echo Text::_('COM_J2COMMERCE_ACTIONS'); ?>">
                                              <span class="fa-solid fa-ellipsis-vertical" aria-hidden="true"></span>
                                           </button>
                                           <span class="dropdown-menu" aria-labelledby="<?php echo $dropdownId; ?>">
                                              <?php foreach ($method->extraActions as $action) : ?>
                                                  <?php if (empty($action['label']) || empty($action['class'])) {
                                                      continue;
                                                  } ?>
                                                  <a role="button" class="dropdown-item <?php echo htmlspecialchars($action['class'], ENT_QUOTES, 'UTF-8'); ?>" href="#"
                                                      data-provider="<?php echo htmlspecialchars($method->provider, ENT_QUOTES, 'UTF-8'); ?>"
                                                      data-method-id="<?php echo $methodId; ?>"
                                                      <?php foreach (($action['data'] ?? []) as $dataKey => $dataValue) :
                                                          $dataKey = preg_replace('/[^a-z0-9-]/', '', strtolower((string) $dataKey));
                                                          if ($dataKey === '') {
                                                              continue;
                                                          } ?>
                                                          data-<?php echo $dataKey; ?>="<?php echo htmlspecialchars((string) $dataValue, ENT_QUOTES, 'UTF-8'); ?>"
                                                      <?php endforeach; ?>>
                                                      <?php if (!empty($action['icon'])) : ?>
                                                          <span class="<?php echo htmlspecialchars($action['icon'], ENT_QUOTES, 'UTF-8'); ?> fa-fw" aria-hidden="true"></span>
                                                      <?php endif; ?>
                                                      <span class="ms-1"><?php echo htmlspecialchars($action['label'], ENT_QUOTES, 'UTF-8'); ?></span>
                                                  </a>
                                              <?php endforeach; ?>
                                              <?php if ($method->canDelete()) : ?>
                                                <a role="button" class="dropdown-item j2commerce-delete-card-btn" href="#" data-provider="<?php echo htmlspecialchars($method->provider, ENT_QUOTES, 'UTF-8'); ?>" data-method-id="<?php echo $methodId; ?>">
                                                    <span class="fa-solid fa-trash text-danger fa-fw" aria-hidden="true"></span>
                                                    <span class="ms-1"><?php echo Text::_('JACTION_DELETE'); ?></span>
                                                </a>
                                              <?php endif; ?>
                                               <?php if ($method->canSetDefault() && !$method->isDefault) : ?>
                                                   <a role="button" class="dropdown-item j2commerce-set-default-btn" href="#" data-provider="<?php echo htmlspecialchars($method->provider, ENT_QUOTES, 'UTF-8'); ?>" data-method-id="<?php echo $methodId; ?>">
                                                       <span class="fa-solid fa-star fa-fw" aria-hidden="true"></span>
                                                       <span class="ms-1"><?php echo Text::_('COM_J2COMMERCE_PAYMENT_METHODS_SET_DEFAULT'); ?></span>
                                                  </a>
                                               <?php endif; ?>
                                           </span>
                                        </span>
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
