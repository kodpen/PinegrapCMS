<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Dashboard widgets - the loader behind api.php's get_widget_data action.
 *
 * Every widget lives in its own file, includes/dashboard/widgets/widget_<id>.php,
 * and defines one function:
 *
 *     function pg_dashboard_widget_<id>($request, $user)
 *
 * $request is the decoded API request and $user the row validate_user()
 * returned. A widget checks its own access and returns an array with
 * 'status' ('success' or 'error'), 'message' and, on success, 'data' - the
 * card's HTML. It never echoes or exits; api.php respond()s with whatever this
 * loader returns, so a request loads only the widget it asked for.
 *
 * Widgets 21 and 22 are the two halves of the Firewall card. Widget 21 builds
 * the event half and calls pg_dashboard_widget_22($request, $user, $waf_panel),
 * which adds the threat half and returns both; a direct request for widget 22
 * answers with the threat half alone.
 *
 * The helpers the widgets share - the list row and its section heading, the
 * headline with its sparkline, the eight-day counts behind it, the ink colour
 * for a tile - are defined below the loader, so every widget file has them.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

// The gate every widget file checks.
define('PG_DASHBOARD_WIDGETS', true);

/**
 * @param array $request
 * @param array $user
 * @return array
 */
function pg_dashboard_widget_run($request, $user)
{
    $widget_id = isset($request['widget_id']) ? $request['widget_id'] : '';

    if (is_int($widget_id)) {
        $widget_id = (string) $widget_id;
    }

    $invalid = array(
        'status' => 'error',
        'message' => 'Invalid widget id.'
    );

    // The id becomes part of a file path, so only the known shapes pass.
    if ((!is_string($widget_id)) || (!preg_match('/^(clock|[1-9][0-9]{0,2})$/D', $widget_id))) {
        return $invalid;
    }

    $file = PG_FUNCTIONS_DIR . '/includes/dashboard/widgets/widget_' . $widget_id . '.php';

    if (!is_file($file)) {
        return $invalid;
    }

    require_once($file);

    $handler = 'pg_dashboard_widget_' . $widget_id;

    if (!function_exists($handler)) {
        return $invalid;
    }

    return $handler($request, $user);
}

// Eight daily counts -- seven days ago through today -- for one timestamp
// column, in ONE query.
//
// This replaces what the activity summary widget used to do: eight separate
// COUNT(*) per metric, twenty-four in total on every dashboard load. The counts
// are bucketed with CASE rather than GROUP BY on a formatted date, so the WHERE
// still compares the raw column against a constant and the index on it applies.
// Wrapping the column in FROM_UNIXTIME() to group by day would defeat exactly
// the index that makes this cheap.
//
// $extra is appended to the WHERE and must already be escaped by the caller.
function pg_activity_daily($table, $column, $extra = '')
{
    $midnight = strtotime(date('Y-m-d'));

    // Boundaries first, oldest to newest, so the buckets below read in the same
    // order the chart draws them.
    $edges = array();
    for ($day = 7; $day >= 0; $day--) {
        $edges[] = $midnight - ($day * 86400);
    }

    $sums = array();
    foreach ($edges as $index => $from) {
        // The last bucket is today and has no upper edge: nothing is recorded
        // in the future, and an upper bound of "now" would drop rows written
        // between building this query and running it.
        $to = isset($edges[$index + 1]) ? $edges[$index + 1] : 0;
        $sums[] = ($to > 0)
            ? "SUM(CASE WHEN $column >= $from AND $column < $to THEN 1 ELSE 0 END) AS d$index"
            : "SUM(CASE WHEN $column >= $from THEN 1 ELSE 0 END) AS d$index";
    }

    $row = db_item(
        "SELECT " . implode(', ', $sums) . "
         FROM $table
         WHERE ($column >= " . $edges[0] . ")" . $extra
    );

    $out = array();
    for ($index = 0; $index < 8; $index++) {
        $out[] = isset($row['d' . $index]) ? (int) $row['d' . $index] : 0;
    }

    return $out;
}

// Headline for a list widget: today's count, which way it moved against
// yesterday, the seven day total, and a sparkline of the eight days.
//
// One line rather than the block the pending shipments card uses. That card has
// a dozen rows under it and can spare the height; these three have lists of
// 52px rows, and a headline that cost 100px would take two of them.
function pg_widget_headline($properties)
{
    $series = $properties['series'];
    $unit   = $properties['unit'];
    $rgb    = $properties['rgb'];
    $id     = $properties['id'];

    $today     = (int) end($series);
    $yesterday = (int) $series[count($series) - 2];
    // The series carries eight days so the sparkline has a lead-in point
    // before the week it labels. The total sums the last seven only --
    // today plus six -- because that is what the label says.
    $total     = array_sum(array_slice($series, -7));

    if ($today > $yesterday) {
        $direction = 'up';
        $arrow = 'bi-caret-up-fill';
        $color = 'text-success';
    } elseif ($today < $yesterday) {
        $direction = 'down';
        $arrow = 'bi-caret-down-fill';
        $color = 'text-danger';
    } else {
        $direction = 'flat';
        $arrow = 'bi-dash';
        $color = 'text-muted';
    }

    return '
    <div class="pg-head">
        <div class="pg-head-line">
            <span class="pg-head-num">' . pg_format_number($today, 0) . '</span>
            <span class="pg-head-unit text-muted">' . $unit . '</span>
            <i class="bi ' . $arrow . ' pg-head-dir ' . $color . '" title="' . h(lang('Compared to yesterday')) . '"></i>
            <span class="pg-head-total text-muted">' . lang(array(
                'string' => 'in 7 days {var:1}',
                'vars' => '<b>' . pg_format_number($total, 0) . '</b>',
            )) . '</span>
        </div>
        <span class="pg-head-spark"><canvas id="' . $id . '" data-series="' . h(json_encode($series)) . '" data-rgb="' . $rgb . '"></canvas></span>
    </div>
    <script>(function(){
        var el = document.getElementById(' . json_encode($id) . ');
        if (!el || typeof Chart === "undefined") return;
        var series = JSON.parse(el.getAttribute("data-series"));
        var rgb = el.getAttribute("data-rgb");
        new Chart(el.getContext("2d"), {
            type: "line",
            data: { labels: series.map(function(_, i){ return i; }), datasets: [{
                data: series,
                borderColor: "rgba("+rgb+",1)", backgroundColor: "rgba("+rgb+",.16)",
                borderWidth: 1.6, fill: true, tension: 0.35, pointRadius: 0
            }]},
            options: {
                animation: false, responsive: true, maintainAspectRatio: false,
                plugins: { legend:{display:false}, tooltip:{enabled:false} },
                scales: { x:{display:false}, y:{display:false, beginAtZero:true} },
                events: []
            }
        });
    })();</script>';
}

// Dashboard list row. Every widget that renders a list builds its rows through
// this, so the cards read as one surface instead of a dozen dialects: an accent
// tile, a bold title with an optional value beside it, a muted second line, and
// an arrow. The shape is styled in backend.src.css (.pg-row-*), which is also
// what lets welcome.php reuse the same boxes for the loading skeleton.
//
// Values arrive already escaped, because several callers legitimately pass
// markup: a product thumbnail, a Bootstrap icon, a formatted amount.
//
// 'color' overrides the tile colour. The rule: the tile is the card's accent,
// so a card reads as one thing, EXCEPT where the row's state is exceptional
// and worth breaking the pattern for -- a cancelled order, an offer expiring
// in three days -- or where the row, not the card, is what the colour
// identifies, as pending shipments does with carriers. Two normal states of
// the same kind are told apart by the glyph, not by colour.
//
// A row with no 'href' renders as a div and drops the arrow, so a list that
// leads nowhere does not advertise a click that will not happen.
function pg_widget_row($properties)
{
    $href   = $properties['href']   ?? '';
    $badge  = $properties['badge']  ?? '';
    $name   = $properties['name']   ?? '';
    $aside  = $properties['aside']  ?? '';
    $meta   = $properties['meta']   ?? '';
    $color  = $properties['color']  ?? '';
    $target = $properties['target'] ?? '';

    $small = !empty($properties['small']);
    $muted = !empty($properties['muted']);
    $extra = $properties['class'] ?? '';

    // Nested anchors do not parse: the moment the browser meets an inner <a>
    // inside the row's own one it closes the row, and everything after that
    // point -- the rest of the meta line, the arrow -- lands in the list as a
    // sibling instead of inside the box. Log messages arrive with their URLs
    // already linkified, so a row that is itself a link keeps the text and
    // drops the inner tags. The row's own href is where it goes.
    if ($href !== '') {
        $name  = pg_strip_anchor_tags($name);
        $aside = pg_strip_anchor_tags($aside);
        $meta  = pg_strip_anchor_tags($meta);
    }

    $output_aside  = ($aside !== '') ? '<span class="pg-row-aside text-muted">' . $aside . '</span>' : '';
    $output_meta   = ($meta !== '') ? '<span class="pg-row-meta d-block text-muted">' . $meta . '</span>' : '';
    // A row that sets its own tile colour has to bring its own glyph colour
    // with it, because the CSS variable pairing only covers the card accents.
    $output_color = ($color !== '')
        ? ' style="background:' . $color . ';color:' . pg_readable_ink($color) . '"'
        : '';
    $output_target = ($target !== '') ? ' target="' . $target . '" rel="noopener"' : '';

    // A real link rather than an onclick handler, so the row is reachable by
    // keyboard and opens in a new tab on middle click like any other link.
    $class = 'pg-row' . ($small ? ' pg-row-sm' : '') . ($muted ? ' opacity-50' : '')
        . ($extra !== '' ? ' ' . $extra : '');

    if ($href !== '') {
        $open  = '<a href="' . $href . '" class="' . $class . '"' . $output_target . '>';
        $close = '<i class="bi bi-arrow-right text-muted pg-row-go"></i></a>';
    } else {
        $open  = '<div class="' . $class . '">';
        $close = '</div>';
    }

    return $open . '
        <span class="pg-row-badge"' . $output_color . '>' . $badge . '</span>
        <span class="pg-row-text">
            <span class="pg-row-line">
                <span class="pg-row-name">' . $name . '</span>
                ' . $output_aside . '
            </span>
            ' . $output_meta . '
        </span>
        ' . $close;
}

// Removes <a> and </a> while leaving the text and any other markup -- an icon,
// a <time>, a thumbnail -- untouched. Only the tags go, so nothing the caller
// meant to show is lost.
function pg_strip_anchor_tags($html)
{
    return preg_replace('#</?a\b[^>]*>#i', '', (string) $html);
}

// White or near-black, whichever reads better on the given background. Mid-tone
// brand colours are the problem case: white on #f59e0b is 2.15:1, which fails
// even the 3:1 that a graphical object needs, and some tiles hold initials,
// which is text and wants 4.5:1. Relative luminance per WCAG 2.1.
function pg_readable_ink($hex)
{
    $hex = ltrim((string) $hex, '#');
    if (strlen($hex) === 3) {
        $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
    }
    if (strlen($hex) !== 6 || !ctype_xdigit($hex)) {
        return '#fff';
    }

    $luminance = 0;
    $weights = array(0.2126, 0.7152, 0.0722);
    foreach (array(0, 2, 4) as $index => $offset) {
        $channel = hexdec(substr($hex, $offset, 2)) / 255;
        $channel = ($channel <= 0.03928)
            ? $channel / 12.92
            : pow(($channel + 0.055) / 1.055, 2.4);
        $luminance += $weights[$index] * $channel;
    }

    // 0.0593 is #18181b's relative luminance plus the same 0.05 offset.
    $against_white = 1.05 / ($luminance + 0.05);
    $against_dark  = ($luminance + 0.05) / 0.0593;

    return ($against_white >= $against_dark) ? '#fff' : '#18181b';
}

// Section heading inside a list, for the widgets that group their rows --
// "Top Pages" over "Top Products", "Expiring Soon" over the rest.
function pg_widget_row_heading($label, $aside = '')
{
    return '<div class="pg-row-heading text-muted">' . $label
        . (($aside !== '') ? '<span class="pg-row-aside">' . $aside . '</span>' : '')
        . '</div>';
}
