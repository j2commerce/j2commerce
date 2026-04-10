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
use J2Commerce\Component\J2commerce\Administrator\Helper\J2htmlHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\Router\Route;

J2CommerceHelper::strapper()->addCSS();

/** @var \J2Commerce\Component\J2commerce\Administrator\View\Shippingtroubles\HtmlView $this */

$wa = $this->getDocument()->getWebAssetManager();
$wa->useScript('multiselect')
    ->useScript('table.columns')
    ->useScript('bootstrap.collapse');

$products = $this->getProducts();
$pagination = $this->getPagination();
$productsStats = $this->getProductsStats();

// Get list ordering and direction from state
$listOrder = $this->escape($this->state->get('list.ordering', 'p.product_source_id'));
$listDirn = $this->escape($this->state->get('list.direction', 'ASC'));

// Helper function to get status badge
function getProductStatusBadge($status) {
    $badgeClass = '';
    $icon = '';
    $text = '';

    switch ($status) {
        case 'success':
            $badgeClass = 'text-bg-success';
            $icon = 'fa-check-circle';
            $text = 'COM_J2COMMERCE_SHIPPING_TROUBLESHOOTER_STATUS_OK';
            break;
        case 'warning':
            $badgeClass = 'text-bg-warning';
            $icon = 'fa-exclamation-triangle';
            $text = 'COM_J2COMMERCE_SHIPPING_TROUBLESHOOTER_STATUS_WARNING';
            break;
        case 'error':
            $badgeClass = 'text-bg-danger';
            $icon = 'fa-times-circle';
            $text = 'COM_J2COMMERCE_SHIPPING_TROUBLESHOOTER_STATUS_ERROR';
            break;
        default:
            $badgeClass = 'text-bg-purple';
            $icon = 'fa-question-circle';
            $text = 'COM_J2COMMERCE_SHIPPING_TROUBLESHOOTER_STATUS_UNKNOWN';
    }

    return '<span class="' . J2htmlHelper::badgeClass('badge ' . $badgeClass) . '"><span class="fa-solid ' . $icon . ' me-1" aria-hidden="true"></span>' . Text::_($text) . '</span>';
}
?>
<?php echo $this->navbar;?>

<form action="<?php echo Route::_('index.php?option=com_j2commerce&view=shippingtroubles&step=products'); ?>" method="post" name="adminForm" id="adminForm">
    <div class="row">
        <div class="col-md-12">
            <div id="j-main-container" class="j-main-container">
            <!-- Header -->
            <div class="card mb-4">
                <div class="card-body">
                    <h2>
                        <span class="fa-solid fa-cog me-2" aria-hidden="true"></span>
                        <?php echo Text::_('COM_J2COMMERCE_SHIPPING_TROUBLESHOOTER_PRODUCTS_TITLE'); ?>
                    </h2>
                    <p class="mb-0">
                        <?php echo Text::_('COM_J2COMMERCE_SHIPPING_TROUBLESHOOTER_PRODUCTS_DESCRIPTION'); ?>
                    </p>
                </div>
            </div>

            <!-- Summary Stats -->
            <?php if ($pagination && $pagination->total > 0): ?>
                <?php
                // Use optimized statistics from the model
                $successCount = $productsStats['success'] ?? 0;
                $warningCount = $productsStats['warning'] ?? 0;
                $errorCount = $productsStats['error'] ?? 0;
                $totalProducts = $productsStats['total'] ?? $pagination->total;
                ?>
                <div class="card mb-5 mt-3">
                    <div class="card-body">
                        <nav class="quick-icons" aria-label="<?php echo Text::_('COM_J2COMMERCE_SHIPPING_TROUBLESHOOTER_WELCOME_TITLE'); ?>">
                            <div class="row flex-wrap">
                                <div class="quickicon quickicon-single col-12 col-md-6 col-lg-3 mb-3 mb-lg-0 border-0">
                                    <div class="alert alert-success my-0 w-100 border-0">
                                        <div class="quickicon-info">
                                            <div class="quickicon-value display-6 mb-3"><?php echo $successCount; ?></div>
                                        </div>
                                        <div class="quickicon-name d-flex align-items-center">
                                            <span class="j-links-link">
                                                <div class="text-capitalize"><?php echo Text::_('COM_J2COMMERCE_SHIPPING_TROUBLESHOOTER_PRODUCTS_OK'); ?></div>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                                <div class="quickicon quickicon-single col-12 col-md-6 col-lg-3 mb-3 mb-lg-0 border-0">
                                    <div class="alert alert-warning my-0 w-100 border-0">
                                        <div class="quickicon-info">
                                            <div class="quickicon-value display-6 mb-3"><?php echo $warningCount; ?></div>
                                        </div>
                                        <div class="quickicon-name d-flex align-items-center">
                                            <span class="j-links-link">
                                                <div class="text-capitalize"><?php echo Text::_('COM_J2COMMERCE_SHIPPING_TROUBLESHOOTER_PRODUCTS_WARNINGS'); ?></div>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                                <div class="quickicon quickicon-single col-12 col-md-6 col-lg-3 mb-3 mb-lg-0 border-0">
                                    <div class="alert alert-danger my-0 w-100 border-0">
                                        <div class="quickicon-info">
                                            <div class="quickicon-value display-6 mb-3"><?php echo $errorCount; ?></div>
                                        </div>
                                        <div class="quickicon-name d-flex align-items-center">
                                            <span class="j-links-link">
                                                <div class="text-capitalize"><?php echo Text::_('COM_J2COMMERCE_SHIPPING_TROUBLESHOOTER_PRODUCTS_ERRORS'); ?></div>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                                <div class="quickicon quickicon-single col-12 col-md-6 col-lg-3 mb-3 mb-lg-0 border-0">
                                    <div class="alert alert-info my-0 w-100 border-0">
                                        <div class="quickicon-info">
                                            <div class="quickicon-value display-6 mb-3"><?php echo $totalProducts; ?></div>
                                        </div>
                                        <div class="quickicon-name d-flex align-items-center">
                                            <span class="j-links-link">
                                                <div class="text-capitalize"><?php echo Text::_('COM_J2COMMERCE_SHIPPING_TROUBLESHOOTER_PRODUCTS_TOTAL'); ?></div>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </nav>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Search Tools -->
            <?php echo LayoutHelper::render('joomla.searchtools.default', ['view' => $this]); ?>

            <?php if (empty($products)): ?>
                <?php if (!empty($this->activeFilters)): ?>
                    <div class="alert alert-info" role="alert">
                        <span class="fa-solid fa-info-circle me-2" aria-hidden="true"></span>
                        <?php echo Text::_('JGLOBAL_NO_MATCHING_RESULTS'); ?>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info" role="alert">
                        <span class="fa-solid fa-info-circle me-2" aria-hidden="true"></span>
                        <?php echo Text::_('COM_J2COMMERCE_SHIPPING_TROUBLESHOOTER_NO_PRODUCTS'); ?>
                    </div>
                    <a href="<?php echo Route::_('index.php?option=com_j2commerce&view=products'); ?>" class="btn btn-primary">
                        <?php echo Text::_('COM_J2COMMERCE_ADD_PRODUCTS'); ?>
                    </a>
                <?php endif; ?>
            <?php else: ?>
                <div class="my-3">
                    <div class="row">
                        <div class="col-md-4">
                            <?php echo getProductStatusBadge('success'); ?>
                            <span class="ms-2"><?php echo Text::_('COM_J2COMMERCE_SHIPPING_TROUBLESHOOTER_LEGEND_SUCCESS'); ?></span>
                        </div>
                        <div class="col-md-4">
                            <?php echo getProductStatusBadge('warning'); ?>
                            <span class="ms-2"><?php echo Text::_('COM_J2COMMERCE_SHIPPING_TROUBLESHOOTER_LEGEND_WARNING'); ?></span>
                        </div>
                        <div class="col-md-4">
                            <?php echo getProductStatusBadge('error'); ?>
                            <span class="ms-2"><?php echo Text::_('COM_J2COMMERCE_SHIPPING_TROUBLESHOOTER_LEGEND_ERROR'); ?></span>
                        </div>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table" id="productList">
                        <thead>
                            <tr>
                                <th scope="col" class="w-1 text-center">
                                    <?php echo HTMLHelper::_('searchtools.sort', 'JGRID_HEADING_ID', 'p.j2commerce_product_id', $listDirn, $listOrder); ?>
                                </th>
                                <th scope="col">
                                    <?php echo HTMLHelper::_('searchtools.sort', 'COM_J2COMMERCE_PRODUCT_NAME', 'p.product_source_id', $listDirn, $listOrder); ?>
                                </th>
                                <th scope="col" class="d-none d-md-table-cell">
                                    <?php echo HTMLHelper::_('searchtools.sort', 'COM_J2COMMERCE_PRODUCT_SKU', 'v.sku', $listDirn, $listOrder); ?>
                                </th>
                                <th scope="col" class="text-center">
                                    <?php echo HTMLHelper::_('searchtools.sort', 'COM_J2COMMERCE_SHIPPING_ENABLED', 'v.shipping', $listDirn, $listOrder); ?>
                                </th>
                                <th scope="col" class="text-center d-none d-lg-table-cell">
                                    <?php echo HTMLHelper::_('searchtools.sort', 'COM_J2COMMERCE_WEIGHT', 'v.weight', $listDirn, $listOrder); ?>
                                </th>
                                <th scope="col" class="text-center d-none d-lg-table-cell"><?php echo Text::_('COM_J2COMMERCE_DIMENSIONS'); ?></th>
                                <th scope="col" class="text-center"><?php echo Text::_('COM_J2COMMERCE_STATUS'); ?></th>
                                <th scope="col" class="text-center"><?php echo Text::_('COM_J2COMMERCE_ACTIONS'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($products as $product):
                                $contentEditLink = Route::_('index.php?option=com_content&task=article.edit&id=' . (int) $product->product_source_id) . '#attrib-j2commerce';
                                ?>
                                <tr>
                                    <td class="text-center">
                                        <?php echo (int) $product->j2commerce_product_id; ?>
                                    </td>
                                    <td>
                                        <strong><?php echo $this->escape($product->product_name); ?></strong>
                                        <?php if (!empty($product->shipping_issues) || !empty($product->shipping_warnings)): ?>
                                            <div class="small text-muted mt-1">
                                                <?php foreach ($product->shipping_issues as $issue): ?>
                                                    <div class="text-danger">
                                                        <span class="fa-solid fa-times-circle me-1" aria-hidden="true"></span>
                                                        <?php echo Text::_($issue); ?>
                                                    </div>
                                                <?php endforeach; ?>
                                                <?php foreach ($product->shipping_warnings as $warning): ?>
                                                    <div class="text-warning">
                                                        <span class="fa-solid fa-exclamation-triangle me-1" aria-hidden="true"></span>
                                                        <?php echo Text::_($warning); ?>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="d-none d-md-table-cell">
                                        <code><?php echo $this->escape($product->product_sku ?: 'N/A'); ?></code>
                                    </td>
                                    <td class="text-center">
                                        <?php if ($product->shipping): ?>
                                            <span class="tbody-icon"><span class="icon-publish" aria-hidden="true"></span></span>
                                        <?php else: ?>
                                            <span class="tbody-icon text-danger"><span class="icon-unpublish text-danger border-danger" aria-hidden="true"></span></span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center d-none d-lg-table-cell">
                                        <?php if (!empty($product->weight) && $product->weight > 0): ?>
                                            <span class="<?php echo J2htmlHelper::badgeClass('badge text-bg-success'); ?>"><?php echo number_format($product->weight, 2); ?></span>
                                        <?php else: ?>
                                            <span class="<?php echo J2htmlHelper::badgeClass('badge text-bg-purple'); ?>">N/A</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center d-none d-lg-table-cell">
                                        <?php
                                        $hasDimensions = !empty($product->length) && !empty($product->width) && !empty($product->height);
                                        ?>
                                        <?php if ($hasDimensions): ?>
                                            <span class="<?php echo J2htmlHelper::badgeClass('badge text-bg-success'); ?>">
                                                <?php echo $product->length . '×' . $product->width . '×' . $product->height; ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="<?php echo J2htmlHelper::badgeClass('badge text-bg-purple'); ?>">N/A</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <?php echo getProductStatusBadge($product->shipping_status); ?>
                                    </td>
                                    <td class="text-center">
                                        <a href="<?php echo $contentEditLink; ?>"
                                           class="btn btn-sm btn-link"
                                           title="<?php echo Text::_('JACTION_EDIT'); ?>"
                                           onclick="sessionStorage.setItem('j2ctab', 'shippingTab')">
                                            <span class="fa-solid fa-edit" aria-hidden="true"></span>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>


            <?php endif; ?>

            <!-- Full Pagination Footer -->
            <?php if ($pagination && $pagination->getPagesCounter()): ?>
                <div class="mt-4">
                    <?php echo $pagination->getListFooter(); ?>
                </div>
            <?php endif; ?>

            <!-- Navigation -->
            <div class="card mt-4">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <a href="<?php echo Route::_('index.php?option=com_j2commerce&view=shippingtroubles&step=shipping'); ?>"
                           class="btn btn-secondary">
                            <span class="fa fa-arrow-left me-1" aria-hidden="true"></span>
                            <?php echo Text::_('COM_J2COMMERCE_SHIPPING_TROUBLESHOOTER_BACK_TO_METHODS'); ?>
                        </a>
                        <a href="<?php echo Route::_('index.php?option=com_j2commerce&view=shippingtroubles'); ?>"
                           class="btn btn-primary">
                            <span class="fa fa-home me-1" aria-hidden="true"></span>
                            <?php echo Text::_('COM_J2COMMERCE_SHIPPING_TROUBLESHOOTER_BACK_TO_START'); ?>
                        </a>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <!-- Hidden form fields for state management -->
    <input type="hidden" name="task" value="">
    <input type="hidden" name="boxchecked" value="0">
    <?php echo HTMLHelper::_('form.token'); ?>
</form>

<?php echo $this->footer ?? ''; ?>
