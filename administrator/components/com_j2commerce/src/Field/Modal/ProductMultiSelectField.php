<?php

/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace J2Commerce\Component\J2commerce\Administrator\Field\Modal;

use J2Commerce\Library\J2Commerce\Field\ModalMultiSelectField;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\LanguageHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Session\Session;
use Joomla\CMS\Uri\Uri;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Supports a modal product picker for multiple products.
 *
 * @since  6.0.0
 */
class ProductMultiSelectField extends ModalMultiSelectField
{
    /**
     * The form field type.
     *
     * @var     string
     * @since   6.0.0
     */
    protected $type = 'Modal_ProductMultiselect';

    /**
     * Method to attach a Form object to the field.
     *
     * @param   \SimpleXMLElement  $element  The SimpleXMLElement object representing the `<field>` tag for the form field object.
     * @param   mixed              $value    The form field value to validate.
     * @param   string             $group    The field name group control value.
     *
     * @return  boolean  True on success.
     *
     * @see     FormField::setup()
     * @since   6.0.0
     */
    public function setup(\SimpleXMLElement $element, $value, $group = null)
    {
        // Handle comma-separated values for multiple products
        if ($value && \is_string($value) && strpos($value, ',') !== false) {
            $values = explode(',', $value);
            $value  = array_map('intval', array_filter($values));
        } elseif ($value && !\is_array($value)) {
            $value = [(int) $value];
        }

        $result = parent::setup($element, $value, $group);

        if (!$result) {
            return $result;
        }

        // Load J2Commerce language
        Factory::getApplication()->getLanguage()->load('com_j2commerce', JPATH_ADMINISTRATOR);

        $languages = LanguageHelper::getContentLanguages([0, 1], false);
        $language  = (string) $this->element['language'];

        // Prepare enabled actions
        $this->canDo['propagate'] = ((string) $this->element['propagate'] == 'true') && \count($languages) > 2;

        // Prepare Urls - use the multi-select modal layout
        $linkItems = (new Uri())->setPath(Uri::base(true) . '/index.php');
        $linkItems->setQuery([
            'option'                => 'com_j2commerce',
            'view'                  => 'products',
            'layout'                => 'modal_multiselect',
            'tmpl'                  => 'component',
            'function'              => 'jSelectItemMultiCallback_' . $this->id,
            Session::getFormToken() => 1,
        ]);

        if ($language) {
            $linkItems->setVar('forcedLanguage', $language);
            $modalTitle                            = Text::_('COM_J2COMMERCE_SELECT_PRODUCTS') . ' &#8212; ' . $this->getTitle();
            $this->dataAttributes['data-language'] = $language;
        } else {
            $modalTitle = Text::_('COM_J2COMMERCE_PRODUCT_SELECT_MODAL_ADD_PRODUCTS');
        }

        $this->urls['select']        = (string) $linkItems;
        $this->modalTitles['select'] = $modalTitle;
        $this->hint                  = $this->hint ?: Text::_('COM_J2COMMERCE_SELECT_PRODUCTS');

        return $result;
    }

    /**
     * Method to get the field input markup.
     *
     * @return  string  The field input markup.
     *
     * @since   6.0.0
     */
    protected function getInput()
    {
        // Load the JavaScript file
        $this->loadJavaScript();

        // Convert array values to comma-separated string for the hidden input
        $inputValue = $this->value;
        if (empty($this->value)) {
            $inputValue = [];
        }

        // Get the parent input HTML
        $html = parent::getInput();

        // Add a container for the products table
        $html .= '<div id="' . $this->id . '_table">';

        // Build the table with the selected products on first load
        if (!empty($this->value)) {
            // Fetch product titles from the database
            $products = $this->getValueTitles();
            $html .= '<div class="my-2"><strong>' . Text::_('COM_J2COMMERCE_SELECTED_PRODUCTS') . ' (' . \count($this->value) . '):</strong></div>';
            $html .= '<table class="table table-sm table-striped"><thead><tr><th class="w-10">' . Text::_('COM_J2COMMERCE_PRODUCT_FIELD_ID') . '</th><th>' . Text::_('COM_J2COMMERCE_PRODUCT_FIELD_NAME') . '</th><th class="text-end w-6"><button type="button" class="btn btn-sm btn-outline-danger" onclick="clearAllItems_' . $this->id . '()" title="' . Text::_('COM_J2COMMERCE_PRODUCTS_CLEAR_ALL') . '"><i class="icon-trash" aria-hidden="true"></i></button></th><th class="w-1"><span class="visually-hidden">' . Text::_('COM_J2COMMERCE_REMOVE') . '</span></th></tr></thead><tbody>';
            foreach ($this->value as $index => $productId) {
                $productName = isset($products[$productId]) ? htmlspecialchars($products[$productId]->title, ENT_QUOTES, 'UTF-8') : $productId;
                $html .= '<tr>';
                $html .= '<td class="fw-bold">' . $productId . '</td>';
                $html .= '<td>' . $productName . '</td>';
                $html .= '<td class="text-end"><button type="button" class="btn btn-sm btn-danger" onclick="removeItem_' . $this->id . '(' . $productId . ')" title="' . Text::_('COM_J2COMMERCE_PRODUCT_CLEAR') . '"><i class="icon-trash" aria-hidden="true"></i></button></td>';
                $html .= '<td class="w-1"><input type="hidden" name="jform[request][product_ids][' . $index . ']" value="' . $productId . '" id="' . $this->id . '_hidden_' . $index . '" data-title="' . $productName . '"></td>';
                $html .= '</tr>';
            }
            $html .= '</tbody></table>';
        } else {
            $html .= '<div class="my-2">' . Text::_('COM_J2COMMERCE_NO_PRODUCTS_SELECTED') . '</div>';
        }
        $html .= '</div>';

        return $html;
    }

    /**
     * Load the JavaScript file for this field type.
     *
     * @return void
     *
     * @since   6.0.0
     */
    protected function loadJavaScript()
    {
        /** @var \Joomla\CMS\WebAsset\WebAssetManager $wa */
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

        // Initialize this specific field instance via inline script
        $initScript = "
        document.addEventListener('DOMContentLoaded', function() {
            if (window.initItemMultiField) {
                const handler = window.initItemMultiField('{$this->id}');

                // Set up product-specific table update function
                handler.customUpdateTable = function(selectedItems) {
                    const tableContainer = document.getElementById('{$this->id}_table');
                    if (!tableContainer) {
                        console.warn('Table container not found: {$this->id}_table');
                        return;
                    }

                    // Clear previous content
                    tableContainer.innerHTML = '';

                    if (selectedItems && selectedItems.length > 0) {
                        // Create table structure
                        const caption = handler.createElement('div', {
                            className: 'my-2',
                            innerHTML: '<strong>' + Joomla.Text._('COM_J2COMMERCE_SELECTED_PRODUCTS') + ' (' + selectedItems.length + '):</strong>'
                        });

                        const table = handler.createElement('table', {
                            className: 'table table-sm table-striped'
                        });

                        const thead = handler.createElement('thead');
                        const headerRow = handler.createElement('tr', {
                            innerHTML: '<th class=\"w-10\">' + Joomla.Text._('COM_J2COMMERCE_PRODUCT_FIELD_ID') + '</th>' +
                                      '<th>' + Joomla.Text._('COM_J2COMMERCE_PRODUCT_FIELD_NAME') + '</th>' +
                                      '<th class=\"text-end w-6\">' +
                                          '<button type=\"button\" class=\"btn btn-sm btn-outline-danger\" ' +
                                                  'onclick=\"clearAllItems_{$this->id}()\" ' +
                                                  'title=\"' + Joomla.Text._('COM_J2COMMERCE_PRODUCTS_CLEAR_ALL') + '\">' +
                                              '<i class=\"icon-trash\" aria-hidden=\"true\"></i>' +
                                          '</button>' +
                                      '</th>' +
                                      '<th class=\"w-1\"><span class=\"visually-hidden\">' + Joomla.Text._('COM_J2COMMERCE_REMOVE') + '</span></th>'
                        });

                        thead.appendChild(headerRow);
                        table.appendChild(thead);

                        const tbody = handler.createElement('tbody');

                        selectedItems.forEach((item, index) => {
                            const row = handler.createElement('tr');

                            // Create hidden field
                            const hiddenField = handler.createElement('input', {
                                type: 'hidden',
                                name: 'jform[request][product_ids][' + index + ']',
                                value: item.id,
                                id: '{$this->id}_hidden_' + index
                            });

                            hiddenField.setAttribute('data-title', item.title);
                            hiddenField.setAttribute('class', 'w-1');

                            // Create row cells
                            row.innerHTML = '<td class=\"fw-bold\">' + item.id + '</td>' +
                                          '<td>' + handler.escapeHtml(item.title) + '</td>' +
                                          '<td class=\"text-end\">' +
                                              '<button type=\"button\" class=\"btn btn-sm btn-danger\" ' +
                                                      'onclick=\"removeItem_{$this->id}(' + item.id + ')\" ' +
                                                      'title=\"' + Joomla.Text._('COM_J2COMMERCE_PRODUCT_CLEAR') + '\">' +
                                                  '<i class=\"icon-trash\" aria-hidden=\"true\"></i>' +
                                              '</button>' +
                                          '</td>';

                            // Add hidden field as last cell
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
        });";

        $wa->addInlineScript($initScript, ['type' => 'text/javascript']);
    }

    /**
     * Method to get the data to be passed to the layout for rendering.
     *
     * @return  array
     *
     * @since 6.0.0
     */
    protected function getLayoutData()
    {
        $data                          = parent::getLayoutData();
        $data['language']              = (string) $this->element['language'];
        $data['multiple']              = true;
        $data['buttonIcons']['select'] = 'fa-solid fa-tags';

        return $data;
    }

    /**
     * Get titles for the selected product IDs
     *
     * @return  array
     *
     * @since   6.0.0
     */
    protected function getValueTitles()
    {
        if (empty($this->value)) {
            return [];
        }

        $db    = $this->getDatabase();
        $query = $db->getQuery(true);

        // Join J2Commerce products with content table to get product titles
        $query->select('p.j2commerce_product_id, c.title')
            ->from($db->quoteName('#__j2commerce_products', 'p'))
            ->join('INNER', $db->quoteName('#__content', 'c') . ' ON p.product_source_id = c.id')
            ->where('p.j2commerce_product_id IN (' . implode(',', array_map('intval', $this->value)) . ')');

        $db->setQuery($query);

        try {
            $items = $db->loadObjectList('j2commerce_product_id');
            return $items ?: [];
        } catch (\Exception $e) {
            return [];
        }
    }
}
