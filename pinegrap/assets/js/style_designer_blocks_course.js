/**
 * Pinegrap - Enterprise Website Platform
 *
 * The learner's course blocks, in their own Courses group: "Mark as done"
 * for a lesson page, "Course progress" (a bar and "4 of 12 lessons done"),
 * "Course lessons" (each lesson with its state and "Continue") and
 * "My courses" (the member's courses, each with its progress).
 *
 * The blocks are markup only, each root carries data-pg-course="<kind>" and
 * is hidden: the site's script (assets/js/pg_course.js, added by the server
 * while Site Settings, Features, Courses is on) asks course_action.php for
 * the member's view and shows and fills the blocks it has something for.
 * A list's first item is the template the script copies per row. On the
 * canvas the hidden roots are drawn as they will look.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     Proprietary, see license-editors.txt
 */
StyleDesigner.registerBlockFamily(function (kit) {
    'use strict';
    var _sdT = kit._sdT;

    var _SD_BLOCK_CSS = {
    };

    var _SD_BLOCK_CSS_NEEDS = {
    };

    var hidden = { name: 'hidden', value: '' };
    // What a press brings is spoken when it shows (pg_course.js).
    var status = { name: 'role', value: 'status' };

    var mark = function (name, value) {
        return { name: 'data-pg-course' + (name ? '-' + name : ''), value: value || '' };
    };

    // A text node with the attributes the script fills it by.
    var marked = function (node, attrs) {
        node.props._attrs = attrs;
        return node;
    };

    // The root every block shares: hidden until the script fills it.
    var root = function (kind, name, kids) {
        return kit.tag('section', 'my-4', name, kids, [mark('', kind), hidden]);
    };

    // A list whose first item is the row the script copies: the name as a
    // link and a badge.
    var list = function (tag, rowName) {
        return kit.tag(tag, 'list-group mb-3', _sdT('List'), [
            kit.tag('li', 'list-group-item d-flex justify-content-between align-items-center gap-3', _sdT('Row'), [
                marked(kit.link(rowName, '#', 'link-body-emphasis', _sdT('Name')), [mark('link')]),
                kit.createNode('semantic', { tag: 'span', cssClass: 'badge text-bg-primary', text: _sdT('Open'), customName: _sdT('State'), _attrs: [mark('state')] })
            ], [mark('item')])
        ], [mark('list')]);
    };

    // ── Mark as done ────────────────────────────────────────────────────
    var buildDone = function () {
        return root('done', _sdT('Mark as Done'), [
            kit.createNode('semantic', { tag: 'button', cssClass: 'btn btn-success px-4', text: _sdT('Mark as done'), customName: _sdT('Mark as done'),
                _attrs: [{ name: 'type', value: 'button' }, mark('press'), { name: 'aria-pressed', value: 'false' }] }),
            marked(kit.p(_sdT('Lesson done.'), 'small text-body-secondary mt-2 mb-0'), [mark('status'), status, hidden])
        ]);
    };

    // ── Course progress ─────────────────────────────────────────────────
    var buildProgress = function () {
        return root('progress', _sdT('Course Progress'), [
            marked(kit.h('h2', _sdT('Course name'), 'h5 fw-bold mb-3'), [mark('name')]),
            kit.div('progress mb-2', _sdT('Bar'), [
                kit.div('progress-bar', _sdT('Fill'), [], [mark('fill')])
            ], [
                { name: 'role', value: 'progressbar' }, { name: 'aria-label', value: _sdT('Course progress') },
                { name: 'aria-valuemin', value: '0' }, { name: 'aria-valuemax', value: '100' },
                { name: 'aria-valuenow', value: '0' }, mark('bar')
            ]),
            marked(kit.p(_sdT('0 of 0 lessons done'), 'small text-body-secondary mb-0'), [mark('count')])
        ]);
    };

    // ── Course lessons ──────────────────────────────────────────────────
    var buildLessons = function () {
        var next = kit.link(_sdT('Continue'), '#', 'btn btn-primary px-4', _sdT('Continue'));
        next.props._attrs = [mark('next'), hidden];
        return root('lessons', _sdT('Course Lessons'), [
            marked(kit.h('h2', _sdT('Course name'), 'h4 fw-bold mb-3'), [mark('name')]),
            list('ol', _sdT('Lesson name')),
            next
        ]);
    };

    // ── My courses ──────────────────────────────────────────────────────
    var buildMine = function () {
        return root('mine', _sdT('My Courses'), [
            kit.h('h2', _sdT('My courses'), 'h4 fw-bold mb-3'),
            list('ul', _sdT('Course name')),
            marked(kit.p(_sdT('You have no courses yet.'), 'text-body-secondary mb-0'), [mark('empty'), hidden])
        ]);
    };

    return {
        prefix: 'course-',
        group: { title: _sdT('Courses'), icon: 'bi-mortarboard' },
        items: [
            { label: _sdT('Course: Mark as Done'), type: 'component', icon: 'bi-check2-circle', extra: { componentType: 'course-done' },
              desc: _sdT('A button on a lesson page that marks the lesson done for the signed-in member.') },
            { label: _sdT('Course: Progress'), type: 'component', icon: 'bi-bar-chart-steps', extra: { componentType: 'course-progress' },
              desc: _sdT('A bar with the lessons the member has done of the course on this page.') },
            { label: _sdT('Course: Lessons'), type: 'component', icon: 'bi-list-ol', extra: { componentType: 'course-lessons' },
              desc: _sdT('The course\'s lessons in their order, each done, open or opening later, with Continue.') },
            { label: _sdT('Course: My Courses'), type: 'component', icon: 'bi-mortarboard', extra: { componentType: 'course-mine' },
              desc: _sdT('The signed-in member\'s courses, each with its progress.') }
        ],
        build: function (ctype) {
            if (ctype === 'course-done') return buildDone();
            if (ctype === 'course-progress') return buildProgress();
            if (ctype === 'course-lessons') return buildLessons();
            if (ctype === 'course-mine') return buildMine();
            return null;
        },
        css: _SD_BLOCK_CSS,
        needs: _SD_BLOCK_CSS_NEEDS
    };
});
