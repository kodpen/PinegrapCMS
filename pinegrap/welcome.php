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

// How often the moving widgets are asked for again, in seconds: 60 unless this
// sign-in chose otherwise, on the Dashboard card of Site Settings > Appearance
// or with welcome.php?refresh=N. Kept in the session, never in a column. A
// shorter cycle costs the server and the browser more, so it is held to 10
// seconds at least, and to a day at most (pg_dashboard_refresh_seconds()).
if (isset($_REQUEST['refresh']) && is_string($_REQUEST['refresh']) && is_numeric($_REQUEST['refresh'])) {
    $_SESSION['software']['welcome']['widget']['refresh'] = pg_dashboard_refresh_seconds($_REQUEST['refresh']);
}

$widget_refresh_time = pg_dashboard_refresh_seconds($_SESSION['software']['welcome']['widget']['refresh'] ?? '', 60);

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
// Two site-wide settings on the dashboard row, chosen by a designer or an
// administrator on the Dashboard card of Site Settings > Appearance: which of
// the four treatments the cards wear, and what sits behind them. They are
// written onto #content further down, where CSS can reach them, with "auto"
// already resolved to a backdrop (pg_dashboard_appearance()). $dashboard is a
// SELECT *, so an installation that has not run 2026.4.4 has neither key and
// keeps the flat cards it has always had.
$dashboard_appearance = pg_dashboard_appearance($dashboard);

$widget_theme = $dashboard_appearance['widget_theme'];
$panel_backdrop = $dashboard_appearance['backdrop'];

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
        <pre class="mb-3 fg-secondary" style="white-space:pre; overflow-x:auto; font-size:.72rem; line-height:1.35; font-family:ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;">' . h($changelog_preamble['banner']) . '</pre>';
    }

    if (count($changelog_preamble['legend']) > 0) {

        $output_changelog_legend = '';

        foreach ($changelog_preamble['legend'] as $changelog_legend_row) {
            $output_changelog_legend .= '
            <span class="d-inline-flex align-items-center gap-1 me-5">
                <span class="badge ' . pg_changelog_tag_class($changelog_legend_row['tag']) . '">' . h($changelog_legend_row['tag']) . '</span>
                <span class="small fg-secondary">' . h($changelog_legend_row['text']) . '</span>
            </span>';
        }

        $output_changelog_head .= '
        <div class="d-flex flex-wrap row-gap-1 pb-5 mb-5 border-bottom">' . $output_changelog_legend . '</div>';
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
            <div class="small fw-bold text-uppercase fg-secondary pt-5 pb-1">' . h($changelog_group) . '</div>';
                }
            }

            $output_changelog_tags = '';

            foreach ($changelog_entry['tags'] as $changelog_tag) {
                $output_changelog_tags .=
                    '<span class="badge ' . pg_changelog_tag_class($changelog_tag) . '">'
                    . h($changelog_tag) . '</span>';
            }

            $output_changelog_entries .= '
            <div class="d-flex gap-3 py-3 border-bottom border-1">
                <div class="d-flex flex-column gap-1 flex-shrink-0" style="width:6.5rem">' . $output_changelog_tags . '</div>
                <div class="small">' . h($changelog_entry['text']) . '</div>
            </div>';
        }

        // Only the newest section is open. A reader who came for "what changed"
        // wants this version; the older ones are history and would bury it.
        $changelog_open = ($changelog_index == 0);
        $changelog_id = 'changelog_v' . preg_replace('/[^0-9]/', '_', $changelog_section['version']);

        $output_changelog_versions .= '
        <div class="mb-3">
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
    $output_changelog_button = '<button title="' . lang('Release Notes') . '" aria-label="' . lang('Release Notes') . '" class="pg-widget-action no-popover" type="button" data-bs-toggle="dialog" data-bs-target="#changelog_modal"><span class="bi bi-card-list"></span></button>';

    $output_changelog_modal = '
    <dialog class="dialog dialog-lg dialog-scrollable pg-dialog-centered" id="changelog_modal" aria-labelledby="changelog_modal_label">
                <div class="dialog-header">
                    <h5 class="dialog-title" id="changelog_modal_label">
                        <span class="bi bi-card-list me-3"></span>' . lang('Release Notes') . '
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="dialog" aria-label="' . lang('Close') . '"></button>
                </div>
                <div class="dialog-body text-start">
                    ' . $output_changelog_head . $output_changelog_versions . '
                </div>
                <div class="dialog-footer">
                    <button type="button" class="btn btn-sm btn-solid theme-secondary" data-bs-dismiss="dialog">' . lang('Close') . '</button>
                </div>
    </dialog>';
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
// arrives. Widget 25 will always shift when it comes back empty.

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
    <span class="placeholder rounded-4 flex-shrink-0" style="width:34px;height:34px"></span>
    <span class="pg-row-text">
        <span class="placeholder col-8 d-block" style="height:13px"></span>
        <span class="placeholder col-5 d-block mt-1" style="height:11px"></span>
    </span>
</div>';

// The compact variant, for the widgets that list one line per entry.
$pg_ph_row_sm = '
<div class="pg-row pg-row-sm">
    <span class="placeholder rounded-4 flex-shrink-0" style="width:22px;height:22px"></span>
    <span class="pg-row-text">
        <span class="placeholder col-7 d-block" style="height:12px"></span>
    </span>
</div>';

// Section heading inside a list -- "Top Pages", "Expiring Soon".
$pg_ph_heading = '
<div class="pg-row-heading">
    <span class="placeholder col-5 rounded-4" style="height:11px"></span>
</div>';

// KPI tile strip, in the two sizes api.php ships: compact (widgets 2 and 13,
// a 12px inline icon) and large (widgets 3 and 4, a 20px block icon). The
// strip height is pinned rather than left to the contents, because everything
// below the strip inherits any error in it.
$pg_ph_tiles = function ($height, $large) {
    $icon = $large ? 20 : 12;
    $tile =
        '<div class="flex-fill rounded-4 ' . ($large ? 'p-3' : 'px-1 py-1') . ' text-center d-flex flex-column align-items-center justify-content-center" style="gap:4px">'
        . '<span class="placeholder rounded-4" style="width:' . $icon . 'px;height:' . $icon . 'px"></span>'
        . '<span class="placeholder rounded-4" style="width:60%;height:' . ($large ? 15 : 13) . 'px"></span>'
        . '<span class="placeholder rounded-4" style="width:80%;height:' . ($large ? 12 : 9) . 'px"></span>'
        . '</div>';
    return '<div class="d-flex ' . ($large ? 'gap-3 p-3' : 'gap-1 px-3 pt-3 pb-1') . '" style="height:' . $height . 'px">' . str_repeat($tile, 3) . '</div>';
};

// Panels of a given height, for the widgets whose body is neither a list nor a
// tile strip. Anything from 100px up is a chart, calendar or similar, so it
// gets one soft rectangle; shorter blocks get a couple of lines so they read
// as a summary strip rather than a slab.
$pg_ph_blocks = function ($heights) {
    $out = '';
    foreach ($heights as $height) {
        if ($height >= 100) {
            $out .= '<div class="p-3" style="height:' . $height . 'px"><span class="placeholder w-100 h-100 rounded-4"></span></div>';
        } else {
            $out .= '<div class="d-flex flex-column justify-content-center px-3 border-bottom" style="height:' . $height . 'px;gap:6px">'
                . '<span class="placeholder col-7 rounded-4" style="height:12px"></span>'
                . '<span class="placeholder col-4 rounded-4" style="height:10px"></span>'
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
        <span class="placeholder rounded-4" style="width:26px;height:19px"></span>
        <span class="placeholder col-4 rounded-4"></span>
    </div>
    <span class="placeholder rounded-4 d-block" style="height:20px"></span>
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
        <span class="placeholder rounded-4" style="width:112px;height:19px"></span>
        <span class="placeholder rounded-4" style="width:54px;height:10px"></span>
    </div>
    <div class="pg-ec-sub" style="height:15.8px"><span class="placeholder col-9 rounded-4 d-block" style="height:10px"></span></div>
</div>
<div class="pg-ec-facts">
    <div class="pg-ec-fact" style="height:36.3px">
        <span class="placeholder col-9 rounded-4" style="height:10px"></span>
        <span class="placeholder col-7 rounded-4" style="height:13px;margin-top:6px"></span>
    </div>
    <div class="pg-ec-fact" style="height:36.3px">
        <span class="placeholder col-8 rounded-4" style="height:10px"></span>
        <span class="placeholder col-10 rounded-4" style="height:13px;margin-top:6px"></span>
    </div>
</div>';

// Default: the plain list, which is what most widgets render.
$placeholder = $pg_ph_card('<div class="pg-list">' . str_repeat($pg_ph_row, 5) . '</div>');

// Sales map: the period head over the outline on the left, the ranked regions
// on the right. Uses the widget's own .pg-split, so a card too narrow for two
// panels stacks here exactly as it will once the data lands.
$placeholder_w1 = $pg_ph_card(
    '<div class="pg-split">'
    . '<div class="pg-split-half d-flex flex-column">'
    . $pg_ph_head
    . '<div class="p-3 flex-fill d-flex"><span class="placeholder w-100 rounded-4" style="min-height:140px"></span></div>'
    . '</div>'
    . '<div class="pg-split-half"><div class="pg-list">' . str_repeat($pg_ph_row, 5) . '</div></div>'
    . '</div>');

// A filter button as the Activity and Operations Desk cards draw them: a tile
// and a label, with the count left to arrive with the data.
$pg_ph_filter = '
<div class="pg-filter">
    <span class="placeholder rounded-4 flex-shrink-0" style="width:28px;height:28px"></span>
    <span class="pg-filter-text"><span class="placeholder col-7 d-block" style="height:12px"></span></span>
</div>';

$placeholder_w7  = $pg_ph_card(
    '<div class="pg-split pg-act">'
    . '<div class="pg-split-half pg-filters">' . str_repeat($pg_ph_filter, 6) . '</div>'
    . '<div class="pg-split-half"><div class="pg-list">' . str_repeat($pg_ph_row, 6) . '</div></div>'
    . '</div>');
$placeholder_w3  = $pg_ph_card(
    '<div class="pg-split">'
    . '<div class="pg-split-half">' . $pg_ph_ec . '<div class="border-top mx-3 mb-1"></div><div class="pg-list">' . str_repeat($pg_ph_row_sm, 4) . '</div></div>'
    . '<div class="pg-split-half"><div class="pg-list">' . $pg_ph_heading . str_repeat($pg_ph_row_sm, 6) . '</div></div>'
    . '</div>');
$placeholder_w9  = $pg_ph_card(
    '<div class="pg-ops">'
    . '<div class="pg-lanes">' . str_repeat($pg_ph_filter, 3) . '</div>'
    . '<div class="pg-ops-body"><div class="pg-list">' . str_repeat($pg_ph_row, 4) . '</div></div>'
    . '</div>');
$placeholder_w4  = $pg_ph_card($pg_ph_tiles(126, true)  . $pg_ph_blocks(array(133)));
$placeholder_w5  = $pg_ph_card($pg_ph_blocks(array(277)));
$placeholder_w8  = $pg_ph_card($pg_ph_head . $pg_ph_blocks(array(27)) . '<div class="pg-list">' . str_repeat($pg_ph_row, 3) . '</div>');
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
    . '<span class="placeholder rounded-4 d-block mx-auto" style="width:170px;height:80px"></span>'
    . '<span class="placeholder rounded-4 d-block mx-auto mt-3" style="width:70px;height:12px"></span>'
    . '</div>'
    . '<div class="pg-checks">'
    . '<span class="placeholder rounded-4" style="width:74px;height:21px"></span>'
    . '<span class="placeholder rounded-4" style="width:58px;height:21px"></span>'
    . '<span class="placeholder rounded-4" style="width:66px;height:21px"></span>'
    . '<span class="placeholder rounded-4" style="width:88px;height:21px"></span>'
    . '<span class="placeholder rounded-4" style="width:62px;height:21px"></span>'
    . '<span class="placeholder rounded-4" style="width:80px;height:21px"></span>'
    . '<span class="placeholder rounded-4" style="width:54px;height:21px"></span>'
    . '<span class="placeholder rounded-4" style="width:92px;height:21px"></span>'
    . '<span class="placeholder rounded-4" style="width:70px;height:21px"></span>'
    . '<span class="placeholder rounded-4" style="width:60px;height:21px"></span>'
    . '</div>'
    . '</div>'
    . '<div class="pg-split-half">'
    . '<div class="pg-jobs">' . str_repeat('<span class="placeholder rounded-4 d-block" style="height:29px"></span>', 7) . '</div>'
    . '</div>'
    . '</div>'
);
$placeholder_w25 = $pg_ph_card($pg_ph_blocks(array(35)) . '<div class="pg-list">' . str_repeat($pg_ph_row, 4) . '</div>');
// Google Search: the two figures over the list of searches.
$placeholder_w31 = $pg_ph_card($pg_ph_blocks(array(48)) . '<div class="pg-list">' . $pg_ph_heading . str_repeat($pg_ph_row_sm, 5) . '</div>');
// The agenda: a day heading over small rows, twice.
$placeholder_w29 = $pg_ph_card('<div class="pg-list">'
    . $pg_ph_heading . str_repeat($pg_ph_row_sm, 3)
    . $pg_ph_heading . str_repeat($pg_ph_row_sm, 3) . '</div>');

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
// dashboards in the field have it stored. 6, 10, 11, 12, 13, 17, 19, 22, 24
// and 26 are retired and are not reused: their content lives on in 3, 5, 7, 9
// and 21.
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

// Google Search: what Search Console says about the site, once the site is
// linked to it (search_console.php). Same reach as the visitor reports.
require_once(PG_FUNCTIONS_DIR . '/includes/fn/search_console.php');

$widget_registry[31] = array(
    'title' => lang('Google Search'),
    'icon'  => 'bi-google',
    'body'  => $placeholder_w31,
    'show'  => ((($user['role'] < 3) || ($user['manage_visitors'] == true)) && pg_gsc_linked()));

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

// Two tracks: the summary on the left, the best sellers on the right. The best
// sellers were the Trending Content card's (6, retired), where they sat apart
// from the figures they explain.
$widget_registry[3] = array(
    'title' => lang('Ecommerce Summary'),
    'icon'  => 'bi-cash-stack',
    'body'  => $placeholder_w3,
    'wide'  => true,
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

// Activity: what happened lately, in one feed with a filter per kind. It took
// over the cards that each listed one kind of recent thing -- content changes
// (this id, formerly Recent Updates), forms (13), contacts (10), users (11),
// the site log (17) and email campaigns (19) -- which are retired. No condition
// of its own: everyone who reaches this screen has something they last edited,
// and each kind inside keeps the gate of the card it came from.
$widget_registry[7] = array(
    'title' => lang('Activity'),
    'icon'  => 'bi-activity',
    'body'  => $placeholder_w7,
    'wide'  => true,
    'show'  => true);

// Orders. The tab strip is gone: both lists are on screen at once, so the
// header is a plain title like every other widget and each half labels itself.
$widget_registry[8] = array(
    'title' => lang('Orders'),
    'icon'  => 'bi-cart4',
    'body'  => $placeholder_w8,
    'wide'  => true,
    'show'  => ((ECOMMERCE == true) && (($user['role'] < 3) || ($user['manage_ecommerce'] == true))));

// Operations desk: what the shop owes -- parcels to send, refunds to pay back,
// products that cannot be sold -- as three lanes on one card. It took over
// Pending Shipments (this id), Out of Stock Products (12) and Refund Pending
// (26). Shown wherever the shop is: the shipping lane is the only one that
// needs shipping switched on, and the card leaves it out when it is off.
$widget_registry[9] = array(
    'title' => lang('Operations Desk'),
    'icon'  => 'bi-clipboard-check',
    'body'  => $placeholder_w9,
    'wide'  => true,
    'show'  => ((ECOMMERCE === true) && (($user['role'] < 3) || ($user['manage_ecommerce'] == true))));

// Workspace cards: what the reader's own work looks like from here -- the
// tasks on their list, what colleagues wait on them for, their week on the
// calendar, and, for whoever leads a team, how that team stands today. Shown
// to workspace members only (pg_dashboard_workspace_rights(), the same gate
// as the strip under the greeting). The three lane cards wear the Operations
// Desk skeleton, which is their shape.
require_once(PG_FUNCTIONS_DIR . '/includes/dashboard/widgets.php');

$pg_ws_rights = pg_dashboard_workspace_rights($user);

$widget_registry[27] = array(
    'title' => lang('My Tasks'),
    'icon'  => 'bi-list-check',
    'body'  => $placeholder_w9,
    'show'  => ($pg_ws_rights !== null));

$widget_registry[28] = array(
    'title' => lang('Waiting on Me'),
    'icon'  => 'bi-hourglass-split',
    'body'  => $placeholder_w9,
    'show'  => ($pg_ws_rights !== null));

$widget_registry[29] = array(
    'title' => lang('My Week'),
    'icon'  => 'bi-calendar-week',
    'body'  => $placeholder_w29,
    'show'  => ($pg_ws_rights !== null));

$widget_registry[30] = array(
    'title' => lang('Team Pulse'),
    'icon'  => 'bi-activity',
    'body'  => $placeholder_w9,
    'show'  => (($pg_ws_rights !== null) && ($pg_ws_rights['board'] || !empty(ws_led_people($pg_ws_rights['id'])))));

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
        . '<div class="card-header pg-widget-handle border-0 bg-reset position-relative">'
        . (($widget['icon'] != '') ? '<i class="bi ' . $widget['icon'] . ' me-3"></i>' : '')
        . $widget['title']
        . $widget['action']
        . '</div>'
        . $widget['body']
        . '</div>'
        . '</div>';
}

// ── Widget controls ─────────────────────────────────────────────────────────
//
// Designers and administrators (role < 2): dragging a card by its header
// stores the new order. Printed into the page script block further down. Card
// style, backdrop, refresh time and Reset Widgets are on the Dashboard card of
// Site Settings > Appearance (includes/settings/appearance.php).
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

    function pg_save_widget_order(order, message_text) {

        $.ajax({
            contentType: "application/json",
            url: "api.php",
            data: JSON.stringify({
                action: "update_dashboard_widgets",
                token: software_token,
                message_text: message_text,
                widgets: order
            }),
            type: "POST"
        });
    }

    // SortableJS (assets/lib/sortablejs/): a card is picked up by its header
    // after a 300 ms hold, so a click on a header button stays a click.
    $(function() {

        var widgets = document.getElementById("widgets");

        if (!widgets || typeof Sortable === "undefined") {
            return;
        }

        Sortable.create(widgets, {
            draggable: ".pg-widget",
            handle: ".pg-widget-handle",
            filter: ".no-sortable",
            preventOnFilter: false,
            delay: 300,
            animation: 100,
            ghostClass: "pg-widget-ghost",
            forceFallback: true,
            fallbackClass: "pg-widget-drag",
            onUpdate: function() {

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

// The greeting at the top: the read, the advice, the workspace strip and the
// next meeting under the clock (includes/dashboard/brief.php).
require_once(PG_FUNCTIONS_DIR . '/includes/dashboard/brief.php');

$brief = pg_dashboard_brief($user, $changelog_sections);

// The system status bar that used to sit above the widget grid is gone: the
// same checks are widget 2 now, so keeping the bar would have printed the same
// score twice on one screen. The popover binding stays, because the widget's
// tiles use it and it has to exist before the widget's markup arrives. It
// waits for Bootstrap through pgUI: this script runs while the page is
// parsed, and Bootstrap 6 loads as a deferred module.
$output_status_popover_script = '
<script>
    pgUI.ready(function () {
        pgUI.popover.get(document.body, {
            selector: \'.status-popover\',
            trigger: "hover"
        });
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
<script src="assets/lib/sortablejs/Sortable.min.js"></script>
<style>
    /* Widget drag (SortableJS): the header is the grip, the card left behind
       is an empty slot and the copy under the pointer stays fully opaque. */
    .card.widget .pg-widget-handle { cursor: grab; }
    .pg-widget-ghost { opacity: 0; }
    .pg-widget-drag { opacity: 1 !important; cursor: move; }
</style>
<style>
    /* Bootstrap 6 raised the .placeholder min-height from 1em to 1.25em,
       which overrides the inline heights of the skeleton bars in 16px text;
       the skeleton was measured against the loaded rows with 1em. */
    @layer custom { .card-body-placeholder .placeholder { min-height: 1em; } }
</style>
    <section class="card pg-clockbar" aria-label="' . h(lang('Welcome')) . '">
        <div class="pg-brief" id="pg_brief">
            <!-- One mark, not two. The hour already had an icon in front of the
                 greeting; moving it into the chip makes it the speaker rather
                 than a decoration, and saves the line a second glyph. -->
            <span class="pg-brief-mark" aria-hidden="true"><i class="bi ' . h($brief['icon']) . '"></i></span>
            <div class="pg-brief-body">
                <p class="pg-brief-text" aria-live="polite">
                    <span class="pg-brief-greet" data-bs-toggle="tooltip" title="' . lang('Role') . ': ' . h($role) . '">' . $brief['greeting'] . '</span>
                    ' . $brief['line'] . '
                </p>
                <!-- The sentence rotates and the strip stays put: the strip is
                     the glance, the sentence is the read. It is not a chip
                     repeating where the sentence points. -->
                ' . $brief['strip'] . '
            </div>
            <button type="button" class="pg-brief-again" id="pg_brief_again" title="' . h(lang('Say something else')) . '" aria-label="' . h(lang('Say something else')) . '">
                <i class="bi bi-arrow-repeat" aria-hidden="true"></i>
            </button>
        </div>
        <div class="pg-clockbar-now">
            <span class="pg-clockbar-when">
                <span id="welcome_time_clock" class="pg-clockbar-time">' . get_absolute_time(array(
                    'timestamp' => time() ,
                    'type' => 'time',
                    'timezone_type' => 'site'
                )) . '</span>
                <span id="welcome_time_date" class="pg-clockbar-date">' . get_absolute_time(array(
                    'timestamp' => time() ,
                    'type' => 'date',
                    'size' => 'long',
                    'timezone_type' => 'site'
                )) . '</span>
            </span>
            ' . $brief['next'] . '
        </div>
    </section>
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
            
            <div id="widgets" class="pg-widgets mb-12">
                ' . $output_widgets . '
            </div>
        </div>
    </div> 
<script>
    // this is required for chart js animations.
    let delayed;
    ' . $output_widget_controls . '

    // ── Loading and refreshing the cards ────────────────────────────────────
    //
    // The cards arrive in one batch (get_widgets_data) rather than one request
    // each: every request pays for the whole bootstrap before it runs a query
    // of its own, and twenty of them at once queued on the server. The slow
    // cards are asked for on their own beside the batch, so the rest are not
    // held back behind them.
    //
    // After that only the cards whose content moves minute to minute are
    // asked for again, and only while somebody can see them: nothing while
    // the tab is hidden, and nothing for a card scrolled out of view. A card
    // skipped that way is marked stale and fetched as it comes back into
    // view. A card whose markup comes back unchanged is left alone -- its
    // charts are not rebuilt and its list keeps its scroll position.
    var pg_widgets = {
        refresh: ["4","7","8","9","16","25","27","28","30"],
        solo: ["1","2","5","21","23"],
        interval: Math.max(10, parseInt("' . (int) $widget_refresh_time . '", 10) || 60) * 1000,
        html: {},
        seen: {},
        stale: {},
        observer: null,
        missed: false
    };

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

    // Scroll positions inside a card, keyed by class and by position among
    // the elements with that class, so a list that gained a row at the top
    // still finds its own scroller after the redraw.
    function pg_widget_scroll_save(card) {
        var saved = [];
        var count = {};
        card.querySelectorAll("*").forEach(function(el){
            var key = el.getAttribute("class") || "";
            count[key] = (count[key] || 0) + 1;
            if (el.scrollTop > 0) {
                saved.push({key: key, nth: count[key], top: el.scrollTop});
            }
        });
        return saved;
    }

    function pg_widget_scroll_restore(card, saved) {
        if (!saved.length) {
            return;
        }
        var count = {};
        card.querySelectorAll("*").forEach(function(el){
            var key = el.getAttribute("class") || "";
            count[key] = (count[key] || 0) + 1;
            saved.forEach(function(item){
                if ((item.key === key) && (item.nth === count[key])) {
                    el.scrollTop = item.top;
                }
            });
        });
    }

    function pg_widget_apply(widget_id, response) {
        if (widget_id === "clock") {
            pg_clock_apply(response);
            return;
        }
        if (!response || (response.status !== "success")) {
            return;
        }
        var $widget = $(".card[widget-id=" + widget_id + "]");
        if (!$widget.length) {
            return;
        }
        var html = (typeof response.data === "string") ? response.data : "";
        if (pg_widgets.html[widget_id] === html) {
            return;
        }
        pg_widgets.html[widget_id] = html;
        var scroll = pg_widget_scroll_save($widget[0]);
        destroy_widget_charts($widget);
        $widget.find(".card-body-placeholder,.card-body,.card-footer").remove();
        $widget.append(html);
        // Listeners finish the card first (a filter hides the rows it does
        // not show), so the scroll goes back onto the list as it will stay.
        $widget.trigger("pg:widget-drawn", [widget_id]);
        pg_widget_scroll_restore($widget[0], scroll);
    }

    // One card at a time, the way the dashboard always asked. Used when the
    // batch fails as a whole -- one card that dies takes the batch response
    // with it, and here it takes only itself -- and by an installation part
    // way through an upgrade that does not know the batch action yet.
    function pg_widgets_one_by_one(ids) {
        ids.forEach(function(widget_id){
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
                    pg_widget_apply(widget_id, response);
                }
            });
        });
    }

    function pg_widgets_request(ids) {
        if (!ids.length) {
            return;
        }
        $.ajax({
            contentType: "application/json",
            url: "api.php",
            data: JSON.stringify({
                action: "get_widgets_data",
                token: software_token,
                widget_ids: ids
            }),
            type: "POST",
            success: function(response) {
                if (!response || (response.status !== "success") || !response.widgets) {
                    pg_widgets_one_by_one(ids);
                    return;
                }
                ids.forEach(function(widget_id){
                    if (response.widgets[widget_id]) {
                        pg_widget_apply(widget_id, response.widgets[widget_id]);
                    }
                });
            },
            error: function() {
                pg_widgets_one_by_one(ids);
            }
        });
    }

    // The light cards go in one batch, sent first so they land first; each
    // slow one goes on its own.
    function pg_widgets_load(ids) {
        var batch = [];
        var alone = [];
        ids.forEach(function(widget_id){
            if (pg_widgets.solo.indexOf(widget_id) !== -1) {
                alone.push(widget_id);
            } else {
                batch.push(widget_id);
            }
        });
        pg_widgets_request(batch);
        alone.forEach(function(widget_id){
            pg_widgets_request([widget_id]);
        });
    }

    function pg_widgets_on_page() {
        return $(".card[widget-id]").map(function(){
            return $(this).attr("widget-id");
        }).get();
    }

    function pg_widgets_tick() {
        if (document.visibilityState === "hidden") {
            pg_widgets.missed = true;
            return;
        }
        pg_widgets.missed = false;
        var due = ["clock"];
        pg_widgets_on_page().forEach(function(widget_id){
            if (pg_widgets.refresh.indexOf(widget_id) === -1) {
                return;
            }
            if (pg_widgets.observer && !pg_widgets.seen[widget_id]) {
                pg_widgets.stale[widget_id] = true;
                return;
            }
            due.push(widget_id);
        });
        pg_widgets_load(due);
    }

    // ── Filters inside a card ───────────────────────────────────────────────
    //
    // A card body marked data-pg-filterable holds buttons (data-pg-filter) and
    // the things they choose between (data-kind). Pressing a button shows the
    // things of its kind -- "all" shows every row -- with the link to the full
    // list of that kind, or the empty note when there is nothing of it. The
    // choice is kept per card in this browser and put back after every
    // redraw, so a refresh never throws the reader back to the first view.
    function pg_filter_store(body) {
        return "pg-dashboard-filter:" + body.getAttribute("data-pg-filterable");
    }

    function pg_filter_read(body) {
        try {
            return window.localStorage.getItem(pg_filter_store(body));
        } catch (e) {
            return null;
        }
    }

    function pg_filter_write(body, value) {
        try {
            window.localStorage.setItem(pg_filter_store(body), value);
        } catch (e) {}
    }

    function pg_filter_apply(body, value) {
        var buttons = body.querySelectorAll("[data-pg-filter]");
        var known = false;
        buttons.forEach(function(button){
            known = known || (button.getAttribute("data-pg-filter") === value);
        });
        // A kind that is not on the card any more -- a permission taken away,
        // a lane switched off -- falls back to the default the card names.
        if (!known) {
            value = body.getAttribute("data-pg-default") || "all";
        }
        buttons.forEach(function(button){
            button.setAttribute("aria-pressed", (button.getAttribute("data-pg-filter") === value) ? "true" : "false");
        });
        var shown = 0;
        body.querySelectorAll("[data-kind]").forEach(function(el){
            var kind = el.getAttribute("data-kind");
            var more = el.classList.contains("pg-filter-more");
            var on = more ? (kind === value) : ((value === "all") || (kind === value));
            el.classList.toggle("d-none", !on);
            if (on && !more) {
                shown++;
            }
        });
        body.querySelectorAll(".pg-filter-none").forEach(function(el){
            el.classList.toggle("d-none", shown > 0);
        });
    }

    $(document).on("pg:widget-drawn", function(event){
        $(event.target).find("[data-pg-filterable]").each(function(){
            pg_filter_apply(this, pg_filter_read(this));
        });
    });

    $(document).on("click", "[data-pg-filterable] [data-pg-filter]", function(){
        var body = $(this).closest("[data-pg-filterable]")[0];
        var value = this.getAttribute("data-pg-filter");
        pg_filter_write(body, value);
        pg_filter_apply(body, value);
        body.querySelectorAll(".pg-filter-scroll").forEach(function(el){
            el.scrollTop = 0;
        });
    });

    // ── The clock ───────────────────────────────────────────────────────────
    //
    // Drawn here rather than fetched every minute. The page is handed the
    // server time and the site offset from UTC once, and a timer in the
    // browser turns the minute over as it happens rather than up to a minute
    // later. Every refresh brings the server reading along, which corrects a
    // drifting computer clock and a change of daylight saving time. A new day
    // asks the server for the date line, which is written in the site
    // language.
    var pg_clock = ' . json_encode(array(
        'skew'   => time(),
        'offset' => (int) date('Z'),
        'h12'    => (TIME_FORMAT == 'twelve_hours'),
        'am'     => trim(preg_replace('/^[0-9:\s]+/', '', get_absolute_time(array('timestamp' => mktime(9, 0, 0), 'type' => 'time', 'timezone_type' => 'site', 'format' => 'plain_text')))),
        'pm'     => trim(preg_replace('/^[0-9:\s]+/', '', get_absolute_time(array('timestamp' => mktime(21, 0, 0), 'type' => 'time', 'timezone_type' => 'site', 'format' => 'plain_text')))),
        // The countdown under the clock, worded by the same strings
        // pg_dashboard_brief_until() uses; %1 and %2 are filled in here.
        'until'  => array(
            'now'  => lang('Now'),
            'min'  => lang(array('string' => 'in {var:1} min', 'vars' => array('%1'))),
            'h'    => lang(array('string' => 'in {var:1} h', 'vars' => array('%1'))),
            'hmin' => lang(array('string' => 'in {var:1} h {var:2} min', 'vars' => array('%1', '%2'))),
        ),
    ), JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) . ';
    pg_clock.skew = (pg_clock.skew * 1000) - Date.now();
    pg_clock.day = null;
    pg_clock.timer = null;

    function pg_clock_draw() {
        var now = Date.now() + pg_clock.skew;
        var site = new Date(now + (pg_clock.offset * 1000));
        var h = site.getUTCHours();
        var m = site.getUTCMinutes();
        var s = site.getUTCSeconds();
        var pad = function(n){ return ((n < 10) ? "0" : "") + n; };
        var day = site.getUTCFullYear() + "-" + pad(site.getUTCMonth() + 1) + "-" + pad(site.getUTCDate());
        var text = pg_clock.h12
            ? (((h % 12) || 12) + ":" + pad(m) + " " + ((h < 12) ? pg_clock.am : pg_clock.pm))
            : (pad(h) + ":" + pad(m));
        var box = document.getElementById("welcome_time_clock");
        if (box) {
            var time = document.createElement("time");
            time.setAttribute("datetime", "T" + pad(h) + ":" + pad(m) + ":" + pad(s) + "Z");
            time.textContent = text.trim();
            box.replaceChildren(time);
        }
        pg_clock_next(now);
        pg_clock_elapsed(now);
        if ((pg_clock.day !== null) && (pg_clock.day !== day)) {
            pg_widgets_request(["clock"]);
        }
        pg_clock.day = day;
        window.clearTimeout(pg_clock.timer);
        pg_clock.timer = window.setTimeout(pg_clock_draw, 60000 - (now % 60000) + 50);
    }

    // The meeting or visit under the clock. The server wrote every event
    // still to come today and CSS shows the first; one that has ended is
    // taken out here, which brings the next one forward without a request.
    // Same sum as pg_dashboard_brief_until(): minutes are rounded up.
    function pg_clock_next(now) {
        var box = document.querySelector(".pg-clockbar-next");
        if (!box) {
            return;
        }
        box.querySelectorAll(".pg-next").forEach(function(item){
            var starts = parseInt(item.getAttribute("data-pg-starts"), 10) * 1000;
            var ends = parseInt(item.getAttribute("data-pg-ends"), 10) * 1000;
            var label = item.querySelector(".pg-next-until");
            if (now >= ends) {
                item.remove();
                return;
            }
            if ((item.getAttribute("data-pg-all-day") === "1") || !label) {
                return;
            }
            if (now >= starts) {
                item.classList.add("is-now");
                label.textContent = pg_clock.until.now;
                return;
            }
            var minutes = Math.ceil((starts - now) / 60000);
            var hours = Math.floor(minutes / 60);
            label.textContent = (minutes < 60)
                ? pg_clock.until.min.replace("%1", minutes)
                : ((minutes % 60) === 0
                    ? pg_clock.until.h.replace("%1", hours)
                    : pg_clock.until.hmin.replace("%1", hours).replace("%2", minutes % 60));
        });
        if (!box.querySelector(".pg-next")) {
            box.remove();
        }
    }

    // A running workspace timer in the strip under the greeting, as hours and
    // minutes since it started.
    function pg_clock_elapsed(now) {
        document.querySelectorAll("[data-pg-elapsed]").forEach(function(el){
            var started = parseInt(el.getAttribute("data-pg-elapsed"), 10);
            if (!started) {
                return;
            }
            var seconds = Math.max(0, Math.floor(now / 1000) - started);
            var minutes = Math.floor((seconds % 3600) / 60);
            el.textContent = Math.floor(seconds / 3600) + ":" + ((minutes < 10) ? "0" : "") + minutes;
        });
    }

    function pg_clock_apply(response) {
        if (!response || (response.status !== "success") || (typeof response.epoch !== "number")) {
            return;
        }
        pg_clock.skew = (response.epoch * 1000) - Date.now();
        pg_clock.offset = response.offset;
        if (typeof response.date === "string") {
            $("#welcome_time_date").html(response.date);
        }
        pg_clock_draw();
    }

    // ── The brief ───────────────────────────────────────────────────────────
    //
    // "Say something else" asks for the greeting again. The server remembers
    // what it said last, so the new line is another one where there is
    // another one to say; the strip and the next meeting come back with it.
    function pg_brief_apply(data) {
        var brief = document.getElementById("pg_brief");
        if (!brief || !data) {
            return;
        }
        var $brief = $(brief);
        if (/^bi-[a-z0-9-]+$/.test(data.icon || "")) {
            $brief.find(".pg-brief-mark .bi").attr("class", "bi " + data.icon);
        }
        $brief.find(".pg-brief-greet").html(data.greeting || "");
        var $text = $brief.find(".pg-brief-text");
        $text.find(".pg-brief-line").remove();
        $text.append(data.line || "");
        $brief.find(".pg-brief-ws").remove();
        $brief.find(".pg-brief-body").append(data.strip || "");
        $(".pg-clockbar-next").remove();
        $(".pg-clockbar-now").append(data.next || "");
        // The arrival animation runs again, so the change is seen.
        brief.classList.remove("is-new");
        void brief.offsetWidth;
        brief.classList.add("is-new");
        pg_clock_draw();
    }

    function pg_brief_again(button) {
        if (button.disabled) {
            return;
        }
        button.disabled = true;
        button.classList.add("is-busy");
        $.ajax({
            contentType: "application/json",
            url: "api.php",
            data: JSON.stringify({
                action: "get_dashboard_brief",
                token: software_token
            }),
            type: "POST",
            success: function(response) {
                if (response && (response.status === "success")) {
                    pg_brief_apply(response.data);
                }
            },
            complete: function() {
                button.disabled = false;
                button.classList.remove("is-busy");
            }
        });
    }

    $(function() {
        pg_clock_draw();

        $("#pg_brief_again").on("click", function(){
            pg_brief_again(this);
        });

        if ("IntersectionObserver" in window) {
            // A scroll brings cards into view a few at a time; the ones that
            // arrive close together are gathered into one request.
            var back = [];
            var back_timer = null;

            pg_widgets.observer = new IntersectionObserver(function(entries){
                entries.forEach(function(entry){
                    var widget_id = entry.target.getAttribute("widget-id");
                    pg_widgets.seen[widget_id] = entry.isIntersecting;
                    if (entry.isIntersecting && pg_widgets.stale[widget_id] && (document.visibilityState !== "hidden")) {
                        delete pg_widgets.stale[widget_id];
                        back.push(widget_id);
                    }
                });
                if (back.length && !back_timer) {
                    back_timer = window.setTimeout(function(){
                        pg_widgets_load(back);
                        back = [];
                        back_timer = null;
                    }, 250);
                }
            }, {rootMargin: "200px 0px"});

            $(".card[widget-id]").each(function(){
                pg_widgets.observer.observe(this);
            });
        }

        pg_widgets_load(pg_widgets_on_page());

        window.setInterval(pg_widgets_tick, pg_widgets.interval);

        // Back to the tab: the clock catches up at once, and a refresh that
        // fell due while it was hidden runs now rather than at the next tick.
        document.addEventListener("visibilitychange", function(){
            if (document.visibilityState !== "visible") {
                return;
            }
            pg_clock_draw();
            if (pg_widgets.missed) {
                pg_widgets_tick();
            }
        });
    });
</script>
</main>' . 
output_footer();
$liveform->remove_form();
