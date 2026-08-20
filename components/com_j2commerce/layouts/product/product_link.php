<?php

/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

defined('_JEXEC') or die;

use J2Commerce\Component\J2commerce\Site\Helper\RouteHelper;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Router\Route;
use Joomla\Component\Content\Site\Helper\RouteHelper as ContentRouteHelper;

/**
 * Renders a product title as a link to the surface the store publishes on, or as plain escaped
 * text when nothing resolves. Site-side twin of the admin product.article_edit_link layout, so
 * modules, plugins and third-party extensions get one link with one behaviour.
 *
 * Which surface is not this layout's decision: product_surface_mode already states it, and the
 * system plugin 301s the other URL anyway. Honouring the mode only spares the redirect hop.
 *
 * $displayData:
 *   title             string  Text rendered inside the link; nothing renders without it
 *   product_id        int     J2Commerce product id
 *   linkable          bool    False forces plain text — deleted or unpublished product
 *   article_id        int     Source article id, 0 when the product has no article
 *   article_alias     string  Source article alias, for the SEF segment
 *   article_catid     int     Source article category, needed for the canonical product path
 *   article_language  string  Source article language tag
 *   tag               string  Wrapping element, none by default
 *   class             string  Extra classes on the anchor
 *   attribs           array   Additional attributes, name => value
 *
 * No query is issued here. Callers list many rows, and a layout cannot hold a cache to spare
 * them — a `static` in an included file is scoped to that one include and re-initialises on
 * every render. Everything the URL needs is passed in.
 */

$title = (string) ($displayData['title'] ?? '');

if ($title === '') {
    return;
}

$tag     = preg_replace('/[^a-z0-9]/i', '', (string) ($displayData['tag'] ?? ''));
$openTag = $tag !== '' ? '<' . $tag . '>' : '';
$endTag  = $tag !== '' ? '</' . $tag . '>' : '';
$escaped = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');

$productId = (int) ($displayData['product_id'] ?? 0);
$linkable  = (bool) ($displayData['linkable'] ?? true);

if (!$linkable || $productId < 1) {
    echo $openTag . $escaped . $endTag;

    return;
}

$articleId = (int) ($displayData['article_id'] ?? 0);
$alias     = (string) ($displayData['article_alias'] ?? '');
$catid     = (int) ($displayData['article_catid'] ?? 0);
$language  = (string) ($displayData['article_language'] ?? '');

$mode = (string) ComponentHelper::getParams('com_j2commerce')->get('product_surface_mode', 'both');

// Only the article mode has a second URL to prefer, and only when an article backs the product.
// Everything else — including a product whose article has been unpublished out from under it —
// falls through to the product view, which is the surface the component always serves.
if ($mode === 'article' && $articleId > 0) {
    $link = ContentRouteHelper::getArticleRoute(
        $alias !== '' ? $articleId . ':' . $alias : (string) $articleId,
        $catid,
        $language ?: null
    );
} else {
    // Both route helpers already drop a '*' tag and a single-language site themselves, so the
    // language goes through raw — same as the system plugin's own getProductSurfaceUrl().
    $link = RouteHelper::getProductRoute(
        $productId,
        $alias ?: null,
        $catid ?: null,
        $language ?: null
    );
}

// Unescaped on purpose: Route::_() escapes the ampersands itself when asked, and this layout
// escapes the whole href below — asking for both turns &amp; into &amp;amp;.
// Route::_() also returns null on router error today and will throw from Joomla 7. A cart line
// is not worth a fatal, so a failure degrades to the text it would have wrapped.
try {
    $url = (string) (Route::_($link, false) ?? '');
} catch (\RuntimeException) {
    $url = '';
}

if ($url === '') {
    echo $openTag . $escaped . $endTag;

    return;
}

$classes = trim('j2c-product-link ' . (string) ($displayData['class'] ?? ''));

$extra = '';

foreach ((array) ($displayData['attribs'] ?? []) as $name => $value) {
    $name = (string) $name;

    // Escaping the value is not enough on its own: a space or '=' inside the name would end the
    // attribute and start a second one, so the name has to be a name before it is emitted.
    // Anchored with \z rather than $, which would accept a trailing newline. Event handlers are
    // refused outright — the browser decodes an attribute value before evaluating it as script,
    // so escaping cannot defuse one, and this layout is documented for third-party callers where
    // the name may come from data the store owner does not control.
    if (!preg_match('/^[a-zA-Z][a-zA-Z0-9-]*\z/', $name) || stripos($name, 'on') === 0) {
        continue;
    }

    $extra .= ' ' . $name . '="' . htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8') . '"';
}

?>
<?php echo $openTag; ?><a href="<?php echo htmlspecialchars($url, ENT_QUOTES, 'UTF-8'); ?>" class="<?php echo htmlspecialchars($classes, ENT_QUOTES, 'UTF-8'); ?>"<?php echo $extra; ?>><?php echo $escaped; ?></a><?php echo $endTag; ?>
