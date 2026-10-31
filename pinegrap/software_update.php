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

// we lock the update notification system, we don't need it anymore
// If this page generates an update notification, the user sees the update notification again from the welcome screen that he/she accesses afterward.
define('SOFTWARE_UPDATE_CHECK_LOCKED', true);


include('init.php');
$user = validate_user();
// Validate the users access
validate_area_access($user, 'manager');

// A hosted site's code is replaced by the platform for every site on the
// account at once, never from one site's control panel.
if (pg_hosted()) {
    output_error(lang('Software updates are managed by the hosting platform.'));
}


// --- PRE-UPGRADE PREPARATION ---
// Only run if current VERSION is 2026
if (defined('VERSION') && VERSION === '2026') {
    set_time_limit(300);

    function copy_and_verify($src, $dst) {
        if (!is_dir($src)) return false;
        if (!is_dir($dst)) {
            mkdir($dst, 0755, true);
        }
        $dir = opendir($src);
        while (($file = readdir($dir)) !== false) {
            if ($file == '.' || $file == '..') continue;
            $srcPath = $src . '/' . $file;
            $dstPath = $dst . '/' . $file;
            if (is_dir($srcPath)) {
                copy_and_verify($srcPath, $dstPath);
            } else {
                // Overwrite when the target is missing or its content differs
                if (!file_exists($dstPath) || md5_file($srcPath) !== md5_file($dstPath)) {
                    copy($srcPath, $dstPath);
                }
            }
        }
        closedir($dir);
        return true;
    }

    function copy_file_and_verify($srcFile, $dstFile) {
        if (!file_exists($srcFile)) return false;
        if (!file_exists($dstFile) || md5_file($srcFile) !== md5_file($dstFile)) {
            copy($srcFile, $dstFile);
        }
        return true;
    }

    try {
        $baseDir = dirname(__FILE__);
        $dataDir = $baseDir . '/data';

        if (!is_dir($dataDir)) {
            mkdir($dataDir, 0755, true);
        }

        // 1. Backups
        copy_and_verify($baseDir . '/install/backups', $dataDir . '/backups');

        // 2. Config
        copy_file_and_verify($baseDir . '/config/config.php', $dataDir . '/config.php');

        // 3. Files
        copy_and_verify($baseDir . '/files', $dataDir . '/files');

        // 4. Layouts
        copy_and_verify($baseDir . '/layouts', $dataDir . '/layouts');

        log_activity(lang('Pre-upgrade preparation completed and verified'), $_SESSION['sessionusername']);
    } catch (Exception $e) {
        log_activity(lang(array('string' => 'Pre-upgrade preparation failed: {var:1}', 'vars' => array($e->getMessage()))), $_SESSION['sessionusername']);
        $liveform->remove_form();
	    include_once('liveform.class.php');
	    $liveform = new liveform('settings');
	    $liveform->add_notice("Pre-upgrade preparation failed: " . $e->getMessage());
	    header('Location: ' . URL_SCHEME . $_SERVER['HTTP_HOST'] . PATH . SOFTWARE_DIRECTORY . '/' . pg_settings_return_url('general', 'pgset-channel'));
	exit();

    }
}
// --- END PRE-UPGRADE PREPARATION ---


$mode = isset($_GET['mode']) ? ($_GET['mode'] ?? '') : null;
// User redirect automatically after software update success
if ($mode === 'autoupgrade') {
    $liveform_welcome = new liveform('welcome');

    log_activity(lang('Software Updated Successfull'), $_SESSION['sessionusername']);

    // Add notice to liveform
    $liveform_welcome->add_notice(lang('Software Updated Successfull'));

    // Update database to mark update as completed
    db("UPDATE config SET software_update_available = 0");

    // Redirect user to install page
    header('Location: ' . URL_SCHEME . $_SERVER['HTTP_HOST'] . PATH . SOFTWARE_DIRECTORY . '/install/index.php?automated_upgrade=true');
    exit();
}

require(dirname(__FILE__) . '/software_update_check.php');

// One request to the update server. The check function already talks to it,
// so the screen reads the decoded response from the same call instead of
// repeating the request and the version comparison.
$software_update_check = software_update_check(true);
$software_update_available = $software_update_check['available'];
$response = $software_update_check['response'];

// if a software update check was just completed, then set constant to that value
if (!defined('SOFTWARE_UPDATE_AVAILABLE')) {
    if ($software_update_available) {
        define('SOFTWARE_UPDATE_AVAILABLE', TRUE);
    } else {
        define('SOFTWARE_UPDATE_AVAILABLE', FALSE);
    }
}


include_once('liveform.class.php');
$liveform = new liveform('software_update');

if ($software_update_check['error'] === 'curl_missing') {
    $liveform = new liveform('settings');
    $liveform->mark_error('update', lang('Software update check could not communicate with the software update server, because cURL is not installed, so it is not known if there is a software update available.'));
    header('Location: ' . URL_SCHEME . $_SERVER['HTTP_HOST'] . PATH . SOFTWARE_DIRECTORY . '/' . pg_settings_return_url('general', 'pgset-channel'));
    exit();
}

if ($software_update_check['error'] === 'curl_error') {
    $curl_errno = $software_update_check['curl_errno'];
    $curl_error = $software_update_check['curl_error'];
    log_activity(lang(array('string'=>'software update check could not communicate with the software update server, so it is not known if there is a software update available. cURL Error Number: {var:1}. cURL Error Message: {var:2}.','vars'=>array($curl_errno,$curl_error) )) );

	$liveform = new liveform('settings');
	$liveform->mark_error('update', lang(array('string'=>'software update check could not communicate with the software update server, so it is not known if there is a software update available. cURL Error Number: {var:1}. cURL Error Message: {var:2}.','vars'=>array($curl_errno,$curl_error) )));
	header('Location: ' . URL_SCHEME . $_SERVER['HTTP_HOST'] . PATH . SOFTWARE_DIRECTORY . '/' . pg_settings_return_url('general', 'pgset-channel'));
	exit();
}

if ($software_update_check['error'] === 'invalid_response') {
    log_activity(lang('software update check received an invalid response from the software update server, so it is not known if there is a software update available'), $_SESSION['sessionusername']);

	$liveform = new liveform('settings');
	$liveform->mark_error('update', lang('software update check received an invalid response from the software update server, so it is not known if there is a software update available') );
	header('Location: ' . URL_SCHEME . $_SERVER['HTTP_HOST'] . PATH . SOFTWARE_DIRECTORY . '/' . pg_settings_return_url('general', 'pgset-channel'));
	exit();
}

// The Kodpen API's answer (includes/fn/vendor_api.php): a release blocked
// behind a stepping stone, a release of another channel and an access block
// are errors with their own reason; an answer that offers nothing takes the
// path below.
if (isset($software_update_check['decision'])) {
    $decision = $software_update_check['decision'];

    if (in_array($decision['outcome'], array('blocked', 'refuse'), true) || ($decision['reason'] === 'access_blocked')) {
        $liveform->remove_form();

        $liveform = new liveform('settings');
        $liveform->mark_error('update', pg_vendor_release_message($decision, VERSION));
        header('Location: ' . URL_SCHEME . $_SERVER['HTTP_HOST'] . PATH . SOFTWARE_DIRECTORY . '/' . pg_settings_return_url('general', 'pgset-channel'));
        exit();
    }
}

// No response when update checks are off and the Kodpen API was not asked.
$new_version = is_array($response) ? trim((string) $response['version']) : '';
$old_version = VERSION;

if($software_update_available == 0){
	$liveform->remove_form();
	include_once('liveform.class.php');

    $query = "DELETE FROM notifications WHERE action = 'software_update'";
    $result = mysqli_query(db::$con, $query) or output_error(lang('Query failed.'));

	$liveform = new liveform('settings');
	$liveform->add_notice(lang('There is no update available.'));
	header('Location: ' . URL_SCHEME . $_SERVER['HTTP_HOST'] . PATH . SOFTWARE_DIRECTORY . '/' . pg_settings_return_url('general', 'pgset-channel'));
	exit();
}

// Folders the web server cannot write into: while one refuses, the Update
// button stays off (see pg_update_permissions_block()).
$permissions_block = pg_update_permissions_block($user);

$update_blocked = $permissions_block['blocked'];

$output_permissions_block = '';

if ($permissions_block['blocked']) {
    $output_permissions_block = '
        <div class="col-12 md:col-8 md:offset-2">' . $permissions_block['html'] . '
        </div>';
}

print
pg_page_shell([
        'title'=> lang('Software Updater'),
        'extra classes'=>'setting',
        'icon'=>'setting',
        'heading'=>lang('Software Updater'),
        'heading_description' => lang('Get new files from update server and Update Software.'),
    ]) . '
<main id="content" class="container-fluid">
<script>
    $(function(){
        var update_button = $("#update");
        var progress = $(".progress .progress-bar");
        var log_box = $(".logbox");

        // The four steps of the update; each starts when the one before it
        // answered with success. The backup step writes the files about to be
        // replaced and the database to a pre-update archive first.
        var steps = [
            { step: "check", width: 40 },
            { step: "download", width: 60 },
            { step: "backup", width: 80 },
            { step: "replace", width: 100 }
        ];

        function set_width(width) {
            progress.css("width", width + "%").attr("aria-valuenow", width);
        }

        function fail(message) {
            progress.addClass("bg-danger").removeClass("progress-bar-animated");
            set_width(100);
            log_box.text(message || "' . escape_javascript(lang('An error occurred.')) . '");
            update_button.prop("disabled", false);
        }

        function run(index) {
            $.ajax({
                contentType: "application/json",
                url: "api.php",
                type: "POST",
                data: JSON.stringify({
                    action: "software_update",
                    token: software_token,
                    step: steps[index].step
                }),
                success: function(response) {
                    if (!response || (response.status != "success")) {
                        fail(response ? response.message : "");
                        return;
                    }
                    set_width(steps[index].width);
                    log_box.text(response.message);
                    if (index + 1 < steps.length) {
                        run(index + 1);
                    } else {
                        window.setTimeout(function(){
                            window.location.replace("?mode=autoupgrade");
                        }, 3000);
                    }
                },
                error: function() {
                    fail(steps[index].step == "backup"
                        ? "' . escape_javascript(lang('The backup before the update could not be written. Nothing was changed.')) . '"
                        : "' . escape_javascript(lang('An error occurred.')) . '");
                }
            });
        }

        update_button.click(function(){
            if (update_button.prop("disabled")) {
                return;
            }
            update_button.prop("disabled", true);
            progress.addClass("progress-bar-animated").removeClass("bg-danger");
            set_width(20);
            log_box.text("' . escape_javascript(lang('Starting')) . '...");
            run(0);
        });
    });
</script>
' . pg_update_permissions_script() . '
    <div class="row">
      <div class="col-12">
        ' . $liveform->output_errors() . '
        ' . $liveform->get_warnings() . '
        ' . $liveform->output_notices() . '
        ' . $output_permissions_block . '
        <div class="col-12 md:col-8 md:offset-2">
			<div class="card my-12 border-4">
				<div class="card-body">
					<h4 class="fg-success text-center"><span class="material-icons" style="line-height:1em;font-size:4em;" aria-hidden="true">browser_updated</span><br/>' . lang('A new Update has been found!') . '</h4>
                    <div class="text-center"><span class="fg-secondary h4">'. $old_version . '</span> <span class="material-icons h2" aria-hidden="true">arrow_right_alt</span> <span class="fg-primary fw-bold h2 fg-success">'. $new_version . '</span></div>
                    
                    <div class="progress">
                      <div class="progress-bar progress-bar-striped" role="progressbar" aria-label="' . h(lang('Update')) . '" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100" style="width: 0%"></div>
                    </div>
                    <div class="logbox form-text" role="status"></div>
				</div>
				<div class="card-footer">
					<div class="text-center">
                    <button type="button" id="update" class="btn btn-subtle theme-secondary"' . ($update_blocked ? ' disabled title="' . h(lang('Open the folders listed above first.')) . '"' : '') . '><span class="me-1 material-icons" aria-hidden="true">sync</span>' . lang('Update') . '</button></div>
				</div>
                
			</div>
		</div>
        <div class="col-12 md:col-8 md:offset-2">
            <div class="alert theme-warning">
                <p class="form-text">' . lang('New files received from the update service will be replaced with software files. If changes have been made specifically for you, contact your server administrator before updating.') . '</p>' . (pg_update_backup_enabled() ? '
                <p class="form-text mb-0">' . lang('The files that are replaced and the database are backed up first (data/backups/pre-update-….zip). Repair Software can roll the files back to it.') . '</p>' : '') . '
            </div>
        </div>

        
</main>
' . output_footer();
$liveform->remove_form();
