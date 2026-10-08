<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Playground": a cheerful personal site for one person. A
 * big hello with stickers and a round photo, what they are up to right now,
 * the things they make (each opening a detail window), a photo carousel, a
 * blog whose posts take comments from any visitor, a guest book with a wall
 * of the latest hellos, a "now" page, an about page with fun facts, very
 * serious skills and a timeline, and a page to say hi. Built on Bootstrap 5.
 * Read by pg_design_templates() (includes/fn/designer.php).
 *
 * The pages go into two folders under pinegrap_playground: public (open to
 * everybody) and private (the site staff and the users given access to the
 * folder). The private folder holds the page the blog posts are written on
 * and the e-mails the site sends: the say-hi form's notification and its
 * reply to the sender, and the e-mail about a new comment.
 *
 * The blog's form starts with the site's sample posts ('sample_records'),
 * so the blog and the home page do not open empty. The guest book's form is
 * the guest book page's own; the wall beside it lists what was sent there,
 * newest first, to every visitor.
 *
 * Placeholders, filled when the template is opened (pg_design_template_prepare()):
 *   {{page:<key>}}    address of the template page with that key
 *   {{tab:<key>}}     that page in a widget setting; its id once published
 *   {{folder:<key>}}  id of that template folder
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

// ── Node builders ───────────────────────────────────────────────────────
// Same shapes the editor's createNode() produces; the editor gives every
// node its id when the tabs open. The name a structural node is known by in
// the layer tree is its label (_label), shown beside the element's own tag.
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
$icon = function ($name, $class) use ($el, $attr) {
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
// $xs 'col' is a bare .col: the cell of a row-cols-* row, which sets the
// width itself.
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
// Bound to a data token: the words stay what the canvas shows until the
// page is published and the widget fills them in.
$bind = function ($token) {
    return array('_bindings' => array('text' => $token));
};
$show_when = function ($flag) {
    return array('_bindings' => array('eo_visible_if' => $flag));
};
// The language switcher button: the server draws it for each request and
// leaves it out while the site has a single language, so it can stay in the
// header of every site.
$lang_switcher = function ($class = '', $variant = 'secondary') {
    return array('type' => 'component', 'props' => array(
        'componentType' => 'language_switcher',
        'variant' => $variant, 'outline' => true, 'size' => 'sm',
        'align' => 'end', 'display' => 'name', 'icon' => 'translate',
        'cssClass' => $class,
    ), 'children' => array());
};

// The sample photos (picsum.photos gives the same picture for the same seed).
$photo = function ($seed, $w, $h) {
    return 'https://picsum.photos/seed/pl-' . $seed . '/' . $w . '/' . $h;
};
$avatar = 'https://placehold.co/96x96/a21caf/ffffff?text=JC';

// ── The parts every page repeats ────────────────────────────────────────

// The header: the round avatar, the name and an "open to projects" sticker
// on the left, the menu and the language switcher on the right, folded
// behind a toggler on a phone.
// The link to the current page is marked active on the server (smart active
// state), which is what lets one header serve every page.
$header = function () use ($el, $link, $image, $span, $attr, $avatar, $lang_switcher) {
    $item = function ($key, $text) use ($el, $link) {
        return $el('li', 'nav-item', lang('Nav Item'), array(
            $link($text, '{{page:' . $key . '}}', 'nav-link px-md-1 px-lg-2 text-nowrap'),
        ));
    };
    return $el('header', 'bg-body border-bottom border-3 border-dark', lang('Site Header'), array(
        $el('nav', 'navbar navbar-expand-md py-3', lang('Navbar'), array(
            $el('div', 'container', lang('Navbar Container'), array(
                $el('a', 'navbar-brand d-flex align-items-center gap-2 me-2', lang('Brand'), array(
                    $image($avatar, lang('Jane Cooper'), 'rounded-circle border border-3 border-dark flex-shrink-0',
                        array('width' => '44', 'height' => '44')),
                    $el('span', 'd-flex flex-column align-items-start lh-sm', lang('Text'), array(
                        $span(lang('Jane Cooper'), 'fw-bolder', lang('Name')),
                        $span(lang('open to projects'), 'badge rounded-pill text-bg-success border border-2 border-dark', lang('Sticker')),
                    )),
                ), array('href' => '{{page:home}}')),
                $el('button', 'navbar-toggler border-2 border-dark rounded-pill', lang('Toggler'), array(
                    $el('span', 'navbar-toggler-icon', lang('Toggler Icon')),
                ), array('_attrs' => array(
                    $attr('type', 'button'),
                    $attr('data-bs-toggle', 'collapse'),
                    $attr('data-bs-target', '#pl-nav'),
                    $attr('aria-controls', 'pl-nav'),
                    $attr('aria-expanded', 'false'),
                    $attr('aria-label', lang('Toggle navigation')),
                ))),
                $el('div', 'collapse navbar-collapse', lang('Nav Links'), array(
                    $el('ul', 'navbar-nav ms-auto mt-3 mt-md-0 fw-semibold', lang('Nav Menu'), array(
                        $item('about', lang('About')),
                        $item('projects', lang('Things I make')),
                        $item('blog', lang('Blog')),
                        $item('now', lang('Now')),
                        $item('guestbook', lang('Guest book')),
                        $item('say_hi', lang('Say hi')),
                    )),
                    $lang_switcher('d-inline-block ms-md-2 mt-3 mt-md-0', 'dark'),
                ), array('id' => 'pl-nav')),
            )),
        ), array('smartActive' => true)),
    ));
};

// The band that sends visitors to the say-hi page: the palette's main
// colour, wall to wall. One shared component, so its words change once.
$cta = function () use ($el, $container, $row, $col, $heading, $para, $link, $icon) {
    return $el('section', 'py-5 text-bg-primary', lang('Call to Action'), array(
        $container(array(
            $row(array(
                $col(array(
                    $heading('h2', lang('Got an idea? Let\'s make something fun.'), 'display-5 fw-bolder mb-2'),
                    $para(lang('Comics, websites, a logo for your band: tell me about it and I will tell you what I would draw first.'), 'lead mb-0 opacity-75'),
                ), '', '8'),
                $col(array(
                    $el('a', 'btn btn-light btn-lg rounded-pill border border-3 border-dark fw-bold px-4', lang('Button'), array(
                        $icon('bi-balloon-heart', 'ms-2'),
                    ), array('href' => '{{page:say_hi}}', 'text' => lang('Say hi'))),
                ), '', '4', '', '12', 'text-lg-end'),
            ), '4', '', 'align-items-center py-lg-3'),
        )),
    ));
};

// The footer: a coffee note, the social links and the copyright, under a
// thick line in the palette's colour.
$footer = function () use ($el, $para, $icon, $span, $attr, $container, $row, $col) {
    $social = function ($name, $label) use ($el, $icon, $attr) {
        return $el('a', 'd-inline-flex align-items-center justify-content-center rounded-circle bg-body border border-2 border-dark link-body-emphasis p-2 lh-1', $label, array(
            $icon($name, 'fs-5'),
        ), array('href' => '#', '_attrs' => array($attr('aria-label', $label))));
    };
    return $el('footer', 'mt-auto py-4 bg-body-tertiary border-top border-4 border-primary', lang('Site Footer'), array(
        $container(array(
            $row(array(
                $col(array(
                    $el('p', 'd-flex align-items-center justify-content-center justify-content-md-start gap-2 mb-0', lang('Coffee Note'), array(
                        $icon('bi-cup-hot-fill', 'fs-4 text-primary'),
                        $span(lang('Made with too much coffee by Jane'), 'fw-semibold', lang('Text')),
                    )),
                ), '4'),
                $col(array(
                    $el('div', 'd-flex justify-content-center gap-2', lang('Social Links'), array(
                        $social('bi-instagram', 'Instagram'),
                        $social('bi-dribbble', 'Dribbble'),
                        $social('bi-github', 'GitHub'),
                        $social('bi-mastodon', 'Mastodon'),
                    )),
                ), '4'),
                $col(array(
                    $para('© {{year}} {{site_name}}', 'small text-body-secondary text-center text-md-end mb-0'),
                ), '4'),
            ), '3', '', 'align-items-center'),
        )),
    ));
};

// A page of the site: the shared header, the page's own content, the shared
// footer.
$page = function ($main) use ($root, $shared, $el) {
    return $root(array(
        $shared('header'),
        $el('main', 'flex-grow-1', lang('Main Content'), $main),
        $shared('footer'),
    ));
};

// The top of the inner pages: a sticker, the title and one sentence on a
// rounded block of colour, with a big icon beside them on wide screens.
$page_header = function ($sticker, $title, $lead, $tone, $icon_name) use ($el, $heading, $para, $span, $icon, $container, $row, $col) {
    return $el('header', 'pt-4 pt-lg-5', lang('Page Header'), array(
        $container(array(
            $el('div', 'p-4 p-lg-5 rounded-5 border border-4 border-dark shadow bg-' . $tone . '-subtle', lang('Header Card'), array(
                $row(array(
                    $col(array(
                        $span($sticker, 'badge rounded-pill text-bg-dark fs-6 mb-3', lang('Sticker')),
                        $heading('h1', $title, 'display-4 fw-bolder mb-2'),
                        $para($lead, 'lead mb-0'),
                    ), '', '9'),
                    $col(array(
                        $icon($icon_name, 'display-1 text-primary'),
                    ), '', '3', '', '12', 'd-none d-lg-flex justify-content-end'),
                ), '4', '', 'align-items-center'),
            )),
        )),
    ));
};

// The heading over a section: an icon in a round sticker, the title and an
// optional line under it.
$section_title = function ($icon_name, $title, $sub = '') use ($el, $heading, $para, $icon) {
    $text = array($heading('h2', $title, 'h1 fw-bolder mb-0'));
    if ($sub !== '') $text[] = $para($sub, 'text-body-secondary mb-0 mt-1');
    return $el('div', 'd-flex align-items-center gap-3 mb-4', lang('Section Header'), array(
        $el('span', 'd-inline-flex align-items-center justify-content-center flex-shrink-0 rounded-circle text-bg-primary border border-3 border-dark p-3 lh-1', lang('Icon Sticker'), array(
            $icon($icon_name, 'fs-3'),
        )),
        $el('div', '', lang('Text'), $text),
    ));
};

// ── Form fields ─────────────────────────────────────────────────────────
// The blocks the Form palette draws. A control's name is the field it fills.
$control_class = 'form-control form-control-lg rounded-4 border-2 border-dark';
$field = function ($id, $label, $control, $class = 'mb-3') use ($el, $attr) {
    return $el('div', $class, lang('Field'), array(
        $el('label', 'form-label fw-semibold', lang('Label'), array(), array('text' => $label, '_attrs' => array($attr('for', $id)))),
        $control,
    ));
};
$input = function ($id, $type, $name, $autocomplete = '', $required = true, $extra_attrs = array(), $cf = array()) use ($el, $attr, $control_class) {
    $attrs = array($attr('type', $type), $attr('name', $name));
    if ($required) $attrs[] = $attr('required', '');
    if ($autocomplete !== '') $attrs[] = $attr('autocomplete', $autocomplete);
    $props = array('id' => $id, '_attrs' => array_merge($attrs, $extra_attrs));
    if ($cf) $props['_cf'] = $cf;
    return $el('input', $control_class, lang('Input'), array(), $props);
};
$textarea = function ($id, $name, $rows, $required = true, $extra_attrs = array()) use ($el, $attr, $control_class) {
    $attrs = array($attr('name', $name), $attr('rows', (string)$rows));
    if ($required) $attrs[] = $attr('required', '');
    return $el('textarea', $control_class, lang('Text Area'), array(), array('id' => $id, '_attrs' => array_merge($attrs, $extra_attrs)));
};
// A file field; what is sent is kept in the template folder named.
$file = function ($id, $name, $folder) use ($el, $attr, $control_class) {
    return $el('input', $control_class, lang('Input'), array(), array('id' => $id, '_attrs' => array(
        $attr('type', 'file'), $attr('name', $name), $attr('accept', 'image/*'),
    ), '_cf' => array('upload_folder_id' => '{{folder:' . $folder . '}}')));
};
$submit = function ($text, $icon_name) use ($el, $attr, $icon) {
    return $el('button', 'btn btn-primary btn-lg rounded-pill border border-3 border-dark fw-bold px-4', lang('Send'), array(
        $icon($icon_name, 'ms-2'),
    ), array('text' => $text, '_attrs' => array($attr('type', 'submit'))));
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

// ── Content ─────────────────────────────────────────────────────────────

// The things Jane makes: the home page shows the first three, the projects
// page all six, each with a window of details.
$projects = array(
    array('icon' => 'bi-palette', 'tone' => 'warning', 'seed' => 'comics',
          'title' => lang('Tiny Comics'), 'tag' => lang('Illustration'),
          'text' => lang('A weekly webcomic about my cat Pixel and his many opinions.'),
          'more' => lang('Every Friday since 2019: four panels, one cat, no apologies. Drawn on an old tablet with a brush I made myself, then printed as a little zine twice a year.'),
          'tools' => array('Procreate', lang('Risograph'), lang('Patience'))),
    array('icon' => 'bi-music-note-beamed', 'tone' => 'info', 'seed' => 'synth',
          'title' => lang('Pocket Synth'), 'tag' => lang('Code'),
          'text' => lang('A browser toy that turns your keyboard into a squeaky synthesizer.'),
          'more' => lang('Press any key and it plays a note; hold shift and it plays a worse one. Built with the Web Audio API in a weekend and still my favourite thing to show at parties.'),
          'tools' => array('JavaScript', 'Web Audio', lang('Headphones'))),
    array('icon' => 'bi-controller', 'tone' => 'success', 'seed' => 'game',
          'title' => lang('Rainy Day'), 'tag' => lang('Game'),
          'text' => lang('A cozy puzzle game about drying laundry before the storm.'),
          'more' => lang('Made in 48 hours for a game jam with two friends. Twelve levels, one very dramatic cloud and a soundtrack recorded on a ukulele.'),
          'tools' => array(lang('Game jam'), lang('Pixel art'), lang('Ukulele'))),
    array('icon' => 'bi-cup-hot', 'tone' => 'danger', 'seed' => 'coffee',
          'title' => lang('Coffee Map'), 'tag' => lang('Side project'),
          'text' => lang('Every café in town, rated by croissant and by how loud the music is.'),
          'more' => lang('Forty-two cafés and counting. Each one gets a score for the coffee, the croissant and the chance of finding a free socket. Updated whenever I am thirsty.'),
          'tools' => array(lang('Maps'), lang('Spreadsheets'), lang('Croissants'))),
    array('icon' => 'bi-bicycle', 'tone' => 'primary', 'seed' => 'rides',
          'title' => lang('Sunday Rides'), 'tag' => lang('Photography'),
          'text' => lang('A photo diary of the slow bike rides I take on Sunday mornings.'),
          'more' => lang('One ride, one photo, one sentence. Mostly fields, sometimes a goat. A small book of the first year is on my shelf and very likely on my mother\'s.'),
          'tools' => array(lang('Film camera'), lang('Bicycle'), lang('Snacks'))),
    array('icon' => 'bi-stars', 'tone' => 'secondary', 'seed' => 'font',
          'title' => lang('Wobbly Sans'), 'tag' => lang('Type'),
          'text' => lang('A free hand-lettered font for birthday cards and lemonade stands.'),
          'more' => lang('Every letter drawn with a felt-tip pen, scanned and traced. It has two hundred glyphs, a very bouncy ampersand and a heart in place of the dot on the i.'),
          'tools' => array(lang('Lettering'), lang('Font tools'), lang('Felt-tip pens'))),
);

// A sticker with a word on it.
$sticker = function ($text, $tone, $icon_name = '') use ($el, $icon, $span) {
    $class = 'badge rounded-pill fs-6 border border-2 border-dark text-bg-' . $tone;
    if ($icon_name === '') return $span($text, $class, lang('Sticker'));
    return $el('span', $class, lang('Sticker'), array($icon($icon_name, 'me-1'), $span($text, '', lang('Text'))));
};

// A project of the home page: a big icon on a block of colour, the words and
// a link to the projects page.
$project_teaser = function ($p) use ($el, $heading, $para, $span, $icon, $col, $sticker) {
    return $col(array(
        $el('div', 'card h-100 rounded-5 border border-3 border-dark shadow-sm bg-' . $p['tone'] . '-subtle', lang('Project Card'), array(
            $el('div', 'card-body d-flex flex-column p-4', lang('Card Body'), array(
                $el('div', 'd-flex flex-wrap align-items-start justify-content-between gap-2 mb-4', lang('Card Top'), array(
                    $el('span', 'd-inline-flex align-items-center justify-content-center rounded-circle bg-body border border-3 border-dark p-3 lh-1', lang('Icon Sticker'), array(
                        $icon($p['icon'], 'display-6 text-primary'),
                    )),
                    $sticker($p['tag'], 'light'),
                )),
                $heading('h3', $p['title'], 'h3 fw-bolder mb-2'),
                $para($p['text'], 'mb-3'),
                $el('a', 'btn btn-link link-body-emphasis fw-bold px-0 mt-auto align-self-start', lang('Link'), array(
                    $span($p['title'], 'visually-hidden', lang('Project Name')),
                    $icon('bi-arrow-right', 'ms-1'),
                ), array('href' => '{{page:projects}}', 'text' => lang('See it'))),
            )),
        )),
    ), '4', '4');
};

// A project of the projects page: a photo, the words and a button that
// opens its window. The windows sit after the grid, outside the cards, so a
// card's lift under the pointer does not carry them along.
$project_card = function ($n, $p) use ($el, $heading, $para, $span, $image, $icon, $col, $attr, $photo, $sticker) {
    return $col(array(
        $el('div', 'card h-100 rounded-5 border border-3 border-dark shadow-sm overflow-hidden bg-' . $p['tone'] . '-subtle', lang('Project Card'), array(
            $el('div', 'ratio ratio-4x3 border-bottom border-3 border-dark', lang('Picture Frame'), array(
                $image($photo($p['seed'], 800, 600), $p['title'], 'object-fit-cover',
                    array('_attrs' => array($attr('loading', 'lazy')))),
            )),
            $el('div', 'card-body d-flex flex-column p-4', lang('Card Body'), array(
                $el('p', 'mb-2', lang('Stickers'), array($sticker($p['tag'], 'light', $p['icon']))),
                $heading('h2', $p['title'], 'h4 fw-bolder mb-2'),
                $para($p['text'], 'mb-3'),
                $el('button', 'btn btn-link link-body-emphasis fw-bold px-0 mt-auto align-self-start', lang('Button'), array(
                    $span($p['title'], 'visually-hidden', lang('Project Name')),
                    $icon('bi-arrow-up-right-circle', 'ms-1'),
                ), array('text' => lang('See it'), '_attrs' => array(
                    $attr('type', 'button'),
                    $attr('data-bs-toggle', 'modal'),
                    $attr('data-bs-target', '#pl-project-' . $n),
                ))),
            )),
        )),
    ), '6', '4');
};
$project_modal = function ($n, $p) use ($el, $heading, $para, $image, $link, $attr, $photo, $sticker) {
    $id = 'pl-project-' . $n;
    $tools = array();
    foreach ($p['tools'] as $t) $tools[] = $sticker($t, $p['tone']);
    return $el('div', 'modal fade', lang('Modal'), array(
        $el('div', 'modal-dialog modal-dialog-centered modal-lg', lang('Modal Dialog'), array(
            $el('div', 'modal-content rounded-5 border border-4 border-dark', lang('Modal Content'), array(
                $el('div', 'modal-header border-bottom border-3 border-dark', lang('Modal Header'), array(
                    $heading('h2', $p['title'], 'modal-title h4 fw-bolder', array('id' => $id . '-title')),
                    $el('button', 'btn-close', lang('Close'), array(), array('_attrs' => array(
                        $attr('type', 'button'),
                        $attr('data-bs-dismiss', 'modal'),
                        $attr('aria-label', lang('Close')),
                    ))),
                )),
                $el('div', 'modal-body p-4', lang('Modal Body'), array(
                    $el('div', 'ratio ratio-16x9 rounded-4 overflow-hidden border border-3 border-dark mb-4', lang('Picture Frame'), array(
                        $image($photo($p['seed'], 1200, 675), $p['title'], 'object-fit-cover',
                            array('_attrs' => array($attr('loading', 'lazy')))),
                    )),
                    $para($p['more'], 'lead mb-4'),
                    $heading('h3', lang('Made with'), 'h6 text-uppercase fw-bold mb-2'),
                    $el('p', 'd-flex flex-wrap gap-2 mb-0', lang('Stickers'), $tools),
                )),
                $el('div', 'modal-footer border-top-0 p-4 pt-0', lang('Modal Footer'), array(
                    $el('button', 'btn btn-outline-dark rounded-pill border-2 fw-bold', lang('Button'), array(), array('text' => lang('Close'), '_attrs' => array(
                        $attr('type', 'button'),
                        $attr('data-bs-dismiss', 'modal'),
                    ))),
                    $link(lang('Ask me about it'), '{{page:say_hi}}', 'btn btn-primary rounded-pill border border-2 border-dark fw-bold'),
                )),
            )),
        )),
    ), array('id' => $id, '_attrs' => array(
        $attr('tabindex', '-1'),
        $attr('aria-labelledby', $id . '-title'),
        $attr('aria-hidden', 'true'),
    )));
};

// A blog post card of the lists (the blog page and the home page): a sticker
// with the date, the title, two sentences. The whole card is the link.
$post_card = function ($title_tag, $md = '6') use ($el, $para, $span, $link, $icon, $col, $optional_image, $bind, $photo) {
    return $col(array(
        $el('article', 'card h-100 rounded-5 border border-3 border-dark shadow-sm overflow-hidden', lang('Post Card'), array(
            $optional_image('cover_image', 'title', 'card-img-top object-fit-cover', 'ratio ratio-16x9 border-bottom border-3 border-dark',
                $photo('post', 800, 450), lang('Cover Image')),
            $el('div', 'card-body d-flex flex-column p-4', lang('Card Body'), array(
                $el('p', 'mb-3', lang('Stickers'), array(
                    $span(lang('Date'), 'badge rounded-pill text-bg-warning border border-2 border-dark', lang('Date'), $bind('submitted_date_and_time')),
                )),
                $el($title_tag, 'h4 fw-bolder card-title', lang('Post Title'), array(
                    $link(lang('Why I draw my cat every single day'), '#',
                        'stretched-link link-body-emphasis text-decoration-none', array(),
                        array('_bindings' => array('text' => 'title', 'href' => 'form_item_view'))),
                )),
                $para(lang('A short summary of the post. Two friendly sentences are enough to make someone curious.'),
                    'card-text text-body-secondary', $bind('summary')),
                $el('span', 'mt-auto fw-bold text-primary', lang('Read More'), array(
                    $icon('bi-arrow-right', 'ms-1'),
                ), array('text' => lang('Read it'))),
            )),
        )),
    ), $md, '4');
};

// A "currently" card: an icon, what kind of thing, what it is.
$currently = function ($icon_name, $title, $value, $tone) use ($el, $heading, $para, $icon, $col) {
    return $col(array(
        $el('div', 'h-100 p-3 p-lg-4 rounded-5 border border-3 border-dark shadow-sm bg-' . $tone . '-subtle', lang('Currently Card'), array(
            $icon($icon_name, 'display-6 text-primary'),
            $heading('h3', $title, 'h6 text-uppercase fw-bold mt-3 mb-1'),
            $para($value, 'fw-semibold mb-0'),
        )),
    ), '', '', '', 'col');
};

// A photo of the home page's carousel.
$slide = function ($seed, $alt, $caption, $active = false) use ($el, $image, $span, $photo) {
    return $el('div', 'carousel-item' . ($active ? ' active' : ''), lang('Slide'), array(
        $el('div', 'ratio ratio-21x9', lang('Picture Frame'), array(
            $image($photo($seed, 1400, 600), $alt, 'd-block w-100 object-fit-cover'),
        )),
        $el('div', 'carousel-caption pb-4', lang('Caption'), array(
            $span($caption, 'badge rounded-pill text-bg-light border border-2 border-dark fs-6 text-wrap', lang('Sticker')),
        )),
    ));
};

// ── Pages ───────────────────────────────────────────────────────────────

$pages = array();

// Home: the hello, what Jane is up to, three things she made, a few photos,
// the newest posts and the band to the say-hi page.
$teasers = array();
foreach (array_slice($projects, 0, 3) as $p) $teasers[] = $project_teaser($p);

$hero_badge = function ($pos, $tone, $icon_name) use ($el, $icon) {
    return $el('span', 'position-absolute d-inline-flex align-items-center justify-content-center rounded-circle border border-3 border-dark shadow p-3 lh-1 ' . $pos . ' text-bg-' . $tone, lang('Icon Sticker'), array(
        $icon($icon_name, 'fs-3'),
    ));
};

$pages[] = array(
    'key'              => 'home',
    'name'             => lang('home'),
    'folder'           => 'public',
    'title'            => lang('Home Page'),
    'meta_description' => lang('Hi, I\'m Jane: I draw, I code and I make small fun things for the web.'),
    'tree'             => $page(array(
        // The hello: the words and the stickers on one side, a round photo
        // with icon stickers around it on the other.
        $el('section', 'py-5 overflow-hidden', lang('Hero'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $heading('h1', lang('Hi, I\'m Jane.'), 'display-1 fw-bolder mb-3'),
                        $el('div', 'd-flex flex-wrap gap-2 mb-4', lang('Stickers'), array(
                            $sticker(lang('I draw'), 'warning', 'bi-palette'),
                            $sticker(lang('I code'), 'info', 'bi-lightning-charge'),
                            $sticker(lang('I drink too much coffee'), 'danger', 'bi-cup-hot'),
                        )),
                        $para(lang('I\'m a designer, developer and illustrator who makes friendly things for the web, and the occasional comic about my cat.'), 'lead mb-4'),
                        $el('div', 'd-flex flex-wrap gap-2', lang('Buttons'), array(
                            $el('a', 'btn btn-primary btn-lg rounded-pill border border-3 border-dark fw-bold px-4', lang('Button'), array(
                                $icon('bi-rocket-takeoff', 'ms-2'),
                            ), array('href' => '{{page:projects}}', 'text' => lang('See what I make'))),
                            $link(lang('Say hi'), '{{page:say_hi}}', 'btn btn-outline-dark btn-lg rounded-pill border-3 fw-bold px-4'),
                        )),
                    ), '7'),
                    $col(array(
                        $el('div', 'position-relative w-75 mx-auto', lang('Photo Frame'), array(
                            $el('div', 'ratio ratio-1x1 rounded-circle overflow-hidden border border-4 border-dark shadow-lg', lang('Picture Frame'), array(
                                $image($photo('jane', 800, 800), lang('Jane Cooper at her desk, laughing'), 'object-fit-cover'),
                            )),
                            $hero_badge('top-0 end-0', 'warning', 'bi-stars'),
                            $hero_badge('top-50 start-0 translate-middle', 'info', 'bi-music-note-beamed'),
                            $hero_badge('bottom-0 end-0', 'success', 'bi-emoji-sunglasses'),
                        )),
                    ), '5'),
                ), '4', '', 'g-lg-5 align-items-center'),
            ), 'py-lg-4'),
        )),

        // What Jane is up to right now.
        $el('section', 'py-5 bg-body-tertiary border-top border-bottom border-3 border-dark', lang('Currently'), array(
            $container(array(
                $section_title('bi-lightning-charge', lang('Currently'), lang('A quick look at what is on my desk this week.')),
                $row(array(
                    $currently('bi-music-note-beamed', lang('Listening'), lang('Lo-fi beats and old ABBA records'), 'warning'),
                    $currently('bi-book', lang('Reading'), lang('A very long book about typefaces'), 'info'),
                    $currently('bi-rocket-takeoff', lang('Building'), lang('A tiny synth for the browser'), 'success'),
                    $currently('bi-cup-hot', lang('Drinking'), lang('Oat flat white, my third today'), 'danger'),
                ), '3', '', 'row-cols-2 row-cols-lg-4 g-lg-4'),
            )),
        )),

        // Three things Jane made.
        $el('section', 'py-5', lang('Things I make'), array(
            $container(array(
                $section_title('bi-palette', lang('Things I make'), lang('Comics, code and other small joys.')),
                $row($teasers, '4'),
                $el('div', 'text-center mt-5', lang('Buttons'), array(
                    $link(lang('See everything I make'), '{{page:projects}}', 'btn btn-outline-dark btn-lg rounded-pill border-3 fw-bold px-4'),
                )),
            )),
        )),

        // A few photos, sliding by themselves.
        $el('section', 'pb-5', lang('Photos'), array(
            $container(array(
                $section_title('bi-camera', lang('A few photos'), lang('Things I saw on the way to somewhere else.')),
                $el('div', 'carousel slide rounded-5 overflow-hidden border border-4 border-dark shadow', lang('Carousel'), array(
                    $el('div', 'carousel-indicators', lang('Indicators'), array(
                        $el('button', 'active', lang('Indicator'), array(), array('_attrs' => array(
                            $attr('type', 'button'), $attr('data-bs-target', '#pl-photos'), $attr('data-bs-slide-to', '0'),
                            $attr('aria-current', 'true'), $attr('aria-label', lang(array('string' => 'Slide {var:1}', 'vars' => array(1)))),
                        ))),
                        $el('button', '', lang('Indicator'), array(), array('_attrs' => array(
                            $attr('type', 'button'), $attr('data-bs-target', '#pl-photos'), $attr('data-bs-slide-to', '1'),
                            $attr('aria-label', lang(array('string' => 'Slide {var:1}', 'vars' => array(2)))),
                        ))),
                        $el('button', '', lang('Indicator'), array(), array('_attrs' => array(
                            $attr('type', 'button'), $attr('data-bs-target', '#pl-photos'), $attr('data-bs-slide-to', '2'),
                            $attr('aria-label', lang(array('string' => 'Slide {var:1}', 'vars' => array(3)))),
                        ))),
                    )),
                    $el('div', 'carousel-inner', lang('Slides'), array(
                        $slide('desk', lang('A messy desk with pens, sketchbooks and a cup of coffee'), lang('My desk on a Monday'), true),
                        $slide('ride', lang('A country road seen from a bicycle in the morning light'), lang('The Sunday ride')),
                        $slide('cat', lang('A sleepy cat curled up on a sketchbook'), lang('Pixel, my harshest critic')),
                    )),
                    $el('button', 'carousel-control-prev', lang('Previous'), array(
                        $el('span', 'carousel-control-prev-icon', lang('Icon'), array(), array('_attrs' => array($attr('aria-hidden', 'true')))),
                        $span(lang('Previous'), 'visually-hidden', lang('Text')),
                    ), array('_attrs' => array($attr('type', 'button'), $attr('data-bs-target', '#pl-photos'), $attr('data-bs-slide', 'prev')))),
                    $el('button', 'carousel-control-next', lang('Next'), array(
                        $el('span', 'carousel-control-next-icon', lang('Icon'), array(), array('_attrs' => array($attr('aria-hidden', 'true')))),
                        $span(lang('Next'), 'visually-hidden', lang('Text')),
                    ), array('_attrs' => array($attr('type', 'button'), $attr('data-bs-target', '#pl-photos'), $attr('data-bs-slide', 'next')))),
                ), array('id' => 'pl-photos', '_attrs' => array($attr('data-bs-ride', 'carousel')))),
            )),
        )),

        // The newest posts of the blog.
        $el('section', 'py-5 bg-body-tertiary border-top border-3 border-dark', lang('Latest Posts'), array(
            $container(array(
                $el('div', 'd-flex flex-wrap align-items-center justify-content-between gap-3 mb-4', lang('Section Header'), array(
                    $el('div', 'd-flex align-items-center gap-3', lang('Title'), array(
                        $el('span', 'd-inline-flex align-items-center justify-content-center flex-shrink-0 rounded-circle text-bg-primary border border-3 border-dark p-3 lh-1', lang('Icon Sticker'), array(
                            $icon('bi-pencil', 'fs-3'),
                        )),
                        $heading('h2', lang('From the blog'), 'h1 fw-bolder mb-0'),
                    )),
                    $link(lang('All posts'), '{{page:blog}}', 'btn btn-outline-dark rounded-pill border-2 fw-bold'),
                )),
                $widget('home_posts'),
            )),
        )),

        $shared('cta'),
    )),
);

// About: the longer hello, fun facts, very serious skills and the way here.
$fact = function ($n, $question, $answer, $open = false) use ($el, $para, $attr) {
    $id = 'pl-fact-' . $n;
    return $el('div', 'accordion-item', lang('Question'), array(
        $el('h3', 'accordion-header', lang('Question Header'), array(
            $el('button', 'accordion-button fw-bold' . ($open ? '' : ' collapsed'), lang('Button'), array(), array(
                'text'   => $question,
                '_attrs' => array(
                    $attr('type', 'button'),
                    $attr('data-bs-toggle', 'collapse'),
                    $attr('data-bs-target', '#' . $id),
                    $attr('aria-expanded', $open ? 'true' : 'false'),
                    $attr('aria-controls', $id),
                ),
            )),
        )),
        $el('div', 'accordion-collapse collapse' . ($open ? ' show' : ''), lang('Answer'), array(
            $el('div', 'accordion-body', lang('Answer Body'), array($para($answer, 'mb-0'))),
        ), array('id' => $id, '_attrs' => array($attr('data-bs-parent', '#pl-facts')))),
    ));
};
// A skill bar. The bar's width is a grid column of the bar (col-N is N/12
// wide in any flex box, and .progress is one), so no inline width is needed.
$skill = function ($label, $cols, $tone) use ($el, $span, $attr) {
    $percent = (int)round($cols * 100 / 12);
    $value = lang(array('string' => '{var:1}%', 'vars' => array($percent)));
    return $el('li', 'mb-3', lang('Skill'), array(
        $el('div', 'd-flex justify-content-between gap-3 fw-semibold mb-1', lang('Skill Label'), array(
            $span($label, '', lang('Name')),
            $span($value, 'text-body-secondary', lang('Value')),
        )),
        $el('div', 'progress rounded-pill border border-2 border-dark', lang('Progress'), array(
            $el('div', 'progress-bar rounded-pill col-' . $cols . ' bg-' . $tone, lang('Progress Bar')),
        ), array('_attrs' => array(
            $attr('role', 'progressbar'),
            $attr('aria-label', $label),
            $attr('aria-valuenow', (string)$percent),
            $attr('aria-valuemin', '0'),
            $attr('aria-valuemax', '100'),
        ))),
    ));
};
$milestone = function ($year, $title, $text) use ($el, $heading, $para, $span) {
    return $el('li', 'position-relative ps-4 pb-4', lang('Milestone'), array(
        $span('', 'position-absolute top-0 start-0 translate-middle rounded-circle bg-primary border border-3 border-dark p-2 mt-2', lang('Dot')),
        $span($year, 'badge rounded-pill text-bg-warning border border-2 border-dark fs-6 mb-2', lang('Year')),
        $heading('h3', $title, 'h5 fw-bolder mb-1'),
        $para($text, 'text-body-secondary mb-0'),
    ));
};

$pages[] = array(
    'key'              => 'about',
    'name'             => lang('about'),
    'folder'           => 'public',
    'title'            => lang('About me'),
    'meta_description' => lang('Who Jane is, what she is good at and a few things she is very bad at.'),
    'tree'             => $page(array(
        $page_header(lang('Nice to meet you'), lang('About me'), lang('A little about who I am, what I do and why there is always a cat in my drawings.'), 'info', 'bi-emoji-sunglasses'),

        // The longer hello, beside a photo.
        $el('section', 'py-5', lang('Story'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $el('div', 'ratio ratio-1x1 rounded-5 overflow-hidden border border-4 border-dark shadow', lang('Picture Frame'), array(
                            $image($photo('studio', 800, 800), lang('Jane drawing at a sunny window'), 'object-fit-cover',
                                array('_attrs' => array($attr('loading', 'lazy')))),
                        )),
                    ), '5', '4'),
                    $col(array(
                        $heading('h2', lang('Hello again!'), 'h1 fw-bolder mb-3'),
                        $para(lang('I grew up drawing on the back of my parents\' receipts and never really stopped. Today I design and build websites for small studios, shops and kind people, and I draw comics in between.'), 'lead mb-3'),
                        $para(lang('I like projects with a sense of humour, a clear goal and a deadline that leaves room for one more idea. When I am not at my desk I am on my bike, in a café or arguing with my cat about who owns the chair.'), 'mb-4'),
                        $el('div', 'd-flex flex-wrap gap-2', lang('Stickers'), array(
                            $sticker(lang('Based in Istanbul'), 'light', 'bi-geo-alt'),
                            $sticker(lang('Speaks three languages'), 'light', 'bi-chat-heart'),
                            $sticker(lang('Owned by a cat'), 'light', 'bi-heart'),
                        )),
                    ), '7', '8'),
                ), '4', '', 'g-lg-5 align-items-center'),
            )),
        )),

        // Fun facts and the very serious skills, side by side.
        $el('section', 'py-5 bg-body-tertiary border-top border-bottom border-3 border-dark', lang('Facts and Skills'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $section_title('bi-balloon-heart', lang('Fun facts')),
                        $el('div', 'accordion rounded-5 overflow-hidden border border-3 border-dark shadow-sm', lang('Accordion'), array(
                            $fact(1, lang('I have drawn my cat more than 400 times.'), lang('Pixel has never once said thank you. He is, however, very photogenic.'), true),
                            $fact(2, lang('My first website had a visitor counter.'), lang('And a dancing GIF, and a guest book. Some traditions are worth keeping, so go and sign mine.')),
                            $fact(3, lang('I can solve a Rubik\'s cube in two minutes.'), lang('Three minutes if anybody is watching. Five if they are giving advice.')),
                            $fact(4, lang('I ride my bike to every meeting I can.'), lang('Rain or shine. Mostly rain. I keep a spare pair of socks in my bag.')),
                            $fact(5, lang('I name my side projects after snacks.'), lang('The current one is called Pretzel. Please do not ask about Nacho.')),
                        ), array('id' => 'pl-facts')),
                    ), '', '6'),
                    $col(array(
                        $section_title('bi-lightning-charge', lang('Very serious skills')),
                        $el('div', 'p-4 rounded-5 border border-3 border-dark shadow-sm bg-body', lang('Skills Card'), array(
                            $el('ul', 'list-unstyled mb-0', lang('Skills'), array(
                                $skill(lang('Drawing cats'), 12, 'primary'),
                                $skill(lang('Making coffee'), 11, 'warning'),
                                $skill(lang('CSS centering'), 9, 'info'),
                                $skill(lang('Replying to e-mails on time'), 7, 'success'),
                                $skill(lang('Naming things'), 5, 'danger'),
                                $skill(lang('Remembering where my keys are'), 3, 'secondary'),
                            )),
                        )),
                    ), '', '6'),
                ), '4', '', 'g-lg-5'),
            )),
        )),

        // The way here, year by year.
        $el('section', 'py-5', lang('Timeline'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $section_title('bi-rocket-takeoff', lang('How I got here'), lang('Four moments, give or take a few hundred coffees.')),
                    ), '', '5'),
                    $col(array(
                        $el('ul', 'list-unstyled border-start border-3 border-dark ms-3 mb-0', lang('Timeline'), array(
                            $milestone('2014', lang('Drew my first comic'), lang('Photocopied thirty of them and handed them out at school.')),
                            $milestone('2017', lang('Wrote my first line of CSS'), lang('Then spent three days trying to center a box.')),
                            $milestone('2020', lang('Went freelance'), lang('Design, code and drawings for small studios and kind people.')),
                            $milestone(lang('Today'), lang('Making fun things'), lang('For clients, for friends and for the joy of it.')),
                        )),
                    ), '', '7'),
                ), '4', '', 'g-lg-5'),
            )),
        )),

        $shared('cta'),
    )),
);

// Things I make: six projects, each with a window of details.
$cards = array();
$modals = array();
foreach ($projects as $i => $p) {
    $cards[] = $project_card($i + 1, $p);
    $modals[] = $project_modal($i + 1, $p);
}
$pages[] = array(
    'key'              => 'projects',
    'name'             => lang('things-i-make'),
    'folder'           => 'public',
    'title'            => lang('Things I make'),
    'meta_description' => lang('Comics, browser toys, a game, a font and other small fun things Jane has made.'),
    'tree'             => $page(array(
        $page_header(lang('Fresh from the desk'), lang('Things I make'), lang('Some for clients, some for friends, most for no reason at all. Open one to see the details.'), 'warning', 'bi-palette'),
        $el('section', 'py-5', lang('Projects'), array(
            $container(array(
                $row($cards, '4'),
                $el('div', '', lang('Project Windows'), $modals),
            )),
        )),
        $shared('cta'),
    )),
);

// Blog
$pages[] = array(
    'key'              => 'blog',
    'name'             => lang('blog'),
    'folder'           => 'public',
    'title'            => lang('Blog'),
    'meta_description' => lang('Notes on drawing, code, coffee and the occasional cat.'),
    'tree'             => $page(array(
        $page_header(lang('Freshly written'), lang('Blog'), lang('Notes on drawing, code, coffee and the occasional cat.'), 'success', 'bi-pencil'),
        $el('section', 'py-5', lang('Posts'), array(
            $container(array($widget('blog_list'))),
        )),
    )),
);

// A blog post: the post the list linked to (?r=), and its comments. Any
// visitor may comment, signed in or not; the CAPTCHA keeps the robots out.
$pages[] = array(
    'key'              => 'blog_post',
    'name'             => lang('blog-post'),
    'folder'           => 'public',
    'title'            => lang('Blog Post'),
    'meta_description' => lang('A post from Jane\'s blog.'),
    'sitemap'          => false,
    'comments'         => array('label' => lang('Comment'), 'allow_new' => true, 'auto_publish' => true, 'show_date' => true, 'login' => false,
                                'email_page' => '{{tab:email_comment}}', 'email_subject' => lang('New comment on the blog'),
                                'notify_email' => '{{site_email}}'),
    'tree'             => $page(array(
        $widget('blog_post'),
        $el('section', 'pb-5', lang('Comments'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $el('div', 'p-4 p-lg-5 rounded-5 border border-3 border-dark bg-body-tertiary', lang('Comments Card'), array(
                            $el('div', 'd-flex align-items-center gap-2 mb-3', lang('Section Header'), array(
                                $icon('bi-chat-heart', 'fs-3 text-primary'),
                                $heading('h2', lang('Say something nice'), 'h3 fw-bolder mb-0'),
                            )),
                            array('type' => 'region', 'props' => array('regionType' => 'comments_block', 'regionName' => ''), 'children' => array()),
                        )),
                    ), '', '8'),
                ), '', 'center'),
            )),
        )),
    )),
);

// The guest book: the page owns the form (a name, a mood, a message); the
// wall beside it lists the six newest hellos to every visitor.
$pages[] = array(
    'key'              => 'guestbook',
    'name'             => lang('guest-book'),
    'folder'           => 'public',
    'title'            => lang('Guest book'),
    'meta_description' => lang('Leave a hello on Jane\'s guest book and see who else dropped by.'),
    'form'             => array(
        'form_name'            => lang('Guest book'),
        'confirmation_message' => lang('Thank you for the hello! It is on the wall now.'),
    ),
    'tree'             => $page(array(
        $page_header(lang('Old-school and proud'), lang('Guest book'), lang('Remember guest books? I loved them. Leave a hello, pick a mood and you are on the wall.'), 'danger', 'bi-emoji-smile'),
        $el('section', 'py-5', lang('Content'), array(
            $container(array(
                $row(array(
                    // The form comes first: it shows its own thank-you
                    // message before the wall is drawn.
                    $col(array(
                        $el('div', 'p-4 rounded-5 border border-4 border-dark shadow bg-warning-subtle', lang('Form Card'), array(
                            $heading('h2', lang('Leave a hello'), 'h3 fw-bolder mb-3'),
                            $widget('guestbook_form'),
                        )),
                    ), '', '5'),
                    $col(array(
                        $section_title('bi-stars', lang('Wall of hellos'), lang('The six newest visitors who stopped by.')),
                        $widget('guestbook_wall'),
                    ), '', '7'),
                ), '4', '', 'g-lg-5'),
            )),
        )),
    )),
);

// Say hi: a message to Jane. She is told by e-mail, the sender gets a reply.
$pages[] = array(
    'key'              => 'say_hi',
    'name'             => lang('say-hi'),
    'folder'           => 'public',
    'title'            => lang('Say hi'),
    'meta_description' => lang('Write to Jane about a project, a comic or just to say hello.'),
    'form'             => array(
        'form_name'            => lang('Say Hi Form'),
        'confirmation_message' => lang('Yay, your message is on its way! I will write back soon.'),
        'notify_email'         => '{{site_email}}',
        'notify_subject'       => lang('New hello from ^^name^^'),
        'notify_page_id'       => '{{tab:email_new_message}}',
        'confirm_email'        => 1,
        'confirm_subject'      => lang('Thanks for saying hi!'),
        'confirm_page_id'      => '{{tab:email_message_received}}',
    ),
    'tree'             => $page(array(
        $page_header(lang('My inbox is friendly'), lang('Say hi'), lang('A project, a question, a cat photo: write a few lines and I will get back to you.'), 'primary', 'bi-chat-heart'),
        $el('section', 'py-5', lang('Content'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $el('div', 'p-4 p-lg-5 rounded-5 border border-4 border-dark shadow bg-body', lang('Form Card'), array(
                            $widget('say_hi_form'),
                        )),
                    ), '', '7'),
                    $col(array(
                        $el('div', 'p-4 rounded-5 border border-3 border-dark shadow-sm bg-info-subtle mb-4', lang('Contact Details'), array(
                            $heading('h2', lang('Other ways to reach me'), 'h4 fw-bolder mb-3'),
                            $el('ul', 'list-group rounded-4 border border-2 border-dark', lang('List'), array(
                                $el('li', 'list-group-item d-flex align-items-center gap-3 py-3', lang('List Item'), array(
                                    $icon('bi-envelope-heart', 'fs-4 text-primary'),
                                    $link('jane@example.com', 'mailto:jane@example.com', 'link-body-emphasis fw-semibold text-break'),
                                )),
                                $el('li', 'list-group-item d-flex align-items-center gap-3 py-3', lang('List Item'), array(
                                    $icon('bi-instagram', 'fs-4 text-primary'),
                                    $link('@janecooper.draws', '#', 'link-body-emphasis fw-semibold'),
                                )),
                                $el('li', 'list-group-item d-flex align-items-center gap-3 py-3', lang('List Item'), array(
                                    $icon('bi-geo-alt', 'fs-4 text-primary'),
                                    $span(lang('Istanbul, usually in a café'), 'fw-semibold', lang('Text')),
                                )),
                            )),
                        )),
                        $el('div', 'p-4 rounded-5 border border-3 border-dark shadow-sm bg-success-subtle', lang('Note'), array(
                            $el('p', 'd-flex align-items-center gap-2 fw-bold mb-2', lang('Title'), array(
                                $icon('bi-cup-hot', 'fs-4 text-primary'),
                                $span(lang('How fast do I answer?'), '', lang('Text')),
                            )),
                            $para(lang('Usually within two coffees. On a busy week, within two days. Never longer than it takes Pixel to knock a pen off the desk.'), 'mb-0'),
                        )),
                    ), '', '5'),
                ), '4', '', 'g-lg-5'),
            )),
        )),
    )),
);

// Now: what Jane is focused on at the moment, a short list with dates.
$now_item = function ($date, $icon_name, $title, $text) use ($el, $heading, $para, $span, $icon) {
    return $el('li', 'list-group-item d-flex gap-3 p-4', lang('List Item'), array(
        $el('span', 'd-inline-flex align-items-center justify-content-center flex-shrink-0 align-self-start rounded-circle bg-primary-subtle border border-2 border-dark p-3 lh-1', lang('Icon Sticker'), array(
            $icon($icon_name, 'fs-4 text-primary'),
        )),
        $el('div', 'flex-grow-1', lang('Text'), array(
            $span($date, 'badge rounded-pill text-bg-dark mb-2', lang('Date')),
            $heading('h2', $title, 'h5 fw-bolder mb-1'),
            $para($text, 'text-body-secondary mb-0'),
        )),
    ));
};
$pages[] = array(
    'key'              => 'now',
    'name'             => lang('now'),
    'folder'           => 'public',
    'title'            => lang('What I\'m up to now'),
    'meta_description' => lang('What Jane is working on, learning and enjoying at the moment.'),
    'tree'             => $page(array(
        $page_header(lang('Updated this month'), lang('What I\'m up to now'), lang('Not a to-do list, more of a postcard: the things that have my attention right now.'), 'success', 'bi-cup-hot'),
        $el('section', 'py-5', lang('Content'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $el('ul', 'list-group rounded-5 border border-3 border-dark shadow-sm', lang('List'), array(
                            $now_item(lang('October 2026'), 'bi-rocket-takeoff', lang('Building a pocket synth'), lang('Three new sounds this week, one of them sounds like a duck. Keeping it.')),
                            $now_item(lang('October 2026'), 'bi-palette', lang('Drawing a comic zine'), lang('Twenty pages about Pixel\'s summer. Printing in November if the cat cooperates.')),
                            $now_item(lang('September 2026'), 'bi-bicycle', lang('Riding to the sea and back'), lang('Eighty kilometres every other Sunday. The sea is still there, I checked.')),
                            $now_item(lang('September 2026'), 'bi-book', lang('Reading about type'), lang('Slowly working through a very heavy book on the history of letters.')),
                            $now_item(lang('August 2026'), 'bi-music-note-beamed', lang('Learning the ukulele'), lang('Four chords so far, which is apparently enough for most songs.')),
                        )),
                    ), '', '8'),
                    $col(array(
                        $el('div', 'p-4 rounded-5 border border-3 border-dark shadow-sm bg-danger-subtle mb-4', lang('Not Now'), array(
                            $heading('h2', lang('Not doing right now'), 'h4 fw-bolder mb-3'),
                            $el('ul', 'list-unstyled mb-0', lang('List'), array(
                                $el('li', 'd-flex align-items-center gap-2 mb-2', lang('List Item'), array($icon('bi-x-circle', 'text-danger'), $span(lang('Starting a podcast'), 'text-decoration-line-through', lang('Text')))),
                                $el('li', 'd-flex align-items-center gap-2 mb-2', lang('List Item'), array($icon('bi-x-circle', 'text-danger'), $span(lang('Learning the bagpipes'), 'text-decoration-line-through', lang('Text')))),
                                $el('li', 'd-flex align-items-center gap-2 mb-0', lang('List Item'), array($icon('bi-x-circle', 'text-danger'), $span(lang('Answering e-mails at midnight'), 'text-decoration-line-through', lang('Text')))),
                            )),
                        )),
                        $el('div', 'p-4 rounded-5 border border-3 border-dark shadow-sm bg-body-tertiary', lang('Note'), array(
                            $para(lang('This is a "now" page: a snapshot of what I am focused on, updated whenever my life changes a little.'), 'small mb-0'),
                        )),
                    ), '', '4'),
                ), '4', '', 'g-lg-5'),
            )),
        )),
        $shared('cta'),
    )),
);

// The page the blog posts are written on: the blog's form, behind the
// private folder. The site's sample posts are copied in when the form is
// made (pg_cf_seed_sample_records()), so the blog does not open empty.
$pages[] = array(
    'key'     => 'blog_new',
    'name'    => lang('new-blog-post'),
    'folder'  => 'private',
    'title'   => lang('New Blog Post'),
    'search'  => false,
    'sitemap' => false,
    'noindex' => true,
    'form'    => array(
        'form_name'            => lang('Blog Posts'),
        'confirmation_message' => lang('The post is published.'),
        'confirmation_page_id' => '{{tab:blog}}',
        'sample_records'       => 'blog',
    ),
    'tree'    => $page(array(
        $page_header(lang('Only you can see this'), lang('New Blog Post'), lang('Write the post; it appears on the blog as soon as you send it.'), 'secondary', 'bi-pencil'),
        $el('section', 'py-5', lang('Content'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $el('div', 'p-4 p-lg-5 rounded-5 border border-4 border-dark shadow bg-body', lang('Form Card'), array(
                            $widget('blog_form'),
                        )),
                    ), '', '8'),
                ), '', 'center'),
            )),
        )),
    )),
);

// The site's error page: a page nobody can find, and every other error.
$pages[] = array(
    'key'     => 'error',
    'name'    => lang('page-not-found'),
    'folder'  => 'public',
    'title'   => lang('Page Not Found'),
    'search'  => false,
    'sitemap' => false,
    'noindex' => true,
    'tree'    => $page(array($widget('error_page'))),
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
            $el('div', 'mx-auto bg-body border border-3 border-dark rounded-5 overflow-hidden', lang('E-mail Card'), array(
                $shared('email_header'),
                $el('div', 'p-4', lang('E-mail Body'), $children, $mail_style('padding:24px')),
                $shared('email_footer'),
            ), $mail_style('max-width:600px;margin:0 auto;background:#ffffff;border:3px solid #111827;border-radius:24px;overflow:hidden')),
        ), $mail_style('background:#f3f4f6;padding:24px 12px;font-family:-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,Helvetica,Arial,sans-serif;font-size:15px;line-height:1.55;color:#1f2937')),
    ));
};
$mail_heading = function ($text) use ($heading, $attr) {
    return $heading('h1', $text, 'h4 fw-bolder mb-3', array('_attrs' => array($attr('style', 'font-size:22px;font-weight:800;margin:0 0 16px;color:#111827'))));
};
$mail_para = function ($text, $class = 'mb-3') use ($para, $attr) {
    return $para($text, $class, array('_attrs' => array($attr('style', 'margin:0 0 16px'))));
};
$mail_button = function ($text, $href) use ($el, $link, $mail_style, $attr) {
    return $el('p', 'my-4', lang('Button'), array(
        $link($text, $href, 'btn btn-primary rounded-pill px-4', array($attr('style',
            'display:inline-block;padding:10px 22px;border-radius:999px;font-weight:700;text-decoration:none;'
            . 'background-color:#a21caf;background-color:var(--bs-primary);color:#ffffff;color:var(--pg-on-primary,#ffffff)'))),
    ), $mail_style('margin:24px 0'));
};
$mail_line = function ($label, $values) use ($el, $span, $mail_style, $attr) {
    return $el('p', 'pg-hide-if-empty mb-1', lang('Line'), array(
        $span($label . ': ', 'text-body-secondary', lang('Label'), array('_attrs' => array($attr('style', 'color:#6b7280')))),
        $el('span', 'pg-field-value fw-semibold', lang('Value'), $values),
    ), $mail_style('margin:0 0 4px'));
};
$mail_box = function ($children, $name) use ($el, $mail_style) {
    return $el('div', 'bg-warning-subtle rounded-4 p-3 my-3', $name, $children,
        $mail_style('background:#fef3c7;border-radius:16px;padding:16px;margin:16px 0'));
};

$mail_settings = array('folder' => 'private', 'search' => false, 'sitemap' => false, 'noindex' => true);

// The say-hi form's notification: what Jane receives for a message.
$pages[] = array_merge($mail_settings, array(
    'key'   => 'email_new_message',
    'name'  => lang('email-new-message'),
    'title' => lang('New Message E-mail'),
    'tree'  => $mail_page(array($widget('email_message_admin'))),
));
// The say-hi form's reply to the sender.
$pages[] = array_merge($mail_settings, array(
    'key'   => 'email_message_received',
    'name'  => lang('email-message-received'),
    'title' => lang('Message Received E-mail'),
    'tree'  => $mail_page(array($widget('email_message_sender'))),
));
// A new comment on a blog post: sent to Jane and to the visitors who follow
// the post. The System region carries the comment and a "View or Reply"
// button.
$pages[] = array_merge($mail_settings, array(
    'key'   => 'email_comment',
    'name'  => lang('email-new-comment'),
    'title' => lang('New Comment E-mail'),
    'tree'  => $mail_page(array(
        $mail_heading(lang('Someone left a comment!')),
        $mail_para(lang('There is something new under a blog post you follow. Read it below and answer on the page.')),
        array('type' => 'region', 'props' => array('regionType' => 'system', 'regionName' => ''), 'children' => array()),
    )),
));

// ── Widgets ─────────────────────────────────────────────────────────────

// A mood of the guest book: an emoji button of a btn-check radio group,
// named for screen readers.
$mood = function ($n, $emoji, $label, $checked = false) use ($el, $attr) {
    $id = 'pl-mood-' . $n;
    $attrs = array($attr('type', 'radio'), $attr('name', 'mood'), $attr('value', $emoji), $attr('autocomplete', 'off'), $attr('aria-label', $label));
    if ($checked) $attrs[] = $attr('checked', '');
    return array(
        $el('input', 'btn-check', lang('Radio Button'), array(), array('id' => $id, '_attrs' => $attrs)),
        $el('label', 'btn btn-outline-dark rounded-pill border-2 fs-4 lh-1 px-3 py-2', lang('Label'), array(), array('text' => $emoji, '_attrs' => array($attr('for', $id)))),
    );
};

$widgets = array(

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
            $row(array($loop(array($post_card('h3', '4')))), '4'),
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
            $row(array($loop(array($post_card('h2')))), '4'),
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
                            $icon('bi-emoji-dizzy', 'display-3 text-primary'),
                            $heading('h2', lang('Post not found'), 'h3 fw-bolder mt-3'),
                            $para(lang('This post could not be found. It may have been removed.'), 'text-body-secondary mb-4', $bind('not_found')),
                            $link(lang('Back to the blog'), '{{page:blog}}', 'btn btn-primary rounded-pill border border-2 border-dark fw-bold'),
                        ), $show_when('record_not_found')),
                        $el('article', '', lang('Post'), array(
                            $el('div', 'd-flex flex-wrap align-items-center justify-content-between gap-2 mb-4', lang('Toolbar'), array(
                                $el('a', 'btn btn-sm btn-outline-dark rounded-pill border-2 fw-bold', lang('Link'), array(
                                    $icon('bi-arrow-left', 'me-1'),
                                    $span(lang('All posts'), '', lang('Text')),
                                ), array('href' => '{{page:blog}}')),
                                $link(lang('Edit'), '#', 'btn btn-sm btn-outline-secondary rounded-pill', array(),
                                    array('_bindings' => array('href' => '__edit_url', 'eo_visible_if' => 'can_edit'))),
                            )),
                            $el('p', 'd-flex flex-wrap align-items-center gap-2 mb-3', lang('Post Details'), array(
                                $span(lang('Date'), 'badge rounded-pill text-bg-warning border border-2 border-dark fs-6', lang('Date'), $bind('submitted_date_and_time')),
                                $span(lang('Jane Cooper'), 'badge rounded-pill text-bg-light border border-2 border-dark fs-6', lang('Author'), $bind('submitter')),
                            )),
                            $heading('h1', lang('Why I draw my cat every single day'), 'display-5 fw-bolder mb-4', $bind('title')),
                            $optional_image('cover_image', 'title', 'object-fit-cover', 'ratio ratio-16x9 rounded-5 overflow-hidden border border-4 border-dark shadow mb-4',
                                $photo('post', 1200, 675), lang('Cover Image')),
                            $para(lang('A short summary of the post. Two friendly sentences are enough to make someone curious.'), 'lead fw-semibold mb-4', $bind('summary')),
                            $el('div', 'fs-5 lh-lg', lang('Post Content'), array(), array_merge(
                                array('text' => lang('The body of the post. Write it on the private page for new posts; line breaks are kept.')), $bind('content'))),
                        ), $show_when('record_found')),
                    ), '', '8'),
                ), '', 'center'),
            ), 'py-5'),
        )),
    ),

    // The blog's form, on the private page: what a post is made of.
    'blog_form' => array(
        'page'   => 'blog_new',
        'slug'   => 'form',
        'config' => array('regionType' => 'custom_form', 'form_source' => 'page'),
        'tree'   => $widget_root(array(
            $field('pl-post-title', lang('Title'), $input('pl-post-title', 'text', 'title', 'off', true, array($attr('maxlength', '150')))),
            $field('pl-post-summary', lang('Summary'), $textarea('pl-post-summary', 'summary', 3)),
            $field('pl-post-cover', lang('Cover Image'), $file('pl-post-cover', 'cover_image', 'public')),
            $field('pl-post-content', lang('Content'), $textarea('pl-post-content', 'content', 14)),
            $submit(lang('Publish'), 'bi-rocket-takeoff'),
            $loop(array()),
        )),
    ),

    // The guest book's form: a name, a mood and a short message.
    'guestbook_form' => array(
        'page'   => 'guestbook',
        'slug'   => 'form',
        'config' => array('regionType' => 'custom_form', 'form_source' => 'page'),
        'tree'   => $widget_root(array(
            $field('pl-guest-name', lang('Your Name'), $input('pl-guest-name', 'text', 'name', 'name', true, array($attr('maxlength', '60')))),
            $el('fieldset', 'mb-3', lang('Fieldset'), array_merge(
                array($el('legend', 'form-label fs-6 fw-semibold', lang('Legend'), array(), array('text' => lang('Pick a mood')))),
                array($el('div', 'd-flex flex-wrap gap-2', lang('Mood Picker'), array_merge(
                    $mood(1, '👋', lang('Waving hand'), true),
                    $mood(2, '😄', lang('Big smile')),
                    $mood(3, '🎉', lang('Party')),
                    $mood(4, '☕', lang('Coffee')),
                    $mood(5, '🌈', lang('Rainbow'))
                )))
            )),
            $field('pl-guest-message', lang('Your message'), $textarea('pl-guest-message', 'message', 4, true, array($attr('maxlength', '280')))),
            $captcha(),
            $submit(lang('Leave my hello'), 'bi-balloon-heart'),
            $loop(array()),
        )),
    ),

    // The wall: the six newest hellos of the guest book. No viewer filter,
    // so every visitor sees every hello, as a guest book should.
    'guestbook_wall' => array(
        'page'   => 'guestbook',
        'slug'   => lang('form-list'),
        'config' => array(
            'regionType'          => 'form_list_view',
            'custom_form_page_id' => '{{tab:guestbook}}',
            'items_per_page'      => 6,
            'max_results'         => 6,
            'order_by_field'      => 'submitted_date_and_time',
            'order_by_direction'  => 'DESC',
            'empty_message'       => lang('The wall is still empty. Be the first to say hello!'),
        ),
        'tree'   => $widget_root(array(
            $row(array($loop(array(
                $col(array(
                    $el('div', 'h-100 p-3 rounded-5 border border-3 border-dark shadow-sm bg-body', lang('Hello Card'), array(
                        $el('div', 'd-flex align-items-center gap-3 mb-2', lang('Card Top'), array(
                            $span('👋', 'd-inline-flex align-items-center justify-content-center flex-shrink-0 rounded-circle bg-warning-subtle border border-2 border-dark fs-3 lh-1 p-2', lang('Mood'), $bind('mood')),
                            $el('div', 'min-w-0', lang('Details'), array(
                                $heading('h3', lang('Jane Cooper'), 'h6 fw-bolder text-truncate mb-0', $bind('name')),
                                $para(lang('Date'), 'small text-body-secondary mb-0', array('_bindings' => array('text' => 'submitted_date_and_time'), '_bindFormats' => array('text' => 'relative'))),
                            )),
                        )),
                        $para(lang('Love the comics! Pixel is clearly the star of the show. Say hi to him from me.'), 'mb-0', $bind('message')),
                    )),
                ), '', '', '6'),
            ))), '3'),
        )),
    ),

    // Say hi: the fields are the page's form.
    'say_hi_form' => array(
        'page'   => 'say_hi',
        'slug'   => 'form',
        'config' => array('regionType' => 'custom_form', 'form_source' => 'page'),
        'tree'   => $widget_root(array(
            $row(array(
                $col(array($field('pl-hi-name', lang('Your Name'), $input('pl-hi-name', 'text', 'name', 'name'))), '6'),
                $col(array($field('pl-hi-email', lang('Email'), $input('pl-hi-email', 'email', 'email', 'email', true, array(), array('contact_field' => 'email_address')))), '6'),
            ), '3'),
            $field('pl-hi-found', lang('How did you find me?'), $el('select', 'form-select form-select-lg rounded-4 border-2 border-dark', lang('Select'), array(
                $el('option', '', lang('Option'), array(), array('text' => lang('Pick one…'), '_attrs' => array($attr('value', '')))),
                $el('option', '', lang('Option'), array(), array('text' => lang('A friend told me'), '_attrs' => array($attr('value', lang('A friend told me'))))),
                $el('option', '', lang('Option'), array(), array('text' => lang('A search engine'), '_attrs' => array($attr('value', lang('A search engine'))))),
                $el('option', '', lang('Option'), array(), array('text' => lang('Social media'), '_attrs' => array($attr('value', lang('Social media'))))),
                $el('option', '', lang('Option'), array(), array('text' => lang('One of my comics'), '_attrs' => array($attr('value', lang('One of my comics'))))),
                $el('option', '', lang('Option'), array(), array('text' => lang('I honestly don\'t remember'), '_attrs' => array($attr('value', lang('I honestly don\'t remember'))))),
            ), array('id' => 'pl-hi-found', '_attrs' => array($attr('name', 'found_via'))))),
            $field('pl-hi-message', lang('Your message'), $textarea('pl-hi-message', 'message', 6)),
            $captcha(),
            $submit(lang('Send it over'), 'bi-send'),
            $loop(array()),
        )),
    ),

    'error_page' => array(
        'page'   => 'error',
        'slug'   => lang('error'),
        'config' => array('regionType' => 'error_page'),
        'tree'   => 'starter',
    ),

    // The e-mail pages' widgets: drawn for the message just sent.
    'email_message_admin' => array(
        'page'   => 'email_new_message',
        'slug'   => lang('form-detail'),
        'config' => array(
            'regionType'          => 'form_item_view',
            'custom_form_page_id' => '{{tab:say_hi}}',
            'access_control'      => 'public',
            'not_found_message'   => lang('The message could not be found.'),
        ),
        'tree'   => $widget_root(array(
            $mail_heading(lang('Somebody said hi!')),
            $mail_para(lang('A new message came in from the say-hi page. Reply to the sender\'s e-mail address to answer.')),
            $mail_box(array(
                $mail_line(lang('Name'), array($span(lang('Jane Cooper'), '', lang('Name'), $bind('name')))),
                $mail_line(lang('Email'), array($span('jane@example.com', '', lang('Email'), $bind('email')))),
                $mail_line(lang('Found me through'), array($span(lang('A friend told me'), '', lang('Text'), $bind('found_via')))),
            ), lang('Sender')),
            $el('div', 'lh-lg', lang('Message'), array(), array_merge(
                array('text' => lang('Hi Jane! I run a small bakery and we would love a logo with a croissant that looks a little bit like a cat.')), $bind('message'))),
            $el('p', 'small text-body-secondary mt-4 mb-0', lang('Reference'), array(
                $span(lang('Reference Code') . ': ', '', lang('Label')),
                $span('ABC123', '', lang('Reference Code'), $bind('reference_code')),
            ), $mail_style('font-size:13px;color:#6b7280;margin:24px 0 0')),
        )),
    ),
    'email_message_sender' => array(
        'page'   => 'email_message_received',
        'slug'   => lang('form-detail'),
        'config' => array(
            'regionType'          => 'form_item_view',
            'custom_form_page_id' => '{{tab:say_hi}}',
            'access_control'      => 'public',
            'not_found_message'   => lang('The message could not be found.'),
        ),
        'tree'   => $widget_root(array(
            $mail_heading(lang('Thanks for saying hi!')),
            $el('p', 'mb-3', lang('Greeting'), array(
                $span(lang('Hello') . ' ', '', lang('Text')),
                $span(lang('Jane Cooper'), '', lang('Name'), $bind('name')),
            ), $mail_style('margin:0 0 16px')),
            $mail_para(lang('Your message landed safely in my inbox. I read every one of them, usually with a coffee, and I will write back soon.')),
            $mail_box(array(
                $el('div', 'lh-lg', lang('Message'), array(), array_merge(
                    array('text' => lang('Hi Jane! I run a small bakery and we would love a logo with a croissant that looks a little bit like a cat.')), $bind('message'))),
            ), lang('Your Message')),
            $mail_button(lang('See what I make'), '{{page:projects}}'),
        )),
    ),
);

return array(
    'name'        => lang('Playground'),
    'version'     => '1.0.1',
    'framework'   => 'bootstrap5',
    'order'       => 15,
    'icon'        => 'bi-emoji-smile',
    // The picture on the template's card and beside the designs made from
    // it: a cheerful personal home page (pg_design_thumb_svg()).
    'thumb'       => 'playground',
    'description' => lang('A playful personal site: a big hello, the things you make, a blog with comments, a guest book and a page to say hi.'),

    // The theme the template is made for; the operator can pick another
    // before opening it, and change it later in the editor.
    'look'        => 'playful',
    'palette'     => 'fuchsia',

    // What the installer lists on the template's card, after the points
    // every template shares.
    'highlights'  => array(
        lang('A guest book whose newest hellos appear on a wall for every visitor.'),
        lang('Blog posts that any visitor can comment on, no account needed.'),
        lang('Six project cards that open a window of details, and a carousel of photos.'),
    ),

    // Made (or found again) when the template is opened; see _pg_tpl_folders().
    'folders'     => array(
        'root'    => array('name' => 'pinegrap_playground', 'access' => 'public'),
        'public'  => array('name' => 'public', 'parent' => 'root', 'access' => 'public'),
        'private' => array('name' => 'private', 'parent' => 'root', 'access' => 'private'),
    ),

    'pages'       => $pages,

    'widgets'     => $widgets,

    'shared'      => array(
        'header'       => array('name' => lang('Playground Header'), 'tree' => $header()),
        'footer'       => array('name' => lang('Playground Footer'), 'tree' => $footer()),
        'cta'          => array('name' => lang('Say Hi Call to Action'), 'tree' => $cta()),
        'email_header' => array('name' => lang('Playground E-mail Header'), 'tree' =>
            $el('div', 'px-4 py-3 border-bottom border-3 border-dark bg-primary-subtle', lang('E-mail Header'), array(
                $link('{{site_name}}', '{{page:home}}', 'fs-5 fw-bolder text-decoration-none link-body-emphasis',
                    array($attr('style', 'font-size:18px;font-weight:800;color:#111827;text-decoration:none'))),
            ), $mail_style('padding:16px 24px;border-bottom:3px solid #111827;background:#fae8ff'))),
        'email_footer' => array('name' => lang('Playground E-mail Footer'), 'tree' =>
            $el('div', 'px-4 py-3 border-top small text-body-secondary', lang('E-mail Footer'), array(
                $para('© {{year}} {{site_name}}', 'mb-1', array('_attrs' => array($attr('style', 'margin:0 0 4px')))),
                $link(lang('Visit the website'), '{{page:home}}', 'link-secondary',
                    array($attr('style', 'color:#6b7280'))),
            ), $mail_style('padding:16px 24px;border-top:1px solid #e5e7eb;font-size:13px;color:#6b7280'))),
    ),
);
