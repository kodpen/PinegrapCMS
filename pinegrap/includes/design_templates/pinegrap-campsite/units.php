<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Campsite", its pitches and cabins: the staff page that
 * adds a pitch or a cabin (the Pitches and Cabins form, a collection, with
 * the sample units written in when it is made), the list of every unit
 * with its kind and setting filters, the unit's own page and the list the
 * home page places. Included after parts.php in the same scope; returns
 * array('pages' => ..., 'widgets' => ...).
 *
 * A unit is a record of the Pitches and Cabins form. Kind, setting and
 * hook-up are pick lists whose answers are the words parts.php gives, so a
 * link with ?kind= or ?setting= (the stay-kind pages, the footer, the
 * filter bar) filters the list by the words a visitor sees. Sleeps and
 * size are number fields, so the lists sort them as numbers. The price is
 * words, a guide written from the season prices; the reception confirms
 * the price with its offer. Featured is the park's choice (office use
 * only): the home page shows the featured units.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

$unit_pages = array();

// ── Pieces ──────────────────────────────────────────────────────────────

// A filter row of a list's settings: the field, how it compares and the
// value, or where the value comes from (a URL parameter, left out while
// the parameter is not in the address).
$cp_filter = function ($field, $operator, $value, $param = '') {
    $row = array('field' => $field, 'operator' => $operator, 'value' => $value, 'dynamic_value' => $param !== '' ? 'url parameter' : '');
    if ($param !== '') $row['dynamic_value_attribute'] = $param;
    return $row;
};
// The answers of the Featured list: the first marks a unit for the home.
$cp_featured_choices = array(lang('Yes'), lang('No'));

// A link to a template page that sends values of the record it is drawn
// in: $params maps the address name to the field it takes (the renderer's
// __page_link).
$cp_page_link = function ($page_key, $params) {
    $list = array();
    foreach ($params as $name => $token) $list[] = array('name' => $name, 'token' => $token);
    return array(
        '_bindings'   => array('href' => '__page_link'),
        '_bindParams' => array('href' => array('page_id' => '{{tab:' . $page_key . '}}', 'params' => $list)),
    );
};

// A list of units: the Pitches and Cabins form, the unit page, A to Z,
// and the words shown when nothing matches.
$cp_unit_list = function ($empty, $more) {
    return array_merge(array(
        'regionType'          => 'form_list_view',
        'custom_form_page_id' => '{{tab:unit_new}}',
        'detail_page_id'      => '{{tab:unit}}',
        'order_by_field'      => 'title',
        'order_by_direction'  => 'ASC',
        'empty_message'       => $empty,
    ), $more);
};
// The unit cards of a list, in rows of three.
$unit_grid = function () use ($widget_root, $row, $loop, $unit_card) {
    return $widget_root(array($row(array($loop(array($unit_card()))), '4')));
};

// The links above the list that narrow it: one per answer of each pick
// list, each opening the list with the filter set.
$cp_filter_bar = function ($groups) use ($el, $link, $span, $query) {
    $navs = array();
    foreach ($groups as $g) {
        $children = array($span($g['label'], 'small fw-semibold text-body-secondary me-1', lang('Label')));
        $children[] = $link(lang('All'), '{{page:units}}', 'btn btn-sm btn-outline-secondary rounded-pill');
        foreach ($g['values'] as $v) {
            $children[] = $link($v, '{{page:units}}?' . $query(array($g['param'] => $v)), 'btn btn-sm btn-outline-secondary rounded-pill');
        }
        $navs[] = $el('div', 'd-flex flex-wrap align-items-center gap-2 mb-2', $g['label'], $children);
    }
    return $el('nav', 'mb-4', lang('Filters'), $navs);
};

// A photo of the unit page, in a frame that leaves when the record has
// none.
$cp_photo = function ($field, $seed) use ($optional_image, $photo) {
    return $optional_image($field, 'title', 'rounded-4 object-fit-cover', 'ratio ratio-4x3', $photo($seed, 1200, 900), lang('Photo'));
};

// A number field of the form: a number kind, so the lists sort it as a
// number.
$cp_number = function ($id, $name, $min) use ($input, $attr) {
    return $input($id, 'number', $name, 'off', true, array('kind' => 'number'), array($attr('min', (string)$min), $attr('step', '1')));
};

// ── Pages ───────────────────────────────────────────────────────────────

// Every pitch and cabin, narrowed by kind and setting from the address.
$unit_pages[] = array(
    'key'              => 'units',
    'name'             => lang('pitches-and-cabins'),
    'folder'           => 'public',
    'title'            => lang('Pitches and Cabins'),
    'meta_description' => lang('Every tent pitch, touring pitch, glamping tent and cabin of the campsite, by the lake, in the meadow or at the forest edge.'),
    'tree'             => $page(array(
        $page_header(lang('Pitches and Cabins'), lang('Choose a kind of stay or a setting to narrow the list, then ask for the place you like.')),
        $el('section', 'py-5', lang('Pitches and Cabins'), array(
            $container(array(
                $cp_filter_bar(array(
                    array('label' => lang('Kind'), 'param' => 'kind', 'values' => $kind_titles),
                    array('label' => lang('Place in the Park'), 'param' => 'setting', 'values' => $setting_titles),
                )),
                $widget('units_list'),
            )),
        ), $searchable('cp-units', lang('pitch, plot, site, caravan, motorhome, tent, glamping, cabin, lodge, bungalow'))),
    )),
);

// One pitch or cabin: its record.
$unit_pages[] = array(
    'key'              => 'unit',
    'name'             => lang('pitch-or-cabin'),
    'folder'           => 'public',
    'title'            => lang('Pitch or Cabin'),
    'meta_description' => lang('A pitch or a cabin of the campsite: its setting, who it sleeps, its features, its photos and a guide price.'),
    'sitemap'          => false,
    'tree'             => $page(array(
        $widget('unit_detail'),
    )),
);

// The staff page that adds a pitch or a cabin. The form is the Pitches and
// Cabins collection; the sample units are written in when the form is
// made, so the lists do not open empty (pg_cf_seed_sample_records()).
$sample_units = array();
foreach ($units as $key => $u) {
    $sample_units[] = array(
        'title'       => $u['name'],
        'kind'        => $unit_kind($key),
        'setting'     => $unit_setting($key),
        'sleeps'      => (string)$u['sleeps'],
        'size'        => (string)$u['size'],
        'hookup'      => $unit_hookup($key),
        'price'       => $from_price($u['kind']),
        'summary'     => $u['summary'],
        'description' => $u['description'],
        'features'    => $features_text($key),
        'photo_1'     => $photo('unit-' . $key, 1200, 900),
        'photo_2'     => $photo('unit-' . $key . '-2', 1200, 900),
        'photo_3'     => $photo('unit-' . $key . '-3', 1200, 900),
        'featured'    => $u['featured'] ? $cp_featured_choices[0] : $cp_featured_choices[1],
    );
}
$unit_pages[] = array(
    'key'     => 'unit_new',
    'name'    => lang('new-pitch-or-cabin'),
    'folder'  => 'private',
    'title'   => lang('New Pitch or Cabin'),
    'search'  => false,
    'sitemap' => false,
    'noindex' => true,
    'form'    => array(
        'form_name'            => lang('Pitches and Cabins'),
        'collection'           => 1,
        'confirmation_message' => lang('The pitch or cabin is published.'),
        'confirmation_page_id' => '{{tab:units}}',
        'sample_records'       => $sample_units,
        // The first photo is the unit's share image (pg_cf_share_image_field_id()).
        'share_image_field'    => 'photo_1',
    ),
    'tree'    => $staff_page(array(
        $page_header(lang('New Pitch or Cabin'), lang('Add the pitch or cabin with its facts and photos; it appears in the list as soon as you send it. Mark it featured from its Edit button.')),
        $el('section', 'py-5', lang('Content'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $el('div', 'p-4 rounded-4 border', lang('Form Card'), array($widget('unit_form'))),
                    ), '', '8'),
                ), '', 'center'),
            )),
        )),
    )),
);

// ── Widgets ─────────────────────────────────────────────────────────────

$unit_widgets = array(

    // The staff's Pitches and Cabins form: one unit. Featured starts as No.
    'unit_form' => array(
        'page'   => 'unit_new',
        'slug'   => 'form',
        'config' => array('regionType' => 'custom_form', 'form_source' => 'page'),
        'tree'   => $widget_root(array(
            $form_step(1, lang('The place')),
            $field('cp-unit-title', lang('Name'), pg_cf_with_role($input('cp-unit-title', 'text', 'title', 'off'), 'title'), 'mb-3',
                lang('A name or a number guests can ask for, for example Lakeside Pitch 12.')),
            $row(array(
                $col(array($field('cp-unit-kind', lang('Kind'), pg_cf_with_role($select('cp-unit-kind', 'kind', lang('Choose a kind…'), $kind_titles), 'category'))), '6'),
                $col(array($field('cp-unit-setting', lang('Place in the Park'), $select('cp-unit-setting', 'setting', lang('Choose a setting…'), $setting_titles))), '6'),
                $col(array($field('cp-unit-sleeps', lang('Sleeps'), $cp_number('cp-unit-sleeps', 'sleeps', 1), 'mb-3', lang('The most people the place takes.'))), '6'),
                $col(array($field('cp-unit-size', lang('Size (m²)'), $cp_number('cp-unit-size', 'size', 1))), '6'),
                $col(array($field('cp-unit-hookup', lang('Hook-up'), $select('cp-unit-hookup', 'hookup', '', $hookup_choices, true, $hookup_choices[0]))), '6'),
                $col(array($field('cp-unit-price', lang('Price'), $input('cp-unit-price', 'text', 'price', 'off'), 'mb-3',
                    sprintf(lang('In words, for example %s.'), $from_price('tent')))), '6'),
            ), '3'),
            $form_step(2, lang('Words')),
            $field('cp-unit-summary', lang('Summary'), pg_cf_with_role($textarea('cp-unit-summary', 'summary', 2), 'description'), 'mb-3',
                lang('One or two lines for the cards of the lists.')),
            $field('cp-unit-description', lang('Description'), $textarea('cp-unit-description', 'description', 5, false)),
            $field('cp-unit-features', lang('Features'), $textarea('cp-unit-features', 'features', 4, false), 'mb-3', lang('One feature on each line.')),
            $form_step(3, lang('Photos')),
            $field('cp-unit-photo-1', lang('First Photo'), pg_cf_with_role($file('cp-unit-photo-1', 'photo_1', 'public', 'image/*'), 'media'), 'mb-3',
                lang('The photo of the cards and the share image.')),
            $row(array(
                $col(array($field('cp-unit-photo-2', lang('Second Photo'), $file('cp-unit-photo-2', 'photo_2', 'public', 'image/*'))), '6'),
                $col(array($field('cp-unit-photo-3', lang('Third Photo'), $file('cp-unit-photo-3', 'photo_3', 'public', 'image/*'))), '6'),
            ), '3'),
            $field('cp-unit-featured', lang('Featured'), $select('cp-unit-featured', 'featured', '', $cp_featured_choices, false, $cp_featured_choices[1], array('office_use_only' => true)),
                'mb-3', lang('A featured pitch or cabin is shown on the home page.')),
            $submit(lang('Publish')),
            $loop(array()),
        )),
    ),

    // Every unit, filtered by the address (kind, setting), A to Z, with a
    // search over the words of the units.
    'units_list' => array(
        'page'   => 'units',
        'slug'   => lang('form-list'),
        'config' => $cp_unit_list(lang('No pitch or cabin matches these choices. Try another kind or setting, or ask the reception.'), array(
            'items_per_page' => 12,
            'filters'        => array(
                $cp_filter('kind', 'is equal to', '', 'kind'),
                $cp_filter('setting', 'is equal to', '', 'setting'),
            ),
            'search_enabled' => true,
            'search_fields'  => array('title', 'kind', 'setting', 'summary', 'description', 'features'),
            'search_width'   => 'full',
            'search_label'   => lang('Search the pitches and cabins: lake view, shade, wood stove...'),
        )),
        'tree'   => $unit_grid(),
    ),

    // The home page's featured units, six at most.
    'home_units' => array(
        'page'   => 'home',
        'slug'   => lang('form-list'),
        'config' => $cp_unit_list(lang('No featured pitches or cabins yet.'), array(
            'items_per_page' => 6,
            'max_results'    => 6,
            'filters'        => array($cp_filter('featured', 'is equal to', $cp_featured_choices[0])),
        )),
        'tree'   => $unit_grid(),
    ),
);

// One unit: the kind and setting, the name, the summary, the facts, the
// photos, the description and the features, and beside them the guide
// price and the ways to ask for this place, see the season prices or see
// more of the same kind.
$unit_sample_key = 'lakeside';
$unit_sample = $units[$unit_sample_key];
$unit_not_found = lang('This pitch or cabin could not be found. It may have been renamed or taken off the list.');
$unit_widgets['unit_detail'] = array(
    'page'   => 'unit',
    'slug'   => lang('form-detail'),
    'config' => array(
        'regionType'          => 'form_item_view',
        'custom_form_page_id' => '{{tab:unit_new}}',
        'access_control'      => 'public',
        'not_found_message'   => $unit_not_found,
    ),
    'tree'   => $widget_root(array(
        $container(array(
            $el('div', 'text-center py-5', lang('Not Found'), array(
                $icon('bi-signpost', 'display-5 text-body-tertiary'),
                $heading('h2', lang('Pitch or cabin not found'), 'h3 fw-semibold mt-3'),
                $para($unit_not_found, 'text-body-secondary mb-4', $bind('not_found')),
                $link(lang('See All Pitches and Cabins'), '{{page:units}}', 'btn btn-primary px-4'),
            ), $show_when('record_not_found')),
            $el('article', '', lang('Pitch or Cabin'), array(
                $el('div', 'd-flex flex-wrap align-items-center justify-content-between gap-2 mb-3', lang('Toolbar'), array(
                    $el('a', 'd-inline-flex align-items-center gap-1 small fw-semibold link-primary text-decoration-none', lang('Link'), array(
                        $icon('bi-arrow-left'),
                        $span(lang('All Pitches and Cabins'), '', lang('Link Text')),
                    ), array('href' => '{{page:units}}')),
                    $edit_button(),
                )),
                $el('div', 'd-flex flex-wrap gap-2 mb-2', lang('Badges'), array(
                    $span($unit_kind($unit_sample_key), 'badge rounded-pill bg-primary-subtle text-primary-emphasis', lang('Kind'), $bind('kind')),
                    $span($unit_setting($unit_sample_key), 'badge rounded-pill text-bg-secondary', lang('Place in the Park'), $bind('setting')),
                )),
                $heading('h1', $unit_sample['name'], 'pcp-display display-6 mb-2', $bind('title')),
                $para($unit_sample['summary'], 'lead text-body-secondary mb-3', $bind('summary')),
                $unit_facts($unit_sample_key, 'list-unstyled d-flex flex-wrap gap-3 text-body-secondary mb-4'),
                // The photos: the first large, the other two beside it.
                $row(array(
                    $col(array($cp_photo('photo_1', 'unit-' . $unit_sample_key)), '', '8'),
                    $col(array(
                        $el('div', 'd-flex flex-column gap-3 h-100', lang('Photos'), array(
                            $cp_photo('photo_2', 'unit-' . $unit_sample_key . '-2'),
                            $cp_photo('photo_3', 'unit-' . $unit_sample_key . '-3'),
                        )),
                    ), '', '4'),
                ), '3', '', 'mb-5'),
                $row(array(
                    $col(array(
                        $el('div', 'pg-hide-if-empty mb-4', lang('Description'), array(
                            $heading('h2', lang('About this place'), 'pcp-display h4 mb-2'),
                            $el('div', 'pg-field-value', lang('Value'), array(), array_merge(array('text' => $unit_sample['description']), $bind('description'))),
                        )),
                        $el('div', 'pg-hide-if-empty mb-4', lang('Features'), array(
                            $heading('h2', lang('Features'), 'pcp-display h4 mb-2'),
                            $el('div', 'pg-field-value lh-lg', lang('Value'), array(), array_merge(array('text' => $features_text($unit_sample_key)), $bind('features'))),
                        )),
                    ), '', '8'),
                    $col(array(
                        $el('aside', 'card border-0 shadow-sm rounded-4', lang('Stay Card'), array(
                            $el('div', 'card-body p-4', lang('Card Body'), array(
                                $span($from_price($unit_sample['kind']), 'pcp-price d-block h4 fw-bold mb-1', lang('Price'), $bind('price')),
                                $el('p', 'small text-body-secondary mb-3', lang('Size'), array(
                                    $span((string)$unit_sample['size'], '', lang('Value'), $bind('size')),
                                    $span(' m²', '', lang('Unit')),
                                )),
                                $para($price_note, 'small text-body-secondary mb-3'),
                                // Opens the stay request with this place and
                                // its kind chosen (?unit=, ?kind=).
                                $link(lang('Request this place'), '#', 'btn btn-primary rounded-pill w-100 mb-2', array(),
                                    $cp_page_link('book', array('unit' => 'title', 'kind' => 'kind'))),
                                $link(lang('See the Prices'), '{{page:prices}}', 'btn btn-outline-secondary rounded-pill w-100 mb-2'),
                                // Opens the list on this place's kind (?kind=).
                                $link(lang('More places of this kind'), '#', 'btn btn-outline-secondary rounded-pill w-100', array(),
                                    $cp_page_link('units', array('kind' => 'kind'))),
                            )),
                        )),
                    ), '', '4'),
                ), '4', '', 'g-lg-5 mb-4'),
            ), $show_when('record_found')),
        ), 'py-5'),
    )),
);

return array('pages' => $unit_pages, 'widgets' => $unit_widgets);
