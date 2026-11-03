<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The visual editor's SEO check on the draft: the page the editor holds,
 * saved or not, rendered by the server the way the structure pass renders
 * the saved page (pg_seo_render_page(), the background renderer), so the
 * structure rules run in document mode on the whole page - head, shared
 * components and system widgets included - instead of on the fragment the
 * editor generates.
 *
 * The draft reaches the renderer as a row override: get_page_content() reads
 * pg_seo_draft_override() only while pg_seo_rendering() is on, so a visitor's
 * request never sees it. System widgets draw from the editor's trees and
 * settings through pg_sw_preview_overrides(), as the Preview draws them.
 *
 * Loaded by api.php's designer/seo_check, not by functions.php.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

/**
 * The draft row of the page being checked: page_id plus the fields the
 * editor holds (pg_seo_draft_apply()). Empty when no check is running.
 *
 * @param array|null $set the draft to set, array() to clear, null to read
 * @return array
 */
function pg_seo_draft_override($set = null)
{
    static $draft = array();

    if ($set !== null) {
        $draft = is_array($set) ? $set : array();
    }

    return $draft;
}

/**
 * A page row with the draft's fields in place of the stored ones, when the
 * draft is this page's. Only the content the editor changes is replaced: the
 * address, the folder and the style stay the stored page's, so the render
 * reads the same records and permissions the saved page does. A column the
 * row lacks (an un-migrated database) stays absent, except the body. Pure.
 *
 * @param array|null $row   the page row get_page_content() read
 * @param array      $draft pg_seo_draft_override()
 * @return array|null
 */
function pg_seo_draft_apply($row, $draft)
{
    if (!is_array($row) || !is_array($draft) || empty($draft['page_id'])
        || (int)($row['page_id'] ?? 0) !== (int)$draft['page_id']) {
        return $row;
    }

    foreach (array('page_title', 'page_meta_description', 'page_search_keywords', 'noindex', 'nofollow') as $key) {
        if (array_key_exists($key, $draft) && array_key_exists($key, $row)) {
            $row[$key] = (string)$draft[$key];
        }
    }
    if (array_key_exists('page_tree_code', $draft)) {
        $row['page_tree_code'] = (string)$draft['page_tree_code'];
    }

    return $row;
}

/**
 * The stored findings the check carries over: on a fragment, the
 * document-only ones it could not answer (pg_seo_document_only_codes()) and
 * did not find; on a rendered draft, none - the draft answers them itself,
 * and the stored ones describe the page as it was last saved. Pure.
 *
 * @param array $raw      code => finding of this check
 * @param array $stored   pg_seo_load_issues() rows
 * @param bool  $document whether the draft was rendered as a whole page
 * @return array code => array(severity, occurrences, detail)
 */
function pg_seo_check_carried($raw, $stored, $document)
{
    if ($document) {
        return array();
    }

    $carried = array();
    $codes = pg_seo_document_only_codes();

    foreach ((array)$stored as $issue) {
        $code = (string)($issue['code'] ?? '');
        if (!in_array($code, $codes, true) || isset($raw[$code]) || isset($carried[$code])) {
            continue;
        }
        $carried[$code] = array(
            'severity'    => (string)$issue['severity'],
            'occurrences' => (int)$issue['occurrences'],
            'detail'      => (string)$issue['detail'],
        );
    }

    return $carried;
}

/**
 * The structure pass's context (pg_seo_document_context()) for a rendered
 * draft, built from the editor's fields.
 *
 * @param array  $row       seo_check's page fields: page_title, sitemap
 *                          ('1'/'0'), folder_public, folder_archived
 * @param string $page_type the stored page's type
 * @return array
 */
function pg_seo_check_draft_context($row, $page_type)
{
    require_once(PG_FUNCTIONS_DIR . '/seo_structure.php');

    return pg_seo_document_context(
        (string)($row['page_title'] ?? ''),
        ((string)($row['sitemap'] ?? '') === '1') && !empty($row['folder_public']) && empty($row['folder_archived']),
        $page_type
    );
}

/**
 * Render the editor's draft of a saved page as the structure pass renders
 * the saved one. Returns NULL when the draft cannot be rendered here (a page
 * not saved yet, one the user may not edit, a type the pass does not render,
 * a tree the save would refuse): the check then falls back to the fragment.
 * Otherwise array(html, error, page_type) from pg_seo_render_page().
 *
 * The tree goes through the save's own checks and permission gate
 * (pg_designer_gate_tree()): what is rendered is what Save would write. The
 * editor's widget trees are drawn only for a designer; a content-level
 * operator's draft draws the stored widgets, as their save cannot change
 * them either.
 *
 * @param int   $page_id
 * @param array $fields  page_title, page_meta_description,
 *                       page_search_keywords, noindex, nofollow
 * @param string $tree_json the editor's tree
 * @param array $widgets [{id, tree_json, system_region_config}], as
 *                       designer/preview_widgets takes them
 * @param array $user    validate_user() row
 * @return array|null
 */
function pg_seo_check_draft_render($page_id, $fields, $tree_json, $widgets, $user)
{
    $page_id = (int)$page_id;
    if ($page_id <= 0 || trim((string)$tree_json) === '') {
        return NULL;
    }

    require_once(PG_FUNCTIONS_DIR . '/includes/designer_access.php');
    if (pg_designer_page_access($page_id, $user) !== 'edit') {
        return NULL;
    }

    $page_row = db_item(
        "SELECT page.page_id, page.page_name, page.page_title, page.page_type, page.page_style,
                page.sitemap, page.system_region_header, page.system_region_footer, page.page_folder
         FROM page WHERE page.page_id = '" . $page_id . "' LIMIT 1");
    if (!$page_row) {
        return NULL;
    }

    $vt = _validate_and_clean_tree_json((string)$tree_json);
    if (!$vt['ok']) {
        return NULL;
    }
    $tree = json_decode(!empty($vt['cleaned_json']) ? $vt['cleaned_json'] : (string)$tree_json, true);
    $gate = pg_designer_gate_tree($tree, $page_id, $user);
    if (!$gate['ok'] || !is_array($gate['tree'])) {
        return NULL;
    }
    $tree = $gate['tree'];
    _sanitize_dynamic_placeholders($tree);

    $abc = (string)db_value("SELECT additional_body_classes FROM style WHERE style_id = '" . (int)$page_row['page_style'] . "' LIMIT 1");
    // Elements carry their node ids in this render (data-pg-node), so a
    // finding badges its node on the canvas; the page tree and every widget
    // and shared component drawn into it alike.
    pg_designer_audit_render(true);
    try {
        $draft = array('page_id' => $page_id, 'page_tree_code' => generate_style_code_from_tree($tree, $page_row['page_name'], $abc));
    } finally {
        pg_designer_audit_render(false);
    }
    foreach (array('page_title', 'page_meta_description', 'page_search_keywords', 'noindex', 'nofollow') as $key) {
        if (array_key_exists($key, (array)$fields)) {
            $draft[$key] = (string)$fields[$key];
        }
    }

    $overrides = pg_designer_is_full($user) ? pg_designer_preview_override_rows($widgets) : array();

    require_once(PG_FUNCTIONS_DIR . '/seo_structure.php');

    // The session is lent empty for the render, as the audit buttons render
    // (audit_run.php): what the page draws for the signed-in editor - a
    // basket, a member's name - is not what a visitor is served.
    $saved_session = isset($_SESSION) ? $_SESSION : null;
    $_SESSION = array();
    pg_seo_draft_override($draft);
    pg_designer_audit_render(true);
    pg_sw_preview_overrides($overrides);
    try {
        $render = pg_seo_render_page($page_id, $page_row);
    } finally {
        pg_designer_audit_render(false);
        pg_seo_draft_override(array());
        pg_sw_preview_overrides(array());
        if ($saved_session === null) {
            unset($_SESSION);
        } else {
            $_SESSION = $saved_session;
        }
    }

    if ($render === NULL) {
        return NULL;
    }
    $render['page_type'] = (string)$page_row['page_type'];

    return $render;
}

/**
 * The audit rule registry as the editor's audit panel lists it: one row per
 * concept with its label (the accessibility report's wording, else the SEO
 * one; '' for a concept only the editor checks, which it labels itself), the
 * SEO and accessibility codes and severities, the WCAG criterion and level,
 * the editor rule ids and whether only a browser measures it in full.
 *
 * @return array[]
 */
function pg_seo_check_concepts()
{
    require_once(PG_FUNCTIONS_DIR . '/includes/fn/audit_rules.php');
    require_once(PG_FUNCTIONS_DIR . '/includes/fn/a11y.php');

    $rows = array();

    foreach (pg_audit_rules() as $concept => $rule) {
        $label = '';
        if ($rule['a11y'] !== '') {
            $label = pg_a11y_issue_label($rule['a11y']);
        } elseif ($rule['seo'] !== '' && function_exists('pg_seo_issue_label')) {
            $label = pg_seo_issue_label($rule['seo'], 1, '');
        }
        $rows[] = array(
            'concept'       => $concept,
            'label'         => $label,
            'seo'           => $rule['seo'],
            'seo_severity'  => isset($rule['severity']['seo']) ? $rule['severity']['seo'] : '',
            'a11y'          => $rule['a11y'],
            'a11y_severity' => isset($rule['severity']['a11y']) ? $rule['severity']['a11y'] : '',
            'criterion'     => $rule['criterion'],
            'level'         => $rule['level'],
            'editor'        => $rule['editor'],
            'browser'       => $rule['browser'],
        );
    }

    return $rows;
}

/**
 * The draft's accessibility findings (pg_a11y_check_html()) as the panel
 * lists them: concept, code, label, severity, WCAG criterion and level,
 * occurrences, the first example and the editor nodes they were found on.
 *
 * @param array $findings code => finding
 * @return array[]
 */
function pg_seo_check_a11y_rows($findings)
{
    require_once(PG_FUNCTIONS_DIR . '/includes/fn/audit_rules.php');
    require_once(PG_FUNCTIONS_DIR . '/includes/fn/a11y.php');

    $rules = pg_a11y_rules();
    $rows = array();

    foreach ((array) $findings as $code => $f) {
        if (!isset($rules[$code])) {
            continue;
        }
        $rows[] = array(
            'concept'     => pg_audit_rule_concept('a11y', $code),
            'code'        => $code,
            'label'       => pg_a11y_issue_label($code),
            'severity'    => (string) $f['severity'],
            'criterion'   => $rules[$code]['criterion'],
            'level'       => $rules[$code]['level'],
            'occurrences' => (int) $f['occurrences'],
            'detail'      => (string) $f['detail'],
            'nodes'       => isset($f['nodes']) ? array_values((array) $f['nodes']) : array(),
        );
    }

    return $rows;
}
