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

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Form\FormField;
use Joomla\CMS\Language\Text;

/**
 * Price field - displays an alert box with pricing information.
 *
 * @since  6.0.7
 */
class PriceField extends FormField
{
    protected $type = 'Price';

    protected function getInput(): string
    {
        if (!empty($this->layout)) {
            return parent::getInput();
        }

        $wa = Factory::getApplication()->getDocument()->getWebAssetManager();

        // Hide the description element
        $helpId = htmlspecialchars($this->id, ENT_COMPAT, 'UTF-8') . '-desc';
        $wa->addInlineStyle('#' . $helpId . ' {display:none!important;}');

        $title       = (string) $this->element['label'];
        $description = $this->description;
        $class       = ' ' . $this->class;
        $margin      = $description ? '' : ' mb-0';

        $html = '<div id="' . htmlspecialchars($this->id, ENT_COMPAT, 'UTF-8') . '" class="alert' . htmlspecialchars($class, ENT_COMPAT, 'UTF-8') . '">';
        $html .= '<h3 class="alert-heading h3' . $margin . '">' . Text::_($title) . '</h3>';

        if ($description) {
            $html .= '<p>' . Text::_($description) . '</p>';
        }

        $html .= '</div>';

        return $html;
    }
}
