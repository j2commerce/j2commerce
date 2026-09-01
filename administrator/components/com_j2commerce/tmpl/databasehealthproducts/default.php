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

/** @var \J2Commerce\Component\J2commerce\Administrator\View\Databasehealthproducts\HtmlView $this */

$wa = $this->getDocument()->getWebAssetManager();
$wa->useScript('table.columns')
    ->useScript('multiselect');
$wa->registerAndUseScript(
    'com_j2commerce.database-health-products',
    'media/com_j2commerce/js/administrator/databasehealthproducts.js',
    [],
    ['defer' => true]
);

Text::script('COM_J2COMMERCE_DATABASE_HEALTH_PRODUCT_DELETE');
Text::script('COM_J2COMMERCE_DATABASE_HEALTH_PRODUCT_DELETED');
Text::script('COM_J2COMMERCE_DATABASE_HEALTH_PRODUCT_DELETE_CONFIRM_BUTTON');
Text::script('COM_J2COMMERCE_DATABASE_HEALTH_PRODUCT_DELETE_WARNING');
Text::script('COM_J2COMMERCE_DATABASE_HEALTH_PRODUCTS_REMAINING');
Text::script('JGLOBAL_CONFIRM_DELETE');
Text::script('JGLOBAL_NO_MATCHING_RESULTS');
Text::script('JCANCEL');

$listOrder = $this->escape($this->state->get('list.ordering'));
$listDirn  = $this->escape($this->state->get('list.direction'));
?>

<form
    action="<?php echo Route::_('index.php?option=com_j2commerce&view=databasehealthproducts&tmpl=component'); ?>"
    method="post"
    name="adminForm"
    id="adminForm"
>
    <?php echo LayoutHelper::render('joomla.searchtools.default', ['view' => $this]); ?>

    <p class="mt-3 mb-2" id="database-health-products-count">
        <?php echo Text::sprintf('COM_J2COMMERCE_DATABASE_HEALTH_PRODUCTS_REMAINING', $this->total); ?>
    </p>

    <?php if (empty($this->items)) : ?>
        <div class="alert alert-info">
            <span class="icon-info-circle" aria-hidden="true"></span><span class="visually-hidden"><?php echo Text::_('INFO'); ?></span>
            <?php echo Text::_('JGLOBAL_NO_MATCHING_RESULTS'); ?>
        </div>
    <?php else : ?>
        <table class="table itemList" id="database-health-products-list">
            <caption class="visually-hidden">
                <?php echo Text::_('COM_J2COMMERCE_DATABASE_HEALTH_CHECK_PRODUCTS_WITHOUT_MASTER_VARIANT_LABEL'); ?>,
                <span id="orderedBy"><?php echo Text::_('JGLOBAL_SORTED_BY'); ?></span>,
                <span id="filteredBy"><?php echo Text::_('JGLOBAL_FILTERED_BY'); ?></span>
            </caption>
            <thead>
                <tr>
                    <th scope="col" class="w-10">
                        <?php echo HTMLHelper::_('searchtools.sort', 'COM_J2COMMERCE_HEADING_PRODUCT_ID', 'a.j2commerce_product_id', $listDirn, $listOrder); ?>
                    </th>
                    <th scope="col">
                        <?php echo HTMLHelper::_('searchtools.sort', 'COM_J2COMMERCE_HEADING_PRODUCT_NAME', 'product_name', $listDirn, $listOrder); ?>
                    </th>
                    <th scope="col" class="w-15 text-end">
                        <?php echo Text::_('COM_J2COMMERCE_ACTIONS'); ?>
                    </th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($this->items as $item) :
                $productName = !empty($item->product_name) ? $item->product_name : ('#' . (int) $item->j2commerce_product_id);
                ?>
                <tr data-product-id="<?php echo (int) $item->j2commerce_product_id; ?>">
                    <td><?php echo (int) $item->j2commerce_product_id; ?></td>
                    <td><?php echo $this->escape($productName); ?></td>
                    <td class="text-end">
                        <button
                            type="button"
                            class="btn btn-sm btn-outline-danger database-health-product-delete"
                            data-product-id="<?php echo (int) $item->j2commerce_product_id; ?>"
                            data-product-name="<?php echo $this->escape($productName); ?>"
                        >
                            <span class="fa-solid fa-trash" aria-hidden="true"></span>
                            <?php echo Text::_('COM_J2COMMERCE_DATABASE_HEALTH_PRODUCT_DELETE'); ?>
                        </button>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>

        <?php echo $this->pagination->getListFooter(); ?>
    <?php endif; ?>

    <dialog
        id="database-health-product-delete-dialog"
        class="p-0 border-0 rounded-3 shadow"
        aria-labelledby="database-health-product-delete-dialog-title"
        aria-describedby="database-health-product-delete-dialog-warning"
    >
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="database-health-product-delete-dialog-title" class="modal-title fs-5"><?php echo Text::_('COM_J2COMMERCE_DATABASE_HEALTH_PRODUCT_DELETE'); ?></h2>
            </div>
            <div class="modal-body">
                <p id="database-health-product-delete-dialog-warning" class="dhp-product-warning"></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary dhp-cancel"><?php echo Text::_('JCANCEL'); ?></button>
                <button type="button" class="btn btn-danger dhp-confirm-delete"><?php echo Text::_('COM_J2COMMERCE_DATABASE_HEALTH_PRODUCT_DELETE_CONFIRM_BUTTON'); ?></button>
            </div>
        </div>
    </dialog>

    <input type="hidden" name="task" value="">
    <input type="hidden" name="boxchecked" value="0">
    <?php echo HTMLHelper::_('form.token', ['id' => 'database-health-products-token-field']); ?>
</form>
