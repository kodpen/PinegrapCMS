/**
 * Pinegrap - Enterprise Website Platform
 *
 * The marking of a site search on the page a result led to. A result that
 * matched a part of a page marked as a search section in the visual editor
 * links to it as page?highlight=<search>#<section id>
 * (_pg_sw_search_attach_sections()); this file is added to the page only
 * for such a request (pg_search_highlight_inject()).
 *
 * The words of the search are marked in yellow (<mark class="pg-search-hit">)
 * inside the section - on the whole main content when the anchor names no
 * element - the section is outlined for a moment and scrolled to, and the
 * parameter leaves the address so a copied link is the plain page. Escape
 * takes the marks away. A mark in a folded part - an accordion's answer, a
 * closed <details> - opens that part, so what the search found is in view.
 *
 * Matching ignores case, and for Turkish i / ı / İ / I are one letter, as a
 * visitor types them. Text in scripts, styles, form fields and code is left
 * alone.
 *
 * Plain ES5 and no jQuery: it has to work whatever the design loads.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

(function () {
    'use strict';

    var PARAM = 'highlight';

    function readQuery() {
        var m = new RegExp('[?&]' + PARAM + '=([^&#]*)').exec(window.location.search);
        if (!m) return '';
        try {
            return decodeURIComponent(m[1].replace(/\+/g, ' ')).trim().slice(0, 100);
        } catch (e) {
            return '';
        }
    }

    function escapeRe(s) {
        return s.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    }

    // One pattern piece per letter: the four Turkish i's match each other,
    // whatever the case.
    function letterRe(word) {
        var out = '';
        for (var i = 0; i < word.length; i++) {
            var ch = word.charAt(i);
            out += /[iıİI]/.test(ch) ? '[iıİI]' : escapeRe(ch);
        }
        return out;
    }

    // The whole phrase first, then its words of two letters or more.
    function buildPattern(query) {
        var parts = [];
        var phrase = query.replace(/["+]/g, ' ').replace(/\s+/g, ' ').trim();
        if (!phrase) return null;
        parts.push(letterRe(phrase));
        phrase.split(' ').forEach(function (w) {
            if (w.length >= 2 && w !== phrase) parts.push(letterRe(w));
        });
        try {
            return new RegExp('(' + parts.join('|') + ')', 'gi');
        } catch (e) {
            return null;
        }
    }

    var SKIP = /^(SCRIPT|STYLE|NOSCRIPT|TEXTAREA|INPUT|SELECT|OPTION|CODE|PRE|MARK|SVG|TEMPLATE)$/;

    function markIn(root, re) {
        var walker = document.createTreeWalker(root, NodeFilter.SHOW_TEXT, {
            acceptNode: function (n) {
                if (!n.nodeValue || !n.nodeValue.trim()) return NodeFilter.FILTER_REJECT;
                for (var p = n.parentNode; p && p !== root.parentNode; p = p.parentNode) {
                    if (p.nodeType === 1 && (SKIP.test(p.nodeName.toUpperCase()) || p.isContentEditable)) return NodeFilter.FILTER_REJECT;
                }
                return NodeFilter.FILTER_ACCEPT;
            }
        });
        var nodes = [];
        while (walker.nextNode()) nodes.push(walker.currentNode);

        var first = null;
        nodes.forEach(function (node) {
            var text = node.nodeValue;
            re.lastIndex = 0;
            if (!re.test(text)) return;
            re.lastIndex = 0;
            var frag = document.createDocumentFragment();
            var last = 0, m;
            while ((m = re.exec(text)) !== null) {
                if (m[0] === '') { re.lastIndex++; continue; }
                if (m.index > last) frag.appendChild(document.createTextNode(text.slice(last, m.index)));
                var mark = document.createElement('mark');
                mark.className = 'pg-search-hit';
                mark.textContent = m[0];
                frag.appendChild(mark);
                if (!first) first = mark;
                last = m.index + m[0].length;
            }
            if (last < text.length) frag.appendChild(document.createTextNode(text.slice(last)));
            node.parentNode.replaceChild(frag, node);
        });
        return first;
    }

    function addStyle() {
        if (document.getElementById('pg-search-highlight-css')) return;
        var css = document.createElement('style');
        css.id = 'pg-search-highlight-css';
        css.textContent =
            'mark.pg-search-hit{background:#ffe066;color:inherit;padding:0 .12em;border-radius:.2em;box-shadow:0 0 0 1px rgba(250,176,5,.35)}' +
            '[data-bs-theme="dark"] mark.pg-search-hit{background:#ffd43b;color:#212529}' +
            '.pg-search-target{outline:3px solid #ffd43b;outline-offset:6px;border-radius:.35rem;transition:outline-color 1.2s ease}' +
            '.pg-search-target.pg-search-target-fade{outline-color:transparent}' +
            '@media (prefers-reduced-motion: reduce){.pg-search-target{transition:none}}';
        document.head.appendChild(css);
    }

    // CSS.escape where the browser has it; ids made by the editor are plain
    // words and hyphens anyway.
    function cssId(id) {
        return (window.CSS && window.CSS.escape) ? window.CSS.escape(id) : id.replace(/([^\w-])/g, '\\$1');
    }

    // Open the folded parts the marks sit in: a Bootstrap collapse gets the
    // class it shows with and its toggles the state Bootstrap gives them, so
    // the next click folds it as usual; a <details> opens.
    function reveal(marks) {
        for (var i = 0; i < marks.length; i++) {
            for (var p = marks[i].parentNode; p && p !== document.body; p = p.parentNode) {
                if (p.nodeType !== 1) continue;
                if (p.nodeName.toUpperCase() === 'DETAILS') p.open = true;
                if (p.classList && p.classList.contains('collapse') && !p.classList.contains('show')) {
                    p.classList.add('show');
                    if (!p.id) continue;
                    var toggles = document.querySelectorAll('[data-bs-target="#' + cssId(p.id) + '"], [href="#' + cssId(p.id) + '"]');
                    for (var t = 0; t < toggles.length; t++) {
                        toggles[t].classList.remove('collapsed');
                        toggles[t].setAttribute('aria-expanded', 'true');
                    }
                }
            }
        }
    }

    function clearMarks() {
        var marks = document.querySelectorAll('mark.pg-search-hit');
        for (var i = 0; i < marks.length; i++) {
            var m = marks[i];
            m.parentNode.replaceChild(document.createTextNode(m.textContent), m);
        }
        document.body.normalize();
        var t = document.querySelectorAll('.pg-search-target');
        for (var j = 0; j < t.length; j++) t[j].classList.remove('pg-search-target', 'pg-search-target-fade');
    }

    function run() {
        var query = readQuery();
        var re = query ? buildPattern(query) : null;
        if (!re) return;

        var id = '';
        try { id = decodeURIComponent(window.location.hash.slice(1)); } catch (e) { id = ''; }
        var target = id ? document.getElementById(id) : null;
        var scope = target || document.querySelector('main') || document.body;

        addStyle();
        var first = markIn(scope, re);
        // Every folded part of the section that holds a mark; without a
        // section, only the first mark's, so a page of questions does not
        // open all at once.
        reveal(target ? target.querySelectorAll('mark.pg-search-hit') : (first ? [first] : []));

        if (target) {
            target.classList.add('pg-search-target');
            setTimeout(function () { target.classList.add('pg-search-target-fade'); }, 2500);
        }
        var to = target || first;
        if (to && to.scrollIntoView) {
            var smooth = !(window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches);
            to.scrollIntoView({ behavior: smooth ? 'smooth' : 'auto', block: target ? 'start' : 'center' });
        }

        // The plain page from now on: a copied or bookmarked address does not
        // carry the marking; the anchor stays.
        try {
            var url = window.location.pathname
                + window.location.search.replace(new RegExp('([?&])' + PARAM + '=[^&#]*&?'), '$1').replace(/[?&]$/, '')
                + window.location.hash;
            window.history.replaceState(window.history.state, '', url);
        } catch (e) {}

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' || e.key === 'Esc') clearMarks();
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', run);
    } else {
        run();
    }
})();
