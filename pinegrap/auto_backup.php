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
//required for software backup mysql dumb
use Ifsnop\Mysqldump as IMysqldump;
include('init.php');


// This feature can take a long time to run for a large site,
// so increase the allowed execution time for the PHP script.
ini_set('max_execution_time', 0);
ini_set('memory_limit', '-1');

// Scheduled-task health: recorded here rather than at the end, because the
// backup routine returns from inside its own success branch and the end of
// this script is not reached on a successful run. It is also the honest
// place: what the maintenance panel reports is whether the scheduled task
// fires at all. Whether the extension needed to write a backup is installed
// is a different question, and the settings screen answers that one next to
// this job's command.
pg_cron_ran('auto_backup');

if (!extension_loaded('pdo_mysql') ) {
    echo lang('pdo_mysql.dll is not enabled. Please enable it for Auto Backup feature.');
    exit();
}



// auto backup defined from init
if(defined('LAST_SOFTWARE_AUTO_BACKUP') && ( !defined('SOFTWARE_AUTO_BACKUP') || SOFTWARE_AUTO_BACKUP != false )){
    
    // if define is 0, that mean this script is first time runing so we set a timestamp value
    if(LAST_SOFTWARE_AUTO_BACKUP == 0){
        $last_software_auto_backup = time() - 86401;
    }else{
        // otherwise its not first time, we set from db
        $last_software_auto_backup = LAST_SOFTWARE_AUTO_BACKUP;
    }

    // now timestamp - last check
    $last_software_auto_backup = time() - $last_software_auto_backup;

    //we dont want server atacked, software can work this script once a day.
    if($last_software_auto_backup > 86400){//86400 = 24 hours
        // run auto_backup function
        software_auto_backup();
    }
}

function software_auto_backup(){
    $backup_location = dirname(__FILE__) . '/data/backups/';
    
    $backup_folder_name = 'auto_backup_' . date('Y-m-W');
    if (!file_exists($backup_location.$backup_folder_name)) {
        mkdir($backup_location.$backup_folder_name, 0777, true);
    }
    
    
    include_once('mysqldump.php');
                    
    //Create mysql dump file named slq.sql and save it in backup directory
    // first backup Mysql because, if there is timeout when file copy mysql important for us. so even timeout to copy files or layouts we have mysql dump anyway.
    try {
        $dump = new IMysqldump\Mysqldump('mysql:host='.DB_HOST.';dbname='.DB_DATABASE.'', ''.DB_USERNAME.'', ''.DB_PASSWORD.'');
        $dump->start($backup_location.$backup_folder_name .'/sql.sql');
    } catch (\Exception $e) {
        
        $backups_error_message = $e->getMessage();
    
        //if mysql error and backup folder is empty, delete it.
        if (!file_exists($backup_location.$backup_folder_name.'/*')) {
            rmdir($backup_location.$backup_folder_name);
        }
        log_activity(lang('Software Auto Backup error:') . $backups_error_message, $_SESSION['sessionusername']);
        return;
    }
    
    //Prepare for files and layouts**
    //if files directory not exist Create directory
    if (!file_exists($backup_location.$backup_folder_name.'/files')) {
        mkdir($backup_location.$backup_folder_name.'/files', 0777, true);
    }
    //if layouts directory not exist Create directory
    if (!file_exists($backup_location.$backup_folder_name.'/layouts')) {
        mkdir($backup_location.$backup_folder_name.'/layouts', 0777, true);
    }
    //CLEAR//
    // delete all files from template files directory
    $files = glob($backup_location.$backup_folder_name.'/files/{,.}*', GLOB_BRACE); // get all file names
    foreach($files as $file){ // iterate files
        if(is_file($file))
        unlink($file); // delete file
    }
    // delete all files from template layouts directory
    $layouts = glob($backup_location.$backup_folder_name.'/layouts/{,.}*', GLOB_BRACE); // get all layouts names
    foreach($layouts as $layout){ // iterate layouts files
        if(is_file($layout))
        unlink($layout); // delete layouts files
    }
    
    //WRITE//
     // prepare path to template files
     $backup_files_path = $backup_location.$backup_folder_name.'/files/';
     $handle = opendir(FILE_DIRECTORY_PATH);
     // copy files to backup directory
     while (false !== ($file = readdir($handle))) {
         if (($file != '.') && ($file != '..')) {
             copy(FILE_DIRECTORY_PATH . '/' . $file,$backup_files_path . $file);
         }
     }
     closedir($handle);
    
    //WRITE//
    // prepare path to template layouts
    $backup_layouts_path = $backup_location.$backup_folder_name.'/layouts/';
    $handle = opendir(LAYOUT_DIRECTORY_PATH);
    // copy files to backup directory
    while (false !== ($file = readdir($handle))) {
        if (($file != '.') && ($file != '..')) {
            copy(LAYOUT_DIRECTORY_PATH . '/' . $file,$backup_layouts_path . $file);
        }
    }
    closedir($handle);

    //create .htaccess file to make directory unaccessable.
    file_put_contents($backup_location.$backup_folder_name.'/.htaccess','deny from all');
    
    if (file_exists($backup_location.$backup_folder_name)) {
                        
        if (file_exists($backup_location.$backup_folder_name.'/sql.sql')) {
            if (file_exists($backup_location.$backup_folder_name.'/files')) {
                if (file_exists($backup_location.$backup_folder_name.'/layouts')) {
                    $query ="UPDATE config SET last_software_auto_backup = UNIX_TIMESTAMP()";
                    $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
                    log_activity(lang('Software Auto Backup Success'), $_SESSION['sessionusername']);
                    return;
                }
            }
        }
        
    }
}

