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
use Joomla\CMS\Form\FormHelper;
use Joomla\CMS\User\CurrentUserInterface;
use Joomla\Database\DatabaseAwareInterface;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\Exception\DatabaseNotFoundException;

class RouterModalCategoryField extends FormField
{
    public $type = 'RouterModalCategory';

    protected ?FormField $delegateField = null;

    public function setup(\SimpleXMLElement $element, $value, $group = null): bool
    {
        $result = parent::setup($element, $value, $group);

        if (!$result) {
            return false;
        }

        $this->delegateField = $this->createModalCategoryField($element, $value, $group);

        return $this->delegateField !== null;
    }

    protected function createModalCategoryField(\SimpleXMLElement $element, $value, ?string $group): ?FormField
    {
        if (\is_array($value)) {
            $value = !empty($value) ? (string) reset($value) : '';
        }

        FormHelper::addFieldPrefix('Joomla\\Component\\Categories\\Administrator\\Field');
        $className = FormHelper::loadFieldClass('modal_category');

        if (!$className) {
            $className = 'Joomla\\Component\\Categories\\Administrator\\Field\\Modal\\CategoryField';

            if (!class_exists($className)) {
                return null;
            }
        }

        $modalXml              = new \SimpleXMLElement('<field />');
        $modalXml['name']      = (string) $element['name'];
        $modalXml['type']      = 'modal_category';
        $modalXml['label']     = (string) ($element['label'] ?? 'JGLOBAL_CHOOSE_CATEGORY_LABEL');
        $modalXml['extension'] = (string) ($element['extension'] ?? 'com_content');
        $modalXml['required']  = (string) ($element['required'] ?? 'false');
        $modalXml['select']    = 'true';
        $modalXml['new']       = 'true';
        $modalXml['edit']      = 'true';
        $modalXml['clear']     = 'true';

        $field = new $className();

        $this->injectFieldDependencies($field);

        $field->setForm($this->form);

        if ($field->setup($modalXml, $value, $group)) {
            return $field;
        }

        return null;
    }

    protected function injectFieldDependencies(FormField $field): void
    {
        if ($field instanceof DatabaseAwareInterface) {
            try {
                $field->setDatabase($this->getDatabase());
            } catch (DatabaseNotFoundException $e) {
                $field->setDatabase(Factory::getContainer()->get(DatabaseInterface::class));
            }
        }

        if ($field instanceof CurrentUserInterface) {
            try {
                $field->setCurrentUser($this->getCurrentUser());
            } catch (\Exception $e) {
                $field->setCurrentUser(Factory::getApplication()->getIdentity());
            }
        }
    }

    protected function getInput(): string
    {
        return $this->delegateField ? $this->delegateField->__get('input') : '';
    }

    protected function getLabel(): string
    {
        return $this->delegateField ? $this->delegateField->__get('label') : parent::getLabel();
    }

    public function __get($name)
    {
        if ($this->delegateField && \in_array($name, ['input', 'label'])) {
            return $this->delegateField->__get($name);
        }

        return parent::__get($name);
    }
}
