<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Art Gallery", its requests: the enquiry about a work,
 * the enquiry's own page with its messages, My Enquiries and the staff's
 * list; the private viewing request, its page, My Private Viewings and the
 * staff's list; the privacy page the consent boxes link; the e-mails of
 * both forms and the new message e-mail every thread of the site sends,
 * and the widgets that draw them. Included after parts.php, cards.php and
 * mail.php in the same scope; returns array('pages' => ..., 'widgets' => ...).
 *
 * An enquiry is a record of the Artwork Enquiries form, never a price
 * commitment or a sale: a work is sold through the gallery. The gallery
 * sets its office-use-only Status (answered, reserved for you, sold,
 * closed); its answers are the page's comments, mailed to the visitor and
 * to the gallery. A visitor without an account gets one
 * (auto-registration), so the enquiry's page and My Enquiries show it at
 * once.
 *
 * A private viewing request is a record of the Private Viewings form: the
 * exhibition, a day not before today, the time of day and the party. The
 * gallery confirms it or offers another time on the request's page.
 *
 * The enquiry's work and kind start with what a link sends (?artwork=, a
 * work's title as the work page writes it; ?kind=, an answer of the Kind
 * list as the advisory cards write it); the viewing's exhibition with
 * ?exhibition= (an exhibition's title, as its page writes it).
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

// The samples the canvas shows. The day is words, never a date: a fixed
// day would read as a past week a year later.
$sample_work = $work_choices[0];
$sample_kind = $enquiry_choices[0];
$sample_message = lang('I saw this painting at the opening and keep thinking about it. Could you tell me its price with framing, and whether I could see it again in daylight?');
$sample_exhibition = $exhibition_choices[0];
$sample_day = lang('Preferred day');
$sample_time = $time_choices[1];
$sample_party = $party_choices[1];
$sample_note = lang('We are furnishing a new flat and would like to see the larger paintings together.');

// ── Page shells ─────────────────────────────────────────────────────────

// The side column of a request page: what happens next, then the
// gallery's opening hours and a link.
$request_aside = function ($steps, $link_text, $link_href) use ($el, $heading, $link, $check_list, $opening_hours) {
    $items = array();
    foreach ($steps as $n => $text) {
        $items[] = $el('li', $n < count($steps) - 1 ? 'mb-2' : '', lang('Step'), array(), array('text' => $text));
    }
    $hours = array();
    foreach ($opening_hours as $h) $hours[] = $h['days'] . ': ' . $h['hours'];
    return array(
        $el('div', 'p-4 bg-body-tertiary mb-4', lang('How It Works'), array(
            $heading('h2', lang('What happens next'), 'pgl-display h5 mb-3'),
            $el('ol', 'small text-body-secondary ps-3 mb-0', lang('List'), $items),
        )),
        $el('div', 'p-4 border', lang('Opening Hours'), array(
            $heading('h2', lang('Opening hours'), 'pgl-display h5 mb-3'),
            $check_list($hours, 'list-unstyled small mb-3'),
            $link($link_text, $link_href, 'fw-semibold link-primary text-decoration-none small'),
        )),
    );
};

// A form page: the header, the form in a framed card and the side column.
$request_page = function ($title, $lead, $widget_key, $aside) use ($page, $page_header, $el, $container, $row, $col, $widget) {
    return $page(array(
        $page_header($title, $lead),
        $el('section', 'py-5', lang('Content'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $el('div', 'border p-4 p-lg-5', lang('Form Card'), array($widget($widget_key))),
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

// A visitor's own list: the list, then a box that sends a new one.
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
                            $el('div', 'p-4 bg-body-tertiary', lang('New Request'), array(
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

// The enquiry about a work. What is sent opens the enquiry's own page.
$request_pages[] = array(
    'key'              => 'enquire',
    'name'             => lang('ask-about-a-work'),
    'folder'           => 'public',
    'title'            => lang('Ask About a Work'),
    'meta_description' => lang('Ask the gallery about a work: its price, its condition, framing and delivery, or a commission. We answer you personally.'),
    'form'             => array(
        'form_name'            => lang('Artwork Enquiries'),
        'confirmation_message' => lang('Thank you, your enquiry has reached the gallery. We answer on your enquiry\'s page and by e-mail.'),
        'confirmation_page_id' => '{{tab:enquiry_view}}',
        'auto_registration'    => 1,
        'notify_email'         => '{{site_email}}',
        'notify_subject'       => lang('New enquiry: ^^artwork^^'),
        'notify_page_id'       => '{{tab:email_enquiry_new}}',
        'confirm_email'        => 1,
        'confirm_subject'      => lang('We received your enquiry'),
        'confirm_page_id'      => '{{tab:email_enquiry_received}}',
    ),
    'tree'             => $request_page(lang('Ask About a Work'),
        lang('Tell us which work you are thinking of and what you would like to know. Someone from the gallery answers you personally, usually within a day.'),
        'enquiry_form',
        array_merge(array(
            // An enquiry never buys a work: the sale goes through the
            // gallery.
            $el('div', 'p-4 border-start border-2 border-primary bg-body-tertiary mb-4', lang('No Obligation'), array(
                $heading('h2', lang('An enquiry commits you to nothing'), 'pgl-display h5 mb-2'),
                $para(lang('We answer with the price, the condition and the ways the work can reach you. Nothing is paid online: a work is sold only when you and the gallery agree.'), 'small mb-0'),
            )),
        ), $request_aside(array(
            lang('Your enquiry opens its own page, and an account for you if you have none.'),
            lang('We answer there with the price, photographs in daylight or a time to see the work.'),
            lang('If you wish, we hold the work for you for a few days while you decide.'),
            lang('We frame, pack and deliver it, with its certificate signed by the artist.'),
        ), lang('How buying a work goes'), '{{page:advisory}}'))),
);

// One enquiry: the enquiry, its status and the messages.
$request_pages[] = $request_view('enquiry_view', lang('artwork-enquiry'), lang('Artwork Enquiry'),
    'enquiry_detail', 'enquiry_messages', lang('There is a new message about your enquiry'));

// My Enquiries: the visitor's own enquiries (for the staff, everybody's).
$request_pages[] = $my_list_page('my_enquiries', lang('my-enquiries'), lang('My Enquiries'),
    lang('Your enquiries about works, their status and the gallery\'s answers, newest first.'),
    'my_enquiries_list', 'bi-easel2', lang('Another work in mind?'),
    lang('Ask about another work or book a private viewing; your details are filled in for you.'),
    array(
        $link(lang('Ask About a Work'), '{{page:enquire}}', 'btn btn-sm btn-primary rounded-0 px-3'),
        $link(lang('Book a Private Viewing'), '{{page:viewing}}', 'btn btn-sm btn-outline-primary rounded-0 px-3'),
    ));

$request_pages[] = $staff_list_page('staff_enquiries', lang('all-enquiries'), lang('Enquiries'),
    lang('Every enquiry about a work, newest first: answer under each and set the status as the work is reserved or sold.'),
    'staff_enquiries_list');

// The private viewing request: what is sent opens the request's own page.
$request_pages[] = array(
    'key'              => 'viewing',
    'name'             => lang('book-a-private-viewing'),
    'folder'           => 'public',
    'title'            => lang('Book a Private Viewing'),
    'meta_description' => lang('See an exhibition or the gallery\'s other works with someone from the gallery, at a time that suits you and your guests.'),
    'form'             => array(
        'form_name'            => lang('Private Viewings'),
        'confirmation_message' => lang('Thank you, your private viewing request has reached the gallery. We confirm the time on your request\'s page and by e-mail.'),
        'confirmation_page_id' => '{{tab:viewing_view}}',
        'auto_registration'    => 1,
        'notify_email'         => '{{site_email}}',
        'notify_subject'       => lang('New private viewing request: ^^exhibition^^'),
        'notify_page_id'       => '{{tab:email_viewing_new}}',
        'confirm_email'        => 1,
        'confirm_subject'      => lang('We received your private viewing request'),
        'confirm_page_id'      => '{{tab:email_viewing_received}}',
    ),
    'tree'             => $request_page(lang('Book a Private Viewing'),
        lang('Choose an exhibition, a day and a time: someone from the gallery walks you through the works, with time for every question.'),
        'viewing_form',
        $request_aside(array(
            lang('Your request opens its own page, and an account for you if you have none.'),
            lang('We confirm the time there, or offer another one if the gallery is closed that day.'),
            lang('Come to the gallery at the time agreed; we keep the room quiet for you.'),
            lang('Ask about any work during the viewing, or later on your request\'s page.'),
        ), lang('Plan Your Visit'), '{{page:visit}}')),
);

// One private viewing request: the request, its status and the messages.
$request_pages[] = $request_view('viewing_view', lang('private-viewing'), lang('Private Viewing'),
    'viewing_detail', 'viewing_messages', lang('There is a new message about your private viewing'));

$request_pages[] = $my_list_page('my_viewings', lang('my-private-viewings'), lang('My Private Viewings'),
    lang('Your private viewing requests, their status and the gallery\'s answers, newest first.'),
    'my_viewings_list', 'bi-calendar-heart', lang('Another visit?'),
    lang('Book another private viewing or ask about a work you saw.'),
    array(
        $link(lang('Book a Private Viewing'), '{{page:viewing}}', 'btn btn-sm btn-primary rounded-0 px-3'),
        $link(lang('Ask About a Work'), '{{page:enquire}}', 'btn btn-sm btn-outline-primary rounded-0 px-3'),
    ));

$request_pages[] = $staff_list_page('staff_viewings', lang('all-private-viewings'), lang('Private Viewings'),
    lang('Every private viewing request, newest first: confirm the time or offer another under each, then set the status.'),
    'staff_viewings_list');

// The privacy notice the consent boxes and the footer link to: sample text
// with a note to adapt it.
$request_pages[] = array(
    'key'              => 'privacy',
    'name'             => lang('privacy-policy'),
    'folder'           => 'public',
    'title'            => lang('Privacy Policy'),
    'meta_description' => lang('What the gallery keeps about you when you ask about a work, book a private viewing or join the newsletter, why, and what you can ask of us.'),
    'tree'             => $page(array(
        $page_header(lang('Privacy Policy'), lang('What the gallery keeps about you when you ask about a work, book a private viewing or join the newsletter, why, and what you can ask of us.')),
        $el('section', 'py-5', lang('Your Data'), array(
            $container(array($row(array($col(array(
                $el('div', 'alert alert-secondary rounded-0 d-flex gap-2 small', lang('Sample Text Note'), array(
                    $icon('bi-info-circle', 'mt-1'),
                    $span(lang('Sample text: adapt it to your company and to the data protection law that applies to you before you publish.'), '', lang('Text')),
                ), array('_attrs' => array($attr('role', 'note')))),
                $heading('h2', lang('Your data'), 'pgl-display h4 mb-3'),
                $para(lang('When you ask about a work, book a private viewing, join the newsletter or open an account, we keep your name, your contact details and what you tell us, to answer you, arrange your visit and send you the invitations you asked for.'), 'mb-3'),
                $para(lang('We keep it as long as the law requires, never sell it and never give it to advertisers. You can see and change your account data from My Account, and ask us about the rest through the contact page.'), 'mb-0'),
            ), '', '8'))))),
        ), $searchable('gl-your-data', lang('privacy, personal data, KVKK, GDPR, delete my data'))),
    )),
);

// The e-mails: what the gallery receives and the reply to the sender of
// each form, and the new message e-mail.
foreach (array(
    array('email_enquiry_new', lang('email-new-enquiry'), lang('New Enquiry E-mail'), 'email_enquiry_admin'),
    array('email_enquiry_received', lang('email-enquiry-received'), lang('Enquiry Received E-mail'), 'email_enquiry_client'),
    array('email_viewing_new', lang('email-new-private-viewing'), lang('New Private Viewing E-mail'), 'email_viewing_admin'),
    array('email_viewing_received', lang('email-private-viewing-received'), lang('Private Viewing Received E-mail'), 'email_viewing_client'),
) as $m) {
    $request_pages[] = array_merge($mail_settings, array(
        'key'   => $m[0],
        'name'  => $m[1],
        'title' => $m[2],
        'tree'  => $mail_page(array($widget($m[3]))),
    ));
}
// A new message or reply: sent to the sender of an enquiry or a viewing
// request, the sender of a conversation and the visitors watching a page.
// Its Comments widget draws the comment in the card of mail.php; the
// System region after it keeps the fixed comment for an e-mail the widget
// does not draw.
$request_pages[] = array_merge($mail_settings, array(
    'key'   => 'email_reply',
    'name'  => lang('email-new-reply'),
    'title' => lang('New Reply E-mail'),
    'tree'  => $mail_page(array(
        $mail_heading(lang('There is a new message')),
        $mail_para(lang('Something new was written on a page you follow: your enquiry, your private viewing, a conversation or a Journal article. You can read it below and answer it on the page.')),
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
// The day and the time of day of a viewing.
$viewing_when = function () use ($span, $bind, $sample_day, $sample_time) {
    return array(
        $span($sample_day, '', lang('Date'), $bind('viewing_date')),
        $span(' · ', '', lang('Text')),
        $span($sample_time, '', lang('Time of day'), $bind('time_of_day')),
    );
};

// A request in a list, linked to its page: its title and status, then its
// details. The staff's row starts with the sender's name and adds the
// phone; the visitor's own adds the messages.
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
            $span($status, 'badge rounded-0 border text-body', lang('Status'), $bind('status')),
        )),
        $el('div', 'd-flex flex-wrap column-gap-3 row-gap-1 small text-body-secondary', lang('Details'), $items),
    ), array('href' => '#', '_bindings' => array('href' => 'form_item_view')));
};
$enquiry_row = function ($staff = false) use ($el, $span, $icon, $bind, $request_row, $sample_work, $sample_kind, $enquiry_statuses) {
    return $request_row($staff, $sample_work, 'artwork', lang('Artwork'), $enquiry_statuses[0], array(
        $el('span', 'd-inline-flex align-items-center gap-1', lang('Kind'), array(
            $icon('bi-tag'), $span($sample_kind, '', lang('Kind'), $bind('kind')),
        )),
    ));
};
$viewing_row = function ($staff = false) use ($el, $span, $icon, $bind, $request_row, $viewing_when, $sample_exhibition, $sample_party, $viewing_statuses) {
    return $request_row($staff, $sample_exhibition, 'exhibition', lang('Exhibition'), $viewing_statuses[0], array(
        $el('span', 'd-inline-flex align-items-center gap-1', lang('When'), array_merge(array($icon('bi-calendar-event')), $viewing_when())),
        $el('span', 'd-inline-flex align-items-center gap-1', lang('Party'), array(
            $icon('bi-people'), $span($sample_party, '', lang('Party'), $bind('party')),
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

// The lines of an enquiry and of a viewing request, for their e-mails.
$mail_enquiry = function () use ($mail_box, $mail_line, $span, $bind, $sample_work, $sample_kind, $sample_message, $contact_choices) {
    return $mail_box(array(
        $mail_line(lang('Artwork'), array($span($sample_work, '', lang('Artwork'), $bind('artwork')))),
        $mail_line(lang('Kind'), array($span($sample_kind, '', lang('Kind'), $bind('kind')))),
        $mail_line(lang('Message'), array($span($sample_message, '', lang('Message'), $bind('message')))),
        $mail_line(lang('Reply by'), array($span($contact_choices[0][1], '', lang('Reply by'), $bind('reply_by')))),
    ), lang('Enquiry'));
};
$mail_viewing = function () use ($mail_box, $mail_line, $span, $bind, $viewing_when, $sample_exhibition, $sample_party, $sample_note) {
    return $mail_box(array(
        $mail_line(lang('Exhibition'), array($span($sample_exhibition, '', lang('Exhibition'), $bind('exhibition')))),
        $mail_line(lang('When'), $viewing_when()),
        $mail_line(lang('Party'), array($span($sample_party, '', lang('Party'), $bind('party')))),
        $mail_line(lang('Notes'), array($span($sample_note, '', lang('Notes'), $bind('notes')))),
    ), lang('Private Viewing'));
};
// A reference code line under a staff e-mail.
$mail_reference = function () use ($el, $span, $bind, $mail_style) {
    return $el('p', 'small text-body-secondary mt-4 mb-0', lang('Reference'), array(
        $span(lang('Reference Code') . ': ', '', lang('Label')),
        $span('ABC123', '', lang('Reference Code'), $bind('reference_code')),
    ), $mail_style('font-size:13px;color:#57534e;margin:24px 0 0'));
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
    ), array_merge($mail_style('border-top:1px solid #e7e5e4;padding-top:16px'), $show_when('has_new_account')));
};
// The sender's lines of a staff e-mail.
$mail_sender = function () use ($mail_box, $mail_line, $span, $bind, $sender_name) {
    return $mail_box(array(
        $mail_line(lang('Name'), $sender_name()),
        $mail_line(lang('Email'), array($span('jane.cooper@example.com', '', lang('Email'), $bind('email')))),
        $mail_line(lang('Phone'), array($span('+90 555 000 00 00', '', lang('Phone'), $bind('phone')))),
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
// A list of requests: the visitor's own (the staff see them all), or the
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
            $el('div', 'list-group list-group-flush border-top', lang('Request List'), array($loop(array($row($staff))))),
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
                        $heading('h2', $not_found_title, 'pgl-display h3 mt-3'),
                        $para($missing, 'text-body-secondary mb-4', $bind('not_found')),
                        $link($back_text, '{{page:' . $back_key . '}}', 'btn btn-primary rounded-0 px-4'),
                    ), $show_when('record_not_found')),
                    $el('div', '', lang('Record'), array(
                        $el('div', 'alert alert-success rounded-0 d-flex gap-2', lang('Sent Notice'), array(
                            $icon('bi-check-circle', 'mt-1'),
                            $span($sent, '', lang('Text')),
                        ), array_merge(array('_attrs' => array($attr('role', 'status'))), $show_when('submitted_this_session'))),
                        $el('div', 'alert alert-info rounded-0', lang('New Account'), array(
                            $el('p', 'fw-semibold mb-2', lang('Title'), array(), array('text' => lang('We opened an account for you'))),
                            $el('p', 'mb-1', lang('Sign-in Email'), array(
                                $span(lang('Email') . ': ', '', lang('Label')),
                                $span('sample@example.com', 'fw-semibold', lang('Value'), $bind('__new_account_email')),
                            )),
                            // The single-use set password link, for the person who sent the form
                            // while it is live; the sentence below covers a used or expired link.
                            $link(lang('Set your password'), '#', 'btn btn-sm btn-primary rounded-0', array(),
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
                        $el('div', 'border p-4 mb-4', lang('Request Card'), array(
                            $el('div', 'd-flex flex-wrap align-items-start justify-content-between gap-2 mb-3', lang('Title'), array(
                                $el('div', '', lang('Words'), array(
                                    $para($eyebrow, 'pgl-eyebrow small text-uppercase fw-semibold text-primary mb-1'),
                                    $heading('h1', $title_sample, 'pgl-display h3 mb-0', $bind($title_field)),
                                )),
                                $span($status, 'badge rounded-0 text-bg-primary fs-6', lang('Status'), $bind('status')),
                            )),
                            $el('dl', 'row small mb-0', lang('Details'), $details),
                        )),
                        $el('div', 'pg-hide-if-empty p-4 bg-body-tertiary mb-4', $text_label, array(
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
// the gallery every message.
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

// The sender's fields of both forms.
$contact_fields = function ($p) use ($row, $col, $field, $input) {
    return $row(array(
        $col(array($field($p . '-first-name', lang('First Name'), $input($p . '-first-name', 'text', 'first_name', 'given-name', true, array('contact_field' => 'first_name')))), '6'),
        $col(array($field($p . '-last-name', lang('Last Name'), $input($p . '-last-name', 'text', 'last_name', 'family-name', true, array('contact_field' => 'last_name')))), '6'),
        $col(array($field($p . '-email', lang('Email'), $input($p . '-email', 'email', 'email', 'email', true, array('contact_field' => 'email_address')))), '6'),
        $col(array($field($p . '-phone', lang('Phone'), $input($p . '-phone', 'tel', 'phone', 'tel', false, array('contact_field' => 'mobile_phone')))), '6'),
    ), '3');
};

$enquiry_missing = lang('This enquiry could not be found, or it is not yours to see.');
$viewing_missing = lang('This private viewing request could not be found, or it is not yours to see.');
$not_before_today = array('rules' => array('min_date' => 'today'));
$time_radios = array();
foreach ($time_choices as $t) $time_radios[] = array($t, $t);

$request_widgets = array(

    // The enquiry: the work and the kind start with what a link sends
    // (?artwork=, ?kind=). The Status is the gallery's (office use only):
    // every enquiry starts as Enquiry received.
    'enquiry_form' => array(
        'page'   => 'enquire',
        'slug'   => 'form',
        'config' => array('regionType' => 'custom_form', 'form_source' => 'page'),
        'tree'   => $widget_root(array(
            $form_step(1, lang('The work')),
            $row(array(
                $col(array($field('gl-enq-artwork', lang('Artwork'), $select('gl-enq-artwork', 'artwork', lang('Choose a work'), $work_choices, true, '', array('url_param' => 'artwork')))), '6'),
                $col(array($field('gl-enq-kind', lang('Kind'), $select('gl-enq-kind', 'kind', '', $enquiry_choices, true, $enquiry_choices[0], array('url_param' => 'kind')))), '6'),
            ), '3'),
            $field('gl-enq-message', lang('Message'), $textarea('gl-enq-message', 'message', 5, true), 'mb-3',
                lang('What you would like to know: the price with framing, the condition, delivery, or the wall it is for.')),
            $form_step(2, lang('How to reach you')),
            $contact_fields('gl-enq'),
            $radios('gl-enq-reply', 'reply_by', lang('Reply by'), $contact_choices, $contact_choices[0][0]),
            $field('gl-enq-status', lang('Status'), $select('gl-enq-status', 'status', '', $enquiry_statuses, false, $enquiry_statuses[0], array('office_use_only' => true))),
            $agree('gl-enq-consent', 'consent', lang('I agree that the gallery keeps these details to answer my enquiry, as its privacy notice explains.'), true),
            $captcha(),
            $el('div', 'd-flex flex-wrap align-items-center gap-3', lang('Send Area'), array(
                $submit(lang('Send the Enquiry'), 'btn btn-primary btn-lg rounded-0 px-4'),
                $para(lang('An enquiry commits you to nothing.'), 'small text-body-secondary mb-0'),
            )),
            $loop(array()),
        )),
    ),

    // One enquiry; the gallery's answers follow as comments.
    'enquiry_detail' => $detail_widget('enquiry_view', 'enquire', $enquiry_missing, 'submitter_only') + array(
        'tree' => $request_detail(lang('My Enquiries'), 'my_enquiries', $enquiry_missing, lang('Enquiry not found'),
            lang('Artwork Enquiry'), $sample_work, 'artwork', $enquiry_statuses[0],
            lang('Thank you, your enquiry has reached the gallery. We answer here, usually within a day, and by e-mail.'),
            array_merge(
                $detail(lang('Kind'), '', lang('Kind'), $bound($sample_kind, 'kind')),
                $detail(lang('Reply by'), '', lang('Reply by'), $bound($contact_choices[0][1], 'reply_by')),
                $detail(lang('Name'), '', lang('Name'), $sender_name(), true),
                $detail(lang('Reference Code'), 'mb-0 font-monospace', lang('Reference Code'), $bound('ABC123', 'reference_code'))
            ),
            lang('Message'), $sample_message, 'message',
            lang('Only you and the gallery can read this page. Ask more below; we answer here and by e-mail.')),
    ),

    'enquiry_messages' => $request_messages('enquiry_view',
        lang('No messages yet. The gallery\'s answer comes here; you can write to us about the work too.'), lang('Write to us')),

    'my_enquiries_list' => $request_list('my_enquiries', 'enquire', 'enquiry_view', $enquiry_row, array(), '',
        lang('No enquiries yet. Ask about any work you saw in the gallery or on the site.')),

    'staff_enquiries_list' => $request_list('staff_enquiries', 'enquire', 'enquiry_view', $enquiry_row,
        array('first_name', 'last_name', 'email', 'artwork', 'kind', 'message', 'status'),
        lang('Search by name, e-mail, work, kind or status...'), lang('No enquiries yet.')),

    // The private viewing request: the exhibition starts with what a link
    // sends (?exhibition=).
    'viewing_form' => array(
        'page'   => 'viewing',
        'slug'   => 'form',
        'config' => array('regionType' => 'custom_form', 'form_source' => 'page'),
        'tree'   => $widget_root(array(
            $form_step(1, lang('Your visit')),
            $field('gl-vw-exhibition', lang('Exhibition'), $select('gl-vw-exhibition', 'exhibition', lang('Choose an exhibition'), $exhibition_choices, true, '', array('url_param' => 'exhibition'))),
            $row(array(
                $col(array($field('gl-vw-date', lang('Preferred day'), $input('gl-vw-date', 'date', 'viewing_date', 'off', true, $not_before_today))), '6'),
                $col(array($field('gl-vw-party', lang('Party'), $select('gl-vw-party', 'party', '', $party_choices, true, $party_choices[0]))), '6'),
            ), '3'),
            $radios('gl-vw-time', 'time_of_day', lang('Time of day'), $time_radios, $time_choices[1]),
            $field('gl-vw-notes', lang('Notes'), $textarea('gl-vw-notes', 'notes', 3, false), 'mb-3',
                lang('The works you would most like to see, a step-free route, or anything we should prepare.')),
            $form_step(2, lang('How to reach you')),
            $contact_fields('gl-vw'),
            $field('gl-vw-status', lang('Status'), $select('gl-vw-status', 'status', '', $viewing_statuses, false, $viewing_statuses[0], array('office_use_only' => true))),
            $agree('gl-vw-consent', 'consent', lang('I agree that the gallery keeps these details to arrange my private viewing, as its privacy notice explains.'), true),
            $captcha(),
            $el('div', 'd-flex flex-wrap align-items-center gap-3', lang('Send Area'), array(
                $submit(lang('Send the Request'), 'btn btn-primary btn-lg rounded-0 px-4'),
                $para(lang('A private viewing is free.'), 'small text-body-secondary mb-0'),
            )),
            $loop(array()),
        )),
    ),

    'viewing_detail' => $detail_widget('viewing_view', 'viewing', $viewing_missing, 'submitter_only') + array(
        'tree' => $request_detail(lang('My Private Viewings'), 'my_viewings', $viewing_missing, lang('Private viewing not found'),
            lang('Private Viewing'), $sample_exhibition, 'exhibition', $viewing_statuses[0],
            lang('Thank you, your request has reached the gallery. We confirm the time here and by e-mail.'),
            array_merge(
                $detail(lang('When'), '', lang('When'), $viewing_when(), true),
                $detail(lang('Party'), '', lang('Party'), $bound($sample_party, 'party')),
                $detail(lang('Name'), '', lang('Name'), $sender_name(), true),
                $detail(lang('Reference Code'), 'mb-0 font-monospace', lang('Reference Code'), $bound('ABC123', 'reference_code'))
            ),
            lang('Notes'), $sample_note, 'notes',
            lang('Another day or more guests? Write to us below; we answer here and by e-mail.')),
    ),

    'viewing_messages' => $request_messages('viewing_view',
        lang('No messages yet. The gallery confirms the time here.'), lang('Write to us')),

    'my_viewings_list' => $request_list('my_viewings', 'viewing', 'viewing_view', $viewing_row, array(), '',
        lang('No private viewings yet. Book one and see the works with someone from the gallery.')),

    'staff_viewings_list' => $request_list('staff_viewings', 'viewing', 'viewing_view', $viewing_row,
        array('first_name', 'last_name', 'email', 'exhibition', 'party', 'status'),
        lang('Search by name, e-mail, exhibition or status...'), lang('No private viewings yet.')),

    // The e-mail pages' widgets: drawn for the submission just sent.
    'email_enquiry_admin' => $detail_widget('email_enquiry_new', 'enquire', lang('The enquiry could not be found.'), 'public') + array(
        'tree' => $widget_root(array(
            $mail_heading(lang('A new enquiry about a work')),
            $mail_para(lang('It came from the enquiry form. Answer under the enquiry on its page and set the status; what you write there reaches the visitor by e-mail.')),
            $mail_enquiry(),
            $mail_sender(),
            $mail_button(lang('Open the enquiry'), '{{page:enquiry_view}}?r=^^reference_code^^'),
            $mail_reference(),
        )),
    ),
    'email_enquiry_client' => $detail_widget('email_enquiry_received', 'enquire', lang('The enquiry could not be found.'), 'public') + array(
        'tree' => $widget_root(array(
            $mail_heading(lang('We received your enquiry')),
            $mail_greeting(),
            $mail_para(lang('Thank you. Someone from the gallery answers on your enquiry\'s page, usually within a day, and you get the answer by e-mail.')),
            $mail_enquiry(),
            $mail_para(lang('An enquiry commits you to nothing: a work is sold only when you and the gallery agree.')),
            $mail_button(lang('Open my enquiry'), '{{page:enquiry_view}}?r=^^reference_code^^'),
            $mail_new_account(),
        )),
    ),
    'email_viewing_admin' => $detail_widget('email_viewing_new', 'viewing', lang('The private viewing request could not be found.'), 'public') + array(
        'tree' => $widget_root(array(
            $mail_heading(lang('A new private viewing request')),
            $mail_para(lang('It came from the private viewing form. Confirm the time or offer another under the request on its page and set the status; what you write there reaches the visitor by e-mail.')),
            $mail_viewing(),
            $mail_sender(),
            $mail_button(lang('Open the request'), '{{page:viewing_view}}?r=^^reference_code^^'),
            $mail_reference(),
        )),
    ),
    'email_viewing_client' => $detail_widget('email_viewing_received', 'viewing', lang('The private viewing request could not be found.'), 'public') + array(
        'tree' => $widget_root(array(
            $mail_heading(lang('We received your private viewing request')),
            $mail_greeting(),
            $mail_para(lang('Thank you. We confirm the time on your request\'s page and by e-mail, or offer another one if the gallery is closed that day.')),
            $mail_viewing(),
            $mail_button(lang('Open my request'), '{{page:viewing_view}}?r=^^reference_code^^'),
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
