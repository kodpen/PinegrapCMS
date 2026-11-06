<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Car Dealership", its vehicles: the staff page that adds
 * a vehicle (the Vehicles form, a collection, with the sample vehicles
 * written in when it is made), the stock list with its filters, the new
 * and used car lists, the vehicle's own page and the lists the home page
 * places. Included after parts.php in the same scope; returns
 * array('pages' => ..., 'widgets' => ...).
 *
 * A vehicle is a record of the Vehicles form. Its make, condition, body
 * style, fuel and gearbox are pick lists whose answers are the words
 * parts.php gives, so a link with ?make=, ?body=, ?fuel= or ?condition=
 * filters the lists by the words a visitor sees. Year, mileage, power and
 * price are number fields, so a list can sort on them; the price label is
 * the words the cards show ("$21,900"). The photos are image fields kept
 * in the public folder. Status and Featured are the staff's (office use
 * only): a sold car leaves the public lists, a featured one is shown on
 * the home page.
 *
 * parts.php keeps the sample vehicles in $vehicles, so every name here
 * starts with vehicle_ and none is $vehicles.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

$vehicle_pages = array();

// ── Pieces ──────────────────────────────────────────────────────────────

// A filter row of a list's settings: the field, how it compares and the
// value, or the URL parameter it comes from (left out while the parameter
// is not in the address).
$vehicle_filter = function ($field, $operator, $value, $param = '') {
    $row = array('field' => $field, 'operator' => $operator, 'value' => $value, 'dynamic_value' => $param !== '' ? 'url parameter' : '');
    if ($param !== '') $row['dynamic_value_attribute'] = $param;
    return $row;
};
// The filters a visitor sets with a link: the parameter has the field's name.
$vehicle_url_filters = function ($fields) use ($vehicle_filter) {
    $rows = array();
    foreach ($fields as $f) $rows[] = $vehicle_filter($f, 'is equal to', '', $f);
    return $rows;
};
// A sold car stays on its own page (a link to it keeps working) but leaves
// the lists.
$vehicle_unsold = $vehicle_filter('status', 'is not equal to', $vehicle_statuses[2]);
// The answers of the Featured list: the first marks a car for the home.
$vehicle_featured_choices = array(lang('Yes'), lang('No'));

// A link to a template page that sends values of the record it is drawn
// in: $params maps the address name to the field it takes (the renderer's
// __page_link).
$vehicle_page_link = function ($page_key, $params) {
    $list = array();
    foreach ($params as $name => $token) $list[] = array('name' => $name, 'token' => $token);
    return array(
        '_bindings'   => array('href' => '__page_link'),
        '_bindParams' => array('href' => array('page_id' => '{{tab:' . $page_key . '}}', 'params' => $list)),
    );
};

// A list's settings: the Vehicles form, the vehicle page, newest first.
$vehicle_list_config = function ($more) {
    return array_merge(array(
        'regionType'          => 'form_list_view',
        'custom_form_page_id' => '{{tab:vehicle_new}}',
        'detail_page_id'      => '{{tab:vehicle}}',
        'order_by_field'      => 'submitted_date_and_time',
        'order_by_direction'  => 'DESC',
        'empty_message'       => lang('No car in stock matches these choices right now. Call us: new cars come in every week.'),
    ), $more);
};
// The cards of a list, in rows of three.
$vehicle_grid = function () use ($widget_root, $row, $loop, $vehicle_card) {
    return $widget_root(array($row(array($loop(array($vehicle_card()))), '4')));
};

// The links above a list that narrow it: one per make, body style and fuel
// (and condition on the stock list), each opening the list page named with
// the filter set.
$vehicle_filter_bar = function ($page_key, $with_condition) use ($el, $link, $span, $query, $make_titles, $body_titles, $fuel_titles, $condition_titles) {
    $chips = function ($label, $param, $values) use ($el, $link, $span, $query, $page_key) {
        $children = array($span($label, 'small fw-semibold text-body-secondary me-1', lang('Label')));
        $children[] = $link(lang('All'), '{{page:' . $page_key . '}}', 'btn btn-sm btn-outline-secondary rounded-pill');
        foreach ($values as $v) {
            $children[] = $link($v, '{{page:' . $page_key . '}}?' . $query(array($param => $v)), 'btn btn-sm btn-outline-secondary rounded-pill');
        }
        return $el('div', 'd-flex flex-wrap align-items-center gap-2 mb-2', $label, $children);
    };
    $rows = array();
    if ($with_condition) $rows[] = $chips(lang('Vehicle Condition'), 'condition', $condition_titles);
    $rows[] = $chips(lang('Make'), 'make', $make_titles);
    $rows[] = $chips(lang('Body Style'), 'body', $body_titles);
    $rows[] = $chips(lang('Fuel'), 'fuel', $fuel_titles);
    return $el('nav', 'mb-4', lang('Filters'), $rows);
};

// A list page: the header, the filter links, the list and the band.
$vehicle_list_page = function ($key, $name, $title, $lead, $description, $widget_key, $with_condition) use ($page, $page_header, $el, $container, $widget, $shared, $vehicle_filter_bar) {
    return array(
        'key'              => $key,
        'name'             => $name,
        'folder'           => 'public',
        'title'            => $title,
        'meta_description' => $description,
        'tree'             => $page(array(
            $page_header($title, $lead),
            $el('section', 'py-5', lang('Vehicles'), array(
                $container(array($vehicle_filter_bar($key, $with_condition), $widget($widget_key))),
            )),
            $shared('test_drive_band'),
        )),
    );
};

// ── Pages ───────────────────────────────────────────────────────────────

$vehicle_pages[] = $vehicle_list_page('vehicles', lang('cars'), lang('All Cars'),
    lang('Every car in our showroom, new, used and ex-demonstrator. Choose a make, a body style or a fuel to narrow the list.'),
    lang('New, used and ex-demonstrator cars in stock, with photos, prices, mileage and the way to book a test drive.'),
    'vehicles_list', true);
$vehicle_pages[] = $vehicle_list_page('new_cars', lang('new-cars'), lang('New Cars'),
    lang('New cars from Aster, Norda, Velo and Kestrel, with the full warranty and five years of servicing.'),
    lang('New cars in stock and to order, lowest price first, with the warranty and the servicing plan.'),
    'new_cars_list', false);
$vehicle_pages[] = $vehicle_list_page('used_cars', lang('used-cars'), lang('Used Cars'),
    lang('Used and ex-demonstrator cars, each checked on 120 points and sold with a warranty.'),
    lang('Used and ex-demonstrator cars checked on 120 points, newest models first, each with a warranty.'),
    'used_cars_list', false);

// One vehicle: the photos, the price, the facts, the specification, the
// description and the features, with the ways to try it and to pay.
$vehicle_pages[] = array(
    'key'              => 'vehicle',
    'name'             => lang('car'),
    'folder'           => 'public',
    'title'            => lang('Vehicle'),
    'meta_description' => lang('A car in our showroom: photos, price, mileage, specification and the way to book a test drive.'),
    'sitemap'          => false,
    'tree'             => $page(array(
        $widget('vehicle_detail'),
        $shared('test_drive_band'),
    ), false),
);

// The staff page that adds a vehicle. The form is the Vehicles collection;
// the sample vehicles are written in when the form is made, so the lists
// do not open empty (pg_cf_seed_sample_records()).
$sample_vehicles = array();
foreach ($vehicles as $key => $v) {
    $sample_vehicles[] = array(
        'title'        => $vehicle_title($key),
        'make'         => $makes[$v['make']]['title'],
        'model'        => $v['model'],
        'condition'    => $conditions[$v['condition']],
        'body'         => $bodies[$v['body']]['title'],
        'fuel'         => $fuels[$v['fuel']]['title'],
        'transmission' => $transmissions[$v['gearbox']],
        'year'         => (string)$v['year'],
        'mileage'      => (string)$v['mileage'],
        'power'        => (string)$v['power'],
        'price'        => (string)$v['price'],
        'price_label'  => $v['price_label'],
        'colour'       => $v['colour'],
        'summary'      => $v['summary'],
        'description'  => $v['description'],
        'features'     => implode("\n", $v['features']),
        'photo_1'      => $photo($key, 1200, 900),
        'photo_2'      => $photo($key . '-2', 1200, 900),
        'photo_3'      => $photo($key . '-3', 1200, 900),
        'photo_4'      => $photo($key . '-4', 1200, 900),
        'status'       => $vehicle_statuses[$v['status']],
        'featured'     => $v['featured'] ? $vehicle_featured_choices[0] : $vehicle_featured_choices[1],
    );
}
$vehicle_pages[] = array(
    'key'     => 'vehicle_new',
    'name'    => lang('new-vehicle'),
    'folder'  => 'private',
    'title'   => lang('New Vehicle'),
    'search'  => false,
    'sitemap' => false,
    'noindex' => true,
    'form'    => array(
        'form_name'            => lang('Vehicles'),
        'collection'           => 1,
        'confirmation_message' => lang('The vehicle is published.'),
        'confirmation_page_id' => '{{tab:vehicles}}',
        'sample_records'       => $sample_vehicles,
        // The first photo is the vehicle's share image (pg_cf_share_image_field_id()).
        'share_image_field'    => 'photo_1',
    ),
    'tree'    => $staff_page(array(
        $page_header(lang('New Vehicle'), lang('Add the car with its photos; it appears in the lists as soon as you send it. Mark it reserved, sold or featured from the vehicle\'s Edit button.')),
        $el('section', 'py-5', lang('Content'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $el('div', 'p-4 rounded-3 border', lang('Form Card'), array($widget('vehicle_form'))),
                    ), '', '8'),
                ), '', 'center'),
            )),
        )),
    )),
);

// ── Widget pieces ───────────────────────────────────────────────────────

// A number field of the Vehicles form: a number kind, so the lists sort it
// as a number.
$vehicle_number = function ($id, $name, $min = '0', $max = '') use ($input, $attr) {
    $more = array($attr('min', $min), $attr('step', '1'));
    if ($max !== '') $more[] = $attr('max', $max);
    return $input($id, 'number', $name, 'off', true, array('kind' => 'number'), $more);
};

// A photo of the vehicle page, in a frame that leaves when the record has
// none.
$vehicle_photo = function ($field, $seed) use ($optional_image, $photo) {
    return $optional_image($field, 'title', 'rounded-3 object-fit-cover', 'ratio ratio-4x3', $photo($seed, 1200, 900), lang('Photo'));
};

// The vehicle's specification: a row per value, each leaving when the
// record has none.
$vehicle_spec = function ($key) use ($el, $span, $heading, $bind, $vehicles, $makes, $bodies, $fuels, $transmissions) {
    $s = $vehicles[$key];
    $line = function ($label, $value, $token, $unit = '') use ($el, $span, $bind) {
        $value_children = array($span($value, '', lang('Value'), $bind($token)));
        if ($unit !== '') $value_children[] = $span(' ' . $unit, 'text-body-secondary', lang('Unit'));
        return $el('div', 'd-flex justify-content-between gap-3 py-2 border-bottom small pg-hide-if-empty', $label, array(
            $span($label, 'text-body-secondary', lang('Label')),
            $el('span', 'fw-semibold text-end', lang('Value'), $value_children),
        ));
    };
    return $el('div', 'mb-4', lang('Specification'), array(
        $heading('h2', lang('Specification'), 'h5 fw-semibold mb-2'),
        $el('div', 'row row-cols-1 row-cols-md-2 gx-5', lang('Specification List'), array(
            $el('div', 'col', lang('Column'), array(
                $line(lang('Make'), $makes[$s['make']]['title'], 'make'),
                $line(lang('Model'), $s['model'], 'model'),
                $line(lang('Body Style'), $bodies[$s['body']]['title'], 'body'),
                $line(lang('Colour'), $s['colour'], 'colour'),
            )),
            $el('div', 'col', lang('Column'), array(
                $line(lang('Fuel'), $fuels[$s['fuel']]['title'], 'fuel'),
                $line(lang('Gearbox'), $transmissions[$s['gearbox']], 'transmission'),
                $line(lang('Power'), (string)$s['power'], 'power', lang('hp')),
                $line(lang('Mileage'), number_format($s['mileage']), 'mileage', lang('km')),
            )),
        )),
    ));
};

// Beside the vehicle's words: the price and the ways to try the car, to
// trade in the one the visitor drives and to pay.
$vehicle_aside = function ($key) use ($el, $para, $link, $span, $icon, $bind, $vehicle_page_link, $vehicles, $dealer) {
    $s = $vehicles[$key];
    return $el('aside', 'card border-0 shadow-sm rounded-3', lang('Vehicle Actions'), array(
        $el('div', 'card-body p-4', lang('Card Body'), array(
            $para(lang('Price'), 'small text-uppercase fw-semibold text-body-secondary mb-1'),
            $para($s['price_label'], 'pdl-price h2 fw-bold text-primary mb-3', $bind('price_label')),
            // Each button opens its request with this car named (?vehicle=).
            $link(lang('Book a Test Drive'), '#', 'btn btn-primary w-100 mb-2', array(),
                $vehicle_page_link('test_drive', array('vehicle' => 'title'))),
            $link(lang('Apply for Finance'), '#', 'btn btn-outline-primary w-100 mb-2', array(),
                $vehicle_page_link('finance_apply', array('vehicle' => 'title'))),
            $link(lang('Value My Car'), '#', 'btn btn-outline-secondary w-100 mb-2', array(),
                $vehicle_page_link('tradein', array('vehicle' => 'title'))),
            $link(lang('Call the showroom'), 'tel:' . preg_replace('/[^0-9+]/', '', $dealer['phone']), 'btn btn-link link-secondary w-100 mb-3'),
            $el('p', 'd-flex gap-2 small text-body-secondary mb-0', lang('Note'), array(
                $icon('bi-info-circle', 'mt-1'),
                $span(lang('Test drives are free and last about 30 minutes. We confirm the time within one working day.'), '', lang('Text')),
            )),
        )),
    ));
};

$vehicle_widgets = array(

    // The staff's Vehicles form: one car. Status starts In stock, Featured
    // starts No; both are the staff's (office use only).
    'vehicle_form' => array(
        'page'   => 'vehicle_new',
        'slug'   => 'form',
        'config' => array('regionType' => 'custom_form', 'form_source' => 'page'),
        'tree'   => $widget_root(array(
            $form_step(1, lang('The car')),
            $field('dl-veh-title', lang('Title'), pg_cf_with_role($input('dl-veh-title', 'text', 'title', 'off'), 'title'), 'mb-3',
                lang('The make and the model as the cards show them, for example Aster City 1.0.')),
            $row(array(
                $col(array($field('dl-veh-make', lang('Make'), pg_cf_with_role($select('dl-veh-make', 'make', lang('Choose a make…'), $make_titles), 'category'))), '6'),
                $col(array($field('dl-veh-model', lang('Model'), $input('dl-veh-model', 'text', 'model', 'off'))), '6'),
                $col(array($field('dl-veh-condition', lang('Vehicle Condition'), $select('dl-veh-condition', 'condition', '', $condition_titles, true, $condition_titles[0]))), '6'),
                $col(array($field('dl-veh-body', lang('Body Style'), $select('dl-veh-body', 'body', lang('Choose a body style…'), $body_titles))), '6'),
                $col(array($field('dl-veh-fuel', lang('Fuel'), $select('dl-veh-fuel', 'fuel', lang('Choose a fuel…'), $fuel_titles))), '6'),
                $col(array($field('dl-veh-transmission', lang('Gearbox'), $select('dl-veh-transmission', 'transmission', '', $transmission_titles, true, $transmission_titles[0]))), '6'),
                $col(array($field('dl-veh-colour', lang('Colour'), $input('dl-veh-colour', 'text', 'colour', 'off'))), '6'),
            ), '3'),
            $form_step(2, lang('Numbers and price')),
            $row(array(
                $col(array($field('dl-veh-year', lang('Model year'), $vehicle_number('dl-veh-year', 'year', '1990', '2100'))), '4'),
                $col(array($field('dl-veh-mileage', lang('Mileage (km)'), $vehicle_number('dl-veh-mileage', 'mileage'))), '4'),
                $col(array($field('dl-veh-power', lang('Power (hp)'), $vehicle_number('dl-veh-power', 'power', '1'))), '4'),
                $col(array($field('dl-veh-price', lang('Price'), $vehicle_number('dl-veh-price', 'price'), 'mb-3', lang('The number the lists sort on, without the currency.'))), '6'),
                $col(array($field('dl-veh-price-label', lang('Price as shown'), $input('dl-veh-price-label', 'text', 'price_label', 'off'), 'mb-3', lang('As the cards show it, for example $21,900.'))), '6'),
            ), '3'),
            $form_step(3, lang('Photos and words')),
            $field('dl-veh-photo-1', lang('First Photo'), pg_cf_with_role($file('dl-veh-photo-1', 'photo_1', 'public', 'image/*'), 'media'), 'mb-3',
                lang('The photo of the cards: the car from the front three-quarter, in daylight.')),
            $row(array(
                $col(array($field('dl-veh-photo-2', lang('Second Photo'), $file('dl-veh-photo-2', 'photo_2', 'public', 'image/*'))), '4'),
                $col(array($field('dl-veh-photo-3', lang('Third Photo'), $file('dl-veh-photo-3', 'photo_3', 'public', 'image/*'))), '4'),
                $col(array($field('dl-veh-photo-4', lang('Fourth Photo'), $file('dl-veh-photo-4', 'photo_4', 'public', 'image/*'))), '4'),
            ), '3'),
            $field('dl-veh-summary', lang('Summary'), pg_cf_with_role($textarea('dl-veh-summary', 'summary', 2), 'description'), 'mb-3',
                lang('One or two sentences under the title on the car\'s page.')),
            $field('dl-veh-description', lang('Description'), $textarea('dl-veh-description', 'description', 6)),
            $field('dl-veh-features', lang('Features'), $textarea('dl-veh-features', 'features', 4, false), 'mb-3', lang('One feature on each line.')),
            $form_step(4, lang('Office use')),
            $row(array(
                $col(array($field('dl-veh-status', lang('Status'), $select('dl-veh-status', 'status', '', $vehicle_statuses, true, $vehicle_statuses[0], array('office_use_only' => true)),
                    'mb-3', lang('A sold car leaves the lists; its page stays.'))), '6'),
                $col(array($field('dl-veh-featured', lang('Featured'), $select('dl-veh-featured', 'featured', '', $vehicle_featured_choices, false, $vehicle_featured_choices[1], array('office_use_only' => true)),
                    'mb-3', lang('A featured car is shown on the home page.'))), '6'),
            ), '3'),
            $submit(lang('Publish')),
            $loop(array()),
        )),
    ),

    // Every car not sold, filtered by the address (condition, make, body
    // style, fuel), with a search over the words of the cars.
    'vehicles_list' => array(
        'page'   => 'vehicles',
        'slug'   => lang('form-list'),
        'config' => $vehicle_list_config(array(
            'items_per_page' => 12,
            'filters'        => array_merge(array($vehicle_unsold), $vehicle_url_filters(array('condition', 'make', 'body', 'fuel'))),
            'search_enabled' => true,
            'search_fields'  => array('title', 'make', 'model', 'body', 'colour', 'features', 'description'),
            'search_width'   => 'full',
            'search_label'   => lang('Search the stock: a make, a model, a colour, a feature...'),
        )),
        'tree'   => $vehicle_grid(),
    ),

    // New cars, lowest price first.
    'new_cars_list' => array(
        'page'   => 'new_cars',
        'slug'   => lang('form-list'),
        'config' => $vehicle_list_config(array(
            'items_per_page'     => 12,
            'order_by_field'     => 'price',
            'order_by_direction' => 'ASC',
            'filters'            => array_merge(array($vehicle_filter('condition', 'is equal to', $conditions['new']), $vehicle_unsold),
                $vehicle_url_filters(array('make', 'body', 'fuel'))),
        )),
        'tree'   => $vehicle_grid(),
    ),

    // Used and ex-demonstrator cars, the newest model years first.
    'used_cars_list' => array(
        'page'   => 'used_cars',
        'slug'   => lang('form-list'),
        'config' => $vehicle_list_config(array(
            'items_per_page'     => 12,
            'order_by_field'     => 'year',
            'order_by_direction' => 'DESC',
            'filters'            => array_merge(array($vehicle_filter('condition', 'is not equal to', $conditions['new']), $vehicle_unsold),
                $vehicle_url_filters(array('make', 'body', 'fuel'))),
        )),
        'tree'   => $vehicle_grid(),
    ),

    // The home page's featured cars, three at most.
    'home_featured' => array(
        'page'   => 'home',
        'slug'   => lang('form-list'),
        'config' => $vehicle_list_config(array(
            'items_per_page' => 3,
            'max_results'    => 3,
            'filters'        => array($vehicle_filter('featured', 'is equal to', $vehicle_featured_choices[0]), $vehicle_unsold),
            'empty_message'  => lang('No featured cars yet.'),
        )),
        'tree'   => $vehicle_grid(),
    ),

    // The home page's three latest used cars: the list's default order,
    // the date the car was added, newest first.
    'home_used' => array(
        'page'   => 'home',
        'slug'   => lang('form-list'),
        'config' => $vehicle_list_config(array(
            'items_per_page' => 3,
            'max_results'    => 3,
            'filters'        => array($vehicle_filter('condition', 'is not equal to', $conditions['new']), $vehicle_unsold),
            'empty_message'  => lang('No used cars in stock this week. Ask us: we often know of one before it comes in.'),
        )),
        'tree'   => $vehicle_grid(),
    ),
);

// One vehicle: the photos, the condition, status and body style, the title
// and summary, the facts, the description, the features, the specification
// and the price with the ways to try it and to pay.
$vehicle_sample_key = 'aster-city';
$vehicle_sample = $vehicles[$vehicle_sample_key];
$vehicle_widgets['vehicle_detail'] = array(
    'page'   => 'vehicle',
    'slug'   => lang('form-detail'),
    'config' => array(
        'regionType'          => 'form_item_view',
        'custom_form_page_id' => '{{tab:vehicle_new}}',
        'access_control'      => 'public',
        'not_found_message'   => lang('This car could not be found. It may have been sold or taken out of stock.'),
    ),
    'tree'   => $widget_root(array(
        $container(array(
            $el('div', 'text-center py-5', lang('Not Found'), array(
                $icon('bi-car-front', 'display-5 text-body-tertiary'),
                $heading('h2', lang('Car not found'), 'h3 fw-semibold mt-3'),
                $para(lang('This car could not be found. It may have been sold or taken out of stock.'), 'text-body-secondary mb-4', $bind('not_found')),
                $link(lang('See all cars'), '{{page:vehicles}}', 'btn btn-primary px-4'),
            ), $show_when('record_not_found')),
            $el('article', '', lang('Vehicle'), array(
                $el('div', 'd-flex flex-wrap align-items-center justify-content-between gap-2 mb-3', lang('Toolbar'), array(
                    $el('a', 'd-inline-flex align-items-center gap-1 small fw-semibold link-primary text-decoration-none', lang('Link'), array(
                        $icon('bi-arrow-left'),
                        $span(lang('All Cars'), '', lang('Link Text')),
                    ), array('href' => '{{page:vehicles}}')),
                    $edit_button(),
                )),
                $el('div', 'd-flex flex-wrap gap-2 mb-2', lang('Badges'), array(
                    $span($conditions[$vehicle_sample['condition']], 'badge text-bg-primary', lang('Vehicle Condition'), $bind('condition')),
                    $span($vehicle_statuses[$vehicle_sample['status']], 'badge text-bg-light border', lang('Status'), $bind('status')),
                    $span($bodies[$vehicle_sample['body']]['title'], 'badge bg-primary-subtle text-primary-emphasis', lang('Body Style'), $bind('body')),
                )),
                $heading('h1', $vehicle_title($vehicle_sample_key), 'display-6 fw-semibold mb-2', $bind('title')),
                $para($vehicle_sample['summary'], 'lead text-body-secondary mb-3 pg-hide-if-empty', $bind('summary')),
                $el('div', 'mb-4', lang('Facts'), array($vehicle_facts($vehicle_sample_key))),
                // The photos: the first large, the other three under it.
                $row(array(
                    $col(array($vehicle_photo('photo_1', $vehicle_sample_key))),
                    $col(array($vehicle_photo('photo_2', $vehicle_sample_key . '-2')), '', '', '', '4'),
                    $col(array($vehicle_photo('photo_3', $vehicle_sample_key . '-3')), '', '', '', '4'),
                    $col(array($vehicle_photo('photo_4', $vehicle_sample_key . '-4')), '', '', '', '4'),
                ), '3', '', 'mb-4'),
                $row(array(
                    $col(array(
                        $el('div', 'pg-hide-if-empty mb-4', lang('Description'), array(
                            $heading('h2', lang('About this car'), 'h5 fw-semibold mb-2'),
                            $el('div', 'pg-field-value lh-lg', lang('Value'), array(), array_merge(array('text' => $vehicle_sample['description']), $bind('description'))),
                        )),
                        $el('div', 'pg-hide-if-empty p-4 rounded-3 bg-body-tertiary mb-4', lang('Features'), array(
                            $heading('h2', lang('Features'), 'h5 fw-semibold mb-2'),
                            $el('div', 'pg-field-value lh-lg', lang('Value'), array(), array_merge(array('text' => implode("\n", $vehicle_sample['features'])), $bind('features'))),
                        )),
                        $vehicle_spec($vehicle_sample_key),
                        $el('div', 'alert alert-light border d-flex gap-2 small mb-0', lang('Note'), array(
                            $icon('bi-info-circle', 'mt-1'),
                            $span(lang('Prices, mileage and equipment are given in good faith; please confirm them with us before you buy.'), '', lang('Text')),
                        )),
                    ), '', '8'),
                    $col(array($vehicle_aside($vehicle_sample_key)), '', '4'),
                ), '4', '', 'g-lg-5'),
            ), $show_when('record_found')),
        ), 'py-5'),
    )),
);

return array('pages' => $vehicle_pages, 'widgets' => $vehicle_widgets);
