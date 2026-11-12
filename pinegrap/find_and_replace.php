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
 *              2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

ini_set('max_execution_time', '9999');
include('init.php');
$user = validate_user();
validate_area_access($user, 'designer');

include_once('liveform.class.php');
$liveform = new liveform('find_and_replace');

// ── Table definitions ────────────────────────────────────────────────────────
// edit_url  : {column_name} placeholders are filled from the item values
// extra_join: a JOIN added to the search query (optional)
// url_raw   : true leaves out the OUTPUT_SOFTWARE_DIRECTORY prefix (site URL)
// touch     : the last-modified time and user columns a replace updates
// max_length: column => characters, for columns shorter than LONGTEXT
$all_table_defs = [

    'style' => [
        'label'        => lang('Page Styles'),
        'table'        => 'style',
        'id_column'    => 'style_id',
        'name_column'  => 'style_name',
        'extra_select' => [],
        'extra_join'   => '',
        'columns'      => ['style_code'],
        'column_labels' => ['style_code' => lang('Code')],
        'edit_url'     => 'edit_custom_style.php?id={style_id}',
        'url_raw'      => false,
        'touch'        => ['style_timestamp', 'style_user'],
    ],

    'pregion' => [
        'label'        => lang('Page Regions'),
        'table'        => 'pregion',
        'id_column'    => 'pregion_id',
        'name_column'  => 'pregion_name',
        // JOIN with the page table → the frontend URL and the row's name
        // come from page_name. A row whose page is gone is not searched.
        'extra_select' => ['page.page_name AS pregion_page_name'],
        'extra_join'   => 'INNER JOIN page ON pregion.pregion_page = page.page_id',
        // A page whose content moved into its designer tree no longer reads
        // its rows; a page in the Recycle Bin (or a folder carried into it)
        // is neither listed nor changed, as in mass_edit.php.
        'base_where'   => pg_page_region_read_sql() . pg_pages_bin_sql(pg_recycle_bin_folder_ids(), false),
        'columns'      => ['pregion_content'],
        'column_labels' => ['pregion_content' => lang('Content')],
        // The region's own name is an internal id; the page names the row.
        'display_name_column' => 'pregion_page_name',
        // The page the region belongs to is marked for SEO rescoring.
        'seo_stale_page' => 'pregion_page',
        'touch'        => ['pregion_timestamp', 'pregion_user'],
        // The page's frontend URL — edited from the page itself
        'edit_url'     => '{pregion_page_name}',
        'url_raw'      => true,   // OUTPUT_PATH . page_name, without the software directory
    ],

    'cregion_common' => [
        'label'        => lang('Common Regions'),
        'table'        => 'cregion',
        'id_column'    => 'cregion_id',
        'name_column'  => 'cregion_name',
        'extra_select' => [],
        'extra_join'   => '',
        // A region made a shared component draws the component; its row
        // is no longer read.
        'base_where'   => "cregion_designer_type = 'no' AND " . pg_common_region_not_converted_sql(),
        'columns'      => ['cregion_content'],
        'column_labels' => ['cregion_content' => lang('Content')],
        'edit_url'     => 'edit_common_region.php?id={cregion_id}',
        'url_raw'      => false,
        'touch'        => ['cregion_timestamp', 'cregion_user'],
    ],

    'cregion_designer' => [
        'label'        => lang('Designer Regions'),
        'table'        => 'cregion',
        'id_column'    => 'cregion_id',
        'name_column'  => 'cregion_name',
        'extra_select' => [],
        'extra_join'   => '',
        'base_where'   => "cregion_designer_type = 'yes'",
        'columns'      => ['cregion_content'],
        'column_labels' => ['cregion_content' => lang('Content')],
        'edit_url'     => 'edit_designer_region.php?id={cregion_id}',
        'url_raw'      => false,
        'touch'        => ['cregion_timestamp', 'cregion_user'],
    ],

    'dregion' => [
        'label'        => lang('Dynamic Regions'),
        'table'        => 'dregion',
        'id_column'    => 'dregion_id',
        'name_column'  => 'dregion_name',
        'extra_select' => [],
        'extra_join'   => '',
        'columns'      => ['dregion_code'],
        'column_labels' => ['dregion_code' => lang('Code')],
        'edit_url'     => 'edit_dynamic_region.php?id={dregion_id}',
        'url_raw'      => false,
        'touch'        => ['dregion_timestamp', 'dregion_user'],
    ],

];

// Dynamic region code is PHP that runs on the server, and its add and edit
// screens are administrator-only; a designer does not get a third way to
// write it from here.
if ($user['role'] > 0) {
    unset($all_table_defs['dregion']);
}

// Add the product tables when e-commerce is enabled
if (defined('ECOMMERCE') && ECOMMERCE === true) {
    $all_table_defs['products'] = [
        'label'        => lang('Products'),
        'table'        => 'products',
        'id_column'    => 'id',
        'name_column'  => 'name',
        'extra_select' => [],
        'extra_join'   => '',
        // A row in the Recycle Bin opens read-only; it is not changed here.
        'base_where'   => '1' . pg_catalog_bin_live_sql('products'),
        'columns'      => ['short_description', 'full_description', 'details'],
        'column_labels' => [
            'short_description' => lang('Short Description'),
            'full_description'  => lang('Full Description'),
            'details'           => lang('Details'),
        ],
        'edit_url'     => 'edit_product.php?id={id}',
        'url_raw'      => false,
        'touch'        => ['timestamp', 'user'],
        // VARCHAR(100), as the edit form's maxlength.
        'max_length'   => ['short_description' => 100],
        // The row carries its own SEO score, marked stale in the same UPDATE.
        'seo_stale'    => 'row',
    ];

    $all_table_defs['product_groups'] = [
        'label'        => lang('Product Groups'),
        'table'        => 'product_groups',
        'id_column'    => 'id',
        'name_column'  => 'name',
        'extra_select' => [],
        'extra_join'   => '',
        // A row in the Recycle Bin opens read-only; it is not changed here.
        'base_where'   => '1' . pg_catalog_bin_live_sql('product_groups'),
        'columns'      => ['short_description', 'full_description', 'details'],
        'column_labels' => [
            'short_description' => lang('Short Description'),
            'full_description'  => lang('Full Description'),
            'details'           => lang('Details'),
        ],
        'edit_url'     => 'edit_product_group.php?id={id}',
        'url_raw'      => false,
        'touch'        => ['timestamp', 'user'],
        // VARCHAR(100), as the edit form's maxlength.
        'max_length'   => ['short_description' => 100],
        // The row carries its own SEO score, marked stale in the same UPDATE.
        'seo_stale'    => 'row',
    ];
}

$all_table_defs['form_data'] = [
    'label'        => lang('Submitted Forms'),
    'table'        => 'form_data',
    'id_column'    => 'id',
    'name_column'  => 'name',
    // form_id → forms.id (what edit_submitted_form.php expects)
    'extra_select' => ['form_data.form_id'],
    'extra_join'   => '',
    'columns'      => ['data'],
    // Each field is a row: the Field column shows the field's name, and the
    // rows of one submission are one record named by its form.
    'column_label_from' => 'name',
    'record_key'   => 'form_id',
    'edit_url'     => 'edit_submitted_form.php?id={form_id}',
    'url_raw'      => false,
];

$all_table_defs['comments'] = [
    'label'        => lang('Comments'),
    'table'        => 'comments',
    'id_column'    => 'id',
    'name_column'  => 'name',
    'extra_select' => [],
    'extra_join'   => '',
    'columns'      => ['message'],
    'column_labels' => ['message' => lang('Message')],
    'edit_url'     => 'edit_comment.php?id={id}',
    'url_raw'      => false,
    'touch'        => ['last_modified_timestamp', 'last_modified_user_id'],
];

// Add when ads are enabled
if (defined('ADS') && ADS === true) {
    $all_table_defs['ads'] = [
        'label'        => lang('Ads'),
        'table'        => 'ads',
        'id_column'    => 'id',
        'name_column'  => 'name',
        'extra_select' => [],
        'extra_join'   => '',
        'columns'      => ['content'],
        'column_labels' => ['content' => lang('Content')],
        'edit_url'     => 'edit_ad.php?id={id}',
        'url_raw'      => false,
        'touch'        => ['last_modified_timestamp', 'last_modified_user_id'],
    ];
}

// Visual designer pages and shared components: the words of their trees
// (pg_tree_search_texts()), where a converted page or common region keeps
// what its region rows held. A replace writes the tree as a designer save
// would (pg_tree_search_save_page(), pg_tree_search_save_component()).
if (pg_multi_page_design_ready()) {
    $all_table_defs['page_tree'] = [
        'label'        => lang('Designer Pages'),
        'table'        => 'page',
        'id_column'    => 'page_id',
        'name_column'  => 'page_name',
        'extra_select' => [],
        'extra_join'   => '',
        'base_where'   => "(page.page_tree_json IS NOT NULL) AND (page.page_tree_json <> '')" . pg_pages_bin_sql(pg_recycle_bin_folder_ids(), false),
        'columns'      => ['page_tree_json'],
        'column_labels' => ['page_tree_json' => lang('Content')],
        'tree'         => 'page',
        'edit_url'     => '{page_name}',
        'url_raw'      => true,
    ];
}

// System widgets are left out: their words come from their settings.
if (pg_schema_has('shared_components', 'system_region_config')) {
    $all_table_defs['shared_component'] = [
        'label'        => lang('Shared Components'),
        'table'        => 'shared_components',
        'id_column'    => 'id',
        'name_column'  => 'name',
        'extra_select' => [],
        'extra_join'   => '',
        'base_where'   => 'system_region_config IS NULL',
        'columns'      => ['tree_json'],
        'column_labels' => ['tree_json' => lang('Content')],
        'tree'         => 'component',
        'edit_url'     => 'edit_system_style.php?component={id}',
        'url_raw'      => false,
    ];
}

// ── Match context (context snippet) ──────────────────────────────────────────
function find_match_context($text, $find, $case_sensitive, $is_regex, $context_len = 80) {
    if ($is_regex) {
        if (!@preg_match(pg_find_replace_regex($find, $case_sensitive), $text, $m)) return '';
        $matched = $m[0];
        $pos     = $case_sensitive ? mb_strpos($text, $matched) : mb_stripos($text, $matched);
        if ($pos === false) return '';
    } elseif ($case_sensitive) {
        $pos = mb_strpos($text, $find);
        if ($pos === false) return '';
        $matched = $find;
    } else {
        // The same matcher as the count and the replace (pg_search_ci_pattern()).
        if (!@preg_match(pg_search_ci_pattern($find), $text, $m, PREG_OFFSET_CAPTURE)) return '';
        $matched = $m[0][0];
        $pos     = mb_strlen(substr($text, 0, $m[0][1]));
    }

    $start     = max(0, $pos - $context_len);
    $text_len  = mb_strlen($text);
    $match_len = mb_strlen($matched);

    return ($start > 0 ? '<span class="fg-secondary">…</span>' : '')
        . h(mb_substr($text, $start, $pos - $start))
        . '<mark class="px-0">' . h($matched) . '</mark>'
        . h(mb_substr($text, $pos + $match_len, $context_len))
        . (pg_find_replace_snippet_cut($pos, $match_len, $context_len, $text_len) ? '<span class="fg-secondary">…</span>' : '');
}

// ── Edit URL builder ──────────────────────────────────────────────────────────
// url_raw=true  → OUTPUT_PATH . page_name  (frontend page)
// url_raw=false → OUTPUT_PATH . SOFTWARE_DIRECTORY . '/' . url (backend page)
function build_find_edit_url($pattern, $item, $url_raw = false) {
    if (!$pattern) return '';
    $url = $pattern;
    foreach ($item as $col => $val) {
        $url = str_replace('{' . $col . '}', $url_raw ? $val : urlencode($val), $url);
    }
    return $url_raw
        ? OUTPUT_PATH . $url
        : OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/' . $url;
}

// ── WHERE clause builder ──────────────────────────────────────────────────────
// The filter only fetches candidate rows; the PHP count decides which rows
// match. A regular expression is matched in the column's own (case-
// insensitive) collation even when Case-sensitive is on: MySQL 8 refuses a
// binary regular expression on utf8mb4 text, and the case-sensitive PCRE
// count then drops the rows that differ in letter case.
function build_find_where($columns, $find, $case_sensitive, $is_regex) {
    $parts  = [];
    $params = [];
    foreach ($columns as $col) {
        if ($is_regex) {
            $parts[]  = "($col REGEXP ?)";
            $params[] = (string) $find;
        } elseif ($case_sensitive) {
            $parts[]  = "($col LIKE BINARY ?)";
            $params[] = '%' . escape_like($find) . '%';
        } else {
            $parts[]  = "(LOWER($col) LIKE ?)";
            $params[] = '%' . pg_search_like_term($find) . '%';
        }
    }
    return [implode(' OR ', $parts), $params];
}

// The rows a search fetches, or false when the database refused the query
// (a regular expression PCRE accepts and the database's engine does not,
// such as "[[:<:]]" on MySQL 8).
function find_replace_rows($def, $sql_select, $find, $case_sensitive, $is_regex) {
    $sql_join       = !empty($def['extra_join']) ? ' ' . $def['extra_join'] : '';
    $sql_base_where = !empty($def['base_where'] ?? '') ? '(' . $def['base_where'] . ') AND ' : '';
    list($sql_where, $params) = build_find_where($def['columns'], $find, $case_sensitive, $is_regex);
    // A tree is JSON: a term it would escape, or a regular expression, reads
    // every tree and the count over its texts decides.
    if (!empty($def['tree']) && !pg_tree_search_like_safe($find, $is_regex)) {
        list($sql_where, $params) = ['1', []];
    }

    $result = db_q("SELECT $sql_select FROM {$def['table']}$sql_join WHERE $sql_base_where($sql_where)", $params);
    if ($result === false) {
        if (!$is_regex) {
            output_error(lang('Query failed.'));
        }
        return false;
    }

    $items = [];
    while ($item = mysqli_fetch_assoc($result)) {
        $items[] = $item;
    }
    return $items;
}

// The texts of a record a search reads, each as array(field label, text):
// its columns, or the words of a designer tree's nodes.
function find_replace_item_texts($def, $item) {
    $texts = [];
    foreach ($def['columns'] as $col) {
        $label = !empty($def['column_label_from'])
            ? (string) $item[$def['column_label_from']]
            : ($def['column_labels'][$col] ?? $col);
        if (empty($def['tree'])) {
            $texts[] = [$label, (string) $item[$col]];
            continue;
        }
        foreach (pg_tree_search_texts(json_decode((string) $item[$col], true)) as $text) {
            $texts[] = [$label, $text['value']];
        }
    }
    return $texts;
}

// One text with the search replaced: array(new text, replacements), or
// null when PCRE fails on it.
function find_replace_value($text, $find, $replace, $case_sensitive, $is_regex) {
    $count = 0;
    if ($is_regex) {
        $new = @preg_replace(pg_find_replace_regex($find, $case_sensitive), $replace, (string) $text, -1, $count);
        if ($new === null) return null;
    } elseif ($case_sensitive) {
        $new = str_replace($find, $replace, (string) $text, $count);
    } else {
        $new = pg_search_ci_replace($find, $replace, $text, $count);
    }
    return [$new, $count];
}

// How many times the search matches a text; -1 when a regular expression
// matches empty text there ("\b", a lookahead), which a replace would fill.
function find_replace_count($text, $find, $case_sensitive, $is_regex) {
    if ($is_regex) {
        $pattern = pg_find_replace_regex($find, $case_sensitive);
        if (pg_find_replace_has_empty_match($pattern, $text)) return -1;
        return (int) @preg_match_all($pattern, (string) $text);
    }
    if ($case_sensitive) {
        return substr_count((string) $text, $find);
    }
    return pg_search_ci_count($text, $find);
}

// The hidden copy of the search a form posts again: textareas, so line
// breaks reach the server as the typed ones did.
function find_replace_hidden_fields($find, $replace, $case_sensitive, $use_regex, $scope) {
    $html = '<textarea name="find" class="d-none" aria-hidden="true" tabindex="-1">' . "\n" . h($find) . '</textarea>'
        . '<textarea name="replace" class="d-none" aria-hidden="true" tabindex="-1">' . "\n" . h($replace) . '</textarea>';
    if ($case_sensitive) $html .= '<input type="hidden" name="case_sensitive" value="1">';
    if ($use_regex) $html .= '<input type="hidden" name="use_regex" value="1">';
    foreach ($scope as $key) {
        $html .= '<input type="hidden" name="scope[]" value="' . h($key) . '">';
    }
    return $html;
}

// A submitted form record is named by its form and reference code, read in
// one query for the submissions listed.
function find_replace_name_submissions($records) {
    $ids = [];
    foreach ($records as $record) {
        $ids[] = (int) $record['key'];
    }
    if (!$ids) return $records;

    $names = [];
    $rows  = db_items(
        "SELECT forms.id, forms.reference_code, custom_form_pages.form_name
        FROM forms
        LEFT JOIN custom_form_pages ON forms.page_id = custom_form_pages.page_id
        WHERE forms.id IN (" . implode(',', array_unique($ids)) . ")"
    );
    foreach ($rows as $row) {
        $names[(int) $row['id']] = trim($row['form_name'] . ' ' . $row['reference_code']);
    }

    foreach ($records as $i => $record) {
        if (($names[(int) $record['key']] ?? '') !== '') {
            $records[$i]['name'] = $names[(int) $record['key']];
        }
    }
    return $records;
}

// The replace cannot be undone; both of its buttons ask first.
$replace_confirm = lang('Replace every match in the selected types of items? This cannot be undone.');

// ── GET: show the form ───────────────────────────────────────────────────────
if (!$_POST) {

    // Every box is ticked on a first draw only; a refused search comes back
    // with exactly the boxes the user ticked.
    $scope_defaults = $liveform->prefill() ? ['checked' => 'checked'] : [];
    $scope_html = '';
    foreach ($all_table_defs as $key => $def) {
        $scope_html .= '
        <div class="form-field d-inline-grid me-5 mb-3">
            ' . $liveform->output_field($scope_defaults + [
                'type'  => 'checkbox',
                'id'    => 'scope_' . $key,
                'name'  => 'scope[]',
                'value' => $key,
                'class' => 'check',
            ]) . '
            <label class="" for="scope_' . $key . '">' . h($def['label']) . '</label>
        </div>';
    }

    // The warning names only the types of items this user's scope has.
    $scope_labels = [];
    foreach ($all_table_defs as $def) {
        $scope_labels[] = $def['label'];
    }

    echo
    pg_page_shell([
        'title'        => lang('Find & Replace'),
        'extra classes'=> 'setting',
        'icon'         => 'setting',
        'heading'      => lang('Find & Replace'),
        'heading_description' => lang('Enter the text that you want to mass-find and replace throughout many types of items in the system.'),
    ]) . '
<main id="content" class="container-fluid">
    ' . $liveform->output_errors() . '
    ' . $liveform->output_notices() . '
    <form method="post">
        ' . get_token_field() . '
        <div class="row">
            <div class="col-12 lg:col-8 2xl:col-9 order-1">
                <div class="row">
                    <div class="col-12 xl:col-6">
                        <div class="card mb-7">
                            <h2 class="card-header bg-reset border-0 text-uppercase h5 fg-primary fw-bold">
                                <label for="find" class="mb-0">' . lang('Find') . '</label>
                            </h2>
                            <div class="card-body pt-0">
                                ' . $liveform->output_field([
                                    'type'  => 'textarea',
                                    'id'    => 'find',
                                    'name'  => 'find',
                                    'rows'  => '10',
                                    'class' => 'form-control font-monospace',
                                    'required' => 'required',
                                ]) . '
                            </div>
                        </div>
                    </div>
                    <div class="col-12 xl:col-6">
                        <div class="card mb-7">
                            <h2 class="card-header bg-reset border-0 text-uppercase h5 fg-primary fw-bold">
                                <label for="replace" class="mb-0">' . lang('Replace') . '</label>
                            </h2>
                            <div class="card-body pt-0">
                                ' . $liveform->output_field([
                                    'type'  => 'textarea',
                                    'id'    => 'replace',
                                    'name'  => 'replace',
                                    'rows'  => '10',
                                    'class' => 'form-control font-monospace',
                                    'aria-describedby' => 'replace_help',
                                ]) . '
                                <div class="form-text fg-secondary small mt-3" id="replace_help">' . lang('Leave empty to delete found text. Back-references ($1, $2) are supported when regular expressions are on.') . '</div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card mb-7">
                    <button type="button" class="card-header bg-reset fg-reset border-0 w-100 text-start d-flex justify-content-between align-items-center"
                        data-bs-toggle="collapse" data-bs-target="#scope_panel" aria-controls="scope_panel" aria-expanded="true">
                        <span class="text-uppercase h5 fg-primary fw-bold mb-0">' . lang('Scope') . '</span>
                        <span class="material-icons" aria-hidden="true">expand_more</span>
                    </button>
                    <div class="collapse show" id="scope_panel">
                        <div class="card-body pt-0">
                            <div class="mb-5">
                                <button type="button"
                                    onclick="document.querySelectorAll(\'#scope_checkboxes input\').forEach(c=>c.checked=true)"
                                    class="btn btn-sm btn-outline theme-secondary me-1">' . lang('Select all') . '</button>
                                <button type="button"
                                    onclick="document.querySelectorAll(\'#scope_checkboxes input\').forEach(c=>c.checked=false)"
                                    class="btn btn-sm btn-outline theme-secondary">' . lang('Deselect all') . '</button>
                            </div>
                            <div id="scope_checkboxes">
                                ' . $scope_html . '
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-12 lg:col-4 2xl:col-3 order-2">
                <!-- 3.3rem: the fixed control panel header covers the top of the window. -->
                <div class="position-sticky" style="top:3.3rem;">
                    <div class="card mb-7">
                        <h2 class="card-header bg-reset border-0 text-uppercase h5 fg-primary fw-bold">
                            ' . lang('Options') . '
                        </h2>
                        <div class="card-body pt-0">
                            <div class="form-field mb-3">
                                <div class="switch">' . $liveform->output_field([
                                    'type'  => 'checkbox',
                                    'id'    => 'case_sensitive',
                                    'name'  => 'case_sensitive',
                                    'value' => '1',
                                    'class' => 'no-bs',
                                ]) . '</div>
                                <label class="" for="case_sensitive">' . lang('Case sensitive') . '</label>
                            </div>
                            <div class="form-field">
                                <div class="switch">' . $liveform->output_field([
                                    'type'  => 'checkbox',
                                    'id'    => 'use_regex',
                                    'name'  => 'use_regex',
                                    'value' => '1',
                                    'class' => 'no-bs',
                                ]) . '</div>
                                <label class="" for="use_regex">' . lang('Use regular expression') . '</label>
                            </div>
                        </div>
                    </div>
                    <div class="alert theme-warning mb-7">
                        <h2 class="h5">' . lang('Warning') . '</h2>
                        <p>' . lang('This feature can be dangerous. You can accidentally lose a large amount of content. Make sure to have someone create a backup of your database first. Use at your own risk.') . '</p>
                        <p class="mb-0">' . h(lang(['string' => 'This screen changes these types of items: {var:1}.', 'vars' => implode(', ', $scope_labels)])) . '</p>
                    </div>
                </div>
            </div>
            <div class="col-12 order-3 position-sticky" style="bottom:0; z-index:3;">
                <nav class="buttons navigation text-center mb-7" aria-label="' . h(lang('data edit buttons')) . '">
                    <div class="container">
                        <div class="btn-group flex-wrap justify-content-center">
                            <button type="submit" name="submit_button" value="Find"
                                class="btn my-1 btn-outline theme-primary">
                                <span class="material-icons me-3" aria-hidden="true">search</span>
                                <span class="pg-btn-label">' . lang('Find') . '</span>
                            </button>
                            <button type="submit" name="submit_button" value="Find &amp; Replace"
                                class="btn my-1 btn-solid theme-success" data-confirm-content="' . h($replace_confirm) . '">
                                <span class="material-icons me-3" aria-hidden="true">find_replace</span>
                                <span class="pg-btn-label">' . lang(array('string'=>'Find & Replace')) . '</span>
                            </button>
                        </div>
                    </div>
                </nav>
            </div>
        </div>
    </form>
</main>' .
    output_footer();

    $liveform->remove_form();

// ── POST: process ────────────────────────────────────────────────────────────
} else {
    validate_token_field();
    $liveform->add_fields_to_session(array('trim' => false));
    $liveform->validate_required_field('find', lang(array('string' => '{var:1} is required', 'vars' => lang('Find'))));

    if ($liveform->check_form_errors() == true) {
        go($_SERVER['PHP_SELF']);
    }

    $find           = $liveform->get_field_value('find');
    $replace        = $liveform->get_field_value('replace');
    $case_sensitive = $liveform->get_field_value('case_sensitive');
    $use_regex      = $liveform->get_field_value('use_regex');
    $action         = isset($_POST['submit_button']) ? $_POST['submit_button'] : 'Find & Replace';
    // No box ticked is no table, never every table.
    $selected_scope = pg_find_replace_scope($_POST['scope'] ?? null, $all_table_defs);

    if (!is_string($find) || !is_string($replace)) {
        $liveform->mark_error('find', lang('Invalid request.'));
        go($_SERVER['PHP_SELF']);
    }

    if (!$selected_scope) {
        $liveform->mark_error('scope', lang('Select at least one type of item in Scope.'));
        go($_SERVER['PHP_SELF']);
    }

    // A pattern PCRE cannot compile, or one that matches empty text and so
    // every row and every gap between two characters, is refused.
    if ($use_regex) {
        $regex_error = pg_find_replace_regex_error($find, $case_sensitive);
        if ($regex_error === 'invalid') {
            $liveform->mark_error('find', lang('Invalid regular expression.'));
            go($_SERVER['PHP_SELF']);
        }
        if ($regex_error === 'empty') {
            $liveform->mark_error('find', lang('A regular expression that matches empty text cannot be used.'));
            go($_SERVER['PHP_SELF']);
        }
    }

    // Filter the table list by the selected scope
    $tables = array_intersect_key($all_table_defs, array_flip($selected_scope));

    // ────────────────────────────────────────────────────────────────────────
    // FIND ONLY
    // ────────────────────────────────────────────────────────────────────────
    if ($action === 'Find') {

        $find_results  = [];
        $total_matches = 0;
        $total_records = 0;

        foreach ($tables as $key => $def) {

            // Build the SELECT: id + name + extra + the columns to search
            $select_cols = array_merge(
                [$def['id_column'], $def['name_column']],
                $def['extra_select'],
                $def['columns']
            );
            $sql_select = implode(', ', array_unique($select_cols));

            $items = find_replace_rows($def, $sql_select, $find, $case_sensitive, $use_regex);
            if ($items === false) {
                $liveform->mark_error('find', lang('Invalid regular expression.'));
                go($_SERVER['PHP_SELF']);
            }

            if (!$items) continue;

            $group_results = [];

            foreach ($items as $item) {
                $col_matches = [];

                foreach (find_replace_item_texts($def, $item) as list($field, $text)) {
                    $cnt = find_replace_count($text, $find, $case_sensitive, $use_regex);
                    if ($cnt < 0) {
                        $liveform->mark_error('find', lang('A regular expression that matches empty text cannot be used.'));
                        go($_SERVER['PHP_SELF']);
                    }

                    if ($cnt > 0) {
                        $snippet = find_match_context($text, $find, $case_sensitive, (bool)$use_regex);
                        $col_matches[] = [
                            'field'   => $field,
                            'count'   => $cnt,
                            'snippet' => $snippet,
                        ];
                        $total_matches += $cnt;
                    }
                }

                if ($col_matches) {
                    $name = $item[$def['name_column']];
                    if (!empty($def['display_name_column']) && ((string) $item[$def['display_name_column']] !== '')) {
                        $name = $item[$def['display_name_column']];
                    }
                    $group_results[] = [
                        'key'     => !empty($def['record_key']) ? $item[$def['record_key']] : $item[$def['id_column']],
                        'name'    => $name,
                        'url'     => build_find_edit_url($def['edit_url'], $item, $def['url_raw'] ?? false),
                        'matches' => $col_matches,
                    ];
                }
            }

            if (!empty($def['record_key'])) {
                $group_results = pg_find_replace_merge_records($group_results);
                if ($def['table'] === 'form_data') {
                    $group_results = find_replace_name_submissions($group_results);
                }
            }
            $total_records += count($group_results);

            if ($group_results) {
                $find_results[$key] = [
                    'label'   => $def['label'],
                    'results' => $group_results,
                ];
            }
        }

        // No matches found
        if (!$total_matches) {
            $liveform->mark_error('find', lang('Sorry, no matches were found. Please try entering different text to find.'));
            go($_SERVER['PHP_SELF']);
        }

        // Build the result HTML. Each type lists its first records only: a
        // one-letter search can match thousands of rows, and the page would
        // be megabytes of HTML.
        $find_results_limit = 200;
        $results_html = '';
        foreach ($find_results as $group) {
            $rows = '';
            foreach (array_slice($group['results'], 0, $find_results_limit) as $r) {
                $name_cell = $r['url']
                    ? '<a href="' . h($r['url']) . '" target="_blank" rel="noopener" class="fw-semibold text-decoration-none">'
                        . h($r['name'])
                        . ' <span class="material-icons align-middle" style="font-size:.85rem" aria-hidden="true">open_in_new</span></a>'
                    : '<span class="fw-semibold">' . h($r['name']) . '</span>';

                foreach ($r['matches'] as $m) {
                    $rows .= '
                    <tr>
                        <td class="align-top text-break">' . $name_cell . '</td>
                        <td class="align-top fg-secondary small">' . h($m['field']) . '</td>
                        <td class="align-top text-center"><span class="badge theme-secondary">' . $m['count'] . '</span></td>
                        <td class="align-top small font-monospace lh-lg text-break">' . $m['snippet'] . '</td>
                    </tr>';
                }
            }

            $results_html .= '
            <div class="card mb-7">
                <div class="card-header bg-reset border-0 text-uppercase h6 fg-primary fw-bold d-flex align-items-center gap-3">
                    ' . h($group['label']) . '
                    <span class="badge theme-primary fw-normal">' . h(lang(array(
                        'string' => count($group['results']) === 1 ? '{var:1} record' : '{var:1} records',
                        'vars'   => pg_format_number(count($group['results']), 0),
                    ))) . '</span>
                </div>
                <div class="card-body p-0 table-responsive">
                    <table class="table table-hover table-sm pg-find-results">
                        <thead class="pg-table-light">
                            <tr>
                                <th>' . lang('Name') . '</th>
                                <th>' . lang('Field') . '</th>
                                <th class="text-center">' . lang('Count') . '</th>
                                <th>' . lang('Context') . '</th>
                            </tr>
                        </thead>
                        <tbody>' . $rows . '</tbody>
                    </table>
                </div>
                ' . (count($group['results']) > $find_results_limit
                    ? '<div class="card-footer bg-reset fg-secondary small">' . h(lang(array(
                        'string' => 'The first {var:1} records are listed; {var:2} more are not.',
                        'vars'   => array(pg_format_number($find_results_limit, 0), pg_format_number(count($group['results']) - $find_results_limit, 0)),
                    ))) . '</div>'
                    : '') . '
            </div>';
        }

        // Summary
        $summary = lang(array(
            'string' => '{var:1} matches found in {var:2} records',
            'vars'   => array(pg_format_number($total_matches, 0), pg_format_number($total_records, 0)),
        ));

        echo
        pg_page_shell([
            'title'        => lang('Find Results'),
            'extra classes'=> 'setting',
            'icon'         => 'setting',
            'heading'      => lang('Find Results'),
            'heading_description' => lang('Enter the text that you want to mass-find and replace throughout many types of items in the system.'),
            'cancel'       => array('enable' => 'true', 'url' => 'find_and_replace.php', 'title' => lang('Find & Replace')),
            'breadcrumb'   => array(
                array('label' => lang('Find & Replace'), 'url' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/find_and_replace.php'),
                array('label' => lang('Find Results')),
            ),
        ]) . '
        <main id="content" class="container-fluid">
            ' . pg_screen_toolbar(array(
                // The heading description is hidden on narrow screens; the
                // match count is the answer to the search, so it stays here.
                'grow' => '<span class="fg-secondary" role="status">' . h($summary) . '</span>',
            ), array('class' => 'mb-5')) . '
            <div class="row">
                <div class="col-12">
                    ' . $results_html . '
                </div>
            </div>
            <form method="post" class="text-center mb-7">
                ' . get_token_field() . '
                ' . find_replace_hidden_fields($find, $replace, $case_sensitive, $use_regex, $selected_scope) . '
                <button type="submit" name="submit_button" value="Find &amp; Replace"
                    class="btn btn-solid theme-success" data-confirm-content="' . h($replace_confirm) . '">
                    <span class="material-icons me-3" aria-hidden="true">find_replace</span>
                    <span class="pg-btn-label">' . lang('Find & Replace') . '</span>
                </button>
            </form>
        </main>' .
        output_footer();

        // The form stays in the session: the back arrow opens it filled in
        // with the same search, and its own draw then clears it.
        exit;

    // ────────────────────────────────────────────────────────────────────────
    // FIND & REPLACE
    // ────────────────────────────────────────────────────────────────────────
    } else {

        $number_of_items        = 0;
        $number_of_replacements = 0;
        $too_long               = [];
        $held                   = [];
        $updates                = [];

        // First every new value is worked out; nothing is written while a
        // value could still refuse the whole replace.
        foreach ($tables as $def) {

            $sql_select     = $def['id_column'] . ', ' . $def['name_column'];
            foreach ($def['columns'] as $col) {
                $sql_select .= ', ' . $col;
            }
            if (!empty($def['seo_stale_page'])) {
                $sql_select .= ', ' . $def['seo_stale_page'];
            }

            $items = find_replace_rows($def, $sql_select, $find, $case_sensitive, $use_regex);
            if ($items === false) {
                $liveform->mark_error('find', lang('Invalid regular expression.'));
                go($_SERVER['PHP_SELF']);
            }

            foreach ($items as $item) {
                $replacement = 0;
                $skip        = false;

                if ($use_regex) {
                    $pattern = pg_find_replace_regex($find, $case_sensitive);
                    foreach (find_replace_item_texts($def, $item) as list(, $text)) {
                        if (pg_find_replace_has_empty_match($pattern, $text)) {
                            $liveform->mark_error('find', lang('A regular expression that matches empty text cannot be used.'));
                            go($_SERVER['PHP_SELF']);
                        }
                    }
                }

                foreach ($def['columns'] as $col) {
                    if (!empty($def['tree'])) {
                        // Only the words of the tree's nodes change; the tree
                        // is written whole.
                        $tree = json_decode((string) $item[$col], true);
                        if (!is_array($tree)) continue;
                        list($tree, $count) = pg_tree_search_replace($tree, function ($text) use ($find, $replace, $case_sensitive, $use_regex) {
                            $done = find_replace_value($text, $find, $replace, $case_sensitive, $use_regex);
                            return ($done === null) ? [$text, 0] : $done;
                        });
                        if ($count > 0) {
                            $item['_tree'] = $tree;
                            $replacement += $count;
                        }
                        continue;
                    }

                    $done = find_replace_value($item[$col], $find, $replace, $case_sensitive, $use_regex);
                    if ($done === null) continue;
                    list($new_val, $count) = $done;

                    if ($count > 0) {
                        // A value longer than its column would be cut without
                        // an error (the session is not strict): the row is
                        // left as it was and named in the notice.
                        if (pg_find_replace_too_long($col, $new_val, $def['max_length'] ?? [])) {
                            $skip = true;
                        }
                        $item[$col]   = $new_val;
                        $replacement += $count;
                    }
                }

                if ($skip) {
                    $too_long[] = $item[$def['name_column']];
                } elseif ($replacement > 0) {
                    $item['_count'] = $replacement;
                    $updates[] = [$def, $item];
                    $number_of_replacements += $replacement;
                }
            }
        }

        foreach ($updates as $update) {
            list($def, $item) = $update;

            // A tree is written as a designer save writes it. A page open in
            // the visual designer is left alone: its editor's next save would
            // write over the replace.
            if (!empty($def['tree'])) {
                $tree_id = (int) $item[$def['id_column']];
                if ($def['tree'] === 'page') {
                    require_once(PG_FUNCTIONS_DIR . '/includes/designer_collab.php');
                    $saved = pg_collab_may_edit_page('', $tree_id)
                        && pg_tree_search_save_page($tree_id, $item['_tree'], (int) $user['id']);
                } else {
                    $saved = pg_tree_search_save_component($tree_id, $item['_tree']);
                }
                if ($saved) {
                    $number_of_items++;
                } else {
                    $held[] = $item[$def['name_column']];
                    $number_of_replacements -= $item['_count'];
                }
                continue;
            }

            $sql_set = '';
            foreach ($def['columns'] as $col) {
                if ($sql_set != '') $sql_set .= ', ';
                $sql_set .= "$col = '" . e($item[$col]) . "'";
            }
            // The SEO score reads these texts and the scheduled SEO
            // job scores only stale rows.
            if (($def['seo_stale'] ?? '') === 'row') {
                $sql_set .= ", seo_analysis_current = '0'";
            }
            // The lists' Last Modified shows which rows the replace changed.
            if (!empty($def['touch'])) {
                $sql_set .= ", {$def['touch'][0]} = UNIX_TIMESTAMP(), {$def['touch'][1]} = '" . (int) $user['id'] . "'";
            }
            // Products are traced in the audit trail; the other tables here are not.
            $audit_before = ($def['table'] === 'products') ? pg_audit_fetch('products', (int) $item[$def['id_column']]) : array();
            db("UPDATE {$def['table']} SET $sql_set WHERE {$def['id_column']} = '" . (int) $item[$def['id_column']] . "'");
            if ($def['table'] === 'products') {
                pg_audit_record('update', 'products', (int) $item[$def['id_column']], $audit_before);
            }
            if (!empty($def['seo_stale_page']) && ((int) $item[$def['seo_stale_page']] > 0)) {
                db_q("UPDATE page SET seo_analysis_current = '0' WHERE page_id = ?", array((int) $item[$def['seo_stale_page']]));
            }
            $number_of_items++;
        }

        if ($too_long) {
            $liveform->add_notice(h(lang('Some values were not saved because they are longer than the field allows') . ': ' . implode(', ', $too_long)));
        }

        if ($held) {
            $liveform->add_notice(h(lang('Some items were not changed because they are open in the Page Design Studio') . ': ' . implode(', ', $held)));
        }

        if ($number_of_replacements == 0) {
            if (!$too_long && !$held) {
                $liveform->mark_error('find', lang('Sorry, no matches were found. Please try entering different text to find.'));
            }
            go($_SERVER['PHP_SELF']);
        }

        $match_plural_suffix = $number_of_replacements == 1 ? '' : 'es';

        $item_plural_suffix = $number_of_items == 1 ? '' : 's';

        $message = lang(array(
            'string' => '{var:1} match{suffix:1} found and replaced in {var:2} item{suffix:2}.',
            'vars'   => array(pg_format_number($number_of_replacements, 0), pg_format_number($number_of_items, 0)),
            'suffix' => array($match_plural_suffix, $item_plural_suffix),
        ));

        log_activity(lang('Find & Replace') . ': ' . $message, $_SESSION['sessionusername']);
        $liveform->add_notice(h($message));
        go($_SERVER['PHP_SELF']);
    }
}
?>
