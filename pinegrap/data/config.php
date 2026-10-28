<?php
// Prevent direct access
if (php_sapi_name() !== "cli" && basename(__FILE__) === basename($_SERVER["SCRIPT_FILENAME"])) {
    http_response_code(403);
    exit("Forbidden");
}
define('DB_HOST', 'localhost');
define('DB_USERNAME', 'pinegrap');
define('DB_PASSWORD', 'sandbox');
define('DB_DATABASE', 'pinegrap_sandbox');
define('ENCRYPTION_KEY', 'TOMFXZF6GNP4GW5Q6WMETDUASXDAO6EX'); // DO NOT MODIFY OR SHARE
// Automated upgrade from a cron job over the web: install/index.php?automated_upgrade=true&secret=<this value>
// Optional. Undefined or shorter than 16 characters means the key path is closed; php from the command line never needs it.
// define('AUTOMATED_UPGRADE_SECRET', 'change-this-to-a-long-random-string');
define('DYNAMIC_REGIONS', true);
define('PHP_REGIONS', true);
define('DEFAULT_SOFTWARE_LANGUAGE', 'tr');
define('SOFTWARE_UPDATE_CHECK', false);
?>