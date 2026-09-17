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
                // Eğer hedef yoksa veya içerik farklıysa üzerine yaz
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

        log_activity("Pre-upgrade preparation completed and verified", $_SESSION['sessionusername']);
    } catch (Exception $e) {
        log_activity("Pre-upgrade preparation failed: " . $e->getMessage(), $_SESSION['sessionusername']);
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
$software_update_available = software_update_check();
// if a software update check was just completed, then set constant to that value
if (isset($software_update_available)) {
    if (!defined('SOFTWARE_UPDATE_AVAILABLE')) {
        if ($software_update_available) {
            define('SOFTWARE_UPDATE_AVAILABLE', TRUE);
        } else {
            define('SOFTWARE_UPDATE_AVAILABLE', FALSE);
        }
    }
}


include_once('liveform.class.php');
$liveform = new liveform('software_update');

if(!function_exists('curl_init')){
	$liveform->mark_error('Update',lang('Software update check could not communicate with the software update server, because cURL is not installed, so it is not known if there is a software update available.'));
}

$request = array();
$request['hostname'] = HOSTNAME_SETTING;
$request['url'] = URL_SCHEME . HOSTNAME_SETTING . PATH;
$request['version'] = VERSION;
$request['edition'] = EDITION;
$request['uname'] = function_exists('php_uname') ? php_uname() : PHP_OS; // disable_functions on some hosts
$request['os'] = PHP_OS;
$request['web_server'] = $_SERVER['SERVER_SOFTWARE'];
$request['php_version'] = phpversion();
$request['mysql_version'] = db("SELECT VERSION()");
$request['installer'] = INSTALLER;
$request['private_label'] = PRIVATE_LABEL;
$data = encode_json($request);
$API = '59593DS72233483322T669223344';
// Beta sites ask their own question; see pg_update_channel().
$REQUEST = function_exists('pg_update_request_key') ? pg_update_request_key() : 'latest_version';

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'https://www.kodpen.com/api2?API='.$API.'&REQUEST='.$REQUEST);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
// Identify this installation. Sent with no User-Agent, a licence or
// update request looks like an anonymous client to the receiving
// server's own firewall and gets rejected.
curl_setopt($ch, CURLOPT_USERAGENT, function_exists('pinegrap_user_agent') ? pinegrap_user_agent() : 'Pinegrap');
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 0);
// Verify the certificate. See pg_curl_tls() for why this matters most
// on the update and licence channel.
pg_curl_tls($ch);
curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST,  2);
curl_setopt($ch, CURLOPT_FORBID_REUSE, true);
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_HTTPHEADER, array(
    'Content-Type: application/json',
    'Content-Length: ' . strlen($data)));

// if there is a proxy address, then send cURL request through proxy
if (PROXY_ADDRESS != '') {
    curl_setopt($ch, CURLOPT_HTTPPROXYTUNNEL, true);
    curl_setopt($ch, CURLOPT_PROXYTYPE, CURLPROXY_HTTP);
    curl_setopt($ch, CURLOPT_PROXY, PROXY_ADDRESS);
}

$response = curl_exec($ch);
$curl_errno = curl_errno($ch);
$curl_error = curl_error($ch);
curl_close($ch);

if ($response === false) {
    log_activity(lang(array('string'=>'software update check could not communicate with the software update server, so it is not known if there is a software update available. cURL Error Number: {var:1}. cURL Error Message: {var:2}.','vars'=>array($curl_errno,$curl_error) )) );
    
    $query = "DELETE FROM notifications WHERE action = 'software_update'";
    $result = mysqli_query(db::$con, $query) or output_error(lang('Query failed.'));

	include_once('liveform.class.php');
	$liveform = new liveform('settings');
	$liveform->mark_error('update', lang(array('string'=>'software update check could not communicate with the software update server, so it is not known if there is a software update available. cURL Error Number: {var:1}. cURL Error Message: {var:2}.','vars'=>array($curl_errno,$curl_error) )));
	header('Location: ' . URL_SCHEME . $_SERVER['HTTP_HOST'] . PATH . SOFTWARE_DIRECTORY . '/' . pg_settings_return_url('general', 'pgset-channel'));
	exit();
}

$response = decode_json($response);

if (!isset($response['version'])) {
    log_activity(lang('software update check received an invalid response from the software update server, so it is not known if there is a software update available'), $_SESSION['sessionusername']);
	
    $query = "DELETE FROM notifications WHERE action = 'software_update'";
    $result = mysqli_query(db::$con, $query) or output_error(lang('Query failed.'));

    include_once('liveform.class.php');
	$liveform = new liveform('settings');
	$liveform->mark_error('update', lang('software update check received an invalid response from the software update server, so it is not known if there is a software update available') );
	header('Location: ' . URL_SCHEME . $_SERVER['HTTP_HOST'] . PATH . SOFTWARE_DIRECTORY . '/' . pg_settings_return_url('general', 'pgset-channel'));
	exit();
}

// If the software update check is not disabled in the config.php file,
// then continue to determine if there is a software update.
if (
    (defined('SOFTWARE_UPDATE_CHECK') == FALSE)
    || (SOFTWARE_UPDATE_CHECK == TRUE)
) {
    // figure out if new version is greater than old version
    
    $new_version = trim($response['version']);
    $new_version_parts = explode('.', $new_version);
    
    $old_version = VERSION;
    $old_version_parts = explode('.', $old_version);
    
    // assume that new version is not greater than old version, until we find out otherwise
    $new_version_is_greater_than_old_version = FALSE;

    // if the major number of the new version is greater than the major number of the old version,
    // then the new version is greater than the old version
    if ($new_version_parts[0] > $old_version_parts[0]) {
        $new_version_is_greater_than_old_version = TRUE;
        
    // else if the major number of the new version is equal to the major number of the old version,
    // then continue to check
    } elseif ($new_version_parts[0] == $old_version_parts[0]) {
        // if the minor number of the new version is greater than the minor number of the old version,
        // then the new version is greater than the old version
        if ($new_version_parts[1] > $old_version_parts[1]) {
            $new_version_is_greater_than_old_version = TRUE;
            
        // else if the minor number of the new version is equal to the minor number of the old version,
        // then continue to check
        } elseif ($new_version_parts[1] == $old_version_parts[1]) {
            // if the maintenance number of the new version is greater than the maintenance number of the old version,
            // then the new version is greater than the old version
            if ($new_version_parts[2] > $old_version_parts[2]) {
                $new_version_is_greater_than_old_version = TRUE;
            }
        }
    }

    // assume that there is not an available software update until we find out otherwise
    $software_update_available = 0;
    
    // if the new version is greater than the old version, then there is an available software update
    if ($new_version_is_greater_than_old_version == TRUE) {
        $software_update_available = 1;
    }

}
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

// Folders the web server cannot write into. The replace step opens the package
// on the tree as the web server, and a folder that refuses keeps its old files
// through every extraction - the site then runs new code on old files. So the
// folders are checked here, before anything is downloaded, the same way the
// System Status card checks them: while one refuses, the Update button stays
// off and a button beside the list opens them (0777 folders, 0666 files, see
// pg_write_permission_repair()). Once they are open the download and the
// extraction run exactly as before.
$output_permissions_block = '';

$update_blocked = false;

if (function_exists('pg_write_permission_scan')) {

    $permissions = pg_write_permission_scan();

    if ($permissions['directories_count'] > 0) {

        $update_blocked = true;

        $permissions_rows = '';

        foreach (array_slice($permissions['directories'], 0, 12, true) as $permissions_path => $permissions_mode) {
            $permissions_rows .= '<li><code>' . h($permissions_path) . '/</code> <span class="text-body-secondary">' . h($permissions_mode) . '</span></li>';
        }

        if ($permissions['directories_count'] > 12) {
            $permissions_rows .= '<li class="text-body-secondary">' . h(lang(array('string' => 'and {var:1} more', 'vars' => number_format($permissions['directories_count'] - 12)))) . '</li>';
        }

        // The repair changes who may write into the software directory, so it
        // is an administrator's button; a manager sees the list and the name of
        // who to ask.
        if ((int) $user['role'] === 0) {
            $permissions_action = '
                <button type="button" class="btn btn-danger" id="write_permissions_fix"
                        data-busy-label="' . h(lang('Fixing')) . '"
                        data-idle-label="' . h(lang('Set the file permissions')) . '"
                        data-failed-label="' . h(lang('The permissions could not be changed.')) . '">
                    <i class="bi bi-wrench-adjustable me-1"></i><span id="write_permissions_fix_state">' . h(lang('Set the file permissions')) . '</span>
                </button>
                <span class="form-text d-block mt-2">' . h(lang('Sets these folders to 0777 and the files in them that refuse to 0666, so that both the web server and your FTP or file manager user can replace them. Folders that belong to another system user cannot be changed from here and are listed afterwards.')) . '</span>
                <div class="form-text mt-2 d-none" id="write_permissions_fix_result"></div>';
        } else {
            $permissions_action = '<span class="form-text d-block mt-2">' . h(lang('An administrator can open them from this screen, or set them writable over FTP.')) . '</span>';
        }

        $output_permissions_block = '
        <div class="col-12 col-md-8 offset-md-2">
            <div class="alert alert-danger">
                <p class="form-text mb-2"><i class="bi bi-folder-x me-1"></i>' . h(lang(array(
                    'string' => 'The web server cannot write into {var:1} folder(s) of the software. The update cannot add or replace files there, so it does not start until they are opened:',
                    'vars' => number_format($permissions['directories_count'])
                ))) . '</p>
                <ul class="mb-2 small">' . $permissions_rows . '</ul>
                ' . $permissions_action . '
            </div>
        </div>';

    }

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
    function update(){
        $status = "";
        var update_btn = $("#update");
        var Progress = $(".progress .progress-bar");
        var LogBox = $(".logbox");
        LogBox.empty();
        update_btn.html("<span class=\'spinner-border spinner-border-sm\'></span> ' . lang('Updating') . '...").addClass("disabled").removeClass("ready");
        Progress.addClass("progress-bar-animated").removeClass("bg-danger").attr("style","width:20%");
        LogBox.html("' . lang('Starting') . '...");
        // Use AJAX to get various card info.
        $.ajax({
            contentType: "application/json",
            url: "api.php",
            data: JSON.stringify({
                action: "software_update",
                token:software_token ,
                step: "check"
            }),
            type: "POST",
            success: function(response) {
                // Check the values in console
                $status = response.status;
                if($status == "success"){
                    Progress.attr("style","width:40%");
                    LogBox.html(response.message);
                    $.ajax({
                        contentType: "application/json",
                        url: "api.php",
                        data: JSON.stringify({
                            action: "software_update",
                            token:software_token ,
                            step: "download"
                        }),
                        type: "POST",
                        success: function(response) {
                            $status = response.status;
                            if($status == "success"){
                                Progress.attr("style","width:70%");
                                LogBox.html(response.message);
                                $.ajax({
                                    contentType: "application/json",
                                    url: "api.php",
                                    data: JSON.stringify({
                                        action: "software_update",
                                        token:software_token ,
                                        step: "replace"
                                    }),
                                    type: "POST",
                                    success: function(response) {
                                        $status = response.status;
                                        if($status == "success"){
                                            Progress.attr("style","width:100%");
                                            LogBox.html(response.message);
                                            window.setTimeout(function(){
                                                window.location.replace("?mode=autoupgrade");
                                            }, 3000);
                                            
                                        }else{

                                            Progress.addClass("bg-danger").removeClass("progress-bar-animated").attr("style","width:100%");
                                            LogBox.html(response.message);
                                            update_btn.text("' . lang('Retry Update') . '").removeClass("disabled").addClass("ready");
                                        }
                                    }
                                });
                            }else{
                                Progress.addClass("bg-danger").removeClass("progress-bar-animated").attr("style","width:100%");
                                LogBox.html(response.message);
                                update_btn.text("' . lang('Retry Update') . '").removeClass("disabled").addClass("ready");
                            }
                        }
                    });
                }else{
                    Progress.addClass("bg-danger").removeClass("progress-bar-animated").attr("style","width:100%");
                    LogBox.html(response.message);
                    update_btn.text("' . lang('Retry Update') . '").removeClass("disabled").addClass("ready");
                }
            }
        });
    }
    $(function(){
        $("#update").click(function(){
            if ($(this).hasClass("ready")) {
                update();
            }
        });

        // Opening the folders the web server cannot write into, then reading
        // the screen again: the list and the Update button are rendered from
        // the scan, so a reload is what turns the button on.
        $("#write_permissions_fix").click(function(){
            var button = $(this),
                result = $("#write_permissions_fix_result");
            if (button.prop("disabled")) {
                return;
            }
            button.prop("disabled", true);
            $("#write_permissions_fix_state").text(button.attr("data-busy-label"));
            $.ajax({
                contentType: "application/json",
                url: "api.php",
                type: "POST",
                data: JSON.stringify({
                    action: "write_permissions_repair",
                    token: software_token
                }),
                success: function(response) {
                    result.text(response.message || button.attr("data-failed-label")).removeClass("d-none");
                    $("#write_permissions_fix_state").text(button.attr("data-idle-label"));
                    if (response.status == "success") {
                        window.setTimeout(function(){
                            window.location.reload();
                        }, 1500);
                    } else {
                        button.prop("disabled", false);
                    }
                },
                error: function() {
                    result.text(button.attr("data-failed-label")).removeClass("d-none");
                    $("#write_permissions_fix_state").text(button.attr("data-idle-label"));
                    button.prop("disabled", false);
                }
            });
        });
    });
</script>
    <div class="row">
      <div class="col-12">
        ' . $liveform->output_errors() . '
        ' . $liveform->get_warnings() . '
        ' . $liveform->output_notices() . '
        ' . $output_permissions_block . '
        <div class="col-12 col-md-8 offset-md-2">
			<div class="card my-5 border-4">
				<div class="card-body">
					<h4 class="text-success text-center"><span class="material-icons" style="line-height:1em;font-size:4em;">browser_updated</span><br/>' . lang('A new Update has been found!') . '</h4>
                    <div class="text-center"><span class="text-secondary h4">'. $old_version . '</span> <span class="material-icons h2">arrow_right_alt</span> <span class="text-primary fw-bold h2 text-success">'. $new_version . '</span></div>
                    
                    <div class="progress">
                      <div class="progress-bar progress-bar-striped  text-start" role="progressbar" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100" style="width: 0%"><span class="logbox ms-1"></span></div>
                    </div>
				</div>
				<div class="card-footer">
					<div class="text-center">
                    <a id="update" class="btn btn-light ' . ($update_blocked ? 'disabled' : 'ready') . '" href="#!"' . ($update_blocked ? ' aria-disabled="true" title="' . h(lang('Open the folders listed above first.')) . '"' : '') . '><span class="me-1 material-icons">sync</span>' . lang('Update') . '</a></div>
				</div>
                
			</div>
		</div>
        <div class="col-12 col-md-8 offset-md-2">
            <div class="alert alert-warning">
                <p class="form-text">' . lang('New files received from the update service will be replaced with software files. If changes have been made specifically for you, contact your server administrator before updating.') . '</p>
            </div>
        </div>

        
</main>
' . output_footer();
$liveform->remove_form();

?> 