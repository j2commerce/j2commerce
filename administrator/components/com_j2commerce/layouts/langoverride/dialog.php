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

/**
 * Body of the language-override dialog, shared by every editor that writes one.
 *
 * @var array $displayData
 * @var array<string, string> $languages  Installed languages, tag => title.
 * @var string $defaultTag  Language the dialog opens on.
 * @var string $key  Key it opens against, empty where the caller has many and the script picks one.
 */
$languages  = $displayData['languages'] ?? [];
$defaultTag = (string) ($displayData['defaultTag'] ?? '');
$key        = (string) ($displayData['key'] ?? '');
?>
<div class="j2c-subject-override p-3">
    <div class="mb-3">
        <label class="form-label" for="j2c-so-language"><?php echo Text::_('COM_J2COMMERCE_EMAILTEMPLATE_SUBJECT_OVERRIDE_LANGUAGE'); ?></label>
        <select class="form-select" id="j2c-so-language">
            <?php foreach ($languages as $tag => $title) : ?>
                <option value="<?php echo htmlspecialchars((string) $tag, ENT_COMPAT, 'UTF-8'); ?>"<?php echo $tag === $defaultTag ? ' selected' : ''; ?>>
                    <?php echo htmlspecialchars((string) $title, ENT_COMPAT, 'UTF-8'); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="mb-3">
        <label class="form-label" for="j2c-so-key"><?php echo Text::_('COM_J2COMMERCE_EMAILTEMPLATE_SUBJECT_OVERRIDE_KEY'); ?></label>
        <input type="text" class="form-control" id="j2c-so-key" value="<?php echo htmlspecialchars($key, ENT_COMPAT, 'UTF-8'); ?>" readonly>
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
