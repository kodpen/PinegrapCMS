<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The panel's audit buttons: Analyze HTML Structure (Pages list) and Check
 * Pages Now (accessibility report) run one bounded tick of their pass here,
 * then return to the screen they came from with what the tick did.
 *
 * The pages are rendered as a visitor who is not signed in, as the nightly
 * jobs render them: PG_ANONYMOUS_RENDER keeps initialize_user() from loading
 * the session's user, and the session is closed and left out of the run, so
 * a page behind a membership gate gives the same findings whoever pressed.
 * The caller's role is checked from the session here, and the session is
 * opened again afterwards for the notice; nobody is signed out.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

define('PG_ANONYMOUS_RENDER', true);

include('init.php');

$pass = (isset($_REQUEST['pass']) && ($_REQUEST['pass'] === 'a11y')) ? 'a11y' : 'structure';

validate_token_field();

// Managers and up, as the screens the buttons sit on. The session's user id
// is read here because this request signs nobody in.
$username = (string) ($_SESSION['sessionusername'] ?? '');

$user_id = db_value(
    "SELECT user_id FROM user
    WHERE (user_id = '" . (int) ($_SESSION['sessionuserid'] ?? 0) . "') AND (user_role < '3')");

if ($user_id == '') {
    log_activity(lang('access denied to run a page audit'), $username);
    output_error(lang('Access denied') . '. <a href="javascript:history.go(-1)">' . lang('Go back') . '</a>.');
}

// Where the button returns: the Pages list in the view it was pressed from
// (only a plain filter name is carried; the list checks it again), or the
// accessibility report.
if ($pass === 'a11y') {
    $return = PATH . SOFTWARE_DIRECTORY . '/view_accessibility.php';
    $liveform_name = 'view_accessibility';
} else {
    $return = PATH . SOFTWARE_DIRECTORY . '/view_pages.php';
    $liveform_name = 'view_pages';

    if (isset($_GET['filter']) && is_string($_GET['filter']) && preg_match('/^[a-z_]+$/', $_GET['filter'])) {
        $return .= '?filter=' . urlencode($_GET['filter']);
    }
}

include_once('liveform.class.php');

require_once(dirname(__FILE__) . '/seo.php');
require_once(dirname(__FILE__) . '/seo_structure.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/a11y.php');

if (!pg_seo_structure_schema_ready()) {
    $liveform = new liveform($liveform_name);
    $liveform->add_notice(lang('Please run the software upgrade to enable SEO scores.'));
    go($return);
}

if (!class_exists('DOMDocument')) {
    $liveform = new liveform($liveform_name);
    $liveform->add_notice(lang('The PHP DOM extension is not available, so the HTML structure cannot be analyzed.'));
    go($return);
}

// Deliberately shorter than the job's budget: a browser request that keeps an
// administrator waiting a full minute reads as a hung screen, and the pass
// carries on from its place on the next click or the next job run.
$budget = defined('SEO_ANALYZE_BUTTON_BUDGET') ? (int) SEO_ANALYZE_BUTTON_BUDGET : 20;

// Saved and closed before the first render: the renderers read the session
// directly (the signed-in name, the cart), and a render that ends the process
// must not write an emptied session back.
session_write_close();
$_SESSION = array();

if ($pass === 'a11y') {
    $run = pg_a11y_run($budget);

    if ($run['done']) {
        pg_cron_ran('seo_a11y_job');
    }
} else {
    $run = pg_seo_analyze_batch($budget);
}

// The caller's session again, as it was saved, for the notice.
session_start();

$liveform = new liveform($liveform_name);

if ($pass === 'a11y') {
    if ($run['done']) {
        $liveform->add_notice(lang(array('string' => 'Every published page has been checked ({var:1} in this run).', 'vars' => array($run['checked']))));
    } else {
        $liveform->add_notice(lang(array('string' => '{var:1} pages were checked. Click again to continue with the rest.', 'vars' => array($run['checked']))));
    }

    log_activity(lang('ran the accessibility check'), $username);
} else {
    if ($run['remaining'] > 0) {
        $liveform->add_notice(lang(array(
            'string' => 'The HTML structure of {var:1} record{suffix:1} was analyzed. {var:2} record{suffix:2} still need to be analyzed - click Analyze HTML Structure again to continue.',
            'vars' => array($run['analyzed'], $run['remaining']),
            'suffix' => array((($run['analyzed'] == 1) ? '' : 's'), (($run['remaining'] == 1) ? '' : 's')))));
    } elseif ($run['analyzed'] > 0) {
        $liveform->add_notice(lang(array(
            'string' => 'The HTML structure of {var:1} record{suffix:1} was analyzed.',
            'vars' => $run['analyzed'],
            'suffix' => (($run['analyzed'] == 1) ? '' : 's'))));
    } else {
        // Nothing analyzed and nothing left: the queue was already empty.
        $liveform->add_notice(lang('The HTML structure of every record is already up to date.'));
    }

    log_activity(lang('analyzed HTML structure'), $username);
}

go($return);
