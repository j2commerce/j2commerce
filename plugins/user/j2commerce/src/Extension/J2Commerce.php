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
use Joomla\CMS\Document\HtmlDocument;
use Joomla\CMS\Factory;
use Joomla\CMS\Filter\InputFilter;
use Joomla\CMS\Form\Form;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;
use Joomla\CMS\Uri\Uri;
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
        $app = $this->getApplication();

        // Registration is open to guests, so the token is the only caller check
        // available here — every com_j2commerce AJAX surface carries one.
        if (!Session::checkToken('request')) {
            $event->setArgument('result', json_encode(['error' => ['token' => Text::_('JINVALID_TOKEN')]]));

            return;
        }

        $post = $this->cleanFieldMap($app->getInput()->get('j2reg', [], 'ARRAY'));

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

    /**
     * The ARRAY input filter is a bare cast, so its elements arrive uncleaned.
     * Every other address-write path in the component reads through getString().
     */
    private function cleanFieldMap(array $raw): array
    {
        $filter = InputFilter::getInstance();
        $clean  = [];

        foreach ($raw as $key => $value) {
            if (\is_array($value)) {
                $clean[$key] = array_map(
                    static fn ($item): string => \is_scalar($item) ? $filter->clean((string) $item, 'string') : '',
                    $value
                );

                continue;
            }

            $clean[$key] = \is_scalar($value) ? $filter->clean((string) $value, 'string') : '';
        }

        return $clean;
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

        $app      = $this->getApplication();
        $j2Fields = $this->cleanFieldMap($app->getInput()->get('j2reg', [], 'ARRAY'));

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

        // Wrap in container with address ID hidden field. The data attributes drive the shared
        // country/zone cascade in media/com_j2commerce/js/site/j2commerce-countryzone.js.
        $addressId = $address ? (int) $address->j2commerce_address_id : 0;
        $html      = '<div id="billing-new" data-j2c-countryzone'
            . ' data-country-name="j2reg[country_id]"'
            . ' data-zone-name="j2reg[zone_id]"'
            . ' data-base-url="' . htmlspecialchars($this->countryZoneBaseUrl(), ENT_QUOTES, 'UTF-8') . '">'
            . $html
            . '<input type="hidden" name="j2reg[j2commerce_address_id]" value="' . $addressId . '">'
            . '</div>';

        $this->loadCountryZoneAssets();

        return $html;
    }

    /** The AJAX endpoints live in the site application, so admin forms need an absolute site URL. */
    private function countryZoneBaseUrl(): string
    {
        return $this->getApplication()->isClient('administrator')
            ? Uri::root(true) . '/index.php?option=com_j2commerce'
            : Route::_('index.php?option=com_j2commerce', false);
    }

    /** Register the shared country/zone cascade script and the strings it renders. */
    private function loadCountryZoneAssets(): void
    {
        $app      = $this->getApplication();
        $document = $app->getDocument();

        if (!$document instanceof HtmlDocument) {
            return;
        }

        $app->getLanguage()->load('com_j2commerce', JPATH_SITE);

        $wa = $document->getWebAssetManager();
        // core carries Joomla.Text, which the cascade reads its placeholder strings from.
        $wa->useScript('core');
        $wa->registerAndUseScript('com_j2commerce.dom', 'media/com_j2commerce/js/site/j2commerce-dom.js', [], ['defer' => true]);
        $wa->registerAndUseScript(
            'com_j2commerce.countryzone',
            'media/com_j2commerce/js/site/j2commerce-countryzone.js',
            [],
            ['defer' => true],
            ['com_j2commerce.dom']
        );

        Text::script('COM_J2COMMERCE_LOADING');
        Text::script('COM_J2COMMERCE_SELECT_ZONE');
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
