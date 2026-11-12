<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Recruitment Agency", part 1 of its files: the node
 * builders, the agency's own data (the sectors, the cities, the kinds of
 * contract and ways of working, the sample jobs with their salary, duties
 * and requirements, the consultants, how the agency works with candidates
 * and with employers, its services for employers, why people choose it,
 * its figures, what candidates and employers say, and the answers of the
 * forms' pick lists) and the parts every page repeats (account bar,
 * header, footer, call band, job, sector, city, service, consultant and
 * testimonial cards, job facts, page shells, section headers, form fields,
 * the comment thread).
 * The page files of the same folder are included after it in the same
 * scope and build their trees with these variables;
 * includes/design_templates/pinegrap-recruitment.php puts them together.
 *
 * Every structural node carries its role in '_label' (the designer's own
 * label, shown beside the element's tag in the layer tree).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

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
// leaves it out while the site has a single language.
$lang_switcher = function ($class = '', $variant = 'secondary') {
    return array('type' => 'component', 'props' => array(
        'componentType' => 'language_switcher',
        'variant' => $variant, 'outline' => true, 'size' => 'sm',
        'align' => 'lg-end', 'display' => 'name', 'icon' => 'translate',
        'cssClass' => $class,
    ), 'children' => array());
};


// A sample picture with a fixed seed, so the same picture comes back on
// every opening until the agency puts its own photographs in its place.
$photo = function ($seed, $width, $height) {
    return 'https://picsum.photos/seed/pinegrap-recruitment-' . $seed . '/' . (int)$width . '/' . (int)$height;
};
// A sample portrait: initials on the palette's colour. The initials follow
// the name in the site's language.
$portrait = function ($name, $size = 480) {
    $initials = '';
    foreach (array_slice(preg_split('/\s+/u', trim($name)), 0, 2) as $w) $initials .= mb_strtoupper(mb_substr($w, 0, 1, 'UTF-8'), 'UTF-8');
    return 'https://placehold.co/' . (int)$size . 'x' . (int)$size . '/d1fae5/065f46?text=' . rawurlencode($initials !== '' ? $initials : 'R');
};

// ── The agency: its sectors, its cities, its jobs and its people ────────

// The agency itself. The about page tells its story; the footer, the call
// band and the contact lines use the address, phone and office hours.
$agency = array(
    'tagline' => lang('The right job for every candidate, the right people for every employer'),
    'story'   => lang('Three recruiters who were tired of sending CVs nobody read started the agency in a small office with one phone line. Today our consultants place people in six sectors across five cities, and we still work the same way: a consultant who knows the sector meets every candidate, reads every CV and calls back every applicant.'),
    'address' => lang('Business Centre, Career Street 18, Floor 4'),
    'phone'   => '+90 555 000 00 00',
    'email'   => 'jobs@example.com',
    'hours'   => lang('Monday to Friday, 9:00 to 18:00'),
    'licence' => lang('A licensed private employment agency; our service is always free for candidates'),
);

// The sectors the agency recruits for. Each has its own page
// (sector_<key>); the job list opens filtered on it (?sector=) and the
// employer's request opens with it chosen, in the sector's own words.
$sectors = array(
    'technology'  => array('icon' => 'bi-cpu', 'title' => lang('Technology'),
        'summary' => lang('Developers, data people, IT support and product teams.'),
        'text'    => lang('Our technology consultants were developers and IT managers themselves, so they read a CV for what a person has built, not for keywords. We fill roles from first-line support to engineering leads.'),
        'roles'   => array(lang('Software developers'), lang('Data analysts and engineers'), lang('IT support and infrastructure'), lang('Product and project managers'))),
    'finance'     => array('icon' => 'bi-calculator', 'title' => lang('Finance and Accounting'),
        'summary' => lang('Accountants, controllers, payroll and credit teams.'),
        'text'    => lang('From a part-time bookkeeper to a finance director, we place people who keep a business\'s numbers right. Every candidate\'s qualifications are checked before we put them forward.'),
        'roles'   => array(lang('Accountants and bookkeepers'), lang('Financial controllers'), lang('Payroll specialists'), lang('Credit control and collections'))),
    'engineering' => array('icon' => 'bi-gear-wide-connected', 'title' => lang('Engineering and Manufacturing'),
        'summary' => lang('Engineers, technicians and production teams for the factory floor.'),
        'text'    => lang('Manufacturers come to us for engineers who can start on a running line. We know the plants of our region and the shift patterns they work, and we fill a single role or a whole new shift.'),
        'roles'   => array(lang('Mechanical and electrical engineers'), lang('Maintenance technicians'), lang('Quality inspectors'), lang('Production supervisors'))),
    'healthcare'  => array('icon' => 'bi-heart-pulse', 'title' => lang('Healthcare'),
        'summary' => lang('Nurses, care staff and clinic teams for hospitals and care homes.'),
        'text'    => lang('Hospitals, clinics and care homes need staff they can trust from the first shift. We check every licence and reference, and our healthcare team answers the phone when a shift needs covering at short notice.'),
        'roles'   => array(lang('Registered nurses'), lang('Care assistants'), lang('Medical secretaries'), lang('Clinic and laboratory staff'))),
    'sales'       => array('icon' => 'bi-graph-up-arrow', 'title' => lang('Sales and Marketing'),
        'summary' => lang('Sales people, account managers and marketing teams.'),
        'text'    => lang('We find sales people who have hit targets before and marketers who can show their results. Our consultants test every candidate with the kind of conversation they would have with a real customer.'),
        'roles'   => array(lang('Sales executives'), lang('Key account managers'), lang('Digital marketing specialists'), lang('Customer service teams'))),
    'logistics'   => array('icon' => 'bi-truck', 'title' => lang('Logistics and Supply Chain'),
        'summary' => lang('Warehouse, transport and supply chain staff, permanent or temporary.'),
        'text'    => lang('Warehouses and carriers call us for a busy season or a new depot. We supply temporary teams within days and recruit the planners and managers who run the operation all year.'),
        'roles'   => array(lang('Warehouse operatives and supervisors'), lang('Forklift drivers'), lang('Transport planners'), lang('Purchasing and supply chain staff'))),
);

// The cities the agency has an office in, the job list's second filter.
$cities = array(
    'istanbul' => array('title' => lang('Istanbul'), 'text' => lang('Our head office and the most jobs, in every sector.')),
    'ankara'   => array('title' => lang('Ankara'),   'text' => lang('Technology, finance and public sector suppliers.')),
    'izmir'    => array('title' => lang('Izmir'),    'text' => lang('Logistics, finance and the port\'s industries.')),
    'bursa'    => array('title' => lang('Bursa'),    'text' => lang('Engineering and manufacturing for the region\'s plants.')),
    'antalya'  => array('title' => lang('Antalya'),  'text' => lang('Healthcare, hospitality and seasonal work.')),
);

// The kinds of contract and the ways of working, the Jobs form's pick lists.
$contract_types = array('permanent' => lang('Permanent'), 'contract' => lang('Fixed-term contract'), 'temporary' => lang('Temporary'), 'part_time' => lang('Part-time'));
$work_modes = array('on_site' => lang('On-site'), 'hybrid' => lang('Hybrid'), 'remote' => lang('Remote'));

// A sum written as the site writes money: the figure for the site's base
// currency, USD's figure for a currency not listed, with the base
// currency's symbol (the jobs' salaries).
$base_currency = defined('BASE_CURRENCY_CODE') ? strtoupper((string)BASE_CURRENCY_CODE) : 'USD';
$money = function (array $prices) use ($base_currency) {
    $amount = isset($prices[$base_currency]) ? $prices[$base_currency] : $prices['USD'];
    return pg_money_text($amount, defined('BASE_CURRENCY_SYMBOL') ? (string)BASE_CURRENCY_SYMBOL : '$');
};
// A monthly salary band in words, the value of a job's Salary field.
$salary = function (array $low, array $high) use ($money) {
    return sprintf(lang('%1$s to %2$s a month'), $money($low), $money($high));
};

// The agency's consultants (the team page, the about page and a job's
// page): one for each sector. Fixed data, not a collection.
$consultants = array(
    array('name' => lang('Elif Kaya'), 'role' => lang('Technology Recruitment Consultant'), 'sector' => 'technology',
        'bio' => lang('A former developer who has placed engineers in start-ups and banks for ten years.')),
    array('name' => lang('Mehmet Arslan'), 'role' => lang('Finance Recruitment Consultant'), 'sector' => 'finance',
        'bio' => lang('A qualified accountant who knows what a finance team needs from its next hire.')),
    array('name' => lang('Ayşe Yıldız'), 'role' => lang('Engineering Recruitment Consultant'), 'sector' => 'engineering',
        'bio' => lang('Spent eight years in a car plant\'s human resources team before joining us.')),
    array('name' => lang('Sarah Mitchell'), 'role' => lang('Healthcare Recruitment Consultant'), 'sector' => 'healthcare',
        'bio' => lang('A nurse for twelve years, now finding nurses and carers for hospitals and care homes.')),
    array('name' => lang('Burak Çelik'), 'role' => lang('Sales Recruitment Consultant'), 'sector' => 'sales',
        'bio' => lang('Led sales teams before he started recruiting them, and still loves a good pitch.')),
    array('name' => lang('Zeynep Aksoy'), 'role' => lang('Logistics Recruitment Consultant'), 'sector' => 'logistics',
        'bio' => lang('Fills a warehouse shift in a day and a supply chain director in a month.')),
);
// The consultant of a sector, by the sector's key.
$consultant_of = function ($sector) use ($consultants) {
    foreach ($consultants as $c) {
        if ($c['sector'] === $sector) return $c;
    }
    return $consultants[0];
};

// The sample jobs: the records the Jobs form starts with. The employer is
// described, not named, as agencies do; the salary is a monthly band. Each
// job's page opens the application with the job chosen (?job=).
$jobs = array(
    'software_developer' => array('title' => lang('Senior Software Developer'), 'sector' => 'technology', 'city' => 'istanbul',
        'type' => 'permanent', 'mode' => 'hybrid', 'employer' => lang('A fast-growing online payments company'),
        'salary' => $salary(array('USD' => 3500, 'EUR' => 3200, 'GBP' => 2800, 'TRY' => 120000), array('USD' => 5000, 'EUR' => 4600, 'GBP' => 4000, 'TRY' => 170000)),
        'summary' => lang('Build and run the services behind a payments app used by a million people, in a team of eight developers.'),
        'duties'  => array(lang('Design, build and test new features'), lang('Review your colleagues\' code'), lang('Keep the services fast and secure'), lang('Help the junior developers grow')),
        'requirements' => array(lang('Five years of building web applications'), lang('Experience with a modern back-end language'), lang('Good knowledge of SQL databases'), lang('Written and spoken English')),
        'featured' => true),
    'data_analyst' => array('title' => lang('Data Analyst'), 'sector' => 'technology', 'city' => 'ankara',
        'type' => 'contract', 'mode' => 'remote', 'employer' => lang('A national retail chain'),
        'salary' => $salary(array('USD' => 2200, 'EUR' => 2000, 'GBP' => 1750, 'TRY' => 75000), array('USD' => 3000, 'EUR' => 2750, 'GBP' => 2400, 'TRY' => 100000)),
        'summary' => lang('Turn the sales and stock data of two hundred stores into reports the buyers use every week.'),
        'duties'  => array(lang('Build and keep the weekly sales reports'), lang('Answer the buyers\' questions with data'), lang('Clean and join data from several systems'), lang('Present what you find to the managers')),
        'requirements' => array(lang('Two years of work with data'), lang('Strong SQL and spreadsheet skills'), lang('A reporting tool such as Power BI or Tableau'), lang('Clear writing for people who are not analysts')),
        'featured' => false),
    'accountant' => array('title' => lang('Accountant'), 'sector' => 'finance', 'city' => 'izmir',
        'type' => 'permanent', 'mode' => 'on_site', 'employer' => lang('A family-owned food exporter'),
        'salary' => $salary(array('USD' => 1800, 'EUR' => 1650, 'GBP' => 1450, 'TRY' => 60000), array('USD' => 2400, 'EUR' => 2200, 'GBP' => 1900, 'TRY' => 80000)),
        'summary' => lang('Keep the books of a growing exporter and prepare the monthly accounts with the finance manager.'),
        'duties'  => array(lang('Post invoices, payments and bank entries'), lang('Prepare the monthly accounts'), lang('Work with the tax advisers on returns'), lang('Follow up the export payments')),
        'requirements' => array(lang('A degree in accounting or finance'), lang('Three years in an accounts team'), lang('Experience with an accounting system'), lang('Some knowledge of export paperwork')),
        'featured' => true),
    'mechanical_engineer' => array('title' => lang('Mechanical Engineer'), 'sector' => 'engineering', 'city' => 'bursa',
        'type' => 'permanent', 'mode' => 'on_site', 'employer' => lang('A supplier of parts to the car industry'),
        'salary' => $salary(array('USD' => 2000, 'EUR' => 1850, 'GBP' => 1600, 'TRY' => 68000), array('USD' => 2800, 'EUR' => 2550, 'GBP' => 2250, 'TRY' => 95000)),
        'summary' => lang('Improve the production lines of a parts plant and lead the projects that bring in new machines.'),
        'duties'  => array(lang('Plan and run improvement projects on the lines'), lang('Bring new machines into production'), lang('Solve breakdowns with the maintenance team'), lang('Keep the technical drawings up to date')),
        'requirements' => array(lang('A degree in mechanical engineering'), lang('Three years in a manufacturing plant'), lang('A CAD program'), lang('Willing to work with the shift teams')),
        'featured' => true),
    'staff_nurse' => array('title' => lang('Staff Nurse'), 'sector' => 'healthcare', 'city' => 'antalya',
        'type' => 'permanent', 'mode' => 'on_site', 'employer' => lang('A private hospital'),
        'salary' => $salary(array('USD' => 1500, 'EUR' => 1400, 'GBP' => 1200, 'TRY' => 50000), array('USD' => 2000, 'EUR' => 1850, 'GBP' => 1600, 'TRY' => 68000)),
        'summary' => lang('Care for the patients of a surgical ward in a modern private hospital with international patients.'),
        'duties'  => array(lang('Plan and give the care of your patients'), lang('Give medicines and keep the records'), lang('Work with the doctors on the ward rounds'), lang('Support the patients and their families')),
        'requirements' => array(lang('A nursing degree and a licence to practise'), lang('One year on a hospital ward'), lang('Willing to work shifts'), lang('English or another foreign language is a plus')),
        'featured' => false),
    'sales_executive' => array('title' => lang('Sales Executive'), 'sector' => 'sales', 'city' => 'istanbul',
        'type' => 'permanent', 'mode' => 'hybrid', 'employer' => lang('A business software company'),
        'salary' => $salary(array('USD' => 1600, 'EUR' => 1500, 'GBP' => 1300, 'TRY' => 55000), array('USD' => 2600, 'EUR' => 2400, 'GBP' => 2100, 'TRY' => 88000)),
        'summary' => lang('Win new business customers for a software company, with a base salary and a commission on every sale.'),
        'duties'  => array(lang('Find and call new business customers'), lang('Show the software to the customers'), lang('Prepare offers and close the sales'), lang('Keep the customer records up to date')),
        'requirements' => array(lang('Two years of selling to businesses'), lang('A record of meeting targets'), lang('A driving licence'), lang('Confident on the phone and in meetings')),
        'featured' => false),
    'warehouse_supervisor' => array('title' => lang('Warehouse Shift Supervisor'), 'sector' => 'logistics', 'city' => 'izmir',
        'type' => 'temporary', 'mode' => 'on_site', 'employer' => lang('An international logistics company'),
        'salary' => $salary(array('USD' => 1200, 'EUR' => 1100, 'GBP' => 950, 'TRY' => 40000), array('USD' => 1500, 'EUR' => 1400, 'GBP' => 1200, 'TRY' => 50000)),
        'summary' => lang('Lead a shift of twenty people through the busy season in a large distribution centre.'),
        'duties'  => array(lang('Plan the shift and give out the work'), lang('Keep the team safe and on target'), lang('Check the goods in and out'), lang('Report the shift\'s results to the manager')),
        'requirements' => array(lang('Two years in a warehouse, one as a team leader'), lang('A forklift licence is a plus'), lang('Willing to work nights'), lang('Experience with a warehouse system')),
        'featured' => false),
    'marketing_assistant' => array('title' => lang('Marketing Assistant'), 'sector' => 'sales', 'city' => 'ankara',
        'type' => 'part_time', 'mode' => 'hybrid', 'employer' => lang('A chain of language schools'),
        'salary' => $salary(array('USD' => 700, 'EUR' => 650, 'GBP' => 560, 'TRY' => 24000), array('USD' => 900, 'EUR' => 830, 'GBP' => 720, 'TRY' => 30000)),
        'summary' => lang('Help the marketing team with social media, events and the newsletter, twenty hours a week.'),
        'duties'  => array(lang('Write and plan the social media posts'), lang('Help at open days and events'), lang('Prepare the monthly newsletter'), lang('Report the results of each campaign')),
        'requirements' => array(lang('A student or graduate of marketing or communication'), lang('Good writing skills'), lang('Some experience with social media for a business'), lang('Free for twenty hours a week')),
        'featured' => false),
);
// A job's duties and requirements as the value of its long-text fields:
// one line per item.
$duties_text = function ($key) use ($jobs) {
    return implode("\n", $jobs[$key]['duties']);
};
$requirements_text = function ($key) use ($jobs) {
    return implode("\n", $jobs[$key]['requirements']);
};

// How the agency works with a candidate, from the CV to the first day (the
// candidates page and the home).
$candidate_steps = array(
    array('title' => lang('Send your CV'), 'text' => lang('Apply for a job or send an open application. Every CV is read by a consultant, never only by a program.')),
    array('title' => lang('Meet your consultant'), 'text' => lang('A consultant who knows your sector calls you to talk about what you want next and what you are good at.')),
    array('title' => lang('Interviews'), 'text' => lang('We put you forward only for jobs you agreed to, prepare you for each interview and tell you how it went.')),
    array('title' => lang('Offer and first day'), 'text' => lang('We talk the offer through with you and stay in touch through your first months in the new job.')),
);

// How the agency works with an employer, from the call to the placement
// (the employers page and the home).
$employer_steps = array(
    array('title' => lang('Tell us the role'), 'text' => lang('Send a staff request or call us. A consultant learns about the role, the team and what success looks like.')),
    array('title' => lang('A shortlist in five working days'), 'text' => lang('We search, interview and check references, and send you the three to five best candidates.')),
    array('title' => lang('Interviews'), 'text' => lang('We arrange the interviews, collect the feedback from both sides and handle the offer.')),
    array('title' => lang('Placement and follow-up'), 'text' => lang('If a permanent hire leaves within three months, we find a replacement free of charge.')),
);

// What the agency offers employers (the employers page and the home).
$employer_services = array(
    'permanent' => array('icon' => 'bi-person-check', 'title' => lang('Permanent Recruitment'),
        'summary' => lang('We find, interview and shortlist the right people for your permanent roles.'),
        'points'  => array(lang('A fee only when a candidate starts'), lang('A shortlist in five working days'), lang('A free replacement within three months'))),
    'temporary' => array('icon' => 'bi-clock-history', 'title' => lang('Temporary Staff'),
        'summary' => lang('Checked and ready temporary staff for a busy season, a project or a sudden gap.'),
        'points'  => array(lang('Staff ready within two working days'), lang('We handle the contracts and payroll'), lang('One invoice for all your temporary staff'))),
    'executive' => array('icon' => 'bi-award', 'title' => lang('Executive Search'),
        'summary' => lang('A discreet search for directors and senior managers, approaching people who are not looking.'),
        'points'  => array(lang('A search plan agreed with you'), lang('Confidential approaches'), lang('Detailed reports on every candidate'))),
    'volume' => array('icon' => 'bi-people', 'title' => lang('Volume Hiring'),
        'summary' => lang('Many people for a new site, a new shift or a new store, recruited together.'),
        'points'  => array(lang('Assessment days we organise for you'), lang('One project manager for the whole hire'), lang('Weekly reports on the progress'))),
);

// Why candidates and employers choose the agency (the home and the about
// page).
$why_us = array(
    array('icon' => 'bi-person-badge', 'title' => lang('Consultants who know the sector'),
        'text' => lang('Each sector has its own consultant who has worked in it.')),
    array('icon' => 'bi-telephone', 'title' => lang('Every applicant hears back'),
        'text' => lang('We answer every application, even when the answer is no.')),
    array('icon' => 'bi-shield-check', 'title' => lang('Checked candidates'),
        'text' => lang('Qualifications and references are checked before we put anyone forward.')),
    array('icon' => 'bi-wallet2', 'title' => lang('Free for candidates'),
        'text' => lang('Candidates never pay us anything; employers pay when someone starts.')),
);

// The agency in four figures (the home and the about page).
$figures = array(
    array('value' => '2,400+', 'label' => lang('People placed last year')),
    array('value' => '650', 'label' => lang('Employers who hire with us')),
    array('value' => '6', 'label' => lang('Specialist sectors')),
    array('value' => '5', 'label' => lang('City offices')),
);

// What candidates and employers say.
$testimonials = array(
    array('kind' => 'candidate', 'name' => lang('Canan Öztürk'), 'role' => lang('Placed as an accountant'),
        'quote' => lang('My consultant called me back the same day, prepared me for every interview and I started my new job three weeks later.')),
    array('kind' => 'employer', 'name' => lang('David Brooks'), 'role' => lang('Operations Director, a logistics company'),
        'quote' => lang('They found forty temporary staff for our new depot in a week, and half of them are still with us.')),
    array('kind' => 'candidate', 'name' => lang('Emre Doğan'), 'role' => lang('Placed as a software developer'),
        'quote' => lang('For once a recruiter understood what I actually do. Every job they sent me was one I wanted to talk about.')),
    array('kind' => 'employer', 'name' => lang('Selin Yılmaz'), 'role' => lang('Head of People, a private hospital'),
        'quote' => lang('Every nurse they sent us had their licence and references checked, which saves us days on every hire.')),
);
$testimonial_kinds = array('candidate' => lang('Candidate'), 'employer' => lang('Employer'));

// The answers of the forms' pick lists. Each answer's value is its own
// words, so the e-mails and the lists read as the person chose.
$sector_titles = array();
foreach ($sectors as $s) $sector_titles[] = $s['title'];
$city_titles = array();
foreach ($cities as $c) $city_titles[] = $c['title'];
$contract_titles = array_values($contract_types);
$mode_titles = array_values($work_modes);
$consultant_names = array();
foreach ($consultants as $c) $consultant_names[] = $c['name'];
$job_titles = array();
foreach ($jobs as $j) $job_titles[] = $j['title'];
$application_job_choices = array_merge($job_titles, array(lang('Open application')));
$application_sector_choices = array_merge($sector_titles, array(lang('Another sector')));
$application_city_choices = array_merge($city_titles, array(lang('Anywhere, or remote')));
$notice_choices = array(lang('Available now'), lang('One week'), lang('Two weeks'), lang('One month'), lang('Two months or more'));
// The states of a job application, set by the agency in the panel.
$application_statuses = array(lang('Application received'), lang('Shortlisted'), lang('Interview'), lang('Offer made'), lang('Placed'), lang('Not this time'));
$enquiry_sector_choices = array_merge($sector_titles, array(lang('Another sector')));
$enquiry_service_choices = array();
foreach ($employer_services as $s) $enquiry_service_choices[] = $s['title'];
$headcount_choices = array(lang('One person'), lang('Two to five people'), lang('Six to twenty people'), lang('More than twenty people'));
$start_choices = array(lang('As soon as possible'), lang('Within a month'), lang('Within three months'), lang('Not decided yet'));
// The states of an employer's staff request, set by the agency in the panel.
$enquiry_statuses = array(lang('Request received'), lang('Call booked'), lang('Shortlist sent'), lang('Role filled'), lang('Closed'));
$contact_choices = array(
    array(lang('E-mail'), lang('E-mail')),
    array(lang('Phone'), lang('Phone')),
);

// The query string of a link that opens a list with a filter set
// (?sector=, ?city=, ?type=) or a form with a choice made (?job=,
// ?sector=): the form's fields start with these values (_cf.url_param).
$query = function ($params) {
    return http_build_query($params, '', '&', PHP_QUERY_RFC3986);
};
// A page: the account bar, the header, the page's own content and the
// shared footer, with the call band above the footer unless the page is a
// form or an account page.
$page = function ($main, $band = true) use ($root, $shared, $el) {
    $children = array(
        $shared('account_bar'),
        $shared('header'),
        $el('main', '', lang('Main Content'), $main),
    );
    if ($band) $children[] = $shared('call_band');
    $children[] = $shared('footer');
    return $root($children);
};

// The top of the inner pages: where the page sits, its title and one
// sentence, and whatever the page adds under them.
$page_header = function ($title, $lead, $more = array()) use ($el, $heading, $para, $link, $container, $attr) {
    return $el('header', 'py-4 py-lg-5 border-bottom bg-body-tertiary', lang('Page Header'), array(
        $container(array_merge(array(
            $el('nav', '', lang('Breadcrumb'), array(
                $el('ol', 'breadcrumb small mb-2', lang('Breadcrumb List'), array(
                    $el('li', 'breadcrumb-item', lang('Breadcrumb Item'), array($link(lang('Homepage'), '{{page:home}}', 'link-secondary text-decoration-none'))),
                    $el('li', 'breadcrumb-item active', lang('Breadcrumb Item'), array(), array('text' => $title, '_attrs' => array($attr('aria-current', 'page')))),
                )),
            ), array('_attrs' => array($attr('aria-label', lang('Breadcrumb'))))),
            $heading('h1', $title, 'prc-display display-6 mb-2'),
            $para($lead, 'lead text-body-secondary mb-0'),
        ), $more)),
    ));
};

// The heading over a section: a small line above, the title, a link to
// the rest, and a rule under them.
$section_header = function ($eyebrow, $title, $more_text = '', $more_href = '') use ($el, $heading, $para, $icon, $span) {
    $children = array($el('div', '', lang('Section Title'), array(
        $para($eyebrow, 'prc-eyebrow text-uppercase fw-semibold text-primary mb-1'),
        $heading('h2', $title, 'prc-display h3 mb-0'),
    )));
    if ($more_text !== '') {
        $children[] = $el('a', 'd-inline-flex align-items-center gap-1 fw-semibold link-primary text-decoration-none text-nowrap', lang('Link'), array(
            $span($more_text, '', lang('Link Text')),
            $icon('bi-arrow-right'),
        ), array('href' => $more_href));
    }
    return $el('div', 'd-flex flex-wrap align-items-end justify-content-between gap-2 pb-3 mb-4 border-bottom', lang('Section Header'), $children);
};

// An icon in a soft round tile, the way the sectors, services and why-us points are marked.
$icon_tile = function ($name, $class = '') use ($el, $icon) {
    return $el('span', trim('d-inline-flex align-items-center justify-content-center rounded-circle bg-primary-subtle text-primary-emphasis p-3 lh-1 ' . $class), lang('Icon Box'), array(
        $icon($name, 'fs-4'),
    ));
};

// Five stars, for what candidates and employers say.
$stars = function () use ($el, $icon, $span) {
    return $el('p', 'd-flex gap-1 text-warning mb-2', lang('Rating'), array(
        $span(lang('Five out of five stars'), 'visually-hidden', lang('Text')),
        $icon('bi-star-fill'), $icon('bi-star-fill'), $icon('bi-star-fill'), $icon('bi-star-fill'), $icon('bi-star-fill'),
    ));
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

// A check list: a tick and the words on each line.
$check_list = function ($items, $class = 'list-unstyled mb-0') use ($el, $icon, $span) {
    $children = array();
    $last = count($items) - 1;
    foreach (array_values($items) as $n => $text) {
        $children[] = $el('li', 'd-flex gap-2 ' . ($n === $last ? 'mb-0' : 'mb-2'), lang('List Item'), array(
            $icon('bi-check2', 'text-primary'),
            $span($text, '', lang('Text')),
        ));
    }
    return $el('ul', $class, lang('Check List'), $children);
};

// A question: a search section of its own, so a search that matches it
// opens the page at the question (the marking opens the answer).
$faq = function ($n, $question, $answer, $topics = '', $parent = 'rc-faq') use ($el, $para, $attr, $searchable) {
    $id = $parent . '-' . $n;
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


// The agency's figures in a row: the number large, its meaning under it.
$figure_row = function ($class = '') use ($el, $span, $col, $row, $figures) {
    $cols = array();
    foreach ($figures as $f) {
        $cols[] = $col(array(
            $el('div', 'text-center', lang('Key Figure'), array(
                $span($f['value'], 'prc-stat d-block display-5 fw-bold text-primary', lang('Value')),
                $span($f['label'], 'd-block small text-body-secondary', lang('Label')),
            )),
        ), '3', '', '', '6');
    }
    return $row($cols, '4', '', $class);
};

// ── The parts every page repeats ────────────────────────────────────────

// The line in the account bar: the service is free for candidates, and the
// way to send a CV. A shared component.
$notice = function () use ($el, $icon, $span, $link) {
    return $el('p', 'd-flex flex-wrap align-items-center gap-2 mb-0', lang('Notice'), array(
        $icon('bi-wallet2', 'text-primary'),
        $span(lang('Free for candidates: we never charge you for finding a job'), '', lang('Text')),
        $link(lang('Send Your CV'), '{{page:apply}}', 'link-primary fw-semibold'),
    ));
};

// The bar above the header: the notice on the left, the language switcher
// and the login region on the right. Every page places it as one shared
// component, so a change to it is made once for the whole site. The agency
// sells nothing online, so the bar carries no cart.
$account_bar_tree = function () use ($el, $widget, $shared, $lang_switcher) {
    return $el('div', 'border-bottom small bg-body-tertiary', lang('Account Bar'), array(
        $el('div', 'container d-flex flex-wrap align-items-center justify-content-between gap-2 py-1', lang('Account Area'), array(
            $el('div', '', lang('Announcement Area'), array($shared('notice'))),
            $el('div', 'd-flex align-items-center gap-2 ms-auto', lang('Account Links'), array(
                $lang_switcher(), $widget('login_region'),
            )),
        )),
    ));
};

// The header: the agency's name, the menu and the two ways in, one for
// employers and one for candidates. The links to the current page are
// marked active on the server (smart active state), so one header serves
// every page.
$header = function () use ($el, $link, $icon, $span, $attr) {
    $items = array();
    foreach (array(
        array('jobs', lang('Find a Job')),
        array('sectors', lang('Sectors')),
        array('candidates', lang('Candidates')),
        array('employers', lang('Employers')),
        array('about', lang('About Us')),
        array('blog', lang('Career Advice')),
    ) as $n) {
        $items[] = $el('li', 'nav-item', lang('Nav Item'), array($link($n[1], '{{page:' . $n[0] . '}}', 'nav-link text-nowrap')));
    }
    return $el('header', 'bg-body border-bottom', lang('Site Header'), array(
        $el('nav', 'navbar navbar-expand-xl py-3', lang('Navbar'), array(
            $el('div', 'container flex-xl-wrap', lang('Navbar Container'), array(
                $el('a', 'navbar-brand d-flex align-items-center gap-2', lang('Brand'), array(
                    $icon('bi-person-workspace', 'fs-4 text-primary'),
                    $span('{{site_name}}', 'prc-brand fs-4', lang('Name')),
                ), array('href' => '{{page:home}}')),
                $el('button', 'navbar-toggler', lang('Toggler'), array(
                    $el('span', 'navbar-toggler-icon', lang('Toggler Icon')),
                ), array('_attrs' => array(
                    $attr('type', 'button'),
                    $attr('data-bs-toggle', 'collapse'),
                    $attr('data-bs-target', '#rc-nav'),
                    $attr('aria-controls', 'rc-nav'),
                    $attr('aria-expanded', 'false'),
                    $attr('aria-label', lang('Toggle navigation')),
                ))),
                $el('div', 'collapse navbar-collapse', lang('Nav Links'), array(
                    $el('ul', 'navbar-nav prc-nav ms-xl-auto me-xl-2 me-xxl-3 column-gap-xxl-2 py-3 py-xl-0', lang('Nav Menu'), $items, array('smartActive' => true)),
                    $el('div', 'd-flex flex-column flex-xl-row align-items-xl-center gap-2 pb-2 pb-xl-0', lang('Header Actions'), array(
                        $link(lang('Hire Staff'), '{{page:hire}}', 'btn btn-outline-primary d-xl-none px-4 text-nowrap'),
                        $el('a', 'btn btn-primary d-inline-flex align-items-center justify-content-center gap-2 px-4 px-xl-3 px-xxl-4 text-nowrap', lang('Call to Action'), array(
                            $icon('bi-file-earmark-person'),
                            $span(lang('Send Your CV'), '', lang('Link Text')),
                        ), array('href' => '{{page:apply}}')),
                    )),
                ), array('id' => 'rc-nav')),
            )),
        ), array('_attrs' => array($attr('aria-label', lang('Main navigation'))))),
    ));
};

// The footer, on a dark band: the agency in a sentence, how to reach it
// and its profiles, the jobs by sector and by city, the candidates' pages
// and the employers' pages; at the bottom the year, the site's name, the
// agency's own pages and its licence.
$footer = function () use ($el, $para, $link, $icon, $span, $heading, $sectors, $cities, $agency, $query, $attr) {
    $plain = 'link-body-emphasis text-decoration-none';
    $list = function ($links) use ($el) {
        $items = array();
        foreach ($links as $l) $items[] = $el('li', 'mb-2', lang('List Item'), array($l));
        return $el('ul', 'list-unstyled small mb-0', lang('Links'), $items);
    };
    $sector_links = array();
    foreach ($sectors as $s) $sector_links[] = $link($s['title'], '{{page:jobs}}?' . $query(array('sector' => $s['title'])), $plain);
    $city_links = array($link(lang('All Jobs'), '{{page:jobs}}', $plain));
    foreach ($cities as $c) $city_links[] = $link($c['title'], '{{page:jobs}}?' . $query(array('city' => $c['title'])), $plain);
    $social = function ($name, $label) use ($el, $icon, $attr) {
        return $el('a', 'link-body-emphasis fs-5', $label, array($icon($name)), array('href' => '#', '_attrs' => array($attr('aria-label', $label))));
    };
    $contact_line = function ($icon_name, $child, $name) use ($el, $icon) {
        return $el('p', 'd-flex align-items-start gap-2 small mb-2', $name, array($icon($icon_name, 'text-primary'), $child));
    };

    return $el('footer', 'mt-auto pt-5 pb-4 bg-body text-body', lang('Site Footer'), array(
        $el('div', 'container', lang('Footer Container'), array(
            $el('div', 'row g-4', lang('Footer Columns'), array(
                $el('div', 'col-12 col-lg-4', lang('About the Agency'), array(
                    $el('a', 'd-inline-flex align-items-center gap-2 fs-4 mb-3 ' . $plain, lang('Brand'), array(
                        $icon('bi-person-workspace', 'text-primary'),
                        $span('{{site_name}}', 'prc-brand', lang('Name')),
                    ), array('href' => '{{page:home}}')),
                    $para($agency['tagline'], 'small text-body-secondary mb-3'),
                    $el('div', '', lang('Contact Details'), array(
                        $contact_line('bi-geo-alt', $link($agency['address'], '{{page:contact}}', $plain), lang('Address')),
                        $contact_line('bi-telephone', $link($agency['phone'], 'tel:' . preg_replace('/[^0-9+]/', '', $agency['phone']), $plain), lang('Phone')),
                        $contact_line('bi-envelope', $link($agency['email'], 'mailto:' . $agency['email'], $plain . ' text-break'), lang('Email')),
                        $contact_line('bi-clock', $span($agency['hours'], '', lang('Text')), lang('Office Hours')),
                    )),
                    $el('div', 'd-flex gap-3 mt-3', lang('Social Links'), array(
                        $social('bi-linkedin', 'LinkedIn'),
                        $social('bi-instagram', 'Instagram'),
                        $social('bi-facebook', 'Facebook'),
                        $social('bi-twitter-x', 'X'),
                    )),
                )),
                $el('div', 'col-6 col-md-3 col-lg-2', lang('Jobs by Sector'), array(
                    $heading('h2', lang('Jobs by Sector'), 'h6 text-uppercase small fw-bold mb-3'),
                    $list($sector_links),
                )),
                $el('div', 'col-6 col-md-3 col-lg-2', lang('Jobs by City'), array(
                    $heading('h2', lang('Jobs by City'), 'h6 text-uppercase small fw-bold mb-3'),
                    $list($city_links),
                )),
                $el('div', 'col-6 col-md-3 col-lg-2', lang('Candidates'), array(
                    $heading('h2', lang('For Candidates'), 'h6 text-uppercase small fw-bold mb-3'),
                    $list(array(
                        $link(lang('Find a Job'), '{{page:jobs}}', $plain),
                        $link(lang('Send Your CV'), '{{page:apply}}', $plain),
                        $link(lang('My Applications'), '{{page:my_applications}}', $plain),
                        $link(lang('How It Works'), '{{page:candidates}}', $plain),
                        $link(lang('Career Advice'), '{{page:blog}}', $plain),
                        $link(lang('Job Alerts'), '{{page:newsletter}}', $plain),
                        $link(lang('My Account'), '{{page:my_account}}', $plain),
                    )),
                )),
                $el('div', 'col-6 col-md-3 col-lg-2', lang('Employers'), array(
                    $heading('h2', lang('For Employers'), 'h6 text-uppercase small fw-bold mb-3'),
                    $list(array(
                        $link(lang('Hire Staff'), '{{page:hire}}', $plain),
                        $link(lang('Our Services'), '{{page:employers}}', $plain),
                        $link(lang('My Staff Requests'), '{{page:my_enquiries}}', $plain),
                        $link(lang('Our Consultants'), '{{page:team}}', $plain),
                        $link(lang('Cities'), '{{page:cities}}', $plain),
                        $link(lang('FAQ'), '{{page:faq}}', $plain),
                    )),
                )),
            )),
            $el('div', 'd-flex flex-column flex-md-row justify-content-between align-items-center gap-3 border-top mt-4 pt-4', lang('Footer Bottom'), array(
                $para('© {{year}} {{site_name}}', 'small text-body-secondary mb-0'),
                $el('nav', 'd-flex flex-wrap justify-content-center gap-3 small', lang('Footer Links'), array(
                    $link(lang('About Us'), '{{page:about}}', $plain),
                    $link(lang('Contact Us'), '{{page:contact}}', $plain),
                    $link(lang('Privacy Policy'), '{{page:privacy}}', $plain),
                ), array('_attrs' => array($attr('aria-label', lang('Footer Links'))))),
                $para($agency['licence'], 'small text-body-secondary mb-0'),
            )),
        )),
    ), array('_attrs' => array($attr('data-bs-theme', 'dark'))));
};

// The call band: a full-width band in the palette's colour with a way in
// for each side, the candidate's and the employer's. Shared, so every page
// that ends with it carries the same call.
$call_band = function () use ($el, $heading, $para, $link, $container, $row, $col) {
    return $el('section', 'py-5 text-bg-primary', lang('Call to Action'), array(
        $container(array(
            $row(array(
                $col(array(
                    $heading('h2', lang('Looking for your next job, or your next hire?'), 'prc-display h2 mb-2'),
                    $para(lang('Candidates send a CV for free and hear back from a consultant. Employers receive a shortlist within five working days.'), 'mb-0'),
                ), '', '7'),
                $col(array(
                    $el('div', 'd-flex flex-wrap gap-2 justify-content-lg-end', lang('Buttons'), array(
                        $link(lang('Find a Job'), '{{page:jobs}}', 'btn btn-secondary btn-lg px-4'),
                        $link(lang('Hire Staff'), '{{page:hire}}', 'btn btn-link btn-lg text-reset'),
                    )),
                ), '', '5'),
            ), '4', '', 'align-items-center'),
        )),
    ));
};

// The facts of a job in one list: sector, city, contract type, work mode
// and salary, each bound to the Jobs form's field of the same name.
$job_facts = function ($sample_key = 'software_developer', $class = 'd-flex flex-wrap gap-3 small text-body-secondary mb-0') use ($el, $icon, $span, $bind, $jobs, $sectors, $cities, $contract_types, $work_modes) {
    $j = $jobs[$sample_key];
    $fact = function ($icon_name, $text, $token, $name, $text_class = '') use ($el, $icon, $span, $bind) {
        return $el('li', 'd-inline-flex align-items-center gap-1', $name, array(
            $icon($icon_name, 'text-primary'),
            $span($text, $text_class, $name, $bind($token)),
        ));
    };
    return $el('ul', 'list-unstyled ' . $class, lang('Job Facts'), array(
        $fact($sectors[$j['sector']]['icon'], $sectors[$j['sector']]['title'], 'sector', lang('Sector')),
        $fact('bi-geo-alt', $cities[$j['city']]['title'], 'city', lang('City')),
        $fact('bi-briefcase', $contract_types[$j['type']], 'contract_type', lang('Contract Type')),
        $fact('bi-laptop', $work_modes[$j['mode']], 'work_mode', lang('Work Mode')),
        $fact('bi-cash-stack', $j['salary'], 'salary', lang('Salary'), 'prc-salary fw-semibold text-body-emphasis'),
    ));
};

// A job's card of the lists (the home, the job list and a sector's page):
// the sector, the title linking the job's page, the employer, the summary,
// and on the side the city, the contract type, the work mode and the
// salary. Every value is bound to the Jobs form's field of the same name;
// the sample fills the canvas.
$job_card = function ($sample_key = 'software_developer') use ($el, $para, $span, $link, $icon, $bind, $jobs, $sectors, $cities, $contract_types, $work_modes) {
    $j = $jobs[$sample_key];
    $fact = function ($icon_name, $text, $token, $name, $text_class = '') use ($el, $icon, $span, $bind) {
        return $el('li', 'd-inline-flex align-items-center gap-1', $name, array(
            $icon($icon_name, 'text-primary'),
            $span($text, $text_class, $name, $bind($token)),
        ));
    };
    return $el('article', 'card border-0 shadow-sm rounded-4 mb-3 prc-lift', lang('Job Card'), array(
        $el('div', 'card-body d-flex flex-column flex-md-row align-items-md-center gap-3 p-4', lang('Card Body'), array(
            $el('div', 'flex-grow-1', lang('Job'), array(
                $span($sectors[$j['sector']]['title'], 'prc-eyebrow small text-uppercase fw-semibold text-primary', lang('Sector'), $bind('sector')),
                $el('h3', 'prc-display h5 position-static my-1', lang('Job Title'), array(
                    $link($j['title'], '#', 'stretched-link link-body-emphasis text-decoration-none', array(),
                        array('_bindings' => array('text' => 'title', 'href' => 'form_item_view'))),
                )),
                $para($j['employer'], 'small fw-semibold mb-1', $bind('employer')),
                $para($j['summary'], 'small text-body-secondary mb-0', $bind('summary')),
            )),
            $el('ul', 'list-unstyled d-flex flex-wrap flex-md-column gap-2 gap-md-1 small mb-0 flex-shrink-0', lang('Job Facts'), array(
                $fact('bi-geo-alt', $cities[$j['city']]['title'], 'city', lang('City')),
                $fact('bi-briefcase', $contract_types[$j['type']], 'contract_type', lang('Contract Type')),
                $fact('bi-laptop', $work_modes[$j['mode']], 'work_mode', lang('Work Mode')),
                $fact('bi-cash-stack', $j['salary'], 'salary', lang('Salary'), 'prc-salary fw-semibold'),
            )),
        )),
    ));
};

// A sector's card (the home and the sectors page): the icon, the title
// linking the sector's page, the summary and a link to its open jobs.
$sector_card = function ($key) use ($el, $para, $link, $col, $icon_tile, $sectors, $query) {
    $s = $sectors[$key];
    return $col(array(
        $el('article', 'card h-100 border-0 shadow-sm rounded-4 prc-lift', lang('Sector Card'), array(
            $el('div', 'card-body d-flex flex-column p-4', lang('Card Body'), array(
                $icon_tile($s['icon'], 'align-self-start mb-3'),
                $el('h3', 'prc-display h5 mb-2', lang('Sector Name'), array(
                    $link($s['title'], '{{page:sector_' . $key . '}}', 'link-body-emphasis text-decoration-none'),
                )),
                $para($s['summary'], 'small text-body-secondary mb-3'),
                $link(lang('See the Jobs'), '{{page:jobs}}?' . $query(array('sector' => $s['title'])), 'mt-auto small fw-semibold link-primary text-decoration-none'),
            )),
        )),
    ), '6', '4');
};

// A city's card (the cities page and the home): the city, what is hired
// there, and the job list filtered on it.
$city_card = function ($key) use ($el, $para, $link, $icon, $col, $cities, $query) {
    $c = $cities[$key];
    return $col(array(
        $el('article', 'card h-100 border-0 bg-body-tertiary rounded-4 prc-lift', lang('City Card'), array(
            $el('div', 'card-body p-4', lang('Card Body'), array(
                $el('h3', 'prc-display h5 d-flex align-items-center gap-2 position-static mb-2', lang('City Name'), array(
                    $icon('bi-geo-alt-fill', 'text-primary'),
                    $link($c['title'], '{{page:jobs}}?' . $query(array('city' => $c['title'])), 'stretched-link link-body-emphasis text-decoration-none'),
                )),
                $para($c['text'], 'small text-body-secondary mb-0'),
            )),
        )),
    ), '6', '4');
};

// A service for employers (the employers page and the home): the icon,
// the title, the summary and its three promises.
$service_card = function ($key) use ($el, $heading, $para, $col, $icon_tile, $check_list, $employer_services) {
    $s = $employer_services[$key];
    return $col(array(
        $el('article', 'card h-100 border-0 shadow-sm rounded-4', lang('Service Card'), array(
            $el('div', 'card-body d-flex flex-column p-4', lang('Card Body'), array(
                $icon_tile($s['icon'], 'align-self-start mb-3'),
                $heading('h3', $s['title'], 'prc-display h5 mb-2'),
                $para($s['summary'], 'small text-body-secondary mb-3'),
                $check_list($s['points'], 'list-unstyled small mb-0 mt-auto'),
            )),
        )),
    ), '6', '3');
};

// A consultant (the team page, the about page and a sector's page): the
// portrait, the name, the role, a sentence and the sector's open jobs.
// $md and $lg size its column; a sector's page gives it the whole side.
$consultant_card = function ($n, $md = '6', $lg = '4') use ($el, $heading, $para, $link, $image, $col, $attr, $portrait, $consultants, $sectors, $query) {
    $c = $consultants[$n];
    return $col(array(
        $el('article', 'card h-100 border-0 shadow-sm rounded-4 text-center p-4', lang('Consultant'), array(
            $image($portrait($c['name'], 240), $c['name'], 'rounded-circle mx-auto mb-3', array('width' => '120', 'height' => '120', '_attrs' => array($attr('loading', 'lazy')))),
            $heading('h3', $c['name'], 'prc-display h5 mb-1'),
            $para($c['role'], 'small fw-semibold text-primary mb-2'),
            $para($c['bio'], 'small text-body-secondary mb-3'),
            $link(sprintf(lang('Jobs in %s'), $sectors[$c['sector']]['title']), '{{page:jobs}}?' . $query(array('sector' => $sectors[$c['sector']]['title'])),
                'mt-auto small fw-semibold link-primary text-decoration-none'),
        )),
    ), $md, $lg);
};

// What a candidate or an employer says: the stars, the quote, the person
// and which side they came from.
$testimonial_card = function ($n) use ($el, $para, $span, $col, $image, $attr, $stars, $portrait, $testimonials, $testimonial_kinds) {
    $t = $testimonials[$n];
    return $col(array(
        $el('figure', 'card h-100 border-0 shadow-sm rounded-4 p-4 mb-0', lang('Testimonial'), array(
            $el('div', 'd-flex justify-content-between align-items-start gap-2', lang('Card Top'), array(
                $stars(),
                $span($testimonial_kinds[$t['kind']], 'badge rounded-pill bg-body text-body-emphasis border', lang('Badge')),
            )),
            $el('blockquote', 'mb-4', lang('Quote'), array($para($t['quote'], 'prc-quote mb-0'))),
            $el('figcaption', 'd-flex align-items-center gap-3 mt-auto', lang('Person'), array(
                $image($portrait($t['name'], 96), $t['name'], 'rounded-circle', array('width' => '48', 'height' => '48', '_attrs' => array($attr('loading', 'lazy')))),
                $el('div', '', lang('Name and Role'), array(
                    $span($t['name'], 'd-block fw-semibold', lang('Name')),
                    $span($t['role'], 'd-block small text-body-secondary', lang('Role')),
                )),
            )),
        )),
    ), '6', '3');
};
// ── Form fields ─────────────────────────────────────────────────────────
$field = function ($id, $label, $control, $class = 'mb-3', $hint = '') use ($el, $attr, $para) {
    $children = array(
        $el('label', 'form-label', lang('Label'), array(), array('text' => $label, '_attrs' => array($attr('for', $id)))),
        $control,
    );
    if ($hint !== '') $children[] = $para($hint, 'form-text', array('id' => $id . '-help'));
    return $el('div', $class, $label, $children);
};
$input = function ($id, $type, $name, $autocomplete = '', $required = true, $cf = array(), $more = array()) use ($el, $attr) {
    $attrs = array($attr('type', $type), $attr('name', $name));
    if ($required) $attrs[] = $attr('required', '');
    if ($autocomplete !== '') $attrs[] = $attr('autocomplete', $autocomplete);
    $props = array('id' => $id, '_attrs' => array_merge($attrs, $more));
    if ($cf) $props['_cf'] = $cf;
    return $el('input', 'form-control', lang('Field'), array(), $props);
};
$textarea = function ($id, $name, $rows, $required = true, $cf = array()) use ($el, $attr) {
    $attrs = array($attr('name', $name), $attr('rows', (string)$rows));
    if ($required) $attrs[] = $attr('required', '');
    $props = array('id' => $id, '_attrs' => $attrs);
    if ($cf) $props['_cf'] = $cf;
    return $el('textarea', 'form-control', lang('Field'), array(), $props);
};
// A pick list: the first, empty option is the prompt, not an answer.
// $selected is the answer chosen to begin with (no prompt then).
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
$submit = function ($text, $class = 'btn btn-primary px-4') use ($el, $attr) {
    return $el('button', $class, lang('Send Button'), array(), array('text' => $text, '_attrs' => array($attr('type', 'submit'))));
};
$captcha = function () use ($el) {
    return $el('div', 'mb-3', lang('CAPTCHA'), array(), array('_bindings' => array('section' => 'captcha')));
};
// A box the visitor must tick: what was agreed to is stored with the form.
// $privacy adds a link to the privacy page after the label.
$agree = function ($id, $name, $text, $privacy = false) use ($el, $attr, $link) {
    $children = array(
        $el('input', 'form-check-input', lang('Field'), array(), array('id' => $id, '_attrs' => array(
            $attr('type', 'checkbox'), $attr('name', $name), $attr('value', 'yes'), $attr('required', ''),
        ))),
        $el('label', 'form-check-label', lang('Label'), array(), array('text' => $text, '_attrs' => array($attr('for', $id)))),
    );
    if ($privacy) $children[] = $link(lang('Privacy Policy'), '{{page:privacy}}', 'ms-1');
    return $el('div', 'form-check small mb-3', lang('Consent'), $children);
};
// A group of radio buttons under its legend: the legend names the field.
// Each option is array(value, label).
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
// The numbered heading of a part of a longer form.
$form_step = function ($n, $title) use ($el, $span, $heading) {
    return $el('div', 'd-flex align-items-center gap-2 mt-2 mb-3', lang('Step'), array(
        $span((string)$n, 'badge rounded-circle text-bg-primary', lang('Step Number')),
        $heading('h2', $title, 'h6 fw-bold mb-0'),
    ));
};

// The record's Edit button, shown to whoever may edit the submissions of
// its form; it opens the record in the control panel and comes back.
$edit_button = function ($text = '') use ($link) {
    return $link($text !== '' ? $text : lang('Edit'), '#', 'btn btn-sm btn-outline-secondary rounded-pill', array(),
        array('_bindings' => array('href' => '__edit_url', 'eo_visible_if' => 'can_edit')));
};

// The comments of a record page (a request's messages, a conversation's
// replies, a post's comments), in the reading column: the page's Comments
// widget, which reads the record the page's view above it shows.
$comments_section = function ($name, $widget_key) use ($el, $container, $row, $col, $widget) {
    return $el('section', 'pb-5', $name, array(
        $container(array(
            $row(array(
                $col(array($widget($widget_key)), '', '8'),
            ), '', 'center'),
        )),
    ));
};

// A Messages node: the notices and errors of the form it sits in.
$messages = function ($class = '') {
    return array('type' => 'content', 'props' => array('contentType' => 'messages', 'cssClass' => $class), 'children' => array());
};

// A thread of the Comments widget: the comments in the widget's order, each
// a card with its writer, badges, date, the comment it answers where the
// thread stops going deeper, its words, its file and its links; the page
// links; the form (its notices first, the comment being answered, the
// stars while the page asks for them, the words, the name for whoever types
// one, the file, the staff's publish options, the box to follow the thread,
// the CAPTCHA); the box to follow or stop following; the staff's Stop and
// Allow buttons. A part under a flag shows only while it holds for the
// visitor. $w: the words (title, empty, heading, label, sign_in, submit,
// watch_box, follow, unfollow, close, reopen), reply (each comment can be
// answered and shared), watch (the boxes to follow the thread).
$comment_thread = function ($w) use ($el, $heading, $para, $link, $span, $attr, $bind, $show_when, $loop, $widget_root, $messages) {
    $actions = array();
    if ($w['reply']) {
        $actions[] = $link(lang('Reply'), '#', 'link-primary', array(), array('_bindings' => array('action' => 'comment_reply')));
        $actions[] = $link(lang('Share'), '#', 'link-secondary', array(), array('_bindings' => array('action' => 'comment_share')));
    }
    // Bound to the comment's edit screen, so the list adds no pencil of its own.
    $actions[] = $link(lang('Edit'), '#', 'link-secondary', array(), array('_bindings' => array('href' => '__comment_edit_url', 'eo_visible_if' => 'comment_can_edit')));

    $badges = array(
        $span(lang('Jane Cooper'), 'fw-semibold', lang('Name'), $bind('__comment_name')),
        $span('jane', 'small text-body-secondary', lang('User Name'), array('_bindings' => array('text' => '__comment_staff_name', 'eo_visible_if' => 'is_staff'))),
        $span(lang('Badge'), 'badge rounded-pill text-bg-secondary', lang('Badge'), array('_bindings' => array('text' => '__comment_badge', 'eo_visible_if' => 'comment_has_badge'))),
    );
    if ($w['reply']) {
        $badges[] = $span(lang('Featured'), 'badge rounded-pill text-bg-primary', lang('Featured'), $show_when('comment_is_featured'));
    }
    $card = $el('div', 'card border-0 bg-body-tertiary rounded-4 flex-grow-1', lang('Comment Card'), array(
        $el('div', 'card-body p-4', lang('Card Body'), array(
            $el('p', 'small fw-semibold text-danger-emphasis mb-2 pg-hide-if-empty', lang('Comment Status'), array(),
                array('text' => lang('Awaiting approval'), '_bindings' => array('text' => '__comment_status', 'eo_visible_if' => 'comment_is_pending'))),
            $el('div', 'text-warning mb-1', lang('Stars'), pg_sw_part_comment_stars(), $show_when('comment_has_rating')),
            $el('div', 'd-flex flex-wrap align-items-center gap-2', lang('Name Line'), $badges),
            $span(lang('2 days ago'), 'd-block small text-body-secondary pg-hide-if-empty', lang('Time'), $bind('__comment_date')),
            $el('blockquote', 'border-start border-3 ps-3 my-2 small text-body-secondary', lang('Quote'), array(
                $el('p', 'mb-1', lang('In reply to'), array(
                    $span(lang('In reply to'), 'me-1', lang('Label')),
                    $link(lang('Jane Cooper'), '#', 'fw-semibold link-body-emphasis', array(), array('_bindings' => array('href' => '__comment_parent_url', 'text' => '__comment_parent_name'))),
                )),
                $el('p', 'mb-0 pg-hide-if-empty', lang('Quoted text'), array(), array('text' => lang('Quoted text'), '_bindings' => array('text' => '__comment_quote'))),
            ), $show_when('comment_has_quote')),
            $el('div', 'mt-2', lang('Message'), array(), array('text' => lang('Comment text'), '_bindings' => array('text' => '__comment_message_html'))),
            $el('p', 'small mt-3 mb-0', lang('Attachment'), array(
                $link('file.pdf', '#', 'me-2', array(), array('target' => '_blank', 'rel' => 'noopener', '_bindings' => array('href' => '__comment_file_url', 'text' => '__comment_file_name'))),
                $span('12 KB', 'text-body-secondary', lang('Value'), $bind('__comment_file_size')),
            ), $show_when('comment_has_file')),
            $el('div', 'd-flex flex-wrap gap-3 mt-3 small', lang('Comment Actions'), $actions),
        )),
    ));

    $label = function ($for, $text, $class = 'form-label') use ($el, $attr) {
        return $el('label', $class, lang('Label'), array(), array('text' => $text, '_attrs' => array($attr('for', $for))));
    };
    $control = function ($tag, $class, $id, $value, $attrs = array()) use ($el) {
        return $el($tag, $class, lang('Field'), array(), array('id' => $id, '_attrs' => $attrs, '_bindings' => array('value' => $value)));
    };
    $fields = array($messages());
    if ($w['reply']) {
        // The comment being answered. Reply shows the box by its hidden
        // attribute, so it takes no display utility (d-flex would keep it
        // on screen, empty).
        $fields[] = $el('div', 'alert alert-secondary py-2 small', lang('Replying To'), array(
            $span(lang('Replying to'), 'me-1', lang('Text')),
            $el('strong', '', lang('Name'), array(), array('text' => lang('Jane Cooper'), '_bindings' => array('text' => '__reply_to_name'))),
            $link(lang('Cancel'), '#', 'ms-3 alert-link', array(), array('_bindings' => array('action' => 'comment_reply_cancel'))),
        ), $show_when('is_replying'));
    }
    $fields[] = $el('div', 'mb-3', lang('Rating'), array(
        $el('div', 'form-label', lang('Label'), array(), array('text' => lang('Rating'))),
        $el('div', '', lang('Stars'), array(), array('_bindings' => array('section' => 'comment_rating_input'))),
    ), $show_when('rating_on'));
    $fields[] = $el('div', 'mb-3', lang('Message'), array(
        $label('comment_message', $w['label']),
        $control('textarea', 'form-control', 'comment_message', 'comment_message', array($attr('rows', '5'))),
    ));
    $fields[] = $el('div', 'mb-3', lang('Your Name'), array(
        $label('comment_name', lang('Your Name')),
        $control('input', 'form-control', 'comment_name', 'comment_name', array($attr('type', 'text'))),
    ), $show_when('name_is_typed'));
    $fields[] = $el('div', 'mb-3', lang('Your Name'), array(
        $label('comment_name_type', lang('Your Name')),
        $control('select', 'form-select', 'comment_name_type', 'comment_name_type'),
    ), $show_when('name_is_chosen'));
    $fields[] = $el('p', 'mb-3 small', lang('Posting as'), array(
        $span(lang('Posting as'), 'me-1', lang('Label')),
        $el('strong', '', lang('Name'), array(), array('text' => lang('Jane Cooper'), '_bindings' => array('text' => '__comment_author_name'))),
    ), $show_when('name_is_fixed'));
    $fields[] = $el('div', 'mb-3', lang('Attach a File'), array(
        $label('comment_file', lang('Attach a File')),
        $control('input', 'form-control', 'comment_file', 'comment_file', array($attr('type', 'file'))),
    ), $show_when('files_on'));
    $fields[] = $el('div', 'mb-3 small', lang('Publish'), array(), array('_bindings' => array('section' => 'comment_publish')));
    if ($w['watch']) {
        $fields[] = $el('div', 'form-check mb-3', lang('Watch'), array(
            $control('input', 'form-check-input', 'comment_watcher', 'comment_watcher', array($attr('type', 'checkbox'))),
            $label('comment_watcher', $w['watch_box'], 'form-check-label'),
        ), $show_when('watching_on'));
    }
    $fields[] = $el('div', 'mb-3', lang('CAPTCHA'), array(), array('_bindings' => array('section' => 'comment_captcha')));
    $fields[] = $el('div', '', lang('Send Button'), array(
        $el('button', 'btn btn-primary rounded-pill px-4', lang('Send Button'), array(), array('text' => $w['submit'], '_attrs' => array($attr('type', 'submit')), '_bindings' => array('action' => 'comment_submit'))),
    ));

    $small_button = function ($text, $action) use ($el, $attr) {
        return $el('button', 'btn btn-sm btn-outline-secondary rounded-pill', lang('Send Button'), array(), array('text' => $text, '_attrs' => array($attr('type', 'submit')), '_bindings' => array('action' => $action)));
    };

    $children = array(
        $heading('h2', $w['title'], 'h4 fw-bold mb-3', array('_bindings' => array('text' => '__comments_title', 'eo_visible_if' => 'has_comments'))),
        $el('div', 'pg-hide-if-empty', lang('Comment List'), array(
            $loop(array($el('div', 'd-flex mb-3', lang('Comment'), array(
                $el('div', 'flex-shrink-0 border-start border-2 ms-2 me-3', lang('Reply Indent'), array(), $show_when('comment_is_reply')),
                $card,
            )))),
            $el('div', 'mt-3', lang('Pagination'), array(), array('_bindings' => array('section' => 'comment_pagination'))),
        )),
        $para($w['empty'], 'text-body-secondary', $show_when('no_comments')),
        $el('div', 'mt-4', lang('Add a comment'), array(
            $heading('h3', $w['heading'], 'h5 fw-bold mb-2', $show_when('comments_open')),
            $el('p', 'small text-body-secondary mb-3 pg-hide-if-empty', lang('Text'), array(), array('_bindings' => array('text' => '__comments_intro', 'eo_visible_if' => 'comments_open'))),
            $el('div', 'alert alert-secondary', lang('Closed'), array(), array('_bindings' => array('text' => '__comments_closed_message', 'eo_visible_if' => 'comments_closed'))),
            $el('div', 'alert alert-secondary d-flex flex-wrap align-items-center gap-2', lang('Sign in'), array(
                $span($w['sign_in'], '', lang('Text')),
                $link(lang('Sign in'), '#', 'alert-link', array(), array('rel' => 'nofollow', '_bindings' => array('href' => '__login_url'))),
            ), $show_when('must_sign_in')),
            $el('form', 'card card-body border-0 bg-body-tertiary rounded-4 p-4', lang('Comment Form'), $fields, array('_bindings' => array('action' => 'comment_form', 'eo_visible_if' => 'can_comment'))),
        )),
    );
    if ($w['watch']) {
        // Following the thread: how many follow it, the visitor's own
        // button, and the staff's list of who follows.
        $children[] = $el('div', 'small border-top pt-3 mt-4 d-flex flex-column gap-2 pg-hide-if-empty', lang('Watch'), array(
            $el('p', 'mb-0 pg-hide-if-empty', lang('Text'), array(), $bind('__watcher_count_text')),
            $el('form', 'd-flex flex-wrap align-items-center gap-2', lang('Watch Form'), array(
                $messages('w-100'),
                $small_button($w['follow'], 'comment_watch'),
                $small_button($w['unfollow'], 'comment_unwatch'),
            ), array('_bindings' => array('action' => 'comment_watch_form'))),
            $el('div', '', lang('Watchers'), array(), array('_bindings' => array('section' => 'comment_watchers'))),
        ), $show_when('watching_on'));
    }
    $children[] = $el('div', 'd-flex justify-content-end mt-3', lang('Staff Controls'), array(
        $el('form', 'd-flex gap-2', lang('Open or Close'), array(
            $small_button($w['close'], 'comment_close'),
            $small_button($w['reopen'], 'comment_reopen'),
        ), array('_bindings' => array('action' => 'comment_allow_new_form'))),
    ), $show_when('is_staff'));

    return $widget_root($children);
};
// ── Staff, newsletter and blog parts ────────────────────────────────────

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
                $item('job_new', lang('New Job Opening')),
                $item('staff_applications', lang('Job Applications')),
                $item('staff_enquiries', lang('Staff Requests')),
                $item('blog_new', lang('New Article')),
                $item('inbox', lang('Inbox')),
            ), array('smartActive' => true)),
        )),
    ));
};

// A page of the staff area: the staff bar under the header, no band.
$staff_page = function ($main) use ($root, $shared, $el) {
    return $root(array(
        $shared('account_bar'),
        $shared('header'),
        $shared('staff_bar'),
        $el('main', '', lang('Main Content'), $main),
        $shared('footer'),
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
    return $agree($id, 'consent', lang('I would like to receive new jobs and career advice by e-mail. I can unsubscribe at any time.'));
};

// A file field; what is sent is kept in the template folder named. An
// application takes a CV, an employer's enquiry a job description, so the
// default accepts PDFs, office files, text and pictures; one that takes
// only pictures is a collection's image kind.
$file = function ($id, $name, $folder, $accept = '.pdf,.doc,.docx,.odt,.txt,.jpg,.jpeg,.png') use ($el, $attr) {
    return $el('input', 'form-control', '', array(), array('id' => $id, '_attrs' => array(
        $attr('type', 'file'), $attr('name', $name), $attr('accept', $accept),
    ), '_cf' => array('upload_folder_id' => '{{folder:' . $folder . '}}')
            + ($accept === 'image/*' ? array('kind' => 'image') : array())));
};

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
$post_card = function () use ($el, $para, $span, $link, $col, $optional_image, $bind, $photo) {
    return $col(array(
        $el('article', 'card h-100 border-0 shadow-sm overflow-hidden', lang('Post Card'), array(
            $optional_image('cover_image', 'title', 'card-img-top object-fit-cover', 'ratio ratio-16x9 bg-body-tertiary',
                $photo('post', 800, 450), lang('Cover Image')),
            $el('div', 'card-body d-flex flex-column', lang('Card Body'), array(
                $para(lang('Date'), 'small text-uppercase fw-semibold text-primary mb-2', $bind('submitted_date_and_time')),
                $el('h3', 'h5 card-title fw-bold position-static', lang('Post Title'), array(
                    $link(lang('How to write a CV that gets you an interview'), '#', 'stretched-link link-body-emphasis text-decoration-none', array(),
                        array('_bindings' => array('text' => 'title', 'href' => 'form_item_view'))),
                )),
                $para(lang('A short summary of the post. It is what makes a visitor open it, so two sentences are enough.'),
                    'card-text small text-body-secondary', $bind('summary')),
                $span(lang('Read More'), 'mt-auto small fw-semibold text-primary', lang('Read More')),
            )),
        )),
    ), '6', '4');
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

