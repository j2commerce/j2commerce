<?php
/**
 * @package     J2Commerce Library
 * @subpackage  lib_j2commerce
 *
 * @copyright   (C)2024-2025 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Multilanguage;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;

/** @var \Joomla\Component\Content\Administrator\View\Articles\HtmlView $this */

$app = Factory::getApplication();

if ($app->isClient('site')) {
    Session::checkToken('get') or die(Text::_('JINVALID_TOKEN'));
}

// Load the library language
Factory::getApplication()->getLanguage()->load('lib_j2commerce', JPATH_SITE);

/** @var \Joomla\CMS\WebAsset\WebAssetManager $wa */
$wa = $this->getDocument()->getWebAssetManager();

HTMLHelper::_('bootstrap.tooltip', '.hasTooltip');

// @todo: Use of Function and Editor is deprecated and should be removed in 6.0. It stays only for backward compatibility.
$function  = $app->getInput()->getCmd('function', 'jSelectItemMultiCallback');
$editor    = $app->getInput()->getCmd('editor', '');
$listOrder = $this->escape($this->state->get('list.ordering'));
$listDirn  = $this->escape($this->state->get('list.direction'));
$onclick   = $this->escape($function);
$multilang = Multilanguage::isEnabled();

if (!empty($editor)) {
    // This view is used also in com_menus. Load the xtd script only if the editor is set!
    $this->getDocument()->addScriptOptions('xtd-articles', ['editor' => $editor]);
    $onclick = "jSelectItemMultiCallback";
}

Text::script('LIB_J2COMMERCE_ITEM_SELECT');
Text::script('LIB_J2COMMERCE_ITEM_UNSELECT');
Text::script('LIB_J2COMMERCE_ITEM_SELECT_MODAL_SELECTION_DONE_LABEL');

// Register and use the modal multi-select list script from library
$wa->registerAndUseScript(
    'lib_j2commerce.modal-multiselect-list',
    'lib_j2commerce/modal-multiselect-list.min.js'
);
?>
<div class="container-popup">

    <form action="<?php echo Route::_('index.php?option=com_content&view=articles&layout=modal_multiselect&tmpl=component&editor=' . $editor . '&function=' . $function . '&' . Session::getFormToken() . '=1'); ?>" method="post" name="adminForm" id="adminForm">

        <?php echo LayoutHelper::render('joomla.searchtools.default', ['view' => $this]); ?>

        <?php if (empty($this->items)) : ?>
            <div class="alert alert-info">
                <span class="icon-info-circle" aria-hidden="true"></span><span class="visually-hidden"><?php echo Text::_('INFO'); ?></span>
                <?php echo Text::_('JGLOBAL_NO_MATCHING_RESULTS'); ?>
            </div>
        <?php else : ?>
            <!-- Multi-select controls -->
            <div class="row mb-3">
                <div class="col text-end">
                    <button type="button"
                            id="clear-selection-btn"
                            class="btn btn-outline-danger ms-2"
                            style="display: none;"
                            title="<?php echo Text::_('LIB_J2COMMERCE_ARTICLE_SELECT_MODAL_CLEAR_ALL_ARTICLES_LABEL'); ?>">
                        <span class="icon-trash" aria-hidden="true"></i> <?php echo Text::_('LIB_J2COMMERCE_ITEM_SELECT_MODAL_SELECTION_CLEAR_LABEL'); ?>
                    </button>
                    <button type="button"
                            id="done-btn"
                            class="btn btn-success ms-2"
                            data-content-select
                            data-content-type="com_content.article"
                            data-function="<?php echo $this->escape($onclick); ?>"
                            data-button-close="true">
                        <span id="done-btn-text"><?php echo Text::_('LIB_J2COMMERCE_ITEM_SELECT_MODAL_SELECTION_DONE_LABEL'); ?></span>
                        <span class="badge bg-light text-dark ms-1 px-2" id="selected-count-badge" style="display: none;">0</span>
                    </button>
                </div>
            </div>

            <table class="table table-sm">
                <caption class="visually-hidden">
                    <?php echo Text::_('COM_CONTENT_ARTICLES_TABLE_CAPTION'); ?>,
                            <span id="orderedBy"><?php echo Text::_('JGLOBAL_SORTED_BY'); ?> </span>,
                            <span id="filteredBy"><?php echo Text::_('JGLOBAL_FILTERED_BY'); ?></span>
                </caption>
                <thead>
                    <tr>
                        <th scope="col" class="w-1 text-center">
                            <input type="checkbox" class="form-check-input" id="select-all" title="<?php echo Text::_('JGLOBAL_CHECK_ALL'); ?>">
                        </th>
                        <th scope="col" class="w-1 text-center">
                            <?php echo HTMLHelper::_('searchtools.sort', 'JSTATUS', 'a.state', $listDirn, $listOrder); ?>
                        </th>
                        <th scope="col" class="w-1 d-none d-md-table-cell">
                            <?php echo HTMLHelper::_('searchtools.sort', 'JGRID_HEADING_ID', 'a.id', $listDirn, $listOrder); ?>
                        </th>
                        <th scope="col" class="title">
                            <?php echo HTMLHelper::_('searchtools.sort', 'JGLOBAL_TITLE', 'a.title', $listDirn, $listOrder); ?>
                        </th>
                        <th scope="col" class="w-10 d-none d-md-table-cell">
                            <?php echo HTMLHelper::_('searchtools.sort', 'JGRID_HEADING_ACCESS', 'a.access', $listDirn, $listOrder); ?>
                        </th>
                        <?php if ($multilang) : ?>
                            <th scope="col" class="w-15">
                                <?php echo HTMLHelper::_('searchtools.sort', 'JGRID_HEADING_LANGUAGE', 'language', $listDirn, $listOrder); ?>
                            </th>
                        <?php endif; ?>
                        <th scope="col" class="w-10 d-none d-md-table-cell">
                            <?php echo HTMLHelper::_('searchtools.sort', 'JDATE', 'a.created', $listDirn, $listOrder); ?>
                        </th>
                    </tr>
                </thead>
                <tbody>
                <?php
                $states = [
                    -2 => ['icon' => 'icon-trash', 'label' => Text::_('JTRASHED')],
                    0  => ['icon' => 'icon-unpublish', 'label' => Text::_('JUNPUBLISHED')],
                    1  => ['icon' => 'icon-publish', 'label' => Text::_('JPUBLISHED')],
                    2  => ['icon' => 'icon-archive', 'label' => Text::_('JARCHIVED')],
                ];
                ?>
                <?php foreach ($this->items as $i => $item) : ?>
                    <?php
                    $lang = '';
                    if (!empty($item->language) && $multilang) {
                        $tag = \strlen($item->language);
                        if ($tag == 5) {
                            $lang = \substr($item->language, 0, 2);
                        } elseif ($tag == 6) {
                            $lang = \substr($item->language, 0, 3);
                        }
                    }
                    ?>
                    <tr class="row<?php echo $i % 2; ?>">
                        <td class="text-center">
                            <input type="checkbox"
                                   class="item-checkbox form-check-input"
                                   id="item-checkbox-<?php echo $item->id; ?>"
                                   data-id="<?php echo $item->id; ?>"
                                   data-title="<?php echo $this->escape($item->title); ?>">
                        </td>
                        <td class="text-center tbody-icon">
                            <span class="<?php echo $states[$this->escape($item->state)]['icon']; ?>" aria-hidden="true" title="<?php echo $states[$this->escape($item->state)]['label']; ?>"></span>
                        </td>
                        <td class="d-none d-md-table-cell">
                            <?php echo (int) $item->id; ?>
                        </td>
                        <th scope="row">
                            <label for="item-checkbox-<?php echo $item->id; ?>"
                                    class="item-label"
                                    style="cursor: pointer;"
                                    data-bs-toggle="tooltip"
                                    data-bs-placement="top">
                                <?php echo $this->escape($item->title); ?>
                            </label>
                            <div class="small break-word text-muted">
                                <?php if (empty($item->note)) : ?>
                                    <?php echo Text::sprintf('JGLOBAL_LIST_ALIAS', $this->escape($item->alias)); ?>
                                <?php else : ?>
                                    <?php echo Text::sprintf('JGLOBAL_LIST_ALIAS_NOTE', $this->escape($item->alias), $this->escape($item->note)); ?>
                                <?php endif; ?>
                            </div>
                            <div class="small text-muted">
                                <?php echo Text::_('JCATEGORY') . ': ' . $this->escape($item->category_title); ?>
                            </div>
                        </th>
                        <td class="small d-none d-md-table-cell">
                            <?php echo $this->escape($item->access_level); ?>
                        </td>
                        <?php if ($multilang) : ?>
                            <td class="small">
                                <?php echo LayoutHelper::render('joomla.content.language', $item); ?>
                            </td>
                        <?php endif; ?>
                        <td class="small d-none d-md-table-cell">
                            <?php echo HTMLHelper::_('date', $item->created, Text::_('DATE_FORMAT_LC4')); ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>

            <?php // load the pagination. ?>
            <?php echo $this->pagination->getListFooter(); ?>

        <?php endif; ?>

        <input type="hidden" name="task" value="">
        <input type="hidden" name="forcedLanguage" value="<?php echo $app->getInput()->get('forcedLanguage', '', 'CMD'); ?>">
        <?php echo HTMLHelper::_('form.token'); ?>

    </form>
</div>
