<?php

declare(strict_types=1);

/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace J2Commerce\Component\J2commerce\Administrator\SetupGuide;

\defined('_JEXEC') or die;

interface SetupCheckInterface
{
    public function getId(): string;
    public function getGroup(): string;
    public function getGroupOrder(): int;
    public function getLabel(): string;
    public function getDescription(): string;
    public function check(): SetupCheckResult;
    public function getDetailView(): string;
    public function getActions(): array;
    public function isDismissible(): bool;
    public function getGuidedTourUid(): ?string;
}
