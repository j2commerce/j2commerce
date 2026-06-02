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
use Joomla\CMS\Form\Field\ListField;
use Joomla\CMS\HTML\HTMLHelper;

/**
 * OptionType field - dropdown of product option types.
 *
 * Sources the list from OptionModel::getOptionTypes(), so any type a plugin
 * registers via the onJ2CommerceGetOptionTypes event appears automatically.
 *
 * @since  6.0.7
 */
class OptiontypeField extends ListField
{
    protected $type = 'OptionType';

    protected static ?array $cachedTypes = null;

    public function getOptions(): array
    {
        $options = parent::getOptions();

        foreach ($this->getOptionTypes() as $value => $label) {
            $options[] = HTMLHelper::_('select.option', $value, $label);
        }

        return $options;
    }

    protected function getOptionTypes(): array
    {
        if (self::$cachedTypes !== null) {
            return self::$cachedTypes;
        }

        $model = Factory::getApplication()
            ->bootComponent('com_j2commerce')
            ->getMVCFactory()
            ->createModel('Option', 'Administrator', ['ignore_request' => true]);

        self::$cachedTypes = $model ? $model->getOptionTypes() : [];

        return self::$cachedTypes;
    }
}
