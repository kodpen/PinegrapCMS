<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Offers the articles of one folder that match what a visitor types in a
 * form's field: GET ?folder=<folder id>&q=<text>, answered in JSON
 * {"results": [{"title", "url", "excerpt"}]}. A control set to "Offer
 * articles from" a folder (pg_cf_mark_article_suggest()) calls it after a
 * pause in typing.
 *
 * The search is the Search Results widget's "Search only in" that folder
 * (_pg_sw_search_collect() with search_folder_id): the folder and its
 * sub-folders from the site search index, only what the visitor may view,
 * the site's synonyms included. Public and read-only, so it takes no form
 * token; each address may ask PG_ARTICLE_SUGGEST_LIMIT times in
 * PG_ARTICLE_SUGGEST_WINDOW seconds (pg_article_suggest_throttled()).
 * The question is not counted as a site search.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

include('init.php');

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: private, no-store');
header('X-Robots-Tag: noindex');

$status = 200;
$reply = array('results' => array());
$method = isset($_SERVER['REQUEST_METHOD']) ? (string) $_SERVER['REQUEST_METHOD'] : '';
$folder = (isset($_GET['folder']) && is_scalar($_GET['folder'])) ? (int) $_GET['folder'] : 0;
$query = pg_article_suggest_query(isset($_GET['q']) ? $_GET['q'] : null);

if ($method !== 'GET') {
    header('Allow: GET');
    $status = 405;
    $reply = array('error' => 'method');
} elseif ($folder <= 0) {
    $status = 400;
    $reply = array('error' => 'folder');
} elseif ($query !== '') {
    if (pg_article_suggest_throttled()) {
        $status = 429;
        $reply = array('error' => 'rate');
    } else {
        $found = _pg_sw_search_collect($query, array('search_scope' => 'pages', 'search_folder_id' => $folder));
        $reply['results'] = pg_article_suggest_rows($found, PG_ARTICLE_SUGGEST_ROWS);
    }
}

http_response_code($status);
echo json_encode($reply, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
