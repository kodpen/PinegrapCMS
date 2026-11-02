<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Law Firm", its consultation and careers: the
 * consultation request, the consultation's own page with its messages, My
 * Consultations and the staff's list, the privacy page the consent boxes
 * link, the careers page with the application form, the application's page
 * and the staff's list, their e-mails and the widgets that draw them.
 * Included after parts.php and mail.php in the same scope; returns
 * array('pages' => ..., 'widgets' => ...).
 *
 * A consultation request is a record of the Consultations form, as a
 * booking is in the Salon and Clinic templates. A visitor without an
 * account gets one (auto-registration), so the consultation's page and My
 * Consultations show it at once. Its Status is an office-use-only field the
 * staff change in the control panel (Received, Conflict check, Scheduled,
 * Engaged, Declined); the messages under it are the page's comments, mailed
 * to the client and to the firm. The form asks for the other party, so the
 * firm can check for a conflict of interest before it hears the matter.
 *
 * Documents and CVs are kept in the template's private folder: the record's
 * page links them for whoever may read the record.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

$consultation_pages = array();

// The open positions of the careers page; the application form lists their
// titles, and each card opens it with the position chosen (?position=).
$positions = array(
    array('title' => lang('Associate Lawyer, Corporate & Commercial'), 'type' => lang('Full time · Istanbul'),
          'text'  => lang('Two to five years in company law and commercial contracts; English for drafting and negotiation.')),
    array('title' => lang('Legal Intern'), 'type' => lang('Six months · Istanbul'),
          'text'  => lang('For law graduates in their bar internship: research, drafting and hearings beside a partner.')),
    array('title' => lang('Legal Assistant'), 'type' => lang('Full time · Istanbul'),
          'text'  => lang('Files, deadlines and the diary of two lawyers; experience in a law office is a plus.')),
);
$position_titles = array();
foreach ($positions as $p) $position_titles[] = $p['title'];
$position_titles[] = lang('Open application');

// The statuses, in the order a matter moves through them.
$consultation_statuses = array(lang('Received'), lang('Conflict check'), lang('Consultation scheduled'), lang('Engaged'), lang('Declined'));
$application_statuses = array(lang('Received'), lang('Invited to interview'), lang('Offer made'), lang('Not taken forward'));

// ── Pages ───────────────────────────────────────────────────────────────

// The consultation request, with what happens next beside it. What is sent
// opens the consultation's own page.
$consultation_pages[] = array(
    'key'              => 'consultation',
    'name'             => lang('book-a-consultation'),
    'folder'           => 'public',
    'title'            => lang('Book a Consultation'),
    'meta_description' => lang('Tell us about your matter: choose the practice area and a lawyer, attach your documents; we reply within one working day.'),
    'form'             => array(
        'form_name'            => lang('Consultations'),
        'confirmation_message' => lang('Thank you, your request has reached us. A lawyer replies within one working day.'),
        'confirmation_page_id' => '{{tab:consultation_view}}',
        'auto_registration'    => 1,
        'notify_email'         => '{{site_email}}',
        'notify_subject'       => lang('New consultation request: ^^practice_area^^'),
        'notify_page_id'       => '{{tab:email_consultation_new}}',
        'confirm_email'        => 1,
        'confirm_subject'      => lang('We received your consultation request'),
        'confirm_page_id'      => '{{tab:email_consultation_received}}',
    ),
    'tree'             => $page(array(
        $page_header(lang('Book a Consultation'), lang('Tell us about your matter in a few lines. A lawyer reads it and replies within one working day with a time for the first consultation.')),
        $el('section', 'py-5', lang('Content'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $el('div', 'card border-0 shadow-sm rounded-3', lang('Form Card'), array(
                            $el('div', 'card-body p-4 p-lg-5', lang('Card Body'), array($widget('consultation_form'))),
                        )),
                    ), '', '8'),
                    $col(array(
                        $el('div', 'p-4 rounded-3 bg-body-tertiary mb-4', lang('How It Works'), array(
                            $heading('h2', lang('What happens next'), 'h5 fw-semibold mb-3'),
                            $el('ol', 'small text-body-secondary ps-3 mb-0', lang('List'), array(
                                $el('li', 'mb-2', lang('Step'), array(), array('text' => lang('Your request opens its own page, and an account for you if you have none.'))),
                                $el('li', 'mb-2', lang('Step'), array(), array('text' => lang('We check for a conflict of interest, then propose a time for the first consultation.'))),
                                $el('li', '', lang('Step'), array(), array('text' => lang('Questions or more documents? Write under your request.'))),
                            )),
                        )),
                        $el('div', 'p-4 rounded-3 border mb-4', lang('Confidentiality'), array(
                            $el('p', 'd-flex align-items-center gap-2 fw-semibold mb-2', lang('Title'), array(
                                $icon('bi-lock', 'text-primary'),
                                $span(lang('Confidential'), '', lang('Text')),
                            )),
                            $para(lang('Only the firm\'s lawyers and staff read what you send, and it stays under professional secrecy whether or not we take the matter.'), 'small text-body-secondary mb-0'),
                        )),
                        $el('div', 'p-4 rounded-3 border', lang('Office hours'), array(
                            $heading('h2', lang('Office hours'), 'h5 fw-semibold mb-3'),
                            $hours(),
                        )),
                    ), '', '4'),
                ), '4', '', 'g-lg-5'),
            )),
        )),
    ), false),
);

// One consultation: the request, its status and the messages under it.
$consultation_pages[] = array(
    'key'      => 'consultation_view',
    'name'     => lang('consultation'),
    'folder'   => 'registration',
    'title'    => lang('Consultation'),
    'search'   => false,
    'sitemap'  => false,
    'comments' => array('label' => lang('Message'), 'allow_new' => true, 'auto_publish' => true, 'show_date' => true, 'login' => true,
                        'attachments' => true,
                        'email_page' => '{{tab:email_reply}}', 'email_subject' => lang('There is a new message about your consultation'),
                        'notify_email' => '{{site_email}}'),
    'tree'     => $page(array(
        $widget('consultation_detail'),
        $comments_section(lang('Messages'), 'consultation_messages'),
    ), false),
);

// My Consultations: the client's requests (for the staff, everybody's).
$consultation_pages[] = array(
    'key'     => 'my_consultations',
    'name'    => lang('my-consultations'),
    'folder'  => 'registration',
    'title'   => lang('My Consultations'),
    'search'  => false,
    'sitemap' => false,
    'tree'    => $page(array(
        $page_header(lang('My Consultations'), lang('Your requests, their status and your lawyer\'s messages, newest first.')),
        $el('section', 'py-5', lang('Content'), array(
            $container(array(
                $row(array(
                    $col(array($widget('my_consultations_list')), '', '8'),
                    $col(array(
                        $el('div', 'p-4 rounded-3 bg-body-tertiary', lang('New Matter'), array(
                            $el('p', 'd-flex align-items-center gap-2 fw-semibold mb-2', lang('Title'), array(
                                $icon('bi-plus-circle', 'text-primary'),
                                $span(lang('A new matter?'), '', lang('Text')),
                            )),
                            $para(lang('Send a new request; the details you gave last time are filled in for you.'), 'small text-body-secondary mb-3'),
                            $link(lang('Book a Consultation'), '{{page:consultation}}', 'btn btn-sm btn-primary px-3'),
                        )),
                    ), '', '4'),
                ), '4', '', 'g-lg-5'),
            )),
        )),
    ), false),
);

// The staff's list of every request, newest first, with a search.
$consultation_pages[] = array(
    'key'     => 'staff_consultations',
    'name'    => lang('all-consultations'),
    'folder'  => 'private',
    'title'   => lang('All Consultations'),
    'search'  => false,
    'sitemap' => false,
    'tree'    => $staff_page(array(
        $page_header(lang('All Consultations'), lang('Every request, newest first: check for a conflict, set the status and answer under each.')),
        $el('section', 'py-5', lang('Content'), array(
            $container(array($widget('staff_consultations_list'))),
        )),
    )),
);

// The privacy notice the consent boxes link to: sample text with a note to
// adapt it.
$consultation_pages[] = array(
    'key'              => 'privacy',
    'name'             => lang('privacy-policy'),
    'folder'           => 'public',
    'title'            => lang('Privacy Policy'),
    'meta_description' => lang('What the firm keeps about you, why, and what you can ask of us.'),
    'tree'             => $page(array(
        $page_header(lang('Privacy Policy'), lang('What the firm keeps about you, why, and what you can ask of us.')),
        $el('section', 'py-5', lang('Your Data'), array(
            $container(array($row(array($col(array(
                $el('div', 'alert alert-secondary d-flex gap-2 small', lang('Sample Text Note'), array(
                    $icon('bi-info-circle', 'mt-1'),
                    $span(lang('Sample text: adapt it to your firm, to the bar\'s rules and to the data protection law that applies to you before you publish.'), '', lang('Text')),
                ), array('_attrs' => array($attr('role', 'note')))),
                $heading('h2', lang('Your data'), 'h4 fw-semibold mb-3'),
                $para(lang('When you ask for a consultation, apply for a position or open an account, we keep your name, your contact details, what you tell us about your matter and the documents you send, to advise you and to check for conflicts of interest.'), 'mb-3'),
                $para(lang('What you tell us is kept under professional secrecy. We keep it as long as the law and the bar\'s rules require, never sell it and never give it to advertisers. You can see and change your account data from My Account, and ask us about the rest through the contact page.'), 'mb-0'),
            ), '', '8'))))),
        ), $searchable('your-data', lang('privacy, personal data, KVKK, GDPR, delete my data'))),
    )),
);

// Careers: the open positions and the way to apply.
$position_cards = array();
foreach ($positions as $p) {
    $position_cards[] = $col(array(
        $el('article', 'card h-100 border-0 shadow-sm rounded-3', $p['title'], array(
            $el('div', 'card-body d-flex flex-column p-4', lang('Card Body'), array(
                $span($p['type'], 'small text-uppercase fw-semibold text-primary mb-2', lang('Type')),
                $heading('h2', $p['title'], 'h5 fw-semibold mb-2'),
                $para($p['text'], 'small text-body-secondary mb-3'),
                $el('a', 'btn btn-sm btn-outline-primary align-self-start mt-auto', lang('Link'), array(
                    $span(lang('Apply'), '', lang('Link Text')),
                    $span($p['title'], 'visually-hidden', lang('Name')),
                ), array('href' => '{{page:application}}?' . $consultation_query(array('position' => $p['title'])))),
            )),
        )),
    ), '6', '4');
}
$consultation_pages[] = array(
    'key'              => 'careers',
    'name'             => lang('careers'),
    'folder'           => 'public',
    'title'            => lang('Careers'),
    'meta_description' => lang('Work with us: the open positions at the firm and how to apply.'),
    'tree'             => $page(array(
        $page_header(lang('Careers'), lang('A small firm where juniors work beside partners from the first day. See the open positions, or send an open application.')),
        $el('section', 'py-5', lang('Open Positions'), array(
            $container(array(
                $row($position_cards, '4'),
                $el('div', 'd-flex flex-wrap align-items-center gap-3 mt-4', lang('Open Application'), array(
                    $para(lang('No position for you here? We read every open application.'), 'text-body-secondary mb-0'),
                    $link(lang('Send an open application'), '{{page:application}}?' . $consultation_query(array('position' => lang('Open application'))), 'btn btn-primary px-4'),
                )),
            )),
        ), $searchable('open-positions', lang('jobs, career, vacancy, internship, apply'))),
    )),
);

// The application form.
$consultation_pages[] = array(
    'key'              => 'application',
    'name'             => lang('apply'),
    'folder'           => 'public',
    'title'            => lang('Apply'),
    'meta_description' => lang('Apply for a position at the firm: your CV and a few words about you.'),
    'form'             => array(
        'form_name'            => lang('Applications'),
        'confirmation_message' => lang('Thank you, your application has reached us. We answer every application within two weeks.'),
        'confirmation_page_id' => '{{tab:application_view}}',
        'auto_registration'    => 1,
        'notify_email'         => '{{site_email}}',
        'notify_subject'       => lang('New application: ^^position^^'),
        'notify_page_id'       => '{{tab:email_application_new}}',
        'confirm_email'        => 1,
        'confirm_subject'      => lang('We received your application'),
        'confirm_page_id'      => '{{tab:email_application_received}}',
    ),
    'tree'             => $page(array(
        $page_header(lang('Apply'), lang('Your CV and a few words about you. We answer every application within two weeks.')),
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

// One application: what was sent and its status.
$consultation_pages[] = array(
    'key'     => 'application_view',
    'name'    => lang('application'),
    'folder'  => 'registration',
    'title'   => lang('Application'),
    'search'  => false,
    'sitemap' => false,
    'tree'    => $page(array($widget('application_detail')), false),
);

// The staff's list of applications, newest first.
$consultation_pages[] = array(
    'key'     => 'staff_applications',
    'name'    => lang('applications'),
    'folder'  => 'private',
    'title'   => lang('Applications'),
    'search'  => false,
    'sitemap' => false,
    'tree'    => $staff_page(array(
        $page_header(lang('Applications'), lang('Every application, newest first, with its position and status.')),
        $el('section', 'py-5', lang('Content'), array(
            $container(array($widget('staff_applications_list'))),
        )),
    )),
);

// The e-mails: what the staff receive and the reply to the sender, for each
// form, and the new message e-mail.
$consultation_pages[] = array_merge($mail_settings, array(
    'key'   => 'email_consultation_new',
    'name'  => lang('email-new-consultation'),
    'title' => lang('New Consultation E-mail'),
    'tree'  => $mail_page(array($widget('email_consultation_admin'))),
));
$consultation_pages[] = array_merge($mail_settings, array(
    'key'   => 'email_consultation_received',
    'name'  => lang('email-consultation-received'),
    'title' => lang('Consultation Received E-mail'),
    'tree'  => $mail_page(array($widget('email_consultation_client'))),
));
$consultation_pages[] = array_merge($mail_settings, array(
    'key'   => 'email_application_new',
    'name'  => lang('email-new-application'),
    'title' => lang('New Application E-mail'),
    'tree'  => $mail_page(array($widget('email_application_admin'))),
));
$consultation_pages[] = array_merge($mail_settings, array(
    'key'   => 'email_application_received',
    'name'  => lang('email-application-received'),
    'title' => lang('Application Received E-mail'),
    'tree'  => $mail_page(array($widget('email_application_client'))),
));
// A new message or reply: sent to the client of a consultation, the sender
// of a conversation and the visitors watching a page. Its Comments widget
// draws the comment in the card of mail.php; the System region after it
// keeps the fixed comment for an e-mail the widget does not draw.
$consultation_pages[] = array_merge($mail_settings, array(
    'key'   => 'email_reply',
    'name'  => lang('email-new-reply'),
    'title' => lang('New Reply E-mail'),
    'tree'  => $mail_page(array(
        $mail_heading(lang('There is a new message')),
        $mail_para(lang('Something new was written on a page you follow: your consultation, a conversation or an insight. You can read it below and answer it on the page.')),
        $widget('email_comment'),
        array('type' => 'region', 'props' => array('regionType' => 'system', 'regionName' => ''), 'children' => array()),
    )),
));

// ── Widget pieces ───────────────────────────────────────────────────────

// The client's or applicant's name.
$person_name = function () use ($span, $bind) {
    return array(
        $span(lang('Jane'), '', lang('First Name'), $bind('first_name')),
        $span(' ', '', lang('Text')),
        $span(lang('Cooper'), '', lang('Last Name'), $bind('last_name')),
    );
};

// A consultation in a list: the area, the status, the lawyer, the contact
// way, the date sent and the number of messages, linked to its page.
$consultation_row = function ($staff = false) use ($el, $span, $icon, $bind, $person_name) {
    $details = array(
        $el('span', 'd-inline-flex align-items-center gap-1', lang('Lawyer'), array($icon('bi-person'), $span(lang('No preference'), '', lang('Lawyer'), $bind('lawyer')))),
        $el('span', 'd-inline-flex align-items-center gap-1', lang('Sent'), array($icon('bi-calendar3'), $span(lang('Date'), '', lang('Date'), $bind('submitted_date_and_time')))),
    );
    if ($staff) {
        $details[] = $el('span', 'd-inline-flex align-items-center gap-1', lang('Other Party'), array($icon('bi-people'), $span(lang('Sample Holding Inc.'), '', lang('Other Party'), $bind('other_party'))));
        $details[] = $el('span', 'd-inline-flex align-items-center gap-1', lang('Phone'), array($icon('bi-telephone'), $span('+90 555 000 00 00', '', lang('Phone'), $bind('phone'))));
        $title = $el('span', 'fw-semibold', lang('Client'), array_merge($person_name(), array(
            $span(' · ', 'text-body-secondary', lang('Text')),
            $span(lang('Employment'), 'text-body-secondary fw-normal', lang('Practice Area'), $bind('practice_area')),
        )));
    } else {
        $details[] = $el('span', 'd-inline-flex align-items-center gap-1', lang('Messages'), array($icon('bi-chat-left-text'), $span('0', '', lang('Reply Count'), $bind('number_of_comments'))));
        $title = $span(lang('Employment'), 'fw-semibold', lang('Practice Area'), $bind('practice_area'));
    }
    return $el('a', 'list-group-item list-group-item-action p-3', lang('Consultation'), array(
        $el('div', 'd-flex flex-wrap align-items-center justify-content-between gap-2 mb-1', lang('Top Line'), array(
            $title,
            $span(lang('Received'), 'badge rounded-pill bg-primary-subtle text-primary-emphasis', lang('Status'), $bind('status')),
        )),
        $el('div', 'd-flex flex-wrap column-gap-3 row-gap-1 small text-body-secondary', lang('Details'), $details),
    ), array('href' => '#', '_bindings' => array('href' => 'form_item_view')));
};

// An application in the staff's list.
$application_row = function () use ($el, $span, $icon, $bind, $person_name) {
    return $el('a', 'list-group-item list-group-item-action p-3', lang('Application'), array(
        $el('div', 'd-flex flex-wrap align-items-center justify-content-between gap-2 mb-1', lang('Top Line'), array(
            $el('span', 'fw-semibold', lang('Applicant'), array_merge($person_name(), array(
                $span(' · ', 'text-body-secondary', lang('Text')),
                $span(lang('Legal Intern'), 'text-body-secondary fw-normal', lang('Position'), $bind('position')),
            ))),
            $span(lang('Received'), 'badge rounded-pill bg-primary-subtle text-primary-emphasis', lang('Status'), $bind('status')),
        )),
        $el('div', 'd-flex flex-wrap column-gap-3 row-gap-1 small text-body-secondary', lang('Details'), array(
            $el('span', 'd-inline-flex align-items-center gap-1', lang('Sent'), array($icon('bi-calendar3'), $span(lang('Date'), '', lang('Date'), $bind('submitted_date_and_time')))),
            $el('span', 'd-inline-flex align-items-center gap-1', lang('Email'), array($icon('bi-envelope'), $span('jane.cooper@example.com', '', lang('Email'), $bind('email')))),
        )),
    ), array('href' => '#', '_bindings' => array('href' => 'form_item_view')));
};

// A detail of a record's card: the label, then the value. $value is either
// the value's props (its sample text and binding) or, with $children, the
// nodes it is made of.
$detail = function ($label, $class, $name, $value, $children = false) use ($el) {
    return array(
        $el('dt', 'col-sm-4 fw-normal text-body-secondary', lang('Label'), array(), array('text' => $label)),
        $el('dd', trim('col-sm-8 ' . $class), $name, $children ? $value : array(), $children ? array() : $value),
    );
};
// A file the record carries, as a link that opens it.
$file_link = function ($field, $sample) use ($link) {
    return array($link($sample, '#', '', array(), array('target' => '_blank', 'rel' => 'noopener',
        '_bindings' => array('href' => $field, 'text' => $field))));
};

// A record's page (a consultation, an application): the not-found notice,
// the sent notice, the new account notice, the way back and the Edit
// button, then the record's own parts.
$record_view = function ($icon_name, $missing_title, $missing_text, $back_key, $back_text, $sent_text, $parts) use ($el, $heading, $para, $link, $span, $icon, $attr, $bind, $show_when, $container, $row, $col, $edit_button) {
    return array('type' => 'root', 'props' => array(), 'children' => array(
        $container(array(
            $row(array(
                $col(array(
                    $el('div', 'text-center py-5', lang('Not Found'), array(
                        $icon($icon_name, 'display-5 text-body-tertiary'),
                        $heading('h2', $missing_title, 'h3 fw-semibold mt-3'),
                        $para($missing_text, 'text-body-secondary mb-4', $bind('not_found')),
                        $link($back_text, '{{page:' . $back_key . '}}', 'btn btn-primary px-4'),
                    ), $show_when('record_not_found')),
                    $el('div', '', lang('Record'), array_merge(array(
                        $el('div', 'alert alert-success d-flex gap-2', lang('Sent Notice'), array(
                            $icon('bi-check-circle', 'mt-1'),
                            $span($sent_text, '', lang('Text')),
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
                    ), $parts), $show_when('record_found')),
                ), '', '8'),
            ), '', 'center'),
        ), 'py-5'),
    ));
};

// The heading of a record's card: the kind, the title, the status.
$record_title = function ($kind, $sample, $field) use ($el, $heading, $para, $span, $bind) {
    return $el('div', 'd-flex flex-wrap align-items-start justify-content-between gap-2 mb-3', lang('Title'), array(
        $el('div', '', lang('Words'), array(
            $para($kind, 'small text-uppercase fw-semibold text-primary mb-1'),
            $heading('h1', $sample, 'h3 fw-semibold mb-0', $bind($field)),
        )),
        $span(lang('Received'), 'badge rounded-pill bg-primary-subtle text-primary-emphasis fs-6', lang('Status'), $bind('status')),
    ));
};
// A long text the record carries, in a soft box that leaves when empty.
$record_text = function ($label, $field, $sample) use ($el, $para, $bind) {
    return $el('div', 'pg-hide-if-empty p-4 rounded-3 bg-body-tertiary mb-4', $label, array(
        $para($label, 'small fw-semibold text-body-secondary mb-2'),
        $el('div', 'pg-field-value lh-lg', lang('Value'), array(), array_merge(array('text' => $sample), $bind($field))),
    ));
};

// The lines of a consultation, for both e-mails.
$mail_consultation = function () use ($mail_box, $mail_line, $span, $bind) {
    return $mail_box(array(
        $mail_line(lang('Practice Area'), array($span(lang('Employment'), '', lang('Practice Area'), $bind('practice_area')))),
        $mail_line(lang('Lawyer'), array($span(lang('No preference'), '', lang('Lawyer'), $bind('lawyer')))),
        $mail_line(lang('Contact by'), array($span(lang('Video call'), '', lang('Contact by'), $bind('contact_method')))),
        $mail_line(lang('Preferred Time'), array($span(lang('Morning (09:00–12:00)'), '', lang('Time'), $bind('preferred_time')))),
    ), lang('Consultation'));
};
$sample_summary = lang('My employer ended my contract after eight years without notice. I would like to know what I can claim and how long it takes.');
$sample_letter = lang('I finished my bar internship in a commercial firm and would like to grow in company law beside an experienced team.');

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
    ), array_merge($mail_style('border-top:1px solid #dbeafe;padding-top:16px'), $show_when('has_new_account')));
};
// The form detail settings of an e-mail page's widget.
$mail_detail = function ($page, $form, $missing) {
    return array(
        'page'   => $page,
        'slug'   => lang('form-detail'),
        'config' => array(
            'regionType'          => 'form_item_view',
            'custom_form_page_id' => '{{tab:' . $form . '}}',
            'access_control'      => 'public',
            'not_found_message'   => $missing,
        ),
    );
};

$consultation_widgets = array(

    // The consultation request: the fields are the page's form. The Status
    // is the staff's (office use only): a visitor never sees it, and every
    // request starts as Received.
    'consultation_form' => array(
        'page'   => 'consultation',
        'slug'   => 'form',
        'config' => array('regionType' => 'custom_form', 'form_source' => 'page'),
        'tree'   => $widget_root(array(
            $form_step(1, lang('Your matter')),
            $row(array(
                $col(array($field('lw-con-area', lang('Practice Area'), $select('lw-con-area', 'practice_area', lang('Choose an area…'), $area_titles, true, '', array('url_param' => 'area')))), '6'),
                $col(array($field('lw-con-lawyer', lang('Lawyer'), $select('lw-con-lawyer', 'lawyer', '', $lawyer_choices, true, lang('No preference'), array('url_param' => 'lawyer')))), '6'),
            ), '3'),
            $radios('lw-con-client', 'client_type', lang('You are asking as'), array(
                array(lang('A private person'), lang('A private person')),
                array(lang('A company'), lang('A company')),
            ), lang('A private person')),
            $field('lw-con-company', lang('Company name'), $input('lw-con-company', 'text', 'company', 'organization', false), 'mb-3',
                lang('Only when you ask for a company.')),
            $field('lw-con-other', lang('The other party'), $input('lw-con-other', 'text', 'other_party', 'off', false), 'mb-3',
                lang('The person or company on the other side, so we can check that we do not already act for them.')),
            $field('lw-con-summary', lang('What happened?'), $textarea('lw-con-summary', 'summary', 6, true, array('url_param' => 'summary')), 'mb-3',
                lang('A few lines are enough: what happened, what you would like and any deadline you know of.')),
            $field('lw-con-deadline', lang('Deadline, if any'), $input('lw-con-deadline', 'date', 'deadline', 'off', false, array('rules' => array('min_date' => 'today')))),
            $field('lw-con-documents', lang('Documents'), $file('lw-con-documents', 'documents', 'private'), 'mb-3',
                lang('A contract, a letter or a court paper (PDF, Word or a photo). More can follow under your request.')),
            $form_step(2, lang('How to reach you')),
            $radios('lw-con-contact', 'contact_method', lang('First consultation by'), $contact_choices, lang('Video call')),
            $field('lw-con-time', lang('Preferred Time'), $select('lw-con-time', 'preferred_time', lang('Choose a time of day…'), $time_windows)),
            $row(array(
                $col(array($field('lw-con-first-name', lang('First Name'), $input('lw-con-first-name', 'text', 'first_name', 'given-name', true, array('contact_field' => 'first_name')))), '6'),
                $col(array($field('lw-con-last-name', lang('Last Name'), $input('lw-con-last-name', 'text', 'last_name', 'family-name', true, array('contact_field' => 'last_name')))), '6'),
                $col(array($field('lw-con-email', lang('Email'), $input('lw-con-email', 'email', 'email', 'email', true, array('contact_field' => 'email_address')))), '6'),
                $col(array($field('lw-con-phone', lang('Phone'), $input('lw-con-phone', 'tel', 'phone', 'tel', true, array('contact_field' => 'mobile_phone')))), '6'),
            ), '3'),
            $field('lw-con-status', lang('Status'), $select('lw-con-status', 'status', '', $consultation_statuses, false, lang('Received'), array('office_use_only' => true))),
            $agree('lw-con-consent', 'consent', lang('I agree that the firm keeps these details to answer my request and check for conflicts, as its privacy notice explains.'), true),
            $captcha(),
            $el('div', 'd-flex flex-wrap align-items-center gap-3', lang('Send Area'), array(
                $submit(lang('Send Request'), 'btn btn-primary btn-lg px-4'),
                $para(lang('A lawyer replies within one working day.'), 'small text-body-secondary mb-0'),
            )),
            $loop(array()),
        )),
    ),

    // One consultation: the request, its status and what the client wrote;
    // the messages follow as comments.
    'consultation_detail' => array(
        'page'   => 'consultation_view',
        'slug'   => lang('form-detail'),
        'config' => array(
            'regionType'          => 'form_item_view',
            'custom_form_page_id' => '{{tab:consultation}}',
            'access_control'      => 'submitter_only',
            'not_found_message'   => lang('This consultation could not be found, or it is not yours to see.'),
        ),
        'tree'   => $record_view('bi-folder-x', lang('Consultation not found'), lang('This consultation could not be found, or it is not yours to see.'),
            'my_consultations', lang('My Consultations'),
            lang('Thank you, your request has reached us. A lawyer replies within one working day; this page shows the status, and you can write to us below.'),
            array(
                $el('div', 'card border-0 shadow-sm rounded-3 mb-4', lang('Consultation Card'), array(
                    $el('div', 'card-body p-4', lang('Card Body'), array(
                        $record_title(lang('Consultation'), lang('Employment'), 'practice_area'),
                        $el('dl', 'row small mb-0', lang('Details'), array_merge(
                            $detail(lang('Lawyer'), '', lang('Lawyer'), array_merge(array('text' => lang('No preference')), $bind('lawyer'))),
                            $detail(lang('The other party'), '', lang('Other Party'), array_merge(array('text' => lang('Sample Holding Inc.')), $bind('other_party'))),
                            $detail(lang('Deadline, if any'), '', lang('Deadline'), array_merge(array('text' => '2026-12-01'), $bind('deadline'))),
                            $detail(lang('First consultation by'), '', lang('Contact by'), array_merge(array('text' => lang('Video call')), $bind('contact_method'))),
                            $detail(lang('Preferred Time'), '', lang('Time'), array_merge(array('text' => lang('Morning (09:00–12:00)')), $bind('preferred_time'))),
                            $detail(lang('Client'), '', lang('Client'), $person_name(), true),
                            $detail(lang('Documents'), 'text-break', lang('Documents'), $file_link('documents', 'contract.pdf'), true),
                            $detail(lang('Reference Code'), 'mb-0 font-monospace', lang('Reference Code'), array_merge(array('text' => 'ABC123'), $bind('reference_code')))
                        )),
                    )),
                )),
                $record_text(lang('What happened?'), 'summary', $sample_summary),
                $el('div', 'd-flex gap-2 small text-body-secondary', lang('Note'), array(
                    $icon('bi-chat-left-text', 'text-primary mt-1'),
                    $span(lang('More documents or a question? Write to your lawyer below; we answer here and by e-mail.'), '', lang('Text')),
                )),
            )),
    ),

    // The messages about a consultation: the page's own Comments widget,
    // shown only to whoever may read the consultation above it. Oldest
    // first, all on one page, with a file each. No Reply, Share or follow
    // box: the client is e-mailed every answer and the firm every message.
    'consultation_messages' => array(
        'page'   => 'consultation_view',
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
            'empty'     => lang('No messages yet. Write to your lawyer here about your matter.'),
            'heading'   => lang('Write to your lawyer'),
            'label'     => lang('Your message'),
            'sign_in'   => lang('Sign in to write to your lawyer.'),
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

    // My Consultations: the requests the client sent; the staff see them
    // all. Newest first.
    'my_consultations_list' => array(
        'page'   => 'my_consultations',
        'slug'   => lang('form-list'),
        'config' => array(
            'regionType'              => 'form_list_view',
            'custom_form_page_id'     => '{{tab:consultation}}',
            'detail_page_id'          => '{{tab:consultation_view}}',
            'items_per_page'          => 20,
            'order_by_field'          => 'submitted_date_and_time',
            'order_by_direction'      => 'DESC',
            'viewer_filter'           => true,
            'viewer_filter_submitter' => true,
            'viewer_filter_watcher'   => true,
            'viewer_filter_editor'    => true,
            'empty_message'           => lang('No requests yet. Tell us about your matter in a few lines.'),
        ),
        'tree'   => $widget_root(array(
            $el('div', 'list-group', lang('Consultation List'), array(
                $loop(array($consultation_row())),
            )),
        )),
    ),

    // The staff's list: every request, newest first, searchable.
    'staff_consultations_list' => array(
        'page'   => 'staff_consultations',
        'slug'   => lang('form-list'),
        'config' => array(
            'regionType'          => 'form_list_view',
            'custom_form_page_id' => '{{tab:consultation}}',
            'detail_page_id'      => '{{tab:consultation_view}}',
            'items_per_page'      => 20,
            'order_by_field'      => 'submitted_date_and_time',
            'order_by_direction'  => 'DESC',
            'search_enabled'      => true,
            'search_fields'       => array('first_name', 'last_name', 'company', 'other_party', 'practice_area', 'lawyer', 'status'),
            'search_width'        => 'half',
            'search_label'        => lang('Search by client, other party, area, lawyer or status...'),
            'empty_message'       => lang('No consultation requests yet.'),
        ),
        'tree'   => $widget_root(array(
            $el('div', 'list-group', lang('Consultation List'), array(
                $loop(array($consultation_row(true))),
            )),
        )),
    ),

    // The application: the fields are the page's form.
    'application_form' => array(
        'page'   => 'application',
        'slug'   => 'form',
        'config' => array('regionType' => 'custom_form', 'form_source' => 'page'),
        'tree'   => $widget_root(array(
            $field('lw-app-position', lang('Position'), $select('lw-app-position', 'position', lang('Choose a position…'), $position_titles, true, '', array('url_param' => 'position'))),
            $row(array(
                $col(array($field('lw-app-first-name', lang('First Name'), $input('lw-app-first-name', 'text', 'first_name', 'given-name', true, array('contact_field' => 'first_name')))), '6'),
                $col(array($field('lw-app-last-name', lang('Last Name'), $input('lw-app-last-name', 'text', 'last_name', 'family-name', true, array('contact_field' => 'last_name')))), '6'),
                $col(array($field('lw-app-email', lang('Email'), $input('lw-app-email', 'email', 'email', 'email', true, array('contact_field' => 'email_address')))), '6'),
                $col(array($field('lw-app-phone', lang('Phone'), $input('lw-app-phone', 'tel', 'phone', 'tel', true, array('contact_field' => 'mobile_phone')))), '6'),
            ), '3'),
            $field('lw-app-profile', lang('Profile link'), $input('lw-app-profile', 'url', 'profile_link', 'url', false), 'mb-3',
                lang('LinkedIn or another page about your work, if you have one.')),
            $field('lw-app-cv', lang('CV'), $el('input', 'form-control', '', array(), array('id' => 'lw-app-cv', '_attrs' => array(
                $attr('type', 'file'), $attr('name', 'cv'), $attr('accept', '.pdf,.doc,.docx,.odt'), $attr('required', ''),
            ), '_cf' => array('upload_folder_id' => '{{folder:private}}'))), 'mb-3', lang('PDF or Word.')),
            $field('lw-app-letter', lang('A few words about you'), $textarea('lw-app-letter', 'cover_letter', 6)),
            $field('lw-app-status', lang('Status'), $select('lw-app-status', 'status', '', $application_statuses, false, lang('Received'), array('office_use_only' => true))),
            $agree('lw-app-consent', 'consent', lang('I agree that the firm keeps my application to consider it, as its privacy notice explains.'), true),
            $captcha(),
            $submit(lang('Send Application'), 'btn btn-primary btn-lg px-4'),
            $loop(array()),
        )),
    ),

    // One application: what was sent and its status, for the applicant and
    // the staff.
    'application_detail' => array(
        'page'   => 'application_view',
        'slug'   => lang('form-detail'),
        'config' => array(
            'regionType'          => 'form_item_view',
            'custom_form_page_id' => '{{tab:application}}',
            'access_control'      => 'submitter_only',
            'not_found_message'   => lang('This application could not be found, or it is not yours to see.'),
        ),
        'tree'   => $record_view('bi-file-earmark-x', lang('Application not found'), lang('This application could not be found, or it is not yours to see.'),
            'careers', lang('Careers'),
            lang('Thank you, your application has reached us. We answer every application within two weeks; this page shows its status.'),
            array(
                $el('div', 'card border-0 shadow-sm rounded-3 mb-4', lang('Application Card'), array(
                    $el('div', 'card-body p-4', lang('Card Body'), array(
                        $record_title(lang('Application'), lang('Legal Intern'), 'position'),
                        $el('dl', 'row small mb-0', lang('Details'), array_merge(
                            $detail(lang('Applicant'), '', lang('Applicant'), $person_name(), true),
                            $detail(lang('Sent'), '', lang('Sent'), array_merge(array('text' => lang('Date')), $bind('submitted_date_and_time'))),
                            $detail(lang('Profile link'), 'text-break', lang('Link'), array(
                                $link('https://example.com/profile', '#', '', array(), array('target' => '_blank', 'rel' => 'noopener',
                                    '_bindings' => array('href' => 'profile_link', 'text' => 'profile_link'))),
                            ), true),
                            $detail(lang('CV'), 'mb-0 text-break', lang('CV'), $file_link('cv', 'cv.pdf'), true)
                        )),
                    )),
                )),
                $record_text(lang('A few words about you'), 'cover_letter', $sample_letter),
            )),
    ),

    // The staff's list of applications, newest first, searchable.
    'staff_applications_list' => array(
        'page'   => 'staff_applications',
        'slug'   => lang('form-list'),
        'config' => array(
            'regionType'          => 'form_list_view',
            'custom_form_page_id' => '{{tab:application}}',
            'detail_page_id'      => '{{tab:application_view}}',
            'items_per_page'      => 20,
            'order_by_field'      => 'submitted_date_and_time',
            'order_by_direction'  => 'DESC',
            'search_enabled'      => true,
            'search_fields'       => array('first_name', 'last_name', 'position', 'status'),
            'search_width'        => 'half',
            'search_label'        => lang('Search by name, position or status...'),
            'empty_message'       => lang('No applications yet.'),
        ),
        'tree'   => $widget_root(array(
            $el('div', 'list-group', lang('Application List'), array(
                $loop(array($application_row())),
            )),
        )),
    ),

    // The e-mail pages' widgets: drawn for the submission just sent.
    'email_consultation_admin' => $mail_detail('email_consultation_new', 'consultation', lang('The consultation request could not be found.')) + array(
        'tree' => $widget_root(array(
            $mail_heading(lang('A new consultation request')),
            $mail_para(lang('It came from the consultation form. Check the other party for a conflict, then set the status on the request\'s page; what you write under it reaches the client by e-mail.')),
            $mail_consultation(),
            $mail_box(array(
                $mail_line(lang('Client'), $person_name()),
                $mail_line(lang('Company name'), array($span(lang('Sample Holding Inc.'), '', lang('Company'), $bind('company')))),
                $mail_line(lang('The other party'), array($span(lang('Sample Holding Inc.'), '', lang('Other Party'), $bind('other_party')))),
                $mail_line(lang('Email'), array($span('jane.cooper@example.com', '', lang('Email'), $bind('email')))),
                $mail_line(lang('Phone'), array($span('+90 555 000 00 00', '', lang('Phone'), $bind('phone')))),
                $mail_line(lang('Deadline, if any'), array($span('2026-12-01', '', lang('Deadline'), $bind('deadline')))),
                $mail_line(lang('What happened?'), array($span($sample_summary, '', lang('Summary'), $bind('summary')))),
            ), lang('Client')),
            $mail_button(lang('Open the request'), '{{page:consultation_view}}?r=^^reference_code^^'),
            $mail_reference(),
        )),
    ),
    'email_consultation_client' => $mail_detail('email_consultation_received', 'consultation', lang('The consultation request could not be found.')) + array(
        'tree' => $widget_root(array(
            $mail_heading(lang('We received your consultation request')),
            $mail_greeting(),
            $mail_para(lang('Thank you for writing to us. We check for a conflict of interest and a lawyer replies within one working day with a time for the first consultation.')),
            $mail_consultation(),
            $mail_para(lang('More documents or a question? Write to us on the request\'s page; everything about your matter stays there.')),
            $mail_button(lang('Open my request'), '{{page:consultation_view}}?r=^^reference_code^^'),
            $mail_new_account(),
        )),
    ),
    'email_application_admin' => $mail_detail('email_application_new', 'application', lang('The application could not be found.')) + array(
        'tree' => $widget_root(array(
            $mail_heading(lang('A new application')),
            $mail_para(lang('It came from the careers page. Read the CV, then set the status on the application\'s page.')),
            $mail_box(array(
                $mail_line(lang('Position'), array($span(lang('Legal Intern'), '', lang('Position'), $bind('position')))),
                $mail_line(lang('Applicant'), $person_name()),
                $mail_line(lang('Email'), array($span('jane.cooper@example.com', '', lang('Email'), $bind('email')))),
                $mail_line(lang('Phone'), array($span('+90 555 000 00 00', '', lang('Phone'), $bind('phone')))),
                $mail_line(lang('Profile link'), array($span('https://example.com/profile', '', lang('Link'), $bind('profile_link')))),
                $mail_line(lang('A few words about you'), array($span($sample_letter, '', lang('Text'), $bind('cover_letter')))),
            ), lang('Application')),
            $mail_button(lang('Open the application'), '{{page:application_view}}?r=^^reference_code^^'),
            $mail_reference(),
        )),
    ),
    'email_application_client' => $mail_detail('email_application_received', 'application', lang('The application could not be found.')) + array(
        'tree' => $widget_root(array(
            $mail_heading(lang('We received your application')),
            $mail_greeting(),
            $mail_para(lang('Thank you for your interest in the firm. We read every application and answer within two weeks.')),
            $mail_box(array(
                $mail_line(lang('Position'), array($span(lang('Legal Intern'), '', lang('Position'), $bind('position')))),
            ), lang('Application')),
            $mail_button(lang('Open my application'), '{{page:application_view}}?r=^^reference_code^^'),
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

return array('pages' => $consultation_pages, 'widgets' => $consultation_widgets);
