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

use J2Commerce\Component\J2commerce\Administrator\Field\ProductTypeField;
use J2Commerce\Component\J2commerce\Administrator\Helper\CurrencyHelper;
use Joomla\CMS\Button\PublishedButton;
use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;

/** @var \J2Commerce\Component\J2commerce\Administrator\View\Products\HtmlView $this */

$wa = $this->getDocument()->getWebAssetManager();
$wa->useScript('table.columns')
    ->useScript('multiselect');

$listOrder = $this->escape($this->state->get('list.ordering'));
$listDirn  = $this->escape($this->state->get('list.direction'));

// Helper function to extract clean image path from Joomla image string
$getImagePath = function(?string $imagePath): string {
    if (empty($imagePath)) {
        return '';
    }
    // Strip the Joomla image adapter suffix (everything after #)
    $cleanPath = explode('#', $imagePath)[0];
    return $cleanPath;
};

// Current user and permissions for the article state changes and checkin
$user = Factory::getApplication()->getIdentity();
$userId = $user->id;
$canChangeState = $user->authorise('core.edit.state', 'com_content');

?>
<?php echo $this->navbar; ?>

<form action="<?php echo Route::_('index.php?option=com_j2commerce&view=products'); ?>" method="post" name="adminForm" id="adminForm">
    <div class="row">
        <div class="col-md-12">
            <div id="j-main-container" class="j-main-container">
                <?php echo LayoutHelper::render('joomla.searchtools.default', ['view' => $this]); ?>

                <?php if (empty($this->items)) : ?>
                    <div class="alert alert-info">
                        <span class="icon-info-circle" aria-hidden="true"></span><span class="visually-hidden"><?php echo Text::_('INFO'); ?></span>
                        <?php echo Text::_('JGLOBAL_NO_MATCHING_RESULTS'); ?>
                    </div>
                <?php else : ?>
                    <table class="table itemList align-middle" id="productsList">
                        <caption class="visually-hidden">
                            <?php echo Text::_('COM_J2COMMERCE_PRODUCTS'); ?>,
                            <span id="orderedBy"><?php echo Text::_('JGLOBAL_SORTED_BY'); ?></span>,
                            <span id="filteredBy"><?php echo Text::_('JGLOBAL_FILTERED_BY'); ?></span>
                        </caption>
                        <thead>
                            <tr>
                                <td class="w-1 text-center">
                                    <?php echo HTMLHelper::_('grid.checkall'); ?>
                                </td>
                                <th scope="col" class="w-1 text-center">
                                    <?php echo HTMLHelper::_('searchtools.sort', 'JSTATUS', 'c.state', $listDirn, $listOrder); ?>
                                </th>
                                <th scope="col" class="w-5 text-center d-none d-md-table-cell">
                                    <?php echo HTMLHelper::_('searchtools.sort', 'COM_J2COMMERCE_HEADING_PRODUCT_ID', 'a.j2commerce_product_id', $listDirn, $listOrder); ?>
                                </th>
                                <th scope="col">
                                    <?php echo HTMLHelper::_('searchtools.sort', 'COM_J2COMMERCE_HEADING_PRODUCT_NAME', 'c.title', $listDirn, $listOrder); ?>
                                </th>
                                <th scope="col" class="w-10 d-none d-md-table-cell">
                                    <?php echo HTMLHelper::_('searchtools.sort', 'COM_J2COMMERCE_HEADING_SKU', 'v.sku', $listDirn, $listOrder); ?>
                                </th>
                                <th scope="col" class="w-10 d-none d-md-table-cell">
                                    <?php echo HTMLHelper::_('searchtools.sort', 'COM_J2COMMERCE_HEADING_PRODUCT_TYPE', 'a.product_type', $listDirn, $listOrder); ?>
                                </th>
                                <th scope="col" class="w-10 d-none d-md-table-cell">
                                    <?php echo HTMLHelper::_('searchtools.sort', 'COM_J2COMMERCE_HEADING_PRICE', 'v.price', $listDirn, $listOrder); ?>
                                </th>
                                <th scope="col" class="w-5 text-center d-none d-md-table-cell">
                                    <?php echo Text::_('COM_J2COMMERCE_HEADING_SHIPPING'); ?>
                                </th>
                                <th scope="col" class="w-10 d-none d-md-table-cell">
                                    <?php echo Text::_('COM_J2COMMERCE_HEADING_SOURCE'); ?>
                                </th>
                                <th scope="col" class="w-5 d-none d-md-table-cell text-center">
                                    <?php echo Text::_('COM_J2COMMERCE_HEADING_SOURCE_ID'); ?>
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php
                        $returnUrl = base64_encode('index.php?option=com_j2commerce&view=products');
                        foreach ($this->items as $i => $item) :
                            $baseUrl = rtrim(Uri::root(), '/');
                            $contentEditLink = Route::_('index.php?option=com_content&task=article.edit&id=' . (int) $item->product_source_id . '&return=' . $returnUrl);
                            $thumbImage = $getImagePath($item->thumb_image);
                            $hasImage = !empty($thumbImage);
                            $productTypeLabel = ProductTypeField::getProductTypes()[$item->product_type ?? 'simple'] ?? ucfirst($item->product_type ?? 'simple');
                            $articleStateText = ($item->article_state == 1) ? Text::_('JPUBLISHED') : Text::_('JUNPUBLISHED');
                            $articleStateClass = ($item->article_state == 1) ? 'text-success' : 'text-danger';
                            $taxProfileText = !empty($item->taxprofile_name) ? $this->escape($item->taxprofile_name) : Text::_('COM_J2COMMERCE_NOT_TAXABLE');

                            // Check if user can check in this article
                            $canCheckin = $user->authorise('core.manage', 'com_checkin')
                                || $item->checked_out == $userId
                                || empty($item->checked_out);
                        ?>
                            <tr class="row<?php echo $i % 2; ?>">
                                <td class="text-center">
                                    <?php echo HTMLHelper::_('grid.id', $i, $item->j2commerce_product_id, false, 'cid', 'cb', $item->product_name ?? Text::_('COM_J2COMMERCE_NO_PRODUCT_NAME')); ?>
                                </td>
                                <td class="text-center">
                                    <?php
                                    // Render the article status toggle button
                                    $options = [
                                        'task_prefix' => 'products.',
                                        'disabled'    => !$canChangeState,
                                        'id'          => 'state-' . $item->j2commerce_product_id,
                                    ];

                                    echo (new PublishedButton())->render((int) $item->article_state, $i, $options, (string) $item->article_state);
                                    ?>
                                </td>
                                <td class="text-center d-none d-md-table-cell">
                                    <?php echo (int) $item->j2commerce_product_id; ?>
                                </td>
                                <th scope="row">
                                    <div class="d-flex align-items-start">
                                        <?php if ($hasImage) : ?>
                                            <div class="me-3 flex-shrink-0">
                                                <img src="<?php echo Uri::root() . $thumbImage; ?>"
                                                     alt="<?php echo $this->escape($item->product_name); ?>"
                                                     class="img-fluid"
                                                     style="width: 100px; height: 100px; object-fit: cover;">
                                            </div>
                                        <?php endif; ?>
                                        <div class="flex-grow-1">
                                            <?php if (!empty($item->checked_out)) : ?>
                                                <?php echo HTMLHelper::_('jgrid.checkedout', $i, $item->editor, $item->checked_out_time, 'products.', $canCheckin); ?>
                                            <?php endif; ?>
                                            <a href="<?php echo $contentEditLink; ?>" class="fw-semibold">
                                                <?php echo $this->escape($item->product_name ?: Text::_('COM_J2COMMERCE_NO_PRODUCT_NAME')); ?>
                                            </a>
                                            <div class="small">
                                                <div><?php echo Text::_('COM_J2COMMERCE_VISIBLE_IN_STOREFRONT'); ?>: <strong><?php echo $item->visibility ? Text::_('JYES') : Text::_('JNO'); ?></strong></div>
                                                <div><?php echo Text::_('COM_J2COMMERCE_ARTICLE'); ?>: <strong class="<?php echo $articleStateClass; ?>"><?php echo $articleStateText; ?></strong></div>
                                                <div><?php echo Text::_('COM_J2COMMERCE_TAX_PROFILE'); ?>: <strong><?php echo $taxProfileText; ?></strong></div>
                                            </div>
                                        </div>
                                    </div>
                                </th>
                                <td class="d-none d-md-table-cell">
                                    <?php echo $this->escape($item->sku ?: '-'); ?>
                                </td>
                                <td class="d-none d-md-table-cell">
                                    <?php echo $this->escape($productTypeLabel); ?>
                                </td>
                                <td class="d-none d-md-table-cell">
                                    <?php echo CurrencyHelper::format((float) ($item->price ?? 0)); ?>
                                </td>
                                <td class="text-center d-none d-md-table-cell">
                                    <?php if ($item->shipping) : ?>
                                        <span class="badge text-bg-success"><?php echo Text::_('COM_J2COMMERCE_ENABLED'); ?></span>
                                    <?php else : ?>
                                        <span class="badge text-bg-danger"><?php echo Text::_('COM_J2COMMERCE_DISABLED'); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="d-none d-md-table-cell">
                                    <?php echo $this->escape($item->product_source ?: '-'); ?>
                                </td>
                                <td class="text-center d-none d-md-table-cell">
                                    <?php echo (int) $item->product_source_id; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>

                    <?php echo $this->pagination->getListFooter(); ?>
                <?php endif; ?>

                <input type="hidden" name="task" value="">
                <input type="hidden" name="boxchecked" value="0">
                <?php echo HTMLHelper::_('form.token'); ?>
            </div>
        </div>
    </div>
</form>

<?php echo $this->footer ?? ''; ?>
