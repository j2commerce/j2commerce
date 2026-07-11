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
 * Compiles a J2CFlow graph JSON payload into an adjacency list. Skeleton for future
 * graph-authoritative consumers that need to walk/execute a stored flow (e.g. a scheduler-driven
 * marketing-journey runtime) — see PRD §4.1/§9. Not consumed by app_aftersalespecial.
 */
final class FlowCompiler
{
    /**
     * @param array{nodes?: array<int, array<string, mixed>>, edges?: array<int, array<string, mixed>>} $graph
     *
     * @return array<string, array<int, string>> node id => outgoing target node ids
     */
    public static function toAdjacencyList(array $graph): array
    {
        $adjacency = [];

        foreach ($graph['nodes'] ?? [] as $node) {
            if (\is_array($node) && isset($node['id'])) {
                $adjacency[(string) $node['id']] ??= [];
            }
        }

        foreach ($graph['edges'] ?? [] as $edge) {
            if (!\is_array($edge) || !isset($edge['from'], $edge['to'])) {
                continue;
            }

            $from = (string) $edge['from'];
            $adjacency[$from] ??= [];
            $adjacency[$from][] = (string) $edge['to'];
        }

        return $adjacency;
    }
}
