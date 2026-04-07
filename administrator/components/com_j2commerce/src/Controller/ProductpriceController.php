<?php

/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace J2Commerce\Component\J2commerce\Administrator\Controller;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\FormController;
use Joomla\CMS\Session\Session;
use Joomla\Database\ParameterType;

/**
 * Product Price item controller class.
 *
 * Handles single-item operations for product pricing (advanced pricing modal).
 *
 * @since  6.0.0
 */
class ProductpriceController extends FormController
{
    /**
     * The URL option for the component.
     *
     * @var    string
     * @since  6.0.0
     */
    protected $option = 'com_j2commerce';

    /**
     * The URL view item variable.
     *
     * @var    string
     * @since  6.0.0
     */
    protected $view_item = 'productprice';

    /**
     * The URL view list variable.
     *
     * @var    string
     * @since  6.0.0
     */
    protected $view_list = 'productprices';

    /**
     * The prefix to use with controller messages.
     *
     * @var    string
     * @since  6.0.0
     */
    protected $text_prefix = 'COM_J2COMMERCE_PRODUCTPRICE';

    /**
     * Method to edit an existing record.
     *
     * @param   string  $key     The name of the primary key of the URL variable.
     * @param   string  $urlVar  The name of the URL variable for the id.
     *
     * @return  boolean  True if access level check passes, false otherwise.
     *
     * @since   6.0.0
     */
    public function edit($key = null, $urlVar = 'id')
    {
        return parent::edit($key, $urlVar);
    }

    /**
     * Method to save a record.
     *
     * @param   string  $key     The name of the primary key of the URL variable.
     * @param   string  $urlVar  The name of the URL variable for the id.
     *
     * @return  boolean  True if successful, false otherwise.
     *
     * @since   6.0.0
     */
    public function save($key = null, $urlVar = 'id')
    {
        return parent::save($key, $urlVar);
    }

    /**
     * Method to cancel an edit.
     *
     * @param   string  $key  The name of the primary key of the URL variable.
     *
     * @return  boolean  True if access level checks pass, false otherwise.
     *
     * @since   6.0.0
     */
    public function cancel($key = 'id')
    {
        return parent::cancel($key);
    }

    /**
     * Gets the URL arguments to append to a redirect.
     *
     * Override to preserve variant_id when redirecting after save.
     *
     * @param   integer  $recordId  The primary key id for the item.
     * @param   string   $urlVar    The name of the URL variable for the id.
     *
     * @return  string  The URL arguments string to append.
     *
     * @since   6.0.0
     */
    protected function getRedirectToItemAppend($recordId = null, $urlVar = 'id')
    {
        $append = parent::getRedirectToItemAppend($recordId, $urlVar);

        // Preserve variant_id and layout for modal view
        $variantId = $this->input->getInt('variant_id', 0);
        $tmpl      = $this->input->get('tmpl', '');
        $layout    = $this->input->get('layout', '');

        if ($variantId) {
            $append .= '&variant_id=' . $variantId;
        }

        if ($tmpl) {
            $append .= '&tmpl=' . $tmpl;
        }

        if ($layout) {
            $append .= '&layout=' . $layout;
        }

        return $append;
    }

    /**
     * Gets the URL arguments to append to a list redirect.
     *
     * Override to close modal after save instead of redirecting to list.
     *
     * @return  string  The URL arguments string to append.
     *
     * @since   6.0.0
     */
    protected function getRedirectToListAppend()
    {
        $append = parent::getRedirectToListAppend();

        // Preserve tmpl for component mode
        $tmpl = $this->input->get('tmpl', '');
        if ($tmpl) {
            $append .= '&tmpl=' . $tmpl;
        }

        return $append;
    }

    /**
     * Create a new product price via AJAX.
     *
     * @return  void
     *
     * @since   6.0.0
     */
    public function createprice(): void
    {
        // Check for request forgeries
        if (!Session::checkToken()) {
            $this->sendJsonResponse(false, Text::_('JINVALID_TOKEN'));
            return;
        }

        $app  = Factory::getApplication();
        $data = $this->input->get('jform', [], 'array');

        // Validate required fields
        $variantId = (int) ($data['variant_id'] ?? 0);
        $price     = (float) ($data['price'] ?? 0);

        if (!$variantId) {
            $this->sendJsonResponse(false, Text::_('COM_J2COMMERCE_ERROR_INVALID_VARIANT'));
            return;
        }

        // Prepare data for insert
        $db = Factory::getContainer()->get('DatabaseDriver');

        $insertData = (object) [
            'variant_id'        => $variantId,
            'quantity_from'     => !empty($data['quantity_from']) ? (float) $data['quantity_from'] : null,
            'quantity_to'       => !empty($data['quantity_to']) ? (float) $data['quantity_to'] : null,
            'date_from'         => $this->convertDateToMysql($data['date_from'] ?? ''),
            'date_to'           => $this->convertDateToMysql($data['date_to'] ?? ''),
            'customer_group_id' => !empty($data['customer_group_id']) ? (int) $data['customer_group_id'] : null,
            'price'             => $price,
        ];

        try {
            $db->insertObject('#__j2commerce_product_prices', $insertData, 'j2commerce_productprice_id');
            $newId = $db->insertid();

            $this->sendJsonResponse(true, Text::_('COM_J2COMMERCE_PRICE_CREATED'), ['id' => $newId]);
        } catch (\Exception $e) {
            $this->sendJsonResponse(false, $e->getMessage());
        }
    }

    /**
     * Save multiple product prices via AJAX.
     *
     * @return  void
     *
     * @since   6.0.0
     */
    public function saveprices(): void
    {
        // Check for request forgeries
        if (!Session::checkToken()) {
            $this->sendJsonResponse(false, Text::_('JINVALID_TOKEN'));
            return;
        }

        $app    = Factory::getApplication();
        $prices = $this->input->get('jform', [], 'array')['prices'] ?? [];

        if (empty($prices)) {
            $this->sendJsonResponse(false, Text::_('COM_J2COMMERCE_ERROR_NO_PRICES'));
            return;
        }

        $db     = Factory::getContainer()->get('DatabaseDriver');
        $saved  = 0;
        $errors = [];

        foreach ($prices as $priceId => $priceData) {
            $priceId = (int) $priceId;

            if ($priceId <= 0) {
                continue;
            }

            // Verify the price exists
            $query = $db->getQuery(true)
                ->select('j2commerce_productprice_id')
                ->from($db->quoteName('#__j2commerce_product_prices'))
                ->where($db->quoteName('j2commerce_productprice_id') . ' = :id')
                ->bind(':id', $priceId, ParameterType::INTEGER);

            $db->setQuery($query);

            if (!$db->loadResult()) {
                $errors[] = Text::sprintf('COM_J2COMMERCE_ERROR_PRICE_NOT_FOUND', $priceId);
                continue;
            }

            // Prepare update data
            $updateData = (object) [
                'j2commerce_productprice_id' => $priceId,
                'quantity_from'              => isset($priceData['quantity_from']) ? (float) $priceData['quantity_from'] : null,
                'quantity_to'                => isset($priceData['quantity_to']) ? (float) $priceData['quantity_to'] : null,
                'date_from'                  => $this->convertDateToMysql($priceData['date_from'] ?? ''),
                'date_to'                    => $this->convertDateToMysql($priceData['date_to'] ?? ''),
                'customer_group_id'          => isset($priceData['customer_group_id']) ? (int) $priceData['customer_group_id'] : null,
                'price'                      => isset($priceData['price']) ? (float) $priceData['price'] : 0,
            ];

            try {
                $db->updateObject('#__j2commerce_product_prices', $updateData, 'j2commerce_productprice_id');
                $saved++;
            } catch (\Exception $e) {
                $errors[] = Text::sprintf('COM_J2COMMERCE_ERROR_SAVING_PRICE', $priceId) . ': ' . $e->getMessage();
            }
        }

        if (!empty($errors)) {
            $this->sendJsonResponse(false, implode("\n", $errors), ['saved' => $saved]);
        } else {
            $this->sendJsonResponse(true, Text::plural('COM_J2COMMERCE_N_PRICES_SAVED', $saved), ['saved' => $saved]);
        }
    }

    /**
     * Remove a product price via AJAX.
     *
     * @return  void
     *
     * @since   6.0.0
     */
    public function removeprice(): void
    {
        // Check for request forgeries
        if (!Session::checkToken()) {
            $this->sendJsonResponse(false, Text::_('JINVALID_TOKEN'));
            return;
        }

        $priceId = $this->input->getInt('productprice_id', 0);

        if (!$priceId) {
            $this->sendJsonResponse(false, Text::_('COM_J2COMMERCE_ERROR_INVALID_PRICE_ID'));
            return;
        }

        $db = Factory::getContainer()->get('DatabaseDriver');

        try {
            $query = $db->getQuery(true)
                ->delete($db->quoteName('#__j2commerce_product_prices'))
                ->where($db->quoteName('j2commerce_productprice_id') . ' = :id')
                ->bind(':id', $priceId, ParameterType::INTEGER);

            $db->setQuery($query);
            $db->execute();

            if ($db->getAffectedRows() > 0) {
                $this->sendJsonResponse(true, Text::_('COM_J2COMMERCE_PRICE_REMOVED'));
            } else {
                $this->sendJsonResponse(false, Text::_('COM_J2COMMERCE_ERROR_PRICE_NOT_FOUND'));
            }
        } catch (\Exception $e) {
            $this->sendJsonResponse(false, $e->getMessage());
        }
    }

    /**
     * Convert date from form format to MySQL format.
     *
     * Handles conversion from Joomla calendar field format (d-m-Y H:i:s)
     * to MySQL datetime format (Y-m-d H:i:s). Returns null for empty dates.
     *
     * @param   string  $dateString  The date string from the form
     *
     * @return  string|null  MySQL formatted date or null if empty
     *
     * @since   6.0.0
     */
    protected function convertDateToMysql(string $dateString): ?string
    {
        // Return null for empty dates
        if (empty(trim($dateString))) {
            return null;
        }

        // Try to parse the date from form format (d-m-Y H:i:s)
        $date = \DateTime::createFromFormat('d-m-Y H:i:s', $dateString);

        if ($date === false) {
            // Try alternative format without time
            $date = \DateTime::createFromFormat('d-m-Y', $dateString);
        }

        if ($date === false) {
            // If still can't parse, try MySQL format in case it's already correct
            $date = \DateTime::createFromFormat('Y-m-d H:i:s', $dateString);
        }

        if ($date === false) {
            // Return null if we can't parse the date
            return null;
        }

        // Return in MySQL format
        return $date->format('Y-m-d H:i:s');
    }

    /**
     * Send a JSON response.
     *
     * @param   bool    $success  Success status
     * @param   string  $message  Response message
     * @param   array   $data     Additional data
     *
     * @return  void
     *
     * @since   6.0.0
     */
    protected function sendJsonResponse(bool $success, string $message, array $data = []): void
    {
        $app = Factory::getApplication();

        $response = [
            'success' => $success,
            'message' => $message,
            'data'    => $data,
        ];

        $app->setHeader('Content-Type', 'application/json; charset=utf-8');
        $app->sendHeaders();

        echo json_encode($response);

        $app->close();
    }
}
