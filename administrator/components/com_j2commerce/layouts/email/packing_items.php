<?php

/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

\defined('_JEXEC') or die;

// The [PACKING_ITEMS] email tag. Override: templates/<default site template>/html/layouts/com_j2commerce/email/packing_items.php
// Row values are already escaped and formatted; only $row['item'] (the order item row) is raw.
// Use $language, not Text::_(): the email renders in the order's language, not the request's.
// Keep styles inline: email clients drop stylesheets.
// Keep the th headers and the image alt. This file runs on every tag pass, subject included: do not query per row.

$language = $displayData['language'];
?>
<table style="width:100%; border-collapse:collapse;">
<thead>
<tr style="background:#f5f5f5;">
<th scope="col" style="padding:8px; text-align:left; border:1px solid #ddd;"><?php echo $language->_('COM_J2COMMERCE_EMAIL_PRODUCT'); ?></th>
<th scope="col" style="padding:8px; text-align:left; border:1px solid #ddd;"><?php echo $language->_('COM_J2COMMERCE_EMAIL_SKU'); ?></th>
<th scope="col" style="padding:8px; text-align:center; border:1px solid #ddd;"><?php echo $language->_('COM_J2COMMERCE_EMAIL_QUANTITY'); ?></th>
<th scope="col" style="padding:8px; text-align:center; border:1px solid #ddd;"><?php echo $language->_('COM_J2COMMERCE_FIELD_WEIGHT'); ?></th>
</tr>
</thead>
<tbody>
<?php foreach ($displayData['rows'] as $row) : ?>
<tr>
<td style="padding:8px; border:1px solid #ddd;"><?php echo $row['name']; ?><?php if (!empty($row['options'])) : ?><br><small style="color:#666;"><?php echo $row['options']; ?></small><?php endif; ?></td>
<td style="padding:8px; border:1px solid #ddd;"><?php echo $row['sku']; ?></td>
<td style="padding:8px; text-align:center; border:1px solid #ddd;"><?php echo $row['quantity']; ?></td>
<td style="padding:8px; text-align:center; border:1px solid #ddd;"><?php echo $row['weight']; ?></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
