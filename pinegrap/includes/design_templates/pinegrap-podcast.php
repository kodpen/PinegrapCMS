<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Podcast Network": a studio publishing several podcasts.
 * A home page with the latest episodes, the podcasts and the News; the
 * podcasts by category with a page each listing its episodes; the
 * episodes with their player, notes, guests, chapters, transcript and the
 * listeners' comments; the hosts, where to listen, about and the
 * questions; sponsorship with the audience and the packages, and
 * sponsorship enquiries and guest pitches the sender follows from their
 * account, with the status and the network's answers; supporters' tiers
 * with a supporters' area and its bonus episodes, sold with gift
 * memberships when the shop is on; a contact page whose messages become
 * conversations, the News with its post page, a newsletter, the sign-in
 * and account pages, a staff area (the new podcast, episode, News and
 * staff directory forms, the enquiries and the pitches), a help page that
 * searches the whole site and the e-mails. Built on Bootstrap 5. Read by
 * pg_design_templates() (includes/fn/designer.php).
 *
 * The pages are built in includes/design_templates/pinegrap-podcast/ (not
 * read as templates of their own): parts.php holds the node builders, the
 * network's data and the parts every page repeats; mail.php the e-mail
 * builders; pages_public.php, podcasts.php, episodes.php, sponsors.php,
 * supporters.php, standard.php and account.php the pages and widgets. Each
 * is included here in this one scope, in that order, and uses the
 * variables the files before it set. parts.php keeps the network's data
 * in $podcasts, $episodes, $hosts, $packages and $tiers, supporters.php
 * the catalog in $catalog, so no part's return is kept under those names.
 *
 * Opening the template gives the site the newsletter's contact group
 * ('contact_groups'), made once and found again by its name the next time
 * (_pg_tpl_contact_groups()). With the shop on it also gets the
 * Supporters groups with the tiers and the gift memberships ('catalog',
 * _pg_tpl_catalog()). Only the shop pages need the shop; the shared parts
 * never link them, and the support page stands without it.
 *
 * A podcast and an episode are records of collection forms: the staff add
 * one on the new podcast or new episode page and the lists show it at
 * once. An episode references its podcast, and the podcast's page lists
 * the episodes that reference it. An episode's recording is a file in the
 * public episodes folder, played on its page by the bound player. A
 * sponsorship enquiry and a guest pitch are records of their own forms: a
 * visitor without an account gets one (auto-registration), so the
 * record's page and the account's list show it at once. Its Status is an
 * office-use-only field the staff change in the control panel; the
 * messages under it are the page's comments, mailed to the sender and to
 * the network. A tier product's membership days open the supporters
 * folder for its buyer.
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
 * pages change, so a design records which edition it started from. 2.0.0
 * draws the home on the Podcast blocks (the Episode Hero with Chapters),
 * the sponsorship page on the Sponsor Placements, the listening page on
 * the Transcript and an episode's cover over the hero's sound wave; the
 * hosts, the figures and the sponsorship steps are general blocks (Team:
 * Profiles, Stats: Counter, Steps: Numbered).
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
// is kept under a _part name ($podcasts, $episodes and the catalog's
// $catalog are the parts' data).
$podcast_dir = PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-podcast';
include $podcast_dir . '/parts.php';
include $podcast_dir . '/mail.php';
$public_part    = include $podcast_dir . '/pages_public.php';
$podcast_part   = include $podcast_dir . '/podcasts.php';
$episode_part   = include $podcast_dir . '/episodes.php';
$sponsor_part   = include $podcast_dir . '/sponsors.php';
$supporter_part = include $podcast_dir . '/supporters.php';
$standard_part  = include $podcast_dir . '/standard.php';
$account_part   = include $podcast_dir . '/account.php';

// The pages in the order the editor lists them: the visitor's pages, the
// podcasts, the episodes, sponsorship and guest pitches, the supporters'
// area and the shop, the standard pages, the account and staff pages, the
// e-mails last of each part.
$podcast_parts = array($public_part, $podcast_part, $episode_part, $sponsor_part, $supporter_part, $standard_part, $account_part);
$pages   = array();
$widgets = array();
foreach ($podcast_parts as $part) {
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
// The network, the hosts, the apps, sponsorship, support and the
// questions are marked where they are built.
$search_sections = array(
    'contact' => array(
        lang('Contact Details') => array('contact-details', lang('address, e-mail, studio, network, press, booking a guest')),
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
// Two fonts: a geometric grotesque with a little character for the
// headings, the network's name and the prices, a quiet sans for the notes
// and the rest.
$podcast_fonts = array(
    array('family' => 'Space Grotesk', 'weights' => array('500', '600', '700'), 'enabled' => true),
    array('family' => 'DM Sans', 'weights' => array('400', '500', '700'), 'enabled' => true),
);

// The template's own CSS in User Styles. Every class is named ppd- (the
// field and section ids are pd-); no block rule's class is named here.
// Selectors use descendants and pseudo-classes only, so they hold on the
// editor's canvas too. Colours are tokens only, so they follow any
// palette in both themes.
$podcast_css = implode("\n", array(
    ':root,[data-bs-theme]{--pg-font-heading:"Space Grotesk",system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;'
        . '--pg-font-body:"DM Sans",system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;'
        . '--pg-heading-weight:600;--pg-display-weight:700;--pg-heading-tracking:-.015em;--pg-heading-line-height:1.15}',
    'h1,h2,h3,h4,h5,h6,.h1,.h2,.h3,.h4,.h5,.h6,.display-1,.display-2,.display-3,.display-4,.display-5,.display-6{font-family:var(--pg-font-heading)}',
    'body{font-family:var(--pg-font-body)}',
    // Titles: the grotesque, bold and a little tighter.
    '.ppd-display{font-family:var(--pg-font-heading);font-weight:700;letter-spacing:-.02em}',
    // The small line over a title: spaced capitals.
    '.ppd-eyebrow{letter-spacing:.14em;font-size:.75rem}',
    // The network's name in the header and the footer.
    '.ppd-brand{font-family:var(--pg-font-heading);font-weight:700;letter-spacing:-.01em}',
    // A price and an episode's date: figures of one width, so they line up.
    '.ppd-price{font-family:var(--pg-font-heading);font-variant-numeric:tabular-nums lining-nums}',
    '.ppd-date{font-variant-numeric:tabular-nums}',
    // What a sponsor says: a little larger, in the heading face.
    '.ppd-quote{font-family:var(--pg-font-heading);font-size:1.125rem;line-height:1.55}',
    // An episode row's cover: a square of one size, so the rows line up.
    '.ppd-thumb{width:120px;height:120px;max-width:100%}',
    // The chapter list keeps one chapter per line, its times lined up.
    '.ppd-chapters{white-space:pre-line;font-variant-numeric:tabular-nums;line-height:1.9}',
    // The home page's first section: a glow of the primary colour from the
    // upper right corner, where the browser mixes colours.
    '@supports (background-color:color-mix(in srgb,red 8%,blue)){.ppd-hero{background-image:radial-gradient(ellipse 70% 80% at 100% 0%,color-mix(in srgb,var(--bs-primary) 16%,transparent) 0%,transparent 70%)}}',
    // The player's box: a thin line of the primary colour along its top,
    // like a sound wave's baseline.
    '.ppd-wave{border-top:3px solid var(--bs-primary)}',
    // Podcast, package and tier cards rise a little under the pointer.
    '.ppd-lift{transition:transform .2s ease,box-shadow .2s ease}',
    '.ppd-lift:hover,.ppd-lift:focus-visible,.ppd-lift:focus-within{transform:translateY(-3px);box-shadow:var(--bs-box-shadow)}',
    '@media (prefers-reduced-motion:reduce){.ppd-lift{transition:none}.ppd-lift:hover,.ppd-lift:focus-visible,.ppd-lift:focus-within{transform:none}}',
));

return array(
    'name'        => lang('Podcast Network'),
    'version'     => '2.0.0',
    'framework'   => 'bootstrap5',
    'order'       => 141,
    'icon'        => 'bi-mic',
    'description' => lang('A podcast network: podcasts by category with a page each, episodes with a player, notes, chapters, a transcript and the listeners\' comments, the hosts and where to listen, sponsorship enquiries and guest pitches the sender follows from their account, supporters\' tiers with bonus episodes and gift memberships when the shop is on, News, a newsletter, an inbox, a staff area and a help page that searches the whole site.'),

    // The theme the template is made for; the operator can pick another
    // before opening it, and change it later in the editor. The template's
    // own look travels in its User Styles ('assets'), so it holds under any
    // look and palette.
    'look'        => 'bold',
    'palette'     => 'sunset',

    // What the installer lists on the template's card, after the points
    // every template shares.
    'highlights'  => array(
        lang('Podcasts by category with a page each, and episodes with a player, notes, guests, chapters, a transcript and the listeners\' comments; the staff add both from a form.'),
        lang('Sponsorship with the audience and the packages, and sponsorship enquiries and guest pitches with their own page, status and the network\'s answers.'),
        lang('A supporters\' area with bonus episodes, opened by a supporter\'s tier; with the shop on, the tiers and gift memberships are sold.'),
        lang('The hosts, where to listen, News, a newsletter, an inbox, a staff area and a help page that searches the whole site.'),
    ),

    // Made (or found again) when the template is opened; see _pg_tpl_folders().
    // The episodes' recordings are kept in their own public folder, an
    // enquiry's or a pitch's attachment in the private folder, and the
    // supporters' area opens to a member.
    'folders'     => array(
        'root'         => array('name' => 'pinegrap_podcast', 'access' => 'public'),
        'public'       => array('name' => 'public', 'parent' => 'root', 'access' => 'public'),
        'registration' => array('name' => 'registration', 'parent' => 'root', 'access' => 'registration'),
        'private'      => array('name' => 'private', 'parent' => 'root', 'access' => 'private'),
        'episodes'     => array('name' => 'episodes', 'parent' => 'root', 'access' => 'public'),
        'supporters'   => array('name' => 'supporters', 'parent' => 'root', 'access' => 'membership'),
    ),

    'catalog'        => $supporter_part['catalog'],

    'contact_groups' => array(
        'newsletter' => array(
            'name'         => lang('Podcast Newsletter'),
            'description'  => lang('The addresses left on the newsletter form of the podcast network.'),
            'subscription' => true,
        ),
    ),

    'pages'       => $pages,

    'widgets'     => $widgets,

    'shared'      => array(
        'account_bar'    => array('name' => lang('Podcast Account Bar'), 'tree' => $account_bar_tree()),
        'notice'         => array('name' => lang('Podcast Notice'), 'tree' => $notice()),
        'header'         => array('name' => lang('Podcast Header'), 'tree' => $header()),
        'footer'         => array('name' => lang('Podcast Footer'), 'tree' => $footer()),
        'staff_bar'      => array('name' => lang('Podcast Staff Bar'), 'tree' => $staff_bar()),
        'subscribe_band' => array('name' => lang('Podcast Subscribe Band'), 'tree' => $subscribe_band()),
        'email_header'   => array('name' => lang('Podcast E-mail Header'), 'tree' => $email_header_tree()),
        'email_footer'   => array('name' => lang('Podcast E-mail Footer'), 'tree' => $email_footer_tree()),
    ),

    // The template's own look: the rules of the Podcast blocks its pages are
    // drawn on (the Episode Hero, the Sponsor Placements and the Transcript)
    // and its ppd- rules in one User Styles entry, and its two fonts.
    'assets'      => array(
        'css'   => pg_design_template_user_styles(array('podcast-episode', 'podcast-sponsor', 'podcast-transcript'), $podcast_css),
        'fonts' => json_encode($podcast_fonts),
    ),
);
