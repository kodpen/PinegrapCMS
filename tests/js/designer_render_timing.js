/**
 * Pinegrap - Enterprise Website Platform
 *
 * Times the parts of the visual designer's edit cycle that need no DOM, on
 * the big fixture page (tests/fixtures/designer/big_page.json): the undo
 * snapshot saveState() takes, the page baseline the dirty check compares,
 * the shared component snapshot render() takes, the parse an undo makes and
 * the walk findNodeById() makes for the last node. The functions are cut out
 * of assets/js/style_designer.src.js, so the numbers follow the editor's own
 * code.
 *
 *   node tests/js/designer_render_timing.js          a table
 *   node tests/js/designer_render_timing.js --json   one JSON line (tests)
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */
'use strict';

var fs = require('fs');
var path = require('path');

var root = path.resolve(__dirname, '..', '..');
var source = fs.readFileSync(path.join(root, 'pinegrap/assets/js/style_designer.src.js'), 'utf8');
var page = JSON.parse(fs.readFileSync(path.join(root, 'tests/fixtures/designer/big_page.json'), 'utf8'));

// The source of the first declaration of `name`, braces matched.
function cut(name) {
    var from = source.indexOf('function ' + name + '(');
    if (from === -1) throw new Error(name + ' is not in the designer');
    var depth = 0;
    for (var i = source.indexOf('{', from); i < source.length; i++) {
        if (source[i] === '{') depth++;
        else if (source[i] === '}' && --depth === 0) return source.slice(from, i + 1);
    }
    throw new Error(name + ' has no end');
}

var api = new Function(
    ['_pgBaselineTreeKey', '_sdSharedTreeJSON', 'findNodeById'].map(cut).join('\n') +
    '\nreturn { baselineKey: _pgBaselineTreeKey, sharedJSON: _sdSharedTreeJSON, find: findNodeById };'
)();

function count(n) {
    var c = 1;
    (n.children || []).forEach(function (k) { c += count(k); });
    return c;
}

function lastId(n) {
    var kids = n.children || [];
    return kids.length ? lastId(kids[kids.length - 1]) : n._id;
}

// Median of `runs` timings of fn, in milliseconds, after one warm-up run.
function time(fn, runs) {
    fn();
    var list = [];
    for (var i = 0; i < runs; i++) {
        var t0 = process.hrtime.bigint();
        fn();
        list.push(Number(process.hrtime.bigint() - t0) / 1e6);
    }
    list.sort(function (a, b) { return a - b; });
    var mid = list.length >> 1;
    return list.length % 2 ? list[mid] : (list[mid - 1] + list[mid]) / 2;
}

var json = JSON.stringify(page);
var last = lastId(page);
var runs = 25;
var phases = {
    undo_snapshot: time(function () { JSON.stringify(page); }, runs),
    page_baseline: time(function () { JSON.stringify(page, api.baselineKey); }, runs),
    shared_snapshot: time(function () { api.sharedJSON(page); }, runs),
    undo_restore: time(function () { JSON.parse(json); }, runs),
    find_last_node: time(function () { api.find(last, page); }, runs)
};

var out = {
    nodes: count(page),
    bytes: Buffer.byteLength(json),
    found: !!api.find(last, page),
    phases: phases
};

if (process.argv.indexOf('--json') !== -1) {
    console.log(JSON.stringify(out));
} else {
    console.log('fixture: ' + out.nodes + ' nodes, ' + out.bytes + ' bytes; median of ' + runs + ' runs');
    Object.keys(phases).forEach(function (k) {
        console.log('  ' + (k + '                ').slice(0, 16) + phases[k].toFixed(3) + ' ms');
    });
}
