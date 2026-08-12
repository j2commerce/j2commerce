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

use J2Commerce\Component\J2commerce\Administrator\Helper\J2CommerceHelper;
use J2Commerce\Component\J2commerce\Administrator\Model\CustomerModel;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Log\Log;
use Joomla\CMS\MVC\Controller\FormController;
use Joomla\CMS\Session\Session;

/**
 * Customer controller class for single customer editing.
 *
 * @since  6.0.7
 */
class CustomerController extends FormController
{
    use WriteAccessTrait;

    protected string $writeAction = 'j2commerce.editorders';

    /** ajaxGetAddressForm renders the address form for reading; it carries its own vieworders gate. */
    protected function readTasks(): array
    {
        return [
            'display',
            'ajaxGetAddressForm',
        ];
    }

    /**
     * The prefix to use with controller messages.
     *
     * @var    string
     * @since  6.0.7
     */
    protected $text_prefix = 'COM_J2COMMERCE_CUSTOMER';

    /**
     * The primary key name for the customer table.
     *
     * Maps 'id' URL parameter to 'j2commerce_address_id' table column.
     *
     * @var    string
     * @since  6.0.7
     */
    protected $key = 'j2commerce_address_id';

    public function edit($key = null, $urlVar = 'id')
    {
        return parent::edit($key, $urlVar);
    }

    public function save($key = null, $urlVar = 'id')
    {
        return parent::save($key, $urlVar);
    }

    public function cancel($key = 'id')
    {
        return parent::cancel($key);
    }

    /**
     * AJAX: render the Customer Address modal form fragment.
     *
     * Input: id (address PK, 0 for add), user_id (target user when adding).
     * Returns raw HTML (form fragment).
     *
     * @return  void
     *
     * @since   6.0.8
     */
    public function ajaxGetAddressForm(): void
    {
        $app = Factory::getApplication();

        if (!Session::checkToken('get')) {
            // close() is exit(), so respond() never runs — flush the status now or it ships 200.
            $app->setHeader('status', 403, true);
            $app->sendHeaders();
            echo Text::_('JINVALID_TOKEN');
            $app->close();
        }

        if (!J2CommerceHelper::canAccess('j2commerce.vieworders')) {
            $app->setHeader('status', 403, true);
            $app->sendHeaders();
            echo Text::_('JLIB_APPLICATION_ERROR_ACCESS_FORBIDDEN');
            $app->close();
        }

        $input     = $app->getInput();
        $addressId = $input->getInt('id', 0);
        $userId    = $input->getInt('user_id', 0);

        /** @var CustomerModel $model */
        $model = $this->getModel('Customer');
        $form  = $model->getForm([], false);

        $data = [];

        if ($addressId > 0) {
            $row = $model->getAddressForCard($addressId);

            if ($row) {
                $data = (array) $row;
            }
        } else {
            $data['j2commerce_address_id'] = 0;
            $data['user_id']               = $userId;
            $data['type']                  = 'billing';
        }

        if ($form) {
            $form->bind($data);
        }

        // Render the modal form fragment.
        $app->setHeader('Content-Type', 'text/html; charset=UTF-8', true);

        $customFields = $model->getAddressCustomFields();

        // Core fields are rendered explicitly; filter them out of the custom loop.
        $coreFields = [
            'first_name', 'last_name', 'email', 'company', 'type',
            'address_1', 'address_2', 'city', 'zip',
            'country_id', 'zone_id', 'phone_1', 'phone_2', 'tax_number',
        ];

        ob_start();
        require JPATH_ADMINISTRATOR . '/components/com_j2commerce/tmpl/customer/modal_form.php';
        echo ob_get_clean();

        $app->close();
    }

    /**
     * AJAX: save an address row (create or update).
     *
     * @return  void
     *
     * @since   6.0.8
     */
    public function ajaxSaveAddress(): void
    {
        $app = Factory::getApplication();
        $app->setHeader('Content-Type', 'application/json; charset=UTF-8', true);

        if (!Session::checkToken('post')) {
            echo json_encode(['success' => false, 'message' => Text::_('JINVALID_TOKEN')]);
            $app->close();
        }

        if (!J2CommerceHelper::canAccess('j2commerce.editorders')) {
            echo json_encode(['success' => false, 'message' => Text::_('JLIB_APPLICATION_ERROR_ACCESS_FORBIDDEN')]);
            $app->close();
        }

        $input = $app->getInput();
        $data  = $input->post->get('jform', [], 'array');

        if (empty($data)) {
            echo json_encode(['success' => false, 'message' => Text::_('COM_J2COMMERCE_CUSTOMER_ADDRESSES_SAVE_ERROR')]);
            $app->close();
        }

        /** @var CustomerModel $model */
        $model = $this->getModel('Customer');
        $row   = $model->saveAddressRow($data);

        if (!$row) {
            Log::add('customer.saveAddressRow failed: ' . $model->getError(), Log::ERROR, 'com_j2commerce');
            echo json_encode([
                'success' => false,
                'message' => Text::_('COM_J2COMMERCE_CUSTOMER_ADDRESSES_SAVE_ERROR'),
            ]);
            $app->close();
        }

        echo json_encode([
            'success' => true,
            'message' => Text::_('COM_J2COMMERCE_CUSTOMER_ADDRESSES_SAVE_SUCCESS'),
            'address' => $row,
        ]);
        $app->close();
    }

    /**
     * AJAX: re-link every address that belongs to the current user to a new Joomla user.
     *
     * Card mode replaces the page-level Save buttons with AJAX, so the user_id sidebar
     * picker calls this endpoint when the store owner picks a different user.
     *
     * @return  void
     *
     * @since   6.0.8
     */
    public function ajaxRelinkUser(): void
    {
        $app = Factory::getApplication();
        $app->setHeader('Content-Type', 'application/json; charset=UTF-8', true);

        if (!Session::checkToken('post')) {
            echo json_encode(['success' => false, 'message' => Text::_('JINVALID_TOKEN')]);
            $app->close();
        }

        if (!J2CommerceHelper::canAccess('j2commerce.editorders')) {
            echo json_encode(['success' => false, 'message' => Text::_('JLIB_APPLICATION_ERROR_ACCESS_FORBIDDEN')]);
            $app->close();
        }

        $input     = $app->getInput();
        $oldUserId = $input->post->getInt('old_user_id', 0);
        $newUserId = $input->post->getInt('new_user_id', 0);

        if ($oldUserId <= 0 || $oldUserId === $newUserId) {
            echo json_encode([
                'success' => false,
                'message' => Text::_('COM_J2COMMERCE_CUSTOMER_USER_RELINK_INVALID'),
            ]);
            $app->close();
        }

        /** @var CustomerModel $model */
        $model = $this->getModel('Customer');

        if (!$model->relinkUser($oldUserId, $newUserId)) {
            Log::add('customer.relinkUser failed: ' . $model->getError(), Log::ERROR, 'com_j2commerce');
            echo json_encode([
                'success' => false,
                'message' => Text::_('COM_J2COMMERCE_CUSTOMER_USER_RELINK_ERROR'),
            ]);
            $app->close();
        }

        echo json_encode([
            'success' => true,
            'message' => Text::_('COM_J2COMMERCE_CUSTOMER_USER_RELINK_SUCCESS'),
        ]);
        $app->close();
    }

    /**
     * AJAX: delete an address row.
     *
     * @return  void
     *
     * @since   6.0.8
     */
    public function ajaxDeleteAddress(): void
    {
        $app = Factory::getApplication();
        $app->setHeader('Content-Type', 'application/json; charset=UTF-8', true);

        if (!Session::checkToken('post')) {
            echo json_encode(['success' => false, 'message' => Text::_('JINVALID_TOKEN')]);
            $app->close();
        }

        if (!J2CommerceHelper::canAccess('j2commerce.editorders')) {
            echo json_encode(['success' => false, 'message' => Text::_('JLIB_APPLICATION_ERROR_ACCESS_FORBIDDEN')]);
            $app->close();
        }

        $addressId = $app->getInput()->post->getInt('id', 0);

        /** @var CustomerModel $model */
        $model = $this->getModel('Customer');

        if (!$model->deleteAddressRow($addressId)) {
            Log::add('customer.deleteAddressRow failed: ' . $model->getError(), Log::ERROR, 'com_j2commerce');
            echo json_encode([
                'success' => false,
                'message' => Text::_('COM_J2COMMERCE_CUSTOMER_ADDRESSES_DELETE_ERROR'),
            ]);
            $app->close();
        }

        echo json_encode([
            'success' => true,
            'message' => Text::_('COM_J2COMMERCE_CUSTOMER_ADDRESSES_DELETE_SUCCESS'),
        ]);
        $app->close();
    }
}
