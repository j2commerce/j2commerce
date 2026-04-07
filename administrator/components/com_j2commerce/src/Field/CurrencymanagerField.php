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

use Joomla\CMS\Form\FormField;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Uri\Uri;

class CurrencymanagerField extends FormField
{
    protected $type = 'Currencymanager';

    protected function getInput(): string
    {
        $modalId  = 'currencyManagerModal';
        $basePath = rtrim(Uri::root(), '/') . '/administrator/';
        $modalUrl = $basePath . 'index.php?option=com_j2commerce&view=currencies&tmpl=component';

        $html = '<a href="' . htmlspecialchars($modalUrl, ENT_COMPAT, 'UTF-8') . '" '
            . 'class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#' . $modalId . '">'
            . '<span class="icon-cog" aria-hidden="true"></span> '
            . htmlspecialchars(Text::_('COM_J2COMMERCE_CURRENCY_MANAGE_BTN'), ENT_COMPAT, 'UTF-8')
            . '</a>';

        $html .= HTMLHelper::_(
            'bootstrap.renderModal',
            $modalId,
            [
                'url'        => $modalUrl,
                'title'      => Text::_('COM_J2COMMERCE_CONFIG_CURRENCY_MANAGEMENT'),
                'height'     => '100%',
                'width'      => '100%',
                'modalWidth' => '90%',
                'bodyHeight' => '80%',
                'footer'     => '<button type="button" class="btn btn-primary" data-bs-dismiss="modal">'
                    . Text::_('JLIB_HTML_BEHAVIOR_CLOSE') . '</button>',
            ]
        );

        return $html;
    }
}
