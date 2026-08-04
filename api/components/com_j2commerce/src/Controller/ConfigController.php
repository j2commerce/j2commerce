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

use Joomla\CMS\Access\Exception\NotAllowed;
use Joomla\CMS\Component\ComponentHelper;
use Tobscure\JsonApi\AbstractSerializer;
use Tobscure\JsonApi\Resource;

class ConfigController extends J2CommerceApiController
{
    protected $contentType = 'config';

    protected $default_view = 'config';

    public function displayList()
    {
        $this->assertCanReadOptions();

        $params = ComponentHelper::getParams('com_j2commerce');
        $data   = (object) array_merge(['id' => 1], $params->toArray());

        $serializer = new class () extends AbstractSerializer {
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

    /**
     * This returns the component params verbatim — including queue_key, the shared secret the
     * public cron endpoint compares with hash_equals(), and downloadid. Every one of those is
     * a declared config.xml field, so the caller must hold what com_config demands to open
     * Options and read the same values there: core.admin or core.options.
     */
    private function assertCanReadOptions(): void
    {
        $user = $this->app->getIdentity();

        if (
            !$user
            || $user->guest
            || (!$user->authorise('core.admin', 'com_j2commerce') && !$user->authorise('core.options', 'com_j2commerce'))
        ) {
            throw new NotAllowed('JLIB_APPLICATION_ERROR_ACCESS_FORBIDDEN', 403);
        }
    }
}
