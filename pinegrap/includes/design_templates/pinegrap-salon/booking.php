<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Beauty Salon", its booking: the booking form, the
 * booking's own page with its messages, My Appointments, the privacy page
 * the consent box links, the booking e-mails, and the widgets that draw
 * them (the form, the booking, the client's and the staff's lists, the two
 * e-mail views). Included after parts.php and mail.php in the same scope;
 * returns array('pages' => ..., 'widgets' => ...).
 *
 * A booking is a record of the Appointments form, as in the Clinic
 * template. A visitor without an account gets one (auto-registration), so
 * the booking's page and My Appointments show it at once. Its Status is an
 * office-use-only field the staff change in the control panel (Requested,
 * Confirmed, Completed, Cancelled); the messages under it are the page's
 * comments, mailed to the client and to the salon. The form asks for a time
 * of day, not an exact time: the staff confirm the hour.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

$booking_pages = array();

// ── Pages ───────────────────────────────────────────────────────────────

// The booking form, with what happens next and a few reminders beside it.
// What is sent opens the booking's own page.
$booking_pages[] = array(
    'key'              => 'appointment',
    'name'             => lang('book-now'),
    'folder'           => 'public',
    'title'            => lang('Book Now'),
    'meta_description' => lang('Book online: choose the service, a stylist and a time of day; we confirm your time by e-mail.'),
    'form'             => array(
        'form_name'            => lang('Appointments'),
        'confirmation_message' => lang('Thank you, your booking request has reached us. We will confirm your time by e-mail.'),
        // A visitor without an account gets one, which the booking's page
        // tells them; both sides write there as comments.
        'confirmation_page_id' => '{{tab:appointment_view}}',
        'auto_registration'    => 1,
        'notify_email'         => '{{site_email}}',
        'notify_subject'       => lang('New booking request: ^^service^^, ^^preferred_date^^'),
        'notify_page_id'       => '{{tab:email_appointment_new}}',
        'confirm_email'        => 1,
        'confirm_subject'      => lang('We received your booking request'),
        'confirm_page_id'      => '{{tab:email_appointment_received}}',
    ),
    'tree'             => $page(array(
        $page_header(lang('Book Now'), lang('Tell us what you would like and when suits you. We confirm your time by e-mail, usually within the hour.')),
        $el('section', 'py-5', lang('Content'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $el('div', 'card border-0 shadow-sm rounded-4', lang('Form Card'), array(
                            $el('div', 'card-body p-4 p-lg-5', lang('Card Body'), array($widget('appointment_form'))),
                        )),
                    ), '', '8'),
                    $col(array(
                        $el('div', 'p-4 rounded-4 bg-body-tertiary mb-4', lang('How It Works'), array(
                            $heading('h2', lang('What happens next'), 'h5 fw-semibold mb-3'),
                            $el('ol', 'small text-body-secondary ps-3 mb-0', lang('List'), array(
                                $el('li', 'mb-2', lang('Step'), array(), array('text' => lang('Your request opens its own page, and an account for you if you have none.'))),
                                $el('li', 'mb-2', lang('Step'), array(), array('text' => lang('We confirm the hour by e-mail; the page shows the status too.'))),
                                $el('li', '', lang('Step'), array(), array('text' => lang('Need to change something? Write under your booking.'))),
                            )),
                        )),
                        $el('div', 'p-4 rounded-4 border mb-4', lang('Booking Tips'), array(
                            $heading('h2', lang('Good to know'), 'h5 fw-semibold mb-3'),
                            $check_list(array(
                                lang('A first colour or lash tint needs a patch test 48 hours before.'),
                                lang('Come with clean, dry hair for colour, unless we say otherwise.'),
                                lang('Please let us know 24 hours ahead if you cannot come.'),
                            ), 'list-unstyled small mb-0'),
                        )),
                        $el('div', 'p-4 rounded-4 border', lang('Opening hours'), array(
                            $heading('h2', lang('Opening hours'), 'h5 fw-semibold mb-3'),
                            $hours(),
                        )),
                    ), '', '4'),
                ), '4', '', 'g-lg-5'),
            )),
        )),
    ), false),
);

// One booking: the request, its status and the messages under it. The
// form's confirmation page; My Appointments and the e-mails link to it.
$booking_pages[] = array(
    'key'      => 'appointment_view',
    'name'     => lang('booking'),
    'folder'   => 'registration',
    'title'    => lang('Booking'),
    'search'   => false,
    'sitemap'  => false,
    'comments' => array('label' => lang('Message'), 'allow_new' => true, 'auto_publish' => true, 'show_date' => true, 'login' => true,
                        'email_page' => '{{tab:email_reply}}', 'email_subject' => lang('There is a new message about your appointment'),
                        'notify_email' => '{{site_email}}'),
    'tree'     => $page(array(
        $widget('appointment_detail'),
        $comments_section(lang('Messages')),
    ), false),
);

// My Appointments: the client's bookings (for the staff, everybody's).
$booking_pages[] = array(
    'key'     => 'my_appointments',
    'name'    => lang('my-appointments'),
    'folder'  => 'registration',
    'title'   => lang('My Appointments'),
    'search'  => false,
    'sitemap' => false,
    'tree'    => $page(array(
        $page_header(lang('My Appointments'), lang('Your bookings, their status and our messages, newest first.')),
        $el('section', 'py-5', lang('Content'), array(
            $container(array(
                $row(array(
                    $col(array($widget('my_appointments_list')), '', '8'),
                    $col(array(
                        $el('div', 'p-4 rounded-4 bg-body-tertiary mb-4', lang('Book Again'), array(
                            $el('p', 'd-flex align-items-center gap-2 fw-semibold mb-2', lang('Title'), array(
                                $icon('bi-calendar-plus', 'text-primary'),
                                $span(lang('Time for the next visit?'), '', lang('Text')),
                            )),
                            $para(lang('Book again in two minutes; the details you gave last time are filled in for you.'), 'small text-body-secondary mb-3'),
                            $link(lang('Book Now'), '{{page:appointment}}', 'btn btn-sm btn-primary rounded-pill px-3'),
                        )),
                        $el('div', 'p-4 rounded-4 border', lang('Change or Cancel'), array(
                            $heading('h2', lang('Need to change or cancel?'), 'h6 fw-bold mb-2'),
                            $para(lang('Open the booking and write to us under it, or call us. Please let us know a day ahead, so someone else can have the time.'), 'small text-body-secondary mb-0'),
                        )),
                    ), '', '4'),
                ), '4', '', 'g-lg-5'),
            )),
        )),
    ), false),
);

// The privacy notice the consent box links to: sample text with a note to
// adapt it.
$booking_pages[] = array(
    'key'              => 'privacy',
    'name'             => lang('privacy-policy'),
    'folder'           => 'public',
    'title'            => lang('Privacy Policy'),
    'meta_description' => lang('What the salon keeps about you, why, and what you can ask of us.'),
    'tree'             => $page(array(
        $page_header(lang('Privacy Policy'), lang('What the salon keeps about you, why, and what you can ask of us.')),
        $el('section', 'py-5', lang('Your Data'), array(
            $container(array($row(array($col(array(
                $el('div', 'alert alert-secondary d-flex gap-2 small', lang('Sample Text Note'), array(
                    $icon('bi-info-circle', 'mt-1'),
                    $span(lang('Sample text: adapt it to your salon and to the law that applies to you before you publish.'), '', lang('Text')),
                ), array('_attrs' => array($attr('role', 'note')))),
                $heading('h2', lang('Your data'), 'h4 fw-semibold mb-3'),
                $para(lang('When you book or open an account, we keep your name, your contact details, your bookings and what you tell us about them, such as an allergy or the colour you had last time, to look after you on your visits.'), 'mb-3'),
                $para(lang('We keep these details only as long as we need them for that, and never sell them or give them to advertisers. You can see and change your account data from My Account, and ask us to delete it through the contact page.'), 'mb-0'),
            ), '', '8'))))),
        ), $searchable('your-data', lang('privacy, personal data, delete my data'))),
    )),
);

// The booking form's notification: what the staff receive.
$booking_pages[] = array_merge($mail_settings, array(
    'key'   => 'email_appointment_new',
    'name'  => lang('email-new-booking'),
    'title' => lang('New Booking E-mail'),
    'tree'  => $mail_page(array($widget('email_appointment_admin'))),
));
// The booking form's reply to the client.
$booking_pages[] = array_merge($mail_settings, array(
    'key'   => 'email_appointment_received',
    'name'  => lang('email-booking-received'),
    'title' => lang('Booking Received E-mail'),
    'tree'  => $mail_page(array($widget('email_appointment_client'))),
));
// A new message or reply: sent to the client of a booking, the sender of a
// conversation and the visitors watching a page. The System region carries
// the comment and a "View or Reply" button.
$booking_pages[] = array_merge($mail_settings, array(
    'key'   => 'email_reply',
    'name'  => lang('email-new-reply'),
    'title' => lang('New Reply E-mail'),
    'tree'  => $mail_page(array(
        $mail_heading(lang('There is a new message')),
        $mail_para(lang('Something new was written on a page you follow: your booking, a conversation or a post. You can read it below and answer it on the page.')),
        array('type' => 'region', 'props' => array('regionType' => 'system', 'regionName' => ''), 'children' => array()),
    )),
));

// ── Widgets ─────────────────────────────────────────────────────────────

// A booking in a list: the service, the status, the day, the time of day,
// the stylist and the number of messages, linked to its page.
$appointment_row = function ($staff = false) use ($el, $span, $icon, $bind) {
    $details = array(
        $el('span', 'd-inline-flex align-items-center gap-1', lang('Date'), array($icon('bi-calendar3'), $span('2026-11-20', 'fw-semibold', lang('Date'), $bind('preferred_date')))),
        $el('span', 'd-inline-flex align-items-center gap-1', lang('Time'), array($icon('bi-clock'), $span(lang('Afternoon (15:00–18:00)'), '', lang('Time'), $bind('preferred_time')))),
        $el('span', 'd-inline-flex align-items-center gap-1', lang('Stylist'), array($icon('bi-person'), $span(lang('No preference'), '', lang('Stylist'), $bind('stylist')))),
    );
    if ($staff) {
        $details[] = $el('span', 'd-inline-flex align-items-center gap-1', lang('Phone'), array($icon('bi-telephone'), $span('+90 555 000 00 00', '', lang('Phone'), $bind('phone'))));
        $title = $el('span', 'fw-semibold', lang('Client'), array(
            $span(lang('Jane'), '', lang('First Name'), $bind('first_name')),
            $span(' ', '', lang('Text')),
            $span(lang('Cooper'), '', lang('Last Name'), $bind('last_name')),
            $span(' · ', 'text-body-secondary', lang('Text')),
            $span(lang('Cut & Blow-dry'), 'text-body-secondary fw-normal', lang('Service'), $bind('service')),
        ));
    } else {
        $details[] = $el('span', 'd-inline-flex align-items-center gap-1', lang('Messages'), array($icon('bi-chat-left-text'), $span('0', '', lang('Reply Count'), $bind('number_of_comments'))));
        $title = $span(lang('Cut & Blow-dry'), 'fw-semibold', lang('Service'), $bind('service'));
    }
    return $el('a', 'list-group-item list-group-item-action p-3', lang('Booking'), array(
        $el('div', 'd-flex flex-wrap align-items-center justify-content-between gap-2 mb-1', lang('Top Line'), array(
            $title,
            $span(lang('Requested'), 'badge rounded-pill bg-primary-subtle text-primary-emphasis', lang('Status'), $bind('status')),
        )),
        $el('div', 'd-flex flex-wrap column-gap-3 row-gap-1 small text-body-secondary', lang('Details'), $details),
    ), array('href' => '#', '_bindings' => array('href' => 'form_item_view')));
};

// The lines of a booking, for both e-mails.
$mail_booking = function () use ($mail_box, $mail_line, $span, $bind) {
    return $mail_box(array(
        $mail_line(lang('Service'), array($span(lang('Cut & Blow-dry'), '', lang('Service'), $bind('service')))),
        $mail_line(lang('Stylist'), array($span(lang('No preference'), '', lang('Stylist'), $bind('stylist')))),
        $mail_line(lang('Preferred Date'), array($span('2026-11-20', '', lang('Date'), $bind('preferred_date')))),
        $mail_line(lang('Preferred Time'), array($span(lang('Afternoon (15:00–18:00)'), '', lang('Time'), $bind('preferred_time')))),
    ), lang('Booking'));
};

// A detail of the booking's card: the label, then the value. $value is
// either the value's props (its sample text and binding) or, with
// $children, the nodes it is made of.
$detail = function ($label, $class, $name, $value, $children = false) use ($el) {
    return array(
        $el('dt', 'col-sm-4 fw-normal text-body-secondary', lang('Label'), array(), array('text' => $label)),
        $el('dd', trim('col-sm-8 ' . $class), $name, $children ? $value : array(), $children ? array() : $value),
    );
};

$booking_widgets = array(

    // The booking request: the fields are the page's form. The Status is
    // the staff's (office use only): a visitor never sees it, and every
    // request starts as Requested.
    'appointment_form' => array(
        'page'   => 'appointment',
        'slug'   => 'form',
        'config' => array('regionType' => 'custom_form', 'form_source' => 'page'),
        'tree'   => $widget_root(array(
            $form_step(1, lang('What would you like?')),
            $row(array(
                $col(array($field('sl-book-service', lang('Service'), $select('sl-book-service', 'service', lang('Choose a service…'), $service_titles))), '6'),
                $col(array($field('sl-book-stylist', lang('Stylist'), $select('sl-book-stylist', 'stylist', '', $team_choices, true, lang('No preference')))), '6'),
            ), '3'),
            $radios('sl-book-first', 'first_visit', lang('Have you been to us before?'), array(
                array(lang('First time with us'), lang('First time with us')),
                array(lang('I have been before'), lang('I have been before')),
            ), lang('First time with us')),
            $form_step(2, lang('When suits you?')),
            $row(array(
                $col(array($field('sl-book-date', lang('Preferred Date'), $input('sl-book-date', 'date', 'preferred_date', 'off', true, array('rules' => array('min_date' => 'today'))))), '6'),
                $col(array($field('sl-book-time', lang('Preferred Time'), $select('sl-book-time', 'preferred_time', lang('Choose a time of day…'), $time_windows))), '6'),
            ), '3'),
            $form_step(3, lang('About you')),
            $row(array(
                $col(array($field('sl-book-first-name', lang('First Name'), $input('sl-book-first-name', 'text', 'first_name', 'given-name', true, array('contact_field' => 'first_name')))), '6'),
                $col(array($field('sl-book-last-name', lang('Last Name'), $input('sl-book-last-name', 'text', 'last_name', 'family-name', true, array('contact_field' => 'last_name')))), '6'),
                $col(array($field('sl-book-email', lang('Email'), $input('sl-book-email', 'email', 'email', 'email', true, array('contact_field' => 'email_address')))), '6'),
                $col(array($field('sl-book-phone', lang('Phone'), $input('sl-book-phone', 'tel', 'phone', 'tel', true, array('contact_field' => 'mobile_phone')))), '6'),
            ), '3'),
            $field('sl-book-notes', lang('Anything we should know?'), $textarea('sl-book-notes', 'notes', 4, false), 'mb-3',
                lang('An allergy, the look you have in mind or a picture from the lookbook.')),
            $field('sl-book-status', lang('Status'), $select('sl-book-status', 'status', '', array(
                lang('Requested'), lang('Confirmed'), lang('Completed'), lang('Cancelled'),
            ), false, lang('Requested'), array('office_use_only' => true))),
            $agree('sl-book-consent', 'consent', lang('I agree that the salon keeps these details to arrange my visits, as its privacy notice explains.'), true),
            $captcha(),
            $el('div', 'd-flex flex-wrap align-items-center gap-3', lang('Send Area'), array(
                $submit(lang('Request Booking'), 'btn btn-primary btn-lg rounded-pill px-4'),
                $para(lang('We confirm by e-mail, usually within the hour.'), 'small text-body-secondary mb-0'),
            )),
            $loop(array()),
        )),
    ),

    // One booking: the request, its status and what the client wrote; the
    // messages follow as comments.
    'appointment_detail' => array(
        'page'   => 'appointment_view',
        'slug'   => lang('form-detail'),
        'config' => array(
            'regionType'          => 'form_item_view',
            'custom_form_page_id' => '{{tab:appointment}}',
            'access_control'      => 'submitter_only',
            'not_found_message'   => lang('This appointment could not be found, or it is not yours to see.'),
        ),
        'tree'   => $widget_root(array(
            $container(array(
                $row(array(
                    $col(array(
                        $el('div', 'text-center py-5', lang('Not Found'), array(
                            $icon('bi-calendar-x', 'display-5 text-body-tertiary'),
                            $heading('h2', lang('Appointment not found'), 'h3 fw-semibold mt-3'),
                            $para(lang('This appointment could not be found, or it is not yours to see.'), 'text-body-secondary mb-4', $bind('not_found')),
                            $link(lang('My Appointments'), '{{page:my_appointments}}', 'btn btn-primary rounded-pill px-4'),
                        ), $show_when('record_not_found')),
                        $el('div', '', lang('Booking'), array(
                            $el('div', 'alert alert-success d-flex gap-2', lang('Sent Notice'), array(
                                $icon('bi-check-circle', 'mt-1'),
                                $span(lang('Thank you, your request has reached us. We confirm the hour by e-mail; this page shows the status, and you can write to us below.'), '', lang('Text')),
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
                                    $span(lang('My Appointments'), '', lang('Link Text')),
                                ), array('href' => '{{page:my_appointments}}')),
                                $edit_button(lang('Change Status')),
                            )),
                            $el('div', 'card border-0 shadow-sm rounded-4 mb-4', lang('Booking Card'), array(
                                $el('div', 'card-body p-4', lang('Card Body'), array(
                                    $el('div', 'd-flex flex-wrap align-items-start justify-content-between gap-2 mb-3', lang('Title'), array(
                                        $el('div', '', lang('Words'), array(
                                            $para(lang('Booking'), 'small text-uppercase fw-semibold text-primary mb-1'),
                                            $heading('h1', lang('Cut & Blow-dry'), 'h3 fw-semibold mb-0', $bind('service')),
                                        )),
                                        $span(lang('Requested'), 'badge rounded-pill bg-primary-subtle text-primary-emphasis fs-6', lang('Status'), $bind('status')),
                                    )),
                                    $el('dl', 'row small mb-0', lang('Details'), array_merge(
                                        $detail(lang('Preferred Date'), 'fw-semibold', lang('Date'), array_merge(array('text' => '2026-11-20'), $bind('preferred_date'))),
                                        $detail(lang('Preferred Time'), 'fw-semibold', lang('Time'), array_merge(array('text' => lang('Afternoon (15:00–18:00)')), $bind('preferred_time'))),
                                        $detail(lang('Stylist'), '', lang('Stylist'), array_merge(array('text' => lang('No preference')), $bind('stylist'))),
                                        $detail(lang('Client'), '', lang('Client'), array(
                                            $span(lang('Jane'), '', lang('First Name'), $bind('first_name')),
                                            $span(' ', '', lang('Text')),
                                            $span(lang('Cooper'), '', lang('Last Name'), $bind('last_name')),
                                        ), true),
                                        $detail(lang('Reference Code'), 'mb-0 font-monospace', lang('Reference Code'), array_merge(array('text' => 'ABC123'), $bind('reference_code')))
                                    )),
                                )),
                            )),
                            $el('div', 'pg-hide-if-empty p-4 rounded-4 bg-body-tertiary mb-4', lang('Notes'), array(
                                $para(lang('Anything we should know?'), 'small fw-semibold text-body-secondary mb-2'),
                                $el('div', 'pg-field-value lh-lg', lang('Value'), array(), array_merge(
                                    array('text' => lang('I would like to go a little lighter, like the caramel balayage in the lookbook.')), $bind('notes'))),
                            )),
                            $el('div', 'd-flex gap-2 small text-body-secondary', lang('Note'), array(
                                $icon('bi-chat-left-text', 'text-primary mt-1'),
                                $span(lang('A question before your visit, or need to change the time? Write to us below; we answer here and by e-mail.'), '', lang('Text')),
                            )),
                        ), $show_when('record_found')),
                    ), '', '8'),
                ), '', 'center'),
            ), 'py-5'),
        )),
    ),

    // My Appointments: the bookings the client sent (or watches); the staff
    // see them all. Newest first.
    'my_appointments_list' => array(
        'page'   => 'my_appointments',
        'slug'   => lang('form-list'),
        'config' => array(
            'regionType'              => 'form_list_view',
            'custom_form_page_id'     => '{{tab:appointment}}',
            'detail_page_id'          => '{{tab:appointment_view}}',
            'items_per_page'          => 20,
            'order_by_field'          => 'submitted_date_and_time',
            'order_by_direction'      => 'DESC',
            'viewer_filter'           => true,
            'viewer_filter_submitter' => true,
            'viewer_filter_watcher'   => true,
            'viewer_filter_editor'    => true,
            'empty_message'           => lang('No bookings yet. Book your first visit in two minutes.'),
        ),
        'tree'   => $widget_root(array(
            $el('div', 'list-group', lang('Booking List'), array(
                $loop(array($appointment_row())),
            )),
        )),
    ),

    // The staff area's coming bookings: from today on, the nearest first.
    // Placed on the staff page.
    'staff_appointments' => array(
        'page'   => 'staff',
        'slug'   => lang('form-list'),
        'config' => array(
            'regionType'          => 'form_list_view',
            'custom_form_page_id' => '{{tab:appointment}}',
            'detail_page_id'      => '{{tab:appointment_view}}',
            'items_per_page'      => 20,
            'order_by_field'      => 'preferred_date',
            'order_by_direction'  => 'ASC',
            'filters'             => array(
                array('field' => 'preferred_date', 'operator' => 'is greater than or equal to', 'value' => '', 'dynamic_value' => 'current date'),
            ),
            'search_enabled'      => true,
            'search_fields'       => array('first_name', 'last_name', 'service', 'stylist', 'status'),
            'search_width'        => 'half',
            'search_label'        => lang('Search by client, service, stylist or status...'),
            'empty_message'       => lang('No appointments from today on.'),
        ),
        'tree'   => $widget_root(array(
            $el('div', 'list-group', lang('Booking List'), array(
                $loop(array($appointment_row(true))),
            )),
        )),
    ),

    // The e-mail pages' widgets: drawn for the submission just sent.
    'email_appointment_admin' => array(
        'page'   => 'email_appointment_new',
        'slug'   => lang('form-detail'),
        'config' => array(
            'regionType'          => 'form_item_view',
            'custom_form_page_id' => '{{tab:appointment}}',
            'access_control'      => 'public',
            'not_found_message'   => lang('The booking request could not be found.'),
        ),
        'tree'   => $widget_root(array(
            $mail_heading(lang('A new booking request')),
            $mail_para(lang('It came from the booking form. Confirm the hour with the client, then set the status on the booking\'s page; what you write under it reaches the client by e-mail.')),
            $mail_booking(),
            $mail_box(array(
                $mail_line(lang('Client'), array(
                    $span(lang('Jane'), '', lang('First Name'), $bind('first_name')),
                    $span(' ', '', lang('Text')),
                    $span(lang('Cooper'), '', lang('Last Name'), $bind('last_name')),
                )),
                $mail_line(lang('Email'), array($span('jane.cooper@example.com', '', lang('Email'), $bind('email')))),
                $mail_line(lang('Phone'), array($span('+90 555 000 00 00', '', lang('Phone'), $bind('phone')))),
                $mail_line(lang('Been to us before'), array($span(lang('First time with us'), '', lang('Answer'), $bind('first_visit')))),
                $mail_line(lang('Notes'), array($span(lang('I would like to go a little lighter, like the caramel balayage in the lookbook.'), '', lang('Notes'), $bind('notes')))),
            ), lang('Client')),
            $mail_button(lang('Open the appointment'), '{{page:appointment_view}}?r=^^reference_code^^'),
            $el('p', 'small text-body-secondary mt-4 mb-0', lang('Reference'), array(
                $span(lang('Reference Code') . ': ', '', lang('Label')),
                $span('ABC123', '', lang('Reference Code'), $bind('reference_code')),
            ), $mail_style('font-size:13px;color:#6b5560;margin:24px 0 0')),
        )),
    ),
    'email_appointment_client' => array(
        'page'   => 'email_appointment_received',
        'slug'   => lang('form-detail'),
        'config' => array(
            'regionType'          => 'form_item_view',
            'custom_form_page_id' => '{{tab:appointment}}',
            'access_control'      => 'public',
            'not_found_message'   => lang('The booking request could not be found.'),
        ),
        'tree'   => $widget_root(array(
            $mail_heading(lang('We received your booking request')),
            $mail_greeting(),
            $mail_para(lang('Thank you for choosing us. We check the diary and confirm your hour by e-mail, usually within the hour.')),
            $mail_booking(),
            $mail_para(lang('Please arrive five minutes early. Need to change something? Write to us on the booking\'s page.')),
            $mail_button(lang('Open my appointment'), '{{page:appointment_view}}?r=^^reference_code^^'),
            // The account the form opened for a visitor who had none: the
            // address it signs in with, and how to choose a password (the
            // set password link is shown on the booking's page, not mailed).
            $el('div', 'border-top pt-3', lang('New Account'), array(
                $el('p', 'mb-2', lang('Sign-in Email'), array(
                    $span(lang('We opened an account for you with this address:') . ' ', '', lang('Label')),
                    $span('sample@example.com', 'fw-semibold', lang('Value'), $bind('__new_account_email')),
                ), $mail_style('margin:0 0 8px')),
                $el('p', 'mb-0', lang('Password'), array(
                    $span(lang('To choose your password, use') . ' ', '', lang('Label')),
                    $link(lang('Forgot Password'), '{{page:forgot_password}}', ''),
                ), $mail_style('margin:0')),
            ), array_merge($mail_style('border-top:1px solid #f3d9e4;padding-top:16px'), $show_when('has_new_account'))),
        )),
    ),
);

return array('pages' => $booking_pages, 'widgets' => $booking_widgets);
