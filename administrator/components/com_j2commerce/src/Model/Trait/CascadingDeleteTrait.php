<?php

/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace J2Commerce\Component\J2commerce\Administrator\Model\Trait;

\defined('_JEXEC') or die;

/**
 * Shared front half of a model-level delete cascade.
 *
 * A cascade runs before parent::delete(), and AdminModel::delete() is where canDelete() and the
 * per-row load actually happen — so cascading over the raw request keys would destroy the children
 * of records the caller is not allowed to delete, silently and without removing the parent. Every
 * cascading model narrows the keys through deletableKeys() first.
 */
trait CascadingDeleteTrait
{
    /**
     * @param   array|int|string  $pks  Primary keys as submitted. The API routes hand over a single
     *                                  key rather than an array, the way AdminModel::delete() accepts.
     *
     * @return  int[]  Only the keys that load and pass the same canDelete() test AdminModel applies.
     */
    protected function deletableKeys(array|int|string $pks): array
    {
        $allowed = [];

        foreach ((array) $pks as $pk) {
            $pk    = (int) $pk;
            $table = $this->getTable();

            if ($pk > 0 && $table->load($pk) && $this->canDelete($table)) {
                $allowed[] = $pk;
            }
        }

        return $allowed;
    }
}
