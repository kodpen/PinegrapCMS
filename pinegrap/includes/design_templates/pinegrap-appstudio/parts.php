<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Mobile App Developer", part 1 of its files: the node
 * builders, the developer's own data (the kinds of app, the platforms, the
 * sample apps and their release notes, the services and packages for
 * client projects, the way of working, skills, tools, experience, what
 * users say and the answers of the forms' pick lists) and the parts every
 * page repeats (account bar, header, footer, project band, app, release,
 * package and testimonial cards, store buttons, page shells, section
 * headers, form fields, the comment thread).
 * The page files of the same folder are included after it in the same
 * scope and build their trees with these variables;
 * includes/design_templates/pinegrap-appstudio.php puts them together.
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
// every opening until the developer puts their own screenshots in its place.
$photo = function ($seed, $width, $height) {
    return 'https://picsum.photos/seed/pinegrap-appstudio-' . $seed . '/' . (int)$width . '/' . (int)$height;
};
// A sample portrait or app icon: initials on the palette's colour. The
// initials follow the name in the site's language.
$portrait = function ($name, $size = 480) {
    $initials = '';
    foreach (array_slice(preg_split('/\s+/u', trim($name)), 0, 2) as $w) $initials .= mb_strtoupper(mb_substr($w, 0, 1, 'UTF-8'), 'UTF-8');
    return 'https://placehold.co/' . (int)$size . 'x' . (int)$size . '/ede9fe/3b0764?text=' . rawurlencode($initials !== '' ? $initials : 'A');
};
// A date some days before the file is read (Y-m-d), so the sample release
// notes always read as recent.
$days_ago = function ($days) {
    return date('Y-m-d', time() - (int)$days * 86400);
};

// ── The developer: their apps, their releases and their services ───────

// The person behind the site. The about page tells their story; the
// footer, the project band and the contact lines use the address and phone.
$developer = array(
    'name'      => lang('Deniz Kaya'),
    'role'      => lang('Independent iOS and Android developer'),
    'city'      => lang('Izmir, with users in 60 countries'),
    'bio'       => lang('I make small, calm apps that do one thing well, and I build apps for companies that want theirs made with the same care.'),
    'available' => lang('Two places for client projects this quarter'),
    'email'     => 'hello@example.com',
    'phone'     => '+90 555 000 00 00',
);

// The kinds of app, in the order the app list filters them. Each answer's
// value is its own words, so a filter link (?category=) reads as it shows.
$categories = array(
    'productivity' => array('icon' => 'bi-check2-square', 'title' => lang('Productivity'),
        'text' => lang('Habits, lists and focus: apps that take less time than they save.')),
    'health'       => array('icon' => 'bi-heart-pulse', 'title' => lang('Health & Fitness'),
        'text' => lang('Training plans and gentle reminders that work with the watch on your wrist.')),
    'travel'       => array('icon' => 'bi-airplane', 'title' => lang('Travel'),
        'text' => lang('Trip plans, maps and tickets that still open with no signal.')),
    'finance'      => array('icon' => 'bi-wallet2', 'title' => lang('Finance'),
        'text' => lang('Budgets shared with the people you live with, kept on your own phone.')),
    'education'    => array('icon' => 'bi-mortarboard', 'title' => lang('Education'),
        'text' => lang('A few minutes of learning a day, with cards that come back when you forget.')),
);

// The platforms an app runs on. The app list filters ?platform= with
// "contains", so "iOS and Android" shows under both.
$platforms = array(
    'ios'     => array('icon' => 'bi-apple', 'title' => 'iOS'),
    'android' => array('icon' => 'bi-android2', 'title' => 'Android'),
);
$platform_both = lang('iOS and Android');

// How an app is paid for, as the stores say it.
$price_models = array(lang('Free'), lang('Free with in-app purchases'), lang('Paid app'), lang('Subscription'));

// The sample apps: the records the Apps form starts with. The app list,
// the app page and the home read them; the home shows the first three.
// Each names a kind of app above; 'rating' is the number the lists sort
// on; 'version' is the newest release note's. App names are brand names
// and stay as they are in every language.
$apps = array(
    'tally'    => array('name' => 'Tally', 'tagline' => lang('Habits you keep, one tap a day'), 'category' => 'productivity',
        'platforms' => 'both', 'price' => 1, 'version' => '3.2.0', 'rating' => 4.8,
        'summary'     => lang('A habit tracker with no streaks to lose: tap what you did today and see the month fill up.'),
        'description' => lang('Tally keeps your habits on one screen. Mark a habit done with a tap, from the app, a widget or your watch, and see the weeks add up in soft colours. There are no badges and no guilt, only a calm record of the days you showed up.'),
        'features'    => array(lang('Home screen and lock screen widgets'), lang('Works with Apple Watch and Wear OS'),
                               lang('Reminders at the time you choose'), lang('Your data stays on your phone and your own cloud')),
        'privacy'     => lang('Tally collects no personal data. Your habits are kept on your phone and, if you turn it on, in your own iCloud or Google Drive.'),
        'featured' => true),
    'stride'   => array('name' => 'Stride', 'tagline' => lang('A running coach that listens to your legs'), 'category' => 'health',
        'platforms' => 'both', 'price' => 3, 'version' => '2.5.0', 'rating' => 4.7,
        'summary'     => lang('Training plans from your first 5 km to a marathon that change with how your runs feel.'),
        'description' => lang('Stride asks how each run felt and moves the plan with you: an easier week after a hard one, a longer run when you are ready. It reads your runs from the phone or the watch and tells you only what you need at the start line.'),
        'features'    => array(lang('Plans from 5 km to the marathon'), lang('Interval runs with voice cues'),
                               lang('Reads runs from Apple Health and Health Connect'), lang('A watch app that works without the phone')),
        'privacy'     => lang('Stride keeps your runs in your account to build your plan. It never sells or shares them; you can export or delete them at any time.'),
        'featured' => true),
    'wayfarer' => array('name' => 'Wayfarer', 'tagline' => lang('Your whole trip, even with no signal'), 'category' => 'travel',
        'platforms' => 'ios', 'price' => 2, 'version' => '1.9.0', 'rating' => 4.6,
        'summary'     => lang('Plans, bookings and offline maps of a trip in one place, ready in airplane mode.'),
        'description' => lang('Forward a booking e-mail and Wayfarer puts it on the day it belongs to. Every place you save is on an offline map, with the way between them, so the trip still works in a tunnel, on a ferry or abroad without data.'),
        'features'    => array(lang('Offline maps of the places you save'), lang('Bookings read from forwarded e-mails'),
                               lang('A day-by-day plan to share with your travel partner'), lang('Live Activities for your flights')),
        'privacy'     => lang('Wayfarer reads only the e-mails you forward to it and keeps your trips in your iCloud. There is no tracking and no advertising.'),
        'featured' => true),
    'penny'    => array('name' => 'Penny', 'tagline' => lang('A budget for two, without the arguments'), 'category' => 'finance',
        'platforms' => 'android', 'price' => 0, 'version' => '1.4.2', 'rating' => 4.5,
        'summary'     => lang('Shared budgets for couples and flatmates: who paid, who owes and what is left this month.'),
        'description' => lang('Penny keeps the shared costs of a home in one list. Add what you paid in two taps, split it the way you agreed, and see at the end of the month who owes whom. It connects to no bank: you add only what you want to share.'),
        'features'    => array(lang('Split by halves, shares or amounts'), lang('A monthly summary for everyone in the budget'),
                               lang('Works offline and syncs when you are back'), lang('Export to a spreadsheet')),
        'privacy'     => lang('Penny keeps your budgets encrypted on its servers so that the people in a budget see the same list. It connects to no bank and shows no ads.'),
        'featured' => false),
    'lingo'    => array('name' => 'Lingo Cards', 'tagline' => lang('New words that stay with you'), 'category' => 'education',
        'platforms' => 'both', 'price' => 1, 'version' => '4.0.1', 'rating' => 4.9,
        'summary'     => lang('Five minutes of vocabulary a day, with each card coming back just before you would forget it.'),
        'description' => lang('Lingo Cards shows you the words you are about to forget, not the ones you already know. Pick a language and a level, or make your own cards from a photo of a page, and learn in short sessions on the way to work.'),
        'features'    => array(lang('Twelve languages and three levels each'), lang('Cards from a photo of a page'),
                               lang('Spaced repetition that plans your reviews'), lang('Pronunciation by native speakers')),
        'privacy'     => lang('Lingo Cards keeps your progress in your account so that it follows you to a new phone. Photos of pages are read on your phone and never uploaded.'),
        'featured' => false),
);

// The sample release notes: the records the Releases form starts with,
// newest first. Each names a sample app; the newest note of an app carries
// the app's version. 'platform' is the answer of the Platform list.
$releases = array(
    array('app' => 'stride', 'version' => '2.5.0', 'platform' => 'both', 'date' => $days_ago(3),
        'notes' => lang('Interval runs with voice cues, a new watch face complication and a calmer summary screen after each run.')),
    array('app' => 'lingo', 'version' => '4.0.1', 'platform' => 'both', 'date' => $days_ago(9),
        'notes' => lang('Cards made from a photo now keep the accents of the page. Fixes a crash when a review ended with no cards left.')),
    array('app' => 'tally', 'version' => '3.2.0', 'platform' => 'both', 'date' => $days_ago(16),
        'notes' => lang('Lock screen widgets, habits you can pause for a holiday and a new colour for every weekday.')),
    array('app' => 'lingo', 'version' => '4.0.0', 'platform' => 'both', 'date' => $days_ago(24),
        'notes' => lang('A new review plan that shows fewer cards on busy days, and Portuguese and Korean courses.')),
    array('app' => 'wayfarer', 'version' => '1.9.0', 'platform' => 'ios', 'date' => $days_ago(31),
        'notes' => lang('Live Activities for your flights and offline maps that take half the space.')),
    array('app' => 'penny', 'version' => '1.4.2', 'platform' => 'android', 'date' => $days_ago(45),
        'notes' => lang('The monthly summary now shows the amounts in your own currency format. Fixes the export on Android 15.')),
    array('app' => 'stride', 'version' => '2.4.3', 'platform' => 'android', 'date' => $days_ago(58),
        'notes' => lang('Runs from Health Connect come in again after the system update. Thank you for the reports.')),
    array('app' => 'tally', 'version' => '3.1.0', 'platform' => 'both', 'date' => $days_ago(77),
        'notes' => lang('Wear OS app, reminders that follow your time zone and a darker dark mode.')),
);

// What the developer builds for clients, in the order the services page
// lists them. Each opens the project request with its kind chosen.
$project_types = array(
    'ios'       => lang('iPhone app'),
    'android'   => lang('Android app'),
    'both'      => lang('App for iPhone and Android'),
    'prototype' => lang('Clickable prototype'),
    'update'    => lang('Work on an existing app'),
);
$services = array(
    'native'    => array('icon' => 'bi-phone', 'title' => lang('Native apps'), 'type' => 'both',
        'text'   => lang('Apps written in Swift and Kotlin that feel at home on each phone and stay quick for years.'),
        'points' => array(lang('SwiftUI and Jetpack Compose'), lang('Widgets, watch apps and notifications'),
                          lang('Accessible to VoiceOver and TalkBack users'), lang('Tested on old and new phones'))),
    'prototype' => array('icon' => 'bi-bezier2', 'title' => lang('Prototypes'), 'type' => 'prototype',
        'text'   => lang('A clickable prototype on a real phone in two weeks, to test an idea before paying for the whole app.'),
        'points' => array(lang('Screens for the main journey'), lang('A test with five real users'),
                          lang('A short report on what to build first'), lang('Designs ready for the developers'))),
    'backend'   => array('icon' => 'bi-cloud', 'title' => lang('Accounts and sync'), 'type' => 'both',
        'text'   => lang('Sign-in, sync between devices and push notifications, on a service you own and can move.'),
        'points' => array(lang('Sign in with Apple and Google'), lang('Sync that works offline'),
                          lang('Push notifications'), lang('A simple admin page for your team'))),
    'launch'    => array('icon' => 'bi-rocket-takeoff', 'title' => lang('Store launch'), 'type' => 'update',
        'text'   => lang('Store pages, screenshots, the privacy answers and the review: the last mile done for you.'),
        'points' => array(lang('Store listing and screenshots'), lang('Privacy labels and data safety form'),
                          lang('Beta testing with TestFlight and Play'), lang('Answering the store review'))),
    'care'      => array('icon' => 'bi-shield-check', 'title' => lang('Care plan'), 'type' => 'update',
        'text'   => lang('Every new iOS and Android version tested, crashes fixed and small changes made each month.'),
        'points' => array(lang('Ready for each new system version'), lang('Crash reports read every week'),
                          lang('Two days of changes every month'), lang('Answer within one working day'))),
);

// The three packages of the services page and the home. 'price' is the
// words the cards show; the project form's Package list starts from ?package=.
$packages = array(
    'prototype' => array('title' => lang('Prototype'), 'price' => lang('From $3,500'), 'featured' => false,
        'text'   => lang('A clickable prototype and a test with real users, to know what to build.'),
        'points' => array(lang('Up to 15 screens'), lang('A test with five users'), lang('Ready in two to three weeks'))),
    'mvp'       => array('title' => lang('First Version'), 'price' => lang('From $14,000'), 'featured' => true,
        'text'   => lang('The first version of your app in the App Store and Google Play, with sign-in and sync.'),
        'points' => array(lang('iPhone and Android from one plan'), lang('Sign-in, sync and notifications'),
                          lang('Beta test and store launch'), lang('Ready in ten to fourteen weeks'))),
    'product'   => array('title' => lang('Full Product'), 'price' => lang('From $28,000'), 'featured' => false,
        'text'   => lang('A complete app with subscriptions, widgets and a watch app, and a year of care.'),
        'points' => array(lang('In-app purchases and subscriptions'), lang('Widgets and a watch app'),
                          lang('Analytics without tracking'), lang('A year of the care plan'))),
);

// The way of working, in order: what happens and how long it takes.
$process = array(
    array('icon' => 'bi-chat-dots', 'title' => lang('Discover'), 'time' => lang('Week 1'),
        'text' => lang('We talk about the app, who it is for and what it must do first; I send a plan with a fixed price.')),
    array('icon' => 'bi-phone-flip', 'title' => lang('Prototype'), 'time' => lang('Weeks 2–3'),
        'text' => lang('You try the main screens on your own phone before a line of the app is written.')),
    array('icon' => 'bi-code-square', 'title' => lang('Build and beta'), 'time' => lang('Weeks 4–11'),
        'text' => lang('A new beta every week through TestFlight and Google Play testing, so you see the app grow.')),
    array('icon' => 'bi-rocket', 'title' => lang('Launch'), 'time' => lang('Week 12'),
        'text' => lang('The app goes through the store review and goes live; the first month of fixes is included.')),
);

// The skills of the about page and the tools the apps are made with
// (product names, the same in every language).
$skills = array(lang('Native iOS development'), lang('Native Android development'), lang('Interface design'), lang('Accessibility'),
                lang('Offline sync'), lang('Store optimisation'), lang('Privacy by design'), lang('Automated testing'));
$tools = array('Swift', 'SwiftUI', 'Kotlin', 'Jetpack Compose', 'Xcode', 'Android Studio', 'Figma');

// The experience of the about page, newest first.
$experience = array(
    array('years' => '2020 –', 'title' => lang('Independent app developer'), 'place' => lang('Own studio')),
    array('years' => '2016 – 2020', 'title' => lang('Senior iOS developer'), 'place' => lang('A ride-sharing company in Istanbul')),
    array('years' => '2013 – 2016', 'title' => lang('Mobile developer'), 'place' => lang('A digital agency in Izmir')),
);

// The developer in four figures (the home and about pages).
$figures = array(
    array('value' => '5', 'label' => lang('Apps of my own in the stores')),
    array('value' => '1.2M', 'label' => lang('Downloads')),
    array('value' => '4.7', 'label' => lang('Average store rating')),
    array('value' => '30+', 'label' => lang('Apps built for clients')),
);

// What users say, each about one of the sample apps.
$testimonials = array(
    array('app' => 'tally', 'name' => lang('Selin Aydın'), 'role' => lang('Uses Tally every day'),
        'quote' => lang('The first habit app I did not delete after a week. It never makes me feel bad about a missed day.')),
    array('app' => 'stride', 'name' => lang('Burak Şahin'), 'role' => lang('Ran a first half marathon with Stride'),
        'quote' => lang('It moved my plan when I was ill and got me to the start line without an injury. The watch app is all I carry.')),
    array('app' => 'lingo', 'name' => lang('Zeynep Demir'), 'role' => lang('Learning Spanish with Lingo Cards'),
        'quote' => lang('Five minutes on the ferry every morning, and after four months I read the news in Spanish.')),
);

// The answers of the forms' pick lists. Each answer's value is its own
// words, so the e-mails and the lists read as the visitor chose.
$category_titles = array();
foreach ($categories as $c) $category_titles[] = $c['title'];
$platform_choices = array($platforms['ios']['title'], $platforms['android']['title'], $platform_both);
// The platforms of an app in the words of the Platform list.
$platform_title = function ($key) use ($platforms, $platform_both) {
    return isset($platforms[$key]) ? $platforms[$key]['title'] : $platform_both;
};
$app_names = array();
foreach ($apps as $a) $app_names[] = $a['name'];
$support_app_choices = array_merge($app_names, array(lang('Not about one app')));
$ticket_kinds = array(lang('Something does not work'), lang('A question'), lang('An idea for the app'),
                      lang('Purchases and subscriptions'), lang('Privacy and my data'));
// The states of a support request, set by the developer in the panel.
$ticket_statuses = array(lang('Request received'), lang('Looking into it'), lang('Fixed in the next update'), lang('Answered'), lang('Closed'));
$project_type_choices = array_values($project_types);
$package_choices = array(lang('Not sure yet'));
foreach ($packages as $p) $package_choices[] = $p['title'];
$budget_choices = array(lang('Under $5,000'), lang('$5,000–$15,000'), lang('$15,000–$30,000'), lang('Over $30,000'), lang('Not sure yet'));
$start_choices = array(lang('As soon as possible'), lang('Within a month'), lang('In one to three months'), lang('Just exploring'));
$design_choices = array(
    array(lang('Yes, the screens are designed'), lang('Yes, the screens are designed')),
    array(lang('Partly'), lang('Partly')),
    array(lang('No, I need design too'), lang('No, I need design too')),
);
$contact_choices = array(
    array(lang('E-mail'), lang('E-mail')),
    array(lang('Phone'), lang('Phone')),
    array(lang('Video call'), lang('Video call')),
);
// The states of a project request, set by the developer in the panel.
$project_statuses = array(lang('Request received'), lang('Proposal sent'), lang('In progress'), lang('Live in the stores'), lang('Declined'));

// The query string of a link that opens a list with a filter set
// (?category=, ?platform=, ?app=) or a form with a choice made (?app=,
// ?package=): the form's fields start with these values (_cf.url_param).
$query = function ($params) {
    return http_build_query($params, '', '&', PHP_QUERY_RFC3986);
};

// A page: the account bar, the header, the page's own content (the lists,
// the forms, the account pages) and the shared footer.
$page = function ($main, $newsletter = true) use ($root, $shared, $el, $widget) {
    $children = array(
        $shared('account_bar'),
        $shared('header'),
        $el('main', '', lang('Main Content'), $main),
    );
    if ($newsletter) $children[] = $widget('newsletter_band');
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
            $heading('h1', $title, 'display-6 fw-semibold mb-2'),
            $para($lead, 'lead text-body-secondary mb-0'),
        ), $more)),
    ));
};

// The heading over a section: a small line above, the title, a link to
// the rest, and a rule under them.
$section_header = function ($eyebrow, $title, $more_text = '', $more_href = '') use ($el, $heading, $para, $icon, $span) {
    $children = array($el('div', '', lang('Section Title'), array(
        $para($eyebrow, 'pma-eyebrow text-uppercase fw-semibold text-primary mb-1'),
        $heading('h2', $title, 'h3 fw-semibold mb-0'),
    )));
    if ($more_text !== '') {
        $children[] = $el('a', 'd-inline-flex align-items-center gap-1 fw-semibold link-primary text-decoration-none text-nowrap', lang('Link'), array(
            $span($more_text, '', lang('Link Text')),
            $icon('bi-arrow-right'),
        ), array('href' => $more_href));
    }
    return $el('div', 'd-flex flex-wrap align-items-end justify-content-between gap-2 pb-3 mb-4 border-bottom', lang('Section Header'), $children);
};

// An icon in a soft round tile, the way the categories are marked.
$icon_tile = function ($name, $class = '') use ($el, $icon) {
    return $el('span', trim('d-inline-flex align-items-center justify-content-center rounded-circle bg-primary-subtle text-primary-emphasis p-3 lh-1 ' . $class), lang('Icon Box'), array(
        $icon($name, 'fs-4'),
    ));
};

// Five stars, for what clients say.
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
$faq = function ($n, $question, $answer, $topics = '', $parent = 'ma-faq') use ($el, $para, $attr, $searchable) {
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

// ── The parts every page repeats ────────────────────────────────────────

// The line in the account bar: the newest release and the way to read it.
// A shared component.
$notice = function () use ($el, $icon, $span, $link, $apps, $releases) {
    $r = $releases[0];
    return $el('p', 'd-flex flex-wrap align-items-center gap-2 mb-0', lang('Notice'), array(
        $icon('bi-stars', 'text-primary'),
        $span($apps[$r['app']]['name'] . ' ' . $r['version'] . ' — ' . lang('out now in the stores.'), '', lang('Text')),
        $link(lang('What\'s New'), '{{page:releases}}', 'link-primary fw-semibold'),
    ));
};

// The bar above the header: the notice on the left, the language switcher
// and the login region on the right. Every page places it as one shared
// component, so a change to it is made once for the whole site.
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

// The header: the developer's name, the menu, the way to get help and the
// way to start a project. The links to the current page are marked active
// on the server (smart active state), so one header serves every page.
$header = function () use ($el, $link, $icon, $span, $attr) {
    $items = array();
    foreach (array(
        array('apps', lang('Apps')),
        array('releases', lang('What\'s New')),
        array('services', lang('Services')),
        array('about', lang('About')),
        array('blog', lang('Dev Notes')),
        array('support', lang('Get Help')),
    ) as $n) {
        $items[] = $el('li', 'nav-item', lang('Nav Item'), array($link($n[1], '{{page:' . $n[0] . '}}', 'nav-link text-nowrap')));
    }
    return $el('header', 'bg-body border-bottom', lang('Site Header'), array(
        $el('nav', 'navbar navbar-expand-lg py-3', lang('Navbar'), array(
            $el('div', 'container', lang('Navbar Container'), array(
                $el('a', 'navbar-brand d-flex align-items-center gap-2 fw-bold', lang('Brand'), array(
                    $el('span', 'd-inline-flex p-2 rounded-3 text-bg-primary lh-1', lang('Icon Box'), array($icon('bi-phone', 'fs-5'))),
                    $span('{{site_name}}', 'pma-brand fs-4', lang('Name')),
                ), array('href' => '{{page:home}}')),
                $el('button', 'navbar-toggler', lang('Toggler'), array(
                    $el('span', 'navbar-toggler-icon', lang('Toggler Icon')),
                ), array('_attrs' => array(
                    $attr('type', 'button'),
                    $attr('data-bs-toggle', 'collapse'),
                    $attr('data-bs-target', '#ma-nav'),
                    $attr('aria-controls', 'ma-nav'),
                    $attr('aria-expanded', 'false'),
                    $attr('aria-label', lang('Toggle navigation')),
                ))),
                $el('div', 'collapse navbar-collapse', lang('Nav Links'), array(
                    $el('ul', 'navbar-nav ms-lg-auto me-lg-3 column-gap-lg-2 py-3 py-lg-0', lang('Nav Menu'), $items, array('smartActive' => true)),
                    $el('div', 'd-flex flex-column flex-lg-row align-items-lg-center gap-2 pb-2 pb-lg-0', lang('Header Actions'), array(
                        $el('a', 'btn btn-primary rounded-pill d-inline-flex align-items-center justify-content-center gap-2 px-4 text-nowrap', lang('Call to Action'), array(
                            $span(lang('Start a Project'), '', lang('Link Text')),
                            $icon('bi-arrow-up-right'),
                        ), array('href' => '{{page:hire}}')),
                    )),
                ), array('id' => 'ma-nav')),
            )),
        ), array('_attrs' => array($attr('aria-label', lang('Main navigation'))))),
    ));
};

// The footer, on a dark band: the developer in a sentence and the
// profiles, the apps by kind and platform, the pages for users, the
// studio's pages and how to get in touch; at the bottom the year and the
// site's name.
$footer = function () use ($el, $para, $link, $icon, $span, $heading, $categories, $platforms, $developer, $query, $attr) {
    $plain = 'link-body-emphasis text-decoration-none';
    $list = function ($links) use ($el) {
        $items = array();
        foreach ($links as $l) $items[] = $el('li', 'mb-2', lang('List Item'), array($l));
        return $el('ul', 'list-unstyled small mb-0', lang('Links'), $items);
    };
    $app_links = array($link(lang('All Apps'), '{{page:apps}}', $plain));
    foreach ($categories as $c) $app_links[] = $link($c['title'], '{{page:apps}}?' . $query(array('category' => $c['title'])), $plain);
    foreach ($platforms as $p) $app_links[] = $link(sprintf(lang('Apps for %s'), $p['title']), '{{page:apps}}?' . $query(array('platform' => $p['title'])), $plain);
    $social = function ($name, $label) use ($el, $icon, $attr) {
        return $el('a', 'link-body-emphasis fs-5', $label, array($icon($name)), array('href' => '#', '_attrs' => array($attr('aria-label', $label))));
    };
    $contact_line = function ($icon_name, $child, $name) use ($el, $icon) {
        return $el('p', 'd-flex align-items-start gap-2 small mb-2', $name, array($icon($icon_name, 'text-primary'), $child));
    };

    return $el('footer', 'mt-auto pt-5 pb-4 bg-body text-body', lang('Site Footer'), array(
        $el('div', 'container', lang('Footer Container'), array(
            $el('div', 'row g-4', lang('Footer Columns'), array(
                $el('div', 'col-12 col-lg-4', lang('About the Developer'), array(
                    $el('a', 'd-inline-flex align-items-center gap-2 fw-bold fs-4 mb-3 ' . $plain, lang('Brand'), array(
                        $icon('bi-phone', 'text-primary'),
                        $span('{{site_name}}', 'pma-brand', lang('Name')),
                    ), array('href' => '{{page:home}}')),
                    $para($developer['bio'], 'small text-body-secondary mb-3'),
                    $el('div', 'd-flex gap-3', lang('Social Links'), array(
                        $social('bi-github', 'GitHub'),
                        $social('bi-mastodon', 'Mastodon'),
                        $social('bi-linkedin', 'LinkedIn'),
                        $social('bi-youtube', 'YouTube'),
                    )),
                )),
                $el('div', 'col-6 col-md-3 col-lg-2', lang('App Links'), array(
                    $heading('h2', lang('Apps'), 'h6 text-uppercase small fw-bold mb-3'),
                    $list($app_links),
                )),
                $el('div', 'col-6 col-md-3 col-lg-2', lang('Users'), array(
                    $heading('h2', lang('For Users'), 'h6 text-uppercase small fw-bold mb-3'),
                    $list(array(
                        $link(lang('Get Help'), '{{page:support}}', $plain),
                        $link(lang('My Requests'), '{{page:my_tickets}}', $plain),
                        $link(lang('What\'s New'), '{{page:releases}}', $plain),
                        $link(lang('Join the Beta'), '{{page:beta}}', $plain),
                        $link(lang('Privacy Policy'), '{{page:privacy}}', $plain),
                        $link(lang('My Account'), '{{page:my_account}}', $plain),
                    )),
                )),
                $el('div', 'col-6 col-md-3 col-lg-2', lang('Footer Links'), array(
                    $heading('h2', lang('Studio'), 'h6 text-uppercase small fw-bold mb-3'),
                    $list(array(
                        $link(lang('Services'), '{{page:services}}', $plain),
                        $link(lang('How I Work'), '{{page:process}}', $plain),
                        $link(lang('About'), '{{page:about}}', $plain),
                        $link(lang('FAQ'), '{{page:faq}}', $plain),
                        $link(lang('Press Kit'), '{{page:press}}', $plain),
                        $link(lang('Dev Notes'), '{{page:blog}}', $plain),
                        $link(lang('Newsletter'), '{{page:newsletter}}', $plain),
                    )),
                )),
                $el('div', 'col-6 col-md-3 col-lg-2', lang('Contact Details'), array(
                    $heading('h2', lang('Say hello'), 'h6 text-uppercase small fw-bold mb-3'),
                    $contact_line('bi-envelope', $link($developer['email'], 'mailto:' . $developer['email'], $plain . ' text-break'), lang('Email')),
                    $contact_line('bi-telephone', $link($developer['phone'], 'tel:' . preg_replace('/[^0-9+]/', '', $developer['phone']), $plain), lang('Phone')),
                    $contact_line('bi-geo-alt', $span($developer['city'], '', lang('Text')), lang('Address')),
                )),
            )),
            $el('div', 'd-flex flex-column flex-md-row justify-content-between align-items-center gap-3 border-top mt-4 pt-4', lang('Footer Bottom'), array(
                $para('© {{year}} {{site_name}}', 'small text-body-secondary mb-0'),
                $para(lang('Apple and the Apple logo are trademarks of Apple Inc. Google Play and the Google Play logo are trademarks of Google LLC.'), 'small text-body-secondary mb-0 text-md-end'),
            )),
        )),
    ), array('_attrs' => array($attr('data-bs-theme', 'dark'))));
};

// The project band: a full-width band in the palette's colour with the
// way to start a client project. Shared, so every page that ends with it
// carries the same offer.
$project_band = function () use ($el, $heading, $para, $link, $container, $row, $col) {
    return $el('section', 'py-5 text-bg-primary', lang('Call to Action'), array(
        $container(array(
            $row(array(
                $col(array(
                    $heading('h2', lang('Have an app in mind?'), 'pma-display h2 fw-bold mb-2'),
                    $para(lang('Tell me what it should do in a few lines; you get an answer and a first plan within two working days.'), 'mb-0'),
                ), '', '8'),
                $col(array(
                    $el('div', 'd-flex flex-wrap gap-2 justify-content-lg-end', lang('Buttons'), array(
                        $link(lang('Start a Project'), '{{page:hire}}', 'btn btn-secondary btn-lg rounded-pill px-4'),
                        $link(lang('See My Apps'), '{{page:apps}}', 'btn btn-link btn-lg text-reset'),
                    )),
                ), '', '4'),
            ), '4', '', 'align-items-center'),
        )),
    ));
};

// The store buttons of an app: one per store, each bound to the Apps
// form's address field. The address is also the box's hidden value, so a
// button leaves with its box when the app is not in that store.
$store_buttons = function ($sample_key = 'tally', $size = '') use ($el, $icon, $span, $attr, $apps) {
    $s = $apps[$sample_key];
    $button = function ($field, $icon_name, $small, $big, $sample) use ($el, $icon, $span, $attr, $size) {
        return $el('div', 'pg-hide-if-empty', lang('Store Button'), array(
            $span($sample, 'pg-field-value d-none', lang('Value'), array('_bindings' => array('text' => $field))),
            $el('a', trim('btn btn-dark rounded-3 d-inline-flex align-items-center gap-2 px-3 text-start ' . $size), lang('Store Link'), array(
                $icon($icon_name, 'fs-4'),
                $el('span', 'd-flex flex-column lh-sm', lang('Link Text'), array(
                    $span($small, 'small', lang('Text')),
                    $span($big, 'fw-semibold', lang('Store')),
                )),
            ), array('href' => $sample, 'target' => '_blank', '_attrs' => array($attr('rel', 'noopener')), '_bindings' => array('href' => $field))),
        ));
    };
    $ios = in_array($s['platforms'], array('ios', 'both'), true) ? 'https://apps.apple.com/' : '';
    $android = in_array($s['platforms'], array('android', 'both'), true) ? 'https://play.google.com/store/apps' : '';
    return $el('div', 'd-flex flex-wrap gap-2', lang('Store Buttons'), array(
        $button('app_store_url', 'bi-apple', lang('Download on the'), 'App Store', $ios),
        $button('google_play_url', 'bi-google-play', lang('Get it on'), 'Google Play', $android),
    ));
};

// An app's card of the lists (the home and the app list): the icon, the
// name linking the app page, the tagline, the kind, the platforms, the
// rating and how it is paid for. Every value is bound to the Apps form's
// field of the same name; the sample fills the canvas.
$app_card = function ($sample_key = 'tally') use ($el, $para, $span, $link, $image, $icon, $col, $bind, $apps, $categories, $price_models, $platform_title, $portrait, $attr) {
    $s = $apps[$sample_key];
    return $col(array(
        $el('article', 'card h-100 border-0 shadow-sm rounded-4 pma-lift', lang('App Card'), array(
            $el('div', 'card-body d-flex flex-column p-4', lang('Card Body'), array(
                $el('div', 'd-flex align-items-center gap-3 mb-3', lang('App Heading'), array(
                    $el('span', 'pg-field-value d-block flex-shrink-0 rounded-4 overflow-hidden pma-icon', lang('Icon Frame'), array(
                        $image($portrait($s['name'], 160), $s['name'], 'w-100 h-100 object-fit-cover',
                            array('_bindings' => array('src' => 'icon', 'alt' => 'name'), '_attrs' => array($attr('width', '64'), $attr('height', '64')))),
                    )),
                    $el('div', '', lang('Name and Tagline'), array(
                        $el('h3', 'h5 fw-bold position-static mb-1', lang('App Name'), array(
                            $link($s['name'], '#', 'stretched-link link-body-emphasis text-decoration-none', array(),
                                array('_bindings' => array('text' => 'name', 'href' => 'form_item_view'))),
                        )),
                        $para($s['tagline'], 'small text-body-secondary mb-0', $bind('tagline')),
                    )),
                )),
                $para($s['summary'], 'small mb-3', $bind('summary')),
                $el('div', 'd-flex flex-wrap align-items-center gap-2 small mt-auto', lang('Meta'), array(
                    $span($categories[$s['category']]['title'], 'badge rounded-pill bg-body text-body-emphasis border', lang('Category'), $bind('category')),
                    $span($platform_title($s['platforms']), 'badge rounded-pill bg-body text-body-emphasis border', lang('Platforms'), $bind('platforms')),
                    $el('span', 'd-inline-flex align-items-center gap-1 ms-auto', lang('Rating'), array(
                        $icon('bi-star-fill', 'text-warning'),
                        $span(number_format($s['rating'], 1), 'fw-semibold', lang('Value'), $bind('rating')),
                    )),
                )),
                $para($price_models[$s['price']], 'small text-body-secondary mt-2 mb-0', $bind('price_model')),
            )),
        )),
    ), '6', '4');
};

// A release note of the lists (the What's New page, the home and the app
// page): the app, the version, the platform and the date, then what
// changed. Bound to the Releases form's fields.
$release_card = function ($n = 0) use ($el, $para, $span, $heading, $icon, $bind, $apps, $releases, $platform_title) {
    $r = $releases[$n];
    return $el('article', 'd-flex gap-3 py-4 border-bottom', lang('Release Note'), array(
        $el('div', 'flex-shrink-0 pt-1', lang('Marker'), array($icon('bi-tag', 'text-primary fs-5'))),
        $el('div', 'flex-grow-1', lang('Text'), array(
            $el('div', 'd-flex flex-wrap align-items-baseline gap-2 mb-1', lang('Meta'), array(
                $heading('h3', $apps[$r['app']]['name'], 'h6 fw-bold mb-0', $bind('app')),
                $span($r['version'], 'badge rounded-pill text-bg-primary pma-mono', lang('Version'), $bind('version')),
                $span($platform_title($r['platform']), 'small text-body-secondary', lang('Platform'), $bind('platform')),
                $span($r['date'], 'small text-body-secondary ms-auto', lang('Date'), $bind('release_date')),
            )),
            $para($r['notes'], 'mb-0', $bind('notes')),
        )),
    ));
};

// A package's card (the services page and the home): the name, the price,
// a line, what it holds and a link that opens the project request with it
// chosen.
$package_card = function ($key) use ($el, $heading, $para, $link, $span, $col, $check_list, $packages, $query) {
    $p = $packages[$key];
    $children = array();
    if ($p['featured']) $children[] = $span(lang('Most chosen'), 'badge rounded-pill text-bg-primary align-self-start mb-3', lang('Badge'));
    $children[] = $heading('h3', $p['title'], 'h5 fw-bold mb-1');
    $children[] = $para($p['price'], 'pma-display h3 fw-bold mb-2');
    $children[] = $para($p['text'], 'small text-body-secondary mb-3');
    $children[] = $check_list($p['points'], 'list-unstyled small mb-4');
    $children[] = $link(lang('Start with this package'), '{{page:hire}}?' . $query(array('package' => $p['title'])),
        'btn ' . ($p['featured'] ? 'btn-primary' : 'btn-outline-primary') . ' rounded-pill mt-auto');
    return $col(array(
        $el('article', 'card h-100 rounded-4 ' . ($p['featured'] ? 'border-primary border-2 shadow' : 'border-0 shadow-sm'), lang('Package Card'), array(
            $el('div', 'card-body d-flex flex-column p-4', lang('Card Body'), $children),
        )),
    ), '6', '4');
};

// What a user says: the quote, the stars, the person and the app.
$testimonial_card = function ($n) use ($el, $para, $span, $col, $image, $attr, $stars, $portrait, $testimonials) {
    $t = $testimonials[$n];
    return $col(array(
        $el('figure', 'card h-100 border-0 shadow-sm rounded-4 p-4 mb-0', lang('User Quote'), array(
            $stars(),
            $el('blockquote', 'mb-4', lang('Quote'), array($para($t['quote'], 'mb-0'))),
            $el('figcaption', 'd-flex align-items-center gap-3 mt-auto', lang('User'), array(
                $image($portrait($t['name'], 96), $t['name'], 'rounded-circle', array('_attrs' => array($attr('width', '48'), $attr('height', '48'), $attr('loading', 'lazy')))),
                $el('div', '', lang('Name and Role'), array(
                    $span($t['name'], 'd-block fw-semibold', lang('Name')),
                    $span($t['role'], 'd-block small text-body-secondary', lang('Role')),
                )),
            )),
        )),
    ), '', '4');
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
$submit = function ($text, $class = 'btn btn-primary rounded-pill px-4') use ($el, $attr) {
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
                $item('app_new', lang('New App')),
                $item('release_new', lang('New Release Note')),
                $item('staff_tickets', lang('Support Requests')),
                $item('staff_projects', lang('Project Requests')),
                $item('staff_beta', lang('Beta Testers')),
                $item('blog_new', lang('New Article')),
                $item('inbox', lang('Inbox')),
            ), array('smartActive' => true)),
        )),
    ));
};

// A page of the staff area: the staff bar under the header, no newsletter.
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
    return $agree($id, 'consent', lang('I would like to receive news of new apps and Dev Notes posts by e-mail. I can unsubscribe at any time.'));
};

// A file field; what is sent is kept in the template folder named. The
// support request and a project take documents (a spec, a log) and
// pictures (a screenshot, a sketch), so the default accepts PDFs, office
// files, text and pictures; one that takes only pictures is a collection's
// image kind (an app's).
$file = function ($id, $name, $folder, $accept = '.pdf,.doc,.docx,.odt,.txt,.log,.jpg,.jpeg,.png') use ($el, $attr) {
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
                    $link(lang('What we learned shipping our first widget for the home screen'), '#', 'stretched-link link-body-emphasis text-decoration-none', array(),
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

