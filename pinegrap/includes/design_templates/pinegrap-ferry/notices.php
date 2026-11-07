<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Ferry", its travel notices: the staff page that adds a
 * notice (the Travel Notices form, a collection, with the sample notices
 * written in when it is made), the list of every notice with its route and
 * kind filters, the notice's own page and the list the home page places.
 * Included after parts.php in the same scope; returns
 * array('pages' => ..., 'widgets' => ...).
 *
 * A notice is a record of the Travel Notices form. Route and kind are pick
 * lists whose answers are the words parts.php gives, so a link with
 * ?route= or ?kind= (the footer, the route pages, the filter bar) filters
 * the list by the words a visitor sees. When is words: the line writes
 * "this afternoon" or "weekdays for three weeks", never a fixed date the
 * sample would carry. Featured is the line's choice (office use only): the
 * home page shows the featured notices. The pieces defined here
 * ($fy_filter, $fy_featured_choices, $fy_page_link, $fy_filter_bar) serve
 * the bookings too.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

$notice_pages = array();

// ── Pieces ──────────────────────────────────────────────────────────────

// A filter row of a list's settings: the field, how it compares and the
// value, or where the value comes from (a URL parameter, left out while
// the parameter is not in the address).
$fy_filter = function ($field, $operator, $value, $param = '') {
    $row = array('field' => $field, 'operator' => $operator, 'value' => $value, 'dynamic_value' => $param !== '' ? 'url parameter' : '');
    if ($param !== '') $row['dynamic_value_attribute'] = $param;
    return $row;
};
// The answers of the Featured list: the first marks a record for the home.
$fy_featured_choices = array(lang('Yes'), lang('No'));

// A link to a template page that sends values of the record it is drawn
// in: $params maps the address name to the field it takes (the renderer's
// __page_link).
$fy_page_link = function ($page_key, $params) {
    $list = array();
    foreach ($params as $name => $token) $list[] = array('name' => $name, 'token' => $token);
    return array(
        '_bindings'   => array('href' => '__page_link'),
        '_bindParams' => array('href' => array('page_id' => '{{tab:' . $page_key . '}}', 'params' => $list)),
    );
};

// The links above a list that narrow it: one per answer of each pick
// list, each opening the list page $page_key with the filter set.
$fy_filter_bar = function ($page_key, $groups) use ($el, $link, $span, $query) {
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

// A list of notices: the Travel Notices form, the notice page, newest
// first, and the words shown when nothing matches.
$fy_notice_list = function ($empty, $more) {
    return array_merge(array(
        'regionType'          => 'form_list_view',
        'custom_form_page_id' => '{{tab:notice_new}}',
        'detail_page_id'      => '{{tab:notice}}',
        'order_by_field'      => 'submitted_date_and_time',
        'order_by_direction'  => 'DESC',
        'empty_message'       => $empty,
    ), $more);
};
// The notice cards of a list, in rows of three.
$notice_grid = function () use ($widget_root, $row, $loop, $notice_card) {
    return $widget_root(array($row(array($loop(array($notice_card()))), '4')));
};

// ── Pages ───────────────────────────────────────────────────────────────

// Every notice, narrowed by route and kind from the address. A notice for
// every route answers "All Routes", so it shows in the full list and under
// that filter, not under one route's.
$notice_pages[] = array(
    'key'              => 'notices',
    'name'             => lang('travel-notices'),
    'folder'           => 'public',
    'title'            => lang('Travel Notices'),
    'meta_description' => lang('Disruptions, timetable changes, weather warnings and port works on every ferry route, newest first.'),
    'tree'             => $page(array(
        $page_header(lang('Travel Notices'), lang('Check your route before you set off: every change and disruption is here as soon as we know it.')),
        $el('section', 'py-5', lang('Travel Notices'), array(
            $container(array(
                $fy_filter_bar('notices', array(
                    array('label' => lang('Route'), 'param' => 'route', 'values' => $notice_route_choices),
                    array('label' => lang('Kind'), 'param' => 'kind', 'values' => $notice_kinds),
                )),
                $widget('notices_list'),
            )),
        ), $searchable('fy-notices', lang('notice, disruption, delay, cancelled, weather, wind, timetable change, port works, announcement'))),
    )),
);

// One notice: its record.
$notice_pages[] = array(
    'key'              => 'notice',
    'name'             => lang('travel-notice'),
    'folder'           => 'public',
    'title'            => lang('Sailing Notice'),
    'meta_description' => lang('A travel notice of the ferry line: the route, what changes, when and what to do.'),
    'sitemap'          => false,
    'tree'             => $page(array(
        $widget('notice_detail'),
    )),
);

// The staff page that adds a notice. The form is the Travel Notices
// collection; the sample notices are written in when the form is made, so
// the lists do not open empty (pg_cf_seed_sample_records()).
$sample_notice_records = array();
foreach ($sample_notices as $key => $n) {
    $sample_notice_records[] = array(
        'title'    => $n['title'],
        'kind'     => $notice_kind($key),
        'route'    => $notice_route($key),
        'when'     => $n['when'],
        'summary'  => $n['summary'],
        'details'  => $n['details'],
        'photo_1'  => $photo('notice-' . $key, 1200, 675),
        'featured' => $n['featured'] ? $fy_featured_choices[0] : $fy_featured_choices[1],
    );
}
$notice_pages[] = array(
    'key'     => 'notice_new',
    'name'    => lang('new-travel-notice'),
    'folder'  => 'private',
    'title'   => lang('New Travel Notice'),
    'search'  => false,
    'sitemap' => false,
    'noindex' => true,
    'form'    => array(
        'form_name'            => lang('Travel Notices'),
        'collection'           => 1,
        'confirmation_message' => lang('The travel notice is published.'),
        'confirmation_page_id' => '{{tab:notices}}',
        'sample_records'       => $sample_notice_records,
        // The photo is the notice's share image (pg_cf_share_image_field_id()).
        'share_image_field'    => 'photo_1',
    ),
    'tree'    => $staff_page(array(
        $page_header(lang('New Travel Notice'), lang('Tell travellers what changes, on which route and when; the notice is in the list as soon as you send it. Mark it featured from its Edit button to show it on the home page.')),
        $el('section', 'py-5', lang('Content'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $el('div', 'p-4 rounded-4 border', lang('Form Card'), array($widget('notice_form'))),
                    ), '', '8'),
                ), '', 'center'),
            )),
        )),
    )),
);

// ── Widgets ─────────────────────────────────────────────────────────────

$notice_widgets = array(

    // The staff's Travel Notices form: one notice. The route starts on
    // every route, featured on No.
    'notice_form' => array(
        'page'   => 'notice_new',
        'slug'   => 'form',
        'config' => array('regionType' => 'custom_form', 'form_source' => 'page'),
        'tree'   => $widget_root(array(
            $form_step(1, lang('The notice')),
            $field('fy-notice-title', lang('Title'), pg_cf_with_role($input('fy-notice-title', 'text', 'title', 'off'), 'title'), 'mb-3',
                lang('What changes, in a few words, for example: Strong winds may delay the afternoon sailings.')),
            $row(array(
                $col(array($field('fy-notice-kind', lang('Kind'), pg_cf_with_role($select('fy-notice-kind', 'kind', lang('Choose a kind…'), $notice_kinds), 'category'))), '6'),
                $col(array($field('fy-notice-route', lang('Route'), $select('fy-notice-route', 'route', '', $notice_route_choices, true, $notice_route_choices[0]))), '6'),
                $col(array($field('fy-notice-when', lang('When'), $input('fy-notice-when', 'text', 'when', 'off'), 'mb-3',
                    sprintf(lang('In words, for example %s.'), $sample_notices['ramp']['when']))), '12'),
            ), '3'),
            $form_step(2, lang('Words')),
            $field('fy-notice-summary', lang('Summary'), pg_cf_with_role($textarea('fy-notice-summary', 'summary', 2), 'description'), 'mb-3',
                lang('One or two lines for the cards of the lists.')),
            $field('fy-notice-details', lang('Details'), $textarea('fy-notice-details', 'details', 5, false), 'mb-3',
                lang('What travellers should do: another sailing, another quay, extra time at check-in.')),
            $form_step(3, lang('Photo')),
            $field('fy-notice-photo-1', lang('Photo'), pg_cf_with_role($file('fy-notice-photo-1', 'photo_1', 'public', 'image/*'), 'media'), 'mb-3',
                lang('Optional: a picture of the port or the works, and the share image.')),
            $field('fy-notice-featured', lang('Featured'), $select('fy-notice-featured', 'featured', '', $fy_featured_choices, false, $fy_featured_choices[1], array('office_use_only' => true)),
                'mb-3', lang('A featured notice is shown on the home page.')),
            $submit(lang('Publish')),
            $loop(array()),
        )),
    ),

    // Every notice, filtered by the address (route, kind), newest first,
    // with a search over the words of the notices.
    'notices_list' => array(
        'page'   => 'notices',
        'slug'   => lang('form-list'),
        'config' => $fy_notice_list(lang('No travel notice matches these choices: the sailings run as in the timetable.'), array(
            'items_per_page' => 12,
            'filters'        => array(
                $fy_filter('route', 'is equal to', '', 'route'),
                $fy_filter('kind', 'is equal to', '', 'kind'),
            ),
            'search_enabled' => true,
            'search_fields'  => array('title', 'kind', 'route', 'when', 'summary', 'details'),
            'search_width'   => 'full',
            'search_label'   => lang('Search the notices: wind, ramp, winter timetable...'),
        )),
        'tree'   => $notice_grid(),
    ),

    // The home page's featured notices, three at most.
    'home_notices' => array(
        'page'   => 'home',
        'slug'   => lang('form-list'),
        'config' => $fy_notice_list(lang('No travel notices right now: every sailing runs as in the timetable.'), array(
            'items_per_page' => 3,
            'max_results'    => 3,
            'filters'        => array($fy_filter('featured', 'is equal to', $fy_featured_choices[0])),
        )),
        'tree'   => $notice_grid(),
    ),
);

// One notice: the kind and route, the title, when, the summary, the photo
// and the details, and beside them the ways to this route's other
// notices, the booking request with the route chosen and the timetables.
$notice_sample_key = 'wind';
$notice_sample = $sample_notices[$notice_sample_key];
$notice_not_found = lang('This travel notice could not be found. It may have been withdrawn once the sailings were back to normal.');
$notice_widgets['notice_detail'] = array(
    'page'   => 'notice',
    'slug'   => lang('form-detail'),
    'config' => array(
        'regionType'          => 'form_item_view',
        'custom_form_page_id' => '{{tab:notice_new}}',
        'access_control'      => 'public',
        'not_found_message'   => $notice_not_found,
    ),
    'tree'   => $widget_root(array(
        $container(array(
            $el('div', 'text-center py-5', lang('Not Found'), array(
                $icon('bi-signpost', 'display-5 text-body-tertiary'),
                $heading('h2', lang('Notice not found'), 'h3 fw-semibold mt-3'),
                $para($notice_not_found, 'text-body-secondary mb-4', $bind('not_found')),
                $link(lang('See All Travel Notices'), '{{page:notices}}', 'btn btn-primary px-4'),
            ), $show_when('record_not_found')),
            $el('article', '', lang('Sailing Notice'), array(
                $el('div', 'd-flex flex-wrap align-items-center justify-content-between gap-2 mb-3', lang('Toolbar'), array(
                    $el('a', 'd-inline-flex align-items-center gap-1 small fw-semibold link-primary text-decoration-none', lang('Link'), array(
                        $icon('bi-arrow-left'),
                        $span(lang('All Travel Notices'), '', lang('Link Text')),
                    ), array('href' => '{{page:notices}}')),
                    $edit_button(),
                )),
                $row(array(
                    $col(array(
                        $el('div', 'd-flex flex-wrap align-items-center gap-2 mb-2', lang('Badges'), array(
                            $span($notice_kind($notice_sample_key), 'badge rounded-pill text-bg-warning', lang('Kind'), $bind('kind')),
                            $span($notice_route($notice_sample_key), 'badge rounded-pill bg-primary-subtle text-primary-emphasis', lang('Route'), $bind('route')),
                        )),
                        $heading('h1', $notice_sample['title'], 'pfy-display display-6 mb-2', $bind('title')),
                        $el('p', 'd-flex align-items-center gap-2 fw-semibold mb-3', lang('When'), array(
                            $icon('bi-clock', 'text-primary'),
                            $span($notice_sample['when'], '', lang('Value'), $bind('when')),
                        )),
                        $para($notice_sample['summary'], 'lead text-body-secondary mb-4', $bind('summary')),
                        $optional_image('photo_1', 'title', 'rounded-4 object-fit-cover', 'ratio ratio-16x9 mb-4', $photo('notice-' . $notice_sample_key, 1200, 675), lang('Photo')),
                        $el('div', 'pg-hide-if-empty mb-4', lang('Details'), array(
                            $heading('h2', lang('What to do'), 'pfy-display h4 mb-2'),
                            $el('div', 'pg-field-value', lang('Value'), array(), array_merge(array('text' => $notice_sample['details']), $bind('details'))),
                        )),
                    ), '', '8'),
                    $col(array(
                        $el('aside', 'card border-0 shadow-sm rounded-4', lang('Notice Card'), array(
                            $el('div', 'card-body p-4', lang('Card Body'), array(
                                $heading('h2', lang('Travelling on this route?'), 'pfy-display h5 mb-2'),
                                $para($line['checkin'] . '.', 'small text-body-secondary mb-3'),
                                // Opens the list on this notice's route (?route=).
                                $link(lang('Other Notices for This Route'), '#', 'btn btn-outline-secondary rounded-pill w-100 mb-2', array(),
                                    $fy_page_link('notices', array('route' => 'route'))),
                                // Opens the booking request with this route
                                // chosen (?route=); a notice for every route
                                // leaves the choice to the traveller.
                                $link(lang('Request a Booking'), '#', 'btn btn-primary rounded-pill w-100 mb-2', array(),
                                    $fy_page_link('booking_request', array('route' => 'route'))),
                                $link(lang('See the Timetables'), '{{page:timetables}}', 'btn btn-link w-100'),
                            )),
                        )),
                    ), '', '4'),
                ), '4', '', 'g-lg-5 mb-4'),
            ), $show_when('record_found')),
        ), 'py-5'),
    )),
);

return array('pages' => $notice_pages, 'widgets' => $notice_widgets);
