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
 *              2016–2025 Kodpen
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
    $widget_refresh_time = $_SESSION['software']['welcome']['widget']['refresh'];
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
$placeholder = '
<div class="card-body card-body-placeholder overflow-auto h-75">
    <div class="row ">
        <div class="col-auto align-self-center">
            <div class="card-title placeholder-glow " style="width:50px;height:50px;">
                <span class="placeholder w-100 h-100 rounded-pill"></span>
            </div>
        </div>
        <div class="col">
            <p class="card-text placeholder-glow">
                <span class="placeholder col-12"></span>
                <span class="placeholder col-4"></span>
                <span class="placeholder col-7"></span>
            </p>
        </div>
    </div>
</div>';

$output_widget_1    = '';
if ((ECOMMERCE === true) and (($user['role'] < 3) or USER_MANAGE_ECOMMERCE or USER_MANAGE_ECOMMERCE_REPORTS)){
    $output_widget_2    = '<div class="col-12 col-sm-6 col-md-6 col-lg-4 col-xl-3"><div widget-id="2"  class="card widget" ><div class="card-header border-0 bg-reset position-relative"><i class="bi bi-bar-chart-line me-2"></i>' . lang('Activity Summary')  . '</div>  ' . $placeholder . '  </div></div>';
    $output_widget_3    = '<div class="col-12 col-sm-6 col-md-6 col-lg-4 col-xl-3"><div widget-id="3"  class="card widget" ><div class="card-header border-0 bg-reset position-relative"><i class="bi bi-cash-stack me-2"></i>' . lang('Ecommerce Summary')  . '</div>  ' . $placeholder . '  </div></div>';
}
if ($user['role'] < 3){
    $output_widget_4    = '<div class="col-12 col-sm-6 col-md-6 col-lg-4 col-xl-3"><div widget-id="4"  class="card widget" ><div class="card-header border-0 bg-reset position-relative"><i class="bi bi-globe me-2"></i>' . lang('Whois Online')  . '</div>  ' . $placeholder . '  </div></div>';
}
if (($user['role'] < 3) || ($user['manage_visitors'] == true)) {
    $output_widget_5    = '<div class="col-12"><div widget-id="5"  class="card widget no-sortable" ><div class="card-header border-0 bg-reset position-relative"><i class="bi bi-graph-up me-2"></i>' . lang('Visitor Summaries')  . '</div>  ' . $placeholder . '  </div></div>';
}

if (($user['role'] < 3) || ($user['manage_visitors'] == true)) {
    $output_widget_6 = '
    <div class="col-12 col-sm-6 col-md-6 col-lg-4 col-xl-3">
      <div widget-id="6" class="card widget">
        <div class="card-header border-0 bg-reset position-relative">
          <i class="bi bi-fire me-2"></i>' . lang('Trending Content') . '
        </div>
        ' . $placeholder . '
      </div>
    </div>';
}
    $output_widget_7    = '<div class="col-12 col-sm-6 col-md-6 col-lg-4 col-xl-3"><div widget-id="7"  class="card widget" ><div class="card-header border-0 bg-reset position-relative"><i class="bi bi-clock-history me-2"></i>' . lang('Recent Updates')  . '</div>  ' . $placeholder . '  </div></div>';
if ((ECOMMERCE == true) && (($user['role'] < 3) || ($user['manage_ecommerce'] == true))){
    $output_widget_8    = '<div class="col-12 col-sm-6 col-md-6 col-lg-4 col-xl-3"><div widget-id="8"  class="card widget" ><div class="card-header border-0 bg-reset position-relative"><i class="bi bi-bag me-2"></i>' . lang('Shopping')  . '</div>  ' . $placeholder . '  </div></div>';
}
if ((ECOMMERCE === true) && (ECOMMERCE_SHIPPING === true) && (($user['role'] < 3) || ($user['manage_ecommerce'] == true))) {
    $output_widget_9 = '<div class="col-12 col-sm-6 col-md-6 col-lg-4 col-xl-3"><div widget-id="9" class="card widget"><div class="card-header border-0 bg-reset position-relative"><i class="bi bi-truck me-2"></i>' . lang('Pending Shipments') . '</div>' . $placeholder . '</div></div>';
} else {
    $output_widget_9 = '';
}
if (($user['role'] < 3) || ($user['manage_contacts'] == true)){
    $output_widget_10   = '<div class="col-12 col-sm-6 col-md-6 col-lg-4 col-xl-3"><div widget-id="10" class="card widget" ><div class="card-header border-0 bg-reset position-relative"><i class="bi bi-person-lines-fill me-2"></i>' . lang('Contacts')  . '</div>  ' . $placeholder . '  </div></div>';
}
if ($user['role'] < 3){
    $output_widget_11   = '<div class="col-12 col-sm-6 col-md-6 col-lg-4 col-xl-3"><div widget-id="11" class="card widget" ><div class="card-header border-0 bg-reset position-relative"><i class="bi bi-people me-2"></i>' . lang('Users')  . '</div>  ' . $placeholder . '  </div></div>';
}
if ((ECOMMERCE == true) && (($user['role'] < 3) || ($user['manage_ecommerce'] == true))){
    $output_widget_12   = '<div class="col-12 col-sm-6 col-md-6 col-lg-4 col-xl-3"><div widget-id="12" class="card widget" ><div class="card-header border-0 bg-reset position-relative"><i class="bi bi-exclamation-diamond me-2"></i>' . lang('Out of Stock Products')  . '</div>  ' . $placeholder . '  </div></div>';
}
if (($user['role'] < 3) || ($user['manage_forms'] == true)){
    $output_widget_13   = '<div class="col-12 col-sm-6 col-md-6 col-lg-4 col-xl-3"><div widget-id="13" class="card widget" ><div class="card-header border-0 bg-reset position-relative"><i class="bi bi-file-earmark-text me-2"></i>' . lang('Forms')  . '</div>  ' . $placeholder . '  </div></div>';
}
if (($user['role'] < 1) && (SUBSCRIPTION_ID != '') && (SUBSCRIPTION_ID != ' ') && (SUBSCRIPTION_ID != NULL)){
    $output_widget_14   = '<div class="col-12 col-sm-6 col-md-6 col-lg-4 col-xl-3"><div widget-id="14" class="card widget" ><div class="card-header border-0 bg-reset position-relative">' . lang('Subscriptions')  . '</div>  ' . $placeholder . '  </div></div>';
}
if ((ECOMMERCE === true) && (($user['role'] < 3) || ($user['manage_ecommerce'] == true))) {
    $output_widget_15 = '<div class="col-12 col-sm-6 col-md-6 col-lg-4 col-xl-3"><div widget-id="15" class="card widget"><div class="card-header border-0 bg-reset position-relative"><i class="bi bi-tag me-2"></i>' . lang('Offers') . '</div>' . $placeholder . '</div></div>';
} else {
    $output_widget_15 = '';
}

if ((ECOMMERCE == true) && (($user['role'] < 3) || ($user['manage_ecommerce'] == true))){
    $output_widget_16   = '<div class="col-12 col-sm-6 col-md-6 col-lg-4 col-xl-3"><div widget-id="16" class="card widget" ><div class="card-header border-0 bg-reset position-relative"><i class="bi bi-currency-exchange me-2"></i>' . lang('Current Site Exchange Rates')  . '</div>  ' . $placeholder . '  </div></div>';
}
if ($user['role'] < 3){
    $output_widget_17   = '<div class="col-12 col-sm-6 col-md-6 col-lg-4 col-xl-3"><div widget-id="17" class="card widget" ><div class="card-header border-0 bg-reset position-relative"><i class="bi bi-journal-text me-2"></i>' . lang('Site Logs')  . '</div>  ' . $placeholder . '  </div></div>';
}
if ($user['role'] < 1){
    $output_header_includes .= '
    <link href="https://cdn.quilljs.com/1.3.6/quill.snow.css" rel="stylesheet">
    <script src="https://cdn.quilljs.com/1.3.6/quill.js" defer></script>';
  $output_widget_controls .= '
function init_editor() {
    var once = false;
    var editor = $("#editor");
    var save = $("#submit_note");
    var clear = $("#clear_note");
    var quill;

    editor.click(function () {
        if (!once) {
            once = true;

            var toolbarOptions = [
                [{ "header": [1, 2, 3, 4, 5, 6, false] }],
                [{ "align": [] }],
                ["blockquote"],
                [{ "list": "ordered" }]
            ];

            // Initialize Quill editor
            quill = new Quill("#editor", {
                modules: { toolbar: toolbarOptions },
                theme: "snow"
            });

            // Enable clear button if editor has content
            if (quill.getText().trim().length > 0) {
                clear.removeClass("disabled");
            }

            // Listen for content changes
            quill.on("text-change", function () {
                save.removeClass("disabled");

                if (quill.getText().trim().length > 0) {
                    clear.removeClass("disabled");
                } else {
                    clear.addClass("disabled");
                }
            });

            // Ctrl+S or Cmd+S triggers save
            $(document).keydown(function (event) {
                if ((event.ctrlKey || event.metaKey) && String.fromCharCode(event.which).toLowerCase() === "s") {
                    event.preventDefault();
                    save.click();
                    return false;
                }
            });
        }
    });

    // Clear button logic
    clear.click(function () {
        pgConfirm({
            title: "' . lang('Clear') . '",
            message: "' . lang('Do you really want to clear current note?') . '",
            confirmText: "' . lang('Clear') . '",
            cancelText: "' . lang('Cancel') . '",
            variant: "warning"
        }).then(function (ok) {
            if (ok && quill) {
                quill.setText("");
                clear.addClass("disabled");
                save.click();
            }
        });
    });

    // Save button logic
    save.click(function () {
        save.html("' . lang('Saving') . '.. <i id=\"save_load\" class=\"bi bi-arrow-repeat\"></i>");

        var notes = editor.find(".ql-editor").html();

        $.ajax({
            contentType: "application/json",
            url: "api.php",
            data: JSON.stringify({
                action: "update_dashboard_note",
                token: software_token,
                notes: notes
            }),
            type: "POST",
            success: function (response) {
                var status = response.status;
                if (status === "success") {
                    save.html("' . lang('Save') . '");
                    save.addClass("disabled");
                }
            }
        });
    });
}
';



    $output_widget_18   = '<div class="col-12 col-sm-6 col-md-6 col-lg-4 col-xl-3"><div widget-id="18" class="card widget" ><div class="card-header border-0 bg-reset position-relative"><i class="bi bi-check2-square me-2"></i>' . lang('Admin Notes')  . '</div>  ' . $placeholder . '  </div></div>';
}
if($user['role'] < 3){
    $output_widget_19   = '<div class="col-12 col-sm-6 col-md-6 col-lg-4 col-xl-3"><div widget-id="19" class="card widget" ><div class="card-header border-0 bg-reset position-relative"><i class="bi bi-megaphone me-2"></i>' . lang('Email Campaigns')  . '</div>  ' . $placeholder . '  </div></div>';
}

if(validate_calendars_access($user, $only_return = true) != false){
    $output_widget_20   = '<div class="col-12 col-sm-6 col-md-6 col-lg-4 col-xl-3"><div widget-id="20" class="card widget" ><div class="card-header border-0 bg-reset position-relative"><i class="bi bi-calendar3 me-2"></i>' . lang('Calendars')  . '</div>  ' . $placeholder . '  </div></div>';
}

// Firewall widgets: staff roles only (0 administrator, 1 manager,
// 2 designer). Contributors are excluded — firewall events carry raw attack
// payloads and visitor addresses.
//
// 21 is the event feed (what happened, most recent first).
// 22 is the threat digest (who is generating the load, ranked).
if ($user['role'] < 3){
    $output_widget_21   = '<div class="col-12 col-sm-6 col-md-6 col-lg-4 col-xl-3"><div widget-id="21" class="card widget" ><div class="card-header border-0 bg-reset position-relative"><i class="bi bi-shield-check me-2"></i>' . lang('Firewall')  . '</div>  ' . $placeholder . '  </div></div>';
    $output_widget_22   = '<div class="col-12 col-sm-6 col-md-6 col-lg-4 col-xl-3"><div widget-id="22" class="card widget" ><div class="card-header border-0 bg-reset position-relative"><i class="bi bi-radar me-2"></i>' . lang('Threat Summary')  . '</div>  ' . $placeholder . '  </div></div>';
    // Only when monitoring is on. With it off the tables are emptied, so the
    // widget would sit on the dashboard showing zeroes as though the site had
    // no traffic — which reads as a fault rather than as a setting.
    if (!defined('PERF_MONITOR_ENABLED') || PERF_MONITOR_ENABLED) {
        $output_widget_23   = '<div class="col-12 col-sm-6 col-md-6 col-lg-4 col-xl-3"><div widget-id="23" class="card widget" ><div class="card-header border-0 bg-reset position-relative"><i class="bi bi-speedometer2 me-2"></i>' . lang('Performance')  . '</div>  ' . $placeholder . '  </div></div>';
    }
}


$widgets = array(
    //1   => $output_widget_1,//removed
    2   => $output_widget_2,//Activity Summary
    3   => $output_widget_3,//Ecommerce Summary
    4   => $output_widget_4,//Whois Online
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
    18  => $output_widget_18,//Admin Notes
    19  => $output_widget_19,//Email Campaigns
    20  => $output_widget_20,//Calendars
    21  => $output_widget_21,//Firewall
    22  => $output_widget_22,//Threat Summary
    23  => $output_widget_23//Performance
);

$order_widgets = $dashboard['order_widgets'];
if($order_widgets == 'default'){
    $order_widgets = '5,1,2,3,4,6,7,8,9,10,11,12,13,14,15,16,17,18,19,20,21,22,23';
}
$output_widgets = '';
$order_widgets_array = explode(',', $order_widgets);
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

$output_system_status_widget = '';
//if user is at least manager
if ($user['role'] < 3){
    $output_system_status_widget = '
    <div class="mt-3 card widget" style="aspect-ratio: unset;" id="system_status_widget">
        <div class="card-body">
            <span class="placeholder-glow d-flex align-items-center gap-2">
                <span class="placeholder rounded-circle" style="width:1.25rem;height:1.25rem;"></span>
                <span class="placeholder rounded-circle" style="width:1.25rem;height:1.25rem;"></span>
                <span class="placeholder rounded-circle" style="width:1.25rem;height:1.25rem;"></span>
                <span class="placeholder rounded-circle" style="width:1.25rem;height:1.25rem;"></span>
                <span class="placeholder rounded-circle" style="width:1.25rem;height:1.25rem;"></span>
                <span class="placeholder rounded-circle" style="width:1.25rem;height:1.25rem;"></span>
                <span class="vr mx-1"></span>
                <span class="placeholder rounded" style="width:2.5rem;height:1rem;"></span>
            </span>
        </div>
    </div>
    <script>
        var statuspopover = new bootstrap.Popover(document.body, {
          selector: \'.status-popover\',
          trigger: "hover"
        });
    </script>';
}


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
            <div class="row mb-3 justify-content-center justify-content-md-between align-items-center">
                <div class="col-auto col-md-auto order-1 order-md-0">
                    ' . $output_system_status_widget . '
                </div>
                <div class="col-12 col-md-auto order-0 order-md-1 text-center d-flex justify-content-center align-items-center">
                    ' . $output_changelog_button . '
                    ' . $output_widget_control_buttons . '
                </div>
            </div>
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
                    if (["4","6","7","8","10","11","12","13","16","17","19"].includes(widget_id)) {
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
