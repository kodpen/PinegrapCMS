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
include('init.php');
$user = validate_user();
validate_area_access($user, 'manager');

db('UPDATE config SET last_software_auto_backup = 0');

include_once('liveform.class.php');
$liveform = new liveform('backups');

//Backup Files Directory
function backup_list($directory){ 
	$directory_path = 'data/backups/';
    foreach(array_diff(scandir($directory_path),array('..','.' )) as $backup_folder)if(is_dir($directory_path.'/'.$backup_folder))$l[]=$backup_folder; 
    return $l; 
}

$output_warnings_for_backup = '';
$disabled_fieldset_attribute = '';
if (!extension_loaded('pdo_mysql') ) {
	$output_warnings_for_backup = '<div class="alert alert-warning">' . lang('pdo_mysql.dll is not enabled. Please enable it for Backup feature.') . '</div>';
	$disabled_fieldset_attribute = 'disabled="disabled"';
}

$directory = backup_list(getcwd()); 
$output_rows =  '';
if($directory){
	foreach($directory as $backup_folder) {
		$output_rows .= '
		<tr>
			<td class="align-middle text-start actions-buttons">
        	    <button type="submit" name="download_selected" value="Download '.$backup_folder.'" class="m-1 btn-data-control btn btn-outline-primary border-2 " data-loading-content=" " title="' . lang('Download') . '" ><i class="bi bi-download"></i></button>
        	    <button type="submit" name="delete_selected"   value="Delete '.$backup_folder.'" class="m-1 btn-data-control btn btn-outline-danger border-2 " data-loading-content=" " data-confirm-content="' . lang(array('string'=>'WARNING: Selected {var:1} will be permanently deleted.','vars'=>array(lang('backup')))) . '" title="' . lang('Delete') . '" ><i class="bi bi-trash"></i></button>
        	</td>
			<td>
				'.$backup_folder.'
			</td>
			<td>
				<time>'.date('d F Y', filectime('data/backups/'.$backup_folder)).'</time>
			</td>
			<td>' . h(convert_bytes_to_string(folderSize('data/backups/'.$backup_folder), 2)) . '</td>
		</tr>';
	} 
	   
}


if (!$_POST) {


	$output =
	    pg_page_shell(
	        array(
	            'title'          => lang('Backup Manager'),
	            'extra classes'  => 'setting',
	            'icon'           => 'setting',
	            'heading'        => lang('Backup Manager'),
	            'cancel'         => true,
	        )
	    ) . '

	    	        ' . get_codemirror_includes() . '
	        <div class="row">
	            <div class="col-12">
	                ' . $liveform->output_errors() . '
	                ' . $liveform->get_warnings() . '
	                ' . $liveform->output_notices() . '

	                <div class="row mb-2 flex-wrap">
	                    <div class="col-12 col-sm-12 text-center text-md-start">
	                        <h2 class="d-inline-block"
	                            data-bs-content="' . lang('Add, Edit, Delete or Download Website Backups.') . '"
	                            title="' . lang('Backup Manager') . '">'
	                            . lang('Backup Manager') . '
	                        </h2>
	                        <p>' . lang('Disk Usage') . ': ' 
	                            . h(convert_bytes_to_string(folderSize('data/backups'), 2)) . '
	                        </p>
	                    </div>
	                </div>

	                <form name="form" id="backupform" action="backups.php"
	                      class="disable_shortcut" method="post" autocomplete="off">

	                    <div class="card my-4">
	                        <div class="card-body p-0 position-relative">
	                            ' . get_token_field() . '

	                            <table class="chart table-hover table" style="width:100%;display:none">
	                                <thead>
	                                    <tr>
	                                        <th class="noVis">' . lang('Action') . '</th>
	                                        <th>' . lang('Name') . '</th>
	                                        <th>' . lang('Last Modified') . '</th>
	                                        <th>' . lang('Disk Usage') . '</th>
	                                    </tr>
	                                </thead>
	                                <tbody>
	                                    ' . $output_rows . '
	                                </tbody>
	                            </table>
	                        </div>
	                    </div>

	                    <div class="card my-4">
	                        <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">
	                            ' . lang('Create or Update Backup') . '
	                        </div>
	                        <div class="card-body">
	                            <div class="row">
	                                <div class="col-12 my-1">
	                                    ' . $output_warnings_for_backup . '
	                                </div>
	                                <div class="col-12 my-1">
	                                    <div class="progress d-none">
	                                        <div class="progress-bar progress-bar-striped"
	                                             role="progressbar"
	                                             aria-valuemin="0"
	                                             aria-valuemax="100"
	                                             style="width: 0%">
	                                        </div>
	                                    </div>
	                                    <div class="logbox form-text"></div>
	                                </div>
	                                <div class="col-12 col-md-auto my-1">
	                                    <label for="backup_folder_name" class="form-label">'
	                                        . lang('Backup Name') . '
	                                    </label>
	                                    <fieldset class="input-group" ' . $disabled_fieldset_attribute . '>
	                                        ' . $liveform->output_field([
	                                            'type'  => 'text',
	                                            'id'    => 'backup_folder_name',
	                                            'name'  => 'backup_folder_name',
	                                            'class' => 'form-control'
	                                        ]) . '
	                                        <button type="button" id="backup"
	                                                class="btn btn-success ready">'
	                                            . lang('Backup') . '
	                                        </button>
	                                    </fieldset>
	                                    <script>
	                                        var input = document.getElementById("backup_folder_name");
	                                        input.addEventListener("keyup", function(event) {
	                                            if (event.keyCode === 13) {
	                                                event.preventDefault();
	                                                document.getElementById("backup").click();
	                                            }
	                                        });
	                                    </script>
	                                </div>
	                            </div>
	                        </div>
	                    </div>
	                </form>
	            </div>
	        </div>
	    </main>' . output_footer();

	print $output;
	$liveform->remove_form('backups');
}else{
	validate_token_field();
	$liveform->add_fields_to_session();
	$backup_location ='data/backups/';


	// Find if download or delete button selected
	foreach($directory as $backup_folder) {

		if (isset($_POST['download_selected']) && $_POST['download_selected'] === 'Download ' . $backup_folder) {

		    $dir = $backup_location . $backup_folder;
		    $zip_file = $dir . '/' . $backup_folder . '.zip';
				
		    // Create ZIP if it doesn't exist
		    if (!file_exists($zip_file)) {
			
		        $rootPath = realpath($dir);
		        if (!$rootPath || !is_dir($rootPath)) {
		            die("Backup folder not found or inaccessible.");
		        }
			
		        $zip = new ZipArchive();
		        $openResult = $zip->open($zip_file, ZipArchive::CREATE | ZipArchive::OVERWRITE);
			
		        if ($openResult !== true) {
		            die("Failed to create ZIP archive. Error code: " . $openResult);
		        }
			
		        $iterator = new RecursiveIteratorIterator(
		            new RecursiveDirectoryIterator($rootPath),
		            RecursiveIteratorIterator::LEAVES_ONLY
		        );
			
		        foreach ($iterator as $file) {
		            if (!$file->isDir()) {
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
		            die("ZIP creation failed or resulted in empty archive.");
		        }
		    }
		
		    // Serve the ZIP file
		    if (!is_readable($zip_file)) {
		        die("ZIP file not readable.");
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
		if ($_POST['delete_selected'] == 'Delete '.$backup_folder) {

		    $delete_backup = $backup_folder;
		    $dir = $backup_location.$delete_backup;
		
		    try {
		        $it = new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS);
		        $files = new RecursiveIteratorIterator($it, RecursiveIteratorIterator::CHILD_FIRST);
		
		        foreach ($files as $file) {
		            $path = $file->getRealPath();
		
		            if ($file->isDir()) {
		                if (!rmdir($path)) {
		                    $liveform->add_warning(
		                        lang(array('string'=>'Folder ({var:1}) delete is unsuccess','vars'=>$path))
		                    );
		                }
		            } else {
		                if (!unlink($path)) {
		                    $liveform->add_warning(
		                        lang(array('string'=>'File ({var:1}) delete is unsuccess','vars'=>$path))
		                    );
		                }
		            }
		        }
		
		        unset($files);
		        unset($it);
		
		        // Try to remove the main directory
		        if (!rmdir($dir)) {
		            $liveform->add_warning(
		                lang(array('string'=>'Folder ({var:1}) delete is unsuccess','vars'=>$dir))
		            );
		        } else {
		            $liveform->add_notice(
		                lang(array('string'=>'Backup ({var:1}) is deleted','vars'=>array($backup_folder)))
		            );
		            log_activity(
		                lang(array('string'=>'Backup ({var:1}) is deleted','vars'=>array($backup_folder))),
		                $_SESSION['sessionusername']
		            );
		        }
		
		    } catch (UnexpectedValueException $e) {
		        // Handle inaccessible directory (permission denied, etc.)
		        $liveform->add_warning(
		            lang(array(
		                'string'=>'Backup folder ({var:1}) cannot be accessed: {var:2}',
		                'vars'=>array($dir, $e->getMessage())
		            ))
		        );
		        // No notice or log here
		    }
		}
	}
	// forward user back to backups screen
	header('Location: http://' . $_SERVER['HTTP_HOST'] . PATH . SOFTWARE_DIRECTORY . '/backups.php');
}