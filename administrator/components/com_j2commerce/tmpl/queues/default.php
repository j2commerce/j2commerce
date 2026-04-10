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

/** @var \J2Commerce\Component\J2commerce\Administrator\View\Queues\HtmlView $this */

$wa = $this->getDocument()->getWebAssetManager();
$wa->useScript('table.columns')
    ->useScript('multiselect');

$listOrder = $this->escape($this->state->get('list.ordering'));
$listDirn  = $this->escape($this->state->get('list.direction'));

$statusBadges = [
    'pending'    => 'text-bg-info',
    'processing' => 'text-bg-warning',
    'completed'  => 'text-bg-success',
    'failed'     => 'text-bg-danger',
    'dead'       => 'text-bg-dark',
];

?>

<?php echo $this->navbar; ?>

<form action="<?php echo Route::_('index.php?option=com_j2commerce&view=queues'); ?>" method="post" name="adminForm" id="adminForm">
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
                    <table class="table itemList" id="queuesList">
                        <caption class="visually-hidden">
                            <?php echo Text::_('COM_J2COMMERCE_QUEUES'); ?>,
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
                                <th scope="col" class="w-10">
                                    <?php echo HTMLHelper::_('searchtools.sort', 'COM_J2COMMERCE_HEADING_QUEUE_TYPE', 'a.queue_type', $listDirn, $listOrder); ?>
                                </th>
                                <th scope="col" class="w-8 d-none d-md-table-cell">
                                    <?php echo HTMLHelper::_('searchtools.sort', 'COM_J2COMMERCE_HEADING_ITEM_TYPE', 'a.item_type', $listDirn, $listOrder); ?>
                                </th>
                                <th scope="col">
                                    <?php echo HTMLHelper::_('searchtools.sort', 'COM_J2COMMERCE_HEADING_RELATION_ID', 'a.relation_id', $listDirn, $listOrder); ?>
                                </th>
                                <th scope="col" class="w-8 text-center d-none d-md-table-cell">
                                    <?php echo HTMLHelper::_('searchtools.sort', 'COM_J2COMMERCE_HEADING_ATTEMPTS', 'a.attempt_count', $listDirn, $listOrder); ?>
                                </th>
                                <th scope="col" class="w-5 text-center d-none d-lg-table-cell">
                                    <?php echo HTMLHelper::_('searchtools.sort', 'COM_J2COMMERCE_HEADING_PRIORITY', 'a.priority', $listDirn, $listOrder); ?>
                                </th>
                                <th scope="col" class="w-12 d-none d-md-table-cell">
                                    <?php echo HTMLHelper::_('searchtools.sort', 'COM_J2COMMERCE_HEADING_NEXT_ATTEMPT', 'a.next_attempt_at', $listDirn, $listOrder); ?>
                                </th>
                                <th scope="col" class="w-10 d-none d-md-table-cell">
                                    <?php echo HTMLHelper::_('searchtools.sort', 'COM_J2COMMERCE_HEADING_CREATED', 'a.created_on', $listDirn, $listOrder); ?>
                                </th>
                                <th scope="col" class="w-5 d-none d-md-table-cell">
                                    <?php echo HTMLHelper::_('searchtools.sort', 'JGRID_HEADING_ID', 'a.j2commerce_queue_id', $listDirn, $listOrder); ?>
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($this->items as $i => $item) : ?>
                            <?php $badgeClass = J2htmlHelper::badgeClass($statusBadges[$item->status] ?? 'bg-secondary'); ?>
                            <tr class="row<?php echo $i % 2; ?>">
                                <td class="text-center">
                                    <?php echo HTMLHelper::_('grid.id', $i, $item->j2commerce_queue_id, false, 'cid', 'cb', $item->relation_id); ?>
                                </td>
                                <td class="text-center">
                                    <span class="badge <?php echo $badgeClass; ?>">
                                        <?php echo Text::_('COM_J2COMMERCE_QUEUE_STATUS_' . strtoupper($item->status)); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php echo $this->escape($item->queue_type); ?>
                                </td>
                                <td class="d-none d-md-table-cell">
                                    <?php echo $this->escape($item->item_type); ?>
                                </td>
                                <td>
                                    <?php echo $this->escape($item->relation_id); ?>
                                </td>
                                <td class="text-center d-none d-md-table-cell">
                                    <?php echo (int) $item->attempt_count; ?>/<?php echo (int) $item->max_attempts; ?>
                                </td>
                                <td class="text-center d-none d-lg-table-cell">
                                    <?php echo (int) $item->priority; ?>
                                </td>
                                <td class="d-none d-md-table-cell">
                                    <?php echo $item->next_attempt_at ? HTMLHelper::_('date', $item->next_attempt_at, Text::_('DATE_FORMAT_LC5')) : '&mdash;'; ?>
                                </td>
                                <td class="d-none d-md-table-cell">
                                    <?php echo HTMLHelper::_('date', $item->created_on, Text::_('DATE_FORMAT_LC5')); ?>
                                </td>
                                <td class="d-none d-md-table-cell">
                                    <?php echo (int) $item->j2commerce_queue_id; ?>
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

<?php echo $this->footer ?? ''; ?>
