import JoomlaDialog from 'joomla.dialog';

/**
 * @package     J2Commerce Library
 * @subpackage  lib_j2commerce
 *
 * @copyright   (C)2024-2025 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

/**
 * Show Select dialog
 *
 * @param {HTMLInputElement} inputValue
 * @param {HTMLInputElement} inputTitle
 * @param {Object} dialogConfig
 * @returns {Promise}
 */
const doSelect = (dialogConfig) => {
  // Use a JoomlaExpectingPostMessage flag to be able to distinct legacy methods
  // @TODO: This should be removed after full transition to postMessage()
  window.JoomlaExpectingPostMessage = true;
  // Create and show the dialog
  const dialog = new JoomlaDialog(dialogConfig);
  dialog.classList.add('joomla-dialog-content-multiselect-field');
  dialog.show();
  return new Promise(resolve => {
    const msgListener = event => {
      // Avoid cross origins
      if (event.origin !== window.location.origin) return;
      // Check message type
      if (event.data.messageType === 'joomla:content-select') {
        dialog.close();
      } else if (event.data.messageType === 'joomla:cancel') {
        dialog.close();
      }
    };

    // Clear all when dialog is closed
    dialog.addEventListener('joomla-dialog:close', () => {
      delete window.JoomlaExpectingPostMessage;
      window.removeEventListener('message', msgListener);
      dialog.destroy();
      resolve();
    });

    // Wait for message
    window.addEventListener('message', msgListener);
  });
};

/**
 * Initialise the field
 * @param {HTMLElement} container
 */
const setupField = container => {

  // Bind the buttons
  container.addEventListener('click', event => {
    const button = event.target.closest('[data-button-action]');
    if (!button) return;
    event.preventDefault();

    // Extract the data
    const action = button.dataset.buttonAction;
    const dialogConfig = button.dataset.modalConfig ? JSON.parse(button.dataset.modalConfig) : {};
    const keyName = container.dataset.keyName || 'id';
    const token = Joomla.getOptions('csrf.token', '');

    // Handle requested action
    let handle;
    switch (action) {
      case 'select':
      case 'create':
        {
          const url = dialogConfig.src.indexOf('http') === 0 ? new URL(dialogConfig.src) : new URL(dialogConfig.src, window.location.origin);
          url.searchParams.set(token, '1');
          dialogConfig.src = url.toString();
          handle = doSelect(dialogConfig);
          break;
        }
      default:
        throw new Error(`Unknown action ${action} for Modal select field`);
    }
  });
};
const setup = container => {
  container.querySelectorAll('.js-modal-content-multiselect-field').forEach(el => setupField(el));
};
document.addEventListener('DOMContentLoaded', () => setup(document));
document.addEventListener('joomla:updated', event => setup(event.target));
