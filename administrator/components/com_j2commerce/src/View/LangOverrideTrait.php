<?php

/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace J2Commerce\Component\J2commerce\Administrator\View;

\defined('_JEXEC') or die;

use J2Commerce\Component\J2commerce\Administrator\Model\LangoverrideModel;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Session\Session;
use Joomla\CMS\WebAsset\WebAssetManager;

/**
 * Wiring the editors that render a [LANG:KEY] body share: the dialog that rewords one key, and the
 * strings the canvas shows in place of the tokens. Every screen built on the GrapesJS wrapper needs
 * the same block, and the wrapper reads the same option names on all of them.
 */
trait LangOverrideTrait
{
    /** Installed languages the override dialog offers, tag => title. */
    protected array $overrideLanguages = [];

    /** Language the dialog opens on: the one this admin is reading the resolved wording in. */
    protected string $overrideDefaultTag = '';

    /** Key the dialog opens against, empty on a screen whose keys all live in the body. */
    protected string $subjectKey = '';

    /**
     * Register the override dialog's assets and options.
     *
     * Gated on the capability that writing a language override needs - Super User, the same gate
     * the tasks themselves apply. $needed lets the caller add its own condition: a screen with no
     * token to reword loads none of this.
     */
    protected function prepareLangOverride(WebAssetManager $wa, bool $needed): void
    {
        if (!$needed || !$this->getCurrentUser()->authorise('core.admin')) {
            return;
        }

        // Not $this->getModel(): the controller only ever pushes the screen's own model onto the
        // view, so the override model has to be built from the component's own factory.
        /** @var LangoverrideModel $model */
        $model = Factory::getApplication()->bootComponent('com_j2commerce')
            ->getMVCFactory()
            ->createModel('Langoverride', 'Administrator', ['ignore_request' => true]);

        $this->overrideLanguages  = $model->getLanguages();
        $this->overrideDefaultTag = Factory::getApplication()->getLanguage()->getTag();

        if (!isset($this->overrideLanguages[$this->overrideDefaultTag])) {
            $this->overrideDefaultTag = array_key_first($this->overrideLanguages);
        }

        $wa->registerAndUseScript(
            'com_j2commerce.emailtemplate.subjectoverride',
            'media/com_j2commerce/js/administrator/emailtemplate-subject-override.js',
            [],
            ['type' => 'module'],
            ['joomla.dialog']
        );

        $this->getDocument()->addScriptOptions('com_j2commerce.subjectoverride', [
            'baseUrl'  => 'index.php?option=com_j2commerce',
            'token'    => Session::getFormToken(),
            'adminTag' => $this->overrideDefaultTag,
            // Core stores JSAVE as "Save &amp; Close" because it is normally echoed into HTML.
            // The dialog sets its button labels with textContent, which would print the entity.
            'saveLabel'  => html_entity_decode(Text::_('JSAVE'), \ENT_QUOTES, 'UTF-8'),
            'closeLabel' => html_entity_decode(Text::_('JCLOSE'), \ENT_QUOTES, 'UTF-8'),
        ]);

        Text::script('COM_J2COMMERCE_EMAILTEMPLATE_SUBJECT_OVERRIDE_TITLE');
        Text::script('COM_J2COMMERCE_EMAILTEMPLATE_SUBJECT_OVERRIDE_SAVED');
        Text::script('COM_J2COMMERCE_EMAILTEMPLATE_SUBJECT_OVERRIDE_SAVE_FAILED');
        Text::script('COM_J2COMMERCE_EMAILTEMPLATE_LANG_OVERRIDE_EDIT');
    }
}
