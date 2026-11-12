<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Film Studio", its crew calls: the staff page that adds
 * a crew call (the Jobs form, a collection, with the sample calls written
 * in when it is made), the page listing every call, the call's own page
 * with its apply link, the application, the application's own page with
 * its messages, My Applications and the staff's list, their e-mails and
 * the widgets that draw them. Included after parts.php, mail.php,
 * projects.php (its record page link) and enquiries.php (its record, list
 * and page builders) in the same scope; returns
 * array('pages' => ..., 'widgets' => ...).
 *
 * A crew call is a record of the Jobs form; department and type are pick
 * lists whose answers are the words parts.php gives. An application is a
 * record of the Applications form. The call's page opens it with the role
 * chosen (?job=, the call's title); an applicant without an account gets
 * one (auto-registration), so the application's page and My Applications
 * show it at once. The showreel or portfolio is an address drawn as a link
 * opening a new window, never an embed. Its Status is an office-use-only
 * field the studio changes in the control panel; the interview or the
 * trial day is written under it as a message (the page's comments),
 * mailed to the applicant and to the studio. CVs are kept in the
 * template's private folder.
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
$job_sample_key = 'assistant_editor';
$job_sample = $jobs[$job_sample_key];
$sample_reel = 'https://www.example.com/jane-cooper-reel';
$sample_letter = lang('I have cut short films and music videos for two years and assisted on a feature edit; I would like to learn how a studio delivers commercials.');
$experience_choices = array(lang('Starting out'), lang('Up to two years'), lang('Three to five years'), lang('More than five years'));

// What every crew member at the studio has, beside the calls.
$job_benefits = array(
    lang('Pay agreed before every production, paid on time'),
    lang('Time on our cameras and in our suites to learn'),
    lang('Your name in the credits of every film you work on'),
    lang('A producer who plans the day so it ends on time'),
);

// ── Pages ───────────────────────────────────────────────────────────────

// Crew calls: every open call, why work with the studio, how it hires and
// the open application.
$career_pages[] = array(
    'key'              => 'careers',
    'name'             => lang('crew-calls'),
    'folder'           => 'public',
    'title'            => lang('Crew Calls'),
    'meta_description' => lang('Crew calls at the studio: production, camera and post-production roles, freelance days and internships.'),
    'tree'             => $page(array(
        $page_header(lang('Crew Calls'), lang('We hire for each production and keep a core crew all year. See the calls open now, or send an open application with your showreel.')),
        $el('section', 'py-5', lang('Open Calls'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $section_header(lang('Open now'), lang('Crew calls')),
                        $widget('jobs_list'),
                        $el('div', 'd-flex flex-wrap align-items-center gap-3 mt-4', lang('Open Application'), array(
                            $para(lang('No call for your role? Send your showreel; we look at every open application before the next production.'), 'text-body-secondary mb-0'),
                            $link(lang('Send an open application'), '{{page:application}}?' . $query(array('job' => lang('Open application'))), 'btn btn-outline-primary px-4'),
                        )),
                    ), '', '8'),
                    $col(array(
                        $el('div', 'p-4 rounded-3 bg-body-tertiary mb-4', lang('Why Work With Us'), array(
                            $heading('h2', lang('Why work with us'), 'pfm-display h5 mb-3'),
                            $check_list($job_benefits, 'list-unstyled small mb-0'),
                        )),
                        $el('div', 'p-4 rounded-3 border', lang('How We Hire'), array(
                            $heading('h2', lang('How we hire'), 'pfm-display h5 mb-3'),
                            $el('ol', 'list-unstyled mb-0', lang('Steps'), array(
                                $step(1, lang('Your application'), lang('Your CV and your showreel or portfolio.')),
                                $step(2, lang('A conversation'), lang('A call or a visit to the studio with the head of the team.')),
                                $step(3, lang('A day with us'), lang('A paid trial day on a shoot or in the edit.')),
                            )),
                        )),
                    ), '', '4'),
                ), '4', '', 'g-lg-5'),
            )),
        ), $searchable('fm-open-calls', lang('jobs, vacancies, crew, freelance, internship, runner, editor, camera assistant'))),
    )),
);

// One crew call: its record.
$career_pages[] = array(
    'key'              => 'job',
    'name'             => lang('crew-call'),
    'folder'           => 'public',
    'title'            => lang('Crew Call'),
    'meta_description' => lang('A crew call at the studio: the role, what you will do, where and how to apply.'),
    'sitemap'          => false,
    'tree'             => $page(array(
        $widget('job_detail'),
    )),
);

// The staff page that adds a crew call. The form is the Jobs collection;
// the sample calls are written in when the form is made.
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
    'name'    => lang('new-crew-call'),
    'folder'  => 'private',
    'title'   => lang('New Crew Call'),
    'search'  => false,
    'sitemap' => false,
    'noindex' => true,
    'form'    => array(
        'form_name'            => lang('Crew Calls'),
        'collection'           => 1,
        'confirmation_message' => lang('The crew call is published.'),
        'confirmation_page_id' => '{{tab:careers}}',
        'sample_records'       => $sample_jobs,
    ),
    'tree'    => $staff_page(array(
        $page_header(lang('New Crew Call'), lang('Add the role with its duties; it appears on the crew calls page as soon as you send it. Take it down from its Edit button when it is filled.')),
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

// The application and its record pages, built like the requests
// (enquiries.php): the form page, the record's page with its messages, My
// Applications and the staff's list, and the two e-mails.
$application_request = array(
    'key'   => 'application',
    'view'  => 'application_view',
    'mine'  => 'my_applications',
    'staff' => 'staff_applications',
    'mail'  => 'application',
    'slug'  => lang('apply'),
    'view_slug'          => lang('application'),
    'mine_slug'          => lang('my-applications'),
    'staff_slug'         => lang('all-applications'),
    'mail_new_slug'      => lang('email-new-application'),
    'mail_received_slug' => lang('email-application-received'),
    'title' => lang('Apply to Join the Crew'),
    'meta'  => lang('Apply for a crew call at the studio: your CV, your showreel or portfolio and a few words about the films you have worked on.'),
    'lead'  => lang('Your CV, your showreel or portfolio and a few words about the films you have worked on. We answer every application within two weeks.'),
    'aside' => $fm_form_aside(array(
        lang('Your application opens its own page, and an account for you if you have none.'),
        lang('The head of the team watches your showreel and reads your CV.'),
        lang('Within two weeks we write to you: a call, a visit to the studio or a paid trial day.'),
        lang('A newer reel or a question? Write under your application.'),
    ), lang('Your application'), array(
        lang('Only the studio sees your CV and your showreel.'),
        lang('We answer every application, yes or no.'),
        lang('An open application is kept for the next production.'),
    )),
    'form_name'       => lang('Job Applications'),
    'confirmation'    => lang('Thank you, your application has reached us. We answer every application within two weeks.'),
    'notify_subject'  => lang('New application: ^^job^^'),
    'confirm_subject' => lang('We received your application'),
    'view_title'      => lang('Job Application'),
    'message_subject' => lang('There is a new message about your application'),
    'mine_title'      => lang('My Applications'),
    'mine_lead'       => lang('Your applications, their status and our messages, newest first.'),
    'more_title'      => lang('Another role?'),
    'more_text'       => lang('Apply for another crew call or send an open application; your details are filled in for you.'),
    'staff_lead'      => lang('Every application, newest first: watch the showreel, read the CV, set the status and write to the applicant under each.'),
    'mail_new_title'      => lang('New Application E-mail'),
    'mail_received_title' => lang('Application Received E-mail'),

    'missing'       => lang('This application could not be found, or it is not yours to see.'),
    'missing_icon'  => 'bi-file-earmark-x',
    'missing_title' => lang('Application not found'),
    'sent'          => lang('Thank you, your application has reached us. We answer every application within two weeks; this page shows its status, and you can write to us below.'),
    'title_sample'  => $job_sample['title'],
    'title_field'   => 'job',
    'title_label'   => lang('Role'),
    'status_sample' => $application_statuses[0],
    'details'       => array_merge(
        $detail(lang('Applicant'), '', lang('Applicant'), $fm_sender_name(), true),
        $detail(lang('Experience'), '', lang('Experience'), $bound($experience_choices[1], 'experience')),
        $detail(lang('Showreel or Portfolio'), 'text-break', lang('Showreel or Portfolio'), array(
            $link($sample_reel, '#', '', array(), array('target' => '_blank', 'rel' => 'noopener',
                '_bindings' => array('href' => 'showreel', 'text' => 'showreel'))),
        ), true),
        $detail(lang('CV'), 'text-break', lang('CV'), array(
            $link('cv.pdf', '#', '', array(), array('target' => '_blank', 'rel' => 'noopener',
                '_bindings' => array('href' => 'cv', 'text' => 'cv'))),
        ), true),
        $detail(lang('Sent'), '', lang('Sent'), $bound(lang('Date'), 'submitted_date_and_time')),
        $detail(lang('Reference Code'), 'mb-0 font-monospace', lang('Reference Code'), $bound('ABC123', 'reference_code'))
    ),
    'words_label'    => lang('A few words about you'),
    'words'          => $sample_letter,
    'words_field'    => 'cover_letter',
    'note'           => lang('A newer reel or a question? Write to us below; we answer here and by e-mail.'),
    'row_items'      => array(
        array('bi-calendar3', lang('Sent'), lang('Date'), 'submitted_date_and_time'),
        array('bi-award', lang('Experience'), $experience_choices[1], 'experience'),
    ),
    'mine_empty'   => lang('No applications yet. See the crew calls open now, or send an open application.'),
    'staff_empty'  => lang('No applications yet.'),
    'search'       => array('first_name', 'last_name', 'email', 'job', 'status'),
    'search_label' => lang('Search by name, e-mail, role or status...'),
);
$career_pages = array_merge($career_pages, $fm_request_pages($application_request), $fm_request_mail_pages($application_request));

// ── Widgets ─────────────────────────────────────────────────────────────

$job_missing = lang('This crew call could not be found. It may have been filled and taken down.');
$application_mail_missing = lang('This application was not found.');
$application_open = '{{page:application_view}}?r=^^reference_code^^';

// The lines of an application, for its e-mails; the studio's adds the
// applicant's contact, the showreel and the words.
$mail_application = function ($staff) use ($mail_box, $mail_line, $span, $link, $bind, $fm_sender_name, $job_sample, $experience_choices, $sample_reel, $sample_letter) {
    $lines = array(
        $mail_line(lang('Role'), array($span($job_sample['title'], '', lang('Role'), $bind('job')))),
    );
    if ($staff) {
        $lines[] = $mail_line(lang('Applicant'), $fm_sender_name());
        $lines[] = $mail_line(lang('Email'), array($span('jane.cooper@example.com', '', lang('Email'), $bind('email'))));
        $lines[] = $mail_line(lang('Phone'), array($span('+90 555 000 00 00', '', lang('Phone'), $bind('phone'))));
        $lines[] = $mail_line(lang('Experience'), array($span($experience_choices[1], '', lang('Experience'), $bind('experience'))));
        $lines[] = $mail_line(lang('Showreel or Portfolio'), array(
            $link($sample_reel, '#', '', array(), array('target' => '_blank', 'rel' => 'noopener',
                '_bindings' => array('href' => 'showreel', 'text' => 'showreel'))),
        ));
        $lines[] = $mail_line(lang('A few words about you'), array($span($sample_letter, '', lang('Text'), $bind('cover_letter'))));
    }
    return $mail_box($lines, lang('Job Application'));
};

$career_widgets = array(

    // The staff's Jobs form: one crew call.
    'job_form' => array(
        'page'   => 'job_new',
        'slug'   => 'form',
        'config' => array('regionType' => 'custom_form', 'form_source' => 'page'),
        'tree'   => $widget_root(array(
            $form_step(1, lang('The role')),
            $field('fm-job-title', lang('Role'), pg_cf_with_role($input('fm-job-title', 'text', 'title', 'off'), 'title')),
            $row(array(
                $col(array($field('fm-job-department', lang('Department'), pg_cf_with_role($select('fm-job-department', 'department', lang('Choose a department…'), $department_titles), 'category'))), '6'),
                $col(array($field('fm-job-type', lang('Job Type'), $select('fm-job-type', 'type', '', $job_type_titles, true, $job_type_titles[0]))), '6'),
            ), '3'),
            $field('fm-job-location', lang('Location'), $input('fm-job-location', 'text', 'location', 'off'), 'mb-3',
                lang('Where the work is, for example The studio and locations.')),
            $form_step(2, lang('Words')),
            $field('fm-job-summary', lang('Summary'), pg_cf_with_role($textarea('fm-job-summary', 'summary', 2), 'description'), 'mb-3',
                lang('One or two lines for the crew calls page.')),
            $field('fm-job-duties', lang('Duties'), $textarea('fm-job-duties', 'duties', 5), 'mb-3', lang('One duty on each line.')),
            $submit(lang('Publish')),
            $loop(array()),
        )),
    ),

    // Every crew call, A to Z.
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
            'empty_message'       => lang('No crew calls are open just now. Send an open application: we keep it for the next production.'),
        ),
        'tree'   => $widget_root(array($loop(array($job_card($job_sample_key))))),
    ),

    // One crew call: the department and type, the role, the location, the
    // summary, the duties, what the studio offers and the way to apply for
    // this role (?job=).
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
                    $icon('bi-film', 'display-5 text-body-tertiary'),
                    $heading('h2', lang('Crew call not found'), 'pfm-display h3 mt-3'),
                    $para($job_missing, 'text-body-secondary mb-4', $bind('not_found')),
                    $link(lang('See the crew calls'), '{{page:careers}}', 'btn btn-primary px-4'),
                ), $show_when('record_not_found')),
                $el('article', '', lang('Crew Call'), array(
                    $el('div', 'd-flex flex-wrap align-items-center justify-content-between gap-2 mb-3', lang('Toolbar'), array(
                        $el('a', 'd-inline-flex align-items-center gap-1 small fw-semibold link-primary text-decoration-none', lang('Link'), array(
                            $icon('bi-arrow-left'),
                            $span(lang('All Crew Calls'), '', lang('Link Text')),
                        ), array('href' => '{{page:careers}}')),
                        $edit_button(),
                    )),
                    $span($departments[$job_sample['department']], 'pfm-eyebrow small text-uppercase fw-semibold text-primary', lang('Department'), $bind('department')),
                    $heading('h1', $job_sample['title'], 'display-6 mt-1 mb-3', $bind('title')),
                    $el('ul', 'list-unstyled d-flex flex-wrap gap-3 fw-semibold mb-3', lang('Job Facts'), array(
                        $el('li', 'd-inline-flex align-items-center gap-1', lang('Job Type'), array(
                            $icon('bi-briefcase', 'text-primary'),
                            $span($job_types[$job_sample['type']], '', lang('Job Type'), $bind('type')),
                        )),
                        $el('li', 'd-inline-flex align-items-center gap-1', lang('Location'), array(
                            $icon('bi-geo-alt', 'text-primary'),
                            $span($job_sample['location'], '', lang('Location'), $bind('location')),
                        )),
                    )),
                    $para($job_sample['summary'], 'lead text-body-secondary mb-4', $bind('summary')),
                    $row(array(
                        $col(array(
                            $el('div', 'pg-hide-if-empty mb-4', lang('Duties'), array(
                                $heading('h2', lang('What you will do'), 'pfm-display h4 mb-2'),
                                $el('div', 'pg-field-value lh-lg', lang('Value'), array(), $bound($duties_text($job_sample_key), 'duties')),
                            )),
                            $heading('h2', lang('What we offer'), 'pfm-display h4 mb-2'),
                            $check_list($job_benefits, 'list-unstyled mb-4'),
                        ), '', '8'),
                        $col(array(
                            $el('aside', 'card border-0 text-bg-dark rounded-3', lang('Apply Card'), array(
                                $el('div', 'card-body p-4', lang('Card Body'), array(
                                    $heading('h2', lang('Want this role?'), 'pfm-display h5 mb-2'),
                                    $para(lang('Send your CV with your showreel or portfolio; we answer every application within two weeks.'), 'small mb-3'),
                                    // Opens the application with this role
                                    // chosen (?job=).
                                    $link(lang('Apply for this role'), '#', 'btn btn-primary w-100', array(),
                                        $fm_page_link('application', array('job' => 'title'))),
                                )),
                            )),
                        ), '', '4'),
                    ), '4', '', 'g-lg-5'),
                ), $show_when('record_found')),
            ), 'py-5'),
        )),
    ),

    // The application: the fields are the page's form. The role starts
    // with what a link sends (?job=). The Status is the studio's (office
    // use only): every application starts as Application received.
    'application_form' => array(
        'page'   => 'application',
        'slug'   => 'form',
        'config' => array('regionType' => 'custom_form', 'form_source' => 'page'),
        'tree'   => $widget_root(array(
            $form_step(1, lang('The role')),
            $row(array(
                $col(array($field('fm-app-job', lang('Role'), $select('fm-app-job', 'job', lang('Choose a role…'), $application_job_choices, true, '', array('url_param' => 'job')))), '6'),
                $col(array($field('fm-app-experience', lang('Experience'), $select('fm-app-experience', 'experience', lang('Choose…'), $experience_choices))), '6'),
            ), '3'),
            $field('fm-app-showreel', lang('Showreel or Portfolio'), $input('fm-app-showreel', 'url', 'showreel', 'url', false), 'mb-3',
                lang('The address of your reel or portfolio on your video host or website.')),
            $field('fm-app-cv', lang('CV'), $file('fm-app-cv', 'cv', 'private', '.pdf,.doc,.docx,.odt'), 'mb-3', lang('PDF or Word.')),
            $field('fm-app-letter', lang('A few words about you'), $textarea('fm-app-letter', 'cover_letter', 5), 'mb-3',
                lang('The films you have worked on, your role on them, your kit or software and when you could start.')),
            $form_step(2, lang('How to reach you')),
            $row(array(
                $col(array($field('fm-app-first-name', lang('First Name'), $input('fm-app-first-name', 'text', 'first_name', 'given-name', true, array('contact_field' => 'first_name')))), '6'),
                $col(array($field('fm-app-last-name', lang('Last Name'), $input('fm-app-last-name', 'text', 'last_name', 'family-name', true, array('contact_field' => 'last_name')))), '6'),
                $col(array($field('fm-app-email', lang('Email'), $input('fm-app-email', 'email', 'email', 'email', true, array('contact_field' => 'email_address')))), '6'),
                $col(array($field('fm-app-phone', lang('Phone'), $input('fm-app-phone', 'tel', 'phone', 'tel', true, array('contact_field' => 'mobile_phone')))), '6'),
            ), '3'),
            $field('fm-app-status', lang('Status'), $select('fm-app-status', 'status', '', $application_statuses, false, $application_statuses[0], array('office_use_only' => true))),
            $agree('fm-app-consent', 'consent', lang('I agree that the studio keeps my application, CV and showreel address to consider it, as its privacy notice explains.'), true),
            $captcha(),
            $submit(lang('Send Application'), 'btn btn-primary btn-lg px-4'),
            $loop(array()),
        )),
    ),

    // One application: what was sent and its status, for the applicant and
    // the staff; the messages follow as comments.
    'application_detail' => $fm_detail_widget($application_request, 'application_view', $application_request['missing'], 'submitter_only') + array(
        'tree' => $fm_record_tree($application_request),
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
            'empty'     => lang('No messages yet. The day of your interview or trial comes here; you can write to us about your application too.'),
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

    'my_applications_list'    => $fm_request_list($application_request, false),
    'staff_applications_list' => $fm_request_list($application_request, true),

    // The e-mail pages' widgets: drawn for the submission just sent.
    'email_application_admin' => $fm_detail_widget($application_request, 'email_application_new', $application_mail_missing, 'public') + array(
        'tree' => $widget_root(array(
            $mail_heading(lang('A new application')),
            $mail_para(lang('It came from the crew calls page. Watch the showreel and read the CV, then set the status on the application\'s page; what you write under it reaches the applicant by e-mail.')),
            $mail_application(true),
            $mail_button(lang('Open the application'), $application_open),
            $mail_reference(),
        )),
    ),
    'email_application_client' => $fm_detail_widget($application_request, 'email_application_received', $application_mail_missing, 'public') + array(
        'tree' => $widget_root(array(
            $mail_heading(lang('We received your application')),
            $mail_greeting(),
            $mail_para(lang('Thank you for wanting to make films with us. We watch every showreel and answer within two weeks, on the application\'s page and by e-mail.')),
            $mail_application(false),
            $mail_button(lang('Open my application'), $application_open),
            $mail_new_account(),
        )),
    ),
);

return array('pages' => $career_pages, 'widgets' => $career_widgets);
