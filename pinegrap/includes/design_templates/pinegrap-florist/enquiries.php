<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Florist", its enquiries: the event flowers enquiry and
 * the subscription request, each with its own page and messages, the
 * sender's lists on My Requests and the staff's lists; the first
 * consultation booked at a free time; the e-mails of both forms and the
 * widgets that draw them. Included after parts.php, mail.php and
 * orders.php in the same scope (it reuses $detail, $bound, $sender_name,
 * $mail_reference and $mail_new_account); returns
 * array('pages' => ..., 'widgets' => ...).
 *
 * An event enquiry is a request, never a booking of the date: a record of
 * the Event Enquiries form. A subscription request is a record of the
 * Subscription Requests form; the florist confirms the first delivery and
 * the payment, and nothing is billed again by itself. A sender without an
 * account gets one (auto-registration). The Status of both is an
 * office-use-only field the florist changes in the control panel; the
 * consultation, the proposal, the first delivery and a pause are written
 * under the record as messages (the page's comments), mailed to the sender
 * and to the florist.
 *
 * The event and the plan start with what a link sends (?event=, ?plan=:
 * their titles, as parts.php writes them; the Our Work page sends its
 * Kind).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

$enquiry_pages = array();

// The states of a subscription request, set by the florist in the panel.
$subscription_statuses = array(lang('Request received'), lang('Active'), lang('Paused'), lang('Ended'), lang('Cancelled'));

// The sample records the canvas shows. Their dates are words, never a
// date: a fixed day would read as long past a year later.
$sample_event = $event_services['weddings']['title'];
$sample_event_date = lang('Event date');
$sample_budget = $budget_choices[1];
$sample_venue = lang('The garden of a seaside hotel');
$sample_wishes = lang('Soft whites and blush, a long table for eighty and an arch by the sea; my sister carries the bouquet down the aisle.');
$sample_plan = $plans['fortnightly']['title'];
$sample_start = lang('Start date');
$sample_day = $weekday_choices[4];
$sample_slot = $slots['morning']['title'];
$sample_zone = $zones['centre']['title'];
$sample_address = $florist['address'];
$sample_sub_notes = lang('For the office reception; no lilies, please.');

// ── Page shells ─────────────────────────────────────────────────────────

// A numbered list of what happens next.
$next_steps = function ($steps) use ($el, $heading) {
    $items = array();
    foreach ($steps as $n => $text) {
        $items[] = $el('li', $n < count($steps) - 1 ? 'mb-2' : '', lang('Step'), array(), array('text' => $text));
    }
    return $el('div', 'p-4 rounded-4 bg-body-tertiary mb-4', lang('How It Works'), array(
        $heading('h2', lang('What happens next'), 'h5 fw-semibold mb-3'),
        $el('ol', 'small text-body-secondary ps-3 mb-0', lang('List'), $items),
    ));
};
// A side box: its heading, a line and a button.
$side_box = function ($name, $title, $text, $button, $href, $class = 'p-4 rounded-4 border') use ($el, $heading, $para, $link) {
    return $el('div', $class, $name, array(
        $heading('h2', $title, 'h5 fw-semibold mb-2'),
        $para($text, 'small text-body-secondary mb-3'),
        $link($button, $href, 'btn btn-sm btn-outline-primary rounded-pill px-3'),
    ));
};
// A form page: the header, the form in its card, the side column.
$form_page = function ($title, $lead, $widget_key, $aside) use ($page, $page_header, $el, $container, $row, $col, $widget) {
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
// The messages settings of a record page.
$record_comments = function ($subject) {
    return array('label' => lang('Message'), 'allow_new' => true, 'auto_publish' => true, 'show_date' => true, 'login' => true,
                 'attachments' => true,
                 'email_page' => '{{tab:email_reply}}', 'email_subject' => $subject,
                 'notify_email' => '{{site_email}}');
};

// ── Pages ───────────────────────────────────────────────────────────────

// The event flowers enquiry. What is sent opens the enquiry's own page.
$enquiry_pages[] = array(
    'key'              => 'event_enquiry',
    'name'             => lang('event-flowers-enquiry'),
    'folder'           => 'public',
    'title'            => lang('Event Flowers Enquiry'),
    'meta_description' => lang('Tell us about your wedding or event: we answer within a day with a time for a free consultation. Nothing is paid now.'),
    'form'             => array(
        'form_name'            => lang('Event Enquiries'),
        'confirmation_message' => lang('Thank you, your enquiry has reached us. We answer within a day.'),
        'confirmation_page_id' => '{{tab:event_enquiry_view}}',
        'auto_registration'    => 1,
        'notify_email'         => '{{site_email}}',
        'notify_subject'       => lang('New event enquiry: ^^event^^ from ^^first_name^^ ^^last_name^^'),
        'notify_page_id'       => '{{tab:email_event_new}}',
        'confirm_email'        => 1,
        'confirm_subject'      => lang('We have your event enquiry'),
        'confirm_page_id'      => '{{tab:email_event_received}}',
    ),
    'tree'             => $form_page(lang('Event Flowers Enquiry'),
        lang('Tell us about the day: the room, the colours, the guests. We answer within a day with a time for a free consultation; nothing is paid now.'),
        'event_form', array(
            $next_steps(array(
                lang('Your enquiry opens its own page, and an account for you if you have none.'),
                lang('We answer there within a day and suggest a time for a free consultation.'),
                lang('After the consultation you receive a proposal with the flowers and the price, written under your enquiry.'),
                lang('Your date is ours once you agree to the proposal.'),
            )),
            $side_box(lang('Pick a Time'), lang('Pick a free time now'),
                lang('Rather talk first? Choose a free time for a consultation at the studio or by video call.'),
                lang('Book a Consultation'), '{{page:consultation}}', 'p-4 rounded-4 border mb-4'),
            $side_box(lang('Our Work'), lang('See what we have made'),
                lang('Weddings, parties and lobbies we have dressed, with the flowers we used.'),
                lang('Our Work'), '{{page:work}}'),
        )),
);

// One event enquiry: the enquiry, its status and the messages under it.
$enquiry_pages[] = array(
    'key'      => 'event_enquiry_view',
    'name'     => lang('event-enquiry'),
    'folder'   => 'registration',
    'title'    => lang('Event Enquiry'),
    'search'   => false,
    'sitemap'  => false,
    'comments' => $record_comments(lang('There is a new message about your event enquiry')),
    'tree'     => $page(array(
        $widget('event_detail'),
        $comments_section(lang('Messages'), 'event_messages'),
    ), false),
);

// The staff's list of every event enquiry, newest first, with a search.
$enquiry_pages[] = array(
    'key'     => 'staff_events',
    'name'    => lang('all-event-enquiries'),
    'folder'  => 'private',
    'title'   => lang('Event Enquiries'),
    'search'  => false,
    'sitemap' => false,
    'tree'    => $staff_page(array(
        $page_header(lang('Event Enquiries'), lang('Every event enquiry, newest first: suggest a consultation, send the proposal, set the status and write to the sender under each.')),
        $el('section', 'py-5', lang('Content'), array(
            $container(array($widget('staff_events_list'))),
        )),
    )),
);

// The subscription request. What is sent opens the request's own page.
$enquiry_pages[] = array(
    'key'              => 'subscribe',
    'name'             => lang('start-a-subscription'),
    'folder'           => 'public',
    'title'            => lang('Start a Subscription'),
    'meta_description' => lang('Choose a plan, the day and the address: we confirm the first delivery and the price, and nothing is paid until then.'),
    'form'             => array(
        'form_name'            => lang('Subscription Requests'),
        'confirmation_message' => lang('Thank you, your subscription request has reached us. We confirm the first delivery shortly.'),
        'confirmation_page_id' => '{{tab:subscription_view}}',
        'auto_registration'    => 1,
        'notify_email'         => '{{site_email}}',
        'notify_subject'       => lang('New subscription request: ^^plan^^ for ^^first_name^^ ^^last_name^^'),
        'notify_page_id'       => '{{tab:email_subscription_new}}',
        'confirm_email'        => 1,
        'confirm_subject'      => lang('We have your subscription request'),
        'confirm_page_id'      => '{{tab:email_subscription_received}}',
    ),
    'tree'             => $form_page(lang('Start a Subscription'),
        lang('Choose the plan, the day and the address. We confirm the first delivery and the price; nothing is paid now.'),
        'subscribe_form', array(
            $next_steps(array(
                lang('Your request opens its own page, and an account for you if you have none.'),
                lang('We confirm the first delivery and the price there, usually the same day.'),
                lang('You pay once we have confirmed; nothing is charged for a request.'),
                lang('Pause, skip or end it with a message under your request.'),
            )),
            $side_box(lang('Plans'), lang('Compare the plans'),
                lang('Weekly, fortnightly or monthly, with what each includes.'),
                lang('Flower Subscriptions'), '{{page:subscriptions}}', 'p-4 rounded-4 border mb-4'),
            $side_box(lang('Delivery'), lang('Where we deliver'),
                lang('Subscription deliveries are free in every area we reach.'),
                lang('Delivery areas and fees'), '{{page:delivery}}'),
        )),
);

// One subscription request: the request, its status and the messages.
$enquiry_pages[] = array(
    'key'      => 'subscription_view',
    'name'     => lang('subscription-request'),
    'folder'   => 'registration',
    'title'    => lang('Subscription Request'),
    'search'   => false,
    'sitemap'  => false,
    'comments' => $record_comments(lang('There is a new message about your subscription')),
    'tree'     => $page(array(
        $widget('subscription_detail'),
        $comments_section(lang('Messages'), 'subscription_messages'),
    ), false),
);

// A first consultation at a free time: the booking widget's own layout
// beside the way to send an enquiry first and the studio's hours.
$enquiry_pages[] = array(
    'key'              => 'consultation',
    'name'             => lang('book-a-consultation'),
    'folder'           => 'public',
    'title'            => lang('Book a Consultation'),
    'meta_description' => lang('Choose a free time to meet the florist at the studio or by video call about your wedding or event, and confirm in a minute.'),
    'tree'             => $page(array(
        $page_header(lang('Book a Consultation'), lang('Meet us at the studio or by video call, with samples and a mood board. Choose one of the free times; it is yours as soon as you confirm.')),
        $el('section', 'py-5', lang('Content'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $el('div', 'card border-0 shadow-sm rounded-4', lang('Booking Card'), array(
                            $el('div', 'card-body p-4 p-lg-5', lang('Card Body'), array($widget('consultation_slots'))),
                        )),
                    ), '', '8'),
                    $col(array(
                        $side_box(lang('Ask First'), lang('Rather ask first?'),
                            lang('Tell us about your event in an enquiry and we answer within a day with a time that suits you.'),
                            lang('Ask about your event'), '{{page:event_enquiry}}', 'p-4 rounded-4 bg-body-tertiary mb-4'),
                        $el('div', 'p-4 rounded-4 border', lang('The Studio'), array(
                            $heading('h2', lang('The Studio'), 'h5 fw-semibold mb-3'),
                            $check_list(array($florist['address'], $florist['hours'])),
                        )),
                    ), '', '4'),
                ), '4', '', 'g-lg-5'),
            )),
        ), $searchable('fl-consultation', lang('consultation, free time, appointment, samples, mood board, video call'))),
    )),
);

// The e-mails of both forms: what the florist receives and the reply to
// the sender.
$enquiry_pages[] = array_merge($mail_settings, array(
    'key'   => 'email_event_new',
    'name'  => lang('email-new-event-enquiry'),
    'title' => lang('New Event Enquiry E-mail'),
    'tree'  => $mail_page(array($widget('email_event_admin'))),
));
$enquiry_pages[] = array_merge($mail_settings, array(
    'key'   => 'email_event_received',
    'name'  => lang('email-event-enquiry-received'),
    'title' => lang('Event Enquiry Received E-mail'),
    'tree'  => $mail_page(array($widget('email_event_client'))),
));
$enquiry_pages[] = array_merge($mail_settings, array(
    'key'   => 'email_subscription_new',
    'name'  => lang('email-new-subscription-request'),
    'title' => lang('New Subscription Request E-mail'),
    'tree'  => $mail_page(array($widget('email_subscription_admin'))),
));
$enquiry_pages[] = array_merge($mail_settings, array(
    'key'   => 'email_subscription_received',
    'name'  => lang('email-subscription-request-received'),
    'title' => lang('Subscription Request Received E-mail'),
    'tree'  => $mail_page(array($widget('email_subscription_client'))),
));

// ── Widget pieces ───────────────────────────────────────────────────────

// A record in a list, linked to its page: the title, the status and the
// detail items. The staff's row starts with the sender's name and adds the
// phone; the sender's own adds the messages.
$record_row = function ($name, $title_name, $title_sample, $title_field, $status, $items, $staff) use ($el, $span, $icon, $bind, $sender_name) {
    $out = array();
    foreach ($items as $i) {
        $out[] = $el('span', 'd-inline-flex align-items-center gap-1', $i[1], array_merge(array($icon($i[0])), $i[2]));
    }
    if ($staff) {
        $out[] = $el('span', 'd-inline-flex align-items-center gap-1', lang('Phone'), array(
            $icon('bi-telephone'), $span('+90 555 000 00 00', '', lang('Phone'), $bind('phone'))));
        $title = $el('span', 'fw-semibold', lang('Sender'), array_merge($sender_name(), array(
            $span(' · ', 'text-body-secondary', lang('Text')),
            $span($title_sample, 'text-body-secondary fw-normal', $title_name, $bind($title_field)),
        )));
    } else {
        $out[] = $el('span', 'd-inline-flex align-items-center gap-1', lang('Messages'), array(
            $icon('bi-chat-left-text'), $span('0', '', lang('Reply Count'), $bind('number_of_comments'))));
        $title = $span($title_sample, 'fw-semibold', $title_name, $bind($title_field));
    }
    return $el('a', 'list-group-item list-group-item-action p-3', $name, array(
        $el('div', 'd-flex flex-wrap align-items-center justify-content-between gap-2 mb-1', lang('Top Line'), array(
            $title,
            $span($status, 'badge rounded-pill bg-primary-subtle text-primary-emphasis', lang('Status'), $bind('status')),
        )),
        $el('div', 'd-flex flex-wrap column-gap-3 row-gap-1 small text-body-secondary', lang('Details'), $out),
    ), array('href' => '#', '_bindings' => array('href' => 'form_item_view')));
};
$event_row = function ($staff) use ($record_row, $span, $bind, $sample_event, $sample_event_date, $sample_venue, $enquiry_statuses) {
    return $record_row(lang('Event Enquiry'), lang('Event'), $sample_event, 'event', $enquiry_statuses[0], array(
        array('bi-calendar-heart', lang('Date'), array($span($sample_event_date, '', lang('Event Date'), $bind('event_date')))),
        array('bi-people', lang('Guests'), array($span('80', '', lang('Guests'), $bind('guests')))),
        array('bi-geo-alt', lang('Venue'), array($span($sample_venue, '', lang('Venue'), $bind('venue')))),
    ), $staff);
};
$subscription_row = function ($staff) use ($record_row, $span, $bind, $sample_plan, $sample_start, $sample_day, $sample_slot, $sample_zone, $subscription_statuses) {
    return $record_row(lang('Subscription Request'), lang('Plan'), $sample_plan, 'plan', $subscription_statuses[0], array(
        array('bi-calendar-event', lang('Start Date'), array($span($sample_start, '', lang('Start Date'), $bind('start_date')))),
        array('bi-arrow-repeat', lang('Delivery Day'), array(
            $span($sample_day, 'me-1', lang('Delivery Day'), $bind('delivery_day')),
            $span($sample_slot, '', lang('Delivery Time'), $bind('delivery_time')),
        )),
        array('bi-geo-alt', lang('Area'), array($span($sample_zone, '', lang('Area'), $bind('zone')))),
    ), $staff);
};

// The form detail settings of a record widget: the record page's (the
// sender's own, and the staff's) or an e-mail page's (any reader: the
// e-mail is drawn for the submission just sent).
$record_widget = function ($page_key, $form_key, $missing, $access) {
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
// A list of records: the sender's own (the staff see them all), or the
// staff's with a search.
$record_list = function ($page_key, $form_key, $view_key, $row, $staff, $search, $search_label, $empty) use ($widget_root, $el, $loop) {
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
            'search_fields'  => $search,
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
            $el('div', 'list-group', lang('Record List'), array($loop(array($row($staff))))),
        )),
    );
};
// The messages under a record: the page's own Comments widget, shown only
// to whoever may read the record above it. Oldest first, all on one page.
// No Reply, Share or follow box: the sender is e-mailed every answer and
// the florist every message.
$record_messages = function ($page_key, $empty) use ($comment_thread) {
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
// The record page's widget tree: the not-found note, the sent notice, the
// new account, the way back and the Edit button, the card with its
// heading, status and details, then what follows the card.
$record_tree = function ($o) use ($container, $row, $col, $el, $icon, $heading, $para, $span, $link, $attr, $bind, $show_when, $edit_button) {
    return array('type' => 'root', 'props' => array(), 'children' => array(
        $container(array(
            $row(array(
                $col(array(
                    $el('div', 'text-center py-5', lang('Not Found'), array(
                        $icon('bi-envelope-x', 'display-5 text-body-tertiary'),
                        $heading('h2', $o['not_found'], 'h3 fw-semibold mt-3'),
                        $para($o['missing'], 'text-body-secondary mb-4', $bind('not_found')),
                        $link(lang('My Requests'), '{{page:my_requests}}', 'btn btn-primary rounded-pill px-4'),
                    ), $show_when('record_not_found')),
                    $el('div', '', lang('Record'), array_merge(array(
                        $el('div', 'alert alert-success d-flex gap-2', lang('Sent Notice'), array(
                            $icon('bi-check-circle', 'mt-1'),
                            $span($o['sent'], '', lang('Text')),
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
                        $el('div', 'card border-0 shadow-sm rounded-4 mb-4', $o['card'], array(
                            $el('div', 'card-body p-4', lang('Card Body'), array(
                                $el('div', 'd-flex flex-wrap align-items-start justify-content-between gap-2 mb-3', lang('Title'), array(
                                    $el('div', '', lang('Words'), array(
                                        $para($o['eyebrow'], 'pfl-eyebrow small text-uppercase fw-semibold text-primary mb-1'),
                                        $el('h1', 'pfl-display h3 mb-0', $o['title_name'], array(), $o['title']),
                                    )),
                                    $span($o['status'], 'badge rounded-pill bg-primary-subtle text-primary-emphasis fs-6', lang('Status'), $bind('status')),
                                )),
                                $el('dl', 'row small mb-0', lang('Details'), $o['details']),
                            )),
                        )),
                    ), $o['after']), $show_when('record_found')),
                ), '', '8'),
            ), '', 'center'),
        ), 'py-5'),
    ));
};
// A long answer under the card; it leaves when it is empty.
$record_text = function ($label, $sample, $field, $class = '') use ($el, $para, $bound) {
    return $el('div', 'pg-hide-if-empty p-4 rounded-4 bg-body-tertiary mb-4', $label, array(
        $para($label, 'small fw-semibold text-body-secondary mb-2'),
        $el('div', trim('pg-field-value lh-lg ' . $class), lang('Value'), array(), $bound($sample, $field)),
    ));
};
// The note at the foot of a record page.
$record_note = function ($text, $button, $href) use ($el, $icon, $span, $link) {
    return $el('div', 'd-flex flex-wrap gap-2 small text-body-secondary', lang('Note'), array(
        $icon('bi-chat-left-text', 'text-primary mt-1'),
        $span($text, '', lang('Text')),
        $link($button, $href, 'fw-semibold link-primary text-decoration-none'),
    ));
};

// The lines of an event enquiry and of a subscription request, for their
// e-mails.
$mail_event = function () use ($mail_box, $mail_line, $span, $bind, $sample_event, $sample_event_date, $sample_venue, $sample_budget) {
    return $mail_box(array(
        $mail_line(lang('Event'), array($span($sample_event, '', lang('Event'), $bind('event')))),
        $mail_line(lang('Event Date'), array($span($sample_event_date, '', lang('Event Date'), $bind('event_date')))),
        $mail_line(lang('Guests'), array($span('80', '', lang('Guests'), $bind('guests')))),
        $mail_line(lang('Venue'), array($span($sample_venue, '', lang('Venue'), $bind('venue')))),
        $mail_line(lang('Budget'), array($span($sample_budget, '', lang('Budget'), $bind('budget')))),
    ), lang('Event Enquiry'));
};
$mail_subscription = function () use ($mail_box, $mail_line, $span, $bind, $sample_plan, $sample_start, $sample_day, $sample_slot, $sample_zone, $sample_address) {
    return $mail_box(array(
        $mail_line(lang('Plan'), array($span($sample_plan, '', lang('Plan'), $bind('plan')))),
        $mail_line(lang('Start Date'), array($span($sample_start, '', lang('Start Date'), $bind('start_date')))),
        $mail_line(lang('Delivery Day'), array($span($sample_day, '', lang('Delivery Day'), $bind('delivery_day')))),
        $mail_line(lang('Delivery Time'), array($span($sample_slot, '', lang('Delivery Time'), $bind('delivery_time')))),
        $mail_line(lang('Area'), array($span($sample_zone, '', lang('Area'), $bind('zone')))),
        $mail_line(lang('Address'), array($span($sample_address, '', lang('Address'), $bind('address')))),
    ), lang('Subscription Request'));
};
// The sender's lines of a staff e-mail, then the long answer.
$mail_from = function ($label, $sample, $field) use ($mail_box, $mail_line, $span, $bind, $sender_name) {
    return $mail_box(array(
        $mail_line(lang('From'), $sender_name()),
        $mail_line(lang('Email'), array($span('ayse@example.com', '', lang('Email'), $bind('email')))),
        $mail_line(lang('Phone'), array($span('+90 555 000 00 00', '', lang('Phone'), $bind('phone')))),
        $mail_line($label, array($span($sample, '', $label, $bind($field)))),
    ), lang('Sender'));
};

// The sender's fields at the end of both forms.
$contact_fields = function ($prefix) use ($row, $col, $field, $input) {
    return $row(array(
        $col(array($field($prefix . '-first-name', lang('First Name'), $input($prefix . '-first-name', 'text', 'first_name', 'given-name', true, array('contact_field' => 'first_name')))), '6'),
        $col(array($field($prefix . '-last-name', lang('Last Name'), $input($prefix . '-last-name', 'text', 'last_name', 'family-name', true, array('contact_field' => 'last_name')))), '6'),
        $col(array($field($prefix . '-email', lang('Email'), $input($prefix . '-email', 'email', 'email', 'email', true, array('contact_field' => 'email_address')))), '6'),
        $col(array($field($prefix . '-phone', lang('Phone'), $input($prefix . '-phone', 'tel', 'phone', 'tel', true, array('contact_field' => 'mobile_phone')))), '6'),
    ), '3');
};

$event_missing = lang('This event enquiry could not be found, or it is not yours to see.');
$subscription_missing = lang('This subscription request could not be found, or it is not yours to see.');
$plan_default = $plans['fortnightly']['title'];

$enquiry_widgets = array(

    // The event enquiry: the fields are the page's form. The event starts
    // with what a link sends (?event=). The date is never in the past and
    // may stay empty while it is open. The Status is the florist's (office
    // use only): every enquiry starts as Enquiry received.
    'event_form' => array(
        'page'   => 'event_enquiry',
        'slug'   => 'form',
        'config' => array('regionType' => 'custom_form', 'form_source' => 'page'),
        'tree'   => $widget_root(array(
            $form_step(1, lang('Your event')),
            $row(array(
                $col(array($field('fl-evt-event', lang('Event'), $select('fl-evt-event', 'event', '', $event_choices, false, end($event_choices), array('url_param' => 'event')))), '6'),
                $col(array($field('fl-evt-date', lang('Event Date'), $input('fl-evt-date', 'date', 'event_date', 'off', false, array('rules' => array('min_date' => 'today'))), 'mb-3',
                    lang('Leave it empty if the date is open.'))), '6'),
                $col(array($field('fl-evt-guests', lang('Guests'), $input('fl-evt-guests', 'number', 'guests', 'off', true, array(),
                    array($attr('min', '1'), $attr('max', '1000'), $attr('step', '1'), $attr('value', '50'))))), '6'),
                $col(array($field('fl-evt-budget', lang('Budget'), $select('fl-evt-budget', 'budget', '', $budget_choices, false, end($budget_choices)))), '6'),
            ), '3'),
            $field('fl-evt-venue', lang('Venue'), $input('fl-evt-venue', 'text', 'venue', 'off', false), 'mb-3',
                lang('The hall, the garden or the address; we come and see it if it helps.')),
            $form_step(2, lang('Tell us about it')),
            $field('fl-evt-wishes', lang('Your flowers'), $textarea('fl-evt-wishes', 'wishes', 4, false), 'mb-3',
                lang('The colours, the flowers you love, the tables and the feeling you want.')),
            $field('fl-evt-documents', lang('Mood board or floor plan'), $file('fl-evt-documents', 'documents', 'private'), 'mb-3',
                lang('Optional: a PDF, a document or a picture.')),
            $form_step(3, lang('How to reach you')),
            $contact_fields('fl-evt'),
            $radios('fl-evt-contact', 'contact_method', lang('Best way to reach you'), $contact_choices, $contact_choices[0][0]),
            $field('fl-evt-status', lang('Status'), $select('fl-evt-status', 'status', '', $enquiry_statuses, false, $enquiry_statuses[0], array('office_use_only' => true))),
            $agree('fl-evt-consent', 'consent', lang('I agree that the florist keeps these details to answer my enquiry and prepare the flowers for my event, as its privacy notice explains.'), true),
            $captcha(),
            $el('div', 'd-flex flex-wrap align-items-center gap-3', lang('Send Area'), array(
                $submit(lang('Send the Enquiry'), 'btn btn-primary btn-lg rounded-pill px-4'),
                $para(lang('We answer within a day. Nothing is paid now.'), 'small text-body-secondary mb-0'),
            )),
            $loop(array()),
        )),
    ),

    // One event enquiry: the event, its status and what the sender wrote;
    // the florist's answers (the consultation, the proposal) follow as
    // comments.
    'event_detail' => $record_widget('event_enquiry_view', 'event_enquiry', $event_missing, 'submitter_only') + array(
        'tree' => $record_tree(array(
            'not_found'  => lang('Event enquiry not found'),
            'missing'    => $event_missing,
            'sent'       => lang('Thank you, your enquiry has reached us. We answer here within a day with a time for a free consultation; this page shows the status, and you can write to us below.'),
            'card'       => lang('Enquiry Card'),
            'eyebrow'    => lang('Event Enquiry'),
            'title_name' => lang('Event'),
            'title'      => $bound($sample_event, 'event'),
            'status'     => $enquiry_statuses[0],
            'details'    => array_merge(
                $detail(lang('Event Date'), '', lang('Event Date'), $bound($sample_event_date, 'event_date')),
                $detail(lang('Guests'), '', lang('Guests'), $bound('80', 'guests')),
                $detail(lang('Venue'), '', lang('Venue'), $bound($sample_venue, 'venue')),
                $detail(lang('Budget'), '', lang('Budget'), $bound($sample_budget, 'budget')),
                $detail(lang('Best way to reach you'), '', lang('Contact by'), $bound(lang('E-mail'), 'contact_method')),
                array(
                    $el('dt', 'col-sm-4 fw-normal text-body-secondary', lang('Label'), array(), array('text' => lang('Mood board or floor plan'))),
                    $el('dd', 'col-sm-8 text-break', lang('Documents'), array(
                        $link('mood-board.pdf', '#', '', array(), array('target' => '_blank', 'rel' => 'noopener',
                            '_bindings' => array('href' => 'documents', 'text' => 'documents'))),
                    )),
                ),
                $detail(lang('Reference Code'), 'mb-0 font-monospace', lang('Reference Code'), $bound('ABC123', 'reference_code'))
            ),
            'after'      => array(
                $record_text(lang('Your flowers'), $sample_wishes, 'wishes'),
                $record_note(lang('Another date, a question about the proposal or something to add? Write to us below; we answer here and by e-mail.'),
                    lang('Book a Consultation'), '{{page:consultation}}'),
            ),
        )),
    ),

    'event_messages' => $record_messages('event_enquiry_view',
        lang('No messages yet. Our answer comes here; you can write to us about your event too.')),

    'my_events_list' => $record_list('my_requests', 'event_enquiry', 'event_enquiry_view', $event_row, false, array(), '',
        lang('No event enquiries yet. Tell us about your wedding or event, and we answer within a day.')),

    'staff_events_list' => $record_list('staff_events', 'event_enquiry', 'event_enquiry_view', $event_row, true,
        array('first_name', 'last_name', 'email', 'event', 'venue', 'budget', 'status'),
        lang('Search by name, e-mail, event, venue, budget or status...'), lang('No event enquiries yet.')),

    // The booking widget with the editor's starter layout; it shows its
    // no-services text until Settings › Bookings has a service (a first
    // consultation).
    'consultation_slots' => array(
        'page'   => 'consultation',
        'slug'   => lang('booking'),
        'config' => array('regionType' => 'booking'),
        'tree'   => 'starter',
    ),

    // The subscription request: the fields are the page's form. The plan
    // starts with what a link sends (?plan=). The first delivery is never
    // in the past. The Status is the florist's (office use only): every
    // request starts as Request received.
    'subscribe_form' => array(
        'page'   => 'subscribe',
        'slug'   => 'form',
        'config' => array('regionType' => 'custom_form', 'form_source' => 'page'),
        'tree'   => $widget_root(array(
            $form_step(1, lang('Your plan')),
            $row(array(
                $col(array($field('fl-sub-plan', lang('Plan'), $select('fl-sub-plan', 'plan', '', $plan_titles, true, $plan_default, array('url_param' => 'plan')))), '6'),
                $col(array($field('fl-sub-start', lang('Start Date'), $input('fl-sub-start', 'date', 'start_date', 'off', true, array('rules' => array('min_date' => 'today'))), 'mb-3',
                    lang('The day of the first delivery; we confirm it.'))), '6'),
                $col(array($field('fl-sub-day', lang('Delivery Day'), $select('fl-sub-day', 'delivery_day', '', $weekday_choices, true, $weekday_choices[0]))), '6'),
                $col(array($field('fl-sub-time', lang('Delivery Time'), $select('fl-sub-time', 'delivery_time', '', $slot_titles, true, $slot_titles[0]))), '6'),
            ), '3'),
            $form_step(2, lang('The delivery')),
            $row(array(
                $col(array($field('fl-sub-zone', lang('Area'), $select('fl-sub-zone', 'zone', '', $zone_titles, true, $zone_titles[0]))), '6'),
                $col(array($field('fl-sub-recipient', lang('Recipient\'s name'), $input('fl-sub-recipient', 'text', 'recipient_name', 'off', false), 'mb-3',
                    lang('Leave it empty if the flowers are for you.'))), '6'),
            ), '3'),
            $field('fl-sub-address', lang('Address'), $textarea('fl-sub-address', 'address', 2, true), 'mb-3',
                lang('Street, number, floor and flat; a landmark helps.')),
            $field('fl-sub-notes', lang('Notes for us'), $textarea('fl-sub-notes', 'notes', 2, false), 'mb-3',
                lang('Colours you love, an allergy, where to leave the flowers.')),
            $form_step(3, lang('How to reach you')),
            $contact_fields('fl-sub'),
            $field('fl-sub-status', lang('Status'), $select('fl-sub-status', 'status', '', $subscription_statuses, false, $subscription_statuses[0], array('office_use_only' => true))),
            $agree('fl-sub-consent', 'consent', lang('I agree that the florist keeps these details to deliver my subscription, as its privacy notice explains.'), true),
            $captcha(),
            $el('div', 'd-flex flex-wrap align-items-center gap-3', lang('Send Area'), array(
                $submit(lang('Send the Request'), 'btn btn-primary btn-lg rounded-pill px-4'),
                $para(lang('We confirm the price before anything is paid.'), 'small text-body-secondary mb-0'),
            )),
            $loop(array()),
        )),
    ),

    // One subscription request: the plan, the day, the address and its
    // status; the florist's answers (the first delivery, a pause) follow
    // as comments.
    'subscription_detail' => $record_widget('subscription_view', 'subscribe', $subscription_missing, 'submitter_only') + array(
        'tree' => $record_tree(array(
            'not_found'  => lang('Subscription request not found'),
            'missing'    => $subscription_missing,
            'sent'       => lang('Thank you, your subscription request has reached us. We confirm the first delivery and the price here, usually the same day; this page shows the status, and you can write to us below.'),
            'card'       => lang('Subscription Card'),
            'eyebrow'    => lang('Subscription Request'),
            'title_name' => lang('Plan'),
            'title'      => $bound($sample_plan, 'plan'),
            'status'     => $subscription_statuses[0],
            'details'    => array_merge(
                $detail(lang('Start Date'), '', lang('Start Date'), $bound($sample_start, 'start_date')),
                $detail(lang('Delivery Day'), '', lang('Delivery Day'), $bound($sample_day, 'delivery_day')),
                $detail(lang('Delivery Time'), '', lang('Delivery Time'), $bound($sample_slot, 'delivery_time')),
                $detail(lang('Area'), '', lang('Area'), $bound($sample_zone, 'zone')),
                $detail(lang('Recipient'), '', lang('Recipient'), $bound(lang('Elif Demir'), 'recipient_name')),
                $detail(lang('Address'), '', lang('Address'), $bound($sample_address, 'address')),
                $detail(lang('Reference Code'), 'mb-0 font-monospace', lang('Reference Code'), $bound('ABC123', 'reference_code'))
            ),
            'after'      => array(
                $record_text(lang('Notes for us'), $sample_sub_notes, 'notes'),
                $record_note(lang('Away for a week, another day or a new address? Write to us below; we answer here and by e-mail.'),
                    lang('Flower Subscriptions'), '{{page:subscriptions}}'),
            ),
        )),
    ),

    'subscription_messages' => $record_messages('subscription_view',
        lang('No messages yet. Our answer comes here; you can write to us about your subscription too.')),

    'my_subscriptions_list' => $record_list('my_requests', 'subscribe', 'subscription_view', $subscription_row, false, array(), '',
        lang('No subscriptions yet. Choose a plan and we confirm the first delivery.')),

    'staff_subscriptions_list' => $record_list('staff_requests', 'subscribe', 'subscription_view', $subscription_row, true,
        array('first_name', 'last_name', 'email', 'plan', 'delivery_day', 'zone', 'status'),
        lang('Search by name, e-mail, plan, delivery day, area or status...'), lang('No subscription requests yet.')),

    // The e-mail pages' widgets: drawn for the submission just sent.
    'email_event_admin' => $record_widget('email_event_new', 'event_enquiry', lang('This event enquiry was not found.'), 'public') + array(
        'tree' => $widget_root(array(
            $mail_heading(lang('A new event enquiry')),
            $mail_para(lang('It came from the event enquiry form. Answer under the enquiry on its page with a time for a consultation, and set the status; what you write there reaches the sender by e-mail.')),
            $mail_event(),
            $mail_from(lang('Your flowers'), $sample_wishes, 'wishes'),
            $mail_button(lang('Open the enquiry'), '{{page:event_enquiry_view}}?r=^^reference_code^^'),
            $mail_reference(),
        )),
    ),
    'email_event_client' => $record_widget('email_event_received', 'event_enquiry', lang('This event enquiry was not found.'), 'public') + array(
        'tree' => $widget_root(array(
            $mail_heading(lang('We have your event enquiry')),
            $mail_greeting(),
            $mail_para(lang('Thank you. We read every enquiry ourselves and answer within a day with a time for a free consultation, on the enquiry\'s page and by e-mail. Nothing is paid now.')),
            $mail_event(),
            $mail_para(lang('Rather talk first? Book a consultation at a free time, or write to us on the enquiry\'s page.')),
            $mail_button(lang('Open my enquiry'), '{{page:event_enquiry_view}}?r=^^reference_code^^'),
            $mail_new_account(),
        )),
    ),
    'email_subscription_admin' => $record_widget('email_subscription_new', 'subscribe', lang('This subscription request was not found.'), 'public') + array(
        'tree' => $widget_root(array(
            $mail_heading(lang('A new subscription request')),
            $mail_para(lang('It came from the subscription form. Confirm the first delivery and the price under the request on its page, and set the status; what you write there reaches the sender by e-mail.')),
            $mail_subscription(),
            $mail_from(lang('Notes for us'), $sample_sub_notes, 'notes'),
            $mail_button(lang('Open the request'), '{{page:subscription_view}}?r=^^reference_code^^'),
            $mail_reference(),
        )),
    ),
    'email_subscription_client' => $record_widget('email_subscription_received', 'subscribe', lang('This subscription request was not found.'), 'public') + array(
        'tree' => $widget_root(array(
            $mail_heading(lang('We have your subscription request')),
            $mail_greeting(),
            $mail_para(lang('Thank you. We confirm the first delivery and the price shortly, on the request\'s page and by e-mail. Nothing is paid now.')),
            $mail_subscription(),
            $mail_para(lang('Another day or a new address? Write to us on the request\'s page.')),
            $mail_button(lang('Open my request'), '{{page:subscription_view}}?r=^^reference_code^^'),
            $mail_new_account(),
        )),
    ),
);

return array('pages' => $enquiry_pages, 'widgets' => $enquiry_widgets);
