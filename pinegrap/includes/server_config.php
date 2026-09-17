<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The web server configuration file in the WEB ROOT — web.config on IIS,
 * .htaccess on Apache, a sample snippet on nginx.
 *
 * That file is the site's, not the software's. It sits outside the software
 * folder, the update package never contains it, and the operator is free to
 * edit it. The consequence is that a rule added to the installer reaches
 * nobody who is already installed: every site created before it keeps the file
 * it was given on its first day, forever.
 *
 * So the rules live here, once, as blocks:
 *
 *   - the installer writes a file made of every block (pg_server_config_default),
 *   - an installed site is scanned block by block (pg_server_config_scan) and
 *     the System Status widget reports whatever is missing,
 *   - the operator presses one button and the missing blocks are inserted into
 *     the file they already have (pg_server_config_repair).
 *
 * Under includes/ rather than install/, for the reason the migration runner is:
 * the install folder can be deleted from a live server and this has to keep
 * working. install/index.php requires functions.php, and functions.php requires
 * this, so the installer and the running site read one list.
 *
 * Every path in a block is built from PATH and SOFTWARE_DIRECTORY at the moment
 * it is written. The software folder can be renamed and the site can live in a
 * sub-directory; a rule with "pinegrap/" typed into it is a rule that silently
 * protects nothing on a site whose folder is called something else.
 *
 * Compatibility: PHP 7.0 - 8.5
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_INIT_LOADED') && !defined('INSTALL_OR_UPDATE') && !defined('PG_FUNCTIONS_DIR')) {
    // Same guard the other includes carry: this file expects the constants
    // its callers set up and has nothing to say to a direct request.
    //
    // PG_FUNCTIONS_DIR - functions.php, which requires this file - is in the
    // list because functions.php is not always reached through init.php.
    // router.php loads it on its own to render the "database unavailable"
    // page, and with only the first two constants in the guard that page
    // exited here instead: a blank 200 on the one request whose whole purpose
    // is to say what is wrong.
    exit;
}


/**
 * The directory the configuration file is written to.
 *
 * Computed, never assumed: this file sits at
 * <web root>/<software>/includes/server_config.php, so three dirname() calls
 * land on the root whatever the software folder happens to be called and
 * wherever the installation has been moved to.
 */
function pg_server_config_web_root()
{
    return dirname(dirname(dirname(__FILE__)));
}


/**
 * Which server this is.
 *
 * SERVER_SOFTWARE first, because that is what the installer has always keyed
 * on. It is absent on some CGI setups and on the command line, and there the
 * file already sitting in the web root is the better witness: a web.config was
 * put there by IIS's world, an .htaccess by Apache's. Guessing Apache from
 * nothing would write directives into a server that ignores them and then
 * report the site protected.
 *
 * @return string iis | nginx | apache | unknown
 */
function pg_server_config_detect_server()
{
    $software = isset($_SERVER['SERVER_SOFTWARE']) ? (string) $_SERVER['SERVER_SOFTWARE'] : '';

    if (stristr($software, 'iis')) {
        return 'iis';
    }

    if (stristr($software, 'nginx')) {
        return 'nginx';
    }

    if (stristr($software, 'apache') || stristr($software, 'litespeed')) {
        return 'apache';
    }

    $root = pg_server_config_web_root();

    if (file_exists($root . '/web.config')) {
        return 'iis';
    }

    if (file_exists($root . '/.htaccess')) {
        return 'apache';
    }

    return 'unknown';
}


/**
 * Which server, and which file belongs to it.
 *
 * @return array server, label, file (absolute path), name (for the screen)
 */
function pg_server_config_target()
{
    $software = isset($_SERVER['SERVER_SOFTWARE']) ? (string) $_SERVER['SERVER_SOFTWARE'] : '';
    $server   = pg_server_config_detect_server();
    $root     = pg_server_config_web_root();

    if ($server === 'iis') {
        return array(
            'server' => 'iis',
            'label'  => 'IIS',
            'file'   => $root . '/web.config',
            'name'   => 'web.config',
        );
    }

    if ($server === 'nginx') {
        // nginx reads neither file. Its rules go in the server block, which is
        // not ours to write, so the sample is all this can offer.
        return array(
            'server' => 'nginx',
            'label'  => 'nginx',
            'file'   => $root . '/nginx.conf.sample',
            'name'   => 'nginx.conf.sample',
        );
    }

    // HTACCESS_FILE_PATH is the documented override for a multitenant layout
    // where one software folder serves several web roots. Honoured only here:
    // on IIS the installer points that same constant at httpd.ini, which is a
    // different product's file and not what any of this writes.
    $htaccess = (defined('HTACCESS_FILE_PATH') && substr(HTACCESS_FILE_PATH, -9) === '.htaccess')
        ? HTACCESS_FILE_PATH
        : $root . '/.htaccess';

    if ($server === 'apache') {
        return array(
            'server' => 'apache',
            'label'  => stristr($software, 'litespeed') ? 'LiteSpeed' : 'Apache',
            'file'   => $htaccess,
            'name'   => '.htaccess',
        );
    }

    return array(
        'server' => 'unknown',
        'label'  => ($software !== '') ? $software : '',
        'file'   => $htaccess,
        'name'   => '.htaccess',
    );
}


/**
 * The URL prefix rules are written against.
 *
 * PATH is "/" at the root and "/shop/" in a sub-directory.
 *
 * The file is written next to the software folder, which is the directory PATH
 * points at — so a PATTERN in it is already relative to PATH and must NOT
 * repeat it. An IIS rewrite match is "the path relative to the location of the
 * web.config", and an Apache RewriteRule pattern is relative to the directory
 * holding the .htaccess; a rule written as `^shop/pinegrap/data/` in
 * /shop/web.config matches nothing at all, and looks right while doing so.
 *
 * An ACTION is the opposite: the rewrite target is resolved against the site
 * root, so that one carries PATH. Same for an nginx `location`, which is an
 * absolute URI. Hence two accessors — the distinction is the whole reason
 * sub-directory installs go wrong.
 */
function pg_server_config_path()
{
    return defined('PATH') ? PATH : '/';
}

/**
 * The name of the software folder, as it is on disk.
 *
 * An installation can be renamed and the rules have to name the folder that is
 * actually there. SOFTWARE_DIRECTORY is derived from the running file's own
 * path and is the authority whenever it is defined -- an operator may also
 * have pinned it in config.php, and the rest of the software follows that.
 * Where it is not defined this file's own location gives the same answer, so
 * the fallback is still measured rather than guessed. A literal name here
 * would produce rules that read correctly and protect nothing.
 */
function pg_server_config_software_dir()
{
    if (defined('SOFTWARE_DIRECTORY') && SOFTWARE_DIRECTORY !== '') {
        return SOFTWARE_DIRECTORY;
    }

    return basename(dirname(dirname(__FILE__)));
}


/**
 * The blocks, for the server in use.
 *
 * Each block is:
 *   key       stable id — the scan, the repair and the report all key on it
 *   label     short name for a detail row, translated by the caller
 *   why       one sentence: what is exposed without it
 *   level     'required'    the site is unsafe without it
 *             'recommended' worth having, not a hole
 *             'core'        the site does not work without it
 *   detect    regex tested against the file, or '' for "cannot be missing"
 *   snippet   the text to insert
 *   anchor    where it goes: 'rules' | 'top' | 'end'
 *
 * @param string $server 'apache' | 'iis' | 'nginx' | 'unknown'
 * @return array
 */
function pg_server_config_blocks($server = '')
{
    if ($server === '') {
        $target = pg_server_config_target();
        $server = $target['server'];
    }

    $path = pg_server_config_path();         // /  or  /shop/  — actions only
    $dir  = pg_server_config_software_dir(); // pinegrap

    if ($server === 'iis') {
        return array(

            array(
                'key'    => 'router',
                'label'  => 'Router rule',
                'why'    => 'Without this rule no page of the site can be reached: every address other than a real file answers 404.',
                'level'  => 'core',
                'detect' => '/<rule\s+name="Pinegrap Rule"/i',
                'anchor' => 'rules-end',
                'snippet' =>
'                <rule name="Pinegrap Rule" stopProcessing="true">
                    <match url=".*" />
                    <conditions>
                        <add input="{REQUEST_FILENAME}" matchType="IsFile" negate="true" />
                        <add input="{REQUEST_FILENAME}" matchType="IsDirectory" negate="true" />
                    </conditions>
                    <action type="Rewrite" url="' . $path . $dir . '/router.php" />
                </rule>',
            ),

            array(
                'key'    => 'data',
                'label'  => 'Block data folder',
                'why'    => 'data/ holds config.php with the database password, the database backups and every uploaded file. IIS ignores the "deny from all" in data/.htaccess — that is Apache syntax — so without this rule the folder is readable over HTTP, and an uploaded .php file there would be executed.',
                'level'  => 'required',
                'detect' => '/<rule\s+name="Block direct access to data"/i',
                'anchor' => 'rules-start',
                'snippet' =>
'                <rule name="Block direct access to data" stopProcessing="true">
                    <match url="^(' . $dir . '/)?data/" ignoreCase="true" />
                    <action type="CustomResponse" statusCode="403" statusDescription="Forbidden" />
                </rule>',
            ),

            array(
                'key'    => 'includes',
                'label'  => 'Block includes folder',
                'why'    => 'Nothing under includes/ is fetched by a browser — it is PHP that other PHP requires. Requested directly it runs with none of the constants it expects and dies, which is how library files end up in the error log.',
                'level'  => 'required',
                'detect' => '/<rule\s+name="Block direct access to includes"/i',
                'anchor' => 'rules-start',
                'snippet' =>
'                <rule name="Block direct access to includes" stopProcessing="true">
                    <match url="^(' . $dir . '/)?includes/" ignoreCase="true" />
                    <action type="CustomResponse" statusCode="403" statusDescription="Forbidden" />
                </rule>',
            ),

            array(
                'key'    => 'hidden',
                'label'  => 'Hidden and leftover files',
                'why'    => 'A .git folder or a database dump left in the web root is served like any other file. IIS has no MIME type for .sql so it answers 404, which reads as protection but is not: rename it .txt and it downloads.',
                'level'  => 'required',
                'detect' => '/<hiddenSegments>/i',
                'anchor' => 'request-filtering',
                'snippet' =>
'                <hiddenSegments>
                    <add segment=".git" />
                    <add segment=".svn" />
                    <add segment=".vscode" />
                    <add segment="node_modules" />
                </hiddenSegments>
                <fileExtensions allowUnlisted="true">
                    <add fileExtension=".sql" allowed="false" />
                    <add fileExtension=".bak" allowed="false" />
                    <add fileExtension=".log" allowed="false" />
                    <add fileExtension=".env" allowed="false" />
                </fileExtensions>',
            ),

            array(
                'key'    => 'limits',
                'label'  => 'Request limits',
                'why'    => 'IIS refuses a request body over 30 MB by default, so a larger upload fails with a 404.13 the operator cannot read as a size limit.',
                'level'  => 'recommended',
                'detect' => '/<requestLimits\b/i',
                'anchor' => 'request-filtering',
                'snippet' =>
'                <requestLimits maxAllowedContentLength="500000000" maxUrl="40960" maxQueryString="20480" />',
            ),

            array(
                'key'    => 'charset',
                'label'  => 'UTF-8 for static files',
                'why'    => 'IIS serves .css and .js with no charset and the browser guesses. It guesses Latin-1 often enough that a "›" in a stylesheet reaches the screen as "â€º" until the page is reloaded.',
                'level'  => 'recommended',
                'detect' => '/<staticContent>/i',
                'anchor' => 'web-server',
                'snippet' =>
'        <staticContent>
            <remove fileExtension=".css" />
            <mimeMap fileExtension=".css" mimeType="text/css; charset=utf-8" />
            <remove fileExtension=".js" />
            <mimeMap fileExtension=".js" mimeType="application/javascript; charset=utf-8" />
            <remove fileExtension=".json" />
            <mimeMap fileExtension=".json" mimeType="application/json; charset=utf-8" />
            <remove fileExtension=".svg" />
            <mimeMap fileExtension=".svg" mimeType="image/svg+xml; charset=utf-8" />
        </staticContent>',
            ),

            array(
                'key'    => 'headers',
                'label'  => 'Security headers',
                'why'    => 'Without X-Content-Type-Options a browser may run an uploaded file as script because of what is inside it rather than what it is served as; without a referrer policy the full address of a private page travels to every site linked from it.',
                'level'  => 'recommended',
                'detect' => '/X-Content-Type-Options/i',
                'anchor' => 'web-server',
                'snippet' =>
'        <httpProtocol>
            <customHeaders>
                <remove name="X-Content-Type-Options" />
                <add name="X-Content-Type-Options" value="nosniff" />
                <remove name="Referrer-Policy" />
                <add name="Referrer-Policy" value="strict-origin-when-cross-origin" />
                <remove name="X-Powered-By" />
            </customHeaders>
        </httpProtocol>',
            ),
        );
    }

    if ($server === 'nginx') {
        // One block: the sample is written or it is not. nginx does not read a
        // file from the web root, so there is nothing to merge into.
        return array(
            array(
                'key'    => 'sample',
                'label'  => 'nginx sample',
                'why'    => 'nginx reads no configuration file from the web root. The rules have to be pasted into the server block by hand; this file is the copy to paste.',
                'level'  => 'recommended',
                'detect' => '/location/i',
                'anchor' => 'end',
                'snippet' => pg_server_config_default('nginx'),
            ),
        );
    }

    // Apache / LiteSpeed / unknown.
    //
    // Every deny is written twice: Require is 2.4, Order/Deny is 2.2, and a
    // 2.4 server without mod_access_compat answers 500 to the old syntax
    // rather than ignoring it. Both forms are guarded by IfModule so exactly
    // one of them is ever read.
    $deny =
'    <IfModule mod_authz_core.c>
        Require all denied
    </IfModule>
    <IfModule !mod_authz_core.c>
        Order allow,deny
        Deny from all
    </IfModule>';

    return array(

        array(
            'key'    => 'router',
            'label'  => 'Router rule',
            'why'    => 'Without this rule no page of the site can be reached: every address other than a real file answers 404.',
            'level'  => 'core',
            'detect' => '/RewriteRule\s+\.\s+\S*router\.php/i',
            'anchor' => 'end',
            'snippet' =>
'RewriteEngine on

# The following lines redirect all requests to the Pinegrap router,
# except for when an actual file or directory exists for the request.

RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule . ' . $dir . '/router.php [L]',
        ),

        array(
            'key'    => 'data',
            'label'  => 'Block data folder',
            'why'    => 'data/ holds config.php with the database password, the database backups and every uploaded file. The folder carries its own .htaccess, but a server configured with AllowOverride None ignores it — this rule is in the file the operator controls.',
            'level'  => 'required',
            'detect' => '/#\s*pg-block-data\b/i',
            'anchor' => 'before-rewrite',
            'snippet' =>
'# pg-block-data
# data/ is read from disk by the software, never over HTTP: it holds
# config.php, the database backups and every uploaded file.
RewriteRule ^(' . $dir . '/)?data/ - [F,L]',
        ),

        array(
            'key'    => 'includes',
            'label'  => 'Block includes folder',
            'why'    => 'Nothing under includes/ is fetched by a browser — it is PHP that other PHP requires. Requested directly it runs with none of the constants it expects and dies.',
            'level'  => 'required',
            'detect' => '/#\s*pg-block-includes\b/i',
            'anchor' => 'before-rewrite',
            'snippet' =>
'# pg-block-includes
# Library and template files, required by other PHP and never fetched directly.
RewriteRule ^(' . $dir . '/)?includes/ - [F,L]',
        ),

        array(
            'key'    => 'hidden',
            'label'  => 'Hidden and leftover files',
            'why'    => 'A .git folder, a .env, a database dump or an editor backup left in the web root is served like any other file, and each of them names or contains a credential.',
            'level'  => 'required',
            'detect' => '/#\s*pg-block-hidden\b/i',
            'anchor' => 'top',
            'snippet' =>
'# pg-block-hidden
# Version control folders, environment files, dumps and editor leftovers.
<FilesMatch "(^\\.|\\.(sql|bak|log|env|ini|swp|dist|orig|rej)$)">
' . $deny . '
</FilesMatch>
RewriteRule (^|/)\\.(git|svn|hg|env)(/|$) - [F,L]',
        ),

        array(
            'key'    => 'charset',
            'label'  => 'UTF-8 for static files',
            'why'    => 'Apache serves .css and .js with no charset and the browser guesses. It guesses Latin-1 often enough that a "›" in a stylesheet reaches the screen as "â€º" until the page is reloaded.',
            'level'  => 'recommended',
            'detect' => '/AddCharset\s+UTF-8/i',
            'anchor' => 'top',
            'snippet' =>
'AddCharset UTF-8 .css .js .json .svg',
        ),

        array(
            'key'    => 'headers',
            'label'  => 'Security headers',
            'why'    => 'Without X-Content-Type-Options a browser may run an uploaded file as script because of what is inside it rather than what it is served as; without a referrer policy the full address of a private page travels to every site linked from it.',
            'level'  => 'recommended',
            'detect' => '/X-Content-Type-Options/i',
            'anchor' => 'top',
            'snippet' =>
'<IfModule mod_headers.c>
    Header always set X-Content-Type-Options "nosniff"
    Header always set Referrer-Policy "strict-origin-when-cross-origin"
    Header always unset X-Powered-By
</IfModule>',
        ),
    );
}


/**
 * The whole file, for a server that has none.
 *
 * Built from the same blocks the scan looks for, so a fresh install cannot
 * start out already missing something.
 *
 * @param string $server
 * @return string
 */
function pg_server_config_default($server = '')
{
    if ($server === '') {
        $target = pg_server_config_target();
        $server = $target['server'];
    }

    $path = pg_server_config_path();
    $dir  = pg_server_config_software_dir();

    if ($server === 'nginx') {
        return
'# Pinegrap nginx sample. Paste inside your server { } block.
#
# nginx reads no file from the web root, so nothing here is applied until it
# is copied into the server configuration and nginx is reloaded.

# The software reads these folders from disk; they are never fetched over HTTP.
location ~ ^' . $path . '(' . $dir . '/)?(data|' . $dir . '/includes)/ {
    deny all;
    return 403;
}

# Version control folders, environment files, dumps and editor leftovers.
location ~ (^|/)\.(git|svn|hg|env)(/|$) {
    deny all;
    return 403;
}
location ~ \.(sql|bak|log|env|ini|swp|dist|orig|rej)$ {
    deny all;
    return 403;
}

# Everything that is not a real file goes to the router.
location ' . $path . ' {
    try_files $uri $uri/ ' . $path . $dir . '/router.php;
}

add_header X-Content-Type-Options "nosniff" always;
add_header Referrer-Policy "strict-origin-when-cross-origin" always;
';
    }

    if ($server === 'iis') {

        $blocks = array();
        foreach (pg_server_config_blocks('iis') as $block) {
            $blocks[$block['key']] = $block['snippet'];
        }

        // Order matters: the two 403 rules have to be tested before the
        // catch-all router rule, which matches everything and stops.
        return
'<?xml version="1.0" encoding="UTF-8"?>
<configuration>
    <system.webServer>
        <defaultDocument>
            <files>
                <clear />
                <add value="index.php" />
                <add value="index.htm" />
                <add value="index.html" />
            </files>
        </defaultDocument>
' . $blocks['charset'] . '
' . $blocks['headers'] . '
        <rewrite>
            <rules>
' . $blocks['data'] . '
' . $blocks['includes'] . '
' . $blocks['router'] . '
            </rules>
        </rewrite>
        <security>
            <requestFiltering>
' . $blocks['hidden'] . '
' . $blocks['limits'] . '
            </requestFiltering>
        </security>
    </system.webServer>
</configuration>
';
    }

    // Apache and anything unrecognised.
    $blocks = array();
    foreach (pg_server_config_blocks('apache') as $block) {
        $blocks[$block['key']] = $block['snippet'];
    }

    return
'# The following rules are used by Pinegrap.

' . $blocks['charset'] . '

' . $blocks['headers'] . '

' . $blocks['hidden'] . '

RewriteEngine on

# When the system is accessed from a sub-directory with an Apache alias
# (e.g. http://192.168.0.1/~example/), then you might need to uncomment the line
# below and update it to point to where the system is installed.  The system
# will attempt to automatically set the correct value for the line below during
# installation.  You might need to comment out the line below once you launch
# your site at a permanent URL without a sub-directory (e.g. http://www.example.com).

#RewriteBase /~example/

' . $blocks['data'] . '

' . $blocks['includes'] . '

# The following lines redirect all requests to the Pinegrap router,
# except for when an actual file or directory exists for the request.

RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule . ' . $dir . '/router.php [L]
';
}


/**
 * Everything the status row and the repair tile are drawn from, in one string.
 *
 * The dashboard caches its checks for ten minutes while the tile beside them
 * reads the file live; comparing this against the value stored with the cache
 * tells the cache when it is describing a file that no longer exists in that
 * shape. Cheap on purpose — a scan is one read of a few kilobytes.
 */
function pg_server_rules_signature()
{
    $scan = pg_server_config_scan();

    $keys = array();
    foreach ($scan['missing'] as $block) {
        $keys[] = $block['key'];
    }

    return implode('|', array(
        $scan['server'],
        $scan['exists'] ? '1' : '0',
        $scan['valid'] ? '1' : '0',
        $scan['writable'] ? '1' : '0',
        $scan['stale_path'] ? '1' : '0',
        implode(',', $keys),
    ));
}


/**
 * Point the rules this file writes at the folder the software is actually in.
 *
 * An installation gets renamed or moved and the file in the web root keeps the
 * old folder name. The router rule is the loud half of that -- every address
 * 404s and somebody notices within the hour. The two deny rules are the quiet
 * half: they go on matching a folder that no longer exists while the real
 * <software>/data/ is handed to anyone who asks for it, and nothing on the
 * screen or in the file looks wrong. A file copied from another installation
 * has the same problem from its first day.
 *
 * Only the folder token inside our own rules is touched; the rest of the
 * operator's file is not ours to rewrite. The replacement is escaped because a
 * directory name may legally contain a dollar sign.
 *
 * @return string the contents, unchanged when every rule already names the
 *                current folder
 */
function pg_server_config_retarget($contents, $server)
{
    if ($server === 'nginx') {
        return $contents;
    }

    $dir  = pg_server_config_software_dir();
    $path = pg_server_config_path();

    $dir_replacement  = str_replace(array('\\', '$'), array('\\\\', '\\$'), $dir);
    $path_replacement = str_replace(array('\\', '$'), array('\\\\', '\\$'), $path);

    if ($server === 'iis') {

        // <action type="Rewrite" url="/pinegrap/router.php" />
        $contents = preg_replace(
            '/(<action\s+type="Rewrite"\s+url=")[^"]*router\.php(")/i',
            '${1}' . $path_replacement . $dir_replacement . '/router.php${2}',
            $contents);

        // <match url="^(pinegrap/)?data/" ignoreCase="true" />
        return preg_replace(
            '/(<match\s+url="\^\()[^"\/)]*(\/\)\?(?:data|includes)\/")/i',
            '${1}' . $dir_replacement . '${2}',
            $contents);
    }

    // RewriteRule . pinegrap/router.php [L]
    $contents = preg_replace(
        '/(RewriteRule\s+\.\s+)\S*router\.php/i',
        '${1}' . $dir_replacement . '/router.php',
        $contents);

    // RewriteRule ^(pinegrap/)?data/ - [F,L]
    return preg_replace(
        '/(RewriteRule\s+\^\()[^\/)]*(\/\)\?(?:data|includes)\/)/i',
        '${1}' . $dir_replacement . '${2}',
        $contents);
}


/**
 * What the live file has and what it is missing.
 *
 * Reads only — nothing here writes. The router block is scanned like the
 * others but reported separately: a site whose router rule is missing is a
 * site that is not answering, so it is not a finding the operator needs a
 * dashboard to learn.
 *
 * @return array
 */
function pg_server_config_scan($recheck = false)
{
    // One read per request. The status check and the repair tile beside it
    // both ask, and two reads of the same file can only ever disagree.
    static $memo = null;

    if (!$recheck && ($memo !== null)) {
        return $memo;
    }

    $target = pg_server_config_target();

    $result = array(
        'server'    => $target['server'],
        'label'     => $target['label'],
        'name'      => $target['name'],
        'file'      => $target['file'],
        'exists'    => false,
        'writable'  => false,
        'readable'  => false,
        'valid'     => true,     // XML parses (IIS only; always true elsewhere)
        'missing'   => array(),  // blocks not found
        'present'   => array(),  // keys found
        'stale_path' => false,   // a router rule pointing somewhere else
    );

    $blocks = pg_server_config_blocks($target['server']);

    if (!file_exists($target['file'])) {
        // Nothing there: everything is missing, and the repair writes the
        // whole default rather than merging into a file that is not there.
        $result['missing'] = $blocks;
        $result['writable'] = is_writable(dirname($target['file']));
        $memo = $result;
        return $result;
    }

    $result['exists'] = true;
    $result['writable'] = is_writable($target['file']);

    $contents = @file_get_contents($target['file']);

    if ($contents === false) {
        $memo = $result;
        return $result;
    }

    $result['readable'] = true;

    // A web.config that does not parse cannot be merged into safely, and IIS
    // is answering 500.19 to every request anyway. Say so rather than
    // appending to a broken file.
    if ($target['server'] === 'iis' && trim($contents) !== '') {
        $previous = libxml_use_internal_errors(true);
        $result['valid'] = (simplexml_load_string($contents) !== false);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
    }

    foreach ($blocks as $block) {
        if ($block['detect'] !== '' && preg_match($block['detect'], $contents)) {
            $result['present'][] = $block['key'];
        } else {
            $result['missing'][] = $block;
        }
    }

    // A rule naming a folder that is not the one the software is in is worse
    // than a missing rule: the file looks configured. The router rule makes
    // the site 404 and gets noticed; the deny rules just stop protecting
    // anything. The test is the repair itself — if retargeting would change a
    // byte, a rule is pointing somewhere else — so the two can never drift
    // apart into "reported but not fixable".
    $result['stale_path'] = (pg_server_config_retarget($contents, $target['server']) !== $contents);

    $memo = $result;

    return $result;
}


/**
 * Insert the missing blocks into the file the site already has.
 *
 * Additive only. Nothing the operator wrote is rewritten or reordered — a
 * block is either absent, in which case it is inserted at its anchor, or
 * present, in which case it is left exactly as it is even if it does not look
 * like ours. The one exception is the router path, and only when the caller
 * asks: a rule pointing at a folder that no longer exists is not a preference.
 *
 * The file is copied to data/temp before it is touched, and an IIS file is
 * re-parsed after the merge and discarded if the result is not valid XML.
 *
 * @param bool $fix_path also correct a router rule pointing at the wrong folder
 * @return array status, message, applied (keys), backup (path or '')
 */
function pg_server_config_repair($fix_path = true)
{
    // Fresh, not the request memo: this one is about to write.
    $scan   = pg_server_config_scan(true);
    $target = pg_server_config_target();

    $out = array(
        'status'  => 'error',
        'message' => '',
        'applied' => array(),
        'backup'  => '',
    );

    if ($scan['server'] === 'nginx') {
        // The sample can be written; the server block cannot. Writing the file
        // and reporting success would claim a protection that is not applied
        // until somebody pastes it in.
        $sample = pg_server_config_default('nginx');
        if (@file_put_contents($target['file'], $sample) === false) {
            $out['message'] = lang('The file could not be written. Check the permissions of the web root.');
            return $out;
        }
        $out['status'] = 'success';
        $out['applied'] = array('sample');
        $out['message'] = lang('The nginx sample was written. Paste it into your server block and reload nginx — nothing is applied until you do.');
        return $out;
    }

    if (!$scan['valid']) {
        $out['message'] = lang('The file is not valid XML, so nothing was changed. Repair it by hand first.');
        return $out;
    }

    $exists = $scan['exists'];

    if (!$exists && !$scan['writable']) {
        $out['message'] = lang('The file could not be written. Check the permissions of the web root.');
        return $out;
    }

    if ($exists && !$scan['writable']) {
        $out['message'] = lang('The file could not be written. Check the permissions of the web root.');
        return $out;
    }

    // ── Nothing there: write the default whole ──────────────────────────
    if (!$exists) {
        if (@file_put_contents($target['file'], pg_server_config_default($scan['server'])) === false) {
            $out['message'] = lang('The file could not be written. Check the permissions of the web root.');
            return $out;
        }
        foreach ($scan['missing'] as $block) {
            $out['applied'][] = $block['key'];
        }
        $out['status'] = 'success';
        $out['message'] = lang(array(
            'string' => '{var:1} was created.',
            'vars'   => $target['name'],
        ));
        return $out;
    }

    $original = @file_get_contents($target['file']);

    if ($original === false) {
        $out['message'] = lang('The file could not be read.');
        return $out;
    }

    if (!$scan['missing'] && !($fix_path && $scan['stale_path'])) {
        $out['status'] = 'success';
        $out['message'] = lang('Nothing was missing.');
        return $out;
    }

    $contents = $original;
    $skipped  = array();

    foreach ($scan['missing'] as $block) {
        $merged = ($scan['server'] === 'iis')
            ? pg_server_config_insert_iis($contents, $block)
            : pg_server_config_insert_apache($contents, $block);

        // A block whose anchor is not in the file is skipped rather than
        // appended somewhere it does not belong — an <hiddenSegments> outside
        // <requestFiltering> is a 500 for every request on the site.
        if ($merged === null) {
            $skipped[] = $block['key'];
            continue;
        }

        $contents = $merged;
        $out['applied'][] = $block['key'];
    }

    if ($fix_path && $scan['stale_path']) {
        $contents = pg_server_config_retarget($contents, $scan['server']);
        $out['applied'][] = 'software_folder';
    }

    if ($contents === $original) {

        // Everything that was missing had nowhere to go: the file does not
        // carry the section those rules live in — an IIS file with no
        // <rewrite><rules>, say. Reporting "nothing was missing" here would be
        // a claim the operator can only disprove by pressing the button again
        // and watching the same number of rules stay missing.
        if ($skipped) {
            $out['message'] = lang(array(
                'string' => '{var:1} does not have the section these rules belong in, so nothing was changed. They have to be added by hand.',
                'vars'   => $target['name'],
            ));
            return $out;
        }

        $out['status'] = 'success';
        $out['message'] = lang('Nothing was missing.');
        return $out;
    }

    // The merged file has to parse before it replaces one that does. A
    // web.config IIS cannot read takes the whole site down with 500.19, and
    // the operator pressed a button described as making things safer.
    if ($scan['server'] === 'iis') {
        $previous = libxml_use_internal_errors(true);
        $ok = (simplexml_load_string($contents) !== false);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (!$ok) {
            $out['message'] = lang('The result would not have been valid XML, so nothing was changed.');
            return $out;
        }
    }

    $out['backup'] = pg_server_config_backup($target['file'], $original);

    if (@file_put_contents($target['file'], $contents) === false) {
        $out['message'] = lang('The file could not be written. Check the permissions of the web root.');
        return $out;
    }

    $out['status'] = 'success';
    $out['message'] = lang(array(
        'string' => '{var:1} rule(s) were added to {var:2}.',
        'vars'   => array(number_format(count($out['applied'])), $target['name']),
        'suffix' => (count($out['applied']) == 1) ? '' : 's',
    ));

    // Some went in and some did not. The count that stays behind in the status
    // check has to be accounted for here, or the operator reads the difference
    // as the button half working.
    if ($skipped) {
        $out['message'] .= ' ' . lang(array(
            'string' => '{var:1} rule(s) had no place in the file and were left out.',
            'vars'   => number_format(count($skipped)),
        ));
    }

    return $out;
}


/**
 * Keep the file as it was, under data/ where it is not served.
 *
 * A copy beside the original would be served by the very server whose rules
 * are being changed: web.config.bak has no MIME mapping on IIS but .htaccess
 * .bak is plain text on Apache, and the point of the backup is that it is
 * readable. data/temp is behind the rule this file writes.
 *
 * @return string path written, or '' when it could not be
 */
function pg_server_config_backup($file, $contents)
{
    $folder = dirname(dirname(__FILE__)) . '/data/temp/server_config';

    if (!is_dir($folder) && !@mkdir($folder, 0755, true)) {
        return '';
    }

    $name = basename($file);
    if ($name === '' || $name === '.' || $name === '..') {
        $name = 'server_config';
    }

    $backup = $folder . '/' . $name . '.' . date('Ymd-His') . '.bak';

    if (@file_put_contents($backup, $contents) === false) {
        return '';
    }

    return $backup;
}


/**
 * Split a file at the offset of a tag that sits on a line of its own.
 *
 * Everything up to that offset ends with the tag's own indentation, so a
 * snippet appended straight onto it starts out indented twice — a tab from the
 * file plus the eight spaces the snippet carries. Handing the indentation back
 * separately lets the caller open a line for the snippet and then give the tag
 * the indentation it came with.
 *
 * A tag sharing its line with something else has no indentation to reuse; the
 * line is left as it is and the snippet starts on the next one.
 *
 * @return array head (no trailing indentation), indentation, tail
 */
function pg_server_config_split_indent($contents, $at)
{
    $head   = substr($contents, 0, $at);
    $body   = rtrim($head, " \t");
    $indent = substr($head, strlen($body));

    if (($body !== '') && (substr($body, -1) !== "\n")) {
        $body   = $head . "\n";
        $indent = '';
    }

    return array($body, $indent, substr($contents, $at));
}


/**
 * Insert one block into a web.config.
 *
 * String surgery rather than DOM, deliberately: the file is the operator's and
 * carries their comments and their formatting, and DOMDocument::saveXML()
 * would hand it back reindented from top to bottom with every comment moved.
 * A one-line diff is reviewable; a whole-file rewrite is not.
 *
 * Returns null when the anchor is absent — the caller skips the block rather
 * than putting an element where it is not allowed.
 */
function pg_server_config_insert_iis($contents, $block)
{
    $snippet = $block['snippet'];

    switch ($block['anchor']) {

        case 'rules-start':
            // Straight after <rules>, because a 403 rule that runs after the
            // catch-all router rule never runs at all.
            if (!preg_match('/<rules\s*>/i', $contents, $found, PREG_OFFSET_CAPTURE)) {
                return null;
            }
            $at = $found[0][1] + strlen($found[0][0]);
            // <clear /> belongs to <rules> and has to stay first: it wipes the
            // inherited rule set, so a rule written above it is thrown away.
            if (preg_match('/^\s*<clear\s*\/>/i', substr($contents, $at), $clear)) {
                $at += strlen($clear[0]);
            }
            return substr($contents, 0, $at) . "\n" . $snippet . substr($contents, $at);

        case 'rules-end':
            if (!preg_match('/<\/rules\s*>/i', $contents, $found, PREG_OFFSET_CAPTURE)) {
                return null;
            }
            list($head, $indent, $tail) = pg_server_config_split_indent($contents, $found[0][1]);
            return $head . $snippet . "\n" . $indent . $tail;

        case 'request-filtering':
            // The section may not exist at all, in which case it is created
            // inside <security>, and <security> inside <system.webServer>.
            if (preg_match('/<requestFiltering\s*>/i', $contents, $found, PREG_OFFSET_CAPTURE)) {
                $at = $found[0][1] + strlen($found[0][0]);
                return substr($contents, 0, $at) . "\n" . $snippet . substr($contents, $at);
            }
            // A self-closing <requestFiltering /> has to be opened up first.
            if (preg_match('/<requestFiltering\s*\/>/i', $contents, $found, PREG_OFFSET_CAPTURE)) {
                return substr($contents, 0, $found[0][1])
                     . "<requestFiltering>\n" . $snippet . "\n            </requestFiltering>"
                     . substr($contents, $found[0][1] + strlen($found[0][0]));
            }
            if (preg_match('/<security\s*>/i', $contents, $found, PREG_OFFSET_CAPTURE)) {
                $at = $found[0][1] + strlen($found[0][0]);
                return substr($contents, 0, $at)
                     . "\n            <requestFiltering>\n" . $snippet . "\n            </requestFiltering>"
                     . substr($contents, $at);
            }
            if (!preg_match('/<\/system\.webServer\s*>/i', $contents, $found, PREG_OFFSET_CAPTURE)) {
                return null;
            }
            list($head, $indent, $tail) = pg_server_config_split_indent($contents, $found[0][1]);
            return $head
                 . "        <security>\n            <requestFiltering>\n" . $snippet
                 . "\n            </requestFiltering>\n        </security>\n" . $indent . $tail;

        case 'web-server':
        default:
            if (!preg_match('/<\/system\.webServer\s*>/i', $contents, $found, PREG_OFFSET_CAPTURE)) {
                return null;
            }
            list($head, $indent, $tail) = pg_server_config_split_indent($contents, $found[0][1]);
            return $head . $snippet . "\n" . $indent . $tail;
    }
}


/**
 * Insert one block into an .htaccess.
 *
 * Order is the whole question here. mod_rewrite runs its rules in file order
 * and the router rule ends with [L], so a deny written after it is never
 * reached: the request has already been handed to router.php. Anything that
 * denies goes above the first RewriteCond of the router block.
 */
function pg_server_config_insert_apache($contents, $block)
{
    $snippet = $block['snippet'];

    switch ($block['anchor']) {

        case 'before-rewrite':
            // Above the router's conditions, and below RewriteEngine on --
            // a RewriteRule before RewriteEngine on is simply not applied.
            if (preg_match('/^[ \t]*RewriteCond\s+%\{REQUEST_FILENAME\}\s+!-f/mi', $contents, $found, PREG_OFFSET_CAPTURE)) {
                $at = $found[0][1];
                return substr($contents, 0, $at) . $snippet . "\n\n" . substr($contents, $at);
            }
            if (preg_match('/^[ \t]*RewriteEngine\s+on[ \t]*$/mi', $contents, $found, PREG_OFFSET_CAPTURE)) {
                $at = $found[0][1] + strlen($found[0][0]);
                return substr($contents, 0, $at) . "\n\n" . $snippet . substr($contents, $at);
            }
            // No rewrite section at all: the block brings its own engine line.
            return rtrim($contents) . "\n\nRewriteEngine on\n\n" . $snippet . "\n";

        case 'top':
            // Directives that are not rewrite rules; they are order-free, and
            // the top is where a reader looks for them.
            return $snippet . "\n\n" . ltrim($contents, "\r\n");

        case 'end':
        default:
            return rtrim($contents) . "\n\n" . $snippet . "\n";
    }
}
