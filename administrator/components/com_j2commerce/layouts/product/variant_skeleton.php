<?php

/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Joomla\CMS\Language\Text;

/** @var array $displayData */

$rows = max(1, min((int) ($displayData['rows'] ?? 1), 20));
?>
<div class="j2commerce-variant-skeleton" role="status" aria-live="polite">
    <span class="visually-hidden"><?php echo Text::_('COM_J2COMMERCE_LOADING'); ?></span>
    <?php for ($i = 0; $i < $rows; $i++) : ?>
        <div class="variant-item border mb-3 rounded-3 px-3 py-2 placeholder-glow" aria-hidden="true">
            <div class="d-flex align-items-center">
                <span class="placeholder rounded me-2" style="width:1rem;height:1rem;"></span>
                <span class="placeholder rounded me-2" style="width:1.5rem;height:1.5rem;"></span>
                <span class="placeholder col-4 rounded"></span>
                <span class="placeholder col-2 rounded ms-auto"></span>
            </div>
        </div>
    <?php endfor; ?>
</div>
