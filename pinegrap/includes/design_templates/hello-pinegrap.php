<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Say hello to Pinegrap": a whole starter site. Home, about,
 * services, a blog with its post page, a contact page whose messages become
 * conversations, the sign-in and account pages with an inbox and a staff
 * directory, a staff area, an error page and - when the shop is switched on -
 * a small shop. The blog and the shop can be searched, the shop browsed by
 * category and filtered, and the about page's team is the staff directory.
 * Built on Bootstrap 5. Read by pg_design_templates()
 * (includes/fn/designer.php).
 *
 * The pages go into three folders under pinegrap_hello: public (open to
 * everybody), registration (any signed-in visitor) and private (the site
 * staff and the users given access to the folder).
 *
 * The e-mails the site sends are pages too, in the private folder: the
 * contact form's notification and its reply to the sender, the e-mail about
 * a new reply or comment, the order receipt. The contact form, the pages
 * with comments and the checkout are set to send them.
 *
 * Placeholders, filled when the template is opened (pg_design_template_prepare()):
 *   {{page:<key>}}    address of the template page with that key
 *   {{tab:<key>}}     that page in a widget setting; its id once published
 *   {{folder:<key>}}  id of that template folder
 *   {{product_group:root}}  id of the site's top product group (0 if none)
 *   {{site_name}}     the site's organization name
 *   {{site_email}}    the site's own e-mail address (notification settings)
 *   {{year}}          the current year
 * A shared_ref carrying props.templateWidget is pointed at the row made for
 * that entry of 'widgets', one carrying props.templateShared at the row made
 * for that entry of 'shared' (the parts every page repeats: the header, the
 * footer, the call to action band, the staff bar - edited once for all the
 * pages). A shared component cannot hold a widget yet, so the account area
 * with the login region and the cart link is a thin bar of its own above the
 * header. A widget whose tree is 'starter' opens with the
 * layout the editor gives its kind. A page or widget with 'requires' =>
 * 'ecommerce' is left out of a site without the shop.
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

$shop      = defined('ECOMMERCE') && ECOMMERCE;
$panel_url = (defined('OUTPUT_PATH') ? OUTPUT_PATH : '/') . (defined('OUTPUT_SOFTWARE_DIRECTORY') ? OUTPUT_SOFTWARE_DIRECTORY : '') . '/welcome.php';

// Node builders. Same shapes the editor's createNode() produces; the editor
// gives every node its id when the tabs open.
$el = function ($tag, $class, $name, $children = array(), $extra = array()) {
    $props = array('tag' => $tag, 'cssClass' => $class);
    if ($name !== '') $props['customName'] = $name;
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
$icon = function ($name, $class) use ($el) {
    return $el('i', 'bi ' . $name . ' ' . $class, '', array(), array('_attrs' => array(array('name' => 'aria-hidden', 'value' => 'true'))));
};
$span = function ($text, $class, $name = '', $extra = array()) use ($el) {
    return $el('span', $class, $name, array(), array_merge(array('text' => $text), $extra));
};
// A round badge (a step number, an initial): a fixed square, so the
// circle stays round whatever the letter and the row around it.
$circle = array('_attrs' => array(array('name' => 'style', 'value' => 'width:2.75rem;height:2.75rem')));
$container = function ($children, $class = '') {
    return array('type' => 'container', 'props' => array('fluid' => false, 'cssClass' => $class), 'children' => $children);
};
// A gutter above 4 is wider than the container's side padding and pushes a
// phone screen sideways; wider gaps are given from lg up (g-4 g-lg-5).
$row = function ($children, $gutter = '', $justify = '', $class = '') {
    return array('type' => 'row', 'props' => array('gutter' => $gutter, 'justify' => $justify, 'align' => '', 'cssClass' => $class), 'children' => $children);
};
$col = function ($children, $md = '', $lg = '', $sm = '') {
    return array('type' => 'col', 'props' => array('xs' => '12', 'sm' => $sm, 'md' => $md, 'lg' => $lg, 'xl' => '', 'xxl' => '',
                                                  'offsetXs' => '', 'offsetMd' => '', 'order' => '', 'cssClass' => ''), 'children' => $children);
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
// Bound to a data token: the words stay what the canvas shows until the
// page is published and the widget fills them in.
$bind = function ($token) {
    return array('_bindings' => array('text' => $token));
};
$show_when = function ($flag) {
    return array('_bindings' => array('eo_visible_if' => $flag));
};
// The record's Edit button, shown to whoever may edit the submissions of
// its form; it opens the record in the control panel and comes back.
$edit_button = function () use ($link) {
    return $link(lang('Edit'), '#', 'btn btn-sm btn-outline-secondary', array(),
        array('_bindings' => array('href' => '__edit_url', 'eo_visible_if' => 'can_edit')));
};

// The navigation bar every page opens with: a shared component, so a new
// menu item or another brand is written once. The link to the page being
// viewed is marked active on the server (smart active state), which is what
// lets one header serve every page.
$navbar = function () use ($el, $link, $shop) {
    $item = function ($key, $text) use ($el, $link) {
        return $el('li', 'nav-item', lang('Nav Item'), array(
            $link($text, '{{page:' . $key . '}}', 'nav-link'),
        ));
    };
    $items = array(
        $item('home', lang('Home Page')),
        $item('about', lang('About Us')),
        $item('services', lang('Our Services')),
        $item('blog', lang('Blog')),
    );
    if ($shop) $items[] = $item('shop', lang('Shop'));
    $items[] = $item('contact', lang('Contact us'));
    return $el('nav', 'navbar navbar-expand-lg bg-body border-bottom', 'Navbar', array(
        $el('div', 'container', lang('Navbar Container'), array(
            $link('{{site_name}}', '{{page:home}}', 'navbar-brand fw-bold'),
            $el('button', 'navbar-toggler border-0', lang('Toggler'), array(
                $el('span', 'navbar-toggler-icon', lang('Toggler Icon')),
            ), array('_attrs' => array(
                array('name' => 'type', 'value' => 'button'),
                array('name' => 'data-bs-toggle', 'value' => 'collapse'),
                array('name' => 'data-bs-target', 'value' => '#hp-nav'),
                array('name' => 'aria-controls', 'value' => 'hp-nav'),
                array('name' => 'aria-expanded', 'value' => 'false'),
                array('name' => 'aria-label', 'value' => lang('Toggle navigation')),
            ))),
            $el('div', 'collapse navbar-collapse', lang('Nav Links'), array(
                $el('ul', 'navbar-nav ms-auto mb-2 mb-lg-0', lang('Nav Menu'), $items),
            ), array('id' => 'hp-nav')),
        )),
    ), array('smartActive' => true));
};

// The account area above the header: the cart link and the login region.
// System widgets, so it stays on each page (a shared component cannot hold
// one yet); the bar itself is only a frame for them.
$account_bar = function () use ($el, $widget, $shop) {
    return $el('div', 'bg-body-tertiary border-bottom', lang('Account Bar'), array(
        $el('div', 'container d-flex justify-content-end align-items-center gap-2 py-1', lang('Account Area'),
            $shop ? array($widget('cart_link'), $widget('login_region')) : array($widget('login_region'))),
    ));
};

// The footer every page closes with.
$footer = function () use ($el, $para, $link) {
    return $el('footer', 'mt-auto border-top py-4', lang('Footer'), array(
        $el('div', 'container d-flex flex-column flex-md-row justify-content-between align-items-center gap-2', lang('Footer Container'), array(
            $para('© {{year}} {{site_name}}', 'small text-body-secondary mb-0'),
            $el('div', 'd-flex flex-wrap justify-content-center gap-3 small', lang('Footer Links'), array(
                $link(lang('About Us'), '{{page:about}}', 'link-secondary text-decoration-none'),
                $link(lang('Our Services'), '{{page:services}}', 'link-secondary text-decoration-none'),
                $link(lang('Blog'), '{{page:blog}}', 'link-secondary text-decoration-none'),
                $link(lang('Contact us'), '{{page:contact}}', 'link-secondary text-decoration-none'),
            )),
        )),
    ));
};

// A page of the site: the account bar, the shared header, the page's own
// content, the shared footer. ($active is kept for the callers; the header
// finds the current page by itself.)
$page = function ($active, $main) use ($root, $account_bar, $shared, $el) {
    return $root(array(
        $account_bar(),
        $shared('header'),
        $el('main', '', lang('Main Content'), $main),
        $shared('footer'),
    ));
};

// The staff pages carry a second bar under the navigation, with the staff
// area's own links: a shared component too, the current link marked on the
// server like the header's.
$staff_bar = function () use ($el, $link) {
    $item = function ($key, $text) use ($link) {
        return $link($text, '{{page:' . $key . '}}', 'nav-link px-0');
    };
    return $el('div', 'bg-body-tertiary border-bottom', lang('Staff Bar'), array(
        $el('div', 'container d-flex flex-wrap align-items-center gap-4 py-2 small', lang('Staff Bar Container'), array(
            $el('span', 'badge text-bg-dark', lang('Staff Badge'), array(), array('text' => lang('Staff'))),
            $el('nav', 'nav nav-underline gap-4', lang('Staff Links'), array(
                $item('staff', lang('Staff Area')),
                $item('blog_new', lang('New Blog Post')),
                $item('staff_entry', lang('Add to the Staff Directory')),
                $item('inbox', lang('Inbox')),
            ), array('smartActive' => true)),
        )),
    ));
};

// The band that sends visitors to the contact page, closing the home, about
// and services pages: one shared component, so its words change once.
$cta = function () use ($el, $container, $heading, $para, $link) {
    return $el('section', 'py-5', lang('Call to Action'), array(
        $container(array(
            $el('div', 'p-4 p-lg-5 rounded-4 text-bg-secondary d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3', lang('Call to Action'), array(
                $el('div', '', lang('Text'), array(
                    $heading('h2', lang('Have a question? We are happy to help.'), 'h3 mb-2'),
                    $para(lang('Write a few lines; the answer comes to your inbox.'), 'mb-0 opacity-75'),
                )),
                $link(lang('Write to Us'), '{{page:contact}}', 'btn btn-light btn-lg px-4 flex-shrink-0'),
            )),
        )),
    ));
};

// A card of a three-up row.
$feature = function ($icon_name, $title, $text) use ($el, $heading, $para, $icon, $col) {
    return $col(array(
        $el('div', 'h-100 p-4 border rounded-3 bg-body', lang('Feature Card'), array(
            $icon($icon_name, 'fs-2 text-primary'),
            $heading('h3', $title, 'h5 mt-3'),
            $para($text, 'text-body-secondary mb-0'),
        )),
    ), '6', '4');
};

// The top band of the inner pages: title and one sentence.
$page_header = function ($title, $lead) use ($el, $heading, $para, $container) {
    return $el('header', 'py-5 bg-body-tertiary border-bottom', lang('Page Header'), array(
        $container(array(
            $heading('h1', $title, 'display-6 fw-bold mb-2'),
            $para($lead, 'lead text-body-secondary mb-0'),
        )),
    ));
};

// A link card: icon, title, one line; the whole card is the link, and the
// link is the grid cell, so a card the server drops (its page is not on the
// site) leaves no hole in the row.
$link_card = function ($icon_name, $title, $text, $href, $href_token = '') use ($el, $heading, $para, $icon) {
    $extra = array('href' => $href);
    if ($href_token !== '') $extra['_bindings'] = array('href' => $href_token);
    return $el('a', 'col-12 col-md-6 col-lg-4 text-decoration-none link-body-emphasis', $title, array(
        $el('div', 'card h-100 border-0 shadow-sm', lang('Card'), array(
            $el('div', 'card-body d-flex align-items-start gap-3', lang('Card Body'), array(
                $icon($icon_name, 'fs-3 text-primary'),
                $el('div', '', lang('Text'), array(
                    $heading('h2', $title, 'h6 mb-1'),
                    $para($text, 'small text-body-secondary mb-0'),
                )),
            )),
        )),
    ), $extra);
};
$link_cards = function ($cards, $class = '') use ($el) {
    return $el('div', trim('row g-3 ' . $class), lang('Link Cards'), $cards);
};

// Form fields, the blocks the Form palette draws. A control's name is the
// field it fills.
$field = function ($id, $label, $control, $class = 'mb-3') use ($el) {
    return $el('div', $class, $label, array(
        $el('label', 'form-label', lang('Label'), array(), array('text' => $label, '_attrs' => array(array('name' => 'for', 'value' => $id)))),
        $control,
    ));
};
$input = function ($id, $type, $name, $autocomplete = '', $required = true, $extra_attrs = array(), $cf = array()) use ($el) {
    $attrs = array(
        array('name' => 'type', 'value' => $type),
        array('name' => 'name', 'value' => $name),
    );
    if ($required) $attrs[] = array('name' => 'required', 'value' => '');
    if ($autocomplete !== '') $attrs[] = array('name' => 'autocomplete', 'value' => $autocomplete);
    $props = array('id' => $id, '_attrs' => array_merge($attrs, $extra_attrs));
    if ($cf) $props['_cf'] = $cf;
    return $el('input', 'form-control', '', array(), $props);
};
$textarea = function ($id, $name, $rows, $required = true) use ($el) {
    $attrs = array(
        array('name' => 'name', 'value' => $name),
        array('name' => 'rows', 'value' => (string)$rows),
    );
    if ($required) $attrs[] = array('name' => 'required', 'value' => '');
    return $el('textarea', 'form-control', '', array(), array('id' => $id, '_attrs' => $attrs));
};
// A file field; what is sent is kept in the template folder named.
$file = function ($id, $name, $folder, $accept = 'image/*', $required = false) use ($el) {
    $attrs = array(
        array('name' => 'type', 'value' => 'file'),
        array('name' => 'name', 'value' => $name),
        array('name' => 'accept', 'value' => $accept),
    );
    if ($required) $attrs[] = array('name' => 'required', 'value' => '');
    return $el('input', 'form-control', '', array(), array('id' => $id, '_attrs' => $attrs,
        '_cf' => array('upload_folder_id' => '{{folder:' . $folder . '}}')));
};
$submit = function ($text, $name) use ($el) {
    return $el('button', 'btn btn-primary px-4', $name, array(), array('text' => $text, '_attrs' => array(
        array('name' => 'type', 'value' => 'submit'),
    )));
};
// The site-wide CAPTCHA; the block drops when the setting is off or the
// visitor is signed in.
$captcha = function () use ($el) {
    return $el('div', 'mb-3', lang('CAPTCHA'), array(), array('_bindings' => array('section' => 'captcha')));
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
$post_card = function () use ($el, $heading, $para, $span, $link, $col, $optional_image, $bind) {
    return $col(array(
        $el('article', 'card h-100 border-0 shadow-sm overflow-hidden', lang('Post Card'), array(
            $optional_image('cover_image', 'title', 'card-img-top object-fit-cover', 'ratio ratio-16x9',
                'https://picsum.photos/seed/pinegrap-post/800/450', lang('Cover Image')),
            $el('div', 'card-body d-flex flex-column', lang('Card Body'), array(
                $para(lang('Date'), 'small text-body-secondary mb-2', $bind('submitted_date_and_time')),
                $el('h2', 'h5 card-title', lang('Post Title'), array(
                    $link(lang('Five small changes that make a site easier to use'), '#',
                        'stretched-link link-body-emphasis text-decoration-none', array(),
                        array('_bindings' => array('text' => 'title', 'href' => 'form_item_view'))),
                )),
                $para(lang('A short summary of the post. It is what makes a visitor open it, so two sentences are enough.'),
                    'card-text text-body-secondary', $bind('summary')),
                $span(lang('Read More'), 'mt-auto small fw-semibold text-primary', lang('Read More')),
            )),
        )),
    ), '6', '4');
};

// The account pages sit on the page header like the other inner pages; the
// widget under it draws its own card.
$account_page = function ($active, $title, $lead, $widget_key) use ($page, $page_header, $widget) {
    return $page($active, array(
        $page_header($title, $lead),
        $widget($widget_key),
    ));
};

// ── Pages ───────────────────────────────────────────────────────────────

$pages = array();

// A number of the stats bands: the figure and what it counts.
$stat = function ($figure, $label, $class = '') use ($el, $para, $col) {
    return $col(array(
        $el('div', trim('text-center ' . $class), lang('Stat'), array(
            $para($figure, 'display-6 fw-bold mb-1'),
            $para($label, 'small mb-0 opacity-75'),
        )),
    ), '3', '', '6');
};

// A line of a check list.
$check = function ($text) use ($el, $icon, $span) {
    return $el('li', 'd-flex align-items-start gap-2 mb-2', lang('List Item'), array(
        $icon('bi-check2-circle', 'text-primary fs-5 lh-1'),
        $span($text, ''),
    ));
};

// A question of the FAQ: Bootstrap's accordion item, closed.
$faq = function ($n, $question, $answer) use ($el, $para) {
    $id = 'hp-faq-' . $n;
    return $el('div', 'accordion-item', lang('Question'), array(
        $el('h3', 'accordion-header', lang('Question Header'), array(
            $el('button', 'accordion-button collapsed', lang('Question'), array(), array(
                'text'   => $question,
                '_attrs' => array(
                    array('name' => 'type', 'value' => 'button'),
                    array('name' => 'data-bs-toggle', 'value' => 'collapse'),
                    array('name' => 'data-bs-target', 'value' => '#' . $id),
                    array('name' => 'aria-expanded', 'value' => 'false'),
                    array('name' => 'aria-controls', 'value' => $id),
                ),
            )),
        )),
        $el('div', 'accordion-collapse collapse', lang('Answer'), array(
            $el('div', 'accordion-body text-body-secondary', lang('Answer'), array($para($answer, 'mb-0'))),
        ), array('id' => $id, '_attrs' => array(array('name' => 'data-bs-parent', 'value' => '#hp-faq')))),
    ));
};

// A quotation card: what a customer said, with a note that it is a sample
// to replace with the words of a real one.
$quote = function ($text, $name, $role) use ($el, $para, $span, $icon, $col, $circle) {
    return $col(array(
        $el('figure', 'card h-100 mb-0', lang('Quote Card'), array(
            $el('div', 'card-body d-flex flex-column', lang('Card Body'), array(
                $icon('bi-quote', 'fs-1 text-primary lh-1 mb-2'),
                $el('blockquote', 'mb-4 flex-grow-1', lang('Quote'), array($para($text, 'mb-0'))),
                $el('figcaption', 'd-flex align-items-center gap-3', lang('Quote Author'), array(
                    $span(mb_substr($name, 0, 1, 'UTF-8'), 'd-inline-flex align-items-center justify-content-center rounded-circle bg-primary-subtle text-primary-emphasis fw-bold flex-shrink-0 lh-1', lang('Initial'), $circle),
                    $el('div', '', lang('Text'), array(
                        $para($name, 'fw-semibold mb-0'),
                        $para($role, 'small text-body-secondary mb-0'),
                    )),
                )),
            )),
        )),
    ), '', '4');
};

// A step of the about page's timeline.
$milestone = function ($year, $title, $text) use ($el, $heading, $para, $span) {
    return $el('li', 'position-relative ps-4 pb-4 border-start border-2', lang('Milestone'), array(
        $span('', 'position-absolute top-0 start-0 translate-middle rounded-circle bg-primary p-2', lang('Dot')),
        $span($year, 'badge text-bg-primary mb-2', lang('Year')),
        $heading('h3', $title, 'h5 mb-1'),
        $para($text, 'text-body-secondary mb-0'),
    ));
};

// A person of the about page's team: a record of the staff directory. Name,
// job title and photo only; the e-mail and phone stay in the directory, which
// only signed-in members see.
$person = function () use ($el, $heading, $para, $image, $col, $bind) {
    return $col(array(
        $el('div', 'card h-100 text-center', lang('Person Card'), array(
            $el('div', 'card-body', lang('Card Body'), array(
                $el('div', 'pg-hide-if-empty', lang('Picture'), array(
                    $el('span', 'pg-field-value d-block', lang('Picture Frame'), array(
                        $image('https://picsum.photos/seed/pinegrap-person/192/192', lang('Photo'),
                            'rounded-circle mb-3 object-fit-cover', array('width' => '96', 'height' => '96',
                                '_attrs' => array(array('name' => 'loading', 'value' => 'lazy')),
                                '_bindings' => array('src' => 'photo', 'alt' => 'full_name'))),
                    )),
                )),
                $heading('h3', lang('Jane Cooper'), 'h6 mb-0', $bind('full_name')),
                $para(lang('Customer Support'), 'small text-body-secondary mb-0', $bind('job_title')),
            )),
        )),
    ), '6', '3');
};

// A value of the about page: icon, name, one sentence.
$value_card = function ($icon_name, $title, $text) use ($el, $heading, $para, $icon, $col) {
    return $col(array(
        $el('div', 'card h-100', lang('Value Card'), array(
            $el('div', 'card-body', lang('Card Body'), array(
                $el('div', 'd-inline-flex align-items-center justify-content-center rounded-3 bg-primary-subtle text-primary p-3 mb-3', lang('Icon Box'), array(
                    $icon($icon_name, 'fs-4'),
                )),
                $heading('h3', $title, 'h5'),
                $para($text, 'text-body-secondary mb-0'),
            )),
        )),
    ), '6', '3');
};

// Home
$pages[] = array(
    'key'              => 'home',
    'name'             => lang('home'),
    'folder'           => 'public',
    'title'            => lang('Home Page'),
    'meta_description' => lang('Welcome to our site. Find out who we are and get in touch.'),
    'tree'             => $page('home', array(
        // Hero: the words on one side, a picture on the other.
        $el('section', 'py-5 bg-body-tertiary', lang('Hero'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $el('span', 'badge rounded-pill text-bg-primary mb-3', lang('Badge'), array(), array('text' => lang('New site, ready to go'))),
                        $heading('h1', lang('Hello, Pinegrap!'), 'display-5 fw-bold mb-3'),
                        $para(lang('This site is ready. Change the words, add your pictures and publish — every block on these pages can be edited in the visual editor.'), 'lead text-body-secondary mb-4'),
                        $el('div', 'd-flex flex-wrap gap-2 mb-4', lang('Buttons'), array(
                            $link(lang('Our Services'), '{{page:services}}', 'btn btn-primary btn-lg px-4'),
                            $link(lang('Write to Us'), '{{page:contact}}', 'btn btn-outline-secondary btn-lg px-4'),
                        )),
                        $el('ul', 'list-inline small text-body-secondary mb-0', lang('Highlights'), array(
                            $el('li', 'list-inline-item me-3', lang('List Item'), array($icon('bi-check-circle-fill', 'text-primary me-1'), $span(lang('Edited on the page'), ''))),
                            $el('li', 'list-inline-item me-3', lang('List Item'), array($icon('bi-check-circle-fill', 'text-primary me-1'), $span(lang('Fits every screen'), ''))),
                            $el('li', 'list-inline-item', lang('List Item'), array($icon('bi-check-circle-fill', 'text-primary me-1'), $span(lang('Found by search engines'), ''))),
                        )),
                    ), '', '6'),
                    $col(array(
                        $image('https://picsum.photos/seed/pinegrap-hero/960/720', lang('A team at work'), 'img-fluid rounded-4 shadow-lg'),
                    ), '', '6'),
                ), '4', '', 'g-lg-5 align-items-center'),
            ), 'py-lg-4'),
        )),

        // Three things the site does.
        $el('section', 'py-5', lang('Features'), array(
            $container(array(
                $el('div', 'text-center mb-5', lang('Section Header'), array(
                    $para(lang('Why us'), 'text-primary fw-semibold text-uppercase small mb-2'),
                    $heading('h2', lang('Everything you need to start'), 'h2 mb-3'),
                    $para(lang('A site that is simple to keep up to date, with the pages a business needs from the first day.'), 'text-body-secondary mx-auto mb-0 col-lg-7'),
                )),
                $row(array(
                    $feature('bi-pencil-square', lang('Edit on the page'), lang('Click any text and write your own. Blocks move with drag and drop.')),
                    $feature('bi-chat-left-text', lang('Conversations, not dead ends'), lang('A message from the contact page opens a conversation that members follow and answer in their inbox.')),
                    $feature('bi-person-lock', lang('Pages for members and staff'), lang('Signed-in visitors get their account pages; the staff get a private area of their own.')),
                ), '4', '', 'g-lg-5'),
            )),
        )),

        // Picture and text side by side, with a check list.
        $el('section', 'py-5 bg-body-tertiary', lang('How We Help'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $image('https://picsum.photos/seed/pinegrap-work/800/600', lang('Planning a project'), 'img-fluid rounded-4 shadow',
                            array('_attrs' => array(array('name' => 'loading', 'value' => 'lazy')))),
                    ), '', '6'),
                    $col(array(
                        $para(lang('How we help'), 'text-primary fw-semibold text-uppercase small mb-2'),
                        $heading('h2', lang('From the first idea to a site that works for you'), 'h2 mb-3'),
                        $para(lang('Tell visitors what you do and why it matters to them. Two or three short paragraphs read better than one long one.'), 'text-body-secondary mb-4'),
                        $el('ul', 'list-unstyled mb-4', lang('Check List'), array(
                            $check(lang('Clear plans, clear prices, no surprises')),
                            $check(lang('A person who answers, not a ticket number')),
                            $check(lang('Help after the job is done')),
                        )),
                        $link(lang('About Us'), '{{page:about}}', 'btn btn-primary px-4'),
                    ), '', '6'),
                ), '4', '', 'g-lg-5 align-items-center'),
            )),
        )),

        // Numbers in the palette's main colour.
        $el('section', 'py-5 text-bg-primary', lang('Numbers'), array(
            $container(array(
                $row(array(
                    $stat('12+', lang('years of experience')),
                    $stat('350+', lang('finished projects')),
                    $stat('98%', lang('happy customers')),
                    $stat('24/7', lang('support')),
                ), '4'),
            )),
        )),

        // What customers say (samples, to be replaced).
        $el('section', 'py-5', lang('Testimonials'), array(
            $container(array(
                $el('div', 'text-center mb-5', lang('Section Header'), array(
                    $para(lang('References'), 'text-primary fw-semibold text-uppercase small mb-2'),
                    $heading('h2', lang('What our customers say'), 'h2 mb-0'),
                )),
                $row(array(
                    $quote(lang('Put the words of a customer here: a sentence or two on what you did for them and how it went.'), lang('Customer Name'), lang('Company, Title')),
                    $quote(lang('A short, concrete quote reads best: the problem, what changed, and one number if you have it.'), lang('Customer Name'), lang('Company, Title')),
                    $quote(lang('Ask your customers before you publish their words, and use their real names only with their permission.'), lang('Customer Name'), lang('Company, Title')),
                ), '4'),
            )),
        )),

        // The newest posts of the blog.
        $el('section', 'py-5 bg-body-tertiary', lang('Latest Posts'), array(
            $container(array(
                $el('div', 'd-flex flex-wrap align-items-end justify-content-between gap-2 mb-4', lang('Section Header'), array(
                    $heading('h2', lang('From the blog'), 'h3 mb-0'),
                    $link(lang('All posts'), '{{page:blog}}', 'link-primary text-decoration-none fw-semibold'),
                )),
                $widget('home_posts'),
            )),
        )),

        // Questions visitors ask.
        $el('section', 'py-5', lang('Frequently Asked Questions'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $para(lang('Questions'), 'text-primary fw-semibold text-uppercase small mb-2'),
                        $heading('h2', lang('Frequently asked questions'), 'h2 mb-3'),
                        $para(lang('Did not find your answer? Write to us; the answer arrives on the conversation page and in your inbox.'), 'text-body-secondary mb-4'),
                        $link(lang('Write to Us'), '{{page:contact}}', 'btn btn-outline-primary'),
                    ), '', '4'),
                    $col(array(
                        $el('div', 'accordion', lang('FAQ'), array(
                            $faq(1, lang('How do I change the text and the pictures?'), lang('Open the page in the visual editor, click what you want to change and type. Pictures are swapped from the options panel.')),
                            $faq(2, lang('Can I add pages of my own?'), lang('Yes. Add a page from the editor\'s toolbar, build it from the palette and publish it; it gets its own address.')),
                            $faq(3, lang('What happens to the messages sent from the contact page?'), lang('Each message opens a conversation. You answer it from the control panel, and the visitor reads the answer and writes back on the site.')),
                            $faq(4, lang('Can I change the colours and the look later?'), lang('Any time: Settings, Design, Theme. The look and the colour palette change every page at once and leave the content as it is.')),
                        ), array('id' => 'hp-faq')),
                    ), '', '8'),
                ), '4', '', 'g-lg-5'),
            )),
        )),

        // The way to the contact page (shared: the same band on every page
        // that closes with one).
        $shared('cta'),
    )),
);

// About
$pages[] = array(
    'key'              => 'about',
    'name'             => lang('about'),
    'folder'           => 'public',
    'title'            => lang('About Us'),
    'meta_description' => lang('Who we are, what we do and what we care about.'),
    'tree'             => $page('about', array(
        $page_header(lang('About Us'), lang('A few words on who we are, what we do and why we do it.')),

        // The story, beside a picture.
        $el('section', 'py-5', lang('Our Story'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $para(lang('Our story'), 'text-primary fw-semibold text-uppercase small mb-2'),
                        $heading('h2', lang('We started small, and we still work that way'), 'h2 mb-3'),
                        $para(lang('Every site starts with a short story. Write yours here: when you started, what you believe in and what makes your work different.'), 'mb-3'),
                        $para(lang('Describe your products or services in plain words. Visitors read the first two sentences, so put what matters most there.'), 'text-body-secondary mb-0'),
                    ), '', '6'),
                    $col(array(
                        $el('figure', 'mb-0', lang('Picture'), array(
                            $image('https://picsum.photos/seed/pinegrap-story/800/600', lang('Our workshop'), 'img-fluid rounded-4 shadow mb-2',
                                array('_attrs' => array(array('name' => 'loading', 'value' => 'lazy')))),
                            $el('figcaption', 'small text-body-secondary', lang('Caption'), array(), array('text' => lang('Put a picture of your team or your workplace here.'))),
                        )),
                    ), '', '6'),
                ), '4', '', 'g-lg-5 align-items-center'),
            )),
        )),

        // A few numbers.
        $el('section', 'pb-5', lang('Numbers'), array(
            $container(array(
                $el('div', 'card', lang('Numbers Card'), array(
                    $el('div', 'card-body py-4', lang('Card Body'), array(
                        $row(array(
                            $stat('2015', lang('founded')),
                            $stat('24', lang('people on the team')),
                            $stat('350+', lang('finished projects')),
                            $stat('12', lang('cities served')),
                        ), '4'),
                    )),
                )),
            )),
        )),

        // What the team cares about.
        $el('section', 'py-5 bg-body-tertiary', lang('Our Values'), array(
            $container(array(
                $el('div', 'text-center mb-5', lang('Section Header'), array(
                    $para(lang('Our values'), 'text-primary fw-semibold text-uppercase small mb-2'),
                    $heading('h2', lang('What we care about'), 'h2 mb-0'),
                )),
                $row(array(
                    $value_card('bi-chat-heart', lang('Honesty'), lang('Clear and honest communication, even when the news is not what you hoped.')),
                    $value_card('bi-calendar-check', lang('Reliability'), lang('Work delivered when promised, and a word in advance when a date moves.')),
                    $value_card('bi-lightning-charge', lang('Craft'), lang('Small details done well: they are what people remember.')),
                    $value_card('bi-people', lang('Care'), lang('Care that continues after the sale, for as long as you need us.')),
                ), '4'),
            )),
        )),

        // The way here, year by year.
        $el('section', 'py-5', lang('Our Journey'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $para(lang('Our journey'), 'text-primary fw-semibold text-uppercase small mb-2'),
                        $heading('h2', lang('How we got here'), 'h2 mb-3'),
                        $para(lang('Four moments that shaped the company. Change the years and the words to tell your own.'), 'text-body-secondary mb-0'),
                    ), '', '4'),
                    $col(array(
                        $el('ol', 'list-unstyled ms-2 mb-0', lang('Timeline'), array(
                            $milestone('2015', lang('The first office'), lang('Two people, one room and the first customers from the neighbourhood.')),
                            $milestone('2018', lang('A team of ten'), lang('Design and development under one roof, and the first large project.')),
                            $milestone('2022', lang('Customers across the country'), lang('Work with companies in a dozen cities, most of them by recommendation.')),
                            $milestone(lang('Today'), lang('Still growing'), lang('New services, the same way of working: plainly, and on time.')),
                        )),
                    ), '', '8'),
                ), '4', '', 'g-lg-5'),
            )),
        )),

        // The people.
        $el('section', 'py-5 bg-body-tertiary', lang('Our Team'), array(
            $container(array(
                $el('div', 'text-center mb-5', lang('Section Header'), array(
                    $para(lang('Our team'), 'text-primary fw-semibold text-uppercase small mb-2'),
                    $heading('h2', lang('The people behind the work'), 'h2 mb-0'),
                )),
                // Filled from the staff directory: whoever the staff add
                // there appears here.
                $widget('about_team'),
            )),
        )),

        // The way to the contact page (shared).
        $shared('cta'),
    )),
);

// Services
$service = function ($icon_name, $title, $text) use ($el, $heading, $para, $icon, $col) {
    return $col(array(
        $el('div', 'h-100 p-4 rounded-3 border bg-body', lang('Service Card'), array(
            $el('div', 'd-inline-flex align-items-center justify-content-center rounded-3 bg-primary-subtle text-primary p-3 mb-3', lang('Icon Box'), array(
                $icon($icon_name, 'fs-4'),
            )),
            $heading('h3', $title, 'h5'),
            $para($text, 'text-body-secondary mb-0'),
        )),
    ), '6', '4');
};
$step = function ($number, $title, $text) use ($el, $heading, $para, $span, $col, $circle) {
    return $col(array(
        $el('div', 'd-flex gap-3', lang('Step'), array(
            $span($number, 'd-inline-flex align-items-center justify-content-center flex-shrink-0 align-self-start rounded-circle text-bg-primary fw-bold lh-1', lang('Step Number'), $circle),
            $el('div', '', lang('Text'), array(
                $heading('h3', $title, 'h6 mb-1'),
                $para($text, 'small text-body-secondary mb-0'),
            )),
        )),
    ), '', '4');
};
$pages[] = array(
    'key'              => 'services',
    'name'             => lang('services'),
    'folder'           => 'public',
    'title'            => lang('Our Services'),
    'meta_description' => lang('What we can do for you, and how we work together.'),
    'tree'             => $page('services', array(
        $page_header(lang('Our Services'), lang('What we can do for you, and how we work together from the first call to the finished job.')),
        $el('section', 'py-5', lang('Our Services'), array(
            $container(array(
                $row(array(
                    $service('bi-lightbulb', lang('Consulting'), lang('We listen first, then tell you plainly what will work, what it costs and how long it takes.')),
                    $service('bi-vector-pen', lang('Design'), lang('Pages, logos and printed matter that look like you and read easily on any screen.')),
                    $service('bi-code-slash', lang('Development'), lang('Websites and online shops built on a platform you can update yourself.')),
                    $service('bi-graph-up-arrow', lang('Marketing'), lang('Search, social and email campaigns measured by the visitors and sales they bring.')),
                    $service('bi-shield-check', lang('Site Care'), lang('Updates, backups and security checks, so your site keeps running while you work.')),
                    $service('bi-headset', lang('Support'), lang('A real person answers. Write from the contact page and follow the answer in your inbox.')),
                ), '4', '', 'g-lg-5'),
            )),
        )),
        $el('section', 'py-5 bg-body-tertiary', lang('How We Work'), array(
            $container(array(
                $heading('h2', lang('How we work'), 'h3 mb-4'),
                $row(array(
                    $step('1', lang('We talk'), lang('Tell us what you need. We ask the questions that shape the job.')),
                    $step('2', lang('We plan'), lang('You get a clear plan with steps, dates and a price before any work starts.')),
                    $step('3', lang('We deliver'), lang('We build, you check, we polish. Then we stay around for what comes next.')),
                ), '4', '', 'g-lg-5'),
            )),
        )),
        // The way to the contact page (shared).
        $shared('cta'),
    )),
);

// Blog
$pages[] = array(
    'key'              => 'blog',
    'name'             => lang('blog'),
    'folder'           => 'public',
    'title'            => lang('Blog'),
    'meta_description' => lang('News, notes and stories from our team.'),
    'tree'             => $page('blog', array(
        $page_header(lang('Blog'), lang('News, notes and stories from our team.')),
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
    'tree'             => $page('blog', array(
        $widget('blog_post'),
        $el('section', 'pb-5', lang('Comments'), array(
            $container(array(
                $row(array(
                    $col(array(
                        array('type' => 'region', 'props' => array('regionType' => 'comments_block', 'regionName' => ''), 'children' => array()),
                    ), '', '8'),
                ), '', 'center'),
            )),
        )),
    )),
);

// Contact: the form opens a conversation. Sending it lands on the
// conversation page; the answers follow there and in the inbox.
$pages[] = array(
    'key'              => 'contact',
    'name'             => lang('contact-us'),
    'folder'           => 'public',
    'title'            => lang('Contact us'),
    'meta_description' => lang('Send us a message through the form, or reach us by email or phone.'),
    // The page's form settings; the fields are the controls of the widget
    // below. What is sent opens the conversation page: its first message is
    // this one, and the answers follow as comments. A visitor without an
    // account gets one (auto-registration), which that page tells them.
    'form'             => array(
        'form_name'            => lang('Contact Form'),
        'confirmation_message' => lang('Thank you, your message has reached us. We will get back to you soon.'),
        'confirmation_page_id' => '{{tab:conversation}}',
        'auto_registration'    => 1,
        // The staff are told at the site's own address, and the sender gets
        // a reply; both e-mails are pages of the template.
        'notify_email'         => '{{site_email}}',
        'notify_subject'       => lang('New message: ^^subject^^'),
        'notify_page_id'       => '{{tab:email_new_message}}',
        'confirm_email'        => 1,
        'confirm_subject'      => lang('We received your message'),
        'confirm_page_id'      => '{{tab:email_message_received}}',
    ),
    'tree'             => $page('contact', array(
        $page_header(lang('Contact us'), lang('Fill in the form and we will get back to you as soon as we can.')),
        $el('section', 'py-5', lang('Content'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $widget('contact_form'),
                    ), '', '7'),
                    $col(array(
                        $el('div', 'p-4 border rounded-3 bg-body-tertiary mb-4', lang('Contact Details'), array(
                            $heading('h2', lang('Contact Details'), 'h5 mb-4'),
                            $el('p', 'd-flex align-items-center gap-2 mb-3', lang('Email'), array(
                                $icon('bi-envelope', 'text-primary'),
                                $link('hello@example.com', 'mailto:hello@example.com', 'link-body-emphasis text-decoration-none'),
                            )),
                            $el('p', 'd-flex align-items-center gap-2 mb-3', lang('Phone'), array(
                                $icon('bi-telephone', 'text-primary'),
                                $link('+90 555 000 00 00', 'tel:+905550000000', 'link-body-emphasis text-decoration-none'),
                            )),
                            $el('p', 'd-flex align-items-start gap-2 mb-3', lang('Address'), array(
                                $icon('bi-geo-alt', 'text-primary mt-1'),
                                $span(lang('Sample Street 1, 34000 Istanbul, Türkiye'), ''),
                            )),
                            $para(lang('Weekdays 09:00–18:00'), 'small text-body-secondary mb-0'),
                        )),
                        $el('div', 'p-4 border rounded-3', lang('Inbox Note'), array(
                            $el('p', 'd-flex align-items-center gap-2 fw-semibold mb-2', lang('Title'), array(
                                $icon('bi-inbox', 'text-primary'),
                                $span(lang('Follow the answer in your inbox'), ''),
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

// The shop: products, a product, the cart, checkout and the receipt.
$pages[] = array(
    'key'              => 'shop',
    'requires'         => 'ecommerce',
    'name'             => lang('shop'),
    'folder'           => 'public',
    'title'            => lang('Shop'),
    'meta_description' => lang('Our products, ready to order online.'),
    'tree'             => $page('shop', array(
        $page_header(lang('Shop'), lang('Our products, ready to order online.')),
        $widget('shop_catalog'),
    )),
);
$pages[] = array(
    'key'              => 'product',
    'requires'         => 'ecommerce',
    'name'             => lang('product-detail'),
    'folder'           => 'public',
    'title'            => lang('Product'),
    'meta_description' => lang('Product details, options and price.'),
    'sitemap'          => false,
    'tree'             => $page('shop', array($widget('product_detail'))),
);
$pages[] = array(
    'key'              => 'cart',
    'requires'         => 'ecommerce',
    'name'             => lang('cart'),
    'folder'           => 'public',
    'title'            => lang('Shopping Cart'),
    'search'           => false,
    'sitemap'          => false,
    'noindex'          => true,
    'tree'             => $page('shop', array($widget('cart'))),
);
$pages[] = array(
    'key'              => 'checkout',
    'requires'         => 'ecommerce',
    'name'             => lang('checkout'),
    'folder'           => 'public',
    'title'            => lang('Checkout'),
    'search'           => false,
    'sitemap'          => false,
    'noindex'          => true,
    'tree'             => $page('shop', array($widget('checkout'))),
);
$pages[] = array(
    'key'              => 'order',
    'requires'         => 'ecommerce',
    'name'             => lang('order-summary'),
    'folder'           => 'public',
    'title'            => lang('Order Summary'),
    'search'           => false,
    'sitemap'          => false,
    'noindex'          => true,
    'tree'             => $page('shop', array($widget('order_view'))),
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
        'tree'    => $page('', array($widget($widget_key))),
    );
};
$pages[] = $sign_page('login', lang('login'), lang('Log In'), 'login');
$pages[] = $sign_page('register', lang('register'), lang('Sign Up'), 'register');
$pages[] = $sign_page('forgot_password', lang('forgot-password'), lang('Forgot Password'), 'forgot_password');
$pages[] = $sign_page('set_password', lang('set-password'), lang('Set Password'), 'set_password');
$pages[] = $sign_page('logout', lang('logout'), lang('Logout'), 'logout');

// My account: the member's own pages, behind the registration folder.
$account_cards = array(
    $link_card('bi-inbox', lang('Inbox'), lang('Your conversations with us and our answers.'), '{{page:inbox}}'),
    $link_card('bi-person-vcard', lang('My Profile'), lang('Your name, phone and address.'), '#', '__profile_url'),
    $link_card('bi-shield-lock', lang('Change Password'), lang('A new password for your account.'), '#', '__change_password_url'),
    $link_card('bi-envelope-paper', lang('Email Preferences'), lang('The mailing lists you receive.'), '#', '__email_preferences_url'),
);
if ($shop) {
    $account_cards[] = $link_card('bi-journal-bookmark', lang('Address Book'), lang('Where your orders are sent.'), '#', '__address_book_url');
}
$account_cards[] = $link_card('bi-people', lang('Staff Directory'), lang('The people you can reach, and how.'), '{{page:staff_directory}}');

$pages[] = array(
    'key'              => 'my_account',
    'name'             => lang('my-account'),
    'folder'           => 'registration',
    'title'            => lang('My Account'),
    'search'           => false,
    'sitemap'          => false,
    'tree'             => $page('', array($widget('my_account'))),
);
$pages[] = array_merge($sign_page('profile', lang('my-profile'), lang('My Profile'), 'profile'), array('folder' => 'registration'));
$pages[] = array_merge($sign_page('change_password', lang('change-password'), lang('Change Password'), 'change_password'), array('folder' => 'registration'));
$pages[] = array_merge($sign_page('email_preferences', lang('email-preferences'), lang('Email Preferences'), 'email_preferences'), array('folder' => 'registration'));
$pages[] = array_merge($sign_page('address_book', lang('address-book'), lang('Address Book'), 'address_book'), array('folder' => 'registration', 'requires' => 'ecommerce'));

// The inbox: the member's conversations (for the staff, everybody's), and
// the conversation page, where both sides answer as comments.
$pages[] = array(
    'key'              => 'inbox',
    'name'             => lang('inbox'),
    'folder'           => 'registration',
    'title'            => lang('Inbox'),
    'search'           => false,
    'sitemap'          => false,
    'tree'             => $page('', array(
        $page_header(lang('Inbox'), lang('The messages you sent us from the contact page, and our answers.')),
        $el('section', 'py-5', lang('Content'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $el('div', 'd-flex justify-content-end mb-3', lang('Inbox Toolbar'), array(
                            $link(lang('New message'), '{{page:contact}}', 'btn btn-primary btn-sm'),
                        )),
                        $widget('inbox_list'),
                    ), '', '9'),
                ), '', 'center'),
            )),
        )),
    )),
);
$pages[] = array(
    'key'              => 'conversation',
    'name'             => lang('conversation'),
    'folder'           => 'registration',
    'title'            => lang('Conversation'),
    'search'           => false,
    'sitemap'          => false,
    'comments'         => array('label' => lang('Reply'), 'allow_new' => true, 'auto_publish' => true, 'show_date' => true, 'login' => true,
                                'email_page' => '{{tab:email_reply}}', 'email_subject' => lang('There is a new reply to your conversation'),
                                'notify_email' => '{{site_email}}'),
    'tree'             => $page('', array(
        $widget('conversation'),
        $el('section', 'pb-5', lang('Replies'), array(
            $container(array(
                $row(array(
                    $col(array(
                        array('type' => 'region', 'props' => array('regionType' => 'comments_block', 'regionName' => ''), 'children' => array()),
                    ), '', '8'),
                ), '', 'center'),
            )),
        )),
    )),
);
$pages[] = array(
    'key'              => 'staff_directory',
    'name'             => lang('staff-directory'),
    'folder'           => 'registration',
    'title'            => lang('Staff Directory'),
    'search'           => false,
    'sitemap'          => false,
    'tree'             => $page('', array(
        $page_header(lang('Staff Directory'), lang('The people behind the site, and how to reach them.')),
        $el('section', 'py-5', lang('Directory'), array(
            $container(array($widget('staff_directory'))),
        )),
    )),
);

// The staff area, behind the private folder.
$pages[] = array(
    'key'              => 'staff',
    'name'             => lang('staff'),
    'folder'           => 'private',
    'title'            => lang('Staff Area'),
    'search'           => false,
    'sitemap'          => false,
    'noindex'          => true,
    'tree'             => $root(array(
        $account_bar(),
        $shared('header'),
        $shared('staff_bar'),
        $el('main', '', lang('Main Content'), array(
            $el('section', 'py-5 bg-body-tertiary border-bottom', lang('Staff Welcome'), array(
                $container(array(
                    $row(array(
                        $col(array(
                            $el('span', 'badge rounded-pill text-bg-primary mb-3', lang('Badge'), array(), array('text' => lang('Staff only'))),
                            $heading('h1', lang('Welcome to the staff area'), 'display-6 fw-bold mb-3'),
                            $para(lang('This site runs on Pinegrap. Everything a visitor sees — these pages, the forms, the blog, the shop — is edited in one place, and the work behind it lives in the same panel.'), 'lead text-body-secondary mb-4'),
                            $el('div', 'd-flex flex-wrap gap-2', lang('Buttons'), array(
                                $link(lang('Open the control panel'), $panel_url, 'btn btn-primary'),
                                $link(lang('New Blog Post'), '{{page:blog_new}}', 'btn btn-outline-secondary'),
                            )),
                        ), '', '8'),
                    )),
                )),
            )),
            $el('section', 'py-5', lang('Shortcuts'), array(
                $container(array(
                    $heading('h2', lang('Shortcuts'), 'h4 mb-4'),
                    $link_cards(array(
                        $link_card('bi-journal-plus', lang('New Blog Post'), lang('Write a post; it appears on the blog as soon as you send it.'), '{{page:blog_new}}'),
                        $link_card('bi-person-plus', lang('Add to the Staff Directory'), lang('Add a colleague to the directory members see.'), '{{page:staff_entry}}'),
                        $link_card('bi-chat-left-text', lang('All Conversations'), lang('Every message from the contact page. Answer below each one.'), '{{page:inbox}}'),
                    )),
                )),
            )),
            $el('section', 'pb-5', lang('About Pinegrap'), array(
                $container(array(
                    $heading('h2', lang('What Pinegrap does for this site'), 'h4 mb-4'),
                    $row(array(
                        $feature('bi-brush', lang('Visual editor'), lang('Pages are designed on the canvas: drag blocks, write on the page, publish every tab at once.')),
                        $feature('bi-ui-checks', lang('Forms that do the work'), lang('The contact form, this blog and the staff directory are all forms: sent here, listed there.')),
                        $feature('bi-folder-check', lang('Folders decide who sees what'), lang('public is open to all, registration to signed-in visitors, private to staff and the users you give access.')),
                        $feature('bi-kanban', lang('The workspace'), lang('Channels, tasks and the plan board keep the team on the same page, in the panel.')),
                        $feature('bi-bag-check', lang('A shop when you need one'), lang('Products, cart, checkout and orders come with the platform; switch the shop on in the settings.')),
                        $feature('bi-shield-lock', lang('Security built in'), lang('The firewall, sign-in limits and backups run without add-ons.')),
                    ), '4'),
                    $el('div', 'alert alert-secondary d-flex gap-2 mt-4 mb-0', lang('Access Note'), array(
                        $icon('bi-info-circle', 'mt-1'),
                        $span(lang('Only the site staff and the users given access to the private folder see these pages. To let someone in, open their account under Users and tick the folder in Private Content Access.'), ''),
                    )),
                )),
            )),
        )),
        $shared('footer'),
    )),
);
$staff_form_page = function ($key, $name, $title, $lead, $widget_key, $form) use ($root, $account_bar, $shared, $el, $page_header, $container, $row, $col, $widget) {
    return array(
        'key'     => $key,
        'name'    => $name,
        'folder'  => 'private',
        'title'   => $title,
        'search'  => false,
        'sitemap' => false,
        'noindex' => true,
        'form'    => $form,
        'tree'    => $root(array(
            $account_bar(),
            $shared('header'),
            $shared('staff_bar'),
            $el('main', '', lang('Main Content'), array(
                $page_header($title, $lead),
                $el('section', 'py-5', lang('Content'), array(
                    $container(array(
                        $row(array(
                            $col(array(
                                $el('div', 'card border-0 shadow-sm', lang('Form Card'), array(
                                    $el('div', 'card-body p-4', lang('Card Body'), array($widget($widget_key))),
                                )),
                            ), '', '8'),
                        ), '', 'center'),
                    )),
                )),
            )),
            $shared('footer'),
        )),
    );
};
$pages[] = $staff_form_page('blog_new', lang('new-blog-post'), lang('New Blog Post'),
    lang('Write the post; it appears on the blog as soon as you send it.'), 'blog_form',
    array(
        'form_name'            => lang('Blog Posts'),
        'confirmation_message' => lang('The post is published.'),
        'confirmation_page_id' => '{{tab:blog}}',
        // The site's sample blog posts are copied in when the form is made,
        // so the blog and the home page do not open empty
        // (pg_cf_seed_sample_records()).
        'sample_records'       => 'blog',
    ));
$pages[] = $staff_form_page('staff_entry', lang('add-to-staff-directory'), lang('Add to the Staff Directory'),
    lang('Signed-in members see the directory from their account.'), 'staff_form',
    array(
        'form_name'            => lang('Staff Directory'),
        'confirmation_message' => lang('The person is in the staff directory.'),
        'confirmation_page_id' => '{{tab:staff_directory}}',
    ));

// The site's error page: a page nobody can find, and every other error.
$pages[] = array(
    'key'     => 'error',
    'name'    => lang('page-not-found'),
    'folder'  => 'public',
    'title'   => lang('Page Not Found'),
    'search'  => false,
    'sitemap' => false,
    'noindex' => true,
    'tree'    => $page('', array($widget('error_page'))),
);

// ── E-mail pages ────────────────────────────────────────────────────────
// Sent as the body of an e-mail, never visited: the contact form's
// notification to the staff and its reply to the sender, the e-mail about a
// new reply or comment, the order receipt. E-mail programs drop linked
// stylesheets, so the few rules that shape them are written on the elements
// (inline style) next to the Bootstrap classes an e-mail program that does
// read the site's styles uses. Colours fall back to plain values first and
// take the palette's where CSS variables work.
$mail_style = function ($css) {
    return array('_attrs' => array(array('name' => 'style', 'value' => $css)));
};
$mail_page = function ($children) use ($el, $shared, $mail_style) {
    return array('type' => 'root', 'props' => array('cssClass' => ''), 'children' => array(
        $el('div', 'bg-body-tertiary py-4 px-3', lang('E-mail Frame'), array(
            $el('div', 'mx-auto bg-body border rounded-3 overflow-hidden', lang('E-mail Card'), array(
                $shared('email_header'),
                $el('div', 'p-4', lang('E-mail Body'), $children, $mail_style('padding:24px')),
                $shared('email_footer'),
            ), $mail_style('max-width:600px;margin:0 auto;background:#ffffff;border:1px solid #e5e7eb;border-radius:12px;overflow:hidden')),
        ), $mail_style('background:#f3f4f6;padding:24px 12px;font-family:-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,Helvetica,Arial,sans-serif;font-size:15px;line-height:1.55;color:#1f2937')),
    ));
};
$mail_heading = function ($text) use ($heading) {
    return $heading('h1', $text, 'h4 fw-bold mb-3', array('_attrs' => array(array('name' => 'style', 'value' => 'font-size:22px;font-weight:700;margin:0 0 16px;color:#111827'))));
};
$mail_para = function ($text, $class = 'mb-3') use ($para) {
    return $para($text, $class, array('_attrs' => array(array('name' => 'style', 'value' => 'margin:0 0 16px'))));
};
$mail_button = function ($text, $href) use ($el, $link, $mail_style) {
    return $el('p', 'my-4', lang('Button'), array(
        $link($text, $href, 'btn btn-primary px-4', array(array('name' => 'style', 'value' =>
            'display:inline-block;padding:10px 22px;border-radius:8px;font-weight:600;text-decoration:none;'
            . 'background-color:#0d6efd;background-color:var(--bs-primary);color:#ffffff;color:var(--pg-on-primary,#ffffff)'))),
    ), $mail_style('margin:24px 0'));
};
// A label and a value on one line; the line leaves when the value is empty.
$mail_line = function ($label, $values) use ($el, $span, $mail_style) {
    return $el('p', 'pg-hide-if-empty mb-1', lang('Line'), array(
        $span($label . ': ', 'text-body-secondary', lang('Label'), array('_attrs' => array(array('name' => 'style', 'value' => 'color:#6b7280')))),
        $el('span', 'pg-field-value fw-semibold', lang('Value'), $values),
    ), $mail_style('margin:0 0 4px'));
};
$mail_box = function ($children, $name) use ($el, $mail_style) {
    return $el('div', 'bg-body-tertiary rounded-3 p-3 my-3', $name, $children,
        $mail_style('background:#f3f4f6;border-radius:8px;padding:16px;margin:16px 0'));
};

// The contact form's notification: what the staff receive for a message.
$pages[] = array(
    'key'     => 'email_new_message',
    'name'    => lang('email-new-message'),
    'folder'  => 'private',
    'title'   => lang('New Message E-mail'),
    'search'  => false,
    'sitemap' => false,
    'noindex' => true,
    'tree'    => $mail_page(array($widget('email_message_admin'))),
);
// The contact form's reply to the sender.
$pages[] = array(
    'key'     => 'email_message_received',
    'name'    => lang('email-message-received'),
    'folder'  => 'private',
    'title'   => lang('Message Received E-mail'),
    'search'  => false,
    'sitemap' => false,
    'noindex' => true,
    'tree'    => $mail_page(array($widget('email_message_sender'))),
);
// A new reply or comment: sent to the sender of a conversation and to the
// visitors watching a page. The System region carries the comment and a
// "View or Reply" button.
$pages[] = array(
    'key'     => 'email_reply',
    'name'    => lang('email-new-reply'),
    'folder'  => 'private',
    'title'   => lang('New Reply E-mail'),
    'search'  => false,
    'sitemap' => false,
    'noindex' => true,
    'tree'    => $mail_page(array(
        $mail_heading(lang('There is a new reply')),
        $mail_para(lang('Something new was written on a page you follow. You can read it below and answer it on the page.')),
        array('type' => 'region', 'props' => array('regionType' => 'system', 'regionName' => ''), 'children' => array()),
    )),
);
// The order receipt, sent to the billing address when an order is placed.
$pages[] = array(
    'key'      => 'email_order',
    'requires' => 'ecommerce',
    'name'     => lang('email-order-receipt'),
    'folder'   => 'private',
    'title'    => lang('Order Receipt E-mail'),
    'search'   => false,
    'sitemap'  => false,
    'noindex'  => true,
    'tree'     => $mail_page(array($widget('email_order'))),
);

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

$widgets = array(

    // The header's view of the session, one row on every page.
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
                $link(lang('Log In'), '#', 'btn btn-sm btn-outline-primary', array(), array('_bindings' => array('href' => '__login_url'))),
                $link(lang('Sign Up'), '#', 'btn btn-sm btn-primary', array(), array('_bindings' => array('href' => '__register_url'))),
            ), $show_when('is_signed_out')),
            $el('div', 'dropdown', lang('Signed In'), array(
                $el('a', 'd-flex align-items-center gap-2 link-body-emphasis text-decoration-none dropdown-toggle', lang('Account Button'), array(
                    $image('https://placehold.co/64x64/4f6bed/ffffff?text=JC', lang('Jane Cooper'), 'rounded-circle object-fit-cover',
                        array('width' => '32', 'height' => '32', '_bindings' => array('src' => '__user_avatar_url', 'alt' => '__user_name'))),
                    $span(lang('Jane'), 'fw-semibold small', lang('First Name'), $bind('__user_first_name')),
                ), array('href' => '#', '_attrs' => array(
                    array('name' => 'role', 'value' => 'button'),
                    array('name' => 'data-bs-toggle', 'value' => 'dropdown'),
                    array('name' => 'aria-expanded', 'value' => 'false'),
                ))),
                $el('ul', 'dropdown-menu dropdown-menu-end shadow-sm', lang('Account Menu'), array(
                    $el('li', 'px-3 py-2', lang('Account Summary'), array(
                        $span(lang('Jane Cooper'), 'd-block fw-semibold', lang('Full Name'), $bind('__user_name')),
                        $span(lang('jane.cooper@example.com'), 'd-block small text-body-secondary', lang('Email'), $bind('__user_email')),
                    )),
                    $login_divider(),
                    $login_item(lang('My Account'), '__my_account_url'),
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

    // The way to the cart, beside the login region (shop only).
    'cart_link' => array(
        'page'     => '',
        'requires' => 'ecommerce',
        'slug'     => lang('cart-link'),
        'config'   => array('regionType' => 'cart_link', 'cart_page_id' => '{{tab:cart}}'),
        'tree'     => 'starter',
    ),

    // Contact: the fields are the page's form (form_source 'page').
    'contact_form' => array(
        'page'   => 'contact',
        'slug'   => 'form',
        'config' => array('regionType' => 'custom_form', 'form_source' => 'page'),
        'tree'   => $widget_root(array(
            // Wired to the contact, as on the legacy contact form: a
            // signed-in visitor finds them filled in, and the contact a
            // guest's submission creates carries them.
            $row(array(
                $col(array($field('hp-contact-first-name', lang('First Name'), $input('hp-contact-first-name', 'text', 'first_name', 'given-name', true, array(), array('contact_field' => 'first_name')))), '6'),
                $col(array($field('hp-contact-last-name', lang('Last Name'), $input('hp-contact-last-name', 'text', 'last_name', 'family-name', false, array(), array('contact_field' => 'last_name')))), '6'),
                $col(array($field('hp-contact-email', lang('Email'), $input('hp-contact-email', 'email', 'email', 'email', true, array(), array('contact_field' => 'email_address')))), '6'),
                $col(array($field('hp-contact-phone', lang('Phone'), $input('hp-contact-phone', 'tel', 'phone', 'tel', false, array(), array('contact_field' => 'business_phone')))), '6'),
                $col(array($field('hp-contact-company', lang('Company'), $input('hp-contact-company', 'text', 'company', 'organization', false, array(), array('contact_field' => 'company')))), '6'),
                $col(array($field('hp-contact-subject', lang('Subject'), $input('hp-contact-subject', 'text', 'subject', 'off'))), '6'),
            ), '3'),
            $field('hp-contact-message', lang('Your Message'), $textarea('hp-contact-message', 'message', 6)),
            $captcha(),
            $submit(lang('Send Message'), lang('Send')),
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
                            $heading('h2', lang('Post not found'), 'h3 mt-3'),
                            $para(lang('This post could not be found. It may have been removed.'), 'text-body-secondary mb-4', $bind('not_found')),
                            $link(lang('Back to the blog'), '{{page:blog}}', 'btn btn-primary'),
                        ), $show_when('record_not_found')),
                        $el('article', '', lang('Post'), array(
                            $el('div', 'd-flex flex-wrap align-items-center justify-content-between gap-2 mb-3', lang('Toolbar'), array(
                                $link(lang('All posts'), '{{page:blog}}', 'small text-decoration-none'),
                                $edit_button(),
                            )),
                            $heading('h1', lang('Five small changes that make a site easier to use'), 'display-6 fw-bold mb-3', $bind('title')),
                            $el('p', 'd-flex flex-wrap align-items-center gap-2 small text-body-secondary mb-4', lang('Post Details'), array(
                                $icon('bi-calendar3', ''),
                                $span(lang('Date'), '', lang('Date'), $bind('submitted_date_and_time')),
                                $icon('bi-person', 'ms-2'),
                                $span(lang('Jane Cooper'), '', lang('Author'), $bind('submitter')),
                            )),
                            $optional_image('cover_image', 'title', 'rounded-3 object-fit-cover', 'ratio ratio-16x9 mb-4',
                                'https://picsum.photos/seed/pinegrap-post/1200/675', lang('Cover Image')),
                            $para(lang('A short summary of the post. It is what makes a visitor open it, so two sentences are enough.'), 'lead mb-4', $bind('summary')),
                            $el('div', 'fs-6 lh-lg', lang('Post Content'), array(), array_merge(
                                array('text' => lang('The body of the post. Write it on the staff page; line breaks are kept.')), $bind('content'))),
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
            $field('hp-post-title', lang('Title'), $input('hp-post-title', 'text', 'title', 'off', true, array(array('name' => 'maxlength', 'value' => '150')))),
            $field('hp-post-summary', lang('Summary'), $textarea('hp-post-summary', 'summary', 3)),
            $field('hp-post-cover', lang('Cover Image'), $file('hp-post-cover', 'cover_image', 'public')),
            $field('hp-post-content', lang('Content'), $textarea('hp-post-content', 'content', 14)),
            $submit(lang('Publish'), lang('Send')),
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
                $col(array($field('hp-staff-name', lang('Full Name'), $input('hp-staff-name', 'text', 'full_name', 'off'))), '6'),
                $col(array($field('hp-staff-title', lang('Job Title'), $input('hp-staff-title', 'text', 'job_title', 'off'))), '6'),
                $col(array($field('hp-staff-department', lang('Department'), $input('hp-staff-department', 'text', 'department', 'off', false))), '6'),
                // Public: the about page shows the photo to every visitor.
                $col(array($field('hp-staff-photo', lang('Photo'), $file('hp-staff-photo', 'photo', 'public'))), '6'),
                $col(array($field('hp-staff-email', lang('Email'), $input('hp-staff-email', 'email', 'email', 'off', false))), '6'),
                $col(array($field('hp-staff-phone', lang('Phone'), $input('hp-staff-phone', 'tel', 'phone', 'off', false))), '6'),
            ), '3'),
            $field('hp-staff-bio', lang('A few words'), $textarea('hp-staff-bio', 'bio', 3, false)),
            $submit(lang('Add'), lang('Send')),
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
                    $el('div', 'card h-100 border-0 shadow-sm', lang('Person Card'), array(
                        $el('div', 'card-body d-flex align-items-start gap-3', lang('Card Body'), array(
                            $optional_image('photo', 'full_name', 'rounded-circle object-fit-cover', 'flex-shrink-0',
                                'https://picsum.photos/seed/pinegrap-person/128/128', lang('Photo')),
                            $el('div', 'min-w-0', lang('Details'), array(
                                $heading('h2', lang('Jane Cooper'), 'h6 mb-0', $bind('full_name')),
                                $para(lang('Customer Support'), 'small text-body-secondary mb-2', $bind('job_title')),
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
                    )),
                ), '6', '4'),
            ))), '4'),
        )),
    ),

    // The about page's team: the people of the staff directory, in the order
    // they were added. The card shows the name, job title and photo; the
    // contact details stay in the directory.
    'about_team' => array(
        'page'   => 'about',
        'slug'   => lang('form-list'),
        'config' => array(
            'regionType'          => 'form_list_view',
            'custom_form_page_id' => '{{tab:staff_entry}}',
            'items_per_page'      => 12,
            'max_results'         => 12,
            'order_by_field'      => 'submitted_date_and_time',
            'order_by_direction'  => 'ASC',
            'empty_message'       => lang('Our team will be introduced here soon.'),
        ),
        'tree'   => $widget_root(array(
            $row(array($loop(array($person()))), '4', 'center'),
        )),
    ),

    // The inbox: conversations the member started (a signed-in submission
    // of the contact form), or is watching; the staff see them all.
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
            $el('div', 'list-group shadow-sm', lang('Conversation List'), array(
                $loop(array(
                    $el('a', 'list-group-item list-group-item-action py-3', lang('Conversation'), array(
                        $el('div', 'd-flex w-100 justify-content-between gap-3', lang('Top Line'), array(
                            $span(lang('A question about my order'), 'fw-semibold text-truncate', lang('Subject'), $bind('subject')),
                            $span(lang('Date'), 'small text-body-secondary text-nowrap', lang('Date'), $bind('submitted_date_and_time')),
                        )),
                        $el('div', 'd-flex align-items-center gap-2 small text-body-secondary mt-1', lang('Replies'), array(
                            $icon('bi-chat-left-text', ''),
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
                            $heading('h2', lang('Conversation not found'), 'h3 mt-3'),
                            $para(lang('This conversation could not be found, or it is not yours to read.'), 'text-body-secondary mb-4', $bind('not_found')),
                            $link(lang('Back to the inbox'), '{{page:inbox}}', 'btn btn-primary'),
                        ), $show_when('record_not_found')),
                        $el('div', '', lang('Conversation'), array(
                            $el('div', 'alert alert-success d-flex gap-2', lang('Sent Notice'), array(
                                $icon('bi-check-circle', 'mt-1'),
                                $span(lang('Thank you, your message has reached us. Our answers appear on this page and in your inbox, and you can write back below.'), ''),
                            ), array_merge(array('_attrs' => array(array('name' => 'role', 'value' => 'status'))), $show_when('submitted_this_session'))),
                            $el('div', 'alert alert-info', lang('New Account'), array(
                                $el('p', 'fw-semibold mb-2', lang('Title'), array(), array('text' => lang('We opened an account for you'))),
                                $el('p', 'mb-1', lang('Sign-in Email'), array(
                                    $span(lang('Email') . ': ', ''),
                                    $span('sample@example.com', 'fw-semibold', lang('Value'), $bind('__new_account_email')),
                                )),
                                $el('p', 'mb-2', lang('Temporary Password'), array(
                                    $span(lang('Temporary password') . ': ', ''),
                                    $span('a1b2c3d4', 'fw-semibold font-monospace', lang('Value'), $bind('__new_account_password')),
                                )),
                                $para(lang('You are signed in. Keep these details, and choose a password of your own.'), 'small mb-2'),
                                $link(lang('Change Password'), '{{page:change_password}}', 'btn btn-sm btn-primary'),
                            ), $show_when('has_new_account')),
                            $el('div', 'd-flex flex-wrap align-items-center justify-content-between gap-2 mb-3', lang('Toolbar'), array(
                                $link(lang('Inbox'), '{{page:inbox}}', 'small text-decoration-none'),
                                $edit_button(),
                            )),
                            $heading('h1', lang('A question about my order'), 'h3 fw-bold mb-1', $bind('subject')),
                            $el('p', 'd-flex flex-wrap align-items-center gap-2 small text-body-secondary mb-4', lang('Conversation Details'), array(
                                $icon('bi-calendar3', ''),
                                $span(lang('Date'), '', lang('Date'), $bind('submitted_date_and_time')),
                                $icon('bi-hash', 'ms-2'),
                                $span('ABC123', '', lang('Reference Code'), $bind('reference_code')),
                            )),
                            $el('div', 'card border-0 bg-body-tertiary', lang('First Message'), array(
                                $el('div', 'card-body p-4', lang('Card Body'), array(
                                    $el('p', 'd-flex align-items-center gap-2 fw-semibold mb-2', lang('Sender'), array(
                                        $icon('bi-person-circle', 'fs-5 text-primary'),
                                        $el('span', '', lang('Name'), array(
                                            $span(lang('Jane'), '', lang('First Name'), $bind('first_name')),
                                            $span(lang('Cooper'), '', lang('Last Name'), $bind('last_name')),
                                        )),
                                    )),
                                    $el('div', 'lh-lg', lang('Message'), array(), array_merge(
                                        array('text' => lang('Hello, I would like to know when my order will be shipped.')), $bind('message'))),
                                )),
                            )),
                        ), $show_when('record_found')),
                    ), '', '8'),
                ), '', 'center'),
            ), 'py-5'),
        )),
    ),

    // The member's account, on the widget's own page.
    'my_account' => array(
        'page'   => 'my_account',
        'slug'   => lang('my-account'),
        'config' => array(
            'regionType'       => 'my_account',
            'login_page_id'    => '{{tab:login}}',
            'show_submissions' => false,
        ),
        'tree'   => $widget_root(array(
            $container(array(
                $el('div', 'd-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 mb-4', lang('Account Header'), array(
                    $el('div', '', lang('Greeting'), array(
                        $para(lang('Welcome back'), 'text-body-secondary mb-1'),
                        $heading('h1', lang('Jane Cooper'), 'h3 fw-bold mb-1', $bind('__display_name')),
                        $para(lang('jane.cooper@example.com'), 'small text-body-secondary mb-0', $bind('email_address')),
                    )),
                    $link(lang('Log out'), '#', 'btn btn-outline-secondary', array(), array('_bindings' => array('href' => '__logout_url'))),
                )),
                $link_cards($account_cards, 'mb-5'),
                $heading('h2', lang('My Orders'), 'h5 mb-3', $show_when('has_orders')),
                $el('div', 'mb-4', lang('Order History'), array(), array('_bindings' => array('section' => 'order_history'))),
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
    'address_book' => array(
        'page'     => 'address_book',
        'requires' => 'ecommerce',
        'slug'     => lang('address-book'),
        'config'   => array('regionType' => 'address_book', 'my_account_page_id' => '{{tab:my_account}}'),
        'tree'     => 'starter',
    ),
    'error_page' => array(
        'page'   => 'error',
        'slug'   => lang('error'),
        'config' => array('regionType' => 'error_page'),
        'tree'   => 'starter',
    ),

    // The shop.
    'shop_catalog' => array(
        'page'     => 'shop',
        'requires' => 'ecommerce',
        'slug'     => lang('catalog'),
        'config'   => array(
            'regionType'               => 'catalog_listing',
            // The catalog's top group, not 0: the grid then holds its
            // categories (opened in place) and its variant sets (one card
            // each, the variant picked on the product page). 0 would list
            // every product of the site flat, each variant on its own card.
            'product_group_id'         => '{{product_group:root}}',
            // Browsing: the categories open in place, and the filters
            // (price, in stock, the products' attributes) sit in the side
            // panel behind the Filters button, beside the search and sorting.
            'group_navigation'         => 'drill_down',
            'search_enabled'           => true,
            'sort_control_enabled'     => true,
            'price_filter_enabled'     => true,
            'stock_filter_enabled'     => true,
            'show_attribute_filters'   => true,
            'order_by_field'           => 'sort_order',
            'detail_page_id'           => '{{tab:product}}',
            'add_to_cart_next_page_id' => '{{tab:cart}}',
            'empty_message'            => lang('Product not found.'),
        ),
        'tree'     => 'starter',
    ),
    'product_detail' => array(
        'page'     => 'product',
        'requires' => 'ecommerce',
        'slug'     => lang('product-detail'),
        'config'   => array(
            'regionType'              => 'catalog_item_view',
            'product_group_id'        => 0,
            'next_page_id'            => '{{tab:cart}}',
            'catalog_listing_page_id' => '{{tab:shop}}',
            'not_found_message'       => lang('Product not found.'),
        ),
        'tree'     => 'starter',
    ),
    'cart' => array(
        'page'     => 'cart',
        'requires' => 'ecommerce',
        'slug'     => lang('cart'),
        'config'   => array(
            'regionType'                    => 'shopping_cart',
            'next_page_id_with_shipping'    => '{{tab:checkout}}',
            'next_page_id_without_shipping' => '{{tab:checkout}}',
            'detail_page_id'                => '{{tab:product}}',
        ),
        'tree'     => 'starter',
    ),
    'checkout' => array(
        'page'     => 'checkout',
        'requires' => 'ecommerce',
        'slug'     => lang('checkout'),
        'config'   => array(
            'regionType'            => 'express_order',
            'next_page_id'          => '{{tab:order}}',
            'order_receipt_email_page_id' => '{{tab:email_order}}',
            'order_receipt_email_subject' => lang('Order Receipt #'),
            'cart_section_label'    => lang('Your cart'),
            'purchase_button_label' => lang('Complete Order'),
            'update_button_label'   => lang('Update'),
        ),
        'tree'     => 'starter',
    ),
    'order_view' => array(
        'page'     => 'order',
        'requires' => 'ecommerce',
        'slug'     => lang('order'),
        'config'   => array('regionType' => 'order_view', 'date_format' => ''),
        'tree'     => 'starter',
    ),

    // The e-mail pages' widgets. A form's e-mail page is drawn for the
    // submission just sent and the receipt for the order just placed (the
    // sender passes it): no ?r= and no visitor check is involved.
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
                    $span(' ', ''),
                    $span(lang('Cooper'), '', lang('Last Name'), $bind('last_name')),
                )),
                $mail_line(lang('Email'), array($span('jane.cooper@example.com', '', lang('Email'), $bind('email')))),
                $mail_line(lang('Phone'), array($span('+90 555 000 00 00', '', lang('Phone'), $bind('phone')))),
                $mail_line(lang('Company'), array($span(lang('Company'), '', lang('Company'), $bind('company')))),
                $mail_line(lang('Subject'), array($span(lang('A question about my order'), '', lang('Subject'), $bind('subject')))),
            ), lang('Sender')),
            $el('div', 'lh-lg', lang('Message'), array(), array_merge(
                array('text' => lang('Hello, I would like to know when my order will be shipped.')), $bind('message'))),
            $mail_button(lang('Open the conversation'), '{{page:conversation}}?r=^^reference_code^^'),
            $el('p', 'small text-body-secondary mb-0', lang('Reference'), array(
                $span(lang('Reference Code') . ': ', ''),
                $span('ABC123', '', lang('Reference Code'), $bind('reference_code')),
            ), $mail_style('font-size:13px;color:#6b7280;margin:0')),
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
            $el('p', 'mb-3', lang('Greeting'), array(
                $span(lang('Hello') . ' ', ''),
                $span(lang('Jane'), '', lang('First Name'), $bind('first_name')),
            ), $mail_style('margin:0 0 16px')),
            $mail_para(lang('Thank you for writing to us. Our answer will be on the conversation page and in your inbox, and we will let you know by e-mail when it is there.')),
            $mail_box(array(
                $el('p', 'fw-semibold mb-2', lang('Subject'), array(), array_merge(
                    array('text' => lang('A question about my order'), '_attrs' => array(array('name' => 'style', 'value' => 'font-weight:600;margin:0 0 8px'))), $bind('subject'))),
                $el('div', 'lh-lg', lang('Message'), array(), array_merge(
                    array('text' => lang('Hello, I would like to know when my order will be shipped.')), $bind('message'))),
            ), lang('Your Message')),
            $mail_button(lang('Open the conversation'), '{{page:conversation}}?r=^^reference_code^^'),
            // The account the form opened for a visitor who had none: the
            // address it signs in with, and how to choose a password (the
            // temporary one is shown on the conversation page, not mailed).
            $el('div', 'border-top pt-3', lang('New Account'), array(
                $el('p', 'mb-2', lang('Sign-in Email'), array(
                    $span(lang('We opened an account for you with this address:') . ' ', ''),
                    $span('sample@example.com', 'fw-semibold', lang('Value'), $bind('__new_account_email')),
                ), $mail_style('margin:0 0 8px')),
                $el('p', 'mb-0', lang('Password'), array(
                    $span(lang('To choose your password, use') . ' ', ''),
                    $link(lang('Forgot Password'), '{{page:forgot_password}}', ''),
                ), $mail_style('margin:0')),
            ), array_merge($mail_style('border-top:1px solid #e5e7eb;padding-top:16px'), $show_when('has_new_account'))),
        )),
    ),
    'email_order' => array(
        'page'     => 'email_order',
        'requires' => 'ecommerce',
        'slug'     => lang('order'),
        'config'   => array('regionType' => 'order_view', 'date_format' => ''),
        'tree'     => $widget_root(array(
            $mail_heading(lang('Thank you for your order')),
            $el('p', 'mb-3', lang('Order Details'), array(
                $span(lang('Order Number') . ': ', 'text-body-secondary'),
                $span('PG-12345', 'fw-semibold', lang('Order Number'), $bind('__order_no')),
                $span(' · ', 'text-body-secondary'),
                $span('27.09.2026', '', lang('Date'), $bind('__order_date')),
            ), $mail_style('margin:0 0 16px')),
            $el('table', 'table align-middle mb-3', lang('Order Items'), array(
                $el('tbody', '', lang('Rows'), array(
                    $loop(array(
                        $el('tr', '', lang('Row'), array(
                            $el('td', 'ps-0', lang('Product'), array(
                                $span(lang('Sample Product Name'), 'fw-semibold', lang('Product'), $bind('__item_short_description')),
                                $span(' × ', 'text-body-secondary'),
                                $span('1', '', lang('Quantity'), $bind('__item_qty')),
                            ), $mail_style('padding:8px 0;border-bottom:1px solid #e5e7eb')),
                            $el('td', 'pe-0 text-end', lang('Total'), array(
                                $span('₺0,00', '', lang('Total'), $bind('__item_total')),
                            ), $mail_style('padding:8px 0;border-bottom:1px solid #e5e7eb;text-align:right')),
                        )),
                    )),
                )),
            ), $mail_style('width:100%;border-collapse:collapse;margin:0 0 16px')),
            $el('table', 'table table-sm mb-4', lang('Totals'), array(
                $el('tbody', '', lang('Rows'), array(
                    $el('tr', '', lang('Subtotal'), array(
                        $el('td', 'ps-0 text-body-secondary', lang('Label'), array($span(lang('Subtotal'), ''))),
                        $el('td', 'pe-0 text-end', lang('Value'), array($span('₺0,00', '', lang('Subtotal'), $bind('__order_subtotal'))), $mail_style('text-align:right')),
                    )),
                    $el('tr', '', lang('Discount'), array(
                        $el('td', 'ps-0 text-body-secondary', lang('Label'), array($span(lang('Discount'), ''))),
                        $el('td', 'pe-0 text-end', lang('Value'), array($span('₺0,00', '', lang('Discount'), $bind('__order_discount'))), $mail_style('text-align:right')),
                    ), $show_when('has_discount')),
                    $el('tr', '', lang('Shipping'), array(
                        $el('td', 'ps-0 text-body-secondary', lang('Label'), array($span(lang('Shipping'), ''))),
                        $el('td', 'pe-0 text-end', lang('Value'), array($span('₺0,00', '', lang('Shipping'), $bind('__order_shipping'))), $mail_style('text-align:right')),
                    ), $show_when('has_shipping_cost')),
                    $el('tr', '', lang('Tax'), array(
                        $el('td', 'ps-0 text-body-secondary', lang('Label'), array($span(lang('VAT'), ''))),
                        $el('td', 'pe-0 text-end', lang('Value'), array($span('₺0,00', '', lang('VAT'), $bind('__order_tax'))), $mail_style('text-align:right')),
                    ), $show_when('has_tax')),
                    $el('tr', 'fw-bold', lang('Total'), array(
                        $el('td', 'ps-0', lang('Label'), array($span(lang('Total'), ''))),
                        $el('td', 'pe-0 text-end', lang('Value'), array($span('₺0,00', '', lang('Total'), $bind('__order_total'))), $mail_style('text-align:right;font-weight:700')),
                    )),
                )),
            ), $mail_style('width:100%;border-collapse:collapse;margin:0 0 24px')),
            $el('div', 'row g-3', lang('Addresses'), array(
                $el('div', 'col-sm-6', lang('Billing Address'), array(
                    $el('p', 'small text-body-secondary mb-1', lang('Label'), array(), array('text' => lang('Billing Address'))),
                    $el('div', '', lang('Address'), array(), array_merge(array('text' => lang('Sample Street 1, 34000 Istanbul, Türkiye')), $bind('__billing_address_html'))),
                )),
                $el('div', 'col-sm-6', lang('Shipping Address'), array(
                    $el('p', 'small text-body-secondary mb-1', lang('Label'), array(), array('text' => lang('Shipping Address'))),
                    $el('div', '', lang('Address'), array(), array_merge(array('text' => lang('Sample Street 1, 34000 Istanbul, Türkiye')), $bind('__shipping_address_html'))),
                ), $show_when('has_shipping')),
            )),
            $mail_button(lang('View your order'), '{{page:order}}?order_id=^^__order_id^^'),
        )),
    ),
);

return array(
    'name'        => lang('Say hello to Pinegrap'),
    'version'     => '2.4.1',
    'framework'   => 'bootstrap5',
    'order'       => 10,
    'icon'        => 'bi-hand-thumbs-up',
    'description' => lang('A whole starter site: home, about, services, a blog, a contact page whose messages become conversations, sign-in and account pages with an inbox, a staff area and, with the shop on, a small shop.'),

    // What the installer lists on the template's card, after the points
    // every template shares.
    'highlights'  => array(
        lang('A blog, a contact form, member pages and a shop come ready.'),
        lang('The e-mails the site sends are pages too: the notification, the reply and the order receipt are designed in the same editor.'),
    ),

    // Made (or found again) when the template is opened; see _pg_tpl_folders().
    'folders'     => array(
        'root'         => array('name' => 'pinegrap_hello', 'access' => 'public'),
        'public'       => array('name' => 'public', 'parent' => 'root', 'access' => 'public'),
        'registration' => array('name' => 'registration', 'parent' => 'root', 'access' => 'registration'),
        'private'      => array('name' => 'private', 'parent' => 'root', 'access' => 'private'),
    ),

    'pages'       => $pages,

    // System widgets the pages place: shared_components rows, made when the
    // template is opened.
    'widgets'     => $widgets,

    // The parts every page repeats, one shared component each: made when
    // the template is opened (only the ones some page places), discarded
    // again when the editor is left without publishing.
    'shared'      => array(
        'header'    => array('name' => lang('Site Header'), 'tree' => $navbar()),
        'footer'    => array('name' => lang('Site Footer'), 'tree' => $footer()),
        'cta'       => array('name' => lang('Contact Call to Action'), 'tree' => $cta()),
        'staff_bar' => array('name' => lang('Staff Bar'), 'tree' => $staff_bar()),
        // The e-mail pages' own top and bottom: the site's name, a line
        // under it and a way back to the site.
        'email_header' => array('name' => lang('E-mail Header'), 'tree' =>
            $el('div', 'px-4 py-3 border-bottom', lang('E-mail Header'), array(
                $link('{{site_name}}', '{{page:home}}', 'fs-5 fw-bold text-decoration-none link-body-emphasis',
                    array(array('name' => 'style', 'value' => 'font-size:18px;font-weight:700;color:#111827;text-decoration:none'))),
            ), $mail_style('padding:16px 24px;border-bottom:1px solid #e5e7eb'))),
        'email_footer' => array('name' => lang('E-mail Footer'), 'tree' =>
            $el('div', 'px-4 py-3 border-top small text-body-secondary', lang('E-mail Footer'), array(
                $para('© {{year}} {{site_name}}', 'mb-1', array('_attrs' => array(array('name' => 'style', 'value' => 'margin:0 0 4px')))),
                $link(lang('Visit the website'), '{{page:home}}', 'link-secondary',
                    array(array('name' => 'style', 'value' => 'color:#6b7280'))),
            ), $mail_style('padding:16px 24px;border-top:1px solid #e5e7eb;font-size:13px;color:#6b7280'))),
    ),
);
