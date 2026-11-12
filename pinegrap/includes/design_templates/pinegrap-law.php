<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Law Firm": a law firm (or, by its words, a consulting
 * firm). A home page with the practice areas, the figures, how a matter
 * starts, the lawyers, what clients say and the newest insights; the
 * practice areas with one page each, the lawyers, the case results, the
 * fees, the about page and the questions; a consultation request the client
 * follows from their account, with the status and the lawyer's messages;
 * the careers page with the application form; a contact page whose
 * messages become conversations, Insights (the blog) with its post page,
 * the sign-in and account pages, a staff area (the consultation requests,
 * the applications, the Insights and staff directory forms), a help center
 * that searches the whole site and a newsletter. Built on Bootstrap 5. Read
 * by pg_design_templates() (includes/fn/designer.php).
 *
 * The pages are built in includes/design_templates/pinegrap-law/ (not read
 * as templates of their own): parts.php holds the node builders, the firm's
 * data and the parts every page repeats; mail.php the e-mail builders;
 * pages_public.php, consultation.php, standard.php and account.php the
 * pages and widgets. Each is included here in this one scope, in that
 * order, and uses the variables the files before it set (account.php draws
 * the staff area's list with consultation.php's row).
 *
 * Opening the template gives the site the newsletter's contact group
 * ('contact_groups'), made once and found again by its name the next time
 * (_pg_tpl_contact_groups()). There is no catalog: the firm sells nothing
 * online.
 *
 * A consultation is a record of the Consultations form. A visitor without
 * an account gets one (auto-registration), so the consultation's page and
 * My Consultations show it at once. Its Status is an office-use-only field
 * the staff change in the control panel (Received, Conflict check,
 * Scheduled, Engaged, Declined); the messages under it are the page's
 * comments, mailed to the client and to the firm. The form asks for the
 * other party, so the firm can check for a conflict of interest first.
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
// is kept under a _part name. The public part returns its pages only.
$law_dir = PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-law';
include $law_dir . '/parts.php';
include $law_dir . '/mail.php';
$public_part       = include $law_dir . '/pages_public.php';
$consultation_part = include $law_dir . '/consultation.php';
$standard_part     = include $law_dir . '/standard.php';
$account_part      = include $law_dir . '/account.php';

// The pages in the order the editor lists them: the visitor's pages, the
// consultation and careers, the standard pages, the account and staff
// pages, the e-mails last of each part.
$pages   = array_merge($public_part, $consultation_part['pages'], $standard_part['pages'], $account_part['pages']);
$widgets = array_merge($consultation_part['widgets'], $standard_part['widgets'], $account_part['widgets']);

// ── Search sections ─────────────────────────────────────────────────────
// The parts of the pages the site search points a result at (Search and
// SERP in the editor's options): a result that matches one links to it,
// the words are marked when the visitor arrives, and the page's structured
// data names it. A part is found by its id ('#…') or by the name it has in
// the layer tree, once per page; it keeps an id it already has. The topics
// are words a visitor may search for that the part does not say itself.
// The practice areas, the lawyers, the fees, the questions and the privacy
// notice are marked where they are built.
$search_sections = array(
    'contact' => array(
        lang('Contact Details') => array('contact-details', lang('address, phone, e-mail, office hours, location')),
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
// Two fonts: a sober serif for the headings, a plain sans for the rest.
$law_fonts = array(
    array('family' => 'Source Serif 4', 'weights' => array('600', '700'), 'enabled' => true),
    array('family' => 'Public Sans', 'weights' => array('400', '500', '600'), 'enabled' => true),
);

// The template's own CSS in User Styles. Every class is named plw- (Bootstrap
// owns pe- and ps-, and pl- is a Bootstrap 4 name); no block rule's class is
// named here. Selectors use descendants and pseudo-classes only, so they
// hold on the editor's canvas too. Colours are tokens only, so they follow
// any palette in both themes; a tint mixed with color-mix() comes after a
// plain fallback.
$law_css = implode("\n", array(
    ':root,[data-bs-theme]{--pg-font-heading:"Source Serif 4",Georgia,"Times New Roman",serif;'
        . '--pg-font-body:"Public Sans",system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;'
        . '--pg-heading-weight:600;--pg-display-weight:600;--pg-heading-tracking:0;--pg-heading-line-height:1.2}',
    'h1,h2,h3,h4,h5,h6,.h1,.h2,.h3,.h4,.h5,.h6,.display-1,.display-2,.display-3,.display-4,.display-5,.display-6{font-family:var(--pg-font-heading)}',
    'body{font-family:var(--pg-font-body)}',
    // The small line over a title: spaced capitals.
    '.plw-eyebrow{letter-spacing:.12em;font-size:.75rem}',
    // The firm's name in the header, in the heading serif.
    '.plw-brand{font-family:var(--pg-font-heading);font-weight:700;letter-spacing:.01em}',
    // The home page's first section: a quiet wash of the palette's colour.
    '.plw-hero{background-color:var(--bs-tertiary-bg)}',
    '@supports (background-color:color-mix(in srgb,red 8%,blue)){.plw-hero{background-image:linear-gradient(170deg,color-mix(in srgb,var(--bs-primary) 10%,var(--bs-body-bg)) 0%,var(--bs-body-bg) 65%);background-color:var(--bs-body-bg)}}',
    // Cards that lead somewhere rise a little under the pointer.
    '.plw-lift{transition:transform .2s ease,box-shadow .2s ease}',
    '.plw-lift:hover,.plw-lift:focus-visible,.plw-lift:focus-within{transform:translateY(-3px);box-shadow:var(--bs-box-shadow)}',
    '@media (prefers-reduced-motion:reduce){.plw-lift{transition:none}.plw-lift:hover,.plw-lift:focus-visible,.plw-lift:focus-within{transform:none}}',
    // A long site name ends with an ellipsis instead of widening the page:
    // the brand may shrink in the open bar, and on a phone it leaves room
    // for the menu button on the same line.
    '.navbar-brand{min-width:0;max-width:calc(100% - 5rem)}',
    // A palette's text colour is made readable on the page background only:
    // on the tertiary bands (the account bar, the page headers, the grey
    // sections) Coral, Amber and Electric fall just under 4.5:1 in the light
    // theme, Midnight and Burgundy to 3.9:1 in the dark one. Small coloured
    // text, links, section menus and outline buttons there are mixed 10%
    // darker in the light theme and 15% lighter in the dark one, 4.8:1 or
    // more with every palette; a hovered or focused link keeps its own
    // shade.
    '@supports (color:color-mix(in srgb,red 50%,blue)){.bg-body-tertiary{--plw-band-primary:color-mix(in srgb,var(--pg-primary-fg,var(--bs-primary)) 90%,black);--plw-band-secondary:color-mix(in srgb,var(--pg-secondary-fg,var(--bs-secondary)) 90%,black)}}',
    '@supports (color:color-mix(in srgb,red 50%,blue)){[data-bs-theme=dark] .bg-body-tertiary{--plw-band-primary:color-mix(in srgb,var(--pg-primary-fg,var(--bs-primary)) 85%,white);--plw-band-secondary:color-mix(in srgb,var(--pg-secondary-fg,var(--bs-secondary)) 85%,white)}}',
    '.bg-body-tertiary :is(.text-primary,.link-primary:not(:hover,:focus)){color:var(--plw-band-primary,var(--pg-primary-fg,var(--bs-primary)))!important}',
    '.bg-body-tertiary :is(.text-secondary,.link-secondary:not(:hover,:focus)){color:var(--plw-band-secondary,var(--pg-secondary-fg,var(--bs-secondary)))!important}',
    '.bg-body-tertiary .btn-outline-primary{--bs-btn-color:var(--plw-band-primary,var(--pg-primary-fg,var(--bs-primary)))}',
    '.bg-body-tertiary .btn-outline-secondary{--bs-btn-color:var(--plw-band-secondary,var(--pg-secondary-fg,var(--bs-secondary)))}',
    '.bg-body-tertiary .nav{--bs-nav-link-color:var(--plw-band-primary,var(--bs-link-color))}',
    // On the palette's soft surface (bg-primary-subtle: the call to action
    // bands, the account card) the colour as text falls to 3.2:1 in the
    // light theme (Graphite's secondary); there it takes the emphasis tint
    // the surface is made for, 4.5:1 or more with every palette in both
    // themes.
    '.bg-primary-subtle :is(.text-primary,.link-primary:not(:hover,:focus)){color:var(--bs-primary-text-emphasis)!important}',
    '.bg-primary-subtle :is(.text-secondary,.link-secondary:not(:hover,:focus)){color:var(--bs-secondary-text-emphasis)!important}',
    '.bg-primary-subtle .btn-outline-primary{--bs-btn-color:var(--bs-primary-text-emphasis)}',
    '.bg-primary-subtle .btn-outline-secondary{--bs-btn-color:var(--bs-secondary-text-emphasis)}',
));

return array(
    'name'        => lang('Law Firm'),
    'version'     => '1.1.1',
    'framework'   => 'bootstrap5',
    'order'       => 68,
    'icon'        => 'bi-bank',
    'description' => lang('A law firm: practice areas with a page each, the lawyers, case results and fees, a consultation request with a conflict check that the client follows from their account, careers with an application form, Insights, an inbox, a staff area and a help center that searches the whole site.'),

    // The theme the template is made for; the operator can pick another
    // before opening it, and change it later in the editor. The e-mails'
    // inline colours are this palette's navy. The template's own look
    // travels in its User Styles ('assets'), so it holds under any look and
    // palette.
    'look'        => 'corporate',
    'palette'     => 'midnight',

    // What the installer lists on the template's card, after the points
    // every template shares.
    'highlights'  => array(
        lang('Six practice areas with a page each, the lawyers with their bar admission and languages, case results and four ways to pay.'),
        lang('A consultation request that asks for the other party, so the firm can check for a conflict before it hears the matter.'),
        lang('Each request has its own page with its status and the lawyer\'s messages; the client follows it from their account and sends documents there.'),
        lang('Careers with an application form, Insights, an inbox, a staff area with the requests and applications, and a help center.'),
    ),

    'folders'     => array(
        'root'         => array('name' => 'pinegrap_law', 'access' => 'public'),
        'public'       => array('name' => 'public', 'parent' => 'root', 'access' => 'public'),
        'registration' => array('name' => 'registration', 'parent' => 'root', 'access' => 'registration'),
        'private'      => array('name' => 'private', 'parent' => 'root', 'access' => 'private'),
    ),

    'contact_groups' => array(
        'newsletter' => array(
            'name'         => lang('Law Firm Newsletter'),
            'description'  => lang('The addresses left on the newsletter form of the law firm.'),
            'subscription' => true,
        ),
    ),

    'pages'       => $pages,

    'widgets'     => $widgets,

    'shared'      => array(
        'account_bar'  => array('name' => lang('Law Firm Account Bar'), 'tree' => $account_bar_tree()),
        'notice'       => array('name' => lang('Law Firm Notice'), 'tree' => $notice()),
        'header'       => array('name' => lang('Law Firm Header'), 'tree' => $header()),
        'footer'       => array('name' => lang('Law Firm Footer'), 'tree' => $footer()),
        'staff_bar'    => array('name' => lang('Law Firm Staff Bar'), 'tree' => $staff_bar()),
        'consult_band' => array('name' => lang('Law Firm Consultation Band'), 'tree' => $consult_band()),
        'email_header' => array('name' => lang('Law Firm E-mail Header'), 'tree' => $email_header_tree()),
        'email_footer' => array('name' => lang('Law Firm E-mail Footer'), 'tree' => $email_footer_tree()),
    ),

    // The template's own look: its plw- rules in one User Styles entry, and
    // its two fonts.
    'assets'      => array(
        'css'   => pg_design_template_user_styles(array(), $law_css),
        'fonts' => json_encode($law_fonts),
    ),
);
