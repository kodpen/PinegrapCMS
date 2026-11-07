<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Architecture & Interiors Studio", its careers: the staff
 * page that adds a job opening (the Job Openings form, a collection, with
 * the sample jobs written in when it is made), the careers page listing
 * every opening, the job's own page with its apply link, the application,
 * the application's own page with its messages, My Applications and the
 * staff's list, their e-mails and the widgets that draw them. Included
 * after parts.php, mail.php, projects.php (its page link) and enquiries.php
 * (its record, list and e-mail pieces) in the same scope; returns
 * array('pages' => ..., 'widgets' => ...).
 *
 * A job opening is a record of the Job Openings form; team and type are
 * pick lists whose answers are the words parts.php gives. An application
 * is a record of the Job Applications form. The job's page opens it with
 * the job chosen (?job=, the job's title); an applicant without an account
 * gets one (auto-registration), so the application's page and My
 * Applications show it at once. A designer is judged by the work: the
 * application asks for a portfolio as a link or a file beside the CV, both
 * files kept in the template's private folder. Its Status is an
 * office-use-only field the studio changes in the control panel; the
 * interview day is written under it as a message (the page's comments),
 * mailed to the applicant and to the studio.
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
$job_sample_key = 'architect';
$job_sample = $jobs[$job_sample_key];
$sample_letter = lang('I have four years in a practice that designs schools and housing, two of them running projects on site, and I would like to work on reuse projects like yours.');
$sample_portfolio = 'https://portfolio.example.com/jane-cooper';

// What every job at the studio offers, beside the openings.
$job_benefits = array(
    lang('Your name on the credits of every project you work on'),
    lang('Time on site, not only at the desk'),
    lang('Courses, study trips and the professional exams paid by the studio'),
    lang('A drawing table by the window in a former print works'),
);

// ── Pages ───────────────────────────────────────────────────────────────

// Careers: every opening, why work here and the open application.
$career_pages[] = array(
    'key'              => 'careers',
    'name'             => lang('careers'),
    'folder'           => 'public',
    'title'            => lang('Careers'),
    'meta_description' => lang('Jobs at the studio: architects, interior designers, visualisers and interns.'),
    'tree'             => $page(array(
        $page_header(lang('Careers'), lang('Architects, interior designers and landscape architects draw every project we build. See the jobs open now, or send an open application with your portfolio.')),
        $el('section', 'py-5', lang('Open Jobs'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $widget('jobs_list'),
                        $el('div', 'd-flex flex-wrap align-items-center gap-3 mt-4', lang('Open Application'), array(
                            $para(lang('No job for you here? We read every open application and keep the good portfolios.'), 'text-body-secondary mb-0'),
                            $link(lang('Send an open application'), '{{page:application}}?' . $query(array('job' => lang('Open application'))), 'btn btn-dark rounded-0 px-4'),
                        )),
                    ), '', '8'),
                    $col(array(
                        $el('div', 'p-4 bg-body-tertiary', lang('Why Work Here'), array(
                            $heading('h2', lang('Why work with us'), 'pai-display h5 mb-3'),
                            $check_list($job_benefits, 'list-unstyled small mb-0'),
                        )),
                    ), '', '4'),
                ), '4', '', 'g-lg-5'),
            )),
        ), $searchable('ai-open-jobs', lang('jobs, vacancies, work for us, internship, architect, interior designer'))),
    )),
);

// One job opening: its record.
$career_pages[] = array(
    'key'              => 'job',
    'name'             => lang('job'),
    'folder'           => 'public',
    'title'            => lang('Job Opening'),
    'meta_description' => lang('A job at the studio: what you will do, where and how to apply.'),
    'sitemap'          => false,
    'tree'             => $page(array(
        $widget('job_detail'),
    )),
);

// The staff page that adds a job opening. The form is the Job Openings
// collection; the sample jobs are written in when the form is made.
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
                        $el('div', 'p-4 border', lang('Form Card'), array($widget('job_form'))),
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
    'meta_description' => lang('Apply for a job at the studio: your portfolio, your CV and a few words about your work.'),
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
        $page_header(lang('Apply for a Job'), lang('Your portfolio, your CV and a few words about your work. We answer every application within two weeks.')),
        $el('section', 'py-5', lang('Content'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $el('div', 'p-4 p-lg-5 border', lang('Form Card'), array($widget('application_form'))),
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
    'comments' => $record_page_comments(lang('There is a new message about your application')),
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
    'tree'    => $own_list_page(lang('My Applications'), lang('Your applications, their status and our messages, newest first.'),
        'my_applications_list', lang('Another job?'),
        lang('See the jobs open now; your details are filled in for you when you apply.'), lang('See the open jobs'), '{{page:careers}}'),
);

// The staff's list of every application, newest first, with a search.
$career_pages[] = array(
    'key'     => 'staff_applications',
    'name'    => lang('all-applications'),
    'folder'  => 'private',
    'title'   => lang('Job Applications'),
    'search'  => false,
    'sitemap' => false,
    'tree'    => $staff_list_page(lang('Job Applications'),
        lang('Every application, newest first: look at the portfolio, set the status and write to the applicant under each.'), 'staff_applications_list'),
);

// The e-mails: what the studio receives and the reply to the applicant. A
// new message is mailed by email_reply (enquiries.php).
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

$experience_choices = array(lang('Student or recent graduate'), lang('Up to two years'), lang('Three to five years'), lang('More than five years'));
$application_missing = lang('This application could not be found, or it is not yours to see.');
$job_missing = lang('This job could not be found. It may have been filled and taken down.');

// An application in a list: the job, the status and the date sent, linked
// to its page. The staff's row starts with the applicant's name and adds
// the phone; the applicant's own adds the messages (enquiries.php).
$application_row = function ($staff) use ($request_row, $row_item, $job_sample, $experience_choices, $application_statuses) {
    return $request_row($staff, $job_sample['title'], array(
        $row_item('bi-calendar3', lang('Sent'), lang('Date'), 'submitted_date_and_time'),
        $row_item('bi-mortarboard', lang('Experience'), $experience_choices[2], 'experience'),
    ), $application_statuses[0], lang('Job Application'), 'job');
};

// The lines of an application, for its e-mails; the staff's adds the
// applicant's contact, portfolio and words.
$mail_application = function ($staff) use ($mail_box, $mail_line, $span, $bind, $client_name, $job_sample, $sample_letter, $sample_portfolio, $experience_choices) {
    $lines = array(
        $mail_line(lang('Job'), array($span($job_sample['title'], '', lang('Job'), $bind('job')))),
    );
    if ($staff) {
        $lines[] = $mail_line(lang('Applicant'), $client_name());
        $lines[] = $mail_line(lang('Email'), array($span('jane.cooper@example.com', '', lang('Email'), $bind('email'))));
        $lines[] = $mail_line(lang('Phone'), array($span('+90 555 000 00 00', '', lang('Phone'), $bind('phone'))));
        $lines[] = $mail_line(lang('Experience'), array($span($experience_choices[2], '', lang('Experience'), $bind('experience'))));
        $lines[] = $mail_line(lang('Portfolio Link'), array($span($sample_portfolio, '', lang('Portfolio Link'), $bind('portfolio_link'))));
        $lines[] = $mail_line(lang('A few words about your work'), array($span($sample_letter, '', lang('Text'), $bind('cover_letter'))));
    }
    return $mail_box($lines, lang('Job Application'));
};

// A file of the application on its page: a link to it for whoever may
// read the record.
$file_link = function ($sample, $field) use ($link) {
    return array($link($sample, '#', 'link-dark', array(), array('target' => '_blank', 'rel' => 'noopener',
        '_bindings' => array('href' => $field, 'text' => $field))));
};

$career_widgets = array(

    // The staff's Job Openings form: one opening.
    'job_form' => array(
        'page'   => 'job_new',
        'slug'   => 'form',
        'config' => array('regionType' => 'custom_form', 'form_source' => 'page'),
        'tree'   => $widget_root(array(
            $form_step(1, lang('The job')),
            $field('ai-job-title', lang('Job Title'), pg_cf_with_role($input('ai-job-title', 'text', 'title', 'off'), 'title')),
            $row(array(
                $col(array($field('ai-job-department', lang('Team'), pg_cf_with_role($select('ai-job-department', 'department', lang('Choose a team…'), $department_titles), 'category'))), '6'),
                $col(array($field('ai-job-type', lang('Job Type'), $select('ai-job-type', 'type', '', $job_type_titles, true, $job_type_titles[0]))), '6'),
            ), '3'),
            $field('ai-job-location', lang('Location'), $input('ai-job-location', 'text', 'location', 'off'), 'mb-3',
                sprintf(lang('Where the work is, for example %s.'), $jobs['visualiser']['location'])),
            $form_step(2, lang('Words')),
            $field('ai-job-summary', lang('Summary'), pg_cf_with_role($textarea('ai-job-summary', 'summary', 2), 'description'), 'mb-3',
                lang('One or two lines for the careers page.')),
            $field('ai-job-duties', lang('Duties'), $textarea('ai-job-duties', 'duties', 5), 'mb-3', lang('One duty on each line.')),
            $submit(lang('Publish')),
            $loop(array()),
        )),
    ),

    // Every opening, A to Z.
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
            'empty_message'       => lang('No jobs are open just now. Send an open application with your portfolio: we keep it for the next one.'),
        ),
        'tree'   => $widget_root(array(
            $el('div', 'border-top', lang('Job List'), array($loop(array($job_card())))),
        )),
    ),

    // One opening: the team and type, the title, the location, the
    // summary, the duties, what the studio offers and the way to apply for
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
                    $heading('h2', lang('Job not found'), 'pai-display h3 mt-3'),
                    $para($job_missing, 'text-body-secondary mb-4', $bind('not_found')),
                    $link(lang('See the open jobs'), '{{page:careers}}', 'btn btn-dark rounded-0 px-4'),
                ), $show_when('record_not_found')),
                $el('article', '', lang('Job Opening'), array(
                    $el('div', 'd-flex flex-wrap align-items-center justify-content-between gap-2 mb-3', lang('Toolbar'), array(
                        $el('a', 'd-inline-flex align-items-center gap-1 small fw-semibold link-dark text-decoration-none', lang('Link'), array(
                            $icon('bi-arrow-left'),
                            $span(lang('All Jobs'), '', lang('Link Text')),
                        ), array('href' => '{{page:careers}}')),
                        $edit_button(),
                    )),
                    $el('p', 'pai-eyebrow d-flex flex-wrap gap-2 small text-uppercase fw-semibold text-body-secondary mb-2', lang('Team and Type'), array(
                        $span($departments[$job_sample['department']], '', lang('Team'), $bind('department')),
                        $span('·', '', lang('Separator'), array('_attrs' => array($attr('aria-hidden', 'true')))),
                        $span($job_types[$job_sample['type']], '', lang('Job Type'), $bind('type')),
                    )),
                    $heading('h1', $job_sample['title'], 'pai-display display-6 mb-2', $bind('title')),
                    $el('p', 'd-flex align-items-center gap-1 fw-semibold mb-2', lang('Location'), array(
                        $icon('bi-geo-alt'),
                        $span($job_sample['location'], '', lang('Location'), $bind('location')),
                    )),
                    $para($job_sample['summary'], 'lead text-body-secondary mb-4', $bind('summary')),
                    $row(array(
                        $col(array(
                            $el('div', 'pg-hide-if-empty mb-4', lang('Duties'), array(
                                $heading('h2', lang('What you will do'), 'pai-display h4 mb-2'),
                                $el('div', 'pg-field-value lh-lg', lang('Value'), array(), $bound($duties_text($job_sample_key), 'duties')),
                            )),
                            $heading('h2', lang('What we offer'), 'pai-display h4 mb-2'),
                            $check_list($job_benefits, 'list-unstyled mb-4'),
                        ), '', '8'),
                        $col(array(
                            $el('aside', 'border-top border-dark-subtle pt-4', lang('Apply'), array(
                                $heading('h2', lang('Interested?'), 'pai-display h5 mb-2'),
                                $para(lang('Send your portfolio, your CV and a few words about your work; we answer every application within two weeks.'), 'small text-body-secondary mb-3'),
                                // Opens the application with this job
                                // chosen (?job=).
                                $link(lang('Apply for this job'), '#', 'btn btn-dark rounded-0 w-100', array(),
                                    $ai_page_link('application', array('job' => 'title'))),
                            )),
                        ), '', '4'),
                    ), '4', '', 'g-lg-5'),
                ), $show_when('record_found')),
            ), 'py-5'),
        )),
    ),

    // The application: the fields are the page's form. The job starts with
    // what a link sends (?job=). The Status is the studio's (office use
    // only): every application starts as Application received.
    'application_form' => array(
        'page'   => 'application',
        'slug'   => 'form',
        'config' => array('regionType' => 'custom_form', 'form_source' => 'page'),
        'tree'   => $widget_root(array(
            $form_step(1, lang('The job')),
            $field('ai-app-job', lang('Job'), $select('ai-app-job', 'job', lang('Choose a job…'), $application_job_choices, true, '', array('url_param' => 'job'))),
            $field('ai-app-experience', lang('Experience'), $select('ai-app-experience', 'experience', lang('Choose…'), $experience_choices), 'mb-3',
                lang('Years in practice, in the job you apply for or a similar one.')),
            $form_step(2, lang('Your work')),
            $field('ai-app-portfolio-link', lang('Portfolio Link'), $input('ai-app-portfolio-link', 'url', 'portfolio_link', 'url', false), 'mb-3',
                lang('A website or an online folder with your work, if you have one.')),
            $field('ai-app-portfolio', lang('Portfolio'), $file('ai-app-portfolio', 'portfolio', 'private', '.pdf'), 'mb-3',
                lang('A PDF of a few projects, if you have no link; keep it under 20 MB.')),
            $field('ai-app-cv', lang('CV'), $file('ai-app-cv', 'cv', 'private', '.pdf,.doc,.docx,.odt'), 'mb-3', lang('PDF or Word.')),
            $field('ai-app-letter', lang('A few words about your work'), $textarea('ai-app-letter', 'cover_letter', 5), 'mb-3',
                lang('The projects you are proudest of, what you did on them, and when you could start.')),
            $form_step(3, lang('How to reach you')),
            $row(array(
                $col(array($field('ai-app-first-name', lang('First Name'), $input('ai-app-first-name', 'text', 'first_name', 'given-name', true, array('contact_field' => 'first_name')))), '6'),
                $col(array($field('ai-app-last-name', lang('Last Name'), $input('ai-app-last-name', 'text', 'last_name', 'family-name', true, array('contact_field' => 'last_name')))), '6'),
                $col(array($field('ai-app-email', lang('Email'), $input('ai-app-email', 'email', 'email', 'email', true, array('contact_field' => 'email_address')))), '6'),
                $col(array($field('ai-app-phone', lang('Phone'), $input('ai-app-phone', 'tel', 'phone', 'tel', true, array('contact_field' => 'mobile_phone')))), '6'),
            ), '3'),
            $field('ai-app-status', lang('Status'), $select('ai-app-status', 'status', '', $application_statuses, false, $application_statuses[0], array('office_use_only' => true))),
            $agree('ai-app-consent', 'consent', lang('I agree that the studio keeps my application, portfolio and CV to consider it, as its privacy notice explains.'), true),
            $captcha(),
            $submit(lang('Send Application'), 'btn btn-dark btn-lg rounded-0 px-4'),
            $loop(array()),
        )),
    ),

    // One application: what was sent and its status, for the applicant and
    // the staff; the messages follow as comments.
    'application_detail' => $detail_widget('application_view', 'application', $application_missing, 'submitter_only') + array(
        'tree' => $record_tree(array(
            'not_found_title' => lang('Application not found'),
            'missing'         => $application_missing,
            'list_title'      => lang('My Applications'),
            'list_key'        => 'my_applications',
            'sent'            => lang('Thank you, your application has reached us. We answer every application within two weeks; this page shows its status, and you can write to us below.'),
            'eyebrow'         => lang('Job Application'),
            'service'         => $job_sample['title'],
            'title_field'     => 'job',
            'status'          => $application_statuses[0],
            'details'         => array_merge(
                $detail(lang('Applicant'), '', lang('Applicant'), $client_name(), true),
                $detail(lang('Experience'), '', lang('Experience'), $bound($experience_choices[2], 'experience')),
                $detail(lang('Sent'), '', lang('Sent'), $bound(lang('Date'), 'submitted_date_and_time')),
                $detail(lang('Portfolio Link'), 'text-break', lang('Portfolio Link'), $file_link($sample_portfolio, 'portfolio_link'), true),
                $detail(lang('Portfolio'), 'text-break', lang('Portfolio'), $file_link('portfolio.pdf', 'portfolio'), true),
                $detail(lang('CV'), 'text-break', lang('CV'), $file_link('cv.pdf', 'cv'), true),
                $detail(lang('Reference Code'), 'mb-0 font-monospace', lang('Reference Code'), $bound('ABC123', 'reference_code'))
            ),
            'words_label'     => lang('A few words about your work'),
            'words'           => $sample_letter,
            'words_field'     => 'cover_letter',
            'note'            => lang('The day of your interview comes below. A question, or more work to show? Write to us here.'),
        )),
    ),

    // The messages under an application (enquiries.php).
    'application_messages' => $messages_widget('application_view',
        lang('No messages yet. The day of your interview comes here; you can write to us about your application too.')),

    'my_applications_list' => $request_list('my_applications', 'application', 'application_view', false,
        lang('No applications yet. See the jobs open now, or send an open application with your portfolio.'),
        array(), '', $application_row(false)),

    'staff_applications_list' => $request_list('staff_applications', 'application', 'application_view', true, lang('No applications yet.'),
        array('first_name', 'last_name', 'email', 'job', 'experience', 'status'),
        lang('Search by name, e-mail, job, experience or status...'), $application_row(true)),

    // The e-mail pages' widgets: drawn for the submission just sent.
    'email_application_admin' => $detail_widget('email_application_new', 'application', lang('This application was not found.'), 'public') + array(
        'tree' => $widget_root(array(
            $mail_heading(lang('A new application')),
            $mail_para(lang('It came from the careers page. Look at the portfolio and the CV, then set the status on the application\'s page; what you write under it reaches the applicant by e-mail.')),
            $mail_application(true),
            $mail_button(lang('Open the application'), '{{page:application_view}}?r=^^reference_code^^'),
            $mail_reference(),
        )),
    ),
    'email_application_client' => $detail_widget('email_application_received', 'application', lang('This application was not found.'), 'public') + array(
        'tree' => $widget_root(array(
            $mail_heading(lang('We received your application')),
            $mail_greeting(),
            $mail_para(lang('Thank you for your interest in the studio. We look at every portfolio and answer within two weeks, on the application\'s page and by e-mail.')),
            $mail_application(false),
            $mail_button(lang('Open my application'), '{{page:application_view}}?r=^^reference_code^^'),
            $mail_new_account(),
        )),
    ),
);

return array('pages' => $career_pages, 'widgets' => $career_widgets);
