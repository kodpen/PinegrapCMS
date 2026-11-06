<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The panel's preview address: what a live picture or a tour shows, for an
 * operator who may open the Visual Page Editor.
 *
 *   ?template=&page=&look=&palette=   one page of a template, drawn from its
 *                                     file (pg_design_template_preview_html()),
 *                                     custom templates too; its links lead to
 *                                     the template's other pages here.
 *   ...&still=1                       the same without its scripts, for a
 *                                     live picture (its frame runs none).
 *   ...&tour=1                        the template's tour; its button opens
 *                                     the editor on the template.
 *   ?page_id=                         a visual design page, drafts too, as a
 *                                     first-time visitor sees it: no script,
 *                                     no consent banner, chat bubble or staff
 *                                     bar, no visit counted, the page cache
 *                                     neither read nor written.
 *
 * PG_ANONYMOUS_RENDER keeps initialize_user() from signing the request in,
 * as in audit_run.php: the operator is read from the session here, and the
 * session is closed before anything is drawn (the editor home loads many of
 * these at once, and an open session would make them wait for each other).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

define('PG_ANONYMOUS_RENDER', true);

include('init.php');

include_once('includes/designer_access.php');

header('Content-Type: text/html; charset=utf-8');
header('X-Robots-Tag: noindex');
header('X-Frame-Options: SAMEORIGIN');

// A plain page with a status, and the end of the request.
$pg_design_preview_end = function ($status, $text) {
    http_response_code($status);
    header('Cache-Control: no-store');
    print pg_design_preview_placeholder_html($text);
    exit();
};

if (!isset($_SERVER['REQUEST_METHOD']) || !in_array($_SERVER['REQUEST_METHOD'], array('GET', 'HEAD'), true)) {
    header('Allow: GET, HEAD');
    $pg_design_preview_end(405, lang('Method not allowed.'));
}

// The operator, from the session: anyone the editor admits.
$session_user_id = (int) ($_SESSION['sessionuserid'] ?? 0);

$operator = ($session_user_id > 0)
    ? db_item("SELECT user_id, user_role FROM user WHERE user_id = '" . $session_user_id . "'" . pg_user_enabled_sql() . " LIMIT 1")
    : null;

$user = $operator ? array('id' => (int) $operator['user_id'], 'role' => (int) $operator['user_role']) : null;

if (!$user || (pg_designer_access($user) === PG_DESIGNER_ACCESS_NONE)) {
    session_write_close();
    $pg_design_preview_end(403, lang('Access denied'));
}

session_write_close();

$request = pg_design_preview_request($_GET);

if ($request['kind'] === '') {
    $pg_design_preview_end(404, lang('There is nothing to preview.'));
}

// A template page, or its tour.
if ($request['kind'] !== 'page') {
    $template = pg_design_template($request['template']);

    if (!$template) {
        $pg_design_preview_end(404, lang('The template could not be found.'));
    }

    if ($request['kind'] === 'tour') {
        $html = pg_design_tour_html(array(
            'where' => 'panel',
            'template' => $request['template'],
            'page' => $request['page'],
            'look' => $request['look'],
            'palette' => $request['palette'],
            'continue_href' => pg_design_preview_use_href($request['template'], $request['look'], $request['palette']),
            'continue_label' => lang('Use This Template'),
        ));
    } else {
        $page = $request['page'];

        if ($page === '') {
            $pages = pg_design_template_preview_pages($template);
            $page = $pages ? (string) key($pages) : '';
        }

        $link = pg_design_preview_address('panel', array(
            'template' => $request['template'],
            'look' => $request['look'],
            'palette' => $request['palette'],
            'page' => '{page}',
        ));

        $html = ($page !== '')
            ? pg_design_template_preview_html($request['template'], $page, $request['look'], $request['palette'], $link)
            : '';
    }

    if ($html === '') {
        $pg_design_preview_end(404, lang('This page of the template cannot be previewed.'));
    }

    header('Cache-Control: private, max-age=300');
    print $request['still'] ? pg_design_preview_strip_scripts($html) : $html;
    exit();
}

// A visual design page. One the operator may not know of reads as missing.
$page_row = db_item(
    "SELECT page.page_id, page.page_name, page.page_type, page.page_folder, page.page_style,
        page.page_timestamp, style.style_timestamp
    FROM page
    LEFT JOIN style ON style.style_id = page.page_style
    WHERE (page.page_id = '" . (int) $request['page_id'] . "') AND " . pg_page_visual_design_sql() . pg_designer_not_binned_sql() . "
    LIMIT 1");

if (!$page_row || (pg_designer_page_access($page_row, $user) === 'hidden')) {
    $pg_design_preview_end(404, lang('This page cannot be previewed.'));
}

$etag = pg_design_preview_etag($page_row['page_id'], $page_row['page_timestamp'], $page_row['style_timestamp'], defined('VERSION') ? VERSION : '');

header('Cache-Control: private, max-age=300');
header('ETag: ' . $etag);

if (pg_design_preview_etag_matches($_SERVER['HTTP_IF_NONE_MATCH'] ?? '', $etag)) {
    http_response_code(304);
    exit();
}

// Drawn as a first-time visitor: no session and no cookies. The background
// render (pg_seo_render_page()) leaves out the consent banner and the
// statistics tag and counts no visit; the staff bar, the chat bubble, the
// visit record and the page cache belong to get_page.php, which is not used.
$_SESSION = array();
$_COOKIE = array();

require_once(dirname(__FILE__) . '/seo.php');
require_once(dirname(__FILE__) . '/seo_structure.php');

$render = pg_seo_render_page((int) $page_row['page_id'], $page_row);

if (($render === null) || ($render['error'] !== '') || (trim($render['html']) === '')) {
    $pg_design_preview_end(404, lang('This page cannot be previewed.'));
}

print pg_design_preview_strip_scripts($render['html']);
