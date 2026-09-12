<?php

/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace J2Commerce\Component\J2commerce\Administrator\Field;

use J2Commerce\Component\J2commerce\Administrator\Model\AttachmentrelocateModel;
use Joomla\CMS\Factory;
use Joomla\CMS\Form\FormField;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Session\Session;
use Joomla\CMS\Uri\Uri;

\defined('_JEXEC') or die;

/**
 * The Update Files control, rendered under the attachment-path field.
 *
 * Renders nothing at all on a correctly configured store: the control only means anything
 * where the configured root is a directory this component does not own and something is
 * sitting in it. That check is the model's, so the button and the action agree on when
 * there is work.
 *
 * @since  6.6.2
 */
class AttachmentrelocateField extends FormField
{
    protected $type = 'Attachmentrelocate';

    /** Memoized: renderField() and getInput() both ask, and the scan reads the filesystem. */
    private ?array $scanned = null;

    private bool $didScan = false;

    /**
     * No control, no row.
     *
     * renderField() hands back getInput() verbatim only for a hidden field; otherwise it wraps
     * whatever getInput() returned in the label and control-group markup. An empty string from
     * getInput() therefore still leaves a labelled, empty row on the Options screen, whose
     * <label for> points at an id that nothing renders.
     */
    public function renderField($options = [])
    {
        return $this->isApplicable() ? parent::renderField($options) : '';
    }

    protected function getInput(): string
    {
        if (!$this->isApplicable()) {
            return '';
        }

        $scan = $this->scan();

        static::loadAssetsStatic();

        // base(true) keeps the request's own scheme and host out of it. Uri::base() answered
        // https:// on an http:// request here, which made the fetch cross-origin and dropped
        // the session the CSRF check needs.
        $endpoint  = Uri::base(true) . '/index.php?option=com_j2commerce&task=attachmentrelocate.';
        $csrfToken = Session::getFormToken();

        // Joomla.toggleInlineHelp() selects `div.hide-aware-inline-help`, strips the trailing
        // '-desc' from the div's id, and wires aria-describedby onto the element left holding
        // that id — so the div must be a div, the id must be "{field id}-desc", and the button
        // must carry the field's own id. aria-describedby is deliberately absent here: the
        // toggle adds it when the help is shown and removes it when hidden, and hardcoding it
        // would point at content that is display:none most of the time.
        $descId = $this->id . '-desc';

        $html   = [];
        $html[] = '<div class="j2c-attachment-relocate" '
            . 'data-endpoint="' . htmlspecialchars($endpoint, ENT_QUOTES, 'UTF-8') . '" '
            . 'data-csrf-token="' . htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') . '">';

        // A real <button>, so the role, focusability and Enter/Space activation come from the
        // element rather than from script: references/00-standards/tr-using-aria.md, first rule.
        $html[] = '<button type="button" id="' . htmlspecialchars($this->id, ENT_QUOTES, 'UTF-8') . '" '
            . 'class="btn btn-primary btn-sm" data-j2c-relocate>'
            . htmlspecialchars(Text::_('COM_J2COMMERCE_CONFIG_ATTACHMENT_RELOCATE_BUTTON'), ENT_QUOTES, 'UTF-8')
            . '</button>';

        $html[] = '<div id="' . htmlspecialchars($descId, ENT_QUOTES, 'UTF-8') . '" class="hide-aware-inline-help d-none">';

        $html[] = '<small class="form-text">'
            . htmlspecialchars(
                Text::sprintf(
                    'COM_J2COMMERCE_CONFIG_ATTACHMENT_RELOCATE_DESC',
                    $scan['destination_display'],
                    $scan['source_display'],
                    $scan['outstanding']
                ),
                ENT_QUOTES,
                'UTF-8'
            )
            . '</small>';

        if ($scan['counts'][AttachmentrelocateModel::STATE_ORPHAN] > 0) {
            $html[] = '<small class="form-text">'
                . htmlspecialchars(
                    Text::sprintf(
                        'COM_J2COMMERCE_CONFIG_ATTACHMENT_RELOCATE_ORPHANS',
                        $scan['counts'][AttachmentrelocateModel::STATE_ORPHAN]
                    ),
                    ENT_QUOTES,
                    'UTF-8'
                )
                . '</small>';
        }

        $html[] = '</div>';
        $html[] = '</div>';

        return implode('', $html);
    }

    public static function loadAssetsStatic(): void
    {
        static $loaded = false;

        if ($loaded) {
            return;
        }

        $loaded = true;

        HTMLHelper::_('bootstrap.modal');

        $wa = Factory::getApplication()->getDocument()->getWebAssetManager();
        $wa->registerAndUseScript(
            'com_j2commerce.admin.attachmentrelocate',
            'media/com_j2commerce/js/administrator/attachmentrelocate.js',
            [],
            ['defer' => true]
        );

        Text::script('COM_J2COMMERCE_CONFIG_ATTACHMENT_RELOCATE_MODAL_TITLE');
        Text::script('COM_J2COMMERCE_CONFIG_ATTACHMENT_RELOCATE_SCANNING');
        Text::script('COM_J2COMMERCE_CONFIG_ATTACHMENT_RELOCATE_PROGRESS');
        Text::script('COM_J2COMMERCE_CONFIG_ATTACHMENT_RELOCATE_PROGRESS_LABEL');
        Text::script('COM_J2COMMERCE_CONFIG_ATTACHMENT_RELOCATE_SUMMARY');
        Text::script('COM_J2COMMERCE_CONFIG_ATTACHMENT_RELOCATE_COMPLETE');
        Text::script('COM_J2COMMERCE_CONFIG_ATTACHMENT_RELOCATE_FINALIZED');
        Text::script('COM_J2COMMERCE_CONFIG_ATTACHMENT_RELOCATE_NOT_FINALIZED');
        Text::script('COM_J2COMMERCE_CONFIG_ATTACHMENT_RELOCATE_NOTHING');
        Text::script('COM_J2COMMERCE_CONFIG_ATTACHMENT_RELOCATE_NOTES');
        Text::script('COM_J2COMMERCE_CONFIG_ATTACHMENT_RELOCATE_ERROR');
        Text::script('COM_J2COMMERCE_CONFIG_ATTACHMENT_RELOCATE_CANCELLED');
        Text::script('JCANCEL');
        Text::script('JCLOSE');
    }

    /** Whether there is a move to offer: an unowned root with something still in it. */
    private function isApplicable(): bool
    {
        $scan = $this->scan();

        return $scan !== null && $scan['eligible'] && $scan['outstanding'] > 0;
    }

    /**
     * @return  array<string, mixed>|null  The model's scan, or null when it cannot be reached.
     */
    private function scan(): ?array
    {
        if ($this->didScan) {
            return $this->scanned;
        }

        $this->didScan = true;

        try {
            /** @var AttachmentrelocateModel $model */
            $model = Factory::getApplication()
                ->bootComponent('com_j2commerce')
                ->getMVCFactory()
                ->createModel('Attachmentrelocate', 'Administrator', ['ignore_request' => true]);

            $this->scanned = $model->scan();
        } catch (\Throwable) {
            // The Options form must render whatever this control cannot say.
            $this->scanned = null;
        }

        return $this->scanned;
    }
}
