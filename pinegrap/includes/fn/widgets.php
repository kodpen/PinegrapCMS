<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// A module of functions.php: The remaining system widgets: form list and item view, my account, login, registration, search results, order view, membership, custom form, calendar view.
//
// Loaded by functions.php through require_once, never on its own. The
// functions were moved here from functions.php as they were; PG_FUNCTIONS_DIR
// is the software directory (what dirname(__FILE__) was in functions.php),
// see the note at the top of functions.php.
if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

/**
 * A month's name in the site's language, for the 'd M Y' long-date format.
 *
 * Keeps the month out of date()'s own locale: date('F') is always English, so
 * it is used as the lang() key and translated from there. Replaces the four
 * hard-coded Turkish month arrays this file used to carry.
 *
 * @param  int    $month  1-12
 * @return string         '' when the number is out of range
 */
function pg_widget_month_name($month)
{
    $month = (int)$month;
    if ($month < 1 || $month > 12) return '';
    return lang(date('F', mktime(0, 0, 0, $month, 1, 2000)));
}

// Render a system widget's form_list_view. Loop-area aware:
//
//   1. _split_widget_tree pulls the loop_area out of the tree (replaced by a marker).
//   2. Static portion of the tree is rendered ONCE — search box, header, pagination,
//      empty-state messages, etc. live here.
//   3. Loop_area's children are compiled into a per-record template; each submitted_form
//      gets a clone with ^^token^^ placeholders replaced.
//   4. The accumulated loop output is str_replace'd into the static HTML at the marker.
//
// If the widget has NO loop_area (legacy / non-loop widgets), the entire tree is
// treated as the per-record template (old behavior preserved for backwards-compat).
//
// Tokens supported (per record):
//   ^^{form_field.name}^^   — value from form_data.data for the field with that name
//   ^^{form_field.label}^^  — also matched (case-insensitive + slugified variants)
//   ^^__id^^                — forms.id (raw integer)
//   ^^__reference^^         — forms.reference_code
//   ^^__timestamp^^         — formatted submitted_timestamp
//   ^^__detail_url^^        — currently empty (form_item_view widget will populate)
//   ^^__user^^              — forms.user_id (raw integer)
//
// Config (`system_region_config`) keys used here:
//   items_per_page      int (default 10, max 500)
//   search_enabled      bool (default false)
//   search_fields       array<string>  form_fields.name tokens to search on
//   empty_message       string         shown when no records match (default "Kayıt bulunamadı.")
//   order_by_field      string  __timestamp | __id | form_fields.name
//   order_by_direction  ASC | DESC (default DESC)
//
// URL parameters (shared across the page — if multiple system widgets render on
// the same page they share `page_number` / `query` state; cleaner URLs at the
// cost of per-widget independence):
//   page_number   page index (1-based)
//   query         search query string
//
// NOTE: We deliberately avoid the bare key `page` because Pinegrap's router
// uses `$_GET['page']` as the URL slug (set by .htaccess rewrite). A
// pagination link of the form `?page=2` would overwrite the slug and break
// routing entirely. Match the legacy form_list_view's `page_number` instead.
function _render_system_widget_form_list($custom_form_page_id, $tree_json, $widget_id, $cfg = array())
{
    $custom_form_page_id = (int)$custom_form_page_id;
    $widget_id           = (int)$widget_id;
    if ($custom_form_page_id <= 0 || $tree_json === '' || $tree_json === null) return '';
    if (!is_array($cfg)) $cfg = array();

    // ── Resolve config values with defaults ───────────────────────────────
    $items_per_page = isset($cfg['items_per_page']) ? (int)$cfg['items_per_page'] : 10;
    if ($items_per_page <= 0)  $items_per_page = 10;
    if ($items_per_page > 500) $items_per_page = 500;

    // Outer dataset cap. 0 / missing = unlimited. Capped at 100k to keep
    // pathological values from killing the server even if config is tampered.
    $max_results = isset($cfg['max_results']) ? (int)$cfg['max_results'] : 0;
    if ($max_results < 0) $max_results = 0;
    if ($max_results > 100000) $max_results = 100000;

    $search_enabled = !empty($cfg['search_enabled']);
    $search_fields  = (isset($cfg['search_fields']) && is_array($cfg['search_fields'])) ? $cfg['search_fields'] : array();

    // search_width → Bootstrap utility class on the search wrapper. Whitelisted
    // so config tampering can't inject arbitrary classes.
    $search_width_map = array(
        'full'    => 'w-100',
        'half'    => 'w-50',
        'quarter' => 'w-25',
        'auto'    => 'w-auto',
    );
    $search_width_key = (isset($cfg['search_width']) && isset($search_width_map[$cfg['search_width']]))
                            ? $cfg['search_width'] : 'full';
    $search_width_cls = $search_width_map[$search_width_key];

    // Placeholder text for the search input. Empty config falls back to "Ara...".
    $search_label = (isset($cfg['search_label']) && is_string($cfg['search_label']) && $cfg['search_label'] !== '')
                        ? substr($cfg['search_label'], 0, 100)
                        : 'Ara...';

    $empty_message  = (isset($cfg['empty_message']) && is_string($cfg['empty_message']) && $cfg['empty_message'] !== '')
                        ? $cfg['empty_message']
                        : lang('No records found.');

    $order_by_field    = isset($cfg['order_by_field']) ? (string)$cfg['order_by_field'] : '__timestamp';
    $order_by_direction = (isset($cfg['order_by_direction']) && strtoupper($cfg['order_by_direction']) === 'ASC') ? 'ASC' : 'DESC';

    // viewer_filter family. Sub-toggles default to true once the parent is on,
    // mirroring the legacy form_list_view defaults.
    $viewer_filter           = !empty($cfg['viewer_filter']);
    $viewer_filter_submitter = isset($cfg['viewer_filter_submitter']) ? !empty($cfg['viewer_filter_submitter']) : true;
    $viewer_filter_watcher   = isset($cfg['viewer_filter_watcher'])   ? !empty($cfg['viewer_filter_watcher'])   : true;
    $viewer_filter_editor    = isset($cfg['viewer_filter_editor'])    ? !empty($cfg['viewer_filter_editor'])    : true;

    // ── URL params (shared) ───────────────────────────────────────────────
    // Note: `page` and `search` are page-wide. Multiple system widgets on the
    // same page share state — acceptable trade-off for clean URLs in the
    // current scope (typically one list widget per page).
    $page_param   = 'page_number';
    $query_param  = 'query';
    $current_page = isset($_GET[$page_param]) ? max(1, (int)$_GET[$page_param]) : 1;
    $search_query = ($search_enabled && isset($_GET[$query_param])) ? trim((string)$_GET[$query_param]) : '';
    if (strlen($search_query) > 100) $search_query = substr($search_query, 0, 100);

    // Decode + split the widget tree into static + loop-template halves.
    $tree_decoded = json_decode($tree_json, true);
    if (!is_array($tree_decoded)) return '';
    // Per-widget messages safety net — auto-prepends a Messages content
    // node if the designer hasn't placed one. formName left empty so
    // every pending session message is shown until per-widget liveform
    // names are wired up individually.
    _pg_inject_messages_node($tree_decoded, '');
    $split = _split_widget_tree($tree_decoded);

    // Render the loop template (per-record). If no loop_area was found, fall back to
    // rendering the whole tree as the template (legacy widgets without a loop_area).
    if ($split['loop_children'] === null) {
        $loop_template = trim(_render_tree_node($tree_decoded, 0, 0));
        $static_html   = '';  // no static surround — output is purely the loop
    } else {
        $loop_template = '';
        foreach ($split['loop_children'] as $child) {
            $loop_template .= _render_tree_node($child, 0, 0);
        }
        $loop_template = trim($loop_template);
        $static_html   = trim(_render_tree_node($split['static_tree'], 0, 0));
    }

    // ── Resolve order_by field to a SQL clause (whitelist!) ───────────────
    // Built-ins map to forms.* columns; otherwise the value must match a real
    // form_fields.name for THIS form_pid — anything else falls back to the
    // safe default (submitted_timestamp). No raw user input ever hits SQL.
    //
    // Resolves a single token to {sql, join_alias} with optional secondary
    // sort-data join. Returns null for "no contribution" (empty/invalid input).
    $_resolve_order = function ($field, $direction, $alias) use ($custom_form_page_id) {
        if ($field === '' || $field === null) return null;
        if ($field === '__id') {
            return array('sql' => "forms.id $direction", 'join' => '');
        }
        if ($field === '__timestamp') {
            return array('sql' => "forms.submitted_timestamp $direction", 'join' => '');
        }
        $fid = (int)db_value(
            "SELECT id FROM form_fields
             WHERE page_id = '" . e($custom_form_page_id) . "'
               AND name = '" . e($field) . "'
             LIMIT 1"
        );
        if ($fid <= 0) return null;
        $join = "LEFT JOIN form_data $alias
                   ON $alias.form_id = forms.id
                  AND $alias.form_field_id = '$fid'";
        return array('sql' => "$alias.data $direction", 'join' => $join);
    };

    $order_join   = '';
    $order_parts  = array();
    $primary = $_resolve_order($order_by_field, $order_by_direction, 'sort_data');
    if ($primary) {
        $order_parts[] = $primary['sql'];
        if ($primary['join']) $order_join .= ' ' . $primary['join'];
    } else {
        // Fallback to the safe default if the configured primary is invalid.
        $order_parts[] = "forms.submitted_timestamp $order_by_direction";
    }

    // Secondary sort — cfg key is order_by_2_field; same whitelist guard.
    $order_by_2_field     = isset($cfg['order_by_2_field']) ? (string)$cfg['order_by_2_field'] : '';
    $order_by_2_direction = (isset($cfg['order_by_2_direction']) && strtoupper($cfg['order_by_2_direction']) === 'ASC') ? 'ASC' : 'DESC';
    if ($order_by_2_field !== '') {
        $secondary = $_resolve_order($order_by_2_field, $order_by_2_direction, 'sort_data2');
        if ($secondary) {
            $order_parts[] = $secondary['sql'];
            if ($secondary['join']) $order_join .= ' ' . $secondary['join'];
        }
    }
    // Final tie-breaker so identical primary/secondary values don't reorder
    // mid-page on subsequent loads.
    $order_parts[] = "forms.id $order_by_direction";
    $order_by_sql  = implode(', ', $order_parts);

    // ── Resolve search-field tokens to ids (whitelist) ────────────────────
    $search_field_ids = array();
    if ($search_enabled && $search_query !== '' && !empty($search_fields)) {
        $clean = array();
        foreach ($search_fields as $f) {
            $f = trim((string)$f);
            if ($f !== '') $clean[] = "'" . e($f) . "'";
        }
        if ($clean) {
            $clean_in = implode(',', $clean);
            $rows = db_items(
                "SELECT id FROM form_fields
                 WHERE page_id = '$custom_form_page_id'
                   AND name IN ($clean_in)"
            );
            if (is_array($rows)) {
                foreach ($rows as $r) { $search_field_ids[] = (int)$r['id']; }
            }
        }
    }
    $search_active = $search_enabled && $search_query !== '' && !empty($search_field_ids);

    // ── viewer_filter SQL (audience-based row visibility) ─────────────────
    // Ported from get_form_list_view.php's logic. Three audiences can be
    // ORed: submitter, editor, watcher. Empty/anonymous viewer or all-three-
    // disabled with the parent on => "no rows" (TRUE=FALSE).
    $sql_viewer_filter = '';
    if ($viewer_filter) {
        $all_disabled = !$viewer_filter_submitter && !$viewer_filter_watcher && !$viewer_filter_editor;
        $logged_in    = (defined('USER_LOGGED_IN') && USER_LOGGED_IN === true);
        if ($all_disabled || !$logged_in) {
            $sql_viewer_filter = ' AND (TRUE = FALSE) ';
        } else {
            // Editor short-circuit: if the viewer can edit the custom form, they
            // see everything — no per-row audience filter needed.
            $edit_custom_form_access = false;
            if ($viewer_filter_editor) {
                if (defined('USER_ROLE') && USER_ROLE < 3) {
                    $edit_custom_form_access = true;
                } else {
                    $custom_form_folder_id = db_value(
                        "SELECT page_folder FROM page WHERE page_id = '" . e($custom_form_page_id) . "'"
                    );
                    if (function_exists('check_edit_access') && check_edit_access($custom_form_folder_id) === true) {
                        $edit_custom_form_access = true;
                    }
                }
            }

            if (!$viewer_filter_editor || !$edit_custom_form_access) {
                $clauses = array();
                $uid     = defined('USER_ID') ? USER_ID : 0;
                $umail   = defined('USER_EMAIL_ADDRESS') ? USER_EMAIL_ADDRESS : '';

                if ($viewer_filter_submitter) {
                    $clauses[] = "(forms.user_id = '" . e($uid) . "')";
                }
                if ($viewer_filter_editor) {
                    $clauses[] = "(forms.form_editor_user_id = '" . e($uid) . "')";
                }
                if ($viewer_filter_submitter) {
                    // Connect-to-contact email-address field also counts as submitter.
                    $submitter_field_id = db_value(
                        "SELECT id FROM form_fields
                         WHERE page_id = '" . e($custom_form_page_id) . "'
                           AND contact_field = 'email_address'"
                    );
                    if ($submitter_field_id) {
                        $clauses[] = "((SELECT form_data.data FROM form_data
                                         WHERE form_data.form_id = forms.id
                                           AND form_data.form_field_id = '" . (int)$submitter_field_id . "'
                                         LIMIT 1) = '" . e($umail) . "')";
                    }
                }
                if ($viewer_filter_watcher) {
                    // Watcher check needs the configured detail page (a form_item_view
                    // page with comments + watcher email enabled). Without one, the
                    // watcher contribution collapses to FALSE — same as legacy logic.
                    $detail_pid_for_watch = isset($cfg['detail_page_id']) ? (int)$cfg['detail_page_id'] : 0;
                    $watcher_page_id = $detail_pid_for_watch > 0 ? db_value(
                        "SELECT page_id FROM page
                         WHERE page_id = '" . (int)$detail_pid_for_watch . "'
                           AND page_type = 'form item view'
                           AND comments = '1'
                           AND comments_watcher_email_page_id != '0'"
                    ) : 0;
                    if ($watcher_page_id) {
                        $clauses[] = "(EXISTS (SELECT 1 FROM watchers
                                              WHERE watchers.page_id = '" . (int)$watcher_page_id . "'
                                                AND watchers.item_id = forms.id
                                                AND watchers.item_type = 'submitted_form'
                                                AND ( watchers.user_id = '" . e($uid) . "'
                                                   OR watchers.email_address = '" . e($umail) . "' )
                                              LIMIT 1))";
                    } else {
                        $clauses[] = '(TRUE = FALSE)';
                    }
                }
                if ($clauses) {
                    $sql_viewer_filter = ' AND (' . implode(' OR ', $clauses) . ') ';
                }
            }
        }
    }

    // ── Build the optional EXISTS clause for search filtering ─────────────
    $search_where = '';
    if ($search_active) {
        $sf_in   = implode(',', $search_field_ids);
        $q_esc   = e($search_query);
        $search_where =
            " AND EXISTS (SELECT 1 FROM form_data fd_q
                          WHERE fd_q.form_id = forms.id
                            AND fd_q.form_field_id IN ($sf_in)
                            AND fd_q.data LIKE '%$q_esc%')";
    }

    // ── Build search box HTML (rendered above the loop output) ────────────
    // Preserves all current URL params except the widget-specific page/query
    // so the user keeps their wider page state. Hidden inputs replay the rest.
    $search_html = '';
    if ($search_enabled) {
        $hidden = '';
        if (!empty($_GET) && is_array($_GET)) {
            foreach ($_GET as $k => $v) {
                if ($k === $query_param || $k === $page_param) continue;
                if (is_array($v)) continue;  // skip array params; rare and unsafe
                $hidden .= '<input type="hidden" name="' . h($k) . '" value="' . h((string)$v) . '">';
            }
        }
        $search_html =
            '<form method="get" class="pg-sw-search mb-3" role="search">' .
                $hidden .
                '<div class="input-group input-group-sm ' . $search_width_cls . '">' .
                    '<input type="text" class="form-control" name="' . h($query_param) . '" ' .
                           'value="' . h($search_query) . '" placeholder="' . h($search_label) . '" maxlength="100">' .
                    '<button type="submit" class="btn btn-outline-secondary"><span class="bi bi-search"></span></button>' .
                    ($search_query !== ''
                        ? '<a class="btn btn-outline-secondary" href="?' . h(_pg_sw_build_query($query_param, '', $page_param, '')) . '"><span class="bi bi-x-lg"></span></a>'
                        : '') .
                '</div>' .
            '</form>';
    }

    // Empty-state HTML used when no records match (with or without search active)
    $empty_html = '<div class="pg-sw-empty text-muted py-3">' . h($empty_message) . '</div>';

    // If the loop template is empty, we still want to honor empty/search/pagination
    // on the static surround. But absent a per-record visual there's nothing to repeat,
    // so just return the static HTML with search/empty injected at the slot.
    if ($loop_template === '') {
        if ($static_html === '') return '';
        return str_replace('<!--pg-loop-slot-->', $search_html . $empty_html, $static_html);
    }

    // ── Count total matching rows (for pagination) ────────────────────────
    $total_records = (int)db_value(
        "SELECT COUNT(*) FROM forms
         WHERE forms.page_id = '$custom_form_page_id'
           AND forms.complete = 1
           $search_where
           $sql_viewer_filter"
    );

    if ($total_records === 0) {
        $body = $search_html . $empty_html;
        if ($static_html === '') return $body;
        return str_replace('<!--pg-loop-slot-->', $body, $static_html);
    }

    // Apply the outer dataset cap BEFORE pagination math so the user only ever
    // pages through the first $max_results rows. Final page's LIMIT may be
    // shorter than $items_per_page (e.g. max=23, ipp=10 → page 3 returns 3).
    if ($max_results > 0 && $total_records > $max_results) {
        $total_records = $max_results;
    }

    $total_pages = (int)ceil($total_records / $items_per_page);
    if ($current_page > $total_pages) $current_page = $total_pages;
    $offset = ($current_page - 1) * $items_per_page;
    $page_limit = $items_per_page;
    if ($max_results > 0) {
        $remaining = $max_results - $offset;
        if ($remaining < $page_limit) $page_limit = max(0, $remaining);
    }

    // 1. Pull submitted form rows (the `forms` table) for this custom form,
    //    applying ordering + search + pagination.
    //    Only `complete=1` rows are shown — incomplete sessions are drafts.
    $forms = db_items(
        "SELECT forms.id, forms.page_id, forms.user_id, forms.reference_code, forms.submitted_timestamp
         FROM forms
         $order_join
         WHERE forms.page_id = '$custom_form_page_id'
           AND forms.complete = 1
           $search_where
           $sql_viewer_filter
         ORDER BY $order_by_sql
         LIMIT $page_limit OFFSET $offset"
    );
    if (!$forms) {
        $body = $search_html . $empty_html;
        if ($static_html === '') return $body;
        return str_replace('<!--pg-loop-slot-->', $body, $static_html);
    }

    // 2. Bulk-fetch form_data rows for all those submissions in one query.
    //    Pull BOTH form_fields.name AND form_fields.label (plus form_data.name denormalized
    //    copy) so the token can match whichever identifier the designer used — written
    //    tokens are free text ("^^description^^" etc.) and may correspond to any of them.
    $form_ids = array();
    foreach ($forms as $f) { $form_ids[] = (int)$f['id']; }
    $form_ids_str = implode(',', $form_ids);

    // Resolve the detail page (if configured) into a URL prefix once. Each row
    // appends its own reference_code, matching the legacy form_list_view pattern:
    //   PATH . encode_url_path(page_name) . '?r=' . forms.reference_code
    //
    // This URL is INTENTIONALLY independent of `_pg_sw_build_query`. The detail
    // link points at a *different* page (the form_item_view), so it must NOT
    // carry the current page's pagination/search params (page_number, query) —
    // if it did, the browser would land on the detail page with stale list-view
    // state in the query string (and on installs without URL rewriting, the
    // `page=<slug>` slug param could even point at the wrong page entirely).
    //
    // The page_type guard in the JS dropdown already restricts selections to
    // `form item view` pages; the SQL here looks up the page_name without
    // re-asserting the type so a page that was retyped after selection still
    // resolves rather than silently producing an empty href.
    $detail_page_id    = isset($cfg['detail_page_id']) ? (int)$cfg['detail_page_id'] : 0;
    $detail_url_prefix = '';
    if ($detail_page_id > 0) {
        // JOIN form_item_view_pages so we resolve the page_name AND verify the
        // detail page is bound to the SAME custom form the widget is listing.
        // Without this guard, picking a detail page tied to a different custom
        // form would generate `?r=ABC` links that the detail screen rejects:
        // get_form_item_view.php's WHERE clause asserts
        //   forms.page_id = $custom_form_page_id AND forms.reference_code = ?
        // — the page_id mismatch silently 404s every link. We fall back to ''
        // (which becomes '#' per record) when the config is stale.
        $detail_page_name = db_value(
            "SELECT page.page_name
             FROM page
             INNER JOIN form_item_view_pages
                     ON form_item_view_pages.page_id = page.page_id
                    AND form_item_view_pages.collection = 'a'
                    AND form_item_view_pages.custom_form_page_id = '" . (int)$custom_form_page_id . "'
             WHERE page.page_id = '" . (int)$detail_page_id . "'
             LIMIT 1"
        );
        if ($detail_page_name) {
            $base_path = defined('PATH') ? PATH : '/';
            $detail_url_prefix = $base_path . encode_url_path($detail_page_name) . '?r=';
        }
    }

    // JOIN files so file-upload fields resolve to a usable URL instead of an empty string.
    // form_data.data is empty for file-upload rows; the actual filename lives on
    // form_data.file_id → files.id → files.name. Pinegrap serves uploaded files at
    // OUTPUT_PATH . file_name (see edit_file.php: $data_src = OUTPUT_PATH . $file_name).
    $data_rows = db_items(
        "SELECT
            form_data.form_id,
            form_data.data,
            form_data.name      AS data_name,
            form_data.file_id   AS data_file_id,
            form_fields.name    AS field_name,
            form_fields.label   AS field_label,
            form_fields.type    AS field_type,
            form_fields.wysiwyg AS field_wysiwyg,
            files.name          AS file_name
         FROM form_data
         LEFT JOIN form_fields ON form_data.form_field_id = form_fields.id
         LEFT JOIN files       ON form_data.file_id       = files.id
         WHERE form_data.form_id IN ($form_ids_str)"
    );

    // Build an index that maps MANY possible token strings to the same value.
    // For each form_data row we generate candidate keys from:
    //   - form_fields.name        (canonical programmatic name)
    //   - form_data.name          (denormalized copy, identical to form_fields.name)
    //   - form_fields.label       (what the user usually sees in the admin form editor)
    //   - lowercase(label)
    //   - slugified(label)        ("Short Description" → "short_description")
    // Longer index but O(1) lookup at replace time; memory is negligible for <= 20 rows.
    $by_form = array();
    $_slugify = function ($s) {
        $s = trim((string)$s);
        if ($s === '') return '';
        $s = mb_strtolower($s, 'UTF-8');
        // Map Turkish + common accents to ASCII so "Açıklama" → "aciklama"
        $s = strtr($s, array(
            'ı' => 'i', 'İ' => 'i', 'ğ' => 'g', 'Ğ' => 'g',
            'ü' => 'u', 'Ü' => 'u', 'ş' => 's', 'Ş' => 's',
            'ö' => 'o', 'Ö' => 'o', 'ç' => 'c', 'Ç' => 'c',
        ));
        $s = preg_replace('/[^a-z0-9_]+/', '_', $s);
        return trim($s, '_');
    };

    if (is_array($data_rows)) {
        foreach ($data_rows as $r) {
            $fid = (int)$r['form_id'];
            if (!isset($by_form[$fid])) $by_form[$fid] = array();

            // Resolve the value for this field. File-upload fields don't have plain text
            // in form_data.data — instead the upload lives in `files` (joined above).
            // Build the public URL from OUTPUT_PATH (Pinegrap's file-serve convention).
            $value = isset($r['data']) ? (string)$r['data'] : '';
            $field_type = isset($r['field_type']) ? (string)$r['field_type'] : '';
            if ($field_type === 'file upload' && !empty($r['file_name'])) {
                $output_base = defined('OUTPUT_PATH') ? OUTPUT_PATH : '/';
                $value = $output_base . $r['file_name'];
            }
            // The value is spliced into the rendered markup as-is, and it was typed
            // by the submitter. Only a WYSIWYG text area may carry markup, and that
            // goes through the allow-list filter; everything else is escaped.
            if ($field_type === 'text area' && !empty($r['field_wysiwyg'])) {
                $value = pg_sanitize_rich_text($value);
            } else {
                $value = h($value);
            }

            // Collect every identifier string we might want to match against
            $keys = array();
            if (!empty($r['field_name']))  $keys[] = (string)$r['field_name'];
            if (!empty($r['data_name']))   $keys[] = (string)$r['data_name'];
            if (!empty($r['field_label'])) {
                $lbl = (string)$r['field_label'];
                $keys[] = $lbl;
                $keys[] = mb_strtolower($lbl, 'UTF-8');
                $slug = $_slugify($lbl);
                if ($slug !== '') $keys[] = $slug;
            }
            // Deduplicate + drop empties
            $keys = array_values(array_unique(array_filter($keys, function ($k) { return $k !== ''; })));
            if (!$keys) continue;

            // Multi-value fields: if the same key already exists (e.g. a select-multiple),
            // concatenate with comma separator — matches Pinegrap's display convention.
            // File uploads are atomic per row so they go through the same path safely.
            foreach ($keys as $k) {
                if (isset($by_form[$fid][$k]) && $by_form[$fid][$k] !== '') {
                    $by_form[$fid][$k] .= ', ' . $value;
                } else {
                    $by_form[$fid][$k] = $value;
                }
            }
        }
    }

    // 3. Render each submission by cloning the LOOP template and replacing tokens.
    //    Unknown tokens (no matching form field or built-in) are wiped so the raw
    //    "^^something^^" doesn't leak into the page — use a final regex sweep at the end.
    $loop_output = '';
    foreach ($forms as $f) {
        $fid    = (int)$f['id'];
        $values = isset($by_form[$fid]) ? $by_form[$fid] : array();

        // Built-in tokens (always available regardless of form definition)
        $values['__id']         = (string)$fid;
        $values['__reference']  = isset($f['reference_code'])      ? (string)$f['reference_code']      : '';
        $values['__timestamp']  = isset($f['submitted_timestamp']) ? date('Y-m-d H:i', (int)$f['submitted_timestamp']) : '';
        $values['__user']       = isset($f['user_id']) ? (string)$f['user_id'] : '';
        // Detail link → configured form_item_view page + reference_code.
        //
        // Fallback to `#` (not '') when no detail page is configured. An empty
        // href is resolved by browsers to the *current* URL — which on this
        // page already carries `page_number` / `query` from the list-view's
        // pagination/search state. That made the rendered detail link look
        // like `?page=deneme&page_number=1` instead of pointing nowhere, and
        // tripped users into thinking the URL builder was leaking those
        // params. `#` makes the inert state explicit.
        $values['__detail_url'] = ($detail_url_prefix !== '' && !empty($f['reference_code']))
            ? $detail_url_prefix . urlencode((string)$f['reference_code'])
            : '#';

        $rendered = $loop_template;
        // Replace longest tokens first so '^^foo_bar^^' isn't truncated by '^^foo^^'.
        $keys = array_keys($values);
        usort($keys, function ($a, $b) { return strlen($b) - strlen($a); });
        foreach ($keys as $k) {
            $rendered = str_replace('^^' . $k . '^^', (string)$values[$k], $rendered);
        }

        // Clean up any remaining unresolved placeholders so raw "^^foo^^" never
        // leaks to the browser. Sanitizer: token chars are [A-Za-z0-9_].
        $rendered = preg_replace('/\^\^[A-Za-z0-9_]+\^\^/', '', $rendered);

        // Uniquify Bootstrap component IDs per loop iteration so tabs, collapses
        // and dropdowns in different rows don't share the same id/target.
        // Using both widget_id and record's DB id as suffix prevents collisions
        // across multiple instances of the same widget on the same page.
        $iter_suffix = '_pgw' . $widget_id . 'r' . $fid;
        $rendered = preg_replace('/\bid="([^"]+)"/',             'id="$1'              . $iter_suffix . '"', $rendered);
        $rendered = preg_replace('/data-bs-target="#([^"]+)"/',  'data-bs-target="#$1' . $iter_suffix . '"', $rendered);
        $rendered = preg_replace('/aria-controls="([^"]+)"/',    'aria-controls="$1'   . $iter_suffix . '"', $rendered);
        $rendered = preg_replace('/href="#([^"]+)"/',            'href="#$1'           . $iter_suffix . '"', $rendered);
        $rendered = preg_replace('/data-bs-parent="#([^"]+)"/',  'data-bs-parent="#$1' . $iter_suffix . '"', $rendered);

        $loop_output .= $rendered;
    }

    // 4. Build pagination HTML (Bootstrap 5 nav). Only emitted when there are
    //    multiple pages — single-page lists stay clean. Window of ±2 around the
    //    current page plus first/last anchors so very long lists stay compact.
    $pagination_html = '';
    if ($total_pages > 1) {
        $pagination_html .= '<nav class="pg-sw-pagination" aria-label="Page navigation"><ul class="pagination pagination-sm justify-content-center mt-3 mb-0">';
        // Prev
        if ($current_page > 1) {
            $pagination_html .= '<li class="page-item"><a class="page-link" href="?' .
                h(_pg_sw_build_query($page_param, $current_page - 1)) .
                '" aria-label="Previous">&laquo;</a></li>';
        } else {
            $pagination_html .= '<li class="page-item disabled"><span class="page-link">&laquo;</span></li>';
        }
        // Numbered pages with windowing
        $window = 2;
        $from = max(1, $current_page - $window);
        $to   = min($total_pages, $current_page + $window);
        if ($from > 1) {
            $pagination_html .= '<li class="page-item"><a class="page-link" href="?' .
                h(_pg_sw_build_query($page_param, 1)) . '">1</a></li>';
            if ($from > 2) $pagination_html .= '<li class="page-item disabled"><span class="page-link">…</span></li>';
        }
        for ($i = $from; $i <= $to; $i++) {
            if ($i === $current_page) {
                $pagination_html .= '<li class="page-item active"><span class="page-link">' . $i . '</span></li>';
            } else {
                $pagination_html .= '<li class="page-item"><a class="page-link" href="?' .
                    h(_pg_sw_build_query($page_param, $i)) . '">' . $i . '</a></li>';
            }
        }
        if ($to < $total_pages) {
            if ($to < $total_pages - 1) $pagination_html .= '<li class="page-item disabled"><span class="page-link">…</span></li>';
            $pagination_html .= '<li class="page-item"><a class="page-link" href="?' .
                h(_pg_sw_build_query($page_param, $total_pages)) . '">' . $total_pages . '</a></li>';
        }
        // Next
        if ($current_page < $total_pages) {
            $pagination_html .= '<li class="page-item"><a class="page-link" href="?' .
                h(_pg_sw_build_query($page_param, $current_page + 1)) .
                '" aria-label="Next">&raquo;</a></li>';
        } else {
            $pagination_html .= '<li class="page-item disabled"><span class="page-link">&raquo;</span></li>';
        }
        $pagination_html .= '</ul></nav>';
    }

    // 5. Stitch search + loop output + pagination into the static surround.
    //    The static side (header, container, etc.) was rendered once with a
    //    <!--pg-loop-slot--> marker where the loop_area used to be — that marker
    //    is replaced with [search][loop][pagination].
    $body = $search_html . $loop_output . $pagination_html;
    if ($static_html === '') {
        // Legacy (no loop_area) — output is purely the per-record loop with controls
        return $body;
    }
    return str_replace('<!--pg-loop-slot-->', $body, $static_html);
}

// ============================================================================
// Render a form_item_view system widget — single submitted-form detail.
//
// Companion to _render_system_widget_form_list: the list view's
// ^^__detail_url^^ token links here (`?r=<reference_code>`). This renderer
// resolves that reference back to a `forms` row, joins all of its `form_data`,
// and applies tokens to the loop_area template ONCE (no iteration).
//
// Differs from form_list_view in three ways:
//   1. No pagination, no search, no order_by, no max_results — there's only
//      ever one record in scope.
//   2. ?r= is the trigger; missing ?r= or no matching row → not_found_message
//      (configurable). Empty `?r=` is intentional, not an error.
//   3. Access control is per-record audience (public / submitter_only /
//      logged_in), not per-list-row audience like form_list_view's viewer_filter.
//
// Tokens (loop_area template + static portion):
//   ^^__id^^                — forms.id (raw integer)
//   ^^__reference^^         — forms.reference_code
//   ^^__submitted_at^^      — formatted submitted_timestamp
//   ^^__submitted_by^^      — submitter username (or empty when anonymous)
//   ^^__user^^              — forms.user_id (raw integer)
//   ^^__detail_url^^        — current page URL with ?r= preserved (canonical self)
//   ^^__address_name^^      — forms.address_name (slug)
//   ^^__tracking_code^^     — forms.tracking_code
//   ^^__not_found^^         — empty when record is shown; configured message
//                             when ?r= missing or unauthorized (designer can
//                             wrap a hide/show region around it)
//   ^^{form_field.name}^^   — value from form_data
//   ^^{form_field.name}__label^^  — when show_field_labels is on, the field's
//                                   label as a separate token
//
// Config:
//   custom_form_page_id   — bound custom form (page.page_id of page_type=custom_form)
//   not_found_message     — string shown via ^^__not_found^^ token when miss
//   access_control        — 'public' | 'submitter_only' | 'logged_in'
//   show_field_labels     — bool: emit __label tokens in addition to value tokens
//
// URL params:
//   r — reference_code (matches Pinegrap's classic form item view convention)
function _render_system_widget_form_item_view($custom_form_page_id, $tree_json, $widget_id, $cfg = array())
{
    $custom_form_page_id = (int)$custom_form_page_id;
    $widget_id           = (int)$widget_id;
    if ($custom_form_page_id <= 0 || $tree_json === '' || $tree_json === null) return '';
    if (!is_array($cfg)) $cfg = array();

    // ── Config resolution ────────────────────────────────────────────────
    $not_found_message = (isset($cfg['not_found_message']) && is_string($cfg['not_found_message']) && $cfg['not_found_message'] !== '')
                            ? $cfg['not_found_message']
                            : lang('No records found.');
    $access_control = isset($cfg['access_control']) ? (string)$cfg['access_control'] : 'public';
    if (!in_array($access_control, array('public', 'submitter_only', 'logged_in'), true)) {
        $access_control = 'public';
    }
    $show_field_labels = !empty($cfg['show_field_labels']);

    // Decode + split the widget tree into static + loop-template halves.
    // The loop_area renders ONCE (no iteration). If the tree has no loop_area
    // we render the whole tree as the template, matching form_list_view's
    // legacy fallback so designers without a loop_area still get output.
    $tree_decoded = json_decode($tree_json, true);
    if (!is_array($tree_decoded)) return '';
    // Per-widget messages safety net — auto-prepends a Messages content
    // node if the designer hasn't placed one. formName left empty so
    // every pending session message is shown until per-widget liveform
    // names are wired up individually.
    _pg_inject_messages_node($tree_decoded, '');
    $split = _split_widget_tree($tree_decoded);

    if ($split['loop_children'] === null) {
        $loop_template = trim(_render_tree_node($tree_decoded, 0, 0));
        $static_html   = '';
    } else {
        $loop_template = '';
        foreach ($split['loop_children'] as $child) {
            $loop_template .= _render_tree_node($child, 0, 0);
        }
        $loop_template = trim($loop_template);
        $static_html   = trim(_render_tree_node($split['static_tree'], 0, 0));
    }

    // ── Resolve the requested record ─────────────────────────────────────
    // Empty ?r= is treated as "no record" (not an error) — designer might
    // place this widget on a page hit without a reference, and we want the
    // not_found template to render gracefully.
    $reference_code = isset($_GET['r']) ? trim((string)$_GET['r']) : '';
    $form_row = null;
    if ($reference_code !== '' && strlen($reference_code) <= 100) {
        $form_row = db_item(
            "SELECT id, page_id, user_id, form_editor_user_id, reference_code,
                    submitted_timestamp, address_name, tracking_code, complete
             FROM forms
             WHERE page_id   = '" . (int)$custom_form_page_id . "'
               AND reference_code = '" . e($reference_code) . "'
             LIMIT 1"
        );

        // Register the article/record for visitor tracking. Registered before
        // the access check below on purpose: a denied view is still a view of
        // this record, and treating it as an unnamed hit on the host page
        // would put it back in the bucket this change exists to empty.
        if (is_array($form_row) && !empty($form_row['id'])) {
            pg_track_content('form', $form_row['id']);
        }
    }

    // ── Access control ───────────────────────────────────────────────────
    // Translates the cfg.access_control enum into a single allowed/denied bit.
    // We treat denied as a "not found" state — same UX as missing record so
    // the page never leaks the existence of a record the visitor can't see.
    $allowed = true;
    if ($form_row) {
        if ($access_control === 'logged_in') {
            $allowed = (defined('USER_LOGGED_IN') && USER_LOGGED_IN === true);
        } elseif ($access_control === 'submitter_only') {
            $is_session_submitter = (
                isset($_SESSION['software']['submitted_form_reference_codes'])
                && is_array($_SESSION['software']['submitted_form_reference_codes'])
                && in_array($form_row['reference_code'], $_SESSION['software']['submitted_form_reference_codes'], true)
            );
            $is_user_submitter = (
                defined('USER_LOGGED_IN') && USER_LOGGED_IN === true &&
                defined('USER_ID') && (int)USER_ID === (int)$form_row['user_id'] && (int)$form_row['user_id'] > 0
            );
            $allowed = $is_session_submitter || $is_user_submitter;
        }
    }
    $found_and_allowed = ($form_row && $allowed);

    // Helper: build current page URL with `?r=<code>` for ^^__detail_url^^ token.
    // Mirrors form_list_view's detail-link convention so the same token name
    // works in both widget kinds (designer can paste the same link patterns).
    $self_url = '';
    if (defined('PATH')) {
        // Reconstruct slug from $_GET['page'] (router writes the resolved page slug there)
        $page_slug = isset($_GET['page']) ? (string)$_GET['page'] : '';
        if ($page_slug !== '') {
            // Strip trailing /<group>/... segments — keep only the page slug itself
            $first_slash = mb_strpos($page_slug, '/');
            if ($first_slash !== false) $page_slug = mb_substr($page_slug, 0, $first_slash);
            $self_url = PATH . encode_url_path($page_slug);
            if ($found_and_allowed) {
                $self_url .= '?r=' . urlencode((string)$form_row['reference_code']);
            }
        }
    }

    // ── Build the token map ──────────────────────────────────────────────
    if (!$found_and_allowed) {
        // Render the template with a "not found" token map. Form-field tokens
        // are intentionally absent so unresolved tokens get cleaned up by the
        // final regex sweep (designer's "not_found" region renders, others go
        // empty). The configured message is exposed as ^^__not_found^^ so the
        // designer can wrap it in a styled element.
        $values = array(
            '__id'             => '',
            '__reference'      => '',
            '__submitted_at'   => '',
            '__submitted_by'   => '',
            '__user'           => '',
            '__detail_url'     => '#',
            '__address_name'   => '',
            '__tracking_code'  => '',
            '__not_found'      => $not_found_message,
        );
    } else {
        // Pull all form_data rows for the matched submission. Same JOIN shape
        // as form_list_view's batch fetcher (single `form_id`) — file-upload
        // fields resolve to a public URL via OUTPUT_PATH + files.name.
        $data_rows = db_items(
            "SELECT
                form_data.data,
                form_data.name      AS data_name,
                form_data.file_id   AS data_file_id,
                form_fields.name    AS field_name,
                form_fields.label   AS field_label,
                form_fields.type    AS field_type,
                form_fields.wysiwyg AS field_wysiwyg,
                files.name          AS file_name
             FROM form_data
             LEFT JOIN form_fields ON form_data.form_field_id = form_fields.id
             LEFT JOIN files       ON form_data.file_id       = files.id
             WHERE form_data.form_id = '" . (int)$form_row['id'] . "'"
        );

        // Submitter username (best-effort; empty when anonymous submission)
        $submitter_username = '';
        if ((int)$form_row['user_id'] > 0) {
            $submitter_username = (string)db_value(
                "SELECT user_username FROM user WHERE user_id = '" . (int)$form_row['user_id'] . "' LIMIT 1"
            );
        }

        $values = array(
            '__id'             => (string)$form_row['id'],
            '__reference'      => (string)$form_row['reference_code'],
            '__submitted_at'   => !empty($form_row['submitted_timestamp']) ? date('Y-m-d H:i', (int)$form_row['submitted_timestamp']) : '',
            '__submitted_by'   => h($submitter_username),
            '__user'           => (string)$form_row['user_id'],
            '__detail_url'     => $self_url !== '' ? $self_url : '#',
            '__address_name'   => isset($form_row['address_name']) ? h((string)$form_row['address_name']) : '',
            '__tracking_code'  => isset($form_row['tracking_code']) ? h((string)$form_row['tracking_code']) : '',
            '__not_found'      => '',  // empty when found — designer's not_found region renders empty
        );

        // Slug helper for label-derived alternate keys (lowercase + Turkish-folded).
        $_slugify = function ($s) {
            $s = trim((string)$s);
            if ($s === '') return '';
            $s = mb_strtolower($s, 'UTF-8');
            $s = strtr($s, array(
                'ı' => 'i', 'İ' => 'i', 'ğ' => 'g', 'Ğ' => 'g',
                'ü' => 'u', 'Ü' => 'u', 'ş' => 's', 'Ş' => 's',
                'ö' => 'o', 'Ö' => 'o', 'ç' => 'c', 'Ç' => 'c',
            ));
            $s = preg_replace('/[^a-z0-9_]+/', '_', $s);
            return trim($s, '_');
        };

        // Form-field tokens. Same multi-key strategy as form_list_view: emit
        // value under field_name AND label / lowercased label / slugified label
        // so the designer can use whichever identifier they prefer in bindings.
        if (is_array($data_rows)) {
            foreach ($data_rows as $r) {
                $value = isset($r['data']) ? (string)$r['data'] : '';
                $field_type = isset($r['field_type']) ? (string)$r['field_type'] : '';
                if ($field_type === 'file upload' && !empty($r['file_name'])) {
                    $output_base = defined('OUTPUT_PATH') ? OUTPUT_PATH : '/';
                    $value = $output_base . $r['file_name'];
                }
                // Same rule as form_list_view: submitter-typed values are escaped,
                // WYSIWYG markup is filtered.
                if ($field_type === 'text area' && !empty($r['field_wysiwyg'])) {
                    $value = pg_sanitize_rich_text($value);
                } else {
                    $value = h($value);
                }
                $keys = array();
                if (!empty($r['field_name']))  $keys[] = (string)$r['field_name'];
                if (!empty($r['data_name']))   $keys[] = (string)$r['data_name'];
                if (!empty($r['field_label'])) {
                    $lbl = (string)$r['field_label'];
                    $keys[] = $lbl;
                    $keys[] = mb_strtolower($lbl, 'UTF-8');
                    $slug = $_slugify($lbl);
                    if ($slug !== '') $keys[] = $slug;
                }
                $keys = array_values(array_unique(array_filter($keys, function ($k) { return $k !== ''; })));
                foreach ($keys as $k) {
                    if (isset($values[$k]) && $values[$k] !== '') {
                        // Multi-value (e.g. select-multiple) — concatenate.
                        $values[$k] .= ', ' . $value;
                    } else {
                        $values[$k] = $value;
                    }
                }
                // Optional label tokens: __label suffix when toggled on.
                if ($show_field_labels && !empty($r['field_name'])) {
                    $values[(string)$r['field_name'] . '__label'] = isset($r['field_label']) ? h((string)$r['field_label']) : '';
                }
            }
        }
    }

    // ── Apply tokens to template + static_html (single render, NO loop) ──
    $rendered = $loop_template;
    $static   = $static_html;
    $keys = array_keys($values);
    usort($keys, function ($a, $b) { return strlen($b) - strlen($a); });  // longest first
    foreach ($keys as $k) {
        $tok = '^^' . $k . '^^';
        $rendered = str_replace($tok, (string)$values[$k], $rendered);
        if ($static !== '') $static = str_replace($tok, (string)$values[$k], $static);
    }
    // Strip any remaining unresolved tokens (matches form_list_view convention).
    $rendered = preg_replace('/\^\^[A-Za-z0-9_]+\^\^/', '', $rendered);
    if ($static !== '') $static = preg_replace('/\^\^[A-Za-z0-9_]+\^\^/', '', $static);

    if ($static === '') return $rendered;
    return str_replace('<!--pg-loop-slot-->', $rendered, $static);
}

// Render a system widget's my_account view.  Loop-area aware:
//
//   1. _split_widget_tree pulls the loop_area out of the tree.
//   2. The static portion is rendered ONCE — profile info, header/footer.
//   3. If the widget has a loop_area AND show_submissions is enabled, each
//      of the user's form submissions gets a clone of the loop template with
//      per-submission tokens replaced.
//   4. If there is no loop_area, the entire tree acts as the profile display
//      (backwards-compat; no submission list rendered).
//
// If the visitor is NOT logged in:
//   - If redirect_to_login is true and login_page_id is configured → redirect.
//   - Otherwise render with ^^__not_logged_in^^ = not_logged_in_message; all
//     other tokens collapse to empty, the loop outputs nothing.
//
// Static tokens (always available when logged in):
//   ^^__account_id^^      — user.user_id
//   ^^__username^^        — user.user_username
//   ^^__email^^           — user.user_email
//   ^^__full_name^^       — contacts.first_name + last_name
//   ^^__first_name^^      — contacts.first_name
//   ^^__last_name^^       — contacts.last_name
//   ^^__registered_date^^ — user.user_timestamp (Y-m-d)
//   ^^__avatar_url^^      — contacts.image URL (or empty)
//   ^^__member_id^^       — contacts.member_id
//   ^^__member^^          — "1" if active member, "0" otherwise
//   ^^__not_logged_in^^   — empty when logged in, cfg message when not
//
// Per-submission loop tokens (loop_area + show_submissions):
//   ^^__form_name^^       — page.page_title of the form
//   ^^__reference^^       — forms.reference_code
//   ^^__submitted_at^^    — forms.submitted_timestamp (Y-m-d H:i)
//   ^^__detail_url^^      — submissions_detail_page?r=<code> (or '#')
//   ^^__form_id^^         — forms.id
//
// Config (`system_region_config`) keys used here:
//   not_logged_in_message      string  shown via ^^__not_logged_in^^ when not logged in
//   redirect_to_login          bool    redirect to login_page_id when not logged in
//   login_page_id              int     page.page_id of the login page
//   show_submissions           bool    enable loop_area submission list
//   filter_form_id             int     0 = all forms, >0 = one specific form
//   submissions_detail_page_id int     page_id of the form_item_view detail page
function _render_system_widget_my_account($tree_json, $widget_id, $cfg = array())
{
    $widget_id = (int)$widget_id;
    if ($tree_json === '' || $tree_json === null) return '';
    if (!is_array($cfg)) $cfg = array();

    // ── Config resolution ─────────────────────────────────────────────────
    $not_logged_in_message = (isset($cfg['not_logged_in_message']) && is_string($cfg['not_logged_in_message']) && $cfg['not_logged_in_message'] !== '')
                                ? $cfg['not_logged_in_message']
                                : lang('You must be logged in to view this page.');
    $redirect_to_login  = !empty($cfg['redirect_to_login']);
    $login_page_id      = isset($cfg['login_page_id']) ? (int)$cfg['login_page_id'] : 0;
    $show_submissions   = !empty($cfg['show_submissions']);
    $filter_form_id     = isset($cfg['filter_form_id']) ? (int)$cfg['filter_form_id'] : 0;
    $detail_page_id     = isset($cfg['submissions_detail_page_id']) ? (int)$cfg['submissions_detail_page_id'] : 0;

    // ── Decode + split the widget tree ────────────────────────────────────
    $tree_decoded = json_decode($tree_json, true);
    if (!is_array($tree_decoded)) return '';
    // Per-widget messages safety net — auto-prepends a Messages content
    // node if the designer hasn't placed one. formName left empty so
    // every pending session message is shown until per-widget liveform
    // names are wired up individually.
    _pg_inject_messages_node($tree_decoded, '');
    $split = _split_widget_tree($tree_decoded);

    if ($split['loop_children'] === null) {
        $loop_template = trim(_render_tree_node($tree_decoded, 0, 0));
        $static_html   = '';
    } else {
        $loop_template = '';
        foreach ($split['loop_children'] as $child) {
            $loop_template .= _render_tree_node($child, 0, 0);
        }
        $loop_template = trim($loop_template);
        $static_html   = trim(_render_tree_node($split['static_tree'], 0, 0));
    }

    // ── Auth check ────────────────────────────────────────────────────────
    $logged_in = (defined('USER_LOGGED_IN') && USER_LOGGED_IN === true);

    if (!$logged_in) {
        // Redirect mode: send to login page when configured and accessible.
        if ($redirect_to_login && $login_page_id > 0) {
            $login_page_name = (string)db_value(
                "SELECT page_name FROM page WHERE page_id = '" . (int)$login_page_id . "' LIMIT 1"
            );
            if ($login_page_name !== '') {
                $login_url = (defined('OUTPUT_PATH') ? OUTPUT_PATH : '/') . encode_url_path($login_page_name);
                go($login_url);
                return '';
            }
        }

        // Render with "not logged in" token map; loop_area outputs nothing.
        $values = array(
            '__account_id'      => '',
            '__username'        => '',
            '__email'           => '',
            '__full_name'       => '',
            '__first_name'      => '',
            '__last_name'       => '',
            '__registered_date' => '',
            '__avatar_url'      => '',
            '__member_id'       => '',
            '__member'          => '0',
            '__not_logged_in'   => $not_logged_in_message,
        );
        $rendered = $loop_template;
        $static   = $static_html;
        $keys = array_keys($values);
        usort($keys, function ($a, $b) { return strlen($b) - strlen($a); });
        foreach ($keys as $k) {
            $tok = '^^' . $k . '^^';
            $rendered = str_replace($tok, (string)$values[$k], $rendered);
            if ($static !== '') $static = str_replace($tok, (string)$values[$k], $static);
        }
        $rendered = preg_replace('/\^\^[A-Za-z0-9_]+\^\^/', '', $rendered);
        if ($static !== '') $static = preg_replace('/\^\^[A-Za-z0-9_]+\^\^/', '', $static);
        if ($static === '') return $rendered;
        return str_replace('<!--pg-loop-slot-->', $rendered, $static);
    }

    // ── Fetch logged-in user data (user + contact JOIN) ───────────────────
    $uid = defined('USER_ID') ? (int)USER_ID : 0;
    $user_row = null;
    if ($uid > 0) {
        $user_row = db_item(
            "SELECT
                user.user_id,
                user.user_username,
                user.user_email,
                user.user_timestamp,
                contacts.first_name,
                contacts.last_name,
                contacts.image    AS avatar_image,
                contacts.member_id
             FROM user
             LEFT JOIN contacts ON contacts.id = user.user_contact
             WHERE user.user_id = '" . e($uid) . "'
             LIMIT 1"
        );
    }

    $output_base = defined('OUTPUT_PATH') ? OUTPUT_PATH : '/';

    // Build full_name from contact record; fall back gracefully.
    $fn = ($user_row && isset($user_row['first_name'])) ? trim((string)$user_row['first_name']) : '';
    $ln = ($user_row && isset($user_row['last_name']))  ? trim((string)$user_row['last_name'])  : '';
    $full_name = trim($fn . ' ' . $ln);

    // Avatar: contacts.image stores the filename relative to the site root.
    $avatar_url = '';
    if ($user_row && !empty($user_row['avatar_image'])) {
        $avatar_url = $output_base . $user_row['avatar_image'];
    }

    $registered_date = '';
    if ($user_row && !empty($user_row['user_timestamp'])) {
        $registered_date = date('Y-m-d', (int)$user_row['user_timestamp']);
    }

    // Base token map shared by both the static portion and every loop row.
    $values = array(
        '__account_id'      => $user_row ? (string)$user_row['user_id']      : (string)$uid,
        '__username'        => $user_row ? (string)$user_row['user_username'] : (string)(defined('USER_USERNAME')      ? USER_USERNAME      : ''),
        '__email'           => $user_row ? (string)$user_row['user_email']    : (string)(defined('USER_EMAIL_ADDRESS') ? USER_EMAIL_ADDRESS : ''),
        '__full_name'       => $full_name,
        '__first_name'      => $fn,
        '__last_name'       => $ln,
        '__registered_date' => $registered_date,
        '__avatar_url'      => $avatar_url,
        '__member_id'       => ($user_row && !empty($user_row['member_id'])) ? (string)$user_row['member_id'] : '',
        '__member'          => (defined('USER_MEMBER') && USER_MEMBER === true) ? '1' : '0',
        '__not_logged_in'   => '',
    );

    // ── No loop_area — render entire tree once with account tokens ─────────
    if ($split['loop_children'] === null) {
        $rendered = $loop_template;
        $keys = array_keys($values);
        usort($keys, function ($a, $b) { return strlen($b) - strlen($a); });
        foreach ($keys as $k) {
            $rendered = str_replace('^^' . $k . '^^', (string)$values[$k], $rendered);
        }
        return preg_replace('/\^\^[A-Za-z0-9_]+\^\^/', '', $rendered);
    }

    // ── Loop_area present — build submission rows ─────────────────────────
    $loop_rendered = '';
    if ($show_submissions && $loop_template !== '') {
        // Resolve the detail page URL base.
        $detail_page_name = '';
        if ($detail_page_id > 0) {
            $detail_page_name = (string)db_value(
                "SELECT page_name FROM page WHERE page_id = '" . e($detail_page_id) . "' LIMIT 1"
            );
        }

        // Optional single-form filter — validate that page_id exists and is a custom form.
        $form_where = '';
        if ($filter_form_id > 0) {
            $valid_pid = (int)db_value(
                "SELECT page_id FROM page
                 WHERE page_id = '" . e($filter_form_id) . "'
                   AND " . pg_form_page_sql('page') . "
                 LIMIT 1"
            );
            if ($valid_pid > 0) {
                $form_where = " AND forms.page_id = '" . e($valid_pid) . "'";
            }
        }

        // Fetch at most 200 completed submissions owned by this user.
        $submission_rows = db_items(
            "SELECT
                forms.id,
                forms.reference_code,
                forms.submitted_timestamp,
                page.page_title AS form_name
             FROM forms
             LEFT JOIN page ON forms.page_id = page.page_id
             WHERE forms.user_id = '" . e($uid) . "'
               AND forms.complete = 1
               $form_where
             ORDER BY forms.submitted_timestamp DESC
             LIMIT 200"
        );

        if (is_array($submission_rows)) {
            foreach ($submission_rows as $sr) {
                // Build detail link — mirrors form_list_view's ^^__detail_url^^ convention.
                $detail_url = '#';
                if ($detail_page_name !== '') {
                    $detail_url = $output_base . encode_url_path($detail_page_name)
                                  . '?r=' . urlencode((string)$sr['reference_code']);
                }

                // Per-row tokens extend the base account map.
                $row_values = array_merge($values, array(
                    '__form_name'    => isset($sr['form_name']) ? (string)$sr['form_name'] : '',
                    '__reference'    => (string)$sr['reference_code'],
                    '__submitted_at' => !empty($sr['submitted_timestamp']) ? date('Y-m-d H:i', (int)$sr['submitted_timestamp']) : '',
                    '__detail_url'   => $detail_url,
                    '__form_id'      => (string)$sr['id'],
                ));

                $row_html = $loop_template;
                $rkeys = array_keys($row_values);
                usort($rkeys, function ($a, $b) { return strlen($b) - strlen($a); });
                foreach ($rkeys as $k) {
                    $row_html = str_replace('^^' . $k . '^^', (string)$row_values[$k], $row_html);
                }
                $row_html = preg_replace('/\^\^[A-Za-z0-9_]+\^\^/', '', $row_html);

                // Uniquify Bootstrap component IDs per loop iteration so tabs, collapses
                // and dropdowns in different rows don't share the same id/target.
                // Using both widget_id and record's DB id as suffix prevents collisions
                // across multiple instances of the same widget on the same page.
                $iter_suffix = '_pgw' . $widget_id . 'r' . (int)$sr['id'];
                $row_html = preg_replace('/\bid="([^"]+)"/',             'id="$1'              . $iter_suffix . '"', $row_html);
                $row_html = preg_replace('/data-bs-target="#([^"]+)"/',  'data-bs-target="#$1' . $iter_suffix . '"', $row_html);
                $row_html = preg_replace('/aria-controls="([^"]+)"/',    'aria-controls="$1'   . $iter_suffix . '"', $row_html);
                $row_html = preg_replace('/href="#([^"]+)"/',            'href="#$1'           . $iter_suffix . '"', $row_html);
                $row_html = preg_replace('/data-bs-parent="#([^"]+)"/',  'data-bs-parent="#$1' . $iter_suffix . '"', $row_html);

                $loop_rendered .= $row_html;
            }
        }
    }

    // ── Apply account tokens to static portion + assemble output ──────────
    $static = $static_html;
    $keys = array_keys($values);
    usort($keys, function ($a, $b) { return strlen($b) - strlen($a); });
    foreach ($keys as $k) {
        $tok = '^^' . $k . '^^';
        if ($static !== '') $static = str_replace($tok, (string)$values[$k], $static);
    }
    if ($static !== '') $static = preg_replace('/\^\^[A-Za-z0-9_]+\^\^/', '', $static);

    if ($static === '') return $loop_rendered;
    return str_replace('<!--pg-loop-slot-->', $loop_rendered, $static);
}

// ── Member widgets: login_form, forgot_password ─────────────────────────────
//
// The designer draws the controls; the server owns everything that has to be
// right for the legacy processors to accept the post. Same model as the
// custom form widget: a control IS a field, its `name` attribute is the
// contract, and the names are the ones index.php / forgot_password.php have
// always read (email, password, remember_me, screen). The rendered widget is
// wrapped in the <form> those scripts expect, with the hidden fields they
// need; the designer never draws a form tag.
//
// Errors and notices come back on the processors' liveforms ('login',
// 'forgot_password') and are printed by the widget's Messages node, scoped to
// that form. A feature that is switched off (remember me, forgot-password
// link, Google sign-in) leaves no element behind — the control, its label and
// an emptied wrapper are dropped rather than rendered inert.

// The `name` attribute of a semantic form control, or '' for anything else.
function _pg_member_control_name($node)
{
    if (!is_array($node) || !isset($node['type']) || $node['type'] !== 'semantic') return '';
    $tag = isset($node['props']['tag']) ? strtolower((string)$node['props']['tag']) : '';
    if (!in_array($tag, array('input', 'select', 'textarea', 'button'), true)) return '';
    $attrs = _pg_cf_node_attrs($node);
    return isset($attrs['name']) ? (string)$attrs['name'] : '';
}

// Set (or remove, with null) one attribute in a node's `_attrs` list.
function _pg_member_set_attr(&$node, $attr, $value)
{
    if (!isset($node['props']['_attrs']) || !is_array($node['props']['_attrs'])) $node['props']['_attrs'] = array();
    $node['props']['_attrs'] = array_values(array_filter($node['props']['_attrs'], function ($a) use ($attr) {
        return !(is_array($a) && isset($a['name']) && strtolower((string)$a['name']) === $attr);
    }));
    if ($value !== null) $node['props']['_attrs'][] = array('name' => $attr, 'value' => $value);
}

// Fill the control named $name: a text-like input gets `value`, a checkbox
// gets `checked` (or loses it). Password inputs are never refilled.
function _pg_member_fill_control(&$tree, $name, $value, $checked = null)
{
    if (!is_array($tree)) return;
    if (_pg_member_control_name($tree) === $name) {
        $attrs = _pg_cf_node_attrs($tree);
        $type  = strtolower((string)(isset($attrs['type']) ? $attrs['type'] : 'text'));
        $tag   = strtolower((string)$tree['props']['tag']);
        if ($type === 'checkbox' || $type === 'radio') {
            _pg_member_set_attr($tree, 'checked', $checked ? '' : null);
        } elseif ($tag === 'textarea') {
            $tree['props']['text'] = (string)$value;
        } elseif ($tag === 'input' && $type !== 'password' && $type !== 'file') {
            if ((string)$value !== '') _pg_member_set_attr($tree, 'value', (string)$value);
        }
        return;
    }
    if (!empty($tree['children']) && is_array($tree['children'])) {
        foreach ($tree['children'] as &$c) _pg_member_fill_control($c, $name, $value, $checked);
        unset($c);
    }
}

// Remove the controls $match says yes to, together with the <label for> that
// pointed at each and any wrapper left with nothing in it — an empty
// `.form-check` under a heading reads as a broken form, not a hidden option.
// $match receives (tag, attrs, node) and returns true to drop.
function _pg_member_drop_controls(&$tree, $match)
{
    if (!is_array($tree) || empty($tree['children']) || !is_array($tree['children'])) return;
    $had_children = count($tree['children']) > 0;
    $drop_ids = array();
    $kept = array();
    foreach ($tree['children'] as $child) {
        if (!is_array($child)) { $kept[] = $child; continue; }
        $is_control = false;
        if (isset($child['type']) && $child['type'] === 'semantic' && isset($child['props']['tag'])) {
            $tag = strtolower((string)$child['props']['tag']);
            if (in_array($tag, array('input', 'select', 'textarea', 'button'), true)) {
                $attrs = _pg_cf_node_attrs($child);
                if ($match($tag, $attrs, $child)) {
                    $is_control = true;
                    if (!empty($attrs['id'])) $drop_ids[(string)$attrs['id']] = true;
                }
            }
        } elseif (isset($child['type']) && $child['type'] === 'component'
                  && isset($child['props']['componentType']) && $child['props']['componentType'] === 'btn'
                  && $match('btn', array(), $child)) {
            $is_control = true;
        }
        if (!$is_control) $kept[] = $child;
    }
    if (!empty($drop_ids)) {
        $kept = array_values(array_filter($kept, function ($c) use ($drop_ids) {
            if (!is_array($c) || !isset($c['props']['tag']) || strtolower((string)$c['props']['tag']) !== 'label') return true;
            $la = _pg_cf_node_attrs($c);
            return !(isset($la['for']) && isset($drop_ids[(string)$la['for']]));
        }));
    }
    $tree['children'] = $kept;
    foreach ($tree['children'] as &$c) _pg_member_drop_controls($c, $match);
    unset($c);
    // A wrapper that only ever held what was dropped goes with it.
    $tree['children'] = array_values(array_filter($tree['children'], function ($c) {
        return !(is_array($c) && !empty($c['props']['_pg_member_empty']));
    }));
    if ($had_children && empty($tree['children']) && isset($tree['type']) && $tree['type'] === 'semantic') {
        $tree['props']['_pg_member_empty'] = true;
    }
}

// Drop the control named $name (see _pg_member_drop_controls).
function _pg_member_drop_control(&$tree, $name)
{
    _pg_member_drop_controls($tree, function ($tag, $attrs) use ($name) {
        return isset($attrs['name']) && (string)$attrs['name'] === $name;
    });
}

// Drop every control and button — the form has done its job (a confirmation
// screen) and only the words around it stay.
function _pg_member_drop_all_controls(&$tree)
{
    _pg_member_drop_controls($tree, function ($tag, $attrs, $node) { return true; });
}

// Drop links whose href is bound to a token that resolved to '': a feature
// that is off must not leave an <a href=""> behind. Covers semantic <a>,
// content links and button components. $tokens is token => resolved value.
function _pg_member_drop_empty_links(&$tree, $tokens)
{
    if (!is_array($tree) || empty($tree['children']) || !is_array($tree['children'])) return;
    $kept = array();
    foreach ($tree['children'] as $child) {
        if (is_array($child) && isset($child['props']['_bindings']['href'])) {
            $tok = (string)$child['props']['_bindings']['href'];
            if (array_key_exists($tok, $tokens) && (string)$tokens[$tok] === '') continue;
        }
        $kept[] = $child;
    }
    $tree['children'] = $kept;
    foreach ($tree['children'] as &$c) _pg_member_drop_empty_links($c, $tokens);
    unset($c);
}

// Site name for the ^^__site_name^^ token: the organization name from the
// config row (init.php defines it). There is no `settings` table in this
// product; a query against one is fatal, not empty.
function _pg_member_site_name()
{
    return defined('ORGANIZATION_NAME') ? (string)ORGANIZATION_NAME : '';
}

// Front-end address of a page id, '' when the page is gone.
function _pg_member_page_url($page_id)
{
    $page_id = (int)$page_id;
    if ($page_id <= 0) return '';
    $name = db_value("SELECT page_name FROM page WHERE page_id = '$page_id' LIMIT 1");
    if ($name === '' || $name === null || $name === false) return '';
    return (defined('OUTPUT_PATH') ? OUTPUT_PATH : '/') . encode_url_path((string)$name);
}

// A same-origin path from the query string, or '' — the caller decides the
// default. pg_safe_redirect_path() falls back to PATH, which is not an
// answer to "did the visitor give us one".
function _pg_member_query_path($key)
{
    if (!isset($_GET[$key]) || !is_scalar($_GET[$key])) return '';
    $v = (string)$_GET[$key];
    if ($v === '') return '';
    return (pg_safe_redirect_path($v, '/__none__') === '/__none__') ? '' : $v;
}

// Wrap the rendered widget in the <form> a legacy processor expects. The
// hidden fields ride along with the CSRF token; the controls are the
// designer's and nothing is added under them.
function _pg_member_form_wrap($rendered, $action, $hidden, $class)
{
    // Bootstrap's own validation, as on the custom form: `novalidate` stops
    // the browser's bubbles, `needs-validation` + the shared snippet show the
    // styled :invalid feedback on the first submit attempt. The server checks
    // the same `required` again.
    $open = '<form action="' . h($action) . '" method="post" novalidate class="needs-validation pg-cf-form ' . h($class) . '">' . get_token_field();
    foreach ($hidden as $k => $v) {
        $open .= '<input type="hidden" name="' . h($k) . '" value="' . h((string)$v) . '">';
    }
    $script = function_exists('pg_cf_validation_script') ? pg_cf_validation_script() : '';
    return $open . $rendered . $script . '</form>';
}

// Replace ^^tokens^^ (longest key first) and strip whatever is left.
function _pg_member_apply_tokens($rendered, $values)
{
    $keys = array_keys($values);
    usort($keys, function ($a, $b) { return strlen($b) - strlen($a); });
    foreach ($keys as $k) {
        $rendered = str_replace('^^' . $k . '^^', (string)$values[$k], $rendered);
    }
    return preg_replace('/\^\^[A-Za-z0-9_]+\^\^/', '', $rendered);
}

// A signed-in visitor on a sign-in / sign-up page. The widget keeps its
// shape - container, heading, the Messages block where the designer put it
// - and only the form goes: every control, button, link and server block is
// dropped and the word is printed as a notice on the form's own liveform,
// so it lands exactly where errors would have. $sections are the widget's
// section names, $tokens its link tokens; both are emptied so nothing is
// left pointing at a sign-in the visitor no longer needs.
function _pg_member_render_signed_in(&$tree, $form, $form_name, $send_to, $page_url, $sections, $tokens, $values)
{
    $software   = (defined('OUTPUT_PATH') ? OUTPUT_PATH : '/') . (defined('OUTPUT_SOFTWARE_DIRECTORY') ? OUTPUT_SOFTWARE_DIRECTORY : '');
    $logout_url = $software . '/logout.php?send_to=' . urlencode($page_url);

    $notice = h(lang('You are already signed in.'));
    if ($send_to !== '') {
        $notice .= ' <a href="' . h($send_to) . '" class="alert-link">' . h(lang('Continue')) . '</a>';
    }
    $notice .= ' <a href="' . h($logout_url) . '" class="alert-link" rel="nofollow">' . h(lang('Logout')) . '</a>';
    $form->add_notice($notice);

    _pg_member_drop_all_controls($tree);
    $empty_sections = array();
    foreach ($sections as $name) $empty_sections[$name] = '';
    pg_cf_apply_section_bindings($tree, $empty_sections);
    $empty_tokens = array();
    foreach ($tokens as $name) $empty_tokens[$name] = '';
    _pg_member_drop_empty_links($tree, $empty_tokens);
    pg_cf_link_labels($tree);
    _pg_inject_messages_node($tree, $form_name);

    $rendered = trim(_render_tree_node($tree, 0, 0));
    $form->remove();
    return _pg_member_apply_tokens($rendered, $values);
}

// Every semantic form control in the tree, in document order:
// array('name' => ..., 'node' => array, 'contact_field' => ...). Controls
// without a name are skipped; a submit button is not a field.
function _pg_member_controls($tree)
{
    $out = array();
    $walk = function ($n) use (&$walk, &$out) {
        if (!is_array($n)) return;
        $name = _pg_member_control_name($n);
        if ($name !== '' && strtolower((string)$n['props']['tag']) !== 'button') {
            $attrs = _pg_cf_node_attrs($n);
            $type  = strtolower((string)(isset($attrs['type']) ? $attrs['type'] : 'text'));
            if (!in_array($type, array('submit', 'reset', 'button', 'image'), true)) {
                $cf = (isset($n['props']['_cf']) && is_array($n['props']['_cf'])) ? $n['props']['_cf'] : array();
                $out[] = array(
                    'name'          => $name,
                    'type'          => $type,
                    'node'          => $n,
                    'contact_field' => isset($cf['contact_field']) ? (string)$cf['contact_field'] : '',
                );
            }
        }
        if (!empty($n['children']) && is_array($n['children'])) {
            foreach ($n['children'] as $c) $walk($c);
        }
    };
    $walk($tree);
    return $out;
}

// Put what the visitor typed back into every control the liveform knows,
// by name. Passwords are never refilled; a checkbox is checked when the
// stored value matches its own value (or is truthy when it has none).
function _pg_member_fill_from_form(&$tree, $form, $skip = array())
{
    foreach (_pg_member_controls($tree) as $c) {
        if (in_array($c['name'], $skip, true)) continue;
        if (!$form->field_in_session($c['name'])) continue;
        $value = $form->get_field_value($c['name']);
        if ($c['type'] === 'checkbox' || $c['type'] === 'radio') {
            $attrs = _pg_cf_node_attrs($c['node']);
            $own   = isset($attrs['value']) ? (string)$attrs['value'] : '';
            $checked = ($own !== '') ? ((string)$value === $own) : ((string)$value !== '' && (string)$value !== '0');
            _pg_member_fill_control($tree, $c['name'], $own, $checked);
        } else {
            _pg_member_fill_control($tree, $c['name'], (string)$value);
        }
    }
}

// Apply $fn (by reference) to every control named $name.
function _pg_member_edit_control(&$tree, $name, $fn)
{
    if (!is_array($tree)) return;
    if (_pg_member_control_name($tree) === $name) {
        $fn($tree);
        return;
    }
    if (!empty($tree['children']) && is_array($tree['children'])) {
        foreach ($tree['children'] as &$c) _pg_member_edit_control($c, $name, $fn);
        unset($c);
    }
}

// True when a live page (not in the recycle bin) carries a system widget of
// this type. The Google sign-up gate asks this: a designed registration page
// is an entrance page even though no page_type says so.
function pg_member_widget_published($type)
{
    static $cache = array();
    $type = (string)$type;
    if (isset($cache[$type])) return $cache[$type];
    $cache[$type] = false;

    // page_tree_json arrived with the multi-page editor; on a database the
    // upgrade has not reached, no designed page can carry a widget yet.
    if (function_exists('pg_multi_page_design_ready') && !pg_multi_page_design_ready()) return false;

    $rows = db_items(
        "SELECT id FROM shared_components
         WHERE system_region_config LIKE '%\"regionType\":\"" . e($type) . "\"%'");
    if (!is_array($rows) || count($rows) === 0) return false;

    $binned = function_exists('pg_designer_not_binned_sql') ? pg_designer_not_binned_sql('page.page_folder') : '';
    foreach ($rows as $r) {
        $wid = (int)$r['id'];
        if ($wid <= 0) continue;
        $hit = db_value(
            "SELECT page.page_id FROM page
             WHERE (page.page_tree_json LIKE '%\"sharedId\":" . $wid . ",%'
                 OR page.page_tree_json LIKE '%\"sharedId\":" . $wid . "}%')" . $binned . "
             LIMIT 1");
        if ($hit) { $cache[$type] = true; break; }
    }
    return $cache[$type];
}

// The entrance scripts' extras for a post that came from a member widget
// (registration or membership): the address-book columns its contact-bound
// controls fill, read from the widget's own tree (the visitor names the
// widget, the tree names the columns), and the contact group the widget
// chose. $type is the widget type the caller serves; another type's id is
// ignored.
function pg_member_widget_register_opts($widget_id, $form, $type = 'registration')
{
    $opts = array('contact_fields' => array());
    $widget_id = (int)$widget_id;
    if ($widget_id <= 0) return $opts;

    $row = db_item("SELECT tree_json, system_region_config FROM shared_components WHERE id = '" . $widget_id . "' LIMIT 1");
    if (empty($row)) return $opts;
    $cfg = json_decode((string)$row['system_region_config'], true);
    if (!is_array($cfg) || !isset($cfg['regionType']) || $cfg['regionType'] !== (string)$type) return $opts;
    $tree = json_decode((string)$row['tree_json'], true);
    if (!is_array($tree)) return $opts;

    foreach (_pg_member_controls($tree) as $c) {
        if ($c['contact_field'] === '' || !$form->field_in_session($c['name'])) continue;
        $opts['contact_fields'][$c['contact_field']] = $form->get_field_value($c['name']);
    }
    if (!empty($cfg['contact_group_id']) && (int)$cfg['contact_group_id'] > 0) {
        $opts['contact_group_id'] = (int)$cfg['contact_group_id'];
    }
    return $opts;
}

// Render a 'login_form' system widget.
//
// Posts to <software>/index.php, the same script the legacy login page and
// the login region post to. Controls by name: email, password, remember_me.
// Tokens: ^^__site_name^^, ^^__redirect_url^^, ^^__forgot_password_url^^,
// ^^__register_url^^, ^^__google_signin_url^^. Sections: google_signin,
// forgot_password_link, register_link. Errors: liveform 'login', printed by
// the Messages node.
//
// cfg: redirect_page_id (after sign-in), show_remember_me (default on),
// forgot_password_page_id, register_page_id (a page carrying the matching
// widget; the legacy page types are the fallback), show_register_link.
function _render_system_widget_login_form($tree_json, $widget_id, $cfg = array(), $mode = 'preview')
{
    $widget_id = (int)$widget_id;
    if ($tree_json === '' || $tree_json === null) return '';
    if (!is_array($cfg)) $cfg = array();

    $tree = json_decode($tree_json, true);
    if (!is_array($tree)) return '';

    $software  = (defined('OUTPUT_PATH') ? OUTPUT_PATH : '/') . (defined('OUTPUT_SOFTWARE_DIRECTORY') ? OUTPUT_SOFTWARE_DIRECTORY : '');
    $page_url  = function_exists('get_request_uri') ? (string)get_request_uri() : '';
    $site_name = _pg_member_site_name();

    // Where a successful sign-in lands: the configured page, else what the
    // link that brought the visitor here asked for, else nothing — and
    // send_user_to_login_home() decides (the user's home page, the account
    // page, the control panel).
    $send_to = '';
    if (!empty($cfg['redirect_page_id']) && (int)$cfg['redirect_page_id'] > 0) {
        $send_to = _pg_member_page_url($cfg['redirect_page_id']);
    }
    if ($send_to === '') $send_to = _pg_member_query_path('send_to');
    if ($send_to === '') $send_to = _pg_member_query_path('redirect');

    $lf = new liveform('login');

    // Already signed in: the form would only sign them in again. Editors
    // still see the form in edit mode, so the page can be worked on.
    if ($mode !== 'edit' && function_exists('pg_session_signed_in') && pg_session_signed_in()) {
        return _pg_member_render_signed_in($tree, $lf, 'login', $send_to, $page_url,
            array('google_signin', 'forgot_password_link', 'register_link'),
            array('__forgot_password_url', '__register_url', '__google_signin_url'),
            array('__site_name' => h($site_name), '__redirect_url' => h($send_to)));
    }

    // What the visitor typed, back in the boxes after a refused attempt.
    // index.php keeps the email and blanks the password; the messages node
    // prints the error and then consumes the form, so read first.
    $submitted = $lf->field_in_session('token');
    _pg_member_fill_control($tree, 'email', (string)$lf->get_field_value('email'));

    // Remember me: the site setting first, then the widget's own switch.
    // Checked by default when the visitor asked for it last time (legacy
    // behaviour, read from the cookie), or when they had it on in the attempt
    // that was just refused.
    $remember_ok = defined('REMEMBER_ME') && REMEMBER_ME
                && !(isset($cfg['show_remember_me']) && ($cfg['show_remember_me'] === false || $cfg['show_remember_me'] === 0 || $cfg['show_remember_me'] === '0'));
    if (!$remember_ok) {
        _pg_member_drop_control($tree, 'remember_me');
    } else {
        $checked = $submitted
            ? ((string)$lf->get_field_value('remember_me') === '1')
            : ((isset($_COOKIE['software']['remember_me']) ? (string)$_COOKIE['software']['remember_me'] : '') === 'true');
        _pg_member_fill_control($tree, 'remember_me', '1', $checked);
    }

    // Forgot password: the widget page when one is chosen, else the legacy
    // page type, else the script itself. Off with the site setting.
    $forgot_url = '';
    if (defined('FORGOT_PASSWORD_LINK') && FORGOT_PASSWORD_LINK) {
        if (!empty($cfg['forgot_password_page_id'])) $forgot_url = _pg_member_page_url($cfg['forgot_password_page_id']);
        if ($forgot_url === '') {
            $legacy = function_exists('get_page_type_url') ? get_page_type_url('forgot password') : false;
            $forgot_url = ($legacy !== false && $legacy !== '') ? (string)$legacy : $software . '/forgot_password.php';
        }
        $forgot_url .= (strpos($forgot_url, '?') === false ? '?' : '&') . 'send_to=' . urlencode($page_url);
    }

    // Registration: a page carrying the registration widget when chosen;
    // otherwise, when the switch is on, the legacy registration entrance.
    $register_url = '';
    if (!empty($cfg['register_page_id'])) $register_url = _pg_member_page_url($cfg['register_page_id']);
    if ($register_url === '' && !empty($cfg['show_register_link']) && function_exists('get_page_type_url')) {
        $legacy = get_page_type_url('registration entrance');
        if ($legacy !== false && $legacy !== '') $register_url = (string)$legacy;
    }

    // Google: sign-in only from here (the staff login does the same); a
    // Google identity nobody matches is refused, not registered.
    $google_url  = function_exists('pg_google_signin_url') ? pg_google_signin_url($send_to, 'none') : '';
    $google_html = function_exists('pg_google_signin_button') ? pg_google_signin_button($send_to, 'none') : '';

    $sections = array(
        'google_signin'        => $google_html,
        'forgot_password_link' => ($forgot_url !== '')
            ? '<a href="' . h($forgot_url) . '" class="pg-member-link pg-forgot-password-link" rel="nofollow">' . h(lang('Forgot password?')) . '</a>' : '',
        'register_link'        => ($register_url !== '')
            ? '<a href="' . h($register_url) . '" class="pg-member-link pg-register-link">' . h(lang('Sign Up')) . '</a>' : '',
    );
    pg_cf_apply_section_bindings($tree, $sections);
    _pg_member_drop_empty_links($tree, array(
        '__forgot_password_url' => $forgot_url,
        '__register_url'        => $register_url,
        '__google_signin_url'   => $google_url,
    ));
    pg_cf_link_labels($tree);
    _pg_inject_messages_node($tree, 'login');

    $rendered = trim(_render_tree_node($tree, 0, 0));
    // The screen has been drawn from it; the legacy screen drops the form at
    // this point too, so a stale attempt never shapes a later visit.
    $lf->remove();
    $rendered = _pg_member_form_wrap($rendered, $software . '/index.php', array(
        'send_to'         => $send_to,
        // Where index.php sends a refused attempt: back to this page, with
        // the error on the liveform the Messages node prints.
        'return_to'       => $page_url,
        'require_cookies' => 'true',
    ), 'pg-login-form');

    return _pg_member_apply_tokens($rendered, array(
        '__site_name'           => h($site_name),
        '__redirect_url'        => h($send_to),
        '__forgot_password_url' => h($forgot_url),
        '__register_url'        => h($register_url),
        '__google_signin_url'   => h($google_url),
    ));
}

// Render a 'forgot_password' system widget.
//
// Posts to <software>/forgot_password.php. Control by name: email. Tokens:
// ^^__site_name^^, ^^__password_hint^^, ^^__back_url^^. Section:
// password_hint. Screens follow the processor's `screen` field on the
// 'forgot_password' liveform: '' (the form), 'password_hint' (the hint is
// shown and the same form asks again), 'confirm' (the controls are gone, the
// notice stays — worded the same for a known and an unknown address).
//
// cfg: redirect_page_id (send_to: where the visitor may go afterwards),
// login_page_id (the "back to sign-in" link; the legacy login page type is
// the fallback).
function _render_system_widget_forgot_password($tree_json, $widget_id, $cfg = array(), $mode = 'preview')
{
    $widget_id = (int)$widget_id;
    if ($tree_json === '' || $tree_json === null) return '';
    if (!is_array($cfg)) $cfg = array();

    $tree = json_decode($tree_json, true);
    if (!is_array($tree)) return '';

    $software  = (defined('OUTPUT_PATH') ? OUTPUT_PATH : '/') . (defined('OUTPUT_SOFTWARE_DIRECTORY') ? OUTPUT_SOFTWARE_DIRECTORY : '');
    $page_url  = function_exists('get_request_uri') ? (string)get_request_uri() : '';
    $site_name = _pg_member_site_name();

    $lf     = new liveform('forgot_password');
    $screen = (string)$lf->get_field_value('screen');
    $email  = (string)$lf->get_field_value('email');

    $send_to = '';
    if (!empty($cfg['redirect_page_id'])) $send_to = _pg_member_page_url($cfg['redirect_page_id']);
    if ($send_to === '') $send_to = _pg_member_query_path('send_to');
    if ($send_to === '') $send_to = defined('PATH') ? PATH : '/';

    $back_url = '';
    if (!empty($cfg['login_page_id'])) $back_url = _pg_member_page_url($cfg['login_page_id']);
    if ($back_url === '' && function_exists('get_page_type_url')) {
        $legacy = get_page_type_url('login');
        if ($legacy !== false && $legacy !== '') $back_url = (string)$legacy;
    }
    if ($back_url === '') $back_url = $send_to;

    // The hint screen: PASSWORD_HINT on, the account has one, and the visitor
    // has not yet said it did not help (that second post keeps screen =
    // password_hint and the processor sends the mail).
    $hint = '';
    if ($screen === 'password_hint' && defined('PASSWORD_HINT') && PASSWORD_HINT && $email !== '') {
        $hint = (string)db_value("SELECT user_password_hint FROM user WHERE user_email = '" . e($email) . "' LIMIT 1");
    }

    if ($screen === 'confirm') {
        _pg_member_drop_all_controls($tree);
    } else {
        _pg_member_fill_control($tree, 'email', $email);
    }

    pg_cf_apply_section_bindings($tree, array(
        'password_hint' => ($hint !== '')
            ? '<div class="alert alert-info pg-password-hint" role="alert"><strong>' . h(lang('Password Hint')) . ':</strong> ' . h($hint) . '</div>' : '',
    ));
    _pg_member_drop_empty_links($tree, array('__back_url' => $back_url));
    pg_cf_link_labels($tree);
    _pg_inject_messages_node($tree, 'forgot_password');

    $rendered = trim(_render_tree_node($tree, 0, 0));
    $lf->remove();
    if ($screen !== 'confirm') {
        $rendered = _pg_member_form_wrap($rendered, $software . '/forgot_password.php', array(
            'send_to'   => $send_to,
            'return_to' => $page_url,
            'screen'    => $screen,
        ), 'pg-forgot-password-form');
    }

    return _pg_member_apply_tokens($rendered, array(
        '__site_name'     => h($site_name),
        '__password_hint' => h($hint),
        '__back_url'      => h($back_url),
    ));
}

// Render a 'search_results' system widget. Reads $_GET['q'] as the search term,
// searches page titles/content and (when ECOMMERCE is active) product names.
// Loop_area iterates once per result row.
// Static tokens: ^^__search_query^^, ^^__result_count^^, ^^__no_results^^
// Loop tokens:   ^^__result_title^^, ^^__result_url^^, ^^__result_excerpt^^, ^^__result_type^^
function _render_system_widget_search_results($tree_json, $widget_id, $cfg = array())
{
    $widget_id = (int)$widget_id;
    if ($tree_json === '' || $tree_json === null) return '';
    if (!is_array($cfg)) $cfg = array();

    $tree_decoded = json_decode($tree_json, true);
    if (!is_array($tree_decoded)) return '';

    $split = _split_widget_tree($tree_decoded);

    if ($split['loop_children'] === null) {
        $loop_template = trim(_render_tree_node($tree_decoded, 0, 0));
        $static_html   = '';
    } else {
        $loop_template = '';
        foreach ($split['loop_children'] as $child) {
            $loop_template .= _render_tree_node($child, 0, 0);
        }
        $loop_template = trim($loop_template);
        $static_html   = trim(_render_tree_node($split['static_tree'], 0, 0));
    }

    $output_base = defined('OUTPUT_PATH') ? OUTPUT_PATH : '/';

    // Raw search query — strip tags, trim whitespace
    $raw_q      = isset($_GET['q']) ? trim(strip_tags((string)$_GET['q'])) : '';
    $results    = array();
    $no_results = (!empty($cfg['no_results_message']) && is_string($cfg['no_results_message'])) ? (string)$cfg['no_results_message'] : lang('No results found.');

    // Search scope from cfg: 'all' | 'pages' | 'products'
    $search_scope = (isset($cfg['search_scope']) && in_array($cfg['search_scope'], array('all', 'pages', 'products')))
        ? $cfg['search_scope'] : 'all';
    // Per-page limit from cfg (defaults to 50)
    $results_per_page = (!empty($cfg['results_per_page']) && (int)$cfg['results_per_page'] > 0)
        ? (int)$cfg['results_per_page'] : 50;
    $sql_limit = e($results_per_page);

    if ($raw_q !== '') {
        $q_esc = e($raw_q);
        $like  = "%" . escape_like($raw_q) . "%";
        $q_like = e($like);

        // Search pages (title + name) — exclude hidden/system page types
        if ($search_scope === 'all' || $search_scope === 'pages') {
            $page_rows = db_items(
                "SELECT page_id, page_title, page_name, page_type
                 FROM page
                 WHERE (page_title LIKE '$q_like' OR page_name LIKE '$q_like')
                   AND page_type NOT IN ('login','logout','error','folder view')
                 ORDER BY page_title ASC
                 LIMIT $sql_limit"
            );
            if (is_array($page_rows)) {
                foreach ($page_rows as $pr) {
                    $excerpt = $pr['page_type'] !== '' ? ucfirst((string)$pr['page_type']) : lang('Page');
                    $results[] = array(
                        'title'   => (string)$pr['page_title'],
                        'url'     => $output_base . encode_url_path((string)$pr['page_name']),
                        'excerpt' => $excerpt,
                        'type'    => 'page',
                    );
                }
            }
        }

        // Search products when ECOMMERCE is active
        if (($search_scope === 'all' || $search_scope === 'products') && defined('ECOMMERCE') && ECOMMERCE === true) {
            $product_rows = db_items(
                "SELECT id, name, short_description, address_name
                 FROM products
                 WHERE (name LIKE '$q_like' OR short_description LIKE '$q_like')
                   AND enabled = 1
                 ORDER BY name ASC
                 LIMIT $sql_limit"
            );
            if (is_array($product_rows)) {
                foreach ($product_rows as $pr) {
                    $results[] = array(
                        'title'   => (string)$pr['name'],
                        'url'     => $output_base . encode_url_path((string)$pr['address_name']),
                        'excerpt' => (string)$pr['short_description'],
                        'type'    => 'product',
                    );
                }
            }
        }
    }

    $result_count = count($results);

    // Build loop output
    $loop_rendered = '';
    if ($loop_template !== '' && $result_count > 0) {
        foreach ($results as $idx => $res) {
            $row_values = array(
                '__result_title'   => h((string)$res['title']),
                '__result_url'     => h((string)$res['url']),
                '__result_excerpt' => h((string)$res['excerpt']),
                '__result_type'    => h((string)$res['type']),
            );
            $row_html = $loop_template;
            $rkeys = array_keys($row_values);
            usort($rkeys, function ($a, $b) { return strlen($b) - strlen($a); });
            foreach ($rkeys as $k) {
                $row_html = str_replace('^^' . $k . '^^', $row_values[$k], $row_html);
            }
            $row_html = preg_replace('/\^\^[A-Za-z0-9_]+\^\^/', '', $row_html);

            // Uniquify Bootstrap component IDs per result row
            $iter_suffix = '_pgw' . $widget_id . 'r' . $idx;
            $row_html = preg_replace('/\bid="([^"]+)"/',            'id="$1'              . $iter_suffix . '"', $row_html);
            $row_html = preg_replace('/data-bs-target="#([^"]+)"/', 'data-bs-target="#$1' . $iter_suffix . '"', $row_html);
            $row_html = preg_replace('/aria-controls="([^"]+)"/',   'aria-controls="$1'   . $iter_suffix . '"', $row_html);
            $row_html = preg_replace('/href="#([^"]+)"/',           'href="#$1'           . $iter_suffix . '"', $row_html);

            $loop_rendered .= $row_html;
        }
    }

    // Apply static tokens
    $static_values = array(
        '__search_query' => h($raw_q),
        '__result_count' => (string)$result_count,
        '__no_results'   => ($result_count === 0 && $raw_q !== '') ? h($no_results) : '',
    );

    if ($static_html === '') {
        // No loop_area — replace tokens in the single rendered template
        $rendered = $loop_template;
        $skeys = array_keys($static_values);
        usort($skeys, function ($a, $b) { return strlen($b) - strlen($a); });
        foreach ($skeys as $k) {
            $rendered = str_replace('^^' . $k . '^^', $static_values[$k], $rendered);
        }
        return preg_replace('/\^\^[A-Za-z0-9_]+\^\^/', '', $rendered);
    }

    $skeys = array_keys($static_values);
    usort($skeys, function ($a, $b) { return strlen($b) - strlen($a); });
    foreach ($skeys as $k) {
        $static_html = str_replace('^^' . $k . '^^', $static_values[$k], $static_html);
    }
    $static_html = preg_replace('/\^\^[A-Za-z0-9_]+\^\^/', '', $static_html);

    return str_replace('<!--pg-loop-slot-->', $loop_rendered, $static_html);
}

// Render a 'registration' system widget.
//
// Posts to <software>/registration_entrance.php with register=true - the
// script the legacy entrance page posts to; pg_member_register() does the
// rest for both. Controls by name: first_name, last_name, username (optional;
// derived from the address when absent), email, email_verify, password,
// password_verify, password_hint, opt_in, register_remember_me. Any other
// control whose `_cf.contact_field` names an address-book column is written
// to the new contact (see pg_member_widget_register_opts()).
//
// Tokens: ^^__site_name^^, ^^__login_url^^, ^^__google_signup_url^^,
// ^^__password_rules^^, ^^__opt_in_label^^. Sections: captcha,
// password_rules, google_signup, login_link. Errors and the "you are
// registered" notice: liveform 'register', printed by the Messages node.
//
// cfg: redirect_page_id (after sign-up), login_page_id (the "already a
// member" link; the legacy login page type is the fallback), contact_group_id
// (the site setting when empty), show_remember_me (default on).
function _render_system_widget_registration($tree_json, $widget_id, $cfg = array(), $mode = 'preview')
{
    $widget_id = (int)$widget_id;
    if ($tree_json === '' || $tree_json === null) return '';
    if (!is_array($cfg)) $cfg = array();

    $tree = json_decode($tree_json, true);
    if (!is_array($tree)) return '';

    $software  = (defined('OUTPUT_PATH') ? OUTPUT_PATH : '/') . (defined('OUTPUT_SOFTWARE_DIRECTORY') ? OUTPUT_SOFTWARE_DIRECTORY : '');
    $page_url  = function_exists('get_request_uri') ? (string)get_request_uri() : '';
    $site_name = _pg_member_site_name();

    $send_to = '';
    if (!empty($cfg['redirect_page_id']) && (int)$cfg['redirect_page_id'] > 0) {
        $send_to = _pg_member_page_url($cfg['redirect_page_id']);
    }
    if ($send_to === '') $send_to = _pg_member_query_path('send_to');

    $login_url = '';
    if (!empty($cfg['login_page_id'])) $login_url = _pg_member_page_url($cfg['login_page_id']);
    if ($login_url === '' && function_exists('get_page_type_url')) {
        $legacy = get_page_type_url('login');
        if ($legacy !== false && $legacy !== '') $login_url = (string)$legacy;
    }
    if ($login_url === '') $login_url = $software . '/';

    $lf = new liveform('register');

    // Signed in: the form would open a second account. The notice a
    // sign-up just left ("you are registered") prints with it; an editor
    // still sees the form.
    if ($mode !== 'edit' && function_exists('pg_session_signed_in') && pg_session_signed_in()) {
        return _pg_member_render_signed_in($tree, $lf, 'register', $send_to, $page_url,
            array('captcha', 'password_rules', 'google_signup', 'login_link'),
            array('__login_url', '__google_signup_url'),
            array('__site_name' => h($site_name), '__login_url' => h($login_url),
                  '__opt_in_label' => h(defined('OPT_IN_LABEL') ? (string)OPT_IN_LABEL : '')));
    }

    // First view: consent checked, remember-me as the visitor left it last
    // time. A refused attempt refills everything but the passwords.
    $submitted = $lf->field_in_session('token');
    if (!$submitted) {
        $remembered = ((isset($_COOKIE['software']['remember_me']) ? (string)$_COOKIE['software']['remember_me'] : '') === 'true');
        _pg_member_fill_control($tree, 'opt_in', '1', true);
        _pg_member_fill_control($tree, 'register_remember_me', '1', $remembered);
        _pg_member_fill_control($tree, 'remember_me', '1', $remembered);
    } else {
        _pg_member_fill_from_form($tree, $lf, array('password', 'password_verify', 'captcha_submitted_answer'));
    }

    // Features that are off leave nothing behind.
    if (!(defined('PASSWORD_HINT') && PASSWORD_HINT)) {
        _pg_member_drop_control($tree, 'password_hint');
    }
    $remember_ok = defined('REMEMBER_ME') && REMEMBER_ME
                && !(isset($cfg['show_remember_me']) && ($cfg['show_remember_me'] === false || $cfg['show_remember_me'] === 0 || $cfg['show_remember_me'] === '0'));
    if (!$remember_ok) {
        _pg_member_drop_control($tree, 'register_remember_me');
        _pg_member_drop_control($tree, 'remember_me');
    }

    $rules_html  = (defined('STRONG_PASSWORD') && STRONG_PASSWORD && function_exists('get_strong_password_requirements'))
        ? '<div class="pg-password-rules small text-muted">' . get_strong_password_requirements() . '</div>' : '';
    $captcha     = (defined('CAPTCHA') && CAPTCHA && function_exists('get_captcha_fields')) ? (string)get_captcha_fields($lf) : '';
    $google_url  = function_exists('pg_google_signin_url') ? pg_google_signin_url($send_to, 'create') : '';
    $google_html = function_exists('pg_google_signin_button') ? pg_google_signin_button($send_to, 'create') : '';
    $opt_label   = defined('OPT_IN_LABEL') ? (string)OPT_IN_LABEL : '';

    pg_cf_apply_section_bindings($tree, array(
        'captcha'        => $captcha,
        'password_rules' => $rules_html,
        'google_signup'  => $google_html,
        'login_link'     => '<a href="' . h($login_url) . '" class="pg-member-link pg-login-link">' . h(lang('Already a member? Sign in')) . '</a>',
    ));
    _pg_member_drop_empty_links($tree, array(
        '__login_url'         => $login_url,
        '__google_signup_url' => $google_url,
    ));
    pg_cf_link_labels($tree);
    _pg_inject_messages_node($tree, 'register');

    $rendered = trim(_render_tree_node($tree, 0, 0));
    $lf->remove();
    $rendered = _pg_member_form_wrap($rendered, $software . '/registration_entrance.php', array(
        'register'        => 'true',
        'send_to'         => $send_to,
        'return_to'       => $page_url,
        'require_cookies' => 'true',
        // Which widget drew the form: its tree says which controls fill
        // which address-book columns.
        'pg_widget_id'    => $widget_id,
    ), 'pg-register-form');

    return _pg_member_apply_tokens($rendered, array(
        '__site_name'         => h($site_name),
        '__login_url'         => h($login_url),
        '__google_signup_url' => h($google_url),
        '__password_rules'    => $rules_html,
        '__opt_in_label'      => h($opt_label),
    ));
}

// Render an 'order_view' system widget. Reads a single completed order via
// $_GET['order_id'] (or ?oid=) and iterates order_items in loop_area.
// Security: order must belong to the currently logged-in user (orders.user).
//
// ── ORDER STATIC TOKENS ────────────────────────────────────────────────────
//   ^^__order_id^^              numeric order ID (orders.id)
//   ^^__order_no^^              order number (orders.order_number)
//   ^^__order_date^^            formatted per cfg.date_format
//   ^^__order_status^^          raw status text
//   ^^__order_status_badge^^    <span> carrying the designer-chosen classes for
//                               this order's lifecycle stage. Classes come from
//                               cfg.status_class_{complete|pending|shipped|
//                               cancelled|refunded|default}; see
//                               _pg_order_status_stage() + _pg_order_status_badge_class()
//   ^^__order_total^^           formatted with currency symbol
//   ^^__order_subtotal^^        formatted
//   ^^__order_discount^^        formatted
//   ^^__order_tax^^             formatted
//   ^^__order_shipping^^        formatted (shipping cost)
//   ^^__order_surcharge^^       formatted (card surcharge)
//   ^^__order_item_count^^      # of distinct line items
//   ^^__order_unit_count^^      total units across all items
//   ^^__order_notes^^           visitor's notes (nl2br'd)
//
//   ^^__payment_method^^        raw key (Credit/Debit Card / PayPal Express / Offline)
//   ^^__payment_method_pretty^^ localised label
//   ^^__card_number_masked^^    masked PAN ("4111********1111"); '' when not resolvable
//                               visibility flag: has_card_number
//   ^^__card_type^^             card brand as stored (Visa / MasterCard / …)
//                               visibility flag: has_card_type
//   ^^__transaction_id^^        gateway transaction ref
//   ^^__special_offer_code^^    applied coupon
//
//   Billing (orders.billing_*):
//     __billing_first_name, __billing_last_name, __billing_name,
//     __billing_salutation, __billing_company, __billing_email, __billing_phone,
//     __billing_address_1, __billing_address_2, __billing_city, __billing_state,
//     __billing_zip, __billing_country,
//     __billing_full_address (one-liner CSV), __billing_address_html (<br>-joined)
//
//   Shipping (first ship_to row):
//     __shipping_first_name, __shipping_last_name, __shipping_name,
//     __shipping_company, __shipping_phone, __shipping_address_1,
//     __shipping_address_2, __shipping_city, __shipping_state, __shipping_zip,
//     __shipping_country, __shipping_full_address, __shipping_address_html,
//     __shipping_method, __arrival_date, __has_shipping (1/0)
//
//   Tracking (carrier deep-link templates):
//     __tracking_code          raw tracking number (orders.tracking_code)
//     __tracking_link          carrier deep-link URL (empty when no provider)
//     __tracking_company_name  carrier label ("Yurtiçi Kargo" etc.)
//     visibility flag: has_tracking_link (true when both code + provider resolve)
//     Provider key from orders.tracking_company (optional column, probed).
//     Fallback when column missing/empty: ECOMMERCE_DEFAULT_TRACKING_PROVIDER define.
//     Supported keys: yurtici | aras | mng | ptt | surat
//
//   Timeline (order lifecycle event list):
//     __timeline               pre-built <ul.pg-ov-timeline> HTML (Bootstrap + BI)
//     visibility flag: has_timeline (true when at least one event has a timestamp)
//     Events emitted only when their timestamps are real (no hollow rows).
//
//   Invoice print:
//     __order_invoice_pdf_url  URL to customer-facing printable invoice endpoint
//     visibility flag: can_print_invoice (true only for the logged-in owner)
//
//   Legacy (kept for backward compat):
//     __shipping_address — same as __shipping_full_address (or billing fallback)
//
// ── ITEM LOOP TOKENS (per order_items row) ────────────────────────────────
//   ^^__item_id^^               order_items.id
//   ^^__item_name^^             oi.product_name
//   ^^__item_number^^           oi.item_number (SKU)
//   ^^__item_short_description^^ products.short_description (preferred display name)
//   ^^__item_description^^      products.full_description
//   ^^__item_image^^            absolute image URL
//   ^^__item_image_alt^^        alt text (short_description ?: name)
//   ^^__item_qty^^              quantity
//   ^^__item_price^^            unit price, formatted
//   ^^__item_total^^            line total, formatted
//   ^^__item_url^^              detail page link (products.address_name)
//   ^^__item_ship_to^^          per-item shipping address summary (one-liner)
//
// Note: requires ECOMMERCE to be active. Returns empty if ECOMMERCE is off or
// the order is not found / does not belong to the current user.
function _render_system_widget_order_view($tree_json, $widget_id, $cfg = array())
{
    $widget_id = (int)$widget_id;
    if ($tree_json === '' || $tree_json === null) return '';
    if (!is_array($cfg)) $cfg = array();

    if (!defined('ECOMMERCE') || ECOMMERCE !== true) return '';

    $tree_decoded = json_decode($tree_json, true);
    if (!is_array($tree_decoded)) return '';

    // NOTE: split + render are DEFERRED to AFTER order data is fetched and
    // visibility bindings are applied. Otherwise nodes with
    // `_bindings.eo_visible_if='has_X'` would render BEFORE we know whether
    // the order actually has X (e.g. the Shipping Address card on a digital
    // order, the Order Notes card when the order has no notes).

    $output_base = defined('OUTPUT_PATH') ? OUTPUT_PATH : '/';

    // Currency symbol: cfg overrides site constant
    $currency_symbol = defined('VISITOR_CURRENCY_SYMBOL') ? VISITOR_CURRENCY_SYMBOL : '₺';
    if (!empty($cfg['currency_symbol']) && is_string($cfg['currency_symbol'])) {
        $currency_symbol = $cfg['currency_symbol'];
    }

    // Date format from cfg (validates against safe whitelist)
    $date_format     = (isset($cfg['date_format']) && in_array($cfg['date_format'], array('d.m.Y', 'Y-m-d', 'd M Y')))
                           ? $cfg['date_format'] : 'd.m.Y';
    // 'd M Y' spells the month out; the name itself comes from lang() so it
    // follows the site language, not date()'s always-English output.
    $use_long_month  = ($date_format === 'd M Y');

    // Not-found message from cfg
    $not_found_message = (!empty($cfg['not_found_message']) && is_string($cfg['not_found_message']))
                             ? $cfg['not_found_message'] : lang('The order could not be found.');

    // Get order ID from URL — support both ?order_id= and ?oid=
    $order_id = 0;
    if (!empty($_GET['order_id']) && is_numeric($_GET['order_id'])) {
        $order_id = (int)$_GET['order_id'];
    } elseif (!empty($_GET['oid']) && is_numeric($_GET['oid'])) {
        $order_id = (int)$_GET['oid'];
    }

    if ($order_id <= 0) {
        // No order ID in URL — render static portion only (works as an empty state).
        // We just emit `__not_found` and rely on the leftover-token preg_replace
        // below to strip everything else cleanly. No need to list every token.
        $split_empty = _split_widget_tree($tree_decoded);
        if ($split_empty['loop_children'] === null) {
            $rendered = trim(_render_tree_node($tree_decoded, 0, 0));
        } else {
            $rendered = trim(_render_tree_node($split_empty['static_tree'], 0, 0));
        }
        $rendered = str_replace('^^__not_found^^', h($not_found_message), $rendered);
        return preg_replace('/\^\^[A-Za-z0-9_]+\^\^/', '', $rendered);
    }

    $oid_esc = e($order_id);

    // Fetch order — comprehensive column list. Tokens map 1:1 so designers
    // can drop ^^__order_X^^ anywhere without going back to the docs.
    // Security: verify the order belongs to the current logged-in user
    // (orders.user_id is the FK to users — initialize_order() maintains it).
    //
    // Note on optional columns: `notes` is NOT in the legacy DB schema (only
    // added in some upgrade installs); `member_id` lives on `contacts` not
    // `orders`. Both are queried defensively via a separate query that
    // tolerates missing columns / no contact row — so the order view widget
    // never fatals on a vanilla install. The base SELECT below contains
    // ONLY columns guaranteed by the legacy schema (data/backups/*/sql.sql).
    $current_user_id = defined('USER_ID') ? (int)USER_ID : 0;
    $order = db_item(
        "SELECT id, order_number, order_date, status,
                billing_first_name, billing_last_name, billing_company,
                billing_email_address, billing_phone_number, billing_salutation,
                billing_address_1, billing_address_2,
                billing_city, billing_state, billing_zip_code, billing_country,
                subtotal, discount, tax, shipping, surcharge, total,
                payment_method, transaction_id, special_offer_code,
                payment_installment, installment_charges,
                reference_code, tracking_code,
                card_type, card_number,
                user_id, contact_id
         FROM orders
         WHERE id = '$oid_esc'
         LIMIT 1"
    );

    if (!$order) {
        // Order not found — return empty
        return '';
    }

    // Order ids are sequential, so an arbitrary visitor must never see an order.
    // Only the logged-in owner, or the visitor whose session just completed
    // this order (guest checkout stores user_id = 0 and submit_order.php
    // redirects to the thank-you page with ?order_id=N), may see it.
    $is_owner = ($current_user_id > 0 && (int)$order['user_id'] === $current_user_id);
    $just_completed = ((int)($_SESSION['ecommerce']['completed_order_id'] ?? 0) === (int)$order['id']);
    if (!$is_owner && !$just_completed) {
        // No logged-in owner and not the order this session just placed — return empty
        return '';
    }

    // Resolve member_id via contacts table (orders.contact_id → contacts.id).
    // Defensive: missing contact row returns empty, never fatals.
    $member_id = '';
    if (!empty($order['contact_id'])) {
        $member_id = (string)db_value("SELECT member_id FROM contacts WHERE id = '" . (int)$order['contact_id'] . "' LIMIT 1");
    }

    // Resolve order notes — column doesn't exist on every install.
    // The legacy schema in data/backups/turkish_default/sql.sql has no
    // `notes` column; some upgraded installs do (apps.php selects it).
    // Probe once via SHOW COLUMNS to avoid a hard-fail SELECT.
    static $_order_view_has_notes_col = null;
    if ($_order_view_has_notes_col === null) {
        $_probe = db_value("SHOW COLUMNS FROM orders LIKE 'notes'");
        $_order_view_has_notes_col = ($_probe !== '' && $_probe !== null);
    }
    $order_notes = '';
    if ($_order_view_has_notes_col) {
        $order_notes = (string)db_value("SELECT notes FROM orders WHERE id = '$oid_esc' LIMIT 1");
    }

    // Money formatter — reused everywhere so currency symbol + locale are consistent.
    // VISITOR_CURRENCY_SYMBOL is sometimes stored as an HTML entity (e.g. "&#8378;"
    // for ₺) — when the formatted string is later h()'d in the per-item token
    // map, the leading "&" gets escaped to "&amp;" and visitors see raw
    // "&#8378;499.95" text. Decode once here so the symbol is always a real
    // Unicode glyph by the time it hits h(). ENT_QUOTES | ENT_HTML5 covers
    // numeric (&#8378;), hex (&#x20BA;), and named (&#nbsp;) entities.
    $currency_symbol = html_entity_decode((string)$currency_symbol, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $fmt_money = function ($cents) use ($currency_symbol) {
        return $currency_symbol . number_format((int)$cents / 100, 2, '.', ',');
    };

    // 'YYYY-MM-DD' → display string, honouring cfg.date_format (including the
    // Turkish long-month variant). Used by the read-only gift-card block; kept
    // next to $fmt_money so both formatters are defined before the item loop.
    $fmt_gc_date = function ($ymd) use ($use_long_month, $date_format) {
        $ts = strtotime((string)$ymd);
        if ($ts <= 0) return '';
        if ($use_long_month) {
            return date('d', $ts) . ' ' . pg_widget_month_name(date('n', $ts)) . ' ' . date('Y', $ts);
        }
        return date($date_format, $ts);
    };

    // Build addresses — full one-line (legacy) AND per-line variants
    // (each on its own paragraph for cleaner designer composition).
    $billing_name_parts = array_filter(array(
        trim((string)$order['billing_first_name']),
        trim((string)$order['billing_last_name']),
    ));
    $billing_name = implode(' ', $billing_name_parts);
    $billing_city_line_parts = array_filter(array(
        trim((string)$order['billing_zip_code']),
        trim((string)$order['billing_city']),
        trim((string)$order['billing_state']),
    ));
    $billing_city_line = implode(' ', $billing_city_line_parts);
    $billing_full_parts = array_filter(array(
        $billing_name,
        (string)$order['billing_company'],
        (string)$order['billing_address_1'],
        (string)$order['billing_address_2'],
        $billing_city_line,
        (string)$order['billing_country'],
    ));
    $billing_full_address = implode(', ', $billing_full_parts);

    // Multi-line variant — designer can drop ^^__billing_address_html^^ to get
    // a pre-formatted <br>-separated address (Bootstrap-friendly).
    $billing_address_html = implode('<br>', array_map('h', $billing_full_parts));

    // Shipping address — pull the FIRST ship_to row for this order (most
    // single-recipient orders have exactly one). Multi-recipient orders fall
    // back to this for the top-level summary; per-item shipping is exposed
    // in the loop via __item_ship_to. shipping_method NAME comes from the
    // JOIN with shipping_methods (ship_tos.shipping_method_id → fk).
    $ship_to = db_item(
        "SELECT ship_tos.first_name, ship_tos.last_name, ship_tos.company,
                ship_tos.phone_number, ship_tos.address_1, ship_tos.address_2,
                ship_tos.city, ship_tos.state, ship_tos.zip_code, ship_tos.country,
                ship_tos.arrival_date,
                shipping_methods.name AS shipping_method
         FROM ship_tos
         LEFT JOIN shipping_methods ON ship_tos.shipping_method_id = shipping_methods.id
         WHERE ship_tos.order_id = '$oid_esc' AND ship_tos.complete = 1
         ORDER BY ship_tos.id ASC
         LIMIT 1"
    );
    if (!is_array($ship_to)) $ship_to = array();
    $shipping_name_parts = array_filter(array(
        trim((string)($ship_to['first_name'] ?? '')),
        trim((string)($ship_to['last_name']  ?? '')),
    ));
    $shipping_name = implode(' ', $shipping_name_parts);
    $shipping_city_line_parts = array_filter(array(
        trim((string)($ship_to['zip_code'] ?? '')),
        trim((string)($ship_to['city']     ?? '')),
        trim((string)($ship_to['state']    ?? '')),
    ));
    $shipping_city_line = implode(' ', $shipping_city_line_parts);
    $shipping_full_parts = array_filter(array(
        $shipping_name,
        (string)($ship_to['company']   ?? ''),
        (string)($ship_to['address_1'] ?? ''),
        (string)($ship_to['address_2'] ?? ''),
        $shipping_city_line,
        (string)($ship_to['country']   ?? ''),
    ));
    $shipping_full_address = implode(', ', $shipping_full_parts);
    $shipping_address_html = implode('<br>', array_map('h', $shipping_full_parts));
    $has_shipping = !empty($ship_to);

    // Legacy ^^__shipping_address^^ token: prefer the real shipping address
    // when a ship_to row exists; fall back to billing for digital orders.
    $shipping_address = $has_shipping ? $shipping_full_address : $billing_full_address;

    // Format order date using configured format.
    // orders.order_date is stored as a unix timestamp (int), NOT a datetime
    // string — see submit_order.php:3734 ($order_date = time()). Earlier
    // code used strtotime() which returns false for raw int strings, so the
    // formatted date silently came out empty. Cast directly to int instead.
    $order_date_ts = (int)$order['order_date'];
    if ($order_date_ts > 0) {
        if ($use_long_month) {
            $order_date = date('d', $order_date_ts) . ' ' . pg_widget_month_name(date('n', $order_date_ts)) . ' ' . date('Y', $order_date_ts);
        } else {
            $order_date = date($date_format, $order_date_ts);
        }
    } else {
        $order_date = '';
    }

    // Status badge — the TEXT comes from the order, the LOOK comes from the
    // designer. Each lifecycle stage has its own cfg field
    // (cfg.status_class_complete / _pending / _shipped / _cancelled /
    // _refunded / _default); an empty field falls back to the shared default
    // map. Previously the colour was hard-coded here, which made the one
    // visual decision the designer most wants to control the only one they
    // couldn't reach.
    $status_stage      = _pg_order_status_stage((string)$order['status']);
    $status_badge_cls  = _pg_order_status_badge_class($status_stage, $cfg);
    $status_badge_html = '<span class="' . h($status_badge_cls) . '">' . h((string)$order['status']) . '</span>';

    // Fetch order items — much richer column set. address_name lets designers
    // link to the product detail page; short/full description give them a
    // choice of display name; products.code (longtext free field) surfaces
    // the SKU; ship_to_id resolves via JOIN for per-item shipping address.
    //
    // Note: order_items has NO item_number / sku column in the legacy schema
    // (see data/backups/turkish_default/sql.sql line 4206). The SKU lives on
    // products.code (or .gtin / .mpn for trade IDs). We expose .code as
    // __item_number so the token name stays familiar to designers.
    // p.form / p.form_name / p.form_quantity_type / p.gift_card drive the
    // per-row READ-ONLY blocks below: a product carrying an order form shows
    // what the visitor typed at checkout, a gift card shows the recipient
    // details. Both are output-only here — the order is already placed.
    $item_rows = db_items(
        "SELECT oi.id AS item_id, oi.product_name,
                oi.quantity, oi.price, oi.ship_to_id,
                p.id AS product_id, p.address_name, p.image_name,
                p.short_description, p.full_description, p.code AS item_code,
                p.form AS product_has_form, p.form_name, p.form_quantity_type,
                p.gift_card AS product_is_gift_card,
                st.first_name AS st_first_name, st.last_name AS st_last_name,
                st.address_1 AS st_address_1, st.city AS st_city,
                st.state AS st_state, st.zip_code AS st_zip, st.country AS st_country
         FROM order_items oi
         LEFT JOIN products p ON oi.product_id = p.id
         LEFT JOIN ship_tos st ON oi.ship_to_id = st.id
         WHERE oi.order_id = '$oid_esc'
         ORDER BY oi.id ASC"
    );

    $items = array();
    if (is_array($item_rows)) {
        foreach ($item_rows as $r) {
            $qty        = (int)$r['quantity'];
            $unit_cents = (int)$r['price'];
            $line_cents = $unit_cents * $qty;

            $image_url = !empty($r['image_name'])
                ? $output_base . encode_url_path((string)$r['image_name'])
                : '';

            // Per-item detail URL — when products.address_name is set we can
            // link to /<address_name> (catalog detail page slug).
            $item_url = !empty($r['address_name'])
                ? $output_base . encode_url_path((string)$r['address_name'])
                : '';

            // Per-item shipping summary (compact one-liner for the loop row)
            $item_ship_parts = array_filter(array(
                trim((string)($r['st_first_name'] ?? '') . ' ' . (string)($r['st_last_name'] ?? '')),
                (string)($r['st_address_1'] ?? ''),
                trim((string)($r['st_city'] ?? '') . ' ' . (string)($r['st_state'] ?? '') . ' ' . (string)($r['st_zip'] ?? '')),
                (string)($r['st_country'] ?? ''),
            ));
            $item_ship_to = implode(', ', $item_ship_parts);

            // ── Read-only product-form data ────────────────────────────
            // What the visitor typed into the product's order form at
            // checkout (a gift-card message, a conference attendee name, …).
            // Rendered as OUTPUT, never inputs — this order is placed.
            $item_form_html = '';
            if (!empty($r['product_has_form'])) {
                $_ov_form_name = isset($r['form_name']) ? trim((string)$r['form_name']) : '';
                $item_form_html = _pg_render_order_item_form_data_readonly(
                    (int)$r['item_id'],
                    (int)($r['product_id'] ?? 0),
                    $qty,
                    (string)($r['form_quantity_type'] ?? ''),
                    $_ov_form_name !== '' ? (string)lang($_ov_form_name) : ''
                );
            }

            // ── Read-only gift-card recipient details ──────────────────
            // Separate mechanism from products.form (see the note on
            // _pg_render_order_item_gift_card_readonly).
            $item_gift_card_html = '';
            if (!empty($r['product_is_gift_card'])) {
                $item_gift_card_html = _pg_render_order_item_gift_card_readonly(
                    (int)$r['item_id'],
                    $qty,
                    $unit_cents,
                    $fmt_money,
                    $fmt_gc_date
                );
            }

            $items[] = array(
                'id'                => (int)$r['item_id'],
                'product_id'        => (int)($r['product_id'] ?? 0),
                'name'              => (string)$r['product_name'],
                'form_data_html'    => $item_form_html,
                'gift_card_html'    => $item_gift_card_html,
                'item_number'       => (string)($r['item_code'] ?? ''),  // products.code as SKU
                'short_description' => (string)($r['short_description'] ?? ''),
                'full_description'  => (string)($r['full_description'] ?? ''),
                'qty'               => $qty,
                'unit_cents'        => $unit_cents,
                'line_cents'        => $line_cents,
                'unit_price'        => $fmt_money($unit_cents),
                'line_total'        => $fmt_money($line_cents),
                'image'             => $image_url,
                'url'               => $item_url,
                'ship_to'           => $item_ship_to,
            );
        }
    }

    // Payment method — translate common machine keys to friendly labels.
    // Computed here (rather than later, with $static_values) so it can
    // feed the visibility-binding context below.
    $payment_method_raw = (string)$order['payment_method'];
    $payment_method_pretty = $payment_method_raw;
    $pm_pretty_map = array(
        'Credit/Debit Card' => lang('Credit/Debit Card'),
        'PayPal Express'    => 'PayPal',
        'Offline'           => lang('Bank Transfer / Wire'),
    );
    if (isset($pm_pretty_map[$payment_method_raw])) {
        $payment_method_pretty = $pm_pretty_map[$payment_method_raw];
    }

    // Masked card number — shown under the payment method so the customer can
    // tell WHICH card they paid with when they hold several. Resolution rules
    // (already-masked / encrypted / plaintext) live in the shared helper so
    // this widget behaves identically to the legacy receipt. Empty string when
    // nothing can be safely displayed — the surrounding block should be wired
    // to the `has_card_number` visibility flag rather than rendering a label
    // with no value after it.
    $card_number_masked = _pg_mask_order_card_number((string)$order['card_number']);
    $card_type_str      = trim((string)$order['card_type']);

    // Arrival date / shipping method from ship_to (when present) — also
    // hoisted up here so visibility binding can know about it.
    $arrival_date_str = '';
    if (!empty($ship_to['arrival_date']) && $ship_to['arrival_date'] !== '0000-00-00') {
        $ad_ts = strtotime((string)$ship_to['arrival_date']);
        if ($ad_ts > 0) $arrival_date_str = $use_long_month
            ? date('d', $ad_ts) . ' ' . pg_widget_month_name(date('n', $ad_ts)) . ' ' . date('Y', $ad_ts)
            : date($date_format, $ad_ts);
    }

    // ── Tracking carrier resolution ───────────────────────────────────
    // orders.tracking_company is an OPTIONAL column (not in the legacy
    // schema). Probe once via SHOW COLUMNS; when absent we fall back to
    // the site-wide default define ECOMMERCE_DEFAULT_TRACKING_PROVIDER.
    // Provider keys: yurtici | aras | mng | ptt | surat. Empty string
    // means "no tracking link" — only the raw __tracking_code surfaces.
    static $_order_view_has_tracking_company_col = null;
    if ($_order_view_has_tracking_company_col === null) {
        $_probe_tc = db_value("SHOW COLUMNS FROM orders LIKE 'tracking_company'");
        $_order_view_has_tracking_company_col = ($_probe_tc !== '' && $_probe_tc !== null);
    }
    $tracking_provider = '';
    if ($_order_view_has_tracking_company_col) {
        $tracking_provider = (string)db_value("SELECT tracking_company FROM orders WHERE id = '$oid_esc' LIMIT 1");
    }
    if ($tracking_provider === '' && defined('ECOMMERCE_DEFAULT_TRACKING_PROVIDER')) {
        $tracking_provider = (string)ECOMMERCE_DEFAULT_TRACKING_PROVIDER;
    }
    $tracking_code_str = trim((string)$order['tracking_code']);
    $tracking_link = _eo_tracking_provider_url($tracking_provider, $tracking_code_str);
    $tracking_company_label = _eo_tracking_provider_label($tracking_provider);

    // ── Timeline events ───────────────────────────────────────────────
    // Aggregate ship_to ship_date / delivery_date across all rows; the
    // earliest non-zero date wins (a multi-recipient order is "shipped"
    // when the first ship_to goes out, "delivered" when the first
    // arrives — closest to what customers actually want to see).
    static $_order_view_has_cancelled_at_col = null;
    if ($_order_view_has_cancelled_at_col === null) {
        $_probe_ca = db_value("SHOW COLUMNS FROM orders LIKE 'cancelled_at'");
        $_order_view_has_cancelled_at_col = ($_probe_ca !== '' && $_probe_ca !== null);
    }
    $cancelled_at_ts = 0;
    if ($_order_view_has_cancelled_at_col) {
        $cancelled_at_ts = (int)db_value("SELECT cancelled_at FROM orders WHERE id = '$oid_esc' LIMIT 1");
    }
    $shipping_ts = 0;
    $delivery_ts = 0;
    $st_dates = db_items(
        "SELECT ship_date, delivery_date FROM ship_tos
         WHERE order_id = '$oid_esc'
           AND complete = 1
         ORDER BY id ASC"
    );
    if (is_array($st_dates)) {
        foreach ($st_dates as $_d) {
            if (!empty($_d['ship_date']) && $_d['ship_date'] !== '0000-00-00') {
                $_t = strtotime((string)$_d['ship_date']);
                if ($_t > 0 && ($shipping_ts === 0 || $_t < $shipping_ts)) $shipping_ts = $_t;
            }
            if (!empty($_d['delivery_date']) && $_d['delivery_date'] !== '0000-00-00') {
                $_t = strtotime((string)$_d['delivery_date']);
                if ($_t > 0 && ($delivery_ts === 0 || $_t < $delivery_ts)) $delivery_ts = $_t;
            }
        }
    }
    // Format helper — same date logic as $order_date above, plus HH:MM
    // for events that have a real time component (cancellation, order
    // creation). Pure-date columns (ship_date, delivery_date) skip the
    // time suffix since the DB only stores YYYY-MM-DD.
    $_fmt_ts = function ($ts, $with_time) use ($use_long_month, $date_format) {
        $ts = (int)$ts;
        if ($ts <= 0) return '';
        if ($use_long_month) {
            $s = date('d', $ts) . ' ' . pg_widget_month_name(date('n', $ts)) . ' ' . date('Y', $ts);
        } else {
            $s = date($date_format, $ts);
        }
        if ($with_time) $s .= ' ' . date('H:i', $ts);
        return $s;
    };
    $timeline_events = array();
    // Created — always present (order_date is required to fetch the row)
    if ($order_date_ts > 0) {
        $timeline_events[] = array(
            'icon'  => 'bi-receipt',
            'color' => 'primary',
            'label' => lang('Order Created'),
            'ts'    => $_fmt_ts($order_date_ts, true),
        );
    }
    // Paid — present when a transaction reference exists. The legacy
    // schema has no paid_at column, so we proxy with order_date: every
    // gateway-completed order sets transaction_id at the same instant
    // the order_date is stamped (submit_order.php). Skip for
    // Offline/Banka Havalesi where no auto-payment confirmation exists.
    if (trim((string)$order['transaction_id']) !== '' && $order_date_ts > 0) {
        $timeline_events[] = array(
            'icon'  => 'bi-credit-card',
            'color' => 'success',
            'label' => lang('Payment Received'),
            'ts'    => $_fmt_ts($order_date_ts, true),
        );
    }
    if ($shipping_ts > 0) {
        $timeline_events[] = array(
            'icon'  => 'bi-truck',
            'color' => 'info',
            'label' => lang('Shipped'),
            'ts'    => $_fmt_ts($shipping_ts, false),
        );
    }
    if ($delivery_ts > 0) {
        $timeline_events[] = array(
            'icon'  => 'bi-box-seam',
            'color' => 'success',
            'label' => lang('Delivered'),
            'ts'    => $_fmt_ts($delivery_ts, false),
        );
    }
    if ($cancelled_at_ts > 0) {
        $timeline_events[] = array(
            'icon'  => 'bi-x-circle',
            'color' => 'danger',
            'label' => lang('Cancelled'),
            'ts'    => $_fmt_ts($cancelled_at_ts, true),
        );
    }
    $timeline_html = _eo_render_order_timeline($timeline_events);

    // ── Invoice print URL ─────────────────────────────────────────────
    // Customer-facing printable invoice endpoint. URL is built only once
    // here so the property panel doesn't need to know the file name. The
    // visitor must already be logged in as the order owner (the endpoint
    // re-checks ownership server-side). For guest orders the URL still
    // surfaces but the endpoint will refuse access.
    $invoice_print_url = '';
    if (defined('OUTPUT_PATH') && (int)$order['id'] > 0) {
        $invoice_print_url = OUTPUT_PATH . SOFTWARE_DIRECTORY . '/order_invoice_print.php?order_id=' . (int)$order['id'];
    }
    $can_print_invoice = ($current_user_id > 0 && $current_user_id === (int)$order['user_id']);

    // ── Apply visibility bindings BEFORE tree split + render ──────────
    // Reuses _eo_apply_visibility_bindings (defined in this same file for
    // express_order). The walker is widget-agnostic — it drops any node
    // whose `_bindings.eo_visible_if` resolves to a falsy context flag.
    // Flags reflect the live order data so the Shipping Address /
    // Order Notes cards (etc.) vanish on orders that don\'t have
    // shipping or notes, instead of rendering as empty stubs.
    // ── Cancellation policy resolution ─────────────────────────────────
    // Three gates control whether the cancel UI surfaces on this order:
    //   1. Site setting: ECOMMERCE_ORDER_CANCEL_ALLOWED must be true
    //   2. Order status: must not already be cancelled
    //   3. (optional) Pre-shipment: when ECOMMERCE_ORDER_CANCEL_UNTIL_SHIPPED
    //      is true (default), a recorded tracking code locks it.
    // Designer wires the cancel button under `eo_visible_if='can_cancel'`
    // so when the gate is closed the button + form vanish from the DOM
    // instead of being rendered then disabled.
    $_ov_cancel_allowed = defined('ECOMMERCE_ORDER_CANCEL_ALLOWED') && ECOMMERCE_ORDER_CANCEL_ALLOWED === true;
    $_ov_already_cancelled = ($order['status'] === 'cancelled');
    $_ov_can_cancel = $_ov_cancel_allowed && !$_ov_already_cancelled;
    // This widget is the CUSTOMER's view, so the guard always applies here.
    // Must stay in lockstep with process_order_cancellation()'s guard — the
    // shared _order_has_shipped() helper is what keeps the button's visibility
    // and the endpoint's answer from disagreeing.
    if ($_ov_can_cancel && (!defined('ECOMMERCE_ORDER_CANCEL_UNTIL_SHIPPED') || ECOMMERCE_ORDER_CANCEL_UNTIL_SHIPPED !== false)) {
        if (_order_has_shipped($order_id)) $_ov_can_cancel = false;
    }

    // URL query flag set by cancel_order.php redirect — drives the
    // success/failure notice token. Values: '1' (success), 'already',
    // 'shipped'. Missing = nothing to show.
    $_ov_cancel_flash = isset($_GET['cancelled']) ? (string)$_GET['cancelled'] : '';

    $_ov_vis_ctx = array(
        'has_shipping'             => (bool)$has_shipping,
        'has_notes'                => trim($order_notes) !== '',
        'has_payment_method'       => $payment_method_raw !== '',
        // Card row surfaces only when a maskable number actually resolved —
        // gateway-less / offline orders and undecryptable blobs stay hidden
        // instead of printing "Kart Numarası" above nothing.
        'has_card_number'          => $card_number_masked !== '',
        'has_card_type'            => $card_type_str !== '',
        'has_transaction_id'       => trim((string)$order['transaction_id']) !== '',
        'has_special_offer_code'   => trim((string)$order['special_offer_code']) !== '',
        'has_shipping_method'      => isset($ship_to['shipping_method']) && (string)$ship_to['shipping_method'] !== '',
        'has_arrival_date'         => $arrival_date_str !== '',
        'has_tracking_code'        => trim((string)$order['tracking_code']) !== '',
        // Installment-related flags — true when the Iyzipay 3DS flow stored
        // a plan > 1 month (1 = paid up front / single payment, suppress row).
        'has_installment'          => (int)$order['payment_installment'] > 1,
        'has_installment_charges'  => (int)$order['installment_charges'] > 0,
        // Totals row flags (parallel to express_order)
        'has_discount'             => (int)$order['discount']  > 0,
        'has_tax'                  => (int)$order['tax']       > 0,
        'has_shipping_cost'        => (int)$order['shipping']  > 0,
        'has_surcharge'            => (int)$order['surcharge'] > 0,
        // Cancellation gates
        'can_cancel'               => $_ov_can_cancel,
        'is_cancelled'             => $_ov_already_cancelled,
        // Flash-style notice from cancel_order.php redirect
        'cancel_flash_success'     => $_ov_cancel_flash === '1',
        'cancel_flash_already'     => $_ov_cancel_flash === 'already',
        'cancel_flash_shipped'     => $_ov_cancel_flash === 'shipped',
        // Tracking carrier link surfaces only when BOTH a tracking_code
        // AND a recognised provider resolved to a real URL.
        'has_tracking_link'        => $tracking_link !== '',
        // Timeline card hides on orders with no events (impossible in
        // practice — order_date is always set — but kept for symmetry).
        'has_timeline'             => $timeline_html !== '',
        // Print invoice button: logged-in owner only. Guests viewing a
        // retrieved order won't see the action.
        'can_print_invoice'        => $can_print_invoice,
    );
    if (function_exists('_eo_apply_visibility_bindings')) {
        _eo_apply_visibility_bindings($tree_decoded, $_ov_vis_ctx);
    }

    // ── Now split + render with the visibility-filtered tree ──────────
    $split = _split_widget_tree($tree_decoded);
    if ($split['loop_children'] === null) {
        $loop_template = trim(_render_tree_node($tree_decoded, 0, 0));
        $static_html   = '';
    } else {
        $loop_template = '';
        foreach ($split['loop_children'] as $child) {
            $loop_template .= _render_tree_node($child, 0, 0);
        }
        $loop_template = trim($loop_template);
        $static_html   = trim(_render_tree_node($split['static_tree'], 0, 0));
    }

    // Build loop output
    $loop_rendered = '';
    if ($loop_template !== '' && count($items) > 0) {
        foreach ($items as $idx => $item) {
            $row_values = array(
                // Existing tokens — kept for backward compatibility
                '__item_name'              => h($item['name']),
                '__item_qty'               => h((string)$item['qty']),
                '__item_price'             => h($item['unit_price']),
                '__item_total'             => h($item['line_total']),
                '__item_image'             => h($item['image']),
                // New: identifiers + alternate display names
                '__item_id'                => (string)$item['id'],
                '__item_number'            => h($item['item_number']),
                '__item_short_description' => h($item['short_description']),
                '__item_description'       => h($item['full_description']),
                // The image alt text follows the same precedence as cart:
                // prefer the readable short_description, fall back to name.
                '__item_image_alt'         => h($item['short_description'] !== '' ? $item['short_description'] : $item['name']),
                // Detail link (empty when product has no address_name slug)
                '__item_url'               => h($item['url']),
                // Per-item shipping (one-liner; empty for digital products)
                '__item_ship_to'           => h($item['ship_to']),
                // Read-only product-form answers / gift-card details.
                // Pre-built HTML → NOT h()'d. Empty for ordinary products,
                // so the token quietly disappears on those rows.
                // Kept as two separate tokens (mirroring shopping_cart)
                // because a product can carry a form OR be a gift card and
                // the designer may want them in different places.
                '__item_form_data_html'    => (string)$item['form_data_html'],
                '__item_gift_card_html'    => (string)$item['gift_card_html'],
            );
            $row_html = $loop_template;
            $rkeys = array_keys($row_values);
            usort($rkeys, function ($a, $b) { return strlen($b) - strlen($a); });
            foreach ($rkeys as $k) {
                $row_html = str_replace('^^' . $k . '^^', $row_values[$k], $row_html);
            }
            $row_html = preg_replace('/\^\^[A-Za-z0-9_]+\^\^/', '', $row_html);

            // Auto-append the read-only form / gift-card blocks when the row
            // HAS them and the designer hasn't placed the matching token in
            // the loop template. Same fallback the shopping_cart widget uses:
            // trees saved before these tokens existed would otherwise drop
            // the data silently, and on an order receipt that data is often
            // the whole point (who the gift card was sent to, which name was
            // engraved). A designer who DOES place the token keeps control
            // over where the block lands.
            // Safe to concatenate here: the order_view loop row is a <div>,
            // not a <tr> — see the starter tree in style_designer.js.
            if ($row_values['__item_form_data_html'] !== ''
                && strpos($loop_template, '^^__item_form_data_html^^') === false) {
                $row_html .= $row_values['__item_form_data_html'];
            }
            if ($row_values['__item_gift_card_html'] !== ''
                && strpos($loop_template, '^^__item_gift_card_html^^') === false) {
                $row_html .= $row_values['__item_gift_card_html'];
            }

            // Uniquify Bootstrap component IDs per item row
            $iter_suffix = '_pgw' . $widget_id . 'r' . $idx;
            $row_html = preg_replace('/\bid="([^"]+)"/',            'id="$1'              . $iter_suffix . '"', $row_html);
            $row_html = preg_replace('/data-bs-target="#([^"]+)"/', 'data-bs-target="#$1' . $iter_suffix . '"', $row_html);
            $row_html = preg_replace('/aria-controls="([^"]+)"/',   'aria-controls="$1'   . $iter_suffix . '"', $row_html);
            $row_html = preg_replace('/href="#([^"]+)"/',           'href="#$1'           . $iter_suffix . '"', $row_html);

            $loop_rendered .= $row_html;
        }
    }

    // Number of distinct line items + total units (across all items)
    $item_count = count($items);
    $unit_count = 0;
    foreach ($items as $_it) { $unit_count += (int)$_it['qty']; }

    // (payment_method_pretty + arrival_date_str were computed earlier so
    // they could feed the visibility-binding context. Reuse them here.)

    // Apply static tokens — comprehensive set covering order, billing,
    // shipping, totals, payment, items count, status badge. Designers can
    // freely drop any of these into the tree without code changes.
    $static_values = array(
        // ── Existing (kept for backward compat) ────────────────────────
        '__order_no'         => h((string)$order['order_number']),
        '__order_date'       => h($order_date),
        '__order_status'     => h((string)$order['status']),
        '__order_total'      => $fmt_money((int)$order['total']),
        '__shipping_address' => h($shipping_address),
        '__not_found'        => '',

        // ── Order identifiers / meta ──────────────────────────────────
        '__order_id'          => (string)(int)$order['id'],
        '__order_status_badge' => $status_badge_html,

        // ── Totals breakdown (raw + formatted) ────────────────────────
        '__order_subtotal'   => $fmt_money((int)$order['subtotal']),
        '__order_discount'   => $fmt_money((int)$order['discount']),
        '__order_tax'        => $fmt_money((int)$order['tax']),
        '__order_shipping'   => $fmt_money((int)$order['shipping']),
        '__order_surcharge'  => $fmt_money((int)$order['surcharge']),

        // ── Counts ────────────────────────────────────────────────────
        '__order_item_count' => (string)$item_count,
        '__order_unit_count' => (string)$unit_count,

        // ── Cancellation ──────────────────────────────────────────────
        // __cancel_form: pre-built HTML <form> posting to cancel_order.php
        //   with CSRF token, order_id hidden, optional reason textarea,
        //   and the destructive "Siparişi İptal Et" submit. Designer drops
        //   ^^__cancel_form^^ wherever they want the form. Wrapped in
        //   visibility binding (eo_visible_if='can_cancel') so the form
        //   element disappears when policy disallows cancel.
        // __cancel_status: pre-built alert block shown after the redirect
        //   from cancel_order.php (?cancelled=1 success / =shipped error /
        //   =already info). Empty string when no flash present.
        '__cancel_form'           => _eo_render_cancel_order_form(
            (int)$order['id'],
            (string)$order['payment_method'],
            (int)$order['total']
        ),
        '__cancel_status'         => _eo_render_cancel_status_alert($_ov_cancel_flash),

        // ── Payment ───────────────────────────────────────────────────
        '__payment_method'        => h($payment_method_raw),
        '__payment_method_pretty' => h($payment_method_pretty),
        // Masked PAN — never the raw number. See _pg_mask_order_card_number.
        '__card_number_masked'    => h($card_number_masked),
        '__card_type'             => h($card_type_str),
        '__transaction_id'        => h((string)$order['transaction_id']),
        '__special_offer_code'    => h((string)$order['special_offer_code']),
        '__reference_code'        => h((string)$order['reference_code']),
        '__tracking_code'         => h((string)$order['tracking_code']),
        // Installment info (Iyzipay 3DS only; PayPal/Offline always = 1).
        '__installment_count'     => (string)(int)$order['payment_installment'],
        '__installment_charges'   => $fmt_money((int)$order['installment_charges']),
        // Per-month amount when paying in installments: (total + charges) ÷ count.
        // Renders empty for single-payment orders (avoids dividing by 1 noise).
        '__installment_per_month' => ((int)$order['payment_installment'] > 1
            ? $fmt_money((int)round(((int)$order['total'] + (int)$order['installment_charges']) / (int)$order['payment_installment']))
            : ''),
        // Notes/member_id resolved separately (legacy schema may lack them).
        '__order_notes'           => nl2br(h($order_notes)),
        '__member_id'             => h($member_id),

        // ── Billing — individual fields + pre-built composite ─────────
        '__billing_first_name' => h((string)$order['billing_first_name']),
        '__billing_last_name'  => h((string)$order['billing_last_name']),
        '__billing_name'       => h($billing_name),
        '__billing_salutation' => h((string)$order['billing_salutation']),
        '__billing_company'    => h((string)$order['billing_company']),
        '__billing_email'      => h((string)$order['billing_email_address']),
        '__billing_phone'      => h((string)$order['billing_phone_number']),
        '__billing_address_1'  => h((string)$order['billing_address_1']),
        '__billing_address_2'  => h((string)$order['billing_address_2']),
        '__billing_city'       => h((string)$order['billing_city']),
        '__billing_state'      => h((string)$order['billing_state']),
        '__billing_zip'        => h((string)$order['billing_zip_code']),
        '__billing_country'    => h((string)$order['billing_country']),
        '__billing_full_address' => h($billing_full_address),
        '__billing_address_html' => $billing_address_html,   // pre-rendered <br>-joined block (NOT h()'d)

        // ── Shipping — same shape as billing, sourced from ship_tos ──
        '__shipping_first_name' => h((string)($ship_to['first_name'] ?? '')),
        '__shipping_last_name'  => h((string)($ship_to['last_name']  ?? '')),
        '__shipping_name'       => h($shipping_name),
        '__shipping_company'    => h((string)($ship_to['company']   ?? '')),
        '__shipping_phone'      => h((string)($ship_to['phone_number'] ?? '')),
        '__shipping_address_1'  => h((string)($ship_to['address_1'] ?? '')),
        '__shipping_address_2'  => h((string)($ship_to['address_2'] ?? '')),
        '__shipping_city'       => h((string)($ship_to['city']      ?? '')),
        '__shipping_state'      => h((string)($ship_to['state']     ?? '')),
        '__shipping_zip'        => h((string)($ship_to['zip_code']  ?? '')),
        '__shipping_country'    => h((string)($ship_to['country']   ?? '')),
        '__shipping_full_address' => h($shipping_full_address),
        '__shipping_address_html' => $shipping_address_html,
        '__shipping_method'     => h((string)($ship_to['shipping_method'] ?? '')),
        '__arrival_date'        => h($arrival_date_str),
        '__has_shipping'        => $has_shipping ? '1' : '0',

        // ── Tracking (carrier link templates) ──────────────────────────
        // Real carrier deep-link when orders.tracking_company resolves to
        // a known provider, OR the site-wide default define matches.
        // Empty otherwise — designers should wire the surrounding card
        // under eo_visible_if='has_tracking_link'.
        '__tracking_link'           => h($tracking_link),
        '__tracking_company_name'   => h($tracking_company_label),

        // ── Timeline (lifecycle event list) ────────────────────────────
        // Pre-built Bootstrap timeline HTML — already wrapped in
        // <ul class="pg-ov-timeline">. Empty when no events have
        // timestamps (vanishingly rare; order_date is always set).
        // NOT h()'d — output is structural HTML.
        '__timeline'                => $timeline_html,

        // ── Invoice print ──────────────────────────────────────────────
        // URL to the customer-facing printable invoice endpoint. Owner
        // check is enforced server-side; this token only builds the link.
        '__order_invoice_pdf_url'   => h($invoice_print_url),
    );

    if ($static_html === '') {
        // No loop_area — replace tokens in the single rendered template
        $rendered = $loop_template;
        $skeys = array_keys($static_values);
        usort($skeys, function ($a, $b) { return strlen($b) - strlen($a); });
        foreach ($skeys as $k) {
            $rendered = str_replace('^^' . $k . '^^', $static_values[$k], $rendered);
        }
        return preg_replace('/\^\^[A-Za-z0-9_]+\^\^/', '', $rendered);
    }

    $skeys = array_keys($static_values);
    usort($skeys, function ($a, $b) { return strlen($b) - strlen($a); });
    foreach ($skeys as $k) {
        $static_html = str_replace('^^' . $k . '^^', $static_values[$k], $static_html);
    }
    $static_html = preg_replace('/\^\^[A-Za-z0-9_]+\^\^/', '', $static_html);

    return str_replace('<!--pg-loop-slot-->', $loop_rendered, $static_html);
}

// Render a 'membership' system widget: the membership activation form.
//
// Posts to <software>/membership_entrance.php with register=true - the
// script the legacy entrance page posts to; pg_member_activate() does the
// rest for both. Controls by name: member_id, first_name, last_name,
// username (optional), email / email_verify (the legacy names
// email_address / email_address_verify work too), password, password_verify,
// password_hint, opt_in, register_remember_me. Any other control whose
// `_cf.contact_field` names an address-book column is written to the
// member's contact (see pg_member_widget_register_opts()).
//
// A Google sign-in that matched no account lands here with a pending record:
// the identity is verified, the membership number is what is missing. The
// form is prefilled from Google and a password is no longer required.
//
// Tokens: ^^__site_name^^, ^^__member_id_label^^, ^^__login_url^^,
// ^^__google_signup_url^^, ^^__password_rules^^, ^^__opt_in_label^^.
// Sections: password_rules, google_signup, login_link. Errors and the
// "membership active" notice: liveform 'membership_entrance', printed by
// the Messages node.
//
// cfg: redirect_page_id, login_page_id, contact_group_id (the site setting
// when empty), show_remember_me (default on).
function _render_system_widget_membership($tree_json, $widget_id, $cfg = array(), $mode = 'preview')
{
    $widget_id = (int)$widget_id;
    if ($tree_json === '' || $tree_json === null) return '';
    if (!is_array($cfg)) $cfg = array();

    $tree = json_decode($tree_json, true);
    if (!is_array($tree)) return '';

    $software  = (defined('OUTPUT_PATH') ? OUTPUT_PATH : '/') . (defined('OUTPUT_SOFTWARE_DIRECTORY') ? OUTPUT_SOFTWARE_DIRECTORY : '');
    $page_url  = function_exists('get_request_uri') ? (string)get_request_uri() : '';
    $site_name = _pg_member_site_name();
    $id_label  = defined('MEMBER_ID_LABEL') ? (string)MEMBER_ID_LABEL : '';

    $send_to = '';
    if (!empty($cfg['redirect_page_id']) && (int)$cfg['redirect_page_id'] > 0) {
        $send_to = _pg_member_page_url($cfg['redirect_page_id']);
    }
    if ($send_to === '') $send_to = _pg_member_query_path('send_to');

    $login_url = '';
    if (!empty($cfg['login_page_id'])) $login_url = _pg_member_page_url($cfg['login_page_id']);
    if ($login_url === '' && function_exists('get_page_type_url')) {
        $legacy = get_page_type_url('login');
        if ($legacy !== false && $legacy !== '') $login_url = (string)$legacy;
    }
    if ($login_url === '') $login_url = $software . '/';

    $lf = new liveform('membership_entrance');

    // Signed in: the form would open a second account. The notice an
    // activation just left prints with it; an editor still sees the form.
    if ($mode !== 'edit' && function_exists('pg_session_signed_in') && pg_session_signed_in()) {
        return _pg_member_render_signed_in($tree, $lf, 'membership_entrance', $send_to, $page_url,
            array('password_rules', 'google_signup', 'login_link'),
            array('__login_url', '__google_signup_url'),
            array('__site_name' => h($site_name), '__member_id_label' => h($id_label), '__login_url' => h($login_url),
                  '__opt_in_label' => h(defined('OPT_IN_LABEL') ? (string)OPT_IN_LABEL : '')));
    }

    // Back from Google with no account: say what is left to do, prefill what
    // Google told us, and stop requiring a password.
    $google_pending = $_SESSION['software']['google_membership_pending'] ?? null;
    $google_signup  = ($mode !== 'edit' && is_array($google_pending)
        && ((time() - ((int) ($google_pending['time'] ?? 0))) <= 900));

    $submitted = $lf->field_in_session('token');
    if (!$submitted) {
        $remembered = ((isset($_COOKIE['software']['remember_me']) ? (string)$_COOKIE['software']['remember_me'] : '') === 'true');
        _pg_member_fill_control($tree, 'opt_in', '1', true);
        _pg_member_fill_control($tree, 'register_remember_me', '1', $remembered);
        _pg_member_fill_control($tree, 'remember_me', '1', $remembered);
        if ($google_signup) {
            $g_email = (string)($google_pending['email'] ?? '');
            _pg_member_fill_control($tree, 'first_name', (string)($google_pending['first_name'] ?? ''));
            _pg_member_fill_control($tree, 'last_name', (string)($google_pending['last_name'] ?? ''));
            foreach (array('email', 'email_verify', 'email_address', 'email_address_verify') as $n) {
                _pg_member_fill_control($tree, $n, $g_email);
            }
            _pg_member_fill_control($tree, 'username', get_unique_username((string)strtok($g_email, '@')));
        }
    } else {
        _pg_member_fill_from_form($tree, $lf, array('password', 'password_verify'));
    }
    if ($google_signup) {
        $lf->add_notice(lang('Your Google account is verified. Enter your membership details to finish - a password is optional, because you can keep signing in with Google.'));
        foreach (array('password', 'password_verify') as $n) {
            _pg_member_edit_control($tree, $n, function (&$node) { _pg_member_set_attr($node, 'required', null); });
        }
    }

    if (!(defined('PASSWORD_HINT') && PASSWORD_HINT)) {
        _pg_member_drop_control($tree, 'password_hint');
    }
    $remember_ok = defined('REMEMBER_ME') && REMEMBER_ME
                && !(isset($cfg['show_remember_me']) && ($cfg['show_remember_me'] === false || $cfg['show_remember_me'] === 0 || $cfg['show_remember_me'] === '0'));
    if (!$remember_ok) {
        _pg_member_drop_control($tree, 'register_remember_me');
        _pg_member_drop_control($tree, 'remember_me');
    }

    $rules_html  = (defined('STRONG_PASSWORD') && STRONG_PASSWORD && function_exists('get_strong_password_requirements'))
        ? '<div class="pg-password-rules small text-muted">' . get_strong_password_requirements() . '</div>' : '';
    $google_url  = (!$google_signup && function_exists('pg_google_signin_url')) ? pg_google_signin_url($send_to, 'membership') : '';
    $google_html = (!$google_signup && function_exists('pg_google_signin_button')) ? pg_google_signin_button($send_to, 'membership') : '';
    $opt_label   = defined('OPT_IN_LABEL') ? (string)OPT_IN_LABEL : '';

    pg_cf_apply_section_bindings($tree, array(
        'password_rules' => $rules_html,
        'google_signup'  => $google_html,
        'login_link'     => '<a href="' . h($login_url) . '" class="pg-member-link pg-login-link">' . h(lang('Already a member? Sign in')) . '</a>',
    ));
    _pg_member_drop_empty_links($tree, array(
        '__login_url'         => $login_url,
        '__google_signup_url' => $google_url,
    ));
    pg_cf_link_labels($tree);
    _pg_inject_messages_node($tree, 'membership_entrance');

    $rendered = trim(_render_tree_node($tree, 0, 0));
    $lf->remove();
    $rendered = _pg_member_form_wrap($rendered, $software . '/membership_entrance.php', array(
        'register'        => 'true',
        'send_to'         => $send_to,
        'return_to'       => $page_url,
        'require_cookies' => 'true',
        'pg_widget_id'    => $widget_id,
    ), 'pg-membership-form');

    return _pg_member_apply_tokens($rendered, array(
        '__site_name'         => h($site_name),
        '__member_id_label'   => h($id_label),
        '__login_url'         => h($login_url),
        '__google_signup_url' => h($google_url),
        '__password_rules'    => $rules_html,
        '__opt_in_label'      => h($opt_label),
    ));
}

// Render a 'custom_form' system widget.
//
// Two sources, chosen in the widget's settings:
//   form_source = 'page'      — the form belongs to the page the widget is on.
//                               The controls in the tree ARE its fields; the
//                               save wrote them to form_fields and this render
//                               resolves their row ids.
//   form_source = 'existing'  — a form built elsewhere (a legacy custom form
//                               page, or another designed page), shown here.
//                               The editor rebuilt the widget from that form's
//                               fields, so the tree carries its controls too.
//
// Tokens: ^^__form_title^^       — form_name from custom_form_pages
//         ^^__error_message^^    — error from session
//         ^^__success_message^^  — success from session
// Sections (_bindings.section): captcha — the site-wide CAPTCHA block.
//
// $mode is the page render mode; 'edit' with edit access shows the
// office-use-only controls and tells custom_form.php they were on the form.
function _render_system_widget_custom_form($tree_json, $widget_id, $cfg = array(), $mode = 'preview')
{
    $widget_id = (int)$widget_id;
    if ($tree_json === '' || $tree_json === null) return '';
    if (!is_array($cfg)) $cfg = array();

    $tree_decoded = json_decode($tree_json, true);
    if (!is_array($tree_decoded)) return '';

    // Which page owns the form?
    //
    // 'page' (the default for a widget drawn in the editor) means the page the
    // widget is rendering on - the fields were drawn here, so the submissions
    // belong here. That resolution has to happen at render time and not in the
    // config, because the same widget can be placed on more than one page and
    // each of those pages then collects its own submissions.
    $form_source = _pg_cf_widget_owns_page_form($cfg) ? 'page' : 'existing';

    $rendered_page_id = function_exists('pg_rendered_page') ? (int)pg_rendered_page() : 0;
    $form_page_id = 0;
    if ($form_source === 'page') {
        $form_page_id = $rendered_page_id;
    } elseif (!empty($cfg['form_id']) && (int)$cfg['form_id'] > 0) {
        $form_page_id = (int)$cfg['form_id'];
    } elseif (!empty($cfg['custom_form_page_id']) && (int)$cfg['custom_form_page_id'] > 0) {
        $form_page_id = (int)$cfg['custom_form_page_id'];
    }

    // Form title
    $form_title  = '';
    $cf_page_row = array();
    $cf_folder   = 0;
    if ($form_page_id > 0) {
        $cf_page_row = db_item(
            "SELECT form_name, confirmation_type, confirmation_message
             FROM custom_form_pages WHERE page_id = '" . e($form_page_id) . "' LIMIT 1"
        );
        if (!is_array($cf_page_row)) $cf_page_row = array();
        if (!empty($cf_page_row['form_name'])) {
            $form_title = (string)$cf_page_row['form_name'];
        }
        // The folder the "use folder name as default" option reads from, and
        // the folder whose edit access decides who is staff here.
        $cf_folder = (int)db_value("SELECT page_folder FROM page WHERE page_id = '$form_page_id' LIMIT 1");
    }

    // Just submitted successfully. custom_form.php sends the visitor back here
    // with `<page_id>_confirmation=true`, and the form must NOT be drawn again
    // — a visitor who sees the empty form return assumes nothing happened and
    // submits a second time. Same branch, same query parameter and same
    // wrapper class as the legacy screen, so existing CSS still applies.
    if ($form_page_id > 0
        && isset($_GET[$form_page_id . '_confirmation'])
        && $_GET[$form_page_id . '_confirmation'] === 'true'
        && (!isset($cf_page_row['confirmation_type']) || $cf_page_row['confirmation_type'] === 'message')) {
        $cf_done = isset($cf_page_row['confirmation_message']) ? (string)$cf_page_row['confirmation_message'] : '';
        if (trim(strip_tags($cf_done)) === '') $cf_done = lang('Thank you. Your message has been received.');
        return '<div class="confirmation_message alert alert-success">' . $cf_done . '</div>';
    }

    // Error / success messages from session
    $error_message   = isset($_SESSION['form_error'])   ? (string)$_SESSION['form_error']   : '';
    $success_message = isset($_SESSION['form_success']) ? (string)$_SESSION['form_success'] : '';

    // The form's own liveform: what the visitor typed survives a failed
    // submission in this session, and the CAPTCHA answer is validated against
    // values stored in it. custom_form.php constructs it with the same key.
    $cf_liveform = null;
    if ($form_page_id > 0 && class_exists('liveform')) {
        $cf_liveform = new liveform($form_page_id);
    }

    // Staff view: the page in edit mode, seen by someone who may edit it. The
    // same gate custom_form.php applies before it reads an office-use-only
    // field from the POST, so what is shown is what will be stored.
    $cf_staff = ($mode === 'edit' && $form_page_id > 0
                 && defined('USER_LOGGED_IN') && USER_LOGGED_IN
                 && function_exists('check_edit_access') && check_edit_access($cf_folder));

    // Resolve every control to the row custom_form.php will look for, and
    // re-fill what the visitor typed. The map is empty until the page has
    // been saved once - a control whose field does not exist yet renders
    // under its own name and is simply not stored, which is the harmless
    // half of "not saved yet".
    if ($form_page_id > 0 && function_exists('pg_cf_field_map')) {
        $cf_field_map = pg_cf_field_map($form_page_id);
        pg_cf_apply_field_bindings($tree_decoded, $cf_field_map, $cf_liveform, $cf_folder, $cf_staff);
    }
    // Ids are final now, including the ones invented above, so a label that
    // names nothing can be pointed at the control beside it.
    pg_cf_link_labels($tree_decoded);

    // Validation errors come back on the liveform, not in $_SESSION['form_error']
    // - custom_form.php marks the offending field and redirects to send_to. The
    // messages node is what prints them, scoped to THIS form so a second form
    // on the page does not show the first one's errors.
    if ($form_page_id > 0 && function_exists('_pg_inject_messages_node')) {
        _pg_inject_messages_node($tree_decoded, (string)$form_page_id);
    }

    // Section bindings. `captcha` is the site-wide block, honoured only when
    // the CAPTCHA setting is on and the visitor is not signed in — the same
    // gate get_captcha_fields() applies, so an empty container disappears
    // rather than leaving a labelled box with nothing in it.
    $cf_sections = array(
        'captcha' => ($cf_liveform && defined('CAPTCHA') && CAPTCHA && function_exists('get_captcha_fields'))
            ? (string)get_captcha_fields($cf_liveform) : '',
    );
    pg_cf_apply_section_bindings($tree_decoded, $cf_sections);

    // Render the full tree (loop_area present but hidden)
    $rendered = trim(_render_tree_node($tree_decoded, 0, 0));

    // Wrap in the <form> custom_form.php expects. The controls are the
    // designer's; nothing is added under them.
    if ($form_page_id > 0) {
        $cf_action  = (defined('OUTPUT_PATH') ? OUTPUT_PATH : '/') .
                      (defined('OUTPUT_SOFTWARE_DIRECTORY') ? OUTPUT_SOFTWARE_DIRECTORY . '/' : '') .
                      'custom_form.php';
        $cf_send_to = function_exists('get_request_uri') ? get_request_uri() : '';

        // A file field needs the multipart envelope.
        $cf_has_file = (bool) db_value(
            "SELECT COUNT(*) FROM form_fields
             WHERE page_id = '" . e($form_page_id) . "' AND type = 'file upload'"
        );
        $cf_enctype = $cf_has_file ? ' enctype="multipart/form-data"' : '';

        // Bootstrap's own validation: `novalidate` stops the browser's native
        // bubbles, `needs-validation` + the snippet turn on the styled
        // :invalid feedback on the first submit attempt. Nothing is prevented
        // that the server would have accepted — the same `required` and
        // `pattern` are checked again in custom_form.php, this only moves the
        // first "no" to before the round trip.
        $form_open = '<form action="' . h($cf_action) . '" method="post" novalidate' .
                     ' class="needs-validation pg-cf-form"' . $cf_enctype . '>' .
                     (function_exists('get_token_field') ? get_token_field() : '') .
                     '<input type="hidden" name="page_id" value="' . $form_page_id . '">' .
                     '<input type="hidden" name="send_to" value="' . h($cf_send_to) . '">' .
                     ($cf_staff ? '<input type="hidden" name="office_use_only" value="true">' : '');

        $rendered = $form_open . $rendered . pg_cf_validation_script() . '</form>';
    }

    $values = array(
        '__form_title'      => h($form_title),
        '__error_message'   => h($error_message),
        '__success_message' => h($success_message),
        // Trees written by earlier editor builds bound the button text to
        // this; the editor drops the binding on load, the page must not go
        // blank in between.
        '__submit_label'    => h(lang('Submit')),
    );

    $keys = array_keys($values);
    usort($keys, function ($a, $b) { return strlen($b) - strlen($a); });
    foreach ($keys as $k) {
        $rendered = str_replace('^^' . $k . '^^', $values[$k], $rendered);
    }
    return preg_replace('/\^\^[A-Za-z0-9_]+\^\^/', '', $rendered);
}

// Render a 'calendar_view' system widget. Renders a month-based event list with
// loop_area per event. Events are read from the Pinegrap calendar system
// (users_calendars_xref / calendar tables). If those tables do not exist or have
// no data the widget gracefully renders an empty list.
//
// Static tokens: ^^__month_name^^     — localised month name (e.g. "Nisan")
//                ^^__year^^           — four-digit year (e.g. "2026")
//                ^^__prev_month_url^^ — URL to the previous month (?cal_month=…&cal_year=…)
//                ^^__next_month_url^^ — URL to the next month
//
// Loop tokens (per event):
//   ^^__event_title^^   — event name / subject
//   ^^__event_date^^    — formatted event date (dd.mm.yyyy)
//   ^^__event_time^^    — formatted start time (HH:MM), empty if all-day
//   ^^__event_url^^     — link to event detail page (empty if none)
//   ^^__event_excerpt^^ — short description / notes
//
// URL parameters:
//   cal_month  — 1-12 (defaults to current month)
//   cal_year   — four-digit year (defaults to current year)
//
// NOTE: If the calendar_events table is not present in this installation the
// function returns a placeholder comment rather than a fatal error.
function _render_system_widget_calendar_view($tree_json, $widget_id, $cfg = array())
{
    $widget_id = (int)$widget_id;
    if ($tree_json === '' || $tree_json === null) return '';
    if (!is_array($cfg)) $cfg = array();

    $tree_decoded = json_decode($tree_json, true);
    if (!is_array($tree_decoded)) return '';

    $split = _split_widget_tree($tree_decoded);

    if ($split['loop_children'] === null) {
        $loop_template = trim(_render_tree_node($tree_decoded, 0, 0));
        $static_html   = '';
    } else {
        $loop_template = '';
        foreach ($split['loop_children'] as $child) {
            $loop_template .= _render_tree_node($child, 0, 0);
        }
        $loop_template = trim($loop_template);
        $static_html   = trim(_render_tree_node($split['static_tree'], 0, 0));
    }

        // events_limit from cfg (max events to fetch; default 100)
    $cal_events_limit = (isset($cfg['events_limit']) && (int)$cfg['events_limit'] > 0)
        ? (int)$cfg['events_limit']
        : 100;

    // date_format from cfg (whitelist)
    $valid_date_formats_cal = array('d.m.Y', 'Y-m-d', 'd M Y');
    $cal_date_format = (isset($cfg['date_format']) && in_array($cfg['date_format'], $valid_date_formats_cal))
        ? $cfg['date_format']
        : 'd.m.Y';

    // no_events_message from cfg
    $cal_no_events_message = (!empty($cfg['no_events_message']) && is_string($cfg['no_events_message']))
        ? $cfg['no_events_message']
        : '';

    // Determine which month/year to display
    $now_month = (int)date('n');
    $now_year  = (int)date('Y');

    $cal_month = (isset($_GET['cal_month']) && is_numeric($_GET['cal_month']))
        ? max(1, min(12, (int)$_GET['cal_month']))
        : $now_month;
    $cal_year = (isset($_GET['cal_year']) && is_numeric($_GET['cal_year']))
        ? max(2000, min(2100, (int)$_GET['cal_year']))
        : $now_year;

    $month_name = pg_widget_month_name($cal_month);
    if ($month_name === '') $month_name = (string)$cal_month;

    // Build prev / next month URLs
    $prev_month = $cal_month - 1;
    $prev_year  = $cal_year;
    if ($prev_month < 1) { $prev_month = 12; $prev_year--; }

    $next_month = $cal_month + 1;
    $next_year  = $cal_year;
    if ($next_month > 12) { $next_month = 1; $next_year++; }

    $base_params = (is_array($_GET)) ? $_GET : array();
    unset($base_params['cal_month'], $base_params['cal_year']);

    $prev_params = array_merge($base_params, array('cal_month' => $prev_month, 'cal_year' => $prev_year));
    $next_params = array_merge($base_params, array('cal_month' => $next_month, 'cal_year' => $next_year));

    $current_url = get_request_uri();
    $prev_url = $current_url . '?' . http_build_query($prev_params);
    $next_url = $current_url . '?' . http_build_query($next_params);

    // Fetch events for the selected month from the native calendar_events table.
    // db_items()/db_value() exit on a failed query, so the table is probed with
    // SHOW TABLES (which never fails) before its columns are read.
    $events = array();
    $table_error = false;

    $month_start = sprintf('%04d-%02d-01', $cal_year, $cal_month);
    $month_end   = date('Y-m-t', mktime(0, 0, 0, $cal_month, 1, $cal_year));

    if (db_value("SHOW TABLES LIKE 'calendar_events'") !== null) {
        // Event detail links point to the calendar event view page that a calendar
        // view page is tied to. The widget has no page context, so the first
        // configured one is used; links are omitted when none is configured.
        $event_page_name = (string)db_value(
            "SELECT p.page_name
             FROM calendar_view_pages cvp
             INNER JOIN page p ON p.page_id = cvp.calendar_event_view_page_id
             WHERE cvp.calendar_event_view_page_id > 0
             ORDER BY cvp.id ASC
             LIMIT 1"
        );

        $event_rows = db_items(
            "SELECT ce.id, ce.name, ce.start_time, ce.all_day, ce.short_description
             FROM calendar_events ce
             WHERE ce.published = 1
               AND ce.start_time >= '" . e($month_start) . " 00:00:00'
               AND ce.start_time <= '" . e($month_end) . " 23:59:59'
             ORDER BY ce.start_time ASC
             LIMIT " . $cal_events_limit
        );
        $output_base = defined('OUTPUT_PATH') ? OUTPUT_PATH : '/';
        foreach ($event_rows as $r) {
            $event_start_ts = strtotime((string)$r['start_time']);
            if ($event_start_ts && $event_start_ts > 0) {
                if ($cal_date_format === 'd M Y') {
                    $event_date = date('d', $event_start_ts) . ' ' . pg_widget_month_name((int)date('n', $event_start_ts)) . ' ' . date('Y', $event_start_ts);
                } else {
                    $event_date = date($cal_date_format, $event_start_ts);
                }
                $event_time = ((int)$r['all_day'] === 0) ? date('H:i', $event_start_ts) : '';
            } else {
                $event_date = substr((string)$r['start_time'], 0, 10);
                $event_time = '';
            }
            $event_url = ($event_page_name !== '')
                ? $output_base . encode_url_path($event_page_name) . '?id=' . (int)$r['id']
                : '';
            $events[] = array(
                'title'   => (string)$r['name'],
                'date'    => $event_date,
                'time'    => $event_time,
                'url'     => $event_url,
                'excerpt' => (string)$r['short_description'],
            );
        }
    } else {
        $table_error = true;
    }

    // Build loop output
    $loop_rendered = '';
    if ($loop_template !== '' && count($events) > 0) {
        foreach ($events as $idx => $evt) {
            $row_values = array(
                '__event_title'   => h($evt['title']),
                '__event_date'    => h($evt['date']),
                '__event_time'    => h($evt['time']),
                '__event_url'     => h($evt['url']),
                '__event_excerpt' => h($evt['excerpt']),
            );
            $row_html = $loop_template;
            $rkeys = array_keys($row_values);
            usort($rkeys, function ($a, $b) { return strlen($b) - strlen($a); });
            foreach ($rkeys as $k) {
                $row_html = str_replace('^^' . $k . '^^', $row_values[$k], $row_html);
            }
            $row_html = preg_replace('/\^\^[A-Za-z0-9_]+\^\^/', '', $row_html);

            // Uniquify Bootstrap component IDs per event row
            $iter_suffix = '_pgw' . $widget_id . 'r' . $idx;
            $row_html = preg_replace('/\bid="([^"]+)"/',            'id="$1'              . $iter_suffix . '"', $row_html);
            $row_html = preg_replace('/data-bs-target="#([^"]+)"/', 'data-bs-target="#$1' . $iter_suffix . '"', $row_html);
            $row_html = preg_replace('/aria-controls="([^"]+)"/',   'aria-controls="$1'   . $iter_suffix . '"', $row_html);
            $row_html = preg_replace('/href="#([^"]+)"/',           'href="#$1'           . $iter_suffix . '"', $row_html);

            $loop_rendered .= $row_html;
        }
    }

    if ($table_error) {
        $loop_rendered = '<!-- pg-calendar-view: calendar_events table not found. Create the table or use a form-based approach. -->';
    }

    // Apply static tokens
    $static_values = array(
        '__month_name'      => h($month_name),
        '__year'            => h((string)$cal_year),
        '__prev_month_url'  => h($prev_url),
        '__next_month_url'  => h($next_url),
        '__no_events'       => (count($events) === 0 && !$table_error) ? h($cal_no_events_message) : '',
    );

    if ($static_html === '') {
        // No loop_area split — replace tokens in the single rendered template
        $rendered = $loop_template;
        $skeys = array_keys($static_values);
        usort($skeys, function ($a, $b) { return strlen($b) - strlen($a); });
        foreach ($skeys as $k) {
            $rendered = str_replace('^^' . $k . '^^', $static_values[$k], $rendered);
        }
        return preg_replace('/\^\^[A-Za-z0-9_]+\^\^/', '', $rendered);
    }

    $skeys = array_keys($static_values);
    usort($skeys, function ($a, $b) { return strlen($b) - strlen($a); });
    foreach ($skeys as $k) {
        $static_html = str_replace('^^' . $k . '^^', $static_values[$k], $static_html);
    }
    $static_html = preg_replace('/\^\^[A-Za-z0-9_]+\^\^/', '', $static_html);

    return str_replace('<!--pg-loop-slot-->', $loop_rendered, $static_html);
}
