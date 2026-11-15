<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Mobile App Developer", its support: the support
 * request, the request's own page with its messages, My Support Requests
 * and the staff's list; the privacy page the consent boxes link, the
 * e-mails of the support form and the new message e-mail, and the widgets
 * that draw them. Included after parts.php and mail.php in the same scope;
 * returns array('pages' => ..., 'widgets' => ...).
 *
 * A support request is a record of the Support Requests form, as a
 * valuation is in the Real Estate Agency template. A visitor without an
 * account gets one (auto-registration), so the request's page and My
 * Support Requests show it at once. Its Status is an office-use-only field
 * the developer changes in the control panel; the messages under it are
 * the page's comments, mailed to the user and to the developer.
 *
 * The form's App starts with the app the app page sends (?app=, the app's
 * name). The screenshot or log a user sends is kept in the template's
 * private folder: the request's page links it for whoever may read the
 * request.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

$support_pages = array();

// The side column of the support page: what happens next, then the quick
// answers that may save the user the wait.
$support_aside = function () use ($el, $heading, $para, $link, $icon, $span) {
    $steps = array(
        lang('Your request opens its own page, and an account for you if you have none.'),
        lang('I read every request myself and answer within two working days.'),
        lang('When a fix is on its way, the status says which update brings it.'),
    );
    $items = array();
    foreach ($steps as $n => $text) {
        $items[] = $el('li', $n < count($steps) - 1 ? 'mb-2' : '', lang('Step'), array(), array('text' => $text));
    }
    $quick = function ($icon_name, $text, $key) use ($el, $icon, $span) {
        return $el('a', 'd-flex align-items-center gap-2 link-body-emphasis text-decoration-none py-1', lang('Link'), array(
            $icon($icon_name, 'text-primary'),
            $span($text, '', lang('Link Text')),
        ), array('href' => '{{page:' . $key . '}}'));
    };
    return array(
        $el('div', 'p-4 rounded-4 bg-body-tertiary mb-4', lang('How It Works'), array(
            $heading('h2', lang('What happens next'), 'h5 fw-semibold mb-3'),
            $el('ol', 'small text-body-secondary ps-3 mb-0', lang('List'), $items),
        )),
        $el('div', 'p-4 rounded-4 border', lang('Quick Answers'), array(
            $heading('h2', lang('Before you write'), 'h5 fw-semibold mb-2'),
            $para(lang('Many problems are gone after the newest update: check the version in the app\'s settings.'), 'small text-body-secondary mb-3'),
            $quick('bi-question-circle', lang('Frequently asked questions'), 'faq'),
            $quick('bi-stars', lang('What\'s New in each update'), 'releases'),
            $quick('bi-shield-lock', lang('Privacy Policy'), 'privacy'),
        )),
    );
};

// A My list page: the header, the list, and a card that sends a new request.
$my_page = function ($title, $lead, $list_widget, $new_title, $new_text, $new_link_text, $new_key, $new_icon) use ($page, $page_header, $el, $container, $row, $col, $widget, $icon, $span, $para, $link) {
    return $page(array(
        $page_header($title, $lead),
        $el('section', 'py-5', lang('Content'), array(
            $container(array(
                $row(array(
                    $col(array($widget($list_widget)), '', '8'),
                    $col(array(
                        $el('div', 'p-4 rounded-4 bg-body-tertiary', lang('New Request'), array(
                            $el('p', 'd-flex align-items-center gap-2 fw-semibold mb-2', lang('Title'), array(
                                $icon($new_icon, 'text-primary'),
                                $span($new_title, '', lang('Text')),
                            )),
                            $para($new_text, 'small text-body-secondary mb-3'),
                            $link($new_link_text, '{{page:' . $new_key . '}}', 'btn btn-sm btn-primary rounded-pill px-3'),
                        )),
                    ), '', '4'),
                ), '4', '', 'g-lg-5'),
            )),
        )),
    ), false);
};

// A staff list page.
$staff_list_page = function ($title, $lead, $list_widget) use ($staff_page, $page_header, $el, $container, $widget) {
    return $staff_page(array(
        $page_header($title, $lead),
        $el('section', 'py-5', lang('Content'), array(
            $container(array($widget($list_widget))),
        )),
    ));
};

// ── Pages ───────────────────────────────────────────────────────────────

// The support request. What is sent opens the request's own page.
$support_pages[] = array(
    'key'              => 'support',
    'name'             => lang('support'),
    'folder'           => 'public',
    'title'            => lang('Support'),
    'meta_description' => lang('Something not working in one of my apps, or a question about it? Write to me; I answer every request within two working days.'),
    'form'             => array(
        'form_name'            => lang('Support Requests'),
        'confirmation_message' => lang('Thank you, your support request has reached me. I answer within two working days.'),
        'confirmation_page_id' => '{{tab:support_view}}',
        'auto_registration'    => 1,
        'notify_email'         => '{{site_email}}',
        'notify_subject'       => lang('New support request: ^^app^^ – ^^subject^^'),
        'notify_page_id'       => '{{tab:email_ticket_new}}',
        'confirm_email'        => 1,
        'confirm_subject'      => lang('We received your support request'),
        'confirm_page_id'      => '{{tab:email_ticket_received}}',
    ),
    'tree'             => $page(array(
        $page_header(lang('Support'),
            lang('Something not working, a question or an idea? Tell me which app and what happened; I answer every request myself.')),
        $el('section', 'py-5', lang('Content'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $el('div', 'card border-0 shadow-sm rounded-4', lang('Form Card'), array(
                            $el('div', 'card-body p-4 p-lg-5', lang('Card Body'), array($widget('support_form'))),
                        )),
                    ), '', '8'),
                    $col($support_aside(), '', '4'),
                ), '4', '', 'g-lg-5'),
            )),
        ), $searchable('support', lang('help, support, bug, crash, problem, refund, subscription'))),
    ), false),
);

// One support request: the request, its status and the messages under it.
$support_pages[] = array(
    'key'      => 'support_view',
    'name'     => lang('support-request'),
    'folder'   => 'registration',
    'title'    => lang('Support Request'),
    'search'   => false,
    'sitemap'  => false,
    'comments' => array('label' => lang('Message'), 'allow_new' => true, 'auto_publish' => true, 'show_date' => true, 'login' => true,
                        'attachments' => true,
                        'email_page' => '{{tab:email_reply}}', 'email_subject' => lang('There is a new message about your support request'),
                        'notify_email' => '{{site_email}}'),
    'tree'     => $page(array(
        $widget('ticket_detail'),
        $comments_section(lang('Messages'), 'ticket_messages'),
    ), false),
);

// My Support Requests: the user's requests (for the staff, everybody's).
$support_pages[] = array(
    'key'     => 'my_tickets',
    'name'    => lang('my-support-requests'),
    'folder'  => 'registration',
    'title'   => lang('My Support Requests'),
    'search'  => false,
    'sitemap' => false,
    'tree'    => $my_page(lang('My Support Requests'), lang('Your support requests, their status and my answers, newest first.'),
        'my_tickets_list', lang('Something else?'), lang('Send a new request; your details are filled in for you.'),
        lang('New Support Request'), 'support', 'bi-life-preserver'),
);

// The staff's list of every support request, newest first, with a search.
$support_pages[] = array(
    'key'     => 'staff_tickets',
    'name'    => lang('all-support-requests'),
    'folder'  => 'private',
    'title'   => lang('All Support Requests'),
    'search'  => false,
    'sitemap' => false,
    'tree'    => $staff_list_page(lang('All Support Requests'), lang('Every support request, newest first: set the status and answer under each.'), 'staff_tickets_list'),
);

// The privacy notice the consent boxes link to: the site's data and the
// apps' data, sample text with a note to adapt it. Each app page carries
// its own privacy summary.
$support_pages[] = array(
    'key'              => 'privacy',
    'name'             => lang('privacy-policy'),
    'folder'           => 'public',
    'title'            => lang('Privacy Policy'),
    'meta_description' => lang('What this site and my apps keep about you, why, and what you can ask of me.'),
    'tree'             => $page(array(
        $page_header(lang('Privacy Policy'), lang('What this site and my apps keep about you, why, and what you can ask of me.')),
        $el('section', 'py-5', lang('Your Data'), array(
            $container(array($row(array($col(array(
                $el('div', 'alert alert-secondary d-flex gap-2 small', lang('Sample Text Note'), array(
                    $icon('bi-info-circle', 'mt-1'),
                    $span(lang('Sample text: adapt it to your apps, the stores\' privacy labels and the data protection law that applies to you before you publish.'), '', lang('Text')),
                ), array('_attrs' => array($attr('role', 'note')))),
                $heading('h2', lang('This site'), 'h4 fw-semibold mb-3'),
                $para(lang('When you send a support request or a project request, join a beta, sign up for the newsletter or open an account, I keep your name, your contact details, what you write and the screenshots and files you send, to answer you and to fix what you report.'), 'mb-4'),
                $heading('h2', lang('My apps'), 'h4 fw-semibold mb-3'),
                $para(lang('Each app\'s page says what that app keeps and where. As a rule my apps keep your data on your phone and in your own cloud, collect no advertising data and send crash reports only when you allow it.'), 'mb-4'),
                $heading('h2', lang('Your rights'), 'h4 fw-semibold mb-3'),
                $para(lang('I keep data as long as the law requires, never sell it and never give it to advertisers. You can see and change your account data from My Account, and ask me to send or delete the rest with a support request.'), 'mb-0'),
            ), '', '8'))))),
        ), $searchable('your-data', lang('privacy, personal data, KVKK, GDPR, delete my data, tracking'))),
    )),
);

// The e-mails: what the developer receives and the reply to the sender of
// the support form, and the new message e-mail.
$support_pages[] = array_merge($mail_settings, array(
    'key'   => 'email_ticket_new',
    'name'  => lang('email-new-support-request'),
    'title' => lang('New Support Request E-mail'),
    'tree'  => $mail_page(array($widget('email_ticket_admin'))),
));
$support_pages[] = array_merge($mail_settings, array(
    'key'   => 'email_ticket_received',
    'name'  => lang('email-support-request-received'),
    'title' => lang('Support Request Received E-mail'),
    'tree'  => $mail_page(array($widget('email_ticket_client'))),
));
// A new message or reply: sent to the user of a support request or a
// project, the sender of a conversation and the visitors watching a page.
// Its Comments widget draws the comment in the card of mail.php; the
// System region after it keeps the fixed comment for an e-mail the widget
// does not draw.
$support_pages[] = array_merge($mail_settings, array(
    'key'   => 'email_reply',
    'name'  => lang('email-new-reply'),
    'title' => lang('New Reply E-mail'),
    'tree'  => $mail_page(array(
        $mail_heading(lang('There is a new message')),
        $mail_para(lang('Something new was written on a page you follow: your support request, your project, a conversation or an article. You can read it below and answer it on the page.')),
        $widget('email_comment'),
        array('type' => 'region', 'props' => array('regionType' => 'system', 'regionName' => ''), 'children' => array()),
    )),
));

// ── Widget pieces ───────────────────────────────────────────────────────

// The user's name.
$person_name = function () use ($span, $bind) {
    return array(
        $span(lang('Jane'), '', lang('First Name'), $bind('first_name')),
        $span(' ', '', lang('Text')),
        $span(lang('Cooper'), '', lang('Last Name'), $bind('last_name')),
    );
};

$sample_ticket_app = $apps['tally']['name'];
$sample_ticket_subject = lang('The widget stops updating after midnight');
$sample_ticket_message = lang('Since the last update the home screen widget still shows yesterday\'s habits in the morning. Opening the app fixes it until the next day.');
$sample_device = lang('iPhone 15, iOS 18.1');

// A support request in a list: the subject, the status, the app, the kind
// and the date, linked to its page; the staff also see who sent it.
$ticket_row = function ($staff = false) use ($el, $span, $icon, $bind, $person_name, $sample_ticket_app, $sample_ticket_subject, $ticket_kinds, $ticket_statuses) {
    $item = function ($icon_name, $label, $sample, $field) use ($el, $span, $icon, $bind) {
        return $el('span', 'd-inline-flex align-items-center gap-1', $label, array($icon($icon_name), $span($sample, '', $label, $bind($field))));
    };
    $items = array(
        $item('bi-phone', lang('App'), $sample_ticket_app, 'app'),
        $item('bi-tag', lang('Kind'), $ticket_kinds[0], 'kind'),
        $item('bi-calendar3', lang('Date'), lang('Date'), 'submitted_date_and_time'),
    );
    if ($staff) {
        $items[] = $item('bi-envelope', lang('Email'), 'jane.cooper@example.com', 'email');
        $title = $el('span', 'fw-semibold', lang('User'), array_merge($person_name(), array(
            $span(' · ', 'text-body-secondary', lang('Text')),
            $span($sample_ticket_subject, 'text-body-secondary fw-normal', lang('Subject'), $bind('subject')),
        )));
    } else {
        $items[] = $item('bi-chat-left-text', lang('Messages'), '0', 'number_of_comments');
        $title = $span($sample_ticket_subject, 'fw-semibold', lang('Subject'), $bind('subject'));
    }
    return $el('a', 'list-group-item list-group-item-action p-3', lang('Support Request'), array(
        $el('div', 'd-flex flex-wrap align-items-center justify-content-between gap-2 mb-1', lang('Top Line'), array(
            $title,
            $span($ticket_statuses[0], 'badge rounded-pill bg-primary-subtle text-primary-emphasis', lang('Status'), $bind('status')),
        )),
        $el('div', 'd-flex flex-wrap column-gap-3 row-gap-1 small text-body-secondary', lang('Details'), $items),
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
$bound = function ($sample, $field) use ($bind) {
    return array_merge(array('text' => $sample), $bind($field));
};

$ticket_missing = lang('This support request could not be found, or it is not yours to see.');

// The messages under a request: the page's own Comments widget, shown only
// to whoever may read the request above it. Oldest first, all on one page,
// with a file each. No Reply, Share or follow box: the user is e-mailed
// every answer and the developer every message.
$ticket_messages = array(
    'page'   => 'support_view',
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
        'empty'     => lang('No messages yet. Add a detail or another screenshot here.'),
        'heading'   => lang('Write to the developer'),
        'label'     => lang('Your message'),
        'sign_in'   => lang('Sign in to write about your request.'),
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

// A list of requests: the user's own (the staff see them all), or the
// staff's with a search.
$ticket_list = function ($page_key, $staff) use ($widget_root, $el, $loop, $ticket_row) {
    $config = array(
        'regionType'          => 'form_list_view',
        'custom_form_page_id' => '{{tab:support}}',
        'detail_page_id'      => '{{tab:support_view}}',
        'items_per_page'      => 20,
        'order_by_field'      => 'submitted_date_and_time',
        'order_by_direction'  => 'DESC',
    );
    if ($staff) {
        $config += array(
            'search_enabled' => true,
            'search_fields'  => array('first_name', 'last_name', 'email', 'app', 'kind', 'subject', 'status'),
            'search_width'   => 'half',
            'search_label'   => lang('Search by user, app, kind, subject or status...'),
            'empty_message'  => lang('No support requests yet.'),
        );
    } else {
        $config += array(
            'viewer_filter'           => true,
            'viewer_filter_submitter' => true,
            'viewer_filter_watcher'   => true,
            'viewer_filter_editor'    => true,
            'empty_message'           => lang('No support requests yet. If something does not work in an app, tell me here.'),
        );
    }
    return array(
        'page'   => $page_key,
        'slug'   => lang('form-list'),
        'config' => $config,
        'tree'   => $widget_root(array(
            $el('div', 'list-group', lang('Request List'), array($loop(array($ticket_row($staff))))),
        )),
    );
};

// The lines of a support request, for its e-mails.
$mail_ticket = function () use ($mail_box, $mail_line, $span, $bind, $sample_ticket_app, $sample_ticket_subject, $ticket_kinds, $sample_device) {
    return $mail_box(array(
        $mail_line(lang('Subject'), array($span($sample_ticket_subject, '', lang('Subject'), $bind('subject')))),
        $mail_line(lang('App'), array($span($sample_ticket_app, '', lang('App'), $bind('app')))),
        $mail_line(lang('Kind'), array($span($ticket_kinds[0], '', lang('Kind'), $bind('kind')))),
        $mail_line(lang('App version'), array($span('3.2.0', '', lang('App version'), $bind('app_version')))),
        $mail_line(lang('Device and system'), array($span($sample_device, '', lang('Device and system'), $bind('device')))),
    ), lang('Support Request'));
};
// A reference code line under the developer's e-mail.
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
    ), array_merge($mail_style('border-top:1px solid #ede9fe;padding-top:16px'), $show_when('has_new_account')));
};
// The form detail settings of an e-mail page's widget.
$mail_detail = function ($page_key, $form, $missing) {
    return array(
        'page'   => $page_key,
        'slug'   => lang('form-detail'),
        'config' => array(
            'regionType'          => 'form_item_view',
            'custom_form_page_id' => '{{tab:' . $form . '}}',
            'access_control'      => 'public',
            'not_found_message'   => $missing,
        ),
    );
};

$support_widgets = array(

    // The support request: the fields are the page's form. The App starts
    // with the app the app page sends (?app=). The Status is the staff's
    // (office use only): every request starts as Request received.
    'support_form' => array(
        'page'   => 'support',
        'slug'   => 'form',
        'config' => array('regionType' => 'custom_form', 'form_source' => 'page'),
        'tree'   => $widget_root(array(
            $form_step(1, lang('The app and what happened')),
            $row(array(
                $col(array($field('ma-st-app', lang('App'), $select('ma-st-app', 'app', lang('Choose an app…'), $support_app_choices, true, '', array('url_param' => 'app')))), '6'),
                $col(array($field('ma-st-kind', lang('Kind'), $select('ma-st-kind', 'kind', lang('Choose what it is about…'), $ticket_kinds))), '6'),
            ), '3'),
            $field('ma-st-subject', lang('Subject'), $input('ma-st-subject', 'text', 'subject', 'off'), 'mb-3',
                lang('In a few words, for example "Sync stops after the update".')),
            $field('ma-st-message', lang('What happened?'), $textarea('ma-st-message', 'message', 5), 'mb-3',
                lang('What you did, what you expected and what happened instead. For an idea, what it would let you do.')),
            $row(array(
                $col(array($field('ma-st-version', lang('App version'), $input('ma-st-version', 'text', 'app_version', 'off', false), 'mb-3',
                    lang('In the app\'s settings, under About.'))), '6'),
                $col(array($field('ma-st-device', lang('Device and system'), $input('ma-st-device', 'text', 'device', 'off', false), 'mb-3',
                    lang('For example "iPhone 15, iOS 18.1" or "Pixel 8, Android 15".'))), '6'),
            ), '3'),
            $field('ma-st-file', lang('Screenshot or file'), $file('ma-st-file', 'attachment', 'private'), 'mb-3',
                lang('A screenshot, a screen recording\'s still or a log (a picture, PDF or text). More can follow under your request.')),
            $form_step(2, lang('How to reach you')),
            $row(array(
                $col(array($field('ma-st-first-name', lang('First Name'), $input('ma-st-first-name', 'text', 'first_name', 'given-name', true, array('contact_field' => 'first_name')))), '6'),
                $col(array($field('ma-st-last-name', lang('Last Name'), $input('ma-st-last-name', 'text', 'last_name', 'family-name', true, array('contact_field' => 'last_name')))), '6'),
            ), '3'),
            $field('ma-st-email', lang('Email'), $input('ma-st-email', 'email', 'email', 'email', true, array('contact_field' => 'email_address')), 'mb-3',
                lang('Use the address of your store account if the request is about a purchase.')),
            $field('ma-st-status', lang('Status'), $select('ma-st-status', 'status', '', $ticket_statuses, false, $ticket_statuses[0], array('office_use_only' => true))),
            $agree('ma-st-consent', 'consent', lang('I agree that the developer keeps these details and my files to answer me and fix the app, as the privacy notice explains.'), true),
            $captcha(),
            $el('div', 'd-flex flex-wrap align-items-center gap-3', lang('Send Area'), array(
                $submit(lang('Send the Request'), 'btn btn-primary btn-lg rounded-pill px-4'),
                $para(lang('An answer within two working days.'), 'small text-body-secondary mb-0'),
            )),
            $loop(array()),
        )),
    ),

    // One support request: the request, its status and what the user wrote;
    // the messages follow as comments.
    'ticket_detail' => array(
        'page'   => 'support_view',
        'slug'   => lang('form-detail'),
        'config' => array(
            'regionType'          => 'form_item_view',
            'custom_form_page_id' => '{{tab:support}}',
            'access_control'      => 'submitter_only',
            'not_found_message'   => $ticket_missing,
        ),
        'tree'   => array('type' => 'root', 'props' => array(), 'children' => array(
            $container(array(
                $row(array(
                    $col(array(
                        $el('div', 'text-center py-5', lang('Not Found'), array(
                            $icon('bi-life-preserver', 'display-5 text-body-tertiary'),
                            $heading('h2', lang('Support request not found'), 'h3 fw-semibold mt-3'),
                            $para($ticket_missing, 'text-body-secondary mb-4', $bind('not_found')),
                            $link(lang('My Support Requests'), '{{page:my_tickets}}', 'btn btn-primary rounded-pill px-4'),
                        ), $show_when('record_not_found')),
                        $el('div', '', lang('Record'), array(
                            $el('div', 'alert alert-success d-flex gap-2', lang('Sent Notice'), array(
                                $icon('bi-check-circle', 'mt-1'),
                                $span(lang('Thank you, your request has reached me. I answer within two working days; this page shows the status, and you can add details below.'), '', lang('Text')),
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
                                    $span(lang('My Support Requests'), '', lang('Link Text')),
                                ), array('href' => '{{page:my_tickets}}')),
                                $edit_button(lang('Change Status')),
                            )),
                            $el('div', 'card border-0 shadow-sm rounded-4 mb-4', lang('Request Card'), array(
                                $el('div', 'card-body p-4', lang('Card Body'), array(
                                    $el('div', 'd-flex flex-wrap align-items-start justify-content-between gap-2 mb-3', lang('Title'), array(
                                        $el('div', '', lang('Words'), array(
                                            $para(lang('Support Request'), 'small text-uppercase fw-semibold text-primary mb-1'),
                                            $heading('h1', $sample_ticket_subject, 'h3 fw-semibold mb-0', $bind('subject')),
                                        )),
                                        $span($ticket_statuses[0], 'badge rounded-pill bg-primary-subtle text-primary-emphasis fs-6', lang('Status'), $bind('status')),
                                    )),
                                    $el('dl', 'row small mb-0', lang('Details'), array_merge(
                                        $detail(lang('App'), '', lang('App'), $bound($sample_ticket_app, 'app')),
                                        $detail(lang('Kind'), '', lang('Kind'), $bound($ticket_kinds[0], 'kind')),
                                        $detail(lang('App version'), '', lang('App version'), $bound('3.2.0', 'app_version')),
                                        $detail(lang('Device and system'), '', lang('Device and system'), $bound($sample_device, 'device')),
                                        $detail(lang('Sent by'), '', lang('User'), $person_name(), true),
                                        $detail(lang('Screenshot or file'), 'text-break', lang('Attachment'), array(
                                            $link('screenshot.png', '#', '', array(), array('target' => '_blank', 'rel' => 'noopener',
                                                '_bindings' => array('href' => 'attachment', 'text' => 'attachment'))),
                                        ), true),
                                        $detail(lang('Reference Code'), 'mb-0 font-monospace', lang('Reference Code'), $bound('ABC123', 'reference_code'))
                                    )),
                                )),
                            )),
                            $el('div', 'pg-hide-if-empty p-4 rounded-4 bg-body-tertiary mb-4', lang('What happened?'), array(
                                $para(lang('What happened?'), 'small fw-semibold text-body-secondary mb-2'),
                                $el('div', 'pg-field-value lh-lg', lang('Value'), array(), array_merge(array('text' => $sample_ticket_message), $bind('message'))),
                            )),
                            $el('div', 'd-flex gap-2 small text-body-secondary', lang('Note'), array(
                                $icon('bi-chat-left-text', 'text-primary mt-1'),
                                $span(lang('Another screenshot or a detail you remembered? Write below; I answer here and by e-mail.'), '', lang('Text')),
                            )),
                        ), $show_when('record_found')),
                    ), '', '8'),
                ), '', 'center'),
            ), 'py-5'),
        )),
    ),

    'ticket_messages' => $ticket_messages,

    'my_tickets_list' => $ticket_list('my_tickets', false),

    'staff_tickets_list' => $ticket_list('staff_tickets', true),

    // The e-mail pages' widgets: drawn for the submission just sent.
    'email_ticket_admin' => $mail_detail('email_ticket_new', 'support', lang('The support request could not be found.')) + array(
        'tree' => $widget_root(array(
            $mail_heading(lang('A new support request')),
            $mail_para(lang('It came from the support form. Answer under the request on its page and set its status there; what you write reaches the user by e-mail.')),
            $mail_ticket(),
            $mail_box(array(
                $mail_line(lang('Sent by'), $person_name()),
                $mail_line(lang('Email'), array($span('jane.cooper@example.com', '', lang('Email'), $bind('email')))),
                $mail_line(lang('What happened?'), array($span($sample_ticket_message, '', lang('Text'), $bind('message')))),
            ), lang('User')),
            $mail_button(lang('Open the request'), '{{page:support_view}}?r=^^reference_code^^'),
            $mail_reference(),
        )),
    ),
    'email_ticket_client' => $mail_detail('email_ticket_received', 'support', lang('The support request could not be found.')) + array(
        'tree' => $widget_root(array(
            $mail_heading(lang('We received your support request')),
            $mail_greeting(),
            $mail_para(lang('Thank you for writing. I read every request myself and answer within two working days.')),
            $mail_ticket(),
            $mail_para(lang('Another screenshot or a detail? Add it on the request\'s page; my answer comes there too.')),
            $mail_button(lang('Open my request'), '{{page:support_view}}?r=^^reference_code^^'),
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

return array('pages' => $support_pages, 'widgets' => $support_widgets);
