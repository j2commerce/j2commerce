/**
 * @package     J2Commerce Library
 * @subpackage  lib_j2commerce
 *
 * @copyright   (C)2024-2025 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

/**
 * Generic Multi Item Field JavaScript Handler
 *
 * This module handles the multi-item selection functionality for various field types.
 * It creates field-specific callback functions and manages the item selection UI.
 */

class ItemMultiFieldHandler {
    constructor(fieldId) {
        this.fieldId = fieldId;
        this.init();
    }

    /**
     * Initialize the field handler
     */
    init() {
        // Create the global callback function for this field
        this.createGlobalCallback();

        // Bind removal functions
        this.createGlobalFunctions();

        //console.log(`ItemMultiFieldHandler initialized for field: ${this.fieldId}`);
    }

    /**
     * Create global callback function for the modal
     */
    createGlobalCallback() {
        const fieldId = this.fieldId;
        const handler = this;

        // Create the global callback function that the modal will call
        window[`jSelectItemMultiCallback_${fieldId}`] = function(selectedItems) {
            handler.handleItemSelection(selectedItems);
        };
    }

    /**
     * Create global utility functions for this field
     */
    createGlobalFunctions() {
        const fieldId = this.fieldId;
        const handler = this;

        // Create global remove function
        window[`removeItem_${fieldId}`] = function(itemId) {
            handler.removeItem(itemId);
        };

        // Create global clear all function
        window[`clearAllItems_${fieldId}`] = function() {
            handler.clearAllItems();
        };
    }

    /**
     * Handle item selection from modal
     * @param {Array} selectedItems - Array of selected item objects
     */
    handleItemSelection(selectedItems) {
        //console.log('=== ItemMultiField Callback Started ===');
        //console.log('Field ID:', this.fieldId);
        //console.log('Selected items received:', selectedItems);

        if (selectedItems && selectedItems.length > 0) {
            const ids = selectedItems.map(item => item.id);

            //console.log('Extracted IDs:', ids);

            // Remove existing hidden item fields
            this.removeAllHiddenFields();

            // Update the main field with all item IDs
            this.updateMainField(ids);

            // Create or update the items table using custom handler
            if (this.customUpdateTable) {
                this.customUpdateTable(selectedItems);
            }

            //console.log('=== ItemMultiField Callback Completed ===');
        } else {
            //console.log('No items selected - clearing field');
            this.clearAllItems();
        }
    }

    /**
     * Update the main hidden field value
     * @param {Array} ids - Array of item IDs
     */
    updateMainField(ids) {
        const mainField = document.getElementById(`${this.fieldId}_main`);
        if (mainField) {
            mainField.value = ids.join(',');
            //console.log('Updated main field with IDs:', mainField.value);
        }
    }

    /**
     * Remove all hidden fields for this field
     */
    removeAllHiddenFields() {
        const existingHiddenFields = document.querySelectorAll(`input[id^="${this.fieldId}_hidden"]`);
        existingHiddenFields.forEach(field => field.remove());
    }


    /**
     * Remove a specific item from the selection
     * @param {number} itemId - Item ID to remove
     */
    removeItem(itemId) {
        const hiddenFields = document.querySelectorAll(`input[id^="${this.fieldId}_hidden"]`);
        let itemToRemove = null;
        const remainingItems = [];

        hiddenFields.forEach(field => {
            if (field.value === itemId.toString()) {
                itemToRemove = field;
            } else {
                remainingItems.push({
                    id: field.value,
                    title: field.getAttribute('data-title') || `Item ${field.value}`,
                    catId: field.getAttribute('data-catid') || '',
                    uri: field.getAttribute('data-uri') || ''
                });
            }
        });

        if (itemToRemove) {
            itemToRemove.remove();
        }

        // Re-index the remaining hidden fields
        this.reindexHiddenFields();

        // Update the main field with remaining item IDs
        const remainingIds = remainingItems.map(p => p.id);
        this.updateMainField(remainingIds);

        // Update the table using custom handler
        if (this.customUpdateTable) {
            this.customUpdateTable(remainingItems);
        }

        //console.log('Removed item:', itemId, 'Remaining items:', remainingItems.length);
    }

    /**
     * Clear all selected items
     */
    clearAllItems() {
        //console.log('=== Clear All Items Started ===');

        // Remove all hidden item fields
        this.removeAllHiddenFields();

        // Clear the main field
        this.updateMainField([]);

        // Clear the items table
        const tableContainer = document.getElementById(`${this.fieldId}_table`);
        if (tableContainer) {
            tableContainer.innerHTML = '';
        }

        //console.log('=== Clear All Items Completed ===');
    }

    /**
     * Re-index all hidden fields to maintain proper array indices
     */
    reindexHiddenFields() {
        const allHiddenFields = document.querySelectorAll(`input[id^="${this.fieldId}_hidden"]`);
        allHiddenFields.forEach((field, index) => {
            // Keep the original field name pattern - will be customized per field type
            const originalName = field.name;
            const namePattern = originalName.replace(/\[\d+\]$/, `[${index}]`);
            field.name = namePattern;
            field.id = `${this.fieldId}_hidden_${index}`;
        });
    }

    /**
     * Utility function to create DOM elements with properties
     * @param {string} tagName - HTML tag name
     * @param {Object} properties - Properties to set on the element
     * @returns {HTMLElement} Created element
     */
    createElement(tagName, properties = {}) {
        const element = document.createElement(tagName);

        Object.keys(properties).forEach(key => {
            if (key === 'innerHTML') {
                element.innerHTML = properties[key];
            } else {
                element[key] = properties[key];
            }
        });

        return element;
    }

    /**
     * Escape HTML to prevent XSS
     * @param {string} text - Text to escape
     * @returns {string} Escaped text
     */
    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
}

/**
 * Global registry for field handlers
 */
window.ItemMultiFieldHandlers = window.ItemMultiFieldHandlers || {};

/**
 * Initialize an ItemMultiField handler
 * @param {string} fieldId - The field ID
 */
window.initItemMultiField = function(fieldId) {
    if (!window.ItemMultiFieldHandlers[fieldId]) {
        window.ItemMultiFieldHandlers[fieldId] = new ItemMultiFieldHandler(fieldId);
    }
    return window.ItemMultiFieldHandlers[fieldId];
};

// Auto-initialize when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    // Find all item multi fields and initialize them
    const itemMultiFields = document.querySelectorAll('[data-item-multi-field]');
    itemMultiFields.forEach(field => {
        const fieldId = field.getAttribute('data-item-multi-field');
        if (fieldId) {
            window.initItemMultiField(fieldId);
        }
    });
});