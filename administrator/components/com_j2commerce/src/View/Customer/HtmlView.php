<?php

/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace J2Commerce\Component\J2commerce\Administrator\View\Customer;

\defined('_JEXEC') or die;

use J2Commerce\Component\J2commerce\Administrator\Helper\CustomFieldHelper;
use J2Commerce\Component\J2commerce\Administrator\Helper\J2CommerceHelper;
use J2Commerce\Component\J2commerce\Administrator\View\AdminAssetsTrait;
use Joomla\CMS\Factory;
use Joomla\CMS\Helper\ContentHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\GenericDataException;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\ToolbarHelper;

/**
 * Customer edit view class.
 *
 * @since  6.0.7
 */
class HtmlView extends BaseHtmlView
{
    use AdminAssetsTrait;
    /**
     * The form object.
     *
     * @var    \Joomla\CMS\Form\Form
     * @since  6.0.7
     */
    protected $form;

    /**
     * The active item.
     *
     * @var    object
     * @since  6.0.7
     */
    protected $item;

    /**
     * Model state object.
     *
     * @var    \Joomla\Registry\Registry
     * @since  6.0.7
     */
    protected $state;

    /**
     * All addresses linked to the customer's user account (card mode).
     *
     * @var    array
     * @since  6.0.8
     */
    public array $addresses = [];

    /**
     * Enabled custom address fields (rendered after tax_number).
     *
     * @var    array
     * @since  6.0.8
     */
    public array $addressCustomFields = [];

    /**
     * When true the Address tab renders the Bootstrap 5 card grid + AJAX modal.
     * When false the tab falls back to the traditional inline form (new record or guest).
     *
     * @var    bool
     * @since  6.0.8
     */
    public bool $useCardMode = false;

    /**
     * Display the view.
     *
     * @param   string  $tpl  The name of the template file to parse.
     *
     * @return  void
     *
     * @since   6.0.7
     */
    public function display($tpl = null): void
    {
        if (!J2CommerceHelper::canAccess('j2commerce.vieworders')) {
            throw new \Exception(Text::_('JLIB_APPLICATION_ERROR_ACCESS_FORBIDDEN'), 403);
        }

        $this->loadAdminAssets();

        /** @var \J2Commerce\Component\J2commerce\Administrator\Model\CustomerModel $model */
        $model = $this->getModel();

        $this->form  = $model->getForm();
        $this->item  = $model->getItem();
        $this->state = $model->getState();

        // Decide whether to render the Bootstrap 5 card grid or the inline form.
        // Registered customers resolve their address book by user_id; guest customers
        // (user_id = 0) fall back to lookup by email so they render the card grid the
        // same way — matching how CustomersModel groups the customer list by email.
        $userId    = (int) ($this->item->user_id ?? 0);
        $addressId = (int) ($this->item->j2commerce_address_id ?? 0);
        $email     = (string) ($this->item->email ?? '');

        if ($addressId > 0) {
            if ($userId > 0) {
                $this->addresses = $model->getAddressesByUser($userId);
            } elseif ($email !== '') {
                $this->addresses = $model->getAddressesByEmail($email);
            }

            $this->useCardMode = !empty($this->addresses);
        }

        $this->addressCustomFields = $model->getAddressCustomFields();

        // Ensure the phone-widget CSS + JS (including the MutationObserver that initialises
        // AJAX-injected .j2c-telephone-field elements) is registered on the main page so the
        // phone fields inside the modal get their country dropdown / flag styling.
        CustomFieldHelper::ensureTelephoneAssets();

        // Check for errors
        if (\count($errors = $this->get('Errors'))) {
            throw new GenericDataException(implode("\n", $errors), 500);
        }

        $this->addToolbar();

        parent::display($tpl);
    }

    /**
     * Add the page title and toolbar.
     *
     * @return  void
     *
     * @since   6.0.7
     */
    protected function addToolbar(): void
    {
        Factory::getApplication()->getInput()->set('hidemainmenu', true);

        $isNew      = ($this->item->j2commerce_address_id == 0);
        $canDo      = ContentHelper::getActions('com_j2commerce');
        $user       = Factory::getApplication()->getIdentity();
        $checkedOut = !empty($this->item->checked_out) && (int) $this->item->checked_out !== (int) $user->id;
        $toolbar    = $this->getDocument()->getToolbar();

        ToolbarHelper::title(
            $isNew ? Text::_('COM_J2COMMERCE_CUSTOMER_NEW') : Text::_('COM_J2COMMERCE_CUSTOMER_EDIT'),
            'user'
        );

        if ($this->useCardMode) {
            // Card mode: every CRUD action happens via the AJAX modal, so the page-level
            // Save / Save & Close / Save & New buttons would have nothing meaningful to save.
            // Replace them with a single "Add New Address" button that opens the modal.
            //
            // We render this as a CustomButton (raw HTML) instead of standardButton() because
            // standardButton injects an onclick that calls Joomla.submitbutton(task), which
            // submits the surrounding form and navigates away — even with an empty task. The
            // raw button below is type="button" so it stays put and lets the existing
            // .j2commerce-address-add JS click delegate open the modal.
            if (!$checkedOut && ($canDo->get('core.edit') || $canDo->get('core.create'))) {
                $userId = (int) ($this->item->user_id ?? 0);
                $label  = htmlspecialchars(Text::_('COM_J2COMMERCE_CUSTOMER_ADDRESSES_ADD'), ENT_QUOTES, 'UTF-8');

                $toolbar->customButton('add-customer-address')
                    ->html(
                        '<joomla-toolbar-button>'
                        . '<button type="button" class="btn btn-sm button-new btn-success j2commerce-address-add"'
                        . ' data-user-id="' . $userId . '">'
                        . '<span class="icon-new" aria-hidden="true"></span> '
                        . $label
                        . '</button>'
                        . '</joomla-toolbar-button>'
                    );
            }
        } else {
            // Inline mode (new record or guest address): keep the standard Joomla form
            // toolbar so the store owner can save the address row from the page form.
            if (!$checkedOut && ($canDo->get('core.edit') || $canDo->get('core.create'))) {
                $toolbar->apply('customer.apply');
                $toolbar->save('customer.save');
            }

            if (!$checkedOut && $canDo->get('core.create')) {
                $toolbar->save2new('customer.save2new');
            }
        }

        $toolbar->cancel('customer.cancel', $isNew ? 'JTOOLBAR_CANCEL' : 'JTOOLBAR_CLOSE');

        ToolbarHelper::help(Text::_('COM_J2COMMERCE_CUSTOMERS'), true, 'https://docs.j2commerce.com/v6/sales/customers');
    }
}
