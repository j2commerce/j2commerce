<?php
/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use J2Commerce\Component\J2commerce\Administrator\Helper\VersionHelper;
use Joomla\CMS\Language\Text;

$version = VersionHelper::getVersion();
$year    = date('Y');


?>
<div class="j2commerce-footer mt-5 mb-2 text-center">
    <a href="https://www.j2commerce.com" target="_blank" class="">J2Commerce, LLC</a><span class="mx-2">|</span>Copyright &copy; 2024 - <?php echo $year; ?><span class="mx-2">|</span><?php echo Text::_('JVERSION');?>: <strong><?php echo $version; ?></strong><span class="mx-2">|</span><span class="j2commerce-social-share"><a class="btn btn-link px-1" href="https://www.facebook.com/j2commerce" target="_blank"><span class="fa-brands fab fa-facebook-f fa-fw" aria-hidden="true"></span><span class="sr-only"><?php echo Text::_('COM_J2COMMERCE_FACEBOOK'); ?></span></a><a class="btn btn-link px-1" href="https://github.com/j2commerce" target="_blank"><span class="fa-brands fab fa-github fa-fw" aria-hidden="true"></span><span class="sr-only"><?php echo Text::_('COM_J2COMMERCE_GITHUB'); ?></span></a></span>
</div>
