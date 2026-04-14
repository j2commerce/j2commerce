<?php

/**
 * @package     J2Commerce Library
 * @subpackage  lib_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace J2Commerce\Library\J2Commerce\Field\Modal;

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
 * Library version of UserMultiField for multi-user selection.
 *
 * This field provides a modal interface for selecting multiple users from com_users.
 * It can be used by any extension that includes the J2Commerce library.
 *
 * @since  1.0.0
 */
class UserMultiSelectField extends ModalMultiSelectField
{
    /**
     * The form field type.
     *
     * @var     string
     * @since   1.0.0
     */
    protected $type = 'Modal_UserMultiselect';

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
     * @since   1.0.0
     */
    public function setup(\SimpleXMLElement $element, $value, $group = null)
    {
        // Handle comma-separated values for multiple users
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

        $languages = LanguageHelper::getContentLanguages([0, 1], false);
        $language  = (string) $this->element['language'];

        // Prepare enabled actions
        $this->canDo['propagate'] = ((string) $this->element['propagate'] == 'true') && \count($languages) > 2;

        // Prepare Urls - use the multi-select modal layout from com_users
        $linkItems = (new Uri())->setPath(Uri::base(true) . '/index.php');
        $linkItems->setQuery([
            'option'                => 'com_users',
            'view'                  => 'users',
            'layout'                => 'modal_multiselect',
            'tmpl'                  => 'component',
            'function'              => 'jSelectItemMultiCallback_' . $this->id,
            Session::getFormToken() => 1,
        ]);

        if ($language) {
            $linkItems->setVar('forcedLanguage', $language);
            $modalTitle                            = Text::_('LIB_J2COMMERCE_SELECT_USERS') . ' &#8212; ' . $this->getTitle();
            $this->dataAttributes['data-language'] = $language;
        } else {
            $modalTitle = Text::_('LIB_J2COMMERCE_USER_SELECT_MODAL_ADD_USERS');
        }

        $this->urls['select']        = (string) $linkItems;
        $this->modalTitles['select'] = $modalTitle;
        $this->hint                  = $this->hint ?: Text::_('LIB_J2COMMERCE_SELECT_USERS');

        $this->sql_title_table  = '#__users';
        $this->sql_title_column = 'name';
        $this->sql_title_key    = 'id';

        return $result;
    }

    /**
     * Method to get the field input markup.
     *
     * @return  string  The field input markup.
     *
     * @since   1.0.0
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

        // Add a container for the users table
        $html .= '<div id="' . $this->id . '_table">';

        // Build the table with the selected users on first load
        if (!empty($this->value)) {
            // Fetch user titles from the database
            $users = $this->getValueTitles();
            $html .= '<div class="my-2"><strong>' . Text::_('LIB_J2COMMERCE_SELECTED_USERS') . ' (' . \count($this->value) . '):</strong></div>';
            $html .= '<table class="table table-sm table-striped"><thead><tr><th class="w-10">' . Text::_('LIB_J2COMMERCE_USER_FIELD_ID') . '</th><th>' . Text::_('LIB_J2COMMERCE_USER_FIELD_NAME') . '</th><th class="text-end w-6"><button type="button" class="btn btn-sm btn-outline-danger" onclick="clearAllItems_' . $this->id . '()" title="' . Text::_('LIB_J2COMMERCE_USERS_CLEAR_ALL') . '"><span class="icon-trash" aria-hidden="true"></span></button></th><th class="w-1"><span class="visually-hidden">' . Text::_('LIB_J2COMMERCE_REMOVE') . '</span></th></tr></thead><tbody>';
            foreach ($this->value as $index => $userId) {
                $userName = isset($users[$userId]) ? htmlspecialchars($users[$userId]->name, ENT_QUOTES, 'UTF-8') : $userId;
                $html .= '<tr>';
                $html .= '<td class="fw-bold">' . $userId . '</td>';
                $html .= '<td>' . $userName . '</td>';
                $html .= '<td class="text-end"><button type="button" class="btn btn-sm btn-danger" onclick="removeItem_' . $this->id . '(' . $userId . ')" title="' . Text::_('LIB_J2COMMERCE_USER_CLEAR') . '"><span class="icon-trash" aria-hidden="true"></span></button></td>';
                $html .= '<td class="w-1"><input type="hidden" name="jform[request][user_ids][' . $index . ']" value="' . $userId . '" id="' . $this->id . '_hidden_' . $index . '" data-title="' . $userName . '"></td>';
                $html .= '</tr>';
            }
            $html .= '</tbody></table>';
        } else {
            $html .= '<div class="my-2">' . Text::_('LIB_J2COMMERCE_NO_USERS_SELECTED') . '</div>';
        }
        $html .= '</div>';

        return $html;
    }

    /**
     * Load the JavaScript file for this field type.
     *
     * @return void
     *
     * @since   1.0.0
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

        // Load language strings for JavaScript
        Text::script('LIB_J2COMMERCE_SELECTED_USERS');
        Text::script('LIB_J2COMMERCE_USER_FIELD_ID');
        Text::script('LIB_J2COMMERCE_USER_FIELD_NAME');
        Text::script('LIB_J2COMMERCE_USERS_CLEAR_ALL');
        Text::script('LIB_J2COMMERCE_USER_CLEAR');
        Text::script('LIB_J2COMMERCE_REMOVE');

        // Initialize this specific field instance via inline script
        $initScript = "
        document.addEventListener('DOMContentLoaded', function() {
            if (window.initItemMultiField) {
                const handler = window.initItemMultiField('{$this->id}');

                // Set up user-specific table update function
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
                            innerHTML: '<strong>' + Joomla.Text._('LIB_J2COMMERCE_SELECTED_USERS') + ' (' + selectedItems.length + '):</strong>'
                        });

                        const table = handler.createElement('table', {
                            className: 'table table-sm table-striped'
                        });

                        const thead = handler.createElement('thead');
                        const headerRow = handler.createElement('tr', {
                            innerHTML: '<th class=\"w-10\">' + Joomla.Text._('LIB_J2COMMERCE_USER_FIELD_ID') + '</th>' +
                                      '<th>' + Joomla.Text._('LIB_J2COMMERCE_USER_FIELD_NAME') + '</th>' +
                                      '<th class=\"text-end w-6\">' +
                                          '<button type=\"button\" class=\"btn btn-sm btn-outline-danger\" ' +
                                                  'onclick=\"clearAllItems_{$this->id}()\" ' +
                                                  'title=\"' + Joomla.Text._('LIB_J2COMMERCE_USERS_CLEAR_ALL') + '\">' +
                                              '<span class=\"icon-trash\" aria-hidden=\"true\"></span>' +
                                          '</button>' +
                                      '</th>' +
                                      '<th class=\"w-1\"><span class=\"visually-hidden\">' + Joomla.Text._('LIB_J2COMMERCE_REMOVE') + '</span></th>'
                        });

                        thead.appendChild(headerRow);
                        table.appendChild(thead);

                        const tbody = handler.createElement('tbody');

                        selectedItems.forEach((item, index) => {
                            const row = handler.createElement('tr');

                            // Create hidden field
                            const hiddenField = handler.createElement('input', {
                                type: 'hidden',
                                name: 'jform[request][user_ids][' + index + ']',
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
                                                      'title=\"' + Joomla.Text._('LIB_J2COMMERCE_USER_CLEAR') + '\">' +
                                                  '<span class=\"icon-trash\" aria-hidden=\"true\"></span>' +
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
     * @since 1.0.0
     */
    protected function getLayoutData()
    {
        $data                          = parent::getLayoutData();
        $data['language']              = (string) $this->element['language'];
        $data['multiple']              = true;
        $data['buttonIcons']['select'] = 'fa-solid fa-users';

        return $data;
    }

}
