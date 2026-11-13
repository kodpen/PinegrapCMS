<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Travel Agency", its destinations and tours: the staff
 * pages that add a destination and a tour (the Destinations and Tours
 * forms, collections, with the sample records written in when they are
 * made), the list of every destination with its region filter, the list of
 * every tour with its destination and style filters, the destination's and
 * the tour's own pages, and the lists the home page places. Included after
 * parts.php in the same scope; returns array('pages' => ..., 'widgets' =>
 * ...).
 *
 * A destination and a tour are records of their forms. Region, destination
 * and style are pick lists whose answers are the words parts.php gives, so
 * a link with ?region=, ?destination= or ?style= filters a list by the
 * words a visitor sees. Days and group size are number fields, so a list
 * can sort on them; the price, the length and the departures are the words
 * the cards show. The tour page is the itinerary page, and its comments are
 * the travellers' reviews, with stars. Featured is the agency's own choice
 * (office use only): the home page shows the featured records.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

$tour_pages = array();

// ── Pieces ──────────────────────────────────────────────────────────────

// A filter row of a list's settings: the field, how it compares and the
// value, or where the value comes from (a URL parameter, left out while
// the parameter is not in the address).
$tv_filter = function ($field, $operator, $value, $param = '') {
    $row = array('field' => $field, 'operator' => $operator, 'value' => $value, 'dynamic_value' => $param !== '' ? 'url parameter' : '');
    if ($param !== '') $row['dynamic_value_attribute'] = $param;
    return $row;
};
// The answers of the Featured list: the first marks a record for the home.
$tv_featured_choices = array(lang('Yes'), lang('No'));

// A link to a template page that sends values of the record it is drawn
// in: $params maps the address name to the field it takes (the renderer's
// __page_link).
$tv_page_link = function ($page_key, $params) {
    $list = array();
    foreach ($params as $name => $token) $list[] = array('name' => $name, 'token' => $token);
    return array(
        '_bindings'   => array('href' => '__page_link'),
        '_bindParams' => array('href' => array('page_id' => '{{tab:' . $page_key . '}}', 'params' => $list)),
    );
};

// A list's settings: the form, its record page, the order and the words
// shown when nothing matches.
$tv_list_config = function ($form_page, $detail_page, $order_field, $empty, $more) {
    return array_merge(array(
        'regionType'          => 'form_list_view',
        'custom_form_page_id' => '{{tab:' . $form_page . '}}',
        'detail_page_id'      => '{{tab:' . $detail_page . '}}',
        'order_by_field'      => $order_field,
        'order_by_direction'  => 'ASC',
        'empty_message'       => $empty,
    ), $more);
};
// The cards of a list, in rows of three.
$tour_grid = function () use ($widget_root, $row, $loop, $tour_card) {
    return $widget_root(array($row(array($loop(array($tour_card()))), '4')));
};
$destination_grid = function () use ($widget_root, $row, $loop, $destination_card) {
    return $widget_root(array($row(array($loop(array($destination_card()))), '4')));
};

// The links above a list that narrow it: one per answer of each pick
// list, each opening the list with the filter set.
$tv_filter_bar = function ($page_key, $groups) use ($el, $link, $span, $query) {
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
$tv_number = function ($id, $name, $min) use ($input, $attr) {
    return $input($id, 'number', $name, 'off', true, array('kind' => 'number'), array($attr('min', (string)$min), $attr('step', '1')));
};

// A photo of a record page, in a frame that leaves when the record has
// none.
$tv_photo = function ($field, $seed, $ratio = '4x3') use ($optional_image, $photo) {
    return $optional_image($field, 'name', 'rounded-4 object-fit-cover', 'ratio ratio-' . $ratio, $photo($seed, 1200, $ratio === '16x9' ? 675 : 900), lang('Photo'));
};

// A long field of a record page under its heading, its lines kept; the
// block leaves when the record has no value.
$tv_long_field = function ($title, $token, $sample, $class = 'mb-4') use ($el, $heading, $bind) {
    return $el('div', 'pg-hide-if-empty ' . $class, $title, array(
        $heading('h2', $title, 'ptv-display h4 mb-2'),
        $el('div', 'pg-field-value lh-lg', lang('Value'), array(), array_merge(array('text' => $sample), $bind($token))),
    ));
};

// What a destination page says about the place beyond its summary.
$destination_texts = array(
    'cappadocia' => lang('Wind and water carved the soft rock of central Anatolia into cones, valleys and caves where people have lived for thousands of years. Walk the valleys between the chimneys, sleep in a cave room and watch the sky fill with balloons at dawn.'),
    'amalfi'     => lang('Villages of white and pastel houses cling to the cliffs above a deep blue sea. Paths link them high above the water, boats link them below, and every terrace has lemons, a view and a long lunch.'),
    'kyoto'      => lang('For a thousand years the capital of Japan, Kyoto keeps more than a thousand temples, the wooden streets of Gion and gardens raked every morning. Come early and the city is yours.'),
    'bali'       => lang('Green rice terraces climb the hills around Ubud, temples stand in the water and the island\'s volcanoes rise over the clouds. The south coast has the beaches and the reefs.'),
    'marrakech'  => lang('The red city of Morocco is a maze of souks and gardens behind old walls. Beyond it the High Atlas has Berber villages in its valleys, and beyond the mountains the dunes of the Sahara begin.'),
    'patagonia'  => lang('At the southern end of the Andes, granite towers rise over lakes and glaciers flow down into the steppe. The trails start at the edge of small towns and the wind comes with them.'),
);

// ── Pages ───────────────────────────────────────────────────────────────

// Every destination, narrowed by region from the address.
$tour_pages[] = array(
    'key'              => 'destinations',
    'name'             => lang('destinations'),
    'folder'           => 'public',
    'title'            => lang('Destinations'),
    'meta_description' => lang('The places we travel on four continents, with the best time to go and the tours that visit them.'),
    'tree'             => $page(array(
        $page_header(lang('Destinations'), lang('Places we know well, on four continents. Choose a region to narrow the list.')),
        $el('section', 'py-5', lang('Destinations'), array(
            $container(array(
                $tv_filter_bar('destinations', array(array('label' => lang('Region'), 'param' => 'region', 'values' => $region_titles))),
                $widget('destinations_list'),
            )),
        )),
    )),
);

// One destination: the cover, the region, the name, the country, the best
// time to go, what it is like and the way to its tours.
$tour_pages[] = array(
    'key'              => 'destination',
    'name'             => lang('destination'),
    'folder'           => 'public',
    'title'            => lang('Travel Destination'),
    'meta_description' => lang('A destination of the agency: what it is like, the best time to go and the tours that visit it.'),
    'sitemap'          => false,
    'tree'             => $page(array(
        $widget('destination_detail'),
    )),
);

// Every tour, narrowed by destination and style from the address.
$tour_pages[] = array(
    'key'              => 'tours',
    'name'             => lang('tours'),
    'folder'           => 'public',
    'title'            => lang('Tours'),
    'meta_description' => lang('Small-group tours with an itinerary for every day, departures, the group size and the price per traveller.'),
    'tree'             => $page(array(
        $page_header(lang('Tours'), lang('Every tour with its days, its departures and its price. Choose a destination or a style to narrow the list.')),
        $el('section', 'py-5', lang('Tours'), array(
            $container(array(
                $tv_filter_bar('tours', array(
                    array('label' => lang('Tour Destination'), 'param' => 'destination', 'values' => $destination_names),
                    array('label' => lang('Travel Style'), 'param' => 'style', 'values' => $style_titles),
                )),
                $widget('tours_list'),
            )),
        )),
    )),
);

// One tour, its itinerary page: the tour's record and under it the
// travellers' reviews (stars and words, newest first), which the agency
// is told about.
$tour_pages[] = array(
    'key'              => 'tour',
    'name'             => lang('tour'),
    'folder'           => 'public',
    'title'            => lang('Tour'),
    'meta_description' => lang('A tour of the agency: the itinerary day by day, what it includes, departures, price and reviews.'),
    'sitemap'          => false,
    'comments'         => array('label' => lang('Review'), 'allow_new' => true, 'rating' => true, 'auto_publish' => true, 'show_date' => true, 'login' => true,
                                'email_page' => '{{tab:email_reply}}', 'email_subject' => lang('There is a new review of a tour'),
                                'notify_email' => '{{site_email}}'),
    'tree'             => $page(array(
        $widget('tour_detail'),
        $comments_section(lang('Reviews'), 'tour_reviews'),
    )),
);

// The staff page that adds a destination. The form is the Destinations
// collection; the sample destinations are written in when the form is
// made, so the lists do not open empty (pg_cf_seed_sample_records()).
$sample_destinations = array();
foreach ($destinations as $key => $d) {
    $sample_destinations[] = array(
        'name'        => $d['name'],
        'country'     => $d['country'],
        'region'      => $regions[$d['region']]['title'],
        'season'      => $d['season'],
        'summary'     => $d['summary'],
        'description' => $destination_texts[$key],
        'cover'       => $photo('place-' . $key, 1200, 675),
        'featured'    => $d['featured'] ? $tv_featured_choices[0] : $tv_featured_choices[1],
    );
}
$tour_pages[] = array(
    'key'     => 'destination_new',
    'name'    => lang('new-destination'),
    'folder'  => 'private',
    'title'   => lang('New Destination'),
    'search'  => false,
    'sitemap' => false,
    'noindex' => true,
    'form'    => array(
        'form_name'            => lang('Destinations'),
        'collection'           => 1,
        'confirmation_message' => lang('The destination is published.'),
        'confirmation_page_id' => '{{tab:destinations}}',
        'sample_records'       => $sample_destinations,
        // The cover is the destination's share image (pg_cf_share_image_field_id()).
        'share_image_field'    => 'cover',
    ),
    'tree'    => $staff_page(array(
        $page_header(lang('New Destination'), lang('Add the place with its cover photo; it appears in the list as soon as you send it. Mark it featured from its Edit button.')),
        $el('section', 'py-5', lang('Content'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $el('div', 'p-4 rounded-4 border', lang('Form Card'), array($widget('destination_form'))),
                    ), '', '8'),
                ), '', 'center'),
            )),
        )),
    )),
);

// The staff page that adds a tour. The form is the Tours collection; the
// sample tours are written in when the form is made.
$sample_tours = array();
foreach ($tours as $key => $t) {
    $sample_tours[] = array(
        'name'        => $t['name'],
        'destination' => $destinations[$t['destination']]['name'],
        'style'       => $styles[$t['style']]['title'],
        'days'        => (string)$t['days'],
        'group_size'  => (string)$t['group'],
        'length'      => $length_text($key),
        'departures'  => $t['departures'],
        'price'       => $price_text($key),
        'summary'     => $t['summary'],
        'highlights'  => implode("\n", $t['highlights']),
        'itinerary'   => $itinerary_text($key),
        'photo_1'     => $photo('tour-' . $key, 1200, 900),
        'photo_2'     => $photo('tour-' . $key . '-2', 1200, 900),
        'photo_3'     => $photo('tour-' . $key . '-3', 1200, 900),
        'featured'    => $t['featured'] ? $tv_featured_choices[0] : $tv_featured_choices[1],
    );
}
$tour_pages[] = array(
    'key'     => 'tour_new',
    'name'    => lang('new-tour'),
    'folder'  => 'private',
    'title'   => lang('New Tour'),
    'search'  => false,
    'sitemap' => false,
    'noindex' => true,
    'form'    => array(
        'form_name'            => lang('Tours'),
        'collection'           => 1,
        'confirmation_message' => lang('The tour is published.'),
        'confirmation_page_id' => '{{tab:tours}}',
        'sample_records'       => $sample_tours,
        'share_image_field'    => 'photo_1',
    ),
    'tree'    => $staff_page(array(
        $page_header(lang('New Tour'), lang('Add the tour with its itinerary, price and photos; it appears in the list as soon as you send it. Mark it featured from its Edit button.')),
        $el('section', 'py-5', lang('Content'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $el('div', 'p-4 rounded-4 border', lang('Form Card'), array($widget('tour_form'))),
                    ), '', '8'),
                ), '', 'center'),
            )),
        )),
    )),
);

// ── Widget pieces ───────────────────────────────────────────────────────

// Beside the tour's words, kept in view while the itinerary scrolls: the
// trip at a glance in the Travel (Day by Day) block's list, the price, the
// way to book this tour or ask for it to be changed, and the deposit.
$tour_aside = function ($sample_key) use ($el, $heading, $para, $link, $span, $icon, $bind, $tv_page_link, $price_text, $length_text, $tours, $money, $deposit) {
    $t = $tours[$sample_key];
    $glance = function ($label, $children) use ($el) {
        return $el('div', '', $label, array(
            $el('dt', '', lang('Label'), array(), array('text' => $label)),
            $el('dd', 'd-flex flex-wrap gap-1', lang('Value'), $children),
        ));
    };
    return $el('aside', 'card border-0 shadow-sm rounded-4 sticky-lg-top', lang('Trip at a Glance'), array(
        $el('div', 'card-body p-4', lang('Card Body'), array(
            $heading('h2', lang('Trip at a glance'), 'ptv-display h5 mb-3'),
            $el('dl', 'pg-travel-glance mb-4', lang('Facts'), array(
                $glance(lang('Duration'), array($span($length_text($sample_key), '', lang('Length'), $bind('length')))),
                $glance(lang('Group size'), array(
                    $span(lang('Up to'), '', lang('Label')),
                    $span((string)$t['group'], '', lang('Value'), $bind('group_size')),
                    $span(lang('travellers'), '', lang('Unit')),
                )),
                $glance(lang('Departures'), array($span($t['departures'], '', lang('Departures'), $bind('departures')))),
            )),
            $para(lang('Per traveller, sharing a double room'), 'small text-uppercase fw-semibold text-body-secondary mb-2'),
            $para($price_text($sample_key), 'pg-travel-figure fs-4 fw-bold mb-4', $bind('price')),
            // Opens the booking request with this tour chosen (?tour=).
            $link(lang('Book this tour'), '#', 'btn btn-primary rounded-pill w-100 mb-2', array(),
                $tv_page_link('book', array('tour' => 'name'))),
            $link(lang('Change it to suit you'), '{{page:enquiry}}', 'btn btn-outline-primary rounded-pill w-100 mb-3'),
            $el('p', 'd-flex gap-2 small text-body-secondary mb-0', lang('Note'), array(
                $icon('bi-info-circle', 'mt-1'),
                $span(sprintf(lang('A request is not yet a booking: we confirm your places within a day, then a deposit of %s per traveller holds them.'), $money($deposit)), '', lang('Text')),
            )),
        )),
    ));
};

$tour_widgets = array(

    // The staff's Destinations form: one place. Featured starts as No.
    'destination_form' => array(
        'page'   => 'destination_new',
        'slug'   => 'form',
        'config' => array('regionType' => 'custom_form', 'form_source' => 'page'),
        'tree'   => $widget_root(array(
            $form_step(1, lang('The place')),
            $field('tv-dest-name', lang('Name'), pg_cf_with_role($input('tv-dest-name', 'text', 'name', 'off'), 'title')),
            $row(array(
                $col(array($field('tv-dest-country', lang('Country'), $input('tv-dest-country', 'text', 'country', 'off'))), '6'),
                $col(array($field('tv-dest-region', lang('Region'), pg_cf_with_role($select('tv-dest-region', 'region', lang('Choose a region…'), $region_titles), 'category'))), '6'),
            ), '3'),
            $field('tv-dest-season', lang('Best time to go'), $input('tv-dest-season', 'text', 'season', 'off'), 'mb-3',
                lang('In words, never a date, for example Best in spring and autumn.')),
            $form_step(2, lang('Words')),
            $field('tv-dest-summary', lang('Summary'), pg_cf_with_role($textarea('tv-dest-summary', 'summary', 2), 'description'), 'mb-3',
                lang('One line for the cards of the lists.')),
            $field('tv-dest-description', lang('Description'), $textarea('tv-dest-description', 'description', 5, false)),
            $form_step(3, lang('Photo')),
            $field('tv-dest-cover', lang('Cover Photo'), pg_cf_with_role($file('tv-dest-cover', 'cover', 'public', 'image/*'), 'media'), 'mb-3',
                lang('The photo of the cards and the share image.')),
            $field('tv-dest-featured', lang('Featured'), $select('tv-dest-featured', 'featured', '', $tv_featured_choices, false, $tv_featured_choices[1], array('office_use_only' => true)),
                'mb-3', lang('A featured destination is shown on the home page.')),
            $submit(lang('Publish')),
            $loop(array()),
        )),
    ),

    // The staff's Tours form: one tour. Featured starts as No.
    'tour_form' => array(
        'page'   => 'tour_new',
        'slug'   => 'form',
        'config' => array('regionType' => 'custom_form', 'form_source' => 'page'),
        'tree'   => $widget_root(array(
            $form_step(1, lang('The tour')),
            $field('tv-tour-name', lang('Name'), pg_cf_with_role($input('tv-tour-name', 'text', 'name', 'off'), 'title')),
            $row(array(
                $col(array($field('tv-tour-destination', lang('Tour Destination'), $select('tv-tour-destination', 'destination', lang('Choose a destination…'), $destination_names))), '6'),
                $col(array($field('tv-tour-style', lang('Travel Style'), pg_cf_with_role($select('tv-tour-style', 'style', lang('Choose a style…'), $style_titles), 'category'))), '6'),
                $col(array($field('tv-tour-days', lang('Days'), $tv_number('tv-tour-days', 'days', 1))), '6'),
                $col(array($field('tv-tour-group', lang('Group Size'), $tv_number('tv-tour-group', 'group_size', 1), 'mb-3', lang('The most travellers on one departure.'))), '6'),
            ), '3'),
            $field('tv-tour-length', lang('Length'), $input('tv-tour-length', 'text', 'length', 'off'), 'mb-3',
                sprintf(lang('As the cards show it, for example %s.'), $length_text('cappadocia'))),
            $field('tv-tour-departures', lang('Departures'), $input('tv-tour-departures', 'text', 'departures', 'off'), 'mb-3',
                lang('In words, never a date, for example Every Saturday.')),
            $field('tv-tour-price', lang('Price'), $input('tv-tour-price', 'text', 'price', 'off'), 'mb-3',
                sprintf(lang('As the cards show it, for example %s.'), $price_text('cappadocia'))),
            $form_step(2, lang('Words')),
            $field('tv-tour-summary', lang('Summary'), pg_cf_with_role($textarea('tv-tour-summary', 'summary', 2), 'description'), 'mb-3',
                lang('Two lines for the cards of the lists.')),
            $field('tv-tour-highlights', lang('Highlights'), $textarea('tv-tour-highlights', 'highlights', 4, false), 'mb-3', lang('One highlight on each line.')),
            $field('tv-tour-itinerary', lang('Itinerary'), $textarea('tv-tour-itinerary', 'itinerary', 8), 'mb-3',
                lang('One day on each line, for example Day 1 — Arrival: transfer and a welcome dinner.')),
            $form_step(3, lang('Photos')),
            $field('tv-tour-photo-1', lang('First Photo'), pg_cf_with_role($file('tv-tour-photo-1', 'photo_1', 'public', 'image/*'), 'media'), 'mb-3',
                lang('The photo of the cards and the share image.')),
            $row(array(
                $col(array($field('tv-tour-photo-2', lang('Second Photo'), $file('tv-tour-photo-2', 'photo_2', 'public', 'image/*'))), '6'),
                $col(array($field('tv-tour-photo-3', lang('Third Photo'), $file('tv-tour-photo-3', 'photo_3', 'public', 'image/*'))), '6'),
            ), '3'),
            $field('tv-tour-featured', lang('Featured'), $select('tv-tour-featured', 'featured', '', $tv_featured_choices, false, $tv_featured_choices[1], array('office_use_only' => true)),
                'mb-3', lang('A featured tour is shown on the home page.')),
            $submit(lang('Publish')),
            $loop(array()),
        )),
    ),

    // Every destination, filtered by the address (region), A to Z.
    'destinations_list' => array(
        'page'   => 'destinations',
        'slug'   => lang('form-list'),
        'config' => $tv_list_config('destination_new', 'destination', 'name',
            lang('No destination in this region yet. Ask us: we plan journeys beyond our tours too.'), array(
            'items_per_page' => 12,
            'filters'        => array($tv_filter('region', 'is equal to', '', 'region')),
        )),
        'tree'   => $destination_grid(),
    ),

    // The home page's featured destinations, three at most.
    'home_destinations' => array(
        'page'   => 'home',
        'slug'   => lang('form-list'),
        'config' => $tv_list_config('destination_new', 'destination', 'name', lang('No featured destinations yet.'), array(
            'items_per_page' => 3,
            'max_results'    => 3,
            'filters'        => array($tv_filter('featured', 'is equal to', $tv_featured_choices[0])),
        )),
        'tree'   => $destination_grid(),
    ),

    // Every tour, filtered by the address (destination, style), shortest
    // first, with a search over the words of the tours.
    'tours_list' => array(
        'page'   => 'tours',
        'slug'   => lang('form-list'),
        'config' => $tv_list_config('tour_new', 'tour', 'days',
            lang('No tour matches these choices. Try another destination or style, or ask us for a tailor-made journey.'), array(
            'items_per_page' => 12,
            'filters'        => array(
                $tv_filter('destination', 'is equal to', '', 'destination'),
                $tv_filter('style', 'is equal to', '', 'style'),
            ),
            'search_enabled' => true,
            'search_fields'  => array('name', 'destination', 'style', 'summary', 'highlights', 'itinerary'),
            'search_width'   => 'full',
            'search_label'   => lang('Search the tours: a glacier, a cooking class, a night in the desert...'),
        )),
        'tree'   => $tour_grid(),
    ),

    // The home page's featured tours, three at most.
    'home_tours' => array(
        'page'   => 'home',
        'slug'   => lang('form-list'),
        'config' => $tv_list_config('tour_new', 'tour', 'days', lang('No featured tours yet.'), array(
            'items_per_page' => 3,
            'max_results'    => 3,
            'filters'        => array($tv_filter('featured', 'is equal to', $tv_featured_choices[0])),
        )),
        'tree'   => $tour_grid(),
    ),

    // The reviews under a tour: stars and words, newest first, one level of
    // answers so the agency can reply to a review.
    'tour_reviews' => array(
        'page'   => 'tour',
        'slug'   => lang('reviews'),
        'config' => array(
            'regionType' => 'comments',
            'order'      => 'newest',
            'featured'   => 'none',
            'layout'     => 'threaded',
            'max_depth'  => 1,
            'per_page'   => 10,
        ),
        'tree'   => $comment_thread(array(
            'title'     => lang('Reviews'),
            'empty'     => lang('No reviews of this tour yet. Travelled with us? Tell others how it was.'),
            'heading'   => lang('Review this tour'),
            'label'     => lang('Your review'),
            'sign_in'   => lang('Sign in to write a review.'),
            'submit'    => lang('Submit review'),
            'watch_box' => lang('Notify me when a new review is added.'),
            'follow'    => lang('Notify me of new reviews'),
            'unfollow'  => lang('Stop notifying me'),
            'close'     => lang('Stop new reviews'),
            'reopen'    => lang('Allow new reviews'),
            'reply'     => true,
            'watch'     => true,
        )),
    ),
);

// The record pages' back link and Edit button, above the record.
$tv_toolbar = function ($page_key, $text) use ($el, $icon, $span, $edit_button) {
    return $el('div', 'd-flex flex-wrap align-items-center justify-content-between gap-2 mb-3', lang('Toolbar'), array(
        $el('a', 'd-inline-flex align-items-center gap-1 small fw-semibold link-primary text-decoration-none', lang('Link'), array(
            $icon('bi-arrow-left'),
            $span($text, '', lang('Link Text')),
        ), array('href' => '{{page:' . $page_key . '}}')),
        $edit_button(),
    ));
};
// What a record page shows when its record is not there.
$tv_not_found = function ($icon_name, $title, $text, $back_text, $back_page) use ($el, $icon, $heading, $para, $link, $bind, $show_when) {
    return $el('div', 'text-center py-5', lang('Not Found'), array(
        $icon($icon_name, 'display-5 text-body-tertiary'),
        $heading('h2', $title, 'h3 fw-semibold mt-3'),
        $para($text, 'text-body-secondary mb-4', $bind('not_found')),
        $link($back_text, '{{page:' . $back_page . '}}', 'btn btn-primary rounded-pill px-4'),
    ), $show_when('record_not_found'));
};

// One destination, drawn as the Travel (Destination Guide) block: the
// cover beside the country, the name, the summary, the best time to go and
// the region, and the way to its tours; under them what the place is like.
// The cover fades in and the words rise as the page opens.
$dest_sample_key = 'cappadocia';
$dest_sample = $destinations[$dest_sample_key];
$dest_not_found = lang('This destination could not be found. It may have been renamed or taken off the list.');
$dest_glance = function ($label, $value, $token) use ($el, $span, $bind) {
    return $el('div', '', $label, array(
        $el('dt', '', lang('Label'), array(), array('text' => $label)),
        $el('dd', '', lang('Value'), array($span($value, 'fw-semibold', $label, $bind($token)))),
    ));
};
$tour_widgets['destination_detail'] = array(
    'page'   => 'destination',
    'slug'   => lang('form-detail'),
    'config' => array(
        'regionType'          => 'form_item_view',
        'custom_form_page_id' => '{{tab:destination_new}}',
        'access_control'      => 'public',
        'not_found_message'   => $dest_not_found,
    ),
    'tree'   => $widget_root(array(
        $container(array(
            $tv_not_found('bi-geo', lang('Destination not found'), $dest_not_found, lang('See the destinations'), 'destinations'),
            $el('article', '', lang('Travel Destination'), array(
                $tv_toolbar('destinations', lang('All Destinations')),
                $row(array(
                    $col(array(
                        $fx($el('div', '', lang('Cover'), array($tv_photo('cover', 'place-' . $dest_sample_key, '4x3'))),
                            array('effect' => 'fade', 'trigger' => 'load', 'duration' => 900)),
                    ), '', '6'),
                    $col(array(
                        $fx($el('div', '', lang('Text'), array(
                            $para($dest_sample['country'], 'ptv-eyebrow text-uppercase fw-semibold text-primary mb-2', $bind('country')),
                            $heading('h1', $dest_sample['name'], 'ptv-display display-5 mb-3', $bind('name')),
                            $para($dest_sample['summary'], 'lead text-body-secondary mb-4', $bind('summary')),
                            $el('dl', 'pg-travel-glance row row-cols-1 row-cols-sm-2 g-0 mb-4', lang('Facts'), array(
                                $dest_glance(lang('Best time to go'), $dest_sample['season'], 'season'),
                                $dest_glance(lang('Region'), $regions[$dest_sample['region']]['title'], 'region'),
                            )),
                            $el('div', 'd-flex flex-wrap gap-2', lang('Buttons'), array(
                                // Opens the tour list filtered on this
                                // destination (?destination=).
                                $link(lang('See the tours here'), '#', 'btn btn-primary btn-lg rounded-pill', array(),
                                    $tv_page_link('tours', array('destination' => 'name'))),
                                $link(lang('Plan a Tailor-made Trip'), '{{page:enquiry}}', 'btn btn-outline-primary btn-lg rounded-pill'),
                            )),
                        )), array('effect' => 'slide-up', 'trigger' => 'load', 'duration' => 800, 'delay' => 150, 'stagger' => 80)),
                    ), '', '6'),
                ), '4', '', 'g-lg-5 align-items-center mb-5'),
                $fx($row(array(
                    $col(array(
                        $tv_long_field(lang('About the place'), 'description', $destination_texts[$dest_sample_key]),
                    ), '', '8'),
                ), '4'), $fade_in),
            ), $show_when('record_found')),
        ), 'py-5'),
    )),
);

// One tour, its itinerary page: the style and destination, the name, the
// summary and the facts rise as the page opens, the photos fade in, then the
// highlights, the itinerary, what every tour includes and leaves out come
// in as they scroll into view, beside the trip at a glance with the price
// and the way to book it.
$tour_sample_key = 'cappadocia';
$tour_sample = $tours[$tour_sample_key];
$tour_not_found = lang('This tour could not be found. It may have been renamed or taken off sale.');
$tour_widgets['tour_detail'] = array(
    'page'   => 'tour',
    'slug'   => lang('form-detail'),
    'config' => array(
        'regionType'          => 'form_item_view',
        'custom_form_page_id' => '{{tab:tour_new}}',
        'access_control'      => 'public',
        'not_found_message'   => $tour_not_found,
    ),
    'tree'   => $widget_root(array(
        $container(array(
            $tv_not_found('bi-signpost', lang('Tour not found'), $tour_not_found, lang('See the tours'), 'tours'),
            $el('article', '', lang('Tour'), array(
                $tv_toolbar('tours', lang('All Tours')),
                $fx($el('header', 'col-lg-9 mb-4', lang('Section Header'), array(
                    $el('div', 'd-flex flex-wrap gap-2 mb-3', lang('Badges'), array(
                        $span($styles[$tour_sample['style']]['title'], 'badge rounded-pill bg-primary-subtle text-primary-emphasis', lang('Travel Style'), $bind('style')),
                        $span($destinations[$tour_sample['destination']]['name'], 'badge rounded-pill bg-body text-body-emphasis border', lang('Tour Destination'), $bind('destination')),
                    )),
                    $heading('h1', $tour_sample['name'], 'ptv-display display-5 mb-3', $bind('name')),
                    $para($tour_sample['summary'], 'lead text-body-secondary mb-3', $bind('summary')),
                    $tour_facts($tour_sample_key, 'd-flex flex-wrap gap-3 text-body-secondary mb-0'),
                )), array('effect' => 'slide-up', 'trigger' => 'load', 'duration' => 800, 'stagger' => 90)),
                // The photos: the first large, the other two beside it.
                $fx($row(array(
                    $col(array($tv_photo('photo_1', 'tour-' . $tour_sample_key)), '', '8'),
                    $col(array(
                        $el('div', 'd-flex flex-column gap-3 h-100', lang('Photos'), array(
                            $tv_photo('photo_2', 'tour-' . $tour_sample_key . '-2'),
                            $tv_photo('photo_3', 'tour-' . $tour_sample_key . '-3'),
                        )),
                    ), '', '4'),
                ), '3', '', 'mb-5'), array('effect' => 'fade', 'trigger' => 'load', 'duration' => 900, 'delay' => 200, 'stagger' => 120)),
                $row(array(
                    $col(array(
                        $fx($el('div', 'pg-hide-if-empty p-4 rounded-4 bg-body-tertiary mb-4', lang('Highlights'), array(
                            $heading('h2', lang('Highlights'), 'ptv-display h5 mb-2'),
                            $el('div', 'pg-field-value lh-lg', lang('Value'), array(), array_merge(array('text' => implode("\n", $tour_sample['highlights'])), $bind('highlights'))),
                        )), $fade_in),
                        $fx($tv_long_field(lang('Itinerary'), 'itinerary', $itinerary_text($tour_sample_key)), $fade_in),
                        $rise($row(array(
                            $col(array(
                                $heading('h2', lang('Every tour includes'), 'ptv-display h5 mb-2'),
                                $check_list($included, 'list-unstyled small mb-0'),
                            ), '6'),
                            $col(array(
                                $heading('h2', lang('Not included'), 'ptv-display h5 mb-2'),
                                $check_list($not_included, 'list-unstyled small mb-0'),
                            ), '6'),
                        ), '4', '', 'mb-2'), 120),
                    ), '', '8'),
                    $col(array($tour_aside($tour_sample_key)), '', '4'),
                ), '4', '', 'g-lg-5 align-items-start mb-4'),
            ), $show_when('record_found')),
        ), 'py-5'),
    )),
);

return array('pages' => $tour_pages, 'widgets' => $tour_widgets);
