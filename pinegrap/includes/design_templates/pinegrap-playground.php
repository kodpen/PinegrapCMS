<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Playground": a cheerful personal site for one person. A
 * big hello with stickers and a round photo, what they are up to right now,
 * the things they make (each opening a detail window), a photo carousel, a
 * blog whose posts take comments from any visitor, a guest book with a wall
 * of the latest hellos, a "now" page, an about page with fun facts, very
 * serious skills and a timeline, a page to say hi whose messages become
 * conversations, the sign-in and account pages with an inbox and a staff
 * directory, a staff area, and a search page that looks through the whole
 * site. Built on Bootstrap 5. Read by
 * pg_design_templates() (includes/fn/designer.php).
 *
 * The pages go into three folders under pinegrap_playground: public (open
 * to everybody), registration (any signed-in visitor: the account pages,
 * the inbox, the staff directory) and private (the site staff and the users
 * given access to the folder). The private folder holds the staff area, the
 * pages the blog posts and the directory entries are written on, and the
 * e-mails the site sends: the say-hi form's notification and its reply to
 * the sender, the e-mails about a new comment or reply, the welcome e-mail
 * after a sign-up and the password reset e-mail.
 *
 * The blog's form starts with the template's own sample posts
 * ('sample_records'), so the blog and the home page do not open empty. The
 * login region sits at the end of the shared header. The guest book's form is
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

$shop      = defined('ECOMMERCE') && ECOMMERCE;
$panel_url = (defined('OUTPUT_PATH') ? OUTPUT_PATH : '/') . (defined('OUTPUT_SOFTWARE_DIRECTORY') ? OUTPUT_SOFTWARE_DIRECTORY : '') . '/welcome.php';

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
        'align' => 'md-end', 'display' => 'name', 'icon' => 'translate',
        'cssClass' => $class,
    ), 'children' => array());
};
// Summarize the post with AI: with the visitor's own assistant (each link
// opens it with a question about the post's address) or with the browser's
// built-in model, whose button the browser shows only where it can
// summarize and whose points come into the box below. Shown for a post
// anybody may open.
$ai_summary = function () use ($el, $link, $icon, $span, $attr, $bind, $show_when) {
    $assistant = function ($text, $token) use ($link, $attr) {
        return $link($text, '#', 'btn btn-sm btn-outline-dark rounded-pill border-2 fw-bold', array($attr('rel', 'noopener nofollow')),
            array('target' => '_blank', '_bindings' => array('href' => $token)));
    };
    return $el('aside', 'bg-body border border-2 border-dark rounded-4 shadow-sm p-3 mb-4', lang('AI Summary'), array(
        $el('div', 'd-flex flex-wrap align-items-center gap-2', lang('AI Buttons'), array(
            $icon('bi-stars', 'fs-5 text-primary'),
            $span(lang('Summarize this post with AI'), 'fw-bold me-1', lang('Label')),
            $assistant('ChatGPT', '__ai_chatgpt_url'),
            $assistant('Claude', '__ai_claude_url'),
            $assistant('Perplexity', '__ai_perplexity_url'),
            $assistant('Google AI', '__ai_google_url'),
            $el('button', 'btn btn-sm btn-warning rounded-pill border border-2 border-dark fw-bold', lang('Summarize Here Button'), array(), array(
                'text' => lang('Summarize here'), '_bindings' => array('action' => 'ai_summarize'))),
        )),
        $el('div', 'mt-3', lang('Summary Box'), array(), array_merge(
            array('text' => lang('The main points of the post, written by the visitor\'s browser.')), $bind('__ai_summary'))),
    ), $show_when('has_ai_links'));
};

// The sample photos (picsum.photos gives the same picture for the same seed).
$photo = function ($seed, $w, $h) {
    return 'https://picsum.photos/seed/pl-' . $seed . '/' . $w . '/' . $h;
};
$avatar = 'https://placehold.co/96x96/a21caf/ffffff?text=JC';

// ── The parts every page repeats ────────────────────────────────────────

// The header: the round avatar, the name and an "open to projects" sticker
// on the left, the menu, the language switcher and the login region on the
// right, folded behind a toggler on a phone.
// The link to the current page is marked active on the server (smart active
// state), which is what lets one header serve every page.
$header = function () use ($el, $link, $image, $span, $icon, $attr, $avatar, $lang_switcher, $widget) {
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
                        // The search page: an icon on a wide screen, the
                        // icon and the word in the folded menu.
                        $el('li', 'nav-item', lang('Nav Item'), array(
                            $el('a', 'nav-link px-md-1 px-lg-2 d-flex align-items-center gap-2', lang('Search Link'), array(
                                $icon('bi-search', ''),
                                $span(lang('Search'), 'd-md-none', lang('Text')),
                            ), array('href' => '{{page:search}}', '_attrs' => array($attr('aria-label', lang('Search the site'))))),
                        )),
                    )),
                    $lang_switcher('d-inline-block ms-md-2 mt-3 mt-md-0', 'dark'),
                    $el('div', 'ms-md-3 mt-3 mt-md-0', lang('Account Area'), array($widget('login_region'))),
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

// The staff pages' own menu, under the header: one shared component.
$staff_bar = function () use ($el, $link, $span) {
    $item = function ($key, $text) use ($link) {
        return $link($text, '{{page:' . $key . '}}', 'nav-link px-0 fw-semibold');
    };
    return $el('div', 'bg-warning-subtle border-bottom border-3 border-dark', lang('Staff Bar'), array(
        $el('div', 'container d-flex flex-wrap align-items-center gap-4 py-2', lang('Staff Bar Container'), array(
            $span(lang('Staff'), 'badge rounded-pill text-bg-dark border border-2 border-dark', lang('Sticker')),
            $el('nav', 'nav nav-underline gap-4', lang('Staff Links'), array(
                $item('staff', lang('Staff Area')),
                $item('blog_new', lang('New Blog Post')),
                $item('staff_entry', lang('Add to the Staff Directory')),
                $item('inbox', lang('Inbox')),
            ), array('smartActive' => true)),
        )),
    ));
};

// A page of the staff area: the staff bar under the header.
$staff_page = function ($main) use ($root, $shared, $el) {
    return $root(array(
        $shared('header'),
        $shared('staff_bar'),
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
// A file field; what is sent is kept in the template folder named. It
// takes pictures only, so it is a collection's image kind.
$file = function ($id, $name, $folder) use ($el, $attr, $control_class) {
    return $el('input', $control_class, lang('Input'), array(), array('id' => $id, '_attrs' => array(
        $attr('type', 'file'), $attr('name', $name), $attr('accept', 'image/*'),
    ), '_cf' => array('upload_folder_id' => '{{folder:' . $folder . '}}', 'kind' => 'image')));
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
// A question: a search section of its own, so a search that matches it
// opens the page at the question (the marking opens the answer).
$fact = function ($n, $question, $answer, $open = false, $topics = '', $parent = 'pl-facts') use ($el, $para, $attr, $searchable) {
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
        ), array('id' => $id, '_attrs' => array($attr('data-bs-parent', '#' . $parent)))),
    ), $searchable($id . '-item', $topics));
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
        // What is sent opens the conversation page: its first message is
        // this one, and the answers follow as comments. A visitor without an
        // account gets one (auto-registration), which that page tells them.
        'confirmation_page_id' => '{{tab:conversation}}',
        'auto_registration'    => 1,
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
                            $para(lang('Usually within two coffees. On a busy week, within two days. Never longer than it takes Pixel to knock a pen off the desk.'), 'mb-3'),
                            $para(lang('Your message becomes a conversation: the page that opens after you send it, and your inbox, show my answers, and you can write back there. If you have no account yet, one is opened for you.'), 'small mb-3'),
                            $link(lang('Open my inbox'), '{{page:inbox}}', 'btn btn-sm btn-light rounded-pill border border-2 border-dark fw-bold'),
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

// Signing in and out: the layouts their widgets get from the palette, on
// the site's own frame.
$sign_page = function ($key, $name, $title, $widget_key, $folder = 'public') use ($page, $widget) {
    return array(
        'key'     => $key,
        'name'    => $name,
        'folder'  => $folder,
        'title'   => $title,
        'search'  => false,
        'sitemap' => false,
        'tree'    => $page(array($widget($widget_key))),
    );
};
$pages[] = $sign_page('login', lang('login'), lang('Log In'), 'login');
$pages[] = $sign_page('register', lang('register'), lang('Sign Up'), 'register');
$pages[] = $sign_page('forgot_password', lang('forgot-password'), lang('Forgot Password'), 'forgot_password');
$pages[] = $sign_page('set_password', lang('set-password'), lang('Set Password'), 'set_password');
$pages[] = $sign_page('logout', lang('logout'), lang('Logout'), 'logout');

// My account: the member's own pages, behind the registration folder.
$pages[] = array_merge($sign_page('my_account', lang('my-account'), lang('My Account'), 'my_account', 'registration'),
    array('tree' => $page(array($widget('my_account'), $widget('account_invoices'), $widget('account_security')))));
$pages[] = $sign_page('profile', lang('my-profile'), lang('My Profile'), 'profile', 'registration');
$pages[] = $sign_page('change_password', lang('change-password'), lang('Change Password'), 'change_password', 'registration');
$pages[] = $sign_page('email_preferences', lang('email-preferences'), lang('Email Preferences'), 'email_preferences', 'registration');
$pages[] = array_merge($sign_page('address_book', lang('address-book'), lang('Address Book'), 'address_book', 'registration'), array('requires' => 'ecommerce'));

// The comments of a record page (a conversation's replies), in the reading
// column on a card of their own.
$comments_card = function ($name, $icon_name, $title) use ($el, $heading, $icon, $container, $row, $col) {
    return $el('section', 'pb-5', $name, array(
        $container(array(
            $row(array(
                $col(array(
                    $el('div', 'p-4 p-lg-5 rounded-5 border border-3 border-dark bg-body-tertiary', lang('Comments Card'), array(
                        $el('div', 'd-flex align-items-center gap-2 mb-3', lang('Section Header'), array(
                            $icon($icon_name, 'fs-3 text-primary'),
                            $heading('h2', $title, 'h3 fw-bolder mb-0'),
                        )),
                        array('type' => 'region', 'props' => array('regionType' => 'comments_block', 'regionName' => ''), 'children' => array()),
                    )),
                ), '', '8'),
            ), '', 'center'),
        )),
    ));
};

// The inbox: the member's conversations (for the staff, everybody's), and
// the conversation page, where both sides answer as comments.
$pages[] = array(
    'key'     => 'inbox',
    'name'    => lang('inbox'),
    'folder'  => 'registration',
    'title'   => lang('Inbox'),
    'search'  => false,
    'sitemap' => false,
    'tree'    => $page(array(
        $page_header(lang('Your conversations'), lang('Inbox'), lang('The messages you sent me from the say-hi page, and my answers.'), 'info', 'bi-inbox'),
        $el('section', 'py-5', lang('Content'), array(
            $container(array(
                $row(array(
                    $col(array($widget('inbox_list')), '', '8'),
                    $col(array(
                        $el('div', 'p-4 rounded-5 border border-3 border-dark shadow-sm bg-warning-subtle', lang('Note'), array(
                            $el('p', 'd-flex align-items-center gap-2 fw-bold mb-2', lang('Title'), array(
                                $icon('bi-chat-heart', 'fs-4 text-primary'),
                                $span(lang('Got another idea?'), '', lang('Text')),
                            )),
                            $para(lang('Say hi again from the say-hi page; your message and my answers stay here, in one conversation.'), 'mb-3'),
                            $link(lang('New message'), '{{page:say_hi}}', 'btn btn-primary rounded-pill border border-2 border-dark fw-bold'),
                        )),
                    ), '', '4'),
                ), '4', '', 'g-lg-5'),
            )),
        )),
    )),
);
$pages[] = array(
    'key'      => 'conversation',
    'name'     => lang('conversation'),
    'folder'   => 'registration',
    'title'    => lang('Conversation'),
    'search'   => false,
    'sitemap'  => false,
    // The label is the lower-case noun key: the shared 'Reply' is a verb button.
    'comments' => array('label' => lang('reply'), 'allow_new' => true, 'auto_publish' => true, 'show_date' => true, 'login' => true,
                        'email_page' => '{{tab:email_reply}}', 'email_subject' => lang('There is a new reply to your conversation'),
                        'notify_email' => '{{site_email}}'),
    'tree'     => $page(array(
        $widget('conversation'),
        $comments_card(lang('Replies'), 'bi-chat-dots', lang('Replies')),
    )),
);
$pages[] = array(
    'key'     => 'staff_directory',
    'name'    => lang('staff-directory'),
    'folder'  => 'registration',
    'title'   => lang('Staff Directory'),
    'search'  => false,
    'sitemap' => false,
    'tree'    => $page(array(
        $page_header(lang('The crew'), lang('Staff Directory'), lang('The people who help me run this little corner of the web, and how to reach them.'), 'success', 'bi-people'),
        $el('section', 'py-5', lang('Directory'), array(
            $container(array($widget('staff_directory'))),
        )),
    )),
);

// The staff area, behind the private folder.
$staff_shortcut = function ($icon_name, $title, $text, $href, $tone) use ($el, $heading, $para, $icon, $col) {
    return $col(array(
        $el('a', 'd-block h-100 p-4 rounded-5 border border-3 border-dark shadow-sm text-decoration-none link-body-emphasis bg-' . $tone . '-subtle', $title, array(
            $el('span', 'd-inline-flex align-items-center justify-content-center rounded-circle text-bg-primary border border-3 border-dark p-3 lh-1 mb-3', lang('Icon Sticker'), array(
                $icon($icon_name, 'fs-3'),
            )),
            $heading('h3', $title, 'h4 fw-bolder mb-2'),
            $para($text, 'mb-0'),
        ), array('href' => $href)),
    ), '6', '4');
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
        $page_header(lang('Staff only'), lang('Welcome to the staff area'), lang('Write blog posts, keep the staff directory up to date and answer every hello. Everything else lives in the control panel.'), 'warning', 'bi-stars'),
        $el('section', 'py-5', lang('Shortcuts'), array(
            $container(array(
                $row(array(
                    $staff_shortcut('bi-pencil', lang('New Blog Post'), lang('Write a post; it appears on the blog as soon as you send it.'), '{{page:blog_new}}', 'primary'),
                    $staff_shortcut('bi-person-plus', lang('Add to the Staff Directory'), lang('Add a colleague to the directory members see.'), '{{page:staff_entry}}', 'success'),
                    $staff_shortcut('bi-chat-heart', lang('All Conversations'), lang('Every message from the say-hi page. Answer below each one.'), '{{page:inbox}}', 'info'),
                ), '4'),
                $el('div', 'd-flex flex-wrap align-items-center justify-content-between gap-3 mt-4 p-4 rounded-5 border border-3 border-dark bg-body-tertiary', lang('Panel Note'), array(
                    $el('p', 'd-flex gap-2 mb-0', lang('Access Note'), array(
                        $icon('bi-info-circle', 'mt-1 text-primary'),
                        $span(lang('Only the site staff and the users given access to the private folder see these pages. To let someone in, open their account under Users and tick the folder in Private Content Access.'), '', lang('Text')),
                    )),
                    $link(lang('Open the control panel'), $panel_url, 'btn btn-dark rounded-pill border border-2 border-dark fw-bold px-4'),
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
        'title'       => lang('Why I draw my cat every single day'),
        'summary'     => lang('Five years, one cat, two thousand sketches. Here is what drawing the same subject every day taught me.'),
        'content'     => $post_body(array(
            lang('It started as a joke: one drawing of Pixel a day, for a month. Five years later I still have not stopped, and he still has not posed once.'),
            lang('Drawing the same thing every day takes the pressure off. Some days the sketch is great, most days it is just a cat-shaped potato, and that is fine.'),
            lang('The surprise is how much it changed my other work. My lines are looser, I give up less, and I notice small things, like the way a tail says more than a face.'),
        )),
        'cover_image' => 'https://picsum.photos/seed/pinegrap-playground-post-1/1200/675',
    ),
    array(
        'title'       => lang('How I made a game in 48 hours'),
        'summary'     => lang('A game jam, two friends, too many snacks and one very dramatic cloud.'),
        'content'     => $post_body(array(
            lang('The theme was announced on Friday evening: "before the storm". By midnight we had an idea about drying laundry, and by Saturday breakfast a cloud that chased you.'),
            lang('We cut half of our plans on Saturday night. That was the best decision of the weekend: twelve small levels that work are better than thirty that do not.'),
            lang('On Sunday we recorded the music on a ukulele and pressed the upload button with four minutes to spare. I would do it all again next month.'),
        )),
        'cover_image' => 'https://picsum.photos/seed/pinegrap-playground-post-2/1200/675',
    ),
    array(
        'title'       => lang('My favourite pens, ranked by a very serious jury'),
        'summary'     => lang('The jury is me, the cat and a cup of coffee. The results are final and not open to discussion.'),
        'content'     => $post_body(array(
            lang('In third place, the cheap felt-tip that comes in a pack of twenty. It bleeds, it smudges and it is the most fun I have with any pen.'),
            lang('In second place, the brush pen I take everywhere. One pen, thick lines and thin lines, and no excuse not to draw on the bus.'),
            lang('And the winner: the pencil stub that has been in my pocket for three years. It is two centimetres long, and I would run back into a burning house for it.'),
        )),
        'cover_image' => 'https://picsum.photos/seed/pinegrap-playground-post-3/1200/675',
    ),
    array(
        'title'       => lang('A Sunday ride and the goat that judged me'),
        'summary'     => lang('Slow bike rides, one photo each, and a goat with very strong opinions about my helmet.'),
        'content'     => $post_body(array(
            lang('Every Sunday I take my bike out of town with a film camera and one rule: one ride, one photo.'),
            lang('This week the photo chose itself. A goat stood on a wall by the road, looked at my helmet for a long time and then turned away. I have never felt so judged.'),
            lang('The roll is at the lab now. If the goat came out well, it is going on the wall of the guest book page.'),
        )),
        'cover_image' => 'https://picsum.photos/seed/pinegrap-playground-post-4/1200/675',
    ),
);

// The staff's forms: the blog and the staff directory, behind the private
// folder.
$staff_form_page = function ($key, $name, $title, $lead, $icon_name, $widget_key, $form) use ($staff_page, $page_header, $el, $container, $row, $col, $widget) {
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
            $page_header(lang('Staff only'), $title, $lead, 'secondary', $icon_name),
            $el('section', 'py-5', lang('Content'), array(
                $container(array(
                    $row(array(
                        $col(array(
                            $el('div', 'p-4 p-lg-5 rounded-5 border border-4 border-dark shadow bg-body', lang('Form Card'), array(
                                $widget($widget_key),
                            )),
                        ), '', '8'),
                    ), '', 'center'),
                )),
            )),
        )),
    );
};
$pages[] = $staff_form_page('blog_new', lang('new-blog-post'), lang('New Blog Post'),
    lang('Write the post; it appears on the blog as soon as you send it.'), 'bi-pencil', 'blog_form',
    array(
        'form_name'            => lang('Blog Posts'),
        'collection'           => 1,
        'confirmation_message' => lang('The post is published.'),
        'confirmation_page_id' => '{{tab:blog}}',
        // The template's sample posts are written in when the form is made,
        // so the blog and the home page do not open empty
        // (pg_cf_seed_sample_records()).
        'sample_records'       => $sample_posts,
        // The cover is the posts' share image (pg_cf_share_image_field_id()).
        'share_image_field'    => 'cover_image',
    ));
$pages[] = $staff_form_page('staff_entry', lang('add-to-staff-directory'), lang('Add to the Staff Directory'),
    lang('Signed-in members see the directory from their account.'), 'bi-person-plus', 'staff_form',
    array(
        'form_name'            => lang('Staff Directory'),
        'collection'           => 1,
        'confirmation_message' => lang('The person is in the staff directory.'),
        'confirmation_page_id' => '{{tab:staff_directory}}',
    ));

// The search page: the whole site for what the visitor types - pages, the
// parts of pages marked as search sections - with the questions people ask
// most under it, each a search section of its own.
$help_topic = function ($icon_name, $text, $href) use ($el, $icon, $span) {
    return $el('a', 'list-group-item list-group-item-action d-flex align-items-center gap-3 py-3 fw-semibold', lang('Topic'), array(
        $icon($icon_name, 'fs-5 text-primary'),
        $span($text, '', lang('Text')),
    ), array('href' => $href));
};
$pages[] = array(
    'key'              => 'search',
    'name'             => lang('search'),
    'folder'           => 'public',
    'title'            => lang('Find anything'),
    'meta_description' => lang('Search everything on this site: posts, projects, the now page and more.'),
    'tree'             => $page(array(
        $page_header(lang('Lost something?'), lang('Find anything'), lang('Type a word and I will look through every page: posts, projects, the now page. The part that answers you opens with your words marked.'), 'warning', 'bi-search-heart'),
        $el('section', 'py-5', lang('Content'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $el('div', 'p-4 p-lg-5 rounded-5 border border-4 border-dark shadow bg-body', lang('Search Card'), array(
                            $widget('site_search'),
                        )),
                    ), '', '8'),
                    $col(array(
                        $el('div', 'p-4 rounded-5 border border-3 border-dark shadow-sm bg-info-subtle mb-4', lang('Popular Topics'), array(
                            $heading('h2', lang('Popular topics'), 'h4 fw-bolder mb-3'),
                            $el('div', 'list-group rounded-4 border border-2 border-dark', lang('Topics'), array(
                                $help_topic('bi-palette', lang('Things I make'), '{{page:projects}}'),
                                $help_topic('bi-journal-text', lang('Blog'), '{{page:blog}}'),
                                $help_topic('bi-cup-hot', lang('Now'), '{{page:now}}'),
                                $help_topic('bi-chat-heart', lang('Say hi'), '{{page:say_hi}}'),
                            )),
                        )),
                    ), '', '4'),
                ), '4', '', 'g-lg-5'),
            )),
        )),
        $el('section', 'pb-5', lang('Frequently Asked Questions'), array(
            $container(array(
                $section_title('bi-question-circle', lang('Things people ask me'), lang('If yours is not here, say hi.')),
                $el('div', 'accordion rounded-5 border border-3 border-dark overflow-hidden', lang('FAQ'), array(
                    $fact(11, lang('Do you take commissions?'), lang('Yes, a few each season: comics, logos and small websites. Say hi with what you have in mind and when you need it.'), false, lang('commission, hire, freelance, work together, project'), 'pl-help-faq'),
                    $fact(12, lang('Can I use your drawings?'), lang('For a personal blog or a school project, sure, with a link back to this site. For anything else, ask me first.'), false, lang('license, copyright, permission, use, credit'), 'pl-help-faq'),
                    $fact(13, lang('How do I sign the guest book?'), lang('Open the guest book page, pick a mood and write a line. It shows up on the wall right away.'), false, lang('guest book, sign, hello, wall'), 'pl-help-faq'),
                    $fact(14, lang('Where do I find your answer to my message?'), lang('In your inbox: sign in and open Inbox from the menu under your name. Every reply is on the conversation page, and you can write back there.'), false, lang('inbox, reply, answer, conversation'), 'pl-help-faq'),
                ), array('id' => 'pl-help-faq')),
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
$mail_para = function ($text, $class = 'mb-3', $style = 'margin:0 0 16px') use ($para, $attr) {
    return $para($text, $class, array('_attrs' => array($attr('style', $style))));
};
$mail_button = function ($text, $href, $extra = array()) use ($el, $link, $mail_style, $attr) {
    return $el('p', 'my-4', lang('Button'), array(
        $link($text, $href, 'btn btn-primary rounded-pill px-4', array($attr('style',
            'display:inline-block;padding:10px 22px;border-radius:999px;font-weight:700;text-decoration:none;'
            . 'background-color:#a21caf;background-color:var(--bs-primary,#a21caf);color:#ffffff;color:var(--pg-on-primary,#ffffff)')), $extra),
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
// A new reply in a conversation: sent to whoever started it and to the
// staff. The System region carries the reply and a "View or Reply" button.
$pages[] = array_merge($mail_settings, array(
    'key'   => 'email_reply',
    'name'  => lang('email-new-reply'),
    'title' => lang('New Reply E-mail'),
    'tree'  => $mail_page(array(
        $mail_heading(lang('There is a new reply')),
        $mail_para(lang('Something new was written on a page you follow. You can read it below and answer it on the page.')),
        array('type' => 'region', 'props' => array('regionType' => 'system', 'regionName' => ''), 'children' => array()),
    )),
));

// The account e-mails: the welcome after a sign-up and the password reset
// link. Their Account E-mail widgets fill in the member's name and links.
$pages[] = array_merge($mail_settings, array(
    'key'   => 'email_account_welcome',
    'name'  => lang('email-account-welcome'),
    'title' => lang('Account Welcome E-mail'),
    'tree'  => $mail_page(array($widget('account_email_welcome'))),
));
$pages[] = array_merge($mail_settings, array(
    'key'   => 'email_password_reset',
    'name'  => lang('email-password-reset'),
    'title' => lang('Password Reset E-mail'),
    'tree'  => $mail_page(array($widget('account_email_reset'))),
));
// The member's first name after "Hello,", in an element of its own: a
// translated page matches whole texts.
$mail_account_greeting = function () use ($el, $span, $bind, $mail_style) {
    return $el('p', 'mb-3', lang('Greeting'), array(
        $span(lang('Hello,'), 'me-1', lang('Label'), $mail_style('margin-right:4px')),
        $span(lang('Jane'), '', lang('First Name'), $bind('__user_first_name')),
    ), $mail_style('margin:0 0 16px'));
};

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

// The login region's menu: an item bound to an account link (shown only
// while $flag is on), an item with a page of the site, a divider.
$login_item = function ($text, $token, $flag = '') use ($el, $link) {
    $extra = ($flag !== '') ? array('_bindings' => array('eo_visible_if' => $flag)) : array();
    return $el('li', '', lang('Menu Item'), array(
        $link($text, '#', 'dropdown-item fw-semibold', array(), array('_bindings' => array('href' => $token))),
    ), $extra);
};
$login_page_item = function ($text, $href) use ($el, $link) {
    return $el('li', '', lang('Menu Item'), array($link($text, $href, 'dropdown-item fw-semibold')));
};
$login_divider = function () use ($el) {
    return $el('li', '', lang('Divider'), array($el('hr', 'dropdown-divider', lang('Divider Line'))));
};

// An account card: an icon in a round sticker, a title and one line; the
// whole card is the link and the grid cell, so a card the server drops
// leaves no hole. $flag shows the card only while that widget flag is on.
$account_card = function ($icon_name, $title, $text, $href, $tone, $href_token = '', $flag = '') use ($el, $heading, $para, $icon) {
    $extra = array('href' => $href);
    if ($href_token !== '') $extra['_bindings'] = array('href' => $href_token);
    if ($flag !== '') $extra['_bindings']['eo_visible_if'] = $flag;
    return $el('a', 'col-12 col-md-6 col-lg-4 text-decoration-none link-body-emphasis', $title, array(
        $el('div', 'd-flex align-items-start gap-3 h-100 p-4 rounded-5 border border-3 border-dark shadow-sm bg-' . $tone . '-subtle', lang('Card'), array(
            $el('span', 'd-inline-flex align-items-center justify-content-center flex-shrink-0 rounded-circle text-bg-primary border border-2 border-dark p-2 lh-1', lang('Icon Sticker'), array(
                $icon($icon_name, 'fs-4'),
            )),
            $el('div', '', lang('Text'), array(
                $heading('h2', $title, 'h5 fw-bolder mb-1'),
                $para($text, 'small mb-0'),
            )),
        )),
    ), $extra);
};
$account_cards = array(
    $account_card('bi-inbox', lang('Inbox'), lang('Your conversations with us and our answers.'), '{{page:inbox}}', 'info'),
    $account_card('bi-person-vcard', lang('My Profile'), lang('Your name, phone and address.'), '#', 'warning', '__profile_url'),
    $account_card('bi-shield-lock', lang('Change Password'), lang('A new password for your account.'), '#', 'success', '__change_password_url'),
    $account_card('bi-envelope-paper', lang('Email Preferences'), lang('The mailing lists you receive.'), '#', 'danger', '__email_preferences_url'),
);
if ($shop) {
    $account_cards[] = $account_card('bi-journal-bookmark', lang('Address Book'), lang('Where your orders are sent.'), '#', 'secondary', '__address_book_url');
}
$account_cards[] = $account_card('bi-people', lang('Staff Directory'), lang('The people you can reach, and how.'), '{{page:staff_directory}}', 'primary');
// The staff area: drawn only for the members its private folder lets in.
$account_cards[] = $account_card('bi-stars', lang('Staff Area'), lang('Blog posts, the staff directory and every conversation, in one place.'), '#', 'warning', '__staff_url', 'is_staff');

$widgets = array(

    // The header's view of the session.
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
                $link(lang('Log In'), '#', 'btn btn-sm btn-outline-dark rounded-pill border-2 fw-bold text-nowrap', array(), array('_bindings' => array('href' => '__login_url'))),
                $link(lang('Sign Up'), '#', 'btn btn-sm btn-primary rounded-pill border border-2 border-dark fw-bold text-nowrap', array(), array('_bindings' => array('href' => '__register_url'))),
            ), $show_when('is_signed_out')),
            $el('div', 'dropdown', lang('Signed In'), array(
                $el('button', 'btn btn-link d-flex align-items-center gap-2 p-0 border-0 link-body-emphasis text-decoration-none dropdown-toggle fw-bold', lang('Account Button'), array(
                    $image('https://placehold.co/64x64/a21caf/ffffff?text=JC', lang('Jane Cooper'), 'rounded-circle border border-2 border-dark object-fit-cover',
                        array('width' => '32', 'height' => '32', '_bindings' => array('src' => '__user_avatar_url', 'alt' => '__user_name'))),
                    $span(lang('Jane'), '', lang('First Name'), $bind('__user_first_name')),
                ), array('_attrs' => array(
                    $attr('type', 'button'),
                    $attr('data-bs-toggle', 'dropdown'),
                    $attr('aria-expanded', 'false'),
                ))),
                $el('ul', 'dropdown-menu dropdown-menu-end rounded-4 border border-2 border-dark shadow', lang('Account Menu'), array(
                    $el('li', 'px-3 py-2', lang('Account Summary'), array(
                        $span(lang('Jane Cooper'), 'd-block fw-bolder', lang('Full Name'), $bind('__user_name')),
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

    // The member's account, on the widget's own page.
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
                $el('div', 'd-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 p-4 p-lg-5 mb-4 rounded-5 border border-4 border-dark shadow bg-primary-subtle', lang('Account Header'), array(
                    $el('div', '', lang('Greeting'), array(
                        $span(lang('Welcome back'), 'badge rounded-pill text-bg-dark fs-6 mb-3', lang('Sticker')),
                        $heading('h1', lang('Jane Cooper'), 'display-6 fw-bolder mb-1', $bind('__display_name')),
                        $para(lang('jane.cooper@example.com'), 'mb-0', $bind('email_address')),
                    )),
                    $link(lang('Log out'), '#', 'btn btn-light rounded-pill border border-3 border-dark fw-bold px-4', array(), array('_bindings' => array('href' => '__logout_url'))),
                )),
                $el('div', 'row g-3 mb-5', lang('Link Cards'), $account_cards),
                $heading('h2', lang('My Orders'), 'h3 fw-bolder mb-3', $show_when('has_orders')),
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
            'regionType'            => 'registration',
            'show_remember_me'      => true,
            'login_page_id'         => '{{tab:login}}',
            'redirect_page_id'      => '{{tab:my_account}}',
            'welcome_email_page_id' => '{{tab:email_account_welcome}}',
            'welcome_email_subject' => lang('Welcome! Your account is ready'),
        ),
        'tree'   => 'starter',
    ),
    'forgot_password' => array(
        'page'   => 'forgot_password',
        'slug'   => lang('forgot-password'),
        'config' => array(
            'regionType'          => 'forgot_password',
            'login_page_id'       => '{{tab:login}}',
            'reset_email_page_id' => '{{tab:email_password_reset}}',
            'reset_email_subject' => lang('Reset your password'),
        ),
        'tree'   => 'starter',
    ),
    // The account e-mails' widgets, drawn for the member the e-mail is
    // sent to. The reset e-mail's button carries the set password link: a
    // page without it is not sent (the built-in e-mail goes instead).
    'account_email_welcome' => array(
        'page'   => 'email_account_welcome',
        'slug'   => lang('account-email'),
        'config' => array('regionType' => 'account_email'),
        'tree'   => $widget_root(array(
            $mail_heading(lang('Welcome!')),
            $mail_account_greeting(),
            $mail_para(lang('Your account is ready.')),
            $mail_button(lang('Go to my account'), '#', array('_bindings' => array('href' => '__my_account_url'))),
            $mail_para(lang('Thanks for signing up. Come and say hi any time!'), 'small text-body-secondary mb-0', 'font-size:13px;color:#6b7280;margin:0'),
        )),
    ),
    'account_email_reset' => array(
        'page'   => 'email_password_reset',
        'slug'   => lang('account-email'),
        'config' => array('regionType' => 'account_email'),
        'tree'   => $widget_root(array(
            $mail_heading(lang('Reset your password')),
            $mail_account_greeting(),
            $mail_para(lang('We received a request to reset your password.')),
            $mail_button(lang('Set a new password'), '#', array('_bindings' => array('href' => '__set_password_url'))),
            $mail_para(lang('The link works for 24 hours. If you did not ask for it, ignore this e-mail; your password stays the same.'), 'small text-body-secondary mb-0', 'font-size:13px;color:#6b7280;margin:0'),
        )),
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

    // The inbox: conversations the member started (a signed-in hello), or
    // is watching; the staff see them all.
    'inbox_list' => array(
        'page'   => 'inbox',
        'slug'   => lang('form-list'),
        'config' => array(
            'regionType'              => 'form_list_view',
            'custom_form_page_id'     => '{{tab:say_hi}}',
            'detail_page_id'          => '{{tab:conversation}}',
            'items_per_page'          => 20,
            'order_by_field'          => 'submitted_date_and_time',
            'order_by_direction'      => 'DESC',
            'viewer_filter'           => true,
            'viewer_filter_submitter' => true,
            'viewer_filter_watcher'   => true,
            'viewer_filter_editor'    => true,
            'empty_message'           => lang('No conversations yet. Say hi from the say-hi page.'),
        ),
        'tree'   => $widget_root(array(
            $el('div', 'd-flex flex-column gap-3', lang('Conversation List'), array(
                $loop(array(
                    $el('a', 'd-block p-3 p-lg-4 rounded-5 border border-3 border-dark shadow-sm bg-body text-decoration-none link-body-emphasis', lang('Conversation'), array(
                        $el('div', 'd-flex w-100 justify-content-between gap-3', lang('Top Line'), array(
                            $span(lang('A logo for a bakery'), 'fw-bolder text-truncate', lang('Subject'), $bind('subject')),
                            $span(lang('Date'), 'badge rounded-pill text-bg-warning border border-2 border-dark text-nowrap', lang('Date'), $bind('submitted_date_and_time')),
                        )),
                        $el('div', 'd-flex align-items-center gap-2 small text-body-secondary mt-2', lang('Replies'), array(
                            $icon('bi-chat-dots', ''),
                            $span('0', 'fw-bold', lang('Reply Count'), $bind('number_of_comments')),
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
            'custom_form_page_id' => '{{tab:say_hi}}',
            'access_control'      => 'submitter_only',
            'not_found_message'   => lang('This conversation could not be found, or it is not yours to read.'),
        ),
        'tree'   => $widget_root(array(
            $container(array(
                $row(array(
                    $col(array(
                        $el('div', 'text-center py-5', lang('Not Found'), array(
                            $icon('bi-emoji-dizzy', 'display-3 text-primary'),
                            $heading('h2', lang('Conversation not found'), 'h3 fw-bolder mt-3'),
                            $para(lang('This conversation could not be found, or it is not yours to read.'), 'text-body-secondary mb-4', $bind('not_found')),
                            $link(lang('Back to the inbox'), '{{page:inbox}}', 'btn btn-primary rounded-pill border border-2 border-dark fw-bold'),
                        ), $show_when('record_not_found')),
                        $el('div', '', lang('Conversation'), array(
                            $el('div', 'alert alert-success d-flex gap-2 rounded-4 border-2 border-dark', lang('Sent Notice'), array(
                                $icon('bi-check-circle', 'mt-1'),
                                $span(lang('Thank you, your message has reached us. Our answers appear on this page and in your inbox, and you can write back below.'), '', lang('Text')),
                            ), array_merge(array('_attrs' => array($attr('role', 'status'))), $show_when('submitted_this_session'))),
                            $el('div', 'alert alert-info rounded-4 border-2 border-dark', lang('New Account'), array(
                                $el('p', 'fw-bold mb-2', lang('Title'), array(), array('text' => lang('We opened an account for you'))),
                                $el('p', 'mb-1', lang('Sign-in Email'), array(
                                    $span(lang('Email') . ': ', '', lang('Label')),
                                    $span('sample@example.com', 'fw-semibold', lang('Value'), $bind('__new_account_email')),
                                )),
                                // The single-use set password link, for the person who sent the form
                                // while it is live; the sentence below covers a used or expired link.
                                $link(lang('Set your password'), '#', 'btn btn-sm btn-primary rounded-pill border border-2 border-dark fw-bold', array(),
                                    array('_bindings' => array('href' => '__new_account_set_password_url', 'eo_visible_if' => 'has_set_password_link'))),
                                $para(lang('You can set a password with "Forgot password" on the sign-in page.'), 'small mt-2 mb-0'),
                            ), $show_when('has_new_account')),
                            $el('div', 'd-flex flex-wrap align-items-center justify-content-between gap-2 mb-4', lang('Toolbar'), array(
                                $el('a', 'btn btn-sm btn-outline-dark rounded-pill border-2 fw-bold', lang('Link'), array(
                                    $icon('bi-arrow-left', 'me-1'),
                                    $span(lang('Inbox'), '', lang('Text')),
                                ), array('href' => '{{page:inbox}}')),
                                $link(lang('Edit'), '#', 'btn btn-sm btn-outline-secondary rounded-pill', array(),
                                    array('_bindings' => array('href' => '__edit_url', 'eo_visible_if' => 'can_edit'))),
                            )),
                            $el('p', 'd-flex flex-wrap align-items-center gap-2 mb-3', lang('Conversation Details'), array(
                                $span(lang('Date'), 'badge rounded-pill text-bg-warning border border-2 border-dark fs-6', lang('Date'), $bind('submitted_date_and_time')),
                                $span('ABC123', 'badge rounded-pill text-bg-light border border-2 border-dark fs-6', lang('Reference Code'), $bind('reference_code')),
                            )),
                            $heading('h1', lang('A logo for a bakery'), 'display-6 fw-bolder mb-4', $bind('subject')),
                            $el('div', 'p-4 rounded-5 border border-3 border-dark shadow-sm bg-info-subtle', lang('First Message'), array(
                                $el('p', 'd-flex align-items-center gap-2 fw-bold mb-2', lang('Sender'), array(
                                    $icon('bi-person-circle', 'fs-4 text-primary'),
                                    $span(lang('Jane Cooper'), '', lang('Name'), $bind('name')),
                                )),
                                $el('div', 'fs-5 lh-lg', lang('Message'), array(), array_merge(
                                    array('text' => lang('Hi Jane! I run a small bakery and we would love a logo with a croissant that looks a little bit like a cat.')), $bind('message'))),
                            )),
                        ), $show_when('record_found')),
                    ), '', '8'),
                ), '', 'center'),
            ), 'py-5'),
        )),
    ),

    // The staff page's directory form: one person.
    'staff_form' => array(
        'page'   => 'staff_entry',
        'slug'   => 'form',
        'config' => array('regionType' => 'custom_form', 'form_source' => 'page'),
        'tree'   => $widget_root(array(
            $row(array(
                $col(array($field('pl-staff-name', lang('Full Name'), $input('pl-staff-name', 'text', 'full_name', 'off'))), '6'),
                $col(array($field('pl-staff-title', lang('Job Title'), $input('pl-staff-title', 'text', 'job_title', 'off'))), '6'),
                $col(array($field('pl-staff-department', lang('Department'), $input('pl-staff-department', 'text', 'department', 'off', false))), '6'),
                $col(array($field('pl-staff-photo', lang('Photo'), $file('pl-staff-photo', 'photo', 'public'))), '6'),
                $col(array($field('pl-staff-email', lang('Email'), $input('pl-staff-email', 'email', 'email', 'off', false))), '6'),
                $col(array($field('pl-staff-phone', lang('Phone'), $input('pl-staff-phone', 'tel', 'phone', 'off', false))), '6'),
            ), '3'),
            $field('pl-staff-bio', lang('A few words'), $textarea('pl-staff-bio', 'bio', 3, false)),
            $submit(lang('Add'), 'bi-person-plus'),
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
                    $el('div', 'h-100 p-4 rounded-5 border border-3 border-dark shadow-sm bg-body text-center', lang('Person Card'), array(
                        $optional_image('photo', 'full_name', 'rounded-circle border border-3 border-dark object-fit-cover', 'mx-auto mb-3',
                            'https://picsum.photos/seed/pl-person/128/128', lang('Photo')),
                        $heading('h2', lang('Jane Cooper'), 'h4 fw-bolder mb-1', $bind('full_name')),
                        $el('p', 'mb-3', lang('Stickers'), array(
                            $span(lang('Customer Support'), 'badge rounded-pill text-bg-warning border border-2 border-dark', lang('Job Title'), $bind('job_title')),
                        )),
                        $el('p', 'pg-hide-if-empty small mb-1', lang('Department'), array(
                            $icon('bi-diagram-3', 'text-primary me-1'),
                            $span(lang('Operations'), 'pg-field-value', lang('Value'), $bind('department')),
                        )),
                        $el('p', 'pg-hide-if-empty small mb-1 text-break', lang('Email'), array(
                            $icon('bi-envelope-heart', 'text-primary me-1'),
                            $span(lang('jane.cooper@example.com'), 'pg-field-value', lang('Value'), $bind('email')),
                        )),
                        $el('p', 'pg-hide-if-empty small mb-1', lang('Phone'), array(
                            $icon('bi-telephone', 'text-primary me-1'),
                            $span('+90 555 000 00 00', 'pg-field-value', lang('Value'), $bind('phone')),
                        )),
                        $el('p', 'pg-hide-if-empty small text-body-secondary mt-3 mb-0', lang('A few words'), array(
                            $span(lang('Happy to help with orders and accounts.'), 'pg-field-value', lang('Value'), $bind('bio')),
                        )),
                    )),
                ), '6', '4'),
            ))), '4'),
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
                                // The author badge leaves when the record has no contact name.
                                $span(lang('Jane Cooper'), 'pg-hide-if-empty badge rounded-pill text-bg-light border border-2 border-dark fs-6', lang('Author'), $bind('submitter_name')),
                            )),
                            $heading('h1', lang('Why I draw my cat every single day'), 'display-5 fw-bolder mb-4', $bind('title')),
                            $ai_summary(),
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
            $field('pl-post-title', lang('Title'), pg_cf_with_role($input('pl-post-title', 'text', 'title', 'off', true, array($attr('maxlength', '150'))), 'title')),
            $field('pl-post-summary', lang('Summary'), pg_cf_with_role($textarea('pl-post-summary', 'summary', 3), 'description')),
            $field('pl-post-cover', lang('Cover Image'), pg_cf_with_role($file('pl-post-cover', 'cover_image', 'public'), 'media')),
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
            $field('pl-hi-subject', lang('What is it about?'), $input('pl-hi-subject', 'text', 'subject', 'off')),
            $field('pl-hi-message', lang('Your message'), $textarea('pl-hi-message', 'message', 6)),
            $captcha(),
            $submit(lang('Send it over'), 'bi-send'),
            $loop(array()),
        )),
    ),

    // The search page's search: the whole site for ?query=, ten results a
    // page. A result that matches a search section links to that part of its
    // page (^^__result_section^^ names it).
    'site_search' => array(
        'page'   => 'search',
        'slug'   => lang('search'),
        'config' => array(
            'regionType'       => 'search_results',
            'search_scope'     => 'pages',
            'results_per_page' => 10,
            'empty_message'    => lang('Nothing matched your search. Try fewer or different words.'),
        ),
        'tree'   => $widget_root(array(
            $el('form', 'd-flex flex-column flex-sm-row gap-2 mb-4', lang('Search Form'), array(
                $el('input', 'form-control form-control-lg rounded-pill border-2 border-dark', lang('Input'), array(), array(
                    '_attrs'    => array($attr('type', 'search'), $attr('placeholder', lang('Search the site…')), $attr('aria-label', lang('Search the site'))),
                    '_bindings' => array('value' => 'search_query'),
                )),
                $el('button', 'btn btn-primary btn-lg rounded-pill border border-3 border-dark fw-bold px-4', lang('Send'), array(
                    $icon('bi-search', 'ms-2'),
                ), array(
                    'text'      => lang('Search'),
                    '_attrs'    => array($attr('type', 'submit')),
                    '_bindings' => array('action' => 'submit_search'),
                )),
            ), array('_bindings' => array('action' => 'search_form'))),
            $para(lang('Results heading'), 'fw-bold mb-3', $bind('__results_heading')),
            $el('div', 'd-flex flex-column gap-3', lang('Results'), array(
                $loop(array(
                    $el('article', 'position-relative p-3 p-lg-4 rounded-5 border border-3 border-dark shadow-sm bg-body-tertiary', lang('Result Row'), array(
                        $el('h2', 'h4 fw-bolder mb-2', lang('Result Title'), array(
                            $link(lang('Title'), '#', 'stretched-link link-body-emphasis text-decoration-none', array(),
                                array('_bindings' => array('href' => '__result_url', 'text' => '__result_title'))),
                        )),
                        $el('p', 'mb-2', lang('Stickers'), array(
                            $span(lang('Shipping and returns'), 'badge rounded-pill text-bg-warning border border-2 border-dark', lang('Result Section'), $bind('__result_section')),
                        )),
                        $para(lang('Excerpt'), 'mb-0', $bind('__result_excerpt')),
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
            $mail_para(lang('A new message came in from the say-hi page. Answer it on the conversation page: the answer reaches the sender by e-mail and in their inbox.')),
            $mail_box(array(
                $mail_line(lang('Name'), array($span(lang('Jane Cooper'), '', lang('Name'), $bind('name')))),
                $mail_line(lang('Email'), array($span('jane@example.com', '', lang('Email'), $bind('email')))),
                $mail_line(lang('Subject'), array($span(lang('A logo for a bakery'), '', lang('Subject'), $bind('subject')))),
                $mail_line(lang('Found me through'), array($span(lang('A friend told me'), '', lang('Text'), $bind('found_via')))),
            ), lang('Sender')),
            $el('div', 'lh-lg', lang('Message'), array(), array_merge(
                array('text' => lang('Hi Jane! I run a small bakery and we would love a logo with a croissant that looks a little bit like a cat.')), $bind('message'))),
            $mail_button(lang('Open the conversation'), '{{page:conversation}}?r=^^reference_code^^'),
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
            $mail_para(lang('Your message landed safely in my inbox. I read every one of them, usually with a coffee. My answer will be on the conversation page and in your inbox, and you will get an e-mail when it is there.')),
            $mail_box(array(
                $el('div', 'lh-lg', lang('Message'), array(), array_merge(
                    array('text' => lang('Hi Jane! I run a small bakery and we would love a logo with a croissant that looks a little bit like a cat.')), $bind('message'))),
            ), lang('Your Message')),
            $mail_button(lang('Open the conversation'), '{{page:conversation}}?r=^^reference_code^^'),
            // The account the form opened for a visitor who had none: the
            // address it signs in with, and how to choose a password (the
            // set password link is shown on the conversation page, not mailed).
            $el('div', 'border-top pt-3', lang('New Account'), array(
                $el('p', 'mb-2', lang('Sign-in Email'), array(
                    $span(lang('We opened an account for you with this address:') . ' ', '', lang('Label')),
                    $span('sample@example.com', 'fw-semibold', lang('Value'), $bind('__new_account_email')),
                ), $mail_style('margin:0 0 8px')),
                $el('p', 'mb-0', lang('Password'), array(
                    $span(lang('To choose your password, use') . ' ', '', lang('Label')),
                    $link(lang('Forgot Password'), '{{page:forgot_password}}', ''),
                ), $mail_style('margin:0')),
            ), array_merge($mail_style('border-top:1px solid #e5e7eb;padding-top:16px'), $show_when('has_new_account'))),
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
// page, so only the page's own parts are listed here.
$search_sections = array(
    'about' => array(
        lang('Story')    => array('my-story', lang('about me, who I am, illustrator, maker')),
        lang('Timeline') => array('timeline', lang('history, milestones, school, first job')),
    ),
    'now' => array(
        lang('Content') => array('now', lang('current projects, this month, what I am doing')),
    ),
    'say_hi' => array(
        lang('Contact Details') => array('other-ways-to-reach-me', lang('e-mail, instagram, location, contact')),
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
    'name'        => lang('Playground'),
    'version'     => '1.0.11',
    'framework'   => 'bootstrap5',
    'order'       => 15,
    'icon'        => 'bi-emoji-smile',
    // The picture on the template's card and beside the designs made from
    // it: a cheerful personal home page (pg_design_thumb_svg()).
    'thumb'       => 'playground',
    'description' => lang('A playful personal site: a big hello, the things you make, a blog with comments, a guest book, a page to say hi whose messages become conversations, account pages with an inbox, a staff area and a page that searches the whole site.'),

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
        lang('Sign-in and account pages, an inbox for every hello and a staff area come ready.'),
    ),

    // Made (or found again) when the template is opened; see _pg_tpl_folders().
    'folders'     => array(
        'root'         => array('name' => 'pinegrap_playground', 'access' => 'public'),
        'public'       => array('name' => 'public', 'parent' => 'root', 'access' => 'public'),
        'registration' => array('name' => 'registration', 'parent' => 'root', 'access' => 'registration'),
        'private'      => array('name' => 'private', 'parent' => 'root', 'access' => 'private'),
    ),

    'pages'       => $pages,

    'widgets'     => $widgets,

    'shared'      => array(
        'header'       => array('name' => lang('Playground Header'), 'tree' => $header()),
        'footer'       => array('name' => lang('Playground Footer'), 'tree' => $footer()),
        'cta'          => array('name' => lang('Say Hi Call to Action'), 'tree' => $cta()),
        'staff_bar'    => array('name' => lang('Playground Staff Bar'), 'tree' => $staff_bar()),
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
