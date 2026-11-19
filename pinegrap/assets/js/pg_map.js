/**
 * Pinegrap - Enterprise Website Platform
 *
 * The "Map of places" block (data-pg-map) on a published page: asks
 * places.php for the places the page's form list shows, draws them as
 * cards in the block's list, and narrows the list as the visitor types a
 * town, a postcode or a name. The block's still picture and sample cards
 * are the canvas's; they leave once the real list is drawn. The map itself
 * comes in a later step: this file draws no tiles and loads nothing else.
 *
 * The page gives window.pgMap = {page, endpoint, labels}
 * (pg_place_list_inject()). Without it (the editor's Preview) the block
 * keeps its sample.
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

    var api = { distance: distance, order: order, fold: fold, matches: matches };

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

    function card(place) {
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

        item.appendChild(links);

        return item;
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

        // The canvas's picture leaves with its column (no map yet), and the
        // list's column takes the row; the sample cards leave too.
        Array.prototype.forEach.call(block.querySelectorAll('[data-pg-map-picture], [data-pg-map-sample]'), function (node) {
            var gone = node;

            if (node.hasAttribute('data-pg-map-picture') && node.parentNode && /(^|\s)col(-|\s|$)/.test(node.parentNode.className || '')) {
                gone = node.parentNode;
            }

            gone.parentNode.removeChild(gone);
        });

        if (/(^|\s)col(-|\s|$)/.test(list.parentNode.className || '')) {
            list.parentNode.className = list.parentNode.className.replace(/\bcol-(sm|md|lg|xl|xxl)-\d+\b/g, '').replace(/\s+/g, ' ').trim();
        }

        if (note) {
            var aside = el('p', 'alert alert-warning small', note);
            list.parentNode.insertBefore(aside, list);
        }

        function show() {
            var query = search ? search.value : '';
            var shown = 0;
            list.textContent = '';

            places.forEach(function (place) {
                if (!query || matches(place, query)) {
                    list.appendChild(card(place));
                    shown++;
                }
            });

            if (status) {
                status.textContent = shown ? '' : (query ? t('No place matches your search.') : t('No places to show yet.'));
            }
        }

        if (search) {
            search.addEventListener('input', show);
        }

        show();
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
