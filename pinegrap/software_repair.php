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
// Relative, so the upgrade screen opens with the scheme and host the visitor
// used. URL_SCHEME is the site's setting: behind a proxy that ends TLS it may
// say http while the panel is served over https, and a redirect to that
// address left the secure origin (a request that followed it from a script
// was refused as mixed content). go() below redirects the same way.
$software_upgrade_url = PATH . SOFTWARE_DIRECTORY . '/install/index.php?automated_upgrade=true';

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
                            <li class="list-group-item d-flex gap-3 align-items-start">
                                <i class="bi ' . ($ok ? 'bi-check-circle-fill fg-success' : 'bi-x-circle-fill fg-danger') . ' mt-1" aria-hidden="true"></i>
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
    ? $repair_row(false, lang('Some folders of the software are not writable.'), '<div class="mt-3">' . $repair_permissions['html'] . '</div>')
    : $repair_row(true, lang('The web server can write into every folder of the software.'));

$repair_rows .= $repair_row(true, lang('This site is not hosted on the platform; its files are its own to replace.'));

$repair_warning = '
                    <div class="alert theme-warning">
                        <div class="fw-semibold mb-3"><i class="bi bi-shield-exclamation me-1" aria-hidden="true"></i>' . h(lang('Before you repair')) . '</div>
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
                    <p class="small fg-secondary mb-0">' . h(lang('This version has nothing to repeat.')) . '</p>';

} else {

    $rerun_options = array();

    foreach (array_reverse($rerun_choices) as $rerun_choice) {
        $rerun_count = $rerun_position - array_search($rerun_choice, $rerun_versions, true);
        $rerun_options[lang(array('string' => '{var:1} — runs {var:2} version(s) again', 'vars' => array($rerun_choice, $rerun_count)))] = $rerun_choice;
    }

    $rerun_body = ($rerun_install_folder ? '' : '
                    <div class="alert theme-warning">' . h(lang('The install folder is not on disk, so the upgrade steps cannot run. Repair Software puts it back.')) . '</div>') . '
                    <form id="rerun_form" action="software_repair.php" method="post" class="disable_shortcut">
                        ' . get_token_field() . '
                        <input type="hidden" name="action" value="rerun_upgrades">
                        <label for="version" class="form-label">' . h(lang('Run the steps again from')) . '</label>
                        <div class="d-flex flex-wrap gap-3 align-items-start">
                            <div class="pg-f-md">' . $liveform->output_field(array('type' => 'select', 'name' => 'version', 'id' => 'version', 'class' => 'form-control', 'options' => $rerun_options)) . '</div>
                            <button type="submit" class="btn btn-outline theme-warning"' . ($rerun_install_folder ? '' : ' disabled') . '>
                                <i class="bi bi-arrow-counterclockwise me-1" aria-hidden="true"></i>' . h(lang('Run the steps again')) . '
                            </button>
                        </div>
                    </form>';
}

// -- Roll back an update -------------------------------------------------------

// The archives an update took before it replaced the files
// (pg_update_backup_create()). Only one this site recorded can be rolled back
// to; one that only sits in the folder - uploaded, copied in - is listed, but
// its button stays off and the reason is said.
$rollback_backups = pg_update_backup_list();

if (!$rollback_backups) {

    $rollback_body = '
                    <p class="small fg-secondary mb-0">' . h(lang('No backup taken before an update is in the backup folder yet. The next update writes one, unless it is switched off under Site Settings, General, Update Channel.')) . '</p>';

} else {

    $rollback_rows = '';

    foreach ($rollback_backups as $rollback_backup) {

        $rollback_rows .= '
                        <li class="list-group-item d-flex flex-wrap gap-3 align-items-center">
                            <div class="flex-grow-1">
                                <div class="fw-semibold">' . h(lang(array('string' => 'Version {var:1}', 'vars' => array($rollback_backup['version'])))) . ' <span class="fg-secondary fw-normal small">· ' . h(date('d.m.Y H:i', (int) $rollback_backup['created'])) . ' · ' . h(convert_bytes_to_string($rollback_backup['size'], 1)) . '</span></div>
                                <div class="small fg-secondary text-break">' . h($rollback_backup['name']) . ($rollback_backup['recorded'] ? '' : ' · ' . h(lang('not made by an update on this site'))) . '</div>
                            </div>
                            <button type="button" class="btn btn-sm btn-outline theme-warning pg-rollback" data-name="' . h($rollback_backup['name']) . '" data-version="' . h($rollback_backup['version']) . '"' . ($rollback_backup['recorded'] ? '' : ' disabled data-unrecorded="1"') . '>
                                <i class="bi bi-arrow-counterclockwise me-1" aria-hidden="true"></i>' . h(lang('Roll back')) . '
                            </button>
                        </li>';
    }

    $rollback_body = '
                    <ul class="list-group mb-5">' . $rollback_rows . '
                    </ul>
                    <div class="alert d-none mb-0" id="rollback_result" role="status"></div>';
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

            <div class="row g-5 mb-5">
                <div class="col-12 xl:col-6">
                    <div class="card h-100">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <span class="text-uppercase h5 fg-primary fw-bold mb-0">' . h(lang('Repair Software')) . '</span>
                            <a class="btn btn-sm btn-outline theme-secondary" href="backups.php"><i class="bi bi-archive me-1" aria-hidden="true"></i>' . h(lang('Backup Manager')) . '</a>
                        </div>
                        <div class="card-body">' . $repair_warning . '
                            <ul class="list-group mb-5">' . $repair_rows . '
                            </ul>
                            <div class="progress mb-5">
                                <div class="progress-bar progress-bar-striped text-start" role="progressbar" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100" style="width: 0%"><span class="logbox ms-1"></span></div>
                            </div>
                            <button type="button" id="repair" class="btn btn-outline theme-warning"' . ($repair_blocked ? ' disabled' : '') . '>
                                <i class="bi bi-bandaid me-1" aria-hidden="true"></i><span id="repair_label">' . h(lang('Repair Software')) . '</span>
                            </button>
                        </div>
                    </div>
                </div>
                <div class="col-12 xl:col-6">
                    <div class="card h-100" id="rerun" style="scroll-margin-top: 5rem;">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <span class="text-uppercase h5 fg-primary fw-bold mb-0">' . h(lang('Run the database upgrade steps again')) . '</span>
                            <a class="btn btn-sm btn-outline theme-secondary" href="backups.php"><i class="bi bi-archive me-1" aria-hidden="true"></i>' . h(lang('Backup Manager')) . '</a>
                        </div>
                        <div class="card-body">
                            <p>' . h(lang('Writes the chosen version into the database as the installed one and opens the upgrade screen, which applies every version after it again. Each step asks before it changes anything, so a step that already ran is skipped in a moment; a step that was missed — an upgrade cut short, a table restored from an older backup — is done now.')) . '</p>
                            <p>' . h(lang('Versions before 2026 cannot be repeated; their steps were written without those checks.')) . '</p>
                            <p>' . h(lang('The upgrade screen opens and starts by itself, one version per request. Take a database backup before you press the button.')) . '</p>' . $rerun_body . '
                        </div>
                    </div>
                </div>
            </div>

            <div class="row g-5 mb-5">
                <div class="col-12">
                    <div class="card" id="rollback" style="scroll-margin-top: 5rem;">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <span class="text-uppercase h5 fg-primary fw-bold mb-0">' . h(lang('Roll back an update')) . '</span>
                            <a class="btn btn-sm btn-outline theme-secondary" href="backups.php"><i class="bi bi-archive me-1" aria-hidden="true"></i>' . h(lang('Backup Manager')) . '</a>
                        </div>
                        <div class="card-body">
                            <p>' . h(lang('Before an update or a repair replaces the software files, it backs up the files it replaces and the database. Rolling back writes those files back, deletes the files the update added and records the older version as the installed one. The database is not changed: what the newer upgrade steps added stays and the older version does not use it; the next update runs those steps again.')) . '</p>
                            <p>' . h(lang('To put the database back as well, restore the same archive on the installation screen afterwards. Everything written to the site since the update - orders, form entries, changes - is lost then.')) . ' <a href="' . h(PATH . SOFTWARE_DIRECTORY . '/install/') . '">' . h(lang('Installation screen')) . '</a></p>' . $rollback_body . '
                        </div>
                    </div>
                </div>
            </div>

            <p class="small fg-secondary mb-12">' . h(lang('The upgrade screen writes what it did; the activity log keeps a line for each repair, each rerun and each rollback.')) . '</p>
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

        // The update\'s four steps with repair set; each starts when the one
        // before it answered with success. The backup step writes the files
        // the package replaces and the database to a pre-update archive, or
        // answers at once when that is switched off.
        var steps = [
            { step: "check", width: "30%" },
            { step: "download", width: "55%" },
            { step: "backup", width: "80%" },
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

        // A rollback writes code over the software: asked first, one at a
        // time, and the page reloads on success so the list and the version
        // shown are the ones now on disk.
        $(".pg-rollback").click(function(){
            var button = $(this);
            var result = $("#rollback_result");
            var question = "' . escape_javascript(lang('Write the software files of version {var:1} back over the current ones? The database is not changed.')) . '".replace("{var:1}", button.attr("data-version") || "");

            if (button.prop("disabled") || !window.confirm(question)) {
                return;
            }

            $(".pg-rollback").prop("disabled", true);
            result.addClass("d-none");

            $.ajax({
                contentType: "application/json",
                url: "api.php",
                type: "POST",
                data: JSON.stringify({
                    action: "software_rollback",
                    token: software_token,
                    name: button.attr("data-name")
                }),
                success: function(response) {
                    var ok = response && (response.status == "success");
                    result.removeClass("d-none theme-success theme-danger").addClass(ok ? "theme-success" : "theme-danger")
                        .text((response && response.message) ? response.message : "' . escape_javascript(lang('An error occurred.')) . '");
                    if (ok) {
                        window.setTimeout(function(){
                            window.location.reload();
                        }, 4000);
                    } else {
                        $(".pg-rollback").not("[data-unrecorded]").prop("disabled", false);
                    }
                },
                error: function() {
                    result.removeClass("d-none theme-success").addClass("theme-danger").text("' . escape_javascript(lang('An error occurred.')) . '");
                    $(".pg-rollback").not("[data-unrecorded]").prop("disabled", false);
                }
            });
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
