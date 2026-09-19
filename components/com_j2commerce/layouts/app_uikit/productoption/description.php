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

/**
 * Merchant-authored guidance for one product option, rendered under its label.
 *
 * The id is what callers point aria-describedby at, which is what makes the text announced with
 * the control rather than merely sitting near it (ARIA1, sufficient for 3.3.2 Labels or
 * Instructions when paired with a label). Callers never spell that attribute out: they call
 * ProductLayoutService::optionDescribedBy(), which repeats the emptiness test below, so the
 * attribute cannot outlive the element it names.
 *
 * @var array  $displayData
 * @var string $displayData['description']  raw merchant text; empty renders nothing
 * @var string $displayData['id']           DOM id the describing control references
 */

$description = trim((string) ($displayData['description'] ?? ''));
$id          = (string) ($displayData['id'] ?? '');

if ($description === '' || $id === '') {
    return;
}
?>
<div id="<?php echo htmlspecialchars($id, ENT_QUOTES, 'UTF-8'); ?>" class="j2commerce-option-description uk-text-small">
    <?php echo htmlspecialchars($description, ENT_QUOTES, 'UTF-8'); ?>
</div>
