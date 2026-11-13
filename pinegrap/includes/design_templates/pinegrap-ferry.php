<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Ferry": a ferry line between two mainland ports and two
 * islands. A home page with the routes, the latest travel notices, what is
 * on board and the News; the routes with a page per route (the two ports,
 * the crossing time, the ship, the sailings and the fares as words), the
 * ports (check-in, parking, how to get there), the timetables per route
 * and season, the fares per kind (foot passengers, cars, motorcycles,
 * campervans and vans, bicycles, cabins), what is on board, travelling
 * with us, the about page and the questions; the Travel Notices collection
 * (disruptions, timetable changes, weather, port works) with a filter by
 * route and a page per notice; a booking request and a group enquiry the
 * traveller follows from their account, with the status and the booking
 * office's answers; a contact page whose messages become conversations,
 * the News (the blog) with its post page, a newsletter, the sign-in and
 * account pages, a staff area (the new notice form, the two request lists,
 * the blog and staff directory forms), a help that searches the whole site
 * and the e-mails. Built on Bootstrap 5.
 * Read by pg_design_templates() (includes/fn/designer.php).
 *
 * The pages are built in includes/design_templates/pinegrap-ferry/ (not
 * read as templates of their own): parts.php holds the node builders, the
 * line's data and the parts every page repeats; mail.php the e-mail
 * builders; pages_public.php, notices.php, bookings.php, standard.php and
 * account.php the pages and widgets. Each is included here in this one
 * scope, in that order, and uses the variables the files before it set.
 * parts.php keeps the sample data in $routes, $ports, $seasons, $fares and
 * the like, and its page shell in $page, so no part's return is kept under
 * those names.
 *
 * Opening the template gives the site the newsletter's contact group
 * ('contact_groups'), made once and found again by its name the next time
 * (_pg_tpl_contact_groups()); the line sends the newsletter to it by hand.
 * There is no catalog: the booking office confirms a booking and takes
 * payment, and only the address book needs the shop.
 *
 * A travel notice is a record of a collection form: the staff add one on
 * the new notice page and the lists show it at once, filtered by the
 * address (?route=). A booking request and a group enquiry are records of
 * their own forms, requests and never tickets: a traveller without an
 * account gets one (auto-registration), so the request's page and the
 * account's list show it at once. A request's Status is an
 * office-use-only field the staff change in the control panel; the
 * messages under it are the page's comments, mailed to the traveller and
 * to the line. The template keeps no seat or deck availability and no
 * prices per vehicle length: the fares are words.
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
// is kept under a _part name ($routes, $ports and $fares are parts.php's
// sample data, $page its page shell). notices.php comes before bookings.php:
// the booking lists use its filter and link pieces.
$ferry_dir = PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-ferry';
include $ferry_dir . '/parts.php';
include $ferry_dir . '/mail.php';
$public_part   = include $ferry_dir . '/pages_public.php';
$notice_part   = include $ferry_dir . '/notices.php';
$booking_part  = include $ferry_dir . '/bookings.php';
$standard_part = include $ferry_dir . '/standard.php';
$account_part  = include $ferry_dir . '/account.php';

// The pages in the order the editor lists them: the visitor's pages, the
// travel notices, the booking requests and group enquiries, the standard
// pages, the account and staff pages, the e-mails last of each part.
$ferry_parts = array($public_part, $notice_part, $booking_part, $standard_part, $account_part);
$pages   = array();
$widgets = array();
foreach ($ferry_parts as $part) {
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
// The routes, the ports, the timetables, the fares, travelling with us,
// the questions, the travel notices and the privacy notice are marked
// where they are built (fy- ids).
$search_sections = array(
    'contact' => array(
        lang('Contact Details') => array('contact-details', lang('address, phone, e-mail, booking office, port office, opening hours')),
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
// Two fonts: a tall condensed sans for the headings, the line's name, the
// sailing times and the figures, like the boards at a ferry port; a plain
// humanist sans for the rest.
$ferry_fonts = array(
    array('family' => 'Barlow Condensed', 'weights' => array('500', '600', '700'), 'enabled' => true),
    array('family' => 'Source Sans 3', 'weights' => array('400', '600', '700'), 'enabled' => true),
);

// The template's own CSS in User Styles. Every class is named pfy- (the
// field ids are fy-); no block rule's class is named here. Selectors use
// descendants and pseudo-classes only, so they hold on the editor's canvas
// too. Colours are tokens only, so they follow any palette in both themes.
$ferry_css = implode("\n", array(
    ':root,[data-bs-theme]{--pg-font-heading:"Barlow Condensed","Arial Narrow",system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;'
        . '--pg-font-body:"Source Sans 3",system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;'
        . '--pg-heading-weight:600;--pg-display-weight:700;--pg-heading-tracking:.01em;--pg-heading-line-height:1.1}',
    'h1,h2,h3,h4,h5,h6,.h1,.h2,.h3,.h4,.h5,.h6,.display-1,.display-2,.display-3,.display-4,.display-5,.display-6{font-family:var(--pg-font-heading)}',
    'body{font-family:var(--pg-font-body)}',
    // Titles: the condensed sans, upright and a touch spaced.
    '.pfy-display{font-family:var(--pg-font-heading);font-weight:700;letter-spacing:.01em}',
    // The small line over a title: spaced capitals, like a port's signs.
    '.pfy-eyebrow{letter-spacing:.16em;font-size:.75rem}',
    // The line's name in the header and the footer, in spaced capitals.
    '.pfy-brand{font-family:var(--pg-font-heading);font-weight:700;letter-spacing:.08em;text-transform:uppercase}',
    // A fare: figures of one width, so the fare table's figures line up.
    '.pfy-price{font-variant-numeric:tabular-nums}',
    // A key figure: the condensed sans, figures of one width.
    '.pfy-stat{font-family:var(--pg-font-heading);font-variant-numeric:tabular-nums}',
    // What a traveller says: the body sans, a little larger.
    '.pfy-quote{font-size:1.125rem;line-height:1.6}',
    // The home page's way in: the palette's colour rising from below,
    // like the sea under the bow.
    '.pfy-hero{background:linear-gradient(0deg,var(--bs-primary-bg-subtle),var(--bs-body-bg) 70%)}',
    // Route and notice cards and the staff shortcuts rise a little under
    // the pointer.
    '.pfy-lift{transition:transform .2s ease,box-shadow .2s ease}',
    '.pfy-lift:hover,.pfy-lift:focus-visible,.pfy-lift:focus-within{transform:translateY(-3px);box-shadow:var(--bs-box-shadow)}',
    '@media (prefers-reduced-motion:reduce){.pfy-lift{transition:none}.pfy-lift:hover,.pfy-lift:focus-visible,.pfy-lift:focus-within{transform:none}}',
));

return array(
    'name'        => lang('Ferry'),
    'version'     => '2.0.1',
    'framework'   => 'bootstrap5',
    'order'       => 117,
    'icon'        => 'bi-water',
    'description' => lang('A ferry line: routes between the mainland and the islands with a page per route, the ports, the timetables per season, the fares as words for foot passengers, vehicles and cabins, what is on board, travel notices with a page each, booking requests and group enquiries the traveller follows from their account with the booking office\'s answers, News, a newsletter, an inbox, a staff area and a help that searches the whole site.'),

    // The theme the template is made for; the operator can pick another
    // before opening it, and change it later in the editor. The e-mails'
    // inline colours are this palette's deep teal, readable as white
    // button text. The template's own look travels in its User Styles
    // ('assets'), so it holds under any look and palette.
    'look'        => 'minimal',
    'palette'     => 'ocean',

    // What the installer lists on the template's card, after the points
    // every template shares.
    'highlights'  => array(
        lang('Routes between the mainland and the islands with a page per route: the ports, the crossing time, the ship, the sailings and the fares as words.'),
        lang('Timetables per route and season, fares for foot passengers, vehicles and cabins, the ports and what is on board.'),
        lang('Travel notices as a collection: a page per notice, a list by route, and samples the staff replace from a form.'),
        lang('Booking requests and group enquiries, each with its own page, status and the booking office\'s answers, followed from the traveller\'s account.'),
    ),

    // Made (or found again) when the template is opened; see _pg_tpl_folders().
    'folders'     => array(
        'root'         => array('name' => 'pinegrap_ferry', 'access' => 'public'),
        'public'       => array('name' => 'public', 'parent' => 'root', 'access' => 'public'),
        'registration' => array('name' => 'registration', 'parent' => 'root', 'access' => 'registration'),
        'private'      => array('name' => 'private', 'parent' => 'root', 'access' => 'private'),
    ),

    'contact_groups' => array(
        'newsletter' => array(
            'name'         => lang('Ferry Newsletter'),
            'description'  => lang('The addresses left on the newsletter form of the ferry line.'),
            'subscription' => true,
        ),
    ),

    'pages'       => $pages,

    'widgets'     => $widgets,

    'shared'      => array(
        'account_bar'  => array('name' => lang('Ferry Account Bar'), 'tree' => $account_bar_tree()),
        'notice'       => array('name' => lang('Ferry Notice'), 'tree' => $notice()),
        'header'       => array('name' => lang('Ferry Header'), 'tree' => $header()),
        'footer'       => array('name' => lang('Ferry Footer'), 'tree' => $footer()),
        'staff_bar'    => array('name' => lang('Ferry Staff Bar'), 'tree' => $staff_bar()),
        'booking_band' => array('name' => lang('Ferry Booking Band'), 'tree' => $booking_band()),
        'email_header' => array('name' => lang('Ferry E-mail Header'), 'tree' => $email_header_tree()),
        'email_footer' => array('name' => lang('Ferry E-mail Footer'), 'tree' => $email_footer_tree()),
    ),

    // The template's own look: the ferry blocks' rules its pages place and
    // its pfy- rules in one User Styles entry, and its two fonts.
    'assets'      => array(
        'css'   => pg_design_template_user_styles(array('ferry-departures', 'ferry-timetable', 'ferry-crossings', 'ferry-fares'), $ferry_css),
        'fonts' => json_encode($ferry_fonts),
    ),
);
