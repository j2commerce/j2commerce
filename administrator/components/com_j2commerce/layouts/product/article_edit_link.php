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

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;

/**
 * Renders a product title as a link to its com_content article edit form, or as plain
 * escaped text when no article resolves. Every admin surface that shows a product can
 * call this instead of rebuilding the link and the checked-out handling itself.
 *
 * $displayData:
 *   product_id  int     J2Commerce product id; used to resolve the article when article_id is absent
 *   article_id  int     Article id when the caller already has it — skips the lookup
 *   title       string  Text rendered inside the link
 *   return      string  Return URL, UNENCODED; this layout does the base64
 *   tag         string  Wrapping element, none by default
 *   class       string  Extra classes on the anchor
 *   attribs     array   Additional attributes, name => value
 *
 * Callers listing many products should pass article_id: resolving from product_id costs one
 * query per call, and a layout cannot hold a cache to spare them. PHP scopes a `static` in an
 * included file to that one include, so it is re-initialised on every render and nothing
 * survives between rows — hence no memoisation here, and no registration guard below.
 */

$title = (string) ($displayData['title'] ?? '');

if ($title === '') {
    return;
}

$tag     = preg_replace('/[^a-z0-9]/i', '', (string) ($displayData['tag'] ?? ''));
$openTag = $tag !== '' ? '<' . $tag . '>' : '';
$endTag  = $tag !== '' ? '</' . $tag . '>' : '';
$escaped = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');

$articleId = (int) ($displayData['article_id'] ?? 0);
$productId = (int) ($displayData['product_id'] ?? 0);

if ($articleId < 1 && $productId > 0) {
    $source = 'com_content';
    $db     = Factory::getContainer()->get(DatabaseInterface::class);
    $query  = $db->getQuery(true)
        ->select($db->quoteName('product_source_id'))
        ->from($db->quoteName('#__j2commerce_products'))
        ->where($db->quoteName('j2commerce_product_id') . ' = :productId')
        ->where($db->quoteName('product_source') . ' = :source')
        ->bind(':productId', $productId, ParameterType::INTEGER)
        ->bind(':source', $source, ParameterType::STRING);

    $db->setQuery($query);
    $articleId = (int) $db->loadResult();
}

if ($articleId < 1) {
    echo $openTag . $escaped . $endTag;
    return;
}

// Repeated per link rather than guarded: every call below is idempotent — the asset manager
// keeps one registration per name, addScriptOptions merges onto the same key, and Text::script
// re-states a string it already holds. Layouts self-register so a third-party caller gets the
// behaviour without touching its own view.
$document = Factory::getApplication()->getDocument();

$document->getWebAssetManager()->registerAndUseScript(
    'com_j2commerce.article-edit-link',
    'media/com_j2commerce/js/administrator/article-edit-link.js',
    [],
    ['defer' => true]
);

$document->addScriptOptions('com_j2commerce.articleLink', [
    'stateUrl'   => 'index.php?option=com_j2commerce&task=ajax.articleCheckedOutState&format=json',
    'checkinUrl' => 'index.php?option=com_j2commerce&task=ajax.checkinArticle&format=json',
]);

Text::script('COM_J2COMMERCE_ARTICLE_CHECKED_OUT_BY');
Text::script('COM_J2COMMERCE_ARTICLE_CHECKED_OUT_LOCKED_ON');
Text::script('COM_J2COMMERCE_ARTICLE_CHECKED_OUT_TAKE_OVER');
Text::script('COM_J2COMMERCE_ARTICLE_CHECKIN_AND_CONTINUE');
Text::script('JERROR_AN_ERROR_HAS_OCCURRED');
Text::script('JCLOSE');

$url = 'index.php?option=com_content&task=article.edit&id=' . $articleId;

$return = (string) ($displayData['return'] ?? '');

if ($return !== '') {
    // urlencode on top of base64: a raw base64 payload can carry "+", which the query
    // string decodes back to a space and Joomla's base64 input filter then strips.
    $url .= '&return=' . urlencode(base64_encode($return));
}

$classes = trim('j2c-article-link ' . (string) ($displayData['class'] ?? ''));

$extra = '';

foreach ((array) ($displayData['attribs'] ?? []) as $name => $value) {
    // Escaping the name is not enough on its own: a space or '=' inside it would end the
    // attribute and start a second one, so the name has to be a name before it is emitted.
    if (!preg_match('/^[a-zA-Z][a-zA-Z0-9-]*$/', (string) $name)) {
        continue;
    }

    $extra .= ' ' . $name . '="' . htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8') . '"';
}

?>
<?php echo $openTag; ?><a href="<?php echo Route::_($url); ?>"
   class="<?php echo htmlspecialchars($classes, ENT_QUOTES, 'UTF-8'); ?>"
   data-j2c-article-id="<?php echo $articleId; ?>"<?php echo $extra; ?>><?php echo $escaped; ?></a><?php echo $endTag; ?>
