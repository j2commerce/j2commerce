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

/** @var \J2Commerce\Component\J2commerce\Administrator\View\Taxrules\HtmlView $this */

$wa = $this->getDocument()->getWebAssetManager();
$wa->useScript('table.columns')
    ->useScript('multiselect');

$listOrder = $this->escape($this->state->get('list.ordering'));
$listDirn  = $this->escape($this->state->get('list.direction'));

?>
<?php echo $this->navbar; ?>

<form action="<?php echo Route::_('index.php?option=com_j2commerce&view=taxrules'); ?>" method="post" name="adminForm" id="adminForm">
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
                    <table class="table itemList" id="taxrulesList">
                        <caption class="visually-hidden">
                            <?php echo Text::_('COM_J2COMMERCE_TAXRULES'); ?>,
                            <span id="orderedBy"><?php echo Text::_('JGLOBAL_SORTED_BY'); ?></span>,
                            <span id="filteredBy"><?php echo Text::_('JGLOBAL_FILTERED_BY'); ?></span>
                        </caption>
                        <thead>
                            <tr>
                                <td class="w-1 text-center">
                                    <?php echo HTMLHelper::_('grid.checkall'); ?>
                                </td>
                                <th scope="col">
                                    <?php echo HTMLHelper::_('searchtools.sort', 'COM_J2COMMERCE_HEADING_TAXPROFILE', 'p.taxprofile_name', $listDirn, $listOrder); ?>
                                </th>
                                <th scope="col" class="w-20 d-none d-md-table-cell">
                                    <?php echo HTMLHelper::_('searchtools.sort', 'COM_J2COMMERCE_HEADING_TAXRATE', 'r.taxrate_name', $listDirn, $listOrder); ?>
                                </th>
                                <th scope="col" class="w-15 d-none d-md-table-cell">
                                    <?php echo Text::_('COM_J2COMMERCE_HEADING_ADDRESS_TYPE'); ?>
                                </th>
                                <th scope="col" class="w-5 d-none d-md-table-cell">
                                    <?php echo HTMLHelper::_('searchtools.sort', 'JGRID_HEADING_ID', 'a.j2commerce_taxrule_id', $listDirn, $listOrder); ?>
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($this->items as $i => $item) : ?>
                            <tr class="row<?php echo $i % 2; ?>">
                                <td class="text-center">
                                    <?php echo HTMLHelper::_('grid.id', $i, $item->j2commerce_taxrule_id, false, 'cid', 'cb', $item->taxprofile_name); ?>
                                </td>
                                <td>
                                    <a href="<?php echo Route::_('index.php?option=com_j2commerce&task=taxrule.edit&id=' . $item->j2commerce_taxrule_id); ?>">
                                        <?php echo $this->escape($item->taxprofile_name ?? Text::_('COM_J2COMMERCE_NO_TAXPROFILE')); ?>
                                    </a>
                                </td>
                                <td class="d-none d-md-table-cell">
                                    <?php echo $this->escape($item->taxrate_name ?? Text::_('COM_J2COMMERCE_NO_TAXRATE')); ?>
                                </td>
                                <td class="d-none d-md-table-cell">
                                    <?php
                                    if ($item->address === 'billing') {
                                        echo '<span class="' . J2htmlHelper::badgeClass('badge text-bg-info') . '">' . Text::_('COM_J2COMMERCE_ADDRESS_BILLING') . '</span>';
                                    } elseif ($item->address === 'shipping') {
                                        echo '<span class="' . J2htmlHelper::badgeClass('badge text-bg-success') . '">' . Text::_('COM_J2COMMERCE_ADDRESS_SHIPPING') . '</span>';
                                    } else {
                                        echo $this->escape($item->address);
                                    }
                                    ?>
                                </td>
                                <td class="d-none d-md-table-cell">
                                    <?php echo (int) $item->j2commerce_taxrule_id; ?>
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
