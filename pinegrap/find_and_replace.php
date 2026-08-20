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
 *              2016–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

ini_set('max_execution_time', '9999');
include('init.php');
$user = validate_user();
validate_area_access($user, 'designer');

include_once('liveform.class.php');
$liveform = new liveform('find_and_replace');

// ── Tablo tanımları ──────────────────────────────────────────────────────────
// edit_url  : {kolon_adı} yer tutucuları item değerleri ile doldurulur
// extra_join: Find sorgusuna eklenen JOIN ifadesi (opsiyonel)
// url_raw   : true ise OUTPUT_SOFTWARE_DIRECTORY prefix eklenmez (tam URL)
$all_table_defs = [

    'style' => [
        'label'        => lang('Page Styles'),
        'table'        => 'style',
        'id_column'    => 'style_id',
        'name_column'  => 'style_name',
        'extra_select' => [],
        'extra_join'   => '',
        'columns'      => ['style_code'],
        'edit_url'     => 'edit_custom_style.php?id={style_id}',
        'url_raw'      => false,
    ],

    'pregion' => [
        'label'        => lang('Page Regions'),
        'table'        => 'pregion',
        'id_column'    => 'pregion_id',
        'name_column'  => 'pregion_name',
        // page tablosuyla JOIN → page_name üzerinden frontend URL elde edilir
        'extra_select' => ['page.page_name AS pregion_page_name'],
        'extra_join'   => 'LEFT JOIN page ON pregion.pregion_page = page.page_id',
        'columns'      => ['pregion_content'],
        // Sayfanın frontend URL'si — kendi içinden düzenlenir
        'edit_url'     => '{pregion_page_name}',
        'url_raw'      => true,   // OUTPUT_PATH . page_name (software dir olmadan)
    ],

    'cregion_common' => [
        'label'        => lang('Common Regions'),
        'table'        => 'cregion',
        'id_column'    => 'cregion_id',
        'name_column'  => 'cregion_name',
        'extra_select' => [],
        'extra_join'   => '',
        'base_where'   => "cregion_designer_type = 'no'",
        'columns'      => ['cregion_content'],
        'edit_url'     => 'edit_common_region.php?id={cregion_id}',
        'url_raw'      => false,
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
        'edit_url'     => 'edit_designer_region.php?id={cregion_id}',
        'url_raw'      => false,
    ],

    'dregion' => [
        'label'        => lang('Dynamic Regions'),
        'table'        => 'dregion',
        'id_column'    => 'dregion_id',
        'name_column'  => 'dregion_name',
        'extra_select' => [],
        'extra_join'   => '',
        'columns'      => ['dregion_code'],
        'edit_url'     => 'edit_dynamic_region.php?id={dregion_id}',
        'url_raw'      => false,
    ],

];

// E-ticaret aktifse ürün tablolarını ekle
if (defined('ECOMMERCE') && ECOMMERCE === true) {
    $all_table_defs['products'] = [
        'label'        => lang('Products'),
        'table'        => 'products',
        'id_column'    => 'id',
        'name_column'  => 'name',
        'extra_select' => [],
        'extra_join'   => '',
        'columns'      => ['short_description', 'full_description', 'details'],
        'edit_url'     => 'edit_product.php?id={id}',
        'url_raw'      => false,
    ];

    $all_table_defs['product_groups'] = [
        'label'        => lang('Product Groups'),
        'table'        => 'product_groups',
        'id_column'    => 'id',
        'name_column'  => 'name',
        'extra_select' => [],
        'extra_join'   => '',
        'columns'      => ['short_description', 'full_description', 'details'],
        'edit_url'     => 'edit_product_group.php?id={id}',
        'url_raw'      => false,
    ];
}

$all_table_defs['form_data'] = [
    'label'        => lang('Submitted Forms'),
    'table'        => 'form_data',
    'id_column'    => 'id',
    'name_column'  => 'name',
    // form_id → forms.id (edit_submitted_form.php bunu bekliyor)
    'extra_select' => ['form_data.form_id'],
    'extra_join'   => '',
    'columns'      => ['data'],
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
    'edit_url'     => 'edit_comment.php?id={id}',
    'url_raw'      => false,
];

// Reklamlar aktifse ekle
if (defined('ADS') && ADS === true) {
    $all_table_defs['ads'] = [
        'label'        => lang('Ads'),
        'table'        => 'ads',
        'id_column'    => 'id',
        'name_column'  => 'name',
        'extra_select' => [],
        'extra_join'   => '',
        'columns'      => ['content'],
        'edit_url'     => 'edit_ad.php?id={id}',
        'url_raw'      => false,
    ];
}

// ── Eşleşme bağlamı (context snippet) ────────────────────────────────────────
function find_match_context($text, $find, $case_sensitive, $is_regex, $context_len = 80) {
    if ($is_regex) {
        $flags = 'u' . ($case_sensitive ? '' : 'i');
        if (!@preg_match('/' . $find . '/' . $flags, $text, $m)) return '';
        $matched = $m[0];
        $pos     = $case_sensitive ? mb_strpos($text, $matched) : mb_stripos($text, $matched);
        if ($pos === false) return '';
    } else {
        $pos = $case_sensitive ? mb_strpos($text, $find) : mb_stripos($text, $find);
        if ($pos === false) return '';
        $matched = mb_substr($text, $pos, mb_strlen($find));
    }

    $start     = max(0, $pos - $context_len);
    $text_len  = mb_strlen($text);
    $match_len = mb_strlen($matched);

    return ($start > 0 ? '<span class="text-secondary">…</span>' : '')
        . h(mb_substr($text, $start, $pos - $start))
        . '<mark class="px-0">' . h($matched) . '</mark>'
        . h(mb_substr($text, $pos + $match_len, $context_len))
        . ($pos + $match_len < $text_len ? '<span class="text-secondary">…</span>' : '');
}

// ── Edit URL builder ──────────────────────────────────────────────────────────
// url_raw=true  → OUTPUT_PATH . page_name  (frontend sayfası)
// url_raw=false → OUTPUT_PATH . SOFTWARE_DIRECTORY . '/' . url (backend sayfası)
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
function build_find_where($columns, $find, $case_sensitive, $is_regex) {
    $parts = [];
    foreach ($columns as $col) {
        if ($is_regex) {
            $parts[] = $case_sensitive
                ? "($col REGEXP BINARY '" . e($find) . "')"
                : "($col REGEXP '"        . e($find) . "')";
        } elseif ($case_sensitive) {
            $parts[] = "($col LIKE BINARY '%" . e(escape_like($find)) . "%')";
        } else {
            $parts[] = "(LOWER($col) LIKE '%" . e(escape_like(mb_strtolower($find))) . "%')";
        }
    }
    return implode(' OR ', $parts);
}

// ── GET: form göster ─────────────────────────────────────────────────────────
if (!$_POST) {

    $scope_html = '';
    foreach ($all_table_defs as $key => $def) {
        $scope_html .= '
        <div class="form-check form-check-inline me-3 mb-2">
            <input class="form-check-input" type="checkbox" id="scope_' . $key . '"
                name="scope[]" value="' . $key . '" checked>
            <label class="form-check-label" for="scope_' . $key . '">' . h($def['label']) . '</label>
        </div>';
    }

    echo
    pg_page_shell([
        'title'        => lang('Find & Replace'),
        'extra classes'=> 'setting',
        'icon'         => 'setting',
        'heading'      => lang('Find & Replace'),
        'breadcrumb'   => [
            ['label' => lang('Settings'), 'url' => 'settings.php'],
            ['label' => lang('Find & Replace')],
        ],
    ]) . '
        <div class="row">
            <div class="col-12">
                ' . $liveform->output_errors() . '
                ' . $liveform->output_notices() . '
                <div class="row mb-2 flex-wrap">
                    <div class="col-12 text-center text-md-start">
                        <h2 class="d-inline-block"
                            data-bs-content="' . lang('Enter the text that you want to mass-find and replace throughout many types of items in the system.') . '"
                            title="' . lang('Find & Replace') . '">'
                            . lang('Find & Replace') . '</h2>
                    </div>
                </div>
                <form method="post">
                    ' . get_token_field() . '
                    <div class="alert alert-warning">
                        <h5>' . lang('Warning') . '</h5>
                        <p class="mb-0">' . lang('This feature can be dangerous. You can accidentally lose a large amount of content. Make sure to have someone create a backup of your database first. Use at your own risk.') . '</p>
                        <p class="mb-0">' . lang('Updates Page Styles, Page Regions, Common Regions, Designer Regions, Dynamic Regions, Products, Product Groups, Submitted Forms, Comments, and Ads.') . '</p>
                    </div>
                    <div class="row">

                        <div class="col-12 col-lg-6">
                            <div class="card my-4">
                                <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">
                                    ' . lang('Find') . '
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-12 my-2">
                                            ' . $liveform->output_field([
                                                'type'  => 'textarea',
                                                'name'  => 'find',
                                                'rows'  => '10',
                                                'class' => 'form-control font-monospace',
                                            ]) . '
                                        </div>
                                        <div class="col-12 my-2">
                                            <div class="form-check form-switch mb-2">
                                                ' . $liveform->output_field([
                                                    'type'  => 'checkbox',
                                                    'id'    => 'case_sensitive',
                                                    'name'  => 'case_sensitive',
                                                    'value' => '1',
                                                    'class' => 'form-check-input',
                                                ]) . '
                                                <label class="form-check-label" for="case_sensitive">' . lang('Case-sensitive') . '</label>
                                            </div>
                                            <div class="form-check form-switch">
                                                ' . $liveform->output_field([
                                                    'type'  => 'checkbox',
                                                    'id'    => 'use_regex',
                                                    'name'  => 'use_regex',
                                                    'value' => '1',
                                                    'class' => 'form-check-input',
                                                ]) . '
                                                <label class="form-check-label" for="use_regex">' . lang('Use regular expression') . '</label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-12 col-lg-6">
                            <div class="card my-4">
                                <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">
                                    ' . lang('Replace') . '
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-12 my-2">
                                            ' . $liveform->output_field([
                                                'type'  => 'textarea',
                                                'name'  => 'replace',
                                                'rows'  => '10',
                                                'class' => 'form-control font-monospace',
                                            ]) . '
                                        </div>
                                        <div class="col-12 my-2">
                                            <div class="form-text text-muted small">' . lang('Leave empty to delete found text. Regex back-references ($1, $2) are supported when regex is enabled.') . '</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-12">
                            <div class="card mb-4">
                                <div class="card-header bg-reset border-0 d-flex justify-content-between align-items-center"
                                    style="cursor:pointer"
                                    data-bs-toggle="collapse" data-bs-target="#scope_panel" aria-expanded="true">
                                    <span class="text-uppercase h6 text-primary fw-bold mb-0">' . lang('Scope') . '</span>
                                    <span class="material-icons">expand_more</span>
                                </div>
                                <div class="collapse show" id="scope_panel">
                                    <div class="card-body">
                                        <div class="mb-3">
                                            <button type="button"
                                                onclick="document.querySelectorAll(\'#scope_checkboxes input\').forEach(c=>c.checked=true)"
                                                class="btn btn-sm btn-outline-secondary me-1">' . lang('Select All') . '</button>
                                            <button type="button"
                                                onclick="document.querySelectorAll(\'#scope_checkboxes input\').forEach(c=>c.checked=false)"
                                                class="btn btn-sm btn-outline-secondary">' . lang('Deselect All') . '</button>
                                        </div>
                                        <div id="scope_checkboxes">
                                            ' . $scope_html . '
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                    </div>

                    <nav class="buttons navigation text-center position-sticky mb-4" style="bottom:.5rem;">
                        <div class="container">
                            <div class="btn-group flex-wrap justify-content-center">
                                <button type="submit" name="submit_button" value="Find"
                                    class="btn my-1 btn-outline-primary"
                                    data-loading-content="' . lang('Searching') . '">
                                    <span class="material-icons me-2">search</span>
                                    <span class="btn-text">' . lang('Find') . '</span>
                                </button>
                                <button type="submit" name="submit_button" value="Find &amp; Replace"
                                    class="btn my-1 btn-success"
                                    data-loading-content="' . lang(array('string'=>'Replacing')) . '">
                                    <span class="material-icons me-2">find_replace</span>
                                    <span class="btn-text">' . lang(array('string'=>'Find & Replace')) . '</span>
                                </button>
                            </div>
                        </div>
                    </nav>
                </form>
            </div>
        </div>
    </main>' .
    output_footer();

    $liveform->remove_form();

// ── POST: işle ───────────────────────────────────────────────────────────────
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
    $selected_scope = isset($_POST['scope']) ? $_POST['scope'] : array_keys($all_table_defs);

    // Regex geçerlilik kontrolü
    if ($use_regex) {
        $flags = 'u' . ($case_sensitive ? '' : 'i');
        if (@preg_match('/' . $find . '/' . $flags, '') === false) {
            $liveform->mark_error('find', lang('Invalid regular expression.'));
            go($_SERVER['PHP_SELF']);
        }
    }

    // Seçilen kapsama göre tablo listesini filtrele
    $tables = array_intersect_key($all_table_defs, array_flip($selected_scope));

    // ────────────────────────────────────────────────────────────────────────
    // FIND ONLY
    // ────────────────────────────────────────────────────────────────────────
    if ($action === 'Find') {

        $find_results  = [];
        $total_matches = 0;
        $total_records = 0;

        foreach ($tables as $key => $def) {

            // SELECT oluştur: id + name + extra + aranacak sütunlar
            $select_cols = array_merge(
                [$def['id_column'], $def['name_column']],
                $def['extra_select'],
                $def['columns']
            );
            $sql_select    = implode(', ', array_unique($select_cols));
            $sql_join      = !empty($def['extra_join']) ? ' ' . $def['extra_join'] : '';
            $sql_base_where = !empty($def['base_where'] ?? '') ? '(' . $def['base_where'] . ') AND ' : '';
            $sql_where     = build_find_where($def['columns'], $find, $case_sensitive, $use_regex);

            $items = db_items(
                "SELECT $sql_select FROM {$def['table']}$sql_join WHERE $sql_base_where($sql_where)"
            );

            if (!$items) continue;

            $group_results = [];

            foreach ($items as $item) {
                $col_matches = [];

                foreach ($def['columns'] as $col) {
                    // Kaç eşleşme var?
                    if ($use_regex) {
                        $flags = 'u' . ($case_sensitive ? '' : 'i');
                        $cnt   = (int) @preg_match_all('/' . $find . '/' . $flags, $item[$col]);
                    } elseif ($case_sensitive) {
                        $cnt = substr_count($item[$col], $find);
                    } else {
                        $cnt = substr_count(mb_strtolower($item[$col]), mb_strtolower($find));
                    }

                    if ($cnt > 0) {
                        $snippet = find_match_context($item[$col], $find, $case_sensitive, (bool)$use_regex);
                        $col_matches[] = [
                            'col'     => $col,
                            'count'   => $cnt,
                            'snippet' => $snippet,
                        ];
                        $total_matches += $cnt;
                    }
                }

                if ($col_matches) {
                    $group_results[] = [
                        'id'      => $item[$def['id_column']],
                        'name'    => $item[$def['name_column']],
                        'url'     => build_find_edit_url($def['edit_url'], $item, $def['url_raw'] ?? false),
                        'matches' => $col_matches,
                    ];
                    $total_records++;
                }
            }

            if ($group_results) {
                $find_results[$key] = [
                    'label'   => $def['label'],
                    'results' => $group_results,
                ];
            }
        }

        // Eşleşme bulunamadı
        if (!$total_matches) {
            $liveform->mark_error('find', lang('Sorry, no matches were found. Please try entering different text to find.'));
            go($_SERVER['PHP_SELF']);
        }

        // Sonuç HTML'i oluştur
        $results_html = '';
        foreach ($find_results as $group) {
            $rows = '';
            foreach ($group['results'] as $r) {
                $name_cell = $r['url']
                    ? '<a href="' . h($r['url']) . '" target="_blank" rel="noopener" class="fw-semibold text-decoration-none">'
                        . h($r['name'])
                        . ' <span class="material-icons align-middle" style="font-size:.85rem">open_in_new</span></a>'
                    : '<span class="fw-semibold">' . h($r['name']) . '</span>';

                foreach ($r['matches'] as $m) {
                    $rows .= '
                    <tr>
                        <td class="align-top">' . $name_cell . '</td>
                        <td class="align-top text-muted small font-monospace">' . h($m['col']) . '</td>
                        <td class="align-top text-center"><span class="badge bg-secondary">' . $m['count'] . '</span></td>
                        <td class="align-top small font-monospace lh-lg">' . $m['snippet'] . '</td>
                    </tr>';
                }
            }

            $results_html .= '
            <div class="card mb-4">
                <div class="card-header bg-reset border-0 text-uppercase h6 text-primary fw-bold d-flex align-items-center gap-2">
                    ' . h($group['label']) . '
                    <span class="badge bg-primary fw-normal">' . count($group['results']) . ' ' . lang('record') . '</span>
                </div>
                <div class="card-body p-0">
                    <table class="table table-hover table-sm mb-0">
                        <thead class="table-light">
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
            </div>';
        }

        // Özet
        $summary = number_format($total_matches) . ' '
            . lang('matches found in')
            . ' ' . number_format($total_records) . ' '
            . lang('records');

        echo
        output_header([
            'title'        => lang('Find Results'),
            'extra classes'=> 'setting',
            'icon'         => 'setting',
            'heading'      => lang('Find Results'),
        ]) . '
        <main id="content" class="container">
            <div class="row">
                <div class="col-12">
                    <div class="row mb-2 flex-wrap">
                        <div class="col-12 text-center text-md-start">
                            <h2>' . lang('Find Results') . '</h2>
                            <p class="text-muted">' . h($summary) . '</p>
                            <a href="find_and_replace.php" class="btn btn-outline-secondary btn-sm mb-4">
                                <span class="material-icons me-1" style="font-size:1rem">arrow_back</span>
                                ' . lang('Back to Find & Replace') . '
                            </a>
                        </div>
                    </div>
                    ' . $results_html . '
                </div>
            </div>
        </main>' .
        output_footer();

        $liveform->remove_form();
        exit;

    // ────────────────────────────────────────────────────────────────────────
    // FIND & REPLACE
    // ────────────────────────────────────────────────────────────────────────
    } else {

        $number_of_items        = 0;
        $number_of_replacements = 0;

        foreach ($tables as $def) {

            $sql_select     = $def['id_column'];
            foreach ($def['columns'] as $col) {
                $sql_select .= ', ' . $col;
            }

            $sql_base_where = !empty($def['base_where'] ?? '') ? '(' . $def['base_where'] . ') AND ' : '';
            $sql_where      = build_find_where($def['columns'], $find, $case_sensitive, $use_regex);

            $items = db_items(
                "SELECT $sql_select FROM {$def['table']} WHERE $sql_base_where($sql_where)"
            );

            foreach ($items as $item) {
                $replacement = false;

                foreach ($def['columns'] as $col) {
                    if ($use_regex) {
                        $flags   = 'u' . ($case_sensitive ? '' : 'i');
                        $new_val = @preg_replace('/' . $find . '/' . $flags, $replace, $item[$col], -1, $count);
                        if ($new_val === null) continue;
                        $item[$col] = $new_val;
                    } elseif ($case_sensitive) {
                        $item[$col] = str_replace($find, $replace, $item[$col], $count);
                    } else {
                        $item[$col] = str_ireplace($find, $replace, $item[$col], $count);
                    }

                    if ($count > 0) {
                        $replacement             = true;
                        $number_of_replacements += $count;
                    }
                }

                if ($replacement == true) {
                    $sql_set = '';
                    foreach ($def['columns'] as $col) {
                        if ($sql_set != '') $sql_set .= ', ';
                        $sql_set .= "$col = '" . e($item[$col]) . "'";
                    }
                    db("UPDATE {$def['table']} SET $sql_set WHERE {$def['id_column']} = '" . $item[$def['id_column']] . "'");
                    $number_of_items++;
                }
            }
        }

        if ($number_of_replacements == 0) {
            $liveform->mark_error('find', lang('Sorry, no matches were found. Please try entering different text to find.'));
            go($_SERVER['PHP_SELF']);
        }

        if ($number_of_replacements == 1) {
            $match_plural_suffix = '';
            $match_verb          = 'was';
        } else {
            $match_plural_suffix = 'es';
            $match_verb          = 'were';
        }

        $item_plural_suffix = $number_of_items == 1 ? '' : 's';

        $message = lang(array(
            'string' => '{var:1} match{suffix:1} {var:2} found and replaced in {var:3} item{suffix:2}.',
            'vars'   => array(number_format($number_of_replacements), $match_verb, number_format($number_of_items)),
            'suffix' => array($match_plural_suffix, $item_plural_suffix),
        ));

        log_activity(lang('Find & Replace') . ': ' . $message, $_SESSION['sessionusername']);
        $liveform->add_notice(h($message));
        go($_SERVER['PHP_SELF']);
    }
}
?>
