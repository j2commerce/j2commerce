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

use J2Commerce\Component\J2commerce\Administrator\Helper\CategoryHelper;
use J2Commerce\Component\J2commerce\Administrator\Helper\J2CommerceHelper;
use Joomla\CMS\Form\Form;
use Joomla\CMS\Form\FormHelper;
use Joomla\CMS\Language\Text;

// Register the component field namespace so J2Commerce custom field types
// (e.g. Taxprofile) resolve in the app forms loaded via Form::getInstance().
FormHelper::addFieldPrefix('J2Commerce\\Component\\J2commerce\\Administrator\\Field');

$this->item = $displayData['category'] ?? $displayData['item'] ?? null;
$this->form_prefix = $displayData['form_prefix'] ?? 'jform[attribs][j2commerce]';

// Gather all J2Commerce category app data from plugins
$pluginApps = J2CommerceHelper::plugin()->eventWithAppData(
    'AfterDisplayCategoryForm',
    [$this, $this->item, $this->form_prefix]
);

// Get core J2Commerce category settings (returns array of accordion items)
$coreApps = CategoryHelper::getCoreCategoryFormData($this->item ?? new \stdClass(), $this->form_prefix);

// Merge core apps with plugin apps
$apps = array_merge($coreApps, $pluginApps);

// Sort apps by ordering
usort($apps, function ($a, $b) {
    return ($a['ordering'] ?? 100) <=> ($b['ordering'] ?? 100);
});

if (empty($apps)): ?>
    <div class="alert alert-info">
        <?php echo Text::_('COM_J2COMMERCE_CATEGORY_TAB_NO_APPS'); ?>
    </div>
<?php return; endif; ?>

<div class="accordion" id="j2commerce-category-accordion">
<?php foreach ($apps as $index => $app):
    $element = $app['element'] ?? '';
    $nameKey = $app['name'] ?? 'PLG_J2COMMERCE_' . strtoupper($element);
    $description = $app['description'] ?? ($nameKey . '_DESCRIPTION');
    $imagePath = $app['image'] ?? J2CommerceHelper::getAppImagePath($element);
    $ordering = $app['ordering'] ?? 100;
    $collapseId = 'category-app-collapse-' . htmlspecialchars($element, ENT_QUOTES, 'UTF-8');
    $headingId = 'category-heading-' . htmlspecialchars($element, ENT_QUOTES, 'UTF-8');

    // First accordion item is open by default for better UX
    $isFirst = $index === 0;
    $buttonClass = $isFirst ? 'accordion-button' : 'accordion-button collapsed';
    $collapseClass = $isFirst ? 'accordion-collapse collapse show' : 'accordion-collapse collapse';
    $ariaExpanded = $isFirst ? 'true' : 'false';
?>
    <div class="accordion-item">
        <h2 class="accordion-header" id="<?php echo $headingId; ?>">
            <button class="<?php echo $buttonClass; ?>"
                    type="button"
                    data-bs-toggle="collapse"
                    data-bs-target="#<?php echo $collapseId; ?>"
                    aria-expanded="<?php echo $ariaExpanded; ?>"
                    aria-controls="<?php echo $collapseId; ?>">
                <div class="d-block d-lg-flex align-items-center">
                    <div class="flex-shrink-0">
                        <span class="d-none d-lg-inline-block d-md-block">
                            <img src="<?php echo htmlspecialchars($imagePath, ENT_QUOTES, 'UTF-8'); ?>"
                                 alt="<?php echo htmlspecialchars(Text::_($nameKey), ENT_QUOTES, 'UTF-8'); ?>"
                                 class="me-2 img-fluid j2commerce-app-image"
                            >
                        </span>
                    </div>
                    <div class="flex-grow-1 ms-lg-3 mt-0 mt-lg-0">
                        <div>
                            <?php echo htmlspecialchars(Text::_($nameKey), ENT_QUOTES, 'UTF-8'); ?>
                        </div>
                        <div class="small d-none d-md-block text-muted">
                            <?php echo htmlspecialchars(Text::_($description), ENT_QUOTES, 'UTF-8'); ?>
                        </div>
                    </div>
                </div>
            </button>
        </h2>
        <div id="<?php echo $collapseId; ?>"
             class="<?php echo $collapseClass; ?>"
             aria-labelledby="<?php echo $headingId; ?>"
             data-bs-parent="#j2commerce-category-accordion">
            <div class="accordion-body">
                <?php
                if (!empty($app['form_xml']) && file_exists($app['form_xml'])):
                    $form = Form::getInstance(
                        'j2commerce.category.' . $element,
                        $app['form_xml'],
                        ['control' => $this->form_prefix . '[params]']
                    );

                    if ($form):
                        if (!empty($app['data'])):
                            $form->bind($app['data']);
                        endif;
                        echo $form->renderFieldset('basic');
                    endif;
                elseif (!empty($app['html'])):
                    echo $app['html'];
                endif;
                ?>
            </div>
        </div>
    </div>
<?php endforeach; ?>
</div>