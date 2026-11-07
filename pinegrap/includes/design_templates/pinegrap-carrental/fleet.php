<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Car Rental", its fleet: the staff page that adds a car
 * (the Fleet form, a collection, with the sample cars written in when it
 * is made), the fleet list with its filters, the car's own page with the
 * other cars under it and the featured cars the home page places.
 * Included after parts.php in the same scope; returns
 * array('pages' => ..., 'widgets' => ...).
 *
 * A car is a record of the Fleet form. Its class, gearbox and fuel are
 * pick lists whose answers are the words parts.php gives, so a link with
 * ?class=, ?gearbox= or ?fuel= filters the list by the words a visitor
 * sees. Seats, doors, bags, the minimum age and the daily rate are number
 * fields, so a list can sort on them; the rate label is the words the
 * cards show ("From $36 a day"). The photos are image fields kept in the
 * public folder. Featured and Available are the staff's (office use only):
 * a car taken out of the fleet leaves the lists, a featured one is shown
 * on the home page.
 *
 * parts.php keeps the sample cars in $cars and its builders under car_
 * names, so every name here starts with fleet_.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

$fleet_pages = array();

// ── Pieces ──────────────────────────────────────────────────────────────

// The answers of the Featured and Available lists: the first marks a car
// for the home page, or as one that can be booked.
$fleet_yes_no = array(lang('Yes'), lang('No'));

// A filter row of a list's settings: the field, how it compares and the
// value, or where the value comes from (a URL parameter, the shown record;
// left out while the address carries none).
$fleet_filter = function ($field, $operator, $value, $dynamic = '', $param = '') {
    return array('field' => $field, 'operator' => $operator, 'value' => $value, 'dynamic_value' => $dynamic, 'dynamic_value_attribute' => $param);
};
// The filters a visitor sets with a link: address parameter => field.
$fleet_url_filters = array(
    $fleet_filter('class', 'is equal to', '', 'url parameter', 'class'),
    $fleet_filter('transmission', 'is equal to', '', 'url parameter', 'gearbox'),
    $fleet_filter('fuel', 'is equal to', '', 'url parameter', 'fuel'),
);
// A car taken out of the fleet keeps its page (a link to it keeps
// working) but leaves the lists.
$fleet_available = $fleet_filter('available', 'is equal to', $fleet_yes_no[0]);

// A link to a template page that sends values of the record it is drawn
// in: $params maps the address name to the field it takes (the renderer's
// __page_link).
$fleet_page_link = function ($page_key, $params) {
    $list = array();
    foreach ($params as $name => $token) $list[] = array('name' => $name, 'token' => $token);
    return array(
        '_bindings'   => array('href' => '__page_link'),
        '_bindParams' => array('href' => array('page_id' => '{{tab:' . $page_key . '}}', 'params' => $list)),
    );
};

// A list's settings: the Fleet form, the car page, the lowest rate first.
$fleet_list_config = function ($more) {
    return array_merge(array(
        'regionType'          => 'form_list_view',
        'custom_form_page_id' => '{{tab:car_new}}',
        'detail_page_id'      => '{{tab:car}}',
        'order_by_field'      => 'rate',
        'order_by_direction'  => 'ASC',
        'empty_message'       => lang('No car of this kind is free right now. Call us: we often have one at another desk.'),
    ), $more);
};
// The cards of a list, in rows of three.
$fleet_grid = function () use ($widget_root, $row, $loop, $car_card) {
    return $widget_root(array($row(array($loop(array($car_card()))), '4')));
};

// The links above the list that narrow it: one per class, gearbox and
// fuel, each opening the fleet with the filter set.
$fleet_filter_bar = function () use ($el, $link, $span, $attr, $query, $class_titles, $transmission_titles, $fuel_titles) {
    $chips = function ($label, $param, $values) use ($el, $link, $span, $query) {
        $children = array($span($label, 'small fw-semibold text-body-secondary me-1', lang('Label')));
        $children[] = $link(lang('All'), '{{page:fleet}}', 'btn btn-sm btn-outline-secondary rounded-pill');
        foreach ($values as $v) {
            $children[] = $link($v, '{{page:fleet}}?' . $query(array($param => $v)), 'btn btn-sm btn-outline-secondary rounded-pill');
        }
        return $el('div', 'd-flex flex-wrap align-items-center gap-2 mb-2', $label, $children);
    };
    return $el('nav', 'mb-4', lang('Filters'), array(
        $chips(lang('Car Class'), 'class', $class_titles),
        $chips(lang('Gearbox'), 'gearbox', $transmission_titles),
        $chips(lang('Fuel'), 'fuel', $fuel_titles),
    ), array('_attrs' => array($attr('aria-label', lang('Filters')))));
};

// ── Pages ───────────────────────────────────────────────────────────────

$fleet_pages[] = array(
    'key'              => 'fleet',
    'name'             => lang('fleet'),
    'folder'           => 'public',
    'title'            => lang('Our Fleet'),
    'meta_description' => lang('Every car we rent, from economy to people carriers and electric cars, with seats, bags, gearbox, fuel and the lowest rate a day.'),
    'tree'             => $page(array(
        $page_header(lang('Our Fleet'), lang('Every car we rent, lowest rate first. Choose a class, a gearbox or a fuel to narrow the list; we promise the class, the model may be a similar one.')),
        $el('section', 'py-5', lang('Cars'), array(
            $container(array($fleet_filter_bar(), $widget('fleet_list'))),
        ), $searchable('cr-fleet', lang('cars, models, classes, automatic, manual, diesel, hybrid, electric'))),
        $shared('booking_band'),
    )),
);

// One car: the photos, the class, the facts, the description and the
// features, the deposit and the minimum age, the rate and the way to book
// it, then the other cars of the fleet.
$fleet_pages[] = array(
    'key'              => 'car',
    'name'             => lang('car'),
    'folder'           => 'public',
    'title'            => lang('Rental Car'),
    'meta_description' => lang('A car of our fleet: photos, seats, bags, gearbox, fuel, the deposit and the lowest rate a day, with the way to book it.'),
    'sitemap'          => false,
    'tree'             => $page(array(
        $widget('car_detail'),
        $el('section', 'py-5 bg-body-tertiary', lang('Other Cars'), array(
            $container(array(
                $section_header(lang('The fleet'), lang('Other cars to choose from'), lang('All cars'), '{{page:fleet}}'),
                $widget('car_more'),
            )),
        )),
        $shared('booking_band'),
    ), false),
);

// The staff page that adds a car. The form is the Fleet collection; the
// sample cars are written in when the form is made, so the lists do not
// open empty (pg_cf_seed_sample_records()). The rate is the figure of the
// site's base currency, the one the rate label names.
$fleet_samples = array();
foreach ($cars as $key => $c) {
    $fleet_samples[] = array(
        'title'        => $car_title($key),
        'make'         => $c['make'],
        'model'        => $c['model'],
        'class'        => $classes[$c['class']]['title'],
        'transmission' => $transmissions[$c['gearbox']],
        'fuel'         => $fuels[$c['fuel']]['title'],
        'seats'        => (string)$c['seats'],
        'doors'        => (string)$c['doors'],
        'bags'         => (string)$c['bags'],
        'min_age'      => (string)$c['min_age'],
        'rate'         => (string)(isset($c['rate'][$base_currency]) ? $c['rate'][$base_currency] : $c['rate']['USD']),
        'rate_label'   => $car_rate($key),
        'deposit'      => $money($classes[$c['class']]['deposit']),
        'summary'      => $c['summary'],
        'description'  => $c['description'],
        'features'     => implode("\n", $c['features']),
        'photo_1'      => $photo($key, 1200, 800),
        'photo_2'      => $photo($key . '-2', 1200, 800),
        'photo_3'      => $photo($key . '-3', 1200, 800),
        'featured'     => $c['featured'] ? $fleet_yes_no[0] : $fleet_yes_no[1],
        'available'    => $fleet_yes_no[0],
    );
}
$fleet_pages[] = array(
    'key'     => 'car_new',
    'name'    => lang('new-car'),
    'folder'  => 'private',
    'title'   => lang('New Car'),
    'search'  => false,
    'sitemap' => false,
    'noindex' => true,
    'form'    => array(
        'form_name'            => lang('Fleet'),
        'collection'           => 1,
        'confirmation_message' => lang('The car is in the fleet.'),
        'confirmation_page_id' => '{{tab:fleet}}',
        'sample_records'       => $fleet_samples,
        // The first photo is the car's share image (pg_cf_share_image_field_id()).
        'share_image_field'    => 'photo_1',
    ),
    'tree'    => $staff_page(array(
        $page_header(lang('New Car'), lang('Add the car with its photos; it appears in the fleet as soon as you send it. Feature it on the home page or take it out of the fleet from the car\'s Edit button.')),
        $el('section', 'py-5', lang('Content'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $el('div', 'p-4 rounded-3 border', lang('Form Card'), array($widget('car_form'))),
                    ), '', '8'),
                ), '', 'center'),
            )),
        )),
    )),
);

// ── Widget pieces ───────────────────────────────────────────────────────

// A number field of the Fleet form: a number kind, so the lists sort it
// as a number.
$fleet_number = function ($id, $name, $min = '0', $max = '') use ($input, $attr) {
    $more = array($attr('min', $min), $attr('step', '1'));
    if ($max !== '') $more[] = $attr('max', $max);
    return $input($id, 'number', $name, 'off', true, array('kind' => 'number'), $more);
};

// A photo of the car page, in a frame that leaves when the record has
// none.
$fleet_photo = function ($field, $seed) use ($optional_image, $photo) {
    return $optional_image($field, 'title', 'rounded-3 object-fit-cover', 'ratio ratio-3x2', $photo($seed, 1200, 800), lang('Photo'));
};

// The car's details: a row per value, each leaving when the record has
// none.
$fleet_spec = function ($key) use ($el, $span, $heading, $bind, $cars, $classes, $fuels, $transmissions, $money) {
    $s = $cars[$key];
    $line = function ($label, $value, $token, $unit = '') use ($el, $span, $bind) {
        $value_children = array($span($value, '', lang('Value'), $bind($token)));
        if ($unit !== '') $value_children[] = $span(' ' . $unit, 'text-body-secondary', lang('Unit'));
        return $el('div', 'd-flex justify-content-between gap-3 py-2 border-bottom small pg-hide-if-empty', $label, array(
            $span($label, 'text-body-secondary', lang('Label')),
            $el('span', 'fw-semibold text-end', lang('Value'), $value_children),
        ));
    };
    return $el('div', 'mb-4', lang('Car Details'), array(
        $heading('h2', lang('Car Details'), 'h5 fw-semibold mb-2'),
        $el('div', 'row row-cols-1 row-cols-md-2 gx-5', lang('Details List'), array(
            $el('div', 'col', lang('Column'), array(
                $line(lang('Car Class'), $classes[$s['class']]['title'], 'class'),
                $line(lang('Gearbox'), $transmissions[$s['gearbox']], 'transmission'),
                $line(lang('Fuel'), $fuels[$s['fuel']]['title'], 'fuel'),
                $line(lang('Security deposit'), $money($classes[$s['class']]['deposit']), 'deposit'),
            )),
            $el('div', 'col', lang('Column'), array(
                $line(lang('Seats'), (string)$s['seats'], 'seats'),
                $line(lang('Number of doors'), (string)$s['doors'], 'doors'),
                $line(lang('Bags'), (string)$s['bags'], 'bags'),
                $line(lang('Minimum driver age'), (string)$s['min_age'], 'min_age', lang('years')),
            )),
        )),
    ));
};

// Beside the car's words: the rate and the way to book it, the deals and
// the phone.
$fleet_aside = function ($key) use ($el, $para, $link, $span, $icon, $bind, $fleet_page_link, $car_rate, $company) {
    return $el('aside', 'card border-0 shadow-sm rounded-3', lang('Car Actions'), array(
        $el('div', 'card-body p-4', lang('Card Body'), array(
            $para(lang('Daily rate'), 'small text-uppercase fw-semibold text-body-secondary mb-1'),
            $para($car_rate($key), 'pcr-price h3 fw-bold text-primary mb-3', $bind('rate_label')),
            // Opens the booking request with this car chosen (?car=).
            $link(lang('Book this car'), '#', 'btn btn-primary w-100 mb-2', array(),
                $fleet_page_link('book', array('car' => 'title'))),
            $link(lang('See the Deals'), '{{page:deals}}', 'btn btn-outline-primary w-100 mb-2'),
            $link(lang('Call us'), 'tel:' . preg_replace('/[^0-9+]/', '', $company['phone']), 'btn btn-link link-secondary w-100 mb-3'),
            $el('p', 'd-flex gap-2 small text-body-secondary mb-0', lang('Note'), array(
                $icon('bi-shield-check', 'mt-1'),
                $span(lang('Free cancellation up to 48 hours before pick-up.'), '', lang('Text')),
            )),
        )),
    ));
};

$fleet_widgets = array(

    // The staff's Fleet form: one car. Featured starts No, Available Yes;
    // both are the staff's (office use only).
    'car_form' => array(
        'page'   => 'car_new',
        'slug'   => 'form',
        'config' => array('regionType' => 'custom_form', 'form_source' => 'page'),
        'tree'   => $widget_root(array(
            $form_step(1, lang('The car')),
            $field('cr-car-title', lang('Title'), pg_cf_with_role($input('cr-car-title', 'text', 'title', 'off'), 'title'), 'mb-3',
                lang('The make and the model as the cards show them, for example Lumo Five.')),
            $row(array(
                $col(array($field('cr-car-make', lang('Make'), $input('cr-car-make', 'text', 'make', 'off'))), '6'),
                $col(array($field('cr-car-model', lang('Model'), $input('cr-car-model', 'text', 'model', 'off'))), '6'),
                $col(array($field('cr-car-class', lang('Car Class'), pg_cf_with_role($select('cr-car-class', 'class', lang('Choose a car class…'), $class_titles), 'category'))), '4'),
                $col(array($field('cr-car-transmission', lang('Gearbox'), $select('cr-car-transmission', 'transmission', '', $transmission_titles, true, $transmission_titles[0]))), '4'),
                $col(array($field('cr-car-fuel', lang('Fuel'), $select('cr-car-fuel', 'fuel', lang('Choose a fuel…'), $fuel_titles))), '4'),
            ), '3'),
            $form_step(2, lang('Seats, rate and deposit')),
            $row(array(
                $col(array($field('cr-car-seats', lang('Seats'), $fleet_number('cr-car-seats', 'seats', '1', '9'))), '6', '3'),
                $col(array($field('cr-car-doors', lang('Number of doors'), $fleet_number('cr-car-doors', 'doors', '2', '5'))), '6', '3'),
                $col(array($field('cr-car-bags', lang('Bags'), $fleet_number('cr-car-bags', 'bags'))), '6', '3'),
                $col(array($field('cr-car-min-age', lang('Minimum driver age'), $fleet_number('cr-car-min-age', 'min_age', '18', '99'))), '6', '3'),
                $col(array($field('cr-car-rate', lang('Daily rate'), $fleet_number('cr-car-rate', 'rate'), 'mb-3', lang('The lowest rate a day, the number the list sorts on, without the currency.'))), '6'),
                $col(array($field('cr-car-rate-label', lang('Rate as shown'), $input('cr-car-rate-label', 'text', 'rate_label', 'off'), 'mb-3', lang('As the cards show it, for example From $36 a day.'))), '6'),
                $col(array($field('cr-car-deposit', lang('Security deposit'), $input('cr-car-deposit', 'text', 'deposit', 'off'), 'mb-3', lang('The amount held on the card at pick-up, for example $300.'))), '6'),
            ), '3'),
            $form_step(3, lang('Photos and words')),
            $field('cr-car-photo-1', lang('First Photo'), pg_cf_with_role($file('cr-car-photo-1', 'photo_1', 'public', 'image/*'), 'media'), 'mb-3',
                lang('The photo of the cards: the car from the front three-quarter, in daylight.')),
            $row(array(
                $col(array($field('cr-car-photo-2', lang('Second Photo'), $file('cr-car-photo-2', 'photo_2', 'public', 'image/*'))), '6'),
                $col(array($field('cr-car-photo-3', lang('Third Photo'), $file('cr-car-photo-3', 'photo_3', 'public', 'image/*'))), '6'),
            ), '3'),
            $field('cr-car-summary', lang('Summary'), pg_cf_with_role($textarea('cr-car-summary', 'summary', 2), 'description'), 'mb-3',
                lang('One or two sentences under the title on the car\'s page.')),
            $field('cr-car-description', lang('Description'), $textarea('cr-car-description', 'description', 6)),
            $field('cr-car-features', lang('Features'), $textarea('cr-car-features', 'features', 4, false), 'mb-3', lang('One feature on each line.')),
            $form_step(4, lang('Office use')),
            $row(array(
                $col(array($field('cr-car-featured', lang('Featured'), $select('cr-car-featured', 'featured', '', $fleet_yes_no, false, $fleet_yes_no[1], array('office_use_only' => true)),
                    'mb-3', lang('A featured car is shown on the home page.'))), '6'),
                $col(array($field('cr-car-available', lang('Available for rental'), $select('cr-car-available', 'available', '', $fleet_yes_no, true, $fleet_yes_no[0], array('office_use_only' => true)),
                    'mb-3', lang('A car taken out of the fleet leaves the lists; its page stays.'))), '6'),
            ), '3'),
            $submit(lang('Publish')),
            $loop(array()),
        )),
    ),

    // Every available car, lowest rate first, filtered by the address
    // (class, gearbox, fuel), with a search over the words of the cars.
    'fleet_list' => array(
        'page'   => 'fleet',
        'slug'   => lang('form-list'),
        'config' => $fleet_list_config(array(
            'items_per_page' => 12,
            'filters'        => array_merge(array($fleet_available), $fleet_url_filters),
            'search_enabled' => true,
            'search_fields'  => array('title', 'make', 'model', 'class', 'features', 'summary', 'description'),
            'search_width'   => 'full',
            'search_label'   => lang('Search the fleet: a model, a class, a feature...'),
        )),
        'tree'   => $fleet_grid(),
    ),

    // The home page's featured cars, three at most.
    'home_cars' => array(
        'page'   => 'home',
        'slug'   => lang('form-list'),
        'config' => $fleet_list_config(array(
            'items_per_page' => 3,
            'max_results'    => 3,
            'filters'        => array($fleet_filter('featured', 'is equal to', $fleet_yes_no[0]), $fleet_available),
            'empty_message'  => lang('No featured cars yet.'),
        )),
        'tree'   => $fleet_grid(),
    ),

    // The car page's other cars: three available ones but the one shown.
    'car_more' => array(
        'page'   => 'car',
        'slug'   => lang('form-list'),
        'config' => $fleet_list_config(array(
            'items_per_page' => 3,
            'max_results'    => 3,
            'filters'        => array($fleet_filter('reference_code', 'is not equal to', '', 'shown record'), $fleet_available),
            'empty_message'  => lang('More cars are coming to the fleet soon.'),
        )),
        'tree'   => $fleet_grid(),
    ),
);

// One car: the class badge, the title with "or similar", the summary and
// the facts, the photos, the description, the features and the details,
// and beside them the rate with the way to book it.
$fleet_sample_key = 'lumo-five';
$fleet_sample = $cars[$fleet_sample_key];
$fleet_widgets['car_detail'] = array(
    'page'   => 'car',
    'slug'   => lang('form-detail'),
    'config' => array(
        'regionType'          => 'form_item_view',
        'custom_form_page_id' => '{{tab:car_new}}',
        'access_control'      => 'public',
        'not_found_message'   => lang('This car could not be found. It may have left the fleet.'),
    ),
    'tree'   => $widget_root(array(
        $container(array(
            $el('div', 'text-center py-5', lang('Not Found'), array(
                $icon('bi-car-front', 'display-5 text-primary'),
                $heading('h2', lang('Car not found'), 'h3 fw-semibold mt-3'),
                $para(lang('This car could not be found. It may have left the fleet.'), 'text-body-secondary mb-4', $bind('not_found')),
                $link(lang('See all cars'), '{{page:fleet}}', 'btn btn-primary px-4'),
            ), $show_when('record_not_found')),
            $el('article', '', lang('Car'), array(
                $el('div', 'd-flex flex-wrap align-items-center justify-content-between gap-2 mb-3', lang('Toolbar'), array(
                    $el('a', 'd-inline-flex align-items-center gap-1 small fw-semibold link-primary text-decoration-none', lang('Link'), array(
                        $icon('bi-arrow-left'),
                        $span(lang('All Cars'), '', lang('Link Text')),
                    ), array('href' => '{{page:fleet}}')),
                    $edit_button(),
                )),
                $el('div', 'd-flex flex-wrap gap-2 mb-2', lang('Badges'), array(
                    $span($classes[$fleet_sample['class']]['title'], 'badge text-bg-primary', lang('Car Class'), $bind('class')),
                    $span($fuels[$fleet_sample['fuel']]['title'], 'badge bg-primary-subtle text-primary-emphasis', lang('Fuel'), $bind('fuel')),
                )),
                $heading('h1', $car_title($fleet_sample_key), 'pcr-display display-6 fw-semibold mb-0', $bind('title')),
                $para(lang('or similar'), 'text-body-secondary mb-2'),
                $para($fleet_sample['summary'], 'lead text-body-secondary mb-3 pg-hide-if-empty', $bind('summary')),
                $el('div', 'mb-4', lang('Facts'), array($car_facts($fleet_sample_key))),
                // The photos: the first large, the other two under it.
                $row(array(
                    $col(array($fleet_photo('photo_1', $fleet_sample_key))),
                    $col(array($fleet_photo('photo_2', $fleet_sample_key . '-2')), '', '', '', '6'),
                    $col(array($fleet_photo('photo_3', $fleet_sample_key . '-3')), '', '', '', '6'),
                ), '3', '', 'mb-4'),
                $row(array(
                    $col(array(
                        $el('div', 'pg-hide-if-empty mb-4', lang('Description'), array(
                            $heading('h2', lang('About this car'), 'h5 fw-semibold mb-2'),
                            $el('div', 'pg-field-value lh-lg', lang('Value'), array(), array_merge(array('text' => $fleet_sample['description']), $bind('description'))),
                        )),
                        $el('div', 'pg-hide-if-empty p-4 rounded-3 bg-body-tertiary mb-4', lang('Features'), array(
                            $heading('h2', lang('Features'), 'h5 fw-semibold mb-2'),
                            $el('div', 'pg-field-value lh-lg', lang('Value'), array(), array_merge(array('text' => implode("\n", $fleet_sample['features'])), $bind('features'))),
                        )),
                        $fleet_spec($fleet_sample_key),
                        $el('div', 'alert alert-light border d-flex gap-2 small mb-0', lang('Note'), array(
                            $icon('bi-info-circle', 'mt-1'),
                            $span(lang('We promise the class: you get this car or a similar one with the same seats, gearbox and boot. The price of your rental is confirmed with your booking.'), '', lang('Text')),
                        )),
                    ), '', '8'),
                    $col(array($fleet_aside($fleet_sample_key)), '', '4'),
                ), '4', '', 'g-lg-5'),
            ), $show_when('record_found')),
        ), 'py-5'),
    )),
);

return array('pages' => $fleet_pages, 'widgets' => $fleet_widgets);
