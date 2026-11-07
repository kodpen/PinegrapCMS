<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Home Services", its services and its team: the staff
 * pages that add a service and a team member (the Services and Team forms,
 * collections, with the sample records written in when they are made), the
 * list of every service with its category filter, the team list, the
 * service's and the member's own pages, and the lists the home page
 * places. Included after parts.php in the same scope; returns
 * array('pages' => ..., 'widgets' => ...).
 *
 * A service and a member are records of their forms. Category and role are
 * pick lists whose answers are the words parts.php gives, so a link with
 * ?category= filters the list by the words a visitor sees. Years is a
 * number field, so a list can sort on it; the duration, the crew and the
 * price are the words the cards show. The service page's comments are the
 * customers' reviews, with stars. Featured is the company's own choice
 * (office use only): the home page shows the featured services.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

$service_pages = array();

// ── Pieces ──────────────────────────────────────────────────────────────

// A filter row of a list's settings: the field, how it compares and the
// value, or where the value comes from (a URL parameter, left out while
// the parameter is not in the address).
$hs_filter = function ($field, $operator, $value, $param = '') {
    $row = array('field' => $field, 'operator' => $operator, 'value' => $value, 'dynamic_value' => $param !== '' ? 'url parameter' : '');
    if ($param !== '') $row['dynamic_value_attribute'] = $param;
    return $row;
};
// The answers of the Featured list: the first marks a record for the home.
$hs_featured_choices = array(lang('Yes'), lang('No'));

// A link to a template page that sends values of the record it is drawn
// in: $params maps the address name to the field it takes (the renderer's
// __page_link).
$hs_page_link = function ($page_key, $params) {
    $list = array();
    foreach ($params as $name => $token) $list[] = array('name' => $name, 'token' => $token);
    return array(
        '_bindings'   => array('href' => '__page_link'),
        '_bindParams' => array('href' => array('page_id' => '{{tab:' . $page_key . '}}', 'params' => $list)),
    );
};

// A list's settings: the form, its record page, the order and the words
// shown when nothing matches.
$hs_list_config = function ($form_page, $detail_page, $order_field, $direction, $empty, $more) {
    return array_merge(array(
        'regionType'          => 'form_list_view',
        'custom_form_page_id' => '{{tab:' . $form_page . '}}',
        'detail_page_id'      => '{{tab:' . $detail_page . '}}',
        'order_by_field'      => $order_field,
        'order_by_direction'  => $direction,
        'empty_message'       => $empty,
    ), $more);
};
// The cards of a list: services in rows of three, members in rows of four.
$service_grid = function () use ($widget_root, $row, $loop, $service_card) {
    return $widget_root(array($row(array($loop(array($service_card()))), '4')));
};
$team_grid = function () use ($widget_root, $row, $loop, $team_card) {
    return $widget_root(array($row(array($loop(array($team_card()))), '4')));
};

// The links above the service list that narrow it: one per category, each
// opening the list with the filter set.
$hs_filter_bar = function ($page_key, $label, $param, $values) use ($el, $link, $span, $query) {
    $children = array($span($label, 'small fw-semibold text-body-secondary me-1', lang('Label')));
    $children[] = $link(lang('All'), '{{page:' . $page_key . '}}', 'btn btn-sm btn-outline-secondary');
    foreach ($values as $v) {
        $children[] = $link($v, '{{page:' . $page_key . '}}?' . $query(array($param => $v)), 'btn btn-sm btn-outline-secondary');
    }
    return $el('nav', 'mb-4', lang('Filters'), array(
        $el('div', 'd-flex flex-wrap align-items-center gap-2', $label, $children),
    ));
};

// A number field of a form: a number kind, so the lists sort it as a
// number.
$hs_number = function ($id, $name, $min) use ($input, $attr) {
    return $input($id, 'number', $name, 'off', true, array('kind' => 'number'), array($attr('min', (string)$min), $attr('step', '1')));
};

// A long field of a record page under its heading, its lines kept; the
// block leaves when the record has no value.
$hs_long_field = function ($title, $token, $sample, $class = 'mb-4') use ($el, $heading, $bind) {
    return $el('div', 'pg-hide-if-empty ' . $class, $title, array(
        $heading('h2', $title, 'phs-display h4 mb-2'),
        $el('div', 'pg-field-value lh-lg', lang('Value'), array(), array_merge(array('text' => $sample), $bind($token))),
    ));
};

// A staff page that adds a record: its header and the form in a frame.
$hs_form_page = function ($title, $lead, $widget_key) use ($staff_page, $page_header, $el, $container, $row, $col, $widget) {
    return $staff_page(array(
        $page_header($title, $lead),
        $el('section', 'py-5', lang('Content'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $el('div', 'p-4 rounded-3 border', lang('Form Card'), array($widget($widget_key))),
                    ), '', '8'),
                ), '', 'center'),
            )),
        )),
    ));
};

// What a service page says about the work beyond its summary.
$service_texts = array(
    'deep_clean'     => lang('A deep clean reaches the places a weekly clean leaves: the top of the cupboards, the inside of the oven, the grout between the tiles and the dust behind the sofa. Two cleaners work room by room and finish with the floors, so you come home to a place that feels new.'),
    'move_out'       => lang('Moving out is busy enough. Our crew cleans the empty home from the ceilings to the skirting boards, inside every cupboard and appliance, so it passes a landlord\'s inspection or welcomes a buyer. If anything is missed, we come back.'),
    'leak_repair'    => lang('A small leak wastes water and can damage floors and ceilings. Our plumber finds where the water comes from, replaces the worn washer, seal or fitting and tests the repair under pressure before leaving.'),
    'water_heater'   => lang('A yearly service keeps a boiler or water heater safe and saves energy. We clean the burner and filters, check the flue and the safety devices, set the pressure and leave you a written report of what we found.'),
    'sockets_lights' => lang('More sockets in the kitchen, a light over the dining table or a dimmer in the bedroom: our electrician fits them safely, tests every circuit touched and takes the old fittings away.'),
    'safety_check'   => lang('A full test of your fuse box, wiring, sockets and earthing, written up in a certificate a landlord, a buyer or an insurer accepts. If something needs fixing, you get a price for it on the spot.'),
    'handyman'       => lang('Shelves to put up, a wardrobe to build, a handle that keeps falling off: book our handyman by the hour and give them your list. They bring the tools and the common fixings, and work through it in the order you choose.'),
    'doors_windows'  => lang('Doors that stick in winter, windows that let the draught in and locks that turn badly. We adjust hinges, plane doors, replace handles and locks, and seal the gaps, so everything opens and closes as it should.'),
);

// What a member page says about them beyond the card's line.
$team_bios = array(
    lang('Murat trained as a plumber at sixteen and ran his own van for years before starting the company. He still takes the leaks nobody else can find, and checks the work of every new plumber himself.'),
    lang('Selin has cleaned homes, offices and holiday flats for over a decade. She plans the cleaning crews\' days, trains every new cleaner and chooses the products we use.'),
    lang('Daniel is a qualified electrician with a decade in new builds behind him. He carries out our safety checks, signs the certificates and keeps the team up to date with the rules.'),
    lang('Emre trained as a carpenter and then learnt a bit of every trade. Doors, windows, shelves and furniture are his favourite jobs, and he rarely leaves without fixing one thing more.'),
);

// ── Pages ───────────────────────────────────────────────────────────────

// Every service, narrowed by category from the address.
$service_pages[] = array(
    'key'              => 'services',
    'name'             => lang('services'),
    'folder'           => 'public',
    'title'            => lang('Our Services'),
    'meta_description' => lang('Cleaning, plumbing, electrical and repair services with what each includes, how long it takes and its starting price.'),
    'tree'             => $page(array(
        $page_header(lang('Our Services'), lang('Every service with what it includes and its starting price. Choose a category to narrow the list.')),
        $el('section', 'py-5', lang('Our Services'), array(
            $container(array(
                $hs_filter_bar('services', lang('Category'), 'category', $category_titles),
                $widget('services_list'),
            )),
        )),
    )),
);

// One service: the record and under it the customers' reviews (stars and
// words, newest first), which the company is told about.
$service_pages[] = array(
    'key'              => 'service',
    'name'             => lang('service-details'),
    'folder'           => 'public',
    'title'            => lang('Service Details'),
    'meta_description' => lang('A service of the company: what it includes, how long it takes, its starting price and customer reviews.'),
    'sitemap'          => false,
    'comments'         => array('label' => lang('Review'), 'allow_new' => true, 'rating' => true, 'auto_publish' => true, 'show_date' => true, 'login' => true,
                                'email_page' => '{{tab:email_reply}}', 'email_subject' => lang('There is a new review of a service'),
                                'notify_email' => '{{site_email}}'),
    'tree'             => $page(array(
        $widget('service_detail'),
        $comments_section(lang('Reviews'), 'service_reviews'),
    )),
);

// The team, A to Z.
$service_pages[] = array(
    'key'              => 'team',
    'name'             => lang('our-team'),
    'folder'           => 'public',
    'title'            => lang('Our Team'),
    'meta_description' => lang('The cleaners, plumbers, electricians and handymen who come to your home: trained, insured and our own.'),
    'tree'             => $page(array(
        $page_header(lang('Our Team'), lang('The people who come to your home. Every one is trained, insured and part of our own team.')),
        $el('section', 'py-5', lang('Our Team'), array(
            $container(array(
                $widget('team_list'),
            )),
        )),
    )),
);

// One member of the team.
$service_pages[] = array(
    'key'              => 'team_member',
    'name'             => lang('team-member'),
    'folder'           => 'public',
    'title'            => lang('Team Member'),
    'meta_description' => lang('A member of our team: their trade, their years of experience and the work they do.'),
    'sitemap'          => false,
    'tree'             => $page(array(
        $widget('team_detail'),
    )),
);

// The staff page that adds a service. The form is the Services collection;
// the sample services are written in when the form is made, so the lists
// do not open empty (pg_cf_seed_sample_records()).
$sample_services = array();
foreach ($services as $key => $s) {
    $sample_services[] = array(
        'name'        => $s['name'],
        'category'    => $categories[$s['category']]['title'],
        'duration'    => $s['duration'],
        'crew'        => $crew_text($key),
        'price'       => $price_text($key),
        'summary'     => $s['summary'],
        'description' => $service_texts[$key],
        'includes'    => $includes_text($key),
        'photo'       => $photo('service-' . $key, 1200, 900),
        'featured'    => $s['featured'] ? $hs_featured_choices[0] : $hs_featured_choices[1],
    );
}
$service_pages[] = array(
    'key'     => 'service_new',
    'name'    => lang('new-service'),
    'folder'  => 'private',
    'title'   => lang('New Service'),
    'search'  => false,
    'sitemap' => false,
    'noindex' => true,
    'form'    => array(
        'form_name'            => lang('Our Services'),
        'collection'           => 1,
        'confirmation_message' => lang('The service is published.'),
        'confirmation_page_id' => '{{tab:services}}',
        'sample_records'       => $sample_services,
        // The photo is the service's share image (pg_cf_share_image_field_id()).
        'share_image_field'    => 'photo',
    ),
    'tree'    => $hs_form_page(lang('New Service'),
        lang('Add the service with what it includes, its price and a photo; it appears in the list as soon as you send it. Mark it featured from its Edit button.'),
        'service_form'),
);

// The staff page that adds a team member. The form is the Team collection;
// the sample members are written in when the form is made.
$sample_team = array();
foreach ($team as $n => $m) {
    $sample_team[] = array(
        'name'    => $m['name'],
        'role'    => $m['role'],
        'years'   => (string)$m['years'],
        'summary' => $m['summary'],
        'bio'     => $team_bios[$n],
        'photo'   => $portrait($m['name']),
    );
}
$service_pages[] = array(
    'key'     => 'team_new',
    'name'    => lang('new-team-member'),
    'folder'  => 'private',
    'title'   => lang('New Team Member'),
    'search'  => false,
    'sitemap' => false,
    'noindex' => true,
    'form'    => array(
        'form_name'            => lang('Team'),
        'collection'           => 1,
        'confirmation_message' => lang('The team member is published.'),
        'confirmation_page_id' => '{{tab:team}}',
        'sample_records'       => $sample_team,
        'share_image_field'    => 'photo',
    ),
    'tree'    => $hs_form_page(lang('New Team Member'),
        lang('Add a member of the team with their trade and a photo; they appear on the team page as soon as you send it.'),
        'team_form'),
);

// ── Widget pieces ───────────────────────────────────────────────────────

// Beside the service's words: the price, the duration and the crew, the
// way to book a visit for this service or ask for an estimate, and the
// call-out note.
$service_aside = function ($sample_key) use ($el, $heading, $para, $link, $span, $icon, $bind, $hs_page_link, $price_text, $service_facts, $money, $call_out) {
    return $el('aside', 'card border-0 shadow-sm rounded-3', lang('Price Card'), array(
        $el('div', 'card-body p-4', lang('Card Body'), array(
            $para(lang('Starting price'), 'small text-uppercase fw-semibold text-body-secondary mb-1'),
            $heading('h2', $price_text($sample_key), 'phs-price h4 fw-semibold mb-3', $bind('price')),
            $service_facts($sample_key, 'd-flex flex-column gap-2 small text-body-secondary mb-4'),
            // Open the visit booking and the estimate request with this
            // service chosen (?service=).
            $link(lang('Book a visit for this service'), '#', 'btn btn-primary w-100 mb-2', array(),
                $hs_page_link('book', array('service' => 'name'))),
            $link(lang('Get an Estimate'), '#', 'btn btn-outline-primary w-100 mb-3', array(),
                $hs_page_link('estimate', array('service' => 'name'))),
            $el('p', 'd-flex gap-2 small text-body-secondary mb-0', lang('Note'), array(
                $icon('bi-info-circle', 'mt-1'),
                $span(sprintf(lang('We confirm the price before any work starts. Outside the centre a call-out charge of %s per visit applies.'), $money($call_out)), '', lang('Text')),
            )),
        )),
    ));
};

$service_widgets = array(

    // The staff's Services form: one service. Featured starts as No.
    'service_form' => array(
        'page'   => 'service_new',
        'slug'   => 'form',
        'config' => array('regionType' => 'custom_form', 'form_source' => 'page'),
        'tree'   => $widget_root(array(
            $form_step(1, lang('The service')),
            $field('hs-service-name', lang('Name'), pg_cf_with_role($input('hs-service-name', 'text', 'name', 'off'), 'title')),
            $field('hs-service-category', lang('Category'), pg_cf_with_role($select('hs-service-category', 'category', lang('Choose a category…'), $category_titles), 'category')),
            $row(array(
                $col(array($field('hs-service-duration', lang('Duration'), $input('hs-service-duration', 'text', 'duration', 'off'), 'mb-3',
                    sprintf(lang('In words, for example %s.'), $services['leak_repair']['duration']))), '6'),
                $col(array($field('hs-service-crew', lang('Crew'), $input('hs-service-crew', 'text', 'crew', 'off'), 'mb-3',
                    sprintf(lang('In words, for example %s.'), $crew_text('deep_clean')))), '6'),
            ), '3'),
            $field('hs-service-price', lang('Price'), $input('hs-service-price', 'text', 'price', 'off'), 'mb-3',
                sprintf(lang('As the cards show it, for example %s.'), $price_text('leak_repair'))),
            $form_step(2, lang('Words')),
            $field('hs-service-summary', lang('Summary'), pg_cf_with_role($textarea('hs-service-summary', 'summary', 2), 'description'), 'mb-3',
                lang('Two lines for the cards of the lists.')),
            $field('hs-service-description', lang('Description'), $textarea('hs-service-description', 'description', 5, false)),
            $field('hs-service-includes', lang('What it includes'), $textarea('hs-service-includes', 'includes', 4, false), 'mb-3', lang('One item on each line.')),
            $form_step(3, lang('Photo')),
            $field('hs-service-photo', lang('Photo'), pg_cf_with_role($file('hs-service-photo', 'photo', 'public', 'image/*'), 'media'), 'mb-3',
                lang('The photo of the cards and the share image.')),
            $field('hs-service-featured', lang('Featured'), $select('hs-service-featured', 'featured', '', $hs_featured_choices, false, $hs_featured_choices[1], array('office_use_only' => true)),
                'mb-3', lang('A featured service is shown on the home page.')),
            $submit(lang('Publish')),
            $loop(array()),
        )),
    ),

    // The staff's Team form: one member.
    'team_form' => array(
        'page'   => 'team_new',
        'slug'   => 'form',
        'config' => array('regionType' => 'custom_form', 'form_source' => 'page'),
        'tree'   => $widget_root(array(
            $form_step(1, lang('The person')),
            $field('hs-team-name', lang('Name'), pg_cf_with_role($input('hs-team-name', 'text', 'name', 'off'), 'title')),
            $row(array(
                $col(array($field('hs-team-role', lang('Role'), pg_cf_with_role($select('hs-team-role', 'role', lang('Choose a role…'), $team_roles), 'category'))), '6'),
                $col(array($field('hs-team-years', lang('Years in the trade'), $hs_number('hs-team-years', 'years', 0))), '6'),
            ), '3'),
            $field('hs-team-summary', lang('Summary'), pg_cf_with_role($textarea('hs-team-summary', 'summary', 2), 'description'), 'mb-3',
                lang('One line for the team cards.')),
            $field('hs-team-bio', lang('About them'), $textarea('hs-team-bio', 'bio', 5, false)),
            $form_step(2, lang('Photo')),
            $field('hs-team-photo', lang('Photo'), pg_cf_with_role($file('hs-team-photo', 'photo', 'public', 'image/*'), 'media'), 'mb-3',
                lang('A portrait for the team cards and the share image.')),
            $submit(lang('Publish')),
            $loop(array()),
        )),
    ),

    // Every service, filtered by the address (category), A to Z, with a
    // search over the words of the services.
    'services_list' => array(
        'page'   => 'services',
        'slug'   => lang('form-list'),
        'config' => $hs_list_config('service_new', 'service', 'name', 'ASC',
            lang('No service in this category yet. Ask us for an estimate: we take on more than we list.'), array(
            'items_per_page' => 12,
            'filters'        => array($hs_filter('category', 'is equal to', '', 'category')),
            'search_enabled' => true,
            'search_fields'  => array('name', 'category', 'summary', 'description', 'includes'),
            'search_width'   => 'full',
            'search_label'   => lang('Search the services: a dripping tap, an oven, a new light...'),
        )),
        'tree'   => $service_grid(),
    ),

    // The home page's featured services, three at most.
    'home_services' => array(
        'page'   => 'home',
        'slug'   => lang('form-list'),
        'config' => $hs_list_config('service_new', 'service', 'name', 'ASC', lang('No featured services yet.'), array(
            'items_per_page' => 3,
            'max_results'    => 3,
            'filters'        => array($hs_filter('featured', 'is equal to', $hs_featured_choices[0])),
        )),
        'tree'   => $service_grid(),
    ),

    // The whole team, the longest in the trade first.
    'team_list' => array(
        'page'   => 'team',
        'slug'   => lang('form-list'),
        'config' => $hs_list_config('team_new', 'team_member', 'years', 'DESC', lang('The team is on its way.'), array(
            'items_per_page' => 24,
        )),
        'tree'   => $team_grid(),
    ),

    // The home page's team, four at most.
    'home_team' => array(
        'page'   => 'home',
        'slug'   => lang('form-list'),
        'config' => $hs_list_config('team_new', 'team_member', 'years', 'DESC', lang('The team is on its way.'), array(
            'items_per_page' => 4,
            'max_results'    => 4,
        )),
        'tree'   => $team_grid(),
    ),

    // The reviews under a service: stars and words, newest first, one level
    // of answers so the company can reply to a review.
    'service_reviews' => array(
        'page'   => 'service',
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
            'empty'     => lang('No reviews of this service yet. Had it done by us? Tell others how it went.'),
            'heading'   => lang('Review this service'),
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
$hs_toolbar = function ($page_key, $text) use ($el, $icon, $span, $edit_button) {
    return $el('div', 'd-flex flex-wrap align-items-center justify-content-between gap-2 mb-3', lang('Toolbar'), array(
        $el('a', 'd-inline-flex align-items-center gap-1 small fw-semibold link-primary text-decoration-none', lang('Link'), array(
            $icon('bi-arrow-left'),
            $span($text, '', lang('Link Text')),
        ), array('href' => '{{page:' . $page_key . '}}')),
        $edit_button(),
    ));
};
// What a record page shows when its record is not there.
$hs_not_found = function ($icon_name, $title, $text, $back_text, $back_page) use ($el, $icon, $heading, $para, $link, $bind, $show_when) {
    return $el('div', 'text-center py-5', lang('Not Found'), array(
        $icon($icon_name, 'display-5 text-body-tertiary'),
        $heading('h2', $title, 'h3 fw-semibold mt-3'),
        $para($text, 'text-body-secondary mb-4', $bind('not_found')),
        $link($back_text, '{{page:' . $back_page . '}}', 'btn btn-primary px-4'),
    ), $show_when('record_not_found'));
};

// One service: the category, the name, the summary, the photo, the
// description and what it includes, and beside them the price with the
// ways to book it.
$service_sample_key = 'leak_repair';
$service_sample = $services[$service_sample_key];
$service_not_found = lang('This service could not be found. It may have been renamed or taken off the list.');
$service_widgets['service_detail'] = array(
    'page'   => 'service',
    'slug'   => lang('form-detail'),
    'config' => array(
        'regionType'          => 'form_item_view',
        'custom_form_page_id' => '{{tab:service_new}}',
        'access_control'      => 'public',
        'not_found_message'   => $service_not_found,
    ),
    'tree'   => $widget_root(array(
        $container(array(
            $hs_not_found('bi-tools', lang('Service not found'), $service_not_found, lang('See our services'), 'services'),
            $el('article', '', lang('Service Details'), array(
                $hs_toolbar('services', lang('All Services')),
                $span($categories[$service_sample['category']]['title'], 'badge bg-primary-subtle text-primary-emphasis mb-2', lang('Category'), $bind('category')),
                $heading('h1', $service_sample['name'], 'phs-display display-6 mb-2', $bind('name')),
                $para($service_sample['summary'], 'lead text-body-secondary mb-4', $bind('summary')),
                $row(array(
                    $col(array(
                        $el('div', 'mb-4', lang('Photo'), array(
                            $optional_image('photo', 'name', 'rounded-3 object-fit-cover', 'ratio ratio-16x9',
                                $photo('service-' . $service_sample_key, 1200, 675), lang('Photo')),
                        )),
                        $hs_long_field(lang('About this service'), 'description', $service_texts[$service_sample_key]),
                        $el('div', 'pg-hide-if-empty p-4 rounded-3 bg-body-tertiary mb-4', lang('What it includes'), array(
                            $heading('h2', lang('What it includes'), 'phs-display h5 mb-2'),
                            $el('div', 'pg-field-value lh-lg', lang('Value'), array(), array_merge(array('text' => $includes_text($service_sample_key)), $bind('includes'))),
                        )),
                    ), '', '8'),
                    $col(array($service_aside($service_sample_key)), '', '4'),
                ), '4', '', 'g-lg-5 mb-4'),
            ), $show_when('record_found')),
        ), 'py-5'),
    )),
);

// One member: the portrait, the role, the name, the years in the trade,
// what they do, and the way to book a visit.
$member_sample = $team[0];
$member_not_found = lang('This team member could not be found. They may have moved on.');
$service_widgets['team_detail'] = array(
    'page'   => 'team_member',
    'slug'   => lang('form-detail'),
    'config' => array(
        'regionType'          => 'form_item_view',
        'custom_form_page_id' => '{{tab:team_new}}',
        'access_control'      => 'public',
        'not_found_message'   => $member_not_found,
    ),
    'tree'   => $widget_root(array(
        $container(array(
            $hs_not_found('bi-person', lang('Team member not found'), $member_not_found, lang('Meet the team'), 'team'),
            $el('article', '', lang('Team Member'), array(
                $hs_toolbar('team', lang('Our Team')),
                $row(array(
                    $col(array(
                        $optional_image('photo', 'name', 'rounded-3 object-fit-cover', 'ratio ratio-1x1',
                            $portrait($member_sample['name']), $member_sample['name']),
                    ), '5', '4'),
                    $col(array(
                        $span($member_sample['role'], 'badge bg-primary-subtle text-primary-emphasis mb-2', lang('Role'), $bind('role')),
                        $heading('h1', $member_sample['name'], 'phs-display display-6 mb-2', $bind('name')),
                        $el('p', 'd-flex align-items-center gap-2 text-body-secondary mb-3', lang('Experience'), array(
                            $icon('bi-award', 'text-primary'),
                            $span((string)$member_sample['years'], 'fw-semibold', lang('Value'), $bind('years')),
                            $span(lang('years in the trade'), '', lang('Unit')),
                        )),
                        $para($member_sample['summary'], 'lead mb-4', $bind('summary')),
                        $hs_long_field(lang('About them'), 'bio', $team_bios[0]),
                        $el('div', 'd-flex flex-wrap gap-2', lang('Buttons'), array(
                            $link(lang('Book a Visit'), '{{page:book}}', 'btn btn-primary px-4'),
                            $link(lang('Our Services'), '{{page:services}}', 'btn btn-outline-primary px-4'),
                        )),
                    ), '7', '8'),
                ), '4', '', 'g-lg-5 align-items-start'),
            ), $show_when('record_found')),
        ), 'py-5'),
    )),
);

return array('pages' => $service_pages, 'widgets' => $service_widgets);
