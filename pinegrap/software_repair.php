<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Repair Software: writes the current release package of this site's channel
 * over every software file, then sends the browser to the upgrade screen. The
 * three steps are the update's own (the software_update panel action with
 * repair set, includes/panel/software.php); they are refused while update
 * checks are off in data/config.php and when the update server offers no
 * version or an older one (pg_update_repair_decision()).
 *
 * The second card runs the database upgrade steps again from a chosen 2026
 * version: config.version is set back to it and the upgrade screen applies
 * every version after it, each step asking before it changes anything.
 *
 * Administrators only: both replace or rerun what the site runs on.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// This screen asks the update server itself; the notification check that
// init.php would start is not needed on top of it.
define('SOFTWARE_UPDATE_CHECK_LOCKED', true);

include('init.php');

if (pg_post_body_discarded()) {
    output_error(lang('The request was too large for this server and arrived empty.'), 413);
}

$user = validate_user();
validate_area_access($user, 'administrator');

// A hosted site's code is replaced by the platform for every site on the
// account at once, never from one site's control panel.
if (pg_hosted()) {
    output_error(lang('Software updates are managed by the hosting platform.'));
}

include_once('liveform.class.php');
$liveform = new liveform('software_repair');

$software_repair_url = PATH . SOFTWARE_DIRECTORY . '/software_repair.php';
$software_upgrade_url = URL_SCHEME . $_SERVER['HTTP_HOST'] . PATH . SOFTWARE_DIRECTORY . '/install/index.php?automated_upgrade=true';

// The replace step succeeded: the files on disk are the package's. The
// version the check step found is in the session; failing that, the VERSION
// of the code now running is the package's own.
if (isset($_GET['mode']) && ($_GET['mode'] === 'done')) {

    $repair_version = (isset($_SESSION['software']['repair']['version']) && is_string($_SESSION['software']['repair']['version']))
        ? $_SESSION['software']['repair']['version']
        : VERSION;

    unset($_SESSION['software']['repair']);

    log_activity(lang(array('string' => 'Software repaired with the {var:1} package from the update server.', 'vars' => array($repair_version))), $_SESSION['sessionusername']);

    db("UPDATE config SET software_update_available = 0");

    // System Status and the file integrity check work from caches of their
    // own; they are to look at the new files, not remember the old ones.
    foreach (array('system_status_cache.json', 'hash_reference_state.json') as $repair_cache) {
        if (file_exists(PG_FUNCTIONS_DIR . '/data/temp/' . $repair_cache)) {
            @unlink(PG_FUNCTIONS_DIR . '/data/temp/' . $repair_cache);
        }
    }

    $liveform_welcome = new liveform('welcome');
    $liveform_welcome->add_notice(lang('Software files repaired.'));

    header('Location: ' . $software_upgrade_url);
    exit();
}

$rerun_choices = pg_upgrade_rerun_choices();

$rerun_install_folder = is_dir(PG_FUNCTIONS_DIR . '/install');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    validate_token_field();

    $rerun_action = (isset($_POST['action']) && is_string($_POST['action'])) ? $_POST['action'] : '';
    $rerun_version = (isset($_POST['version']) && is_string($_POST['version'])) ? $_POST['version'] : '';

    if ($rerun_action !== 'rerun_upgrades') {
        $liveform->add_error(lang('Unknown action.'));
        go($software_repair_url);
    }

    // Only a version of the list goes into config.version: a number the
    // runner does not know would close the upgrade screen altogether.
    if (!in_array($rerun_version, $rerun_choices, true)) {
        $liveform->add_error(lang('That version cannot be chosen.'));
        go($software_repair_url);
    }

    if (!$rerun_install_folder) {
        $liveform->add_error(lang('The install folder is not on disk, so the upgrade steps cannot run. Repair Software puts it back.'));
        go($software_repair_url);
    }

    db("UPDATE config SET version = '" . e($rerun_version) . "'");

    log_activity(lang(array('string' => 'Database upgrade steps will run again from {var:1} (was {var:2}).', 'vars' => array($rerun_version, VERSION))), $_SESSION['sessionusername']);

    header('Location: ' . $software_upgrade_url);
    exit();
}

// -- Repair Software ------------------------------------------------------------

$repair_checks_enabled = !(defined('SOFTWARE_UPDATE_CHECK') && (SOFTWARE_UPDATE_CHECK === false));

$repair_permissions = pg_update_permissions_block($user);

$repair_blocked = (!$repair_checks_enabled || $repair_permissions['blocked']);

$repair_row = function ($ok, $text, $extra = '') {
    return '
                            <li class="list-group-item d-flex gap-2 align-items-start">
                                <i class="bi ' . ($ok ? 'bi-check-circle-fill text-success' : 'bi-x-circle-fill text-danger') . ' mt-1" aria-hidden="true"></i>
                                <div class="flex-grow-1">' . h($text) . $extra . '</div>
                            </li>';
};

$repair_rows = $repair_row(true, lang(array(
    'string' => 'Channel: {var:1}. Installed version: {var:2}.',
    'vars' => array((pg_update_channel() === 'beta') ? lang('Beta') : lang('Stable'), VERSION),
)));

$repair_rows .= $repair_checks_enabled
    ? $repair_row(true, lang('Software update checks are on.'))
    : $repair_row(false, lang('Software update checks are turned off in data/config.php (SOFTWARE_UPDATE_CHECK), and the repair uses the same server. Turn them on to repair.'));

$repair_rows .= $repair_permissions['blocked']
    ? $repair_row(false, lang('Some folders of the software are not writable.'), '<div class="mt-2">' . $repair_permissions['html'] . '</div>')
    : $repair_row(true, lang('The web server can write into every folder of the software.'));

$repair_rows .= $repair_row(true, lang('This site is not hosted on the platform; its files are its own to replace.'));

$repair_warning = '
                    <div class="alert alert-warning">
                        <div class="fw-semibold mb-2"><i class="bi bi-shield-exclamation me-1" aria-hidden="true"></i>' . h(lang('Before you repair')) . '</div>
                        <ul class="mb-0 small">
                            <li>' . h(lang('The package is the current release of this site\'s channel (Stable or Beta), downloaded from kodpen.com over a verified connection.')) . '</li>
                            <li>' . h(lang('It is written over every software file. Changes made to those files by hand are lost; files that are not in the package stay where they are. The data folder and the database are not touched.')) . '</li>
                            <li>' . h(lang('Afterwards the upgrade screen opens and runs any upgrade steps that are due.')) . '</li>
                            <li>' . h(lang('Take a backup first. If File Integrity is red on System Status, this is the button that puts it right.')) . '</li>
                            <li>' . h(lang('This replaces the code the site runs. Use it yourself or let an administrator you trust use it; only administrators can open this screen.')) . '</li>
                        </ul>
                    </div>';

// -- Run the database upgrade steps again ---------------------------------------

$rerun_versions = pg_upgrade_versions_list();
$rerun_position = array_search(VERSION, $rerun_versions, true);

if (count($rerun_choices) == 0) {

    $rerun_body = '
                    <p class="small text-body-secondary mb-0">' . h(lang('This version has nothing to repeat.')) . '</p>';

} else {

    $rerun_options = array();

    foreach (array_reverse($rerun_choices) as $rerun_choice) {
        $rerun_count = $rerun_position - array_search($rerun_choice, $rerun_versions, true);
        $rerun_options[lang(array('string' => '{var:1} — runs {var:2} version(s) again', 'vars' => array($rerun_choice, $rerun_count)))] = $rerun_choice;
    }

    $rerun_body = ($rerun_install_folder ? '' : '
                    <div class="alert alert-warning">' . h(lang('The install folder is not on disk, so the upgrade steps cannot run. Repair Software puts it back.')) . '</div>') . '
                    <form id="rerun_form" action="software_repair.php" method="post" class="disable_shortcut">
                        ' . get_token_field() . '
                        <input type="hidden" name="action" value="rerun_upgrades">
                        <label for="version" class="form-label">' . h(lang('Run the steps again from')) . '</label>
                        <div class="d-flex flex-wrap gap-2 align-items-start">
                            <div class="pg-f-md">' . $liveform->output_field(array('type' => 'select', 'name' => 'version', 'id' => 'version', 'class' => 'form-select', 'options' => $rerun_options)) . '</div>
                            <button type="submit" class="btn btn-outline-warning"' . ($rerun_install_folder ? '' : ' disabled') . '>
                                <i class="bi bi-arrow-counterclockwise me-1" aria-hidden="true"></i>' . h(lang('Run the steps again')) . '
                            </button>
                        </div>
                    </form>';
}

echo pg_page_shell(array(
    'title' => lang('Repair Software'),
    'extra classes' => 'setting',
    'icon' => 'setting',
    'heading' => lang('Repair Software'),
));

echo '
<main id="content" class="container-fluid">
    <div class="row">
        <div class="col-12">
            ' . $liveform->output_errors() . '
            ' . $liveform->output_notices() . '

            <div class="row g-3 mb-3">
                <div class="col-12 col-xl-6">
                    <div class="card h-100">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <span class="text-uppercase h5 text-primary fw-bold mb-0">' . h(lang('Repair Software')) . '</span>
                            <a class="btn btn-sm btn-outline-secondary" href="backups.php"><i class="bi bi-archive me-1" aria-hidden="true"></i>' . h(lang('Backup Manager')) . '</a>
                        </div>
                        <div class="card-body">' . $repair_warning . '
                            <ul class="list-group mb-3">' . $repair_rows . '
                            </ul>
                            <div class="progress mb-3">
                                <div class="progress-bar progress-bar-striped text-start" role="progressbar" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100" style="width: 0%"><span class="logbox ms-1"></span></div>
                            </div>
                            <button type="button" id="repair" class="btn btn-outline-warning"' . ($repair_blocked ? ' disabled' : '') . '>
                                <i class="bi bi-bandaid me-1" aria-hidden="true"></i><span id="repair_label">' . h(lang('Repair Software')) . '</span>
                            </button>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-xl-6">
                    <div class="card h-100" id="rerun" style="scroll-margin-top: 5rem;">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <span class="text-uppercase h5 text-primary fw-bold mb-0">' . h(lang('Run the database upgrade steps again')) . '</span>
                            <a class="btn btn-sm btn-outline-secondary" href="backups.php"><i class="bi bi-archive me-1" aria-hidden="true"></i>' . h(lang('Backup Manager')) . '</a>
                        </div>
                        <div class="card-body">
                            <p>' . h(lang('Writes the chosen version into the database as the installed one and opens the upgrade screen, which applies every version after it again. Each step asks before it changes anything, so a step that already ran is skipped in a moment; a step that was missed — an upgrade cut short, a table restored from an older backup — is done now.')) . '</p>
                            <p>' . h(lang('Versions before 2026 cannot be repeated; their steps were written without those checks.')) . '</p>
                            <p>' . h(lang('The upgrade screen opens and starts by itself, one version per request. Take a database backup before you press the button.')) . '</p>' . $rerun_body . '
                        </div>
                    </div>
                </div>
            </div>

            <p class="small text-body-secondary mb-5">' . h(lang('The upgrade screen writes what it did; the activity log keeps a line for each repair and each rerun.')) . '</p>
        </div>
    </div>
</main>
' . pg_update_permissions_script() . '
<script>
    $(function(){
        var repair_button = $("#repair");
        var repair_label = $("#repair_label");
        var progress = $(".progress .progress-bar");
        var log_box = $(".logbox");

        // The update\'s three steps with repair set; each starts when the one
        // before it answered with success.
        var steps = [
            { step: "check", width: "40%" },
            { step: "download", width: "70%" },
            { step: "replace", width: "100%" }
        ];

        function fail(message) {
            progress.addClass("bg-danger").removeClass("progress-bar-animated").attr("style", "width:100%");
            log_box.text(message || "");
            repair_label.text("' . escape_javascript(lang('Retry')) . '");
            repair_button.prop("disabled", false);
        }

        function run(index) {
            $.ajax({
                contentType: "application/json",
                url: "api.php",
                type: "POST",
                data: JSON.stringify({
                    action: "software_update",
                    token: software_token,
                    step: steps[index].step,
                    repair: true
                }),
                success: function(response) {
                    if (!response || (response.status != "success")) {
                        fail(response ? response.message : "");
                        return;
                    }
                    progress.attr("style", "width:" + steps[index].width);
                    log_box.text(response.message);
                    if (index + 1 < steps.length) {
                        run(index + 1);
                    } else {
                        window.setTimeout(function(){
                            window.location.replace("software_repair.php?mode=done");
                        }, 1500);
                    }
                },
                error: function() {
                    fail("' . escape_javascript(lang('An error occurred.')) . '");
                }
            });
        }

        repair_button.click(function(){
            if (repair_button.prop("disabled")) {
                return;
            }
            if (!window.confirm("' . escape_javascript(lang('Replace every software file with the release package now? Files you changed by hand will be lost. Take a backup first.')) . '")) {
                return;
            }
            repair_button.prop("disabled", true);
            repair_label.text("' . escape_javascript(lang('Repairing')) . '...");
            progress.addClass("progress-bar-animated").removeClass("bg-danger").attr("style", "width:20%");
            log_box.text("' . escape_javascript(lang('Starting')) . '...");
            run(0);
        });

        $("#rerun_form").on("submit", function(event){
            var version = $("#version").val() || "";
            var question = "' . escape_javascript(lang('Set the installed version to {var:1} and run the upgrade steps after it again?')) . '".replace("{var:1}", version);
            if (!window.confirm(question)) {
                event.preventDefault();
            }
        });
    });
</script>
' . output_footer();

$liveform->remove_form();
