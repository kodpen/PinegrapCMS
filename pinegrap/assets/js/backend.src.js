// Lang shim, defined up front so every top level helper in this file can call it
// regardless of where in the file it sits. Re-declared defensively further down.
window.pgLang = window.pgLang || function (key) { return key; };

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

        // CodeMirror temasını buradan güncellemek için bir fonksiyon tetikleyelim
        updateCodeMirrorTheme(resolvedTheme);
    };

    const updateCodeMirrorTheme = (resolvedTheme) => {
        document.querySelectorAll(".CodeMirror").forEach(cmElem => {
            if (cmElem.CodeMirror) {
                cmElem.CodeMirror.setOption("theme", resolvedTheme === "dark" ? "pastel-on-dark" : "default");
            }
        });
    };

    const showActiveTheme = (theme) => {
        document.querySelectorAll("[data-bs-theme-value]").forEach(element => {
            element.classList.remove("active");
        });
        const activeElementId = theme === "auto" ? "theme-auto" : `theme-${theme}`;
        document.getElementById(activeElementId)?.classList.add("active");
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
    if (translate) {
        $string = translate[$string];
    }
    return $string;
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


    //check if it's toolbar
    if ($("body").hasClass('toolbar')) {
        //convert color-scheme light for transparent bg. it fix some bug
        $("html").attr('style', 'color-scheme:light;');
    }

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

    // If there is a help URL, then set help button so it loads help popup when clicked.
    if (typeof help_url !== 'undefined' && help_url) {
        $('#help_link, .green_help_button, .white_help_button').click(function () {
            window.open(help_url, 'help', 'location=1, status=1, scrollbars=1, resizable=1, directories=1, toolbar=1, titlebar=1').focus();
            return false;
        });

        // Otherwise, there is not a help URL, so hide help button.  This should only happen if site
        // is private labeled.
    } else {
        $('#help_link, .green_help_button, .white_help_button').hide();
    }
    $('.software_warning.alert-dismissible,.software_notice.alert-dismissible,.software_error.alert-dismissible').each(function () {
        $(this).addClass('fade');
        $(this).find('button.btn-close').attr('style', '');
    });


    $('a:not(.no-submit),button[type=submit]:not(.no-submit),button[type=button]:not(.no-submit)').on('click', function () {
        var el = $(this);
        var loading_content = el.attr("data-loading-content");
        var confirm_content = el.attr("data-confirm-content");
        var confirm_action = el.attr("data-confirm-action");
        var default_content = el.html();
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
        if (loading_content || confirm_content || autoconfirm == true) {
            if (confirm_content || autoconfirm == true) {
                if (confirmation == true) {
                    // if this is parasut export button, than output page refresh for user to see the changed order results.
                    if (value == 'Export Orders For Parasut') {
                        $('#xlsl_setup_modal').modal('hide');
                        //reload this page to show user exported orders.
                        $('body').prepend('<div id="couldown_for_order_exporting_10000" style="z-index: 999;background: ##e3e3e3ab;position: fixed;left: 0;top: 0;width: 100%;height: 100%;text-align: center;line-height: 100vh;backdrop-filter: blur(4px);font-size: 40px;">' + lang('Please Wait') + '...</div>');
                        $('body').attr('style', 'overflow:hidden');
                        setTimeout(function () {
                            window.location.reload();
                        }, 4000);//4sec wait
                    }

                    if (loading_content) {
                        loading_triggered();

                        //if form need to be send also send it.
                        if (result == true) {
                            document.form.submit();
                        }
                        if (confirm_action && confirm_action == 'history-back') {
                            javascript: history.go(-1);
                        }
                    } else {
                        //if no loading needed pass it.
                        //if form need to be send also send it.
                        if (result == true) {
                            document.form.submit();
                        }
                        if (confirm_action && confirm_action == 'history-back') {
                            javascript: history.go(-1);
                        }
                        return true;
                    }
                } else {
                    //user selected NO
                    return false;
                }
            } else {
                if (loading_content) {
                    loading_triggered();
                }
            }
        }

        function loading_triggered() {
            el.addClass('disabled');
            setTimeout(function () {
                el.html('<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> ' + loading_content);
            }, 0);
            setTimeout(function () {
                el.html(default_content);
                el.removeClass('disabled');
            }, 10000);
            return true;
        }

    });



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
                        event.preventDefault();
                        $('#menu_toggle').click();
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
                    var notifications = $('#notifications');
                    if (notifications.length) {
                        event.preventDefault();
                        // CTRL+Q on toolbar and backend shows notification panel. tested in chrome,edge(95.0+) and firefox.
                        // Timeout is necessary in order to workaround Firefox bug
                        // with Ctrl+Q where it would still open find area,
                        // even though we run preventDefault above.
                        setTimeout(function () {
                            notifications.dropdown('toggle');
                        }, 0);
                    }

                    break;
                case 's':
                    // A "disable_shortcut" class has been added to forms that purely delete
                    // items, because the shortcut is too dangerous in that case.
                    // We could still allow it for those types of forms, but just show
                    // the warning before the form is submitted, however we have not
                    // spent the time to do that, so we will just disable them for now.

                    // Find the form closest to the current focused element.
                    var form = $(document.activeElement).closest('form:not(.disable_shortcut)');
                    if (!form.length) {
                        // If no focused form, fallback to the first safe form
                        form = $('form:not(.disable_shortcut):first');
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

        // Hedef butonun name'i form attribute'undan okunur, yoksa 'submit_save'
        var shortcutName = form.attr('submitshortcut') || 'submit_save';

        // Öncelikle bu name'e sahip butonu bul
        var button = form.find('button[name="' + shortcutName + '"]');

        // Eğer bulunamazsa fallback olarak ilk submit butonunu al
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


    // Default sortable for lists that opt in with the "pg-sortable" class and
    // bring no sortable set-up of their own. The selector is deliberately not
    // ".ui-sortable": jQuery UI puts that class on every container it has made
    // sortable, so selecting it here would find the lists other screens
    // configure themselves (dashboard widgets, product images, menu items) and
    // overwrite their options after the fact.
    $(".pg-sortable").sortable({
        containment: "parent",
        helper: 'clone',
        dropOnEmpty: false,
        delay: 300,
        revert: '300',
        animation: 1350,
        axis: 'y',
        swapThreshold: 1,
        tolerance: 'pointer',
        items: "a",
        zIndex: 9999,
        cursor: "move",
        scroll: false,
        update: function (event, ui) {
        }
    }).disableSelection();

    //classic Tooltips
    var ClassicTooltip = $('[data-bs-toggle="tooltip"]').tooltip({ placement: 'auto' });
    $('[data-bs-toggle="tooltip"]').on('click', function () {
        ClassicTooltip.tooltip('hide');
    });
    //classic popover
    var popover = $('[data-bs-toggle="popover"]:not([ data-bs-custom-class="contextmenu"])').popover({
        placement: 'auto',
        html: true
    });

    //default context menu template.
    var ContextMenuTemplate = [
        '<div class="contextmenu popover shadow-lg backdrop">',
        '<div class="popover-arrow"></div>',
        '<div class="popover-body ">',
        '</div>',
        '</div>'].join('');

    /*
     * Left menu context menus.
     *
     * Bootstrap 5 has no "context" trigger. Any trigger it does not recognise silently
     * falls back to focusin/focusout, so the menu only opened when the anchor actually
     * received DOM focus on mouse press. Windows browsers do focus a link on mouse
     * press, macOS browsers (Safari, Chrome and Firefox alike) never mouse-focus links
     * or buttons - it is a platform convention baked into WebKit and Blink. The result
     * was that on macOS the popover never opened while the native menu was suppressed
     * as well, leaving the item completely dead. Register the popover as "manual" and
     * drive it from a real contextmenu listener instead.
     */
    var contextmenu = $('[data-bs-toggle="context"]').popover({
        trigger: 'manual',
        placement: 'auto',
        html: true,
        template: ContextMenuTemplate,
        sanitize: false
    });

    //we need titles, context will use bs-content only, converting title back
    contextmenu.each(function () {
        $(this).attr('title', $(this).attr('data-bs-original-title'));
    });

    // Bootstrap 5 keeps component instances in its own registry, so the Bootstrap 4 era
    // $el.data('bs.popover') lookup always returns undefined here.
    function getContextPopover(el) {
        if (!el || !window.bootstrap || !window.bootstrap.Popover) {
            return null;
        }
        return window.bootstrap.Popover.getInstance(el);
    }

    // Which trigger currently has its menu on screen, so the same menu is never shown
    // twice - see the comment in openContextMenu() for why that matters.
    var openContextTrigger = null;

    contextmenu.on('hidden.bs.popover', function () {
        if (openContextTrigger === this) {
            openContextTrigger = null;
        }
    });

    function hideContextMenus(except) {
        $('[data-bs-toggle="context"]').each(function () {
            if (except && this === except) {
                return;
            }
            var instance = getContextPopover(this);
            if (instance) {
                instance.hide();
            }
            if (openContextTrigger === this) {
                openContextTrigger = null;
            }
        });
    }

    function openContextMenu($el) {
        var el = $el && $el[0];
        if (!el) {
            return;
        }

        /*
         * Showing an already open popover closes it again. Bootstrap queues this after
         * every show():
         *
         *     if (this._isHovered === false) this._leave()
         *     this._isHovered = false
         *
         * and with a manual trigger _leave() has no active trigger to hold it back, so
         * it schedules hide(). The first show() leaves _isHovered as false, so a second
         * show() while still open walks straight into that branch and the menu closes
         * itself one fade later.
         *
         * A long press raises that second call on its own: our own timer fires at 450ms
         * and Chrome and Firefox on Android raise a native contextmenu event at about
         * 500ms, so both paths opened the same menu. macOS never produced the second
         * event, which is why this only showed up on Android.
         */
        if (openContextTrigger === el) {
            return;
        }

        hideContextMenus(el);

        // The body is whatever data-bs-content holds; Bootstrap already picked it up as
        // the instance content when the popover was constructed, so nothing to set here.
        var instance = getContextPopover(el);
        if (!instance) {
            $el.popover({
                html: true,
                placement: 'auto',
                trigger: 'manual',
                template: ContextMenuTemplate,
                sanitize: false
            });
            instance = getContextPopover(el);
        }

        if (!instance) {
            // Bootstrap bundle missing or replaced: fall back to the jQuery bridge.
            $el.popover('show');
            return;
        }

        openContextTrigger = el;
        instance.show();

        // show() bails out silently on a popover with no content. Bootstrap points
        // aria-describedby at the tip synchronously when it really opened, so use that
        // to avoid latching the guard on a menu that never appeared.
        if (!el.getAttribute('aria-describedby')) {
            openContextTrigger = null;
        }
    }

    // Right click, and the macOS ctrl+click that raises the very same event.
    document.addEventListener('contextmenu', function (e) {
        var target = e.target;
        if (!target || typeof target.closest !== 'function') {
            return;
        }

        var trigger = target.closest('[data-bs-toggle="context"]');
        if (trigger) {
            e.preventDefault();
            openContextMenu($(trigger));
            return;
        }

        // Right clicking inside an open menu keeps it open and shows the native menu.
        if (target.closest('.contextmenu.popover')) {
            return;
        }

        hideContextMenus();

        // Menu entries that have no context menu of their own: suppress the browser
        // menu too, there is nothing meaningful behind it.
        var plainMenuItem = target.closest('#menu a.list-group-item-action');
        if (plainMenuItem && !plainMenuItem.hasAttribute('data-bs-toggle')) {
            e.preventDefault();
        }
    });

    /*
     * Touch devices: long press opens the menu. The previous implementation delegated
     * touchstart through jQuery on document, where browsers register the listener as
     * passive - preventDefault() was ignored there and the link navigated away anyway.
     * Bind natively on the menu container so the gesture can be handled properly.
     */
    var menuElement = document.getElementById('menu');
    if (menuElement) {
        var longPressTimer = null;
        var longPressFired = false;
        var longPressOrigin = null;

        var contextTargetFrom = function (node) {
            if (!node || typeof node.closest !== 'function') {
                return null;
            }
            return node.closest('a.list-group-item-action[data-bs-toggle="context"]');
        };

        var cancelLongPress = function () {
            if (longPressTimer) {
                clearTimeout(longPressTimer);
                longPressTimer = null;
            }
            longPressOrigin = null;
        };

        menuElement.addEventListener('touchstart', function (e) {
            var anchor = contextTargetFrom(e.target);
            if (!anchor) {
                return;
            }

            cancelLongPress();
            longPressFired = false;

            var touch = e.touches && e.touches[0];
            longPressOrigin = touch ? { x: touch.clientX, y: touch.clientY } : { x: 0, y: 0 };

            longPressTimer = setTimeout(function () {
                longPressTimer = null;
                longPressFired = true;
                openContextMenu($(anchor));
            }, 450);
        }, { passive: true });

        menuElement.addEventListener('touchmove', function (e) {
            if (!longPressOrigin) {
                return;
            }
            var touch = e.touches && e.touches[0];
            if (!touch) {
                return;
            }
            // A scroll gesture is not a long press.
            if (Math.abs(touch.clientX - longPressOrigin.x) > 10 || Math.abs(touch.clientY - longPressOrigin.y) > 10) {
                cancelLongPress();
            }
        }, { passive: true });

        menuElement.addEventListener('touchend', cancelLongPress, { passive: true });
        menuElement.addEventListener('touchcancel', function () {
            cancelLongPress();
            longPressFired = false;
        }, { passive: true });

        // Swallow the click the browser synthesises after a long press.
        menuElement.addEventListener('click', function (e) {
            if (!longPressFired) {
                return;
            }
            if (!contextTargetFrom(e.target)) {
                return;
            }
            e.preventDefault();
            e.stopPropagation();
            longPressFired = false;
        }, true);
    }

    // Close context menu when clicking outside
    $(document).on('click', function (e) {
        if (!$(e.target).closest('.popover, [data-bs-toggle="context"]').length) {
            hideContextMenus();
        }
    });

    // Escape closes the menu, matching every other overlay in the software.
    $(document).on('keydown', function (e) {
        if (e.key === 'Escape' || e.key === 'Esc') {
            hideContextMenus();
        }
    });


    /*
     * Menu behaviour has two distinct modes.
     *
     *  - lg and up: the sidebar is a rail that shares the grid with the content. The
     *    toggle expands and collapses it and the choice is remembered in a cookie.
     *  - below lg: the sidebar is an off-canvas drawer that slides over the content.
     *    The rail is removed from the grid entirely, so the drawer state is transient
     *    and deliberately NOT written to the cookie - otherwise opening the menu once
     *    on a phone would silently rewrite the desktop preference.
     *
     * The drawer uses its own .drawer-open class instead of .expanded because
     * .expanded is rendered server side from the cookie and would flash the drawer
     * open on every mobile page load.
     */
    var MENU_DRAWER_BREAKPOINT = 992;
    var menuBackdrop = null;

    initMenuToggle();                 // Rail toggle (lg and up) plus drawer toggle
    initMenuDrawer();                 // Navbar button, scrim, Escape, outside click
    initResizeCollapseHandler();      // Keeps the two modes in sync across resizes

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
        menuBackdrop.className = 'software-menu-backdrop d-print-none';
        menuBackdrop.setAttribute('aria-hidden', 'true');
        document.body.appendChild(menuBackdrop);
        return menuBackdrop;
    }

    function openMenuDrawer() {
        var backdrop = getMenuBackdrop();
        $('.software-container').addClass('drawer-open');
        $('#menu_drawer_toggle').attr('aria-expanded', 'true');
        // Next frame, so the element is in the DOM before the opacity transition runs.
        window.requestAnimationFrame(function () {
            backdrop.classList.add('show');
        });
    }

    function closeMenuDrawer() {
        $('.software-container').removeClass('drawer-open');
        $('#menu_drawer_toggle').attr('aria-expanded', 'false');
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

    // Rail toggle. Below lg it drives the drawer instead, so the control keeps working
    // if a stylesheet override ever leaves it visible there.
    function initMenuToggle() {
        $('#menu_toggle').on('click', function () {
            if (isMenuDrawerMode()) {
                toggleMenuDrawer();
                return;
            }

            if (!$(this).hasClass('active')) {
                expandMenu();
            } else {
                collapseMenu();
            }
        });
    }

    function initMenuDrawer() {
        $('#menu_drawer_toggle').on('click', function (e) {
            e.preventDefault();
            toggleMenuDrawer();
        });

        // Scrim tap closes. Bound on document because the element is created lazily.
        $(document).on('click', '.software-menu-backdrop', function () {
            closeMenuDrawer();
        });

        // Tapping the page behind the drawer closes it too.
        $('.software-content').on('click', function () {
            if (isMenuDrawerMode() && isMenuDrawerOpen()) {
                closeMenuDrawer();
            }
        });

        // Following any menu link should not leave the drawer open behind the new page
        // in browsers that restore the DOM from the back-forward cache.
        $('#menu').on('click', 'a[href]', function () {
            if (isMenuDrawerMode()) {
                closeMenuDrawer();
            }
        });

        $(document).on('keydown', function (e) {
            if ((e.key === 'Escape' || e.key === 'Esc') && isMenuDrawerOpen()) {
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

            if (drawerMode) {
                // Entering drawer mode: drop the rail state without touching the cookie
                // so the desktop preference survives.
                closeMenuDrawer();
                $('#menu, .software-container').removeClass('expanded');
                $('#menu_toggle').removeClass('active');
            } else {
                // Back to rail mode: restore whatever the user last chose on desktop.
                closeMenuDrawer();
                if (getCookie('softwaremenustatus') === 'expanded') {
                    $('#menu, .software-container').addClass('expanded');
                    $('#menu_toggle').addClass('active');
                }
            }
        }

        $(window).on('load resize orientationchange', syncMenuMode);
        syncMenuMode();
    }

    // Expand the rail and remember it (lg and up only).
    function expandMenu() {
        $('#menu, .software-container').addClass('expanded');
        $('#menu_toggle').addClass('active');
        setCookie('softwaremenustatus', 'expanded', 1);
    }

    // Collapse the rail and remember it (lg and up only).
    function collapseMenu() {
        $('#menu, .software-container').removeClass('expanded');
        $('#menu_toggle').removeClass('active');
        setCookie('softwaremenustatus', 'collapsed', 1);
    }



    var output_system_language_name;
    switch (software_system_language) {
        case "tr":
            output_system_language_name = "Turkish";
            break;
        default:
            //default is english so we use default.
            output_system_language_name = "English";
    }


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
    }


    var options = {};
    options["language"] = {
        url: "https://cdn.datatables.net/plug-ins/1.13.6/i18n/tr.json"
    };

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
        datatable.columns.adjust().draw();
        $('table.dataTable tbody tr td.dataTables_empty').parent('tr').addClass('unselectable');
    };



    options["dom"] = "<'container-fluid p-3 pt-4'<'row'<'col-12 col-md-6 text-center text-md-start'li><'col-12 col-md-6 text-center text-md-end'BRf>>>rt<'p-2 text-center'i><'p-2'p>";
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
            className: "dropdown-toggle btn btn-sm bi-me-2 bi bi-layout-three-columns",
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
                className: 'dtable-button-container p-2'
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

        // Faz 3a: enable responsive column collapse on narrow viewports.
        // Opt-out per table by adding class "datatable-no-responsive".
        if (!$('table.chart').hasClass('datatable-no-responsive')) {
            options["responsive"] = false;
        }

        var datatable = $("table.chart").DataTable(options);
        datatable.buttons().container().appendTo($(".chart-buttons"));
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
            history.replaceState(null, '', '?' + params + location.hash);
            datatable.search(query).draw();
        }
    }

    //#notifications
    $('#notification_toggle').on('click', function () {
        if ($(this).hasClass("show")) {
            get_notifications(true);
        } else {
            $('#notifications').scrollTop(0);
        }
    });
    //shown #notifications
    $('#notifications').on('shown.bs.dropdown', function () {
        $('#notification_toggle').addClass('show');
        get_notifications(true);
    });
    //hidden #notifications
    $('#notifications').on('hidden.bs.dropdown', function () {
        $('#notification_toggle').removeClass('show');
        $('#notifications').scrollTop(0);
    });

    pg_push_open_requested();

    pg_push_init();

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

    $(document).on("change", "select.collapse-if-selected", function () {
        var target = $(this).attr("data-bs-target");
        if ($(this).val() == '') {
            $(target).collapse("hide");
        } else {
            $(target).collapse("show");
        }
    });
    $(document).on("click change", "input.collapse-switcher", function () {
        var target = $(this).attr("data-bs-target");
        if ($("input[name=" + $(this).attr("name") + "]").length > 1) {
            var multi_switcher_this_id = $(this).attr("id");
            $("input[name=" + $(this).attr("name") + "]").each(function () {
                if ($(this).attr("id") != multi_switcher_this_id) {
                    $($(this).attr("data-bs-target")).toggle(false);
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
    });
    if ($("input.timepicker").length > 0) {
        $("input.timepicker").timepicker(timepicker_options);
    };

    if ($("input[name=convert_to_metric_system]").length > 0) {
        var unit_lb_label = $("input[name=weight]").parent().find("label.unit");
        var unit_lb_label_text = unit_lb_label.text();
        var unit_in_label = $("input[name=length]").parent().find("label.unit");
        var unit_in_label_text = unit_in_label.text();
    }
    $("input[name=convert_to_metric_system]").on("click", function () {
        var switcher = $(this);
        $($(this).attr("data-bs-target")).each(function () {
            switch ($(this).attr("id")) {
                case "weight":
                    var input = $("#" + $(this).attr("id"));
                    if (switcher.is(':checked')) {
                        input.val(input.val() * 0.45359237);
                        input.parent().find("label.unit").each(function () {
                            $(this).text("kg");
                        });
                    } else {
                        input.val(input.val() * 2.20462262185);
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
                        input.val(input.val() * 2.54);
                        input.parent().find("label.unit").each(function () {
                            $(this).text("cm");
                        });

                    } else {
                        input.val(input.val() * 0.393700787);
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

    if ($("select.select2").length) {
        $("select.select2").each(function () {
            var opts = { theme: "bootstrap-5" };
            if ($(this).data('allowClear')) opts.allowClear = true;
            $(this).select2(opts);
        });
    }
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



    // If there is a help URL, then set help button so it loads help popup when clicked.
    if (help_url) {
        $('#help_link').click(function () {
            window.open(help_url, 'help', 'location=1, status=1, scrollbars=1, resizable=1, directories=1, toolbar=1, titlebar=1').focus();
            return false;
        });

        // Otherwise, there is not a help URL, so hide help button.  This should only happen if site
        // is private labeled.
    } else {
        $('#help_link').hide();
    }


    $("#backup").click(function () {
        if ($(this).hasClass("ready")) {
            software_backup_start();
        }
    });

    $("#backup_folder_name").on("keyup", function (event) {
        if (event.keyCode === 13) {
            event.preventDefault();
            $("#backup").click();
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

        function normalizeTR(s) {
            return s.toLowerCase()
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
            page: { thumb: function (r) { return makeThumb(PAGE_ICONS[r.sub] || 'bi-file-earmark', 'text-primary-emphasis', null); }, url: function (r) { return path + software_directory + '/edit_page.php?id=' + r.id; } },
            form: { thumb: function (_r) { return makeThumb('bi-ui-checks', 'text-primary-emphasis', null); }, url: function (r) { return path + software_directory + '/edit_page.php?id=' + r.id; } },
            product: { thumb: function (r) { return makeThumb('bi-box-seam', 'text-success-emphasis', r.image || null); }, url: function (r) { return path + software_directory + '/edit_product.php?id=' + r.id; } },
            product_group: { thumb: function (_r) { return makeThumb('bi-boxes', 'text-info-emphasis', null); }, url: function (r) { return path + software_directory + '/edit_product_group.php?id=' + r.id; } },
            offer: { thumb: function (_r) { return makeThumb('bi-percent', 'text-warning-emphasis', null); }, url: function (r) { return path + software_directory + '/edit_offer.php?id=' + r.id; } },
            menu: { thumb: function (_r) { return makeThumb('bi-menu-button', 'text-body-secondary', null); }, url: function (r) { return path + software_directory + '/edit_menu.php?id=' + r.id; } },
            calendar: { thumb: function (_r) { return makeThumb('bi-calendar3', 'text-body-secondary', null); }, url: function (r) { return path + software_directory + '/edit_calendar.php?id=' + r.id; } },
            email_campaign: { thumb: function (_r) { return makeThumb('bi-megaphone', 'text-danger-emphasis', null); }, url: function (r) { return path + software_directory + '/edit_email_campaign_profile.php?id=' + r.id; } },
            style: { thumb: function (_r) { return makeThumb('bi-window', 'text-info-emphasis', null); }, url: function (r) { return path + software_directory + '/page_designer.php?style_id=' + r.id; } },
            user: { thumb: function (_r) { return makeThumb('bi-person-circle', 'text-body-secondary', null); }, url: function (r) { return path + software_directory + '/edit_user.php?id=' + r.id; } },
            file: { thumb: function (r) { var e = (r.name || '').split('.').pop().toLowerCase(); return makeThumb(FILE_ICONS[e] || (r.design ? 'bi-filetype-css' : 'bi-file-earmark'), 'text-secondary-emphasis', null); }, url: function (r) { return path + software_directory + '/edit_file.php?id=' + r.id; } },
            contact: { thumb: function (r) { return makeThumb('bi-person-vcard', 'text-body-secondary', r.image || null); }, url: function (r) { return path + software_directory + '/edit_contact.php?id=' + r.id; } },
            common_region: { thumb: function (_r) { return makeThumb('bi-columns-gap', 'text-primary-emphasis', null); }, url: function (r) { return path + software_directory + '/edit_common_region.php?id=' + r.id; } },
            design_region: { thumb: function (_r) { return makeThumb('bi-code-square', 'text-warning-emphasis', null); }, url: function (r) { return path + software_directory + '/edit_designer_region.php?id=' + r.id; } },
            login_region: { thumb: function (_r) { return makeThumb('bi-shield-lock', 'text-info-emphasis', null); }, url: function (r) { return path + software_directory + '/edit_login_region.php?id=' + r.id; } },
            short_link: { thumb: function (_r) { return makeThumb('bi-link-45deg', 'text-success-emphasis', null); }, url: function (r) { return path + software_directory + '/edit_short_link.php?id=' + r.id; } },
            order: { thumb: function (_r) { return makeThumb('bi-receipt', 'text-success-emphasis', null); }, url: function (r) { return path + software_directory + '/view_order.php?id=' + r.id; } }
        };

        $(document).on('keydown', function (e) {
            if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
                e.preventDefault();
                $modal.modal($modal.hasClass('show') ? 'hide' : 'show');
            }
        });

        $modal.on('shown.bs.modal', function () {
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

        function appendResults(results) {
            $.each(results, function (_i, r) {
                var meta = TYPE_META[r.type];
                if (!meta) return;
                var $btn = $('<button type="button" class="btn btn-link d-flex align-items-center w-100 link-body-emphasis text-decoration-none py-1 ps-1 gap-2 text-start border-bottom border-opacity-10"></button>');
                $btn.append(meta.thumb(r));
                var $wrap = $('<span class="d-flex flex-column overflow-hidden"></span>');
                $wrap.append($('<span class="text-truncate lh-sm fw-medium"></span>').text(r.name));
                if (r.sub) { $wrap.append($('<span class="text-truncate small text-muted lh-sm"></span>').text(r.sub)); }
                $btn.append($wrap);
                $btn.on('click', function () { $modal.modal('hide'); leave(meta.url(r)); });
                $results.append($btn);
            });
        }

        function runSearch(query, offset) {
            if (loading) return;
            loading = true;
            $clearBtn.removeClass('d-none');
            $emptyBox.addClass('d-none');
            $results.find('.srch-load-more').remove();

            if (offset === 0) {
                $results.empty().addClass('show').append(
                    '<div class="text-center py-2 text-muted"><span class="spinner-border spinner-border-sm me-1"></span>' + t('Please Wait') + '</div>'
                );
            } else {
                $results.append('<div class="srch-load-more text-center py-2 text-muted"><span class="spinner-border spinner-border-sm"></span></div>');
            }

            $.ajax({
                contentType: 'application/json',
                url: 'api.php',
                type: 'POST',
                data: JSON.stringify({ action: 'backend_search', search: query, offset: offset }),
                success: function (resp) {
                    loading = false;
                    $results.find('.srch-load-more').remove();

                    if (offset === 0) {
                        $results.empty();
                        if (resp.status !== 'success') {
                            $results.append('<p class="text-danger my-2">' + t('An error occurred.') + '</p>');
                            return;
                        }
                        if (resp.actions) cachedActions = resp.actions;
                        var actions = cachedActions || [];
                        var q = query.toLowerCase();
                        var matched = actions.filter(function (a) { return actionMatches(q, a.keys); });
                        if (matched.length) {
                            var $actRow = $('<div class="d-flex flex-wrap gap-1 mb-2 pb-2 border-bottom"></div>');
                            $.each(matched, function (_i, a) {
                                var $a = $('<a class="btn btn-sm btn-outline-secondary no-popover" href="' + a.url + '"><span class="bi ' + a.icon + ' me-1"></span>' + a.label + '</a>');

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

                                    var want = window.pgSettingsModal ? window.pgSettingsModal.asked(this) : null;

                                    if (want) {

                                        event.preventDefault();
                                        event.stopPropagation();

                                        $modal.one('hidden.bs.modal', function () {
                                            window.pgSettingsModal.open(want.pane, want.section);
                                        });

                                        $modal.modal('hide');

                                        return;
                                    }

                                    // No dialog in this document - see
                                    // settingsElsewhere(). The chip walks the
                                    // parent to the dashboard, which has one.
                                    var elsewhere = inToolbar ? settingsElsewhere(this.getAttribute('href')) : null;

                                    if (elsewhere) {

                                        event.preventDefault();
                                        event.stopPropagation();

                                        $modal.modal('hide');
                                        leave(elsewhere);

                                        return;
                                    }

                                    $modal.modal('hide');
                                });
                                $actRow.append($a);
                            });
                            $results.append($actRow);
                        }
                        if (!resp.results || resp.results.length === 0) {
                            $results.append('<p class="text-danger my-2">' + t('No Search Result') + '.</p>');
                            return;
                        }
                    }

                    if (resp.results && resp.results.length) {
                        appendResults(resp.results);
                    }

                    if (resp.has_more) {
                        var $more = $('<button type="button" class="srch-load-more btn btn-sm btn-outline-secondary w-100 mt-2 mb-1"><span class="bi bi-chevron-down me-1"></span>' + t('Load More') + '</button>');
                        $more.on('click', function () {
                            currentOffset += 20;
                            runSearch(currentQuery, currentOffset);
                        });
                        $results.append($more);
                    }
                },
                error: function () {
                    loading = false;
                    $results.find('.srch-load-more').remove();
                    if (offset === 0) $results.empty().append('<p class="text-danger my-2">' + t('An error occurred.') + '</p>');
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
                                <button type="button" class="btn btn-link link-danger bi bi-x-lg p-0" title="remove" onclick=" $(this).closest(\'.item\').remove();"></button>\
                            </div>\
                            <div class="card-body overflow-hidden position-relative rounded ratio ratio-2x1 w-100" style="--bs-aspect-ratio: 80%;background: radial-gradient(transparent, #00000024);" title="' + properties.image_name + '">\
                                <input type="hidden" name="' + $output_input_name + '" value="' + $output_input_value + '"/>\
                                <img class="object-fit-contain w-100 h-100" src="' + _img_src + '" />\
                            </div>\
                        </div>\
                    </div>');

            } else {
                $('#software_image_picker_container [name="selected_images[]"][value="' + decodeURIComponent(properties.image_name) + '"]').closest('.item').effect("shake", { direction: "up left", times: 4, distance: 5 }, 1000);
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
            result = confirm('WARNING: The selected ' + name + '(s) will be opted-in.')
            break;

        case 'optout':
            document.form.action.value = 'optout';
            result = confirm('WARNING: The selected ' + name + '(s) will be opted-out.')
            break;

        case 'merge':
            document.form.action.value = 'merge';
            result = confirm('WARNING: The selected duplicate ' + name + '(s) will be merged together.')
            break;
        case 'delete':
            document.form.action.value = 'delete';
            result = confirm('WARNING: The selected ' + name + '(s) will be permanently deleted.')
            break;
    }
    // if user select ok to confirmation, submit form
    if (result == true) {
        document.form.submit();
    }
}

function chart_checkbox_state_change() {
    if ($('.select-all input[type=checkbox]').is(':checked')) {
        $('.enable-on-selected button').each(function () {
            $(this).removeClass('disabled');
        });
        $('.dataTable .action-buttons *').each(function () {
            $(this).attr('style', 'visibility:hidden !important;');
        });

    } else {
        $('.enable-on-selected button').each(function () {
            $(this).addClass('disabled');
        });
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

    $('#notifications').on('shown.bs.dropdown', function () {
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

    $('#push_toggle').toggleClass('btn-outline-secondary', !$subscribed).toggleClass('btn-secondary', $subscribed);

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
function check_unread_notifications(refresh_type) {

    if ($('#notifications').length > 0) {
        $unreads = 0;
        $unreadsCallback = 0;
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
                    if ($('#notifications').hasClass("show")) {
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
                        if ($('#notifications').hasClass("show")) {
                            if ($unreads > $unreadsCallback && refresh_type != 'editcallback_check') {
                                get_notifications();
                            }
                        }
                        $output_unreads = 0;
                        if ($unreads > 99) {
                            $output_unreads = '99+';
                        } else {
                            $output_unreads = $unreads;
                        }
                        //everything is allright, and there is new notifications. We output notificationn button with notices and badges.
                        $('#notification_toggle').addClass('text-success');
                        $('#notification_toggle .has-notification-icon').html($output_unreads);
                    } else {
                        // we check but there is no notifications so we output only notification button.
                        $('#notification_toggle').removeClass('text-success');
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


function get_notifications($read_mark) {
    const notifications = $('#notifications');
    const notifications_body = $('#notifications .menu-scroll-area');
    const notifications_content = $('#notifications .menu-scroll-area *');
    var notifications_empty = $('#notifications .notifications_empty');
    notifications.scrollTop(0);

    notifications_empty.addClass('d-none');

    var spinner_content = '<div class="spinner text-center p-3"><div class="spinner-border text-success" role="status"><span class="visually-hidden">Loading...</span></div></div>';
    notifications_content.remove();

    //first of all we put a loading spinner while processing.
    notifications_body.prepend(spinner_content);

    var spinner = $('#notifications .spinner');
    // we will get new notifications that user can access and this notifications will set readed so we remove badges from notification button.
    $('#notification_toggle .has-notification-icon').html('');
    $('#notification_toggle').removeClass('text-success');

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

                        response.data[i].description = '<div class="notification-description bg-body-secondary" style="--bs-bg-opacity: 0.2;">' + response.data[i].description + '</div>';
                        response.data[i].details = '<div notification-details>' + response.data[i].details + '</div>';
                        if (response.data[i].action != 'custom') {
                            output_button_bar = '<button type="button" class="dropdown-item bi bi-link bi-me-2 text-primary-emphasis notification-go" data-url="' + h(String(response.data[i].url || '')) + '">' + lang('Go to the relevant page') + '</button>';


                            output_button_bar += '<button type="button" class="fs-smaller dropdown-item bi bi-check-square bi-me-2 text-secondary-emphasis" onclick="edit_notification(\'' + notification_id + '\', \'mark_unread\'); this.remove();">' + lang('Mark as unread') + '</button>';

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
                                    <div class="col-12 ps-4 p-2 border-bottom border-secondary " style="--bs-border-opacity:0.04;">\
                                        <div class="d-flex w-100 justify-content-between">\
                                            ' + output_data_title + '\
                                        </div>\
                                        <small>' + response.data[i].description + response.data[i].details + '</small>\
                                        <div class="d-flex w-100 justify-content-between">\
                                            <span class="me-auto badge bg-transparent text-reset fw-light text-overflow-hidden">' + notification_user + '</span>\
                                            <span class="ms-auto badge bg-transparent text-reset fw-light">' + notification_time + '</span>\
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
                        output_notifications += '<a href="#!" onclick="remove_all_notifications();" title="' + lang('Delete All Notifications') + '" class="dropdown-item notification-remove-btn text-danger border-top"><span class="bi bi-trash me-2"></span>' + lang('Delete All Notifications') + '</a></li>';
                    }

                    notifications_body.append(output_notifications);
                    notifications.scrollTop(0);
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


//::::::::::::::::::Filters::::::::::::::::::
function clear_value(filter_number) {
    // if an option was selected for dynamic value pick list, then clear value
    if (document.getElementById('filter_' + filter_number + '_dynamic_value').options[document.getElementById('filter_' + filter_number + '_dynamic_value').selectedIndex].value != '') {
        document.getElementById('filter_' + filter_number + '_value').value = '';
    }
}
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

//::::::::::::::::::input.tagin::::::::::::::::::
function tagin(el, option = {}) {
    const classElement = 'tagin'
    const classWrapper = 'tagin-wrapper'
    const classTag = 'tagin-tag'
    const classRemove = 'tagin-tag-remove '
    const classInput = 'tagin-input'
    const classInputHidden = 'tagin-input-hidden'
    const defaultSeparator = ','
    const defaultDuplicate = 'false'
    const defaultTransform = input => input
    const defaultPlaceholder = ''
    const separator = el.dataset.separator || option.separator || defaultSeparator
    const duplicate = el.dataset.duplicate || option.duplicate || defaultDuplicate
    const transform = eval(el.dataset.transform) || option.transform || defaultTransform
    const placeholder = el.dataset.placeholder || option.placeholder || defaultPlaceholder

    const templateTag = value => `<span class="${classTag}"><span class="${classTag}_text">${value}</span><span class="${classRemove}"></span></span>`

    const getValue = () => el.value
    const getValues = () => getValue().split(separator)

        // Create
        ;
    (function () {
        const className = classWrapper + ' ' + el.className.replace(classElement, '').trim()
        const tags = getValue().trim() === '' ? '' : getValues().map(templateTag).join('')
        const template = `<div class="${className}">${tags}<input type="text" class="${classInput}" placeholder="${placeholder}"></div>`
        el.insertAdjacentHTML('afterend', template) // insert template after element
    })()

    const wrapper = el.nextElementSibling
    const input = wrapper.getElementsByClassName(classInput)[0]
    const getTags = () => [...wrapper.getElementsByClassName(classTag)].map(tag => tag.textContent)
    const getTag = () => getTags().join(separator)

    const updateValue = () => {
        el.value = getTag();
        el.dispatchEvent(new Event('change'))
    }

    // Focus to input
    wrapper.addEventListener('click', () => input.focus())

    // Toggle focus class
    input.addEventListener('focus', () => wrapper.classList.add('focus'))
    input.addEventListener('blur', () => wrapper.classList.remove('focus'))

    // Remove by click
    document.addEventListener('click', e => {
        if (e.target.closest('.' + classRemove)) {
            e.target.closest('.' + classRemove).parentNode.remove()
            updateValue()
        }
    })

    //Stop Enter key submit form, and let addTag if tagin focused
    input.addEventListener('keydown', e => {
        if (e.keyCode == 13) {
            addTag(true)
            autowidth()
            e.preventDefault();
            return false;
        }
    });
    // Remove with backspace
    input.addEventListener('keydown', e => {
        if (input.value === '' && e.keyCode === 8 && wrapper.getElementsByClassName(classTag).length) {
            wrapper.querySelector('.' + classTag + ':last-of-type').remove()
            updateValue()
        }
    })

    // Adding tag
    input.addEventListener('input', () => {
        addTag()
        autowidth()
    })
    input.addEventListener('blur', () => {
        addTag(true)
        autowidth()
    })
    autowidth()

    function autowidth() {
        const fakeEl = document.createElement('div')
        fakeEl.classList.add(classInput, classInputHidden)
        const string = input.value || input.getAttribute('placeholder') || ''
        fakeEl.innerHTML = string.replace(/ /g, '&nbsp;')
        document.body.appendChild(fakeEl)
        input.style.setProperty('width', Math.ceil(window.getComputedStyle(fakeEl).width.replace('px', '')) + 1 + 'px')
        fakeEl.remove()
    }

    function addTag(force = false) {
        const value = transform(input.value.trim())
        if (value === '') {
            input.value = ''
        }
        if (input.value.includes(separator) || (force && input.value != '')) {
            value.split(separator).filter(i => i != '').forEach(val => {
                if (getTags().includes(val) && duplicate === 'false') {
                    alertExist(val)
                } else {
                    input.insertAdjacentHTML('beforebegin', templateTag(val))
                    updateValue()
                }
            })
            input.value = ''
            input.removeAttribute('style')
        }
    }

    function alertExist(value) {
        for (const el of wrapper.getElementsByClassName(classTag)) {
            if (el.textContent === value) {
                el.style.transform = 'scale(1.09)'
                setTimeout(() => {
                    el.removeAttribute('style')
                }, 150)
            }
        }
    }

    function updateTag() {
        if (getValue() !== getTag()) {
            [...wrapper.getElementsByClassName(classTag)].map(tag => tag.remove())
            getValue().trim() !== '' && input.insertAdjacentHTML('beforebegin', getValues().map(templateTag).join(''))
        }
    }

    function escapeRegex(value) {
        return value.replace(/[\-\[\]{}()*+?.,\\\^$|#\s]/g, '\\$&')
    }
    el.addEventListener('change', () => updateTag())
}


function prepare_content_for_html(content) {
    var chars = new Array('&', '�', '�', '�', '�', '�', '�', '�', '�', '�', '�', '�', '�', '�', '�', '�', '�', '�', '�', '�', '�', '�', '�', '�', '�', '�', '�', '�', '�', '�', '�', '�', '�', '�', '�', '�', '�', '�', '�', '�', '�', '�', '�', '�', '�', '�', '�', '�', '�', '�', '�', '�', '�', '�', '�', '�', '�', '�', '�', '�', '�', '�', '�', '\"', '�', '<', '>', '�', '�', '�', '�', '�', '�', '�', '�', '�', '�', '�', '�', '�', '�', '�', '�', '�', '�', '�', '�', '�', '�', '�', '�', '�', '�', '�', '�', '�');

    var entities = new Array('amp', 'agrave', 'aacute', 'acirc', 'atilde', 'auml', 'aring', 'aelig', 'ccedil', 'egrave', 'eacute', 'ecirc', 'euml', 'igrave', 'iacute', 'icirc', 'iuml', 'eth', 'ntilde', 'ograve', 'oacute', 'ocirc', 'otilde', 'ouml', 'oslash', 'ugrave', 'uacute', 'ucirc', 'uuml', 'yacute', 'thorn', 'yuml', 'Agrave', 'Aacute', 'Acirc', 'Atilde', 'Auml', 'Aring', 'AElig', 'Ccedil', 'Egrave', 'Eacute', 'Ecirc', 'Euml', 'Igrave', 'Iacute', 'Icirc', 'Iuml', 'ETH', 'Ntilde', 'Ograve', 'Oacute', 'Ocirc', 'Otilde', 'Ouml', 'Oslash', 'Ugrave', 'Uacute', 'Ucirc', 'Uuml', 'Yacute', 'THORN', 'euro', 'quot', 'szlig', 'lt', 'gt', 'cent', 'pound', 'curren', 'yen', 'brvbar', 'sect', 'uml', 'copy', 'ordf', 'laquo', 'not', 'shy', 'reg', 'macr', 'deg', 'plusmn', 'sup2', 'sup3', 'acute', 'micro', 'para', 'middot', 'cedil', 'sup1', 'ordm', 'raquo', 'frac14', 'frac12', 'frac34');

    for (var i = 0; i < chars.length; i++) {
        myRegExp = new RegExp();
        myRegExp.compile(chars[i], 'g');
        content = content.replace(myRegExp, '&' + entities[i] + ';');
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
                <div class="input-group my-2 group group_' + id + '" >\
                    <select name="product_group" class="form-select product_group"><option value=""></option>' + group_options + '</select>\
                    <a href="javascript:void(0)" title="' + label['Remove'] + '" class="remove btn btn-danger no-popover">x</a>\
                </div>\
            </div>');

        $('.group_' + id + ' .product_group').select2({
            theme: 'bootstrap-5',
            placeholder: '-' + label['Select Group'] + '-',
            allowClear: true
        });

        // If this group is an existing group for the product,
        // then set value and trigger change event so that option pick list appears.
        if (typeof group.product_group !== 'undefined') {
            $('.group_' + id + ' .product_group').val(group.product_group).trigger('change');
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
            '<div class="col-12 my-2 attribute attribute_' + attribute.id + '" data-attribute-id="' + attribute.id + '">\
                <label class="form-label">' + h(attribute.name) + '</label>\
                <div class="input-group">\
                    <label class="input-group-text">' + label['Default Option'] + ':</label>\
                    <select class="default_option_id form-select">\
                        <option value="">-' + label['None'] + '-</option>\
                        ' + output_options + '\
                    </select>\
                    <a href="javascript:void(0)" class="move move_up btn btn-secondary material-icons border no-popover" title="' + label['Move Up'] + '">north</a>\
                    <a href="javascript:void(0)" class="move move_down btn btn-primary material-icons border no-popover" title="' + label['Move Down'] + '">south</a>\
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

            // Let's remove the disabled classes until we find out if they need to be disabled.
            move_up.removeClass('disabled');
            move_down.removeClass('disabled');

            // If there is only 1 attribute, then disable both buttons.
            if (number_of_attributes == 1) {
                move_up.addClass('disabled');
                move_down.addClass('disabled');

                // Otherwise, if this is the first attribute, then disable the move up button.
            } else if (attribute.index() == 0) {
                move_up.addClass('disabled');

                // Otherwise, if this is the last attribute, then disable the move down button.
            } else if (attribute.is(':last-child')) {
                move_down.addClass('disabled');
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

            move_up.removeClass('disabled');
            move_down.removeClass('disabled');

            if (option.is(':only-child')) {
                move_up.addClass('disabled');
                move_down.addClass('disabled');
            } else if (option.is(':first-child')) {
                move_up.addClass('disabled');
            } else if (option.is(':last-child')) {
                move_down.addClass('disabled');
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
            '<div class="col-12 col-lg-7 my-2 option option_' + current_id + '">\
                <div class="p-2 border rounded">\
                    <input type="hidden" name="option_id" value="' + id + '" class="option_id">\
                    <div class="input-group">\
                        <input type="text" name="option_label" maxlength="255" class="option_label form-control">\
                        <a href="javascript:void(0)" class="move move_up btn btn-primary material-icons no-popover" title="' + label['Move Up'] + '">north</a>\
                        <a href="javascript:void(0)" class="move move_down btn btn-primary material-icons no-popover" title="' + label['Move Down'] + '">south</a>\
                        <a href="javascript:void(0)" class="remove btn btn-danger material-icons no-popover" title="' + label['Remove'] + '">delete</a>\
                    </div>\
                    <div class="form-check form-text m-2">\
                      <input class="form-check-input option_no_value" type="checkbox" id="option_no_value_' + current_id + '" name="option_no_value" value="1">\
                      <label class="form-check-label" for="option_no_value_' + current_id + '">' + label['\u0027No Thanks\u0027 Option'] + '</label>\
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
            '<div class="col-12 col-sm-auto ">\
                <div class="input-group my-2 attribute attribute_' + id + '" >\
                    <select name="attribute_id" class="form-select attribute_id">' + output_options + '</select>\
                    <a href="javascript:void(0)" class="remove btn btn-danger no-popover" title="' + label['Remove'] + '">x</a>\
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

                $('.attribute_' + id + ' .attribute_id').after(' <select name="option_id" class="form-select option_id">' + output_options + '</select>');

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
    <div id="message_modal_id_' + id + '" class="modal fade" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">\
        <div class="modal-dialog modal-lg modal-dialog-scrollable">\
            <div class="modal-content">\
                <div class="modal-header"><h5 class="modal-title">' + title + '</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>\
                <div class="modal-body p-0" style="height:100vh"></div>\
            </div>\
        </div>\
    </div>');
    $('body #message_modal_id_' + id + ' .modal-body').append(iframe);

    var messageModal = new bootstrap.Modal(document.getElementById("message_modal_id_" + id), {});
    messageModal.show();

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
    <select class="form-select my-1" id="submit_form_' + action + '_field_' + field_number + '_form_field_id" name="submit_form_' + action + '_field_' + field_number + '_form_field_id"><option value=""></option>';

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
    <button type="button" class="btn btn-danger material-icons" onclick="this.parentNode.parentNode.parentNode.removeChild(this.parentNode.parentNode)">delete</button>\
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
        <optgroup label="System Fields">\
            <option value="reference_code"' + reference_code_selected + '>Reference Code</option>\
        </optgroup>\
        <optgroup label="Form Fields">';

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

        // Bind once: datepicker() on an already-bound field is a no-op that
        // still costs a full re-init, and a second call would drop the value
        // the operator has just typed.
        var field = container.querySelector('input');

        if (field && window.jQuery && !$(field).hasClass('hasDatepicker') && (typeof datetimepicker_options !== 'undefined')) {
            $(field).datepicker(datetimepicker_options);
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

    if (window.jQuery && !$(field).hasClass('hasDatepicker') && (typeof datetimepicker_options !== 'undefined')) {
        $(field).datepicker(datetimepicker_options);
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
   plus the count beside it. The plugin that drives the list only ever sets
   checked/unchecked, so a half-selected list used to show the switch off - the
   one state it could not be. */
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
    Array.prototype.forEach.call((scope || document).querySelectorAll('.pg-perm-list'), pgSyncCheckboxList);
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
        '<select class="form-select" id="filter_' + filter_number + '_field" name="filter_' + filter_number + '_field" onchange="update_value_cell(' + filter_number + '); update_dynamic_value(' + filter_number + ')">\n\
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
    var operator_cell_html = '<select  class="form-select" name="filter_' + filter_number + '_operator">';

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
            <select class="form-select" id="filter_' + filter_number + '_dynamic_value" name="filter_' + filter_number + '_dynamic_value" style="display: none" onchange="update_dynamic_value_attribute(' + filter_number + '); clear_value(' + filter_number + ')"></select>\
        </div>';

    // insert content into dynamic value cell
    var td_4 = document.createElement('td');
    td_4.innerHTML = dynamic_value_cell_html;

    // prepare content for delete cell
    var delete_cell_html = '<button type="button" class="btn btn-danger material-icons" onclick="delete_filter(this.parentNode.parentNode)" >delete</button>';

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
    tbody = tr.parentNode;
    tbody.removeChild(tr);
}

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
                    '<select class="form-select" id="filter_' + filter_number + '_value" name="filter_' + filter_number + '_value">\n\
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

                // else there are not value options for the field, so create HTML for value text box
            } else {
                value_cell_html = '<input class="form-control" id="filter_' + filter_number + '_value" name="filter_' + filter_number + '_value" type="text" value="' + prepare_content_for_html(value) + '" maxlength="255" />';
            }

            // update value cell with HTML
            document.getElementById('filter_' + filter_number + '_value_cell').innerHTML = value_cell_html;

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
        document.getElementById('filter_' + filter_number + '_value').value = '';
    }
}

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

        $("#create_button").value = "Save & Continue";
        $("#create_button .btn-text").text(lang("Save & Continue"));

        // else the submit button should contain the normal "Save"
    } else {
        $("#create_button").val("Save");
        $("#create_button .btn-text").text(lang("Save"));
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
        $("#create_button").value = "Save & Continue";
        $("#create_button .btn-text").text(lang("Save & Continue"));

        // else the form is disabled or the form was already enabled, so do not show notice and update the submit button to contain "Save"
    } else {
        document.getElementById('billing_information_form_notice').style.display = 'none';
        $("#create_button").val("Save");
        $("#create_button .btn-text").text(lang("Save"));
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
        $("#create_button").value = "Save & Continue";
        $("#create_button .btn-text").text(lang("Save & Continue"));
        // Otherwise the form is disabled or the form was already enabled, so do not show notice and
        // update the submit button to contain "Save"
    } else {
        document.getElementById('express_order_shipping_form_notice').style.display = 'none';
        $("#create_button").val("Save");
        $("#create_button .btn-text").text(lang("Save"));
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
        $("#create_button").value = "Save & Continue";
        $("#create_button .btn-text").text(lang("Save & Continue"));

        // else the form is disabled or the form was already enabled, so do not show notice and update the submit button to contain "Save"
    } else {
        document.getElementById('express_order_form_notice').style.display = 'none';
        $("#create_button").val("Save");
        $("#create_button .btn-text").text(lang("Save"));
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
        $("#create_button").value = "Save & Continue";
        $("#create_button .btn-text").text(lang("Save & Continue"));
        // else the form is disabled or the form was already enabled, so do not show notice and update the submit button to contain "Save"
    } else {
        document.getElementById('shipping_address_and_arrival_form_notice').style.display = 'none';
        $("#create_button").val("Save");
        $("#create_button .btn-text").text(lang("Save"));
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

    // If the schedule option is selected by default when the page first loaded,
    // then show fields for it and init date/time picker.
    if (publish.val() == 'schedule') {
        publish_schedule.fadeIn();

        publish_date_and_time.datetimepicker(datetimepicker_options);
    }

    // When the publish pick list is changed, then update fields.
    publish.change(function () {
        // If the schedule option is selected, then show fields for it.
        if (publish.val() == 'schedule') {
            publish_schedule.fadeIn();

            publish_date_and_time.datetimepicker(datetimepicker_options);

            // Place the focus in the date & time field,
            // so that the date/time picker automatically appears.
            publish_date_and_time.focus();

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

        // Remove time picker by removing its parent date picker.
        $("#start_time").datepicker('destroy');
        $("#end_time").datepicker('destroy');

        // Add date picker to both fields.

        $("#start_time").datepicker(datetimepicker_options);

        $("#end_time").datepicker(datetimepicker_options);

        // Get just the date values in order to strip the times from the fields.
        var start_date = $.datepicker.formatDate(date_picker_format, $('#start_time').datepicker('getDate'));
        var end_date = $.datepicker.formatDate(date_picker_format, $('#end_time').datepicker('getDate'));

        // Update fields to only contain the date.
        $("#start_time").datepicker('setDate', start_date);
        $("#end_time").datepicker('setDate', end_date);

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

        // Remove date picker in preparation for adding date/time picker.
        $("#start_time").datepicker('destroy');
        $("#end_time").datepicker('destroy');

        // Add date/time picker to both fields.

        $("#start_time").datetimepicker(datetimepicker_options);

        $("#end_time").datetimepicker(datetimepicker_options);

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
        '<select id="shipping_cutoff_' + shipping_cutoff_number + '_shipping_method_id" name="shipping_cutoff_' + shipping_cutoff_number + '_shipping_method_id" class="form-select">\n\
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
    td_2.innerHTML = '<input id="shipping_cutoff_' + shipping_cutoff_number + '_date_and_time" name="shipping_cutoff_' + shipping_cutoff_number + '_date_and_time" class="form-control" type="text" value="' + properties['date_and_time'] + '" size="20" maxlength="22" />';

    // prepare content for delete cell
    var td_3 = document.createElement('td');
    td_3.innerHTML = '<button type="button" class="btn btn-danger material-icons" onclick="delete_shipping_cutoff(this.parentNode.parentNode)" >delete</button>';

    tr.appendChild(td_1);
    tr.appendChild(td_2);
    tr.appendChild(td_3);

    tbody.appendChild(tr);

    $('#shipping_cutoff_' + shipping_cutoff_number + '_date_and_time').datetimepicker(datetimepicker_options);

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


// initialize function for preparing layout cells
function initialize_style_designer() {
    var selected_area = '';
    var selected_row_index = '';
    var selected_cell_index = '';

    // initialize function for deselecting a cell that should no longer be selected
    function deselect_cell(area, row_index, cell_index) {
        // add disable styling to add column before button
        $('#' + area + '_add_column_before').addClass('disabled');

        // remove onclick event for add column before button
        $('#' + area + '_add_column_before').unbind('click');

        // add disable styling to add column after button
        $('#' + area + '_add_column_after').addClass('disabled');

        // remove onclick event for add column after button
        $('#' + area + '_add_column_after').unbind('click');

        // add disable styling to edit cell properties button
        $('#' + area + '_edit_cell_properties').addClass('disabled');

        // remove onclick event for edit cell properties button
        $('#' + area + '_edit_cell_properties').unbind('click');

        // remove selected styling from cell
        $('#' + area + '_row_' + row_index + '_cell_' + cell_index).removeClass('selected');

        // clear values for selected variables
        selected_area = '';
        selected_row_index = '';
        selected_cell_index = '';
    }

    function select_cell(area, row_index, cell_index) {
        // if there is a selected cell, then deselect it
        if (selected_cell_index !== '') {
            deselect_cell(selected_area, selected_row_index, selected_cell_index);
        }

        // remove disable styling from add column before button
        $('#' + area + '_add_column_before').removeClass('disabled');

        // add onclick event for add column before button
        $('#' + area + '_add_column_before').bind('click', { area: area }, function (event) {
            var area = event.data.area;

            // set the new cell index
            var new_cell_index = selected_cell_index;

            // prepare the cell that will be added to a row
            var cell = {
                'region_type': '',
                'region_name': ''
            };

            // add the cell to the array
            areas[area]['rows'][selected_row_index]['cells'].splice(new_cell_index, 0, cell);

            // update the area so that the correct cells will be displayed for this area
            update_area(area);

            // select the cell that we just added
            select_cell(area, selected_row_index, new_cell_index);
        });

        // remove disable styling from add column after button
        $('#' + area + '_add_column_after').removeClass('disabled');

        // add onclick event for add column after button
        $('#' + area + '_add_column_after').bind('click', { area: area }, function (event) {
            var area = event.data.area;

            // set the new cell index to one more than the selected cell index
            var new_cell_index = selected_cell_index + 1;

            // prepare the cell that will be added to a row
            var cell = {
                'region_type': '',
                'region_name': ''
            };

            // add the cell to the array
            areas[area]['rows'][selected_row_index]['cells'].splice(new_cell_index, 0, cell);

            // update the area so that the correct cells will be displayed for this area
            update_area(area);

            // select the cell that we just added
            select_cell(area, selected_row_index, new_cell_index);
        });

        // remove disable styling from edit cell properties button
        $('#' + area + '_edit_cell_properties').removeClass('disabled');

        // add onclick event for edit cell properties button
        $('#' + area + '_edit_cell_properties').click(function () {
            $('#edit_cell_properties').dialog('open');
        });

        // update the selected variables so they store information for this cell
        selected_area = area;
        selected_row_index = row_index;
        selected_cell_index = cell_index;

        // add selected class to cell
        $('#' + area + '_row_' + row_index + '_cell_' + cell_index).addClass('selected');
    }

    // initialize function that will be responsible for updating the label that appears inside a cell
    function update_cell_label(area, row_index, cell_index) {
        var region_type = areas[area]['rows'][row_index]['cells'][cell_index]['region_type'];
        var region_name = prepare_content_for_html(areas[area]['rows'][row_index]['cells'][cell_index]['region_name']);
        var page_region_number = areas[area]['rows'][row_index]['cells'][cell_index]['page_region_number'];

        var row = row_index + 1;
        var col = cell_index + 1;

        var cell_label = '';

        // prepare cell label
        switch (region_type) {
            case '':
                cell_label = '&nbsp;<br /><span class="theme_fold_css" style="padding: 0"> .r' + row + 'c' + col + ' .c' + col + '</span>';
                break;

            case 'ad':
                cell_label = 'Ad Region: ' + region_name + '<br /><span class="theme_fold_css" style="padding: 0"> .r' + row + 'c' + col + ' .ad_' + region_name + ' .c' + col + '</span>';
                break;

            case 'cart':
                cell_label = 'Cart Region<br /><span class="theme_fold_css" style="padding: 0"> .r' + row + 'c' + col + ' .cart .c' + col + '</span>';
                break;

            case 'common':
                cell_label = 'Common Region: ' + region_name + '<br /><span class="theme_fold_css" style="padding: 0"> .r' + row + 'c' + col + ' .cregion_' + region_name + ' .c' + col + '</span>';
                break;

            case 'designer':
                cell_label = 'Designer Region: ' + region_name + '<br /><span class="theme_fold_css" style="padding: 0"> .r' + row + 'c' + col + ' .cregion_' + region_name + ' .c' + col + '</span>';
                break;

            case 'dynamic':
                cell_label = 'Dynamic Region: ' + region_name + '<br /><span class="theme_fold_css" style="padding: 0"> .r' + row + 'c' + col + ' .dregion_' + region_name + ' .c' + col + '</span>';
                break;

            case 'login':
                cell_label = 'Login Region: ' + region_name + '<br /><span class="theme_fold_css" style="padding: 0"> .r' + row + 'c' + col + ' .login_' + region_name + ' .c' + col + '</span>';
                break;

            case 'menu':
                cell_label = 'Menu Region: ' + region_name + '<br /><span class="theme_fold_css" style="padding: 0"> .r' + row + 'c' + col + ' .menu_' + region_name + ' .c' + col + '</span>';
                break;

            case 'menu_sequence':
                cell_label = 'Menu Sequence Region: ' + region_name + '<br /><span class="theme_fold_css" style="padding: 0"> .r' + row + 'c' + col + ' .menu_sequence_' + region_name + ' .c' + col + '</span>';
                break;

            case 'mobile_switch':
                cell_label = 'Mobile Switch<br /><span class="theme_fold_css" style="padding: 0"> .r' + row + 'c' + col + ' .mobile_switch .c' + col + '</span>';
                break;

            case 'page':
                cell_label = 'Page Region #' + page_region_number + '<br /><span class="theme_fold_css" style="padding: 0"> .r' + row + 'c' + col + ' .pregion .c' + col + '</span>';
                break;

            case 'pdf':
                cell_label = 'PDF Region <sup>beta</sup><br /><span class="theme_fold_css" style="padding: 0"> .r' + row + 'c' + col + ' .pdf .c' + col + '</span>';
                break;

            case 'system':
                var region_name_for_label = '';
                var region_name_for_label_css = '';

                // if there is a region name, then output it next to the label
                if (region_name != '') {
                    region_name_for_label = region_name;
                    region_name_for_label_css = ' .system_' + region_name;

                    // else just output the basic label
                } else {
                    region_name_for_label = 'Use Page';
                    region_name_for_label_css = ' .system'
                }

                cell_label = 'System Region: ' + region_name_for_label + '<br /><span class="theme_fold_css" style="padding: 0"> .r' + row + 'c' + col + region_name_for_label_css + ' .c' + col + '</span>';
                break;

            case 'tag_cloud':
                var output_region_name = '';
                var output_region_name_css = '';

                // if there is a region name for this tag cloud, then prepare to output it
                if (region_name != '') {
                    output_region_name = ': ' + region_name;
                    output_region_name_css = ' .tcloud_' + region_name;
                } else {
                    output_region_name_css = ' .tcloud';
                }

                cell_label = 'Tag Cloud Region' + output_region_name + '<br /><span class="theme_fold_css" style="padding: 0"> .r' + row + 'c' + col + output_region_name_css + ' .c' + col + '</span>';
                break;
        }

        // update the label
        $('#' + area + '_row_' + row_index + '_cell_' + cell_index + ' .cell_label')[0].innerHTML = cell_label;
    }

    // Create function that will be used to update all of the page region numbers
    // anytime an event happens that affects the sequence of numbers (e.g. page region cell added)
    function update_page_region_numbers() {
        var page_region_number = 0;

        // Loop through the areas.
        for (var area in areas) {
            // Loop through the rows.
            for (var row_index = 0; row_index < areas[area]['rows'].length; row_index++) {
                // Loop through the cells.
                for (var cell_index = 0; cell_index < areas[area]['rows'][row_index]['cells'].length; cell_index++) {
                    // If this cell is a page region, then update number
                    if (areas[area]['rows'][row_index]['cells'][cell_index]['region_type'] == 'page') {
                        // increment page region number for this page region
                        page_region_number += 1;

                        areas[area]['rows'][row_index]['cells'][cell_index]['page_region_number'] = page_region_number;

                        // update page region number in cell label
                        update_cell_label(area, row_index, cell_index);
                    }
                }
            }
        }
    }

    // initialize function that will be responsible for looking at the array for an area in order to output cells
    function update_area(area) {
        // remove all cells from the cells container, because we are going to recreate cells
        $('#' + area + ' .cells').empty();

        // loop through rows in this area in order to add cells
        for (var row_index = 0; row_index < areas[area]['rows'].length; row_index++) {
            var number_of_cells = areas[area]['rows'][row_index]['cells'].length;
            var total_margin = (number_of_cells - 1) * 12;
            var total_border = number_of_cells * 2;
            var total_padding = number_of_cells * 24;

            // get the width for the cells based on how many cells are in this row
            var width = ($('#' + area + ' .cells').width() - total_margin - total_border - total_padding) / number_of_cells;

            // round down the width to the nearest whole number and subtract some in order to prevent problems with cells not fitting in one row
            width = Math.floor(width) - 5;

            // loop through cells in this row
            for (var cell_index = 0; cell_index < areas[area]['rows'][row_index]['cells'].length; cell_index++) {
                // add a div for the cell
                $('#' + area + ' .cells').append('\
                    <div id ="' + area + '_row_' + row_index + '_cell_' + cell_index + '" class="cell">\
                        <div class="cell_label"></div>\
                        <div class="cell_remove">X</div>\
                        <div class="clear"></div>\
                    </div>');

                // update the label that appears inside the cell
                update_cell_label(area, row_index, cell_index);

                // if the cell is selected, then add a class to the container
                if ((area === selected_area) && (row_index === selected_row_index) && (cell_index === selected_cell_index)) {
                    $('#' + area + '_row_' + row_index + '_cell_' + cell_index).addClass('selected');
                }

                // if this cell is the last cell in the row, then add last class, so that extra margin on the right is not added
                if (cell_index == (areas[area]['rows'][row_index]['cells'].length - 1)) {
                    $('#' + area + '_row_' + row_index + '_cell_' + cell_index).addClass('last');
                }

                // set the width for the cell
                $('#' + area + '_row_' + row_index + '_cell_' + cell_index).width(width);

                // add click event so that cell can be selected when clicked
                $('#' + area + '_row_' + row_index + '_cell_' + cell_index).bind('click', { area: area, row_index: row_index, cell_index: cell_index }, function (event) {
                    var area = event.data.area;
                    var row_index = event.data.row_index;
                    var cell_index = event.data.cell_index;

                    // if this cell is already selected, then open edit cell properties modal dialog
                    if ((area === selected_area) && (row_index === selected_row_index) && (cell_index === selected_cell_index)) {
                        $('#edit_cell_properties').dialog('open');

                        // else this cell is not already selected, so select it
                    } else {
                        select_cell(area, row_index, cell_index);
                    }
                });

                // add click event to the remove button, so that the cell can be removed
                $('#' + area + '_row_' + row_index + '_cell_' + cell_index + ' .cell_remove').bind('click', { area: area, row_index: row_index, cell_index: cell_index }, function (event) {
                    var area = event.data.area;
                    var row_index = event.data.row_index;
                    var cell_index = event.data.cell_index;

                    // store the region type before we remove it, so we know further below if it was a page region
                    var region_type = areas[area]['rows'][row_index]['cells'][cell_index]['region_type'];

                    // if this cell is selected, then deselect cell
                    if ((area === selected_area) && (row_index === selected_row_index) && (cell_index === selected_cell_index)) {
                        deselect_cell(area, row_index, cell_index);
                    }

                    // if there is only one cell in the row, then remove the whole row
                    if (areas[area]['rows'][row_index]['cells'].length == 1) {
                        areas[area]['rows'].splice(row_index, 1);

                        // else there is more than one cell in the row, so just remove the cell
                    } else {
                        areas[area]['rows'][row_index]['cells'].splice(cell_index, 1);
                    }

                    update_area(area);

                    // if the cell that was removed was a page region, then update page region numbers
                    if (region_type == 'page') {
                        update_page_region_numbers();
                    }
                });
            }

            // add clear div
            $('#' + area + ' .cells').append('<div class="clear"></div>');
        }
    }

    // loop through the areas, in order to prepare them
    for (var area in areas) {
        // add event listener to add row before button
        $('#' + area + '_add_row_before').bind('click', { area: area }, function (event) {
            var area = event.data.area;

            // if there is a selected cell in this area, then prepare to add the row and cell above the selected cell
            if (area == selected_area) {
                var new_row_index = selected_row_index;

                // else there is not a selected cell in this area, so prepare to add the cell to the top of the area
            } else {
                var new_row_index = 0;
            }

            // prepare the row and cell that will be added to the array
            var row = {
                'cells': [
                    {
                        'region_type': '',
                        'region_name': ''
                    }
                ]
            };

            // add the row and cell to the array
            areas[area]['rows'].splice(new_row_index, 0, row);

            // update the area so that the correct cells will be displayed for this area
            update_area(area);

            // select the cell that we just added
            select_cell(area, new_row_index, 0);
        });

        // add event listener to add row after button
        $('#' + area + '_add_row_after').bind('click', { area: area }, function (event) {
            var area = event.data.area;

            // if there is a selected cell in this area, then prepare to add the row and cell below the selected cell
            if (area == selected_area) {
                var new_row_index = selected_row_index + 1;

                // else there is not a selected cell in this area, so prepare to add the cell to the bottom of the area
            } else {
                var new_row_index = areas[area]['rows'].length;
            }

            // prepare the row and cell that will be added to the array
            var row = {
                'cells': [
                    {
                        'region_type': '',
                        'region_name': ''
                    }
                ]
            };

            // add the row and cell to the array
            areas[area]['rows'].splice(new_row_index, 0, row);

            // update the area so that the correct cells will be displayed for this area
            update_area(area);

            // select the cell that we just added
            select_cell(area, new_row_index, 0);
        });

        update_area(area);
    }

    // initialize function that will be responsible for showing or hiding region name field based on what region type is selected
    function show_or_hide_region_type() {
        var region_type = document.getElementById('region_type').options[document.getElementById('region_type').selectedIndex].value;

        // if the selected region type supports a region name, then update the region name pick list with options and show pick list
        if (
            (region_type == 'ad')
            || (region_type == 'common')
            || (region_type == 'designer')
            || (region_type == 'dynamic')
            || (region_type == 'login')
            || (region_type == 'menu')
            || (region_type == 'menu_sequence')
            || (region_type == 'tag_cloud')
            || (region_type == 'system')
        ) {
            // remove existing options from region name pick list
            document.getElementById('region_name').length = 0;

            // initialize variable for storing region names
            var region_names = [];

            // initialize the picklist's first option to be blank
            var picklist_first_option = '';

            // get the region names in different ways for different region types
            switch (region_type) {
                case 'ad':
                    region_names = ad_regions;
                    break;

                case 'common':
                    region_names = common_regions;
                    break;

                case 'designer':
                    region_names = designer_regions;
                    break;

                case 'dynamic':
                    region_names = dynamic_regions;
                    break;

                case 'login':
                    region_names = login_regions;
                    break;

                case 'menu':
                    region_names = menu_regions;
                    break;

                case 'menu_sequence':
                    region_names = menu_sequence_regions;
                    break;

                case 'tag_cloud':
                    region_names = tag_cloud_regions;
                    break;

                case 'system':
                    region_names = system_region_pages;

                    // set the picklists first option for the picklist
                    picklist_first_option = '-Use Page-';
                    break;
            }

            // initialize variable for storing options that will be added to the region name pick list
            document.getElementById('region_name').options.add(new Option(picklist_first_option, ''));

            // loop through all region names in order to prepare options for pick list
            for (var i = 0; i < region_names.length; i++) {
                document.getElementById('region_name').options.add(new Option(region_names[i], region_names[i]));
            }

            // update the region name pick list so that the correct option is selected based on the selected region name
            $("#region_name").val(areas[selected_area]['rows'][selected_row_index]['cells'][selected_cell_index]['region_name']);

            // show the region name row
            document.getElementById('region_name_row').style.display = '';

            // else the selected region type does not require a region name, so hide region name row
        } else {
            document.getElementById('region_name_row').style.display = 'none';
        }
    }

    // initialize edit cell properties modal dialog
    $('#edit_cell_properties').dialog({
        autoOpen: false,
        modal: true,
        width: 500,
        height: 200,
        title: 'Edit Cell Properties',
        dialogClass: 'standard',
        open: function () {
            // if there is no region for the selected cell, then default the region type to page
            if (areas[selected_area]['rows'][selected_row_index]['cells'][selected_cell_index]['region_type'] == '') {
                $("#region_type").val('page');

                // else there is a region for the selected cell, so update the region type pick list so that the correct option is selected based on the selected cell
            } else {
                $("#region_type").val(areas[selected_area]['rows'][selected_row_index]['cells'][selected_cell_index]['region_type']);
            }

            show_or_hide_region_type();
        }
    });

    // add on change event to region type pick list
    $('#region_type').change(function () {
        show_or_hide_region_type();
    });

    // add click event to update cell properties button
    $('#update_cell_properties').click(function () {
        // prepare to update region type and name
        var region_type = '';
        var region_name = '';

        region_type = document.getElementById('region_type').options[document.getElementById('region_type').selectedIndex].value;

        // If the region type supports a region name, and there was at least one name for the user to select,
        // then get the name that was selected.
        if (
            (
                (region_type == 'ad')
                || (region_type == 'common')
                || (region_type == 'designer')
                || (region_type == 'dynamic')
                || (region_type == 'login')
                || (region_type == 'menu')
                || (region_type == 'menu_sequence')
                || (region_type == 'tag_cloud')
                || (region_type == 'system')
            )
            && (document.getElementById('region_name').options.length > 0)
        ) {
            region_name = document.getElementById('region_name').options[document.getElementById('region_name').selectedIndex].value;
        }

        // if the region type requires a region name and a region name was not selected, then alert the user
        if (
            (
                (region_type == 'ad')
                || (region_type == 'common')
                || (region_type == 'designer')
                || (region_type == 'dynamic')
                || (region_type == 'login')
                || (region_type == 'menu')
                || (region_type == 'menu_sequence')
            )
            && (region_name == '')
        ) {
            alert('Please select a region name.');
            return false;
        }

        // store original region type so further below we know if we need to update page region numbers
        var original_region_type = areas[selected_area]['rows'][selected_row_index]['cells'][selected_cell_index]['region_type'];

        // update cell's properties in array
        areas[selected_area]['rows'][selected_row_index]['cells'][selected_cell_index]['region_type'] = region_type;
        areas[selected_area]['rows'][selected_row_index]['cells'][selected_cell_index]['region_name'] = region_name;

        // if this cell was not a page region before and now it is, then update page region numbers
        if (
            (original_region_type != 'page')
            && (region_type == 'page')
        ) {
            update_page_region_numbers();

            // else if this cell was a page region before and now it is not,
            // then update cell label and page region numbers
        } else if (
            (original_region_type == 'page')
            && (region_type != 'page')
        ) {
            update_cell_label(selected_area, selected_row_index, selected_cell_index);

            update_page_region_numbers();

            // else this cell was not a page region before and it still is not one,
            // so just update cell label
        } else {
            update_cell_label(selected_area, selected_row_index, selected_cell_index);
        }

        // close the edit cell properties modal dialog
        $('#edit_cell_properties').dialog('close');
    });

    // add click event to cancel cell properties button
    $('#cancel_cell_properties').click(function () {
        // close the edit cell properties modal dialog
        $('#edit_cell_properties').dialog('close');
    });

    // add submit event for when the form is submitted
    $('#style_designer_form').submit(function () {
        // assume that a "Use Page" system region does not exist until we find out otherwise
        var use_page_system_region_exists = false;

        // loop through the areas in order to determine if there is a "Use Page" system region
        area_loop: for (var area in areas) {
            // loop through the rows
            for (var row_index = 0; row_index < areas[area]['rows'].length; row_index++) {
                // loop through the cells
                for (var cell_index = 0; cell_index < areas[area]['rows'][row_index]['cells'].length; cell_index++) {
                    // if this cell has a "Use Page" system region, then remember that and break out of loops
                    if (
                        (areas[area]['rows'][row_index]['cells'][cell_index]['region_type'] == 'system')
                        && (areas[area]['rows'][row_index]['cells'][cell_index]['region_name'] == '')
                    ) {
                        use_page_system_region_exists = true;
                        break area_loop;
                    }
                }
            }
        }

        // if a system region does not exist, then alert the user
        if (use_page_system_region_exists == false) {
            alert('Please add one "Use Page" system region before continuing.');
            return false;
        }

        document.getElementById('areas').value = JSON.stringify(areas);
        return true;
    });
}

function generateIndexNowKey() {
    try {
        // UUID üretimi (tarayıcı destekliyorsa)
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
            alert("Your browser does not support secure key generation. Please use a modern browser.");
        }
    } catch (error) {
        console.error("Error generating IndexNow key:", error);
        alert("An error occurred while generating the key. Please try again.");
    }
}

// Handle error state specifically for backup process
function software_backup_handleError(response, backup_btn) {
    software_backup_updateProgress(0, response.message, true);
    backup_btn.text(lang("Retry Backup"))
        .removeClass("disabled")
        .addClass("ready");
}

// Update progress bar and log messages for backup process
function software_backup_updateProgress(percent, message, isError = false) {
    const Progress = $(".progress .progress-bar");
    const Progress_container = $(".progress");
    const LogBox = $(".logbox");

    Progress.attr("style", "width: " + percent + "%");
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
            software_backup_handleError({ message: err.statusText || "Unexpected error" }, backup_btn);
            return;
        }
    }
    location.reload();
}



// Initialize backup process
function software_backup_start() {
    const backup_btn = $("#backup");
    const backup_folder_name = $("#backup_folder_name").val();

    backup_btn.text(lang("Backing up") + "...")
        .addClass("disabled")
        .removeClass("ready");

    software_backup_updateProgress(0, "Backup Builder Starting...");
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
        alert(_L('Label Designer') + ': library not loaded.');
        return;
    }

    var MODAL_ID = 'pg-bc-template-modal';
    var $modal = $('#' + MODAL_ID);

    // ── Build modal HTML once ──────────────────────────────────────────
    if (!$modal.length) {
        $('body').append(
            '<div class="modal fade" id="' + MODAL_ID + '" tabindex="-1" data-bs-backdrop="static" aria-hidden="true">' +
            '<div class="modal-dialog modal-xl">' +
            '<div class="modal-content">' +
            '<div class="modal-header py-2">' +
            '<h5 class="modal-title fs-6 fw-semibold"><i class="bi bi-upc-scan me-2"></i>' + _L('Label Designer') + '</h5>' +
            '<button type="button" class="btn-close" data-bs-dismiss="modal"></button>' +
            '</div>' +
            '<div class="modal-body">' +
            '<div class="mb-3">' +
            '<small class="text-muted d-block mb-2">' + _L('Drag fields to canvas') + ':</small>' +
            '<div id="pg-bc-field-sources" class="d-flex flex-wrap gap-2"></div>' +
            '</div>' +
            '<div id="pg-bc-editor-wrap"></div>' +
            '</div>' +
            '<div class="modal-footer py-2 gap-2">' +
            '<button id="pg-bc-reset-btn" type="button" class="btn btn-outline-secondary btn-sm">' + _L('Reset to Default') + '</button>' +
            '<button data-bs-dismiss="modal" type="button" class="btn btn-secondary btn-sm">' + _L('Cancel') + '</button>' +
            '<button id="pg-bc-save-btn" type="button" class="btn btn-primary btn-sm">' + _L('Save Template') + '</button>' +
            '</div>' +
            '</div>' +
            '</div>' +
            '</div>'
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
        var $chip = $('<span class="badge border border-secondary-subtle text-secondary-emphasis fw-normal px-2 py-1 d-inline-flex align-items-center gap-1" draggable="true" style="cursor:grab;user-select:none;">' +
            '<i class="bi ' + f.icon + '"></i>' + f.label + '</span>');
        $chip[0].addEventListener('dragstart', function (e) {
            e.dataTransfer.setData('pg-field', f.key);
            e.dataTransfer.effectAllowed = 'copy';
        });
        $sources.append($chip);
    });

    // ── Show modal, init editor after it's visible ─────────────────────
    var bsModal = new bootstrap.Modal(document.getElementById(MODAL_ID));

    $modal.one('shown.bs.modal', function () {
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
            $btn.prop('disabled', true).text(_L('Saving...'));
            var templateJson = JSON.stringify(editor.getTemplate());

            var apiUrl = (typeof OUTPUT_PATH !== 'undefined' && typeof SOFTWARE_DIRECTORY !== 'undefined')
                ? OUTPUT_PATH + SOFTWARE_DIRECTORY + '/api.php'
                : 'api.php';

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
                        $btn.prop('disabled', false).text(_L('Save Template'));
                    }
                })
                .catch(function () {
                    alert(_L('Network error.'));
                    $btn.prop('disabled', false).text(_L('Save Template'));
                });
        });
    });

    // Reset save-btn text each time the modal opens
    $modal.one('show.bs.modal', function () {
        var _L2 = (typeof pgLang !== 'undefined') ? pgLang : function (k) { return k; };
        $('#pg-bc-save-btn').prop('disabled', false).text(_L2('Save Template'));
    });

    bsModal.show();
}

/* ════════════════════════════════════════════════════════════════════════
   PRODUCT BARCODE PRINT  (view_products.php)
   Fetches the first barcode for a product and opens a print window.
   Page must set window._pgViewBarcodeOpts = { apiUrl, token, labelTemplate, productMap }.
════════════════════════════════════════════════════════════════════════ */
function pgPrintProductBarcode(productId) {
    var opts = window._pgViewBarcodeOpts || {};
    var p = (opts.productMap || {})[productId] || {};

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
                $(state).text(summary);
                $(note).text(message);
                $(result).removeClass('d-none');
                // The redraw brought a new button with it; the old one is gone
                // from the document, so this re-enables whichever is there now.
                $('#' + action).prop('disabled', false);
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
            $counter = $("<div class='d-flex align-items-center gap-2 mt-1'></div>")
                .attr("id", counterId)
                .insertAfter($input);
        }
        var len = $input.val().length, cls, icon;
        if (len === 0) { cls = "secondary"; icon = ""; }
        else if (len < min) { cls = "warning"; icon = " \u2191"; }
        else if (len <= max) { cls = "success"; icon = " \u2713"; }
        else if (len <= max + 10) { cls = "warning"; icon = " \u2193"; }
        else { cls = "danger"; icon = " \u2717"; }
        var pct = Math.min(100, Math.round(len / (max * 1.25) * 100));
        $counter.html(
            "<small class='text-" + cls + " fw-semibold' style='min-width:60px'>" +
            len + "/" + max + icon +
            "</small>" +
            "<div class='progress flex-grow-1' style='height:3px'>" +
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
    const PG_BARCODE_API = (typeof SOFTWARE_DIRECTORY !== 'undefined')
        ? (typeof OUTPUT_PATH !== 'undefined' ? OUTPUT_PATH : '/') + SOFTWARE_DIRECTORY + '/api.php'
        : 'api.php';

    const PG_TOKEN = (typeof SOFTWARE_TOKEN !== 'undefined') ? SOFTWARE_TOKEN : '';

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
                div.innerHTML = '<div style="font-size:10px;color:#888;padding:2px;text-align:center;">Barcode preview</div>';
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
                div.innerHTML = `<span style="font-size:9px;color:#aaa;">${el.field === 'product_image' ? pgLang('Product Image') : 'Image'}</span>`;
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
                btn.className = 'btn btn-outline-secondary btn-sm';
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
                    rotBtn.className = 'btn btn-outline-secondary btn-sm';
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
                frontBtn.className = 'btn btn-outline-secondary btn-sm';
                frontBtn.innerHTML = '<i class="bi bi-arrow-bar-up"></i>';
                frontBtn.title = pgLang('Bring to Front');
                frontBtn.addEventListener('click', () => this._bringToFront(el.id));
                toolbar.appendChild(frontBtn);

                // Send to back
                const backBtn = document.createElement('button');
                backBtn.type = 'button';
                backBtn.className = 'btn btn-outline-secondary btn-sm';
                backBtn.innerHTML = '<i class="bi bi-arrow-bar-down"></i>';
                backBtn.title = pgLang('Send to Back');
                backBtn.addEventListener('click', () => this._sendToBack(el.id));
                toolbar.appendChild(backBtn);

                // Delete
                const delBtn = document.createElement('button');
                delBtn.type = 'button';
                delBtn.className = 'btn btn-outline-danger btn-sm ms-auto';
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
            props.style.cssText = 'background:#f8f9fa;border:1px solid #dee2e6;border-radius:6px;padding:10px;font-size:13px;';

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
                const slider = document.createElement('input');
                slider.type = 'range'; slider.min = -200; slider.max = 200; slider.value = el.scrollX || 0;
                slider.style.width = '100%';
                slider.addEventListener('input', () => { el.scrollX = +slider.value; this.render(); this._fireChange(); });
                scrollWrap.appendChild(slider);
                props.appendChild(scrollWrap);

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
                    note.style.cssText = 'margin-top:6px;font-size:12px;color:#6c757d;padding:4px 0;';
                    note.innerHTML = `<i class="bi bi-image me-1"></i>${pgLang('Product Image')} <span class="text-muted fst-italic">(${pgLang('Dynamic (changes per product)')})</span>`;
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
                inp.placeholder = 'e.g. uploads/logo.png';
                inp.addEventListener('change', () => { el.src = inp.value; self.render(); self._fireChange(); });
                imgRow.appendChild(inp);

                // File picker button — uses Pinegrap system picker if available
                const pick = document.createElement('button');
                pick.type = 'button'; pick.className = 'btn btn-outline-secondary btn-sm mt-1';
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
            div.style.cssText = 'background:#f8f9fa;border:1px solid #dee2e6;border-radius:6px;padding:10px;font-size:13px;';
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
            inp.style.cssText = 'flex:1;min-width:0;height:24px;border:1px solid #ced4da;border-radius:4px;padding:0 4px;font-size:12px;';
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
            sel.style.cssText = 'flex:1;min-width:0;height:24px;border:1px solid #ced4da;border-radius:4px;padding:0 4px;font-size:12px;';
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
                svgPreview.innerHTML = '<text x="5" y="20" fill="red" font-size="11">Invalid barcode</text>';
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
            if (!barcode) { setStatus(pgLang('Barcode value is required.'), 'text-danger'); return; }
            if (triggerBtn) triggerBtn.disabled = true;
            setStatus(pgLang('Saving...'), 'text-muted');
            api('save_product_barcode', { product_id: productId, barcode, barcode_type: type, token })
                .then(res => {
                    setStatus(res.message || (res.status === 'success' ? pgLang('Barcode saved.') : pgLang('Error.')),
                        res.status === 'success' ? 'text-success' : 'text-danger');
                    if (res.status === 'success') {
                        currentBarcode = barcode;
                        currentType = type;
                        // Refresh count badge
                        api('get_product_barcodes', { product_id: productId, token }).then(r => {
                            if (r.status === 'success') updateCount(r.barcodes.length);
                        });
                    }
                }).catch(() => setStatus(pgLang('Network error.'), 'text-danger'))
                .finally(() => { if (triggerBtn) triggerBtn.disabled = false; });
        }

        // Generate — auto-saves
        if (btnGenerate) {
            btnGenerate.addEventListener('click', () => {
                btnGenerate.disabled = true;
                setStatus(pgLang('Generating...'), 'text-muted');
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
                        setStatus(res.message || pgLang('Error generating barcode.'), 'text-danger');
                    }
                }).catch(() => setStatus(pgLang('Network error.'), 'text-danger'))
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
                    '<div class="modal fade" id="' + BLIST_ID + '" tabindex="-1"><div class="modal-dialog">' +
                    '<div class="modal-content"><div class="modal-header py-2">' +
                    '<h6 class="modal-title"><i class="bi bi-upc-scan me-2"></i>' + pgLang('Barcodes') + '</h6>' +
                    '<button type="button" class="btn-close" data-bs-dismiss="modal"></button>' +
                    '</div><div class="modal-body" id="pg-blist-body"><div class="text-center py-3"><div class="spinner-border spinner-border-sm"></div></div></div>' +
                    '</div></div></div>'
                );
                $modal = $('#' + BLIST_ID);
            }
            const bsModal = new bootstrap.Modal(document.getElementById(BLIST_ID));
            bsModal.show();
            loadBarcodeList();
        }

        function loadBarcodeList() {
            const body = document.getElementById('pg-blist-body');
            if (!body) return;
            api('get_product_barcodes', { product_id: productId, token }).then(res => {
                if (res.status !== 'success') { body.innerHTML = '<p class="text-danger p-2">' + (res.message || pgLang('Error.')) + '</p>'; return; }
                if (!res.barcodes.length) {
                    body.innerHTML = '<p class="text-muted p-3 mb-0">' + pgLang('No barcodes assigned to this product.') + '</p>';
                    updateCount(0);
                    return;
                }
                updateCount(res.barcodes.length);
                let html = '<ul class="list-group list-group-flush">';
                res.barcodes.forEach(bc => {
                    html += '<li class="list-group-item d-flex align-items-center gap-2 py-2" data-bc-id="' + bc.id + '">' +
                        '<span class="font-monospace small flex-grow-1">' + h(bc.barcode) + '</span>' +
                        '<span class="badge bg-secondary">' + h(bc.barcode_type) + '</span>' +
                        '<button type="button" class="btn btn-sm btn-outline-success py-0 px-1 pg-bc-print" title="' + pgLang('Print') + '" data-bc="' + h(bc.barcode) + '" data-type="' + h(bc.barcode_type) + '"><i class="bi bi-printer"></i></button>' +
                        '<button type="button" class="btn btn-sm btn-outline-danger py-0 px-1 pg-bc-del" title="' + pgLang('Delete') + '" data-id="' + bc.id + '"><i class="bi bi-trash"></i></button>' +
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
            }).catch(() => { if (body) body.innerHTML = '<p class="text-danger p-2">' + pgLang('Network error.') + '</p>'; });
        }

        function h(str) { return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;'); }

        const btnListModal = document.getElementById('pg-btn-barcode-list');
        if (btnListModal) btnListModal.addEventListener('click', openBarcodeListModal);

        // Print helper (shared by card print btn and list modal)
        function printBarcode(barcode, type) {
            if (!barcode) { setStatus(pgLang('Save a barcode first before printing.'), 'text-danger'); return; }
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
                if (!barcode) { setStatus(pgLang('Save a barcode first before printing.'), 'text-danger'); return; }
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
       SIMPLE LANG SHIM (falls back to key if window.pgLang not defined)
    ═══════════════════════════════════════════════════════════════════ */
    window.pgLang = window.pgLang || function (key) { return key; };

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

        // Use software_token defined globally
        var token = '';
        if (typeof SOFTWARE_TOKEN !== 'undefined') {
            token = SOFTWARE_TOKEN;
        } else if (typeof software_token !== 'undefined') {
            token = software_token;
        }

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

    setTimeout(function () {

        $.ajax({
            contentType: "application/json",
            url: pg_api_url(),
            data: JSON.stringify({
                action: "sitemap_check"
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
            c.className = 'toast-container position-fixed top-0 end-0 p-3';
            c.style.zIndex = '1090';
            c.setAttribute('aria-live', 'polite');
            c.setAttribute('aria-atomic', 'true');
            document.body.appendChild(c);
        }
        return c;
    }

    /**
     * pgToast({message, variant, delay})
     * variant: success | danger | warning | info (default: info)
     * delay: ms (default: 4000)
     */
    window.pgToast = function (opts) {
        opts = opts || {};
        var message = opts.message != null ? String(opts.message) : '';
        var variantWhitelist = { success: 1, danger: 1, warning: 1, info: 1 };
        var variant = variantWhitelist[opts.variant] ? opts.variant : 'info';
        var delay = typeof opts.delay === 'number' ? opts.delay : 4000;

        var iconMap = {
            success: 'bi-check-circle-fill',
            danger: 'bi-exclamation-triangle-fill',
            warning: 'bi-exclamation-circle-fill',
            info: 'bi-info-circle-fill'
        };

        var container = ensureToastContainer();
        var el = document.createElement('div');
        el.className = 'toast align-items-center text-bg-' + variant + ' border-0';
        el.setAttribute('role', variant === 'danger' ? 'alert' : 'status');
        el.setAttribute('aria-live', variant === 'danger' ? 'assertive' : 'polite');
        el.setAttribute('aria-atomic', 'true');
        el.innerHTML =
            '<div class="d-flex">' +
                '<div class="toast-body">' +
                    '<i class="bi ' + iconMap[variant] + ' me-2" aria-hidden="true"></i>' +
                    escapeHtml(message) +
                '</div>' +
                '<button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>' +
            '</div>';
        container.appendChild(el);

        if (typeof bootstrap !== 'undefined' && bootstrap.Toast) {
            var t = new bootstrap.Toast(el, { delay: delay });
            el.addEventListener('hidden.bs.toast', function () { el.remove(); });
            t.show();
            return t;
        } else {
            // graceful degradation
            el.classList.add('show');
            setTimeout(function () { el.remove(); }, delay);
            return null;
        }
    };

    /**
     * pgConfirm({title, message, confirmText, cancelText, variant}): Promise<bool>
     * variant: danger (default) | primary | warning
     */
    window.pgConfirm = function (opts) {
        opts = opts || {};
        var title = opts.title != null ? String(opts.title) : 'Confirm';
        var message = opts.message != null ? String(opts.message) : '';
        var confirmText = opts.confirmText != null ? String(opts.confirmText) : 'OK';
        var cancelText = opts.cancelText != null ? String(opts.cancelText) : 'Cancel';
        var variantWhitelist = { danger: 1, primary: 1, warning: 1, success: 1 };
        var variant = variantWhitelist[opts.variant] ? opts.variant : 'danger';

        return new Promise(function (resolve) {
            var modalEl = document.createElement('div');
            modalEl.className = 'modal fade';
            modalEl.tabIndex = -1;
            modalEl.setAttribute('aria-labelledby', 'pg-confirm-title');
            modalEl.setAttribute('aria-modal', 'true');
            modalEl.setAttribute('role', 'dialog');
            modalEl.innerHTML =
                '<div class="modal-dialog modal-dialog-centered">' +
                    '<div class="modal-content">' +
                        '<div class="modal-header">' +
                            '<h5 class="modal-title" id="pg-confirm-title">' + escapeHtml(title) + '</h5>' +
                            '<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>' +
                        '</div>' +
                        '<div class="modal-body">' + escapeHtml(message) + '</div>' +
                        '<div class="modal-footer">' +
                            '<button type="button" class="btn btn-secondary" data-pg-action="cancel" data-bs-dismiss="modal">' + escapeHtml(cancelText) + '</button>' +
                            '<button type="button" class="btn btn-' + variant + '" data-pg-action="confirm">' + escapeHtml(confirmText) + '</button>' +
                        '</div>' +
                    '</div>' +
                '</div>';
            document.body.appendChild(modalEl);

            var resolved = false;
            function done(value) {
                if (resolved) return;
                resolved = true;
                resolve(value);
            }

            if (typeof bootstrap === 'undefined' || !bootstrap.Modal) {
                // Fallback: native confirm if Bootstrap not loaded
                modalEl.remove();
                done(window.confirm(message));
                return;
            }

            var modal = new bootstrap.Modal(modalEl, { backdrop: 'static', keyboard: true });
            modalEl.querySelector('[data-pg-action="confirm"]').addEventListener('click', function () {
                done(true);
                modal.hide();
            });
            modalEl.addEventListener('hidden.bs.modal', function () {
                done(false); // dismiss/cancel/escape -> false
                modalEl.remove();
            });
            modal.show();
            // Move focus to confirm button after show, for keyboard users
            modalEl.addEventListener('shown.bs.modal', function () {
                var btn = modalEl.querySelector('[data-pg-action="confirm"]');
                if (btn) btn.focus();
            });
        });
    };

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

            // Faz 3c: inline per-field feedback from window.__pgFieldErrors map
            var errs = window.__pgFieldErrors;
            if (!errs || el.dataset.pgFeedbackInjected === '1') return;
            var name = el.getAttribute('name');
            if (!name) return;
            // Strip [] suffix used for array-valued inputs
            var key = name.replace(/\[\]$/, '');
            var msg = errs[name] || errs[key];
            if (!msg) return;
            // Avoid duplicate injection if the page already renders an invalid-feedback sibling
            var sibling = el.nextElementSibling;
            if (sibling && sibling.classList && sibling.classList.contains('invalid-feedback')) {
                el.dataset.pgFeedbackInjected = '1';
                return;
            }
            var feedback = document.createElement('div');
            feedback.className = 'invalid-feedback d-block pg-inline-feedback';
            feedback.textContent = msg;
            var fid = el.id || ('pg-field-' + Math.random().toString(36).slice(2, 8));
            if (!el.id) el.id = fid;
            feedback.id = fid + '-feedback';
            if (!el.hasAttribute('aria-describedby')) {
                el.setAttribute('aria-describedby', feedback.id);
            }
            el.insertAdjacentElement('afterend', feedback);
            el.dataset.pgFeedbackInjected = '1';
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
            if (label && !label.querySelector('.pg-required-marker')) {
                var span = document.createElement('span');
                span.className = 'pg-required-marker text-danger ms-1';
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

    // Expose for callers that render forms after initial load.
    window.pgInjectAriaAndRequiredMarkers = pgInjectAriaAndRequiredMarkers;
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
                    if (panel && window.bootstrap && bootstrap.Offcanvas) {
                        bootstrap.Offcanvas.getOrCreateInstance(panel).show();
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
        panel.addEventListener('show.bs.offcanvas', function () {
            document.body.classList.add('pg-side-panel-open');
        });
        panel.addEventListener('hidden.bs.offcanvas', function () {
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
        chip.className = 'pg-chip';
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

    // Read once: the text of a card does not change as it is typed over, and
    // reading it per keystroke on the commerce screen is a hundred kilobytes of
    // textContent per character.
    cards.forEach(function (card) {
        card.setAttribute('data-pgtext', (card.textContent || '').replace(/\s+/g, ' ').toLowerCase());
    });

    field.addEventListener('input', function () {

        var needle = field.value.trim().toLowerCase();

        cards.forEach(function (card) {
            card.classList.toggle('d-none', (needle !== '') && (card.getAttribute('data-pgtext').indexOf(needle) === -1));
        });
    });
}


/* ────────────────────────────────────────────────────────────────────────────
   MARKUP THAT ARRIVED AFTER THE PAGE DID

   The handlers in the ready block above bind to the elements that existed when
   the page loaded. Anything fetched later -- a pane of the settings modal --
   has to be given the same treatment, so the parts that can be re-run over a
   scope live in these helpers and both callers use them: the ready block runs
   them over the document, the settings modal over its pane.

   What is NOT here: CodeMirror, the tag fields and the rest of the controls
   whose init sits in an inline <script> beside the control. jQuery's .html()
   runs the scripts it injects, so those start themselves -- which is why a
   pane is injected with .html() and never with innerHTML.
   ──────────────────────────────────────────────────────────────────────────── */

// Bootstrap binds ONE component instance per element. A dropdown, tab or
// button toggle owns its element's slot; a popover bound to it first takes the
// slot instead, the toggle's own instance is refused ("doesn't allow more than
// one instance per element") and the menu it opens can never be closed by the
// document click handler. Those toggles keep the native title tooltip.
var _pgNoTitlePopover = ':not([data-bs-toggle="dropdown"]):not([data-bs-toggle="tab"]):not([data-bs-toggle="pill"]):not([data-bs-toggle="button"]):not([data-bs-toggle="tooltip"]):not([data-bs-toggle="popover"])';


/**
 * The software's own tooltips, over one scope.
 */
function pgBindTitlePopovers(scope) {

    var $scope = $(scope || document);

    $scope.find('[title]:not(.popover-click):not(.no-popover)' + _pgNoTitlePopover).popover({
        trigger: 'hover',
        placement: 'auto',
        html: true,
        container: 'body'
    });

    $scope.find('.popover-click[title]:not(.no-popover)' + _pgNoTitlePopover).popover({
        trigger: 'click',
        placement: 'auto',
        html: true,
        container: 'body'
    });
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

        if ($(target).hasClass('popover')) {
            $(target).toggleClass('show');
        }
    });

    $scope.find('select.collapse-if-selected').each(function () {

        var target = $(this).attr('data-bs-target');

        $(target).collapse(($(this).val() === '') ? 'hide' : 'show');
    });
}


/**
 * Everything a freshly injected piece of the panel still needs.
 */
function pgInitInjected(scope) {

    var $scope = $(scope);

    if (!$scope.length) {
        return;
    }

    pgBindTitlePopovers($scope);
    pgSyncCollapseSwitchers($scope);

    if ($.fn.inputmask) {
        $scope.find('.input-mask-key-code').inputmask({
            mask: '9999-9999-9999-9999',
            placeholder: '****-****-****-****',
            showMaskOnHover: false,
            showMaskOnFocus: false
        });
    }

    // The timepicker addon and its wording arrive with the card that uses it,
    // as a <script src> inside the markup, so neither can be assumed.
    if ($.fn.timepicker && (typeof timepicker_options !== 'undefined')) {
        $scope.find('input.timepicker').timepicker(timepicker_options);
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

    var pane = '';              // the category on screen
    var dirty = false;
    var baseline = '';          // the whole pane, as it arrived
    var held = null;            // the same, control by control
    var heldSet = null;         // and the membership question, asked often
    var gesture = 0;            // when a person last did something in here
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

    // .modal-content.pg-sm -- the box the sidebar, the pane and the drawer
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

        modal = new bootstrap.Modal(el);

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
        el.addEventListener('show.bs.modal', function () {

            document.body.classList.add('pg-side-panel-open');

            // Says WHICH dialog is open, which the class above cannot: the
            // designer's side panel shares it. The stylesheet lightens the
            // scrim behind a frosted settings dialog and must not lighten it
            // behind anything else.
            document.body.classList.add('pg-settings-open');

            window.clearInterval(patrolling);
            patrolling = window.setInterval(patrol, 600);
        });

        el.addEventListener('hidden.bs.modal', function () {

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
        // plain save plain.
        $(el).on('click', '#pg_settings_modal_pane [type="submit"]', function () {

            pressed = (this.name && !this.disabled)
                ? { name: this.name, value: this.value }
                : null;
        });

        // Closing with unsaved changes is the one thing worth interrupting for.
        el.addEventListener('hide.bs.modal', function (event) {

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

        // Escape closes the drawer before it closes the dialog. Bound to the
        // document in the capture phase because Bootstrap's own Escape handler
        // sits on the dialog element and was registered first.
        document.addEventListener('keydown', function (event) {

            if ((event.key !== 'Escape') || !el.classList.contains('show') || !drawerOpen()) {
                return;
            }

            event.stopPropagation();
            drawer(false);

        }, true);

        $(el).on('click', '#pg_settings_modal_save', save);

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

            if (!target._pgStacked) {

                target._pgStacked = true;

                // Closing the inner dialog takes the body's modal-open class
                // with it; the settings are still open and still need it, or
                // the screen behind starts scrolling again.
                target.addEventListener('hidden.bs.modal', function () {
                    if (el.classList.contains('show')) {
                        document.body.classList.add('modal-open');
                    }
                });
            }

            bootstrap.Modal.getOrCreateInstance(target).show();
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
            review();
        }, true);

        document.addEventListener('keydown', function (event) {
            unlatch(event.target);
            gesture = Date.now();
            review();
        }, true);

        // Reaching a field with the keyboard is reaching for it too, and the
        // release has to happen before the plugins that bind on focus -- the
        // input masks and the time picker -- get a look at it.
        document.addEventListener('focusin', function (event) {
            unlatch(event.target);
        }, true);

        $(el).on('paste drop', function () {
            gesture = Date.now();
            review();
        });

        // Something in the pane changed. Whether that counts is review()'s
        // question; CodeMirror's own input events reach here the same way.
        $('#pg_settings_modal_pane').on('change input', review);

        // Return in a text field submits a form. There is nowhere for this one
        // to go, so it saves instead.
        $(form).on('submit', function (event) {
            event.preventDefault();
            save();
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
     * The pane's whole value as one string.
     *
     * CodeMirror keeps its own document and only writes back to the textarea
     * when told to, so a code field has to be pushed down before the form can
     * be read at all.
     */
    function snapshot() {

        if (!form) {
            return '';
        }

        $(form).find('.CodeMirror').each(function () {
            if (this.CodeMirror) {
                this.CodeMirror.save();
            }
        });

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
     * hidden inputs and the textarea CodeMirror writes into -- all three are
     * written by this software after the reading below, and putting them back
     * would be fighting ourselves.
     */
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
     * Called again on every review for the controls that were not on screen
     * when the pane arrived -- inside a block the operator has since opened.
     * Nothing could have filled them while they were hidden, so their value as
     * they appear is the one to put back.
     */
    function keep(control) {

        if (!held || (control.type === 'hidden') || (control.offsetParent === null) || heldSet.has(control)) {
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
     * Debounced because the comparison pushes every CodeMirror document into
     * its textarea, and a code field can hold a lot of text.
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

            if (mine) {
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
     * Debounced because the comparison pushes every CodeMirror document into
     * its textarea, and a code field can hold a lot of text.
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

        $('#pg_settings_modal_extras .modal').each(function () {

            var instance = bootstrap.Modal.getInstance(this);

            if (instance) {
                instance.dispose();
            }
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
        var alert = $('<div class="alert alert-dismissible show mb-3" role="alert"></div>')
            .addClass(bad ? 'alert-danger' : 'alert-success')
            .text(said);

        $('<button type="button" class="btn-close no-popover" data-bs-dismiss="alert"></button>')
            .attr('aria-label', say('close'))
            .appendTo(alert);

        $(box).empty().append(alert);
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

            paint(key, response);

        }).fail(function (xhr) {

            if (mine !== seq) {
                return;
            }

            notice(complaint(xhr), true);
            expired(xhr);

        }).always(function () {

            busy--;

            if (mine !== seq) {
                return;
            }

            $(form).removeClass('is-loading');
            $(shell()).removeClass('is-busy');
            setDirty(false);
        });
    }


// Lets go of the CodeMirror editors inside a region that is about to be thrown
// away.
//
// CodeMirror's autoRefresh addon waits for a hidden editor to become visible by
// reading offsetHeight every 250 ms, and re-arms itself for as long as the
// answer is zero. An editor whose markup was replaced, or whose dialog was
// closed, is never visible again - so it is polled for the life of the tab. It
// also binds mouseup and keyup on window, which are never removed either, so
// every later click runs one handler per abandoned editor.
//
// The symptom is not an error and not a crash: the tab keeps scrolling, because
// scrolling is the compositor's job and the compositor is free, while clicks and
// context menus stop because those need the main thread. Chrome never calls the
// page unresponsive, because no single task is long - there are just always
// more of them. Same class as the Chart.js rule: markup that owns a live object
// cannot simply be dropped.
//
// Turning the option off is what the addon listens for; it stops the poll and
// removes the handlers it put on window. toTextArea() does not do this - it
// destroys the editor and leaves the addon polling a wrapper that is no longer
// in the document.
window.pgReleaseEditors = function (scope) {

    var root = scope || document;

    if (!root || !root.querySelectorAll) {
        return;
    }

    var wrappers = root.querySelectorAll('.CodeMirror');

    for (var i = 0; i < wrappers.length; i++) {
        var editor = wrappers[i].CodeMirror;

        if (!editor) {
            continue;
        }

        try { editor.setOption('autoRefresh', false); } catch (e) {}
    }
};
    function paint(key, response) {

        pane = key;

        byId('pg_settings_modal_title').textContent = response.title || '';
        byId('pg_settings_modal_desc').textContent = response.description || '';
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
        // CodeMirror and the tag fields, and .html() runs them.
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

        // CodeMirror edits its own document and leaves the textarea alone, so
        // a code field would otherwise never reach the comparison.
        $pane.find('.CodeMirror').each(function () {
            if (this.CodeMirror) {
                this.CodeMirror.on('change', review);
            }
        });

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

        // CodeMirror keeps its own document and only writes back to the
        // textarea when told to, so serialize() would post the value the pane
        // arrived with.
        $(form).find('.CodeMirror').each(function () {
            if (this.CodeMirror) {
                this.CodeMirror.save();
            }
        });
    }


    function save() {

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
        var button = pressed;

        pressed = null;

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
