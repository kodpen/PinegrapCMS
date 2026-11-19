/**
 * Pinegrap - Enterprise Website Platform
 *
 * The learner's course blocks (data-pg-course) on a published page:
 * "Mark as done" (done), "Course progress" (progress), "Course lessons"
 * (lessons) and "My courses" (mine). Each block is hidden in its markup;
 * this file asks course_action.php for the view of the page's course and
 * shows and fills the blocks it has something for. A visitor or a member
 * without the course's folder sees the lessons' names only; "Mark as done",
 * the progress and "My courses" stay hidden for them.
 *
 * The page gives window.pgCourseConfig = {door, page, labels}
 * (pg_course_blocks_inject()). Without it (the editor's Preview) the blocks
 * stay as they are.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */
(function (root) {
    'use strict';

    // The badge each lesson state wears; Bootstrap 5.3 theme classes, so
    // both colour modes read.
    var STATE_CLASS = {
        done: 'text-bg-success',
        open: 'text-bg-primary',
        opens: 'text-bg-secondary',
        locked: 'bg-body-secondary text-body-secondary'
    };

    // A text in the page's language by its English key; %1 and %2 take
    // the values given.
    function text(labels, key, one, two) {
        var out = (labels && typeof labels[key] === 'string') ? labels[key] : key;
        if (one !== undefined) out = out.split('%1').join(String(one));
        if (two !== undefined) out = out.split('%2').join(String(two));
        return out;
    }

    // A Y-m-d day as the page's language writes it, read at noon so no time
    // zone moves it to another day.
    function day(ymd, lang) {
        var m = /^(\d{4})-(\d{2})-(\d{2})$/.exec(String(ymd || ''));
        if (!m) return String(ymd || '');
        var date = new Date(+m[1], +m[2] - 1, +m[3], 12);
        try {
            return new Intl.DateTimeFormat(lang || undefined, { year: 'numeric', month: 'long', day: 'numeric' }).format(date);
        } catch (e) {
            return date.toDateString();
        }
    }

    // The words of a lesson's state badge.
    function stateText(labels, lesson, lang) {
        if (lesson.state === 'done') return text(labels, 'Done');
        if (lesson.state === 'opens') return text(labels, 'Opens on', day(lesson.opens_on, lang));
        if (lesson.state === 'locked') return text(labels, 'Locked');
        return text(labels, 'Open');
    }

    // The progress line: "4 of 12 lessons done", or "Course complete".
    function countText(labels, view) {
        return view.complete ? text(labels, 'Complete') : text(labels, 'Progress', view.done, view.total);
    }

    function percent(view) {
        return view.total > 0 ? Math.round(view.done * 100 / view.total) : 0;
    }

    if (typeof module === 'object' && module.exports) {
        module.exports = { text: text, day: day, stateText: stateText, countText: countText, percent: percent };
        return;
    }

    var config = root.pgCourseConfig;
    var doc = root.document;
    if (!config || !config.door || !doc || !root.fetch) return;

    var labels = config.labels || {};
    var lang = doc.documentElement.getAttribute('lang') || undefined;
    var token = '';

    function blocks(kind) {
        return Array.prototype.slice.call(doc.querySelectorAll('[data-pg-course="' + kind + '"]'));
    }

    function first(block, mark) {
        return block.querySelector('[' + mark + ']');
    }

    function show(block, on) {
        if (on) block.removeAttribute('hidden'); else block.setAttribute('hidden', '');
    }

    // A list drawn from its first item, the template the block carries:
    // the template is kept hidden and a copy is made per row.
    function fillList(block, rows, draw) {
        var list = first(block, 'data-pg-course-list');
        var item = list && first(list, 'data-pg-course-item');
        if (!item) return;
        Array.prototype.slice.call(list.querySelectorAll('[data-pg-course-copy]')).forEach(function (el) { el.remove(); });
        item.setAttribute('hidden', '');
        rows.forEach(function (row) {
            var copy = item.cloneNode(true);
            copy.removeAttribute('hidden');
            copy.removeAttribute('data-pg-course-item');
            copy.setAttribute('data-pg-course-copy', '');
            draw(copy, row);
            list.appendChild(copy);
        });
    }

    // One row: a link (a plain text for a lesson that is not open) and its
    // badge.
    function drawRow(copy, name, url, badge, badgeClass) {
        var link = first(copy, 'data-pg-course-link');
        var mark = first(copy, 'data-pg-course-state');
        if (link) {
            link.textContent = name;
            if (url) {
                link.setAttribute('href', url);
            } else {
                link.removeAttribute('href');
                link.setAttribute('aria-disabled', 'true');
            }
        }
        if (mark) {
            mark.textContent = badge;
            mark.className = ('badge ' + (badgeClass || '')).trim();
            show(mark, badge !== '');
        }
    }

    function setName(block, course) {
        var name = first(block, 'data-pg-course-name');
        if (name) name.textContent = course.name;
    }

    function fillProgress(course) {
        blocks('progress').forEach(function (block) {
            var on = !!(course && course.member);
            show(block, on);
            if (!on) return;
            setName(block, course);
            var bar = first(block, 'data-pg-course-bar');
            var fill = first(block, 'data-pg-course-fill');
            var count = first(block, 'data-pg-course-count');
            if (bar) {
                bar.setAttribute('aria-valuenow', String(percent(course)));
                bar.setAttribute('aria-valuetext', countText(labels, course));
            }
            if (fill) fill.style.width = percent(course) + '%';
            if (count) count.textContent = countText(labels, course);
        });
    }

    function fillLessons(course) {
        blocks('lessons').forEach(function (block) {
            show(block, !!course);
            if (!course) return;
            setName(block, course);
            fillList(block, course.lessons, function (copy, lesson) {
                if (!course.member) {
                    drawRow(copy, lesson.name, '', '', '');
                    return;
                }
                var reachable = lesson.state === 'done' || lesson.state === 'open';
                drawRow(copy, lesson.name, reachable ? lesson.url : '', stateText(labels, lesson, lang), STATE_CLASS[lesson.state]);
            });
            var next = first(block, 'data-pg-course-next');
            if (next) {
                show(next, !!(course.member && course.next));
                if (course.member && course.next) {
                    next.setAttribute('href', course.next);
                    next.textContent = text(labels, 'Continue');
                }
            }
        });
    }

    function fillMine(mine) {
        blocks('mine').forEach(function (block) {
            show(block, Array.isArray(mine));
            if (!Array.isArray(mine)) return;
            fillList(block, mine, function (copy, row) {
                drawRow(copy, row.name, row.url, countText(labels, row), row.complete ? STATE_CLASS.done : 'bg-body-secondary text-body-secondary');
            });
            var empty = first(block, 'data-pg-course-empty');
            if (empty) show(empty, mine.length === 0);
        });
    }

    function setStatus(block, message) {
        var status = first(block, 'data-pg-course-status');
        if (status) {
            status.textContent = message;
            show(status, message !== '');
        }
    }

    function fillDone(course) {
        var lesson = course && course.member ? course.lesson : null;
        blocks('done').forEach(function (block) {
            show(block, !!lesson);
            if (!lesson) return;
            var button = first(block, 'data-pg-course-press');
            if (!button) return;
            button.disabled = !!lesson.done;
            button.setAttribute('aria-pressed', lesson.done ? 'true' : 'false');
            button.textContent = text(labels, lesson.done ? 'Done' : 'Mark as done');
            button.setAttribute('data-pg-course-lesson', String(lesson.id));
        });
    }

    function fill(data) {
        var course = data && data.course ? data.course : null;
        fillDone(course);
        fillProgress(course);
        fillLessons(course);
        if (data && 'mine' in data) fillMine(data.mine); else fillMine(null);
    }

    // The words a refused press shows.
    function refusal(answer, status) {
        var error = answer && answer.error;
        if (error === 'quiz') return text(labels, 'Quiz', answer.need);
        if (error === 'closed') return text(labels, 'Closed');
        if (error === 'rate' || status === 429) return text(labels, 'Rate');
        return text(labels, 'Failed');
    }

    function press(button) {
        var block = button.closest('[data-pg-course="done"]');
        var body = new URLSearchParams();
        body.set('lesson', button.getAttribute('data-pg-course-lesson') || '');
        body.set('token', token);
        button.disabled = true;
        root.fetch(config.door, { method: 'POST', credentials: 'same-origin', body: body })
            .then(function (res) {
                return res.json().then(function (answer) { return { ok: res.ok, status: res.status, answer: answer }; });
            })
            .then(function (got) {
                if (got.ok && got.answer && got.answer.done) {
                    // The page's blocks at once; "My courses" with the next
                    // view.
                    fillDone(got.answer.view);
                    fillProgress(got.answer.view);
                    fillLessons(got.answer.view);
                    load();
                    if (block) setStatus(block, text(labels, 'Lesson done.'));
                    return;
                }
                button.disabled = false;
                if (block) setStatus(block, refusal(got.answer, got.status));
            })
            .catch(function () {
                button.disabled = false;
                if (block) setStatus(block, text(labels, 'Failed'));
            });
    }

    function load() {
        root.fetch(config.door + '?page=' + encodeURIComponent(config.page || 0), { credentials: 'same-origin' })
            .then(function (res) { return res.ok ? res.json() : null; })
            .then(function (data) {
                if (!data) return;
                token = data.token || '';
                fill(data);
            })
            .catch(function () {});
    }

    doc.addEventListener('click', function (e) {
        var button = e.target && e.target.closest ? e.target.closest('[data-pg-course-press]') : null;
        if (!button || button.disabled) return;
        e.preventDefault();
        press(button);
    });

    if (doc.readyState === 'loading') doc.addEventListener('DOMContentLoaded', load); else load();
}(typeof window !== 'undefined' ? window : this));
