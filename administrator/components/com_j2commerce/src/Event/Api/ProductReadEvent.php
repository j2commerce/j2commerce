<?php

/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace J2Commerce\Component\J2commerce\Administrator\Event\Api;

\defined('_JEXEC') or die;

use J2Commerce\Component\J2commerce\Administrator\Event\PluginEvent;

/** Read-only `type_data` blocks for GET /products/:id from plugins that do not own the type, keyed by element. */
class ProductReadEvent extends PluginEvent
{
    public const NAME = 'onJ2CommerceApiProductRead';

    public function __construct(object $product, string $type)
    {
        parent::__construct(self::NAME, ['product' => $product, 'type' => $type, 'result' => []]);
    }

    public function getProduct(): object
    {
        return $this->arguments['product'];
    }

    public function getType(): string
    {
        return $this->arguments['type'];
    }

    public function addBlock(string $element, array $data): void
    {
        $this->arguments['result'][$element] = $data;
    }

    /** @return array<string, array> */
    public function getBlocks(): array
    {
        return \is_array($this->arguments['result'] ?? null) ? $this->arguments['result'] : [];
    }
}
