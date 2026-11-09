<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Music Artist": a band or a solo artist. A home page with
 * the newest release, the next shows, the records and the news; the tour
 * dates from today on with a country filter and the past shows, one page
 * per show with its venue, doors and ticket link; the discography by
 * format, one page per release with its track list, credits, listening
 * links and the fans' words; the band, the videos and the questions; a
 * press kit with the biography in three lengths, the quotes and the press
 * files; booking enquiries from promoters and festivals the sender follows
 * from their account, with the status and the band's answers; merch and
 * gift cards in the shop when the shop is on; a contact page whose
 * messages become conversations, the News (the blog) with its post page,
 * the mailing list, the sign-in and account pages, a staff area (the new
 * show, release and press file forms, the booking enquiries, the blog and
 * staff directory forms), a help page that searches the whole site and the
 * e-mails. Built on Bootstrap 5. Read by pg_design_templates()
 * (includes/fn/designer.php).
 *
 * The pages are built in includes/design_templates/pinegrap-music/ (not
 * read as templates of their own): parts.php holds the node builders, the
 * artist's data and the parts every page repeats; mail.php the e-mail
 * builders; pages_public.php, releases.php, shows.php, press.php,
 * merch.php, standard.php and account.php the pages and widgets. Each is
 * included here in this one scope, in that order, and uses the variables
 * the files before it set. parts.php keeps the sample data in $releases,
 * $shows, $merch, $gift_cards, $members and $videos, so no part's return is
 * kept under those names.
 *
 * Opening the template gives the site the mailing list's contact group
 * ('contact_groups'), made once and found again by its name the next time
 * (_pg_tpl_contact_groups()); the band sends its news to it by hand. With
 * the shop on it also gets the Merch group and its products and the gift
 * cards ('catalog', _pg_tpl_catalog()); only the shop pages need the shop,
 * and the shared parts never link them. The press kit's files are kept in
 * a public 'press' folder of their own, so a journalist downloads them
 * without an account.
 *
 * A release, a show and a press file are records of their forms,
 * collections: the staff add one on its new page and the lists show it at
 * once, filtered by the address (?format=, ?country=). A booking enquiry is
 * a record of its own form, a request and never a confirmed show: a sender
 * without an account gets one (auto-registration), so the enquiry's page
 * and the account's list show it at once. Its Status is an office-use-only
 * field the staff change in the control panel; the messages under it are
 * the page's comments, mailed to the sender and to the band. The template
 * sells no tickets: a show links to the seller.
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
// is kept under a _part name ($releases, $shows, $merch and $gift_cards are
// parts.php's sample data).
$music_dir = PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-music';
include $music_dir . '/parts.php';
include $music_dir . '/mail.php';
$public_part   = include $music_dir . '/pages_public.php';
$release_part  = include $music_dir . '/releases.php';
$show_part     = include $music_dir . '/shows.php';
$press_part    = include $music_dir . '/press.php';
$merch_part    = include $music_dir . '/merch.php';
$standard_part = include $music_dir . '/standard.php';
$account_part  = include $music_dir . '/account.php';

// The pages in the order the editor lists them: the visitor's pages, the
// releases, the shows, the press kit and booking enquiries, the shop, the
// standard pages, the account and staff pages, the e-mails last of each
// part.
$music_parts = array($public_part, $release_part, $show_part, $press_part, $merch_part, $standard_part, $account_part);
$pages   = array();
$widgets = array();
foreach ($music_parts as $part) {
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
// The band, the videos, the press kit, the questions and the privacy
// notice are marked where they are built.
$search_sections = array(
    'contact' => array(
        lang('Contact Details') => array('contact-details', lang('address, e-mail, management, booking agent, press office')),
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
// Two fonts: a tall condensed display face for the headings, the band's
// name and the show dates, a plain sans for the rest.
$music_fonts = array(
    array('family' => 'Bebas Neue', 'weights' => array('400'), 'enabled' => true),
    array('family' => 'Inter', 'weights' => array('400', '600', '700'), 'enabled' => true),
);

// The template's own CSS in User Styles. Every class is named pmu- (the
// field ids are mu-); no block rule's class is named here. Selectors use
// descendants and pseudo-classes only, so they hold on the editor's canvas
// too. Colours are tokens only, so they follow any palette in both themes.
$music_css = implode("\n", array(
    ':root,[data-bs-theme]{--pg-font-heading:"Bebas Neue",Impact,"Arial Narrow",sans-serif;'
        . '--pg-font-body:"Inter",system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;'
        . '--pg-heading-weight:400;--pg-display-weight:400;--pg-heading-tracking:.02em;--pg-heading-line-height:1.05}',
    'h1,h2,h3,h4,h5,h6,.h1,.h2,.h3,.h4,.h5,.h6,.display-1,.display-2,.display-3,.display-4,.display-5,.display-6{font-family:var(--pg-font-heading)}',
    'body{font-family:var(--pg-font-body)}',
    // Titles: the display face, spaced a little, never bold (it has one weight).
    '.pmu-display{font-family:var(--pg-font-heading);font-weight:400;letter-spacing:.02em;line-height:1.05}',
    // The small line over a title: spaced capitals.
    '.pmu-eyebrow{letter-spacing:.16em;font-size:.75rem}',
    // The band's name in the header and the footer, in the display face.
    '.pmu-brand{font-family:var(--pg-font-heading);font-weight:400;letter-spacing:.06em}',
    // The home page's dark band: a glow of the palette's main colour behind the photo.
    '.pmu-hero{background-image:radial-gradient(circle at 85% 25%,rgba(var(--bs-primary-rgb),.35),transparent 55%)}',
    // A show's date: the display face with figures of one width, so a list of dates lines up.
    '.pmu-date{font-family:var(--pg-font-heading);font-weight:400;letter-spacing:.04em;font-variant-numeric:tabular-nums}',
    // A track list: one track a line, as the staff wrote it.
    '.pmu-tracks{white-space:pre-line;font-variant-numeric:tabular-nums;line-height:1.9}',
    // What the press says: a little larger, in italics.
    '.pmu-quote{font-style:italic;font-size:1.125rem;line-height:1.6}',
    // Release, show and merch cards rise a little under the pointer.
    '.pmu-lift{transition:transform .2s ease,box-shadow .2s ease}',
    '.pmu-lift:hover,.pmu-lift:focus-visible,.pmu-lift:focus-within{transform:translateY(-3px);box-shadow:var(--bs-box-shadow)}',
    '@media (prefers-reduced-motion:reduce){.pmu-lift{transition:none}.pmu-lift:hover,.pmu-lift:focus-visible,.pmu-lift:focus-within{transform:none}}',
));

return array(
    'name'        => lang('Music Artist'),
    'version'     => '2.0.0',
    'framework'   => 'bootstrap5',
    'order'       => 91,
    'icon'        => 'bi-music-note-beamed',
    'description' => lang('A band or a solo artist: tour dates from today on with a page per show and its ticket link, the discography with a page per release, its track list and listening links, the band and the videos, a press kit with downloads, booking enquiries promoters follow from their account, merch and gift cards with the shop on, the News, a mailing list, an inbox, a staff area and a help page that searches the whole site.'),

    // The theme the template is made for; the operator can pick another
    // before opening it, and change it later in the editor. The e-mails'
    // inline colours are this palette's blue. The template's own look
    // travels in its User Styles ('assets'), so it holds under any look and
    // palette.
    'look'        => 'bold',
    'palette'     => 'midnight',

    // What the installer lists on the template's card, after the points
    // every template shares.
    'highlights'  => array(
        lang('Shows and releases as collections: tour dates from today on with a country filter and past shows, a page per show with its venue, doors and ticket link, and a page per release with its track list, credits and listening links.'),
        lang('A press kit with the biography in three lengths, quotes and downloadable press files, and booking enquiries promoters follow with their status and the band\'s answers.'),
        lang('The band, the videos, the questions and the fans\' words on each release.'),
        lang('Merch and gift cards with the shop on, the News, a mailing list, an inbox, a staff area and a help page.'),
    ),

    // Made (or found again) when the template is opened; see
    // _pg_tpl_folders(). The press folder holds the press kit's files,
    // open to everyone.
    'folders'     => array(
        'root'         => array('name' => 'pinegrap_music', 'access' => 'public'),
        'public'       => array('name' => 'public', 'parent' => 'root', 'access' => 'public'),
        'registration' => array('name' => 'registration', 'parent' => 'root', 'access' => 'registration'),
        'private'      => array('name' => 'private', 'parent' => 'root', 'access' => 'private'),
        'press'        => array('name' => 'press', 'parent' => 'root', 'access' => 'public'),
    ),

    'catalog'        => $merch_part['catalog'],

    'contact_groups' => array(
        'newsletter' => array(
            'name'         => lang('Music Mailing List'),
            'description'  => lang('The addresses left on the mailing list form of the band.'),
            'subscription' => true,
        ),
    ),

    'pages'       => $pages,

    'widgets'     => $widgets,

    'shared'      => array(
        'account_bar'  => array('name' => lang('Music Account Bar'), 'tree' => $account_bar_tree()),
        'notice'       => array('name' => lang('Music Notice'), 'tree' => $notice()),
        'header'       => array('name' => lang('Music Header'), 'tree' => $header()),
        'footer'       => array('name' => lang('Music Footer'), 'tree' => $footer()),
        'staff_bar'    => array('name' => lang('Music Staff Bar'), 'tree' => $staff_bar()),
        'list_band'    => array('name' => lang('Music Mailing List Band'), 'tree' => $list_band()),
        'email_header' => array('name' => lang('Music E-mail Header'), 'tree' => $email_header_tree()),
        'email_footer' => array('name' => lang('Music E-mail Footer'), 'tree' => $email_footer_tree()),
    ),

    // The template's own look: the Music blocks' pg-music- rules and its
    // pmu- rules in one User Styles entry, and its two fonts.
    'assets'      => array(
        'css'   => pg_design_template_user_styles(array('music-release', 'music-tour', 'music-presskit'), $music_css),
        'fonts' => json_encode($music_fonts),
    ),
);
