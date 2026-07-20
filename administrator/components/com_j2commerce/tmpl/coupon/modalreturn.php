<?php

/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;

/** @var \J2Commerce\Component\J2commerce\Administrator\View\Coupon\HtmlView $this */

$data     = ['contentType' => 'com_j2commerce.coupon'];
$couponId = $this->item ? (int) $this->item->j2commerce_coupon_id : 0;

// Only report a real, saved coupon back to the picker — cancelling a brand-new
// coupon must not push a phantom "#0" selection into the host field.
if ($couponId > 0) {
    $couponName = $this->item->coupon_name !== '' ? $this->item->coupon_name : Text::_('COM_J2COMMERCE_COUPON') . ' #' . $couponId;
    $title      = !empty($this->item->coupon_code) ? $couponName . ' (' . $this->item->coupon_code . ')' : $couponName;

    $data['id']    = $couponId;
    $data['title'] = $title;
}

/** @var \Joomla\CMS\WebAsset\WebAssetManager $wa */
$wa = $this->getDocument()->getWebAssetManager();
$wa->useScript('modal-content-select');

// Posts the saved coupon back to the parent field as it loads.
$this->getDocument()->addScriptOptions('content-select-on-load', $data, false);
?>

<div class="px-4 py-5 my-5 text-center">
    <span class="fa-8x mb-4 icon-check" aria-hidden="true"></span>
    <h1 class="display-5 fw-bold"><?php echo $this->escape($data['title'] ?? ''); ?></h1>
</div>
