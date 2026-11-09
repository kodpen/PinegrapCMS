<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Bakery", its wholesale enquiries: the enquiry a café, a
 * restaurant, a hotel or an office sends, the enquiry's own page with its
 * messages, My Enquiries and the staff's list, and the e-mails of the form
 * with the widgets that draw them. Included after parts.php, mail.php,
 * classes.php and requests.php in the same scope (it builds on the page
 * and list builders of requests.php, $bk_…); returns array('pages' => ...,
 * 'widgets' => ...).
 *
 * An enquiry is a request, never a sale: a record of the Wholesale
 * Enquiries form. The bakery answers under it (samples to taste, the bakes
 * and the delivery days agreed) and sets the office-use-only Status. A
 * visitor without an account gets one (auto-registration), so the
 * record's page and the visitor's list show it at once. The range starts
 * with what a range card sends (?range=, the range's title).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

$wholesale_pages = array();

// The sample enquiry the canvas shows. A start is words, never a date.
$sample_business = lang('Corner Coffee House');
$sample_business_kind = $business_choices[0];
$sample_range = $wholesale_ranges['pastry']['title'];
$sample_amounts = lang('About 60 croissants and 30 cinnamon buns a day, more at the weekend.');
$sample_days = $delivery_day_choices[0] . ', ' . $delivery_day_choices[3] . ', ' . $delivery_day_choices[4];
$sample_start = lang('The first week of next month');
$sample_trade_address = lang('Market Square 3, Old Town');
$sample_enquiry_notes = lang('We open at half past seven and would like the pastries by seven. Could we taste the croissants and the buns first?');

// A group of boxes that may all be ticked, side by side under its legend:
// the legend names the field. Each answer is its own words.
$bk_checkboxes = function ($id, $name, $legend, $options) use ($el, $attr) {
    $children = array($el('legend', 'form-label fs-6 mb-2', lang('Label'), array(), array('text' => $legend)));
    foreach (array_values($options) as $n => $o) {
        $rid = $id . '-' . ($n + 1);
        $children[] = $el('div', 'form-check form-check-inline', lang('Option'), array(
            $el('input', 'form-check-input', lang('Field'), array(), array('id' => $rid, '_attrs' => array(
                $attr('type', 'checkbox'), $attr('name', $name), $attr('value', $o),
            ))),
            $el('label', 'form-check-label', lang('Label'), array(), array('text' => $o, '_attrs' => array($attr('for', $rid)))),
        ));
    }
    return $el('fieldset', 'mb-3', $legend, $children);
};

// ── Pieces ──────────────────────────────────────────────────────────────

$enquiry_row = function ($staff = false) use ($bk_record_row, $sample_business, $sample_range, $sample_days, $sample_business_kind, $enquiry_statuses) {
    return $bk_record_row($staff, $sample_business, 'business', lang('Business'), $enquiry_statuses[0], array(
        array('bi-shop', lang('Kind of business'), $sample_business_kind, 'business_kind'),
        array('bi-basket', lang('Wholesale range'), $sample_range, 'range'),
        array('bi-truck', lang('Delivery days'), $sample_days, 'delivery_days'),
    ));
};
$mail_enquiry = function () use ($mail_box, $mail_line, $span, $bind, $sample_business, $sample_business_kind, $sample_range, $sample_amounts, $sample_days, $sample_start, $sample_trade_address) {
    $line = function ($label, $sample, $field) use ($mail_line, $span, $bind) {
        return $mail_line($label, array($span($sample, '', $label, $bind($field))));
    };
    return $mail_box(array(
        $line(lang('Business'), $sample_business, 'business'),
        $line(lang('Kind of business'), $sample_business_kind, 'business_kind'),
        $line(lang('Wholesale range'), $sample_range, 'range'),
        $line(lang('Amounts a week'), $sample_amounts, 'amounts'),
        $line(lang('Delivery days'), $sample_days, 'delivery_days'),
        $line(lang('Start from'), $sample_start, 'start_date'),
        $line(lang('Delivery address'), $sample_trade_address, 'address'),
    ), lang('Wholesale Enquiry'));
};

// ── Pages ───────────────────────────────────────────────────────────────

// The enquiry. What is sent opens the enquiry's own page.
$wholesale_pages[] = array(
    'key'              => 'wholesale_enquiry',
    'name'             => lang('wholesale-enquiry'),
    'folder'           => 'public',
    'title'            => lang('Wholesale Enquiry'),
    'meta_description' => lang('Bread and pastries for your café, restaurant, hotel or office: tell us what you need and when, and we bring samples to taste.'),
    'form'             => array(
        'form_name'            => lang('Wholesale Enquiries'),
        'confirmation_message' => lang('Thank you, your wholesale enquiry has reached the bakery. We answer within two working days to arrange a tasting.'),
        'confirmation_page_id' => '{{tab:enquiry_view}}',
        'auto_registration'    => 1,
        'notify_email'         => '{{site_email}}',
        'notify_subject'       => lang('New wholesale enquiry: ^^business^^'),
        'notify_page_id'       => '{{tab:email_enquiry_new}}',
        'confirm_email'        => 1,
        'confirm_subject'      => lang('We received your wholesale enquiry'),
        'confirm_page_id'      => '{{tab:email_enquiry_received}}',
    ),
    'tree'             => $bk_request_page(lang('Wholesale Enquiry'),
        lang('Tell us about your business, what you would like from the ovens and on which days. We answer within two working days and bring samples to taste.'),
        'wholesale_enquiry_form',
        $bk_request_aside(array(
            lang('Your enquiry opens its own page, and an account for you if you have none.'),
            lang('Within two working days we answer there and arrange a tasting at your place or ours.'),
            lang('We agree the bakes, the amounts and the delivery days under your enquiry.'),
            lang('The first crates arrive on your first delivery day, before you open.'),
        ), lang('What we bake for the trade'), $range_titles, lang('See the wholesale ranges'), '{{page:wholesale}}')),
);

$wholesale_pages[] = $bk_request_view('enquiry_view', lang('wholesale-enquiry-details'), lang('Wholesale Enquiry'),
    'enquiry_detail', 'enquiry_messages', lang('There is a new message about your wholesale enquiry'));

$wholesale_pages[] = $bk_my_page('my_enquiries', lang('my-wholesale-enquiries'), lang('My Wholesale Enquiries'),
    lang('Your wholesale enquiries, their status and the bakery\'s answers, newest first.'),
    'my_enquiries_list', lang('Another branch or range?'),
    lang('Send another enquiry; your details are filled in for you.'), lang('Send a wholesale enquiry'), 'wholesale_enquiry');

$wholesale_pages[] = $bk_staff_list_page('staff_enquiries', lang('all-wholesale-enquiries'), lang('Wholesale Enquiries'),
    lang('Every wholesale enquiry, newest first: arrange the tasting and agree the bakes under each, then set the status.'),
    'staff_enquiries_list');

// The e-mails: what the bakery receives and the reply to the sender. A new
// message under an enquiry is mailed with requests.php's email_reply.
$wholesale_pages[] = array_merge($mail_settings, array(
    'key'   => 'email_enquiry_new',
    'name'  => lang('email-new-wholesale-enquiry'),
    'title' => lang('New Wholesale Enquiry E-mail'),
    'tree'  => $mail_page(array($widget('email_enquiry_admin'))),
));
$wholesale_pages[] = array_merge($mail_settings, array(
    'key'   => 'email_enquiry_received',
    'name'  => lang('email-wholesale-enquiry-received'),
    'title' => lang('Wholesale Enquiry Received E-mail'),
    'tree'  => $mail_page(array($widget('email_enquiry_client'))),
));

// ── Widgets ─────────────────────────────────────────────────────────────

$enquiry_missing = lang('This wholesale enquiry could not be found, or it is not yours to see.');

$wholesale_widgets = array(

    // The enquiry: the fields are the page's form. The range starts with
    // what a range card sends (?range=). The Status is the bakery's
    // (office use only): every enquiry starts as Enquiry received.
    'wholesale_enquiry_form' => array(
        'page'   => 'wholesale_enquiry',
        'slug'   => 'form',
        'config' => array('regionType' => 'custom_form', 'form_source' => 'page'),
        'tree'   => $widget_root(array_merge(array(
            $form_step(1, lang('Your business')),
            $row(array(
                $col(array($field('bky-we-business', lang('Business'), $input('bky-we-business', 'text', 'business', 'organization', true))), '6'),
                $col(array($field('bky-we-kind', lang('Kind of business'), $select('bky-we-kind', 'business_kind', lang('Choose a kind…'), $business_choices))), '6'),
            ), '3'),
            $form_step(2, lang('What your business needs')),
            $field('bky-we-range', lang('Wholesale range'), $select('bky-we-range', 'range', lang('Choose a wholesale range…'), $range_titles, true, '', array('url_param' => 'range'))),
            $field('bky-we-amounts', lang('Amounts a week'), $textarea('bky-we-amounts', 'amounts', 3, true), 'mb-3',
                lang('Roughly what and how many: loaves, pastries, cakes or platters.')),
            $bk_checkboxes('bky-we-days', 'delivery_days', lang('Delivery days'), $delivery_day_choices),
            $row(array(
                $col(array($field('bky-we-start', lang('Start from'), $input('bky-we-start', 'date', 'start_date', 'off', true, $not_before_today), 'mb-3',
                    lang('The first delivery we can bring is a week after the tasting.'))), '6'),
                $col(array($field('bky-we-address', lang('Delivery address'), $input('bky-we-address', 'text', 'address', 'street-address', true))), '6'),
            ), '3'),
            $field('bky-we-menu', lang('Your menu'), $file('bky-we-menu', 'menu', 'private'), 'mb-3',
                lang('Your menu or price list helps us suggest the right bakes (a PDF, an office file or a picture).')),
            $field('bky-we-notes', lang('Anything else'), $textarea('bky-we-notes', 'notes', 3, false), 'mb-3',
                lang('Opening time, the latest delivery time, allergies your guests ask about.')),
            $form_step(3, lang('How to reach you')),
        ), $bk_contact_fields('bky-we'), array(
            $field('bky-we-status', lang('Status'), $select('bky-we-status', 'status', '', $enquiry_statuses, false, $enquiry_statuses[0], array('office_use_only' => true))),
            $agree('bky-we-consent', 'consent', lang('I agree that the bakery keeps these details to answer my enquiry and supply my business, as its privacy notice explains.'), true),
            $captcha(),
            $el('div', 'd-flex flex-wrap align-items-center gap-3', lang('Send Area'), array(
                $submit(lang('Send the Enquiry'), 'btn btn-primary btn-lg rounded-pill px-4'),
                $para(lang('We answer within two working days. Nothing is agreed until you say so.'), 'small text-body-secondary mb-0'),
            )),
            $loop(array()),
        ))),
    ),

    // One enquiry; the bakery's answers follow as comments.
    'enquiry_detail' => $bk_detail_widget('enquiry_view', 'wholesale_enquiry', $enquiry_missing, 'submitter_only') + array(
        'tree' => $bk_record_tree(array(
            'icon'          => 'bi-basket',
            'missing_title' => lang('Wholesale enquiry not found'),
            'missing'       => $enquiry_missing,
            'back'          => lang('My Wholesale Enquiries'),
            'back_key'      => 'my_enquiries',
            'thanks'        => lang('Thank you, your enquiry has reached the bakery. We answer here within two working days to arrange a tasting; this page shows the status, and you can write to us below.'),
            'eyebrow'       => lang('Wholesale Enquiry'),
            'title'         => $sample_business,
            'title_field'   => 'business',
            'status'        => $enquiry_statuses[0],
            'details'       => array_merge(
                $bk_detail(lang('Kind of business'), $bk_bound($sample_business_kind, 'business_kind')),
                $bk_detail(lang('Wholesale range'), $bk_bound($sample_range, 'range')),
                $bk_detail(lang('Amounts a week'), $bk_bound($sample_amounts, 'amounts')),
                $bk_detail(lang('Delivery days'), $bk_bound($sample_days, 'delivery_days')),
                $bk_detail(lang('Start from'), $bk_bound($sample_start, 'start_date')),
                $bk_detail(lang('Delivery address'), $bk_bound($sample_trade_address, 'address')),
                $bk_detail(lang('Your menu'), array(
                    $link('menu.pdf', '#', '', array(), array('target' => '_blank', 'rel' => 'noopener',
                        '_bindings' => array('href' => 'menu', 'text' => 'menu'))),
                ), true, 'text-break'),
                $bk_detail(lang('Name'), $bk_sender_name(), true),
                $bk_detail(lang('Reference Code'), $bk_bound('ABC123', 'reference_code'), false, 'mb-0 font-monospace')
            ),
            'words_label'   => lang('Anything else'),
            'words'         => $sample_enquiry_notes,
            'words_field'   => 'notes',
            'note'          => lang('Another branch, a change or a question? Write to us below; we answer here and by e-mail.'),
        )),
    ),

    'enquiry_messages' => $bk_messages_widget('enquiry_view',
        lang('No messages yet. The bakery\'s answer comes here; you can write to us about your enquiry too.')),

    'my_enquiries_list' => $bk_record_list('my_enquiries', 'wholesale_enquiry', 'enquiry_view', $enquiry_row(false), array(), '',
        lang('No wholesale enquiries yet. Tell us what your business needs, and follow our answer here.')),

    'staff_enquiries_list' => $bk_record_list('staff_enquiries', 'wholesale_enquiry', 'enquiry_view', $enquiry_row(true),
        array('business', 'first_name', 'last_name', 'email', 'business_kind', 'range', 'status'),
        lang('Search by business, name, e-mail, range or status...'), lang('No wholesale enquiries yet.')),

    // The e-mail pages' widgets: drawn for the submission just sent.
    'email_enquiry_admin' => $bk_detail_widget('email_enquiry_new', 'wholesale_enquiry', lang('The wholesale enquiry could not be found.'), 'public') + array(
        'tree' => $widget_root(array(
            $mail_heading(lang('A new wholesale enquiry')),
            $mail_para(lang('It came from the wholesale enquiry form. Arrange the tasting and agree the bakes under the enquiry on its page, and set the status; what you write there reaches the customer by e-mail.')),
            $mail_enquiry(),
            $bk_mail_sender(lang('Anything else'), $sample_enquiry_notes, 'notes'),
            $mail_button(lang('Open the enquiry'), '{{page:enquiry_view}}?r=^^reference_code^^'),
            $mail_reference(),
        )),
    ),
    'email_enquiry_client' => $bk_detail_widget('email_enquiry_received', 'wholesale_enquiry', lang('The wholesale enquiry could not be found.'), 'public') + array(
        'tree' => $widget_root(array(
            $mail_heading(lang('We received your wholesale enquiry')),
            $mail_greeting(),
            $mail_para(lang('Thank you. We answer within two working days, on the enquiry\'s page and by e-mail, to arrange a tasting.')),
            $mail_enquiry(),
            $mail_para(lang('Another branch or a change? Write to us on the enquiry\'s page; everything about your supply stays there.')),
            $mail_button(lang('Open my enquiry'), '{{page:enquiry_view}}?r=^^reference_code^^'),
            $mail_new_account(),
        )),
    ),
);

return array('pages' => $wholesale_pages, 'widgets' => $wholesale_widgets);
