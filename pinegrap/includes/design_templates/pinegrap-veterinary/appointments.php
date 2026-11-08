<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Veterinary", its requests: the appointment request, the
 * request's own page with its messages, My Appointments and the staff's
 * list; the health plan sign-up request, its page and the staff's list;
 * the privacy page the consent boxes link; the e-mails of both forms and
 * the new message e-mail every thread of the site sends, and the widgets
 * that draw them. Included after parts.php and mail.php in the same scope;
 * returns array('pages' => ..., 'widgets' => ...).
 *
 * An appointment request is a request, never a held time: a record of the
 * Appointments form. The reception answers each one and sets its
 * office-use-only Status (Appointment confirmed, Visit completed,
 * Cancelled); its answers are the page's comments, mailed to the owner and
 * to the practice. A visitor without an account gets one
 * (auto-registration), so the request's page and My Appointments show it
 * at once. A free time booked at once is the booking widget's
 * (book_online), which the request page links.
 *
 * The service, the vet and the pet start with what a link sends
 * (?service=, ?vet=, ?species=: the words of the pick lists, as parts.php
 * and the vet page write them); the sign-up's plan with ?plan= (a plan's
 * title). A sign-up starts no payment and no membership: the reception
 * starts the plan with the owner.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

$appointment_pages = array();

// The sample requests the canvas shows. The date is words, never a date:
// a fixed day would read as a past week a year later.
$sample_pet = lang('Pamuk');
$sample_species = $species['cat']['title'];
$sample_service = $services['dental']['title'];
$sample_vet = $vets['can']['name'];
$sample_date = lang('Preferred date');
$sample_time = $time_choices[0];
$sample_notes = lang('Pamuk has eaten less for a week and chews on one side only. She is nervous in the car, so a quiet morning time would help.');
$sample_plan = $plans['adult']['title'];
$sample_plan_notes = lang('Our dog Bruno is four and fit; we would like his booster and two check-ups a year in one plan.');
$emergency_tel = 'tel:' . preg_replace('/[^0-9+]/', '', $practice['emergency']);

// ── Page shells ─────────────────────────────────────────────────────────

// The side column of a request page: what happens next, then the
// practice's hours and a link.
$request_aside = function ($steps, $link_text, $link_href) use ($el, $heading, $link, $check_list, $opening_hours) {
    $items = array();
    foreach ($steps as $n => $text) {
        $items[] = $el('li', $n < count($steps) - 1 ? 'mb-2' : '', lang('Step'), array(), array('text' => $text));
    }
    $hours = array();
    foreach ($opening_hours as $h) $hours[] = $h['days'] . ': ' . $h['hours'];
    return array(
        $el('div', 'p-4 rounded-4 bg-body-tertiary mb-4', lang('How It Works'), array(
            $heading('h2', lang('What happens next'), 'pvt-display h5 mb-3'),
            $el('ol', 'small text-body-secondary ps-3 mb-0', lang('List'), $items),
        )),
        $el('div', 'p-4 rounded-4 border', lang('Opening Hours'), array(
            $heading('h2', lang('Opening hours'), 'pvt-display h5 mb-3'),
            $check_list($hours, 'list-unstyled small mb-3'),
            $link($link_text, $link_href, 'fw-semibold link-primary text-decoration-none small'),
        )),
    );
};

// A form page: the header, the form in a card and the side column.
$request_page = function ($title, $lead, $widget_key, $aside) use ($page, $page_header, $el, $container, $row, $col, $widget) {
    return $page(array(
        $page_header($title, $lead),
        $el('section', 'py-5', lang('Content'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $el('div', 'card border-0 shadow-sm rounded-4', lang('Form Card'), array(
                            $el('div', 'card-body p-4 p-lg-5', lang('Card Body'), array($widget($widget_key))),
                        )),
                    ), '', '8'),
                    $col($aside, '', '4'),
                ), '4', '', 'g-lg-5'),
            )),
        )),
    ), false);
};

// A request's own page: the request and the messages under it.
$request_view = function ($key, $name, $title, $detail_key, $messages_key, $subject) use ($page, $widget, $comments_section) {
    return array(
        'key'      => $key,
        'name'     => $name,
        'folder'   => 'registration',
        'title'    => $title,
        'search'   => false,
        'sitemap'  => false,
        'comments' => array('label' => lang('Message'), 'allow_new' => true, 'auto_publish' => true, 'show_date' => true, 'login' => true,
                            'attachments' => false,
                            'email_page' => '{{tab:email_reply}}', 'email_subject' => $subject,
                            'notify_email' => '{{site_email}}'),
        'tree'     => $page(array(
            $widget($detail_key),
            $comments_section(lang('Messages'), $messages_key),
        ), false),
    );
};

// A staff list page.
$staff_list_page = function ($key, $name, $title, $lead, $widget_key) use ($staff_page, $page_header, $el, $container, $widget) {
    return array(
        'key'     => $key,
        'name'    => $name,
        'folder'  => 'private',
        'title'   => $title,
        'search'  => false,
        'sitemap' => false,
        'noindex' => true,
        'tree'    => $staff_page(array(
            $page_header($title, $lead),
            $el('section', 'py-5', lang('Content'), array(
                $container(array($widget($widget_key))),
            )),
        )),
    );
};

// ── Pages ───────────────────────────────────────────────────────────────

// The appointment request. What is sent opens the request's own page.
$appointment_pages[] = array(
    'key'              => 'appointment',
    'name'             => lang('book-an-appointment'),
    'folder'           => 'public',
    'title'            => lang('Book an Appointment'),
    'meta_description' => lang('Tell us about your pet, the visit you need and when suits you: the reception confirms the appointment, usually the same day.'),
    'form'             => array(
        'form_name'            => lang('Appointments'),
        'confirmation_message' => lang('Thank you, your appointment request has reached us. The reception confirms the time, usually the same day.'),
        'confirmation_page_id' => '{{tab:appointment_view}}',
        'auto_registration'    => 1,
        'notify_email'         => '{{site_email}}',
        'notify_subject'       => lang('New appointment request: ^^service^^'),
        'notify_page_id'       => '{{tab:email_appointment_new}}',
        'confirm_email'        => 1,
        'confirm_subject'      => lang('We received your appointment request'),
        'confirm_page_id'      => '{{tab:email_appointment_received}}',
    ),
    'tree'             => $request_page(lang('Book an Appointment'),
        lang('Tell us about your pet, the visit you need and when suits you. The reception confirms the time, usually the same day.'),
        'appointment_form',
        array_merge(array(
            // A free time booked at once: the booking widget's page.
            $el('div', 'p-4 rounded-4 bg-primary-subtle mb-4', lang('Book Online'), array(
                $heading('h2', lang('Pick a free time now'), 'pvt-display h5 mb-2'),
                $para(lang('Know the visit you need? Choose a vet and one of the free times, and the time is yours at once.'), 'small mb-3'),
                $link(lang('Pick a Free Time'), '{{page:book_online}}', 'btn btn-sm btn-primary rounded-pill px-3'),
            )),
            $el('div', 'p-4 rounded-4 bg-danger-subtle text-danger-emphasis mb-4', lang('Emergencies'), array(
                $heading('h2', lang('An emergency?'), 'pvt-display h5 mb-2'),
                $para(lang('Do not send a request: call the emergency line, day or night.'), 'small mb-3'),
                $link($practice['emergency'], $emergency_tel, 'btn btn-sm btn-danger rounded-pill px-3'),
            )),
        ), $request_aside(array(
            lang('Your request opens its own page, and an account for you if you have none.'),
            lang('The reception confirms the time there, usually the same day, or offers another one.'),
            lang('Bring the pet passport or vaccination card and any medicines your pet takes.'),
            lang('Cannot come? Write to us on the request\'s page so the time goes to another pet.'),
        ), lang('See our services'), '{{page:services}}'))),
);

// One appointment request: the request, its status and the messages.
$appointment_pages[] = $request_view('appointment_view', lang('appointment-request'), lang('Appointment Request'),
    'appointment_detail', 'appointment_messages', lang('There is a new message about your appointment'));

// My Appointments: the owner's own requests (for the staff, everybody's).
$appointment_pages[] = array(
    'key'     => 'my_appointments',
    'name'    => lang('my-appointments'),
    'folder'  => 'registration',
    'title'   => lang('My Appointments'),
    'search'  => false,
    'sitemap' => false,
    'tree'    => $page(array(
        $page_header(lang('My Appointments'), lang('Your appointment requests, their status and the reception\'s answers, newest first.')),
        $el('section', 'py-5', lang('Content'), array(
            $container(array(
                $row(array(
                    $col(array($widget('my_appointments_list')), '', '8'),
                    $col(array(
                        $el('div', 'p-4 rounded-4 bg-body-tertiary', lang('New Request'), array(
                            $el('p', 'd-flex align-items-center gap-2 fw-semibold mb-2', lang('Title'), array(
                                $icon('bi-calendar-plus', 'text-primary'),
                                $span(lang('Another visit?'), '', lang('Text')),
                            )),
                            $para(lang('Ask for an appointment or pick a free time; your details are filled in for you.'), 'small text-body-secondary mb-3'),
                            $el('div', 'd-flex flex-wrap gap-2', lang('Buttons'), array(
                                $link(lang('Book an Appointment'), '{{page:appointment}}', 'btn btn-sm btn-primary rounded-pill px-3'),
                                $link(lang('Pick a Free Time'), '{{page:book_online}}', 'btn btn-sm btn-outline-primary rounded-pill px-3'),
                            )),
                        )),
                    ), '', '4'),
                ), '4', '', 'g-lg-5'),
            )),
        )),
    ), false),
);

$appointment_pages[] = $staff_list_page('staff_appointments', lang('all-appointment-requests'), lang('Appointment Requests'),
    lang('Every appointment request, newest first: confirm the time, set the status and write to the owner under each.'),
    'staff_appointments_list');

// The health plan sign-up request: a plan, the pet and when to start.
// What is sent opens the request's own page.
$appointment_pages[] = array(
    'key'              => 'plan_join',
    'name'             => lang('join-a-health-plan'),
    'folder'           => 'public',
    'title'            => lang('Join a Health Plan'),
    'meta_description' => lang('Ask to join a pet health plan: the reception starts the plan with you at your pet\'s next visit. Nothing is paid now.'),
    'form'             => array(
        'form_name'            => lang('Health Plan Requests'),
        'confirmation_message' => lang('Thank you, your health plan request has reached us. The reception writes to you within a day to start the plan.'),
        'confirmation_page_id' => '{{tab:plan_join_view}}',
        'auto_registration'    => 1,
        'notify_email'         => '{{site_email}}',
        'notify_subject'       => lang('New health plan request: ^^plan^^'),
        'notify_page_id'       => '{{tab:email_plan_join_new}}',
        'confirm_email'        => 1,
        'confirm_subject'      => lang('We received your health plan request'),
        'confirm_page_id'      => '{{tab:email_plan_join_received}}',
    ),
    'tree'             => $request_page(lang('Join a Health Plan'),
        lang('Choose the plan that suits your pet. The reception starts it with you at the next visit; nothing is paid now.'),
        'plan_join_form',
        $request_aside(array(
            lang('Your request opens its own page, and an account for you if you have none.'),
            lang('The reception writes to you there within a day and books the plan\'s first check-up.'),
            lang('The plan starts at that visit, with a vet who examines your pet.'),
            lang('Nothing is paid now: the monthly payments start with the plan.'),
        ), lang('Compare the plans'), '{{page:health_plans}}')),
);

// One health plan request: the request, its status and the messages.
$appointment_pages[] = $request_view('plan_join_view', lang('health-plan-request'), lang('Health Plan Request'),
    'plan_join_detail', 'plan_join_messages', lang('There is a new message about your health plan request'));

$appointment_pages[] = $staff_list_page('staff_plan_joins', lang('all-health-plan-requests'), lang('Health Plan Requests'),
    lang('Every health plan request, newest first: start the plan, set the status and write to the owner under each.'),
    'staff_plan_joins_list');

// The privacy notice the consent boxes, the booking widget and the footer
// link to: sample text with a note to adapt it.
$appointment_pages[] = array(
    'key'              => 'privacy',
    'name'             => lang('privacy-policy'),
    'folder'           => 'public',
    'title'            => lang('Privacy Policy'),
    'meta_description' => lang('What the practice keeps about you and your pet when you ask for an appointment or a health plan, why, and what you can ask of us.'),
    'tree'             => $page(array(
        $page_header(lang('Privacy Policy'), lang('What the practice keeps about you and your pet when you ask for an appointment or a health plan, why, and what you can ask of us.')),
        $el('section', 'py-5', lang('Your Data'), array(
            $container(array($row(array($col(array(
                $el('div', 'alert alert-secondary d-flex gap-2 small', lang('Sample Text Note'), array(
                    $icon('bi-info-circle', 'mt-1'),
                    $span(lang('Sample text: adapt it to your company and to the data protection law that applies to you before you publish.'), '', lang('Text')),
                ), array('_attrs' => array($attr('role', 'note')))),
                $heading('h2', lang('Your data'), 'pvt-display h4 mb-3'),
                $para(lang('When you ask for an appointment or a health plan, book a free time, join the newsletter or open an account, we keep your name, your contact details, your pet\'s details and what you tell us about its health, to answer you, keep your appointment and treat your pet safely.'), 'mb-3'),
                $para(lang('We keep it as long as the law requires, never sell it and never give it to advertisers. You can see and change your account data from My Account, and ask us about the rest through the contact page.'), 'mb-0'),
            ), '', '8'))))),
        ), $searchable('vt-your-data', lang('privacy, personal data, KVKK, GDPR, delete my data'))),
    )),
);

// The e-mails: what the reception receives and the reply to the sender of
// each form, and the new message e-mail.
$appointment_pages[] = array_merge($mail_settings, array(
    'key'   => 'email_appointment_new',
    'name'  => lang('email-new-appointment-request'),
    'title' => lang('New Appointment Request E-mail'),
    'tree'  => $mail_page(array($widget('email_appointment_admin'))),
));
$appointment_pages[] = array_merge($mail_settings, array(
    'key'   => 'email_appointment_received',
    'name'  => lang('email-appointment-request-received'),
    'title' => lang('Appointment Request Received E-mail'),
    'tree'  => $mail_page(array($widget('email_appointment_client'))),
));
$appointment_pages[] = array_merge($mail_settings, array(
    'key'   => 'email_plan_join_new',
    'name'  => lang('email-new-health-plan-request'),
    'title' => lang('New Health Plan Request E-mail'),
    'tree'  => $mail_page(array($widget('email_plan_join_admin'))),
));
$appointment_pages[] = array_merge($mail_settings, array(
    'key'   => 'email_plan_join_received',
    'name'  => lang('email-health-plan-request-received'),
    'title' => lang('Health Plan Request Received E-mail'),
    'tree'  => $mail_page(array($widget('email_plan_join_client'))),
));
// A new message or reply: sent to the sender of an appointment or health
// plan request, the sender of a conversation and the visitors watching a
// page. Its Comments widget draws the comment in the card of mail.php; the
// System region after it keeps the fixed comment for an e-mail the widget
// does not draw.
$appointment_pages[] = array_merge($mail_settings, array(
    'key'   => 'email_reply',
    'name'  => lang('email-new-reply'),
    'title' => lang('New Reply E-mail'),
    'tree'  => $mail_page(array(
        $mail_heading(lang('There is a new message')),
        $mail_para(lang('Something new was written on a page you follow: your appointment, your health plan request, a conversation or a Pet Care Article. You can read it below and answer it on the page.')),
        $widget('email_comment'),
        array('type' => 'region', 'props' => array('regionType' => 'system', 'regionName' => ''), 'children' => array()),
    )),
));

// ── Widget pieces ───────────────────────────────────────────────────────

// The sender's name.
$owner_name = function () use ($span, $bind) {
    return array(
        $span(lang('Jane'), '', lang('First Name'), $bind('first_name')),
        $span(' ', '', lang('Text')),
        $span(lang('Cooper'), '', lang('Last Name'), $bind('last_name')),
    );
};
// The pet: its name, then its kind.
$pet_line = function () use ($span, $bind, $sample_pet, $sample_species) {
    return array(
        $span($sample_pet, '', lang('Pet\'s Name'), $bind('pet_name')),
        $span(' · ', '', lang('Text')),
        $span($sample_species, '', lang('Pet'), $bind('species')),
    );
};
// When the owner would like to come: the date, then the time of day.
$visit_when = function () use ($span, $bind, $sample_date, $sample_time) {
    return array(
        $span($sample_date, '', lang('Date'), $bind('preferred_date')),
        $span(' · ', '', lang('Text')),
        $span($sample_time, '', lang('Time'), $bind('time')),
    );
};

// A request in a list, linked to its page: its title and status, then its
// details. The staff's row starts with the sender's name and adds the
// phone; the owner's own adds the messages.
$request_row = function ($staff, $title_sample, $title_field, $title_name, $status, $items) use ($el, $span, $icon, $bind, $owner_name) {
    if ($staff) {
        $items[] = $el('span', 'd-inline-flex align-items-center gap-1', lang('Phone'), array(
            $icon('bi-telephone'), $span('+90 555 000 00 00', '', lang('Phone'), $bind('phone')),
        ));
        $title = $el('span', 'fw-semibold', lang('Sender'), array_merge($owner_name(), array(
            $span(' · ', 'text-body-secondary', lang('Text')),
            $span($title_sample, 'text-body-secondary fw-normal', $title_name, $bind($title_field)),
        )));
    } else {
        $items[] = $el('span', 'd-inline-flex align-items-center gap-1', lang('Messages'), array(
            $icon('bi-chat-left-text'), $span('0', '', lang('Reply Count'), $bind('number_of_comments')),
        ));
        $title = $span($title_sample, 'fw-semibold', $title_name, $bind($title_field));
    }
    return $el('a', 'list-group-item list-group-item-action p-3', lang('Request'), array(
        $el('div', 'd-flex flex-wrap align-items-center justify-content-between gap-2 mb-1', lang('Top Line'), array(
            $title,
            $span($status, 'badge rounded-pill bg-primary-subtle text-primary-emphasis', lang('Status'), $bind('status')),
        )),
        $el('div', 'd-flex flex-wrap column-gap-3 row-gap-1 small text-body-secondary', lang('Details'), $items),
    ), array('href' => '#', '_bindings' => array('href' => 'form_item_view')));
};
$appointment_row = function ($staff = false) use ($el, $icon, $request_row, $pet_line, $visit_when, $sample_service, $appointment_statuses) {
    return $request_row($staff, $sample_service, 'service', lang('Service'), $appointment_statuses[0], array(
        $el('span', 'd-inline-flex align-items-center gap-1', lang('Pet'), array_merge(array($icon('bi-heart')), $pet_line())),
        $el('span', 'd-inline-flex align-items-center gap-1', lang('When'), array_merge(array($icon('bi-calendar-event')), $visit_when())),
    ));
};
$plan_join_row = function ($staff = false) use ($el, $icon, $request_row, $pet_line, $sample_plan, $plan_join_statuses) {
    return $request_row($staff, $sample_plan, 'plan', lang('Plan'), $plan_join_statuses[0], array(
        $el('span', 'd-inline-flex align-items-center gap-1', lang('Pet'), array_merge(array($icon('bi-heart')), $pet_line())),
    ));
};

// A detail of the record's card: the label, then the value. $value is
// either the value's props (its sample text and binding) or, with
// $children, the nodes it is made of.
$detail = function ($label, $class, $name, $value, $children = false) use ($el) {
    return array(
        $el('dt', 'col-sm-4 fw-normal text-body-secondary', lang('Label'), array(), array('text' => $label)),
        $el('dd', trim('col-sm-8 ' . $class), $name, $children ? $value : array(), $children ? array() : $value),
    );
};
$bound = function ($sample, $field) use ($bind) {
    return array_merge(array('text' => $sample), $bind($field));
};

// The lines of an appointment request and of a health plan request, for
// their e-mails.
$mail_appointment = function () use ($mail_box, $mail_line, $span, $bind, $pet_line, $visit_when, $sample_service, $sample_vet, $age_choices, $visit_choices) {
    return $mail_box(array(
        $mail_line(lang('Service'), array($span($sample_service, '', lang('Service'), $bind('service')))),
        $mail_line(lang('Pet'), $pet_line()),
        $mail_line(lang('Age'), array($span($age_choices[1], '', lang('Age'), $bind('pet_age')))),
        $mail_line(lang('Vet'), array($span($sample_vet, '', lang('Vet'), $bind('vet')))),
        $mail_line(lang('When'), $visit_when()),
        $mail_line(lang('New or registered'), array($span($visit_choices[0][1], '', lang('New or registered'), $bind('visit')))),
    ), lang('Appointment Request'));
};
$mail_plan_join = function () use ($mail_box, $mail_line, $span, $bind, $pet_line, $sample_plan, $age_choices, $contact_choices) {
    return $mail_box(array(
        $mail_line(lang('Plan'), array($span($sample_plan, '', lang('Plan'), $bind('plan')))),
        $mail_line(lang('Pet'), $pet_line()),
        $mail_line(lang('Age'), array($span($age_choices[1], '', lang('Age'), $bind('pet_age')))),
        $mail_line(lang('Preferred start'), array($span(lang('Preferred start'), '', lang('Preferred start'), $bind('start_date')))),
        $mail_line(lang('Reply by'), array($span($contact_choices[0][1], '', lang('Reply by'), $bind('reply_by')))),
    ), lang('Health Plan Request'));
};
// A reference code line under a staff e-mail.
$mail_reference = function () use ($el, $span, $bind, $mail_style) {
    return $el('p', 'small text-body-secondary mt-4 mb-0', lang('Reference'), array(
        $span(lang('Reference Code') . ': ', '', lang('Label')),
        $span('ABC123', '', lang('Reference Code'), $bind('reference_code')),
    ), $mail_style('font-size:13px;color:#4b5563;margin:24px 0 0'));
};
// The account a form opened for a sender who had none, in the reply e-mail.
$mail_new_account = function () use ($el, $span, $link, $bind, $show_when, $mail_style) {
    return $el('div', 'border-top pt-3', lang('New Account'), array(
        $el('p', 'mb-2', lang('Sign-in Email'), array(
            $span(lang('We opened an account for you with this address:') . ' ', '', lang('Label')),
            $span('sample@example.com', 'fw-semibold', lang('Value'), $bind('__new_account_email')),
        ), $mail_style('margin:0 0 8px')),
        $el('p', 'mb-0', lang('Password'), array(
            $span(lang('To choose your password, use') . ' ', '', lang('Label')),
            $link(lang('Forgot Password'), '{{page:forgot_password}}', ''),
        ), $mail_style('margin:0')),
    ), array_merge($mail_style('border-top:1px solid #d1fae5;padding-top:16px'), $show_when('has_new_account')));
};
// The sender's lines of a staff e-mail.
$mail_sender = function ($notes_sample) use ($mail_box, $mail_line, $span, $bind, $owner_name) {
    return $mail_box(array(
        $mail_line(lang('Name'), $owner_name()),
        $mail_line(lang('Email'), array($span('jane.cooper@example.com', '', lang('Email'), $bind('email')))),
        $mail_line(lang('Phone'), array($span('+90 555 000 00 00', '', lang('Phone'), $bind('phone')))),
        $mail_line(lang('Notes'), array($span($notes_sample, '', lang('Notes'), $bind('notes')))),
    ), lang('Sender'));
};
// The form detail settings of an e-mail page's widget (any reader: the
// e-mail is drawn for the submission just sent) and of a request page's
// widget (the sender's own, and the staff's).
$detail_widget = function ($page_key, $form_key, $missing, $access) {
    return array(
        'page'   => $page_key,
        'slug'   => lang('form-detail'),
        'config' => array(
            'regionType'          => 'form_item_view',
            'custom_form_page_id' => '{{tab:' . $form_key . '}}',
            'access_control'      => $access,
            'not_found_message'   => $missing,
        ),
    );
};
// A list of requests: the owner's own (the staff see them all), or the
// staff's with a search.
$request_list = function ($page_key, $form_key, $view_key, $row, $search_fields, $search_label, $empty) use ($widget_root, $el, $loop) {
    $staff = $search_fields !== array();
    $config = array(
        'regionType'          => 'form_list_view',
        'custom_form_page_id' => '{{tab:' . $form_key . '}}',
        'detail_page_id'      => '{{tab:' . $view_key . '}}',
        'items_per_page'      => 20,
        'order_by_field'      => 'submitted_date_and_time',
        'order_by_direction'  => 'DESC',
    );
    if ($staff) {
        $config += array(
            'search_enabled' => true,
            'search_fields'  => $search_fields,
            'search_width'   => 'half',
            'search_label'   => $search_label,
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
            $el('div', 'list-group', lang('Request List'), array($loop(array($row($staff))))),
        )),
    );
};

// The record page of a request: not found, the notices of a request just
// sent, the toolbar, the card of details, the notes and a closing line.
$request_detail = function ($back_text, $back_key, $missing, $not_found_title, $eyebrow, $title_sample, $title_field, $status, $sent, $details, $notes_sample, $closing)
    use ($widget_root, $container, $row, $col, $el, $icon, $heading, $para, $link, $span, $bind, $show_when, $attr, $edit_button, $bound) {
    return $widget_root(array(
        $container(array(
            $row(array(
                $col(array(
                    $el('div', 'text-center py-5', lang('Not Found'), array(
                        $icon('bi-calendar-x', 'display-5 text-body-tertiary'),
                        $heading('h2', $not_found_title, 'h3 fw-semibold mt-3'),
                        $para($missing, 'text-body-secondary mb-4', $bind('not_found')),
                        $link($back_text, '{{page:' . $back_key . '}}', 'btn btn-primary rounded-pill px-4'),
                    ), $show_when('record_not_found')),
                    $el('div', '', lang('Record'), array(
                        $el('div', 'alert alert-success d-flex gap-2', lang('Sent Notice'), array(
                            $icon('bi-check-circle', 'mt-1'),
                            $span($sent, '', lang('Text')),
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
                                $span($back_text, '', lang('Link Text')),
                            ), array('href' => '{{page:' . $back_key . '}}')),
                            $edit_button(lang('Change Status')),
                        )),
                        $el('div', 'card border-0 shadow-sm rounded-4 mb-4', lang('Request Card'), array(
                            $el('div', 'card-body p-4', lang('Card Body'), array(
                                $el('div', 'd-flex flex-wrap align-items-start justify-content-between gap-2 mb-3', lang('Title'), array(
                                    $el('div', '', lang('Words'), array(
                                        $para($eyebrow, 'pvt-eyebrow small text-uppercase fw-semibold text-primary mb-1'),
                                        $heading('h1', $title_sample, 'pvt-display h3 mb-0', $bind($title_field)),
                                    )),
                                    $span($status, 'badge rounded-pill bg-primary-subtle text-primary-emphasis fs-6', lang('Status'), $bind('status')),
                                )),
                                $el('dl', 'row small mb-0', lang('Details'), $details),
                            )),
                        )),
                        $el('div', 'pg-hide-if-empty p-4 rounded-4 bg-body-tertiary mb-4', lang('Notes'), array(
                            $para(lang('Notes'), 'small fw-semibold text-body-secondary mb-2'),
                            $el('div', 'pg-field-value lh-lg', lang('Value'), array(), $bound($notes_sample, 'notes')),
                        )),
                        $el('div', 'd-flex gap-2 small text-body-secondary', lang('Note'), array(
                            $icon('bi-chat-left-text', 'text-primary mt-1'),
                            $span($closing, '', lang('Text')),
                        )),
                    ), $show_when('record_found')),
                ), '', '8'),
            ), '', 'center'),
        ), 'py-5'),
    ));
};

// The messages under a request: the page's own Comments widget, shown only
// to whoever may read the request above it. Oldest first, all on one page.
// No Reply, Share or follow box: the sender is e-mailed every answer and
// the reception every message.
$request_messages = function ($page_key, $empty) use ($comment_thread) {
    return array(
        'page'   => $page_key,
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
            'empty'     => $empty,
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
    );
};

// The sender's fields at the end of both forms.
$contact_fields = function ($p) use ($row, $col, $field, $input) {
    return $row(array(
        $col(array($field($p . '-first-name', lang('First Name'), $input($p . '-first-name', 'text', 'first_name', 'given-name', true, array('contact_field' => 'first_name')))), '6'),
        $col(array($field($p . '-last-name', lang('Last Name'), $input($p . '-last-name', 'text', 'last_name', 'family-name', true, array('contact_field' => 'last_name')))), '6'),
        $col(array($field($p . '-email', lang('Email'), $input($p . '-email', 'email', 'email', 'email', true, array('contact_field' => 'email_address')))), '6'),
        $col(array($field($p . '-phone', lang('Phone'), $input($p . '-phone', 'tel', 'phone', 'tel', true, array('contact_field' => 'mobile_phone')))), '6'),
    ), '3');
};
// The pet's name, kind and age, at the start of both forms. The kind
// starts with what a link sends (?species=) on the appointment request.
$pet_fields = function ($p, $species_param) use ($row, $col, $field, $input, $select, $pet_choices, $age_choices) {
    return $row(array(
        $col(array($field($p . '-pet-name', lang('Pet\'s Name'), $input($p . '-pet-name', 'text', 'pet_name', 'off', true))), '4'),
        $col(array($field($p . '-species', lang('Pet'), $select($p . '-species', 'species', lang('Choose a pet…'), $pet_choices, true, '',
            $species_param ? array('url_param' => 'species') : array()))), '4'),
        $col(array($field($p . '-age', lang('Age'), $select($p . '-age', 'pet_age', lang('Choose an age…'), $age_choices, false))), '4'),
    ), '3');
};

$appointment_missing = lang('This appointment request could not be found, or it is not yours to see.');
$plan_join_missing = lang('This health plan request could not be found, or it is not yours to see.');
$not_before_today = array('rules' => array('min_date' => 'today'));

$appointment_widgets = array(

    // The appointment request: the fields are the page's form. The
    // service, the vet and the pet start with what a link sends (?service=,
    // ?vet=, ?species=). The Status is the reception's (office use only):
    // every request starts as Request received.
    'appointment_form' => array(
        'page'   => 'appointment',
        'slug'   => 'form',
        'config' => array('regionType' => 'custom_form', 'form_source' => 'page'),
        'tree'   => $widget_root(array(
            $form_step(1, lang('Your pet')),
            $pet_fields('vt-ap', true),
            $radios('vt-ap-visit', 'visit', lang('New or registered'), $visit_choices, $visit_choices[0][0]),
            $form_step(2, lang('The visit')),
            $row(array(
                $col(array($field('vt-ap-service', lang('Service'), $select('vt-ap-service', 'service', lang('Choose a service…'), $reason_choices, true, '', array('url_param' => 'service')))), '6'),
                $col(array($field('vt-ap-vet', lang('Vet'), $select('vt-ap-vet', 'vet', '', $vet_choices, false, $vet_choices[0], array('url_param' => 'vet')))), '6'),
                $col(array($field('vt-ap-date', lang('Preferred date'), $input('vt-ap-date', 'date', 'preferred_date', 'off', true, $not_before_today))), '6'),
                $col(array($field('vt-ap-time', lang('Time'), $select('vt-ap-time', 'time', lang('Choose a time…'), $time_choices, true))), '6'),
            ), '3'),
            $field('vt-ap-notes', lang('Reason for the visit'), $textarea('vt-ap-notes', 'notes', 4, true), 'mb-3',
                lang('What you have noticed and since when, the medicines your pet takes, anything that makes the visit easier.')),
            $field('vt-ap-file', lang('Earlier results or a photo (optional)'), $file('vt-ap-file', 'records', 'private'), 'mb-3',
                lang('Test results from another vet, or a photo of what worries you.')),
            $form_step(3, lang('How to reach you')),
            $contact_fields('vt-ap'),
            $field('vt-ap-status', lang('Status'), $select('vt-ap-status', 'status', '', $appointment_statuses, false, $appointment_statuses[0], array('office_use_only' => true))),
            $agree('vt-ap-consent', 'consent', lang('I agree that the practice keeps these details, my pet\'s health among them, to answer my request and treat my pet, as its privacy notice explains.'), true),
            $captcha(),
            $el('div', 'd-flex flex-wrap align-items-center gap-3', lang('Send Area'), array(
                $submit(lang('Send the Request'), 'btn btn-primary btn-lg rounded-pill px-4'),
                $para(lang('The reception confirms the time, usually the same day.'), 'small text-body-secondary mb-0'),
            )),
            $loop(array()),
        )),
    ),

    // One appointment request; the reception's answers follow as comments.
    'appointment_detail' => $detail_widget('appointment_view', 'appointment', $appointment_missing, 'submitter_only') + array(
        'tree' => $request_detail(lang('My Appointments'), 'my_appointments', $appointment_missing, lang('Appointment request not found'),
            lang('Appointment Request'), $sample_service, 'service', $appointment_statuses[0],
            lang('Thank you, your request has reached us. The reception confirms the time here, usually the same day; this page shows the status, and you can write to us below.'),
            array_merge(
                $detail(lang('Pet'), '', lang('Pet'), $pet_line(), true),
                $detail(lang('Age'), '', lang('Age'), $bound($age_choices[1], 'pet_age')),
                $detail(lang('Vet'), '', lang('Vet'), $bound($sample_vet, 'vet')),
                $detail(lang('When'), '', lang('When'), $visit_when(), true),
                $detail(lang('New or registered'), '', lang('New or registered'), $bound($visit_choices[0][1], 'visit')),
                $detail(lang('Name'), '', lang('Name'), $owner_name(), true),
                $detail(lang('Reference Code'), 'mb-0 font-monospace', lang('Reference Code'), $bound('ABC123', 'reference_code'))
            ),
            $sample_notes,
            lang('Another time, a question or cannot come? Write to us below; we answer here and by e-mail.')),
    ),

    'appointment_messages' => $request_messages('appointment_view',
        lang('No messages yet. The reception\'s answer comes here; you can write to us about the visit too.')),

    'my_appointments_list' => $request_list('my_appointments', 'appointment', 'appointment_view', $appointment_row, array(), '',
        lang('No appointment requests yet. Ask for an appointment or pick a free time.')),

    'staff_appointments_list' => $request_list('staff_appointments', 'appointment', 'appointment_view', $appointment_row,
        array('first_name', 'last_name', 'email', 'pet_name', 'species', 'service', 'vet', 'status'),
        lang('Search by name, e-mail, pet, service, vet or status...'), lang('No appointment requests yet.')),

    // The health plan request: the plan starts with what a link sends
    // (?plan=).
    'plan_join_form' => array(
        'page'   => 'plan_join',
        'slug'   => 'form',
        'config' => array('regionType' => 'custom_form', 'form_source' => 'page'),
        'tree'   => $widget_root(array(
            $form_step(1, lang('Your plan')),
            $field('vt-pj-plan', lang('Plan'), $select('vt-pj-plan', 'plan', lang('Choose a plan…'), $plan_choices, true, '', array('url_param' => 'plan')), 'mb-3',
                lang('Not sure which one? The vet advises you at the first check-up.')),
            $pet_fields('vt-pj', false),
            $field('vt-pj-start', lang('Preferred start'), $input('vt-pj-start', 'date', 'start_date', 'off', false, $not_before_today)),
            $field('vt-pj-notes', lang('Notes'), $textarea('vt-pj-notes', 'notes', 3, false), 'mb-3',
                lang('Your pet\'s last vaccination, a condition the vet should know about, a second pet to add.')),
            $form_step(2, lang('How to reach you')),
            $contact_fields('vt-pj'),
            $radios('vt-pj-reply', 'reply_by', lang('Reply by'), $contact_choices, $contact_choices[0][0]),
            $field('vt-pj-status', lang('Status'), $select('vt-pj-status', 'status', '', $plan_join_statuses, false, $plan_join_statuses[0], array('office_use_only' => true))),
            $agree('vt-pj-consent', 'consent', lang('I agree that the practice keeps these details to answer my request and run my pet\'s plan, as its privacy notice explains.'), true),
            $captcha(),
            $el('div', 'd-flex flex-wrap align-items-center gap-3', lang('Send Area'), array(
                $submit(lang('Send the Request'), 'btn btn-primary btn-lg rounded-pill px-4'),
                $para(lang('The reception writes to you within a day. Nothing is paid now.'), 'small text-body-secondary mb-0'),
            )),
            $loop(array()),
        )),
    ),

    'plan_join_detail' => $detail_widget('plan_join_view', 'plan_join', $plan_join_missing, 'submitter_only') + array(
        'tree' => $request_detail(lang('Compare the plans'), 'health_plans', $plan_join_missing, lang('Health plan request not found'),
            lang('Health Plan Request'), $sample_plan, 'plan', $plan_join_statuses[0],
            lang('Thank you, your request has reached us. The reception writes to you here within a day to start the plan; you can write to us below.'),
            array_merge(
                $detail(lang('Pet'), '', lang('Pet'), $pet_line(), true),
                $detail(lang('Age'), '', lang('Age'), $bound($age_choices[1], 'pet_age')),
                $detail(lang('Preferred start'), '', lang('Preferred start'), $bound(lang('Preferred start'), 'start_date')),
                $detail(lang('Reply by'), '', lang('Reply by'), $bound($contact_choices[0][1], 'reply_by')),
                $detail(lang('Name'), '', lang('Name'), $owner_name(), true),
                $detail(lang('Reference Code'), 'mb-0 font-monospace', lang('Reference Code'), $bound('ABC123', 'reference_code'))
            ),
            $sample_plan_notes,
            lang('Another plan, a second pet or a question? Write to us below; we answer here and by e-mail.')),
    ),

    'plan_join_messages' => $request_messages('plan_join_view',
        lang('No messages yet. The reception\'s answer comes here; you can write to us about the plan too.')),

    'staff_plan_joins_list' => $request_list('staff_plan_joins', 'plan_join', 'plan_join_view', $plan_join_row,
        array('first_name', 'last_name', 'email', 'pet_name', 'species', 'plan', 'status'),
        lang('Search by name, e-mail, pet, plan or status...'), lang('No health plan requests yet.')),

    // The e-mail pages' widgets: drawn for the submission just sent.
    'email_appointment_admin' => $detail_widget('email_appointment_new', 'appointment', lang('The appointment request could not be found.'), 'public') + array(
        'tree' => $widget_root(array(
            $mail_heading(lang('A new appointment request')),
            $mail_para(lang('It came from the appointment form. Confirm the time under the request on its page, or offer another one, and set the status; what you write there reaches the owner by e-mail.')),
            $mail_appointment(),
            $mail_sender($sample_notes),
            $mail_button(lang('Open the request'), '{{page:appointment_view}}?r=^^reference_code^^'),
            $mail_reference(),
        )),
    ),
    'email_appointment_client' => $detail_widget('email_appointment_received', 'appointment', lang('The appointment request could not be found.'), 'public') + array(
        'tree' => $widget_root(array(
            $mail_heading(lang('We received your appointment request')),
            $mail_greeting(),
            $mail_para(lang('Thank you. The reception confirms the time, usually the same day, on the request\'s page and by e-mail. Bring the pet passport or vaccination card and any medicines your pet takes.')),
            $mail_appointment(),
            $mail_para(lang('If your pet gets worse before the visit, do not wait: call the emergency line, day or night.')),
            $mail_button(lang('Open my request'), '{{page:appointment_view}}?r=^^reference_code^^'),
            $mail_new_account(),
        )),
    ),
    'email_plan_join_admin' => $detail_widget('email_plan_join_new', 'plan_join', lang('The health plan request could not be found.'), 'public') + array(
        'tree' => $widget_root(array(
            $mail_heading(lang('A new health plan request')),
            $mail_para(lang('It came from the health plan form. Book the plan\'s first check-up under the request on its page and set the status; what you write there reaches the owner by e-mail.')),
            $mail_plan_join(),
            $mail_sender($sample_plan_notes),
            $mail_button(lang('Open the request'), '{{page:plan_join_view}}?r=^^reference_code^^'),
            $mail_reference(),
        )),
    ),
    'email_plan_join_client' => $detail_widget('email_plan_join_received', 'plan_join', lang('The health plan request could not be found.'), 'public') + array(
        'tree' => $widget_root(array(
            $mail_heading(lang('We received your health plan request')),
            $mail_greeting(),
            $mail_para(lang('Thank you. The reception writes to you within a day to book the plan\'s first check-up. Nothing is paid now.')),
            $mail_plan_join(),
            $mail_para(lang('A question before you start? Write to us on the request\'s page; everything about the plan stays there.')),
            $mail_button(lang('Open my request'), '{{page:plan_join_view}}?r=^^reference_code^^'),
            $mail_new_account(),
        )),
    ),

    // The comment of the reply e-mail, drawn for the comment the e-mail is
    // about, flat and alone.
    'email_comment' => array(
        'page'   => 'email_reply',
        'slug'   => lang('comments'),
        'config' => array(
            'regionType' => 'comments',
            'order'      => 'oldest',
            'featured'   => 'none',
            'layout'     => 'flat',
            'max_depth'  => 1,
            'per_page'   => 0,
        ),
        'tree'   => $mail_comment_tree,
    ),
);

return array('pages' => $appointment_pages, 'widgets' => $appointment_widgets);
