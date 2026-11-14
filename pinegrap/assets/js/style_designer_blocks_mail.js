/**
 * Pinegrap - Enterprise Website Platform
 *
 * The mail blocks of an e-mail design (a page whose root carries
 * props.pgEmail): the frame; heading and text; text and button; picture
 * and text; a section break; two columns. Each element in them is a
 * heading, text, button, picture, divider, space or column of its own. Each node names its kind in props._mail;
 * the look a mail program needs inline is written when the node is drawn,
 * from pg_mail_block_styles() on the server and _SD_MAIL_STYLES on the
 * canvas, so the tree keeps only classes and props. The frame and the
 * columns are presentation tables, as Outlook needs; the mail pass's head
 * style stacks the columns on a phone (.pg-mail-col).
 *
 * The starter tree of a new design is built on the server with the same
 * shape (pg_email_design_starter_tree()).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     Proprietary, see license-editors.txt
 */
StyleDesigner.registerBlockFamily(function (kit) {
    'use strict';
    var _sdT = kit._sdT;

    // The canvas's own phone rules, as the mail pass's head style has them
    // (pg_mail_pass_head_style()): the Phone view shows the frame full
    // width and the columns stacked. A column's width is its width
    // attribute, not its inline look, so these rules win without
    // !important. The mail pass takes them out of the sent body and writes
    // its own.
    var _SD_BLOCK_CSS = {
        'pg-mail-frame': '@media (max-width: 620px) { .pg-mail-frame { width: 100%; } }',
        'pg-mail-col': '@media (max-width: 620px) { .pg-mail-col { display: block; width: 100%; } }'
    };

    var _SD_BLOCK_CSS_NEEDS = {
        'mail-frame': ['pg-mail-frame'],
        'mail-columns': ['pg-mail-col']
    };

    // A mail block node: a semantic element with its kind.
    var mail = function (tag, kind, name, kids, extra) {
        var p = { tag: tag, cssClass: '', customName: name, _mail: kind };
        Object.keys(extra || {}).forEach(function (k) { p[k] = extra[k]; });
        return kit.createNode('semantic', p, kids || []);
    };
    var plain = function (tag, kids) {
        return kit.createNode('semantic', { tag: tag, cssClass: '', customName: '' }, kids || []);
    };
    // The attributes of a presentation table of this width.
    var tableAttrs = function (width) {
        return [
            { name: 'role', value: 'presentation' },
            { name: 'width', value: width },
            { name: 'cellpadding', value: '0' },
            { name: 'cellspacing', value: '0' },
            { name: 'border', value: '0' }
        ];
    };

    var heading = function () { return mail('h1', 'heading', _sdT('Heading'), [], { text: _sdT('A heading for your e-mail') }); };
    var text = function () { return mail('p', 'text', _sdT('Text'), [], { text: _sdT('Write what you want to tell your readers here.') }); };
    var button = function () {
        return mail('p', 'button_row', _sdT('Button'), [
            mail('a', 'button', _sdT('Button link'), [], { text: _sdT('Visit our site'), href: '/', target: '' })
        ]);
    };

    var frame = function () {
        var attrs = tableAttrs('600');
        attrs.push({ name: 'align', value: 'center' });
        return mail('table', 'frame', _sdT('E-mail frame'), [
            plain('tbody', [plain('tr', [mail('td', 'cell', _sdT('Content'), [heading(), text(), button()])])])
        ], { cssClass: 'pg-mail-frame', _attrs: attrs });
    };

    var column = function (n) {
        return mail('td', 'column', _sdT('Column {var:1}', n), [heading(), text()], {
            cssClass: 'pg-mail-col',
            _attrs: [{ name: 'width', value: '50%' }, { name: 'valign', value: 'top' }]
        });
    };

    // A part of the e-mail made of several blocks, dropped as one.
    var group = function (name, kids) { return mail('div', 'group', name, kids); };

    var builders = {
        'mail-frame': frame,
        'mail-intro': function () {
            return group(_sdT('Heading and text'), [heading(), text(), text()]);
        },
        'mail-button': function () {
            return group(_sdT('Text and button'), [text(), button()]);
        },
        'mail-picture': function () {
            return group(_sdT('Picture and text'), [
                mail('img', 'picture', _sdT('Picture'), [], {
                    src: 'https://picsum.photos/seed/pgmail/1200/600', alt: _sdT('Describe the picture'),
                    _attrs: [{ name: 'width', value: '552' }]
                }),
                mail('div', 'space', _sdT('Space')),
                heading(),
                text()
            ]);
        },
        'mail-break': function () {
            return group(_sdT('Section break'), [
                mail('hr', 'divider', _sdT('Divider')),
                heading(),
                text()
            ]);
        },
        'mail-columns': function () {
            return mail('table', 'columns', _sdT('Two columns'), [
                plain('tbody', [plain('tr', [column(1), column(2)])])
            ], { _attrs: tableAttrs('100%') });
        }
    };

    return {
        prefix: 'mail-',
        group: { title: _sdT('E-mail'), icon: 'bi-envelope' },
        css: _SD_BLOCK_CSS,
        needs: _SD_BLOCK_CSS_NEEDS,
        items: [
            { label: _sdT('E-mail: Frame'), type: 'component', icon: 'bi-envelope', extra: { componentType: 'mail-frame' },
              desc: _sdT('The 600 px frame of an e-mail with a heading, a line of text and a button; every other mail block goes inside it.') },
            { label: _sdT('E-mail: Heading and Text'), type: 'component', icon: 'bi-text-paragraph', extra: { componentType: 'mail-intro' },
              desc: _sdT('A heading and two paragraphs, their look written inline as mail programs need it.') },
            { label: _sdT('E-mail: Text and Button'), type: 'component', icon: 'bi-hand-index', extra: { componentType: 'mail-button' },
              desc: _sdT('A line of text and a link drawn as a centred button; mail programs show no real buttons.') },
            { label: _sdT('E-mail: Picture and Text'), type: 'component', icon: 'bi-image', extra: { componentType: 'mail-picture' },
              desc: _sdT('A picture the width of the frame, with a text read in its place while pictures are held back, then a heading and text.') },
            { label: _sdT('E-mail: Section Break'), type: 'component', icon: 'bi-dash-lg', extra: { componentType: 'mail-break' },
              desc: _sdT('A thin line, then the heading and text of the next part of the e-mail.') },
            { label: _sdT('E-mail: Two Columns'), type: 'component', icon: 'bi-layout-split', extra: { componentType: 'mail-columns' },
              desc: _sdT('Two columns side by side that stack on a phone.') }
        ],
        build: function (ctype) {
            if (ctype === 'mail-frame') return builders['mail-frame']();
            if (ctype === 'mail-intro') return builders['mail-intro']();
            if (ctype === 'mail-button') return builders['mail-button']();
            if (ctype === 'mail-picture') return builders['mail-picture']();
            if (ctype === 'mail-break') return builders['mail-break']();
            if (ctype === 'mail-columns') return builders['mail-columns']();
            return null;
        }
    };
});
