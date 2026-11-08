<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Sports Club": an amateur football and multi-sport club.
 * A home page with the next fixtures, the latest results, the club's
 * events and news; the teams with a page each (training nights, coach,
 * squad and fixtures), the club's story and honours, the ground, the
 * youth academy, the sponsors and the questions; the squads and a page per
 * player; the fixtures from today on and the results before today, each
 * filtered by team, with a page per match and its report; academy
 * registrations and trial requests the family or the player follows from
 * their account, with the status and the coaches' answers; club events
 * and a sponsorship enquiry; kit, season tickets and memberships sold in
 * the shop when the shop is on, a membership opening the members' area;
 * a contact page whose messages become conversations, Club News (the
 * blog) with its post page, a newsletter, the sign-in and account pages, a
 * staff area (the request lists, the match, player, event, article and
 * staff directory forms), a club help that searches the whole site and
 * the e-mails. Built on Bootstrap 5. Read by pg_design_templates()
 * (includes/fn/designer.php).
 *
 * The pages are built in includes/design_templates/pinegrap-sportsclub/
 * (not read as templates of their own): parts.php holds the node builders,
 * the club's data and the parts every page repeats; squad.php the list
 * helpers ($sp_…) the later files use, with the Players collection;
 * mail.php the e-mail builders; pages_public.php, matches.php,
 * registrations.php, events.php, shop.php, standard.php and account.php
 * the pages and widgets. Each is included here in this one scope, in that
 * order, and uses the variables the files before it set. parts.php keeps
 * the club's data in $teams, $players, $matches, $events, $kit,
 * $memberships and $season_tickets, so no part's return is kept under
 * those names.
 *
 * Opening the template gives the site the newsletter's contact group
 * ('contact_groups'), made once and found again by its name the next time
 * (_pg_tpl_contact_groups()). With the shop on it also gets the Kit,
 * Season Tickets and Memberships groups and a product per item ('catalog',
 * _pg_tpl_catalog()); a membership renews the buyer's membership, which
 * opens the members folder. Only the shop pages need the shop, and the
 * shared parts never link them.
 *
 * Teams are fixed data (they change once a season). A player, a match and
 * a club event are records of collections the staff add from the staff
 * area; the lists filter them by the address (?team=, ?kind=). An academy
 * registration, a trial request and a sponsorship enquiry are records of
 * their own forms, requests and never enrolments: a visitor without an
 * account gets one (auto-registration), so the request's page and the
 * account's list show it at once. A request's Status is an office-use-only
 * field the staff change in the control panel; the messages under it are
 * the page's comments, mailed to the family and to the club. The template
 * computes no league table and keeps no seat map.
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
// is kept under a _part name ($teams, $players, $matches, $events and the
// shop's items are parts.php's data).
$sportsclub_dir = PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-sportsclub';
include $sportsclub_dir . '/parts.php';
$squad_part        = include $sportsclub_dir . '/squad.php';
include $sportsclub_dir . '/mail.php';
$public_part       = include $sportsclub_dir . '/pages_public.php';
$match_part        = include $sportsclub_dir . '/matches.php';
$registration_part = include $sportsclub_dir . '/registrations.php';
$event_part        = include $sportsclub_dir . '/events.php';
$shop_part         = include $sportsclub_dir . '/shop.php';
$standard_part     = include $sportsclub_dir . '/standard.php';
$account_part      = include $sportsclub_dir . '/account.php';

// The pages in the order the editor lists them: the visitor's pages, the
// squads, the matches, the registrations, the events and sponsorship, the
// shop and the members' area, the standard pages, the account and staff
// pages, the e-mails last of each part.
$sportsclub_parts = array($public_part, $squad_part, $match_part, $registration_part, $event_part, $shop_part, $standard_part, $account_part);
$pages   = array();
$widgets = array();
foreach ($sportsclub_parts as $part) {
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
// The teams, the club's story and honours, the ground, the academy, the
// sponsors, the questions and the privacy notice are marked where they are
// built (sp- ids).
$search_sections = array(
    'contact' => array(
        lang('Contact Details') => array('contact-details', lang('address, phone, e-mail, clubhouse, training nights, getting here')),
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
// Two fonts: a condensed sans of match-day programmes and shirt numbers for
// the headings, the club's name and the scores, a plain sans for the rest.
$sportsclub_fonts = array(
    array('family' => 'Barlow Condensed', 'weights' => array('500', '600', '700'), 'enabled' => true),
    array('family' => 'Inter', 'weights' => array('400', '500', '600'), 'enabled' => true),
);

// The template's own CSS in User Styles. Every class is named psp- (the
// field ids are sp-); no block rule's class is named here. Selectors use
// descendants and pseudo-classes only, so they hold on the editor's canvas
// too. Colours are tokens only, so they follow any palette in both themes.
$sportsclub_css = implode("\n", array(
    ':root,[data-bs-theme]{--pg-font-heading:"Barlow Condensed","Arial Narrow",Arial,sans-serif;'
        . '--pg-font-body:"Inter",system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;'
        . '--pg-heading-weight:600;--pg-display-weight:700;--pg-heading-tracking:.01em;--pg-heading-line-height:1.1}',
    'h1,h2,h3,h4,h5,h6,.h1,.h2,.h3,.h4,.h5,.h6,.display-1,.display-2,.display-3,.display-4,.display-5,.display-6{font-family:var(--pg-font-heading)}',
    'body{font-family:var(--pg-font-body)}',
    // Titles: the condensed face in capitals, as on a match-day programme.
    '.psp-display{font-family:var(--pg-font-heading);font-weight:700;text-transform:uppercase;letter-spacing:.015em}',
    // The small line over a title: spaced capitals.
    '.psp-eyebrow{letter-spacing:.14em;font-size:.75rem}',
    // The club's name in the header and the footer.
    '.psp-brand{font-family:var(--pg-font-heading);font-weight:700;text-transform:uppercase;letter-spacing:.04em}',
    // A score, a shirt number or a session count: the condensed face with
    // figures of one width, so a results list lines up.
    '.psp-score{font-family:var(--pg-font-heading);font-variant-numeric:tabular-nums;letter-spacing:.02em}',
    // The club's badge beside its name: a touch larger than the text.
    '.psp-crest{line-height:1}',
    // The home page's way in: a diagonal wash of the primary colour, as the
    // stripe across a club shirt.
    '.psp-hero{background-image:linear-gradient(115deg,rgba(var(--bs-primary-rgb),.12) 0%,rgba(var(--bs-primary-rgb),.12) 38%,transparent 38%)}',
    // Team, player, match and event cards rise a little under the pointer.
    '.psp-lift{transition:transform .2s ease,box-shadow .2s ease}',
    '.psp-lift:hover,.psp-lift:focus-visible,.psp-lift:focus-within{transform:translateY(-3px);box-shadow:var(--bs-box-shadow)}',
    '@media (prefers-reduced-motion:reduce){.psp-lift{transition:none}.psp-lift:hover,.psp-lift:focus-visible,.psp-lift:focus-within{transform:none}}',
));

return array(
    'name'        => lang('Sports Club'),
    'version'     => '1.0.0',
    'framework'   => 'bootstrap5',
    'order'       => 113,
    'icon'        => 'bi-trophy',
    'description' => lang('An amateur football and multi-sport club: teams with a page each, squads and player pages, fixtures and results with match reports, the youth academy with registrations and trial requests followed from the account, club events and sponsorship enquiries, kit, season tickets and memberships sold in the shop with a members\' area, Club News, a newsletter, an inbox, a staff area and a club help that searches the whole site.'),

    // The theme the template is made for; the operator can pick another
    // before opening it, and change it later in the editor. The e-mails'
    // inline colours are a darker shade of this palette's green, readable
    // as white button text. The template's own look travels in its User
    // Styles ('assets'), so it holds under any look and palette.
    'look'        => 'bold',
    'palette'     => 'forest',

    // What the installer lists on the template's card, after the points
    // every template shares.
    'highlights'  => array(
        lang('Teams with a page each: training nights, the coach, the squad and the fixtures, and a page per player.'),
        lang('Fixtures from today on and results before today, filtered by team, with a page per match and its report.'),
        lang('Academy registrations and trial requests followed from the account with the status and the coaches\' answers; club events and sponsorship enquiries.'),
        lang('With the shop on, kit, season tickets and memberships that open a members\' area; Club News, a newsletter, an inbox, a staff area and a club help.'),
    ),

    // Made (or found again) when the template is opened; see _pg_tpl_folders().
    // The members folder's access is a membership: a membership product
    // opens it.
    'folders'     => array(
        'root'         => array('name' => 'pinegrap_sportsclub', 'access' => 'public'),
        'public'       => array('name' => 'public', 'parent' => 'root', 'access' => 'public'),
        'registration' => array('name' => 'registration', 'parent' => 'root', 'access' => 'registration'),
        'private'      => array('name' => 'private', 'parent' => 'root', 'access' => 'private'),
        'members'      => array('name' => 'members', 'parent' => 'root', 'access' => 'membership'),
    ),

    'catalog'        => $shop_part['catalog'],

    'contact_groups' => array(
        'newsletter' => array(
            'name'         => lang('Sports Club Newsletter'),
            'description'  => lang('The addresses left on the newsletter form of the sports club.'),
            'subscription' => true,
        ),
    ),

    'pages'       => $pages,

    'widgets'     => $widgets,

    'shared'      => array(
        'account_bar'  => array('name' => lang('Sports Club Account Bar'), 'tree' => $account_bar_tree()),
        'notice'       => array('name' => lang('Sports Club Notice'), 'tree' => $notice()),
        'header'       => array('name' => lang('Sports Club Header'), 'tree' => $header()),
        'footer'       => array('name' => lang('Sports Club Footer'), 'tree' => $footer()),
        'staff_bar'    => array('name' => lang('Sports Club Staff Bar'), 'tree' => $staff_bar()),
        'join_band'    => array('name' => lang('Sports Club Join Band'), 'tree' => $join_band()),
        'email_header' => array('name' => lang('Sports Club E-mail Header'), 'tree' => $email_header_tree()),
        'email_footer' => array('name' => lang('Sports Club E-mail Footer'), 'tree' => $email_footer_tree()),
    ),

    // The template's own look: its psp- rules in one User Styles entry, and
    // its two fonts.
    'assets'      => array(
        'css'   => pg_design_template_user_styles(array(), $sportsclub_css),
        'fonts' => json_encode($sportsclub_fonts),
    ),
);
