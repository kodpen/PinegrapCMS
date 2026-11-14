/* Agent drawing guides: the proposed guide's values and a model drawing of each chart kind as the
   server would write it (plain SVG, solid colours, Inter), on a 1200 x 675 board unless told otherwise. */
var G = {
  board: { w: 1200, h: 675, margin: 48, gutter: 24, cols: 12, unit: 8 },
  type: { title: [44, 700], sub: [24, 400], figure: [72, 700], label: [20, 600], axis: [18, 400], note: [16, 400] },
  font: 'Inter, sans-serif',
  dark: { bg: '#14161c', ink: '#eef0f3', ink2: '#a4a9b1', ink3: '#6d737c', grid: '#262930', base: '#3a3e47',
    series: ['#5892ea', '#00ac86', '#9580e0', '#be8700', '#d46d83'], other: '#5d636c', up: '#3fd0a8', down: '#f58aa0' },
  light: { bg: '#ffffff', ink: '#12151a', ink2: '#58606a', ink3: '#8a919b', grid: '#eceef1', base: '#c9cdd3',
    series: ['#2f6fed', '#0e9a74', '#7150e6', '#b7791f', '#d43d6b'], other: '#a3a9b1', up: '#0e9a74', down: '#d43d6b' }
};
function gEsc(s) { return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;'); }
function gMix(a, b, t) { var p = function (h, i) { return parseInt(h.substr(i, 2), 16); }, o = '#';
  for (var i = 1; i < 7; i += 2) o += ('0' + Math.round(p(a, i) * t + p(b, i) * (1 - t)).toString(16)).slice(-2); return o; }
function gText(x, y, s, size, weight, fill, anchor) {
  return '<text x="' + x + '" y="' + y + '" font-family="' + G.font + '" font-size="' + size + '" font-weight="' + weight + '" fill="' + fill + '"' + (anchor ? ' text-anchor="' + anchor + '"' : '') + '>' + gEsc(s) + '</text>'; }
function gNum(v) { return v >= 10000 ? (Math.round(v / 100) / 10).toLocaleString('tr-TR') + ' B' : v.toLocaleString('tr-TR'); }
function gNice(max) { var step = Math.pow(10, Math.floor(Math.log10(max / 4))), m = [1, 2, 2.5, 5, 10];
  for (var i = 0; i < m.length; i++) if (max / (step * m[i]) <= 5) return step * m[i]; return step * 10; }
/* head: title, subtitle, legend; returns the top of the plot */
function gHead(t, o, P) {
  var m = G.board.margin, s = gText(m, m + 36, o.title, 44, 700, P.ink) + gText(m, m + 76, o.sub, 24, 400, P.ink2), y = m + 76;
  if (o.legend) { var x = m; y += 44;
    o.legend.forEach(function (n, i) { s += '<rect x="' + x + '" y="' + (y - 12) + '" width="14" height="14" rx="4" fill="' + (i < 5 ? P.series[i] : P.other) + '"/>' + gText(x + 22, y + 1, n, 20, 600, P.ink2); x += 50 + n.length * 11; }); }
  if (o.note) s += gText(m, t.h - m + 10, o.note, 16, 400, P.ink3);
  return { svg: s, top: y + 40 };
}
function gBoard(t, inner, P) { return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' + t.w + ' ' + t.h + '" width="' + t.dw + '"><rect width="' + t.w + '" height="' + t.h + '" fill="' + P.bg + '"/>' + inner + '</svg>'; }
/* a value axis: gridlines and ticks on the left; returns the y of a value */
function gAxis(x0, x1, top, bot, max, P) { var step = gNice(max), n = Math.ceil(max / step), s = '', y;
  for (var i = 0; i <= n; i++) { y = bot - (bot - top) * i / n;
    s += '<rect x="' + x0 + '" y="' + (i ? y : y - 0.5) + '" width="' + (x1 - x0) + '" height="' + (i ? 1 : 2) + '" fill="' + (i ? P.grid : P.base) + '"/>' + gText(x0 - 14, y + 6, gNum(step * i), 18, 400, P.ink3, 'end'); }
  return { s: s, y: function (v) { return bot - (bot - top) * v / (step * n); } };
}
function gBars(o, P, stacked) {
  var t = { w: 1200, h: 675, dw: o.dw }, h = gHead(t, o, P), m = 48, x0 = m + 64, x1 = t.w - m, bot = t.h - m - 40, s = h.svg;
  var tot = o.labels.map(function (_, i) { return o.series.reduce(function (a, r) { return stacked ? a + r[i] : Math.max(a, r[i]); }, 0); });
  var ax = gAxis(x0, x1, h.top, bot, Math.max.apply(null, tot), P); s += ax.s;
  var band = (x1 - x0) / o.labels.length, k = stacked ? 1 : o.series.length, bw = Math.min(40, band * 0.56 / k);
  o.labels.forEach(function (lab, i) { var cx = x0 + band * (i + 0.5), acc = 0;
    o.series.forEach(function (r, j) { var x = stacked ? cx - bw / 2 : cx - (k * bw + (k - 1) * 4) / 2 + j * (bw + 4), y0 = ax.y(acc), y1 = ax.y(acc + r[i]), top = (!stacked || j === o.series.length - 1), hgt = y0 - y1 - (stacked && j ? 2 : 0);
      s += top ? '<path d="M' + x + ' ' + (y0 - (stacked && j ? 2 : 0)) + 'V' + (y1 + 6) + 'Q' + x + ' ' + y1 + ' ' + (x + 6) + ' ' + y1 + 'H' + (x + bw - 6) + 'Q' + (x + bw) + ' ' + y1 + ' ' + (x + bw) + ' ' + (y1 + 6) + 'V' + (y0 - (stacked && j ? 2 : 0)) + 'Z" fill="' + P.series[j] + '"/>'
               : '<rect x="' + x + '" y="' + y1 + '" width="' + bw + '" height="' + hgt + '" fill="' + P.series[j] + '"/>';
      if (stacked) acc += r[i]; });
    s += gText(cx, bot + 32, lab, 18, 400, P.ink3, 'middle');
    if (o.mark === i) s += gText(cx, ax.y(tot[i]) - 14, gNum(tot[i]), 20, 600, P.ink, 'middle'); });
  return gBoard(t, s, P);
}
function gLine(o, P, area) {
  var t = { w: 1200, h: 675, dw: o.dw }, h = gHead(t, o, P), m = 48, x0 = m + 64, x1 = t.w - m - 90, bot = t.h - m - 40, s = h.svg;
  var max = Math.max.apply(null, o.series.map(function (r) { return Math.max.apply(null, r); })), ax = gAxis(x0, x1, h.top, bot, max, P); s += ax.s;
  var n = o.labels.length, X = function (i) { return x0 + 12 + (x1 - x0 - 24) * i / (n - 1); };
  o.labels.forEach(function (lab, i) { if (i % (o.every || 1) === 0 || i === n - 1) s += gText(X(i), bot + 32, lab, 18, 400, P.ink3, 'middle'); });
  o.series.forEach(function (r, j) { var c = P.series[j], d = r.map(function (v, i) { return (i ? 'L' : 'M') + X(i).toFixed(1) + ' ' + ax.y(v).toFixed(1); }).join('');
    if (area) s += '<path d="' + d + 'L' + X(n - 1) + ' ' + bot + 'L' + X(0) + ' ' + bot + 'Z" fill="' + gMix(c, P.bg, 0.14) + '"/>';
    s += '<path d="' + d + '" fill="none" stroke="' + c + '" stroke-width="3" stroke-linejoin="round" stroke-linecap="round"/>';
    var ly = ax.y(r[n - 1]); s += '<circle cx="' + X(n - 1) + '" cy="' + ly + '" r="9" fill="' + P.bg + '"/><circle cx="' + X(n - 1) + '" cy="' + ly + '" r="6" fill="' + c + '"/>' + gText(X(n - 1) + 18, ly + 6, gNum(r[n - 1]), 20, 600, P.ink); });
  return gBoard(t, s, P);
}
function gDonut(o, P) {
  var t = { w: 1200, h: 675, dw: o.dw }, h = gHead(t, Object.assign({}, o, { legend: null }), P), s = h.svg, cx = 330, cy = 400, R = 190, r = 128, tot = o.values.reduce(function (a, b) { return a + b; }, 0), a = -Math.PI / 2;
  o.values.forEach(function (v, i) { var b = a + 2 * Math.PI * v / tot, g = 0.012, p = function (rr, ang) { return (cx + rr * Math.cos(ang)).toFixed(1) + ' ' + (cy + rr * Math.sin(ang)).toFixed(1); }, L = (b - a) > Math.PI ? 1 : 0;
    s += '<path d="M' + p(R, a + g) + 'A' + R + ' ' + R + ' 0 ' + L + ' 1 ' + p(R, b - g) + 'L' + p(r, b - g * R / r) + 'A' + r + ' ' + r + ' 0 ' + L + ' 0 ' + p(r, a + g * R / r) + 'Z" fill="' + (i < 5 ? P.series[i] : P.other) + '"/>'; a = b; });
  s += gText(cx, cy + 8, gNum(tot), 56, 700, P.ink, 'middle') + gText(cx, cy + 44, o.unit, 18, 400, P.ink3, 'middle');
  o.names.forEach(function (n, i) { var y = 250 + i * 64;
    s += '<rect x="620" y="' + (y - 15) + '" width="18" height="18" rx="5" fill="' + (i < 5 ? P.series[i] : P.other) + '"/>' + gText(652, y, n, 22, 600, P.ink) + gText(1152, y, Math.round(o.values[i] / tot * 100) + ' %', 22, 600, P.ink, 'end') + gText(1040, y, gNum(o.values[i]), 22, 400, P.ink3, 'end');
    if (i < o.names.length - 1) s += '<rect x="620" y="' + (y + 24) + '" width="532" height="1" fill="' + P.grid + '"/>'; });
  return gBoard(t, s, P);
}
function gSpark(r, x, y, w, hh, P, c) { var mx = Math.max.apply(null, r), mn = Math.min.apply(null, r), n = r.length, X = function (i) { return x + w * i / (n - 1); }, Y = function (v) { return y + hh - hh * (v - mn) / (mx - mn || 1); };
  var d = r.map(function (v, i) { return (i ? 'L' : 'M') + X(i).toFixed(1) + ' ' + Y(v).toFixed(1); }).join('');
  return '<path d="' + d + 'L' + (x + w) + ' ' + (y + hh) + 'L' + x + ' ' + (y + hh) + 'Z" fill="' + gMix(c, P.bg, 0.12) + '"/><path d="' + d + '" fill="none" stroke="' + c + '" stroke-width="3" stroke-linejoin="round" stroke-linecap="round"/><circle cx="' + X(n - 1) + '" cy="' + Y(r[n - 1]) + '" r="8" fill="' + P.bg + '"/><circle cx="' + X(n - 1) + '" cy="' + Y(r[n - 1]) + '" r="5" fill="' + c + '"/>'; }
function gSparkline(o, P) { var t = { w: 600, h: 120, dw: o.dw }, s = gText(24, 50, o.label, 18, 600, P.ink2) + gText(24, 92, o.value, 32, 700, P.ink) + gSpark(o.data, 240, 26, 336, 68, P, P.series[0]);
  return gBoard(t, s, P); }
function gTile(o, P) { var t = { w: 600, h: 360, dw: o.dw }, up = o.delta.charAt(0) !== '-', c = up ? P.up : P.down;
  var s = gText(40, 72, o.label, 20, 600, P.ink2) + gText(40, 158, o.value, 72, 700, P.ink)
    + '<path d="' + (up ? 'M40 206l9 -12 9 12z' : 'M40 194l9 12 9 -12z') + '" fill="' + c + '"/>' + gText(66, 207, o.delta, 18, 600, c) + gText(66 + o.delta.length * 11 + 10, 207, o.vs, 18, 400, P.ink3)
    + gSpark(o.data, 40, 246, 520, 74, P, P.series[0]);
  return gBoard(t, s, P); }
