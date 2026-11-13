<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Web Designer Portfolio": a freelance web designer's
 * site. A home page with selected work, the services and what clients
 * say; the work list with filters by kind of project and one case-study
 * page per project; the services with three packages, the way of working,
 * the about page (story, skills, tools, experience) and the questions; a
 * project brief the client follows from their account, with its status
 * and the designer's messages; a contact page whose messages become
 * conversations, the Journal (the blog) with its post page, a newsletter,
 * the sign-in and account pages, a staff area (the new case study form,
 * the briefs, the blog and staff directory forms), a help center that
 * searches the whole site and the e-mails. Built on Bootstrap 5. Read by
 * pg_design_templates() (includes/fn/designer.php).
 *
 * The pages are built in includes/design_templates/pinegrap-portfolio/
 * (not read as templates of their own): parts.php holds the node builders,
 * the designer's data and the parts every page repeats; mail.php the
 * e-mail builders; pages_public.php, work.php, briefs.php, standard.php
 * and account.php the pages and widgets. Each is included here in this one
 * scope, in that order, and uses the variables the files before it set.
 * parts.php keeps the sample case studies in $projects, so no part's
 * return is kept under that name.
 *
 * Opening the template gives the site the newsletter's contact group
 * ('contact_groups'), made once and found again by its name the next time
 * (_pg_tpl_contact_groups()); the designer sends new case studies and
 * Journal posts to it by hand. There is no shop: a website is quoted, not
 * bought through a cart.
 *
 * A case study is a record of the Projects form, a collection: the staff
 * add one on the new case study page and the lists show it at once,
 * filtered by the address (?category=). A brief is a record of the Project
 * Briefs form; a visitor without an account gets one (auto-registration),
 * so the brief's page and My Briefs show it at once. Its Status is an
 * office-use-only field the designer changes in the control panel; the
 * messages under it are the page's comments, mailed to the client and to
 * the designer.
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
// is kept under a _part name ($projects is parts.php's sample data). The
// public part returns its pages only.
$portfolio_dir = PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-portfolio';
include $portfolio_dir . '/parts.php';
include $portfolio_dir . '/mail.php';
$public_part   = include $portfolio_dir . '/pages_public.php';
$work_part     = include $portfolio_dir . '/work.php';
$brief_part    = include $portfolio_dir . '/briefs.php';
$standard_part = include $portfolio_dir . '/standard.php';
$account_part  = include $portfolio_dir . '/account.php';

// The pages in the order the editor lists them: the visitor's pages, the
// work, the briefs, the standard pages, the account and staff pages, the
// e-mails last of each part.
$pages   = array_merge($public_part, $work_part['pages'], $brief_part['pages'], $standard_part['pages'], $account_part['pages']);
$widgets = array_merge($work_part['widgets'], $brief_part['widgets'], $standard_part['widgets'], $account_part['widgets']);

// ── Search sections ─────────────────────────────────────────────────────
// The parts of the pages the site search points a result at (Search and
// SERP in the editor's options): a result that matches one links to it,
// the words are marked when the visitor arrives, and the page's structured
// data names it. A part is found by its id ('#…') or by the name it has in
// the layer tree, once per page; it keeps an id it already has. The topics
// are words a visitor may search for that the part does not say itself.
// The services, the packages, the way of working, the about page, the
// questions and the privacy notice are marked where they are built.
$search_sections = array(
    'contact' => array(
        lang('Contact Details') => array('contact-details', lang('address, phone, e-mail, answer time, location')),
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
// Two fonts: a strong geometric sans for the headings and the figures, a
// quiet sans for the rest.
$portfolio_fonts = array(
    array('family' => 'Space Grotesk', 'weights' => array('500', '700'), 'enabled' => true),
    array('family' => 'Inter', 'weights' => array('400', '500', '600'), 'enabled' => true),
);

// The template's own CSS in User Styles. Every class is named pdp- (the
// forum template owns pf-); no block rule's class is named here. Selectors
// use descendants and pseudo-classes only, so they hold on the editor's
// canvas too. Colours are tokens only, so they follow any palette in both
// themes; a tint mixed with color-mix() comes after a plain fallback.
$portfolio_css = implode("\n", array(
    ':root,[data-bs-theme]{--pg-font-heading:"Space Grotesk",system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;'
        . '--pg-font-body:"Inter",system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;'
        . '--pg-heading-weight:700;--pg-display-weight:700;--pg-heading-tracking:-.02em;--pg-heading-line-height:1.1}',
    'h1,h2,h3,h4,h5,h6,.h1,.h2,.h3,.h4,.h5,.h6,.display-1,.display-2,.display-3,.display-4,.display-5,.display-6{font-family:var(--pg-font-heading)}',
    'body{font-family:var(--pg-font-body)}',
    // Large titles and figures: the heading font, drawn a little tighter.
    '.pdp-display{font-family:var(--pg-font-heading);letter-spacing:-.02em}',
    // The small line over a title: spaced capitals.
    '.pdp-eyebrow{letter-spacing:.14em;font-size:.75rem}',
    // The designer's name in the header, in the heading font.
    '.pdp-brand{font-family:var(--pg-font-heading);font-weight:700;letter-spacing:-.01em}',
    // The home page's first section: a soft wash of the palette's colour.
    '.pdp-hero{background-color:var(--bs-tertiary-bg)}',
    '@supports (background-color:color-mix(in srgb,red 8%,blue)){.pdp-hero{background-image:linear-gradient(160deg,color-mix(in srgb,var(--bs-primary) 8%,var(--bs-body-bg)) 0%,var(--bs-body-bg) 65%);background-color:var(--bs-body-bg)}}',
    // Case-study cards rise a little under the pointer.
    '.pdp-lift{transition:transform .2s ease}',
    '.pdp-lift:hover,.pdp-lift:focus-visible,.pdp-lift:focus-within{transform:translateY(-4px)}',
    '@media (prefers-reduced-motion:reduce){.pdp-lift{transition:none}.pdp-lift:hover,.pdp-lift:focus-visible,.pdp-lift:focus-within{transform:none}}',
));

return array(
    'name'        => lang('Web Designer Portfolio'),
    'version'     => '1.0.1',
    'framework'   => 'bootstrap5',
    'order'       => 72,
    'icon'        => 'bi-vector-pen',
    'description' => lang('A freelance web designer: selected work with a case study per project and filters by kind, services with three packages, the way of working, about and questions, a project brief the client follows from their account, a newsletter, the Journal, an inbox, a staff area and a help center that searches the whole site.'),

    // The theme the template is made for; the operator can pick another
    // before opening it, and change it later in the editor. The e-mails'
    // inline colours are this palette's graphite. The template's own look
    // travels in its User Styles ('assets'), so it holds under any look and
    // palette.
    'look'        => 'bold',
    'palette'     => 'graphite',

    // What the installer lists on the template's card, after the points
    // every template shares.
    'highlights'  => array(
        lang('Selected work with a case-study page per project, filters by kind of project, and six sample case studies the staff replace from a form.'),
        lang('Services with three packages, the way of working week by week, an about page with skills, tools and experience, and the questions clients ask.'),
        lang('A project brief with its own page, status and the designer\'s messages, followed from the client\'s account.'),
        lang('A newsletter, the Journal, an inbox, a staff area with the briefs and a help center.'),
    ),

    // Made (or found again) when the template is opened; see _pg_tpl_folders().
    'folders'     => array(
        'root'         => array('name' => 'pinegrap_portfolio', 'access' => 'public'),
        'public'       => array('name' => 'public', 'parent' => 'root', 'access' => 'public'),
        'registration' => array('name' => 'registration', 'parent' => 'root', 'access' => 'registration'),
        'private'      => array('name' => 'private', 'parent' => 'root', 'access' => 'private'),
    ),

    'contact_groups' => array(
        'newsletter' => array(
            'name'         => lang('Portfolio Newsletter'),
            'description'  => lang('The addresses left on the newsletter form of the web designer\'s site.'),
            'subscription' => true,
        ),
    ),

    'pages'       => $pages,

    'widgets'     => $widgets,

    'shared'      => array(
        'account_bar'  => array('name' => lang('Portfolio Account Bar'), 'tree' => $account_bar_tree()),
        'notice'       => array('name' => lang('Portfolio Notice'), 'tree' => $notice()),
        'header'       => array('name' => lang('Portfolio Header'), 'tree' => $header()),
        'footer'       => array('name' => lang('Portfolio Footer'), 'tree' => $footer()),
        'staff_bar'    => array('name' => lang('Portfolio Staff Bar'), 'tree' => $staff_bar()),
        'brief_band'   => array('name' => lang('Portfolio Brief Band'), 'tree' => $brief_band()),
        'email_header' => array('name' => lang('Portfolio E-mail Header'), 'tree' => $email_header_tree()),
        'email_footer' => array('name' => lang('Portfolio E-mail Footer'), 'tree' => $email_footer_tree()),
    ),

    // The template's own look: its pdp- rules in one User Styles entry, and
    // its two fonts.
    'assets'      => array(
        'css'   => pg_design_template_user_styles(array(), $portfolio_css),
        'fonts' => json_encode($portfolio_fonts),
    ),
);
