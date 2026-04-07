<?php

/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace J2Commerce\Component\J2commerce\Administrator\View\Invoicetemplate;

\defined('_JEXEC') or die;

use J2Commerce\Component\J2commerce\Administrator\View\AdminAssetsTrait;
use Joomla\CMS\Factory;
use Joomla\CMS\Helper\ContentHelper;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\GenericDataException;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Session\Session;
use Joomla\CMS\Toolbar\Toolbar;
use Joomla\CMS\Toolbar\ToolbarHelper;

class HtmlView extends BaseHtmlView
{
    use AdminAssetsTrait;

    protected $item;
    protected $form;
    protected $state;
    protected $shortcodes;

    public function display($tpl = null)
    {
        $this->loadAdminAssets();

        $model = $this->getModel();

        $this->item       = $model->getItem();
        $this->form       = $model->getForm();
        $this->state      = $model->getState();
        $this->shortcodes = $model->getAvailableShortcodes();

        // Replace shortcode src values with data URI placeholders to prevent 404s in TinyMCE
        if (!empty($this->item->body)) {
            $placeholder      = "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='100' height='40'%3E%3Crect fill='%23e5e7eb' width='100' height='40' rx='4'/%3E%3Ctext x='50' y='24' text-anchor='middle' font-family='sans-serif' font-size='10' fill='%236b7280'%3EShortcode%3C/text%3E%3C/svg%3E";
            $this->item->body = preg_replace(
                '/(<img[^>]*)\ssrc="(\[[A-Z_]+\])"/',
                '$1 src="' . $placeholder . '" data-j2c-src="$2"',
                $this->item->body
            );
            $this->form->setValue('body', null, $this->item->body);
        }

        if (\count($errors = $model->getErrors())) {
            throw new GenericDataException(implode("\n", $errors), 500);
        }

        $this->addToolbar();

        HTMLHelper::_('bootstrap.modal', '#loadTemplateModal');
        HTMLHelper::_('bootstrap.offcanvas', '#shortcodesOffcanvas');

        $bodySource = $this->item->body_source ?? 'editor';

        $wa = $this->getDocument()->getWebAssetManager();
        $wa->registerAndUseStyle('com_j2commerce.vendor.grapesjs.css', 'media/com_j2commerce/vendor/grapesjs/css/grapes.min.css');
        $wa->registerAndUseScript('com_j2commerce.vendor.grapesjs', 'media/com_j2commerce/vendor/grapesjs/js/grapes.min.js', [], ['defer' => true]);
        $wa->registerAndUseScript('com_j2commerce.vendor.grapesjs.newsletter', 'media/com_j2commerce/vendor/grapesjs/js/grapesjs-preset-newsletter.min.js', [], ['defer' => true]);
        $wa->registerAndUseStyle('com_j2commerce.grapes.j2commerce.css', 'media/com_j2commerce/css/administrator/grapesjs-j2commerce.css');
        $wa->registerAndUseScript('com_j2commerce.grapes.j2commerce.js', 'media/com_j2commerce/js/administrator/grapesjs-j2commerce.js', [], ['defer' => true]);

        $this->getDocument()->addScriptOptions('com_j2commerce.emaileditor', [
            'bodySource'      => $bodySource,
            'bodyJson'        => $this->item->body_json ?? '',
            'bodyHtml'        => $this->item->body ?? '',
            'shortcodes'      => $this->shortcodes ?? [],
            'csrfToken'       => Session::getFormToken(),
            'previewUrl'      => 'index.php?option=com_j2commerce&task=invoicetemplate.preview&format=raw',
            'loadTemplateUrl' => 'index.php?option=com_j2commerce&task=invoicetemplate.loadTemplate&format=json',
            'getPresetsUrl'   => 'index.php?option=com_j2commerce&task=invoicetemplate.getTemplatePresets&format=json',
        ]);

        Text::script('COM_J2COMMERCE_EMAILTEMPLATE_LOADING');
        Text::script('COM_J2COMMERCE_EMAILTEMPLATE_SENDING');
        Text::script('COM_J2COMMERCE_EMAILTEMPLATE_ENTER_EMAIL');
        Text::script('COM_J2COMMERCE_EMAILTEMPLATE_PREVIEW_FAILED');
        Text::script('COM_J2COMMERCE_EMAILTEMPLATE_SEND_FAILED');
        Text::script('COM_J2COMMERCE_EMAILTEMPLATE_TEST_SENT');
        Text::script('COM_J2COMMERCE_EMAILTEMPLATE_LOAD_SUCCESS');
        Text::script('COM_J2COMMERCE_EMAILTEMPLATE_LOAD_FAILED');
        Text::script('COM_J2COMMERCE_EMAILTEMPLATE_REQUEST_FAILED');
        Text::script('COM_J2COMMERCE_EMAILTEMPLATE_CODE_MODE_WARNING');
        Text::script('COM_J2COMMERCE_EMAILTEMPLATE_VISUAL_MODE_IMPORT');
        Text::script('COM_J2COMMERCE_EMAILTEMPLATE_LOAD_TEMPLATE_CONFIRM');
        Text::script('COM_J2COMMERCE_EMAILTEMPLATE_CATEGORY_J2_BLOCKS');
        Text::script('COM_J2COMMERCE_EMAILTEMPLATE_CATEGORY_SHORTCODES');
        Text::script('COM_J2COMMERCE_EMAILTEMPLATE_BLOCK_ORDER_ITEMS');
        Text::script('COM_J2COMMERCE_EMAILTEMPLATE_BLOCK_BILLING_ADDRESS');
        Text::script('COM_J2COMMERCE_EMAILTEMPLATE_BLOCK_SHIPPING_ADDRESS');
        Text::script('COM_J2COMMERCE_EMAILTEMPLATE_BLOCK_ORDER_SUMMARY');
        Text::script('COM_J2COMMERCE_EMAILTEMPLATE_BLOCK_CTA_BUTTON');
        Text::script('COM_J2COMMERCE_EMAILTEMPLATE_BLOCK_STORE_LOGO');
        Text::script('COM_J2COMMERCE_EMAILTEMPLATE_BLOCK_SOCIAL_LINKS');
        Text::script('COM_J2COMMERCE_EMAILTEMPLATE_BLOCK_CONDITIONAL');
        Text::script('COM_J2COMMERCE_EMAILTEMPLATE_BLOCK_HOOK');
        Text::script('COM_J2COMMERCE_INVOICETEMPLATE_PRINT_TEST');
        Text::script('COM_J2COMMERCE_INVOICETEMPLATE_REFRESH_PREVIEW');
        Text::script('COM_J2COMMERCE_INVOICETEMPLATE_LOAD_TEMPLATE_CONFIRM');
        Text::script('COM_J2COMMERCE_INVOICETEMPLATE_LOADING');
        Text::script('COM_J2COMMERCE_INVOICETEMPLATE_PREVIEW_FAILED');
        Text::script('COM_J2COMMERCE_INVOICETEMPLATE_REQUEST_FAILED');
        Text::script('COM_J2COMMERCE_INVOICETEMPLATE_LOAD_SUCCESS');
        Text::script('COM_J2COMMERCE_INVOICETEMPLATE_LOAD_FAILED');
        Text::script('COM_J2COMMERCE_INVOICETEMPLATE_NO_PRESETS');

        parent::display($tpl);
    }

    protected function addToolbar()
    {
        $toolbar    = $this->getDocument()->getToolbar();
        $isNew      = ($this->item->j2commerce_invoicetemplate_id == 0);
        $canDo      = ContentHelper::getActions('com_j2commerce', 'invoicetemplate', $this->item->j2commerce_invoicetemplate_id ?? 0);
        $user       = Factory::getApplication()->getIdentity();
        $checkedOut = !(($this->item->checked_out ?? null) === null || ($this->item->checked_out ?? 0) == $user->id);

        Factory::getApplication()->getInput()->set('hidemainmenu', true);

        $title = $isNew ? Text::_('COM_J2COMMERCE_INVOICETEMPLATE_NEW') : Text::_('COM_J2COMMERCE_INVOICETEMPLATE_EDIT');
        ToolbarHelper::title($title, 'fa-solid fa-print');

        $canEdit = !$checkedOut && ($canDo->get('core.edit') || ($canDo->get('core.create') && $isNew));

        if ($canEdit) {
            $toolbar->apply('invoicetemplate.apply');
        }

        $saveGroup = $toolbar->dropdownButton('save-group');
        $saveGroup->configure(
            function (Toolbar $childBar) use ($canEdit, $canDo, $isNew) {
                if ($canEdit) {
                    $childBar->save('invoicetemplate.save');
                }
                if ($canDo->get('core.create')) {
                    $childBar->save2new('invoicetemplate.save2new');
                    if (!$isNew) {
                        $childBar->save2copy('invoicetemplate.save2copy');
                    }
                }
            }
        );

        $toolbar->cancel('invoicetemplate.cancel');

        $toolbar->divider();

        $toolbar->customButton('loadTemplate')
            ->html('<button type="button" class="btn btn-outline-secondary mx-2" data-bs-toggle="modal" data-bs-target="#loadTemplateModal">'
                . '<span class="icon-copy me-2" aria-hidden="true"></span>'
                . Text::_('COM_J2COMMERCE_INVOICETEMPLATE_LOAD_TEMPLATE')
                . '</button>');

        $toolbar->customButton('viewShortcodes')
            ->html('<button type="button" class="btn btn-outline-info me-1" data-bs-toggle="offcanvas" data-bs-target="#shortcodesOffcanvas">'
                . '<span class="icon-tags me-2" aria-hidden="true"></span>'
                . Text::_('COM_J2COMMERCE_INVOICETEMPLATE_SHORTCODES')
                . '</button>');

        $toolbar->customButton('printTest')
            ->html('<button type="button" class="btn btn-success" id="btn-print-test">'
                . '<span class="icon-print me-2" aria-hidden="true"></span>'
                . Text::_('COM_J2COMMERCE_INVOICETEMPLATE_PRINT_TEST')
                . '</button>');

        $toolbar->divider();
        $toolbar->help('Print_Template:_Edit');
    }
}
