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

//if there is no config file, create one.
if (!file_exists(dirname(__FILE__) . '/data/config.php')) {   
    $file_config = fopen(dirname(__FILE__) . '/data/config.php','w');
    fwrite($file_config, '<?php ?>');
    fclose($file_config);
}
require(dirname(__FILE__) . '/data/config.php');

// Pinegrap error reporting: suppress notices and deprecated warnings.
// Set SET_ERROR_REPORTING to false in config.php to let php.ini take over.
if (!defined('SET_ERROR_REPORTING') or SET_ERROR_REPORTING) {
    ini_set('error_reporting', E_ALL & ~E_NOTICE & ~E_DEPRECATED);
}

ini_set('default_charset', 'utf-8');
mb_internal_encoding('UTF-8');
mb_http_output('UTF-8');

// Remember that the PHP settings have been set, so that we don't have to do it again
// in the init.php script.
define('PHP_SETTINGS_UPDATED', true);

// If DB_HOST is not defined, then config.php is not setup properly,
// so installation has probably not been completed, so output error.
if (defined('DB_HOST') == false) {
    router_output_error('Sorry, config.php is not configured properly. This probably means that the software has not been installed. Please install the software, or configure config.php properly.');
}

// Create a class to hold the MySQL connection object so that we can access it anywhere.
class db {
    public static $con;
}

// if php version is bigger than 7 than try connect else use old methods
if (defined('PHP_MAJOR_VERSION') && PHP_MAJOR_VERSION >= 7) {
    try {
        db::$con = @mysqli_connect(DB_HOST, DB_USERNAME, DB_PASSWORD, DB_DATABASE);
    } catch( Exception $e ) {
        router_output_error('Sorry, this website could not connect to the database.  The server administrator should check the status of the database.  If there is not a problem with the database, then the server administrator should verify that the database information in the config.php file in the software directory is correct.');
    }
}else{
    db::$con = @mysqli_connect(DB_HOST, DB_USERNAME, DB_PASSWORD, DB_DATABASE);
}

// if the connection or selection of the database failed, then output error

if (!db::$con) {
    router_output_error('Sorry, this website could not connect to the database.  The server administrator should check the status of the database.  If there is not a problem with the database, then the server administrator should verify that the database information in the config.php file in the software directory is correct.');
}else{
    // check db connected but if it's empty or has no config table.
    if(!mysqli_num_rows(mysqli_query(db::$con,"SHOW TABLES LIKE 'config'"))) {
        router_output_error('Sorry, this website could not found the required config table in a database. The server administrator should check the status of the database.');
        exit();
    }
}

$mysql_version = preg_replace('#[^0-9\.]#', '', mysqli_get_server_info(db::$con));

if (version_compare($mysql_version, '5.5.3', '>=') == true) {
    $mysql_character_set = 'utf8mb4';
} else {
    $mysql_character_set = 'utf8';
}

// If the mysqli_set_charset function is available (PHP 5.2.3+)
// then use it to set the character set.  This is necessary
// so that mysqli_real_escape_string will be secure.
if (function_exists('mysqli_set_charset')) {
    mysqli_set_charset(db::$con, $mysql_character_set);
}

// Even though we most likely ran mysqli_set_charset above,
// we have to run the command below also so that all charset
// and collation settings get set properly.  For example,
// mysqli_set_charset does not set collation_connection
// to the collation that we want.
mysqli_query(db::$con, "SET NAMES '" . $mysql_character_set . "' COLLATE '" . $mysql_character_set . "_unicode_ci'");

// Remember that we connected to the database, so that later if the init.php
// script is run then it will know it does not need to connect to the database.
define('DB_CONNECTED', true);

// Disable MySQL strict mode, because later versions of MySQL enable strict mode by default,
// and Pinegrap is not compatible with strict mode.  This will also remove all other sql modes,
// however that should be fine.
mysqli_query(db::$con, "SET SESSION sql_mode = ''");

// If this site has enabled the legacy MySQL connection in the config.php and it is using a version
// of PHP that supports that (i.e. before PHP 7), then create a legacy DB connection.  Sites might
// enable this feature because they have old custom code in hook code, styles, custom layouts, or etc.
// that uses the old mysql extension and relies on Pinegrap to start that connection.
// If a site enables this feature, then a second db connection will be opened for every request,
// which might have a negative performance effect.

if (defined('DB_LEGACY') and DB_LEGACY and function_exists('mysql_connect')) {

    $link=@mysql_connect(DB_HOST, DB_USERNAME, DB_PASSWORD);

    if ($link and @mysql_select_db(DB_DATABASE)) {
        
        if (function_exists('mysql_set_charset')) {
            @mysql_set_charset($mysql_character_set);
        }

        @mysql_query("SET NAMES '" . $mysql_character_set . "' COLLATE '" . $mysql_character_set . "_unicode_ci'");
    }
}

// Get software directory in order to get path.

// if this server is on Windows, then path delimiter is a backslash
if (mb_strtoupper(mb_substr(PHP_OS, 0, 3)) == 'WIN') {
    $delimiter = '\\';
    
// else this server is not on Windows, so path delimiter is a forward slash
} else {
    $delimiter = '/';
}

$path_parts = explode($delimiter, dirname(__FILE__));
define('SOFTWARE_DIRECTORY', $path_parts[count($path_parts) - 1]);

// Get path to home page in order to understand requested address (e.g. / or /sub-directory/).

// Get the url path parts in order to get the file name.
$url_path_parts = explode('/', $_SERVER['SCRIPT_NAME']);
$file_name = $url_path_parts[count($url_path_parts) - 1];

// If the index.php in the software root was requested, then get the path in a certain way.
if ($file_name == 'index.php') {
    // get the path without the file name on the end
    $url_path = dirname($_SERVER['SCRIPT_NAME']);
    
    // convert backslashes to forward slashes
    // backslashes seem to only appear on Windows when only the root is left (e.g. \).
    $url_path = str_replace('\\', '/', $url_path);

// else the software root index.php file was not requested, so get the path in a different way
} else {
    // if this is at least PHP 5.0.0, then use strrpos to get the position of the last occurrence of the software directory in the path
    // PHP 4 does not support multicharacter needle for strrpos
    if (version_compare(PHP_VERSION, '5.0.0', '>=') == TRUE) {
        $position = mb_strrpos($_SERVER['SCRIPT_NAME'], '/' . SOFTWARE_DIRECTORY);
        
    // else this is PHP 4, so use mb_strrpos, which supports a multicharacter needle
    } else {
        $position = mb_strrpos($_SERVER['SCRIPT_NAME'], '/' . SOFTWARE_DIRECTORY);
    }
    
    // if the software directory could not be found in the path (should never happen), then set the path to /
    if ($position === FALSE) {
        $url_path = '/';
        
    // else the software directory was found, so set the path to everything up to the software directory
    } else {
        $url_path = mb_substr($_SERVER['SCRIPT_NAME'], 0, $position);
    }
}

// if the path is not the root, then add a slash on the end
if ($url_path != '/') {
    $url_path .= '/';
}

define('PATH', $url_path);

define('REQUEST_URL', router_get_request_url());

// ── Web Application Firewall (first pass) ────────────────────────────────
// The router dispatches get_file.php, robots.txt and sitemap.xml without ever
// reaching init.php, so those requests would otherwise be unprotected.
//
// Only the identity-independent checks run here — IP lists, attack tooling,
// rate limits. Content inspection is deliberately left to the init.php pass,
// because the session has not started yet and page regions are edited from
// the page's own front-end URL: without knowing whether a designer is logged
// in, saving a region containing <script> would look exactly like an attack.
require_once(dirname(__FILE__) . '/waf.php');
waf_run('router');

$request_url_without_path = mb_substr(REQUEST_URL, mb_strlen(PATH));

// Get first 6 characters so that we can test if visitor used old address format.
$first_6_characters = mb_strtolower(mb_substr($request_url_without_path, 0, 6));

// If the visitor requested the old address format for an item (e.g. /pages/example),
// then send 301 redirect to new address format (e.g. /example).
if ( 
    ($first_6_characters == 'pages/')
    || ($first_6_characters == 'files/')
) {

    $query =
        "SELECT
            url_scheme,
            hostname
        FROM config";
    $result = mysqli_query(db::$con, $query) or router_output_error('Query failed.');
    $config = mysqli_fetch_assoc($result);

    // Create a new request uri without "pages/" or "files/".
    $new_request_url_without_path = mb_substr($request_url_without_path, 6);

    header('Location: ' . $config['url_scheme'] . $config['hostname'] . PATH . $new_request_url_without_path, true, 301);
    exit();
}

$query_string_position = mb_strpos($request_url_without_path, '?');

// If a query string was found, then remove the query string in order to set the item name.
if ($query_string_position !== false) {
    $item_name = mb_substr($request_url_without_path, 0, $query_string_position);

// Otherwise there is no query string, so just set the item name to the request uri with path.
} else {
    $item_name = $request_url_without_path;
}

$item_name = rawurldecode($item_name);

// If the item name is blank or is "index.php", then that means the home page was requested,
// so forward to get page script.
if (
    ($item_name == '')
    || ($item_name == 'index.php')
) {

    require(dirname(__FILE__) . '/get_page.php');
    exit();
}

// Check is request is for robots.txt or sitemap.xml.
// It is important that this check be done before the file check below,
// so that proper dynamic content is outputted instead of file,
// if a file exists for these item names.
// We should eventually not allow files to be created with these names.

$item_name_lowercase = mb_strtolower($item_name);

// If item is "robots.txt", then run that script.
if ($item_name_lowercase == 'robots.txt') {
    require(dirname(__FILE__) . '/get_robots.txt.php');
    exit();
}

// If item is "sitemap.xml", then run that script.
if ($item_name_lowercase == 'sitemap.xml') {
    require(dirname(__FILE__) . '/get_sitemap.xml.php');
    exit();
}

// Check if item is a file.
$query = "SELECT id FROM files WHERE name = '" . router_escape($item_name) . "'";
$result = mysqli_query(db::$con, $query) or router_output_error('Query failed.');

// If file was found, then run get file script.
if (mysqli_num_rows($result) > 0) {
    $_GET['name'] = $item_name;
    require(dirname(__FILE__) . '/get_file.php');
    exit();
}

// Check if item is a short link.
$query =
    "SELECT
        short_links.destination_type,
        short_links.url,
        short_links.tracking_code,
        short_links.file_id,
        page.page_name,
        product_groups.address_name AS product_group_address_name,
        products.address_name AS product_address_name
    FROM short_links
    LEFT JOIN page ON short_links.page_id = page.page_id
    LEFT JOIN product_groups ON short_links.product_group_id = product_groups.id
    LEFT JOIN products ON short_links.product_id = products.id
    WHERE short_links.name = '" . router_escape($item_name) . "'";
$result = mysqli_query(db::$con, $query) or router_output_error('Query failed.');



// If short link was found, then process it.
if (mysqli_num_rows($result) > 0) {
    
    $short_link = mysqli_fetch_assoc($result);

    // Prepare destination differently based on the destination type.
    switch ($short_link['destination_type']) {
        
        // Prepare destination for page, product_group, and product types.
        default:
            // If this short link has a tracking code and there is not already
            // a tracking code in the query string, then set it.
            if (($short_link['tracking_code'] != '') && ($_GET['t'] == '')) {
                $_GET['t'] = $short_link['tracking_code'];
            }

            // Start the page path with just the page name.
            $page_path = $short_link['page_name'];

            // Add additional info to the page path if destination type is product group or product.
            switch ($short_link['destination_type']) {
                case 'product_group':
                    $page_path .= '/' . $short_link['product_group_address_name'];
                    break;

                case 'product':
                    $page_path .= '/' . $short_link['product_address_name'];
                    break;

                case 'file':
                   
                    $query = "SELECT name FROM files WHERE id = '" . $short_link['file_id'] . "'";
                    $result = mysqli_query(db::$con, $query) or router_output_error('Query failed.');
                    // If file was found, then run get file script.
                    if (mysqli_num_rows($result) > 0) {
                        $result = mysqli_fetch_assoc($result);

                        $_GET['name'] = $result['name'];
                      
                        require(dirname(__FILE__) . '/get_file.php');
                        exit();
                    }

                    break;
            }


            $_GET['page'] = $page_path;
            require(dirname(__FILE__) . '/get_page.php');
            exit();

            break;
        
        // If the destination type is URL then forward visitor to URL.
        case 'url':
            // Build a full URL with scheme and everything from the
            // possibly short URL that they might have entered,
            // so it is compatible with header call below.

            $query =
                "SELECT
                    url_scheme,
                    hostname
                FROM config";
            $result = mysqli_query(db::$con, $query) or router_output_error('Query failed.');
            $config = mysqli_fetch_assoc($result);

            $url_parts = parse_url($short_link['url']);

            $url = '';

            if ($url_parts['scheme'] == '') {
                $url .= $config['url_scheme'];
            } else {
                $url .= $url_parts['scheme'] . '://';
            }

            if ($url_parts['host'] == '') {
                $url .= $config['hostname'];
            } else {
                $url .= $url_parts['host'];
            }

            if ($url_parts['path'] != '') {
                // If the first character in the path is not a /, then add the path.
                if (mb_substr($url_parts['path'], 0, 1) != '/') {
                    $url .= PATH;
                }

                $url .= $url_parts['path'];
            }

            if ($url_parts['query'] != '') {
                $url .= '?' . $url_parts['query'];
            }

            if ($url_parts['fragment'] != '') {
                $url .= '#' . $url_parts['fragment'];
            }

            header('Location: ' . $url, true, 301);
            exit();
            break;
    }
}

// If we have gotten here, then that means the item is not a file, short link, or other item,
// so just assume it is page so run get page script.  If a page does not exist
// for the item name, then the get page script will deal with that.
$_GET['page'] = $item_name;
require(dirname(__FILE__) . '/get_page.php');
exit();

function router_escape($string) {
    return mysqli_real_escape_string(db::$con, $string);
}

function router_get_request_url()
{
    // the order of the conditionals below is important, because newer versions of IIS supply a REQUEST_URI value,
    // but the value does not contain the original pretty URL and might also have other problems
    
    // if HTTP_X_REWRITE_URL is set (i.e. ISAPI_Rewrite is being used on IIS)
    if (isset($_SERVER['HTTP_X_REWRITE_URL']) && $_SERVER['HTTP_X_REWRITE_URL']) {
        return $_SERVER['HTTP_X_REWRITE_URL'];
    
    // else if REQUEST_URI is set (i.e. non IIS web server)
    } elseif ($_SERVER['REQUEST_URI']) {
        return $_SERVER['REQUEST_URI'];
        
    // else no REQUEST_URI or HTTP_X_REWRITE_URL can be found (i.e. IIS web server without ISAPI_Rewrite)
    } else {
        // if QUERY_STRING is set then return PHP_SELF and QUERY_STRING
        if ($_SERVER['QUERY_STRING']) {
            return $_SERVER['PHP_SELF'] . '?' . $_SERVER['QUERY_STRING'];
            
        // else QUERY_STRING is not set, so return just PHP_SELF
        } else {
            return $_SERVER['PHP_SELF'];
        }
    }
}

function router_output_error($error_message) {
    
    $output_error_message = $error_message;

    $mysql_error = '';

    if (db::$con) {
        $mysql_error = mysqli_error(db::$con);
    }

    // if there is a MySQL error, then add that to the error message
    if ($mysql_error !== '') {
        $output_error_message .= ' ' . router_h($mysql_error);
    }
    if(defined('EDITION')){
        define('EDITION', EDITION);
    }else{
        define('EDITION', 'Premium');
    }

    require (dirname(__FILE__) . '/functions.php');
    if(
        (isset($_GET['local']))
        && ($_GET['local'] ==='en'
        || $_GET['local'] ==='tr')
    ){
        define('SOFTWARE_LANGUAGE', $_GET['local']);
    }else{
        
        //else user is not request we check config defines. if ENFORCEMENT_SOFTWARE_LANGUAGE exits use it
        if( defined('ENFORCEMENT_SOFTWARE_LANGUAGE') ){
            define('SOFTWARE_LANGUAGE', ENFORCEMENT_SOFTWARE_LANGUAGE);
            $output_enforcement = '(' . ENFORCEMENT_SOFTWARE_LANGUAGE . ')';
        }else{
            // else check if there is DEFAULT_SOFTWARE_LANGUAGE setup in config.
            if( defined('DEFAULT_SOFTWARE_LANGUAGE') ){
                define('SOFTWARE_LANGUAGE', DEFAULT_SOFTWARE_LANGUAGE);
            }else{
                define('SOFTWARE_LANGUAGE', dedect_user_language());
            }
        }
    
    }
    
    // get software directory

    // if this server is on Windows, then path delimiter is a backslash
    if (mb_strtoupper(mb_substr(PHP_OS, 0, 3)) == 'WIN') {
        $delimiter = '\\';

    // else this server is not on Windows, so path delimiter is a forward slash
    } else {
        $delimiter = '/';
    }
    $path_parts = explode($delimiter, dirname(__FILE__));
    define('SOFTWARE_DIRECTORY', $path_parts[count($path_parts) - 1]);

    // prepare escaped version of software directory because we will use this a lot
    define('OUTPUT_SOFTWARE_DIRECTORY', h(SOFTWARE_DIRECTORY));

    // if this request was made over the web (i.e. not a cron job),
    // then get the path (e.g. /~example) to the software root (i.e. the level above the software directory)
    // For cron jobs, we will set the path later from the value that we get from the database
    if ($_SERVER['HTTP_HOST'] != '') {
        // get the url path parts in order to get the file name
        $url_path_parts = explode('/', $_SERVER['SCRIPT_NAME']);
        $file_name = $url_path_parts[count($url_path_parts) - 1];
    
        // if the index.php in the software root was requested, then get the path in a certain way
        if (
    ($file_name == 'index.php')
    && (defined('NON_ROOT_INDEX') == FALSE)
        ) {
    // get the path without the file name on the end
    $url_path = dirname($_SERVER['SCRIPT_NAME']);
    
    // convert backslashes to forward slashes
    // backslashes seem to only appear on Windows when only the root is left (e.g. \).
    $url_path = str_replace('\\', '/', $url_path);

        // else the software root index.php file was not requested, so get the path in a different way
        } else {
    // if this is at least PHP 5.0.0, then use strrpos to get the position of the last occurrence of the software directory in the path
    // PHP 4 does not support multicharacter needle for strrpos
    if (version_compare(PHP_VERSION, '5.0.0', '>=') == TRUE) {
        $position = mb_strrpos($_SERVER['SCRIPT_NAME'], '/' . SOFTWARE_DIRECTORY);
        
    // else this is PHP 4, so use mb_strrpos, which supports a multicharacter needle
    } else {
        $position = mb_strrpos($_SERVER['SCRIPT_NAME'], '/' . SOFTWARE_DIRECTORY);
    }
    
    // if the software directory could not be found in the path (should never happen), then set the path to /
    if ($position === FALSE) {
        $url_path = '/';
        
    // else the software directory was found, so set the path to everything up to the software directory
    } else {
        $url_path = mb_substr($_SERVER['SCRIPT_NAME'], 0, $position);
    }
        }
    
        // if the path is not the root, then add a slash on the end
        if ($url_path != '/') {
            $url_path .= '/';
        }
    
        define('PATH', $url_path);
    
        // prepare escaped version of path because we will use this a lot
        define('OUTPUT_PATH', h(PATH));
    
        // If the REQUEST_URL has not been defined by the router already,
        // then set it (e.g. for control panel screens).
        if (!defined('REQUEST_URL')) {
    define('REQUEST_URL', get_request_uri());
        }
    }
   
    // If the ENVIRONMENT constant is set to "development", then set the ENVIRONMENT_SUFFIX to "src".
    // This allows us to use source files instead of minified files during development.
    if (defined('ENVIRONMENT') and ENVIRONMENT == 'development') {
        define('ENVIRONMENT_SUFFIX', 'src');
        // Otherwise the ENVIRONMENT constant is not defined or set to something else, so set the ENVIRONMENT_SUFFIX to "min".
    }
    else {
        define('ENVIRONMENT_SUFFIX', 'min');
    }
    $output_page_title = lang('Error');
    $output_quick_fix_buttons = '';
    $output_other_help_buttons = '';

    
    if (
        defined('DB_HOST') == false 
        || !db::$con 
        || (
            db::$con 
            && !mysqli_num_rows(mysqli_query(db::$con,"SHOW TABLES LIKE 'config'")) 
            ) 
        ) {
        $output_quick_fix_buttons .='<div class="col-12"><a href="' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/install/" class="btn btn-link link-primary py-0 mb-2 "><span class="material-icons me-1">install_desktop</span>' . lang('Install or Reinstall Software') . '</a></div>';
        $output_other_help_buttons .='<div class="col-12"><a target="_blank" href="https://www.kodpen.com/iletisim" class="btn btn-link link-primary py-0 mb-2 "><span class="material-icons me-1">support_agent</span>' . lang('Get Support') . '</a></div>';
        $output_page_title = lang('Software Configuration Error');
    }

   

    $output_other_help_buttons .='<div class="col-12"><a target="_blank" href="' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/pi.php" class="btn btn-link link-primary py-0 mb-2 "><span class="material-icons me-1">help</span>' . lang('PHP Check') . '</a></div>';
    $output_other_help_buttons .='<div class="col-12"><a target="_blank" href="https://1drv.ms/b/s!AtFjifE5cgNcgqsMUui6rjx8v6_q7A?e=DrbNvb" class="btn btn-link link-primary py-0 mb-2 "><span class="material-icons me-1">article</span>' . lang('Documents') . '</a></div>';

    function get_header($output_page_title) {
    	return output_header_secure(array(
    		'title' => $output_page_title,
    		'icon' => 'setting'
    	));
    }

    function get_footer() {
       
        return '<div id="footer" class="footer p-3  d-flex flex-wrap justify-content-center justify-content-md-between text-light d-print-none">
                    <h6 class="mx-auto">' . lang('Pinegrap Content Management System') . '</h6>
                    <h6 class="version_and_copyright mx-auto">' . EDITION . ' - <span class="material-icons" >copyright</span>' . date('Y', time()) . '</h6>
                </div>
            </body>
        </html>';

    }

    if($output_other_help_buttons != ''){
        $output_other_help_buttons = '<p class="mt-3">' . lang('Other help links') . ':</p>' . $output_other_help_buttons;
    }




    echo 
    get_header($output_page_title) . '
    <meta name="robots" content="noindex">
    <style>
    .alert.alert-danger{
        background: #ffe4e4ed !important;
        backdrop-filter: blur(4rem) brightness(3) saturate(8);
    }
    .card{
        background: #ffffffde;
        backdrop-filter: blur(8rem) brightness(2) saturate(9);
    }
    h2.title{
        color:#fff;
    }
    @media (prefers-color-scheme: dark) {
        .alert.alert-danger{
            background: #0c0a0aed !important;
    
        }

        .card{
            background: #000000de;
        }
    }

    
    </style>
    <main id="content" class="container">
        <div class="row">
            <div class="col-12">
                <div class="row mb-2  flex-wrap">
                    <div class="col-12 col-sm-12 text-center text-md-start">
                        <h2 class="d-inline-block title" data-bs-content="' . lang('Something went wrong') . '" title="' . lang('Error') . '">' . lang('Error') . '</h2>
                    </div>
                </div>
                <div class="row" id="install_fields">
                    <div class="col-12 ">
                    <div class="alert alert-danger d-flex align-items-center">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor" class="bi bi-exclamation-triangle-fill flex-shrink-0 me-2" viewBox="0 0 16 16" role="img" aria-label="Warning:">
                            <path d="M8.982 1.566a1.13 1.13 0 0 0-1.96 0L.165 13.233c-.457.778.091 1.767.98 1.767h13.713c.889 0 1.438-.99.98-1.767L8.982 1.566zM8 5c.535 0 .954.462.9.995l-.35 3.507a.552.552 0 0 1-1.1 0L7.1 5.995A.905.905 0 0 1 8 5zm.002 6a1 1 0 1 1 0 2 1 1 0 0 1 0-2z"/>
                        </svg>
                        <div>' . lang($output_error_message) . '</div>
                    </div>
                        <div class="card my-4">
                         
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-12  my-2">
                                        <p>' . lang('The reason for the error is written above.') . '</p>
                                        <p>' . lang('Possible quick fixes') . ':</p>
                                        ' . $output_quick_fix_buttons . $output_other_help_buttons . '
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
    <script>
    document.body.style.background = "#0000008c url(\'' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/assets/images/backgrounds/image8.jpg\')";
    document.body.style.backgroundPosition = "center";
    document.body.style.backgroundAttachment = "fixed";
    document.body.style.backgroundBlendMode = "darken";
    </script>
    ' . get_footer();

    exit();
}

function router_h($content)
{
    return htmlspecialchars($content, ENT_COMPAT | ENT_HTML401, 'UTF-8');
}

// dedect user language from list, default is en
function dedect_user_language(){
	$supportedLanguages=['en','tr'];
	$lang = substr($_SERVER['HTTP_ACCEPT_LANGUAGE'], 0, 2);
	if(!in_array($lang,$supportedLanguages)){
		$lang='en';
	}
	return $lang;
}