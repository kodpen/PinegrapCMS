<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Clinic": a local doctors' practice. A home page with the
 * way to book on top, the services, the doctors, how booking works, what
 * patients say and the newest posts; a services page with the fees, the
 * doctors with their days, the check-up packages, an appointment form whose
 * requests the patient follows from their account (the request's own page
 * with its messages, and My Appointments), a patient guide, the about page,
 * a blog with its post page, a contact page whose messages become
 * conversations (the inbox and the conversation page), the sign-in and
 * account pages, a staff area (the coming appointments, the blog's form,
 * the staff directory), a help center that searches the whole site and a
 * newsletter that collects e-mail addresses. Built on Bootstrap 5.
 * Read by pg_design_templates() (includes/fn/designer.php).
 *
 * Opening the template gives the site the newsletter's contact group
 * ('contact_groups'), made once and found again by its name the next time
 * (_pg_tpl_contact_groups()). There is no catalog: the practice sells
 * nothing online.
 *
 * An appointment request is a record of the Appointments form. A visitor
 * without an account gets one (auto-registration), so the request's page
 * and My Appointments show it at once. Its Status is an office-use-only
 * field the staff change in the control panel (Requested, Confirmed,
 * Completed, Cancelled); the messages under it are the page's comments,
 * mailed to the patient and to the practice.
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
 * Every structural node carries its role in '_label' (the designer's own
 * label, shown beside the element's tag in the layer tree).
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

$panel_url = (defined('OUTPUT_PATH') ? OUTPUT_PATH : '/') . (defined('OUTPUT_SOFTWARE_DIRECTORY') ? OUTPUT_SOFTWARE_DIRECTORY : '') . '/welcome.php';
$shop      = defined('ECOMMERCE') && ECOMMERCE;

// ── Node builders ───────────────────────────────────────────────────────
// Same shapes the editor's createNode() produces; the editor gives every
// node its id when the tabs open. $name is the node's label in the layer
// tree ('_label'): the tag stays visible beside it.
$el = function ($tag, $class, $name, $children = array(), $extra = array()) {
    $props = array('tag' => $tag, 'cssClass' => $class);
    if ($name !== '') $props['_label'] = $name;
    return array('type' => 'semantic', 'props' => array_merge($props, $extra), 'children' => $children);
};
$heading = function ($tag, $text, $class, $extra = array()) {
    return array('type' => 'content', 'props' => array_merge(array('contentType' => 'heading', 'tag' => $tag, 'text' => $text, 'cssClass' => $class), $extra), 'children' => array());
};
$para = function ($text, $class = '', $extra = array()) {
    return array('type' => 'content', 'props' => array_merge(array('contentType' => 'paragraph', 'text' => $text, 'cssClass' => $class), $extra), 'children' => array());
};
$link = function ($text, $href, $class, $attrs = array(), $extra = array()) {
    $props = array('contentType' => 'link', 'text' => $text, 'href' => $href, 'cssClass' => $class);
    if ($attrs) $props['_attrs'] = $attrs;
    return array('type' => 'content', 'props' => array_merge($props, $extra), 'children' => array());
};
$image = function ($src, $alt, $class, $extra = array()) {
    return array('type' => 'content', 'props' => array_merge(array('contentType' => 'image', 'src' => $src, 'alt' => $alt,
        'fluid' => false, 'rounded' => false, 'cssClass' => $class), $extra), 'children' => array());
};
$attr = function ($name, $value) {
    return array('name' => $name, 'value' => $value);
};
// A decorative icon: the words beside it say what it means.
$icon = function ($name, $class = '') use ($el, $attr) {
    return $el('i', trim('bi ' . $name . ' ' . $class), lang('Icon'), array(), array('_attrs' => array($attr('aria-hidden', 'true'))));
};
$span = function ($text, $class, $name, $extra = array()) use ($el) {
    return $el('span', $class, $name, array(), array_merge(array('text' => $text), $extra));
};
$container = function ($children, $class = '') {
    return array('type' => 'container', 'props' => array('fluid' => false, 'cssClass' => $class), 'children' => $children);
};
// A gutter above 4 is wider than the container's side padding and pushes a
// phone screen sideways; wider gaps are given from lg up (g-4 g-lg-5).
$row = function ($children, $gutter = '', $justify = '', $class = '') {
    return array('type' => 'row', 'props' => array('gutter' => $gutter, 'justify' => $justify, 'align' => '', 'cssClass' => $class), 'children' => $children);
};
$col = function ($children, $md = '', $lg = '', $sm = '', $xs = '12', $class = '') {
    return array('type' => 'col', 'props' => array('xs' => $xs, 'sm' => $sm, 'md' => $md, 'lg' => $lg, 'xl' => '', 'xxl' => '',
                                                  'offsetXs' => '', 'offsetMd' => '', 'order' => '', 'cssClass' => $class), 'children' => $children);
};
$root = function ($children) {
    return array('type' => 'root', 'props' => array('cssClass' => 'd-flex flex-column min-vh-100'), 'children' => $children);
};
$widget_root = function ($children) {
    return array('type' => 'root', 'props' => array(), 'children' => $children);
};
$loop = function ($children) {
    return array('type' => 'loop_area', 'props' => array(), 'children' => $children);
};
$widget = function ($key) {
    return array('type' => 'shared_ref', 'props' => array('templateWidget' => $key), 'children' => array());
};
$shared = function ($key) {
    return array('type' => 'shared_ref', 'props' => array('templateShared' => $key), 'children' => array());
};
$bind = function ($token) {
    return array('_bindings' => array('text' => $token));
};
$show_when = function ($flag) {
    return array('_bindings' => array('eo_visible_if' => $flag));
};
// A part of a page the site search can point a result at (Search and SERP in
// the editor's options): its anchor, the switch, and the topics a visitor
// may search for that the part does not say itself.
$searchable = function ($id, $topics = '') {
    $props = array('id' => $id, 'searchSection' => true);
    if ($topics !== '') $props['searchKeywords'] = $topics;
    return $props;
};
// The language switcher button: the server draws it for each request and
// leaves it out while the site has a single language, so it can stay in the
// header of every site.
$lang_switcher = function ($class = '', $variant = 'secondary') {
    return array('type' => 'component', 'props' => array(
        'componentType' => 'language_switcher',
        'variant' => $variant, 'outline' => true, 'size' => 'sm',
        'align' => 'lg-end', 'display' => 'name', 'icon' => 'translate',
        'cssClass' => $class,
    ), 'children' => array());
};

// A sample portrait: the doctor's initials on the palette's colour, until
// the practice puts a photo in its place. The initials follow the name in
// the site's language.
$portrait = function ($name, $size = 480) {
    $words = preg_split('/\s+/u', trim(preg_replace('/^\S+\.\s*/u', '', $name)));
    $initials = '';
    foreach (array_slice($words, 0, 2) as $w) $initials .= mb_strtoupper(mb_substr($w, 0, 1, 'UTF-8'), 'UTF-8');
    return 'https://placehold.co/' . $size . 'x' . $size . '/ccfbf1/0f766e?text=' . rawurlencode($initials !== '' ? $initials : 'Dr');
};

// ── The practice: its services, doctors and check-ups ──────────────────

// The services, in the order the pages list them. Each has its section on
// the services page (#service-<key>), the words a visitor may search for,
// how long a visit takes and what it costs.
$services = array(
    'family' => array('icon' => 'bi-house-heart', 'title' => lang('Family Medicine'),
        'text'     => lang('Everyday care for every age: illnesses, long-term conditions, check-ups and advice you can act on.'),
        'items'    => array(lang('Coughs, colds and infections'), lang('Blood pressure and diabetes care'), lang('Health reports and referrals'), lang('Advice before travel')),
        'duration' => lang('20 minutes'), 'price' => lang('$80'),
        'topics'   => lang('family doctor, general practitioner, GP, illness, fever, referral')),
    'children' => array('icon' => 'bi-balloon-heart', 'title' => lang('Paediatrics'),
        'text'     => lang('Children\'s health from the first weeks to the teenage years, in a room made for small patients.'),
        'items'    => array(lang('Newborn and baby checks'), lang('Growth and development'), lang('Childhood illnesses'), lang('School and sports reports')),
        'duration' => lang('30 minutes'), 'price' => lang('$90'),
        'topics'   => lang('children, child, baby, newborn, paediatrician, kids')),
    'heart' => array('icon' => 'bi-heart-pulse', 'title' => lang('Cardiology'),
        'text'     => lang('Heart checks and follow-up, with the tests done in the same visit.'),
        'items'    => array(lang('ECG and blood pressure'), lang('Echocardiography'), lang('Cholesterol follow-up'), lang('Chest pain and palpitations')),
        'duration' => lang('40 minutes'), 'price' => lang('$140'),
        'topics'   => lang('heart, cardiologist, ECG, EKG, blood pressure, cholesterol')),
    'skin' => array('icon' => 'bi-droplet-half', 'title' => lang('Dermatology'),
        'text'     => lang('Skin, hair and nails: from a rash that will not go away to the yearly mole check.'),
        'items'    => array(lang('Mole and skin checks'), lang('Acne and eczema'), lang('Allergy tests'), lang('Hair and nail problems')),
        'duration' => lang('30 minutes'), 'price' => lang('$110'),
        'topics'   => lang('skin, dermatologist, rash, mole, acne, eczema, allergy')),
    'lab' => array('icon' => 'bi-clipboard2-pulse', 'title' => lang('Laboratory & Tests'),
        'text'     => lang('Blood and urine tests on site, most results the same day and in your account.'),
        'items'    => array(lang('Blood count and biochemistry'), lang('Thyroid and vitamin levels'), lang('Urine tests'), lang('Results explained by your doctor')),
        'duration' => lang('10 minutes'), 'price' => lang('From $25'),
        'topics'   => lang('blood test, laboratory, lab, results, urine, analysis')),
    'vaccines' => array('icon' => 'bi-shield-plus', 'title' => lang('Vaccinations'),
        'text'     => lang('The childhood schedule, the yearly flu jab and the vaccines you need before a journey.'),
        'items'    => array(lang('Childhood vaccines'), lang('Flu and pneumonia vaccines'), lang('Travel vaccines'), lang('A record of every dose')),
        'duration' => lang('15 minutes'), 'price' => lang('From $45'),
        'topics'   => lang('vaccine, vaccination, jab, flu shot, immunisation, travel')),
);

// The doctors, in the order the pages list them, each with the service they
// lead and the days they see patients.
$doctors = array(
    'cooper' => array('name' => lang('Dr. Jane Cooper'), 'specialty' => lang('Family Medicine'), 'service' => 'family',
        'bio'       => lang('Fifteen years as a family doctor; she looks after whole families, from newborns to grandparents.'),
        'languages' => lang('English, Turkish'), 'days' => lang('Monday to Friday')),
    'brooks' => array('name' => lang('Dr. Michael Brooks'), 'specialty' => lang('Cardiology'), 'service' => 'heart',
        'bio'       => lang('Heart specialist; he runs the heart check-up and the blood pressure clinic.'),
        'languages' => lang('English, German'), 'days' => lang('Tuesday and Thursday')),
    'turner' => array('name' => lang('Dr. Emily Turner'), 'specialty' => lang('Paediatrics'), 'service' => 'children',
        'bio'       => lang('Children\'s doctor with a calm way for nervous first visits and vaccinations.'),
        'languages' => lang('English, Turkish'), 'days' => lang('Monday, Wednesday and Friday')),
    'ross' => array('name' => lang('Dr. Daniel Ross'), 'specialty' => lang('Dermatology'), 'service' => 'skin',
        'bio'       => lang('Skin specialist for rashes, moles, acne and allergy tests.'),
        'languages' => lang('English, Arabic'), 'days' => lang('Wednesday and Saturday')),
);

// The check-up packages: the middle one is the one most patients choose.
$packages = array(
    array('title' => lang('Essential Check-up'), 'price' => lang('$150'), 'featured' => false,
          'text'  => lang('A yearly look at the basics, for adults with no complaints.'),
          'items' => array(lang('Consultation with a family doctor'), lang('Blood count and blood sugar'), lang('Cholesterol and kidney tests'), lang('Blood pressure and body mass index'))),
    array('title' => lang('Heart Check-up'), 'price' => lang('$240'), 'featured' => true,
          'text'  => lang('For anyone over forty, or with heart disease in the family.'),
          'items' => array(lang('Everything in the Essential Check-up'), lang('Consultation with a cardiologist'), lang('ECG and echocardiography'), lang('A plan you take home'))),
    array('title' => lang('Complete Check-up'), 'price' => lang('$390'), 'featured' => false,
          'text'  => lang('The whole picture in one morning, results explained the same week.'),
          'items' => array(lang('Everything in the Heart Check-up'), lang('Thyroid, vitamin and liver tests'), lang('Skin check with a dermatologist'), lang('A follow-up call with your doctor'))),
);

// The answers of the appointment form's pick lists. Each answer's value is
// its own words, so the e-mails and the lists read as the patient chose.
$service_titles = array();
foreach ($services as $s) $service_titles[] = $s['title'];
$doctor_choices = array(lang('No preference'));
foreach ($doctors as $d) $doctor_choices[] = $d['name'] . ' · ' . $d['specialty'];
$time_windows = array(
    lang('Morning (08:00–12:00)'),
    lang('Midday (12:00–14:00)'),
    lang('Afternoon (14:00–17:00)'),
    lang('Evening (17:00–19:00)'),
);

// ── The parts every page repeats ────────────────────────────────────────

// The line in the account bar: where to turn in an emergency. A shared
// component.
$notice = function () use ($el, $icon, $span) {
    return $el('p', 'd-flex align-items-center gap-2 mb-0', lang('Notice'), array(
        $icon('bi-telephone-plus', 'text-danger'),
        $span(lang('Medical emergency? Call 112 straight away.'), '', lang('Text')),
    ));
};

// The bar above the header: the notice on the left, the language switcher
// and the login region on the right. A shared component like the header
// under it: the login region and the notice are placed inside it.
$account_bar = function () use ($el, $widget, $shared, $lang_switcher) {
    return $el('div', 'border-bottom small bg-body-tertiary', lang('Account Bar'), array(
        $el('div', 'container d-flex flex-wrap align-items-center justify-content-between gap-2 py-1', lang('Account Area'), array(
            $el('div', '', lang('Announcement Area'), array($shared('notice'))),
            $el('div', 'd-flex align-items-center gap-2 ms-auto', lang('Account Links'), array(
                $lang_switcher(), $widget('login_region'),
            )),
        )),
    ));
};
// Every page places the bar as one shared component, so a change to it is
// made once for the whole site. The builder above is the component's tree.
$account_bar_tree = $account_bar;
$account_bar = function () use ($shared) {
    return $shared('account_bar');
};

// The header: the practice's name, the menu, the phone and the way to book.
// The links to the current page are marked active on the server (smart
// active state), which is what lets one header serve every page.
$header = function () use ($el, $link, $icon, $span, $attr) {
    $items = array();
    foreach (array(
        array('services', lang('Our Services')),
        array('doctors', lang('Our Doctors')),
        array('checkups', lang('Check-ups')),
        array('patient_guide', lang('Patient Guide')),
        array('blog', lang('Blog')),
        array('contact', lang('Contact us')),
    ) as $n) {
        $items[] = $el('li', 'nav-item', lang('Nav Item'), array($link($n[1], '{{page:' . $n[0] . '}}', 'nav-link text-nowrap')));
    }
    return $el('header', 'bg-body border-bottom', lang('Site Header'), array(
        $el('nav', 'navbar navbar-expand-xl py-3', lang('Navbar'), array(
            $el('div', 'container', lang('Navbar Container'), array(
                $el('a', 'navbar-brand d-flex align-items-center gap-2 fw-bold', lang('Brand'), array(
                    $el('span', 'd-inline-flex p-2 rounded-circle text-bg-primary lh-1', lang('Icon Box'), array($icon('bi-heart-pulse', 'fs-5'))),
                    $span('{{site_name}}', 'fs-5', lang('Name')),
                ), array('href' => '{{page:home}}')),
                $el('button', 'navbar-toggler', lang('Toggler'), array(
                    $el('span', 'navbar-toggler-icon', lang('Toggler Icon')),
                ), array('_attrs' => array(
                    $attr('type', 'button'),
                    $attr('data-bs-toggle', 'collapse'),
                    $attr('data-bs-target', '#cl-nav'),
                    $attr('aria-controls', 'cl-nav'),
                    $attr('aria-expanded', 'false'),
                    $attr('aria-label', lang('Toggle navigation')),
                ))),
                $el('div', 'collapse navbar-collapse', lang('Nav Links'), array(
                    $el('ul', 'navbar-nav mx-xl-auto column-gap-xl-2 py-3 py-xl-0', lang('Nav Menu'), $items, array('smartActive' => true)),
                    $el('div', 'd-flex flex-column flex-xl-row align-items-xl-center gap-2 gap-xl-3 pb-2 pb-xl-0', lang('Header Actions'), array(
                        $el('a', 'd-inline-flex d-xl-none d-xxl-inline-flex align-items-center gap-2 link-body-emphasis text-decoration-none text-nowrap fw-semibold small', lang('Phone'), array(
                            $icon('bi-telephone', 'text-primary'),
                            $span('+90 555 000 00 00', '', lang('Link Text')),
                        ), array('href' => 'tel:+905550000000')),
                        $el('a', 'btn btn-primary d-inline-flex align-items-center justify-content-center gap-2 px-3 text-nowrap', lang('Call to Action'), array(
                            $icon('bi-calendar2-check'),
                            $span(lang('Book an Appointment'), '', lang('Link Text')),
                        ), array('href' => '{{page:appointment}}')),
                    )),
                ), array('id' => 'cl-nav')),
            )),
        ), array('_attrs' => array($attr('aria-label', lang('Main navigation'))))),
    ));
};

// The opening hours, as a definition list: the day, then the hours.
$hours = function ($class = 'row small mb-0') use ($el) {
    $days = array(
        array(lang('Monday–Friday'), '08:00–19:00'),
        array(lang('Saturday'), '09:00–14:00'),
        array(lang('Sunday'), lang('Closed')),
    );
    $children = array();
    foreach ($days as $d) {
        $children[] = $el('dt', 'col-6 fw-normal text-body-secondary', lang('Day'), array(), array('text' => $d[0]));
        $children[] = $el('dd', 'col-6 mb-2', lang('Time'), array(), array('text' => $d[1]));
    }
    return $el('dl', $class, lang('List'), $children);
};

// The footer, on a dark band: the practice in a sentence, the links for
// patients, the services, how to reach the practice and the hours; under
// them the copyright and the reminder that the site is no doctor.
$footer = function () use ($el, $para, $link, $icon, $span, $heading, $hours, $services, $attr) {
    $plain = 'link-body-emphasis text-decoration-none';
    $list = function ($links) use ($el) {
        $items = array();
        foreach ($links as $l) $items[] = $el('li', 'mb-2', lang('List Item'), array($l));
        return $el('ul', 'list-unstyled small mb-0', lang('Links'), $items);
    };
    $service_links = array();
    foreach ($services as $key => $s) $service_links[] = $link($s['title'], '{{page:services}}#service-' . $key, $plain);
    $social = function ($name, $label) use ($el, $icon, $attr) {
        return $el('a', 'link-body-emphasis fs-5', $label, array($icon($name)), array('href' => '#', '_attrs' => array($attr('aria-label', $label))));
    };
    $contact_line = function ($icon_name, $child, $name) use ($el, $icon) {
        return $el('p', 'd-flex align-items-start gap-2 small mb-2', $name, array($icon($icon_name, 'text-primary'), $child));
    };

    return $el('footer', 'mt-auto pt-5 pb-4 bg-body text-body', lang('Site Footer'), array(
        $el('div', 'container', lang('Footer Container'), array(
            $el('div', 'row g-4', lang('Footer Columns'), array(
                $el('div', 'col-12 col-lg-4', lang('About the Practice'), array(
                    $el('a', 'd-inline-flex align-items-center gap-2 fw-bold fs-5 mb-3 ' . $plain, lang('Brand'), array(
                        $el('span', 'd-inline-flex p-2 rounded-circle text-bg-primary lh-1', lang('Icon Box'), array($icon('bi-heart-pulse'))),
                        $span('{{site_name}}', '', lang('Name')),
                    ), array('href' => '{{page:home}}')),
                    $para(lang('A neighbourhood practice for the whole family: family doctors, specialists and a laboratory under one roof.'), 'small text-body-secondary mb-3'),
                    $el('div', 'd-flex gap-3', lang('Social Links'), array(
                        $social('bi-instagram', 'Instagram'),
                        $social('bi-facebook', 'Facebook'),
                        $social('bi-linkedin', 'LinkedIn'),
                    )),
                )),
                $el('div', 'col-6 col-md-4 col-lg-2', lang('Patients'), array(
                    $heading('h2', lang('Patients'), 'h6 text-uppercase small fw-bold mb-3'),
                    $list(array(
                        $link(lang('Book an Appointment'), '{{page:appointment}}', $plain),
                        $link(lang('My Appointments'), '{{page:my_appointments}}', $plain),
                        $link(lang('Patient Guide'), '{{page:patient_guide}}', $plain),
                        $link(lang('Inbox'), '{{page:inbox}}', $plain),
                        $link(lang('My Account'), '{{page:my_account}}', $plain),
                        $link(lang('Help Center'), '{{page:search}}', $plain),
                    )),
                )),
                $el('div', 'col-6 col-md-4 col-lg-2', lang('Footer Links'), array(
                    $heading('h2', lang('Our Services'), 'h6 text-uppercase small fw-bold mb-3'),
                    $list($service_links),
                )),
                $el('div', 'col-12 col-md-4 col-lg-4', lang('Contact Details'), array(
                    $heading('h2', lang('Visit us'), 'h6 text-uppercase small fw-bold mb-3'),
                    $contact_line('bi-geo-alt', $span(lang('Sample Street 1, 34000 Istanbul, Türkiye'), '', lang('Text')), lang('Address')),
                    $contact_line('bi-telephone', $link('+90 555 000 00 00', 'tel:+905550000000', $plain), lang('Phone')),
                    $contact_line('bi-envelope', $link('hello@example.com', 'mailto:hello@example.com', $plain . ' text-break'), lang('Email')),
                    $hours('row small mt-3 mb-0'),
                )),
            )),
            $el('div', 'd-flex flex-column flex-md-row justify-content-between align-items-center gap-3 border-top mt-4 pt-4', lang('Footer Bottom'), array(
                $para('© {{year}} {{site_name}}', 'small text-body-secondary mb-0'),
                $para(lang('What this site says is general information, not a diagnosis. Ask your doctor about your own health.'), 'small text-body-secondary text-md-end mb-0'),
            )),
        )),
    ), array('_attrs' => array($attr('data-bs-theme', 'dark'))));
};

// The booking band: a full-width band in the palette's colour with the way
// to book and the phone. Shared, so every page that ends with it carries
// the same offer.
$booking_band = function () use ($el, $heading, $para, $link, $icon, $container, $row, $col) {
    return $el('section', 'py-5 text-bg-primary', lang('Call to Action'), array(
        $container(array(
            $row(array(
                $col(array(
                    $el('div', 'd-flex align-items-start gap-3', lang('Text'), array(
                        $icon('bi-calendar-heart', 'fs-1 lh-1'),
                        $el('div', '', lang('Words'), array(
                            $heading('h2', lang('Need to see a doctor? Book in two minutes.'), 'h3 fw-bold mb-2'),
                            $para(lang('Choose the service and a time of day; we confirm by e-mail, usually within two working hours.'), 'mb-0 opacity-75'),
                        )),
                    )),
                ), '', '8'),
                $col(array(
                    $el('div', 'd-flex flex-wrap gap-2 justify-content-lg-end', lang('Buttons'), array(
                        $link(lang('Book an Appointment'), '{{page:appointment}}', 'btn btn-light btn-lg px-4'),
                        $link(lang('Call us'), 'tel:+905550000000', 'btn btn-outline-light btn-lg px-4'),
                    )),
                ), '', '4'),
            ), '4', '', 'align-items-center'),
        )),
    ));
};

// A page of the practice: the account bar, the shared header, the page's
// own content, the newsletter band (left off where it would be in the way:
// the forms, the account pages) and the shared footer.
$page = function ($main, $newsletter = true) use ($root, $account_bar, $shared, $el, $widget) {
    $children = array(
        $account_bar(),
        $shared('header'),
        $el('main', '', lang('Main Content'), $main),
    );
    if ($newsletter) $children[] = $widget('newsletter_band');
    $children[] = $shared('footer');
    return $root($children);
};

// The staff pages' own menu, under the header: one shared component.
$staff_bar = function () use ($el, $link, $span) {
    $item = function ($key, $text) use ($link) {
        return $link($text, '{{page:' . $key . '}}', 'nav-link px-0');
    };
    return $el('div', 'border-bottom small', lang('Staff Bar'), array(
        $el('div', 'container d-flex flex-wrap align-items-center gap-4 py-2', lang('Staff Bar Container'), array(
            $span(lang('Staff'), 'badge rounded-pill text-bg-primary', lang('Staff Badge')),
            $el('nav', 'nav nav-underline gap-4', lang('Staff Links'), array(
                $item('staff', lang('Staff Area')),
                $item('my_appointments', lang('All Appointments')),
                $item('blog_new', lang('New Blog Post')),
                $item('staff_entry', lang('Add to the Staff Directory')),
                $item('inbox', lang('Inbox')),
            ), array('smartActive' => true)),
        )),
    ));
};

// A page of the staff area: the staff bar under the header, no newsletter.
$staff_page = function ($main) use ($root, $account_bar, $shared, $el) {
    return $root(array(
        $account_bar(),
        $shared('header'),
        $shared('staff_bar'),
        $el('main', '', lang('Main Content'), $main),
        $shared('footer'),
    ));
};

// The top of the inner pages: where the page sits, its title and one
// sentence, and whatever the page adds under them.
$page_header = function ($title, $lead, $more = array()) use ($el, $heading, $para, $link, $container, $attr) {
    return $el('header', 'py-4 py-lg-5 border-bottom bg-primary-subtle', lang('Page Header'), array(
        $container(array_merge(array(
            $el('nav', '', lang('Breadcrumb'), array(
                $el('ol', 'breadcrumb small mb-2', lang('Breadcrumb List'), array(
                    $el('li', 'breadcrumb-item', lang('Breadcrumb Item'), array($link(lang('Homepage'), '{{page:home}}', 'link-secondary text-decoration-none'))),
                    $el('li', 'breadcrumb-item active', lang('Breadcrumb Item'), array(), array('text' => $title, '_attrs' => array($attr('aria-current', 'page')))),
                )),
            ), array('_attrs' => array($attr('aria-label', lang('Breadcrumb'))))),
            $heading('h1', $title, 'display-6 fw-bold mb-2'),
            $para($lead, 'lead text-body-secondary mb-0'),
        ), $more)),
    ));
};

// The heading over a section: a small line above, the title, a link to
// the rest, and a rule under them.
$section_header = function ($eyebrow, $title, $more_text = '', $more_href = '') use ($el, $heading, $para, $icon, $span) {
    $children = array($el('div', '', lang('Section Title'), array(
        $para($eyebrow, 'small text-uppercase fw-semibold text-primary mb-1'),
        $heading('h2', $title, 'h3 fw-bold mb-0'),
    )));
    if ($more_text !== '') {
        $children[] = $el('a', 'd-inline-flex align-items-center gap-1 fw-semibold link-primary text-decoration-none text-nowrap', lang('Link'), array(
            $span($more_text, '', lang('Link Text')),
            $icon('bi-arrow-right'),
        ), array('href' => $more_href));
    }
    return $el('div', 'd-flex flex-wrap align-items-end justify-content-between gap-2 pb-3 mb-4 border-bottom', lang('Section Header'), $children);
};

// An icon in a soft round tile, the way the services are marked.
$icon_tile = function ($name, $class = '') use ($el, $icon) {
    return $el('span', trim('d-inline-flex align-items-center justify-content-center rounded-circle bg-primary-subtle text-primary-emphasis p-3 lh-1 ' . $class), lang('Icon Box'), array(
        $icon($name, 'fs-4'),
    ));
};

// Five stars, for what patients say.
$stars = function () use ($el, $icon, $span) {
    return $el('p', 'd-flex gap-1 text-warning mb-2', lang('Rating'), array(
        $span(lang('Five out of five stars'), 'visually-hidden', lang('Text')),
        $icon('bi-star-fill'), $icon('bi-star-fill'), $icon('bi-star-fill'), $icon('bi-star-fill'), $icon('bi-star-fill'),
    ));
};

// ── Form fields ─────────────────────────────────────────────────────────
$field = function ($id, $label, $control, $class = 'mb-3') use ($el, $attr) {
    return $el('div', $class, $label, array(
        $el('label', 'form-label', lang('Label'), array(), array('text' => $label, '_attrs' => array($attr('for', $id)))),
        $control,
    ));
};
$input = function ($id, $type, $name, $autocomplete = '', $required = true, $cf = array()) use ($el, $attr) {
    $attrs = array($attr('type', $type), $attr('name', $name));
    if ($required) $attrs[] = $attr('required', '');
    if ($autocomplete !== '') $attrs[] = $attr('autocomplete', $autocomplete);
    $props = array('id' => $id, '_attrs' => $attrs);
    if ($cf) $props['_cf'] = $cf;
    return $el('input', 'form-control', lang('Field'), array(), $props);
};
$textarea = function ($id, $name, $rows, $required = true) use ($el, $attr) {
    $attrs = array($attr('name', $name), $attr('rows', (string)$rows));
    if ($required) $attrs[] = $attr('required', '');
    return $el('textarea', 'form-control', lang('Field'), array(), array('id' => $id, '_attrs' => $attrs));
};
// A pick list: the first, empty option is the prompt, not an answer. Each
// answer's value is its own words, so the e-mails and the subject line read
// as the visitor chose. $selected is the answer chosen to begin with (no
// prompt then).
$select = function ($id, $name, $prompt, $options, $required = true, $selected = '', $cf = array()) use ($el, $attr) {
    $children = array();
    if ($selected === '') {
        $children[] = $el('option', '', lang('Option'), array(), array('text' => $prompt, '_attrs' => array($attr('value', ''))));
    }
    foreach ($options as $text) {
        $oattrs = array($attr('value', $text));
        if ($text === $selected) $oattrs[] = $attr('selected', '');
        $children[] = $el('option', '', lang('Option'), array(), array('text' => $text, '_attrs' => $oattrs));
    }
    $attrs = array($attr('name', $name));
    if ($required) $attrs[] = $attr('required', '');
    $props = array('id' => $id, '_attrs' => $attrs);
    if ($cf) $props['_cf'] = $cf;
    return $el('select', 'form-select', lang('Field'), $children, $props);
};
// A group of radio buttons under its legend: the legend names the field.
$radios = function ($id, $name, $legend, $options, $checked) use ($el, $attr) {
    $children = array($el('legend', 'form-label fs-6 mb-2', lang('Label'), array(), array('text' => $legend)));
    foreach (array_values($options) as $n => $o) {
        $rid = $id . '-' . ($n + 1);
        $rattrs = array($attr('type', 'radio'), $attr('name', $name), $attr('value', $o[0]));
        if ($o[0] === $checked) $rattrs[] = $attr('checked', '');
        $children[] = $el('div', 'form-check form-check-inline', lang('Option'), array(
            $el('input', 'form-check-input', lang('Field'), array(), array('id' => $rid, '_attrs' => $rattrs)),
            $el('label', 'form-check-label', lang('Label'), array(), array('text' => $o[1], '_attrs' => array($attr('for', $rid)))),
        ));
    }
    return $el('fieldset', 'mb-3', $legend, $children);
};
$submit = function ($text, $class = 'btn btn-primary px-4') use ($el, $attr) {
    return $el('button', $class, lang('Send Button'), array(), array('text' => $text, '_attrs' => array($attr('type', 'submit'))));
};
$captcha = function () use ($el) {
    return $el('div', 'mb-3', lang('CAPTCHA'), array(), array('_bindings' => array('section' => 'captcha')));
};
// A box the visitor must tick: what was agreed to is stored with the form.
$agree = function ($id, $name, $text) use ($el, $attr) {
    return $el('div', 'form-check small mb-3', lang('Consent'), array(
        $el('input', 'form-check-input', lang('Field'), array(), array('id' => $id, '_attrs' => array(
            $attr('type', 'checkbox'), $attr('name', $name), $attr('value', 'yes'), $attr('required', ''),
        ))),
        $el('label', 'form-check-label', lang('Label'), array(), array('text' => $text, '_attrs' => array($attr('for', $id)))),
    ));
};
// The newsletter's e-mail field and button, in one line. The address is the
// contact's, so the sign-up becomes a contact of the newsletter's group.
$newsletter_line = function ($id) use ($el, $attr, $submit) {
    return $el('div', 'input-group mb-3', lang('E-mail and Button'), array(
        $el('label', 'visually-hidden', lang('Label'), array(), array('text' => lang('Email'), '_attrs' => array($attr('for', $id)))),
        $el('input', 'form-control', lang('Field'), array(), array('id' => $id, '_cf' => array('contact_field' => 'email_address'), '_attrs' => array(
            $attr('type', 'email'), $attr('name', 'email'), $attr('required', ''), $attr('autocomplete', 'email'),
            $attr('placeholder', lang('Your e-mail address')),
        ))),
        $submit(lang('Subscribe')),
    ));
};
$newsletter_consent = function ($id) use ($agree) {
    return $agree($id, 'consent', lang('I would like to receive the practice\'s health letter by e-mail. I can unsubscribe at any time.'));
};

// A file field; what is sent is kept in the template folder named.
$file = function ($id, $name, $folder, $accept = 'image/*') use ($el, $attr) {
    return $el('input', 'form-control', '', array(), array('id' => $id, '_attrs' => array(
        $attr('type', 'file'), $attr('name', $name), $attr('accept', $accept),
    ), '_cf' => array('upload_folder_id' => '{{folder:' . $folder . '}}')));
};

// The numbered heading of a part of the appointment form.
$form_step = function ($n, $title) use ($el, $span, $heading) {
    return $el('div', 'd-flex align-items-center gap-2 mt-2 mb-3', lang('Step'), array(
        $span((string)$n, 'badge rounded-circle text-bg-primary', lang('Step Number')),
        $heading('h2', $title, 'h6 fw-bold mb-0'),
    ));
};

// ── Blog, inbox and staff parts ─────────────────────────────────────────

// A picture that leaves with its row when the record has none (the
// pg-hide-if-empty row of the form widgets).
$optional_image = function ($token, $alt_token, $class, $frame, $sample, $alt) use ($el, $image) {
    return $el('div', 'pg-hide-if-empty', lang('Picture'), array(
        $el('span', 'pg-field-value d-block ' . $frame, lang('Picture Frame'), array(
            $image($sample, $alt, $class, array('_bindings' => array('src' => $token, 'alt' => $alt_token))),
        )),
    ));
};

// A blog post card of the lists (the blog page and the home page).
$post_card = function () use ($el, $para, $span, $link, $col, $optional_image, $bind) {
    return $col(array(
        $el('article', 'card h-100 border-0 shadow-sm overflow-hidden', lang('Post Card'), array(
            $optional_image('cover_image', 'title', 'card-img-top object-fit-cover', 'ratio ratio-16x9 bg-body-tertiary',
                'https://picsum.photos/seed/pinegrap-clinic-post/800/450', lang('Cover Image')),
            $el('div', 'card-body d-flex flex-column', lang('Card Body'), array(
                $para(lang('Date'), 'small text-uppercase fw-semibold text-primary mb-2', $bind('submitted_date_and_time')),
                $el('h3', 'h5 card-title fw-bold position-static', lang('Post Title'), array(
                    $link(lang('Five habits for a healthy heart'), '#', 'stretched-link link-body-emphasis text-decoration-none', array(),
                        array('_bindings' => array('text' => 'title', 'href' => 'form_item_view'))),
                )),
                $para(lang('A short summary of the post. It is what makes a visitor open it, so two sentences are enough.'),
                    'card-text small text-body-secondary', $bind('summary')),
                $span(lang('Read More'), 'mt-auto small fw-semibold text-primary', lang('Read More')),
            )),
        )),
    ), '6', '4');
};

// The record's Edit button, shown to whoever may edit the submissions of
// its form; it opens the record in the control panel and comes back.
$edit_button = function ($text = '') use ($link) {
    return $link($text !== '' ? $text : lang('Edit'), '#', 'btn btn-sm btn-outline-secondary', array(),
        array('_bindings' => array('href' => '__edit_url', 'eo_visible_if' => 'can_edit')));
};

// Summarize the post with AI: with the visitor's own assistant (each link
// opens it with a question about the post's address) or with the browser's
// built-in model, whose button the browser shows only where it can
// summarize and whose points come into the box below. Shown for a post
// anybody may open.
$ai_summary = function () use ($el, $link, $icon, $span, $attr, $bind, $show_when) {
    $assistant = function ($text, $token) use ($link, $attr) {
        return $link($text, '#', 'btn btn-sm btn-outline-primary rounded-pill', array($attr('rel', 'noopener nofollow')),
            array('target' => '_blank', '_bindings' => array('href' => $token)));
    };
    return $el('aside', 'p-3 rounded-4 bg-body-tertiary mb-4', lang('AI Summary'), array(
        $el('div', 'd-flex flex-wrap align-items-center gap-2', lang('AI Buttons'), array(
            $icon('bi-stars', 'text-primary'),
            $span(lang('Summarize this post with AI'), 'small fw-semibold me-1', lang('Label')),
            $assistant('ChatGPT', '__ai_chatgpt_url'),
            $assistant('Claude', '__ai_claude_url'),
            $assistant('Perplexity', '__ai_perplexity_url'),
            $assistant('Google AI', '__ai_google_url'),
            $el('button', 'btn btn-sm btn-primary rounded-pill', lang('Summarize Here Button'), array(), array(
                'text' => lang('Summarize here'), '_bindings' => array('action' => 'ai_summarize'))),
        )),
        $el('div', 'small mt-3', lang('Summary Box'), array(), array_merge(
            array('text' => lang('The main points of the post, written by the visitor\'s browser.')), $bind('__ai_summary'))),
    ), $show_when('has_ai_links'));
};

// The comments of a record page (a post's comments, a conversation's
// replies, an appointment's messages), in the reading column.
$comments_section = function ($name) use ($el, $container, $row, $col) {
    return $el('section', 'pb-5', $name, array(
        $container(array(
            $row(array(
                $col(array(
                    array('type' => 'region', 'props' => array('regionType' => 'comments_block', 'regionName' => ''), 'children' => array()),
                ), '', '8'),
            ), '', 'center'),
        )),
    ));
};

// A question: a search section of its own, so a search that matches it
// opens the page at the question (the marking opens the answer).
$faq = function ($n, $question, $answer, $topics = '', $parent = 'cl-faq') use ($el, $para, $attr, $searchable) {
    $id = 'cl-faq-' . $n;
    return $el('div', 'accordion-item', lang('Question'), array(
        $el('h3', 'accordion-header', lang('Question Header'), array(
            $el('button', 'accordion-button collapsed', lang('Question'), array(), array(
                'text'   => $question,
                '_attrs' => array(
                    $attr('type', 'button'),
                    $attr('data-bs-toggle', 'collapse'),
                    $attr('data-bs-target', '#' . $id),
                    $attr('aria-expanded', 'false'),
                    $attr('aria-controls', $id),
                ),
            )),
        )),
        $el('div', 'accordion-collapse collapse', lang('Answer'), array(
            $el('div', 'accordion-body text-body-secondary', lang('Body'), array($para($answer, 'mb-0'))),
        ), array('id' => $id, '_attrs' => array($attr('data-bs-parent', '#' . $parent)))),
    ), $searchable($id . '-item', $topics));
};

// A numbered step of a short how-to.
$step = function ($n, $title, $text) use ($el, $heading, $para, $span) {
    return $el('li', 'd-flex gap-3 mb-4', lang('Step'), array(
        $span((string)$n, 'badge rounded-pill text-bg-primary fs-6 align-self-start', lang('Step Number')),
        $el('div', '', lang('Text'), array(
            $heading('h3', $title, 'h6 fw-bold mb-1'),
            $para($text, 'text-body-secondary mb-0'),
        )),
    ));
};

// A line of a check list: a tick and the words.
$check_item = function ($text, $class = 'mb-2') use ($el, $icon, $span) {
    return $el('li', 'd-flex gap-2 ' . $class, lang('List Item'), array(
        $icon('bi-check2-circle', 'text-primary'),
        $span($text, '', lang('Text')),
    ));
};
$check_list = function ($items, $class = 'list-unstyled mb-0') use ($el, $check_item) {
    $children = array();
    foreach (array_values($items) as $n => $text) $children[] = $check_item($text, ($n === count($items) - 1) ? 'mb-0' : 'mb-2');
    return $el('ul', $class, lang('Check List'), $children);
};

// ── Pages ───────────────────────────────────────────────────────────────

$pages = array();

// Home: the way to book, the services, the doctors, how booking works, what
// patients say and the newest posts.

// The four ways in under the hero.
$quick_action = function ($icon_name, $title, $text, $href) use ($el, $heading, $para, $icon_tile, $col) {
    return $col(array(
        $el('a', 'card h-100 border-0 shadow-sm text-decoration-none link-body-emphasis', $title, array(
            $el('div', 'card-body d-flex align-items-start gap-3 p-4', lang('Card Body'), array(
                $icon_tile($icon_name, 'flex-shrink-0'),
                $el('div', '', lang('Text'), array(
                    $heading('h2', $title, 'h6 fw-bold mb-1'),
                    $para($text, 'small text-body-secondary mb-0'),
                )),
            )),
        ), array('href' => $href)),
    ), '6', '3');
};

// A service card of the home page, linked to its section of the services
// page.
$service_cards = array();
foreach ($services as $key => $s) {
    $service_cards[] = $col(array(
        $el('a', 'card h-100 border-0 bg-body-tertiary text-decoration-none link-body-emphasis', $s['title'], array(
            $el('div', 'card-body p-4', lang('Card Body'), array(
                $icon_tile($s['icon'], 'mb-3'),
                $heading('h3', $s['title'], 'h5 fw-bold mb-2'),
                $para($s['text'], 'small text-body-secondary mb-3'),
                $el('span', 'd-inline-flex align-items-center gap-1 fw-semibold small text-primary', lang('Link Text'), array(
                    $span(lang('Learn more'), '', lang('Text')),
                    $icon('bi-arrow-right'),
                )),
            )),
        ), array('href' => '{{page:services}}#service-' . $key)),
    ), '6', '4');
}

// The doctors as small cards: the portrait, the name and the specialty.
$doctor_cards = array();
foreach ($doctors as $key => $d) {
    $doctor_cards[] = $col(array(
        $el('a', 'card h-100 border-0 shadow-sm text-center text-decoration-none link-body-emphasis', $d['name'], array(
            $el('div', 'card-body p-4', lang('Card Body'), array(
                $image($portrait($d['name'], 240), $d['name'], 'rounded-circle object-fit-cover mb-3', array('width' => '112', 'height' => '112', '_attrs' => array($attr('loading', 'lazy')))),
                $heading('h3', $d['name'], 'h6 fw-bold mb-1'),
                $para($d['specialty'], 'small text-primary fw-semibold mb-2'),
                $para($d['days'], 'small text-body-secondary mb-0'),
            )),
        ), array('href' => '{{page:doctors}}#doctor-' . $key)),
    ), '6', '3', '', '12');
}

// What patients say.
$testimonial = function ($quote, $who, $what) use ($el, $para, $stars, $col) {
    return $col(array(
        $el('figure', 'h-100 p-4 rounded-4 bg-body mb-0', lang('Testimonial'), array(
            $stars(),
            $el('blockquote', 'mb-3', lang('Quote'), array($para($quote, 'mb-0'))),
            $el('figcaption', 'small', lang('Author'), array(
                $para($who, 'fw-semibold mb-0'),
                $para($what, 'text-body-secondary mb-0'),
            )),
        )),
    ), '', '4');
};

// The numbers band.
$fact = function ($number, $text) use ($el, $para, $col) {
    return $col(array(
        $el('div', 'text-center', lang('Number'), array(
            $para($number, 'display-6 fw-bold mb-1'),
            $para($text, 'small opacity-75 mb-0'),
        )),
    ), '', '3', '', '6');
};

$pages[] = array(
    'key'              => 'home',
    'name'             => lang('home'),
    'folder'           => 'public',
    'title'            => lang('Home Page'),
    'meta_description' => lang('Family doctors, specialists and a laboratory close to home. Book an appointment online and follow it from your account.'),
    'tree'             => $page(array(
        // The way to book: the words and buttons on the left, a picture of
        // the practice with today's hours on the right.
        $el('section', 'py-5 bg-primary-subtle', lang('Hero'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $el('span', 'badge rounded-pill text-bg-light text-primary-emphasis d-inline-flex align-items-center gap-1 mb-3', lang('Badge'), array(
                            $icon('bi-patch-check'),
                            $span(lang('Accepting new patients'), '', lang('Text')),
                        )),
                        $heading('h1', lang('Care for the whole family, close to home'), 'display-5 fw-bold mb-3'),
                        $para(lang('Family doctors, specialists and a laboratory under one roof. Book online, see your doctor on the day you choose and follow everything from your account.'), 'lead text-body-secondary mb-4'),
                        $el('div', 'd-flex flex-wrap gap-2 mb-4', lang('Buttons'), array(
                            $link(lang('Book an Appointment'), '{{page:appointment}}', 'btn btn-primary btn-lg px-4'),
                            $link(lang('Our Services'), '{{page:services}}', 'btn btn-outline-primary btn-lg px-4'),
                        )),
                        $el('ul', 'list-unstyled d-flex flex-wrap column-gap-4 row-gap-2 small mb-0', lang('Benefits'), array(
                            $el('li', 'd-flex align-items-center gap-2', lang('List Item'), array($icon('bi-clock-history', 'text-primary'), $span(lang('Same-day appointments'), '', lang('Text')))),
                            $el('li', 'd-flex align-items-center gap-2', lang('List Item'), array($icon('bi-shield-check', 'text-primary'), $span(lang('Most insurers accepted'), '', lang('Text')))),
                            $el('li', 'd-flex align-items-center gap-2', lang('List Item'), array($icon('bi-calendar-week', 'text-primary'), $span(lang('Open on Saturdays'), '', lang('Text')))),
                        )),
                    ), '', '6'),
                    $col(array(
                        $el('div', 'position-relative', lang('Picture Area'), array(
                            $el('div', 'ratio ratio-4x3 rounded-4 overflow-hidden shadow', lang('Picture Frame'), array(
                                $image('https://picsum.photos/seed/pinegrap-clinic-hero/1200/900', lang('The reception of the practice'), 'object-fit-cover'),
                            )),
                            $el('div', 'card border-0 shadow position-absolute bottom-0 start-0 m-3', lang('Hours Card'), array(
                                $el('div', 'card-body py-3 px-4', lang('Card Body'), array(
                                    $el('p', 'd-flex align-items-center gap-2 fw-semibold mb-2', lang('Title'), array(
                                        $icon('bi-clock', 'text-primary'),
                                        $span(lang('Opening hours'), '', lang('Text')),
                                    )),
                                    $hours(),
                                )),
                            )),
                        )),
                    ), '', '6'),
                ), '4', '', 'g-lg-5 align-items-center'),
            )),
        )),

        // The four ways in.
        $el('section', 'py-5', lang('Quick Actions'), array(
            $container(array(
                $row(array(
                    $quick_action('bi-calendar2-check', lang('Book an Appointment'), lang('Choose the service, the doctor and a time of day.'), '{{page:appointment}}'),
                    $quick_action('bi-card-checklist', lang('My Appointments'), lang('Your requests, their status and our messages.'), '{{page:my_appointments}}'),
                    $quick_action('bi-chat-dots', lang('Ask a Question'), lang('Write to us; the answer comes to your inbox.'), '{{page:contact}}'),
                    $quick_action('bi-journal-medical', lang('Patient Guide'), lang('Your first visit, results, prescriptions and fees.'), '{{page:patient_guide}}'),
                ), '4'),
            )),
        )),

        // The services.
        $el('section', 'py-5 bg-body-tertiary', lang('Our Services'), array(
            $container(array(
                $section_header(lang('What we do'), lang('Our services'), lang('All services and fees'), '{{page:services}}'),
                $row($service_cards, '4'),
            )),
        )),

        // The numbers.
        $el('section', 'py-5 text-bg-primary', lang('Numbers'), array(
            $container(array(
                $row(array(
                    $fact('25+', lang('years caring for the neighbourhood')),
                    $fact('12', lang('doctors, nurses and lab staff')),
                    $fact('6', lang('services under one roof')),
                    $fact('4.9', lang('average rating from our patients')),
                ), '4'),
            )),
        )),

        // The doctors.
        $el('section', 'py-5', lang('Our Doctors'), array(
            $container(array(
                $section_header(lang('The team'), lang('Meet our doctors'), lang('All doctors'), '{{page:doctors}}'),
                $row($doctor_cards, '4'),
            )),
        )),

        // How booking works.
        $el('section', 'py-5 border-top', lang('How It Works'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $para(lang('Booking online'), 'small text-uppercase fw-semibold text-primary mb-1'),
                        $heading('h2', lang('Three steps to your appointment'), 'h3 fw-bold mb-3'),
                        $para(lang('No account needed to begin: the form opens one for you, so you can follow your appointment and write to us afterwards.'), 'text-body-secondary mb-4'),
                        $link(lang('Book an Appointment'), '{{page:appointment}}', 'btn btn-primary px-4'),
                    ), '', '5'),
                    $col(array(
                        $el('ol', 'list-unstyled mb-0', lang('List'), array(
                            $step(1, lang('Tell us what you need'), lang('Choose the service, a doctor if you have one in mind, the day and a time of day.')),
                            $step(2, lang('We confirm by e-mail'), lang('A receptionist checks the calendar and confirms your time, usually within two working hours.')),
                            $step(3, lang('Come in, or join by video'), lang('Bring your ID and any earlier results. Questions before the visit? Write under your appointment.')),
                        )),
                    ), '', '7'),
                ), '4', '', 'g-lg-5 align-items-center'),
            )),
        )),

        // What patients say.
        $el('section', 'py-5 bg-body-tertiary', lang('Testimonials'), array(
            $container(array(
                $section_header(lang('Our patients'), lang('What patients say')),
                $row(array(
                    $testimonial(lang('“Booked in the morning, seen in the afternoon. The doctor took the time to explain everything.”'), lang('Ayla K.'), lang('Family Medicine')),
                    $testimonial(lang('“Our daughter used to cry at every vaccination. Here she left with a sticker and a smile.”'), lang('Murat and Elif S.'), lang('Paediatrics')),
                    $testimonial(lang('“The heart check-up was done in one morning, and the results were in my account the same day.”'), lang('Hasan D.'), lang('Heart Check-up')),
                ), '4'),
            )),
        )),

        // The blog's three newest posts.
        $el('section', 'py-5', lang('From the Blog'), array(
            $container(array(
                $section_header(lang('Health notes'), lang('From our blog'), lang('All posts'), '{{page:blog}}'),
                $widget('home_posts'),
            )),
        )),

        $shared('booking_band'),
    )),
);

// Services: one section for each, with what it covers, how long a visit
// takes and what it costs; the fees in a table under them.
$service_sections = array();
$fee_rows = array();
$n = 0;
foreach ($services as $key => $s) {
    $service_sections[] = $el('section', 'py-5' . ($n % 2 ? ' bg-body-tertiary' : ''), $s['title'], array(
        $container(array(
            $row(array(
                $col(array(
                    $icon_tile($s['icon'], 'mb-3'),
                    $heading('h2', $s['title'], 'h3 fw-bold mb-2'),
                    $para($s['text'], 'lead text-body-secondary mb-4'),
                    $el('div', 'd-flex flex-wrap gap-3 small mb-4', lang('Meta'), array(
                        $el('span', 'd-inline-flex align-items-center gap-1', lang('Duration'), array($icon('bi-clock', 'text-primary'), $span($s['duration'], '', lang('Text')))),
                        $el('span', 'd-inline-flex align-items-center gap-1', lang('Price'), array($icon('bi-receipt', 'text-primary'), $span($s['price'], 'fw-semibold', lang('Text')))),
                    )),
                    $link(lang('Book this service'), '{{page:appointment}}', 'btn btn-primary px-4'),
                ), '', '6'),
                $col(array(
                    $el('div', 'p-4 rounded-4 border bg-body', lang('What It Covers'), array(
                        $heading('h3', lang('What it covers'), 'h6 fw-bold mb-3'),
                        $check_list($s['items']),
                    )),
                ), '', '6'),
            ), '4', '', 'g-lg-5 align-items-center'),
        )),
    ), $searchable('service-' . $key, $s['topics']));
    $fee_rows[] = $el('tr', '', lang('Row'), array(
        $el('td', '', lang('Service'), array(), array('text' => $s['title'])),
        $el('td', 'text-body-secondary', lang('Duration'), array(), array('text' => $s['duration'])),
        $el('td', 'text-end fw-semibold', lang('Price'), array(), array('text' => $s['price'])),
    ));
    $n++;
}
$pages[] = array(
    'key'              => 'services',
    'name'             => lang('services'),
    'folder'           => 'public',
    'title'            => lang('Our Services'),
    'meta_description' => lang('Family medicine, children\'s health, cardiology, dermatology, laboratory tests and vaccinations, with what each visit costs.'),
    'tree'             => $page(array_merge(
        array($page_header(lang('Our Services'), lang('From the family doctor to the specialist and the laboratory: what each service covers, how long it takes and what it costs.'))),
        $service_sections,
        array(
            $el('section', 'py-5 border-top', lang('Fees'), array(
                $container(array(
                    $row(array(
                        $col(array(
                            $para(lang('Fees'), 'small text-uppercase fw-semibold text-primary mb-1'),
                            $heading('h2', lang('Fees at a glance'), 'h3 fw-bold mb-3'),
                            $para(lang('The fee of a first visit. With a private insurer we send the bill to them; ask at reception what your policy covers.'), 'text-body-secondary mb-0'),
                        ), '', '4'),
                        $col(array(
                            $el('div', 'table-responsive', lang('Table Frame'), array(
                                $el('table', 'table align-middle mb-0', lang('Fees Table'), array(
                                    $el('thead', '', lang('Table Head'), array(
                                        $el('tr', '', lang('Row'), array(
                                            $el('th', '', lang('Column'), array(), array('text' => lang('Specialty'), '_attrs' => array($attr('scope', 'col')))),
                                            $el('th', '', lang('Column'), array(), array('text' => lang('Visit length'), '_attrs' => array($attr('scope', 'col')))),
                                            $el('th', 'text-end', lang('Column'), array(), array('text' => lang('Fee'), '_attrs' => array($attr('scope', 'col')))),
                                        )),
                                    )),
                                    $el('tbody', '', lang('Rows'), $fee_rows),
                                )),
                            )),
                        ), '', '8'),
                    ), '4', '', 'g-lg-5'),
                )),
            )),
            $shared('booking_band'),
        )
    )),
);

// Doctors: a profile for each, with the days they see patients and the way
// to book with them.
$doctor_profiles = array();
foreach ($doctors as $key => $d) {
    $doctor_profiles[] = $col(array(
        $el('article', 'card h-100 border-0 shadow-sm overflow-hidden', $d['name'], array(
            $el('div', 'row g-0 h-100', lang('Card Row'), array(
                $el('div', 'col-sm-5', lang('Portrait'), array(
                    $el('div', 'ratio ratio-1x1 h-100 bg-primary-subtle', lang('Picture Frame'), array(
                        $image($portrait($d['name']), $d['name'], 'object-fit-cover', array('_attrs' => array($attr('loading', 'lazy')))),
                    )),
                )),
                $el('div', 'col-sm-7', lang('Details'), array(
                    $el('div', 'card-body d-flex flex-column h-100 p-4', lang('Card Body'), array(
                        $span($d['specialty'], 'badge bg-primary-subtle text-primary-emphasis align-self-start mb-2', lang('Badge')),
                        $heading('h2', $d['name'], 'h5 fw-bold mb-2'),
                        $para($d['bio'], 'small text-body-secondary mb-3'),
                        $el('p', 'd-flex align-items-start gap-2 small mb-1', lang('Days'), array($icon('bi-calendar-week', 'text-primary'), $span($d['days'], '', lang('Text')))),
                        $el('p', 'd-flex align-items-start gap-2 small mb-3', lang('Languages'), array($icon('bi-translate', 'text-primary'), $span($d['languages'], '', lang('Text')))),
                        $el('a', 'btn btn-sm btn-outline-primary align-self-start mt-auto', lang('Link'), array(
                            $span(lang('Book an Appointment'), '', lang('Link Text')),
                            $span($d['name'], 'visually-hidden', lang('Name')),
                        ), array('href' => '{{page:appointment}}')),
                    )),
                )),
            )),
        ), $searchable('doctor-' . $key, $d['specialty'])),
    ), '', '6');
}
$pages[] = array(
    'key'              => 'doctors',
    'name'             => lang('doctors'),
    'folder'           => 'public',
    'title'            => lang('Our Doctors'),
    'meta_description' => lang('The family doctors and specialists of the practice, the days they see patients and the languages they speak.'),
    'tree'             => $page(array(
        $page_header(lang('Our Doctors'), lang('Family doctors and specialists who know their patients by name. Choose one, or let us find the first free time.')),
        $el('section', 'py-5', lang('Doctors'), array(
            $container(array(
                $row($doctor_profiles, '4'),
                $el('div', 'alert alert-info d-flex gap-2 mt-4 mb-0', lang('Note'), array(
                    $icon('bi-info-circle', 'mt-1'),
                    $span(lang('No preference? Choose "No preference" on the appointment form and we book you with the first doctor free for the service.'), '', lang('Text')),
                )),
            )),
        )),
        $shared('booking_band'),
    )),
);

// Check-ups: the three packages, how to prepare and the common questions.
$package_cards = array();
foreach ($packages as $p) {
    $featured = !empty($p['featured']);
    $package_cards[] = $col(array(
        $el('div', 'card h-100 ' . ($featured ? 'border-primary border-2 shadow' : 'border-0 shadow-sm'), $p['title'], array_merge(
            $featured ? array($el('div', 'card-header text-bg-primary text-center small fw-semibold', lang('Badge'), array(), array('text' => lang('Most chosen')))) : array(),
            array($el('div', 'card-body d-flex flex-column p-4', lang('Card Body'), array(
                $heading('h2', $p['title'], 'h5 fw-bold mb-2'),
                $para($p['text'], 'small text-body-secondary mb-3'),
                $para($p['price'], 'display-6 fw-bold mb-3'),
                $check_list($p['items'], 'list-unstyled small mb-4'),
                $link(lang('Book this check-up'), '{{page:appointment}}', 'btn ' . ($featured ? 'btn-primary' : 'btn-outline-primary') . ' mt-auto'),
            )))
        )),
    ), '', '4');
}
$pages[] = array(
    'key'              => 'checkups',
    'name'             => lang('check-ups'),
    'folder'           => 'public',
    'title'            => lang('Check-ups'),
    'meta_description' => lang('Three check-up packages, from the yearly basics to the complete picture in one morning, with the results explained.'),
    'tree'             => $page(array(
        $page_header(lang('Check-ups'), lang('A morning at the practice, the tests in one visit and your results explained by a doctor.')),
        $el('section', 'py-5', lang('Packages'), array(
            $container(array(
                $row($package_cards, '4', '', 'align-items-stretch'),
                $para(lang('Prices for a visit without insurance. Many insurers cover a yearly check-up; ask us before you book.'), 'small text-body-secondary text-center mt-4 mb-0'),
            )),
        )),
        $el('section', 'py-5 bg-body-tertiary', lang('Before Your Check-up'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $para(lang('Preparing'), 'small text-uppercase fw-semibold text-primary mb-1'),
                        $heading('h2', lang('Before your check-up'), 'h3 fw-bold mb-4'),
                        $el('ol', 'list-unstyled mb-0', lang('List'), array(
                            $step(1, lang('Fast from midnight'), lang('Water is fine; skip breakfast, coffee and cigarettes until the blood is taken.')),
                            $step(2, lang('Bring your medicines'), lang('A list of what you take, or the boxes themselves, and any earlier results.')),
                            $step(3, lang('Plan for a morning'), lang('Most check-ups take two to three hours; breakfast is served after the blood tests.')),
                        )),
                    ), '', '6'),
                    $col(array(
                        $el('div', 'accordion', lang('FAQ'), array(
                            $faq(1, lang('When are the results ready?'), lang('Most results are in your account the same day; your doctor goes through them with you within the week.'), lang('results, test results, when, ready')),
                            $faq(2, lang('Does my insurance pay for a check-up?'), lang('Many private insurers pay for one check-up a year. Tell us your insurer when you book and we check it for you.'), lang('insurance, cover, pay, policy')),
                            $faq(3, lang('Can I add a test to a package?'), lang('Yes. Write it in the reason for the visit, or ask your doctor on the day.'), lang('extra test, add, package')),
                        ), array('id' => 'cl-faq')),
                    ), '', '6'),
                ), '4', '', 'g-lg-5'),
            )),
        )),
        $shared('booking_band'),
    )),
);

// The appointment: the request form, with how it works and what to bring
// beside it. What is sent opens the request's own page.
$pages[] = array(
    'key'              => 'appointment',
    'name'             => lang('book-an-appointment'),
    'folder'           => 'public',
    'title'            => lang('Book an Appointment'),
    'meta_description' => lang('Request an appointment online: choose the service, the doctor and a time of day; we confirm by e-mail.'),
    'form'             => array(
        'form_name'            => lang('Appointments'),
        'confirmation_message' => lang('Thank you, your request has reached us. We will confirm your appointment by e-mail.'),
        // What is sent opens the request's own page, where the patient sees
        // its status and both sides write as comments. A visitor without an
        // account gets one (auto-registration), which that page tells them.
        'confirmation_page_id' => '{{tab:appointment_view}}',
        'auto_registration'    => 1,
        'notify_email'         => '{{site_email}}',
        'notify_subject'       => lang('New appointment request: ^^department^^, ^^preferred_date^^'),
        'notify_page_id'       => '{{tab:email_appointment_new}}',
        'confirm_email'        => 1,
        'confirm_subject'      => lang('We received your appointment request'),
        'confirm_page_id'      => '{{tab:email_appointment_received}}',
    ),
    'tree'             => $page(array(
        $page_header(lang('Book an Appointment'), lang('Tell us what you need and when suits you. We confirm your time by e-mail, usually within two working hours.')),
        $el('section', 'py-5', lang('Content'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $el('div', 'card border-0 shadow-sm', lang('Form Card'), array(
                            $el('div', 'card-body p-4 p-lg-5', lang('Card Body'), array($widget('appointment_form'))),
                        )),
                    ), '', '8'),
                    $col(array(
                        $el('div', 'alert alert-danger d-flex gap-2', lang('Emergency Note'), array(
                            $icon('bi-exclamation-triangle', 'mt-1'),
                            $span(lang('Chest pain, trouble breathing or heavy bleeding? Do not wait for an appointment: call 112.'), '', lang('Text')),
                        ), array('_attrs' => array($attr('role', 'note')))),
                        $el('div', 'p-4 rounded-4 bg-body-tertiary mb-4', lang('How It Works'), array(
                            $heading('h2', lang('What happens next'), 'h5 fw-bold mb-3'),
                            $el('ol', 'small text-body-secondary ps-3 mb-0', lang('List'), array(
                                $el('li', 'mb-2', lang('Step'), array(), array('text' => lang('Your request opens its own page, and an account for you if you have none.'))),
                                $el('li', 'mb-2', lang('Step'), array(), array('text' => lang('We confirm the time by e-mail; the page shows the status too.'))),
                                $el('li', '', lang('Step'), array(), array('text' => lang('Need to change something? Write under your appointment.'))),
                            )),
                        )),
                        $el('div', 'p-4 rounded-4 border mb-4', lang('What to Bring'), array(
                            $heading('h2', lang('What to bring'), 'h5 fw-bold mb-3'),
                            $check_list(array(lang('Your ID card'), lang('Your insurance card'), lang('A list of your medicines'), lang('Earlier results and reports')), 'list-unstyled small mb-0'),
                        )),
                        $el('div', 'p-4 rounded-4 border', lang('Opening hours'), array(
                            $heading('h2', lang('Opening hours'), 'h5 fw-bold mb-3'),
                            $hours(),
                        )),
                    ), '', '4'),
                ), '4', '', 'g-lg-5'),
            )),
        )),
    ), false),
);

// One appointment: the request, its status and the messages under it. The
// form's confirmation page; My Appointments and the e-mails link to it.
$pages[] = array(
    'key'      => 'appointment_view',
    'name'     => lang('appointment'),
    'folder'   => 'registration',
    'title'    => lang('Appointment'),
    'search'   => false,
    'sitemap'  => false,
    'comments' => array('label' => lang('Message'), 'allow_new' => true, 'auto_publish' => true, 'show_date' => true, 'login' => true,
                        'email_page' => '{{tab:email_reply}}', 'email_subject' => lang('There is a new message about your appointment'),
                        'notify_email' => '{{site_email}}'),
    'tree'     => $page(array(
        $widget('appointment_detail'),
        $comments_section(lang('Messages')),
    ), false),
);

// My Appointments: the patient's requests (for the staff, everybody's).
$pages[] = array(
    'key'     => 'my_appointments',
    'name'    => lang('my-appointments'),
    'folder'  => 'registration',
    'title'   => lang('My Appointments'),
    'search'  => false,
    'sitemap' => false,
    'tree'    => $page(array(
        $page_header(lang('My Appointments'), lang('Your appointment requests, their status and our messages, newest first.')),
        $el('section', 'py-5', lang('Content'), array(
            $container(array(
                $row(array(
                    $col(array($widget('my_appointments_list')), '', '8'),
                    $col(array(
                        $el('div', 'p-4 rounded-4 bg-body-tertiary mb-4', lang('Book Again'), array(
                            $el('p', 'd-flex align-items-center gap-2 fw-semibold mb-2', lang('Title'), array(
                                $icon('bi-calendar-plus', 'text-primary'),
                                $span(lang('Another appointment?'), '', lang('Text')),
                            )),
                            $para(lang('Book again in two minutes; the details you gave last time are filled in for you.'), 'small text-body-secondary mb-3'),
                            $link(lang('Book an Appointment'), '{{page:appointment}}', 'btn btn-sm btn-primary'),
                        )),
                        $el('div', 'p-4 rounded-4 border', lang('Change or Cancel'), array(
                            $heading('h2', lang('Need to change or cancel?'), 'h6 fw-bold mb-2'),
                            $para(lang('Open the appointment and write to us under it, or call us. Please let us know a day ahead, so someone else can have the time.'), 'small text-body-secondary mb-0'),
                        )),
                    ), '', '4'),
                ), '4', '', 'g-lg-5'),
            )),
        )),
    ), false),
);

// The patient guide: the first visit, insurance and fees, results,
// prescriptions and changing an appointment, each a search section, and the
// common questions.
$guide_topic = function ($id, $icon_name, $title, $paragraphs, $topics) use ($el, $heading, $para, $icon_tile, $searchable) {
    $children = array(
        $el('div', 'd-flex align-items-center gap-3 mb-3', lang('Title'), array(
            $icon_tile($icon_name),
            $heading('h2', $title, 'h4 fw-bold mb-0'),
        )),
    );
    foreach (array_values($paragraphs) as $n => $p) $children[] = $para($p, 'text-body-secondary' . ($n === count($paragraphs) - 1 ? ' mb-0' : ''));
    return $el('section', 'pb-5 mb-5 border-bottom', $title, $children, $searchable($id, $topics));
};
$pages[] = array(
    'key'              => 'patient_guide',
    'name'             => lang('patient-guide'),
    'folder'           => 'public',
    'title'            => lang('Patient Guide'),
    'meta_description' => lang('Your first visit, insurance and fees, test results, prescriptions and how to change an appointment.'),
    'tree'             => $page(array(
        $page_header(lang('Patient Guide'), lang('Everything you need to know before and after your visit, in one place.')),
        $el('div', 'py-5', lang('Content'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $el('nav', 'p-4 rounded-4 bg-body-tertiary', lang('Contents'), array(
                            $heading('h2', lang('In this guide'), 'h6 text-uppercase small fw-bold text-body-secondary mb-3'),
                            $el('div', 'list-group list-group-flush', lang('Topics'), array(
                                $link(lang('Your first visit'), '#guide-first-visit', 'list-group-item list-group-item-action'),
                                $link(lang('Insurance and payment'), '#guide-insurance', 'list-group-item list-group-item-action'),
                                $link(lang('Test results'), '#guide-results', 'list-group-item list-group-item-action'),
                                $link(lang('Prescriptions'), '#guide-prescriptions', 'list-group-item list-group-item-action'),
                                $link(lang('Changing an appointment'), '#guide-changes', 'list-group-item list-group-item-action'),
                                $link(lang('Your privacy'), '#guide-privacy', 'list-group-item list-group-item-action'),
                            )),
                        ), array('_attrs' => array($attr('aria-label', lang('In this guide'))))),
                    ), '', '3', '', '12', 'd-none d-lg-block'),
                    $col(array(
                        $guide_topic('guide-first-visit', 'bi-door-open', lang('Your first visit'), array(
                            lang('Come ten minutes early: reception opens your file with your ID card and, if you have one, your insurance card.'),
                            lang('Bring a list of the medicines you take and any results from other doctors. A child\'s vaccination card is welcome too.'),
                        ), lang('first visit, new patient, register, what to bring, ID')),
                        $guide_topic('guide-insurance', 'bi-shield-check', lang('Insurance and payment'), array(
                            lang('We work with most private insurers and send the bill to them directly; you pay only what your policy does not cover.'),
                            lang('Without insurance, the fees on the services page apply. You can pay by card or cash at reception.'),
                        ), lang('insurance, payment, fees, price, bill, card')),
                        $guide_topic('guide-results', 'bi-clipboard2-pulse', lang('Test results'), array(
                            lang('Most laboratory results are ready the same day. We write to you under your appointment when they are in.'),
                            lang('Your doctor explains every result; if something needs a closer look, we call you ourselves.'),
                        ), lang('results, lab results, blood test, report')),
                        $guide_topic('guide-prescriptions', 'bi-capsule', lang('Prescriptions'), array(
                            lang('Your doctor writes the prescription during the visit; it reaches the pharmacy electronically.'),
                            lang('For a repeat prescription of a medicine you take long-term, write to us from the contact page; there is no need to come in every time.'),
                        ), lang('prescription, repeat prescription, medicine, pharmacy')),
                        $guide_topic('guide-changes', 'bi-calendar2-x', lang('Changing an appointment'), array(
                            lang('Open the appointment from My Appointments and write to us under it, or call reception.'),
                            lang('Please tell us at least a day ahead; the time then goes to someone who is waiting for one.'),
                        ), lang('cancel, change, reschedule, move appointment')),
                        $guide_topic('guide-privacy', 'bi-lock', lang('Your privacy'), array(
                            lang('Your details and your health information are seen only by the people who care for you, and kept as the law requires.'),
                            lang('You can ask for a copy of your file, or for something in it to be corrected, at any time.'),
                        ), lang('privacy, data, medical records, personal data, GDPR')),
                        $heading('h2', lang('Frequently asked questions'), 'h4 fw-bold mb-3'),
                        $el('div', 'accordion', lang('FAQ'), array(
                            $faq(11, lang('Can I see a doctor today?'), lang('Usually, yes. Book online and choose today, or call us in the morning; we keep times free each day for patients who need them.'), lang('same day, today, urgent, walk-in'), 'cl-guide-faq'),
                            $faq(12, lang('Do you offer video consultations?'), lang('Yes, for follow-ups, results and repeat prescriptions. Choose "Video call" on the appointment form.'), lang('online, video, telemedicine, remote'), 'cl-guide-faq'),
                            $faq(13, lang('Can I bring my child to my appointment?'), lang('Of course. If your child needs to be seen as well, book a separate appointment so the doctor has time for both of you.'), lang('child, children, family'), 'cl-guide-faq'),
                            $faq(14, lang('Where do I find your answer to my message?'), lang('In your account: your appointments and your messages each have a page, and every answer appears there and comes by e-mail.'), lang('inbox, reply, answer, message'), 'cl-guide-faq'),
                        ), array('id' => 'cl-guide-faq')),
                    ), '', '9'),
                ), '4', '', 'g-lg-5'),
            )),
        )),
        $shared('booking_band'),
    )),
);

// About: the practice's story, what it stands for, and the place.
$value = function ($icon_name, $title, $text) use ($el, $icon_tile, $heading, $para, $col) {
    return $col(array(
        $el('div', 'h-100 p-4 rounded-4 border', lang('Feature'), array(
            $icon_tile($icon_name, 'mb-3'),
            $heading('h3', $title, 'h5 fw-bold mb-2'),
            $para($text, 'text-body-secondary mb-0'),
        )),
    ), '', '4');
};
$pages[] = array(
    'key'              => 'about',
    'name'             => lang('about'),
    'folder'           => 'public',
    'title'            => lang('About Us'),
    'meta_description' => lang('A neighbourhood practice for the whole family since 2001: family doctors, specialists and a laboratory under one roof.'),
    'tree'             => $page(array(
        $page_header(lang('About Us'), lang('A neighbourhood practice for the whole family, with the time to listen.')),
        $el('section', 'py-5', lang('Our Story'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $para(lang('Our story'), 'small text-uppercase fw-semibold text-primary mb-1'),
                        $heading('h2', lang('Two doctors, one waiting room, and a promise to listen'), 'h3 fw-bold mb-3'),
                        $para(lang('The practice opened in 2001 with two family doctors and a waiting room of six chairs. Today twelve of us look after the families of the neighbourhood, often three generations of them.'), 'text-body-secondary'),
                        $para(lang('We have grown, but the promise has not changed: enough time in every visit to listen, explain and decide together.'), 'text-body-secondary mb-0'),
                    ), '', '6'),
                    $col(array(
                        $el('div', 'ratio ratio-4x3 rounded-4 overflow-hidden bg-body-tertiary', lang('Picture Frame'), array(
                            $image('https://picsum.photos/seed/pinegrap-clinic-practice/1200/900', lang('The waiting room of the practice'), 'object-fit-cover'),
                        )),
                    ), '', '6'),
                ), '4', '', 'g-lg-5 align-items-center'),
            )),
        )),
        $el('section', 'pb-5', lang('Features'), array(
            $container(array(
                $row(array(
                    $value('bi-ear', lang('Time to listen'), lang('Twenty minutes for every visit, and longer when it is needed.')),
                    $value('bi-people', lang('One team'), lang('Your family doctor, the specialists and the laboratory share one file, so nobody asks twice.')),
                    $value('bi-award', lang('Care you can check'), lang('Accredited laboratory, trained staff and the guidelines doctors trust.')),
                ), '4'),
            )),
        )),
        $el('section', 'py-5 border-top', lang('Our Team'), array(
            $container(array(
                $section_header(lang('The team'), lang('Our doctors'), lang('All doctors'), '{{page:doctors}}'),
                $row($doctor_cards, '4'),
            )),
        )),
        $shared('booking_band'),
    )),
);

// Contact: a message to the practice; the staff are told, the sender gets
// a reply. Repeat prescriptions are asked for here too.
$pages[] = array(
    'key'              => 'contact',
    'name'             => lang('contact-us'),
    'folder'           => 'public',
    'title'            => lang('Contact us'),
    'meta_description' => lang('A question about an appointment, a result or a prescription? Write to us or call the practice.'),
    'form'             => array(
        'form_name'            => lang('Contact Form'),
        'confirmation_message' => lang('Thank you, your message has reached us. We will get back to you soon.'),
        // What is sent opens the conversation page: its first message is
        // this one, and the answers follow as comments. A visitor without an
        // account gets one (auto-registration), which that page tells them.
        'confirmation_page_id' => '{{tab:conversation}}',
        'auto_registration'    => 1,
        'notify_email'         => '{{site_email}}',
        'notify_subject'       => lang('New message: ^^topic^^'),
        'notify_page_id'       => '{{tab:email_new_message}}',
        'confirm_email'        => 1,
        'confirm_subject'      => lang('We received your message'),
        'confirm_page_id'      => '{{tab:email_message_received}}',
    ),
    'tree'             => $page(array(
        $page_header(lang('Contact us'), lang('A question about an appointment, a result or a prescription: write to us, and the answer comes to your inbox.')),
        $el('section', 'py-5', lang('Content'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $el('div', 'alert alert-warning d-flex gap-2 mb-4', lang('Note'), array(
                            $icon('bi-info-circle', 'mt-1'),
                            $span(lang('Messages are read in opening hours. For anything urgent, call us; in an emergency, call 112.'), '', lang('Text')),
                        )),
                        $widget('contact_form'),
                    ), '', '7'),
                    $col(array(
                        $el('div', 'p-4 rounded-4 bg-body-tertiary', lang('Contact Details'), array(
                            $heading('h2', lang('Visit the practice'), 'h5 fw-bold mb-3'),
                            $el('p', 'd-flex align-items-start gap-2 mb-2', lang('Address'), array(
                                $icon('bi-geo-alt', 'text-primary'),
                                $span(lang('Sample Street 1, 34000 Istanbul, Türkiye'), '', lang('Text')),
                            )),
                            $el('p', 'd-flex align-items-center gap-2 mb-2', lang('Phone'), array(
                                $icon('bi-telephone', 'text-primary'),
                                $link('+90 555 000 00 00', 'tel:+905550000000', 'link-body-emphasis text-decoration-none'),
                            )),
                            $el('p', 'd-flex align-items-center gap-2 mb-2', lang('Email'), array(
                                $icon('bi-envelope', 'text-primary'),
                                $link('hello@example.com', 'mailto:hello@example.com', 'link-body-emphasis text-decoration-none'),
                            )),
                            $el('p', 'd-flex align-items-start gap-2 mb-4', lang('Getting Here'), array(
                                $icon('bi-p-circle', 'text-primary'),
                                $span(lang('Free parking behind the building; the bus stop is two minutes away.'), '', lang('Text')),
                            )),
                            $heading('h3', lang('Opening hours'), 'h6 fw-bold mb-2'),
                            $hours(),
                        )),
                        $el('div', 'p-4 rounded-4 border mt-4', lang('Inbox Note'), array(
                            $el('p', 'd-flex align-items-center gap-2 fw-semibold mb-2', lang('Title'), array(
                                $icon('bi-inbox', 'text-primary'),
                                $span(lang('Follow the answer in your inbox'), '', lang('Text')),
                            )),
                            $para(lang('Your message becomes a conversation: the page that opens after you send it, and your inbox, show our answers, and you can write back there. If you have no account yet, one is opened for you.'), 'small text-body-secondary mb-3'),
                            $link(lang('Open my inbox'), '{{page:inbox}}', 'btn btn-sm btn-outline-primary'),
                        )),
                    ), '', '5'),
                ), '4', '', 'g-lg-5'),
            )),
        )),
    )),
);

// The newsletter: its page owns the form. What is sent adds the address to
// the newsletter's contact group, and the subscriber gets the welcome
// e-mail.
$pages[] = array(
    'key'              => 'newsletter',
    'name'             => lang('newsletter'),
    'folder'           => 'public',
    'title'            => lang('Newsletter'),
    'meta_description' => lang('Seasonal health advice, vaccination reminders and news from the practice, once a month.'),
    'form'             => array(
        'form_name'            => lang('Newsletter'),
        'confirmation_message' => lang('Thank you! You are now subscribed to our newsletter.'),
        'contact_group_id'     => '{{contact_group:newsletter}}',
        'confirm_email'        => 1,
        'confirm_subject'      => lang('Welcome to the health letter'),
        'confirm_page_id'      => '{{tab:email_welcome}}',
    ),
    'tree'             => $page(array(
        $page_header(lang('Newsletter'), lang('Seasonal health advice, vaccination reminders and news from the practice, once a month.')),
        $el('section', 'py-5', lang('Content'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $el('div', 'p-4 p-lg-5 rounded-4 border border-2', lang('Form Card'), array($widget('newsletter_form'))),
                    ), '', '7'),
                    $col(array(
                        $el('div', 'p-4 rounded-4 bg-body-tertiary', lang('What You Get'), array(
                            $heading('h2', lang('In every letter'), 'h5 fw-bold mb-3'),
                            $check_list(array(
                                lang('Advice for the season: flu, allergies, the summer sun'),
                                lang('A reminder when a vaccination is due'),
                                lang('One e-mail a month, never your address to anyone else'),
                            )),
                        )),
                    ), '', '5'),
                ), '4', '', 'g-lg-5'),
            )),
        )),
    ), false),
);

// Blog: every post, newest first, with a search.
$pages[] = array(
    'key'              => 'blog',
    'name'             => lang('blog'),
    'folder'           => 'public',
    'title'            => lang('Blog'),
    'meta_description' => lang('Health notes from our doctors: prevention, children\'s health, the heart and the skin.'),
    'tree'             => $page(array(
        $page_header(lang('Blog'), lang('Health notes from our doctors: prevention, children\'s health, the heart and the skin.')),
        $el('section', 'py-5', lang('Posts'), array(
            $container(array($widget('blog_list'))),
        )),
    )),
);

// A blog post: the post the list linked to (?r=), and its comments.
$pages[] = array(
    'key'              => 'blog_post',
    'name'             => lang('blog-post'),
    'folder'           => 'public',
    'title'            => lang('Blog Post'),
    'meta_description' => lang('A post from our blog.'),
    'sitemap'          => false,
    'comments'         => array('label' => lang('Comment'), 'allow_new' => true, 'auto_publish' => true, 'show_date' => true, 'login' => true,
                                'email_page' => '{{tab:email_reply}}', 'email_subject' => lang('New comment on the blog'),
                                'notify_email' => '{{site_email}}'),
    'tree'             => $page(array(
        $widget('blog_post'),
        $comments_section(lang('Comments')),
    )),
);

// Signing in and out.
$sign_page = function ($key, $name, $title, $widget_key) use ($page, $widget) {
    return array(
        'key'     => $key,
        'name'    => $name,
        'folder'  => 'public',
        'title'   => $title,
        'search'  => false,
        'sitemap' => false,
        'tree'    => $page(array($widget($widget_key)), false),
    );
};
$pages[] = $sign_page('login', lang('login'), lang('Log In'), 'login');
$pages[] = $sign_page('register', lang('register'), lang('Sign Up'), 'register');
$pages[] = $sign_page('forgot_password', lang('forgot-password'), lang('Forgot Password'), 'forgot_password');
$pages[] = $sign_page('set_password', lang('set-password'), lang('Set Password'), 'set_password');
$pages[] = $sign_page('logout', lang('logout'), lang('Logout'), 'logout');

// My account: the patient's own pages, behind the registration folder.
$pages[] = array(
    'key'     => 'my_account',
    'name'    => lang('my-account'),
    'folder'  => 'registration',
    'title'   => lang('My Account'),
    'search'  => false,
    'sitemap' => false,
    'tree'    => $page(array($widget('my_account'), $widget('account_invoices'), $widget('account_security')), false),
);
$pages[] = array_merge($sign_page('profile', lang('my-profile'), lang('My Profile'), 'profile'), array('folder' => 'registration'));
$pages[] = array_merge($sign_page('change_password', lang('change-password'), lang('Change Password'), 'change_password'), array('folder' => 'registration'));
$pages[] = array_merge($sign_page('email_preferences', lang('email-preferences'), lang('Email Preferences'), 'email_preferences'), array('folder' => 'registration'));
$pages[] = array_merge($sign_page('address_book', lang('address-book'), lang('Address Book'), 'address_book'), array('folder' => 'registration', 'requires' => 'ecommerce'));

// The inbox: the patient's conversations (for the staff, everybody's), and
// the conversation page, where both sides answer as comments.
$pages[] = array(
    'key'     => 'inbox',
    'name'    => lang('inbox'),
    'folder'  => 'registration',
    'title'   => lang('Inbox'),
    'search'  => false,
    'sitemap' => false,
    'tree'    => $page(array(
        $page_header(lang('Inbox'), lang('The messages you sent us from the contact page, and our answers.')),
        $el('section', 'py-5', lang('Content'), array(
            $container(array(
                $row(array(
                    $col(array($widget('inbox_list')), '', '8'),
                    $col(array(
                        $el('div', 'p-4 rounded-4 bg-body-tertiary mb-4', lang('Inbox Note'), array(
                            $el('p', 'd-flex align-items-center gap-2 fw-semibold mb-2', lang('Title'), array(
                                $icon('bi-chat-left-text', 'text-primary'),
                                $span(lang('A question for your doctor?'), '', lang('Text')),
                            )),
                            $para(lang('Write to us from the contact page; your message and our answers stay here, in one conversation.'), 'small text-body-secondary mb-3'),
                            $link(lang('New message'), '{{page:contact}}', 'btn btn-sm btn-primary'),
                        )),
                        $el('div', 'p-4 rounded-4 border', lang('Appointments Note'), array(
                            $para(lang('Messages about an appointment are written under the appointment itself.'), 'small text-body-secondary mb-3'),
                            $link(lang('My Appointments'), '{{page:my_appointments}}', 'btn btn-sm btn-outline-primary'),
                        )),
                    ), '', '4'),
                ), '4', '', 'g-lg-5'),
            )),
        )),
    ), false),
);
$pages[] = array(
    'key'      => 'conversation',
    'name'     => lang('conversation'),
    'folder'   => 'registration',
    'title'    => lang('Conversation'),
    'search'   => false,
    'sitemap'  => false,
    'comments' => array('label' => lang('Reply'), 'allow_new' => true, 'auto_publish' => true, 'show_date' => true, 'login' => true,
                        'email_page' => '{{tab:email_reply}}', 'email_subject' => lang('There is a new reply to your conversation'),
                        'notify_email' => '{{site_email}}'),
    'tree'     => $page(array(
        $widget('conversation'),
        $comments_section(lang('Replies')),
    ), false),
);
$pages[] = array(
    'key'     => 'staff_directory',
    'name'    => lang('staff-directory'),
    'folder'  => 'registration',
    'title'   => lang('Staff Directory'),
    'search'  => false,
    'sitemap' => false,
    'tree'    => $page(array(
        $page_header(lang('Staff Directory'), lang('The doctors, nurses and staff of the practice, and how to reach them.')),
        $el('section', 'py-5', lang('Directory'), array(
            $container(array($widget('staff_directory'))),
        )),
    ), false),
);

// The staff area, behind the private folder: the coming appointments first.
$staff_shortcut = function ($icon_name, $title, $text, $href) use ($el, $heading, $para, $icon, $col) {
    return $col(array(
        $el('a', 'd-flex align-items-start gap-3 h-100 p-4 rounded-4 bg-body-tertiary text-decoration-none link-body-emphasis', $title, array(
            $icon($icon_name, 'fs-3 text-primary'),
            $el('div', '', lang('Text'), array(
                $heading('h3', $title, 'h6 fw-bold mb-1'),
                $para($text, 'small text-body-secondary mb-0'),
            )),
        ), array('href' => $href)),
    ), '6', '3');
};
$pages[] = array(
    'key'     => 'staff',
    'name'    => lang('staff'),
    'folder'  => 'private',
    'title'   => lang('Staff Area'),
    'search'  => false,
    'sitemap' => false,
    'noindex' => true,
    'tree'    => $staff_page(array(
        $el('section', 'py-5 bg-body-tertiary border-bottom', lang('Staff Welcome'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $span(lang('Staff only'), 'badge rounded-pill text-bg-primary mb-3', lang('Badge')),
                        $heading('h1', lang('Welcome to the staff area'), 'display-6 fw-bold mb-3'),
                        $para(lang('The coming appointments, the conversations with patients and the blog are kept here. To confirm or cancel an appointment, open it and press Change Status.'), 'lead text-body-secondary mb-4'),
                        $el('div', 'd-flex flex-wrap gap-2', lang('Buttons'), array(
                            $link(lang('Open the control panel'), $panel_url, 'btn btn-primary px-4'),
                            $link(lang('All Appointments'), '{{page:my_appointments}}', 'btn btn-outline-primary'),
                        )),
                    ), '', '8'),
                )),
            )),
        )),
        $el('section', 'py-5', lang('Coming Appointments'), array(
            $container(array(
                $section_header(lang('From today'), lang('Coming appointments'), lang('All Appointments'), '{{page:my_appointments}}'),
                $widget('staff_appointments'),
            )),
        )),
        $el('section', 'pb-5', lang('Shortcuts'), array(
            $container(array(
                $section_header(lang('Staff'), lang('Shortcuts')),
                $row(array(
                    $staff_shortcut('bi-calendar2-week', lang('All Appointments'), lang('Every request, newest first, with its status.'), '{{page:my_appointments}}'),
                    $staff_shortcut('bi-chat-left-text', lang('All Conversations'), lang('Every message from the contact page. Answer below each one.'), '{{page:inbox}}'),
                    $staff_shortcut('bi-journal-plus', lang('New Blog Post'), lang('Write a post; it appears on the blog as soon as you send it.'), '{{page:blog_new}}'),
                    $staff_shortcut('bi-person-plus', lang('Add to the Staff Directory'), lang('Add a colleague to the directory patients see.'), '{{page:staff_entry}}'),
                ), '4'),
                $el('div', 'alert alert-secondary d-flex gap-2 mt-4 mb-0', lang('Access Note'), array(
                    $icon('bi-info-circle', 'mt-1'),
                    $span(lang('Only the site staff and the users given access to the private folder see these pages. To let someone in, open their account under Users and tick the folder in Private Content Access.'), '', lang('Text')),
                )),
            )),
        )),
    )),
);

// The blog's first posts, written into its form when the form is made:
// the template carries them, so they are there on every site. Each is
// edited or deleted like any other post.
$post_body = function ($paragraphs) {
    return implode("\n\n", $paragraphs);
};
$sample_posts = array(
    array(
        'title'       => lang('Five habits for a healthy heart'),
        'summary'     => lang('Small changes that add up: what our cardiologist tells every patient after the first check-up.'),
        'content'     => $post_body(array(
            lang('Walk thirty minutes a day, five days a week. It does not need to be fast; it needs to be every day.'),
            lang('Fill half your plate with vegetables, choose olive oil over butter and keep salt for the table, not the pot.'),
            lang('Sleep seven hours, stop smoking and know your numbers: blood pressure, cholesterol and blood sugar. A yearly check-up is the easiest way to keep them in view.'),
        )),
        'cover_image' => 'https://picsum.photos/seed/pinegrap-clinic-post-1/1200/675',
    ),
    array(
        'title'       => lang('Is it a cold or the flu?'),
        'summary'     => lang('How to tell them apart, what helps at home and when it is time to see a doctor.'),
        'content'     => $post_body(array(
            lang('A cold comes slowly: a sore throat, a runny nose, perhaps a mild fever. The flu comes in a few hours, with a high fever, aching muscles and deep tiredness.'),
            lang('Both are viruses, so antibiotics do not help. Rest, drink plenty and take something for the fever if you need it.'),
            lang('See a doctor if the fever lasts more than three days, if breathing becomes hard, or if a baby, an older person or someone with a long-term illness is unwell.'),
        )),
        'cover_image' => 'https://picsum.photos/seed/pinegrap-clinic-post-2/1200/675',
    ),
    array(
        'title'       => lang('Your child\'s vaccination calendar'),
        'summary'     => lang('Which vaccines come when, from the first month to school age, and why the schedule matters.'),
        'content'     => $post_body(array(
            lang('The first vaccines are given in the first weeks of life, and the most important ones are finished before the second birthday.'),
            lang('Keeping to the schedule protects your child at the age when these illnesses are most dangerous. A dose that was missed can almost always be caught up.'),
            lang('Bring the vaccination card to every visit; we note each dose and send you a reminder when the next one is due.'),
        )),
        'cover_image' => 'https://picsum.photos/seed/pinegrap-clinic-post-3/1200/675',
    ),
    array(
        'title'       => lang('Sun and skin: a summer guide'),
        'summary'     => lang('Our dermatologist on sunscreen, the hours to avoid and the moles worth showing a doctor.'),
        'content'     => $post_body(array(
            lang('Use a sunscreen of factor 30 or more, put it on before you go out and again every two hours, and after every swim.'),
            lang('Stay in the shade between eleven and three, when the sun is strongest, and keep babies out of direct sun altogether.'),
            lang('Check your moles once a month. One that grows, changes colour or bleeds is worth a visit; a yearly skin check takes ten minutes.'),
        )),
        'cover_image' => 'https://picsum.photos/seed/pinegrap-clinic-post-4/1200/675',
    ),
);

// The staff's forms: the blog and the staff directory.
$staff_form_page = function ($key, $name, $title, $lead, $widget_key, $form) use ($staff_page, $page_header, $el, $container, $row, $col, $widget) {
    return array(
        'key'     => $key,
        'name'    => $name,
        'folder'  => 'private',
        'title'   => $title,
        'search'  => false,
        'sitemap' => false,
        'noindex' => true,
        'form'    => $form,
        'tree'    => $staff_page(array(
            $page_header($title, $lead),
            $el('section', 'py-5', lang('Content'), array(
                $container(array(
                    $row(array(
                        $col(array(
                            $el('div', 'p-4 rounded-4 border', lang('Form Card'), array($widget($widget_key))),
                        ), '', '8'),
                    ), '', 'center'),
                )),
            )),
        )),
    );
};
$pages[] = $staff_form_page('blog_new', lang('new-blog-post'), lang('New Blog Post'),
    lang('Write the post; it appears on the blog as soon as you send it.'), 'blog_form',
    array(
        'form_name'            => lang('Blog Posts'),
        'confirmation_message' => lang('The post is published.'),
        'confirmation_page_id' => '{{tab:blog}}',
        // The template's sample posts are written in when the form is made,
        // so the blog and the home page do not open empty
        // (pg_cf_seed_sample_records()).
        'sample_records'       => $sample_posts,
    ));
$pages[] = $staff_form_page('staff_entry', lang('add-to-staff-directory'), lang('Add to the Staff Directory'),
    lang('Signed-in patients see the directory from their account.'), 'staff_form',
    array(
        'form_name'            => lang('Staff Directory'),
        'confirmation_message' => lang('The person is in the staff directory.'),
        'confirmation_page_id' => '{{tab:staff_directory}}',
    ));

// The help center: a search over the whole site - pages and the parts of
// pages marked as search sections (the services, the doctors, the guide's
// topics and questions) - with the common questions under it, each a
// search section of its own.
$help_topic = function ($icon_name, $text, $href) use ($el, $icon, $span) {
    return $el('a', 'list-group-item list-group-item-action d-flex align-items-center gap-2', lang('Topic'), array(
        $icon($icon_name, 'text-primary'),
        $span($text, '', lang('Link Text')),
    ), array('href' => $href));
};
$pages[] = array(
    'key'              => 'search',
    'name'             => lang('help-center'),
    'folder'           => 'public',
    'title'            => lang('Help Center'),
    'meta_description' => lang('Search the practice\'s site: services, doctors, the patient guide and answers to common questions.'),
    'tree'             => $page(array(
        $page_header(lang('Help Center'), lang('Search the whole site: the services, the doctors, the patient guide and the answers to the questions we hear most.')),
        $el('section', 'py-5', lang('Content'), array(
            $container(array(
                $row(array(
                    $col(array($widget('site_search')), '', '8'),
                    $col(array(
                        $el('div', 'p-4 rounded-4 bg-body-tertiary mb-4', lang('Popular Topics'), array(
                            $heading('h2', lang('Popular topics'), 'h5 fw-bold mb-3'),
                            $el('nav', 'list-group', lang('Topics'), array(
                                $help_topic('bi-calendar2-check', lang('Book an Appointment'), '{{page:appointment}}'),
                                $help_topic('bi-card-checklist', lang('My Appointments'), '{{page:my_appointments}}'),
                                $help_topic('bi-journal-medical', lang('Patient Guide'), '{{page:patient_guide}}'),
                                $help_topic('bi-clipboard2-pulse', lang('Check-ups'), '{{page:checkups}}'),
                                $help_topic('bi-envelope', lang('Contact us'), '{{page:contact}}'),
                            ), array('_attrs' => array($attr('aria-label', lang('Popular topics'))))),
                        )),
                        $el('div', 'p-4 rounded-4 border', lang('Still Need Help'), array(
                            $heading('h2', lang('Still need help?'), 'h5 fw-bold mb-2'),
                            $para(lang('Write to us from the contact page; the answer comes to your inbox on this site.'), 'small text-body-secondary mb-3'),
                            $link(lang('Write to Us'), '{{page:contact}}', 'btn btn-sm btn-primary'),
                        )),
                    ), '', '4'),
                ), '4', '', 'g-lg-5'),
            )),
        )),
        $el('section', 'py-5 border-top', lang('Frequently Asked Questions'), array(
            $container(array(
                $section_header(lang('Questions'), lang('Frequently asked questions')),
                $row(array(
                    $col(array(
                        $el('div', 'accordion', lang('FAQ'), array(
                            $faq(21, lang('How do I book an appointment?'), lang('Open Book an Appointment, choose the service, a doctor if you like, the day and a time of day, and send the form. We confirm by e-mail.'), lang('book, appointment, reservation, online booking'), 'cl-help-faq'),
                            $faq(22, lang('How do I know my appointment is confirmed?'), lang('We send you an e-mail, and the status on your appointment\'s page changes to Confirmed. My Appointments lists every request with its status.'), lang('confirmation, confirmed, status'), 'cl-help-faq'),
                            $faq(23, lang('How do I cancel or move an appointment?'), lang('Open the appointment from My Appointments and write to us under it, or call reception, at least a day ahead if you can.'), lang('cancel, change, reschedule'), 'cl-help-faq'),
                            $faq(24, lang('Where do I find your answer to my message?'), lang('In your inbox: sign in and open Inbox from the menu under your name. Every reply is on the conversation page, and you can write back there.'), lang('inbox, reply, answer, conversation'), 'cl-help-faq'),
                        ), array('id' => 'cl-help-faq')),
                    ), '', '9'),
                ), '', 'center'),
            )),
        )),
    )),
);

// The site's error page.
$pages[] = array(
    'key'     => 'error',
    'name'    => lang('page-not-found'),
    'folder'  => 'public',
    'title'   => lang('Page Not Found'),
    'search'  => false,
    'sitemap' => false,
    'noindex' => true,
    'tree'    => $page(array($widget('error_page')), false),
);

// ── E-mail pages ────────────────────────────────────────────────────────
// Sent as the body of an e-mail, never visited. E-mail programs drop linked
// stylesheets, so the few rules that shape them are written on the elements
// next to the Bootstrap classes.
$mail_style = function ($css) use ($attr) {
    return array('_attrs' => array($attr('style', $css)));
};
$mail_page = function ($children) use ($el, $shared, $mail_style) {
    return array('type' => 'root', 'props' => array('cssClass' => ''), 'children' => array(
        $el('div', 'bg-body-tertiary py-4 px-3', lang('E-mail Frame'), array(
            $el('div', 'mx-auto bg-body border rounded-3 overflow-hidden', lang('E-mail Card'), array(
                $shared('email_header'),
                $el('div', 'p-4', lang('E-mail Body'), $children, $mail_style('padding:24px')),
                $shared('email_footer'),
            ), $mail_style('max-width:600px;margin:0 auto;background:#ffffff;border:1px solid #d5e7e4;border-radius:10px;overflow:hidden')),
        ), $mail_style('background:#f0fdfa;padding:24px 12px;font-family:-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,Helvetica,Arial,sans-serif;font-size:15px;line-height:1.6;color:#12302c')),
    ));
};
$mail_heading = function ($text) use ($heading, $attr) {
    return $heading('h1', $text, 'h4 fw-bold mb-3', array('_attrs' => array($attr('style', 'font-size:22px;font-weight:700;margin:0 0 16px;color:#12302c'))));
};
$mail_para = function ($text, $class = 'mb-3') use ($para, $attr) {
    return $para($text, $class, array('_attrs' => array($attr('style', 'margin:0 0 16px'))));
};
$mail_button = function ($text, $href) use ($el, $link, $mail_style, $attr) {
    return $el('p', 'my-4', lang('Button'), array(
        $link($text, $href, 'btn btn-primary px-4', array($attr('style',
            'display:inline-block;padding:10px 22px;border-radius:6px;font-weight:600;text-decoration:none;'
            . 'background-color:#0f766e;background-color:var(--bs-primary);color:#ffffff;color:var(--pg-on-primary,#ffffff)'))),
    ), $mail_style('margin:24px 0'));
};
$mail_line = function ($label, $values) use ($el, $span, $mail_style, $attr) {
    return $el('p', 'pg-hide-if-empty mb-1', lang('Line'), array(
        $span($label . ': ', 'text-body-secondary', lang('Label'), array('_attrs' => array($attr('style', 'color:#5b6b69')))),
        $el('span', 'pg-field-value fw-semibold', lang('Value'), $values),
    ), $mail_style('margin:0 0 4px'));
};
$mail_box = function ($children, $name) use ($el, $mail_style) {
    return $el('div', 'bg-body-tertiary rounded-3 p-3 my-3', $name, $children,
        $mail_style('background:#f0fdfa;border-radius:8px;padding:16px;margin:16px 0'));
};
$mail_greeting = function () use ($el, $span, $bind, $mail_style) {
    return $el('p', 'mb-3', lang('Greeting'), array(
        $span(lang('Hello') . ' ', '', lang('Text')),
        $span(lang('Jane'), '', lang('First Name'), $bind('first_name')),
    ), $mail_style('margin:0 0 16px'));
};
// The lines of an appointment request, for both e-mails.
$mail_appointment = function () use ($mail_box, $mail_line, $span, $bind) {
    return $mail_box(array(
        $mail_line(lang('Specialty'), array($span(lang('Family Medicine'), '', lang('Specialty'), $bind('department')))),
        $mail_line(lang('Doctor'), array($span(lang('No preference'), '', lang('Doctor'), $bind('doctor')))),
        $mail_line(lang('Preferred Date'), array($span('2026-10-20', '', lang('Date'), $bind('preferred_date')))),
        $mail_line(lang('Preferred Time'), array($span(lang('Morning (08:00–12:00)'), '', lang('Time'), $bind('preferred_time')))),
        $mail_line(lang('Visit type'), array($span(lang('At the practice'), '', lang('Visit type'), $bind('visit_mode')))),
    ), lang('Appointment'));
};

$mail_settings = array('folder' => 'private', 'search' => false, 'sitemap' => false, 'noindex' => true);

// The appointment form's notification: what the staff receive.
$pages[] = array_merge($mail_settings, array(
    'key'   => 'email_appointment_new',
    'name'  => lang('email-new-appointment'),
    'title' => lang('New Appointment E-mail'),
    'tree'  => $mail_page(array($widget('email_appointment_admin'))),
));
// The appointment form's reply to the patient.
$pages[] = array_merge($mail_settings, array(
    'key'   => 'email_appointment_received',
    'name'  => lang('email-appointment-received'),
    'title' => lang('Appointment Received E-mail'),
    'tree'  => $mail_page(array($widget('email_appointment_patient'))),
));
// The contact form's notification: what the staff receive for a message.
$pages[] = array_merge($mail_settings, array(
    'key'   => 'email_new_message',
    'name'  => lang('email-new-message'),
    'title' => lang('New Message E-mail'),
    'tree'  => $mail_page(array($widget('email_message_admin'))),
));
// The contact form's reply to the sender.
$pages[] = array_merge($mail_settings, array(
    'key'   => 'email_message_received',
    'name'  => lang('email-message-received'),
    'title' => lang('Message Received E-mail'),
    'tree'  => $mail_page(array($widget('email_message_sender'))),
));
// A new reply, comment or message: sent to the patient of an appointment,
// the sender of a conversation and the visitors watching a page. The System
// region carries the comment and a "View or Reply" button.
$pages[] = array_merge($mail_settings, array(
    'key'   => 'email_reply',
    'name'  => lang('email-new-reply'),
    'title' => lang('New Reply E-mail'),
    'tree'  => $mail_page(array(
        $mail_heading(lang('There is a new message')),
        $mail_para(lang('Something new was written on a page you follow: your appointment, a conversation or a post. You can read it below and answer it on the page.')),
        array('type' => 'region', 'props' => array('regionType' => 'system', 'regionName' => ''), 'children' => array()),
    )),
));
// The newsletter's welcome, sent to a new subscriber.
$pages[] = array_merge($mail_settings, array(
    'key'   => 'email_welcome',
    'name'  => lang('email-newsletter-welcome'),
    'title' => lang('Newsletter Welcome E-mail'),
    'tree'  => $mail_page(array(
        $mail_heading(lang('Welcome to the health letter')),
        $mail_para(lang('Thank you for subscribing. Once a month you hear what the season asks of your health, when a vaccination is due and what is new at the practice.')),
        $mail_para(lang('Changed your mind? Every e-mail we send has a link to unsubscribe.'), 'small text-body-secondary mb-3'),
        $mail_button(lang('Read the latest health notes'), '{{page:blog}}'),
    )),
));

// ── Widgets ─────────────────────────────────────────────────────────────

$login_item = function ($text, $token, $flag = '') use ($el, $link) {
    $extra = ($flag !== '') ? array('_bindings' => array('eo_visible_if' => $flag)) : array();
    return $el('li', '', lang('Menu Item'), array(
        $link($text, '#', 'dropdown-item', array(), array('_bindings' => array('href' => $token))),
    ), $extra);
};
$login_page_item = function ($text, $href) use ($el, $link) {
    return $el('li', '', lang('Menu Item'), array($link($text, $href, 'dropdown-item')));
};
$login_divider = function () use ($el) {
    return $el('li', '', lang('Divider'), array($el('hr', 'dropdown-divider', lang('Divider Line'))));
};
// $flag shows the link only while that widget flag is on.
$account_link = function ($icon_name, $text, $href, $token = '', $flag = '') use ($el, $icon, $span) {
    $extra = array('href' => $href);
    if ($token !== '') $extra['_bindings'] = array('href' => $token);
    if ($flag !== '') $extra['_bindings']['eo_visible_if'] = $flag;
    return $el('a', 'list-group-item list-group-item-action d-flex align-items-center gap-2', lang('Menu Item'), array(
        $icon($icon_name, 'text-primary'),
        $span($text, '', lang('Link Text')),
    ), $extra);
};

// The patient's account menu: the appointments first.
$account_links = array(
    $account_link('bi-calendar2-check', lang('My Appointments'), '{{page:my_appointments}}'),
    $account_link('bi-calendar-plus', lang('Book an Appointment'), '{{page:appointment}}'),
    $account_link('bi-inbox', lang('Inbox'), '{{page:inbox}}'),
    $account_link('bi-person-vcard', lang('My Profile'), '#', '__profile_url'),
);
if ($shop) $account_links[] = $account_link('bi-journal-bookmark', lang('Address Book'), '#', '__address_book_url');
$account_links[] = $account_link('bi-shield-lock', lang('Change Password'), '#', '__change_password_url');
$account_links[] = $account_link('bi-envelope-paper', lang('Email Preferences'), '#', '__email_preferences_url');
$account_links[] = $account_link('bi-person-lines-fill', lang('Staff Directory'), '{{page:staff_directory}}');
// Drawn only for the members the staff area's private folder lets in.
$account_links[] = $account_link('bi-person-badge', lang('Staff Area'), '#', '__staff_url', 'is_staff');

// An appointment in a list: the day, the service, the doctor, the time and
// the status, linked to its page.
$appointment_row = function () use ($el, $span, $icon, $bind) {
    return $el('a', 'list-group-item list-group-item-action p-3', lang('Appointment'), array(
        $el('div', 'd-flex flex-wrap align-items-center justify-content-between gap-2 mb-1', lang('Top Line'), array(
            $span(lang('Family Medicine'), 'fw-semibold', lang('Service'), $bind('department')),
            $span(lang('Requested'), 'badge rounded-pill bg-primary-subtle text-primary-emphasis', lang('Status'), $bind('status')),
        )),
        $el('div', 'd-flex flex-wrap column-gap-3 row-gap-1 small text-body-secondary', lang('Details'), array(
            $el('span', 'd-inline-flex align-items-center gap-1', lang('Date'), array($icon('bi-calendar3'), $span('2026-10-20', '', lang('Date'), $bind('preferred_date')))),
            $el('span', 'd-inline-flex align-items-center gap-1', lang('Time'), array($icon('bi-clock'), $span(lang('Morning (08:00–12:00)'), '', lang('Time'), $bind('preferred_time')))),
            $el('span', 'd-inline-flex align-items-center gap-1', lang('Doctor'), array($icon('bi-person'), $span(lang('No preference'), '', lang('Doctor'), $bind('doctor')))),
            $el('span', 'd-inline-flex align-items-center gap-1', lang('Messages'), array($icon('bi-chat-left-text'), $span('0', '', lang('Reply Count'), $bind('number_of_comments')))),
        )),
    ), array('href' => '#', '_bindings' => array('href' => 'form_item_view')));
};

$widgets = array(

    // The account bar's view of the session.
    'login_region' => array(
        'page'   => '',
        'slug'   => lang('login-region'),
        'config' => array(
            'regionType'         => 'login_region',
            'login_page_id'      => '{{tab:login}}',
            'register_page_id'   => '{{tab:register}}',
            'show_register_link' => true,
            'account_page_id'    => '{{tab:my_account}}',
            'staff_page_id'      => '{{tab:staff}}',
        ),
        'tree'   => $widget_root(array(
            $el('div', 'd-flex align-items-center gap-2', lang('Signed Out'), array(
                $link(lang('Log In'), '#', 'btn btn-sm btn-link link-body-emphasis text-decoration-none text-nowrap', array(), array('_bindings' => array('href' => '__login_url'))),
                $link(lang('Sign Up'), '#', 'btn btn-sm btn-outline-primary text-nowrap', array(), array('_bindings' => array('href' => '__register_url'))),
            ), $show_when('is_signed_out')),
            $el('div', 'dropdown', lang('Signed In'), array(
                $el('a', 'd-flex align-items-center gap-2 link-body-emphasis text-decoration-none dropdown-toggle', lang('Account Button'), array(
                    $image('https://placehold.co/64x64/0f766e/ffffff?text=JC', lang('Jane Cooper'), 'rounded-circle object-fit-cover',
                        array('width' => '24', 'height' => '24', '_bindings' => array('src' => '__user_avatar_url', 'alt' => '__user_name'))),
                    $span(lang('Jane'), 'fw-semibold', lang('First Name'), $bind('__user_first_name')),
                ), array('href' => '#', '_attrs' => array(
                    $attr('role', 'button'),
                    $attr('data-bs-toggle', 'dropdown'),
                    $attr('aria-expanded', 'false'),
                ))),
                $el('ul', 'dropdown-menu dropdown-menu-end shadow-sm', lang('Account Menu'), array(
                    $el('li', 'px-3 py-2', lang('Account Summary'), array(
                        $span(lang('Jane Cooper'), 'd-block fw-semibold', lang('Full Name'), $bind('__user_name')),
                        $span(lang('jane.cooper@example.com'), 'd-block small text-body-secondary', lang('Email'), $bind('__user_email')),
                    )),
                    $login_divider(),
                    $login_item(lang('My Account'), '__my_account_url'),
                    $login_page_item(lang('My Appointments'), '{{page:my_appointments}}'),
                    $login_page_item(lang('Inbox'), '{{page:inbox}}'),
                    $login_item(lang('Edit my profile'), '__profile_url'),
                    $login_item(lang('Staff area'), '__staff_url', 'is_staff'),
                    $login_item(lang('Control panel'), '__panel_url', 'has_panel_access'),
                    $login_divider(),
                    $login_item(lang('Log out'), '__logout_url'),
                )),
            ), $show_when('is_signed_in')),
            $loop(array()),
        )),
    ),

    // The newsletter's own form, on its page: the address and the consent.
    'newsletter_form' => array(
        'page'   => 'newsletter',
        'slug'   => 'form',
        'config' => array('regionType' => 'custom_form', 'form_source' => 'page'),
        'tree'   => $widget_root(array(
            $icon('bi-envelope-heart', 'fs-1 text-primary d-block mb-2'),
            $heading('h2', lang('The health letter'), 'h4 fw-bold mb-2'),
            $para(lang('Leave your e-mail address; we write once a month, with advice for the season.'), 'text-body-secondary mb-4'),
            $newsletter_line('cl-newsletter-email'),
            $newsletter_consent('cl-newsletter-consent'),
            $captcha(),
            $loop(array()),
        )),
    ),

    // The band at the foot of the pages: the same form, the newsletter
    // page's, so every sign-up lands in one list.
    'newsletter_band' => array(
        'page'   => '',
        'slug'   => lang('newsletter'),
        'config' => array(
            'regionType'          => 'custom_form',
            'form_source'         => 'existing',
            'form_id'             => '{{tab:newsletter}}',
            'custom_form_page_id' => '{{tab:newsletter}}',
        ),
        'tree'   => $widget_root(array(
            $el('section', 'py-5', lang('Newsletter'), array(
                $container(array(
                    $el('div', 'rounded-4 bg-body-tertiary p-4 p-lg-5', lang('Newsletter Band'), array(
                        $row(array(
                            $col(array(
                                $el('div', 'd-flex align-items-start gap-3', lang('Text'), array(
                                    $icon('bi-envelope-heart', 'fs-1 lh-1 text-primary'),
                                    $el('div', '', lang('Words'), array(
                                        $heading('h2', lang('The health letter'), 'h4 fw-bold mb-1'),
                                        $para(lang('Advice for the season and vaccination reminders, once a month. Unsubscribe whenever you like.'), 'text-body-secondary mb-0'),
                                    )),
                                )),
                            ), '', '5'),
                            $col(array(
                                $newsletter_line('cl-band-email'),
                                $newsletter_consent('cl-band-consent'),
                                $captcha(),
                            ), '', '7'),
                        ), '4', '', 'align-items-center'),
                    )),
                )),
            )),
            $loop(array()),
        )),
    ),

    // The appointment request: the fields are the page's form. The Status
    // is the staff's (office use only): a visitor never sees it, and every
    // request starts as Requested.
    'appointment_form' => array(
        'page'   => 'appointment',
        'slug'   => 'form',
        'config' => array('regionType' => 'custom_form', 'form_source' => 'page'),
        'tree'   => $widget_root(array(
            $form_step(1, lang('What do you need?')),
            $row(array(
                $col(array($field('cl-appt-department', lang('Specialty'), $select('cl-appt-department', 'department', lang('Choose a specialty…'), $service_titles))), '6'),
                $col(array($field('cl-appt-doctor', lang('Doctor'), $select('cl-appt-doctor', 'doctor', '', $doctor_choices, true, lang('No preference')))), '6'),
            ), '3'),
            $radios('cl-appt-visit', 'visit_mode', lang('How would you like to be seen?'), array(
                array(lang('At the practice'), lang('At the practice')),
                array(lang('Video call'), lang('Video call')),
            ), lang('At the practice')),
            $form_step(2, lang('When suits you?')),
            $row(array(
                $col(array($field('cl-appt-date', lang('Preferred Date'), $input('cl-appt-date', 'date', 'preferred_date', 'off'))), '6'),
                $col(array($field('cl-appt-time', lang('Preferred Time'), $select('cl-appt-time', 'preferred_time', lang('Choose a time of day…'), $time_windows))), '6'),
            ), '3'),
            $form_step(3, lang('About you')),
            $row(array(
                $col(array($field('cl-appt-first-name', lang('First Name'), $input('cl-appt-first-name', 'text', 'first_name', 'given-name', true, array('contact_field' => 'first_name')))), '6'),
                $col(array($field('cl-appt-last-name', lang('Last Name'), $input('cl-appt-last-name', 'text', 'last_name', 'family-name', true, array('contact_field' => 'last_name')))), '6'),
                $col(array($field('cl-appt-email', lang('Email'), $input('cl-appt-email', 'email', 'email', 'email', true, array('contact_field' => 'email_address')))), '6'),
                $col(array($field('cl-appt-phone', lang('Phone'), $input('cl-appt-phone', 'tel', 'phone', 'tel', true, array('contact_field' => 'mobile_phone')))), '6'),
                $col(array($field('cl-appt-birth', lang('Date of Birth'), $input('cl-appt-birth', 'date', 'date_of_birth', 'bday', false))), '6'),
                $col(array($field('cl-appt-insurance', lang('Insurance'), $select('cl-appt-insurance', 'insurance', lang('Choose…'), array(
                    lang('Private insurance'),
                    lang('National health insurance'),
                    lang('No insurance, I pay myself'),
                ), false))), '6'),
            ), '3'),
            $field('cl-appt-reason', lang('Reason for the visit'), $textarea('cl-appt-reason', 'reason', 4, false)),
            $field('cl-appt-status', lang('Status'), $select('cl-appt-status', 'status', '', array(
                lang('Requested'), lang('Confirmed'), lang('Completed'), lang('Cancelled'),
            ), false, lang('Requested'), array('office_use_only' => true))),
            $agree('cl-appt-consent', 'consent', lang('I agree that the practice keeps these details to arrange my care, as its privacy notice explains.')),
            $captcha(),
            $el('div', 'd-flex flex-wrap align-items-center gap-3', lang('Send Area'), array(
                $submit(lang('Request Appointment'), 'btn btn-primary btn-lg px-4'),
                $para(lang('We confirm by e-mail, usually within two working hours.'), 'small text-body-secondary mb-0'),
            )),
            $loop(array()),
        )),
    ),

    // One appointment: the request, its status and what to bring; the
    // messages follow as comments.
    'appointment_detail' => array(
        'page'   => 'appointment_view',
        'slug'   => lang('form-detail'),
        'config' => array(
            'regionType'          => 'form_item_view',
            'custom_form_page_id' => '{{tab:appointment}}',
            'access_control'      => 'submitter_only',
            'not_found_message'   => lang('This appointment could not be found, or it is not yours to see.'),
        ),
        'tree'   => $widget_root(array(
            $container(array(
                $row(array(
                    $col(array(
                        $el('div', 'text-center py-5', lang('Not Found'), array(
                            $icon('bi-calendar-x', 'display-5 text-body-tertiary'),
                            $heading('h2', lang('Appointment not found'), 'h3 fw-bold mt-3'),
                            $para(lang('This appointment could not be found, or it is not yours to see.'), 'text-body-secondary mb-4', $bind('not_found')),
                            $link(lang('My Appointments'), '{{page:my_appointments}}', 'btn btn-primary px-4'),
                        ), $show_when('record_not_found')),
                        $el('div', '', lang('Appointment'), array(
                            $el('div', 'alert alert-success d-flex gap-2', lang('Sent Notice'), array(
                                $icon('bi-check-circle', 'mt-1'),
                                $span(lang('Thank you, your request has reached us. We confirm the time by e-mail; this page shows the status, and you can write to us below.'), '', lang('Text')),
                            ), array_merge(array('_attrs' => array($attr('role', 'status'))), $show_when('submitted_this_session'))),
                            $el('div', 'alert alert-info', lang('New Account'), array(
                                $el('p', 'fw-semibold mb-2', lang('Title'), array(), array('text' => lang('We opened an account for you'))),
                                $el('p', 'mb-1', lang('Sign-in Email'), array(
                                    $span(lang('Email') . ': ', '', lang('Label')),
                                    $span('sample@example.com', 'fw-semibold', lang('Value'), $bind('__new_account_email')),
                                )),
                                $el('p', 'mb-2', lang('Temporary Password'), array(
                                    $span(lang('Temporary password') . ': ', '', lang('Label')),
                                    $span('a1b2c3d4', 'fw-semibold font-monospace', lang('Value'), $bind('__new_account_password')),
                                )),
                                $para(lang('You are signed in. Keep these details, and choose a password of your own.'), 'small mb-2'),
                                $link(lang('Change Password'), '{{page:change_password}}', 'btn btn-sm btn-primary'),
                            ), $show_when('has_new_account')),
                            $el('div', 'd-flex flex-wrap align-items-center justify-content-between gap-2 mb-3', lang('Toolbar'), array(
                                $el('a', 'd-inline-flex align-items-center gap-1 small fw-semibold link-primary text-decoration-none', lang('Link'), array(
                                    $icon('bi-arrow-left'),
                                    $span(lang('My Appointments'), '', lang('Link Text')),
                                ), array('href' => '{{page:my_appointments}}')),
                                $edit_button(lang('Change Status')),
                            )),
                            $el('div', 'card border-0 shadow-sm mb-4', lang('Appointment Card'), array(
                                $el('div', 'card-body p-4', lang('Card Body'), array(
                                    $el('div', 'd-flex flex-wrap align-items-start justify-content-between gap-2 mb-3', lang('Title'), array(
                                        $el('div', '', lang('Words'), array(
                                            $para(lang('Appointment'), 'small text-uppercase fw-semibold text-primary mb-1'),
                                            $heading('h1', lang('Family Medicine'), 'h3 fw-bold mb-0', $bind('department')),
                                        )),
                                        $span(lang('Requested'), 'badge rounded-pill bg-primary-subtle text-primary-emphasis fs-6', lang('Status'), $bind('status')),
                                    )),
                                    $el('dl', 'row small mb-0', lang('Details'), array(
                                        $el('dt', 'col-sm-4 fw-normal text-body-secondary', lang('Label'), array(), array('text' => lang('Preferred Date'))),
                                        $el('dd', 'col-sm-8 fw-semibold', lang('Date'), array(), array_merge(array('text' => '2026-10-20'), $bind('preferred_date'))),
                                        $el('dt', 'col-sm-4 fw-normal text-body-secondary', lang('Label'), array(), array('text' => lang('Preferred Time'))),
                                        $el('dd', 'col-sm-8 fw-semibold', lang('Time'), array(), array_merge(array('text' => lang('Morning (08:00–12:00)')), $bind('preferred_time'))),
                                        $el('dt', 'col-sm-4 fw-normal text-body-secondary', lang('Label'), array(), array('text' => lang('Doctor'))),
                                        $el('dd', 'col-sm-8', lang('Doctor'), array(), array_merge(array('text' => lang('No preference')), $bind('doctor'))),
                                        $el('dt', 'col-sm-4 fw-normal text-body-secondary', lang('Label'), array(), array('text' => lang('Visit type'))),
                                        $el('dd', 'col-sm-8', lang('Visit type'), array(), array_merge(array('text' => lang('At the practice')), $bind('visit_mode'))),
                                        $el('dt', 'col-sm-4 fw-normal text-body-secondary', lang('Label'), array(), array('text' => lang('Patient'))),
                                        $el('dd', 'col-sm-8', lang('Patient'), array(
                                            $span(lang('Jane'), '', lang('First Name'), $bind('first_name')),
                                            $span(' ', '', lang('Text')),
                                            $span(lang('Cooper'), '', lang('Last Name'), $bind('last_name')),
                                        )),
                                        $el('dt', 'col-sm-4 fw-normal text-body-secondary', lang('Label'), array(), array('text' => lang('Reference Code'))),
                                        $el('dd', 'col-sm-8 mb-0 font-monospace', lang('Reference Code'), array(), array_merge(array('text' => 'ABC123'), $bind('reference_code'))),
                                    )),
                                )),
                            )),
                            $el('div', 'pg-hide-if-empty p-4 rounded-4 bg-body-tertiary mb-4', lang('Reason'), array(
                                $para(lang('Reason for the visit'), 'small fw-semibold text-body-secondary mb-2'),
                                $el('div', 'pg-field-value lh-lg', lang('Value'), array(), array_merge(
                                    array('text' => lang('A cough for two weeks that will not go away.')), $bind('reason'))),
                            )),
                            $el('div', 'd-flex gap-2 small text-body-secondary', lang('Note'), array(
                                $icon('bi-chat-left-text', 'text-primary mt-1'),
                                $span(lang('A question before the visit, or need to change the time? Write to us below; we answer here and by e-mail.'), '', lang('Text')),
                            )),
                        ), $show_when('record_found')),
                    ), '', '8'),
                ), '', 'center'),
            ), 'py-5'),
        )),
    ),

    // My Appointments: the requests the patient sent (or watches); the
    // staff see them all. Newest first.
    'my_appointments_list' => array(
        'page'   => 'my_appointments',
        'slug'   => lang('form-list'),
        'config' => array(
            'regionType'              => 'form_list_view',
            'custom_form_page_id'     => '{{tab:appointment}}',
            'detail_page_id'          => '{{tab:appointment_view}}',
            'items_per_page'          => 20,
            'order_by_field'          => 'submitted_date_and_time',
            'order_by_direction'      => 'DESC',
            'viewer_filter'           => true,
            'viewer_filter_submitter' => true,
            'viewer_filter_watcher'   => true,
            'viewer_filter_editor'    => true,
            'empty_message'           => lang('No appointments yet. Book your first one in two minutes.'),
        ),
        'tree'   => $widget_root(array(
            $el('div', 'list-group', lang('Appointment List'), array(
                $loop(array($appointment_row())),
            )),
        )),
    ),

    // The staff area's coming appointments: from today on, the nearest
    // first.
    'staff_appointments' => array(
        'page'   => 'staff',
        'slug'   => lang('form-list'),
        'config' => array(
            'regionType'          => 'form_list_view',
            'custom_form_page_id' => '{{tab:appointment}}',
            'detail_page_id'      => '{{tab:appointment_view}}',
            'items_per_page'      => 20,
            'order_by_field'      => 'preferred_date',
            'order_by_direction'  => 'ASC',
            'filters'             => array(
                array('field' => 'preferred_date', 'operator' => 'is greater than or equal to', 'value' => '', 'dynamic_value' => 'current date'),
            ),
            'search_enabled'      => true,
            'search_fields'       => array('first_name', 'last_name', 'department', 'doctor', 'status'),
            'search_width'        => 'half',
            'search_label'        => lang('Search by patient, service or status...'),
            'empty_message'       => lang('No appointments from today on.'),
        ),
        'tree'   => $widget_root(array(
            $el('div', 'list-group', lang('Appointment List'), array(
                $loop(array(
                    $el('a', 'list-group-item list-group-item-action p-3', lang('Appointment'), array(
                        $el('div', 'd-flex flex-wrap align-items-center justify-content-between gap-2 mb-1', lang('Top Line'), array(
                            $el('span', 'fw-semibold', lang('Patient'), array(
                                $span(lang('Jane'), '', lang('First Name'), $bind('first_name')),
                                $span(' ', '', lang('Text')),
                                $span(lang('Cooper'), '', lang('Last Name'), $bind('last_name')),
                            )),
                            $span(lang('Requested'), 'badge rounded-pill bg-primary-subtle text-primary-emphasis', lang('Status'), $bind('status')),
                        )),
                        $el('div', 'd-flex flex-wrap column-gap-3 row-gap-1 small text-body-secondary', lang('Details'), array(
                            $el('span', 'd-inline-flex align-items-center gap-1', lang('Date'), array($icon('bi-calendar3'), $span('2026-10-20', 'fw-semibold', lang('Date'), $bind('preferred_date')))),
                            $el('span', 'd-inline-flex align-items-center gap-1', lang('Time'), array($icon('bi-clock'), $span(lang('Morning (08:00–12:00)'), '', lang('Time'), $bind('preferred_time')))),
                            $el('span', 'd-inline-flex align-items-center gap-1', lang('Service'), array($icon('bi-heart-pulse'), $span(lang('Family Medicine'), '', lang('Service'), $bind('department')))),
                            $el('span', 'd-inline-flex align-items-center gap-1', lang('Doctor'), array($icon('bi-person'), $span(lang('No preference'), '', lang('Doctor'), $bind('doctor')))),
                            $el('span', 'd-inline-flex align-items-center gap-1', lang('Phone'), array($icon('bi-telephone'), $span('+90 555 000 00 00', '', lang('Phone'), $bind('phone')))),
                        )),
                    ), array('href' => '#', '_bindings' => array('href' => 'form_item_view'))),
                )),
            )),
        )),
    ),

    // Contact: the fields are the page's form.
    'contact_form' => array(
        'page'   => 'contact',
        'slug'   => 'form',
        'config' => array('regionType' => 'custom_form', 'form_source' => 'page'),
        'tree'   => $widget_root(array(
            $row(array(
                $col(array($field('cl-contact-first-name', lang('First Name'), $input('cl-contact-first-name', 'text', 'first_name', 'given-name', true, array('contact_field' => 'first_name')))), '6'),
                $col(array($field('cl-contact-last-name', lang('Last Name'), $input('cl-contact-last-name', 'text', 'last_name', 'family-name', false, array('contact_field' => 'last_name')))), '6'),
                $col(array($field('cl-contact-email', lang('Email'), $input('cl-contact-email', 'email', 'email', 'email', true, array('contact_field' => 'email_address')))), '6'),
                $col(array($field('cl-contact-phone', lang('Phone'), $input('cl-contact-phone', 'tel', 'phone', 'tel', false, array('contact_field' => 'mobile_phone')))), '6'),
            ), '3'),
            $field('cl-contact-topic', lang('What is it about?'), $select('cl-contact-topic', 'topic', lang('Choose a topic…'), array(
                lang('A question about an appointment'),
                lang('Test results'),
                lang('A repeat prescription'),
                lang('Insurance and payment'),
                lang('Something else'),
            ))),
            $field('cl-contact-message', lang('Your Message'), $textarea('cl-contact-message', 'message', 6)),
            $captcha(),
            $submit(lang('Send Message')),
            $loop(array()),
        )),
    ),

    // The three newest posts on the home page.
    'home_posts' => array(
        'page'   => 'home',
        'slug'   => lang('form-list'),
        'config' => array(
            'regionType'          => 'form_list_view',
            'custom_form_page_id' => '{{tab:blog_new}}',
            'detail_page_id'      => '{{tab:blog_post}}',
            'items_per_page'      => 3,
            'max_results'         => 3,
            'order_by_field'      => 'submitted_date_and_time',
            'order_by_direction'  => 'DESC',
            'empty_message'       => lang('No posts yet.'),
        ),
        'tree'   => $widget_root(array(
            $row(array($loop(array($post_card()))), '4'),
        )),
    ),

    // The blog: every post, newest first, with a search over the posts.
    'blog_list' => array(
        'page'   => 'blog',
        'slug'   => lang('form-list'),
        'config' => array(
            'regionType'          => 'form_list_view',
            'custom_form_page_id' => '{{tab:blog_new}}',
            'detail_page_id'      => '{{tab:blog_post}}',
            'items_per_page'      => 9,
            'order_by_field'      => 'submitted_date_and_time',
            'order_by_direction'  => 'DESC',
            'search_enabled'      => true,
            'search_fields'       => array('title', 'summary', 'content'),
            'search_width'        => 'full',
            'search_label'        => lang('Search the blog...'),
            'empty_message'       => lang('No posts yet.'),
        ),
        'tree'   => $widget_root(array(
            $row(array($loop(array($post_card()))), '4', '', 'g-lg-5'),
        )),
    ),

    // One post.
    'blog_post' => array(
        'page'   => 'blog_post',
        'slug'   => lang('form-detail'),
        'config' => array(
            'regionType'          => 'form_item_view',
            'custom_form_page_id' => '{{tab:blog_new}}',
            'access_control'      => 'public',
            'not_found_message'   => lang('This post could not be found. It may have been removed.'),
        ),
        'tree'   => $widget_root(array(
            $container(array(
                $row(array(
                    $col(array(
                        $el('div', 'text-center py-5', lang('Not Found'), array(
                            $icon('bi-journal-x', 'display-5 text-body-tertiary'),
                            $heading('h2', lang('Post not found'), 'h3 fw-bold mt-3'),
                            $para(lang('This post could not be found. It may have been removed.'), 'text-body-secondary mb-4', $bind('not_found')),
                            $link(lang('Back to the blog'), '{{page:blog}}', 'btn btn-primary px-4'),
                        ), $show_when('record_not_found')),
                        $el('article', '', lang('Post'), array(
                            $el('div', 'd-flex flex-wrap align-items-center justify-content-between gap-2 mb-3', lang('Toolbar'), array(
                                $el('a', 'd-inline-flex align-items-center gap-1 small fw-semibold link-primary text-decoration-none', lang('Link'), array(
                                    $icon('bi-arrow-left'),
                                    $span(lang('All posts'), '', lang('Link Text')),
                                ), array('href' => '{{page:blog}}')),
                                $edit_button(),
                            )),
                            $heading('h1', lang('Five habits for a healthy heart'), 'display-6 fw-bold mb-3', $bind('title')),
                            $el('p', 'd-flex flex-wrap align-items-center gap-2 small text-body-secondary pb-3 mb-4 border-bottom', lang('Post Details'), array(
                                $icon('bi-calendar3'),
                                $span(lang('Date'), '', lang('Date'), $bind('submitted_date_and_time')),
                                $icon('bi-person', 'ms-2'),
                                $span(lang('Dr. Jane Cooper'), '', lang('Author'), $bind('submitter')),
                            )),
                            $ai_summary(),
                            $optional_image('cover_image', 'title', 'rounded-4 object-fit-cover', 'ratio ratio-16x9 mb-4',
                                'https://picsum.photos/seed/pinegrap-clinic-post/1200/675', lang('Cover Image')),
                            $para(lang('A short summary of the post. It is what makes a visitor open it, so two sentences are enough.'), 'lead mb-4', $bind('summary')),
                            $el('div', 'fs-6 lh-lg', lang('Post Content'), array(), array_merge(
                                array('text' => lang('The body of the post. Write it on the staff page; line breaks are kept.')), $bind('content'))),
                            $el('div', 'alert alert-secondary d-flex gap-2 small mt-5 mb-0', lang('Note'), array(
                                $icon('bi-info-circle', 'mt-1'),
                                $span(lang('This post is general information, not advice for your own health. If you are worried, book an appointment.'), '', lang('Text')),
                            )),
                        ), $show_when('record_found')),
                    ), '', '8'),
                ), '', 'center'),
            ), 'py-5'),
        )),
    ),

    // The staff page's blog form: what a post is made of.
    'blog_form' => array(
        'page'   => 'blog_new',
        'slug'   => 'form',
        'config' => array('regionType' => 'custom_form', 'form_source' => 'page'),
        'tree'   => $widget_root(array(
            $field('cl-post-title', lang('Title'), pg_cf_with_role($input('cl-post-title', 'text', 'title', 'off'), 'title')),
            $field('cl-post-summary', lang('Summary'), pg_cf_with_role($textarea('cl-post-summary', 'summary', 3), 'description')),
            $field('cl-post-cover', lang('Cover Image'), pg_cf_with_role($file('cl-post-cover', 'cover_image', 'public'), 'media')),
            $field('cl-post-content', lang('Content'), $textarea('cl-post-content', 'content', 14)),
            $submit(lang('Publish')),
            $loop(array()),
        )),
    ),

    // The staff page's directory form: one person.
    'staff_form' => array(
        'page'   => 'staff_entry',
        'slug'   => 'form',
        'config' => array('regionType' => 'custom_form', 'form_source' => 'page'),
        'tree'   => $widget_root(array(
            $row(array(
                $col(array($field('cl-staff-name', lang('Full Name'), $input('cl-staff-name', 'text', 'full_name', 'off'))), '6'),
                $col(array($field('cl-staff-title', lang('Job Title'), $input('cl-staff-title', 'text', 'job_title', 'off'))), '6'),
                $col(array($field('cl-staff-department', lang('Department'), $input('cl-staff-department', 'text', 'department', 'off', false))), '6'),
                $col(array($field('cl-staff-photo', lang('Photo'), $file('cl-staff-photo', 'photo', 'public'))), '6'),
                $col(array($field('cl-staff-email', lang('Email'), $input('cl-staff-email', 'email', 'email', 'off', false))), '6'),
                $col(array($field('cl-staff-phone', lang('Phone'), $input('cl-staff-phone', 'tel', 'phone', 'off', false))), '6'),
            ), '3'),
            $field('cl-staff-bio', lang('A few words'), $textarea('cl-staff-bio', 'bio', 3, false)),
            $submit(lang('Add')),
            $loop(array()),
        )),
    ),

    // The directory members see.
    'staff_directory' => array(
        'page'   => 'staff_directory',
        'slug'   => lang('form-list'),
        'config' => array(
            'regionType'          => 'form_list_view',
            'custom_form_page_id' => '{{tab:staff_entry}}',
            'items_per_page'      => 24,
            'order_by_field'      => 'full_name',
            'order_by_direction'  => 'ASC',
            'search_enabled'      => true,
            'search_fields'       => array('full_name', 'job_title', 'department'),
            'search_width'        => 'half',
            'search_label'        => lang('Search by name or department...'),
            'empty_message'       => lang('The directory is empty for now.'),
        ),
        'tree'   => $widget_root(array(
            $row(array($loop(array(
                $col(array(
                    $el('div', 'd-flex align-items-start gap-3 h-100 p-4 rounded-4 bg-body-tertiary', lang('Person Card'), array(
                        $optional_image('photo', 'full_name', 'rounded-circle object-fit-cover', 'flex-shrink-0',
                            'https://placehold.co/128x128/ccfbf1/0f766e?text=Dr', lang('Photo')),
                        $el('div', 'min-w-0', lang('Details'), array(
                            $heading('h2', lang('Dr. Jane Cooper'), 'h6 fw-bold mb-0', $bind('full_name')),
                            $para(lang('Family Medicine'), 'small text-primary fw-semibold mb-2', $bind('job_title')),
                            $el('p', 'pg-hide-if-empty small mb-1', lang('Department'), array(
                                $icon('bi-diagram-3', 'text-primary me-1'),
                                $span(lang('Operations'), 'pg-field-value', lang('Value'), $bind('department')),
                            )),
                            $el('p', 'pg-hide-if-empty small mb-1 text-break', lang('Email'), array(
                                $icon('bi-envelope', 'text-primary me-1'),
                                $span(lang('jane.cooper@example.com'), 'pg-field-value', lang('Value'), $bind('email')),
                            )),
                            $el('p', 'pg-hide-if-empty small mb-1', lang('Phone'), array(
                                $icon('bi-telephone', 'text-primary me-1'),
                                $span('+90 555 000 00 00', 'pg-field-value', lang('Value'), $bind('phone')),
                            )),
                            $el('p', 'pg-hide-if-empty small text-body-secondary mt-2 mb-0', lang('A few words'), array(
                                $span(lang('Happy to help with orders and accounts.'), 'pg-field-value', lang('Value'), $bind('bio')),
                            )),
                        )),
                    )),
                ), '6', '4'),
            ))), '4'),
        )),
    ),

    // The inbox: conversations the patient started (a signed-in submission
    // of the contact form), or is watching; the staff see them all. The
    // contact form's subject is its topic.
    'inbox_list' => array(
        'page'   => 'inbox',
        'slug'   => lang('form-list'),
        'config' => array(
            'regionType'              => 'form_list_view',
            'custom_form_page_id'     => '{{tab:contact}}',
            'detail_page_id'          => '{{tab:conversation}}',
            'items_per_page'          => 20,
            'order_by_field'          => 'submitted_date_and_time',
            'order_by_direction'      => 'DESC',
            'viewer_filter'           => true,
            'viewer_filter_submitter' => true,
            'viewer_filter_watcher'   => true,
            'viewer_filter_editor'    => true,
            'empty_message'           => lang('No conversations yet. Start one from the contact page.'),
        ),
        'tree'   => $widget_root(array(
            $el('div', 'list-group', lang('Conversation List'), array(
                $loop(array(
                    $el('a', 'list-group-item list-group-item-action py-3', lang('Conversation'), array(
                        $el('div', 'd-flex w-100 justify-content-between gap-3', lang('Top Line'), array(
                            $span(lang('A repeat prescription'), 'fw-semibold text-truncate', lang('Subject'), $bind('topic')),
                            $span(lang('Date'), 'small text-body-secondary text-nowrap', lang('Date'), $bind('submitted_date_and_time')),
                        )),
                        $el('div', 'd-flex align-items-center gap-2 small text-body-secondary mt-1', lang('Replies'), array(
                            $icon('bi-chat-left-text'),
                            $span('0', 'fw-semibold', lang('Reply Count'), $bind('number_of_comments')),
                            $span(lang('replies'), '', lang('Label')),
                        )),
                    ), array('href' => '#', '_bindings' => array('href' => 'form_item_view'))),
                )),
            )),
        )),
    ),

    // One conversation: the first message; the answers follow as comments.
    'conversation' => array(
        'page'   => 'conversation',
        'slug'   => lang('form-detail'),
        'config' => array(
            'regionType'          => 'form_item_view',
            'custom_form_page_id' => '{{tab:contact}}',
            'access_control'      => 'submitter_only',
            'not_found_message'   => lang('This conversation could not be found, or it is not yours to read.'),
        ),
        'tree'   => $widget_root(array(
            $container(array(
                $row(array(
                    $col(array(
                        $el('div', 'text-center py-5', lang('Not Found'), array(
                            $icon('bi-chat-left-dots', 'display-5 text-body-tertiary'),
                            $heading('h2', lang('Conversation not found'), 'h3 fw-bold mt-3'),
                            $para(lang('This conversation could not be found, or it is not yours to read.'), 'text-body-secondary mb-4', $bind('not_found')),
                            $link(lang('Back to the inbox'), '{{page:inbox}}', 'btn btn-primary px-4'),
                        ), $show_when('record_not_found')),
                        $el('div', '', lang('Conversation'), array(
                            $el('div', 'alert alert-success d-flex gap-2', lang('Sent Notice'), array(
                                $icon('bi-check-circle', 'mt-1'),
                                $span(lang('Thank you, your message has reached us. Our answers appear on this page and in your inbox, and you can write back below.'), '', lang('Text')),
                            ), array_merge(array('_attrs' => array($attr('role', 'status'))), $show_when('submitted_this_session'))),
                            $el('div', 'alert alert-info', lang('New Account'), array(
                                $el('p', 'fw-semibold mb-2', lang('Title'), array(), array('text' => lang('We opened an account for you'))),
                                $el('p', 'mb-1', lang('Sign-in Email'), array(
                                    $span(lang('Email') . ': ', '', lang('Label')),
                                    $span('sample@example.com', 'fw-semibold', lang('Value'), $bind('__new_account_email')),
                                )),
                                $el('p', 'mb-2', lang('Temporary Password'), array(
                                    $span(lang('Temporary password') . ': ', '', lang('Label')),
                                    $span('a1b2c3d4', 'fw-semibold font-monospace', lang('Value'), $bind('__new_account_password')),
                                )),
                                $para(lang('You are signed in. Keep these details, and choose a password of your own.'), 'small mb-2'),
                                $link(lang('Change Password'), '{{page:change_password}}', 'btn btn-sm btn-primary'),
                            ), $show_when('has_new_account')),
                            $el('div', 'd-flex flex-wrap align-items-center justify-content-between gap-2 mb-3', lang('Toolbar'), array(
                                $el('a', 'd-inline-flex align-items-center gap-1 small fw-semibold link-primary text-decoration-none', lang('Link'), array(
                                    $icon('bi-arrow-left'),
                                    $span(lang('Inbox'), '', lang('Link Text')),
                                ), array('href' => '{{page:inbox}}')),
                                $edit_button(),
                            )),
                            $heading('h1', lang('A repeat prescription'), 'h3 fw-bold mb-1', $bind('topic')),
                            $el('p', 'd-flex flex-wrap align-items-center gap-2 small text-body-secondary mb-4', lang('Conversation Details'), array(
                                $icon('bi-calendar3'),
                                $span(lang('Date'), '', lang('Date'), $bind('submitted_date_and_time')),
                                $icon('bi-hash', 'ms-2'),
                                $span('ABC123', '', lang('Reference Code'), $bind('reference_code')),
                            )),
                            $el('div', 'p-4 rounded-4 bg-body-tertiary', lang('First Message'), array(
                                $el('p', 'd-flex align-items-center gap-2 fw-semibold mb-2', lang('Sender'), array(
                                    $icon('bi-person-circle', 'fs-5 text-primary'),
                                    $span(lang('Jane'), '', lang('First Name'), $bind('first_name')),
                                    $span(lang('Cooper'), '', lang('Last Name'), $bind('last_name')),
                                )),
                                $el('div', 'lh-lg', lang('Message'), array(), array_merge(
                                    array('text' => lang('Hello, I need a repeat prescription for my blood pressure tablets.')), $bind('message'))),
                            )),
                        ), $show_when('record_found')),
                    ), '', '8'),
                ), '', 'center'),
            ), 'py-5'),
        )),
    ),

    // The patient's account: who is signed in and the way to each account
    // page.
    'my_account' => array(
        'page'   => 'my_account',
        'slug'   => lang('my-account'),
        'config' => array(
            'regionType'       => 'my_account',
            'login_page_id'    => '{{tab:login}}',
            'show_submissions' => false,
            'staff_page_id'    => '{{tab:staff}}',
        ),
        'tree'   => $widget_root(array(
            $container(array(
                $row(array(
                    $col(array(
                        $el('div', 'p-4 rounded-4 bg-primary-subtle mb-3', lang('Account Card'), array(
                            $para(lang('Welcome back'), 'small text-body-secondary mb-1'),
                            $heading('h1', lang('Jane Cooper'), 'h4 fw-bold mb-1', $bind('__display_name')),
                            $para(lang('jane.cooper@example.com'), 'small text-body-secondary mb-3', $bind('email_address')),
                            $link(lang('Log out'), '#', 'btn btn-sm btn-outline-secondary', array(), array('_bindings' => array('href' => '__logout_url'))),
                        )),
                        $el('nav', 'list-group', lang('Account Menu'), $account_links, array('_attrs' => array($attr('aria-label', lang('My Account'))))),
                    ), '', '4'),
                    $col(array(
                        $el('div', 'p-4 rounded-4 border mb-4', lang('Appointments Card'), array(
                            $el('div', 'd-flex flex-wrap align-items-center justify-content-between gap-3', lang('Title'), array(
                                $el('div', 'd-flex align-items-center gap-3', lang('Words'), array(
                                    $icon('bi-calendar2-check', 'fs-2 text-primary'),
                                    $el('div', '', lang('Text'), array(
                                        $heading('h2', lang('Your appointments'), 'h5 fw-bold mb-1'),
                                        $para(lang('See what you have booked, its status and our messages.'), 'small text-body-secondary mb-0'),
                                    )),
                                )),
                                $el('div', 'd-flex flex-wrap gap-2', lang('Buttons'), array(
                                    $link(lang('My Appointments'), '{{page:my_appointments}}', 'btn btn-primary'),
                                    $link(lang('Book an Appointment'), '{{page:appointment}}', 'btn btn-outline-primary'),
                                )),
                            )),
                        )),
                        $heading('h2', lang('My Orders'), 'h5 fw-bold mb-3', $show_when('has_orders')),
                        $el('div', '', lang('Order History'), array(), array('_bindings' => array('section' => 'order_history'))),
                    ), '', '8'),
                ), '4', '', 'g-lg-5'),
                $loop(array()),
            ), 'py-5'),
        )),
    ),

    // Signing in, up and out, and the password: the layouts their widgets
    // get from the palette.
    'login' => array(
        'page'   => 'login',
        'slug'   => lang('login'),
        'config' => array(
            'regionType'              => 'login_form',
            'show_remember_me'        => true,
            'register_page_id'        => '{{tab:register}}',
            'show_register_link'      => true,
            'forgot_password_page_id' => '{{tab:forgot_password}}',
        ),
        'tree'   => 'starter',
    ),
    'register' => array(
        'page'   => 'register',
        'slug'   => lang('registration'),
        'config' => array(
            'regionType'       => 'registration',
            'show_remember_me' => true,
            'login_page_id'    => '{{tab:login}}',
            'redirect_page_id' => '{{tab:my_account}}',
        ),
        'tree'   => 'starter',
    ),
    'forgot_password' => array(
        'page'   => 'forgot_password',
        'slug'   => lang('forgot-password'),
        'config' => array('regionType' => 'forgot_password', 'login_page_id' => '{{tab:login}}'),
        'tree'   => 'starter',
    ),
    'set_password' => array(
        'page'   => 'set_password',
        'slug'   => lang('set-password'),
        'config' => array('regionType' => 'set_password', 'redirect_page_id' => '{{tab:my_account}}', 'forgot_password_page_id' => '{{tab:forgot_password}}'),
        'tree'   => 'starter',
    ),
    'logout' => array(
        'page'   => 'logout',
        'slug'   => lang('logout'),
        'config' => array('regionType' => 'logout', 'login_page_id' => '{{tab:login}}'),
        'tree'   => 'starter',
    ),
    'profile' => array(
        'page'   => 'profile',
        'slug'   => lang('profile'),
        'config' => array('regionType' => 'account_profile', 'my_account_page_id' => '{{tab:my_account}}'),
        'tree'   => 'starter',
    ),
    'change_password' => array(
        'page'   => 'change_password',
        'slug'   => lang('change-password'),
        'config' => array('regionType' => 'change_password', 'my_account_page_id' => '{{tab:my_account}}'),
        'tree'   => 'starter',
    ),
    'email_preferences' => array(
        'page'   => 'email_preferences',
        'slug'   => lang('email-preferences'),
        'config' => array('regionType' => 'email_preferences', 'my_account_page_id' => '{{tab:my_account}}'),
        'tree'   => 'starter',
    ),
    // The customer's invoices and returns from the ERP, under the account
    // overview on the same page; it draws nothing for a member without an
    // ERP account, so a site that does not use the ERP never sees it.
    'account_invoices' => array(
        'page'   => 'my_account',
        'slug'   => lang('invoices'),
        'config' => array('regionType' => 'account_invoices', 'max_results' => 50),
        'tree'   => 'starter',
    ),
    // Google connection, remembered devices and two-step verification,
    // under the account overview on the same page.
    'account_security' => array(
        'page'   => 'my_account',
        'slug'   => lang('security'),
        'config' => array('regionType' => 'account_security', 'login_page_id' => '{{tab:login}}'),
        'tree'   => 'starter',
    ),
    'address_book' => array(
        'page'     => 'address_book',
        'requires' => 'ecommerce',
        'slug'     => lang('address-book'),
        'config'   => array('regionType' => 'address_book', 'my_account_page_id' => '{{tab:my_account}}'),
        'tree'     => 'starter',
    ),
    // The help center's search: the whole site for ?query=, ten results a
    // page. A result that matches a search section links to that part of
    // its page (^^__result_section^^ names it).
    'site_search' => array(
        'page'   => 'search',
        'slug'   => lang('search'),
        'config' => array(
            'regionType'       => 'search_results',
            'search_scope'     => 'all',
            'results_per_page' => 10,
            'empty_message'    => lang('Nothing matched your search. Try fewer or different words.'),
        ),
        'tree'   => $widget_root(array(
            $el('form', 'mb-4', lang('Search Form'), array(
                $el('div', 'input-group input-group-lg', lang('Search Box'), array(
                    $el('input', 'form-control', '', array(), array(
                        '_attrs'    => array($attr('type', 'search'), $attr('placeholder', lang('Search the site…')), $attr('aria-label', lang('Search the site'))),
                        '_bindings' => array('value' => 'search_query'),
                    )),
                    $el('button', 'btn btn-primary px-4', lang('Search Button'), array($icon('bi-search')), array(
                        '_attrs'    => array($attr('type', 'submit'), $attr('aria-label', lang('Search'))),
                        '_bindings' => array('action' => 'submit_search'),
                    )),
                )),
            ), array('_bindings' => array('action' => 'search_form'))),
            $para(lang('Results heading'), 'fw-semibold pb-3 mb-0 border-bottom', $bind('__results_heading')),
            $el('div', '', lang('Results'), array(
                $loop(array(
                    $el('article', 'py-3 border-bottom position-relative', lang('Result Row'), array(
                        $span(lang('Page'), 'badge bg-secondary-subtle text-secondary-emphasis mb-2', lang('Result Type'), $bind('__result_type_label')),
                        $el('h2', 'h5 fw-bold mb-1 position-static', lang('Result Title'), array(
                            $link(lang('Title'), '#', 'stretched-link link-body-emphasis text-decoration-none', array(),
                                array('_bindings' => array('href' => '__result_url', 'text' => '__result_title'))),
                        )),
                        $para(lang('Test results'), 'small fw-semibold text-primary mb-1', array_merge(array('customName' => lang('Result Section')), $bind('__result_section'))),
                        $para(lang('Excerpt'), 'small text-body-secondary mb-0', $bind('__result_excerpt')),
                    )),
                )),
            )),
            $el('div', 'mt-4', lang('Pagination'), array(), array('_bindings' => array('section' => 'pagination'))),
        )),
    ),

    'error_page' => array(
        'page'   => 'error',
        'slug'   => lang('error'),
        'config' => array('regionType' => 'error_page'),
        'tree'   => 'starter',
    ),

    // The e-mail pages' widgets: drawn for the submission just sent.
    'email_appointment_admin' => array(
        'page'   => 'email_appointment_new',
        'slug'   => lang('form-detail'),
        'config' => array(
            'regionType'          => 'form_item_view',
            'custom_form_page_id' => '{{tab:appointment}}',
            'access_control'      => 'public',
            'not_found_message'   => lang('The appointment request could not be found.'),
        ),
        'tree'   => $widget_root(array(
            $mail_heading(lang('A new appointment request')),
            $mail_para(lang('It came from the appointment form. Confirm the time with the patient, then set the status on the appointment\'s page; what you write under it reaches the patient by e-mail.')),
            $mail_appointment(),
            $mail_box(array(
                $mail_line(lang('Patient'), array(
                    $span(lang('Jane'), '', lang('First Name'), $bind('first_name')),
                    $span(' ', '', lang('Text')),
                    $span(lang('Cooper'), '', lang('Last Name'), $bind('last_name')),
                )),
                $mail_line(lang('Email'), array($span('jane.cooper@example.com', '', lang('Email'), $bind('email')))),
                $mail_line(lang('Phone'), array($span('+90 555 000 00 00', '', lang('Phone'), $bind('phone')))),
                $mail_line(lang('Date of Birth'), array($span('1985-04-12', '', lang('Date'), $bind('date_of_birth')))),
                $mail_line(lang('Insurance'), array($span(lang('Private insurance'), '', lang('Insurance'), $bind('insurance')))),
                $mail_line(lang('Reason for the visit'), array($span(lang('A cough for two weeks that will not go away.'), '', lang('Reason'), $bind('reason')))),
            ), lang('Patient')),
            $mail_button(lang('Open the appointment'), '{{page:appointment_view}}?r=^^reference_code^^'),
            $el('p', 'small text-body-secondary mt-4 mb-0', lang('Reference'), array(
                $span(lang('Reference Code') . ': ', '', lang('Label')),
                $span('ABC123', '', lang('Reference Code'), $bind('reference_code')),
            ), $mail_style('font-size:13px;color:#5b6b69;margin:24px 0 0')),
        )),
    ),
    'email_appointment_patient' => array(
        'page'   => 'email_appointment_received',
        'slug'   => lang('form-detail'),
        'config' => array(
            'regionType'          => 'form_item_view',
            'custom_form_page_id' => '{{tab:appointment}}',
            'access_control'      => 'public',
            'not_found_message'   => lang('The appointment request could not be found.'),
        ),
        'tree'   => $widget_root(array(
            $mail_heading(lang('We received your appointment request')),
            $mail_greeting(),
            $mail_para(lang('Thank you for choosing us. A receptionist checks the calendar and confirms your time by e-mail, usually within two working hours.')),
            $mail_appointment(),
            $mail_para(lang('Please bring your ID card, your insurance card and a list of the medicines you take. Need to change something? Write to us on the appointment\'s page.')),
            $mail_button(lang('Open my appointment'), '{{page:appointment_view}}?r=^^reference_code^^'),
            // The account the form opened for a visitor who had none: the
            // address it signs in with, and how to choose a password (the
            // temporary one is shown on the appointment's page, not mailed).
            $el('div', 'border-top pt-3', lang('New Account'), array(
                $el('p', 'mb-2', lang('Sign-in Email'), array(
                    $span(lang('We opened an account for you with this address:') . ' ', '', lang('Label')),
                    $span('sample@example.com', 'fw-semibold', lang('Value'), $bind('__new_account_email')),
                ), $mail_style('margin:0 0 8px')),
                $el('p', 'mb-0', lang('Password'), array(
                    $span(lang('To choose your password, use') . ' ', '', lang('Label')),
                    $link(lang('Forgot Password'), '{{page:forgot_password}}', ''),
                ), $mail_style('margin:0')),
            ), array_merge($mail_style('border-top:1px solid #d5e7e4;padding-top:16px'), $show_when('has_new_account'))),
        )),
    ),
    'email_message_admin' => array(
        'page'   => 'email_new_message',
        'slug'   => lang('form-detail'),
        'config' => array(
            'regionType'          => 'form_item_view',
            'custom_form_page_id' => '{{tab:contact}}',
            'access_control'      => 'public',
            'not_found_message'   => lang('The message could not be found.'),
        ),
        'tree'   => $widget_root(array(
            $mail_heading(lang('A new message has arrived')),
            $mail_para(lang('It came from the contact form. Answer it on the conversation page: the answer reaches the sender by e-mail and in their inbox.')),
            $mail_box(array(
                $mail_line(lang('Sender'), array(
                    $span(lang('Jane'), '', lang('First Name'), $bind('first_name')),
                    $span(' ', '', lang('Text')),
                    $span(lang('Cooper'), '', lang('Last Name'), $bind('last_name')),
                )),
                $mail_line(lang('Email'), array($span('jane.cooper@example.com', '', lang('Email'), $bind('email')))),
                $mail_line(lang('Phone'), array($span('+90 555 000 00 00', '', lang('Phone'), $bind('phone')))),
                $mail_line(lang('Subject'), array($span(lang('A repeat prescription'), '', lang('Subject'), $bind('topic')))),
            ), lang('Sender')),
            $el('div', 'lh-lg', lang('Message'), array(), array_merge(
                array('text' => lang('Hello, I need a repeat prescription for my blood pressure tablets.')), $bind('message'))),
            $mail_button(lang('Open the conversation'), '{{page:conversation}}?r=^^reference_code^^'),
            $el('p', 'small text-body-secondary mt-4 mb-0', lang('Reference'), array(
                $span(lang('Reference Code') . ': ', '', lang('Label')),
                $span('ABC123', '', lang('Reference Code'), $bind('reference_code')),
            ), $mail_style('font-size:13px;color:#5b6b69;margin:24px 0 0')),
        )),
    ),
    'email_message_sender' => array(
        'page'   => 'email_message_received',
        'slug'   => lang('form-detail'),
        'config' => array(
            'regionType'          => 'form_item_view',
            'custom_form_page_id' => '{{tab:contact}}',
            'access_control'      => 'public',
            'not_found_message'   => lang('The message could not be found.'),
        ),
        'tree'   => $widget_root(array(
            $mail_heading(lang('Your message has reached us')),
            $mail_greeting(),
            $mail_para(lang('Thank you for writing to us. Our answer will be on the conversation page and in your inbox, and we will let you know by e-mail when it is there.')),
            $mail_box(array(
                $el('p', 'fw-semibold mb-2', lang('Subject'), array(), array_merge(
                    array('text' => lang('A repeat prescription'), '_attrs' => array($attr('style', 'font-weight:600;margin:0 0 8px'))), $bind('topic'))),
                $el('div', 'lh-lg', lang('Message'), array(), array_merge(
                    array('text' => lang('Hello, I need a repeat prescription for my blood pressure tablets.')), $bind('message'))),
            ), lang('Your Message')),
            $mail_button(lang('Open the conversation'), '{{page:conversation}}?r=^^reference_code^^'),
            $el('div', 'border-top pt-3', lang('New Account'), array(
                $el('p', 'mb-2', lang('Sign-in Email'), array(
                    $span(lang('We opened an account for you with this address:') . ' ', '', lang('Label')),
                    $span('sample@example.com', 'fw-semibold', lang('Value'), $bind('__new_account_email')),
                ), $mail_style('margin:0 0 8px')),
                $el('p', 'mb-0', lang('Password'), array(
                    $span(lang('To choose your password, use') . ' ', '', lang('Label')),
                    $link(lang('Forgot Password'), '{{page:forgot_password}}', ''),
                ), $mail_style('margin:0')),
            ), array_merge($mail_style('border-top:1px solid #d5e7e4;padding-top:16px'), $show_when('has_new_account'))),
        )),
    ),
);

// ── Search sections ─────────────────────────────────────────────────────
// The parts of the pages the site search points a result at (Search and
// SERP in the editor's options): a result that matches one links to it,
// the words are marked when the visitor arrives, and the page's structured
// data names it. A part is found by its id ('#…') or by the name it has in
// the layer tree, once per page; it keeps an id it already has. The topics
// are words a visitor may search for that the part does not say itself.
// Parts inside a shared component or a widget belong to that, not to the
// page, so only the page's own parts are listed here. The services, the
// doctors and the guide's topics are marked where they are built.
$search_sections = array(
    'checkups' => array(
        lang('Packages')             => array('check-up-packages', lang('check-up, health check, screening, package, price')),
        lang('Before Your Check-up') => array('before-your-check-up', lang('fasting, preparation, what to bring')),
    ),
    'appointment' => array(
        lang('What to Bring') => array('what-to-bring', lang('ID, insurance card, medicines, documents')),
    ),
    'about' => array(
        lang('Our Story') => array('our-story', lang('about us, our story, history, practice')),
        lang('Features')  => array('why-us', lang('why choose us, values, quality')),
    ),
    'contact' => array(
        lang('Contact Details') => array('contact-details', lang('address, phone, e-mail, opening hours, parking, location')),
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

return array(
    'name'        => lang('Clinic'),
    'version'     => '1.0.2',
    'framework'   => 'bootstrap5',
    'order'       => 50,
    'icon'        => 'bi-heart-pulse',
    // The picture on the template's card and beside the designs made from
    // it: a practice's home page (pg_design_thumb_svg()).
    'thumb'       => 'clinic',
    'description' => lang('A local doctors\' practice: services with fees, doctors, check-up packages, online appointment requests the patient follows from their account, a patient guide, a blog, an inbox, a staff area with the coming appointments and a help center that searches the whole site.'),

    // The theme the template is made for; the operator can pick another
    // before opening it, and change it later in the editor.
    'look'        => 'modern-soft',
    'palette'     => 'ocean',

    // What the installer lists on the template's card, after the points
    // every template shares.
    'highlights'  => array(
        lang('An appointment form: each request gets its own page with a status and messages, listed under My Appointments.'),
        lang('Services with their fees, the doctors with their days, and three check-up packages.'),
        lang('A staff area with the coming appointments, the conversations with patients and the blog\'s form.'),
        lang('A patient guide and a help center that search the whole site, and a health letter for e-mail campaigns.'),
    ),

    // Made (or found again) when the template is opened; see _pg_tpl_folders().
    'folders'     => array(
        'root'         => array('name' => 'pinegrap_clinic', 'access' => 'public'),
        'public'       => array('name' => 'public', 'parent' => 'root', 'access' => 'public'),
        'registration' => array('name' => 'registration', 'parent' => 'root', 'access' => 'registration'),
        'private'      => array('name' => 'private', 'parent' => 'root', 'access' => 'private'),
    ),

    'contact_groups' => array(
        'newsletter' => array(
            'name'         => lang('Clinic Newsletter'),
            'description'  => lang('The addresses left on the health letter form of the practice.'),
            'subscription' => true,
        ),
    ),

    'pages'       => $pages,

    'widgets'     => $widgets,

    'shared'      => array(
        'account_bar'  => array('name' => lang('Clinic Account Bar'), 'tree' => $account_bar_tree()),
        'notice'       => array('name' => lang('Clinic Notice'), 'tree' => $notice()),
        'header'       => array('name' => lang('Clinic Header'), 'tree' => $header()),
        'footer'       => array('name' => lang('Clinic Footer'), 'tree' => $footer()),
        'staff_bar'    => array('name' => lang('Clinic Staff Bar'), 'tree' => $staff_bar()),
        'booking_band' => array('name' => lang('Clinic Booking Band'), 'tree' => $booking_band()),
        'email_header' => array('name' => lang('Clinic E-mail Header'), 'tree' =>
            $el('div', 'px-4 py-3 border-bottom', lang('E-mail Header'), array(
                $link('{{site_name}}', '{{page:home}}', 'fs-5 fw-bold text-decoration-none link-body-emphasis',
                    array($attr('style', 'font-size:18px;font-weight:700;color:#0f766e;text-decoration:none'))),
            ), $mail_style('padding:16px 24px;border-bottom:1px solid #d5e7e4'))),
        'email_footer' => array('name' => lang('Clinic E-mail Footer'), 'tree' =>
            $el('div', 'px-4 py-3 border-top small text-body-secondary', lang('E-mail Footer'), array(
                $para('© {{year}} {{site_name}}', 'mb-1', array('_attrs' => array($attr('style', 'margin:0 0 4px')))),
                $para(lang('Medical emergency? Call 112 straight away.'), 'mb-1', array('_attrs' => array($attr('style', 'margin:0 0 4px')))),
                $link(lang('Visit our website'), '{{page:home}}', 'link-secondary',
                    array($attr('style', 'color:#5b6b69'))),
            ), $mail_style('padding:16px 24px;border-top:1px solid #d5e7e4;font-size:13px;color:#5b6b69'))),
    ),
);
