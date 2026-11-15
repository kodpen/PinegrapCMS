<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Recruitment Agency", its job applications: the
 * application form, the application's own page with its messages, the
 * candidate's own list and the staff's list; the privacy page the consent
 * boxes link, the application e-mails and the new message e-mail every
 * thread of the site sends, and the widgets that draw them. Included after
 * parts.php, mail.php and jobs.php in the same scope; returns
 * array('pages' => ..., 'widgets' => ...). The record builders below
 * (form page, record page, lists, messages) serve the employers' staff
 * requests too.
 *
 * An application is a record of the Job Applications form, never an
 * applicant tracking system: a candidate without an account gets one
 * (auto-registration), so the application's page and My Applications show
 * it at once. The job starts with what a link sends (?job=: the job's
 * title, as the job page sends it); without one it is an open
 * application, which is also how a candidate registers a CV. Its Status
 * is an office-use-only field the agency sets in the control panel; the
 * consultant writes under it (the page's comments), mailed to the
 * candidate and to the agency. The CV is kept in the template's private
 * folder: the record's page links it for whoever may read the record.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

$application_pages = array();

// The sample application the canvas shows: words, never a date.
$app_sample = $jobs['software_developer'];
$app_sample_sector = $sectors[$app_sample['sector']]['title'];
$app_sample_city = $cities[$app_sample['city']]['title'];
$app_sample_notice = $notice_choices[3];
$app_sample_letter = lang('I have six years of building web services, the last three on a payments platform, and I am looking for a team where I can lead the design of new features.');
$app_open = $application_job_choices[count($application_job_choices) - 1];

// ── Record pieces ───────────────────────────────────────────────────────

// The side column of a form page: what happens next, then the agency's
// phone and hours.
$rc_form_aside = function ($title, $steps, $more) use ($el, $heading, $para, $icon, $span, $agency) {
    $items = array();
    foreach ($steps as $n => $text) {
        $items[] = $el('li', $n < count($steps) - 1 ? 'mb-2' : '', lang('Step'), array(), array('text' => $text));
    }
    return array(
        $el('div', 'p-4 rounded-4 bg-body-tertiary mb-4', lang('How It Works'), array(
            $heading('h2', $title, 'prc-display h5 mb-3'),
            $el('ol', 'small text-body-secondary ps-3 mb-0', lang('List'), $items),
        )),
        $el('div', 'p-4 rounded-4 border', lang('Questions'), array(
            $el('p', 'd-flex align-items-center gap-2 fw-semibold mb-2', lang('Title'), array(
                $icon('bi-telephone', 'text-primary'),
                $span(lang('Rather talk to us?'), '', lang('Text')),
            )),
            $para($more, 'small text-body-secondary mb-2'),
            $para($agency['phone'], 'small fw-semibold mb-1'),
            $para($agency['hours'], 'small text-body-secondary mb-0'),
        )),
    );
};

// A form page: the header, the form in a card and the side column.
$rc_ask_page = function ($title, $lead, $widget_key, $aside) use ($page, $page_header, $el, $container, $row, $col, $widget) {
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

// A visitor's own list page, with the way to send another.
$rc_my_page = function ($title, $lead, $widget_key, $more_title, $more_text, $more_link, $more_key) use ($page, $page_header, $el, $container, $row, $col, $widget, $icon, $span, $para, $link) {
    return $page(array(
        $page_header($title, $lead),
        $el('section', 'py-5', lang('Content'), array(
            $container(array(
                $row(array(
                    $col(array($widget($widget_key)), '', '8'),
                    $col(array(
                        $el('div', 'p-4 rounded-4 bg-body-tertiary', lang('Send Another'), array(
                            $el('p', 'd-flex align-items-center gap-2 fw-semibold mb-2', lang('Title'), array(
                                $icon('bi-plus-circle', 'text-primary'),
                                $span($more_title, '', lang('Text')),
                            )),
                            $para($more_text, 'small text-body-secondary mb-3'),
                            $link($more_link, '{{page:' . $more_key . '}}', 'btn btn-sm btn-primary px-3'),
                        )),
                    ), '', '4'),
                ), '4', '', 'g-lg-5'),
            )),
        )),
    ), false);
};

// The staff's list page.
$rc_staff_list_page = function ($title, $lead, $widget_key) use ($staff_page, $page_header, $el, $container, $widget) {
    return $staff_page(array(
        $page_header($title, $lead),
        $el('section', 'py-5', lang('Content'), array(
            $container(array($widget($widget_key))),
        )),
    ));
};

// The comments settings of a record page: messages by signed-in readers,
// published at once, a file each, the reply e-mail to the sender and a
// copy to the agency.
$rc_messages_settings = function ($subject) {
    return array('label' => lang('Message'), 'allow_new' => true, 'auto_publish' => true, 'show_date' => true, 'login' => true,
        'attachments' => true,
        'email_page' => '{{tab:email_reply}}', 'email_subject' => $subject,
        'notify_email' => '{{site_email}}');
};

// The sender's name.
$person_name = function () use ($span, $bind) {
    return array(
        $span(lang('Jane'), '', lang('First Name'), $bind('first_name')),
        $span(' ', '', lang('Text')),
        $span(lang('Cooper'), '', lang('Last Name'), $bind('last_name')),
    );
};

// A record in a list: its title, its status and its details, linked to its
// page. The staff's row starts with the sender's name and adds the phone;
// the sender's own adds the messages.
$rc_record_row = function ($name, $title_sample, $title_field, $status, $items, $staff, $person) use ($el, $span, $icon, $bind, $person_name) {
    $item = function ($icon_name, $label, $sample, $field) use ($el, $icon, $span, $bind) {
        return $el('span', 'd-inline-flex align-items-center gap-1', $label, array(
            $icon($icon_name),
            $span($sample, '', $label, $bind($field)),
        ));
    };
    $children = array();
    foreach ($items as $i) $children[] = $item($i[0], $i[1], $i[2], $i[3]);
    if ($staff) {
        $children[] = $item('bi-envelope', lang('Email'), 'jane.cooper@example.com', 'email');
        $children[] = $item('bi-telephone', lang('Phone'), '+90 555 000 00 00', 'phone');
        $title = $el('span', 'fw-semibold', $person, array_merge($person_name(), array(
            $span(' · ', 'text-body-secondary', lang('Text')),
            $span($title_sample, 'text-body-secondary fw-normal', $name, $bind($title_field)),
        )));
    } else {
        $children[] = $item('bi-chat-left-text', lang('Messages'), '0', 'number_of_comments');
        $title = $span($title_sample, 'fw-semibold', $name, $bind($title_field));
    }
    return $el('a', 'list-group-item list-group-item-action p-3', $name, array(
        $el('div', 'd-flex flex-wrap align-items-center justify-content-between gap-2 mb-1', lang('Top Line'), array(
            $title,
            $span($status, 'badge rounded-pill bg-primary-subtle text-primary-emphasis', lang('Status'), $bind('status')),
        )),
        $el('div', 'd-flex flex-wrap column-gap-3 row-gap-1 small text-body-secondary', lang('Details'), $children),
    ), array('href' => '#', '_bindings' => array('href' => 'form_item_view')));
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

// The form detail settings of an e-mail page's widget (any reader: the
// e-mail is drawn for the submission just sent) and of a record page's
// widget (the sender's own, and the staff's).
$rc_detail_widget = function ($page_key, $form_key, $missing, $access) {
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
// A list of records, newest first: the sender's own (the staff see them
// all), or the staff's with a search.
$rc_record_list = function ($page_key, $form_key, $view_key, $staff, $empty, $search_fields, $search_label, $item) use ($widget_root, $el, $loop) {
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
            $el('div', 'list-group', lang('Record List'), array($loop(array($item)))),
        )),
    );
};

// The messages under a record: the page's own Comments widget, shown only
// to whoever may read the record above it. Oldest first, all on one page,
// with a file each. No Reply, Share or follow box: the sender is e-mailed
// every answer and the agency every message.
$rc_messages_widget = function ($page_key, $empty) use ($comment_thread) {
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

// The notes at the top of a record just sent: thank you, and the account
// the form opened for a sender who had none.
$rc_sent_notes = function ($thanks) use ($el, $icon, $span, $link, $para, $bind, $show_when, $attr) {
    return array(
        $el('div', 'alert alert-success d-flex gap-2', lang('Sent Notice'), array(
            $icon('bi-check-circle', 'mt-1'),
            $span($thanks, '', lang('Text')),
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
    );
};

// A record's page: not found, or the notes, the toolbar, the card with its
// details, the sender's own words and the note about messages.
$rc_record_tree = function ($r) use ($el, $heading, $para, $icon, $span, $link, $container, $row, $col, $bind, $show_when, $edit_button, $rc_sent_notes) {
    return array('type' => 'root', 'props' => array(), 'children' => array(
        $container(array(
            $row(array(
                $col(array(
                    $el('div', 'text-center py-5', lang('Not Found'), array(
                        $icon($r['missing_icon'], 'display-5 text-body-tertiary'),
                        $heading('h2', $r['missing_title'], 'h3 fw-semibold mt-3'),
                        $para($r['missing'], 'text-body-secondary mb-4', $bind('not_found')),
                        $link($r['back'], '{{page:' . $r['back_key'] . '}}', 'btn btn-primary px-4'),
                    ), $show_when('record_not_found')),
                    $el('div', '', lang('Record'), array_merge($rc_sent_notes($r['thanks']), array(
                        $el('div', 'd-flex flex-wrap align-items-center justify-content-between gap-2 mb-3', lang('Toolbar'), array(
                            $el('a', 'd-inline-flex align-items-center gap-1 small fw-semibold link-primary text-decoration-none', lang('Link'), array(
                                $icon('bi-arrow-left'),
                                $span($r['back'], '', lang('Link Text')),
                            ), array('href' => '{{page:' . $r['back_key'] . '}}')),
                            $edit_button(lang('Change Status')),
                        )),
                        $el('div', 'card border-0 shadow-sm rounded-4 mb-4', $r['card'], array(
                            $el('div', 'card-body p-4', lang('Card Body'), array(
                                $el('div', 'd-flex flex-wrap align-items-start justify-content-between gap-2 mb-3', lang('Title'), array(
                                    $el('div', '', lang('Words'), array(
                                        $para($r['eyebrow'], 'prc-eyebrow small text-uppercase fw-semibold text-primary mb-1'),
                                        $heading('h1', $r['title'], 'prc-display h3 mb-0', $bind($r['title_field'])),
                                    )),
                                    $span($r['status'], 'badge rounded-pill bg-primary-subtle text-primary-emphasis fs-6', lang('Status'), $bind('status')),
                                )),
                                $el('dl', 'row small mb-0', lang('Details'), $r['details']),
                            )),
                        )),
                        $el('div', 'pg-hide-if-empty p-4 rounded-4 bg-body-tertiary mb-4', $r['words_label'], array(
                            $para($r['words_label'], 'small fw-semibold text-body-secondary mb-2'),
                            $el('div', 'pg-field-value lh-lg', lang('Value'), array(), array_merge(array('text' => $r['words']), $bind($r['words_field']))),
                        )),
                        $el('div', 'd-flex gap-2 small text-body-secondary', lang('Note'), array(
                            $icon('bi-chat-left-text', 'text-primary mt-1'),
                            $span($r['note'], '', lang('Text')),
                        )),
                    )), $show_when('record_found')),
                ), '', '8'),
            ), '', 'center'),
        ), 'py-5'),
    ));
};

// The contact fields of a form: name, e-mail and phone, each filling the
// sender's contact.
$rc_contact_fields = function ($p) use ($row, $col, $field, $input) {
    return $row(array(
        $col(array($field($p . '-first-name', lang('First Name'), $input($p . '-first-name', 'text', 'first_name', 'given-name', true, array('contact_field' => 'first_name')))), '6'),
        $col(array($field($p . '-last-name', lang('Last Name'), $input($p . '-last-name', 'text', 'last_name', 'family-name', true, array('contact_field' => 'last_name')))), '6'),
        $col(array($field($p . '-email', lang('Email'), $input($p . '-email', 'email', 'email', 'email', true, array('contact_field' => 'email_address')))), '6'),
        $col(array($field($p . '-phone', lang('Phone'), $input($p . '-phone', 'tel', 'phone', 'tel', true, array('contact_field' => 'mobile_phone')))), '6'),
    ), '3');
};

// ── Application pieces ──────────────────────────────────────────────────

$application_row = function ($staff) use ($rc_record_row, $app_sample, $app_sample_sector, $app_sample_city, $application_statuses) {
    return $rc_record_row(lang('Job'), $app_sample['title'], 'job', $application_statuses[0], array(
        array('bi-calendar3', lang('Sent'), lang('Date'), 'submitted_date_and_time'),
        array('bi-diagram-3', lang('Sector'), $app_sample_sector, 'sector'),
        array('bi-geo-alt', lang('City'), $app_sample_city, 'city'),
    ), $staff, lang('Candidate'));
};

// The lines of an application, for its e-mails; the staff's adds the
// candidate's contact and covering letter.
$mail_application = function ($staff) use ($mail_box, $mail_line, $span, $bind, $person_name, $app_sample, $app_sample_sector, $app_sample_city, $app_sample_notice, $app_sample_letter) {
    $lines = array(
        $mail_line(lang('Job'), array($span($app_sample['title'], '', lang('Job'), $bind('job')))),
        $mail_line(lang('Sector'), array($span($app_sample_sector, '', lang('Sector'), $bind('sector')))),
        $mail_line(lang('City'), array($span($app_sample_city, '', lang('City'), $bind('city')))),
        $mail_line(lang('Notice Period'), array($span($app_sample_notice, '', lang('Notice Period'), $bind('notice_period')))),
    );
    if ($staff) {
        $lines[] = $mail_line(lang('Candidate'), $person_name());
        $lines[] = $mail_line(lang('Email'), array($span('jane.cooper@example.com', '', lang('Email'), $bind('email'))));
        $lines[] = $mail_line(lang('Phone'), array($span('+90 555 000 00 00', '', lang('Phone'), $bind('phone'))));
        $lines[] = $mail_line(lang('Covering Letter'), array($span($app_sample_letter, '', lang('Covering Letter'), $bind('cover_letter'))));
    }
    return $mail_box($lines, lang('Job Application'));
};

// ── Pages ───────────────────────────────────────────────────────────────

// The application. A job's page opens it with the job chosen (?job=);
// without one it is an open application. What is sent opens the
// application's own page.
$application_pages[] = array(
    'key'              => 'apply',
    'name'             => lang('apply'),
    'folder'           => 'public',
    'title'            => lang('Send Your CV'),
    'meta_description' => lang('Apply for a job or send an open application: your CV, the sector and city you want, and a consultant who reads it.'),
    'form'             => array(
        'form_name'            => lang('Job Applications'),
        'confirmation_message' => lang('Thank you, your application has reached us. A consultant reads every CV within two working days.'),
        'confirmation_page_id' => '{{tab:application_view}}',
        'auto_registration'    => 1,
        'notify_email'         => '{{site_email}}',
        'notify_subject'       => lang('New application: ^^job^^'),
        'notify_page_id'       => '{{tab:email_application_new}}',
        'confirm_email'        => 1,
        'confirm_subject'      => lang('We received your application'),
        'confirm_page_id'      => '{{tab:email_application_received}}',
    ),
    'tree'             => $rc_ask_page(lang('Send Your CV'),
        lang('Apply for the job you chose, or send an open application and let a consultant find the job for you. It is free, and a person reads every CV.'),
        'application_form',
        $rc_form_aside(lang('What happens next'), array(
            lang('Your application opens its own page, and an account for you if you have none.'),
            lang('A consultant who knows your sector reads your CV within two working days.'),
            lang('When this job or another one fits, your consultant calls you; every answer also comes under your application and by e-mail.'),
            lang('We send your CV to an employer only after you agree.'),
        ), lang('Not sure which job to choose? Call us and a consultant talks it through with you.'))),
);

// One application: what was sent, its status and the messages under it.
$application_pages[] = array(
    'key'      => 'application_view',
    'name'     => lang('application'),
    'folder'   => 'registration',
    'title'    => lang('Job Application'),
    'search'   => false,
    'sitemap'  => false,
    'comments' => $rc_messages_settings(lang('There is a new message about your application')),
    'tree'     => $page(array(
        $widget('application_detail'),
        $comments_section(lang('Messages'), 'application_messages'),
    ), false),
);

// My Applications: the candidate's own applications (for the staff,
// everybody's).
$application_pages[] = array(
    'key'     => 'my_applications',
    'name'    => lang('my-applications'),
    'folder'  => 'registration',
    'title'   => lang('My Applications'),
    'search'  => false,
    'sitemap' => false,
    'tree'    => $rc_my_page(lang('My Applications'), lang('Your applications, their status and your consultant\'s messages, newest first.'),
        'my_applications_list', lang('Another job?'),
        lang('See the jobs open now; your details are filled in for you when you apply.'), lang('See the Open Jobs'), 'jobs'),
);

// The staff's list of every application, newest first, with a search.
$application_pages[] = array(
    'key'     => 'staff_applications',
    'name'    => lang('all-applications'),
    'folder'  => 'private',
    'title'   => lang('Job Applications'),
    'search'  => false,
    'sitemap' => false,
    'tree'    => $rc_staff_list_page(lang('Job Applications'),
        lang('Every application, newest first: read the CV, set the status and write to the candidate under each.'), 'staff_applications_list'),
);

// The privacy notice the consent boxes and the footer link to: sample text
// with a note to adapt it.
$application_pages[] = array(
    'key'              => 'privacy',
    'name'             => lang('privacy-policy'),
    'folder'           => 'public',
    'title'            => lang('Privacy Policy'),
    'meta_description' => lang('What the agency keeps about candidates and employers, why, who sees a CV, and what you can ask of us.'),
    'tree'             => $page(array(
        $page_header(lang('Privacy Policy'), lang('What the agency keeps about candidates and employers, why, who sees a CV, and what you can ask of us.')),
        $el('section', 'py-5', lang('Your Data'), array(
            $container(array($row(array($col(array(
                $el('div', 'alert alert-secondary d-flex gap-2 small', lang('Sample Text Note'), array(
                    $icon('bi-info-circle', 'mt-1'),
                    $span(lang('Sample text: adapt it to your agency and to the data protection law that applies to you before you publish.'), '', lang('Text')),
                ), array('_attrs' => array($attr('role', 'note')))),
                $heading('h2', lang('Your data'), 'h4 fw-semibold mb-3'),
                $para(lang('When you apply for a job, send an open application, ask us for staff, join the job alerts or open an account, we keep your name, your contact details, what you tell us and the CV or documents you send, to find you a job or the people you need.'), 'mb-3'),
                $para(lang('Only our consultants read a CV. We send it to an employer only after the candidate agrees, never sell it and never use it for anything else. We keep it as long as the law allows; you can see and change your account data from My Account and ask us to delete the rest through the contact page.'), 'mb-0'),
            ), '', '8'))))),
        ), $searchable('rc-your-data', lang('privacy, personal data, KVKK, GDPR, delete my CV'))),
    )),
);

// The e-mails: what the agency receives and the reply to the candidate.
$application_pages[] = array_merge($mail_settings, array(
    'key'   => 'email_application_new',
    'name'  => lang('email-new-application'),
    'title' => lang('New Application E-mail'),
    'tree'  => $mail_page(array($widget('email_application_admin'))),
));
$application_pages[] = array_merge($mail_settings, array(
    'key'   => 'email_application_received',
    'name'  => lang('email-application-received'),
    'title' => lang('Application Received E-mail'),
    'tree'  => $mail_page(array($widget('email_application_client'))),
));
// A new message or reply: sent to the candidate of an application, the
// employer of a staff request, the sender of a conversation and the
// visitors watching a page. Its Comments widget draws the comment in the
// card of mail.php; the System region after it keeps the fixed comment
// for an e-mail the widget does not draw.
$application_pages[] = array_merge($mail_settings, array(
    'key'   => 'email_reply',
    'name'  => lang('email-new-reply'),
    'title' => lang('New Reply E-mail'),
    'tree'  => $mail_page(array(
        $mail_heading(lang('There is a new message')),
        $mail_para(lang('Something new was written on a page you follow: your application, your staff request, a conversation or a career advice article. You can read it below and answer it on the page.')),
        $widget('email_comment'),
        array('type' => 'region', 'props' => array('regionType' => 'system', 'regionName' => ''), 'children' => array()),
    )),
));

// ── Widgets ─────────────────────────────────────────────────────────────

$application_missing = lang('This application could not be found, or it is not yours to see.');

$application_widgets = array(

    // The application: the fields are the page's form. The job starts with
    // what a link sends (?job=), else it is an open application. The
    // Status is the agency's (office use only): every application starts
    // as received.
    'application_form' => array(
        'page'   => 'apply',
        'slug'   => 'form',
        'config' => array('regionType' => 'custom_form', 'form_source' => 'page'),
        'tree'   => $widget_root(array(
            $form_step(1, lang('The job you want')),
            $field('rc-app-job', lang('Job'), $select('rc-app-job', 'job', '', $application_job_choices, true, $app_open, array('url_param' => 'job')), 'mb-3',
                lang('Choose Open application to let your consultant find the job for you.')),
            $row(array(
                $col(array($field('rc-app-sector', lang('Sector'), $select('rc-app-sector', 'sector', lang('Choose a sector…'), $application_sector_choices))), '6'),
                $col(array($field('rc-app-city', lang('City'), $select('rc-app-city', 'city', lang('Choose a city…'), $application_city_choices))), '6'),
            ), '3'),
            $field('rc-app-notice', lang('Notice Period'), $select('rc-app-notice', 'notice_period', lang('Choose…'), $notice_choices), 'mb-3',
                lang('How soon you could start a new job.')),
            $form_step(2, lang('About you')),
            $rc_contact_fields('rc-app'),
            $form_step(3, lang('Your CV')),
            $field('rc-app-cv', lang('CV'), $file('rc-app-cv', 'cv', 'private', '.pdf,.doc,.docx,.odt'), 'mb-3', lang('PDF or Word. Only our consultants see it.')),
            $field('rc-app-letter', lang('Covering Letter'), $textarea('rc-app-letter', 'cover_letter', 5, false), 'mb-3',
                lang('A few lines about what you do best and what you are looking for next.')),
            $field('rc-app-status', lang('Status'), $select('rc-app-status', 'status', '', $application_statuses, false, $application_statuses[0], array('office_use_only' => true))),
            $agree('rc-app-consent', 'consent', lang('I agree that the agency keeps my application and CV to find me a job and sends my CV to an employer only after asking me, as its privacy notice explains.'), true),
            $captcha(),
            $el('div', 'd-flex flex-wrap align-items-center gap-3', lang('Send Area'), array(
                $submit(lang('Send Application'), 'btn btn-primary btn-lg px-4'),
                $para(lang('Free for candidates, always.'), 'small text-body-secondary mb-0'),
            )),
            $loop(array()),
        )),
    ),

    // One application: what the candidate sent and its status; the
    // consultant's messages follow as comments.
    'application_detail' => $rc_detail_widget('application_view', 'apply', $application_missing, 'submitter_only') + array(
        'tree' => $rc_record_tree(array(
            'missing_icon'  => 'bi-file-earmark-x',
            'missing_title' => lang('Application not found'),
            'missing'       => $application_missing,
            'back'          => lang('My Applications'),
            'back_key'      => 'my_applications',
            'thanks'        => lang('Thank you, your application has reached us. A consultant reads your CV within two working days; this page shows its status, and you can write to us below.'),
            'card'          => lang('Application Card'),
            'eyebrow'       => lang('Job Application'),
            'title'         => $app_sample['title'],
            'title_field'   => 'job',
            'status'        => $application_statuses[0],
            'details'       => array_merge(
                $detail(lang('Candidate'), '', lang('Candidate'), $person_name(), true),
                $detail(lang('Sector'), '', lang('Sector'), $bound($app_sample_sector, 'sector')),
                $detail(lang('City'), '', lang('City'), $bound($app_sample_city, 'city')),
                $detail(lang('Notice Period'), '', lang('Notice Period'), $bound($app_sample_notice, 'notice_period')),
                $detail(lang('Sent'), '', lang('Sent'), $bound(lang('Date'), 'submitted_date_and_time')),
                $detail(lang('CV'), 'text-break', lang('CV'), array(
                    $link('cv.pdf', '#', '', array(), array('target' => '_blank', 'rel' => 'noopener',
                        '_bindings' => array('href' => 'cv', 'text' => 'cv'))),
                ), true),
                $detail(lang('Reference Code'), 'mb-0 font-monospace', lang('Reference Code'), $bound('ABC123', 'reference_code'))
            ),
            'words_label'   => lang('Covering Letter'),
            'words'         => $app_sample_letter,
            'words_field'   => 'cover_letter',
            'note'          => lang('A new CV or a question for your consultant? Write to us below; we answer here and by e-mail.'),
        )),
    ),

    'application_messages' => $rc_messages_widget('application_view',
        lang('No messages yet. Your consultant writes here about interviews and offers; you can write to us about your application too.')),

    'my_applications_list' => $rc_record_list('my_applications', 'apply', 'application_view', false,
        lang('No applications yet. See the jobs open now, or send an open application.'),
        array(), '', $application_row(false)),

    'staff_applications_list' => $rc_record_list('staff_applications', 'apply', 'application_view', true, lang('No applications yet.'),
        array('first_name', 'last_name', 'email', 'job', 'sector', 'city', 'status'),
        lang('Search by candidate, job, sector, city or status...'), $application_row(true)),

    // The e-mail pages' widgets: drawn for the submission just sent.
    'email_application_admin' => $rc_detail_widget('email_application_new', 'apply', lang('This application was not found.'), 'public') + array(
        'tree' => $widget_root(array(
            $mail_heading(lang('A new application')),
            $mail_para(lang('It came from the website. Read the CV, then set the status on the application\'s page; what you write under it reaches the candidate by e-mail.')),
            $mail_application(true),
            $mail_button(lang('Open the application'), '{{page:application_view}}?r=^^reference_code^^'),
            $mail_reference(),
        )),
    ),
    'email_application_client' => $rc_detail_widget('email_application_received', 'apply', lang('This application was not found.'), 'public') + array(
        'tree' => $widget_root(array(
            $mail_heading(lang('We received your application')),
            $mail_greeting(),
            $mail_para(lang('Thank you. A consultant who knows your sector reads your CV within two working days; every answer comes on the application\'s page and by e-mail.')),
            $mail_application(false),
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

return array('pages' => $application_pages, 'widgets' => $application_widgets);
