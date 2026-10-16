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
 * 
 */


// A file dragged into the file manager arrives as one large json body, base64 encoded.  Reading
// it and parsing it leaves two copies of it in memory at once, and memory_limit is sized for
// ordinary page requests, so a file well inside what the server could carry used to end the
// request with "Allowed memory size exhausted" halfway through.
//
// The room has to be made here, before the body is read: by the time a handler could ask for it
// the allocation that fails has already been attempted.  Only a request that is actually
// carrying something large is lifted, only as far as that body needs, and never below what the
// server was already set to.  functions.php has the same ceiling under
// pg_upload_memory_ceiling(), which is what the file manager quotes as its limit; if you change
// one, change the other.  ini_set is allowed to fail: a host that forbids it simply keeps the
// behaviour we had before.
$request_content_length = isset($_SERVER['CONTENT_LENGTH']) ? (int) $_SERVER['CONTENT_LENGTH'] : 0;

if ($request_content_length > (4 * 1024 * 1024)) {

    $memory_setting = trim((string) @ini_get('memory_limit'));
    $memory_unit    = strtolower(substr($memory_setting, -1));
    $memory_current = (float) $memory_setting;

    if ($memory_unit == 'g') { $memory_current = $memory_current * 1024 * 1024 * 1024; }
    elseif ($memory_unit == 'm') { $memory_current = $memory_current * 1024 * 1024; }
    elseif ($memory_unit == 'k') { $memory_current = $memory_current * 1024; }

    $memory_ceiling = defined('UPLOAD_MEMORY_CEILING') ? trim((string) UPLOAD_MEMORY_CEILING) : '512M';
    $ceiling_unit   = strtolower(substr($memory_ceiling, -1));
    $ceiling_bytes  = (float) $memory_ceiling;

    if ($ceiling_unit == 'g') { $ceiling_bytes = $ceiling_bytes * 1024 * 1024 * 1024; }
    elseif ($ceiling_unit == 'm') { $ceiling_bytes = $ceiling_bytes * 1024 * 1024; }
    elseif ($ceiling_unit == 'k') { $ceiling_bytes = $ceiling_bytes * 1024; }

    // Two copies of the body, plus room for the software itself and the work around it.
    $memory_needed = (2 * $request_content_length) + (48 * 1024 * 1024);

    if ($memory_needed > $ceiling_bytes) {
        $memory_needed = $ceiling_bytes;
    }

    // -1 (unlimited) reads as 0 here, and there is nothing to raise in that case.
    if (($memory_current > 0) && ($memory_needed > $memory_current)) {
        @ini_set('memory_limit', (int) $memory_needed);
    }

}

$raw_request = @file_get_contents('php://input');

$request = json_decode($raw_request, true);

// PHP throws the whole body away when the request is larger than post_max_size, and the script then
// runs with nothing in it.  Without this the answer would be "Invalid token", which sends everybody
// looking in the wrong place; the real reason is the size of the request.  We cannot look at
// CONTENT_LENGTH here, because PHP sets it to zero when it discards the body, so we go by the fact
// that a json request without a body never happens on purpose.
if (($request === null) && ($raw_request === '') && ($_SERVER['REQUEST_METHOD'] == 'POST') &&
    (isset($_SERVER['CONTENT_TYPE'])) && (stripos($_SERVER['CONTENT_TYPE'], 'json') !== false)) {

    header('Content-Type: application/json');

    // work out what this server would have taken, so the screen can lower its own limit to match
    $post_setting = trim((string) @ini_get('post_max_size'));

    $post_unit = strtolower(substr($post_setting, -1));

    $post_bytes = (float) $post_setting;

    if ($post_unit == 'g') { $post_bytes = $post_bytes * 1024 * 1024 * 1024; }
    elseif ($post_unit == 'm') { $post_bytes = $post_bytes * 1024 * 1024; }
    elseif ($post_unit == 'k') { $post_bytes = $post_bytes * 1024; }

    $upload_limit_bytes = ($post_bytes > 0) ? (int) floor(max(0, $post_bytes - 65536) * 0.74) : 0;

    print json_encode(array(
        'status' => 'error',
        'upload_limit_bytes' => $upload_limit_bytes,
        'post_max_size' => $post_setting,
        'message' => 'The request was larger than this server accepts in one request (post_max_size ' . $post_setting . '). Raise post_max_size and upload_max_filesize, or send a smaller file.'));

    exit();

}

// The body has been parsed, so the raw copy is nothing but weight.  It matters on uploads,
// where the JSON carries a base64 payload of tens of megabytes: holding the raw text and the
// decoded JSON at the same time is already two copies of the file before anything is written.
//
// The size is kept because pg_upload_limits() works the upload allowance out from the memory
// peak, and by this point that peak holds both of those copies.  Without knowing how much of
// it belongs to the body, the allowance would shrink as the file grows -- the bigger the file,
// the smaller the limit it is measured against -- and a file that uploaded yesterday would be
// turned away as too large today.
$GLOBALS['pg_request_body_bytes'] = strlen($raw_request);

// A request from a page drawn in another language says so (pg_lang, in the
// query string or the body), so what it is answered with - a product's
// description, a shipping method's name - is in that language too.
if (function_exists('pg_tr_define_from_request')) {
    pg_tr_define_from_request($request);
}

unset($raw_request);

// If login info was included in the request, then store it, so that initialize_user() can login user.
//
// Both halves or neither. A body carrying a name and no password used to define
// the pair anyway, with the password reading as null, which turned a malformed
// request into a sign-in attempt against a password nobody sent.
if (isset($request['username']) && is_string($request['username']) && ($request['username'] !== '')
    && isset($request['password']) && is_string($request['password'])) {
    define('API_USERNAME', $request['username']);
    // Raw password; initialize_user() verifies it against the stored hash.
    define('API_PASSWORD', $request['password']);
}

include('init.php');

// Add header in order to start response.
header('Content-Type: application/json');
if (isset($request['action'])) {
    $action = $request['action'];
} else {
    $action = 'Null';
}
if (isset($request['token'])) {
    $token = $request['token'];
}


// Every action and the general checks it gets are listed in
// includes/panel/actions.php. An exempt action checks the session, the role
// and the token in its own handler; any other one - and any name the table
// does not know - must come from a signed-in user, with role 1 or better
// unless its row says any_role.
require_once(dirname(__FILE__) . '/includes/panel/actions.php');

$panel_gate = pg_panel_gate($action);

if ($panel_gate !== 'exempt') {

    // The password was right but the account has a second step, which a
    // request carrying a password cannot give (initialize_user() loaded no
    // user). Said apart from "Invalid login." so the client can tell the
    // person what to do instead of asking for the password again.
    if (defined('API_MFA_REQUIRED')) {
        header('HTTP/1.1 401 Unauthorized');
        respond(array(
            'status' => 'error',
            'code' => 'mfa_required',
            'message' => lang('This account uses two-step verification and cannot sign in with a password here. Sign in on the website, or use an application key.')
        ));
    }

    // If a user was not found then respond with an error.
    if (!USER_LOGGED_IN) {
        respond(array(
            'status' => 'error',
            'message' => 'Invalid login.'
        ));
    }

    if ($panel_gate === 'role') {
        // If the user does not have a designer or administrator role, then respond with an error.
        if (USER_ROLE > 1) {
            respond(array(
                'status' => 'error',
                'message' => 'The User must have a Designer or Administrator role.'
            ));
        }
    }
}

pg_panel_dispatch($action, $request);

respond(array(
    'status' => 'error',
    'message' => 'Invalid action.'
));

function respond($response)
{
    echo encode_json($response);
    exit;
}

// A token is required to be passed in the request for session login requests
// that update an item.
function validate_token()
{

    global $token;

    // If the user passed a username and password in this request
    // and did not login via a session, then token validation is not
    // necessary, so return true.
    //
    // API_AUTHENTICATED, not API_USERNAME: the first says the password was
    // checked and was right, the second only says one was sent. A page on
    // another site can make a visitor's browser post whatever body it likes,
    // and while this read the second of the two, adding a made up user name to
    // that body was enough to have the token waived - on a request that still
    // arrived with the visitor's own cookies.
    if (defined('API_AUTHENTICATED')) {
        return true;
    }

    // If the token does not exist in the session,
    // or the passed token does not match the token from the session,
    // then this might be a CSRF attack so respond with an error.
    //
    // The body is decoded json, so the token arrives with whatever type the
    // sender gave it. A loose compare would take `true` (or, on PHP 7, `0`)
    // as equal to the session's hex string; only a string compared byte for
    // byte counts.
    $session_token = isset($_SESSION['software']['token']) ? (string) $_SESSION['software']['token'] : '';
    if (
        ($session_token === '')
        || (!is_string($token))
        || (!hash_equals($session_token, $token))
    ) {
        respond(array(
            'status' => 'error',
            'message' => 'Invalid token.'
        ));
    }
}