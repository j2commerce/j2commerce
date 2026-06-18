<?php

/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace J2Commerce\Component\J2commerce\Site\View\Categories;

\defined('_JEXEC') or die;

use J2Commerce\Component\J2commerce\Site\Helper\RouteHelper;
use Joomla\CMS\MVC\View\GenericDataException;
use Joomla\CMS\MVC\View\JsonView as BaseJsonView;
use Joomla\CMS\Router\Route;

class JsonView extends BaseJsonView
{
    public function display($tpl = null): void
    {
        $model = $this->getModel();

        if (\count($errors = $model->getErrors())) {
            throw new GenericDataException(implode("\n", $errors), 500);
        }

        $items  = $model->getItems();
        $mapped = [];

        foreach ($items as $item) {
            $catid = (int) $item->id;

            $mapped[] = [
                'id'            => $catid,
                'title'         => strip_tags((string) ($item->title ?? '')),
                'alias'         => $item->alias ?? null,
                'url'           => Route::_(RouteHelper::getCategoryRoute($catid), true),
                'description'   => strip_tags((string) ($item->description ?? '')),
                'parent_id'     => isset($item->parent_id) ? (int) $item->parent_id : null,
                'product_count' => isset($item->product_count) ? (int) $item->product_count : 0,
            ];
        }

        $this->_output = [
            'categories' => $mapped,
            'count'      => \count($mapped),
        ];

        // The site application overwrites the JSON document buffer with the
        // component's echoed output, so emit the payload via echo rather than
        // relying on the parent setBuffer() path.
        echo json_encode(
            $this->_output,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE
        );
    }
}
