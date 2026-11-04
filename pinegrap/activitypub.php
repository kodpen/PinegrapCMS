<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The site's ActivityPub documents (includes/fn/activitypub.php), handed
 * over by router.php, which names the document in PG_AP_ROUTE:
 *
 *   webfinger     /.well-known/webfinger?resource=acct:<handle>@<host>
 *   nodeinfo      /.well-known/nodeinfo
 *   nodeinfo-2.1  /.well-known/nodeinfo/2.1
 *   actor         /@<handle>; a browser is sent to the home page
 *   followers     /@<handle>/followers
 *   outbox        /@<handle>/outbox
 *
 * Asked for at its own address, or while the feature is off, it answers 404.
 * Called by other servers, it runs with no session.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_NO_SESSION')) {
    define('PG_NO_SESSION', true);
}

$_SESSION = array();

include(dirname(__FILE__) . '/init.php');

require_once(PG_FUNCTIONS_DIR . '/includes/fn/activitypub.php');

$ap_route = defined('PG_AP_ROUTE') ? PG_AP_ROUTE : '';

if (!pg_ap_enabled() || !in_array($ap_route, array('webfinger', 'nodeinfo', 'nodeinfo-2.1', 'actor', 'followers', 'outbox'), true)) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    exit();
}

// A person who opens the actor's address in a browser sees the site.
if (($ap_route === 'actor') && !pg_ap_accepts_activity(isset($_SERVER['HTTP_ACCEPT']) ? $_SERVER['HTTP_ACCEPT'] : '')) {
    header('Vary: Accept');
    header('Location: ' . URL_SCHEME . HOSTNAME_SETTING . PATH, true, 302);
    exit();
}

$ap_site = pg_ap_site();

switch ($ap_route) {
    case 'webfinger':
        $ap_answer = pg_ap_webfinger(isset($_GET['resource']) && is_string($_GET['resource']) ? $_GET['resource'] : '', $ap_site);

        if ($ap_answer === null) {
            pg_ap_json(array('error' => 'Not found'), 'application/json', 404);
        }

        pg_ap_json($ap_answer, 'application/jrd+json');
        break;

    case 'nodeinfo':
        pg_ap_json(pg_ap_nodeinfo_links($ap_site), 'application/json');
        break;

    case 'nodeinfo-2.1':
        pg_ap_json(pg_ap_nodeinfo($ap_site), 'application/json; profile="http://nodeinfo.diaspora.software/ns/schema/2.1#"');
        break;

    case 'followers':
        pg_ap_json(pg_ap_followers_collection($ap_site));
        break;

    case 'outbox':
        require_once(PG_FUNCTIONS_DIR . '/includes/fn/activitypub_outbox.php');
        pg_ap_json(pg_ap_outbox_collection($ap_site, pg_ap_outbox_items(20)));
        break;

    default:
        pg_ap_json(pg_ap_actor($ap_site));
}
