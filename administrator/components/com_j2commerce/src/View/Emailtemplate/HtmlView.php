<?php
/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace J2Commerce\Component\J2commerce\Administrator\View\Emailtemplate;

defined('_JEXEC') or die;

use J2Commerce\Component\J2commerce\Administrator\Model\EmailtemplateModel;
use J2Commerce\Component\J2commerce\Administrator\View\AdminAssetsTrait;
use Joomla\CMS\Factory;
use Joomla\CMS\Form\Form;
use Joomla\CMS\Helper\ContentHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\GenericDataException;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\Session\Session;
use Joomla\CMS\Toolbar\Toolbar;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Joomla\CMS\Event\GenericEvent;

class HtmlView extends BaseHtmlView
{
    use AdminAssetsTrait;

    protected $item;
    protected $form;
    protected $state;
    protected $shortcodes;
    protected $pluginTemplateCards = [];

    /**
     * Execute and display a template script.
     *
     * @param   string  $tpl  The name of the template file to parse; automatically searches through the template paths.
     *
     * @return  void
     *
     * @since   6.0.0
     */
    public function display($tpl = null)
    {
        $this->loadAdminAssets();

        /** @var EmailtemplateModel $model */
        $model = $this->getModel();

        $this->item       = $model->getItem();
        $this->form       = $model->getForm();
        $this->state      = $model->getState();
        $this->shortcodes = $model->getAvailableShortcodes();

        // File-based body source can execute arbitrary PHP — restrict to super users
        if (!Factory::getApplication()->getIdentity()->authorise('core.admin')) {
            $this->form->setFieldAttribute('body_source', 'filter', 'cmd');
            $bodySourceField = $this->form->getField('body_source');
            if ($bodySourceField) {
                $element = $bodySourceField->__get('element');
                foreach ($element->children() as $option) {
                    if ((string) $option['value'] === 'file') {
                        $dom = dom_import_simplexml($option);
                        $dom->parentNode->removeChild($dom);
                        break;
                    }
                }
            }
            // Force back to visual if currently set to file
            if (($this->item->body_source ?? '') === 'file') {
                $this->form->setValue('body_source', null, 'visual');
            }
        }

        // Merge type-specific shortcodes when editing a non-transactional email template
        $emailType = $this->item->email_type ?? 'transactional';
        if ($emailType !== 'transactional') {
            try {
                PluginHelper::importPlugin('j2commerce');
                $db = Factory::getContainer()->get(\Joomla\Database\DatabaseInterface::class);
                $registry = new \J2Commerce\Component\J2commerce\Administrator\Service\EmailTypeRegistry($db);
                $typeTags = $registry->getGroupedTagsForType($emailType);

                // Load plugin language files so PLG_ labels resolve
                $lang = Factory::getApplication()->getLanguage();
                foreach ($typeTags as $tags) {
                    foreach ($tags as $tagConfig) {
                        $label = $tagConfig['label'] ?? '';
                        if (str_starts_with($label, 'PLG_') && $label === Text::_($label)) {
                            $parts = explode('_', strtolower($label));
                            if (\count($parts) >= 4) {
                                $ext = $parts[0] . '_' . $parts[1] . '_' . $parts[2] . '_' . $parts[3];
                                $lang->load($ext, JPATH_ADMINISTRATOR)
                                    || $lang->load($ext, JPATH_PLUGINS . '/' . $parts[1] . '/' . $parts[2] . '_' . $parts[3]);
                            }
                        }
                    }
                }

                foreach ($typeTags as $group => $tags) {
                    $groupLabel = ucfirst($group);
                    if (!isset($this->shortcodes[$groupLabel])) {
                        $this->shortcodes[$groupLabel] = [];
                    }
                    foreach ($tags as $tagName => $tagConfig) {
                        $label = $tagConfig['label'] ?? $tagName;
                        $this->shortcodes[$groupLabel]["[$tagName]"] = Text::_($label);
                    }
                }
            } catch (\Exception $e) {
                // Graceful degradation — type-specific shortcodes unavailable
            }
        }

        // Replace shortcode src/href values with data URI placeholders to prevent 404s in TinyMCE
        if (!empty($this->item->body)) {
            $placeholder = "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='100' height='40'%3E%3Crect fill='%23e5e7eb' width='100' height='40' rx='4'/%3E%3Ctext x='50' y='24' text-anchor='middle' font-family='sans-serif' font-size='10' fill='%236b7280'%3EShortcode%3C/text%3E%3C/svg%3E";
            $this->item->body = preg_replace(
                '/(<img[^>]*)\ssrc="(\[[A-Z_]+\])"/',
                '$1 src="' . $placeholder . '" data-j2c-src="$2"',
                $this->item->body
            );
            $this->form->setValue('body', null, $this->item->body);
        }

        // Gather plugin-provided template cards
        PluginHelper::importPlugin('j2commerce');
        $pluginCards = [];
        $pluginEvent = new GenericEvent('onJ2CommerceGetEmailTemplateCards', ['cards' => &$pluginCards]);
        Factory::getApplication()->getDispatcher()->dispatch('onJ2CommerceGetEmailTemplateCards', $pluginEvent);
        $this->pluginTemplateCards = $pluginCards;

        // Check for errors.
        if (\count($errors = $model->getErrors())) {
            throw new GenericDataException(implode("\n", $errors), 500);
        }

        $this->addToolbar();

        HTMLHelper::_('bootstrap.modal', '#sendTestModal');
        HTMLHelper::_('bootstrap.modal', '#loadTemplateModal');
        HTMLHelper::_('bootstrap.offcanvas', '#shortcodesOffcanvas');

        $bodySource = $this->item->body_source ?? 'visual';

        $wa = $this->getDocument()->getWebAssetManager();
        $wa->registerAndUseStyle('com_j2commerce.grapes.css', 'media/com_j2commerce/css/administrator/grapes.min.css');
        $wa->registerAndUseScript('com_j2commerce.grapes.js', 'media/com_j2commerce/js/administrator/grapes.min.js', [], ['defer' => true]);
        $wa->registerAndUseScript('com_j2commerce.grapes.newsletter.js', 'media/com_j2commerce/js/administrator/grapesjs-preset-newsletter.min.js', [], ['defer' => true]);
        $wa->registerAndUseStyle('com_j2commerce.grapes.j2commerce.css', 'media/com_j2commerce/css/administrator/grapesjs-j2commerce.css');
        $wa->registerAndUseScript('com_j2commerce.grapes.j2commerce.js', 'media/com_j2commerce/js/administrator/grapesjs-j2commerce.js', [], ['defer' => true]);

        $this->getDocument()->addScriptOptions('com_j2commerce.emaileditor', [
            'bodySource'      => $bodySource,
            'bodyJson'        => $this->item->body_json ?? '',
            'bodyHtml'        => $this->item->body ?? '',
            'shortcodes'      => $this->shortcodes ?? [],
            'emailType'       => $this->item->email_type ?? 'transactional',
            'csrfToken'       => Session::getFormToken(),
            'previewUrl'      => 'index.php?option=com_j2commerce&task=emailtemplate.preview&format=raw',
            'loadTemplateUrl' => 'index.php?option=com_j2commerce&task=emailtemplate.loadTemplate&format=json',
            'shortcodesUrl'   => 'index.php?option=com_j2commerce&task=emailtemplate.getShortcodes&format=json',
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
        // Block category and label strings for GrapesJS block manager
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

        parent::display($tpl);
    }

    /**
     * Add the page title and toolbar.
     *
     * @return  void
     *
     * @since   6.0.0
     */
    protected function addToolbar()
    {
        $toolbar    = $this->getDocument()->getToolbar();
        $isNew      = ($this->item->j2commerce_emailtemplate_id == 0);
        $canDo      = ContentHelper::getActions('com_j2commerce', 'emailtemplate', $this->item->j2commerce_emailtemplate_id);
        $user       = Factory::getApplication()->getIdentity();
        $checkedOut = !(($this->item->checked_out ?? null) === null || ($this->item->checked_out ?? 0) == $user->id);

        Factory::getApplication()->getInput()->set('hidemainmenu', true);

        $title = $isNew ? Text::_('COM_J2COMMERCE_EMAILTEMPLATE_NEW') : Text::_('COM_J2COMMERCE_EMAILTEMPLATE_EDIT');
        ToolbarHelper::title($title, 'envelope');

        $canEdit = !$checkedOut && ($canDo->get('core.edit') || ($canDo->get('core.create') && $isNew));

        if ($canEdit) {
            $toolbar->apply('emailtemplate.apply');
        }

        $saveGroup = $toolbar->dropdownButton('save-group');
        $saveGroup->configure(
            function (Toolbar $childBar) use ($canEdit, $canDo) {
                if ($canEdit) {
                    $childBar->save('emailtemplate.save');
                }
                if ($canDo->get('core.create')) {
                    $childBar->save2new('emailtemplate.save2new');
                    $childBar->save2copy('emailtemplate.save2copy');
                }
            }
        );

        $toolbar->cancel('emailtemplate.cancel');

        $toolbar->divider();

        // Load Template button
        $toolbar->customButton('loadTemplate')
            ->html('<button type="button" class="btn btn-outline-secondary mx-2" data-bs-toggle="modal" data-bs-target="#loadTemplateModal">'
                . '<span class="icon-copy me-1" aria-hidden="true"></span>'
                . Text::_('COM_J2COMMERCE_EMAILTEMPLATE_LOAD_TEMPLATE')
                . '</button>');

        // View Shortcodes button (opens offcanvas)
        $toolbar->customButton('viewShortcodes')
            ->html('<button type="button" class="btn btn-outline-info" data-bs-toggle="offcanvas" data-bs-target="#shortcodesOffcanvas">'
                . '<span class="icon-tags me-1" aria-hidden="true"></span>'
                . Text::_('COM_J2COMMERCE_EMAILTEMPLATE_SHORTCODES')
                . '</button>');

        $toolbar->divider();
        $toolbar->help('J2Commerce_Email_Template', false, 'https://docs.j2commerce.com/design/email-templates');
    }
}
