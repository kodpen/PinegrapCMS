<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Social Community": a photo community whose members
 * share pictures and talk about them. A home page with the newest posts,
 * the topics, a few members and Community News; explore by topic and by
 * tag, a page per post with its comments and the box that saves it; the
 * people with a topic filter and a page per member with their profile and
 * posts; a member's own pages (share a photo, my posts, saved posts, my
 * profile, my reports); reporting a post and the moderators' reports, notes
 * and every post with the hidden ones; about, the community guidelines,
 * the questions page, help, the privacy notice and the terms; contact and
 * the inbox, the newsletter, Community News (the blog) and its post page,
 * the sign-in and account pages, a staff area, a search that finds posts,
 * people and pages alike and the e-mails. Built on Bootstrap 5.
 * Read by pg_design_templates() (includes/fn/designer.php).
 *
 * The pages are built in includes/design_templates/pinegrap-social/ (not
 * read as templates of their own): parts.php holds the node builders, the
 * community's data (topics, members, their posts, the guidelines) and the
 * parts every page repeats; mail.php the e-mail builders; pages_public.php,
 * posts.php, profiles.php, reports.php, standard.php and account.php the
 * pages and widgets. Each is included here in this one scope, in that
 * order, and uses the variables the files before it set.
 *
 * A post is a record of the Posts form, a profile one of the Profiles form,
 * a report one of the Reports form: collections a signed-in member sends.
 * A post is public once sent; a moderator takes it off the site by setting
 * its office-use-only Status to Hidden, and every public list and the post
 * page leave the hidden ones out. A member saves a post by following its
 * comments. The photos are kept in the posts folder, the profile pictures
 * in the avatars folder, both public. There are no likes, no following
 * and no messages between members.
 *
 * Opening the template gives the site the newsletter's contact group
 * ('contact_groups'), made once and found again by its name the next time
 * (_pg_tpl_contact_groups()).
 *
 * Placeholders, filled when the template is opened (pg_design_template_prepare()):
 *   {{page:<key>}}                  address of the template page with that key
 *   {{tab:<key>}}                   that page in a widget setting; its id once published
 *   {{folder:<key>}}                id of that template folder
 *   {{contact_group:<key>}}         id of a contact group below
 *   {{site_name}}, {{site_email}}, {{year}}
 * See includes/design_templates/hello-pinegrap.php for widgets, shared
 * components and 'requires'.
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
// is kept under a _part name ($topics, $members and $social_posts are
// parts.php's samples).
$social_dir = PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-social';
include $social_dir . '/parts.php';
include $social_dir . '/mail.php';
$public_part   = include $social_dir . '/pages_public.php';
$post_part     = include $social_dir . '/posts.php';
$profile_part  = include $social_dir . '/profiles.php';
$report_part   = include $social_dir . '/reports.php';
$standard_part = include $social_dir . '/standard.php';
$account_part  = include $social_dir . '/account.php';

// The pages in the order the editor lists them: the visitor's pages, the
// posts, the people, the reports, the standard pages, the account and staff
// pages, the e-mails last of each part.
$pages   = array_merge($public_part['pages'], $post_part['pages'], $profile_part['pages'], $report_part['pages'], $standard_part['pages'], $account_part['pages']);
$widgets = array_merge($public_part['widgets'], $post_part['widgets'], $profile_part['widgets'], $report_part['widgets'], $standard_part['widgets'], $account_part['widgets']);

// ── Search sections ─────────────────────────────────────────────────────
// The parts of the pages the site search points a result at (Search and
// SERP in the editor's options): a result that matches one links to it,
// the words are marked when the visitor arrives, and the page's structured
// data names it. A part is found by its id ('#…') or by the name it has in
// the layer tree, once per page; it keeps an id it already has. The topics
// are words a visitor may search for that the part does not say itself.
// The topics, the about page's values, the guidelines, the questions and
// the privacy notice are marked where they are built.
$search_sections = array(
    'contact' => array(
        lang('Contact Details') => array('contact-details', lang('address, e-mail, community team, support')),
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
// Two fonts: a round geometric sans for the headings and the community's
// name, a plain sans for the rest.
$social_fonts = array(
    array('family' => 'Figtree', 'weights' => array('600', '700', '800'), 'enabled' => true),
    array('family' => 'Inter', 'weights' => array('400', '500', '600'), 'enabled' => true),
);

// The template's own CSS in User Styles. Every class is named psc- (field
// ids use sc-); no block rule's class is named here. Selectors use
// descendants and pseudo-classes only, so they hold on the editor's canvas
// too. Colours are tokens only, so they follow any palette in both themes.
// A tint mixed with color-mix() comes after a plain fallback.
$social_css = implode("\n", array(
    ':root,[data-bs-theme]{--pg-font-heading:"Figtree",system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;'
        . '--pg-font-body:"Inter",system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;'
        . '--pg-heading-weight:700;--pg-display-weight:800;--pg-heading-tracking:-.015em;--pg-heading-line-height:1.15}',
    'h1,h2,h3,h4,h5,h6,.h1,.h2,.h3,.h4,.h5,.h6,.display-1,.display-2,.display-3,.display-4,.display-5,.display-6{font-family:var(--pg-font-heading)}',
    'body{font-family:var(--pg-font-body)}',
    // The small line over a section's title: spaced capitals.
    '.psc-eyebrow{letter-spacing:.12em;font-size:.75rem}',
    // The community's name in the header and the footer, in the heading font.
    '.psc-brand{font-family:var(--pg-font-heading);font-weight:800;letter-spacing:-.02em}',
    // The search box: a field wide enough for a few words, never wider than
    // the line.
    '.psc-search{max-width:32rem}',
    // A member's picture: a small round one beside a name, a large one on a
    // profile; the frame keeps its size whatever the photo's shape.
    '.psc-avatar{width:2.5rem;height:2.5rem}',
    '.psc-avatar-lg{width:6rem;height:6rem}',
    // A photo beside a line of the staff's list of posts.
    '.psc-thumb{width:4rem;height:4rem}',
    // A post of the feed is never wider than a phone's picture, so the
    // photo stays whole on a wide screen.
    '.psc-post{max-width:36rem;margin-inline:auto}',
    // A tile of a photo grid fills its column; the photo grows a little
    // under the pointer and the comment count shows over it on a shade, so
    // its words read on any photo.
    '.psc-tile-col .psc-tile{height:100%}',
    '.psc-tile img{transition:transform .3s ease}',
    '.psc-tile:hover img,.psc-tile:focus-within img{transform:scale(1.04)}',
    '.psc-tile-info{background:linear-gradient(to top,rgba(var(--bs-black-rgb),.65),transparent);opacity:0;transition:opacity .2s ease}',
    '.psc-tile:hover .psc-tile-info,.psc-tile:focus-within .psc-tile-info{opacity:1}',
    // A touch screen has no pointer to rest on a tile: there the count
    // stays in view.
    '@media (hover:none){.psc-tile-info{opacity:1}}',
    '.psc-tile-info,.psc-tile-info .text-body-secondary{color:var(--bs-white)!important}',
    // The join band: a soft light of the palette's colour from a corner.
    '@supports (background-color:color-mix(in srgb,red 8%,blue)){.psc-band{background-image:radial-gradient(ellipse 60% 90% at 100% 0%,color-mix(in srgb,var(--bs-secondary) 14%,transparent) 0%,transparent 70%)}}',
    // Cards that lead somewhere rise a little under the pointer.
    '.psc-lift{transition:transform .2s ease,box-shadow .2s ease}',
    '.psc-lift:hover,.psc-lift:focus-visible,.psc-lift:focus-within{transform:translateY(-3px);box-shadow:var(--bs-box-shadow)}',
    '@media (prefers-reduced-motion:reduce){.psc-lift,.psc-tile img{transition:none}.psc-tile-info{transition:none}.psc-lift:hover,.psc-lift:focus-visible,.psc-lift:focus-within{transform:none}.psc-tile:hover img,.psc-tile:focus-within img{transform:none}}',
    // Dark theme, grey sections (the account bar, page headers, grey bands):
    // the palette's primary and second colours are made readable on the
    // page background and fall to about 4:1 on this lighter grey. Links,
    // eyebrows and outline buttons there take the primary's emphasis tint,
    // lightened a fifth towards the text colour where the browser mixes
    // colours (some palettes' tint is as dark as the primary itself), and
    // the second colour gives way to the body's second text colour.
    '[data-bs-theme=dark] .bg-body-tertiary :is(.text-primary,.link-primary){color:var(--bs-primary-text-emphasis)!important}',
    '[data-bs-theme=dark] .bg-body-tertiary .btn-outline-primary{--bs-btn-color:var(--bs-primary-text-emphasis)}',
    '@supports (color:color-mix(in srgb,red 50%,blue)){'
        . '[data-bs-theme=dark] .bg-body-tertiary :is(.text-primary,.link-primary){color:color-mix(in srgb,var(--bs-primary-text-emphasis) 80%,var(--bs-emphasis-color))!important}'
        . '[data-bs-theme=dark] .bg-body-tertiary .btn-outline-primary{--bs-btn-color:color-mix(in srgb,var(--bs-primary-text-emphasis) 80%,var(--bs-emphasis-color))}}',
    '[data-bs-theme=dark] .bg-body-tertiary .link-secondary{color:var(--bs-secondary-color)!important}',
    '[data-bs-theme=dark] .bg-body-tertiary .btn-outline-secondary{--bs-btn-color:var(--bs-secondary-color)}',
    // Pills, tabs and underlined links there (filters, the staff bar) read
    // the link colour: they take the same tint.
    '[data-bs-theme=dark] .bg-body-tertiary .nav{--bs-nav-link-color:var(--bs-primary-text-emphasis)}',
    '@supports (color:color-mix(in srgb,red 50%,blue)){[data-bs-theme=dark] .bg-body-tertiary .nav{--bs-nav-link-color:color-mix(in srgb,var(--bs-primary-text-emphasis) 80%,var(--bs-emphasis-color))}}',
    // The primary's soft tint (the account card, soft bands): a palette's
    // primary and second colours are made readable on the page background
    // and fall under 4.5:1 here with several palettes (Coral 3.41:1, the
    // second colour of Graphite 3.16:1). Text, links and outline buttons
    // take the colour's emphasis tint, Bootstrap's pair for a soft tint.
    '.bg-primary-subtle :is(.text-primary,.link-primary){color:var(--bs-primary-text-emphasis)!important}',
    '.bg-primary-subtle :is(.btn-outline-primary,.btn-link){--bs-btn-color:var(--bs-primary-text-emphasis)}',
    '.bg-primary-subtle .link-secondary{color:var(--bs-secondary-text-emphasis)!important}',
    '.bg-primary-subtle .btn-outline-secondary{--bs-btn-color:var(--bs-secondary-text-emphasis)}',
));

return array(
    'name'        => lang('Social Community'),
    'version'     => '1.0.0',
    'framework'   => 'bootstrap5',
    'order'       => 78,
    'icon'        => 'bi-camera',
    'description' => lang('A photo community: members share photos with a caption, a topic and tags, explore them by topic and tag, comment and save posts, keep a profile and report what breaks the guidelines; moderators hide posts and read reports, with Community News, a newsletter, an inbox, a staff area and a search that finds posts, people and pages.'),

    // The theme the template is made for; the operator can pick another
    // before opening it, and change it later in the editor. The e-mails'
    // inline colours are this palette's deep pink (its secondary). The
    // template's own look travels in its User Styles ('assets'), so it
    // holds under any look and palette.
    'look'        => 'modern-soft',
    'palette'     => 'lavender',

    // What the installer lists on the template's card, after the points
    // every template shares.
    'highlights'  => array(
        lang('Members share photos with a caption, a topic, tags and a place; explore them by topic and by tag, each on its own page with comments.'),
        lang('A profile per member with their picture, words and posts, the people list by topic, and saved posts followed from the account.'),
        lang('Reports from members, notes for the moderators, and a post taken off the site by its status.'),
        lang('Community guidelines, Community News, a newsletter, an inbox, a staff area and a search that finds posts, people and pages.'),
    ),

    // Made (or found again) when the template is opened; see _pg_tpl_folders().
    // The members' photos and profile pictures are public files of their
    // own folders.
    'folders'     => array(
        'root'         => array('name' => 'pinegrap_social', 'access' => 'public'),
        'public'       => array('name' => 'public', 'parent' => 'root', 'access' => 'public'),
        'registration' => array('name' => 'registration', 'parent' => 'root', 'access' => 'registration'),
        'private'      => array('name' => 'private', 'parent' => 'root', 'access' => 'private'),
        'posts'        => array('name' => 'posts', 'parent' => 'root', 'access' => 'public'),
        'avatars'      => array('name' => 'avatars', 'parent' => 'root', 'access' => 'public'),
    ),

    'contact_groups' => array(
        'newsletter' => array(
            'name'         => lang('Community Newsletter'),
            'description'  => lang('The addresses left on the newsletter form of the community.'),
            'subscription' => true,
        ),
    ),

    'pages'       => $pages,

    'widgets'     => $widgets,

    'shared'      => array(
        'account_bar'  => array('name' => lang('Community Account Bar'), 'tree' => $account_bar_tree()),
        'notice'       => array('name' => lang('Community Notice'), 'tree' => $notice()),
        'header'       => array('name' => lang('Community Header'), 'tree' => $header()),
        'member_bar'   => array('name' => lang('Community Member Bar'), 'tree' => $member_bar()),
        'footer'       => array('name' => lang('Community Footer'), 'tree' => $footer()),
        'join_band'    => array('name' => lang('Community Join Band'), 'tree' => $join_band()),
        'staff_bar'    => array('name' => lang('Community Staff Bar'), 'tree' => $staff_bar()),
        'email_header' => array('name' => lang('Community E-mail Header'), 'tree' => $email_header_tree()),
        'email_footer' => array('name' => lang('Community E-mail Footer'), 'tree' => $email_footer_tree()),
    ),

    // The template's own look: its psc- rules in one User Styles entry, and
    // its two fonts.
    'assets'      => array(
        'css'   => pg_design_template_user_styles(array(), $social_css),
        'fonts' => json_encode($social_fonts),
    ),
);
