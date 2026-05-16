/**
 * @package     J2Commerce
 * @subpackage  mod_j2commerce_menu
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

const initJ2CommerceMenu = () => {
    const nav = document.getElementById('j2commerceNav');

    if (!nav || typeof MetisMenu === 'undefined') {
        return;
    }

    new MetisMenu(nav, { toggle: true });
};

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initJ2CommerceMenu, { once: true });
} else {
    initJ2CommerceMenu();
}
