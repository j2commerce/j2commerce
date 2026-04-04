<?php
/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use J2Commerce\Component\J2commerce\Administrator\Helper\J2CommerceHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

/** @var \J2Commerce\Component\J2commerce\Administrator\View\Shippingtroubles\HtmlView $this */

J2CommerceHelper::strapper()->addCSS();

$wa = $this->getDocument()->getWebAssetManager();
$step = Factory::getApplication()->getInput()->getString('step', '');


$diagnostics = $this->getDiagnostics();

// Helper function to get status badge
function getStatusBadge($status, $message = '') {
    $badgeClass = '';
    $icon = '';

    switch ($status) {
        case 'success':
            $badgeClass = 'text-bg-success';
            $icon = 'fa-check-circle';
            break;
        case 'warning':
            $badgeClass = 'text-bg-warning text-dark';
            $icon = 'fa-exclamation-triangle';
            break;
        case 'error':
            $badgeClass = 'text-bg-danger';
            $icon = 'fa-times-circle';
            break;
        default:
            $badgeClass = 'text-bg-purple';
            $icon = 'fa-question-circle';
    }

    return '<span class="badge ' . $badgeClass . '"><span class="fa-solid ' . $icon . ' me-1" aria-hidden="true"></span>' . Text::_($message) . '</span>';
}


?>
<?php echo $this->navbar;?>

<div class="row">
    <div class="col-md-12">
        <div id="j-main-container" class="j-main-container">

            <div class="card mb-4">
                <div class="card-body">
                    <h2>
                        <span class="fa-solid fa-cog me-2" aria-hidden="true"></span>
                        <?php echo Text::_('COM_J2COMMERCE_SHIPPING_TROUBLESHOOTER_METHODS_TITLE'); ?>
                    </h2>
                    <p class="mb-0">
                        <?php echo Text::_('COM_J2COMMERCE_SHIPPING_TROUBLESHOOTER_METHODS_DESCRIPTION'); ?>
                    </p>
                </div>
            </div>
            <?php if (!empty($diagnostics)): ?>
                <div class="card mb-5 mt-3">
                    <div class="card-body">
                        <nav class="quick-icons" aria-label="Shipping Configuration Dashboard">
                            <div class="row flex-wrap">

                                <div class="quickicon quickicon-single col-12 col-md-6 col-lg-3 mb-3 mb-lg-0 border-0">
                                    <div class="alert alert-<?php echo $diagnostics['geozones']['status'];?> my-0 w-100 border-0">
                                        <div class="quickicon-info">
                                            <div class="quickicon-value display-6 mb-3"><?php echo $diagnostics['geozones']['count'];?></div>
                                        </div>
                                        <div class="quickicon-name d-flex align-items-center">
                                                <span class="j-links-link">
                                                    <div class="text-capitalize"><?php echo Text::_('COM_J2COMMERCE_GEOZONES'); ?></div>
                                                </span>
                                        </div>
                                    </div>
                                </div>

                                <div class="quickicon quickicon-single col-12 col-md-6 col-lg-3 mb-3 mb-lg-0 border-0">
                                    <div class="alert alert-<?php echo $diagnostics['shipping_methods']['status'];?> my-0 w-100 border-0">
                                        <div class="quickicon-info">
                                            <div class="quickicon-value display-6 mb-3"><?php echo $diagnostics['shipping_methods']['count'];?></div>
                                        </div>
                                        <div class="quickicon-name d-flex align-items-center">
                                                <span class="j-links-link">
                                                    <div class="text-capitalize"><?php echo Text::_('COM_J2COMMERCE_SHIPPING_TROUBLESHOOTER_SHIPPING_METHODS'); ?></div>
                                                </span>
                                        </div>
                                    </div>
                                </div>

                                <div class="quickicon quickicon-single col-12 col-md-6 col-lg-3 mb-3 mb-lg-0 border-0">
                                    <div class="alert alert-<?php echo $diagnostics['shipping_rates']['status'];?> my-0 w-100 border-0">
                                        <div class="quickicon-info">
                                            <div class="quickicon-value display-6 mb-3"><?php echo $diagnostics['shipping_rates']['count'];?></div>
                                        </div>
                                        <div class="quickicon-name d-flex align-items-center">
                                                <span class="j-links-link">
                                                    <div class="text-capitalize"><?php echo Text::_('COM_J2COMMERCE_SHIPPING_TROUBLESHOOTER_SHIPPING_RATES'); ?></div>
                                                </span>
                                        </div>
                                    </div>
                                </div>

                            </div>
                        </nav>
                    </div>
                </div>

                <div class="cpanel-modules cpanel mt-3">
                    <div class="card-columns">
                        <div class="module-wrapper">
                            <div class="card">
                                <h2 class="card-header"><?php echo Text::_('COM_J2COMMERCE_SHIPPING_TROUBLESHOOTER_QUICK_CHECK');?></h2>
                                <ul class="list-group list-group-flush fs-6">
                                    <li class="list-group-item d-flex align-items-center">
                                        <span>
                                            <span class="fa-solid fa-globe me-2 fa-fw" aria-hidden="true"></span><?php echo Text::_('COM_J2COMMERCE_SHIPPING_TROUBLESHOOTER_QUICK_CHECK_GEOZONES');?>
                                        </span>
                                        <span class="menu-quicktask">
                                            <?php echo getStatusBadge($diagnostics['geozones']['status'], $diagnostics['geozones']['message']); ?>
                                        </span>
                                    </li>
                                    <li class="list-group-item d-flex align-items-center">
                                        <span>
                                            <span class="fa-solid fa-truck me-2 fa-fw" aria-hidden="true"></span><?php echo Text::_('COM_J2COMMERCE_SHIPPING_TROUBLESHOOTER_QUICK_CHECK_SHIPPINGZONES');?>
                                        </span>
                                        <span class="menu-quicktask">
                                            <?php echo getStatusBadge($diagnostics['shipping_methods']['status'], $diagnostics['shipping_methods']['message']); ?>
                                        </span>
                                    </li>
                                    <li class="list-group-item d-flex align-items-center">
                                        <span>
                                            <span class="fa-solid fa-calculator me-2 fa-fw" aria-hidden="true"></span><?php echo Text::_('COM_J2COMMERCE_SHIPPING_TROUBLESHOOTER_QUICK_CHECK_SHIPPINGRATES');?>
                                        </span>
                                        <span class="menu-quicktask">
                                            <?php echo getStatusBadge($diagnostics['shipping_rates']['status'], $diagnostics['shipping_rates']['message']); ?>
                                        </span>
                                    </li>
                                </ul>
                            </div>
                        </div>
                        <div class="module-wrapper">
                            <div class="card mb-3">
                                <div class="card-header">
                                    <h2>
                                        <?php echo Text::_('COM_J2COMMERCE_SHIPPING_TROUBLESHOOTER_QUICK_ACTIONS'); ?>
                                    </h2>
                                </div>
                                <div class="card-body">
                                    <nav class="quick-icons" aria-label="<?php echo Text::_('COM_J2COMMERCE_SHIPPING_TROUBLESHOOTER_QUICK_ACTIONS'); ?>">
                                        <ul class="nav flex-wrap">
                                            <li class="quickicon quickicon-single">
                                                <a href="<?php echo Route::_('index.php?option=com_j2commerce&view=shippingmethods'); ?>">
                                                    <div class="quickicon-info">
                                                        <div class="quickicon-icon">
                                                            <div class="fa-solid fa-shipping-fast" aria-hidden="true"></div>
                                                        </div>
                                                    </div>
                                                    <div class="quickicon-name d-flex align-items-end"><?php echo Text::_('COM_J2COMMERCE_MANAGE_SHIPPING_METHODS'); ?></div>
                                                </a>
                                            </li>
                                            <li class="quickicon quickicon-single">
                                                <a href="<?php echo Route::_('index.php?option=com_j2commerce&view=geozones'); ?>">
                                                    <div class="quickicon-info">
                                                        <div class="quickicon-icon">
                                                            <div class="fa-solid fa-pie-chart fa-chart-pie" aria-hidden="true"></div>
                                                        </div>
                                                    </div>
                                                    <div class="quickicon-name d-flex align-items-end"><?php echo Text::_('COM_J2COMMERCE_MANAGE_GEOZONES'); ?></div>
                                                </a>
                                            </li>
                                        </ul>
                                    </nav>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
            <?php if (!empty($diagnostics)): ?>
                <?php
                $hasErrors = false;
                $hasWarnings = false;
                foreach ($diagnostics as $diagnostic) {
                    if ($diagnostic['status'] === 'error') $hasErrors = true;
                    if ($diagnostic['status'] === 'warning') $hasWarnings = true;
                }
                ?>

                <?php if ($hasErrors): ?>
                    <div class="alert alert-danger" role="alert">
                        <h5 class="text-danger"><span class="fa-solid fa-exclamation-triangle me-2" aria-hidden="true"></span><?php echo Text::_('COM_J2COMMERCE_SHIPPING_TROUBLESHOOTER_CRITICAL_ISSUES'); ?></h5>
                        <ul class="mb-0">
                            <?php if ($diagnostics['shipping_methods']['status'] === 'error'): ?>
                                <li><?php echo Text::_('COM_J2COMMERCE_SHIPPING_TROUBLESHOOTER_RECOMMENDATION_ADD_METHODS'); ?></li>
                            <?php endif; ?>
                            <?php if ($diagnostics['shipping_rates']['status'] === 'error'): ?>
                                <li><?php echo Text::_('COM_J2COMMERCE_SHIPPING_TROUBLESHOOTER_RECOMMENDATION_CONFIGURE_RATES'); ?></li>
                            <?php endif; ?>
                        </ul>
                    </div>
                <?php elseif ($hasWarnings): ?>
                    <div class="alert alert-warning" role="alert">
                        <h5 class="text-warning"><span class="fa-solid fa-info-circle me-2" aria-hidden="true"></span><?php echo Text::_('COM_J2COMMERCE_SHIPPING_TROUBLESHOOTER_IMPROVEMENTS'); ?></h5>
                        <ul class="mb-0">
                            <?php if ($diagnostics['geozones']['status'] === 'warning'): ?>
                                <li><?php echo Text::_('COM_J2COMMERCE_SHIPPING_TROUBLESHOOTER_RECOMMENDATION_ADD_GEOZONES'); ?></li>
                            <?php endif; ?>
                        </ul>
                    </div>
                <?php else: ?>
                    <div class="alert alert-success" role="alert">
                        <h5 class="text-success"><span class="fa-solid fa-check-circle me-2" aria-hidden="true"></span><?php echo Text::_('COM_J2COMMERCE_SHIPPING_TROUBLESHOOTER_ALL_GOOD'); ?></h5>
                        <p class="mb-0"><?php echo Text::_('COM_J2COMMERCE_SHIPPING_TROUBLESHOOTER_ALL_GOOD_DESCRIPTION'); ?></p>
                    </div>
                <?php endif; ?>
            <?php endif; ?>


            <!-- Navigation -->
            <div class="card mt-4">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <a href="<?php echo Route::_('index.php?option=com_j2commerce&view=shippingtroubles'); ?>"
                           class="btn btn-secondary">
                            <span class="fa fa-arrow-left me-1" aria-hidden="true"></span>
                            <?php echo Text::_('COM_J2COMMERCE_SHIPPING_TROUBLESHOOTER_BACK_TO_START'); ?>
                        </a>
                        <a href="<?php echo Route::_('index.php?option=com_j2commerce&view=shippingtroubles&step=products'); ?>"
                           class="btn btn-primary">
                            <?php echo Text::_('COM_J2COMMERCE_SHIPPING_TROUBLESHOOTER_NEXT_PRODUCTS'); ?>
                            <span class="fa fa-arrow-right ms-1" aria-hidden="true"></span>
                        </a>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>

<?php echo $this->footer ?? ''; ?>
