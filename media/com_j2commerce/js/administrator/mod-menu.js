/**
 * @package     J2Commerce
 * @subpackage  mod_j2commerce_menu
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

// Atum's status layout echoes the rendered module twice — once in `.header-item`
// and once inside the `#header-more-items` overflow dropdown — so the template
// cannot emit unique ids of its own. Ids are assigned here instead, per copy,
// and each toggle is pointed at the panel that shipped with it.
const initJ2CommerceMenu = () => {
    const toggles = document.querySelectorAll('.j2commerce-offcanvas-toggle');
    const panels = document.querySelectorAll('.j2commerce-offcanvas');

    panels.forEach((panel, index) => {
        if (!panel.id) {
            panel.id = `j2commerceOffcanvas-${index}`;
        }

        const toggle = toggles[index];

        if (toggle) {
            toggle.setAttribute('data-bs-target', `#${panel.id}`);
            toggle.setAttribute('aria-controls', panel.id);
        }

        const nav = panel.querySelector('.j2commerce-nav');

        if (nav && typeof MetisMenu !== 'undefined') {
            new MetisMenu(nav, { toggle: true });
        }
    });
};

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initJ2CommerceMenu, { once: true });
} else {
    initJ2CommerceMenu();
}
