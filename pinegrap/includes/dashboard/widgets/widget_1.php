<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Dashboard widget 1 - Sales Map: completed orders by billing country and region.
 *
 * Loaded and called by includes/dashboard/widgets.php; see that file for the
 * contract every widget follows.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_DASHBOARD_WIDGETS')) {
    exit;
}

function pg_dashboard_widget_1($request, $user)
{
    // ── Sales map ───────────────────────────────────────────
    //
    // Where the money came from. The dashboard could say how much
    // was sold and what was sold, never where from: that question
    // needed a report to be built in view_order_report.php and run,
    // which is not something anyone does while glancing at a panel.
    //
    // Two views over one set of numbers. The world map answers
    // "which countries", a country's own map answers "which parts
    // of it", and the list beside either one always names regions:
    // the map is the scale, the list is the detail. The card opens
    // on whichever view its own data calls for -- see
    // pg_sales_map_collect().
    //
    // The figures come from the same columns and the same status
    // filter as the Sales Report's "Billing State" summary, so the
    // card and the report cannot disagree. Everything about the
    // data is in includes/sales_map.php; what is left here is the
    // markup, as it is for every other widget on this screen.
    //
    // Gated on commerce REPORTS, the permission view_order_report.php
    // asks for, rather than on commerce: this is a sales report, and
    // a user can hold one of those two without the other.
    if ((ECOMMERCE === true) && USER_MANAGE_ECOMMERCE_REPORTS) {

        require_once PG_FUNCTIONS_DIR . '/includes/sales_map.php';

        $sales_map = pg_sales_map_collect();

        if ($sales_map['status'] !== 'ok') {

            return array(
                'status'  => 'success',
                'message' => lang('Data Received successfully.'),
                'data'    => '<div class="card-body p-0">'
                    . pg_widget_empty('bi-map', lang(array(
                        'string' => 'There is no {var:1} right now.',
                        'vars'   => lang('Order'))))
                    . '</div>');
        }

        // Separators given explicitly, as everywhere else in the
        // dashboard widgets: number_format() with only a precision
        // falls back to the English ones and puts "9,986" on the
        // same line as "798.380,70".
        // Every place on this card links to the orders screen, and
        // that screen asks for commerce, not for commerce reports.
        // Somebody who may read the report but not work the orders
        // gets the same card without the links -- rather than a row
        // that lands on "Access denied".
        $sm_can_open = (defined('USER_MANAGE_ECOMMERCE') && USER_MANAGE_ECOMMERCE);

        $sm_url = function ($country_name, $state, $from) use ($sm_can_open, $sales_map) {

            if (!$sm_can_open) {
                return '';
            }

            return pg_sales_map_orders_url($country_name, $state, $from, $sales_map['first']);
        };

        $sm_money = function ($cents) {
            return pg_format_money($cents / 100, BASE_CURRENCY_SYMBOL);
        };

        $sm_count = function ($number) {
            return pg_format_number($number, 0);
        };

        $sm_orders = function ($number) use ($sm_count) {
            return lang(array(
                'string' => '{var:1} order{suffix:1}',
                'vars'   => $sm_count($number),
                'suffix' => ($number == 1) ? '' : 's'));
        };

        // The world is always on offer; a country joins it only
        // where we ship outlines for it. One market and one map
        // means no switch at all.
        $sm_views = array('world' => lang('World'));
        $sm_files = array('world' => 'assets/maps/world-countries.svg');

        if ($sales_map['home_map'] !== '') {
            // Through lang(), because the countries table holds one
            // spelling per installation and it is whatever was typed
            // into it. The value that filters the orders screen is
            // the stored one and stays untranslated.
            $sm_views[$sales_map['home']] = lang($sales_map['home_name']);
            $sm_files[$sales_map['home']] = 'assets/maps/' . $sales_map['home_map'];
        }

        $sm_view = (($sales_map['default'] === 'home') && ($sales_map['home_map'] !== ''))
            ? $sales_map['home']
            : 'world';

        // Versioned by the file's own timestamp, the way the rest of
        // the panel's assets are: the outlines are cached hard, and
        // a regenerated map has to reach a browser that already has
        // the old one.
        $sm_maps = array();

        foreach ($sm_files as $sm_key => $sm_file) {
            $sm_maps[$sm_key] = $sm_file . '?v=' . (int) @filemtime(PG_FUNCTIONS_DIR . '/' . $sm_file);
        }

        $sm_heads = '';
        $sm_lists = '';
        $sm_tabs = '';
        $sm_switch = '';

        $sm_payload = array(
            'view'  => $sm_view,
            'mode'  => $sales_map['period'],
            'maps'  => $sm_maps,
            'rows'  => array(),
            'names' => array(),
            'none'  => '<span class="text-muted">' . h(lang('No sales')) . '</span>');

        foreach ($sm_views as $sm_view_key => $sm_view_label) {

            $sm_is_world = ($sm_view_key === 'world');

            // Names for the places that sold nothing: hovering one
            // still has to say which place it is.
            $sm_names = array();

            if ($sm_is_world) {

                // Every country the outlines have, under the code
                // the outlines use -- not only the rows the shop's
                // countries table happens to hold.
                foreach (pg_sales_map_country_names() as $sm_code => $sm_name) {
                    $sm_names[$sm_code] = h(lang($sm_name));
                }

            } else {

                foreach (call_user_func(pg_sales_map_region_maps()[$sm_view_key]['names']) as $sm_code => $sm_name) {
                    $sm_names[$sm_code] = h($sm_name);
                }
            }

            $sm_payload['names'][$sm_view_key] = $sm_names;
            $sm_payload['rows'][$sm_view_key] = array();

            $sm_switch .=
                '<li><button type="button" class="dropdown-item'
                . (($sm_view_key === $sm_view) ? ' active' : '') . '" data-smap-view="' . h($sm_view_key) . '">'
                . '<i class="bi bi-check2 pg-smap-tick"></i>' . h($sm_view_label) . '</button></li>';

            foreach ($sales_map['periods'] as $sm_period_key => $sm_period) {

                $sm_slot = $sm_view_key . '|' . $sm_period_key;
                $sm_hidden = (($sm_view_key === $sm_view) && ($sm_period_key === $sales_map['period']))
                    ? ''
                    : ' d-none';

                // A view answers for what it draws: the world for
                // every order, a country for its own. Showing the
                // shop's whole revenue over a map of one country
                // would invite the reader to add the map up and get
                // a different number.
                $sm_regions = array();

                foreach ($sm_period['regions'] as $sm_region_key => $sm_region) {

                    if (($sm_is_world) || ($sm_region['country'] === $sm_view_key)) {
                        $sm_regions[$sm_region_key] = $sm_region;
                    }
                }

                if ($sm_is_world) {
                    $sm_total = $sm_period['total'];
                    $sm_orders_count = $sm_period['count'];
                } else {
                    $sm_total = $sm_period['countries'][$sm_view_key]['total'] ?? 0;
                    $sm_orders_count = $sm_period['countries'][$sm_view_key]['count'] ?? 0;
                }

                // ── Head ─────────────────────────────────────────
                $sm_places = $sm_is_world ? count($sm_period['countries']) : count($sm_regions);

                $sm_heads .=
                    '<div class="pg-head' . $sm_hidden . '" data-smap-head="' . $sm_slot . '">'
                    . '<div class="pg-head-line">'
                    . '<span class="pg-head-num">' . $sm_money($sm_total) . '</span>'
                    . '<span class="pg-head-unit text-muted">' . h($sm_period['label']) . '</span>'
                    . '<span class="pg-head-total text-muted">'
                    . $sm_orders($sm_orders_count)
                    . ' &middot; '
                    . lang(array(
                        'string' => $sm_is_world ? '{var:1} countr{suffix:1}' : '{var:1} region{suffix:1}',
                        'vars'   => $sm_count($sm_places),
                        'suffix' => $sm_is_world
                            ? (($sm_places == 1) ? 'y' : 'ies')
                            : (($sm_places == 1) ? '' : 's')))
                    . '</span>'
                    . '</div></div>';

                // ── Regions, ranked ──────────────────────────────
                //
                // Built here rather than in the browser so that a
                // row on this card is the same row as on every
                // other one: pg_widget_row() stays the single
                // description of what a dashboard list line looks
                // like.
                $sm_rows = '';
                $sm_rank = 0;

                foreach ($sm_regions as $sm_region) {

                    $sm_rank++;

                    if ($sm_rank > 8) {
                        break;
                    }

                    $sm_share = ($sm_total > 0)
                        ? round(($sm_region['total'] / $sm_total) * 100)
                        : 0;

                    $sm_meta = $sm_orders($sm_region['count'])
                        . ' &middot; ' . lang(array('string' => '{var:1}% share', 'vars' => $sm_share));

                    // On the world view the region's country is
                    // half of its identity: there is a Sivas in
                    // Turkey and a Georgia in two places.
                    if (($sm_is_world) && ($sm_region['country_name'] !== '')) {
                        $sm_meta = h(lang($sm_region['country_name'])) . ' &middot; ' . $sm_meta;
                    }

                    $sm_rows .= pg_widget_row(array(
                        'href'  => h($sm_url(
                            $sm_region['country_name'], $sm_region['state'], $sm_period['from'])),
                        'badge' => $sm_rank,
                        'name'  => h($sm_region['name']),
                        'aside' => $sm_money($sm_region['total']),
                        'meta'  => $sm_meta));
                }

                if ($sm_rows === '') {
                    $sm_rows = pg_widget_empty('bi-map', lang('There are no sales in this period.'));
                }

                $sm_lists .=
                    '<div class="pg-list' . $sm_hidden . '" data-smap-list="' . $sm_slot . '">'
                    . $sm_rows . '</div>';

                // ── What the map colours ─────────────────────────
                //
                // Countries on the world view, regions on a
                // country's own. The tooltip is composed here for
                // the same reason the rows are: money formatting,
                // plurals and the translation table all live on
                // this side.
                $sm_entries = $sm_is_world ? $sm_period['countries'] : $sm_regions;
                $sm_steps = pg_sales_map_steps($sm_entries);
                $sm_tips = array();

                foreach ($sm_entries as $sm_entry_key => $sm_entry) {

                    if ($sm_entry['code'] === '') {
                        continue;
                    }

                    $sm_share = ($sm_total > 0)
                        ? round(($sm_entry['total'] / $sm_total) * 100)
                        : 0;

                    // A country names its own best regions, a region
                    // names its best cities: one level further down
                    // than whatever is being pointed at.
                    $sm_detail = array();

                    if ($sm_is_world) {

                        foreach ($sm_period['regions'] as $sm_region) {

                            if (($sm_region['country'] === $sm_entry['code']) && (count($sm_detail) < 3)) {
                                $sm_detail[] = h($sm_region['name']);
                            }
                        }

                    } else {

                        foreach ($sm_entry['cities'] as $sm_city) {

                            if ($sm_city['name'] !== '') {
                                $sm_detail[] = h($sm_city['name']);
                            }
                        }
                    }

                    $sm_tips[$sm_entry['code']] = array(
                        'b' => (int) ($sm_steps[$sm_entry_key] ?? 0),
                        'u' => $sm_url(
                            $sm_is_world ? $sm_entry['name'] : $sm_entry['country_name'],
                            $sm_is_world ? '' : $sm_entry['state'],
                            $sm_period['from']),
                        'p' => '<b>' . h($sm_is_world ? lang($sm_entry['name']) : $sm_entry['name']) . '</b>'
                            . '<span>' . $sm_money($sm_entry['total']) . ' &middot; '
                            . $sm_orders($sm_entry['count']) . '</span>'
                            . '<span>' . lang(array('string' => '{var:1}% share', 'vars' => $sm_share)) . '</span>'
                            . (!empty($sm_detail)
                                ? '<span class="text-muted">' . implode(', ', $sm_detail) . '</span>'
                                : ''));
                }

                $sm_payload['rows'][$sm_view_key][$sm_period_key] = $sm_tips;
            }
        }

        // ── Period switch ────────────────────────────────────────
        foreach ($sales_map['periods'] as $sm_period_key => $sm_period) {

            $sm_tabs .=
                '<li><button type="button" class="dropdown-item'
                . (($sm_period_key === $sales_map['period']) ? ' active' : '') . '"'
                . ' data-smap="' . $sm_period_key . '">'
                . '<i class="bi bi-check2 pg-smap-tick"></i>' . h($sm_period['label']) . '</button></li>';
        }

        // ── The switches, as a menu ──────────────────────────────
        //
        // A strip of buttons under the map cost it a band of its own
        // height, and a .btn-group stretched across the panel gives
        // every button an equal share of it whatever its label says
        // -- three period labels wrapped to two lines apiece there.
        // A menu in the corner costs one icon, and the head beside
        // the revenue already names the period on show.
        //
        // The map section only appears where there is a second map
        // to switch to.
        $sm_menu =
            '<div class="dropdown pg-smap-menu">'
            . '<button type="button" class="btn btn-sm btn-ghost pg-smap-menu-btn" id="pg_smap_menu"'
            . ' data-bs-toggle="dropdown" aria-expanded="false" aria-label="' . h(lang('Filter')) . '">'
            . '<i class="bi bi-sliders"></i></button>'
            . '<ul class="dropdown-menu dropdown-menu-end">'
            . '<li><h6 class="dropdown-header">' . h(lang('Period')) . '</h6></li>'
            . $sm_tabs
            . ((count($sm_views) > 1)
                ? '<li><hr class="dropdown-divider"></li>'
                    . '<li><h6 class="dropdown-header">' . h(lang('Map')) . '</h6></li>' . $sm_switch
                : '')
            . '</ul></div>';

        // ── Map panel ────────────────────────────────────────────
        //
        // The outlines are static files so the browser can cache
        // them: the dashboard rebuilds its widgets once a minute and
        // the paths do not need to come back with every rebuild.
        // Fetched rather than pointed at with an <img>, because a
        // path inside an <img> cannot be coloured.
        $output_data =
            '<div class="card-body p-0 pg-split" id="pg_smap">'
            . '<div class="pg-split-half pg-smap-main">'
            . $sm_heads
            . '<div class="pg-smap-canvas' . ($sm_can_open ? '' : ' is-static') . '" id="pg_smap_canvas" role="img" aria-label="' . h(lang('Sales Map')) . '">'
            . '<div class="pg-smap-tip" id="pg_smap_tip"></div>'
            . $sm_menu
            . '</div>'
            . '</div>'
            . '<div class="pg-split-half">' . $sm_lists . '</div>'
            . '</div>
            <script>(function(){

                var root = document.getElementById("pg_smap");

                if (!root) { return; }

                var data = ' . encode_json($sm_payload) . ';
                var view = data.view;
                var mode = data.mode;
                var canvas = document.getElementById("pg_smap_canvas");
                var tip = document.getElementById("pg_smap_tip");

                // One layer per view, fetched once and then kept:
                // switching back and forth must not go to the
                // network, and the browser cache is not a promise.
                var layers = {};

                function rows() {
                    return (data.rows[view] || {})[mode] || {};
                }

                function hide() {
                    if (tip) { tip.classList.remove("is-on"); }
                }

                // Painting is separate from loading: switching the
                // period recolours the paths that are already there
                // rather than fetching anything.
                function paint() {

                    var layer = layers[view];

                    if (!layer) { return; }

                    var current = rows();
                    var paths = layer.querySelectorAll("path[id]");

                    for (var i = 0; i < paths.length; i++) {
                        var row = current[paths[i].id];
                        paths[i].setAttribute("class", "pg-smap-s" + (row ? row.b : 0));
                    }
                }

                function reveal() {
                    for (var key in layers) {
                        layers[key].classList.toggle("d-none", key !== view);
                    }
                }

                function ensure() {

                    if (!canvas) { return; }

                    reveal();

                    if (layers[view]) { paint(); return; }

                    var url = data.maps[view];

                    if (!url) { return; }

                    fetch(url, { credentials: "same-origin" })
                        .then(function(response){
                            if (!response.ok) { throw new Error(response.status); }
                            return response.text();
                        })
                        .then(function(markup){
                            var layer = document.createElement("div");
                            layer.className = "pg-smap-layer";
                            layer.innerHTML = markup;
                            canvas.appendChild(layer);
                            layers[view] = layer;
                            reveal();
                            paint();
                        })
                        .catch(function(){
                            // The outlines are a presentation
                            // layer: every number they carry is
                            // already written in the list beside
                            // them, so a missing file costs the
                            // picture and nothing else.
                            canvas.classList.add("d-none");
                        });
                }

                function show() {

                    var slot = view + "|" + mode;

                    root.querySelectorAll("[data-smap-head],[data-smap-list]").forEach(function(el){
                        var owner = el.getAttribute("data-smap-head") || el.getAttribute("data-smap-list");
                        el.classList.toggle("d-none", owner !== slot);
                    });

                    root.querySelectorAll("[data-smap]").forEach(function(btn){
                        btn.classList.toggle("active", btn.getAttribute("data-smap") === mode);
                    });

                    root.querySelectorAll("[data-smap-view]").forEach(function(btn){
                        btn.classList.toggle("active", btn.getAttribute("data-smap-view") === view);
                    });

                    ensure();
                    hide();
                }

                function place(target) {
                    return (target && target.closest) ? target.closest("path[id]") : null;
                }

                if (canvas) {

                    canvas.addEventListener("mousemove", function(event){

                        var path = place(event.target);

                        if ((!path) || (!tip)) { hide(); return; }

                        var row = rows()[path.id];
                        var names = data.names[view] || {};
                        var body = row
                            ? row.p
                            : (names[path.id] ? ("<b>" + names[path.id] + "</b>" + data.none) : "");

                        if (!body) { hide(); return; }

                        var box = canvas.getBoundingClientRect();

                        tip.innerHTML = body;
                        tip.classList.add("is-on");

                        // Kept inside the card: a tooltip that hangs
                        // off the right edge is clipped by the split
                        // panel, not by the window.
                        var x = event.clientX - box.left + 14;
                        var y = event.clientY - box.top + 14;

                        x = Math.min(x, Math.max(0, box.width - tip.offsetWidth - 4));
                        y = Math.min(y, Math.max(0, box.height - tip.offsetHeight - 4));

                        tip.style.left = x + "px";
                        tip.style.top = y + "px";
                    });

                    canvas.addEventListener("mouseleave", hide);

                    canvas.addEventListener("click", function(event){

                        var path = place(event.target);
                        var row = path ? rows()[path.id] : null;

                        if (row && row.u) { window.location.href = row.u; }
                    });
                }

                root.querySelectorAll("[data-smap]").forEach(function(btn){
                    btn.addEventListener("click", function(){
                        mode = btn.getAttribute("data-smap");
                        show();
                    });
                });

                root.querySelectorAll("[data-smap-view]").forEach(function(btn){
                    btn.addEventListener("click", function(){
                        view = btn.getAttribute("data-smap-view");
                        canvas.classList.remove("d-none");
                        show();
                    });
                });

                // Popper in fixed strategy. The menu lives inside
                // the map panel, and that panel is a scroll box:
                // absolutely positioned, the menu is cut off at its
                // edge. Fixed takes it out of that box entirely.
                var menu = document.getElementById("pg_smap_menu");

                if ((menu) && (window.bootstrap) && (window.bootstrap.Dropdown)) {

                    new window.bootstrap.Dropdown(menu, {
                        popperConfig: function (config) {
                            config.strategy = "fixed";
                            return config;
                        }
                    });
                }

                show();
            })();</script>';

        return array(
            'status'  => 'success',
            'message' => lang('Data Received successfully.'),
            'data'    => $output_data);

    } else {

        return array(
            'status'  => 'error',
            'message' => 'Access denied');
    }
}
