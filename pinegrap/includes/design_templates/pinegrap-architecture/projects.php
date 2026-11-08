<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Architecture & Interiors Studio", its projects
 * portfolio: the staff page that adds a project (the Projects form, a
 * collection, with the sample projects written in when it is made), the
 * list of every project with its type and location filters, the project's
 * own page and the list the home page places. Included after parts.php in
 * the same scope; returns array('pages' => ..., 'widgets' => ...).
 *
 * A project is a record of the Projects form. Type, location, service and
 * stage are pick lists whose answers are the words parts.php gives, so a
 * link with ?type= or ?location= (the type tiles, the place buttons, the
 * footer) filters the list by the words a visitor sees. The setting and
 * the size are words, never a date. The brief is the client's question,
 * the credits one "Role: Name" line per collaborator, the client's words
 * their own. Featured is the studio's choice (office use only): the home
 * page shows the featured projects.
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
$ai_filter = function ($field, $operator, $value, $param = '') {
    $row = array('field' => $field, 'operator' => $operator, 'value' => $value, 'dynamic_value' => $param !== '' ? 'url parameter' : '');
    if ($param !== '') $row['dynamic_value_attribute'] = $param;
    return $row;
};
// The answers of the Featured list: the first marks a project for the home.
$ai_featured_choices = array(lang('Yes'), lang('No'));

// A link to a template page that sends values of the record it is drawn
// in: $params maps the address name to the field it takes (the renderer's
// __page_link).
$ai_page_link = function ($page_key, $params) {
    $list = array();
    foreach ($params as $name => $token) $list[] = array('name' => $name, 'token' => $token);
    return array(
        '_bindings'   => array('href' => '__page_link'),
        '_bindParams' => array('href' => array('page_id' => '{{tab:' . $page_key . '}}', 'params' => $list)),
    );
};

// A list of projects: the Projects form, the project page, A to Z, and
// the words shown when nothing matches.
$ai_project_list = function ($empty, $more) {
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
    return $widget_root(array($row(array($loop(array($project_card()))), '4', '', 'gy-5')));
};

// The links above the list that narrow it: one per answer of each pick
// list, each opening the list with the filter set.
$ai_filter_bar = function ($groups) use ($el, $link, $span, $query) {
    $navs = array();
    foreach ($groups as $g) {
        $children = array($span($g['label'], 'pai-eyebrow small text-uppercase fw-semibold text-body-secondary me-2', lang('Label')));
        $children[] = $link(lang('All'), '{{page:projects}}', 'btn btn-sm btn-outline-dark rounded-0');
        foreach ($g['values'] as $v) {
            $children[] = $link($v, '{{page:projects}}?' . $query(array($g['param'] => $v)), 'btn btn-sm btn-outline-dark rounded-0');
        }
        $navs[] = $el('div', 'd-flex flex-wrap align-items-center gap-2 mb-2', $g['label'], $children);
    }
    return $el('nav', 'mb-5', lang('Filters'), $navs);
};

// ── Pages ───────────────────────────────────────────────────────────────

// Every project, narrowed by type and location from the address.
$project_pages[] = array(
    'key'              => 'projects',
    'name'             => lang('projects'),
    'folder'           => 'public',
    'title'            => lang('Projects'),
    'meta_description' => lang('Houses, workplaces, hotels, libraries and public spaces the studio has designed, with the photographs and credits of each.'),
    'tree'             => $page(array(
        // The list opens on a plate of the work: the second plate of the
        // studio's book, the project it shows one click away.
        $plate_hero(lang('Our work'), lang('Projects'), lang('Buildings, interiors and places we have designed, completed or under way. Choose a type or a place to narrow the list.'), array(
            $link(lang('Start a Project'), '{{page:enquiry}}', 'btn btn-dark rounded-0 px-4'),
            $link(lang('How We Work'), '{{page:process}}', 'btn btn-outline-dark rounded-0 px-4'),
        ), $photo('project-print_works', 1800, 770), $projects['print_works']['name'], array(
            'no'    => '02',
            'name'  => $projects['print_works']['name'],
            'place' => $projects['print_works']['place'],
            'href'  => '{{page:projects}}?' . $query(array('type' => $project_types[$projects['print_works']['type']]['title'])),
        )),
        $el('section', 'py-5 border-top', lang('Projects'), array(
            $container(array(
                $rise($ai_filter_bar(array(
                    array('label' => lang('Type'), 'param' => 'type', 'values' => $type_titles),
                    array('label' => lang('Location'), 'param' => 'location', 'values' => $location_titles),
                )), 60),
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
    'meta_description' => lang('A project of the studio: the facts, the photographs, the brief, the credits and what the client said.'),
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
        'type'         => $project_types[$p['type']]['title'],
        'location'     => $locations[$p['location']],
        'service'      => $services[$p['service']]['title'],
        'stage'        => $project_stages[$p['stage']],
        'place'        => $p['place'],
        'size'         => $p['size'],
        'client'       => $p['client'],
        'summary'      => $p['summary'],
        'brief'        => $p['brief'],
        'credits'      => $credits_text($key),
        'client_words' => $p['quote'],
        'photo_1'      => $photo('project-' . $key, 1600, 1000),
        'photo_2'      => $photo('project-' . $key . '-2', 1600, 1000),
        'photo_3'      => $photo('project-' . $key . '-3', 1600, 1000),
        'photo_4'      => $photo('project-' . $key . '-4', 1600, 1000),
        'featured'     => $p['featured'] ? $ai_featured_choices[0] : $ai_featured_choices[1],
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
        $page_header(lang('New Project'), lang('Add the project with its facts, photographs and credits; it appears in the list as soon as you send it. Mark it featured from its Edit button.')),
        $el('section', 'py-5', lang('Content'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $el('div', 'p-4 border', lang('Form Card'), array($widget('project_form'))),
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
            $field('ai-project-name', lang('Project Name'), pg_cf_with_role($input('ai-project-name', 'text', 'name', 'off'), 'title')),
            $row(array(
                $col(array($field('ai-project-type', lang('Project Type'), pg_cf_with_role($select('ai-project-type', 'type', lang('Choose a type…'), $type_titles), 'category'))), '6'),
                $col(array($field('ai-project-location', lang('Location'), $select('ai-project-location', 'location', lang('Choose a place…'), $location_titles))), '6'),
                $col(array($field('ai-project-service', lang('Service'), $select('ai-project-service', 'service', lang('Choose a service…'), $service_titles))), '6'),
                $col(array($field('ai-project-stage', lang('Stage'), $select('ai-project-stage', 'stage', '', $stage_titles, true, $stage_titles[0]))), '6'),
            ), '3'),
            $field('ai-project-place', lang('Setting'), $input('ai-project-place', 'text', 'place', 'off', false), 'mb-3',
                sprintf(lang('Where it stands, in words, for example %s.'), $projects['courtyard_house']['place'])),
            $field('ai-project-size', lang('Project Size'), $input('ai-project-size', 'text', 'size', 'off', false), 'mb-3',
                sprintf(lang('In words, for example %s.'), $projects['print_works']['size'])),
            $field('ai-project-client', lang('Client'), $input('ai-project-client', 'text', 'client', 'off', false)),
            $form_step(2, lang('Words')),
            $field('ai-project-summary', lang('Summary'), pg_cf_with_role($textarea('ai-project-summary', 'summary', 2), 'description'), 'mb-3',
                lang('One or two lines for the cards of the lists.')),
            $field('ai-project-brief', lang('The Brief'), $textarea('ai-project-brief', 'brief', 4, false), 'mb-3',
                lang('What the client asked for, in a few sentences.')),
            $field('ai-project-credits', lang('Credits'), $textarea('ai-project-credits', 'credits', 4, false), 'mb-3',
                sprintf(lang('One collaborator on each line, as role and name, for example %s.'), $projects['courtyard_house']['credits'][0][0] . ': ' . $projects['courtyard_house']['credits'][0][1])),
            $field('ai-project-words', lang('The Client\'s Words'), $textarea('ai-project-words', 'client_words', 3, false), 'mb-3',
                lang('What the client said about the work, with their consent.')),
            $form_step(3, lang('Photos')),
            $field('ai-project-photo-1', lang('First Photo'), pg_cf_with_role($file('ai-project-photo-1', 'photo_1', 'public', 'image/*'), 'media'), 'mb-3',
                lang('The wide photo of the project page, the photo of the cards and the share image.')),
            $row(array(
                $col(array($field('ai-project-photo-2', lang('Second Photo'), $file('ai-project-photo-2', 'photo_2', 'public', 'image/*'))), '4'),
                $col(array($field('ai-project-photo-3', lang('Third Photo'), $file('ai-project-photo-3', 'photo_3', 'public', 'image/*'))), '4'),
                $col(array($field('ai-project-photo-4', lang('Fourth Photo'), $file('ai-project-photo-4', 'photo_4', 'public', 'image/*'))), '4'),
            ), '3'),
            $field('ai-project-featured', lang('Featured'), $select('ai-project-featured', 'featured', '', $ai_featured_choices, false, $ai_featured_choices[1], array('office_use_only' => true)),
                'mb-3', lang('A featured project is shown on the home page.')),
            $submit(lang('Publish')),
            $loop(array()),
        )),
    ),

    // Every project, filtered by the address (type, location), A to Z,
    // with a search over the words of the projects.
    'projects_list' => array(
        'page'   => 'projects',
        'slug'   => lang('form-list'),
        'config' => $ai_project_list(lang('No project matches these choices yet. Try another type or place, or tell us about yours.'), array(
            'items_per_page' => 12,
            'filters'        => array(
                $ai_filter('type', 'is equal to', '', 'type'),
                $ai_filter('location', 'is equal to', '', 'location'),
            ),
            'search_enabled' => true,
            'search_fields'  => array('name', 'type', 'location', 'place', 'service', 'summary', 'brief', 'credits'),
            'search_width'   => 'full',
            'search_label'   => lang('Search the projects: a house, a library, a courtyard...'),
        )),
        'tree'   => $project_grid(),
    ),

    // The home page's featured projects, three at most.
    'home_projects' => array(
        'page'   => 'home',
        'slug'   => lang('form-list'),
        'config' => $ai_project_list(lang('No featured projects yet.'), array(
            'items_per_page' => 3,
            'max_results'    => 3,
            'filters'        => array($ai_filter('featured', 'is equal to', $ai_featured_choices[0])),
        )),
        'tree'   => $project_grid(),
    ),
);

// One project as the studio publishes a building: the project sheet (the
// first photo beside the type and location, the name, the summary, the
// facts and the credits), the other photos, then the brief and the
// client's words beside the ways to start a similar project or see more
// of the same type.
$project_sample_key = 'courtyard_house';
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
                $icon('bi-building', 'display-5 text-body-secondary'),
                $heading('h2', lang('Project not found'), 'h3 fw-semibold mt-3'),
                $para($project_not_found, 'text-body-secondary mb-4', $bind('not_found')),
                $link(lang('See Our Projects'), '{{page:projects}}', 'btn btn-dark rounded-0 px-4'),
            ), $show_when('record_not_found')),
            $el('article', '', lang('Project'), array(
                $el('div', 'd-flex flex-wrap align-items-center justify-content-between gap-2 mb-4', lang('Toolbar'), array(
                    $el('a', 'd-inline-flex align-items-center gap-1 small fw-semibold link-dark text-decoration-none', lang('Link'), array(
                        $icon('bi-arrow-left'),
                        $span(lang('All Projects'), '', lang('Link Text')),
                    ), array('href' => '{{page:projects}}')),
                    $edit_button(),
                )),
                // The project sheet (Architecture: Project Sheet): the first
                // photo beside the type, the name, the summary, the facts
                // and the credits; the photo fades and the facts rise.
                $row(array(
                    $fx($col(array(
                        $el('figure', 'mb-0', lang('Photograph'), array(
                            $el('span', 'pg-field-value d-block ratio ratio-4x3 overflow-hidden bg-body-tertiary', lang('Picture Frame'), array(
                                $image($photo('project-' . $project_sample_key, 1600, 1200), $project_sample['name'], 'w-100 h-100 object-fit-cover',
                                    array('_bindings' => array('src' => 'photo_1', 'alt' => 'name'))),
                            )),
                            $el('figcaption', 'small text-body-secondary mt-2', lang('Photo Caption'), array(), array_merge(array('text' => $project_sample['place']), $bind('place'))),
                        )),
                    ), '', '7'), array('effect' => 'fade', 'trigger' => 'view', 'duration' => 900)),
                    $col(array(
                        $el('p', 'pai-eyebrow d-flex flex-wrap gap-2 small text-uppercase fw-semibold text-body-secondary mb-2', lang('Type and Location'), array(
                            $span($project_types[$project_sample['type']]['title'], '', lang('Project Type'), $bind('type')),
                            $span('·', '', lang('Separator'), array('_attrs' => array($attr('aria-hidden', 'true')))),
                            $span($locations[$project_sample['location']], '', lang('Location'), $bind('location')),
                        )),
                        $heading('h1', $project_sample['name'], 'pai-display display-6 mb-3', $bind('name')),
                        $para($project_sample['summary'], 'text-body-secondary mb-4', $bind('summary')),
                        $rise($project_facts($project_sample_key)),
                        $el('div', 'mt-4', lang('Credits'), array($credits_block($project_sample_key))),
                    ), '', '5'),
                ), '4', '', 'g-lg-5 mb-5'),
                $el('div', 'mb-5', lang('Gallery'), array($gallery($project_sample_key, true))),
                $row(array(
                    $col(array(
                        $el('div', 'pg-hide-if-empty', lang('The Brief'), array(
                            $heading('h2', lang('The Brief'), 'pai-eyebrow h6 text-uppercase fw-bold mb-3'),
                            $el('p', 'pg-field-value fs-5 mb-0', lang('Value'), array(), array_merge(array('text' => $project_sample['brief']), $bind('brief'))),
                        )),
                        $el('figure', 'pg-hide-if-empty border-start border-2 border-primary ps-4 mt-5 mb-0', lang('The Client\'s Words'), array(
                            $el('blockquote', 'mb-2', lang('Blockquote'), array(
                                $el('p', 'pai-quote pg-field-value fs-4 mb-0', lang('Value'), array(), array_merge(array('text' => $project_sample['quote']), $bind('client_words'))),
                            )),
                            $el('figcaption', 'small fw-semibold text-body-secondary mb-0', lang('Client'), array(), array_merge(array('text' => $project_sample['client']), $bind('client'))),
                        )),
                    ), '', '8'),
                    $col(array(
                        $el('aside', 'border-top border-dark-subtle pt-4', lang('Start a Project'), array(
                            $heading('h2', lang('Planning something similar?'), 'pai-display h5 mb-2'),
                            $para(lang('Tell us about your site and your plans. The first consultation is free.'), 'small text-body-secondary mb-3'),
                            // Opens the enquiry with this project's
                            // service chosen (?service=).
                            $link(lang('Start a Project'), '#', 'btn btn-dark rounded-0 w-100 mb-2', array(),
                                $ai_page_link('enquiry', array('service' => 'service'))),
                            // Opens the list on this project's type
                            // (?type=).
                            $link(lang('More projects of this type'), '#', 'btn btn-outline-dark rounded-0 w-100', array(),
                                $ai_page_link('projects', array('type' => 'type'))),
                        )),
                    ), '', '4'),
                ), '4', '', 'g-lg-5 mb-4'),
            ), $show_when('record_found')),
        ), 'py-5'),
    )),
);

return array('pages' => $project_pages, 'widgets' => $project_widgets);
