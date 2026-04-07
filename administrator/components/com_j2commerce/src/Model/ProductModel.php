<?php

/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace J2Commerce\Component\J2commerce\Administrator\Model;

\defined('_JEXEC') or die;

use J2Commerce\Component\J2commerce\Administrator\Helper\ProductHelper;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Form\Form;
use Joomla\CMS\MVC\Model\AdminModel;

/**
 * Product item model class.
 *
 * @since  6.0.3
 */
class ProductModel extends AdminModel
{
    /**
     * The type alias for this content type.
     *
     * @var    string
     * @since  6.0.3
     */
    public $typeAlias = 'com_j2commerce.product';

    /**
     * The prefix to use with controller messages.
     *
     * @var    string
     * @since  6.0.3
     */
    protected $text_prefix = 'COM_J2COMMERCE_PRODUCT';

    /**
     * Method to get the row form.
     *
     * @param   array    $data      Data for the form.
     * @param   boolean  $loadData  True if the form is to load its own data (default case), false if not.
     *
     * @return  Form|boolean  A Form object on success, false on failure
     *
     * @since   6.0.3
     */
    public function getForm($data = [], $loadData = true)
    {
        $form = $this->loadForm(
            'com_j2commerce.product',
            'product',
            ['control' => 'jform', 'load_data' => $loadData]
        );

        if (empty($form)) {
            return false;
        }

        return $form;
    }

    /**
     * Method to get the data that should be injected in the form.
     *
     * @return  mixed  The data for the form.
     *
     * @since   6.0.3
     */
    protected function loadFormData()
    {
        $data = Factory::getApplication()->getUserState('com_j2commerce.edit.product.data', []);

        if (empty($data)) {
            $data = $this->getItem();
        }

        return $data;
    }

    /**
     * Method to get a single record with full hydrated data.
     *
     * Uses ProductHelper::getFullProduct() to load all related data including:
     * - manufacturer name
     * - product images
     * - article data (product_name, product_short_desc, product_long_desc)
     * - variants
     * - product options
     *
     * @param   integer  $pk  The id of the primary key.
     *
     * @return  object|boolean  Object on success, false on failure.
     *
     * @since   6.0.3
     * @since   6.0.8 Uses ProductHelper::getFullProduct() for hydration
     */
    public function getItem($pk = null)
    {
        // Get base item from parent (needed for form binding and new records)
        $item = parent::getItem($pk);

        if (!$item || empty($item->j2commerce_product_id)) {
            return $item;
        }

        // Get fully hydrated product from ProductHelper
        $fullProduct = ProductHelper::getFullProduct((int) $item->j2commerce_product_id);

        if ($fullProduct) {
            // Merge hydrated data onto the base item
            // Keep base item properties for form binding compatibility
            foreach ($fullProduct as $key => $value) {
                if (!isset($item->$key)) {
                    $item->$key = $value;
                }
            }

            // Explicitly set hydrated properties (overwrite base if needed)
            $item->manufacturer          = $fullProduct->manufacturer ?? '';
            $item->product_name          = $fullProduct->product_name ?? '';
            $item->product_short_desc    = $fullProduct->product_short_desc ?? '';
            $item->product_long_desc     = $fullProduct->product_long_desc ?? '';
            $item->source                = $fullProduct->source ?? null;
            $item->main_image            = $fullProduct->main_image ?? '';
            $item->main_image_alt        = $fullProduct->main_image_alt ?? '';
            $item->thumb_image           = $fullProduct->thumb_image ?? '';
            $item->thumb_image_alt       = $fullProduct->thumb_image_alt ?? '';
            $item->additional_images     = $fullProduct->additional_images ?? '';
            $item->additional_images_alt = $fullProduct->additional_images_alt ?? '';
            $item->variants              = $fullProduct->variants ?? [];
            $item->product_options       = $fullProduct->product_options ?? [];
            $item->product_edit_url      = $fullProduct->product_edit_url ?? '';
            $item->product_view_url      = $fullProduct->product_view_url ?? '';
        }

        // Ensure JSON fields are encoded as strings for form binding
        // Hidden fields cannot accept arrays - they need string values
        if (isset($item->params) && (\is_array($item->params) || \is_object($item->params))) {
            $item->params = json_encode($item->params);
        }

        if (isset($item->plugins) && (\is_array($item->plugins) || \is_object($item->plugins))) {
            $item->plugins = json_encode($item->plugins);
        }

        return $item;
    }

    /**
     * Method to populate the state.
     *
     * CRITICAL: Override to read 'id' from URL instead of table's primary key name.
     *
     * @return  void
     *
     * @since   6.0.3
     */
    protected function populateState(): void
    {
        $app = Factory::getApplication();

        // Read from URL param 'id', NOT from the table's column name
        $pk = $app->getInput()->getInt('id', 0);
        $this->setState($this->getName() . '.id', $pk);

        $params = ComponentHelper::getParams('com_j2commerce');
        $this->setState('params', $params);
    }

    /**
     * Prepare and sanitize the table before saving.
     *
     * @param   \Joomla\CMS\Table\Table  $table  The Table object
     *
     * @return  void
     *
     * @since   6.0.3
     */
    protected function prepareTable($table): void
    {
        $date = Factory::getDate()->toSql();
        $user = Factory::getApplication()->getIdentity();

        if (empty($table->j2commerce_product_id)) {
            // New record
            if (empty($table->created_on)) {
                $table->created_on = $date;
            }
            if (empty($table->created_by)) {
                $table->created_by = $user->id;
            }
        }

        // Always update modified
        $table->modified_on = $date;
        $table->modified_by = $user->id;
    }
}
