<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Web Designer Portfolio", its work: the list of case
 * studies with its category filters, the case study's own page, the staff
 * page that adds a case study (the Projects form, a collection, with the
 * sample case studies written in when it is made) and the list the home
 * places. Included after parts.php in the same scope; returns
 * array('pages' => ..., 'widgets' => ...).
 *
 * A case study is a record of the Projects form. Its category is a pick
 * list whose answers are the words parts.php gives, so a link with
 * ?category= filters the list by the words a visitor sees. The year is a
 * number field, so the lists sort on it; the pictures are image fields
 * kept in the public folder. Featured is set in the panel (hidden from the
 * form on the site) and picks the case studies the home shows.
 *
 * The helpers of this file start with $work_: the other page files run in
 * the same scope.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

$work_pages = array();

// The answers of the Featured list: the first marks a case study the home
// shows.
$work_featured = array(lang('Yes'), lang('No'));

// ── Pieces ──────────────────────────────────────────────────────────────

// A filter row of a list's settings: the field, how it compares and the
// value, or where the value comes from (a URL parameter, the shown record).
$work_filter = function ($field, $operator, $value, $dynamic = '', $attribute = null) {
    $row = array('field' => $field, 'operator' => $operator, 'value' => $value, 'dynamic_value' => $dynamic);
    if ($attribute !== null) $row['dynamic_value_attribute'] = $attribute;
    return $row;
};

// A list's settings: the Projects form, the case study page, newest year
// first (a number field, so 2026 comes before 2025).
$work_list_config = function ($more) {
    return array_merge(array(
        'regionType'          => 'form_list_view',
        'custom_form_page_id' => '{{tab:project_new}}',
        'detail_page_id'      => '{{tab:project}}',
        'order_by_field'      => 'year',
        'order_by_direction'  => 'DESC',
        'empty_message'       => lang('No case studies of this kind yet. Ask me: there is often one I can show you.'),
    ), $more);
};

// The cards of a list, in rows of three.
$work_card_grid = function () use ($widget_root, $row, $loop, $project_card) {
    return $widget_root(array($row(array($loop(array($project_card()))), '4')));
};

// The links above the list that narrow it: All, then one per kind of
// project, each opening the work page with ?category= set to the kind's
// words (the Category list's answers).
$work_filter_bar = function () use ($el, $link, $attr, $query, $categories) {
    $children = array($link(lang('All'), '{{page:work}}', 'btn btn-sm btn-outline-secondary rounded-pill'));
    foreach ($categories as $c) {
        $children[] = $link($c['title'], '{{page:work}}?' . $query(array('category' => $c['title'])), 'btn btn-sm btn-outline-secondary rounded-pill');
    }
    return $el('nav', 'd-flex flex-wrap align-items-center gap-2 mb-4', lang('Filters'), $children,
        array('_attrs' => array($attr('aria-label', lang('Kinds of project')))));
};

// ── Pages ───────────────────────────────────────────────────────────────

// The work: every case study, newest first, narrowed by a kind of project.
$work_pages[] = array(
    'key'              => 'work',
    'name'             => lang('work'),
    'folder'           => 'public',
    'title'            => lang('Selected Work'),
    'meta_description' => lang('Case studies of websites, online shops, brands and app designs: the challenge, the approach and the results.'),
    'tree'             => $page(array(
        $page_header(lang('Selected Work'),
            lang('Websites, shops, brands and apps I designed, each with the problem it solved and what changed after the launch.')),
        $el('section', 'py-5', lang('Case Studies'), array(
            $container(array($work_filter_bar(), $widget('work_list'))),
        )),
        $shared('brief_band'),
    )),
);

// One case study: the pictures, the facts, the story and the way to start
// a project of the same kind; then more work.
$work_pages[] = array(
    'key'              => 'project',
    'name'             => lang('project'),
    'folder'           => 'public',
    'title'            => lang('Case Study'),
    'meta_description' => lang('A case study: the client, the challenge, the approach and the results, with the pictures of the work.'),
    'sitemap'          => false,
    'tree'             => $page(array(
        $widget('project_detail'),
        $el('section', 'pb-5', lang('More Work'), array(
            $container(array(
                $section_header(lang('More work'), lang('Other projects'), lang('All Work'), '{{page:work}}'),
                $widget('project_more'),
            )),
        )),
        $shared('brief_band'),
    ), false),
);

// The staff page that adds a case study. The form is the Projects
// collection; the sample case studies are written in when the form is
// made, so the lists do not open empty (pg_cf_seed_sample_records()).
$work_samples = array();
foreach ($projects as $key => $p) {
    $work_samples[] = array(
        'title'     => $p['title'],
        'category'  => $categories[$p['category']]['title'],
        'client'    => $p['client'],
        'industry'  => $p['industry'],
        'year'      => (string)$p['year'],
        'role'      => $p['role'],
        'summary'   => $p['summary'],
        'challenge' => $p['challenge'],
        'approach'  => $p['approach'],
        'results'   => $p['results'],
        'cover'     => $photo($key, 1200, 900),
        'image_2'   => $photo($key . '-2', 1200, 900),
        'image_3'   => $photo($key . '-3', 1200, 900),
        'tools'     => $p['tools'],
        'url'       => $p['url'],
        'featured'  => $work_featured[$p['featured'] ? 0 : 1],
    );
}
$work_pages[] = array(
    'key'     => 'project_new',
    'name'    => lang('new-project'),
    'folder'  => 'private',
    'title'   => lang('New Case Study'),
    'search'  => false,
    'sitemap' => false,
    'noindex' => true,
    'form'    => array(
        'form_name'            => lang('Projects'),
        'collection'           => 1,
        'confirmation_message' => lang('The case study is published.'),
        'confirmation_page_id' => '{{tab:work}}',
        'sample_records'       => $work_samples,
        // The cover is the case study's share image (pg_cf_share_image_field_id()).
        'share_image_field'    => 'cover',
    ),
    'tree'    => $staff_page(array(
        $page_header(lang('New Case Study'), lang('Add a project with its pictures; it appears in the work list as soon as you send it. Mark it Featured from its Edit button to show it on the home page.')),
        $el('section', 'py-5', lang('Content'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $el('div', 'p-4 rounded-4 border', lang('Form Card'), array($widget('project_form'))),
                    ), '', '8'),
                ), '', 'center'),
            )),
        )),
    )),
);

// ── Widget pieces ───────────────────────────────────────────────────────

// A picture of the case study page, in a frame that leaves when the record
// has none.
$work_picture = function ($field, $seed, $ratio) use ($optional_image, $photo) {
    return $optional_image($field, 'title', 'rounded-4 object-fit-cover', 'ratio ' . $ratio . ' rounded-4 overflow-hidden bg-body-tertiary',
        $photo($seed, 1200, 900), lang('Picture'));
};

// A line of the facts beside the story: the name and the record's value;
// the line leaves when the record has no value.
$work_fact = function ($label, $token, $sample) use ($el, $span, $bind) {
    return $el('div', 'pg-hide-if-empty py-2 border-bottom', $label, array(
        $el('dt', 'small text-uppercase fw-semibold text-body-secondary', lang('Label'), array(), array('text' => $label)),
        $el('dd', 'pg-field-value mb-0', lang('Value'), array(), array_merge(array('text' => $sample), $bind($token))),
    ));
};

// A part of the story: its heading and the record's words.
$work_story = function ($title, $token, $sample) use ($el, $heading, $bind) {
    return $el('section', 'pg-hide-if-empty mb-5', $title, array(
        $heading('h2', $title, 'h4 fw-bold mb-3'),
        $el('div', 'pg-field-value lh-lg', lang('Value'), array(), array_merge(array('text' => $sample), $bind($token))),
    ));
};

// A link to a template page that sends values of the record it is drawn
// in: $params maps the address name to the field it takes (the renderer's
// __page_link).
$work_page_link = function ($page_key, $params) {
    $list = array();
    foreach ($params as $name => $token) $list[] = array('name' => $name, 'token' => $token);
    return array(
        '_bindings'   => array('href' => '__page_link'),
        '_bindParams' => array('href' => array('page_id' => '{{tab:' . $page_key . '}}', 'params' => $list)),
    );
};

$work_widgets = array(

    // The staff's Projects form: one case study. Featured starts No and is
    // set in the panel.
    'project_form' => array(
        'page'   => 'project_new',
        'slug'   => 'form',
        'config' => array('regionType' => 'custom_form', 'form_source' => 'page'),
        'tree'   => $widget_root(array(
            $form_step(1, lang('The project')),
            $field('pd-prj-title', lang('Title'), pg_cf_with_role($input('pd-prj-title', 'text', 'title', 'off'), 'title'), 'mb-3',
                lang('What you made and for whom, in one line.')),
            $row(array(
                $col(array($field('pd-prj-category', lang('Category'), pg_cf_with_role($select('pd-prj-category', 'category', lang('Choose a kind of project…'), $category_titles), 'category'))), '6'),
                $col(array($field('pd-prj-year', lang('Year'), $input('pd-prj-year', 'number', 'year', 'off', true, array('kind' => 'number'),
                    array($attr('min', '2000'), $attr('max', '2100'), $attr('step', '1'))))), '6'),
                $col(array($field('pd-prj-client', lang('Client'), $input('pd-prj-client', 'text', 'client', 'off'))), '6'),
                $col(array($field('pd-prj-industry', lang('Industry'), $input('pd-prj-industry', 'text', 'industry', 'off', false))), '6'),
                $col(array($field('pd-prj-role', lang('My role'), $input('pd-prj-role', 'text', 'role', 'off', false))), '6'),
                $col(array($field('pd-prj-tools', lang('Tools'), $input('pd-prj-tools', 'text', 'tools', 'off', false))), '6'),
            ), '3'),
            $field('pd-prj-url', lang('Live address'), $input('pd-prj-url', 'url', 'url', 'off', false), 'mb-3',
                lang('The address of the finished site, if it is online.')),
            $form_step(2, lang('The story')),
            $field('pd-prj-summary', lang('Summary'), pg_cf_with_role($textarea('pd-prj-summary', 'summary', 2), 'description'), 'mb-3',
                lang('One or two sentences for the cards of the lists.')),
            $field('pd-prj-challenge', lang('The challenge'), $textarea('pd-prj-challenge', 'challenge', 4, false)),
            $field('pd-prj-approach', lang('The approach'), $textarea('pd-prj-approach', 'approach', 4, false)),
            $field('pd-prj-results', lang('The results'), $textarea('pd-prj-results', 'results', 3, false)),
            $form_step(3, lang('Pictures')),
            $field('pd-prj-cover', lang('Cover Picture'), pg_cf_with_role($file('pd-prj-cover', 'cover', 'public', 'image/*'), 'media')),
            $row(array(
                $col(array($field('pd-prj-image-2', lang('Second Picture'), $file('pd-prj-image-2', 'image_2', 'public', 'image/*'))), '6'),
                $col(array($field('pd-prj-image-3', lang('Third Picture'), $file('pd-prj-image-3', 'image_3', 'public', 'image/*'))), '6'),
            ), '3'),
            $field('pd-prj-featured', lang('Featured'), $select('pd-prj-featured', 'featured', '', $work_featured, false, $work_featured[1],
                array('office_use_only' => true)), 'mb-3', lang('Featured case studies are the ones the home page shows.')),
            $submit(lang('Publish')),
            $loop(array()),
        )),
    ),

    // Every case study, filtered by the address (?category=), with a
    // search over the words of the case studies.
    'work_list' => array(
        'page'   => 'work',
        'slug'   => lang('form-list'),
        'config' => $work_list_config(array(
            'items_per_page' => 12,
            'filters'        => array($work_filter('category', 'is equal to', '', 'url parameter', 'category')),
            'search_enabled' => true,
            'search_fields'  => array('title', 'client', 'industry', 'summary', 'tools'),
            'search_width'   => 'full',
            'search_label'   => lang('Search the work: a client, an industry, a tool...'),
        )),
        'tree'   => $work_card_grid(),
    ),

    // The case study page's other projects: the three newest but the one
    // shown.
    'project_more' => array(
        'page'   => 'project',
        'slug'   => lang('form-list'),
        'config' => $work_list_config(array(
            'items_per_page' => 3,
            'max_results'    => 3,
            'filters'        => array($work_filter('reference_code', 'is not equal to', '', 'shown record', '')),
            'empty_message'  => lang('More case studies are coming soon.'),
        )),
        'tree'   => $work_card_grid(),
    ),

    // The home page's three newest featured case studies.
    'home_projects' => array(
        'page'   => 'home',
        'slug'   => lang('form-list'),
        'config' => $work_list_config(array(
            'items_per_page' => 3,
            'max_results'    => 3,
            'filters'        => array($work_filter('featured', 'is equal to', $work_featured[0])),
            'empty_message'  => lang('No case studies yet.'),
        )),
        'tree'   => $work_card_grid(),
    ),
);

// One case study: the kind and the year, the title and the summary, the
// pictures, the facts beside the story, the live site and the brief
// button that opens the brief with this kind of project chosen.
$work_sample = $projects['harbour'];
$work_widgets['project_detail'] = array(
    'page'   => 'project',
    'slug'   => lang('form-detail'),
    'config' => array(
        'regionType'          => 'form_item_view',
        'custom_form_page_id' => '{{tab:project_new}}',
        'access_control'      => 'public',
        'not_found_message'   => lang('This case study could not be found. It may have been taken off the site.'),
    ),
    'tree'   => $widget_root(array(
        $container(array(
            $el('div', 'text-center py-5', lang('Not Found'), array(
                $icon('bi-folder-x', 'display-5 text-body-tertiary'),
                $heading('h2', lang('Case study not found'), 'h3 fw-semibold mt-3'),
                $para(lang('This case study could not be found. It may have been taken off the site.'), 'text-body-secondary mb-4', $bind('not_found')),
                $link(lang('See the work'), '{{page:work}}', 'btn btn-primary rounded-pill px-4'),
            ), $show_when('record_not_found')),
            $el('article', '', lang('Case Study'), array(
                $el('div', 'd-flex flex-wrap align-items-center justify-content-between gap-2 mb-4', lang('Toolbar'), array(
                    $el('a', 'd-inline-flex align-items-center gap-1 small fw-semibold link-primary text-decoration-none', lang('Link'), array(
                        $icon('bi-arrow-left'),
                        $span(lang('All Work'), '', lang('Link Text')),
                    ), array('href' => '{{page:work}}')),
                    $edit_button(),
                )),
                $el('div', 'd-flex flex-wrap align-items-center gap-2 small mb-3', lang('Meta'), array(
                    $span($categories[$work_sample['category']]['title'], 'badge rounded-pill text-bg-primary', lang('Category'), $bind('category')),
                    $span((string)$work_sample['year'], 'text-body-secondary', lang('Year'), $bind('year')),
                )),
                $heading('h1', $work_sample['title'], 'pdp-display display-5 fw-bold mb-3', $bind('title')),
                $para($work_sample['summary'], 'lead text-body-secondary mb-4', $bind('summary')),
                $el('div', 'mb-4', lang('Cover'), array($work_picture('cover', 'harbour', 'ratio-16x9'))),
                $row(array(
                    $col(array(
                        $work_story(lang('The challenge'), 'challenge', $work_sample['challenge']),
                        $work_story(lang('The approach'), 'approach', $work_sample['approach']),
                        $row(array(
                            $col(array($work_picture('image_2', 'harbour-2', 'ratio-4x3')), '6'),
                            $col(array($work_picture('image_3', 'harbour-3', 'ratio-4x3')), '6'),
                        ), '3', '', 'mb-5'),
                        $work_story(lang('The results'), 'results', $work_sample['results']),
                    ), '', '8'),
                    $col(array(
                        $el('aside', 'card border-0 shadow-sm rounded-4', lang('Facts'), array(
                            $el('div', 'card-body p-4', lang('Card Body'), array(
                                $el('dl', 'mb-4', lang('Facts List'), array(
                                    $work_fact(lang('Client'), 'client', $work_sample['client']),
                                    $work_fact(lang('Industry'), 'industry', $work_sample['industry']),
                                    $work_fact(lang('Year'), 'year', (string)$work_sample['year']),
                                    $work_fact(lang('My role'), 'role', $work_sample['role']),
                                    $work_fact(lang('Tools'), 'tools', $work_sample['tools']),
                                )),
                                // The live site, shown only when the record has its address.
                                $link(lang('Visit the live site'), '#', 'btn btn-outline-primary rounded-pill w-100 mb-2',
                                    array($attr('rel', 'noopener')),
                                    array('target' => '_blank', '_bindings' => array('href' => 'url', 'show_if' => 'url'))),
                                // Opens the brief with this kind of project chosen (?type=).
                                $link(lang('Start a project like this'), '#', 'btn btn-primary rounded-pill w-100', array(),
                                    $work_page_link('brief', array('type' => 'category'))),
                            )),
                        )),
                    ), '', '4'),
                ), '4', '', 'g-lg-5'),
            ), $show_when('record_found')),
        ), 'py-5'),
    )),
);

return array('pages' => $work_pages, 'widgets' => $work_widgets);
