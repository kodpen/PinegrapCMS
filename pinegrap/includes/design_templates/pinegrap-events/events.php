<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Events and Conferences", its events and registration:
 * the coming events and an event's own page, the staff's form for a new
 * event with the sample events, the registration form, a registration's own
 * page with its messages, My Registrations, the privacy page the consent box
 * links, the registration and reply e-mails, and the widgets that draw them.
 * Included after parts.php and mail.php in the same scope; returns
 * array('pages' => ..., 'widgets' => ...).
 *
 * An event is a record of the Events form the organiser adds in the panel,
 * as the Café's events: the lists show those from today on, so a past
 * event drops out by itself. A registration is a record of the
 * Registrations form, as the Salon's booking: a visitor without an account
 * gets one (auto-registration), so the registration's page and My
 * Registrations show it at once. Its Status is an office-use-only field the
 * staff change in the control panel (Registered, Confirmed, Waitlisted,
 * Registration cancelled, Attended); the messages under it are the page's
 * comments, mailed to the attendee and to the organiser.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

$event_pages = array();

// ── The events and their samples ────────────────────────────────────────

// The events the lists open with, spread over the weeks after the day the
// template is opened, so they are still to come: the first day after today
// with the ISO weekday given (1 = Monday), plus $weeks weeks. Each is a
// record of the Events form, edited or deleted like any other.
$event_on = function ($weekday, $weeks = 0) {
    $day = new DateTime('today');
    do {
        $day->modify('+1 day');
    } while ((int)$day->format('N') !== $weekday);
    if ($weeks > 0) $day->modify('+' . (7 * $weeks) . ' days');
    return $day->format('Y-m-d');
};
$event_kinds = array(lang('Meetup'), lang('Workshop'), lang('Webinar'), lang('Conference'));
$sample_events = array(
    array('title' => lang('Product Night: Design Systems in Practice'), 'event_date' => $event_on(4), 'event_time' => '18:30', 'kind' => lang('Meetup'),
          'location' => lang('Galata Co-working, Istanbul'), 'price' => lang('Free'),
          'summary' => lang('Two short talks on design systems that teams actually use, then pizza and questions.'), 'image' => $photo('event-meetup', 800, 450)),
    array('title' => lang('Writing Release Notes People Read'), 'event_date' => $event_on(2, 1), 'event_time' => '17:00', 'kind' => lang('Webinar'),
          'location' => lang('Online'), 'price' => lang('Free'),
          'summary' => lang('A one-hour webinar on release notes that bring users back, with examples to copy.'), 'image' => $photo('event-webinar', 800, 450)),
    array('title' => lang('Hands-on: Your First Product Analytics Dashboard'), 'event_date' => $event_on(6, 1), 'event_time' => '10:00', 'kind' => lang('Workshop'),
          'location' => lang('Harbour Congress Centre, Workshop Room'), 'price' => $money(array('USD' => 59, 'EUR' => 55, 'GBP' => 49, 'TRY' => 2399)),
          'summary' => lang('A half-day workshop: bring a laptop and leave with a dashboard your team will open every morning.'), 'image' => $photo('event-workshop', 800, 450)),
    array('title' => lang('Engineering Leaders Breakfast'), 'event_date' => $event_on(3, 2), 'event_time' => '08:30', 'kind' => lang('Meetup'),
          'location' => lang('Quayside Hotel, Istanbul'), 'price' => $money(array('USD' => 25, 'EUR' => 23, 'GBP' => 19, 'TRY' => 800)),
          'summary' => lang('Coffee, a long table and one question to talk about: how do you grow a team without slowing it down?'), 'image' => $photo('event-breakfast', 800, 450)),
    array('title' => lang('Ask the Speakers: Live Q&A'), 'event_date' => $event_on(4, 3), 'event_time' => '19:00', 'kind' => lang('Webinar'),
          'location' => lang('Online'), 'price' => lang('Free'),
          'summary' => lang('Three of this year\'s speakers answer your questions before the conference.'), 'image' => $photo('event-qa', 800, 450)),
    array('title' => lang('The Annual Conference'), 'event_date' => $event_on(2, 6), 'event_time' => '09:00', 'kind' => lang('Conference'),
          'location' => $venue['name'], 'price' => sprintf(lang('Passes from %s'), $passes['day']['price']),
          'summary' => lang('Two days, four tracks and the evening reception. The programme and the speakers are on their own pages.'), 'image' => $photo('hero', 800, 450)),
);

// The answers of the registration form's pick lists. Each answer's value is
// its own words, so the e-mails and the lists read as the attendee chose.
$pass_choices = array();
foreach ($passes as $p) $pass_choices[] = $p['title'];
$pass_choices[] = lang('Free event (no pass needed)');
$diets = array(lang('No requirements'), lang('Vegetarian'), lang('Vegan'), lang('Gluten-free'), lang('Other (tell us below)'));
// The first sample event's day: the sample text the cards show where the
// record's own date is drawn, so no fixed date is written into a page.
$sample_day = new DateTime($sample_events[0]['event_date']);
$statuses = array(lang('Registered'), lang('Confirmed'), lang('Waitlisted'), lang('Registration cancelled'), lang('Attended'));

// An event of the lists: its photo with the date on a leaf over it, the
// kind, the title (the link to its page), when and where, the words, the
// price and the way to register for it.
$event_card = function () use ($el, $span, $para, $icon, $col, $image, $bind, $bind_date, $attr, $link, $page_link, $sample_day) {
    return $col(array(
        $el('article', 'card h-100 border-0 shadow-sm overflow-hidden pev-lift', lang('Event Card'), array(
            $el('div', 'position-relative', lang('Picture Area'), array(
                $el('div', 'ratio ratio-16x9 bg-body-tertiary', lang('Picture Frame'), array(
                    $image('https://picsum.photos/seed/pinegrap-events-event-meetup/800/450', lang('Product Night: Design Systems in Practice'), 'object-fit-cover',
                        array('_bindings' => array('src' => 'image', 'alt' => 'title'), '_attrs' => array($attr('loading', 'lazy')))),
                )),
                $el('div', 'position-absolute top-0 start-0 m-3 text-center rounded-3 bg-body text-body px-3 py-2 shadow-sm', lang('Date Leaf'), array(
                    $span($sample_day->format('d'), 'd-block fs-4 fw-bold lh-1', lang('Day'), $bind_date('event_date', 'd')),
                    $span($sample_day->format('m.Y'), 'd-block small fw-semibold text-primary mt-1', lang('Month'), $bind_date('event_date', 'm.Y')),
                )),
            )),
            $el('div', 'card-body d-flex flex-column p-4', lang('Card Body'), array(
                $span(lang('Meetup'), 'badge rounded-pill bg-primary-subtle text-primary-emphasis align-self-start mb-2', lang('Kind'), $bind('kind')),
                $el('h3', 'h5 fw-bold mb-2', lang('Event Title'), array(
                    $link(lang('Product Night: Design Systems in Practice'), '#', 'link-body-emphasis text-decoration-none', array(),
                        array('_bindings' => array('text' => 'title', 'href' => 'form_item_view'))),
                )),
                $el('p', 'd-flex flex-wrap align-items-center column-gap-3 row-gap-1 small text-body-secondary mb-2', lang('When and Where'), array(
                    $el('span', 'd-inline-flex align-items-center gap-1', lang('Time'), array($icon('bi-clock'), $span('18:30', '', lang('Time'), $bind('event_time')))),
                    $el('span', 'd-inline-flex align-items-center gap-1', lang('Location'), array($icon('bi-geo-alt'), $span(lang('Galata Co-working, Istanbul'), '', lang('Location'), $bind('location')))),
                )),
                $para(lang('Two short talks on design systems that teams actually use, then pizza and questions.'), 'small mb-3', $bind('summary')),
                $el('div', 'd-flex flex-wrap align-items-center justify-content-between gap-2 mt-auto', lang('Card Footer'), array(
                    $span(lang('Free'), 'small fw-semibold', lang('Price'), $bind('price')),
                    // The registration form opens with the event filled in
                    // (?event=: the field's url_param).
                    $link(lang('Register'), '{{page:registration}}', 'btn btn-sm btn-outline-primary', array(),
                        $page_link('registration', array('event' => 'title'))),
                )),
            )),
        )),
    ), '6', '4');
};

// ── Pages ───────────────────────────────────────────────────────────────

// The coming events: records of the Events form, from today on.
$event_pages[] = array(
    'key'              => 'events',
    'name'             => lang('events'),
    'folder'           => 'public',
    'title'            => lang('Events'),
    'meta_description' => lang('Meetups, workshops and webinars between two conferences: the coming dates and how to register.'),
    'tree'             => $page(array(
        $page_header(lang('Events'), lang('Meetups, workshops and webinars all year round. Most are free; every one needs a registration so we can plan the room.')),
        $el('section', 'py-5', lang('Coming Events'), array(
            $container(array($widget('events_list'))),
        )),
        $el('section', 'py-5 bg-body-tertiary', lang('Host an Event'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $heading('h2', lang('Host a meetup with us'), 'h4 fw-bold mb-2'),
                        $para(lang('Have a room and an idea? We bring the speakers, the audience and the pizza. Tell us about your space.'), 'text-body-secondary mb-0'),
                    ), '', '8'),
                    $col(array(
                        $link(lang('Contact us'), '{{page:contact}}', 'btn btn-outline-primary px-4'),
                    ), '', '4', '', '12', 'text-lg-end'),
                ), '4', '', 'align-items-center'),
            )),
        ), $searchable('host-an-event', lang('host, venue, meetup, partner, space'))),
        $shared('register_band'),
    )),
);

// One event: the record the lists link to, open to anybody.
$event_pages[] = array(
    'key'     => 'event_view',
    'name'    => lang('event'),
    'folder'  => 'public',
    'title'   => lang('Event'),
    'search'  => false,
    'tree'    => $page(array(
        $widget('event_detail'),
        $shared('register_band'),
    )),
);

// The staff's form for a new event: it is on the lists as soon as it is
// sent, and leaves them once its day has passed.
$event_pages[] = array(
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
        // The sample events are written in when the form is made, so the
        // lists do not open empty (pg_cf_seed_sample_records()).
        'sample_records'       => $sample_events,
        'share_image_field'    => 'image',
    ),
    'tree'    => $staff_page(array(
        $page_header(lang('New Event'), lang('The event appears on the events page and the home page until its day has passed. To change or remove one, open the Events form in the control panel.')),
        $el('section', 'py-5', lang('Content'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $el('div', 'p-4 rounded-4 border', lang('Form Card'), array($widget('event_form'))),
                    ), '', '8'),
                ), '', 'center'),
            )),
        )),
    )),
);

// The registration form, with what happens next beside it. What is sent
// opens the registration's own page.
$event_pages[] = array(
    'key'              => 'registration',
    'name'             => lang('register-for-an-event'),
    'folder'           => 'public',
    'title'            => lang('Register for an Event'),
    'meta_description' => lang('Register for the conference or one of our events in two minutes; your confirmation comes by e-mail.'),
    'form'             => array(
        'form_name'            => lang('Registrations'),
        'confirmation_message' => lang('Thank you, your registration has reached us. Your confirmation comes by e-mail.'),
        // A visitor without an account gets one, which the registration's
        // page tells them; both sides write there as comments.
        'confirmation_page_id' => '{{tab:registration_view}}',
        'auto_registration'    => 1,
        'notify_email'         => '{{site_email}}',
        'notify_subject'       => lang('New registration: ^^event^^ (^^pass^^)'),
        'notify_page_id'       => '{{tab:email_registration_new}}',
        'confirm_email'        => 1,
        'confirm_subject'      => lang('We received your registration'),
        'confirm_page_id'      => '{{tab:email_registration_received}}',
    ),
    'tree'             => $page(array(
        $page_header(lang('Register for an Event'), lang('Tell us which event and pass, and who is coming. Your confirmation comes by e-mail, usually within a working day.')),
        $el('section', 'py-5', lang('Content'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $el('div', 'card border-0 shadow-sm rounded-4', lang('Form Card'), array(
                            $el('div', 'card-body p-4 p-lg-5', lang('Card Body'), array($widget('registration_form'))),
                        )),
                    ), '', '8'),
                    $col(array(
                        $el('div', 'p-4 rounded-4 bg-body-tertiary mb-4', lang('How It Works'), array(
                            $heading('h2', lang('What happens next'), 'h5 fw-bold mb-3'),
                            $el('ol', 'small text-body-secondary ps-3 mb-0', lang('List'), array(
                                $el('li', 'mb-2', lang('Step'), array(), array('text' => lang('Your registration opens its own page, and an account for you if you have none.'))),
                                $el('li', 'mb-2', lang('Step'), array(), array('text' => lang('We confirm by e-mail; the page shows the status too.'))),
                                $el('li', '', lang('Step'), array(), array('text' => lang('Need to change something? Write under your registration.'))),
                            )),
                        )),
                        $el('div', 'p-4 rounded-4 border', lang('Paying for a Pass'), array(
                            $heading('h2', lang('Paying for a pass'), 'h5 fw-bold mb-3'),
                            $para(lang('Buy a pass in the ticket shop and register here with the same e-mail, or choose a pass here and we send your company an invoice.'), 'small text-body-secondary mb-3'),
                            $link(lang('See the passes'), '{{page:tickets}}', 'btn btn-sm btn-outline-primary px-3'),
                        )),
                    ), '', '4'),
                ), '4', '', 'g-lg-5'),
            )),
        )),
    ), false),
);

// One registration: the details, its status and the messages under it.
// The form's confirmation page; My Registrations and the e-mails link to
// it.
$event_pages[] = array(
    'key'      => 'registration_view',
    'name'     => lang('registration'),
    'folder'   => 'registration',
    'title'    => lang('My Registration'),
    'search'   => false,
    'sitemap'  => false,
    'comments' => array('label' => lang('Message'), 'allow_new' => true, 'auto_publish' => true, 'show_date' => true, 'login' => true,
                        'email_page' => '{{tab:email_reply}}', 'email_subject' => lang('There is a new message about your registration'),
                        'notify_email' => '{{site_email}}'),
    'tree'     => $page(array(
        $widget('registration_detail'),
        $comments_section(lang('Messages'), 'registration_messages'),
    ), false),
);

// My Registrations: the attendee's registrations (for the staff,
// everybody's).
$event_pages[] = array(
    'key'     => 'my_registrations',
    'name'    => lang('my-registrations'),
    'folder'  => 'registration',
    'title'   => lang('My Registrations'),
    'search'  => false,
    'sitemap' => false,
    'tree'    => $page(array(
        $page_header(lang('My Registrations'), lang('Your registrations, their status and our messages, newest first.')),
        $el('section', 'py-5', lang('Content'), array(
            $container(array(
                $row(array(
                    $col(array($widget('my_registrations_list')), '', '8'),
                    $col(array(
                        $el('div', 'p-4 rounded-4 bg-body-tertiary mb-4', lang('Coming Events'), array(
                            $el('p', 'd-flex align-items-center gap-2 fw-semibold mb-2', lang('Title'), array(
                                $icon('bi-calendar-plus', 'text-primary'),
                                $span(lang('Another event?'), '', lang('Text')),
                            )),
                            $para(lang('See what is coming up and register in two minutes.'), 'small text-body-secondary mb-3'),
                            $link(lang('All events'), '{{page:events}}', 'btn btn-sm btn-primary px-3'),
                        )),
                        $el('div', 'p-4 rounded-4 border', lang('Change or Cancel'), array(
                            $heading('h2', lang('Need to change or cancel?'), 'h6 fw-bold mb-2'),
                            $para(lang('Open the registration and write to us under it. Passing your place to a colleague is fine until a week before.'), 'small text-body-secondary mb-0'),
                        )),
                    ), '', '4'),
                ), '4', '', 'g-lg-5'),
            )),
        )),
    ), false),
);

// The privacy notice the consent box links to: sample text with a note to
// adapt it.
$event_pages[] = array(
    'key'              => 'privacy',
    'name'             => lang('privacy-policy'),
    'folder'           => 'public',
    'title'            => lang('Privacy Policy'),
    'meta_description' => lang('What we keep about attendees, why, and what you can ask of us.'),
    'tree'             => $page(array(
        $page_header(lang('Privacy Policy'), lang('What we keep about attendees, why, and what you can ask of us.')),
        $el('section', 'py-5', lang('Your Data'), array(
            $container(array($row(array($col(array(
                $el('div', 'alert alert-secondary d-flex gap-2 small', lang('Sample Text Note'), array(
                    $icon('bi-info-circle', 'mt-1'),
                    $span(lang('Sample text: adapt it to your events and to the law that applies to you before you publish.'), '', lang('Text')),
                ), array('_attrs' => array($attr('role', 'note')))),
                $heading('h2', lang('Your data'), 'h4 fw-bold mb-3'),
                $para(lang('When you register or open an account, we keep your name, your contact details, your company, the events you registered for and what you tell us about your needs, to run the event and look after you on the day.'), 'mb-3'),
                $para(lang('Name badges show your name and company only. We share attendee lists with sponsors only when you say yes, never sell your details, and keep them only as long as we need them. You can see and change your account data from My Account, and ask us to delete it through the contact page.'), 'mb-0'),
            ), '', '8'))))),
        ), $searchable('your-data', lang('privacy, personal data, delete my data, badge, sponsors'))),
    )),
);

// The registration form's notification: what the organiser receives.
$event_pages[] = array_merge($mail_settings, array(
    'key'   => 'email_registration_new',
    'name'  => lang('email-new-registration'),
    'title' => lang('New Registration E-mail'),
    'tree'  => $mail_page(array($widget('email_registration_admin'))),
));
// The registration form's reply to the attendee.
$event_pages[] = array_merge($mail_settings, array(
    'key'   => 'email_registration_received',
    'name'  => lang('email-registration-received'),
    'title' => lang('Registration Received E-mail'),
    'tree'  => $mail_page(array($widget('email_registration_client'))),
));
// A new message or reply: sent to the attendee of a registration, the
// sender of a conversation and the visitors watching a page. Its Comments
// widget draws the comment with its "View or Reply" button; the System
// region after it keeps the fixed comment for an e-mail the widget does
// not draw (a card edited so it lost its link to the comment).
$event_pages[] = array_merge($mail_settings, array(
    'key'   => 'email_reply',
    'name'  => lang('email-new-reply'),
    'title' => lang('New Reply E-mail'),
    'tree'  => $mail_page(array(
        $mail_heading(lang('There is a new message')),
        $mail_para(lang('Something new was written on a page you follow: your registration, a conversation or a post. You can read it below and answer it on the page.')),
        $widget('email_comment'),
        array('type' => 'region', 'props' => array('regionType' => 'system', 'regionName' => ''), 'children' => array()),
    )),
));

// ── Widgets ─────────────────────────────────────────────────────────────

// A registration in a list: the event, the status, the pass and the number
// of messages (for the staff: the attendee and the company), linked to its
// page.
$registration_row = function ($staff = false) use ($el, $span, $icon, $bind) {
    $details = array(
        $el('span', 'd-inline-flex align-items-center gap-1', lang('Pass'), array($icon('bi-ticket-perforated'), $span(lang('Conference Pass'), '', lang('Pass'), $bind('pass')))),
    );
    if ($staff) {
        $details[] = $el('span', 'd-inline-flex align-items-center gap-1', lang('Company'), array($icon('bi-building'), $span(lang('Northwind Labs'), '', lang('Company'), $bind('company'))));
        $details[] = $el('span', 'd-inline-flex align-items-center gap-1', lang('Dietary needs'), array($icon('bi-egg-fried'), $span(lang('No requirements'), '', lang('Dietary needs'), $bind('diet'))));
        $title = $el('span', 'fw-semibold', lang('Attendee'), array(
            $span(lang('Jane'), '', lang('First Name'), $bind('first_name')),
            $span(' ', '', lang('Text')),
            $span(lang('Cooper'), '', lang('Last Name'), $bind('last_name')),
            $span(' · ', 'text-body-secondary', lang('Text')),
            $span(lang('The Annual Conference'), 'text-body-secondary fw-normal', lang('Event'), $bind('event')),
        ));
    } else {
        $details[] = $el('span', 'd-inline-flex align-items-center gap-1', lang('Messages'), array($icon('bi-chat-left-text'), $span('0', '', lang('Reply Count'), $bind('number_of_comments'))));
        $title = $span(lang('The Annual Conference'), 'fw-semibold', lang('Event'), $bind('event'));
    }
    return $el('a', 'list-group-item list-group-item-action p-3', lang('Registration'), array(
        $el('div', 'd-flex flex-wrap align-items-center justify-content-between gap-2 mb-1', lang('Top Line'), array(
            $title,
            $span(lang('Registered'), 'badge rounded-pill bg-primary-subtle text-primary-emphasis', lang('Status'), $bind('status')),
        )),
        $el('div', 'd-flex flex-wrap column-gap-3 row-gap-1 small text-body-secondary', lang('Details'), $details),
    ), array('href' => '#', '_bindings' => array('href' => 'form_item_view')));
};

// The lines of a registration, for both e-mails.
$mail_registration = function () use ($mail_box, $mail_line, $span, $bind) {
    return $mail_box(array(
        $mail_line(lang('Event'), array($span(lang('The Annual Conference'), '', lang('Event'), $bind('event')))),
        $mail_line(lang('Pass'), array($span(lang('Conference Pass'), '', lang('Pass'), $bind('pass')))),
        $mail_line(lang('Attendee'), array(
            $span(lang('Jane'), '', lang('First Name'), $bind('first_name')),
            $span(' ', '', lang('Text')),
            $span(lang('Cooper'), '', lang('Last Name'), $bind('last_name')),
        )),
        $mail_line(lang('Company'), array($span(lang('Northwind Labs'), '', lang('Company'), $bind('company')))),
    ), lang('Registration'));
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

// The events lists: from today on, the nearest first.
$events_list = function ($page_key, $count) use ($widget_root, $row, $loop, $event_card) {
    $config = array(
        'regionType'          => 'form_list_view',
        'custom_form_page_id' => '{{tab:event_new}}',
        'detail_page_id'      => '{{tab:event_view}}',
        'items_per_page'      => $count,
        'order_by_field'      => 'event_date',
        'order_by_direction'  => 'ASC',
        'filters'             => array(
            array('field' => 'event_date', 'operator' => 'is greater than or equal to', 'value' => '', 'dynamic_value' => 'current date'),
        ),
        'empty_message'       => lang('No events planned right now. Subscribe to our newsletter to hear about the next ones.'),
    );
    if ($page_key === 'home') $config['max_results'] = $count;
    return array(
        'page'   => $page_key,
        'slug'   => lang('coming-events'),
        'config' => $config,
        'tree'   => $widget_root(array(
            $row(array($loop(array($event_card()))), '4'),
        )),
    );
};

$event_widgets = array(
    'home_events' => $events_list('home', 3),
    'events_list' => $events_list('events', 12),

    // One event: its picture, when and where, the words and the way to
    // register for it.
    'event_detail' => array(
        'page'   => 'event_view',
        'slug'   => lang('form-detail'),
        'config' => array(
            'regionType'          => 'form_item_view',
            'custom_form_page_id' => '{{tab:event_new}}',
            'access_control'      => 'public',
            'not_found_message'   => lang('This event could not be found. It may have been moved or removed.'),
        ),
        'tree'   => $widget_root(array(
            $container(array(
                $el('div', 'text-center py-5', lang('Not Found'), array(
                    $icon('bi-calendar-x', 'display-5 text-body-tertiary'),
                    $heading('h1', lang('Event not found'), 'h3 fw-bold mt-3'),
                    $para(lang('This event could not be found. It may have been moved or removed.'), 'text-body-secondary mb-4', $bind('not_found')),
                    $link(lang('All events'), '{{page:events}}', 'btn btn-primary px-4'),
                ), $show_when('record_not_found')),
                $el('article', '', lang('Event'), array(
                    $el('div', 'd-flex flex-wrap align-items-center justify-content-between gap-2 mb-4', lang('Toolbar'), array(
                        $el('a', 'd-inline-flex align-items-center gap-1 small fw-semibold link-primary text-decoration-none', lang('Link'), array(
                            $icon('bi-arrow-left'),
                            $span(lang('All events'), '', lang('Link Text')),
                        ), array('href' => '{{page:events}}')),
                        $edit_button(),
                    )),
                    $row(array(
                        $col(array(
                            $optional_image('image', 'title', 'object-fit-cover', 'ratio ratio-16x9 rounded-4 overflow-hidden bg-body-tertiary mb-4',
                                'https://picsum.photos/seed/pinegrap-events-event-meetup/1200/675', lang('Event picture')),
                            $span(lang('Meetup'), 'badge rounded-pill bg-primary-subtle text-primary-emphasis mb-2', lang('Kind'), $bind('kind')),
                            $heading('h1', lang('Product Night: Design Systems in Practice'), 'display-6 fw-bold mb-3', $bind('title')),
                            $el('div', 'pg-field-value lead text-body-secondary', lang('Summary'), array(), array_merge(
                                array('text' => lang('Two short talks on design systems that teams actually use, then pizza and questions.')), $bind('summary'))),
                        ), '', '8'),
                        $col(array(
                            $el('div', 'card border-0 shadow-sm rounded-4', lang('Event Card'), array(
                                $el('div', 'card-body p-4', lang('Card Body'), array(
                                    $el('dl', 'small mb-4', lang('Details'), array(
                                        $el('dt', 'd-flex align-items-center gap-2 fw-semibold', lang('Label'), array($icon('bi-calendar3', 'text-primary'), $span(lang('Date'), '', lang('Text')))),
                                        $el('dd', 'mb-3', lang('Date'), array(), array_merge(array('text' => $sample_events[0]['event_date']), $bind('event_date'))),
                                        $el('dt', 'd-flex align-items-center gap-2 fw-semibold', lang('Label'), array($icon('bi-clock', 'text-primary'), $span(lang('Starts at'), '', lang('Text')))),
                                        $el('dd', 'mb-3', lang('Time'), array(), array_merge(array('text' => '18:30'), $bind('event_time'))),
                                        $el('dt', 'd-flex align-items-center gap-2 fw-semibold', lang('Label'), array($icon('bi-geo-alt', 'text-primary'), $span(lang('Location'), '', lang('Text')))),
                                        $el('dd', 'mb-3', lang('Location'), array(), array_merge(array('text' => lang('Galata Co-working, Istanbul')), $bind('location'))),
                                        $el('dt', 'd-flex align-items-center gap-2 fw-semibold', lang('Label'), array($icon('bi-ticket-perforated', 'text-primary'), $span(lang('Price'), '', lang('Text')))),
                                        $el('dd', 'mb-0', lang('Price'), array(), array_merge(array('text' => lang('Free')), $bind('price'))),
                                    )),
                                    $link(lang('Register'), '{{page:registration}}', 'btn btn-primary w-100', array(),
                                        $page_link('registration', array('event' => 'title'))),
                                )),
                            )),
                        ), '', '4'),
                    ), '4', '', 'g-lg-5'),
                ), $show_when('record_found')),
            ), 'py-5'),
        )),
    ),

    // The staff page's events form: one event.
    'event_form' => array(
        'page'   => 'event_new',
        'slug'   => 'form',
        'config' => array('regionType' => 'custom_form', 'form_source' => 'page'),
        'tree'   => $widget_root(array(
            $field('ev-event-title', lang('Title'), pg_cf_with_role($input('ev-event-title', 'text', 'title', 'off'), 'title')),
            $row(array(
                $col(array($field('ev-event-date', lang('Date'), $input('ev-event-date', 'date', 'event_date', 'off'))), '6'),
                $col(array($field('ev-event-time', lang('Starts at'), $input('ev-event-time', 'text', 'event_time', 'off', true, array(), array($attr('placeholder', '18:30'))))), '6'),
                $col(array($field('ev-event-kind', lang('Kind'), $select('ev-event-kind', 'kind', lang('Choose…'), $event_kinds))), '6'),
                $col(array($field('ev-event-location', lang('Location'), $input('ev-event-location', 'text', 'location', 'off', true, array(), array($attr('placeholder', lang('Online')))))), '6'),
            ), '3'),
            $field('ev-event-summary', lang('Summary'), pg_cf_with_role($textarea('ev-event-summary', 'summary', 3), 'description')),
            $field('ev-event-price', lang('Price'), $input('ev-event-price', 'text', 'price', 'off', false, array(), array($attr('placeholder', lang('Free'))))),
            $field('ev-event-image', lang('Image'), pg_cf_with_role($file('ev-event-image', 'image', 'public'), 'media')),
            $submit(lang('Publish')),
            $loop(array()),
        )),
    ),

    // The registration: the fields are the page's form. The Status is the
    // staff's (office use only): a visitor never sees it, and every
    // registration starts as Registered.
    'registration_form' => array(
        'page'   => 'registration',
        'slug'   => 'form',
        'config' => array('regionType' => 'custom_form', 'form_source' => 'page'),
        'tree'   => $widget_root(array(
            $form_step(1, lang('Which event?')),
            $row(array(
                // Filled in from the event's Register button (?event=).
                $col(array($field('ev-reg-event', lang('Event'), $input('ev-reg-event', 'text', 'event', 'off', true, array('url_param' => 'event'),
                    array($attr('value', lang('The Annual Conference')))), 'mb-3', lang('Filled in when you come from an event\'s page.'))), '6'),
                $col(array($field('ev-reg-pass', lang('Pass'), $select('ev-reg-pass', 'pass', lang('Choose a pass…'), $pass_choices, true, '', array('url_param' => 'pass')))), '6'),
            ), '3'),
            $form_step(2, lang('Who is coming?')),
            $row(array(
                $col(array($field('ev-reg-first-name', lang('First Name'), $input('ev-reg-first-name', 'text', 'first_name', 'given-name', true, array('contact_field' => 'first_name')))), '6'),
                $col(array($field('ev-reg-last-name', lang('Last Name'), $input('ev-reg-last-name', 'text', 'last_name', 'family-name', true, array('contact_field' => 'last_name')))), '6'),
                $col(array($field('ev-reg-email', lang('Email'), $input('ev-reg-email', 'email', 'email', 'email', true, array('contact_field' => 'email_address')))), '6'),
                $col(array($field('ev-reg-phone', lang('Phone'), $input('ev-reg-phone', 'tel', 'phone', 'tel', false, array('contact_field' => 'mobile_phone')))), '6'),
                $col(array($field('ev-reg-company', lang('Company'), $input('ev-reg-company', 'text', 'company', 'organization', false, array('contact_field' => 'company')))), '6'),
                $col(array($field('ev-reg-job', lang('Job Title'), $input('ev-reg-job', 'text', 'job_title', 'organization-title', false))), '6'),
            ), '3'),
            $form_step(3, lang('Anything we should know?')),
            $field('ev-reg-diet', lang('Dietary needs'), $select('ev-reg-diet', 'diet', '', $diets, false, lang('No requirements'))),
            $field('ev-reg-needs', lang('Access needs or notes'), $textarea('ev-reg-needs', 'needs', 3, false), 'mb-3',
                lang('A wheelchair place, a quiet room, an invoice address or anything else.')),
            $field('ev-reg-status', lang('Status'), $select('ev-reg-status', 'status', '', $statuses, false, lang('Registered'), array('office_use_only' => true))),
            $agree('ev-reg-consent', 'consent', lang('I agree that the organiser keeps these details to run the event, as its privacy notice explains.'), true),
            $captcha(),
            $el('div', 'd-flex flex-wrap align-items-center gap-3', lang('Send Area'), array(
                $submit(lang('Register'), 'btn btn-primary btn-lg px-4'),
                $para(lang('Your confirmation comes by e-mail.'), 'small text-body-secondary mb-0'),
            )),
            $loop(array()),
        )),
    ),

    // One registration: the details, its status and what the attendee
    // wrote; the messages follow under it.
    'registration_detail' => array(
        'page'   => 'registration_view',
        'slug'   => lang('form-detail'),
        'config' => array(
            'regionType'          => 'form_item_view',
            'custom_form_page_id' => '{{tab:registration}}',
            'access_control'      => 'submitter_only',
            'not_found_message'   => lang('This registration could not be found, or it is not yours to see.'),
        ),
        'tree'   => $widget_root(array(
            $container(array(
                $row(array(
                    $col(array(
                        $el('div', 'text-center py-5', lang('Not Found'), array(
                            $icon('bi-ticket-perforated', 'display-5 text-body-tertiary'),
                            $heading('h2', lang('Registration not found'), 'h3 fw-bold mt-3'),
                            $para(lang('This registration could not be found, or it is not yours to see.'), 'text-body-secondary mb-4', $bind('not_found')),
                            $link(lang('My Registrations'), '{{page:my_registrations}}', 'btn btn-primary px-4'),
                        ), $show_when('record_not_found')),
                        $el('div', '', lang('Registration'), array(
                            $el('div', 'alert alert-success d-flex gap-2', lang('Sent Notice'), array(
                                $icon('bi-check-circle', 'mt-1'),
                                $span(lang('Thank you, your registration has reached us. We confirm it by e-mail; this page shows the status, and you can write to us below.'), '', lang('Text')),
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
                            $el('div', 'card border-0 shadow-sm rounded-4 mb-4', lang('Registration Card'), array(
                                $el('div', 'card-body p-4', lang('Card Body'), array(
                                    $el('div', 'd-flex flex-wrap align-items-start justify-content-between gap-2 mb-3', lang('Title'), array(
                                        $el('div', '', lang('Words'), array(
                                            $para(lang('Registration'), 'small text-uppercase fw-semibold text-primary mb-1'),
                                            $heading('h1', lang('The Annual Conference'), 'h3 fw-bold mb-0', $bind('event')),
                                        )),
                                        $span(lang('Registered'), 'badge rounded-pill bg-primary-subtle text-primary-emphasis fs-6', lang('Status'), $bind('status')),
                                    )),
                                    $el('dl', 'row small mb-0', lang('Details'), array_merge(
                                        $detail(lang('Pass'), 'fw-semibold', lang('Pass'), array_merge(array('text' => lang('Conference Pass')), $bind('pass'))),
                                        $detail(lang('Attendee'), '', lang('Attendee'), array(
                                            $span(lang('Jane'), '', lang('First Name'), $bind('first_name')),
                                            $span(' ', '', lang('Text')),
                                            $span(lang('Cooper'), '', lang('Last Name'), $bind('last_name')),
                                        ), true),
                                        $detail(lang('Company'), '', lang('Company'), array_merge(array('text' => lang('Northwind Labs')), $bind('company'))),
                                        $detail(lang('Dietary needs'), '', lang('Dietary needs'), array_merge(array('text' => lang('No requirements')), $bind('diet'))),
                                        $detail(lang('Reference Code'), 'mb-0 font-monospace', lang('Reference Code'), array_merge(array('text' => 'ABC123'), $bind('reference_code')))
                                    )),
                                )),
                            )),
                            $el('div', 'pg-hide-if-empty p-4 rounded-4 bg-body-tertiary mb-4', lang('Notes'), array(
                                $para(lang('Access needs or notes'), 'small fw-semibold text-body-secondary mb-2'),
                                $el('div', 'pg-field-value lh-lg', lang('Value'), array(), array_merge(
                                    array('text' => lang('Please send the invoice to our finance team at finance@example.com.')), $bind('needs'))),
                            )),
                            $el('div', 'd-flex gap-2 small text-body-secondary', lang('Note'), array(
                                $icon('bi-chat-left-text', 'text-primary mt-1'),
                                $span(lang('A question before the event, or a colleague coming in your place? Write to us below; we answer here and by e-mail.'), '', lang('Text')),
                            )),
                        ), $show_when('record_found')),
                    ), '', '8'),
                ), '', 'center'),
            ), 'py-5'),
        )),
    ),

    // The messages about a registration: the page's own Comments widget,
    // which shows them only to whoever may read the registration above it.
    // Oldest first, one after another, all on one page, never pinned. No
    // Reply, Share or follow box: the registration has two sides, its
    // attendee is e-mailed every answer and the organiser every message.
    'registration_messages' => array(
        'page'   => 'registration_view',
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
            'empty'     => lang('No messages yet. Write to us here about your registration.'),
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

    // My Registrations: the registrations the attendee sent (or watches);
    // the staff see them all. Newest first.
    'my_registrations_list' => array(
        'page'   => 'my_registrations',
        'slug'   => lang('form-list'),
        'config' => array(
            'regionType'              => 'form_list_view',
            'custom_form_page_id'     => '{{tab:registration}}',
            'detail_page_id'          => '{{tab:registration_view}}',
            'items_per_page'          => 20,
            'order_by_field'          => 'submitted_date_and_time',
            'order_by_direction'      => 'DESC',
            'viewer_filter'           => true,
            'viewer_filter_submitter' => true,
            'viewer_filter_watcher'   => true,
            'viewer_filter_editor'    => true,
            'empty_message'           => lang('No registrations yet. Find an event and register in two minutes.'),
        ),
        'tree'   => $widget_root(array(
            $el('div', 'list-group', lang('Registration List'), array(
                $loop(array($registration_row())),
            )),
        )),
    ),

    // The staff area's registrations: newest first, searchable by attendee,
    // company, event, pass or status. Placed on the staff page.
    'staff_registrations' => array(
        'page'   => 'staff',
        'slug'   => lang('form-list'),
        'config' => array(
            'regionType'          => 'form_list_view',
            'custom_form_page_id' => '{{tab:registration}}',
            'detail_page_id'      => '{{tab:registration_view}}',
            'items_per_page'      => 20,
            'order_by_field'      => 'submitted_date_and_time',
            'order_by_direction'  => 'DESC',
            'search_enabled'      => true,
            'search_fields'       => array('first_name', 'last_name', 'company', 'event', 'pass', 'status'),
            'search_width'        => 'half',
            'search_label'        => lang('Search by attendee, company, event, pass or status...'),
            'empty_message'       => lang('No registrations yet.'),
        ),
        'tree'   => $widget_root(array(
            $el('div', 'list-group', lang('Registration List'), array(
                $loop(array($registration_row(true))),
            )),
        )),
    ),

    // The e-mail pages' widgets: drawn for the submission just sent.
    'email_registration_admin' => array(
        'page'   => 'email_registration_new',
        'slug'   => lang('form-detail'),
        'config' => array(
            'regionType'          => 'form_item_view',
            'custom_form_page_id' => '{{tab:registration}}',
            'access_control'      => 'public',
            'not_found_message'   => lang('The registration could not be found.'),
        ),
        'tree'   => $widget_root(array(
            $mail_heading(lang('A new registration')),
            $mail_para(lang('It came from the registration form. Check the pass or the payment, then set the status on the registration\'s page; what you write under it reaches the attendee by e-mail.')),
            $mail_registration(),
            $mail_box(array(
                $mail_line(lang('Email'), array($span('jane.cooper@example.com', '', lang('Email'), $bind('email')))),
                $mail_line(lang('Phone'), array($span('+90 555 000 00 00', '', lang('Phone'), $bind('phone')))),
                $mail_line(lang('Job Title'), array($span(lang('Product Manager'), '', lang('Job Title'), $bind('job_title')))),
                $mail_line(lang('Dietary needs'), array($span(lang('No requirements'), '', lang('Dietary needs'), $bind('diet')))),
                $mail_line(lang('Access needs or notes'), array($span(lang('Please send the invoice to our finance team at finance@example.com.'), '', lang('Notes'), $bind('needs')))),
            ), lang('Attendee')),
            $mail_button(lang('Open the registration'), '{{page:registration_view}}?r=^^reference_code^^'),
            $el('p', 'small text-body-secondary mt-4 mb-0', lang('Reference'), array(
                $span(lang('Reference Code') . ': ', '', lang('Label')),
                $span('ABC123', '', lang('Reference Code'), $bind('reference_code')),
            ), $mail_style('font-size:13px;color:#64748b;margin:24px 0 0')),
        )),
    ),
    'email_registration_client' => array(
        'page'   => 'email_registration_received',
        'slug'   => lang('form-detail'),
        'config' => array(
            'regionType'          => 'form_item_view',
            'custom_form_page_id' => '{{tab:registration}}',
            'access_control'      => 'public',
            'not_found_message'   => lang('The registration could not be found.'),
        ),
        'tree'   => $widget_root(array(
            $mail_heading(lang('We received your registration')),
            $mail_greeting(),
            $mail_para(lang('Thank you for registering. We check the details and confirm by e-mail, usually within a working day.')),
            $mail_registration(),
            $mail_para(lang('Bring the confirmation on your phone on the day. Need to change something? Write to us on the registration\'s page.')),
            $mail_button(lang('Open my registration'), '{{page:registration_view}}?r=^^reference_code^^'),
            // The account the form opened for a visitor who had none: the
            // address it signs in with, and how to choose a password (the
            // set password link is shown on the registration's page, not
            // mailed).
            $el('div', 'border-top pt-3', lang('New Account'), array(
                $el('p', 'mb-2', lang('Sign-in Email'), array(
                    $span(lang('We opened an account for you with this address:') . ' ', '', lang('Label')),
                    $span('sample@example.com', 'fw-semibold', lang('Value'), $bind('__new_account_email')),
                ), $mail_style('margin:0 0 8px')),
                $el('p', 'mb-0', lang('Password'), array(
                    $span(lang('To choose your password, use') . ' ', '', lang('Label')),
                    $link(lang('Forgot Password'), '{{page:forgot_password}}', ''),
                ), $mail_style('margin:0')),
            ), array_merge($mail_style('border-top:1px solid #e0e7ff;padding-top:16px'), $show_when('has_new_account'))),
        )),
    ),

    // The comment of the reply e-mail, drawn for the comment the e-mail is
    // about, flat and alone. The pages that name the e-mail keep their
    // comment settings.
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

return array('pages' => $event_pages, 'widgets' => $event_widgets);
