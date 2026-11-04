/**
 * Pinegrap - Enterprise Website Platform
 *
 * The booking widget on a site page (includes/fn/widgets_booking.php): the
 * visitor picks a service, a staff member or "anyone", a free day on the
 * month calendar of pg_datetime.js, then a time, which booking_slots.php
 * holds for them. The widget's parts are found by the attributes their
 * bindings gave them (data-pg-booking-part, data-pg-booking-action), never
 * by a class, so a designer may style and move them freely.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */
(function (window, document) {
    'use strict';

    var STEPS = ['step_service', 'step_staff', 'step_when', 'step_details'];

    function pad(n) {
        return (n < 10 ? '0' : '') + n;
    }

    function readSettings(root) {
        try {
            return JSON.parse(root.getAttribute('data-pg-booking')) || null;
        } catch (e) {
            return null;
        }
    }

    function fill(text, values) {
        return String(text || '').replace(/\{(\w+)\}/g, function (all, key) {
            return Object.prototype.hasOwnProperty.call(values, key) ? values[key] : all;
        });
    }

    function Widget(root, settings) {
        this.root = root;
        this.s = settings;
        this.w = settings.words || {};
        this.state = { service: 0, staff: 0, day: '', month: '', path: ['step_service'] };
        this.calendar = null;
        this.lang = String(document.documentElement.lang || 'en').slice(0, 2).toLowerCase();
        this.request = 0;
    }

    Widget.prototype.part = function (name) {
        return this.root.querySelector('[data-pg-booking-part="' + name + '"]');
    };

    Widget.prototype.say = function (text) {
        var status = this.part('status');
        if (status) {
            status.textContent = text || '';
        }
    };

    Widget.prototype.show = function (step) {
        var self = this;
        STEPS.forEach(function (name) {
            var el = self.part(name);
            if (el) {
                el.hidden = (name !== step);
            }
        });
        var shown = this.part(step);
        var heading = shown && shown.querySelector('h1, h2, h3, h4, h5, h6');
        if (heading) {
            if (!heading.hasAttribute('tabindex')) {
                heading.setAttribute('tabindex', '-1');
            }
            heading.focus({ preventScroll: false });
        }
    };

    Widget.prototype.go = function (step) {
        this.state.path.push(step);
        this.say('');
        this.show(step);
    };

    Widget.prototype.back = function () {
        var path = this.state.path;
        if (path.length < 2) {
            return;
        }
        if (path.pop() === 'step_details') {
            this.post({ action: 'release' });
        }
        this.say('');
        this.show(path[path.length - 1]);
    };

    Widget.prototype.post = function (body) {
        return window.fetch(this.s.url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
            body: JSON.stringify(body)
        }).then(function (response) {
            return response.json().catch(function () {
                return { ok: false };
            });
        });
    };

    Widget.prototype.button = function (text, onClick, pressed) {
        var b = document.createElement('button');
        b.type = 'button';
        b.className = 'btn btn-outline-primary';
        b.textContent = text;
        if (pressed !== undefined) {
            b.setAttribute('aria-pressed', pressed ? 'true' : 'false');
        }
        b.addEventListener('click', onClick);
        return b;
    };

    Widget.prototype.pickService = function (id) {
        var self = this, staff = (this.s.staff && this.s.staff[String(id)]) || [];
        this.state.service = id;
        if (!staff.length) {
            return;
        }
        if (staff.length === 1 || !this.part('step_staff')) {
            this.state.staff = staff.length === 1 ? staff[0].id : 0;
            this.openWhen();
            return;
        }
        var list = this.part('staff');
        if (list) {
            list.innerHTML = '';
            list.setAttribute('role', 'group');
            var choose = function (staffId) {
                return function () {
                    self.state.staff = staffId;
                    self.openWhen();
                };
            };
            list.appendChild(this.button(this.w.anyone, choose(0)));
            staff.forEach(function (person) {
                list.appendChild(self.button(person.name, choose(person.id)));
            });
        }
        this.go('step_staff');
    };

    Widget.prototype.openWhen = function () {
        var self = this, today = new Date(), month = today.getFullYear() + '-' + pad(today.getMonth() + 1);
        this.state.day = '';
        this.state.month = month;
        var times = this.part('times');
        if (times) {
            times.innerHTML = '';
        }
        this.go('step_when');
        var mount = this.part('calendar');
        if (!mount || !window.pgDateTime) {
            return;
        }
        window.pgDateTime.whenReady(function () {
            if (self.calendar) {
                self.calendar.destroy();
            }
            self.calendar = window.pgDateTime.month(mount, {
                month: month,
                days: [],
                locale: self.lang,
                firstWeekday: self.lang === 'tr' ? 1 : 0,
                dateMin: today.getFullYear() + '-' + pad(today.getMonth() + 1) + '-' + pad(today.getDate()),
                onPick: function (iso) {
                    self.pickDay(iso);
                },
                onMonth: function (shown) {
                    self.loadMonth(shown);
                }
            });
            self.loadMonth(month);
        });
    };

    Widget.prototype.loadMonth = function (month) {
        var self = this, ticket = ++this.request;
        this.state.month = month;
        this.say(this.w.loading);
        this.post({ action: 'month', service: this.state.service, staff: this.state.staff, month: month }).then(function (answer) {
            if (ticket !== self.request) {
                return;
            }
            var days = (answer && answer.ok && answer.days) || [];
            if (self.calendar) {
                self.calendar.days(days);
            }
            self.say(answer && answer.ok ? (days.length ? '' : self.w.noDays) : (answer.message || self.w.failed));
        }, function () {
            self.say(self.w.failed);
        });
    };

    Widget.prototype.dayLabel = function (iso) {
        try {
            return new Intl.DateTimeFormat(this.lang, { dateStyle: 'full', timeZone: 'UTC' }).format(new Date(iso + 'T12:00:00Z'));
        } catch (e) {
            return iso;
        }
    };

    Widget.prototype.pickDay = function (iso) {
        var self = this, ticket = ++this.request, list = this.part('times');
        this.state.day = iso;
        this.say(this.w.loading);
        this.post({ action: 'day', service: this.state.service, staff: this.state.staff, day: iso }).then(function (answer) {
            if (ticket !== self.request || !list) {
                return;
            }
            var times = (answer && answer.ok && answer.times) || [];
            list.innerHTML = '';
            list.setAttribute('role', 'group');
            list.setAttribute('aria-label', fill(self.w.times, { day: self.dayLabel(iso) }));
            times.forEach(function (time) {
                list.appendChild(self.button(time.label, function () {
                    self.hold(time);
                }));
            });
            self.say(answer && answer.ok ? (times.length ? fill(self.w.times, { day: self.dayLabel(iso) }) + ' ' + fill(self.w.zoneNote, { zone: self.s.zone }) : self.w.noTimes) : (answer.message || self.w.failed));
        }, function () {
            self.say(self.w.failed);
        });
    };

    Widget.prototype.hold = function (time) {
        var self = this;
        this.post({ action: 'hold', service: this.state.service, staff: time.staff, start: time.start }).then(function (answer) {
            if (answer && answer.ok && answer.hold) {
                self.state.held = { start: time.start, staff: time.staff, until: answer.hold.until };
                var text = fill(self.w.held, { time: self.dayLabel(self.state.day) + ' ' + answer.hold.label, minutes: self.s.hold });
                var summary = self.part('summary');
                if (summary) {
                    summary.textContent = text;
                }
                self.go('step_details');
                self.say(text);
                return;
            }
            self.say((answer && answer.message) || self.w.failed);
            if (answer && answer.error === 'taken') {
                self.pickDay(self.state.day);
                self.loadMonth(self.state.month);
            }
        }, function () {
            self.say(self.w.failed);
        });
    };

    Widget.prototype.start = function () {
        var self = this, status = this.part('status');
        if (status) {
            status.setAttribute('role', 'status');
            status.setAttribute('aria-live', 'polite');
        }
        this.show('step_service');
        this.root.addEventListener('click', function (event) {
            var target = event.target && event.target.closest ? event.target.closest('[data-pg-booking-action]') : null;
            if (!target || !self.root.contains(target)) {
                return;
            }
            event.preventDefault();
            var action = target.getAttribute('data-pg-booking-action');
            if (action === 'service') {
                self.pickService(parseInt(target.getAttribute('data-pg-booking-service'), 10) || 0);
            } else if (action === 'back') {
                self.back();
            }
        });
    };

    function init() {
        var roots = document.querySelectorAll('[data-pg-booking]');
        Array.prototype.forEach.call(roots, function (root) {
            var settings = readSettings(root);
            if (settings && settings.url && !root.pgBooking) {
                root.pgBooking = new Widget(root, settings);
                root.pgBooking.start();
            }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})(window, document);
