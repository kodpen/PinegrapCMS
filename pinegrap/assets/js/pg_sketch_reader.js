/**
 * Pinegrap - Enterprise Website Platform
 *
 * The sketch reader of the visual designer's Prototype view
 * (window.PGSketchReader): the boxes drawn inside a page's frame in, the
 * page's sections out, in order from the top, each with its kind and an
 * unsure mark where the rules cannot decide. Pure: it reads what it is
 * handed and changes nothing.
 *
 * - read(frame, boxes): frame {x, y, w, h}; each box {id, x, y, w, h} in the
 *   same coordinates, with optional `text` (the typed word), `mark`
 *   ('cross' a picture, 'lines' text), `kind` (chosen from the chip's menu)
 *   and `parent` (the id of the box it was drawn in; without it a box lying
 *   inside a bigger one is its child). Answers {sections, skipped}.
 * - The rules: boxes side by side (overlapping in height) are one band and
 *   one section; a small box is a button of its band; equal boxes in a row
 *   are features, cards (each a picture and text) or a gallery (pictures
 *   only); a thin full-width band on top is the header, at the bottom the
 *   footer; a tall band right under the header is the intro; stacked
 *   fields with a button are a form; a cross is a picture, lines are text.
 *   A typed word that names a kind (English or Turkish) decides; a kind
 *   chosen from the chip's menu wins over everything.
 * - A section the rules cannot decide is never guessed in silence: it
 *   carries unsure: true, the reason ('empty', 'words', 'mixed'), the best
 *   guess as its kind and the kinds to ask about as options.
 *
 * Sizes are read against the frame's width, so a frame drawn at any zoom
 * reads the same. Nothing runs at load but the definition of
 * window.PGSketchReader. tests/sketch_reader_test.php runs it in Node.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

(function () {
    'use strict';

    // The kinds a section can be read as, in the chip menu's order.
    var KINDS = ['header', 'intro', 'features', 'cards', 'text', 'picture', 'gallery', 'form', 'cta', 'footer', 'catalog', 'blog', 'booking'];

    // The system widget a kind places.
    var WIDGETS = {catalog: 'catalog', blog: 'blog', booking: 'booking'};

    // Words that name a kind, folded (see fold()). A word of four letters or
    // more also matches a longer word it starts (blogs, randevular).
    var WORDS = {
        header: ['header', 'navbar', 'navigation', 'nav', 'logo', 'ustbilgi'],
        intro: ['intro', 'hero', 'banner', 'giris', 'karsilama', 'manset'],
        features: ['features', 'feature', 'services', 'benefits', 'why us', 'ozellikler', 'hizmetler', 'avantajlar'],
        cards: ['cards', 'team', 'testimonials', 'pricing', 'plans', 'kartlar', 'ekibimiz', 'ekip', 'yorumlar', 'fiyatlar', 'paketler'],
        text: ['text', 'about', 'content', 'story', 'metin', 'hakkimizda', 'hikayemiz', 'icerik'],
        picture: ['picture', 'image', 'photo', 'video', 'resim', 'gorsel', 'fotograf'],
        gallery: ['gallery', 'portfolio', 'galeri', 'portfoy'],
        form: ['form', 'contact', 'newsletter', 'subscribe', 'iletisim', 'bulten', 'abone'],
        cta: ['cta', 'call to action', 'sign up', 'get started', 'hemen basla', 'kayit ol'],
        footer: ['footer', 'copyright', 'altbilgi', 'telif'],
        catalog: ['catalog', 'products', 'product', 'shop', 'store', 'katalog', 'urunler', 'urun', 'magaza'],
        blog: ['blog', 'news', 'posts', 'articles', 'haberler', 'yazilar', 'makaleler'],
        booking: ['booking', 'book', 'appointment', 'reservation', 'randevu', 'rezervasyon']
    };

    // Sizes as parts of the frame's width.
    var MIN_SIDE = 0.01;      // a smaller box is a stray click
    var BUTTON_W = 0.35;      // a box at most this wide and
    var BUTTON_H = 0.07;      // this high is a button
    var BAR_EDGE = 0.06;      // a header starts and a footer ends this near the edge
    var HEADER_H = 0.12;
    var FOOTER_H = 0.2;
    var INTRO_H = 0.3;
    var INTRO_GAP = 0.2;      // the intro starts this near the header or the top
    var CTA_H = 0.2;
    var EQUAL = 0.2;          // boxes within this part of each other are equal
    var WIDE = 0.8;           // a bar is at least this wide

    function num(value) {
        return (typeof value === 'number' && isFinite(value)) ? value : null;
    }

    function str(value) {
        return (typeof value === 'string') ? value : '';
    }

    // Lower case, no accents, Turkish dotted and dotless i as i, single
    // spaces, anything but letters and digits a space.
    function fold(text) {
        var out = str(text).replace(/[İIı]/g, 'i').toLowerCase();

        if (typeof out.normalize === 'function') {
            out = out.normalize('NFD');
        }

        return out.replace(/[̀-ͯ]/g, '')
            .replace(/[^a-z0-9]+/g, ' ')
            .replace(/^ +| +$/g, '');
    }

    // The kinds a typed phrase names, in KINDS order.
    function wordKinds(text) {
        var tokens = fold(text).split(' '), found = [];

        if (tokens.length === 1 && tokens[0] === '') {
            return found;
        }

        KINDS.forEach(function (kind) {
            var hit = WORDS[kind].some(function (entry) {
                var parts = entry.split(' ');

                for (var i = 0; i + parts.length <= tokens.length; i++) {
                    var all = true;

                    for (var j = 0; j < parts.length && all; j++) {
                        var token = tokens[i + j];
                        all = (token === parts[j]) || (j === parts.length - 1 && parts[j].length >= 4 && token.indexOf(parts[j]) === 0);
                    }

                    if (all) {
                        return true;
                    }
                }

                return false;
            });

            if (hit) {
                found.push(kind);
            }
        });

        return found;
    }

    function unique(list) {
        var out = [];

        list.forEach(function (item) {
            if (item && out.indexOf(item) === -1) {
                out.push(item);
            }
        });

        return out;
    }

    function inside(inner, outer) {
        var w = Math.min(inner.x + inner.w, outer.x + outer.w) - Math.max(inner.x, outer.x);
        var h = Math.min(inner.y + inner.h, outer.y + outer.h) - Math.max(inner.y, outer.y);

        return (w > 0 && h > 0) ? (w * h) / (inner.w * inner.h) : 0;
    }

    function isButton(box, W) {
        return box.w <= BUTTON_W * W && box.h <= BUTTON_H * W;
    }

    function near(a, b) {
        var big = Math.max(a, b);

        return big <= 0 || (big - Math.min(a, b)) / big <= EQUAL;
    }

    function equalRow(list) {
        if (list.length < 2) {
            return false;
        }

        var w = list[0].w, h = list[0].h;

        return list.every(function (box) {
            return near(box.w, w) && near(box.h, h);
        });
    }

    function hasPicture(box) {
        return box.mark === 'cross' || box.children.some(hasPicture);
    }

    function hasText(box) {
        return box.mark === 'lines' || box.text !== '' || box.children.some(function (child) {
            return !child.button && (hasText(child) || !hasPicture(child));
        });
    }

    function pictureOnly(box) {
        return hasPicture(box) && !hasText(box);
    }

    function isEmpty(box) {
        return box.mark === '' && box.text === '' && box.children.length === 0;
    }

    // Boxes in bands: a box joins the first band it overlaps in height by
    // half of the smaller of the two; bands in order from the top, boxes in
    // a band from the left.
    function bandsOf(list) {
        var bands = [];

        list.slice().sort(function (a, b) {
            return (a.y - b.y) || (a.x - b.x);
        }).forEach(function (box) {
            var band = null;

            for (var i = 0; i < bands.length && !band; i++) {
                var overlap = Math.min(bands[i].bottom, box.y + box.h) - Math.max(bands[i].top, box.y);

                if (overlap > 0 && overlap >= 0.5 * Math.min(box.h, bands[i].bottom - bands[i].top)) {
                    band = bands[i];
                }
            }

            if (!band) {
                band = {top: box.y, bottom: box.y + box.h, boxes: []};
                bands.push(band);
            }

            band.boxes.push(box);
            band.top = Math.min(band.top, box.y);
            band.bottom = Math.max(band.bottom, box.y + box.h);
        });

        bands.sort(function (a, b) {
            return a.top - b.top;
        });

        bands.forEach(function (band) {
            band.boxes.sort(function (a, b) {
                return (a.x - b.x) || (a.y - b.y);
            });
        });

        return bands;
    }

    // Kind of an equal row from what each of its boxes holds.
    function rowKind(items) {
        if (items.every(pictureOnly)) {
            return 'gallery';
        }

        if (items.every(function (box) { return hasPicture(box) && hasText(box); })) {
            return 'cards';
        }

        return 'features';
    }

    // Stacked fields (wide and thin against the box) with a button.
    function isForm(box) {
        var fields = box.children.filter(function (child) {
            return !child.button && child.w >= 0.5 * box.w && child.h <= 0.3 * box.h && child.mark !== 'cross';
        });

        return fields.length >= 2 && bandsOf(fields).length >= 2 && box.children.some(function (child) {
            return child.button;
        });
    }

    // The kind of one box by its own content alone.
    function boxKind(box) {
        if (isForm(box)) {
            return 'form';
        }

        var row = box.children.filter(function (child) { return !child.button; });
        var bands = bandsOf(row);

        if (bands.length === 1 && equalRow(bands[0].boxes)) {
            return rowKind(bands[0].boxes);
        }

        return pictureOnly(box) ? 'picture' : 'text';
    }

    function headingOf(list) {
        for (var i = 0; i < list.length; i++) {
            var text = list[i].text || headingOf(list[i].children.slice().sort(function (a, b) {
                return (a.y - b.y) || (a.x - b.x);
            }));

            if (text) {
                return text;
            }
        }

        return '';
    }

    function section(band, kind, guess) {
        var items = band.boxes.filter(function (box) { return !box.button; });
        var buttons = [];
        var columns = items.length;

        if (columns === 1) {
            var inner = bandsOf(items[0].children.filter(function (child) { return !child.button; }));
            columns = (inner.length === 1 && equalRow(inner[0].boxes)) ? inner[0].boxes.length : 1;
        }

        // Its buttons: small boxes beside its boxes and inside them.
        (function collect(list) {
            list.forEach(function (box) {
                if (box.button) {
                    buttons.push(box);
                }

                collect(box.children);
            });
        })(band.boxes);

        return {
            id: band.boxes[0].id,
            kind: kind,
            widget: WIDGETS[kind] || null,
            boxes: (items.length ? items : band.boxes).map(function (box) { return box.id; }),
            buttons: items.length ? buttons.map(function (box) { return box.id; }) : [],
            columns: columns,
            heading: headingOf(band.boxes),
            picture: band.boxes.some(hasPicture),
            top: band.top,
            height: band.bottom - band.top,
            unsure: !!guess,
            reason: guess ? guess.reason : null,
            options: guess ? unique([kind].concat(guess.options)) : []
        };
    }

    // One band's section; `at` says where the band sits on the page.
    function readBand(band, at) {
        var items = band.boxes.filter(function (box) { return !box.button; });
        var W = at.W, height = band.bottom - band.top;
        var top = band.top <= BAR_EDGE * W;
        var bottom = at.last && band.bottom >= at.H - BAR_EDGE * W;

        var chosen = band.boxes.filter(function (box) {
            return KINDS.indexOf(box.kind) !== -1;
        });

        if (chosen.length) {
            return section(band, chosen[0].kind);
        }

        // Small boxes only: a logo and menu items, links at the bottom, or a
        // button standing alone.
        if (!items.length) {
            if (at.index === 0 && top) {
                return section(band, 'header');
            }

            return section(band, bottom ? 'footer' : 'cta');
        }

        var named = unique([].concat.apply([], items.map(function (box) {
            return wordKinds(box.text);
        })));
        named.sort(function (a, b) {
            return KINDS.indexOf(a) - KINDS.indexOf(b);
        });

        var wide = items.length === 1 && items[0].w >= WIDE * W;
        var geometry;

        if (at.index === 0 && top && height <= HEADER_H * W && wide) {
            geometry = 'header';
        } else if (bottom && height <= FOOTER_H * W && wide) {
            geometry = 'footer';
        } else if (items.length > 1) {
            geometry = equalRow(items) ? rowKind(items) : null;

            if (!geometry && items.length === 2 && items.some(pictureOnly) && items.some(function (box) { return !pictureOnly(box) && hasText(box); })) {
                geometry = at.intro && height >= INTRO_H * W ? 'intro' : 'text';
            }
        } else if (at.intro && height >= INTRO_H * W && !isForm(items[0])) {
            geometry = 'intro';
        } else if (band.boxes.length > 1 && height < CTA_H * W && !hasPicture(items[0]) && boxKind(items[0]) === 'text') {
            geometry = 'cta';
        } else {
            geometry = boxKind(items[0]);
        }

        if (named.length === 1) {
            return section(band, named[0]);
        }

        if (named.length > 1) {
            return section(band, named[0], {reason: 'words', options: named.concat(geometry ? [geometry] : [])});
        }

        if (!geometry) {
            // Unlike boxes side by side: the widest one guesses.
            var widest = items.slice().sort(function (a, b) { return b.w - a.w; })[0];

            return section(band, boxKind(widest), {reason: 'mixed', options: ['features', 'cards', 'text', 'picture', 'gallery']});
        }

        if (geometry !== 'header' && geometry !== 'footer' && items.length === 1 && isEmpty(items[0])) {
            return section(band, geometry === 'intro' ? 'intro' : 'text', {
                reason: 'empty',
                options: (at.intro ? ['intro'] : []).concat(['text', 'picture', 'features', 'cta', 'form'])
            });
        }

        return section(band, geometry);
    }

    // Boxes checked, clipped to frame coordinates, given their children.
    function prepare(frame, boxes, skipped) {
        var fx = num(frame.x) || 0, fy = num(frame.y) || 0;
        var W = num(frame.w), H = num(frame.h);
        var list = [], byId = {};

        (Array.isArray(boxes) ? boxes : []).forEach(function (raw) {
            raw = (raw && typeof raw === 'object') ? raw : {};
            var id = (typeof raw.id === 'string' || typeof raw.id === 'number') ? String(raw.id) : '';
            var x = num(raw.x), y = num(raw.y), w = num(raw.w), h = num(raw.h);

            if (!id || byId[id]) {
                return;
            }

            if (x === null || y === null || w === null || h === null || w < MIN_SIDE * W || h < MIN_SIDE * W) {
                skipped.push(id);
                return;
            }

            var box = {
                id: id,
                x: x - fx,
                y: y - fy,
                w: w,
                h: h,
                text: str(raw.text).replace(/\s+/g, ' ').replace(/^ | $/g, ''),
                mark: (raw.mark === 'cross' || raw.mark === 'lines') ? raw.mark : '',
                kind: str(raw.kind),
                parentId: (raw.parent !== undefined && raw.parent !== null) ? String(raw.parent) : '',
                children: []
            };

            if (inside(box, {x: 0, y: 0, w: W, h: H}) < 0.5) {
                skipped.push(id);
                return;
            }

            box.button = isButton(box, W);
            byId[id] = box;
            list.push(box);
        });

        var tops = [];

        list.forEach(function (box) {
            var parent = (box.parentId && box.parentId !== box.id) ? byId[box.parentId] : null;

            if (!parent) {
                // The smallest bigger box holding nine tenths of it.
                list.forEach(function (other) {
                    if (other !== box && other.w * other.h > box.w * box.h && inside(box, other) >= 0.9
                        && (!parent || other.w * other.h < parent.w * parent.h)) {
                        parent = other;
                    }
                });
            }

            box.parent = parent;
        });

        // A parent chain that loops back is cut where it does.
        list.forEach(function (box) {
            var seen = [box], step = box.parent;

            while (step) {
                if (seen.indexOf(step) !== -1) {
                    box.parent = null;
                    break;
                }

                seen.push(step);
                step = step.parent;
            }
        });

        list.forEach(function (box) {
            if (box.parent) {
                box.parent.children.push(box);
            } else {
                tops.push(box);
            }
        });

        return {W: W, H: H, tops: tops, all: list};
    }

    function read(frame, boxes) {
        var skipped = [], sections = [];

        frame = (frame && typeof frame === 'object') ? frame : {};

        if (!(num(frame.w) > 0)) {
            return {sections: sections, skipped: skipped};
        }

        var page = prepare(frame, boxes, skipped);
        var H = num(frame.h) > 0 ? frame.h : page.all.reduce(function (most, box) {
            return Math.max(most, box.y + box.h);
        }, 0);
        var bands = bandsOf(page.tops);
        var previous = null;

        bands.forEach(function (band, index) {
            var after = previous ? previous.bottom : 0;
            var intro = (index === 0 || (index === 1 && sections[0].kind === 'header'))
                && band.top - after <= INTRO_GAP * page.W;

            sections.push(readBand(band, {
                W: page.W,
                H: H,
                index: index,
                last: index === bands.length - 1,
                intro: intro
            }));
            previous = band;
        });

        return {sections: sections, skipped: skipped};
    }

    window.PGSketchReader = {
        KINDS: KINDS.slice(),
        WIDGETS: WIDGETS,
        fold: fold,
        wordKinds: wordKinds,
        read: read
    };
})();
