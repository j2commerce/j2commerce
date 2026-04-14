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

/** @var \Joomla\Component\Users\Administrator\View\Users\HtmlView $this */

$app = Factory::getApplication();

if ($app->isClient('site')) {
    Session::checkToken('get') or die(Text::_('JINVALID_TOKEN'));
}

// Load the library language
Factory::getApplication()->getLanguage()->load('lib_j2commerce', JPATH_SITE);

/** @var \Joomla\CMS\WebAsset\WebAssetManager $wa */
$wa = $this->getDocument()->getWebAssetManager();
$wa->useScript('modal-content-select'); // TODO replace?

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
    $this->getDocument()->addScriptOptions('xtd-users', ['editor' => $editor]);
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

    <form action="<?php echo Route::_('index.php?option=com_users&view=users&layout=modal_multiselect&tmpl=component&editor=' . $editor . '&function=' . $function . '&' . Session::getFormToken() . '=1'); ?>" method="post" name="adminForm" id="adminForm">

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
                            title="<?php echo Text::_('LIB_J2COMMERCE_USER_SELECT_MODAL_CLEAR_ALL_USERS_LABEL'); ?>">
                        <span class="icon-trash" aria-hidden="true"></i> <?php echo Text::_('LIB_J2COMMERCE_ITEM_SELECT_MODAL_SELECTION_CLEAR_LABEL'); ?>
                    </button>
                    <button type="button"
                            id="done-btn"
                            class="btn btn-success ms-2"
                            data-content-select
                            data-content-type="com_users.user"
                            data-function="<?php echo $this->escape($onclick); ?>"
                            data-button-close="true">
                        <span id="done-btn-text"><?php echo Text::_('LIB_J2COMMERCE_ITEM_SELECT_MODAL_SELECTION_DONE_LABEL'); ?></span>
                        <span class="badge bg-light text-dark ms-1 px-2" id="selected-count-badge" style="display: none;">0</span>
                    </button>
                </div>
            </div>

            <table class="table table-sm">
                <caption class="visually-hidden">
                    <?php echo Text::_('COM_USERS_USERS_TABLE_CAPTION'); ?>,
                        <span id="orderedBy"><?php echo Text::_('JGLOBAL_SORTED_BY'); ?> </span>,
                        <span id="filteredBy"><?php echo Text::_('JGLOBAL_FILTERED_BY'); ?></span>
                </caption>
                <thead>
                    <tr>
                        <th scope="col" class="w-1 text-center">
                            <input type="checkbox" class="form-check-input" id="select-all" title="<?php echo Text::_('JGLOBAL_CHECK_ALL'); ?>">
                        </th>
                        <th scope="col" class="w-1 text-center">
                            <?php echo HTMLHelper::_('searchtools.sort', 'COM_USERS_HEADING_ENABLED', 'a.block', $listDirn, $listOrder); ?>
                        </th>
                        <th scope="col" class="w-1">
                            <?php echo HTMLHelper::_('searchtools.sort', 'JGRID_HEADING_ID', 'a.id', $listDirn, $listOrder); ?>
                        </th>
                        <th scope="col" class="title">
                            <?php echo HTMLHelper::_('searchtools.sort', 'COM_USERS_HEADING_NAME', 'a.name', $listDirn, $listOrder); ?>
                        </th>
                        <th scope="col" class="w-15 d-none d-md-table-cell">
                            <?php echo HTMLHelper::_('searchtools.sort', 'JGLOBAL_USERNAME', 'a.username', $listDirn, $listOrder); ?>
                        </th>
                        <th scope="col" class="w-25 d-none d-md-table-cell">
                            <?php echo HTMLHelper::_('searchtools.sort', 'JGLOBAL_EMAIL', 'a.email', $listDirn, $listOrder); ?>
                        </th>
                        <th scope="col" class="w-1 text-center d-none d-lg-table-cell">
                            <?php echo HTMLHelper::_('searchtools.sort', 'COM_USERS_HEADING_ACTIVATED', 'a.activation', $listDirn, $listOrder); ?>
                        </th>
                        <th scope="col" class="w-15 d-none d-lg-table-cell">
                            <?php echo Text::_('COM_USERS_HEADING_GROUPS'); ?>
                        </th>
                    </tr>
                </thead>
                <tbody>
                <?php
                $blockStates = [
                    0  => ['icon' => 'icon-unpublish', 'label' => Text::_('COM_USERS_USER_FIELD_BLOCK')],
                    1  => ['icon' => 'icon-publish', 'label' => Text::_('JENABLED')],
                ];
                $activatedStates = [
                    0  => ['icon' => 'icon-unpublish', 'label' => Text::_('COM_USERS_UNACTIVATED')],
                    1  => ['icon' => 'icon-publish', 'label' => Text::_('COM_USERS_ACTIVATED')],
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

                    $userName = !empty($item->name) ? $item->name : 'User #' . $item->id;
                    ?>
                    <tr class="row<?php echo $i % 2; ?>">
                        <td class="text-center">
                            <input type="checkbox"
                                   class="item-checkbox form-check-input"
                                   id="item-checkbox-<?php echo $item->id; ?>"
                                   data-id="<?php echo $item->id; ?>"
                                   data-title="<?php echo $this->escape($userName); ?>"
                                   data-username="<?php echo $this->escape($item->username); ?>"
                                   data-email="<?php echo $this->escape($item->email); ?>">
                        </td>
                        <td class="text-center tbody-icon">
                            <span class="<?php echo $blockStates[$this->escape($item->block ? 0 : 1)]['icon']; ?>" aria-hidden="true" title="<?php echo $blockStates[$this->escape($item->block ? 0 : 1)]['label']; ?>"></span>
                        </td>
                        <td>
                            <?php echo (int) $item->id; ?>
                        </td>
                        <th scope="row" class="nowrap has-context">
                            <div class="d-block d-lg-flex">
                                <div class="flex-grow-1">
                                    <div>
                                        <label for="item-checkbox-<?php echo $item->id; ?>"
                                               class="item-label"
                                               style="cursor: pointer;"
                                               data-bs-toggle="tooltip"
                                               data-bs-placement="top">
                                            <?php echo $this->escape($userName); ?>
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </th>
                        <td class="d-none d-md-table-cell">
                            <span><?php echo $this->escape($item->username); ?></span>
                        </td>
                        <td class="d-none d-md-table-cell">
                            <?php echo $this->escape($item->email); ?>
                        </td>
                        <td class="text-center d-none d-lg-table-cell tbody-icon">
                            <span class="<?php echo $activatedStates[(empty($item->activation) ? 1 : 0)]['icon']; ?>" aria-hidden="true" title="<?php echo $activatedStates[(empty($item->activation) ? 1 : 0)]['label']; ?>"></span>
                        </td>
                        <td class="small d-none d-lg-table-cell">
                            <?php if (!empty($item->group_names)) : ?>
                                <?php echo nl2br($this->escape($item->group_names), false); ?>
                            <?php else : ?>
                                <span class="text-muted">-</span>
                            <?php endif; ?>
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
