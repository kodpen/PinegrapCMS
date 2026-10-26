/**
 * Pinegrap - Enterprise Website Platform
 *
 * Bootstrap 6 bridge: loads Bootstrap's ES module bundle for the panel and
 * hands it to the classic scripts.
 *
 * Bootstrap 6 ships only as an ES module and sets no global. The panel's
 * scripts are classic and reach Bootstrap through pgUI (pg_ui.js), which
 * reads window.bootstrap and waits on window.pgBootstrapReady. This module
 * sets the global and fires pg:bootstrap-ready, which settles that promise.
 * It never adds the Bootstrap 5 names (Modal, Dropdown, Offcanvas): a
 * Dialog built on a <div> throws, so an old call must fail where it stands.
 *
 * A module script is deferred: printed in <head> before the screens' own
 * defer bundles, it runs before them and before jQuery's ready handlers.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

import * as bootstrap from '../lib/bootstrap-6/js/bootstrap.bundle.min.js';

window.bootstrap = bootstrap;

// Chromium: two quick presses of Escape on an animated dialog close it past
// Bootstrap's own cleanup and leave dialog-open (the scroll lock) on <html>.
// The class follows what is still open after every close, which also keeps
// it while a dialog stacked on another one closes. The close event does not
// bubble, hence the capture phase.
document.addEventListener('close', function (event) {
    if (!(event.target instanceof HTMLDialogElement)) {
        return;
    }
    document.documentElement.classList.toggle('dialog-open', !!document.querySelector('dialog[open]:modal'));
}, true);

document.dispatchEvent(new CustomEvent('pg:bootstrap-ready', { detail: bootstrap }));
