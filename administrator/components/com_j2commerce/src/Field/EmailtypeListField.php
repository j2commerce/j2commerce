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

use J2Commerce\Component\J2commerce\Administrator\Helper\EmailHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Form\Field\ListField;
use Joomla\CMS\Language\Text;

class EmailtypeListField extends ListField
{
    protected $type = 'EmailtypeList';

    protected function getOptions(): array
    {
        $options = parent::getOptions();

        try {
            $emailTypes = EmailHelper::getEmailTypes();
            $lang       = Factory::getApplication()->getLanguage();

            foreach ($emailTypes as $typeId => $typeConfig) {
                $label = $typeConfig['label'] ?? $typeId;

                if (preg_match('/^[A-Z0-9_]+$/', $label)) {
                    if (str_starts_with($label, 'PLG_') && $label === Text::_($label)) {
                        $this->loadPluginLanguage($label, $lang);
                    }

                    $label = Text::_($label);
                }

                $options[] = (object) [
                    'value' => $typeId,
                    'text'  => $label,
                ];
            }
        } catch (\Exception $e) {
            $options[] = (object) [
                'value' => 'transactional',
                'text'  => Text::_('COM_J2COMMERCE_EMAILTYPE_TRANSACTIONAL'),
            ];
        }

        return $options;
    }

    /** Derive plugin extension name from a PLG_ language key and load its language file. */
    private function loadPluginLanguage(string $key, \Joomla\CMS\Language\Language $lang): void
    {
        $parts = explode('_', strtolower($key));

        if (\count($parts) >= 4) {
            $ext = $parts[0] . '_' . $parts[1] . '_' . $parts[2] . '_' . $parts[3];
            $lang->load($ext, JPATH_ADMINISTRATOR)
                || $lang->load($ext, JPATH_PLUGINS . '/' . $parts[1] . '/' . $parts[2] . '_' . $parts[3]);
        }
    }
}
