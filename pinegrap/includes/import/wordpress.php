<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The WordPress export (WXR) reader of the import framework
 * (includes/fn/importers.php). The file is walked with XMLReader one <item>
 * at a time, never read whole. Each item becomes one framework item:
 *
 * - post and custom post types: a record of the chosen form (kind record),
 *   followed by its approved and held comments (kind comment), threads kept;
 * - page: a page (kind page, written by the pages path);
 * - attachment: a file (kind file, fetched by the media path).
 *
 * Menu items, revisions, block editor internals, posts in the bin and
 * auto-drafts are not imported. Source keys carry the old site's host, so
 * exports of two sites never share a key.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

const PG_IMPORT_WP_NS = 'http://wordpress.org/export/';

/**
 * Post types that are WordPress internals, never content.
 *
 * @return string[]
 */
function pg_import_wp_skipped_types()
{
    return array(
        'nav_menu_item', 'revision', 'custom_css', 'customize_changeset', 'oembed_cache', 'user_request',
        'wp_block', 'wp_template', 'wp_template_part', 'wp_global_styles', 'wp_navigation', 'wp_font_family', 'wp_font_face',
    );
}

/**
 * A WordPress status as the record's: published or draft; '' when the post
 * is not imported (in the bin, an auto-draft). Pure.
 *
 * @param string $status
 * @return string
 */
function pg_import_wp_status($status)
{
    $status = strtolower(trim((string) $status));

    if (in_array($status, array('publish', 'inherit'), true)) {
        return 'published';
    }

    if (in_array($status, array('draft', 'pending', 'future', 'private'), true)) {
        return 'draft';
    }

    return '';
}

/**
 * The time of a post: its GMT date when it has one (a draft carries
 * 0000-00-00), else its local date read in the old site's zone. 0 when
 * neither is a date. Pure.
 *
 * @param string $gmt
 * @param string $local
 * @param string $zone a time zone name, '' for UTC
 * @return int
 */
function pg_import_wp_time($gmt, $local, $zone = '')
{
    $pattern = '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/';
    $gmt = trim((string) $gmt);
    $local = trim((string) $local);

    if ((preg_match($pattern, $gmt) === 1) && (strncmp($gmt, '0000', 4) !== 0)) {
        $date = DateTime::createFromFormat('Y-m-d H:i:s', $gmt, new DateTimeZone('UTC'));

        return ($date !== false) ? $date->getTimestamp() : 0;
    }

    if ((preg_match($pattern, $local) === 1) && (strncmp($local, '0000', 4) !== 0)) {
        try {
            $tz = new DateTimeZone(($zone !== '') ? $zone : 'UTC');
        } catch (\Exception $error) {
            $tz = new DateTimeZone('UTC');
        }

        $date = DateTime::createFromFormat('Y-m-d H:i:s', $local, $tz);

        return ($date !== false) ? $date->getTimestamp() : 0;
    }

    return 0;
}

/**
 * Shortcodes of the fixed list handled; every other one stays as text. A
 * caption keeps what it wraps (the image and its text), an embed becomes its
 * address as a link, a gallery goes: its pictures are the post's
 * attachments. Pure.
 *
 * @param string $html
 * @return string
 */
function pg_import_wp_shortcodes($html)
{
    $html = (string) $html;

    $html = preg_replace('/\[caption\b[^\]]*\](.*?)\[\/caption\]/is', '$1', $html);

    $html = preg_replace_callback('/\[embed\b[^\]]*\](.*?)\[\/embed\]/is', function ($match) {
        $url = trim(strip_tags($match[1]));

        if (preg_match('#^https?://#i', $url) !== 1) {
            return '';
        }

        return '<a href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '</a>';
    }, $html);

    $html = preg_replace('/\[gallery\b[^\]]*\]/i', '', $html);

    return trim($html);
}

/**
 * The classic editor's text as HTML: blocks apart by a blank line become
 * paragraphs and a single line break a <br>, as WordPress shows them. Text
 * the block editor wrote (it holds <p> already) stays as it is. Pure.
 *
 * @param string $html
 * @return string
 */
function pg_import_wp_autop($html)
{
    $html = trim(str_replace(array("\r\n", "\r"), "\n", (string) $html));

    if (($html === '') || (preg_match('/<p[\s>]/i', $html) === 1)) {
        return $html;
    }

    $block = '/^<(h[1-6]|ul|ol|li|table|blockquote|pre|figure|div|hr|img)\b/i';
    $out = array();

    foreach (preg_split('/\n\s*\n/', $html) as $part) {
        $part = trim($part);

        if ($part === '') {
            continue;
        }

        $out[] = (preg_match($block, $part) === 1) ? $part : '<p>' . str_replace("\n", "<br>\n", $part) . '</p>';
    }

    return implode("\n", $out);
}

/**
 * One <item> as framework items: the post, page or attachment first, then
 * its comments. Empty when the item is not imported. Pure.
 *
 * @param SimpleXMLElement $item
 * @param array            $site pg_import_wp_site(): key, ns, zone, authors
 * @return array[]
 */
function pg_import_wp_item($item, $site)
{
    $ns = (string) ($site['ns'] ?? PG_IMPORT_WP_NS . '1.2/');
    $wp = $item->children($ns);
    $content = $item->children('http://purl.org/rss/1.0/modules/content/');
    $excerpt = $item->children($ns . 'excerpt/');
    $dc = $item->children('http://purl.org/dc/elements/1.1/');

    $type = trim((string) $wp->post_type);
    $status = pg_import_wp_status((string) $wp->status);
    $post_id = (int) $wp->post_id;

    if (($type === '') || ($post_id <= 0) || ($status === '') || in_array($type, pg_import_wp_skipped_types(), true)) {
        return array();
    }

    $key = (string) $site['key'];
    $title = trim((string) $item->title);
    $time = pg_import_wp_time((string) $wp->post_date_gmt, (string) $wp->post_date, (string) ($site['zone'] ?? ''));

    if ($type === 'attachment') {
        $url = trim((string) $wp->attachment_url);

        return array(array(
            'kind'       => 'file',
            'source_key' => $key . ':post:' . $post_id,
            'title'      => ($title !== '') ? $title : pg_basename((string) parse_url($url, PHP_URL_PATH)),
            'data'       => array('url' => $url, 'caption' => trim((string) $excerpt->encoded), 'parent' => (int) $wp->post_parent, 'time' => $time),
        ));
    }

    $login = trim((string) $dc->creator);
    $author = $site['authors'][$login] ?? array('login' => $login, 'name' => $login, 'email' => '');

    $categories = array();
    $tags = array();

    foreach ($item->category as $term) {
        $domain = (string) $term['domain'];
        $name = trim((string) $term);

        if ($name === '') {
            continue;
        }

        if ($domain === 'category') {
            $categories[] = $name;
        } elseif ($domain === 'post_tag') {
            $tags[] = $name;
        }
    }

    $thumbnail = 0;

    foreach ($wp->postmeta as $meta) {
        if ((string) $meta->meta_key === '_thumbnail_id') {
            $thumbnail = (int) $meta->meta_value;
        }
    }

    $post = array(
        'kind'       => ($type === 'page') ? 'page' : 'record',
        'source_key' => $key . ':post:' . $post_id,
        'title'      => $title,
        'data'       => array(
            'post_type'  => $type,
            'status'     => $status,
            'body'       => pg_import_wp_autop(pg_import_wp_shortcodes((string) $content->encoded)),
            'excerpt'    => trim((string) $excerpt->encoded),
            'time'       => $time,
            'slug'       => trim((string) $wp->post_name),
            'link'       => trim((string) $item->link),
            'parent'     => (int) $wp->post_parent,
            'author'     => $author,
            'categories' => $categories,
            'tags'       => $tags,
            // The featured image as the attachment's source key: the file
            // item may come later in the export, the writer finds it then.
            'picture'    => ($thumbnail > 0) ? $key . ':post:' . $thumbnail : '',
        ),
    );

    $items = array($post);

    foreach ($wp->comment as $comment) {
        $comment_type = trim((string) $comment->comment_type);
        $approved = trim((string) $comment->comment_approved);

        // Pingbacks and trackbacks are not conversations; spam and the bin
        // are not kept.
        if (!in_array($comment_type, array('', 'comment'), true) || !in_array($approved, array('0', '1'), true)) {
            continue;
        }

        $comment_id = (int) $comment->comment_id;
        $parent = (int) $comment->comment_parent;

        $items[] = array(
            'kind'       => 'comment',
            'source_key' => $key . ':comment:' . $comment_id,
            'title'      => mb_substr(trim(strip_tags((string) $comment->comment_content)), 0, 80),
            'data'       => array(
                'post'      => $post['source_key'],
                'post_kind' => $post['kind'],
                'parent'    => ($parent > 0) ? $key . ':comment:' . $parent : '',
                'name'      => trim((string) $comment->comment_author),
                'email'     => trim((string) $comment->comment_author_email),
                'message'   => trim((string) $comment->comment_content),
                'published' => ($approved === '1'),
                'time'      => pg_import_wp_time((string) $comment->comment_date_gmt, (string) $comment->comment_date, (string) ($site['zone'] ?? '')),
            ),
        );
    }

    return $items;
}

/**
 * The site part of an export: its host as the key prefix and the authors by
 * login. Read from the channel head, before the first <item>.
 *
 * @param string $file
 * @return array{key:string,ns:string,authors:array,zone:string}
 */
function pg_import_wp_site($file)
{
    $site = array('key' => 'wp', 'ns' => PG_IMPORT_WP_NS . '1.2/', 'authors' => array(), 'zone' => '');
    $reader = pg_import_wp_reader($file);

    if ($reader === null) {
        return $site;
    }

    while (@$reader->read()) {

        if ($reader->nodeType !== XMLReader::ELEMENT) {
            continue;
        }

        if ($reader->localName === 'item') {
            break;
        }

        if (strpos((string) $reader->namespaceURI, PG_IMPORT_WP_NS) !== 0) {
            continue;
        }

        // WXR 1.0, 1.1 and 1.2 files are all in use: the namespace is read
        // from the file.
        if (preg_match('#^' . preg_quote(PG_IMPORT_WP_NS, '#') . '\d+\.\d+/$#', (string) $reader->namespaceURI) === 1) {
            $site['ns'] = (string) $reader->namespaceURI;
        }

        if ($reader->localName === 'base_site_url') {
            $host = strtolower((string) parse_url(trim($reader->readString()), PHP_URL_HOST));

            if ($host !== '') {
                $site['key'] = preg_replace('/^www\./', '', $host);
            }
        } elseif ($reader->localName === 'author') {
            $node = @simplexml_load_string($reader->readOuterXml(), 'SimpleXMLElement', LIBXML_NONET | LIBXML_NOCDATA);

            if ($node !== false) {
                $author = $node->children($reader->namespaceURI);
                $login = trim((string) $author->author_login);

                if ($login !== '') {
                    $site['authors'][$login] = array(
                        'login' => $login,
                        'name'  => (trim((string) $author->author_display_name) !== '') ? trim((string) $author->author_display_name) : $login,
                        'email' => trim((string) $author->author_email),
                    );
                }
            }

            $reader->next();
        }
    }

    $reader->close();

    return $site;
}

/**
 * An XMLReader on the export, null when it cannot be opened. PHPExcel
 * switches libxml's entity loader off for the whole request, which stops
 * XMLReader opening any file; it is switched on again as for the office
 * readers (includes/fn/office.php). Nothing here asks libxml to resolve
 * entities (no LIBXML_NOENT, always LIBXML_NONET).
 *
 * @param string $file
 * @return XMLReader|null
 */
function pg_import_wp_reader($file)
{
    if (function_exists('libxml_disable_entity_loader')) {
        @libxml_disable_entity_loader(false); // phpcs:ignore PHPCompatibility.FunctionUse.RemovedFunctions.libxml_disable_entity_loaderDeprecated -- guarded by function_exists(); undoes PHPExcel's switch-off, which stops XMLReader opening the export
    }

    $reader = new XMLReader();

    return @$reader->open((string) $file, null, LIBXML_NONET) ? $reader : null;
}

// ---- The reader contract --------------------------------------------------

function pg_import_wordpress_open($file)
{
    return array(
        'file'    => (string) $file,
        'index'   => 0,
        'pending' => array(),
        'site'    => pg_import_wp_site((string) $file),
    );
}

/**
 * The next item; null at the end. The cursor counts the <item> elements
 * read and holds the comments of the last one still to hand out. Within one
 * request the XMLReader stays open between calls; a new request opens the
 * file again and skips the items already read.
 *
 * @param array $cursor
 * @return array|null
 */
function pg_import_wordpress_next(&$cursor)
{
    static $open = null;

    if (!empty($cursor['pending'])) {
        return array_shift($cursor['pending']);
    }

    $file = (string) ($cursor['file'] ?? '');
    $index = (int) ($cursor['index'] ?? 0);

    if (($open === null) || ($open['file'] !== $file) || ($open['index'] !== $index)) {

        if ($open !== null) {
            $open['reader']->close();
        }

        $reader = pg_import_wp_reader($file);

        if ($reader === null) {
            $open = null;

            return null;
        }

        $open = array('file' => $file, 'index' => 0, 'reader' => $reader);
        $found = pg_import_wp_seek($reader);

        while ($found && ($open['index'] < $index)) {
            $reader->next('item');
            $open['index']++;
            $found = ($reader->nodeType === XMLReader::ELEMENT) && ($reader->localName === 'item');
        }

        $open['at_item'] = $found;
    }

    while (!empty($open['at_item'])) {

        $xml = $open['reader']->readOuterXml();
        $open['reader']->next('item');
        $open['index']++;
        $open['at_item'] = ($open['reader']->nodeType === XMLReader::ELEMENT) && ($open['reader']->localName === 'item');
        $cursor['index'] = $open['index'];

        $node = @simplexml_load_string(pg_import_wp_wrap($xml, (string) ($cursor['site']['ns'] ?? PG_IMPORT_WP_NS . '1.2/')), 'SimpleXMLElement', LIBXML_NONET | LIBXML_NOCDATA);
        $items = ($node !== false) ? pg_import_wp_item($node->item, (array) $cursor['site']) : array();

        if ($items) {
            $first = array_shift($items);
            $cursor['pending'] = $items;

            return $first;
        }
    }

    $open['reader']->close();
    $open = null;

    return null;
}

// Moves a fresh reader to the first <item>; false when there is none.
function pg_import_wp_seek($reader)
{
    while (@$reader->read()) {
        if (($reader->nodeType === XMLReader::ELEMENT) && ($reader->localName === 'item')) {
            return true;
        }
    }

    return false;
}

// readOuterXml() may give the item without the namespaces declared on
// <rss>; it is read inside an element that declares them again.
function pg_import_wp_wrap($xml, $ns)
{
    return '<wrap xmlns:content="http://purl.org/rss/1.0/modules/content/" xmlns:dc="http://purl.org/dc/elements/1.1/"'
        . ' xmlns:wfw="http://wellformedweb.org/CommentAPI/" xmlns:wp="' . htmlspecialchars($ns, ENT_QUOTES, 'UTF-8') . '"'
        . ' xmlns:excerpt="' . htmlspecialchars($ns . 'excerpt/', ENT_QUOTES, 'UTF-8') . '">' . $xml . '</wrap>';
}

/**
 * The dry run's line for an item.
 *
 * @param array $item
 * @return array{title:string,detail:string}
 */
function pg_import_wordpress_plan($item)
{
    $data = (array) ($item['data'] ?? array());
    $kind = (string) ($item['kind'] ?? '');

    if ($kind === 'file') {
        return array('title' => (string) $item['title'], 'detail' => (string) ($data['url'] ?? ''));
    }

    if ($kind === 'comment') {
        return array('title' => (string) $item['title'], 'detail' => (string) ($data['name'] ?? ''));
    }

    $detail = array((string) ($data['post_type'] ?? ''));

    if (($data['status'] ?? '') === 'draft') {
        $detail[] = lang('Draft');
    }

    if (!empty($data['author']['name'])) {
        $detail[] = (string) $data['author']['name'];
    }

    return array('title' => (string) $item['title'], 'detail' => implode(', ', array_filter($detail)));
}

/**
 * The mapping rows import.php asks for: the form posts become records of,
 * which of its fields takes each part of a post, and the folders pages and
 * files go to.
 *
 * @return array
 */
function pg_import_wordpress_mapping()
{
    $field = function ($label) {
        return array('label' => $label, 'type' => 'form_field', 'form' => 'form');
    };

    return array(
        'form'        => array('label' => lang('Form the posts become records of'), 'type' => 'form', 'required' => true),
        'title'       => $field(lang('Title')) + array('required' => true),
        'body'        => $field(lang('Body')),
        'excerpt'     => $field(lang('Summary')),
        'date'        => $field(lang('Date')),
        'picture'     => $field(lang('Featured image')),
        'categories'  => $field(lang('Categories')),
        'tags'        => $field(lang('Tags')),
        'page_folder' => array('label' => lang('Folder for pages'), 'type' => 'folder'),
        'file_folder' => array('label' => lang('Folder for files'), 'type' => 'folder'),
    );
}
