<?php
/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;

$db    = Factory::getContainer()->get('DatabaseDriver');
$query = $db->getQuery(true)
    ->select($db->quoteName(['id', 'title']))
    ->from($db->quoteName('#__usergroups'))
    ->order($db->quoteName('title') . ' ASC');
$db->setQuery($query);
$userGroups = $db->loadObjectList();
?>

<div class="modal fade" id="collapseModal" tabindex="-1" aria-labelledby="collapseModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title fs-5" id="collapseModalLabel"><?php echo Text::_('COM_J2COMMERCE_BATCH_TITLE'); ?></h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?php echo Text::_('JCLOSE'); ?>"></button>
            </div>
            <div class="modal-body px-4 pt-4 pb-2">
                <div class="row">
                    <div class="form-group col-md-6 mb-3">
                        <div class="controls">
                            <label id="batch-group-lbl" for="batch_customer_group_id">
                                <?php echo Text::_('COM_J2COMMERCE_BATCH_USER_GROUP'); ?>
                            </label>
                            <select name="batch_customer_group_id" id="batch_customer_group_id" class="form-select">
                                <option value=""><?php echo Text::_('COM_J2COMMERCE_SELECT_USER_GROUP'); ?></option>
                                <?php foreach ($userGroups as $group) : ?>
                                    <option value="<?php echo (int) $group->id; ?>"><?php echo htmlspecialchars($group->title, ENT_QUOTES, 'UTF-8'); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="form-group col-md-6 mb-3">
                        <div class="controls">
                            <label id="batch-datefrom-lbl" for="batch_date_from">
                                <?php echo Text::_('COM_J2COMMERCE_BATCH_DATE_FROM'); ?>
                            </label>
                            <?php echo HTMLHelper::_('calendar', '', 'batch_date_from', 'batch_date_from', '%Y-%m-%d %H:%M:%S', ['class' => 'form-control', 'showTime' => true]); ?>
                        </div>
                    </div>
                    <div class="form-group col-md-6 mb-3">
                        <div class="controls">
                            <label id="batch-dateto-lbl" for="batch_date_to">
                                <?php echo Text::_('COM_J2COMMERCE_BATCH_DATE_TO'); ?>
                            </label>
                            <?php echo HTMLHelper::_('calendar', '', 'batch_date_to', 'batch_date_to', '%Y-%m-%d %H:%M:%S', ['class' => 'form-control', 'showTime' => true]); ?>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-danger" data-bs-dismiss="modal"><?php echo Text::_('JCANCEL'); ?></button>
                <joomla-toolbar-button task="advancedpricing.batch">
                    <button type="button" class="btn btn-primary"><?php echo Text::_('JGLOBAL_BATCH_PROCESS'); ?></button>
                </joomla-toolbar-button>
            </div>
        </div>
    </div>
</div>
