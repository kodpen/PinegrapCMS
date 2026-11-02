<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Run now on the Search and audits screen (search_audits.php): one bounded
 * tick of the SEO score, HTML structure or accessibility pass, answered in
 * JSON; the screen asks again until the pass is done. The search index is
 * driven through update_search_index.php instead.
 *
 * The pages are rendered as a visitor who is not signed in, as the nightly
 * jobs render them (PG_ANONYMOUS_RENDER, the session closed during the run),
 * like audit_run.php. The tick takes the job's lock, so it never runs beside
 * a dispatched tick or another Run now of the same pass.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

define('PG_ANONYMOUS_RENDER', true);

include('init.php');

require_once(dirname(__FILE__) . '/seo.php');
require_once(dirname(__FILE__) . '/seo_structure.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/a11y.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/search_audits.php');

$username = pg_search_audits_json_gate();

$key = (isset($_POST['engine']) && is_string($_POST['engine']) && in_array($_POST['engine'], array('seo', 'structure', 'a11y'), true)) ? $_POST['engine'] : '';

if ($key === '') {
    pg_search_audits_refuse('engine', lang('Invalid request.'), 400);
}

$engines = pg_search_audits_engines();
$job = $engines[$key]['job'];

// Read by the render path; this request has no route to define it.
if (!defined('REQUEST_URL')) {
    define('REQUEST_URL', defined('PATH') ? PATH : '/');
}

if (!pg_cron_claim($job)) {
    pg_search_audits_refuse('busy', lang('Another run of this pass is going. Try again when it has finished.'), 409);
}

register_shutdown_function('pg_cron_release', $job);

// Short, as the panel's other audit buttons: the screen asks again.
$budget = defined('SEO_ANALYZE_BUTTON_BUDGET') ? (int) SEO_ANALYZE_BUTTON_BUDGET : 20;

// Saved and closed before the first render: the renderers read the session
// directly, and a render that ends the process must not write an emptied
// session back.
session_write_close();
$_SESSION = array();

$run = pg_search_audits_tick($key, $budget, !empty($_POST['start']));

if (!$run['ready']) {
    pg_search_audits_refuse('unavailable', lang('This pass needs the software upgrade and the PHP DOM extension.'), 409);
}

if (!empty($_POST['start'])) {
    log_activity(lang(array('string' => 'started {var:1} from Search and audits', 'vars' => array($engines[$key]['label']))), $username);
}

$message = $run['failed']
    ? lang(array('string' => '{var:1} done in this request, {var:2} could not be rendered.', 'vars' => array($run['count'], $run['failed'])))
    : lang(array('string' => '{var:1} done in this request.', 'vars' => array($run['count'])));

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
echo json_encode(array(
    'ok'      => true,
    'done'    => (bool) $run['done'],
    'count'   => (int) $run['count'],
    'failed'  => (int) $run['failed'],
    'message' => $message,
), JSON_UNESCAPED_UNICODE);
