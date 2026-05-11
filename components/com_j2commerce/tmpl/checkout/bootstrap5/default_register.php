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

use J2Commerce\Component\J2commerce\Administrator\Helper\CustomFieldHelper;
use J2Commerce\Component\J2commerce\Administrator\Helper\J2CommerceHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Multilanguage;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\Component\Content\Site\Helper\RouteHelper;
use Joomla\Database\ParameterType;

/** @var \J2Commerce\Component\J2commerce\Site\View\Checkout\HtmlView $this */

$showShipping     = $this->showShipping ?? false;
$fields           = $this->fields ?? [];
$registrationForm = $this->registrationForm ?? null;

$config = J2CommerceHelper::config();
$requiredIndicator = $config->get('checkout_required_indicator', 'asterisk');
$fieldStyle = $config->get('checkout_field_style', 'normal');
$isFloating = ($fieldStyle === 'floating');
$asterisk = ($requiredIndicator === 'asterisk') ? ' <span class="text-danger">*</span>' : '';
?>
<div class="j2commerce-register-form">

    <div class="row g-3">
        <?php foreach ($fields as $field) : ?>
            <?php echo CustomFieldHelper::renderField($field); ?>
        <?php endforeach; ?>
    </div>

    <div class="j2commerce-checkout-password-container">
        <h5 class="mt-4 mb-3"><?php echo Text::_('COM_J2COMMERCE_CHECKOUT_SET_PASSWORD'); ?></h5>
        <div class="row g-3">
            <?php if ($isFloating) : ?>
                <div class="col-md-6">
                    <div class="form-floating">
                        <input type="password" name="password" id="password" class="form-control" required autocomplete="new-password" placeholder="<?php echo Text::_('COM_J2COMMERCE_CHECKOUT_ENTER_PASSWORD'); ?>" />
                        <label for="password"><?php echo Text::_('COM_J2COMMERCE_CHECKOUT_ENTER_PASSWORD'); ?><?php echo $asterisk; ?></label>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-floating">
                        <input type="password" name="confirm" id="confirm" class="form-control" required autocomplete="new-password" placeholder="<?php echo Text::_('COM_J2COMMERCE_CHECKOUT_CONFIRM_PASSWORD'); ?>" />
                        <label for="confirm"><?php echo Text::_('COM_J2COMMERCE_CHECKOUT_CONFIRM_PASSWORD'); ?><?php echo $asterisk; ?></label>
                    </div>
                </div>
            <?php else : ?>
                <div class="col-md-6">
                    <div class="form-normal">
                        <label for="password" class="form-label"><?php echo Text::_('COM_J2COMMERCE_CHECKOUT_ENTER_PASSWORD'); ?><?php echo $asterisk; ?></label>
                        <input type="password" name="password" id="password" class="form-control" required autocomplete="new-password" />
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-normal">
                        <label for="confirm" class="form-label"><?php echo Text::_('COM_J2COMMERCE_CHECKOUT_CONFIRM_PASSWORD'); ?><?php echo $asterisk; ?></label>
                        <input type="password" name="confirm" id="confirm" class="form-control" required autocomplete="new-password" />
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($showShipping) : ?>
        <div class="mt-3 mb-3">
            <div class="form-check">
                <input class="form-check-input" type="checkbox" name="shipping_address" value="1" id="shipping-same-as-billing" checked>
                <label class="form-check-label" for="shipping-same-as-billing">
                    <?php echo Text::_('COM_J2COMMERCE_CHECKOUT_SHIPPING_SAME_AS_BILLING'); ?>
                </label>
            </div>
        </div>
    <?php endif; ?>

    <?php echo J2CommerceHelper::plugin()->eventWithHtml('CheckoutRegister', [$this]); ?>

    <?php if ($registrationForm !== null) : ?>
        <?php foreach ($registrationForm->getFieldsets() as $fieldsetName => $fieldset) : ?>
            <?php if ($fieldsetName === 'privacyconsent') : ?>
                <?php
                $privacyField = $registrationForm->getField('privacy', 'privacyconsent');
                $privacyLink  = '';
                $privacyNote  = '';

                if ($privacyField) {
                    $noteAttr    = (string) $privacyField->getAttribute('note', '');
                    $privacyNote = $noteAttr !== ''
                        ? Text::_($noteAttr)
                        : Text::_('PLG_SYSTEM_PRIVACYCONSENT_NOTE_FIELD_DEFAULT');

                    $articleId  = (int) $privacyField->getAttribute('article', 0);
                    $menuItemId = (int) $privacyField->getAttribute('menu_item', 0);

                    if ($articleId > 0) {
                        $db    = Factory::getContainer()->get(\Joomla\Database\DatabaseInterface::class);
                        $query = $db->createQuery()
                            ->select($db->quoteName(['id', 'alias', 'catid', 'language']))
                            ->from($db->quoteName('#__content'))
                            ->where($db->quoteName('id') . ' = :id')
                            ->bind(':id', $articleId, ParameterType::INTEGER);
                        $db->setQuery($query);
                        $article = $db->loadObject();

                        if ($article) {
                            $slug        = $article->alias ? ($article->id . ':' . $article->alias) : $article->id;
                            $privacyLink = Route::_(RouteHelper::getArticleRoute($slug, $article->catid, $article->language));
                        }
                    } elseif ($menuItemId > 0) {
                        $url = 'index.php?Itemid=' . $menuItemId;

                        if (Multilanguage::isEnabled()) {
                            $db    = Factory::getContainer()->get(\Joomla\Database\DatabaseInterface::class);
                            $query = $db->createQuery()
                                ->select($db->quoteName(['id', 'language']))
                                ->from($db->quoteName('#__menu'))
                                ->where($db->quoteName('id') . ' = :id')
                                ->bind(':id', $menuItemId, ParameterType::INTEGER);
                            $db->setQuery($query);
                            $menuItem = $db->loadObject();

                            if ($menuItem) {
                                $url .= '&lang=' . $menuItem->language;
                            }
                        }

                        $privacyLink = Route::_($url);
                    }
                }
                ?>
                <div class="alert alert-info mt-3" role="alert">
                    <div class="form-check mb-2">
                        <input type="checkbox"
                               class="form-check-input"
                               id="jform_privacyconsent_privacy"
                               name="jform[privacyconsent][privacy]"
                               value="1"
                               required>
                        <label class="form-check-label" for="jform_privacyconsent_privacy">
                            <?php echo htmlspecialchars($privacyNote, ENT_QUOTES, 'UTF-8'); ?>
                        </label>
                    </div>
                    <?php if ($privacyLink) : ?>
                        <a href="<?php echo htmlspecialchars($privacyLink, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener" class="small">
                            <?php echo Text::_('PLG_SYSTEM_PRIVACYCONSENT_SUBJECT'); ?>
                        </a>
                    <?php endif; ?>
                </div>
            <?php else : ?>
                <?php foreach ($registrationForm->getFieldset($fieldsetName) as $field) : ?>
                    <div class="mt-3">
                        <?php echo $field->renderField(); ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        <?php endforeach; ?>
    <?php endif; ?>

    <div class="mt-3">
        <button type="button" id="button-register" class="btn btn-primary">
            <?php echo Text::_('COM_J2COMMERCE_CHECKOUT_CONTINUE'); ?>
        </button>
    </div>
</div>
