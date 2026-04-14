/**
 * @package     J2Commerce Library
 * @subpackage  lib_j2commerce
 *
 * @copyright   (C)2024-2025 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

/**
 * A helper to Post a Message (integrated from modal-content-select.js)
 * @param {Object} data
 */
const send = data => {
    // Set the message type and send it
    data.messageType = data.messageType || 'joomla:content-select';
    window.parent.postMessage(data);
};

// Bind the data-content-select buttons (from modal-content-select.js)
document.addEventListener('click', event => {
    const button = event.target.closest('[data-content-select]');
    if (!button) return;
    event.preventDefault();

    // Extract the data and send
    const data = Object.assign({}, button.dataset);
    delete data.contentSelect;
    send(data);
});

// Check for "select on load" (from modal-content-select.js)
window.addEventListener('load', () => {
    const data = Joomla.getOptions('content-select-on-load');
    if (data) {
        send(data);
    }
});

document.addEventListener("DOMContentLoaded", function() {
    var selectedItems = [];
    var accumulatedItems = [];
    var preSelectedIds = [];

    // Simple function to get field ID from URL
    function getFieldIdFromUrl() {
        var urlParams = new URLSearchParams(window.location.search);
        var functionName = urlParams.get("function") || "jSelectItemMulti";
        var match = functionName.match(/jSelectItemMultiCallback_(.+)$/);
        return match ? match[1] : null;
    }

    // Initialize sessionStorage for persistence across pages
    var fieldId = getFieldIdFromUrl();
    var storageKey = "accumulatedItems_" + (fieldId || "default");

    // Restore accumulated items from sessionStorage
    var storedItems = sessionStorage.getItem(storageKey);
    if (storedItems) {
        try {
            accumulatedItems = JSON.parse(storedItems);
            //console.log("Restored accumulated items:", accumulatedItems.length);
        } catch (e) {
            console.error("Error parsing stored items:", e);
            accumulatedItems = [];
        }
    }

    // Initialize pre-selected items
    function initializePreSelected() {
        var fieldId = getFieldIdFromUrl();
        var existingIds = [];
        var existingTitles = {};

        if (window.parent && fieldId) {
            var hiddenFields = window.parent.document.querySelectorAll("input[id^=\"" + fieldId + "_hidden\"]");
            hiddenFields.forEach(function(field) {
                if (field.value) {
                    existingIds.push(field.value);
                    // Try to get the title from the data-title attribute
                    var title = field.getAttribute("data-title");
                    if (title) {
                        existingTitles[field.value] = title;
                    }
                }
            });
        }

        //console.log("Found existing IDs:", existingIds);
        //console.log("Found existing titles:", existingTitles);

        if (existingIds.length > 0) {
            preSelectedIds = existingIds.slice();
        }

        // First, check all items on current page and build proper item data
        var currentPageItems = {};
        document.querySelectorAll(".item-checkbox").forEach(function(checkbox) {
            currentPageItems[checkbox.dataset.id] = {
                id: checkbox.dataset.id,
                title: checkbox.dataset.title
            };
        });

        // Now merge pre-selected items with accumulated items, using proper titles when available
        if (preSelectedIds.length > 0) {
            preSelectedIds.forEach(function(itemId) {
                var exists = accumulatedItems.find(function(p) { return p.id === itemId; });
                if (!exists) {
                    var itemData = currentPageItems[itemId] || {
                        id: itemId,
                        title: existingTitles[itemId] || "Item " + itemId  // Use existing title or fallback
                    };
                    accumulatedItems.push(itemData);
                }
            });
        }

        // Check items that are in accumulated list
        document.querySelectorAll(".item-checkbox").forEach(function(checkbox) {
            var isAccumulated = accumulatedItems.find(function(p) { return p.id === checkbox.dataset.id; });
            if (isAccumulated) {
                checkbox.checked = true;
                var itemData = {
                    id: checkbox.dataset.id,
                    title: checkbox.dataset.title
                };
                selectedItems.push(itemData);

                // Update accumulated item with correct title from current page
                isAccumulated.title = checkbox.dataset.title;
            }
        });

        updateUI();
    }

    // Handle checkbox changes
    document.querySelectorAll(".item-checkbox").forEach(function(checkbox) {
        checkbox.addEventListener("change", function() {
            var itemData = {
                id: this.dataset.id,
                title: this.dataset.title
            };

            if (this.checked) {
                // Add to current page selection
                var exists = selectedItems.find(function(p) { return p.id === itemData.id; });
                if (!exists) {
                    selectedItems.push(itemData);
                }

                // Add to accumulated items immediately
                var existsInAccumulated = accumulatedItems.find(function(p) { return p.id === itemData.id; });
                if (!existsInAccumulated) {
                    accumulatedItems.push(itemData);
                    //console.log("Added item to accumulated list:", itemData.title);
                }
            } else {
                // Remove from both current page selection and accumulated items
                selectedItems = selectedItems.filter(function(p) { return p.id !== itemData.id; });
                accumulatedItems = accumulatedItems.filter(function(p) { return p.id !== itemData.id; });
                //console.log("Removed item from accumulated list:", itemData.title);
            }

            // Update sessionStorage immediately
            sessionStorage.setItem(storageKey, JSON.stringify(accumulatedItems));
            //console.log("Updated storage, total items:", accumulatedItems.length);

            updateUI();
            updateTooltips();
        });
    });

    // Handle select-all checkbox
    var selectAllCheckbox = document.getElementById("select-all");
    if (selectAllCheckbox) {
        selectAllCheckbox.addEventListener("change", function() {
            var checkboxes = document.querySelectorAll(".item-checkbox");
            checkboxes.forEach(function(checkbox) {
                if (checkbox.checked !== selectAllCheckbox.checked) {
                    checkbox.checked = selectAllCheckbox.checked;

                    // Manually handle the logic instead of dispatching event
                    var itemData = {
                        id: checkbox.dataset.id,
                        title: checkbox.dataset.title
                    };

                    if (checkbox.checked) {
                        // Add to current page selection
                        var exists = selectedItems.find(function(p) { return p.id === itemData.id; });
                        if (!exists) {
                            selectedItems.push(itemData);
                        }

                        // Add to accumulated items immediately
                        var existsInAccumulated = accumulatedItems.find(function(p) { return p.id === itemData.id; });
                        if (!existsInAccumulated) {
                            accumulatedItems.push(itemData);
                            //console.log("Added item to accumulated list:", itemData.title);
                        }
                    } else {
                        // Remove from both current page selection and accumulated items
                        selectedItems = selectedItems.filter(function(p) { return p.id !== itemData.id; });
                        accumulatedItems = accumulatedItems.filter(function(p) { return p.id !== itemData.id; });
                        //console.log("Removed item from accumulated list:", itemData.title);
                    }
                }
            });

            // Update sessionStorage after all changes
            sessionStorage.setItem(storageKey, JSON.stringify(accumulatedItems));
            //console.log("Updated storage after select-all, total items:", accumulatedItems.length);

            updateUI();
            updateTooltips();
        });
    }

    // Handle Clear Selection button
    var clearBtn = document.getElementById("clear-selection-btn");
    if (clearBtn) {
        clearBtn.addEventListener("click", function() {
            // Clear all selections
            accumulatedItems = [];
            selectedItems = [];

            // Uncheck all checkboxes on current page
            document.querySelectorAll(".item-checkbox").forEach(function(checkbox) {
                checkbox.checked = false;
            });

            // Clear the parent field by applying empty selection
            var urlParams = new URLSearchParams(window.location.search);
            var functionName = urlParams.get("function") || "jSelectItemMulti";

            if (window.parent && typeof window.parent[functionName] === "function") {
                //window.parent[functionName]([]);  // Apply empty selection to clear the field
            }

            // Clear sessionStorage
            sessionStorage.removeItem(storageKey);
            //console.log("Cleared all selections and parent field");

            // Update UI
            updateUI();
            updateTooltips();
        });
    }

    // Handle Done button
    var doneBtn = document.getElementById("done-btn");
    if (doneBtn) {
        doneBtn.addEventListener("click", function() {
            // Always call parent function, even with empty selection
            var urlParams = new URLSearchParams(window.location.search);
            var functionName = urlParams.get("function") || "jSelectItemMulti";

            if (window.parent && typeof window.parent[functionName] === "function") {
                window.parent[functionName](accumulatedItems);
                //console.log("Applied selection:", accumulatedItems.length > 0 ? accumulatedItems.length + " items" : "cleared selection");
            }

            // Clear all selections after successful application
            accumulatedItems = [];
            selectedItems = [];

            // Uncheck all checkboxes on current page
            document.querySelectorAll(".item-checkbox").forEach(function(checkbox) {
                checkbox.checked = false;
            });

            // Clear sessionStorage to prevent memory leaks
            sessionStorage.removeItem(storageKey);
            //console.log("Cleared storage for field:", fieldId);

            // Update UI before closing
            updateUI();
            updateTooltips();

            // Close modal
            if (window.parent) {
                var closeBtn = window.parent.document.querySelector(".modal .btn-close");
                if (closeBtn) closeBtn.click();
            }
        });
    }

    function updateUI() {
        var count = accumulatedItems.length;

        // Update the combined Done button with count
        var doneBtn = document.getElementById("done-btn");
        var doneBtnText = document.getElementById("done-btn-text");
        var countBadge = document.getElementById("selected-count-badge");

        if (doneBtn && doneBtnText && countBadge) {
            if (count > 0) {
                doneBtnText.textContent = Joomla.Text._('LIB_J2COMMERCE_ITEM_SELECT_MODAL_SELECTION_DONE_LABEL');
                countBadge.textContent = count;
                countBadge.style.display = "inline";
                doneBtn.classList.remove("btn-secondary");
                doneBtn.classList.add("btn-success");
            } else {
                doneBtnText.textContent = Joomla.Text._('LIB_J2COMMERCE_ITEM_SELECT_MODAL_SELECTION_DONE_LABEL');
                countBadge.style.display = "none";
                doneBtn.classList.remove("btn-success");
                doneBtn.classList.add("btn-secondary");
            }
        }

        // Show/hide Clear Selection button
        var clearBtn = document.getElementById("clear-selection-btn");
        if (clearBtn) {
            clearBtn.style.display = count > 0 ? "inline-block" : "none";
        }

        // Update select-all checkbox state based on current page
        var selectAllCheckbox = document.getElementById("select-all");
        if (selectAllCheckbox) {
            var checkboxes = document.querySelectorAll(".item-checkbox");
            var checkedCount = 0;
            checkboxes.forEach(function(checkbox) {
                if (checkbox.checked) checkedCount++;
            });

            if (checkedCount === 0) {
                selectAllCheckbox.checked = false;
                selectAllCheckbox.indeterminate = false;
            } else if (checkedCount === checkboxes.length) {
                selectAllCheckbox.checked = true;
                selectAllCheckbox.indeterminate = false;
            } else {
                selectAllCheckbox.checked = false;
                selectAllCheckbox.indeterminate = true;
            }
        }
    }

    // Initialize Bootstrap tooltips
    function initializeTooltips() {
        var tooltipTriggerList = [].slice.call(document.querySelectorAll("[data-bs-toggle=\"tooltip\"]"));
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
    }

    // Update tooltip text based on checkbox state
    function updateTooltips() {
        document.querySelectorAll(".item-label").forEach(function(label) {
            var checkboxId = label.getAttribute("for");
            var checkbox = document.getElementById(checkboxId);
            if (checkbox) {
                var tooltip = bootstrap.Tooltip.getInstance(label);
                var newTitle = checkbox.checked ? Joomla.Text._('LIB_J2COMMERCE_ITEM_UNSELECT') : Joomla.Text._('LIB_J2COMMERCE_ITEM_SELECT');

                if (tooltip) {
                    label.setAttribute("data-bs-original-title", newTitle);
                    tooltip.setContent({".tooltip-inner": newTitle });
                }
            }
        });
    }

    // Initialize tooltips first
    setTimeout(function() {
        initializeTooltips();
        initializePreSelected();
        updateTooltips(); // Update tooltips after pre-selection
    }, 100);
});