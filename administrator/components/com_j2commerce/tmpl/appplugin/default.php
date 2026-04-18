<?php

/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

/** @var \J2Commerce\Component\J2commerce\Administrator\View\Appplugin\HtmlView $this */

echo $this->navbar;

echo $this->pluginHtml;

echo $this->footer ?? '';
