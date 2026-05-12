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
$asterisk = ($requiredIndicator === 'asterisk') ? ' <span class="uk-text-danger">*</span>' : '';
?>
<div class="j2commerce-register-form">

    <div class="row g-3">
        <?php foreach ($fields as $field) : ?>
            <?php echo CustomFieldHelper::renderField($field); ?>
        <?php endforeach; ?>
    </div>

    <div class="j2commerce-checkout-password-container">
        <h5 class="uk-margin-medium-top uk-margin-small-bottom"><?php echo Text::_('COM_J2COMMERCE_CHECKOUT_SET_PASSWORD'); ?></h5>
        <div class="uk-grid uk-grid-small" uk-grid>
            <div class="uk-width-1-2@m">
                <label for="password" class="uk-form-label"><?php echo Text::_('COM_J2COMMERCE_CHECKOUT_ENTER_PASSWORD'); ?><?php echo $asterisk; ?></label>
                <input type="password" name="password" id="password" class="uk-input" required autocomplete="new-password" />
            </div>
            <div class="uk-width-1-2@m">
                <label for="confirm" class="uk-form-label"><?php echo Text::_('COM_J2COMMERCE_CHECKOUT_CONFIRM_PASSWORD'); ?><?php echo $asterisk; ?></label>
                <input type="password" name="confirm" id="confirm" class="uk-input" required autocomplete="new-password" />
            </div>
        </div>
    </div>

    <?php if ($showShipping) : ?>
        <div class="uk-margin">
            <label class="uk-flex uk-flex-middle">
                <input class="uk-checkbox uk-margin-small-right" type="checkbox" name="shipping_address" value="1" id="shipping-same-as-billing" checked>
                <span for="shipping-same-as-billing">
                    <?php echo Text::_('COM_J2COMMERCE_CHECKOUT_SHIPPING_SAME_AS_BILLING'); ?>
                </span>
            </label>
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
                <div class="uk-alert uk-alert-primary uk-margin-top" uk-alert role="alert">
                    <div class="uk-margin-small-bottom">
                        <label class="uk-flex uk-flex-middle">
                            <input type="checkbox"
                                   class="uk-checkbox uk-margin-small-right"
                                   id="jform_privacyconsent_privacy"
                                   name="jform[privacyconsent][privacy]"
                                   value="1"
                                   required>
                            <span for="jform_privacyconsent_privacy">
                                <?php echo htmlspecialchars($privacyNote, ENT_QUOTES, 'UTF-8'); ?>
                            </span>
                        </label>
                    </div>
                    <?php if ($privacyLink) : ?>
                        <a href="<?php echo htmlspecialchars($privacyLink, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener" class="uk-text-small">
                            <?php echo Text::_('PLG_SYSTEM_PRIVACYCONSENT_SUBJECT'); ?>
                        </a>
                    <?php endif; ?>
                </div>
            <?php else : ?>
                <?php foreach ($registrationForm->getFieldset($fieldsetName) as $field) : ?>
                    <div class="uk-margin-top">
                        <?php echo $field->renderField(); ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        <?php endforeach; ?>
    <?php endif; ?>

    <div class="uk-margin-top">
        <button type="button" id="button-register" class="uk-button uk-button-primary">
            <?php echo Text::_('COM_J2COMMERCE_CHECKOUT_CONTINUE'); ?>
        </button>
    </div>
</div>
