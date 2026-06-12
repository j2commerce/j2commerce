<?php

/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace J2Commerce\Component\J2commerce\Api\Controller;

\defined('_JEXEC') or die;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\MVC\Controller\ApiController;
use Tobscure\JsonApi\AbstractSerializer;
use Tobscure\JsonApi\Resource;

class ConfigController extends ApiController
{
    protected $contentType = 'config';

    protected $default_view = 'config';

    public function displayList()
    {
        $params = ComponentHelper::getParams('com_j2commerce');
        $data = (object) array_merge(['id' => 1], $params->toArray());

        $serializer = new class extends AbstractSerializer {
            protected $type = 'config';

            public function getId($model): string
            {
                return '1';
            }

            public function getAttributes($model, ?array $fields = null): array
            {
                $attrs = (array) $model;
                unset($attrs['id']);

                return $attrs;
            }
        };

        $this->app->getDocument()->setData(new Resource($data, $serializer));

        return $this;
    }
}
