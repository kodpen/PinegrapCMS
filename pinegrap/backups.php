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
include('init.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/list_table.php');
$user = validate_user();
validate_area_access($user, 'manager');

include_once('liveform.class.php');
$liveform = new liveform('backups');

//Backup Files Directory
// Folders (hand-made backups, folder auto backups, the install dumps) and the
// archives the automatic backup writes. Every download and delete below acts
// only on a name this list returned, so nothing posted can reach outside it.
// Newest first, by the time each backup was taken (pg_backup_taken_time()).
function backup_list($directory){ 
	$directory_path = 'data/backups/';
	$l = array();
	if (!is_dir($directory_path)) {
		return $l;
	}
    foreach(array_diff(scandir($directory_path),array('..','.' )) as $backup_folder){
        if(is_dir($directory_path.'/'.$backup_folder) || (is_file($directory_path.'/'.$backup_folder) && (strtolower(substr($backup_folder, -4)) === '.zip'))){
            $l[$backup_folder] = pg_backup_taken_time($directory_path.$backup_folder);
        }
    }
    return $l; 
}

$output_warnings_for_backup = '';
$disabled_fieldset_attribute = '';
if (!extension_loaded('pdo_mysql') ) {
	$output_warnings_for_backup = '<div class="alert theme-warning">' . lang('pdo_mysql.dll is not enabled. Please enable it for Backup feature.') . '</div>';
	$disabled_fieldset_attribute = 'disabled="disabled"';
}

// A restore started on the installation screen that has not put its tables in
// place yet (includes/fn/backup_restore.php).
$output_restore_notice = '';
$unfinished_restore = pg_restore_unfinished();
if ($unfinished_restore !== null) {
	$output_restore_notice = '
	<div class="alert theme-warning d-flex gap-3 align-items-start">
		<i class="bi bi-hourglass-split"></i>
		<div>
			' . h(lang(array('string' => 'A restore of the backup {var:1} is unfinished ({var:2}, last step {var:3}). The site runs on its own tables until it is finished.', 'vars' => array($unfinished_restore['folder'], pg_restore_progress_text($unfinished_restore), get_absolute_time(array('timestamp' => (int) $unfinished_restore['updated'], 'format' => 'plain_text')))))) . '
			<a href="' . h(PATH . SOFTWARE_DIRECTORY . '/install/') . '">' . lang('Continue it or discard it on the installation screen.') . '</a>
		</div>
	</div>';
}

$backup_times = backup_list(getcwd());
$directory = pg_backup_list_order($backup_times);
// Every backup is printed: the list table driver sorts and searches them in
// the browser (browser mode) and keeps a heading's sort. The row buttons
// post the form the list sits in, as before.
$list_rows = array();
$sort_columns = array('name' => lang('Name'), 'last_modified' => lang('Last Modified'), 'size' => lang('Disk Usage'));
$list_state = pg_list_state('backups', $sort_columns, array('sort' => 'last_modified', 'order' => 'desc'));

foreach ($directory as $backup_number => $backup_folder) {
	$backup_is_archive = is_file('data/backups/'.$backup_folder);
	// A folder without its dump (a delete that stopped half-way, a copy
	// that never finished) cannot be restored, so it is not offered for
	// download as if it were a backup.
	$backup_incomplete = !$backup_is_archive && !is_file('data/backups/'.$backup_folder.'/sql.sql');
	$backup_time = (int) $backup_times[$backup_folder];
	$backup_size = (int) ($backup_is_archive ? filesize('data/backups/'.$backup_folder) : folderSize('data/backups/'.$backup_folder));
	// A starter-site dump stays deletable, but its confirmation names
	// what is lost: the installer offers only the dumps on disk.
	$backup_delete_confirm = (!$backup_is_archive && pg_backup_is_starter_dump($backup_folder))
		? lang(array('string' => 'Delete the backup {var:1} permanently? It is a starter site: a later install made from this copy no longer offers this language, and the panel does not bring the backup back.', 'vars' => array($backup_folder)))
		: lang(array('string' => 'Delete the backup {var:1} permanently?', 'vars' => array($backup_folder)));

	$list_rows[] = array(
		'id' => $backup_number + 1,
		'cells' => array(
			'name' => pg_list_cell(h($backup_folder) . '
				<span class="badge rounded-pill ' . ($backup_is_archive ? 'theme-primary' : 'theme-secondary') . ' ms-1">' . ($backup_is_archive ? lang('Archive') : lang('Folder')) . '</span>' . ($backup_incomplete ? '
				<span class="badge rounded-pill theme-warning ms-1" title="' . h(lang('This folder holds no database dump and cannot be restored.')) . '">' . lang('Incomplete') . '</span>' : ''),
				'align-middle', array('data-pg-sort-value' => $backup_folder)),
			'last_modified' => pg_list_cell('<time datetime="' . date('c', $backup_time) . '">' . h(get_absolute_time(array('timestamp' => $backup_time, 'format' => 'plain_text'))) . '</time>',
				'align-middle', array('data-pg-sort-value' => $backup_time)),
			'size' => pg_list_cell(h(convert_bytes_to_string($backup_size, 2)), 'align-middle', array('data-pg-sort-value' => $backup_size)),
			'actions' => pg_list_cell(($backup_incomplete ? '' : '
				<button type="submit" name="download_selected" value="Download '.h($backup_folder).'" class="m-1 btn-data-control btn btn-outline theme-primary border-2 " title="' . h(lang('Download')) . '" aria-label="' . h(lang(array('string' => 'Download {var:1}', 'vars' => array($backup_folder)))) . '"><i class="bi bi-download" aria-hidden="true"></i></button>') . '
				<button type="submit" name="delete_selected"   value="Delete '.h($backup_folder).'" class="m-1 btn-data-control btn btn-outline theme-danger border-2 " data-confirm-content="' . h($backup_delete_confirm) . '" title="' . h(lang('Delete')) . '" aria-label="' . h(lang(array('string' => 'Delete {var:1}', 'vars' => array($backup_folder)))) . '"><i class="bi bi-trash" aria-hidden="true"></i></button>',
				'align-middle text-nowrap'),
		),
	);
}

$list = pg_list_table_printed(array(
	'list' => 'backups',
	'columns' => array(
		'name' => array('heading' => lang('Name')),
		'last_modified' => array('heading' => lang('Last Modified'), 'kind' => 'date'),
		'size' => array('heading' => lang('Disk Usage'), 'kind' => 'number'),
		'actions' => array('heading' => lang('Action'), 'kind' => 'actions'),
	),
	'levels' => $list_state['sorts'],
	'rows' => $list_rows,
));


// Archives in the bucket of the "S3-compatible storage" destination. The
// bucket is read only when asked (?remote=1), so a slow or unreachable store
// never holds the screen up.
$backup_s3_on = (function_exists('pg_backup_settings_ready') && pg_backup_settings_ready()
	&& (pg_backup_settings()['backup_remote_type'] === 's3'));
$output_remote_card = '';

if (!$_POST && $backup_s3_on) {

	$output_remote_body = '
	                            <div class="p-5">
	                                <p class="form-text mt-0">' . lang('The archives the automatic backup copied to the bucket. Bring one to this server to download it or restore it from the installation screen.') . '</p>
	                                <a href="backups.php?remote=1" class="btn btn-sm btn-outline theme-secondary"><i class="bi bi-cloud-download me-1" aria-hidden="true"></i>' . lang('Show the archives in the bucket') . '</a>
	                            </div>';

	if (isset($_GET['remote'])) {

		$remote_archives = pg_backup_s3_archives();

		if (!$remote_archives['ok']) {
			$output_remote_body = '
	                            <div class="p-5"><div class="alert theme-warning mb-0">' . h($remote_archives['error']) . '</div></div>';
		} elseif (!$remote_archives['rows']) {
			$output_remote_body = '
	                            <div class="p-5 form-text">' . lang('The bucket holds no backup archive.') . '</div>';
		} else {
			$output_remote_rows = '';

			foreach ($remote_archives['rows'] as $remote_row) {
				$remote_here = file_exists('data/backups/' . $remote_row['name']);
				$output_remote_rows .= '
	                                    <tr>
	                                        <td class="align-middle text-start actions-buttons">' . ($remote_here
					? '<span class="badge rounded-pill theme-secondary">' . lang('On this server') . '</span>'
					: '<button type="submit" name="fetch_remote" value="' . h($remote_row['name']) . '" class="m-1 btn-data-control btn btn-outline theme-primary border-2" title="' . h(lang('Bring to this server')) . '" aria-label="' . h(lang(array('string' => 'Bring {var:1} to this server', 'vars' => array($remote_row['name'])))) . '"><i class="bi bi-cloud-download" aria-hidden="true"></i></button>') . '</td>
	                                        <td>' . h($remote_row['name']) . '</td>
	                                        <td data-order="' . (int) $remote_row['modified'] . '">' . ($remote_row['modified'] ? h(get_absolute_time(array('timestamp' => $remote_row['modified'], 'format' => 'plain_text'))) : '') . '</td>
	                                        <td data-order="' . (int) $remote_row['size'] . '">' . h(convert_bytes_to_string($remote_row['size'], 2)) . '</td>
	                                    </tr>';
			}

			$output_remote_body = '
	                            <table class="table table-hover mb-0">
	                                <thead>
	                                    <tr>
	                                        <th>' . lang('Action') . '</th>
	                                        <th>' . lang('Name') . '</th>
	                                        <th>' . lang('Last Modified') . '</th>
	                                        <th>' . lang('Size') . '</th>
	                                    </tr>
	                                </thead>
	                                <tbody>' . $output_remote_rows . '
	                                </tbody>
	                            </table>' . ($remote_archives['truncated'] ? '
	                            <div class="p-5 form-text">' . lang('Only the first 2,000 objects of the bucket were read.') . '</div>' : '');
		}
	}

	$output_remote_card = '
	                    <div class="card my-7" id="backup-remote">
	                        <div class="card-header bg-reset border-0 text-uppercase h5 fg-primary fw-bold">
	                            ' . lang('Copies in the bucket') . '
	                        </div>
	                        <div class="card-body p-0">' . $output_remote_body . '
	                        </div>
	                    </div>';
}


if (!$_POST) {


	$output =
	    pg_page_shell(
	        array(
	            'title'          => lang('Backup Manager'),
	            'extra classes'  => 'setting',
	            'icon'           => 'setting',
	            'heading'        => lang('Backup Manager'),
	            'heading_description' => lang('Take, download or delete website backups.'),
	            'cancel'         => true,
	        )
	    ) . '
<main id="content" class="container-fluid">
	    ' . pg_screen_toolbar(array(
	        // The screen's one action: a name and the button that takes the
	        // backup. backend.src.js reads #backup_folder_name and #backup.
	        'primary' => '<label for="backup_folder_name" class="visually-hidden">' . lang('Backup Name') . '</label>
	            <fieldset class="input-group input-group-sm pg-toolbar-search pg-backup-create" ' . $disabled_fieldset_attribute . '>
	                ' . $liveform->output_field([
	                    'type'  => 'text',
	                    'id'    => 'backup_folder_name',
	                    'name'  => 'backup_folder_name',
	                    'class' => 'form-control',
	                    'maxlength' => PG_BACKUP_NAME_MAX,
	                    'placeholder' => lang('Backup Name'),
	                ]) . '
	                <button type="button" id="backup" class="btn btn-sm btn-solid theme-primary ready">'
	                    . lang('Backup') . '
	                </button>
	            </fieldset>
	            <script>
	                // Return starts the backup; the field sits outside any form,
	                // so there is no submit to stop, only the click to make.
	                document.getElementById("backup_folder_name").addEventListener("keydown", function(event) {
	                    if (event.key !== "Enter" || event.isComposing) {
	                        return;
	                    }
	                    event.preventDefault();
	                    document.getElementById("backup").click();
	                });
	            </script>',
	        'grow' => '<span class="small fg-secondary">' . lang('Disk Usage') . ': ' . h(convert_bytes_to_string(folderSize('data/backups'), 2)) . '</span>',
	        'search' => pg_list_table_search_box('backups'),
	    )) . '

	        <div class="mb-7">
	            ' . $output_warnings_for_backup . '
	            <div class="progress d-none">
	                <div class="progress-bar progress-bar-striped"
	                     role="progressbar"
	                     aria-label="' . h(lang('Backup')) . '"
	                     aria-valuenow="0"
	                     aria-valuemin="0"
	                     aria-valuemax="100"
	                     style="width: 0%">
	                </div>
	            </div>
	            <div class="logbox form-text" role="status"></div>
	            <div class="form-text">' . lang('Database backups leave out the rows of visit records, page view counts, form item view counts, the firewall, performance and API request logs and the Search Console figures: those tables are backed up empty. The daily visitor summary and the hourly visitor chart are backed up in full; the Search Console figures of the last 90 days are downloaded again after a restore.') . '</div>
	        </div>

	        <div class="row">
	            <div class="col-12">
	                <div class="text-break">
	                ' . $liveform->output_errors() . '
	                ' . $liveform->get_warnings() . '
	                ' . $liveform->output_notices() . '
	                </div>
	                ' . $output_restore_notice . '

	                <form name="form" id="backupform" action="backups.php"
	                      class="disable_shortcut" method="post" autocomplete="off">

	                    ' . get_token_field() . '
	                    ' . pg_list_table_html($list['source'], $list['page'], lang('Backups')) . '

	                    ' . $output_remote_card . '
	                </form>
	            </div>
	        </div>
	    
</main>' . pg_js_bundle_tags('assets/js/pg_list_table.min.js') . output_footer();

	print $output;
	$liveform->remove_form('backups');
}else{
	validate_token_field();
	$liveform->add_fields_to_session();
	$backup_location ='data/backups/';


	// Bring an archive of the bucket here. Only a name the bucket lists now is
	// fetched, with the size it lists, so nothing posted reaches another key.
	if (isset($_POST['fetch_remote']) && is_string($_POST['fetch_remote']) && $backup_s3_on) {

		$remote_archives = pg_backup_s3_archives();
		$remote_found = null;

		foreach ($remote_archives['rows'] as $remote_row) {
			if ($remote_row['name'] === $_POST['fetch_remote']) {
				$remote_found = $remote_row;
			}
		}

		if (!$remote_archives['ok']) {
			$liveform->add_warning(h($remote_archives['error']));
		} elseif ($remote_found === null) {
			$liveform->add_warning(lang('That archive is no longer in the bucket.'));
		} else {
			$remote_fetched = pg_backup_s3_fetch($remote_found);

			if ($remote_fetched['ok']) {
				$liveform->add_notice(lang(array('string' => 'The archive {var:1} was brought to this server.', 'vars' => array(h($remote_found['name'])))));
				log_activity(lang(array('string' => 'The archive {var:1} was brought from the backup bucket to this server.', 'vars' => array($remote_found['name']))), $_SESSION['sessionusername']);
			} else {
				$liveform->add_warning(lang(array('string' => 'The archive {var:1} could not be brought to this server: {var:2}', 'vars' => array(h($remote_found['name']), h($remote_fetched['error'])))));
			}
		}

		go(PATH . SOFTWARE_DIRECTORY . '/backups.php?remote=1');
	}

	// Find if download or delete button selected
	foreach($directory as $backup_folder) {

		if (isset($_POST['download_selected']) && $_POST['download_selected'] === 'Download ' . $backup_folder) {

		    $dir = $backup_location . $backup_folder;

		    // An archive is served as it is; a folder is zipped first, beside
		    // its own files, as it always was.
		    $zip_file = is_file($dir) ? $dir : $dir . '/' . $backup_folder . '.zip';
				
		    // Write the folder's ZIP when there is none, or when the backup was
		    // updated after the last download wrote it.
		    if (!is_file($dir) && !pg_backup_folder_zip_current($zip_file, $dir)) {
			
		        $rootPath = realpath($dir);
		        if (!$rootPath || !is_dir($rootPath)) {
		            output_error(lang('Backup folder not found or inaccessible.'));
		        }
			
		        $zip = new ZipArchive();
		        $openResult = $zip->open($zip_file, ZipArchive::CREATE | ZipArchive::OVERWRITE);
			
		        if ($openResult !== true) {
		            output_error(lang(array('string' => 'Failed to create ZIP archive. Error code: {var:1}', 'vars' => array((int) $openResult))));
		        }
			
		        $iterator = new RecursiveIteratorIterator(
		            new RecursiveDirectoryIterator($rootPath),
		            RecursiveIteratorIterator::LEAVES_ONLY
		        );
			
		        // The previous archive sits in the same folder and is never
		        // packed into the new one.
		        $oldZipPath = realpath($zip_file);

		        foreach ($iterator as $file) {
		            if (!$file->isDir() && ($file->getRealPath() !== $oldZipPath)) {
		                $filePath = $file->getRealPath();
		                $relativePath = substr($filePath, strlen($rootPath) + 1);
					
		                if (is_readable($filePath)) {
		                    $zip->addFile($filePath, $relativePath);
		                }
		            }
		        }
			
		        $zip->close();
			
		        // Fallback: check if zip was created and has content
		        if (!file_exists($zip_file) || filesize($zip_file) < 100) {
		            output_error(lang('ZIP creation failed or resulted in empty archive.'));
		        }
		    }
		
		    // Serve the ZIP file
		    if (!is_readable($zip_file)) {
		        output_error(lang('ZIP file not readable.'));
		    }
		
		    header('Content-Description: File Transfer');
		    header('Content-Type: application/zip');
		    header('Content-Disposition: attachment; filename="' . basename($zip_file) . '"');
		    header('Content-Length: ' . filesize($zip_file));
		    header('Pragma: no-cache');
		    header('Expires: 0');
		
		    $handle = fopen($zip_file, 'rb');
		    if ($handle) {
		        while (!feof($handle)) {
		            echo fread($handle, 8192);
		        }
		        fclose($handle);
		    } else {
		        die("Failed to open ZIP file for download.");
		    }
		
		    exit();
		}
		if (isset($_POST['delete_selected']) && $_POST['delete_selected'] === 'Delete '.$backup_folder) {

		    $delete_backup = $backup_folder;
		    $dir = $backup_location.$delete_backup;

		    // An archive is one file.
		    if (is_file($dir)) {
		        if (!unlink($dir)) {
		            $liveform->add_warning(
		                h(lang(array('string' => 'The backup {var:1} could not be deleted.', 'vars' => array($backup_folder))))
		            );
		        } else {
		            $liveform->add_notice(
		                lang(array('string'=>'Backup ({var:1}) is deleted','vars'=>array($backup_folder)))
		            );
		            log_activity(
		                lang(array('string'=>'Backup ({var:1}) is deleted','vars'=>array($backup_folder))),
		                $_SESSION['sessionusername']
		            );
		            db('UPDATE config SET last_software_auto_backup = 0');
		        }
		        continue;
		    }
		
		    try {
		        $it = new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS);
		        $files = new RecursiveIteratorIterator($it, RecursiveIteratorIterator::CHILD_FIRST);

		        // What could not be removed is counted, not listed: the paths
		        // are the server's, and one line per entry ran off a phone's
		        // screen. The backup's name says which one it was.
		        $delete_left = 0;

		        foreach ($files as $file) {
		            $path = $file->getRealPath();

		            if ($file->isDir() ? !@rmdir($path) : !@unlink($path)) {
		                $delete_left++;
		            }
		        }

		        unset($files);
		        unset($it);

		        // Try to remove the main directory
		        if (!@rmdir($dir)) {
		            $delete_message = $delete_left
		                ? lang(array('string' => 'The backup {var:1} could not be deleted completely: {var:2} files or folders in it were refused. It is listed as incomplete.', 'vars' => array($backup_folder, $delete_left)))
		                : lang(array('string' => 'The backup {var:1} could not be deleted completely: its files were deleted but the folder was refused. It is listed as incomplete.', 'vars' => array($backup_folder)));
		            $liveform->add_warning(h($delete_message));
		            log_activity($delete_message, $_SESSION['sessionusername']);
		        } else {
		            $liveform->add_notice(
		                lang(array('string'=>'Backup ({var:1}) is deleted','vars'=>array($backup_folder)))
		            );
		            log_activity(
		                lang(array('string'=>'Backup ({var:1}) is deleted','vars'=>array($backup_folder))),
		                $_SESSION['sessionusername']
		            );
		            // A backup was removed, so let the scheduled auto backup
		            // take a fresh one on its next run instead of waiting out
		            // the 24 hour interval.
		            db('UPDATE config SET last_software_auto_backup = 0');
		        }

		    } catch (UnexpectedValueException $e) {
		        // Handle inaccessible directory (permission denied, etc.). The
		        // exception is a PHP warning, which PHP has already HTML-escaped
		        // when html_errors is on.
		        $liveform->add_warning(
		            lang(array(
		                'string'=>'Backup folder ({var:1}) cannot be accessed: {var:2}',
		                'vars'=>array(h($backup_folder), filter_var(ini_get('html_errors'), FILTER_VALIDATE_BOOLEAN) ? $e->getMessage() : h($e->getMessage()))
		            ))
		        );
		        // No notice or log here
		    }
		}
	}
	// forward user back to backups screen
	go(PATH . SOFTWARE_DIRECTORY . '/backups.php');
}