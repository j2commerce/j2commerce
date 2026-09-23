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

use Joomla\CMS\Language\Text;

/** @var array $displayData */
$msg         = $displayData;
$id          = htmlspecialchars($msg['id'] ?? '', ENT_QUOTES, 'UTF-8');
$text        = $msg['text'] ?? '';
$type        = htmlspecialchars($msg['type'] ?? 'info', ENT_QUOTES, 'UTF-8');
$icon        = htmlspecialchars($msg['icon'] ?? '', ENT_QUOTES, 'UTF-8');
$dismissible = $msg['dismissible'] ?? 'session';
$link        = $msg['link'] ?? '';
$linkText    = htmlspecialchars($msg['linkText'] ?? '', ENT_QUOTES, 'UTF-8');
$position    = (int) ($msg['position'] ?? 0);
$total       = (int) ($msg['total'] ?? 0);
?>
<div class="swiper-slide" data-message-id="<?php echo $id; ?>" role="group" aria-roledescription="slide"
    <?php if ($total > 1) : ?>aria-label="<?php echo htmlspecialchars(Text::sprintf('COM_J2COMMERCE_DASHBOARD_MSG_SLIDE', $position, $total), ENT_QUOTES, 'UTF-8'); ?>"<?php endif; ?>>
    <?php // role="status" rather than "alert": these are standing maintenance notices rendered
          // with the page, not time-critical errors that should interrupt. ?>
    <div class="alert alert-<?php echo $type; ?> d-flex align-items-center my-0 position-relative border-0" role="status">
        <?php if ($icon) : ?>
            <span class="<?php echo $icon; ?> fa-lg me-3 flex-shrink-0" aria-hidden="true"></span>
        <?php endif; ?>

        <div class="flex-grow-1">
            <?php echo $text; ?>
            <?php if ($link) : ?>
                <a href="<?php echo $link; ?>" class="btn btn-sm btn-<?php echo $type; ?> ms-3 flex-shrink-0">
                    <?php echo $linkText ?: Text::_('JOPTIONS'); ?>
                    <span class="fa-solid fa-arrow-right ms-1" aria-hidden="true"></span>
                </a>
            <?php endif; ?>
        </div>

        <?php if ($dismissible !== 'none') : ?>
            <div class="dropdown ms-3 flex-shrink-0">
                <button type="button" class="btn btn-sm btn-link alert-link p-0" data-bs-toggle="dropdown" data-bs-display="static" aria-expanded="false" aria-label="<?php echo Text::_('JCLOSE'); ?>">
                    <span class="fa-solid fa-xmark fs-3" aria-hidden="true"></span>
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <?php if ($dismissible === 'session' || $dismissible === 'forever') : ?>
                        <li>
                            <button type="button" class="dropdown-item" data-dismiss-message="session">
                                <span class="fa-regular fa-eye-slash me-2" aria-hidden="true"></span>
                                <?php echo Text::_('COM_J2COMMERCE_DASHBOARD_MSG_DISMISS_SESSION'); ?>
                            </button>
                        </li>
                    <?php endif; ?>
                    <?php if ($dismissible === 'forever') : ?>
                        <li>
                            <button type="button" class="dropdown-item" data-dismiss-message="forever">
                                <span class="fa-regular fa-bell-slash me-2" aria-hidden="true"></span>
                                <?php echo Text::_('COM_J2COMMERCE_DASHBOARD_MSG_DISMISS_FOREVER'); ?>
                            </button>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        <?php endif; ?>
    </div>
</div>
