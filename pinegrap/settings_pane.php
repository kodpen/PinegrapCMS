<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Site Settings over AJAX: one category of the settings modal.
 *
 *   GET  ?pane=security   the cards, as JSON carrying HTML
 *   POST pane=security    saves that category and answers in JSON
 *
 * The settings are a modal the panel can open from any screen, so there is no
 * page to render and no redirect to follow: the answer is always JSON, even
 * when something goes wrong. output_error() is put into its throwing mode for
 * that reason -- its error screen would reach the browser as "unexpected
 * token <" and the operator would be told nothing at all.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

include('init.php');

$user = validate_user();

// The gate the settings have always used: role <= 2 (Administrator, Designer,
// Manager). It is here rather than in the modal's markup because a button that
// is not drawn is not a permission check.
validate_area_access($user, 'manager');

include_once('liveform.class.php');

define('PG_SETTINGS_ENTRY', true);

include_once(PG_FUNCTIONS_DIR . '/includes/settings/registry.php');
include_once(PG_FUNCTIONS_DIR . '/includes/settings/fragment.php');

header('Content-Type: application/json; charset=utf-8');

// Nothing here is cacheable: the answer is this operator's settings.
header('Cache-Control: no-store');


/**
 * Answer and stop.
 *
 * @param array $payload
 * @param int $status
 */
function pg_settings_pane_respond($payload, $status = 200)
{
    set_response_code($status);

    print json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    exit;
}


/**
 * What to tell the operator when a card or a save gives up.
 *
 * output_error() writes for a screen and its message can carry markup -- a
 * "go back" link, most often -- which means nothing in a dialog nobody
 * navigated to, and which the notice would print as text.
 *
 * @param Throwable $error
 * @return string
 */
function pg_settings_pane_complaint($error)
{
    return trim(preg_replace('/\s+/', ' ', strip_tags($error->getMessage())));
}


/**
 * A sentence on its way to the dialog.
 *
 * The notice area sets its text, not its markup, and that is the right way
 * round: these sentences come from save modules, from tools that left one
 * behind on their way here, and -- through them -- from services like
 * Mailchimp. None of that belongs in the page as markup.
 *
 * Text is therefore what they are turned into. Tags are dropped rather than
 * shown: a notice that carries "<textarea>" would otherwise print the tag and
 * bury the DNS record inside it. Entities are decoded for the same reason --
 * a message escaped for a screen arrives here as "=&gt;" and there is no HTML
 * left for it to be escaped against.
 *
 * @param string $message
 * @return string
 */
function pg_settings_pane_words($message)
{
    return trim(html_entity_decode(strip_tags((string) $message), ENT_QUOTES, 'UTF-8'));
}


// From here on an error is a JSON error.
pg_error_throws(true);

$liveform = new liveform('settings');

$pane = isset($_REQUEST['pane']) ? (string) $_REQUEST['pane'] : '';

if (pg_settings_category($pane) === null) {
    pg_settings_pane_respond(array('status' => 'error', 'message' => lang('Page not found.')), 404);
}


if (!$_POST) {

    try {
        $fragment = pg_settings_fragment($pane, $user, $liveform);

    } catch (Throwable $error) {
        pg_settings_pane_respond(array('status' => 'error', 'message' => pg_settings_pane_complaint($error)), 500);
    }

    $category = pg_settings_category($pane);

    // What a tool left in this form on its way here. purge_cache.php,
    // clean_up.php, cloudflare.php and software_update.php all finish on a
    // screen of their own and send the operator back to the settings with a
    // sentence to read; there is no settings screen left to read it on, so the
    // dialog is handed it with the first pane it asks for.
    $notices = array();
    $errors  = array();

    foreach ($liveform->get_notices() as $notice) {
        $notices[] = pg_settings_pane_words($notice);
    }

    foreach ($liveform->get_errors() as $error) {
        $errors[] = pg_settings_pane_words($error);
    }

    // The form the cards were drawn from is done with. get_warnings() and
    // output_notices() read the session without clearing it, so anything left
    // here would come back on every opening -- removing it is what makes the
    // hand-over above happen exactly once.
    $liveform->remove_form();

    pg_settings_pane_respond(array(
        'status'      => 'success',
        'notices'     => $notices,
        'errors'      => $errors,
        'pane'        => $pane,
        'title'       => $category['label'],
        'description' => $category['description'],
        'icon'        => $category['icon'],
        'sections'    => $fragment['sections'],
        'html'        => $fragment['html'],
        'modals'      => $fragment['modals'],
        'scripts'     => $fragment['scripts'],
        'meta'        => $fragment['meta'],
        // A fresh token with every pane: the one the modal was opened with may
        // have been spent by a save in another tab, and the modal stays open
        // across many saves.
        'token'       => (isset($_SESSION['software']['token']) ? $_SESSION['software']['token'] : ''),
    ));
}


// ── the save ─────────────────────────────────────────────────────────────

// Same check the screens use. It answers with a screen of its own, which is no
// use here, so the comparison is made directly and refused in JSON.
if (
    !isset($_POST['token'])
    || !isset($_SESSION['software']['token'])
    || !hash_equals((string) $_SESSION['software']['token'], (string) $_POST['token'])
) {
    pg_settings_pane_respond(array(
        'status'  => 'error',
        'message' => lang('Your session has expired. Please sign in again.'),
        'expired' => true,
    ), 403);
}

try {
    $scheme = pg_settings_apply($pane, $user, $liveform);

} catch (Throwable $error) {
    pg_settings_pane_respond(array('status' => 'error', 'message' => pg_settings_pane_complaint($error)), 500);
}

// A save that marked a field did not save, and must not be answered with
// "saved". A module marks rather than throws when the value is the operator's
// mistake and not the software's: Mailchimp's checks its key, its list and its
// store against Mailchimp itself and marks whichever one the service refused.
//
// The messages go up as they are and the pane is deliberately NOT read again:
// the marks live in the form, but repainting from storage would take away
// everything the operator has just typed -- which is the very thing they need
// in front of them to fix it.
if ($liveform->check_form_errors()) {

    $errors = array();

    foreach ($liveform->get_errors() as $error) {
        $errors[] = pg_settings_pane_words($error);
    }

    $liveform->remove_form();

    pg_settings_pane_respond(array(
        'status' => 'invalid',
        'pane'   => $pane,
        'errors' => $errors,
        'token'  => (isset($_SESSION['software']['token']) ? $_SESSION['software']['token'] : ''),
    ));
}

// The same confirmation the screen printed. The save modules do not add it --
// they only speak up about what they could not do -- so it is added here, where
// a save is known to have finished.
$liveform->add_notice(lang('The Site Settings have been saved.'));

// The notices the save left behind, read out of the form rather than shown on a
// page that is never rendered. Removing the form is what stops them coming back
// on the next pane -- get_warnings() and output_notices() read the session, they
// do not clear it.
$notices = array();

foreach ($liveform->get_notices() as $notice) {
    $notices[] = pg_settings_pane_words($notice);
}

$liveform->remove_form();

pg_settings_pane_respond(array(
    'status'   => 'success',
    'pane'     => $pane,
    'notices'  => $notices,
    // Secure Mode can be switched on by the save that just ran. The dialog
    // cannot go on talking to the old scheme: say where the panel lives now
    // and let it move the whole screen there.
    'reload'   => (($scheme !== URL_SCHEME)
        ? ($scheme . HOSTNAME . PATH . SOFTWARE_DIRECTORY . '/welcome.php')
        : ''),
    'meta'     => pg_settings_last_modified(pg_settings_config_row(true)),
    'token'    => (isset($_SESSION['software']['token']) ? $_SESSION['software']['token'] : ''),
));
