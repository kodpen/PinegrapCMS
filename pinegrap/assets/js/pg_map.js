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
 * "Near me" (X11) asks the browser's own location question; the position
 * stays in the browser, where the distances and the order are worked out.
 * A typed address (X12) is looked up through places.php (POST, once per
 * press of Find, Photon only); only the point it gives is used, and nothing
 * typed is stored. Both are offered only when the answer holds the whole
 * list: in a cut list the nearest place could be among those left out.
 * Node loads this file for its tests: the pure functions are exported there.
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

    // A distance as the card prints it: one decimal under 10 km, whole
    // kilometres above.
    function formatKm(km, locale) {
        var value = km < 10 ? Math.round(km * 10) / 10 : Math.round(km);

        try {
            return value.toLocaleString(locale || undefined, { maximumFractionDigits: 1 });
        } catch (e) {
            return String(value);
        }
    }

    // Whether "Near me" and the address box are offered: the whole list
    // (not cut) with at least one place that has coordinates.
    function nearOffered(places, cut) {
        return !cut && places.some(function (place) {
            return typeof place.lat === 'number' && typeof place.lng === 'number';
        });
    }

    // The rows the list shows: the places matching what was typed, in the
    // list's order, or nearest first with their distance when a point to
    // measure from is given ({lat, lng}).
    function arrange(places, query, from) {
        var rows = from ? order(places, from.lat, from.lng) : places.map(function (place, index) {
            return { place: place, index: index, km: null };
        });

        return rows.filter(function (row) {
            return !query || matches(row.place, query);
        });
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

    var api = { distance: distance, order: order, formatKm: formatKm, nearOffered: nearOffered, arrange: arrange, fold: fold, matches: matches, marketingAllowed: marketingAllowed, gate: gate, whenAllowed: whenAllowed };

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

    function card(place, onMap, km) {
        var item = el('li', 'pg-map-card');
        item.appendChild(el('h3', 'h6 mb-1', place.name || place.address));

        if (typeof km === 'number') {
            item.appendChild(el('p', 'mb-1 small fw-semibold', t('{km} km').replace('{km}', formatKm(km, root.document.documentElement.lang))));
        }

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

    // "Near me" and, while the lookup is on, the address box, under the
    // search. Each sets the point the list is ordered from and draws again.
    function nearTools(block, search, list, view) {
        var bar = el('div', 'd-flex flex-wrap align-items-center gap-2 mb-3');
        var said = el('p', 'small mb-0 w-100');
        said.setAttribute('role', 'status');
        said.hidden = true;

        function tell(text) {
            said.textContent = text;
            said.hidden = !text;
        }

        function from(lat, lng, line) {
            tell('');
            view.from = { lat: lat, lng: lng, line: line };
            view.show();

            if (view.leaflet) {
                view.leaflet.setView([lat, lng], Math.max(view.leaflet.getZoom(), 11));
            }
        }

        if (root.navigator && root.navigator.geolocation) {
            var near = el('button', 'btn btn-sm btn-outline-primary', t('Near me'));
            near.type = 'button';
            near.addEventListener('click', function () {
                near.disabled = true;
                // The browser asks the visitor; the position stays here.
                root.navigator.geolocation.getCurrentPosition(function (position) {
                    near.disabled = false;
                    from(position.coords.latitude, position.coords.longitude, t('Nearest first from your position.'));
                }, function () {
                    near.disabled = false;
                    tell(t('Your browser did not give a position.'));
                }, { timeout: 15000, maximumAge: 300000 });
            });
            bar.appendChild(near);
        }

        if (config.find && root.URLSearchParams) {
            var form = el('form', 'd-flex flex-wrap gap-2 flex-grow-1');
            form.setAttribute('role', 'search');
            form.setAttribute('aria-label', t('Find near an address'));
            var box = el('input', 'form-control form-control-sm flex-grow-1 w-auto');
            box.type = 'search';
            box.name = 'pg-map-find';
            box.autocomplete = 'off';
            box.maxLength = 200;
            box.placeholder = t('Address or postcode');
            box.setAttribute('aria-label', t('Address or postcode'));
            var find = el('button', 'btn btn-sm btn-outline-primary', t('Find'));
            find.type = 'submit';
            form.appendChild(box);
            form.appendChild(find);

            // One lookup per press; the text goes in the body and is not
            // kept on the page after the answer.
            form.addEventListener('submit', function (event) {
                event.preventDefault();

                var text = box.value.replace(/\s+/g, ' ').trim();

                if (!text || find.disabled) {
                    return;
                }

                find.disabled = true;

                root.fetch(config.endpoint, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new root.URLSearchParams({ find: text }).toString()
                }).then(function (response) {
                    return response.json().then(function (answer) {
                        return { status: response.status, answer: answer };
                    });
                }).then(function (result) {
                    find.disabled = false;
                    var answer = result.answer || {};

                    if (answer.ok && answer.state === 'found' && typeof answer.lat === 'number' && typeof answer.lng === 'number') {
                        from(answer.lat, answer.lng, t('Nearest first from: {address}').replace('{address}', text));
                    } else if (answer.ok && answer.state === 'not_found') {
                        tell(t('The address was not found.'));
                    } else if (result.status === 429 || answer.state === 'wait') {
                        tell(t('The address service asks to wait. Try again in a minute.'));
                    } else {
                        tell(t('The address could not be looked up.'));
                    }
                }).catch(function () {
                    find.disabled = false;
                    tell(t('The address could not be looked up.'));
                });
            });
            bar.appendChild(form);
        }

        if (!bar.firstChild) {
            return;
        }

        bar.appendChild(said);

        var after = search;

        if (after && after.parentNode && /(^|\s)(input-group|form-floating)(\s|$)/.test(after.parentNode.className || '')) {
            after = after.parentNode;
        }

        if (after && after.parentNode) {
            after.parentNode.insertBefore(bar, after.nextSibling);
        } else {
            list.parentNode.insertBefore(bar, list);
        }

        block.setAttribute('data-pg-map-near', '1');
    }

    function draw(block, places, note, cut) {
        var list = block.querySelector('[data-pg-map-list]');
        var search = block.querySelector('[data-pg-map-search]');
        var status = block.querySelector('[data-pg-map-status]');

        if (!list) {
            return;
        }

        // A cut list offers no order by distance (the nearest place could be
        // among those left out).
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

        // The point the list is ordered from: {lat, lng, line}, or null.
        view.from = null;

        function show() {
            var query = search ? search.value : '';
            var rows = arrange(places, query, view.from);
            var shown = {};
            list.textContent = '';
            view.cards = [];

            rows.forEach(function (row) {
                var marker = view.markers[row.index];
                var item = card(row.place, (marker && view.leaflet) ? function () {
                    view.leaflet.setView(marker.getLatLng(), Math.max(view.leaflet.getZoom(), 15));
                    marker.openPopup();
                } : null, row.km);
                view.cards[row.index] = item;
                list.appendChild(item);
                shown[row.index] = true;
            });

            // The markers follow the search, as the list does.
            if (view.leaflet) {
                view.markers.forEach(function (marker, index) {
                    if (!marker) {
                        return;
                    }

                    if (shown[index] && !view.leaflet.hasLayer(marker)) {
                        marker.addTo(view.leaflet);
                    } else if (!shown[index] && view.leaflet.hasLayer(marker)) {
                        view.leaflet.removeLayer(marker);
                    }
                });
            }

            if (status) {
                status.textContent = rows.length ? (view.from ? view.from.line : '') : (query ? t('No place matches your search.') : t('No places to show yet.'));
            }
        }

        view.show = show;

        if (nearOffered(places, cut)) {
            nearTools(block, search, list, view);
        }

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
