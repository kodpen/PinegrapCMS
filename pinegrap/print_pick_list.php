<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * A wave's pick list on paper: the wave's name, then one row per product and
 * location in shelf order with a box to tick, its shelf address, code, name,
 * variant options, units and how many addresses want it; then the wave's
 * orders, each with its order code (Code 128 of the order number), the code
 * the Pack screen scans. Light paper whatever the panel theme, printed when
 * it opens. Manager and up, the orders right or the "Packing only" right.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

include('init.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/pick_waves.php');

$user = validate_user();
pg_pick_validate_access($user);

// One id; one sent as an array (?id[]=1) finds no wave.
$wave = pg_pick_wave(is_scalar($_GET['id'] ?? null) ? (int) $_GET['id'] : 0);

if (!$wave) {
    output_error(lang('The wave could not be found.'), 404);
}

$rows = '';

foreach (pg_pick_wave_lines($wave['id']) as $line) {
    $rows .= '
        <tr>
            <td class="tick"><span class="box"></span></td>
            <td class="bin">' . h($line['bin']) . '</td>
            <td>' . h($line['code']) . '</td>
            <td>' . h($line['name']) . (($line['options']) ? '<div class="options">' . h(implode(' · ', $line['options'])) . '</div>' : '') . '</td>
            <td class="units">' . h(pg_format_number($line['units'], 0)) . '</td>
            <td class="units">' . h(pg_format_number($line['addresses'], 0)) . '</td>
        </tr>';
}

$orders = '';

foreach (pg_pick_wave_addresses($wave['id']) as $address) {
    if ((int) $address['removed_at'] > 0 || (string) $address['order_number'] === '') {
        continue;
    }

    $orders .= '<div class="order"><svg class="order-code" data-pg-order-code="' . h((string) $address['order_number']) . '" role="img" aria-label="' . h((string) $address['order_number']) . '"></svg></div>';
}

echo
    output_header_secure(array('title' => lang('Picking list') . ' · ' . pg_pick_wave_label($wave), 'icon' => 'store')) . '
    <script>
        // A print view stands for the paper: light whatever the stored panel
        // theme. Set again on DOMContentLoaded, after backend.src.js (a
        // synchronous head script) has applied the stored theme.
        (function () {
            var pgPrintLight = function () { document.documentElement.setAttribute("data-bs-theme", "light"); };
            pgPrintLight();
            document.addEventListener("DOMContentLoaded", pgPrintLight);
        })();
    </script>
    <style type="text/css">@layer custom {
        body { background-color: white; color: black; font-family: Arial, Verdana, sans-serif; font-size: 85%; margin: 0; padding: 1em; }
        h1 { margin: 0 0 .2em 0; }
        h2 { margin: 1.5em 0 .5em 0; font-size: 1.1em; }
        table { border-collapse: collapse; width: 100%; }
        th { text-align: left; font-weight: bold; }
        td, th { border: #aaaaaa 1px solid; padding: .4em; vertical-align: top; }
        .tick { width: 1.6em; }
        .box { display: inline-block; width: 1em; height: 1em; border: black 1px solid; }
        .bin { font-size: 1.4em; font-weight: bold; white-space: nowrap; }
        .units { text-align: right; white-space: nowrap; }
        .options { color: #333333; font-size: .9em; }
        .orders { display: flex; flex-wrap: wrap; gap: 1em 2em; }
        .order-code { height: 60px; }
        tr { page-break-inside: avoid; }
    }</style>
    <div id="content">
        <h1>' . h(lang('Picking list')) . ' · ' . h(pg_pick_wave_label($wave)) . '</h1>
        <div>' . h(lang(array('string' => 'Made {var:1}', 'vars' => array(get_absolute_time(array('timestamp' => (int) $wave['created_at'], 'size' => 'short', 'format' => 'plain_text')))))) . '</div>
        <h2>' . h(lang('Shelves')) . '</h2>
        <table>
            <tr>
                <th><span class="visually-hidden">' . h(lang('Tick')) . '</span></th>
                <th>' . h(lang('Shelf')) . '</th>
                <th>' . h(lang('Code')) . '</th>
                <th>' . h(lang('Product')) . '</th>
                <th class="units">' . h(lang('Units')) . '</th>
                <th class="units">' . h(lang('Addresses')) . '</th>
            </tr>
            ' . $rows . '
        </table>
        ' . (($orders !== '') ? '<h2>' . h(lang('Orders')) . '</h2><div class="orders">' . $orders . '</div>' : '') . '
    </div>
    <script src="assets/lib/JsBarcode/JsBarcode.all.min.js"></script>
    <script>
        // Each order code as Code 128 of the order number, then the print
        // dialog.
        document.querySelectorAll("[data-pg-order-code]").forEach(function (svg) {
            try {
                JsBarcode(svg, svg.getAttribute("data-pg-order-code"), { format: "CODE128", height: 40, margin: 0, fontSize: 14 });
            } catch (e) { /* a number Code 128 cannot hold stays as its label */ }
        });
        window.onload = function () {
            setTimeout(function () { window.print(); }, 500);
        };
    </script>
' . output_footer_secure();
