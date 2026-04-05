<?php

/**
 * @package     J2Commerce
 * @subpackage  mod_j2commerce_currency
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Router\Route;

/** @var \Joomla\Registry\Registry $params */
/** @var object $module */
$currencies  = $currencies ?? [];
$currentCode = $currentCode ?? '';
$redirectUrl = $redirectUrl ?? '';

// Only render if there are 2 or more currencies
if (\count($currencies) < 2) {
    return;
}

// Load Bootstrap dropdown JS
HTMLHelper::_('bootstrap.dropdown');

// Get the current currency data
$currentCurrency = $currencies[$currentCode] ?? [];
$currentSymbol   = htmlspecialchars((string) ($currentCurrency['currency_symbol'] ?? ''), ENT_QUOTES, 'UTF-8');
$currentTitle    = htmlspecialchars((string) ($currentCurrency['currency_title'] ?? ''), ENT_QUOTES, 'UTF-8');

// Module params for custom colors
$moduleId        = (int) ($module->id ?? 0);
$backgroundColor = htmlspecialchars($params->get('background_color', '#FFFFFF'), ENT_QUOTES, 'UTF-8');
$textColor       = htmlspecialchars($params->get('text_color', '#000000'), ENT_QUOTES, 'UTF-8');
$linkColor       = htmlspecialchars($params->get('link_color', '#CCCCCC'), ENT_QUOTES, 'UTF-8');
$linkHoverColor  = htmlspecialchars($params->get('link_hover_color', '#000000'), ENT_QUOTES, 'UTF-8');
$activeLinkColor = htmlspecialchars($params->get('active_link_color', '#000000'), ENT_QUOTES, 'UTF-8');

$formAction = Route::_('index.php');
$scopeId    = 'mod-j2commerce-currency-' . $moduleId;
?>

<style>
#<?php echo $scopeId; ?> .j2commerce-currency-btn {
    background-color: <?php echo $backgroundColor; ?>;
    color: <?php echo $textColor; ?>;
    border: 1px solid <?php echo $linkColor; ?>;
}
#<?php echo $scopeId; ?> .j2commerce-currency-btn:hover,
#<?php echo $scopeId; ?> .j2commerce-currency-btn:focus {
    color: <?php echo $linkHoverColor; ?>;
}
#<?php echo $scopeId; ?> .dropdown-item {
    color: <?php echo $linkColor; ?>;
}
#<?php echo $scopeId; ?> .dropdown-item:hover,
#<?php echo $scopeId; ?> .dropdown-item:focus {
    color: <?php echo $linkHoverColor; ?>;
}
#<?php echo $scopeId; ?> .dropdown-item.active,
#<?php echo $scopeId; ?> .dropdown-item.active:hover {
    color: <?php echo $activeLinkColor; ?>;
    background-color: transparent;
    font-weight: 700;
}
</style>

<div id="<?php echo $scopeId; ?>" class="j2commerce j2commerce-currency-switcher">
    <div class="dropdown">
        <button class="btn j2commerce-currency-btn dropdown-toggle" type="button"
                id="<?php echo $scopeId; ?>-btn"
                data-bs-toggle="dropdown" aria-expanded="false">
            <?php echo $currentSymbol; ?> <?php echo htmlspecialchars($currentCode, ENT_QUOTES, 'UTF-8'); ?>
        </button>
        <ul class="dropdown-menu" aria-labelledby="<?php echo $scopeId; ?>-btn">
            <?php foreach ($currencies as $code => $currency) :
                $code      = htmlspecialchars((string) $code, ENT_QUOTES, 'UTF-8');
                $symbol    = htmlspecialchars((string) ($currency['currency_symbol'] ?? ''), ENT_QUOTES, 'UTF-8');
                $title     = htmlspecialchars((string) ($currency['currency_title'] ?? ''), ENT_QUOTES, 'UTF-8');
                $isActive  = ($code === $currentCode);
            ?>
                <li>
                    <a class="dropdown-item<?php echo $isActive ? ' active' : ''; ?>"
                       href="#"
                       data-currency-code="<?php echo $code; ?>"
                       <?php if ($isActive) : ?>aria-current="true"<?php endif; ?>>
                        <?php echo $symbol; ?> - <?php echo $title; ?>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>

    <form action="<?php echo $formAction; ?>" method="post" class="d-none"
          id="<?php echo $scopeId; ?>-form">
        <input type="hidden" name="option" value="com_j2commerce">
        <input type="hidden" name="task" value="carts.setcurrency">
        <input type="hidden" name="currency_code" value="">
        <input type="hidden" name="redirect" value="<?php echo htmlspecialchars($redirectUrl, ENT_QUOTES, 'UTF-8'); ?>">
        <?php echo HTMLHelper::_('form.token'); ?>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var container = document.getElementById('<?php echo $scopeId; ?>');
    if (!container) {
        return;
    }

    var form = document.getElementById('<?php echo $scopeId; ?>-form');

    container.addEventListener('click', function (e) {
        var item = e.target.closest('[data-currency-code]');
        if (!item) {
            return;
        }

        e.preventDefault();

        var code = item.getAttribute('data-currency-code');
        if (!code) {
            return;
        }

        form.querySelector('input[name="currency_code"]').value = code;
        form.submit();
    });
});
</script>
