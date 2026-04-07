<?php

/**
 * @package     J2Commerce Library
 * @subpackage  lib_j2commerce
 *
 * @copyright   (C)2024-2025 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */

namespace J2Commerce\Library\J2Commerce\Field;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Joomla\CMS\Factory;
use Joomla\CMS\Form\FormField;
use Joomla\CMS\Layout\FileLayout;
use Joomla\Database\ParameterType;

/**
 * Base class for modal multi-select fields
 *
 * Provides common functionality for fields that allow selection of multiple items
 * through a modal interface.
 *
 * @since  1.0.0
 */
class ModalMultiSelectField extends FormField
{
    /**
     * The form field type.
     *
     * @var    string
     * @since  1.0.0
     */
    protected $type = 'ModalMultiSelect';

    /**
     * Layout to render
     *
     * @var    string
     * @since  1.0.0
     */
    protected $layout = 'form.field.modal-multiselect';

    /**
     * Enabled actions: select, clear, edit, new
     *
     * @var    boolean[]
     * @since  1.0.0
     */
    protected $canDo = [];

    /**
     * Urls for modal: select, edit, new
     *
     * @var    string[]
     * @since  1.0.0
     */
    protected $urls = [];

    /**
     * List of titles for each modal type: select, edit, new
     *
     * @var    string[]
     * @since  1.0.0
     */
    protected $modalTitles = [];

    /**
     * List of icons for each button type: select, edit, new
     *
     * @var    string[]
     * @since  1.0.0
     */
    protected $buttonIcons = [];

    /**
     * The table name to select the title related to the field value.
     *
     * @var     string
     * @since   1.0.0
     */
    protected $sql_title_table = '';

    /**
     * The column name in the $sql_title_table, to select the title related to the field value.
     *
     * @var     string
     * @since   1.0.0
     */
    protected $sql_title_column = '';

    /**
     * The key name in the $sql_title_table that represent the field value, to select the title related to the field value.
     *
     * @var     string
     * @since   1.0.0
     */
    protected $sql_title_key = '';

    /**
     * Database driver instance
     *
     * @var    \Joomla\Database\DatabaseInterface
     * @since  1.0.0
     */
    protected $database;

    /**
     * Method to attach a Form object to the field.
     *
     * @param   \SimpleXMLElement  $element  The SimpleXMLElement object representing the `<field>` tag for the form field object.
     * @param   mixed              $value    The form field value to validate.
     * @param   string             $group    The field name group control value.
     *
     * @return  boolean  True on success.
     *
     * @see     FormField::setup()
     * @since   1.0.0
     */
    public function setup(\SimpleXMLElement $element, $value, $group = null)
    {
        $result = parent::setup($element, $value, $group);

        if (!$result) {
            return $result;
        }

        // Prepare enabled actions
        $this->__set('select', (string) $this->element['select'] != 'false');
        $this->__set('clear', (string) $this->element['clear'] != 'false');

        // Prepare Urls and titles
        foreach (
            ['urlSelect', 'titleSelect', 'iconSelect',
                'sql_title_table', 'sql_title_column', 'sql_title_key',] as $attr
        ) {
            $this->__set($attr, (string) $this->element[$attr]);
        }

        // Load the library language
        Factory::getApplication()->getLanguage()->load('lib_j2commerce', JPATH_SITE);

        return $result;
    }

    /**
     * Method to get certain otherwise inaccessible properties from the form field object.
     *
     * @param   string  $name  The property name for which to get the value.
     *
     * @return  mixed  The property value or null.
     *
     * @since   1.0.0
     */
    public function __get($name)
    {
        switch ($name) {
            case 'select':
                return $this->canDo['select'] ?? true;
            case 'clear':
                return $this->canDo['clear'] ?? true;
            case 'urlSelect':
                return $this->urls['select'] ?? '';
            case 'titleSelect':
                return $this->modalTitles['select'] ?? '';
            case 'iconSelect':
                return $this->buttonIcons['select'] ?? '';
            case 'sql_title_table':
            case 'sql_title_column':
            case 'sql_title_key':
                return $this->$name;
            default:
                return parent::__get($name);
        }
    }

    /**
     * Method to set certain otherwise inaccessible properties of the form field object.
     *
     * @param   string  $name   The property name for which to set the value.
     * @param   mixed   $value  The value of the property.
     *
     * @return  void
     *
     * @since   1.0.0
     */
    public function __set($name, $value)
    {
        switch ($name) {
            case 'select':
                $this->canDo['select'] = (bool) $value;
                break;
            case 'clear':
                $this->canDo['clear'] = (bool) $value;
                break;
            case 'urlSelect':
                $this->urls['select'] = (string) $value;
                break;
            case 'titleSelect':
                $this->modalTitles['select'] = (string) $value;
                break;
            case 'iconSelect':
                $this->buttonIcons['select'] = (string) $value;
                break;
            case 'sql_title_table':
            case 'sql_title_column':
            case 'sql_title_key':
                $this->$name = (string) $value;
                break;
            default:
                parent::__set($name, $value);
        }
    }

    /**
     * Method to get the field input markup.
     *
     * @return  string  The field input markup.
     *
     * @since   1.0.0
     */
    protected function getInput()
    {
        if (empty($this->layout)) {
            throw new \UnexpectedValueException(\sprintf('%s has no layout assigned.', $this->name));
        }

        // Get the layout data
        if (method_exists('\Joomla\CMS\Form\FormField', 'collectLayoutData')) {
            $data = $this->collectLayoutData(); // Joomla 5.1+
        } else {
            $data = $this->getLayoutData();
        }

        return $this->getRenderer($this->layout)->render($data);
    }

    /**
     * Method to retrieve the selected items.
     *
     * @return string
     *
     * @since   1.0.0
     */
    protected function getValueTitles()
    {
        $items  = [];
        $values = (array) $this->value;

        // Selecting the title for the field value, when required info were given
        if (!empty($values) && $this->sql_title_table && $this->sql_title_column && $this->sql_title_key) {
            try {
                $db    = $this->getDatabase();
                $query = $db->getQuery(true)
                    ->select($db->quoteName([$this->sql_title_key, $this->sql_title_column]))
                    ->from($db->quoteName($this->sql_title_table))
                    ->whereIn($db->quoteName($this->sql_title_key), $values, ParameterType::INTEGER);
                $db->setQuery($query);

                $items = $db->loadObjectList($this->sql_title_key);
            } catch (\Throwable $e) {
                Factory::getApplication()->enqueueMessage($e->getMessage(), 'error');
            }
        }

        return $items;
    }

    /**
     * Method to get the data to be passed to the layout for rendering.
     *
     * @return  array
     *
     * @since 1.0.0
     */
    protected function getLayoutData()
    {
        $data                = parent::getLayoutData();
        $data['canDo']       = $this->canDo;
        $data['urls']        = $this->urls;
        $data['modalTitles'] = $this->modalTitles;
        $data['buttonIcons'] = $this->buttonIcons;

        return $data;
    }

    /**
     * Get the renderer
     *
     * @param   string  $layoutId  Id to load
     *
     * @return  FileLayout
     *
     * @since   1.0.0
     */
    protected function getRenderer($layoutId = 'default')
    {
        $renderer = new FileLayout($layoutId);

        // 1. Template overrides (admin templates)
        $app = Factory::getApplication();
        if ($app->isClient('administrator')) {
            $template = $app->getTemplate();
            $renderer->addIncludePath(JPATH_ADMINISTRATOR . '/templates/' . $template . '/html');
        }

        // 2. Library layouts as fallback
        $renderer->addIncludePath(JPATH_LIBRARIES . '/j2commerce/layouts');

        return $renderer;
    }
}
