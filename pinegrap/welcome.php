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
 *              2016–2026 Kodpen
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
if (($user['role'] == 3) && (no_acl_check($user['id']) == false) && ($user['manage_calendars'] == false) && ($user['manage_forms'] == false) && ($user['manage_visitors'] == false) && ($user['manage_contacts'] == false) && ($user['manage_emails'] == false) && ($user['manage_ecommerce'] == false) && ($user['manage_ecommerce_reports'] == false) && (count(get_items_user_can_edit('ad_regions', $user['id'])) == 0))
{
    log_activity("access denied to welcome screen", $_SESSION['sessionusername']);
    output_error('Access denied. <a href="javascript:history.go(-1)">Go back</a>.');
}


$output_widget_1 = '';
$output_widget_2 = '';
$output_widget_3 = '';
$output_widget_4 = '';
$output_widget_5 = '';
$output_widget_6 = '';
$output_widget_7 = '';
$output_widget_8 = '';
$output_widget_9 = '';
$output_widget_10 = '';
$output_widget_11 = '';
$output_widget_12 = '';
$output_widget_13 = '';
$output_widget_14 = '';
$output_widget_15 = '';
$output_widget_16 = '';
$output_widget_17 = '';
$output_widget_18 = '';
$output_widget_19 = '';
$output_widget_20 = '';
$output_widget_21 = '';
$output_widget_22 = '';
$output_widget_23 = '';
$output_widget_24 = '';
$output_widget_25 = '';
$output_widget_26 = '';







$query = "SELECT * FROM dashboard";
$result = mysqli_query(db::$con, $query) or output_error('Query failed.');
$dashboard = mysqli_fetch_assoc($result);
$order_widgets = $dashboard['order_widgets'];




$output_header_includes = '';
    


$output_widget_control_buttons = '';

// Release notes.
//
// Read here and embedded in the modal rather than linked, so the browser never
// requests changelog.txt over the network. The file sits in the software
// directory, which is served directly, and a public release list tells anyone
// which version a site runs and which security fixes it is missing.
//
// Costs one small file read on a screen that already assembles a dozen widgets.
$output_changelog_button = '';
$changelog_path = dirname(__FILE__) . '/changelog.txt';

if (is_file($changelog_path)) {

    $changelog_text = @file_get_contents($changelog_path);

    if ($changelog_text !== false && $changelog_text !== '') {

        $output_changelog_button = '
        <button title="' . lang('Release Notes') . '" class="btn panel-title-row border-0 me-1 my-1 no-popover" type="button" data-bs-toggle="modal" data-bs-target="#changelog_modal"><span class="bi bi-card-list"></span></button>

        <div class="modal fade" id="changelog_modal" tabindex="-1" aria-labelledby="changelog_modal_label" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-scrollable modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="changelog_modal_label">
                            <span class="bi bi-card-list me-2"></span>' . lang('Release Notes') . '
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="' . lang('Close') . '"></button>
                    </div>
                    <div class="modal-body">
                        <!--
                            text-align is forced left: the dashboard centres this
                            column, and inherited centring turns a column-aligned
                            text file into ragged nonsense.

                            white-space is "pre", not "pre-wrap". Wrapping breaks
                            the banner and the aligned tag column; a horizontal
                            scrollbar on a narrow screen is the lesser cost.
                        -->
                        <pre class="mb-0 text-body" style="text-align: left; white-space: pre; overflow-x: auto; font-size: .78rem; line-height: 1.45; font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, &quot;Courier New&quot;, monospace;">' . h($changelog_text) . '</pre>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">' . lang('Close') . '</button>
                    </div>
                </div>
            </div>
        </div>';
    }
}
$output_widget_controls = '';






// if the user is a Manager or above then output widget controls
if ($user['role'] < 2){


    $output_widget_controls .= '
        function reset_widgets() {

            widgets = [];
            widgets.push("default");
            widgets.join(",");

            // Use AJAX to update.
            $.ajax({
                contentType: "application/json",
                url: "api.php",
                data: JSON.stringify({
                    action: "update_dashboard_widgets",
                    token: software_token,
                    message_text : "restart",
                    widgets: widgets
                }),
                type: "POST",
                success: function(response) {
                    if (response.status == "success") {
                        location.reload();
                    }
                }
            });
        };
        $(function() {
            $("#widgets").sortable({
                connectWith: "#widgets",
                cancel: ".no-sortable",
                placeholder: "col-12 col-sm-6 col-md-6 col-lg-4 col-xl-3",
                handle: ".card .card-header",
                containment: "parent",
                delay:300,
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
                    var widgets = [];
                    $.each($("#widgets .widget"), function() {
                        widgets.push($(this).attr("widget-id"));
                    });
        
                    if (widgets.length === 0) {
                        alert("Please select at lease 1 widget");
                        return false;
                    }
                    widgets.join(",");
        
                    // Use AJAX to get various card info.
                    $.ajax({
                        contentType: "application/json",
                        url: "api.php",
                        data: JSON.stringify({
                            action: "update_dashboard_widgets",
                            token: software_token,
                            message_text : "repositioning",
                            widgets: widgets
                        }),
                        type: "POST",
                        success: function(response) {
                        }
                    });
                }
            });
        });';


    $output_widget_control_buttons = '
        <div class="dropdown no-arrow">
            <button title="' . lang('Widget Options') . '" class="btn panel-title-row border-0 dropdown-toggle me-1 my-1 no-popover" data-bs-target="#theme_options_menu" type="button" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false" ><span class=" bi bi-gear"></span></button>
            <div class="dropdown-menu p-2" id="theme_options_menu">         
                <form type="get" action="" class="disable_shortcut">
                    <label for="refresh" class="form-label">' . lang('Refresh Time') . '</label>
                    <div class="input-group">
                        <input type="number" name="refresh" id="refresh" min="10" value="' . $widget_refresh_time . '" class="form-control"/>
                        <div class="input-group-text">' . lang('seconds') . '</div>
                    </div>
                    <div class="text-center my-2">
                        <button class="btn btn-sm btn-primary w-100" type="submit">' . lang('Update') . '</button>
                    </div>
                    <hr class="divider"/>
                    <div class="text-center my-2">
                    <button data-loading-content=" " type="button" onclick="reset_widgets()" class="btn btn-sm btn-primary w-100" >' . lang('Reset Widgets') . '</button>
                    </div>
                </form>
            </div>
        </div>';
  

}
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

// Default: the plain list, which is what most widgets render.
$placeholder = $pg_ph_card('<div class="pg-list">' . str_repeat($pg_ph_row, 5) . '</div>');

$placeholder_w10 = $pg_ph_card($pg_ph_head . '<div class="pg-list">' . str_repeat($pg_ph_row, 4) . '</div>');

$placeholder_w3  = $pg_ph_card($pg_ph_tiles(123, true)  . '<div class="border-top mx-2 mb-1"></div><div class="pg-list">' . str_repeat($pg_ph_row_sm, 4) . '</div>');
$placeholder_w4  = $pg_ph_card($pg_ph_tiles(126, true)  . $pg_ph_blocks(array(133)));
$placeholder_w5  = $pg_ph_card($pg_ph_blocks(array(277)));
$placeholder_w6  = $pg_ph_card('<div class="pg-list">' . $pg_ph_heading . str_repeat($pg_ph_row_sm, 8) . '</div>');
$placeholder_w8  = $pg_ph_card($pg_ph_head . $pg_ph_blocks(array(27)) . '<div class="pg-list">' . str_repeat($pg_ph_row, 3) . '</div>');
$placeholder_w13 = $pg_ph_card($pg_ph_head . '<div class="pg-list">' . str_repeat($pg_ph_row, 4) . '</div>');
$placeholder_w15 = $pg_ph_card('<div class="pg-list">' . $pg_ph_heading . str_repeat($pg_ph_row, 4) . '</div>');
$placeholder_w18 = $pg_ph_card($pg_ph_blocks(array(29, 91, 29, 110)));
$placeholder_w20 = $pg_ph_card($pg_ph_blocks(array(231)));
$placeholder_w21 = $pg_ph_card($pg_ph_blocks(array(47, 36, 58, 86)));
$placeholder_w22 = $pg_ph_card($pg_ph_blocks(array(54, 28, 115)));
$placeholder_w23 = $pg_ph_card($pg_ph_blocks(array(79, 51, 28, 69)));
// The gauge, then the tile grid. Both borrow the widget's own classes so the
// boxes land where api.php will put them.
$placeholder_w24 = $pg_ph_card(
    '<div class="pg-health">'
    . '<span class="placeholder rounded d-block mx-auto" style="width:170px;height:80px"></span>'
    . '<span class="placeholder rounded d-block mx-auto mt-2" style="width:70px;height:12px"></span>'
    . '</div>'
    . '<div class="pg-health-grid">' . str_repeat('<span class="placeholder rounded" style="height:32px"></span>', 15) . '</div>'
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

$output_widget_1    = '';
if ((ECOMMERCE === true) and (($user['role'] < 3) or USER_MANAGE_ECOMMERCE or USER_MANAGE_ECOMMERCE_REPORTS)){
    $output_widget_3    = '<div class="col-12 col-sm-6 col-md-6 col-lg-4 col-xl-3"><div widget-id="3"  class="card widget" ><div class="card-header border-0 bg-reset position-relative"><i class="bi bi-cash-stack me-2"></i>' . lang('Ecommerce Summary')  . '</div>  ' . $placeholder_w3 . '  </div></div>';
}
if ($user['role'] < 3){
    $output_widget_4    = '<div class="col-12 col-sm-6 col-md-6 col-lg-4 col-xl-3"><div widget-id="4"  class="card widget" ><div class="card-header border-0 bg-reset position-relative"><i class="bi bi-broadcast-pin me-2"></i>' . lang('Online Engagement')  . '</div>  ' . $placeholder_w4 . '  </div></div>';
}
if (($user['role'] < 3) || ($user['manage_visitors'] == true)) {
    $output_widget_5    = '<div class="col-12"><div widget-id="5"  class="card widget no-sortable" ><div class="card-header border-0 bg-reset position-relative"><i class="bi bi-graph-up me-2"></i>' . lang('Visitor Summaries')  . '</div>  ' . $placeholder_w5 . '  </div></div>';
}

if (($user['role'] < 3) || ($user['manage_visitors'] == true)) {
    $output_widget_6 = '
    <div class="col-12 col-sm-6 col-md-6 col-lg-4 col-xl-3">
      <div widget-id="6" class="card widget">
        <div class="card-header border-0 bg-reset position-relative">
          <i class="bi bi-fire me-2"></i>' . lang('Trending Content') . '
        </div>
        ' . $placeholder_w6 . '
      </div>
    </div>';
}
    $output_widget_7    = '<div class="col-12 col-sm-6 col-md-6 col-lg-4 col-xl-3"><div widget-id="7"  class="card widget" ><div class="card-header border-0 bg-reset position-relative"><i class="bi bi-clock-history me-2"></i>' . lang('Recent Updates')  . '</div>  ' . $placeholder . '  </div></div>';
if ((ECOMMERCE == true) && (($user['role'] < 3) || ($user['manage_ecommerce'] == true))){
    $output_widget_8    = '<div class="col-12 col-sm-6 col-md-6 col-lg-4 col-xl-3"><div widget-id="8"  class="card widget" ><div class="card-header border-0 bg-reset position-relative">
                            <nav class="nav nav-underline nav-justified nav-fill flex-nowrap mb-0" id="nav-tab" role="tablist" style="--bs-nav-pills-border-radius: var(--bs-border-radius);--bs-nav-pills-link-active-color: #fff;--bs-nav-pills-link-active-bg: #0d6efd;">
                                <li class="nav-item">
                                    <a class="nav-link link-secondary active py-0 px-1" href="javascript:void(0)"data-bs-toggle="tab" data-bs-target="#tab-order" role="tab" aria-selected="true"><i class="bi bi-cart4 me-2"></i>' . lang('Orders') . '</a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link link-secondary py-0 px-1    " href="javascript:void(0)" data-bs-toggle="tab" data-bs-target="#tab-card" role="tab" aria-selected="false"><i class="bi bi-basket me-2"></i>' . lang('Carts') . '</a>
                                </li>
                            </nav>
                            </div>  ' . $placeholder_w8 . '  </div></div>';
}
if ((ECOMMERCE === true) && (ECOMMERCE_SHIPPING === true) && (($user['role'] < 3) || ($user['manage_ecommerce'] == true))) {
    $output_widget_9 = '<div class="col-12 col-sm-6 col-md-6 col-lg-4 col-xl-3"><div widget-id="9" class="card widget"><div class="card-header border-0 bg-reset position-relative"><i class="bi bi-truck me-2"></i>' . lang('Pending Shipments') . '</div>' . $placeholder_shipments . '</div></div>';
} else {
    $output_widget_9 = '';
}
if (($user['role'] < 3) || ($user['manage_contacts'] == true)){
    $output_widget_10   = '<div class="col-12 col-sm-6 col-md-6 col-lg-4 col-xl-3"><div widget-id="10" class="card widget" ><div class="card-header border-0 bg-reset position-relative"><i class="bi bi-person-lines-fill me-2"></i>' . lang('Contacts')  . '</div>  ' . $placeholder_w10 . '  </div></div>';
}
if ($user['role'] < 3){
    $output_widget_11   = '<div class="col-12 col-sm-6 col-md-6 col-lg-4 col-xl-3"><div widget-id="11" class="card widget" ><div class="card-header border-0 bg-reset position-relative"><i class="bi bi-people me-2"></i>' . lang('Users')  . '</div>  ' . $placeholder . '  </div></div>';
}
if ((ECOMMERCE == true) && (($user['role'] < 3) || ($user['manage_ecommerce'] == true))){
    $output_widget_12   = '<div class="col-12 col-sm-6 col-md-6 col-lg-4 col-xl-3"><div widget-id="12" class="card widget" ><div class="card-header border-0 bg-reset position-relative"><i class="bi bi-exclamation-diamond me-2"></i>' . lang('Out of Stock Products')  . '</div>  ' . $placeholder . '  </div></div>';
}
if (($user['role'] < 3) || ($user['manage_forms'] == true)){
    $output_widget_13   = '<div class="col-12 col-sm-6 col-md-6 col-lg-4 col-xl-3"><div widget-id="13" class="card widget" ><div class="card-header border-0 bg-reset position-relative"><i class="bi bi-file-earmark-text me-2"></i>' . lang('Forms')  . '</div>  ' . $placeholder_w13 . '  </div></div>';
}
if (($user['role'] < 1) && (SUBSCRIPTION_ID != '') && (SUBSCRIPTION_ID != ' ') && (SUBSCRIPTION_ID != NULL)){
    $output_widget_14   = '<div class="col-12 col-sm-6 col-md-6 col-lg-4 col-xl-3"><div widget-id="14" class="card widget" ><div class="card-header border-0 bg-reset position-relative">' . lang('Subscriptions')  . '</div>  ' . $placeholder . '  </div></div>';
}
if ((ECOMMERCE === true) && (($user['role'] < 3) || ($user['manage_ecommerce'] == true))) {
    $output_widget_15 = '<div class="col-12 col-sm-6 col-md-6 col-lg-4 col-xl-3"><div widget-id="15" class="card widget"><div class="card-header border-0 bg-reset position-relative"><i class="bi bi-tag me-2"></i>' . lang('Offers') . '</div>' . $placeholder_w15 . '</div></div>';
} else {
    $output_widget_15 = '';
}

if ((ECOMMERCE == true) && (($user['role'] < 3) || ($user['manage_ecommerce'] == true))){
    $output_widget_16   = '<div class="col-12 col-sm-6 col-md-6 col-lg-4 col-xl-3"><div widget-id="16" class="card widget" ><div class="card-header border-0 bg-reset position-relative"><i class="bi bi-currency-exchange me-2"></i>' . lang('Current Site Exchange Rates')  . '</div>  ' . $placeholder . '  </div></div>';
}
if ($user['role'] < 3){
    $output_widget_17   = '<div class="col-12 col-sm-6 col-md-6 col-lg-4 col-xl-3"><div widget-id="17" class="card widget" ><div class="card-header border-0 bg-reset position-relative"><i class="bi bi-journal-text me-2"></i>' . lang('Site Logs')  . '</div>  ' . $placeholder . '  </div></div>';
}
// ── File Management (widget 18) ─────────────────────────────────────
//
// Replaces the old Admin Notes widget and deliberately keeps its id, so
// dashboards that already stored "18" in dashboard.order_widgets pick the
// new widget up in the same slot without anyone having to reset widgets.
//
// Shown to anyone who can actually act on a file. Roles 0-2 always can;
// a role 3 contributor only when at least one folder was granted to them
// with edit rights, otherwise the widget would be a permanently empty
// card on their dashboard. The scoping itself (which files, and whether
// design files are included at all) happens in api.php, where the data
// is built.
if (($user['role'] < 3) || no_acl_check($user['id'])) {
    $output_widget_18   = '<div class="col-12 col-sm-6 col-md-6 col-lg-4 col-xl-3"><div widget-id="18" class="card widget" ><div class="card-header border-0 bg-reset position-relative"><i class="bi bi-hdd-stack me-2"></i>' . lang('File Management')  . '</div>  ' . $placeholder_w18 . '  </div></div>';
}
if($user['role'] < 3){
    $output_widget_19   = '<div class="col-12 col-sm-6 col-md-6 col-lg-4 col-xl-3"><div widget-id="19" class="card widget" ><div class="card-header border-0 bg-reset position-relative"><i class="bi bi-megaphone me-2"></i>' . lang('Email Campaigns')  . '</div>  ' . $placeholder . '  </div></div>';
}

if(validate_calendars_access($user, $only_return = true) != false){
    $output_widget_20   = '<div class="col-12 col-sm-6 col-md-6 col-lg-4 col-xl-3"><div widget-id="20" class="card widget" ><div class="card-header border-0 bg-reset position-relative"><i class="bi bi-calendar3 me-2"></i>' . lang('Calendars')  . '</div>  ' . $placeholder_w20 . '  </div></div>';
}

// Firewall widgets: staff roles only (0 administrator, 1 manager,
// 2 designer). Contributors are excluded — firewall events carry raw attack
// payloads and visitor addresses.
//
// 21 is the event feed (what happened, most recent first).
// 22 is the threat digest (who is generating the load, ranked).
if ($user['role'] < 3){
    $output_widget_21   = '<div class="col-12 col-sm-6 col-md-6 col-lg-4 col-xl-3"><div widget-id="21" class="card widget" ><div class="card-header border-0 bg-reset position-relative"><i class="bi bi-shield-check me-2"></i>' . lang('Firewall')  . '</div>  ' . $placeholder_w21 . '  </div></div>';
    $output_widget_22   = '<div class="col-12 col-sm-6 col-md-6 col-lg-4 col-xl-3"><div widget-id="22" class="card widget" ><div class="card-header border-0 bg-reset position-relative"><i class="bi bi-radar me-2"></i>' . lang('Threat Summary')  . '</div>  ' . $placeholder_w22 . '  </div></div>';
    // Only when monitoring is on. With it off the tables are emptied, so the
    // widget would sit on the dashboard showing zeroes as though the site had
    // no traffic — which reads as a fault rather than as a setting.
    if (!defined('PERF_MONITOR_ENABLED') || PERF_MONITOR_ENABLED) {
        $output_widget_23   = '<div class="col-12 col-sm-6 col-md-6 col-lg-4 col-xl-3"><div widget-id="23" class="card widget" ><div class="card-header border-0 bg-reset position-relative"><i class="bi bi-speedometer2 me-2"></i>' . lang('Performance')  . '</div>  ' . $placeholder_w23 . '  </div></div>';
    }
}

// One health score for the installation: security checks, plus the backup,
// update and scheduled-task checks that used to be a separate maintenance card
// at this same id. Limited to the roles that can act on any of it -- backups.php
// and software_update.php both require manager or above, so below that the card
// would be a list of read-only worries.
if ($user['role'] < 3) {
    $output_widget_2   = '<div class="col-12 col-sm-6 col-md-6 col-lg-4 col-xl-3"><div widget-id="2" class="card widget" ><div class="card-header border-0 bg-reset position-relative"><i class="bi bi-activity me-2"></i>' . lang('System Status')  . '</div>  ' . $placeholder_w24 . '  </div></div>';
}

// Comment moderation queue. Same reach as view_comments.php: staff always,
// and a contributor once at least one folder has been granted to them, since
// the queue is scoped through the commented page's folder.
if (($user['role'] < 3) || no_acl_check($user['id'])) {
    $output_widget_25   = '<div class="col-12 col-sm-6 col-md-6 col-lg-4 col-xl-3"><div widget-id="25" class="card widget" ><div class="card-header border-0 bg-reset position-relative"><i class="bi bi-chat-square-quote me-2"></i>' . lang('Comment Moderation')  . '</div>  ' . $placeholder_w25 . '  </div></div>';
}

// Cancelled orders whose money has not gone back yet. Same reach as the rest
// of the shop screens, because view_order.php is where the refund is marked
// done and that is what every row here links to.
if ((ECOMMERCE == true) && (($user['role'] < 3) || ($user['manage_ecommerce'] == true))) {
    $output_widget_26   = '<div class="col-12 col-sm-6 col-md-6 col-lg-4 col-xl-3"><div widget-id="26" class="card widget" ><div class="card-header border-0 bg-reset position-relative"><i class="bi bi-cash-coin me-2"></i>' . lang('Refund Pending')  . '</div>  ' . $placeholder_w25 . '  </div></div>';
}


$widgets = array(
    //1   => $output_widget_1,//removed
    2  => $output_widget_2,//System Status
    3   => $output_widget_3,//Ecommerce Summary
    4   => $output_widget_4,//Online Engagement
    //5   => $output_widget_5,//Visitor Summaries
    6   => $output_widget_6,//Trending Content
    7   => $output_widget_7,//Recent Updates
    8   => $output_widget_8,//Shopping
    9   => $output_widget_9,//Pending Shipments
    10  => $output_widget_10,//Contacts
    11  => $output_widget_11,//Users
    12  => $output_widget_12,//Out of Stock Products
    13  => $output_widget_13,//Forms
    14  => $output_widget_14,//Subscriptions
    15  => $output_widget_15,//Offers
    16  => $output_widget_16,//Current Site Exchange Rates
    17  => $output_widget_17,//Site Logs
    18  => $output_widget_18,//File Management
    19  => $output_widget_19,//Email Campaigns
    20  => $output_widget_20,//Calendars
    21  => $output_widget_21,//Firewall
    22  => $output_widget_22,//Threat Summary
    23  => $output_widget_23,//Performance
    //24   => $output_widget_24,//removed
    25  => $output_widget_25,//Comment Moderation
    26  => $output_widget_26//Pending Refunds
);

$order_widgets = $dashboard['order_widgets'];
if($order_widgets == 'default'){
    $order_widgets = '5,1,2,3,4,6,7,8,9,10,11,12,13,14,15,16,17,18,19,20,21,22,23,24,25,26';
}
$output_widgets = '';
$order_widgets_array = explode(',', $order_widgets);

// A widget that shipped after this dashboard's order was last saved is not in
// the stored list. array_search() answers false for it, false compares as 0,
// and the sort below then scatters new widgets to the front in an order nobody
// chose. Appending the unknown ids gives every widget a defined position and
// places new ones at the end, which is where a new thing belongs.
//
// It also means adding a widget never needs a database migration or a message
// asking everyone to press "Reset Widgets".
foreach (array_keys($widgets) as $unplaced_widget_key) {

    if (!in_array((string) $unplaced_widget_key, $order_widgets_array)) {
        $order_widgets_array[] = (string) $unplaced_widget_key;
    }
}

$selected_widgets = $order_widgets_array;
uksort($widgets, function ($key1, $key2) use ($selected_widgets)
{
    return (array_search($key1, $selected_widgets) > array_search($key2, $selected_widgets));
});
foreach ($widgets as $key => $value)
{
    $output_widgets .= $value;

}




$output_greeting_message_text = '';
$time = date("H"); // current hour in 24h format
if ($time < 4) {
    $icon = '<i class="bi bi-moon-stars"></i> ';
    $output_greeting_message_text = $icon . lang('Good night') . ', ';
} elseif ($time >= 4 && $time < 12) {
    $icon = '<i class="bi bi-sunrise"></i> ';
    $output_greeting_message_text = $icon . lang('Good morning') . ', ';
} elseif ($time >= 12 && $time < 17) {
    $icon = '<i class="bi bi-sun"></i> ';
    $output_greeting_message_text = $icon . lang('Good afternoon') . ', ';
} elseif ($time >= 17 && $time < 19) {
    $icon = '<i class="bi bi-brightness-alt-high"></i> ';
    $output_greeting_message_text = $icon . lang('Good evening') . ', ';
} else {
    $icon = '<i class="bi bi-moon-stars"></i> ';
    $output_greeting_message_text = $icon . lang('Good night') . ', ';
}

$output_greeting_message_text .= $_SESSION['sessionusername'];

// The system status bar that used to sit above the widget grid is gone: the
// same checks are widget 24 now, so keeping the bar would have printed the same
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
        
    )
) . 
$output_header_includes .
'<meta http-equiv="Refresh" content="3600"> <!--Refresh page every (3600 sec = 60 min) to stop error invalid token. Will be measured later. -->
<script src="assets/chartjs/chart.umd.min.js"></script>
    <div class="row p-4 justify-content-center">
        <div class="col-auto text-center">
            <div class="h6 mb-0 text-body-tertiary" data-bs-toggle="tooltip" title="' . lang('Role') . ': ' . h($role) . '">
                ' . $output_greeting_message_text . '
            </div>
            <div class="lh-1 display-3 fw-bolder clock-color " >
                ' . get_absolute_time(array(
                    'timestamp' => time() ,
                    'type' => 'time',
                    'timezone_type' => 'site'
                )) . '
            </div>
            <div class="fw-bolder text-body-tertiary " style="font-size:.8em">
                ' . get_absolute_time(array(
                    'timestamp' => time() ,
                    'type' => 'date',
                    'size' => 'long',
                    'timezone_type' => 'site'
                )) . '
            </div>
        </div> 
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
            <div class="row mb-3 justify-content-center align-items-center">
                <div class="col-12 col-md-auto text-center d-flex justify-content-center align-items-center">
                    ' . $output_changelog_button . '
                    ' . $output_widget_control_buttons . '
                </div>
            </div>
            ' . $output_status_popover_script . '
            <div class="row g-4 mb-4">
                ' . $output_widget_5 . '
            </div>
            
            <div id="widgets" class="row g-4 mb-5">
                ' . $output_widgets . '
            </div>
        </div>
    </div> 
</main>
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
                            $("#system_status_widget .card-body").html(response.data);
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
</script>' . 
output_footer();
$liveform->remove_form();
