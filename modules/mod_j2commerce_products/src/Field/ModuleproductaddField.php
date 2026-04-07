<?php

/**
 * @package     J2Commerce
 * @subpackage  mod_j2commerce_products
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace J2Commerce\Module\Products\Site\Field;

\defined('_JEXEC') or die;

use J2Commerce\Component\J2commerce\Administrator\Field\Modal\ProductMultiSelectField;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;

class ModuleproductaddField extends ProductMultiSelectField
{
    protected $type = 'Moduleproductadd';

    protected function getInput()
    {
        $this->loadJavaScript();

        $html = $this->getParentModalInput();

        $html .= '<div id="' . $this->id . '_table">';

        if (!empty($this->value)) {
            $products = $this->getValueTitles();
            $html .= $this->renderProductTable($products);
        } else {
            $html .= '<div class="my-2">' . Text::_('COM_J2COMMERCE_NO_PRODUCTS_SELECTED') . '</div>';
        }

        $html .= '</div>';

        return $html;
    }

    private function getParentModalInput(): string
    {
        if (empty($this->layout)) {
            throw new \UnexpectedValueException(\sprintf('%s has no layout assigned.', $this->name));
        }

        if (method_exists('\Joomla\CMS\Form\FormField', 'collectLayoutData')) {
            $data = $this->collectLayoutData();
        } else {
            $data = $this->getLayoutData();
        }

        return $this->getRenderer($this->layout)->render($data);
    }

    private function renderProductTable(array $products): string
    {
        $app = Factory::getApplication();
        $wa  = $app->getDocument()->getWebAssetManager();
        $wa->registerAndUseStyle('com_j2commerce.editview', 'media/com_j2commerce/css/administrator/editview.css');
        $fieldName = htmlspecialchars($this->name, ENT_QUOTES, 'UTF-8');
        $count     = \count($this->value);

        $html = '<div class="j2commerce-product-edit-form pt-3"><div class="my-2 d-flex align-items-center"><span class="badge text-bg-info me-2 px-2">'.$count.'</span><strong>' . Text::_('COM_J2COMMERCE_SELECTED_PRODUCTS') . '</strong></div>';
        $html .= '<table class="table table-sm"><thead><tr>'
            . '<th scope="col" class="w-10">' . Text::_('COM_J2COMMERCE_PRODUCT_FIELD_ID') . '</th>'
            . '<th scope="col">' . Text::_('COM_J2COMMERCE_PRODUCT_FIELD_NAME') . '</th>'
            . '<th scope="col" class="text-end w-6"><button type="button" class="btn btn-sm btn-soft-danger" onclick="clearAllItems_' . $this->id . '()" title="' . Text::_('COM_J2COMMERCE_PRODUCTS_CLEAR_ALL') . '"><span class="icon-trash" aria-hidden="true"></span></button></th>'
            . '<th class="w-1"><span class="visually-hidden">' . Text::_('COM_J2COMMERCE_REMOVE') . '</span></th>'
            . '</tr></thead><tbody>';

        foreach ($this->value as $index => $productId) {
            $productName = isset($products[$productId])
                ? htmlspecialchars($products[$productId]->title, ENT_QUOTES, 'UTF-8')
                : (string) $productId;

            $html .= '<tr>'
                . '<td>' . (int) $productId . '</td>'
                . '<td>' . $productName . '</td>'
                . '<td class="text-end"><button type="button" class="btn btn-sm btn-soft-danger" onclick="removeItem_' . $this->id . '(' . (int) $productId . ')" title="' . Text::_('COM_J2COMMERCE_PRODUCT_CLEAR') . '"><span class="icon-trash" aria-hidden="true"></span></button></td>'
                . '<td class="w-1"><input type="hidden" name="' . $fieldName . '[' . $index . ']" value="' . (int) $productId . '" id="' . $this->id . '_hidden_' . $index . '" data-title="' . $productName . '"></td>'
                . '</tr>';
        }

        $html .= '</tbody></table></div>';

        return $html;
    }

    protected function loadJavaScript()
    {
        $wa = Factory::getApplication()->getDocument()->getWebAssetManager();
        $wa->registerAndUseScript(
            'lib_j2commerce.modal-multiselect-field',
            'lib_j2commerce/modal-multiselect-field.min.js',
            [],
            ['type' => 'module'],
            ['core']
        );

        Text::script('COM_J2COMMERCE_SELECTED_PRODUCTS');
        Text::script('COM_J2COMMERCE_PRODUCT_FIELD_ID');
        Text::script('COM_J2COMMERCE_PRODUCT_FIELD_NAME');
        Text::script('COM_J2COMMERCE_PRODUCTS_CLEAR_ALL');
        Text::script('COM_J2COMMERCE_PRODUCT_CLEAR');
        Text::script('COM_J2COMMERCE_REMOVE');

        $fieldName = addslashes(htmlspecialchars($this->name, ENT_QUOTES, 'UTF-8'));

        $initScript = <<<JS
        document.addEventListener('DOMContentLoaded', function() {
            if (window.initItemMultiField) {
                const handler = window.initItemMultiField('{$this->id}');

                handler.customUpdateTable = function(selectedItems) {
                    const tableContainer = document.getElementById('{$this->id}_table');
                    if (!tableContainer) return;

                    tableContainer.innerHTML = '';

                    if (selectedItems && selectedItems.length > 0) {
                        const caption = handler.createElement('div', {
                            className: 'my-2',
                            innerHTML: '<strong>' + Joomla.Text._('COM_J2COMMERCE_SELECTED_PRODUCTS') + ' (' + selectedItems.length + '):</strong>'
                        });

                        const table = handler.createElement('table', {
                            className: 'table'
                        });

                        const thead = handler.createElement('thead');
                        const headerRow = handler.createElement('tr', {
                            innerHTML: '<th class="w-10">' + Joomla.Text._('COM_J2COMMERCE_PRODUCT_FIELD_ID') + '</th>' +
                                      '<th>' + Joomla.Text._('COM_J2COMMERCE_PRODUCT_FIELD_NAME') + '</th>' +
                                      '<th class="text-end w-6">' +
                                          '<button type="button" class="btn btn-sm btn-outline-danger" ' +
                                                  'onclick="clearAllItems_{$this->id}()" ' +
                                                  'title="' + Joomla.Text._('COM_J2COMMERCE_PRODUCTS_CLEAR_ALL') + '">' +
                                              '<i class="icon-trash" aria-hidden="true"></i>' +
                                          '</button>' +
                                      '</th>' +
                                      '<th class="w-1"><span class="visually-hidden">' + Joomla.Text._('COM_J2COMMERCE_REMOVE') + '</span></th>'
                        });

                        thead.appendChild(headerRow);
                        table.appendChild(thead);

                        const tbody = handler.createElement('tbody');

                        selectedItems.forEach((item, index) => {
                            const row = handler.createElement('tr');

                            const hiddenField = handler.createElement('input', {
                                type: 'hidden',
                                name: '{$fieldName}[' + index + ']',
                                value: item.id,
                                id: '{$this->id}_hidden_' + index
                            });

                            hiddenField.setAttribute('data-title', item.title);

                            row.innerHTML = '<td class="fw-bold">' + item.id + '</td>' +
                                          '<td>' + handler.escapeHtml(item.title) + '</td>' +
                                          '<td class="text-end">' +
                                              '<button type="button" class="btn btn-sm btn-soft-danger" ' +
                                                      'onclick="removeItem_{$this->id}(' + item.id + ')" ' +
                                                      'title="' + Joomla.Text._('COM_J2COMMERCE_PRODUCT_CLEAR') + '">' +
                                                  '<span class="icon-trash" aria-hidden="true"></span>' +
                                              '</button>' +
                                          '</td>';

                            const hiddenCell = handler.createElement('td');
                            hiddenCell.appendChild(hiddenField);
                            row.appendChild(hiddenCell);

                            tbody.appendChild(row);
                        });

                        table.appendChild(tbody);
                        tableContainer.appendChild(caption);
                        tableContainer.appendChild(table);
                    }
                };
            }
        });
JS;

        $wa->addInlineScript($initScript, ['type' => 'text/javascript']);
    }
}
