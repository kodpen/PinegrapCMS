<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Florist", its order requests: the bouquet order
 * request, the request's own page with its messages, My Requests and the
 * staff's list; the privacy page the consent boxes link, the e-mails of
 * the order form and the new message e-mail every thread of the site
 * sends, and the widgets that draw them. Included after parts.php and
 * mail.php in the same scope; returns array('pages' => ..., 'widgets' => ...).
 *
 * An order request is a request the florist confirms, never a sale: a
 * record of the Flower Orders form. A sender without an account gets one
 * (auto-registration), so the request's page and My Requests show it at
 * once. Its Status is an office-use-only field the florist changes in the
 * control panel; the price, a question about the flowers and the delivery
 * are written under it as messages (the page's comments), mailed to the
 * sender and to the florist.
 *
 * The bouquet and the occasion start with what a link sends (?bouquet=,
 * ?occasion=: their titles, as parts.php writes them).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

$order_pages = array();

// The sample request the canvas shows. Its date is words, never a date: a
// fixed day would read as long past a year later.
$sample_bouquet = $bouquets['blush']['title'];
$sample_occasion = $occasions['love']['title'];
$sample_size = $sizes['regular']['title'];
$sample_date = lang('Delivery date');
$sample_slot = $slots['afternoon']['title'];
$sample_zone = $zones['centre']['title'];
$sample_recipient = lang('Elif Demir');
$sample_address = $florist['address'];
$sample_card = lang('Happy anniversary, with all my love.');
$sample_notes = lang('Please call before you come; the bell does not work.');

// The answers of the size buttons: the size, then what it adds.
$size_options = array();
foreach ($sizes as $s) $size_options[] = array($s['title'], $s['title'] . ' · ' . $s['text']);

// ── Page shells ─────────────────────────────────────────────────────────

// The side column of the order request: what happens next, the delivery
// day and areas, and the shop's phone for a same-day order.
$order_aside = function () use ($el, $heading, $para, $link, $check_list, $cutoff_text, $florist) {
    $steps = array(
        lang('Your request opens its own page, and an account for you if you have none.'),
        lang('We confirm the bouquet, the price and the delivery there, usually within the hour.'),
        lang('You pay once we have confirmed; nothing is charged for a request.'),
        lang('We arrange the flowers on the morning they leave and tell you when they are delivered.'),
    );
    $items = array();
    foreach ($steps as $n => $text) {
        $items[] = $el('li', $n < count($steps) - 1 ? 'mb-2' : '', lang('Step'), array(), array('text' => $text));
    }
    return array(
        $el('div', 'p-4 rounded-4 bg-body-tertiary mb-4', lang('How It Works'), array(
            $heading('h2', lang('What happens next'), 'h5 fw-semibold mb-3'),
            $el('ol', 'small text-body-secondary ps-3 mb-0', lang('List'), $items),
        )),
        $el('div', 'p-4 rounded-4 border mb-4', lang('Good to know'), array(
            $heading('h2', lang('Good to know'), 'h5 fw-semibold mb-3'),
            $check_list(array(
                $cutoff_text() . '.',
                $florist['promise'],
                lang('Leave your name off the card for a surprise; we never tell.'),
            ), 'list-unstyled small mb-3'),
            $link(lang('Delivery areas and fees'), '{{page:delivery}}', 'fw-semibold link-primary text-decoration-none small'),
        )),
        $el('div', 'p-4 rounded-4 border', lang('In a Hurry'), array(
            $heading('h2', lang('Flowers for today?'), 'h5 fw-semibold mb-2'),
            $para(lang('After the cut-off, call us: we often still find a way.'), 'small text-body-secondary mb-3'),
            $link($florist['phone'], 'tel:' . str_replace(' ', '', $florist['phone']), 'btn btn-sm btn-outline-primary rounded-pill px-3'),
        )),
    );
};

// ── Pages ───────────────────────────────────────────────────────────────

// The order request. What is sent opens the request's own page.
$order_pages[] = array(
    'key'              => 'order_request',
    'name'             => lang('order-flowers'),
    'folder'           => 'public',
    'title'            => lang('Order Flowers'),
    'meta_description' => lang('Choose a bouquet, the day and the time, and write the card: we confirm the price and the delivery, usually within the hour.'),
    'form'             => array(
        'form_name'            => lang('Flower Orders'),
        'confirmation_message' => lang('Thank you, your order request has reached us. We confirm it shortly.'),
        'confirmation_page_id' => '{{tab:request_view}}',
        'auto_registration'    => 1,
        'notify_email'         => '{{site_email}}',
        'notify_subject'       => lang('New order request: ^^bouquet^^ for ^^recipient_name^^'),
        'notify_page_id'       => '{{tab:email_request_new}}',
        'confirm_email'        => 1,
        'confirm_subject'      => lang('We have your order request'),
        'confirm_page_id'      => '{{tab:email_request_received}}',
    ),
    'tree'             => $page(array(
        $page_header(lang('Order Flowers'),
            lang('Choose the bouquet, the day and the time, and write the card. We confirm the price and the delivery, usually within the hour; nothing is paid now.')),
        $el('section', 'py-5', lang('Content'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $el('div', 'card border-0 shadow-sm rounded-4', lang('Form Card'), array(
                            $el('div', 'card-body p-4 p-lg-5', lang('Card Body'), array($widget('request_form'))),
                        )),
                    ), '', '8'),
                    $col($order_aside(), '', '4'),
                ), '4', '', 'g-lg-5'),
            )),
        )),
    ), false),
);

// One request: the request, its status and the messages under it.
$order_pages[] = array(
    'key'      => 'request_view',
    'name'     => lang('order-request'),
    'folder'   => 'registration',
    'title'    => lang('Order Request'),
    'search'   => false,
    'sitemap'  => false,
    'comments' => array('label' => lang('Message'), 'allow_new' => true, 'auto_publish' => true, 'show_date' => true, 'login' => true,
                        'attachments' => true,
                        'email_page' => '{{tab:email_reply}}', 'email_subject' => lang('There is a new message about your order request'),
                        'notify_email' => '{{site_email}}'),
    'tree'     => $page(array(
        $widget('request_detail'),
        $comments_section(lang('Messages'), 'request_messages'),
    ), false),
);

// My Requests: the sender's own order requests, event enquiries and
// subscriptions (for the staff, everybody's); enquiries.php owns the last
// two lists.
$order_pages[] = array(
    'key'     => 'my_requests',
    'name'    => lang('my-requests'),
    'folder'  => 'registration',
    'title'   => lang('My Requests'),
    'search'  => false,
    'sitemap' => false,
    'tree'    => $page(array(
        $page_header(lang('My Requests'), lang('Your order requests, their status and our answers, newest first.')),
        $el('section', 'py-5', lang('Content'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $widget('my_requests_list'),
                        $heading('h2', lang('Event Enquiries'), 'h5 fw-semibold mt-5 mb-3'),
                        $widget('my_events_list'),
                        $heading('h2', lang('Subscriptions'), 'h5 fw-semibold mt-5 mb-3'),
                        $widget('my_subscriptions_list'),
                    ), '', '8'),
                    $col(array(
                        $el('div', 'p-4 rounded-4 bg-body-tertiary mb-4', lang('New Request'), array(
                            $el('p', 'd-flex align-items-center gap-2 fw-semibold mb-2', lang('Title'), array(
                                $icon('bi-flower1', 'text-primary'),
                                $span(lang('Flowers for someone else?'), '', lang('Text')),
                            )),
                            $para(lang('Send another order request; your details are filled in for you.'), 'small text-body-secondary mb-3'),
                            $link(lang('Order Flowers'), '{{page:order_request}}', 'btn btn-sm btn-primary rounded-pill px-3'),
                        )),
                        $el('div', 'p-4 rounded-4 border', lang('Subscriptions'), array(
                            $para(lang('Flowers on the same day every week, fortnight or month: see the subscription plans.'), 'small text-body-secondary mb-3'),
                            $link(lang('Flower Subscriptions'), '{{page:subscriptions}}', 'btn btn-sm btn-outline-primary rounded-pill px-3'),
                        )),
                    ), '', '4'),
                ), '4', '', 'g-lg-5'),
            )),
        )),
    ), false),
);

// The staff's list of every order request, newest first, with a search,
// then the subscription requests (enquiries.php owns that list).
$order_pages[] = array(
    'key'     => 'staff_requests',
    'name'    => lang('all-flower-orders'),
    'folder'  => 'private',
    'title'   => lang('Flower Orders'),
    'search'  => false,
    'sitemap' => false,
    'tree'    => $staff_page(array(
        $page_header(lang('Flower Orders'), lang('Every order request and subscription request, newest first: confirm the price, set the status and write to the sender under each.')),
        $el('section', 'py-5', lang('Content'), array(
            $container(array(
                $widget('staff_requests_list'),
                $heading('h2', lang('Subscription Requests'), 'h4 fw-semibold mt-5 mb-3'),
                $widget('staff_subscriptions_list'),
            )),
        )),
    )),
);

// The privacy notice the consent boxes and the footer link to: sample text
// with a note to adapt it.
$order_pages[] = array(
    'key'              => 'privacy',
    'name'             => lang('privacy-policy'),
    'folder'           => 'public',
    'title'            => lang('Privacy Policy'),
    'meta_description' => lang('What the florist keeps about you and the people you send flowers to, why, and what you can ask of us.'),
    'tree'             => $page(array(
        $page_header(lang('Privacy Policy'), lang('What the florist keeps about you and the people you send flowers to, why, and what you can ask of us.')),
        $el('section', 'py-5', lang('Your Data'), array(
            $container(array($row(array($col(array(
                $el('div', 'alert alert-secondary d-flex gap-2 small', lang('Sample Text Note'), array(
                    $icon('bi-info-circle', 'mt-1'),
                    $span(lang('Sample text: adapt it to your company and to the data protection law that applies to you before you publish.'), '', lang('Text')),
                ), array('_attrs' => array($attr('role', 'note')))),
                $heading('h2', lang('Your data'), 'h4 fw-semibold mb-3'),
                $para(lang('When you send an order request, ask about event flowers, start a subscription, buy in the shop, join the newsletter or open an account, we keep your name and contact details and what you tell us, to answer you and deliver your flowers.'), 'mb-3'),
                $para(lang('For a delivery we also keep the recipient\'s name, phone and address and the card message, and use them only to bring the flowers. We pass to a courier only what the delivery needs, keep the rest as long as the law requires, never sell it and never give it to advertisers. You can see and change your account data from My Account, and ask us about the rest through the contact page.'), 'mb-0'),
            ), '', '8'))))),
        ), $searchable('fl-your-data', lang('privacy, personal data, KVKK, GDPR, delete my data'))),
    )),
);

// The e-mails: what the florist receives and the reply to the sender, and
// the new message e-mail.
$order_pages[] = array_merge($mail_settings, array(
    'key'   => 'email_request_new',
    'name'  => lang('email-new-order-request'),
    'title' => lang('New Order Request E-mail'),
    'tree'  => $mail_page(array($widget('email_request_admin'))),
));
$order_pages[] = array_merge($mail_settings, array(
    'key'   => 'email_request_received',
    'name'  => lang('email-order-request-received'),
    'title' => lang('Order Request Received E-mail'),
    'tree'  => $mail_page(array($widget('email_request_client'))),
));
// A new message or reply: sent to the sender of a request, of an enquiry
// and of a conversation, and to the visitors watching a page. Its Comments
// widget draws the comment in the card of mail.php; the System region
// after it keeps the fixed comment for an e-mail the widget does not draw.
$order_pages[] = array_merge($mail_settings, array(
    'key'   => 'email_reply',
    'name'  => lang('email-new-reply'),
    'title' => lang('New Reply E-mail'),
    'tree'  => $mail_page(array(
        $mail_heading(lang('There is a new message')),
        $mail_para(lang('Something new was written on a page you follow: your order request, an enquiry, a conversation or a Flower Journal post. You can read it below and answer it on the page.')),
        $widget('email_comment'),
        array('type' => 'region', 'props' => array('regionType' => 'system', 'regionName' => ''), 'children' => array()),
    )),
));

// ── Widget pieces ───────────────────────────────────────────────────────

// The sender's name.
$sender_name = function () use ($span, $bind) {
    return array(
        $span(lang('Ayşe'), '', lang('First Name'), $bind('first_name')),
        $span(' ', '', lang('Text')),
        $span(lang('Kaya'), '', lang('Last Name'), $bind('last_name')),
    );
};

// A request in a list: the bouquet, the status, the day and time, the
// recipient and the area, linked to its page. The staff's row starts with
// the sender's name and adds the recipient's phone; the sender's own adds
// the messages.
$request_row = function ($staff = false) use ($el, $span, $icon, $bind, $sender_name, $sample_bouquet, $sample_date, $sample_slot, $sample_recipient, $sample_zone, $request_statuses) {
    $item = function ($icon_name, $name, $children) use ($el, $icon) {
        return $el('span', 'd-inline-flex align-items-center gap-1', $name, array_merge(array($icon($icon_name)), $children));
    };
    $items = array(
        $item('bi-calendar-event', lang('Delivery'), array(
            $span($sample_date, 'me-1', lang('Delivery Date'), $bind('delivery_date')),
            $span($sample_slot, '', lang('Delivery Time'), $bind('delivery_time')),
        )),
        $item('bi-person-heart', lang('Recipient'), array($span($sample_recipient, '', lang('Recipient'), $bind('recipient_name')))),
        $item('bi-geo-alt', lang('Area'), array($span($sample_zone, '', lang('Area'), $bind('zone')))),
    );
    if ($staff) {
        $items[] = $item('bi-telephone', lang('Phone'), array($span('+90 555 000 00 00', '', lang('Phone'), $bind('recipient_phone'))));
        $title = $el('span', 'fw-semibold', lang('Sender'), array_merge($sender_name(), array(
            $span(' · ', 'text-body-secondary', lang('Text')),
            $span($sample_bouquet, 'text-body-secondary fw-normal', lang('Bouquet'), $bind('bouquet')),
        )));
    } else {
        $items[] = $item('bi-chat-left-text', lang('Messages'), array($span('0', '', lang('Reply Count'), $bind('number_of_comments'))));
        $title = $span($sample_bouquet, 'fw-semibold', lang('Bouquet'), $bind('bouquet'));
    }
    return $el('a', 'list-group-item list-group-item-action p-3', lang('Order Request'), array(
        $el('div', 'd-flex flex-wrap align-items-center justify-content-between gap-2 mb-1', lang('Top Line'), array(
            $title,
            $span($request_statuses[0], 'badge rounded-pill bg-primary-subtle text-primary-emphasis', lang('Status'), $bind('status')),
        )),
        $el('div', 'd-flex flex-wrap column-gap-3 row-gap-1 small text-body-secondary', lang('Details'), $items),
    ), array('href' => '#', '_bindings' => array('href' => 'form_item_view')));
};

// A detail of the record's card: the label, then the value.
$detail = function ($label, $class, $name, $value) use ($el) {
    return array(
        $el('dt', 'col-sm-4 fw-normal text-body-secondary', lang('Label'), array(), array('text' => $label)),
        $el('dd', trim('col-sm-8 ' . $class), $name, array(), $value),
    );
};
$bound = function ($sample, $field) use ($bind) {
    return array_merge(array('text' => $sample), $bind($field));
};

// The lines of a request, for its e-mails.
$mail_request = function () use ($mail_box, $mail_line, $span, $bind, $sample_bouquet, $sample_occasion, $sample_size, $sample_date, $sample_slot, $sample_zone, $sample_recipient, $sample_address, $sample_card) {
    return $mail_box(array(
        $mail_line(lang('Bouquet'), array($span($sample_bouquet, '', lang('Bouquet'), $bind('bouquet')))),
        $mail_line(lang('Special Occasion'), array($span($sample_occasion, '', lang('Special Occasion'), $bind('occasion')))),
        $mail_line(lang('Size'), array($span($sample_size, '', lang('Size'), $bind('size')))),
        $mail_line(lang('Delivery Date'), array($span($sample_date, '', lang('Delivery Date'), $bind('delivery_date')))),
        $mail_line(lang('Delivery Time'), array($span($sample_slot, '', lang('Delivery Time'), $bind('delivery_time')))),
        $mail_line(lang('Area'), array($span($sample_zone, '', lang('Area'), $bind('zone')))),
        $mail_line(lang('Recipient'), array($span($sample_recipient, '', lang('Recipient'), $bind('recipient_name')))),
        $mail_line(lang('Address'), array($span($sample_address, '', lang('Address'), $bind('address')))),
        $mail_line(lang('Card message'), array($span($sample_card, '', lang('Card message'), $bind('card_message')))),
    ), lang('Order Request'));
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
    ), array_merge($mail_style('border-top:1px solid #f1dfe2;padding-top:16px'), $show_when('has_new_account')));
};
// The sender's lines of the staff e-mail.
$mail_sender = function () use ($mail_box, $mail_line, $span, $bind, $sender_name, $sample_notes, $card_choices) {
    return $mail_box(array(
        $mail_line(lang('From'), $sender_name()),
        $mail_line(lang('Email'), array($span('ayse@example.com', '', lang('Email'), $bind('email')))),
        $mail_line(lang('Phone'), array($span('+90 555 000 00 00', '', lang('Phone'), $bind('phone')))),
        $mail_line(lang('Recipient\'s phone'), array($span('+90 555 111 11 11', '', lang('Recipient\'s phone'), $bind('recipient_phone')))),
        $mail_line(lang('Card'), array($span($card_choices[0][1], '', lang('Card'), $bind('card')))),
        $mail_line(lang('Notes for us'), array($span($sample_notes, '', lang('Notes for us'), $bind('notes')))),
    ), lang('Sender'));
};
// The form detail settings of an e-mail page's widget (any reader: the
// e-mail is drawn for the submission just sent) and of the request page's
// widget (the sender's own, and the staff's).
$detail_widget = function ($page_key, $missing, $access) {
    return array(
        'page'   => $page_key,
        'slug'   => lang('form-detail'),
        'config' => array(
            'regionType'          => 'form_item_view',
            'custom_form_page_id' => '{{tab:order_request}}',
            'access_control'      => $access,
            'not_found_message'   => $missing,
        ),
    );
};
// A list of requests: the sender's own (the staff see them all), or the
// staff's with a search.
$request_list = function ($page_key, $staff, $empty) use ($widget_root, $el, $loop, $request_row) {
    $config = array(
        'regionType'          => 'form_list_view',
        'custom_form_page_id' => '{{tab:order_request}}',
        'detail_page_id'      => '{{tab:request_view}}',
        'items_per_page'      => 20,
        'order_by_field'      => 'submitted_date_and_time',
        'order_by_direction'  => 'DESC',
    );
    if ($staff) {
        $config += array(
            'search_enabled' => true,
            'search_fields'  => array('first_name', 'last_name', 'email', 'recipient_name', 'bouquet', 'occasion', 'zone', 'status'),
            'search_width'   => 'half',
            'search_label'   => lang('Search by name, e-mail, recipient, bouquet, occasion, area or status...'),
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
            $el('div', 'list-group', lang('Request List'), array($loop(array($request_row($staff))))),
        )),
    );
};

$request_missing = lang('This order request could not be found, or it is not yours to see.');

$order_widgets = array(

    // The order request: the fields are the page's form. The bouquet and
    // the occasion start with what a link sends (?bouquet=, ?occasion=).
    // The delivery date is never in the past. The Status is the florist's
    // (office use only): every request starts as Request received.
    'request_form' => array(
        'page'   => 'order_request',
        'slug'   => 'form',
        'config' => array('regionType' => 'custom_form', 'form_source' => 'page'),
        'tree'   => $widget_root(array(
            $form_step(1, lang('The flowers')),
            $row(array(
                $col(array($field('fl-ord-bouquet', lang('Bouquet'), $select('fl-ord-bouquet', 'bouquet', '', $bouquet_choices, false, end($bouquet_choices), array('url_param' => 'bouquet')))), '6'),
                $col(array($field('fl-ord-occasion', lang('Special Occasion'), $select('fl-ord-occasion', 'occasion', '', $occasion_choices, false, end($occasion_choices), array('url_param' => 'occasion')))), '6'),
            ), '3'),
            $radios('fl-ord-size', 'size', lang('Size'), $size_options, $size_options[0][0]),
            $form_step(2, lang('The delivery')),
            $row(array(
                $col(array($field('fl-ord-date', lang('Delivery Date'), $input('fl-ord-date', 'date', 'delivery_date', 'off', true, array('rules' => array('min_date' => 'today'))), 'mb-3',
                    $cutoff_text() . '.')), '6'),
                $col(array($field('fl-ord-time', lang('Delivery Time'), $select('fl-ord-time', 'delivery_time', '', $slot_titles, true, $slot_titles[0]))), '6'),
                $col(array($field('fl-ord-zone', lang('Area'), $select('fl-ord-zone', 'zone', '', $zone_titles, true, $zone_titles[0]))), '6'),
                $col(array($field('fl-ord-recipient', lang('Recipient\'s name'), $input('fl-ord-recipient', 'text', 'recipient_name', 'off', true))), '6'),
                $col(array($field('fl-ord-recipient-phone', lang('Recipient\'s phone'), $input('fl-ord-recipient-phone', 'tel', 'recipient_phone', 'off', true), 'mb-3',
                    lang('Only for the courier, if nobody opens the door.'))), '6'),
            ), '3'),
            $field('fl-ord-address', lang('Address'), $textarea('fl-ord-address', 'address', 2, true), 'mb-3',
                lang('Street, number, floor and flat; a landmark helps.')),
            $form_step(3, lang('The card')),
            $radios('fl-ord-card', 'card', lang('Card'), $card_choices, $card_choices[0][0]),
            $field('fl-ord-message', lang('Card message'), $textarea('fl-ord-message', 'card_message', 3, false), 'mb-3',
                lang('We write it by hand. Sign it as you like, or leave your name off for a surprise.')),
            $field('fl-ord-notes', lang('Notes for us'), $textarea('fl-ord-notes', 'notes', 2, false), 'mb-3',
                lang('Colours they love, an allergy, the best time to ring the bell.')),
            $form_step(4, lang('How to reach you')),
            $row(array(
                $col(array($field('fl-ord-first-name', lang('First Name'), $input('fl-ord-first-name', 'text', 'first_name', 'given-name', true, array('contact_field' => 'first_name')))), '6'),
                $col(array($field('fl-ord-last-name', lang('Last Name'), $input('fl-ord-last-name', 'text', 'last_name', 'family-name', true, array('contact_field' => 'last_name')))), '6'),
                $col(array($field('fl-ord-email', lang('Email'), $input('fl-ord-email', 'email', 'email', 'email', true, array('contact_field' => 'email_address')))), '6'),
                $col(array($field('fl-ord-phone', lang('Phone'), $input('fl-ord-phone', 'tel', 'phone', 'tel', true, array('contact_field' => 'mobile_phone')))), '6'),
            ), '3'),
            $field('fl-ord-status', lang('Status'), $select('fl-ord-status', 'status', '', $request_statuses, false, $request_statuses[0], array('office_use_only' => true))),
            $agree('fl-ord-consent', 'consent', lang('I agree that the florist keeps these details and the recipient\'s to confirm and deliver my order, as its privacy notice explains.'), true),
            $captcha(),
            $el('div', 'd-flex flex-wrap align-items-center gap-3', lang('Send Area'), array(
                $submit(lang('Send the Request'), 'btn btn-primary btn-lg rounded-pill px-4'),
                $para(lang('We confirm the price before anything is paid.'), 'small text-body-secondary mb-0'),
            )),
            $loop(array()),
        )),
    ),

    // One request: the flowers, the delivery, the card and its status;
    // the florist's answers (the price, a question, the delivery) follow
    // as comments.
    'request_detail' => $detail_widget('request_view', $request_missing, 'submitter_only') + array(
        'tree' => array('type' => 'root', 'props' => array(), 'children' => array(
            $container(array(
                $row(array(
                    $col(array(
                        $el('div', 'text-center py-5', lang('Not Found'), array(
                            $icon('bi-envelope-x', 'display-5 text-body-tertiary'),
                            $heading('h2', lang('Order request not found'), 'h3 fw-semibold mt-3'),
                            $para($request_missing, 'text-body-secondary mb-4', $bind('not_found')),
                            $link(lang('My Requests'), '{{page:my_requests}}', 'btn btn-primary rounded-pill px-4'),
                        ), $show_when('record_not_found')),
                        $el('div', '', lang('Record'), array(
                            $el('div', 'alert alert-success d-flex gap-2', lang('Sent Notice'), array(
                                $icon('bi-check-circle', 'mt-1'),
                                $span(lang('Thank you, your order request has reached us. We confirm the price and the delivery here, usually within the hour; this page shows the status, and you can write to us below.'), '', lang('Text')),
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
                                    $span(lang('My Requests'), '', lang('Link Text')),
                                ), array('href' => '{{page:my_requests}}')),
                                $edit_button(lang('Change Status')),
                            )),
                            $el('div', 'card border-0 shadow-sm rounded-4 mb-4', lang('Request Card'), array(
                                $el('div', 'card-body p-4', lang('Card Body'), array(
                                    $el('div', 'd-flex flex-wrap align-items-start justify-content-between gap-2 mb-3', lang('Title'), array(
                                        $el('div', '', lang('Words'), array(
                                            $para(lang('Order Request'), 'pfl-eyebrow small text-uppercase fw-semibold text-primary mb-1'),
                                            $el('h1', 'pfl-display h3 mb-0', lang('Bouquet'), array(), $bound($sample_bouquet, 'bouquet')),
                                        )),
                                        $span($request_statuses[0], 'badge rounded-pill bg-primary-subtle text-primary-emphasis fs-6', lang('Status'), $bind('status')),
                                    )),
                                    $el('dl', 'row small mb-0', lang('Details'), array_merge(
                                        $detail(lang('Special Occasion'), '', lang('Special Occasion'), $bound($sample_occasion, 'occasion')),
                                        $detail(lang('Size'), '', lang('Size'), $bound($sample_size, 'size')),
                                        $detail(lang('Delivery Date'), '', lang('Delivery Date'), $bound($sample_date, 'delivery_date')),
                                        $detail(lang('Delivery Time'), '', lang('Delivery Time'), $bound($sample_slot, 'delivery_time')),
                                        $detail(lang('Area'), '', lang('Area'), $bound($sample_zone, 'zone')),
                                        $detail(lang('Recipient'), '', lang('Recipient'), $bound($sample_recipient, 'recipient_name')),
                                        $detail(lang('Recipient\'s phone'), '', lang('Recipient\'s phone'), $bound('+90 555 111 11 11', 'recipient_phone')),
                                        $detail(lang('Address'), '', lang('Address'), $bound($sample_address, 'address')),
                                        $detail(lang('Card'), '', lang('Card'), $bound($card_choices[0][1], 'card')),
                                        $detail(lang('Reference Code'), 'mb-0 font-monospace', lang('Reference Code'), $bound('ABC123', 'reference_code'))
                                    )),
                                )),
                            )),
                            $el('div', 'pg-hide-if-empty p-4 rounded-4 bg-body-tertiary mb-4', lang('Card message'), array(
                                $para(lang('Card message'), 'small fw-semibold text-body-secondary mb-2'),
                                $el('div', 'pfl-quote pg-field-value lh-lg', lang('Value'), array(), $bound($sample_card, 'card_message')),
                            )),
                            $el('div', 'pg-hide-if-empty p-4 rounded-4 border mb-4', lang('Notes for us'), array(
                                $para(lang('Notes for us'), 'small fw-semibold text-body-secondary mb-2'),
                                $el('div', 'pg-field-value', lang('Value'), array(), $bound($sample_notes, 'notes')),
                            )),
                            $el('div', 'd-flex flex-wrap gap-2 small text-body-secondary', lang('Note'), array(
                                $icon('bi-chat-left-text', 'text-primary mt-1'),
                                $span(lang('Another day, a different card or a question about the flowers? Write to us below; we answer here and by e-mail.'), '', lang('Text')),
                                $link(lang('Order Flowers'), '{{page:order_request}}', 'fw-semibold link-primary text-decoration-none'),
                            )),
                        ), $show_when('record_found')),
                    ), '', '8'),
                ), '', 'center'),
            ), 'py-5'),
        )),
    ),

    // The messages under a request: the page's own Comments widget, shown
    // only to whoever may read the request above it. Oldest first, all on
    // one page. No Reply, Share or follow box: the sender is e-mailed every
    // answer and the florist every message.
    'request_messages' => array(
        'page'   => 'request_view',
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
            'empty'     => lang('No messages yet. Our answer comes here; you can write to us about your order too.'),
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
    ),

    'my_requests_list' => $request_list('my_requests', false,
        lang('No order requests yet. Choose a bouquet and we confirm it shortly.')),

    'staff_requests_list' => $request_list('staff_requests', true, lang('No order requests yet.')),

    // The e-mail pages' widgets: drawn for the submission just sent.
    'email_request_admin' => $detail_widget('email_request_new', lang('This order request was not found.'), 'public') + array(
        'tree' => $widget_root(array(
            $mail_heading(lang('A new order request')),
            $mail_para(lang('It came from the order form. Confirm the price and the delivery under the request on its page, and set the status; what you write there reaches the sender by e-mail.')),
            $mail_request(),
            $mail_sender(),
            $mail_button(lang('Open the request'), '{{page:request_view}}?r=^^reference_code^^'),
            $mail_reference(),
        )),
    ),
    'email_request_client' => $detail_widget('email_request_received', lang('This order request was not found.'), 'public') + array(
        'tree' => $widget_root(array(
            $mail_heading(lang('We have your order request')),
            $mail_greeting(),
            $mail_para(lang('Thank you. We confirm the price and the delivery shortly, on the request\'s page and by e-mail. Nothing is paid now.')),
            $mail_request(),
            $mail_para(lang('Something to change, the card or the time? Write to us on the request\'s page.')),
            $mail_button(lang('Open my request'), '{{page:request_view}}?r=^^reference_code^^'),
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

return array('pages' => $order_pages, 'widgets' => $order_widgets);
