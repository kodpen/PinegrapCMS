<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Marina", its charter boats: the staff page that adds a
 * boat (the Charter Boats form, a collection, with the sample boats
 * written in when it is made), the list of every boat with its type and
 * skipper filters, the boat's own page and the list the home page places.
 * Included after parts.php in the same scope; returns
 * array('pages' => ..., 'widgets' => ...).
 *
 * A boat is a record of the Charter Boats form. Type and skipper are pick
 * lists whose answers are the words parts.php gives, so a link with ?type=
 * or ?skipper= (the footer, the filter bar) filters the list by the words
 * a visitor sees. Length, guests and cabins are number fields, so the
 * lists sort them as numbers. The price is words, a guide for a day; the
 * charter desk confirms the price with its offer. Featured is the
 * marina's choice (office use only): the home page shows the featured
 * boats. The pieces defined here ($mn_filter, $mn_featured_choices,
 * $mn_page_link, $mn_filter_bar, $mn_number) serve the events too.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

$charter_pages = array();

// ── Pieces ──────────────────────────────────────────────────────────────

// A filter row of a list's settings: the field, how it compares and the
// value, or where the value comes from (a URL parameter, left out while
// the parameter is not in the address).
$mn_filter = function ($field, $operator, $value, $param = '') {
    $row = array('field' => $field, 'operator' => $operator, 'value' => $value, 'dynamic_value' => $param !== '' ? 'url parameter' : '');
    if ($param !== '') $row['dynamic_value_attribute'] = $param;
    return $row;
};
// The answers of the Featured list: the first marks a record for the home.
$mn_featured_choices = array(lang('Yes'), lang('No'));

// A link to a template page that sends values of the record it is drawn
// in: $params maps the address name to the field it takes (the renderer's
// __page_link).
$mn_page_link = function ($page_key, $params) {
    $list = array();
    foreach ($params as $name => $token) $list[] = array('name' => $name, 'token' => $token);
    return array(
        '_bindings'   => array('href' => '__page_link'),
        '_bindParams' => array('href' => array('page_id' => '{{tab:' . $page_key . '}}', 'params' => $list)),
    );
};

// The links above a list that narrow it: one per answer of each pick
// list, each opening the list page $page_key with the filter set.
$mn_filter_bar = function ($page_key, $groups) use ($el, $link, $span, $query) {
    $navs = array();
    foreach ($groups as $g) {
        $children = array($span($g['label'], 'small fw-semibold text-body-secondary me-1', lang('Label')));
        $children[] = $link(lang('All'), '{{page:' . $page_key . '}}', 'btn btn-sm btn-outline-secondary rounded-pill');
        foreach ($g['values'] as $v) {
            $children[] = $link($v, '{{page:' . $page_key . '}}?' . $query(array($g['param'] => $v)), 'btn btn-sm btn-outline-secondary rounded-pill');
        }
        $navs[] = $el('div', 'd-flex flex-wrap align-items-center gap-2 mb-2', $g['label'], $children);
    }
    return $el('nav', 'mb-4', lang('Filters'), $navs);
};

// A number field of a form: a number kind, so the lists sort it as a
// number.
$mn_number = function ($id, $name, $min) use ($input, $attr) {
    return $input($id, 'number', $name, 'off', true, array('kind' => 'number'), array($attr('min', (string)$min), $attr('step', '1')));
};

// A list of boats: the Charter Boats form, the boat page, A to Z, and the
// words shown when nothing matches.
$mn_boat_list = function ($empty, $more) {
    return array_merge(array(
        'regionType'          => 'form_list_view',
        'custom_form_page_id' => '{{tab:boat_new}}',
        'detail_page_id'      => '{{tab:boat}}',
        'order_by_field'      => 'title',
        'order_by_direction'  => 'ASC',
        'empty_message'       => $empty,
    ), $more);
};
// The boat cards of a list, in rows of three.
$boat_grid = function () use ($widget_root, $row, $loop, $boat_card) {
    return $widget_root(array($row(array($loop(array($boat_card()))), '4')));
};

// A photo of the boat page, in a frame that leaves when the record has
// none.
$mn_photo = function ($field, $seed) use ($optional_image, $photo) {
    return $optional_image($field, 'title', 'rounded-4 object-fit-cover', 'ratio ratio-4x3', $photo($seed, 1200, 900), lang('Photo'));
};

// The terms the charter desk confirms with every offer.
$charter_note = lang('Prices are a guide for a day in the season. The charter desk confirms the price, the deposit and what is included with its offer.');

// ── Pages ───────────────────────────────────────────────────────────────

// Every charter boat, narrowed by type and skipper from the address.
$charter_pages[] = array(
    'key'              => 'charters',
    'name'             => lang('charters'),
    'folder'           => 'public',
    'title'            => lang('Charter Boats'),
    'meta_description' => lang('Sailing yachts, a catamaran, a motor yacht, day boats and a RIB to charter from the marina, with or without a skipper.'),
    'tree'             => $page(array(
        $page_header(lang('Charter Boats'), lang('Choose a type of boat or how you want to sail, then ask about the boat you like.')),
        $el('section', 'py-5', lang('Charter Boats'), array(
            $container(array(
                $mn_filter_bar('charters', array(
                    array('label' => lang('Type'), 'param' => 'type', 'values' => $type_titles),
                    array('label' => lang('Skipper'), 'param' => 'skipper', 'values' => $skipper_choices),
                )),
                $widget('charters_list'),
            )),
        ), $searchable('mn-charters', lang('charter, rent, hire, yacht, sailing boat, catamaran, motor yacht, day boat, rib, skipper, bareboat'))),
    )),
);

// One boat: its record.
$charter_pages[] = array(
    'key'              => 'boat',
    'name'             => lang('charter-boat'),
    'folder'           => 'public',
    'title'            => lang('Charter Boat'),
    'meta_description' => lang('A charter boat of the marina: its type, length, guests and cabins, its features, its photos and a guide price.'),
    'sitemap'          => false,
    'tree'             => $page(array(
        $widget('boat_detail'),
    )),
);

// The staff page that adds a boat. The form is the Charter Boats
// collection; the sample boats are written in when the form is made, so
// the lists do not open empty (pg_cf_seed_sample_records()).
$sample_boats = array();
foreach ($boats as $key => $b) {
    $sample_boats[] = array(
        'title'       => $b['name'],
        'type'        => $boat_type($key),
        'skipper'     => $boat_skipper($key),
        'length'      => (string)$b['length'],
        'guests'      => (string)$b['guests'],
        'cabins'      => (string)$b['cabins'],
        'price'       => $boat_price($key),
        'summary'     => $b['summary'],
        'description' => $b['description'],
        'features'    => $features_text($key),
        'photo_1'     => $photo('boat-' . $key, 1200, 900),
        'photo_2'     => $photo('boat-' . $key . '-2', 1200, 900),
        'photo_3'     => $photo('boat-' . $key . '-3', 1200, 900),
        'featured'    => $b['featured'] ? $mn_featured_choices[0] : $mn_featured_choices[1],
    );
}
$charter_pages[] = array(
    'key'     => 'boat_new',
    'name'    => lang('new-charter-boat'),
    'folder'  => 'private',
    'title'   => lang('New Charter Boat'),
    'search'  => false,
    'sitemap' => false,
    'noindex' => true,
    'form'    => array(
        'form_name'            => lang('Charter Boats'),
        'collection'           => 1,
        'confirmation_message' => lang('The charter boat is published.'),
        'confirmation_page_id' => '{{tab:charters}}',
        'sample_records'       => $sample_boats,
        // The first photo is the boat's share image (pg_cf_share_image_field_id()).
        'share_image_field'    => 'photo_1',
    ),
    'tree'    => $staff_page(array(
        $page_header(lang('New Charter Boat'), lang('Add the boat with its facts and photos; it appears in the list as soon as you send it. Mark it featured from its Edit button.')),
        $el('section', 'py-5', lang('Content'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $el('div', 'p-4 rounded-4 border', lang('Form Card'), array($widget('boat_form'))),
                    ), '', '8'),
                ), '', 'center'),
            )),
        )),
    )),
);

// ── Widgets ─────────────────────────────────────────────────────────────

$charter_widgets = array(

    // The staff's Charter Boats form: one boat. Featured starts as No.
    'boat_form' => array(
        'page'   => 'boat_new',
        'slug'   => 'form',
        'config' => array('regionType' => 'custom_form', 'form_source' => 'page'),
        'tree'   => $widget_root(array(
            $form_step(1, lang('The boat')),
            $field('mn-boat-title', lang('Name'), pg_cf_with_role($input('mn-boat-title', 'text', 'title', 'off'), 'title'), 'mb-3',
                lang('The boat\'s name, as crews will ask for it.')),
            $row(array(
                $col(array($field('mn-boat-type', lang('Type'), pg_cf_with_role($select('mn-boat-type', 'type', lang('Choose a type…'), $type_titles), 'category'))), '6'),
                $col(array($field('mn-boat-skipper', lang('Skipper'), $select('mn-boat-skipper', 'skipper', '', $skipper_choices, true, $skipper_choices[0]))), '6'),
                $col(array($field('mn-boat-length', lang('Length in metres'), $mn_number('mn-boat-length', 'length', 1))), '4'),
                $col(array($field('mn-boat-guests', lang('Guests'), $mn_number('mn-boat-guests', 'guests', 1), 'mb-3', lang('The most people aboard for a day.'))), '4'),
                $col(array($field('mn-boat-cabins', lang('Cabins'), $mn_number('mn-boat-cabins', 'cabins', 0))), '4'),
                $col(array($field('mn-boat-price', lang('Price'), $input('mn-boat-price', 'text', 'price', 'off'), 'mb-3',
                    sprintf(lang('In words, for example %s.'), $boat_price('gull')))), '12'),
            ), '3'),
            $form_step(2, lang('Words')),
            $field('mn-boat-summary', lang('Summary'), pg_cf_with_role($textarea('mn-boat-summary', 'summary', 2), 'description'), 'mb-3',
                lang('One or two lines for the cards of the lists.')),
            $field('mn-boat-description', lang('Description'), $textarea('mn-boat-description', 'description', 5, false)),
            $field('mn-boat-features', lang('Features'), $textarea('mn-boat-features', 'features', 4, false), 'mb-3', lang('One feature on each line.')),
            $form_step(3, lang('Photos')),
            $field('mn-boat-photo-1', lang('First Photo'), pg_cf_with_role($file('mn-boat-photo-1', 'photo_1', 'public', 'image/*'), 'media'), 'mb-3',
                lang('The photo of the cards and the share image.')),
            $row(array(
                $col(array($field('mn-boat-photo-2', lang('Second Photo'), $file('mn-boat-photo-2', 'photo_2', 'public', 'image/*'))), '6'),
                $col(array($field('mn-boat-photo-3', lang('Third Photo'), $file('mn-boat-photo-3', 'photo_3', 'public', 'image/*'))), '6'),
            ), '3'),
            $field('mn-boat-featured', lang('Featured'), $select('mn-boat-featured', 'featured', '', $mn_featured_choices, false, $mn_featured_choices[1], array('office_use_only' => true)),
                'mb-3', lang('A featured boat is shown on the home page.')),
            $submit(lang('Publish')),
            $loop(array()),
        )),
    ),

    // Every boat, filtered by the address (type, skipper), A to Z, with a
    // search over the words of the boats.
    'charters_list' => array(
        'page'   => 'charters',
        'slug'   => lang('form-list'),
        'config' => $mn_boat_list(lang('No boat matches these choices. Try another type, or ask the charter desk.'), array(
            'items_per_page' => 12,
            'filters'        => array(
                $mn_filter('type', 'is equal to', '', 'type'),
                $mn_filter('skipper', 'is equal to', '', 'skipper'),
            ),
            'search_enabled' => true,
            'search_fields'  => array('title', 'type', 'skipper', 'summary', 'description', 'features'),
            'search_width'   => 'full',
            'search_label'   => lang('Search the boats: catamaran, flybridge, paddle boards...'),
        )),
        'tree'   => $boat_grid(),
    ),

    // The home page's featured boats, six at most.
    'home_charters' => array(
        'page'   => 'home',
        'slug'   => lang('form-list'),
        'config' => $mn_boat_list(lang('No featured charter boats yet.'), array(
            'items_per_page' => 6,
            'max_results'    => 6,
            'filters'        => array($mn_filter('featured', 'is equal to', $mn_featured_choices[0])),
        )),
        'tree'   => $boat_grid(),
    ),
);

// One boat: the type and skipper, the name, the summary, the facts, the
// photos, the description and the features, and beside them the guide
// price and the ways to ask about this boat or see more of its type.
$boat_sample_key = 'aurora';
$boat_sample = $boats[$boat_sample_key];
$boat_not_found = lang('This boat could not be found. It may have been renamed or taken off the charter list.');
$charter_widgets['boat_detail'] = array(
    'page'   => 'boat',
    'slug'   => lang('form-detail'),
    'config' => array(
        'regionType'          => 'form_item_view',
        'custom_form_page_id' => '{{tab:boat_new}}',
        'access_control'      => 'public',
        'not_found_message'   => $boat_not_found,
    ),
    'tree'   => $widget_root(array(
        $container(array(
            $el('div', 'text-center py-5', lang('Not Found'), array(
                $icon('bi-signpost', 'display-5 text-body-tertiary'),
                $heading('h2', lang('Boat not found'), 'h3 fw-semibold mt-3'),
                $para($boat_not_found, 'text-body-secondary mb-4', $bind('not_found')),
                $link(lang('See All Charter Boats'), '{{page:charters}}', 'btn btn-primary px-4'),
            ), $show_when('record_not_found')),
            $el('article', '', lang('Charter Boat'), array(
                $el('div', 'd-flex flex-wrap align-items-center justify-content-between gap-2 mb-3', lang('Toolbar'), array(
                    $el('a', 'd-inline-flex align-items-center gap-1 small fw-semibold link-primary text-decoration-none', lang('Link'), array(
                        $icon('bi-arrow-left'),
                        $span(lang('All Charter Boats'), '', lang('Link Text')),
                    ), array('href' => '{{page:charters}}')),
                    $edit_button(),
                )),
                $el('div', 'd-flex flex-wrap gap-2 mb-2', lang('Badges'), array(
                    $span($boat_type($boat_sample_key), 'badge rounded-pill bg-primary-subtle text-primary-emphasis', lang('Type'), $bind('type')),
                    $span($boat_skipper($boat_sample_key), 'badge rounded-pill text-bg-dark', lang('Skipper'), $bind('skipper')),
                )),
                $heading('h1', $boat_sample['name'], 'pmn-display display-6 mb-2', $bind('title')),
                $para($boat_sample['summary'], 'lead text-body-secondary mb-3', $bind('summary')),
                $boat_facts($boat_sample_key, 'list-unstyled d-flex flex-wrap gap-3 text-body-secondary mb-4'),
                // The photos: the first large, the other two beside it.
                $row(array(
                    $col(array($mn_photo('photo_1', 'boat-' . $boat_sample_key)), '', '8'),
                    $col(array(
                        $el('div', 'd-flex flex-column gap-3 h-100', lang('Photos'), array(
                            $mn_photo('photo_2', 'boat-' . $boat_sample_key . '-2'),
                            $mn_photo('photo_3', 'boat-' . $boat_sample_key . '-3'),
                        )),
                    ), '', '4'),
                ), '3', '', 'mb-5'),
                $row(array(
                    $col(array(
                        $el('div', 'pg-hide-if-empty mb-4', lang('Description'), array(
                            $heading('h2', lang('About this boat'), 'pmn-display h4 mb-2'),
                            $el('div', 'pg-field-value', lang('Value'), array(), array_merge(array('text' => $boat_sample['description']), $bind('description'))),
                        )),
                        $el('div', 'pg-hide-if-empty mb-4', lang('Features'), array(
                            $heading('h2', lang('Features'), 'pmn-display h4 mb-2'),
                            $el('div', 'pg-field-value lh-lg', lang('Value'), array(), array_merge(array('text' => $features_text($boat_sample_key)), $bind('features'))),
                        )),
                    ), '', '8'),
                    $col(array(
                        $el('aside', 'card border-0 shadow-sm rounded-4', lang('Charter Card'), array(
                            $el('div', 'card-body p-4', lang('Card Body'), array(
                                $span($boat_price($boat_sample_key), 'pmn-price d-block h4 fw-bold mb-1', lang('Price'), $bind('price')),
                                $el('p', 'small text-body-secondary mb-3', lang('Cabins'), array(
                                    $span(lang('Cabins'), 'me-1', lang('Label')),
                                    $span((string)$boat_sample['cabins'], '', lang('Value'), $bind('cabins')),
                                )),
                                $para($charter_note, 'small text-body-secondary mb-3'),
                                // Opens the charter enquiry with this boat
                                // chosen (?boat=).
                                $link(lang('Ask about this boat'), '#', 'btn btn-primary rounded-pill w-100 mb-2', array(),
                                    $mn_page_link('charter_enquiry', array('boat' => 'title'))),
                                // Opens the list on this boat's type (?type=).
                                $link(lang('More boats of this type'), '#', 'btn btn-outline-secondary rounded-pill w-100', array(),
                                    $mn_page_link('charters', array('type' => 'type'))),
                            )),
                        )),
                    ), '', '4'),
                ), '4', '', 'g-lg-5 mb-4'),
            ), $show_when('record_found')),
        ), 'py-5'),
    )),
);

return array('pages' => $charter_pages, 'widgets' => $charter_widgets);
