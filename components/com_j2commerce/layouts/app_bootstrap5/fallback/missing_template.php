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

use Joomla\CMS\Language\Text;

$filename    = $displayData['filename'] ?? 'unknown';
$subtemplate = $displayData['subtemplate'] ?? 'unknown';
?>
<div class="alert alert-warning j2commerce-missing-template" role="alert">
    <strong><?php echo Text::_('COM_J2COMMERCE'); ?>:</strong>
    <?php echo Text::sprintf(
        'COM_J2COMMERCE_ERR_TEMPLATE_FILE_NOT_FOUND',
        htmlspecialchars($filename, ENT_QUOTES, 'UTF-8'),
        htmlspecialchars($subtemplate, ENT_QUOTES, 'UTF-8')
    ); ?>
</div>
