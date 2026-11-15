<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Pharmacy", its requests: the prescription request, the
 * request's own page with its messages, My Prescriptions and the staff's
 * list; the question to the pharmacist, its page with the private reply
 * thread, My Questions and the staff's list; the privacy page the consent
 * boxes link; the e-mails of both forms and the new message e-mail every
 * thread of the site sends, and the widgets that draw them. Included after
 * parts.php and mail.php in the same scope; returns
 * array('pages' => ..., 'widgets' => ...).
 *
 * A prescription request is a record of the Prescription Requests form,
 * never a sale: the medicines are handed over only against the original
 * prescription, at the counter or at the door. The pharmacy sets its
 * office-use-only Status (being prepared, ready to collect, out for
 * delivery, collected); its answers are the page's comments, mailed to the
 * customer and to the pharmacy. A visitor without an account gets one
 * (auto-registration), so the request's page and My Prescriptions show it
 * at once.
 *
 * A question to the pharmacist is a record of the Questions form whose
 * page only its sender and the staff can read: the answer is a private
 * thread, never a public Q&A.
 *
 * The request's kind and pharmacist start with what a link sends
 * (?service=, ?pharmacist=: the words of the pick lists, as the service
 * cards and the pharmacist page write them); the question's topic with
 * ?topic= (a health topic's title, as a service card or a blog post sends
 * it).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

$request_pages = array();

// The sample requests the canvas shows. The day is words, never a date:
// a fixed day would read as a past week a year later.
$sample_service = $request_choices[0];
$sample_pharmacist = $pharmacist_choices[0];
$sample_day = lang('Preferred day');
$sample_medicines = lang('Blood pressure tablets, one a day, a box of 28; the eye drops I had last time.');
$sample_notes = lang('I can come after work, around six. Please call me if one of the medicines has to be ordered.');
$sample_topic = $topic_choices[0];
$sample_question = lang('Can I take a painkiller for a headache while I am on blood pressure tablets?');
$sample_taken = lang('One blood pressure tablet in the morning, a vitamin D drop on weekdays.');
$prescription_kinds = array(
    array(lang('A new prescription'), lang('A new prescription')),
    array(lang('A repeat of my last one'), lang('A repeat of my last one')),
);

// ── Page shells ─────────────────────────────────────────────────────────

// The side column of a request page: what happens next, then the
// pharmacy's hours and a link.
$request_aside = function ($steps, $link_text, $link_href) use ($el, $heading, $link, $check_list, $opening_hours) {
    $items = array();
    foreach ($steps as $n => $text) {
        $items[] = $el('li', $n < count($steps) - 1 ? 'mb-2' : '', lang('Step'), array(), array('text' => $text));
    }
    $hours = array();
    foreach ($opening_hours as $h) $hours[] = $h['days'] . ': ' . $h['hours'];
    return array(
        $el('div', 'p-4 rounded-4 bg-body-tertiary mb-4', lang('How It Works'), array(
            $heading('h2', lang('What happens next'), 'prx-display h5 mb-3'),
            $el('ol', 'small text-body-secondary ps-3 mb-0', lang('List'), $items),
        )),
        $el('div', 'p-4 rounded-4 border', lang('Opening Hours'), array(
            $heading('h2', lang('Opening hours'), 'prx-display h5 mb-3'),
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

// A customer's own list: the list, then a box that sends a new one.
$my_list_page = function ($key, $name, $title, $lead, $widget_key, $box_icon, $box_title, $box_text, $buttons)
    use ($page, $page_header, $el, $container, $row, $col, $widget, $para, $icon, $span) {
    return array(
        'key'     => $key,
        'name'    => $name,
        'folder'  => 'registration',
        'title'   => $title,
        'search'  => false,
        'sitemap' => false,
        'tree'    => $page(array(
            $page_header($title, $lead),
            $el('section', 'py-5', lang('Content'), array(
                $container(array(
                    $row(array(
                        $col(array($widget($widget_key)), '', '8'),
                        $col(array(
                            $el('div', 'p-4 rounded-4 bg-body-tertiary', lang('New Request'), array(
                                $el('p', 'd-flex align-items-center gap-2 fw-semibold mb-2', lang('Title'), array(
                                    $icon($box_icon, 'text-primary'),
                                    $span($box_title, '', lang('Text')),
                                )),
                                $para($box_text, 'small text-body-secondary mb-3'),
                                $el('div', 'd-flex flex-wrap gap-2', lang('Buttons'), $buttons),
                            )),
                        ), '', '4'),
                    ), '4', '', 'g-lg-5'),
                )),
            )),
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

// The prescription request. What is sent opens the request's own page.
$request_pages[] = array(
    'key'              => 'prescription',
    'name'             => lang('send-a-prescription-request'),
    'folder'           => 'public',
    'title'            => lang('Send a Prescription Request'),
    'meta_description' => lang('Send us your prescription number or the medicines you need: we prepare them and tell you when to collect them, or bring them home.'),
    'form'             => array(
        'form_name'            => lang('Prescription Requests'),
        'confirmation_message' => lang('Thank you, your prescription request has reached us. We tell you as soon as the medicines are ready.'),
        'confirmation_page_id' => '{{tab:prescription_view}}',
        'auto_registration'    => 1,
        'notify_email'         => '{{site_email}}',
        'notify_subject'       => lang('New prescription request: ^^service^^'),
        'notify_page_id'       => '{{tab:email_prescription_new}}',
        'confirm_email'        => 1,
        'confirm_subject'      => lang('We received your prescription request'),
        'confirm_page_id'      => '{{tab:email_prescription_received}}',
    ),
    'tree'             => $request_page(lang('Send a Prescription Request'),
        lang('Give us the prescription number or the medicines you need and how you would like to have them. We prepare everything and tell you when it is ready.'),
        'prescription_form',
        array_merge(array(
            // Nothing is handed over without the original prescription.
            $el('div', 'p-4 rounded-4 bg-warning-subtle text-warning-emphasis mb-4', lang('Original Prescription'), array(
                $heading('h2', lang('Bring the original prescription'), 'prx-display h5 mb-2'),
                $para(lang('We prepare your medicines in advance, but we hand them over only when the original prescription is shown at the counter or at the door.'), 'small mb-0'),
            )),
            // A vaccination or a health check is booked online.
            $el('div', 'p-4 rounded-4 bg-primary-subtle mb-4', lang('Book Online'), array(
                $heading('h2', lang('Book a vaccination or check'), 'prx-display h5 mb-2'),
                $para(lang('A flu jab, a blood pressure or blood sugar check: choose a free time and it is yours at once.'), 'small mb-3'),
                $link(lang('Book a Time'), '{{page:book_online}}', 'btn btn-sm btn-primary rounded-pill px-3'),
            )),
            $el('div', 'p-4 rounded-4 bg-danger-subtle text-danger-emphasis mb-4', lang('Need it tonight?'), array(
                $heading('h2', lang('Need a medicine tonight?'), 'prx-display h5 mb-2'),
                $para(lang('Do not send a request: go to the pharmacy on duty, or call the emergency number in an emergency.'), 'small mb-3'),
                $el('div', 'd-flex flex-wrap gap-2', lang('Buttons'), array(
                    $link(lang('Find the pharmacy on duty'), '{{page:on_duty}}', 'btn btn-sm btn-outline-danger rounded-pill px-3'),
                    $link($pharmacy['emergency'], $tel($pharmacy['emergency']), 'btn btn-sm btn-danger rounded-pill px-3'),
                )),
            )),
        ), $request_aside(array(
            lang('Your request opens its own page, and an account for you if you have none.'),
            lang('We check the stock, order what is missing and set the status there.'),
            lang('You get a message when the medicines are ready to collect or on their way.'),
            lang('Show the original prescription when you collect them, or at the door.'),
        ), lang('How prescriptions work'), '{{page:service_prescriptions}}'))),
);

// One prescription request: the request, its status and the messages.
$request_pages[] = $request_view('prescription_view', lang('prescription-request'), lang('Prescription Request'),
    'prescription_detail', 'prescription_messages', lang('There is a new message about your prescription request'));

// My Prescriptions: the customer's own requests (for the staff, everybody's).
$request_pages[] = $my_list_page('my_prescriptions', lang('my-prescriptions'), lang('My Prescriptions'),
    lang('Your prescription requests, their status and the pharmacy\'s answers, newest first.'),
    'my_prescriptions_list', 'bi-prescription2', lang('Another prescription?'),
    lang('Send a new request or book a vaccination or check; your details are filled in for you.'),
    array(
        $link(lang('Send a Prescription Request'), '{{page:prescription}}', 'btn btn-sm btn-primary rounded-pill px-3'),
        $link(lang('Book a Time'), '{{page:book_online}}', 'btn btn-sm btn-outline-primary rounded-pill px-3'),
    ));

$request_pages[] = $staff_list_page('staff_prescriptions', lang('all-prescription-requests'), lang('Prescription Requests'),
    lang('Every prescription request, newest first: prepare the medicines, set the status and write to the customer under each.'),
    'staff_prescriptions_list');

// The question to the pharmacist: what is sent opens the question's own
// page, which only its sender and the staff can read.
$request_pages[] = array(
    'key'              => 'ask',
    'name'             => lang('ask-a-pharmacist'),
    'folder'           => 'public',
    'title'            => lang('Ask a Pharmacist'),
    'meta_description' => lang('Ask our pharmacists about a medicine, a side effect or a health worry: the answer comes to you privately, usually the same day.'),
    'form'             => array(
        'form_name'            => lang('Questions to the Pharmacist'),
        'confirmation_message' => lang('Thank you, your question has reached our pharmacists. The answer comes to your question\'s page and by e-mail.'),
        'confirmation_page_id' => '{{tab:ask_view}}',
        'auto_registration'    => 1,
        'notify_email'         => '{{site_email}}',
        'notify_subject'       => lang('New question to the pharmacist: ^^topic^^'),
        'notify_page_id'       => '{{tab:email_question_new}}',
        'confirm_email'        => 1,
        'confirm_subject'      => lang('We received your question'),
        'confirm_page_id'      => '{{tab:email_question_received}}',
    ),
    'tree'             => $request_page(lang('Ask a Pharmacist'),
        lang('A question about a medicine, a side effect or what to take for a cold? Write to us: a pharmacist answers you privately, usually the same day.'),
        'ask_form',
        array_merge(array(
            $el('div', 'p-4 rounded-4 bg-danger-subtle text-danger-emphasis mb-4', lang('Emergencies'), array(
                $heading('h2', lang('Not for an emergency'), 'prx-display h5 mb-2'),
                $para(lang('Chest pain, breathing difficulty or a poisoning cannot wait for an answer: call the emergency number at once.'), 'small mb-3'),
                $link($pharmacy['emergency'], $tel($pharmacy['emergency']), 'btn btn-sm btn-danger rounded-pill px-3'),
            )),
        ), $request_aside(array(
            lang('Your question opens its own page, which only you and our pharmacists can read.'),
            lang('A pharmacist answers there, usually the same day, and you get the answer by e-mail.'),
            lang('Ask more under the answer; everything stays on the question\'s page.'),
            lang('For a longer talk, come in: the consultation room is free.'),
        ), lang('Read our health advice'), '{{page:blog}}'))),
);

// One question: the question, its status and the private replies.
$request_pages[] = $request_view('ask_view', lang('question-to-the-pharmacist'), lang('Question to the Pharmacist'),
    'question_detail', 'question_messages', lang('There is a new answer to your question'));

$request_pages[] = $my_list_page('my_questions', lang('my-questions'), lang('My Questions'),
    lang('Your questions to our pharmacists and their answers, newest first.'),
    'my_questions_list', 'bi-chat-left-dots', lang('Another question?'),
    lang('Ask a pharmacist about a medicine or a health worry; the answer comes privately.'),
    array(
        $link(lang('Ask a Pharmacist'), '{{page:ask}}', 'btn btn-sm btn-primary rounded-pill px-3'),
    ));

$request_pages[] = $staff_list_page('staff_questions', lang('all-questions-to-the-pharmacist'), lang('Questions to the Pharmacist'),
    lang('Every question, newest first: answer under each, then set the status.'),
    'staff_questions_list');

// The privacy notice the consent boxes, the booking widget and the footer
// link to: sample text with a note to adapt it.
$request_pages[] = array(
    'key'              => 'privacy',
    'name'             => lang('privacy-policy'),
    'folder'           => 'public',
    'title'            => lang('Privacy Policy'),
    'meta_description' => lang('What the pharmacy keeps about you when you send a prescription request or a question, why, and what you can ask of us.'),
    'tree'             => $page(array(
        $page_header(lang('Privacy Policy'), lang('What the pharmacy keeps about you when you send a prescription request or a question, why, and what you can ask of us.')),
        $el('section', 'py-5', lang('Your Data'), array(
            $container(array($row(array($col(array(
                $el('div', 'alert alert-secondary d-flex gap-2 small', lang('Sample Text Note'), array(
                    $icon('bi-info-circle', 'mt-1'),
                    $span(lang('Sample text: adapt it to your company and to the data protection law that applies to you before you publish.'), '', lang('Text')),
                ), array('_attrs' => array($attr('role', 'note')))),
                $heading('h2', lang('Your data'), 'prx-display h4 mb-3'),
                $para(lang('When you send a prescription request or a question, book a vaccination or a check, join the newsletter or open an account, we keep your name, your contact details, the medicines you ask for and what you tell us about your health, to prepare your medicines, answer you and advise you safely.'), 'mb-3'),
                $para(lang('We keep it as long as the law requires, never sell it and never give it to advertisers. You can see and change your account data from My Account, and ask us about the rest through the contact page.'), 'mb-0'),
            ), '', '8'))))),
        ), $searchable('rx-your-data', lang('privacy, personal data, KVKK, GDPR, delete my data'))),
    )),
);

// The e-mails: what the pharmacy receives and the reply to the sender of
// each form, and the new message e-mail.
foreach (array(
    array('email_prescription_new', lang('email-new-prescription-request'), lang('New Prescription Request E-mail'), 'email_prescription_admin'),
    array('email_prescription_received', lang('email-prescription-request-received'), lang('Prescription Request Received E-mail'), 'email_prescription_client'),
    array('email_question_new', lang('email-new-question-to-the-pharmacist'), lang('New Question E-mail'), 'email_question_admin'),
    array('email_question_received', lang('email-question-received'), lang('Question Received E-mail'), 'email_question_client'),
) as $m) {
    $request_pages[] = array_merge($mail_settings, array(
        'key'   => $m[0],
        'name'  => $m[1],
        'title' => $m[2],
        'tree'  => $mail_page(array($widget($m[3]))),
    ));
}
// A new message or reply: sent to the sender of a prescription request or
// a question, the sender of a conversation and the visitors watching a
// page. Its Comments widget draws the comment in the card of mail.php; the
// System region after it keeps the fixed comment for an e-mail the widget
// does not draw.
$request_pages[] = array_merge($mail_settings, array(
    'key'   => 'email_reply',
    'name'  => lang('email-new-reply'),
    'title' => lang('New Reply E-mail'),
    'tree'  => $mail_page(array(
        $mail_heading(lang('There is a new message')),
        $mail_para(lang('Something new was written on a page you follow: your prescription request, your question, a conversation or a Health Advice article. You can read it below and answer it on the page.')),
        $widget('email_comment'),
        array('type' => 'region', 'props' => array('regionType' => 'system', 'regionName' => ''), 'children' => array()),
    )),
));

// ── Widget pieces ───────────────────────────────────────────────────────

// The sender's name.
$sender_name = function () use ($span, $bind) {
    return array(
        $span(lang('Jane'), '', lang('First Name'), $bind('first_name')),
        $span(' ', '', lang('Text')),
        $span(lang('Cooper'), '', lang('Last Name'), $bind('last_name')),
    );
};
// How the medicines are handed over, then the preferred day.
$handover = function () use ($span, $bind, $collect_choices, $sample_day) {
    return array(
        $span($collect_choices[0][1], '', lang('Collect or Deliver'), $bind('collect')),
        $span(' · ', '', lang('Text')),
        $span($sample_day, '', lang('Date'), $bind('preferred_date')),
    );
};

// A request in a list, linked to its page: its title and status, then its
// details. The staff's row starts with the sender's name and adds the
// phone; the customer's own adds the messages.
$request_row = function ($staff, $title_sample, $title_field, $title_name, $status, $items) use ($el, $span, $icon, $bind, $sender_name) {
    if ($staff) {
        $items[] = $el('span', 'd-inline-flex align-items-center gap-1', lang('Phone'), array(
            $icon('bi-telephone'), $span('+90 555 000 00 00', '', lang('Phone'), $bind('phone')),
        ));
        $title = $el('span', 'fw-semibold', lang('Sender'), array_merge($sender_name(), array(
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
$prescription_row = function ($staff = false) use ($el, $span, $icon, $bind, $request_row, $handover, $sample_service, $prescription_statuses) {
    return $request_row($staff, $sample_service, 'service', lang('Request'), $prescription_statuses[0], array(
        $el('span', 'd-inline-flex align-items-center gap-1', lang('Patient'), array(
            $icon('bi-person'), $span(lang('Jane Cooper'), '', lang('Patient\'s Name'), $bind('patient_name')),
        )),
        $el('span', 'd-inline-flex align-items-center gap-1', lang('When'), array_merge(array($icon('bi-bag-check')), $handover())),
    ));
};
$question_row = function ($staff = false) use ($el, $span, $icon, $bind, $request_row, $sample_topic, $question_statuses) {
    return $request_row($staff, $sample_topic, 'topic', lang('Topic'), $question_statuses[0], array(
        $el('span', 'd-inline-flex align-items-center gap-1', lang('Reply by'), array(
            $icon('bi-reply'), $span(lang('E-mail'), '', lang('Reply by'), $bind('reply_by')),
        )),
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

// The lines of a prescription request and of a question, for their
// e-mails.
$mail_prescription = function () use ($mail_box, $mail_line, $span, $bind, $handover, $sample_service, $sample_pharmacist, $sample_medicines, $prescription_kinds) {
    return $mail_box(array(
        $mail_line(lang('Request'), array($span($sample_service, '', lang('Request'), $bind('service')))),
        $mail_line(lang('Patient'), array($span(lang('Jane Cooper'), '', lang('Patient\'s Name'), $bind('patient_name')))),
        $mail_line(lang('Prescription'), array($span($prescription_kinds[0][1], '', lang('Prescription'), $bind('prescription_kind')))),
        $mail_line(lang('Prescription number'), array($span('1A2B3C4', '', lang('Prescription number'), $bind('prescription_number')))),
        $mail_line(lang('Medicines'), array($span($sample_medicines, '', lang('Medicines'), $bind('medicines')))),
        $mail_line(lang('Collect or Deliver'), $handover()),
        $mail_line(lang('Pharmacist'), array($span($sample_pharmacist, '', lang('Pharmacist'), $bind('pharmacist')))),
    ), lang('Prescription Request'));
};
$mail_question = function () use ($mail_box, $mail_line, $span, $bind, $sample_topic, $sample_question, $sample_taken, $contact_choices) {
    return $mail_box(array(
        $mail_line(lang('Topic'), array($span($sample_topic, '', lang('Topic'), $bind('topic')))),
        $mail_line(lang('Question'), array($span($sample_question, '', lang('Question'), $bind('question')))),
        $mail_line(lang('Medicines already taken'), array($span($sample_taken, '', lang('Medicines already taken'), $bind('medicines_taken')))),
        $mail_line(lang('Reply by'), array($span($contact_choices[0][1], '', lang('Reply by'), $bind('reply_by')))),
    ), lang('Question'));
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
    ), array_merge($mail_style('border-top:1px solid #ccfbf1;padding-top:16px'), $show_when('has_new_account')));
};
// The sender's lines of a staff e-mail; $notes adds the notes line.
$mail_sender = function ($notes_sample) use ($mail_box, $mail_line, $span, $bind, $sender_name) {
    $lines = array(
        $mail_line(lang('Name'), $sender_name()),
        $mail_line(lang('Email'), array($span('jane.cooper@example.com', '', lang('Email'), $bind('email')))),
        $mail_line(lang('Phone'), array($span('+90 555 000 00 00', '', lang('Phone'), $bind('phone')))),
    );
    if ($notes_sample !== '') $lines[] = $mail_line(lang('Notes'), array($span($notes_sample, '', lang('Notes'), $bind('notes'))));
    return $mail_box($lines, lang('Sender'));
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
// A list of requests: the customer's own (the staff see them all), or the
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
// sent, the toolbar, the card of details, the long text and a closing line.
$request_detail = function ($back_text, $back_key, $missing, $not_found_title, $eyebrow, $title_sample, $title_field, $status, $sent, $details, $text_label, $text_sample, $text_field, $closing)
    use ($widget_root, $container, $row, $col, $el, $icon, $heading, $para, $link, $span, $bind, $show_when, $attr, $edit_button, $bound) {
    return $widget_root(array(
        $container(array(
            $row(array(
                $col(array(
                    $el('div', 'text-center py-5', lang('Not Found'), array(
                        $icon('bi-file-earmark-x', 'display-5 text-body-tertiary'),
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
                                        $para($eyebrow, 'prx-eyebrow small text-uppercase fw-semibold text-primary mb-1'),
                                        $heading('h1', $title_sample, 'prx-display h3 mb-0', $bind($title_field)),
                                    )),
                                    $span($status, 'badge rounded-pill bg-primary-subtle text-primary-emphasis fs-6', lang('Status'), $bind('status')),
                                )),
                                $el('dl', 'row small mb-0', lang('Details'), $details),
                            )),
                        )),
                        $el('div', 'pg-hide-if-empty p-4 rounded-4 bg-body-tertiary mb-4', $text_label, array(
                            $para($text_label, 'small fw-semibold text-body-secondary mb-2'),
                            $el('div', 'pg-field-value lh-lg', lang('Value'), array(), $bound($text_sample, $text_field)),
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
// the pharmacy every message.
$request_messages = function ($page_key, $empty, $heading_text) use ($comment_thread) {
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
            'heading'   => $heading_text,
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

$prescription_missing = lang('This prescription request could not be found, or it is not yours to see.');
$question_missing = lang('This question could not be found, or it is not yours to see.');
$not_before_today = array('rules' => array('min_date' => 'today'));

$request_widgets = array(

    // The prescription request: the fields are the page's form. The kind
    // and the pharmacist start with what a link sends (?service=,
    // ?pharmacist=). The Status is the pharmacy's (office use only): every
    // request starts as Request received.
    'prescription_form' => array(
        'page'   => 'prescription',
        'slug'   => 'form',
        'config' => array('regionType' => 'custom_form', 'form_source' => 'page'),
        'tree'   => $widget_root(array(
            $form_step(1, lang('Your prescription')),
            $row(array(
                $col(array($field('rx-pr-service', lang('Request'), $select('rx-pr-service', 'service', lang('Choose a request…'), $request_choices, true, '', array('url_param' => 'service')))), '6'),
                $col(array($field('rx-pr-pharmacist', lang('Pharmacist'), $select('rx-pr-pharmacist', 'pharmacist', '', $pharmacist_choices, false, $pharmacist_choices[0], array('url_param' => 'pharmacist')))), '6'),
                $col(array($field('rx-pr-patient', lang('Patient\'s Name'), $input('rx-pr-patient', 'text', 'patient_name', 'off', true))), '6'),
                $col(array($field('rx-pr-birth', lang('Date of birth'), $input('rx-pr-birth', 'date', 'birth_date', 'off', true))), '6'),
            ), '3'),
            $radios('rx-pr-kind', 'prescription_kind', lang('Prescription'), $prescription_kinds, $prescription_kinds[0][0]),
            $field('rx-pr-number', lang('Prescription number'), $input('rx-pr-number', 'text', 'prescription_number', 'off', false), 'mb-3',
                lang('The code on your prescription; leave it empty for a repeat of your last one.')),
            $field('rx-pr-medicines', lang('Medicines'), $textarea('rx-pr-medicines', 'medicines', 3, true), 'mb-3',
                lang('Each medicine, its strength and how many boxes, as written on the prescription.')),
            $form_step(2, lang('Collect or Deliver')),
            $radios('rx-pr-collect', 'collect', lang('Collect or Deliver'), $collect_choices, $collect_choices[0][0]),
            $field('rx-pr-date', lang('Preferred day'), $input('rx-pr-date', 'date', 'preferred_date', 'off', true, $not_before_today)),
            $field('rx-pr-notes', lang('Notes'), $textarea('rx-pr-notes', 'notes', 3, false), 'mb-3',
                lang('A time that suits you, the delivery address, an allergy we should know about.')),
            $form_step(3, lang('How to reach you')),
            $contact_fields('rx-pr'),
            $field('rx-pr-status', lang('Status'), $select('rx-pr-status', 'status', '', $prescription_statuses, false, $prescription_statuses[0], array('office_use_only' => true))),
            $agree('rx-pr-consent', 'consent', lang('I agree that the pharmacy keeps these details, my medicines among them, to prepare my prescription, as its privacy notice explains.'), true),
            $captcha(),
            $el('div', 'd-flex flex-wrap align-items-center gap-3', lang('Send Area'), array(
                $submit(lang('Send the Request'), 'btn btn-primary btn-lg rounded-pill px-4'),
                $para(lang('The medicines are handed over against the original prescription.'), 'small text-body-secondary mb-0'),
            )),
            $loop(array()),
        )),
    ),

    // One prescription request; the pharmacy's answers follow as comments.
    'prescription_detail' => $detail_widget('prescription_view', 'prescription', $prescription_missing, 'submitter_only') + array(
        'tree' => $request_detail(lang('My Prescriptions'), 'my_prescriptions', $prescription_missing, lang('Prescription request not found'),
            lang('Prescription Request'), $sample_service, 'service', $prescription_statuses[0],
            lang('Thank you, your request has reached us. We tell you here when the medicines are ready; this page shows the status, and you can write to us below.'),
            array_merge(
                $detail(lang('Patient'), '', lang('Patient\'s Name'), $bound(lang('Jane Cooper'), 'patient_name')),
                $detail(lang('Prescription'), '', lang('Prescription'), $bound($prescription_kinds[0][1], 'prescription_kind')),
                $detail(lang('Prescription number'), 'font-monospace', lang('Prescription number'), $bound('1A2B3C4', 'prescription_number')),
                $detail(lang('Collect or Deliver'), '', lang('Collect or Deliver'), $handover(), true),
                $detail(lang('Pharmacist'), '', lang('Pharmacist'), $bound($sample_pharmacist, 'pharmacist')),
                $detail(lang('Name'), '', lang('Name'), $sender_name(), true),
                $detail(lang('Reference Code'), 'mb-0 font-monospace', lang('Reference Code'), $bound('ABC123', 'reference_code'))
            ),
            lang('Medicines'), $sample_medicines, 'medicines',
            lang('Show the original prescription when you collect the medicines. A question or another day? Write to us below.')),
    ),

    'prescription_messages' => $request_messages('prescription_view',
        lang('No messages yet. The pharmacy\'s answer comes here; you can write to us about the request too.'), lang('Write to us')),

    'my_prescriptions_list' => $request_list('my_prescriptions', 'prescription', 'prescription_view', $prescription_row, array(), '',
        lang('No prescription requests yet. Send one and collect your medicines without waiting.')),

    'staff_prescriptions_list' => $request_list('staff_prescriptions', 'prescription', 'prescription_view', $prescription_row,
        array('first_name', 'last_name', 'email', 'patient_name', 'prescription_number', 'service', 'pharmacist', 'collect', 'status'),
        lang('Search by name, e-mail, patient, prescription number or status...'), lang('No prescription requests yet.')),

    // The question to the pharmacist: the topic starts with what a link
    // sends (?topic=).
    'ask_form' => array(
        'page'   => 'ask',
        'slug'   => 'form',
        'config' => array('regionType' => 'custom_form', 'form_source' => 'page'),
        'tree'   => $widget_root(array(
            $form_step(1, lang('Your question')),
            $field('rx-aq-topic', lang('Topic'), $select('rx-aq-topic', 'topic', lang('Choose a topic…'), $topic_choices, true, '', array('url_param' => 'topic'))),
            $field('rx-aq-question', lang('Question'), $textarea('rx-aq-question', 'question', 5, true), 'mb-3',
                lang('What you would like to know, who it is for and since when, if it is about a complaint.')),
            $field('rx-aq-taken', lang('Medicines already taken'), $textarea('rx-aq-taken', 'medicines_taken', 3, false), 'mb-3',
                lang('Every medicine, vitamin or remedy taken now, so the answer is safe with them.')),
            $form_step(2, lang('How to reach you')),
            $contact_fields('rx-aq'),
            $radios('rx-aq-reply', 'reply_by', lang('Reply by'), $contact_choices, $contact_choices[0][0]),
            $field('rx-aq-status', lang('Status'), $select('rx-aq-status', 'status', '', $question_statuses, false, $question_statuses[0], array('office_use_only' => true))),
            $agree('rx-aq-consent', 'consent', lang('I agree that the pharmacy keeps my question and what I tell it about my health to answer me, as its privacy notice explains.'), true),
            $captcha(),
            $el('div', 'd-flex flex-wrap align-items-center gap-3', lang('Send Area'), array(
                $submit(lang('Send the Question'), 'btn btn-primary btn-lg rounded-pill px-4'),
                $para(lang('Only you and our pharmacists can read it.'), 'small text-body-secondary mb-0'),
            )),
            $loop(array()),
        )),
    ),

    'question_detail' => $detail_widget('ask_view', 'ask', $question_missing, 'submitter_only') + array(
        'tree' => $request_detail(lang('My Questions'), 'my_questions', $question_missing, lang('Question not found'),
            lang('Question to the Pharmacist'), $sample_topic, 'topic', $question_statuses[0],
            lang('Thank you, your question has reached our pharmacists. The answer comes below, usually the same day, and by e-mail.'),
            array_merge(
                $detail(lang('Medicines already taken'), '', lang('Medicines already taken'), $bound($sample_taken, 'medicines_taken')),
                $detail(lang('Reply by'), '', lang('Reply by'), $bound($contact_choices[0][1], 'reply_by')),
                $detail(lang('Name'), '', lang('Name'), $sender_name(), true),
                $detail(lang('Reference Code'), 'mb-0 font-monospace', lang('Reference Code'), $bound('ABC123', 'reference_code'))
            ),
            lang('Question'), $sample_question, 'question',
            lang('Only you and our pharmacists can read this page. Ask more below; we answer here and by e-mail.')),
    ),

    'question_messages' => $request_messages('ask_view',
        lang('No answer yet. A pharmacist answers here, usually the same day.'), lang('Ask more')),

    'my_questions_list' => $request_list('my_questions', 'ask', 'ask_view', $question_row, array(), '',
        lang('No questions yet. Ask a pharmacist about a medicine or a health worry.')),

    'staff_questions_list' => $request_list('staff_questions', 'ask', 'ask_view', $question_row,
        array('first_name', 'last_name', 'email', 'topic', 'question', 'status'),
        lang('Search by name, e-mail, topic, question or status...'), lang('No questions yet.')),

    // The e-mail pages' widgets: drawn for the submission just sent.
    'email_prescription_admin' => $detail_widget('email_prescription_new', 'prescription', lang('The prescription request could not be found.'), 'public') + array(
        'tree' => $widget_root(array(
            $mail_heading(lang('A new prescription request')),
            $mail_para(lang('It came from the prescription form. Check the stock, write to the customer under the request on its page and set the status; what you write there reaches the customer by e-mail.')),
            $mail_prescription(),
            $mail_sender($sample_notes),
            $mail_button(lang('Open the request'), '{{page:prescription_view}}?r=^^reference_code^^'),
            $mail_reference(),
        )),
    ),
    'email_prescription_client' => $detail_widget('email_prescription_received', 'prescription', lang('The prescription request could not be found.'), 'public') + array(
        'tree' => $widget_root(array(
            $mail_heading(lang('We received your prescription request')),
            $mail_greeting(),
            $mail_para(lang('Thank you. We prepare your medicines and tell you on the request\'s page and by e-mail when they are ready.')),
            $mail_prescription(),
            $mail_para(lang('Bring the original prescription: we hand the medicines over only when it is shown, at the counter or at the door.')),
            $mail_button(lang('Open my request'), '{{page:prescription_view}}?r=^^reference_code^^'),
            $mail_new_account(),
        )),
    ),
    'email_question_admin' => $detail_widget('email_question_new', 'ask', lang('The question could not be found.'), 'public') + array(
        'tree' => $widget_root(array(
            $mail_heading(lang('A new question to the pharmacist')),
            $mail_para(lang('It came from the question form. Answer under the question on its page and set the status; what you write there reaches the customer by e-mail.')),
            $mail_question(),
            $mail_sender(''),
            $mail_button(lang('Open the question'), '{{page:ask_view}}?r=^^reference_code^^'),
            $mail_reference(),
        )),
    ),
    'email_question_client' => $detail_widget('email_question_received', 'ask', lang('The question could not be found.'), 'public') + array(
        'tree' => $widget_root(array(
            $mail_heading(lang('We received your question')),
            $mail_greeting(),
            $mail_para(lang('Thank you. A pharmacist answers on the question\'s page, usually the same day, and you get the answer by e-mail.')),
            $mail_question(),
            $mail_para(lang('In an emergency do not wait for the answer: call the emergency number at once.')),
            $mail_button(lang('Open my question'), '{{page:ask_view}}?r=^^reference_code^^'),
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

return array('pages' => $request_pages, 'widgets' => $request_widgets);
