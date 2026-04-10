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

use J2Commerce\Component\J2commerce\Administrator\Helper\J2htmlHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\User\UserFactoryInterface;


/** @var array $displayData */
$total      = (int) ($displayData['total'] ?? 0);
$guests     = (int) ($displayData['guests'] ?? 0);
$registered = (int) ($displayData['registered'] ?? 0);
$users      = $displayData['users'] ?? [];
$userFactory = Factory::getContainer()->get(UserFactoryInterface::class);


?>

<div class="card j2commerce-liveusers-widget" id="j2commerce-liveusers">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h2 class="mb-0 fs-4"><span class="fa-solid fa-users me-2 text-info" aria-hidden="true"></span><?php echo Text::_('COM_J2COMMERCE_LIVE_USERS'); ?></h2>
        <span class="<?php echo J2htmlHelper::badgeClass('badge text-bg-success'); ?>"><?php echo $total; ?></span>
    </div>
    <div class="card-body">
        <?php if ($total > 0) : ?>
        <div class="row">
            <div class="col-2 stats-sidebar">
                <div class="report-stat-box p-2 <?php echo J2htmlHelper::badgeClass('text-bg-success'); ?> mb-2 text-center">
                    <div class="fs-2 fw-bold j2commerce-liveusers-total"><?php echo $total; ?></div>
                    <div class="report-stat-title small"><?php echo Text::_('COM_J2COMMERCE_LIVE_USERS_TOTAL'); ?></div>
                </div>
                <div class="report-stat-box p-2 <?php echo J2htmlHelper::badgeClass('text-bg-info'); ?> mb-2 text-center">
                    <div class="fs-2 fw-bold j2commerce-liveusers-registered"><?php echo $registered; ?></div>
                    <div class="report-stat-title small"><?php echo Text::_('COM_J2COMMERCE_LIVE_USERS_REGISTERED'); ?></div>
                </div>
                <div class="report-stat-box p-2 <?php echo J2htmlHelper::badgeClass('text-bg-warning'); ?> text-center">
                    <div class="fs-2 fw-bold j2commerce-liveusers-guests"><?php echo $guests; ?></div>
                    <div class="report-stat-title small"><?php echo Text::_('COM_J2COMMERCE_LIVE_USERS_GUESTS'); ?></div>
                </div>
            </div>
            <div class="col-10 stats-content">
                <?php if (!empty($users)) : ?>
                <h3 class="h5"><?php echo Text::_('COM_J2COMMERCE_LIVE_USERS_ACTIVE'); ?></h3>
                <ul class="list-group list-group-flush j2commerce-liveusers-list">
                    <?php foreach ($users as $user) :
                        $user_fullname  = $userFactory->loadUserById($user->userid)->name;
                        $minutesAgo = max(0, (int) floor((time() - (int) $user->time) / 60));
                        $timeText = $minutesAgo < 1
                            ? Text::_('COM_J2COMMERCE_LIVE_USERS_JUST_NOW')
                            : Text::sprintf('COM_J2COMMERCE_LIVE_USERS_MINUTES_AGO', $minutesAgo);
                        ?>
                        <li class="list-group-item d-flex justify-content-between align-items-center px-0 small">
                            <span>
                                <span class="icon-user me-1" aria-hidden="true"></span>
                                <?php echo htmlspecialchars($user_fullname, ENT_QUOTES, 'UTF-8'); ?>
                                <span class="ms-1">(<?php echo htmlspecialchars($user->username, ENT_QUOTES, 'UTF-8'); ?>)</span>
                            </span>
                            <small class="text-body-secondary"><?php echo $timeText; ?></small>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <?php else : ?>
                <p class="text-body-secondary mb-0"><?php echo Text::_('COM_J2COMMERCE_LIVE_USERS_GUESTS_ONLY'); ?></p>
                <?php endif; ?>
            </div>
        </div>
        <?php else : ?>
        <div class="text-center text-body-secondary py-4">
            <span class="fa-solid fa-user-slash fs-1 mb-3 d-block" aria-hidden="true"></span>
            <p class="mb-0"><?php echo Text::_('COM_J2COMMERCE_LIVE_USERS_NONE'); ?></p>
        </div>
        <?php endif; ?>
    </div>
</div>
