<?php
/**
 * @package     J2Commerce
 * @subpackage  Plugin.J2Commerce.AppFlexivariable
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

/** @var \stdClass $displayData */

$vars = $displayData;
$params = $vars->params ?? null;

HTMLHelper::_('bootstrap.tab');
?>

<div class="j2commerce-configuration">
    <form action="<?php echo htmlspecialchars($vars->action, ENT_QUOTES, 'UTF-8'); ?>" method="post" name="adminForm" id="adminForm" class="form-horizontal form-validate">

        <input type="hidden" name="option" value="com_j2commerce">
        <input type="hidden" name="view" value="apps">
        <input type="hidden" name="app_id" value="<?php echo (int) $vars->id; ?>">
        <input type="hidden" name="appTask" id="appTask" value="">
        <input type="hidden" name="task" id="task" value="view">
        <?php echo HTMLHelper::_('form.token'); ?>

        <div class="card">
            <div class="card-header">
                <h3><?php echo Text::_('PLG_J2COMMERCE_APP_FLEXIVARIABLE'); ?></h3>
            </div>
            <div class="card-body">
                <div class="alert alert-info">
                    <h4 class="alert-heading"><?php echo Text::_('PLG_J2COMMERCE_APP_FLEXIVARIABLE_ABOUT'); ?></h4>
                    <p><?php echo Text::_('PLG_J2COMMERCE_APP_FLEXIVARIABLE_DESC'); ?></p>
                </div>

                <div class="row">
                    <div class="col-md-12">
                        <h4><?php echo Text::_('PLG_J2COMMERCE_APP_FLEXIVARIABLE_FEATURES'); ?></h4>
                        <ul class="list-group">
                            <li class="list-group-item">
                                <span class="icon-checkmark text-success" aria-hidden="true"></span>
                                <?php echo Text::_('PLG_J2COMMERCE_APP_FLEXIVARIABLE_FEATURE_1'); ?>
                            </li>
                            <li class="list-group-item">
                                <span class="icon-checkmark text-success" aria-hidden="true"></span>
                                <?php echo Text::_('PLG_J2COMMERCE_APP_FLEXIVARIABLE_FEATURE_2'); ?>
                            </li>
                            <li class="list-group-item">
                                <span class="icon-checkmark text-success" aria-hidden="true"></span>
                                <?php echo Text::_('PLG_J2COMMERCE_APP_FLEXIVARIABLE_FEATURE_3'); ?>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    Joomla.submitbutton = function(pressbutton) {
        if (pressbutton === 'save' || pressbutton === 'apply') {
            document.getElementById('task').value = 'view';
            document.getElementById('appTask').value = pressbutton;
            Joomla.submitform('view');
            return;
        }
        Joomla.submitform(pressbutton);
    };
});
</script>
