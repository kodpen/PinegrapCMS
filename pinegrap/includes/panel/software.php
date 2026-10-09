<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Panel actions - backups and software updates.
 *
 *   software_backup        the backup screen's step-by-step backup (folder,
 *                          database dump, files, archive)
 *   software_update_check  the background update check output_header() starts
 *   software_update        the step-by-step software update (check, download,
 *                          replace)
 *
 * Called by pg_panel_dispatch() (includes/panel/actions.php); see that file
 * for the contract.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// The backup step builds its dump with IMysqldump\Mysqldump.
use Ifsnop\Mysqldump as IMysqldump;

if (!defined('PG_PANEL_ACTIONS')) {
    exit;
}

function pg_panel_software_backup($request, $action)
{

    // A backup writes the whole database out to disk and copies every file
    // beside it.  The action sits in the exemption list at the top of
    // api.php and had nothing of its own in the general gate's place, so the
    // steps ran for whoever could reach the address.  Manager and a valid
    // token is the same reach backups.php asks for at its own door, and
    // now the door the Backups view knocks on asks the same.
    $user = validate_user();
    validate_area_access($user, 'manager');
    validate_token();

    // This feature can take a long time to run for a large site,
    // so increase the allowed execution time for the PHP script.
    ini_set('memory_limit', '512M');
    ini_set('max_execution_time', 500);
    $step = isset($request['step']) ? (string) $request['step'] : '';
    $backup_name = isset($request['backup_name']) ? (string) $request['backup_name'] : '';

    $backup_location = 'data/backups/';

    // The name travels back to the client after every step and returns
    // with the next one, so each step has to treat it as input. It is
    // reduced once, here, to a single folder-name character class: path
    // separators, dots and anything else outside it become underscores,
    // which keeps every step's mkdir, dump, copy and unlink inside the
    // backups directory. The result is stable under a second pass, so the
    // name a step hands back is the name the next step will compute.
    $backup_folder_name = preg_replace('/[^A-Za-z0-9_-]/', '_', basename($backup_name));

    // Only the first step may start without a name; it makes its own. Every
    // later step works on a folder that must already exist under a name.
    if (($backup_folder_name === '') && ($step != 'create_backup_folder')) {
        $response = array(
            'status' => 'error',
            'message' => lang('The backup name is not valid.')
        );
        echo encode_json($response);
        exit();
    }

    switch ($step) {

        case 'create_backup_folder':
            if ($backup_folder_name === '') {
                $hostname_clean = defined('HOSTNAME') ? HOSTNAME : '';
                $backup_name = ($hostname_clean ? $hostname_clean . '_' : '') . date('Y-m-d@H-i');
                $backup_folder_name = preg_replace('/[^A-Za-z0-9_-]/', '_', $backup_name);
            }

            //check if directory is exists
            //if not exist Create directory.
            if (!file_exists($backup_location . $backup_folder_name)) {
                mkdir($backup_location . $backup_folder_name, 0777, true);
            }
            //return success json output
            $response = array(
                'status' => 'success',
                'backup_name' => $backup_folder_name,
                'message' => lang('Site backup folder create successful. Mysql dumb creating, please wait...')
            );
            echo encode_json($response);
            exit();
            break;

        case 'create_mysql_dumb':
            include_once(PG_FUNCTIONS_DIR . '/mysqldump.php');

            //Create mysql dump file named slq.sql and save it in backup directory
            // first backup Mysql because, if there is timeout when file copy mysql important for us. so even timeout to copy files or layouts we have mysql dump anyway.
            try {
                $dump = new IMysqldump\Mysqldump('mysql:host=' . DB_HOST . ';dbname=' . DB_DATABASE . '', '' . DB_USERNAME . '', '' . DB_PASSWORD . '');
                $dump->start($backup_location . $backup_folder_name . '/sql.sql');
            } catch (\Exception $e) {
                $backups_error_message = $e->getMessage();

                //if mysql error and backup folder is empty, delete it.
                if (is_dir($backup_location . $backup_folder_name) && count(glob($backup_location . $backup_folder_name . '/*')) === 0) {
                    rmdir($backup_location . $backup_folder_name);
                }

                log_activity('Creating Mysql Dumb is Failure. Because: ' . h($backups_error_message), $_SESSION['sessionusername']);
                //return error json output
                $response = array(
                    'status' => 'error',
                    'message' => h($backups_error_message)
                );
                echo encode_json($response);
                exit();
            }

            //return success json output
            $response = array(
                'status' => 'success',
                'backup_name' => $backup_folder_name,
                'message' => lang('Mysql dumb created in backup directory successful. Clearing old files in directory, please wait...')
            );
            echo encode_json($response);
            exit();
            //Mysql Backup complete
            break;

        case 'clear_files_and_layouts':
            //Prepare for files and layouts**
            //if files directory not exist Create directory
            if (!file_exists($backup_location . $backup_folder_name . '/files')) {
                mkdir($backup_location . $backup_folder_name . '/files', 0777, true);
            }
            //if layouts directory not exist Create directory
            if (!file_exists($backup_location . $backup_folder_name . '/layouts')) {
                mkdir($backup_location . $backup_folder_name . '/layouts', 0777, true);
            }
            //CLEAR//
            // delete all files from template files directory
            $files = pg_glob_brace($backup_location . $backup_folder_name . '/files/{,.}*'); // get all file names
            foreach ($files as $file) { // iterate files
                if (is_file($file))
                    unlink($file); // delete file
            }
            // delete all files from template layouts directory
            $layouts = pg_glob_brace($backup_location . $backup_folder_name . '/layouts/{,.}*'); // get all layouts names
            foreach ($layouts as $layout) { // iterate layouts files
                if (is_file($layout))
                    unlink($layout); // delete layouts files
            }

            //return success json output
            $response = array(
                'status' => 'success',
                'backup_name' => $backup_folder_name,
                'message' => lang('Files and layouts cleared in backup directory. Copying files, please wait...')
            );
            echo encode_json($response);
            exit();
            break;


        case 'move_files':

            //WRITE//
            // prepare path to template files
            $backup_files_path = $backup_location . $backup_folder_name . '/files/';
            $handle = opendir(FILE_DIRECTORY_PATH);
            // copy files to backup directory
            while (false !== ($file = readdir($handle))) {
                if (($file != '.') && ($file != '..')) {
                    copy(FILE_DIRECTORY_PATH . '/' . $file, $backup_files_path . $file);
                }
            }
            closedir($handle);

            //return success json output
            $response = array(
                'status' => 'success',
                'backup_name' => $backup_folder_name,
                'message' => lang('Files copied to backup directory. Copying layouts, please wait...')
            );
            echo encode_json($response);
            exit();
            break;

        case 'move_layouts':

            //WRITE//
            // prepare path to template layouts
            $backup_layouts_path = $backup_location . $backup_folder_name . '/layouts/';
            $handle = opendir(LAYOUT_DIRECTORY_PATH);
            // copy files to backup directory
            while (false !== ($file = readdir($handle))) {
                if (($file != '.') && ($file != '..')) {
                    copy(LAYOUT_DIRECTORY_PATH . '/' . $file, $backup_layouts_path . $file);
                }
            }
            closedir($handle);

            //return success json output
            $response = array(
                'status' => 'success',
                'backup_name' => $backup_folder_name,
                'message' => lang('Layouts copied to backup directory. Creating .htaccess for security reason, please wait...')
            );
            echo encode_json($response);
            exit();
            break;

        case 'create_htaccess_and_config':
            //create .htaccess file to make directory unaccessable.
            file_put_contents($backup_location . $backup_folder_name . '/.htaccess', 'deny from all');
            //return success json output
            $response = array(
                'status' => 'success',
                'backup_name' => $backup_folder_name,
                'message' => lang('Htaccess create in backup directory successful. Check backup folder create success or not, please wait...')
            );
            echo encode_json($response);
            exit();
            break;

        case 'check':

            if (file_exists($backup_location . $backup_folder_name)) {

                if (file_exists($backup_location . $backup_folder_name . '/sql.sql')) {
                    if (file_exists($backup_location . $backup_folder_name . '/files')) {
                        if (file_exists($backup_location . $backup_folder_name . '/layouts')) {
                            $liveform_backups = new liveform('backups');

                            log_activity("Software Backup (" . $backup_folder_name . ") Success", $_SESSION['sessionusername']);
                            // Add notice to liveform.
                            $liveform_backups->add_notice('Software Backup (' . $backup_folder_name . ') Create Success.');
                            //return success json output
                            $response = array(
                                'status' => 'success',
                                'backup_name' => $backup_folder_name,
                                'message' => lang('Software Backup process Successful. Page will be refresh...')
                            );
                            echo encode_json($response);
                            exit();
                        }
                    }
                }

            }

            //return error json output
            $response = array(
                'status' => 'error',
                'message' => lang('software Backup check has error. backup maybe still created but we cant provide.')
            );
            echo encode_json($response);
            exit();


            break;

        default:
            //return error json output
            $response = array(
                'status' => 'error',
                'message' => lang('software Backup steps error.')
            );
            echo encode_json($response);
            exit();
    }
}

function pg_panel_software_update_check($request, $action)
{
    // Async background check triggered by output_header() JS injection.
    // Runs the daily/periodic software update check without blocking the page load.
    validate_token();
    $user = validate_user();
    $current_timestamp = time();
    if (
        (defined('SOFTWARE_UPDATE_CHECK') == false or SOFTWARE_UPDATE_CHECK == true)
        and ($current_timestamp >= (LAST_SOFTWARE_UPDATE_CHECK_TIMESTAMP + 259200))
    ) {
        require(PG_FUNCTIONS_DIR . '/software_update_check.php');
        software_update_check();
        exit();
    }
}

function pg_panel_software_update($request, $action)
{
    //software update is not software update check.
    //it is action to update software from software_update.php
    //used api because some slow servers connections down, timeout or somethings like this when do this one step.

    // The steps below download a package and unpack it over the codebase.
    // The action sits in the exemption list at the top of api.php, so
    // the general gate does not run for it: ask here for the same thing
    // software_update.php asks at its own door - a signed-in manager with
    // a valid token - before any step is looked at.
    if (!USER_LOGGED_IN) {
        respond(array(
            'status' => 'error',
            'message' => 'Invalid login.'
        ));
    }
    $user = validate_user();
    validate_area_access($user, 'manager');
    validate_token();

    // A hosted site's code is replaced by the platform for every site on
    // the account at once, never by one of them.
    if (pg_hosted()) {
        respond(array(
            'status' => 'error',
            'message' => lang('Software updates are managed by the hosting platform.')
        ));
    }

    // The same three steps run for Repair Software (software_repair.php),
    // which writes the channel's current package over the software whether
    // or not it is newer. That replaces executable code on request, so it is
    // an administrator's, like the screen that starts it.
    $repair = !empty($request['repair']);

    if ($repair && ((int) $user['role'] !== 0)) {
        respond(array(
            'status' => 'error',
            'message' => lang('Access denied.')
        ));
    }

    // This feature can take a long time to run for a large site,
    // so increase the allowed execution time for the PHP script.
    ini_set('max_execution_time', '9999');

    $step = isset($request['step']) ? $request['step'] : '';
    if (!in_array($step, array('check', 'download', 'replace'), true)) {
        respond(array(
            'status' => 'error',
            'message' => 'Invalid step.'
        ));
    }
    switch ($step) {
        case 'check':
            //check if there is really have a software update, also software_update page check but may user open 2 page and update and update again.
            // now if try software update after an update user get error message and update stop.
            // The caller is software_update.php's script, which shows the
            // message of an error answer in its log box and offers a retry.
            if ($repair) {
                // Refused while update checks are off (the repair asks the
                // same server), when the server names no version, and when
                // it offers an older one: pg_update_repair_decision().
                $checks_enabled = !(defined('SOFTWARE_UPDATE_CHECK') && (SOFTWARE_UPDATE_CHECK === false));

                if (!$checks_enabled) {
                    $decision = pg_update_repair_decision('', VERSION, false);
                    respond(array(
                        'status' => 'error',
                        'message' => $decision['message']
                    ));
                }
            }

            $server = pg_update_server_version();

            if ($server['error'] === 'curl_missing') {
                respond(array(
                    'status' => 'error',
                    'message' => lang('Software update check could not communicate with the software update server, because cURL is not installed, so it is not known if there is a software update available.')
                ));
            }

            if ($server['error'] === 'curl_error') {
                log_activity(
                    'software update check could not communicate with the software update server, so it is not known if there is a software update available. cURL Error Number: ' . $server['curl_errno'] . '. cURL Error Message: ' . $server['curl_error'] . '.'
                );
                //return error json output
                $response = array(
                    'status' => 'error',
                    'message' => 'No access to the update server.'
                );
                echo encode_json($response);
                exit();
            }

            if ($server['error'] === 'invalid_response') {
                log_activity('software update check received an invalid response from the software update server, so it is not known if there is a software update available');
                //return error json output
                $response = array(
                    'status' => 'error',
                    'message' => 'No response from the update server.'
                );
                echo encode_json($response);
                exit();

            }

            if ($repair) {
                $decision = pg_update_repair_decision($server['version'], VERSION, true);

                if ($decision['ok']) {
                    // software_repair.php?mode=done names the version in the
                    // activity log.
                    $_SESSION['software']['repair']['version'] = $server['version'];
                }

                respond(array(
                    'status' => $decision['ok'] ? 'success' : 'error',
                    'message' => $decision['message']
                ));
            }

            $response = array('version' => $server['version']);

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
            //there is no software
            if ($software_update_available == 0) {
                //return error json output
                $response = array(
                    'status' => 'error',
                    'message' => 'There is no update available.'
                );
                echo encode_json($response);
                exit();
            }
            //there is software update so we can go step 2:Download the update file.
            //return success json output
            $response = array(
                'status' => 'success',
                'message' => 'Downloading...'
            );
            echo encode_json($response);
            exit();

            break;
        case 'download':
            //Step 2: download update file from curl
            // The package of this installation's channel. The name is asked for
            // once and reused below, so the file the replace step opens is the
            // file this step wrote.
            $update_package = pg_update_package_file();

            $ch = curl_init("https://www.kodpen.com/" . $update_package);
            // Identify this installation on outgoing requests. Sent with no
            // User-Agent, a request looks like an anonymous client to the receiving
            // server's firewall and gets rejected — which is how Pinegrap ended up
            // blocking its own licence and update checks.
            curl_setopt($ch, CURLOPT_USERAGENT, function_exists('pinegrap_user_agent') ? pinegrap_user_agent() : 'Pinegrap');
            curl_setopt($ch, CURLOPT_HEADER, 0);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_BINARYTRANSFER, 1);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15); // seconds to establish the connection
            curl_setopt($ch, CURLOPT_TIMEOUT, 120);       // total seconds allowed for the zip download

            // if there is a proxy address, then send cURL request through proxy
            if (PROXY_ADDRESS != '') {
                curl_setopt($ch, CURLOPT_HTTPPROXYTUNNEL, true);
                curl_setopt($ch, CURLOPT_PROXYTYPE, CURLPROXY_HTTP);
                curl_setopt($ch, CURLOPT_PROXY, PROXY_ADDRESS);
            }
            $raw = curl_exec($ch);
            $curl_errno = curl_errno($ch);
            $curl_error = curl_error($ch);
            $http_status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $expected_bytes = (int) curl_getinfo($ch, CURLINFO_CONTENT_LENGTH_DOWNLOAD);
            curl_close($ch);

            // A non-200 body is still a successful transfer as far as cURL
            // is concerned. Without this check a 403 from the update
            // server's own firewall, or a 404 page, gets written to disk
            // as pinegrap_software_update.zip and fails three steps later
            // as an unexplained archive error.
            if ($raw !== false && $http_status !== 200) {
                log_activity('software update download returned HTTP ' . $http_status . ' instead of the update package.');

                $response = array(
                    'status' => 'error',
                    'message' => 'The update server returned HTTP ' . $http_status . ' instead of the update package.'
                );
                echo encode_json($response);
                exit();
            }

            // A transfer cut short mid-stream is not an error to cURL
            // either; compare against the length the server promised.
            if ($raw !== false && $expected_bytes > 0 && strlen($raw) < $expected_bytes) {
                log_activity('software update download was truncated: ' . strlen($raw) . ' of ' . $expected_bytes . ' bytes.');

                $response = array(
                    'status' => 'error',
                    'message' => 'The download was cut short (' . strlen($raw) . ' of ' . $expected_bytes . ' bytes). Please try again.'
                );
                echo encode_json($response);
                exit();
            }

            if ($raw !== false && !pg_looks_like_zip($raw)) {
                log_activity('software update download was not a zip archive.');

                $response = array(
                    'status' => 'error',
                    'message' => 'What was downloaded is not a zip archive. A proxy or firewall may have replaced the response.'
                );
                echo encode_json($response);
                exit();
            }

            if ($raw === false) {
                // there is an error about download so notice user and log activiy
                log_activity(
                    'software update file get could not communicate with the software update server, may its about update server so try it later. cURL Error Number: ' . $curl_errno . '. cURL Error Message: ' . $curl_error . '.'
                );
                //return error json output
                $response = array(
                    'status' => 'error',
                    'message' => 'Error while get files from the update server.' . pg_curl_tls_hint($curl_errno)
                );
                echo encode_json($response);
                exit();
            }

            // Zip file name
            $filename = $update_package;
            if (file_exists($filename)) {
                unlink($filename);
            }

            // 'x' fails when the file still exists, and the unlink above
            // can fail on permissions. Writing through an unchecked handle
            // emitted a warning and carried on as if it had worked.
            $fp = @fopen($filename, 'wb');

            if ($fp === false) {
                $response = array(
                    'status' => 'error',
                    'message' => 'Could not create the update file. Check write permission for the software directory.'
                );
                echo encode_json($response);
                exit();
            }

            $written = fwrite($fp, $raw);
            fclose($fp);

            // A short write means a full disk. Left unchecked it produced a
            // truncated archive that extracted partially.
            if ($written === false || $written < strlen($raw)) {
                @unlink($filename);

                $response = array(
                    'status' => 'error',
                    'message' => 'The update file could not be written completely. The disk may be full.'
                );
                echo encode_json($response);
                exit();
            }
            //zip file download success we can go step 3: replace the software files
            $response = array(
                'status' => 'success',
                'message' => 'Files overwriting...'
            );
            echo encode_json($response);
            exit();
            break;

        case 'replace':
            //Step 3: replace files.
            define('_PATH', PG_FUNCTIONS_DIR);
            // Zip file name — the channel's package, the same name the download step used.
            $filename = pg_update_package_file();
            // Unzip path
            $path = _PATH . "/../";

            // pg_extract_archive() checks archive consistency BEFORE
            // touching anything, refuses to start while a file on disk
            // cannot be replaced (another owner, read-only), then proves
            // every entry landed on disk with the archive's own size and
            // CRC afterwards - not merely that a file of that name exists,
            // which an old copy the server kept would satisfy.
            //
            // The previous code called extractTo() and discarded its
            // return value. Extraction stops at the first entry it cannot
            // write — one locked file, one permission problem, a full disk
            // — and everything after it is silently never created, while
            // the screen reports a successful update. That is why an
            // update could leave files missing and need repairing by hand.
            $extract = pg_extract_archive($filename, $path);

            if (!$extract['ok']) {
                log_activity('software update extraction failed: ' . $extract['message']
                    . (!empty($extract['missing']) ? ' Missing: ' . implode(', ', array_slice($extract['missing'], 0, 10)) : '')
                    . (!empty($extract['stale']) ? ' Not replaced: ' . implode(', ', array_slice($extract['stale'], 0, 10)) : '')
                    . (!empty($extract['blocked']) ? ' Cannot be replaced: ' . implode(', ', array_slice($extract['blocked'], 0, 10)) : ''));

                $response = array(
                    'status' => 'error',
                    'message' => $extract['message']
                );
                echo encode_json($response);
                exit();
            }

            unlink($filename);

            // The bytecode cache still holds the OLD files. Two reasons
            // this has to be dropped here rather than left to the cache's
            // own timestamp check:
            //
            //  • The screen sends the browser to install/index.php as
            //    soon as this returns. Between the new files landing and
            //    the cache noticing them (opcache.revalidate_freq, two
            //    seconds by default) the upgrade would run the PREVIOUS
            //    version's code against the new schema — the exact
            //    window the upgrade bridge exists to survive, entered on
            //    purpose for no reason.
            //  • Where the host turned timestamp validation off
            //    (opcache.validate_timestamps = 0, common on tuned
            //    production boxes) the old code keeps running until
            //    someone restarts PHP. The operator sees "update
            //    complete" and no change whatsoever.
            //
            // Also reclaims the memory the replaced files occupied:
            // every superseded copy stays in the cache as waste until
            // it is invalidated, and this software's largest file is
            // several megabytes of compiled opcodes on its own.
            //
            // Failure is not fatal — purge_cache.php exists for the
            // hosts that refuse the API — but it is worth a log line,
            // because "I updated and nothing changed" starts here.
            // extension_loaded() is not the question, and neither is
            // function_exists(): the extension can be compiled in while
            // opcache.enable is off, in which case the functions all
            // exist, every call returns false and emits a warning. Ask
            // the cache whether it is running.
            //
            // A host that blocks opcache.restrict_api answers nothing at
            // all — status is unreadable there but a reset may still be
            // allowed, so "unknown" tries anyway and stays quiet about
            // the outcome. Only a cache that says it is enabled AND
            // refuses every attempt is worth a log line.
            $pg_opcache_status  = function_exists('opcache_get_status') ? @opcache_get_status(false) : null;
            $pg_opcache_running = is_array($pg_opcache_status) ? !empty($pg_opcache_status['opcache_enabled']) : null;

            if ($pg_opcache_running !== false) {
                $pg_update_opcache_cleared = false;

                if (function_exists('opcache_reset')) {
                    $pg_update_opcache_cleared = (bool) @opcache_reset();
                }

                // opcache.restrict_api blocks reset() from a script
                // outside its directory; per-file invalidation is still
                // allowed on some of those hosts. The paths are the ones
                // the archive just wrote, so nothing else is walked.
                if (!$pg_update_opcache_cleared && function_exists('opcache_invalidate')) {
                    $pg_update_files = (isset($extract['files']) && is_array($extract['files'])) ? $extract['files'] : array();
                    foreach ($pg_update_files as $pg_update_file) {
                        if (substr($pg_update_file, -4) === '.php') {
                            if (@opcache_invalidate($pg_update_file, true)) {
                                $pg_update_opcache_cleared = true;
                            }
                        }
                    }
                }

                if (!$pg_update_opcache_cleared && $pg_opcache_running === true) {
                    log_activity('software update: the bytecode cache could not be cleared - run Purge Cache if the update does not take effect');
                }
            }

            $query = "DELETE FROM notifications WHERE action = 'software_update'";
            $result = mysqli_query(db::$con, $query) or output_error(lang('Query failed.'));

            //there is no error so update complete.
            //return success json output
            $response = array(
                'status' => 'success',
                'message' => 'Success. Being redirected for Upgrade.'
            );
            echo encode_json($response);
            exit();

            break;

        default:
            //return error json output
            $response = array(
                'status' => 'error',
                'message' => 'Crashed.'
            );
            echo encode_json($response);
            exit();
    }

    exit();
}
