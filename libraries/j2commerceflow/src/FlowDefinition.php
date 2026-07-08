<?php

/**
 * @package     J2Commerce
 * @subpackage  lib_j2commerceflow
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace J2Commerce\Library\Flow;

\defined('_JEXEC') or die;

/**
 * Validates a flow graph JSON payload against J2CFlow's node/edge shape. Skeleton for future
 * graph-authoritative consumers (flows stored as graph JSON rather than derived from domain
 * rows) — see PRD §4.1/§4.3. Not consumed by app_aftersalespecial, whose graph is derived.
 */
final class FlowDefinition
{
    private const VALID_NODE_KEYS = ['id', 'type', 'icon', 'title', 'body', 'badges', 'x', 'y'];
    private const VALID_EDGE_KEYS = ['from', 'to', 'label', 'style'];

    /** @param array{nodes?: array<int, array<string, mixed>>, edges?: array<int, array<string, mixed>>} $graph */
    public static function validate(array $graph): bool
    {
        return empty(self::errors($graph));
    }

    /**
     * @param array{nodes?: array<int, array<string, mixed>>, edges?: array<int, array<string, mixed>>} $graph
     *
     * @return string[]
     */
    public static function errors(array $graph): array
    {
        $errors = [];
        $nodes  = $graph['nodes'] ?? [];
        $edges  = $graph['edges'] ?? [];

        if (!\is_array($nodes)) {
            return ['nodes must be an array'];
        }

        $nodeIds = [];

        foreach ($nodes as $node) {
            if (!\is_array($node) || !isset($node['id'], $node['type'])) {
                $errors[] = 'each node requires id and type';

                continue;
            }

            $nodeIds[] = (string) $node['id'];
        }

        if (!\is_array($edges)) {
            $errors[] = 'edges must be an array';

            return $errors;
        }

        foreach ($edges as $edge) {
            if (!\is_array($edge) || !isset($edge['from'], $edge['to'])) {
                $errors[] = 'each edge requires from and to';

                continue;
            }

            if (!\in_array((string) $edge['from'], $nodeIds, true) || !\in_array((string) $edge['to'], $nodeIds, true)) {
                $errors[] = \sprintf('edge %s->%s references an unknown node', $edge['from'], $edge['to']);
            }
        }

        return $errors;
    }
}
