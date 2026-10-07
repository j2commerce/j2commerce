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
<div class="j2commerce-price-sku-container uk-flex uk-flex-wrap uk-flex-middle uk-flex-between uk-margin-bottom" style="gap: .25rem"><?php echo $innerHtml; ?></div>
