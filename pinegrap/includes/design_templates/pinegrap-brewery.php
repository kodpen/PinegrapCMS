<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Craft Brewery": a craft brewery with a taproom. A home
 * page with the beers, the coming taproom events, the tours and the news;
 * the beers by style with a page each and the drinkers' notes with stars,
 * the taproom with its hours, tap list and food, the about page and the
 * questions; taproom events from today on and the past ones; brewery tour
 * bookings the visitor follows from their account, with the status and
 * the brewery's answers, and private tastings booked at a free time; beer
 * packs, glassware, merch, gift cards and Beer Club terms sold in the shop
 * when the shop is on, a term opening the club's members' area; a contact
 * page whose messages become conversations, the News with its post page,
 * a newsletter, the sign-in and account pages, a staff area (the new beer,
 * new event, News and staff directory forms, the tour bookings), a help
 * page that searches the whole site and the e-mails. Built on Bootstrap 5.
 * Read by pg_design_templates() (includes/fn/designer.php).
 *
 * The pages are built in includes/design_templates/pinegrap-brewery/ (not
 * read as templates of their own): parts.php holds the node builders, the
 * brewery's data and the parts every page repeats; mail.php the e-mail
 * builders; pages_public.php, beers.php, events.php, tours.php, shop.php,
 * standard.php and account.php the pages and widgets. Each is included
 * here in this one scope, in that order, and uses the variables the files
 * before it set. parts.php keeps the brewery's data in $beers, $events,
 * $tours, $packs, $gift_cards and $club_terms, so no part's return is kept
 * under those names.
 *
 * Opening the template gives the site the newsletter's contact group
 * ('contact_groups'), made once and found again by its name the next time
 * (_pg_tpl_contact_groups()). With the shop on it also gets the Brewery
 * Shop groups with their products ('catalog', _pg_tpl_catalog()); a Beer
 * Club term renews the buyer's membership, which opens the club folder.
 * Only the shop pages need the shop, and the shared parts never link them.
 * The beers are for adults of legal drinking age: the footer, the shop
 * and the tour booking say so; the template keeps no age gate.
 *
 * A beer and a taproom event are records of collection forms: the staff
 * add one on the new beer or new event page and the lists show it at
 * once, the beers filtered by the address (?style=), the events from
 * today on. A tour booking is a record of its own form, a request and
 * never a reservation: a visitor without an account gets one
 * (auto-registration), so the booking's page and the account's list show
 * it at once. Its Status is an office-use-only field the staff change in
 * the control panel; the messages under it are the page's comments,
 * mailed to the visitor and to the brewery. A private tasting is a booking
 * service; the template keeps no tour capacity per slot and sells no
 * event tickets.
 *
 * Placeholders, filled when the template is opened (pg_design_template_prepare()):
 *   {{page:<key>}}                  address of the template page with that key
 *   {{tab:<key>}}                   that page in a widget setting; its id once published
 *   {{folder:<key>}}                id of that template folder
 *   {{contact_group:<key>}}         id of a contact group below
 *   {{product_group:<key>}}         id of a group of the catalog
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
// is kept under a _part name ($beers, $events, $tours and the shop's lists
// are parts.php's data).
$brewery_dir = PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-brewery';
include $brewery_dir . '/parts.php';
include $brewery_dir . '/mail.php';
$public_part   = include $brewery_dir . '/pages_public.php';
$beer_part     = include $brewery_dir . '/beers.php';
$event_part    = include $brewery_dir . '/events.php';
$tour_part     = include $brewery_dir . '/tours.php';
$shop_part     = include $brewery_dir . '/shop.php';
$standard_part = include $brewery_dir . '/standard.php';
$account_part  = include $brewery_dir . '/account.php';

// The pages in the order the editor lists them: the visitor's pages, the
// beers, the events, the tours, the shop and the club, the standard pages,
// the account and staff pages, the e-mails last of each part.
$pages = array_merge($public_part['pages'], $beer_part['pages'], $event_part['pages'], $tour_part['pages'],
    $shop_part['pages'], $standard_part['pages'], $account_part['pages']);
$widgets = array_merge($public_part['widgets'], $beer_part['widgets'], $event_part['widgets'], $tour_part['widgets'],
    $shop_part['widgets'], $standard_part['widgets'], $account_part['widgets']);

// ── Search sections ─────────────────────────────────────────────────────
// The parts of the pages the site search points a result at (Search and
// SERP in the editor's options): a result that matches one links to it,
// the words are marked when the visitor arrives, and the page's structured
// data names it. A part is found by its id ('#…') or by the name it has in
// the layer tree, once per page; it keeps an id it already has. The topics
// are words a visitor may search for that the part does not say itself.
// The taproom's taps and food, the brewhouse, the team, the tours, the
// private tastings, the shop's groups and the club are marked where they
// are built.
$search_sections = array(
    'contact' => array(
        lang('Contact Details') => array('contact-details', lang('address, phone, e-mail, taproom, opening hours, directions')),
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
// Two fonts: a sturdy slab serif for the headings and the figures, like a
// label on a cask, and a plain sans for the rest.
$brewery_fonts = array(
    array('family' => 'Zilla Slab', 'weights' => array('500', '600', '700'), 'enabled' => true),
    array('family' => 'Work Sans', 'weights' => array('400', '500', '600'), 'enabled' => true),
);

// The template's own CSS in User Styles. Every class is named pbw- (the
// field ids are bw-); no block rule's class is named here. Selectors use
// descendants and pseudo-classes only, so they hold on the editor's canvas
// too. Colours are tokens only, so they follow any palette in both themes.
$brewery_css = implode("\n", array(
    ':root,[data-bs-theme]{--pg-font-heading:"Zilla Slab",Rockwell,"Roboto Slab",Georgia,serif;'
        . '--pg-font-body:"Work Sans",system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;'
        . '--pg-heading-weight:600;--pg-display-weight:700;--pg-heading-tracking:0;--pg-heading-line-height:1.15}',
    'h1,h2,h3,h4,h5,h6,.h1,.h2,.h3,.h4,.h5,.h6,.display-1,.display-2,.display-3,.display-4,.display-5,.display-6{font-family:var(--pg-font-heading)}',
    'body{font-family:var(--pg-font-body)}',
    // Titles: the slab serif, bold.
    '.pbw-display{font-family:var(--pg-font-heading);font-weight:700;letter-spacing:-.005em}',
    // The small line over a title: spaced capitals.
    '.pbw-eyebrow{letter-spacing:.14em;font-size:.75rem}',
    // The brewery's name in the header and the footer.
    '.pbw-brand{font-family:var(--pg-font-heading);font-weight:700;letter-spacing:.01em}',
    // A beer's strength and bitterness, a price and an event day: figures
    // of one width.
    '.pbw-abv,.pbw-price,.pbw-date{font-variant-numeric:tabular-nums}',
    '.pbw-date{font-family:var(--pg-font-heading)}',
    // What a drinker says: the slab serif, a little larger.
    '.pbw-quote{font-family:var(--pg-font-heading);font-size:1.125rem;line-height:1.5}',
    // The home page's first section and the tastings box: a warm glow of
    // the primary colour from the upper right corner, where the browser
    // mixes colours.
    '@supports (background-color:color-mix(in srgb,red 8%,blue)){.pbw-hero{background-image:radial-gradient(ellipse 70% 80% at 100% 0%,color-mix(in srgb,var(--bs-primary) 16%,transparent) 0%,transparent 70%)}}',
    // Beer, tour and staff cards rise a little under the pointer.
    '.pbw-lift{transition:transform .2s ease,box-shadow .2s ease}',
    '.pbw-lift:hover,.pbw-lift:focus-visible,.pbw-lift:focus-within{transform:translateY(-3px);box-shadow:var(--bs-box-shadow)}',
    '@media (prefers-reduced-motion:reduce){.pbw-lift{transition:none}.pbw-lift:hover,.pbw-lift:focus-visible,.pbw-lift:focus-within{transform:none}}',
));

return array(
    'name'        => lang('Craft Brewery'),
    'version'     => '1.0.0',
    'framework'   => 'bootstrap5',
    'order'       => 129,
    'icon'        => 'bi-cup-straw',
    'description' => lang('A craft brewery with a taproom: the beers by style with a page each and drinkers\' notes, the taproom\'s hours, taps and food, taproom events from today on, brewery tour bookings the visitor follows from their account, private tastings booked at a free time, beer packs, merch, gift cards and Beer Club terms sold in the shop with a members\' area, News, a newsletter, an inbox, a staff area and a help page that searches the whole site.'),

    // The theme the template is made for; the operator can pick another
    // before opening it, and change it later in the editor. The e-mails'
    // inline accent follows the palette's primary colour where the mail
    // client reads it. The template's own look travels in its User Styles
    // ('assets'), so it holds under any look and palette.
    'look'        => 'organic',
    'palette'     => 'amber',

    // What the installer lists on the template's card, after the points
    // every template shares.
    'highlights'  => array(
        lang('Beers by style with a page each, drinkers\' notes with stars, and sample beers the staff replace from a form.'),
        lang('Brewery tour bookings with their own page, status and the brewery\'s answers, and private tastings booked at a free time.'),
        lang('Taproom events from today on, the taproom\'s hours, tap list and food, and News.'),
        lang('With the shop on, beer packs, merch, gift cards and Beer Club terms that open a members\' area.'),
    ),

    // Made (or found again) when the template is opened; see _pg_tpl_folders().
    // The club folder's access is a membership: a Beer Club term opens it.
    'folders'     => array(
        'root'         => array('name' => 'pinegrap_brewery', 'access' => 'public'),
        'public'       => array('name' => 'public', 'parent' => 'root', 'access' => 'public'),
        'registration' => array('name' => 'registration', 'parent' => 'root', 'access' => 'registration'),
        'private'      => array('name' => 'private', 'parent' => 'root', 'access' => 'private'),
        'club'         => array('name' => 'club', 'parent' => 'root', 'access' => 'membership'),
    ),

    'catalog'        => $shop_part['catalog'],

    'contact_groups' => array(
        'newsletter' => array(
            'name'         => lang('Brewery Newsletter'),
            'description'  => lang('The addresses left on the newsletter form of the brewery.'),
            'subscription' => true,
        ),
    ),

    'pages'       => $pages,

    'widgets'     => $widgets,

    'shared'      => array(
        'account_bar'  => array('name' => lang('Brewery Account Bar'), 'tree' => $account_bar_tree()),
        'notice'       => array('name' => lang('Brewery Notice'), 'tree' => $notice()),
        'header'       => array('name' => lang('Brewery Header'), 'tree' => $header()),
        'footer'       => array('name' => lang('Brewery Footer'), 'tree' => $footer()),
        'staff_bar'    => array('name' => lang('Brewery Staff Bar'), 'tree' => $staff_bar()),
        'news_band'    => array('name' => lang('Brewery Newsletter Band'), 'tree' => $news_band()),
        'email_header' => array('name' => lang('Brewery E-mail Header'), 'tree' => $email_header_tree()),
        'email_footer' => array('name' => lang('Brewery E-mail Footer'), 'tree' => $email_footer_tree()),
    ),

    // The template's own look: its pbw- rules in one User Styles entry, and
    // its two fonts.
    'assets'      => array(
        'css'   => pg_design_template_user_styles(array(), $brewery_css),
        'fonts' => json_encode($brewery_fonts),
    ),
);
