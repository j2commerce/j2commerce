<?php

/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace J2Commerce\Component\J2commerce\Administrator\Field\Modal;

use Joomla\CMS\Factory;
use Joomla\CMS\Form\Field\ModalSelectField;
use Joomla\CMS\Language\LanguageHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\FileLayout;
use Joomla\CMS\Session\Session;
use Joomla\CMS\Uri\Uri;
use Joomla\Database\ParameterType;

\defined('_JEXEC') or die;

/**
 * Supports a modal product picker.
 *
 * @since  6.0.0
 */
class ProductField extends ModalSelectField
{
    /**
     * The form field type.
     *
     * @var    string
     *
     * @since  6.0.0
     */
    protected $type = 'Modal_Product';

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
     *
     * @since   6.0.0
     */
    public function setup(\SimpleXMLElement $element, $value, $group = null)
    {
        // Check if the value consist with id:alias, extract the id only
        if ($value && str_contains($value, ':')) {
            [$id]  = explode(':', $value, 2);
            $value = (int) $id;
        }

        $result = parent::setup($element, $value, $group);

        if (!$result) {
            return $result;
        }

        Factory::getApplication()->getLanguage()->load('com_j2commerce', JPATH_ADMINISTRATOR);

        $languages = LanguageHelper::getContentLanguages([0, 1], false);
        $language  = (string) $this->element['language'];

        // Prepare enabled actions
        $this->canDo['propagate']  = ((string) $this->element['propagate'] == 'true') && \count($languages) > 2;

        // Prepare Urls
        $linkProducts = (new Uri())->setPath(Uri::base(true) . '/index.php');
        $linkProducts->setQuery([
            'option'                => 'com_j2commerce',
            'view'                  => 'products',
            'layout'                => 'modal',
            'tmpl'                  => 'component',
            Session::getFormToken() => 1,
        ]);

        if ($language) {
            $linkProducts->setVar('forcedLanguage', $language);

            $modalTitle = Text::_('COM_J2COMMERCE_SELECT_A_PRODUCT') . ' &#8212; ' . $this->getTitle();

            $this->dataAttributes['data-language'] = $language;
        } else {
            $modalTitle = Text::_('COM_J2COMMERCE_SELECT_A_PRODUCT');
        }

        $this->urls['select']  = (string) $linkProducts;

        // Prepare titles
        $this->modalTitles['select']  = $modalTitle;

        $this->hint = $this->hint ?: Text::_('COM_J2COMMERCE_SELECT_A_PRODUCT');

        return $result;
    }

    /**
     * Method to retrieve the title of selected item.
     *
     * @return string
     *
     * @since   6.0.0
     */
    protected function getValueTitle()
    {
        $value = (int) $this->value ?: '';
        $title = '';

        if ($value) {
            try {
                $db    = $this->getDatabase();
                $query = $db->getQuery(true);

                $query->select($db->quoteName('c.title'))
                    ->from($db->quoteName('#__j2commerce_products', 'p'))
                    ->join('LEFT', $db->quoteName('#__content', 'c'), $db->quoteName('p.product_source_id') . ' = ' . $db->quoteName('c.id'))
                    ->where($db->quoteName('p.j2commerce_product_id') . ' = :value')
                    ->where($db->quoteName('p.product_source') . ' = ' . $db->quote('com_content'))
                    ->bind(':value', $value, ParameterType::INTEGER);

                $db->setQuery($query);

                $title = $db->loadResult();
            } catch (\Throwable $e) {
                Factory::getApplication()->enqueueMessage($e->getMessage(), 'error');
            }
        }

        return $title ?: $value;
    }

    /**
     * Method to get the data to be passed to the layout for rendering.
     *
     * @return  array
     *
     * @since 6.0.0
     */
    protected function getLayoutData()
    {
        $data             = parent::getLayoutData();
        $data['language'] = (string) $this->element['language'];

        return $data;
    }

    /**
     * Get the renderer
     *
     * @param   string  $layoutId  Id to load
     *
     * @return  FileLayout
     *
     * @since   6.0.0
     */
    protected function getRenderer($layoutId = 'default')
    {
        $layout = parent::getRenderer($layoutId);
        $layout->setComponent('com_j2commerce');
        $layout->setClient(1);

        return $layout;
    }
}
