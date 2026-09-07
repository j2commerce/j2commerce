<?php

/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

\defined('_JEXEC') or die;

use Joomla\CMS\Layout\LayoutHelper;

/** @var \J2Commerce\Component\J2commerce\Administrator\View\Emailtemplate\HtmlView $this */

echo LayoutHelper::render(
    'langoverride.dialog',
    [
        'languages'  => $this->overrideLanguages,
        'defaultTag' => $this->overrideDefaultTag,
        'key'        => $this->subjectKey,
    ],
    JPATH_ADMINISTRATOR . '/components/com_j2commerce/layouts'
);
