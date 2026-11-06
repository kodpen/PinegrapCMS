<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Construction Firm", its projects portfolio: the staff
 * page that adds a project (the Projects form, a collection, with the
 * sample projects written in when it is made), the list of every project
 * with its sector and service filters, the project's own page and the list
 * the home page places. Included after parts.php in the same scope;
 * returns array('pages' => ..., 'widgets' => ...).
 *
 * A project is a record of the Projects form. Sector, service and status
 * are pick lists whose answers are the words parts.php gives, so a link
 * with ?sector= or ?service= (the sector tiles, the service pages, the
 * footer) filters the list by the words a visitor sees. Size and duration
 * are words, never a date. The scope is one item per line and the client's
 * words are the client's own. Featured is the firm's choice (office use
 * only): the home page shows the featured projects.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

$project_pages = array();

// ── Pieces ──────────────────────────────────────────────────────────────

// A filter row of a list's settings: the field, how it compares and the
// value, or where the value comes from (a URL parameter, left out while
// the parameter is not in the address).
$cn_filter = function ($field, $operator, $value, $param = '') {
    $row = array('field' => $field, 'operator' => $operator, 'value' => $value, 'dynamic_value' => $param !== '' ? 'url parameter' : '');
    if ($param !== '') $row['dynamic_value_attribute'] = $param;
    return $row;
};
// The answers of the Featured list: the first marks a project for the home.
$cn_featured_choices = array(lang('Yes'), lang('No'));

// A link to a template page that sends values of the record it is drawn
// in: $params maps the address name to the field it takes (the renderer's
// __page_link).
$cn_page_link = function ($page_key, $params) {
    $list = array();
    foreach ($params as $name => $token) $list[] = array('name' => $name, 'token' => $token);
    return array(
        '_bindings'   => array('href' => '__page_link'),
        '_bindParams' => array('href' => array('page_id' => '{{tab:' . $page_key . '}}', 'params' => $list)),
    );
};

// A list of projects: the Projects form, the project page, A to Z, and
// the words shown when nothing matches.
$cn_project_list = function ($empty, $more) {
    return array_merge(array(
        'regionType'          => 'form_list_view',
        'custom_form_page_id' => '{{tab:project_new}}',
        'detail_page_id'      => '{{tab:project}}',
        'order_by_field'      => 'name',
        'order_by_direction'  => 'ASC',
        'empty_message'       => $empty,
    ), $more);
};
// The project cards of a list, in rows of three.
$project_grid = function () use ($widget_root, $row, $loop, $project_card) {
    return $widget_root(array($row(array($loop(array($project_card()))), '4')));
};

// The links above the list that narrow it: one per answer of each pick
// list, each opening the list with the filter set.
$cn_filter_bar = function ($groups) use ($el, $link, $span, $query) {
    $navs = array();
    foreach ($groups as $g) {
        $children = array($span($g['label'], 'small fw-semibold text-body-secondary me-1', lang('Label')));
        $children[] = $link(lang('All'), '{{page:projects}}', 'btn btn-sm btn-outline-secondary rounded-pill');
        foreach ($g['values'] as $v) {
            $children[] = $link($v, '{{page:projects}}?' . $query(array($g['param'] => $v)), 'btn btn-sm btn-outline-secondary rounded-pill');
        }
        $navs[] = $el('div', 'd-flex flex-wrap align-items-center gap-2 mb-2', $g['label'], $children);
    }
    return $el('nav', 'mb-4', lang('Filters'), $navs);
};

// A photo of the project page, in a frame that leaves when the record has
// none.
$cn_photo = function ($field, $seed) use ($optional_image, $photo) {
    return $optional_image($field, 'name', 'rounded-3 object-fit-cover', 'ratio ratio-4x3', $photo($seed, 1200, 900), lang('Photo'));
};

// ── Pages ───────────────────────────────────────────────────────────────

// Every project, narrowed by sector and service from the address.
$project_pages[] = array(
    'key'              => 'projects',
    'name'             => lang('projects'),
    'folder'           => 'public',
    'title'            => lang('Our Projects'),
    'meta_description' => lang('Homes, offices, schools, warehouses, roads and bridges we have built, with the facts of each project.'),
    'tree'             => $page(array(
        $page_header(lang('Our Projects'), lang('Buildings and works we have handed over or have on site now. Choose a sector or a service to narrow the list.')),
        $el('section', 'py-5', lang('Projects'), array(
            $container(array(
                $cn_filter_bar(array(
                    array('label' => lang('Sector'), 'param' => 'sector', 'values' => $sector_titles),
                    array('label' => lang('Service Type'), 'param' => 'service', 'values' => $service_titles),
                )),
                $widget('projects_list'),
            )),
        )),
    )),
);

// One project: its record.
$project_pages[] = array(
    'key'              => 'project',
    'name'             => lang('project'),
    'folder'           => 'public',
    'title'            => lang('Project'),
    'meta_description' => lang('A project of the firm: the facts, the photos, the scope of the work and what the client said.'),
    'sitemap'          => false,
    'tree'             => $page(array(
        $widget('project_detail'),
    )),
);

// The staff page that adds a project. The form is the Projects
// collection; the sample projects are written in when the form is made,
// so the lists do not open empty (pg_cf_seed_sample_records()).
$sample_projects = array();
foreach ($projects as $key => $p) {
    $sample_projects[] = array(
        'name'         => $p['name'],
        'sector'       => $sectors[$p['sector']]['title'],
        'service'      => $services[$p['service']]['title'],
        'location'     => $p['location'],
        'status'       => $project_statuses[$p['status']],
        'size'         => $p['size'],
        'duration'     => $p['duration'],
        'client'       => $p['client'],
        'summary'      => $p['summary'],
        'scope'        => $scope_text($key),
        'client_words' => $p['quote'],
        'photo_1'      => $photo('project-' . $key, 1200, 900),
        'photo_2'      => $photo('project-' . $key . '-2', 1200, 900),
        'photo_3'      => $photo('project-' . $key . '-3', 1200, 900),
        'featured'     => $p['featured'] ? $cn_featured_choices[0] : $cn_featured_choices[1],
    );
}
$project_pages[] = array(
    'key'     => 'project_new',
    'name'    => lang('new-project'),
    'folder'  => 'private',
    'title'   => lang('New Project'),
    'search'  => false,
    'sitemap' => false,
    'noindex' => true,
    'form'    => array(
        'form_name'            => lang('Projects'),
        'collection'           => 1,
        'confirmation_message' => lang('The project is published.'),
        'confirmation_page_id' => '{{tab:projects}}',
        'sample_records'       => $sample_projects,
        // The first photo is the project's share image (pg_cf_share_image_field_id()).
        'share_image_field'    => 'photo_1',
    ),
    'tree'    => $staff_page(array(
        $page_header(lang('New Project'), lang('Add the project with its facts and photos; it appears in the list as soon as you send it. Mark it featured from its Edit button.')),
        $el('section', 'py-5', lang('Content'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $el('div', 'p-4 rounded-3 border', lang('Form Card'), array($widget('project_form'))),
                    ), '', '8'),
                ), '', 'center'),
            )),
        )),
    )),
);

// ── Widgets ─────────────────────────────────────────────────────────────

$project_widgets = array(

    // The staff's Projects form: one project. Featured starts as No.
    'project_form' => array(
        'page'   => 'project_new',
        'slug'   => 'form',
        'config' => array('regionType' => 'custom_form', 'form_source' => 'page'),
        'tree'   => $widget_root(array(
            $form_step(1, lang('The project')),
            $field('cn-project-name', lang('Project Name'), pg_cf_with_role($input('cn-project-name', 'text', 'name', 'off'), 'title')),
            $row(array(
                $col(array($field('cn-project-sector', lang('Sector'), pg_cf_with_role($select('cn-project-sector', 'sector', lang('Choose a sector…'), $sector_titles), 'category'))), '6'),
                $col(array($field('cn-project-service', lang('Service Type'), $select('cn-project-service', 'service', lang('Choose a service…'), $service_titles))), '6'),
                $col(array($field('cn-project-location', lang('Location'), $input('cn-project-location', 'text', 'location', 'off'))), '6'),
                $col(array($field('cn-project-status', lang('Status'), $select('cn-project-status', 'status', '', $status_titles, true, $status_titles[0]))), '6'),
            ), '3'),
            $field('cn-project-size', lang('Project Size'), $input('cn-project-size', 'text', 'size', 'off'), 'mb-3',
                sprintf(lang('In words, for example %s.'), $projects['harbour_view']['size'])),
            $field('cn-project-duration', lang('Duration'), $input('cn-project-duration', 'text', 'duration', 'off'), 'mb-3',
                lang('In words, never a date, for example 22 months.')),
            $field('cn-project-client', lang('Client'), $input('cn-project-client', 'text', 'client', 'off', false)),
            $form_step(2, lang('Words')),
            $field('cn-project-summary', lang('Summary'), pg_cf_with_role($textarea('cn-project-summary', 'summary', 2), 'description'), 'mb-3',
                lang('One or two lines for the cards of the lists.')),
            $field('cn-project-scope', lang('Scope of Work'), $textarea('cn-project-scope', 'scope', 5, false), 'mb-3', lang('One item of the work on each line.')),
            $field('cn-project-words', lang('The Client\'s Words'), $textarea('cn-project-words', 'client_words', 3, false), 'mb-3',
                lang('What the client said about the work, with their consent.')),
            $form_step(3, lang('Photos')),
            $field('cn-project-photo-1', lang('First Photo'), pg_cf_with_role($file('cn-project-photo-1', 'photo_1', 'public', 'image/*'), 'media'), 'mb-3',
                lang('The photo of the cards and the share image.')),
            $row(array(
                $col(array($field('cn-project-photo-2', lang('Second Photo'), $file('cn-project-photo-2', 'photo_2', 'public', 'image/*'))), '6'),
                $col(array($field('cn-project-photo-3', lang('Third Photo'), $file('cn-project-photo-3', 'photo_3', 'public', 'image/*'))), '6'),
            ), '3'),
            $field('cn-project-featured', lang('Featured'), $select('cn-project-featured', 'featured', '', $cn_featured_choices, false, $cn_featured_choices[1], array('office_use_only' => true)),
                'mb-3', lang('A featured project is shown on the home page.')),
            $submit(lang('Publish')),
            $loop(array()),
        )),
    ),

    // Every project, filtered by the address (sector, service), A to Z,
    // with a search over the words of the projects.
    'projects_list' => array(
        'page'   => 'projects',
        'slug'   => lang('form-list'),
        'config' => $cn_project_list(lang('No project matches these choices yet. Try another sector or service, or ask us about yours.'), array(
            'items_per_page' => 12,
            'filters'        => array(
                $cn_filter('sector', 'is equal to', '', 'sector'),
                $cn_filter('service', 'is equal to', '', 'service'),
            ),
            'search_enabled' => true,
            'search_fields'  => array('name', 'sector', 'service', 'location', 'summary', 'scope'),
            'search_width'   => 'full',
            'search_label'   => lang('Search the projects: a school, a bridge, a restoration...'),
        )),
        'tree'   => $project_grid(),
    ),

    // The home page's featured projects, three at most.
    'home_projects' => array(
        'page'   => 'home',
        'slug'   => lang('form-list'),
        'config' => $cn_project_list(lang('No featured projects yet.'), array(
            'items_per_page' => 3,
            'max_results'    => 3,
            'filters'        => array($cn_filter('featured', 'is equal to', $cn_featured_choices[0])),
        )),
        'tree'   => $project_grid(),
    ),
);

// One project: the sector and status, the name, the summary, the facts,
// the photos, the scope of the work, the client's words and beside them
// the way to ask for a quote for the same service or see more of the
// sector.
$project_sample_key = 'harbour_view';
$project_sample = $projects[$project_sample_key];
$project_not_found = lang('This project could not be found. It may have been renamed or taken off the list.');
$project_widgets['project_detail'] = array(
    'page'   => 'project',
    'slug'   => lang('form-detail'),
    'config' => array(
        'regionType'          => 'form_item_view',
        'custom_form_page_id' => '{{tab:project_new}}',
        'access_control'      => 'public',
        'not_found_message'   => $project_not_found,
    ),
    'tree'   => $widget_root(array(
        $container(array(
            $el('div', 'text-center py-5', lang('Not Found'), array(
                $icon('bi-building', 'display-5 text-body-tertiary'),
                $heading('h2', lang('Project not found'), 'h3 fw-semibold mt-3'),
                $para($project_not_found, 'text-body-secondary mb-4', $bind('not_found')),
                $link(lang('See Our Projects'), '{{page:projects}}', 'btn btn-primary px-4'),
            ), $show_when('record_not_found')),
            $el('article', '', lang('Project'), array(
                $el('div', 'd-flex flex-wrap align-items-center justify-content-between gap-2 mb-3', lang('Toolbar'), array(
                    $el('a', 'd-inline-flex align-items-center gap-1 small fw-semibold link-primary text-decoration-none', lang('Link'), array(
                        $icon('bi-arrow-left'),
                        $span(lang('All Projects'), '', lang('Link Text')),
                    ), array('href' => '{{page:projects}}')),
                    $edit_button(),
                )),
                $el('div', 'd-flex flex-wrap gap-2 mb-2', lang('Badges'), array(
                    $span($sectors[$project_sample['sector']]['title'], 'badge rounded-pill bg-primary-subtle text-primary-emphasis', lang('Sector'), $bind('sector')),
                    $span($project_statuses[$project_sample['status']], 'badge rounded-pill text-bg-dark', lang('Status'), $bind('status')),
                )),
                $heading('h1', $project_sample['name'], 'pcn-display display-6 mb-2', $bind('name')),
                $para($project_sample['summary'], 'lead text-body-secondary mb-3', $bind('summary')),
                $project_facts($project_sample_key, 'd-flex flex-wrap gap-3 text-body-secondary mb-4'),
                // The photos: the first large, the other two beside it.
                $row(array(
                    $col(array($cn_photo('photo_1', 'project-' . $project_sample_key)), '', '8'),
                    $col(array(
                        $el('div', 'd-flex flex-column gap-3 h-100', lang('Photos'), array(
                            $cn_photo('photo_2', 'project-' . $project_sample_key . '-2'),
                            $cn_photo('photo_3', 'project-' . $project_sample_key . '-3'),
                        )),
                    ), '', '4'),
                ), '3', '', 'mb-5'),
                $row(array(
                    $col(array(
                        $el('div', 'pg-hide-if-empty mb-4', lang('Scope of Work'), array(
                            $heading('h2', lang('Scope of Work'), 'pcn-display h4 mb-2'),
                            $el('div', 'pg-field-value lh-lg', lang('Value'), array(), array_merge(array('text' => $scope_text($project_sample_key)), $bind('scope'))),
                        )),
                        $el('figure', 'pg-hide-if-empty p-4 rounded-3 bg-body-tertiary border-start border-4 border-primary mb-4', lang('The Client\'s Words'), array(
                            $el('blockquote', 'blockquote mb-2', lang('Blockquote'), array(
                                $el('p', 'pg-field-value fs-5 mb-0', lang('Value'), array(), array_merge(array('text' => $project_sample['quote']), $bind('client_words'))),
                            )),
                            $el('figcaption', 'small fw-semibold text-body-secondary mb-0', lang('Client'), array(), array_merge(array('text' => $project_sample['client']), $bind('client'))),
                        )),
                    ), '', '8'),
                    $col(array(
                        $el('aside', 'card border-0 shadow-sm rounded-3', lang('Project Card'), array(
                            $el('div', 'card-body p-4', lang('Card Body'), array(
                                $heading('h2', lang('Planning something similar?'), 'pcn-display h5 mb-2'),
                                $para(lang('Tell us about your project: an engineer visits the site free of charge and you receive a fixed-price quote.'), 'small text-body-secondary mb-3'),
                                // Opens the quote request with this
                                // project's service chosen (?service=).
                                $link(lang('Request a Quote'), '#', 'btn btn-primary w-100 mb-2', array(),
                                    $cn_page_link('quote', array('service' => 'service'))),
                                // Opens the list on this project's sector
                                // (?sector=).
                                $link(lang('More projects in this sector'), '#', 'btn btn-outline-secondary w-100', array(),
                                    $cn_page_link('projects', array('sector' => 'sector'))),
                            )),
                        )),
                    ), '', '4'),
                ), '4', '', 'g-lg-5 mb-4'),
            ), $show_when('record_found')),
        ), 'py-5'),
    )),
);

return array('pages' => $project_pages, 'widgets' => $project_widgets);
