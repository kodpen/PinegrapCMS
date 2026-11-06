<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Charity", its giving: the catalog (the Donate group with
 * a gift to where it is needed most and one per campaign), the donate page,
 * the pledge (a gift by bank transfer, from a company or in a will, followed
 * on its own page), the product, cart, checkout and order pages, the gift
 * receipt e-mail and the cart link of the account bar. Included after
 * parts.php, mail.php and forms.php in the same scope; returns
 * array('pages' => ..., 'widgets' => ..., 'catalog' => ...).
 *
 * A gift is a product with the donation selection type ('donation' =>
 * true, _pg_tpl_product_kind()): its price is the suggested amount, the
 * visitor types the amount they give in the cart, and it is neither taxed
 * nor shipped. The donate page always stands: without the shop it keeps
 * the gift amounts, the bank transfer box and the pledge form, and its list
 * of gifts (a widget that needs the shop) is left out.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

$donate_pages = array();

// ── The catalog ─────────────────────────────────────────────────────────
// Made once when the template is first opened (_pg_tpl_catalog()). The
// suggested amount of every gift is the second of the gift amounts, so the
// cart opens on a figure the donate page names.
$suggested_gift = $gift_amounts[1]['price'];
$gift_products = array(array(
    'sku'         => $general_sku,
    'title'       => lang('Where It Is Needed Most'),
    'description' => lang('A gift we put to work where the need is greatest: a well, a classroom, a clinic or relief after a disaster.'),
    'price'       => $suggested_gift,
    'donation'    => true,
    'featured'    => true,
    'keywords'    => lang('donation, gift, general fund, charity'),
));
foreach ($campaigns as $key => $c) {
    $gift_products[] = array(
        'sku'         => $campaign_sku($key),
        'title'       => $c['title'],
        'description' => $c['summary'] . ' ' . $c['story'],
        'price'       => $suggested_gift,
        'donation'    => true,
        'featured'    => !empty($c['featured']),
        'keywords'    => $c['place'] . ', ' . $causes[$c['cause']]['topics'],
    );
}

$catalog = array(
    'folder'  => 'pinegrap_charity_catalog',
    // The Details tab of every gift's page.
    'details' => array(
        lang('Type the amount you give in the cart; the suggested amount is only a start.'),
        lang('A receipt for your gift is sent by e-mail as soon as it arrives.'),
        lang('A sample gift of the template: change its name and suggested amount in the catalog, or remove it.'),
    ),
    'groups'  => array(
        'donate' => array(
            'parent'      => 'root',
            'title'       => lang('Donate'),
            'description' => lang('Give where it is needed most, or to one of our campaigns.'),
            'products'    => $gift_products,
        ),
    ),
);

// ── Pages ───────────────────────────────────────────────────────────────

// The gift amounts, as cards that open this page's gift list.
$donate_gift_cards = array();
foreach (array_keys($gift_amounts) as $n) $donate_gift_cards[] = $gift_card($n);

// The kinds of pledge, the values the Pledges form stores, and the
// campaigns a pledge may name (the same titles the gifts carry).
$pledge_kinds = array(
    lang('A bank transfer'),
    lang('A gift from my company'),
    lang('A gift in my will'),
    lang('A monthly standing order'),
);
$pledge_statuses = array(lang('Received'), lang('We contacted you'), lang('Gift arrived'), lang('Closed'));
$pledge_targets = array(lang('Where It Is Needed Most'));
foreach ($campaigns as $c) $pledge_targets[] = $c['title'];
$sample_pledge = lang('We would like to match what our staff give this year, and visit one of the wells with them.');

// The bank transfer box: sample details the site owner changes to its own.
$bank_box = $el('div', 'p-4 rounded-4 border h-100', lang('Bank Transfer'), array(
    $el('p', 'd-flex align-items-center gap-2 fw-semibold mb-3', lang('Title'), array(
        $icon('bi-bank', 'text-primary'),
        $span(lang('Give by bank transfer'), '', lang('Text')),
    )),
    $el('dl', 'row small mb-3', lang('Bank Details'), array(
        $el('dt', 'col-5 fw-normal text-body-secondary', lang('Label'), array(), array('text' => lang('Account name'))),
        $el('dd', 'col-7', lang('Value'), array(), array('text' => '{{site_name}}')),
        $el('dt', 'col-5 fw-normal text-body-secondary', lang('Label'), array(), array('text' => 'IBAN')),
        $el('dd', 'col-7 font-monospace text-break', lang('Value'), array(), array('text' => 'TR00 0000 0000 0000 0000 0000 00')),
        $el('dt', 'col-5 fw-normal text-body-secondary', lang('Label'), array(), array('text' => lang('Reference'))),
        $el('dd', 'col-7 mb-0', lang('Value'), array(), array('text' => lang('Your name and the campaign'))),
    )),
    $para(lang('Tell us about your transfer with the form below, so that we can thank you and send your receipt.'), 'small text-body-secondary mb-0'),
), $searchable('bank-transfer', lang('bank transfer, IBAN, wire, account number')));

$donate_pages[] = array(
    'key'              => 'donate',
    'name'             => lang('donate'),
    'folder'           => 'public',
    'title'            => lang('Donate'),
    'meta_description' => lang('Give once where it is needed most or to one of our campaigns, by card or bank transfer, and see what your gift can do.'),
    'form'             => array(
        'form_name'            => lang('Pledges'),
        'confirmation_message' => lang('Thank you for your pledge. We write to you within two working days; you can follow it from your account.'),
        'confirmation_page_id' => '{{tab:pledge_view}}',
        'auto_registration'    => 1,
        'notify_email'         => '{{site_email}}',
        'notify_subject'       => lang('New pledge: ^^pledge_kind^^'),
        'notify_page_id'       => '{{tab:email_pledge_new}}',
        'confirm_email'        => 1,
        'confirm_subject'      => lang('We received your pledge'),
        'confirm_page_id'      => '{{tab:email_pledge_received}}',
    ),
    'tree'             => $page(array(
        $el('section', 'py-5 bg-body-tertiary pch-hero', lang('Hero'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $para(lang('Your gift'), 'pch-eyebrow text-uppercase fw-semibold text-primary mb-2'),
                        $heading('h1', lang('Give today, see what it builds'), 'display-5 fw-semibold mb-3'),
                        $para(lang('Choose where your gift goes, type the amount in the cart and pay by card. Every gift gets a receipt by e-mail, and every campaign a report.'), 'lead text-body-secondary mb-0'),
                    ), '', '7'),
                    $col(array(
                        $el('div', 'p-4 rounded-4 bg-body shadow-sm', lang('In Short'), array(
                            $heading('h2', lang('Where the money goes'), 'h5 fw-semibold mb-3'),
                            $spending_bars(),
                            $link(lang('Transparency'), '{{page:transparency}}', 'small fw-semibold link-primary'),
                        )),
                    ), '', '5'),
                ), '4', '', 'g-lg-5 align-items-center'),
            )),
        )),
        $el('section', 'py-5', lang('Gift Amounts'), array(
            $container(array(
                $section_header(lang('Your gift'), lang('What your gift can do')),
                $row($donate_gift_cards, '4'),
            )),
        ), $searchable('gift-amounts', lang('how much, amount, what it buys'))),
        // The gifts of the catalog: where it is needed most and each
        // campaign. Left out on a site without the shop.
        $el('section', 'py-5 bg-body-tertiary', lang('Choose Your Gift'), array(
            $container(array(
                $section_header(lang('Give online'), lang('Choose where your gift goes'), lang('See our campaigns'), '{{page:campaigns}}'),
                $widget('donate_catalog'),
            )),
        ), $searchable('give-online', lang('donate online, card, pay, give now'))),
        $el('section', 'py-5', lang('Other Ways to Give'), array(
            $container(array(
                $section_header(lang('Other ways to give'), lang('By transfer, from a company or in a will')),
                $row(array(
                    $col(array($bank_box), '', '4'),
                    $col(array(
                        $el('div', 'card border-0 shadow-sm rounded-4', lang('Form Card'), array(
                            $el('div', 'card-body p-4 p-lg-5', lang('Card Body'), array(
                                $heading('h3', lang('Make a pledge'), 'h4 fw-semibold mb-2'),
                                $para(lang('A transfer, a company gift, a gift in your will or a monthly standing order: tell us, and we write to you to thank you and arrange it.'), 'text-body-secondary mb-4'),
                                $widget('pledge_form'),
                            )),
                        )),
                    ), '', '8'),
                ), '4', '', 'g-lg-5'),
            )),
        ), $searchable('pledge', lang('pledge, legacy, will, company, matching, standing order'))),
        $el('section', 'py-5 border-top bg-body-tertiary', lang('Promises'), array(
            $container(array($promise_row())),
        )),
    ), false),
);

// One pledge: what was sent, its status and the messages under it.
$donate_pages[] = array(
    'key'      => 'pledge_view',
    'name'     => lang('pledge'),
    'folder'   => 'registration',
    'title'    => lang('Pledge'),
    'search'   => false,
    'sitemap'  => false,
    'comments' => array('label' => lang('Message'), 'allow_new' => true, 'auto_publish' => true, 'show_date' => true, 'login' => true,
                        'email_page' => '{{tab:email_reply}}', 'email_subject' => lang('There is a new message about your pledge'),
                        'notify_email' => '{{site_email}}'),
    'tree'     => $page(array(
        $widget('pledge_detail'),
        $comments_section(lang('Messages'), 'pledge_messages'),
    ), false),
);

// The shop's own pages: the cart, the checkout and the order show the
// editor's layouts for their kinds, out of the search and the sitemap.
$gift_page = function ($key, $name, $title, $main) use ($page) {
    return array(
        'key'      => $key,
        'requires' => 'ecommerce',
        'name'     => $name,
        'folder'   => 'public',
        'title'    => $title,
        'search'   => false,
        'sitemap'  => false,
        'noindex'  => true,
        'tree'     => $page($main, false),
    );
};

// A gift: its page, then where the money goes.
$donate_pages[] = array_merge($gift_page('product', lang('gift'), lang('Gift'), array(
    $widget('product_detail'),
    $el('section', 'py-5 border-top bg-body-tertiary', lang('Promises'), array(
        $container(array(
            $promise_row(),
            $el('div', 'd-flex flex-wrap gap-2 mt-4', lang('Buttons'), array(
                $link(lang('See our campaigns'), '{{page:campaigns}}', 'btn btn-outline-primary rounded-pill px-4'),
                $link(lang('Where the money goes'), '{{page:transparency}}', 'btn btn-outline-secondary rounded-pill px-4'),
            )),
        )),
    )),
)), array('search' => true, 'sitemap' => true, 'noindex' => false, 'meta_description' => lang('A gift to one of our campaigns or where it is needed most.')));
$donate_pages[] = $gift_page('cart', lang('cart'), lang('Your Gifts'), array($widget('cart')));
$donate_pages[] = $gift_page('checkout', lang('checkout'), lang('Checkout'), array($widget('checkout')));
$donate_pages[] = $gift_page('order', lang('order-summary'), lang('Thank You'), array($widget('order_view')));

// The gift receipt, sent to the billing address when an order is placed.
$donate_pages[] = array_merge($mail_settings, array(
    'key'      => 'email_order',
    'requires' => 'ecommerce',
    'name'     => lang('email-order-receipt'),
    'title'    => lang('Gift Receipt E-mail'),
    'tree'     => $mail_page(array($widget('email_order'))),
));

// The pledge e-mails: what the staff receive and the reply to the sender.
$donate_pages[] = array_merge($mail_settings, array(
    'key'   => 'email_pledge_new',
    'name'  => lang('email-new-pledge'),
    'title' => lang('New Pledge E-mail'),
    'tree'  => $mail_page(array($widget('email_pledge_admin'))),
));
$donate_pages[] = array_merge($mail_settings, array(
    'key'   => 'email_pledge_received',
    'name'  => lang('email-pledge-received'),
    'title' => lang('Pledge Received E-mail'),
    'tree'  => $mail_page(array($widget('email_pledge_client'))),
));

// ── Widgets ─────────────────────────────────────────────────────────────

// A line of the receipt's totals, shown while its flag holds.
$gift_total_row = function ($name, $label, $token, $flag = '', $class = '', $style = 'text-align:right') use ($el, $span, $bind, $show_when, $mail_style) {
    return $el('tr', $class, $name, array(
        $el('td', 'ps-0' . ($class === '' ? ' text-body-secondary' : ''), lang('Label'), array($span($label, '', lang('Text')))),
        $el('td', 'pe-0 text-end', lang('Value'), array($span('$50.00', '', lang('Amount'), $bind($token))), $mail_style($style)),
    ), $flag !== '' ? $show_when($flag) : array());
};

// The lines of a pledge, for its e-mails.
$mail_pledge = function () use ($mail_box, $mail_line, $span, $bind, $pledge_kinds, $pledge_targets) {
    return $mail_box(array(
        $mail_line(lang('Kind of gift'), array($span($pledge_kinds[1], '', lang('Kind of gift'), $bind('pledge_kind')))),
        $mail_line(lang('For'), array($span($pledge_targets[0], '', lang('For'), $bind('campaign')))),
        $mail_line(lang('Amount'), array($span('5,000', '', lang('Amount'), $bind('amount')))),
    ), lang('Pledge'));
};
$pledge_missing = lang('This pledge could not be found, or it is not yours to see.');
$sample_gift = $money($suggested_gift);

$donate_widgets = array(

    // The gifts: the Donate group, the gift where it is needed most first.
    'donate_catalog' => array(
        'page'     => 'donate',
        'requires' => 'ecommerce',
        'slug'     => lang('catalog'),
        'config'   => array(
            'regionType'               => 'catalog_listing',
            'product_group_id'         => '{{product_group:donate}}',
            'group_navigation'         => 'drill_down',
            'search_enabled'           => false,
            'sort_control_enabled'     => false,
            'price_filter_enabled'     => false,
            'stock_filter_enabled'     => false,
            'order_by_field'           => 'sort_order',
            'order_by_direction'       => 'ASC',
            'items_per_page'           => 12,
            'detail_page_id'           => '{{tab:product}}',
            'add_to_cart_stay_on_page' => false,
            'add_to_cart_next_page_id' => '{{tab:cart}}',
            'empty_message'            => lang('No gifts to choose yet. You can give by bank transfer below.'),
        ),
        'tree'     => 'starter',
    ),

    // The way to the cart, in the account bar; left out without the shop.
    'cart_link' => array(
        'page'     => '',
        'requires' => 'ecommerce',
        'slug'     => lang('cart-link'),
        'config'   => array('regionType' => 'cart_link', 'cart_page_id' => '{{tab:cart}}'),
        'tree'     => 'starter',
    ),

    // The shop's pages draw the editor's own layouts for their kinds. A
    // gift's breadcrumb starts at the donate page.
    'product_detail' => array(
        'page'     => 'product',
        'requires' => 'ecommerce',
        'slug'     => lang('product-detail'),
        'config'   => array(
            'regionType'              => 'catalog_item_view',
            'product_group_id'        => 0,
            'next_page_id'            => '{{tab:cart}}',
            'catalog_listing_page_id' => '{{tab:donate}}',
            'catalog_root_label'      => lang('Donate'),
            'not_found_message'       => lang('Gift not found.'),
        ),
        'tree'     => 'starter',
    ),
    'cart' => array(
        'page'     => 'cart',
        'requires' => 'ecommerce',
        'slug'     => lang('cart'),
        'config'   => array(
            'regionType'                    => 'shopping_cart',
            'next_page_id_with_shipping'    => '{{tab:checkout}}',
            'next_page_id_without_shipping' => '{{tab:checkout}}',
            'detail_page_id'                => '{{tab:product}}',
        ),
        'tree'     => 'starter',
    ),
    'checkout' => array(
        'page'     => 'checkout',
        'requires' => 'ecommerce',
        'slug'     => lang('checkout'),
        'config'   => array(
            'regionType'                  => 'express_order',
            'next_page_id'                => '{{tab:order}}',
            'order_receipt_email_page_id' => '{{tab:email_order}}',
            'order_receipt_email_subject' => lang('Gift Receipt #'),
            'cart_section_label'          => lang('Your gifts'),
            'purchase_button_label'       => lang('Complete My Gift'),
            'update_button_label'         => lang('Update'),
        ),
        'tree'     => 'starter',
    ),
    'order_view' => array(
        'page'     => 'order',
        'requires' => 'ecommerce',
        'slug'     => lang('order'),
        'config'   => array('regionType' => 'order_view', 'date_format' => ''),
        'tree'     => 'starter',
    ),

    // The receipt: the number and date, the gifts, the total, the billing
    // address, what the gift does next and where to follow it.
    'email_order' => array(
        'page'     => 'email_order',
        'requires' => 'ecommerce',
        'slug'     => lang('order'),
        'config'   => array('regionType' => 'order_view', 'date_format' => ''),
        'tree'     => $widget_root(array(
            $mail_heading(lang('Thank you for your gift')),
            $el('p', 'mb-3', lang('Order Details'), array(
                $span(lang('Receipt Number') . ': ', 'text-body-secondary', lang('Label')),
                $span('PG-12345', 'fw-semibold', lang('Order Number'), $bind('__order_no')),
                $span(' · ', 'text-body-secondary', lang('Text')),
                $span(lang('Date'), '', lang('Date'), $bind('__order_date')),
            ), $mail_style('margin:0 0 16px')),
            $el('table', 'table align-middle mb-3', lang('Order Items'), array(
                $el('tbody', '', lang('Rows'), array(
                    $loop(array(
                        $el('tr', '', lang('Row'), array(
                            $el('td', 'ps-0', lang('Product'), array(
                                $span(lang('Where It Is Needed Most'), 'fw-semibold', lang('Product Name'), $bind('__item_short_description')),
                            ), $mail_style('padding:8px 0;border-bottom:1px solid #e5e7eb')),
                            $el('td', 'pe-0 text-end', lang('Total'), array(
                                $span($sample_gift, '', lang('Amount'), $bind('__item_total')),
                            ), $mail_style('padding:8px 0;border-bottom:1px solid #e5e7eb;text-align:right')),
                        )),
                    )),
                )),
            ), $mail_style('width:100%;border-collapse:collapse;margin:0 0 16px')),
            $el('table', 'table table-sm mb-4', lang('Totals'), array(
                $el('tbody', '', lang('Rows'), array(
                    $gift_total_row(lang('Subtotal'), lang('Subtotal'), '__order_subtotal', 'has_tax'),
                    $gift_total_row(lang('Tax'), lang('VAT'), '__order_tax', 'has_tax'),
                    $gift_total_row(lang('Total'), lang('Total'), '__order_total', '', 'fw-bold', 'text-align:right;font-weight:700'),
                )),
            ), $mail_style('width:100%;border-collapse:collapse;margin:0 0 24px')),
            $el('div', '', lang('Billing Address'), array(
                $el('p', 'small text-body-secondary mb-1', lang('Label'), array(), array('text' => lang('Billing Address'))),
                $el('div', '', lang('Address'), array(), array_merge(array('text' => lang('Jane Cooper')), $bind('__billing_address_html'))),
            ), $mail_style('margin:0 0 16px')),
            $mail_box(array(
                $el('p', 'fw-semibold mb-1', lang('Title'), array(), array_merge(array('text' => lang('What happens next')), $mail_style('font-weight:600;margin:0 0 4px'))),
                $mail_para(lang('Your gift goes to work in the field. Our News tells what it builds, and our reports show every account.'), 'mb-0', 'margin:0'),
            ), lang('Next Step')),
            $mail_button(lang('See what your gift builds'), '{{page:blog}}'),
            $mail_para(lang('This e-mail is the receipt for your gift; it is also on the order page:'), 'small mb-1', 'margin:0 0 4px;font-size:13px'),
            $link(lang('View your receipt'), '{{page:order}}?order_id=^^__order_id^^', 'small', array($attr('style', 'font-size:13px;color:#4b5563'))),
        )),
    ),

    // The pledge: the fields are the page's form. The campaign starts with
    // the campaign page's choice (?campaign=). The Status is the staff's
    // (office use only): every pledge starts as Received.
    'pledge_form' => array(
        'page'   => 'donate',
        'slug'   => 'form',
        'config' => array('regionType' => 'custom_form', 'form_source' => 'page'),
        'tree'   => $widget_root(array_merge(array(
            $form_step(1, lang('Your gift')),
            $row(array(
                $col(array($field('ch-pl-kind', lang('Kind of gift'), $select('ch-pl-kind', 'pledge_kind', lang('Choose how you give…'), $pledge_kinds))), '6'),
                $col(array($field('ch-pl-campaign', lang('For'), $select('ch-pl-campaign', 'campaign', '', $pledge_targets, true, $pledge_targets[0], array('url_param' => 'campaign')))), '6'),
            ), '3'),
            $field('ch-pl-amount', lang('Amount'), $input('ch-pl-amount', 'text', 'amount', 'off', false), 'mb-3',
                lang('If you know it: the amount, or the amount each month.')),
            $field('ch-pl-message', lang('Anything we should know?'), $textarea('ch-pl-message', 'message', 3, false)),
            $form_step(2, lang('About you')),
        ), $request_contact('ch-pl', $pledge_statuses,
            lang('I agree that the charity keeps these details to arrange my gift and thank me, as its privacy notice explains.'),
            lang('Send the Pledge'), lang('We write to you within two working days.')), array(
            $loop(array()),
        ))),
    ),

    // One pledge: what was sent and its status; the messages follow as
    // comments.
    'pledge_detail' => $request_detail_config('pledge_view', 'donate', $pledge_missing, 'submitter_only') + array(
        'tree' => $request_record_view('bi-heart', lang('Pledge not found'), $pledge_missing,
            'donate', lang('Donate'),
            lang('Thank you for your pledge. We write to you within two working days; this page shows the status, and you can write to us below.'),
            array(
                $el('div', 'card border-0 shadow-sm rounded-4 mb-4', lang('Pledge Card'), array(
                    $el('div', 'card-body p-4', lang('Card Body'), array(
                        $request_record_title(lang('Pledge'), $pledge_kinds[1], 'pledge_kind'),
                        $el('dl', 'row small mb-0', lang('Details'), array_merge(
                            $request_detail(lang('For'), '', lang('For'), $request_bound($pledge_targets[0], 'campaign')),
                            $request_detail(lang('Amount'), '', lang('Amount'), $request_bound('5,000', 'amount')),
                            $request_detail(lang('Donor'), '', lang('Donor'), $request_person(), true),
                            $request_detail(lang('Reference Code'), 'mb-0 font-monospace', lang('Reference Code'), $request_bound('ABC123', 'reference_code'))
                        )),
                    )),
                )),
                $request_record_text(lang('Anything we should know?'), 'message', $sample_pledge),
                $request_record_note(lang('A question about your gift? Write to us below; we answer here and by e-mail.')),
            )),
    ),

    'pledge_messages' => $request_messages('pledge_view',
        lang('No messages yet. Write to us here about your pledge.'),
        lang('Write to the charity'), lang('Sign in to write to the charity.')),

    // The e-mail pages' widgets: drawn for the pledge just sent.
    'email_pledge_admin' => $request_detail_config('email_pledge_new', 'donate', lang('The pledge could not be found.'), 'public') + array(
        'tree' => $widget_root(array(
            $mail_heading(lang('A new pledge')),
            $mail_para(lang('It came from the donate page. Write to the donor to thank them and arrange the gift, then set the status on the pledge\'s page; what you write under it reaches the donor by e-mail.')),
            $mail_pledge(),
            $mail_sender(array(
                $mail_line(lang('Anything we should know?'), array($span($sample_pledge, '', lang('Text'), $bind('message')))),
            )),
            $mail_button(lang('Open the pledge'), '{{page:pledge_view}}?r=^^reference_code^^'),
            $mail_reference(),
        )),
    ),
    'email_pledge_client' => $request_detail_config('email_pledge_received', 'donate', lang('The pledge could not be found.'), 'public') + array(
        'tree' => $widget_root(array(
            $mail_heading(lang('We received your pledge')),
            $mail_greeting(),
            $mail_para(lang('Thank you for your pledge. We write to you within two working days to thank you and arrange your gift.')),
            $mail_pledge(),
            $mail_para(lang('A question in the meantime? Write to us on the pledge\'s page; our answer comes there too.')),
            $mail_button(lang('Open my pledge'), '{{page:pledge_view}}?r=^^reference_code^^'),
            $mail_new_account(),
        )),
    ),
);

return array('pages' => $donate_pages, 'widgets' => $donate_widgets, 'catalog' => $catalog);
