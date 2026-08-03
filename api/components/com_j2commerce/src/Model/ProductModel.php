<?php

/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace J2Commerce\Component\J2commerce\Api\Model;

\defined('_JEXEC') or die;

use J2Commerce\Component\J2commerce\Administrator\Model\ProductModel as AdminProductModel;
use Joomla\Component\Fields\Administrator\Helper\FieldsHelper;

/** @since  6.0.16 */
class ProductModel extends AdminProductModel
{
    public function getItem($pk = null): object|bool
    {
        $item = parent::getItem($pk);

        if (!$item || empty($item->j2commerce_product_id)) {
            return $item;
        }

        $article = $item->source ?? null;

        if ($article !== null && isset($article->id)) {
            $user    = $this->getCurrentUser();
            $visible = (int) ($article->state ?? 0) === 1
                && \in_array((int) ($article->access ?? 0), $user->getAuthorisedViewLevels(), true);

            if ($visible) {
                $safe              = new \stdClass();
                $safe->id          = $article->id ?? null;
                $safe->title       = $article->title ?? null;
                $safe->alias       = $article->alias ?? null;
                $safe->introtext   = $article->introtext ?? null;
                $safe->fulltext    = $article->fulltext ?? null;
                $safe->state       = $article->state ?? null;
                $safe->catid       = $article->catid ?? null;
                $safe->language    = $article->language ?? null;
                $safe->created     = $article->created ?? null;
                $safe->modified    = $article->modified ?? null;
                $safe->publish_up  = $article->publish_up ?? null;
                $safe->publish_down = $article->publish_down ?? null;

                $item->article  = $safe;
                // $f->value is prepared/rendered HTML (prepareValue=true); rawvalue dropped intentionally.
                $item->jcfields = array_map(
                    static fn ($f) => (object) [
                        'id'    => $f->id,
                        'name'  => $f->name,
                        'label' => $f->label,
                        'value' => $f->value,
                        'type'  => $f->type,
                    ],
                    FieldsHelper::getFields('com_content.article', $article, true)
                );
            } else {
                $item->article  = null;
                $item->jcfields = [];
            }
        } else {
            $item->article  = null;
            $item->jcfields = [];
        }

        return $item;
    }
}
