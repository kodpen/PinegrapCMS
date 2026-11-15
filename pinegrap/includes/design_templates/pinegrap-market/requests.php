<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Marketplace", its requests: the application of a maker
 * who would like to sell on the market, its own page with its messages, My
 * Applications and the staff's list; the help request about an order, its
 * page, My Help Requests and the staff's list; the privacy page the consent
 * boxes link, the e-mails of both forms and the new message e-mail, and the
 * widgets that draw them. Included after parts.php and mail.php in the same
 * scope; returns array('pages' => ..., 'widgets' => ...).
 *
 * A request is a record of its form (Applications, Help Requests), as a
 * valuation is in the Real Estate template. A visitor without an account
 * gets one (auto-registration), so the request's page and the My lists
 * show it at once. Its Status is an office-use-only field the staff change
 * in the control panel; the messages under it are the page's comments,
 * mailed to the sender and to the market.
 *
 * An approved application opens no shop by itself: makers are profiles,
 * not accounts, so the staff add the maker to the Sellers collection by
 * hand (New Maker) and the products to the catalog. The application form's
 * Category starts with the category page's choice (?category=), the help
 * form's Order number and Topic with the order page's (?order=, ?topic=).
 * The photos and receipts sent are kept in the template's private folder:
 * the record's page shows them to whoever may read the record.
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
$application_statuses = array(lang('Received'), lang('In review'), lang('Approved'), lang('Not this time'));
$help_statuses = array(lang('Received'), lang('In progress'), lang('Waiting for your answer'), lang('Resolved'));

// The category titles, the values the application stores (the Sellers
// collection stores the same words).
$request_category_titles = array();
foreach ($categories as $c) $request_category_titles[] = $c['title'];

// How soon a maker can send a piece once it is ordered.
$prep_times = array(lang('Ready to send'), lang('Within a week'), lang('Within two weeks'), lang('Made to order, longer'));

// What a help request is about.
$help_topics = array(
    lang('Where is my order?'),
    lang('Damaged or not as described'),
    lang('Return or exchange'),
    lang('A question about a piece'),
    lang('Something else'),
);

// The side column of a request page: what happens next, then a box that
// leads to the page answering the usual questions first.
$request_aside = function ($steps, $box_title, $box_text, $box_link_text, $box_key) use ($el, $heading, $para, $link) {
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
            $heading('h2', $box_title, 'h5 fw-semibold mb-2'),
            $para($box_text, 'small text-body-secondary mb-3'),
            $link($box_link_text, '{{page:' . $box_key . '}}', 'btn btn-sm btn-outline-primary rounded-pill px-3'),
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
$request_my_page = function ($title, $lead, $list_widget, $new_title, $new_text, $new_link_text, $new_key, $new_icon) use ($page, $page_header, $el, $container, $row, $col, $widget, $icon, $span, $para, $link) {
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
$request_staff_page = function ($title, $lead, $list_widget) use ($staff_page, $page_header, $el, $container, $widget) {
    return $staff_page(array(
        $page_header($title, $lead),
        $el('section', 'py-5', lang('Content'), array(
            $container(array($widget($list_widget))),
        )),
    ));
};

// ── Pages ───────────────────────────────────────────────────────────────

// The application of a maker. What is sent opens the application's own page.
$request_pages[] = array(
    'key'              => 'apply',
    'name'             => lang('apply-to-sell'),
    'folder'           => 'public',
    'title'            => lang('Apply to Sell'),
    'meta_description' => lang('Tell us about your work and your shop: it takes ten minutes, and we answer every application within two weeks.'),
    'form'             => array(
        'form_name'            => lang('Maker Applications'),
        'confirmation_message' => lang('Thank you, your application has reached us. We answer within two weeks; you can follow it from your account.'),
        'confirmation_page_id' => '{{tab:application_view}}',
        'auto_registration'    => 1,
        'notify_email'         => '{{site_email}}',
        'notify_subject'       => lang('New maker application: ^^shop_name^^'),
        'notify_page_id'       => '{{tab:email_application_new}}',
        'confirm_email'        => 1,
        'confirm_subject'      => lang('We received your application'),
        'confirm_page_id'      => '{{tab:email_application_received}}',
    ),
    'tree'             => $request_page(lang('Apply to Sell'),
        lang('Tell us about your work and your shop. It takes ten minutes, and we answer every application within two weeks.'),
        'application_form', $request_aside(array(
            lang('Your application opens its own page, and an account for you if you have none.'),
            lang('We look at your work and may ask for a sample piece.'),
            lang('Once we agree, we photograph your pieces and open your shop on the market.'),
        ), lang('What we look for'), lang('Pieces you make yourself, materials you can name and photos that show the work as it is.'),
            lang('Sell with us'), 'sell')),
);

// One application: what the maker sent, its status and the messages under it.
$request_pages[] = array(
    'key'      => 'application_view',
    'name'     => lang('application'),
    'folder'   => 'registration',
    'title'    => lang('Maker Application'),
    'search'   => false,
    'sitemap'  => false,
    'comments' => array('label' => lang('Message'), 'allow_new' => true, 'auto_publish' => true, 'show_date' => true, 'login' => true,
                        'attachments' => true,
                        'email_page' => '{{tab:email_reply}}', 'email_subject' => lang('There is a new message about your application'),
                        'notify_email' => '{{site_email}}'),
    'tree'     => $page(array(
        $widget('application_detail'),
        $comments_section(lang('Messages'), 'application_messages'),
    ), false),
);

// My Applications: the maker's applications (for the staff, everybody's).
$request_pages[] = array(
    'key'     => 'my_applications',
    'name'    => lang('my-applications'),
    'folder'  => 'registration',
    'title'   => lang('My Applications'),
    'search'  => false,
    'sitemap' => false,
    'tree'    => $request_my_page(lang('My Applications'), lang('Your applications to sell on the market, their status and our messages, newest first.'),
        'my_applications_list', lang('Another shop?'), lang('Send an application for it; your details are filled in for you.'),
        lang('Apply to Sell'), 'apply', 'bi-shop'),
);

// The staff's list of every application, newest first, with a search.
$request_pages[] = array(
    'key'     => 'staff_applications',
    'name'    => lang('all-applications'),
    'folder'  => 'private',
    'title'   => lang('All Applications'),
    'search'  => false,
    'sitemap' => false,
    'tree'    => $request_staff_page(lang('All Applications'),
        lang('Every maker application, newest first: set the status and answer under each. An approved maker is added with New Maker.'),
        'staff_applications_list'),
);

// The help request about an order.
$request_pages[] = array(
    'key'              => 'help',
    'name'             => lang('order-help'),
    'folder'           => 'public',
    'title'            => lang('Help with an Order'),
    'meta_description' => lang('Something wrong with an order? Tell us what happened: we answer within a working day and you can follow it from your account.'),
    'form'             => array(
        'form_name'            => lang('Help Requests'),
        'confirmation_message' => lang('Thank you, your request has reached us. We answer within a working day; you can follow it from your account.'),
        'confirmation_page_id' => '{{tab:help_view}}',
        'auto_registration'    => 1,
        'notify_email'         => '{{site_email}}',
        'notify_subject'       => lang('New help request: ^^topic^^'),
        'notify_page_id'       => '{{tab:email_help_new}}',
        'confirm_email'        => 1,
        'confirm_subject'      => lang('We received your help request'),
        'confirm_page_id'      => '{{tab:email_help_received}}',
    ),
    'tree'             => $request_page(lang('Help with an Order'),
        lang('A late parcel, a broken piece or a change of mind? Tell us what happened; we answer within a working day.'),
        'help_form', $request_aside(array(
            lang('Your request opens its own page, and an account for you if you have none.'),
            lang('We look at your order and ask the maker where needed.'),
            lang('We answer on your request\'s page and by e-mail, until it is resolved.'),
        ), lang('Quick answers'), lang('Delivery times, returns and payments: the usual questions are answered on one page.'),
            lang('Questions and Answers'), 'faq')),
);

// One help request: what was sent, its status and the messages under it.
$request_pages[] = array(
    'key'      => 'help_view',
    'name'     => lang('help-request'),
    'folder'   => 'registration',
    'title'    => lang('Help Request'),
    'search'   => false,
    'sitemap'  => false,
    'comments' => array('label' => lang('Message'), 'allow_new' => true, 'auto_publish' => true, 'show_date' => true, 'login' => true,
                        'attachments' => true,
                        'email_page' => '{{tab:email_reply}}', 'email_subject' => lang('There is a new message about your help request'),
                        'notify_email' => '{{site_email}}'),
    'tree'     => $page(array(
        $widget('help_detail'),
        $comments_section(lang('Messages'), 'help_messages'),
    ), false),
);

// My Help Requests: the customer's requests (for the staff, everybody's).
$request_pages[] = array(
    'key'     => 'my_help',
    'name'    => lang('my-help-requests'),
    'folder'  => 'registration',
    'title'   => lang('My Help Requests'),
    'search'  => false,
    'sitemap' => false,
    'tree'    => $request_my_page(lang('My Help Requests'), lang('Your help requests about orders, their status and our answers, newest first.'),
        'my_help_list', lang('Something else?'), lang('Send a new request; your details are filled in for you.'),
        lang('Help with an Order'), 'help', 'bi-life-preserver'),
);

// The staff's list of every help request, newest first, with a search.
$request_pages[] = array(
    'key'     => 'staff_help',
    'name'    => lang('all-help-requests'),
    'folder'  => 'private',
    'title'   => lang('All Help Requests'),
    'search'  => false,
    'sitemap' => false,
    'tree'    => $request_staff_page(lang('All Help Requests'),
        lang('Every help request, newest first: look at the order, set the status and answer under each.'),
        'staff_help_list'),
);

// The privacy notice the consent boxes link to: sample text with a note to
// adapt it.
$request_pages[] = array(
    'key'              => 'privacy',
    'name'             => lang('privacy-policy'),
    'folder'           => 'public',
    'title'            => lang('Privacy Policy'),
    'meta_description' => lang('What the market keeps about you, why, and what you can ask of us.'),
    'tree'             => $page(array(
        $page_header(lang('Privacy Policy'), lang('What the market keeps about you, why, and what you can ask of us.')),
        $el('section', 'py-5', lang('Your Data'), array(
            $container(array($row(array($col(array(
                $el('div', 'alert alert-secondary d-flex gap-2 small', lang('Sample Text Note'), array(
                    $icon('bi-info-circle', 'mt-1'),
                    $span(lang('Sample text: adapt it to your market and to the data protection law that applies to you before you publish.'), '', lang('Text')),
                ), array('_attrs' => array($attr('role', 'note')))),
                $heading('h2', lang('Your data'), 'h4 fw-semibold mb-3'),
                $para(lang('When you order, ask for help with an order, apply to sell, sign up for the newsletter or open an account, we keep your name, your contact details, your orders and what you send us, to deliver your order, answer you and look at your application.'), 'mb-3'),
                $para(lang('A maker receives only what is needed to make or mend your piece, never your payment details. We keep your data as long as the law requires, never sell it and never give it to advertisers. You can see and change your account data from My Account, and ask us about the rest through the contact page.'), 'mb-0'),
            ), '', '8'))))),
        ), $searchable('your-data', lang('privacy, personal data, KVKK, GDPR, delete my data'))),
    )),
);

// The e-mails: what the staff receive and the reply to the sender, for each
// form, and the new message e-mail.
$request_pages[] = array_merge($mail_settings, array(
    'key'   => 'email_application_new',
    'name'  => lang('email-new-application'),
    'title' => lang('New Application E-mail'),
    'tree'  => $mail_page(array($widget('email_application_admin'))),
));
$request_pages[] = array_merge($mail_settings, array(
    'key'   => 'email_application_received',
    'name'  => lang('email-application-received'),
    'title' => lang('Application Received E-mail'),
    'tree'  => $mail_page(array($widget('email_application_client'))),
));
$request_pages[] = array_merge($mail_settings, array(
    'key'   => 'email_help_new',
    'name'  => lang('email-new-help-request'),
    'title' => lang('New Help Request E-mail'),
    'tree'  => $mail_page(array($widget('email_help_admin'))),
));
$request_pages[] = array_merge($mail_settings, array(
    'key'   => 'email_help_received',
    'name'  => lang('email-help-request-received'),
    'title' => lang('Help Request Received E-mail'),
    'tree'  => $mail_page(array($widget('email_help_client'))),
));
// A new message or reply: sent to the sender of an application or a help
// request, the sender of a conversation and the visitors watching a page.
// Its Comments widget draws the comment in the card of mail.php; the System
// region after it keeps the fixed comment for an e-mail the widget does
// not draw.
$request_pages[] = array_merge($mail_settings, array(
    'key'   => 'email_reply',
    'name'  => lang('email-new-reply'),
    'title' => lang('New Reply E-mail'),
    'tree'  => $mail_page(array(
        $mail_heading(lang('There is a new message')),
        $mail_para(lang('Something new was written on a page you follow: your application, your help request, a conversation or a Journal post. You can read it below and answer it on the page.')),
        $widget('email_comment'),
        array('type' => 'region', 'props' => array('regionType' => 'system', 'regionName' => ''), 'children' => array()),
    )),
));

// ── Widget pieces ───────────────────────────────────────────────────────

// The sender's name.
$request_person = function () use ($span, $bind) {
    return array(
        $span(lang('Jane'), '', lang('First Name'), $bind('first_name')),
        $span(' ', '', lang('Text')),
        $span(lang('Cooper'), '', lang('Last Name'), $bind('last_name')),
    );
};

// A request in a list: its title field, the status, the details, linked to
// its page. $details: array(icon, label, sample, field) rows.
$request_row = function ($name, $title_field, $title_sample, $details, $staff) use ($el, $span, $icon, $bind, $request_person) {
    $items = array();
    foreach ($details as $d) {
        $items[] = $el('span', 'd-inline-flex align-items-center gap-1', $d[1], array($icon($d[0]), $span($d[2], '', $d[1], $bind($d[3]))));
    }
    $items[] = $el('span', 'd-inline-flex align-items-center gap-1', lang('Sent'), array($icon('bi-calendar3'), $span(lang('Date'), '', lang('Date'), $bind('submitted_date_and_time'))));
    if ($staff) {
        $items[] = $el('span', 'd-inline-flex align-items-center gap-1', lang('Phone'), array($icon('bi-telephone'), $span('+90 555 000 00 00', '', lang('Phone'), $bind('phone'))));
        $title = $el('span', 'fw-semibold', lang('Sender'), array_merge($request_person(), array(
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
            $span(lang('Received'), 'badge rounded-pill bg-primary-subtle text-primary-emphasis', lang('Status'), $bind('status')),
        )),
        $el('div', 'd-flex flex-wrap column-gap-3 row-gap-1 small text-body-secondary', lang('Details'), $items),
    ), array('href' => '#', '_bindings' => array('href' => 'form_item_view')));
};

// The samples a request shows before it is sent: a maker who is not yet on
// the market, and an order of one of the market's pieces.
$sample_shop = lang('Saltmarsh Weavers');
$sample_city = lang('Porto');
$sample_order = '1042';
$sample_work = lang('I weave baskets and table mats from rush and seagrass I cut myself, and dye some of them with onion skins and walnut.');
$sample_help = lang('The bowl arrived with a chip on its rim. I have attached a photo of it and of the box.');

$application_row = function ($staff = false) use ($request_row, $sample_shop, $sample_city, $request_category_titles) {
    return $request_row(lang('Maker Application'), 'shop_name', $sample_shop, array(
        array('bi-grid', lang('Category'), $request_category_titles[0], 'category'),
        array('bi-geo-alt', lang('City'), $sample_city, 'city'),
    ), $staff);
};
$help_row = function ($staff = false) use ($request_row, $sample_order, $help_topics) {
    return $request_row(lang('Help Request'), 'topic', $help_topics[1], array(
        array('bi-receipt', lang('Order number'), $sample_order, 'order_number'),
    ), $staff);
};

// A detail of a record's card: the label, then the value. $value is either
// the value's props (its sample text and binding) or, with $children, the
// nodes it is made of.
$request_detail = function ($label, $class, $name, $value, $children = false) use ($el) {
    return array(
        $el('dt', 'col-sm-4 fw-normal text-body-secondary', lang('Label'), array(), array('text' => $label)),
        $el('dd', trim('col-sm-8 ' . $class), $name, $children ? $value : array(), $children ? array() : $value),
    );
};
$request_bound = function ($sample, $field) use ($bind) {
    return array_merge(array('text' => $sample), $bind($field));
};
// A file the record carries, as a link that opens it.
$request_file_link = function ($field, $sample) use ($link) {
    return array($link($sample, '#', '', array(), array('target' => '_blank', 'rel' => 'noopener',
        '_bindings' => array('href' => $field, 'text' => $field))));
};

// A record's page (an application, a help request): the not-found notice,
// the sent notice, the new account notice, the way back and the Edit
// button, then the record's own parts.
$request_record_view = function ($icon_name, $missing_title, $missing_text, $back_key, $back_text, $sent_text, $parts) use ($el, $heading, $para, $link, $span, $icon, $attr, $bind, $show_when, $container, $row, $col, $edit_button) {
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
$request_record_title = function ($kind, $sample, $field) use ($el, $heading, $para, $span, $bind) {
    return $el('div', 'd-flex flex-wrap align-items-start justify-content-between gap-2 mb-3', lang('Title'), array(
        $el('div', '', lang('Words'), array(
            $para($kind, 'pmk-eyebrow small text-uppercase fw-semibold text-primary mb-1'),
            $heading('h1', $sample, 'h3 fw-semibold mb-0', $bind($field)),
        )),
        $span(lang('Received'), 'badge rounded-pill bg-primary-subtle text-primary-emphasis fs-6', lang('Status'), $bind('status')),
    ));
};
// A long text the record carries, in a soft box that leaves when empty.
$request_record_text = function ($label, $field, $sample) use ($el, $para, $bind) {
    return $el('div', 'pg-hide-if-empty p-4 rounded-4 bg-body-tertiary mb-4', $label, array(
        $para($label, 'small fw-semibold text-body-secondary mb-2'),
        $el('div', 'pg-field-value lh-lg', lang('Value'), array(), array_merge(array('text' => $sample), $bind($field))),
    ));
};
// The note under a record's card: where to write.
$request_record_note = function ($text) use ($el, $icon, $span) {
    return $el('div', 'd-flex gap-2 small text-body-secondary', lang('Note'), array(
        $icon('bi-chat-left-text', 'text-primary mt-1'),
        $span($text, '', lang('Text')),
    ));
};

// The messages under a request: the page's own Comments widget, shown only
// to whoever may read the request above it. Oldest first, all on one page,
// with a file each. No Reply, Share or follow box: the sender is e-mailed
// every answer and the market every message.
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

// A list of requests: the sender's own (the staff see them all), or the
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
// status (the staff's), the consent with the privacy link and the send
// button.
$request_contact = function ($prefix, $statuses, $consent, $send, $promise) use ($el, $row, $col, $field, $input, $select, $agree, $captcha, $submit, $para) {
    return array(
        $row(array(
            $col(array($field($prefix . '-first-name', lang('First Name'), $input($prefix . '-first-name', 'text', 'first_name', 'given-name', true, array('contact_field' => 'first_name')))), '6'),
            $col(array($field($prefix . '-last-name', lang('Last Name'), $input($prefix . '-last-name', 'text', 'last_name', 'family-name', true, array('contact_field' => 'last_name')))), '6'),
            $col(array($field($prefix . '-email', lang('Email'), $input($prefix . '-email', 'email', 'email', 'email', true, array('contact_field' => 'email_address')))), '6'),
            $col(array($field($prefix . '-phone', lang('Phone'), $input($prefix . '-phone', 'tel', 'phone', 'tel', false, array('contact_field' => 'mobile_phone')))), '6'),
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

// The lines of an application and of a help request, for their e-mails.
$mail_application = function () use ($mail_box, $mail_line, $span, $bind, $sample_shop, $sample_city, $request_category_titles, $prep_times) {
    return $mail_box(array(
        $mail_line(lang('Shop name'), array($span($sample_shop, '', lang('Shop name'), $bind('shop_name')))),
        $mail_line(lang('Category'), array($span($request_category_titles[0], '', lang('Category'), $bind('category')))),
        $mail_line(lang('City'), array($span($sample_city, '', lang('City'), $bind('city')))),
        $mail_line(lang('Preparation time'), array($span($prep_times[1], '', lang('Preparation time'), $bind('lead_time')))),
    ), lang('Maker Application'));
};
$mail_help = function () use ($mail_box, $mail_line, $span, $bind, $sample_order, $help_topics) {
    return $mail_box(array(
        $mail_line(lang('Topic'), array($span($help_topics[1], '', lang('Topic'), $bind('topic')))),
        $mail_line(lang('Order number'), array($span($sample_order, '', lang('Order number'), $bind('order_number')))),
    ), lang('Help Request'));
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
    ), array_merge($mail_style('border-top:1px solid #fed7aa;padding-top:16px'), $show_when('has_new_account')));
};
// The sender's lines of a staff e-mail.
$mail_sender = function ($more = array()) use ($mail_box, $mail_line, $span, $bind, $request_person) {
    return $mail_box(array_merge(array(
        $mail_line(lang('Sender'), $request_person()),
        $mail_line(lang('Email'), array($span('jane.cooper@example.com', '', lang('Email'), $bind('email')))),
        $mail_line(lang('Phone'), array($span('+90 555 000 00 00', '', lang('Phone'), $bind('phone')))),
    ), $more), lang('Sender'));
};
// The form detail settings of an e-mail page's widget (public: the mail is
// drawn for the submission just sent) and of a request page's widget (its
// sender only, and the staff).
$request_detail_config = function ($page, $form, $missing, $access) {
    return array(
        'page'   => $page,
        'slug'   => lang('form-detail'),
        'config' => array(
            'regionType'          => 'form_item_view',
            'custom_form_page_id' => '{{tab:' . $form . '}}',
            'access_control'      => $access,
            'not_found_message'   => $missing,
        ),
    );
};

$application_missing = lang('This application could not be found, or it is not yours to see.');
$help_missing = lang('This help request could not be found, or it is not yours to see.');

$request_widgets = array(

    // The application: the fields are the page's form. The Category starts
    // with the category page's choice (?category=). The Status is the
    // staff's (office use only): every application starts as Received.
    'application_form' => array(
        'page'   => 'apply',
        'slug'   => 'form',
        'config' => array('regionType' => 'custom_form', 'form_source' => 'page'),
        'tree'   => $widget_root(array_merge(array(
            $form_step(1, lang('Your shop')),
            $row(array(
                $col(array($field('mk-ap-shop', lang('Shop name'), $input('mk-ap-shop', 'text', 'shop_name', 'organization'))), '6'),
                $col(array($field('mk-ap-category', lang('Category'), $select('mk-ap-category', 'category', lang('Choose a category…'), $request_category_titles, true, '', array('url_param' => 'category')))), '6'),
                $col(array($field('mk-ap-city', lang('City'), $input('mk-ap-city', 'text', 'city', 'address-level2'))), '6'),
                $col(array($field('mk-ap-lead', lang('Preparation time'), $select('mk-ap-lead', 'lead_time', lang('Choose a preparation time…'), $prep_times))), '6'),
            ), '3'),
            $field('mk-ap-website', lang('Website or social page'), $input('mk-ap-website', 'url', 'website', 'url', false), 'mb-3',
                lang('Where we can see more of your work, if you have such a page.')),
            $field('mk-ap-work', lang('Your work'), $textarea('mk-ap-work', 'description', 5), 'mb-3',
                lang('What you make, from which materials and how; how many pieces you can make in a month.')),
            $field('mk-ap-photos', lang('Photos of your work'), $file('mk-ap-photos', 'photos', 'private', 'image/*'), 'mb-3',
                lang('One photo of a piece as it is; more can follow under your application.')),
            $form_step(2, lang('About you')),
        ), $request_contact('mk-ap', $application_statuses,
            lang('I agree that the market keeps these details to look at my application and answer me, as its privacy notice explains.'),
            lang('Send the Application'), lang('We answer within two weeks.')), array(
            $loop(array()),
        ))),
    ),

    // One application: what the maker sent and its status; the messages
    // follow as comments.
    'application_detail' => $request_detail_config('application_view', 'apply', $application_missing, 'submitter_only') + array(
        'tree' => $request_record_view('bi-shop-window', lang('Application not found'), $application_missing,
            'my_applications', lang('My Applications'),
            lang('Thank you, your application has reached us. We answer within two weeks; this page shows the status, and you can write to us below.'),
            array(
                $el('div', 'card border-0 shadow-sm rounded-4 mb-4', lang('Application Card'), array(
                    $el('div', 'card-body p-4', lang('Card Body'), array(
                        $request_record_title(lang('Maker Application'), $sample_shop, 'shop_name'),
                        $optional_image('photos', 'shop_name', 'rounded-3 object-fit-cover', 'ratio ratio-16x9 mb-3',
                            $photo('apply', 1200, 675), lang('Photos of your work')),
                        $el('dl', 'row small mb-0', lang('Details'), array_merge(
                            $request_detail(lang('Category'), '', lang('Category'), $request_bound($request_category_titles[0], 'category')),
                            $request_detail(lang('City'), '', lang('City'), $request_bound($sample_city, 'city')),
                            $request_detail(lang('Preparation time'), '', lang('Preparation time'), $request_bound($prep_times[1], 'lead_time')),
                            $request_detail(lang('Website or social page'), 'text-break', lang('Website'), $request_file_link('website', 'example.com'), true),
                            $request_detail(lang('Maker'), '', lang('Maker'), $request_person(), true),
                            $request_detail(lang('Reference Code'), 'mb-0 font-monospace', lang('Reference Code'), $request_bound('ABC123', 'reference_code'))
                        )),
                    )),
                )),
                $request_record_text(lang('Your work'), 'description', $sample_work),
                $request_record_note(lang('More photos or a question? Write to us below; we answer here and by e-mail.')),
            )),
    ),

    'application_messages' => $request_messages('application_view',
        lang('No messages yet. Write to us here about your application.'),
        lang('Write to the market'), lang('Sign in to write to the market.')),

    'my_applications_list' => $request_list('my_applications', 'apply', 'application_view', $application_row(),
        lang('No applications yet. Tell us about your work to open a shop.')),

    'staff_applications_list' => $request_list('staff_applications', 'apply', 'application_view', $application_row(true),
        lang('No applications yet.'),
        array('first_name', 'last_name', 'shop_name', 'category', 'city', 'status'),
        lang('Search by maker, shop, category, city or status...')),

    // The help request: the fields are the page's form. The Order number
    // and the Topic start with the order page's choice (?order=, ?topic=).
    'help_form' => array(
        'page'   => 'help',
        'slug'   => 'form',
        'config' => array('regionType' => 'custom_form', 'form_source' => 'page'),
        'tree'   => $widget_root(array_merge(array(
            $form_step(1, lang('Your order')),
            $row(array(
                $col(array($field('mk-hp-order', lang('Order number'), $input('mk-hp-order', 'text', 'order_number', 'off', false, array('url_param' => 'order')), 'mb-3',
                    lang('On your order e-mail and in My Account.'))), '6'),
                $col(array($field('mk-hp-topic', lang('Topic'), $select('mk-hp-topic', 'topic', lang('Choose a topic…'), $help_topics, true, '', array('url_param' => 'topic')))), '6'),
            ), '3'),
            $field('mk-hp-message', lang('What happened?'), $textarea('mk-hp-message', 'message', 5), 'mb-3',
                lang('Which piece, what is wrong and what you would like: a replacement, a repair or your money back.')),
            $field('mk-hp-file', lang('Photo or receipt'), $file('mk-hp-file', 'attachment', 'private'), 'mb-3',
                lang('A photo of the damage or of the parcel, or a receipt (PDF or a picture).')),
            $form_step(2, lang('How to reach you')),
        ), $request_contact('mk-hp', $help_statuses,
            lang('I agree that the market keeps these details to answer my request, and shares what is needed with the maker, as its privacy notice explains.'),
            lang('Send the Request'), lang('We answer within a working day.')), array(
            $loop(array()),
        ))),
    ),

    // One help request: what was sent and its status; the messages follow
    // as comments.
    'help_detail' => $request_detail_config('help_view', 'help', $help_missing, 'submitter_only') + array(
        'tree' => $request_record_view('bi-life-preserver', lang('Help request not found'), $help_missing,
            'my_help', lang('My Help Requests'),
            lang('Thank you, your request has reached us. We answer within a working day; this page shows the status, and you can write to us below.'),
            array(
                $el('div', 'card border-0 shadow-sm rounded-4 mb-4', lang('Help Request Card'), array(
                    $el('div', 'card-body p-4', lang('Card Body'), array(
                        $request_record_title(lang('Help Request'), $help_topics[1], 'topic'),
                        $el('dl', 'row small mb-0', lang('Details'), array_merge(
                            $request_detail(lang('Order number'), 'font-monospace', lang('Order number'), $request_bound($sample_order, 'order_number')),
                            $request_detail(lang('Customer'), '', lang('Customer'), $request_person(), true),
                            $request_detail(lang('Photo or receipt'), 'text-break', lang('Attachment'), $request_file_link('attachment', 'photo.jpg'), true),
                            $request_detail(lang('Reference Code'), 'mb-0 font-monospace', lang('Reference Code'), $request_bound('ABC123', 'reference_code'))
                        )),
                    )),
                )),
                $request_record_text(lang('What happened?'), 'message', $sample_help),
                $request_record_note(lang('Anything to add? Write to us below; we answer here and by e-mail until it is resolved.')),
            )),
    ),

    'help_messages' => $request_messages('help_view',
        lang('No messages yet. Write to us here about your request.'),
        lang('Write to the market'), lang('Sign in to write to the market.')),

    'my_help_list' => $request_list('my_help', 'help', 'help_view', $help_row(),
        lang('No help requests yet. Something wrong with an order? Tell us from Help.')),

    'staff_help_list' => $request_list('staff_help', 'help', 'help_view', $help_row(true),
        lang('No help requests yet.'),
        array('first_name', 'last_name', 'order_number', 'topic', 'status'),
        lang('Search by customer, order number, topic or status...')),

    // The e-mail pages' widgets: drawn for the submission just sent.
    'email_application_admin' => $request_detail_config('email_application_new', 'apply', lang('The application could not be found.'), 'public') + array(
        'tree' => $widget_root(array(
            $mail_heading(lang('A new maker application')),
            $mail_para(lang('It came from the application form. Look at the work, then set the status on the application\'s page; what you write under it reaches the maker by e-mail. An approved maker is added with New Maker.')),
            $mail_application(),
            $mail_sender(array(
                $mail_line(lang('Website or social page'), array($span('example.com', '', lang('Website'), $bind('website')))),
                $mail_line(lang('Your work'), array($span($sample_work, '', lang('Text'), $bind('description')))),
            )),
            $mail_button(lang('Open the application'), '{{page:application_view}}?r=^^reference_code^^'),
            $mail_reference(),
        )),
    ),
    'email_application_client' => $request_detail_config('email_application_received', 'apply', lang('The application could not be found.'), 'public') + array(
        'tree' => $widget_root(array(
            $mail_heading(lang('We received your application')),
            $mail_greeting(),
            $mail_para(lang('Thank you for telling us about your work. We look at every application and answer within two weeks; we may ask for a sample piece.')),
            $mail_application(),
            $mail_para(lang('More photos or a question? Write to us on the application\'s page; our answer comes there too.')),
            $mail_button(lang('Open my application'), '{{page:application_view}}?r=^^reference_code^^'),
            $mail_new_account(),
        )),
    ),
    'email_help_admin' => $request_detail_config('email_help_new', 'help', lang('The help request could not be found.'), 'public') + array(
        'tree' => $widget_root(array(
            $mail_heading(lang('A new help request')),
            $mail_para(lang('It came from the help form. Look at the order, ask the maker where needed, then set the status on the request\'s page; what you write under it reaches the customer by e-mail.')),
            $mail_help(),
            $mail_sender(array(
                $mail_line(lang('What happened?'), array($span($sample_help, '', lang('Text'), $bind('message')))),
            )),
            $mail_button(lang('Open the request'), '{{page:help_view}}?r=^^reference_code^^'),
            $mail_reference(),
        )),
    ),
    'email_help_client' => $request_detail_config('email_help_received', 'help', lang('The help request could not be found.'), 'public') + array(
        'tree' => $widget_root(array(
            $mail_heading(lang('We received your help request')),
            $mail_greeting(),
            $mail_para(lang('We are sorry something went wrong. We look at your order and answer within a working day.')),
            $mail_help(),
            $mail_para(lang('Anything to add, or another photo? Write to us on the request\'s page; everything about it stays there.')),
            $mail_button(lang('Open my request'), '{{page:help_view}}?r=^^reference_code^^'),
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
