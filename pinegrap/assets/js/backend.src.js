// Translation shim, defined up front so every top level helper in this file
// can call it regardless of where in the file it sits. It reads the map the
// panel header emits (output.php) and falls back to the English key when the
// map is absent or lacks the entry, so a missing key never renders "undefined".
window.pgLang = function (key) {
    var v = (typeof translate !== 'undefined' && translate) ? translate[key] : undefined;
    return (v !== undefined && v !== null) ? String(v) : key;
};

// Says a short answer to a screen reader without moving focus. A live region
// is only heard when its words change after it is in the document, so the two
// regions (polite and assertive) are made empty once the page has loaded and
// the words are written a moment later; a region inserted together with its
// words, like a panel redrawn with its report, is read by nobody. The region
// is emptied first, so the same sentence twice in a row is heard twice.
(function () {
    var timers = {};

    function region(assertive) {
        var id = assertive ? 'pg_live_assertive' : 'pg_live_polite';
        var el = document.getElementById(id);

        if (!el && document.body) {
            el = document.createElement('div');
            el.id = id;
            el.className = 'visually-hidden';
            el.setAttribute('role', assertive ? 'alert' : 'status');
            el.setAttribute('aria-live', assertive ? 'assertive' : 'polite');
            el.setAttribute('aria-atomic', 'true');
            document.body.appendChild(el);
        }

        return el;
    }

    window.pgAnnounce = function (text, assertive) {
        var el = region(!!assertive);

        if (!el) {
            return;
        }

        el.textContent = '';
        window.clearTimeout(timers[el.id]);
        timers[el.id] = window.setTimeout(function () {
            el.textContent = String(text || '');
        }, 150);
    };

    function prepare() {
        region(false);
        region(true);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', prepare);
    } else {
        prepare();
    }
})();

// Panel money boxes use the Inputmask "currency" alias with the separators of
// the panel language (pg_money_mask_attributes()). The numeric alias turns
// whichever of "." and "," is not its decimal point into the decimal point as
// it is typed, so "12.500" on a Turkish panel became 12,50 and "1,234.56" on
// an English one 1.23. A typed thousands separator stays a thousands
// separator: the mask passes over it and groups the digits itself. Defined
// before the binding masks the page's boxes on document ready.
(function () {
    var Inputmask = window.Inputmask;
    var numeric = (Inputmask && Inputmask.prototype && Inputmask.prototype.aliases) ? Inputmask.prototype.aliases.numeric : null;

    if (!numeric || (typeof numeric.mask !== 'function') || (typeof Inputmask.extendAliases !== 'function')) {
        return;
    }

    var numericMask = numeric.mask;

    Inputmask.extendAliases({
        currency: {
            mask: function (opts) {
                var mask = numericMask.call(this, opts);

                if (opts.substitutes && (opts.groupSeparator !== '') && (opts.groupSeparator !== opts.radixPoint)) {
                    delete opts.substitutes[opts.groupSeparator];
                }

                return mask;
            }
        }
    });
})();

(() => {
    "use strict";

    const getPreferredTheme = () => {
        const storedTheme = localStorage.getItem("pinegrap backend color scheme");
        if (storedTheme) {
            return storedTheme;
        }
        return window.matchMedia("(prefers-color-scheme: dark)").matches ? "dark" : "light";
    };

    const setTheme = (theme) => {
        const prefersDarkScheme = window.matchMedia("(prefers-color-scheme: dark)").matches;
        const resolvedTheme = theme === "auto" ? (prefersDarkScheme ? "dark" : "light") : theme;

        document.documentElement.setAttribute("data-bs-theme", resolvedTheme);
        localStorage.setItem("pinegrap backend color scheme", theme);
        document.cookie = `prefers-color-scheme=${resolvedTheme}`;

        // Push the resolved theme to every code editor on the page.
        updateCodeEditorTheme(resolvedTheme);
    };

    // The code editors (pg_code_editor_attach() and the screens that build
    // their own keep them in window.pgCodeEditors) follow the scheme.
    const updateCodeEditorTheme = (resolvedTheme) => {
        const editors = window.pgCodeEditors || {};
        Object.keys(editors).forEach(id => {
            const editor = editors[id];
            if (editor && (typeof editor.setTheme === "function") && !(editor.isDestroyed && editor.isDestroyed())) {
                editor.setTheme(resolvedTheme);
            }
        });
    };

    const showActiveTheme = (theme) => {
        document.querySelectorAll("[data-bs-theme-value]").forEach(element => {
            element.classList.remove("active");
        });
        const activeElementId = theme === "auto" ? "theme-auto" : `theme-${theme}`;
        document.getElementById(activeElementId)?.classList.add("active");

        // The switch in the account menu is three buttons for one choice, so
        // each says whether it is the one that is on.
        document.querySelectorAll(".pg-um-themebtn").forEach(button => {
            button.setAttribute("aria-pressed", button.classList.contains("active") ? "true" : "false");
        });
    };

    const onSchemeChange = () => {
        if (localStorage.getItem("pinegrap backend color scheme") === "auto") {
            setTheme("auto");
        }
    };

    window.matchMedia("(prefers-color-scheme: dark)").addEventListener("change", onSchemeChange);

    document.addEventListener("DOMContentLoaded", () => {
        const theme = getPreferredTheme();
        setTheme(theme);
        showActiveTheme(theme);

        document.querySelectorAll("[data-bs-theme-value]").forEach(button => {
            button.addEventListener("click", () => {
                const selectedTheme = button.getAttribute("data-bs-theme-value");
                setTheme(selectedTheme);
                showActiveTheme(selectedTheme);
            });
        });
    });

})();



function setCookie(cname, cvalue, exdays) {
    const d = new Date();
    d.setTime(d.getTime() + (exdays * 24 * 60 * 60 * 1000));
    let expires = "expires=" + d.toUTCString();
    document.cookie = cname + "=" + cvalue + ";" + expires + ";path=/";
}
function getCookie(cname) {
    let name = cname + "=";
    let decodedCookie = decodeURIComponent(document.cookie);
    let ca = decodedCookie.split(';');
    for (let i = 0; i < ca.length; i++) {
        let c = ca[i];
        while (c.charAt(0) == ' ') {
            c = c.substring(1);
        }
        if (c.indexOf(name) == 0) {
            return c.substring(name.length, c.length);
        }
    }
    return "";
}
function open_search_index_window() {
    window.open('update_search_index.php', 'popup', 'toolbar=no,location=no,directories=no,status=yes,menubar=no,resizable=yes,copyhistory=no,scrollbars=yes,width=500,height=500');
}
function lang($string) {
    return window.pgLang($string);
}

function getPreferredTheme() {
    var LocalStorageSoftwarePinegrapTheme = localStorage.getItem('Software_Pinegrap_Theme');
    if (LocalStorageSoftwarePinegrapTheme && LocalStorageSoftwarePinegrapTheme != 'auto') {
        if (LocalStorageSoftwarePinegrapTheme == 'dark') {
            return 'dark';
        } else {
            return 'light';
        }
    } else {
        var OsStorageSoftwarePinegrapTheme = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
        if (OsStorageSoftwarePinegrapTheme == 'dark') {
            return 'dark';
        } else {
            return 'light';
        }
    }
}

function getPreferredThemeColor() {
    var LocalStorageSoftwarePinegrapTheme = localStorage.getItem('pinegrap backend color scheme');
    if (LocalStorageSoftwarePinegrapTheme && LocalStorageSoftwarePinegrapTheme != 'auto') {
        if (LocalStorageSoftwarePinegrapTheme == 'dark') {
            return '#fff';
        } else {
            return '#000';
        }
    } else {
        var OsStorageSoftwarePinegrapTheme = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
        if (OsStorageSoftwarePinegrapTheme == 'dark') {
            return '#fff';
        } else {
            return '#000';
        }
    }
}

$(document).ready(function () {


    //update backdrop height.
    update_menu_backdrop_height();

    //init lazy if there is img with lazy class.
    if ($("img.lazy").length > 0) {
        $("img.lazy:not(.delayed)").Lazy();
    }

    // Get the page designer button so that we can add a click event
    // A binding for '#button_bar .page_designer_button' stood here, meant to
    // make Ctrl+G navigate the parent window. It never matched anything:
    // pg_page_shell() draws no #button_bar, and that button has only ever
    // existed in the shell. The shortcut works through the handler further
    // down this file, and through the one in frontend.src.js on the front end.

    // F1 opens the documentation of what is on screen, in a new tab. While the
    // settings dialog is open that is the category in it, not the screen
    // underneath. The help controls are plain links; this only follows one,
    // so a private-labelled site, which prints none, gets nothing here either.
    $(document).on('keydown', function (e) {
        if ((e.key !== 'F1') || e.ctrlKey || e.metaKey || e.altKey || e.shiftKey) {
            return;
        }

        var link = $('#pg_settings_modal.show #pg_settings_modal_help:not(.d-none)').get(0)
            || document.getElementById('help_button')
            || $('#help_link a').get(0);

        if (!link || !link.href) {
            return;
        }

        e.preventDefault();
        window.open(link.href, '_blank', 'noopener');
    });
    $('.software_warning.alert-dismissible,.software_notice.alert-dismissible,.software_error.alert-dismissible').each(function () {
        $(this).addClass('fade');
        $(this).find('button.btn-close').attr('style', '');
    });


    // Buttons are never relabelled or held while a request runs. A label
    // swapped to "Saving" and a button kept disabled for a fixed time made
    // screens that save over Ajax wait long after the answer had come back.
    // Double submits are stopped on the form instead (see below), and a
    // screen that sends its own request disables its button for exactly as
    // long as that request runs.
    $('a:not(.no-submit),button[type=submit]:not(.no-submit),button[type=button]:not(.no-submit)').on('click', function () {
        var el = $(this);
        var confirm_content = el.attr("data-confirm-content");
        var confirm_action = el.attr("data-confirm-action");
        var value = el.attr("value");
        var result;
        var autoconfirm = false;
        var confirmation = false;
        switch (value) {
            case 'Remove Card Data for Selected':
                document.form.action.value = 'remove_card_data';
                result = true;
                break;
            case 'Export Orders For Parasut':
                document.form.action.value = 'export_orders_for_parasut';
                autoconfirm = true;
                confirmation = true;
                result = true;
                break;
            case 'Delete Selected':
                document.form.action.value = 'delete';
                result = true;
                break;
            case 'Cancel Selected':
                document.form.action.value = 'cancel';
                result = true;
                break;
            case 'Mark Shipped Selected':
                document.form.action.value = 'mark_shipped';
                result = true;
                break;
            case 'Merge Selected':
                document.form.action.value = 'merge';
                result = true;
                break;
            case 'Opt-In Selected':
                document.form.action.value = 'optin';
                result = true;
                break;
            case 'Opt-Out Selected':
                document.form.action.value = 'optout';
                result = true;
                break;
        }
        // if its not autoconfirmed btn and there is confirm content in this button
        if (autoconfirm == false && confirm_content) {
            if (confirm(confirm_content) == true) {
                confirmation = true;
            }
        }
        if (confirm_content || autoconfirm == true) {
            if (confirmation == true) {
                // if this is parasut export button, than output page refresh for user to see the changed order results.
                if (value == 'Export Orders For Parasut') {
                    pgUI.dialog.hide('#xlsl_setup_modal');
                    //reload this page to show user exported orders.
                    $('body').prepend('<div id="couldown_for_order_exporting_10000" style="z-index: 999;background: color-mix(in srgb, var(--pg-bg-body) 70%, transparent);color: var(--pg-fg-body);position: fixed;left: 0;top: 0;width: 100%;height: 100%;text-align: center;line-height: 100vh;backdrop-filter: blur(4px);font-size: 40px;">' + lang('Please Wait') + '...</div>');
                    $('body').attr('style', 'overflow:hidden');
                    setTimeout(function () {
                        window.location.reload();
                    }, 4000);//4sec wait
                }

                //if form need to be send also send it.
                if (result == true) {
                    document.form.submit();
                }
                if (confirm_action && confirm_action == 'history-back') {
                    history.go(-1);
                }
                return true;
            } else {
                //user selected NO
                return false;
            }
        }
    });

    // A double click, or Enter pressed twice, must not send the same form or
    // open the same link twice: some of them do something (create, delete,
    // duplicate) rather than only show a page. The guard lives on the form
    // or link, not on the button: nothing on screen changes, and the
    // submitting button keeps its name and value in the request (a disabled
    // button would drop them). It only lasts a moment, because a request
    // whose answer is a download leaves the page where it is and has to be
    // repeatable straight away.
    //
    // Installed once per window: a second copy would find the form just
    // marked by the first and cancel the submit.
    if (!window.pgSubmitGuard) {
        window.pgSubmitGuard = true;

        var pg_sent = new WeakMap();

        var pg_just_sent = function (el) {
            var now = Date.now();

            if ((now - (pg_sent.get(el) || 0)) < 3000) {
                return true;
            }

            pg_sent.set(el, now);

            return false;
        };

        // An answer that opens in another tab or frame leaves this page as
        // it is; sending again from here is a new request.
        var pg_opens_here = function (target) {
            return !target || (target.toLowerCase() === '_self');
        };

        // Both listeners are on window, so they run after every handler on
        // the element and on document: a screen that sends the form over
        // Ajax, or handles the link itself, has cancelled the event by then
        // and is left alone.
        window.addEventListener('submit', function (e) {
            var form = e.target;

            if (e.defaultPrevented || !form || (form.nodeName !== 'FORM')) {
                return;
            }

            if (!pg_opens_here((e.submitter && e.submitter.getAttribute('formtarget')) || form.getAttribute('target'))) {
                return;
            }

            if (pg_just_sent(form)) {
                e.preventDefault();
            }
        });

        window.addEventListener('click', function (e) {
            if (e.defaultPrevented || (e.button !== 0) || e.ctrlKey || e.metaKey || e.shiftKey || e.altKey) {
                return;
            }

            var link = (e.target && e.target.closest) ? e.target.closest('a[href]') : null;

            if (!link || link.hasAttribute('download') || !pg_opens_here(link.getAttribute('target'))) {
                return;
            }

            var href = link.getAttribute('href');

            if (!href || (href.charAt(0) === '#') || /^\s*javascript:/i.test(href)) {
                return;
            }

            if (pg_just_sent(link)) {
                e.preventDefault();
            }
        });

        // Back to this page from the history: everything on it can be sent
        // again.
        window.addEventListener('pageshow', function (e) {
            if (e.persisted) {
                pg_sent = new WeakMap();
            }
        });
    }



    // Add keyboard shortcuts.
    $(window).bind('keydown', function (event) {
        if (event.ctrlKey || event.metaKey) {
            switch (String.fromCharCode(event.which).toLowerCase()) {
                // Add keyboard shortcut (Ctrl+D) for fullscreen toggle
                // for when focus is on the toolbar.
                case 'd':
                    event.preventDefault();
                    var fullscreen_toggle = $(parent.document).find('#software_fullscreen_toggle');

                    if (fullscreen_toggle.length) {

                        event.preventDefault();
                        fullscreen_toggle.click();
                    } else {
                        // The button in the bar: the drawer on a phone, the
                        // next of the three menu states on a wide screen.
                        event.preventDefault();
                        $('#menu_mode_toggle').trigger('click');
                    }
                    break;

                // Add keyboard shortcut (Ctrl+E) for edit mode,
                // for when focus is on the toolbar.
                case 'e':
                    var grid_toggle = $(parent.document).find('#grid_toggle');

                    if (grid_toggle.length) {
                        event.preventDefault();

                        // Timeout resolves Firefox bug.
                        setTimeout(function () {
                            grid_toggle.click();
                        }, 0);
                    }

                    break;

                // Page designer shortcut (Ctrl+G).
                case 'g':
                    var page_designer_button = $('.page_designer_button');

                    // On a front-end page this handler runs inside the toolbar
                    // iframe, but the button moved out of it: it is on the page
                    // itself now, in the panel behind the SEO ring. Same origin,
                    // so the document above is readable — guarded anyway,
                    // because a screen can be framed by something else.
                    if ((page_designer_button.length == 0) && (window.parent !== window)) {
                        try {
                            page_designer_button = $(window.parent.document).find('.page_designer_button');
                        } catch (e) {
                            page_designer_button = $();
                        }
                    }

                    if (page_designer_button.length) {
                        event.preventDefault();

                        // Timeout is necessary in order to workaround Firefox bug
                        // with Ctrl+G where it would still open find area,
                        // even though we run preventDefault above.
                        setTimeout(function () {
                            page_designer_button.click();
                        }, 0);
                    }

                    break;
                case 'y':
                    //This Function Development purphose. add class body, this class add outlines all elements on body css.This function for development draw line
                    show_draw_lines();
                    break;
                case 'q':
                    var notification_toggle = document.getElementById('notification_toggle');
                    if (notification_toggle && $('#notifications').length) {
                        event.preventDefault();
                        // CTRL+Q on toolbar and backend shows notification panel. tested in chrome,edge(95.0+) and firefox.
                        // Timeout is necessary in order to workaround Firefox bug
                        // with Ctrl+Q where it would still open find area,
                        // even though we run preventDefault above.
                        // The bell is clicked rather than the menu toggled: a
                        // Bootstrap 6 Menu made on the .menu box is a second
                        // instance that ignores the bell's data-bs-display,
                        // positions the panel inline for good and leaves its
                        // own aria-expanded on the box.
                        setTimeout(function () {
                            notification_toggle.click();
                        }, 0);
                    }

                    break;
                case 's':
                    // A dialog or editor that saves on Ctrl+S itself (Site
                    // Settings, a code editor dialog) has already taken the
                    // key. Going on would also submit the screen behind it:
                    // that form was saved, the page reloaded and the dialog
                    // closed before its own answer arrived.
                    if (event.isDefaultPrevented()) {
                        break;
                    }

                    // A "disable_shortcut" class has been added to forms that purely delete
                    // items, because the shortcut is too dangerous in that case.
                    // We could still allow it for those types of forms, but just show
                    // the warning before the form is submitted, however we have not
                    // spent the time to do that, so we will just disable them for now.

                    // Find the form closest to the current focused element.
                    var form = $(document.activeElement).closest('form:not(.disable_shortcut)');
                    if (!form.length) {
                        // No focused form: fall back to the first safe form the
                        // operator can see. A form inside a closed dialog (the
                        // settings modal on every screen) has no handlers wired
                        // yet, and submitting it natively would navigate away.
                        form = $('form:not(.disable_shortcut)').filter(':visible').first();
                    }

                    if (form.length) {
                        event.preventDefault();

                        // Read the preferred submit button name from the form attribute
                        var shortcutName = form.attr('submitshortcut') || 'submit_save';

                        // Try to find the matching button by name
                        var button = form.find('button[name="' + shortcutName + '"]');

                        if (button.length) {
                            // Simulate a click on the button to ensure correct POST data
                            button[0].click();
                        } else {
                            // If no button found, fallback to submitting the form directly
                            form.submit();
                        }
                    }



                    break;

            }
        }
    });



    // Add Ctrl+S keyboard shortcut info to the correct submit button of every form.
    // A "disable_shortcut" class has been added to forms that purely delete
    // items, because the shortcut is too dangerous in that case.

    $('form:not(.disable_shortcut)').each(function () {
        var form = $(this);

        // The target button name comes from the form's submitshortcut attribute, default 'submit_save'.
        var shortcutName = form.attr('submitshortcut') || 'submit_save';

        // Look for the button with that name first.
        var button = form.find('button[name="' + shortcutName + '"]');

        // Fall back to the first submit button when there is none.
        if (!button.length) {
            button = form.find('[type=submit]:first');
        }

        if (button.length) {
            var title = button.prop('title');
            if (title !== '') {
                button.prop('title', title + ' (Ctrl+S | \u2318+S)');
            } else {
                button.prop('title', 'Ctrl+S | \u2318+S');
            }
        }
    });


    //classic Tooltips
    var classicTooltips = $('[data-bs-toggle="tooltip"]').each(function () {
        pgUI.tooltip.get(this, { placement: 'auto' });
    });
    classicTooltips.on('click', function () {
        classicTooltips.each(function () {
            pgUI.tooltip.hide(this);
        });
    });
    //classic popover
    // An empty data-bs-title or data-bs-content (a title a script fills in
    // later) reads as null in Bootstrap, whose type check then throws; an
    // empty string given here wins over the attribute. One element that still
    // fails must not stop the rest of this ready block (the main menu below).
    $('[data-bs-toggle="popover"]:not([ data-bs-custom-class="contextmenu"])').each(function () {
        var popoverOptions = {
            placement: 'auto',
            html: true
        };
        if (this.getAttribute('data-bs-title') === '') {
            popoverOptions.title = '';
        }
        if (this.getAttribute('data-bs-content') === '') {
            popoverOptions.content = '';
        }
        try {
            pgUI.popover.get(this, popoverOptions);
        } catch (error) {
            if (window.console) {
                console.error(error);
            }
        }
    });


    /*
     * The main menu (output_header(), includes/fn/output.php).
     *
     *  - lg and up: the sidebar shares the grid with the content, in one of three
     *    states -- expanded (icons and labels), collapsed (the icon rail) or
     *    hidden (no column at all). The button in the bar walks them in that
     *    order, one click at a time: mini, open, hidden, mini again. The state
     *    is kept in the softwaremenustatus cookie, which the server reads to
     *    draw the page in it (pg_menu_state()). A navigator screen (the
     *    Workspace, the File Manager, the Catalog Manager) shows it open or
     *    as the rail only, kept in softwaremenunav (pg_menu_navigator_state()).
     *  - below lg: the sidebar is an off-canvas drawer that slides over the
     *    content, opened and shut by the same button in the bar. The drawer
     *    state is transient and deliberately NOT written to the cookie --
     *    otherwise opening the menu once on a phone would silently rewrite the
     *    desktop preference.
     *
     * The drawer uses its own .drawer-open class instead of .expanded because
     * .expanded is rendered server side from the cookie and would flash the drawer
     * open on every mobile page load.
     */
    var MENU_DRAWER_BREAKPOINT = 1024;
    var MENU_STATES = ['expanded', 'collapsed', 'hidden'];
    // The panel this tab keeps open (initMenuPanels()). Assigned up here: the
    // init calls below run before the rest of this section is reached.
    var MENU_PANEL_KEY = 'pgMenuPanel';
    // A screen whose own navigation sits in a panel of the menu (the
    // Workspace list, the explorer's tree) shows the menu open on a wide
    // screen, or narrowed to the rail, never hidden. Its choice is kept apart
    // in softwaremenunav, so the other screens keep theirs (the server draws
    // it: pg_menu_navigator_state()). Read up here for the same reason.
    var MENU_NAVIGATOR = document.querySelector('.software-container[data-pg-menu-navigator]') !== null;
    // Set once initMenuPanels() has run: until then a change of the menu's
    // state opens no panel by itself (autoMenuPanel()).
    var menuPanelsReady = false;
    var menuBackdrop = null;

    initMenuToggle();                 // The button in the bar: mini, open, hidden (open and mini on a navigator screen; the drawer on a phone)
    initMenuDrawer();                 // Scrim, Escape, outside click, header menus
    initResizeCollapseHandler();      // Keeps the two modes in sync across resizes
    initMenuPanels();                 // Rows that open their entries inside the menu
    initMenuClock();                  // Dashboard clock and Calendars day keep running

    function isMenuDrawerMode() {
        return window.innerWidth < MENU_DRAWER_BREAKPOINT;
    }

    function isMenuDrawerOpen() {
        return $('.software-container').hasClass('drawer-open');
    }

    function getMenuBackdrop() {
        if (menuBackdrop && menuBackdrop.parentNode) {
            return menuBackdrop;
        }
        menuBackdrop = document.createElement('div');
        menuBackdrop.className = 'software-menu-backdrop pg-print-none';
        menuBackdrop.setAttribute('aria-hidden', 'true');
        document.body.appendChild(menuBackdrop);
        return menuBackdrop;
    }

    function openMenuDrawer() {
        var backdrop = getMenuBackdrop();
        $('.software-container').addClass('drawer-open');
        paintMenuToggle();
        // Next frame, so the element is in the DOM before the opacity transition
        // runs; not for a drawer shut again in between.
        window.requestAnimationFrame(function () {
            if (isMenuDrawerOpen()) {
                backdrop.classList.add('show');
            }
        });
    }

    function closeMenuDrawer() {
        $('.software-container').removeClass('drawer-open');
        paintMenuToggle();
        if (menuBackdrop) {
            menuBackdrop.classList.remove('show');
        }
    }

    function toggleMenuDrawer() {
        if (isMenuDrawerOpen()) {
            closeMenuDrawer();
        } else {
            openMenuDrawer();
        }
    }

    // The order the button in the bar walks the wide-screen states in.
    var MENU_STATE_NEXT = { collapsed: 'expanded', expanded: 'hidden', hidden: 'collapsed' };

    // The wide-screen state the cookie holds; on a navigator screen open
    // unless it was narrowed there.
    function savedMenuState() {
        if (MENU_NAVIGATOR) {
            return (getCookie('softwaremenunav') === 'collapsed') ? 'collapsed' : 'expanded';
        }

        var state = getCookie('softwaremenustatus');
        return (MENU_STATES.indexOf(state) === -1) ? 'collapsed' : state;
    }

    // The wide-screen state on screen now, read off the container.
    function shownMenuState() {
        var container = $('.software-container');

        if (container.hasClass('menu-hidden')) {
            return 'hidden';
        }

        return container.hasClass('expanded') ? 'expanded' : 'collapsed';
    }

    // The shut drawer only slides off screen, so its links would stay in the
    // tab order and the reading order: inert takes them out until it opens.
    // Wide screens never set it.
    function paintMenuInert() {
        var sidebar = document.querySelector('.software-sidebar');

        if (!sidebar) {
            return;
        }

        if (isMenuDrawerMode() && !isMenuDrawerOpen()) {
            sidebar.setAttribute('inert', '');
        } else {
            sidebar.removeAttribute('inert');
        }
    }

    // Names on the button in the bar what it does here: below lg whether the
    // drawer is open, above it the state the menu is in. The picture follows
    // from the stylesheet.
    function paintMenuToggle() {
        var toggle = document.getElementById('menu_mode_toggle');
        var label;

        paintMenuInert();

        if (!toggle) {
            return;
        }

        if (isMenuDrawerMode()) {
            var open = isMenuDrawerOpen();

            toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
            label = toggle.getAttribute(open ? 'data-label-hide' : 'data-label-show');
        } else {
            var state = shownMenuState();

            toggle.setAttribute('aria-expanded', (state !== 'hidden') ? 'true' : 'false');
            label = toggle.getAttribute('data-label-view') + ': ' + toggle.getAttribute('data-label-' + state);
        }

        if (label) {
            toggle.setAttribute('aria-label', label);
            toggle.setAttribute('title', label + ' (Ctrl+D)');
        }
    }

    // Draws a wide-screen state on the container, the menu and the button.
    function paintMenuState(state) {
        $('#menu, .software-container').toggleClass('expanded', state === 'expanded');
        $('.software-container').toggleClass('menu-hidden', (state === 'hidden') && ($('#menu').length > 0));

        paintMenuToggle();
        settleMenuPanel();
    }

    // Chooses a wide-screen state and remembers it, for a year rather than the
    // day the old expand/collapse cookie lasted. A navigator screen keeps its
    // own, open or narrowed.
    function setMenuState(state) {
        if (MENU_NAVIGATOR) {
            if ((state !== 'expanded') && (state !== 'collapsed')) {
                return;
            }

            setCookie('softwaremenunav', state, 365);
            paintMenuState(state);
            return;
        }

        if (MENU_STATES.indexOf(state) === -1) {
            return;
        }

        setCookie('softwaremenustatus', state, 365);
        paintMenuState(state);
    }

    // The state the button in the bar moves a wide screen on to: mini, open,
    // hidden in turn, or on a navigator screen open and narrowed in turn.
    function nextMenuState() {
        var shown = shownMenuState();

        if (MENU_NAVIGATOR) {
            return (shown === 'expanded') ? 'collapsed' : 'expanded';
        }

        return MENU_STATE_NEXT[shown];
    }

    function initMenuToggle() {
        // The button in the bar: the drawer below lg, the next state above.
        $('#menu_mode_toggle').on('click', function (e) {
            e.preventDefault();

            if (isMenuDrawerMode()) {
                toggleMenuDrawer();
                return;
            }

            setMenuState(nextMenuState());
        });
    }

    function initMenuDrawer() {
        // Scrim tap closes. Bound on document because the element is created lazily.
        $(document).on('click', '.software-menu-backdrop', function () {
            closeMenuDrawer();
        });

        // Tapping the page behind the drawer closes it too; not a layer
        // that belongs to the menu while it is in use ([data-pg-menu-hold]:
        // the tree's own menu, whose Rename edits a row in the drawer).
        $('.software-content').on('click', function (e) {
            if (e.target && e.target.closest && e.target.closest('[data-pg-menu-hold]')) {
                return;
            }

            if (isMenuDrawerMode() && isMenuDrawerOpen()) {
                closeMenuDrawer();
            }
        });

        // Following any menu link should not leave the drawer open behind the new page
        // in browsers that restore the DOM from the back-forward cache. A row
        // with a panel opens the panel in the drawer instead (initMenuPanels()).
        $('#menu').on('click', 'a[href]:not([data-pg-menu-panel])', function () {
            if (isMenuDrawerMode()) {
                closeMenuDrawer();
            }
        });

        // A panel open in the drawer goes back first, wherever the focus is;
        // the next Escape closes the drawer.
        $(document).on('keydown', function (e) {
            if ((e.key === 'Escape' || e.key === 'Esc') && isMenuDrawerOpen()) {
                if ($('#menu').hasClass('pg-menu-drilled')) {
                    backMenuPanel({ animate: true, focus: true });
                    return;
                }

                // Focus on a row would stay behind, off screen, in the
                // inert drawer: it goes back to the button that opened it.
                var active = document.activeElement;
                var inMenu = !!(active && active.closest && active.closest('.software-sidebar'));

                closeMenuDrawer();

                var toggle = document.getElementById('menu_mode_toggle');

                if (inMenu && toggle) {
                    toggle.focus();
                }
            }
        });

        // Focus leaving the open drawer shuts it, as it shuts the widened
        // rail of a wide screen: the sidebar comes before the screen in the
        // page, so Tab past its last row would walk the controls under the
        // scrim. Not for a layer that belongs to the menu ([data-pg-menu-hold])
        // or the bar button, whose own click toggles the drawer. Focus with no
        // relatedTarget (a frame, a blank spot) is read on the next turn.
        function drawerLostFocus(next) {
            if (!isMenuDrawerMode() || !isMenuDrawerOpen() || !next || (next === document.body) || (next.nodeType !== 1)) {
                return;
            }

            if (next.closest('.software-sidebar') || next.closest('[data-pg-menu-hold]') || (next.id === 'menu_mode_toggle')) {
                return;
            }

            closeMenuDrawer();
        }

        $('.software-sidebar').on('focusout', function (e) {
            var next = e.relatedTarget;

            if (next) {
                drawerLostFocus(next);
            } else {
                window.setTimeout(function () {
                    drawerLostFocus(document.activeElement);
                }, 0);
            }
        });

        // A menu of the header opens over the screen below it, and the drawer
        // is drawn above the header's layer: left open, it would cover the
        // account sheet on a phone. Opening any of them puts the drawer away.
        pgUI.menu.on('#header', 'show', function () {
            if (isMenuDrawerOpen()) {
                closeMenuDrawer();
            }
        });
    }

    // Keep the two modes consistent when the viewport crosses the breakpoint.
    function initResizeCollapseHandler() {
        var wasDrawerMode = null;

        function syncMenuMode() {
            var drawerMode = isMenuDrawerMode();
            if (drawerMode === wasDrawerMode) {
                return;
            }
            wasDrawerMode = drawerMode;

            closeMenuDrawer();

            if (drawerMode) {
                // Entering drawer mode: set the wide-screen state aside without
                // touching the cookie, so the desktop preference survives.
                $('#menu, .software-container').removeClass('expanded');
                $('.software-container').removeClass('menu-hidden');
                paintMenuToggle();
            } else {
                // Back to the wide screen: whatever the user last chose there
                // (for this kind of screen).
                paintMenuState(savedMenuState());
            }

            settleMenuPanel();
        }

        $(window).on('load resize orientationchange', syncMenuMode);
        syncMenuMode();
    }

    /*
     * Panels of the main menu (output_menu(), pg_menu_panel()).
     *
     * A row with several entries carries data-pg-menu-panel. A plain click on
     * it shows that panel in place of the rows; the arrow at the panel's head,
     * or Escape, brings the rows back. A click with a modifier key or the
     * middle button is left to the browser, so the row's own screen still
     * opens in a new tab, and the right mouse button gets the browser's own
     * menu: the rows no longer have a menu of their own there.
     *
     * The collapsed rail has no room for a panel, so there the click widens
     * the rail over the content (.pg-menu-peek) for as long as the panel is
     * open; the way back, Escape, a click outside or focus leaving the menu
     * put it back to the rail.
     *
     * While the menu shows its labels the open panel is kept for this tab
     * (sessionStorage), and the next page opens it again when it is about
     * that page -- one of its entries is the page, or its row is the page's
     * row -- so a panel stays open while its entries are followed.
     */
    function storeMenuPanel(id) {
        try {
            if (id) {
                window.sessionStorage.setItem(MENU_PANEL_KEY, id);
            } else {
                window.sessionStorage.removeItem(MENU_PANEL_KEY);
            }
        } catch (e) {
            // Storage refused (private mode, a full quota): the panel is simply not kept.
        }
    }

    function storedMenuPanel() {
        try {
            return window.sessionStorage.getItem(MENU_PANEL_KEY) || '';
        } catch (e) {
            return '';
        }
    }

    // The collapsed rail of a wide screen (hidden counts too): the one state
    // with no room for a panel unless the rail is widened.
    function isMenuRail() {
        return !isMenuDrawerMode() && !$('#menu').hasClass('expanded');
    }

    function isMenuPeek() {
        return $('.software-container').hasClass('pg-menu-peek');
    }

    // aria-expanded on the row and the entries whose panels are open: the
    // open panel, and the panel holding the entry it was opened from.
    function paintMenuPanelRows(menu, panel) {
        var open = [];
        var guard = 0;

        while (panel && (guard++ < 4)) {
            open.push(panel.id);

            var entry = document.getElementById(panel.getAttribute('data-pg-menu-row'));

            panel = (entry && (typeof entry.closest === 'function')) ? entry.closest('.pg-menu-panel') : null;
        }

        Array.prototype.forEach.call(menu.querySelectorAll('[data-pg-menu-panel]'), function (row) {
            row.setAttribute('aria-expanded', (open.indexOf(row.getAttribute('data-pg-menu-panel')) !== -1) ? 'true' : 'false');
        });
    }

    function openMenuPanel(panel, options) {
        var menu = document.getElementById('menu');

        if (!menu || !panel) {
            return;
        }

        options = options || {};

        Array.prototype.forEach.call(menu.querySelectorAll('.pg-menu-panel.is-open'), function (open) {
            if (open !== panel) {
                open.classList.remove('is-open');
            }
        });

        menu.classList.toggle('pg-menu-animate', !!options.animate);
        // Back to a first-level panel it comes in from the left, like the rows.
        menu.classList.toggle('pg-menu-returning', !!options.back);
        panel.classList.add('is-open');
        menu.classList.add('pg-menu-drilled');
        paintMenuPanelRows(menu, panel);

        if (isMenuRail()) {
            $('.software-container').addClass('pg-menu-peek');
            storeMenuPanel('');
        } else {
            storeMenuPanel(panel.id);
        }

        var body = menu.querySelector('.menu-body');

        if (body) {
            body.scrollTop = 0;
        }

        if (options.focus) {
            var back = panel.querySelector('[data-pg-menu-back]');

            if (back) {
                back.focus({ preventScroll: true });
            }
        }

        // For a panel a script fills when it is first shown (the Workspace
        // list, workspace_side.js).
        if (typeof window.CustomEvent === 'function') {
            panel.dispatchEvent(new CustomEvent('pg:menu-panel-open', { bubbles: true }));
        }
    }

    // Back one step: from a second-level panel to the panel that holds the
    // entry it was opened from, from a first-level panel to the rows.
    function backMenuPanel(options) {
        var menu = document.getElementById('menu');
        var open = menu ? menu.querySelector('.pg-menu-panel.is-open') : null;
        var entry = open ? document.getElementById(open.getAttribute('data-pg-menu-row')) : null;
        var parent = (entry && (typeof entry.closest === 'function')) ? entry.closest('.pg-menu-panel') : null;

        options = options || {};

        if (!parent) {
            closeMenuPanel(options);
            return;
        }

        openMenuPanel(parent, { animate: options.animate, back: true });

        if (options.focus) {
            entry.focus();
        }
    }

    function closeMenuPanel(options) {
        var menu = document.getElementById('menu');

        if (!menu) {
            return;
        }

        options = options || {};

        var open = menu.querySelector('.pg-menu-panel.is-open');

        menu.classList.toggle('pg-menu-animate', !!options.animate);
        menu.classList.remove('pg-menu-drilled', 'pg-menu-returning');
        paintMenuPanelRows(menu, null);
        $('.software-container').removeClass('pg-menu-peek');
        storeMenuPanel('');

        if (open) {
            open.classList.remove('is-open');

            if (options.focus) {
                var row = document.getElementById(open.getAttribute('data-pg-menu-row'));

                if (row) {
                    row.focus();
                }
            }
        }
    }

    // After the menu changed how it shows: the widened rail belongs to the
    // collapsed rail alone, and a rail that is not widened has no room for a
    // panel. A panel the labels now have room for is kept for the tab again.
    function settleMenuPanel() {
        var menu = document.getElementById('menu');

        if (!menu) {
            return;
        }

        if (!isMenuRail() || $('.software-container').hasClass('menu-hidden')) {
            $('.software-container').removeClass('pg-menu-peek');
        }

        if (!menu.classList.contains('pg-menu-drilled')) {
            autoMenuPanel(menu);
            return;
        }

        if (isMenuRail() && !isMenuPeek()) {
            closeMenuPanel({});
        } else if (!isMenuRail()) {
            var open = menu.querySelector('.pg-menu-panel.is-open');

            if (open) {
                storeMenuPanel(open.id);
            }
        }
    }

    // A panel that opens by itself on the screens it is about (the
    // Workspace list on the workspace's own screens, which print
    // data-pg-menu-auto in it): whenever the menu shows its labels and no
    // other panel is open. The rail has no room for it.
    function autoMenuPanel(menu) {
        if (!menuPanelsReady || !menu || isMenuRail() || menu.classList.contains('pg-menu-drilled')) {
            return;
        }

        var marker = menu.querySelector('.pg-menu-panel [data-pg-menu-auto]');
        var panel = marker ? marker.closest('.pg-menu-panel') : null;

        if (panel) {
            openMenuPanel(panel, {});
        }
    }

    // The visible words of an entry: the Calendars entry draws the day of
    // the month as svg text, which is not part of its name.
    function menuEntryLabel(link) {
        var text = '';

        // An entry drawn with counts and controls inside it names itself.
        if (link.hasAttribute('data-pg-menu-label')) {
            return link.getAttribute('data-pg-menu-label').replace(/\s+/g, ' ').trim();
        }

        Array.prototype.forEach.call(link.childNodes, function (node) {
            if (node.nodeType === 3) {
                text += node.nodeValue;
            } else if ((node.nodeType === 1) && (node.tagName.toLowerCase() !== 'svg')) {
                text += node.textContent;
            }
        });

        return text.replace(/\s+/g, ' ').trim();
    }

    // How closely an entry's address is the page's own: -1 for another page;
    // otherwise every parameter of the entry is on the page with the same
    // value, the more of them the closer, and the same set closest of all.
    function menuEntryScore(link, here, params, paramCount) {
        var url;

        try {
            url = new URL(link.getAttribute('href'), here.href);
        } catch (e) {
            return -1;
        }

        if ((url.origin !== here.origin) || (url.pathname !== here.pathname)) {
            return -1;
        }

        var own = 0;
        var matched = true;

        url.searchParams.forEach(function (value, key) {
            own++;

            if (params.get(key) !== value) {
                matched = false;
            }
        });

        if (!matched) {
            return -1;
        }

        // A link to a section of the page (#pgset-...) is the page only when
        // the address names that section.
        if (url.hash && (url.hash !== here.hash)) {
            return -1;
        }

        return (own * 2) + ((own === paramCount) ? 1 : 0) + (url.hash ? 1 : 0);
    }

    // Marks in each panel the entry that is the current page and writes its
    // name as the hint of the panel's row. The stylesheet shows the hint on
    // the active row only, so it follows .active when workspace.js moves it
    // between the Workspace row and a pinned My Notes row.
    function markMenuCurrent(menu) {
        var here = window.location;
        var params = new URLSearchParams(here.search);
        var paramCount = 0;

        // Run again when only the #section changes: the marks of the last run go first.
        Array.prototype.forEach.call(menu.querySelectorAll('.pg-menu-current'), function (link) {
            link.classList.remove('pg-menu-current');
            link.removeAttribute('aria-current');
        });

        Array.prototype.forEach.call(menu.querySelectorAll('.pg-menu-hint'), function (hint) {
            hint.textContent = '';
        });

        params.forEach(function () {
            paramCount++;
        });

        Array.prototype.forEach.call(menu.querySelectorAll('.pg-menu-panel'), function (panel) {
            var best = null;
            var bestScore = -1;

            Array.prototype.forEach.call(panel.querySelectorAll('.pg-menu-panel-body a[href]:not([data-pg-menu-skip])'), function (link) {
                var score = menuEntryScore(link, here, params, paramCount);

                if (score > bestScore) {
                    best = link;
                    bestScore = score;
                }
            });

            if (!best) {
                return;
            }

            best.classList.add('pg-menu-current');
            best.setAttribute('aria-current', 'page');

            var row = document.getElementById(panel.getAttribute('data-pg-menu-row'));

            if (!row) {
                return;
            }

            var hint = row.querySelector('.pg-menu-hint');
            var name = row.querySelector('.list-group-text');
            var label = menuEntryLabel(best);

            if (hint && (label !== '') && (!name || (label.toLocaleLowerCase() !== name.textContent.trim().toLocaleLowerCase()))) {
                hint.textContent = label;
            }
        });
    }

    function initMenuPanels() {
        var menu = document.getElementById('menu');
        var sidebar = menu ? menu.closest('.software-sidebar') : null;

        if (!menu || !menu.querySelector('.pg-menu-panel')) {
            return;
        }

        markMenuCurrent(menu);

        menu.addEventListener('click', function (e) {
            var target = e.target;

            if (!target || (typeof target.closest !== 'function')) {
                return;
            }

            if (target.closest('[data-pg-menu-back]')) {
                e.preventDefault();
                backMenuPanel({ animate: true, focus: true });
                return;
            }

            var row = target.closest('a[data-pg-menu-panel]');

            if (row) {
                if (e.defaultPrevented || (e.button !== 0) || e.ctrlKey || e.metaKey || e.shiftKey || e.altKey) {
                    return;
                }

                var panel = document.getElementById(row.getAttribute('data-pg-menu-panel'));

                if (panel) {
                    e.preventDefault();
                    openMenuPanel(panel, { animate: true, focus: true });
                }

                return;
            }

            // An entry that leaves this page in place (a new tab, a window of
            // its own, a #section of this very page) puts the widened rail
            // back; one that loads a page here takes the rail with it anyway.
            var entry = target.closest('.pg-menu-panel-body a');

            if (entry && isMenuPeek()) {
                var href = entry.getAttribute('href');
                var samePage = !!(href && entry.href && (entry.href.split('#')[0] === window.location.href.split('#')[0]));

                if (!href || (entry.getAttribute('target') === '_blank') || samePage) {
                    closeMenuPanel({});
                }
            }
        });

        // Escape inside the menu goes back one step, and stops there: a
        // second Escape is the drawer's.
        menu.addEventListener('keydown', function (e) {
            // A search box in a panel clears itself on Escape first.
            if (e.defaultPrevented) {
                return;
            }

            if (((e.key === 'Escape') || (e.key === 'Esc')) && menu.classList.contains('pg-menu-drilled')) {
                e.stopPropagation();
                backMenuPanel({ animate: true, focus: true });
            }
        });

        document.addEventListener('keydown', function (e) {
            if (((e.key === 'Escape') || (e.key === 'Esc')) && isMenuPeek()) {
                closeMenuPanel({});
            }
        });

        // What counts as inside the widened rail: the sidebar, and a layer a
        // panel opened over the page (a list's right-click menu, a tour's
        // bubble), which marks itself data-pg-menu-hold.
        function insideMenu(node) {
            if (!node || (node.nodeType !== 1)) {
                return false;
            }

            return !!((sidebar && sidebar.contains(node)) || (node.closest && node.closest('[data-pg-menu-hold]')));
        }

        document.addEventListener('click', function (e) {
            if (!isMenuPeek() || insideMenu(e.target)) {
                return;
            }

            // A control that redrew itself (a section of a list opened or
            // closed) has left the document by now; the path of the click
            // was taken when it started, and still holds the sidebar.
            var path = (typeof e.composedPath === 'function') ? e.composedPath() : [];

            for (var i = 0; i < path.length; i++) {
                if ((path[i] === sidebar) || ((path[i].nodeType === 1) && path[i].hasAttribute('data-pg-menu-hold'))) {
                    return;
                }
            }

            closeMenuPanel({});
        });

        // Focus leaving the menu puts the widened rail back. Focus that goes
        // into a frame (a rich text editor) has no relatedTarget and its clicks
        // and keys never reach this document, so the frame is looked for on
        // the next turn, when it has become the active element.
        function peekLostFocus() {
            var active = document.activeElement;

            if (isMenuPeek() && active && (active !== document.body) && sidebar && !insideMenu(active)) {
                closeMenuPanel({});
            }
        }

        menu.addEventListener('focusout', function (e) {
            if (!isMenuPeek()) {
                return;
            }

            if (!e.relatedTarget) {
                window.setTimeout(peekLostFocus, 0);
            } else if (sidebar && !insideMenu(e.relatedTarget)) {
                closeMenuPanel({});
            }
        });

        window.addEventListener('blur', function () {
            if (isMenuPeek()) {
                window.setTimeout(peekLostFocus, 0);
            }
        });

        // A #section link of this page (Site Settings) changes no page, so
        // the current entry is found again.
        window.addEventListener('hashchange', function () {
            markMenuCurrent(menu);
        });

        // The way in for a script that draws into a panel (the Workspace
        // list): open, close and the current entry again after the address
        // changed without a page load (history.replaceState()).
        window.pgMenu = {
            // Asked from a click on the page (a button of the screen), it
            // opens once that click is over: the click would otherwise reach
            // the closers above (a click outside the widened rail, a tap on
            // the page behind the drawer) and shut it again at once.
            openPanel: function (id, options) {
                var panel = document.getElementById(id);

                if (!panel || !panel.classList.contains('pg-menu-panel')) {
                    return;
                }

                options = options || {};

                window.setTimeout(function () {
                    if (isMenuDrawerMode() && !isMenuDrawerOpen()) {
                        openMenuDrawer();
                    }

                    openMenuPanel(panel, { animate: !panel.classList.contains('is-open'), focus: !!options.focus });
                }, 0);
            },
            // Done with the panel after an entry was answered on this page:
            // the widened rail and the phone's drawer step aside.
            dismiss: function () {
                if (isMenuPeek()) {
                    closeMenuPanel({});
                }

                if (isMenuDrawerMode() && isMenuDrawerOpen()) {
                    closeMenuDrawer();
                }
            },
            refreshCurrent: function () {
                markMenuCurrent(menu);
            },
            isRail: function () {
                return isMenuRail();
            }
        };

        menuPanelsReady = true;

        // The panel this tab had open, when the page is one it is about;
        // otherwise the panel this screen opens by itself, if any.
        var stored = storedMenuPanel();
        var panel = (stored !== '') ? document.getElementById(stored) : null;
        var row = panel ? document.getElementById(panel.getAttribute('data-pg-menu-row')) : null;

        if (panel && row && panel.classList.contains('pg-menu-panel') && !isMenuRail() && (panel.querySelector('.pg-menu-current') || row.classList.contains('active'))) {
            openMenuPanel(panel, {});
            return;
        }

        if (stored !== '') {
            storeMenuPanel('');
        }

        autoMenuPanel(menu);
    }

    // The Dashboard entry's icon is a clock and the Calendars entry's icon
    // shows the day of the month (pg_menu_icon()). The server sets both from
    // the site's time zone; from there they are moved on by how long this page
    // has been open, not by the browser's clock, which may be in another zone.
    function initMenuClock() {
        var clock = document.querySelector('#menu svg[data-pg-clock]');
        if (!clock) {
            return;
        }

        var hour = clock.querySelector('.pg-mi-hour');
        var minute = clock.querySelector('.pg-mi-minute');
        var day = document.querySelector('#menu svg[data-pg-day] .pg-mi-date');
        var start = parseInt(clock.getAttribute('data-pg-clock'), 10) || 0;
        var date = (clock.getAttribute('data-pg-clock-date') || '').split('-');
        var opened = Date.now();

        function draw() {
            // Seconds since the site's midnight on the day the page was drawn.
            var now = start + Math.floor((Date.now() - opened) / 1000);
            var seconds = now % 86400;

            if (hour) {
                hour.setAttribute('transform', 'rotate(' + ((seconds % 43200) / 120).toFixed(2) + ' 12 12)');
            }
            if (minute) {
                minute.setAttribute('transform', 'rotate(' + ((seconds % 3600) / 10).toFixed(2) + ' 12 12)');
            }

            // Past midnight the calendar's day moves on with the clock. UTC
            // arithmetic, so a daylight-saving switch cannot shift the date.
            if (day && (date.length === 3)) {
                var text = String(new Date(Date.UTC(+date[0], +date[1] - 1, +date[2] + Math.floor(now / 86400))).getUTCDate());

                if (day.textContent !== text) {
                    day.textContent = text;
                }
            }
        }

        draw();

        // The minute hand turns a degree every ten seconds.
        window.setInterval(draw, 10000);

        // Timers are throttled in a background tab; catch up on return.
        document.addEventListener('visibilitychange', function () {
            if (!document.hidden) {
                draw();
            }
        });
    }



    // Panel languages with a file in assets/lib/DataTables/i18n/.
    var DATATABLES_LANGUAGES = { tr: true };



    /*
    Select All Checker
    An Example:
    <div class="multiselect-checkbox-container">
        <input type="checkbox" id="checker" class="multiselect-checkbox-checker" />
        <label for="checker">Select/Unselect All</label><br/>
        <input type="checkbox" class="multiselect-checkbox" id="checkbox-1"/>
        <label for="checkbox-1">Checkbox 1</label><br/>
        <input type="checkbox" class="multiselect-checkbox" id="checkbox-2"/>
        <label for="checkbox-2">Checkbox 2</label><br/>
        <input type="checkbox" class="multiselect-checkbox" id="checkbox-3"/>
        <label for="checkbox-3">Checkbox 3</label><br/>
        <input type="checkbox" class="multiselect-checkbox" id="checkbox-4"/>
        <label for="checkbox-4">Checkbox 4</label><br/>
    </div>
    */
    if ($(".multiselect-checkbox-container").length > 0) {
        $(".multiselect-checkbox-container").each(function () {
            // create or increase id number and set an id for this container
            if (typeof $i == 'undefined') {
                $i = 0;
            } else {
                $i++;
            }
            $(this).attr('multiselect-id', $i);
            $container_id = $(this).attr('multiselect-id');

            $(this).find('.multiselect-checkbox-checker').multiselectCheckbox({
                checkboxes: ".multiselect-checkbox-container[multiselect-id=" + $container_id + "] .multiselect-checkbox",
                syncEvent: "checkbox",
                checkedClassName: "selected",
                checkedKeyDataAttributeName: "jquery-multi-select-checkbox-checked-key"
            });
        });

        // The plugin sets the "Select All" switch to all or nothing and fires
        // change only for that switch, so the mixed state is read back from
        // the boxes on every change in a list, and once for lists that load
        // half-ticked (a saved event or filter).
        $(document).on('change', '.multiselect-checkbox-container', function () {
            pgSyncCheckboxList(this);
        });

        pgSyncCheckboxLists();
    }


    // DataTables' Bootstrap 5 integration names v5 classes for the controls
    // it builds; the panel runs Bootstrap 6.
    if ($.fn.dataTable) {
        $.extend(true, $.fn.dataTable.ext.classes, {
            length: { select: 'form-control form-control-sm' },
            layout: {
                row: 'row mt-3 justify-content-between',
                cell: 'md:d-flex justify-content-between align-items-center',
                start: 'dt-layout-start md:col-auto me-auto',
                end: 'dt-layout-end md:col-auto ms-auto',
                full: 'dt-layout-full md:col'
            }
        });
    }

    // The Buttons integration draws the column visibility list as a v5
    // dropdown; Bootstrap 6 styles it as a menu. dropdown-menu stays, since
    // DataTables' own rule for it keeps the list in place instead of the
    // menu's absolute position.
    if ($.fn.dataTable && $.fn.dataTable.Buttons) {
        $.extend(true, $.fn.dataTable.Buttons.defaults.dom.collection, {
            container: { content: { className: 'dropdown-menu menu show' } },
            button: { liner: { className: 'dropdown-item menu-item' } }
        });
    }

    var options = {};
    // The panel language's DataTables 2 texts from the local copy; English
    // is DataTables' own. The CDN's 1.13.6 Turkish file was loaded for every
    // language, lacked the 2.x keys ("All", pagination names) and held the
    // table back until a third party answered.
    if (DATATABLES_LANGUAGES[software_system_language]) {
        options["language"] = {
            url: path + software_directory + "/assets/lib/DataTables/i18n/" + software_system_language + ".json"
        };
    }

    options["stateSave"] = false;
    options["serverside"] = false;
    options["bInfo"] = false;
    options["bProcessing"] = true;
    options["ordering"] = false;
    options["bSort"] = false;
    options["deferRender"] = true;
    options["scroller"] = false;
    options["select"] = false;
    options["processing"] = false;
    options["scrollX"] = true;
    options["fixedHeader"] = {
        headerOffset: $("#header").outerHeight()
    }


    options["drawCallback"] = function (settings) {
        // Reset checkbox states and row selections
        $("table.chart.chart:not(.datatable-restricted-mode) .select-all input[type=checkbox]").prop("checked", false).removeClass('selected');
        $("table.chart.chart #select_all").prop("checked", false);
        chart_checkbox_state_change();
        $("table.chart:not(.datatable-restricted-mode) tr.selected").removeClass('selected');

        // Hide pagination if only one page exists
        const api = $.fn.dataTable.Api(settings);
        const pageInfo = api.page.info();
        const pagination = $(api.table().container()).find('div.dt-paging');

        if (pageInfo.pages <= 1) {
            pagination.css('display', 'none');
        } else {
            pagination.css('display', 'block'); // or 'block' depending on your layout
        }
    };



    options["fnInitComplete"] = function () {
        datatable.columns.adjust();
        $('table.chart').show();


        $("#select_all").multiselectCheckbox({
            checkboxes: "tr:not(.unselectable) .select-all input",
            sync: "table.chart tbody tr:not(.child):not(.unselectable)",
            syncEvent: "click",
            checkedClassName: "selected",
            checkedKeyDataAttributeName: "jquery-multi-select-checkbox-checked-key",
            onNotAllChecked: function (selectedMap) {
                chart_checkbox_state_change();
            },
            onAllChecked: function (selectedMap) {
                chart_checkbox_state_change();
            },
            onAllUnchecked: function (selectedMap) {
                chart_checkbox_state_change();
            }
        });
        $("table.dataTable #select_all").prop("checked", false);
        datatable.columns.adjust();

        // A list paged on the server would ask for the same page again.
        // false keeps the page: a bare draw() puts the list back on page 1
        // and would throw away the page restored from history.state.
        if (!options.serverSide) {
            datatable.draw(false);
        }

        // DataTables names its pager landmark "pagination" in English
        // whatever the language; the panel language's file names it.
        $(datatable.table().container()).find('div.dt-paging nav').attr('aria-label', datatable.i18n('aria.paging', 'Pagination'));
        $('table.dataTable tbody tr td.dataTables_empty').parent('tr').addClass('unselectable');
    };



    options["dom"] = "<'container-fluid p-5 pt-7'<'row'<'col-12 md:col-6 text-center md:text-start'li><'col-12 md:col-6 text-center md:text-end'BRf>>>rt<'p-3 text-center'i><'p-3'p>";
    options["buttons"] = {
        buttons: [{
            text: lang('Reset'),
            className: "btn btn-sm bi-me-2 bi bi-arrow-clockwise",
            action: function (e, dt, node, config) {
                if (confirm(lang('Column Reorder and Column Visiblity reset?')) == true) {
                    datatable.colReorder.reset();
                    datatable.columns().visible(true, true);
                }

            }
        }, {
            extend: "colvis",
            className: "pg-menu-caret btn btn-sm bi-me-2 bi bi-layout-three-columns",
            text: lang('Column Visiblity'),
            collectionLayout: "fixed two-column",
            columns: ":not(.noVis)",
            columnText: function (dt, idx, title) {
                title = title.replace("unfold_more", "");
                title = title.replace("keyboard_arrow_down", "");
                title = title.replace("keyboard_arrow_up", "");
                return (idx + 1) + ': ' + title;
            },
        }],
        dom: {
            container: {
                tag: 'div',
                className: 'dtable-button-container p-3'
            },
            button: {
                tag: 'a',
                className: ' btn btn-sm '
            }
        }
    };


    if ($('table.chart').length) {
        if ($('table.chart').hasClass('datatable-restricted-mode')) {
            options["bPaginate"] = false;
            options["paging"] = false;
            options["bLengthChange"] = false;
            options["searching"] = false;
        } else {
            options["bPaginate"] = true;
            options["paging"] = true;
            options["pageLength"] = 100;
            options["searching"] = true;
            options["lengthMenu"] = [
                [10, 25, 50, 100, 1000, -1],
                [10, 25, 50, 100, 1000, "Unlimited"],
            ];
        }
        if ($('table.chart').hasClass('datatable-no-info')) {
            options["info"] = false;
        } else {
            options["info"] = true;
        }

        // A list that names a panel action is paged on the server.
        if ($('table.chart[data-pg-list-action]').length === 1) {
            pgListServerOptions(options, $('table.chart[data-pg-list-action]'));
        }

        // A list comes back on the page, length and search it was left on:
        // panel pages are sent no-store, so Back loads the screen again.
        // Every table.chart shares these options, so only a single list is
        // restored. DataTables' stateSave stays off: it would also keep the
        // column order and visibility the Reset button starts afresh.
        var pgListKey = null;
        if ($('table.chart').length === 1 && options["paging"]) {
            pgListKey = $('table.chart').attr('id') || '0';
            var pgListKept = pgListState(pgListKey);
            if (pgListKept && options["lengthMenu"][0].indexOf(pgListKept.length) !== -1) {
                options["displayStart"] = pgListKept.start;
                options["pageLength"] = pgListKept.length;
                options["search"] = { search: pgListKept.search };
            }
        }

        var datatable = $("table.chart").DataTable(options);
        datatable.buttons().container().appendTo($(".chart-buttons"));
        // Each draw follows a page, length or search change, after
        // DataTables has settled the start (a new search goes to page 1).
        if (pgListKey !== null) {
            datatable.on('draw.dt', function () {
                var info = datatable.page.info();
                pgListKeep(pgListKey, { start: info.start, length: info.length, search: datatable.search() });
            });
        }
        $(window).on("resize", function () {
            datatable.columns.adjust();
        });
    }
    //software search no longer available, if there is query paramater in url, search it in datatable, and remove query paramater from url.
    if (window.location.href.indexOf('?query=') > 0 || window.location.href.indexOf('&query=') > 0) {
        if ($("table.chart").length > 0) {
            const query = new URLSearchParams(window.location.search).get('query');
            let params = new URLSearchParams(location.search);
            params.delete('query');
            history.replaceState(history.state, '', '?' + params + location.hash);
            datatable.search(query).draw();
        }
    }

    //#notifications
    $('#notification_toggle').on('click', function () {
        if (pgUI.menu.isOpen(this)) {
            get_notifications(true);
        } else {
            $('#notifications').scrollTop(0);
        }
    });
    // Bootstrap 6 fires the menu events on the toggle, not on the .menu box.
    //shown #notifications
    pgUI.menu.on('#notification_toggle', 'shown', function () {
        $('#notification_toggle').addClass('show');
        get_notifications(true);
    });
    //hidden #notifications
    pgUI.menu.on('#notification_toggle', 'hidden', function () {
        $('#notification_toggle').removeClass('show');
        $('#notifications').scrollTop(0);
    });

    pg_push_open_requested();

    pg_push_init();

    pg_quick_note_init();

    pg_contact_quick_init();

    pg_account_menu_init();

    pg_shortcuts_init();

    check_unread_notifications('onload_check');
    setInterval(function () {
        //this interval is auto check for notifications.
        // we set this default 30 sec(30 * 1000).
        check_unread_notifications('scheduled_check');
    }, 20 * 1000); // 60 * 1000 = 1min.



    // Tooltips and the collapse pairs, drawn over the markup this page arrived
    // with. Both helpers take a scope because the settings modal runs them
    // again over a pane that arrives later; see pgInitInjected().
    pgBindTitlePopovers(document);
    pgSyncCollapseSwitchers(document);
    pgBindRanges(document);

    $(document).on("change", "select.collapse-if-selected", function () {
        var target = $(this).attr("data-bs-target");
        var open = ($(this).val() != '');
        // toggle: false, or a new instance would toggle on creation.
        $(target).each(function () {
            pgUI.collapse.get(this, { toggle: false });
            pgUI.collapse[open ? 'show' : 'hide'](this);
        });
    });
    $(document).on("click change", "input.collapse-switcher", function () {
        var target = $(this).attr("data-bs-target");
        if ($("input[name=" + $(this).attr("name") + "]").length > 1) {
            var multi_switcher_this_id = $(this).attr("id");
            $("input[name=" + $(this).attr("name") + "]").each(function () {
                if ($(this).attr("id") != multi_switcher_this_id) {
                    $($(this).attr("data-bs-target")).toggle(false);
                    pgCollapseSwitcherShow($(this).attr("data-bs-target"), false);
                }
            });
        }
        var returned;
        if ($(target).hasClass('show-reverse')) {
            if (this.checked == true) {
                returned = false;
            } else {
                returned = true;
            }
        } else {
            returned = this.checked;
        }
        $(target).toggle(returned);
        pgCollapseSwitcherShow(target, returned);
    });

    /* The e-document card's "test the connection".
       The settings pane posts itself over AJAX, so the browser never acts on
       a submit button's formaction: the pane's own save swallowed the click
       and the operator was told the settings were saved instead of being
       given an answer. The button is a plain button now and asks the
       endpoint itself -- the provider that is picked and that provider's
       boxes as they stand -- then prints the answer under the button. No new
       tab: a popup is the one part of this a browser is free to refuse, and
       an answer nobody sees is the fault all over again.
       Nothing is saved by it; that is still the pane's Save.
       The Paraşüt card's Connect button is the same handler with another
       endpoint and another box prefix (data-pg-fields): it posts the
       parasut_* boxes and prints the answer in the result box nearest to
       it, the one in its own block. */
    $(document).on("click", "button.pg-edoc-test", function (event) {

        event.preventDefault();

        var $button = $(this);
        var action = $button.attr("data-pg-action");
        var prefix = $button.attr("data-pg-fields") || "edoc_";

        if (!action || $button.prop("disabled")) {
            return;
        }

        var $card = $button.closest(".pg-set-card");
        var $result = $button.closest(".col-12, .pg-f-md").find(".pg-edoc-test-result").first();
        var token = $("#pg_settings_modal_token").val();
        var data = new FormData();

        if (!$result.length) {
            $result = $card.find(".pg-edoc-test-result").first();
        }

        if (typeof token === "undefined") {
            token = $button.closest("form").find("input[name=token]").val();
        }

        data.append("token", token || "");
        data.append("format", "json");

        $card.find("[name^='" + prefix + "']").each(function () {

            if (this.disabled || (((this.type === "checkbox") || (this.type === "radio")) && !this.checked)) {
                return;
            }

            data.append(this.name, $(this).val());
        });

        $button.prop("disabled", true);
        $result.html('<div class="fg-secondary small"><span class="spinner-border spinner-border-sm me-3" aria-hidden="true"></span><span class="visually-hidden">' + h(pgLang('Please Wait')) + '</span></div>');

        window.fetch(action, { method: "POST", body: data, credentials: "same-origin" })
            .then(function (response) {
                return response.ok ? response.json() : Promise.reject(new Error(String(response.status)));
            })
            .then(function (answer) {

                var $alert = $("<div>").addClass("alert theme-" + (answer.tone || "secondary") + " d-flex align-items-start gap-3 mb-1");

                $alert.append($("<i>").addClass("bi " + (answer.icon || "bi-info-circle") + " fs-lg"));

                var $body = $("<div>");

                $.each(answer.lines || [], function (index, line) {
                    $body.append($("<div>").text(line));
                });

                $alert.append($body);
                $result.empty().append($alert).append($("<div>").addClass("form-text").text(answer.note || ""));

                // What the endpoint saved goes back into the open card (the
                // Paraşüt company number, emptied code and secret boxes), so
                // the pane's own Save writes the same values again instead of
                // the ones the page was drawn with.
                $.each(answer.fill || {}, function (name, value) {
                    $card.find("[name='" + name + "']").val(value);
                });

                if (answer.status && answer.status.text) {
                    var $status = $card.find(".pg-parasut-status").first();
                    var tone = answer.status.tone || "secondary";

                    // The box is coloured by theme-<tone> (Bootstrap 6), not alert-*.
                    $status.removeClass(function (index, classes) {
                        return (classes.match(/(^|\s)theme-\S+/g) || []).join(" ");
                    }).addClass("theme-" + tone);
                    $status.find("i").attr("class", "bi " + (tone === "success" ? "bi-check-circle" : "bi-plug"));
                    $status.find("span").text(answer.status.text);
                }
            })
            .catch(function (error) {
                $result.html($("<div>").addClass("alert theme-danger mb-1").text("HTTP " + error.message));
            })
            .then(function () {
                $button.prop("disabled", false);
            });
    });

    /* The Paraşüt authorization link follows the Client ID box as it is
       typed, so an operator can authorize before the first save; with no
       client id there is nothing to open and the link is disabled. The
       redirect is the out-of-band one the API documentation names. */
    $(document).on("input change", "#parasut_client_id", function () {

        var $link = $(this).closest(".pg-set-card").find("a.pg-parasut-authorize").first();
        var base = $link.attr("data-pg-base");
        var clientId = $.trim($(this).val());

        if (!$link.length || !base) {
            return;
        }

        if (clientId === "") {
            $link.addClass("disabled").attr("href", "");
            return;
        }

        $link.removeClass("disabled").attr("href", base + "?client_id=" + encodeURIComponent(clientId)
            + "&redirect_uri=" + encodeURIComponent("urn:ietf:wg:oauth:2.0:oob") + "&response_type=code");
    });

    if ($("input[name=convert_to_metric_system]").length > 0) {
        var unit_lb_label = $("input[name=weight]").parent().find("label.unit");
        var unit_lb_label_text = unit_lb_label.text();
        var unit_in_label = $("input[name=length]").parent().find("label.unit");
        var unit_in_label_text = unit_in_label.text();
    }
    // A dimension as typed on the product screen, with either decimal
    // separator (the server reads "12,5" and "12.5" alike); no digits is 0.
    function pgProductFigure(text) {
        var n = parseFloat(String(text).replace(/\s+/g, "").replace(",", "."));
        return isNaN(n) ? 0 : n;
    }
    // The converted figure to four places, the precision the columns hold.
    function pgProductRound(n) {
        return Math.round(n * 10000) / 10000;
    }
    $("input[name=convert_to_metric_system]").on("click", function () {
        var switcher = $(this);
        $($(this).attr("data-bs-target")).each(function () {
            switch ($(this).attr("id")) {
                case "weight":
                    var input = $("#" + $(this).attr("id"));
                    if (switcher.is(':checked')) {
                        input.val(pgProductRound(pgProductFigure(input.val()) * 0.45359237));
                        input.parent().find("label.unit").each(function () {
                            $(this).text("kg");
                        });
                    } else {
                        input.val(pgProductRound(pgProductFigure(input.val()) * 2.20462262185));
                        input.parent().find("label.unit").each(function () {
                            $(this).text(unit_lb_label_text);
                        });
                    }
                    break;
                case "length":
                case "width":
                case "height":
                    var input = $("#" + $(this).attr("id"));
                    if (switcher.is(':checked')) {
                        input.val(pgProductRound(pgProductFigure(input.val()) * 2.54));
                        input.parent().find("label.unit").each(function () {
                            $(this).text("cm");
                        });

                    } else {
                        input.val(pgProductRound(pgProductFigure(input.val()) * 0.393700787));
                        input.parent().find("label.unit").each(function () {
                            $(this).text(unit_in_label_text);
                        });
                    }
                    break;
            }
        });
    });

    $('.number-controls .minus').on("click", function () {
        var el = $(this).closest("div").find("input:not(.number-controls-disabled)");
        // we set veriable for min
        var min_val = 0;
        // if there is a min prop we use it for min value
        if (el.prop('min')) {
            min_val = el.prop('min');
        }
        // we set veriable for max
        var max_val = 9999999999999;
        // if there is a max prop we use it for min value
        if (el.prop('max')) {
            max_val = el.prop('max');
        }
        // Set new Value with increase
        var new_val = +el.val() - 1;
        // check if we disable minus button 
        if (min_val >= new_val) {
            $(this).addClass('disabled');
        } else {
            $(this).removeClass('disabled');
        }
        // check if we disable plus button
        if (max_val <= new_val) {
            $(this).closest("div").find(".plus").addClass('disabled');
        } else {
            $(this).closest("div").find(".plus").removeClass('disabled');
        }
        // if new value is bigger than min value use it else we use min value.
        if (new_val <= min_val) {
            el.val(min_val);
        } else {
            el.val(new_val);
        }
    });
    $('.number-controls .plus').on("click", function () {
        var el = $(this).closest("div").find("input:not(.number-controls-disabled)");

        // we set veriable for min
        var min_val = 0;
        // if there is a min prop we use it for min value
        if (el.prop('min')) {
            min_val = el.prop('min');
        }
        // we set veriable for max
        var max_val = 9999999999999;
        // if there is a max prop we use it for min value
        if (el.prop('max')) {
            max_val = el.prop('max');
        }
        // Set new Value with increase
        var new_val = +el.val() + 1;
        // check if we disable plus button 
        if (max_val <= new_val) {
            $(this).addClass('disabled');
        } else {
            $(this).removeClass('disabled');
        }
        // check if we disable minus button 
        if (min_val >= new_val) {
            $(this).closest("div").find(".minus").addClass('disabled');
        } else {
            $(this).closest("div").find(".minus").removeClass('disabled');
        }
        // if new value is smaller than max value use it else we use max value.
        if (max_val <= new_val) {
            el.val(max_val);
        } else {
            el.val(new_val);
        }
    });

    $(".show-password-btn").each(function () {
        $(this).on("click", function () {
            var input = $(this).siblings("input");
            if (input.prop("type") == "password") {
                $(this).addClass('bi-eye-slash').removeClass('bi-eye');
                input.prop("type", "text");
            } else {
                $(this).addClass('bi-eye').removeClass('bi-eye-slash');
                input.prop("type", "password");

            }
        });

    });

    if ($(".input-mask-key-code").length) {
        $(".input-mask-key-code").inputmask({
            mask: '9999-9999-9999-9999',
            placeholder: '****-****-****-****',
            showMaskOnHover: false,
            showMaskOnFocus: false
        });
    };





    // Return in Backup Name is caught on keydown by backups.php itself.
    $("#backup").click(function () {
        if (!$(this).prop("disabled")) {
            software_backup_start();
        }
    });

    // Backend Global Search
    $('#EnableSearch, .pg-search-enable').removeClass('d-none');

    (function () {
        var $modal = $('#SearchBox');
        var $input = $modal.find('input[name="query"]');
        var $results = $modal.find('#search_results');
        var $emptyBox = $modal.find('.search_results_empty_box');
        var $clearBtn = $modal.find('.clear');
        var timer, currentQuery = '', currentOffset = 0, loading = false;

        var cachedActions = null;

        // The palette runs inside the toolbar too, and that is an iframe laid
        // over the site being edited. An address opened without saying where
        // loads a whole admin screen into a fifty-pixel strip, with its own
        // toolbar inside it. The header and the menu drawer have always
        // answered this with target="_parent"; the chips here get the same
        // attribute, and the result rows, which navigate from script, walk the
        // parent window instead. (The notification list reads the address for
        // the same reason; the body class says it in one word.)
        var inToolbar = document.body.classList.contains('toolbar');

        function leave(url) {
            (inToolbar ? window.parent : window).location.href = url;
        }

        // Settings is a dialog and the toolbar prints none on purpose: that
        // header is a thin strip inside the frame of the site being edited,
        // and a dialog the size of the viewport has nowhere to open there. So
        // the parent is sent to the dashboard with the category named in the
        // fragment, which is what pgSettingsModal.fromHash() answers to on
        // arrival - the same shape pg_settings_return_url() writes for tools
        // that finish on another screen.
        function settingsElsewhere(href) {

            href = String(href || '');

            var hit = href.match(/(?:^|\/)settings_([a-z_]+)\.php(?:\?[^#]*)?(?:#([a-z0-9_-]+))?$/i);

            if (!hit) {
                return null;
            }

            var base = href.replace(/#.*$/, '').replace(/\/[^\/]*$/, '/');
            var pane = hit[1].toLowerCase();
            var card = hit[2] ? hit[2].toLowerCase() : '';

            return base + 'welcome.php#settings/' + pane + (card ? '/' + card : '');
        }

        function t(key) {
            var v = (typeof translate !== 'undefined') ? translate[key] : undefined;
            return (v !== undefined && v !== null) ? v : key;
        }

        // String() first: one term that arrives as a number would otherwise
        // stop the matcher, and with it every search typed into the box.
        function normalizeTR(s) {
            return String(s).toLowerCase()
                .replace(/\u011f/g, 'g').replace(/\u00fc/g, 'u').replace(/\u015f/g, 's')
                .replace(/\u0131/g, 'i').replace(/\u00f6/g, 'o').replace(/\u00e7/g, 'c');
        }

        function actionMatches(q, keys) {
            if (q.length < 1) return false;
            var qn = normalizeTR(q);
            for (var i = 0; i < keys.length; i++) {
                var k = normalizeTR(keys[i]);
                if (k.indexOf(qn) === 0 || qn.indexOf(k) === 0) return true;
            }
            return false;
        }

        var PAGE_ICONS = {
            'content': 'bi-file-earmark-text', 'catalog detail': 'bi-box-seam',
            'search results': 'bi-search', 'blog': 'bi-journal-text',
            'calendar': 'bi-calendar3', 'my account': 'bi-person',
            'membership entrance': 'bi-door-open', 'shopping cart': 'bi-cart',
            'checkout': 'bi-credit-card', 'order receipt': 'bi-receipt',
            'email a friend': 'bi-envelope', 'forgot password': 'bi-key',
            'custom form': 'bi-ui-checks'
        };
        var FILE_ICONS = {
            'css': 'bi-filetype-css', 'js': 'bi-filetype-js',
            'jpg': 'bi-file-earmark-image', 'jpeg': 'bi-file-earmark-image',
            'png': 'bi-file-earmark-image', 'gif': 'bi-file-earmark-image',
            'svg': 'bi-file-earmark-image', 'webp': 'bi-file-earmark-image',
            'pdf': 'bi-file-earmark-pdf', 'zip': 'bi-file-earmark-zip',
            'mp4': 'bi-file-earmark-play', 'mp3': 'bi-file-earmark-music'
        };

        function makeThumb(icon, color, imgSrc) {
            if (imgSrc) {
                return '<img src="' + path + imgSrc + '" class="srch-thumb flex-shrink-0" style="width:32px;height:32px;object-fit:cover;border-radius:6px;" alt="" onerror="this.style.display=\'none\'">';
            }
            return '<span class="bi ' + icon + ' ' + color + ' flex-shrink-0" style="font-size:1.15rem;width:32px;text-align:center;"></span>';
        }

        var TYPE_META = {
            page: { thumb: function (r) { return makeThumb(PAGE_ICONS[r.sub] || 'bi-file-earmark', 'fg-emphasis-primary', null); }, url: function (r) { return path + software_directory + '/edit_page.php?id=' + r.id; } },
            form: { thumb: function (_r) { return makeThumb('bi-ui-checks', 'fg-emphasis-primary', null); }, url: function (r) { return path + software_directory + '/edit_page.php?id=' + r.id; } },
            product: { thumb: function (r) { return makeThumb('bi-box-seam', 'fg-emphasis-success', r.image || null); }, url: function (r) { return path + software_directory + '/edit_product.php?id=' + r.id; } },
            product_group: { thumb: function (_r) { return makeThumb('bi-boxes', 'fg-emphasis-info', null); }, url: function (r) { return path + software_directory + '/edit_product_group.php?id=' + r.id; } },
            offer: { thumb: function (_r) { return makeThumb('bi-percent', 'fg-emphasis-warning', null); }, url: function (r) { return path + software_directory + '/edit_offer.php?id=' + r.id; } },
            menu: { thumb: function (_r) { return makeThumb('bi-menu-button', 'fg-secondary', null); }, url: function (r) { return path + software_directory + '/edit_menu.php?id=' + r.id; } },
            calendar: { thumb: function (_r) { return makeThumb('bi-calendar3', 'fg-secondary', null); }, url: function (r) { return path + software_directory + '/edit_calendar.php?id=' + r.id; } },
            email_campaign: { thumb: function (_r) { return makeThumb('bi-megaphone', 'fg-emphasis-danger', null); }, url: function (r) { return path + software_directory + '/edit_email_campaign_profile.php?id=' + r.id; } },
            style: { thumb: function (_r) { return makeThumb('bi-window', 'fg-emphasis-info', null); }, url: function (r) { return path + software_directory + (r.style_type ? '/edit_' + r.style_type + '_style.php?id=' + r.id : '/view_styles.php'); } },
            user: { thumb: function (_r) { return makeThumb('bi-person-circle', 'fg-secondary', null); }, url: function (r) { return path + software_directory + '/edit_user.php?id=' + r.id; } },
            file: { thumb: function (r) { var e = (r.name || '').split('.').pop().toLowerCase(); return makeThumb(FILE_ICONS[e] || (r.design ? 'bi-filetype-css' : 'bi-file-earmark'), 'fg-emphasis-secondary', null); }, url: function (r) { return path + software_directory + '/edit_file.php?id=' + r.id; } },
            contact: { thumb: function (r) { return makeThumb('bi-person-vcard', 'fg-secondary', r.image || null); }, url: function (r) { return path + software_directory + '/edit_contact.php?id=' + r.id; } },
            common_region: { thumb: function (_r) { return makeThumb('bi-columns-gap', 'fg-emphasis-primary', null); }, url: function (r) { return path + software_directory + '/edit_common_region.php?id=' + r.id; } },
            design_region: { thumb: function (_r) { return makeThumb('bi-code-square', 'fg-emphasis-warning', null); }, url: function (r) { return path + software_directory + '/edit_designer_region.php?id=' + r.id; } },
            login_region: { thumb: function (_r) { return makeThumb('bi-shield-lock', 'fg-emphasis-info', null); }, url: function (r) { return path + software_directory + '/edit_login_region.php?id=' + r.id; } },
            short_link: { thumb: function (_r) { return makeThumb('bi-link-45deg', 'fg-emphasis-success', null); }, url: function (r) { return path + software_directory + '/edit_short_link.php?id=' + r.id; } },
            order: { thumb: function (_r) { return makeThumb('bi-receipt', 'fg-emphasis-success', null); }, url: function (r) { return path + software_directory + '/view_order.php?id=' + r.id; } }
        };

        $(document).on('keydown', function (e) {
            if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
                e.preventDefault();
                pgUI.dialog[pgUI.dialog.isOpen($modal) ? 'hide' : 'show']($modal);
            }
        });

        pgUI.dialog.on($modal, 'shown', function () {
            $input.focus();
            // Pre-fetch actions silently to cache them — nothing rendered until user types
            prefetchActions();
        });

        $input.on('input', function () {
            clearTimeout(timer);
            var val = $.trim($input.val());
            if (!val) { resetUI(); return; }
            $emptyBox.addClass('d-none');
            timer = setTimeout(function () {
                if (val !== currentQuery) { currentQuery = val; currentOffset = 0; runSearch(val, 0); }
            }, 280);
        });

        $clearBtn.on('click', resetUI);

        function resetUI() {
            $input.val('');
            currentQuery = '';
            currentOffset = 0;
            $results.empty().removeClass('show');
            $clearBtn.addClass('d-none');
            $emptyBox.removeClass('d-none');
        }

        function prefetchActions() {
            if (cachedActions !== null) return;
            $.ajax({
                contentType: 'application/json',
                url: 'api.php',
                type: 'POST',
                data: JSON.stringify({ action: 'backend_search', search: '', offset: 0 }),
                success: function (resp) {
                    if (resp.status === 'success' && resp.actions) {
                        cachedActions = resp.actions;
                    }
                }
            });
        }

        // Returns the first result button it added (empty when none).
        function appendResults(results) {
            var $first = $();
            $.each(results, function (_i, r) {
                var meta = TYPE_META[r.type];
                if (!meta) return;
                var $btn = $('<button type="button" class="btn btn-link d-flex align-items-center w-100 pg-link-body-emphasis text-decoration-none py-1 ps-1 gap-3 text-start border-bottom"></button>');
                $btn.append(meta.thumb(r));
                var $wrap = $('<span class="d-flex flex-column overflow-hidden"></span>');
                $wrap.append($('<span class="text-truncate lh-sm fw-medium"></span>').text(r.name));
                if (r.sub) { $wrap.append($('<span class="text-truncate small fg-secondary lh-sm"></span>').text(r.sub)); }
                $btn.append($wrap);
                $btn.on('click', function () { pgUI.dialog.hide($modal); leave(meta.url(r)); });
                $results.append($btn);
                if (!$first.length) { $first = $btn; }
            });
            return $first;
        }

        // The next 20 results of the same query.
        function loadMoreButton() {
            var $more = $('<button type="button" class="srch-load-more btn btn-sm btn-outline theme-secondary w-100 mt-3 mb-1"><span class="bi bi-chevron-down me-1"></span>' + t('Load More') + '</button>');
            $more.on('click', function () {
                if (loading) return;
                currentOffset += 20;
                runSearch(currentQuery, currentOffset);
            });
            return $more;
        }

        // A later page that failed: the same page can be asked again, with
        // the reason above the button that takes the focus back.
        function loadMoreFailed() {
            currentOffset -= 20;
            var $more = loadMoreButton();
            $results.append($('<p class="srch-more-error fg-danger my-3" role="alert"></p>').text(t('An error occurred.')), $more);
            $more.trigger('focus');
        }

        function runSearch(query, offset) {
            if (loading) return;
            loading = true;
            $clearBtn.removeClass('d-none');
            $emptyBox.addClass('d-none');
            // Load More is about to go; the result above it holds the focus
            // until the next page arrives, not <body>.
            var $oldMore = $results.find('.srch-load-more');
            if ($oldMore.is(':focus')) {
                $oldMore.prevAll('button').first().trigger('focus');
            }
            $results.find('.srch-load-more, .srch-more-error').remove();

            if (offset === 0) {
                $results.empty().addClass('show').append(
                    '<div class="text-center py-3 fg-secondary"><span class="spinner-border spinner-border-sm me-1"></span>' + t('Please Wait') + '</div>'
                );
            } else {
                $results.append('<div class="srch-load-more text-center py-3 fg-secondary" role="status"><span class="spinner-border spinner-border-sm" aria-hidden="true"></span><span class="visually-hidden">' + t('Loading...') + '</span></div>');
            }

            $.ajax({
                contentType: 'application/json',
                url: 'api.php',
                type: 'POST',
                data: JSON.stringify({ action: 'backend_search', search: query, offset: offset }),
                success: function (resp) {
                    loading = false;
                    $results.find('.srch-load-more').remove();

                    // Only the first page's branch below reads the status.
                    if (offset > 0 && !(resp && resp.status === 'success')) {
                        loadMoreFailed();
                        return;
                    }

                    if (offset === 0) {
                        $results.empty();
                        if (resp.status !== 'success') {
                            $results.append('<p class="fg-danger my-3">' + t('An error occurred.') + '</p>');
                            return;
                        }
                        if (resp.actions) cachedActions = resp.actions;
                        var actions = cachedActions || [];
                        var q = query.toLowerCase();
                        var matched = actions.filter(function (a) { return actionMatches(q, a.keys); });
                        if (matched.length) {
                            var $actRow = $('<div class="d-flex flex-wrap gap-1 mb-3 pb-3 border-bottom"></div>');
                            $.each(matched, function (_i, a) {
                                var $a = $('<a class="btn btn-sm btn-outline theme-secondary no-popover" href="' + a.url + '"><span class="bi ' + a.icon + ' me-1"></span>' + a.label + '</a>');

                                if (inToolbar) {
                                    $a.attr('target', '_parent');
                                }
                                // A settings chip opens the dialog instead of
                                // walking to a screen, and one dialog gives way
                                // to the other: showing the settings while the
                                // search box is still fading out leaves
                                // Bootstrap with a second backdrop and a body
                                // that never gets its scrollbar back. The
                                // delegated handler further down would do
                                // exactly that, so it is not let through.
                                $a.on('click', function (event) {

                                    // The keyboard shortcuts are a dialog of
                                    // this document, opened once the search
                                    // box has gone, for the reason above.
                                    if (this.getAttribute('href') === '#keyboard-shortcuts') {

                                        event.preventDefault();
                                        event.stopPropagation();

                                        pgUI.dialog.on($modal, 'hidden', function () {
                                            if (typeof window.pgShowShortcuts === 'function') {
                                                window.pgShowShortcuts();
                                            }
                                        }, { once: true });

                                        pgUI.dialog.hide($modal);

                                        return;
                                    }

                                    var want = window.pgSettingsModal ? window.pgSettingsModal.asked(this) : null;

                                    if (want) {

                                        event.preventDefault();
                                        event.stopPropagation();

                                        pgUI.dialog.on($modal, 'hidden', function () {
                                            window.pgSettingsModal.open(want.pane, want.section);
                                        }, { once: true });

                                        pgUI.dialog.hide($modal);

                                        return;
                                    }

                                    // No dialog in this document - see
                                    // settingsElsewhere(). The chip walks the
                                    // parent to the dashboard, which has one.
                                    var elsewhere = inToolbar ? settingsElsewhere(this.getAttribute('href')) : null;

                                    if (elsewhere) {

                                        event.preventDefault();
                                        event.stopPropagation();

                                        pgUI.dialog.hide($modal);
                                        leave(elsewhere);

                                        return;
                                    }

                                    pgUI.dialog.hide($modal);
                                });
                                $actRow.append($a);
                            });
                            $results.append($actRow);
                        }
                        if (!resp.results || resp.results.length === 0) {
                            $results.append('<p class="fg-danger my-3">' + t('No Search Result') + '.</p>');
                            return;
                        }
                    }

                    if (resp.results && resp.results.length) {
                        var $first = appendResults(resp.results);
                        // The Load More button that held the focus is gone.
                        if (offset > 0) {
                            $first.trigger('focus');
                        }
                    }

                    if (resp.has_more) {
                        var $more = loadMoreButton();
                        $results.append($more);
                        if (offset > 0 && !(resp.results && resp.results.length)) {
                            $more.trigger('focus');
                        }
                    }
                },
                error: function () {
                    loading = false;
                    $results.find('.srch-load-more').remove();
                    if (offset > 0) { loadMoreFailed(); } else {
                        $results.empty().append('<p class="fg-danger my-3">' + t('An error occurred.') + '</p>');
                    }
                }
            });
        }
    }());





});


//::::::::::::::::::File Picker::::::::::::::::::
windowRef = null;
function software_image_picker(properties) {
    if (properties.initialize !== undefined) {
        //if there is already window, first close it.
        if (windowRef !== null) {
            windowRef.close();
        }
        var urlparameters = '';
        if (properties.SingleImage !== undefined && properties.SingleImage === true) {
            urlparameters = '?SingleImage=true';
        }

        if (properties.file_input_name !== undefined && properties.file_input_name !== '') {
            if (urlparameters !== null && urlparameters !== '') {
                urlparameters = urlparameters + '&';
            } else {
                urlparameters = urlparameters + '?';
            }
            urlparameters = urlparameters + 'file_input_name=' + properties.file_input_name;
        }


        var
            windowUrl = 'editor_select_image.php' + urlparameters,
            windowId = 'NewWindow_' + new Date().getTime(),
            windowFeatures = 'channelmode=no,directories=no,fullscreen=no,' + 'location=no,dependent=yes,menubar=no,resizable=no,scrollbars=yes,' + 'status=no,toolbar=no,titlebar=no,' + 'left=0,top=0,width=1000px,height=500px';

        windowRef = window.open(windowUrl, windowId, windowFeatures);
        // A blocked popup returns null; dereferencing it threw a TypeError that killed
        // the rest of the handler. Safari blocks these far more eagerly than Chrome.
        if (!windowRef) {
            alert(pgLang('Please allow pop-up windows for this site.'));
            return;
        }
        windowRef.onload = function () {

            windowRef.focus();
        };
    }
    if (properties.return !== undefined) {
        if ($('#software_image_picker_container').length) {
            //if this file already added, dont add it again.
            if ($('#software_image_picker_container [name="selected_images[]"][value="' + properties.image_name + '"]').length < 1) {


                if (properties.SingleImage !== undefined && properties.SingleImage === true) {
                    $('#software_image_picker_container').empty();
                }

                //default
                $output_input_name = 'selected_images[]';
                $output_input_value = decodeURIComponent(properties.image_name);
                if (properties.file_input_name !== undefined && properties.file_input_name !== '') {
                    $output_input_name = decodeURIComponent(properties.file_input_name);
                    if (properties.file_input_name == 'file_id') {
                        $output_input_value = properties.file_id;
                    }
                }
                // Support absolute URLs (e.g. Unsplash) — don't prepend path
                var _img_src = /^https?:\/\//.test(properties.image_name)
                    ? properties.image_name
                    : path + decodeURIComponent(properties.image_name);
                $('#software_image_picker_container').append('\
                    <div class="item col">\
                        <div class="card bg-transparent border-0 shadow-none cursor-pointer image">\
                            <div class="card-header d-flex justify-content-end p-1 border-0 bg-transparent">\
                                <button type="button" class="btn btn-link pg-link-danger bi bi-x-lg p-0" aria-label="' + h(pgLang('Remove')) + '" onclick=" $(this).closest(\'.item\').remove();"></button>\
                            </div>\
                            <div class="card-body overflow-hidden position-relative rounded-4 w-100 p-0" style="aspect-ratio: 5 / 4;background: radial-gradient(transparent, #00000024);" title="' + h(decodeURIComponent(properties.image_name)) + '">\
                                <input type="hidden" name="' + $output_input_name + '" value="' + $output_input_value + '"/>\
                                <img class="object-fit-contain w-100 h-100" alt="" src="' + _img_src + '" />\
                            </div>\
                        </div>\
                    </div>');

            } else {
                // The image is already picked: shake its tile (four times,
                // 5px, one second) instead of adding it again.
                $('#software_image_picker_container [name="selected_images[]"][value="' + decodeURIComponent(properties.image_name) + '"]').closest('.item').each(function () {
                    if (typeof this.animate === 'function' && !window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
                        var frames = [{ transform: 'none' }];
                        for (var i = 0; i < 4; i++) {
                            frames.push({ transform: 'translateX(-5px)' }, { transform: 'translateX(5px)' });
                        }
                        frames.push({ transform: 'none' });
                        this.animate(frames, { duration: 1000, easing: 'ease-in-out' });
                    }
                });
            }

        }
    }
}

//::::::::::::::::::Table.chart Edit Contents Buttons::::::::::::::::::
function edit_chart_content(action, name) {
    var result;
    if (typeof name === 'undefined') {
        var name = 'item';
    }
    switch (action) {
        case 'edit':
            document.form.action.value = 'edit';
            break;

        case 'organize':
            document.form.action.value = 'organize';
            break;

        case 'optin':
            document.form.action.value = 'optin';
            result = confirm(lang('WARNING: The selected {var}(s) will be opted-in.').replace('{var}', name))
            break;

        case 'optout':
            document.form.action.value = 'optout';
            result = confirm(lang('WARNING: The selected {var}(s) will be opted-out.').replace('{var}', name))
            break;

        case 'merge':
            document.form.action.value = 'merge';
            result = confirm(lang('WARNING: The selected duplicate {var}(s) will be merged together.').replace('{var}', name))
            break;
        case 'delete':
            document.form.action.value = 'delete';
            result = confirm(lang('WARNING: The selected {var}(s) will be permanently deleted.').replace('{var}', name))
            break;
    }
    // if user select ok to confirmation, submit form
    if (result == true) {
        document.form.submit();
    }
}

/**
 * DataTables options for a list paged on the server (includes/fn/list_paging.php).
 * The table names its panel action in data-pg-list-action and what the action
 * needs besides the page (the filter) in data-pg-list-params. Each draw asks
 * the action for one page with the page window and the search box; a row
 * comes back as its cells' content, with the cells' classes in DT_RowData.
 * Sorting stays the headings' links, so DataTables sends no order.
 */
function pgListServerOptions(options, $table) {
    var params = {};

    try {
        params = JSON.parse($table.attr('data-pg-list-params') || '{}') || {};
    } catch (e) {
        params = {};
    }

    options.serverSide = true;
    options.searchDelay = 400;

    options.ajax = function (request, callback) {
        var body = $.extend({}, params, {
            action: $table.attr('data-pg-list-action'),
            token: software_token,
            draw: request.draw,
            start: request.start,
            length: request.length,
            search: (request.search && request.search.value) || ''
        });

        $.ajax({
            contentType: 'application/json',
            url: 'api.php',
            type: 'POST',
            dataType: 'json',
            data: JSON.stringify(body)
        }).done(function (response) {
            if (response && (response.status === 'success')) {
                callback(response);
            } else {
                pgListServerFailed(callback, request.draw, $table, response && response.message);
            }
        }).fail(function (xhr) {
            pgListServerFailed(callback, request.draw, $table, xhr.responseJSON && xhr.responseJSON.message);
        });
    };

    // The cells' classes and other attributes (a quick edit's data-pg-edit);
    // cells holds hidden columns' cells too.
    options.createdRow = function (row, data, index, cells) {
        var classes = (data && data.DT_RowData && data.DT_RowData.pgCells) || [];
        var attributes = (data && data.DT_RowData && data.DT_RowData.pgAttrs) || {};

        for (var i = 0; (i < classes.length) && cells && (i < cells.length); i++) {
            cells[i].className = classes[i];

            for (var name in (attributes[i] || {})) {
                if (Object.prototype.hasOwnProperty.call(attributes[i], name)) {
                    cells[i].setAttribute(name, attributes[i][name]);
                }
            }
        }
    };

    // Hints, menus and lazy pictures of the rows that just arrived.
    $table.on('draw.dt', function () {
        pgInitInjected($table.find('tbody'));

        if ($.fn.Lazy) {
            $table.find('img.lazy:not(.delayed)').Lazy();
        }
    });
}

/**
 * The page, length and search a paged list was left on, kept in the history
 * entry by pgListKeep() so Back opens it where it was; null when the entry
 * holds none for the list or holds something unusable.
 */
function pgListState(key) {
    var state = (window.history && $.isPlainObject(history.state)) ? history.state : {};
    var lists = $.isPlainObject(state.pgList) ? state.pgList : {};
    var kept = Object.prototype.hasOwnProperty.call(lists, key) ? lists[key] : null;

    if (kept && typeof kept.start === 'number' && kept.start >= 0 && kept.start % 1 === 0
        && typeof kept.length === 'number'
        && typeof kept.search === 'string') {
        return kept;
    }

    return null;
}

// Keeps a list's {start, length, search} in the current history entry,
// beside whatever else the entry holds.
function pgListKeep(key, kept) {
    if (!window.history || !history.replaceState) {
        return;
    }

    var state = $.extend({}, $.isPlainObject(history.state) ? history.state : null);
    state.pgList = $.extend({}, $.isPlainObject(state.pgList) ? state.pgList : null);
    state.pgList[key] = kept;

    try {
        history.replaceState(state, '', location.href);
    } catch (e) {
        // A refused state (size limit) leaves the entry as it was.
    }
}

// An empty page with the reason in the table's empty row.
function pgListServerFailed(callback, draw, $table, message) {
    callback({ draw: draw, recordsTotal: 0, recordsFiltered: 0, data: [] });
    $table.find('tbody td.dt-empty').text(message || lang('Query failed.'));
}

function chart_checkbox_state_change() {
    // The multiselect plugin only ever sets the select-all box to all or
    // nothing, so a part-ticked list read as "not checked". The count is taken
    // over the enabled boxes of the rows the plugin itself selects.
    var $boxes = $('tr:not(.unselectable) .select-all input').not(':disabled');
    var ticked = $boxes.filter(':checked').length;

    $('#select_all')
        .prop('indeterminate', (ticked > 0) && (ticked < $boxes.length))
        .prop('checked', (ticked > 0) && (ticked === $boxes.length));

    // The property as well as the class: .btn.disabled only stops the pointer,
    // so a bulk button outside the bottom bar stayed focusable and pressable
    // with nothing ticked.
    if ($('.select-all input[type=checkbox]').is(':checked')) {
        $('.enable-on-selected button').removeClass('disabled').prop('disabled', false);
        $('.dataTable .action-buttons *').each(function () {
            $(this).attr('style', 'visibility:hidden !important;');
        });

    } else {
        $('.enable-on-selected button').addClass('disabled').prop('disabled', true);
        $('.dataTable .action-buttons *').each(function () {
            $(this).attr('style', '');
        });
    };
}

//::::::::::::::::::PUSH NOTIFICATIONS::::::::::::::::::

// Web push turns the panel from something that has to be looked at into
// something that speaks up. The bell above still polls - a browser that refuses
// the permission, or a device the operator never enabled, keeps working exactly
// as it did - and this only adds the case where nothing is looking at all.
var pg_push = {
    strings: {},
    version: '',
    key: '',
    registration: null,
    subscribed: false,
    busy: false
};

function pg_push_init() {

    var $row = $('#push_row');

    if ($row.length === 0) {
        return;
    }

    // A worker needs a secure context, and the permission is meaningless
    // without one. A panel reached over plain http simply does not offer this.
    if (!('serviceWorker' in navigator) || !('PushManager' in window) || !('Notification' in window) || !window.isSecureContext) {
        return;
    }

    try {
        var config = JSON.parse($('#pg-push-config').text() || '{}');
        pg_push.strings = config.strings || {};
        pg_push.version = config.version || '';
    } catch (error) {
        pg_push.strings = {};
        pg_push.version = '';
    }

    // iOS only hands a push subscription to a site that was added to the home
    // screen, and asking before that is done fails in a way the operator cannot
    // act on. Say what to do instead of showing a button that cannot work.
    if (pg_push_is_ios() && !pg_push_is_installed()) {
        pg_push_hint(pg_push.strings.installed_first || '');
        return;
    }

    navigator.serviceWorker.register('sw.js?v=' + encodeURIComponent(pg_push.version) + '&strings=' + encodeURIComponent(JSON.stringify({
        title: pg_push.strings.title || '',
        body: pg_push.strings.body || ''
    }))).then(function (registration) {

        pg_push.registration = registration;

        return registration.pushManager.getSubscription();

    }).then(function () {

        pg_push_refresh();
        pg_push_watch();

    }).catch(function () {
        // Registration is refused on a panel served from a folder the worker
        // may not control, and by a browser in private mode. Neither is an
        // error the operator can do anything about, so the row stays hidden.
    });

    // Delegated: the system status card is drawn by ajax and redrawn every time
    // one of its jobs finishes, so its button is never the same element twice.
    $(document).on('click', '.pg-push-toggle', function () {
        pg_push_toggle();
    });
}

// The permission is read at the moment it is asked for, and it can change
// while the panel sits open - somebody turns the site back on in the browser,
// or on Android in the notification settings of the installed application,
// which is where the permission moves to once the panel is installed. Without
// this, the row would keep repeating what was true when the page loaded and
// the operator would think the change did not take.
function pg_push_refresh() {

    if (!pg_push.registration) {
        return;
    }

    pg_push.registration.pushManager.getSubscription().then(function (subscription) {

        if ((!subscription) && (Notification.permission === 'denied')) {
            pg_push_hint(pg_push.strings.blocked || '');
            return null;
        }

        $('#push_hint').addClass('d-none');
        $('#push_row').removeClass('d-none').addClass('d-flex');

        if (!subscription) {
            pg_push_paint(false);
            return null;
        }

        // The browser keeps its subscription even after the site has forgotten
        // it - an operator ending it from the sessions screen, a database
        // restored from before it was made. Asking makes the button tell the
        // truth: it says "on" only when this site would actually send here.
        return pg_push_send('push_config', { endpoint: subscription.endpoint }).then(function (response) {

            var known = !!(response && response.status === 'success' && response.data && response.data.known);

            if ((response) && (response.status === 'success') && (response.data) && (response.data.public_key)) {
                pg_push.key = response.data.public_key;
            }

            pg_push_paint(known);

        }, function () {
            // The question could not be asked; believe the browser.
            pg_push_paint(true);
        });

    }).catch(function () {
        // Nothing to repaint if the browser will not say what it holds.
    });
}

// Three ways to notice. The permissions API reports the change as it happens
// where it is supported; coming back to the tab covers the person who left to
// change a setting; opening the bell covers the rest.
function pg_push_watch() {

    if ((navigator.permissions) && (navigator.permissions.query)) {

        navigator.permissions.query({ name: 'notifications' }).then(function (status) {
            status.onchange = function () {
                pg_push_refresh();
            };
        }).catch(function () {
            // Not every browser knows this permission name.
        });
    }

    document.addEventListener('visibilitychange', function () {
        if (!document.hidden) {
            pg_push_refresh();
        }
    });

    pgUI.menu.on('#notification_toggle', 'shown', function () {
        pg_push_refresh();
    });
}

// A chat banner opens the panel at the conversation it came from. The id is
// read from the address the notification carried, and the chat widget is asked
// to open it once it exists - the widget builds itself from its own config
// block and may not have run yet when this does.
function pg_push_open_requested() {

    var requested = parseInt((window.location.search.match(/[?&]chat=(\d+)/) || [])[1], 10);

    if (!requested) {
        return;
    }

    var waited = 0;
    var timer = setInterval(function () {

        waited++;

        if (typeof window.pgChatOpenConversation === 'function') {
            clearInterval(timer);
            window.pgChatOpenConversation(requested);
        } else if (waited > 40) {
            clearInterval(timer);
        }
    }, 250);
}

// The account menu (pg_account_menu(), includes/fn/output.php). Bootstrap
// opens and closes it; what is added here is the close button of the phone
// sheet, which covers the screen below the header. (That the drawer of the
// main menu gives way to it is initMenuDrawer()'s business.)
function pg_account_menu_init() {

    var toggle = document.getElementById('user_menu_toggle');

    if (!toggle || !window.pgUI) {
        return;
    }

    document.addEventListener('click', function (event) {

        if (!event.target.closest || !event.target.closest('[data-pg-account-close]')) {
            return;
        }

        pgUI.menu.hide(toggle);
        toggle.focus();
    });
}

// The keyboard shortcuts (pg_shortcuts_dialog(), includes/fn/output.php).
// ? opens them from anywhere outside a field, the key most web applications
// use for the same sheet; the search box offers them as a chip and calls
// window.pgShowShortcuts().
function pg_shortcuts_init() {

    var dialog = document.getElementById('pg_shortcuts');

    if (!dialog || !window.pgUI) {
        return;
    }

    // The command key stands where Ctrl does on an Apple keyboard.
    var platform = (navigator.userAgentData && navigator.userAgentData.platform) || navigator.platform || navigator.userAgent || '';

    if (/mac|iphone|ipad|ipod/i.test(platform)) {
        dialog.querySelectorAll('kbd[data-pg-mod]').forEach(function (key) {
            key.textContent = '⌘';
        });
    }

    window.pgShowShortcuts = function () {
        pgUI.dialog.show(dialog);
    };

    document.addEventListener('keydown', function (event) {

        if ((event.key !== '?') || event.ctrlKey || event.metaKey || event.altKey || event.defaultPrevented) {
            return;
        }

        var on = document.activeElement;

        if (on && ((on.tagName === 'INPUT') || (on.tagName === 'TEXTAREA') || (on.tagName === 'SELECT') || on.isContentEditable)) {
            return;
        }

        // Not over another dialog: it would open under that one's backdrop.
        if (pgUI.anyOpen('dialog') && !pgUI.dialog.isOpen(dialog)) {
            return;
        }

        event.preventDefault();
        pgUI.dialog.toggle(dialog);
    });
}

// The address book form at the top of the account menu (pg_account_menu(),
// includes/fn/output.php), there while the name or the picture is missing.
// The texts and the address come with the form.
function pg_contact_quick_init() {

    var item = document.getElementById('pg_contact_quick');
    var form = item ? item.querySelector('[data-pg-contact-quick]') : null;
    var LATER = 'pg_contact_photo_later';

    if (!form) {
        return;
    }

    // "Not now" on the picture keeps it out of the menu for a month.
    if (item.hasAttribute('data-photo-only')) {
        try {
            if ((parseInt(window.localStorage.getItem(LATER), 10) || 0) > Date.now()) {
                item.hidden = true;
                return;
            }
        } catch (error) {}
    }

    var later = form.querySelector('[data-pg-contact-later]');

    if (later) {
        later.addEventListener('click', function () {
            try { window.localStorage.setItem(LATER, String(Date.now() + 30 * 86400000)); } catch (error) {}
            item.hidden = true;
        });
    }

    var photo = '';
    var file = form.elements.photo;
    var preview = form.querySelector('[data-pg-contact-preview]');
    var error = form.querySelector('[data-pg-contact-error]');

    // Cut square and made small in the browser: the upload stays light and
    // carries nothing but the picture.
    file.addEventListener('change', function () {
        var chosen = file.files && file.files[0];

        photo = '';
        error.textContent = '';

        if (!chosen) {
            return;
        }

        var reader = new FileReader();

        reader.onload = function () {
            var image = new Image();

            image.onload = function () {
                var side = Math.min(image.naturalWidth, image.naturalHeight);
                var size = Math.min(256, side);
                var canvas = document.createElement('canvas');

                canvas.width = size;
                canvas.height = size;

                var context = canvas.getContext('2d');
                context.fillStyle = '#fff';
                context.fillRect(0, 0, size, size);
                context.drawImage(image, (image.naturalWidth - side) / 2, (image.naturalHeight - side) / 2, side, side, 0, 0, size, size);

                try {
                    photo = canvas.toDataURL('image/jpeg', 0.88);
                } catch (drawError) {
                    photo = String(reader.result || '');
                }

                preview.src = photo;
            };

            image.onerror = function () {
                error.textContent = form.getAttribute('data-error-type') || '';
            };

            image.src = String(reader.result || '');
        };

        reader.readAsDataURL(chosen);
    });

    form.addEventListener('submit', function (event) {
        event.preventDefault();

        var button = form.querySelector('button[type=submit]');
        var body = {
            action: 'contact_quick_save',
            token: (typeof software_token !== 'undefined') ? software_token : '',
            photo: photo
        };

        ['first_name', 'last_name', 'mobile_phone'].forEach(function (name) {
            if (form.elements[name]) { body[name] = form.elements[name].value; }
        });

        button.disabled = true;
        error.textContent = '';

        fetch(form.getAttribute('data-api'), {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(body)
        }).then(function (response) {
            return response.json();
        }).then(function (json) {
            if (json && json.status === 'success') {
                window.location.reload();
                return;
            }

            error.textContent = (json && json.message) ? json.message : (form.getAttribute('data-error-save') || '');
            button.disabled = false;
        }).catch(function () {
            error.textContent = form.getAttribute('data-error-save') || '';
            button.disabled = false;
        });
    });
}

// The quick note in the header (ws_notes_quick_markup(), includes/workspace/
// notes.php): what is written in the box becomes one of the person's
// workspace notes through the same action the notes screen saves with. The
// texts and addresses come with the form, so a screen without the workspace
// carries none of this.
function pg_quick_note_init() {

    var form = document.querySelector('[data-pg-quick-note]');

    if (!form) {
        return;
    }

    var text = form.elements.body;
    var button = form.querySelector('button[type=submit]');
    var status = form.querySelector('[data-pg-quick-note-status]');
    var toggle = document.getElementById('pg_quick_note_toggle');
    var saving = false;

    // Bootstrap fires its dropdown events at the toggle.
    if (toggle) {
        pgUI.menu.on(toggle, 'shown', function () {
            text.focus();
        });
    }

    function say(message, note_id) {

        status.textContent = '';
        status.className = 'small ms-auto ' + (note_id ? 'fg-success' : 'fg-danger');
        status.appendChild(document.createTextNode(message));

        if (note_id) {
            var link = document.createElement('a');

            link.href = form.getAttribute('data-note-url') + encodeURIComponent(note_id);
            link.className = 'ms-1';
            link.textContent = form.getAttribute('data-open');

            if (window.self !== window.top) {
                link.target = '_parent';
            }

            status.appendChild(link);
        }
    }

    function save() {

        var body = text.value.replace(/\s+$/, '');

        if (saving) {
            return;
        }

        if (body.trim() === '') {
            text.focus();
            return;
        }

        saving = true;
        button.disabled = true;
        status.textContent = '';

        fetch(form.getAttribute('data-api'), {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'ws_note_save', token: software_token, title: '', body: body })
        }).then(function (response) {
            return response.json();
        }).then(function (json) {
            var note = (json && json.status === 'success' && json.data) ? json.data.note : null;

            if (note && note.id) {
                text.value = '';
                say(form.getAttribute('data-saved'), note.id);
            } else {
                say((json && json.message) ? json.message : form.getAttribute('data-failed'), 0);
            }
        }).catch(function () {
            say(form.getAttribute('data-failed'), 0);
        }).then(function () {
            saving = false;
            button.disabled = false;
        });
    }

    form.addEventListener('submit', function (event) {
        event.preventDefault();
        save();
    });

    text.addEventListener('keydown', function (event) {
        if ((event.ctrlKey || event.metaKey) && (event.key === 'Enter')) {
            event.preventDefault();
            save();
        }
    });

    text.addEventListener('input', function () {
        if (status.textContent !== '') {
            status.textContent = '';
        }
    });
}

function pg_push_hint($text) {

    var $hint = $('#push_hint');

    $('#push_row').addClass('d-none').removeClass('d-flex');

    // The card has no room for the explanation, so its row says the state and
    // the button stays out of reach rather than promising something it cannot
    // do.
    $('#push_widget_note').text(pg_push.strings.state_blocked || '');
    $('.pg-push-toggle').prop('disabled', true);
    $('#push_widget_row').removeClass('d-none');

    // The element is rendered with the home-screen sentence already in it, so an
    // empty string here means "show what is there", not "blank the row".
    if ($text !== '') {
        $hint.text($text);
    }

    $hint.removeClass('d-none');
}

function pg_push_is_ios() {
    return (/iphone|ipad|ipod/i.test(navigator.userAgent))
        || ((navigator.platform === 'MacIntel') && (navigator.maxTouchPoints > 1));
}

function pg_push_is_installed() {
    return (window.navigator.standalone === true)
        || (window.matchMedia && window.matchMedia('(display-mode: standalone)').matches);
}

// The same state is drawn in two places - under the bell and in the system
// status card - and the card arrives by ajax long after this file has run. Both
// are painted by class rather than by id so that neither has to know the other
// exists, and the card's row is revealed here because until this runs there is
// nothing true to put in it.
function pg_push_paint($subscribed)
{
    pg_push.subscribed = $subscribed;

    $('.pg-push-toggle').prop('disabled', false);
    $('.pg-push-label').text($subscribed ? (pg_push.strings.on || '') : (pg_push.strings.off || ''));

    $('#push_toggle').toggleClass('btn-outline', !$subscribed).toggleClass('btn-solid', $subscribed);

    $('#push_widget_note').text($subscribed ? (pg_push.strings.state_on || '') : (pg_push.strings.state_off || ''));
    $('#push_widget_row').removeClass('d-none');
}

// The permission prompt must be asked for by the click itself. Anything awaited
// first - even reading the current subscription - can spend the browser's
// transient activation, and a request made without it is refused silently: no
// prompt, no error, nothing for the operator to see. So the subscribed state is
// remembered from the last paint rather than looked up here, and the request is
// the first thing the branch does.
function pg_push_toggle() {

    if (pg_push.busy) {
        return;
    }

    // The worker is registered asynchronously and the row is only revealed once
    // that resolved, so this should not happen - but a click that answers with
    // nothing is the one outcome worth ruling out entirely.
    if (!pg_push.registration) {
        pg_push_message(pg_push.strings.not_ready || '');
        return;
    }

    if (pg_push.subscribed) {
        pg_push_busy(true);

        pg_push.registration.pushManager.getSubscription().then(function (subscription) {
            return subscription ? pg_push_off(subscription) : false;
        }).then(function ($subscribed) {
            pg_push_busy(false);
            pg_push_paint($subscribed);
        }).catch(function () {
            pg_push_busy(false);
            pg_push_paint(false);
        });

        return;
    }

    if (Notification.permission === 'denied') {
        pg_push_hint(pg_push.strings.blocked || '');
        pg_push_message(pg_push.strings.blocked || '');
        return;
    }

    pg_push_busy(true);

    Notification.requestPermission().then(function (permission) {

        if (permission !== 'granted') {

            if (permission === 'denied') {
                pg_push_message(pg_push.strings.blocked || '');
                return false;
            }

            // 'default' means the prompt was dismissed - or never drawn at all.
            // Chrome suppresses it for people who usually block notifications
            // and for sites with a low acceptance rate, showing a small bell in
            // the address bar instead; in a standalone window there is no
            // address bar, so absolutely nothing appears. Saying so is the
            // difference between a button that is refused and a button that
            // looks broken.
            pg_push_message(pg_push.strings.dismissed || '');

            return false;
        }

        return pg_push_subscribe();

    }).then(function ($subscribed) {
        pg_push_busy(false);
        pg_push_paint($subscribed);
    }).catch(function () {
        pg_push_busy(false);
        pg_push_paint(false);
    });
}

function pg_push_busy($busy) {

    pg_push.busy = $busy;

    if ($busy) {
        $('.pg-push-toggle').prop('disabled', true);
        $('.pg-push-label').text(pg_push.strings.working || '');
    }
}

function pg_push_subscribe() {

    return pg_push_key().then(function (key) {

        if (key === '') {
            return false;
        }

        // subscribe() answers with the existing subscription when the browser
        // already holds one for this key, so a device the site has forgotten
        // re-registers itself rather than asking the push service for a second
        // address.
        return pg_push.registration.pushManager.subscribe({
            userVisibleOnly: true,
            applicationServerKey: pg_push_key_bytes(key)
        }).then(function (subscription) {

            return pg_push_send('push_subscribe', { subscription: subscription.toJSON() }).then(function (response) {

                // A subscription the server could not store would deliver to
                // nobody, so it is given back rather than left behind in the
                // browser as a promise the panel cannot keep.
                if (!response || response.status !== 'success') {
                    return subscription.unsubscribe().then(function () { return false; });
                }

                return true;
            });
        });
    });
}

function pg_push_off(subscription) {

    var $endpoint = subscription.endpoint;

    return subscription.unsubscribe().then(function () {
        return pg_push_send('push_unsubscribe', { endpoint: $endpoint });
    }).then(function () {
        return false;
    });
}

function pg_push_key() {

    if (pg_push.key !== '') {
        return $.Deferred().resolve(pg_push.key).promise();
    }

    return pg_push_send('push_config', {}).then(function (response) {

        if ((response) && (response.status === 'success') && (response.data.available)) {
            pg_push.key = response.data.public_key;
        }

        return pg_push.key;
    });
}

// The application server key travels as base64url text and has to be handed to
// the browser as bytes.
function pg_push_key_bytes($key) {

    var padded = ($key + '='.repeat((4 - ($key.length % 4)) % 4)).replace(/-/g, '+').replace(/_/g, '/');
    var raw = window.atob(padded);
    var bytes = new Uint8Array(raw.length);

    for (var i = 0; i < raw.length; i++) {
        bytes[i] = raw.charCodeAt(i);
    }

    return bytes;
}

function pg_push_send($action, $data) {

    var payload = $.extend({ action: $action, token: software_token }, $data);

    return $.ajax({
        contentType: 'application/json',
        url: 'api.php',
        type: 'POST',
        data: JSON.stringify(payload),
        dataType: 'json'
    });
}

function pg_push_message($text) {

    if ($text === '') {
        return;
    }

    if (typeof window.pgToast === 'function') {
        window.pgToast({ message: $text, variant: 'warning' });
    } else {
        window.alert($text);
    }
}

//::::::::::::::::::NOTIFICATION::::::::::::::::::
// The unread count the open list was last drawn for. It survives across
// polls and drops to 0 when the list marks everything read, so a poll
// redraws the open list only when the count really rises.
var $unreadsCallback = 0;

function check_unread_notifications(refresh_type) {

    if ($('#notifications').length > 0) {
        $unreads = 0;
        // we check unread notifications from api.php with ajax.
        $.ajax({
            contentType: 'application/json',
            url: 'api.php',
            data: JSON.stringify({
                action: 'check_unread_notifications',
                token: software_token // token is important.
            }),
            type: 'POST',
            success: function (response) {

                $status = response.status;

                // if we get response and its response.status is "success" than we check unread notifications. if someone saw a notification before its check readed.
                // we only check notifications nobody seen before.
                if ($status == "success") {


                    //if notifications active, edit callback and there is no notifications, get it. fix a issue when last notification removed.
                    if (pgUI.menu.isOpen('#notification_toggle')) {
                        if (refresh_type == 'editcallback_check') {
                            if ($('#notifications .menu-scroll-area .list-group li.software_notification').length <= 1) {
                                get_notifications();
                            }

                        }
                    }

                    $unreads = response.number_of_unread_notifications;
                    // if there is at least 1 unreaded notification that user is accessable than prepare for output notification badge.
                    // this check system dont get notifications we dont wanna more traffic.
                    if ($unreads > 0) {
                        // if notification view menu is active than we also get notifications and update all but we dont set notifications readed, because maybe sceen is forgetten and user close and go we be sure to readed.
                        if (pgUI.menu.isOpen('#notification_toggle')) {
                            if ($unreads > $unreadsCallback && refresh_type != 'editcallback_check') {
                                get_notifications(false, true);
                            }
                        }
                        $output_unreads = 0;
                        if ($unreads > 99) {
                            $output_unreads = '99+';
                        } else {
                            $output_unreads = $unreads;
                        }
                        //everything is allright, and there is new notifications. We output notificationn button with notices and badges.
                        $('#notification_toggle').addClass('fg-success');
                        $('#notification_toggle .has-notification-icon').html($output_unreads);
                    } else {
                        // we check but there is no notifications so we output only notification button.
                        $('#notification_toggle').removeClass('fg-success');
                        $('#notification_toggle .has-notification-icon').html('');
                    }
                    // we set callback equal to unreads we need this for check to new notifications is really new one or we seen old one.
                    $unreadsCallback = $unreads;
                } else {
                    // there is a error from api.php so we log in console user may check. this notification check is not done.
                    console.log(response.message);
                }
            },
            error: function (xhr, ajaxOptions, thrownError) {
                // there is a error from ajax so we log in console user may check. this notification check is not done.
                console.log('Error while get number of unread notifications.EC:' + xhr.status);
            }
        });
    }
};
var notification_scrollTop = 0;

function remove_all_notifications() {
    $.ajax({
        contentType: 'application/json',
        url: 'api.php',
        data: JSON.stringify({
            action: 'remove_notifications',
            token: software_token // token is important.
        }),
        type: 'POST',
        success: function (response) {
            // if we get response and its response.status is "success" than we know all notifications are deleted.
            $status = response.status;
            if ($status == "success") {

                // if success we check notifications if we really done it. and it trigger all other checks.
                get_notifications();
            } else {
                // there is a error from api.php so we log in console user may check. notifications not removed.
                console.log(response.message);
            }
        },
        error: function (xhr, ajaxOptions, thrownError) {
            // there is a error from ajax so we log in console user may check. this notification get is not done.
            console.log('Error while delete notifications.EC:' + xhr.status);
        }
    });
};


// keep_place: a redraw the reader did not ask for (the unread poll) keeps the
// old list until the new one arrives, then puts back its scroll position and
// the focus on the same notification row.
function get_notifications($read_mark, keep_place) {
    const notifications = $('#notifications');
    const notifications_body = $('#notifications .menu-scroll-area');
    const notifications_content = $('#notifications .menu-scroll-area *');
    var notifications_empty = $('#notifications .notifications_empty');
    var scroll_area = notifications_body;
    var kept_scroll = 0;
    var kept_scroll_outer = 0;
    var kept_row = '';
    var kept_index = -1;

    if ($read_mark) {
        $unreadsCallback = 0;
    }

    if (keep_place) {
        kept_scroll = scroll_area.scrollTop();
        kept_scroll_outer = notifications.scrollTop();
        var focused_row = $(document.activeElement).closest('li[notification-id]');
        if (focused_row.length && notifications.has(focused_row[0]).length) {
            kept_row = String(focused_row.attr('notification-id'));
            kept_index = focused_row.find('button, a, [tabindex]').index(document.activeElement);
        }
    } else {
        notifications.scrollTop(0);

        notifications_empty.addClass('d-none');

        var spinner_content = '<div class="spinner text-center p-5"><div class="spinner-border fg-success" role="status"><span class="visually-hidden">' + lang('Loading...') + '</span></div></div>';
        notifications_content.remove();

        //first of all we put a loading spinner while processing.
        notifications_body.prepend(spinner_content);
    }

    // Puts the scroll position and the focused row back after a keep_place
    // redraw; a row that is gone leaves the focus where it was.
    var restore_place = function () {
        scroll_area.scrollTop(kept_scroll);
        notifications.scrollTop(kept_scroll_outer);
        if (kept_row === '') {
            return;
        }
        var row = notifications.find('li[notification-id="' + kept_row + '"]');
        var focusables = row.find('button, a, [tabindex]');
        var target = kept_index >= 0 && kept_index < focusables.length ? focusables.eq(kept_index) : focusables.first();
        if (target.length) {
            target.trigger('focus');
        }
    };

    var spinner = $('#notifications .spinner');
    // we will get new notifications that user can access and this notifications will set readed so we remove badges from notification button.
    $('#notification_toggle .has-notification-icon').html('');
    $('#notification_toggle').removeClass('fg-success');

    // we check notifications from api.php with ajax.
    $.ajax({
        contentType: 'application/json',
        url: 'api.php',
        data: JSON.stringify({
            action: 'get_notifications',
            read_mark: $read_mark, // if this is true all notifications will be readed that user can access.
            token: software_token // token is important.
        }),
        type: 'POST',
        success: function (response) {

            // if we get response and its response.status is "success" than we get all notifications json encoded from api.php.
            $status = response.status;
            if ($status == "success") {
                let i = 0;

                // there is at least 1 notifications, so we will output it.
                var data_length = response.data.length;

                var output_notifications = '';
                if (keep_place) {
                    notifications_body.children().remove();
                    notifications_empty.addClass('d-none');
                }
                if (data_length > 0) {
                    // notification loading spinner remove
                    spinner.remove();

                    // than we create notification by data.
                    output_notifications = '<ul class="list-group list-group-flush">';

                    var output_button_bar = '';
                    // title, description and details arrive from the server as
                    // ready-made HTML (pg_notification_display() escapes the
                    // row's values and adds the markup). time is also
                    // server-built markup: get_relative_time() wraps the
                    // relative time in a <time> element that carries the
                    // absolute time as a tooltip. Everything else is a raw
                    // value and is escaped here before it becomes markup.
                    // The target URL travels in a data attribute and is read
                    // by the delegated click handler below, never spliced into
                    // an inline onclick.
                    var notification_id;
                    var notification_user;
                    var notification_time;
                    var notification_type;
                    var notification_readed;
                    while (i < data_length) {
                        notification_id = parseInt(response.data[i].id, 10) || 0;
                        notification_user = h(String(response.data[i].user || ''));
                        notification_time = String(response.data[i].time || '');
                        notification_type = h(String(response.data[i].type || ''));

                        response.data[i].description = '<div class="notification-description bg-2" style="--bs-bg-opacity: 0.2;">' + response.data[i].description + '</div>';
                        response.data[i].details = '<div notification-details>' + response.data[i].details + '</div>';
                        if (response.data[i].action != 'custom') {
                            output_button_bar = '<button type="button" class="menu-item bi bi-link bi-me-2 fg-emphasis-primary notification-go" data-url="' + h(String(response.data[i].url || '')) + '">' + lang('Go to the relevant page') + '</button>';


                            output_button_bar += '<button type="button" class="fs-smaller menu-item bi bi-check-square bi-me-2 fg-emphasis-secondary" onclick="edit_notification(\'' + notification_id + '\', \'mark_unread\'); this.remove();">' + lang('Mark as unread') + '</button>';

                            output_data_title = '<small class="notification-title" title="' + response.data[i].title + '">' + response.data[i].title + '</small>';
                        } else {
                            output_data_title = '<div class="notification-title text-wrap w-100">' + response.data[i].title + '</div>';
                        }


                        if (response.data[i].readed != 1) {
                            notification_readed = 'new';
                        } else {
                            notification_readed = '';
                        }

                        $output_border_classes = '';
                        if (i > 0) {
                            $output_border_classes = ' border-top border-secondary';

                        }

                        //we complete notification skeleton and output it before clear notification button.
                        // OUTPUT
                        output_notifications += '\
                        <li notification-id="' + notification_id + '" class="software_notification custom-contextmenu position-relative list-group-item border-0 p-0 viewed bg-transparent ' + notification_readed + ' ' + notification_type + '">\
                            <div class="container-fluid">\
                            <div class="row notification-card ' + $output_border_classes + '" style="--bs-border-opacity:0.08;">\
                                    <div class="col-12 ps-7 p-3 border-bottom border-secondary " style="--bs-border-opacity:0.04;">\
                                        <div class="d-flex w-100 justify-content-between">\
                                            ' + output_data_title + '\
                                        </div>\
                                        <small>' + response.data[i].description + response.data[i].details + '</small>\
                                        <div class="d-flex w-100 justify-content-between">\
                                            <span class="me-auto badge bg-transparent fg-reset fw-light text-overflow-hidden">' + notification_user + '</span>\
                                            <span class="ms-auto badge bg-transparent fg-reset fw-light">' + notification_time + '</span>\
                                        </div>\
                                    </div>\
                                    ' + output_button_bar + '\
                                </div>\
                            </div>\
                        </li>';
                        i++;
                    }
                    output_notifications += '</ul>';

                    if (data_length > 1) {
                        output_notifications += '<a href="#!" onclick="remove_all_notifications();" title="' + lang('Delete All Notifications') + '" class="menu-item notification-remove-btn fg-danger border-top"><span class="bi bi-trash me-3"></span>' + lang('Delete All Notifications') + '</a></li>';
                    }

                    notifications_body.append(output_notifications);
                    if (keep_place) {
                        restore_place();
                    } else {
                        notifications.scrollTop(0);
                    }
                } else {
                    // notification loading spinner remove
                    spinner.remove();
                    // show no notification content.
                    notifications_empty.removeClass('d-none');
                    notifications.scrollTop(0);
                }

            } else {
                // there is a error from api.php so we log in console user may check. this notification get is not done.
                console.log(response.message);
            }
        },
        error: function (xhr, ajaxOptions, thrownError) {
            // there is a error from ajax so we log in console user may check. this notification get is not done.
            console.log('Error while get notifications.EC:' + xhr.status);
        }
    });
};

// Opens the page a notification points to. The URL sits in a data attribute
// (see get_notifications()), so a notification title or comment that
// contains quotes or markup cannot break out into script. Only a relative
// panel path is accepted: the server builds these from a script name and an
// integer id, anything else is ignored.
$(document).on('click', '#notifications .notification-go', function () {
    var url = String($(this).attr('data-url') || '');

    if (url === '' || /^[a-z][a-z0-9+.-]*:/i.test(url) || url.indexOf('//') === 0 || url.indexOf('\\') !== -1) {
        return;
    }

    if (window.location.href.indexOf('toolbar.php') > -1) {
        parent.document.location.href = url;
    } else {
        location.href = url;
    }
});

function edit_notification(notification_id, do_action) {

    $target = $('#notifications li[notification-id= ' + notification_id + ']');


    if (do_action === 'remove') {
        $target.hide('slow', function () {
            $target.remove();
        });
    } else if (do_action === 'mark_unread') {
        $target.addClass('new');
        $target.removeClass('viewed');

    }
    // we check notifications from api.php with ajax.
    $.ajax({
        contentType: 'application/json',
        url: 'api.php',
        data: JSON.stringify({
            action: 'edit_notifications',
            do_action: do_action,
            id: notification_id,
            token: software_token // token is important.
        }),
        type: 'POST',
        success: function (response) {
            // if we get response and its response.status is "success" than we get all notifications json encoded from api.php.
            $status = response.status;
            if ($status == "success") {
                //we check notifications because maybe change notification number

                check_unread_notifications('editcallback_check');
            } else {
                // there is a error from api.php so we log in console user may check. this notification get is not done.
                console.log(response.message);
            }
        },
        error: function (xhr, ajaxOptions, thrownError) {
            // there is a error from ajax so we log in console user may check. this notification get is not done.
            console.log('Error while delete notification.EC:' + xhr.status);
        }
    });
};


//::::::::::::::::::Submit Form::::::::::::::::::
function submit_form(form_name) {
    document.getElementById(form_name).submit();
}

function submit_optimize_content() {
    // if save and analyze button was clicked, then show analysis notice and determine if form should be submitted
    if (submit_button == 'save_and_analyze') {
        // show analysis notice
        document.getElementById('analysis_notice').style.display = '';

        // if analysis is allowed, then submit form
        if (allow_analysis == true) {
            return true;

            // else analysis is not allowed, so do not submit form
        } else {
            return false;
        }

        // else save and return button was clicked, so submit form
    } else {
        return true;
    }
}
function update_menu_backdrop_height() {
    var menu = document.getElementById('menu');
    var menu_backdrop_style = document.getElementById('menu_backdrop_style');
    if (menu_backdrop_style && menu.className.match(/show/)) {
        menu_backdrop_style.innerHTML = '';
        menu_backdrop_style.innerHTML = '.advanced-visuals #menu.backdrop:before,.advanced-visuals #menu.backdrop:after{height:' + menu.scrollHeight + 'px;}';
    }
}




// Show the list of contact groups when the opt-in check box is checked.
function init_email_preferences() {

    var contact_groups = $('.contact_groups');

    if (contact_groups.length) {

        var opt_in = $('input[name=opt_in]');

        opt_in.change(function () {

            if (opt_in.is(':checked')) {
                contact_groups.fadeIn();
            } else {
                contact_groups.fadeOut();
            }

        });

        // Trigger a change event so the fields will be updated during initial page load.
        opt_in.trigger('change');
    }

}

//Development purphose. add draw lines to all elements
function show_draw_lines() {
    $('body').toggleClass('show-draw-lines');
}

function prepare_content_for_html(content) {
    var chars = new Array('&', '\u00e0', '\u00e1', '\u00e2', '\u00e3', '\u00e4', '\u00e5', '\u00e6', '\u00e7', '\u00e8', '\u00e9', '\u00ea', '\u00eb', '\u00ec', '\u00ed', '\u00ee', '\u00ef', '\u00f0', '\u00f1', '\u00f2', '\u00f3', '\u00f4', '\u00f5', '\u00f6', '\u00f8', '\u00f9', '\u00fa', '\u00fb', '\u00fc', '\u00fd', '\u00fe', '\u00ff', '\u00c0', '\u00c1', '\u00c2', '\u00c3', '\u00c4', '\u00c5', '\u00c6', '\u00c7', '\u00c8', '\u00c9', '\u00ca', '\u00cb', '\u00cc', '\u00cd', '\u00ce', '\u00cf', '\u00d0', '\u00d1', '\u00d2', '\u00d3', '\u00d4', '\u00d5', '\u00d6', '\u00d8', '\u00d9', '\u00da', '\u00db', '\u00dc', '\u00dd', '\u00de', '\u20ac', '\u0022', '\u00df', '\u003c', '\u003e', '\u00a2', '\u00a3', '\u00a4', '\u00a5', '\u00a6', '\u00a7', '\u00a8', '\u00a9', '\u00aa', '\u00ab', '\u00ac', '\u00ad', '\u00ae', '\u00af', '\u00b0', '\u00b1', '\u00b2', '\u00b3', '\u00b4', '\u00b5', '\u00b6', '\u00b7', '\u00b8', '\u00b9', '\u00ba', '\u00bb', '\u00bc', '\u00bd', '\u00be');

    var entities = new Array('amp', 'agrave', 'aacute', 'acirc', 'atilde', 'auml', 'aring', 'aelig', 'ccedil', 'egrave', 'eacute', 'ecirc', 'euml', 'igrave', 'iacute', 'icirc', 'iuml', 'eth', 'ntilde', 'ograve', 'oacute', 'ocirc', 'otilde', 'ouml', 'oslash', 'ugrave', 'uacute', 'ucirc', 'uuml', 'yacute', 'thorn', 'yuml', 'Agrave', 'Aacute', 'Acirc', 'Atilde', 'Auml', 'Aring', 'AElig', 'Ccedil', 'Egrave', 'Eacute', 'Ecirc', 'Euml', 'Igrave', 'Iacute', 'Icirc', 'Iuml', 'ETH', 'Ntilde', 'Ograve', 'Oacute', 'Ocirc', 'Otilde', 'Ouml', 'Oslash', 'Ugrave', 'Uacute', 'Ucirc', 'Uuml', 'Yacute', 'THORN', 'euro', 'quot', 'szlig', 'lt', 'gt', 'cent', 'pound', 'curren', 'yen', 'brvbar', 'sect', 'uml', 'copy', 'ordf', 'laquo', 'not', 'shy', 'reg', 'macr', 'deg', 'plusmn', 'sup2', 'sup3', 'acute', 'micro', 'para', 'middot', 'cedil', 'sup1', 'ordm', 'raquo', 'frac14', 'frac12', 'frac34');

    for (var i = 0; i < chars.length; i++) {
        content = content.split(chars[i]).join('&' + entities[i] + ';');
    }

    return content;
}

// Create a new HTML escaping function with a shorter name
// and that is probably faster than the one above.
function h(content) {
    if (typeof content === 'undefined') {
        return '';
    }

    content = content.replace(/&/g, '&amp;');
    content = content.replace(/</g, '&lt;');
    content = content.replace(/>/g, '&gt;');
    content = content.replace(/"/g, '&quot;');

    return content;
}


function init_product_groups(properties) {
    var group_options = properties.group_options;
    var label = properties.labels;
    var selected_groups = properties.selected_groups;
    var current_id = 0;
    $('.add_group').click(add_group);

    if (selected_groups) {
        $.each(selected_groups, function (index, group) {
            add_group(group);
        });
    }

    function add_group(group) {
        current_id++;

        var id = current_id;

        $('.group_list').append(
            '<div class="col-12">\
                <div class="input-group my-3 group group_' + id + '" >\
                    <select name="product_group" class="form-control product_group" data-pg-combobox data-allow-clear="true"><option value=""></option>' + group_options + '</select>\
                    <a href="javascript:void(0)" title="' + label['Remove'] + '" class="remove btn btn-solid theme-danger no-popover">x</a>\
                </div>\
            </div>');

        // The combobox reads its placeholder from the data attribute.
        var select = $('.group_' + id + ' .product_group').attr('data-placeholder', '-' + label['Select Group'] + '-')[0];

        if (window.pgCombobox) {
            pgCombobox.init(select);
        }

        // If this group is an existing group for the product,
        // then set value and trigger change event so that option pick list appears.
        if (typeof group.product_group !== 'undefined') {
            if (window.pgCombobox) {
                pgCombobox.set(select, group.product_group);
            } else {
                $(select).val(group.product_group).trigger('change');
            }
        }

        $('.group_' + id + ' .remove').click(function () {
            $(this).parent().parent().remove();
        });
    }

    // Once the create/edit product form is submitted,
    // put groups into a JSON string in a hidden form field.
    $('.product_form').submit(function () {
        // Create an array of groups by looping through all of the option fields.

        var groups = [];

        $('.group').each(function () {
            var product_group = $('.product_group', this).val();

            // If an group and an option was selected, then add them to array.
            if (product_group) {
                groups.push({
                    product_group: product_group
                });
            }
        });

        // If there is at least one group, then add a hidden field to the form
        // with a JSON value that contains the groups.
        if (groups.length) {
            var hidden_field = $('<input type="hidden" name="groups">');

            hidden_field.val(JSON.stringify(groups));

            $('.groups').append(hidden_field);
        }

        return true;
    });
}


function init_product_group_attributes(properties) {
    var attributes = properties.attributes;
    var label = properties.labels;


    $.each(attributes, function (index, attribute) {
        var output_options = '';

        $.each(attribute.options, function (index, option) {
            var output_selected = '';

            if (option.id == attribute.default_option_id) {
                var output_selected = ' selected="selected"';
            }

            output_options += '<option value="' + option.id + '"' + output_selected + '>' + h(option.label) + '</option>';
        });

        $('.attributes').append(
            '<div class="col-12 my-3 attribute attribute_' + attribute.id + '" data-attribute-id="' + attribute.id + '">\
                <label class="form-label" for="default_option_id_' + attribute.id + '">' + h(attribute.name) + '</label>\
                <div class="input-group">\
                    <label class="input-group-text" for="default_option_id_' + attribute.id + '">' + label['Default Option'] + ':</label>\
                    <select id="default_option_id_' + attribute.id + '" class="default_option_id form-control">\
                        <option value="">-' + label['None'] + '-</option>\
                        ' + output_options + '\
                    </select>\
                    <button type="button" class="move move_up btn btn-solid theme-secondary border no-popover" aria-label="' + h(label['Move Up']) + '"><span class="material-icons" aria-hidden="true">north</span></button>\
                    <button type="button" class="move move_down btn btn-solid theme-primary border no-popover" aria-label="' + h(label['Move Down']) + '"><span class="material-icons" aria-hidden="true">south</span></button>\
                </div>\
            </div>');

        $('.attribute_' + attribute.id + ' .move_up').click(function () {
            var row = $(this).parents('div').parents('div.attribute');
            row.insertBefore(row.prev());
            update_move_buttons();
        });

        $('.attribute_' + attribute.id + ' .move_down').click(function () {
            var row = $(this).parents('div').parents('div.attribute');
            row.insertAfter(row.next());
            update_move_buttons();
        });
    });

    update_move_buttons();

    function update_move_buttons() {
        var number_of_attributes = $('.attribute').length;

        $('.attribute').each(function () {
            var attribute = $(this);
            var move_up = $('.move_up', attribute);
            var move_down = $('.move_down', attribute);

            // Enable both buttons until we find out if they need to be disabled.
            move_up.prop('disabled', false);
            move_down.prop('disabled', false);

            // If there is only 1 attribute, then disable both buttons.
            if (number_of_attributes == 1) {
                move_up.prop('disabled', true);
                move_down.prop('disabled', true);

                // Otherwise, if this is the first attribute, then disable the move up button.
            } else if (attribute.index() == 0) {
                move_up.prop('disabled', true);

                // Otherwise, if this is the last attribute, then disable the move down button.
            } else if (attribute.is(':last-child')) {
                move_down.prop('disabled', true);
            }
        });
    }

    // Prepare attributes when form is submitted.
    $('.product_group_form').submit(function () {
        // Create an array of attributes by looping through all of the attribute.

        var attributes = [];

        $('.attribute').each(function () {
            var attribute = $(this);

            attributes.push({
                id: attribute.attr('data-attribute-id'),
                default_option_id: $('.default_option_id', attribute).val()
            });
        });

        // Add a hidden field to the form with a JSON value that contains the attributes.

        var hidden_field = $('<input type="hidden" name="attributes">');

        hidden_field.val(JSON.stringify(attributes));

        $(this).append(hidden_field);

        return true;
    });
}




function init_product_attribute_options(properties) {

    var options = properties.options;
    var label = properties.labels;



    var current_id = 0;

    $('.add_option').click(add_option);

    $.each(options, function (index, option) {
        add_option(option);
    });


    function update_move_buttons() {
        $('.option').each(function () {
            var option = $(this);
            var move_up = $('.move_up', option);
            var move_down = $('.move_down', option);

            move_up.prop('disabled', false);
            move_down.prop('disabled', false);

            if (option.is(':only-child')) {
                move_up.prop('disabled', true);
                move_down.prop('disabled', true);
            } else if (option.is(':first-child')) {
                move_up.prop('disabled', true);
            } else if (option.is(':last-child')) {
                move_down.prop('disabled', true);
            }
        });
    }

    function add_option(option) {

        current_id++;

        var id = '';

        if (option.id) {
            id = option.id;
        }

        $('.option_list').append(
            '<div class="col-12 lg:col-7 my-3 option option_' + current_id + '">\
                <div class="p-3 border rounded-4">\
                    <input type="hidden" name="option_id" value="' + id + '" class="option_id">\
                    <div class="input-group">\
                        <input type="text" name="option_label" maxlength="255" class="option_label form-control" aria-label="' + h(label['Option']) + '">\
                        <button type="button" class="move move_up btn btn-solid theme-primary no-popover" aria-label="' + h(label['Move Up']) + '"><span class="material-icons" aria-hidden="true">north</span></button>\
                        <button type="button" class="move move_down btn btn-solid theme-primary no-popover" aria-label="' + h(label['Move Down']) + '"><span class="material-icons" aria-hidden="true">south</span></button>\
                        <button type="button" class="remove btn btn-solid theme-danger no-popover" aria-label="' + h(label['Remove']) + '"><span class="material-icons" aria-hidden="true">delete</span></button>\
                    </div>\
                    <div class="form-field form-text m-3">\
                      <input class="check option_no_value" type="checkbox" id="option_no_value_' + current_id + '" name="option_no_value" value="1">\
                      <label class="" for="option_no_value_' + current_id + '">' + label['\u0027No Thanks\u0027 Option'] + '</label>\
                    </div>\
                </div>\
            </div>');

        if (typeof option.label !== 'undefined') {
            $('.option_' + current_id + ' .option_label').val(option.label);

            if (option.no_value == 1) {
                $('.option_' + current_id + ' .option_no_value').prop('checked', true);
            }
        }

        // If the add option button was clicked, then set focus to field that was just added.
        if (typeof option.label === 'undefined') {
            $('.option_' + current_id + ' .option_label').focus();
        }

        $('.option_' + current_id + ' .move_up').click(function () {
            var option = $(this).parent().parent().parent();

            option.after(option.prev());

            update_move_buttons();
        });

        $('.option_' + current_id + ' .move_down').click(function () {
            var option = $(this).parent().parent().parent();

            option.before(option.next());

            update_move_buttons();
        });

        $('.option_' + current_id + ' .remove').click(function () {
            $(this).parent().parent().parent().remove();

            update_move_buttons();
        });

        update_move_buttons();
    }

    // Prepare options when form is submitted.
    $('.product_attribute_form').submit(function () {
        // Create an array of options by looping through all of the option fields.

        var options = [];

        $('.option').each(function () {
            var option_id = $('.option_id', this).val();
            var label = $('.option_label', this).val();

            var no_value = '';

            if ($('.option_no_value', this).is(':checked')) {
                no_value = '1';
            }

            options.push({
                id: option_id,
                label: label,
                no_value: no_value
            });
        });

        // Add a hidden field to the form with a JSON value that contains the options.

        var hidden_field = $('<input type="hidden" name="options">');

        hidden_field.val(JSON.stringify(options));

        $('.options').append(hidden_field);

        return true;
    });
}

function init_product_attributes(properties) {
    var attributes = properties.attributes;
    var label = properties.labels;
    var selected_attributes = properties.selected_attributes;

    var current_id = 0;

    $('.add_attribute').click(add_attribute);

    if (selected_attributes) {
        $.each(selected_attributes, function (index, attribute) {
            add_attribute(attribute);
        });
    }

    function add_attribute(attribute) {
        current_id++;

        var id = current_id;

        var output_options = '<option value="">-' + label['Select Attribute'] + '-</option>';

        $.each(attributes, function (index, attribute) {
            output_options += '<option value="' + attribute.id + '">' + h(attribute.name) + '</option>';
        });

        $('.attribute_list').append(
            '<div class="col-12 sm:col-auto ">\
                <div class="input-group my-3 attribute attribute_' + id + '" >\
                    <select name="attribute_id" class="form-control attribute_id">' + output_options + '</select>\
                    <a href="javascript:void(0)" class="remove btn btn-solid theme-danger no-popover" title="' + label['Remove'] + '">x</a>\
                </div>\
            </div>');

        // Once the user selects an attribute, then show option pick list.
        $('.attribute_' + id + ' .attribute_id').change(function () {
            $('.attribute_' + id + ' .option_id').remove();

            var selected_attribute_id = $('.attribute_' + id + ' .attribute_id').val();

            if (selected_attribute_id) {
                var output_options = '<option value="">-' + label['Select Option'] + '-</option>';

                var selected_attribute_index = 0;

                $.each(attributes, function (index, attribute) {
                    if (attribute.id == selected_attribute_id) {
                        selected_attribute_index = index;
                        return false;
                    }
                });

                $.each(attributes[selected_attribute_index].options, function (index, option) {
                    output_options += '<option value="' + option.id + '">' + h(option.label) + '</option>';
                });

                $('.attribute_' + id + ' .attribute_id').after(' <select name="option_id" class="form-control option_id">' + output_options + '</select>');

                // If this attribute is an existing attribute for the product,
                // then select correct option in pick list.
                if (typeof attribute.option_id !== 'undefined') {
                    $('.attribute_' + id + ' .option_id').val(attribute.option_id);
                }
            }
        });

        // If this attribute is an existing attribute for the product,
        // then set value and trigger change event so that option pick list appears.
        if (typeof attribute.attribute_id !== 'undefined') {
            $('.attribute_' + id + ' .attribute_id').val(attribute.attribute_id).change();
        }

        $('.attribute_' + id + ' .remove').click(function () {
            $(this).parent().parent().remove();
        });
    }

    // Once the create/edit product form is submitted,
    // put attributes into a JSON string in a hidden form field.
    $('.product_form').submit(function () {
        // Create an array of attributes by looping through all of the option fields.

        var attributes = [];

        $('.attribute').each(function () {
            var attribute_id = $('.attribute_id', this).val();
            var option_id = $('.option_id', this).val();

            // If an attribute and an option was selected, then add them to array.
            if (attribute_id && option_id) {
                attributes.push({
                    attribute_id: attribute_id,
                    option_id: option_id
                });
            }
        });

        // If there is at least one attribute, then add a hidden field to the form
        // with a JSON value that contains the attributes.
        if (attributes.length) {
            var hidden_field = $('<input type="hidden" name="attributes">');

            hidden_field.val(JSON.stringify(attributes));

            $('.attributes').append(hidden_field);
        }

        return true;
    });
}

// Create a function that will open a jQuery dialog that contains an iframe.
function open_dialog(properties) {
    var modal = properties.modal;
    var id = properties.id;
    var title = properties.title;
    var url = properties.url;
    var width = properties.width; // unused
    var height = properties.height; // unused


    // Add iframe to the body.

    var iframe = $('<iframe id="message_iframe" src="' + h(url) + '" frameBorder="0" style="display: block; margin: 0;width:100%;height:100%;" allowfullscreen></iframe>');

    $('body').append('\
    <dialog id="message_modal_id_' + id + '" class="dialog dialog-lg dialog-scrollable" data-bs-backdrop="static" data-bs-keyboard="false">\
                <div class="dialog-header"><h5 class="dialog-title">' + title + '</h5><button type="button" class="btn-close" data-bs-dismiss="dialog" aria-label="' + h(pgLang('Close')) + '"></button></div>\
                <div class="dialog-body p-0" style="height:100vh"></div>\
    </dialog>');
    $('body #message_modal_id_' + id + ' .dialog-body').append(iframe);

    pgUI.dialog.show(document.getElementById("message_modal_id_" + id));

}

function check_iframe_access(iframe) {
    var key = (+new Date) + "" + Math.random();

    try {
        var global = iframe.contentWindow;
        global[key] = "asd";
        return global[key] === "asd";
    } catch (e) {
        return false;
    }
}

function product_submit_form_update_custom_form_fields() {
    var custom_form_page_id = $('#submit_form_custom_form_page_id').val();

    submit_form_custom_form_fields = [];

    // If a custom form is selected, then get fields for that custom form,
    // so that we can create a pick list of fields.
    if (custom_form_page_id) {
        $.ajax({
            dataType: 'json',
            url: 'get_custom_form_fields.php',
            data: 'page_id=' + custom_form_page_id,
            async: false,
            success: function (data) {
                submit_form_custom_form_fields = data;
            }
        });
    };

    // Update where pick list.
    init_product_submit_form_update_where();
}

function product_submit_form_add_field(properties) {
    var action = properties.action;

    if (properties.form_field_id) {
        var form_field_id = properties.form_field_id;
    } else {
        var form_field_id = '';
    }

    if (properties.value) {
        var value = properties.value;
    } else {
        var value = '';
    }

    // Get field number by adding one to the current number of fields.
    var field_number = last_submit_form_field_number[action] + 1;

    var container = $('#submit_form_' + action + '_field');
    var output;

    output = '<div class="input-group w-100">\
    <span class="input-group-text my-1">\
    <label for="submit_form_' + action + '_field_' + field_number + '_form_field_id" class="form-label">Set &nbsp;</label>\
    <select class="form-control my-1" id="submit_form_' + action + '_field_' + field_number + '_form_field_id" name="submit_form_' + action + '_field_' + field_number + '_form_field_id"><option value=""></option>';

    // Assume that the selected field type is "text box" until we find out otherwise.
    // We use this to determine if a text box or text area should be displayed for the value field.
    var field_type = 'text box';
    var length = submit_form_custom_form_fields.length;
    // Loop through all custom form fields in order to prepare options for pick list.
    for (var index = 0; index < length; index++) {
        var status = '';
        // If this option should be selected by default, then select it.
        if (form_field_id == submit_form_custom_form_fields[index]['id']) {
            status = ' selected="selected"';

            field_type = submit_form_custom_form_fields[index]['type'];
        }
        output += '<option value="' + submit_form_custom_form_fields[index]['id'] + '"' + status + '>' + prepare_content_for_html(submit_form_custom_form_fields[index]['name']) + '</option>';
    }
    output += '</select></span>';

    if (field_type != 'text area') {
        output += '<span class="input-group-text my-1" id="submit_form_' + action + '_field_' + field_number + '_value_cell">\
        <label for="submit_form_' + action + '_field_' + field_number + '_value" class="form-label">to &nbsp;</label>\
        <input class="form-control" id="submit_form_' + action + '_field_' + field_number + '_value" name="submit_form_' + action + '_field_' + field_number + '_value" type="text" value="' + prepare_content_for_html(value) + '"/>\
        </span>';
    } else {
        output += '<span class="input-group-text my-1" id="submit_form_' + action + '_field_' + field_number + '_value_cell">\
        <label for="submit_form_' + action + '_field_' + field_number + '_value" class="form-label">to &nbsp;</label>\
        <textarea class="form-control" id="submit_form_' + action + '_field_' + field_number + '_value" name="submit_form_' + action + '_field_' + field_number + '_value">' + prepare_content_for_html(value) + '</textarea>\
        </span>';
    }

    output += '<span class="input-group-text my-1">\
    <button type="button" class="btn btn-solid theme-danger material-icons" onclick="this.parentNode.parentNode.parentNode.removeChild(this.parentNode.parentNode)">delete</button>\
    </span>\
    </div>';
    container.append(output);

    // Update value field based on the field type when the selected field is changed.
    $('#submit_form_' + action + '_field_' + field_number + '_form_field_id').change(function () {
        var selected_form_field_id = $(this).val();

        // Assume that the selected field type is "text box" until we find out otherwise.
        // We use this to determine if a text box or text area should be displayed for the value field.
        var field_type = 'text box';

        var length = submit_form_custom_form_fields.length;

        // Loop through the custom form fields in order to determine the type for the selected field.
        for (var index = 0; index < length; index++) {
            // If this field is the selected field, then remember field type and break out of loop.
            if (submit_form_custom_form_fields[index]['id'] == selected_form_field_id) {
                field_type = submit_form_custom_form_fields[index]['type'];
                break;
            }
        }

        // Store the value so we don't lose it when we might change the value field type below.
        var previous_value = $('#submit_form_' + action + '_field_' + field_number + '_value').val();

        if (field_type != 'text area') {
            $('#submit_form_' + action + '_field_' + field_number + '_value_cell').html('<label for="submit_form_' + action + '_field_' + field_number + '_value" class="form-label">to &nbsp;</label>\
            <input class="form-control" id="submit_form_' + action + '_field_' + field_number + '_value" name="submit_form_' + action + '_field_' + field_number + '_value" type="text" value="' + prepare_content_for_html(previous_value) + '"/>');

        } else {
            $('#submit_form_' + action + '_field_' + field_number + '_value_cell').html('<label for="submit_form_' + action + '_field_' + field_number + '_value" class="form-label">to &nbsp;</label>\
        <textarea class="form-control" id="submit_form_' + action + '_field_' + field_number + '_value" name="submit_form_' + action + '_field_' + field_number + '_value">' + prepare_content_for_html(previous_value) + '</textarea>');

        }
    });

    // Update number of fields.
    last_submit_form_field_number[action]++;
    document.getElementById('last_submit_form_' + action + '_field_number').value = last_submit_form_field_number[action];
}

function init_product_submit_form_update_where(field) {
    var reference_code_selected = '';

    if (field == 'reference_code') {
        reference_code_selected = ' selected="selected"';
    }

    var options =
        '<option value=""></option>\
        <optgroup label="' + lang('System Fields') + '">\
            <option value="reference_code"' + reference_code_selected + '>' + lang('Reference Code') + '</option>\
        </optgroup>\
        <optgroup label="' + lang('Form Fields') + '">';

    var length = submit_form_custom_form_fields.length;

    // Loop through all custom form fields in order to prepare options for pick list.
    for (var index = 0; index < length; index++) {
        var selected = '';

        // If this option should be selected by default, then select it.
        if (submit_form_custom_form_fields[index]['id'] == field) {
            selected = ' selected="selected"';
        }

        options += '<option value="' + submit_form_custom_form_fields[index]['id'] + '"' + selected + '>' + h(submit_form_custom_form_fields[index]['name']) + '</option>';
    }

    options += '</optgroup>';

    $('#submit_form_update_where_field').html(options);
}

function createXMLHttpRequest() {
    if (window.XMLHttpRequest) {
        try {
            return new XMLHttpRequest();
        } catch (error) {
            return false;
        }
    } else if (window.ActiveXObject) {
        try {
            return new ActiveXObject("Microsoft.XMLHTTP");
        } catch (error) {
            return false;
        }
    }
}

function show_or_hide_ecommerce_payment_gateway() {
    // hide all payment gateway fields until we determine which should be displayed
    document.getElementById('ecommerce_payment_gateway_transaction_type_row').style.display = 'none';
    document.getElementById('ecommerce_payment_gateway_mode_row').style.display = 'none';
    document.getElementById('ecommerce_authorizenet_api_login_id_row').style.display = 'none';
    document.getElementById('ecommerce_authorizenet_transaction_key_row').style.display = 'none';
    document.getElementById('ecommerce_clearcommerce_client_id_row').style.display = 'none';
    document.getElementById('ecommerce_clearcommerce_user_id_row').style.display = 'none';
    document.getElementById('ecommerce_clearcommerce_password_row').style.display = 'none';
    document.getElementById('ecommerce_first_data_global_gateway_store_number_row').style.display = 'none';
    document.getElementById('ecommerce_first_data_global_gateway_pem_file_name_row').style.display = 'none';
    document.getElementById('ecommerce_paypal_payflow_pro_partner_row').style.display = 'none';
    document.getElementById('ecommerce_paypal_payflow_pro_merchant_login_row').style.display = 'none';
    document.getElementById('ecommerce_paypal_payflow_pro_user_row').style.display = 'none';
    document.getElementById('ecommerce_paypal_payflow_pro_password_row').style.display = 'none';
    document.getElementById('ecommerce_paypal_payments_pro_gateway_mode_row').style.display = 'none';
    document.getElementById('ecommerce_paypal_payments_pro_api_username_row').style.display = 'none';
    document.getElementById('ecommerce_paypal_payments_pro_api_password_row').style.display = 'none';
    document.getElementById('ecommerce_paypal_payments_pro_api_signature_row').style.display = 'none';
    document.getElementById('ecommerce_sage_merchant_id_row').style.display = 'none';
    document.getElementById('ecommerce_sage_merchant_key_row').style.display = 'none';
    document.getElementById('ecommerce_stripe_api_key_row').style.display = 'none';
    document.getElementById('ecommerce_iyzipay_api_key_row').style.display = 'none';
    document.getElementById('ecommerce_iyzipay_secret_key_row').style.display = 'none';
    document.getElementById('ecommerce_iyzipay_installment_row').style.display = 'none';
    document.getElementById('ecommerce_iyzipay_threeds_row').style.display = 'none';
    document.getElementById('ecommerce_iyzipay_protected_currency_row').style.display = 'none';



    // if credit/debit card is checked, the prepare to show fields
    if (document.getElementById('ecommerce_credit_debit_card').checked == true) {
        // show different fields depending on payment gateway choice
        switch (document.getElementById('ecommerce_payment_gateway').options[document.getElementById('ecommerce_payment_gateway').selectedIndex].value) {
            case 'Authorize.Net':
                document.getElementById('ecommerce_payment_gateway_transaction_type_row').style.display = '';
                document.getElementById('ecommerce_payment_gateway_mode_row').style.display = '';
                document.getElementById('ecommerce_authorizenet_api_login_id_row').style.display = '';
                document.getElementById('ecommerce_authorizenet_transaction_key_row').style.display = '';
                break;

            case 'ClearCommerce':
                document.getElementById('ecommerce_payment_gateway_transaction_type_row').style.display = '';
                document.getElementById('ecommerce_payment_gateway_mode_row').style.display = '';
                document.getElementById('ecommerce_clearcommerce_client_id_row').style.display = '';
                document.getElementById('ecommerce_clearcommerce_user_id_row').style.display = '';
                document.getElementById('ecommerce_clearcommerce_password_row').style.display = '';
                break;

            case 'First Data Global Gateway':
                document.getElementById('ecommerce_payment_gateway_transaction_type_row').style.display = '';
                document.getElementById('ecommerce_payment_gateway_mode_row').style.display = '';
                document.getElementById('ecommerce_first_data_global_gateway_store_number_row').style.display = '';
                document.getElementById('ecommerce_first_data_global_gateway_pem_file_name_row').style.display = '';
                break;

            case 'PayPal Payflow Pro':
                document.getElementById('ecommerce_payment_gateway_transaction_type_row').style.display = '';
                document.getElementById('ecommerce_payment_gateway_mode_row').style.display = '';
                document.getElementById('ecommerce_paypal_payflow_pro_partner_row').style.display = '';
                document.getElementById('ecommerce_paypal_payflow_pro_merchant_login_row').style.display = '';
                document.getElementById('ecommerce_paypal_payflow_pro_user_row').style.display = '';
                document.getElementById('ecommerce_paypal_payflow_pro_password_row').style.display = '';
                break;

            case 'PayPal Payments Pro':
                document.getElementById('ecommerce_payment_gateway_transaction_type_row').style.display = '';
                document.getElementById('ecommerce_paypal_payments_pro_gateway_mode_row').style.display = '';
                document.getElementById('ecommerce_paypal_payments_pro_api_username_row').style.display = '';
                document.getElementById('ecommerce_paypal_payments_pro_api_password_row').style.display = '';
                document.getElementById('ecommerce_paypal_payments_pro_api_signature_row').style.display = '';
                break;

            case 'Sage':
                document.getElementById('ecommerce_payment_gateway_transaction_type_row').style.display = '';
                document.getElementById('ecommerce_sage_merchant_id_row').style.display = '';
                document.getElementById('ecommerce_sage_merchant_key_row').style.display = '';
                break;

            case 'Stripe':
                document.getElementById('ecommerce_payment_gateway_transaction_type_row').style.display = '';
                document.getElementById('ecommerce_stripe_api_key_row').style.display = '';
                break;

            case 'Iyzipay':
                document.getElementById('ecommerce_payment_gateway_mode_row').style.display = '';
                document.getElementById('ecommerce_iyzipay_api_key_row').style.display = '';
                document.getElementById('ecommerce_iyzipay_secret_key_row').style.display = '';
                document.getElementById('ecommerce_iyzipay_installment_row').style.display = '';
                document.getElementById('ecommerce_iyzipay_threeds_row').style.display = '';
                if (document.getElementById('ecommerce_multicurrency').checked == 1) {
                    document.getElementById('ecommerce_iyzipay_protected_currency_row').style.display = '';
                }
                break;
        }
    }

    // The fields of the driver-based gateways name the gateway they belong to
    // (pg_payment_settings_card_rows()), so a new driver needs no case here.
    // Such a gateway takes the store's Transaction Type; its keys say whether
    // it runs in test or live mode, so the mode row stays hidden.
    var card = document.getElementById('ecommerce_credit_debit_card').checked == true;
    var gateway = document.getElementById('ecommerce_payment_gateway').value;
    var driver_gateway = false;

    Array.prototype.forEach.call(document.querySelectorAll('[data-pg-payment-gateway]'), function (row) {
        var own = (card && (row.getAttribute('data-pg-payment-gateway') === gateway));
        row.style.display = own ? '' : 'none';
        driver_gateway = driver_gateway || own;
    });

    if (driver_gateway) {
        document.getElementById('ecommerce_payment_gateway_transaction_type_row').style.display = '';
    }
}

// Example starter JavaScript for disabling form submissions if there are invalid fields
(function () {
    'use strict'

    // Fetch all the forms we want to apply custom Bootstrap validation styles to
    var forms = document.querySelectorAll('.needs-validation')

    // Loop over them and prevent submission
    Array.prototype.slice.call(forms)
        .forEach(function (form) {
            form.addEventListener('submit', function (event) {
                if (!form.checkValidity()) {
                    event.preventDefault()
                    event.stopPropagation()
                }

                form.classList.add('was-validated')
            }, false)
        })
})();

function change_field_type($field_type) {
    $('#rss_field_row').removeClass('show');
    $('#label_row').removeClass('show');
    $('#size_row').removeClass('show');
    $('#maxlength_row').removeClass('show');
    $('#position_row').removeClass('show');
    $('#information_row').removeClass('show');
    $('#spacing_row').removeClass('show');
    $('#default_value_row').removeClass('show');
    $('#contact_field_row').removeClass('show');
    $('#required_row').removeClass('show');
    $('#office_use_only_row').removeClass('show');
    $('#translate_values_row').removeClass('show');
    $('#upload_folder_id_row').removeClass('show');
    $('#wysiwyg_row').removeClass('show');
    $('#rows_row').removeClass('show');
    $('#multiple_row').removeClass('show');
    $('#quiz_question_row').removeClass('show');
    $('#choices_row').removeClass('show');
    // show needed objects
    switch ($field_type) {
        case 'text box':
            $('#rss_field_row').addClass('show');
            $('#label_row').addClass('show');
            $('#position_row').addClass('show');
            $('#size_row').addClass('show');
            $('#maxlength_row').addClass('show');
            $('#default_value_row').addClass('show');
            $('#spacing_row').addClass('show');
            $('#contact_field_row').addClass('show');
            $('#required_row').addClass('show');
            $('#office_use_only_row').addClass('show');
            $('#translate_values_row').addClass('show');
            $('#quiz_question_row').addClass('show');
            break;

        case 'text area':
            $('#rss_field_row').addClass('show');
            $('#label_row').addClass('show');
            $('#position_row').addClass('show');
            $('#maxlength_row').addClass('show');
            $('#default_value_row').addClass('show');
            $('#spacing_row').addClass('show');
            $('#contact_field_row').addClass('show');
            $('#required_row').addClass('show');
            $('#office_use_only_row').addClass('show');
            $('#translate_values_row').addClass('show');
            $('#wysiwyg_row').addClass('show');
            $('#rows_row').addClass('show');
            break;

        case 'pick list':
            $('#rss_field_row').addClass('show');
            $('#label_row').addClass('show');
            $('#position_row').addClass('show');
            $('#size_row').addClass('show');
            $('#default_value_row').addClass('show');
            $('#spacing_row').addClass('show');
            $('#contact_field_row').addClass('show');
            $('#required_row').addClass('show');
            $('#office_use_only_row').addClass('show');
            $('#multiple_row').addClass('show');
            $('#quiz_question_row').addClass('show');
            $('#choices_row').addClass('show');
            break;

        case 'radio button':
            $('#rss_field_row').addClass('show');
            $('#label_row').addClass('show');
            $('#position_row').addClass('show');
            $('#default_value_row').addClass('show');
            $('#spacing_row').addClass('show');
            $('#contact_field_row').addClass('show');
            $('#required_row').addClass('show');
            $('#office_use_only_row').addClass('show');
            $('#quiz_question_row').addClass('show');
            $('#choices_row').addClass('show');
            break;

        case 'check box':
            $('#rss_field_row').addClass('show');
            $('#label_row').addClass('show');
            $('#position_row').addClass('show');
            $('#default_value_row').addClass('show');
            $('#spacing_row').addClass('show');
            $('#contact_field_row').addClass('show');
            $('#required_row').addClass('show');
            $('#office_use_only_row').addClass('show');
            $('#quiz_question_row').addClass('show');
            $('#choices_row').addClass('show');
            break;

        case 'file upload':
            $('#rss_field_row').addClass('show');
            $('#label_row').addClass('show');
            $('#position_row').addClass('show');
            $('#size_row').addClass('show');
            $('#required_row').addClass('show');
            $('#spacing_row').addClass('show');
            $('#office_use_only_row').addClass('show');
            $('#upload_folder_id_row').addClass('show');
            $('#contact_field_row').addClass('show');
            break;

        case 'signature':
            $('#label_row').addClass('show');
            $('#position_row').addClass('show');
            $('#required_row').addClass('show');
            $('#spacing_row').addClass('show');
            $('#upload_folder_id_row').addClass('show');
            break;

        case 'date':
            $('#rss_field_row').addClass('show');
            $('#label_row').addClass('show');
            $('#position_row').addClass('show');
            $('#size_row').addClass('show');
            $('#default_value_row').addClass('show');
            $('#spacing_row').addClass('show');
            $('#required_row').addClass('show');
            $('#office_use_only_row').addClass('show');
            $('#quiz_question_row').addClass('show');
            break;

        case 'date and time':
            $('#rss_field_row').addClass('show');
            $('#label_row').addClass('show');
            $('#position_row').addClass('show');
            $('#size_row').addClass('show');
            $('#default_value_row').addClass('show');
            $('#spacing_row').addClass('show');
            $('#required_row').addClass('show');
            $('#office_use_only_row').addClass('show');
            $('#quiz_question_row').addClass('show');
            break;

        case 'email address':
            $('#rss_field_row').addClass('show');
            $('#label_row').addClass('show');
            $('#position_row').addClass('show');
            $('#size_row').addClass('show');
            $('#maxlength_row').addClass('show');
            $('#default_value_row').addClass('show');
            $('#spacing_row').addClass('show');
            $('#contact_field_row').addClass('show');
            $('#required_row').addClass('show');
            $('#office_use_only_row').addClass('show');
            $('#quiz_question_row').addClass('show');
            break;

        case 'information':
            $('#information_row').addClass('show');
            $('#position_row').addClass('show');
            $('#spacing_row').addClass('show');
            $('#office_use_only_row').addClass('show');
            if ((typeof tinyMCE !== 'undefined') && (tinyMCE.getInstanceById('information') == null)) {
                tinyMCE.execCommand('mceAddControl', false, 'information');
            }
            break;

        case 'time':
            $('#rss_field_row').addClass('show');
            $('#label_row').addClass('show');
            $('#position_row').addClass('show');
            $('#size_row').addClass('show');
            $('#default_value_row').addClass('show');
            $('#spacing_row').addClass('show');
            $('#required_row').addClass('show');
            $('#office_use_only_row').addClass('show');
            $('#quiz_question_row').addClass('show');
            break;
    }
}

function check_if_page_type_supports_layout(page_type) {
    switch (page_type) {
        case 'billing information':
        case 'catalog':
        case 'catalog detail':
        case 'change password':
        case 'set password':
        case 'custom form':
        case 'email preferences':
        case 'express order':
        case 'forgot password':
        case 'form item view':
        case 'form list view':
        case 'login':
        case 'membership entrance':
        case 'my account':
        case 'my account profile':
        case 'view order':
        case 'order form':
        case 'order preview':
        case 'order receipt':
        case 'photo gallery':
        case 'registration entrance':
        case 'search results':
        case 'shipping address and arrival':
        case 'shipping method':
        case 'shopping cart':
        case 'update address book':
            return true;
            break;

        default:
            return false;
            break;
    }
}

function change_email_campaign_profile_action() {
    // Hide all items until we determine which need to be shown.
    $('#calendar_event_id_row').removeClass('show');
    $('#custom_form_page_id_row').removeClass('show');
    $('#email_campaign_profile_id_row').removeClass('show');
    $('#product_id_row').removeClass('show');
    $('#schedule_period').removeClass('show');
    $('#schedule_base').removeClass('show');
    $('#standard_schedule_period_and_base').removeClass('show');
    $('#subscription_fields_row').removeClass('show');

    schedule_period
    // Show certain rows based on which destination type was selected.
    switch (document.getElementById('action').options[document.getElementById('action').selectedIndex].value) {
        case 'calendar_event_reserved':
            $('#calendar_event_id_row').addClass('show');
            $('#schedule_period').addClass('show');
            $('#schedule_base').addClass('show');
            break;

        case 'custom_form_submitted':
            $('#custom_form_page_id_row').addClass('show');
            $('#standard_schedule_period_and_base').addClass('show');
            break;

        case 'email_campaign_sent':
            $('#email_campaign_profile_id_row').addClass('show');
            $('#standard_schedule_period_and_base').addClass('show');
            break;

        case 'order_completed':
            $('#standard_schedule_period_and_base').addClass('show');
            break;

        case 'product_ordered':
            $('#product_id_row').addClass('show');
            $('#standard_schedule_period_and_base').addClass('show');
            break;

        case 'subscription_renewed':
        case 'subscription_failed':
        case 'subscription_cancelled':
            $('#subscription_fields_row').addClass('show');
            $('#standard_schedule_period_and_base').addClass('show');
            break;

        default:
            $('#standard_schedule_period_and_base').addClass('show');
            break;
    }
}

/* The expiration date that hangs off a private-folder grant. Shown when the
   folder is ticked, hidden when it is not - derived from the checkbox rather
   than driven by a click handler, because "select all" ticks boxes
   programmatically and fires no event on any of them. */
function show_or_hide_view_expiration_date(folder_id) {

    var box       = document.getElementById('view_' + folder_id);
    var container = document.getElementById('view_' + folder_id + '_expiration_date_container');

    if (!box || !container) {
        return;
    }

    if (box.checked) {

        container.style.display = '';

        // pgDatePicker.init() draws a field once and leaves a drawn one
        // alone, so a date the operator has just typed stays.
        var field = container.querySelector('input');

        if (field && window.pgDatePicker) {
            pgDatePicker.init(field);
        }

    } else {
        container.style.display = 'none';
    }
}

/* Bring every expiry box in a container up to date with its checkbox, and open
   the ones that already carry a date so an existing expiry is never hidden
   behind a button. */
function pgSyncAclExpiry(scope) {

    var root = scope || document;

    Array.prototype.forEach.call(root.querySelectorAll('[id$="_expiration_date_container"]'), function (container) {

        show_or_hide_view_expiration_date(container.id.replace('view_', '').replace('_expiration_date_container', ''));

        var field = container.querySelector('.pg-acl-expiry-input');

        if (field && field.value !== '') {
            pgOpenAclExpiry(container, false);
        }
    });
}

/* Open the date field of one expiry box. Binding the datepicker is deferred to
   here rather than done for every folder up front: a list of forty folders
   would otherwise build forty calendars nobody asked for. */
function pgOpenAclExpiry(container, focus) {

    container.classList.add('is-open');

    var trigger = container.querySelector('.pg-acl-expiry-add');
    var field   = container.querySelector('.pg-acl-expiry-input');

    if (trigger) {
        trigger.setAttribute('aria-expanded', 'true');
    }

    if (!field) {
        return;
    }

    // The folder tree prints pg_fields.js with it (get_acl_folder_tree()).
    if (window.pgDatePicker) {
        pgDatePicker.init(field);
    }

    if (focus) {
        field.focus();
    }
}

/* Close it again and drop the date: an expiry the operator cannot see is one
   they cannot know about, so hiding the field has to clear the value with it. */
function pgCloseAclExpiry(container) {

    container.classList.remove('is-open');

    var trigger = container.querySelector('.pg-acl-expiry-add');
    var field   = container.querySelector('.pg-acl-expiry-input');

    if (trigger) {
        trigger.setAttribute('aria-expanded', 'false');
    }

    if (field) {
        field.value = '';
        field.style.color = '';
    }
}

/* The "select all" switch of one checkbox list: three states rather than two,
   plus the count beside it where the list has one. The plugin that drives the
   list only ever sets checked/unchecked, so a half-selected list used to show
   the switch off - the one state it could not be. */
function pgSyncCheckboxList(list) {

    var boxes = Array.prototype.filter.call(
        list.querySelectorAll('input.multiselect-checkbox'),
        function (box) { return !box.disabled; });

    var checked = boxes.filter(function (box) { return box.checked; }).length;
    var checker = list.querySelector('.multiselect-checkbox-checker');

    if (checker) {
        checker.indeterminate = ((checked > 0) && (checked < boxes.length));
        checker.checked = ((checked > 0) && (checked === boxes.length));
    }

    var counter = list.querySelector('[data-pg-list-count]');

    if (counter) {
        counter.textContent = boxes.length
            ? String(counter.getAttribute('data-pg-list-count')).replace('{n}', checked).replace('{m}', boxes.length)
            : '';
    }
}

function pgSyncCheckboxLists(scope) {
    Array.prototype.forEach.call((scope || document).querySelectorAll('.multiselect-checkbox-container'), pgSyncCheckboxList);
}

function change_user_role(user_role) {
    pgUserPermissionRole(user_role);
}

function change_offer_action_type($offer_action_type) {
    // hide all objects
    $('#discount_order').removeClass('show');
    $('#discount_product').removeClass('show');
    $('#add_product').removeClass('show');
    $('#discount_shipping').removeClass('show');

    // show needed objects
    switch ($offer_action_type) {
        case 'discount order':
            $('#discount_order').addClass('show');
            break;

        case 'discount product':
            $('#discount_product').addClass('show');
            break;

        case 'add product':
            $('#add_product').addClass('show');
            break;

        case 'discount shipping':
            $('#discount_shipping').addClass('show');
            break;
    }
}


// loop through all filters in order to create rows for filters
function initialize_filters() {
    for (var i = 0; i < filters.length; i++) {
        create_filter(filters[i]);
    }
}

// create row for filter
function create_filter(properties) {
    // if no properties were passed, then set blank values
    if (!properties) {
        var properties = new Array();
        properties['field'] = '';
        properties['operator'] = '';
        properties['value'] = '';
        properties['dynamic_value'] = '';
        properties['dynamic_value_attribute'] = '';
    }

    // get filter number by adding one to the current number of filters
    var filter_number = last_filter_number + 1;

    var tbody = document.getElementById('filter_table').getElementsByTagName('tbody')[0];
    var tr = document.createElement('tr');

    // prepare content for field cell
    var field_cell_html =
        '<select class="form-control" id="filter_' + filter_number + '_field" name="filter_' + filter_number + '_field" onchange="update_value_cell(' + filter_number + '); update_dynamic_value(' + filter_number + ')">\n\
            <option value=""></option>';

    // loop through all field options in order to prepare field options for pick list
    for (var i = 0; i < field_options.length; i++) {
        // if the option is a starting optgroup, then prepare starting optgroup
        if (field_options[i]['value'] == '<optgroup>') {
            field_cell_html += '<optgroup label="' + prepare_content_for_html(field_options[i]['name']) + '">';

            // else if option is an ending optgroup, then prepare ending optgroup
        } else if (field_options[i]['value'] == '</optgroup>') {
            field_cell_html += '</optgroup>';

            // else option is a standard option, so prepare standard option
        } else {
            var status = '';

            // if this option should be selected by default, then select option by default
            if (properties['field'] == field_options[i]['value']) {
                status = ' selected="selected"';
            }

            field_cell_html += '<option value="' + field_options[i]['value'] + '"' + status + '>' + prepare_content_for_html(field_options[i]['name']) + '</option>';
        }
    }

    field_cell_html += '</select>';

    // insert content into field cell
    var td_1 = document.createElement('td');
    td_1.innerHTML = field_cell_html;

    // prepare content for operator cell
    var operator_cell_html = '<select  class="form-control" name="filter_' + filter_number + '_operator">';

    // create array for operator options
    var operators = new Array(
        '', 'contains', 'does not contain', 'is equal to', 'is not equal to', 'is less than', 'is less than or equal to', 'is greater than', 'is greater than or equal to');

    // loop through all operators in order to prepare options
    for (var i = 0; i < operators.length; i++) {
        var status = '';

        // if this operator should be selected by default, then select operator by default
        if (properties['operator'] == operators[i]) {
            status = ' selected="selected"';
        }
        if (operators[i] == '') {
            operator_cell_html += '<option value="' + operators[i] + '"' + status + '>' + operators[i] + '</option>';
        } else {
            operator_cell_html += '<option value="' + operators[i] + '"' + status + '>' + lang(operators[i]) + '</option>';
        }

    }

    operator_cell_html += '</select>';

    // insert content into operator cell
    var td_2 = document.createElement('td');
    td_2.innerHTML = operator_cell_html;

    var td_3 = document.createElement('td');
    td_3.id = 'filter_' + filter_number + '_value_cell';

    // prepare content for dynamic value cell
    var dynamic_value_cell_html =
        '<div class="input-group">\
            <input class="form-control" id="filter_' + filter_number + '_dynamic_value_attribute" name="filter_' + filter_number + '_dynamic_value_attribute" type="text" value="' + prepare_content_for_html(properties['dynamic_value_attribute']) + '" size="2" maxlength="10" style="display: none" />\
            <select class="form-control" id="filter_' + filter_number + '_dynamic_value" name="filter_' + filter_number + '_dynamic_value" style="display: none" onchange="update_dynamic_value_attribute(' + filter_number + '); clear_value(' + filter_number + ')"></select>\
        </div>';

    // insert content into dynamic value cell
    var td_4 = document.createElement('td');
    td_4.innerHTML = dynamic_value_cell_html;

    // prepare content for delete cell
    var delete_cell_html = '<button type="button" class="btn btn-solid theme-danger material-icons" onclick="delete_filter(this.parentNode.parentNode)" >delete</button>';

    var td_5 = document.createElement('td');
    td_5.innerHTML = delete_cell_html;

    tr.appendChild(td_1);
    tr.appendChild(td_2);
    tr.appendChild(td_3);
    tr.appendChild(td_4);
    tr.appendChild(td_5);

    tbody.appendChild(tr);

    update_value_cell(filter_number, properties['value']);
    update_dynamic_value(filter_number, properties['dynamic_value'], properties['dynamic_value_attribute']);

    // update number of filters
    last_filter_number++;
    document.getElementById('last_filter_number').value = last_filter_number;

}

function delete_filter(tr) {
    // a date or time value box takes its control with it
    var value_box = tr.querySelector('td[id$="_value_cell"] input');

    if (value_box && window.pgFields) {
        window.pgFields.destroy(value_box);
    }

    tbody = tr.parentNode;
    tbody.removeChild(tr);
}

// The hook a filter's value box gets for a date or time field.
var FILTER_VALUE_HOOKS = {'date': 'data-pg-date', 'date and time': 'data-pg-datetime', 'time': 'data-pg-time'};

function update_value_cell(filter_number, value) {
    // if value is not defined, then set value to empty string
    if (!value) {
        value = '';
    }

    // get field value for filter
    var field_value = document.getElementById('filter_' + filter_number + '_field').value;

    // loop through field options in order to determine if there are value options for field

    for (var i = 0; i < field_options.length; i++) {
        // if the option is the currently selected option, then prepare value cell HTML
        if (field_options[i]['value'] == field_value) {
            var value_cell_html = '';

            // if there are value options for the field, then create HTML for pick list of value options
            if (field_options[i]['value_options']) {
                value_cell_html =
                    '<select class="form-control" id="filter_' + filter_number + '_value" name="filter_' + filter_number + '_value">\n\
                        <option value=""></option>';

                // loop through all value options in order to prepare values options for pick list
                for (var j = 0; j < field_options[i]['value_options'].length; j++) {
                    var status = '';

                    // if this option should be selected by default, then select option by default
                    if (value == field_options[i]['value_options'][j]['value']) {
                        status = ' selected="selected"';
                    }

                    value_cell_html += '<option value="' + field_options[i]['value_options'][j]['value'] + '"' + status + '>' + prepare_content_for_html(field_options[i]['value_options'][j]['name']) + '</option>';
                }

                value_cell_html += '</select>';

                // else there are not value options for the field, so create HTML for value text box,
            // on the date-and-time control for a date or time field (the text the server reads stays the same)
            } else {
                var hook = FILTER_VALUE_HOOKS[field_options[i]['type']] || '';

                value_cell_html = '<input class="form-control" id="filter_' + filter_number + '_value" name="filter_' + filter_number + '_value" type="text" value="' + prepare_content_for_html(value) + '" maxlength="255"' + (hook ? ' ' + hook : '') + ' />';
            }

            // update value cell with HTML; the previous box's control goes first
            var cell = document.getElementById('filter_' + filter_number + '_value_cell');
            var old_value = document.getElementById('filter_' + filter_number + '_value');

            if (old_value && window.pgFields) {
                window.pgFields.destroy(old_value);
            }

            cell.innerHTML = value_cell_html;

            if (window.pgFields) {
                window.pgFields.init(cell);
            }

            break;
        }
    }
}

function update_dynamic_value(filter_number, dynamic_value, dynamic_value_attribute) {
    // get field value for filter
    field_value = document.getElementById('filter_' + filter_number + '_field').value;

    // get field type
    var field_type = '';

    // loop through all field options in order to find type
    for (var i = 0; i < field_options.length; i++) {
        // if this field option is the selected field option, then set type
        if (field_options[i]['value'] == field_value) {
            field_type = field_options[i]['type'];
            break;
        }
    }

    // create array for dynamic value options
    var dynamic_value_options = new Array();

    dynamic_value_options[0] = new Array();
    dynamic_value_options[0]['name'] = '';
    dynamic_value_options[0]['value'] = '';

    // if field type is date then add options for date
    if (field_type == 'date') {
        var index = dynamic_value_options.length;
        dynamic_value_options[index] = new Array();
        dynamic_value_options[index]['name'] = lang('Current Date');
        dynamic_value_options[index]['value'] = 'current date';
    }

    // if field type is date and time then add options for date and time
    if (field_type == 'date and time') {
        var index = dynamic_value_options.length;
        dynamic_value_options[index] = new Array();
        dynamic_value_options[index]['name'] = lang('Current Date & Time');
        dynamic_value_options[index]['value'] = 'current date and time';
    }

    // if field type is date and time then add options for date and time
    if ((field_type == 'date') || (field_type == 'date and time')) {
        var index = dynamic_value_options.length;
        dynamic_value_options[index] = new Array();
        dynamic_value_options[index]['name'] = lang('Day(s) Ago');
        dynamic_value_options[index]['value'] = 'days ago';
    }

    // if field type is time then add options for time
    if (field_type == 'time') {
        var index = dynamic_value_options.length;
        dynamic_value_options[index] = new Array();
        dynamic_value_options[index]['name'] = lang('Current Time');
        dynamic_value_options[index]['value'] = 'current time';
    }

    // if field type is username then add options for username
    if (field_type == 'username') {
        var index = dynamic_value_options.length;
        dynamic_value_options[index] = new Array();
        dynamic_value_options[index]['name'] = lang('Viewer');
        dynamic_value_options[index]['value'] = 'viewer';
    }

    // if field type is email address then add options for email address
    if (field_type == 'email address') {
        var index = dynamic_value_options.length;
        dynamic_value_options[index] = new Array();
        dynamic_value_options[index]['name'] = lang('Viewer\'s E-mail Address');
        dynamic_value_options[index]['value'] = 'viewers email address';
    }

    // remove any existing options from dynamic value pick list
    document.getElementById('filter_' + filter_number + '_dynamic_value').options.length = 0;

    // loop through all dynamic value options in order to add options to dynamic value pick list
    for (var i = 0; i < dynamic_value_options.length; i++) {
        document.getElementById('filter_' + filter_number + '_dynamic_value').options[i] = new Option(dynamic_value_options[i]['name'], dynamic_value_options[i]['value']);

        // if this dynamic value option should be selected by default, then select dynamic value option by default
        if (dynamic_value_options[i]['value'] == dynamic_value) {
            document.getElementById('filter_' + filter_number + '_dynamic_value').selectedIndex = i;
        }
    }

    // if there is more than one dynamic value option, then show dynamic value pick list
    if (dynamic_value_options.length > 1) {
        document.getElementById('filter_' + filter_number + '_dynamic_value').style.display = 'inline';

        // else there is not at least one dynamic value option, so hide dynamic value pick list and attribute
    } else {
        document.getElementById('filter_' + filter_number + '_dynamic_value').style.display = 'none';
    }

    update_dynamic_value_attribute(filter_number, dynamic_value_attribute);
}

function update_dynamic_value_attribute(filter_number, dynamic_value_attribute) {
    // get dynamic value for filter
    dynamic_value = document.getElementById('filter_' + filter_number + '_dynamic_value').value;

    // if the dynamic value is days ago, then show attribute
    if (dynamic_value == 'days ago') {
        document.getElementById('filter_' + filter_number + '_dynamic_value_attribute').style.display = 'inline';

        // else the dynamic value is not days ago, so hide attribute
    } else {
        document.getElementById('filter_' + filter_number + '_dynamic_value_attribute').style.display = 'none';
    }
}

function clear_value(filter_number) {
    // if an option was selected for dynamic value pick list, then clear value
    if (document.getElementById('filter_' + filter_number + '_dynamic_value').options[document.getElementById('filter_' + filter_number + '_dynamic_value').selectedIndex].value != '') {
        pg_filter_value_clear(document.getElementById('filter_' + filter_number + '_value'));
    }
}

// Empties a filter's value box; a date or time box on the date-and-time
// control is emptied through its wrapper, so the box it shows empties too.
function pg_filter_value_clear(field) {
    if (field.hasAttribute('data-pg-date') && window.pgDatePicker) {
        window.pgDatePicker.set(field, '');
    } else if ((field.hasAttribute('data-pg-datetime') || field.hasAttribute('data-pg-time')) && window.pgDateTimeField) {
        window.pgDateTimeField.set(field, '');
    } else {
        field.value = '';
    }
}

// A date picked in a box marked data-pg-add-to-chips="<id>" (the shipping
// method's excluded dates) is added to those chips, once, and the box is
// emptied for the next one.
$(document).on('change', '[data-pg-add-to-chips]', function () {
    var value = this.value.trim();
    var chips = document.getElementById(this.getAttribute('data-pg-add-to-chips'));

    if (!value || !chips || !window.pgChips || !window.pgDatePicker || !window.pgDatePicker.get(this)) {
        return;
    }

    var list = window.pgChips.get(chips);

    if (list.indexOf(value) === -1) {
        window.pgChips.set(chips, list.concat(value));
    }

    window.pgDatePicker.set(this, '');
});

function init_shipping_method_service() {
    var service = $('#service');
    service.change(function () {
        var service_value = service.val();
        // If a service has been selected and we support real-time rates for that service, then
        // show real-time rate row.
        if (
            service_value &&
            (service_value.substr(0, 4) == 'usps' || service_value.substr(0, 3) == 'ups')
        ) {
            $('#realtime_rate_row').addClass('show');

            // Otherwise hide the real-time rate row.
        } else {
            $('#realtime_rate_row').removeClass('show');
        }
    });
    // Trigger change event for initial page load.
    service.trigger('change');
}




function change_page_type(page_type) {

    if (check_if_page_type_supports_layout(page_type)) {
        $('#layout_type_row').fadeIn();

    } else {
        $('#layout_type_row').fadeOut();
    }

    // hide all objects
    document.getElementById('email_a_friend_submit_button_label_row').style.display = 'none';
    document.getElementById('email_a_friend_next_page_id_row').style.display = 'none';
    document.getElementById('folder_view_pages_row').style.display = 'none';
    document.getElementById('folder_view_files_row').style.display = 'none';
    document.getElementById('photo_gallery_number_of_columns_row').style.display = 'none';
    document.getElementById('photo_gallery_thumbnail_max_size_row').style.display = 'none';
    document.getElementById('update_address_book_address_type_row').style.display = 'none';
    document.getElementById('update_address_book_address_type_page_id_row').style.display = 'none';

    // if e-commerce is on
    if (document.getElementById('order_form_product_layout_row_1')) {
        document.getElementById('catalog_product_group_id_row').style.display = 'none';
        document.getElementById('catalog_menu_row').style.display = 'none';
        document.getElementById('catalog_search_row').style.display = 'none';
        document.getElementById('catalog_number_of_featured_items_row').style.display = 'none';
        document.getElementById('catalog_number_of_new_items_row').style.display = 'none';
        document.getElementById('catalog_number_of_columns_row').style.display = 'none';
        document.getElementById('catalog_image_width_row').style.display = 'none';
        document.getElementById('catalog_image_height_row').style.display = 'none';
        document.getElementById('catalog_back_button_label_row').style.display = 'none';
        document.getElementById('catalog_catalog_detail_page_id_row').style.display = 'none';
        document.getElementById('catalog_detail_allow_customer_to_add_product_to_order_row').style.display = 'none';
        document.getElementById('catalog_detail_back_button_label_row').style.display = 'none';
        document.getElementById('express_order_shopping_cart_label_row').style.display = 'none';
        document.getElementById('express_order_quick_add_label_row').style.display = 'none';
        document.getElementById('express_order_quick_add_product_group_id_row').style.display = 'none';
        document.getElementById('express_order_product_description_type_row').style.display = 'none';
        document.getElementById('express_order_shipping_form_row').style.display = 'none';
        document.getElementById('express_order_special_offer_code_label_row').style.display = 'none';
        document.getElementById('express_order_special_offer_code_message_row').style.display = 'none';
        document.getElementById('express_order_custom_field_1_label_row').style.display = 'none';
        document.getElementById('express_order_custom_field_2_label_row').style.display = 'none';
        document.getElementById('express_order_po_number_row').style.display = 'none';
        document.getElementById('express_order_form_row').style.display = 'none';
        document.getElementById('express_order_form_notice').style.display = 'none';
        document.getElementById('express_order_form_name_row').style.display = 'none';
        document.getElementById('express_order_form_label_column_width_row').style.display = 'none';
        document.getElementById('express_order_card_verification_number_page_id_row').style.display = 'none';

        if (document.getElementById('express_order_offline_payment_always_allowed_row')) {
            document.getElementById('express_order_offline_payment_always_allowed_row').style.display = 'none';
            document.getElementById('express_order_offline_payment_label_row').style.display = 'none';
        }

        document.getElementById('express_order_terms_page_id_row').style.display = 'none';
        document.getElementById('express_order_update_button_label_row').style.display = 'none';
        document.getElementById('express_order_purchase_now_button_label_row').style.display = 'none';
        document.getElementById('express_order_auto_registration_row').style.display = 'none';

        // If hook code rows exists (i.e. user is a designer or administrator and hooks are enabled),
        // then hide hook code rows by default.
        if (document.getElementById('express_order_pre_save_hook_code_row')) {
            document.getElementById('express_order_pre_save_hook_code_row').style.display = 'none';
            document.getElementById('express_order_post_save_hook_code_row').style.display = 'none';
        }
        document.getElementById('express_order_order_receipt_email_row').style.display = 'none';
        document.getElementById('express_order_next_page_id_row').style.display = 'none';
        document.getElementById('order_form_product_layout_row_1').style.display = 'none';
        document.getElementById('order_form_product_group_id_row').style.display = 'none';
        document.getElementById('order_form_product_layout_row_1').style.display = 'none';
        document.getElementById('order_form_product_layout_row_2').style.display = 'none';
        document.getElementById('order_form_add_button_row').style.display = 'none';
        document.getElementById('order_form_skip_button_row').style.display = 'none';

        // If search folder row exists (i.e. advanced search is enabled), then hide it.
        if (document.getElementById('search_results_search_folder_id_row')) {
            document.getElementById('search_results_search_folder_id_row').style.display = 'none';
        }

        // if the ecommerce search results rows exist (i.e. if user has more than a user role), then hide them
        if (document.getElementById('search_results_search_catalog_items_row')) {
            document.getElementById('search_results_search_catalog_items_row').style.display = 'none';
        }

        document.getElementById('shopping_cart_shopping_cart_label_row').style.display = 'none';
        document.getElementById('shopping_cart_quick_add_label_row').style.display = 'none';
        document.getElementById('shopping_cart_quick_add_product_group_id_row').style.display = 'none';
        document.getElementById('shopping_cart_product_description_type_row').style.display = 'none';
        document.getElementById('shopping_cart_special_offer_code_label_row').style.display = 'none';
        document.getElementById('shopping_cart_special_offer_code_message_row').style.display = 'none';
        document.getElementById('shopping_cart_update_button_label_row').style.display = 'none';
        document.getElementById('shopping_cart_checkout_button_label_row').style.display = 'none';

        // If hook code row exists (i.e. user is a designer or administrator and hooks are enabled),
        // then hide hook code row by default.
        if (document.getElementById('shopping_cart_hook_code_row')) {
            document.getElementById('shopping_cart_hook_code_row').style.display = 'none';
        }

        document.getElementById('shopping_cart_next_page_id_with_shipping_row').style.display = 'none';
        document.getElementById('shopping_cart_next_page_id_without_shipping_row').style.display = 'none';
        document.getElementById('shipping_address_and_arrival_address_type_row').style.display = 'none';
        document.getElementById('shipping_address_and_arrival_form_row').style.display = 'none';
        document.getElementById('shipping_address_and_arrival_form_notice').style.display = 'none';
        document.getElementById('shipping_address_and_arrival_form_name_row').style.display = 'none';
        document.getElementById('shipping_address_and_arrival_form_label_column_width_row').style.display = 'none';
        document.getElementById('shipping_address_and_arrival_submit_button_row').style.display = 'none';
        document.getElementById('shipping_method_product_description_type_row').style.display = 'none';
        document.getElementById('shipping_method_submit_button_row').style.display = 'none';
        document.getElementById('billing_information_custom_field_1_label_row').style.display = 'none';
        document.getElementById('billing_information_custom_field_2_label_row').style.display = 'none';
        document.getElementById('billing_information_po_number_row').style.display = 'none';
        document.getElementById('billing_information_form_row').style.display = 'none';
        document.getElementById('billing_information_form_notice').style.display = 'none';
        document.getElementById('billing_information_form_name_row').style.display = 'none';
        document.getElementById('billing_information_form_label_column_width_row').style.display = 'none';
        document.getElementById('billing_information_submit_button_label_row').style.display = 'none';
        document.getElementById('billing_information_next_page_id_row').style.display = 'none';
        document.getElementById('order_preview_product_description_type_row').style.display = 'none';
        document.getElementById('order_preview_card_verification_number_page_id_row').style.display = 'none';

        if (document.getElementById('order_preview_offline_payment_always_allowed_row')) {
            document.getElementById('order_preview_offline_payment_always_allowed_row').style.display = 'none';
            document.getElementById('order_preview_offline_payment_label_row').style.display = 'none';
        }

        document.getElementById('order_preview_terms_page_id_row').style.display = 'none';
        document.getElementById('order_preview_submit_button_label_row').style.display = 'none';
        document.getElementById('order_preview_auto_registration_row').style.display = 'none';

        // If hook code rows exists (i.e. user is a designer or administrator and hooks are enabled),
        // then hide hook code rows by default.
        if (document.getElementById('order_preview_pre_save_hook_code_row')) {
            document.getElementById('order_preview_pre_save_hook_code_row').style.display = 'none';
            document.getElementById('order_preview_post_save_hook_code_row').style.display = 'none';
        }

        document.getElementById('order_preview_order_receipt_email_row').style.display = 'none';
        document.getElementById('order_preview_next_page_id_row').style.display = 'none';
        document.getElementById('order_receipt_product_description_type_row').style.display = 'none';
    }

    // if forms is on
    if (document.getElementById('custom_form_form_name_row')) {
        document.getElementById('custom_form_form_name_row').style.display = 'none';
        document.getElementById('custom_form_enabled_row').style.display = 'none';
        document.getElementById('custom_form_quiz_row').style.display = 'none';
        document.getElementById('custom_form_label_column_width_row').style.display = 'none';
        document.getElementById('custom_form_watcher_page_id_row').style.display = 'none';
        document.getElementById('custom_form_save_row').style.display = 'none';
        document.getElementById('custom_form_submit_button_label_row').style.display = 'none';
        document.getElementById('custom_form_auto_registration_row').style.display = 'none';

        // If hook code row exists (i.e. user is a designer or administrator and hooks are enabled),
        // then hide hook code row by default.
        if (document.getElementById('custom_form_hook_code_row')) {
            document.getElementById('custom_form_hook_code_row').style.display = 'none';
        }

        document.getElementById('custom_form_submitter_email_row').style.display = 'none';
        document.getElementById('custom_form_administrator_email_row').style.display = 'none';
        document.getElementById('custom_form_contact_group_id_row').style.display = 'none';
        document.getElementById('custom_form_membership_row').style.display = 'none';
        document.getElementById('custom_form_private_row').style.display = 'none';

        // If grant offer rows exist (i.e. commerce is enabled and user has access to commerce),
        // then hide grant offer rows.
        if (document.getElementById('custom_form_offer_row')) {
            document.getElementById('custom_form_offer_row').style.display = 'none';
        }

        document.getElementById('custom_form_confirmation_type_row').style.display = 'none';
        document.getElementById('custom_form_return_type_row').style.display = 'none';
        document.getElementById('custom_form_pretty_urls_row').style.display = 'none';
        document.getElementById('custom_form_confirmation_continue_button_label_row').style.display = 'none';
        document.getElementById('custom_form_confirmation_next_page_id_row').style.display = 'none';
        document.getElementById('form_list_view_custom_form_page_id_row').style.display = 'none';
        document.getElementById('form_list_view_form_item_view_page_id_row').style.display = 'none';
        document.getElementById('form_list_view_viewer_filter_row').style.display = 'none';
        document.getElementById('form_item_view_custom_form_page_id_row').style.display = 'none';
        document.getElementById('form_item_view_submitter_security_row').style.display = 'none';
        document.getElementById('form_item_view_submitted_form_editable_by_registered_user_row').style.display = 'none';
        document.getElementById('form_item_view_submitted_form_editable_by_submitter_row').style.display = 'none';

        // If hook code row exists (i.e. user is a designer or administrator and hooks are enabled),
        // then hide hook code row by default.
        if (document.getElementById('form_item_view_hook_code_row')) {
            document.getElementById('form_item_view_hook_code_row').style.display = 'none';
        }

        document.getElementById('form_view_directory_form_list_views_row').style.display = 'none';
        document.getElementById('form_view_directory_summary_row').style.display = 'none';
        document.getElementById('form_view_directory_form_list_view_heading_row').style.display = 'none';
        document.getElementById('form_view_directory_subject_heading_row').style.display = 'none';
        document.getElementById('form_view_directory_number_of_submitted_forms_heading_row').style.display = 'none';
    }

    // if calendars is on
    if (document.getElementById('calendar_view_default_view_row')) {
        document.getElementById('calendar_view_calendars_row').style.display = 'none';
        document.getElementById('calendar_view_default_view_row').style.display = 'none';
        document.getElementById('calendar_view_calendar_event_view_page_id_row').style.display = 'none';
        document.getElementById('calendar_event_view_calendars_row').style.display = 'none';
        document.getElementById('calendar_view_number_of_upcoming_events_row').style.display = 'none';
        document.getElementById('calendar_event_view_notes_row').style.display = 'none';
        document.getElementById('calendar_event_view_back_button_label_row').style.display = 'none';
    }

    // if affiliate program is on
    if (document.getElementById('affiliate_sign_up_form_terms_page_id_row')) {
        document.getElementById('affiliate_sign_up_form_terms_page_id_row').style.display = 'none';
        document.getElementById('affiliate_sign_up_form_submit_button_label_row').style.display = 'none';
        document.getElementById('affiliate_sign_up_form_next_page_id_row').style.display = 'none';
    }

    // show needed objects
    switch (page_type) {

        case 'email a friend':
            document.getElementById('email_a_friend_submit_button_label_row').style.display = '';
            document.getElementById('email_a_friend_next_page_id_row').style.display = '';
            break;

        case 'folder view':
            document.getElementById('folder_view_pages_row').style.display = '';
            document.getElementById('folder_view_files_row').style.display = '';
            break;

        case 'photo gallery':
            document.getElementById('photo_gallery_number_of_columns_row').style.display = '';
            document.getElementById('photo_gallery_thumbnail_max_size_row').style.display = '';
            break;

        case 'update address book':
            document.getElementById('update_address_book_address_type_row').style.display = '';
            break;

        case 'custom form':
            document.getElementById('custom_form_form_name_row').style.display = '';
            document.getElementById('custom_form_enabled_row').style.display = '';
            document.getElementById('custom_form_quiz_row').style.display = '';

            document.getElementById('custom_form_label_column_width_row').style.display = '';
            document.getElementById('custom_form_watcher_page_id_row').style.display = '';
            document.getElementById('custom_form_save_row').style.display = '';
            document.getElementById('custom_form_submit_button_label_row').style.display = '';
            document.getElementById('custom_form_auto_registration_row').style.display = '';

            // If hook code row exists (i.e. user is a designer or administrator and hooks are enabled),
            // then show row.
            if (document.getElementById('custom_form_hook_code_row')) {
                document.getElementById('custom_form_hook_code_row').style.display = '';
            }

            document.getElementById('custom_form_submitter_email_row').style.display = '';
            document.getElementById('custom_form_administrator_email_row').style.display = '';


            document.getElementById('custom_form_contact_group_id_row').style.display = '';
            document.getElementById('custom_form_membership_row').style.display = '';



            document.getElementById('custom_form_private_row').style.display = '';



            // If grant offer rows exist (i.e. commerce is enabled and user has access to commerce),
            // then show them.
            if (document.getElementById('custom_form_offer_row')) {
                document.getElementById('custom_form_offer_row').style.display = '';
            }

            document.getElementById('custom_form_confirmation_type_row').style.display = '';

            show_or_hide_custom_form_confirmation_type();

            document.getElementById('custom_form_return_type_row').style.display = '';

            show_or_hide_custom_form_return_type();

            document.getElementById('custom_form_pretty_urls_row').style.display = '';

            break;

        case 'custom form confirmation':
            document.getElementById('custom_form_confirmation_continue_button_label_row').style.display = '';
            document.getElementById('custom_form_confirmation_next_page_id_row').style.display = '';
            break;

        case 'form list view':
            document.getElementById('form_list_view_custom_form_page_id_row').style.display = '';
            document.getElementById('form_list_view_form_item_view_page_id_row').style.display = '';
            document.getElementById('form_list_view_viewer_filter_row').style.display = '';
            break;

        case 'form item view':
            document.getElementById('form_item_view_custom_form_page_id_row').style.display = '';
            document.getElementById('form_item_view_submitter_security_row').style.display = '';
            document.getElementById('form_item_view_submitted_form_editable_by_registered_user_row').style.display = '';
            document.getElementById('form_item_view_submitted_form_editable_by_submitter_row').style.display = '';
            // If hook code row exists (i.e. user is a designer or administrator and hooks are enabled),
            // then show row.
            if (document.getElementById('form_item_view_hook_code_row')) {
                document.getElementById('form_item_view_hook_code_row').style.display = '';
            }

            break;

        case 'form view directory':
            document.getElementById('form_view_directory_form_list_views_row').style.display = '';
            document.getElementById('form_view_directory_summary_row').style.display = '';
            document.getElementById('form_view_directory_form_list_view_heading_row').style.display = '';
            document.getElementById('form_view_directory_subject_heading_row').style.display = '';
            document.getElementById('form_view_directory_number_of_submitted_forms_heading_row').style.display = '';
            break;

        case 'calendar view':
            document.getElementById('calendar_view_calendars_row').style.display = '';
            document.getElementById('calendar_view_default_view_row').style.display = '';
            document.getElementById('calendar_view_calendar_event_view_page_id_row').style.display = '';

            show_or_hide_calendar_view_number_of_upcoming_events();
            break;

        case 'calendar event view':
            document.getElementById('calendar_event_view_calendars_row').style.display = '';
            document.getElementById('calendar_event_view_notes_row').style.display = '';
            document.getElementById('calendar_event_view_back_button_label_row').style.display = '';
            break;

        case 'catalog':
            document.getElementById('catalog_product_group_id_row').style.display = '';
            document.getElementById('catalog_menu_row').style.display = '';
            document.getElementById('catalog_search_row').style.display = '';
            document.getElementById('catalog_number_of_featured_items_row').style.display = '';
            document.getElementById('catalog_number_of_new_items_row').style.display = '';
            document.getElementById('catalog_number_of_columns_row').style.display = '';
            document.getElementById('catalog_image_width_row').style.display = '';
            document.getElementById('catalog_image_height_row').style.display = '';
            document.getElementById('catalog_back_button_label_row').style.display = '';
            document.getElementById('catalog_catalog_detail_page_id_row').style.display = '';
            break;

        case 'catalog detail':
            document.getElementById('catalog_detail_allow_customer_to_add_product_to_order_row').style.display = '';
            document.getElementById('catalog_detail_back_button_label_row').style.display = '';
            break;

        case 'express order':
            document.getElementById('express_order_shopping_cart_label_row').style.display = '';
            document.getElementById('express_order_quick_add_label_row').style.display = '';
            document.getElementById('express_order_quick_add_product_group_id_row').style.display = '';
            document.getElementById('express_order_product_description_type_row').style.display = '';
            document.getElementById('express_order_shipping_form_row').style.display = '';
            document.getElementById('express_order_special_offer_code_label_row').style.display = '';
            document.getElementById('express_order_special_offer_code_message_row').style.display = '';
            document.getElementById('express_order_custom_field_1_label_row').style.display = '';
            document.getElementById('express_order_custom_field_2_label_row').style.display = '';
            document.getElementById('express_order_po_number_row').style.display = '';
            document.getElementById('express_order_form_row').style.display = '';
            show_or_hide_express_order_custom_billing_form();
            document.getElementById('express_order_card_verification_number_page_id_row').style.display = '';

            if (document.getElementById('express_order_offline_payment_always_allowed_row')) {
                document.getElementById('express_order_offline_payment_always_allowed_row').style.display = '';
                document.getElementById('express_order_offline_payment_label_row').style.display = '';
            }

            document.getElementById('express_order_terms_page_id_row').style.display = '';
            document.getElementById('express_order_update_button_label_row').style.display = '';
            document.getElementById('express_order_purchase_now_button_label_row').style.display = '';
            document.getElementById('express_order_auto_registration_row').style.display = '';

            // If hook code rows exists (i.e. user is a designer or administrator and hooks are enabled),
            // then show rows.
            if (document.getElementById('express_order_pre_save_hook_code_row')) {
                document.getElementById('express_order_pre_save_hook_code_row').style.display = '';
                document.getElementById('express_order_post_save_hook_code_row').style.display = '';
            }

            document.getElementById('express_order_order_receipt_email_row').style.display = '';
            document.getElementById('express_order_next_page_id_row').style.display = '';
            break;

        case 'order form':
            document.getElementById('order_form_product_group_id_row').style.display = '';
            document.getElementById('order_form_product_layout_row_1').style.display = '';
            document.getElementById('order_form_product_layout_row_2').style.display = '';
            document.getElementById('order_form_add_button_row').style.display = '';
            document.getElementById('order_form_skip_button_row').style.display = '';
            break;

        case 'search results':
            // If search folder row exists (i.e. advanced search is enabled), then show it.
            if (document.getElementById('search_results_search_folder_id_row')) {
                document.getElementById('search_results_search_folder_id_row').style.display = '';
            }

            // if e-commerce is on, then show e-commerce fields for search results
            if (document.getElementById('search_results_search_catalog_items_row')) {
                document.getElementById('search_results_search_catalog_items_row').style.display = '';
            }
            break;

        case 'shopping cart':
            document.getElementById('shopping_cart_shopping_cart_label_row').style.display = '';
            document.getElementById('shopping_cart_quick_add_label_row').style.display = '';
            document.getElementById('shopping_cart_quick_add_product_group_id_row').style.display = '';
            document.getElementById('shopping_cart_product_description_type_row').style.display = '';
            document.getElementById('shopping_cart_special_offer_code_label_row').style.display = '';
            document.getElementById('shopping_cart_special_offer_code_message_row').style.display = '';
            document.getElementById('shopping_cart_update_button_label_row').style.display = '';
            document.getElementById('shopping_cart_checkout_button_label_row').style.display = '';

            // If hook code row exists (i.e. user is a designer or administrator and hooks are enabled),
            // then show row.
            if (document.getElementById('shopping_cart_hook_code_row')) {
                document.getElementById('shopping_cart_hook_code_row').style.display = '';
            }

            document.getElementById('shopping_cart_next_page_id_with_shipping_row').style.display = '';
            document.getElementById('shopping_cart_next_page_id_without_shipping_row').style.display = '';
            break;

        case 'shipping address and arrival':
            document.getElementById('shipping_address_and_arrival_address_type_row').style.display = '';
            document.getElementById('shipping_address_and_arrival_form_row').style.display = '';
            show_or_hide_custom_shipping_form();
            document.getElementById('shipping_address_and_arrival_submit_button_row').style.display = '';
            break;

        case 'shipping method':
            document.getElementById('shipping_method_product_description_type_row').style.display = '';
            document.getElementById('shipping_method_submit_button_row').style.display = '';
            break;

        case 'billing information':
            document.getElementById('billing_information_custom_field_1_label_row').style.display = '';
            document.getElementById('billing_information_custom_field_2_label_row').style.display = '';
            document.getElementById('billing_information_po_number_row').style.display = '';
            document.getElementById('billing_information_form_row').style.display = '';
            show_or_hide_billing_information_custom_billing_form();
            document.getElementById('billing_information_submit_button_label_row').style.display = '';
            document.getElementById('billing_information_next_page_id_row').style.display = '';
            break;

        case 'order preview':
            document.getElementById('order_preview_product_description_type_row').style.display = '';
            document.getElementById('order_preview_card_verification_number_page_id_row').style.display = '';

            if (document.getElementById('order_preview_offline_payment_always_allowed_row')) {
                document.getElementById('order_preview_offline_payment_always_allowed_row').style.display = '';
                document.getElementById('order_preview_offline_payment_label_row').style.display = '';
            }

            document.getElementById('order_preview_terms_page_id_row').style.display = '';
            document.getElementById('order_preview_submit_button_label_row').style.display = '';
            document.getElementById('order_preview_auto_registration_row').style.display = '';

            // If hook code rows exists (i.e. user is a designer or administrator and hooks are enabled),
            // then show rows.
            if (document.getElementById('order_preview_pre_save_hook_code_row')) {
                document.getElementById('order_preview_pre_save_hook_code_row').style.display = '';
                document.getElementById('order_preview_post_save_hook_code_row').style.display = '';
            }

            document.getElementById('order_preview_order_receipt_email_row').style.display = '';

            document.getElementById('order_preview_next_page_id_row').style.display = '';
            break;

        case 'order receipt':
            document.getElementById('order_receipt_product_description_type_row').style.display = '';
            break;

        case 'affiliate sign up form':
            document.getElementById('affiliate_sign_up_form_terms_page_id_row').style.display = '';
            document.getElementById('affiliate_sign_up_form_submit_button_label_row').style.display = '';
            document.getElementById('affiliate_sign_up_form_next_page_id_row').style.display = '';
            break;
    }

    // if the selected page type is a valid page type for the sitemap, then show sitemap row
    if (
        (page_type == 'standard') ||
        (page_type == 'folder view') ||
        (page_type == 'photo gallery') ||
        (page_type == 'custom form') ||
        (page_type == 'form list view') ||
        (page_type == 'form item view') ||
        (page_type == 'form view directory') ||
        (page_type == 'calendar view') ||
        (page_type == 'calendar event view') ||
        (page_type == 'catalog') ||
        (page_type == 'catalog detail') ||
        (page_type == 'express order') ||
        (page_type == 'order form') ||
        (page_type == 'shopping cart') ||
        (page_type == 'search results')
    ) {

        document.getElementById('sitemap_row').style.display = '';
    } else {
        document.getElementById('sitemap_row').style.display = 'none';
    }
    // Page types that do not support any options, we dont output options row.
    if (
        (page_type == 'standard') ||
        (page_type == 'error') ||
        (page_type == 'logout') ||
        (page_type == 'registration confirmation') ||
        (page_type == 'membership confirmation') ||
        (page_type == 'affiliate sign up confirmation') ||
        (page_type == 'affiliate welcome')
    ) {
        $('#options_row').removeClass('show');
    } else {
        $('#options_row').addClass('show');
    }

    // if the comment fields exist (e.g. edit page screen, not create page screen), then show or hide the form item view comment fields
    if (document.getElementById('comments')) {
        show_or_hide_form_item_view_comment_fields();
    }

    // If a custom form was just enabled,
    // then update the submit button to contain "Save & Continue"
    if (
        ((page_type == 'custom form') && (original_page_type != 'custom form')) ||
        ((page_type == 'shipping address and arrival') && (document.getElementById('shipping_address_and_arrival_form').checked == true) && (original_shipping_address_and_arrival_form != 1)) ||
        ((page_type == 'billing information') && (document.getElementById('billing_information_form').checked == true) && (original_billing_information_form != 1)) ||
        (
            (page_type == 'express order') &&
            (
                (document.getElementById('express_order_shipping_form').checked && original_express_order_shipping_form != 1) ||
                (document.getElementById('express_order_form').checked && original_express_order_form != 1)
            )
        )
    ) {

        $("#create_button").val("Save & Continue");
        $("#create_button .pg-btn-label").text(lang("Save & Continue"));

        // else the submit button should contain the normal "Save"
    } else {
        $("#create_button").val("Save");
        $("#create_button .pg-btn-label").text(lang("Save"));
    }
}

function show_or_hide_billing_information_custom_billing_form() {
    if (document.getElementById('billing_information_form').checked == true) {
        document.getElementById('billing_information_form_name_row').style.display = '';
        document.getElementById('billing_information_form_label_column_width_row').style.display = '';

    } else {
        document.getElementById('billing_information_form_name_row').style.display = 'none';
        document.getElementById('billing_information_form_label_column_width_row').style.display = 'none';
    }

    // if the form is enabled and the form was not originally enabled, then show notice and update the submit button to contain "Save & Continue"
    if ((document.getElementById('billing_information_form').checked == true) && (original_billing_information_form != 1)) {
        document.getElementById('billing_information_form_notice').style.display = '';
        $("#create_button").val("Save & Continue");
        $("#create_button .pg-btn-label").text(lang("Save & Continue"));

        // else the form is disabled or the form was already enabled, so do not show notice and update the submit button to contain "Save"
    } else {
        document.getElementById('billing_information_form_notice').style.display = 'none';
        $("#create_button").val("Save");
        $("#create_button .pg-btn-label").text(lang("Save"));
    }
}

function toggle_express_order_custom_shipping_form() {

    // If the form is enabled and the form was not originally enabled, then show notice and update
    // the submit button to contain "Save & Continue"
    if (
        document.getElementById('express_order_shipping_form').checked &&
        original_express_order_shipping_form != 1
    ) {
        document.getElementById('express_order_shipping_form_notice').style.display = '';
        $("#create_button").val("Save & Continue");
        $("#create_button .pg-btn-label").text(lang("Save & Continue"));
        // Otherwise the form is disabled or the form was already enabled, so do not show notice and
        // update the submit button to contain "Save"
    } else {
        document.getElementById('express_order_shipping_form_notice').style.display = 'none';
        $("#create_button").val("Save");
        $("#create_button .pg-btn-label").text(lang("Save"));
    }
}

function show_or_hide_express_order_custom_billing_form() {
    if (document.getElementById('express_order_form').checked == true) {
        document.getElementById('express_order_form_name_row').style.display = '';
        document.getElementById('express_order_form_label_column_width_row').style.display = '';
    } else {
        document.getElementById('express_order_form_name_row').style.display = 'none';
        document.getElementById('express_order_form_label_column_width_row').style.display = 'none';
    }

    // if the form is enabled and the form was not originally enabled, then show notice and update the submit button to contain "Save & Continue"
    if ((document.getElementById('express_order_form').checked == true) && (original_express_order_form != 1)) {
        document.getElementById('express_order_form_notice').style.display = '';
        $("#create_button").val("Save & Continue");
        $("#create_button .pg-btn-label").text(lang("Save & Continue"));

        // else the form is disabled or the form was already enabled, so do not show notice and update the submit button to contain "Save"
    } else {
        document.getElementById('express_order_form_notice').style.display = 'none';
        $("#create_button").val("Save");
        $("#create_button .pg-btn-label").text(lang("Save"));
        // we check if its checked before
        toggle_express_order_custom_shipping_form();
    }
}

function show_or_hide_custom_shipping_form() {
    if (document.getElementById('shipping_address_and_arrival_form').checked == true) {
        document.getElementById('shipping_address_and_arrival_form_name_row').style.display = '';
        document.getElementById('shipping_address_and_arrival_form_label_column_width_row').style.display = '';
    } else {
        document.getElementById('shipping_address_and_arrival_form_name_row').style.display = 'none';
        document.getElementById('shipping_address_and_arrival_form_label_column_width_row').style.display = 'none';
    }

    // if the form is enabled and the form was not originally enabled, then show notice and update the submit button to contain "Save & Continue"
    if ((document.getElementById('shipping_address_and_arrival_form').checked == true) && (original_shipping_address_and_arrival_form != 1)) {
        document.getElementById('shipping_address_and_arrival_form_notice').style.display = '';
        $("#create_button").val("Save & Continue");
        $("#create_button .pg-btn-label").text(lang("Save & Continue"));
        // else the form is disabled or the form was already enabled, so do not show notice and update the submit button to contain "Save"
    } else {
        document.getElementById('shipping_address_and_arrival_form_notice').style.display = 'none';
        $("#create_button").val("Save");
        $("#create_button .pg-btn-label").text(lang("Save"));
    }
}

function show_or_hide_custom() {
    if (document.getElementById('custom').checked == true) {
        document.getElementById('custom_maximum_arrival_date_row').style.display = '';
        document.getElementById('shipping_cutoff_heading_row').style.display = 'none';
        document.getElementById('shipping_cutoff_row').style.display = 'none';
    } else {
        document.getElementById('custom_maximum_arrival_date_row').style.display = 'none';
        document.getElementById('shipping_cutoff_heading_row').style.display = '';
        document.getElementById('shipping_cutoff_row').style.display = '';
    }
}

function show_or_hide_comments() {
    // if comments is checked then prepare to show rows
    if (document.getElementById('comments').checked == true) {
        document.getElementById('comments_administrator_email_row').style.display = '';

        show_or_hide_form_item_view_comment_fields();

        document.getElementById('comments_watcher_email_row').style.display = '';

        // else hide all rows
    } else {
        document.getElementById('comments_administrator_email_row').style.display = 'none';
        document.getElementById('comments_administrator_email_conditional_administrators_row').style.display = 'none';
        document.getElementById('comments_submitter_email_row').style.display = 'none';
        document.getElementById('comments_watcher_email_row').style.display = 'none';
        document.getElementById('comments_watchers_managed_by_submitter_row').style.display = 'none';
    }
}

function show_or_hide_form_item_view_comment_fields() {
    // get page type
    var page_type = document.getElementById('page_type').options[document.getElementById('page_type').selectedIndex].value;

    // if comments are enabled and the page type is form item view then show rows
    if ((document.getElementById('comments').checked == true) && (page_type == 'form item view')) {
        document.getElementById('comments_administrator_email_conditional_administrators_row').style.display = '';
        document.getElementById('comments_submitter_email_row').style.display = '';
        document.getElementById('comments_watchers_managed_by_submitter_row').style.display = '';

        // else hide rows
    } else {
        document.getElementById('comments_administrator_email_conditional_administrators_row').style.display = 'none';
        document.getElementById('comments_submitter_email_row').style.display = 'none';
        document.getElementById('comments_watchers_managed_by_submitter_row').style.display = 'none';
    }
}

function show_or_hide_custom_form_confirmation_type() {
    // Start off by hiding all rows under the confirmation type field until we determine which should be shown.
    // If the message option is selected, then show the message row
    if (document.getElementById('custom_form_confirmation_type_message').checked == true) {

        // If the rich-text editor has not been loaded already for the message field, then load it.
        if ((typeof tinyMCE !== 'undefined') && (tinyMCE.getInstanceById('custom_form_confirmation_message') == null)) {
            tinyMCE.execCommand('mceAddControl', false, 'custom_form_confirmation_message');
        }
    }
}

function show_or_hide_custom_form_return_type() {
    // If the message option is selected, then show the message row
    if (document.getElementById('custom_form_return_type_message').checked == true) {

        // If the rich-text editor has not been loaded already for the message field, then load it.
        if ((typeof tinyMCE !== 'undefined') && (tinyMCE.getInstanceById('custom_form_return_message') == null)) {
            tinyMCE.execCommand('mceAddControl', false, 'custom_form_return_message');
        }
    }
}

function show_or_hide_calendar_view_number_of_upcoming_events() {
    if ((document.getElementById('calendar_view_default_view').options[document.getElementById('calendar_view_default_view').selectedIndex].value == 'upcoming') &&
        (document.getElementById('calendar_view_number_of_upcoming_events_row').style.display == 'none')) {
        document.getElementById('calendar_view_number_of_upcoming_events_row').style.display = '';

    } else {
        document.getElementById('calendar_view_number_of_upcoming_events_row').style.display = 'none';
    }
}

function change_order_by(number) {
    var order_by = document.getElementById('order_by_' + number).options[document.getElementById('order_by_' + number).selectedIndex].value;

    // if order by is blank or random, then hide ascending/descending pick list
    if ((order_by == '') || (order_by == 'random')) {
        document.getElementById('order_by_' + number + '_type').style.display = 'none';

        // else order by is not blank or random, so show ascending/descending pick list
    } else {
        document.getElementById('order_by_' + number + '_type').style.display = 'inline';
    }
}

function show_or_hide_edit_form_list_view_browse_field(field_id) {
    if (document.getElementById('browse_field_' + field_id).checked == true) {
        document.getElementById('browse_field_' + field_id + '_number_of_columns_cell').style.display = '';
        document.getElementById('browse_field_' + field_id + '_sort_order_cell').style.display = '';
        document.getElementById('browse_field_' + field_id + '_shortcut_cell').style.display = '';

        // If there is a date format field (i.e. field has a date or date and time type)
        // then show that cell also.
        if (document.getElementById('browse_field_' + field_id + '_date_format')) {
            document.getElementById('browse_field_' + field_id + '_date_format_cell').style.display = '';
        }

    } else {
        document.getElementById('browse_field_' + field_id + '_number_of_columns_cell').style.display = 'none';
        document.getElementById('browse_field_' + field_id + '_sort_order_cell').style.display = 'none';
        document.getElementById('browse_field_' + field_id + '_shortcut_cell').style.display = 'none';
        document.getElementById('browse_field_' + field_id + '_date_format_cell').style.display = 'none';
    }
}

function change_short_link_destination_type(destination_type) {
    // Hide all rows until we determine which need to be shown.
    document.getElementById('page_id_row').style.display = 'none';
    document.getElementById('catalog_page_id_row').style.display = 'none';
    document.getElementById('or_row').style.display = 'none';
    document.getElementById('catalog_detail_page_id_row').style.display = 'none';
    document.getElementById('product_group_id_row').style.display = 'none';
    document.getElementById('product_id_row').style.display = 'none';
    document.getElementById('url_row').style.display = 'none';
    document.getElementById('tracking_code_row').style.display = 'none';
    document.getElementById('file_row').style.display = 'none';

    // Show certain rows based on which destination type was selected.
    switch (destination_type) {
        case 'page':
            document.getElementById('page_id_row').style.display = '';
            document.getElementById('tracking_code_row').style.display = '';
            break;

        case 'product_group':
            document.getElementById('catalog_page_id_row').style.display = '';
            document.getElementById('or_row').style.display = '';
            document.getElementById('catalog_detail_page_id_row').style.display = '';
            document.getElementById('product_group_id_row').style.display = '';
            document.getElementById('tracking_code_row').style.display = '';
            break;

        case 'product':
            document.getElementById('catalog_detail_page_id_row').style.display = '';
            document.getElementById('product_id_row').style.display = '';
            document.getElementById('tracking_code_row').style.display = '';
            break;

        case 'url':
            document.getElementById('url_row').style.display = '';
            break;

        case 'file':
            document.getElementById('file_row').style.display = '';
            break;
    }
}

// Setup edit comment publish pick list functionality and date/time picker.
function init_edit_comment_publish() {
    var publish = $('#publish');
    var publish_date_and_time = $('#publish_date_and_time');
    var publish_schedule = $('#publish_schedule');

    // The date and time field is a data-pg-datetime hook (pg_fields.js).
    // If the schedule option is selected by default when the page first loaded,
    // then show fields for it.
    if (publish.val() == 'schedule') {
        publish_schedule.fadeIn();
    }

    // When the publish pick list is changed, then update fields.
    publish.change(function () {
        // If the schedule option is selected, then show fields for it.
        if (publish.val() == 'schedule') {
            publish_schedule.fadeIn();

            // Focus the box of the date and time field, which opens its
            // calendar; the field itself is the hidden store.
            if (window.pgFields) {
                window.pgFields.focus(publish_date_and_time[0]);
            }

            // Otherwise the schedule option is not selected, so hide its fields.
        } else {
            publish_schedule.fadeOut();
        }
    });
}



// Create a function that will be used to set the start and end time fields
// for calendar events so they either accept both a date & time if "all day" is disabled
// or just a date if "all day" is enabled.
function toggle_calendar_event_all_day() {
    // If all day is checked, then prepare start and end date fields to just contain dates.
    if (document.getElementById('all_day').checked == true) {
        document.getElementById('start_time_label').style.display = 'none';
        document.getElementById('end_time_label').style.display = 'none';
        document.getElementById('show_start_time_container').style.display = 'none';
        document.getElementById('show_end_time_container').style.display = 'none';

        // Turn both date and time fields into date fields that keep only
        // the date of what they held.
        ['start_time', 'end_time'].forEach(function (id) {
            var field = document.getElementById(id);
            var date = pgDateTimeField.get(field).slice(0, 10);

            pgFields.switchKind(field, 'date');
            pgDatePicker.set(field, date);
        });

        // Update the size and maxlength of the fields to support just a date.
        $('#start_time').attr('size', 10);
        $('#start_time').attr('maxlength', 10);
        $('#end_time').attr('size', 10);
        $('#end_time').attr('maxlength', 10);

        // Otherwise all day is not checked, so prepare start and end date fields to contain both dates and times.
    } else {
        document.getElementById('start_time_label').style.display = '';
        document.getElementById('end_time_label').style.display = '';
        document.getElementById('show_start_time_container').style.display = '';
        document.getElementById('show_end_time_container').style.display = '';

        // Date and time pickers on both fields; the dates they hold stay.
        pgFields.switchKind(document.getElementById('start_time'), 'datetime');
        pgFields.switchKind(document.getElementById('end_time'), 'datetime');

        // Update the size and maxlength of the fields to support both a date and time.
        $('#start_time').attr('size', 19);
        $('#start_time').attr('maxlength', 19);
        $('#end_time').attr('size', 19);
        $('#end_time').attr('maxlength', 19);
    }
}

function toggle_calendar_event_recurrence() {
    // Assume that rows should be hidden until we find out otherwise.
    document.getElementById('recurrence_days_of_the_week_row').style.display = 'none';
    document.getElementById('recurrence_month_type_row').style.display = 'none';

    // If recurrence is checked, then determine which recurrence rows should be shown.
    if (document.getElementById('recurrence').checked == true) {
        change_calendar_event_recurrence_type();
        document.getElementById('number_of_initial_spots_row').style.display = '';
    } else {
        // if this is the edit calendar event screen, then determine if we should show or hide initial spots
        // the initial spots field is always displayed on the create calendar event screen, so that is why we don't have to deal with it
        if (document.getElementById('number_of_remaining_spots_row')) {
            document.getElementById('number_of_initial_spots_row').style.display = 'none';
        } else {
            document.getElementById('number_of_initial_spots_row').style.display = '';
        }

    }

    // if this is a recurring event and reservations is enabled then show separate reservations field
    if (
        (document.getElementById('recurrence').checked == true)
        && (document.getElementById('reservations').checked == true)
    ) {
        document.getElementById('separate_reservations_row').style.display = '';

        if (document.getElementById('number_of_remaining_spots_row')) {
            if (document.getElementById('separate_reservations').checked == true) {
                document.getElementById('number_of_initial_spots_row').style.display = '';
            } else {
                document.getElementById('number_of_initial_spots_row').style.display = 'none';
            }
        } else {
            document.getElementById('number_of_initial_spots_row').style.display = '';
        }

        // else separate reservations should not be shown, so hide it
    } else {
        document.getElementById('separate_reservations_row').style.display = 'none';
    }


}

function change_calendar_event_recurrence_type() {
    // Hide various recurrence rows until we find out which should be shown.
    document.getElementById('recurrence_days_of_the_week_row').style.display = 'none';
    document.getElementById('recurrence_month_type_row').style.display = 'none';

    // Show different rows depending on the selected recurrence type.
    switch (document.getElementById('recurrence_type').options[document.getElementById('recurrence_type').selectedIndex].value) {
        case 'day':
            document.getElementById('recurrence_days_of_the_week_row').style.display = '';
            break;

        case 'month':
            document.getElementById('recurrence_month_type_row').style.display = '';
            break;
    }
}

// resize iframe by its content.
//<iframe onload="resizeIframe(this)"></iframe>
function resizeIframe(obj) {
    obj.style.height = obj.contentWindow.document.documentElement.scrollHeight + 'px';
    obj.style.width = '100%';
}

// create row for shipping cut-off
function create_shipping_cutoff(properties) {
    // if no properties were passed, then set blank values
    if (!properties) {
        var properties = new Array();
        properties['shipping_method_id'] = '';
        properties['date_and_time'] = '';
    }

    // get shipping cut-off number by adding one to the current number of shipping cut-offs
    var shipping_cutoff_number = last_shipping_cutoff_number + 1;

    var tbody = document.getElementById('shipping_cutoff_table').getElementsByTagName('tbody')[0];
    var tr = document.createElement('tr');

    // prepare content for shipping method id cell
    var shipping_method_id_cell_html =
        '<select id="shipping_cutoff_' + shipping_cutoff_number + '_shipping_method_id" name="shipping_cutoff_' + shipping_cutoff_number + '_shipping_method_id" class="form-control">\n\
            <option value=""></option>';

    // loop through all shipping method id options in order to prepare options for pick list
    for (var i = 0; i < shipping_method_id_options.length; i++) {
        var status = '';

        // if this option should be selected by default, then select option by default
        if (properties['shipping_method_id'] == shipping_method_id_options[i]['value']) {
            status = ' selected="selected"';
        }

        shipping_method_id_cell_html += '<option value="' + shipping_method_id_options[i]['value'] + '"' + status + '>' + prepare_content_for_html(shipping_method_id_options[i]['name']) + '</option>';
    }

    shipping_method_id_cell_html += '</select>';

    // insert content into shipping method id cell
    var td_1 = document.createElement('td');
    td_1.innerHTML = shipping_method_id_cell_html;

    // prepare content for date and time cell
    var td_2 = document.createElement('td');
    td_2.innerHTML = '<input id="shipping_cutoff_' + shipping_cutoff_number + '_date_and_time" name="shipping_cutoff_' + shipping_cutoff_number + '_date_and_time" class="form-control" type="text" value="' + prepare_content_for_html(String(properties['date_and_time'] || '')) + '" size="20" maxlength="22" data-pg-datetime />';

    // prepare content for delete cell
    var td_3 = document.createElement('td');
    td_3.innerHTML = '<button type="button" class="btn btn-solid theme-danger material-icons" onclick="delete_shipping_cutoff(this.parentNode.parentNode)" >delete</button>';

    tr.appendChild(td_1);
    tr.appendChild(td_2);
    tr.appendChild(td_3);

    tbody.appendChild(tr);

    if (window.pgDateTimeField) {
        pgDateTimeField.init(document.getElementById('shipping_cutoff_' + shipping_cutoff_number + '_date_and_time'));
    }

    // show the shipping cut-off table in case it was hidden
    document.getElementById('shipping_cutoff_table').style.display = '';

    // update number of shipping cut-offs
    last_shipping_cutoff_number++;
    document.getElementById('last_shipping_cutoff_number').value = last_shipping_cutoff_number;
}
function delete_shipping_cutoff(tr) {
    tbody = tr.parentNode;
    tbody.removeChild(tr);

    // if there is only one row in the table, then it is the heading row, so hide the whole table
    if (document.getElementById('shipping_cutoff_table').getElementsByTagName('tr').length == 1) {
        document.getElementById('shipping_cutoff_table').style.display = 'none';
    }
}


function generateIndexNowKey() {
    try {
        // Prefer a real UUID when the browser supports it.
        if (typeof crypto.randomUUID === "function") {
            const uuidPart = crypto.randomUUID().replace(/-/g, "");
            const randomPart = Math.floor(Math.random() * 10000).toString().padStart(4, "0");
            const key = uuidPart + randomPart;

            const input = document.getElementById("indexnow_key");
            if (input) {
                input.value = key;
                input.classList.add("is-valid");
            } else {
                console.warn("IndexNow input field not found.");
            }
        } else {
            console.error("crypto.randomUUID is not supported in this browser.");
            alert(lang("Your browser does not support secure key generation. Please use a modern browser."));
        }
    } catch (error) {
        console.error("Error generating IndexNow key:", error);
        alert(lang("An error occurred while generating the key. Please try again."));
    }
}

// Handle error state specifically for backup process
function software_backup_handleError(response, backup_btn) {
    software_backup_updateProgress(0, response.message, true);
    backup_btn.prop("disabled", false);
}

// Update progress bar and log messages for backup process
function software_backup_updateProgress(percent, message, isError = false) {
    const Progress = $(".progress .progress-bar");
    const Progress_container = $(".progress");
    const LogBox = $(".logbox");

    Progress.attr("style", "width: " + percent + "%").attr("aria-valuenow", percent);
    Progress_container.removeClass("d-none");
    Progress.toggleClass("progress-bar-animated", percent < 100);

    LogBox.empty().append(message);
    if (isError) {
        LogBox.addClass("software_error").removeClass("software_notice");
    } else {
        LogBox.addClass("software_notice").removeClass("software_error");
    }
}

// Run backup steps sequentially
async function software_backup_runSteps(backup_name) {
    const backup_btn = $("#backup");
    const steps = [
        { step: "create_backup_folder", progress: 15 },
        { step: "create_mysql_dumb", progress: 30 },
        { step: "clear_files_and_layouts", progress: 45 },
        { step: "move_files", progress: 60 },
        { step: "move_layouts", progress: 75 },
        { step: "create_htaccess_and_config", progress: 90 },
        { step: "check", progress: 100 }
    ];

    for (const s of steps) {
        try {
            const response = await $.ajax({
                contentType: "application/json",
                url: "api.php",
                type: "POST",
                data: JSON.stringify({
                    action: "software_backup",
                    token: software_token,
                    step: s.step,
                    backup_name: backup_name
                })
            });

            if (response.status === "success") {
                software_backup_updateProgress(s.progress, response.message);
                backup_name = response.backup_name;
            } else {
                software_backup_handleError(response, backup_btn);
                return;
            }
        } catch (err) {
            software_backup_handleError({ message: lang("An error occurred.") }, backup_btn);
            return;
        }
    }
    location.reload();
}



// Initialize backup process
function software_backup_start() {
    const backup_btn = $("#backup");
    const backup_folder_name = $("#backup_folder_name").val();

    backup_btn.prop("disabled", true);

    software_backup_updateProgress(0, lang("Backup Builder Starting..."));
    software_backup_runSteps(backup_folder_name);
}



// ─── SEO Character Counter ────────────────────────────────────────────────────
/* ════════════════════════════════════════════════════════════════════════
   BARCODE LABEL TEMPLATE EDITOR (edit_product.php)
   Opens a Bootstrap modal containing the PgBarcode LabelEditor.
   Requires pinegrap-barcode.js (loaded on pages that use this function).
════════════════════════════════════════════════════════════════════════ */
function editBarcodeTemplate(opts) {
    // opts = window._pgBarcodeOpts: { productId, barcodeValue, shortDescription,
    //         sku, price, attributes, labelTemplate, productImageSrc, apiToken }
    opts = opts || {};
    var _L = (typeof pgLang !== 'undefined') ? pgLang : function (k) { return k; };

    if (typeof window.PgBarcode === 'undefined') {
        alert(_L('Label Designer') + ': ' + _L('Library not loaded.'));
        return;
    }

    var MODAL_ID = 'pg-bc-template-modal';
    var $modal = $('#' + MODAL_ID);

    // ── Build modal HTML once ──────────────────────────────────────────
    if (!$modal.length) {
        $('body').append(
            '<dialog class="dialog dialog-xl" id="' + MODAL_ID + '" aria-labelledby="pg-bc-template-title" data-bs-backdrop="static">' +
            '<div class="dialog-header py-3">' +
            '<h5 class="dialog-title fs-md fw-semibold" id="pg-bc-template-title"><i class="bi bi-upc-scan me-3"></i>' + _L('Label Designer') + '</h5>' +
            '<button type="button" class="btn-close" data-bs-dismiss="dialog" aria-label="' + h(_L('Close')) + '"></button>' +
            '</div>' +
            '<div class="dialog-body">' +
            '<div class="mb-5">' +
            '<small class="fg-secondary d-block mb-3">' + _L('Drag fields to canvas') + ':</small>' +
            '<div id="pg-bc-field-sources" class="d-flex flex-wrap gap-3"></div>' +
            '</div>' +
            '<div id="pg-bc-editor-wrap"></div>' +
            '</div>' +
            '<div class="dialog-footer py-3 gap-3">' +
            '<button id="pg-bc-reset-btn" type="button" class="btn btn-outline theme-secondary btn-sm">' + _L('Reset to Default') + '</button>' +
            '<button data-bs-dismiss="dialog" type="button" class="btn btn-solid theme-secondary btn-sm">' + _L('Cancel') + '</button>' +
            '<button id="pg-bc-save-btn" type="button" class="btn btn-solid theme-primary btn-sm">' + _L('Save Template') + '</button>' +
            '</div>' +
            '</dialog>'
        );
        $modal = $('#' + MODAL_ID);
    }

    // ── Field source chips (draggable onto canvas) ─────────────────────
    var $sources = $('#pg-bc-field-sources').empty();
    var fieldDefs = [
        { key: 'sku', label: _L('SKU'), icon: 'bi-upc' },
        { key: 'short_description', label: _L('Product Name'), icon: 'bi-tag' },
        { key: 'attributes', label: _L('Attributes'), icon: 'bi-list-ul' },
        { key: 'product_image', label: _L('Product Image'), icon: 'bi-image' }
    ];
    fieldDefs.forEach(function (f) {
        var $chip = $('<span class="badge border border-subtle-secondary fg-emphasis-secondary fw-normal px-3 py-1 d-inline-flex align-items-center gap-1" draggable="true" style="cursor:grab;user-select:none;">' +
            '<i class="bi ' + f.icon + '"></i>' + f.label + '</span>');
        $chip[0].addEventListener('dragstart', function (e) {
            e.dataTransfer.setData('pg-field', f.key);
            e.dataTransfer.effectAllowed = 'copy';
        });
        $sources.append($chip);
    });

    // ── Show modal, init editor after it's visible ─────────────────────
    var bsModal = pgUI.dialog.get(document.getElementById(MODAL_ID));

    pgUI.dialog.on($modal, 'shown', function () {
        var wrap = document.getElementById('pg-bc-editor-wrap');
        wrap.innerHTML = '';

        var editor = new window.PgBarcode.LabelEditor(wrap, {
            barcodeValue: opts.barcodeValue || '1234567890123',
            productImageSrc: opts.productImageSrc || '',
            fieldValues: {
                short_description: opts.shortDescription || 'Product Name',
                sku: opts.sku || 'SKU-001',
                price: opts.price || '0.00',
                attributes: opts.attributes || ''
            }
        });

        // Load existing template (or DEFAULT_TEMPLATE if none saved)
        editor.loadTemplate(opts.labelTemplate || null);

        // Reset button
        $('#pg-bc-reset-btn').off('click').on('click', function () {
            if (confirm(_L('Reset to default template?'))) {
                editor.loadTemplate(null);
            }
        });

        // Save button
        $('#pg-bc-save-btn').off('click').on('click', function () {
            var $btn = $(this);
            $btn.prop('disabled', true);
            var templateJson = JSON.stringify(editor.getTemplate());

            var apiUrl = pg_api_url();

            fetch(apiUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'save_barcode_template', template: templateJson, token: opts.apiToken || '' })
            })
                .then(function (r) { return r.json(); })
                .then(function (res) {
                    if (res.status === 'success') {
                        // Update in-page opts so Print uses the new template immediately
                        if (window._pgBarcodeOpts) {
                            window._pgBarcodeOpts.labelTemplate = templateJson;
                        }
                        bsModal.hide();
                    } else {
                        alert(res.message || _L('Error saving template.'));
                        $btn.prop('disabled', false);
                    }
                })
                .catch(function () {
                    alert(_L('Network error.'));
                    $btn.prop('disabled', false);
                });
        });
    }, { once: true });

    // Re-enable the save button each time the modal opens
    pgUI.dialog.on($modal, 'show', function () {
        $('#pg-bc-save-btn').prop('disabled', false);
    }, { once: true });

    bsModal.show();
}

/* ════════════════════════════════════════════════════════════════════════
   PRODUCT BARCODE PRINT  (view_products.php)
   Fetches the first barcode for a product and opens a print window.
   Page must set window._pgViewBarcodeOpts = { apiUrl, token, labelTemplate };
   the button carries what the label prints in data-pg-barcode-product (JSON:
   sku, short_description, price, productImageSrc).
════════════════════════════════════════════════════════════════════════ */
function pgPrintProductBarcode(productId, button) {
    var opts = window._pgViewBarcodeOpts || {};
    var p = {};

    try {
        p = JSON.parse((button && button.getAttribute('data-pg-barcode-product')) || '{}') || {};
    } catch (e) {
        p = {};
    }

    /*
     * The print window has to be opened synchronously, while the click that started
     * this call is still the active user gesture. Safari (macOS and iOS) and Firefox
     * drop the gesture as soon as a network round trip is awaited, so opening the
     * window inside the fetch callback was silently blocked there while it happened
     * to survive in Chrome. Open first, fill in once the barcode arrives.
     */
    var w = window.open('', '_blank', 'width=600,height=400');
    if (!w) {
        alert(pgLang('Please allow pop-up windows for this site to print barcodes.'));
        return;
    }
    w.document.write('<!DOCTYPE html><html><head><meta charset="utf-8"></head><body></body></html>');

    var closeOnFailure = function () {
        try { w.close(); } catch (e) { }
    };

    fetch(opts.apiUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'get_product_barcodes', product_id: productId, token: opts.token })
    })
        .then(function (r) { return r.json(); })
        .then(function (res) {
            if (res.status !== 'success' || !res.barcodes || !res.barcodes.length) {
                closeOnFailure();
                return;
            }
            var b = res.barcodes[0];
            var tmpWrap = document.createElement('div');
            tmpWrap.style.cssText = 'position:absolute;left:-9999px;';
            document.body.appendChild(tmpWrap);
            var editor = new window.PgBarcode.LabelEditor(tmpWrap, { labelTemplate: opts.labelTemplate || null });
            editor.loadTemplate(opts.labelTemplate || null);
            var html = editor.buildPrintHTML({
                barcode: b.barcode,
                barcodeType: b.barcode_type,
                sku: p.sku || '',
                short_description: p.short_description || '',
                price: p.price || '',
                attributes: '',
                productImageSrc: p.productImageSrc || ''
            });
            document.body.removeChild(tmpWrap);
            w.document.open();
            w.document.write(html);
            w.document.close();
        })
        .catch(closeOnFailure);
}

// System Status widget: the jobs column.
//
// The widget is drawn by api.php and injected into whatever screen asks for
// it, so these handlers are delegated from document - a directly bound one
// would run before the rows exist and never see them. That is also why the
// software-wide confirm handler further up cannot serve them: it binds at DOM
// ready to the anchors that are on the page at that moment.
//
// Nothing here is scoped to a screen. The widget is the dashboard's, and the
// jobs travel with it wherever it is drawn.
$(function () {

    // Anything on the card that asks before it acts. The question text rides on
    // the control so the wording stays with the thing being confirmed, and
    // stays translated by PHP.
    $(document).on('click', '.pg-job-btn[data-confirm-content]', function (event) {
        if (!window.confirm($(this).attr('data-confirm-content'))) {
            event.preventDefault();
        }
    });

    // Redrawing the whole card once a job has changed what it reports.
    //
    // The alternative was to patch the row that was pressed, which is wrong
    // twice over. All three jobs delete the ten-minute status file, so the
    // score, the arc, its colour and every tile behind it go stale together --
    // and writing the rules also decides whether the Fix button should still be
    // drawn at all. One request answers all of it, and it is the same request
    // welcome.php makes.
    //
    // The answer the operator is reading would be thrown away with the old
    // markup, so it is handed back in a callback and written into the fresh
    // panel. The ids are stable across a redraw, which is what makes that
    // possible.
    //
    // welcome.php keeps widget 2 out of its periodic refresh so that nothing
    // closes an open panel under the operator's hand. This is the deliberate
    // exception: it happens because they pressed something.
    window.pgHealthReload = function (after) {

        var $card = $('.card[widget-id="2"]');

        if ($card.length === 0) {
            if (typeof after === 'function') {
                after();
            }
            return;
        }

        $.ajax({
            contentType: 'application/json',
            url: 'api.php',
            type: 'POST',
            data: JSON.stringify({
                action: 'get_widget_data',
                token: software_token,
                widget_id: '2'
            }),
            success: function (response) {
                if (response.status === 'success') {
                    $card.find('.card-body-placeholder,.card-body,.card-footer').remove();
                    $card.append(response.data);

                    // The card brings a fresh notification row with it, blank
                    // and hidden. Only the browser knows what belongs in it.
                    if (typeof pg_push_refresh === 'function') {
                        pg_push_refresh();
                    }
                }
            },
            // Whether or not the redraw worked, the report has to land
            // somewhere: a job that finished and said nothing reads as a job
            // that did nothing.
            complete: function () {
                if (typeof after === 'function') {
                    after();
                }
            }
        });
    };

    // The three jobs that run over ajax share one shape: say what is happening,
    // refuse a second press, redraw the card, and put the answer in the panel
    // under their own row rather than in an alert -- it is a report, it names
    // files and counts, and the operator may want to read it twice.
    function pg_health_job($button, action) {

        var idle   = $button.attr('data-idle-label'),
            busy   = $button.attr('data-busy-label'),
            failed = $button.attr('data-failed-label'),
            state  = '#' + action + '_state',
            result = '#' + action + '_result',
            note   = '#' + action + '_message';

        $button.prop('disabled', true);
        $(state).text(busy);
        $(result).addClass('d-none');

        function settle(summary, message) {
            window.pgHealthReload(function () {
                var fresh = document.getElementById(action);
                var active = document.activeElement;

                $(state).text(summary);
                $(note).text(message);
                $(result).removeClass('d-none');
                // The redraw brought a new button with it; the old one is gone
                // from the document, so this re-enables whichever is there now.
                $('#' + action).prop('disabled', false);

                // Focus was on the pressed button, which the redraw removed, so
                // it fell to the body. It goes to the new button, unless the
                // operator has moved on to something else meanwhile.
                if (fresh && (!active || (active === document.body) || !document.contains(active) || $(active).closest('.card[widget-id="2"]').length)) {
                    fresh.focus({ preventScroll: true });
                }

                // The report panel arrives with its words, which a screen
                // reader does not read out by itself.
                if (typeof window.pgAnnounce === 'function') {
                    window.pgAnnounce(message);
                }
            });
        }

        $.ajax({
            contentType: 'application/json',
            url: 'api.php',
            type: 'POST',
            data: JSON.stringify({
                action: action,
                token: software_token
            }),
            success: function (response) {
                settle(response.summary || idle, response.message || failed);
            },
            error: function () {
                settle(idle, failed);
            }
        });
    }

    // The full CHECK TABLE sweep. Minutes on a large database, which is why the
    // row says so while it runs.
    $(document).on('click', '#database_deep_check', function (event) {

        var $button = $(this);

        if (event.isDefaultPrevented() || $button.prop('disabled')) {
            return;
        }

        pg_health_job($button, 'database_deep_check');
    });

    // Writing the missing rules into the web root's web.config / .htaccess.
    // The report names the file, how many rules went in and where the copy of
    // the old one was kept, which is more than a dialog should be asked to
    // hold.
    //
    // The question is asked by the delegated data-confirm-content handler
    // above, which runs first and calls preventDefault() when the operator says
    // no. On a link that is enough, because the default action is the
    // navigation; a <button> has no default action to stop, so the flag has to
    // be read rather than relied on.
    $(document).on('click', '#server_config_repair', function (event) {

        var $button = $(this);

        if (event.isDefaultPrevented() || $button.prop('disabled')) {
            return;
        }

        pg_health_job($button, 'server_config_repair');
    });

    // Pointing CURL_CA_BUNDLE in data/config.php at the bundled cacert.pem.
    // Same confirm-then-run shape as the rules file.
    $(document).on('click', '#ca_bundle_config_repair', function (event) {

        var $button = $(this);

        if (event.isDefaultPrevented() || $button.prop('disabled')) {
            return;
        }

        pg_health_job($button, 'ca_bundle_config_repair');
    });

    // Opening the folders and files of the software the web server cannot
    // write to (0777 / 0666), so the next update can replace everything. Same
    // confirm-then-run shape as the rules file: the delegated
    // data-confirm-content handler asks first and preventDefault()s a "no".
    $(document).on('click', '#write_permissions_repair', function (event) {

        var $button = $(this);

        if (event.isDefaultPrevented() || $button.prop('disabled')) {
            return;
        }

        pg_health_job($button, 'write_permissions_repair');
    });

    // Clearing the caches. This used to be a link to purge_cache.php, which
    // does the work and then lands the operator on settings.php -- so pressing
    // a button on a dashboard card took the card away, and the one number the
    // purge had just invalidated went unread. Same work, same function behind
    // it, answered in place.
    $(document).on('click', '#purge_cache', function (event) {

        var $button = $(this);

        if (event.isDefaultPrevented() || $button.prop('disabled')) {
            return;
        }

        pg_health_job($button, 'purge_cache');
    });

    // Replacing data/cacert.pem with the current Mozilla root list. The
    // report names the new date and count and, when the download is refused,
    // why -- an older file, a truncated one, a certificate OpenSSL could not
    // read -- which is more than a dialog should hold. Same confirm-then-run
    // shape as the other jobs: the delegated data-confirm-content handler
    // asks first and preventDefault()s a "no".
    $(document).on('click', '#ca_bundle_update', function (event) {

        var $button = $(this);

        if (event.isDefaultPrevented() || $button.prop('disabled')) {
            return;
        }

        pg_health_job($button, 'ca_bundle_update');
    });
});

// Settings screen: land on the section a #pgset-… link asks for.
//
// The cards carry ids, so in principle the browser could do this by itself.
// In practice it cannot: the screen is several thousand pixels of form, the
// code editors and the status card only take their final height after the
// page has loaded, and the browser measures the anchor before that - it
// jumps a few pixels and gives up. So the jump is repeated once the layout
// has settled. Also handles the case where the screen is already open and
// only the hash changes, which loads nothing at all.
// Side rail on the settings screen.
//
// Three jobs, and the first one is the reason the others work. The twelve
// sections are written in the order they were added to the page over the
// years; the rail groups them by subject, and a rail whose order disagrees
// with the page is worse than no rail at all -- the highlight jumps backwards
// as you scroll. So the sections are put into rail order here, in the DOM, so
// that reading order, tab order and scroll order are the same one order. The
// stylesheet carries the same sequence as flex `order`, which is what holds
// the page steady in the moment before this runs.
//
// Nothing is moved out of the form and no field is touched, so what the page
// submits is exactly what it submitted before.
// Section rail placement.
//
// One placement for every screen that has a rail, so there is one thing to fix
// when it is wrong. The shell drops an empty slot before <main>; this moves it
// inside, wraps whatever the page drew in a body box beside it, and lets the
// stylesheet put the two side by side from xl up. Below that the wrapper is an
// ordinary block and the page looks exactly as it did.
//
// The list itself has two sources and the same markup either way: a screen with
// an opinion (settings, products) renders the links into the slot in PHP, and a
// screen without one gets them from the cards it drew.
function initSectionRail() {

    var slot = document.getElementById('pg_section_rail');
    var main = document.getElementById('content');

    // The stylesheet is holding the rail's space open, from a class the shell
    // put on the moment it decided this screen has a rail. Without it the page
    // drew full width and then lost 232px of it in one frame, and every line of
    // the form slid sideways after it was already readable.
    //
    // Handing the space back is part of putting the rail in it, so it happens
    // in the same breath as the move -- and on every way out of here, including
    // the ways that decide there is no rail after all.
    var reserved = document.querySelector('.software-content.pg-awaiting-rail');

    function releaseReservedSpace() {
        if (reserved) {
            reserved.classList.remove('pg-awaiting-rail');
            reserved = null;
        }
    }

    if (!slot || !main) {
        releaseReservedSpace();
        return;
    }

    var nav = slot.querySelector('.pg-settings-nav');

    if (!nav) {
        releaseReservedSpace();
        return;
    }

    if (!nav.querySelector('.pg-settings-link')) {
        buildSectionRailFromCards(nav, main);
    }

    // Nothing worth a rail. Two sections are a page you can see all of.
    if (nav.querySelectorAll('.pg-settings-link[data-pgset]').length < 3) {
        slot.remove();
        releaseReservedSpace();
        return;
    }

    var body = document.createElement('div');
    body.className = 'pg-page-body';

    while (main.firstChild) {
        body.appendChild(main.firstChild);
    }

    main.appendChild(slot);
    main.appendChild(body);
    main.classList.add('pg-has-rail');
    slot.hidden = false;

    // Same frame as the line above: the reservation and the rail are never both
    // on the page at a moment the browser paints.
    releaseReservedSpace();

    initSettingsNav();
}

// The rail a screen did not describe, read from what it drew: the cards inside
// its form, in the order they appear. A card is a section when it has a header
// -- that is the line the operator would look for anyway -- and the icon beside
// that header comes along, because it is already the mark for that section.
function buildSectionRailFromCards(nav, main) {

    var form = main.querySelector('form');

    if (!form) {
        return;
    }

    var seen = 0;

    Array.prototype.slice.call(form.querySelectorAll('.card > .card-header')).forEach(function (header) {

        var card = header.parentElement;

        // Only the outermost cards. A card inside a card is a panel within a
        // section, not a section, and listing both reads as a duplicate.
        if (card.parentElement.closest('.card')) {
            return;
        }

        // A header that is only controls -- a toolbar, a switch -- has no name
        // to put in the rail.
        var label = header.textContent.replace(/\s+/g, ' ').trim();

        if (!label) {
            return;
        }

        if (!card.id) {
            seen++;
            card.id = 'pgset-auto-' + seen;
        }

        var icon = header.querySelector('i.bi, span.bi');
        var glyph = icon ? (icon.className.match(/bi-[a-z0-9-]+/) || [''])[0] : '';

        var link = document.createElement('a');
        link.className = 'pg-settings-link';
        link.href = '#' + card.id;
        link.setAttribute('data-pgset', card.id);
        link.innerHTML = '<i class="bi ' + (glyph || 'bi-dot') + '" aria-hidden="true"></i><span></span>';
        link.querySelector('span').textContent = label;

        nav.appendChild(link);
    });
}

// Any screen that renders the rail gets its behaviour, without having to
// remember to call for it. The settings screen still calls this itself and the
// data-pgready guard makes the second call a no-op.
$(function () {
    initSectionRail();
    initUserPermissionRows();

    if (document.querySelector('.pg-settings-nav')) {
        initSettingsNav();
    }
});

function initSettingsNav() {

    var nav = document.querySelector('.pg-settings-nav');

    if (!nav || nav.getAttribute('data-pgready')) {
        return;
    }

    // Which box holds the sections is the page's business, not this function's:
    // the settings screen keeps them in its own column, the product screen in
    // the column beside its preview. The nav names it.
    // A screen that arranges its own sections names the box they sit in; one
    // that lets the rail read its cards has no such box, and the page itself
    // stands in for it. Only reordering ever reaches into it, and that is
    // opt-in, so standing in is enough.
    var panesSelector = nav.getAttribute('data-pgpanes');

    // Guarded rather than defaulted into the call: querySelector('') is a
    // syntax error, not an empty result, and it took the whole rail down
    // silently on every screen that does not name its own box.
    var panes = (panesSelector ? document.querySelector(panesSelector) : null)
        || document.querySelector('.pg-page-body')
        || document.getElementById('content');

    if (!panes) {
        return;
    }

    nav.setAttribute('data-pgready', '1');

    // Only the top-level links stand for sections. The commerce steps are jump
    // points inside one of them, so they are not moved, not observed and never
    // become the marked row -- their section is.
    var links = Array.prototype.slice.call(nav.querySelectorAll('.pg-settings-link[data-pgset]'));
    var sections = [];

    // Reordering is opt-in. The settings screen asks for it because its
    // sections were written in the order they were added over the years; the
    // product screen's order is deliberate -- inventory before variants,
    // because the matrix seeds its rows from the quantity typed there -- and
    // moving them would undo that.
    var reorder = nav.getAttribute('data-pgreorder');

    links.forEach(function (link) {

        var section = document.getElementById(link.getAttribute('data-pgset'));

        // A link to a section this page did not draw is a dead control. Some
        // sections depend on a feature being switched on, so the page decides
        // and the rail follows.
        if (!section) {
            link.hidden = true;
            return;
        }

        if (reorder) {
            panes.appendChild(section);
        }

        sections.push(section);
    });

    // A step list that names a switch is only shown while that switch is on,
    // because its steps live inside the collapse the switch owns and there is
    // nothing to link to while it is off. Bound to the checkbox rather than
    // read once at load: turning the feature on is something an operator does
    // on this very page. Step lists without a switch are always listed.
    Array.prototype.slice.call(nav.querySelectorAll('.pg-settings-subs[data-pgswitch]')).forEach(function (steps) {

        var gate = document.getElementById(steps.getAttribute('data-pgswitch'));

        if (!gate) {
            steps.hidden = false;
            return;
        }

        var sync = function () {
            steps.hidden = !gate.checked;
        };

        gate.addEventListener('change', sync);
        sync();
    });

    // A section the map does not name still belongs on the page; it goes to
    // the end rather than disappearing, and the rail simply never points at it.
    if (reorder) {
        Array.prototype.slice.call(panes.children).forEach(function (child) {
            if (child.id && child.id.indexOf('pgset-') === 0 && sections.indexOf(child) === -1) {
                panes.appendChild(child);
            }
        });
    }

    // On a narrow screen the rail is a panel under the header rather than a
    // column beside the page, and this button is what opens it. It carries the
    // section you are in rather than a menu glyph, so the thing that opens the
    // list also answers "where am I" -- which on a twelve-section form is the
    // question being asked most of the time.
    var toggle = document.querySelector('.pg-settings-toggle');
    var toggleText = toggle ? toggle.querySelector('.pg-settings-toggle-text') : null;
    var toggleIcon = toggle ? toggle.querySelector('.pg-settings-toggle-icon') : null;

    function closeNav() {
        if (toggle) {
            nav.classList.remove('is-open');
            toggle.setAttribute('aria-expanded', 'false');
        }
    }

    function activate(id) {

        links.forEach(function (link) {

            var on = link.getAttribute('data-pgset') === id;

            link.classList.toggle('is-active', on);

            if (on && toggleText) {
                toggleText.textContent = link.textContent.trim();

                if (toggleIcon) {
                    toggleIcon.className = 'bi pg-settings-toggle-icon ' + (link.querySelector('i') || { className: '' }).className.replace('bi ', '');
                }
            }
        });
    }

    if (toggle) {

        toggle.addEventListener('click', function () {
            var open = nav.classList.toggle('is-open');
            toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        });

        document.addEventListener('click', function (event) {
            if (!nav.contains(event.target) && !toggle.contains(event.target)) {
                closeNav();
            }
        });

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                closeNav();
            }
        });
    }

    // Scroll spy: the last section whose top has passed the reading line, which
    // sits below the fixed header rather than at the top of the viewport --
    // without the offset the section above stays marked while its successor
    // fills the screen.
    //
    // Geometry each time rather than a map of what is intersecting. Sections
    // here run from a few hundred pixels to a few thousand, so several are on
    // screen at once and "the first one intersecting" is not the one being
    // read; and a map has a state to go stale, which is how a rail ends up
    // pointing at a section that scrolled away minutes ago.
    function spy() {

        var line = 100;

        // The tail of the page is the one place the line cannot decide. The
        // last sections never bring their tops up to it -- the scroll runs out
        // first -- so at the very bottom, and only there, the last section
        // with anything on screen wins instead.
        //
        // "Unreachable" alone was not enough and was worse than the problem:
        // on a tall window a short page makes every section unreachable, and
        // the rail then pointed at the last one from the top of the page.
        var atBottom = (window.innerHeight + window.scrollY) >= (document.documentElement.scrollHeight - 2);
        var current = sections[0];
        var currentTop = null;

        for (var i = 0; i < sections.length; i++) {

            var top = sections[i].getBoundingClientRect().top;

            if (top <= line || (atBottom && top < window.innerHeight)) {

                // Strictly lower, so that cards standing side by side keep the
                // first of the row rather than the last. Some screens lay their
                // sections out two across, and there the rail cannot mean "the
                // one you scrolled past" -- both were passed at once.
                if (currentTop === null || top > currentTop) {
                    current = sections[i];
                    currentTop = top;
                }
            }
        }

        if (current) {
            activate(current.id);
        }
    }

    if (sections.length && 'IntersectionObserver' in window) {

        // The observer is the clock, not the answer: it wakes the spy when a
        // section edge crosses the viewport, which is far cheaper than a scroll
        // handler on a page this long.
        var observer = new IntersectionObserver(spy, { threshold: 0 });

        sections.forEach(function (section) {
            observer.observe(section);
        });

        $(window).on('scroll.pgsettings', spy);
    }

    nav.addEventListener('click', function (event) {

        var link = event.target.closest ? event.target.closest('.pg-settings-link') : null;

        if (!link) {
            return;
        }

        var section = document.getElementById(link.getAttribute('data-pgset') || link.getAttribute('data-pgsub'));

        if (!section) {
            return;
        }

        event.preventDefault();

        // Instant for the same reason initSettingsAnchors is instant: this page
        // lays out as it scrolls -- code editors, charts -- and every shift on
        // the way down cancels a smooth scroll a few pixels in.
        section.scrollIntoView({ block: 'start', behavior: 'instant' });

        // A step marks the section it belongs to, not itself: the rail's mark
        // answers "which section", and the steps are places inside one.
        var owner = link.getAttribute('data-pgset') ? section : section.closest('[id^="pgset-"]');

        if (owner) {
            activate(owner.id);
        }

        closeNav();

        if (window.history && window.history.replaceState) {
            window.history.replaceState(null, '', '#' + section.id);
        }
    });

    if (/^#pgset-/.test(window.location.hash)) {
        activate(window.location.hash.slice(1));
    } else if (sections.length) {
        activate(sections[0].id);
    }

    $(window).on('hashchange', function () {
        if (/^#pgset-/.test(window.location.hash)) {
            activate(window.location.hash.slice(1));
        }
    });
}

function initSettingsAnchors() {
    function jump() {
        if (!/^#pgset-/.test(window.location.hash)) {
            return;
        }
        var target = document.getElementById(window.location.hash.slice(1));
        if (target) {
            // instant, not the page default of smooth: a smooth scroll is an
            // animation, and every layout shift on the way down cancels it.
            // On a screen this long that means it stops after a few pixels.
            target.scrollIntoView({ block: 'start', behavior: 'instant' });
        }
    }

    $(window).on('load', function () {
        window.setTimeout(jump, 60);
    });
    $(window).on('hashchange', jump);
    jump();
}

// Shared utility used by edit_page.php (static mode) and mass_edit.php (dynamic mode).
//
// Rules array items:
//   { sel, min, max }            → dynamic mode: counter div auto-created after each input (id = "seo_c_" + input.id)
//   { sel, counterId, min, max } → static mode:  uses a pre-existing div with the given counterId
//
function initSeoCounters(rules) {
    function renderSeoCounter(input, counterId, min, max) {
        var $input = $(input);
        var $counter = $("#" + counterId);
        if (!$counter.length) {
            $counter = $("<div class='d-flex align-items-center gap-3 mt-1'></div>")
                .attr("id", counterId)
                .insertAfter($input);
        }
        // A screen reader hears the counter as the field's description, so
        // the count, the target range and the state are said in words; the
        // visible "45/60 \u2191" is hidden from it.
        var describedBy = ($input.attr("aria-describedby") || "").split(/\s+/).filter(Boolean);
        if (describedBy.indexOf(counterId) === -1) {
            describedBy.push(counterId);
            $input.attr("aria-describedby", describedBy.join(" "));
        }
        var len = $input.val().length, cls, icon, said;
        var range = function (text) {
            return text.replace("{var:1}", len).replace("{var:2}", min).replace("{var:3}", max);
        };
        if (len === 0) {
            cls = "secondary"; icon = "";
            said = pgLang('No text yet. Aim for {var:1} to {var:2} characters.').replace("{var:1}", min).replace("{var:2}", max);
        }
        else if (len < min) { cls = "warning"; icon = " \u2191"; said = range(pgLang('{var:1} characters, too short. Aim for {var:2} to {var:3}.')); }
        else if (len <= max) { cls = "success"; icon = " \u2713"; said = range(pgLang('{var:1} characters, good length. Aim for {var:2} to {var:3}.')); }
        else if (len <= max + 10) { cls = "warning"; icon = " \u2193"; said = range(pgLang('{var:1} characters, too long. Aim for {var:2} to {var:3}.')); }
        else { cls = "danger"; icon = " \u2717"; said = range(pgLang('{var:1} characters, too long. Aim for {var:2} to {var:3}.')); }
        var pct = Math.min(100, Math.round(len / (max * 1.25) * 100));
        $counter.html(
            "<small class='fg-" + cls + " fw-semibold' style='min-width:60px' aria-hidden='true'>" +
            len + "/" + max + icon +
            "</small>" +
            "<span class='visually-hidden'>" + h(said) + "</span>" +
            "<div class='progress flex-grow-1' style='height:3px' aria-hidden='true'>" +
            "<div class='progress-bar bg-" + cls + "' style='width:" + pct + "%;transition:width .15s'></div>" +
            "</div>"
        );
    }

    rules.forEach(function (r) {
        if (r.counterId) {
            // Static mode (edit_page.php): single input, pre-existing counter div
            var $el = $(r.sel);
            if (!$el.length) return;
            renderSeoCounter($el[0], r.counterId, r.min, r.max);
            $el.on("input", function () { renderSeoCounter(this, r.counterId, r.min, r.max); });
        } else {
            // Dynamic mode (mass_edit.php): multiple inputs, counter div created on-the-fly
            $(document).on("input", r.sel, function () {
                renderSeoCounter(this, "seo_c_" + $(this).attr("id"), r.min, r.max);
            });
            $(r.sel).each(function () {
                renderSeoCounter(this, "seo_c_" + $(this).attr("id"), r.min, r.max);
            });
        }
    });
}

// Search engine indexing switches, shared by the page screens (add_page.php,
// edit_page.php) and the Visual Editor's style settings panel
// (add_system_style.php, edit_system_style.php).
//
// Both dependent controls are driven from the master switch rather than being
// left in a state the save would silently discard: a page closed to search
// engines is dropped from the site map by the save, and nofollow only qualifies
// a noindex directive, so neither can stand on its own on screen either.
//
// The two screens use different element ids, so they are passed in. The page
// screens post a bare checkbox, where a disabled control sends nothing at all;
// the style panel pairs each checkbox with a hidden zero, which submits instead.
// Either way the disabled state and the save agree without trusting each other.
function bindPageIndexingSwitches(ids) {
    ids = ids || {};

    var noindex = document.getElementById(ids.noindex || 'noindex');

    if (!noindex) {
        return;
    }

    var nofollow = document.getElementById(ids.nofollow || 'nofollow');
    var sitemap = document.getElementById(ids.sitemap || 'sitemap');
    var hint = ids.hint ? document.getElementById(ids.hint) : null;

    function applyPageIndexingState() {
        if (nofollow) {
            nofollow.disabled = !noindex.checked;

            if (!noindex.checked) {
                nofollow.checked = false;
            }
        }

        if (sitemap) {
            sitemap.disabled = noindex.checked;

            if (noindex.checked) {
                sitemap.checked = false;
            }
        }

        if (hint) {
            hint.style.display = noindex.checked ? '' : 'none';
        }
    }

    noindex.addEventListener('change', applyPageIndexingState);

    applyPageIndexingState();
}

// The box that keeps a renamed page's old address (edit_page.php): shown
// while the name differs from the stored one, read the way the save writes
// it (spaces and & become _). Hidden, it still posts its state, so the save
// compares the names itself.
function bindPageRenameRedirect(ids) {
    ids = ids || {};

    var nameField = document.getElementById(ids.name || 'name');
    var row = document.getElementById(ids.row || 'name_redirect_row');

    if (!nameField || !row) {
        return;
    }

    var current = row.getAttribute('data-current-name') || '';

    function applyPageRenameState() {
        var name = nameField.value.trim().replace(/ /g, '_').replace(/&/g, '_');

        row.classList.toggle('d-none', (name === '') || (name === current));
    }

    nameField.addEventListener('input', applyPageRenameState);

    applyPageRenameState();
}

/* ─────────────────────────────────────────────────────────────────────────
   Barcode management and label editor for PineGrap CMS.
   Requires: JsBarcode (loaded from CDN on pages that use barcode features).
   Exports: window.PgBarcode = { LabelEditor, initProductBarcode, initLabelDesigner, api }
───────────────────────────────────────────────────────────────────────── */
(function (window) {
    'use strict';

    /* ═══════════════════════════════════════════════════════════════════
       HELPER UTILITIES
    ═══════════════════════════════════════════════════════════════════ */
    // pg_api_url() and the software_token global are defined by the backend
    // header script; the function declaration is hoisted, so it is available here.
    const PG_BARCODE_API = pg_api_url();

    const PG_TOKEN = (typeof software_token !== 'undefined') ? software_token : '';

    function api(action, data) {
        return fetch(PG_BARCODE_API, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(Object.assign({ action, token: PG_TOKEN }, data))
        }).then(r => r.json());
    }

    /* ═══════════════════════════════════════════════════════════════════
       DEFAULT LABEL TEMPLATE
    ═══════════════════════════════════════════════════════════════════ */
    const DEFAULT_TEMPLATE = {
        label: { width: 60, height: 40, unit: 'mm', background: '#ffffff' },
        elements: [
            {
                id: 'barcode1', type: 'barcode',
                x: 5, y: 3, width: 50, height: 16,
                barcodeType: 'CODE128', showText: true
            },
            {
                id: 'text_name', type: 'text',
                field: 'short_description', label: 'Product Name',
                x: 5, y: 22, width: 50, height: 7,
                fontSize: 9, fontWeight: 'bold', align: 'center', color: '#000000',
                scrollX: 0
            },
            {
                id: 'text_sku', type: 'text',
                field: 'sku', label: 'SKU',
                x: 5, y: 31, width: 25, height: 5,
                fontSize: 7, fontWeight: 'normal', align: 'left', color: '#555555',
                scrollX: 0
            }
        ]
    };

    /* ═══════════════════════════════════════════════════════════════════
       LABEL EDITOR CLASS
    ═══════════════════════════════════════════════════════════════════ */
    function LabelEditor(containerEl, options) {
        this.container = containerEl;
        this.options = options || {};
        this.template = JSON.parse(JSON.stringify(DEFAULT_TEMPLATE));
        this.selected = null;      // selected element id
        this.dragging = null;      // { id, startX, startY, origX, origY }
        this.resizing = null;      // { id, handle, startX, startY, origW, origH, origX, origY }
        this.PX_PER_MM = 3.2;      // editor scale
        this.onchange = options.onchange || null;

        this._build();
    }

    LabelEditor.prototype = {

        /* ── Build DOM ─────────────────────────────────────────────── */
        _build() {
            this.container.innerHTML = '';
            this.container.style.display = 'flex';
            this.container.style.gap = '12px';
            this.container.style.alignItems = 'flex-start';
            this.container.style.flexWrap = 'wrap';

            // Left: canvas area
            const canvasWrap = document.createElement('div');
            canvasWrap.style.cssText = 'flex:0 0 auto;';
            this.canvasWrap = canvasWrap;

            this.canvas = document.createElement('div');
            this.canvas.id = 'pg-label-canvas';
            this.canvas.style.cssText = `position:relative;overflow:hidden;border:1px solid #aaa;cursor:default;user-select:none;box-shadow:2px 2px 6px rgba(0,0,0,.15);`;
            canvasWrap.appendChild(this.canvas);

            // Dimensions label
            this.dimLabel = document.createElement('div');
            this.dimLabel.style.cssText = 'text-align:center;font-size:11px;color:#888;margin-top:4px;';
            canvasWrap.appendChild(this.dimLabel);

            // Right: properties panel
            this.panel = document.createElement('div');
            this.panel.style.cssText = 'flex:1 1 200px;min-width:200px;max-width:320px;';

            this.container.appendChild(canvasWrap);
            this.container.appendChild(this.panel);

            this._applyCanvasSize();
            this._bindCanvasEvents();
            this.render();
            this._renderPanel();
        },

        _applyCanvasSize() {
            const lbl = this.template.label;
            const w = Math.round(lbl.width * this.PX_PER_MM);
            const h = Math.round(lbl.height * this.PX_PER_MM);
            this.canvas.style.width = w + 'px';
            this.canvas.style.height = h + 'px';
            this.canvas.style.background = lbl.background || '#ffffff';
            this.dimLabel.textContent = lbl.width + ' \u00d7 ' + lbl.height + ' mm';
        },

        /* ── Rendering ─────────────────────────────────────────────── */
        render() {
            // Remove element divs (keep ruler/grid if any)
            Array.from(this.canvas.querySelectorAll('.pg-el')).forEach(el => el.remove());
            this.template.elements.forEach(el => this._renderElement(el));
        },

        _renderElement(el) {
            const pxX = Math.round(el.x * this.PX_PER_MM);
            const pxY = Math.round(el.y * this.PX_PER_MM);
            const pxW = Math.round(el.width * this.PX_PER_MM);
            const pxH = Math.round(el.height * this.PX_PER_MM);

            const div = document.createElement('div');
            div.className = 'pg-el';
            div.dataset.id = el.id;
            const zIdx = this.template.elements.indexOf(el);
            const rot = el.rotation ? `rotate(${el.rotation}deg)` : '';
            div.style.cssText = `position:absolute;left:${pxX}px;top:${pxY}px;width:${pxW}px;height:${pxH}px;box-sizing:border-box;overflow:hidden;cursor:move;z-index:${zIdx};${rot ? 'transform:' + rot + ';transform-origin:center center;' : ''}`;

            if (this.selected === el.id) {
                div.style.outline = '2px solid #0d6efd';
            }

            if (el.type === 'barcode') {
                this._renderBarcodeEl(div, el);
            } else if (el.type === 'text') {
                this._renderTextEl(div, el);
            } else if (el.type === 'image') {
                this._renderImageEl(div, el);
            } else if (el.type === 'rect') {
                this._renderRectEl(div, el);
            }

            // Resize handle (bottom-right corner) — hidden when element is rotated
            if (el.type !== 'rect' && !el.rotation) {
                const handle = document.createElement('div');
                handle.className = 'pg-resize-handle';
                handle.style.cssText = 'position:absolute;right:0;bottom:0;width:8px;height:8px;background:#0d6efd;cursor:se-resize;opacity:0;transition:opacity .2s;';
                div.appendChild(handle);
                div.addEventListener('mouseenter', () => handle.style.opacity = '1');
                div.addEventListener('mouseleave', () => handle.style.opacity = '0');
            }

            this.canvas.appendChild(div);
            this._bindElementEvents(div, el);
        },

        _renderBarcodeEl(div, el) {
            const svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
            svg.style.cssText = 'width:100%;height:100%;display:block;';
            div.appendChild(svg);

            const barcodeVal = this.options.barcodeValue || '0000000000000';
            try {
                JsBarcode(svg, barcodeVal, {
                    format: el.barcodeType || 'CODE128',
                    displayValue: el.showText !== false,
                    fontSize: 9,
                    margin: 2,
                    width: 1.2,
                    height: Math.max(20, Math.round(el.height * this.PX_PER_MM) - (el.showText ? 14 : 4)),
                    lineColor: '#000000',
                });
            } catch (e) {
                div.innerHTML = `<div style="font-size:10px;color:#888;padding:2px;text-align:center;">${pgLang('Barcode preview')}</div>`;
            }
        },

        _renderTextEl(div, el) {
            const fieldValues = this.options.fieldValues || {};
            let text = '';
            if (el.field === 'custom') {
                text = el.text || '';
            } else if (el.field === 'short_description') {
                text = fieldValues.short_description || el.label || 'Product Name';
            } else if (el.field === 'sku') {
                text = fieldValues.sku || el.label || 'SKU';
            } else if (el.field === 'price') {
                text = fieldValues.price || '0.00';
            } else if (el.field === 'attributes') {
                text = fieldValues.attributes || el.label || 'Attributes';
            } else {
                text = el.text || el.label || '';
            }

            div.style.display = 'flex';
            div.style.alignItems = 'center';
            div.style.justifyContent = el.align === 'center' ? 'center' : (el.align === 'right' ? 'flex-end' : 'flex-start');
            div.style.padding = '0 2px';

            const span = document.createElement('span');
            span.style.cssText = `
                font-size:${el.fontSize || 8}px;
                font-weight:${el.fontWeight || 'normal'};
                color:${el.color || '#000'};
                white-space:nowrap;
                transform:translateX(${el.scrollX || 0}px);
                display:inline-block;
                max-width:none;
            `;
            span.textContent = text;
            div.appendChild(span);
        },

        _renderImageEl(div, el) {
            // 'product_image' field: use the current product's image from editor options
            const src = (el.field === 'product_image')
                ? (this.options.productImageSrc || el.src || '')
                : (el.src || '');
            if (src) {
                const img = document.createElement('img');
                img.src = src;
                img.style.cssText = 'width:100%;height:100%;object-fit:contain;';
                div.appendChild(img);
            } else {
                div.style.cssText += 'border:1px dashed #aaa;display:flex;align-items:center;justify-content:center;';
                div.innerHTML = `<span style="font-size:9px;color:#aaa;">${el.field === 'product_image' ? pgLang('Product Image') : pgLang('Image')}</span>`;
            }
        },

        _renderRectEl(div, el) {
            div.style.cursor = 'move';
            div.style.border = `${el.borderWidth || 1}px solid ${el.borderColor || '#000'}`;
            div.style.background = el.fill && el.fill !== 'none' ? el.fill : 'transparent';
            div.style.borderRadius = (el.borderRadius || 0) + 'px';
        },

        /* ── Element events ────────────────────────────────────────── */
        _bindElementEvents(div, el) {
            div.addEventListener('mousedown', (e) => {
                // Check if clicking resize handle
                if (e.target.classList.contains('pg-resize-handle')) {
                    e.stopPropagation();
                    this.resizing = {
                        id: el.id,
                        startX: e.clientX, startY: e.clientY,
                        origW: el.width, origH: el.height,
                        origX: el.x, origY: el.y
                    };
                    this.selected = el.id;
                    this.render();
                    this._renderPanel();
                    return;
                }
                e.stopPropagation();
                this.selected = el.id;
                this.dragging = {
                    id: el.id,
                    startX: e.clientX, startY: e.clientY,
                    origX: el.x, origY: el.y
                };
                this.render();
                this._renderPanel();
            });
        },

        _bindCanvasEvents() {
            // Deselect on canvas click
            this.canvas.addEventListener('mousedown', (e) => {
                if (e.target === this.canvas) {
                    this.selected = null;
                    this.render();
                    this._renderPanel();
                }
            });

            document.addEventListener('mousemove', (e) => {
                if (this.dragging) {
                    const dx = (e.clientX - this.dragging.startX) / this.PX_PER_MM;
                    const dy = (e.clientY - this.dragging.startY) / this.PX_PER_MM;
                    const elData = this._findEl(this.dragging.id);
                    if (elData) {
                        const rot = elData.rotation || 0;
                        const W = elData.width, H = elData.height;
                        const lw = this.template.label.width, lh = this.template.label.height;
                        let minX, maxX, minY, maxY;
                        if (rot === 90 || rot === 270) {
                            minX = (H - W) / 2; maxX = lw - (W + H) / 2;
                            minY = (W - H) / 2; maxY = lh - (H + W) / 2;
                        } else {
                            minX = 0; maxX = lw - W;
                            minY = 0; maxY = lh - H;
                        }
                        elData.x = Math.round(Math.max(minX, Math.min(maxX, this.dragging.origX + dx)) * 10) / 10;
                        elData.y = Math.round(Math.max(minY, Math.min(maxY, this.dragging.origY + dy)) * 10) / 10;
                        this.render();
                        this._updatePanelPosition(elData);
                    }
                }
                if (this.resizing) {
                    const dx = (e.clientX - this.resizing.startX) / this.PX_PER_MM;
                    const dy = (e.clientY - this.resizing.startY) / this.PX_PER_MM;
                    const elData = this._findEl(this.resizing.id);
                    if (elData) {
                        elData.width = Math.max(5, this.resizing.origW + dx);
                        elData.height = Math.max(3, this.resizing.origH + dy);
                        elData.width = Math.round(elData.width * 10) / 10;
                        elData.height = Math.round(elData.height * 10) / 10;
                        this.render();
                        this._updatePanelPosition(elData);
                    }
                }
            });

            document.addEventListener('mouseup', () => {
                if (this.dragging || this.resizing) {
                    this.dragging = null;
                    this.resizing = null;
                    this._fireChange();
                }
            });

            // Delete / Backspace key removes selected element
            document.addEventListener('keydown', (e) => {
                if (!this.selected) return;
                if (e.key !== 'Delete' && e.key !== 'Backspace') return;
                const active = document.activeElement;
                if (active && (active.tagName === 'INPUT' || active.tagName === 'TEXTAREA' || active.tagName === 'SELECT')) return;
                e.preventDefault();
                this._deleteEl(this.selected);
            });

            // Drop zone — field source chips drag onto canvas
            this.canvas.addEventListener('dragover', (e) => {
                if (e.dataTransfer.types.indexOf('pg-field') !== -1) {
                    e.preventDefault();
                    this.canvas.style.outline = '2px dashed #0d6efd';
                }
            });
            this.canvas.addEventListener('dragleave', () => {
                this.canvas.style.outline = '';
            });
            this.canvas.addEventListener('drop', (e) => {
                e.preventDefault();
                this.canvas.style.outline = '';
                const fieldType = e.dataTransfer.getData('pg-field');
                if (!fieldType) return;
                const rect = this.canvas.getBoundingClientRect();
                const x = Math.round(Math.max(0, (e.clientX - rect.left) / this.PX_PER_MM) * 10) / 10;
                const y = Math.round(Math.max(0, (e.clientY - rect.top) / this.PX_PER_MM) * 10) / 10;
                this._addFieldElement(fieldType, x, y);
            });
        },

        /* ── Properties panel ──────────────────────────────────────── */
        _renderPanel() {
            this.panel.innerHTML = '';

            const el = this.selected ? this._findEl(this.selected) : null;

            // Toolbar: Add element buttons
            const toolbar = document.createElement('div');
            toolbar.style.cssText = 'display:flex;flex-wrap:wrap;gap:4px;margin-bottom:8px;';

            const addBtn = (label, icon, action) => {
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'btn btn-outline theme-secondary btn-sm';
                btn.innerHTML = `<i class="bi ${icon}"></i> ${label}`;
                btn.addEventListener('click', action);
                toolbar.appendChild(btn);
            };

            addBtn(pgLang('Add Text'), 'bi-fonts', () => this._addText());
            addBtn(pgLang('Add Rect'), 'bi-square', () => this._addRect());
            addBtn(pgLang('Add Image'), 'bi-image', () => this._addImage());

            if (el) {
                // Rotate 90° — only for text, image, rect
                if (el.type !== 'barcode') {
                    const rotBtn = document.createElement('button');
                    rotBtn.type = 'button';
                    rotBtn.className = 'btn btn-outline theme-secondary btn-sm';
                    rotBtn.innerHTML = '<i class="bi bi-arrow-clockwise"></i>';
                    rotBtn.title = pgLang('Rotate 90\u00b0');
                    rotBtn.addEventListener('click', () => {
                        el.rotation = ((el.rotation || 0) + 90) % 360;
                        this.render(); this._renderPanel(); this._fireChange();
                    });
                    toolbar.appendChild(rotBtn);
                }

                // Bring to front
                const frontBtn = document.createElement('button');
                frontBtn.type = 'button';
                frontBtn.className = 'btn btn-outline theme-secondary btn-sm';
                frontBtn.innerHTML = '<i class="bi bi-arrow-bar-up"></i>';
                frontBtn.title = pgLang('Bring to Front');
                frontBtn.addEventListener('click', () => this._bringToFront(el.id));
                toolbar.appendChild(frontBtn);

                // Send to back
                const backBtn = document.createElement('button');
                backBtn.type = 'button';
                backBtn.className = 'btn btn-outline theme-secondary btn-sm';
                backBtn.innerHTML = '<i class="bi bi-arrow-bar-down"></i>';
                backBtn.title = pgLang('Send to Back');
                backBtn.addEventListener('click', () => this._sendToBack(el.id));
                toolbar.appendChild(backBtn);

                // Delete
                const delBtn = document.createElement('button');
                delBtn.type = 'button';
                delBtn.className = 'btn btn-outline theme-danger btn-sm ms-auto';
                delBtn.innerHTML = '<i class="bi bi-trash"></i>';
                delBtn.title = pgLang('Delete');
                delBtn.addEventListener('click', () => this._deleteEl(el.id));
                toolbar.appendChild(delBtn);
            }

            this.panel.appendChild(toolbar);

            if (!el) {
                // Label size settings
                this.panel.appendChild(this._buildLabelSizePanel());
                return;
            }

            // Element-specific properties
            const props = document.createElement('div');
            props.style.cssText = 'background:var(--pg-bg-1);border:1px solid var(--pg-border-color);border-radius:6px;padding:10px;font-size:13px;';

            const title = document.createElement('div');
            title.style.cssText = 'font-weight:600;margin-bottom:8px;text-transform:capitalize;';
            title.textContent = el.type + (el.field ? ' \u2014 ' + el.field : '');
            props.appendChild(title);

            // Position + size
            props.appendChild(this._fieldRow(pgLang('X (mm)'), 'number', el.x, v => { el.x = +v; this.render(); this._fireChange(); }, 0.1));
            props.appendChild(this._fieldRow(pgLang('Y (mm)'), 'number', el.y, v => { el.y = +v; this.render(); this._fireChange(); }, 0.1));
            props.appendChild(this._fieldRow(pgLang('W (mm)'), 'number', el.width, v => { el.width = +v; this.render(); this._fireChange(); }, 0.1));
            props.appendChild(this._fieldRow(pgLang('H (mm)'), 'number', el.height, v => { el.height = +v; this.render(); this._fireChange(); }, 0.1));

            if (el.type === 'text') {
                props.appendChild(this._fieldRow(pgLang('Font size'), 'number', el.fontSize, v => { el.fontSize = +v; this.render(); this._fireChange(); }, 1));
                props.appendChild(this._fieldRow(pgLang('Color'), 'color', el.color, v => { el.color = v; this.render(); this._fireChange(); }));
                props.appendChild(this._selectRow(pgLang('Align'), ['left', 'center', 'right'], el.align, v => { el.align = v; this.render(); this._fireChange(); }));
                props.appendChild(this._selectRow(pgLang('Weight'), ['normal', 'bold'], el.fontWeight, v => { el.fontWeight = v; this.render(); this._fireChange(); }));

                // Horizontal scroll slider for text
                const scrollWrap = document.createElement('div');
                scrollWrap.style.cssText = 'margin-top:6px;';
                scrollWrap.innerHTML = `<label style="font-size:12px">${pgLang('Scroll X')}</label>`;
                const range = document.createElement('div');
                range.className = 'form-range pg-range-bubble';
                const slider = document.createElement('input');
                slider.type = 'range'; slider.className = 'form-range-input';
                slider.min = -200; slider.max = 200; slider.value = el.scrollX || 0;
                slider.setAttribute('aria-label', pgLang('Scroll X'));
                slider.addEventListener('input', () => { el.scrollX = +slider.value; this.render(); this._fireChange(); });
                range.appendChild(slider);
                scrollWrap.appendChild(range);
                props.appendChild(scrollWrap);
                pgUI.range.bubble(range);

                if (el.field === 'custom') {
                    props.appendChild(this._fieldRow(pgLang('Text'), 'text', el.text || '', v => { el.text = v; this.render(); this._fireChange(); }));
                }
            }

            if (el.type === 'barcode') {
                props.appendChild(this._selectRow(pgLang('Format'), ['CODE128', 'EAN13', 'CODE39', 'UPC'], el.barcodeType, v => { el.barcodeType = v; this.render(); this._fireChange(); }));
                props.appendChild(this._checkRow(pgLang('Show text'), el.showText !== false, v => { el.showText = v; this.render(); this._fireChange(); }));
            }

            if (el.type === 'rect') {
                props.appendChild(this._fieldRow(pgLang('Border color'), 'color', el.borderColor || '#000000', v => { el.borderColor = v; this.render(); this._fireChange(); }));
                props.appendChild(this._fieldRow(pgLang('Fill color'), 'color', el.fill && el.fill !== 'none' ? el.fill : '#ffffff', v => { el.fill = v; this.render(); this._fireChange(); }));
                props.appendChild(this._fieldRow(pgLang('Border width'), 'number', el.borderWidth || 1, v => { el.borderWidth = +v; this.render(); this._fireChange(); }, 1));
                props.appendChild(this._fieldRow(pgLang('Radius'), 'number', el.borderRadius || 0, v => { el.borderRadius = +v; this.render(); this._fireChange(); }, 1));
            }

            if (el.type === 'image') {
                // Product image field: src is resolved dynamically per product — no URL editing
                if (el.field === 'product_image') {
                    const note = document.createElement('div');
                    note.style.cssText = 'margin-top:6px;font-size:12px;color:var(--pg-fg-2);padding:4px 0;';
                    note.innerHTML = `<i class="bi bi-image me-1"></i>${pgLang('Product Image')} <span class="fg-secondary fst-italic">(${pgLang('Dynamic (changes per product)')})</span>`;
                    props.appendChild(note);
                    this.panel.appendChild(props);
                    return;
                }

                const imgRow = document.createElement('div');
                imgRow.style.cssText = 'margin-top:6px;';
                imgRow.innerHTML = `<label style="font-size:12px;display:block;">${pgLang('Image URL / path')}</label>`;
                const inp = document.createElement('input');
                inp.type = 'text'; inp.className = 'form-control form-control-sm';
                inp.value = el.src || '';
                inp.placeholder = pgLang('e.g. uploads/logo.png');
                inp.addEventListener('change', () => { el.src = inp.value; this.render(); this._fireChange(); });
                imgRow.appendChild(inp);

                // File picker button — uses Pinegrap system picker if available
                const pick = document.createElement('button');
                pick.type = 'button'; pick.className = 'btn btn-outline theme-secondary btn-sm mt-1';
                pick.innerHTML = `<i class="bi bi-folder2-open"></i> ${pgLang('Browse')}`;
                pick.addEventListener('click', () => {
                    const self = this;
                    if (typeof window.software_image_picker !== 'undefined') {
                        // Temporarily intercept the picker callback to capture the chosen image
                        const _orig = window.software_image_picker;
                        window.software_image_picker = function (props) {
                            if (props.return) {
                                window.software_image_picker = _orig;
                                const imgPath = decodeURIComponent(props.image_name);
                                el.src = (typeof OUTPUT_PATH !== 'undefined' ? OUTPUT_PATH : '/') + imgPath;
                                inp.value = imgPath;
                                self.render();
                                self._fireChange();
                                return;
                            }
                            _orig.call(window, props);
                        };
                        window.software_image_picker({ initialize: true, SingleImage: true });
                    } else {
                        // Fallback: local file reader (data URL, not saved to server)
                        const fileInput = document.createElement('input');
                        fileInput.type = 'file'; fileInput.accept = 'image/*';
                        fileInput.addEventListener('change', () => {
                            const file = fileInput.files[0];
                            if (!file) return;
                            const reader = new FileReader();
                            reader.onload = e2 => { el.src = e2.target.result; inp.value = '(embedded)'; self.render(); self._fireChange(); };
                            reader.readAsDataURL(file);
                        });
                        fileInput.click();
                    }
                });
                imgRow.appendChild(pick);
                props.appendChild(imgRow);
            }

            this.panel.appendChild(props);
        },

        _buildLabelSizePanel() {
            const lbl = this.template.label;
            const div = document.createElement('div');
            div.style.cssText = 'background:var(--pg-bg-1);border:1px solid var(--pg-border-color);border-radius:6px;padding:10px;font-size:13px;';
            div.innerHTML = `<div style="font-weight:600;margin-bottom:8px;">${pgLang('Label Size')}</div>`;
            div.appendChild(this._fieldRow(pgLang('Width (mm)'), 'number', lbl.width, v => { lbl.width = +v; this._applyCanvasSize(); this.render(); this._fireChange(); }, 1));
            div.appendChild(this._fieldRow(pgLang('Height (mm)'), 'number', lbl.height, v => { lbl.height = +v; this._applyCanvasSize(); this.render(); this._fireChange(); }, 1));
            div.appendChild(this._fieldRow(pgLang('Background'), 'color', lbl.background || '#ffffff', v => { lbl.background = v; this._applyCanvasSize(); this._fireChange(); }));
            return div;
        },

        _updatePanelPosition(el) {
            // Live-update x/y inputs in panel without full re-render
            const inputs = this.panel.querySelectorAll('input[type=number]');
            if (inputs.length >= 4) {
                inputs[0].value = el.x;
                inputs[1].value = el.y;
                inputs[2].value = el.width;
                inputs[3].value = el.height;
            }
        },

        /* ── Panel helpers ─────────────────────────────────────────── */
        _fieldRow(label, type, value, onChange, step) {
            const row = document.createElement('div');
            row.style.cssText = 'display:flex;align-items:center;justify-content:space-between;margin-bottom:5px;gap:6px;';
            const lbl = document.createElement('label');
            lbl.style.cssText = 'flex:0 0 90px;font-size:12px;';
            lbl.textContent = label;
            const inp = document.createElement('input');
            inp.type = type; inp.value = value;
            inp.style.cssText = 'flex:1;min-width:0;height:24px;border:1px solid var(--pg-border-color);border-radius:4px;padding:0 4px;font-size:12px;background:var(--pg-bg-body);color:var(--pg-fg-body);';
            if (step) inp.step = step;
            inp.addEventListener('input', () => onChange(inp.value));
            if (type === 'color') inp.addEventListener('change', () => onChange(inp.value));
            row.appendChild(lbl); row.appendChild(inp);
            return row;
        },

        _selectRow(label, options, current, onChange) {
            const row = document.createElement('div');
            row.style.cssText = 'display:flex;align-items:center;justify-content:space-between;margin-bottom:5px;gap:6px;';
            const lbl = document.createElement('label');
            lbl.style.cssText = 'flex:0 0 90px;font-size:12px;';
            lbl.textContent = label;
            const sel = document.createElement('select');
            sel.style.cssText = 'flex:1;min-width:0;height:24px;border:1px solid var(--pg-border-color);border-radius:4px;padding:0 4px;font-size:12px;background:var(--pg-bg-body);color:var(--pg-fg-body);';
            options.forEach(o => {
                const opt = document.createElement('option');
                opt.value = o; opt.textContent = o;
                if (o === current) opt.selected = true;
                sel.appendChild(opt);
            });
            sel.addEventListener('change', () => onChange(sel.value));
            row.appendChild(lbl); row.appendChild(sel);
            return row;
        },

        _checkRow(label, checked, onChange) {
            const row = document.createElement('div');
            row.style.cssText = 'display:flex;align-items:center;gap:6px;margin-bottom:5px;';
            const inp = document.createElement('input');
            inp.type = 'checkbox'; inp.checked = checked;
            inp.style.cursor = 'pointer';
            const lbl = document.createElement('label');
            lbl.style.cssText = 'font-size:12px;cursor:pointer;';
            lbl.textContent = label;
            inp.addEventListener('change', () => onChange(inp.checked));
            lbl.addEventListener('click', () => inp.click());
            row.appendChild(inp); row.appendChild(lbl);
            return row;
        },

        /* ── Add elements ──────────────────────────────────────────── */
        _addText() {
            const id = 'text_' + Date.now();
            this.template.elements.push({
                id, type: 'text', field: 'custom', text: 'Text',
                x: 5, y: 5, width: 30, height: 6,
                fontSize: 8, fontWeight: 'normal', align: 'left', color: '#000000', scrollX: 0
            });
            this.selected = id;
            this.render();
            this._renderPanel();
            this._fireChange();
        },

        _addRect() {
            const id = 'rect_' + Date.now();
            this.template.elements.push({
                id, type: 'rect',
                x: 2, y: 2, width: this.template.label.width - 4, height: this.template.label.height - 4,
                borderWidth: 1, borderColor: '#000000', fill: 'none', borderRadius: 0
            });
            this.selected = id;
            this.render();
            this._renderPanel();
            this._fireChange();
        },

        _addImage() {
            const id = 'img_' + Date.now();
            this.template.elements.push({
                id, type: 'image', src: '',
                x: 2, y: 2, width: 12, height: 10
            });
            this.selected = id;
            this.render();
            this._renderPanel();
            this._fireChange();
        },

        _deleteEl(id) {
            // Prevent deleting the primary barcode element
            const el = this._findEl(id);
            if (el && el.type === 'barcode' && id === 'barcode1') {
                alert(pgLang('The primary barcode element cannot be deleted.'));
                return;
            }
            this.template.elements = this.template.elements.filter(e => e.id !== id);
            this.selected = null;
            this.render();
            this._renderPanel();
            this._fireChange();
        },

        _bringToFront(id) {
            const idx = this.template.elements.findIndex(e => e.id === id);
            if (idx === -1 || idx === this.template.elements.length - 1) return;
            const [el] = this.template.elements.splice(idx, 1);
            this.template.elements.push(el);
            this.render();
            this._fireChange();
        },

        _sendToBack(id) {
            const idx = this.template.elements.findIndex(e => e.id === id);
            if (idx <= 0) return;
            const [el] = this.template.elements.splice(idx, 1);
            this.template.elements.unshift(el);
            this.render();
            this._fireChange();
        },

        /** Add an element by field type at position x,y (in mm) */
        _addFieldElement(fieldType, x, y) {
            const id = fieldType.replace(/[^a-z0-9]/gi, '_') + '_' + Date.now();
            let el;
            if (fieldType === 'product_image') {
                el = {
                    id, type: 'image', field: 'product_image',
                    src: this.options.productImageSrc || '',
                    x, y, width: 15, height: 12
                };
            } else {
                const fieldLabels = {
                    sku: 'SKU',
                    short_description: 'Product Name',
                    attributes: 'Attributes',
                    price: 'Price'
                };
                el = {
                    id, type: 'text', field: fieldType,
                    label: fieldLabels[fieldType] || fieldType,
                    x, y, width: 30, height: 6,
                    fontSize: 8, fontWeight: 'normal', align: 'left', color: '#000000', scrollX: 0
                };
            }
            this.template.elements.push(el);
            this.selected = el.id;
            this.render();
            this._renderPanel();
            this._fireChange();
        },

        /* ── Helpers ───────────────────────────────────────────────── */
        _findEl(id) {
            return this.template.elements.find(e => e.id === id) || null;
        },

        _fireChange() {
            if (this.onchange) this.onchange(this.getTemplate());
        },

        /* ── Public API ────────────────────────────────────────────── */
        getTemplate() {
            return JSON.parse(JSON.stringify(this.template));
        },

        loadTemplate(json) {
            if (!json) {
                // Reset to built-in default
                this.template = JSON.parse(JSON.stringify(DEFAULT_TEMPLATE));
            } else {
                try {
                    const t = (typeof json === 'string') ? JSON.parse(json) : json;
                    if (t && t.label && t.elements) {
                        this.template = JSON.parse(JSON.stringify(t));
                    }
                } catch (e) { /* ignore bad JSON */ }
            }
            this._applyCanvasSize();
            this.render();
            this._renderPanel();
        },

        setFieldValues(values) {
            this.options.fieldValues = values;
            this.render();
        },

        setBarcodeValue(val) {
            this.options.barcodeValue = val;
            this.render();
        },

        /** Generate printable HTML for this label with real data */
        buildPrintHTML(data) {
            const lbl = this.template.label;
            const DPI = 96;
            const mmToPx = mm => mm * DPI / 25.4;
            const W = mmToPx(lbl.width);
            const H = mmToPx(lbl.height);

            let elementsHTML = '';
            this.template.elements.forEach((el, elIdx) => {
                const l = mmToPx(el.x);
                const t = mmToPx(el.y);
                const w = mmToPx(el.width);
                const h = mmToPx(el.height);
                const rotStyle = el.rotation ? `transform:rotate(${el.rotation}deg);transform-origin:center center;` : '';
                const base = `position:absolute;left:${l}px;top:${t}px;width:${w}px;height:${h}px;overflow:hidden;box-sizing:border-box;z-index:${elIdx};${rotStyle}`;

                if (el.type === 'barcode') {
                    const BC_FONT = 10;
                    const bcH = Math.max(15, h - (el.showText !== false ? BC_FONT + 8 : 4));
                    const baseNoClip = `position:absolute;left:${l}px;top:${t}px;width:${w}px;height:${h}px;box-sizing:border-box;z-index:${elIdx};${rotStyle}`;
                    elementsHTML += `<div style="${baseNoClip}"><svg id="print-bc-${el.id}" style="display:block;" data-bc-type="${el.barcodeType || 'CODE128'}" data-bc-show-text="${el.showText !== false}" data-bc-h="${bcH}" data-bc-fs="${BC_FONT}"></svg></div>`;
                } else if (el.type === 'text') {
                    let txt = '';
                    if (el.field === 'custom') txt = el.text || '';
                    else if (el.field === 'short_description') txt = data.short_description || '';
                    else if (el.field === 'sku') txt = data.sku || '';
                    else if (el.field === 'price') txt = data.price || '';
                    else if (el.field === 'attributes') txt = data.attributes || '';
                    const transform = el.scrollX ? `transform:translateX(${el.scrollX}px);` : '';
                    elementsHTML += `<div style="${base}display:flex;align-items:center;justify-content:${el.align === 'center' ? 'center' : (el.align === 'right' ? 'flex-end' : 'flex-start')};padding:0 2px;">
                        <span style="font-size:${el.fontSize || 8}px;font-weight:${el.fontWeight || 'normal'};color:${el.color || '#000'};white-space:nowrap;${transform}">${this._esc(txt)}</span></div>`;
                } else if (el.type === 'image') {
                    const src = (el.field === 'product_image')
                        ? (data.productImageSrc || el.src || '')
                        : (el.src || '');
                    elementsHTML += `<img src="${this._esc(src)}" style="${base}object-fit:contain;"/>`;
                } else if (el.type === 'rect') {
                    const fill = el.fill && el.fill !== 'none' ? el.fill : 'transparent';
                    elementsHTML += `<div style="${base}border:${el.borderWidth || 1}px solid ${el.borderColor || '#000'};background:${fill};border-radius:${el.borderRadius || 0}px;"></div>`;
                }
            });

            /* The date, the page title and the URL a browser prints around the
               page live in the @page margin. body{margin:0} does not remove
               them — only a zero @page margin does, and that is also what stops
               a 40mm label being centred on a sheet of A4.

               The title is left empty as well: a printer that has headers
               forced on would otherwise put the word "Label" on every sticker. */
            return `<!DOCTYPE html><html><head><meta charset="utf-8">
<title></title>
<script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.6/dist/JsBarcode.all.min.js"><\/script>
<style>
  @page { size: ${W}px ${H}px; margin: 0; }
  * { margin:0; padding:0; box-sizing:border-box; }
  html, body { background:#fff; margin:0; padding:0; }
  @media print {
    html, body { margin:0; padding:0; width:${W}px; height:${H}px; }
  }
  .label { position:relative; width:${W}px; height:${H}px; background:${lbl.background || '#fff'}; overflow:hidden; }
</style>
</head><body>
<div class="label">${elementsHTML}</div>
<script>
window.onload = function() {
  document.querySelectorAll('[id^="print-bc-"]').forEach(function(svg) {
    try {
      JsBarcode(svg, ${JSON.stringify(data.barcode || '0000000000')}, {
        format: svg.dataset.bcType || 'CODE128',
        displayValue: svg.dataset.bcShowText !== 'false',
        margin: 2, lineColor: '#000', width: 1.5,
        fontSize: parseInt(svg.dataset.bcFs, 10) || 10,
        height: parseInt(svg.dataset.bcH, 10) || 30
      });
    } catch(e){}
  });
  setTimeout(function(){ window.print(); }, 400);
};
<\/script>
</body></html>`;
        },

        _esc(str) {
            return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
        }
    };

    /* ═══════════════════════════════════════════════════════════════════
       PRODUCT PAGE BARCODE MANAGER  (edit_product.php)
    ═══════════════════════════════════════════════════════════════════ */
    function initProductBarcode(opts) {
        const productId = opts.productId;
        const token = opts.apiToken || PG_TOKEN;

        const barcodeInput = document.getElementById('pg-barcode-input');
        const barcodeTypeEl = document.getElementById('pg-barcode-type');
        const svgPreview = document.getElementById('pg-barcode-svg');
        const statusMsg = document.getElementById('pg-barcode-status');
        const btnGenerate = document.getElementById('pg-btn-generate');
        const btnSave = document.getElementById('pg-btn-save-barcode');
        const btnPrint = document.getElementById('pg-btn-print-barcode');
        const barcodeCount = document.getElementById('pg-barcode-count');

        let currentBarcode = opts.barcodeValue || '';
        let currentType = opts.barcodeType || 'CODE128';

        function setStatus(msg, cls) {
            if (!statusMsg) return;
            statusMsg.textContent = msg;
            statusMsg.className = 'form-text ' + (cls || '');
        }

        function renderPreview(value, type) {
            if (!svgPreview) return;
            if (!value) { svgPreview.innerHTML = ''; return; }
            try {
                JsBarcode(svgPreview, value, {
                    format: type || 'CODE128', displayValue: true,
                    fontSize: 11, margin: 4, width: 1.5, height: 40, lineColor: '#000000',
                });
            } catch (e) {
                svgPreview.innerHTML = '<text x="5" y="20" fill="red" font-size="11">' + pgLang('Invalid barcode') + '</text>';
            }
        }

        function updateCount(n) {
            if (!barcodeCount) return;
            barcodeCount.textContent = n > 0 ? n : '';
            barcodeCount.style.display = n > 0 ? '' : 'none';
        }

        // Fetch initial count
        api('get_product_barcodes', { product_id: productId, token }).then(res => {
            if (res.status === 'success') updateCount(res.barcodes.length);
        });

        renderPreview(currentBarcode, currentType);

        if (barcodeInput) {
            barcodeInput.addEventListener('input', () => {
                renderPreview(barcodeInput.value, barcodeTypeEl ? barcodeTypeEl.value : currentType);
            });
        }
        if (barcodeTypeEl) {
            barcodeTypeEl.addEventListener('change', () => {
                renderPreview(barcodeInput ? barcodeInput.value : currentBarcode, barcodeTypeEl.value);
            });
        }

        // Shared save — always inserts a new barcode row
        function saveBarcode(barcode, type, triggerBtn) {
            if (!barcode) { setStatus(pgLang('Barcode value is required.'), 'fg-danger'); return; }
            if (triggerBtn) triggerBtn.disabled = true;
            setStatus(pgLang('Saving...'), 'fg-secondary');
            api('save_product_barcode', { product_id: productId, barcode, barcode_type: type, token })
                .then(res => {
                    setStatus(res.message || (res.status === 'success' ? pgLang('Barcode saved.') : pgLang('Error.')),
                        res.status === 'success' ? 'fg-success' : 'fg-danger');
                    if (res.status === 'success') {
                        currentBarcode = barcode;
                        currentType = type;
                        // Refresh count badge
                        api('get_product_barcodes', { product_id: productId, token }).then(r => {
                            if (r.status === 'success') updateCount(r.barcodes.length);
                        });
                    }
                }).catch(() => setStatus(pgLang('Network error.'), 'fg-danger'))
                .finally(() => { if (triggerBtn) triggerBtn.disabled = false; });
        }

        // Generate — auto-saves
        if (btnGenerate) {
            btnGenerate.addEventListener('click', () => {
                btnGenerate.disabled = true;
                setStatus(pgLang('Generating...'), 'fg-secondary');
                api('generate_product_barcode', {
                    product_id: productId,
                    barcode_type: barcodeTypeEl ? barcodeTypeEl.value : currentType,
                    token
                }).then(res => {
                    if (res.status === 'success') {
                        if (barcodeInput) barcodeInput.value = res.barcode;
                        if (barcodeTypeEl) barcodeTypeEl.value = res.barcode_type;
                        renderPreview(res.barcode, res.barcode_type);
                        saveBarcode(res.barcode, res.barcode_type, null);
                    } else {
                        setStatus(res.message || pgLang('Error generating barcode.'), 'fg-danger');
                    }
                }).catch(() => setStatus(pgLang('Network error.'), 'fg-danger'))
                    .finally(() => { btnGenerate.disabled = false; });
            });
        }

        // Manual save
        if (btnSave) {
            btnSave.addEventListener('click', () => {
                saveBarcode(
                    barcodeInput ? barcodeInput.value.trim() : currentBarcode,
                    barcodeTypeEl ? barcodeTypeEl.value : currentType,
                    btnSave
                );
            });
        }

        if (barcodeInput) {
            barcodeInput.addEventListener('keydown', (e) => {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    saveBarcode(barcodeInput.value.trim(), barcodeTypeEl ? barcodeTypeEl.value : currentType, null);
                }
            });
        }

        // ── Barcodes list modal ──────────────────────────────────────────
        const BLIST_ID = 'pg-barcodes-list-modal';
        function openBarcodeListModal() {
            let $modal = $('#' + BLIST_ID);
            if (!$modal.length) {
                $('body').append(
                    '<dialog class="dialog" id="' + BLIST_ID + '" aria-labelledby="pg-barcodes-list-title">' +
                    '<div class="dialog-header py-3">' +
                    '<h6 class="dialog-title" id="pg-barcodes-list-title"><i class="bi bi-upc-scan me-3"></i>' + pgLang('Barcodes') + '</h6>' +
                    '<button type="button" class="btn-close" data-bs-dismiss="dialog" aria-label="' + h(pgLang('Close')) + '"></button>' +
                    '</div><div class="dialog-body" id="pg-blist-body"><div class="text-center py-5"><div class="spinner-border spinner-border-sm"></div></div></div>' +
                    '</dialog>'
                );
                $modal = $('#' + BLIST_ID);
            }
            pgUI.dialog.show(document.getElementById(BLIST_ID));
            loadBarcodeList();
        }

        function loadBarcodeList() {
            const body = document.getElementById('pg-blist-body');
            if (!body) return;
            api('get_product_barcodes', { product_id: productId, token }).then(res => {
                if (res.status !== 'success') { body.innerHTML = '<p class="fg-danger p-3">' + (res.message || pgLang('Error.')) + '</p>'; return; }
                if (!res.barcodes.length) {
                    body.innerHTML = '<p class="fg-secondary p-5 mb-0">' + pgLang('No barcodes assigned to this product.') + '</p>';
                    updateCount(0);
                    return;
                }
                updateCount(res.barcodes.length);
                let html = '<ul class="list-group list-group-flush">';
                res.barcodes.forEach(bc => {
                    html += '<li class="list-group-item d-flex align-items-center gap-3 py-3" data-bc-id="' + bc.id + '">' +
                        '<span class="font-monospace small flex-grow-1">' + h(bc.barcode) + '</span>' +
                        '<span class="badge theme-secondary">' + h(bc.barcode_type) + '</span>' +
                        '<button type="button" class="btn btn-sm btn-outline theme-success py-0 px-1 pg-bc-print" title="' + pgLang('Print') + '" data-bc="' + h(bc.barcode) + '" data-type="' + h(bc.barcode_type) + '"><i class="bi bi-printer"></i></button>' +
                        '<button type="button" class="btn btn-sm btn-outline theme-danger py-0 px-1 pg-bc-del" title="' + pgLang('Delete') + '" data-id="' + bc.id + '"><i class="bi bi-trash"></i></button>' +
                        '</li>';
                });
                html += '</ul>';
                body.innerHTML = html;

                // Bind delete buttons
                body.querySelectorAll('.pg-bc-del').forEach(btn => {
                    btn.addEventListener('click', () => {
                        if (!confirm(pgLang('Are you sure you want to delete the barcode for this product?'))) return;
                        btn.disabled = true;
                        api('delete_product_barcode', { id: +btn.dataset.id, product_id: productId, token })
                            .then(r => { if (r.status === 'success') loadBarcodeList(); else alert(r.message || pgLang('Error.')); })
                            .catch(() => alert(pgLang('Network error.')))
                            .finally(() => { btn.disabled = false; });
                    });
                });

                // Bind print buttons
                body.querySelectorAll('.pg-bc-print').forEach(btn => {
                    btn.addEventListener('click', () => printBarcode(btn.dataset.bc, btn.dataset.type));
                });
            }).catch(() => { if (body) body.innerHTML = '<p class="fg-danger p-3">' + pgLang('Network error.') + '</p>'; });
        }

        function h(str) { return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;'); }

        const btnListModal = document.getElementById('pg-btn-barcode-list');
        if (btnListModal) btnListModal.addEventListener('click', openBarcodeListModal);

        // Print helper (shared by card print btn and list modal)
        function printBarcode(barcode, type) {
            if (!barcode) { setStatus(pgLang('Save a barcode first before printing.'), 'fg-danger'); return; }
            const tmpWrap = document.createElement('div');
            tmpWrap.style.cssText = 'position:absolute;left:-9999px;top:-9999px;';
            document.body.appendChild(tmpWrap);
            const editor = new LabelEditor(tmpWrap, {
                barcodeValue: barcode,
                productImageSrc: opts.productImageSrc || '',
                fieldValues: {
                    short_description: opts.shortDescription || '',
                    sku: opts.sku || '', price: opts.price || '', attributes: opts.attributes || ''
                }
            });
            editor.loadTemplate(opts.labelTemplate || null);
            const html = editor.buildPrintHTML({
                barcode, short_description: opts.shortDescription || '',
                sku: opts.sku || '', price: opts.price || '',
                attributes: opts.attributes || '', productImageSrc: opts.productImageSrc || ''
            });
            document.body.removeChild(tmpWrap);
            const win = window.open('', '_blank', 'width=600,height=400');
            if (win) { win.document.write(html); win.document.close(); }
        }

        // Print button in card
        if (btnPrint) {
            btnPrint.addEventListener('click', () => {
                const barcode = barcodeInput ? barcodeInput.value.trim() : currentBarcode;
                if (!barcode) { setStatus(pgLang('Save a barcode first before printing.'), 'fg-danger'); return; }
                printBarcode(barcode, barcodeTypeEl ? barcodeTypeEl.value : currentType);
            });
        }
    }

    /* ═══════════════════════════════════════════════════════════════════
       SETTINGS PAGE LABEL DESIGNER (settings.php)
    ═══════════════════════════════════════════════════════════════════ */
    function initLabelDesigner(containerId, templateInputId, opts) {
        const container = document.getElementById(containerId);
        const templateInput = document.getElementById(templateInputId);
        if (!container || !templateInput) return;

        const editor = new LabelEditor(container, {
            barcodeValue: opts.previewBarcode || '1234567890',
            fieldValues: {
                short_description: opts.previewName || 'Product Name',
                sku: opts.previewSku || 'SKU-001',
                price: opts.previewPrice || '99.90',
                attributes: opts.previewAttrs || 'Size: M, Color: Red'
            },
            onchange: function (tpl) {
                templateInput.value = JSON.stringify(tpl);
            }
        });

        // Load saved template if any
        const saved = templateInput.value.trim();
        if (saved) { try { editor.loadTemplate(saved); } catch (e) { } }

        // Expose for external reset
        container._pgEditor = editor;
    }

    /* ═══════════════════════════════════════════════════════════════════
       EXPORTS
    ═══════════════════════════════════════════════════════════════════ */
    window.PgBarcode = {
        LabelEditor,
        initProductBarcode,
        initLabelDesigner,
        api
    };

}(window));

/* ════════════════════════════════════════════════════════════════════════
   BACKGROUND CALL HELPERS
   The calls below run on every backend page.  They used to ask for "api.php"
   next to the current address, which only works for the pages that sit in the
   software directory itself.  The install screen is in a sub directory, so
   there they asked for install/api.php and answered 404 every time.
════════════════════════════════════════════════════════════════════════ */
function pg_api_url() {
    if ((typeof path !== 'undefined') && (typeof software_directory !== 'undefined')) {
        return path + software_directory + '/api.php';
    }
    return 'api.php';
}

// The install screen has no logged in backend user, so the background calls below have nothing
// to report and are skipped there.
function pg_background_calls_allowed() {
    if ((typeof software_backend_user !== 'undefined') && (software_backend_user === false)) {
        return false;
    }
    return true;
}

/* ════════════════════════════════════════════════════════════════════════
   USER ONLINE HEARTBEAT (Runs on all backend pages)
════════════════════════════════════════════════════════════════════════ */
(function () {
    if (!pg_background_calls_allowed()) { return; }

    function sendHeartbeat() {
        var apiUrl = pg_api_url();

        // software_token is defined globally by the backend header script.
        var token = (typeof software_token !== 'undefined') ? software_token : '';

        if (!token) return;

        $.ajax({
            contentType: "application/json",
            url: apiUrl,
            data: JSON.stringify({
                action: "user_online_check",
                token: token
            }),
            type: "POST"
        });
    }

    // First heartbeat on load
    sendHeartbeat();
    // Then every 50 seconds
    setInterval(sendHeartbeat, 50000);
})();

/* ════════════════════════════════════════════════════════════════════════
   SITEMAP CHECK ASYNC TRIGGER (Runs silently in the background)
════════════════════════════════════════════════════════════════════════ */
(function () {
    if (!pg_background_calls_allowed()) { return; }

    // software_token is defined globally by the backend header script; the
    // endpoint refuses the call without it, so there is nothing to send then.
    var token = (typeof software_token !== 'undefined') ? software_token : '';

    if (!token) { return; }

    setTimeout(function () {

        $.ajax({
            contentType: "application/json",
            url: pg_api_url(),
            data: JSON.stringify({
                action: "sitemap_check",
                token: token
            }),
            type: "POST"
        });
    }, 2000); // Wait 2 seconds after page load to not block UI thread

})();

/* ════════════════════════════════════════════════════════════════════════
   UPDATE CHECK ASYNC TRIGGER (Runs silently in the background)
════════════════════════════════════════════════════════════════════════ */
(function () {
    if (!pg_background_calls_allowed()) { return; }

    // Software Update Check ASYNC Trigger
    if (typeof software_update_check_needed !== 'undefined' && software_update_check_needed == true) {
        setTimeout(function () {

            $.ajax({
                contentType: "application/json",
                url: pg_api_url(),
                data: JSON.stringify({
                    action: "software_update_check",
                    token: (typeof software_token !== 'undefined' ? software_token : '')
                }),
                type: "POST"
            });
        }, 3000); // Wait 3 seconds after page load
    }
})();

/* ════════════════════════════════════════════════════════════════════════
   PG UI HELPERS — pgConfirm, pgToast, ARIA auto-injectors
   Additive: these replace window.confirm and ad-hoc alerts for new code.
   Older call sites still use the browser dialogs and are migrated as they
   are touched.
════════════════════════════════════════════════════════════════════════ */
(function (window) {
    "use strict";

    function escapeHtml(s) {
        return String(s == null ? '' : s)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function ensureToastContainer() {
        var c = document.getElementById('pg-toast-container');
        if (!c) {
            c = document.createElement('div');
            c.id = 'pg-toast-container';
            c.className = 'toast-container position-fixed top-0 end-0 p-5';
            c.setAttribute('aria-live', 'polite');
            c.setAttribute('aria-atomic', 'true');
            document.body.appendChild(c);
        }
        return c;
    }

    // On the body under the panel's top bar the container starts below the
    // bar and stays under its stacking level (1030), above sticky bars
    // (1020): a lasting toast would otherwise cover the bell and account
    // buttons, and the header menus open inside the bar's stacking context.
    // Inside a dialog (pgUI.hostLayer) or without a bar it keeps the top.
    function placeToastContainer(c) {
        var underBar = c.parentNode === document.body && !!document.querySelector('.software-navbar.fixed-top');
        c.style.top = underBar ? 'var(--pg-navbar-h)' : '';
        c.style.zIndex = underBar ? '1025' : '1090';
    }

    /**
     * pgToast({message, variant, delay})
     * variant: success | danger | warning | info (default: info)
     * delay: ms (default: 4000), success and info only: a danger or warning
     * toast stays until its Close button is used, so a keyboard or
     * magnifier user can still reach it, and the same one is not stacked.
     */
    window.pgToast = function (opts) {
        opts = opts || {};
        var message = opts.message != null ? String(opts.message) : '';
        var variantWhitelist = { success: 1, danger: 1, warning: 1, info: 1 };
        var variant = variantWhitelist[opts.variant] ? opts.variant : 'info';
        var delay = typeof opts.delay === 'number' ? opts.delay : 4000;
        var sticky = variant === 'danger' || variant === 'warning';

        var iconMap = {
            success: 'bi-check-circle-fill',
            danger: 'bi-exclamation-triangle-fill',
            warning: 'bi-exclamation-circle-fill',
            info: 'bi-info-circle-fill'
        };

        var container = ensureToastContainer();
        // An open dialog's backdrop would cover a body-level toast: the
        // container goes into the top dialog until it hides.
        if (window.pgUI && pgUI.hostLayer) {
            pgUI.hostLayer(container);
        }
        placeToastContainer(container);
        var key = variant + '\n' + message;
        if (sticky) {
            for (var i = 0; i < container.children.length; i++) {
                if (container.children[i].getAttribute('data-pg-toast-key') === key) {
                    return window.pgUI ? pgUI.toast.find(container.children[i]) : null;
                }
            }
        }
        var el = document.createElement('div');
        el.setAttribute('data-pg-toast-key', key);
        // Bootstrap 6 has no text-bg-*: the fill and its ink are two classes.
        el.className = 'toast align-items-center bg-' + variant + ' fg-contrast-' + variant + ' border-0';
        el.setAttribute('role', variant === 'danger' ? 'alert' : 'status');
        el.setAttribute('aria-live', variant === 'danger' ? 'assertive' : 'polite');
        el.setAttribute('aria-atomic', 'true');
        el.innerHTML =
            '<div class="d-flex">' +
                '<div class="toast-body">' +
                    '<i class="bi ' + iconMap[variant] + ' me-3" aria-hidden="true"></i>' +
                    escapeHtml(message) +
                '</div>' +
                '<button type="button" class="btn-close me-3 m-auto" data-bs-dismiss="toast" aria-label="' + escapeHtml(lang('Close')) + '"></button>' +
            '</div>';
        container.appendChild(el);

        var t = window.pgUI ? pgUI.toast.get(el, { delay: delay, autohide: !sticky }) : null;

        if (t) {
            pgUI.toast.on(el, 'hidden', function () { el.remove(); });
            t.show();
            return t;
        } else {
            // graceful degradation
            el.classList.add('show');
            if (!sticky) {
                setTimeout(function () { el.remove(); }, delay);
            } else {
                el.querySelector('.btn-close').addEventListener('click', function () { el.remove(); });
            }
            return null;
        }
    };

    /**
     * pgConfirm({title, message, confirmText, cancelText, variant}): Promise<bool>
     * variant: danger (default) | primary | warning
     */
    window.pgConfirm = function (opts) {
        opts = opts || {};
        var title = opts.title != null ? String(opts.title) : lang('Confirm');
        var message = opts.message != null ? String(opts.message) : '';
        var confirmText = opts.confirmText != null ? String(opts.confirmText) : lang('OK');
        var cancelText = opts.cancelText != null ? String(opts.cancelText) : lang('Cancel');
        var variantWhitelist = { danger: 1, primary: 1, warning: 1, success: 1 };
        var variant = variantWhitelist[opts.variant] ? opts.variant : 'danger';

        return new Promise(function (resolve) {
            var modalEl = document.createElement('dialog');
            modalEl.className = 'dialog pg-dialog-centered';
            modalEl.tabIndex = -1;
            modalEl.setAttribute('aria-labelledby', 'pg-confirm-title');
            modalEl.setAttribute('aria-modal', 'true');
            modalEl.setAttribute('role', 'dialog');
            modalEl.innerHTML =
                '<div class="dialog-header">' +
                            '<h5 class="dialog-title" id="pg-confirm-title">' + escapeHtml(title) + '</h5>' +
                            '<button type="button" class="btn-close" data-bs-dismiss="dialog" aria-label="' + escapeHtml(lang('Close')) + '"></button>' +
                        '</div>' +
                        '<div class="dialog-body">' + escapeHtml(message) + '</div>' +
                        '<div class="dialog-footer">' +
                            '<button type="button" class="btn btn-solid theme-secondary" data-pg-action="cancel" data-bs-dismiss="dialog">' + escapeHtml(cancelText) + '</button>' +
                            '<button type="button" class="btn btn-solid theme-' + variant + '" data-pg-action="confirm">' + escapeHtml(confirmText) + '</button>' +
                        '</div>';
            document.body.appendChild(modalEl);

            var resolved = false;
            function done(value) {
                if (resolved) return;
                resolved = true;
                resolve(value);
            }

            var modal = window.pgUI ? pgUI.dialog.get(modalEl, { backdrop: 'static', keyboard: true }) : null;

            if (!modal) {
                // Fallback: native confirm if Bootstrap not loaded
                modalEl.remove();
                done(window.confirm(message));
                return;
            }

            modalEl.querySelector('[data-pg-action="confirm"]').addEventListener('click', function () {
                done(true);
                modal.hide();
            });
            pgUI.dialog.on(modalEl, 'hidden', function () {
                done(false); // dismiss/cancel/escape -> false
                modalEl.remove();
            });
            modal.show();
            // Move focus to confirm button after show, for keyboard users
            pgUI.dialog.on(modalEl, 'shown', function () {
                var btn = modalEl.querySelector('[data-pg-action="confirm"]');
                if (btn) btn.focus();
            });
        });
    };

    // Wrappers that lay the field and its add-ons out in one row (no wrap): an
    // error message inside one squeezes the field to a sliver, so it goes
    // after the wrapper.
    var PG_FEEDBACK_WRAPPERS = '.input-group, .form-adorn, .chip-input, .switch';

    function pgIsFeedback(node) {
        return !!(node && node.classList && node.classList.contains('invalid-feedback'));
    }

    function pgIsControl(node) {
        return !!(node && /^(INPUT|SELECT|TEXTAREA)$/.test(node.tagName));
    }

    // Right after the field, or after the row wrapper holding it (a switch
    // inside a .form-field: at the end of the .form-field).
    function pgPutFeedback(el, feedback) {
        var wrapper = el.closest ? el.closest(PG_FEEDBACK_WRAPPERS) : null;
        var field = (wrapper && wrapper.classList.contains('switch') && wrapper.closest) ? wrapper.closest('.form-field') : null;

        if (field) {
            field.appendChild(feedback);
        } else if (wrapper) {
            wrapper.insertAdjacentElement('afterend', feedback);
        } else {
            el.insertAdjacentElement('afterend', feedback);
        }
    }

    // The message that belongs to a field: a later sibling before the next
    // control (Bootstrap's .is-invalid ~ .invalid-feedback), else the one
    // pgPutFeedback() places after its row wrapper.
    function pgFieldFeedback(el) {
        var wrapper = el.closest ? el.closest(PG_FEEDBACK_WRAPPERS) : null;
        var field = (wrapper && wrapper.classList.contains('switch') && wrapper.closest) ? wrapper.closest('.form-field') : null;
        var node;

        for (node = el.nextElementSibling; node; node = node.nextElementSibling) {
            if (pgIsFeedback(node)) return node;
            if (pgIsControl(node) || (node.querySelector && node.querySelector('input, select, textarea'))) break;
        }

        node = field ? field.lastElementChild : (wrapper ? wrapper.nextElementSibling : null);

        return pgIsFeedback(node) ? node : null;
    }

    // Adds or removes one id in aria-describedby, keeping the others.
    function pgDescribedBy(el, id, on) {
        var ids = (el.getAttribute('aria-describedby') || '').split(/\s+/).filter(Boolean);
        var at = ids.indexOf(id);

        if (on && at === -1) ids.push(id);
        if (!on && at !== -1) ids.splice(at, 1);

        if (ids.length) {
            el.setAttribute('aria-describedby', ids.join(' '));
        } else {
            el.removeAttribute('aria-describedby');
        }
    }

    // Mirrors .is-invalid into aria-invalid and links the field's message
    // through aria-describedby while the field is invalid, so a screen reader
    // hears the error and its reason; both go when the class does. A message
    // hidden by CSS is still read through the link.
    function pgFieldAria(el) {
        var on = el.classList.contains('is-invalid');
        var feedback = pgFieldFeedback(el);

        if (on) {
            el.setAttribute('aria-invalid', 'true');
        } else if (el.getAttribute('aria-invalid') === 'true') {
            el.removeAttribute('aria-invalid');
        }

        if (!feedback) return;

        if (on && !feedback.id) {
            feedback.id = (el.id || ('pg-field-' + Math.random().toString(36).slice(2, 8))) + '-feedback';
        }

        if (feedback.id) pgDescribedBy(el, feedback.id, on);
    }

    // Marks a field invalid or clears it, for a script that checks a field
    // after the page was drawn. With a message and none on the page for the
    // field, one is made where pgPutFeedback() puts it; clearing removes it.
    function pgFieldInvalid(el, on, message) {
        var feedback = pgFieldFeedback(el);
        var made = !!(feedback && feedback.hasAttribute('data-pg-made'));

        if (on && message) {
            if (!feedback) {
                feedback = document.createElement('div');
                feedback.className = 'invalid-feedback d-block pg-inline-feedback';
                feedback.setAttribute('data-pg-made', '');
                pgPutFeedback(el, feedback);
                made = true;
            }

            if (made) feedback.textContent = message;
        }

        el.classList.toggle('is-invalid', !!on);
        pgFieldAria(el);

        if (!on && made) feedback.remove();
    }

    // Puts an invalid field's error message where it can be read: right after
    // the field, or after the row wrapper holding it (a switch inside a
    // .form-field: at the end of the .form-field). A message liveform printed
    // after the field is moved there; one is made from errs (field name ->
    // text) when the page has none. Runs for the output_errors() inline call
    // and again on DOMContentLoaded, so a message already in place is kept
    // and never doubled.
    function pgPlaceInlineFeedback(el, errs) {
        if (el.dataset.pgFeedbackInjected === '1') return;

        var wrapper = el.closest ? el.closest(PG_FEEDBACK_WRAPPERS) : null;
        var field = (wrapper && wrapper.classList.contains('switch') && wrapper.closest) ? wrapper.closest('.form-field') : null;
        var own = pgIsFeedback(el.nextElementSibling) ? el.nextElementSibling : null;
        var ownMovable = !!(own && own.classList.contains('pg-inline-feedback'));
        var placed = null;

        function put(feedback) {
            pgPutFeedback(el, feedback);
        }

        if (wrapper) {
            placed = field ? field.lastElementChild : wrapper.nextElementSibling;
            if (!pgIsFeedback(placed) || placed === own) placed = null;
        }

        if (placed) {
            if (ownMovable) own.remove();
            el.dataset.pgFeedbackInjected = '1';
            return;
        }

        if (own) {
            // A static message of the page's own stays where the page put it.
            if (wrapper && ownMovable) put(own);
            el.dataset.pgFeedbackInjected = '1';
            return;
        }

        if (!errs) return;
        var name = el.getAttribute('name');
        if (!name) return;
        // Strip [] suffix used for array-valued inputs
        var key = name.replace(/\[\]$/, '');
        var msg = errs[name] || errs[key];
        if (!msg) return;

        var feedback = document.createElement('div');
        feedback.className = 'invalid-feedback d-block pg-inline-feedback';
        feedback.textContent = msg;
        var fid = el.id || ('pg-field-' + Math.random().toString(36).slice(2, 8));
        if (!el.id) el.id = fid;
        feedback.id = fid + '-feedback';
        if (!el.hasAttribute('aria-describedby')) {
            el.setAttribute('aria-describedby', feedback.id);
        }
        put(feedback);
        el.dataset.pgFeedbackInjected = '1';
    }

    // Auto-injectors: aria-invalid for .is-invalid fields and required-marker on labels.
    // Runs after DOM is ready and once on Bootstrap form re-renders aren't expected here.
    function pgInjectAriaAndRequiredMarkers(root) {
        root = root || document;

        // ARIA invalid for liveform / Bootstrap is-invalid fields
        var invalids = root.querySelectorAll(
            '.is-invalid, input[style*="border: red"], select[style*="border: red"], textarea[style*="border: red"]'
        );
        invalids.forEach(function (el) {
            if (!el.hasAttribute('aria-invalid')) el.setAttribute('aria-invalid', 'true');

            pgPlaceInlineFeedback(el, window.__pgFieldErrors);

            if (pgIsControl(el) && el.classList.contains('is-invalid')) pgFieldAria(el);
        });

        // Faz 4a: auto-bind <label> elements that have no `for` attribute to a
        // neighbouring input/select/textarea that has an `id`. Many legacy admin
        // pages render labels and fields side-by-side without explicit `for`, which
        // breaks click-to-focus and screen-reader announcement.
        var labelsWithoutFor = root.querySelectorAll('label:not([for])');
        labelsWithoutFor.forEach(function (label) {
            // If a child input/select/textarea already supplies the implicit association, leave it.
            if (label.querySelector('input, select, textarea')) return;
            // Find the nearest field after this label.
            var nextField = label.nextElementSibling;
            while (nextField) {
                var t = nextField.tagName;
                if (t === 'INPUT' || t === 'SELECT' || t === 'TEXTAREA') break;
                if (nextField.tagName === 'LABEL') { nextField = null; break; }
                nextField = nextField.nextElementSibling;
            }
            if (!nextField || !nextField.id) return;
            if (nextField.type === 'hidden' || nextField.type === 'submit') return;
            // Skip if another label already targets this field.
            if (document.querySelector('label[for="' + nextField.id.replace(/"/g, '\\"') + '"]')) return;
            label.setAttribute('for', nextField.id);
        });

        // Faz 3d: icon-only buttons/links get aria-label from their title attribute.
        // Many legacy admin action buttons (view_files action col, etc.) carry only a
        // BI/Material icon inside and rely on `title=""` for the affordance label.
        var iconOnlyCandidates = root.querySelectorAll(
            'button[title]:not([aria-label]), a[title]:not([aria-label])'
        );
        iconOnlyCandidates.forEach(function (el) {
            var visibleText = (el.textContent || '').replace(/\s+/g, '').length;
            if (visibleText > 0) return; // already has visible text
            var t = el.getAttribute('title');
            if (t && t.trim().length) el.setAttribute('aria-label', t.trim());
        });

        // Required marker on associated label
        var requiredFields = root.querySelectorAll(
            'input[required], select[required], textarea[required]'
        );
        requiredFields.forEach(function (field) {
            if (field.type === 'hidden' || field.type === 'submit') return;
            var label = null;
            if (field.id) label = document.querySelector('label[for="' + field.id.replace(/"/g, '\\"') + '"]');
            if (!label) {
                var p = field.parentElement;
                while (p && p.tagName !== 'LABEL' && p !== document.body) p = p.parentElement;
                if (p && p.tagName === 'LABEL') label = p;
            }
            // A label whose text already starts with "*" carries its own
            // marker: a second one would read "*Name *".
            if (label && !label.querySelector('.pg-required-marker') && !/^\s*\*/.test(label.textContent)) {
                var span = document.createElement('span');
                span.className = 'pg-required-marker fg-danger ms-1';
                span.setAttribute('aria-hidden', 'true');
                span.textContent = '*';
                label.appendChild(span);
            }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () { pgInjectAriaAndRequiredMarkers(); });
    } else {
        pgInjectAriaAndRequiredMarkers();
    }

    // Scripts mark fields after the page is drawn (a product code already in
    // use, a blank SKU on save, a quick-account field the server refused) and
    // clear them again, mostly by the class alone: the observer keeps
    // aria-invalid and the message link in step with it, whoever changes it.
    if (window.MutationObserver) {
        new MutationObserver(function (records) {
            records.forEach(function (record) {
                var el = record.target;

                if (!pgIsControl(el)) return;

                var was = /(^|\s)is-invalid(\s|$)/.test(record.oldValue || '');

                if (was !== el.classList.contains('is-invalid')) pgFieldAria(el);
            });
        }).observe(document.documentElement, { subtree: true, attributes: true, attributeFilter: ['class'], attributeOldValue: true });
    }

    // Expose for callers that render forms after initial load.
    window.pgInjectAriaAndRequiredMarkers = pgInjectAriaAndRequiredMarkers;
    window.pgFieldInvalid = pgFieldInvalid;
})(window);

/* ── User permission rows (add_user.php, edit_user.php) ────────────────────
   The rights groups are rows with a summary and an offcanvas panel; the panel
   holds the checkboxes that used to sit in the page. This keeps the two in
   step: the numbers on a row are counted from the boxes in its panel, never
   stored anywhere, so they cannot drift from what will be posted.

   Two kinds of gate. A group that owns a column (manage_calendars,
   manage_visitors, manage_ecommerce) posts its gate and the selections are left
   alone when it is switched off - the column already denies the right, and
   wiping the choices would lose them on the next save. A group that owns no
   column (folders, shared content, ads, private access) has nothing to post, so
   its gate means "there is a selection here": switching it off clears the
   panel, switching it back on puts back exactly what was cleared. Nothing is
   written until the form is saved either way. */
function initUserPermissionRows() {

    var block = document.getElementById('pg_permissions');

    if (!block) {
        return;
    }

    var rows = Array.prototype.slice.call(block.querySelectorAll('.pg-perm'));

    function panelOf(id) {
        return document.getElementById('pgperm_panel_' + id);
    }

    function boxesIn(scope, id) {
        var panel = panelOf(id);
        if (!panel) {
            return [];
        }
        var host = panel.querySelector('[data-pg-count="' + scope + '"]');
        return host ? Array.prototype.slice.call(host.querySelectorAll('input.multiselect-checkbox')) : [];
    }

    // Every checkbox in this group's panel, whichever list it belongs to.
    function allBoxes(id) {
        var panel = panelOf(id);
        return panel ? Array.prototype.slice.call(panel.querySelectorAll('input.multiselect-checkbox')) : [];
    }

    function countChecked(list) {
        var n = 0;
        for (var i = 0; i < list.length; i++) {
            if (list[i].checked && !list[i].disabled) {
                n++;
            }
        }
        return n;
    }

    function refreshRow(row) {

        var id    = row.getAttribute('data-pg-perm');
        var pills = Array.prototype.slice.call(row.querySelectorAll('.pg-perm-pill'));
        var parts = [];
        var total = 0;

        pills.forEach(function (pill) {

            var scope = pill.getAttribute('data-pg-count-for');
            var n     = countChecked(boxesIn(scope, id));
            var unit  = (n === 1) ? pill.getAttribute('data-pg-unit-one') : pill.getAttribute('data-pg-unit-many');

            total += n;

            if (n > 0) {
                pill.textContent = n + ' ' + unit;
                parts.push(pill.textContent);
            } else {
                pill.textContent = '';
            }
        });

        // The panel repeats the same numbers in its footer, so an operator deep
        // in a folder tree can see what they have without closing it.
        var footer = document.querySelector('[data-pg-panel-count="' + id + '"]');
        if (footer) {
            footer.textContent = parts.length ? parts.join(' · ') : (block.getAttribute('data-pg-empty') || '');
        }

        // A row with counters and nothing counted says so where the numbers go.
        var summary = row.querySelector('.pg-perm-summary');
        if (summary && pills.length) {
            var none = summary.querySelector('.pg-perm-none');
            if (total === 0 && !none) {
                none = document.createElement('span');
                none.className = 'pg-perm-none';
                none.textContent = block.getAttribute('data-pg-empty') || '';
                summary.appendChild(none);
            } else if (total > 0 && none) {
                none.parentNode.removeChild(none);
            }
        }

        return total;
    }

    function refreshTally() {

        // The tally lives in the card header, which is outside the rows box.
        var target = document.querySelector('[data-pg-tally]');
        if (!target) {
            return;
        }

        var on = 0;
        rows.forEach(function (row) {
            var gate = row.querySelector('.pg-perm-gate');
            if (gate && gate.checked) {
                on++;
            }
        });

        // The sentence comes from lang() with its placeholders left in, so the
        // word order stays the translator's.
        target.textContent = String(target.getAttribute('data-pg-tally'))
            .replace('{n}', on)
            .replace('{m}', rows.length);
    }

    // What a column-less gate switches: the fields it names, or the checkboxes
    // of the scope it names, or - when it names neither - everything in its
    // panel. Named rather than inferred because a group can carry a counter
    // that is not what grants the access: page types are counted on the content
    // row, but the folder list is the access.
    function gateTargets(row) {

        var gate   = row.querySelector('.pg-perm-gate');
        var id     = row.getAttribute('data-pg-perm');
        var panel  = panelOf(id);
        var fields = gate ? gate.getAttribute('data-pg-gate-fields') : null;

        if (fields && panel) {
            return fields.split(',').map(function (name) {
                return panel.querySelector('#' + name.trim());
            }).filter(function (el) {
                return !!el;
            });
        }

        var scope = gate ? gate.getAttribute('data-pg-gate-scope') : null;

        return scope ? boxesIn(scope, id) : allBoxes(id);
    }

    function applyGate(row, opening) {

        var gate = row.querySelector('.pg-perm-gate');
        if (!gate) {
            return;
        }

        var id = row.getAttribute('data-pg-perm');

        row.classList.toggle('is-off', !gate.checked);

        // Only a gate with no column of its own touches what is inside.
        if (gate.hasAttribute('data-pg-gate-ui')) {

            var targets = gateTargets(row);

            if (!gate.checked) {

                row.pgCleared = targets.filter(function (box) {
                    return box.checked && !box.disabled;
                });

                row.pgCleared.forEach(function (box) {
                    box.checked = false;
                    box.dispatchEvent(new Event('change', { bubbles: true }));
                });

            } else if (row.pgCleared && row.pgCleared.length) {

                row.pgCleared.forEach(function (box) {
                    box.checked = true;
                    box.dispatchEvent(new Event('change', { bubbles: true }));
                });
                row.pgCleared = null;

            } else if (opening) {

                // Switched on with nothing behind it. A gate over a pair of
                // fields turns the first one on, because an empty panel would
                // grant nothing and the switch would spring back; a gate over a
                // list opens the panel so the operator can pick.
                if (gate.getAttribute('data-pg-gate-fields')) {

                    if (countChecked(targets) === 0 && targets.length) {
                        targets[0].checked = true;
                        targets[0].dispatchEvent(new Event('change', { bubbles: true }));
                    }

                } else if (countChecked(targets) === 0) {

                    var panel = panelOf(id);
                    if (panel && window.pgUI) {
                        pgUI.drawer.show(panel);
                    }
                }
            }
        }

        refreshRow(row);
        refreshTally();
    }

    // The chat launcher is fixed at z-index 1080, above Bootstrap's offcanvas
    // (1045), so it sits on top of the panel's footer buttons. Marking the body
    // while a panel is open lets the stylesheet take it out of the way; raising
    // the panel instead would leave the launcher floating over the backdrop.
    document.querySelectorAll('.pg-perm-panel').forEach(function (panel) {
        pgUI.drawer.on(panel, 'show', function () {
            document.body.classList.add('pg-side-panel-open');
        });
        pgUI.drawer.on(panel, 'hidden', function () {
            document.body.classList.remove('pg-side-panel-open');
        });
    });

    function syncGate(row, gate) {

        var wanted = (countChecked(gateTargets(row)) > 0);

        if (gate.checked !== wanted) {
            gate.checked = wanted;
            refreshTally();
        }

        row.classList.toggle('is-off', !gate.checked);
    }

    rows.forEach(function (row) {

        var id    = row.getAttribute('data-pg-perm');
        var gate  = row.querySelector('.pg-perm-gate');
        var panel = panelOf(id);

        if (gate) {
            gate.addEventListener('change', function () {
                applyGate(row, gate.checked);
            });

            // The server sets the gate from the counts it knows; this settles
            // any disagreement from what is actually ticked, so the row and its
            // panel start out saying the same thing.
            if (gate.hasAttribute('data-pg-gate-ui')) {
                syncGate(row, gate);
            } else {
                row.classList.toggle('is-off', !gate.checked);
            }
        }

        if (panel) {
            // One listener per panel rather than per checkbox: the folder tree
            // is rebuilt by other code on this screen, and delegation survives
            // that. "Select all" fires change on each box, so this covers it.
            panel.addEventListener('change', function (event) {

                if (!event.target || event.target.type !== 'checkbox') {
                    return;
                }

                refreshRow(row);
                pgSyncAclExpiry(panel);
                pgSyncCheckboxLists(panel);

                // A UI gate follows what it drives: picking the first folder
                // turns the group on, clearing the last one turns it off.
                if (gate && gate.hasAttribute('data-pg-gate-ui')) {
                    syncGate(row, gate);
                }
            });
        }

        refreshRow(row);

        if (panel) {
            pgSyncAclExpiry(panel);
            pgSyncCheckboxLists(panel);

            // The two buttons inside an expiry box. Delegated because the
            // folder tree is rendered by PHP and never rebuilt here, but the
            // boxes come and go with the class that opens them.
            panel.addEventListener('click', function (event) {

                var add = event.target.closest ? event.target.closest('.pg-acl-expiry-add') : null;

                if (add) {
                    event.preventDefault();
                    pgOpenAclExpiry(add.closest('.pg-acl-expiry'), true);
                    return;
                }

                var clear = event.target.closest ? event.target.closest('.pg-acl-expiry-clear') : null;

                if (clear) {
                    event.preventDefault();
                    pgCloseAclExpiry(clear.closest('.pg-acl-expiry'));
                }
            });
        }
    });

    refreshTally();

    // The role picker decides whether these rows are shown at all. A disabled
    // picker posts through a hidden field, so both are consulted.
    var role = document.querySelector('.pg-roles input[name="role"]:checked')
        || document.querySelector('input[type="hidden"][name="role"]');

    if (role) {
        pgUserPermissionRole(role.value);
    }
}

/* Role decides whether the rows are shown at all: every role above "user"
   carries all rights, so the block is replaced by a notice naming them. The
   old markup (still used by import_users.php) is handled in change_user_role
   below; this is only the new one. */
function pgUserPermissionRole(role) {

    var block  = document.getElementById('pg_permissions_block');
    var notice = document.getElementById('pg_permissions_everything');

    if (!block || !notice) {
        return false;
    }

    var limited = (String(role) === '3');

    block.classList.toggle('d-none', !limited);
    notice.classList.toggle('d-none', limited);

    return true;
}


// ── Site Settings: the hub ──────────────────────────────────────────────────
//
// The hub is a way in, so the field is the first thing on it and it filters
// what is already on screen rather than submitting anywhere: the answer to
// "where is that setting" is in the tiles and their section chips.
//
// Each tile and each related-screen row carries the words it can be found by in
// data-pgsearch, built on the server from the labels it prints plus the same
// keyword list the command palette uses, so a renamed section stays searchable
// without a second list to keep in step.
function initSettingsHubSearch() {

    var field = document.getElementById('pg_settings_hub_search');

    if (!field) {
        return;
    }

    var empty   = document.querySelector('.pg-hub-empty');
    var counter = document.getElementById('pg_settings_hub_count');
    var heading = document.getElementById('pg_settings_tools_heading');
    var targets = Array.prototype.slice.call(document.querySelectorAll('[data-pgsearch]'));

    function apply() {

        var needle = field.value.trim().toLowerCase();
        var tiles = 0;
        var rows = 0;

        targets.forEach(function (el) {

            var hit = (needle === '') || (el.getAttribute('data-pgsearch').indexOf(needle) !== -1);

            // The card is the grid item and a related-screen row is its own
            // box, so either way what is hidden is the element itself.
            el.classList.toggle('d-none', !hit);

            if (!hit) return;

            if (el.classList.contains('list-group-item')) rows++;
            else tiles++;
        });

        // A group whose every row was filtered out has nothing left to say.
        Array.prototype.slice.call(document.querySelectorAll('#pg_settings_hub_tools .card')).forEach(function (card) {
            card.classList.toggle('d-none', card.querySelectorAll('.list-group-item:not(.d-none)').length === 0);
        });

        // The heading over the related screens goes with them.
        if (heading) {
            heading.classList.toggle('d-none', rows === 0);
        }

        if (empty) {
            empty.classList.toggle('d-none', (tiles + rows) > 0);
        }

        // Said rather than only shown: on a filter that leaves one tile the
        // count is how the operator knows Enter will open the right thing.
        //
        // The wording comes off the element, translated by the server: lang()
        // in the browser only knows the keys the page preloaded, and a count
        // needs both its singular and its plural.
        if (counter) {
            var found = tiles + rows;
            var template = counter.getAttribute(found === 1 ? 'data-pgcount-one' : 'data-pgcount-many') || '%d';

            counter.textContent = (needle === '') ? '' : template.replace('%d', found);
        }
    }

    field.addEventListener('input', apply);

    field.addEventListener('keydown', function (e) {

        // Enter on a search that left one thing standing opens it: the field is
        // being used to get somewhere, not to read a list.
        if (e.key === 'Enter') {

            e.preventDefault();

            var hit = document.querySelector('#pg_settings_hub .pg-tile:not(.d-none) .pg-tile-head')
                   || document.querySelector('#pg_settings_hub_tools .list-group-item:not(.d-none)');

            if (hit) hit.click();
            return;
        }

        if (e.key === 'Escape') {
            field.value = '';
            apply();
        }
    });

    // "/" focuses the field from anywhere on the page, which is the shortcut
    // the hint in the field promises. Not while something else is being typed
    // into, and not when a modifier is held -- that is somebody using the
    // browser, not this page.
    document.addEventListener('keydown', function (e) {

        if (e.key !== '/' || e.ctrlKey || e.metaKey || e.altKey) return;

        var on = document.activeElement;

        if (on && (on.tagName === 'INPUT' || on.tagName === 'TEXTAREA' || on.tagName === 'SELECT' || on.isContentEditable)) {
            return;
        }

        e.preventDefault();
        field.focus();
        field.select();
    });

    initSettingsHubRecent();
}


// The three settings an operator opened last, offered back as chips.
//
// Per-viewer convenience, so it lives in localStorage rather than in the
// database: it is worth nothing to anybody else, and a browser that refuses
// storage (a private window, site data cleared) just shows no chips. Every
// read and write is guarded for that reason.
function initSettingsHubRecent() {

    var KEY = 'pg_settings_recent';
    var MAX = 4;
    var box = document.getElementById('pg_settings_recent');

    function read() {
        try {
            var raw = window.localStorage.getItem(KEY);
            var list = raw ? JSON.parse(raw) : [];
            return Array.isArray(list) ? list : [];
        } catch (err) {
            return [];
        }
    }

    function write(list) {
        try {
            window.localStorage.setItem(KEY, JSON.stringify(list));
        } catch (err) {
            /* storage refused; the chips are a convenience, not a feature */
        }
    }

    // Recorded on the way out, on the link itself, so a chip leads exactly
    // where the operator went.
    document.addEventListener('click', function (e) {

        var link = e.target && e.target.closest ? e.target.closest('[data-pgrecent]') : null;

        if (!link) return;

        var entry = { label: link.getAttribute('data-pgrecent'), url: link.getAttribute('href') };

        if (!entry.label || !entry.url) return;

        var list = read().filter(function (item) { return item && item.url !== entry.url; });

        list.unshift(entry);
        write(list.slice(0, MAX));
    });

    if (!box) return;

    var list = read();

    if (!list.length) return;

    list.forEach(function (item) {

        if (!item || !item.label || !item.url) return;

        // Built with the DOM rather than innerHTML: the label came out of
        // storage, and storage is not a place this page can vouch for.
        var chip = document.createElement('a');
        chip.className = 'chip';
        chip.href = item.url;
        chip.textContent = item.label;
        box.appendChild(chip);
    });

    box.classList.remove('d-none');
}


// ── Site Settings: filter the cards of one category ──────────────────────────
//
// Same idea one level down. The cards stay in the DOM either way, because the
// form posts every field on the screen whether it is on show or not -- hiding a
// card must not drop what it holds.
function initSettingsFilter() {

    var field = document.getElementById('pg_settings_filter');

    if (!field) {
        return;
    }

    var cards = Array.prototype.slice.call(document.querySelectorAll('#pg_settings_sections > div'));
    var status = document.getElementById('pg_settings_filter_status');

    // Read once: the text of a card does not change as it is typed over, and
    // reading it per keystroke on the commerce screen is a hundred kilobytes of
    // textContent per character.
    cards.forEach(function (card) {
        card.setAttribute('data-pgtext', (card.textContent || '').replace(/\s+/g, ' ').toLowerCase());
    });

    // Only what a person typed filters. A password manager writes the saved
    // username into this box a moment after the page loads (it sits outside the
    // form, so it has no share of the form's autofill guard) and raises the same
    // trusted input event a keystroke does; acted on, it hid every card whose
    // text lacked the name. A fill lands in a box nobody is in, typing never
    // does, so a value that arrives without focus is wiped instead. The same
    // goes for a value already there when this runs.
    function apply() {

        if (document.activeElement !== field) {
            field.value = '';
        }

        var needle = field.value.trim().toLowerCase();
        var shown = 0;

        cards.forEach(function (card) {
            var hide = (needle !== '') && (card.getAttribute('data-pgtext').indexOf(needle) === -1);

            card.classList.toggle('d-none', hide);

            if (!hide) {
                shown++;
            }
        });

        // How many cards are left is said politely; a word that matches none
        // also says so on screen, where it would otherwise leave a blank page.
        if (status) {
            var text = '';

            if (needle !== '') {
                text = shown
                    ? (status.getAttribute('data-pg-count') || '').replace('{var:1}', shown).replace('{var:2}', cards.length)
                    : (status.getAttribute('data-pg-empty') || '');
            }

            status.classList.toggle('visually-hidden', shown > 0);

            if (status.textContent !== text) {
                status.textContent = text;
            }
        }
    }

    field.addEventListener('input', apply);
    apply();
}


/* ────────────────────────────────────────────────────────────────────────────
   MARKUP THAT ARRIVED AFTER THE PAGE DID

   The handlers in the ready block above bind to the elements that existed when
   the page loaded. Anything fetched later -- a pane of the settings modal --
   has to be given the same treatment, so the parts that can be re-run over a
   scope live in these helpers and both callers use them: the ready block runs
   them over the document, the settings modal over its pane.

   What is NOT here: the code editors, the tag fields and the rest of the controls
   whose init sits in an inline <script> beside the control. jQuery's .html()
   runs the scripts it injects, so those start themselves -- which is why a
   pane is injected with .html() and never with innerHTML.
   ──────────────────────────────────────────────────────────────────────────── */

// Bootstrap binds ONE component instance per element. A dropdown, tab or
// button toggle owns its element's slot; a popover bound to it first takes the
// slot instead, the toggle's own instance is refused ("doesn't allow more than
// one instance per element") and the menu it opens can never be closed by the
// document click handler. Those toggles keep the native title tooltip.
var _pgNoTitlePopover = ':not([data-bs-toggle="menu"]):not([data-bs-toggle="tab"]):not([data-bs-toggle="pill"]):not([data-bs-toggle="button"]):not([data-bs-toggle="tooltip"]):not([data-bs-toggle="popover"])';


/**
 * The software's own tooltips, over one scope.
 */
function pgBindTitlePopovers(scope) {

    var $scope = $(scope || document);

    $scope.find('[title]:not(.popover-click):not(.no-popover)' + _pgNoTitlePopover).each(function () {
        pgUI.popover.get(this, {
            trigger: 'hover',
            placement: 'auto',
            html: true,
            container: 'body'
        });
    });

    $scope.find('.popover-click[title]:not(.no-popover)' + _pgNoTitlePopover).each(function () {
        pgUI.popover.get(this, {
            trigger: 'click',
            placement: 'auto',
            html: true,
            container: 'body'
        });
    });
}


/**
 * Set .show on a switcher's plain .collapse section together with its display.
 *
 * Bootstrap 6 hides `.collapse:not(.show)` with display:none and block-size:0
 * outside any layer, so an inline display alone leaves the section 0 px high.
 * A callout (.collapse.popover) keeps its own .show toggle.
 */
function pgCollapseSwitcherShow(target, shown) {
    $(target).filter('.collapse:not(.popover)').toggleClass('show', !!shown);
}


/**
 * Draw every collapse pair in the state its own control is in.
 *
 * The controls themselves are driven by two delegated listeners in the ready
 * block, so this is only about the first paint.
 */
function pgSyncCollapseSwitchers(scope) {

    var $scope = $(scope || document);

    $scope.find('input.collapse-switcher').each(function () {

        var target = $(this).attr('data-bs-target');
        var returned;

        if ($(target).hasClass('show-reverse')) {
            returned = !this.checked;
        } else {
            returned = this.checked;
        }

        $(target).toggle(returned);
        pgCollapseSwitcherShow(target, returned);

        if ($(target).hasClass('popover')) {
            $(target).toggleClass('show');
        }
    });

    $scope.find('select.collapse-if-selected').each(function () {

        var target = $(this).attr('data-bs-target');
        var hide = ($(this).val() === '');

        $(target).each(function () {
            var section = this;

            pgUI.ready(function () {
                pgUI[hide ? 'hide' : 'show']('collapse', section);
            });
        });
    });
}


/**
 * Everything a freshly injected piece of the panel still needs.
 */
// Bootstrap 6 Ranges marked data-pg-range, with the value bubble; the
// attribute's value is the unit after the number ("%").
function pgBindRanges(scope) {
    $(scope).find('.form-range[data-pg-range]').each(function () {
        var unit = this.getAttribute('data-pg-range') || '';
        pgUI.range.bubble(this, function (value) { return value + unit; });
    });
}

function pgInitInjected(scope) {

    var $scope = $(scope);

    if (!$scope.length) {
        return;
    }

    pgBindTitlePopovers($scope);
    pgSyncCollapseSwitchers($scope);
    pgBindRanges($scope);

    if ($.fn.inputmask) {
        $scope.find('.input-mask-key-code').inputmask({
            mask: '9999-9999-9999-9999',
            placeholder: '****-****-****-****',
            showMaskOnHover: false,
            showMaskOnFocus: false
        });
    }

    // Chips, combobox, date and time fields marked with a data-pg-* hook; a
    // card whose library arrives with its markup prints pg_fields_script().
    if (window.pgFields) {
        pgFields.init($scope[0]);
    }
}


/* ────────────────────────────────────────────────────────────────────────────
   THE SETTINGS MODAL

   Site Settings is not a page any more. The gear in the header opens it over
   whatever screen the operator is on, each category's cards arrive from
   settings_pane.php, and saving stays inside the dialog: a setting is nearly
   always something wanted in the middle of another job, and going to a page
   for it means losing the job.

   Openable from anywhere in the panel:

       window.pgOpenSettings()                          the pane last used
       window.pgOpenSettings('security')                a category
       window.pgOpenSettings('firewall', 'pgset-waf')   and a card in it

   In markup the address is the hook, so a breadcrumb, a toolbar button and a
   dashboard widget open it with the link they already had:

       <a href="settings_firewall.php#pgset-waf">...</a>
       <a href="#settings">...</a>                  the category last used
       <a href="#settings/firewall/pgset-waf">...</a>

   The href stays a real address on purpose: it is what the link does on a
   screen where this dialog is not printed, on a middle click and in a new tab.
   A control with no address of its own says the same thing with
   data-pg-settings / data-pg-settings-section instead.
   ──────────────────────────────────────────────────────────────────────────── */
var pgSettingsModal = (function () {

    var STORE = 'pg_settings_last_pane';

    var el = null;              // the dialog
    var modal = null;           // its bootstrap instance
    var form = null;            // the pane's form, which is also its scroller
    var endpoint = '';
    var anchors = {};
    var helps = {};             // category => its section of the documentation

    var pane = '';              // the category on screen
    var dirty = false;
    var baseline = '';          // the whole pane, as it arrived
    var held = null;            // the same, control by control
    var heldSet = null;         // and the membership question, asked often
    var gesture = 0;            // when a person last did something in here
    var gestureControl = null;  // and which control they did it to
    var touched = false;        // and whether that ever changed anything
    var warned = false;         // a fill has been reported on this pane
    var reviewing = 0;

    // The card button that started the save, remembered from its click because
    // nothing else carries it that far.
    var pressed = null;          // the pending comparison
    var patrolling = 0;         // the timer that watches for silent writes
    var busy = 0;               // requests in flight
    var seq = 0;                // the pane request that is allowed to paint
    var leaving = false;        // a close the operator has already agreed to
    var pending = '';           // a card to scroll to once the pane is in

    function byId(id) { return document.getElementById(id); }

    function working() { return busy > 0; }

    // .dialog.pg-sm -- the box the sidebar, the pane and the drawer
    // state all live on.
    function shell() { return el ? el.querySelector('.pg-sm') : null; }

    // Every word the operator reads comes from the server, on the dialog.
    function say(name) { return el ? (el.getAttribute('data-pg' + name) || '') : ''; }


    function ready() {

        el = byId('pg_settings_modal');

        if (!el) {
            return false;
        }

        if (modal) {
            return true;
        }

        form = byId('pg_settings_modal_form');
        endpoint = el.getAttribute('data-pgurl') || '';

        try {
            anchors = JSON.parse(el.getAttribute('data-pganchors') || '{}');
        } catch (error) {
            anchors = {};
        }

        try {
            helps = JSON.parse(el.getAttribute('data-pghelp') || '{}');
        } catch (error) {
            helps = {};
        }

        modal = pgUI.dialog.get(el);

        wire();

        return true;
    }


    /**
     * The option list, on a phone.
     *
     * Below 576px there is no room for the list and a settings form side by
     * side, so the list slides in over the pane. It is the same list in the
     * same place; only its width is borrowed.
     */
    function drawer(open) {

        var box = shell();

        if (!box) {
            return;
        }

        box.classList.toggle('is-nav-open', !!open);

        var button = byId('pg_settings_modal_menu');

        if (button) {
            button.setAttribute('aria-expanded', open ? 'true' : 'false');
        }

        if (open) {

            var row = el.querySelector('.pg-sm-link.is-active') || el.querySelector('.pg-sm-link[data-pane]');

            if (row) {
                row.focus({ preventScroll: true });
            }
        } else if (button && window.matchMedia && window.matchMedia('(max-width: 575.98px)').matches) {

            // The shut list turns visibility:hidden, which would drop the
            // focus of a row inside it; at wider widths the list stays in
            // view and the chosen row keeps the focus.
            var side = byId('pg_settings_modal_side');

            if (side && side.contains(document.activeElement)) {
                button.focus({ preventScroll: true });
            }
        }
    }


    function drawerOpen() {

        var box = shell();

        return !!box && box.classList.contains('is-nav-open');
    }


    function wire() {

        // The chat launcher is fixed at a higher z-index than any modal, so it
        // would sit on top of the save bar. Same class the designer's side
        // panel uses -- one rule for it, not two.
        pgUI.dialog.on(el, 'show', function () {

            document.body.classList.add('pg-side-panel-open');

            // Says WHICH dialog is open, which the class above cannot: the
            // designer's side panel shares it. The stylesheet lightens the
            // scrim behind a frosted settings dialog and must not lighten it
            // behind anything else.
            document.body.classList.add('pg-settings-open');

            window.clearInterval(patrolling);
            patrolling = window.setInterval(patrol, 600);
        });

        pgUI.dialog.on(el, 'hidden', function () {

            document.body.classList.remove('pg-side-panel-open');
            document.body.classList.remove('pg-settings-open');

            window.clearInterval(patrolling);
            patrolling = 0;

            // The pane stays in the document with its editors in it, hidden.
            // Hidden is exactly the state the refresh addon waits out forever.
            window.pgReleaseEditors(byId('pg_settings_modal'));

            clearExtras();
            drawer(false);

            // The next opening asks for the pane again rather than showing
            // values that may have been changed by the work done in between.
            pane = '';
            leaving = false;
            touched = false;
            warned = false;
            baseline = '';
            held = null;
            heldSet = null;
            setDirty(false);
        });

        // Which card button asked for this save, if it was a card button at
        // all. Delegated because the cards arrive by AJAX. The dialog's own
        // Save is type="button" and never lands here, which is what keeps a
        // plain save plain; Return presses the form's first, nameless submit
        // button (modal.php), which clears it. A click whose default was
        // cancelled -- an inline confirm() answered with Cancel -- submits
        // nothing, so it is not remembered for the next save either.
        $(el).on('click', '#pg_settings_modal_form [type="submit"]', function (event) {

            pressed = (!event.isDefaultPrevented() && this.name && !this.disabled)
                ? { name: this.name, value: this.value }
                : null;
        });

        // Closing with unsaved changes is the one thing worth interrupting for.
        pgUI.dialog.on(el, 'hide', function (event) {

            if (!dirty || leaving) {
                return;
            }

            event.preventDefault();

            ask().then(function (agreed) {
                if (agreed) {
                    leaving = true;
                    modal.hide();
                }
            });
        });

        // The sidebar. A category row loads a pane; the rows under them are
        // links and leave, which the arrow on them says.
        $(el).on('click', '.pg-sm-link[data-pane]', function () {
            choose(this.getAttribute('data-pane'));
        });

        $(el).on('click', '#pg_settings_modal_menu', function () {
            drawer(!drawerOpen());
        });

        $(el).on('click', '#pg_settings_modal_scrim', function () {
            drawer(false);
        });

        // On a phone the list is a drawer over the pane: Tab past its last
        // row would land on the covered list button and the fields under the
        // scrim. Focus leaving the list shuts it. A focus with no
        // relatedTarget (a click on a blank spot, a frame) is read on the
        // next turn, once it has settled.
        function sideLostFocus(next) {

            var side = byId('pg_settings_modal_side');

            if (!side || !drawerOpen() || !window.matchMedia || !window.matchMedia('(max-width: 575.98px)').matches) {
                return;
            }

            if (next && (next !== document.body) && !side.contains(next)) {
                drawer(false);
            }
        }

        el.addEventListener('focusout', function (event) {

            var side = byId('pg_settings_modal_side');

            if (!side || !side.contains(event.target)) {
                return;
            }

            if (event.relatedTarget) {
                sideLostFocus(event.relatedTarget);
            } else {
                window.setTimeout(function () {
                    sideLostFocus(document.activeElement);
                }, 0);
            }
        });

        // Escape closes the drawer before it closes the dialog. Bound to the
        // document in the capture phase because Bootstrap's own Escape handler
        // sits on the dialog element and was registered first.
        document.addEventListener('keydown', function (event) {

            if ((event.key !== 'Escape') || !pgUI.dialog.isOpen(el) || !drawerOpen()) {
                return;
            }

            // A native <dialog> closes on Escape as the key's default
            // action, which stopping the event alone does not cancel.
            event.preventDefault();
            event.stopPropagation();
            drawer(false);

        }, true);

        $(el).on('click', '#pg_settings_modal_save', function () {
            save();
        });

        // A dialog a card brings with it opens ON TOP of the settings, not
        // instead of them. Bootstrap's own data-api would close the settings
        // first and cannot be headed off -- its delegated listeners sit on the
        // document in the capture phase -- so the server hands us the trigger
        // under a name of our own; see pg_settings_own_dialog_triggers().
        $(el).on('click', '#pg_settings_modal_pane [data-pgdialog="modal"]', function (event) {

            var target = document.querySelector(
                this.getAttribute('data-bs-target') || this.getAttribute('href') || '');

            if (!target) {
                return;
            }

            event.preventDefault();


            pgUI.dialog.show(target);
        });

        // When a person last did something. Bound to the document and in the
        // capture phase, not to the dialog: a card's own sub-dialog sits in the
        // box BESIDE the dialog, and the file picker it opens is somewhere else
        // again -- a press in either of those is still the operator working on
        // this pane.
        //
        // The time is all that is kept. Which control was pressed says nothing
        // useful: the operator presses "Generate" and the value appears in a
        // field they never touched. review() decides what the change was worth
        // by comparing the pane against what it arrived with.
        //
        // And a gesture asks for a review of its own, because a script that
        // writes into a field raises no event -- without this the generated key
        // would leave Save disabled, and the next review would put it back as
        // though nobody had asked for it.
        document.addEventListener('pointerdown', function (event) {
            unlatch(event.target);
            gesture = Date.now();
            gestureControl = controlOf(event.target);
            review();
        }, true);

        document.addEventListener('keydown', function (event) {
            unlatch(event.target);
            gesture = Date.now();
            gestureControl = controlOf(event.target);
            review();
        }, true);

        // Reaching a field with the keyboard is reaching for it too, and the
        // release has to happen before the plugins that bind on focus -- the
        // input masks and the time picker -- get a look at it.
        document.addEventListener('focusin', function (event) {
            unlatch(event.target);

            // Leaving a control ends the operator's claim on it. The claim
            // exists so that a field being worked in is not overruled while
            // its dropdown is open; once they are somewhere else, that field
            // is as unattended as any other and goes back under guard.
            if (controlOf(event.target) !== gestureControl) {
                gestureControl = null;
            }
        }, true);

        $(el).on('paste drop', function () {
            gesture = Date.now();
            gestureControl = controlOf(document.activeElement);
            review();
        });

        // Something in the pane changed. Whether that counts is review()'s
        // question; a code field raises input on its textarea the same way.
        $('#pg_settings_modal_pane').on('change input', review);

        // A field a refused save marked loses the mark once it is edited.
        $('#pg_settings_modal_pane').on('change input', '[data-pg-sm-marked]', function () {
            this.removeAttribute('data-pg-sm-marked');
            window.pgFieldInvalid(this, false);
        });

        // Return in a text field submits a form. There is nowhere for this one
        // to go, so it saves instead. Only a submit carries the card button
        // that started it: the button is taken here and handed over, so a
        // remembered button never rides along with a later Save or Ctrl+S.
        $(form).on('submit', function (event) {
            event.preventDefault();

            var button = pressed;

            pressed = null;
            save(button);
        });

        el.addEventListener('keydown', function (event) {

            if ((event.ctrlKey || event.metaKey) && ((event.key === 's') || (event.key === 'S'))) {
                event.preventDefault();
                save();
            }
        });
    }


    /**
     * The unsaved-changes question, asked the way the software asks questions.
     */
    function ask() {

        if (typeof window.pgConfirm === 'function') {

            return window.pgConfirm({
                title: say('leavetitle'),
                message: say('leave'),
                confirmText: say('discard'),
                cancelText: say('stay'),
                variant: 'warning'
            });
        }

        return Promise.resolve(window.confirm(say('leave')));
    }


    function setDirty(state) {

        dirty = !!state;

        var button = byId('pg_settings_modal_save');

        if (button) {
            button.disabled = !dirty || working();
        }
    }


    /**
     * The pane's whole value as one string. A code field writes every edit
     * into its textarea, so the form holds it.
     */
    function snapshot() {

        if (!form) {
            return '';
        }

        return $(form).serialize();
    }


    /**
     * One control's value, whatever kind of control it is.
     */
    function valueOf(control) {

        if ((control.type === 'checkbox') || (control.type === 'radio')) {
            return control.checked ? '1' : '';
        }

        return String(control.value);
    }


    /**
     * The pane control by control, so a single field can be put back.
     *
     * Only what a fill can reach: a control that is not on screen cannot be
     * filled by anything but us. That leaves out the tokens, the tag fields'
     * hidden inputs and the textarea a code editor writes into -- all three are
     * written by this software after the reading below, and putting them back
     * would be fighting ourselves.
     */
    /**
     * The form control a gesture landed on, if it landed on one. A click on a
     * label or on the arrow inside a field is a click on the field.
     */
    function controlOf(target) {

        if (!target || !target.closest) {
            return null;
        }

        var control = target.closest('input, select, textarea');

        if (control) {
            return control;
        }

        var label = target.closest('label');

        return (label && label.control) ? label.control : null;
    }


    function hold() {

        held = [];
        heldSet = new Set();

        if (!form) {
            return;
        }

        $(form).find(':input').each(function () {
            keep(this);
        });
    }


    /**
     * Take one control under watch, at whatever it is showing now.
     *
     * Called again on every review for the controls that come on screen later
     * -- inside a block the operator has since opened.
     *
     * A shut block used to be left out of this on the grounds that nothing
     * could reach it. That is not true of a password manager: it fills by
     * markup and pays no attention to what is on screen, and every credential
     * in the settings sits inside a block that starts shut -- the payment
     * gateways, the couriers, the accounting connections. Filled while shut,
     * the value was then adopted as the one to keep the moment the operator
     * opened the block, and saved from there. (Reported as an e-document
     * provider's user name coming back reading "admin", which is the
     * operator's own panel login: the pair of boxes looked like a login form.)
     *
     * So a shut INPUT or SELECT is watched and latched from the moment the
     * pane arrives, which is the only moment its value is still the server's.
     * A shut TEXTAREA is still left alone: nothing fills one, and the code
     * fields are written into by the editor this software puts over them --
     * watching those would be fighting ourselves.
     */
    function keep(control) {

        if (!held || (control.type === 'hidden') || heldSet.has(control)) {
            return;
        }

        if ((control.offsetParent === null) && (control.tagName === 'TEXTAREA')) {
            return;
        }

        heldSet.add(control);
        held.push([control, valueOf(control)]);

        latch(control);
    }


    /**
     * Hold a text field shut until somebody reaches for it.
     *
     * Autofill and every password manager worth the name skip a read-only
     * field, and there is nothing in the settings that anyone should ever have
     * filled in for them -- no name, no address, no card, no password of the
     * operator's own. This is the half of the defence that stops the value
     * appearing at all; sweep() is the half that catches what gets through.
     *
     * Set from here and never from the server: if this script does not run, the
     * field was never shut and the operator can type in it as always. The other
     * way round -- shut in the markup, opened by script -- would lock the form
     * the day the script breaks.
     *
     * Only the kinds a fill can reach. A checkbox, a radio or a select ignores
     * readonly anyway, and a field the screen itself put in that state keeps
     * it: the mark says who shut it.
     */
    function latch(control) {

        if ((control.tagName !== 'INPUT') && (control.tagName !== 'TEXTAREA')) {
            return;
        }

        if (control.readOnly || control.disabled) {
            return;
        }

        if ((control.tagName === 'INPUT')
            && ('|checkbox|radio|file|color|range|button|submit|reset|image|'.indexOf('|' + control.type + '|') >= 0)) {
            return;
        }

        control.readOnly = true;
        control.setAttribute('data-pgro', '');
    }


    /**
     * And open it, at the first sign of a person.
     */
    function unlatch(target) {

        var control = (target && target.closest) ? target.closest('[data-pgro]') : null;

        if (!control) {
            return;
        }

        control.readOnly = false;
        control.removeAttribute('data-pgro');
    }


    /**
     * Is there anything here the operator would mind losing?
     *
     * Two answers have to agree, because one is not enough. A browser's
     * autofill and a password manager both write into the pane shortly after
     * it is painted, and both raise the same TRUSTED input and change events a
     * keystroke raises -- the event cannot say who caused it. Listening to the
     * event alone brought the pane up already unsaved, so choosing another
     * category or closing the dialog asked a question nobody had caused.
     *
     * So the pane is asked instead: has a person touched this dialog, and does
     * it still hold what it arrived with? A fill nobody asked for fails the
     * first; something typed and then taken back fails the second.
     *
     * Debounced because the comparison serialises the whole form, and a code
     * field can hold a lot of text.
     */
    /**
     * Put one control back to the value it is being held at.
     */
    function putBack(entry) {

        if ((entry[0].type === 'checkbox') || (entry[0].type === 'radio')) {
            entry[0].checked = (entry[1] === '1');
        } else {
            entry[0].value = entry[1];
        }
    }


    /**
     * Walk the pane once: adopt what the operator did, put back what they did
     * not.
     *
     * Did a person cause this? Nothing in the change itself says so, but the
     * clock does: a fill arrives on its own, seconds after the pane was painted
     * and with nobody having touched anything. A fill the operator asked for --
     * clicking the manager's own icon inside the field -- lands inside the
     * window and counts as theirs, which is right: they asked for it. So do the
     * buttons a card publishes ("Generate", the image picker), which write into
     * a field a moment after the click that ran them.
     *
     * @return number how many were put back
     */
    function sweep() {

        if (!held) {
            return 0;
        }

        var mine = (Date.now() - gesture) < 1200;
        var putback = 0;

        for (var i = 0; i < held.length; i++) {

            var control = held[i][0];

            if (!control.form || (valueOf(control) === held[i][1])) {
                continue;
            }

            // The clock is not the whole answer. A native <select> the
            // operator opens and reads through -- the hour list on the
            // overdue card runs to twenty-four entries -- sends the page
            // nothing between the click that opened it and the change that
            // closes it, so a browse of more than a second looked like a fill
            // and the choice was put back under the operator's eyes. A
            // control they are working in is theirs however long they take:
            // the one the last gesture landed on, or the one that has focus.
            // A fill into some other field, which is the whole point of this
            // machinery, still has nobody's hand on it.
            if (mine || (control === gestureControl) || (control === document.activeElement)) {
                held[i][1] = valueOf(control);
                touched = true;
                continue;
            }

            // Every time, however many times. Giving up after a few goes was
            // tried and it reads as the fault itself: the operator watches the
            // wrong value settle into the field and stay there. A manager
            // stubborn enough to write it back every second is a manager the
            // operator needs to see being refused.
            held[i][2] = (held[i][2] || 0) + 1;

            putBack(held[i]);
            putback++;
        }

        return putback;
    }


    /**
     * Is there anything here the operator would mind losing?
     *
     * Two answers have to agree, because one is not enough. A browser's
     * autofill and a password manager both write into the pane shortly after
     * it is painted, and both raise the same TRUSTED input and change events a
     * keystroke raises -- the event cannot say who caused it. Listening to the
     * event alone brought the pane up already unsaved, so choosing another
     * category or closing the dialog asked a question nobody had caused.
     *
     * So the pane is asked instead: has a person touched this dialog, and does
     * it still hold what it arrived with? A fill nobody asked for fails the
     * first; something typed and then taken back fails the second.
     *
     * Debounced because the comparison serialises the whole form, and a code
     * field can hold a lot of text.
     */
    function review() {

        window.clearTimeout(reviewing);

        reviewing = window.setTimeout(function () {

            if (!held) {
                return;
            }

            if (sweep() && !warned) {
                warned = true;
                notice(say('filled'), true);
            }

            $(form).find(':input').each(function () {
                keep(this);
            });

            setDirty(touched && (snapshot() !== baseline));

        }, 120);
    }


    /**
     * Watch the pane on a timer, because the worst offender is silent.
     *
     * Kaspersky's password manager writes the value straight onto the control
     * and raises nothing at all -- no input, no change -- so every listener in
     * here sleeps through it. That was the whole of the reported fault: the
     * support address came back reading "admin" and the dialog never knew.
     *
     * Cheap on purpose: the roll call is a string compare per visible control,
     * and the expensive half (pushing the code fields down and serialising the
     * form) only runs on the tick that actually found something. While the
     * operator is working the timer stands down -- their own events are already
     * driving review().
     */
    function patrol() {

        if (!held || ((Date.now() - gesture) < 1200)) {
            return;
        }

        if (!sweep()) {
            return;
        }

        if (!warned) {
            warned = true;
            notice(say('filled'), true);
        }

        setDirty(touched && (snapshot() !== baseline));
    }


    /**
     * Let go of the dialogs the pane on screen brought with it.
     *
     * Disposed rather than dropped: an instance whose element is torn out from
     * under it leaves its backdrop on the page, over a screen with nothing to
     * close it.
     */
    function clearExtras() {

        // The code fields of a card's own dialog (the cron commands) go with it.
        window.pgReleaseEditors(byId('pg_settings_modal_extras'));

        $('#pg_settings_modal_extras .dialog').each(function () {

            pgUI.dialog.dispose(this);
        });

        $('#pg_settings_modal_extras').empty();
    }


    function notice(said, bad) {

        var box = byId('pg_settings_modal_notice');

        if (!box) {
            return;
        }

        if (!said) {
            box.innerHTML = '';
            return;
        }

        // No py-* here. .alert-dismissible parks the close button absolutely at
        // the top right with a padding of its own, so a shortened alert cannot
        // contain it and the button hangs out of the box.
        var alert = $('<div class="alert pg-alert-dismissible show mb-5" role="alert"></div>')
            .addClass(bad ? 'theme-danger' : 'theme-success')
            .text(said);

        $('<button type="button" class="btn-close no-popover" data-bs-dismiss="alert"></button>')
            .attr('aria-label', say('close'))
            .appendTo(alert);

        $(box).empty().append(alert);
    }


    /**
     * The control a refused save named: by its name, as a list field
     * (name[]), or the first control of a nested list (languages[tr][prefix]
     * for languages). A hidden twin of a checkbox is never the one marked.
     */
    function markable(name) {

        var box = byId('pg_settings_modal_pane');

        if (!box || !name) {
            return null;
        }

        var esc = (window.CSS && CSS.escape) ? CSS.escape : function (text) { return String(text).replace(/["\\]/g, '\\$&'); };
        var found = box.querySelectorAll('[name="' + esc(name) + '"], [name="' + esc(name + '[]') + '"], [name^="' + esc(name + '[') + '"]');

        for (var i = 0; i < found.length; i++) {

            if (found[i].type !== 'hidden') {
                return found[i];
            }
        }

        return null;
    }


    /**
     * Takes back the marks of the last refused save.
     */
    function unmark() {

        $('#pg_settings_modal_pane [data-pg-sm-marked]').each(function () {
            this.removeAttribute('data-pg-sm-marked');
            window.pgFieldInvalid(this, false);
        });
    }


    /**
     * A save the server refused names the fields it marked, each with its
     * sentence. They are marked here with the sentence under them, the notice
     * at the top of the scrolling form comes into view -- the footer Save is
     * pressed from anywhere in the pane -- and the first marked field that
     * can take focus gets it, so the operator lands on what to fix. Focus
     * opens a latched field the way a click does.
     */
    function refused(fields) {

        var first = null;

        unmark();

        Object.keys(fields).forEach(function (name) {

            var control = markable(name);

            if (!control) {
                return;
            }

            control.setAttribute('data-pg-sm-marked', '');
            window.pgFieldInvalid(control, true, fields[name]);

            if (!first && !control.disabled && control.getClientRects().length) {
                first = control;
            }
        });

        form.scrollTop = 0;

        if (first) {
            first.focus();
        }
    }


    /**
     * What went wrong, in the operator's language where the server said so.
     */
    function complaint(xhr) {

        if (xhr && xhr.responseJSON && xhr.responseJSON.message) {
            return xhr.responseJSON.message;
        }

        return say('failed');
    }


    /**
     * Scroll one card into view inside the pane.
     *
     * By arithmetic on the scroller rather than scrollIntoView(): that also
     * scrolls every ancestor, and the ancestor here is the page behind the
     * dialog.
     */
    function jump(section) {

        if (!section || !form) {
            return;
        }

        // After the frame the pane was injected in: a code field or an image
        // inside a card changes its own height as it starts, and an offset
        // measured before that lands somewhere else.
        window.requestAnimationFrame(function () {

            var card = byId(section);

            if (!card) {
                return;
            }

            form.scrollTop += card.getBoundingClientRect().top - form.getBoundingClientRect().top - 8;
        });
    }


    /* ── loading a pane ──────────────────────────────────────────────────── */

    function choose(key) {

        if (!key || (key === pane)) {
            drawer(false);
            return;
        }

        if (!dirty) {
            drawer(false);
            load(key);
            return;
        }

        ask().then(function (agreed) {
            if (agreed) {
                drawer(false);
                load(key);
            }
        });
    }


    function load(key) {

        // Tapping a second category before the first arrives is a normal
        // thing to do on a slow link. Both requests run, but only the newest
        // one is allowed to paint -- otherwise the pane can end up showing a
        // category the sidebar is no longer pointing at.
        var mine = ++seq;
        var later = false;

        var settle = function () {
            $(form).removeClass('is-loading');
            $(shell()).removeClass('is-busy');
            setDirty(false);
        };

        busy++;
        touched = false;
        warned = false;
        baseline = '';
        held = null;
        heldSet = null;
        window.clearTimeout(reviewing);
        setDirty(false);
        $(form).addClass('is-loading');
        $(shell()).addClass('is-busy');

        return $.ajax({
            url: endpoint,
            data: { pane: key },
            dataType: 'json'

        }).done(function (response) {

            if (mine !== seq) {
                return;
            }

            if (!response || (response.status !== 'success')) {
                notice((response && response.message) ? response.message : say('failed'), true);
                return;
            }

            // A pane with a code field is painted once the code editor has
            // loaded; the dialog stays busy until then.
            later = window.pgCodeEditorReady(String(response.html || '') + String(response.modals || '') + String(response.scripts || ''), function () {

                if (mine !== seq) {
                    return;
                }

                paint(key, response);

                if (later) {
                    settle();
                }
            });

        }).fail(function (xhr) {

            if (mine !== seq) {
                return;
            }

            notice(complaint(xhr), true);
            expired(xhr);

        }).always(function () {

            busy--;

            if ((mine !== seq) || later) {
                return;
            }

            settle();
        });
    }


// Lets go of the code editors inside a region that is about to be thrown
// away (window.pgCodeEditors, filled by pg_code_editor_attach() and the screens
// that build their own). An editor view keeps listeners on the document and an
// observer on its box until it is told to go, so markup that owns one cannot
// simply be dropped -- the same rule as for Chart.js.
window.pgReleaseEditors = function (scope) {

    var root = scope || document;

    if (!root || !root.contains) {
        return;
    }

    var editors = window.pgCodeEditors || {};

    Object.keys(editors).forEach(function (id) {

        var instance = editors[id];

        if (!instance || !instance.host || ((root !== document) && !root.contains(instance.host))) {
            return;
        }

        try { instance.destroy(); } catch (e) {}

        delete editors[id];
    });
};

// Runs `run` once the code editor facade (assets/js/pg_code_editor.js,
// window.PgCodeEditor) is on the page, fetching it the first time.
//
// The panel header names the facade and its engine in window.pgCodeEditorConfig
// (output_control_panel_header_includes()); a screen that draws a code field
// adds the engine's translated texts to it (pg_code_editor_includes()). The
// facade itself is a small script, but most screens never draw a code field,
// so it is not in the head: pg_code_editor_attach() asks for it here, and so
// does markup that arrives later, such as a pane of the settings dialog.
//
// `run` also runs when the script cannot be fetched; it checks for
// PgCodeEditor itself and leaves the plain textarea in place.
window.pgCodeEditorUse = (function () {

    var waiting = null;     // callbacks while the facade loads; null otherwise

    var configure = function () {
        if (window.PgCodeEditor && window.pgCodeEditorConfig) {
            window.PgCodeEditor.configure(window.pgCodeEditorConfig);
        }
    };

    return function (run) {

        var config = window.pgCodeEditorConfig || {};

        if (window.PgCodeEditor || !config.facade) {
            configure();
            run();
            return;
        }

        if (waiting) {
            waiting.push(run);
            return;
        }

        waiting = [run];

        var script = document.createElement('script');

        script.src = config.facade;
        script.onload = script.onerror = function () {
            var callbacks = waiting;

            waiting = null;
            configure();
            callbacks.forEach(function (callback) {
                try { callback(); } catch (e) {}
            });
        };

        document.head.appendChild(script);
    };
})();

// Makes sure the code editor is on the page before markup that draws a code
// field is put into it, then calls done.
//
// Markup that arrives later -- a pane of the settings dialog, whose inline
// scripts call pgCodeEditorUse() as .html() runs them -- waits here for the
// facade and its engine, so the pane is painted with its editors rather than
// with plain boxes that turn into editors a moment later. Anywhere else done
// runs at once, before this returns.
//
// Returns true when done will run later. A file that fails to load still ends
// in done: the textarea stays a plain field.
window.pgCodeEditorReady = function (markup, done) {

    if ((String(markup || '').indexOf('pgCodeEditorUse') === -1)
        || (window.PgCodeEditor && window.PgCodeEditor.isLoaded())) {
        done();
        return false;
    }

    window.pgCodeEditorUse(function () {
        if (window.PgCodeEditor) {
            window.PgCodeEditor.load().then(done, done);
        } else {
            done();
        }
    });

    return true;
};
    function paint(key, response) {

        pane = key;

        byId('pg_settings_modal_title').textContent = response.title || '';
        byId('pg_settings_modal_desc').textContent = response.description || '';

        // The help in the header follows the category: each one is a section
        // of the Site Settings page of the documentation.
        var help = byId('pg_settings_modal_help');

        if (help) {
            if (helps[key]) {
                help.href = helps[key];
                help.classList.remove('d-none');
            } else {
                help.classList.add('d-none');
            }
        }
        // innerHTML: the helper returns markup -- a <time> element carrying the
        // exact moment as its title, and the operator's name, which it escapes.
        byId('pg_settings_modal_meta').innerHTML = response.meta || '';
        byId('pg_settings_modal_key').value = key;
        byId('pg_settings_modal_token').value = response.token || '';

        // What a tool left behind on its way here: the cache purge, the
        // clean-up and the updater all finish on a screen of their own and
        // send the operator back with something to read. The endpoint hands it
        // over once, on the first pane the dialog asks for.
        if (response.errors && response.errors.length) {
            notice(response.errors.join(' '), true);
        } else {
            notice((response.notices && response.notices.length) ? response.notices.join(' ') : '');
        }

        // A dialog the last pane brought with it goes when the pane does.
        clearExtras();

        var $pane = $('#pg_settings_modal_pane');

        // The editors the last pane started go before its markup does; the
        // addon that keeps them refreshed outlives the element otherwise.
        window.pgReleaseEditors($pane[0]);

        // .html(), not innerHTML: the cards carry inline scripts that start
        // the code editors and the tag fields, and .html() runs them.
        $pane.html(response.html || '');

        // A card's own dialog cannot stay inside this one -- nested modals
        // share the outer backdrop and are drawn behind it.
        if (response.modals) {
            $('#pg_settings_modal_extras').html(response.modals);
        }

        // The scripts a card publishes separately. Appended into the pane, not
        // into a detached element: jQuery only runs an injected script once the
        // node it lives in is in the document.
        if (response.scripts) {
            $pane.append(response.scripts);
        }

        pgInitInjected($pane);

        // What the pane arrived holding, whole and control by control. Read
        // here and not a frame later: a fill that lands in between would become
        // the baseline, and then the next save would store it without anybody
        // having chosen it.
        touched = false;
        warned = false;
        baseline = snapshot();
        hold();
        setDirty(false);

        $(el).find('.pg-sm-link[data-pane]').each(function () {
            this.classList.toggle('is-active', this.getAttribute('data-pane') === key);
        });

        try {
            window.localStorage.setItem(STORE, key);
        } catch (error) {
            // A private window has no store; the dialog then opens on the
            // first category, which is not worth reporting.
        }

        if (pending) {
            jump(pending);
            pending = '';
        } else {
            form.scrollTop = 0;
        }

        // The pane the operator was reading has just been replaced, so focus
        // is on an element that no longer exists and has fallen to the body.
        // Putting it on the new pane is where a reader has to start anyway.
        var box = byId('pg_settings_modal_pane');

        if (box && el.contains(document.activeElement) === false) {
            box.focus({ preventScroll: true });
        }
    }


    /* ── saving ──────────────────────────────────────────────────────────── */

    /**
     * Everything that has to be true of the form before it goes to the server.
     * Shared by the save and by a card's action button, which posts the same
     * fields -- an action usually acts on what is typed in them.
     */
    function harden() {

        // Anything a password manager kept writing back after being put back
        // three times is still on screen, and this is the last moment it can be
        // stopped: it is not the operator's value and it must not become the
        // site's. Their own edits were adopted as they were made, so they are
        // what is being held by now and nothing here touches them.
        if (held) {

            for (var g = 0; g < held.length; g++) {

                if (held[g][2] && held[g][0].form && (valueOf(held[g][0]) !== held[g][1])) {
                    putBack(held[g]);
                }
            }
        }
    }


    /**
     * @param {?{name: string, value: string}} [button] the card button that
     *     submitted the form (the submit handler); none for the dialog's Save
     *     and Ctrl+S
     */
    function save(button) {

        if (!pane || working()) {
            return;
        }

        harden();

        // The card button that started this, by name. A serializer never
        // includes a submit button -- one is only a successful control when it
        // submitted the form, and by then serialize() is looking at the form,
        // not at the click -- so the name is remembered on the way in and put
        // back here. Without it "Update Bot IP Lists" saved the firewall
        // settings and fetched nothing, and said "saved" as though it had:
        // the save module asks for post_value('waf_refresh_ranges') and it was
        // never in the body. On a page the browser had always sent it.
        button = button || null;

        busy++;
        setDirty(dirty);
        $(shell()).addClass('is-busy');

        $.ajax({
            url: endpoint,
            method: 'POST',
            data: $(form).serialize()
                + (button ? ('&' + encodeURIComponent(button.name) + '=' + encodeURIComponent(button.value)) : ''),
            dataType: 'json'

        }).done(function (response) {

            // Refused, not broken: the save marked a field rather than writing
            // it. Nothing was stored, so the dialog stays dirty and the pane
            // stays exactly as the operator left it -- what they typed is what
            // they need in front of them to fix it.
            if (response && (response.status === 'invalid')) {

                byId('pg_settings_modal_token').value = response.token || '';

                notice((response.errors && response.errors.length)
                    ? response.errors.join(' ')
                    : say('failed'), true);

                refused(response.fields || {});

                return;
            }

            if (!response || (response.status !== 'success')) {
                notice((response && response.message) ? response.message : say('failed'), true);
                return;
            }

            byId('pg_settings_modal_token').value = response.token || '';

            // Secure Mode was turned on by this save: the panel is at another
            // scheme now and every later request from this page would go to
            // the old one.
            if (response.reload) {
                dirty = false;
                window.location.href = response.reload;
                return;
            }

            var said = (response.notices && response.notices.length)
                ? response.notices.join(' ')
                : '';

            setDirty(false);

            // Read the pane back rather than trust the form: a save normalises
            // what it was given -- a list becomes comma separated, a limit of
            // zero becomes one -- and the operator should see what was stored.
            load(pane).always(function () {
                notice(said);
            });

        }).fail(function (xhr) {

            notice(complaint(xhr), true);
            expired(xhr);

        }).always(function () {

            busy--;
            $(shell()).removeClass('is-busy');
            setDirty(dirty);
        });
    }


    /**
     * The session is gone: nothing here can be saved until the operator signs
     * in again, and the screen behind the dialog is just as stale. The message
     * is left up long enough to be read.
     */
    function expired(xhr) {

        if (xhr && xhr.responseJSON && xhr.responseJSON.expired) {
            window.setTimeout(function () { window.location.reload(); }, 3000);
        }
    }


    /* ── opening ─────────────────────────────────────────────────────────── */

    function open(key, section) {

        if (!ready()) {
            return;
        }

        if (section) {
            pending = section;
        }

        key = String(key || '');

        if (!/^[a-z_]+$/.test(key)) {
            key = '';
        }

        if (!key) {

            try {
                key = String(window.localStorage.getItem(STORE) || '');
            } catch (error) {
                key = '';
            }

            // A remembered category that is no longer in the sidebar (renamed,
            // or not visible to this role) must not be asked for.
            if (!/^[a-z_]+$/.test(key) || !el.querySelector('.pg-sm-link[data-pane="' + key + '"]')) {
                key = '';
            }
        }

        if (!key) {
            var first = el.querySelector('.pg-sm-link[data-pane]');
            key = first ? first.getAttribute('data-pane') : '';
        }

        modal.show();

        if (key && (key !== pane)) {
            choose(key);

        } else if (pending) {
            jump(pending);
            pending = '';
        }
    }


    /**
     * Is this a category the sidebar actually offers?
     *
     * Every category read out of an address is checked here, so a file whose
     * name only looks like a settings screen leaves its link alone instead of
     * opening an empty dialog.
     */
    function known(key) {

        return !!key
            && /^[a-z_]+$/.test(key)
            && !!el
            && !!el.querySelector('.pg-sm-link[data-pane="' + key + '"]');
    }


    /**
     * What an address is asking for.
     *
     * Breadcrumbs, toolbars and dashboard widgets are drawn by helpers that
     * emit a plain <a href> and pass no attributes through, so the address
     * itself has to be the hook. The href stays a real address: on a screen
     * where the dialog is not printed, and on a middle click, the link still
     * goes somewhere that works.
     *
     * The #pgset- fragment is answered before the file name, because a card
     * that moved to another category has left its old file behind in every
     * link written before the move.
     *
     * @return object|null {pane, section}; an empty pane means the one last used
     */
    function fromUrl(href) {

        if (!ready()) {
            return null;
        }

        href = String(href || '');

        var hit;

        // #settings, #settings/<category>, #settings/<category>/<card>
        hit = href.match(/^#settings(?:\/([a-z_]+)(?:\/([a-z0-9_-]+))?)?$/i);

        if (hit) {
            return { pane: (hit[1] || '').toLowerCase(), section: (hit[2] || '').toLowerCase() };
        }

        // Somebody else's settings.php is somebody else's screen. Only an
        // address with a scheme is asked: everything the panel writes is
        // relative or carries OUTPUT_PATH, which is this origin.
        if (/^[a-z][a-z0-9+.-]*:|^\/\//i.test(href)) {

            var here = String(window.location.origin || '');

            if ((!here) || (href.indexOf(here + '/') !== 0)) {
                return null;
            }
        }

        // A settings screen, the hub, or the single screen they replace. Both
        // spellings of an old fragment: the map carries pgset- cards and
        // pgsub- sub-sections alike.
        hit = href.match(/(?:^|\/)settings(?:2|_([a-z_]+))?\.php(?:\?[^#]*)?(?:#(pg(?:set|sub)-[a-z0-9_-]+))?$/i);

        if (!hit) {
            return null;
        }

        var file = hit[1] ? hit[1].toLowerCase() : '';
        var card = hit[2] ? hit[2].toLowerCase() : '';

        if (card && anchors[card]) {

            var moved = String(anchors[card]).split('|');

            return { pane: moved[0], section: moved[1] || '' };
        }

        if (file) {
            return known(file) ? { pane: file, section: card } : null;
        }

        return { pane: '', section: card };
    }


    /**
     * What a link is asking for.
     *
     * The attributes come first for a control that has no address of its own;
     * everything else is read out of the href.
     *
     * @return object|null {pane, section}; an empty pane means the one last used
     */
    function asked(link) {

        // The main menu's Site Settings links lead to the settings pages on
        // purpose (pg_menu_settings_entries()).
        if (link.hasAttribute('data-pg-settings-page')) {
            return null;
        }

        var key = link.getAttribute('data-pg-settings');

        if (key && ready()) {
            return { pane: key, section: link.getAttribute('data-pg-settings-section') || '' };
        }

        return fromUrl(link.getAttribute('href') || '');
    }


    /**
     * An address that asks for the settings.
     *
     * #settings/<category>[/<card>] is what this dialog answers to; a bare
     * #pgset- fragment is a link written when the settings were one long page,
     * and the map on the dialog says which category holds it now.
     */
    function fromHash(hash) {

        if (!ready()) {
            return false;
        }

        hash = String(hash || '').replace(/^#/, '');

        if (!hash) {
            return false;
        }

        if (hash === 'settings') {

            open('');

            return true;
        }

        if (hash.indexOf('settings/') === 0) {

            var parts = hash.slice(9).split('/');

            open(parts[0], parts[1] || '');

            return true;
        }

        if (anchors[hash]) {

            // A card on the settings page itself is that page's own section,
            // reached from its section links (the main menu's Site Settings
            // entries among them); only an anchor no card on the page answers
            // is an old link meant for the dialog.
            var own = document.getElementById(hash);
            var dialog = document.getElementById('pg_settings_modal');

            if (own && !(dialog && dialog.contains(own))) {
                return false;
            }

            var target = String(anchors[hash]).split('|');

            open(target[0], target[1] || '');

            return true;
        }

        return false;
    }


    return { ready: ready, open: open, fromHash: fromHash, asked: asked, fromUrl: fromUrl };
})();


window.pgOpenSettings = function (pane, section) {
    pgSettingsModal.open(pane, section);
};


/**
 * Go to an address -- unless the settings answer it, in which case they open
 * over this screen and nothing is navigated to at all.
 *
 * For the controls that are not links and so are never seen by the delegated
 * handler below: the back button in the header is a <button> carrying an
 * onclick, and a screen whose cancel points at the settings should open them
 * rather than walk to a page.
 */
window.pgSettingsGo = function (url) {

    var want = pgSettingsModal.fromUrl(url);

    if (want) {
        pgSettingsModal.open(want.pane, want.section);
        return;
    }

    window.location = url;
};


$(function () {

    var button = document.getElementById('pg_settings_open');

    if (button) {
        button.addEventListener('click', function () { pgSettingsModal.open(); });
    }

    if (!document.getElementById('pg_settings_modal')) {
        return;
    }

    // A link that points at a settings screen opens the dialog instead. The
    // address is the hook, so a breadcrumb, a toolbar button and a dashboard
    // widget all open it without carrying anything of ours; their href is left
    // alone so that they still go somewhere on a screen where the dialog is not
    // printed, and on a middle click or a new tab.
    $(document).on('click', 'a[href], [data-pg-settings]', function (event) {

        if ((event.which === 2) || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) {
            return;
        }

        if (this.target && (this.target !== '_self')) {
            return;
        }

        var want = pgSettingsModal.asked(this);

        if (!want) {
            return;
        }

        event.preventDefault();

        pgSettingsModal.open(want.pane, want.section);
    });

    pgSettingsModal.fromHash(window.location.hash);

    $(window).on('hashchange', function () {
        pgSettingsModal.fromHash(window.location.hash);
    });
});

/* ---------------------------------------------------------------------------
 * Sortable tables.
 *
 * A table that carries data-pg-sort gets buttons in its column headings: a
 * click sorts the body by that column, the second click reverses it and the
 * third puts the rows back in the order the server sent them. DataTables
 * tables (table.chart) sort themselves and are not touched here.
 *
 * Shift+click sorts by more than one column: it adds the column as the next
 * level, ascending, and on a column already sorted by turns ascending into
 * descending and descending into gone, leaving the other levels as they
 * are. Each level orders the rows the one before it leaves equal, and once
 * there is more than one, every sorted heading shows its number.
 *
 * A cell sorts by its data-sort value when it has one (a date as Ymd, an
 * amount in minor units), else by data-order, else by its text. A column whose
 * values are all numbers sorts as numbers, anything else as text in the page's
 * language; empty cells go last either way. A heading marked
 * data-pg-sort="none", an empty heading and one that spans columns stay plain.
 *
 * A row marked data-pg-sort-fixed ("nothing here yet", a subtotal) stays at
 * the end - at the top with data-pg-sort-fixed="top", for an opening balance
 * - and a row marked data-pg-sort-with-prev moves with the row above it. The
 * last choice is remembered per page and table for this viewer when the
 * browser lets the page keep it.
 *
 * Lists the server sorts (pg_list_sort_heading()) take Shift+click the same
 * way; their headings are links, and the one Shift+click follows is in
 * data-pg-sort-add.
 * ------------------------------------------------------------------------- */
(function () {
    'use strict';

    var collator = (window.Intl && window.Intl.Collator)
        ? new window.Intl.Collator(document.documentElement.lang || undefined, { numeric: true, sensitivity: 'base' })
        : null;

    function say(key) {
        return (typeof window.pgLang === 'function') ? window.pgLang(key) : key;
    }

    function addStyle() {
        if (document.getElementById('pg_sort_style')) {
            return;
        }

        var style = document.createElement('style');
        style.id = 'pg_sort_style';
        style.textContent = '.pg-sort-btn{background:none;border:0;padding:0;margin:0;color:inherit;font:inherit;'
            + 'text-transform:inherit;letter-spacing:inherit;text-align:inherit;display:inline-flex;align-items:center;'
            + 'gap:.3em;cursor:pointer;white-space:nowrap}'
            + '.pg-sort-btn:focus-visible{outline:2px solid var(--pg-primary);outline-offset:2px;border-radius:2px}'
            + '.pg-sort-btn .pg-sort-icon{opacity:.35;font-size:.8em}'
            + '.pg-sort-btn .pg-sort-rank{font-size:.8em}'
            + 'th[data-pg-sort-level] .pg-sort-btn .pg-sort-icon{opacity:1}'
            + '@media print{.pg-sort-btn .pg-sort-icon,.pg-sort-btn .pg-sort-rank{display:none}}';
        document.head.appendChild(style);
    }

    function storageKey(table) {
        var tables = document.querySelectorAll('table[data-pg-sort]');
        var position = Array.prototype.indexOf.call(tables, table);

        return 'pgSort:' + window.location.pathname + ':' + (table.id || ('t' + position));
    }

    // Levels as text, "3:desc,1:asc": what the table carries in
    // data-pg-sort-state and what is remembered. A single "3:asc" kept
    // before there were levels reads as one level.
    function serialize(levels) {
        return levels.map(function (level) {
            return level.column + ':' + level.direction;
        }).join(',');
    }

    function parse(text) {
        var levels = [];
        var seen = {};

        String(text || '').split(',').forEach(function (pair) {
            var parts = pair.split(':');

            if ((parts.length === 2) && /^\d+$/.test(parts[0]) && ((parts[1] === 'asc') || (parts[1] === 'desc')) && !seen[parts[0]]) {
                seen[parts[0]] = true;
                levels.push({ column: parseInt(parts[0], 10), direction: parts[1] });
            }
        });

        return levels;
    }

    function rankOf(levels, column) {
        for (var i = 0; i < levels.length; i++) {
            if (levels[i].column === column) {
                return i;
            }
        }

        return -1;
    }

    function remember(table, levels) {
        try {
            if (levels.length) {
                window.localStorage.setItem(storageKey(table), serialize(levels));
            } else {
                window.localStorage.removeItem(storageKey(table));
            }
        } catch (error) {
            // Storage refused (private window, blocked site data): the sort
            // still works, it is just not remembered.
        }
    }

    function recalled(table) {
        try {
            return parse(window.localStorage.getItem(storageKey(table)));
        } catch (error) {
            // Nothing kept, or nothing can be.
        }

        return [];
    }

    // The cell under a column, counting the columns a wider cell spans.
    function cellAt(row, column) {
        var position = 0;

        for (var i = 0; i < row.cells.length; i++) {
            var span = row.cells[i].colSpan || 1;

            if ((column >= position) && (column < position + span)) {
                return row.cells[i];
            }

            position += span;
        }

        return null;
    }

    function rawValue(cell) {
        if (!cell) {
            return '';
        }

        if (cell.hasAttribute('data-sort')) {
            return cell.getAttribute('data-sort');
        }

        if (cell.hasAttribute('data-order')) {
            return cell.getAttribute('data-order');
        }

        return (cell.textContent || '').replace(/\s+/g, ' ').trim();
    }

    // The body split into what moves together: a row and the rows riding
    // with it. Fixed rows are set apart and put back at the end.
    function groupsOf(body) {
        var groups = [];
        var fixed = [];
        var top = [];

        Array.prototype.forEach.call(body.rows, function (row) {
            if (row.getAttribute('data-pg-sort-fixed') === 'top') {
                top.push(row);
            } else if (row.hasAttribute('data-pg-sort-fixed')) {
                fixed.push(row);
            } else if (row.hasAttribute('data-pg-sort-with-prev') && groups.length) {
                groups[groups.length - 1].rows.push(row);
            } else {
                groups.push({ lead: row, rows: [row] });
            }
        });

        return { groups: groups, fixed: fixed, top: top };
    }

    // No levels puts the rows back in the order the server sent them.
    function sortBody(body, levels) {
        var split = groupsOf(body);
        var numeric = levels.map(function () {
            return true;
        });

        split.groups.forEach(function (group) {
            group.order = parseInt(group.lead.getAttribute('data-pg-sort-index') || '0', 10);
            group.values = levels.map(function (level, index) {
                var value = rawValue(cellAt(group.lead, level.column));

                if ((value !== '') && !isFinite(Number(value))) {
                    numeric[index] = false;
                }

                return value;
            });
        });

        function compare(a, b, index) {
            var x = a.values[index];
            var y = b.values[index];

            if ((x === '') !== (y === '')) {
                return (x === '') ? 1 : -1;
            }

            var result;

            if (numeric[index]) {
                result = Number(x) - Number(y);
            } else if (collator) {
                result = collator.compare(x, y);
            } else {
                result = (x < y) ? -1 : ((x > y) ? 1 : 0);
            }

            return (levels[index].direction === 'desc') ? -result : result;
        }

        split.groups.sort(function (a, b) {
            for (var i = 0; i < levels.length; i++) {
                var result = compare(a, b, i);

                if (result !== 0) {
                    return result;
                }
            }

            return a.order - b.order;
        });

        var fragment = document.createDocumentFragment();

        split.top.forEach(function (row) {
            fragment.appendChild(row);
        });
        split.groups.forEach(function (group) {
            group.rows.forEach(function (row) {
                fragment.appendChild(row);
            });
        });
        split.fixed.forEach(function (row) {
            fragment.appendChild(row);
        });

        body.appendChild(fragment);
    }

    function show(table, levels) {
        Array.prototype.forEach.call(table.querySelectorAll('th[data-pg-sort-column]'), function (heading) {
            var rank = rankOf(levels, parseInt(heading.getAttribute('data-pg-sort-column'), 10));
            var direction = (rank >= 0) ? levels[rank].direction : '';
            var icon = heading.querySelector('.pg-sort-icon');
            var number = heading.querySelector('.pg-sort-rank');
            var button = heading.querySelector('.pg-sort-btn');

            // aria-sort goes on one heading at a time: the leading level's.
            if (rank === 0) {
                heading.setAttribute('aria-sort', (direction === 'asc') ? 'ascending' : 'descending');
            } else {
                heading.removeAttribute('aria-sort');
            }

            if (direction) {
                heading.setAttribute('data-pg-sort-level', String(rank + 1));
            } else {
                heading.removeAttribute('data-pg-sort-level');
            }

            if (icon) {
                icon.className = 'pg-sort-icon bi ' + (direction ? ((direction === 'asc') ? 'bi-caret-up-fill' : 'bi-caret-down-fill') : 'bi-arrow-down-up');
            }

            if (number) {
                number.className = 'pg-sort-rank bi ' + (((levels.length > 1) && (rank >= 0) && (rank < 9)) ? ('bi-' + (rank + 1) + '-circle-fill') : 'd-none');
            }

            if (button) {
                button.title = direction ? say((direction === 'asc') ? 'Ascending' : 'Descending') : say('Sort (Shift+click adds a level)');
            }
        });
    }

    function apply(table, levels) {
        Array.prototype.forEach.call(table.tBodies, function (body) {
            sortBody(body, levels);
        });

        table.setAttribute('data-pg-sort-state', serialize(levels));
        show(table, levels);
    }

    function prepare(table) {
        if (table.hasAttribute('data-pg-sort-ready') || table.classList.contains('chart') || !table.tHead) {
            return;
        }

        table.setAttribute('data-pg-sort-ready', '1');
        addStyle();

        // The order the server sent, to come back to on the third click.
        Array.prototype.forEach.call(table.tBodies, function (body) {
            groupsOf(body).groups.forEach(function (group, index) {
                group.lead.setAttribute('data-pg-sort-index', String(index));
            });
        });

        var rows = table.tHead.rows;
        var headingRow = rows.length ? rows[rows.length - 1] : null;

        if (!headingRow) {
            return;
        }

        var position = 0;

        Array.prototype.forEach.call(headingRow.cells, function (heading) {
            var column = position;
            var span = heading.colSpan || 1;

            position += span;

            if ((span !== 1) || (heading.getAttribute('data-pg-sort') === 'none')
                || ((heading.textContent || '').trim() === '') || heading.querySelector('input, select, button')) {
                return;
            }

            var button = document.createElement('button');
            var icon = document.createElement('i');
            var number = document.createElement('i');

            button.type = 'button';
            button.className = 'pg-sort-btn';
            button.title = say('Sort (Shift+click adds a level)');

            while (heading.firstChild) {
                button.appendChild(heading.firstChild);
            }

            icon.className = 'pg-sort-icon bi bi-arrow-down-up';
            icon.setAttribute('aria-hidden', 'true');
            number.className = 'pg-sort-rank bi d-none';
            number.setAttribute('aria-hidden', 'true');
            button.appendChild(icon);
            button.appendChild(number);
            heading.appendChild(button);
            heading.setAttribute('data-pg-sort-column', String(column));

            button.addEventListener('click', function (event) {
                var levels = parse(table.getAttribute('data-pg-sort-state'));
                var rank = rankOf(levels, column);

                if (event.shiftKey) {
                    if (rank < 0) {
                        levels.push({ column: column, direction: 'asc' });
                    } else if (levels[rank].direction === 'asc') {
                        levels[rank].direction = 'desc';
                    } else {
                        levels.splice(rank, 1);
                    }
                } else {
                    var current = (rank === 0) ? levels[0].direction : '';
                    var next = (current === 'asc') ? 'desc' : ((current === 'desc') ? '' : 'asc');

                    levels = next ? [{ column: column, direction: next }] : [];
                }

                apply(table, levels);
                remember(table, levels);
            });
        });

        var kept = recalled(table).filter(function (level) {
            return table.querySelector('th[data-pg-sort-column="' + level.column + '"]');
        });

        if (kept.length) {
            apply(table, kept);
        }
    }

    // Shift+click on a heading of a list the server sorts follows the
    // address that keeps the other levels. DataTables' fixed header copies
    // the headings, so this listens on the document, not on each link.
    document.addEventListener('click', function (event) {
        var link = (event.shiftKey && event.target.closest) ? event.target.closest('a[data-pg-sort-add]') : null;

        if (link) {
            event.preventDefault();
            window.location.href = link.getAttribute('data-pg-sort-add');
        }
    });

    // Shift+mousedown would otherwise select the heading's text.
    document.addEventListener('mousedown', function (event) {
        if (event.shiftKey && event.target.closest && event.target.closest('a[data-pg-sort-add], .pg-sort-btn')) {
            event.preventDefault();
        }
    });

    window.pgSortTables = function (root) {
        Array.prototype.forEach.call((root || document).querySelectorAll('table[data-pg-sort]'), prepare);
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () {
            window.pgSortTables(document);
        });
    } else {
        window.pgSortTables(document);
    }
})();

/* -------------------------------------------------------------------------
 * Quick edit on list tables: a cell changed in place, without leaving the
 * list (pg_list_edit_table() and pg_list_edit_cell() in
 * includes/fn/output.php).
 *
 * A cell marked data-pg-edit opens an input on a click, a tap, Enter or F2.
 * Enter or leaving the input saves, Escape puts the cell back as it was, Tab
 * saves and opens the same column on the next row (Shift+Tab: the row
 * before). A choice list saves as soon as something is picked. The value
 * goes to the table's data-pg-edit-url with the session token; the answer
 * redraws the cell from what was stored, and the other cells of the row it
 * names. A refusal, or a request that never got an answer, puts the cell back
 * and says why.
 *
 * Ctrl, Cmd or Shift with the click belongs to the row selection
 * (multiselectCheckbox) and opens nothing, and so does a click that ends a
 * text selection. The input has no name, so it never travels with the form
 * the table sits in, and its keys stay with it: Enter does not submit that
 * form and Ctrl+S saves the cell rather than the page's form.
 * ------------------------------------------------------------------------- */
(function () {
    'use strict';

    function say(key) {
        return (typeof window.pgLang === 'function') ? window.pgLang(key) : key;
    }

    function addStyle() {
        if (document.getElementById('pg_list_edit_style')) {
            return;
        }

        var style = document.createElement('style');
        style.id = 'pg_list_edit_style';
        style.textContent = 'td[data-pg-edit]{cursor:pointer}'
            + 'td[data-pg-edit]:focus-visible{outline:2px solid var(--pg-primary);outline-offset:-2px}'
            + 'td[data-pg-edit]:not([data-pg-editing]):not([data-pg-edit-busy])::after{content:"\\f4cb";font-family:bootstrap-icons;'
            + 'font-style:normal;font-weight:400;font-size:.75rem;line-height:1;margin-inline-start:.35em;vertical-align:.05em;'
            + 'color:var(--pg-fg-2);opacity:0}'
            + 'td[data-pg-edit]:hover::after,td[data-pg-edit]:focus::after{opacity:.9}'
            + '@media (hover:none){td[data-pg-edit]:not([data-pg-editing])::after{opacity:.55}}'
            + 'td[data-pg-editing]{cursor:auto}'
            + 'td[data-pg-editing] .form-control,td[data-pg-editing] select.form-control{min-width:7rem}'
            + 'td.pg-edit-saved{animation:pgEditSaved 1.2s ease-out}'
            + 'td.pg-edit-failed{animation:pgEditFailed 1.6s ease-out}'
            + '@keyframes pgEditSaved{from{background-color:color-mix(in srgb, var(--pg-success) 30%, transparent)}to{background-color:transparent}}'
            + '@keyframes pgEditFailed{from{background-color:color-mix(in srgb, var(--pg-danger) 30%, transparent)}to{background-color:transparent}}'
            + '@media (prefers-reduced-motion:reduce){td.pg-edit-saved,td.pg-edit-failed{animation:none}}'
            + '@media print{td[data-pg-edit]::after{display:none}}';
        document.head.appendChild(style);
    }

    function quote(name) {
        return (window.CSS && window.CSS.escape) ? window.CSS.escape(name) : String(name).replace(/[^\w-]/g, '');
    }

    function columnsOf(table) {
        if (!table.pgEditColumns) {
            try {
                table.pgEditColumns = JSON.parse(table.getAttribute('data-pg-edit-columns') || '{}') || {};
            } catch (error) {
                table.pgEditColumns = {};
            }
        }

        return table.pgEditColumns;
    }

    function tokenFor(table) {
        if (typeof window.software_token === 'string' && window.software_token !== '') {
            return window.software_token;
        }

        var form = table.closest('form');
        var field = (form || document).querySelector('input[name="token"]');

        return field ? field.value : '';
    }

    // The new look of a cell from the answer: content, classes, tip and the
    // value a table sorted in the browser orders it by. An editable cell
    // keeps its hint when the answer has no tip of its own.
    function paint(cell, view) {
        if (typeof view.html === 'string') {
            cell.innerHTML = view.html;
        }

        if (typeof view['class'] === 'string') {
            cell.className = view['class'];
        }

        if (typeof view.title === 'string') {
            if (view.title !== '') {
                cell.setAttribute('title', view.title);
            } else if (!cell.hasAttribute('data-pg-edit')) {
                cell.removeAttribute('title');
            }
        }

        if ((view.sort !== undefined) && (view.sort !== null)) {
            cell.setAttribute('data-sort', String(view.sort));
        }
    }

    function flash(cell, name) {
        cell.classList.remove('pg-edit-saved', 'pg-edit-failed');
        void cell.offsetWidth;
        cell.classList.add(name);

        window.setTimeout(function () {
            cell.classList.remove(name);
        }, 1700);
    }

    // DataTables keeps its own copy of each cell for its search box.
    function refreshSearch(table, row) {
        var $ = window.jQuery;

        if ($ && $.fn && $.fn.dataTable && $.fn.dataTable.isDataTable(table)) {
            try {
                $(table).DataTable().row(row).invalidate('dom');
            } catch (error) {
                // The search sees the old value until the page is reloaded.
            }
        }
    }

    // The same column on the next row (or the one before) that can change;
    // false when there is none (Tab on the last row, Shift+Tab on the first).
    function step(cell, direction) {
        var field = cell.getAttribute('data-pg-edit');
        var row = cell.closest('tr');

        while (row) {
            row = (direction > 0) ? row.nextElementSibling : row.previousElementSibling;

            var next = row ? row.querySelector('td[data-pg-edit="' + quote(field) + '"]') : null;

            if (next && !next.hasAttribute('data-pg-edit-busy')) {
                open(next);
                return true;
            }
        }

        return false;
    }

    function close(state) {
        state.cell.removeAttribute('data-pg-editing');
        state.cell.removeAttribute('data-pg-edit-busy');
        state.cell.removeAttribute('aria-busy');
    }

    function failed(state, message) {
        close(state);
        state.cell.innerHTML = state.html;
        flash(state.cell, 'pg-edit-failed');

        message = message || say('The change could not be saved. Check the connection and try again.');

        if (typeof window.pgToast === 'function') {
            window.pgToast({ message: message, variant: 'danger', delay: 6000 });
        } else {
            window.alert(message);
        }
    }

    function saved(state, data) {
        var cell = state.cell;
        var row = cell.closest('tr');

        close(state);
        paint(cell, data);

        if ((data.value !== undefined) && (data.value !== null)) {
            cell.setAttribute('data-pg-edit-value', String(data.value));
        }

        if (row && data.cells && (typeof data.cells === 'object')) {
            Object.keys(data.cells).forEach(function (name) {
                var other = row.querySelector('[data-pg-cell="' + quote(name) + '"], td[data-pg-edit="' + quote(name) + '"]');

                if (other && (other !== cell)) {
                    paint(other, data.cells[name]);
                }
            });
        }

        flash(cell, 'pg-edit-saved');

        if (row) {
            refreshSearch(state.table, row);
        }
    }

    function send(state, value, shown) {
        var cell = state.cell;
        var body = new FormData();
        var spinner = document.createElement('span');

        // What was typed stays on screen, with a spinner, until the answer.
        cell.removeAttribute('data-pg-editing');
        cell.setAttribute('data-pg-edit-busy', '');
        cell.setAttribute('aria-busy', 'true');
        cell.textContent = shown;
        spinner.className = 'spinner-border spinner-border-sm ms-3';
        spinner.setAttribute('aria-hidden', 'true');
        cell.appendChild(spinner);

        body.append('pg_list_edit', '1');
        body.append('token', tokenFor(state.table));
        body.append('field', cell.getAttribute('data-pg-edit'));
        body.append('id', cell.getAttribute('data-pg-edit-id') || '');
        body.append('value', value);

        window.fetch(state.table.getAttribute('data-pg-edit-url'), {
            method: 'POST',
            body: body,
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json' }
        }).then(function (response) {
            return response.json().then(function (data) {
                return data;
            }, function () {
                return null;
            });
        }).then(function (data) {
            if (data && data.ok) {
                saved(state, data);
            } else {
                failed(state, (data && data.message) ? String(data.message) : '');
            }
        }, function () {
            failed(state, '');
        });
    }

    // direction: 1 or -1 opens the same column on the next or the previous
    // row; refocus puts the keyboard back on the cell (Enter, Ctrl+S), which
    // a click elsewhere must not do.
    function commit(state, direction, refocus) {
        if (state.done) {
            return;
        }

        state.done = true;

        var control = state.control;
        var value = (control.tagName === 'SELECT') ? control.value : control.value.trim();

        if (value === state.value) {
            close(state);
            state.cell.innerHTML = state.html;
        } else {
            send(state, value, (control.tagName === 'SELECT') ? control.options[control.selectedIndex].text : value);
        }

        // With no row to go on to, the cell keeps the focus: its input has
        // just left the page, which would drop the focus to <body>.
        if (direction) {
            if (!step(state.cell, direction)) {
                state.cell.focus();
            }
        } else if (refocus) {
            state.cell.focus();
        }
    }

    function cancel(state) {
        if (state.done) {
            return;
        }

        state.done = true;
        close(state);
        state.cell.innerHTML = state.html;
        state.cell.focus();
    }

    function open(cell) {
        var table = cell.closest('table[data-pg-edit-url]');
        var column = table ? columnsOf(table)[cell.getAttribute('data-pg-edit')] : null;

        if (!column || cell.hasAttribute('data-pg-editing') || cell.hasAttribute('data-pg-edit-busy')) {
            return;
        }

        var value = cell.getAttribute('data-pg-edit-value') || '';
        var control;

        if (column.type === 'select') {
            control = document.createElement('select');
            control.className = 'form-control form-control-sm';

            (column.options || []).forEach(function (pair) {
                var option = document.createElement('option');

                option.value = String(pair[0]);
                option.textContent = String(pair[1]);
                option.selected = (option.value === value);
                control.appendChild(option);
            });
        } else {
            control = document.createElement('input');
            control.type = 'text';
            control.className = 'form-control form-control-sm' + ((column.type === 'text') ? '' : ' text-end');
            control.value = value;
            control.autocomplete = 'off';
            control.setAttribute('enterkeyhint', 'done');

            if (column.type === 'integer') {
                control.inputMode = 'numeric';
            } else if (column.type === 'money') {
                control.inputMode = 'decimal';
            } else if (column.max) {
                control.maxLength = column.max;
            }
        }

        control.setAttribute('aria-label', String(column.label || ''));

        var state = { cell: cell, table: table, control: control, value: value, html: cell.innerHTML, done: false };

        cell.setAttribute('data-pg-editing', '');
        cell.innerHTML = '';
        cell.appendChild(control);
        control.focus();

        if (control.tagName === 'INPUT') {
            control.select();
        } else if (typeof control.showPicker === 'function') {
            try {
                control.showPicker();
            } catch (error) {
                // Not allowed here; the list opens on the next click.
            }
        }

        control.addEventListener('keydown', function (event) {
            // The panel's own shortcuts listen on the document; what is typed
            // here is for this input.
            event.stopPropagation();

            if (event.key === 'Enter') {
                event.preventDefault();
                commit(state, 0, true);
            } else if (event.key === 'Escape') {
                event.preventDefault();
                cancel(state);
            } else if (event.key === 'Tab') {
                event.preventDefault();
                commit(state, event.shiftKey ? -1 : 1);
            } else if ((event.ctrlKey || event.metaKey) && ((event.key === 's') || (event.key === 'S'))) {
                event.preventDefault();
                commit(state, 0, true);
            }
        });

        control.addEventListener('blur', function () {
            commit(state);
        });

        if (control.tagName === 'SELECT') {
            control.addEventListener('change', function () {
                commit(state);
            });
        }
    }

    document.addEventListener('click', function (event) {
        if ((event.button !== 0) || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey || !event.target.closest) {
            return;
        }

        var cell = event.target.closest('td[data-pg-edit]');

        if (!cell || event.target.closest('a, button, input, select, textarea, label')) {
            return;
        }

        if (window.getSelection && (String(window.getSelection()) !== '')) {
            return;
        }

        open(cell);
    });

    document.addEventListener('keydown', function (event) {
        var cell = event.target;

        if (cell && cell.matches && cell.matches('td[data-pg-edit]') && ((event.key === 'Enter') || (event.key === 'F2'))) {
            event.preventDefault();
            open(cell);
        }
    });

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', addStyle);
    } else {
        addStyle();
    }
})();
