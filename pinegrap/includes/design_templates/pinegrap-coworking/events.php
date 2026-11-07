<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Coworking", its community events and the event
 * sign-ups: the staff page that adds an event (the Events form, a
 * collection, with the sample events written in when it is made), the list
 * of the coming events with its topic filter, the event's own page and the
 * list the home page places; the sign-up, its own page with its messages,
 * My Sign-ups and the staff's list, and the e-mails of the sign-up.
 * Included after parts.php, mail.php, spaces.php and requests.php in the
 * same scope (their request pieces are reused here); returns
 * array('pages' => ..., 'widgets' => ...).
 *
 * An event is a record of the Events form. Its date is a date field, so
 * the lists keep only the events from today on, the nearest first, and an
 * event drops out of them once its day has passed. Its topic is a pick
 * list whose answers are the words parts.php gives, so a link with ?topic=
 * filters the list by the words a visitor sees. The sample events are
 * dated from the day the template is opened; no date is fixed in a tree.
 *
 * A sign-up is a request, never a held seat: a record of the Event
 * Sign-ups form. The template keeps no capacity, so the front desk answers
 * each sign-up and sets its office-use-only Status; its answers are the
 * page's comments, mailed to the visitor and to the space. The event
 * starts with what the event page sends (?event=, the event's title).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

$event_page_list = array();

// ── Pieces ──────────────────────────────────────────────────────────────

// The day of a sample event: $in_days after today, moved on to the
// weekday its title needs ('weekday', ISO 1 = Monday), else past a
// weekend, so every event is still to come and falls on a staffed day.
$event_day = function (array $e) {
    $day = new DateTime('today');
    $day->modify('+' . (int)$e['in_days'] . ' days');
    if (isset($e['weekday'])) {
        while ((int)$day->format('N') !== (int)$e['weekday']) $day->modify('+1 day');
    } else {
        while ((int)$day->format('N') > 5) $day->modify('+1 day');
    }
    return $day->format('Y-m-d');
};

// How long an event lasts and what it costs, in words.
$event_duration = function (array $e) {
    return sprintf(lang('%d minutes'), $e['minutes']);
};
$event_price = function (array $e) {
    return $e['free'] ? lang('Free for members and their guests') : lang('Free for members; guests pay at the door');
};

// A link to a template page that sends values of the record it is drawn
// in: $params maps the address name to the field it takes (the renderer's
// __page_link).
$event_page_link = function ($page_key, $params) {
    $list = array();
    foreach ($params as $name => $token) $list[] = array('name' => $name, 'token' => $token);
    return array(
        '_bindings'   => array('href' => '__page_link'),
        '_bindParams' => array('href' => array('page_id' => '{{tab:' . $page_key . '}}', 'params' => $list)),
    );
};

// A list of events: the Events form, the event page, the coming events
// only (the date from today on), the nearest first.
$event_list_config = function ($more) {
    $filters = array(
        array('field' => 'event_date', 'operator' => 'is greater than or equal to', 'value' => '', 'dynamic_value' => 'current date'),
    );
    return array_merge(array(
        'regionType'          => 'form_list_view',
        'custom_form_page_id' => '{{tab:event_new}}',
        'detail_page_id'      => '{{tab:event}}',
        'order_by_field'      => 'event_date',
        'order_by_direction'  => 'ASC',
        'empty_message'       => lang('No events planned right now. Subscribe to the newsletter to hear about the next ones.'),
    ), $more, array('filters' => array_merge($filters, $more['filters'] ?? array())));
};
$event_grid = function () use ($widget_root, $row, $loop, $event_card) {
    return $widget_root(array($row(array($loop(array($event_card()))), '4')));
};

// The links above the event list that narrow it: one per topic.
$event_filter_bar = function () use ($el, $link, $span, $query, $topic_titles) {
    $children = array($span(lang('Topic'), 'small fw-semibold text-body-secondary me-1', lang('Label')));
    $children[] = $link(lang('All'), '{{page:events}}', 'btn btn-sm btn-outline-secondary');
    foreach ($topic_titles as $v) {
        $children[] = $link($v, '{{page:events}}?' . $query(array('topic' => $v)), 'btn btn-sm btn-outline-secondary');
    }
    return $el('nav', 'd-flex flex-wrap align-items-center gap-2 mb-4', lang('Filters'), $children);
};

// The sample sign-up the canvas shows.
$guest_counts = array(lang('Just me'), lang('Me and one guest'), lang('Me and two guests'));
$member_choices = array(lang('I am a member'), lang('Not a member yet'));
$sample_event_key = 'talk';
$sample_event = $events[$sample_event_key];
$sample_signup_event = $sample_event['title'];
$sample_guests = $guest_counts[1];
$sample_signup_notes = lang('I would like to bring a colleague who is thinking about a desk here.');

// ── Pages ───────────────────────────────────────────────────────────────

// The coming events, narrowed by topic from the address.
$event_page_list[] = array(
    'key'              => 'events',
    'name'             => lang('events'),
    'folder'           => 'public',
    'title'            => lang('Community Events'),
    'meta_description' => lang('Breakfasts, talks, office hours and Friday drinks at the coworking space: the coming events and how to sign up.'),
    'tree'             => $page(array(
        $page_header(lang('Community Events'), lang('Breakfasts, talks and evenings with the people who work here. Members come free; sign up from the event\'s page.')),
        $el('section', 'py-5', lang('Events'), array(
            $container(array($event_filter_bar(), $widget('events_list'))),
        ), $searchable('cw-events', lang('events, community, talk, workshop, breakfast, drinks, networking, meetup'))),
    )),
);

// One event: its record.
$event_page_list[] = array(
    'key'              => 'event',
    'name'             => lang('event'),
    'folder'           => 'public',
    'title'            => lang('Event'),
    'meta_description' => lang('A community event at the coworking space: when and where it is, who it is for and how to sign up.'),
    'sitemap'          => false,
    'tree'             => $page(array(
        $widget('event_detail'),
    )),
);

// The staff page that adds an event. The form is the Events collection;
// the sample events are written in when the form is made, so the lists do
// not open empty (pg_cf_seed_sample_records()).
$sample_events = array();
foreach ($events as $key => $e) {
    $sample_events[] = array(
        'title'      => $e['title'],
        'topic'      => $event_topics[$e['topic']],
        'event_date' => $event_day($e),
        'start_time' => $e['time'],
        'duration'   => $event_duration($e),
        'place'      => $spaces[$e['space']]['name'],
        'price'      => $event_price($e),
        'summary'    => $e['summary'],
        'photo'      => $photo('event-' . $key, 1200, 675),
    );
}
$event_page_list[] = array(
    'key'     => 'event_new',
    'name'    => lang('new-event'),
    'folder'  => 'private',
    'title'   => lang('New Event'),
    'search'  => false,
    'sitemap' => false,
    'noindex' => true,
    'form'    => array(
        'form_name'            => lang('Events'),
        'collection'           => 1,
        'confirmation_message' => lang('The event is published.'),
        'confirmation_page_id' => '{{tab:events}}',
        'sample_records'       => $sample_events,
        // The photo is the event's share image (pg_cf_share_image_field_id()).
        'share_image_field'    => 'photo',
    ),
    'tree'    => $staff_page(array(
        $page_header(lang('New Event'), lang('The event appears on the events page and the home page until its day has passed.')),
        $el('section', 'py-5', lang('Content'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $el('div', 'p-4 rounded-3 border', lang('Form Card'), array($widget('event_form'))),
                    ), '', '8'),
                ), '', 'center'),
            )),
        )),
    )),
);

// The sign-up. What is sent opens the sign-up's own page.
$event_page_list[] = array(
    'key'              => 'event_signup',
    'name'             => lang('event-sign-up'),
    'folder'           => 'public',
    'title'            => lang('Sign Up for an Event'),
    'meta_description' => lang('Sign up for a community event at the coworking space: the front desk confirms your place by e-mail and on your sign-up\'s page.'),
    'form'             => array(
        'form_name'            => lang('Event Sign-ups'),
        'confirmation_message' => lang('Thank you, your sign-up has reached us. The front desk confirms your place within a day.'),
        'confirmation_page_id' => '{{tab:signup_view}}',
        'auto_registration'    => 1,
        'notify_email'         => '{{site_email}}',
        'notify_subject'       => lang('New event sign-up: ^^event^^'),
        'notify_page_id'       => '{{tab:email_signup_new}}',
        'confirm_email'        => 1,
        'confirm_subject'      => lang('We received your event sign-up'),
        'confirm_page_id'      => '{{tab:email_signup_received}}',
    ),
    'tree'             => $request_page(lang('Sign Up for an Event'),
        lang('Choose the event and who comes with you. Members come free; the front desk confirms your place within a day.'),
        'signup_form',
        $request_aside(array(
            lang('Your sign-up opens its own page, and an account for you if you have none.'),
            lang('The front desk confirms your place there within a day, or tells you when the event is full.'),
            lang('Come a few minutes early and say your name at the front desk.'),
            lang('Cannot come? Write to us on the sign-up\'s page so the place goes to someone else.'),
        ), lang('See all events'), '{{page:events}}')),
);

// One sign-up: the sign-up, its status and the messages.
$event_page_list[] = $request_view('signup_view', lang('event-sign-up-request'), lang('Event Sign-up'),
    'signup_detail', 'signup_messages', lang('There is a new message about your event sign-up'));

// My Sign-ups: the visitor's own sign-ups (for the staff, everybody's).
$event_page_list[] = array(
    'key'     => 'my_signups',
    'name'    => lang('my-sign-ups'),
    'folder'  => 'registration',
    'title'   => lang('My Sign-ups'),
    'search'  => false,
    'sitemap' => false,
    'tree'    => $page(array(
        $page_header(lang('My Sign-ups'), lang('Your event sign-ups, their status and the front desk\'s answers, newest first.')),
        $el('section', 'py-5', lang('Content'), array(
            $container(array(
                $row(array(
                    $col(array($widget('my_signups_list')), '', '8'),
                    $col(array(
                        $el('div', 'p-4 rounded-3 bg-body-tertiary', lang('New Request'), array(
                            $el('p', 'd-flex align-items-center gap-2 fw-semibold mb-2', lang('Title'), array(
                                $icon('bi-calendar-plus', 'text-primary'),
                                $span(lang('Another event?'), '', lang('Text')),
                            )),
                            $para(lang('Choose one from the events; your details are filled in for you.'), 'small text-body-secondary mb-3'),
                            $link(lang('See all events'), '{{page:events}}', 'btn btn-sm btn-primary px-3'),
                        )),
                    ), '', '4'),
                ), '4', '', 'g-lg-5'),
            )),
        )),
    ), false),
);

$event_page_list[] = $staff_list_page('staff_signups', lang('all-event-sign-ups'), lang('Event Sign-ups'),
    lang('Every event sign-up, newest first: confirm the place, set the status and write to the visitor under each.'),
    'staff_signups_list');

// The e-mails: what the front desk receives and the reply to the sender.
$event_page_list[] = array_merge($mail_settings, array(
    'key'   => 'email_signup_new',
    'name'  => lang('email-new-event-sign-up'),
    'title' => lang('New Event Sign-up E-mail'),
    'tree'  => $mail_page(array($widget('email_signup_admin'))),
));
$event_page_list[] = array_merge($mail_settings, array(
    'key'   => 'email_signup_received',
    'name'  => lang('email-event-sign-up-received'),
    'title' => lang('Event Sign-up Received E-mail'),
    'tree'  => $mail_page(array($widget('email_signup_client'))),
));

// ── Widgets ─────────────────────────────────────────────────────────────

// The sign-up's event, who comes and membership, for its e-mails.
$mail_signup = function () use ($mail_box, $mail_line, $span, $bind, $sample_signup_event, $sample_guests, $member_choices, $contact_choices) {
    return $mail_box(array(
        $mail_line(lang('Event'), array($span($sample_signup_event, '', lang('Event'), $bind('event')))),
        $mail_line(lang('Who Comes'), array($span($sample_guests, '', lang('Who Comes'), $bind('guests')))),
        $mail_line(lang('Membership'), array($span($member_choices[0], '', lang('Membership'), $bind('member')))),
        $mail_line(lang('Reply by'), array($span($contact_choices[0][1], '', lang('Reply by'), $bind('reply_by')))),
    ), lang('Event Sign-up'));
};
$signup_row = function ($staff = false) use ($el, $icon, $span, $bind, $request_row, $sample_signup_event, $sample_guests, $signup_statuses) {
    return $request_row($staff, $sample_signup_event, 'event', lang('Event'), $signup_statuses[0], array(
        $el('span', 'd-inline-flex align-items-center gap-1', lang('Who Comes'), array(
            $icon('bi-people'), $span($sample_guests, '', lang('Who Comes'), $bind('guests')),
        )),
    ));
};
$signup_missing = lang('This event sign-up could not be found, or it is not yours to see.');

$event_widgets = array(

    // The staff's Events form: one event.
    'event_form' => array(
        'page'   => 'event_new',
        'slug'   => 'form',
        'config' => array('regionType' => 'custom_form', 'form_source' => 'page'),
        'tree'   => $widget_root(array(
            $form_step(1, lang('The event')),
            $field('cw-event-title', lang('Event Title'), pg_cf_with_role($input('cw-event-title', 'text', 'title', 'off'), 'title')),
            $row(array(
                $col(array($field('cw-event-topic', lang('Topic'), pg_cf_with_role($select('cw-event-topic', 'topic', lang('Choose a topic…'), $topic_titles), 'category'))), '6'),
                $col(array($field('cw-event-place', lang('Place'), $select('cw-event-place', 'place', lang('Choose a workspace…'), $space_names))), '6'),
            ), '3'),
            $row(array(
                $col(array($field('cw-event-date', lang('Date'), $input('cw-event-date', 'date', 'event_date', 'off'), 'mb-3',
                    lang('The event leaves the lists the day after.'))), '4'),
                $col(array($field('cw-event-time', lang('Start Time'), $input('cw-event-time', 'text', 'start_time', 'off'), 'mb-3',
                    sprintf(lang('For example %s.'), $sample_event['time']))), '4'),
                $col(array($field('cw-event-duration', lang('Duration'), $input('cw-event-duration', 'text', 'duration', 'off', false), 'mb-3',
                    sprintf(lang('For example %s.'), $event_duration($sample_event)))), '4'),
            ), '3'),
            $field('cw-event-price', lang('Price'), $input('cw-event-price', 'text', 'price', 'off', false), 'mb-3',
                sprintf(lang('In words, for example %s.'), $event_price($sample_event))),
            $form_step(2, lang('Words')),
            $field('cw-event-summary', lang('Summary'), pg_cf_with_role($textarea('cw-event-summary', 'summary', 2), 'description'), 'mb-3',
                lang('One or two lines for the cards of the lists.')),
            $field('cw-event-details', lang('What Happens'), $textarea('cw-event-details', 'details', 5, false), 'mb-3', lang('One point on each line.')),
            $form_step(3, lang('Photo')),
            $field('cw-event-photo', lang('Photo'), pg_cf_with_role($file('cw-event-photo', 'photo', 'public', 'image/*'), 'media'), 'mb-3',
                lang('A wide photo works best; it is also the share image.')),
            $submit(lang('Publish')),
            $loop(array()),
        )),
    ),

    // The coming events, filtered by the address (topic).
    'events_list' => array(
        'page'   => 'events',
        'slug'   => lang('form-list'),
        'config' => $event_list_config(array(
            'items_per_page' => 12,
            // Left out while ?topic= is not in the address.
            'filters'        => array(array('field' => 'topic', 'operator' => 'is equal to', 'value' => '',
                                            'dynamic_value' => 'url parameter', 'dynamic_value_attribute' => 'topic')),
        )),
        'tree'   => $event_grid(),
    ),

    // The home page's next three events.
    'home_events' => array(
        'page'   => 'home',
        'slug'   => lang('coming-events'),
        'config' => $event_list_config(array(
            'items_per_page' => 3,
            'max_results'    => 3,
        )),
        'tree'   => $event_grid(),
    ),

    // The sign-up: the fields are the page's form. The event starts with
    // what the event page sends (?event=, the event's title): a text
    // field, so an event the space adds later is taken as well. The Status
    // is the front desk's (office use only): every sign-up starts as
    // Request received.
    'signup_form' => array(
        'page'   => 'event_signup',
        'slug'   => 'form',
        'config' => array('regionType' => 'custom_form', 'form_source' => 'page'),
        'tree'   => $widget_root(array(
            $form_step(1, lang('Your event')),
            $field('cw-su-event', lang('Event'), $input('cw-su-event', 'text', 'event', 'off', true, array('url_param' => 'event')), 'mb-3',
                lang('Filled in from the event\'s page; write the event\'s name if it is empty.')),
            $row(array(
                $col(array($field('cw-su-guests', lang('Who Comes'), $select('cw-su-guests', 'guests', '', $guest_counts, true, $guest_counts[0]))), '6'),
                $col(array($field('cw-su-member', lang('Membership'), $select('cw-su-member', 'member', '', $member_choices, true, $member_choices[0]))), '6'),
            ), '3'),
            $field('cw-su-notes', lang('Notes'), $textarea('cw-su-notes', 'notes', 3, false), 'mb-3',
                lang('Food you do not eat, access needs, a question for the speaker.')),
            $form_step(2, lang('How to reach you')),
            $contact_fields('cw-su'),
            $radios('cw-su-reply', 'reply_by', lang('Reply by'), $contact_choices, $contact_choices[0][0]),
            $field('cw-su-status', lang('Status'), $select('cw-su-status', 'status', '', $signup_statuses, false, $signup_statuses[0], array('office_use_only' => true))),
            $agree('cw-su-consent', 'consent', lang('I agree that the coworking space keeps these details to answer my sign-up and keep my place, as its privacy notice explains.'), true),
            $captcha(),
            $el('div', 'd-flex flex-wrap align-items-center gap-3', lang('Send Area'), array(
                $submit(lang('Sign Up'), 'btn btn-primary btn-lg px-4'),
                $para(lang('The front desk confirms your place within a day.'), 'small text-body-secondary mb-0'),
            )),
            $loop(array()),
        )),
    ),

    // One sign-up; the front desk's answers follow as comments.
    'signup_detail' => $detail_widget('signup_view', 'event_signup', $signup_missing, 'submitter_only') + array(
        'tree' => $request_detail(lang('My Sign-ups'), 'my_signups', $signup_missing, lang('Event sign-up not found'),
            lang('Event Sign-up'), $sample_signup_event, 'event', $signup_statuses[0],
            lang('Thank you, your sign-up has reached us. The front desk confirms your place here within a day; this page shows the status, and you can write to us below.'),
            array_merge(
                $detail(lang('Who Comes'), '', lang('Who Comes'), $bound($sample_guests, 'guests')),
                $detail(lang('Membership'), '', lang('Membership'), $bound($member_choices[0], 'member')),
                $detail(lang('Reply by'), '', lang('Reply by'), $bound($contact_choices[0][1], 'reply_by')),
                $detail(lang('Name'), '', lang('Name'), $member_name(), true),
                $detail(lang('Reference Code'), 'mb-0 font-monospace', lang('Reference Code'), $bound('ABC123', 'reference_code'))
            ),
            $sample_signup_notes,
            lang('A guest more, a question or cannot come? Write to us below; we answer here and by e-mail.')),
    ),

    'signup_messages' => $request_messages('signup_view',
        lang('No messages yet. The front desk\'s answer comes here; you can write to us about the event too.')),

    'my_signups_list' => $request_list('my_signups', 'event_signup', 'signup_view', $signup_row, array(), '',
        lang('No event sign-ups yet. Choose an event and sign up.')),

    'staff_signups_list' => $request_list('staff_signups', 'event_signup', 'signup_view', $signup_row,
        array('first_name', 'last_name', 'email', 'event', 'member', 'status'),
        lang('Search by name, e-mail, event, membership or status...'), lang('No event sign-ups yet.')),

    // The e-mail pages' widgets: drawn for the submission just sent.
    'email_signup_admin' => $detail_widget('email_signup_new', 'event_signup', lang('The event sign-up could not be found.'), 'public') + array(
        'tree' => $widget_root(array(
            $mail_heading(lang('A new event sign-up')),
            $mail_para(lang('It came from the event sign-up form. Confirm the place under the sign-up on its page, or say the event is full, and set the status; what you write there reaches the visitor by e-mail.')),
            $mail_signup(),
            $mail_sender($sample_signup_notes),
            $mail_button(lang('Open the sign-up'), '{{page:signup_view}}?r=^^reference_code^^'),
            $mail_reference(),
        )),
    ),
    'email_signup_client' => $detail_widget('email_signup_received', 'event_signup', lang('The event sign-up could not be found.'), 'public') + array(
        'tree' => $widget_root(array(
            $mail_heading(lang('We received your event sign-up')),
            $mail_greeting(),
            $mail_para(lang('Thank you. The front desk confirms your place within a day, on the sign-up\'s page and by e-mail. Come a few minutes early and say your name at the front desk.')),
            $mail_signup(),
            $mail_para(lang('Cannot come? Write to us on the sign-up\'s page so the place goes to someone else.')),
            $mail_button(lang('Open my sign-up'), '{{page:signup_view}}?r=^^reference_code^^'),
            $mail_new_account(),
        )),
    ),
);

// One event: the topic, the title, the summary, its facts, the photo, what
// happens and beside them the way to sign up, and the other events of the
// same topic.
$event_not_found = lang('This event could not be found. It may have passed or been taken off the list.');
$event_fact = function ($icon_name, $name, $sample, $token) use ($el, $icon, $span, $bind) {
    return $el('li', 'd-inline-flex align-items-center gap-1', $name, array(
        $icon($icon_name, 'text-primary'),
        $span($sample, '', lang('Value'), $bind($token)),
    ));
};
$event_widgets['event_detail'] = array(
    'page'   => 'event',
    'slug'   => lang('form-detail'),
    'config' => array(
        'regionType'          => 'form_item_view',
        'custom_form_page_id' => '{{tab:event_new}}',
        'access_control'      => 'public',
        'not_found_message'   => $event_not_found,
    ),
    'tree'   => $widget_root(array(
        $container(array(
            $el('div', 'text-center py-5', lang('Not Found'), array(
                $icon('bi-calendar-x', 'display-5 text-body-tertiary'),
                $heading('h2', lang('Event not found'), 'h3 fw-semibold mt-3'),
                $para($event_not_found, 'text-body-secondary mb-4', $bind('not_found')),
                $link(lang('All Events'), '{{page:events}}', 'btn btn-primary px-4'),
            ), $show_when('record_not_found')),
            $el('article', '', lang('Event'), array(
                $el('div', 'd-flex flex-wrap align-items-center justify-content-between gap-2 mb-3', lang('Toolbar'), array(
                    $el('a', 'd-inline-flex align-items-center gap-1 small fw-semibold link-primary text-decoration-none', lang('Link'), array(
                        $icon('bi-arrow-left'),
                        $span(lang('All Events'), '', lang('Link Text')),
                    ), array('href' => '{{page:events}}')),
                    $edit_button(),
                )),
                $span($event_topics[$sample_event['topic']], 'pcw-eyebrow small text-uppercase fw-semibold text-primary d-block mb-2', lang('Topic'), $bind('topic')),
                $heading('h1', $sample_event['title'], 'pcw-display display-6 mb-2', $bind('title')),
                $para($sample_event['summary'], 'lead text-body-secondary mb-3', $bind('summary')),
                $el('ul', 'list-unstyled d-flex flex-wrap gap-3 text-body-secondary mb-4', lang('Event Facts'), array(
                    $event_fact('bi-calendar-event', lang('Date'), lang('Date'), 'event_date'),
                    $event_fact('bi-clock', lang('Start Time'), $sample_event['time'], 'start_time'),
                    $event_fact('bi-hourglass-split', lang('Duration'), $event_duration($sample_event), 'duration'),
                    $event_fact('bi-geo-alt', lang('Place'), $spaces[$sample_event['space']]['name'], 'place'),
                )),
                $row(array(
                    $col(array(
                        $optional_image('photo', 'title', 'rounded-3 object-fit-cover', 'ratio ratio-16x9 mb-4',
                            $photo('event-' . $sample_event_key, 1200, 675), lang('Photo')),
                        $el('div', 'pg-hide-if-empty mb-4', lang('What Happens'), array(
                            $heading('h2', lang('What happens'), 'pcw-display h4 mb-2'),
                            $el('div', 'pg-field-value lh-lg', lang('Value'), array(), $bound($sample_event['summary'], 'details')),
                        )),
                    ), '', '8'),
                    $col(array(
                        $el('aside', 'card border-0 bg-body-tertiary', lang('Sign-up Card'), array(
                            $el('div', 'card-body p-4', lang('Card Body'), array(
                                $heading('h2', lang('Join us'), 'pcw-display h5 mb-2'),
                                $para($event_price($sample_event), 'pcw-price fw-semibold mb-2', $bind('price')),
                                $para(lang('Sign up and the front desk confirms your place within a day.'), 'small text-body-secondary mb-3'),
                                // Opens the sign-up with this event chosen
                                // (?event=).
                                $link(lang('Sign Up'), '#', 'btn btn-primary w-100 mb-2', array(),
                                    $event_page_link('event_signup', array('event' => 'title'))),
                                // Opens the list on this event's topic (?topic=).
                                $link(lang('More events like this'), '#', 'btn btn-link w-100', array(),
                                    $event_page_link('events', array('topic' => 'topic'))),
                            )),
                        )),
                    ), '', '4'),
                ), '4', '', 'g-lg-5 mb-4'),
            ), $show_when('record_found')),
        ), 'py-5'),
    )),
);

return array('pages' => $event_page_list, 'widgets' => $event_widgets);
