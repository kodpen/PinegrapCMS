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
 *              2016–2025 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

/**
 * Google product taxonomy: search.
 *
 * The taxonomy is ~5,595 categories. Sending it to the browser would be roughly
 * 785 KB of markup and 5,595 DOM nodes that select2 then copies into its own
 * results list and rescans on every keystroke, so the list stays here and only
 * matches travel.
 *
 * The list is a file shipped with the software, so there is nothing to download
 * and no state to change here: this endpoint only reads.
 *
 * Answers JSON, including on failure — the caller is a fetch() and an HTML
 * error page arriving where JSON is expected reads to the operator as "nothing
 * happened".
 */

include('init.php');
include_once('product_builder.php');


/**
 * Send a JSON response and stop.
 *
 * @param array $data
 * @return void
 */
function pg_gt_respond($data)
{
    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');

    print encode_json($data);
    exit();
}


$user = validate_user();

// Same rule as validate_ecommerce_access(), answering in JSON.
if (!($user['role'] < 3 || $user['manage_ecommerce'] == TRUE)) {
    log_activity(lang('access denied to commerce'), $_SESSION['sessionusername']);
    pg_gt_respond(array('error' => lang('Access denied')));
}

$action = isset($_REQUEST['action']) ? $_REQUEST['action'] : 'search';

switch ($action) {

    /* ---------------------------------------------------------- search */

    // Read-only and idempotent, so no CSRF token: it changes nothing and the
    // worst a forged request achieves is a list of category names the attacker
    // could have downloaded from Google directly.
    case 'search':

        $query   = isset($_REQUEST['q']) ? (string) $_REQUEST['q'] : '';
        $matches = pg_pb_google_taxonomy_search($query, 30);

        $results = array();

        foreach ($matches as $match) {
            // The id is what gets stored on the product; the path is what the
            // operator reads. Google accepts either in the feed, but the id is
            // language-independent and survives a category being renamed.
            $results[] = array(
                'id'   => $match['id'],
                'text' => ($match['id'] === $match['path'])
                    ? $match['path']
                    : $match['path'] . '  (' . $match['id'] . ')',
            );
        }

        pg_gt_respond(array('results' => $results));

        break;

    default:
        pg_gt_respond(array('error' => lang('Page not found.')));
}
