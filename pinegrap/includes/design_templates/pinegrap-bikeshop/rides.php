<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Bike Shop", its group rides: the rides page (the week's
 * regular rides, then every ride from today on by pace), a ride's own
 * page, the staff page that adds a ride (the Group Rides form, a
 * collection, with the sample rides written in when it is made) and the
 * home's next featured rides. Included after parts.php, cards.php and
 * pages_public.php in the same scope (it uses $block_pill of the public
 * pages); returns array('pages' => ..., 'widgets' => ...).
 *
 * Every sample ride is dated from the day the template opens ($ride_on),
 * and the lists keep only the rides from today on (the current date
 * filter), nearest first. The list narrows to one pace from the address
 * (?pace=, in the pace list's own words); without it every pace shows.
 * The week is drawn in the structure of the palette's bikeshop-rides block,
 * so the staff edit it as the palette's own; the calendar is the
 * collection, each ride in the ride card of cards.php.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

$ride_pages = array();

// ── Pieces ──────────────────────────────────────────────────────────────

// Whether the home shows a ride: the answers of the office-only Featured
// field.
$ride_featured_choices = array(lang('Yes'), lang('No'));

// The sample rides as records of the Group Rides form, each dated from the
// day the template opens. They have no route link: the shop adds its own.
$sample_rides = array();
foreach ($rides as $key => $r) {
    $sample_rides[] = array(
        'title'         => $r['title'],
        'ride_date'     => $ride_on($r['weekday'], $r['weeks']),
        'start_time'    => $r['time'],
        'pace'          => $ride_pace($key),
        'drop_rule'     => $ride_drop($key),
        'distance'      => $r['distance'],
        'climbing'      => $r['climbing'],
        'meeting_point' => $r['place'],
        'summary'       => $r['summary'],
        'photo_1'       => $photo('ride-' . $key, 1200, 675),
        'featured'      => $r['featured'] ? $ride_featured_choices[0] : $ride_featured_choices[1],
    );
}

// A filter row of a list; $param reads the value from the address.
$ride_filter = function ($field, $operator, $value, $param = '') {
    $row = array('field' => $field, 'operator' => $operator, 'value' => $value, 'dynamic_value' => $param !== '' ? 'url parameter' : '');
    if ($param !== '') $row['dynamic_value_attribute'] = $param;
    return $row;
};
$ride_from_today = array_merge($ride_filter('ride_date', 'is greater than or equal to', ''), array('dynamic_value' => 'current date'));

// The rides from today on, nearest first, opening the ride's page.
$ride_list = function ($empty, $more) {
    return array_merge(array(
        'regionType'          => 'form_list_view',
        'custom_form_page_id' => '{{tab:ride_new}}',
        'detail_page_id'      => '{{tab:ride}}',
        'order_by_field'      => 'ride_date',
        'order_by_direction'  => 'ASC',
        'empty_message'       => $empty,
    ), $more);
};

// The days of the week by ISO number, for the week's regular rides.
$weekday_names = array(1 => lang('Monday'), 2 => lang('Tuesday'), 3 => lang('Wednesday'), 4 => lang('Thursday'),
    5 => lang('Friday'), 6 => lang('Saturday'), 7 => lang('Sunday'));

// The pace pills in the colours of the rides block, each said in words.
$pace_looks = array(
    'social' => 'bg-success-subtle text-success-emphasis',
    'steady' => 'bg-info-subtle text-info-emphasis',
    'fast'   => 'bg-danger-subtle text-danger-emphasis',
);

// A regular ride of the week, in the bikeshop-rides block's structure: the
// day and a <time> start, the name, the pace and drop rule as pills, the
// distance, climbing and speed in a list of terms, the meeting point. The
// next ride carries "Next ride", a primary border and aria-current.
$week_ride = function ($key, $next) use ($el, $heading, $para, $span, $col, $attr, $block_pill, $rides, $paces, $drop_rules, $weekday_names, $pace_looks) {
    $r = $rides[$key];
    $stat = function ($label, $value) use ($el) {
        return $el('div', 'col', $label, array(
            $el('dt', 'small fw-normal text-body-secondary', lang('Term'), array(), array('text' => $label)),
            $el('dd', 'fw-semibold pg-bike-num mb-0', lang('Value'), array(), array('text' => $value)),
        ));
    };
    $heads = array(
        $heading('h3', $r['title'], 'pbs-display h5 text-uppercase fw-bold mb-0'),
        $block_pill($pace_looks[$r['pace']], $paces[$r['pace']]['icon'], $paces[$r['pace']]['title']),
        $block_pill('bg-body-secondary text-body-emphasis', $drop_rules[$r['drop']]['icon'], $drop_rules[$r['drop']]['title']),
    );
    if ($next) $heads[] = $span(lang('Next ride'), 'badge rounded-pill text-bg-primary', lang('Badge'));
    $extra = $next ? array('_attrs' => array($attr('aria-current', 'true'))) : array();
    return $col(array(
        $el('article', 'card h-100 rounded-0 ' . ($next ? 'border-primary border-2 shadow' : 'border-0 shadow-sm'), lang('Ride Card'), array(
            $el('div', 'card-body p-4', lang('Card Body'), array(
                $el('div', 'd-flex align-items-baseline gap-2 mb-2', lang('Ride Time'), array(
                    $span($weekday_names[$r['weekday']], 'fw-bold text-uppercase small text-primary', lang('Weekday')),
                    $el('time', 'pg-bike-num h4 fw-bold mb-0', lang('Start Time'), array(), array(
                        'text' => $r['time'], '_attrs' => array($attr('datetime', $r['time'])))),
                )),
                $el('div', 'd-flex flex-wrap align-items-center gap-2 mb-3', lang('Ride Head'), $heads),
                $el('dl', 'row row-cols-3 g-2 mb-3', lang('Ride Facts'), array(
                    $stat(lang('Distance'), $r['distance']),
                    $stat(lang('Climbing'), $r['climbing']),
                    $stat(lang('Pace'), $paces[$r['pace']]['title']),
                )),
                $para($r['place'] . ' · ' . $paces[$r['pace']]['speed'], 'small text-body-secondary mb-0'),
            )),
        ), $extra),
    ), '6', '4');
};

// The regular rides: the ones of this week, nearest first.
$week_keys = array();
foreach ($rides as $key => $r) {
    if ((int)$r['weeks'] === 0) $week_keys[$key] = $ride_on($r['weekday'], 0);
}
asort($week_keys);
$week_cards = array();
foreach (array_keys($week_keys) as $n => $key) $week_cards[] = $week_ride($key, $n === 0);

// ── Pages ───────────────────────────────────────────────────────────────

// The group rides: the week's regular rides, then every ride from today on
// with the paces to narrow it, and how the shop rides together.
$ride_pages[] = array(
    'key'              => 'rides',
    'name'             => lang('group-rides'),
    'folder'           => 'public',
    'title'            => lang('Group Rides'),
    'meta_description' => lang('The shop\'s free group rides from today on: the day, the start time, the pace, the distance and where to meet.'),
    'tree'             => $page(array(
        $page_header(lang('Group Rides'), lang('Free rides from the shop door every week, at three paces. Pick a pace, check the day and come along.')),
        $el('section', 'py-5', lang('Bike Shop (Group Rides)'), array(
            $container(array(
                $el('div', 'd-flex flex-wrap align-items-end justify-content-between gap-3 mb-4', lang('Section Header'), array(
                    $el('div', 'pbs-mark border-start border-4 border-primary ps-3', lang('Section Title'), array(
                        $heading('h2', lang('Ride with us this week'), 'pbs-display h2 text-uppercase mb-1'),
                        $para(lang('Free and open to every rider; bring lights once the evenings draw in.'), 'text-body-secondary mb-0'),
                    )),
                    $pace_legend(),
                )),
                $rise($row($week_cards, '4')),
            )),
        ), $searchable('bikeshop-rides-week', lang('weekly ride, evening ride, café ride, hill reps, start time, meeting point'))),
        $el('section', 'py-5 bg-body-tertiary border-top', lang('Ride Calendar'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $section_header(lang('Every ride from today'), lang('The regular rides and the specials, the nearest first.')),
                        $el('nav', 'mb-4', lang('Paces'), array($pace_pills()), array('_attrs' => array($attr('aria-label', lang('Pace'))))),
                        $widget('rides_list'),
                    ), '', '8'),
                    $col(array(
                        $el('div', 'p-4 bg-body border-start border-4 border-dark mb-4', lang('How We Ride'), array(
                            $heading('h2', lang('How we ride together'), 'pbs-display h5 text-uppercase mb-3'),
                            $check_list(array(
                                lang('A helmet and a bike in working order; lights once the evenings draw in.'),
                                lang('Two abreast at most, single file when the road narrows.'),
                                lang('Call out holes and point at them; the leader says when we stop.'),
                                lang('On a no-drop ride we regroup at the top of every climb.'),
                            ), 'list-unstyled small mb-0'),
                        )),
                        $el('div', 'p-4 border border-2 border-primary', lang('Before the Ride'), array(
                            $heading('h2', lang('Ready for the ride?'), 'pbs-display h5 text-uppercase mb-2'),
                            $para(lang('A safety check the same day, or a fit for a longer ride in comfort.'), 'small text-body-secondary mb-3'),
                            $el('div', 'd-flex flex-wrap gap-2', lang('Buttons'), array(
                                $link($job_kinds['safety']['title'], '{{page:service_request}}?' . $query(array('job' => $job_kinds['safety']['title'])), 'btn btn-sm btn-dark rounded-0 px-3'),
                                $link(lang('Bike Fit'), '{{page:fit}}', 'btn btn-sm btn-outline-dark rounded-0 px-3'),
                            )),
                        )),
                    ), '', '4'),
                ), '4', '', 'g-lg-5'),
            )),
        ), $searchable('bikeshop-rides-calendar', lang('group ride, calendar, pace, social, steady, fast, no-drop, gravel, beginners'))),
    )),
);

// One ride.
$ride_pages[] = array(
    'key'              => 'ride',
    'name'             => lang('group-ride'),
    'folder'           => 'public',
    'title'            => lang('Group Ride'),
    'meta_description' => lang('A group ride from the shop: the day and start time, the pace, the distance and climbing, and where to meet.'),
    'sitemap'          => false,
    'tree'             => $page(array(
        $widget('ride_detail'),
    )),
);

// The staff page that adds a ride. The form is the Group Rides collection;
// the sample rides are written in when the form is made, so the lists do
// not open empty (pg_cf_seed_sample_records()).
$ride_pages[] = array(
    'key'     => 'ride_new',
    'name'    => lang('new-group-ride'),
    'folder'  => 'private',
    'title'   => lang('New Group Ride'),
    'search'  => false,
    'sitemap' => false,
    'noindex' => true,
    'form'    => array(
        'form_name'            => lang('Group Rides'),
        'collection'           => 1,
        'confirmation_message' => lang('The ride is published.'),
        'confirmation_page_id' => '{{tab:rides}}',
        'sample_records'       => $sample_rides,
        'share_image_field'    => 'photo_1',
    ),
    'tree'    => $staff_page(array(
        $page_header(lang('New Group Ride'), lang('Add a ride with its day, start time, pace and meeting point; it stays in the calendar until its day has passed.')),
        $el('section', 'py-5', lang('Content'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $el('div', 'card rounded-0 border-0 border-top border-4 border-dark shadow-sm', lang('Form Card'), array(
                            $el('div', 'card-body p-4 p-lg-5', lang('Card Body'), array($widget('ride_form'))),
                        )),
                    ), '', '8'),
                ), '', 'center'),
            )),
        )),
    )),
);

// ── Widgets ─────────────────────────────────────────────────────────────

$ride_widgets = array(

    // The staff's Group Rides form: one ride, its day, its pace and route,
    // and whether the home shows it.
    'ride_form' => array(
        'page'   => 'ride_new',
        'slug'   => 'form',
        'config' => array('regionType' => 'custom_form', 'form_source' => 'page'),
        'tree'   => $widget_root(array(
            $form_step(1, lang('The ride')),
            $field('bikeshop-ride-title', lang('Name'), pg_cf_with_role($input('bikeshop-ride-title', 'text', 'title', 'off'), 'title')),
            $row(array(
                $col(array($field('bikeshop-ride-date', lang('Date'), $input('bikeshop-ride-date', 'date', 'ride_date', 'off', true, array('rules' => array('min_date' => 'today'))))), '6'),
                $col(array($field('bikeshop-ride-time', lang('Starts at'), $input('bikeshop-ride-time', 'text', 'start_time', 'off', true, array(), array($attr('placeholder', '08:30'))))), '6'),
            ), '3'),
            $row(array(
                $col(array($field('bikeshop-ride-pace', lang('Pace'), pg_cf_with_role($select('bikeshop-ride-pace', 'pace', lang('Choose a pace…'), $pace_titles), 'category'))), '6'),
                $col(array($field('bikeshop-ride-drop', lang('Drop rule'), $select('bikeshop-ride-drop', 'drop_rule', '', $drop_titles, true, $drop_titles[0]))), '6'),
            ), '3'),
            $row(array(
                $col(array($field('bikeshop-ride-distance', lang('Distance'), $input('bikeshop-ride-distance', 'text', 'distance', 'off'), 'mb-3',
                    sprintf(lang('In words, for example %s.'), $rides['saturday_social']['distance']))), '6'),
                $col(array($field('bikeshop-ride-climbing', lang('Climbing'), $input('bikeshop-ride-climbing', 'text', 'climbing', 'off', false), 'mb-3',
                    sprintf(lang('In words, for example %s.'), $rides['saturday_social']['climbing']))), '6'),
            ), '3'),
            $field('bikeshop-ride-meet', lang('Meeting point'), $input('bikeshop-ride-meet', 'text', 'meeting_point', 'off')),
            $field('bikeshop-ride-route', lang('Route link'), $input('bikeshop-ride-route', 'url', 'route_link', 'url', false), 'mb-3',
                lang('The web address of the route on a map, if there is one.')),
            $field('bikeshop-ride-summary', lang('Summary'), pg_cf_with_role($textarea('bikeshop-ride-summary', 'summary', 3), 'description')),
            $form_step(2, lang('Photo')),
            $field('bikeshop-ride-photo', lang('Photo'), pg_cf_with_role($file('bikeshop-ride-photo', 'photo_1', 'public', 'image/*'), 'media'), 'mb-3',
                lang('The picture of the ride\'s page and the share image.')),
            $field('bikeshop-ride-featured', lang('Featured'), $select('bikeshop-ride-featured', 'featured', '', $ride_featured_choices, false, $ride_featured_choices[1], array('office_use_only' => true)),
                'mb-3', lang('A featured ride is shown on the home page until its day.')),
            $submit(lang('Publish')),
            $loop(array()),
        )),
    ),

    // The rides from today on, nearest first, at the pace of the address.
    'rides_list' => array(
        'page'   => 'rides',
        'slug'   => lang('form-list'),
        'config' => $ride_list(lang('No rides at this pace right now. Try another pace, or subscribe to the newsletter to hear about the next ones.'), array(
            'items_per_page' => 12,
            'filters'        => array($ride_from_today, $ride_filter('pace', 'is equal to', '', 'pace')),
        )),
        'tree'   => $widget_root(array($row(array($loop(array($ride_card()))), '4'))),
    ),

    // The home page's rides: the next featured ones, two at most.
    'home_rides' => array(
        'page'   => 'home',
        'slug'   => lang('group-rides'),
        'config' => $ride_list(lang('No rides planned right now.'), array(
            'items_per_page' => 2,
            'max_results'    => 2,
            'filters'        => array($ride_from_today, $ride_filter('featured', 'is equal to', $ride_featured_choices[0])),
        )),
        'tree'   => $widget_root(array($row(array($loop(array($ride_card('gravel_explore')))), '4'))),
    ),
);

// One ride: the photo, the pace and drop rule, the title, when and where,
// the distance and climbing, the summary and the route, with the ways to
// the other rides and to a safety check before it.
$ride_sample_key = 'saturday_social';
$ride_sample = $rides[$ride_sample_key];
$ride_not_found = lang('This ride could not be found. It may have passed, or the page was renamed.');
$ride_fact = function ($icon_name, $name, $sample, $token, $class = 'mb-2') use ($el, $icon, $span, $bind) {
    return $el('li', 'd-flex align-items-center gap-2 ' . $class, $name, array(
        $icon($icon_name, 'text-primary'),
        $span($sample, 'pg-bike-num', $name, $bind($token)),
    ));
};
$ride_widgets['ride_detail'] = array(
    'page'   => 'ride',
    'slug'   => lang('form-detail'),
    'config' => array(
        'regionType'          => 'form_item_view',
        'custom_form_page_id' => '{{tab:ride_new}}',
        'access_control'      => 'public',
        'not_found_message'   => $ride_not_found,
    ),
    'tree'   => $widget_root(array(
        $container(array(
            $el('div', 'text-center py-5', lang('Not Found'), array(
                $icon('bi-calendar-x', 'display-5 text-body-secondary'),
                $heading('h1', lang('Ride not found'), 'pbs-display h3 text-uppercase mt-3'),
                $para($ride_not_found, 'text-body-secondary mb-4', $bind('not_found')),
                $link(lang('All Group Rides'), '{{page:rides}}', 'btn btn-primary rounded-0 px-4'),
            ), $show_when('record_not_found')),
            $el('article', '', lang('Ride'), array(
                $el('div', 'd-flex flex-wrap align-items-center justify-content-between gap-2 mb-4', lang('Toolbar'), array(
                    $el('a', 'd-inline-flex align-items-center gap-1 small fw-semibold link-primary text-decoration-none', lang('Link'), array(
                        $icon('bi-arrow-left'),
                        $span(lang('Group Rides'), '', lang('Link Text')),
                    ), array('href' => '{{page:rides}}')),
                    $edit_button(),
                )),
                $row(array(
                    $col(array(
                        $optional_image('photo_1', 'title', 'w-100 h-100 object-fit-cover', 'ratio ratio-16x9 overflow-hidden bg-body-tertiary',
                            $photo('ride-' . $ride_sample_key, 1200, 675), lang('Photo')),
                    ), '', '7'),
                    $col(array(
                        $el('div', 'd-flex flex-wrap gap-2 mb-3', lang('Badges'), array(
                            $el('span', 'badge rounded-0 text-bg-light border d-inline-flex align-items-center gap-1', lang('Badge'), array(
                                $icon($paces[$ride_sample['pace']]['icon']),
                                $span($ride_pace($ride_sample_key), '', lang('Pace'), $bind('pace')),
                            )),
                            $el('span', 'badge rounded-0 text-bg-light border d-inline-flex align-items-center gap-1', lang('Badge'), array(
                                $icon($drop_rules[$ride_sample['drop']]['icon']),
                                $span($ride_drop($ride_sample_key), '', lang('Drop rule'), $bind('drop_rule')),
                            )),
                        )),
                        $heading('h1', $ride_sample['title'], 'pbs-display display-5 text-uppercase fw-bold mb-3', $bind('title')),
                        $el('ul', 'list-unstyled border-start border-4 border-primary ps-3 mb-4', lang('When and Where'), array(
                            $ride_fact('bi-calendar3', lang('Date'), $ride_on($ride_sample['weekday'], $ride_sample['weeks']), 'ride_date'),
                            $ride_fact('bi-clock', lang('Starts at'), $ride_sample['time'], 'start_time'),
                            $ride_fact('bi-geo-alt', lang('Meeting point'), $ride_sample['place'], 'meeting_point'),
                            $ride_fact('bi-signpost-split', lang('Distance'), $ride_sample['distance'], 'distance'),
                            $ride_fact('bi-graph-up-arrow', lang('Climbing'), $ride_sample['climbing'], 'climbing', 'mb-0'),
                        )),
                        $para($ride_sample['summary'], 'lead mb-4', $bind('summary')),
                        $el('div', 'pg-hide-if-empty mb-3', lang('Route Button'), array(
                            $span(lang('Route link'), 'pg-field-value d-none', lang('Value'), $bind('route_link')),
                            $el('a', 'btn btn-outline-dark rounded-0 d-inline-flex align-items-center gap-2 px-4', lang('Link'), array(
                                $icon('bi-map'),
                                $span(lang('See the route'), '', lang('Link Text')),
                            ), array('href' => '#', 'target' => '_blank', 'rel' => 'noopener', '_bindings' => array('href' => 'route_link'))),
                        )),
                        $el('div', 'd-flex flex-wrap gap-2', lang('Buttons'), array(
                            $link(lang('All Group Rides'), '{{page:rides}}', 'btn btn-primary rounded-0 px-4'),
                            $link($job_kinds['safety']['title'], '{{page:service_request}}?' . $query(array('job' => $job_kinds['safety']['title'])), 'btn btn-outline-dark rounded-0 px-4'),
                        )),
                    ), '', '5'),
                ), '4', '', 'g-lg-5 align-items-center'),
            ), $show_when('record_found')),
        ), 'py-5'),
    )),
);

return array('pages' => $ride_pages, 'widgets' => $ride_widgets);
