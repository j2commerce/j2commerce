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
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\Router\Route;

/** @var \J2Commerce\Component\J2commerce\Administrator\View\Queuelogs\HtmlView $this */

$wa = $this->getDocument()->getWebAssetManager();
$wa->useScript('table.columns')
    ->useScript('multiselect');

HTMLHelper::_('bootstrap.modal', '.queuelog-details-modal');

$listOrder = $this->escape($this->state->get('list.ordering'));
$listDirn  = $this->escape($this->state->get('list.direction'));

$statusBadges = [
    'running'   => 'text-bg-warning',
    'completed' => 'text-bg-success',
    'error'     => 'text-bg-danger',
];

?>

<?php echo $this->navbar; ?>

<form action="<?php echo Route::_('index.php?option=com_j2commerce&view=queuelogs'); ?>" method="post" name="adminForm" id="adminForm">
    <div class="row">
        <div class="col-md-12">
            <div id="j-main-container" class="j-main-container">
                <?php echo LayoutHelper::render('joomla.searchtools.default', ['view' => $this]); ?>

                <?php if (empty($this->items)) : ?>
                    <div class="alert alert-info">
                        <span class="icon-info-circle" aria-hidden="true"></span>
                        <span class="visually-hidden"><?php echo Text::_('INFO'); ?></span>
                        <?php echo Text::_('JGLOBAL_NO_MATCHING_RESULTS'); ?>
                    </div>
                <?php else : ?>
                    <table class="table itemList" id="queuelogsList">
                        <caption class="visually-hidden">
                            <?php echo Text::_('COM_J2COMMERCE_QUEUE_LOGS'); ?>,
                            <span id="orderedBy"><?php echo Text::_('JGLOBAL_SORTED_BY'); ?></span>,
                            <span id="filteredBy"><?php echo Text::_('JGLOBAL_FILTERED_BY'); ?></span>
                        </caption>
                        <thead>
                            <tr>
                                <td class="w-1 text-center">
                                    <?php echo HTMLHelper::_('grid.checkall'); ?>
                                </td>
                                <th scope="col" class="w-5 text-center">
                                    <?php echo HTMLHelper::_('searchtools.sort', 'JSTATUS', 'a.status', $listDirn, $listOrder); ?>
                                </th>
                                <th scope="col" class="w-12">
                                    <?php echo HTMLHelper::_('searchtools.sort', 'COM_J2COMMERCE_HEADING_QUEUE_TYPE', 'a.queue_type', $listDirn, $listOrder); ?>
                                </th>
                                <th scope="col" class="w-12">
                                    <?php echo HTMLHelper::_('searchtools.sort', 'COM_J2COMMERCE_HEADING_STARTED_AT', 'a.started_at', $listDirn, $listOrder); ?>
                                </th>
                                <th scope="col" class="w-8 d-none d-md-table-cell">
                                    <?php echo HTMLHelper::_('searchtools.sort', 'COM_J2COMMERCE_HEADING_DURATION', 'a.duration_ms', $listDirn, $listOrder); ?>
                                </th>
                                <th scope="col" class="w-5 d-none d-md-table-cell">
                                    <?php echo HTMLHelper::_('searchtools.sort', 'COM_J2COMMERCE_HEADING_ITEMS_SUCCESS', 'a.items_success', $listDirn, $listOrder); ?>
                                </th>
                                <th scope="col" class="w-5 d-none d-md-table-cell">
                                    <?php echo HTMLHelper::_('searchtools.sort', 'COM_J2COMMERCE_HEADING_ITEMS_FAILED', 'a.items_failed', $listDirn, $listOrder); ?>
                                </th>
                                <th scope="col" class="w-5 d-none d-lg-table-cell">
                                    <?php echo HTMLHelper::_('searchtools.sort', 'COM_J2COMMERCE_HEADING_ITEMS_SKIPPED', 'a.items_skipped', $listDirn, $listOrder); ?>
                                </th>
                                <th scope="col" class="w-5 d-none d-md-table-cell">
                                    <?php echo HTMLHelper::_('searchtools.sort', 'JGRID_HEADING_ID', 'a.j2commerce_queue_log_id', $listDirn, $listOrder); ?>
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($this->items as $i => $item) : ?>
                            <?php
                            $badgeClass = J2htmlHelper::badgeClass($statusBadges[$item->status] ?? 'bg-secondary');
                            $durationMs = (int) $item->duration_ms;
                            $duration   = $durationMs >= 1000
                                ? number_format($durationMs / 1000, 1) . 's'
                                : $durationMs . 'ms';

                            $detailsJson = $item->details ?? '[]';
                            $successCount = (int) $item->items_success;
                            $failedCount  = (int) $item->items_failed;
                            $skippedCount = (int) $item->items_skipped;
                            ?>
                            <tr class="row<?php echo $i % 2; ?>">
                                <td class="text-center">
                                    <?php echo HTMLHelper::_('grid.id', $i, $item->j2commerce_queue_log_id, false, 'cid', 'cb', $item->queue_type); ?>
                                </td>
                                <td class="text-center">
                                    <span class="badge <?php echo $badgeClass; ?>">
                                        <?php echo Text::_('COM_J2COMMERCE_QUEUE_LOG_STATUS_' . strtoupper($item->status)); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php echo $this->escape($item->queue_type); ?>
                                </td>
                                <td>
                                    <?php echo HTMLHelper::_('date', $item->started_at, Text::_('DATE_FORMAT_LC5')); ?>
                                </td>
                                <td class="d-none d-md-table-cell">
                                    <?php echo $item->duration_ms !== null ? $duration : '&mdash;'; ?>
                                </td>
                                <td class="d-none d-md-table-cell">
                                    <?php if ($successCount > 0) : ?>
                                        <a href="#" class="<?php echo J2htmlHelper::badgeClass('badge text-bg-success'); ?> text-decoration-none js-queuelog-details"
                                           data-details="<?php echo $this->escape($detailsJson); ?>"
                                           data-filter="completed"
                                           data-title="<?php echo $this->escape(Text::sprintf('COM_J2COMMERCE_QUEUE_LOG_SUCCESSFUL_ITEMS', $successCount)); ?>">
                                            <?php echo $successCount; ?>
                                        </a>
                                    <?php else : ?>
                                        <span class="<?php echo J2htmlHelper::badgeClass('badge text-bg-light'); ?>">0</span>
                                    <?php endif; ?>
                                </td>
                                <td class="d-none d-md-table-cell">
                                    <?php if ($failedCount > 0) : ?>
                                        <a href="#" class="<?php echo J2htmlHelper::badgeClass('badge text-bg-danger'); ?> text-decoration-none js-queuelog-details"
                                           data-details="<?php echo $this->escape($detailsJson); ?>"
                                           data-filter="failed"
                                           data-title="<?php echo $this->escape(Text::sprintf('COM_J2COMMERCE_QUEUE_LOG_FAILED_ITEMS', $failedCount)); ?>">
                                            <?php echo $failedCount; ?>
                                        </a>
                                    <?php else : ?>
                                        <span class="<?php echo J2htmlHelper::badgeClass('badge text-bg-light'); ?>">0</span>
                                    <?php endif; ?>
                                </td>
                                <td class="d-none d-lg-table-cell">
                                    <?php if ($skippedCount > 0) : ?>
                                        <a href="#" class="<?php echo J2htmlHelper::badgeClass('badge text-bg-warning'); ?> text-decoration-none js-queuelog-details"
                                           data-details="<?php echo $this->escape($detailsJson); ?>"
                                           data-filter="skipped"
                                           data-title="<?php echo $this->escape(Text::sprintf('COM_J2COMMERCE_QUEUE_LOG_SKIPPED_ITEMS', $skippedCount)); ?>">
                                            <?php echo $skippedCount; ?>
                                        </a>
                                    <?php else : ?>
                                        <span class="<?php echo J2htmlHelper::badgeClass('badge text-bg-light'); ?>">0</span>
                                    <?php endif; ?>
                                </td>
                                <td class="d-none d-md-table-cell">
                                    <?php echo (int) $item->j2commerce_queue_log_id; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>

                    <?php echo $this->pagination->getListFooter(); ?>
                <?php endif; ?>

                <input type="hidden" name="task" value="">
                <input type="hidden" name="boxchecked" value="0">
                <?php echo HTMLHelper::_('form.token'); ?>
            </div>
        </div>
    </div>
</form>

<div class="modal fade" id="queuelogDetailsModal" tabindex="-1" aria-labelledby="queuelogDetailsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="queuelogDetailsModalLabel"></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?php echo Text::_('JCLOSE'); ?>"></button>
            </div>
            <div class="modal-body p-4" id="queuelogDetailsModalBody"></div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const modalEl = document.getElementById('queuelogDetailsModal');
    if (!modalEl) return;

    const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
    const titleEl = document.getElementById('queuelogDetailsModalLabel');
    const bodyEl = document.getElementById('queuelogDetailsModalBody');

    const statusMap = {
        completed: '<?php echo Text::_('COM_J2COMMERCE_QUEUE_LOG_STATUS_COMPLETED'); ?>',
        failed:    '<?php echo Text::_('COM_J2COMMERCE_QUEUE_LOG_STATUS_FAILED'); ?>',
        skipped:   '<?php echo Text::_('COM_J2COMMERCE_QUEUE_LOG_STATUS_SKIPPED'); ?>'
    };

    const filterToStatuses = {
        completed: ['completed'],
        failed:    ['failed', 'error'],
        skipped:   ['skipped']
    };

    document.addEventListener('click', (e) => {
        const link = e.target.closest('.js-queuelog-details');
        if (!link) return;

        e.preventDefault();

        const title = link.dataset.title || '';
        const filter = link.dataset.filter || '';
        let details = [];

        try {
            details = JSON.parse(link.dataset.details || '[]');
        } catch (err) {
            details = [];
        }

        const matchStatuses = filterToStatuses[filter] || [filter];
        const filtered = details.filter(item => matchStatuses.includes(item.status));

        titleEl.textContent = title;

        if (!filtered.length) {
            bodyEl.innerHTML = '<p class="text-muted"><?php echo Text::_('JGLOBAL_NO_MATCHING_RESULTS'); ?></p>';
        } else {
            const showError = filter === 'failed' || filter === 'skipped';
            const esc = (v) => String(v ?? '').replace(/</g, '&lt;');
            let html = '<table class="table table-sm">';
            html += '<thead><tr>';
            html += '<th><?php echo Text::_('COM_J2COMMERCE_HEADING_QUEUE_ID'); ?></th>';
            html += '<th><?php echo Text::_('COM_J2COMMERCE_HEADING_ITEM_TYPE'); ?></th>';
            html += '<th><?php echo Text::_('COM_J2COMMERCE_HEADING_RELATION_ID'); ?></th>';
            html += '<th><?php echo Text::_('JSTATUS'); ?></th>';
            if (showError) {
                html += '<th><?php echo Text::_('COM_J2COMMERCE_HEADING_ERROR_MESSAGE'); ?></th>';
            }
            html += '</tr></thead><tbody>';

            for (const item of filtered) {
                html += '<tr>';
                html += '<td>' + esc(item.id) + '</td>';
                html += '<td>' + esc(item.item_type) + '</td>';
                html += '<td>' + esc(item.relation_id) + '</td>';
                html += '<td><span class="badge ' + (item.status === 'completed' ? '<?php echo J2htmlHelper::badgeClass('text-bg-success'); ?>' : item.status === 'failed' ? '<?php echo J2htmlHelper::badgeClass('text-bg-danger'); ?>' : '<?php echo J2htmlHelper::badgeClass('text-bg-warning'); ?>') + '">' + esc(statusMap[item.status] || item.status) + '</span></td>';
                if (showError) {
                    html += '<td><small class="text-danger">' + esc(item.error || '&mdash;') + '</small></td>';
                }
                html += '</tr>';
            }

            html += '</tbody></table>';
            bodyEl.innerHTML = html;
        }

        modal.show();
    });
});
</script>

<?php echo $this->footer ?? ''; ?>
