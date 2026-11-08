<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Recruitment Agency", its employer enquiries: the staff
 * request form, the request's own page with its messages, the employer's
 * own list and the staff's list, the staff request e-mails, and the
 * widgets that draw them. Included after parts.php, mail.php, jobs.php
 * and applications.php in the same scope (it builds with the record
 * pieces of applications.php); returns array('pages' => ...,
 * 'widgets' => ...).
 *
 * A staff request is a record of the Staff Requests form, never a vacancy
 * the employer publishes: an employer without an account gets one
 * (auto-registration), so the request's page and My Staff Requests show
 * it at once. The sector starts with what a link sends (?sector=: the
 * sector's title, as the sector pages send it). Its Status is an
 * office-use-only field the agency sets in the control panel; the
 * consultant writes under it (the page's comments), mailed to the
 * employer and to the agency. The job description is kept in the
 * template's private folder: the record's page links it for whoever may
 * read the record.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

$enquiry_pages = array();

// The sample staff request the canvas shows: words, never a date.
$enq_sample_role = $jobs['software_developer']['title'];
$enq_sample_company = lang('Bluewave Software Ltd');
$enq_sample_sector = $sectors['technology']['title'];
$enq_sample_city = $cities[$jobs['software_developer']['city']]['title'];
$enq_sample_service = $enquiry_service_choices[0];
$enq_sample_contract = $contract_titles[0];
$enq_sample_headcount = $headcount_choices[1];
$enq_sample_start = $start_choices[1];
$enq_sample_details = lang('We are growing our payments team and need two developers who have built web services, can work with our product owners and want to stay with us for years.');

// ── Staff request pieces ────────────────────────────────────────────────

$enquiry_row = function ($staff) use ($rc_record_row, $enq_sample_role, $enq_sample_company, $enq_sample_sector, $enq_sample_city, $enq_sample_headcount, $enquiry_statuses) {
    $items = array(
        array('bi-calendar3', lang('Sent'), lang('Date'), 'submitted_date_and_time'),
        array('bi-diagram-3', lang('Sector'), $enq_sample_sector, 'sector'),
        array('bi-geo-alt', lang('City'), $enq_sample_city, 'city'),
        array('bi-people', lang('How Many'), $enq_sample_headcount, 'headcount'),
    );
    if ($staff) $items[] = array('bi-building', lang('Company'), $enq_sample_company, 'company');
    return $rc_record_row(lang('Role'), $enq_sample_role, 'role', $enquiry_statuses[0], $items, $staff, lang('Employer'));
};

// The lines of a staff request, for its e-mails; the staff's adds the
// employer's contact and the details.
$mail_enquiry = function ($staff) use ($mail_box, $mail_line, $span, $bind, $person_name, $enq_sample_role, $enq_sample_company, $enq_sample_sector, $enq_sample_city, $enq_sample_service, $enq_sample_contract, $enq_sample_headcount, $enq_sample_start, $enq_sample_details) {
    $lines = array(
        $mail_line(lang('Role'), array($span($enq_sample_role, '', lang('Role'), $bind('role')))),
        $mail_line(lang('Company'), array($span($enq_sample_company, '', lang('Company'), $bind('company')))),
        $mail_line(lang('Sector'), array($span($enq_sample_sector, '', lang('Sector'), $bind('sector')))),
        $mail_line(lang('City'), array($span($enq_sample_city, '', lang('City'), $bind('city')))),
        $mail_line(lang('Service'), array($span($enq_sample_service, '', lang('Service'), $bind('service')))),
        $mail_line(lang('Contract Type'), array($span($enq_sample_contract, '', lang('Contract Type'), $bind('contract_type')))),
        $mail_line(lang('How Many'), array($span($enq_sample_headcount, '', lang('How Many'), $bind('headcount')))),
        $mail_line(lang('Start'), array($span($enq_sample_start, '', lang('Start'), $bind('start')))),
    );
    if ($staff) {
        $lines[] = $mail_line(lang('Contact'), $person_name());
        $lines[] = $mail_line(lang('Email'), array($span('jane.cooper@example.com', '', lang('Email'), $bind('email'))));
        $lines[] = $mail_line(lang('Phone'), array($span('+90 555 000 00 00', '', lang('Phone'), $bind('phone'))));
        $lines[] = $mail_line(lang('About the Role'), array($span($enq_sample_details, '', lang('About the Role'), $bind('details'))));
    }
    return $mail_box($lines, lang('Staff Request'));
};

// ── Pages ───────────────────────────────────────────────────────────────

// The staff request. A sector's page opens it with the sector chosen
// (?sector=). What is sent opens the request's own page.
$enquiry_pages[] = array(
    'key'              => 'hire',
    'name'             => lang('hire-staff'),
    'folder'           => 'public',
    'title'            => lang('Hire Staff'),
    'meta_description' => lang('Tell us the role you need to fill: a consultant who knows your sector calls you and sends a shortlist in five working days.'),
    'form'             => array(
        'form_name'            => lang('Staff Requests'),
        'confirmation_message' => lang('Thank you, your staff request has reached us. A consultant calls you within one working day.'),
        'confirmation_page_id' => '{{tab:enquiry_view}}',
        'auto_registration'    => 1,
        'notify_email'         => '{{site_email}}',
        'notify_subject'       => lang('New staff request: ^^role^^'),
        'notify_page_id'       => '{{tab:email_enquiry_new}}',
        'confirm_email'        => 1,
        'confirm_subject'      => lang('We received your staff request'),
        'confirm_page_id'      => '{{tab:email_enquiry_received}}',
    ),
    'tree'             => $rc_ask_page(lang('Hire Staff'),
        lang('Tell us the role, the team and when you need someone. A consultant who knows your sector calls you, then sends you a shortlist of checked candidates.'),
        'enquiry_form',
        $rc_form_aside(lang('What happens next'), array(
            lang('Your staff request opens its own page, and an account for you if you have none.'),
            lang('A consultant who knows your sector calls you within one working day to learn about the role and the team.'),
            lang('Within five working days you receive a shortlist of three to five checked candidates.'),
            lang('We arrange the interviews and the offer; every answer also comes under your staff request and by e-mail.'),
        ), lang('Many roles to fill, or a whole new team? Call us and a consultant plans it with you.'))),
);

// One staff request: what was sent, its status and the messages under it.
$enquiry_pages[] = array(
    'key'      => 'enquiry_view',
    'name'     => lang('staff-request'),
    'folder'   => 'registration',
    'title'    => lang('Staff Request'),
    'search'   => false,
    'sitemap'  => false,
    'comments' => $rc_messages_settings(lang('There is a new message about your staff request')),
    'tree'     => $page(array(
        $widget('enquiry_detail'),
        $comments_section(lang('Messages'), 'enquiry_messages'),
    ), false),
);

// My Staff Requests: the employer's own requests (for the staff,
// everybody's).
$enquiry_pages[] = array(
    'key'     => 'my_enquiries',
    'name'    => lang('my-staff-requests'),
    'folder'  => 'registration',
    'title'   => lang('My Staff Requests'),
    'search'  => false,
    'sitemap' => false,
    'tree'    => $rc_my_page(lang('My Staff Requests'), lang('Your staff requests, their status and your consultant\'s messages, newest first.'),
        'my_enquiries_list', lang('Another role to fill?'),
        lang('Send a new staff request; your details are filled in for you.'), lang('Hire Staff'), 'hire'),
);

// The staff's list of every staff request, newest first, with a search.
$enquiry_pages[] = array(
    'key'     => 'staff_enquiries',
    'name'    => lang('all-staff-requests'),
    'folder'  => 'private',
    'title'   => lang('Staff Requests'),
    'search'  => false,
    'sitemap' => false,
    'tree'    => $rc_staff_list_page(lang('Staff Requests'),
        lang('Every staff request, newest first: call the employer, set the status and write to them under each.'), 'staff_enquiries_list'),
);

// The e-mails: what the agency receives and the reply to the employer.
$enquiry_pages[] = array_merge($mail_settings, array(
    'key'   => 'email_enquiry_new',
    'name'  => lang('email-new-staff-request'),
    'title' => lang('New Staff Request E-mail'),
    'tree'  => $mail_page(array($widget('email_enquiry_admin'))),
));
$enquiry_pages[] = array_merge($mail_settings, array(
    'key'   => 'email_enquiry_received',
    'name'  => lang('email-staff-request-received'),
    'title' => lang('Staff Request Received E-mail'),
    'tree'  => $mail_page(array($widget('email_enquiry_client'))),
));

// ── Widgets ─────────────────────────────────────────────────────────────

$enquiry_missing = lang('This staff request could not be found, or it is not yours to see.');

$enquiry_widgets = array(

    // The staff request: the fields are the page's form. The sector starts
    // with what a link sends (?sector=). The Status is the agency's
    // (office use only): every request starts as received.
    'enquiry_form' => array(
        'page'   => 'hire',
        'slug'   => 'form',
        'config' => array('regionType' => 'custom_form', 'form_source' => 'page'),
        'tree'   => $widget_root(array(
            $form_step(1, lang('The role')),
            $field('rc-enq-role', lang('Role'), $input('rc-enq-role', 'text', 'role', '', true), 'mb-3',
                lang('The job title, as you would put it in the advert.')),
            $row(array(
                $col(array($field('rc-enq-sector', lang('Sector'), $select('rc-enq-sector', 'sector', lang('Choose a sector…'), $enquiry_sector_choices, true, '', array('url_param' => 'sector')))), '6'),
                $col(array($field('rc-enq-city', lang('City'), $select('rc-enq-city', 'city', lang('Choose a city…'), $application_city_choices))), '6'),
                $col(array($field('rc-enq-service', lang('Service'), $select('rc-enq-service', 'service', lang('Choose a service…'), $enquiry_service_choices))), '6'),
                $col(array($field('rc-enq-contract', lang('Contract Type'), $select('rc-enq-contract', 'contract_type', lang('Choose…'), $contract_titles))), '6'),
                $col(array($field('rc-enq-headcount', lang('How Many'), $select('rc-enq-headcount', 'headcount', lang('Choose…'), $headcount_choices))), '6'),
                $col(array($field('rc-enq-start', lang('Start'), $select('rc-enq-start', 'start', lang('Choose…'), $start_choices))), '6'),
            ), '3'),
            $field('rc-enq-details', lang('About the Role'), $textarea('rc-enq-details', 'details', 5, false), 'mb-3',
                lang('The team, the main duties and what the right person brings.')),
            $field('rc-enq-description', lang('Job Description'), $file('rc-enq-description', 'job_description', 'private'), 'mb-3',
                lang('If you have one: PDF, Word or a photo. Only our consultants see it.')),
            $form_step(2, lang('About you')),
            $field('rc-enq-company', lang('Company'), $input('rc-enq-company', 'text', 'company', 'organization', true, array('contact_field' => 'company'))),
            $rc_contact_fields('rc-enq'),
            $field('rc-enq-status', lang('Status'), $select('rc-enq-status', 'status', '', $enquiry_statuses, false, $enquiry_statuses[0], array('office_use_only' => true))),
            $agree('rc-enq-consent', 'consent', lang('I agree that the agency keeps my staff request and contact details to find the people I need, as its privacy notice explains.'), true),
            $captcha(),
            $el('div', 'd-flex flex-wrap align-items-center gap-3', lang('Send Area'), array(
                $submit(lang('Send Staff Request'), 'btn btn-primary btn-lg px-4'),
                $para(lang('No fee until a candidate starts.'), 'small text-body-secondary mb-0'),
            )),
            $loop(array()),
        )),
    ),

    // One staff request: what the employer sent and its status; the
    // consultant's messages follow as comments.
    'enquiry_detail' => $rc_detail_widget('enquiry_view', 'hire', $enquiry_missing, 'submitter_only') + array(
        'tree' => $rc_record_tree(array(
            'missing_icon'  => 'bi-file-earmark-x',
            'missing_title' => lang('Staff request not found'),
            'missing'       => $enquiry_missing,
            'back'          => lang('My Staff Requests'),
            'back_key'      => 'my_enquiries',
            'thanks'        => lang('Thank you, your staff request has reached us. A consultant calls you within one working day; this page shows its status, and you can write to us below.'),
            'card'          => lang('Staff Request Card'),
            'eyebrow'       => lang('Staff Request'),
            'title'         => $enq_sample_role,
            'title_field'   => 'role',
            'status'        => $enquiry_statuses[0],
            'details'       => array_merge(
                $detail(lang('Company'), '', lang('Company'), $bound($enq_sample_company, 'company')),
                $detail(lang('Contact'), '', lang('Contact'), $person_name(), true),
                $detail(lang('Sector'), '', lang('Sector'), $bound($enq_sample_sector, 'sector')),
                $detail(lang('City'), '', lang('City'), $bound($enq_sample_city, 'city')),
                $detail(lang('Service'), '', lang('Service'), $bound($enq_sample_service, 'service')),
                $detail(lang('Contract Type'), '', lang('Contract Type'), $bound($enq_sample_contract, 'contract_type')),
                $detail(lang('How Many'), '', lang('How Many'), $bound($enq_sample_headcount, 'headcount')),
                $detail(lang('Start'), '', lang('Start'), $bound($enq_sample_start, 'start')),
                $detail(lang('Sent'), '', lang('Sent'), $bound(lang('Date'), 'submitted_date_and_time')),
                $detail(lang('Job Description'), 'text-break', lang('Job Description'), array(
                    $link('job-description.pdf', '#', '', array(), array('target' => '_blank', 'rel' => 'noopener',
                        '_bindings' => array('href' => 'job_description', 'text' => 'job_description'))),
                ), true),
                $detail(lang('Reference Code'), 'mb-0 font-monospace', lang('Reference Code'), $bound('ABC123', 'reference_code'))
            ),
            'words_label'   => lang('About the Role'),
            'words'         => $enq_sample_details,
            'words_field'   => 'details',
            'note'          => lang('A change to the role or a question for your consultant? Write to us below; we answer here and by e-mail.'),
        )),
    ),

    'enquiry_messages' => $rc_messages_widget('enquiry_view',
        lang('No messages yet. Your consultant writes here about the shortlist and the interviews; you can write to us about your staff request too.')),

    'my_enquiries_list' => $rc_record_list('my_enquiries', 'hire', 'enquiry_view', false,
        lang('No staff requests yet. Tell us the role you need to fill and a consultant calls you.'),
        array(), '', $enquiry_row(false)),

    'staff_enquiries_list' => $rc_record_list('staff_enquiries', 'hire', 'enquiry_view', true, lang('No staff requests yet.'),
        array('company', 'first_name', 'last_name', 'email', 'role', 'sector', 'city', 'status'),
        lang('Search by company, contact, role, sector, city or status...'), $enquiry_row(true)),

    // The e-mail pages' widgets: drawn for the submission just sent.
    'email_enquiry_admin' => $rc_detail_widget('email_enquiry_new', 'hire', lang('This staff request was not found.'), 'public') + array(
        'tree' => $widget_root(array(
            $mail_heading(lang('A new staff request')),
            $mail_para(lang('It came from the website. Call the employer, then set the status on the request\'s page; what you write under it reaches the employer by e-mail.')),
            $mail_enquiry(true),
            $mail_button(lang('Open the staff request'), '{{page:enquiry_view}}?r=^^reference_code^^'),
            $mail_reference(),
        )),
    ),
    'email_enquiry_client' => $rc_detail_widget('email_enquiry_received', 'hire', lang('This staff request was not found.'), 'public') + array(
        'tree' => $widget_root(array(
            $mail_heading(lang('We received your staff request')),
            $mail_greeting(),
            $mail_para(lang('Thank you. A consultant who knows your sector calls you within one working day; every answer comes on the request\'s page and by e-mail.')),
            $mail_enquiry(false),
            $mail_button(lang('Open my staff request'), '{{page:enquiry_view}}?r=^^reference_code^^'),
            $mail_new_account(),
        )),
    ),
);

return array('pages' => $enquiry_pages, 'widgets' => $enquiry_widgets);
