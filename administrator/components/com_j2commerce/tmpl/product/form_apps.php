<?php
/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use J2Commerce\Component\J2commerce\Administrator\Helper\J2CommerceHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Form\Form;
use Joomla\CMS\Form\FormFactoryInterface;
use Joomla\CMS\Language\Text;

$item = $displayData['product'];
$formPrefix = $displayData['form_prefix'] ?? '';

$apps = J2CommerceHelper::plugin()->eventWithAppData(
    'AfterDisplayProductForm',
    [$this, $item, $formPrefix]
);

if (empty($apps)): ?>
    <div class="alert alert-info">
        <?php echo Text::_('COM_J2COMMERCE_APP_TAB_NO_APPS'); ?>
    </div>
<?php return; endif; ?>

<div class="accordion" id="j2commerce-apps-accordion">
<?php foreach ($apps as $index => $app):
    $element = $app['element'] ?? '';
    $nameKey = $app['name'] ?? 'PLG_J2COMMERCE_' . strtoupper($element);
    $description = $app['description'] ?? ($nameKey . '_DESC');
    $imagePath = J2CommerceHelper::getAppImagePath($element);
    $collapseId = 'app-collapse-' . htmlspecialchars($element, ENT_QUOTES, 'UTF-8');
    $headingId = 'heading-' . htmlspecialchars($element, ENT_QUOTES, 'UTF-8');
?>
    <div class="accordion-item">
        <h2 class="accordion-header" id="<?php echo $headingId; ?>">
            <button class="accordion-button collapsed box-shadow-none" type="button" data-bs-toggle="collapse" data-bs-target="#<?php echo $collapseId; ?>" aria-expanded="false" aria-controls="<?php echo $collapseId; ?>">
                <div class="d-block d-lg-flex align-items-center">
                    <div class="flex-shrink-0">
                        <span class="d-none d-lg-inline-block d-md-block">
                            <img src="<?php echo htmlspecialchars($imagePath, ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars(Text::_($nameKey), ENT_QUOTES, 'UTF-8'); ?>" class="me-2 img-fluid j2commerce-app-image">
                        </span>
                    </div>
                    <div class="flex-grow-1 ms-lg-3 mt-0 mt-lg-0">
                        <div>
                            <?php echo htmlspecialchars(Text::_($nameKey), ENT_QUOTES, 'UTF-8'); ?>
                        </div>
                        <div class="small d-none d-md-block text-muted"><?php echo htmlspecialchars(Text::_($description), ENT_QUOTES, 'UTF-8'); ?></div>
                    </div>
                </div>
            </button>
        </h2>
        <div id="<?php echo $collapseId; ?>" class="accordion-collapse collapse" aria-labelledby="<?php echo $headingId; ?>" data-bs-parent="#j2commerce-apps-accordion">
            <div class="accordion-body">
                <?php
                // Render XML form fields if provided
                if (!empty($app['form_xml']) && file_exists($app['form_xml'])):
                    $formPrefix = $app['form_prefix'] ?? $formPrefix;
                    $factory = Factory::getContainer()->get(FormFactoryInterface::class);

                    $form = $factory->createForm(
                        'j2commerce.app.' . $element,
                        [
                            'control' => $formPrefix . '[params]',
                            'load_data' => true
                        ]
                    );

                    $xml = $app['form_xml'] ?? null;

                    if ($xml instanceof \SimpleXMLElement) {
                        $ok = $form->load($xml);
                    } elseif (\is_string($xml) && \is_file($xml)) {
                        $ok = $form->loadFile($xml, false);
                    } elseif (\is_string($xml) && \trim($xml) !== '') {
                        $ok = $form->load($xml);
                    } else {
                        throw new \UnexpectedValueException('J2Commerce app form_xml is empty or invalid (expected XML string, file path, or SimpleXMLElement).');
                    }
                    if (!$ok) {
                        throw new \RuntimeException('Failed to load form XML for ' . $element . '. XML is invalid or could not be parsed.');
                    }


                    if ($form):
                        if (!empty($app['data'])):
                            $form->bind($app['data']);
                        endif;

                        echo $form->renderFieldset('basic');
                    endif;
                endif;

                // Render raw HTML (standalone or alongside XML form)
                if (!empty($app['html'])):
                    echo $app['html'];
                endif;
                ?>
            </div>
        </div>
    </div>
<?php endforeach; ?>
</div>
