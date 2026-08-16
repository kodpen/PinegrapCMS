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
    // if necessary.  We had to add the click event in order for the keyboard
    // shortcut (Ctrl+G) for the page designer to work.
    var page_designer_button = $('#button_bar .page_designer_button');

    // If a page designer button was found, and we are not already
    // in the page designer, then add click event.
    // We don't want to add a click event if we are already in the page
    // designer because the click event will conflict with the click event
    // that the page designer adds.
    if (
        (page_designer_button.length) &&
        (
            (check_iframe_access(parent.parent) == false) ||
            ($(parent.parent.document).find('.page_designer').length == 0)
        )
    ) {
        page_designer_button.click(function (event) {
            event.preventDefault();
            window.parent.location = page_designer_button[0].href;
        });
    }

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


    $(".ui-sortable").sortable({
        disable: '.no-sortable',
        tolerance: 'touch',
        containment: "parent",
        helper: 'clone',
        handle: this,
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



    options["dom"] = "<'container p-3 pt-4'<'row'<'col-12 col-md-6 text-center text-md-start'li><'col-12 col-md-6 text-center text-md-end'BRf>>>rt<'p-2 text-center'i><'p-2'p>";
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

    check_unread_notifications('onload_check');
    setInterval(function () {
        //this interval is auto check for notifications.
        // we set this default 30 sec(30 * 1000).
        check_unread_notifications('scheduled_check');
    }, 20 * 1000); // 60 * 1000 = 1min.



    $('[title]:not(.popover-click):not(.no-popover)').popover({
        trigger: 'hover',
        placement: 'auto',
        html: true,
        container: 'body'

    });
    $('.popover-click[title]:not(.no-popover)').popover({
        trigger: ' click',
        placement: 'auto',
        html: true,
        container: 'body'

    });



    if ($("input.collapse-switcher").length > 0) {
        $("input.collapse-switcher").each(function () {
            var target = $(this).attr("data-bs-target");
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
            if ($(target).hasClass('popover')) {
                $(target).toggleClass("show");
            }
        });
    }
    if ($("select.collapse-if-selected").length > 0) {
        $("select.collapse-if-selected").each(function () {
            var target = $(this).attr("data-bs-target");
            if ($(this).val() == '') {
                $(target).collapse("hide");
            } else {
                $(target).collapse("show");
            }
        });
    }

    $("select.collapse-if-selected").on("change", function () {
        var target = $(this).attr("data-bs-target");
        if ($(this).val() == '') {
            $(target).collapse("hide");
        } else {
            $(target).collapse("show");
        }
    });
    $("input.collapse-switcher").on("click change", function () {
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

    var header = $(".header-content-for-add-page").text();
    $('input.add-header-content-updater').bind('input', function () {
        if ($(this).val().length > 5) {
            $(".header-content-for-add-page").text('[' + $(this).val() + ']');
        }

        if ($(this).val().length < 5 || $(this).val().length > 60) {
            $(".header-content-for-add-page").text(header);
        }
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
    $('#EnableSearch').removeClass('d-none');

    (function () {
        var $modal = $('#SearchBox');
        var $input = $modal.find('input[name="query"]');
        var $results = $modal.find('#search_results');
        var $emptyBox = $modal.find('.search_results_empty_box');
        var $clearBtn = $modal.find('.clear');
        var timer, currentQuery = '', currentOffset = 0, loading = false;

        var cachedActions = null;

        function t(key) {
            var v = (typeof translate !== 'undefined') ? translate[key] : undefined;
            return (v !== undefined && v !== null) ? v : key;
        }

        function normalizeTR(s) {
            return s.toLowerCase()
                .replace(/ğ/g, 'g').replace(/ü/g, 'u').replace(/ş/g, 's')
                .replace(/ı/g, 'i').replace(/ö/g, 'o').replace(/ç/g, 'c');
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
                $btn.on('click', function () { $modal.modal('hide'); window.location.href = meta.url(r); });
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
                                $a.on('click', function () { $modal.modal('hide'); });
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

                    var output_target = 'location.href';
                    if (window.location.href.indexOf("toolbar.php") > -1) {
                        output_target = 'parent.document.location.href';
                    }
                    var output_button_bar = '';
                    while (i < data_length) {
                        response.data[i].description = '<div class="notification-description bg-body-secondary" style="--bs-bg-opacity: 0.2;">' + response.data[i].description + '</div>';
                        response.data[i].details = '<div notification-details>' + response.data[i].details + '</div>';
                        if (response.data[i].action != 'custom') {
                            output_button_bar = '<button type="button" class="dropdown-item bi bi-link bi-me-2 text-primary-emphasis" onclick="' + output_target + '=\'' + response.data[i].url + '\'">' + lang('Go to the relevant page') + '</button>';


                            output_button_bar += '<button type="button" class="fs-smaller dropdown-item bi bi-check-square bi-me-2 text-secondary-emphasis" onclick="edit_notification(\'' + response.data[i].id + '\', \'mark_unread\'); this.remove();">' + lang('Mark as unread') + '</button>';

                            output_data_title = '<small class="notification-title" title="' + response.data[i].title + '">' + response.data[i].title + '</small>';
                        } else {
                            output_data_title = '<div class="notification-title text-wrap w-100">' + response.data[i].title + '</div>';
                        }


                        if (response.data[i].readed != 1) {
                            response.data[i].readed = 'new';
                        } else {
                            response.data[i].readed = '';
                        }

                        $output_border_classes = '';
                        if (i > 0) {
                            $output_border_classes = ' border-top border-secondary';

                        }

                        //we complete notification skeleton and output it before clear notification button.
                        // OUTPUT
                        output_notifications += '\
                        <li notification-id="' + response.data[i].id + '" class="software_notification custom-contextmenu position-relative list-group-item border-0 p-0 viewed bg-transparent ' + response.data[i].readed + ' ' + response.data[i].type + '">\
                            <div class="container-fluid">\
                            <div class="row notification-card ' + $output_border_classes + '" style="--bs-border-opacity:0.08;">\
                                    <div class="col-12 ps-4 p-2 border-bottom border-secondary " style="--bs-border-opacity:0.04;">\
                                        <div class="d-flex w-100 justify-content-between">\
                                            ' + output_data_title + '\
                                        </div>\
                                        <small>' + response.data[i].description + response.data[i].details + '</small>\
                                        <div class="d-flex w-100 justify-content-between">\
                                            <span class="me-auto badge bg-transparent text-reset fw-light text-overflow-hidden">' + response.data[i].user + '</span>\
                                            <span class="ms-auto badge bg-transparent text-reset fw-light">' + response.data[i].time + '</span>\
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

function show_or_hide_view_expiration_date(folder_id) {
    if (document.getElementById('view_' + folder_id).checked == true) {
        document.getElementById('view_' + folder_id + '_expiration_date_container').style.display = '';
        $('#view_' + folder_id + '_expiration_date').datepicker(datetimepicker_options);

    } else {
        document.getElementById('view_' + folder_id + '_expiration_date_container').style.display = 'none';
        $('#view_' + folder_id + '_expiration_date').datepicker('destroy');
    }
}

function show_or_hide_contact_group_access() {
    if ((document.getElementById('manage_contacts').checked == true) || (document.getElementById('manage_emails').checked == true)) {
        $('#contact_group_access').addClass('show');
    } else {
        $('#contact_group_access').removeClass('show');
    }
}

function change_user_role(user_role) {
    // if user role was selected, then show certain access fields
    if (user_role == 3) {
        $('#user_has_all_permissions').removeClass('show');
        $('#manage_ecommerce_heading_row').addClass('show');
        $('#manage_ecommerce_row').addClass('show');
        $('#manage_calendars_heading_row').addClass('show');
        $('#manage_calendars_row').addClass('show');
        $('#manage_visitors_row').addClass('show');
        $('#manage_visitors_heading_row').addClass('show');
        $('#shared_content_access_rights_heading_row').addClass('show');
        $('#edit_access_heading_row').addClass('show');
        $('#edit_access_row').addClass('show');
        $('#common_regions_access_row').addClass('show');
        $('#menus_access_row').addClass('show');
        $('#manage_contacts_and_manage_emails_row').addClass('show');
        $('#manage_contacts_and_manage_emails_heading_row').addClass('show');
        show_or_hide_contact_group_access();
        $('#manage_ad_regions_heading_row').addClass('show');
        $('#manage_ad_regions_row').addClass('show');
        $('#view_access_heading_row').addClass('show');
        $('#view_access_row').addClass('show');

        // else administrator, designer, or manager role was selected, so hide certain access fields
    } else {
        $('#user_has_all_permissions').addClass('show');
        $('#manage_ecommerce_heading_row').removeClass('show');
        $('#manage_ecommerce_row').removeClass('show');
        $('#manage_calendars_heading_row').removeClass('show');
        $('#manage_calendars_row').removeClass('show');
        $('#manage_visitors_row').removeClass('show');
        $('#manage_visitors_heading_row').removeClass('show');
        $('#shared_content_access_rights_heading_row').removeClass('show');
        $('#edit_access_heading_row').removeClass('show');
        $('#edit_access_row').removeClass('show');
        $('#common_regions_access_row').removeClass('show');
        $('#menus_access_row').removeClass('show');
        $('#manage_contacts_and_manage_emails_heading_row').removeClass('show');
        $('#manage_contacts_and_manage_emails_row').removeClass('show');
        $('#manage_ad_regions_heading_row').removeClass('show');
        $('#manage_ad_regions_row').removeClass('show');
        $('#view_access_heading_row').removeClass('show');
        $('#view_access_row').removeClass('show');
    }
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
        else if (len < min) { cls = "warning"; icon = " ↑"; }
        else if (len <= max) { cls = "success"; icon = " ✓"; }
        else if (len <= max + 10) { cls = "warning"; icon = " ↓"; }
        else { cls = "danger"; icon = " ✗"; }
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
            this.dimLabel.textContent = lbl.width + ' × ' + lbl.height + ' mm';
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
                    rotBtn.title = pgLang('Rotate 90°');
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
            title.textContent = el.type + (el.field ? ' — ' + el.field : '');
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
   USER ONLINE HEARTBEAT (Runs on all backend pages)
════════════════════════════════════════════════════════════════════════ */
(function () {
    function sendHeartbeat() {
        var apiUrl = (typeof OUTPUT_PATH !== 'undefined' && typeof SOFTWARE_DIRECTORY !== 'undefined')
            ? OUTPUT_PATH + SOFTWARE_DIRECTORY + '/api.php'
            : 'api.php';

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
    setTimeout(function () {

        $.ajax({
            contentType: "application/json",
            url: 'api.php',
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
    // Software Update Check ASYNC Trigger
    if (typeof software_update_check_needed !== 'undefined' && software_update_check_needed == true) {
        setTimeout(function () {

            $.ajax({
                contentType: "application/json",
                url: 'api.php',
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
   Faz 2: additive infrastructure; existing code is not migrated yet.
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
