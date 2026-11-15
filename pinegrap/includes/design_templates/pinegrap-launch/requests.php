<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Phone Launch Site", its requests: the trade-in quote
 * request, the quote's own page with its messages, My Trade-ins and the
 * staff's list; the support request, its page, My Support Requests and the
 * staff's list; the privacy page the consent boxes link, the e-mails of
 * both forms and the new message e-mail, and the widgets that draw them.
 * Included after parts.php and mail.php in the same scope; returns
 * array('pages' => ..., 'widgets' => ...).
 *
 * A request is a record of its form (Trade-ins, Support Requests), as a
 * viewing is in the Real Estate template. A visitor without an account
 * gets one (auto-registration), so the request's page and the My lists
 * show it at once. Its Status is an office-use-only field the staff change
 * in the control panel; the messages under it are the page's comments,
 * mailed to the customer and to the maker. The quote itself is written
 * there as a message: the template has no price calculator.
 *
 * Both forms' Orbit model starts with the model a link sends (?model=, the
 * model's name as $models writes it). The photos of an old phone and the
 * receipt or photo of a fault are kept in the template's private folder:
 * the record's page links them for whoever may read the record.
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

// The statuses, in the order a request moves through them.
$tradein_statuses = array(lang('Request received'), lang('Quote sent'), lang('Phone collected'), lang('Credit given'), lang('Closed'));
$support_statuses = array(lang('Open'), lang('Waiting for you'), lang('Repair in progress'), lang('Solved'));

// The Orbit models by name: the answers of both forms' model field.
$model_names = array();
foreach ($models as $model_key => $model_row) $model_names[] = $model_row['name'];
unset($model_key, $model_row);

// The old phone: its maker, its storage and its condition. Each answer's
// value is its words.
$old_brands = array('Orbit', 'Apple', 'Samsung', 'Google', 'Xiaomi', lang('Another brand'));
$old_storage = array(lang('64 GB'), $storage_sizes['128'], $storage_sizes['256'], $storage_sizes['512'], lang('1 TB'));
$conditions = array(
    array(lang('Like new'), lang('Like new')),
    array(lang('Light scratches'), lang('Light scratches')),
    array(lang('Cracked screen or back'), lang('Cracked screen or back')),
    array(lang('Does not turn on'), lang('Does not turn on')),
);
// What a support request is about.
$support_topics = array(
    lang('Screen'), lang('Battery and charging'), lang('Camera'), lang('Software and updates'),
    lang('Sound and calls'), lang('Water damage'), lang('Something else'),
);

// The side column of a request page: what happens next, then a box of
// good-to-know lines and its link.
$request_aside = function ($steps, $box_title, $box_lines, $box_link_text, $box_link_href) use ($el, $heading, $link, $check_list) {
    $items = array();
    foreach ($steps as $n => $text) {
        $items[] = $el('li', $n < count($steps) - 1 ? 'mb-2' : '', lang('Step'), array(), array('text' => $text));
    }
    return array(
        $el('div', 'p-4 rounded-4 bg-body-tertiary mb-4', lang('How It Works'), array(
            $heading('h2', lang('What happens next'), 'h5 fw-semibold mb-3'),
            $el('ol', 'small text-body-secondary ps-3 mb-0', lang('List'), $items),
        )),
        $el('div', 'p-4 rounded-4 border', $box_title, array(
            $heading('h2', $box_title, 'h5 fw-semibold mb-3'),
            $check_list($box_lines, 'list-unstyled small mb-3'),
            $link($box_link_text, $box_link_href, 'fw-semibold link-primary text-decoration-none small'),
        )),
    );
};

// A request form's page: the header, the form in a card, the side column.
$request_page = function ($title, $lead, $form_widget, $aside) use ($page, $page_header, $el, $container, $row, $col, $widget) {
    return $page(array(
        $page_header($title, $lead),
        $el('section', 'py-5', lang('Content'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $el('div', 'card border-0 shadow-sm rounded-4', lang('Form Card'), array(
                            $el('div', 'card-body p-4 p-lg-5', lang('Card Body'), array($widget($form_widget))),
                        )),
                    ), '', '8'),
                    $col($aside, '', '4'),
                ), '4', '', 'g-lg-5'),
            )),
        )),
    ), false);
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

// The trade-in quote request. What is sent opens the quote's own page.
$request_pages[] = array(
    'key'              => 'tradein',
    'name'             => lang('trade-in'),
    'folder'           => 'public',
    'title'            => lang('Trade-in'),
    'meta_description' => lang('Trade in your old phone, any brand and any condition: tell us about it, get a quote within a working day and take the credit off your new Orbit.'),
    'form'             => array(
        'form_name'            => lang('Trade-ins'),
        'confirmation_message' => lang('Thank you, your trade-in request has reached us. We send your quote within a working day.'),
        'confirmation_page_id' => '{{tab:tradein_view}}',
        'auto_registration'    => 1,
        'notify_email'         => '{{site_email}}',
        'notify_subject'       => lang('New trade-in request: ^^old_model^^'),
        'notify_page_id'       => '{{tab:email_tradein_new}}',
        'confirm_email'        => 1,
        'confirm_subject'      => lang('We received your trade-in request'),
        'confirm_page_id'      => '{{tab:email_tradein_received}}',
    ),
    'tree'             => $request_page(lang('Trade-in'),
        lang('Tell us about your old phone, any brand and any condition. We send a quote within a working day, and the credit comes off your new Orbit.'),
        'tradein_form', $request_aside(array(
            lang('Your request opens its own page, and an account for you if you have none.'),
            lang('We send the quote there within a working day, with what it is based on.'),
            lang('Accept it, and a courier collects the old phone when the new one arrives.'),
            lang('Once we have checked the phone, the credit comes off your order or back to your card.'),
        ), lang('Good to know'), array(
            lang('A broken phone still has value: send it in whatever its state.'),
            lang('Back up your phone and sign out of your accounts before it leaves you.'),
            lang('We erase every phone we receive and recycle what cannot be reused.'),
        ), lang('Questions about trade-in? Read the FAQ'), '{{page:faq}}')),
);

// One trade-in: the request, its status and the messages under it.
$request_pages[] = array(
    'key'      => 'tradein_view',
    'name'     => lang('trade-in-quote'),
    'folder'   => 'registration',
    'title'    => lang('Trade-in Quote'),
    'search'   => false,
    'sitemap'  => false,
    'comments' => array('label' => lang('Message'), 'allow_new' => true, 'auto_publish' => true, 'show_date' => true, 'login' => true,
                        'attachments' => true,
                        'email_page' => '{{tab:email_reply}}', 'email_subject' => lang('There is a new message about your trade-in'),
                        'notify_email' => '{{site_email}}'),
    'tree'     => $page(array(
        $widget('tradein_detail'),
        $comments_section(lang('Messages'), 'tradein_messages'),
    ), false),
);

// My Trade-ins: the customer's trade-in requests (for the staff, everybody's).
$request_pages[] = array(
    'key'     => 'my_tradeins',
    'name'    => lang('my-trade-ins'),
    'folder'  => 'registration',
    'title'   => lang('My Trade-ins'),
    'search'  => false,
    'sitemap' => false,
    'tree'    => $my_page(lang('My Trade-ins'), lang('Your trade-in requests, their quotes and status, newest first.'),
        'my_tradeins_list', lang('Another old phone?'), lang('Ask for a quote for it too; your details are filled in for you.'),
        lang('Get a quote'), 'tradein', 'bi-arrow-repeat'),
);

// The staff's list of every trade-in request, newest first, with a search.
$request_pages[] = array(
    'key'     => 'staff_tradeins',
    'name'    => lang('all-trade-ins'),
    'folder'  => 'private',
    'title'   => lang('All Trade-ins'),
    'search'  => false,
    'sitemap' => false,
    'tree'    => $staff_list_page(lang('All Trade-ins'), lang('Every trade-in request, newest first: send the quote, set the status and answer under each.'), 'staff_tradeins_list'),
);

// The support request: a fault, a repair, a question about the phone.
$request_pages[] = array(
    'key'              => 'support',
    'name'             => lang('support'),
    'folder'           => 'public',
    'title'            => lang('Repair and Support'),
    'meta_description' => lang('Something wrong with your Orbit? Tell us what happens; we answer within a working day and collect the phone for a repair if it needs one.'),
    'form'             => array(
        'form_name'            => lang('Support Requests'),
        'confirmation_message' => lang('Thank you, your support request has reached us. We answer within a working day.'),
        'confirmation_page_id' => '{{tab:support_view}}',
        'auto_registration'    => 1,
        'notify_email'         => '{{site_email}}',
        'notify_subject'       => lang('New support request: ^^issue^^'),
        'notify_page_id'       => '{{tab:email_support_new}}',
        'confirm_email'        => 1,
        'confirm_subject'      => lang('We received your support request'),
        'confirm_page_id'      => '{{tab:email_support_received}}',
    ),
    'tree'             => $request_page(lang('Repair and Support'),
        lang('Tell us what happens and since when. We answer within a working day, and collect the phone from your door if it needs a repair.'),
        'support_form', $request_aside(array(
            lang('Your request opens its own page, and an account for you if you have none.'),
            lang('A specialist answers there within a working day, often with a fix you can try at once.'),
            lang('If it needs a repair, a courier collects the phone and brings it back, usually within five working days.'),
        ), lang('Before you write'), array(
            lang('Restart the phone and install the latest update: it solves many faults.'),
            lang('Repairs under the two-year warranty cost nothing, collection included.'),
            lang('Back up your phone before a repair: we may need to reset it.'),
        ), lang('Find a quick answer in the FAQ'), '{{page:faq}}')),
);

// One support request: the request, its status and the messages under it.
$request_pages[] = array(
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
        $widget('support_detail'),
        $comments_section(lang('Messages'), 'support_messages'),
    ), false),
);

// My Support Requests: the customer's own (for the staff, everybody's).
$request_pages[] = array(
    'key'     => 'my_support',
    'name'    => lang('my-support-requests'),
    'folder'  => 'registration',
    'title'   => lang('My Support Requests'),
    'search'  => false,
    'sitemap' => false,
    'tree'    => $my_page(lang('My Support Requests'), lang('Your support requests, their status and our answers, newest first.'),
        'my_support_list', lang('Something else?'), lang('Send another support request; your details are filled in for you.'),
        lang('Repair and Support'), 'support', 'bi-tools'),
);

// The staff's list of every support request, newest first, with a search.
$request_pages[] = array(
    'key'     => 'staff_support',
    'name'    => lang('all-support-requests'),
    'folder'  => 'private',
    'title'   => lang('All Support Requests'),
    'search'  => false,
    'sitemap' => false,
    'tree'    => $staff_list_page(lang('All Support Requests'), lang('Every support request, newest first: answer, arrange the repair and set the status under each.'), 'staff_support_list'),
);

// The privacy notice the consent boxes and the footer link to: sample text
// with a note to adapt it.
$request_pages[] = array(
    'key'              => 'privacy',
    'name'             => lang('privacy-policy'),
    'folder'           => 'public',
    'title'            => lang('Privacy Policy'),
    'meta_description' => lang('What we keep about you when you buy, trade in or ask for support, why, and what you can ask of us.'),
    'tree'             => $page(array(
        $page_header(lang('Privacy Policy'), lang('What we keep about you when you buy, trade in or ask for support, why, and what you can ask of us.')),
        $el('section', 'py-5', lang('Your Data'), array(
            $container(array($row(array($col(array(
                $el('div', 'alert alert-secondary d-flex gap-2 small', lang('Sample Text Note'), array(
                    $icon('bi-info-circle', 'mt-1'),
                    $span(lang('Sample text: adapt it to your company and to the data protection law that applies to you before you publish.'), '', lang('Text')),
                ), array('_attrs' => array($attr('role', 'note')))),
                $heading('h2', lang('Your data'), 'h4 fw-semibold mb-3'),
                $para(lang('When you order, ask for a trade-in quote or a repair, join the launch list or open an account, we keep your name, your contact details, your orders and what you tell us about your phones, with the photos and receipts you send, to deliver, quote, repair and answer you.'), 'mb-3'),
                $para(lang('We keep it as long as the law requires, never sell it and never give it to advertisers. A phone we receive is erased before it is checked. You can see and change your account data from My Account, and ask us about the rest through the contact page.'), 'mb-0'),
            ), '', '8'))))),
        ), $searchable('your-data', lang('privacy, personal data, KVKK, GDPR, delete my data'))),
    )),
);

// The e-mails: what the staff receive and the reply to the sender, for each
// form, and the new message e-mail.
$request_pages[] = array_merge($mail_settings, array(
    'key'   => 'email_tradein_new',
    'name'  => lang('email-new-trade-in'),
    'title' => lang('New Trade-in E-mail'),
    'tree'  => $mail_page(array($widget('email_tradein_admin'))),
));
$request_pages[] = array_merge($mail_settings, array(
    'key'   => 'email_tradein_received',
    'name'  => lang('email-trade-in-received'),
    'title' => lang('Trade-in Received E-mail'),
    'tree'  => $mail_page(array($widget('email_tradein_client'))),
));
$request_pages[] = array_merge($mail_settings, array(
    'key'   => 'email_support_new',
    'name'  => lang('email-new-support-request'),
    'title' => lang('New Support Request E-mail'),
    'tree'  => $mail_page(array($widget('email_support_admin'))),
));
$request_pages[] = array_merge($mail_settings, array(
    'key'   => 'email_support_received',
    'name'  => lang('email-support-request-received'),
    'title' => lang('Support Request Received E-mail'),
    'tree'  => $mail_page(array($widget('email_support_client'))),
));
// A new message or reply: sent to the customer of a trade-in or a support
// request, the sender of a conversation and the visitors watching a page.
// Its Comments widget draws the comment in the card of mail.php; the System
// region after it keeps the fixed comment for an e-mail the widget does not
// draw.
$request_pages[] = array_merge($mail_settings, array(
    'key'   => 'email_reply',
    'name'  => lang('email-new-reply'),
    'title' => lang('New Reply E-mail'),
    'tree'  => $mail_page(array(
        $mail_heading(lang('There is a new message')),
        $mail_para(lang('Something new was written on a page you follow: your trade-in, your support request, a conversation or a press release. You can read it below and answer it on the page.')),
        $widget('email_comment'),
        array('type' => 'region', 'props' => array('regionType' => 'system', 'regionName' => ''), 'children' => array()),
    )),
));

// ── Widget pieces ───────────────────────────────────────────────────────

// The customer's name.
$person_name = function () use ($span, $bind) {
    return array(
        $span(lang('Jane'), '', lang('First Name'), $bind('first_name')),
        $span(' ', '', lang('Text')),
        $span(lang('Cooper'), '', lang('Last Name'), $bind('last_name')),
    );
};

// A request in a list: its title field, the status, the details, linked to
// its page. $details: array(icon, label, sample, field) rows.
$request_row = function ($name, $title_field, $title_sample, $details, $staff, $first_status) use ($el, $span, $icon, $bind, $person_name) {
    $items = array();
    foreach ($details as $d) {
        $items[] = $el('span', 'd-inline-flex align-items-center gap-1', $d[1], array($icon($d[0]), $span($d[2], '', $d[1], $bind($d[3]))));
    }
    $items[] = $el('span', 'd-inline-flex align-items-center gap-1', lang('Sent'), array($icon('bi-calendar3'), $span(lang('Date'), '', lang('Date'), $bind('submitted_date_and_time'))));
    if ($staff) {
        $items[] = $el('span', 'd-inline-flex align-items-center gap-1', lang('Phone'), array($icon('bi-telephone'), $span('+90 555 000 00 00', '', lang('Phone'), $bind('phone'))));
        $title = $el('span', 'fw-semibold', lang('Customer'), array_merge($person_name(), array(
            $span(' · ', 'text-body-secondary', lang('Text')),
            $span($title_sample, 'text-body-secondary fw-normal', $name, $bind($title_field)),
        )));
    } else {
        $items[] = $el('span', 'd-inline-flex align-items-center gap-1', lang('Messages'), array($icon('bi-chat-left-text'), $span('0', '', lang('Reply Count'), $bind('number_of_comments'))));
        $title = $span($title_sample, 'fw-semibold', $name, $bind($title_field));
    }
    return $el('a', 'list-group-item list-group-item-action p-3', $name, array(
        $el('div', 'd-flex flex-wrap align-items-center justify-content-between gap-2 mb-1', lang('Top Line'), array(
            $title,
            $span($first_status, 'badge rounded-pill bg-primary-subtle text-primary-emphasis', lang('Status'), $bind('status')),
        )),
        $el('div', 'd-flex flex-wrap column-gap-3 row-gap-1 small text-body-secondary', lang('Details'), $items),
    ), array('href' => '#', '_bindings' => array('href' => 'form_item_view')));
};
$sample_old_model = 'Galaxy S21';
$sample_model = $models['one']['name'];
$tradein_row = function ($staff = false) use ($request_row, $sample_old_model, $sample_model, $conditions, $tradein_statuses) {
    return $request_row(lang('Trade-in'), 'old_model', $sample_old_model, array(
        array('bi-phone', lang('Brand'), 'Samsung', 'old_brand'),
        array('bi-stars', lang('Phone condition'), $conditions[1][1], 'condition'),
        array('bi-bag', lang('New Orbit'), $sample_model, 'new_model'),
    ), $staff, $tradein_statuses[0]);
};
$support_row = function ($staff = false) use ($request_row, $support_topics, $sample_model, $support_statuses) {
    return $request_row(lang('Support Request'), 'issue', $support_topics[1], array(
        array('bi-phone', lang('Phone model'), $sample_model, 'model'),
    ), $staff, $support_statuses[0]);
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
// A file the record carries, as a link that opens it.
$file_link = function ($field, $sample) use ($link) {
    return array($link($sample, '#', '', array(), array('target' => '_blank', 'rel' => 'noopener',
        '_bindings' => array('href' => $field, 'text' => $field))));
};

// A record's page (a trade-in, a support request): the not-found notice,
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
                        $link($back_text, '{{page:' . $back_key . '}}', 'btn btn-primary rounded-pill px-4'),
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
$record_title = function ($kind, $sample, $field, $first_status) use ($el, $heading, $para, $span, $bind) {
    return $el('div', 'd-flex flex-wrap align-items-start justify-content-between gap-2 mb-3', lang('Title'), array(
        $el('div', '', lang('Words'), array(
            $para($kind, 'plx-eyebrow small text-uppercase fw-semibold text-primary mb-1'),
            $heading('h1', $sample, 'h3 fw-semibold mb-0', $bind($field)),
        )),
        $span($first_status, 'badge rounded-pill bg-primary-subtle text-primary-emphasis fs-6', lang('Status'), $bind('status')),
    ));
};
// A long text the record carries, in a soft box that leaves when empty.
$record_text = function ($label, $field, $sample) use ($el, $para, $bind) {
    return $el('div', 'pg-hide-if-empty p-4 rounded-4 bg-body-tertiary mb-4', $label, array(
        $para($label, 'small fw-semibold text-body-secondary mb-2'),
        $el('div', 'pg-field-value lh-lg', lang('Value'), array(), array_merge(array('text' => $sample), $bind($field))),
    ));
};
// The note under a record's card: where to write.
$record_note = function ($text) use ($el, $icon, $span) {
    return $el('div', 'd-flex gap-2 small text-body-secondary', lang('Note'), array(
        $icon('bi-chat-left-text', 'text-primary mt-1'),
        $span($text, '', lang('Text')),
    ));
};

// The messages under a request: the page's own Comments widget, shown only
// to whoever may read the request above it. Oldest first, all on one page,
// with a file each. No Reply, Share or follow box: the customer is e-mailed
// every answer and the maker every message.
$request_messages = function ($page_key, $empty, $heading_text, $sign_in) use ($comment_thread) {
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
            'sign_in'   => $sign_in,
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

// A list of requests: the customer's own (the staff see them all), or the
// staff's with a search.
$request_list = function ($page_key, $form_key, $view_key, $row_node, $empty, $search_fields = array(), $search_label = '') use ($widget_root, $el, $loop) {
    $config = array(
        'regionType'          => 'form_list_view',
        'custom_form_page_id' => '{{tab:' . $form_key . '}}',
        'detail_page_id'      => '{{tab:' . $view_key . '}}',
        'items_per_page'      => 20,
        'order_by_field'      => 'submitted_date_and_time',
        'order_by_direction'  => 'DESC',
    );
    if ($search_fields) {
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
            $el('div', 'list-group', lang('Request List'), array($loop(array($row_node)))),
        )),
    );
};

// The contact part of both forms: the name, the e-mail and the phone, the
// status (the staff's), the consent and the send button.
$contact_part = function ($prefix, $statuses, $consent, $send, $promise) use ($el, $row, $col, $field, $input, $select, $agree, $captcha, $submit, $para) {
    return array(
        $row(array(
            $col(array($field($prefix . '-first-name', lang('First Name'), $input($prefix . '-first-name', 'text', 'first_name', 'given-name', true, array('contact_field' => 'first_name')))), '6'),
            $col(array($field($prefix . '-last-name', lang('Last Name'), $input($prefix . '-last-name', 'text', 'last_name', 'family-name', true, array('contact_field' => 'last_name')))), '6'),
            $col(array($field($prefix . '-email', lang('Email'), $input($prefix . '-email', 'email', 'email', 'email', true, array('contact_field' => 'email_address')))), '6'),
            $col(array($field($prefix . '-phone', lang('Phone'), $input($prefix . '-phone', 'tel', 'phone', 'tel', true, array('contact_field' => 'mobile_phone')))), '6'),
        ), '3'),
        $field($prefix . '-status', lang('Status'), $select($prefix . '-status', 'status', '', $statuses, false, $statuses[0], array('office_use_only' => true))),
        $agree($prefix . '-consent', 'consent', $consent, true),
        $captcha(),
        $el('div', 'd-flex flex-wrap align-items-center gap-3', lang('Send Area'), array(
            $submit($send, 'btn btn-primary btn-lg rounded-pill px-4'),
            $para($promise, 'small text-body-secondary mb-0'),
        )),
    );
};

// The lines of a trade-in and of a support request, for their e-mails.
$mail_tradein = function () use ($mail_box, $mail_line, $span, $bind, $sample_old_model, $sample_model, $conditions, $storage_sizes) {
    return $mail_box(array(
        $mail_line(lang('Brand'), array($span('Samsung', '', lang('Brand'), $bind('old_brand')))),
        $mail_line(lang('Model'), array($span($sample_old_model, '', lang('Model'), $bind('old_model')))),
        $mail_line(lang('Storage'), array($span($storage_sizes['128'], '', lang('Storage'), $bind('old_storage')))),
        $mail_line(lang('Phone condition'), array($span($conditions[1][1], '', lang('Phone condition'), $bind('condition')))),
        $mail_line(lang('New Orbit'), array($span($sample_model, '', lang('New Orbit'), $bind('new_model')))),
    ), lang('Trade-in'));
};
$mail_support = function () use ($mail_box, $mail_line, $span, $bind, $support_topics, $sample_model) {
    return $mail_box(array(
        $mail_line(lang('What it is about'), array($span($support_topics[1], '', lang('Topic'), $bind('issue')))),
        $mail_line(lang('Phone model'), array($span($sample_model, '', lang('Phone model'), $bind('model')))),
        $mail_line(lang('Order number'), array($span('10042', '', lang('Order number'), $bind('order_number')))),
    ), lang('Support Request'));
};
$sample_tradein_note = lang('The battery lasts about half a day; there is a small chip on the bottom corner. Box and cable included.');
$sample_support_note = lang('Since the last update the phone loses about a third of its battery overnight, even in flight mode.');

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
    ), array_merge($mail_style('border-top:1px solid #ede9fe;padding-top:16px'), $show_when('has_new_account')));
};
// The customer's lines of a staff e-mail.
$mail_client = function ($more = array()) use ($mail_box, $mail_line, $span, $bind, $person_name) {
    return $mail_box(array_merge(array(
        $mail_line(lang('Customer'), $person_name()),
        $mail_line(lang('Email'), array($span('jane.cooper@example.com', '', lang('Email'), $bind('email')))),
        $mail_line(lang('Phone'), array($span('+90 555 000 00 00', '', lang('Phone'), $bind('phone')))),
    ), $more), lang('Customer'));
};
// The form detail settings of an e-mail page's widget (any reader: the
// e-mail is drawn for the submission just sent) and of a request page's
// widget (the sender's own, and the staff's).
$detail_widget = function ($page_key, $form, $missing, $access) {
    return array(
        'page'   => $page_key,
        'slug'   => lang('form-detail'),
        'config' => array(
            'regionType'          => 'form_item_view',
            'custom_form_page_id' => '{{tab:' . $form . '}}',
            'access_control'      => $access,
            'not_found_message'   => $missing,
        ),
    );
};

$tradein_missing = lang('This trade-in could not be found, or it is not yours to see.');
$support_missing = lang('This support request could not be found, or it is not yours to see.');

$request_widgets = array(

    // The trade-in request: the fields are the page's form. The new Orbit
    // starts with the model a link sends (?model=). The Status is the
    // staff's (office use only): every request starts as Received.
    'tradein_form' => array(
        'page'   => 'tradein',
        'slug'   => 'form',
        'config' => array('regionType' => 'custom_form', 'form_source' => 'page'),
        'tree'   => $widget_root(array_merge(array(
            $form_step(1, lang('Your old phone')),
            $row(array(
                $col(array($field('lx-ti-brand', lang('Brand'), $select('lx-ti-brand', 'old_brand', lang('Choose a brand…'), $old_brands))), '6'),
                $col(array($field('lx-ti-model', lang('Model'), $input('lx-ti-model', 'text', 'old_model', 'off'))), '6'),
            ), '3'),
            $field('lx-ti-storage', lang('Storage'), $select('lx-ti-storage', 'old_storage', lang('Choose a size…'), $old_storage, false), 'mb-3',
                lang('In the phone\'s settings, under About phone or General.')),
            $radios('lx-ti-condition', 'condition', lang('Phone condition'), $conditions, $conditions[1][0]),
            $field('lx-ti-photos', lang('Photos of the phone'), $file('lx-ti-photos', 'photos', 'private', 'image/*'), 'mb-3',
                lang('The front and the back, switched on if it turns on. More can follow under your request.')),
            $field('lx-ti-note', lang('Anything else about the phone?'), $textarea('lx-ti-note', 'note', 3, false), 'mb-3',
                lang('The battery, a repair it had, whether the box and cable come with it.')),
            $form_step(2, lang('Your new Orbit')),
            $field('lx-ti-new', lang('New Orbit'), $select('lx-ti-new', 'new_model', lang('Choose a model…'), $model_names, false, '', array('url_param' => 'model')), 'mb-3',
                lang('The model the credit goes towards; you can still change your mind.')),
            $form_step(3, lang('How to reach you')),
        ), $contact_part('lx-ti', $tradein_statuses,
            lang('I agree that the maker keeps these details to quote for my phone and answer me, as its privacy notice explains.'),
            lang('Get a quote'), lang('We send the quote within a working day.')), array(
            $loop(array()),
        ))),
    ),

    // One trade-in: the request, its status and what the customer wrote;
    // the messages (the quote among them) follow as comments.
    'tradein_detail' => $detail_widget('tradein_view', 'tradein', $tradein_missing, 'submitter_only') + array(
        'tree' => $record_view('bi-phone', lang('Trade-in not found'), $tradein_missing,
            'my_tradeins', lang('My Trade-ins'),
            lang('Thank you, your request has reached us. We send the quote here within a working day; this page shows the status, and you can write to us below.'),
            array(
                $el('div', 'card border-0 shadow-sm rounded-4 mb-4', lang('Trade-in Card'), array(
                    $el('div', 'card-body p-4', lang('Card Body'), array(
                        $record_title(lang('Trade-in'), $sample_old_model, 'old_model', $tradein_statuses[0]),
                        $el('dl', 'row small mb-0', lang('Details'), array_merge(
                            $detail(lang('Brand'), '', lang('Brand'), $bound('Samsung', 'old_brand')),
                            $detail(lang('Storage'), '', lang('Storage'), $bound($storage_sizes['128'], 'old_storage')),
                            $detail(lang('Phone condition'), '', lang('Phone condition'), $bound($conditions[1][1], 'condition')),
                            $detail(lang('New Orbit'), '', lang('New Orbit'), $bound($sample_model, 'new_model')),
                            $detail(lang('Customer'), '', lang('Customer'), $person_name(), true),
                            $detail(lang('Photos of the phone'), 'text-break', lang('Photos'), $file_link('photos', 'phone-front.jpg'), true),
                            $detail(lang('Reference Code'), 'mb-0 font-monospace', lang('Reference Code'), $bound('ABC123', 'reference_code'))
                        )),
                    )),
                )),
                $record_text(lang('Anything else about the phone?'), 'note', $sample_tradein_note),
                $record_note(lang('More photos or a question about the quote? Write to us below; we answer here and by e-mail.')),
            )),
    ),

    'tradein_messages' => $request_messages('tradein_view',
        lang('No messages yet. Your quote comes here; you can write to us about the trade-in too.'),
        lang('Write to us'), lang('Sign in to write to us.')),

    'my_tradeins_list' => $request_list('my_tradeins', 'tradein', 'tradein_view', $tradein_row(),
        lang('No trade-in requests yet. Tell us about your old phone for a quote.')),

    'staff_tradeins_list' => $request_list('staff_tradeins', 'tradein', 'tradein_view', $tradein_row(true),
        lang('No trade-in requests yet.'),
        array('first_name', 'last_name', 'old_brand', 'old_model', 'condition', 'new_model', 'status'),
        lang('Search by customer, brand, model, condition or status...')),

    // The support request: the fields are the page's form. The model starts
    // with the one a link sends (?model=).
    'support_form' => array(
        'page'   => 'support',
        'slug'   => 'form',
        'config' => array('regionType' => 'custom_form', 'form_source' => 'page'),
        'tree'   => $widget_root(array_merge(array(
            $form_step(1, lang('Your phone and what happens')),
            $row(array(
                $col(array($field('lx-sr-model', lang('Phone model'), $select('lx-sr-model', 'model', lang('Choose a model…'), $model_names, true, '', array('url_param' => 'model')))), '6'),
                $col(array($field('lx-sr-topic', lang('What it is about'), $select('lx-sr-topic', 'issue', lang('Choose a topic…'), $support_topics))), '6'),
            ), '3'),
            $field('lx-sr-order', lang('Order number'), $input('lx-sr-order', 'text', 'order_number', 'off', false), 'mb-3',
                lang('On your order e-mail; it helps us find the warranty. Leave it empty for a phone bought elsewhere.')),
            $field('lx-sr-description', lang('What happens?'), $textarea('lx-sr-description', 'description', 5), 'mb-3',
                lang('What you see, since when, and what you have tried.')),
            $field('lx-sr-file', lang('Receipt or photo'), $file('lx-sr-file', 'documents', 'private'), 'mb-3',
                lang('A receipt, a screenshot or a photo of the damage (PDF or a picture). More can follow under your request.')),
            $form_step(2, lang('How to reach you')),
        ), $contact_part('lx-sr', $support_statuses,
            lang('I agree that the maker keeps these details to look into my request and answer me, as its privacy notice explains.'),
            lang('Send the Request'), lang('We answer within a working day.')), array(
            $loop(array()),
        ))),
    ),

    // One support request: the request, its status and what the customer
    // wrote; the answers follow as comments.
    'support_detail' => $detail_widget('support_view', 'support', $support_missing, 'submitter_only') + array(
        'tree' => $record_view('bi-tools', lang('Support request not found'), $support_missing,
            'my_support', lang('My Support Requests'),
            lang('Thank you, your request has reached us. A specialist answers here within a working day; this page shows the status, and you can write to us below.'),
            array(
                $el('div', 'card border-0 shadow-sm rounded-4 mb-4', lang('Support Request Card'), array(
                    $el('div', 'card-body p-4', lang('Card Body'), array(
                        $record_title(lang('Support Request'), $support_topics[1], 'issue', $support_statuses[0]),
                        $el('dl', 'row small mb-0', lang('Details'), array_merge(
                            $detail(lang('Phone model'), '', lang('Phone model'), $bound($sample_model, 'model')),
                            $detail(lang('Order number'), '', lang('Order number'), $bound('10042', 'order_number')),
                            $detail(lang('Customer'), '', lang('Customer'), $person_name(), true),
                            $detail(lang('Receipt or photo'), 'text-break', lang('Documents'), $file_link('documents', 'receipt.pdf'), true),
                            $detail(lang('Reference Code'), 'mb-0 font-monospace', lang('Reference Code'), $bound('ABC123', 'reference_code'))
                        )),
                    )),
                )),
                $record_text(lang('What happens?'), 'description', $sample_support_note),
                $record_note(lang('Something changed, or a question? Write to us below; we answer here and by e-mail.')),
            )),
    ),

    'support_messages' => $request_messages('support_view',
        lang('No messages yet. Our answer comes here; you can add details or a photo too.'),
        lang('Write to us'), lang('Sign in to write to us.')),

    'my_support_list' => $request_list('my_support', 'support', 'support_view', $support_row(),
        lang('No support requests yet. Something wrong with your phone? Tell us what happens.')),

    'staff_support_list' => $request_list('staff_support', 'support', 'support_view', $support_row(true),
        lang('No support requests yet.'),
        array('first_name', 'last_name', 'model', 'issue', 'order_number', 'status'),
        lang('Search by customer, model, topic, order or status...')),

    // The e-mail pages' widgets: drawn for the submission just sent.
    'email_tradein_admin' => $detail_widget('email_tradein_new', 'tradein', lang('The trade-in request could not be found.'), 'public') + array(
        'tree' => $widget_root(array(
            $mail_heading(lang('A new trade-in request')),
            $mail_para(lang('It came from the trade-in form. Write the quote under the request on its page and set the status; what you write there reaches the customer by e-mail.')),
            $mail_tradein(),
            $mail_client(array(
                $mail_line(lang('Anything else about the phone?'), array($span($sample_tradein_note, '', lang('Note'), $bind('note')))),
            )),
            $mail_button(lang('Open the request'), '{{page:tradein_view}}?r=^^reference_code^^'),
            $mail_reference(),
        )),
    ),
    'email_tradein_client' => $detail_widget('email_tradein_received', 'tradein', lang('The trade-in request could not be found.'), 'public') + array(
        'tree' => $widget_root(array(
            $mail_heading(lang('We received your trade-in request')),
            $mail_greeting(),
            $mail_para(lang('Thank you. We look at what you told us and send a quote within a working day, on the request\'s page and by e-mail.')),
            $mail_tradein(),
            $mail_para(lang('More photos or a question? Write to us on the request\'s page; everything about your trade-in stays there.')),
            $mail_button(lang('Open my request'), '{{page:tradein_view}}?r=^^reference_code^^'),
            $mail_new_account(),
        )),
    ),
    'email_support_admin' => $detail_widget('email_support_new', 'support', lang('The support request could not be found.'), 'public') + array(
        'tree' => $widget_root(array(
            $mail_heading(lang('A new support request')),
            $mail_para(lang('It came from the support form. Answer under the request on its page and set the status; what you write there reaches the customer by e-mail.')),
            $mail_support(),
            $mail_client(array(
                $mail_line(lang('What happens?'), array($span($sample_support_note, '', lang('Text'), $bind('description')))),
            )),
            $mail_button(lang('Open the request'), '{{page:support_view}}?r=^^reference_code^^'),
            $mail_reference(),
        )),
    ),
    'email_support_client' => $detail_widget('email_support_received', 'support', lang('The support request could not be found.'), 'public') + array(
        'tree' => $widget_root(array(
            $mail_heading(lang('We received your support request')),
            $mail_greeting(),
            $mail_para(lang('Thank you. A specialist reads it and answers within a working day; if the phone needs a repair, we arrange the collection with you.')),
            $mail_support(),
            $mail_para(lang('Something changed, or a photo to add? Write to us on the request\'s page; everything about it stays there.')),
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

return array('pages' => $request_pages, 'widgets' => $request_widgets);
