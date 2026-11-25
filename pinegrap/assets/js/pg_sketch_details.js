/**
 * Pinegrap - Enterprise Website Platform
 *
 * The values of a sketch page's "Page details" window in the designer's
 * Prototype view (window.PGSketchDetails): the address made from a name,
 * the window's values from what the sketch keeps, the checks before a
 * save and what is kept after it. Pure, no DOM; the window itself is
 * assets/js/style_designer_sketchdetails.js.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */
(function (root) {
    'use strict';

    // The longest name, address (the page_name column), title and
    // description (page_title and page_meta_description) kept.
    var LIMITS = { name: 120, address: 100, title: 255, description: 255 };

    // The page types the window offers: a standard page, and the types
    // that bring a system widget, keyed by the sketch reader's widget
    // kinds (catalog, blog, booking), so the build puts the widget where
    // the sketch has a box of that kind.
    var TYPES = [
        { key: 'standard', widget: null },
        { key: 'catalog', widget: 'catalog' },
        { key: 'blog', widget: 'blog' },
        { key: 'booking', widget: 'booking' }
    ];

    var FOLD_FROM = 'ıİIşŞğĞçÇöÖüÜâÂîÎûÛ';
    var FOLD_TO = 'iiissggccoouuaaiiuu';

    function str(v, max) {
        return String(v == null ? '' : v).replace(/\s+/g, ' ').trim().slice(0, max);
    }

    function int(v) {
        v = Number(v);
        return isFinite(v) && v > 0 ? Math.floor(v) : 0;
    }

    function type(key) {
        return TYPES.filter(function (t) { return t.key === key; })[0] || null;
    }

    // The address a name makes: lower case, Turkish letters and letters
    // with a mark as the plain letter, every run of anything else one
    // hyphen, none at either end ("Yoga Sınıfları" gives
    // "yoga-siniflari").
    function address(name) {
        var s = String(name == null ? '' : name), out = '';
        for (var i = 0; i < s.length; i++) {
            var c = s.charAt(i), k = FOLD_FROM.indexOf(c);
            if (k !== -1) { out += FOLD_TO.charAt(k); continue; }
            var low = c.toLowerCase();
            if (low.length !== 1) low = c;
            if (low.normalize) low = low.normalize('NFD').charAt(0) || low;
            out += low;
        }
        return out.replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '').slice(0, LIMITS.address).replace(/-+$/, '');
    }

    // What a sketch page keeps of its details, made safe: null when there
    // is nothing (a page never saved from the window).
    function keep(raw) {
        if (!raw || typeof raw !== 'object') return null;
        return {
            address: str(raw.address, LIMITS.address),
            typed: !!raw.typed,
            type: type(raw.type) ? raw.type : 'standard',
            menu: int(raw.menu),
            menuName: str(raw.menuName, 100),
            after: int(raw.after),
            afterName: str(raw.afterName, 100),
            title: str(raw.title, LIMITS.title),
            description: str(raw.description, LIMITS.description),
            folder: int(raw.folder)
        };
    }

    // The window's values for a sketch page: its name, or `fallback` (the
    // name the tool shows); what it keeps; else the defaults, which need
    // no choice: the address made from the name, a standard page, in no
    // menu, no title or description, and `folder` (the design's own).
    // A kept folder that is no longer offered (`folders`, ids) gives way
    // to `folder`.
    function values(page, opts) {
        opts = opts || {};
        var name = str(page && page.name, LIMITS.name) || str(opts.fallback, LIMITS.name);
        var d = keep(page && page.details);
        var folders = Array.isArray(opts.folders) ? opts.folders : null;
        var folder = d && d.folder ? d.folder : int(opts.folder);
        if (folders && folders.indexOf(folder) === -1) folder = int(opts.folder);
        var typed = !!(d && d.typed && d.address);
        return {
            name: name,
            address: typed ? d.address : address(name),
            typed: typed,
            type: d ? d.type : 'standard',
            menu: d ? d.menu : 0,
            menuName: d ? d.menuName : '',
            after: d ? d.after : 0,
            afterName: d ? d.afterName : '',
            title: d ? d.title : '',
            description: d ? d.description : '',
            folder: folder
        };
    }

    // Why the values cannot be saved, in the window's order: [{field,
    // code, page}]. Codes: name 'empty'; address 'empty', 'chars' (a ?, %
    // or + or a control character: the page would not open), 'long',
    // 'used' (`page` the page or sketch page that has it), 'taken' (a
    // file or an address the site answers itself); title and description
    // 'long'. ctx: {used: [{name, address}] the other pages of the design
    // and the other sketch pages, server: {used: page name, taken: bool}
    // the server's answer for this address, or null}.
    function check(v, ctx) {
        ctx = ctx || {};
        var out = [];
        var name = String(v.name || '').trim();
        var addr = String(v.address || '').trim();
        if (!name) out.push({ field: 'name', code: 'empty', page: '' });
        if (!addr) out.push({ field: 'address', code: 'empty', page: '' });
        else if (/[?%+\x00-\x1f\x7f]/.test(addr)) out.push({ field: 'address', code: 'chars', page: '' });
        else if (addr.length > LIMITS.address) out.push({ field: 'address', code: 'long', page: '' });
        else {
            var low = addr.toLowerCase();
            var mine = (Array.isArray(ctx.used) ? ctx.used : []).filter(function (p) { return String(p.address || '').toLowerCase() === low; })[0];
            var server = ctx.server || null;
            if (mine) out.push({ field: 'address', code: 'used', page: String(mine.name || mine.address) });
            else if (server && server.used) out.push({ field: 'address', code: 'used', page: String(server.used) });
            else if (server && server.taken) out.push({ field: 'address', code: 'taken', page: '' });
        }
        if (String(v.title || '').length > LIMITS.title) out.push({ field: 'title', code: 'long', page: '' });
        if (String(v.description || '').length > LIMITS.description) out.push({ field: 'description', code: 'long', page: '' });
        return out;
    }

    // What the sketch keeps once the values are saved: the name for the
    // frame's head, the rest as its details (keep()); a menu's item only
    // with the menu.
    function stored(v) {
        var d = keep(v);
        if (!d.menu) { d.menuName = ''; d.after = 0; d.afterName = ''; }
        if (!d.typed) d.address = address(v.name);
        return { name: str(v.name, LIMITS.name), details: d };
    }

    root.PGSketchDetails = {
        LIMITS: LIMITS,
        TYPES: TYPES,
        type: type,
        address: address,
        keep: keep,
        values: values,
        check: check,
        stored: stored
    };
})(window);
