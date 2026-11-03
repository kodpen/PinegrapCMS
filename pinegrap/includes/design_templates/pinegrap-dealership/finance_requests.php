<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Car Dealership", its finance requests: the request
 * form, the request's own page with its messages, My Finance Requests and
 * the staff's list, the two e-mails the form sends and the widgets that
 * draw them. Included after parts.php in the same scope; returns
 * array('pages' => ..., 'widgets' => ...).
 *
 * A finance request is a record of the Finance Requests form. A visitor
 * without an account gets one (auto-registration), so the request's page
 * and My Finance Requests show it at once. Its Status is an office-use-only
 * field the staff change in the control panel; the messages under it are
 * the page's comments, mailed to the customer and to the dealer.
 *
 * The Vehicle starts with the car the vehicle page sends (?vehicle=, the
 * vehicle's title) and the Way to Pay with the plan the finance page's
 * cards send (?plan=). The form asks for bands (deposit, term, income,
 * employment), never an ID or a bank number: the adviser asks for the
 * documents in person.
 *
 * Every helper of this file is named fin_… so it never replaces a
 * variable another part of the folder defines in the same scope.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

$fin_pages = array();
$fin_sample_vehicle = $vehicle_title('velo-volt');
$fin_sample_plan = $finance_plans['pcp']['title'];
$fin_missing = lang('This finance request could not be found, or it is not yours to see.');
$fin_sample_note = lang('I would like to keep the monthly payment under $600 and trade in my current car.');

// ── E-mail builders ─────────────────────────────────────────────────────
// E-mail programs drop linked stylesheets, so the few rules that shape an
// e-mail are written on the elements next to the Bootstrap classes, and a
// colour keeps its fallback inside var() for a client that blocks the CSS.

$fin_mail_style = function ($css) use ($attr) {
    return array('_attrs' => array($attr('style', $css)));
};
$fin_mail_page = function ($children) use ($el, $shared, $fin_mail_style) {
    return array('type' => 'root', 'props' => array('cssClass' => ''), 'children' => array(
        $el('div', 'bg-body-tertiary py-4 px-3', lang('E-mail Frame'), array(
            $el('div', 'mx-auto bg-body border rounded-3 overflow-hidden', lang('E-mail Card'), array(
                $shared('email_header'),
                $el('div', 'p-4', lang('E-mail Body'), $children, $fin_mail_style('padding:24px')),
                $shared('email_footer'),
            ), $fin_mail_style('max-width:600px;margin:0 auto;background:#ffffff;border:1px solid #dbeafe;border-radius:10px;overflow:hidden')),
        ), $fin_mail_style('background:#eff6ff;padding:24px 12px;font-family:-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,Helvetica,Arial,sans-serif;font-size:15px;line-height:1.6;color:#1f2937')),
    ));
};
$fin_mail_heading = function ($text) use ($heading, $attr) {
    return $heading('h1', $text, 'h4 fw-semibold mb-3', array('_attrs' => array($attr('style', 'font-size:22px;font-weight:600;margin:0 0 16px;color:#1f2937'))));
};
$fin_mail_para = function ($text) use ($para, $attr) {
    return $para($text, 'mb-3', array('_attrs' => array($attr('style', 'margin:0 0 16px'))));
};
$fin_mail_button = function ($text, $href) use ($el, $link, $fin_mail_style, $attr) {
    return $el('p', 'my-4', lang('Button'), array(
        $link($text, $href, 'btn btn-primary px-4', array($attr('style',
            'display:inline-block;padding:10px 22px;border-radius:6px;font-weight:600;text-decoration:none;'
            . 'background-color:#1d4ed8;background-color:var(--bs-primary,#1d4ed8);color:#ffffff;color:var(--pg-on-primary,#ffffff)'))),
    ), $fin_mail_style('margin:24px 0'));
};
// A line of details: the label, then the value; the line leaves when the
// value is empty.
$fin_mail_line = function ($label, $values) use ($el, $span, $fin_mail_style, $attr) {
    return $el('p', 'pg-hide-if-empty mb-1', lang('Line'), array(
        $span($label . ': ', 'text-body-secondary', lang('Label'), array('_attrs' => array($attr('style', 'color:#4b5563')))),
        $el('span', 'pg-field-value fw-semibold', lang('Value'), $values),
    ), $fin_mail_style('margin:0 0 4px'));
};
$fin_mail_box = function ($children, $name) use ($el, $fin_mail_style) {
    return $el('div', 'bg-body-tertiary rounded-3 p-3 my-3', $name, $children,
        $fin_mail_style('background:#eff6ff;border-radius:8px;padding:16px;margin:16px 0'));
};
$fin_mail_greeting = function () use ($el, $span, $bind, $fin_mail_style) {
    return $el('p', 'mb-3', lang('Greeting'), array(
        $span(lang('Hello') . ' ', '', lang('Text')),
        $span(lang('Jane'), '', lang('First Name'), $bind('first_name')),
    ), $fin_mail_style('margin:0 0 16px'));
};
// The customer's name.
$fin_person_name = function () use ($span, $bind) {
    return array(
        $span(lang('Jane'), '', lang('First Name'), $bind('first_name')),
        $span(' ', '', lang('Text')),
        $span(lang('Cooper'), '', lang('Last Name'), $bind('last_name')),
    );
};
// The request's lines, in both e-mails.
$fin_mail_request = function () use ($fin_mail_box, $fin_mail_line, $span, $bind, $fin_sample_vehicle, $fin_sample_plan, $deposit_choices, $term_choices) {
    return $fin_mail_box(array(
        $fin_mail_line(lang('Vehicle'), array($span($fin_sample_vehicle, '', lang('Vehicle'), $bind('vehicle')))),
        $fin_mail_line(lang('Way to pay'), array($span($fin_sample_plan, '', lang('Way to pay'), $bind('plan')))),
        $fin_mail_line(lang('Deposit'), array($span($deposit_choices[2], '', lang('Deposit'), $bind('deposit')))),
        $fin_mail_line(lang('Loan term'), array($span($term_choices[1], '', lang('Loan term'), $bind('term')))),
        $fin_mail_line(lang('Contact by'), array($span(lang('Phone'), '', lang('Contact by'), $bind('contact_method')))),
    ), lang('Finance Request'));
};
// The customer's lines of the staff e-mail.
$fin_mail_client = function () use ($fin_mail_box, $fin_mail_line, $span, $bind, $fin_person_name, $employment_choices, $income_choices, $fin_sample_note) {
    return $fin_mail_box(array(
        $fin_mail_line(lang('Customer'), $fin_person_name()),
        $fin_mail_line(lang('Email'), array($span('jane.cooper@example.com', '', lang('Email'), $bind('email')))),
        $fin_mail_line(lang('Phone'), array($span('+90 555 000 00 00', '', lang('Phone'), $bind('phone')))),
        $fin_mail_line(lang('Employment status'), array($span($employment_choices[0], '', lang('Employment status'), $bind('employment')))),
        $fin_mail_line(lang('Monthly income'), array($span($income_choices[1], '', lang('Monthly income'), $bind('income')))),
        $fin_mail_line(lang('Anything we should know?'), array($span($fin_sample_note, '', lang('Note'), $bind('note')))),
    ), lang('Customer'));
};
// A reference code line under the staff e-mail.
$fin_mail_reference = function () use ($el, $span, $bind, $fin_mail_style) {
    return $el('p', 'small text-body-secondary mt-4 mb-0', lang('Reference'), array(
        $span(lang('Reference Code') . ': ', '', lang('Label')),
        $span('ABC123', '', lang('Reference Code'), $bind('reference_code')),
    ), $fin_mail_style('font-size:13px;color:#4b5563;margin:24px 0 0'));
};
// The account the form opened for a sender who had none, in the reply.
$fin_mail_new_account = function () use ($el, $span, $link, $bind, $show_when, $fin_mail_style) {
    return $el('div', 'border-top pt-3', lang('New Account'), array(
        $el('p', 'mb-2', lang('Sign-in Email'), array(
            $span(lang('We opened an account for you with this address:') . ' ', '', lang('Label')),
            $span('sample@example.com', 'fw-semibold', lang('Value'), $bind('__new_account_email')),
        ), $fin_mail_style('margin:0 0 8px')),
        $el('p', 'mb-0', lang('Password'), array(
            $span(lang('To choose your password, use') . ' ', '', lang('Label')),
            $link(lang('Forgot Password'), '{{page:forgot_password}}', ''),
        ), $fin_mail_style('margin:0')),
    ), array_merge($fin_mail_style('border-top:1px solid #dbeafe;padding-top:16px'), $show_when('has_new_account')));
};
// The form detail settings of an e-mail page's widget (public: it is drawn
// for the submission just sent) and of the request page's (its sender and
// the staff only).
$fin_detail_config = function ($page_key, $access) use ($fin_missing) {
    return array(
        'page'   => $page_key,
        'slug'   => lang('form-detail'),
        'config' => array(
            'regionType'          => 'form_item_view',
            'custom_form_page_id' => '{{tab:finance_apply}}',
            'access_control'      => $access,
            'not_found_message'   => $fin_missing,
        ),
    );
};

// ── Page parts ──────────────────────────────────────────────────────────

// A list of finance requests: the customer's own (the staff see them all),
// or the staff's with a search. A row: the vehicle, the status, the plan,
// the deposit and term, the date; the staff's row names the customer and
// the phone, the customer's the number of messages.
$fin_list = function ($page_key, $staff) use ($widget_root, $el, $loop, $span, $icon, $bind, $fin_person_name, $fin_sample_vehicle, $fin_sample_plan, $term_choices) {
    $item = function ($icon_name, $name, $sample, $token) use ($el, $span, $icon, $bind) {
        return $el('span', 'd-inline-flex align-items-center gap-1', $name, array($icon($icon_name), $span($sample, '', $name, $bind($token))));
    };
    $items = array(
        $item('bi-wallet2', lang('Way to pay'), $fin_sample_plan, 'plan'),
        $item('bi-calendar-range', lang('Loan term'), $term_choices[1], 'term'),
        $item('bi-calendar3', lang('Sent'), lang('Date'), 'submitted_date_and_time'),
    );
    if ($staff) {
        $items[] = $item('bi-telephone', lang('Phone'), '+90 555 000 00 00', 'phone');
        $title = $el('span', 'fw-semibold', lang('Customer'), array_merge($fin_person_name(), array(
            $span(' · ', 'text-body-secondary', lang('Text')),
            $span($fin_sample_vehicle, 'text-body-secondary fw-normal', lang('Vehicle'), $bind('vehicle')),
        )));
    } else {
        $items[] = $item('bi-chat-left-text', lang('Messages'), '0', 'number_of_comments');
        $title = $span($fin_sample_vehicle, 'fw-semibold', lang('Vehicle'), $bind('vehicle'));
    }
    $row_node = $el('a', 'list-group-item list-group-item-action p-3', lang('Finance Request'), array(
        $el('div', 'd-flex flex-wrap align-items-center justify-content-between gap-2 mb-1', lang('Top Line'), array(
            $title,
            $span(lang('Request received'), 'badge rounded-pill bg-primary-subtle text-primary-emphasis', lang('Status'), $bind('status')),
        )),
        $el('div', 'd-flex flex-wrap column-gap-3 row-gap-1 small text-body-secondary', lang('Details'), $items),
    ), array('href' => '#', '_bindings' => array('href' => 'form_item_view')));

    $config = array(
        'regionType'          => 'form_list_view',
        'custom_form_page_id' => '{{tab:finance_apply}}',
        'detail_page_id'      => '{{tab:finance_view}}',
        'items_per_page'      => 20,
        'order_by_field'      => 'submitted_date_and_time',
        'order_by_direction'  => 'DESC',
    );
    if ($staff) {
        $config += array(
            'search_enabled' => true,
            'search_fields'  => array('first_name', 'last_name', 'vehicle', 'plan', 'status'),
            'search_width'   => 'half',
            'search_label'   => lang('Search by customer, vehicle, plan or status...'),
            'empty_message'  => lang('No finance requests yet.'),
        );
    } else {
        $config += array(
            'viewer_filter'           => true,
            'viewer_filter_submitter' => true,
            'viewer_filter_watcher'   => true,
            'viewer_filter_editor'    => true,
            'empty_message'           => lang('No finance requests yet. Choose a way to pay on the finance page and ask about it.'),
        );
    }
    return array(
        'page'   => $page_key,
        'slug'   => lang('form-list'),
        'config' => $config,
        'tree'   => $widget_root(array(
            $el('div', 'list-group', lang('Request List'), array($loop(array($row_node)))),
        )),
    );
};

// A detail of the request's card: the label, then the value (its sample
// text and binding, or with $nodes the nodes it is made of).
$fin_detail = function ($label, $class, $name, $value, $nodes = false) use ($el) {
    return array(
        $el('dt', 'col-sm-4 fw-normal text-body-secondary', lang('Label'), array(), array('text' => $label)),
        $el('dd', trim('col-sm-8 ' . $class), $name, $nodes ? $value : array(), $nodes ? array() : $value),
    );
};
$fin_bound = function ($sample, $token) use ($bind) {
    return array_merge(array('text' => $sample), $bind($token));
};

// The side column of the request form: what happens next, what to bring,
// then the showroom hours.
$fin_aside = function () use ($el, $heading, $hours, $check_list) {
    $steps = array(
        lang('Your request opens its own page, and an account for you if you have none.'),
        lang('Our finance adviser calls you within a working day with the monthly figures.'),
        lang('You bring your papers to the showroom; nothing is signed before you have read every figure.'),
    );
    $items = array();
    foreach ($steps as $n => $text) {
        $items[] = $el('li', $n < count($steps) - 1 ? 'mb-2' : '', lang('Step'), array(), array('text' => $text));
    }
    return array(
        $el('div', 'p-4 rounded-3 bg-body-tertiary mb-4', lang('How It Works'), array(
            $heading('h2', lang('What happens next'), 'h5 fw-semibold mb-3'),
            $el('ol', 'small text-body-secondary ps-3 mb-0', lang('List'), $items),
        )),
        $el('div', 'p-4 rounded-3 border mb-4', lang('What to Bring'), array(
            $heading('h2', lang('What to bring to the showroom'), 'h5 fw-semibold mb-3'),
            $check_list(array(lang('Your ID card or passport'), lang('Your driving licence'), lang('Proof of income and address')),
                'list-unstyled small mb-2'),
            $el('p', 'small text-body-secondary mb-0', lang('Note'), array(), array(
                'text' => lang('Never send these papers by the form or by e-mail; the adviser sees them in person.'))),
        )),
        $el('div', 'p-4 rounded-3 border', lang('Showroom hours'), array(
            $heading('h2', lang('Showroom hours'), 'h5 fw-semibold mb-3'),
            $hours(),
        )),
    );
};

// ── Pages ───────────────────────────────────────────────────────────────

// The finance request. What is sent opens the request's own page.
$fin_pages[] = array(
    'key'              => 'finance_apply',
    'name'             => lang('apply-for-finance'),
    'folder'           => 'public',
    'title'            => lang('Apply for Finance'),
    'meta_description' => lang('Ask for the monthly figures of a car: choose a way to pay, a deposit and a term, and our adviser calls you within a working day.'),
    'form'             => array(
        'form_name'            => lang('Finance Requests'),
        'confirmation_message' => lang('Thank you, your finance request has reached us. Our adviser calls you within a working day.'),
        'confirmation_page_id' => '{{tab:finance_view}}',
        'auto_registration'    => 1,
        'notify_email'         => '{{site_email}}',
        'notify_subject'       => lang('New finance request: ^^vehicle^^'),
        'notify_page_id'       => '{{tab:email_finance_new}}',
        'confirm_email'        => 1,
        'confirm_subject'      => lang('We received your finance request'),
        'confirm_page_id'      => '{{tab:email_finance_received}}',
    ),
    'tree'             => $page(array(
        $page_header(lang('Apply for Finance'),
            lang('Tell us the car and how you would like to pay. Our adviser works out the monthly figures and calls you within a working day.')),
        $el('section', 'py-5', lang('Content'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $el('div', 'card border-0 shadow-sm rounded-3', lang('Form Card'), array(
                            $el('div', 'card-body p-4 p-lg-5', lang('Card Body'), array($widget('finance_form'))),
                        )),
                    ), '', '8'),
                    $col($fin_aside(), '', '4'),
                ), '4', '', 'g-lg-5'),
            )),
        )),
    ), false),
);

// One finance request: the request, its status and the messages under it.
$fin_pages[] = array(
    'key'      => 'finance_view',
    'name'     => lang('finance-request'),
    'folder'   => 'registration',
    'title'    => lang('Finance Request'),
    'search'   => false,
    'sitemap'  => false,
    'comments' => array('label' => lang('Message'), 'allow_new' => true, 'auto_publish' => true, 'show_date' => true, 'login' => true,
                        'attachments' => true,
                        'email_page' => '{{tab:email_reply}}', 'email_subject' => lang('There is a new message about your finance request'),
                        'notify_email' => '{{site_email}}'),
    'tree'     => $page(array(
        $widget('finance_detail'),
        $comments_section(lang('Messages'), 'finance_messages'),
    ), false),
);

// My Finance Requests: the customer's requests (for the staff, everybody's).
$fin_pages[] = array(
    'key'     => 'my_finance',
    'name'    => lang('my-finance-requests'),
    'folder'  => 'registration',
    'title'   => lang('My Finance Requests'),
    'search'  => false,
    'sitemap' => false,
    'tree'    => $page(array(
        $page_header(lang('My Finance Requests'), lang('Your finance requests, their status and your adviser\'s messages, newest first.')),
        $el('section', 'py-5', lang('Content'), array(
            $container(array(
                $row(array(
                    $col(array($widget('my_finance_list')), '', '8'),
                    $col(array(
                        $el('div', 'p-4 rounded-3 bg-body-tertiary', lang('New Request'), array(
                            $el('p', 'd-flex align-items-center gap-2 fw-semibold mb-2', lang('Title'), array(
                                $icon('bi-wallet2', 'text-primary'),
                                $span(lang('Another car?'), '', lang('Text')),
                            )),
                            $para(lang('Open its page and ask about finance from there; your details are filled in for you.'), 'small text-body-secondary mb-3'),
                            $link(lang('See the stock'), '{{page:vehicles}}', 'btn btn-sm btn-primary rounded-pill px-3'),
                        )),
                    ), '', '4'),
                ), '4', '', 'g-lg-5'),
            )),
        )),
    ), false),
);

// The staff's list of every finance request, newest first, with a search.
$fin_pages[] = array(
    'key'     => 'staff_finance',
    'name'    => lang('all-finance-requests'),
    'folder'  => 'private',
    'title'   => lang('All Finance Requests'),
    'search'  => false,
    'sitemap' => false,
    'tree'    => $staff_page(array(
        $page_header(lang('All Finance Requests'), lang('Every finance request, newest first: call the customer, set the status and answer under each.')),
        $el('section', 'py-5', lang('Content'), array(
            $container(array($widget('staff_finance_list'))),
        )),
    )),
);

// The e-mails: what the staff receive and the reply to the sender. A
// private folder, out of the search and the sitemap.
$fin_mail_settings = array('folder' => 'private', 'search' => false, 'sitemap' => false, 'noindex' => true);
$fin_pages[] = array_merge($fin_mail_settings, array(
    'key'   => 'email_finance_new',
    'name'  => lang('email-new-finance-request'),
    'title' => lang('New Finance Request E-mail'),
    'tree'  => $fin_mail_page(array($widget('email_finance_admin'))),
));
$fin_pages[] = array_merge($fin_mail_settings, array(
    'key'   => 'email_finance_received',
    'name'  => lang('email-finance-request-received'),
    'title' => lang('Finance Request Received E-mail'),
    'tree'  => $fin_mail_page(array($widget('email_finance_client'))),
));

// ── Widgets ─────────────────────────────────────────────────────────────

$fin_widgets = array(

    // The finance request: the fields are the page's form. The Vehicle
    // starts with the car the vehicle page sends (?vehicle=), the Way to
    // pay with the plan the finance page's card sends (?plan=). The Status
    // is the staff's (office use only): every request starts as received.
    'finance_form' => array(
        'page'   => 'finance_apply',
        'slug'   => 'form',
        'config' => array('regionType' => 'custom_form', 'form_source' => 'page'),
        'tree'   => $widget_root(array(
            $form_step(1, lang('The car and the way to pay')),
            $field('dl-fi-vehicle', lang('Vehicle'), $input('dl-fi-vehicle', 'text', 'vehicle', 'off', false, array('url_param' => 'vehicle')), 'mb-3',
                lang('The car you have in mind; its page\'s button fills it in. Leave it empty if you have not chosen yet.')),
            $field('dl-fi-plan', lang('Way to pay'), $select('dl-fi-plan', 'plan', '', $plan_choices, true, $plan_choices[0], array('url_param' => 'plan'))),
            $row(array(
                $col(array($field('dl-fi-deposit', lang('Deposit'), $select('dl-fi-deposit', 'deposit', lang('Choose a deposit…'), $deposit_choices))), '6'),
                $col(array($field('dl-fi-term', lang('Loan term'), $select('dl-fi-term', 'term', lang('Choose a term…'), $term_choices))), '6'),
            ), '3'),
            $form_step(2, lang('Your situation')),
            $el('div', 'alert alert-secondary d-flex gap-2 small', lang('Bands Note'), array(
                $icon('bi-shield-lock', 'mt-1'),
                $span(lang('We only ask for bands here. Never write an ID number or a bank account number in this form.'), '', lang('Text')),
            ), array('_attrs' => array($attr('role', 'note')))),
            $row(array(
                $col(array($field('dl-fi-employment', lang('Employment status'), $select('dl-fi-employment', 'employment', lang('Choose one…'), $employment_choices))), '6'),
                $col(array($field('dl-fi-income', lang('Monthly income'), $select('dl-fi-income', 'income', lang('Choose a band…'), $income_choices))), '6'),
            ), '3'),
            $field('dl-fi-note', lang('Anything we should know?'), $textarea('dl-fi-note', 'note', 3, false), 'mb-3',
                lang('A monthly payment you would like to stay under, a trade-in, or a good time to call.')),
            $form_step(3, lang('How to reach you')),
            $radios('dl-fi-contact', 'contact_method', lang('Best way to reach you'), $contact_choices, lang('Phone')),
            $row(array(
                $col(array($field('dl-fi-first-name', lang('First Name'), $input('dl-fi-first-name', 'text', 'first_name', 'given-name', true, array('contact_field' => 'first_name')))), '6'),
                $col(array($field('dl-fi-last-name', lang('Last Name'), $input('dl-fi-last-name', 'text', 'last_name', 'family-name', true, array('contact_field' => 'last_name')))), '6'),
                $col(array($field('dl-fi-email', lang('Email'), $input('dl-fi-email', 'email', 'email', 'email', true, array('contact_field' => 'email_address')))), '6'),
                $col(array($field('dl-fi-phone', lang('Phone'), $input('dl-fi-phone', 'tel', 'phone', 'tel', true, array('contact_field' => 'mobile_phone')))), '6'),
            ), '3'),
            $field('dl-fi-status', lang('Status'), $select('dl-fi-status', 'status', '', $finance_statuses, false, $finance_statuses[0], array('office_use_only' => true))),
            $agree('dl-fi-consent', 'consent', lang('I agree that the dealer keeps these details to work out my finance and answer me, as its privacy notice explains.'), true),
            $captcha(),
            $el('div', 'd-flex flex-wrap align-items-center gap-3', lang('Send Area'), array(
                $submit(lang('Ask for the Figures'), 'btn btn-primary btn-lg rounded-pill px-4'),
                $para(lang('No credit check is made when you send this form.'), 'small text-body-secondary mb-0'),
            )),
            $loop(array()),
        )),
    ),

    // One finance request: the not-found notice, the sent notice, the new
    // account notice, the way back and the Change Status button, then the
    // request's card, the note and where to write; the messages follow as
    // comments.
    'finance_detail' => $fin_detail_config('finance_view', 'submitter_only') + array(
        'tree' => array('type' => 'root', 'props' => array(), 'children' => array(
            $container(array(
                $row(array(
                    $col(array(
                        $el('div', 'text-center py-5', lang('Not Found'), array(
                            $icon('bi-wallet2', 'display-5 text-body-tertiary'),
                            $heading('h2', lang('Finance request not found'), 'h3 fw-semibold mt-3'),
                            $para($fin_missing, 'text-body-secondary mb-4', $bind('not_found')),
                            $link(lang('My Finance Requests'), '{{page:my_finance}}', 'btn btn-primary rounded-pill px-4'),
                        ), $show_when('record_not_found')),
                        $el('div', '', lang('Record'), array(
                            $el('div', 'alert alert-success d-flex gap-2', lang('Sent Notice'), array(
                                $icon('bi-check-circle', 'mt-1'),
                                $span(lang('Thank you, your request has reached us. Our adviser calls you within a working day; this page shows the status, and you can write to us below.'), '', lang('Text')),
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
                                    $span(lang('My Finance Requests'), '', lang('Link Text')),
                                ), array('href' => '{{page:my_finance}}')),
                                $edit_button(lang('Change Status')),
                            )),
                            $el('div', 'card border-0 shadow-sm rounded-3 mb-4', lang('Finance Card'), array(
                                $el('div', 'card-body p-4', lang('Card Body'), array(
                                    $el('div', 'd-flex flex-wrap align-items-start justify-content-between gap-2 mb-3', lang('Title'), array(
                                        $el('div', '', lang('Words'), array(
                                            $para(lang('Finance Request'), 'small text-uppercase fw-semibold text-primary mb-1'),
                                            $heading('h1', $fin_sample_vehicle, 'h3 fw-semibold mb-0', $bind('vehicle')),
                                        )),
                                        $span(lang('Request received'), 'badge rounded-pill bg-primary-subtle text-primary-emphasis fs-6', lang('Status'), $bind('status')),
                                    )),
                                    $el('dl', 'row small mb-0', lang('Details'), array_merge(
                                        $fin_detail(lang('Way to pay'), '', lang('Way to pay'), $fin_bound($fin_sample_plan, 'plan')),
                                        $fin_detail(lang('Deposit'), '', lang('Deposit'), $fin_bound($deposit_choices[2], 'deposit')),
                                        $fin_detail(lang('Loan term'), '', lang('Loan term'), $fin_bound($term_choices[1], 'term')),
                                        $fin_detail(lang('Employment status'), '', lang('Employment status'), $fin_bound($employment_choices[0], 'employment')),
                                        $fin_detail(lang('Monthly income'), '', lang('Monthly income'), $fin_bound($income_choices[1], 'income')),
                                        $fin_detail(lang('Best way to reach you'), '', lang('Contact by'), $fin_bound(lang('Phone'), 'contact_method')),
                                        $fin_detail(lang('Customer'), '', lang('Customer'), $fin_person_name(), true),
                                        $fin_detail(lang('Reference Code'), 'mb-0 font-monospace', lang('Reference Code'), $fin_bound('ABC123', 'reference_code'))
                                    )),
                                )),
                            )),
                            $el('div', 'pg-hide-if-empty p-4 rounded-3 bg-body-tertiary mb-4', lang('Anything we should know?'), array(
                                $para(lang('Anything we should know?'), 'small fw-semibold text-body-secondary mb-2'),
                                $el('div', 'pg-field-value lh-lg', lang('Value'), array(), $fin_bound($fin_sample_note, 'note')),
                            )),
                            $el('div', 'd-flex gap-2 small text-body-secondary', lang('Note'), array(
                                $icon('bi-chat-left-text', 'text-primary mt-1'),
                                $span(lang('A question about the figures? Write to your adviser below; we answer here and by e-mail. Never send an ID or bank number here.'), '', lang('Text')),
                            )),
                        ), $show_when('record_found')),
                    ), '', '8'),
                ), '', 'center'),
            ), 'py-5'),
        )),
    ),

    // The messages under the request: the page's own Comments widget, shown
    // only to whoever may read the request. Oldest first, all on one page,
    // with a file each. No Reply, Share or follow box: the customer is
    // e-mailed every answer and the dealer every message.
    'finance_messages' => array(
        'page'   => 'finance_view',
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
            'empty'     => lang('No messages yet. Write to your adviser here about the finance.'),
            'heading'   => lang('Write to your adviser'),
            'label'     => lang('Your message'),
            'sign_in'   => lang('Sign in to write to your adviser.'),
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

    'my_finance_list'    => $fin_list('my_finance', false),
    'staff_finance_list' => $fin_list('staff_finance', true),

    // The e-mail pages' widgets: drawn for the submission just sent.
    'email_finance_admin' => $fin_detail_config('email_finance_new', 'public') + array(
        'tree' => $widget_root(array(
            $fin_mail_heading(lang('A new finance request')),
            $fin_mail_para(lang('It came from the finance form. Call the customer with the monthly figures, then set the status on the request\'s page; what you write under it reaches the customer by e-mail.')),
            $fin_mail_request(),
            $fin_mail_client(),
            $fin_mail_button(lang('Open the request'), '{{page:finance_view}}?r=^^reference_code^^'),
            $fin_mail_reference(),
        )),
    ),
    'email_finance_client' => $fin_detail_config('email_finance_received', 'public') + array(
        'tree' => $widget_root(array(
            $fin_mail_heading(lang('We received your finance request')),
            $fin_mail_greeting(),
            $fin_mail_para(lang('Thank you. Our finance adviser works out the monthly figures and calls you within a working day; no credit check is made before you agree to one.')),
            $fin_mail_request(),
            $fin_mail_para(lang('A question about the figures? Write to us on the request\'s page. Never send an ID or a bank account number by e-mail; the adviser sees your papers in the showroom.')),
            $fin_mail_button(lang('Open my request'), '{{page:finance_view}}?r=^^reference_code^^'),
            $fin_mail_new_account(),
        )),
    ),
);

return array('pages' => $fin_pages, 'widgets' => $fin_widgets);
