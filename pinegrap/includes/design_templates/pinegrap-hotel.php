<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Hotel": a family-run hotel by the sea. A home page with
 * the featured rooms, the board plans, the offers and what guests say; the
 * rooms with filters by kind and view, one page per room with its photos,
 * facts and amenities; the rates, the offers, dining, the spa, getting
 * here, the about page and the questions; a booking request and an event
 * enquiry the guest follows from their account, with the status and the
 * hotel's answers; weddings and meetings with the event spaces; gift
 * vouchers in the shop when the shop is on; a contact page whose messages
 * become conversations, the Hotel Journal (the blog) with its post page, a
 * newsletter, the sign-in and account pages, a staff area (the new room
 * form, the booking requests, the event enquiries, the blog and staff
 * directory forms), a guest help that searches the whole site and the
 * e-mails. Built on Bootstrap 5. Read by pg_design_templates()
 * (includes/fn/designer.php).
 *
 * The pages are built in includes/design_templates/pinegrap-hotel/ (not
 * read as templates of their own): parts.php holds the node builders, the
 * hotel's data and the parts every page repeats; mail.php the e-mail
 * builders; pages_public.php, rooms.php, bookings.php, events.php,
 * shop.php, standard.php and account.php the pages and widgets. Each is
 * included here in this one scope, in that order, and uses the variables
 * the files before it set. parts.php keeps the sample rooms in $rooms and
 * the gift vouchers in $vouchers, so no part's return is kept under those
 * names.
 *
 * Opening the template gives the site the newsletter's contact group
 * ('contact_groups'), made once and found again by its name the next time
 * (_pg_tpl_contact_groups()); the hotel sends the newsletter to it by hand.
 * With the shop on it also gets the Gift Vouchers group and its products
 * ('catalog', _pg_tpl_catalog()); only the shop pages need the shop, and
 * the shared parts never link them.
 *
 * A room is a record of the Rooms form, a collection: the staff add one on
 * the new room page and the lists show it at once, filtered by the address
 * (?type=, ?view=). A booking request and an event enquiry are records of
 * their own forms, requests and never reservations: a guest without an
 * account gets one (auto-registration), so the request's page and the
 * account's list show it at once. A request's Status is an office-use-only
 * field the staff change in the control panel; the messages under it are
 * the page's comments, mailed to the guest and to the hotel. The template
 * keeps no room stock and no prices per date.
 *
 * Placeholders, filled when the template is opened (pg_design_template_prepare()):
 *   {{page:<key>}}                  address of the template page with that key
 *   {{tab:<key>}}                   that page in a widget setting; its id once published
 *   {{folder:<key>}}                id of that template folder
 *   {{contact_group:<key>}}         id of a contact group below
 *   {{site_name}}, {{site_email}}, {{year}}
 * See includes/design_templates/hello-pinegrap.php for widgets, shared
 * components, 'catalog' and 'requires'.
 *
 * The version follows the template, not the software: raise it whenever the
 * pages change, so a design records which edition it started from.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

// The parts set variables of their own in this scope, so what each returns
// is kept under a _part name ($rooms and $vouchers are parts.php's sample
// data).
$hotel_dir = PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-hotel';
include $hotel_dir . '/parts.php';
include $hotel_dir . '/mail.php';
$public_part   = include $hotel_dir . '/pages_public.php';
$room_part     = include $hotel_dir . '/rooms.php';
$booking_part  = include $hotel_dir . '/bookings.php';
$event_part    = include $hotel_dir . '/events.php';
$shop_part     = include $hotel_dir . '/shop.php';
$standard_part = include $hotel_dir . '/standard.php';
$account_part  = include $hotel_dir . '/account.php';

// The pages in the order the editor lists them: the visitor's pages, the
// rooms, the booking requests, the events, the shop, the standard pages,
// the account and staff pages, the e-mails last of each part.
$pages = array_merge($public_part['pages'], $room_part['pages'], $booking_part['pages'], $event_part['pages'],
    $shop_part['pages'], $standard_part['pages'], $account_part['pages']);
$widgets = array_merge($public_part['widgets'], $room_part['widgets'], $booking_part['widgets'], $event_part['widgets'],
    $shop_part['widgets'], $standard_part['widgets'], $account_part['widgets']);

// ── Search sections ─────────────────────────────────────────────────────
// The parts of the pages the site search points a result at (Search and
// SERP in the editor's options): a result that matches one links to it,
// the words are marked when the visitor arrives, and the page's structured
// data names it. A part is found by its id ('#…') or by the name it has in
// the layer tree, once per page; it keeps an id it already has. The topics
// are words a visitor may search for that the part does not say itself.
// The about page, dining, spa, getting here, the rates, the questions and
// the privacy notice are marked where they are built.
$search_sections = array(
    'contact' => array(
        lang('Contact Details') => array('contact-details', lang('address, phone, e-mail, reception, front desk')),
        lang('Inbox Note')      => array('inbox-note', lang('answer, reply, conversation, inbox')),
    ),
);
$searchable_tree = function ($node, $marks, &$used) use (&$searchable_tree) {
    if (!is_array($node)) return $node;
    if (isset($node['props']) && is_array($node['props'])) {
        $p = $node['props'];
        $name = isset($p['customName']) ? (string)$p['customName'] : (isset($p['_label']) ? (string)$p['_label'] : '');
        $key = null;
        if (!empty($p['id']) && isset($marks['#' . $p['id']]) && !isset($used['#' . $p['id']])) {
            $key = '#' . $p['id'];
        } elseif ($name !== '' && isset($marks[$name]) && !isset($used[$name])) {
            $key = $name;
        }
        if ($key !== null) {
            $used[$key] = true;
            if (empty($p['id'])) $node['props']['id'] = $marks[$key][0];
            $node['props']['searchSection'] = true;
            if ($marks[$key][1] !== '') $node['props']['searchKeywords'] = $marks[$key][1];
        }
    }
    if (!empty($node['children']) && is_array($node['children'])) {
        foreach ($node['children'] as $i => $child) $node['children'][$i] = $searchable_tree($child, $marks, $used);
    }
    return $node;
};
foreach ($pages as $i => $p) {
    if (!isset($search_sections[$p['key']])) continue;
    $used = array();
    $pages[$i]['tree'] = $searchable_tree($p['tree'], $search_sections[$p['key']], $used);
}

// ── The template's own look ─────────────────────────────────────────────
// Two fonts: a high-contrast serif for the headings, the figures and the
// guests' words, a quiet sans for the rest.
$hotel_fonts = array(
    array('family' => 'Playfair Display', 'weights' => array('500', '600'), 'enabled' => true),
    array('family' => 'DM Sans', 'weights' => array('400', '500', '600'), 'enabled' => true),
);

// The template's own CSS in User Styles. Every class is named pht- (the
// field ids are ht-); no block rule's class is named here. Selectors use
// descendants and pseudo-classes only, so they hold on the editor's canvas
// too. Colours are tokens only, so they follow any palette in both themes.
$hotel_css = implode("\n", array(
    ':root,[data-bs-theme]{--pg-font-heading:"Playfair Display",Georgia,"Times New Roman",serif;'
        . '--pg-font-body:"DM Sans",system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;'
        . '--pg-heading-weight:500;--pg-display-weight:500;--pg-heading-tracking:0;--pg-heading-line-height:1.2}',
    'h1,h2,h3,h4,h5,h6,.h1,.h2,.h3,.h4,.h5,.h6,.display-1,.display-2,.display-3,.display-4,.display-5,.display-6{font-family:var(--pg-font-heading)}',
    'body{font-family:var(--pg-font-body)}',
    // Titles and figures: the serif, a little lighter.
    '.pht-display{font-family:var(--pg-font-heading);font-weight:500}',
    // The small line over a title: spaced capitals.
    '.pht-eyebrow{letter-spacing:.16em;font-size:.75rem}',
    // The hotel's name in the header and the footer, in the serif.
    '.pht-brand{font-family:var(--pg-font-heading);font-weight:600;letter-spacing:.01em}',
    // A rate or a price: figures of one width, so a table's prices line up.
    '.pht-price{font-variant-numeric:tabular-nums}',
    // What a guest says: the serif in italics.
    '.pht-quote{font-family:var(--pg-font-heading);font-style:italic;font-size:1.125rem;line-height:1.6}',
    // Room, space and voucher cards rise a little under the pointer.
    '.pht-lift{transition:transform .2s ease,box-shadow .2s ease}',
    '.pht-lift:hover,.pht-lift:focus-visible,.pht-lift:focus-within{transform:translateY(-3px);box-shadow:var(--bs-box-shadow)}',
    '@media (prefers-reduced-motion:reduce){.pht-lift{transition:none}.pht-lift:hover,.pht-lift:focus-visible,.pht-lift:focus-within{transform:none}}',
));

return array(
    'name'        => lang('Hotel'),
    'version'     => '2.0.0',
    'framework'   => 'bootstrap5',
    'order'       => 76,
    'icon'        => 'bi-buildings',
    'description' => lang('A hotel by the sea: the rooms with a page each and filters by kind and view, the rates and board plans, offers, dining, the spa, weddings and meetings, booking requests and event enquiries the guest follows from their account, gift vouchers with the shop on, the Hotel Journal, a newsletter, an inbox, a staff area and a guest help that searches the whole site.'),

    // The theme the template is made for; the operator can pick another
    // before opening it, and change it later in the editor. The e-mails'
    // inline colours are this palette's warm browns. The template's own
    // look travels in its User Styles ('assets'), so it holds under any
    // look and palette.
    'look'        => 'elegant',
    'palette'     => 'cocoa',

    // What the installer lists on the template's card, after the points
    // every template shares.
    'highlights'  => array(
        lang('The rooms with a page each, filters by kind and view, and sample rooms the staff replace from a form.'),
        lang('Booking requests by room, board plan and offer, with their own page, status and the reception\'s answers.'),
        lang('Weddings and meetings with the event spaces and an enquiry followed from the account.'),
        lang('Gift vouchers with the shop on, the Hotel Journal, a newsletter, an inbox, a staff area and a guest help.'),
    ),

    // Made (or found again) when the template is opened; see _pg_tpl_folders().
    'folders'     => array(
        'root'         => array('name' => 'pinegrap_hotel', 'access' => 'public'),
        'public'       => array('name' => 'public', 'parent' => 'root', 'access' => 'public'),
        'registration' => array('name' => 'registration', 'parent' => 'root', 'access' => 'registration'),
        'private'      => array('name' => 'private', 'parent' => 'root', 'access' => 'private'),
    ),

    'catalog'        => $shop_part['catalog'],

    'contact_groups' => array(
        'newsletter' => array(
            'name'         => lang('Hotel Newsletter'),
            'description'  => lang('The addresses left on the newsletter form of the hotel.'),
            'subscription' => true,
        ),
    ),

    'pages'       => $pages,

    'widgets'     => $widgets,

    'shared'      => array(
        'account_bar'  => array('name' => lang('Hotel Account Bar'), 'tree' => $account_bar_tree()),
        'notice'       => array('name' => lang('Hotel Notice'), 'tree' => $notice()),
        'header'       => array('name' => lang('Hotel Header'), 'tree' => $header()),
        'footer'       => array('name' => lang('Hotel Footer'), 'tree' => $footer()),
        'staff_bar'    => array('name' => lang('Hotel Staff Bar'), 'tree' => $staff_bar()),
        'booking_band' => array('name' => lang('Hotel Booking Band'), 'tree' => $booking_band()),
        'email_header' => array('name' => lang('Hotel E-mail Header'), 'tree' => $email_header_tree()),
        'email_footer' => array('name' => lang('Hotel E-mail Footer'), 'tree' => $email_footer_tree()),
    ),

    // The template's own look: its pht- rules in one User Styles entry, and
    // its two fonts.
    'assets'      => array(
        'css'   => pg_design_template_user_styles(array(), $hotel_css),
        'fonts' => json_encode($hotel_fonts),
    ),
);
