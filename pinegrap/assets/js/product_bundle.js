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
            return;
        }

        var row = template.content.firstElementChild.cloneNode(true);

        row.querySelector('input[name="bundle_item_product_id[]"]').value = product.id;
        row.firstElementChild.insertBefore(document.createTextNode(productLabel(product)), row.firstElementChild.firstChild);
        rows.appendChild(row);
    }

    function clearResults() {
        results.textContent = '';
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
            .then(function (response) { return response.json(); })
            .then(function (data) {
                // An answer to an older query that came in late is dropped.
                if (ask === asked) {
                    show((data && data.results) || []);
                }
            })
            .catch(clearResults);
    }

    search.addEventListener('input', function () {
        clearTimeout(timer);
        timer = setTimeout(lookup, 250);
    });

    // Enter in the search box would submit the product form.
    search.addEventListener('keydown', function (event) {
        if (event.key === 'Enter') {
            event.preventDefault();
        }
    });

    rows.addEventListener('click', function (event) {
        var button = event.target.closest('.pg-pb-bundle-remove');

        if (button) {
            button.closest('tr').remove();
        }
    });
}());
