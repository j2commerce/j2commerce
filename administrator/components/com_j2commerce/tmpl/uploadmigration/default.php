<?php

/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use J2Commerce\Component\J2commerce\Administrator\Helper\J2CommerceHelper;
use J2Commerce\Component\J2commerce\Administrator\Model\UploadmigrationModel;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

/** @var \J2Commerce\Component\J2commerce\Administrator\View\Uploadmigration\HtmlView $this */

J2CommerceHelper::strapper()->addCSS();

$scan    = $this->scan;
$counts  = $scan['counts'];
$entries = $scan['entries'];

$stateLabels = [
    UploadmigrationModel::STATE_MOVABLE    => ['COM_J2COMMERCE_UPLOAD_MIGRATION_STATE_MOVABLE', 'success'],
    UploadmigrationModel::STATE_PRESENT    => ['COM_J2COMMERCE_UPLOAD_MIGRATION_STATE_PRESENT', 'info'],
    UploadmigrationModel::STATE_ORPHAN     => ['COM_J2COMMERCE_UPLOAD_MIGRATION_STATE_ORPHAN', 'secondary'],
    UploadmigrationModel::STATE_UNRESOLVED => ['COM_J2COMMERCE_UPLOAD_MIGRATION_STATE_UNRESOLVED', 'warning'],
];
?>
<?php echo $this->navbar; ?>

<form action="<?php echo Route::_('index.php?option=com_j2commerce&view=uploadmigration'); ?>" method="post" name="adminForm" id="adminForm">
    <div class="row">
        <div class="col-md-12">
            <div id="j-main-container" class="j-main-container">
                <div class="card mb-4">
                    <div class="card-body">
                        <h2>
                            <span class="fa-solid fa-truck-ramp-box me-2 text-primary" aria-hidden="true"></span>
                            <?php echo Text::_('COM_J2COMMERCE_UPLOAD_MIGRATION'); ?>
                        </h2>
                        <p class="mb-0"><?php echo Text::_('COM_J2COMMERCE_UPLOAD_MIGRATION_DESCRIPTION'); ?></p>
                    </div>
                </div>

                <?php if ($scan['legacy'] === null) : ?>
                    <div class="alert alert-success">
                        <span class="icon-check-circle" aria-hidden="true"></span>
                        <?php echo Text::sprintf('COM_J2COMMERCE_UPLOAD_MIGRATION_NOTHING_TO_DO', $this->escape(UploadmigrationModel::LEGACY_RELATIVE_PATH)); ?>
                    </div>
                <?php else : ?>
                    <?php if ($scan['root'] === null) : ?>
                        <div class="alert alert-danger">
                            <span class="icon-warning" aria-hidden="true"></span>
                            <?php echo Text::_('COM_J2COMMERCE_UPLOAD_MIGRATION_NO_ROOT'); ?>
                        </div>
                    <?php endif; ?>

                    <div class="card mb-4">
                        <div class="card-body">
                            <dl class="row mb-0">
                                <dt class="col-sm-3"><?php echo Text::_('COM_J2COMMERCE_UPLOAD_MIGRATION_LEGACY_FOLDER'); ?></dt>
                                <dd class="col-sm-9"><code><?php echo $this->escape(UploadmigrationModel::LEGACY_RELATIVE_PATH); ?></code></dd>
                                <dt class="col-sm-3"><?php echo Text::_('COM_J2COMMERCE_UPLOAD_MIGRATION_ATTACHMENT_ROOT'); ?></dt>
                                <dd class="col-sm-9 mb-0">
                                    <code><?php echo $this->escape($scan['root_display'] !== '' ? $scan['root_display'] : Text::_('COM_J2COMMERCE_UPLOAD_MIGRATION_UNAVAILABLE')); ?></code>
                                </dd>
                            </dl>
                        </div>
                    </div>

                    <div class="row mb-4">
                        <?php foreach ($stateLabels as $state => [$label, $variant]) : ?>
                            <div class="col-6 col-lg-3 mb-3 mb-lg-0">
                                <div class="alert alert-<?php echo $variant; ?> my-0 w-100 border-0">
                                    <div class="display-6 mb-2"><?php echo (int) ($counts[$state] ?? 0); ?></div>
                                    <div><?php echo Text::_($label); ?></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <?php if (empty($entries)) : ?>
                        <div class="alert alert-info">
                            <span class="icon-info-circle" aria-hidden="true"></span>
                            <?php echo Text::_('COM_J2COMMERCE_UPLOAD_MIGRATION_FOLDER_EMPTY'); ?>
                        </div>
                    <?php else : ?>
                        <table class="table itemList" id="uploadMigrationList">
                            <caption class="visually-hidden"><?php echo Text::_('COM_J2COMMERCE_UPLOAD_MIGRATION_TABLE_CAPTION'); ?></caption>
                            <thead>
                                <tr>
                                    <th scope="col"><?php echo Text::_('COM_J2COMMERCE_UPLOAD_MIGRATION_HEADING_FILE'); ?></th>
                                    <th scope="col" class="w-10 d-none d-md-table-cell"><?php echo Text::_('COM_J2COMMERCE_UPLOAD_MIGRATION_HEADING_SIZE'); ?></th>
                                    <th scope="col" class="w-10 d-none d-md-table-cell"><?php echo Text::_('COM_J2COMMERCE_UPLOAD_MIGRATION_HEADING_ROW_STATUS'); ?></th>
                                    <th scope="col"><?php echo Text::_('COM_J2COMMERCE_UPLOAD_MIGRATION_HEADING_DESTINATION'); ?></th>
                                    <th scope="col" class="w-15"><?php echo Text::_('COM_J2COMMERCE_UPLOAD_MIGRATION_HEADING_OUTCOME'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($entries as $entry) : ?>
                                    <?php [$label, $variant] = $stateLabels[$entry['state']]; ?>
                                    <tr>
                                        <th scope="row"><?php echo $this->escape($entry['name']); ?></th>
                                        <td class="d-none d-md-table-cell"><?php echo HTMLHelper::_('number.bytes', $entry['size']); ?></td>
                                        <td class="d-none d-md-table-cell"><?php echo $entry['status'] !== '' ? $this->escape($entry['status']) : '&mdash;'; ?></td>
                                        <td>
                                            <?php echo $entry['target'] !== '' ? '<code>' . $this->escape($entry['target']) . '</code>' : '&mdash;'; ?>
                                        </td>
                                        <td><span class="badge bg-<?php echo $variant; ?>"><?php echo Text::_($label); ?></span></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                <?php endif; ?>

                <input type="hidden" name="task" value="">
                <?php echo HTMLHelper::_('form.token'); ?>
            </div>
        </div>
    </div>
</form>
