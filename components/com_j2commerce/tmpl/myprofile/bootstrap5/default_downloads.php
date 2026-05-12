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

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\Registry\Registry;

/** @var \J2Commerce\Component\J2commerce\Site\View\Myprofile\HtmlView $this */

$downloads  = $this->downloads;
$total      = $this->downloadsTotal;
$limit      = (int) $this->params->get('list_limit', 20);
$dateFormat = $this->params->get('date_format', 'Y-m-d');
$nullDate   = '0000-00-00 00:00:00';
?>

<?php if (empty($downloads) && $total === 0): ?>
<div class="alert alert-info" id="j2c-no-downloads"><?php echo Text::_('COM_J2COMMERCE_NO_DOWNLOADS'); ?></div>
<?php else: ?>
<div id="j2c-downloads-container">
    <h4 class="mb-4"><?php echo Text::_('COM_J2COMMERCE_MYPROFILE_DOWNLOADS'); ?></h4>
    <!-- Search -->
    <div class="mb-3">
        <input type="text" id="j2c-download-search" class="form-control" placeholder="<?php echo $this->escape(Text::_('COM_J2COMMERCE_MYPROFILE_SEARCH_DOWNLOADS')); ?>" autocomplete="off">
    </div>

    <!-- Downloads table -->
    <div id="j2c-downloads-table-wrap">
        <div class="table-responsive">
            <table class="table" id="j2c-downloads-table">
                <thead>
                    <tr>
                        <th scope="col"><?php echo Text::_('COM_J2COMMERCE_ORDER'); ?></th>
                        <th scope="col"><?php echo Text::_('COM_J2COMMERCE_FILES'); ?></th>
                        <th scope="col"><?php echo Text::_('COM_J2COMMERCE_ACCESS_EXPIRES'); ?></th>
                        <th scope="col" class="text-center"><?php echo Text::_('COM_J2COMMERCE_DOWNLOADS_REMAINING'); ?></th>
                        <th scope="col" class="text-center" style="width:1%"><span class="visually-hidden"><?php echo Text::_('COM_J2COMMERCE_ACTIONS'); ?></span></th>
                    </tr>
                </thead>
                <tbody id="j2c-downloads-body">
                    <?php foreach ($downloads as $dl): ?>
                    <?php
                    $notGranted = empty($dl->access_granted) || $dl->access_granted === $nullDate;
                    $expired = false;
                    $limitReached = false;

                    if (!$notGranted && $dl->access_expires !== $nullDate && strtotime($dl->access_expires) < time()) {
                        $expired = true;
                    }

                    // Download limit comes from product params, not productfile download_total
                    $downloadLimit = 0;
                    if (!empty($dl->product_params)) {
                        $downloadLimit = (int) (new Registry($dl->product_params))->get('download_limit', 0);
                    }

                    $limitCount = (int) ($dl->limit_count ?? 0);
                    if ($downloadLimit > 0 && $limitCount >= $downloadLimit) {
                        $limitReached = true;
                    }

                    $remaining = $downloadLimit > 0 ? max(0, $downloadLimit - $limitCount) : -1;
                    $canDownload = !$notGranted && !$expired && !$limitReached && !empty($dl->product_file_save_name);

                    $orderViewUrl = Route::_('index.php?option=com_j2commerce&view=myprofile&layout=order&order_id=' . urlencode($dl->order_id));
                    $displayName = $dl->product_file_display_name ?? '';
                    ?>
                    <tr data-order="<?php echo $this->escape($dl->order_id); ?>" data-file="<?php echo $this->escape($displayName); ?>">
                        <td><a href="<?php echo $orderViewUrl; ?>" title="<?php echo $this->escape($dl->order_id); ?>"><?php echo $this->escape($dl->order_id); ?></a></td>
                        <td>
                            <?php if (!empty($displayName)): ?>
                                <small class="fw-medium"><?php echo $this->escape($displayName); ?></small>
                            <?php else: ?>
                                <span class="text-muted fst-italic"><?php echo Text::_('COM_J2COMMERCE_FILE_UNAVAILABLE'); ?></span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($notGranted): ?>
                                <span class="badge text-bg-dark"><?php echo Text::_('COM_J2COMMERCE_DOWNLOAD_PENDING'); ?></span>
                            <?php elseif ($dl->access_expires === $nullDate): ?>
                                <?php echo Text::_('COM_J2COMMERCE_NEVER_EXPIRES'); ?>
                            <?php else: ?>
                                <?php echo HTMLHelper::_('date', $dl->access_expires, $dateFormat); ?>
                            <?php endif; ?>
                        </td>
                        <td class="text-center">
                            <?php echo $remaining >= 0 ? $remaining : '&infin;'; ?>
                        </td>
                        <td class="text-center">
                            <?php if ($canDownload): ?>
                            <a href="<?php echo Route::_('index.php?option=com_j2commerce&task=myprofile.download&token=' . urlencode($dl->order_id) . '&fid=' . (int) $dl->j2commerce_productfile_id); ?>"
                               class="btn btn-sm btn-soft-success" title="<?php echo Text::_('COM_J2COMMERCE_DOWNLOAD'); ?>">
                                <span class="icon-download" aria-hidden="true"></span>
                            </a>
                            <?php elseif ($notGranted): ?>
                            <span class="badge text-bg-dark"><?php echo Text::_('COM_J2COMMERCE_DOWNLOAD_PENDING'); ?></span>
                            <?php elseif ($expired): ?>
                            <span class="badge text-bg-danger"><?php echo Text::_('COM_J2COMMERCE_EXPIRED'); ?></span>
                            <?php elseif ($limitReached): ?>
                            <span class="badge text-bg-warning"><?php echo Text::_('COM_J2COMMERCE_LIMIT_REACHED'); ?></span>
                            <?php else: ?>
                            <span class="badge text-bg-dark"><?php echo Text::_('COM_J2COMMERCE_FILE_UNAVAILABLE'); ?></span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <div class="d-flex justify-content-end align-items-center" id="j2c-downloads-pagination">
            <?php
            $start = 1;
            $end   = min($limit, $total);
            ?>
            <?php if ($total > $limit): ?>
            <nav aria-label="<?php echo Text::_('JLIB_HTML_PAGINATION'); ?>">
                <ul class="pagination my-0" id="j2c-downloads-pagination-list">
                    <?php
                    $pages = (int) ceil($total / $limit);
                    for ($p = 0; $p < $pages; $p++):
                        $active = ($p === 0) ? ' active' : '';
                    ?>
                    <li class="page-item<?php echo $active; ?>">
                        <a class="page-link j2c-download-page-link" href="#" data-page="<?php echo $p; ?>"><?php echo $p + 1; ?></a>
                    </li>
                    <?php endfor; ?>
                </ul>
            </nav>
            <?php endif; ?>
            <span class="text-muted small ms-3 align-self-center" id="j2c-downloads-count">
                <?php echo $start . ' - ' . $end . ' / ' . $total . ' ' . Text::_('COM_J2COMMERCE_ITEMS'); ?>
            </span>
        </div>
    </div>
</div>
<?php endif; ?>
