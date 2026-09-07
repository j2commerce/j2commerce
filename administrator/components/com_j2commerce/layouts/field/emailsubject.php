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
 * @var  array   $displayData
 * @var  string  $id
 * @var  string  $name
 * @var  string  $token
 * @var  string  $key
 * @var  string  $resolved
 * @var  string  $class
 * @var  bool    $readonly
 */
extract($displayData);

$hintId = $id . '-token-hint';
?>
<div class="input-group">
    <input
        type="text"
        id="<?php echo $this->escape($id); ?>"
        class="form-control <?php echo $this->escape($class); ?>"
        value="<?php echo $this->escape($resolved); ?>"
        aria-describedby="<?php echo $this->escape($hintId); ?>"
        readonly>
    <?php if (!$readonly) : ?>
        <button
            type="button"
            class="btn btn-secondary"
            id="<?php echo $this->escape($id); ?>-editsubject"
            data-j2c-subject-key="<?php echo $this->escape($key); ?>"
            data-j2c-subject-target="<?php echo $this->escape($id); ?>">
            <span class="icon-language" aria-hidden="true"></span>
            <?php echo Text::_('COM_J2COMMERCE_EMAILTEMPLATE_SUBJECT_EDIT'); ?>
        </button>
    <?php endif; ?>
</div>
<input type="hidden" name="<?php echo $this->escape($name); ?>" id="<?php echo $this->escape($id); ?>-token" value="<?php echo $this->escape($token); ?>">
<div id="<?php echo $this->escape($hintId); ?>" class="form-text">
    <?php echo Text::sprintf('COM_J2COMMERCE_EMAILTEMPLATE_SUBJECT_TOKEN_HINT', $this->escape($key)); ?>
</div>
<?php // Lives on the page rather than in the dialog: a save closes the dialog, and a live region
      // that is removed in the same breath as it is written never gets announced. ?>
<div id="<?php echo $this->escape($id); ?>-status" role="status" class="form-text"></div>
