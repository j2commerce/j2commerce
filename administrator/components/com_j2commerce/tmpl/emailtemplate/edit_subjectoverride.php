<?php

/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

\defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;

/** @var \J2Commerce\Component\J2commerce\Administrator\View\Emailtemplate\HtmlView $this */
?>
<div class="j2c-subject-override">
    <div class="mb-3">
        <label class="form-label" for="j2c-so-language"><?php echo Text::_('COM_J2COMMERCE_EMAILTEMPLATE_SUBJECT_OVERRIDE_LANGUAGE'); ?></label>
        <select class="form-select" id="j2c-so-language">
            <?php foreach ($this->overrideLanguages as $tag => $title) : ?>
                <option value="<?php echo $this->escape($tag); ?>"<?php echo $tag === $this->overrideDefaultTag ? ' selected' : ''; ?>>
                    <?php echo $this->escape($title); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="mb-3">
        <label class="form-label" for="j2c-so-key"><?php echo Text::_('COM_J2COMMERCE_EMAILTEMPLATE_SUBJECT_OVERRIDE_KEY'); ?></label>
        <input type="text" class="form-control" id="j2c-so-key" value="<?php echo $this->escape($this->subjectKey); ?>" readonly>
    </div>

    <div class="mb-3">
        <label class="form-label" for="j2c-so-original"><?php echo Text::_('COM_J2COMMERCE_EMAILTEMPLATE_SUBJECT_OVERRIDE_ORIGINAL'); ?></label>
        <textarea class="form-control" id="j2c-so-original" rows="2" readonly aria-describedby="j2c-so-original-desc"></textarea>
        <div id="j2c-so-original-desc" class="form-text"><?php echo Text::_('COM_J2COMMERCE_EMAILTEMPLATE_SUBJECT_OVERRIDE_ORIGINAL_DESC'); ?></div>
    </div>

    <div class="mb-3">
        <label class="form-label" for="j2c-so-text"><?php echo Text::_('COM_J2COMMERCE_EMAILTEMPLATE_SUBJECT_OVERRIDE_TEXT'); ?></label>
        <textarea class="form-control" id="j2c-so-text" rows="2" aria-describedby="j2c-so-text-desc"></textarea>
        <div id="j2c-so-text-desc" class="form-text"><?php echo Text::_('COM_J2COMMERCE_EMAILTEMPLATE_SUBJECT_OVERRIDE_TEXT_DESC'); ?></div>
    </div>

    <div id="j2c-so-status" role="status" class="j2c-so-status"></div>
</div>
