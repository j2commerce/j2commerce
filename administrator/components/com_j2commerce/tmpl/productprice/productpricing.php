<?php
/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use J2Commerce\Component\J2commerce\Administrator\Helper\CurrencyHelper;
use J2Commerce\Component\J2commerce\Administrator\Helper\J2CommerceHelper;
use J2Commerce\Component\J2commerce\Administrator\Helper\UtilitiesHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

/** @var \J2Commerce\Component\J2commerce\Administrator\View\Productprice\HtmlView $this */

$this->prefix = 'jform[prices]';
$row_class = 'row';
$col_class = 'col-md-';
$app = Factory::getApplication();
$input = $app->getInput();
$currencySymbol   = (new CurrencyHelper())->getSymbol();
$currencyDecimals = CurrencyHelper::getDecimalPlace();

// Initialize tooltips
HTMLHelper::_('bootstrap.tooltip', '[data-bs-toggle="tooltip"]', ['placement' => 'top']);

// Get Web Asset Manager
$wa = $app->getDocument()->getWebAssetManager();
$wa->useScript('form.validate')
    ->useScript('keepalive');

// Add custom styles
$style = <<<CSS
.product-pricing .control-group .controls { min-width: 110px; }
.product-pricing .input-group .control-group { margin-bottom: 0 !important; }
.product-pricing #jform_quantity_from { min-width: 115px; width: 115px;border-top-right-radius:0;border-bottom-right-radius:0; }
.product-pricing .calendar-field { min-width: 200px; }
.j2commerce-loading { cursor: wait; }
.j2commerce-loading * { pointer-events: none; }
CSS;
$wa->addInlineStyle($style);

// JavaScript for AJAX operations
$variantId = (int) $this->variant_id;
$script = <<<JS
document.addEventListener('DOMContentLoaded', function() {
    const J2CommercePricing = {
        token: Joomla.getOptions('csrf.token'),
        variantId: {$variantId},

        init: function() {
            this.bindCreateButton();
            this.bindSaveAllButton();
            this.bindRemoveButtons();
        },

        bindCreateButton: function() {
            const createBtn = document.getElementById('btn-create-price');
            if (createBtn) {
                createBtn.addEventListener('click', (e) => {
                    e.preventDefault();
                    this.createPrice();
                });
            }
        },

        bindSaveAllButton: function() {
            const saveBtn = document.getElementById('btn-save-all-prices');
            if (saveBtn) {
                saveBtn.addEventListener('click', (e) => {
                    e.preventDefault();
                    this.saveAllPrices();
                });
            }
        },

        bindRemoveButtons: function() {
            document.querySelectorAll('.btn-remove-price').forEach(btn => {
                btn.addEventListener('click', (e) => {
                    e.preventDefault();
                    const priceId = btn.dataset.priceId;
                    if (confirm(Joomla.Text._('COM_J2COMMERCE_CONFIRM_DELETE') || 'Are you sure?')) {
                        this.removePrice(priceId);
                    }
                });
            });
        },

        createPrice: function() {
            const form = document.getElementById('product-form');
            const formData = new FormData(form);
            formData.append('task', 'productprice.createprice');
            formData.append(this.token, 1);

            this.showLoading();

            fetch('index.php?option=com_j2commerce&format=json', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                this.hideLoading();
                if (data.success) {
                    Joomla.renderMessages({success: [data.message || 'Price created successfully']});
                    location.reload();
                } else {
                    Joomla.renderMessages({error: [data.message || 'Error creating price']});
                }
            })
            .catch(error => {
                this.hideLoading();
                Joomla.renderMessages({error: ['Request failed: ' + error.message]});
            });
        },

        saveAllPrices: function() {
            const form = document.getElementById('product-form');
            const formData = new FormData(form);
            formData.append('task', 'productprice.saveprices');
            formData.append(this.token, 1);

            this.showLoading();

            fetch('index.php?option=com_j2commerce&format=json', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                this.hideLoading();
                if (data.success) {
                    Joomla.renderMessages({success: [data.message || 'Prices saved successfully']});
                } else {
                    Joomla.renderMessages({error: [data.message || 'Error saving prices']});
                }
            })
            .catch(error => {
                this.hideLoading();
                Joomla.renderMessages({error: ['Request failed: ' + error.message]});
            });
        },

        removePrice: function(priceId) {
            const formData = new FormData();
            formData.append('task', 'productprice.removeprice');
            formData.append('productprice_id', priceId);
            formData.append('variant_id', this.variantId);
            formData.append(this.token, 1);

            this.showLoading();

            fetch('index.php?option=com_j2commerce&format=json', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                this.hideLoading();
                if (data.success) {
                    const row = document.getElementById('productprice-row-' + priceId);
                    if (row) {
                        row.style.transition = 'opacity 0.3s';
                        row.style.opacity = '0';
                        setTimeout(() => row.remove(), 300);
                    }
                    Joomla.renderMessages({success: [data.message || 'Price removed successfully']});
                } else {
                    Joomla.renderMessages({error: [data.message || 'Error removing price']});
                }
            })
            .catch(error => {
                this.hideLoading();
                Joomla.renderMessages({error: ['Request failed: ' + error.message]});
            });
        },

        showLoading: function() {
            document.body.classList.add('j2commerce-loading');
        },

        hideLoading: function() {
            document.body.classList.remove('j2commerce-loading');
        }
    };

    J2CommercePricing.init();
});
JS;
$wa->addInlineScript($script);
?>
<div class="j2commerce product-pricing px-lg-4">
    <?php if (isset($this->variant_id) && $this->variant_id > 0): ?>
    <form action="<?php echo Route::_('index.php?option=com_j2commerce&layout=productpricing&variant_id=' . (int) $this->variant_id); ?>"
          method="post"
          name="adminForm"
          id="product-form"
          class="form-validate">

        <?php echo $this->form->renderField('j2commerce_productprice_id'); ?>
        <?php echo $this->form->renderField('variant_id'); ?>
        <input type="hidden" name="task" id="task" value="" />
        <?php echo HTMLHelper::_('form.token'); ?>

        <!-- Add New Price Section -->
        <?php
        // Ensure date fields are empty for new price creation
        $this->form->setValue('date_from', null, '');
        $this->form->setValue('date_to', null, '');
        $this->form->setValue('quantity_from', null, '');
        $this->form->setValue('customer_group_id', null, 1);
        $this->form->setValue('price', null, number_format(0, $currencyDecimals, '.', ''));
        ?>
        <div class="note <?php echo $row_class; ?> mb-3">
            <fieldset class="options-form">
                <legend><?php echo Text::_('COM_J2COMMERCE_PRODUCT_ADD_PRICING'); ?></legend>
                <table class="adminlist table itemList">
                    <thead>
                    <tr>
                        <th scope="col" class="w-40">
                            <?php echo Text::_('COM_J2COMMERCE_PRODUCT_PRICE_DATE_RANGE'); ?>
                            <span class="fas fa-solid fa-exclamation-circle ms-1"
                                  data-bs-toggle="tooltip"
                                  title="<?php echo Text::_('COM_J2COMMERCE_OPTIONAL'); ?>"></span>
                        </th>
                        <th scope="col" class="w-15">
                            <?php echo Text::_('COM_J2COMMERCE_PRODUCT_PRICE_QUANTITY_RANGE'); ?>
                            <span class="fas fa-solid fa-exclamation-circle ms-1"
                                  data-bs-toggle="tooltip"
                                  title="<?php echo Text::_('COM_J2COMMERCE_OPTIONAL'); ?>"></span>
                        </th>
                        <th scope="col" class="w-20"><?php echo Text::_('COM_J2COMMERCE_PRODUCT_PRICE_GROUP_RANGE'); ?></th>
                        <th scope="col" class="w-15"><?php echo Text::_('COM_J2COMMERCE_PRODUCT_PRICE_VALUE'); ?></th>
                        <th scope="col" class="w-10"></th>
                    </tr>
                    </thead>
                    <tbody>
                    <tr>
                        <td>
                            <div class="input-group">
                                <?php echo HTMLHelper::_('calendar', '', 'jform[date_from]', 'jform_date_from', '%d-%m-%Y %H:%M:%S', [
                                    'class' => 'form-control calendar-field',
                                    'showTime' => true
                                ]); ?>
                                <span class="input-group-text mx-2"><?php echo Text::_('COM_J2COMMERCE_TO'); ?></span>
                                <?php echo HTMLHelper::_('calendar', '', 'jform[date_to]', 'jform_date_to', '%d-%m-%Y %H:%M:%S', [
                                    'class' => 'form-control calendar-field',
                                    'showTime' => true
                                ]); ?>
                            </div>
                        </td>
                        <td>
                            <div class="input-group">
                                <?php echo $this->form->renderField('quantity_from'); ?>
                                <span class="input-group-text"><?php echo Text::_('COM_J2COMMERCE_QUANTITY_AND_ABOVE'); ?></span>
                            </div>
                        </td>
                        <td>
                            <?php echo $this->form->renderField('customer_group_id'); ?>
                        </td>
                        <td>
                            <div class="input-group">
                                <span class="input-group-text"><?php echo $currencySymbol; ?></span>
                                <?php echo $this->form->getField('price')->input; ?>
                            </div>
                        </td>
                        <td class="text-end">
                            <button type="button"
                                    class="btn btn-success"
                                    id="btn-create-price">
                                <?php echo Text::_('COM_J2COMMERCE_PRODUCT_CREATE_PRICE'); ?>
                            </button>
                        </td>
                    </tr>
                    </tbody>
                </table>
            </fieldset>
        </div>

        <!-- Current Prices Section -->
        <div class="note_green <?php echo $row_class; ?>">
            <fieldset class="options-form">
                <legend><?php echo Text::_('COM_J2COMMERCE_PRODUCT_CURRENT_PRICES'); ?></legend>
                <div class="text-start mb-3">
                    <button type="button"
                            class="btn btn-success btn-sm"
                            id="btn-save-all-prices">
                        <?php echo Text::_('COM_J2COMMERCE_PRODUCT_SAVE_ALL_PRICES'); ?>
                    </button>
                </div>
                <table class="table itemList">
                    <thead>
                    <tr>
                        <th scope="col" class="w-40">
                            <?php echo Text::_('COM_J2COMMERCE_PRODUCT_PRICE_DATE_RANGE'); ?>
                            <span class="fas fa-solid fa-exclamation-circle ms-1"
                                  data-bs-toggle="tooltip"
                                  title="<?php echo Text::_('COM_J2COMMERCE_OPTIONAL'); ?>"></span>
                        </th>
                        <th scope="col" class="w-20">
                            <?php echo Text::_('COM_J2COMMERCE_PRODUCT_PRICE_QUANTITY_RANGE'); ?>
                            <span class="fas fa-solid fa-exclamation-circle ms-1"
                                  data-bs-toggle="tooltip"
                                  title="<?php echo Text::_('COM_J2COMMERCE_OPTIONAL'); ?>"></span>
                        </th>
                        <th scope="col" class="w-10"><?php echo Text::_('COM_J2COMMERCE_PRODUCT_PRICE_GROUP_RANGE'); ?></th>
                        <th scope="col" class="w-20"><?php echo Text::_('COM_J2COMMERCE_PRODUCT_PRICE_VALUE'); ?></th>
                        <th scope="col" class="w-10"></th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if (isset($this->prices) && !empty($this->prices)):
                        foreach ($this->prices as $key => $pricing):
                            $priceId = $pricing->j2commerce_productprice_id;
                            $fieldPrefix = $this->prefix . "[{$priceId}]";
                    ?>
                        <tr id="productprice-row-<?php echo $priceId; ?>">
                            <td>
                                <div class="input-group">
                                    <?php
                                    $dateFrom = !empty($pricing->date_from) && $pricing->date_from !== '0000-00-00 00:00:00'
                                        ? UtilitiesHelper::convertUtcToCurrent($pricing->date_from)
                                        : '';
                                    echo HTMLHelper::_('calendar', $dateFrom, "{$fieldPrefix}[date_from]", "price_date_from_{$key}", '%d-%m-%Y %H:%M:%S', [
                                        'class' => 'form-control calendar-field',
                                        'showTime' => true
                                    ]);
                                    ?>
                                    <span class="input-group-text mx-2"><?php echo Text::_('COM_J2COMMERCE_TO'); ?></span>
                                    <?php
                                    $dateTo = !empty($pricing->date_to) && $pricing->date_to !== '0000-00-00 00:00:00'
                                        ? UtilitiesHelper::convertUtcToCurrent($pricing->date_to)
                                        : '';
                                    echo HTMLHelper::_('calendar', $dateTo, "{$fieldPrefix}[date_to]", "price_date_to_{$key}", '%d-%m-%Y %H:%M:%S', [
                                        'class' => 'form-control calendar-field',
                                        'showTime' => true
                                    ]);
                                    ?>
                                </div>
                            </td>
                            <td>
                                <div class="input-group">
                                    <input type="text"
                                           name="<?php echo $fieldPrefix; ?>[quantity_from]"
                                           value="<?php echo (int) $pricing->quantity_from; ?>"
                                           class="form-control"
                                           style="width: 80px;" />
                                    <span class="input-group-text"><?php echo Text::_('COM_J2COMMERCE_QUANTITY_AND_ABOVE'); ?></span>
                                </div>
                            </td>
                            <td>
                                <?php
                                // Build customer group dropdown for existing prices
                                $groupOptions = [];
                                foreach ($this->groups as $group) {
                                    $groupOptions[] = HTMLHelper::_('select.option', $group->id, $group->title);
                                }
                                echo HTMLHelper::_('select.genericlist', $groupOptions, "{$fieldPrefix}[customer_group_id]", [
                                    'class' => 'form-select'
                                ], 'value', 'text', (int) $pricing->customer_group_id);
                                ?>
                            </td>
                            <td>
                                <div class="input-group">
                                    <span class="input-group-text"><?php echo $currencySymbol; ?></span>
                                    <input type="text"
                                           name="<?php echo $fieldPrefix; ?>[price]"
                                           value="<?php echo number_format((float) $pricing->price, $currencyDecimals, '.', ''); ?>"
                                           class="form-control" />
                                </div>
                                <input type="hidden"
                                       name="<?php echo $fieldPrefix; ?>[j2commerce_productprice_id]"
                                       value="<?php echo $priceId; ?>" />
                                <input type="hidden"
                                       name="<?php echo $fieldPrefix; ?>[variant_id]"
                                       value="<?php echo (int) $pricing->variant_id; ?>" />
                            </td>
                            <td class="text-end">
                                <button type="button"
                                        class="btn btn-danger btn-remove-price"
                                        data-price-id="<?php echo $priceId; ?>">
                                    <?php echo Text::_('COM_J2COMMERCE_REMOVE'); ?>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" class="text-center text-muted">
                                <?php echo Text::_('COM_J2COMMERCE_NO_PRICES_FOUND'); ?>
                            </td>
                        </tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </fieldset>
        </div>
    </form>
    <?php else: ?>
        <div class="alert alert-warning">
            <?php echo Text::_('COM_J2COMMERCE_NO_VARIANT_FOUND'); ?>
        </div>
    <?php endif; ?>
</div>
