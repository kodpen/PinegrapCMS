<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The server side of the graph canvas core (assets/js/pg_graph_canvas.js,
 * window.PGGraphCanvas): the texts it shows whatever its boxes mean. A
 * screen that prints the core hands them to its script with its own texts;
 * the core reads them through its adapter's text().
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

/**
 * The texts of the graph canvas core, keys `pgc_*`.
 *
 * @return array key => text
 */
function pg_graph_canvas_js_strings()
{
    return array(
        // A box's role for a screen reader, when its user names none.
        'pgc_box'      => lang('Box'),
        // The title of a box's outgoing port while the graph can be changed.
        'pgc_port_out' => lang('Drag to the box that comes after it'),
    );
}
