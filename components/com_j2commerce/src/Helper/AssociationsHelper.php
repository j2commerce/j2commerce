<?php

/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace J2Commerce\Component\J2commerce\Site\Helper;

\defined('_JEXEC') or die;

use Joomla\CMS\Association\AssociationExtensionHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Associations;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;

/**
 * Exposes the product/category view's language association to mod_languages (and any
 * other caller of the core Associations framework), so switching language from a
 * product page, or from a category or subcategory listing, resolves to that item's
 * translation rather than to whatever menu item happens to be active.
 *
 * Neither products nor J2Commerce's catid-keyed listings carry association rows of
 * their own:
 * - A product's underlying Joomla article (#__content, linked via
 *   #__j2commerce_products) carries the standard `com_content.item` association that
 *   editors already set up from the Articles manager.
 * - A category's association is the standard `com_categories.item` one, set up from
 *   the Categories manager — the same table/context com_content's own category
 *   association uses, since J2Commerce categories ARE com_content categories.
 *
 * Without this class, `com_j2commerce` does not implement AssociationServiceInterface,
 * so mod_languages falls back to the active menu item's own association — typically an
 * ancestor category's menu, losing however many path segments sit below it — rather
 * than the specific item being viewed.
 *
 * Deliberately does not populate $itemTypes: that would register types in the
 * com_associations admin manager, which needs getType()/getAssociations()/getItem()
 * to list and edit associations, none of which this class implements. Only the
 * frontend getAssociationsForItem() path (used by mod_languages) is needed.
 *
 * @since  6.6.6
 */
class AssociationsHelper extends AssociationExtensionHelper
{
    /**
     * @var     string
     * @since   6.6.6
     */
    protected $extension = 'com_j2commerce';

    /**
     * @var     array
     * @since   6.6.6
     */
    protected $itemTypes = [];

    /**
     * @var     boolean
     * @since   6.6.6
     */
    protected $associationsSupport = true;

    /**
     * Method to get the associations for the current product/category item.
     *
     * Called by mod_languages with no arguments; the current view/id are read from
     * the request, mirroring com_content's own AssociationHelper::getAssociations().
     *
     * @param   integer  $id    Id of the item
     * @param   string   $view  Name of the view
     *
     * @return  array  Map of language tag => unrouted URL
     *
     * @since   6.6.6
     */
    public function getAssociationsForItem($id = 0, $view = null)
    {
        $app   = Factory::getApplication();
        $input = $app->getInput();

        $view = $view ?? $input->getCmd('view');

        if (!Associations::isEnabled()) {
            return [];
        }

        if ($view === 'product') {
            return $this->getProductAssociations((int) $this->normalizeId($id ?: $input->getInt('id')));
        }

        // The nestable "products" listing (view=products&catid=X): id arrives as catid.
        if ($view === 'products') {
            return $this->getCategoryAssociations(
                (int) $this->normalizeId($id ?: $input->getInt('catid')),
                'products'
            );
        }

        // The "categories" listing (view=categories&id=X): shows the children of category id.
        if ($view === 'categories') {
            return $this->getCategoryAssociations(
                (int) $this->normalizeId($id ?: $input->getInt('id')),
                'categories'
            );
        }

        return [];
    }

    /** Strips a trailing ":alias" from an "id:alias" style identifier. */
    private function normalizeId($id): string
    {
        $id = (string) $id;

        if (strpos($id, ':') !== false) {
            [$id] = explode(':', $id, 2);
        }

        return $id;
    }

    /** Per-language product routes for the product backed by $productId, keyed by language tag. */
    private function getProductAssociations(int $productId): array
    {
        if ($productId < 1) {
            return [];
        }

        $db = Factory::getContainer()->get(DatabaseInterface::class);

        $articleId = $this->getProductArticleId($db, $productId);

        if (!$articleId) {
            return [];
        }

        $associations = Associations::getAssociations('com_content', '#__content', 'com_content.item', $articleId);

        $routes = [];

        foreach ($associations as $tag => $item) {
            $assocArticleId = (int) explode(':', (string) $item->id)[0];

            if ($assocArticleId < 1 || $assocArticleId === $articleId) {
                continue;
            }

            $product = $this->getProductByArticleId($db, $assocArticleId);

            if (!$product) {
                continue;
            }

            $routes[$tag] = RouteHelper::getProductRoute(
                $product['id'],
                $product['alias'],
                $product['catid'],
                $tag
            );
        }

        return $routes;
    }

    /**
     * Per-language routes for the category $catid, keyed by language tag. $view selects
     * which route each associated category builds: 'products' lists the products inside
     * it, 'categories' lists its own children — matching the view this request came in on.
     */
    private function getCategoryAssociations(int $catid, string $view): array
    {
        if ($catid < 2) {
            return [];
        }

        $db = Factory::getContainer()->get(DatabaseInterface::class);

        $associations = Associations::getAssociations('com_content', '#__categories', 'com_categories.item', $catid, 'id', 'alias', '');

        $routes = [];

        foreach ($associations as $tag => $item) {
            $assocCatId = (int) explode(':', (string) $item->id)[0];

            if ($assocCatId < 1 || $assocCatId === $catid || !$this->isCategoryAccessible($db, $assocCatId)) {
                continue;
            }

            $routes[$tag] = $view === 'categories'
                ? RouteHelper::getCategoriesRoute($assocCatId, $tag)
                : RouteHelper::getProductsRoute($assocCatId, $tag);
        }

        return $routes;
    }

    /** Published, and inside the current visitor's view levels — the same test core's own CategoriesHelper::getAssociations() applies. */
    private function isCategoryAccessible(DatabaseInterface $db, int $catid): bool
    {
        $query = $db->getQuery(true)
            ->select($db->quoteName('published'))
            ->from($db->quoteName('#__categories'))
            ->where($db->quoteName('id') . ' = :catid')
            ->whereIn($db->quoteName('access'), Factory::getApplication()->getIdentity()->getAuthorisedViewLevels())
            ->bind(':catid', $catid, ParameterType::INTEGER);

        $db->setQuery($query);

        return (int) $db->loadResult() === 1;
    }

    /** The content article id backing a j2commerce product, or null if not found. */
    private function getProductArticleId(DatabaseInterface $db, int $productId): ?int
    {
        $query = $db->getQuery(true)
            ->select($db->quoteName('c.id'))
            ->from($db->quoteName('#__content', 'c'))
            ->join(
                'INNER',
                $db->quoteName('#__j2commerce_products', 'p'),
                $db->quoteName('p.product_source_id') . ' = ' . $db->quoteName('c.id')
            )
            ->where($db->quoteName('p.product_source') . ' = ' . $db->quote('com_content'))
            ->where($db->quoteName('p.j2commerce_product_id') . ' = :id')
            ->bind(':id', $productId, ParameterType::INTEGER);

        $db->setQuery($query);

        return (int) $db->loadResult() ?: null;
    }

    /** The j2commerce product (id, alias, catid) whose article is $articleId, or null if none. */
    private function getProductByArticleId(DatabaseInterface $db, int $articleId): ?array
    {
        $query = $db->getQuery(true)
            ->select([
                $db->quoteName('p.j2commerce_product_id', 'id'),
                $db->quoteName('c.alias'),
                $db->quoteName('c.catid'),
            ])
            ->from($db->quoteName('#__j2commerce_products', 'p'))
            ->join('INNER', $db->quoteName('#__content', 'c'), $db->quoteName('c.id') . ' = ' . $db->quoteName('p.product_source_id'))
            ->where($db->quoteName('p.product_source') . ' = ' . $db->quote('com_content'))
            ->where($db->quoteName('p.product_source_id') . ' = :articleId')
            ->bind(':articleId', $articleId, ParameterType::INTEGER);

        $db->setQuery($query);
        $row = $db->loadAssoc();

        if (!$row || empty($row['id'])) {
            return null;
        }

        return [
            'id'    => (int) $row['id'],
            'alias' => $row['alias'] ?: null,
            'catid' => $row['catid'] ? (int) $row['catid'] : null,
        ];
    }
}
