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

// A background run (crontab, or the general job's dispatcher) has no user.
// Every other request is a web request and must come from a signed-in user
// who may open backups.php, the screen this job stands in for. Without the
// gate an anonymous GET wrote a full database dump and a copy of every file
// into a folder whose name is the current week. Checked before the job is
// recorded as having run, so a refused request does not count as a run.
if (!pg_cron_is_background_run()) {
    $user = validate_user();
    validate_area_access($user, 'manager');
}

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

// The run itself -- the archive, the retention and the remote copy -- lives in
// includes/fn/backup.php.
function software_auto_backup(){
    pg_backup_run_auto();
}
