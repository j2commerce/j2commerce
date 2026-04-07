<?php

/**
 * @package     J2Commerce
 * @subpackage  plg_user_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace J2Commerce\Plugin\User\J2Commerce\Extension;

\defined('_JEXEC') or die;

use J2Commerce\Component\J2commerce\Administrator\Helper\CustomFieldHelper;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Form\Form;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\User\UserFactoryInterface;
use Joomla\Database\DatabaseAwareTrait;
use Joomla\Database\ParameterType;
use Joomla\Event\Event;
use Joomla\Event\SubscriberInterface;

class J2Commerce extends CMSPlugin implements SubscriberInterface
{
    use DatabaseAwareTrait;

    protected $autoloadLanguage = true;

    public static function getSubscribedEvents(): array
    {
        return [
            'onContentPrepareForm' => 'onContentPrepareForm',
            'onUserAfterSave'      => 'onUserAfterSave',
            'onAjaxJ2commerce'     => 'onAjaxJ2commerce',
        ];
    }

    /** AJAX validation of address fields during user registration. */
    public function onAjaxJ2commerce(Event $event): void
    {
        $app  = $this->getApplication();
        $post = $app->getInput()->get('j2reg', [], 'ARRAY');

        $app->getSession()->set('j2commerce.userregister', $post);

        $disableName = (int) $this->params->get('disable_name', 0);

        $fields = CustomFieldHelper::getFieldsByArea('register', 'address');
        $errors = CustomFieldHelper::validateFields($fields, $post);

        // Never require email here -- Joomla handles that on the registration form
        unset($errors['email']);

        if ($disableName) {
            unset($errors['first_name'], $errors['last_name']);
        }

        $json = $errors ? ['error' => $errors] : ['success' => 1];

        $event->setArgument('result', json_encode($json));
    }

    public function onContentPrepareForm(Event $event): void
    {
        [$form, $data] = array_values($event->getArguments());

        if (!($form instanceof Form)) {
            return;
        }

        if (!ComponentHelper::isEnabled('com_j2commerce')) {
            return;
        }

        $formName           = $form->getName();
        $showAddressFields  = (int) $this->params->get('show_address_fields', 1);

        if (\in_array($formName, ['com_users.registration', 'com_users.user'], true) && $showAddressFields) {
            $this->injectAddressFields($form, $data);
        }
    }

    public function onUserAfterSave(Event $event): void
    {
        [$data, $isNew, $result, $error] = array_values($event->getArguments());

        $userId = (int) ($data['id'] ?? 0);
        if (!$userId || !$result) {
            return;
        }

        $showAddressFields = (int) $this->params->get('show_address_fields', 1);
        if (!$showAddressFields) {
            return;
        }

        $app           = $this->getApplication();
        $j2Fields      = $app->getInput()->get('j2reg', [], 'ARRAY');

        if (empty($j2Fields)) {
            return;
        }

        $disableName = (int) $this->params->get('disable_name', 0);
        if ($disableName) {
            $j2Fields = $this->inferNameFromJoomlaFields($j2Fields);
        }

        $isAdmin = $app->isClient('administrator');

        // Admin: always save. Site: only on new registration.
        if ($isAdmin || $isNew) {
            $this->saveAddress($j2Fields, $userId);
        }
    }

    private function injectAddressFields(Form $form, mixed $data): void
    {
        // Load frontend language so custom field labels resolve
        $this->getApplication()->getLanguage()->load('com_j2commerce', JPATH_SITE);

        $fields = CustomFieldHelper::getFieldsByArea('register', 'address');
        if (empty($fields)) {
            return;
        }

        $disableName = (int) $this->params->get('disable_name', 0);
        $fieldHtml   = (string) $this->params->get('field_html', '');

        // Load existing address for admin edit
        $userId  = 0;
        $address = null;

        $app = $this->getApplication();

        if ($app->isClient('administrator')) {
            $userId = $app->getInput()->getInt('id', 0);
        }

        if ($userId > 0) {
            $address = $this->loadAddressByUser($userId);
        }

        // Check session for previously submitted data (AJAX validation round-trip)
        $sessionData = $app->getSession()->get('j2commerce.userregister', []);

        // Build the HTML using CustomFieldHelper
        $html = $this->buildFieldsHtml($fields, $address, $sessionData, $fieldHtml, $disableName);

        // Inject as a custom XML field into the form
        $xml      = new \SimpleXMLElement('<form></form>');
        $fieldset = $xml->addChild('fields');
        $fieldset->addAttribute('name', 'j2commerce_address');
        $fs = $fieldset->addChild('fieldset');
        $fs->addAttribute('name', 'j2commerce_address');
        $fs->addAttribute('label', 'PLG_USER_J2COMMERCE_ADDRESS_FIELDS');

        $field = $fs->addChild('field');
        $field->addAttribute('name', 'j2commerce_address_html');
        $field->addAttribute('type', 'note');
        $field->addAttribute('label', '');
        $field->addAttribute('description', $html);

        $form->load($xml->asXML());
    }

    private function buildFieldsHtml(
        array $fields,
        ?object $address,
        array $sessionData,
        string $fieldHtml,
        int $disableName
    ): string {
        $html = '';

        if (!empty($fieldHtml) && \strlen($fieldHtml) >= 5) {
            // Use the custom layout template from plugin params
            $html = $fieldHtml;

            foreach ($fields as $field) {
                $namekey = $field->field_namekey;

                if ($disableName && \in_array($namekey, ['first_name', 'last_name'], true)) {
                    $html = str_replace('[' . $namekey . ']', '', $html);
                    continue;
                }

                if ($namekey === 'email') {
                    $html = str_replace('[' . $namekey . ']', '', $html);
                    continue;
                }

                $value    = $sessionData[$namekey] ?? ($address?->$namekey ?? '');
                $rendered = CustomFieldHelper::renderField($field, (string) $value, [
                    'id' => $namekey,
                ]);

                // Wrap field name for form submission as j2reg[field_name]
                $rendered = $this->wrapFieldNames($rendered, $namekey);
                $html     = str_replace('[' . $namekey . ']', $rendered, $html);
            }

            // Remove any unprocessed placeholders
            $html = preg_replace('/\[[\w]+\]/', '', $html);
        } else {
            $fieldsHtml = '';

            foreach ($fields as $field) {
                $namekey = $field->field_namekey;

                if ($disableName && \in_array($namekey, ['first_name', 'last_name'], true)) {
                    continue;
                }

                if ($namekey === 'email') {
                    continue;
                }

                $value    = $sessionData[$namekey] ?? ($address?->$namekey ?? '');
                $rendered = CustomFieldHelper::renderField($field, (string) $value, [
                    'id' => $namekey,
                ]);
                $fieldsHtml .= $this->wrapFieldNames($rendered, $namekey);
            }

            $html = '<div class="row">' . $fieldsHtml . '</div>';
        }

        // Wrap in container with address ID hidden field
        $addressId = $address ? (int) $address->j2commerce_address_id : 0;
        $html      = '<div id="billing-new">' . $html
            . '<input type="hidden" name="j2reg[j2commerce_address_id]" value="' . $addressId . '">'
            . '</div>';

        // Add AJAX country/zone linking script
        $selectCountry = Text::sprintf('COM_J2COMMERCE_SELECT_PLACEHOLDER', Text::_('COM_J2COMMERCE_COUNTRY'));
        $selectZone    = Text::sprintf('COM_J2COMMERCE_SELECT_PLACEHOLDER', Text::_('COM_J2COMMERCE_ZONE'));

        $html .= '<script>
document.addEventListener("DOMContentLoaded", function() {
    var container = document.getElementById("billing-new");
    if (!container) return;

    var countrySelect = container.querySelector(\'select[name="j2reg[country_id]"]\');
    var zoneSelect = container.querySelector(\'select[name="j2reg[zone_id]"]\');
    if (!countrySelect) return;

    var savedCountryId = countrySelect.value || "";
    var savedZoneId = zoneSelect ? (zoneSelect.value || "") : "";

    var countryUrl = "index.php?option=com_j2commerce&task=ajax.getCountries";
    if (savedCountryId) countryUrl += "&country_id=" + encodeURIComponent(savedCountryId);

    fetch(countryUrl)
        .then(function(r) { return r.text(); })
        .then(function(html) {
            countrySelect.innerHTML = html;
            if (countrySelect.value && zoneSelect) {
                loadZones(countrySelect.value, savedZoneId);
            }
        });

    if (!zoneSelect) return;

    function loadZones(countryId, selectedZoneId) {
        zoneSelect.innerHTML = \'<option value="">...</option>\';
        zoneSelect.disabled = true;

        if (!countryId || countryId === "0") {
            zoneSelect.innerHTML = \'<option value="">' . htmlspecialchars($selectZone, ENT_QUOTES, 'UTF-8') . '</option>\';
            zoneSelect.disabled = false;
            return;
        }

        var url = "index.php?option=com_j2commerce&task=ajax.getZones&country_id=" + encodeURIComponent(countryId);
        if (selectedZoneId) url += "&zone_id=" + encodeURIComponent(selectedZoneId);

        fetch(url)
            .then(function(r) { return r.text(); })
            .then(function(html) {
                zoneSelect.innerHTML = html;
                zoneSelect.disabled = false;
            });
    }

    countrySelect.addEventListener("change", function() {
        loadZones(this.value, "");
    });
});
</script>';

        return $html;
    }

    /** Rewrite name="field_name" to name="j2reg[field_name]" for form submission. */
    private function wrapFieldNames(string $html, string $namekey): string
    {
        return str_replace(
            'name="' . $namekey . '"',
            'name="j2reg[' . $namekey . ']"',
            $html
        );
    }

    /** Infer first/last name from Joomla form name field when disable_name is on. */
    private function inferNameFromJoomlaFields(array $j2Fields): array
    {
        $app       = $this->getApplication();
        $joomForm  = $app->getInput()->get('jform', [], 'ARRAY');

        $name  = $joomForm['name'] ?? ($joomForm['username'] ?? '');
        $parts = explode(' ', trim($name), 2);

        $j2Fields['first_name'] = $parts[0] ?: $name;
        $j2Fields['last_name']  = $parts[1] ?? ($parts[0] ?: $name);

        return $j2Fields;
    }

    private function saveAddress(array $data, int $userId): bool
    {
        try {
            $db         = $this->getDatabase();
            $addressId  = (int) ($data['j2commerce_address_id'] ?? 0);
            $isUpdate   = false;

            // If editing an existing address, verify ownership
            if ($addressId > 0) {
                $query = $db->getQuery(true)
                    ->select($db->quoteName('user_id'))
                    ->from($db->quoteName('#__j2commerce_addresses'))
                    ->where($db->quoteName('j2commerce_address_id') . ' = :id')
                    ->bind(':id', $addressId, ParameterType::INTEGER);

                $db->setQuery($query);
                $ownerUserId = (int) $db->loadResult();

                if ($ownerUserId === $userId) {
                    $isUpdate = true;
                } else {
                    $addressId = 0;
                }
            }

            // Get user email
            $userFactory = Factory::getContainer()->get(UserFactoryInterface::class);
            $user        = $userFactory->loadUserById($userId);

            // Build address record
            $address             = new \stdClass();
            $address->user_id    = $userId;
            $address->email      = $user->email;
            $address->first_name = $data['first_name'] ?? '';
            $address->last_name  = $data['last_name'] ?? '';
            $address->address_1  = $data['address_1'] ?? '';
            $address->address_2  = $data['address_2'] ?? '';
            $address->city       = $data['city'] ?? '';
            $address->zip        = $data['zip'] ?? '';
            $address->zone_id    = $data['zone_id'] ?? '';
            $address->country_id = $data['country_id'] ?? '';
            $address->phone_1    = $data['phone_1'] ?? '';
            $address->phone_2    = $data['phone_2'] ?? '';
            $address->company    = $data['company'] ?? '';
            $address->tax_number = $data['tax_number'] ?? '';
            $address->type       = 'billing';

            if ($isUpdate) {
                $address->j2commerce_address_id = $addressId;
                $db->updateObject('#__j2commerce_addresses', $address, 'j2commerce_address_id');
            } else {
                $db->insertObject('#__j2commerce_addresses', $address, 'j2commerce_address_id');
            }

            // Clear session data
            $this->getApplication()->getSession()->clear('j2commerce.userregister');

            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function loadAddressByUser(int $userId): ?object
    {
        $db    = $this->getDatabase();
        $query = $db->getQuery(true)
            ->select('*')
            ->from($db->quoteName('#__j2commerce_addresses'))
            ->where($db->quoteName('user_id') . ' = :uid')
            ->bind(':uid', $userId, ParameterType::INTEGER)
            ->setLimit(1);

        $db->setQuery($query);

        return $db->loadObject() ?: null;
    }
}
