/**
 * Pinegrap - Enterprise Website Platform
 *
 * The booking widget on a site page (includes/fn/widgets_booking.php): the
 * visitor picks a service, a staff member or "anyone", a free day on the
 * month calendar of pg_datetime.js, then a time, which booking_slots.php
 * holds for them while they give their details; the confirm request books
 * it, or sends them back to the times when it was taken or the hold ran out.
 * A service with a deposit answers the details with its payment, drawn in
 * the details step in place of the form; paying posts that form to
 * booking_slots.php, which ends on this page again with the
 * #pg-booking-payment fragment, and the widget asks how the payment went.
 * The widget's parts are found by the attributes their
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

    var STEPS = ['step_service', 'step_staff', 'step_when', 'step_details', 'step_done'];
    var FIELDS = ['name', 'email', 'phone', 'notes', 'consent'];

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
        this.monthRequest = 0;
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
            this.unpay();
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

    // quiet: the status line is left to the day's times read at the same
    // time; the month and the day have tickets of their own.
    Widget.prototype.loadMonth = function (month, quiet) {
        var self = this, ticket = ++this.monthRequest;
        this.state.month = month;
        if (!quiet) {
            this.say(this.w.loading);
        }
        this.post({ action: 'month', service: this.state.service, staff: this.state.staff, month: month }).then(function (answer) {
            if (ticket !== self.monthRequest) {
                return;
            }
            var days = (answer && answer.ok && answer.days) || [];
            if (self.calendar) {
                self.calendar.days(days);
            }
            if (quiet && answer && answer.ok) {
                return;
            }
            self.say(answer && answer.ok ? (days.length ? '' : self.w.noDays) : (answer.message || self.w.failed));
        }, function () {
            if (ticket === self.monthRequest) {
                self.say(self.w.failed);
            }
        });
    };

    Widget.prototype.dayLabel = function (iso) {
        try {
            return new Intl.DateTimeFormat(this.lang, { dateStyle: 'full', timeZone: 'UTC' }).format(new Date(iso + 'T12:00:00Z'));
        } catch (e) {
            return iso;
        }
    };

    // note: a sentence said before the day's times (why they were read again).
    Widget.prototype.pickDay = function (iso, note) {
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
            self.say((note ? note + ' ' : '') + (answer && answer.ok ? (times.length ? fill(self.w.times, { day: self.dayLabel(iso) }) + ' ' + fill(self.w.zoneNote, { zone: self.s.zone }) : self.w.noTimes) : (answer.message || self.w.failed)));
        }, function () {
            self.say(self.w.failed);
        });
    };

    Widget.prototype.hold = function (time) {
        var self = this;
        this.post({ action: 'hold', service: this.state.service, staff: time.staff, start: time.start }).then(function (answer) {
            if (answer && answer.ok && answer.hold) {
                self.state.held = { start: time.start, staff: time.staff, until: answer.hold.until,
                    when: self.dayLabel(self.state.day) + ' ' + answer.hold.label };
                self.prefill(answer.visitor || {});
                var text = fill(self.w.held, { time: self.state.held.when, minutes: self.s.hold });
                var summary = self.part('summary');
                if (summary) {
                    summary.textContent = text;
                }
                self.go('step_details');
                self.say(text);
                return;
            }
            if (answer && answer.error === 'taken') {
                self.loadMonth(self.state.month, true);
                self.pickDay(self.state.day, answer.message || self.w.taken);
                return;
            }
            self.say((answer && answer.message) || self.w.failed);
        }, function () {
            self.say(self.w.failed);
        });
    };

    Widget.prototype.field = function (name) {
        var form = this.part('form');
        return form && form.elements ? form.elements.namedItem(name) : null;
    };

    // A signed-in visitor's details go into the boxes still empty.
    Widget.prototype.prefill = function (visitor) {
        var self = this;
        ['name', 'email', 'phone'].forEach(function (name) {
            var el = self.field(name);
            if (el && !el.value && visitor[name]) {
                el.value = visitor[name];
            }
        });
    };

    Widget.prototype.mark = function (errors) {
        var self = this, first = null;
        FIELDS.forEach(function (name) {
            var el = self.field(name);
            if (!el || !el.setAttribute) {
                return;
            }
            var bad = Object.prototype.hasOwnProperty.call(errors, name);
            el.setAttribute('aria-invalid', bad ? 'true' : 'false');
            el.classList.toggle('is-invalid', bad);
            if (bad && !first) {
                first = el;
            }
        });
        return first;
    };

    // Back to the times of the held day, which are read again: the time
    // was taken by someone else or the hold ran out.
    Widget.prototype.lost = function (message) {
        var path = this.state.path;
        while (path.length > 1 && path[path.length - 1] !== 'step_when') {
            path.pop();
        }
        this.state.held = null;
        this.unpay();
        this.show('step_when');
        this.loadMonth(this.state.month, true);
        this.pickDay(this.state.day, message);
    };

    Widget.prototype.confirm = function () {
        var self = this, form = this.part('form');
        if (!form || this.sending) {
            return;
        }
        if (form.checkValidity && !form.checkValidity()) {
            if (form.reportValidity) {
                form.reportValidity();
            }
            return;
        }
        var body = { action: 'confirm', page: window.location.pathname + window.location.search };
        FIELDS.forEach(function (name) {
            var el = self.field(name);
            if (el) {
                body[name] = name === 'consent' ? !!el.checked : String(el.value || '');
            }
        });
        var submit = form.querySelector('[type="submit"]');
        this.sending = true;
        if (submit) {
            submit.disabled = true;
        }
        this.say(this.w.sending);
        var finish = function () {
            self.sending = false;
            if (submit) {
                submit.disabled = false;
            }
        };
        this.post(body).then(function (answer) {
            finish();
            answer = answer || {};
            if (answer.ok && answer.status === 'pay') {
                self.mark({});
                self.pay((answer.pay && answer.pay.html) || '');
                return;
            }
            if (answer.ok) {
                self.mark({});
                var done = self.part('done');
                if (done) {
                    done.textContent = answer.message || '';
                }
                var summary = self.part('summary');
                var when = self.state.held ? self.state.held.when : '';
                form.reset();
                self.state.path = ['step_done'];
                self.state.held = null;
                self.show('step_done');
                self.say((answer.message || '') + (when ? ' ' + when : ''));
                if (summary) {
                    summary.textContent = '';
                }
                return;
            }
            if (answer.error === 'invalid') {
                var errors = answer.fields || {}, first = self.mark(errors), list = [];
                Object.keys(errors).forEach(function (key) {
                    list.push(errors[key]);
                });
                self.say((answer.message || self.w.check) + ' ' + list.join(' '));
                if (first) {
                    first.focus();
                }
                return;
            }
            if (answer.error === 'taken' || answer.error === 'expired') {
                self.lost(answer.message || self.w.taken);
                return;
            }
            self.say(answer.message || self.w.failed);
        }, function () {
            finish();
            self.say(self.w.failed);
        });
    };

    // The deposit's payment in the details step, in place of the form. Its
    // scripts (the card service's and the site's card box) are loaded in
    // order; innerHTML alone would not run them.
    Widget.prototype.pay = function (html) {
        var form = this.part('form'), step = this.part('step_details');
        if (!step) {
            return;
        }
        this.unpay();
        var box = document.createElement('div');
        box.setAttribute('data-pg-booking-paybox', '');
        box.innerHTML = html;
        if (form) {
            form.hidden = true;
            form.parentNode.insertBefore(box, form.nextSibling);
        } else {
            step.appendChild(box);
        }
        var scripts = Array.prototype.slice.call(box.querySelectorAll('script'));
        var hosted = function () {
            if (window.pgPaymentHosted) {
                window.pgPaymentHosted.setUp();
            }
        };
        scripts.forEach(function (old, index) {
            var script = document.createElement('script');
            if (old.src) {
                script.src = old.src;
                script.async = false;
            } else {
                script.text = old.text;
            }
            if (index === scripts.length - 1) {
                script.addEventListener('load', hosted);
            }
            old.parentNode.replaceChild(script, old);
        });
        if (!scripts.length) {
            hosted();
        }
        this.say('');
        var first = box.querySelector('input:not([type="hidden"]), button');
        if (first) {
            first.focus();
        }
    };

    Widget.prototype.unpay = function () {
        var box = this.root.querySelector('[data-pg-booking-paybox]'), form = this.part('form');
        if (box) {
            box.parentNode.removeChild(box);
        }
        if (form) {
            form.hidden = false;
        }
    };

    // Back on the page after paying: the payment still open (refused, or
    // paid elsewhere and not yet confirmed), the booking made, or the time lost.
    Widget.prototype.resume = function () {
        var self = this;
        if (window.history && window.history.replaceState) {
            window.history.replaceState(null, '', window.location.pathname + window.location.search);
        }
        this.post({ action: 'payment' }).then(function (answer) {
            answer = answer || {};
            if (answer.state === 'open') {
                self.state.path = ['step_service', 'step_details'];
                self.show('step_details');
                self.pay((answer.pay && answer.pay.html) || '');
            } else if (answer.state === 'done') {
                var done = self.part('done');
                if (done) {
                    done.textContent = answer.message || '';
                }
                self.state.path = ['step_done'];
                self.show('step_done');
                self.say(answer.message || '');
            } else if (answer.state === 'expired') {
                self.say(answer.message || self.w.taken);
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
        if (window.location.hash === '#pg-booking-payment' && !document.querySelector('[data-pg-booking-resumed]')) {
            this.root.setAttribute('data-pg-booking-resumed', '');
            this.resume();
        }
        this.root.addEventListener('submit', function (event) {
            var form = self.part('form');
            if (form && event.target === form) {
                event.preventDefault();
                self.confirm();
            }
        });
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
