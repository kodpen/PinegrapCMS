<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Construction Firm", its careers: the staff page that
 * adds a job opening (the Jobs form, a collection, with the sample jobs
 * written in when it is made), the careers page listing every opening,
 * the job's own page with its apply link, the application, the
 * application's own page with its messages, My Applications and the
 * staff's list, their e-mails and the widgets that draw them. Included
 * after parts.php, mail.php, projects.php (its record page link) and
 * quotes.php (its record and e-mail pieces) in the same scope; returns
 * array('pages' => ..., 'widgets' => ...).
 *
 * A job opening is a record of the Jobs form; department and type are pick
 * lists whose answers are the words parts.php gives. An application is a
 * record of the Applications form. The job's page opens it with the job
 * chosen (?job=, the job's title); an applicant without an account gets
 * one (auto-registration), so the application's page and My Applications
 * show it at once. Its Status is an office-use-only field the firm changes
 * in the control panel; the interview day is written under it as a
 * message (the page's comments), mailed to the applicant and to the firm.
 * CVs are kept in the template's private folder.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

$career_pages = array();

// The sample application the canvas shows.
$job_sample_key = 'site_engineer';
$job_sample = $jobs[$job_sample_key];
$sample_letter = lang('I have three years on residential sites as an assistant site engineer and would like to run the setting out and quality checks on a larger project.');

// What every job at the firm offers, beside the openings.
$job_benefits = array(
    lang('A permanent contract and pay on time, every month'),
    lang('Safety training and protective equipment from the first day'),
    lang('Courses and certificates paid by the firm'),
    lang('A project manager who knows your name'),
);

// ── Pages ───────────────────────────────────────────────────────────────

// Careers: every opening, why work here and the open application.
$career_pages[] = array(
    'key'              => 'careers',
    'name'             => lang('careers'),
    'folder'           => 'public',
    'title'            => lang('Careers'),
    'meta_description' => lang('Jobs on our sites and in our office: engineers, project managers, craftspeople and apprentices.'),
    'tree'             => $page(array(
        $page_header(lang('Careers'), lang('Engineers, craftspeople and apprentices build every project we hand over. See the jobs open now, or send an open application.')),
        $el('section', 'py-5', lang('Open Jobs'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $widget('jobs_list'),
                        $el('div', 'd-flex flex-wrap align-items-center gap-3 mt-4', lang('Open Application'), array(
                            $para(lang('No job for you here? We read every open application.'), 'text-body-secondary mb-0'),
                            $link(lang('Send an open application'), '{{page:application}}?' . $query(array('job' => lang('Open application'))), 'btn btn-primary px-4'),
                        )),
                    ), '', '8'),
                    $col(array(
                        $el('div', 'p-4 rounded-3 bg-body-tertiary', lang('Why Work Here'), array(
                            $heading('h2', lang('Why work with us'), 'pcn-display h5 mb-3'),
                            $check_list($job_benefits, 'list-unstyled small mb-0'),
                        )),
                    ), '', '4'),
                ), '4', '', 'g-lg-5'),
            )),
        ), $searchable('cn-open-jobs', lang('jobs, vacancies, work for us, apprenticeship, site engineer'))),
    )),
);

// One job opening: its record.
$career_pages[] = array(
    'key'              => 'job',
    'name'             => lang('job'),
    'folder'           => 'public',
    'title'            => lang('Job Opening'),
    'meta_description' => lang('A job at the firm: what you will do, where and how to apply.'),
    'sitemap'          => false,
    'tree'             => $page(array(
        $widget('job_detail'),
    )),
);

// The staff page that adds a job opening. The form is the Jobs collection;
// the sample jobs are written in when the form is made.
$sample_jobs = array();
foreach ($jobs as $key => $j) {
    $sample_jobs[] = array(
        'title'      => $j['title'],
        'department' => $departments[$j['department']],
        'type'       => $job_types[$j['type']],
        'location'   => $j['location'],
        'summary'    => $j['summary'],
        'duties'     => $duties_text($key),
    );
}
$career_pages[] = array(
    'key'     => 'job_new',
    'name'    => lang('new-job'),
    'folder'  => 'private',
    'title'   => lang('New Job Opening'),
    'search'  => false,
    'sitemap' => false,
    'noindex' => true,
    'form'    => array(
        'form_name'            => lang('Job Openings'),
        'collection'           => 1,
        'confirmation_message' => lang('The job opening is published.'),
        'confirmation_page_id' => '{{tab:careers}}',
        'sample_records'       => $sample_jobs,
    ),
    'tree'    => $staff_page(array(
        $page_header(lang('New Job Opening'), lang('Add the job with its duties; it appears on the careers page as soon as you send it. Take it down from its Edit button when it is filled.')),
        $el('section', 'py-5', lang('Content'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $el('div', 'p-4 rounded-3 border', lang('Form Card'), array($widget('job_form'))),
                    ), '', '8'),
                ), '', 'center'),
            )),
        )),
    )),
);

// The application. What is sent opens the application's own page.
$career_pages[] = array(
    'key'              => 'application',
    'name'             => lang('apply'),
    'folder'           => 'public',
    'title'            => lang('Apply for a Job'),
    'meta_description' => lang('Apply for a job at the firm: your CV and a few words about your experience.'),
    'form'             => array(
        'form_name'            => lang('Job Applications'),
        'confirmation_message' => lang('Thank you, your application has reached us. We answer every application within two weeks.'),
        'confirmation_page_id' => '{{tab:application_view}}',
        'auto_registration'    => 1,
        'notify_email'         => '{{site_email}}',
        'notify_subject'       => lang('New application: ^^job^^'),
        'notify_page_id'       => '{{tab:email_application_new}}',
        'confirm_email'        => 1,
        'confirm_subject'      => lang('We received your application'),
        'confirm_page_id'      => '{{tab:email_application_received}}',
    ),
    'tree'             => $page(array(
        $page_header(lang('Apply for a Job'), lang('Your CV and a few words about your experience. We answer every application within two weeks.')),
        $el('section', 'py-5', lang('Content'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $el('div', 'card border-0 shadow-sm rounded-3', lang('Form Card'), array(
                            $el('div', 'card-body p-4 p-lg-5', lang('Card Body'), array($widget('application_form'))),
                        )),
                    ), '', '8'),
                ), '', 'center'),
            )),
        )),
    ), false),
);

// One application: what was sent, its status and the messages under it.
$career_pages[] = array(
    'key'      => 'application_view',
    'name'     => lang('application'),
    'folder'   => 'registration',
    'title'    => lang('Job Application'),
    'search'   => false,
    'sitemap'  => false,
    'comments' => array('label' => lang('Message'), 'allow_new' => true, 'auto_publish' => true, 'show_date' => true, 'login' => true,
                        'attachments' => true,
                        'email_page' => '{{tab:email_reply}}', 'email_subject' => lang('There is a new message about your application'),
                        'notify_email' => '{{site_email}}'),
    'tree'     => $page(array(
        $widget('application_detail'),
        $comments_section(lang('Messages'), 'application_messages'),
    ), false),
);

// My Applications: the applicant's own applications (for the staff,
// everybody's).
$career_pages[] = array(
    'key'     => 'my_applications',
    'name'    => lang('my-applications'),
    'folder'  => 'registration',
    'title'   => lang('My Applications'),
    'search'  => false,
    'sitemap' => false,
    'tree'    => $page(array(
        $page_header(lang('My Applications'), lang('Your applications, their status and our messages, newest first.')),
        $el('section', 'py-5', lang('Content'), array(
            $container(array(
                $row(array(
                    $col(array($widget('my_applications_list')), '', '8'),
                    $col(array(
                        $el('div', 'p-4 rounded-3 bg-body-tertiary', lang('Open Jobs'), array(
                            $el('p', 'd-flex align-items-center gap-2 fw-semibold mb-2', lang('Title'), array(
                                $icon('bi-briefcase', 'text-primary'),
                                $span(lang('Another job?'), '', lang('Text')),
                            )),
                            $para(lang('See the jobs open now; your details are filled in for you when you apply.'), 'small text-body-secondary mb-3'),
                            $link(lang('See the open jobs'), '{{page:careers}}', 'btn btn-sm btn-primary px-3'),
                        )),
                    ), '', '4'),
                ), '4', '', 'g-lg-5'),
            )),
        )),
    ), false),
);

// The staff's list of every application, newest first, with a search.
$career_pages[] = array(
    'key'     => 'staff_applications',
    'name'    => lang('all-applications'),
    'folder'  => 'private',
    'title'   => lang('Job Applications'),
    'search'  => false,
    'sitemap' => false,
    'tree'    => $staff_page(array(
        $page_header(lang('Job Applications'), lang('Every application, newest first: read the CV, set the status and write to the applicant under each.')),
        $el('section', 'py-5', lang('Content'), array(
            $container(array($widget('staff_applications_list'))),
        )),
    )),
);

// The e-mails: what the firm receives and the reply to the applicant. A
// new message is mailed by email_reply (quotes.php).
$career_pages[] = array_merge($mail_settings, array(
    'key'   => 'email_application_new',
    'name'  => lang('email-new-application'),
    'title' => lang('New Application E-mail'),
    'tree'  => $mail_page(array($widget('email_application_admin'))),
));
$career_pages[] = array_merge($mail_settings, array(
    'key'   => 'email_application_received',
    'name'  => lang('email-application-received'),
    'title' => lang('Application Received E-mail'),
    'tree'  => $mail_page(array($widget('email_application_client'))),
));

// ── Widget pieces ───────────────────────────────────────────────────────

// The applicant's name.
$applicant_name = function () use ($span, $bind) {
    return array(
        $span(lang('Jane'), '', lang('First Name'), $bind('first_name')),
        $span(' ', '', lang('Text')),
        $span(lang('Cooper'), '', lang('Last Name'), $bind('last_name')),
    );
};

// An application in a list: the job, the status and the date sent, linked
// to its page. The staff's row starts with the applicant's name and adds
// the e-mail; the applicant's own adds the messages.
$application_row = function ($staff = false) use ($el, $span, $icon, $bind, $applicant_name, $job_sample, $application_statuses) {
    $item = function ($icon_name, $name, $children) use ($el, $icon) {
        return $el('span', 'd-inline-flex align-items-center gap-1', $name, array_merge(array($icon($icon_name)), $children));
    };
    $items = array(
        $item('bi-calendar3', lang('Sent'), array($span(lang('Date'), '', lang('Date'), $bind('submitted_date_and_time')))),
    );
    if ($staff) {
        $items[] = $item('bi-envelope', lang('Email'), array($span('jane.cooper@example.com', '', lang('Email'), $bind('email'))));
        $items[] = $item('bi-telephone', lang('Phone'), array($span('+90 555 000 00 00', '', lang('Phone'), $bind('phone'))));
        $title = $el('span', 'fw-semibold', lang('Applicant'), array_merge($applicant_name(), array(
            $span(' · ', 'text-body-secondary', lang('Text')),
            $span($job_sample['title'], 'text-body-secondary fw-normal', lang('Job'), $bind('job')),
        )));
    } else {
        $items[] = $item('bi-chat-left-text', lang('Messages'), array($span('0', '', lang('Reply Count'), $bind('number_of_comments'))));
        $title = $span($job_sample['title'], 'fw-semibold', lang('Job'), $bind('job'));
    }
    return $el('a', 'list-group-item list-group-item-action p-3', lang('Job Application'), array(
        $el('div', 'd-flex flex-wrap align-items-center justify-content-between gap-2 mb-1', lang('Top Line'), array(
            $title,
            $span($application_statuses[0], 'badge rounded-pill bg-primary-subtle text-primary-emphasis', lang('Status'), $bind('status')),
        )),
        $el('div', 'd-flex flex-wrap column-gap-3 row-gap-1 small text-body-secondary', lang('Details'), $items),
    ), array('href' => '#', '_bindings' => array('href' => 'form_item_view')));
};

// The lines of an application, for its e-mails; the staff's adds the
// applicant's contact and words.
$mail_application = function ($staff) use ($mail_box, $mail_line, $span, $bind, $applicant_name, $job_sample, $sample_letter) {
    $lines = array(
        $mail_line(lang('Job'), array($span($job_sample['title'], '', lang('Job'), $bind('job')))),
    );
    if ($staff) {
        $lines[] = $mail_line(lang('Applicant'), $applicant_name());
        $lines[] = $mail_line(lang('Email'), array($span('jane.cooper@example.com', '', lang('Email'), $bind('email'))));
        $lines[] = $mail_line(lang('Phone'), array($span('+90 555 000 00 00', '', lang('Phone'), $bind('phone'))));
        $lines[] = $mail_line(lang('Experience'), array($span(lang('Three to five years'), '', lang('Experience'), $bind('experience'))));
        $lines[] = $mail_line(lang('A few words about you'), array($span($sample_letter, '', lang('Text'), $bind('cover_letter'))));
    }
    return $mail_box($lines, lang('Job Application'));
};

// The form detail settings of a widget reading an application: the
// application's page (the applicant's own, and the staff's) and the
// e-mail pages (any reader: drawn for the submission just sent).
$application_detail_widget = function ($page_key, $missing, $access) {
    return array(
        'page'   => $page_key,
        'slug'   => lang('form-detail'),
        'config' => array(
            'regionType'          => 'form_item_view',
            'custom_form_page_id' => '{{tab:application}}',
            'access_control'      => $access,
            'not_found_message'   => $missing,
        ),
    );
};
// A list of applications: the applicant's own (the staff see them all),
// or the staff's with a search.
$application_list = function ($page_key, $staff, $empty) use ($widget_root, $el, $loop, $application_row) {
    $config = array(
        'regionType'          => 'form_list_view',
        'custom_form_page_id' => '{{tab:application}}',
        'detail_page_id'      => '{{tab:application_view}}',
        'items_per_page'      => 20,
        'order_by_field'      => 'submitted_date_and_time',
        'order_by_direction'  => 'DESC',
    );
    if ($staff) {
        $config += array(
            'search_enabled' => true,
            'search_fields'  => array('first_name', 'last_name', 'email', 'job', 'status'),
            'search_width'   => 'half',
            'search_label'   => lang('Search by name, e-mail, job or status...'),
        );
    } else {
        $config += array(
            'viewer_filter'           => true,
            'viewer_filter_submitter' => true,
            'viewer_filter_watcher'   => true,
            'viewer_filter_editor'    => true,
        );
    }
    $config['empty_message'] = $empty;
    return array(
        'page'   => $page_key,
        'slug'   => lang('form-list'),
        'config' => $config,
        'tree'   => $widget_root(array(
            $el('div', 'list-group', lang('Application List'), array($loop(array($application_row($staff))))),
        )),
    );
};

$experience_choices = array(lang('Starting out'), lang('Up to two years'), lang('Three to five years'), lang('More than five years'));
$application_missing = lang('This application could not be found, or it is not yours to see.');
$job_missing = lang('This job could not be found. It may have been filled and taken down.');

$career_widgets = array(

    // The staff's Jobs form: one opening.
    'job_form' => array(
        'page'   => 'job_new',
        'slug'   => 'form',
        'config' => array('regionType' => 'custom_form', 'form_source' => 'page'),
        'tree'   => $widget_root(array(
            $form_step(1, lang('The job')),
            $field('cn-job-title', lang('Job Title'), pg_cf_with_role($input('cn-job-title', 'text', 'title', 'off'), 'title')),
            $row(array(
                $col(array($field('cn-job-department', lang('Department'), pg_cf_with_role($select('cn-job-department', 'department', lang('Choose a department…'), $department_titles), 'category'))), '6'),
                $col(array($field('cn-job-type', lang('Job Type'), $select('cn-job-type', 'type', '', $job_type_titles, true, $job_type_titles[0]))), '6'),
            ), '3'),
            $field('cn-job-location', lang('Location'), $input('cn-job-location', 'text', 'location', 'off'), 'mb-3',
                lang('Where the work is, for example Sites across the region.')),
            $form_step(2, lang('Words')),
            $field('cn-job-summary', lang('Summary'), pg_cf_with_role($textarea('cn-job-summary', 'summary', 2), 'description'), 'mb-3',
                lang('One or two lines for the careers page.')),
            $field('cn-job-duties', lang('Duties'), $textarea('cn-job-duties', 'duties', 5), 'mb-3', lang('One duty on each line.')),
            $submit(lang('Publish')),
            $loop(array()),
        )),
    ),

    // Every opening, by department, A to Z.
    'jobs_list' => array(
        'page'   => 'careers',
        'slug'   => lang('form-list'),
        'config' => array(
            'regionType'          => 'form_list_view',
            'custom_form_page_id' => '{{tab:job_new}}',
            'detail_page_id'      => '{{tab:job}}',
            'items_per_page'      => 20,
            'order_by_field'      => 'title',
            'order_by_direction'  => 'ASC',
            'empty_message'       => lang('No jobs are open just now. Send an open application: we keep it for the next one.'),
        ),
        'tree'   => $widget_root(array($loop(array($job_card())))),
    ),

    // One opening: the department and type, the title, the location, the
    // summary, the duties, what the firm offers and the way to apply for
    // this job (?job=).
    'job_detail' => array(
        'page'   => 'job',
        'slug'   => lang('form-detail'),
        'config' => array(
            'regionType'          => 'form_item_view',
            'custom_form_page_id' => '{{tab:job_new}}',
            'access_control'      => 'public',
            'not_found_message'   => $job_missing,
        ),
        'tree'   => $widget_root(array(
            $container(array(
                $el('div', 'text-center py-5', lang('Not Found'), array(
                    $icon('bi-briefcase', 'display-5 text-body-tertiary'),
                    $heading('h2', lang('Job not found'), 'h3 fw-semibold mt-3'),
                    $para($job_missing, 'text-body-secondary mb-4', $bind('not_found')),
                    $link(lang('See the open jobs'), '{{page:careers}}', 'btn btn-primary px-4'),
                ), $show_when('record_not_found')),
                $el('article', '', lang('Job Opening'), array(
                    $el('div', 'd-flex flex-wrap align-items-center justify-content-between gap-2 mb-3', lang('Toolbar'), array(
                        $el('a', 'd-inline-flex align-items-center gap-1 small fw-semibold link-primary text-decoration-none', lang('Link'), array(
                            $icon('bi-arrow-left'),
                            $span(lang('All Jobs'), '', lang('Link Text')),
                        ), array('href' => '{{page:careers}}')),
                        $edit_button(),
                    )),
                    $el('div', 'd-flex flex-wrap gap-2 mb-2', lang('Badges'), array(
                        $span($departments[$job_sample['department']], 'badge rounded-pill bg-primary-subtle text-primary-emphasis', lang('Department'), $bind('department')),
                        $span($job_types[$job_sample['type']], 'badge rounded-pill text-bg-secondary', lang('Job Type'), $bind('type')),
                    )),
                    $heading('h1', $job_sample['title'], 'pcn-display display-6 mb-2', $bind('title')),
                    $el('p', 'd-flex align-items-center gap-1 fw-semibold mb-2', lang('Location'), array(
                        $icon('bi-geo-alt', 'text-primary'),
                        $span($job_sample['location'], '', lang('Location'), $bind('location')),
                    )),
                    $para($job_sample['summary'], 'lead text-body-secondary mb-4', $bind('summary')),
                    $row(array(
                        $col(array(
                            $el('div', 'pg-hide-if-empty mb-4', lang('Duties'), array(
                                $heading('h2', lang('What you will do'), 'pcn-display h4 mb-2'),
                                $el('div', 'pg-field-value lh-lg', lang('Value'), array(), $bound($duties_text($job_sample_key), 'duties')),
                            )),
                            $heading('h2', lang('What we offer'), 'pcn-display h4 mb-2'),
                            $check_list($job_benefits, 'list-unstyled mb-4'),
                        ), '', '8'),
                        $col(array(
                            $el('aside', 'card border-0 shadow-sm rounded-3', lang('Apply Card'), array(
                                $el('div', 'card-body p-4', lang('Card Body'), array(
                                    $heading('h2', lang('Interested?'), 'pcn-display h5 mb-2'),
                                    $para(lang('Send your CV and a few words about your experience; we answer every application within two weeks.'), 'small text-body-secondary mb-3'),
                                    // Opens the application with this job
                                    // chosen (?job=).
                                    $link(lang('Apply for this job'), '#', 'btn btn-primary w-100', array(),
                                        $cn_page_link('application', array('job' => 'title'))),
                                )),
                            )),
                        ), '', '4'),
                    ), '4', '', 'g-lg-5'),
                ), $show_when('record_found')),
            ), 'py-5'),
        )),
    ),

    // The application: the fields are the page's form. The job starts with
    // what a link sends (?job=). The Status is the firm's (office use
    // only): every application starts as Application received.
    'application_form' => array(
        'page'   => 'application',
        'slug'   => 'form',
        'config' => array('regionType' => 'custom_form', 'form_source' => 'page'),
        'tree'   => $widget_root(array(
            $field('cn-app-job', lang('Job'), $select('cn-app-job', 'job', lang('Choose a job…'), $application_job_choices, true, '', array('url_param' => 'job'))),
            $row(array(
                $col(array($field('cn-app-first-name', lang('First Name'), $input('cn-app-first-name', 'text', 'first_name', 'given-name', true, array('contact_field' => 'first_name')))), '6'),
                $col(array($field('cn-app-last-name', lang('Last Name'), $input('cn-app-last-name', 'text', 'last_name', 'family-name', true, array('contact_field' => 'last_name')))), '6'),
                $col(array($field('cn-app-email', lang('Email'), $input('cn-app-email', 'email', 'email', 'email', true, array('contact_field' => 'email_address')))), '6'),
                $col(array($field('cn-app-phone', lang('Phone'), $input('cn-app-phone', 'tel', 'phone', 'tel', true, array('contact_field' => 'mobile_phone')))), '6'),
            ), '3'),
            $field('cn-app-experience', lang('Experience'), $select('cn-app-experience', 'experience', lang('Choose…'), $experience_choices), 'mb-3',
                lang('Years in construction, in the job you apply for or a similar one.')),
            $field('cn-app-cv', lang('CV'), $file('cn-app-cv', 'cv', 'private', '.pdf,.doc,.docx,.odt'), 'mb-3', lang('PDF or Word.')),
            $field('cn-app-letter', lang('A few words about you'), $textarea('cn-app-letter', 'cover_letter', 5), 'mb-3',
                lang('The sites you have worked on, your trade or certificates, and when you could start.')),
            $field('cn-app-status', lang('Status'), $select('cn-app-status', 'status', '', $application_statuses, false, $application_statuses[0], array('office_use_only' => true))),
            $agree('cn-app-consent', 'consent', lang('I agree that the firm keeps my application and CV to consider it, as its privacy notice explains.'), true),
            $captcha(),
            $submit(lang('Send Application'), 'btn btn-primary btn-lg px-4'),
            $loop(array()),
        )),
    ),

    // One application: what was sent and its status, for the applicant and
    // the staff; the messages follow as comments.
    'application_detail' => $application_detail_widget('application_view', $application_missing, 'submitter_only') + array(
        'tree' => array('type' => 'root', 'props' => array(), 'children' => array(
            $container(array(
                $row(array(
                    $col(array(
                        $el('div', 'text-center py-5', lang('Not Found'), array(
                            $icon('bi-file-earmark-x', 'display-5 text-body-tertiary'),
                            $heading('h2', lang('Application not found'), 'h3 fw-semibold mt-3'),
                            $para($application_missing, 'text-body-secondary mb-4', $bind('not_found')),
                            $link(lang('My Applications'), '{{page:my_applications}}', 'btn btn-primary px-4'),
                        ), $show_when('record_not_found')),
                        $el('div', '', lang('Record'), array(
                            $el('div', 'alert alert-success d-flex gap-2', lang('Sent Notice'), array(
                                $icon('bi-check-circle', 'mt-1'),
                                $span(lang('Thank you, your application has reached us. We answer every application within two weeks; this page shows its status, and you can write to us below.'), '', lang('Text')),
                            ), array_merge(array('_attrs' => array($attr('role', 'status'))), $show_when('submitted_this_session'))),
                            $el('div', 'alert alert-info', lang('New Account'), array(
                                $el('p', 'fw-semibold mb-2', lang('Title'), array(), array('text' => lang('We opened an account for you'))),
                                $el('p', 'mb-1', lang('Sign-in Email'), array(
                                    $span(lang('Email') . ': ', '', lang('Label')),
                                    $span('sample@example.com', 'fw-semibold', lang('Value'), $bind('__new_account_email')),
                                )),
                                // The single-use set password link, for the person who sent the form
                                // while it is live; the sentence below covers a used or expired link.
                                $link(lang('Set your password'), '#', 'btn btn-sm btn-primary', array(),
                                    array('_bindings' => array('href' => '__new_account_set_password_url', 'eo_visible_if' => 'has_set_password_link'))),
                                $para(lang('You can set a password with "Forgot password" on the sign-in page.'), 'small mt-2 mb-0'),
                            ), $show_when('has_new_account')),
                            $el('div', 'd-flex flex-wrap align-items-center justify-content-between gap-2 mb-3', lang('Toolbar'), array(
                                $el('a', 'd-inline-flex align-items-center gap-1 small fw-semibold link-primary text-decoration-none', lang('Link'), array(
                                    $icon('bi-arrow-left'),
                                    $span(lang('My Applications'), '', lang('Link Text')),
                                ), array('href' => '{{page:my_applications}}')),
                                $edit_button(lang('Change Status')),
                            )),
                            $el('div', 'card border-0 shadow-sm rounded-3 mb-4', lang('Application Card'), array(
                                $el('div', 'card-body p-4', lang('Card Body'), array(
                                    $el('div', 'd-flex flex-wrap align-items-start justify-content-between gap-2 mb-3', lang('Title'), array(
                                        $el('div', '', lang('Words'), array(
                                            $para(lang('Job Application'), 'pcn-eyebrow small text-uppercase fw-semibold text-primary mb-1'),
                                            $heading('h1', $job_sample['title'], 'pcn-display h3 mb-0', $bind('job')),
                                        )),
                                        $span($application_statuses[0], 'badge rounded-pill bg-primary-subtle text-primary-emphasis fs-6', lang('Status'), $bind('status')),
                                    )),
                                    $el('dl', 'row small mb-0', lang('Details'), array_merge(
                                        $detail(lang('Applicant'), '', lang('Applicant'), $applicant_name(), true),
                                        $detail(lang('Experience'), '', lang('Experience'), $bound($experience_choices[2], 'experience')),
                                        $detail(lang('Sent'), '', lang('Sent'), $bound(lang('Date'), 'submitted_date_and_time')),
                                        $detail(lang('CV'), 'text-break', lang('CV'), array(
                                            $link('cv.pdf', '#', '', array(), array('target' => '_blank', 'rel' => 'noopener',
                                                '_bindings' => array('href' => 'cv', 'text' => 'cv'))),
                                        ), true),
                                        $detail(lang('Reference Code'), 'mb-0 font-monospace', lang('Reference Code'), $bound('ABC123', 'reference_code'))
                                    )),
                                )),
                            )),
                            $el('div', 'pg-hide-if-empty p-4 rounded-3 bg-body-tertiary mb-4', lang('A few words about you'), array(
                                $para(lang('A few words about you'), 'small fw-semibold text-body-secondary mb-2'),
                                $el('div', 'pg-field-value lh-lg', lang('Value'), array(), $bound($sample_letter, 'cover_letter')),
                            )),
                        ), $show_when('record_found')),
                    ), '', '8'),
                ), '', 'center'),
            ), 'py-5'),
        )),
    ),

    // The messages under an application: the page's own Comments widget,
    // shown only to whoever may read the application above it. Oldest
    // first, all on one page, with a file each.
    'application_messages' => array(
        'page'   => 'application_view',
        'slug'   => lang('comments'),
        'config' => array(
            'regionType' => 'comments',
            'order'      => 'oldest',
            'featured'   => 'none',
            'layout'     => 'flat',
            'max_depth'  => 1,
            'per_page'   => 0,
        ),
        'tree'   => $comment_thread(array(
            'title'     => lang('Messages'),
            'empty'     => lang('No messages yet. The day of your interview comes here; you can write to us about your application too.'),
            'heading'   => lang('Write to us'),
            'label'     => lang('Your message'),
            'sign_in'   => lang('Sign in to write to us.'),
            'submit'    => lang('Send message'),
            'watch_box' => '',
            'follow'    => '',
            'unfollow'  => '',
            'close'     => lang('Stop new messages'),
            'reopen'    => lang('Allow new messages'),
            'reply'     => false,
            'watch'     => false,
        )),
    ),

    'my_applications_list' => $application_list('my_applications', false,
        lang('No applications yet. See the jobs open now, or send an open application.')),

    'staff_applications_list' => $application_list('staff_applications', true, lang('No applications yet.')),

    // The e-mail pages' widgets: drawn for the submission just sent.
    'email_application_admin' => $application_detail_widget('email_application_new', lang('This application was not found.'), 'public') + array(
        'tree' => $widget_root(array(
            $mail_heading(lang('A new application')),
            $mail_para(lang('It came from the careers page. Read the CV, then set the status on the application\'s page; what you write under it reaches the applicant by e-mail.')),
            $mail_application(true),
            $mail_button(lang('Open the application'), '{{page:application_view}}?r=^^reference_code^^'),
            $mail_reference(),
        )),
    ),
    'email_application_client' => $application_detail_widget('email_application_received', lang('This application was not found.'), 'public') + array(
        'tree' => $widget_root(array(
            $mail_heading(lang('We received your application')),
            $mail_greeting(),
            $mail_para(lang('Thank you for your interest in the firm. We read every application and answer within two weeks, on the application\'s page and by e-mail.')),
            $mail_application(false),
            $mail_button(lang('Open my application'), '{{page:application_view}}?r=^^reference_code^^'),
            $mail_new_account(),
        )),
    ),
);

return array('pages' => $career_pages, 'widgets' => $career_widgets);
