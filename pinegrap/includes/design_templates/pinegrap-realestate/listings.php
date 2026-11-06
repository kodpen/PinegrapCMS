<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Real Estate Agency", its listings: the staff page that
 * adds a listing (the Listings form, a collection, with the sample
 * listings written in when it is made), the lists of homes for sale, to
 * rent and of every listing with their filters, the listing's own page,
 * and the lists the home and the neighbourhood pages place. Included
 * after parts.php in the same scope; returns array('pages' => ...,
 * 'widgets' => ...).
 *
 * A listing is a record of the Listings form. Its deal, type,
 * neighbourhood, status and agent are pick lists whose answers are the
 * words parts.php gives, so a link with ?deal=, ?type= or ?area= filters
 * the lists by the words a visitor sees. Price, bedrooms, bathrooms and
 * size are number fields, so a list can sort on them; the price label is
 * the words the cards show ("$1,500 a month"). The photos are image
 * fields kept in the public folder.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

$listing_pages = array();

// ── Pieces ──────────────────────────────────────────────────────────────

// A filter row of a list's settings: the field, how it compares and the
// value, or where the value comes from (a URL parameter).
$filter = function ($field, $operator, $value, $dynamic = '', $attribute = null) {
    $row = array('field' => $field, 'operator' => $operator, 'value' => $value, 'dynamic_value' => $dynamic);
    if ($attribute !== null) $row['dynamic_value_attribute'] = $attribute;
    return $row;
};
// The filters a visitor sets with a link: each is left out while its
// parameter is not in the address.
$url_filters = function ($params) use ($filter) {
    $rows = array();
    foreach ($params as $field => $param) $rows[] = $filter($field, 'is equal to', '', 'url parameter', $param);
    return $rows;
};
// A home still on the market.
$on_market = $filter('status', 'is equal to', $listing_statuses[0]);
// A deal not yet closed: sold and rented homes leave the lists a visitor
// browses, a home under offer stays with its badge.
$not_closed = array(
    $filter('status', 'is not equal to', $listing_statuses[2]),
    $filter('status', 'is not equal to', $listing_statuses[3]),
);

// A link to a template page that sends values of the record it is drawn
// in: $params maps the address name to the field it takes (the renderer's
// __page_link).
$page_link = function ($page_key, $params) {
    $list = array();
    foreach ($params as $name => $token) $list[] = array('name' => $name, 'token' => $token);
    return array(
        '_bindings'   => array('href' => '__page_link'),
        '_bindParams' => array('href' => array('page_id' => '{{tab:' . $page_key . '}}', 'params' => $list)),
    );
};

// A list's settings: the Listings form, the listing page, newest first.
$list_config = function ($more) {
    return array_merge(array(
        'regionType'          => 'form_list_view',
        'custom_form_page_id' => '{{tab:listing_new}}',
        'detail_page_id'      => '{{tab:listing}}',
        'order_by_field'      => 'submitted_date_and_time',
        'order_by_direction'  => 'DESC',
        'empty_message'       => lang('No homes match these choices right now. Ask us: new homes come in every week.'),
    ), $more);
};
// The cards of a list, in rows of three.
$card_grid = function () use ($widget_root, $row, $loop, $listing_card) {
    return $widget_root(array($row(array($loop(array($listing_card()))), '4')));
};

// The links above a list that narrow it: one per type and one per
// neighbourhood, each opening the list page named with the filter set.
$filter_bar = function ($page_key) use ($el, $link, $span, $query, $property_types, $areas) {
    $chips = function ($label, $param, $values) use ($el, $link, $span, $query, $page_key) {
        $children = array($span($label, 'small fw-semibold text-body-secondary me-1', lang('Label')));
        $children[] = $link(lang('All'), '{{page:' . $page_key . '}}', 'btn btn-sm btn-outline-secondary rounded-pill');
        foreach ($values as $v) {
            $children[] = $link($v, '{{page:' . $page_key . '}}?' . $query(array($param => $v)), 'btn btn-sm btn-outline-secondary rounded-pill');
        }
        return $el('div', 'd-flex flex-wrap align-items-center gap-2 mb-2', $label, $children);
    };
    $types = array();
    foreach ($property_types as $t) $types[] = $t['title'];
    $places = array();
    foreach ($areas as $a) $places[] = $a['title'];
    return $el('nav', 'mb-4', lang('Filters'), array(
        $chips(lang('Type'), 'type', $types),
        $chips(lang('Neighbourhood'), 'area', $places),
    ));
};

// A list page: the header, the filter links, the list and the band.
$list_page = function ($key, $name, $title, $lead, $description, $widget_key) use ($page, $page_header, $el, $container, $widget, $shared, $filter_bar) {
    return array(
        'key'              => $key,
        'name'             => $name,
        'folder'           => 'public',
        'title'            => $title,
        'meta_description' => $description,
        'tree'             => $page(array(
            $page_header($title, $lead),
            $el('section', 'py-5', lang('Listings'), array(
                $container(array($filter_bar($key), $widget($widget_key))),
            )),
            $shared('valuation_band'),
        )),
    );
};

// ── Pages ───────────────────────────────────────────────────────────────

$listing_pages[] = $list_page('buy', lang('homes-for-sale'), lang('Homes for Sale'),
    lang('Flats, houses and villas for sale, each one visited, measured and checked by our agents.'),
    lang('Homes for sale in Seafront, the Old Town, Parkside, Hillside and the Business District, with photos, prices and the agent to call.'),
    'buy_list');
$listing_pages[] = $list_page('rent', lang('homes-to-rent'), lang('Homes to Rent'),
    lang('Flats, houses and offices to rent, with the monthly rent and what it includes.'),
    lang('Homes and offices to rent in the city\'s best neighbourhoods, with the monthly rent, photos and the agent to call.'),
    'rent_list');
$listing_pages[] = $list_page('listings', lang('listings'), lang('All Listings'),
    lang('Every home we have for sale or to rent. Choose a type or a neighbourhood to narrow the list.'),
    lang('Every home the agency has for sale or to rent, by type and neighbourhood.'),
    'listings_list');

// One listing: the photos, the price, the facts, the features, the
// description and the agent, with the way to ask for a viewing.
$listing_pages[] = array(
    'key'              => 'listing',
    'name'             => lang('listing'),
    'folder'           => 'public',
    'title'            => lang('Listing'),
    'meta_description' => lang('A home for sale or to rent: photos, price, rooms, features and the agent to call.'),
    'sitemap'          => false,
    'tree'             => $page(array(
        $widget('listing_detail'),
        $shared('valuation_band'),
    ), false),
);

// The staff page that adds a listing. The form is the Listings collection;
// the sample listings are written in when the form is made, so the lists
// do not open empty (pg_cf_seed_sample_records()).
$sample_listings = array();
foreach ($listings as $key => $l) {
    $sample_listings[] = array(
        'title'       => $l['title'],
        'deal'        => $deals[$l['deal']],
        'type'        => $property_types[$l['type']]['title'],
        'area'        => $areas[$l['area']]['title'],
        'status'      => $listing_statuses[$l['status']],
        'price'       => (string)$l['price'],
        'price_label' => $l['price_label'],
        'bedrooms'    => (string)$l['bedrooms'],
        'bathrooms'   => (string)$l['bathrooms'],
        'size'        => (string)$l['size'],
        'cover'       => $photo($key, 1200, 900),
        'photo_2'     => $photo($key . '-2', 1200, 900),
        'photo_3'     => $photo($key . '-3', 1200, 900),
        'features'    => implode("\n", $l['features']),
        'description' => $l['text'],
        'agent'       => $agents[$l['agent']]['name'],
    );
}
$listing_pages[] = array(
    'key'     => 'listing_new',
    'name'    => lang('new-listing'),
    'folder'  => 'private',
    'title'   => lang('New Listing'),
    'search'  => false,
    'sitemap' => false,
    'noindex' => true,
    'form'    => array(
        'form_name'            => lang('Listings'),
        'collection'           => 1,
        'confirmation_message' => lang('The listing is published.'),
        'confirmation_page_id' => '{{tab:listings}}',
        'sample_records'       => $sample_listings,
        // The cover is the listing's share image (pg_cf_share_image_field_id()).
        'share_image_field'    => 'cover',
    ),
    'tree'    => $staff_page(array(
        $page_header(lang('New Listing'), lang('Add the home with its photos; it appears in the lists as soon as you send it. Change its status from the listing\'s Edit button.')),
        $el('section', 'py-5', lang('Content'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $el('div', 'p-4 rounded-3 border', lang('Form Card'), array($widget('listing_form'))),
                    ), '', '8'),
                ), '', 'center'),
            )),
        )),
    )),
);

// ── Widget pieces ───────────────────────────────────────────────────────

// A number field of the Listings form: a number kind, so the lists sort it
// as a number.
$number = function ($id, $name, $required = true) use ($input, $attr) {
    return $input($id, 'number', $name, 'off', $required, array('kind' => 'number'), array($attr('min', '0'), $attr('step', '1')));
};

// A photo of the listing page, in a frame that leaves when the record has
// none.
$listing_photo = function ($field, $seed, $frame) use ($optional_image, $photo) {
    return $optional_image($field, 'title', 'rounded-3 object-fit-cover', 'ratio ratio-4x3 ' . $frame, $photo($seed, 1200, 900), lang('Photo'));
};

// The listing's agent beside the facts: the name the record names, and the
// ways to reach the office about this home.
$listing_agent = function () use ($el, $heading, $para, $link, $span, $icon, $bind, $page_link) {
    return $el('aside', 'card border-0 shadow-sm rounded-3', lang('Agent Card'), array(
        $el('div', 'card-body p-4', lang('Card Body'), array(
            $para(lang('Your agent for this home'), 'small text-uppercase fw-semibold text-body-secondary mb-2'),
            $heading('h2', lang('Deniz Aydın'), 'h5 fw-semibold mb-3', $bind('agent')),
            // Opens the viewing form with this home named (?listing=).
            $link(lang('Ask for a viewing'), '#', 'btn btn-primary w-100 mb-2', array(),
                $page_link('viewing', array('listing' => 'title'))),
            $link(lang('Call the office'), 'tel:+905550000000', 'btn btn-outline-primary w-100 mb-3'),
            $el('p', 'd-flex gap-2 small text-body-secondary mb-0', lang('Note'), array(
                $icon('bi-info-circle', 'mt-1'),
                $span(lang('Viewings are free and always with an agent. We confirm the time within a working day.'), '', lang('Text')),
            )),
        )),
    ));
};

$listing_widgets = array(

    // The staff's Listings form: one home. Status starts On the market.
    'listing_form' => array(
        'page'   => 'listing_new',
        'slug'   => 'form',
        'config' => array('regionType' => 'custom_form', 'form_source' => 'page'),
        'tree'   => $widget_root(array(
            $form_step(1, lang('The home')),
            $field('re-lst-title', lang('Title'), pg_cf_with_role($input('re-lst-title', 'text', 'title', 'off'), 'title'), 'mb-3',
                lang('A line a buyer remembers: the rooms, the best feature and where it is.')),
            $row(array(
                $col(array($field('re-lst-deal', lang('Deal'), $select('re-lst-deal', 'deal', '', array_values($deals), true, $deals['sale']))), '6'),
                $col(array($field('re-lst-type', lang('Type'), pg_cf_with_role($select('re-lst-type', 'type', lang('Choose a type…'), $type_titles), 'category'))), '6'),
                $col(array($field('re-lst-area', lang('Neighbourhood'), $select('re-lst-area', 'area', lang('Choose a neighbourhood…'), $area_titles))), '6'),
                $col(array($field('re-lst-status', lang('Status'), $select('re-lst-status', 'status', '', $listing_statuses, true, $listing_statuses[0]))), '6'),
            ), '3'),
            $form_step(2, lang('Price and size')),
            $row(array(
                $col(array($field('re-lst-price', lang('Price'), $number('re-lst-price', 'price'), 'mb-3', lang('The number the lists sort on: the sale price, or the monthly rent.'))), '6'),
                $col(array($field('re-lst-price-label', lang('Price as shown'), $input('re-lst-price-label', 'text', 'price_label', 'off'), 'mb-3', lang('As the cards show it, for example $1,500 a month.'))), '6'),
                $col(array($field('re-lst-bedrooms', lang('Bedrooms'), $number('re-lst-bedrooms', 'bedrooms'))), '4'),
                $col(array($field('re-lst-bathrooms', lang('Bathrooms'), $number('re-lst-bathrooms', 'bathrooms'))), '4'),
                $col(array($field('re-lst-size', lang('Size (m²)'), $number('re-lst-size', 'size'))), '4'),
            ), '3'),
            $form_step(3, lang('Photos and words')),
            $field('re-lst-cover', lang('Cover Photo'), pg_cf_with_role($file('re-lst-cover', 'cover', 'public', 'image/*'), 'media')),
            $row(array(
                $col(array($field('re-lst-photo-2', lang('Second Photo'), $file('re-lst-photo-2', 'photo_2', 'public', 'image/*'))), '6'),
                $col(array($field('re-lst-photo-3', lang('Third Photo'), $file('re-lst-photo-3', 'photo_3', 'public', 'image/*'))), '6'),
            ), '3'),
            $field('re-lst-features', lang('Features'), $textarea('re-lst-features', 'features', 4, false), 'mb-3', lang('One feature on each line.')),
            $field('re-lst-description', lang('Description'), pg_cf_with_role($textarea('re-lst-description', 'description', 6), 'description')),
            $field('re-lst-agent', lang('Agent'), $select('re-lst-agent', 'agent', lang('Choose an agent…'), array_slice($agent_choices, 1))),
            $submit(lang('Publish')),
            $loop(array()),
        )),
    ),

    // Homes for sale: the deal is fixed, the type and neighbourhood come
    // from the address.
    'buy_list' => array(
        'page'   => 'buy',
        'slug'   => lang('form-list'),
        'config' => $list_config(array(
            'items_per_page' => 12,
            'filters'        => array_merge(array($filter('deal', 'is equal to', $deals['sale'])), $not_closed, $url_filters(array('type' => 'type', 'area' => 'area'))),
        )),
        'tree'   => $card_grid(),
    ),

    // Homes to rent.
    'rent_list' => array(
        'page'   => 'rent',
        'slug'   => lang('form-list'),
        'config' => $list_config(array(
            'items_per_page' => 12,
            'filters'        => array_merge(array($filter('deal', 'is equal to', $deals['rent'])), $not_closed, $url_filters(array('type' => 'type', 'area' => 'area'))),
        )),
        'tree'   => $card_grid(),
    ),

    // Every listing, filtered by the address (deal, type, neighbourhood),
    // with a search over the words of the listings.
    'listings_list' => array(
        'page'   => 'listings',
        'slug'   => lang('form-list'),
        'config' => $list_config(array(
            'items_per_page' => 12,
            'filters'        => array_merge($not_closed, $url_filters(array('deal' => 'deal', 'type' => 'type', 'area' => 'area'))),
            'search_enabled' => true,
            'search_fields'  => array('title', 'area', 'type', 'features', 'description'),
            'search_width'   => 'full',
            'search_label'   => lang('Search the listings: a street, a feature, a neighbourhood...'),
        )),
        'tree'   => $card_grid(),
    ),

    // The home page's three newest homes still on the market.
    'home_listings' => array(
        'page'   => 'home',
        'slug'   => lang('form-list'),
        'config' => $list_config(array(
            'items_per_page' => 3,
            'max_results'    => 3,
            'filters'        => array($on_market),
            'empty_message'  => lang('No listings yet.'),
        )),
        'tree'   => $card_grid(),
    ),
);

// Each neighbourhood page's homes on the market, three at most.
foreach ($areas as $key => $a) {
    $listing_widgets['area_listings_' . $key] = array(
        'page'   => 'area_' . $key,
        'slug'   => lang('form-list'),
        'config' => $list_config(array(
            'items_per_page' => 3,
            'max_results'    => 3,
            'filters'        => array($filter('area', 'is equal to', $a['title']), $on_market),
            'empty_message'  => lang('Nothing on the market here this week. Ask us: we often know of a home before it is listed.'),
        )),
        'tree'   => $card_grid(),
    );
}

// One listing: the photos, the price and status, the facts, the features,
// the description, the agent and the viewing button.
$sample = $listings['seaview'];
$listing_widgets['listing_detail'] = array(
    'page'   => 'listing',
    'slug'   => lang('form-detail'),
    'config' => array(
        'regionType'          => 'form_item_view',
        'custom_form_page_id' => '{{tab:listing_new}}',
        'access_control'      => 'public',
        'not_found_message'   => lang('This listing could not be found. It may have been sold, let or taken off the market.'),
    ),
    'tree'   => $widget_root(array(
        $container(array(
            $el('div', 'text-center py-5', lang('Not Found'), array(
                $icon('bi-house-x', 'display-5 text-body-tertiary'),
                $heading('h2', lang('Listing not found'), 'h3 fw-semibold mt-3'),
                $para(lang('This listing could not be found. It may have been sold, let or taken off the market.'), 'text-body-secondary mb-4', $bind('not_found')),
                $link(lang('See the listings'), '{{page:listings}}', 'btn btn-primary px-4'),
            ), $show_when('record_not_found')),
            $el('article', '', lang('Listing'), array(
                $el('div', 'd-flex flex-wrap align-items-center justify-content-between gap-2 mb-3', lang('Toolbar'), array(
                    $el('a', 'd-inline-flex align-items-center gap-1 small fw-semibold link-primary text-decoration-none', lang('Link'), array(
                        $icon('bi-arrow-left'),
                        $span(lang('All Listings'), '', lang('Link Text')),
                    ), array('href' => '{{page:listings}}')),
                    $edit_button(),
                )),
                $el('div', 'd-flex flex-wrap gap-2 mb-2', lang('Badges'), array(
                    $span($deals[$sample['deal']], 'badge text-bg-primary', lang('Deal'), $bind('deal')),
                    $span($listing_statuses[$sample['status']], 'badge text-bg-light border', lang('Status'), $bind('status')),
                    $span($property_types[$sample['type']]['title'], 'badge bg-primary-subtle text-primary-emphasis', lang('Type'), $bind('type')),
                )),
                $heading('h1', $sample['title'], 'display-6 fw-semibold mb-2', $bind('title')),
                $el('p', 'd-flex align-items-center gap-1 text-body-secondary mb-4', lang('Neighbourhood'), array(
                    $icon('bi-geo-alt'),
                    $span($areas[$sample['area']]['title'], '', lang('Value'), $bind('area')),
                )),
                // The photos: the cover large, the other two beside it.
                $row(array(
                    $col(array($listing_photo('cover', 'seaview', '')), '', '8'),
                    $col(array(
                        $el('div', 'd-flex flex-column gap-3 h-100', lang('Photos'), array(
                            $listing_photo('photo_2', 'seaview-2', ''),
                            $listing_photo('photo_3', 'seaview-3', ''),
                        )),
                    ), '', '4'),
                ), '3', '', 'mb-4'),
                $row(array(
                    $col(array(
                        $el('div', 'd-flex flex-wrap align-items-center justify-content-between gap-3 pb-3 mb-4 border-bottom', lang('Price and Facts'), array(
                            $para($sample['price_label'], 'pre-price h2 fw-bold text-primary mb-0', $bind('price_label')),
                            $listing_facts($sample),
                        )),
                        $el('div', 'pg-hide-if-empty mb-4', lang('Description'), array(
                            $heading('h2', lang('About this home'), 'h5 fw-semibold mb-2'),
                            $el('div', 'pg-field-value lh-lg', lang('Value'), array(), array_merge(array('text' => $sample['text']), $bind('description'))),
                        )),
                        $el('div', 'pg-hide-if-empty p-4 rounded-3 bg-body-tertiary mb-4', lang('Features'), array(
                            $heading('h2', lang('Features'), 'h5 fw-semibold mb-2'),
                            $el('div', 'pg-field-value lh-lg', lang('Value'), array(), array_merge(array('text' => implode("\n", $sample['features'])), $bind('features'))),
                        )),
                        $el('div', 'alert alert-light border d-flex gap-2 small mb-0', lang('Note'), array(
                            $icon('bi-info-circle', 'mt-1'),
                            $span(lang('Details are given in good faith; ask us to confirm what matters to you before an offer.'), '', lang('Text')),
                        )),
                    ), '', '8'),
                    $col(array($listing_agent()), '', '4'),
                ), '4', '', 'g-lg-5'),
            ), $show_when('record_found')),
        ), 'py-5'),
    )),
);

return array('pages' => $listing_pages, 'widgets' => $listing_widgets);
