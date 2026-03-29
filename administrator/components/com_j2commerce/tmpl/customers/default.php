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
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\Router\Route;

/** @var \J2Commerce\Component\J2commerce\Administrator\View\Customers\HtmlView $this */

$wa = $this->getDocument()->getWebAssetManager();
$wa->useScript('table.columns')
    ->useScript('multiselect');

$listOrder = $this->escape($this->state->get('list.ordering'));
$listDirn  = $this->escape($this->state->get('list.direction'));

?>
<?php echo $this->navbar; ?>

<form action="<?php echo Route::_('index.php?option=com_j2commerce&view=customers'); ?>" method="post" name="adminForm" id="adminForm">
    <div class="row">
        <div class="col-md-12">
            <div id="j-main-container" class="j-main-container">
                <?php echo LayoutHelper::render('joomla.searchtools.default', ['view' => $this]); ?>

                <?php if (empty($this->items)) : ?>
                    <div class="alert alert-info">
                        <span class="icon-info-circle" aria-hidden="true"></span><span class="visually-hidden"><?php echo Text::_('INFO'); ?></span>
                        <?php echo Text::_('JGLOBAL_NO_MATCHING_RESULTS'); ?>
                    </div>
                <?php else : ?>
                    <table class="table itemList" id="customerList">
                        <caption class="visually-hidden">
                            <?php echo Text::_('COM_J2COMMERCE_CUSTOMERS_TABLE_CAPTION'); ?>
                        </caption>
                        <thead>
                            <tr>
                                <td class="w-1 text-center">
                                    <?php echo HTMLHelper::_('grid.checkall'); ?>
                                </td>
                                <th scope="col">
                                    <?php echo HTMLHelper::_('searchtools.sort', 'COM_J2COMMERCE_HEADING_CUSTOMER_NAME', 'customer_name', $listDirn, $listOrder); ?>
                                </th>
                                <th scope="col">
                                    <?php echo HTMLHelper::_('searchtools.sort', 'COM_J2COMMERCE_HEADING_EMAIL', 'a.email', $listDirn, $listOrder); ?>
                                </th>
                                <th scope="col" class="d-none d-md-table-cell">
                                    <?php echo Text::_('COM_J2COMMERCE_HEADING_ADDRESS'); ?>
                                </th>
                                <th scope="col" class="d-none d-md-table-cell">
                                    <?php echo HTMLHelper::_('searchtools.sort', 'COM_J2COMMERCE_HEADING_CITY', 'a.city', $listDirn, $listOrder); ?>
                                </th>
                                <th scope="col" class="d-none d-lg-table-cell">
                                    <?php echo HTMLHelper::_('searchtools.sort', 'COM_J2COMMERCE_HEADING_COUNTRY', 'c.country_name', $listDirn, $listOrder); ?>
                                </th>
                                <th scope="col" class="d-none d-lg-table-cell">
                                    <?php echo HTMLHelper::_('searchtools.sort', 'COM_J2COMMERCE_HEADING_ZONE', 'z.zone_name', $listDirn, $listOrder); ?>
                                </th>
                                <th scope="col" class="d-none d-lg-table-cell">
                                    <?php echo Text::_('COM_J2COMMERCE_HEADING_PHONE'); ?>
                                </th>
                                <th scope="col" class="w-5 text-center">
                                    <?php echo Text::_('COM_J2COMMERCE_HEADING_ORDER_COUNT'); ?>
                                </th>
                                <th scope="col" class="w-5 text-center d-none d-md-table-cell">
                                    <?php echo HTMLHelper::_('searchtools.sort', 'COM_J2COMMERCE_HEADING_ID', 'a.j2commerce_address_id', $listDirn, $listOrder); ?>
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($this->items as $i => $item) : ?>
                                <tr class="row<?php echo $i % 2; ?>">
                                    <td class="text-center">
                                        <?php echo HTMLHelper::_('grid.id', $i, $item->j2commerce_address_id, false, 'cid', 'cb', $item->customer_name); ?>
                                    </td>
                                    <th scope="row">
                                        <a href="<?php echo Route::_('index.php?option=com_j2commerce&task=customer.edit&id=' . (int) $item->j2commerce_address_id); ?>">
                                            <?php echo $this->escape($item->customer_name); ?>
                                        </a>
                                        <?php if (!empty($item->company)) : ?>
                                            <br><small class="text-muted"><?php echo $this->escape($item->company); ?></small>
                                        <?php endif; ?>
                                    </th>
                                    <td>
                                        <a href="<?php echo Route::_('index.php?option=com_j2commerce&task=customer.edit&id=' . (int) $item->j2commerce_address_id); ?>">
                                            <?php echo $this->escape($item->email); ?>
                                        </a>
                                    </td>
                                    <td class="d-none d-md-table-cell">
                                        <?php echo $this->escape($item->address_1); ?>
                                        <?php if (!empty($item->address_2)) : ?>
                                            <br><small><?php echo $this->escape($item->address_2); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td class="d-none d-md-table-cell">
                                        <?php echo $this->escape($item->city); ?>
                                        <?php if (!empty($item->zip)) : ?>
                                            <small class="text-muted"><?php echo $this->escape($item->zip); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td class="d-none d-lg-table-cell">
                                        <?php echo $this->escape($item->country_name); ?>
                                    </td>
                                    <td class="d-none d-lg-table-cell">
                                        <?php echo $this->escape($item->zone_name); ?>
                                    </td>
                                    <td class="d-none d-lg-table-cell">
                                        <?php echo $this->escape($item->phone_1); ?>
                                    </td>
                                    <td class="text-center">
                                        <?php if ($item->order_count > 0) : ?>
                                            <span class="badge bg-success"><?php echo (int) $item->order_count; ?></span>
                                        <?php else : ?>
                                            <span class="badge bg-secondary">0</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center d-none d-md-table-cell">
                                        <?php echo (int) $item->j2commerce_address_id; ?>
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
