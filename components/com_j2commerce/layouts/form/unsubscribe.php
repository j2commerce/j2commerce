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

use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

/** @var array{email: string, encoded: string, token: string, framework: string} $displayData */
$isUk   = ($displayData['framework'] ?? '') === 'uikit';
$action = Route::_('index.php?option=com_j2commerce&task=carts.unsubscribe', false);
$esc    = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
?>
<div class="j2commerce-unsubscribe">
    <h1><?php echo Text::_('COM_J2COMMERCE_CART_REMINDER_UNSUBSCRIBE_CONFIRM_HEADING'); ?></h1>
    <p><?php echo Text::sprintf('COM_J2COMMERCE_CART_REMINDER_UNSUBSCRIBE_CONFIRM_TEXT', $esc($displayData['email'])); ?></p>
    <form method="post" action="<?php echo $esc($action); ?>">
        <input type="hidden" name="e" value="<?php echo $esc($displayData['encoded']); ?>">
        <input type="hidden" name="t" value="<?php echo $esc($displayData['token']); ?>">
        <button type="submit" class="<?php echo $isUk ? 'uk-button uk-button-primary' : 'btn btn-primary'; ?>">
            <?php echo Text::_('COM_J2COMMERCE_CART_REMINDER_UNSUBSCRIBE_CONFIRM_BUTTON'); ?>
        </button>
    </form>
</div>
