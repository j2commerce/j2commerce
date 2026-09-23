/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

// Dashboard notices — carousel with dismiss support.
// Deliberately separate from dashboard.js: the notices are gated on core.edit while dashboard.js
// is gated on j2commerce.viewreports and depends on Chart.js, so sharing a file left every admin
// without the reports permission holding un-dismissable markup.
(() => {
    const STORAGE_KEY = 'j2c_dismissed_msgs';

    function readList(storage) {
        try {
            const raw = JSON.parse(storage.getItem(STORAGE_KEY) || '[]');
            return Array.isArray(raw) ? raw : [];
        } catch (e) {
            return [];
        }
    }

    function getDismissed() {
        return [...readList(sessionStorage), ...readList(localStorage)];
    }

    function dismissMessage(id, mode) {
        const storage = mode === 'forever' ? localStorage : sessionStorage;
        const list = readList(storage);

        if (!list.includes(id)) {
            list.push(id);
            try {
                storage.setItem(STORAGE_KEY, JSON.stringify(list));
            } catch (e) {
                // A full or blocked store must not stop the slide from being removed.
            }
        }
    }

    function removeWrap() {
        document.getElementById('j2commerce-dashboard-messages-wrap')?.remove();
    }

    function removeControls() {
        document.getElementById('j2commerce-dashboard-messages-controls')?.remove();
    }

    // The fade effect only drops a slide's opacity, so without this the links and buttons of
    // every hidden slide stay in the tab order — focusable, and completely invisible once
    // focused. Swiper's own a11y module does this, but it is off: it would overwrite our
    // translated labels with its untranslated English defaults.
    function syncSlideTabIndexes(swiper) {
        swiper.slides.forEach((slide, index) => {
            const isActive = index === swiper.activeIndex;

            slide.setAttribute('aria-hidden', isActive ? 'false' : 'true');
            slide.querySelectorAll('a[href], button').forEach((node) => {
                node.tabIndex = isActive ? 0 : -1;
            });
        });
    }

    function initDashboardMessages() {
        const el = document.getElementById('j2commerce-dashboard-messages');

        if (!el || typeof Swiper === 'undefined') {
            // Controls that no carousel will ever drive are worse than no controls.
            removeControls();
            return;
        }

        const dismissed = getDismissed();

        el.querySelectorAll('.swiper-slide').forEach((slide) => {
            if (dismissed.includes(slide.dataset.messageId)) {
                slide.remove();
            }
        });

        const remaining = el.querySelectorAll('.swiper-slide').length;

        if (remaining === 0) {
            removeWrap();
            return;
        }

        // One notice is not a carousel: no rotation to control and nothing to page between.
        if (remaining < 2) {
            removeControls();
            return;
        }

        // 2.2.2 Pause, Stop, Hide — honour a reduced-motion preference by not rotating at all.
        const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

        const swiper = new Swiper(el, {
            effect: 'fade',
            fadeEffect: { crossFade: true },
            speed: 800,
            loop: false,
            // Our own controls carry translated labels; Swiper's a11y module would overwrite
            // them with its untranslated English defaults.
            a11y: false,
            keyboard: { enabled: true, onlyInViewport: true },
            navigation: {
                prevEl: '#j2commerce-dashboard-messages-prev',
                nextEl: '#j2commerce-dashboard-messages-next',
            },
            autoplay: reduceMotion
                ? false
                : {
                    delay: 10000,
                    // Must stay false: disabling autoplay on the first interaction left every
                    // later notice unreachable once the carousel had no other control.
                    disableOnInteraction: false,
                    pauseOnMouseEnter: true,
                },
            on: {
                init: syncSlideTabIndexes,
                slideChange: syncSlideTabIndexes,
            },
        });

        const toggle = document.getElementById('j2commerce-dashboard-messages-rotate');

        if (!toggle) {
            return;
        }

        if (reduceMotion) {
            toggle.remove();
            return;
        }

        const setToggleState = (running) => {
            toggle.dataset.running = running ? '1' : '0';
            toggle.setAttribute('aria-label', running ? toggle.dataset.labelStop : toggle.dataset.labelStart);
            toggle.querySelector('span').className = running
                ? 'fa-solid fa-pause'
                : 'fa-solid fa-play';
        };

        setToggleState(true);

        toggle.addEventListener('click', () => {
            if (toggle.dataset.running === '1') {
                swiper.autoplay.stop();
                return;
            }

            swiper.autoplay.start();
        });

        // The label is the only signal of whether rotation is running, so it has to follow
        // Swiper rather than our clicks: pauseOnMouseEnter stops and starts autoplay inside
        // Swiper, and a label still reading "stop" while hover has paused it is a lie.
        // Four events, not two: stop/start are the explicit calls, pause/resume are what
        // pauseOnMouseEnter and the Page Visibility handler raise.
        swiper.on('autoplayStop autoplayPause', () => setToggleState(false));
        swiper.on('autoplayStart autoplayResume', () => setToggleState(true));

        // APG: rotation stops when keyboard focus enters the carousel, and does not restart on
        // its own — a user who tabbed in is reading, and silent resumption moves it under them.
        el.addEventListener('focusin', () => {
            if (toggle.dataset.running === '1') {
                swiper.autoplay.stop();
            }
        });
    }

    document.addEventListener('click', (e) => {
        const btn = e.target.closest('[data-dismiss-message]');

        if (!btn) {
            return;
        }

        const slide = btn.closest('.swiper-slide');
        const id = slide?.dataset.messageId;

        if (!id) {
            return;
        }

        dismissMessage(id, btn.dataset.dismissMessage);
        slide.remove();

        const el = document.getElementById('j2commerce-dashboard-messages');
        const remaining = el ? el.querySelectorAll('.swiper-slide').length : 0;

        if (remaining === 0) {
            removeWrap();
            return;
        }

        if (el?.swiper) {
            el.swiper.update();
            syncSlideTabIndexes(el.swiper);
        }

        if (remaining < 2) {
            // Nothing left to rotate between, so the rotation must stop with its controls.
            el?.swiper?.autoplay?.stop();
            removeControls();
        }

        // The dismissed slide held focus, and removing it drops focus to <body>. Aim at the
        // notice now on screen — never a plain querySelector across every slide, which would
        // land on a hidden one — and fall back to the wrapper, which carries tabindex="-1".
        const active = el?.querySelector('.swiper-slide-active') || el?.querySelector('.swiper-slide');
        const target = active?.querySelector('a[href], button')
            || document.getElementById('j2commerce-dashboard-messages-wrap');

        target?.focus();
    });

    // Always use DOMContentLoaded — Swiper loads as a separate defer script
    // that may not have executed yet when this IIFE runs
    if (document.readyState === 'loading' || document.readyState === 'interactive') {
        document.addEventListener('DOMContentLoaded', initDashboardMessages);
    } else {
        initDashboardMessages();
    }
})();
