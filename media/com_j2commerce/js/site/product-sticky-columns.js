/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

/**
 * Sticky product detail column.
 *
 * Whichever of the two columns is shorter gets is-sticky, so the taller one scrolls past it.
 * A ResizeObserver covers every height change without listening for each feature that causes
 * one — accordion panels, tab switches, variant swaps, options revealing extra fields.
 */
(() => {
    'use strict';

    const setup = (row) => {
        const media = row.querySelector(':scope > .j2c-product-detail-media');
        const info = row.querySelector(':scope > .j2c-product-detail-info');

        if (!media || !info) {
            return;
        }

        const apply = () => {
            // Side by side is what makes sticky meaningful, and it is what the two grid
            // frameworks disagree about: Bootstrap stacks below 992px, UIkit below 1200px.
            // Asking the rendered layout beats guessing a breakpoint — but by width, not by
            // offset: a sticky column's offset shifts while scrolled, so an observer firing
            // mid-scroll (a lazily loaded image, say) would read the pair as stacked and
            // drop the class. Widths do not move.
            const sideBySide = media.offsetWidth + info.offsetWidth <= row.clientWidth + 1;
            const mediaHeight = media.offsetHeight;
            const infoHeight = info.offsetHeight;

            const stick = sideBySide && mediaHeight !== infoHeight
                ? (mediaHeight < infoHeight ? media : info)
                : null;

            media.classList.toggle('is-sticky', stick === media);
            info.classList.toggle('is-sticky', stick === info);
        };

        // Measuring inside the observer callback would re-enter it via the class change.
        let queued = false;
        const schedule = () => {
            if (queued) {
                return;
            }

            queued = true;
            window.requestAnimationFrame(() => {
                queued = false;
                apply();
            });
        };

        const observer = new ResizeObserver(schedule);
        observer.observe(media);
        observer.observe(info);
        window.addEventListener('resize', schedule);

        apply();
    };

    const init = () => document.querySelectorAll('.j2c-sticky-enabled').forEach(setup);

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
