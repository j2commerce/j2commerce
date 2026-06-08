<?php

/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace J2Commerce\Component\J2commerce\Administrator\Controller;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\AdminController;
use Joomla\CMS\Response\JsonResponse;

/**
 * Inventory Controller
 *
 * @since  6.0.0
 */
class InventoryController extends AdminController
{
    /**
     * The default view for the display method.
     *
     * @var    string
     * @since  6.0.0
     */
    protected $default_view = 'inventory';

    /**
     * Method to get a model object, loading it if required.
     *
     * @param   string  $name    The model name. Optional.
     * @param   string  $prefix  The class prefix. Optional.
     * @param   array   $config  Configuration array for model. Optional.
     *
     * @return  \Joomla\CMS\MVC\Model\BaseDatabaseModel  The model.
     *
     * @since   6.0.0
     */
    public function getModel($name = 'Inventory', $prefix = 'Administrator', $config = [])
    {
        return parent::getModel($name, $prefix, $config);
    }

    /**
     * AJAX method to save individual inventory item
     *
     * @return  void
     *
     * @since   6.0.0
     */
    public function saveItem()
    {
        // Check for request forgeries
        $this->checkToken();

        try {
            $app   = Factory::getApplication();
            $input = $app->getInput();

            // Get the data from the request
            $productId    = $input->getInt('product_id', 0);
            $variantId    = $input->getInt('variant_id', 0);
            $quantity     = $input->getInt('quantity', 0);
            $manageStock  = $input->getInt('manage_stock', 0);
            $availability = $input->getInt('availability', 0);

            // Validate required data
            if (!$productId || !$variantId) {
                throw new \Exception(Text::_('COM_J2COMMERCE_INVENTORY_ERROR_MISSING_DATA'));
            }

            // Get the model
            $model = $this->getModel();

            // Save the data
            $result = $model->saveInventoryItem($productId, $variantId, $quantity, $manageStock, $availability);

            if ($result) {
                $message = Text::_('COM_J2COMMERCE_INVENTORY_SAVE_SUCCESS');
                $app->enqueueMessage($message, 'message');

                echo new JsonResponse(['success' => true, 'message' => $message]);
            } else {
                throw new \Exception(Text::_('COM_J2COMMERCE_INVENTORY_SAVE_ERROR'));
            }

        } catch (\Exception $e) {
            $app     = Factory::getApplication();
            $message = Text::sprintf('COM_J2COMMERCE_INVENTORY_SAVE_FAILED', $e->getMessage());
            $app->enqueueMessage($message, 'error');

            echo new JsonResponse(['success' => false, 'message' => $message]);
        }

        $app->close();
    }

    /**
     * Batch-update inventory for the checked products. Applies only the fields
     * the user enabled in the modal; the change cascades to every variant of
     * each selected product.
     *
     * @return  void
     *
     * @since   6.0.0
     */
    public function batch(): void
    {
        $this->checkToken();

        $app   = Factory::getApplication();
        $input = $app->getInput();

        try {
            if (!$app->getIdentity()->authorise('core.edit', 'com_j2commerce')) {
                throw new \Exception(Text::_('JLIB_APPLICATION_ERROR_ACCESS_FORBIDDEN'));
            }

            $pks = (array) $input->post->get('cid', [], 'int');

            if (empty($pks)) {
                throw new \Exception(Text::_('COM_J2COMMERCE_INVENTORY_BATCH_NO_SELECTION'));
            }

            $fields = [];

            if ($input->post->getInt('apply_quantity', 0) === 1) {
                $fields['quantity'] = $input->post->getInt('batch_quantity', 0);
            }

            if ($input->post->getInt('apply_manage_stock', 0) === 1) {
                $fields['manage_stock'] = $input->post->getInt('batch_manage_stock', 0);
            }

            if ($input->post->getInt('apply_availability', 0) === 1) {
                $fields['availability'] = $input->post->getInt('batch_availability', 1);
            }

            if (empty($fields)) {
                throw new \Exception(Text::_('COM_J2COMMERCE_INVENTORY_BATCH_NO_FIELDS'));
            }

            $count   = $this->getModel()->batchUpdate($pks, $fields);
            $message = Text::sprintf('COM_J2COMMERCE_INVENTORY_BATCH_SUCCESS', $count);
            $app->enqueueMessage($message, 'message');

            echo new JsonResponse(['success' => true, 'message' => $message]);
        } catch (\Exception $e) {
            echo new JsonResponse(['success' => false, 'message' => $e->getMessage()]);
        }

        $app->close();
    }

    /**
     * Method to check if you can add a new record.
     *
     * @param   array  $data  An array of input data.
     *
     * @return  boolean
     *
     * @since   6.0.0
     */
    protected function allowAdd($data = [])
    {
        return Factory::getApplication()->getIdentity()->authorise('core.create', $this->option);
    }

    /**
     * Method to check if you can edit a record.
     *
     * @param   array   $data  An array of input data.
     * @param   string  $key   The name of the key for the primary key.
     *
     * @return  boolean
     *
     * @since   6.0.0
     */
    protected function allowEdit($data = [], $key = 'id')
    {
        return Factory::getApplication()->getIdentity()->authorise('core.edit', $this->option);
    }
}
