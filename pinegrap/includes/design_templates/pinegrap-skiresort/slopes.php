<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Ski Resort", its slopes and lifts: the staff pages that
 * add a slope and a lift (the Slopes and Lifts forms, collections, with the
 * sample records written in when they are made), the slopes page with the
 * slope map and its difficulty and area filters, the slope's page, the
 * lifts page with its area filter, and the featured slopes the home page
 * places. Included after parts.php and cards.php in the same scope;
 * returns array('pages' => ..., 'widgets' => ...).
 *
 * The slopes page opens with the Ski Resort palette's slope map
 * (skiresort-slope-map, assets/js/style_designer_blocks_skiresort.js),
 * drawn here with its structure and classes: the map picture, the
 * difficulty legend in the markers' fixed colours (a record's colour cannot
 * follow its field) and the areas. A slope's and a lift's status is a field
 * the staff change each morning from the record's Edit button; a lift has
 * no page of its own.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

$slope_pages = array();

// ── Pieces ──────────────────────────────────────────────────────────────

// A filter row of a list: a fixed value, or the value of the address
// parameter $param when one is named.
$skr_filter = function ($field, $operator, $value, $param = '') {
    $row = array('field' => $field, 'operator' => $operator, 'value' => $value, 'dynamic_value' => $param !== '' ? 'url parameter' : '');
    if ($param !== '') $row['dynamic_value_attribute'] = $param;
    return $row;
};

// A list of a collection's records: its form page, its record page (none
// for the lifts), the order and the words shown when nothing matches.
$skr_record_list = function ($form_page, $detail_page, $empty, $more, $order = 'title', $direction = 'ASC') {
    $config = array(
        'regionType'          => 'form_list_view',
        'custom_form_page_id' => '{{tab:' . $form_page . '}}',
        'order_by_field'      => $order,
        'order_by_direction'  => $direction,
        'empty_message'       => $empty,
    );
    if ($detail_page !== '') $config['detail_page_id'] = '{{tab:' . $detail_page . '}}';
    return array_merge($config, $more);
};

// The links above a list that narrow it: one per answer, each opening the
// list page with the filter set.
$skr_filter_bar = function ($page_key, $label, $param, $values) use ($el, $link, $span, $attr, $query) {
    $children = array($span($label, 'small fw-semibold text-body-secondary me-1', lang('Label')));
    $children[] = $link(lang('All'), '{{page:' . $page_key . '}}', 'btn btn-sm btn-outline-secondary');
    foreach ($values as $v) {
        $children[] = $link($v, '{{page:' . $page_key . '}}?' . $query(array($param => $v)), 'btn btn-sm btn-outline-secondary');
    }
    return $el('nav', 'd-flex flex-wrap align-items-center gap-2 mb-3', $label, $children,
        array('_attrs' => array($attr('aria-label', $label))));
};

// The cards of a list, in rows of three.
$slope_grid = function () use ($widget_root, $row, $loop, $slope_card) {
    return $widget_root(array($row(array($loop(array($slope_card()))), '4')));
};
$lift_grid = function () use ($widget_root, $row, $loop, $lift_card) {
    return $widget_root(array($row(array($loop(array($lift_card()))), '4')));
};

$skr_featured_choices = array(lang('Yes'), lang('No'));

// The slope map (skiresort-slope-map): the map picture with its caption,
// the difficulty legend with the markers' colours, each grade opening the
// list on it, and the areas.
$slope_counts = array();
foreach ($slopes as $s) $slope_counts[$s['difficulty']] = ($slope_counts[$s['difficulty']] ?? 0) + 1;
$grade_colours = array('green' => 'pg-ski-green', 'blue' => 'pg-ski-blue', 'red' => 'pg-ski-red', 'black' => 'pg-ski-black');
$slope_map = function () use ($el, $heading, $para, $span, $link, $image, $icon, $container, $row, $col, $searchable, $attr, $photo, $query,
    $difficulties, $areas, $slope_counts, $grade_colours) {
    $grades = array();
    foreach ($difficulties as $key => $d) {
        $grades[] = $el('li', 'd-flex align-items-center gap-3 py-2 border-bottom', lang('Grade'), array(
            $icon($d['icon'], 'fs-4 flex-shrink-0 ' . $grade_colours[$key]),
            $el('div', 'flex-grow-1', lang('Grade Name'), array(
                $el('h4', 'h6 fw-semibold mb-0', lang('Difficulty'), array(
                    $link($d['title'], '{{page:slopes}}?' . $query(array('difficulty' => $d['title'])), 'link-body-emphasis text-decoration-none'),
                )),
                $span($d['summary'], 'small text-body-secondary', lang('Text')),
            )),
            $span(sprintf(lang('%s slopes'), $slope_counts[$key] ?? 0), 'small fw-semibold text-nowrap', lang('Count')),
        ));
    }
    $area_links = array();
    foreach ($areas as $a) {
        $area_links[] = $el('li', '', lang('Area'), array(
            $link($a['title'], '{{page:slopes}}?' . $query(array('area' => $a['title'])), 'btn btn-sm btn-outline-secondary'),
        ));
    }
    return $el('section', 'py-5', lang('Slope Map'), array(
        $container(array(
            $row(array(
                $col(array(
                    $el('figure', 'mb-0', lang('Slope Map'), array(
                        $image($photo('slope-map', 1200, 800), lang('Slope map of the resort with its three areas'), 'img-fluid w-100 rounded-4 shadow-sm'),
                        $el('figcaption', 'd-flex flex-wrap align-items-center justify-content-between gap-2 mt-3', lang('Map Caption'), array(
                            $span(lang('Slopes are groomed every night; a closed slope is roped off.'), 'small text-body-secondary', lang('Text')),
                            $link(lang('Today\'s Conditions'), '{{page:conditions}}', 'link-primary fw-semibold'),
                        )),
                    )),
                ), '', '8'),
                $col(array(
                    $heading('h2', lang('Slope map'), 'psk-display h3 mb-3'),
                    $heading('h3', lang('Difficulty'), 'h5 fw-bold mb-2'),
                    $el('ul', 'list-unstyled mb-4', lang('Difficulty Legend'), $grades),
                    $heading('h3', lang('Areas'), 'h5 fw-bold mb-2'),
                    $el('ul', 'list-unstyled d-flex flex-wrap gap-2 mb-0', lang('Area List'), $area_links),
                ), '', '4'),
            ), '4', '', 'g-lg-5'),
        )),
    ), $searchable('skr-slope-map', lang('piste map, trail map, difficulty, green, blue, red, black, area')));
};

// ── Pages ───────────────────────────────────────────────────────────────

// The slopes: the map, then every slope narrowed by difficulty and area
// from the address.
$slope_pages[] = array(
    'key'              => 'slopes',
    'name'             => lang('slopes'),
    'folder'           => 'public',
    'title'            => lang('Slopes'),
    'meta_description' => lang('Every slope of the resort by difficulty and area, from the nursery slope to the summit couloir, with today\'s status.'),
    'tree'             => $page(array(
        $page_header(lang('Slopes'), lang('Green to black, village to summit. Choose a difficulty or an area to narrow the list.')),
        $slope_map(),
        $el('section', 'py-5 bg-body-tertiary', lang('All Slopes'), array(
            $container(array(
                $heading('h2', lang('All slopes'), 'psk-display h3 mb-3'),
                $skr_filter_bar('slopes', lang('Difficulty'), 'difficulty', $difficulty_titles),
                $skr_filter_bar('slopes', lang('Area'), 'area', $area_titles),
                $el('div', 'mt-4', lang('List'), array($widget('slopes_list'))),
            )),
        ), $searchable('skr-slopes', lang('piste, run, nursery slope, couloir, groomed, open, closed'))),
    )),
);

// One slope.
$slope_pages[] = array(
    'key'              => 'slope',
    'name'             => lang('slope'),
    'folder'           => 'public',
    'title'            => lang('Slope'),
    'meta_description' => lang('A slope of the resort: its difficulty, area, length, vertical drop, the lift that serves it and today\'s status.'),
    'sitemap'          => false,
    'tree'             => $page(array(
        $widget('slope_detail'),
    )),
);

// The lifts, narrowed by area from the address.
$slope_pages[] = array(
    'key'              => 'lifts',
    'name'             => lang('lifts'),
    'folder'           => 'public',
    'title'            => lang('Lifts'),
    'meta_description' => lang('The gondola, chairlifts, drag lifts and magic carpet of the resort, with their ride time, capacity and today\'s status.'),
    'tree'             => $page(array(
        $page_header(lang('Lifts'), sprintf(lang('%s. A lift on hold opens again as soon as the wind drops.'), $resort['hours'])),
        $el('section', 'py-5', lang('All Lifts'), array(
            $container(array(
                $heading('h2', lang('Every lift of the resort'), 'psk-display h3 mb-3'),
                $skr_filter_bar('lifts', lang('Area'), 'area', $area_titles),
                $el('div', 'mt-4', lang('List'), array($widget('lifts_list'))),
            )),
        ), $searchable('skr-lift-list', lang('lift, gondola, chairlift, drag lift, magic carpet, open, closed'))),
    )),
);

// The staff page that adds a slope. The form is the Slopes collection; the
// sample slopes are written in when the form is made, so the lists do not
// open empty (pg_cf_seed_sample_records()).
$sample_slopes = array();
foreach ($slopes as $key => $s) {
    $sample_slopes[] = array(
        'title'      => $s['title'],
        'difficulty' => $slope_difficulty($key),
        'area'       => $slope_area($key),
        'status'     => $slope_status($key),
        'length'     => $s['length'],
        'drop'       => $s['drop'],
        'lift'       => $lifts[$s['lift']]['title'],
        'summary'    => $s['summary'],
        'details'    => $details_text($s['details']),
        'photo_1'    => $photo('slope-' . $key, 1200, 675),
        'featured'   => $s['featured'] ? $skr_featured_choices[0] : $skr_featured_choices[1],
    );
}
$slope_pages[] = array(
    'key'     => 'slope_new',
    'name'    => lang('new-slope'),
    'folder'  => 'private',
    'title'   => lang('New Slope'),
    'search'  => false,
    'sitemap' => false,
    'noindex' => true,
    'form'    => array(
        'form_name'            => lang('Slopes'),
        'collection'           => 1,
        'confirmation_message' => lang('The slope is published.'),
        'confirmation_page_id' => '{{tab:slopes}}',
        'sample_records'       => $sample_slopes,
        'share_image_field'    => 'photo_1',
    ),
    'tree'    => $staff_page(array(
        $page_header(lang('New Slope'), lang('Add a slope with its difficulty, area and photo; it appears in the list as soon as you send it. Change its status each morning from its Edit button.')),
        $el('section', 'py-5', lang('Content'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $el('div', 'card border-0 shadow-sm p-4', lang('Form Card'), array($widget('slope_form'))),
                    ), '', '8'),
                ), '', 'center'),
            )),
        )),
    )),
);

// The staff page that adds a lift. The form is the Lifts collection.
$sample_lifts = array();
foreach ($lifts as $key => $l) {
    $sample_lifts[] = array(
        'title'    => $l['title'],
        'kind'     => $lift_kinds[$l['kind']],
        'area'     => $areas[$l['area']]['title'],
        'status'   => $lift_status($key),
        'ride'     => $l['ride'],
        'capacity' => $l['capacity'],
    );
}
$slope_pages[] = array(
    'key'     => 'lift_new',
    'name'    => lang('new-lift'),
    'folder'  => 'private',
    'title'   => lang('New Lift'),
    'search'  => false,
    'sitemap' => false,
    'noindex' => true,
    'form'    => array(
        'form_name'            => lang('Lifts'),
        'collection'           => 1,
        'confirmation_message' => lang('The lift is published.'),
        'confirmation_page_id' => '{{tab:lifts}}',
        'sample_records'       => $sample_lifts,
    ),
    'tree'    => $staff_page(array(
        $page_header(lang('New Lift'), lang('Add a lift with its kind, area, ride time and capacity. Change its status each morning from its Edit button on the lifts page.')),
        $el('section', 'py-5', lang('Content'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $el('div', 'card border-0 shadow-sm p-4', lang('Form Card'), array($widget('lift_form'))),
                    ), '', '8'),
                ), '', 'center'),
            )),
        )),
    )),
);

// ── Widgets ─────────────────────────────────────────────────────────────

$slope_widgets = array(

    // The staff's Slopes form: one slope, its facts, its photo, its status
    // and whether the home shows it.
    'slope_form' => array(
        'page'   => 'slope_new',
        'slug'   => 'form',
        'config' => array('regionType' => 'custom_form', 'form_source' => 'page'),
        'tree'   => $widget_root(array(
            $form_step(1, lang('The slope')),
            $field('skr-slope-title', lang('Name'), pg_cf_with_role($input('skr-slope-title', 'text', 'title', 'off'), 'title')),
            $row(array(
                $col(array($field('skr-slope-difficulty', lang('Difficulty'), pg_cf_with_role($select('skr-slope-difficulty', 'difficulty', lang('Choose a difficulty'), $difficulty_titles), 'category'))), '6'),
                $col(array($field('skr-slope-area', lang('Area'), $select('skr-slope-area', 'area', lang('Choose an area'), $area_titles))), '6'),
            ), '3'),
            $row(array(
                $col(array($field('skr-slope-length', lang('Length'), $input('skr-slope-length', 'text', 'length', 'off'), 'mb-3',
                    sprintf(lang('In words, for example %s.'), $slopes['meadow']['length']))), '4'),
                $col(array($field('skr-slope-drop', lang('Vertical drop'), $input('skr-slope-drop', 'text', 'drop', 'off'), 'mb-3',
                    sprintf(lang('In words, for example %s.'), $slopes['meadow']['drop']))), '4'),
                $col(array($field('skr-slope-lift', lang('Served by'), $input('skr-slope-lift', 'text', 'lift', 'off'), 'mb-3',
                    lang('The lift that takes skiers to the top.'))), '4'),
            ), '3'),
            $field('skr-slope-summary', lang('Summary'), pg_cf_with_role($input('skr-slope-summary', 'text', 'summary', 'off'), 'description'), 'mb-3',
                lang('One sentence for the cards of the lists.')),
            $field('skr-slope-details', lang('Details'), $textarea('skr-slope-details', 'details', 4), 'mb-3',
                lang('One line per point: grooming, snowmaking, what skiers should know.')),
            $form_step(2, lang('Photo and status')),
            $field('skr-slope-photo', lang('Photo'), pg_cf_with_role($file('skr-slope-photo', 'photo_1', 'public', 'image/*'), 'media'), 'mb-3',
                lang('The picture of the cards and the share image.')),
            $row(array(
                $col(array($field('skr-slope-status', lang('Status'), $select('skr-slope-status', 'status', '', $status_titles, true, $status_titles[0]), 'mb-3',
                    lang('Set by the slope team every morning.'))), '6'),
                $col(array($field('skr-slope-featured', lang('Featured'), $select('skr-slope-featured', 'featured', '', $skr_featured_choices, false, $skr_featured_choices[1], array('office_use_only' => true)),
                    'mb-3', lang('A featured slope is shown on the home page.'))), '6'),
            ), '3'),
            $submit(lang('Publish')),
            $loop(array()),
        )),
    ),

    // The staff's Lifts form: one lift.
    'lift_form' => array(
        'page'   => 'lift_new',
        'slug'   => 'form',
        'config' => array('regionType' => 'custom_form', 'form_source' => 'page'),
        'tree'   => $widget_root(array(
            $field('skr-lift-title', lang('Name'), pg_cf_with_role($input('skr-lift-title', 'text', 'title', 'off'), 'title')),
            $row(array(
                $col(array($field('skr-lift-kind', lang('Kind'), pg_cf_with_role($select('skr-lift-kind', 'kind', lang('Choose a kind'), $lift_kind_titles), 'category'))), '6'),
                $col(array($field('skr-lift-area', lang('Area'), $select('skr-lift-area', 'area', lang('Choose an area'), $area_titles))), '6'),
            ), '3'),
            $row(array(
                $col(array($field('skr-lift-ride', lang('Ride'), $input('skr-lift-ride', 'text', 'ride', 'off'), 'mb-3',
                    sprintf(lang('In words, for example %s.'), $lifts['valley_gondola']['ride']))), '6'),
                $col(array($field('skr-lift-capacity', lang('Capacity'), $input('skr-lift-capacity', 'text', 'capacity', 'off'), 'mb-3',
                    sprintf(lang('In words, for example %s.'), $lifts['valley_gondola']['capacity']))), '6'),
            ), '3'),
            $field('skr-lift-status', lang('Status'), $select('skr-lift-status', 'status', '', $status_titles, true, $status_titles[0]), 'mb-3',
                lang('Set by the lift team every morning.')),
            $submit(lang('Publish')),
            $loop(array()),
        )),
    ),

    // Every slope by name, filtered by the address (difficulty and area),
    // with a search over the words of the slopes.
    'slopes_list' => array(
        'page'   => 'slopes',
        'slug'   => lang('form-list'),
        'config' => $skr_record_list('slope_new', 'slope', lang('No slope matches this choice. Choose another difficulty or area, or see every slope.'), array(
            'items_per_page' => 24,
            'filters'        => array(
                $skr_filter('difficulty', 'is equal to', '', 'difficulty'),
                $skr_filter('area', 'is equal to', '', 'area'),
            ),
            'search_enabled' => true,
            'search_fields'  => array('title', 'summary', 'lift'),
            'search_width'   => 'full',
            'search_label'   => lang('Search the slopes: a name, a lift...'),
        )),
        'tree'   => $slope_grid(),
    ),

    // The home page's slopes: the featured ones, three at most.
    'home_slopes' => array(
        'page'   => 'home',
        'slug'   => lang('form-list'),
        'config' => $skr_record_list('slope_new', 'slope', lang('No slope is featured yet.'), array(
            'items_per_page' => 3,
            'max_results'    => 3,
            'filters'        => array($skr_filter('featured', 'is equal to', $skr_featured_choices[0])),
        )),
        'tree'   => $slope_grid(),
    ),

    // Every lift by name, filtered by the address (area). A lift has no
    // page of its own.
    'lifts_list' => array(
        'page'   => 'lifts',
        'slug'   => lang('form-list'),
        'config' => $skr_record_list('lift_new', '', lang('No lift in this area yet. Choose another area, or see every lift.'), array(
            'items_per_page' => 24,
            'filters'        => array($skr_filter('area', 'is equal to', '', 'area')),
        )),
        'tree'   => $lift_grid(),
    ),
);

// One slope: the photo, the facts, the details line by line, and the ways
// to the conditions and to the other slopes of its difficulty.
$slope_sample_key = 'meadow';
$slope_sample = $slopes[$slope_sample_key];
$slope_not_found = lang('This slope could not be found. It may have been renamed.');
$slope_widgets['slope_detail'] = array(
    'page'   => 'slope',
    'slug'   => lang('form-detail'),
    'config' => array(
        'regionType'          => 'form_item_view',
        'custom_form_page_id' => '{{tab:slope_new}}',
        'access_control'      => 'public',
        'not_found_message'   => $slope_not_found,
    ),
    'tree'   => $widget_root(array(
        $container(array(
            $el('div', 'text-center py-5', lang('Not Found'), array(
                $icon('bi-signpost-split', 'display-5 text-body-secondary'),
                $heading('h2', lang('Slope not found'), 'psk-display h3 mt-3'),
                $para($slope_not_found, 'text-body-secondary mb-4', $bind('not_found')),
                $link(lang('See the Slopes'), '{{page:slopes}}', 'btn btn-primary px-4'),
            ), $show_when('record_not_found')),
            $el('article', '', lang('Slope'), array(
                $el('div', 'd-flex flex-wrap align-items-center justify-content-between gap-2 mb-4', lang('Toolbar'), array(
                    $el('a', 'd-inline-flex align-items-center gap-1 small fw-semibold link-primary text-decoration-none', lang('Link'), array(
                        $icon('bi-arrow-left'),
                        $span(lang('Slopes'), '', lang('Link Text')),
                    ), array('href' => '{{page:slopes}}')),
                    $edit_button(),
                )),
                $row(array(
                    $col(array(
                        $optional_image('photo_1', 'title', 'w-100 h-100 object-fit-cover', 'ratio ratio-16x9 rounded-4 overflow-hidden bg-body-tertiary',
                            $photo('slope-' . $slope_sample_key, 1200, 675), lang('Photo')),
                    ), '', '7'),
                    $col(array(
                        $el('div', 'd-flex flex-wrap align-items-center gap-2 mb-2', lang('Card Top'), array(
                            $span($slope_difficulty($slope_sample_key), 'psk-eyebrow small text-uppercase fw-semibold text-primary', lang('Difficulty'), $bind('difficulty')),
                            $status_badge($slope_status($slope_sample_key)),
                        )),
                        $heading('h1', $slope_sample['title'], 'psk-display display-5 fw-bold mb-3', $bind('title')),
                        $para($slope_sample['summary'], 'lead text-body-secondary mb-4', $bind('summary')),
                        $slope_facts($slope_sample_key, 'list-unstyled mb-4'),
                        $link(lang('Today\'s Conditions'), '{{page:conditions}}', 'btn btn-primary px-4'),
                    ), '', '5'),
                ), '4', '', 'g-lg-5 mb-5'),
                $heading('h2', lang('Good to know'), 'psk-display h4 mb-3'),
                $el('div', 'pg-field-value lh-lg', lang('Details'), array(), array_merge(array('text' => $details_text($slope_sample['details'])), $bind('details'))),
            ), $show_when('record_found')),
        ), 'py-5'),
    )),
);

return array('pages' => $slope_pages, 'widgets' => $slope_widgets);
