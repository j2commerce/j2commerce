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

// The [ITEMS] email tag. Override: templates/<default site template>/html/layouts/com_j2commerce/email/items.php
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
<th scope="col" style="padding:8px; text-align:right; border:1px solid #ddd;"><?php echo $language->_('COM_J2COMMERCE_EMAIL_QUANTITY'); ?></th>
<th scope="col" style="padding:8px; text-align:right; border:1px solid #ddd;"><?php echo $language->_('COM_J2COMMERCE_EMAIL_PRICE'); ?></th>
<th scope="col" style="padding:8px; text-align:right; border:1px solid #ddd;"><?php echo $language->_('COM_J2COMMERCE_EMAIL_TOTAL'); ?></th>
</tr>
</thead>
<tbody>
<?php foreach ($displayData['rows'] as $row) : ?>
<tr>
<td style="padding:8px; border:1px solid #ddd;"><?php if ($row['imageUrl'] !== '') : ?><img src="<?php echo $row['imageUrl']; ?>" alt="<?php echo $row['name']; ?>" width="50" height="50" style="border-radius:4px; object-fit:cover; margin-right:8px; vertical-align:middle;" /><?php endif; ?><?php echo $row['name']; ?><?php if (!empty($row['sku'])) : ?><br><small><?php echo $language->_('COM_J2COMMERCE_EMAIL_SKU'); ?>: <?php echo $row['sku']; ?></small><?php endif; ?><?php if ($row['options'] !== '') : ?><br><small><?php echo $row['options']; ?></small><?php endif; ?></td>
<td style="padding:8px; text-align:right; border:1px solid #ddd;"><?php echo $row['quantity']; ?></td>
<td style="padding:8px; text-align:right; border:1px solid #ddd;"><?php echo $row['price']; ?></td>
<td style="padding:8px; text-align:right; border:1px solid #ddd;"><?php echo $row['total']; ?></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
