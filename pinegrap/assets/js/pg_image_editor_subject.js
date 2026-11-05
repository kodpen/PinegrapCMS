/**
 * Pinegrap - Enterprise Website Platform
 *
 * Image editor: select subject. GrabCut on a picture's pixels with no
 * trained model: colour models of five Gaussians for the subject and the
 * background, a graph of the pixels in the start box (eight neighbours,
 * edges weighted by colour contrast) cut by a Boykov-Kolmogorov max-flow,
 * the models learnt again from the cut, five rounds. The start box is the
 * rectangle the person drew, or found from a saliency map (each pixel's
 * colour distance from the picture's border colours) with its edges.
 *
 * Runs as a worker (messages below), on the page where a site's policy
 * refuses the worker (window.PgImageSubject), and under Node for the tests
 * (module.exports): the same object in each.
 *
 * Messages in:  { type: 'cut', data, width, height, box }  (data an
 *               ArrayBuffer of RGBA; box [x0, y0, x1, y1] or null)
 *          out: { type: 'mask', mask, box }  (mask an ArrayBuffer, a byte
 *               a pixel, 1 for the subject)
 * A failure answers { type: 'error', message }.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

(function (root) {
    'use strict';

    // Components of each colour model.
    var K = 5;

    // Rounds of learning the models and cutting again.
    var ROUNDS = 5;

    // The smoothness weight of a neighbour edge (GrabCut's gamma).
    var GAMMA = 50;

    // ---- max-flow (Boykov-Kolmogorov) ----

    var TERMINAL = -2, ORPHAN = -3, NONE = -1, FREE = 0, SRC = 1, SNK = 2;

    // A graph of n nodes; up to arcs directed arcs (two per edge).
    function Graph(n, arcs) {
        this.n = n;
        this.tcap = new Float64Array(n);
        this.first = new Int32Array(n).fill(-1);
        this.next = new Int32Array(arcs);
        this.to = new Int32Array(arcs);
        this.cap = new Float64Array(arcs);
        this.arcs = 0;
        this.flow = 0;
        this.tree = new Uint8Array(n);
    }

    // An edge a-b with capacity ab one way and ba the other.
    Graph.prototype.edge = function (a, b, ab, ba) {
        var i = this.arcs;

        this.to[i] = b;
        this.cap[i] = ab;
        this.next[i] = this.first[a];
        this.first[a] = i;
        this.to[i + 1] = a;
        this.cap[i + 1] = ba;
        this.next[i + 1] = this.first[b];
        this.first[b] = i + 1;
        this.arcs += 2;
    };

    // Capacities from the source and to the sink: the smaller one flows at
    // once, the rest is kept as a signed residual.
    Graph.prototype.terminal = function (i, source, sink) {
        var d = this.tcap[i];

        if (d > 0) {
            source += d;
        } else {
            sink -= d;
        }

        this.flow += Math.min(source, sink);
        this.tcap[i] = source - sink;
    };

    // The maximum flow; afterwards inSource(i) tells the cut.
    Graph.prototype.maxflow = function () {
        var n = this.n, tcap = this.tcap, first = this.first, next = this.next, to = this.to, cap = this.cap;
        var tree = this.tree, parent = new Int32Array(n).fill(NONE), ts = new Int32Array(n), dist = new Int32Array(n);
        var queued = new Uint8Array(n), queue = new Int32Array(n + 1), qHead = 0, qTail = 0, qLen = n + 1;
        var orphans = [], time = 0, i, a, j;

        function activate(v) {
            if (!queued[v]) {
                queued[v] = 1;
                queue[qTail] = v;
                qTail = (qTail + 1) % qLen;
            }
        }

        function orphan(v) {
            parent[v] = ORPHAN;
            orphans.push(v);
        }

        for (i = 0; i < n; i++) {
            tree[i] = FREE;

            if (tcap[i] !== 0) {
                tree[i] = (tcap[i] > 0) ? SRC : SNK;
                parent[i] = TERMINAL;
                dist[i] = 1;
                activate(i);
            }
        }

        while (qHead !== qTail) {
            i = queue[qHead];

            if (parent[i] === NONE) {
                queued[i] = 0;
                qHead = (qHead + 1) % qLen;
                continue;
            }

            // Grow the tree of i until it touches the other tree.
            var mid = -1;

            for (a = first[i]; a >= 0; a = next[a]) {
                j = to[a];

                if (tree[i] === SRC) {
                    if (cap[a] <= 0) {
                        continue;
                    }

                    if (tree[j] === FREE) {
                        tree[j] = SRC;
                        parent[j] = a ^ 1;
                        ts[j] = ts[i];
                        dist[j] = dist[i] + 1;
                        activate(j);
                    } else if (tree[j] === SNK) {
                        mid = a;
                        break;
                    }
                } else {
                    if (cap[a ^ 1] <= 0) {
                        continue;
                    }

                    if (tree[j] === FREE) {
                        tree[j] = SNK;
                        parent[j] = a ^ 1;
                        ts[j] = ts[i];
                        dist[j] = dist[i] + 1;
                        activate(j);
                    } else if (tree[j] === SRC) {
                        mid = a ^ 1;
                        break;
                    }
                }
            }

            if (mid < 0) {
                queued[i] = 0;
                qHead = (qHead + 1) % qLen;
                continue;
            }

            // Augment along source root .. s, mid arc, t .. sink root.
            time++;

            var s = to[mid ^ 1], t = to[mid], f = cap[mid], v;

            for (v = s; parent[v] !== TERMINAL; v = to[parent[v]]) {
                f = Math.min(f, cap[parent[v] ^ 1]);
            }

            f = Math.min(f, tcap[v]);

            for (v = t; parent[v] !== TERMINAL; v = to[parent[v]]) {
                f = Math.min(f, cap[parent[v]]);
            }

            f = Math.min(f, -tcap[v]);
            cap[mid] -= f;
            cap[mid ^ 1] += f;

            for (v = s; ; v = to[a]) {
                a = parent[v];

                if (a === TERMINAL) {
                    tcap[v] -= f;

                    if (tcap[v] <= 0) {
                        orphan(v);
                    }

                    break;
                }

                cap[a ^ 1] -= f;
                cap[a] += f;

                if (cap[a ^ 1] <= 0) {
                    orphan(v);
                }
            }

            for (v = t; ; v = to[a]) {
                a = parent[v];

                if (a === TERMINAL) {
                    tcap[v] += f;

                    if (tcap[v] >= 0) {
                        orphan(v);
                    }

                    break;
                }

                cap[a] -= f;
                cap[a ^ 1] += f;

                if (cap[a] <= 0) {
                    orphan(v);
                }
            }

            this.flow += f;

            // Adopt the orphans: a new parent in the same tree whose path
            // still reaches the terminal, else free them and their children.
            while (orphans.length) {
                var o = orphans.pop(), side = tree[o], best = NONE, bestD = Infinity;

                for (a = first[o]; a >= 0; a = next[a]) {
                    j = to[a];

                    if ((tree[j] !== side) || (parent[j] === NONE) || ((side === SRC) ? cap[a ^ 1] <= 0 : cap[a] <= 0)) {
                        continue;
                    }

                    var d = 0, k = j;

                    for (;;) {
                        if (ts[k] === time) {
                            d += dist[k];
                            break;
                        }

                        var p = parent[k];

                        d++;

                        if (p === TERMINAL) {
                            ts[k] = time;
                            dist[k] = 1;
                            break;
                        }

                        if (p === ORPHAN || p === NONE) {
                            d = Infinity;
                            break;
                        }

                        k = to[p];
                    }

                    if (d < Infinity) {
                        if (d < bestD) {
                            best = a;
                            bestD = d;
                        }

                        for (k = j; ts[k] !== time; k = to[parent[k]]) {
                            ts[k] = time;
                            dist[k] = d--;
                        }
                    }
                }

                if (best !== NONE) {
                    parent[o] = best;
                    ts[o] = time;
                    dist[o] = bestD + 1;
                    continue;
                }

                for (a = first[o]; a >= 0; a = next[a]) {
                    j = to[a];

                    if ((tree[j] !== side) || (parent[j] === NONE)) {
                        continue;
                    }

                    if ((side === SRC) ? cap[a ^ 1] > 0 : cap[a] > 0) {
                        activate(j);
                    }

                    if ((parent[j] >= 0) && (to[parent[j]] === o)) {
                        orphan(j);
                    }
                }

                tree[o] = FREE;
                parent[o] = NONE;
            }
        }

        return this.flow;
    };

    // Whether node i ends on the source's side of the minimum cut.
    Graph.prototype.inSource = function (i) {
        return this.tree[i] === SRC;
    };

    // ---- colour models ----

    // A mixture of K Gaussians over RGB.
    function Gmm() {
        this.w = new Float64Array(K);
        this.mean = new Float64Array(K * 3);
        this.inv = new Float64Array(K * 9);
        this.norm = new Float64Array(K);
    }

    // Learn the model from the pixels of idx (indices into data) and their
    // components comp.
    Gmm.prototype.learn = function (data, idx, comp) {
        var sums = new Float64Array(K * 3), prods = new Float64Array(K * 9), counts = new Float64Array(K), total = idx.length, i, c, p;

        for (i = 0; i < total; i++) {
            p = idx[i] * 4;
            c = comp[i];

            var r = data[p], g = data[p + 1], b = data[p + 2], s = c * 3, q = c * 9;

            counts[c]++;
            sums[s] += r;
            sums[s + 1] += g;
            sums[s + 2] += b;
            prods[q] += r * r;
            prods[q + 1] += r * g;
            prods[q + 2] += r * b;
            prods[q + 4] += g * g;
            prods[q + 5] += g * b;
            prods[q + 8] += b * b;
        }

        for (c = 0; c < K; c++) {
            this.w[c] = total ? counts[c] / total : 0;

            if (!counts[c]) {
                this.norm[c] = 0;
                continue;
            }

            var n = counts[c], m0 = sums[c * 3] / n, m1 = sums[c * 3 + 1] / n, m2 = sums[c * 3 + 2] / n, P = prods.subarray(c * 9, c * 9 + 9);

            this.mean[c * 3] = m0;
            this.mean[c * 3 + 1] = m1;
            this.mean[c * 3 + 2] = m2;

            // The covariance, kept invertible by a small diagonal.
            var c00 = P[0] / n - m0 * m0 + 0.01, c01 = P[1] / n - m0 * m1, c02 = P[2] / n - m0 * m2;
            var c11 = P[4] / n - m1 * m1 + 0.01, c12 = P[5] / n - m1 * m2, c22 = P[8] / n - m2 * m2 + 0.01;
            var det = c00 * (c11 * c22 - c12 * c12) - c01 * (c01 * c22 - c12 * c02) + c02 * (c01 * c12 - c11 * c02);

            if (det <= 1e-9) {
                c00 += 1;
                c11 += 1;
                c22 += 1;
                det = c00 * (c11 * c22 - c12 * c12) - c01 * (c01 * c22 - c12 * c02) + c02 * (c01 * c12 - c11 * c02);
            }

            var I = this.inv.subarray(c * 9, c * 9 + 9);

            I[0] = (c11 * c22 - c12 * c12) / det;
            I[1] = I[3] = (c02 * c12 - c01 * c22) / det;
            I[2] = I[6] = (c01 * c12 - c02 * c11) / det;
            I[4] = (c00 * c22 - c02 * c02) / det;
            I[5] = I[7] = (c01 * c02 - c00 * c12) / det;
            I[8] = (c00 * c11 - c01 * c01) / det;
            this.norm[c] = this.w[c] / Math.sqrt(det);
        }
    };

    // The density of component c (without the constant 2 pi factor).
    Gmm.prototype.comp = function (c, r, g, b) {
        if (!this.norm[c]) {
            return 0;
        }

        var d0 = r - this.mean[c * 3], d1 = g - this.mean[c * 3 + 1], d2 = b - this.mean[c * 3 + 2], I = this.inv, q = c * 9;
        var e = d0 * (d0 * I[q] + d1 * I[q + 1] + d2 * I[q + 2]) + d1 * (d0 * I[q + 3] + d1 * I[q + 4] + d2 * I[q + 5])
            + d2 * (d0 * I[q + 6] + d1 * I[q + 7] + d2 * I[q + 8]);

        return this.norm[c] * Math.exp(-0.5 * e);
    };

    // The component most likely to hold a colour.
    Gmm.prototype.best = function (r, g, b) {
        var best = 0, top = -1, c, v;

        for (c = 0; c < K; c++) {
            v = this.comp(c, r, g, b);

            if (v > top) {
                top = v;
                best = c;
            }
        }

        return best;
    };

    // The cost of a colour under the model: -log of its density.
    Gmm.prototype.cost = function (r, g, b) {
        var s = 0, c;

        for (c = 0; c < K; c++) {
            s += this.comp(c, r, g, b);
        }

        return -Math.log(Math.max(s, 1e-300));
    };

    // Components for the first learning: k-means (ten rounds) from K
    // pixels spread through the set.
    function kmeans(data, idx) {
        var n = idx.length, comp = new Uint8Array(n), centres = new Float64Array(K * 3), round, i, c;

        for (c = 0; c < K; c++) {
            var p = idx[Math.floor((c + 0.5) * n / K)] * 4;

            centres[c * 3] = data[p];
            centres[c * 3 + 1] = data[p + 1];
            centres[c * 3 + 2] = data[p + 2];
        }

        for (round = 0; round < 10; round++) {
            var sums = new Float64Array(K * 4);

            for (i = 0; i < n; i++) {
                var q = idx[i] * 4, best = 0, bestD = Infinity;

                for (c = 0; c < K; c++) {
                    var d0 = data[q] - centres[c * 3], d1 = data[q + 1] - centres[c * 3 + 1], d2 = data[q + 2] - centres[c * 3 + 2];
                    var d = d0 * d0 + d1 * d1 + d2 * d2;

                    if (d < bestD) {
                        bestD = d;
                        best = c;
                    }
                }

                comp[i] = best;
                sums[best * 4] += data[q];
                sums[best * 4 + 1] += data[q + 1];
                sums[best * 4 + 2] += data[q + 2];
                sums[best * 4 + 3]++;
            }

            for (c = 0; c < K; c++) {
                if (sums[c * 4 + 3]) {
                    centres[c * 3] = sums[c * 4] / sums[c * 4 + 3];
                    centres[c * 3 + 1] = sums[c * 4 + 1] / sums[c * 4 + 3];
                    centres[c * 3 + 2] = sums[c * 4 + 2] / sums[c * 4 + 3];
                }
            }
        }

        return comp;
    }

    // ---- GrabCut ----

    // Labels of a pixel.
    var BG = 0, FG = 1, PR_BG = 2, PR_FG = 3;

    // A box [x0, y0, x1, y1] (x1, y1 past the last pixel) inside w x h with
    // at least a pixel of background round it; null when none fits.
    function clampBox(box, w, h) {
        if (!box || (w < 3) || (h < 3)) {
            return null;
        }

        var x0 = Math.max(1, Math.round(box[0])), y0 = Math.max(1, Math.round(box[1]));
        var x1 = Math.min(w - 1, Math.round(box[2])), y1 = Math.min(h - 1, Math.round(box[3]));

        return ((x1 - x0 >= 2) && (y1 - y0 >= 2)) ? [x0, y0, x1, y1] : null;
    }

    // The subject's mask (a byte a pixel, 1 inside) of data (RGBA, w x h)
    // from the start box; opts.rounds, opts.labels (a byte a pixel: BG, FG,
    // PR_BG, PR_FG, which the box then does not set).
    function grabcut(data, w, h, box, opts) {
        opts = opts || {};

        var n = w * h, labels = opts.labels ? new Uint8Array(opts.labels) : new Uint8Array(n), x, y, i, round;

        if (!opts.labels) {
            box = clampBox(box, w, h);

            if (!box) {
                return new Uint8Array(n);
            }

            for (y = box[1]; y < box[3]; y++) {
                for (x = box[0]; x < box[2]; x++) {
                    labels[y * w + x] = PR_FG;
                }
            }
        }

        // The pixels the cut decides, and each one's node.
        var node = new Int32Array(n).fill(-1), free = [];

        for (i = 0; i < n; i++) {
            if ((labels[i] === PR_BG) || (labels[i] === PR_FG)) {
                node[i] = free.length;
                free.push(i);
            }
        }

        var mask = new Uint8Array(n);

        for (i = 0; i < n; i++) {
            mask[i] = ((labels[i] === FG) || (labels[i] === PR_FG)) ? 1 : 0;
        }

        if (!free.length) {
            return mask;
        }

        // Neighbour edges: right, down, down-right, down-left; beta from
        // the mean contrast.
        var DIRS = [[1, 0, 1], [0, 1, 1], [1, 1, Math.SQRT1_2], [-1, 1, Math.SQRT1_2]];
        var sum = 0, count = 0;

        for (y = 0; y < h; y++) {
            for (x = 0; x < w; x++) {
                DIRS.forEach(function (d) {
                    var nx = x + d[0], ny = y + d[1];

                    if ((nx >= 0) && (nx < w) && (ny < h)) {
                        sum += diff2(data, (y * w + x) * 4, (ny * w + nx) * 4);
                        count++;
                    }
                });
            }
        }

        var beta = (sum > 0) ? count / (2 * sum) : 0;
        var weights = new Float32Array(n * 4);

        for (y = 0; y < h; y++) {
            for (x = 0; x < w; x++) {
                for (var dk = 0; dk < 4; dk++) {
                    var d = DIRS[dk], nx = x + d[0], ny = y + d[1];

                    if ((nx >= 0) && (nx < w) && (ny < h)) {
                        weights[(y * w + x) * 4 + dk] = GAMMA * d[2] * Math.exp(-beta * diff2(data, (y * w + x) * 4, (ny * w + nx) * 4));
                    }
                }
            }
        }

        var fg = new Gmm(), bg = new Gmm(), rounds = opts.rounds || ROUNDS;

        for (round = 0; round < rounds; round++) {
            var fgIdx = [], bgIdx = [];

            for (i = 0; i < n; i++) {
                (mask[i] ? fgIdx : bgIdx).push(i);
            }

            if (!fgIdx.length || !bgIdx.length) {
                break;
            }

            if (!round) {
                fg.learn(data, fgIdx, kmeans(data, fgIdx));
                bg.learn(data, bgIdx, kmeans(data, bgIdx));
            } else {
                fg.learn(data, fgIdx, assign(fg, data, fgIdx));
                bg.learn(data, bgIdx, assign(bg, data, bgIdx));
            }

            var g = new Graph(free.length, free.length * 8);

            free.forEach(function (p, k) {
                var q = p * 4, source = bg.cost(data[q], data[q + 1], data[q + 2]), sink = fg.cost(data[q], data[q + 1], data[q + 2]);
                var px = p % w, py = (p - px) / w;

                for (var e = 0; e < 8; e++) {
                    var dd = DIRS[e % 4], sg = (e < 4) ? 1 : -1, nx = px + sg * dd[0], ny = py + sg * dd[1];

                    if ((nx < 0) || (nx >= w) || (ny < 0) || (ny >= h)) {
                        continue;
                    }

                    var o = ny * w + nx, wt = (e < 4) ? weights[p * 4 + e] : weights[o * 4 + (e - 4)];

                    if (node[o] >= 0) {
                        // Each edge once: from the node with the lower index.
                        if (e < 4) {
                            g.edge(k, node[o], wt, wt);
                        }
                    } else if (labels[o] === FG) {
                        source += wt;
                    } else {
                        sink += wt;
                    }
                }

                g.terminal(k, source, sink);
            });

            g.maxflow();

            free.forEach(function (p, k) {
                mask[p] = g.inSource(k) ? 1 : 0;
            });
        }

        return mask;
    }

    function diff2(data, a, b) {
        var d0 = data[a] - data[b], d1 = data[a + 1] - data[b + 1], d2 = data[a + 2] - data[b + 2];

        return d0 * d0 + d1 * d1 + d2 * d2;
    }

    function assign(gmm, data, idx) {
        var comp = new Uint8Array(idx.length);

        for (var i = 0; i < idx.length; i++) {
            var q = idx[i] * 4;

            comp[i] = gmm.best(data[q], data[q + 1], data[q + 2]);
        }

        return comp;
    }

    // ---- the start box ----

    // Where the subject probably is: each pixel's colour distance from the
    // nearest of four border colours, with its edge strength, kept above
    // the mean and a half deviation; the box from the 2nd to the 98th
    // percentile of those pixels each way, grown by 4 %. Null on a flat
    // picture.
    function startBox(data, w, h) {
        var n = w * h, band = Math.max(1, Math.round(Math.min(w, h) * 0.04)), border = [], x, y, i;

        for (y = 0; y < h; y++) {
            for (x = 0; x < w; x++) {
                if ((x < band) || (y < band) || (x >= w - band) || (y >= h - band)) {
                    border.push(y * w + x);
                }
            }
        }

        var comp = kmeans(data, border), centres = [], c;

        for (c = 0; c < K; c++) {
            centres.push(null);
        }

        border.forEach(function (p, k) {
            var q = p * 4, s = centres[comp[k]] || (centres[comp[k]] = [0, 0, 0, 0]);

            s[0] += data[q];
            s[1] += data[q + 1];
            s[2] += data[q + 2];
            s[3]++;
        });
        centres = centres.filter(Boolean).map(function (s) { return [s[0] / s[3], s[1] / s[3], s[2] / s[3]]; });

        // The pictures' colours are smoothed by a 3 x 3 box first, so noise
        // does not read as subject.
        var smooth = new Float32Array(n * 3), sal = new Float32Array(n), edge = new Float32Array(n), lum = new Float32Array(n);

        for (y = 0; y < h; y++) {
            for (x = 0; x < w; x++) {
                var s0 = 0, s1 = 0, s2 = 0, m = 0;

                for (var dy = -1; dy <= 1; dy++) {
                    for (var dx = -1; dx <= 1; dx++) {
                        var xx = x + dx, yy = y + dy;

                        if ((xx >= 0) && (yy >= 0) && (xx < w) && (yy < h)) {
                            var q = (yy * w + xx) * 4;

                            s0 += data[q];
                            s1 += data[q + 1];
                            s2 += data[q + 2];
                            m++;
                        }
                    }
                }

                i = y * w + x;
                smooth[i * 3] = s0 / m;
                smooth[i * 3 + 1] = s1 / m;
                smooth[i * 3 + 2] = s2 / m;
                lum[i] = 0.299 * s0 / m + 0.587 * s1 / m + 0.114 * s2 / m;
            }
        }

        var maxSal = 0, maxEdge = 0;

        for (i = 0; i < n; i++) {
            var best = Infinity;

            centres.forEach(function (ce) {
                var a = smooth[i * 3] - ce[0], b = smooth[i * 3 + 1] - ce[1], cc = smooth[i * 3 + 2] - ce[2];

                best = Math.min(best, a * a + b * b + cc * cc);
            });
            sal[i] = Math.sqrt(best);
            maxSal = Math.max(maxSal, sal[i]);
        }

        for (y = 1; y < h - 1; y++) {
            for (x = 1; x < w - 1; x++) {
                i = y * w + x;
                edge[i] = Math.hypot(lum[i + 1] - lum[i - 1], lum[i + w] - lum[i - w]);
                maxEdge = Math.max(maxEdge, edge[i]);
            }
        }

        if (maxSal < 8) {
            return null;
        }

        var score = new Float32Array(n), mean = 0, sq = 0;

        for (i = 0; i < n; i++) {
            score[i] = 0.75 * sal[i] / maxSal + (maxEdge ? 0.25 * edge[i] / maxEdge : 0);
            mean += score[i];
            sq += score[i] * score[i];
        }

        mean /= n;

        var limit = mean + 0.5 * Math.sqrt(Math.max(0, sq / n - mean * mean)), xs = new Uint32Array(w), ys = new Uint32Array(h), total = 0;

        for (y = 0; y < h; y++) {
            for (x = 0; x < w; x++) {
                if (score[y * w + x] > limit) {
                    xs[x]++;
                    ys[y]++;
                    total++;
                }
            }
        }

        if (!total) {
            return null;
        }

        var pct = function (hist, f) {
            var acc = 0;

            for (var k = 0; k < hist.length; k++) {
                acc += hist[k];

                if (acc > f * total) {
                    return k;
                }
            }

            return hist.length - 1;
        };
        var x0 = pct(xs, 0.02), x1 = pct(xs, 0.98) + 1, y0 = pct(ys, 0.02), y1 = pct(ys, 0.98) + 1;
        var gx = Math.round((x1 - x0) * 0.04) + 1, gy = Math.round((y1 - y0) * 0.04) + 1;

        return clampBox([x0 - gx, y0 - gy, x1 + gx, y1 + gy], w, h);
    }

    // The subject of a picture: its mask and the box it started from.
    function subject(data, w, h, box) {
        var start = box ? clampBox(box, w, h) : startBox(data, w, h);

        return { mask: start ? grabcut(data, w, h, start) : new Uint8Array(w * h), box: start };
    }

    // A message session (see the head of the file); post(answer, transfer).
    function Session(post) {
        this.post = post;
    }

    Session.prototype.receive = function (m) {
        if (!m || (m.type !== 'cut')) {
            return;
        }

        try {
            var w = m.width | 0, h = m.height | 0, data = new Uint8ClampedArray(m.data || 0);

            if ((w < 1) || (h < 1) || (data.length !== w * h * 4)) {
                throw new Error('The pixels do not match the size.');
            }

            var out = subject(data, w, h, m.box || null);

            this.post({ type: 'mask', mask: out.mask.buffer, box: out.box }, [out.mask.buffer]);
        } catch (err) {
            this.post({ type: 'error', message: String(err && err.message || err) });
        }
    };

    Session.prototype.stop = function () {};

    var api = {
        Graph: Graph,
        grabcut: grabcut,
        startBox: startBox,
        clampBox: clampBox,
        subject: subject,
        session: function (post) { return new Session(post); },
        BG: BG, FG: FG, PR_BG: PR_BG, PR_FG: PR_FG
    };

    if ((typeof module === 'object') && module.exports) {
        module.exports = api;
        return;
    }

    // A worker: the messages above.
    if ((typeof root.importScripts === 'function') && (typeof root.document === 'undefined')) {
        var session = new Session(function (a, transfer) { root.postMessage(a, transfer || []); });

        root.onmessage = function (e) { session.receive(e.data || {}); };

        return;
    }

    root.PgImageSubject = api;
}((typeof self !== 'undefined') ? self : this));
