/**
 * Pinegrap - Enterprise Website Platform
 *
 * The bundle card of the product screen (pg_pb_render_bundle_card() in
 * product_builder.php): a product search that adds a row per product picked,
 * and the remove buttons. The rows are plain form fields; the server checks
 * and saves them (includes/fn/bundles.php). The search is the counter sale's
 * (add_order.php?request=search_products), which answers for the same
 * commerce right as this screen.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */
(function () {
    'use strict';

    var search = document.getElementById('pg_pb_bundle_search');
    var results = document.getElementById('pg_pb_bundle_results');
    var rows = document.getElementById('pg_pb_bundle_rows');
    var template = document.getElementById('pg_pb_bundle_row_template');

    if (!search || !results || !rows || !template) {
        return;
    }

    var self = parseInt(search.getAttribute('data-self'), 10) || 0;
    var timer = null;
    var asked = 0;
    var announceTimer = null;

    // The list and the polite status line are described here, so the card's
    // markup stays as product_builder.php prints it.
    var status = document.createElement('div');

    status.className = 'visually-hidden';
    status.setAttribute('role', 'status');
    status.setAttribute('aria-live', 'polite');
    status.setAttribute('aria-atomic', 'true');
    results.parentNode.insertBefore(status, results.nextSibling);

    search.setAttribute('role', 'combobox');
    search.setAttribute('aria-autocomplete', 'list');
    search.setAttribute('aria-controls', results.id);
    search.setAttribute('aria-expanded', 'false');

    // Emptied first and filled a moment later, so the same sentence twice in
    // a row is read again.
    function announce(text) {
        clearTimeout(announceTimer);
        status.textContent = '';
        announceTimer = setTimeout(function () {
            status.textContent = text;
        }, 150);
    }

    // The translated words come from the page's fields script tag
    // (pg_fields_script()), printed further down the screen, so they are
    // read when needed rather than on load.
    function noResultsText() {
        var tag = document.querySelector('script[data-pg-no-results-label]');

        return (tag && tag.getAttribute('data-pg-no-results-label')) || 'No results found';
    }

    function quantityWord() {
        var head = rows.closest('table').querySelector('thead th:nth-child(2)');

        return head ? head.textContent.trim() : '';
    }

    function resultItems() {
        return Array.prototype.slice.call(results.querySelectorAll('button'));
    }

    function productLabel(product) {
        var name = (product.short_description || '').trim();

        return name !== '' ? name + ' (' + product.name + ')' : product.name;
    }

    // The row of a product already in the bundle, so picking it again adds
    // one to its quantity instead of a second row.
    function rowOf(productId) {
        var inputs = rows.querySelectorAll('input[name="bundle_item_product_id[]"]');

        for (var i = 0; i < inputs.length; i++) {
            if (parseInt(inputs[i].value, 10) === productId) {
                return inputs[i].closest('tr');
            }
        }

        return null;
    }

    function add(product) {
        var existing = rowOf(product.id);

        if (existing) {
            var quantity = existing.querySelector('input[name="bundle_item_quantity[]"]');
            quantity.value = (parseInt(quantity.value, 10) || 0) + 1;
            announce(productLabel(product) + ', ' + quantityWord() + ': ' + quantity.value);
            return;
        }

        var row = template.content.firstElementChild.cloneNode(true);

        row.querySelector('input[name="bundle_item_product_id[]"]').value = product.id;
        row.firstElementChild.insertBefore(document.createTextNode(productLabel(product)), row.firstElementChild.firstChild);
        rows.appendChild(row);
        announce(productLabel(product) + ', ' + quantityWord() + ': ' + row.querySelector('input[name="bundle_item_quantity[]"]').value);
    }

    function clearResults() {
        results.textContent = '';
        search.setAttribute('aria-expanded', 'false');
    }

    // Shown and read out alike, for an empty answer and a failed request.
    function showNothing() {
        clearResults();

        var empty = document.createElement('div');

        empty.className = 'list-group-item fg-secondary small';
        empty.textContent = noResultsText();
        results.appendChild(empty);
        announce(empty.textContent);
    }

    function show(list) {
        clearResults();

        list.forEach(function (product) {
            if (product.id === self) {
                return;
            }

            var item = document.createElement('button');

            item.type = 'button';
            item.className = 'list-group-item list-group-item-action';
            item.textContent = productLabel(product);
            item.addEventListener('click', function () {
                add(product);
                clearResults();
                search.value = '';
                search.focus();
            });

            results.appendChild(item);
        });

        // Nothing came back, or only the product itself.
        if (!results.querySelector('button')) {
            showNothing();
            return;
        }

        search.setAttribute('aria-expanded', 'true');
    }

    function lookup() {
        var q = search.value.trim();
        var ask = ++asked;

        if (q === '') {
            clearResults();
            return;
        }

        fetch(search.getAttribute('data-url') + encodeURIComponent(q), {
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
            .then(function (response) {
                if (!response.ok) {
                    throw new Error(String(response.status));
                }

                return response.json();
            })
            .then(function (data) {
                // An answer to an older query that came in late is dropped.
                if (ask === asked) {
                    show((data && data.results) || []);
                }
            })
            .catch(function () {
                if (ask === asked) {
                    showNothing();
                }
            });
    }

    search.addEventListener('input', function () {
        clearTimeout(timer);
        timer = setTimeout(lookup, 250);
    });

    // Enter in the search box would submit the product form. ArrowDown
    // moves into the results, Escape closes them.
    search.addEventListener('keydown', function (event) {
        if (event.key === 'Enter') {
            event.preventDefault();
        } else if (event.key === 'ArrowDown') {
            var first = resultItems()[0];

            if (first) {
                event.preventDefault();
                first.focus();
            }
        } else if (event.key === 'Escape' && results.firstChild) {
            event.preventDefault();
            clearResults();
        }
    });

    // The arrows move through the results; up from the first one and Escape
    // go back to the search box.
    results.addEventListener('keydown', function (event) {
        var items = resultItems();
        var at = items.indexOf(document.activeElement);

        if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
            event.preventDefault();

            if (event.key === 'ArrowUp' && at <= 0) {
                search.focus();
            } else if (items.length) {
                items[Math.min(items.length - 1, Math.max(0, at + (event.key === 'ArrowDown' ? 1 : -1)))].focus();
            }
        } else if (event.key === 'Escape') {
            event.preventDefault();
            clearResults();
            search.focus();
        }
    });

    rows.addEventListener('click', function (event) {
        var button = event.target.closest('.pg-pb-bundle-remove');

        if (button) {
            // Focus goes to the next row's quantity, else the search box, so
            // it does not fall to the page top with the row.
            var row = button.closest('tr');
            var next = row.nextElementSibling ? row.nextElementSibling.querySelector('input[name="bundle_item_quantity[]"]') : null;
            row.remove();
            (next || search).focus();
        }
    });
}());
