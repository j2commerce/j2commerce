<?php
/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

defined('_JEXEC') or die;

extract($displayData);

$innerHtml = $innerHtml ?? '';

if ($innerHtml === '') {
    return;
}
?>
<div class="j2commerce-price-sku-container d-flex flex-wrap align-items-center justify-content-between gap-1 mb-4"><?php echo $innerHtml; ?></div>
