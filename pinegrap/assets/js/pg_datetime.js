/**
 * Pinegrap - Enterprise Website Platform
 *
 * The one date-and-time control: our own parse and format of the site's
 * date and time text, and a calendar on the vendored vanilla-calendar-pro
 * (assets/lib/vanilla-calendar-pro/index.js), with no jQuery and no
 * Bootstrap. The panel's hooks in pg_fields.js draw their fields with it.
 *
 * Values cross this file as ISO text ('2026-10-26', '17:00',
 * '2026-10-26 17:00'), parsed and formatted from the site date order, never
 * by Date() parsing: Date() takes 5/3/2026 as May on a day-first site.
 *
 *   pgDateTime.parseDate(text, order) / formatDate(iso, order)
 *   pgDateTime.parseTime(text) / formatTime(hhmm)
 *   pgDateTime.parseDateTime(text, order) / formatDateTime(iso, order)
 *   pgDateTime.order()                    'day_month' or 'month_day'
 *   pgDateTime.clockTime(hhmm)            '17:05' on the site's clock ('5:05 PM' with 12 hours)
 *   pgDateTime.timeMode()                 12 or 24, from the data-pg-calendar of its tag
 *   pgDateTime.ready() / whenReady(fn)    the calendar library is loaded
 *   pgDateTime.attach(box, options)       a calendar for the typed box `box`: a date, a
 *                                         date and time (the time row under the dates)
 *                                         or a time (the time row alone)
 *
 * The library is loaded once, by this file, from the data-pg-calendar-src
 * of its own tag: a settings card that brings the tags again runs this file
 * a second time, and the library itself has no such guard.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */
(function (window, document) {
    'use strict';

    if (window.pgDateTime) {
        return;
    }

    function pad(n) {
        return ('0' + n).slice(-2);
    }

    // ------------------------------------------------------------ formats

    // 'month_day' or 'day_month', from the format the screen printed with
    // get_date_picker_format() ('m/d/yy' or 'd/m/yy').
    function dateOrder() {
        var f = window.date_picker_format
            || (window.datetimepicker_options && window.datetimepicker_options.dateFormat)
            || 'd/m/yy';
        return /^m/i.test(String(f)) ? 'month_day' : 'day_month';
    }

    function validDate(y, m, d) {
        if (m < 1 || m > 12 || d < 1) {
            return false;
        }
        var days = [31, (y % 4 === 0 && (y % 100 !== 0 || y % 400 === 0)) ? 29 : 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];
        return d <= days[m - 1];
    }

    // Site text ('26/10/2026', '10/26/2026'; '-' and '.' are read as '/') to
    // '2026-10-26'; '' when it is not a whole, real date.
    function parseDate(text, order) {
        var p = String(text || '').trim().split(/[\/\-.]/);
        if (p.length !== 3 || !/^\d{4}$/.test(p[2]) || !/^\d{1,2}$/.test(p[0]) || !/^\d{1,2}$/.test(p[1])) {
            return '';
        }
        var y = parseInt(p[2], 10);
        var m = parseInt((order || dateOrder()) === 'month_day' ? p[0] : p[1], 10);
        var d = parseInt((order || dateOrder()) === 'month_day' ? p[1] : p[0], 10);
        return validDate(y, m, d) ? (p[2] + '-' + pad(m) + '-' + pad(d)) : '';
    }

    // '2026-10-26' to the site text without leading zeros, as the picker
    // writes it ('26/10/2026'); '' for anything else.
    function formatDate(iso, order) {
        var m = /^(\d{4})-(\d{2})-(\d{2})$/.exec(String(iso || ''));
        if (!m || !validDate(+m[1], +m[2], +m[3])) {
            return '';
        }
        var d = String(+m[3]), mo = String(+m[2]);
        return ((order || dateOrder()) === 'month_day' ? (mo + '/' + d) : (d + '/' + mo)) + '/' + m[1];
    }

    // '5:00 PM', '5 pm', '17:00' or '17:00:00' to '17:00'; '' otherwise.
    // 12 AM is midnight, 12 PM noon.
    function parseTime(text) {
        var m = /^(\d{1,2})(?::(\d{2}))?(?::\d{2})?\s*([ap])?\.?\s*m?\.?$/i.exec(String(text || '').trim());
        if (!m || (!m[2] && !m[3])) {
            return '';
        }
        var h = parseInt(m[1], 10), min = m[2] ? parseInt(m[2], 10) : 0;
        if (min > 59) {
            return '';
        }
        if (m[3]) {
            if (h < 1 || h > 12) {
                return '';
            }
            h = (h % 12) + (m[3].toLowerCase() === 'p' ? 12 : 0);
        } else if (h > 23) {
            return '';
        }
        return pad(h) + ':' + pad(min);
    }

    // '17:00' to '5:00 PM', the picker's "h:mm TT".
    function formatTime(hhmm) {
        var m = /^(\d{1,2}):(\d{2})$/.exec(String(hhmm || ''));
        if (!m || +m[1] > 23 || +m[2] > 59) {
            return '';
        }
        var h = +m[1];
        return ((h % 12) || 12) + ':' + m[2] + ' ' + (h < 12 ? 'AM' : 'PM');
    }

    // '26/10/2026 5:00 PM' to '2026-10-26 17:00'; a date alone reads as
    // midnight. '' when either part is wrong.
    function parseDateTime(text, order) {
        var m = /^(\S+)(?:\s+(.+))?$/.exec(String(text || '').trim());
        if (!m) {
            return '';
        }
        var date = parseDate(m[1], order);
        var time = m[2] ? parseTime(m[2]) : '00:00';
        return (date && time) ? (date + ' ' + time) : '';
    }

    function formatDateTime(value, order) {
        var m = /^(\d{4}-\d{2}-\d{2})[ T](\d{1,2}:\d{2})/.exec(String(value || ''));
        if (!m) {
            return '';
        }
        var date = formatDate(m[1], order), time = formatTime(m[2]);
        return (date && time) ? (date + ' ' + time) : '';
    }

    // ------------------------------------------------------------ library

    var waiting = [], loading = false;

    function ready() {
        return !!(window.VanillaCalendarPro && window.VanillaCalendarPro.Calendar);
    }

    function flush() {
        waiting.splice(0).forEach(function (fn) { fn(); });
    }

    // The library's tag is added by this file once; fn runs when it has run.
    function whenReady(fn) {
        if (ready()) {
            fn();
            return;
        }
        waiting.push(fn);
        if (loading) {
            return;
        }
        var own = document.querySelector('script[data-pg-calendar-src]');
        var src = own ? own.getAttribute('data-pg-calendar-src') : '';
        var parent = document.head || document.body;
        if (!src || !parent) {
            return;
        }
        loading = true;
        var tag = document.createElement('script');
        tag.src = src;
        tag.addEventListener('load', function () {
            loading = false;
            if (ready()) {
                flush();
            }
        });
        parent.appendChild(tag);
    }

    // ------------------------------------------------------------ calendar

    // The theme the calendar takes: the nearest data-bs-theme, else the
    // system's, as Bootstrap 6 sets it for its own Datepicker.
    function themeOf(box) {
        var holder = box.closest ? box.closest('[data-bs-theme]') : null;
        return holder ? holder.getAttribute('data-bs-theme') : '';
    }

    function syncTheme(box, cal) {
        var theme = themeOf(box);
        if (!cal || cal === box) {
            return;
        }
        if (theme) {
            cal.setAttribute('data-bs-theme', theme);
        } else {
            cal.removeAttribute('data-bs-theme');
        }
    }

    // The library hangs the popup off <body>, under an open <dialog> in the
    // top layer; it goes into the box's overlay host (pgUI.overlayHost(),
    // when the panel has it) and is placed under the box there, the host
    // being its containing block.
    function placeInHost(box, cal, hostOf) {
        var host = hostOf ? hostOf(box) : null;
        if (!cal || cal === box || !host || host === document.body) {
            return;
        }
        if (cal.parentNode !== host) {
            host.appendChild(cal);
        }
        var f = box.getBoundingClientRect(), h = host.getBoundingClientRect();
        var left = f.left - h.left - host.clientLeft + host.scrollLeft;
        // A field near the right edge of a narrow dialog: the calendar stays
        // inside it rather than being cut off.
        var right = host.scrollLeft + host.clientWidth - cal.offsetWidth;
        cal.style.top = (f.bottom - h.top - host.clientTop + host.scrollTop) + 'px';
        cal.style.left = Math.max(0, Math.min(left, right)) + 'px';
    }

    function monthOf(iso) {
        var m = /^(\d{4})-(\d{2})-\d{2}$/.exec(String(iso || ''));
        return m ? { selectedYear: +m[1], selectedMonth: +m[2] - 1 } : {};
    }

    function assign(to, from) {
        for (var k in from) {
            if (Object.prototype.hasOwnProperty.call(from, k)) {
                to[k] = from[k];
            }
        }
        return to;
    }

    // ------------------------------------------------------------ clock

    // The clock and the words pg_fields_script() printed on this file's tag
    // (data-pg-calendar, from pg_calendar_settings()): 12 or 24, the Done
    // button's word, the calendar's screen-reader names.
    var settingsCache = null;

    function settings() {
        if (settingsCache) {
            return settingsCache;
        }
        var own = document.querySelector('script[data-pg-calendar]'), read = null;
        try {
            read = own ? JSON.parse(own.getAttribute('data-pg-calendar')) : null;
        } catch (e) {
            read = null;
        }
        read = read || {};
        settingsCache = {
            timeMode: read.timeMode === 24 ? 24 : 12,
            done: read.done || 'Done',
            labels: read.labels || null
        };
        return settingsCache;
    }

    // '17:05' on the site's clock: '17:05' with the 24-hour clock, else
    // '5:05 PM'; '' for anything else.
    function clockTime(hhmm) {
        if (!/^\d{2}:\d{2}$/.test(String(hhmm || '')) || !formatTime(hhmm)) {
            return '';
        }
        return settings().timeMode === 24 ? hhmm : formatTime(hhmm);
    }

    // '17:05' in the form the library's time row takes: '17:05', or with
    // the 12-hour clock '05:05 PM' (its pattern refuses an hour without
    // its zero, and falls back to midnight).
    function libraryTime(hhmm, mode) {
        var m = /^(\d{2}):(\d{2})$/.exec(String(hhmm || '')) || [null, '00', '00'];
        if (mode === 24) {
            return m[1] + ':' + m[2];
        }
        var h = +m[1];
        return pad((h % 12) || 12) + ':' + m[2] + ' ' + (h < 12 ? 'AM' : 'PM');
    }

    function escapeText(text) {
        return String(text).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    // The library's own month layout with the time row under the dates, as
    // its default layout has it; the Done button after them in a popup.
    function layout(mode, inline) {
        var s = settings(), done = inline ? '' : '<div class="pg-vc-actions"><button type="button" class="btn btn-sm btn-solid theme-primary" data-pg-vc-done>' + escapeText(s.done) + '</button></div>';
        if (mode === 'time') {
            return '<#ControlTime />' + done;
        }
        var nav = escapeText((s.labels && s.labels.navigation) || 'Calendar Navigation');
        return '<div class="vc-header" data-vc="header" role="group" aria-label="' + nav + '">'
            + '<#ArrowPrev [month] />'
            + '<div class="vc-header__content" data-vc-header="content" aria-live="polite" aria-atomic="true"><#Month /><#Year /></div>'
            + '<#ArrowNext [month] />'
            + '</div>'
            + '<div class="vc-wrapper" data-vc="wrapper"><#WeekNumbers />'
            + '<div class="vc-content" data-vc="content" role="grid"><#Week /><#Dates /><#DateRangeTooltip /></div>'
            + '</div>'
            + '<#ControlTime />' + done;
    }

    // The time the library's row shows, as '17:05'.
    function shownTime(self) {
        var c = self.context;
        return parseTime(c.selectedHours + ':' + c.selectedMinutes + (c.selectedKeeping ? ' ' + c.selectedKeeping : ''));
    }

    // A calendar for the typed box `box`, as a popup under it, or drawn open
    // after it (options.inline: in a drawer, where a popup would hang off
    // the drawer's scrolling body).
    //   options.mode           'date' (the default), 'datetime' (the time row
    //                          under the dates) or 'time' (the time row only)
    //   options.iso            the date it shows ('' for none)
    //   options.time           the time it shows ('17:05'; midnight without)
    //   options.locale         month and day names ('tr', 'en')
    //   options.firstWeekday   0 Sunday, 1 Monday
    //   options.dateMin / dateMax  '2026-01-01': no day before / after it
    //   options.onPick(iso, hhmm)  a day was clicked or the time moved: the
    //                          shown date ('' for none) and time ('' in date mode)
    //   options.host(box)      the element the popup goes into
    // A date closes a date popup; with a time row the popup stays open for
    // it and closes with Done, Escape or a click outside. Returns
    // { calendar, set(iso, hhmm), destroy() }; set() moves the calendar to a
    // date and time without drawing it again.
    function attach(box, options) {
        var o = options || {}, mode = o.mode || 'date', inline = !!o.inline, target = box, wrap = null, offs = [];
        // The calendar takes over the element it is drawn in, classes
        // included, so it gets a box of its own inside ours.
        if (inline) {
            wrap = document.createElement('div');
            wrap.className = 'pg-date-inline';
            target = document.createElement('div');
            wrap.appendChild(target);
            box.parentNode.insertBefore(wrap, box.nextSibling);
        }
        var theme = themeOf(box), shown = false, calendar = null, timeMode = settings().timeMode;
        var place = function () {
            window.setTimeout(function () {
                if (shown && calendar) {
                    placeInHost(box, calendar.context.mainElement, o.host);
                }
            }, 0);
        };
        var pick = function (self, time) {
            var dates = self.context.selectedDates || [];
            if (o.onPick) {
                o.onPick(mode === 'time' ? '' : (dates.length ? dates[0] : ''), time);
            }
        };
        var dressed = function (main) {
            syncTheme(box, main);
            if (mode === 'time' && main && main !== box && main.classList) {
                main.classList.add('pg-vc-time-only');
            }
        };
        var calendarOptions = {
            inputMode: !inline,
            positionToInput: 'left',
            locale: o.locale || 'en',
            firstWeekday: o.firstWeekday || 0,
            selectionDatesMode: 'single',
            selectedDates: o.iso ? [o.iso] : [],
            // A second click on the chosen day keeps it, as jQuery UI did.
            enableDateToggle: false,
            selectedTheme: (!theme || theme === 'auto') ? 'system' : theme,
            themeAttrDetect: '[data-bs-theme]',
            onInit: function (self) {
                var main = self.context.mainElement;
                dressed(main);
                // Done is ours: the library has no such button.
                if (mode !== 'date' && !inline && main && main !== box) {
                    main.addEventListener('click', function (event) {
                        if (event.target && event.target.closest && event.target.closest('[data-pg-vc-done]')) {
                            self.hide();
                        }
                    });
                }
            },
            onShow: function (self) {
                shown = true;
                dressed(self.context.mainElement);
                placeInHost(box, self.context.mainElement, o.host);
                window.addEventListener('resize', place);
            },
            onHide: function () {
                shown = false;
                window.removeEventListener('resize', place);
            },
            onClickDate: function (self) {
                pick(self, mode === 'date' ? '' : shownTime(self));
                // A date closes a date popup; focus goes back to the box.
                if (mode === 'date' && !inline && (self.context.selectedDates || []).length) {
                    self.hide();
                }
            }
        };
        if (settings().labels) {
            calendarOptions.labels = settings().labels;
        }
        if (o.dateMin) {
            calendarOptions.dateMin = o.dateMin;
        }
        if (o.dateMax) {
            calendarOptions.dateMax = o.dateMax;
        }
        if (mode !== 'date') {
            calendarOptions.selectionTimeMode = timeMode;
            calendarOptions.selectedTime = libraryTime(o.time, timeMode);
            calendarOptions.layouts = { default: layout(mode, inline) };
            calendarOptions.onChangeTime = function (self, event, isError) {
                if (!isError) {
                    pick(self, shownTime(self));
                }
            };
        }
        assign(calendarOptions, monthOf(o.iso));
        // The library moves focus into the open calendar on every arrow key
        // in the box; Left and Right keep moving the text cursor. This
        // listener comes first, so it runs before the library's own.
        var keys = function (event) {
            if ((event.key === 'ArrowLeft' || event.key === 'ArrowRight') && !event.altKey && !event.ctrlKey && !event.metaKey) {
                event.stopImmediatePropagation();
            }
        };
        if (!inline) {
            box.addEventListener('keydown', keys);
            offs.push(function () { box.removeEventListener('keydown', keys); });
        }
        calendar = new window.VanillaCalendarPro.Calendar(target, calendarOptions);
        calendar.init();
        return {
            calendar: calendar,
            set: function (iso, hhmm) {
                var next = mode === 'time' ? {} : assign({ selectedDates: iso ? [iso] : [] }, monthOf(iso));
                if (mode !== 'date') {
                    next.selectedTime = libraryTime(hhmm, timeMode);
                }
                calendar.set(next);
            },
            destroy: function () {
                offs.forEach(function (off) { off(); });
                window.removeEventListener('resize', place);
                // Destroying puts a copy of the box as it was at creation in
                // its place; the box itself, with its value and listeners,
                // goes back.
                var parent = box.parentNode, index = parent ? Array.prototype.indexOf.call(parent.childNodes, box) : -1;
                calendar.destroy();
                if (!inline && parent && box.parentNode !== parent && parent.childNodes[index]) {
                    parent.replaceChild(box, parent.childNodes[index]);
                }
                if (wrap && wrap.parentNode) {
                    wrap.parentNode.removeChild(wrap);
                }
            }
        };
    }

    window.pgDateTime = {
        order: dateOrder,
        parseDate: parseDate,
        formatDate: formatDate,
        parseTime: parseTime,
        formatTime: formatTime,
        parseDateTime: parseDateTime,
        formatDateTime: formatDateTime,
        clockTime: clockTime,
        timeMode: function () { return settings().timeMode; },
        ready: ready,
        whenReady: whenReady,
        attach: attach
    };
})(window, document);
