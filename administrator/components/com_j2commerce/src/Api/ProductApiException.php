<?php

/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace J2Commerce\Component\J2commerce\Administrator\Api;

\defined('_JEXEC') or die;

use Joomla\CMS\MVC\Controller\Exception\Save;

/** Joomla's JSON:API error is `{title, code}`: `<pointer>: <detail>` lines as the title, the HTTP status as code. */
final class ProductApiException extends Save
{
    /** @param list<array{pointer: string, detail: string}> $errors */
    public function __construct(int $status, public readonly array $errors)
    {
        parent::__construct(
            implode("\n", array_map(static fn (array $e): string => $e['pointer'] . ': ' . $e['detail'], $errors)),
            $status
        );
    }

    public static function single(int $status, string $pointer, string $detail): self
    {
        return new self($status, [['pointer' => $pointer, 'detail' => $detail]]);
    }
}
