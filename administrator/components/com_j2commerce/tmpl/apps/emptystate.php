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

use J2Commerce\Component\J2commerce\Administrator\Helper\J2CommerceHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;

/** @var \J2Commerce\Component\J2commerce\Administrator\View\Apps\HtmlView $this */

J2CommerceHelper::strapper()->addCSS();
?>

<?php echo $this->navbar; ?>

<div class="container">
    <div class="row">
        <div class="col-md-12">
            <div class="text-center">
                <div class="mb-3">
                    <span class="fa-8x mb-4 fa fa-puzzle-piece text-muted" aria-hidden="true"></span>
                </div>
                <h2 class="mb-3">
                    <?php echo Text::_('COM_J2COMMERCE_APPS_EMPTYSTATE_TITLE'); ?>
                </h2>
                <p class="lead">
                    <?php echo Text::_('COM_J2COMMERCE_APPS_EMPTYSTATE_CONTENT'); ?>
                </p>
                <p>
                    <?php echo Text::_('COM_J2COMMERCE_APPS_EMPTYSTATE_INSTRUCTIONS'); ?>
                </p>
                <div class="mt-4">
                    <a href="https://www.j2commerce.com/extensions/apps" target="_blank" class="btn btn-primary">
                        <?php echo Text::_('COM_J2COMMERCE_APPS_BROWSE_EXTENSIONS'); ?>
                        <span class="icon-download ms-1" aria-hidden="true"></span>
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php echo $this->footer ?? ''; ?>
