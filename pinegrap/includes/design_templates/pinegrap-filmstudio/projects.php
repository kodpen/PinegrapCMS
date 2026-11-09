<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Film Studio", its work: the staff page that adds a
 * project (the Projects form, a collection, with the sample projects
 * written in when it is made), the list of every project with its industry
 * and service filters, the project's own page and the list the home page
 * places. Included after parts.php in the same scope; returns
 * array('pages' => ..., 'widgets' => ...).
 *
 * A project is a record of the Projects form. Industry, service and status
 * are pick lists whose answers are the words parts.php gives, so a link
 * with ?industry= or ?service= (the industry tiles, the service pages, the
 * footer) filters the list by the words a visitor sees. Where it was shot
 * and the running time are words, never a date. The credits are one
 * "Role: Name" line each. The film's address is drawn as a link opening a
 * new window, never as an embed: the studio's video host plays it.
 * Featured is the studio's choice (office use only): the home page shows
 * the featured projects.
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
$fm_filter = function ($field, $operator, $value, $param = '') {
    $row = array('field' => $field, 'operator' => $operator, 'value' => $value, 'dynamic_value' => $param !== '' ? 'url parameter' : '');
    if ($param !== '') $row['dynamic_value_attribute'] = $param;
    return $row;
};
// The answers of the Featured list: the first marks a project for the home.
$fm_featured_choices = array(lang('Yes'), lang('No'));

// A link to a template page that sends values of the record it is drawn
// in: $params maps the address name to the field it takes (the renderer's
// __page_link).
$fm_page_link = function ($page_key, $params) {
    $list = array();
    foreach ($params as $name => $token) $list[] = array('name' => $name, 'token' => $token);
    return array(
        '_bindings'   => array('href' => '__page_link'),
        '_bindParams' => array('href' => array('page_id' => '{{tab:' . $page_key . '}}', 'params' => $list)),
    );
};

// A list of projects: the Projects form, the project page, A to Z, and
// the words shown when nothing matches.
$fm_project_list = function ($empty, $more) {
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
$fm_filter_bar = function ($groups) use ($el, $link, $span, $query) {
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

// A still of the project page, in a 16:9 frame that leaves when the record
// has none.
$fm_still = function ($field, $seed) use ($optional_image, $photo) {
    return $optional_image($field, 'name', 'rounded-3 object-fit-cover', 'ratio ratio-16x9', $photo($seed, 1280, 720), lang('Still'));
};

// ── Pages ───────────────────────────────────────────────────────────────

// Every project, narrowed by industry and service from the address.
$project_pages[] = array(
    'key'              => 'projects',
    'name'             => lang('work'),
    'folder'           => 'public',
    'title'            => lang('Our Work'),
    'meta_description' => lang('Commercials, brand films, music videos and documentaries we have made, with the credits of each film.'),
    'tree'             => $page(array(
        $page_header(lang('Our Work'), lang('Films we have released or are finishing now. Choose an industry or a service to narrow the list.')),
        $el('section', 'py-5', lang('Projects'), array(
            $container(array(
                $fm_filter_bar(array(
                    array('label' => lang('Industry'), 'param' => 'industry', 'values' => $industry_titles),
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
    'name'             => lang('film'),
    'folder'           => 'public',
    'title'            => lang('Project'),
    'meta_description' => lang('A film of the studio: the stills, the facts, the credits and what the client said.'),
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
        'industry'     => $industries[$p['industry']]['title'],
        'service'      => $services[$p['service']]['title'],
        'client'       => $p['client'],
        'location'     => $p['location'],
        'runtime'      => $p['runtime'],
        'status'       => $project_statuses[$p['status']],
        'summary'      => $p['summary'],
        'credits'      => $credits_text($key),
        'client_words' => $p['quote'],
        'film_url'     => 'https://www.example.com/films/' . str_replace('_', '-', $key),
        'still_1'      => $photo('project-' . $key, 1280, 720),
        'still_2'      => $photo('project-' . $key . '-2', 1280, 720),
        'still_3'      => $photo('project-' . $key . '-3', 1280, 720),
        'still_4'      => $photo('project-' . $key . '-4', 1280, 720),
        'featured'     => $p['featured'] ? $fm_featured_choices[0] : $fm_featured_choices[1],
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
        // The first still is the project's share image (pg_cf_share_image_field_id()).
        'share_image_field'    => 'still_1',
    ),
    'tree'    => $staff_page(array(
        $page_header(lang('New Project'), lang('Add the film with its facts, stills and credits; it appears in the list as soon as you send it. Mark it featured from its Edit button.')),
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

    // The staff's Projects form: one film. Featured starts as No.
    'project_form' => array(
        'page'   => 'project_new',
        'slug'   => 'form',
        'config' => array('regionType' => 'custom_form', 'form_source' => 'page'),
        'tree'   => $widget_root(array(
            $form_step(1, lang('The film')),
            $field('fm-project-name', lang('Project Name'), pg_cf_with_role($input('fm-project-name', 'text', 'name', 'off'), 'title')),
            $row(array(
                $col(array($field('fm-project-industry', lang('Industry'), pg_cf_with_role($select('fm-project-industry', 'industry', lang('Choose an industry…'), $industry_titles), 'category'))), '6'),
                $col(array($field('fm-project-service', lang('Service Type'), $select('fm-project-service', 'service', lang('Choose a service…'), $service_titles))), '6'),
                $col(array($field('fm-project-client', lang('Client'), $input('fm-project-client', 'text', 'client', 'off', false))), '6'),
                $col(array($field('fm-project-location', lang('Shot In'), $input('fm-project-location', 'text', 'location', 'off', false))), '6'),
                $col(array($field('fm-project-runtime', lang('Running Time'), $input('fm-project-runtime', 'text', 'runtime', 'off'), 'mb-3',
                    lang('In words, for example 60 seconds.'))), '6'),
                $col(array($field('fm-project-status', lang('Status'), $select('fm-project-status', 'status', '', $status_titles, true, $status_titles[0]))), '6'),
            ), '3'),
            $form_step(2, lang('Words')),
            $field('fm-project-summary', lang('Summary'), pg_cf_with_role($textarea('fm-project-summary', 'summary', 2), 'description'), 'mb-3',
                lang('One or two lines for the cards of the lists.')),
            $field('fm-project-credits', lang('Credits'), $textarea('fm-project-credits', 'credits', 5, false), 'mb-3',
                lang('One credit on each line, as Role: Name.')),
            $field('fm-project-words', lang('The Client\'s Words'), $textarea('fm-project-words', 'client_words', 3, false), 'mb-3',
                lang('What the client said about the film, with their consent.')),
            $field('fm-project-film', lang('Film Address'), $input('fm-project-film', 'url', 'film_url', 'off', false), 'mb-3',
                lang('The film\'s page on your video host; the project page links it.')),
            $form_step(3, lang('Stills')),
            $field('fm-project-still-1', lang('First Still'), pg_cf_with_role($file('fm-project-still-1', 'still_1', 'public', 'image/*'), 'media'), 'mb-3',
                lang('The still of the cards and the share image.')),
            $row(array(
                $col(array($field('fm-project-still-2', lang('Second Still'), $file('fm-project-still-2', 'still_2', 'public', 'image/*'))), '4'),
                $col(array($field('fm-project-still-3', lang('Third Still'), $file('fm-project-still-3', 'still_3', 'public', 'image/*'))), '4'),
                $col(array($field('fm-project-still-4', lang('Fourth Still'), $file('fm-project-still-4', 'still_4', 'public', 'image/*'))), '4'),
            ), '3'),
            $field('fm-project-featured', lang('Featured'), $select('fm-project-featured', 'featured', '', $fm_featured_choices, false, $fm_featured_choices[1], array('office_use_only' => true)),
                'mb-3', lang('A featured project is shown on the home page.')),
            $submit(lang('Publish')),
            $loop(array()),
        )),
    ),

    // Every project, filtered by the address (industry, service), A to Z,
    // with a search over the words of the projects.
    'projects_list' => array(
        'page'   => 'projects',
        'slug'   => lang('form-list'),
        'config' => $fm_project_list(lang('No film matches these choices yet. Try another industry or service, or tell us about yours.'), array(
            'items_per_page' => 12,
            'filters'        => array(
                $fm_filter('industry', 'is equal to', '', 'industry'),
                $fm_filter('service', 'is equal to', '', 'service'),
            ),
            'search_enabled' => true,
            'search_fields'  => array('name', 'industry', 'service', 'client', 'summary', 'credits'),
            'search_width'   => 'full',
            'search_label'   => lang('Search the work: a client, a director, a music video...'),
        )),
        'tree'   => $project_grid(),
    ),

    // The home page's featured projects, three at most.
    'home_projects' => array(
        'page'   => 'home',
        'slug'   => lang('form-list'),
        'config' => $fm_project_list(lang('No featured projects yet.'), array(
            'items_per_page' => 3,
            'max_results'    => 3,
            'filters'        => array($fm_filter('featured', 'is equal to', $fm_featured_choices[0])),
        )),
        'tree'   => $project_grid(),
    ),
);

// One project: the industry and status, the name, the summary, the facts,
// the first still with the way to watch the film, the other stills, the
// credits, the client's words and beside them the way to start a project
// with the same service or see more of the industry.
$project_sample_key = 'night_drive';
$project_sample = $projects[$project_sample_key];
$project_not_found = lang('This film could not be found. It may have been renamed or taken off the list.');
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
                $icon('bi-film', 'display-5 text-body-tertiary'),
                $heading('h2', lang('Film not found'), 'h3 fw-semibold mt-3'),
                $para($project_not_found, 'text-body-secondary mb-4', $bind('not_found')),
                $link(lang('See Our Work'), '{{page:projects}}', 'btn btn-primary px-4'),
            ), $show_when('record_not_found')),
            $el('article', '', lang('Project'), array(
                $el('div', 'd-flex flex-wrap align-items-center justify-content-between gap-2 mb-3', lang('Toolbar'), array(
                    $el('a', 'd-inline-flex align-items-center gap-1 small fw-semibold link-primary text-decoration-none', lang('Link'), array(
                        $icon('bi-arrow-left'),
                        $span(lang('All Work'), '', lang('Link Text')),
                    ), array('href' => '{{page:projects}}')),
                    $edit_button(),
                )),
                $el('div', 'd-flex flex-wrap gap-2 mb-2', lang('Badges'), array(
                    $span($industries[$project_sample['industry']]['title'], 'badge rounded-pill bg-primary-subtle text-primary-emphasis', lang('Industry'), $bind('industry')),
                    $span($project_statuses[$project_sample['status']], 'badge rounded-pill text-bg-dark', lang('Status'), $bind('status')),
                )),
                $heading('h1', $project_sample['name'], 'pfm-display display-6 mb-2', $bind('name')),
                $para($project_sample['summary'], 'lead text-body-secondary mb-3', $bind('summary')),
                $project_facts($project_sample_key, 'd-flex flex-wrap gap-3 text-body-secondary mb-4'),
                $el('div', 'mb-3', lang('Stills'), array($fm_still('still_1', 'project-' . $project_sample_key))),
                // The way to watch the film: its address, opening the
                // video host in a new window. The row leaves when the
                // record has no address (its hidden value is the address).
                $el('p', 'pg-hide-if-empty mb-4', lang('Watch the Film'), array(
                    $el('span', 'pg-field-value d-none', lang('Value'), array(), array_merge(
                        array('text' => 'https://www.example.com/films/night-drive'), $bind('film_url'))),
                    $el('a', 'btn btn-primary d-inline-flex align-items-center gap-2 px-4', lang('Link'), array(
                        $icon('bi-play-circle'),
                        $span(lang('Watch the film'), '', lang('Link Text')),
                    ), array('href' => '#', 'target' => '_blank', 'rel' => 'noopener', '_bindings' => array('href' => 'film_url'))),
                )),
                $row(array(
                    $col(array($fm_still('still_2', 'project-' . $project_sample_key . '-2')), '4'),
                    $col(array($fm_still('still_3', 'project-' . $project_sample_key . '-3')), '4'),
                    $col(array($fm_still('still_4', 'project-' . $project_sample_key . '-4')), '4'),
                ), '3', '', 'mb-5'),
                $row(array(
                    $col(array(
                        $el('div', 'pg-hide-if-empty mb-4', lang('Credits'), array($credits_list($project_sample_key))),
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
                                $heading('h2', lang('Planning a film like this?'), 'pfm-display h5 mb-2'),
                                $para(lang('Tell us what the film must do: a producer calls you back within a day and sends a written treatment within a week.'), 'small text-body-secondary mb-3'),
                                // Opens the enquiry with this project's
                                // service chosen (?service=).
                                $link(lang('Start a Project'), '#', 'btn btn-primary w-100 mb-2', array(),
                                    $fm_page_link('enquiry', array('service' => 'service'))),
                                // Opens the list on this project's
                                // industry (?industry=).
                                $link(lang('More work in this industry'), '#', 'btn btn-outline-secondary w-100', array(),
                                    $fm_page_link('projects', array('industry' => 'industry'))),
                            )),
                        )),
                    ), '', '4'),
                ), '4', '', 'g-lg-5 mb-4'),
            ), $show_when('record_found')),
        ), 'py-5'),
    )),
);

return array('pages' => $project_pages, 'widgets' => $project_widgets);
