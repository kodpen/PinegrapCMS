<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Recruitment Agency", its job listings: the staff page
 * that adds a job (the Jobs form, a collection, with the sample jobs
 * written in when it is made), the list of every open job with its sector,
 * city and contract filters, the job's own page and the list the home page
 * places. Included after parts.php in the same scope; returns
 * array('pages' => ..., 'widgets' => ...).
 *
 * A job is a record of the Jobs form. Sector, city, contract type and work
 * mode are pick lists whose answers are the words parts.php gives, so a
 * link with ?sector=, ?city= or ?type= (the sector and city cards, the
 * sector pages, the footer) filters the list by the words a visitor sees.
 * The salary is a band in words, the employer a description; duties and
 * requirements are one item per line. Featured is the agency's choice
 * (office use only): the home page shows the featured jobs.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

$job_pages = array();

// ── Pieces ──────────────────────────────────────────────────────────────

// A filter row of a list's settings: the field, how it compares and the
// value, or where the value comes from (a URL parameter, left out while
// the parameter is not in the address).
$rc_filter = function ($field, $operator, $value, $param = '') {
    $row = array('field' => $field, 'operator' => $operator, 'value' => $value, 'dynamic_value' => $param !== '' ? 'url parameter' : '');
    if ($param !== '') $row['dynamic_value_attribute'] = $param;
    return $row;
};
// The answers of the Featured list: the first marks a job for the home.
$rc_featured_choices = array(lang('Yes'), lang('No'));

// A link to a template page that sends values of the record it is drawn
// in: $params maps the address name to the field it takes (the renderer's
// __page_link).
$rc_page_link = function ($page_key, $params) {
    $list = array();
    foreach ($params as $name => $token) $list[] = array('name' => $name, 'token' => $token);
    return array(
        '_bindings'   => array('href' => '__page_link'),
        '_bindParams' => array('href' => array('page_id' => '{{tab:' . $page_key . '}}', 'params' => $list)),
    );
};

// A list of jobs: the Jobs form, the job page, the newest first (a new
// opening is what a candidate looks for), and the words shown when nothing
// matches.
$rc_job_list = function ($empty, $more) {
    return array_merge(array(
        'regionType'          => 'form_list_view',
        'custom_form_page_id' => '{{tab:job_new}}',
        'detail_page_id'      => '{{tab:job}}',
        'order_by_field'      => 'submitted_date_and_time',
        'order_by_direction'  => 'DESC',
        'empty_message'       => $empty,
    ), $more);
};
// The job cards of a list, one under the other.
$job_rows = function () use ($widget_root, $loop, $job_card) {
    return $widget_root(array($loop(array($job_card()))));
};

// The links above the list that narrow it: one per answer of each pick
// list, each opening the list with the filter set.
$rc_filter_bar = function ($groups) use ($el, $link, $span, $query) {
    $navs = array();
    foreach ($groups as $g) {
        $children = array($span($g['label'], 'small fw-semibold text-body-secondary me-1', lang('Label')));
        $children[] = $link(lang('All'), '{{page:jobs}}', 'btn btn-sm btn-outline-secondary rounded-pill');
        foreach ($g['values'] as $v) {
            $children[] = $link($v, '{{page:jobs}}?' . $query(array($g['param'] => $v)), 'btn btn-sm btn-outline-secondary rounded-pill');
        }
        $navs[] = $el('div', 'd-flex flex-wrap align-items-center gap-2 mb-2', $g['label'], $children);
    }
    return $el('nav', 'mb-4', lang('Filters'), $navs);
};

// A long-text field of the job page (duties, requirements): its heading
// and one item per line, leaving with its heading when the job has none.
$rc_lines = function ($title, $token, $sample) use ($el, $heading, $bind) {
    return $el('div', 'pg-hide-if-empty mb-4', $title, array(
        $heading('h2', $title, 'prc-display h4 mb-2'),
        $el('div', 'pg-field-value lh-lg', lang('Value'), array(), array_merge(array('text' => $sample), $bind($token))),
    ));
};

// ── Pages ───────────────────────────────────────────────────────────────

// Every open job, narrowed by sector, city and contract type from the
// address.
$job_pages[] = array(
    'key'              => 'jobs',
    'name'             => lang('jobs'),
    'folder'           => 'public',
    'title'            => lang('Find a Job'),
    'meta_description' => lang('Open jobs in technology, finance, engineering, healthcare, sales and logistics, by city and contract type.'),
    'tree'             => $page(array(
        $page_header(lang('Find a Job'), lang('Every job open with us now. Choose a sector, a city or a contract type to narrow the list.')),
        $el('section', 'py-5', lang('Jobs'), array(
            $container(array(
                $rc_filter_bar(array(
                    array('label' => lang('Sector'), 'param' => 'sector', 'values' => $sector_titles),
                    array('label' => lang('City'), 'param' => 'city', 'values' => $city_titles),
                    array('label' => lang('Contract Type'), 'param' => 'type', 'values' => $contract_titles),
                )),
                $widget('jobs_list'),
                $el('div', 'd-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 p-4 mt-4 rounded-4 bg-body-tertiary', lang('Open Application'), array(
                    $el('div', '', lang('Text'), array(
                        $heading('h2', lang('Nothing for you today?'), 'prc-display h5 mb-1'),
                        $para(lang('Send an open application: your consultant calls you when a job fits, often before it is advertised.'), 'small text-body-secondary mb-0'),
                    )),
                    $link(lang('Send an Open Application'), '{{page:apply}}', 'btn btn-primary px-4 text-nowrap'),
                )),
            )),
        ), $searchable('rc-jobs', lang('vacancies, job search, open positions, work, careers'))),
    )),
);

// One job: its record.
$job_pages[] = array(
    'key'              => 'job',
    'name'             => lang('job'),
    'folder'           => 'public',
    'title'            => lang('Job'),
    'meta_description' => lang('A job open with the agency: the employer, the salary, the duties, what you need and how to apply.'),
    'sitemap'          => false,
    'tree'             => $page(array(
        $widget('job_detail'),
    )),
);

// The staff page that adds a job. The form is the Jobs collection; the
// sample jobs are written in when the form is made, so the lists do not
// open empty (pg_cf_seed_sample_records()).
$sample_jobs = array();
foreach ($jobs as $key => $j) {
    $c = $consultant_of($j['sector']);
    $sample_jobs[] = array(
        'title'         => $j['title'],
        'sector'        => $sectors[$j['sector']]['title'],
        'city'          => $cities[$j['city']]['title'],
        'contract_type' => $contract_types[$j['type']],
        'work_mode'     => $work_modes[$j['mode']],
        'salary'        => $j['salary'],
        'employer'      => $j['employer'],
        'summary'       => $j['summary'],
        'duties'        => $duties_text($key),
        'requirements'  => $requirements_text($key),
        'consultant'    => $c['name'],
        'featured'      => $j['featured'] ? $rc_featured_choices[0] : $rc_featured_choices[1],
    );
}
$job_pages[] = array(
    'key'     => 'job_new',
    'name'    => lang('new-job'),
    'folder'  => 'private',
    'title'   => lang('New Job Opening'),
    'search'  => false,
    'sitemap' => false,
    'noindex' => true,
    'form'    => array(
        'form_name'            => lang('Jobs'),
        'collection'           => 1,
        'confirmation_message' => lang('The job is published.'),
        'confirmation_page_id' => '{{tab:jobs}}',
        'sample_records'       => $sample_jobs,
    ),
    'tree'    => $staff_page(array(
        $page_header(lang('New Job Opening'), lang('Add the job with its facts; it appears in the list as soon as you send it. Mark it featured from its Edit button.')),
        $el('section', 'py-5', lang('Content'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $el('div', 'p-4 rounded-4 border', lang('Form Card'), array($widget('job_form'))),
                    ), '', '8'),
                ), '', 'center'),
            )),
        )),
    )),
);

// ── Widgets ─────────────────────────────────────────────────────────────

$sample_job = $jobs['software_developer'];
$job_widgets = array(

    // The staff's Jobs form: one job. Featured starts as No.
    'job_form' => array(
        'page'   => 'job_new',
        'slug'   => 'form',
        'config' => array('regionType' => 'custom_form', 'form_source' => 'page'),
        'tree'   => $widget_root(array(
            $form_step(1, lang('The job')),
            $field('rc-job-title', lang('Job Title'), pg_cf_with_role($input('rc-job-title', 'text', 'title', 'off'), 'title')),
            $row(array(
                $col(array($field('rc-job-sector', lang('Sector'), pg_cf_with_role($select('rc-job-sector', 'sector', lang('Choose a sector…'), $sector_titles), 'category'))), '6'),
                $col(array($field('rc-job-city', lang('City'), $select('rc-job-city', 'city', lang('Choose a city…'), $city_titles))), '6'),
                $col(array($field('rc-job-type', lang('Contract Type'), $select('rc-job-type', 'contract_type', '', $contract_titles, true, $contract_titles[0]))), '6'),
                $col(array($field('rc-job-mode', lang('Work Mode'), $select('rc-job-mode', 'work_mode', '', $mode_titles, true, $mode_titles[0]))), '6'),
            ), '3'),
            $field('rc-job-salary', lang('Salary'), $input('rc-job-salary', 'text', 'salary', 'off', false), 'mb-3',
                sprintf(lang('In words, for example %s.'), $sample_job['salary'])),
            $field('rc-job-employer', lang('Employer'), $input('rc-job-employer', 'text', 'employer', 'off', false), 'mb-3',
                lang('Describe the employer without naming them if they asked you not to, for example a national retail chain.')),
            $field('rc-job-consultant', lang('Consultant'), $select('rc-job-consultant', 'consultant', lang('Choose a consultant…'), $consultant_names, false)),
            $form_step(2, lang('Words')),
            $field('rc-job-summary', lang('Summary'), pg_cf_with_role($textarea('rc-job-summary', 'summary', 2), 'description'), 'mb-3',
                lang('One or two lines for the cards of the lists.')),
            $field('rc-job-duties', lang('Duties'), $textarea('rc-job-duties', 'duties', 5, false), 'mb-3', lang('One duty on each line.')),
            $field('rc-job-requirements', lang('Requirements'), $textarea('rc-job-requirements', 'requirements', 5, false), 'mb-3', lang('One requirement on each line.')),
            $field('rc-job-featured', lang('Featured'), $select('rc-job-featured', 'featured', '', $rc_featured_choices, false, $rc_featured_choices[1], array('office_use_only' => true)),
                'mb-3', lang('A featured job is shown on the home page.')),
            $submit(lang('Publish')),
            $loop(array()),
        )),
    ),

    // Every job, filtered by the address (sector, city, contract type),
    // the newest first, with a search over the words of the jobs.
    'jobs_list' => array(
        'page'   => 'jobs',
        'slug'   => lang('form-list'),
        'config' => $rc_job_list(lang('No job matches these choices right now. Try another sector or city, or send an open application.'), array(
            'items_per_page' => 12,
            'filters'        => array(
                $rc_filter('sector', 'is equal to', '', 'sector'),
                $rc_filter('city', 'is equal to', '', 'city'),
                $rc_filter('contract_type', 'is equal to', '', 'type'),
            ),
            'search_enabled' => true,
            'search_fields'  => array('title', 'sector', 'city', 'contract_type', 'work_mode', 'employer', 'summary', 'duties', 'requirements'),
            'search_width'   => 'full',
            'search_label'   => lang('Search the jobs: a role, a skill, a company type...'),
        )),
        'tree'   => $job_rows(),
    ),

    // The home page's featured jobs, three at most.
    'home_jobs' => array(
        'page'   => 'home',
        'slug'   => lang('form-list'),
        'config' => $rc_job_list(lang('No featured jobs yet.'), array(
            'items_per_page' => 3,
            'max_results'    => 3,
            'filters'        => array($rc_filter('featured', 'is equal to', $rc_featured_choices[0])),
        )),
        'tree'   => $job_rows(),
    ),
);

// One job: the sector and contract type, the title, the employer, the
// summary, the facts, the duties and the requirements, and beside them the
// way to apply with the job chosen, the consultant and more jobs in the
// sector.
$job_sample_key = 'software_developer';
$job_not_found = lang('This job could not be found. It may have been filled or taken off the list.');
$job_widgets['job_detail'] = array(
    'page'   => 'job',
    'slug'   => lang('form-detail'),
    'config' => array(
        'regionType'          => 'form_item_view',
        'custom_form_page_id' => '{{tab:job_new}}',
        'access_control'      => 'public',
        'not_found_message'   => $job_not_found,
    ),
    'tree'   => $widget_root(array(
        $container(array(
            $el('div', 'text-center py-5', lang('Not Found'), array(
                $icon('bi-briefcase', 'display-5 text-body-tertiary'),
                $heading('h2', lang('Job not found'), 'h3 fw-semibold mt-3'),
                $para($job_not_found, 'text-body-secondary mb-4', $bind('not_found')),
                $link(lang('See the Open Jobs'), '{{page:jobs}}', 'btn btn-primary px-4'),
            ), $show_when('record_not_found')),
            $el('article', '', lang('Job'), array(
                $el('div', 'd-flex flex-wrap align-items-center justify-content-between gap-2 mb-3', lang('Toolbar'), array(
                    $el('a', 'd-inline-flex align-items-center gap-1 small fw-semibold link-primary text-decoration-none', lang('Link'), array(
                        $icon('bi-arrow-left'),
                        $span(lang('All Jobs'), '', lang('Link Text')),
                    ), array('href' => '{{page:jobs}}')),
                    $edit_button(),
                )),
                $el('div', 'd-flex flex-wrap gap-2 mb-2', lang('Badges'), array(
                    $span($sectors[$sample_job['sector']]['title'], 'badge rounded-pill bg-primary-subtle text-primary-emphasis', lang('Sector'), $bind('sector')),
                    $span($contract_types[$sample_job['type']], 'badge rounded-pill text-bg-secondary', lang('Contract Type'), $bind('contract_type')),
                )),
                $heading('h1', $sample_job['title'], 'prc-display display-6 mb-1', $bind('title')),
                $para($sample_job['employer'], 'fw-semibold mb-2 pg-hide-if-empty', $bind('employer')),
                $para($sample_job['summary'], 'lead text-body-secondary mb-3', $bind('summary')),
                $job_facts($job_sample_key, 'd-flex flex-wrap gap-3 text-body-secondary mb-5'),
                $row(array(
                    $col(array(
                        $rc_lines(lang('What you will do'), 'duties', $duties_text($job_sample_key)),
                        $rc_lines(lang('What you need'), 'requirements', $requirements_text($job_sample_key)),
                    ), '', '8'),
                    $col(array(
                        $el('aside', 'card border-0 shadow-sm rounded-4', lang('Apply Card'), array(
                            $el('div', 'card-body p-4', lang('Card Body'), array(
                                $heading('h2', lang('Interested?'), 'prc-display h5 mb-2'),
                                $para(lang('Apply with your CV. A consultant reads every application and calls you back.'), 'small text-body-secondary mb-3'),
                                // Opens the application with this job
                                // chosen (?job=).
                                $link(lang('Apply for This Job'), '#', 'btn btn-primary w-100 mb-3', array(),
                                    $rc_page_link('apply', array('job' => 'title'))),
                                $el('p', 'd-flex align-items-center gap-2 small mb-3 pg-hide-if-empty', lang('Consultant'), array(
                                    $icon('bi-person-badge', 'text-primary'),
                                    $span(lang('Your consultant:'), 'text-body-secondary', lang('Label')),
                                    $span($consultant_of($sample_job['sector'])['name'], 'fw-semibold', lang('Consultant'), $bind('consultant')),
                                )),
                                // Opens the list on this job's sector
                                // (?sector=).
                                $link(lang('More jobs in this sector'), '#', 'btn btn-outline-secondary w-100', array(),
                                    $rc_page_link('jobs', array('sector' => 'sector'))),
                            )),
                        )),
                    ), '', '4'),
                ), '4', '', 'g-lg-5 mb-4'),
            ), $show_when('record_found')),
        ), 'py-5'),
    )),
);

return array('pages' => $job_pages, 'widgets' => $job_widgets);
