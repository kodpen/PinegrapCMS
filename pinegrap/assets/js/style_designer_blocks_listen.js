/**
 * Pinegrap - Enterprise Website Platform
 *
 * The Listen block: three buttons that read the page's article aloud with
 * the browser's own voice. The block is markup only: its root carries the
 * mark data-pg-listen and is hidden, its buttons data-pg-listen-play,
 * data-pg-listen-pause and data-pg-listen-stop, each with a
 * data-pg-listen-text label. The page adds assets/js/pg_listen.js for the
 * mark (pg_listen_inject(), the Preview's row in _SD_PAGE_SCRIPTS), which
 * shows the root only where the browser has a voice for the page's
 * language and writes the labels in that language. On the editor's canvas
 * the root is drawn visible and silent (IFRAME_CSS).
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
        // Buttons a finger can hit (44 px), the icon and the label on one line.
        'pg-listen': '.pg-listen .btn { min-height: 2.75rem; display: inline-flex; align-items: center; gap: .5rem; }'
    };

    var _SD_BLOCK_CSS_NEEDS = {
        'listen-buttons': ['pg-listen']
    };

    // One button: an icon the screen reader skips and the label the script
    // fills in the page's language.
    var button = function (name, cls, icon, label, hidden) {
        var attrs = [{ name: 'type', value: 'button' }, { name: 'data-pg-listen-' + name, value: '' }];
        if (name === 'pause') attrs.push({ name: 'aria-pressed', value: 'false' });
        if (hidden) attrs.push({ name: 'hidden', value: '' });
        return kit.tag('button', cls, label, [
            kit.createNode('content', { contentType: 'icon', iconName: icon, cssClass: '', fontSize: '',
                _attrs: [{ name: 'aria-hidden', value: 'true' }] }),
            kit.createNode('semantic', { tag: 'span', cssClass: '', text: label, customName: _sdT('Label'),
                _attrs: [{ name: 'data-pg-listen-text', value: '' }] }, [])
        ], attrs);
    };

    var buildButtons = function () {
        return kit.div('pg-listen d-flex flex-wrap align-items-center gap-2 my-3', _sdT('Listen'), [
            button('play', 'btn btn-outline-primary', 'bi-volume-up', _sdT('Listen'), false),
            button('pause', 'btn btn-outline-secondary', 'bi-pause-fill', _sdT('Pause'), true),
            button('stop', 'btn btn-outline-secondary', 'bi-stop-fill', _sdT('Stop'), true)
        ], [
            { name: 'data-pg-listen', value: '' },
            { name: 'hidden', value: '' },
            { name: 'role', value: 'group' },
            { name: 'aria-label', value: _sdT('Read this article aloud') }
        ]);
    };

    return {
        prefix: 'listen-',
        group: { title: _sdT('Listen'), icon: 'bi-volume-up' },
        items: [
            { label: _sdT('Listen'), type: 'component', icon: 'bi-volume-up', extra: { componentType: 'listen-buttons' },
              desc: _sdT('A Listen button that reads the article around it aloud in the visitor\'s browser, with pause and stop.') }
        ],
        build: function (ctype) {
            if (ctype === 'listen-buttons') return buildButtons();
            return null;
        },
        css: _SD_BLOCK_CSS,
        needs: _SD_BLOCK_CSS_NEEDS
    };
});
