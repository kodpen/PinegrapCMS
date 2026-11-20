/**
 * Pinegrap - Enterprise Website Platform
 *
 * The "Map of places" block (data-pg-map) on a published page: asks
 * places.php for the places the page's form list shows, draws them as
 * cards in the block's list, and narrows the list as the visitor types a
 * town, a postcode or a name. The block's still picture and sample cards
 * are the canvas's; they leave once the real list is drawn.
 *
 * While "Maps" is on (Site Settings, Features) the picture's place becomes a
 * map: Leaflet (assets/lib/leaflet/) with the site's raster tiles, a marker
 * per place with coordinates, and the place's card in the marker's popup.
 * With the cookie notice on, neither the library nor a tile is asked for
 * before the visitor allows marketing: the picture stays with a "Show the
 * map" button that opens the notice, and the map is drawn on the notice's
 * pg:consent event, without a reload. With the notice off it is drawn at
 * once. Off, the picture leaves and the list takes the row.
 *
 * The page gives window.pgMap = {page, endpoint, labels, map}
 * (pg_place_list_inject(), pg_place_tiles_page()). Without it (the editor's
 * Preview) the block keeps its sample.
 *
 * Distance and order by distance are worked out here, in the browser; no
 * position is sent anywhere. Node loads this file for its tests: the pure
 * functions are exported there.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */
(function (root) {
    'use strict';

    // The distance between two points in kilometres (haversine).
    function distance(lat1, lng1, lat2, lng2) {
        var rad = Math.PI / 180;
        var dLat = (lat2 - lat1) * rad;
        var dLng = (lng2 - lng1) * rad;
        var a = Math.sin(dLat / 2) * Math.sin(dLat / 2)
            + Math.cos(lat1 * rad) * Math.cos(lat2 * rad) * Math.sin(dLng / 2) * Math.sin(dLng / 2);

        return 6371 * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
    }

    // The places nearest first, each with its distance; places without
    // coordinates keep their list order after the others.
    function order(places, lat, lng) {
        var withPoint = [];
        var without = [];

        places.forEach(function (place, index) {
            if (typeof place.lat === 'number' && typeof place.lng === 'number') {
                withPoint.push({ place: place, index: index, km: distance(lat, lng, place.lat, place.lng) });
            } else {
                without.push({ place: place, index: index, km: null });
            }
        });

        withPoint.sort(function (a, b) {
            return (a.km - b.km) || (a.index - b.index);
        });

        return withPoint.concat(without);
    }

    // Text as it is compared: lower case, no accents, Turkish dotted and
    // dotless i alike.
    function fold(text) {
        var s = String(text || '').replace(/İ/g, 'i').replace(/I/g, 'i').replace(/ı/g, 'i').toLowerCase();

        if (s.normalize) {
            s = s.normalize('NFD').replace(/[̀-ͯ]/g, '');
        }

        return s.replace(/\s+/g, ' ').trim();
    }

    // Whether a place holds every word of what was typed.
    function matches(place, query) {
        var words = fold(query).split(' ').filter(Boolean);
        var text = fold([place.name, place.address, place.phone].join(' '));

        return words.every(function (word) {
            return text.indexOf(word) !== -1;
        });
    }

    // Whether the visitor's stored answer allows marketing: the pg_consent
    // cookie as pg_consent.js writes it ("1" then ".m1" among the answers).
    function marketingAllowed(cookie) {
        var match = String(cookie || '').match(/(?:^|;\s*)pg_consent=([^;]*)/);

        if (!match) {
            return false;
        }

        var parts = match[1].split('.');

        return parts.shift() === '1' && parts.indexOf('m1') !== -1;
    }

    // What the block does about the map: 'off' (the list alone), 'wait'
    // (the picture and its button until marketing is allowed) or 'load'.
    function gate(map, cookie) {
        if (!map || !map.on || !map.tiles) {
            return 'off';
        }

        return (!map.consent || marketingAllowed(cookie)) ? 'load' : 'wait';
    }

    // Calls mount once the map may load: at once, or on the cookie notice's
    // answer that allows marketing. Nothing else loads the map, so no tile
    // is asked for before. Returns the gate.
    function whenAllowed(doc, map, cookie, mount) {
        var state = gate(map, cookie);

        if (state === 'load') {
            mount();
        } else if (state === 'wait') {
            var listen = function (event) {
                if (event && event.detail && event.detail.marketing) {
                    doc.removeEventListener('pg:consent', listen);
                    mount();
                }
            };

            doc.addEventListener('pg:consent', listen);
        }

        return state;
    }

    var api = { distance: distance, order: order, fold: fold, matches: matches, marketingAllowed: marketingAllowed, gate: gate, whenAllowed: whenAllowed };

    if (typeof module !== 'undefined' && module.exports) {
        module.exports = api;
        return;
    }

    root.pgMapList = api;

    var config = root.pgMap;

    if (!config || !config.page || !root.document) {
        return;
    }

    var labels = config.labels || {};

    function t(key) {
        return labels[key] || key;
    }

    function el(tag, cls, text) {
        var node = root.document.createElement(tag);

        if (cls) {
            node.className = cls;
        }

        if (text !== undefined) {
            node.textContent = text;
        }

        return node;
    }

    function directions(place) {
        if (typeof place.lat === 'number') {
            return 'https://www.openstreetmap.org/directions?to=' + encodeURIComponent(place.lat + ',' + place.lng);
        }

        return 'https://www.openstreetmap.org/search?query=' + encodeURIComponent(place.address);
    }

    function card(place, onMap) {
        var item = el('li', 'pg-map-card');
        item.appendChild(el('h3', 'h6 mb-1', place.name || place.address));

        if (place.name && place.address) {
            item.appendChild(el('p', 'mb-1', place.address));
        }

        if (place.hours) {
            item.appendChild(el('p', 'mb-1 text-body-secondary small', place.hours));
        }

        if (place.phone) {
            var phone = el('a', 'd-inline-block mb-2', place.phone);
            phone.href = 'tel:' + place.phone.replace(/[^0-9+]/g, '');
            item.appendChild(phone);
        }

        var links = el('div', 'd-flex flex-wrap gap-2');
        var go = el('a', 'btn btn-sm btn-outline-primary', t('Directions'));
        go.href = directions(place);
        go.target = '_blank';
        go.rel = 'noopener';
        links.appendChild(go);

        if (place.url) {
            var more = el('a', 'btn btn-sm btn-link', t('Details'));
            more.href = place.url;
            links.appendChild(more);
        }

        if (onMap) {
            var locate = el('button', 'btn btn-sm btn-link', t('Show on the map'));
            locate.type = 'button';
            locate.addEventListener('click', onMap);
            links.appendChild(locate);
        }

        item.appendChild(links);

        return item;
    }

    // ── The map ─────────────────────────────────────────────────────────

    var library = null;

    // Leaflet's stylesheet and script, once for the page; calls back when
    // the script has run.
    function loadLibrary(map, done) {
        if (root.L && root.L.map) {
            done();
            return;
        }

        if (!library) {
            library = [];

            var style = root.document.createElement('link');
            style.rel = 'stylesheet';
            style.href = map.style;
            root.document.head.appendChild(style);

            var script = root.document.createElement('script');
            script.src = map.script;
            script.onload = function () {
                library.forEach(function (callback) {
                    callback();
                });
                library = [];
            };
            root.document.head.appendChild(script);
        }

        library.push(done);
    }

    // The map in the picture's place, a marker per place with coordinates.
    function mountMap(view, map) {
        loadLibrary(map, function () {
            var L = root.L;
            var box = el('div', 'pg-map-canvas rounded border');
            box.style.height = '28rem';
            box.style.maxHeight = '70vh';
            box.setAttribute('role', 'region');
            box.setAttribute('aria-label', t('Map of places'));

            view.picture.textContent = '';
            view.picture.appendChild(box);

            var leaflet = L.map(box, { scrollWheelZoom: false });
            leaflet.attributionControl.setPrefix('<a href="https://leafletjs.com" target="_blank" rel="noopener">Leaflet</a>');
            L.tileLayer(map.tiles, { attribution: map.credit, maxZoom: 19 }).addTo(leaflet);

            var points = [];

            view.places.forEach(function (place, index) {
                if (typeof place.lat !== 'number' || typeof place.lng !== 'number') {
                    return;
                }

                var marker = L.marker([place.lat, place.lng], { title: place.name || place.address, alt: place.name || place.address });
                // The place's card in the popup, as a block rather than a
                // list item.
                marker.bindPopup(function () {
                    var item = card(place);
                    // Leaflet draws its popup white in either colour mode:
                    // the card's text takes the light mode's colours there.
                    var box = el('div', 'pg-map-popup');
                    box.setAttribute('data-bs-theme', 'light');

                    while (item.firstChild) {
                        box.appendChild(item.firstChild);
                    }

                    return box;
                });
                marker.on('popupopen', function () {
                    view.mark(index);
                });
                view.markers[index] = marker;
                points.push([place.lat, place.lng]);
            });

            if (points.length) {
                leaflet.fitBounds(points, { padding: [32, 32], maxZoom: 15 });
            } else {
                leaflet.setView([20, 0], 2);
            }

            view.leaflet = leaflet;
            view.show();
        });
    }

    function draw(block, places, note, cut) {
        var list = block.querySelector('[data-pg-map-list]');
        var search = block.querySelector('[data-pg-map-search]');
        var status = block.querySelector('[data-pg-map-status]');

        if (!list) {
            return;
        }

        // A cut list offers no order by distance (the nearest place could be
        // among those left out): a later step reads this mark.
        if (cut) {
            block.setAttribute('data-pg-map-cut', '1');
        }

        var map = config.map || {};
        var picture = block.querySelector('[data-pg-map-picture]');
        var view = { places: places, picture: picture, markers: [], leaflet: null, cards: [] };

        // The sample cards leave. The picture keeps its place while a map
        // may come; otherwise it leaves with its column and the list's
        // column takes the row.
        Array.prototype.forEach.call(block.querySelectorAll('[data-pg-map-sample]'), function (node) {
            node.parentNode.removeChild(node);
        });

        var state = picture ? gate(map, root.document.cookie) : 'off';

        if (state === 'off') {
            Array.prototype.forEach.call(block.querySelectorAll('[data-pg-map-picture]'), function (node) {
                var gone = node;

                if (node.parentNode && /(^|\s)col(-|\s|$)/.test(node.parentNode.className || '')) {
                    gone = node.parentNode;
                }

                gone.parentNode.removeChild(gone);
            });

            if (/(^|\s)col(-|\s|$)/.test(list.parentNode.className || '')) {
                list.parentNode.className = list.parentNode.className.replace(/\bcol-(sm|md|lg|xl|xxl)-\d+\b/g, '').replace(/\s+/g, ' ').trim();
            }
        }

        // Until marketing is allowed: the still picture, a button that
        // opens the cookie notice and a line saying why. The button presses
        // the notice's own corner button: as a data-pg-cc-open opener it
        // would hide that corner button for good once the map replaced it.
        var notice = root.document.querySelector('#pg-cc .pg-cc-fab');

        if (state === 'wait' && notice) {
            picture.style.position = 'relative';

            var ask = el('div', 'position-absolute top-50 start-50 translate-middle d-flex flex-column align-items-center gap-2 text-center p-3 rounded bg-body shadow-sm');
            ask.style.width = '20rem';
            ask.style.maxWidth = '90%';
            var open = el('button', 'btn btn-primary w-100 text-wrap', t('Show the map'));
            open.type = 'button';
            open.addEventListener('click', function () {
                notice.click();
            });
            ask.appendChild(open);
            ask.appendChild(el('p', 'small text-body-secondary mb-0', t('The map shows once you allow marketing in the cookie settings.')));
            picture.appendChild(ask);
        }

        view.mark = function (index) {
            view.cards.forEach(function (item, i) {
                if (!item) {
                    return;
                }

                item.classList.toggle('border-primary', i === index);

                if (i === index && item.scrollIntoView) {
                    item.scrollIntoView({ block: 'nearest' });
                }
            });
        };

        if (note) {
            var aside = el('p', 'alert alert-warning small', note);
            list.parentNode.insertBefore(aside, list);
        }

        function show() {
            var query = search ? search.value : '';
            var shown = 0;
            list.textContent = '';
            view.cards = [];

            places.forEach(function (place, index) {
                var marker = view.markers[index];
                var match = !query || matches(place, query);

                // The markers follow the search, as the list does.
                if (marker && view.leaflet) {
                    if (match && !view.leaflet.hasLayer(marker)) {
                        marker.addTo(view.leaflet);
                    } else if (!match && view.leaflet.hasLayer(marker)) {
                        view.leaflet.removeLayer(marker);
                    }
                }

                if (match) {
                    var item = card(place, (marker && view.leaflet) ? function () {
                        view.leaflet.setView(marker.getLatLng(), Math.max(view.leaflet.getZoom(), 15));
                        marker.openPopup();
                    } : null);
                    view.cards[index] = item;
                    list.appendChild(item);
                    shown++;
                }
            });

            if (status) {
                status.textContent = shown ? '' : (query ? t('No place matches your search.') : t('No places to show yet.'));
            }
        }

        view.show = show;

        if (search) {
            search.addEventListener('input', show);
        }

        show();

        if (state !== 'off') {
            whenAllowed(root.document, map, root.document.cookie, function () {
                mountMap(view, map);
            });
        }
    }

    function load() {
        var blocks = root.document.querySelectorAll('[data-pg-map]');

        if (!blocks.length || !root.fetch) {
            return;
        }

        root.fetch(config.endpoint + '?page=' + encodeURIComponent(config.page), { credentials: 'same-origin' })
            .then(function (response) {
                return response.json();
            })
            .then(function (answer) {
                Array.prototype.forEach.call(blocks, function (block) {
                    draw(block, (answer && answer.ok && answer.places) || [], (answer && answer.note) || '', !!(answer && answer.cut));
                });
            })
            .catch(function () {
                Array.prototype.forEach.call(blocks, function (block) {
                    var status = block.querySelector('[data-pg-map-status]');

                    if (status) {
                        status.textContent = t('The places could not be loaded.');
                    }
                });
            });
    }

    if (root.document.readyState === 'loading') {
        root.document.addEventListener('DOMContentLoaded', load);
    } else {
        load();
    }
}(typeof window !== 'undefined' ? window : this));
