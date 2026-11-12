<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Public Library": a town's public library with a central
 * library and neighbourhood branches. A home page with the new arrivals,
 * the coming events, the branches, the services, the figures and what
 * members say; the visit with the opening hours of every branch and a page
 * per branch; the Catalogue collection with filters by format and audience
 * and a page per title; events and reading clubs with a page each,
 * filtered by kind; children and teens, the digital library, rooms and
 * spaces, the about page and the questions; a library card request, a
 * title reservation, an event place and a room booking the member follows
 * from their account, with the status and the library's answers; a contact
 * page whose messages become conversations, News (the blog) with its post
 * page, a newsletter, the sign-in and account pages, a staff area (the new
 * title and new event forms, the four request lists, the blog and staff
 * directory forms), a help center that searches the whole site and the
 * e-mails. Built on Bootstrap 5. Read by pg_design_templates()
 * (includes/fn/designer.php).
 *
 * The pages are built in includes/design_templates/pinegrap-library/ (not
 * read as templates of their own): parts.php holds the node builders, the
 * library's data and the parts every page repeats; mail.php the e-mail
 * builders; pages_public.php, catalogue.php, events.php, membership.php,
 * rooms.php, standard.php and account.php the pages and widgets. Each is
 * included here in this one scope, in that order, and uses the variables
 * the files before it set. parts.php keeps the sample data in $branches,
 * $services, $digital, $catalogue_items, $events, $rooms and $staff, so no
 * part's return is kept under those names.
 *
 * Opening the template gives the site the newsletter's contact group
 * ('contact_groups'), made once and found again by its name the next time
 * (_pg_tpl_contact_groups()); the library sends the newsletter to it by
 * hand. There is no catalog: a public library takes no payment online, and
 * only the address book needs the shop.
 *
 * A title and an event are records of their forms, collections: the staff
 * add one on the new title or new event page and the lists show it at
 * once, filtered by the address (?format=, ?audience=, ?kind=). A library
 * card, a reservation, an event place and a room booking are records of
 * their own forms, requests and never a loan or a confirmed booking: a
 * visitor without an account gets one (auto-registration), so the
 * request's page and the account's list show it at once. A request's Status
 * is an office-use-only field the staff change in the control panel; the
 * messages under a request are the page's comments, mailed to the member
 * and to the library.
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
 * pages change, so a design records which edition it started from. 2.0.0
 * draws the home, the visit page, the Central Library's page and the
 * catalogue on the Library blocks (the Shelf Hero, the Card and Loans,
 * the Shelf Mark Guide) and the general Seasonal Hours and Floor
 * Directory.
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
// is kept under a _part name ($events, $rooms and $staff are parts.php's
// sample data).
$library_dir = PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-library';
include $library_dir . '/parts.php';
include $library_dir . '/mail.php';
$public_part     = include $library_dir . '/pages_public.php';
$catalogue_part  = include $library_dir . '/catalogue.php';
$event_part      = include $library_dir . '/events.php';
$membership_part = include $library_dir . '/membership.php';
$room_part       = include $library_dir . '/rooms.php';
$standard_part   = include $library_dir . '/standard.php';
$account_part    = include $library_dir . '/account.php';

// The pages in the order the editor lists them: the visitor's pages, the
// catalogue, the events, the library cards and reservations, the room
// bookings, the standard pages, the account and staff pages, the e-mails
// last of each part.
$library_parts = array($public_part, $catalogue_part, $event_part, $membership_part, $room_part, $standard_part, $account_part);
$pages   = array();
$widgets = array();
foreach ($library_parts as $part) {
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
// The opening hours, the branches, the services, the catalogue, the
// events, children and teens, the digital library, the rooms, the
// questions and the privacy notice are marked where they are built (lib-
// ids).
$search_sections = array(
    'contact' => array(
        lang('Contact Details') => array('contact-details', lang('address, phone, e-mail, enquiry desk, ask a librarian, opening hours')),
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
// Two fonts: a bookish serif made for reading on screen for the headings,
// the library's name and the figures, a clear humanist sans for the rest.
$library_fonts = array(
    array('family' => 'Literata', 'weights' => array('500', '600', '700'), 'enabled' => true),
    array('family' => 'Source Sans 3', 'weights' => array('400', '500', '600'), 'enabled' => true),
);

// The template's own CSS in User Styles. Every class is named plb- (the
// field ids are lib-); no block rule's class is named here. Selectors use
// descendants and pseudo-classes only, so they hold on the editor's canvas
// too. Colours are tokens only, so they follow any palette in both themes.
$library_css = implode("\n", array(
    ':root,[data-bs-theme]{--pg-font-heading:"Literata",Georgia,"Times New Roman",serif;'
        . '--pg-font-body:"Source Sans 3",system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;'
        . '--pg-heading-weight:600;--pg-display-weight:600;--pg-heading-tracking:-.005em;--pg-heading-line-height:1.2}',
    'h1,h2,h3,h4,h5,h6,.h1,.h2,.h3,.h4,.h5,.h6,.display-1,.display-2,.display-3,.display-4,.display-5,.display-6{font-family:var(--pg-font-heading)}',
    'body{font-family:var(--pg-font-body)}',
    // Titles: the serif face, a little tighter.
    '.plb-display{font-family:var(--pg-font-heading);font-weight:600;letter-spacing:-.005em}',
    // The small line over a title: spaced capitals, as on a shelf label.
    '.plb-eyebrow{letter-spacing:.14em;font-size:.75rem}',
    // The library's name in the header and the footer, in the serif face.
    '.plb-brand{font-family:var(--pg-font-heading);font-weight:700}',
    // A key figure: the serif face, figures of one width.
    '.plb-stat{font-family:var(--pg-font-heading);font-variant-numeric:tabular-nums}',
    // Opening times: figures of one width, so the hours line up.
    '.plb-hours{font-variant-numeric:tabular-nums}',
    // What a member says: the serif face in italics, a little larger.
    '.plb-quote{font-family:var(--pg-font-heading);font-style:italic;font-size:1.125rem;line-height:1.6}',
    // A cover: the 2:3 shape of a book jacket, whatever the image.
    '.plb-cover{--bs-aspect-ratio:150%}',
    // The home page's way in: a quiet wash of the primary colour from the
    // top corner, as light falling across a reading room.
    '.plb-hero{background-image:linear-gradient(160deg,rgba(var(--bs-primary-rgb),.1),transparent 55%)}',
    // Title, event and branch cards rise a little under the pointer.
    '.plb-lift{transition:transform .2s ease,box-shadow .2s ease}',
    '.plb-lift:hover,.plb-lift:focus-visible,.plb-lift:focus-within{transform:translateY(-3px);box-shadow:var(--bs-box-shadow)}',
    '@media (prefers-reduced-motion:reduce){.plb-lift{transition:none}.plb-lift:hover,.plb-lift:focus-visible,.plb-lift:focus-within{transform:none}}',
));

return array(
    'name'        => lang('Public Library'),
    'version'     => '2.0.0',
    'framework'   => 'bootstrap5',
    'order'       => 103,
    'icon'        => 'bi-book',
    'description' => lang('A public library with branches: opening hours and a page per branch, the catalogue with new arrivals and a page per title, events and reading clubs with place requests, library card requests, title reservations and room bookings followed from the account, children and teens, the digital library, News, a newsletter, an inbox, a staff area and a help center that searches the whole site.'),

    // The theme the template is made for; the operator can pick another
    // before opening it, and change it later in the editor. The e-mails'
    // inline colours are a darker shade of this palette's green, readable
    // as white button text. The template's own look travels in its User
    // Styles ('assets'), so it holds under any look and palette.
    'look'        => 'elegant',
    'palette'     => 'forest',

    // What the installer lists on the template's card, after the points
    // every template shares.
    'highlights'  => array(
        lang('The visit: opening hours day by day, a page per branch with what it offers, getting here and access.'),
        lang('The catalogue as a collection: a page per title with author, format, shelf and branch, filters by format and audience, and new arrivals on the home page.'),
        lang('Events and reading clubs with a page each and place requests; library card requests, title reservations and room bookings followed from the account with the status and the library\'s answers.'),
        lang('Children and teens, the digital library, rooms and spaces, News, a newsletter, an inbox, a staff area and a help center.'),
    ),

    // Made (or found again) when the template is opened; see _pg_tpl_folders().
    'folders'     => array(
        'root'         => array('name' => 'pinegrap_library', 'access' => 'public'),
        'public'       => array('name' => 'public', 'parent' => 'root', 'access' => 'public'),
        'registration' => array('name' => 'registration', 'parent' => 'root', 'access' => 'registration'),
        'private'      => array('name' => 'private', 'parent' => 'root', 'access' => 'private'),
    ),

    'contact_groups' => array(
        'newsletter' => array(
            'name'         => lang('Library Newsletter'),
            'description'  => lang('The addresses left on the newsletter form of the library.'),
            'subscription' => true,
        ),
    ),

    'pages'       => $pages,

    'widgets'     => $widgets,

    'shared'      => array(
        'account_bar'  => array('name' => lang('Library Account Bar'), 'tree' => $account_bar_tree()),
        'notice'       => array('name' => lang('Library Notice'), 'tree' => $notice()),
        'header'       => array('name' => lang('Library Header'), 'tree' => $header()),
        'footer'       => array('name' => lang('Library Footer'), 'tree' => $footer()),
        'staff_bar'    => array('name' => lang('Library Staff Bar'), 'tree' => $staff_bar()),
        'join_band'    => array('name' => lang('Library Join Band'), 'tree' => $join_band()),
        'email_header' => array('name' => lang('Library E-mail Header'), 'tree' => $email_header_tree()),
        'email_footer' => array('name' => lang('Library E-mail Footer'), 'tree' => $email_footer_tree()),
    ),

    // The template's own look: the rules of the blocks its pages are drawn
    // on (the Shelf Hero, the Card and Loans, the Shelf Mark Guide, the
    // Seasonal Hours and the Floor Directory) and its plb- rules in one
    // User Styles entry, and its two fonts.
    'assets'      => array(
        'css'   => pg_design_template_user_styles(array('library-hero', 'library-card', 'library-shelfmark', 'hours-seasons', 'locations-floors'), $library_css),
        'fonts' => json_encode($library_fonts),
    ),
);
