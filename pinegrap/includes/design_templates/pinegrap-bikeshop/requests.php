<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Bike Shop", its requests: the service request of the
 * workshop and the bike fit request, each with the request's own page and
 * its messages, the rider's list and the staff's list; the booking page
 * for a fit at a free time; the privacy page the consent boxes and the
 * booking widget link; the reply e-mail of every request's messages and
 * the two e-mails of each request. Included after parts.php, cards.php and
 * mail.php in the same scope; returns array('pages' => ..., 'widgets' =>
 * ...).
 *
 * A repair and a fit are requests, never bookings: records of the Service
 * Requests and the Fit Requests forms. The workshop or the fitter answers
 * each request on its page and sets its office-use-only Status (and, for
 * a repair, the Quote, in words); the answers are the page's comments,
 * mailed to the rider and to the shop. A rider without an account gets one
 * (auto-registration), so the request's page and the rider's list show it
 * at once. The job, the bike kind and the fit package start with what a
 * card of the workshop or the fit page sends (?job=, ?bike=, ?fit=, each
 * in its list's own words). The booking page places the booking widget
 * beside the fit request for a rider who would rather pick a free time.
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

// The sample requests the canvas shows: the same answers the request
// facts of cards.php draw; the dates are counted from today.
$sample_job = $job_kinds['service']['title'];
$sample_bike = $bike_kinds['gravel']['title'];
$sample_make = 'Dust Trail Gravel';
$sample_dropoff = $ride_on(2);
$sample_service_notes = lang('The gears skip on the two smallest sprockets and the rear brake squeals. Ridden about 3,000 km since it was new.');
$sample_quote = lang('Full service, a new chain and rear brake pads; we ring you before fitting anything else.');
$sample_fit = $fit_kinds['full']['title'];
$sample_style = $riding_styles[2];
$sample_preferred = $ride_on(4);
$sample_fit_notes = lang('Numb hands after an hour and a sore lower back on longer rides. I would like to ride the Sunday hill loop in comfort.');

// ── Request pieces ──────────────────────────────────────────────────────

// The side column of a request page: what happens next as numbered steps
// on a graphite edge, then the opening hours and a link.
$request_aside = function ($steps, $link_text, $link_href) use ($el, $heading, $link, $span, $hours_list) {
    $items = array();
    foreach ($steps as $n => $text) {
        $items[] = $el('li', 'd-flex gap-3' . ($n < count($steps) - 1 ? ' mb-3' : ''), lang('Step'), array(
            $span((string)($n + 1), 'pbs-display pg-bike-num badge rounded-0 text-bg-dark flex-shrink-0', lang('Step Number')),
            $span($text, 'small', lang('Text')),
        ));
    }
    return array(
        $el('div', 'p-4 bg-body-tertiary border-start border-4 border-dark mb-4', lang('How It Works'), array(
            $heading('h2', lang('What happens next'), 'pbs-display h5 text-uppercase mb-3'),
            $el('ol', 'list-unstyled mb-0', lang('List'), $items),
        )),
        $el('div', 'p-4 border', lang('Opening Hours'), array(
            $heading('h2', lang('Opening hours'), 'pbs-display h5 text-uppercase mb-3'),
            $hours_list('mb-3'),
            $link($link_text, $link_href, 'fw-semibold link-primary text-decoration-none small'),
        )),
    );
};

// A box of the side column that points at the other way in.
$switch_box = function ($title, $text, $button, $href) use ($el, $heading, $para, $link) {
    return $el('div', 'p-4 border border-2 border-primary mb-4', $title, array(
        $heading('h2', $title, 'pbs-display h5 text-uppercase mb-2'),
        $para($text, 'small text-body-secondary mb-3'),
        $link($button, $href, 'btn btn-sm btn-outline-dark rounded-0 px-3'),
    ));
};

// A form page: the header, the form on a square card with a graphite top
// edge, and the side column.
$request_page = function ($title, $lead, $widget_key, $aside) use ($page, $page_header, $el, $container, $row, $col, $widget) {
    return $page(array(
        $page_header($title, $lead),
        $el('section', 'py-5', lang('Content'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $el('div', 'card rounded-0 border-0 border-top border-4 border-dark shadow-sm', lang('Form Card'), array(
                            $el('div', 'card-body p-4 p-lg-5', lang('Card Body'), array($widget($widget_key))),
                        )),
                    ), '', '8'),
                    $col($aside, '', '4'),
                ), '4', '', 'g-lg-5'),
            )),
        )),
    ));
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
        )),
    );
};

// The rider's own list of one kind of request, with a box to send another.
$my_list_page = function ($key, $name, $title, $lead, $widget_key, $box_icon, $box_title, $box_text, $box_button, $box_href)
    use ($page, $page_header, $el, $container, $row, $col, $widget, $icon, $span, $para, $link) {
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
                            $el('div', 'p-4 bg-body-tertiary border-start border-4 border-primary', lang('New Request'), array(
                                $el('p', 'd-flex align-items-center gap-2 fw-semibold mb-2', lang('Title'), array(
                                    $icon($box_icon, 'text-primary'),
                                    $span($box_title, '', lang('Text')),
                                )),
                                $para($box_text, 'small text-body-secondary mb-3'),
                                $link($box_button, $box_href, 'btn btn-sm btn-primary rounded-0 px-3'),
                            )),
                        ), '', '4'),
                    ), '4', '', 'g-lg-5'),
                )),
            )),
        )),
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
        'tree'    => $staff_page(array(
            $page_header($title, $lead),
            $el('section', 'py-5', lang('Content'), array(
                $container(array($widget($widget_key))),
            )),
        )),
    );
};

// The sender's name.
$visitor_name = function () use ($span, $bind) {
    return array(
        $span(lang('Jane'), '', lang('First Name'), $bind('first_name')),
        $span(' ', '', lang('Text')),
        $span(lang('Cooper'), '', lang('Last Name'), $bind('last_name')),
    );
};

// A request in a list, linked to its page, like a job ticket: its title
// and status badge, then its details. The staff's row starts with the
// sender's name and adds the phone; the rider's own adds the messages.
$request_row = function ($staff, $title_sample, $title_field, $title_name, $status, $items) use ($el, $span, $icon, $bind, $visitor_name, $status_badge) {
    if ($staff) {
        $items[] = $el('span', 'd-inline-flex align-items-center gap-1', lang('Phone'), array(
            $icon('bi-telephone'), $span('+90 555 000 00 00', '', lang('Phone'), $bind('phone')),
        ));
        $title = $el('span', 'fw-semibold', lang('Sender'), array_merge($visitor_name(), array(
            $span(' · ', 'text-body-secondary', lang('Text')),
            $span($title_sample, 'text-body-secondary fw-normal', $title_name, $bind($title_field)),
        )));
    } else {
        $items[] = $el('span', 'd-inline-flex align-items-center gap-1', lang('Messages'), array(
            $icon('bi-chat-left-text'), $span('0', '', lang('Reply Count'), $bind('number_of_comments')),
        ));
        $title = $span($title_sample, 'pbs-display text-uppercase fw-semibold', $title_name, $bind($title_field));
    }
    return $el('a', 'list-group-item list-group-item-action p-3', lang('Request'), array(
        $el('div', 'd-flex flex-wrap align-items-center justify-content-between gap-2 mb-1', lang('Top Line'), array(
            $title,
            $status_badge($status),
        )),
        $el('div', 'd-flex flex-wrap column-gap-3 row-gap-1 small text-body-secondary', lang('Details'), $items),
    ), array('href' => '#', '_bindings' => array('href' => 'form_item_view')));
};
// A detail of a list row: an icon and a bound value.
$row_item = function ($icon_name, $name, $sample, $field) use ($el, $icon, $span, $bind) {
    return $el('span', 'd-inline-flex align-items-center gap-1', $name, array(
        $icon($icon_name), $span($sample, '', $name, $bind($field)),
    ));
};

$bound = function ($sample, $field) use ($bind) {
    return array_merge(array('text' => $sample), $bind($field));
};

// A reference code line under a staff e-mail.
$mail_reference = function () use ($el, $span, $bind, $mail_style) {
    return $el('p', 'small text-body-secondary mt-4 mb-0', lang('Reference'), array(
        $span(lang('Reference Code') . ': ', '', lang('Label')),
        $span('ABC123', '', lang('Reference Code'), $bind('reference_code')),
    ), $mail_style('font-size:13px;color:#4b5563;margin:24px 0 0'));
};
// The account a form opened for a sender who had none, in the e-mail to
// the rider.
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
    ), array_merge($mail_style('border-top:1px solid #d6dce3;padding-top:16px'), $show_when('has_new_account')));
};
// The sender's lines of a staff e-mail.
$mail_sender = function ($notes_label, $notes_sample) use ($mail_box, $mail_line, $span, $bind, $visitor_name) {
    return $mail_box(array(
        $mail_line(lang('Name'), $visitor_name()),
        $mail_line(lang('Email'), array($span('jane.cooper@example.com', '', lang('Email'), $bind('email')))),
        $mail_line(lang('Phone'), array($span('+90 555 000 00 00', '', lang('Phone'), $bind('phone')))),
        $mail_line(lang('Reply by'), array($span(lang('E-mail'), '', lang('Reply by'), $bind('reply_by')))),
        $mail_line($notes_label, array($span($notes_sample, '', $notes_label, $bind('notes')))),
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
// A list of requests: the rider's own (the staff see them all), or the
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
            $el('div', 'list-group list-group-flush border-top border-bottom', lang('Request List'), array($loop(array($row($staff))))),
        )),
    );
};

// The record page of a request, a job ticket: not found, the notices of a
// request just sent, the toolbar, the ticket (eyebrow, title, status badge
// and the request's facts of cards.php), the shop's answer (the quote of a
// repair), the rider's words and a closing line.
$request_detail = function ($back_text, $back_key, $missing, $not_found_title, $eyebrow, $title_sample, $title_field, $status, $sent, $facts, $extra, $notes_label, $notes_sample, $closing)
    use ($widget_root, $container, $row, $col, $el, $icon, $heading, $para, $link, $span, $bind, $show_when, $attr, $edit_button, $bound, $status_badge, $visitor_name) {
    return $widget_root(array(
        $container(array(
            $row(array(
                $col(array(
                    $el('div', 'text-center py-5', lang('Not Found'), array(
                        $icon('bi-wrench-adjustable-circle', 'display-5 text-body-tertiary'),
                        $heading('h2', $not_found_title, 'pbs-display h3 text-uppercase mt-3'),
                        $para($missing, 'text-body-secondary mb-4', $bind('not_found')),
                        $link($back_text, '{{page:' . $back_key . '}}', 'btn btn-primary rounded-0 px-4'),
                    ), $show_when('record_not_found')),
                    $el('div', '', lang('Record'), array_merge(array(
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
                        $el('article', 'card rounded-0 border-0 border-top border-4 border-primary shadow-sm mb-4', lang('Request Card'), array(
                            $el('div', 'card-body p-4', lang('Card Body'), array(
                                $el('div', 'd-flex flex-wrap align-items-start justify-content-between gap-2 mb-4', lang('Title'), array(
                                    $el('div', '', lang('Words'), array(
                                        $para($eyebrow, 'pbs-eyebrow small text-uppercase fw-semibold text-primary mb-1'),
                                        $heading('h1', $title_sample, 'pbs-display h2 text-uppercase mb-0', $bind($title_field)),
                                    )),
                                    $status_badge($status, 'badge rounded-0 text-bg-dark text-wrap fs-6'),
                                )),
                                $facts,
                                $el('p', 'd-flex flex-wrap gap-3 small text-body-secondary border-top pt-3 mt-4 mb-0', lang('Ticket Line'), array(
                                    $el('span', 'd-inline-flex align-items-center gap-1', lang('Name'), array_merge(array($icon('bi-person')), $visitor_name())),
                                    $el('span', 'd-inline-flex align-items-center gap-1', lang('Reference Code'), array(
                                        $icon('bi-upc'),
                                        $span('ABC123', 'font-monospace', lang('Reference Code'), $bind('reference_code')),
                                    )),
                                )),
                            )),
                        )),
                    ), $extra, array(
                        $el('div', 'pg-hide-if-empty p-4 bg-body-tertiary border-start border-4 border-dark mb-4', lang('Notes'), array(
                            $para($notes_label, 'small fw-semibold text-uppercase text-body-secondary mb-2'),
                            $el('div', 'pg-field-value lh-lg', lang('Value'), array(), $bound($notes_sample, 'notes')),
                        )),
                        $el('div', 'd-flex gap-2 small text-body-secondary', lang('Note'), array(
                            $icon('bi-chat-left-text', 'text-primary mt-1'),
                            $span($closing, '', lang('Text')),
                        )),
                    )), $show_when('record_found')),
                ), '', '8'),
            ), '', 'center'),
        ), 'py-5'),
    ));
};

// The messages under a request: the page's own Comments widget, shown only
// to whoever may read the request above it. Oldest first, all on one page.
// No Reply, Share or follow box: the sender is e-mailed every answer and
// the shop every message.
$request_messages = function ($page_key, $empty) use ($comment_thread) {
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

// The sender's fields at the end of a request form.
$contact_fields = function ($p) use ($row, $col, $field, $input) {
    return $row(array(
        $col(array($field($p . '-first-name', lang('First Name'), $input($p . '-first-name', 'text', 'first_name', 'given-name', true, array('contact_field' => 'first_name')))), '6'),
        $col(array($field($p . '-last-name', lang('Last Name'), $input($p . '-last-name', 'text', 'last_name', 'family-name', true, array('contact_field' => 'last_name')))), '6'),
        $col(array($field($p . '-email', lang('Email'), $input($p . '-email', 'email', 'email', 'email', true, array('contact_field' => 'email_address')))), '6'),
        $col(array($field($p . '-phone', lang('Phone'), $input($p . '-phone', 'tel', 'phone', 'tel', true, array('contact_field' => 'mobile_phone')))), '6'),
    ), '3');
};

// The send area at the end of a request form.
$send_area = function ($button, $note) use ($el, $para, $submit) {
    return $el('div', 'd-flex flex-wrap align-items-center gap-3', lang('Send Area'), array(
        $submit($button, 'btn btn-primary btn-lg rounded-0 text-uppercase px-4'),
        $para($note, 'small text-body-secondary mb-0'),
    ));
};

// A drop-off or a preferred date: today or later.
$not_before_today = array('rules' => array('min_date' => 'today'));

// ── Pages: service requests ─────────────────────────────────────────────

// The service request. What is sent opens the request's own page.
$request_pages[] = array(
    'key'              => 'service_request',
    'name'             => lang('request-a-service'),
    'folder'           => 'public',
    'title'            => lang('Request a Service'),
    'meta_description' => lang('Ask the workshop for a repair or a service: choose the job and a drop-off day, and the mechanic writes back with a quote.'),
    'form'             => array(
        'form_name'            => lang('Service Requests'),
        'confirmation_message' => lang('Thank you, your service request has reached the workshop. A mechanic writes to you within a working day.'),
        'confirmation_page_id' => '{{tab:service_view}}',
        'auto_registration'    => 1,
        'notify_email'         => '{{site_email}}',
        'notify_subject'       => lang('New service request: ^^job^^'),
        'notify_page_id'       => '{{tab:email_service_new}}',
        'confirm_email'        => 1,
        'confirm_subject'      => lang('We received your service request'),
        'confirm_page_id'      => '{{tab:email_service_received}}',
    ),
    'tree'             => $request_page(lang('Request a Service'),
        lang('Tell us the job, the bike and the day you can drop it off. A mechanic writes back with a quote before any work starts; nothing is paid now.'),
        'service_form',
        array_merge(array(
            $switch_box(lang('Not sure which job?'),
                lang('Choose "Something else" and tell us what the bike does; the mechanic suggests the job with the quote.'),
                lang('See the workshop'), '{{page:workshop}}'),
        ), $request_aside(array(
            lang('Your request opens its own page, and an account for you if you have none.'),
            lang('A mechanic writes to you there within a working day with a quote and a drop-off time.'),
            lang('Drop the bike off on the morning agreed; parts beyond the quote are fitted only after we ring you.'),
            lang('The page shows when the bike is ready to collect; you pay when you collect it.'),
        ), lang('My Service Requests'), '{{page:my_services}}'))),
);

// One service request: the ticket, its status and the messages.
$request_pages[] = $request_view('service_view', lang('service-request'), lang('Service Request'),
    'service_detail', 'service_messages', lang('There is a new message about your service request'));

// My Services: the rider's own service requests (for the staff,
// everybody's).
$request_pages[] = $my_list_page('my_services', lang('my-service-requests'), lang('My Service Requests'),
    lang('Your bikes in the workshop, their status and the mechanics\' answers, newest first.'),
    'my_services_list', 'bi-tools', lang('Another bike to look at?'),
    lang('Send a new request; your details are filled in for you.'), lang('Request a Service'), '{{page:service_request}}');

$request_pages[] = $staff_list_page('staff_services', lang('all-service-requests'), lang('Service Requests'),
    lang('Every service request, newest first: give a quote, set the status and write to the rider under each.'),
    'staff_services_list');

// ── Pages: fit requests and the booking page ────────────────────────────

// The fit request. What is sent opens the request's own page.
$request_pages[] = array(
    'key'              => 'fit_request',
    'name'             => lang('request-a-bike-fit'),
    'folder'           => 'public',
    'title'            => lang('Ask for a Bike Fit'),
    'meta_description' => lang('Ask the fitting studio for a bike fit: choose the package, tell us how you ride, and the fitter writes back with a time.'),
    'form'             => array(
        'form_name'            => lang('Fit Requests'),
        'confirmation_message' => lang('Thank you, your fit request has reached the fitting studio. The fitter writes to you within a working day.'),
        'confirmation_page_id' => '{{tab:fit_view}}',
        'auto_registration'    => 1,
        'notify_email'         => '{{site_email}}',
        'notify_subject'       => lang('New fit request: ^^fit^^'),
        'notify_page_id'       => '{{tab:email_fit_new}}',
        'confirm_email'        => 1,
        'confirm_subject'      => lang('We received your fit request'),
        'confirm_page_id'      => '{{tab:email_fit_received}}',
    ),
    'tree'             => $request_page(lang('Ask for a Bike Fit'),
        lang('Choose the package and tell us how you ride and what hurts. The fitter writes back with a time in the studio; nothing is paid now.'),
        'fit_form',
        array_merge(array(
            $switch_box(lang('Rather pick a time now?'),
                lang('Know the package already? Choose one of the fitting studio\'s free times and it is yours at once.'),
                lang('Book a Fit Online'), '{{page:book_online}}'),
        ), $request_aside(array(
            lang('Your request opens its own page, and an account for you if you have none.'),
            lang('The fitter reads how you ride and writes to you there with a time in the studio.'),
            lang('Bring your bike, your shoes and the kit you ride in.'),
            lang('You pay in the studio after the fit, and keep the fit sheet.'),
        ), lang('See the fit packages'), '{{page:fit}}'))),
);

// One fit request: the ticket, its status and the messages.
$request_pages[] = $request_view('fit_view', lang('fit-request'), lang('Fit Request'),
    'fit_detail', 'fit_messages', lang('There is a new message about your bike fit'));

$request_pages[] = $my_list_page('my_fits', lang('my-fit-requests'), lang('My Fit Requests'),
    lang('Your bike fit requests, their status and the fitter\'s answers, newest first.'),
    'my_fits_list', 'bi-rulers', lang('Another fit?'),
    lang('A follow-up or a cleat fit for new shoes: your details are filled in for you.'), lang('Ask for a Bike Fit'), '{{page:fit_request}}');

$request_pages[] = $staff_list_page('staff_fits', lang('all-fit-requests'), lang('Fit Requests'),
    lang('Every fit request, newest first: offer a time, set the status and write to the rider under each.'),
    'staff_fits_list');

// A fit at a free time: the booking widget beside a box that points at
// the fit request, and the opening hours.
$request_pages[] = array(
    'key'              => 'book_online',
    'name'             => lang('book-online'),
    'folder'           => 'public',
    'title'            => lang('Book a Fit Online'),
    'meta_description' => lang('Book a bike fit online: choose the package, the fitter and one of the studio\'s free times, and it is booked at once.'),
    'tree'             => $page(array(
        $page_header(lang('Book a Fit Online'), lang('Choose the package, the fitter and one of the studio\'s free times. The time is yours as soon as you confirm.')),
        $el('section', 'py-5', lang('Content'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $el('div', 'card rounded-0 border-0 border-top border-4 border-dark shadow-sm', lang('Booking Card'), array(
                            $el('div', 'card-body p-4 p-lg-5', lang('Card Body'), array($widget('booking_slots'))),
                        )),
                    ), '', '8'),
                    $col(array(
                        $switch_box(lang('Rather ask first?'),
                            lang('Pains to talk through, or no free time that suits you? Send a fit request and the fitter finds a time with you.'),
                            lang('Ask for a Bike Fit'), '{{page:fit_request}}'),
                        $el('div', 'p-4 border', lang('Opening Hours'), array(
                            $heading('h2', lang('Opening hours'), 'pbs-display h5 text-uppercase mb-3'),
                            $hours_list('mb-0'),
                        )),
                    ), '', '4'),
                ), '4', '', 'g-lg-5'),
            )),
        ), $searchable('bikeshop-book-online', lang('book online, bike fit, free time, fitting studio, appointment'))),
    )),
);

// ── Privacy and the e-mails ─────────────────────────────────────────────

// The privacy notice the consent boxes, the booking widget and the footer
// link to: sample text with a note to adapt it.
$request_pages[] = array(
    'key'              => 'privacy',
    'name'             => lang('privacy-policy'),
    'folder'           => 'public',
    'title'            => lang('Privacy Policy'),
    'meta_description' => lang('What the shop keeps about you when you send a service or fit request, book a fit, buy from us or open an account, and why.'),
    'tree'             => $page(array(
        $page_header(lang('Privacy Policy'), lang('What the shop keeps about you when you send a service or fit request, book a fit, buy from us or open an account, and why.')),
        $el('section', 'py-5', lang('Your Data'), array(
            $container(array($row(array($col(array(
                $el('div', 'alert alert-secondary rounded-0 d-flex gap-2 small', lang('Sample Text Note'), array(
                    $icon('bi-info-circle', 'mt-1'),
                    $span(lang('Sample text: adapt it to your company and to the data protection law that applies to you before you publish.'), '', lang('Text')),
                ), array('_attrs' => array($attr('role', 'note')))),
                $heading('h2', lang('Your data'), 'pbs-display h4 text-uppercase mb-3'),
                $para(lang('When you send a service or fit request, book a fit, buy a bike or parts, join the newsletter or open an account, we keep your name, your contact details and what you tell us, such as your bike, your height and how you ride, to answer you, look after your bike and set up your fit.'), 'mb-3'),
                $para(lang('We keep it as long as the law requires, never sell it and never give it to advertisers. You can see and change your account data from My Account, and ask us about the rest through the contact page.'), 'mb-0'),
            ), '', '8'))))),
        ), $searchable('bikeshop-your-data', lang('privacy, personal data, KVKK, GDPR, delete my data'))),
    )),
);

// The e-mails: what the shop receives and the reply to the rider.
foreach (array(
    array('email_service_new', lang('email-new-service-request'), lang('New Service Request E-mail'), 'email_service_admin'),
    array('email_service_received', lang('email-service-request-received'), lang('Service Request Received E-mail'), 'email_service_client'),
    array('email_fit_new', lang('email-new-fit-request'), lang('New Fit Request E-mail'), 'email_fit_admin'),
    array('email_fit_received', lang('email-fit-request-received'), lang('Fit Request Received E-mail'), 'email_fit_client'),
) as $m) {
    $request_pages[] = array_merge($mail_settings, array(
        'key'   => $m[0],
        'name'  => $m[1],
        'title' => $m[2],
        'tree'  => $mail_page(array($widget($m[3]))),
    ));
}
// A new message or reply: sent to the sender of a request, the sender of a
// conversation and the visitors watching a page. Its Comments widget draws
// the comment in the card of mail.php; the System region after it keeps
// the fixed comment for an e-mail the widget does not draw.
$request_pages[] = array_merge($mail_settings, array(
    'key'   => 'email_reply',
    'name'  => lang('email-new-reply'),
    'title' => lang('New Reply E-mail'),
    'tree'  => $mail_page(array(
        $mail_heading(lang('There is a new message')),
        $mail_para(lang('Something new was written on a page you follow: your service request, your fit request, a conversation or a news post. You can read it below and answer it on the page.')),
        $widget('email_comment'),
        array('type' => 'region', 'props' => array('regionType' => 'system', 'regionName' => ''), 'children' => array()),
    )),
));

// ── Widgets ─────────────────────────────────────────────────────────────

// The request's job, bike and drop-off day, for its e-mails.
$mail_service = function () use ($mail_box, $mail_line, $span, $bind, $sample_job, $sample_bike, $sample_make) {
    return $mail_box(array(
        $mail_line(lang('Job'), array($span($sample_job, '', lang('Job'), $bind('job')))),
        $mail_line(lang('Bike kind'), array($span($sample_bike, '', lang('Bike kind'), $bind('bike_kind')))),
        $mail_line(lang('Make and model'), array($span($sample_make, '', lang('Make and model'), $bind('make_model')))),
        $mail_line(lang('Preferred drop-off date'), array($span(lang('Preferred drop-off date'), '', lang('Preferred drop-off date'), $bind('dropoff_date')))),
    ), lang('Service Request'));
};
// The request's package, the rider's measures and the preferred day.
$mail_fit = function () use ($mail_box, $mail_line, $span, $bind, $sample_fit, $sample_style) {
    return $mail_box(array(
        $mail_line(lang('Fit package'), array($span($sample_fit, '', lang('Fit package'), $bind('fit')))),
        $mail_line(lang('Height'), array($span('178', '', lang('Height'), $bind('height')))),
        $mail_line(lang('Inseam'), array($span('84', '', lang('Inseam'), $bind('inseam')))),
        $mail_line(lang('Riding style'), array($span($sample_style, '', lang('Riding style'), $bind('riding_style')))),
        $mail_line(lang('Preferred date'), array($span(lang('Preferred date'), '', lang('Preferred date'), $bind('preferred_date')))),
    ), lang('Fit Request'));
};

$service_row = function ($staff = false) use ($request_row, $row_item, $sample_job, $sample_bike, $sample_make, $service_statuses) {
    return $request_row($staff, $sample_job, 'job', lang('Job'), $service_statuses[0], array(
        $row_item('bi-bicycle', lang('Bike kind'), $sample_bike, 'bike_kind'),
        $row_item('bi-tag', lang('Make and model'), $sample_make, 'make_model'),
        $row_item('bi-calendar-event', lang('Preferred drop-off date'), lang('Preferred drop-off date'), 'dropoff_date'),
    ));
};
$fit_row = function ($staff = false) use ($request_row, $row_item, $sample_fit, $sample_style, $fit_statuses) {
    return $request_row($staff, $sample_fit, 'fit', lang('Fit package'), $fit_statuses[0], array(
        $row_item('bi-speedometer2', lang('Riding style'), $sample_style, 'riding_style'),
        $row_item('bi-calendar-event', lang('Preferred date'), lang('Preferred date'), 'preferred_date'),
    ));
};
$service_missing = lang('This service request could not be found, or it is not yours to see.');
$fit_missing = lang('This fit request could not be found, or it is not yours to see.');

$request_widgets = array(

    // The service request: the fields are the page's form. The job and the
    // bike kind start with what a workshop card sends (?job=, ?bike=). The
    // Status and the Quote are the workshop's (office use only): every
    // request starts as Request received.
    'service_form' => array(
        'page'   => 'service_request',
        'slug'   => 'form',
        'config' => array('regionType' => 'custom_form', 'form_source' => 'page'),
        'tree'   => $widget_root(array(
            $form_step(1, lang('The job and the bike')),
            $field('bikeshop-sr-job', lang('Job'), $select('bikeshop-sr-job', 'job', lang('Choose a job…'), $job_choices, true, '', array('url_param' => 'job'))),
            $row(array(
                $col(array($field('bikeshop-sr-bike', lang('Bike kind'), $select('bikeshop-sr-bike', 'bike_kind', lang('Choose…'), $bike_titles, true, '', array('url_param' => 'bike')))), '6'),
                $col(array($field('bikeshop-sr-make', lang('Make and model'), $input('bikeshop-sr-make', 'text', 'make_model', 'off', false), 'mb-3',
                    lang('As it says on the frame, if you know it.'))), '6'),
            ), '3'),
            $field('bikeshop-sr-dropoff', lang('Preferred drop-off date'), $input('bikeshop-sr-dropoff', 'date', 'dropoff_date', 'off', true, $not_before_today), 'mb-3',
                lang('Drop-off is in the morning, 09:00 to 11:00; the mechanic confirms the day.')),
            $field('bikeshop-sr-notes', lang('What is wrong?'), $textarea('bikeshop-sr-notes', 'notes', 4, true), 'mb-3',
                lang('What the bike does, when it started, and anything you have already tried or replaced.')),
            $form_step(2, lang('How to reach you')),
            $contact_fields('bikeshop-sr'),
            $radios('bikeshop-sr-reply', 'reply_by', lang('Reply by'), $contact_choices, $contact_choices[0][0]),
            $field('bikeshop-sr-status', lang('Status'), $select('bikeshop-sr-status', 'status', '', $service_statuses, false, $service_statuses[0], array('office_use_only' => true))),
            $field('bikeshop-sr-quote', lang('Quote'), $textarea('bikeshop-sr-quote', 'quote', 2, false, array('office_use_only' => true))),
            $agree('bikeshop-sr-consent', 'consent', lang('I agree that the shop keeps these details to look after my bike and contact me about it.'), true),
            $captcha(),
            $send_area(lang('Send the Request'), lang('A mechanic writes to you within a working day.')),
            $loop(array()),
        )),
    ),

    // One service request; the workshop's answers follow as comments.
    'service_detail' => $detail_widget('service_view', 'service_request', $service_missing, 'submitter_only') + array(
        'tree' => $request_detail(lang('My Service Requests'), 'my_services', $service_missing, lang('Service request not found'),
            lang('Service Request'), $sample_job, 'job', $service_statuses[0],
            lang('Thank you, your request has reached the workshop. A mechanic writes to you here within a working day; this page shows the status, and you can write to us below.'),
            $request_facts('service'),
            array(
                $el('div', 'pg-hide-if-empty p-4 border border-2 border-primary mb-4', lang('Quote'), array(
                    $el('p', 'd-flex align-items-center gap-2 small fw-semibold text-uppercase mb-2', lang('Title'), array(
                        $icon('bi-receipt', 'text-primary'),
                        $span(lang('The workshop\'s quote'), '', lang('Text')),
                    )),
                    $el('div', 'pg-field-value lh-lg', lang('Value'), array(), $bound($sample_quote, 'quote')),
                )),
            ),
            lang('What is wrong?'), $sample_service_notes,
            lang('A question about the quote, or another day to drop off? Write to us below; we answer here and by e-mail.')),
    ),

    'service_messages' => $request_messages('service_view',
        lang('No messages yet. The mechanic\'s answer comes here; you can write to us about the bike too.')),

    'my_services_list' => $request_list('my_services', 'service_request', 'service_view', $service_row, array(), '',
        lang('No service requests yet. Choose a job on the workshop page.')),

    'staff_services_list' => $request_list('staff_services', 'service_request', 'service_view', $service_row,
        array('first_name', 'last_name', 'email', 'job', 'bike_kind', 'make_model', 'status'),
        lang('Search by name, e-mail, job, bike or status...'), lang('No service requests yet.')),

    // The fit request: the package starts with what a fit card sends
    // (?fit=). The Status is the fitter's (office use only).
    'fit_form' => array(
        'page'   => 'fit_request',
        'slug'   => 'form',
        'config' => array('regionType' => 'custom_form', 'form_source' => 'page'),
        'tree'   => $widget_root(array(
            $form_step(1, lang('The fit and how you ride')),
            $field('bikeshop-fr-fit', lang('Fit package'), $select('bikeshop-fr-fit', 'fit', lang('Choose a package…'), $fit_titles, true, '', array('url_param' => 'fit'))),
            $row(array(
                $col(array($field('bikeshop-fr-height', lang('Height'), $input('bikeshop-fr-height', 'number', 'height', 'off', true, array(), array(
                    $attr('min', '100'), $attr('max', '230'), $attr('step', '1'), $attr('inputmode', 'numeric'))), 'mb-3', lang('In centimetres.'))), '4'),
                $col(array($field('bikeshop-fr-inseam', lang('Inseam'), $input('bikeshop-fr-inseam', 'number', 'inseam', 'off', false, array(), array(
                    $attr('min', '50'), $attr('max', '120'), $attr('step', '1'), $attr('inputmode', 'numeric'))), 'mb-3', lang('In centimetres, if you know it.'))), '4'),
                $col(array($field('bikeshop-fr-style', lang('Riding style'), $select('bikeshop-fr-style', 'riding_style', lang('Choose…'), $riding_styles, true))), '4'),
            ), '3'),
            $field('bikeshop-fr-date', lang('Preferred date'), $input('bikeshop-fr-date', 'date', 'preferred_date', 'off', true, $not_before_today), 'mb-3',
                lang('The studio fits by appointment, 10:00 to 17:00; the fitter confirms the time.')),
            $field('bikeshop-fr-notes', lang('Pains or goals'), $textarea('bikeshop-fr-notes', 'notes', 4, false), 'mb-3',
                lang('Where it hurts and when, an injury, a ride or race you are training for.')),
            $form_step(2, lang('How to reach you')),
            $contact_fields('bikeshop-fr'),
            $radios('bikeshop-fr-reply', 'reply_by', lang('Reply by'), $contact_choices, $contact_choices[0][0]),
            $field('bikeshop-fr-status', lang('Status'), $select('bikeshop-fr-status', 'status', '', $fit_statuses, false, $fit_statuses[0], array('office_use_only' => true))),
            $agree('bikeshop-fr-consent', 'consent', lang('I agree that the shop keeps these details, my measures included, to prepare my bike fit.'), true),
            $captcha(),
            $send_area(lang('Send the Request'), lang('The fitter writes to you within a working day.')),
            $loop(array()),
        )),
    ),

    'fit_detail' => $detail_widget('fit_view', 'fit_request', $fit_missing, 'submitter_only') + array(
        'tree' => $request_detail(lang('My Fit Requests'), 'my_fits', $fit_missing, lang('Fit request not found'),
            lang('Fit Request'), $sample_fit, 'fit', $fit_statuses[0],
            lang('Thank you, your request has reached the fitting studio. The fitter writes to you here within a working day; this page shows the status, and you can write to us below.'),
            $request_facts('fit'),
            array(),
            lang('Pains or goals'), $sample_fit_notes,
            lang('Another day, or something the fitter should know before you come? Write to us below; we answer here and by e-mail.')),
    ),

    'fit_messages' => $request_messages('fit_view',
        lang('No messages yet. The fitter\'s answer comes here; you can write to us about the fit too.')),

    'my_fits_list' => $request_list('my_fits', 'fit_request', 'fit_view', $fit_row, array(), '',
        lang('No fit requests yet. Choose a package on the bike fit page.')),

    'staff_fits_list' => $request_list('staff_fits', 'fit_request', 'fit_view', $fit_row,
        array('first_name', 'last_name', 'email', 'fit', 'riding_style', 'status'),
        lang('Search by name, e-mail, package or status...'), lang('No fit requests yet.')),

    // A fit at a free time: the booking widget in the editor's starter
    // layout (service, staff, day and time, details, confirmation).
    'booking_slots' => array(
        'page'   => 'book_online',
        'slug'   => lang('booking'),
        'config' => array('regionType' => 'booking'),
        'tree'   => 'starter',
    ),

    // The e-mail pages' widgets: drawn for the submission just sent.
    'email_service_admin' => $detail_widget('email_service_new', 'service_request', lang('The service request could not be found.'), 'public') + array(
        'tree' => $widget_root(array(
            $mail_heading(lang('A new service request')),
            $mail_para(lang('It came from the service request form. Write the quote and a drop-off time under the request on its page and set the status; what you write there reaches the rider by e-mail.')),
            $mail_service(),
            $mail_sender(lang('What is wrong?'), $sample_service_notes),
            $mail_button(lang('Open the request'), '{{page:service_view}}?r=^^reference_code^^'),
            $mail_reference(),
        )),
    ),
    'email_service_client' => $detail_widget('email_service_received', 'service_request', lang('The service request could not be found.'), 'public') + array(
        'tree' => $widget_root(array(
            $mail_heading(lang('We received your service request')),
            $mail_greeting(),
            $mail_para(lang('Thank you. A mechanic writes to you within a working day with a quote and a drop-off time, on the request\'s page and by e-mail. No work starts before you agree to the quote.')),
            $mail_service(),
            $mail_para(lang('Something to add? Write to us on the request\'s page; everything about the bike stays there.')),
            $mail_button(lang('Open my request'), '{{page:service_view}}?r=^^reference_code^^'),
            $mail_new_account(),
        )),
    ),
    'email_fit_admin' => $detail_widget('email_fit_new', 'fit_request', lang('The fit request could not be found.'), 'public') + array(
        'tree' => $widget_root(array(
            $mail_heading(lang('A new fit request')),
            $mail_para(lang('It came from the fit request form. Offer a time in the studio under the request on its page and set the status; what you write there reaches the rider by e-mail.')),
            $mail_fit(),
            $mail_sender(lang('Pains or goals'), $sample_fit_notes),
            $mail_button(lang('Open the request'), '{{page:fit_view}}?r=^^reference_code^^'),
            $mail_reference(),
        )),
    ),
    'email_fit_client' => $detail_widget('email_fit_received', 'fit_request', lang('The fit request could not be found.'), 'public') + array(
        'tree' => $widget_root(array(
            $mail_heading(lang('We received your fit request')),
            $mail_greeting(),
            $mail_para(lang('Thank you. The fitter writes to you within a working day with a time in the studio, on the request\'s page and by e-mail. Bring your bike, your shoes and the kit you ride in.')),
            $mail_fit(),
            $mail_para(lang('Something to add? Write to us on the request\'s page; everything about the fit stays there.')),
            $mail_button(lang('Open my request'), '{{page:fit_view}}?r=^^reference_code^^'),
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
