<?php
/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

\defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;

// Shim pre-builds $inputHtml and passes it via $displayData
$inputHtml         = (string) ($displayData['inputHtml'] ?? '');
$decrementDisabled = (string) ($displayData['decrementDisabled'] ?? '');
$incrementDisabled = (string) ($displayData['incrementDisabled'] ?? '');
$iconMinus         = (string) ($displayData['iconMinus'] ?? '');
$iconPlus          = (string) ($displayData['iconPlus'] ?? '');
?>
<div class="count-input flex-shrink-0">
    <button type="button" class="btn btn-icon btn-lg" data-decrement aria-label="<?php echo htmlspecialchars(Text::_('COM_J2COMMERCE_DECREASE_QUANTITY'), ENT_QUOTES, 'UTF-8'); ?>"<?php echo $decrementDisabled; ?>>
        <span class="<?php echo htmlspecialchars($iconMinus, ENT_QUOTES, 'UTF-8'); ?>" aria-hidden="true"></span>
    </button>
    <?php echo $inputHtml; ?>
    <button type="button" class="btn btn-icon btn-lg" data-increment aria-label="<?php echo htmlspecialchars(Text::_('COM_J2COMMERCE_INCREASE_QUANTITY'), ENT_QUOTES, 'UTF-8'); ?>"<?php echo $incrementDisabled; ?>>
        <span class="<?php echo htmlspecialchars($iconPlus, ENT_QUOTES, 'UTF-8'); ?>" aria-hidden="true"></span>
    </button>
</div>
