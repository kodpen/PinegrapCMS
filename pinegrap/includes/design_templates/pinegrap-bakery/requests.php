<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Bakery", its requests: the custom cake order, the
 * order's own page with its messages, My Cake Orders and the staff's list;
 * the class place request, its page, My Class Places and the staff's list;
 * the privacy page the consent boxes link; the e-mails of both forms and
 * the new message e-mail every thread of the site sends, and the widgets
 * that draw them. Included after parts.php, mail.php and classes.php in the
 * same scope; returns array('pages' => ..., 'widgets' => ...). The page and
 * list builders here ($bk_…) serve the wholesale enquiries too.
 *
 * A cake order is a request, never a sale: a record of the Cake Orders
 * form. The bakery answers with a design and a price as a message under
 * the order (the page's comments, mailed to the customer and to the
 * bakery) and sets the office-use-only Status. A class place is asked for
 * the same way: the template keeps no class capacity, so the bakery
 * confirms each request or says the class is full. A visitor without an
 * account gets one (auto-registration), so the record's page and the
 * visitor's list show it at once.
 *
 * The cake and the occasion start with what a link sends (?cake=,
 * ?occasion=: a cake card's words, as parts.php writes them), the class
 * with ?class= (the class page sends the record's name).
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

// The sample order and place request the canvas shows. A day is words,
// never a date: a fixed day would read as a past week a year later.
$sample_cake = $cakes['drip']['name'];
$sample_occasion = $occasions[$cakes['drip']['occasion']];
$sample_size = $size_choices[1];
$sample_pickup_day = lang('A Saturday, two weeks ahead');
$sample_cake_words = lang('Happy 7th Birthday, Ada');
$sample_cake_notes = lang('A drip cake in pink and gold for my daughter\'s party, with a few raspberries on top. About sixteen children and their parents.');
$sample_allergies = lang('One child cannot eat nuts.');
$sample_address = lang('Linden Street 8, Old Town');
$sample_class = $baking_classes['sourdough']['name'];
$sample_class_day = lang('A Saturday morning next month');
$sample_class_notes = lang('A gift for my mother\'s birthday: the place is in her name, and I would like the card to give with it.');
$not_before_today = array('rules' => array('min_date' => 'today'));
$place_choices = array('1', '2', '3', '4');

// ── Page builders ───────────────────────────────────────────────────────

// The side column of a form page: what happens next, then a second card
// (its title, its lines and a link).
$bk_request_aside = function ($steps, $box_title, $box_lines, $link_text, $link_href) use ($el, $heading, $link, $check_list) {
    $items = array();
    foreach ($steps as $n => $text) {
        $items[] = $el('li', $n < count($steps) - 1 ? 'mb-2' : '', lang('Step'), array(), array('text' => $text));
    }
    return array(
        $el('div', 'p-4 rounded-4 bg-primary-subtle mb-4', lang('How It Works'), array(
            $heading('h2', lang('What happens next'), 'pbk-display h5 mb-3'),
            $el('ol', 'small ps-3 mb-0', lang('List'), $items),
        )),
        $el('div', 'p-4 rounded-4 border', $box_title, array(
            $heading('h2', $box_title, 'pbk-display h5 mb-3'),
            $check_list($box_lines, 'list-unstyled small mb-3'),
            $link($link_text, $link_href, 'fw-semibold link-primary text-decoration-none small'),
        )),
    );
};

// A form page: the header, the form in a card and the side column.
$bk_request_page = function ($title, $lead, $widget_key, $aside) use ($page, $page_header, $el, $container, $row, $col, $widget) {
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

// A record's own page: the record and the messages under it, which reach
// the customer and the bakery by e-mail.
$bk_request_view = function ($key, $name, $title, $detail_key, $messages_key, $subject) use ($page, $widget, $comments_section) {
    return array(
        'key'      => $key,
        'name'     => $name,
        'folder'   => 'registration',
        'title'    => $title,
        'search'   => false,
        'sitemap'  => false,
        'comments' => array('label' => lang('Message'), 'allow_new' => true, 'auto_publish' => true, 'show_date' => true, 'login' => true,
                            'attachments' => true,
                            'email_page' => '{{tab:email_reply}}', 'email_subject' => $subject,
                            'notify_email' => '{{site_email}}'),
        'tree'     => $page(array(
            $widget($detail_key),
            $comments_section(lang('Messages'), $messages_key),
        ), false),
    );
};

// The visitor's own list page, with the way to send another.
$bk_my_page = function ($key, $name, $title, $lead, $widget_key, $more_title, $more_text, $more_link, $more_key) use ($page, $page_header, $el, $container, $row, $col, $widget, $icon, $span, $para, $link) {
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
                            $el('div', 'p-4 rounded-4 bg-primary-subtle', lang('New Request'), array(
                                $el('p', 'd-flex align-items-center gap-2 fw-semibold mb-2', lang('Title'), array(
                                    $icon('bi-plus-circle', 'text-primary'),
                                    $span($more_title, '', lang('Text')),
                                )),
                                $para($more_text, 'small mb-3'),
                                $link($more_link, '{{page:' . $more_key . '}}', 'btn btn-sm btn-primary rounded-pill px-3'),
                            )),
                        ), '', '4'),
                    ), '4', '', 'g-lg-5'),
                )),
            )),
        ), false),
    );
};

// The staff's list page.
$bk_staff_list_page = function ($key, $name, $title, $lead, $widget_key) use ($staff_page, $page_header, $el, $container, $widget) {
    return array(
        'key'     => $key,
        'name'    => $name,
        'folder'  => 'private',
        'title'   => $title,
        'search'  => false,
        'sitemap' => false,
        'tree'    => $staff_page(array(
            $page_header($title, $lead),
            $el('section', 'py-5', lang('Content'), array(
                $container(array($widget($widget_key))),
            )),
        )),
    );
};

// ── Widget builders ─────────────────────────────────────────────────────

// The sender's name.
$bk_sender_name = function () use ($span, $bind) {
    return array(
        $span(lang('Jane'), '', lang('First Name'), $bind('first_name')),
        $span(' ', '', lang('Text')),
        $span(lang('Cooper'), '', lang('Last Name'), $bind('last_name')),
    );
};

// A detail of the record's card: the label, then the value. $value is
// either the value's props (its sample text and binding) or, with
// $children, the nodes it is made of.
$bk_detail = function ($label, $value, $children = false, $class = '') use ($el) {
    return array(
        $el('dt', 'col-sm-4 fw-normal text-body-secondary', lang('Label'), array(), array('text' => $label)),
        $el('dd', trim('col-sm-8 ' . $class), $label, $children ? $value : array(), $children ? array() : $value),
    );
};
$bk_bound = function ($sample, $field) use ($bind) {
    return array_merge(array('text' => $sample), $bind($field));
};

// A record in a list, linked to its page: its title and status, then its
// details (each array(icon, label, sample, field)). The staff's row starts
// with the sender's name and adds the phone; the visitor's own adds the
// messages.
$bk_record_row = function ($staff, $title_sample, $title_field, $title_name, $status, $items) use ($el, $span, $icon, $bind, $bk_sender_name) {
    $item = function ($icon_name, $label, $sample, $field) use ($el, $icon, $span, $bind) {
        return $el('span', 'd-inline-flex align-items-center gap-1', $label, array(
            $icon($icon_name),
            $span($sample, '', $label, $bind($field)),
        ));
    };
    $children = array();
    foreach ($items as $i) $children[] = $item($i[0], $i[1], $i[2], $i[3]);
    if ($staff) {
        $children[] = $item('bi-telephone', lang('Phone'), '+90 555 000 00 00', 'phone');
        $title = $el('span', 'fw-semibold', lang('Sender'), array_merge($bk_sender_name(), array(
            $span(' · ', 'text-body-secondary', lang('Text')),
            $span($title_sample, 'text-body-secondary fw-normal', $title_name, $bind($title_field)),
        )));
    } else {
        $children[] = $item('bi-chat-left-text', lang('Messages'), '0', 'number_of_comments');
        $title = $span($title_sample, 'fw-semibold', $title_name, $bind($title_field));
    }
    return $el('a', 'list-group-item list-group-item-action p-3', lang('Request'), array(
        $el('div', 'd-flex flex-wrap align-items-center justify-content-between gap-2 mb-1', lang('Top Line'), array(
            $title,
            $span($status, 'badge rounded-pill bg-primary-subtle text-primary-emphasis', lang('Status'), $bind('status')),
        )),
        $el('div', 'd-flex flex-wrap column-gap-3 row-gap-1 small text-body-secondary', lang('Details'), $children),
    ), array('href' => '#', '_bindings' => array('href' => 'form_item_view')));
};

// The form detail settings of an e-mail page's widget (any reader: the
// e-mail is drawn for the submission just sent) and of a record page's
// widget (the sender's own, and the staff's).
$bk_detail_widget = function ($page_key, $form_key, $missing, $access) {
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

// A list of records, newest first: the visitor's own (the staff see them
// all), or the staff's with a search.
$bk_record_list = function ($page_key, $form_key, $view_key, $item, $search_fields, $search_label, $empty) use ($widget_root, $el, $loop) {
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
            $el('div', 'list-group', lang('Request List'), array($loop(array($item)))),
        )),
    );
};

// A record's page: not found, or the notes of a record just sent (thank
// you, and the account the form opened), the toolbar, the card with its
// details, the sender's own words and the note about messages. $r: icon,
// missing_title, missing, back, back_key, thanks, eyebrow, title,
// title_field, status, details, words_label, words, words_field, note.
$bk_record_tree = function ($r) use ($widget_root, $container, $row, $col, $el, $icon, $heading, $para, $link, $span, $bind, $show_when, $attr, $edit_button, $bk_bound) {
    return $widget_root(array(
        $container(array(
            $row(array(
                $col(array(
                    $el('div', 'text-center py-5', lang('Not Found'), array(
                        $icon($r['icon'], 'display-5 text-body-tertiary'),
                        $heading('h2', $r['missing_title'], 'h3 fw-semibold mt-3'),
                        $para($r['missing'], 'text-body-secondary mb-4', $bind('not_found')),
                        $link($r['back'], '{{page:' . $r['back_key'] . '}}', 'btn btn-primary rounded-pill px-4'),
                    ), $show_when('record_not_found')),
                    $el('div', '', lang('Record'), array(
                        $el('div', 'alert alert-success d-flex gap-2', lang('Sent Notice'), array(
                            $icon('bi-check-circle', 'mt-1'),
                            $span($r['thanks'], '', lang('Text')),
                        ), array_merge(array('_attrs' => array($attr('role', 'status'))), $show_when('submitted_this_session'))),
                        $el('div', 'alert alert-info', lang('New Account'), array(
                            $el('p', 'fw-semibold mb-2', lang('Title'), array(), array('text' => lang('We opened an account for you'))),
                            $el('p', 'mb-1', lang('Sign-in Email'), array(
                                $span(lang('Email') . ': ', '', lang('Label')),
                                $span('sample@example.com', 'fw-semibold', lang('Value'), $bind('__new_account_email')),
                            )),
                            // The single-use set password link, for the person who sent the form
                            // while it is live; the sentence below covers a used or expired link.
                            $link(lang('Set your password'), '#', 'btn btn-sm btn-primary rounded-pill', array(),
                                array('_bindings' => array('href' => '__new_account_set_password_url', 'eo_visible_if' => 'has_set_password_link'))),
                            $para(lang('You can set a password with "Forgot password" on the sign-in page.'), 'small mt-2 mb-0'),
                        ), $show_when('has_new_account')),
                        $el('div', 'd-flex flex-wrap align-items-center justify-content-between gap-2 mb-3', lang('Toolbar'), array(
                            $el('a', 'd-inline-flex align-items-center gap-1 small fw-semibold link-primary text-decoration-none', lang('Link'), array(
                                $icon('bi-arrow-left'),
                                $span($r['back'], '', lang('Link Text')),
                            ), array('href' => '{{page:' . $r['back_key'] . '}}')),
                            $edit_button(lang('Change Status')),
                        )),
                        $el('div', 'card border-0 shadow-sm rounded-4 mb-4', lang('Request Card'), array(
                            $el('div', 'card-body p-4', lang('Card Body'), array(
                                $el('div', 'd-flex flex-wrap align-items-start justify-content-between gap-2 mb-3', lang('Title'), array(
                                    $el('div', '', lang('Words'), array(
                                        $para($r['eyebrow'], 'pbk-eyebrow small text-uppercase fw-semibold text-primary mb-1'),
                                        $heading('h1', $r['title'], 'pbk-display h3 mb-0', $bind($r['title_field'])),
                                    )),
                                    $span($r['status'], 'badge rounded-pill bg-primary-subtle text-primary-emphasis fs-6', lang('Status'), $bind('status')),
                                )),
                                $el('dl', 'row small mb-0', lang('Details'), $r['details']),
                            )),
                        )),
                        $el('div', 'pg-hide-if-empty p-4 rounded-4 bg-body-tertiary mb-4', $r['words_label'], array(
                            $para($r['words_label'], 'small fw-semibold text-body-secondary mb-2'),
                            $el('div', 'pg-field-value lh-lg', lang('Value'), array(), $bk_bound($r['words'], $r['words_field'])),
                        )),
                        $el('div', 'd-flex gap-2 small text-body-secondary', lang('Note'), array(
                            $icon('bi-chat-left-text', 'text-primary mt-1'),
                            $span($r['note'], '', lang('Text')),
                        )),
                    ), $show_when('record_found')),
                ), '', '8'),
            ), '', 'center'),
        ), 'py-5'),
    ));
};

// The messages under a record: the page's own Comments widget, shown only
// to whoever may read the record above it. Oldest first, all on one page,
// with a file each (a sketch, a photo of the cake wished for). No Reply,
// Share or follow box: the customer is e-mailed every answer and the
// bakery every message.
$bk_messages_widget = function ($page_key, $empty) use ($comment_thread) {
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

// The contact fields every form ends with, and the way to answer.
$bk_contact_fields = function ($p) use ($row, $col, $field, $input, $radios, $contact_choices) {
    return array(
        $row(array(
            $col(array($field($p . '-first-name', lang('First Name'), $input($p . '-first-name', 'text', 'first_name', 'given-name', true, array('contact_field' => 'first_name')))), '6'),
            $col(array($field($p . '-last-name', lang('Last Name'), $input($p . '-last-name', 'text', 'last_name', 'family-name', true, array('contact_field' => 'last_name')))), '6'),
            $col(array($field($p . '-email', lang('Email'), $input($p . '-email', 'email', 'email', 'email', true, array('contact_field' => 'email_address')))), '6'),
            $col(array($field($p . '-phone', lang('Phone'), $input($p . '-phone', 'tel', 'phone', 'tel', true, array('contact_field' => 'mobile_phone')))), '6'),
        ), '3'),
        $radios($p . '-contact', 'contact_method', lang('Contact by'), $contact_choices, $contact_choices[0][0]),
    );
};

// The sender's lines of a staff e-mail, with what they wrote.
$bk_mail_sender = function ($words_label, $words, $words_field) use ($mail_box, $mail_line, $span, $bind, $bk_sender_name, $contact_choices) {
    return $mail_box(array(
        $mail_line(lang('Name'), $bk_sender_name()),
        $mail_line(lang('Email'), array($span('jane.cooper@example.com', '', lang('Email'), $bind('email')))),
        $mail_line(lang('Phone'), array($span('+90 555 000 00 00', '', lang('Phone'), $bind('phone')))),
        $mail_line(lang('Contact by'), array($span($contact_choices[0][1], '', lang('Contact by'), $bind('contact_method')))),
        $mail_line($words_label, array($span($words, '', $words_label, $bind($words_field)))),
    ), lang('Sender'));
};

// ── Cake orders: pieces ─────────────────────────────────────────────────

$cake_order_row = function ($staff = false) use ($bk_record_row, $sample_cake, $sample_occasion, $sample_size, $sample_pickup_day, $cake_statuses) {
    return $bk_record_row($staff, $sample_cake, 'cake', lang('Cake'), $cake_statuses[0], array(
        array('bi-balloon', lang('Occasion'), $sample_occasion, 'occasion'),
        array('bi-calendar-event', lang('Day you need it'), $sample_pickup_day, 'pickup_date'),
        array('bi-people', lang('Size'), $sample_size, 'size'),
    ));
};
$mail_cake_order = function () use ($mail_box, $mail_line, $span, $bind, $sample_cake, $sample_occasion, $sample_size, $sample_pickup_day, $sample_cake_words, $sample_address, $sample_allergies, $flavour_choices, $filling_choices, $fulfilment_choices) {
    $line = function ($label, $sample, $field) use ($mail_line, $span, $bind) {
        return $mail_line($label, array($span($sample, '', $label, $bind($field))));
    };
    return $mail_box(array(
        $line(lang('Occasion'), $sample_occasion, 'occasion'),
        $line(lang('Cake'), $sample_cake, 'cake'),
        $line(lang('Size'), $sample_size, 'size'),
        $line(lang('Sponge'), $flavour_choices[1], 'flavour'),
        $line(lang('Filling'), $filling_choices[2], 'filling'),
        $line(lang('Words on the cake'), $sample_cake_words, 'cake_words'),
        $line(lang('Day you need it'), $sample_pickup_day, 'pickup_date'),
        $line(lang('Collect or deliver'), $fulfilment_choices[0][1], 'fulfilment'),
        $line(lang('Delivery address'), $sample_address, 'address'),
        $line(lang('Allergies and diets'), $sample_allergies, 'allergies'),
    ), lang('Cake Order'));
};

// ── Class places: pieces ────────────────────────────────────────────────

$booking_row = function ($staff = false) use ($bk_record_row, $sample_class, $sample_class_day, $booking_statuses) {
    return $bk_record_row($staff, $sample_class, 'class', lang('Baking Class'), $booking_statuses[0], array(
        array('bi-calendar-event', lang('Wished day'), $sample_class_day, 'class_date'),
        array('bi-people', lang('Places'), '1', 'places'),
    ));
};
$mail_booking = function () use ($mail_box, $mail_line, $span, $bind, $sample_class, $sample_class_day) {
    return $mail_box(array(
        $mail_line(lang('Baking Class'), array($span($sample_class, '', lang('Baking Class'), $bind('class')))),
        $mail_line(lang('Wished day'), array($span($sample_class_day, '', lang('Wished day'), $bind('class_date')))),
        $mail_line(lang('Places'), array($span('1', '', lang('Places'), $bind('places')))),
    ), lang('Class Place Request'));
};

// ── Pages ───────────────────────────────────────────────────────────────

$opening_lines = array();
foreach ($opening_hours as $h) $opening_lines[] = $h['days'] . ': ' . $h['hours'];

// The cake order. What is sent opens the order's own page.
$request_pages[] = array(
    'key'              => 'cake_order',
    'name'             => lang('order-a-cake'),
    'folder'           => 'public',
    'title'            => lang('Order a Cake'),
    'meta_description' => lang('Order a celebration cake made for your day: tell us the occasion, the size and the flavours, and we answer with a design and a price.'),
    'form'             => array(
        'form_name'            => lang('Cake Orders'),
        'confirmation_message' => lang('Thank you, your cake order has reached the bakery. We answer with a design and a price within a day.'),
        'confirmation_page_id' => '{{tab:cake_order_view}}',
        'auto_registration'    => 1,
        'notify_email'         => '{{site_email}}',
        'notify_subject'       => lang('New cake order: ^^cake^^ (^^occasion^^)'),
        'notify_page_id'       => '{{tab:email_cake_order_new}}',
        'confirm_email'        => 1,
        'confirm_subject'      => lang('We received your cake order'),
        'confirm_page_id'      => '{{tab:email_cake_order_received}}',
    ),
    'tree'             => $bk_request_page(lang('Order a Cake'),
        lang('Tell us the occasion, how many it should serve and what you would like on it. We answer with a design and a price within a day; nothing is paid now.'),
        'cake_order_form',
        $bk_request_aside(array(
            lang('Your order opens its own page, and an account for you if you have none.'),
            lang('Within a day we send a design and a price there, and you receive it by e-mail.'),
            lang('Write back under your order to change anything; we confirm when you agree.'),
            lang('Collect the cake at the counter on your day, or we deliver it.'),
        ), lang('How far ahead?'), array($pickup['cakes'], $pickup['custom'], $pickup['window']),
            lang('See the celebration cakes'), '{{page:cakes}}')),
);

$request_pages[] = $bk_request_view('cake_order_view', lang('cake-order'), lang('Cake Order'),
    'cake_order_detail', 'cake_order_messages', lang('There is a new message about your cake order'));

$request_pages[] = $bk_my_page('my_cake_orders', lang('my-cake-orders'), lang('My Cake Orders'),
    lang('Your cake orders, their status and the bakery\'s designs and prices, newest first.'),
    'my_cake_orders_list', lang('Another celebration?'),
    lang('Order another cake; your details are filled in for you.'), lang('Order a Cake'), 'cake_order');

$request_pages[] = $bk_staff_list_page('staff_cake_orders', lang('all-cake-orders'), lang('Cake Orders'),
    lang('Every cake order, newest first: send the design and the price under each, then set the status.'),
    'staff_cake_orders_list');

// The class place request. What is sent opens the request's own page.
$request_pages[] = array(
    'key'              => 'class_booking',
    'name'             => lang('ask-for-a-class-place'),
    'folder'           => 'public',
    'title'            => lang('Ask for a Class Place'),
    'meta_description' => lang('Ask for a place in a baking class: choose the class and the day you would like, and the bakery confirms your place within a day.'),
    'form'             => array(
        'form_name'            => lang('Class Place Requests'),
        'confirmation_message' => lang('Thank you, your class place request has reached the bakery. We confirm your place within a day.'),
        'confirmation_page_id' => '{{tab:booking_view}}',
        'auto_registration'    => 1,
        'notify_email'         => '{{site_email}}',
        'notify_subject'       => lang('New class place request: ^^class^^'),
        'notify_page_id'       => '{{tab:email_booking_new}}',
        'confirm_email'        => 1,
        'confirm_subject'      => lang('We received your class place request'),
        'confirm_page_id'      => '{{tab:email_booking_received}}',
    ),
    'tree'             => $bk_request_page(lang('Ask for a Class Place'),
        lang('Choose the class and the day you would like. The bakery confirms your place within a day, or offers the nearest day with a free place.'),
        'class_booking_form',
        $bk_request_aside(array(
            lang('Your request opens its own page, and an account for you if you have none.'),
            lang('Within a day we confirm your place and the class day there, or offer another day if the class is full.'),
            lang('Come with an apron; flour, tools, recipes and coffee are at the bench.'),
            lang('Cannot come? Write to us on the request\'s page so the place goes to someone else.'),
        ), lang('Opening hours'), $opening_lines, lang('See the baking classes'), '{{page:classes}}')),
);

$request_pages[] = $bk_request_view('booking_view', lang('class-place-request'), lang('Class Place Request'),
    'booking_detail', 'booking_messages', lang('There is a new message about your class place request'));

$request_pages[] = $bk_my_page('my_bookings', lang('my-class-places'), lang('My Class Places'),
    lang('Your class place requests, their status and the bakery\'s answers, newest first.'),
    'my_bookings_list', lang('Another baking class?'),
    lang('Choose one from the baking classes; your details are filled in for you.'), lang('See the baking classes'), 'classes');

$request_pages[] = $bk_staff_list_page('staff_bookings', lang('all-class-place-requests'), lang('Class Place Requests'),
    lang('Every class place request, newest first: confirm the place and the day under each, then set the status.'),
    'staff_bookings_list');

// The privacy notice the consent boxes and the footer link to: sample text
// with a note to adapt it.
$request_pages[] = array(
    'key'              => 'privacy',
    'name'             => lang('privacy-policy'),
    'folder'           => 'public',
    'title'            => lang('Privacy Policy'),
    'meta_description' => lang('What the bakery keeps about you when you order a cake, ask for a class place or open an account, why, and what you can ask of us.'),
    'tree'             => $page(array(
        $page_header(lang('Privacy Policy'), lang('What the bakery keeps about you when you order a cake, ask for a class place or open an account, why, and what you can ask of us.')),
        $el('section', 'py-5', lang('Your Data'), array(
            $container(array($row(array($col(array(
                $el('div', 'alert alert-secondary d-flex gap-2 small', lang('Sample Text Note'), array(
                    $icon('bi-info-circle', 'mt-1'),
                    $span(lang('Sample text: adapt it to your company and to the data protection law that applies to you before you publish.'), '', lang('Text')),
                ), array('_attrs' => array($attr('role', 'note')))),
                $heading('h2', lang('Your data'), 'pbk-display h4 mb-3'),
                $para(lang('When you order a cake, ask for a class place, write to us about wholesale, join the newsletter or open an account, we keep your name, your contact details, what you ask for, the allergies you tell us about and the pictures you send, to answer you, bake your order and keep your place.'), 'mb-3'),
                $para(lang('We keep it as long as the law requires, never sell it and never give it to advertisers. You can see and change your account data from My Account, and ask us about the rest through the contact page.'), 'mb-0'),
            ), '', '8'))))),
        ), $searchable('bky-your-data', lang('privacy, personal data, KVKK, GDPR, delete my data'))),
    )),
);

// The e-mails: what the bakery receives and the reply to the sender of
// each form, and the new message e-mail.
$request_pages[] = array_merge($mail_settings, array(
    'key'   => 'email_cake_order_new',
    'name'  => lang('email-new-cake-order'),
    'title' => lang('New Cake Order E-mail'),
    'tree'  => $mail_page(array($widget('email_cake_order_admin'))),
));
$request_pages[] = array_merge($mail_settings, array(
    'key'   => 'email_cake_order_received',
    'name'  => lang('email-cake-order-received'),
    'title' => lang('Cake Order Received E-mail'),
    'tree'  => $mail_page(array($widget('email_cake_order_client'))),
));
$request_pages[] = array_merge($mail_settings, array(
    'key'   => 'email_booking_new',
    'name'  => lang('email-new-class-place-request'),
    'title' => lang('New Class Place Request E-mail'),
    'tree'  => $mail_page(array($widget('email_booking_admin'))),
));
$request_pages[] = array_merge($mail_settings, array(
    'key'   => 'email_booking_received',
    'name'  => lang('email-class-place-request-received'),
    'title' => lang('Class Place Request Received E-mail'),
    'tree'  => $mail_page(array($widget('email_booking_client'))),
));
// A new message or reply: sent to the sender of a cake order, a class place
// request or a wholesale enquiry, the sender of a conversation and the
// visitors watching a page. Its Comments widget draws the comment in the
// card of mail.php; the System region after it keeps the fixed comment for
// an e-mail the widget does not draw.
$request_pages[] = array_merge($mail_settings, array(
    'key'   => 'email_reply',
    'name'  => lang('email-new-reply'),
    'title' => lang('New Reply E-mail'),
    'tree'  => $mail_page(array(
        $mail_heading(lang('There is a new message')),
        $mail_para(lang('Something new was written on a page you follow: your cake order, your class place request, your wholesale enquiry, a conversation or a blog post. You can read it below and answer it on the page.')),
        $widget('email_comment'),
        array('type' => 'region', 'props' => array('regionType' => 'system', 'regionName' => ''), 'children' => array()),
    )),
));

// ── Widgets ─────────────────────────────────────────────────────────────

$cake_order_missing = lang('This cake order could not be found, or it is not yours to see.');
$booking_missing = lang('This class place request could not be found, or it is not yours to see.');

$request_widgets = array(

    // The cake order: the fields are the page's form. The occasion and the
    // cake start with what a link sends (?occasion=, ?cake=). The address
    // is asked for, and required, only for a delivery. The Status is the
    // bakery's (office use only): every order starts as Request received.
    'cake_order_form' => array(
        'page'   => 'cake_order',
        'slug'   => 'form',
        'config' => array('regionType' => 'custom_form', 'form_source' => 'page'),
        'tree'   => $widget_root(array_merge(array(
            $form_step(1, lang('Your cake')),
            $row(array(
                $col(array($field('bky-co-occasion', lang('Occasion'), $select('bky-co-occasion', 'occasion', lang('Choose an occasion…'), $occasion_choices, true, '', array('url_param' => 'occasion')))), '6'),
                $col(array($field('bky-co-cake', lang('Cake'), $select('bky-co-cake', 'cake', lang('Choose a cake…'), $cake_choices, true, '', array('url_param' => 'cake')))), '6'),
                $col(array($field('bky-co-size', lang('Size'), $select('bky-co-size', 'size', lang('Choose a size…'), $size_choices))), '12'),
                $col(array($field('bky-co-flavour', lang('Sponge'), $select('bky-co-flavour', 'flavour', lang('Choose a sponge…'), $flavour_choices))), '6'),
                $col(array($field('bky-co-filling', lang('Filling'), $select('bky-co-filling', 'filling', lang('Choose a filling…'), $filling_choices))), '6'),
            ), '3'),
            $field('bky-co-words', lang('Words on the cake'), $input('bky-co-words', 'text', 'cake_words', 'off', false), 'mb-3',
                lang('Written in icing; a name and a few words fit best.')),
            $field('bky-co-notes', lang('Your ideas'), $textarea('bky-co-notes', 'notes', 4, false), 'mb-3',
                lang('Colours, a theme, decorations, how many guests: whatever helps us design it.')),
            $field('bky-co-inspiration', lang('A picture or a sketch'), $file('bky-co-inspiration', 'inspiration', 'private'), 'mb-3',
                lang('A photo of a cake you like or a drawing of your own (a picture or a PDF).')),
            $field('bky-co-allergies', lang('Allergies and diets'), $textarea('bky-co-allergies', 'allergies', 2, false), 'mb-3',
                lang('Nuts, eggs, gluten or anything else we must leave out.')),
            $form_step(2, lang('Collect or deliver')),
            $field('bky-co-date', lang('Day you need it'), $input('bky-co-date', 'date', 'pickup_date', 'off', true, $not_before_today), 'mb-3',
                $pickup['custom']),
            $radios('bky-co-fulfilment', 'fulfilment', lang('Collect or deliver'), $fulfilment_choices, $fulfilment_choices[0][0]),
            $field('bky-co-address', lang('Delivery address'), $input('bky-co-address', 'text', 'address', 'street-address', false,
                array('rules' => array(
                    'required_when' => array('field' => 'fulfilment', 'answers' => array($fulfilment_choices[1][0])),
                    'shown_when'    => array('field' => 'fulfilment', 'answers' => array($fulfilment_choices[1][0])),
                )))),
            $form_step(3, lang('How to reach you')),
        ), $bk_contact_fields('bky-co'), array(
            $field('bky-co-status', lang('Status'), $select('bky-co-status', 'status', '', $cake_statuses, false, $cake_statuses[0], array('office_use_only' => true))),
            $agree('bky-co-consent', 'consent', lang('I agree that the bakery keeps these details, my allergies among them, to answer my order and bake the cake, as its privacy notice explains.'), true),
            $captcha(),
            $el('div', 'd-flex flex-wrap align-items-center gap-3', lang('Send Area'), array(
                $submit(lang('Send the Cake Order'), 'btn btn-primary btn-lg rounded-pill px-4'),
                $para(lang('We answer with a design and a price within a day. Nothing is paid now.'), 'small text-body-secondary mb-0'),
            )),
            $loop(array()),
        ))),
    ),

    // One cake order; the bakery's design, price and answers follow as
    // comments.
    'cake_order_detail' => $bk_detail_widget('cake_order_view', 'cake_order', $cake_order_missing, 'submitter_only') + array(
        'tree' => $bk_record_tree(array(
            'icon'          => 'bi-cake2',
            'missing_title' => lang('Cake order not found'),
            'missing'       => $cake_order_missing,
            'back'          => lang('My Cake Orders'),
            'back_key'      => 'my_cake_orders',
            'thanks'        => lang('Thank you, your cake order has reached the bakery. We send a design and a price here within a day; this page shows the status, and you can write to us below.'),
            'eyebrow'       => lang('Cake Order'),
            'title'         => $sample_cake,
            'title_field'   => 'cake',
            'status'        => $cake_statuses[0],
            'details'       => array_merge(
                $bk_detail(lang('Occasion'), $bk_bound($sample_occasion, 'occasion')),
                $bk_detail(lang('Size'), $bk_bound($sample_size, 'size')),
                $bk_detail(lang('Sponge'), $bk_bound($flavour_choices[1], 'flavour')),
                $bk_detail(lang('Filling'), $bk_bound($filling_choices[2], 'filling')),
                $bk_detail(lang('Words on the cake'), $bk_bound($sample_cake_words, 'cake_words')),
                $bk_detail(lang('Day you need it'), $bk_bound($sample_pickup_day, 'pickup_date')),
                $bk_detail(lang('Collect or deliver'), $bk_bound($fulfilment_choices[0][1], 'fulfilment')),
                $bk_detail(lang('Delivery address'), $bk_bound($sample_address, 'address')),
                $bk_detail(lang('Allergies and diets'), $bk_bound($sample_allergies, 'allergies')),
                $bk_detail(lang('A picture or a sketch'), array(
                    $link('cake-idea.jpg', '#', '', array(), array('target' => '_blank', 'rel' => 'noopener',
                        '_bindings' => array('href' => 'inspiration', 'text' => 'inspiration'))),
                ), true, 'text-break'),
                $bk_detail(lang('Name'), $bk_sender_name(), true),
                $bk_detail(lang('Reference Code'), $bk_bound('ABC123', 'reference_code'), false, 'mb-0 font-monospace')
            ),
            'words_label'   => lang('Your ideas'),
            'words'         => $sample_cake_notes,
            'words_field'   => 'notes',
            'note'          => lang('Another picture, a change or a question? Write to us below; we answer here and by e-mail.'),
        )),
    ),

    'cake_order_messages' => $bk_messages_widget('cake_order_view',
        lang('No messages yet. The bakery\'s design and price come here; you can write to us about your cake too.')),

    'my_cake_orders_list' => $bk_record_list('my_cake_orders', 'cake_order', 'cake_order_view', $cake_order_row(false), array(), '',
        lang('No cake orders yet. Tell us about the cake for your day, and follow the design and the price here.')),

    'staff_cake_orders_list' => $bk_record_list('staff_cake_orders', 'cake_order', 'cake_order_view', $cake_order_row(true),
        array('first_name', 'last_name', 'email', 'occasion', 'cake', 'status'),
        lang('Search by name, e-mail, occasion, cake or status...'), lang('No cake orders yet.')),

    // The class place request: the class starts with what the class page
    // sends (?class=). The Status is the bakery's.
    'class_booking_form' => array(
        'page'   => 'class_booking',
        'slug'   => 'form',
        'config' => array('regionType' => 'custom_form', 'form_source' => 'page'),
        'tree'   => $widget_root(array_merge(array(
            $form_step(1, lang('Your baking class')),
            $field('bky-cb-class', lang('Baking Class'), $select('bky-cb-class', 'class', lang('Choose a baking class…'), $class_names, true, '', array('url_param' => 'class'))),
            $row(array(
                $col(array($field('bky-cb-date', lang('Wished day'), $input('bky-cb-date', 'date', 'class_date', 'off', true, $not_before_today), 'mb-3',
                    lang('We confirm the class day nearest to it.'))), '8'),
                $col(array($field('bky-cb-places', lang('Places'), $select('bky-cb-places', 'places', '', $place_choices, true, $place_choices[0]))), '4'),
            ), '3'),
            $field('bky-cb-notes', lang('Notes'), $textarea('bky-cb-notes', 'notes', 3, false), 'mb-3',
                lang('A gift for someone else, an allergy, the age of a child for Little Bakers.')),
            $form_step(2, lang('How to reach you')),
        ), $bk_contact_fields('bky-cb'), array(
            $field('bky-cb-status', lang('Status'), $select('bky-cb-status', 'status', '', $booking_statuses, false, $booking_statuses[0], array('office_use_only' => true))),
            $agree('bky-cb-consent', 'consent', lang('I agree that the bakery keeps these details to answer my request and keep my place, as its privacy notice explains.'), true),
            $captcha(),
            $el('div', 'd-flex flex-wrap align-items-center gap-3', lang('Send Area'), array(
                $submit(lang('Ask for a Place'), 'btn btn-primary btn-lg rounded-pill px-4'),
                $para(lang('We confirm your place within a day. Nothing is paid now.'), 'small text-body-secondary mb-0'),
            )),
            $loop(array()),
        ))),
    ),

    'booking_detail' => $bk_detail_widget('booking_view', 'class_booking', $booking_missing, 'submitter_only') + array(
        'tree' => $bk_record_tree(array(
            'icon'          => 'bi-calendar-x',
            'missing_title' => lang('Class place request not found'),
            'missing'       => $booking_missing,
            'back'          => lang('My Class Places'),
            'back_key'      => 'my_bookings',
            'thanks'        => lang('Thank you, your request has reached the bakery. We confirm your place and the class day here within a day; this page shows the status, and you can write to us below.'),
            'eyebrow'       => lang('Class Place Request'),
            'title'         => $sample_class,
            'title_field'   => 'class',
            'status'        => $booking_statuses[0],
            'details'       => array_merge(
                $bk_detail(lang('Wished day'), $bk_bound($sample_class_day, 'class_date')),
                $bk_detail(lang('Places'), $bk_bound('1', 'places')),
                $bk_detail(lang('Name'), $bk_sender_name(), true),
                $bk_detail(lang('Reference Code'), $bk_bound('ABC123', 'reference_code'), false, 'mb-0 font-monospace')
            ),
            'words_label'   => lang('Notes'),
            'words'         => $sample_class_notes,
            'words_field'   => 'notes',
            'note'          => lang('Another day, a question or cannot come? Write to us below; we answer here and by e-mail.'),
        )),
    ),

    'booking_messages' => $bk_messages_widget('booking_view',
        lang('No messages yet. The bakery\'s answer comes here; you can write to us about your class too.')),

    'my_bookings_list' => $bk_record_list('my_bookings', 'class_booking', 'booking_view', $booking_row(false), array(), '',
        lang('No class place requests yet. Choose a class and ask for your place.')),

    'staff_bookings_list' => $bk_record_list('staff_bookings', 'class_booking', 'booking_view', $booking_row(true),
        array('first_name', 'last_name', 'email', 'class', 'status'),
        lang('Search by name, e-mail, class or status...'), lang('No class place requests yet.')),

    // The e-mail pages' widgets: drawn for the submission just sent.
    'email_cake_order_admin' => $bk_detail_widget('email_cake_order_new', 'cake_order', lang('The cake order could not be found.'), 'public') + array(
        'tree' => $widget_root(array(
            $mail_heading(lang('A new cake order')),
            $mail_para(lang('It came from the cake order form. Send the design and the price under the order on its page and set the status; what you write there reaches the customer by e-mail.')),
            $mail_cake_order(),
            $bk_mail_sender(lang('Your ideas'), $sample_cake_notes, 'notes'),
            $mail_button(lang('Open the order'), '{{page:cake_order_view}}?r=^^reference_code^^'),
            $mail_reference(),
        )),
    ),
    'email_cake_order_client' => $bk_detail_widget('email_cake_order_received', 'cake_order', lang('The cake order could not be found.'), 'public') + array(
        'tree' => $widget_root(array(
            $mail_heading(lang('We received your cake order')),
            $mail_greeting(),
            $mail_para(lang('Thank you. We send a design and a price within a day, on the order\'s page and by e-mail. Nothing is paid until you agree.')),
            $mail_cake_order(),
            $mail_para(lang('Another picture or a change? Write to us on the order\'s page; everything about your cake stays there.')),
            $mail_button(lang('Open my order'), '{{page:cake_order_view}}?r=^^reference_code^^'),
            $mail_new_account(),
        )),
    ),
    'email_booking_admin' => $bk_detail_widget('email_booking_new', 'class_booking', lang('The class place request could not be found.'), 'public') + array(
        'tree' => $widget_root(array(
            $mail_heading(lang('A new class place request')),
            $mail_para(lang('It came from the class place form. Confirm the place and the class day under the request on its page, or offer another day, and set the status; what you write there reaches the customer by e-mail.')),
            $mail_booking(),
            $bk_mail_sender(lang('Notes'), $sample_class_notes, 'notes'),
            $mail_button(lang('Open the request'), '{{page:booking_view}}?r=^^reference_code^^'),
            $mail_reference(),
        )),
    ),
    'email_booking_client' => $bk_detail_widget('email_booking_received', 'class_booking', lang('The class place request could not be found.'), 'public') + array(
        'tree' => $widget_root(array(
            $mail_heading(lang('We received your class place request')),
            $mail_greeting(),
            $mail_para(lang('Thank you. We confirm your place and the class day within a day, on the request\'s page and by e-mail. Bring an apron; everything else is at the bench.')),
            $mail_booking(),
            $mail_para(lang('Another day or cannot come? Write to us on the request\'s page so the place goes to someone else.')),
            $mail_button(lang('Open my request'), '{{page:booking_view}}?r=^^reference_code^^'),
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
