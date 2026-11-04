<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Online Courses": an online school. A home page with the
 * courses on top, the subjects, the figures, how it works, what students
 * say and the newest posts; the courses with one overview page each
 * (syllabus, instructor, price, Enrol), the instructors, the pricing with
 * the all-access membership, the about page and the questions; each
 * course's classroom and lessons, the first lesson a free preview; the
 * members' library; assignments sent and followed from the account; an
 * enrolment request for a course without the shop; the course and
 * membership products with the product, cart, checkout and order pages
 * when the shop is on; a contact page whose messages become conversations,
 * a blog with its post page, the sign-in and account pages, a staff area
 * (the enrolment requests, the assignments, the blog and staff directory
 * forms), a help center that searches the whole site and a newsletter.
 * Built on Bootstrap 5. Read by pg_design_templates()
 * (includes/fn/designer.php).
 *
 * The pages are built in includes/design_templates/pinegrap-courses/ (not
 * read as templates of their own): parts.php holds the node builders, the
 * school's data and the parts every page repeats; mail.php the e-mail
 * builders; pages_public.php, learn.php, assignments.php, shop.php,
 * standard.php and account.php the pages and widgets. Each is included
 * here in this one scope, in that order, and uses the variables the files
 * before it set.
 *
 * Access comes from features the site already has. Each course's classroom
 * and lessons sit in the course's own private folder; the course is a
 * product of the Courses group of the catalog ('catalog', from shop.php)
 * that gives the buyer that folder once the order is paid. The membership
 * is a product that renews the buyer's membership, which opens the
 * members' library in the membership folder. Without the shop, Enrol opens
 * an enrolment request and the staff give the folder in the control panel.
 * The catalog and the newsletter's contact group ('contact_groups') are
 * made once and found again by their names the next time
 * (_pg_tpl_catalog(), _pg_tpl_contact_groups()). The shop pages need the
 * shop ('requires' on each page); the course and pricing pages always
 * stand.
 *
 * Placeholders, filled when the template is opened (pg_design_template_prepare()):
 *   {{page:<key>}}                  address of the template page with that key
 *   {{tab:<key>}}                   that page in a widget setting; its id once published
 *   {{folder:<key>}}                id of that template folder
 *   {{product_group:<key>}}         id of a group of the catalog ('root': the site's top group)
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

// The parts set variables of their own in this scope (account.php sets
// $shop), so what each returns is kept under a _part name.
$courses_dir = PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-courses';
include $courses_dir . '/parts.php';
include $courses_dir . '/mail.php';
$public_part   = include $courses_dir . '/pages_public.php';
$learn_part    = include $courses_dir . '/learn.php';
$study_part    = include $courses_dir . '/assignments.php';
$shop_part     = include $courses_dir . '/shop.php';
$standard_part = include $courses_dir . '/standard.php';
$account_part  = include $courses_dir . '/account.php';

// The pages in the order the editor lists them: the visitor's pages, the
// classrooms and lessons, enrolment and assignments, the shop, the standard
// pages, the account and staff pages, the e-mails last of each part.
$pages   = array_merge($public_part['pages'], $learn_part['pages'], $study_part['pages'], $shop_part['pages'], $standard_part['pages'], $account_part['pages']);
$widgets = array_merge($public_part['widgets'], $learn_part['widgets'], $study_part['widgets'], $shop_part['widgets'], $standard_part['widgets'], $account_part['widgets']);

// ── Search sections ─────────────────────────────────────────────────────
// The parts of the pages the site search points a result at (Search and
// SERP in the editor's options): a result that matches one links to it,
// the words are marked when the visitor arrives, and the page's structured
// data names it. A part is found by its id ('#…') or by the name it has in
// the layer tree, once per page; it keeps an id it already has. The topics
// are words a visitor may search for that the part does not say itself.
// The courses, lessons, instructors, plans, questions and the privacy
// notice are marked where they are built.
$search_sections = array(
    'contact' => array(
        lang('Contact Details') => array('contact-details', lang('address, phone, e-mail, office, student support')),
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
// Two fonts: a friendly rounded sans for the headings, a plain sans for
// the rest.
$courses_fonts = array(
    array('family' => 'Nunito', 'weights' => array('600', '800'), 'enabled' => true),
    array('family' => 'Inter', 'weights' => array('400', '500', '600'), 'enabled' => true),
);

// The template's own CSS in User Styles. Every class is named pco-; no
// block rule's class is named here. Selectors use descendants and
// pseudo-classes only, so they hold on the editor's canvas too. Colours are
// tokens only, so they follow any palette in both themes; a tint mixed with
// color-mix() comes after a plain fallback.
$courses_css = implode("\n", array(
    ':root,[data-bs-theme]{--pg-font-heading:"Nunito",system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;'
        . '--pg-font-body:"Inter",system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;'
        . '--pg-heading-weight:800;--pg-display-weight:800;--pg-heading-tracking:-.005em;--pg-heading-line-height:1.2}',
    'h1,h2,h3,h4,h5,h6,.h1,.h2,.h3,.h4,.h5,.h6,.display-1,.display-2,.display-3,.display-4,.display-5,.display-6{font-family:var(--pg-font-heading)}',
    'body{font-family:var(--pg-font-body)}',
    // The small line over a section's title: spaced capitals.
    '.pco-eyebrow{letter-spacing:.1em;font-size:.75rem}',
    // The school's name in the header, in the heading font.
    '.pco-brand{font-family:var(--pg-font-heading);font-weight:800}',
    // The home page's first section: a wash of the palette's two colours.
    '.pco-hero{background-color:var(--bs-tertiary-bg)}',
    '@supports (background-color:color-mix(in srgb,red 8%,blue)){.pco-hero{background-image:linear-gradient(160deg,color-mix(in srgb,var(--bs-primary) 14%,var(--bs-body-bg)) 0%,var(--bs-body-bg) 60%,color-mix(in srgb,var(--bs-secondary) 8%,var(--bs-body-bg)) 100%);background-color:var(--bs-body-bg)}}',
    // Cards that lead somewhere rise a little under the pointer.
    '.pco-lift{transition:transform .2s ease,box-shadow .2s ease}',
    '.pco-lift:hover,.pco-lift:focus-visible,.pco-lift:focus-within{transform:translateY(-3px);box-shadow:var(--bs-box-shadow)}',
    '@media (prefers-reduced-motion:reduce){.pco-lift{transition:none}.pco-lift:hover,.pco-lift:focus-visible,.pco-lift:focus-within{transform:none}}',
));

// Made (or found again) when the template is opened; see _pg_tpl_folders().
// Each course has its own private folder, which its product grants; the
// members' library is in the membership folder.
$courses_folders = array(
    'root'         => array('name' => 'pinegrap_courses', 'access' => 'public'),
    'public'       => array('name' => 'public', 'parent' => 'root', 'access' => 'public'),
    'registration' => array('name' => 'registration', 'parent' => 'root', 'access' => 'registration'),
    'private'      => array('name' => 'private', 'parent' => 'root', 'access' => 'private'),
);
foreach ($courses as $c) {
    $courses_folders[$c['folder']] = array('name' => $c['folder'], 'parent' => 'root', 'access' => 'private');
}
$courses_folders['members'] = array('name' => 'members', 'parent' => 'root', 'access' => 'membership');

return array(
    'name'        => lang('Online Courses'),
    'version'     => '1.0.1',
    'framework'   => 'bootstrap5',
    'order'       => 67,
    'icon'        => 'bi-mortarboard',
    'description' => lang('An online school: courses with a syllabus, an instructor and a free first lesson, each course\'s classroom opened by buying it or by an enrolment request, an all-access membership with a members\' library, assignments with the instructor\'s feedback, a blog, an inbox, a staff area and a help center that searches the whole site.'),

    // The theme the template is made for; the operator can pick another
    // before opening it, and change it later in the editor. The e-mails'
    // inline colours are this palette's teal. The template's own look
    // travels in its User Styles ('assets'), so it holds under any look and
    // palette.
    'look'        => 'modern-soft',
    'palette'     => 'ocean',

    // What the installer lists on the template's card, after the points
    // every template shares.
    'highlights'  => array(
        lang('Courses with a syllabus by module, an instructor, a price and a free first lesson; each course\'s classroom and lessons in its own private folder.'),
        lang('With the shop on, a paid course opens its classroom by itself, and an all-access membership opens the members\' library; without it, an enrolment request the staff answer.'),
        lang('Assignments students send from a lesson and follow from their account, with the instructor\'s feedback and a status; questions under every lesson.'),
        lang('A staff area with the enrolment requests and the assignments, a blog, an inbox and a help center that searches the whole site.'),
    ),

    'folders'     => $courses_folders,

    'catalog'        => $shop_part['catalog'],

    'contact_groups' => array(
        'newsletter' => array(
            'name'         => lang('Courses Newsletter'),
            'description'  => lang('The addresses left on the newsletter form of the online school.'),
            'subscription' => true,
        ),
    ),

    'pages'       => $pages,

    'widgets'     => $widgets,

    'shared'      => array(
        'account_bar'  => array('name' => lang('Courses Account Bar'), 'tree' => $account_bar_tree()),
        'notice'       => array('name' => lang('Courses Notice'), 'tree' => $notice()),
        'header'       => array('name' => lang('Courses Header'), 'tree' => $header()),
        'footer'       => array('name' => lang('Courses Footer'), 'tree' => $footer()),
        'staff_bar'    => array('name' => lang('Courses Staff Bar'), 'tree' => $staff_bar()),
        'enrol_band'   => array('name' => lang('Courses Enrol Band'), 'tree' => $enrol_band()),
        'email_header' => array('name' => lang('Courses E-mail Header'), 'tree' => $email_header_tree()),
        'email_footer' => array('name' => lang('Courses E-mail Footer'), 'tree' => $email_footer_tree()),
    ),

    // The template's own look: its pco- rules in one User Styles entry, and
    // its two fonts.
    'assets'      => array(
        'css'   => pg_design_template_user_styles(array(), $courses_css),
        'fonts' => json_encode($courses_fonts),
    ),
);
