<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Sports Club", its registrations: the youth academy
 * registration, its own page with its messages, My Registrations and the
 * staff's list; the trial request, its own page and the staff's list; the
 * privacy page the consent boxes link, the e-mails of both forms and the
 * new message e-mail every thread of the site sends, and the widgets that
 * draw them. Included after parts.php and mail.php in the same scope;
 * returns array('pages' => ..., 'widgets' => ...).
 *
 * A registration is a request for a place in an age group, never an
 * enrolment: a record of the Academy Registrations form. A parent without
 * an account gets one (auto-registration), so the registration's page and
 * My Registrations show it at once. Its Status is an office-use-only field
 * the club changes in the control panel (received, taster session booked,
 * place offered, waiting list, closed); the taster day and the offer are
 * written under it as messages (the page's comments), mailed to the parent
 * and to the club. The age group starts with what a link sends (?team=: the
 * team's title, as the team pages write it).
 *
 * A trial request is a smaller form of the same kind for a player of any
 * age who can sign it: the team (?team=) and the position (?position=)
 * start with what a link sends, the coach writes the training night under
 * the request, and the player finds it under My Registrations.
 *
 * The helpers of this file are named rg_…, so they never take the place
 * of a helper another page file of the folder defines in the same scope.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

$registration_pages = array();

// The sample records the canvas shows. The child is never named and the
// birth date is never a date in the sample: the fields show what they
// hold.
$rg_sample_team = $teams['u12']['title'];
$rg_sample_birth = lang('Date of birth');
$rg_sample_medical = lang('Mild asthma: an inhaler is in the kit bag. No allergies.');
$rg_sample_notes = lang('She has played in the school team for a year and would love to join her friends on Wednesdays.');
$rg_sample_trial_team = $teams['women']['title'];
$rg_sample_position = $positions['midfielder']['title'];
$rg_sample_club = lang('Played for a university side until last season');
// A trial asks for the position, or leaves it to the coach.
$rg_position_choices = array_merge($position_titles, array(lang('Not sure yet')));

// ── Page shells ─────────────────────────────────────────────────────────

// A form page: the form in a card on the left, the side column on the
// right.
$rg_form_page = function ($title, $lead, $widget_key, $aside) use ($page, $page_header, $el, $container, $row, $col, $widget) {
    return $page(array(
        $page_header($title, $lead),
        $el('section', 'py-5', lang('Content'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $el('div', 'card border-0 shadow-sm', lang('Form Card'), array(
                            $el('div', 'card-body p-4 p-lg-5', lang('Card Body'), array($widget($widget_key))),
                        )),
                    ), '', '8'),
                    $col($aside, '', '4'),
                ), '4', '', 'g-lg-5'),
            )),
        )),
    ), false);
};

// The side column of a form: what happens next, a check list and the
// club's phone.
$rg_aside = function ($steps, $title, $points) use ($el, $heading, $para, $icon, $span, $check_list, $club) {
    $items = array();
    foreach ($steps as $n => $text) {
        $items[] = $el('li', $n < count($steps) - 1 ? 'mb-2' : '', lang('Step'), array(), array('text' => $text));
    }
    return array(
        $el('div', 'p-4 rounded-3 bg-body-tertiary mb-4', lang('How It Works'), array(
            $heading('h2', lang('What happens next'), 'psp-display h5 mb-3'),
            $el('ol', 'small text-body-secondary ps-3 mb-0', lang('List'), $items),
        )),
        $el('div', 'p-4 rounded-3 border mb-4', $title, array(
            $heading('h2', $title, 'psp-display h5 mb-3'),
            $check_list($points, 'list-unstyled small mb-0'),
        )),
        $el('div', 'p-4 rounded-3 border', lang('Questions'), array(
            $el('p', 'd-flex align-items-center gap-2 fw-semibold mb-2', lang('Title'), array(
                $icon('bi-telephone', 'text-primary'),
                $span(lang('Questions?'), '', lang('Text')),
            )),
            $para($club['phone'], 'small fw-semibold mb-0'),
        )),
    );
};

// A staff page holding one list.
$rg_staff_list_page = function ($title, $lead, $widget_key) use ($staff_page, $page_header, $el, $container, $widget) {
    return $staff_page(array(
        $page_header($title, $lead),
        $el('section', 'py-5', lang('Content'), array(
            $container(array($widget($widget_key))),
        )),
    ));
};

// A record page: the record, then its messages.
$rg_comments = function ($email_subject) {
    return array('label' => lang('Message'), 'allow_new' => true, 'auto_publish' => true, 'show_date' => true, 'login' => true,
        'attachments' => true, 'email_page' => '{{tab:email_reply}}', 'email_subject' => $email_subject,
        'notify_email' => '{{site_email}}');
};

// ── Pages ───────────────────────────────────────────────────────────────

// The academy registration. What is sent opens the registration's page.
$registration_pages[] = array(
    'key'              => 'academy_register',
    'name'             => lang('register-for-the-academy'),
    'folder'           => 'public',
    'title'            => lang('Register for the Academy'),
    'meta_description' => lang('Register your child for an age group of the youth academy; the club invites them to a free taster session.'),
    'form'             => array(
        'form_name'            => lang('Academy Registrations'),
        'confirmation_message' => lang('Thank you, the registration has reached us. The academy calls you within a week to book a taster session.'),
        'confirmation_page_id' => '{{tab:registration_view}}',
        'auto_registration'    => 1,
        'notify_email'         => '{{site_email}}',
        'notify_subject'       => lang('New academy registration: ^^team^^'),
        'notify_page_id'       => '{{tab:email_registration_new}}',
        'confirm_email'        => 1,
        'confirm_subject'      => lang('We received your academy registration'),
        'confirm_page_id'      => '{{tab:email_registration_received}}',
    ),
    'tree'             => $rg_form_page(lang('Register for the Academy'),
        lang('Tell us about your child and the age group. A registration is not a commitment: every child starts with a free taster session.'),
        'registration_form', $rg_aside(array(
            lang('The registration opens its own page, and an account for you if you have none.'),
            lang('The coach of the age group calls you within a week to book a taster session.'),
            lang('After the taster we offer a place, or a place on the waiting list when the group is full.'),
            lang('Questions? Write under the registration.'),
        ), lang('Good to bring'), array(
            lang('Boots or trainers and shin pads'),
            lang('A water bottle and a warm top'),
            lang('Any inhaler or medicine your child needs'),
        ))),
);

// One registration: the child, the age group, its status and the messages
// under it.
$registration_pages[] = array(
    'key'      => 'registration_view',
    'name'     => lang('academy-registration'),
    'folder'   => 'registration',
    'title'    => lang('Academy Registration'),
    'search'   => false,
    'sitemap'  => false,
    'comments' => $rg_comments(lang('There is a new message about your academy registration')),
    'tree'     => $page(array(
        $widget('registration_detail'),
        $comments_section(lang('Messages'), 'registration_messages'),
    ), false),
);

// My Registrations: the visitor's own registrations and trial requests
// (for the staff, everybody's).
$registration_pages[] = array(
    'key'     => 'my_registrations',
    'name'    => lang('my-registrations'),
    'folder'  => 'registration',
    'title'   => lang('My Registrations'),
    'search'  => false,
    'sitemap' => false,
    'tree'    => $page(array(
        $page_header(lang('My Registrations'), lang('Your academy registrations and trial requests, their status and our answers, newest first.')),
        $el('section', 'py-5', lang('Content'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $heading('h2', lang('Academy Registrations'), 'psp-display h4 mb-3'),
                        $widget('my_registrations_list'),
                        $heading('h2', lang('Trial Requests'), 'psp-display h4 mt-5 mb-3'),
                        $widget('my_trials_list'),
                    ), '', '8'),
                    $col(array(
                        $el('div', 'p-4 rounded-3 bg-body-tertiary', lang('Another Child'), array(
                            $el('p', 'd-flex align-items-center gap-2 fw-semibold mb-2', lang('Title'), array(
                                $icon('bi-plus-circle', 'text-primary'),
                                $span(lang('A brother or sister?'), '', lang('Text')),
                            )),
                            $para(lang('Register another child for their age group; your details are filled in for you.'), 'small text-body-secondary mb-3'),
                            $link(lang('Register a child'), '{{page:academy_register}}', 'btn btn-sm btn-primary px-3'),
                        )),
                    ), '', '4'),
                ), '4', '', 'g-lg-5'),
            )),
        )),
    ), false),
);

// The staff's list of every registration, newest first, with a search.
$registration_pages[] = array(
    'key'     => 'staff_registrations',
    'name'    => lang('all-academy-registrations'),
    'folder'  => 'private',
    'title'   => lang('Academy Registrations'),
    'search'  => false,
    'sitemap' => false,
    'noindex' => true,
    'tree'    => $rg_staff_list_page(lang('Academy Registrations'),
        lang('Every academy registration, newest first: book the taster session, offer the place, set the status and write to the family under each.'),
        'staff_registrations_list'),
);

// The trial request. What is sent opens the request's page.
$registration_pages[] = array(
    'key'              => 'trial',
    'name'             => lang('ask-for-a-trial'),
    'folder'           => 'public',
    'title'            => lang('Ask for a Trial'),
    'meta_description' => lang('Ask for a trial with one of the club\'s teams; the coach invites you to a training night.'),
    'form'             => array(
        'form_name'            => lang('Trial Requests'),
        'confirmation_message' => lang('Thank you, your trial request has reached us. The coach writes to you within a week with a training night.'),
        'confirmation_page_id' => '{{tab:trial_view}}',
        'auto_registration'    => 1,
        'notify_email'         => '{{site_email}}',
        'notify_subject'       => lang('New trial request: ^^team^^, ^^position^^'),
        'notify_page_id'       => '{{tab:email_trial_new}}',
        'confirm_email'        => 1,
        'confirm_subject'      => lang('We received your trial request'),
        'confirm_page_id'      => '{{tab:email_trial_received}}',
    ),
    'tree'             => $rg_form_page(lang('Ask for a Trial'),
        lang('New players are welcome all season, whatever your level. Tell us about yourself and the coach invites you to a training night.'),
        'trial_form', $rg_aside(array(
            lang('Your request opens its own page, and an account for you if you have none.'),
            lang('The coach of the team writes to you within a week with a training night.'),
            lang('Train with the squad once or twice; then the coach tells you how it went.'),
        ), lang('Good to bring'), array(
            lang('Boots for grass and for the all-weather pitch'),
            lang('Shin pads and a water bottle'),
            lang('A clear idea of when you can train'),
        ))),
);

// One trial request: the team, the position, its status and the messages
// under it.
$registration_pages[] = array(
    'key'      => 'trial_view',
    'name'     => lang('trial-request'),
    'folder'   => 'registration',
    'title'    => lang('Trial Request'),
    'search'   => false,
    'sitemap'  => false,
    'comments' => $rg_comments(lang('There is a new message about your trial request')),
    'tree'     => $page(array(
        $widget('trial_detail'),
        $comments_section(lang('Messages'), 'trial_messages'),
    ), false),
);

// The staff's list of every trial request, newest first, with a search.
$registration_pages[] = array(
    'key'     => 'staff_trials',
    'name'    => lang('all-trial-requests'),
    'folder'  => 'private',
    'title'   => lang('Trial Requests'),
    'search'  => false,
    'sitemap' => false,
    'noindex' => true,
    'tree'    => $rg_staff_list_page(lang('Trial Requests'),
        lang('Every trial request, newest first: invite the player to a training night, set the status and write to them under each.'),
        'staff_trials_list'),
);

// The privacy notice the consent boxes and the footer link to: sample text
// with a note to adapt it.
$registration_pages[] = array(
    'key'              => 'privacy',
    'name'             => lang('privacy-policy'),
    'folder'           => 'public',
    'title'            => lang('Privacy Policy'),
    'meta_description' => lang('What the club keeps about you and your child when you register, ask for a trial or open an account, why, and what you can ask of us.'),
    'tree'             => $page(array(
        $page_header(lang('Privacy Policy'), lang('What the club keeps about you and your child when you register, ask for a trial or open an account, why, and what you can ask of us.')),
        $el('section', 'py-5', lang('Your Data'), array(
            $container(array($row(array($col(array(
                $el('div', 'alert alert-secondary d-flex gap-2 small', lang('Sample Text Note'), array(
                    $icon('bi-info-circle', 'mt-1'),
                    $span(lang('Sample text: adapt it to your club and to the data protection law that applies to you before you publish.'), '', lang('Text')),
                ), array('_attrs' => array($attr('role', 'note')))),
                $heading('h2', lang('Your data'), 'h4 fw-semibold mb-3'),
                $para(lang('When you register a child, ask for a trial, join the newsletter or open an account, we keep your name and contact details, your child\'s name, date of birth and the health notes you give us, to answer you, arrange training and keep players safe.'), 'mb-3'),
                $para(lang('Only the club\'s welfare officer and the coaches of the team read a registration, and health notes only to keep a player safe. We keep it as long as the law requires, never sell it and never give it to advertisers. You can see and change your account data from My Account, and ask us about the rest through the contact page.'), 'mb-0'),
            ), '', '8'))))),
        ), $searchable('sp-your-data', lang('privacy, personal data, KVKK, GDPR, delete my data'))),
    )),
);

// The e-mails: what the club receives and the reply to the sender for
// each form, and the new message e-mail.
foreach (array(
    'email_registration_new'      => array(lang('email-new-academy-registration'), lang('New Academy Registration E-mail'), 'email_registration_admin'),
    'email_registration_received' => array(lang('email-academy-registration-received'), lang('Academy Registration Received E-mail'), 'email_registration_parent'),
    'email_trial_new'             => array(lang('email-new-trial-request'), lang('New Trial Request E-mail'), 'email_trial_admin'),
    'email_trial_received'        => array(lang('email-trial-request-received'), lang('Trial Request Received E-mail'), 'email_trial_player'),
) as $rg_key => $rg_mail) {
    $registration_pages[] = array_merge($mail_settings, array(
        'key'   => $rg_key,
        'name'  => $rg_mail[0],
        'title' => $rg_mail[1],
        'tree'  => $mail_page(array($widget($rg_mail[2]))),
    ));
}
// A new message or reply: sent to the parent of a registration, the player
// of a trial request, the sender of a conversation and the visitors
// watching a page. Its Comments widget draws the comment in the card of
// mail.php; the System region after it keeps the fixed comment for an
// e-mail the widget does not draw.
$registration_pages[] = array_merge($mail_settings, array(
    'key'   => 'email_reply',
    'name'  => lang('email-new-reply'),
    'title' => lang('New Reply E-mail'),
    'tree'  => $mail_page(array(
        $mail_heading(lang('There is a new message')),
        $mail_para(lang('Something new was written on a page you follow: your registration, your trial request, a conversation or a news article. You can read it below and answer it on the page.')),
        $widget('email_comment'),
        array('type' => 'region', 'props' => array('regionType' => 'system', 'regionName' => ''), 'children' => array()),
    )),
));

// ── Widget pieces ───────────────────────────────────────────────────────

// A name from two fields; $sample_first and $sample_last are its sample.
$rg_name = function ($first, $last, $sample_first, $sample_last, $first_name, $last_name) use ($span, $bind) {
    return array(
        $span($sample_first, '', $first_name, $bind($first)),
        $span(' ', '', lang('Text')),
        $span($sample_last, '', $last_name, $bind($last)),
    );
};
$rg_parent_name = function () use ($rg_name) {
    return $rg_name('first_name', 'last_name', lang('Jane'), lang('Cooper'), lang('First Name'), lang('Last Name'));
};
// The child's name: the fields' own words in the sample.
$rg_child_name = function () use ($rg_name) {
    return $rg_name('child_first_name', 'child_last_name', lang('Child\'s first name'), lang('Child\'s last name'),
        lang('Child\'s First Name'), lang('Child\'s Last Name'));
};

// A bound value in a list item or a mail line.
$rg_value = function ($sample, $name, $field) use ($span, $bind) {
    return array($span($sample, '', $name, $bind($field)));
};
// A list item with its icon.
$rg_item = function ($icon_name, $name, $children) use ($el, $icon) {
    return $el('span', 'd-inline-flex align-items-center gap-1', $name, array_merge(array($icon($icon_name)), $children));
};

// A record in a list, linked to its page: the name, the status and the
// details under them.
$rg_row = function ($name, $title, $status, $items) use ($el, $span, $bind) {
    return $el('a', 'list-group-item list-group-item-action p-3', $name, array(
        $el('div', 'd-flex flex-wrap align-items-center justify-content-between gap-2 mb-1', lang('Top Line'), array(
            $el('span', 'fw-semibold', lang('Name'), $title),
            $span($status, 'badge rounded-pill bg-primary-subtle text-primary-emphasis', lang('Status'), $bind('status')),
        )),
        $el('div', 'd-flex flex-wrap column-gap-3 row-gap-1 small text-body-secondary', lang('Details'), $items),
    ), array('href' => '#', '_bindings' => array('href' => 'form_item_view')));
};
// A registration in a list: the child and the age group; the staff's row
// adds the parent and the phone, the parent's own the messages.
$rg_registration_row = function ($staff) use ($rg_row, $rg_item, $rg_value, $rg_child_name, $rg_parent_name, $rg_sample_team, $rg_sample_birth, $registration_statuses) {
    $items = array(
        $rg_item('bi-people', lang('Age Group'), $rg_value($rg_sample_team, lang('Age Group'), 'team')),
        $rg_item('bi-balloon', lang('Date of Birth'), $rg_value($rg_sample_birth, lang('Date of Birth'), 'child_birth_date')),
    );
    if ($staff) {
        $items[] = $rg_item('bi-person', lang('Parent or Guardian'), $rg_parent_name());
        $items[] = $rg_item('bi-telephone', lang('Phone'), $rg_value('+90 555 000 00 00', lang('Phone'), 'phone'));
    } else {
        $items[] = $rg_item('bi-chat-left-text', lang('Messages'), $rg_value('0', lang('Reply Count'), 'number_of_comments'));
    }
    return $rg_row(lang('Academy Registration'), $rg_child_name(), $registration_statuses[0], $items);
};
// A trial request in a list: the player, the team and the position; the
// staff's row adds the experience and the phone.
$rg_trial_row = function ($staff) use ($rg_row, $rg_item, $rg_value, $rg_parent_name, $rg_sample_trial_team, $rg_sample_position, $experience_choices, $trial_statuses) {
    $items = array(
        $rg_item('bi-people', lang('Team Name'), $rg_value($rg_sample_trial_team, lang('Team Name'), 'team')),
        $rg_item('bi-person-standing', lang('Playing Position'), $rg_value($rg_sample_position, lang('Playing Position'), 'position')),
    );
    if ($staff) {
        $items[] = $rg_item('bi-bar-chart', lang('Experience'), $rg_value($experience_choices[2][0], lang('Experience'), 'experience'));
        $items[] = $rg_item('bi-telephone', lang('Phone'), $rg_value('+90 555 000 00 00', lang('Phone'), 'phone'));
    } else {
        $items[] = $rg_item('bi-chat-left-text', lang('Messages'), $rg_value('0', lang('Reply Count'), 'number_of_comments'));
    }
    return $rg_row(lang('Trial Request'), $rg_parent_name(), $trial_statuses[0], $items);
};

// A list: the visitor's own (the staff see them all), or the staff's with
// a search.
$rg_list = function ($page_key, $form_key, $view_key, $staff, $search, $search_label, $row, $empty) use ($widget_root, $el, $loop) {
    $config = array(
        'regionType'          => 'form_list_view',
        'custom_form_page_id' => '{{tab:' . $form_key . '}}',
        'detail_page_id'      => '{{tab:' . $view_key . '}}',
        'items_per_page'      => 20,
        'order_by_field'      => 'submitted_date_and_time',
        'order_by_direction'  => 'DESC',
    );
    if ($staff) {
        $config += array('search_enabled' => true, 'search_fields' => $search, 'search_width' => 'half', 'search_label' => $search_label);
    } else {
        $config += array('viewer_filter' => true, 'viewer_filter_submitter' => true, 'viewer_filter_watcher' => true, 'viewer_filter_editor' => true);
    }
    $config['empty_message'] = $empty;
    return array(
        'page'   => $page_key,
        'slug'   => lang('form-list'),
        'config' => $config,
        'tree'   => $widget_root(array(
            $el('div', 'list-group', lang('Request List'), array($loop(array($row)))),
        )),
    );
};

// A detail of the record's card: the label, then the value's nodes.
$rg_detail = function ($label, $children, $class = '') use ($el) {
    return array(
        $el('dt', 'col-sm-4 fw-normal text-body-secondary', lang('Label'), array(), array('text' => $label)),
        $el('dd', trim('col-sm-8 ' . $class), $label, $children),
    );
};

// The form detail settings of a widget: an e-mail page's (any reader: the
// e-mail is drawn for the submission just sent) or a record page's (the
// sender's own, and the staff's).
$rg_detail_widget = function ($page_key, $form_key, $missing, $access) {
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

// A record page: the not-found note, the sent notice and the new account,
// the toolbar, the card with the eyebrow, the title, the status and the
// details, the long fields and the note about messages.
$rg_record = function ($r) use ($el, $heading, $para, $span, $link, $icon, $attr, $bind, $show_when, $container, $row, $col, $edit_button, $sp_not_found, $sp_long_field) {
    $children = array(
        $el('div', 'alert alert-success d-flex gap-2', lang('Sent Notice'), array(
            $icon('bi-check-circle', 'mt-1'),
            $span($r['sent'], '', lang('Text')),
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
                $span(lang('My Registrations'), '', lang('Link Text')),
            ), array('href' => '{{page:my_registrations}}')),
            $edit_button(lang('Change Status')),
        )),
        $el('div', 'card border-0 shadow-sm mb-4', lang('Record Card'), array(
            $el('div', 'card-body p-4', lang('Card Body'), array(
                $el('div', 'd-flex flex-wrap align-items-start justify-content-between gap-2 mb-3', lang('Title'), array(
                    $el('div', '', lang('Words'), array(
                        $para($r['eyebrow'], 'psp-eyebrow small text-uppercase fw-semibold text-primary mb-1'),
                        $heading('h1', $r['title'], 'psp-display h3 mb-0', $bind('team')),
                    )),
                    $span($r['status'], 'badge rounded-pill bg-primary-subtle text-primary-emphasis fs-6', lang('Status'), $bind('status')),
                )),
                $el('dl', 'row small mb-0', lang('Details'), call_user_func_array('array_merge', $r['details'])),
            )),
        )),
    );
    foreach ($r['long'] as $l) $children[] = $sp_long_field($l[0], $l[1], $l[2]);
    $children[] = $el('div', 'd-flex gap-2 small text-body-secondary', lang('Note'), array(
        $icon('bi-chat-left-text', 'text-primary mt-1'),
        $span($r['note'], '', lang('Text')),
    ));
    return array('type' => 'root', 'props' => array(), 'children' => array(
        $container(array(
            $row(array(
                $col(array(
                    $sp_not_found('bi-clipboard-x', $r['not_found_title'], $r['missing'], lang('My Registrations'), 'my_registrations'),
                    $el('div', '', lang('Record'), $children, $show_when('record_found')),
                ), '', '8'),
            ), '', 'center'),
        ), 'py-5'),
    ));
};

// The messages under a record: the page's own Comments widget, shown only
// to whoever may read the record above it. Oldest first, all on one page,
// with a file each. No Reply, Share or follow box: the sender is e-mailed
// every answer and the club every message.
$rg_messages = function ($page_key, $empty) use ($comment_thread) {
    return array(
        'page'   => $page_key,
        'slug'   => lang('comments'),
        'config' => array('regionType' => 'comments', 'order' => 'oldest', 'featured' => 'none', 'layout' => 'flat', 'max_depth' => 1, 'per_page' => 0),
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

// The lines of a registration, for its e-mails.
$rg_mail_registration = function () use ($mail_box, $mail_line, $rg_value, $rg_child_name, $rg_sample_team, $rg_sample_birth, $rg_sample_medical) {
    return $mail_box(array(
        $mail_line(lang('Child'), $rg_child_name()),
        $mail_line(lang('Date of Birth'), $rg_value($rg_sample_birth, lang('Date of Birth'), 'child_birth_date')),
        $mail_line(lang('Age Group'), $rg_value($rg_sample_team, lang('Age Group'), 'team')),
        $mail_line(lang('Health notes'), $rg_value($rg_sample_medical, lang('Health notes'), 'medical_notes')),
    ), lang('Academy Registration'));
};
// The lines of a trial request, for its e-mails.
$rg_mail_trial = function () use ($mail_box, $mail_line, $rg_value, $rg_sample_trial_team, $rg_sample_position, $rg_sample_club, $experience_choices) {
    return $mail_box(array(
        $mail_line(lang('Team Name'), $rg_value($rg_sample_trial_team, lang('Team Name'), 'team')),
        $mail_line(lang('Playing Position'), $rg_value($rg_sample_position, lang('Playing Position'), 'position')),
        $mail_line(lang('Experience'), $rg_value($experience_choices[2][0], lang('Experience'), 'experience')),
        $mail_line(lang('Last club'), $rg_value($rg_sample_club, lang('Last club'), 'last_club')),
    ), lang('Trial Request'));
};
// The sender's lines of a staff e-mail.
$rg_mail_sender = function ($name) use ($mail_box, $mail_line, $rg_value, $rg_parent_name, $rg_sample_notes) {
    return $mail_box(array(
        $mail_line($name, $rg_parent_name()),
        $mail_line(lang('Email'), $rg_value('jane.cooper@example.com', lang('Email'), 'email')),
        $mail_line(lang('Phone'), $rg_value('+90 555 000 00 00', lang('Phone'), 'phone')),
        $mail_line(lang('Contact by'), $rg_value(lang('Phone'), lang('Contact by'), 'contact_method')),
        $mail_line(lang('Notes'), $rg_value($rg_sample_notes, lang('Notes'), 'notes')),
    ), lang('Sender'));
};
// A reference code line under a staff e-mail.
$rg_mail_reference = function () use ($el, $span, $bind, $mail_style) {
    return $el('p', 'small text-body-secondary mt-4 mb-0', lang('Reference'), array(
        $span(lang('Reference Code') . ': ', '', lang('Label')),
        $span('ABC123', '', lang('Reference Code'), $bind('reference_code')),
    ), $mail_style('font-size:13px;color:#4b5563;margin:24px 0 0'));
};
// The account the form opened for a sender who had none, in the reply
// e-mail.
$rg_mail_new_account = function () use ($el, $span, $link, $bind, $show_when, $mail_style) {
    return $el('div', 'border-top pt-3', lang('New Account'), array(
        $el('p', 'mb-2', lang('Sign-in Email'), array(
            $span(lang('We opened an account for you with this address:') . ' ', '', lang('Label')),
            $span('sample@example.com', 'fw-semibold', lang('Value'), $bind('__new_account_email')),
        ), $mail_style('margin:0 0 8px')),
        $el('p', 'mb-0', lang('Password'), array(
            $span(lang('To choose your password, use') . ' ', '', lang('Label')),
            $link(lang('Forgot Password'), '{{page:forgot_password}}', ''),
        ), $mail_style('margin:0')),
    ), array_merge($mail_style('border-top:1px solid #e5e7eb;padding-top:16px'), $show_when('has_new_account')));
};

// The contact fields every form ends with.
$rg_contact_fields = function ($prefix) use ($row, $col, $field, $input, $radios, $contact_choices) {
    return array(
        $row(array(
            $col(array($field($prefix . '-first-name', lang('First Name'), $input($prefix . '-first-name', 'text', 'first_name', 'given-name', true, array('contact_field' => 'first_name')))), '6'),
            $col(array($field($prefix . '-last-name', lang('Last Name'), $input($prefix . '-last-name', 'text', 'last_name', 'family-name', true, array('contact_field' => 'last_name')))), '6'),
            $col(array($field($prefix . '-email', lang('Email'), $input($prefix . '-email', 'email', 'email', 'email', true, array('contact_field' => 'email_address')))), '6'),
            $col(array($field($prefix . '-phone', lang('Phone'), $input($prefix . '-phone', 'tel', 'phone', 'tel', true, array('contact_field' => 'mobile_phone')))), '6'),
        ), '3'),
        $radios($prefix . '-contact', 'contact_method', lang('Contact by'), $contact_choices, $contact_choices[1][0]),
    );
};
// The end of every form: the status (the club's), the consent, the CAPTCHA
// and the button.
$rg_form_end = function ($prefix, $statuses, $consent, $button, $promise) use ($el, $field, $select, $agree, $captcha, $submit, $para, $loop) {
    return array(
        $field($prefix . '-status', lang('Status'), $select($prefix . '-status', 'status', '', $statuses, false, $statuses[0], array('office_use_only' => true))),
        $agree($prefix . '-consent', 'consent', $consent, true),
        $captcha(),
        $el('div', 'd-flex flex-wrap align-items-center gap-3', lang('Send Area'), array(
            $submit($button, 'btn btn-primary btn-lg rounded-pill px-4'),
            $para($promise, 'small text-body-secondary mb-0'),
        )),
        $loop(array()),
    );
};

$rg_missing = lang('This registration could not be found, or it is not yours to see.');
$rg_trial_missing = lang('This trial request could not be found, or it is not yours to see.');

$registration_widgets = array(

    // The academy registration: the fields are the page's form. The age
    // group starts with what a link sends (?team=). The Status is the
    // club's (office use only): every registration starts as Registration
    // received.
    'registration_form' => array(
        'page'   => 'academy_register',
        'slug'   => 'form',
        'config' => array('regionType' => 'custom_form', 'form_source' => 'page'),
        'tree'   => $widget_root(array_merge(array(
            $form_step(1, lang('Your child')),
            $row(array(
                $col(array($field('sp-rg-child-first', lang('Child\'s First Name'), $input('sp-rg-child-first', 'text', 'child_first_name', 'off'))), '6'),
                $col(array($field('sp-rg-child-last', lang('Child\'s Last Name'), $input('sp-rg-child-last', 'text', 'child_last_name', 'off'))), '6'),
                $col(array($field('sp-rg-birth', lang('Date of Birth'), $input('sp-rg-birth', 'date', 'child_birth_date', 'off'))), '6'),
                $col(array($field('sp-rg-team', lang('Age Group'), $select('sp-rg-team', 'team', lang('Choose an age group…'), $youth_team_titles, true, '', array('url_param' => 'team')))), '6'),
            ), '3'),
            $radios('sp-rg-experience', 'experience', lang('Experience'), $experience_choices, $experience_choices[0][0]),
            $field('sp-rg-medical', lang('Health notes'), $textarea('sp-rg-medical', 'medical_notes', 3, false), 'mb-3',
                lang('Allergies, asthma, medicines or anything the coach should know to keep your child safe. Only the coaches and the welfare officer read it.')),
            $field('sp-rg-notes', lang('Notes'), $textarea('sp-rg-notes', 'notes', 3, false), 'mb-3',
                lang('Anything else: friends in the group, days that do not suit you, a brother or sister at the club.')),
            $form_step(2, lang('About you')),
        ), $rg_contact_fields('sp-rg'), $rg_form_end('sp-rg', $registration_statuses,
            lang('I am the child\'s parent or guardian and agree that the club keeps these details, the health notes included, to arrange training, as its privacy notice explains.'),
            lang('Send the Registration'), lang('The academy calls you within a week.')))),
    ),

    'registration_detail' => $rg_detail_widget('registration_view', 'academy_register', $rg_missing, 'submitter_only') + array(
        'tree' => $rg_record(array(
            'sent'            => lang('Thank you, the registration has reached us. The academy calls you within a week to book a taster session; this page shows the status, and you can write to us below.'),
            'eyebrow'         => lang('Academy Registration'),
            'title'           => $rg_sample_team,
            'status'          => $registration_statuses[0],
            'details'         => array(
                $rg_detail(lang('Child'), $rg_child_name()),
                $rg_detail(lang('Date of Birth'), $rg_value($rg_sample_birth, lang('Date of Birth'), 'child_birth_date')),
                $rg_detail(lang('Experience'), $rg_value($experience_choices[1][0], lang('Experience'), 'experience')),
                $rg_detail(lang('Parent or Guardian'), $rg_parent_name()),
                $rg_detail(lang('Reference Code'), $rg_value('ABC123', lang('Reference Code'), 'reference_code'), 'mb-0 font-monospace'),
            ),
            'long'            => array(
                array(lang('Health notes'), 'medical_notes', $rg_sample_medical),
                array(lang('Notes'), 'notes', $rg_sample_notes),
            ),
            'note'            => lang('A question, or a change to the health notes? Write to us below; we answer here and by e-mail.'),
            'not_found_title' => lang('Registration not found'),
            'missing'         => $rg_missing,
        )),
    ),

    'registration_messages' => $rg_messages('registration_view',
        lang('No messages yet. The day of the taster session and our answer come here; you can write to us about your child too.')),

    'my_registrations_list' => $rg_list('my_registrations', 'academy_register', 'registration_view', false, array(), '',
        $rg_registration_row(false), lang('No registrations yet. Register a child, and the academy calls you within a week.')),

    'my_trials_list' => $rg_list('my_registrations', 'trial', 'trial_view', false, array(), '',
        $rg_trial_row(false), lang('No trial requests yet.')),

    'staff_registrations_list' => $rg_list('staff_registrations', 'academy_register', 'registration_view', true,
        array('child_first_name', 'child_last_name', 'first_name', 'last_name', 'email', 'team', 'status'),
        lang('Search by child, parent, age group or status...'), $rg_registration_row(true), lang('No academy registrations yet.')),

    // The trial request: the team and the position start with what a link
    // sends (?team=, ?position=). The Status is the club's (office use
    // only).
    'trial_form' => array(
        'page'   => 'trial',
        'slug'   => 'form',
        'config' => array('regionType' => 'custom_form', 'form_source' => 'page'),
        'tree'   => $widget_root(array_merge(array(
            $form_step(1, lang('Your game')),
            $row(array(
                $col(array($field('sp-tr-team', lang('Team Name'), $select('sp-tr-team', 'team', '', $trial_team_choices, true,
                    $trial_team_choices[count($trial_team_choices) - 1], array('url_param' => 'team')))), '6'),
                $col(array($field('sp-tr-position', lang('Playing Position'), $select('sp-tr-position', 'position', '', $rg_position_choices, false,
                    $rg_position_choices[count($rg_position_choices) - 1], array('url_param' => 'position')))), '6'),
                $col(array($field('sp-tr-birth', lang('Date of Birth'), $input('sp-tr-birth', 'date', 'birth_date', 'bday'))), '6'),
                $col(array($field('sp-tr-club', lang('Last club'), $input('sp-tr-club', 'text', 'last_club', 'off', false))), '6'),
            ), '3'),
            $radios('sp-tr-experience', 'experience', lang('Experience'), $experience_choices, $experience_choices[1][0]),
            $field('sp-tr-notes', lang('Notes'), $textarea('sp-tr-notes', 'notes', 3, false), 'mb-3',
                lang('The evenings you can train, an injury the coach should know about, or anything else.')),
            $form_step(2, lang('How to reach you')),
        ), $rg_contact_fields('sp-tr'), $rg_form_end('sp-tr', $trial_statuses,
            lang('I agree that the club keeps these details to arrange the trial, as its privacy notice explains.'),
            lang('Ask for a Trial'), lang('The coach writes to you within a week.')))),
    ),

    'trial_detail' => $rg_detail_widget('trial_view', 'trial', $rg_trial_missing, 'submitter_only') + array(
        'tree' => $rg_record(array(
            'sent'            => lang('Thank you, your trial request has reached us. The coach writes to you within a week with a training night; this page shows the status, and you can write to us below.'),
            'eyebrow'         => lang('Trial Request'),
            'title'           => $rg_sample_trial_team,
            'status'          => $trial_statuses[0],
            'details'         => array(
                $rg_detail(lang('Player Name'), $rg_parent_name()),
                $rg_detail(lang('Playing Position'), $rg_value($rg_sample_position, lang('Playing Position'), 'position')),
                $rg_detail(lang('Experience'), $rg_value($experience_choices[2][0], lang('Experience'), 'experience')),
                $rg_detail(lang('Last club'), $rg_value($rg_sample_club, lang('Last club'), 'last_club')),
                $rg_detail(lang('Reference Code'), $rg_value('ABC123', lang('Reference Code'), 'reference_code'), 'mb-0 font-monospace'),
            ),
            'long'            => array(array(lang('Notes'), 'notes', $rg_sample_notes)),
            'note'            => lang('A question about the trial? Write to us below; we answer here and by e-mail.'),
            'not_found_title' => lang('Trial request not found'),
            'missing'         => $rg_trial_missing,
        )),
    ),

    'trial_messages' => $rg_messages('trial_view',
        lang('No messages yet. The training night and the coach\'s answer come here; you can write to us too.')),

    'staff_trials_list' => $rg_list('staff_trials', 'trial', 'trial_view', true,
        array('first_name', 'last_name', 'email', 'phone', 'team', 'position', 'status'),
        lang('Search by name, team, position or status...'), $rg_trial_row(true), lang('No trial requests yet.')),

    // The e-mail pages' widgets: drawn for the submission just sent.
    'email_registration_admin' => $rg_detail_widget('email_registration_new', 'academy_register', lang('This registration was not found.'), 'public') + array(
        'tree' => $widget_root(array(
            $mail_heading(lang('A new academy registration')),
            $mail_para(lang('It came from the academy form. Call the family to book a taster session, write the day under the registration on its page and set the status; what you write there reaches the family by e-mail.')),
            $rg_mail_registration(),
            $rg_mail_sender(lang('Parent or Guardian')),
            $mail_button(lang('Open the registration'), '{{page:registration_view}}?r=^^reference_code^^'),
            $rg_mail_reference(),
        )),
    ),
    'email_registration_parent' => $rg_detail_widget('email_registration_received', 'academy_register', lang('This registration was not found.'), 'public') + array(
        'tree' => $widget_root(array(
            $mail_heading(lang('We received your academy registration')),
            $mail_greeting(),
            $mail_para(lang('Thank you. The coach of the age group calls you within a week to book a free taster session; the status and our answers are on the registration\'s page and come by e-mail.')),
            $rg_mail_registration(),
            $mail_button(lang('Open my registration'), '{{page:registration_view}}?r=^^reference_code^^'),
            $rg_mail_new_account(),
        )),
    ),
    'email_trial_admin' => $rg_detail_widget('email_trial_new', 'trial', lang('This trial request was not found.'), 'public') + array(
        'tree' => $widget_root(array(
            $mail_heading(lang('A new trial request')),
            $mail_para(lang('It came from the trial form. Pass it to the coach of the team, write the training night under the request on its page and set the status; what you write there reaches the player by e-mail.')),
            $rg_mail_trial(),
            $rg_mail_sender(lang('Player Name')),
            $mail_button(lang('Open the request'), '{{page:trial_view}}?r=^^reference_code^^'),
            $rg_mail_reference(),
        )),
    ),
    'email_trial_player' => $rg_detail_widget('email_trial_received', 'trial', lang('This trial request was not found.'), 'public') + array(
        'tree' => $widget_root(array(
            $mail_heading(lang('We received your trial request')),
            $mail_greeting(),
            $mail_para(lang('Thank you. The coach of the team writes to you within a week with a training night to come to; the status and our answers are on the request\'s page.')),
            $rg_mail_trial(),
            $mail_button(lang('Open my request'), '{{page:trial_view}}?r=^^reference_code^^'),
            $rg_mail_new_account(),
        )),
    ),

    // The comment of the reply e-mail, drawn for the comment the e-mail is
    // about, flat and alone.
    'email_comment' => array(
        'page'   => 'email_reply',
        'slug'   => lang('comments'),
        'config' => array('regionType' => 'comments', 'order' => 'oldest', 'featured' => 'none', 'layout' => 'flat', 'max_depth' => 1, 'per_page' => 0),
        'tree'   => $mail_comment_tree,
    ),
);

return array('pages' => $registration_pages, 'widgets' => $registration_widgets);
