<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Driving School": a driving school with its licence
 * classes and courses. A home page with the licence classes, the featured
 * courses, the instructors, the pass stories and the News; a page per
 * licence class (car, automatic car, motorcycle, light truck, car with
 * trailer), the courses with their prices as a collection filtered by
 * licence and format, the instructors, the theory test and the practical
 * test, the about page and the questions; a course enrolment request and a
 * lesson request the learner follows from their account, with the status
 * and the office's answers, and lessons booked online at a free time; pass
 * stories as a collection; a contact page whose messages become
 * conversations, the News (the blog) with its post page, a newsletter,
 * the sign-in and account pages, a staff area (the new course and pass
 * story forms, the enrolment and lesson requests, the blog and staff
 * directory forms), a learner help that searches the whole site and the
 * e-mails. No shop: the school takes the payment at its office once an
 * enrolment is confirmed. Built on Bootstrap 5. Read by
 * pg_design_templates() (includes/fn/designer.php).
 *
 * The pages are built in includes/design_templates/pinegrap-drivingschool/
 * (not read as templates of their own): parts.php holds the node builders,
 * the school's data and the parts every page repeats; mail.php the e-mail
 * builders; pages_public.php, courses.php, enrolments.php, lessons.php,
 * stories.php, standard.php and account.php the pages and widgets. Each is
 * included here in this one scope, in that order, and uses the variables
 * the files before it set (stories.php reuses courses.php's list pieces,
 * lessons.php the request pieces of enrolments.php). parts.php keeps the
 * school's data in $licences, $courses, $instructors and $stories, so no
 * part's return is kept under those names.
 *
 * Opening the template gives the site the newsletter's contact group
 * ('contact_groups'), made once and found again by its name the next time
 * (_pg_tpl_contact_groups()). Only the address book needs the shop, and no
 * page links a shop page.
 *
 * A course and a pass story are records of their collections: the staff
 * add one on its new page and the lists show it at once, filtered by the
 * address (?licence=, ?format=). A course enrolment and a lesson request
 * are records of their own forms, requests and never a booked place or a
 * payment: a visitor without an account gets one (auto-registration), so
 * the request's page and the account's list show it at once. A request's
 * Status is an office-use-only field the staff change in the control
 * panel; the messages under it are the page's comments, mailed to the
 * learner and to the school.
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
// is kept under a _part name ($licences, $courses, $instructors and
// $stories are parts.php's data).
$ds_dir = PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-drivingschool';
include $ds_dir . '/parts.php';
include $ds_dir . '/mail.php';
$public_part    = include $ds_dir . '/pages_public.php';
$course_part    = include $ds_dir . '/courses.php';
$enrolment_part = include $ds_dir . '/enrolments.php';
$lesson_part    = include $ds_dir . '/lessons.php';
$story_part     = include $ds_dir . '/stories.php';
$standard_part  = include $ds_dir . '/standard.php';
$account_part   = include $ds_dir . '/account.php';

// The pages in the order the editor lists them: the visitor's pages, the
// courses, the requests, the stories, the standard pages, the account and
// staff pages, the e-mails last of each part.
$pages = array_merge($public_part['pages'], $course_part['pages'], $enrolment_part['pages'], $lesson_part['pages'],
    $story_part['pages'], $standard_part['pages'], $account_part['pages']);
$widgets = array_merge($public_part['widgets'], $course_part['widgets'], $enrolment_part['widgets'], $lesson_part['widgets'],
    $story_part['widgets'], $standard_part['widgets'], $account_part['widgets']);

// ── Search sections ─────────────────────────────────────────────────────
// The parts of the pages the site search points a result at (Search and
// SERP in the editor's options): a result that matches one links to it,
// the words are marked when the visitor arrives, and the page's structured
// data names it. A part is found by its id ('#…') or by the name it has in
// the layer tree, once per page; it keeps an id it already has. The topics
// are words a visitor may search for that the part does not say itself.
// The licences, the courses, the instructors, the tests, the about page,
// the questions, the stories and the requests are marked where they are
// built.
$search_sections = array(
    'contact' => array(
        lang('Contact Details') => array('contact-details', lang('address, phone, e-mail, office, branches, opening hours')),
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
// Two fonts: a firm sans for the headings, the figures and the plates, a
// plain sans for the rest.
$ds_fonts = array(
    array('family' => 'Barlow', 'weights' => array('500', '600', '700'), 'enabled' => true),
    array('family' => 'Inter', 'weights' => array('400', '500', '600'), 'enabled' => true),
);

// The template's own CSS in User Styles. Every class is named pdr- (the
// field ids are drv-); no block rule's class is named here. Selectors use
// descendants and pseudo-classes only, so they hold on the editor's canvas
// too. Colours are tokens only, so they follow any palette in both themes.
$ds_css = implode("\n", array(
    ':root,[data-bs-theme]{--pg-font-heading:"Barlow","Arial Narrow",Arial,sans-serif;'
        . '--pg-font-body:"Inter",system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;'
        . '--pg-heading-weight:600;--pg-display-weight:700;--pg-heading-tracking:0;--pg-heading-line-height:1.15}',
    'h1,h2,h3,h4,h5,h6,.h1,.h2,.h3,.h4,.h5,.h6,.display-1,.display-2,.display-3,.display-4,.display-5,.display-6{font-family:var(--pg-font-heading)}',
    'body{font-family:var(--pg-font-body)}',
    // Titles: the firm sans, tight.
    '.pdr-display{font-family:var(--pg-font-heading);font-weight:700;letter-spacing:-.01em}',
    // The small line over a title: spaced capitals.
    '.pdr-eyebrow{letter-spacing:.14em;font-size:.75rem}',
    // The school's name in the header and the footer.
    '.pdr-brand{font-family:var(--pg-font-heading);font-weight:700;letter-spacing:.02em}',
    // A price or a figure: figures of one width, in the heading font.
    '.pdr-stat{font-family:var(--pg-font-heading);font-variant-numeric:tabular-nums}',
    // A licence class's letter as a number plate: a framed square in the
    // heading font, on the page's own surface.
    '.pdr-plate{min-width:2.2em;height:2.2em;padding:0 .35em;font-family:var(--pg-font-heading);line-height:1;'
        . 'color:var(--bs-emphasis-color);background-color:var(--bs-body-bg);border:2px solid currentColor;border-radius:.4rem}',
    // A course's format, a story's try: the badge in the heading font.
    '.pdr-badge{font-family:var(--pg-font-heading);font-weight:600;letter-spacing:.02em}',
    // A learner's own words.
    '.pdr-quote{font-style:italic}',
    // The home page's first section and the enrol band: a soft wash of the
    // primary colour in one corner, where the browser mixes colours.
    '@supports (background-color:color-mix(in srgb,red 8%,blue)){.pdr-hero{background-image:radial-gradient(ellipse 70% 80% at 100% 0%,color-mix(in srgb,var(--bs-primary) 16%,transparent) 0%,transparent 70%)}}',
    // Licence, course, instructor and story cards rise a little under the pointer.
    '.pdr-lift{transition:transform .2s ease,box-shadow .2s ease}',
    '.pdr-lift:hover,.pdr-lift:focus-visible,.pdr-lift:focus-within{transform:translateY(-3px);box-shadow:var(--bs-box-shadow)}',
    '@media (prefers-reduced-motion:reduce){.pdr-lift{transition:none}.pdr-lift:hover,.pdr-lift:focus-visible,.pdr-lift:focus-within{transform:none}}',
));

return array(
    'name'        => lang('Driving School'),
    'version'     => '1.1.0',
    'framework'   => 'bootstrap5',
    'order'       => 111,
    'icon'        => 'bi-car-front',
    'description' => lang('A driving school: the licence classes with a page each, courses with their prices filtered by licence and format, the instructors, the theory and the practical test, course enrolment and lesson requests the learner follows from their account, lessons booked online, pass stories, the News, a newsletter, an inbox, a staff area and a learner help that searches the whole site.'),

    // The theme the template is made for; the operator can pick another
    // before opening it, and change it later in the editor. The e-mails'
    // inline colours are this palette's amber. The template's own look
    // travels in its User Styles ('assets'), so it holds under any look
    // and palette.
    'look'        => 'bold',
    'palette'     => 'amber',

    // What the installer lists on the template's card, after the points
    // every template shares.
    'highlights'  => array(
        lang('The licence classes with a page each: what they let you drive, the minimum age, the lessons and the tests.'),
        lang('Courses with their prices as words, filtered by licence and format, each opening its enrolment request.'),
        lang('Course enrolment and lesson requests followed from the account, and lessons booked online at a free time.'),
        lang('Pass stories, the News, a newsletter, an inbox, a staff area and a learner help.'),
    ),

    // Made (or found again) when the template is opened; see _pg_tpl_folders().
    'folders'     => array(
        'root'         => array('name' => 'pinegrap_drivingschool', 'access' => 'public'),
        'public'       => array('name' => 'public', 'parent' => 'root', 'access' => 'public'),
        'registration' => array('name' => 'registration', 'parent' => 'root', 'access' => 'registration'),
        'private'      => array('name' => 'private', 'parent' => 'root', 'access' => 'private'),
    ),

    'contact_groups' => array(
        'newsletter' => array(
            'name'         => lang('Driving School Newsletter'),
            'description'  => lang('The addresses left on the newsletter form of the driving school.'),
            'subscription' => true,
        ),
    ),

    'pages'       => $pages,

    'widgets'     => $widgets,

    'shared'      => array(
        'account_bar'  => array('name' => lang('Driving School Account Bar'), 'tree' => $account_bar_tree()),
        'notice'       => array('name' => lang('Driving School Notice'), 'tree' => $notice()),
        'header'       => array('name' => lang('Driving School Header'), 'tree' => $header()),
        'footer'       => array('name' => lang('Driving School Footer'), 'tree' => $footer()),
        'staff_bar'    => array('name' => lang('Driving School Staff Bar'), 'tree' => $staff_bar()),
        'enrol_band'   => array('name' => lang('Driving School Enrol Band'), 'tree' => $enrol_band()),
        'email_header' => array('name' => lang('Driving School E-mail Header'), 'tree' => $email_header_tree()),
        'email_footer' => array('name' => lang('Driving School E-mail Footer'), 'tree' => $email_footer_tree()),
    ),

    // The template's own look: the rules of the Driving School blocks its
    // pages place beside its pdr- rules in one User Styles entry, and its
    // two fonts.
    'assets'      => array(
        'css'   => pg_design_template_user_styles(array('drivingschool-licences', 'drivingschool-signs', 'drivingschool-test-sheet'), $ds_css),
        'fonts' => json_encode($ds_fonts),
    ),
);
