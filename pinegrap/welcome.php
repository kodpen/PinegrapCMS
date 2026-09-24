<?php
/**
 * PineGrap - Enterprise Website Platform
 *
 * Originally developed as LiveSite by Camelback Web Architects.
 * Since 2017, maintained and evolved by Erdal Güral (Kodpen) under the name PineGrap.
 * The final LiveSite update (2019) has been integrated into PineGrap.
 * LiveSite remains available as a separate downloadable legacy version.
 *
 * @author      Camelback Web Architects
 *              Erdal Güral (Kodpen)
 * @link        https://livesite.com
 *              https://kodpen.com
 * @copyright   2001–2019 Camelback Consulting, Inc.
 *              2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

include ('init.php');
$user = validate_user();

$role = '';
switch ($user['role']){
    case '0':
        $role = lang('administrator');
    break;
    case '1':
        $role = lang('designer');
    break;
    case '2':
        $role = lang('manager');
    break;
    case '3':
        $role = lang('user');
    break;
}

//db("UPDATE config SET version = '2026.1.23'");

//we can set widget refresh frequent with url
// welcome.php?refresh=1 will refresh widgets every sec.
// More frequent refresh increases data consumption and creates more system load both server and visitor computer.
// we limit it with min 10 sec.
$widget_refresh_time = 60;//default 60 sec
if(isset($_REQUEST['refresh']) && is_numeric($_REQUEST['refresh']) && $_REQUEST['refresh'] >= 10){
    $_SESSION['software']['welcome']['widget']['refresh'] = $_REQUEST['refresh'];
}

// if widget refresh time value was passed in the query string
if(isset($_SESSION['software']['welcome']['widget']['refresh'])){
    $widget_refresh_time = ($_SESSION['software']['welcome']['widget']['refresh'] ?? '');
}

// Add pinegrap notices
include_once('liveform.class.php');
$liveform = new liveform('welcome');

// if the user has a user role and the user does not have edit access to any folders and the user does not have access to control panels, then deny access to software welcome screen
if (($user['role'] == 3) && (no_acl_check($user['id']) == false) && ($user['manage_calendars'] == false) && ($user['manage_forms'] == false) && ($user['manage_visitors'] == false) && ($user['manage_contacts'] == false) && ($user['manage_emails'] == false) && ($user['manage_ecommerce'] == false) && ($user['manage_ecommerce_reports'] == false) && (empty($user['manage_erp'])) && (empty($user['manage_workspace'])) && (count(get_items_user_can_edit('ad_regions', $user['id'])) == 0))
{
    log_activity("access denied to welcome screen", $_SESSION['sessionusername']);
    output_error(lang('Access denied.') . ' <a href="javascript:history.go(-1)">' . lang('Go back') . '</a>.');
}

$query = "SELECT * FROM dashboard";
$result = mysqli_query(db::$con, $query) or output_error('Query failed.');
$dashboard = mysqli_fetch_assoc($result);

// ── Appearance ──────────────────────────────────────────────────────────────
//
// Two administrator-chosen settings, both on the dashboard row: which of the
// four treatments the cards wear, and what sits behind them. They are read here
// and written onto #content further down, where CSS can reach them.
//
// A plain ?? rather than a column check, because $dashboard is already a
// SELECT *: an installation that has not run 2026.4.4 has neither key and
// falls through to the pair it has been looking at all along. The whitelists do
// the same job for a value that was hand-edited in the database.
$widget_theme = (string) ($dashboard['widget_theme'] ?? '');

if (!in_array($widget_theme, array('flat', 'neon', 'glass', 'aurora'), true)) {
    $widget_theme = 'flat';
}

$panel_backdrop = (string) ($dashboard['panel_backdrop'] ?? '');

if (!in_array($panel_backdrop, array('auto', 'none', 'mesh', 'dusk', 'ember'), true)) {
    $panel_backdrop = 'auto';
}

// "auto" means "whatever the card theme needs", and that is a mapping CSS
// cannot express -- an attribute selector cannot read a second attribute. It is
// resolved here instead, which is also why changing the theme reloads the page.
//
// Flat maps to none deliberately: an installation that has never opened this
// menu must look exactly as it did, and flat is the theme it is already on.
// Glass maps to mesh because glass over a flat colour is not glass, it is a
// tint -- the wash is the thing it refracts.
if ($panel_backdrop == 'auto') {

    $panel_backdrop_for_theme = array(
        'flat'   => 'none',
        'neon'   => 'dusk',
        'glass'  => 'mesh',
        'aurora' => 'mesh');

    $panel_backdrop = $panel_backdrop_for_theme[$widget_theme];
}

$output_header_includes = '';

// Release notes.
//
// Read here and embedded in the modal rather than linked, so the browser never
// requests changelog.txt over the network. The file sits in the software
// directory, which is served directly, and a public release list tells anyone
// which version a site runs and which security fixes it is missing.
//
// Drawn the way the install screen draws it -- a coloured tag beside each
// entry, grouped under its version -- rather than as the raw file in a <pre>.
// Same file, same parse; the monospace dump kept the banner and the column
// alignment of a text editor, which is exactly what a reader does not need
// here. Newest version is open, the rest are folded away.
//
// Costs one small file read on a screen that already assembles a dozen widgets.
$output_changelog_button = '';
$output_changelog_modal = '';

$changelog_sections = pg_changelog_sections();

if (count($changelog_sections) > 0) {

    // The head of the file: the wordmark, the title and the tag key. The
    // drawing needs a monospace block that does not wrap; the key is drawn with
    // the same badges the entries below carry, so it reads as a key rather than
    // as a paragraph about one.
    $changelog_preamble = pg_changelog_preamble();
    $output_changelog_head = '';

    if ($changelog_preamble['banner'] != '') {
        $output_changelog_head .= '
        <pre class="mb-2 text-body-secondary" style="white-space:pre; overflow-x:auto; font-size:.72rem; line-height:1.35; font-family:ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;">' . h($changelog_preamble['banner']) . '</pre>';
    }

    if (count($changelog_preamble['legend']) > 0) {

        $output_changelog_legend = '';

        foreach ($changelog_preamble['legend'] as $changelog_legend_row) {
            $output_changelog_legend .= '
            <span class="d-inline-flex align-items-center gap-1 me-3">
                <span class="badge ' . pg_changelog_tag_class($changelog_legend_row['tag']) . '">' . h($changelog_legend_row['tag']) . '</span>
                <span class="small text-body-secondary">' . h($changelog_legend_row['text']) . '</span>
            </span>';
        }

        $output_changelog_head .= '
        <div class="d-flex flex-wrap gap-y-1 pb-3 mb-3 border-bottom">' . $output_changelog_legend . '</div>';
    }

    $output_changelog_versions = '';

    foreach ($changelog_sections as $changelog_index => $changelog_section) {

        $output_changelog_entries = '';
        $changelog_group = '';

        foreach ($changelog_section['entries'] as $changelog_entry) {

            // A release big enough to be written under topic headings keeps
            // them here. Sixty entries in one undifferentiated column is a
            // list nobody finishes; the heading is what lets a reader skip
            // the four areas they do not use and stop at the one they do.
            $changelog_entry_group = isset($changelog_entry['group']) ? $changelog_entry['group'] : '';

            if ($changelog_entry_group != $changelog_group) {

                $changelog_group = $changelog_entry_group;

                if ($changelog_group != '') {
                    $output_changelog_entries .= '
            <div class="small fw-bold text-uppercase text-body-secondary pt-3 pb-1">' . h($changelog_group) . '</div>';
                }
            }

            $output_changelog_tags = '';

            foreach ($changelog_entry['tags'] as $changelog_tag) {
                $output_changelog_tags .=
                    '<span class="badge ' . pg_changelog_tag_class($changelog_tag) . '">'
                    . h($changelog_tag) . '</span>';
            }

            $output_changelog_entries .= '
            <div class="d-flex gap-2 py-2 border-bottom border-1">
                <div class="d-flex flex-column gap-1 flex-shrink-0" style="width:6.5rem">' . $output_changelog_tags . '</div>
                <div class="small">' . h($changelog_entry['text']) . '</div>
            </div>';
        }

        // Only the newest section is open. A reader who came for "what changed"
        // wants this version; the older ones are history and would bury it.
        $changelog_open = ($changelog_index == 0);
        $changelog_id = 'changelog_v' . preg_replace('/[^0-9]/', '_', $changelog_section['version']);

        $output_changelog_versions .= '
        <div class="mb-2">
            <button class="btn btn-link p-0 text-decoration-none fw-bold' . ($changelog_open ? '' : ' collapsed') . '"
                    type="button" data-bs-toggle="collapse" data-bs-target="#' . $changelog_id . '"
                    aria-expanded="' . ($changelog_open ? 'true' : 'false') . '" aria-controls="' . $changelog_id . '">
                <i class="bi bi-chevron-down me-1"></i>' . h($changelog_section['version']) . '
            </button>
            <div class="collapse' . ($changelog_open ? ' show' : '') . '" id="' . $changelog_id . '">
                ' . $output_changelog_entries . '
            </div>
        </div>';
    }

    // The trigger rides the System Status header; the dialog cannot follow it
    // there, because a widget card clips and scrolls its own content. It is
    // printed once with the page body instead.
    $output_changelog_button = '<button title="' . lang('Release Notes') . '" aria-label="' . lang('Release Notes') . '" class="pg-widget-action no-popover" type="button" data-bs-toggle="modal" data-bs-target="#changelog_modal"><span class="bi bi-card-list"></span></button>';

    $output_changelog_modal = '
    <div class="modal fade" id="changelog_modal" tabindex="-1" aria-labelledby="changelog_modal_label" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="changelog_modal_label">
                        <span class="bi bi-card-list me-2"></span>' . lang('Release Notes') . '
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="' . lang('Close') . '"></button>
                </div>
                <div class="modal-body text-start">
                    ' . $output_changelog_head . $output_changelog_versions . '
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">' . lang('Close') . '</button>
                </div>
            </div>
        </div>
    </div>';
}
$output_widget_controls = '';
// ── Loading skeletons ───────────────────────────────────────────────────────
//
// .card.widget is aspect-ratio 1/1, so a widget card never resizes and the
// grid cannot reflow when the AJAX lands. What the single generic skeleton got
// wrong was where the boxes sit *inside* the card: one 50px circle over three
// full-width lines matches no widget body api.php actually returns, so every
// card visibly rearranged itself as its data arrived.
//
// Each shape below mirrors what api.php sends for that widget. Pixel heights
// are explicit for two reasons: Bootstrap sizes .placeholder from the font
// size of the line it sits on rather than the one the real markup uses, and
// pinning the height of the blocks above a list is what puts the list itself
// in the right place. Every number here was measured against the loaded card
// in a browser, not estimated.
//
// One case cannot be solved from here: a widget with nothing to show renders a
// centred "none yet" panel, and no skeleton can know that before the data
// arrives. Widgets 19, 25 and 26 will always shift when they come back empty.

$pg_ph_card = function ($inner) {
    return '<div class="card-body card-body-placeholder p-0 overflow-hidden placeholder-glow">' . $inner . '</div>';
};

// The shared dashboard row. It borrows .pg-row and .pg-row-text from the real
// markup rather than re-describing them, so the two match by construction and
// cannot drift apart the next time the row is restyled. The tile is a plain
// placeholder box rather than .pg-row-badge, because that class paints itself
// with the card's accent colour and the skeleton would look already loaded.
$pg_ph_row = '
<div class="pg-row">
    <span class="placeholder rounded flex-shrink-0" style="width:34px;height:34px"></span>
    <span class="pg-row-text">
        <span class="placeholder col-8 d-block" style="height:13px"></span>
        <span class="placeholder col-5 d-block mt-1" style="height:11px"></span>
    </span>
</div>';

// The compact variant, for the widgets that list one line per entry.
$pg_ph_row_sm = '
<div class="pg-row pg-row-sm">
    <span class="placeholder rounded flex-shrink-0" style="width:22px;height:22px"></span>
    <span class="pg-row-text">
        <span class="placeholder col-7 d-block" style="height:12px"></span>
    </span>
</div>';

// Section heading inside a list -- "Top Pages", "Expiring Soon".
$pg_ph_heading = '
<div class="pg-row-heading">
    <span class="placeholder col-5 rounded" style="height:11px"></span>
</div>';

// KPI tile strip, in the two sizes api.php ships: compact (widgets 2 and 13,
// a 12px inline icon) and large (widgets 3 and 4, a 20px block icon). The
// strip height is pinned rather than left to the contents, because everything
// below the strip inherits any error in it.
$pg_ph_tiles = function ($height, $large) {
    $icon = $large ? 20 : 12;
    $tile =
        '<div class="flex-fill rounded ' . ($large ? 'p-2' : 'px-1 py-1') . ' text-center d-flex flex-column align-items-center justify-content-center" style="gap:4px">'
        . '<span class="placeholder rounded" style="width:' . $icon . 'px;height:' . $icon . 'px"></span>'
        . '<span class="placeholder rounded" style="width:60%;height:' . ($large ? 15 : 13) . 'px"></span>'
        . '<span class="placeholder rounded" style="width:80%;height:' . ($large ? 12 : 9) . 'px"></span>'
        . '</div>';
    return '<div class="d-flex ' . ($large ? 'gap-2 p-2' : 'gap-1 px-2 pt-2 pb-1') . '" style="height:' . $height . 'px">' . str_repeat($tile, 3) . '</div>';
};

// Panels of a given height, for the widgets whose body is neither a list nor a
// tile strip. Anything from 100px up is a chart, calendar or similar, so it
// gets one soft rectangle; shorter blocks get a couple of lines so they read
// as a summary strip rather than a slab.
$pg_ph_blocks = function ($heights) {
    $out = '';
    foreach ($heights as $height) {
        if ($height >= 100) {
            $out .= '<div class="p-2" style="height:' . $height . 'px"><span class="placeholder w-100 h-100 rounded"></span></div>';
        } else {
            $out .= '<div class="d-flex flex-column justify-content-center px-2 border-bottom" style="height:' . $height . 'px;gap:6px">'
                . '<span class="placeholder col-7 rounded" style="height:12px"></span>'
                . '<span class="placeholder col-4 rounded" style="height:10px"></span>'
                . '</div>';
        }
    }
    return $out;
};

// The headline that replaced the activity summary. Same 46px on all three
// widgets that carry it, so it is one shape rather than three that can drift.
$pg_ph_head = '
<div class="pg-head">
    <div class="pg-head-line">
        <span class="placeholder rounded" style="width:26px;height:19px"></span>
        <span class="placeholder col-4 rounded"></span>
    </div>
    <span class="placeholder rounded d-block" style="height:20px"></span>
</div>';

// Ecommerce summary head: the stock line, its detail line and the two facts
// under it. Mirrors the real markup's own classes so the padding cannot drift.
// The three line boxes carry their measured heights rather than their
// contents': a placeholder bar is sized from the style attribute, the real
// line from the font's line-height, and the two do not agree. Measured on the
// loaded card -- value line 22.8, detail line 15.8, fact column 36.3 -- which
// puts the separator and every row below it at the same offset in both states.
$pg_ph_ec = '
<div class="pg-ec-head">
    <div class="pg-ec-line" style="height:22.8px">
        <span class="placeholder rounded" style="width:112px;height:19px"></span>
        <span class="placeholder rounded" style="width:54px;height:10px"></span>
    </div>
    <div class="pg-ec-sub" style="height:15.8px"><span class="placeholder col-9 rounded d-block" style="height:10px"></span></div>
</div>
<div class="pg-ec-facts">
    <div class="pg-ec-fact" style="height:36.3px">
        <span class="placeholder col-9 rounded" style="height:10px"></span>
        <span class="placeholder col-7 rounded" style="height:13px;margin-top:6px"></span>
    </div>
    <div class="pg-ec-fact" style="height:36.3px">
        <span class="placeholder col-8 rounded" style="height:10px"></span>
        <span class="placeholder col-10 rounded" style="height:13px;margin-top:6px"></span>
    </div>
</div>';

// Default: the plain list, which is what most widgets render.
$placeholder = $pg_ph_card('<div class="pg-list">' . str_repeat($pg_ph_row, 5) . '</div>');

$placeholder_w10 = $pg_ph_card($pg_ph_head . '<div class="pg-list">' . str_repeat($pg_ph_row, 4) . '</div>');

// Sales map: the period head over the outline on the left, the ranked regions
// on the right. Uses the widget's own .pg-split, so a card too narrow for two
// panels stacks here exactly as it will once the data lands.
$placeholder_w1 = $pg_ph_card(
    '<div class="pg-split">'
    . '<div class="pg-split-half d-flex flex-column">'
    . $pg_ph_head
    . '<div class="p-2 flex-fill d-flex"><span class="placeholder w-100 rounded" style="min-height:140px"></span></div>'
    . '</div>'
    . '<div class="pg-split-half"><div class="pg-list">' . str_repeat($pg_ph_row, 5) . '</div></div>'
    . '</div>');

$placeholder_w3  = $pg_ph_card($pg_ph_ec . '<div class="border-top mx-2 mb-1"></div><div class="pg-list">' . str_repeat($pg_ph_row_sm, 4) . '</div>');
$placeholder_w4  = $pg_ph_card($pg_ph_tiles(126, true)  . $pg_ph_blocks(array(133)));
$placeholder_w5  = $pg_ph_card($pg_ph_blocks(array(277)));
$placeholder_w6  = $pg_ph_card('<div class="pg-list">' . $pg_ph_heading . str_repeat($pg_ph_row_sm, 8) . '</div>');
$placeholder_w8  = $pg_ph_card($pg_ph_head . $pg_ph_blocks(array(27)) . '<div class="pg-list">' . str_repeat($pg_ph_row, 3) . '</div>');
$placeholder_w13 = $pg_ph_card($pg_ph_head . '<div class="pg-list">' . str_repeat($pg_ph_row, 4) . '</div>');
$placeholder_w15 = $pg_ph_card('<div class="pg-list">' . $pg_ph_heading . str_repeat($pg_ph_row, 4) . '</div>');
$placeholder_w18 = $pg_ph_card($pg_ph_blocks(array(29, 91, 29, 110)));
$placeholder_w20 = $pg_ph_card($pg_ph_blocks(array(231)));
$placeholder_w21 = $pg_ph_card($pg_ph_blocks(array(47, 36, 58, 86)));
$placeholder_w23 = $pg_ph_card($pg_ph_blocks(array(79, 51, 28, 69)));
// System status: the gauge over its tile grid on the left, the jobs column on
// the right. Both halves borrow the widget's own classes so the boxes land
// where api.php will put them, and .pg-split is what makes them stack again on
// a card too narrow for two panels.
$placeholder_w24 = $pg_ph_card(
    '<div class="pg-split">'
    . '<div class="pg-split-half">'
    . '<div class="pg-health">'
    . '<span class="placeholder rounded d-block mx-auto" style="width:170px;height:80px"></span>'
    . '<span class="placeholder rounded d-block mx-auto mt-2" style="width:70px;height:12px"></span>'
    . '</div>'
    . '<div class="pg-checks">'
    . '<span class="placeholder rounded" style="width:74px;height:21px"></span>'
    . '<span class="placeholder rounded" style="width:58px;height:21px"></span>'
    . '<span class="placeholder rounded" style="width:66px;height:21px"></span>'
    . '<span class="placeholder rounded" style="width:88px;height:21px"></span>'
    . '<span class="placeholder rounded" style="width:62px;height:21px"></span>'
    . '<span class="placeholder rounded" style="width:80px;height:21px"></span>'
    . '<span class="placeholder rounded" style="width:54px;height:21px"></span>'
    . '<span class="placeholder rounded" style="width:92px;height:21px"></span>'
    . '<span class="placeholder rounded" style="width:70px;height:21px"></span>'
    . '<span class="placeholder rounded" style="width:60px;height:21px"></span>'
    . '</div>'
    . '</div>'
    . '<div class="pg-split-half">'
    . '<div class="pg-jobs">' . str_repeat('<span class="placeholder rounded d-block" style="height:29px"></span>', 7) . '</div>'
    . '</div>'
    . '</div>'
);
$placeholder_w25 = $pg_ph_card($pg_ph_blocks(array(35)) . '<div class="pg-list">' . str_repeat($pg_ph_row, 4) . '</div>');

// Loading state for the pending shipments card. The card itself cannot resize
// -- .card.widget is aspect-ratio 1/1 -- so this is not about the grid moving.
// It is about the boxes inside: the generic skeleton above puts them nowhere
// near where the loaded body puts them, so the content visibly slides into
// place. Mirroring the real structure holds every box within a pixel of where
// api.php will put it. Measured against the live card: head, meter and first
// row all land within 1px.
$placeholder_shipments = '
<div class="card-body card-body-placeholder p-0 overflow-hidden">
    <div class="pg-ship-head placeholder-glow">
        <div class="d-flex align-items-baseline flex-wrap gap-2">
            <span class="placeholder rounded" style="width:46px;height:32px"></span>
            <span class="placeholder col-6 rounded"></span>
        </div>
        <div class="pg-ship-meter">
            <span></span>
            <span></span>
            <span></span>
            <span></span>
            <span></span>
            <span></span>
            <span></span>
            <span></span>
            <span></span>
            <span></span>
            <span></span>
            <span></span>
        </div>
    </div>
    <div class="pg-ship-list placeholder-glow">
        <div class="pg-ship-row">
            <span class="placeholder pg-ship-badge"></span>
            <span class="pg-ship-text">
                <span class="placeholder col-7 d-block mb-1"></span>
                <span class="placeholder col-4 d-block"></span>
            </span>
        </div>
        <div class="pg-ship-row">
            <span class="placeholder pg-ship-badge"></span>
            <span class="pg-ship-text">
                <span class="placeholder col-7 d-block mb-1"></span>
                <span class="placeholder col-4 d-block"></span>
            </span>
        </div>
        <div class="pg-ship-row">
            <span class="placeholder pg-ship-badge"></span>
            <span class="pg-ship-text">
                <span class="placeholder col-7 d-block mb-1"></span>
                <span class="placeholder col-4 d-block"></span>
            </span>
        </div>
    </div>
</div>';

// ── Widget registry ─────────────────────────────────────────────────────────
//
// One entry per dashboard card: what it is called, the skeleton it wears while
// its data is in flight, and the single condition under which this user may see
// it at all.
//
// It replaces twenty-five $output_widget_N variables that each repeated the
// same markup, and that shape is what hid the ordering fault. A widget whose
// condition failed became an empty string but kept its slot in a second array,
// so "may this user see it" and "where does it sit" were two facts held in two
// places -- and the browser was only ever told one of them.
//
// Here 'show' answers the first question and nothing else. Position is worked
// out below, out of dashboard.order_widgets, and position never decides
// visibility. A widget is on the screen whenever its settings and permissions
// allow it, whatever the stored order happens to say.
//
// The order of the entries is the factory order, and it is what Reset Widgets
// restores. A widget the stored order has never heard of -- one that arrived
// with an upgrade -- is placed by it, at the end. That is what lets a new
// widget appear on its own, with no migration and with nobody having to press
// Reset Widgets.
//
// Ids are permanent: api.php answers get_widget_data on the same number and
// dashboards in the field have it stored. 22 and 24 are retired and are not
// reused.
//
// 1 is the exception, and it is a deliberate one: the slot answered with an
// empty string for long enough that no dashboard can be showing anything under
// it, so the sales map took it rather than opening a new number. The cost is
// that a stored order which still lists 1 places the map where the old widget
// used to sit -- until someone resets the order, not where this registry puts
// it.
//
// Fields, all optional except 'show': title, icon (a Bootstrap icon class),
// action (extra markup in the header), body (the loading skeleton), wide
// (spans two grid tracks), show (may this user see it).
$widget_defaults = array(
    'title'  => '',
    'icon'   => '',
    'action' => '',
    'body'   => '',
    'wide'   => false,
    'show'   => false);

$widget_registry = array();

// Visitor summaries. First card on a factory dashboard, because the question
// "is anybody out there" is the one people open this screen for.
$widget_registry[5] = array(
    'title' => lang('Visitor Summaries'),
    'icon'  => 'bi-graph-up',
    'body'  => $placeholder_w5,
    'wide'  => true,
    'show'  => (($user['role'] < 3) || ($user['manage_visitors'] == true)));

// System status. One health score for the installation: the security checks
// alongside the backup, update and scheduled-task checks that used to be a
// separate maintenance card at this same id. Limited to the roles that can act
// on any of it -- backups.php and software_update.php are both manager and
// above, so below that the card is a list of worries nobody reading it can
// clear.
$widget_registry[2] = array(
    'title'  => lang('System Status'),
    'icon'   => 'bi-activity',
    'action' => $output_changelog_button,
    'body'   => $placeholder_w24,
    // Two tracks. The card carries a gauge, a dozen readings and seven jobs,
    // and in one track the jobs were four-character tiles that had stopped
    // saying what they did. Wide, the readings keep the left half and the jobs
    // get a line each with room for a verb.
    'wide'   => true,
    'show'   => ($user['role'] < 3));

$widget_registry[3] = array(
    'title' => lang('Ecommerce Summary'),
    'icon'  => 'bi-cash-stack',
    'body'  => $placeholder_w3,
    'show'  => ((ECOMMERCE === true) && (($user['role'] < 3) || USER_MANAGE_ECOMMERCE || USER_MANAGE_ECOMMERCE_REPORTS)));

// Sales map, directly under the ecommerce summary: that card says how much was
// sold, this one says where from, and the two are read together.
//
// Two tracks, because the left panel holds a map -- of the world, or of one
// country -- and the right one a ranked list; in a single track the outlines
// are too small to find anything on.
//
// Gated on commerce REPORTS rather than on commerce: the card is a sales
// report, and the two are separate permissions on a user. Roles 0-2 hold it
// unconditionally, a contributor only when it has been granted -- the same
// reach as view_order_report.php, which is where this data otherwise comes
// from. Managing orders is not the same as being shown what the shop earns.
$widget_registry[1] = array(
    'title' => lang('Sales Map'),
    'icon'  => 'bi-map',
    'body'  => $placeholder_w1,
    'wide'  => true,
    'show'  => ((ECOMMERCE === true) && USER_MANAGE_ECOMMERCE_REPORTS));

$widget_registry[4] = array(
    'title' => lang('Online Engagement'),
    'icon'  => 'bi-broadcast-pin',
    'body'  => $placeholder_w4,
    'wide'  => true,
    'show'  => ($user['role'] < 3));

$widget_registry[6] = array(
    'title' => lang('Trending Content'),
    'icon'  => 'bi-fire',
    'body'  => $placeholder_w6,
    'show'  => (($user['role'] < 3) || ($user['manage_visitors'] == true)));

// The one card with no condition at all: everyone who reaches this screen has
// something they last edited.
$widget_registry[7] = array(
    'title' => lang('Recent Updates'),
    'icon'  => 'bi-clock-history',
    'body'  => $placeholder,
    'show'  => true);

// Orders. The tab strip is gone: both lists are on screen at once, so the
// header is a plain title like every other widget and each half labels itself.
$widget_registry[8] = array(
    'title' => lang('Orders'),
    'icon'  => 'bi-cart4',
    'body'  => $placeholder_w8,
    'wide'  => true,
    'show'  => ((ECOMMERCE == true) && (($user['role'] < 3) || ($user['manage_ecommerce'] == true))));

$widget_registry[9] = array(
    'title' => lang('Pending Shipments'),
    'icon'  => 'bi-truck',
    'body'  => $placeholder_shipments,
    'show'  => ((ECOMMERCE === true) && (ECOMMERCE_SHIPPING === true) && (($user['role'] < 3) || ($user['manage_ecommerce'] == true))));

$widget_registry[10] = array(
    'title' => lang('Contacts'),
    'icon'  => 'bi-person-lines-fill',
    'body'  => $placeholder_w10,
    'show'  => (($user['role'] < 3) || ($user['manage_contacts'] == true)));

$widget_registry[11] = array(
    'title' => lang('Users'),
    'icon'  => 'bi-people',
    'body'  => $placeholder,
    'show'  => ($user['role'] < 3));

$widget_registry[12] = array(
    'title' => lang('Out of Stock Products'),
    'icon'  => 'bi-exclamation-diamond',
    'body'  => $placeholder,
    'show'  => ((ECOMMERCE == true) && (($user['role'] < 3) || ($user['manage_ecommerce'] == true))));

$widget_registry[13] = array(
    'title' => lang('Forms'),
    'icon'  => 'bi-file-earmark-text',
    'body'  => $placeholder_w13,
    'show'  => (($user['role'] < 3) || ($user['manage_forms'] == true)));

// Subscriptions. Administrator only, and only where the installation has a
// subscription key to report on. Carries no icon, as it always has.
$widget_registry[14] = array(
    'title' => lang('Subscriptions'),
    'body'  => $placeholder,
    'show'  => (($user['role'] < 1) && (trim((string) SUBSCRIPTION_ID) != '')));

$widget_registry[15] = array(
    'title' => lang('Offers'),
    'icon'  => 'bi-tag',
    'body'  => $placeholder_w15,
    'show'  => ((ECOMMERCE === true) && (($user['role'] < 3) || ($user['manage_ecommerce'] == true))));

$widget_registry[16] = array(
    'title' => lang('Current Site Exchange Rates'),
    'icon'  => 'bi-currency-exchange',
    'body'  => $placeholder,
    'show'  => ((ECOMMERCE == true) && (($user['role'] < 3) || ($user['manage_ecommerce'] == true))));

$widget_registry[17] = array(
    'title' => lang('Site Logs'),
    'icon'  => 'bi-journal-text',
    'body'  => $placeholder,
    'show'  => ($user['role'] < 3));

// File management. Replaced the old Admin Notes widget and deliberately kept
// its id, so dashboards that already stored "18" picked the new widget up in
// the same slot.
//
// Shown to anyone who can act on a file. Roles 0-2 always can; a role 3
// contributor only when at least one folder was granted to them with edit
// rights, otherwise the card would be permanently empty for them. Which files,
// and whether design files are included at all, is decided in api.php where the
// data is built.
$widget_registry[18] = array(
    'title' => lang('File Management'),
    'icon'  => 'bi-hdd-stack',
    'body'  => $placeholder_w18,
    'show'  => (($user['role'] < 3) || no_acl_check($user['id'])));

$widget_registry[19] = array(
    'title' => lang('Email Campaigns'),
    'icon'  => 'bi-megaphone',
    'body'  => $placeholder,
    'show'  => ($user['role'] < 3));

$widget_registry[20] = array(
    'title' => lang('Calendars'),
    'icon'  => 'bi-calendar3',
    'body'  => $placeholder_w20,
    'show'  => (validate_calendars_access($user, true) != false));

// Firewall: staff roles only. Contributors are excluded, because firewall
// events carry raw attack payloads and visitor addresses.
//
// One card, two halves: the event feed beside the threat digest. They were
// widgets 21 and 22; reading either without the other only ever gave half the
// picture, so api.php builds both panels under id 21 and 22 is retired.
$widget_registry[21] = array(
    'title' => lang('Firewall'),
    'icon'  => 'bi-shield-check',
    'body'  => $placeholder_w21,
    'wide'  => true,
    'show'  => ($user['role'] < 3));

// Performance, and only while monitoring is on. With it off the tables are
// emptied, so the card would sit there showing zeroes as though the site had no
// traffic -- which reads as a fault rather than as a setting.
$widget_registry[23] = array(
    'title' => lang('Performance'),
    'icon'  => 'bi-speedometer2',
    'body'  => $placeholder_w23,
    'show'  => (($user['role'] < 3) && ((!defined('PERF_MONITOR_ENABLED')) || PERF_MONITOR_ENABLED)));

// Comment moderation queue. Same reach as view_comments.php: staff always, and
// a contributor once at least one folder has been granted to them, since the
// queue is scoped through the commented page's folder.
$widget_registry[25] = array(
    'title' => lang('Comment Moderation'),
    'icon'  => 'bi-chat-square-quote',
    'body'  => $placeholder_w25,
    'show'  => (($user['role'] < 3) || no_acl_check($user['id'])));

// Cancelled orders whose money has not gone back yet. Same reach as the rest of
// the shop screens, because view_order.php is where the refund is marked done
// and that is what every row here links to.
$widget_registry[26] = array(
    'title' => lang('Refund Pending'),
    'icon'  => 'bi-cash-coin',
    'body'  => $placeholder_w25,
    'show'  => ((ECOMMERCE == true) && (($user['role'] < 3) || ($user['manage_ecommerce'] == true))));

// ── Order ───────────────────────────────────────────────────────────────────
//
// dashboard.order_widgets is a preference about position and nothing else. It
// is not a list of which widgets exist, and above all it is not a list of which
// widgets are switched on. Reading it as either is what used to lose cards:
//
//   1. Ecommerce is switched off in the settings. Its cards stop being drawn,
//      which is correct.
//   2. Someone drags one of the remaining cards. The old save collected the ids
//      that were in the grid -- which by then no longer included the ecommerce
//      ones -- and wrote that shortened list over the stored order.
//   3. Ecommerce goes back on. The missing ids are nowhere in the stored order,
//      so the positions they had are gone for good, and the cards can only
//      reappear in a heap at the end of the dashboard. To an operator who had
//      arranged the screen, that is the widgets not coming back at all, and
//      Reset Widgets is the only way out.
//
// The stored order now always carries every id, hidden ones included, and the
// browser is given the whole list so that a drag can put the hidden ones back
// where they were (see pg_merge_widget_order below).
//
// This end refuses to let a missing id mean anything either. Unknown ids are
// dropped, known ids that are absent are appended, and what comes out is a
// complete list every time -- which is also why a widget added by an upgrade
// needs no migration.
$widget_ids = array_keys($widget_registry);
$widget_order = array();

// 'default' is what Reset Widgets writes, and an empty column is what a fresh
// row holds. Both mean "factory order", which is the registry order, so both
// start from an empty list and let the append below fill it in.
$stored_widget_order = trim((string) ($dashboard['order_widgets'] ?? ''));

if ($stored_widget_order != 'default') {

    foreach (explode(',', $stored_widget_order) as $stored_widget_id) {

        $stored_widget_id = (int) trim($stored_widget_id);

        // Retired ids, and any id an older save wrote twice, are dropped here
        // rather than carried along and guarded against everywhere later.
        if ((in_array($stored_widget_id, $widget_ids, true)) && (!in_array($stored_widget_id, $widget_order, true))) {

            $widget_order[] = $stored_widget_id;
        }
    }
}

foreach ($widget_ids as $widget_id) {

    if (!in_array($widget_id, $widget_order, true)) {
        $widget_order[] = $widget_id;
    }
}

// ── Markup ──────────────────────────────────────────────────────────────────
//
// A hidden widget is skipped here and nowhere else: it keeps its place in
// $widget_order, and that whole list is what the browser is handed below.
$output_widgets = '';

foreach ($widget_order as $widget_id) {

    $widget = $widget_registry[$widget_id] + $widget_defaults;

    if (!$widget['show']) {
        continue;
    }

    $output_widgets .=
        '<div class="pg-widget' . ($widget['wide'] ? ' pg-widget-wide' : '') . '">'
        . '<div widget-id="' . (int) $widget_id . '" class="card widget">'
        . '<div class="card-header border-0 bg-reset position-relative">'
        . (($widget['icon'] != '') ? '<i class="bi ' . $widget['icon'] . ' me-2"></i>' : '')
        . $widget['title']
        . $widget['action']
        . '</div>'
        . $widget['body']
        . '</div>'
        . '</div>';
}

// ── Widget controls ─────────────────────────────────────────────────────────
//
// Manager and above. Printed into the page script block further down; the Reset
// Widgets button that calls reset_widgets() is built by functions.php, in the
// menu popover, and only on this screen.
//
// Built here rather than beside the other page furniture because it has to
// carry $widget_order, and that is not known until the registry has been read.
if ($user['role'] < 2) {

    $output_widget_controls =
    'var pg_widget_order = ' . encode_json(array_map('strval', $widget_order)) . ';

    // Turn what the grid is showing into the full order to store.
    //
    // The grid only holds the cards this user can see. A card hidden by a
    // setting still owns a position, and that position has to survive a drag --
    // otherwise switching the setting back on drops the card at the end of the
    // dashboard, which is the fault this replaced.
    //
    // Each absent id goes back after the nearest card that used to precede it
    // and is still in the list. Walking the previous order forwards keeps a run
    // of hidden cards together: the second anchors on the first, which has
    // already been put back. An id with nothing surviving before it was at the
    // top and returns to the top.
    function pg_merge_widget_order(visible) {

        var merged = visible.slice();

        for (var i = 0; i < pg_widget_order.length; i++) {

            var id = pg_widget_order[i];

            if (merged.indexOf(id) !== -1) {
                continue;
            }

            var at = 0;

            for (var j = i - 1; j >= 0; j--) {

                var anchor = merged.indexOf(pg_widget_order[j]);

                if (anchor !== -1) {
                    at = anchor + 1;
                    break;
                }
            }

            merged.splice(at, 0, id);
        }

        return merged;
    }

    function pg_save_widget_order(order, message_text, on_success) {

        $.ajax({
            contentType: "application/json",
            url: "api.php",
            data: JSON.stringify({
                action: "update_dashboard_widgets",
                token: software_token,
                message_text: message_text,
                widgets: order
            }),
            type: "POST",
            success: function(response) {
                if ((response.status == "success") && (on_success)) {
                    on_success();
                }
            }
        });
    }

    // The two appearance selects in the Panel entry\'s context menu. Both save
    // through one call and both reload, because the backdrop is decided from
    // the card theme whenever it is left on "auto" and only the server knows
    // how to resolve that.
    function set_dashboard_appearance(field, value) {

        $.ajax({
            contentType: "application/json",
            url: "api.php",
            data: JSON.stringify({
                action: "update_dashboard_appearance",
                token: software_token,
                field: field,
                value: value
            }),
            type: "POST",
            success: function(response) {
                if (response.status == "success") {
                    location.reload();
                }
            }
        });
    }

    // Restores the factory order. It has nothing to do with which widgets are
    // switched on -- nothing on this screen switches a widget on or off any
    // more, the settings do that -- so it no longer has anything to repair,
    // only an arrangement to forget.
    function reset_widgets() {

        pg_save_widget_order(["default"], "restart", function() {
            location.reload();
        });
    }

    $(function() {
        $("#widgets").sortable({
            connectWith: "#widgets",
            cancel: ".no-sortable",
            placeholder: "pg-widget",
            handle: ".card .card-header",
            containment: "parent",
            delay: 300,
            revert: "100",
            cursorAt: { left: 50 },
            animation: 0,
            forcePlaceholderSize: false,
            forceHelperSize: true,
            swapThreshold: 1,
            tolerance: "pointer",
            zIndex: 9999,
            cursor: "move",
            update: function() {

                var visible = [];

                $.each($("#widgets .widget"), function() {
                    visible.push($(this).attr("widget-id"));
                });

                if (visible.length === 0) {
                    return;
                }

                // Kept in step with what was just stored, so a second drag on
                // the same page merges against the current list rather than
                // against the one the page was built with.
                pg_widget_order = pg_merge_widget_order(visible);

                pg_save_widget_order(pg_widget_order, "repositioning");
            }
        });
    });';
}

// Which part of the day it is. The old split called 19:00 night, which is when
// most people are still working; evening now runs to 23:00 and only the small
// hours are night.
$time = (int) date('H');
if ($time < 5) {
    $greeting_part = 'late';
} elseif ($time < 12) {
    $greeting_part = 'morning';
} elseif ($time < 18) {
    $greeting_part = 'afternoon';
} elseif ($time < 23) {
    $greeting_part = 'evening';
} else {
    $greeting_part = 'late';
}

$greeting_icons = array(
    'late'      => 'bi-moon-stars',
    'morning'   => 'bi-sunrise',
    'afternoon' => 'bi-sun',
    'evening'   => 'bi-brightness-alt-high',
);
$greeting_labels = array(
    'late'      => lang('Good night'),
    'morning'   => lang('Good morning'),
    'afternoon' => lang('Good afternoon'),
    'evening'   => lang('Good evening'),
);

// Once in a while, a lighter greeting instead of the plain one. Every time
// would be a gimmick and would wear out inside a week; roughly one refresh in
// four keeps it a small surprise, which is the only thing that makes it worth
// having. The plain greeting is the default and always the fallback.
$greeting_playful = array(
    'late' => array(
        lang('Night knight'),
        lang('Still up'),
        lang('The quiet shift'),
    ),
    'morning' => array(
        lang('First coffee'),
        lang('Bright and early'),
        lang('A fresh page'),
    ),
    'afternoon' => array(
        lang('Peak hours'),
        lang('Halfway there'),
        lang('Full swing'),
    ),
    'evening' => array(
        lang('Golden hour'),
        lang('Winding down'),
        lang('The long light'),
    ),
);

$greeting_label = $greeting_labels[$greeting_part];

if ((mt_rand(1, 4) === 1) && !empty($greeting_playful[$greeting_part])) {

    $greeting_playful_last = (string) ($_SESSION['software']['welcome']['greeting_playful'] ?? '');
    $greeting_playful_choices = array_values(
        array_diff($greeting_playful[$greeting_part], array($greeting_playful_last)));

    if (empty($greeting_playful_choices)) {
        $greeting_playful_choices = $greeting_playful[$greeting_part];
    }

    $greeting_label = $greeting_playful_choices[array_rand($greeting_playful_choices)];
    $_SESSION['software']['welcome']['greeting_playful'] = $greeting_label;
}

$output_greeting_icon = $greeting_icons[$greeting_part];
$output_greeting_message_text = $greeting_label . ', ' . h($_SESSION['sessionusername']) . '.';

// The line under the greeting.
//
// It is a read, not a readout. The widgets below already carry every number on
// this screen; repeating two of them in prose adds nothing anyone would stop
// for. What is missing between a wall of tiles and an operator is the sentence
// somebody who had already looked would say -- how it is going, and what to do
// about it -- so that is what this is:
//
//   1. the read     one sentence that weighs several signals against each other
//                   and says how the day is going
//   2. the advice   one thing worth doing, and a way straight to it
//
// Both halves are drawn from the same signals, gathered once below. Every
// signal sits behind the same gate as the widget that owns it, so the briefing
// never weighs a number the operator is not allowed to see -- which is also why
// each one can be missing, and why nothing here assumes it is there.
//
// The read is chosen from whichever readings are true right now, and rotates
// among them; the advice is not. Advice is the most useful thing to do, and
// picking that at random would make it worth ignoring.
//
// Nothing is invented to sound encouraging. A slow day says the day is young,
// because that is true; it does not say sales are strong, because that is not.
$greeting_now = time();

$sig = array(
    'orders_today'    => null,
    'orders_before'   => null,
    'carts_week'      => null,
    'visitors_now'    => null,
    'health'          => null,
    'perf_today'      => null,
    'perf_before'     => null,
    'hits_today'      => null,
    'hits_before'     => null,
    'comments'        => null,
    'out_of_stock'    => null,
    'new_contacts'    => null,
    'log_today'       => null,
    'version_pending' => null,
    'map_lead'        => null,
    'map_new'         => null,
);

// ── signals ──────────────────────────────────────────────────────────────────

// Comment moderation. Same scope marker as view_comments.php: unpublished.
if ($user['role'] < 3) {
    $sig['comments'] = (int) db_value("SELECT COUNT(*) FROM comments WHERE published = '0'");
}

if ((ECOMMERCE == true) && (($user['role'] < 3) || ($user['manage_ecommerce'] == true))) {

    $sig['out_of_stock'] = (int) db_value("SELECT COUNT(*) FROM products WHERE out_of_stock = '1'");

    // Today against the same stretch of yesterday, which is what makes the
    // number mean something: eleven orders is good news or bad news depending
    // entirely on what eleven usually looks like here.
    //
    // Every window in one pass. order_date is a unix timestamp, so the windows
    // are plain arithmetic, and the WHERE keeps the scan to the week they all
    // fall inside rather than the whole table -- this screen is the first thing
    // that loads after a sign in and it is not the place to read an order
    // history.
    $greeting_day_start  = $greeting_now - 86400;
    $greeting_day_before = $greeting_now - 172800;

    // status != 'incomplete' is not a detail, it is the difference between an
    // order and a basket somebody walked away from. Both live in this table,
    // and without the filter the briefing was calling abandoned carts sales --
    // and saying orders were ahead of yesterday on a site that had taken none
    // for two days, three inches above the widget that says otherwise.
    //
    // The rule is copied from the orders widget on this same screen rather than
    // decided here: it draws the orders list from status != 'incomplete' and
    // the basket list from status = 'incomplete'. Two things on one screen
    // counting the same rows differently is worse than either rule being wrong.
    // The baskets come out of the same pass. They are the other half of this
    // table and they are worth a sentence of their own: somebody who filled a
    // basket and left got further than everybody who never started one.
    //
    // A week for those, two days for the orders, so the scan is a week wide and
    // every sum carries both of its own bounds -- without the lower bound the
    // "yesterday" figure would quietly swallow the five days behind it.
    $greeting_week_start = $greeting_now - 604800;

    $greeting_orders = db_item(
        "SELECT
            SUM(CASE WHEN status != 'incomplete' AND order_date >= " . (int) $greeting_day_start . " THEN 1 ELSE 0 END) AS orders_today,
            SUM(CASE WHEN status != 'incomplete' AND order_date >= " . (int) $greeting_day_before . " AND order_date < " . (int) $greeting_day_start . " THEN 1 ELSE 0 END) AS orders_before,
            SUM(CASE WHEN status =  'incomplete' THEN 1 ELSE 0 END) AS carts_week
         FROM orders
         WHERE order_date >= " . (int) $greeting_week_start);

    $sig['orders_today']  = (int) ($greeting_orders['orders_today'] ?? 0);
    $sig['orders_before'] = (int) ($greeting_orders['orders_before'] ?? 0);
    $sig['carts_week']    = (int) ($greeting_orders['carts_week'] ?? 0);
}

// Where the orders came from, which is the one thing this screen knows and
// never says in words. Same permission as the Sales Map card -- it is the same
// reading of the same rows, and a sentence about what the shop earned belongs
// with the report, not with the order queue.
//
// Two windows in one pass over a month: the week just gone, and the three weeks
// before it. That is what lets the line tell "busiest this week" from "somewhere
// that had never ordered before", and the second is the more interesting of the
// two by a distance.
if ((ECOMMERCE == true) && USER_MANAGE_ECOMMERCE_REPORTS) {

    require_once PG_FUNCTIONS_DIR . '/includes/sales_map.php';

    $greeting_map_week  = $greeting_now - 604800;
    $greeting_map_month = $greeting_now - 2592000;

    $greeting_map_rows = db_items(
        "SELECT
            billing_country,
            billing_state,
            SUM(CASE WHEN order_date >= " . (int) $greeting_map_week . " THEN 1 ELSE 0 END) AS recent,
            SUM(CASE WHEN order_date <  " . (int) $greeting_map_week . " THEN 1 ELSE 0 END) AS earlier
         FROM orders
         WHERE status IN ('complete', 'exported')
         AND order_date >= " . (int) $greeting_map_month . "
         GROUP BY billing_country, billing_state");

    $greeting_map_recent = 0;
    $greeting_map_earlier = 0;
    $greeting_map_lead = null;
    $greeting_map_new = null;

    foreach ($greeting_map_rows as $greeting_map_row) {

        $greeting_map_count = (int) $greeting_map_row['recent'];
        $greeting_map_before = (int) $greeting_map_row['earlier'];

        $greeting_map_recent += $greeting_map_count;
        $greeting_map_earlier += $greeting_map_before;

        if ($greeting_map_count < 1) {
            continue;
        }

        $greeting_map_place = array(
            'name'    => pg_sales_map_label($greeting_map_row['billing_country'], $greeting_map_row['billing_state']),
            'state'   => trim((string) $greeting_map_row['billing_state']),
            'country' => trim((string) $greeting_map_row['billing_country']),
            'count'   => $greeting_map_count,
            // The window this was counted over, carried along so the link under
            // the sentence covers the same days the sentence is about.
            'from'    => $greeting_map_month);

        if (($greeting_map_lead === null) || ($greeting_map_count > $greeting_map_lead['count'])) {
            $greeting_map_lead = $greeting_map_place;
        }

        // First order ever is not what this says -- first in a month is, and
        // that is the honest claim for a month-wide query.
        if (($greeting_map_before === 0)
            && (($greeting_map_new === null) || ($greeting_map_count > $greeting_map_new['count']))
        ) {
            $greeting_map_new = $greeting_map_place;
        }
    }

    // A leader needs somewhere to lead: one order out of two is not a pattern,
    // it is two orders.
    if (($greeting_map_lead !== null) && ($greeting_map_recent >= 3) && ($greeting_map_lead['count'] >= 2)) {
        $sig['map_lead'] = $greeting_map_lead;
    }

    // And "new" only means anything against a month that had orders in it.
    // Without this every place is new on a shop's first week, which is a line
    // that says nothing on the one week somebody is watching closest.
    if (($greeting_map_new !== null) && ($greeting_map_earlier > 0)) {
        $sig['map_new'] = $greeting_map_new;
    }
}

if (($user['role'] < 3) || ($user['manage_visitors'] == true)) {
    $sig['visitors_now'] = (int) db_value(
        "SELECT COUNT(*) FROM visitors WHERE stop_timestamp >= " . (int) ($greeting_now - 1200));
}

if (($user['role'] < 3) || ($user['manage_contacts'] == true)) {
    $sig['new_contacts'] = (int) db_value(
        "SELECT COUNT(*) FROM contacts WHERE timestamp >= " . (int) ($greeting_now - 604800));
}

if ($user['role'] < 3) {
    $sig['log_today'] = (int) db_value(
        "SELECT COUNT(*) FROM log WHERE log_timestamp >= " . (int) ($greeting_now - 86400));
}

// Site health, read straight off the cache the system status widget writes and
// never computed here. The checks behind that score are the expensive thing on
// this screen -- a cold run walks every table -- and a greeting line is not
// worth paying for it. So the briefing weighs health when the widget has
// already been to look, and leaves it out when it has not. After the first load
// of the dashboard it always has.
if ($user['role'] < 3) {

    $greeting_health_file = dirname(__FILE__) . '/data/temp/system_status_cache.json';

    if (is_file($greeting_health_file)) {

        $greeting_health = json_decode(@file_get_contents($greeting_health_file), true);

        // An hour, not the ten minutes the widget refreshes on. A score from
        // forty minutes ago is still true enough to weigh in a sentence, and
        // insisting on a fresh one would only mean saying nothing most loads.
        if (is_array($greeting_health) && isset($greeting_health['score'], $greeting_health['ts'])
            && (($greeting_now - (int) $greeting_health['ts']) < 3600)
        ) {
            $sig['health'] = (int) $greeting_health['score'];
        }
    }
}

// How fast the site is answering, today against yesterday. perf_stats is the
// hourly roll-up rather than the raw log -- one row per page per hour, read
// through idx_area_hour -- so this is a small aggregate over two days and not a
// walk through a million requests.
$greeting_perf_on = (!defined('PERF_MONITOR') || PERF_MONITOR !== false)
    && (!defined('PERF_MONITOR_ENABLED') || PERF_MONITOR_ENABLED);

if (($user['role'] < 3) && $greeting_perf_on) {

    $greeting_perf_start  = $greeting_now - 86400;
    $greeting_perf_before = $greeting_now - 172800;

    $greeting_perf = db_item(
        "SELECT
            SUM(CASE WHEN hour_start >= " . (int) $greeting_perf_start . " THEN hits ELSE 0 END) AS hits_today,
            SUM(CASE WHEN hour_start >= " . (int) $greeting_perf_start . " THEN total_ms ELSE 0 END) AS ms_today,
            SUM(CASE WHEN hour_start <  " . (int) $greeting_perf_start . " THEN hits ELSE 0 END) AS hits_before,
            SUM(CASE WHEN hour_start <  " . (int) $greeting_perf_start . " THEN total_ms ELSE 0 END) AS ms_before
         FROM perf_stats
         WHERE area = 'frontend' AND hour_start >= " . (int) $greeting_perf_before);

    $greeting_hits_today  = (int) ($greeting_perf['hits_today'] ?? 0);
    $greeting_hits_before = (int) ($greeting_perf['hits_before'] ?? 0);

    // Twenty requests is the point where an average stops being one slow page
    // wearing an average as a disguise.
    if ($greeting_hits_today >= 20) {
        $sig['perf_today'] = (int) round(((float) ($greeting_perf['ms_today'] ?? 0)) / $greeting_hits_today);
    }

    if ($greeting_hits_before >= 20) {
        $sig['perf_before'] = (int) round(((float) ($greeting_perf['ms_before'] ?? 0)) / $greeting_hits_before);
    }

    // The request counts come out of the same rows as the timings, so traffic
    // is a signal this screen already paid for. Fifty a day before it is worth
    // comparing -- below that the difference between two days is one crawler.
    if (($greeting_hits_today >= 50) && ($greeting_hits_before >= 50)) {
        $sig['hits_today']  = $greeting_hits_today;
        $sig['hits_before'] = $greeting_hits_before;
    }
}

// Where this install stands against the release notes it shipped with. The
// changelog is already parsed further up this screen for the modal, so this
// costs nothing -- and the comparison is real: VERSION comes from the database,
// the newest changelog heading comes from the files, and the two part company
// exactly when an upgrade has been uploaded but not yet run.
if (($user['role'] < 3) && (count($changelog_sections) > 0) && (VERSION != 'SETUP')) {

    $greeting_newest_version = (string) $changelog_sections[0]['version'];

    $sig['version_pending'] = version_compare($greeting_newest_version, (string) VERSION, '>')
        ? $greeting_newest_version
        : false;
}

// ── directions ───────────────────────────────────────────────────────────────
//
// Where the sentence points. The link goes on the words it is about -- "the 2
// empty products", "the security checks" -- rather than on a chip parked at the
// end: the thing being talked about and the thing you click are then the same
// thing, and the sentence keeps its shape in every language because the anchor
// is one substitution inside it.
$greeting_link = function ($href, $text) {
    return '<a class="pg-brief-link" href="' . h($href) . '">' . h($text) . '</a>';
};

// ── the advice ───────────────────────────────────────────────────────────────
//
// Worked out before the reading, because the reading is chosen to suit it.
//
// Two tiers, and only the top occupied one is drawn from: something actually
// wrong outranks an opportunity, always. Inside a tier it rotates, and each
// entry has more than one way of saying itself.
//
// Strict priority was the first attempt and it was wrong in practice. With two
// products out of stock it produced the identical sentence on every refresh
// until somebody restocked them, and a line that never changes stops being
// read -- which costs more than picking the second most useful thing sometimes.
$has_orders = ($sig['orders_today'] !== null);
$has_perf   = ($sig['perf_today'] !== null) && ($sig['perf_before'] !== null);

$greeting_options = array();

if (!empty($sig['version_pending'])) {
    $greeting_options[] = array('tier' => 1, 'topic' => 'version', 'text' => lang(array(
        'string' => 'Version {var:1} is sitting in the files unrun, so the release notes are the place to start.',
        'vars' => (string) $sig['version_pending'])));
}

if (($sig['health'] !== null) && ($sig['health'] < 70)) {

    $greeting_checks_link = $greeting_link('settings_security.php#pgset-session', lang('the security checks'));

    // Neither of these points back at a score, because the reading above is
    // about something else as often as not, and a sentence that refers to
    // something nobody said is a sentence you have to work out.
    $greeting_options[] = array('tier' => 1, 'topic' => 'health', 'text' => lang(array(
        'string' => 'System health is usually pulled down by {var:1} first, so I would start there.',
        'vars' => $greeting_checks_link)));
    $greeting_options[] = array('tier' => 1, 'topic' => 'health', 'text' => lang(array(
        'string' => 'The shortest way back up that score runs through {var:1}.',
        'vars' => $greeting_checks_link)));
}

if (((int) $sig['comments']) > 0) {

    $greeting_comments_link = $greeting_link('view_comments.php', lang(array(
        'string' => '{var:1} waiting comments', 'vars' => pg_format_number($sig['comments'], 0))));

    $greeting_options[] = array('tier' => 1, 'topic' => 'comments', 'text' => lang(array(
        'string' => 'Approving the {var:1} puts them on the pages they belong to.',
        'vars' => $greeting_comments_link)));
    $greeting_options[] = array('tier' => 1, 'topic' => 'comments', 'text' => lang(array(
        'string' => 'There are {var:1}, and publishing them keeps those pages alive.',
        'vars' => $greeting_comments_link)));
}

if (((int) $sig['out_of_stock']) > 0) {

    // The filtered list, not the catalogue. Advice that drops you in front of
    // four thousand products has made you do the finding yourself.
    $greeting_stock_link = $greeting_link('view_products.php?filter=out_of_stock_products', lang(array(
        'string' => '{var:1} empty products', 'vars' => pg_format_number($sig['out_of_stock'], 0))));

    $greeting_options[] = array('tier' => 1, 'topic' => 'stock', 'text' => lang(array(
        'string' => 'Restocking the {var:1} would keep those pages selling.',
        'vars' => $greeting_stock_link)));
    $greeting_options[] = array('tier' => 1, 'topic' => 'stock', 'text' => lang(array(
        'string' => 'There are {var:1} on the site, and each one is a page that cannot sell.',
        'vars' => $greeting_stock_link)));
}

if ($has_perf && ($sig['perf_today'] > $sig['perf_before']) && ($sig['perf_today'] > 500)) {

    $greeting_perf_link = $greeting_link('view_performance_log.php', lang('the performance log'));

    $greeting_options[] = array('tier' => 2, 'topic' => 'perf', 'text' => lang(array(
        'string' => 'Pages are slower than yesterday, and {var:1} will say which ones.',
        'vars' => $greeting_perf_link)));
    $greeting_options[] = array('tier' => 2, 'topic' => 'perf', 'text' => lang(array(
        'string' => 'That slowdown has been going since yesterday; {var:1} has the page names.',
        'vars' => $greeting_perf_link)));
}

if (((int) $sig['carts_week']) > 0) {

    // reset=true first, because this screen keeps its filters in the session
    // and a month-old date range left behind from the last visit would hide
    // the very rows the sentence just promised.
    $greeting_carts_link = $greeting_link('view_orders.php?reset=true&status=incomplete', lang(array(
        'string' => '{var:1} baskets', 'vars' => pg_format_number($sig['carts_week'], 0))));

    $greeting_options[] = array('tier' => 2, 'topic' => 'carts', 'text' => lang(array(
        'string' => 'There are {var:1} left half full this week, and it is worth a look at who walked away.',
        'vars' => $greeting_carts_link)));
    $greeting_options[] = array('tier' => 2, 'topic' => 'carts', 'text' => lang(array(
        'string' => 'Someone filled {var:1} this week and left them; they are all still on the list.',
        'vars' => $greeting_carts_link)));
}

if (((int) $sig['new_contacts']) > 0) {

    // Not a problem to fix -- an opening to take. Most days the dashboard has
    // nothing wrong with it, and "nothing is wrong" is not the same as "there
    // is nothing worth doing".
    $greeting_people_link = $greeting_link('view_contacts.php', lang(array(
        'string' => '{var:1} people', 'vars' => pg_format_number($sig['new_contacts'], 0))));

    $greeting_options[] = array('tier' => 2, 'topic' => 'contacts', 'text' => lang(array(
        'string' => 'The {var:1} who joined this week have not heard from you yet.',
        'vars' => $greeting_people_link)));
    $greeting_options[] = array('tier' => 2, 'topic' => 'contacts', 'text' => lang(array(
        'string' => '{var:1} joined this week, and a first hello tends to land well.',
        'vars' => $greeting_people_link)));
}

// A place that had never ordered before. Not a problem to fix and not a number
// to watch -- a door that opened, which is the kind of thing the sales map is
// on the screen to show and a tile full of totals never will.
//
// Only offered with the link, and the link asks for the other commerce
// permission: view_orders.php wants `manage_ecommerce`, while the figure itself
// came in under `manage_ecommerce_reports`. Without somewhere to send the
// reader this is a reading, not advice, and it is already in the pool below.
if (($sig['map_new'] !== null) && (defined('USER_MANAGE_ECOMMERCE') && USER_MANAGE_ECOMMERCE)) {

    $greeting_map_link = $greeting_link(
        pg_sales_map_orders_url('', $sig['map_new']['state'], $sig['map_new']['from'], 0),
        $sig['map_new']['name']);

    $greeting_options[] = array('tier' => 2, 'topic' => 'map', 'text' => lang(array(
        'string' => '{var:1} ordered for the first time this month, and that order is worth a look.',
        'vars' => $greeting_map_link)));
    $greeting_options[] = array('tier' => 2, 'topic' => 'map', 'text' => lang(array(
        'string' => 'New on the map this month: {var:1}.',
        'vars' => $greeting_map_link)));
}

// Top occupied tier only.
$greeting_tier = 0;

foreach ($greeting_options as $greeting_option) {
    if (($greeting_tier === 0) || ($greeting_option['tier'] < $greeting_tier)) {
        $greeting_tier = $greeting_option['tier'];
    }
}

$greeting_advice_pool = array();

foreach ($greeting_options as $greeting_option) {
    if ($greeting_option['tier'] === $greeting_tier) {
        $greeting_advice_pool[] = $greeting_option['text'];
    }
}

// ── what the second half is, this time ───────────────────────────────────────
//
// Not always advice. Something is nearly always open on a working site, and a
// line that turns every single one of them into a suggestion is nagging, not
// helping -- you stop reading it, which costs the one time it mattered.
//
// So the second half is drawn by kind first: usually the advice when there is
// something genuinely wrong, less often when it is only an opportunity, and the
// rest of the time a small thing about the software or nothing more than a
// civil word. A suggestion you can also just not get is a suggestion; one that
// arrives every time is an instruction.
$greeting_tips = array_map('h', array(
    lang('Search opens on Ctrl+K from anywhere, if you would rather not reach for the mouse.'),
    lang('Did you know the widgets can be dragged around by their headers?'),
    lang('Right click the Panel button in the menu and you will find the widget theme settings.'),
    lang('If you prefer the light theme, it is in the user menu at the top right.'),
));

// What the software gained lately, as opposed to how to work it.
//
// Three attempts to get this right, and the first two were the same mistake in
// different clothes.
//
// Scraping the release notes came first and read like a specification, because
// that is what it is: "the Comment Moderation card: count of comments awaiting
// approval and the newest five" is a line for somebody upgrading, not a
// sentence anybody says out loud.
//
// Then the notes were rewritten by hand but still announced -- "A recent update
// brought this: offers are put together in one place now" -- which is a press
// release with a colon in it. A label and a colon is the shape of a bulletin;
// nobody talks like that either.
//
// So there is no label and no template. Each line is a whole sentence somebody
// might say while you were both looking at the screen, and the recency lives
// inside it as an ordinary word -- "now", "these days", "by the way" -- rather
// than in a heading. No version number anywhere: a line here outlives the
// release that brought it by a dozen versions, and the changelog is one click
// away for anyone who wants the full account.
// Most of them end with an offer to go and look, and the offer is the link --
// the whole question, not a word inside it. It is what somebody would actually
// say next, and it means the sentence is never bent around an anchor: a
// question reads the same in every language, which a phrase spliced into the
// middle of one does not.
//
// It uses the same anchor as everything else in this line. A second link style
// was written for it first and that was the mistake: two kinds of link in one
// sentence makes the reader work out whether the difference means anything,
// and here it does not.
$greeting_ask = function ($href, $text) use ($greeting_link) {
    return ' ' . $greeting_link($href, $text);
};

$greeting_features = array(
    h(lang('By the way, you can build a variant product in one go now.'))
        . $greeting_ask('add_product.php', lang('Want to add one and see?')),

    h(lang('The file manager was rebuilt, by the way. Folders, uploads and previews all on one screen.'))
        . $greeting_ask('view_folders.php', lang('Shall we take a look?')),

    h(lang('You can put an offer together in one place these days, rule and outcome on the same screen.'))
        . $greeting_ask('add_offer.php', lang('Feel like making one now?')),

    h(lang('You can cap how many devices one account stays signed in from, did you know?'))
        . $greeting_ask('settings_security.php#pgset-session', lang('Want to turn it on?')),

    h(lang('Product groups have a management screen of their own these days.'))
        . $greeting_ask('view_product_groups.php', lang('Care to have a look?')),

    h(lang('Your pages and products carry an SEO score now, out of 100.'))
        . $greeting_ask('view_pages.php', lang('Want to see how yours are doing?')),

    // No offer on this one: the widget it is about is further down this very
    // screen, and pointing somebody somewhere they already are is not help.
    h(lang('You can start a deep database check from the system status widget whenever you like.')),

    h(lang('The comments waiting for approval turn up on the dashboard now, newest first.'))
        . $greeting_ask('view_comments.php', lang('Want to go through them?')),
);

// Manager and above, which is what settings_pane.php asks for
// (validate_area_access($user, 'manager'), so role <= 2). Pointing a
// contributor at a screen that will turn them away is worse than saying nothing.
//
// The address comes from pg_settings_link() rather than being written out: the
// category screens are what it knows about, and the dialog recognises its own
// addresses and opens over whatever screen the reader is on.
if ($user['role'] <= 2) {

    $greeting_features[] =
        h(lang('Settings are split into sections these days, and they open over whatever screen you are on.'))
        . $greeting_ask(pg_settings_link(), lang('Want to open them?'));
}

// Two kinds of closing word, and the difference matters. The first three are
// claims about the state of the site and are only true when nothing is open --
// "nothing needs you right now" printed under a line that just said two
// products are out of stock is the briefing contradicting itself in the space
// of one breath. The rest are civil words, true on any day.
$greeting_closers = array_map('h', array(
    lang('Take it easy.'),
    lang('Enjoy the work.'),
    lang('The panel is all yours.'),
));

if (empty($greeting_advice_pool)) {
    $greeting_closers = array_merge($greeting_closers, array_map('h', array(
        lang('Nothing needs you right now.'),
        lang('Everything here looks in good order.'),
        lang('All quiet on this side, so the day is yours.'),
    )));
}

// Something about the web itself, which is neither advice nor a manual.
//
// It has no use whatsoever, and that is the point: a screen somebody opens
// every morning for two years can afford to be interesting once in a while, and
// a small true thing is what makes a line worth reading when there is nothing
// to report. Nothing here is about this site -- it is about the web the site
// lives on.
//
// The openings are deliberately all different. "Did you know" on all eight
// would turn a surprise into a format inside a week, which is the same failure
// as saying the same sentence every refresh, only slower.
$greeting_trivia = array_map('h', array(
    lang('The world\'s first webcam was pointed at a coffee pot, so nobody in the Cambridge lab would walk down for nothing.'),
    lang('Forty-four of every hundred people clicked the very first banner ad. No advert has come near that since.'),
    lang('The @ in an email address was chosen because it was the one symbol on the keyboard that turned up in nobody\'s name. That was the whole reason.'),
    lang('Did you know the www at the front of an address means nothing technically? It is a habit left over from the early days.'),
    lang('The only tag you are actually required to write in a valid HTML page is the title. Even the html tag is optional, hard as that is to believe.'),
    lang('The little icon on a browser tab came from no standard: in 1999 a browser started asking every site for favicon.ico, never stopped asking, and it became a convention.'),
    lang('The web forgets faster than you would think: roughly 38% of the pages that existed in 2013 were gone a decade later.'),
    lang('The ping command is named after submarine sonar. Its author had the sound going out and coming back in mind.'),
    lang('The first message ever sent between two computers was two letters long. They typed LOGIN, the machine crashed after LO, and that was how the internet opened.'),
    lang('Tim Berners-Lee has said he is sorry about the two slashes in http://. They were never necessary; he just wrote them that way and the world copied him.'),
    lang('There is a real status code reserved for a teapot refusing to make coffee, 418, and some servers still answer with it.'),
    lang('The man who sent the first email cannot remember what it said. Something like QWERTYUIOP, he thinks.'),
    lang('robots.txt exists because one crawler brought a server to its knees in 1994. The fix was a plain text file, and it is still the same file today.'),
    lang('The first web page ever published is still online at CERN, and it is nothing but text and links.'),
));

// Once in a blue moon, none of the above. Nothing to report and no lesson to
// teach -- just the thing a person says when they have nothing to say. One load
// in twenty, because the whole worth of it is that it is not expected, and a
// surprise on a schedule is a feature.
$greeting_rare = array_map('h', array(
    lang('Nothing comes to mind to report, but I hope you are keeping well. 🙂'),
    lang('Let the numbers rest a minute. How are you doing?'),
    lang('From where I am sitting everything looks fine. ☕'),
    lang('The panel is working, you are working. Good partnership. 🤝'),
    lang('Today I will just wish you an easy one. ✨'),
));

// The weighting, written as a bag because five entries in a bag is easier to
// read and to change than a chain of thresholds.
if (empty($greeting_advice_pool)) {
    $greeting_kinds = array('tip', 'feature', 'trivia', 'closer', 'closer');
} elseif ($greeting_tier === 1) {
    $greeting_kinds = array('advice', 'advice', 'advice', 'tip', 'feature', 'trivia', 'closer');
} else {
    $greeting_kinds = array('advice', 'advice', 'tip', 'feature', 'trivia', 'closer', 'closer');
}


$greeting_kind = $greeting_kinds[array_rand($greeting_kinds)];

if (mt_rand(1, 20) === 1) {
    $greeting_kind = 'rare';
}

$greeting_advice = '';
$greeting_advice_topic = '';

if ($greeting_kind === 'advice') {

    $greeting_last_advice = (string) ($_SESSION['software']['welcome']['greeting_advice'] ?? '');
    $greeting_advice_choices = array_values(array_diff($greeting_advice_pool, array($greeting_last_advice)));

    if (empty($greeting_advice_choices)) {
        $greeting_advice_choices = $greeting_advice_pool;
    }

    $greeting_advice = $greeting_advice_choices[array_rand($greeting_advice_choices)];
    $_SESSION['software']['welcome']['greeting_advice'] = $greeting_advice;

    // The reading is kept off whatever the advice is about, so it has to know.
    foreach ($greeting_options as $greeting_option) {
        if ($greeting_option['text'] === $greeting_advice) {
            $greeting_advice_topic = $greeting_option['topic'];
            break;
        }
    }

} else {

    if ($greeting_kind === 'rare') {
        $greeting_tail_pool = $greeting_rare;
    } elseif ($greeting_kind === 'trivia') {
        $greeting_tail_pool = $greeting_trivia;
    } elseif ($greeting_kind === 'feature') {
        $greeting_tail_pool = $greeting_features;
    } elseif ($greeting_kind === 'tip') {
        $greeting_tail_pool = $greeting_tips;
    } else {
        $greeting_tail_pool = $greeting_closers;
    }

    $greeting_last_tail = (string) ($_SESSION['software']['welcome']['greeting_tail'] ?? '');
    $greeting_tail_choices = array_values(array_diff($greeting_tail_pool, array($greeting_last_tail)));

    if (empty($greeting_tail_choices)) {
        $greeting_tail_choices = $greeting_tail_pool;
    }

    $greeting_tail = $greeting_tail_choices[array_rand($greeting_tail_choices)];
    $_SESSION['software']['welcome']['greeting_tail'] = $greeting_tail;

    // Already escaped where it was written, and the features carry an anchor
    // this file built itself, so it goes out as it is.
    $greeting_advice = $greeting_tail;
}

// ── the read ─────────────────────────────────────────────────────────────────
//
// Two kinds, and the first kind wins whenever there is one.
//
// A reading that holds two signals against each other says something no tile on
// this screen can: a tile can report traffic and another can report orders, but
// only a sentence can say that one moved and the other did not. That is the
// whole reason this line exists, so where such a reading is available it is the
// only kind offered.
//
// The plain readings are the floor -- one signal, still true, still worth a
// glance -- for the hours when nothing is moving against anything.
$greeting_smart = array();
$greeting_plain = array();

$has_traffic = ($sig['hits_today'] !== null) && ($sig['hits_before'] !== null);

// Fifteen percent, because two days of a real site are never equal and calling
// a four percent drift "up" would be the sort of confident nonsense that makes
// a line like this untrustworthy.
$traffic_up   = $has_traffic && ($sig['hits_today'] > ($sig['hits_before'] * 1.15));
$traffic_down = $has_traffic && ($sig['hits_today'] < ($sig['hits_before'] * 0.85));
$orders_up    = $has_orders && ($sig['orders_today'] > $sig['orders_before']);
$orders_flat  = $has_orders && ($sig['orders_today'] <= $sig['orders_before']);
$perf_slower  = $has_perf && ($sig['perf_today'] > ($sig['perf_before'] * 1.15));

if ($traffic_up && $orders_up) {
    $greeting_smart[] = array('topic' => 'orders',
        'text' => lang('Traffic is up on yesterday and orders have moved up with it.'));
}

if ($traffic_up && $orders_flat) {
    $greeting_smart[] = array('topic' => 'orders',
        'text' => lang('More visits than yesterday, and the same orders as yesterday.'));
}

if ($traffic_down && $orders_up) {
    $greeting_smart[] = array('topic' => 'orders',
        'text' => lang('Fewer visits than yesterday and more orders, so the people arriving are the right ones.'));
}

if ($traffic_up && $perf_slower) {
    $greeting_smart[] = array('topic' => 'perf',
        'text' => lang('Traffic is up and the pages have slowed with it, which is usually one story rather than two.'));
}

if ($traffic_up && !$perf_slower && ($sig['perf_today'] !== null)) {
    $greeting_smart[] = array('topic' => 'perf', 'text' => lang(array(
        'string' => 'Traffic is up on yesterday and the pages are holding at {var:1} ms.',
        'vars' => pg_format_number($sig['perf_today'], 0))));
}

if ($orders_up && (((int) $sig['out_of_stock']) > 0)) {
    $greeting_smart[] = array('topic' => 'stock', 'text' => lang(array(
        'string' => 'Orders are running ahead of yesterday while {var:1} products sit out of stock.',
        'vars' => pg_format_number($sig['out_of_stock'], 0))));
}

// Nobody bought, but somebody nearly did. That is a different day from one
// where nothing happened at all, and no tile on this screen says it.
if ($has_orders && ($sig['orders_today'] === 0) && (((int) $sig['carts_week']) > 0)) {
    $greeting_smart[] = array('topic' => 'carts', 'text' => lang(array(
        'string' => 'No orders today, though {var:1} baskets were filled and left this week.',
        'vars' => pg_format_number($sig['carts_week'], 0))));
}

if ((((int) $sig['new_contacts']) > 0) && $has_orders && ($sig['orders_today'] === 0)) {
    $greeting_smart[] = array('topic' => 'contacts',
        'text' => lang('New people are still arriving, though none of them has ordered today.'));
}

if (($sig['health'] !== null) && ($sig['health'] < 70) && (((int) $sig['log_today']) > 0)) {
    $greeting_smart[] = array('topic' => 'health', 'text' => lang(array(
        'string' => 'There has been activity today and system health is at {var:1}%, which is worth a look together.',
        'vars' => $sig['health'])));
}

// Somewhere ordered that had not ordered in the month before it. This belongs
// with the combinational readings rather than with the plain ones because that
// is what it is: this week held against the three weeks behind it.
if ($sig['map_new'] !== null) {
    $greeting_smart[] = array('topic' => 'map', 'text' => lang(array(
        'string' => '{var:1} ordered for the first time this month.',
        'vars' => $sig['map_new']['name'])));
}

// ── the floor ──
if ($has_orders && ($sig['orders_today'] > $sig['orders_before'])) {
    $greeting_plain[] = array('topic' => 'orders',
        'text' => lang('Orders are running ahead of where they were yesterday.'));
} elseif ($has_orders && ($sig['orders_today'] === 0) && ($sig['orders_before'] > 0)) {
    $greeting_plain[] = array('topic' => 'orders', 'text' => (((int) $sig['visitors_now']) > 0)
        ? lang('No orders yet today, though people are on the site as we speak.')
        : lang('No orders yet today, and there are hours left in it.'));
}

if ($has_perf && ($sig['perf_today'] < $sig['perf_before'])) {
    $greeting_plain[] = array('topic' => 'perf', 'text' => lang(array(
        'string' => 'The site is answering in {var:1} ms, quicker than it managed yesterday.',
        'vars' => pg_format_number($sig['perf_today'], 0))));
} elseif (($sig['perf_today'] !== null) && ($sig['perf_today'] <= 200)) {
    $greeting_plain[] = array('topic' => 'perf', 'text' => lang(array(
        'string' => 'Pages are coming back in {var:1} ms, which is comfortable.',
        'vars' => pg_format_number($sig['perf_today'], 0))));
} elseif ($sig['perf_today'] !== null) {
    $greeting_plain[] = array('topic' => 'perf', 'text' => lang(array(
        'string' => 'Pages are averaging {var:1} ms today.',
        'vars' => pg_format_number($sig['perf_today'], 0))));
}

if (($sig['health'] !== null) && ($sig['health'] >= 90)) {
    $greeting_plain[] = array('topic' => 'health',
        'text' => lang('System health is high and the infrastructure side is clear.'));
} elseif (($sig['health'] !== null) && ($sig['health'] >= 70)) {
    $greeting_plain[] = array('topic' => 'health', 'text' => lang(array(
        'string' => 'System health is sitting at a reasonable {var:1}%.',
        'vars' => $sig['health'])));
} elseif ($sig['health'] !== null) {
    $greeting_plain[] = array('topic' => 'health', 'text' => lang(array(
        'string' => 'Everything is running, though system health has slipped to {var:1}%.',
        'vars' => $sig['health'])));
}

if (((int) $sig['visitors_now']) > 0) {
    $greeting_plain[] = array('topic' => 'visitors', 'text' => lang(array(
        'string' => '{var:1} people are looking around the site right now.',
        'vars' => pg_format_number($sig['visitors_now'], 0))));
}

// Where the week's orders are coming from. The place is named rather than
// linked because this half of the line is escaped whole -- the advice half is
// the one that carries an anchor.
if ($sig['map_lead'] !== null) {

    $greeting_plain[] = array('topic' => 'map', 'text' => lang(array(
        'string' => '{var:1} leads the week with {var:2} order{suffix:2}.',
        'vars'   => array($sig['map_lead']['name'], pg_format_number($sig['map_lead']['count'], 0)),
        'suffix' => array('', ($sig['map_lead']['count'] == 1) ? '' : 's'))));

    $greeting_plain[] = array('topic' => 'map', 'text' => lang(array(
        'string' => 'The busiest place on the map this week: {var:1}.',
        'vars'   => $sig['map_lead']['name'])));
}

if (((int) $sig['new_contacts']) > 0) {
    $greeting_plain[] = array('topic' => 'contacts', 'text' => lang(array(
        'string' => '{var:1} new people joined your contacts this week.',
        'vars' => pg_format_number($sig['new_contacts'], 0))));
}

// A reading that holds two signals against each other is worth more than one
// that reports a single number -- but preferring those alone was a mistake and
// a bad one. On a settled site there is often exactly one combination to be
// found, and offering only that meant the identical sentence on every single
// refresh: ten out of ten, which is not a briefing, it is nagging.
//
// So both kinds go in the pool always, and the combinational ones simply count
// twice. They still come up about half the time, and the line always has
// somewhere else to go.
$greeting_reads = $greeting_plain;

foreach ($greeting_smart as $greeting_smart_row) {
    $greeting_reads[] = $greeting_smart_row;
    $greeting_reads[] = $greeting_smart_row;
}

// Off the advice's subject where there is anywhere else to go, and back onto it
// where there is not: a repetitive line still beats an empty one.
$greeting_read_pool = array();

foreach ($greeting_reads as $greeting_read_row) {
    if ($greeting_read_row['topic'] !== $greeting_advice_topic) {
        $greeting_read_pool[] = $greeting_read_row['text'];
    }
}

if (empty($greeting_read_pool)) {
    foreach ($greeting_reads as $greeting_read_row) {
        $greeting_read_pool[] = $greeting_read_row['text'];
    }
}

// A floor under the floor, for an install where every gate is shut.
if (empty($greeting_read_pool)) {
    $greeting_read_pool[] = lang('Everything here looks in good order.');
}

// Two back, not one. Excluding only the last leaves an A B A B flap when there
// are two candidates, which reads as repetition just as much as A A A does.
// With a memory two deep, nothing comes round again inside three refreshes
// wherever there are three things to say.
$greeting_seen = $_SESSION['software']['welcome']['greeting_seen'] ?? array();

if (!is_array($greeting_seen)) {
    $greeting_seen = array();
}

$greeting_read_choices = array_values(array_diff($greeting_read_pool, $greeting_seen));

// Give the memory back one entry at a time rather than forgetting all of it:
// with exactly two things to say, the older one is still the better answer.
if (empty($greeting_read_choices) && (count($greeting_seen) > 1)) {
    $greeting_read_choices = array_values(
        array_diff($greeting_read_pool, array_slice($greeting_seen, -1)));
}

if (empty($greeting_read_choices)) {
    $greeting_read_choices = $greeting_read_pool;
}

$greeting_read = $greeting_read_choices[array_rand($greeting_read_choices)];

$greeting_seen[] = $greeting_read;
$_SESSION['software']['welcome']['greeting_seen'] = array_slice($greeting_seen, -2);

// The reading is escaped; the advice carries one anchor this file built itself
// and is already safe, so it goes out as it is.
$output_greeting_nudge =
    '<span class="pg-brief-line">' . h($greeting_read) . ' ' . $greeting_advice . '</span>';

// The system status bar that used to sit above the widget grid is gone: the
// same checks are widget 2 now, so keeping the bar would have printed the same
// score twice on one screen. The popover binding stays, because the widget's
// tiles use it and it has to exist before the widget's markup arrives.
$output_status_popover_script = '
<script>
    var statuspopover = new bootstrap.Popover(document.body, {
      selector: \'.status-popover\',
      trigger: "hover"
    });
</script>';
print
pg_page_shell(
    array(
        'title'=> lang('Welcome'),
        'extra classes'=>'setting welcome',
        'icon'=>'welcome',
        'heading'=>lang('Welcome'),
        'heading_description' => lang('Your site at a glance: visitors, orders and health'),
    )
) . '<main id="content" class="container-fluid" data-widget-theme="' . h($widget_theme) . '" data-panel-backdrop="' . h($panel_backdrop) . '">' .
$output_header_includes .
'<meta http-equiv="Refresh" content="3600"> <!--Refresh page every (3600 sec = 60 min) to stop error invalid token. Will be measured later. -->
<script src="assets/lib/chartjs/chart.umd.min.js"></script>
    <div class="pg-clockbar">
        <div class="pg-brief">
            <!-- One mark, not two. The hour already had an icon in front of the
                 greeting; moving it into the chip makes it the speaker rather
                 than a decoration, and saves the line a second glyph. -->
            <span class="pg-brief-mark" aria-hidden="true"><i class="bi ' . h($output_greeting_icon) . '"></i></span>
            <p class="pg-brief-text">
                <span class="pg-brief-greet" data-bs-toggle="tooltip" title="' . lang('Role') . ': ' . h($role) . '">' . $output_greeting_message_text . '</span>
                ' . $output_greeting_nudge . '
            </p>
        </div>
        <span class="pg-clockbar-now">
            <span id="welcome_time_clock" class="pg-clockbar-time">' . get_absolute_time(array(
                'timestamp' => time() ,
                'type' => 'time',
                'timezone_type' => 'site'
            )) . '</span>
            <span class="pg-clockbar-date">' . get_absolute_time(array(
                'timestamp' => time() ,
                'type' => 'date',
                'size' => 'long',
                'timezone_type' => 'site'
            )) . '</span>
        </span>
    </div>
    <div class="row">
        <div class="col-12">
            <div class="row">
                <div class="col-12">
                    ' . $liveform->output_errors() . '
                    ' . $liveform->get_warnings() . '
                    ' . $liveform->output_notices() . '
                </div>
            </div>
            ' . $output_changelog_modal . '
            ' . $output_status_popover_script . '
            
            <div id="widgets" class="pg-widgets mb-5">
                ' . $output_widgets . '
            </div>
        </div>
    </div> 
<script>
    // this is required for chart js animations.
    let delayed;
    ' . $output_widget_controls . '
    var widget_refresh_time = "' . $widget_refresh_time . '";
    $( document ).ready(function() {
        get_widgets_data();
        window.setInterval(function(){
            get_widgets_data();
          }, widget_refresh_time*1000);
    });
    let $get_widgets_data_firstrun = true;

    // Charts have to be destroyed before the markup holding them is replaced.
    // Chart.js keeps every chart it builds in a registry of its own, keyed by
    // the canvas; taking the canvas out of the document does not take the
    // chart out of that registry. The instance keeps its resize observer, its
    // data and the whole widget response alive, and every window resize walks
    // the full list. The dashboard rebuilds its widgets once a minute, so a
    // screen left open for a working day ends up with thousands of charts
    // drawing into canvases nobody can see.
    function destroy_widget_charts($scope){
        if (typeof Chart === "undefined" || typeof Chart.getChart !== "function") {
            return;
        }
        $scope.find("canvas").each(function(){
            var chart = Chart.getChart(this);
            if (chart) {
                chart.destroy();
            }
        });
    }

    function update_clock(){
        $.ajax({
            contentType: "application/json",
            url: "api.php",
            data: JSON.stringify({
                action: "get_widget_data",
                token: software_token,
                widget_id: "clock"
            }),
            type: "POST",
            success: function(response) {
                if(response.status == "success"){
                    $("#welcome_time_clock").html("");
                    $("#welcome_time_clock").html(response.data);
                }
            }
        });
    }

function get_widgets_data(){
        update_clock();
        if($get_widgets_data_firstrun == true){
            // load system status asynchronously to avoid blocking page render
            if($("#system_status_widget").length > 0){
                $.ajax({
                    contentType: "application/json",
                    url: "api.php",
                    data: JSON.stringify({
                        action: "get_widget_data",
                        token: software_token,
                        widget_id: "system_status"
                    }),
                    type: "POST",
                    success: function(response) {
                        if(response.status == "success"){
                            var $status_body = $("#system_status_widget .card-body");
                            destroy_widget_charts($status_body);
                            $status_body.html(response.data);
                        }
                    }
                });
            }
            // this is first run we update all widgets
            if($(".card[widget-id]").length > 0){
                $(".card[widget-id]").each(function(){
                    var widget_id = $(this).attr("widget-id");
                    $.ajax({
                        contentType: "application/json",
                        url: "api.php",
                        data: JSON.stringify({
                            action: "get_widget_data",
                            token: software_token,
                            widget_id: widget_id
                        }),
                        type: "POST",
                        success: function(response) {
                            if(response.status == "success"){
                                var $widget = $(".card[widget-id="+ widget_id +"]");
                                destroy_widget_charts($widget);
                                $widget.find(".card-body-placeholder,.card-body,.card-footer").remove();
                                $widget.append(response.data);
                            }
                        }
                    });
                });
            }
            $get_widgets_data_firstrun = false;
        }else{
            // Not first run we dont need update all widgets
            if($(".card[widget-id]").length > 0){
                $(".card[widget-id]").each(function(){
                    var widget_id = $(this).attr("widget-id");
                    //ids of refresh required widgets
                    if (["4","6","7","8","10","11","12","13","16","17","19","25","26"].includes(widget_id)) {
                        $.ajax({
                            contentType: "application/json",
                            url: "api.php",
                            data: JSON.stringify({
                                action: "get_widget_data",
                                token: software_token,
                                widget_id: widget_id
                            }),
                            type: "POST",
                            success: function(response) {
                                if(response.status == "success"){
                                    var $widget = $(".card[widget-id="+ widget_id +"]");
                                    destroy_widget_charts($widget);
                                    $widget.find(".card-body-placeholder,.card-body,.card-footer").remove();
                                    $widget.append(response.data);
                                }
                            }
                        });
                    }
                });
            }
        }
    };
</script>
</main>' . 
output_footer();
$liveform->remove_form();
