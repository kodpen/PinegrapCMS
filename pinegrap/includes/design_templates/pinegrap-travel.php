<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Travel Agency": a travel agency and tour operator. A
 * home page drawn on the Travel blocks (a photo hero with the regions,
 * a tour day by day, a destination guide), with the featured tours, the
 * destinations, the deals and what travellers say; the destinations and the tours with filters by region,
 * destination and travel style, one page per tour with its photos, facts,
 * day-by-day itinerary and the travellers' reviews; travel information,
 * the deals, the about page and the questions; a booking request and a
 * tailor-made trip enquiry the traveller follows from their account, with
 * the status and the agency's answers; tour deposits and gift cards in the
 * shop when the shop is on; a contact page whose messages become
 * conversations, the Travel Journal (the blog) with its post page, a
 * newsletter, the sign-in and account pages, a staff area (the new tour
 * and destination forms, the booking requests, the enquiries, the blog and
 * staff directory forms), a traveller help that searches the whole site
 * and the e-mails. Built on Bootstrap 5. Read by pg_design_templates()
 * (includes/fn/designer.php).
 *
 * The pages are built in includes/design_templates/pinegrap-travel/ (not
 * read as templates of their own): parts.php holds the node builders, the
 * agency's data and the parts every page repeats; mail.php the e-mail
 * builders; pages_public.php, tours.php, bookings.php, enquiries.php,
 * shop.php, standard.php and account.php the pages and widgets. Each is
 * included here in this one scope, in that order, and uses the variables
 * the files before it set. parts.php keeps the sample data in $tours,
 * $destinations, $deals and $gift_cards, so no part's return is kept under
 * those names.
 *
 * Opening the template gives the site the newsletter's contact group
 * ('contact_groups'), made once and found again by its name the next time
 * (_pg_tpl_contact_groups()); the agency sends the newsletter to it by
 * hand. With the shop on it also gets the Tour Packages group and its
 * products, a deposit per sample tour and the gift cards ('catalog',
 * _pg_tpl_catalog()); only the shop pages need the shop, and the shared
 * parts never link them.
 *
 * A destination and a tour are records of their forms, collections: the
 * staff add one on the new destination or new tour page and the lists show
 * it at once, filtered by the address (?region=, ?destination=, ?style=). A
 * booking request and a tailor-made enquiry are records of their own
 * forms, requests and never reservations: a traveller without an account
 * gets one (auto-registration), so the request's page and the account's
 * list show it at once. A request's Status is an office-use-only field the
 * staff change in the control panel; the messages under it are the page's
 * comments, mailed to the traveller and to the agency. The template keeps
 * no seat counts and no prices per date.
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
// is kept under a _part name ($tours, $destinations, $deals and $gift_cards
// are parts.php's sample data).
$travel_dir = PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-travel';
include $travel_dir . '/parts.php';
include $travel_dir . '/mail.php';
$public_part   = include $travel_dir . '/pages_public.php';
$tour_part     = include $travel_dir . '/tours.php';
$booking_part  = include $travel_dir . '/bookings.php';
$enquiry_part  = include $travel_dir . '/enquiries.php';
$shop_part     = include $travel_dir . '/shop.php';
$standard_part = include $travel_dir . '/standard.php';
$account_part  = include $travel_dir . '/account.php';

// The pages in the order the editor lists them: the visitor's pages, the
// destinations and tours, the booking requests, the tailor-made enquiries,
// the shop, the standard pages, the account and staff pages, the e-mails
// last of each part.
$travel_parts = array($public_part, $tour_part, $booking_part, $enquiry_part, $shop_part, $standard_part, $account_part);
$pages   = array();
$widgets = array();
foreach ($travel_parts as $part) {
    $pages   = array_merge($pages, $part['pages']);
    $widgets = array_merge($widgets, $part['widgets']);
}

// ── Search sections ─────────────────────────────────────────────────────
// The parts of the pages the site search points a result at (Search and
// SERP in the editor's options): a result that matches one links to it,
// the words are marked when the visitor arrives, and the page's structured
// data names it. A part is found by its id ('#…') or by the name it has in
// the layer tree, once per page; it keeps an id it already has. The topics
// are words a visitor may search for that the part does not say itself.
// The about page, travel information, the deals and the privacy notice are
// marked where they are built.
$search_sections = array(
    'contact' => array(
        lang('Contact Details') => array('contact-details', lang('address, phone, e-mail, office, opening hours')),
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
// Two fonts: a round geometric sans for the headings, the agency's name
// and the prices, a friendly sans for the rest.
$travel_fonts = array(
    array('family' => 'Poppins', 'weights' => array('500', '600', '700'), 'enabled' => true),
    array('family' => 'Nunito Sans', 'weights' => array('400', '600', '700'), 'enabled' => true),
);

// The template's own CSS in User Styles, after the block rules the pages
// use. Every class is named ptv- (the field ids are tv-); no block rule's
// class is named here. Selectors use
// descendants and pseudo-classes only, so they hold on the editor's canvas
// too. Colours are tokens only, so they follow any palette in both themes.
$travel_css = implode("\n", array(
    ':root,[data-bs-theme]{--pg-font-heading:"Poppins",system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;'
        . '--pg-font-body:"Nunito Sans",system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;'
        . '--pg-heading-weight:600;--pg-display-weight:700;--pg-heading-tracking:-.01em;--pg-heading-line-height:1.2}',
    'h1,h2,h3,h4,h5,h6,.h1,.h2,.h3,.h4,.h5,.h6,.display-1,.display-2,.display-3,.display-4,.display-5,.display-6{font-family:var(--pg-font-heading)}',
    'body{font-family:var(--pg-font-body)}',
    // Titles and figures: the heading font, a little tighter.
    '.ptv-display{font-family:var(--pg-font-heading);font-weight:700;letter-spacing:-.01em}',
    // The small line over a title: spaced capitals.
    '.ptv-eyebrow{letter-spacing:.14em;font-size:.75rem}',
    // The agency's name in the header and the footer, in the heading font.
    '.ptv-brand{font-family:var(--pg-font-heading);font-weight:700;letter-spacing:-.01em}',
    // A price: figures of one width, so the prices of a list line up.
    '.ptv-price{font-variant-numeric:tabular-nums}',
    // What a traveller says: a little larger, in italics.
    '.ptv-quote{font-style:italic;font-size:1.125rem;line-height:1.6}',
    // Tour, destination and product cards rise a little under the pointer.
    '.ptv-lift{transition:transform .2s ease,box-shadow .2s ease}',
    '.ptv-lift:hover,.ptv-lift:focus-visible,.ptv-lift:focus-within{transform:translateY(-3px);box-shadow:var(--bs-box-shadow)}',
    '@media (prefers-reduced-motion:reduce){.ptv-lift{transition:none}.ptv-lift:hover,.ptv-lift:focus-visible,.ptv-lift:focus-within{transform:none}}',
));

return array(
    'name'        => lang('Travel Agency'),
    'version'     => '2.0.1',
    'framework'   => 'bootstrap5',
    'order'       => 83,
    'icon'        => 'bi-airplane',
    'description' => lang('A travel agency or tour operator: destinations and tours with a page each, a day-by-day itinerary and filters by region and style, booking requests and tailor-made trip enquiries the traveller follows from their account, travellers\' reviews on each tour, deposits and gift cards with the shop on, travel information, the Travel Journal, a newsletter, an inbox, a staff area and a traveller help that searches the whole site.'),

    // The theme the template is made for; the operator can pick another
    // before opening it, and change it later in the editor. The e-mails'
    // inline colours are this palette's blue. The template's own look
    // travels in its User Styles ('assets'), so it holds under any look and
    // palette.
    'look'        => 'modern-soft',
    'palette'     => 'sky',

    // What the installer lists on the template's card, after the points
    // every template shares.
    'highlights'  => array(
        lang('Destinations and tours as collections: a page per tour with its photos, facts and day-by-day itinerary, filters by region, destination and style, and sample tours the staff replace from a form.'),
        lang('Booking requests by tour and departure, with their own page, status and the agency\'s answers, and tailor-made trip enquiries followed the same way.'),
        lang('Travellers\' reviews with stars on each tour, the deals, travel information and the questions.'),
        lang('Deposits and gift cards with the shop on, the Travel Journal, a newsletter, an inbox, a staff area and a traveller help.'),
    ),

    // Made (or found again) when the template is opened; see _pg_tpl_folders().
    'folders'     => array(
        'root'         => array('name' => 'pinegrap_travel', 'access' => 'public'),
        'public'       => array('name' => 'public', 'parent' => 'root', 'access' => 'public'),
        'registration' => array('name' => 'registration', 'parent' => 'root', 'access' => 'registration'),
        'private'      => array('name' => 'private', 'parent' => 'root', 'access' => 'private'),
    ),

    'catalog'        => $shop_part['catalog'],

    'contact_groups' => array(
        'newsletter' => array(
            'name'         => lang('Travel Newsletter'),
            'description'  => lang('The addresses left on the newsletter form of the travel agency.'),
            'subscription' => true,
        ),
    ),

    'pages'       => $pages,

    'widgets'     => $widgets,

    'shared'      => array(
        'account_bar'  => array('name' => lang('Travel Account Bar'), 'tree' => $account_bar_tree()),
        'notice'       => array('name' => lang('Travel Notice'), 'tree' => $notice()),
        'header'       => array('name' => lang('Travel Header'), 'tree' => $header()),
        'footer'       => array('name' => lang('Travel Footer'), 'tree' => $footer()),
        'staff_bar'    => array('name' => lang('Travel Staff Bar'), 'tree' => $staff_bar()),
        'trip_band'    => array('name' => lang('Travel Trip Band'), 'tree' => $trip_band()),
        'email_header' => array('name' => lang('Travel E-mail Header'), 'tree' => $email_header_tree()),
        'email_footer' => array('name' => lang('Travel E-mail Footer'), 'tree' => $email_footer_tree()),
    ),

    // The template's own look: the rules of the blocks its pages place
    // (the Hero (Image Background) and the two Travel blocks), then its
    // ptv- rules, in one User Styles entry; and its two fonts.
    'assets'      => array(
        'css'   => pg_design_template_user_styles(array('hero-image-bg', 'travel-itinerary', 'travel-destination'), $travel_css),
        'fonts' => json_encode($travel_fonts),
    ),
);
