<?php

/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace J2Commerce\Component\J2commerce\Administrator\Helper;

\defined('_JEXEC') or die;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Mail\MailerFactoryInterface;
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\User\User;
use Joomla\CMS\User\UserFactoryInterface;
use Joomla\CMS\User\UserHelper as JoomlaUserHelper;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;

/**
 * User helper class for J2Commerce.
 *
 * @since  6.0.0
 */
class UserHelper
{
    /**
     * Cached database instance.
     *
     * @var   DatabaseInterface|null
     */
    private static ?DatabaseInterface $db = null;

    /**
     * Singleton instance.
     *
     * @var   static|null
     */
    protected static ?self $instance = null;

    /**
     * Get the database instance.
     */
    private static function getDatabase(): DatabaseInterface
    {
        if (self::$db === null) {
            self::$db = Factory::getContainer()->get(DatabaseInterface::class);
        }

        return self::$db;
    }

    /**
     * Get the user factory instance.
     */
    private static function getUserFactory(): UserFactoryInterface
    {
        return Factory::getContainer()->get(UserFactoryInterface::class);
    }

    /**
     * Get a singleton instance.
     *
     * @param   array  $config  Optional configuration (for compatibility).
     */
    public static function getInstance(array $config = []): self
    {
        if (static::$instance === null) {
            static::$instance = new static();
        }

        return static::$instance;
    }

    /**
     * Add a customer address record.
     *
     * @param   array  $data  Address data to save.
     *
     * @return  int|false  Address ID on success, false on failure.
     */
    public static function addCustomer(array $data): int|false
    {
        $db   = self::getDatabase();
        $user = Factory::getApplication()->getIdentity();

        $address = new \stdClass();

        $allowedFields = [
            'first_name', 'last_name', 'email', 'address_1', 'address_2',
            'city', 'zip', 'zone_id', 'country_id', 'phone_1', 'phone_2',
            'fax', 'company', 'tax_number',
        ];

        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                $address->$field = $data[$field];
            }
        }

        $address->user_id = ($user && $user->id) ? (int) $user->id : 0;
        $address->type    = 'billing';

        try {
            $db->insertObject('#__j2commerce_addresses', $address, 'j2commerce_address_id');

            return (int) $address->j2commerce_address_id;
        } catch (\Exception $e) {
            Factory::getApplication()->enqueueMessage($e->getMessage(), 'error');

            return false;
        }
    }

    /**
     * Update an existing customer address record.
     *
     * @param   int    $addressId  Address ID to update.
     * @param   array  $data       Address data to update.
     */
    public static function updateCustomer(int $addressId, array $data): bool
    {
        if ($addressId < 1) {
            return false;
        }

        $db = self::getDatabase();

        $address                        = new \stdClass();
        $address->j2commerce_address_id = $addressId;

        $allowedFields = [
            'first_name', 'last_name', 'email', 'address_1', 'address_2',
            'city', 'zip', 'zone_id', 'country_id', 'phone_1', 'phone_2',
            'fax', 'company', 'tax_number', 'user_id', 'type',
        ];

        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                $address->$field = $data[$field];
            }
        }

        try {
            $db->updateObject('#__j2commerce_addresses', $address, 'j2commerce_address_id');

            return true;
        } catch (\Exception $e) {
            Factory::getApplication()->enqueueMessage($e->getMessage(), 'error');

            return false;
        }
    }

    /**
     * Get customer address by ID.
     *
     * @param   int  $addressId  Address ID.
     */
    public static function getCustomerAddress(int $addressId): ?object
    {
        if ($addressId < 1) {
            return null;
        }

        $db    = self::getDatabase();
        $query = $db->getQuery(true);
        $id    = $addressId;

        $query->select('*')
            ->from($db->quoteName('#__j2commerce_addresses'))
            ->where($db->quoteName('j2commerce_address_id') . ' = :addressId')
            ->bind(':addressId', $id, ParameterType::INTEGER);

        $db->setQuery($query);

        return $db->loadObject() ?: null;
    }

    /**
     * Get all addresses for a user.
     *
     * @param   int     $userId  User ID.
     * @param   string  $type    Optional address type filter ('billing', 'shipping').
     */
    public static function getUserAddresses(int $userId, string $type = ''): array
    {
        if ($userId < 1) {
            return [];
        }

        $db    = self::getDatabase();
        $query = $db->getQuery(true);
        $id    = $userId;

        $query->select('*')
            ->from($db->quoteName('#__j2commerce_addresses'))
            ->where($db->quoteName('user_id') . ' = :userId')
            ->bind(':userId', $id, ParameterType::INTEGER);

        if (!empty($type)) {
            $typeValue = $type;
            $query->where($db->quoteName('type') . ' = :type')
                ->bind(':type', $typeValue);
        }

        $query->order($db->quoteName('j2commerce_address_id') . ' DESC');

        $db->setQuery($query);

        return $db->loadObjectList() ?: [];
    }

    // =========================================================================
    // USER EXISTENCE CHECK METHODS
    // =========================================================================

    /**
     * Check if a username exists in Joomla users table.
     *
     * @param   string  $username  Username to check.
     */
    public static function usernameExists(string $username): bool
    {
        if (empty($username)) {
            return false;
        }

        $db    = self::getDatabase();
        $query = $db->getQuery(true);
        $name  = $username;

        $query->select('COUNT(*)')
            ->from($db->quoteName('#__users'))
            ->where($db->quoteName('username') . ' = :username')
            ->bind(':username', $name);

        $db->setQuery($query);

        return (int) $db->loadResult() > 0;
    }

    /**
     * Check if an email exists in Joomla users table.
     *
     * @param   string  $email  Email to check.
     *
     * @return  object|null  User data if exists, null otherwise.
     */
    public static function emailExists(string $email): ?object
    {
        if (empty($email)) {
            return null;
        }

        $db         = self::getDatabase();
        $query      = $db->getQuery(true);
        $emailValue = $email;

        $query->select('*')
            ->from($db->quoteName('#__users'))
            ->where($db->quoteName('email') . ' = :email')
            ->bind(':email', $emailValue);

        $db->setQuery($query);

        return $db->loadObject() ?: null;
    }

    /**
     * Check if an email exists in J2Commerce addresses table.
     *
     * @param   string  $email  Email to check.
     *
     * @return  object|null  Address data if exists, null otherwise.
     */
    public static function emailExistsInAddresses(string $email): ?object
    {
        if (empty($email)) {
            return null;
        }

        $db         = self::getDatabase();
        $query      = $db->getQuery(true);
        $emailValue = $email;

        $query->select('*')
            ->from($db->quoteName('#__j2commerce_addresses'))
            ->where($db->quoteName('email') . ' = :email')
            ->bind(':email', $emailValue);

        $db->setQuery($query);

        return $db->loadObject() ?: null;
    }

    // =========================================================================
    // USER CREATION AND REGISTRATION METHODS
    // =========================================================================

    /**
     * Create a new Joomla user account.
     *
     * @param   array  $details   User details including 'name', 'email', 'password'.
     * @param   bool   $sendMail  Whether to send registration email.
     */
    public static function createNewUser(array $details, bool $sendMail = true): ?User
    {
        $user   = new User();
        $config = ComponentHelper::getParams('com_users');

        $defaultUserGroup = $config->get('new_usertype', 2);
        $hashedPassword   = JoomlaUserHelper::hashPassword($details['password'] ?? '');
        $name             = !empty($details['name']) ? $details['name'] : ($details['email'] ?? '');

        // Set user properties using direct property assignment (Joomla 6)
        $user->id       = 0;
        $user->name     = $name;
        $user->username = $details['email'] ?? '';
        $user->password = $hashedPassword;
        $user->email    = $details['email'] ?? '';
        $user->groups   = [$defaultUserGroup];

        $autoregister = $details['autoregister'] ?? $config->get('autoregister', 1);

        Factory::getApplication()->getDispatcher()->dispatch(
            'onJ2CommerceBeforeRegisterUserSave',
            new \Joomla\Event\Event('onJ2CommerceBeforeRegisterUserSave', ['user' => &$user, 'details' => &$details])
        );

        if ($autoregister) {
            if (!$user->save()) {
                Factory::getApplication()->enqueueMessage($user->getError(), 'error');

                return null;
            }
        } else {
            // Temporary user - set custom flag via Registry params
            $params = $user->getParameters();
            $params->set('tmp_user', true);
            $user->params = $params->toString();
        }

        $useractivation = (int) $config->get('useractivation', 0);

        if ($sendMail) {
            self::sendRegistrationEmail($user, $details, $useractivation);
        }

        return $user;
    }

    /**
     * Send registration confirmation email.
     *
     * @param   User   $user            User object.
     * @param   array  $details         User details including original password.
     * @param   int    $useractivation  Activation setting (0=none, 1=self, 2=admin).
     */
    public static function sendRegistrationEmail(User $user, array $details, int $useractivation = 0): bool
    {
        $app         = Factory::getApplication();
        $config      = $app->getConfig();
        $usersConfig = ComponentHelper::getParams('com_users');

        $name       = $user->name ?: $user->email;
        $email      = $user->email;
        $activation = $user->activation;
        $password   = $details['password2'] ?? $details['password'] ?? '';

        $sitename = $config->get('sitename');
        $mailfrom = $config->get('mailfrom');
        $fromname = $config->get('fromname');
        $siteURL  = Uri::base();

        $subject = Text::sprintf('COM_J2COMMERCE_ACCOUNT_DETAILS', $name, $sitename);
        $subject = html_entity_decode($subject, ENT_QUOTES, 'UTF-8');

        $sendPassword = (int) $usersConfig->get('sendpassword', 0);

        if ($useractivation === 1) {
            $activationLink = $siteURL . 'index.php?option=com_users&task=registration.activate&token=' . $activation;
            $message        = Text::sprintf(
                'COM_J2COMMERCE_SEND_MSG_ACTIVATE',
                $name,
                $sitename,
                $activationLink,
                $siteURL,
                $email,
                $password
            );
        } elseif ($sendPassword) {
            $message = Text::sprintf('COM_J2COMMERCE_SEND_MSG', $name, $sitename, $siteURL, $email, $password);
        } else {
            $message = Text::sprintf('COM_J2COMMERCE_SEND_MSG_NOPW', $name, $sitename, $siteURL, $email);
        }

        $message = html_entity_decode($message, ENT_QUOTES, 'UTF-8');

        try {
            $mailer = Factory::getContainer()->get(MailerFactoryInterface::class)->createMailer();
            $mailer->addRecipient($email);
            $mailer->setSubject($subject);
            $mailer->setBody($message);
            $mailer->setSender([$mailfrom, $fromname]);

            return (bool) $mailer->send();
        } catch (\Exception $e) {
            Factory::getApplication()->getLogger()->warning(
                'J2Commerce: Failed to send registration email: ' . $e->getMessage()
            );

            return false;
        }
    }

    // =========================================================================
    // AUTHENTICATION METHODS
    // =========================================================================

    /**
     * Log in a user with credentials.
     *
     * @param   array   $credentials  Array with 'username' and 'password'.
     * @param   bool    $remember     Whether to remember the login.
     * @param   string  $return       Optional redirect URL after login.
     */
    public static function login(array $credentials, bool $remember = true, string $return = ''): bool
    {
        $app = Factory::getApplication();

        // Security: prevent open redirect
        if (!empty($return) && str_contains($return, 'http') && !str_starts_with($return, Uri::base())) {
            $return = '';
        }

        $options = ['remember' => $remember];
        $success = $app->login($credentials, $options);

        if ($success && !empty($return)) {
            $app->redirect($return);
        }

        return (bool) $success;
    }

    /**
     * Log out the current user.
     *
     * @param   string  $return  Optional redirect URL after logout.
     */
    public static function logout(string $return = ''): bool
    {
        $app     = Factory::getApplication();
        $success = $app->logout();

        // Security: prevent open redirect
        if (!empty($return) && str_contains($return, 'http') && !str_starts_with($return, Uri::base())) {
            $return = '';
        }

        if ($success && !empty($return)) {
            $app->redirect($return);
        }

        return (bool) $success;
    }

    /**
     * Block or unblock a user account.
     *
     * @param   int   $userId   User ID.
     * @param   bool  $unblock  True to unblock, false to block.
     */
    public static function unblockUser(int $userId, bool $unblock = true): bool
    {
        if ($userId < 1) {
            return false;
        }

        $user = self::getUserFactory()->loadUserById($userId);

        if (!$user->id) {
            return false;
        }

        $user->block = $unblock ? 0 : 1;

        return $user->save();
    }

    /**
     * Get all Joomla user groups.
     *
     * @return  array  Array of user groups with 'value' and 'text' properties.
     */
    public function getActiveUserGroupNames(): array
    {
        try {
            $db = self::getDatabase();

            $query = $db->getQuery(true)
                ->select([
                    $db->quoteName('id', 'value'),
                    $db->quoteName('title', 'text'),
                ])
                ->from($db->quoteName('#__usergroups'))
                ->order($db->quoteName('title') . ' ASC');

            $db->setQuery($query);

            return $db->loadObjectList() ?: [];
        } catch (\Exception $e) {
            Factory::getApplication()->enqueueMessage('Error getting user group names: ' . $e->getMessage(), 'error');

            return [];
        }
    }

    /**
     * Validate password against Joomla password rules.
     *
     * @param   string  $password         Password to validate.
     * @param   string  $confirmPassword  Confirmation password.
     * @param   array   $errors           Reference array for error messages.
     * @param   bool    $useJoomlaRules   Whether to use Joomla's password rules.
     */
    public static function validatePassword(
        string $password,
        string $confirmPassword,
        array &$errors,
        bool $useJoomlaRules = true
    ): bool {
        $isValid          = true;
        $minimumLength    = 4;
        $minimumIntegers  = 0;
        $minimumSymbols   = 0;
        $minimumUppercase = 0;

        if ($useJoomlaRules) {
            $params = ComponentHelper::getParams('com_users');

            if ($params) {
                $paramLength    = $params->get('minimum_length');
                $paramIntegers  = $params->get('minimum_integers');
                $paramSymbols   = $params->get('minimum_symbols');
                $paramUppercase = $params->get('minimum_uppercase');

                if (!empty($paramLength)) {
                    $minimumLength = (int) $paramLength;
                }

                if (!empty($paramIntegers)) {
                    $minimumIntegers = (int) $paramIntegers;
                }

                if (!empty($paramSymbols)) {
                    $minimumSymbols = (int) $paramSymbols;
                }

                if (!empty($paramUppercase)) {
                    $minimumUppercase = (int) $paramUppercase;
                }
            }
        }

        $valueLength = \strlen($password);

        if (empty($password)) {
            $errors['password'] = Text::_('COM_J2COMMERCE_PASSWORD_REQUIRED');
            $isValid            = false;
        }

        if (empty($confirmPassword)) {
            $errors['confirm'] = Text::_('COM_J2COMMERCE_PASSWORD_REQUIRED');
            $isValid           = false;
        }

        if ($password !== $confirmPassword) {
            $errors['confirm'] = Text::_('COM_J2COMMERCE_PASSWORDS_DOESTNOT_MATCH');
            $isValid           = false;
        }

        if ($valueLength > 4096) {
            $errors['password'] = Text::_('COM_J2COMMERCE_PASSWORD_TOO_LONG');
            $isValid            = false;
        }

        $valueTrim = trim($password);

        if (\strlen($valueTrim) !== $valueLength) {
            $errors['password'] = Text::_('COM_J2COMMERCE_SPACES_IN_PASSWORD');
            $isValid            = false;
        }

        if ($minimumIntegers > 0) {
            $nInts = preg_match_all('/[0-9]/', $password);

            if ($nInts < $minimumIntegers) {
                $errors['password'] = Text::plural('COM_J2COMMERCE_NOT_ENOUGH_INTEGERS_N', $minimumIntegers);
                $isValid            = false;
            }
        }

        if ($minimumSymbols > 0) {
            $nSymbols = preg_match_all('/[\W]/', $password);

            if ($nSymbols < $minimumSymbols) {
                $errors['password'] = Text::plural('COM_J2COMMERCE_NOT_ENOUGH_SYMBOLS_N', $minimumSymbols);
                $isValid            = false;
            }
        }

        if ($minimumUppercase > 0) {
            $nUppercase = preg_match_all('/[A-Z]/', $password);

            if ($nUppercase < $minimumUppercase) {
                $errors['password'] = Text::plural('COM_J2COMMERCE_NOT_ENOUGH_UPPERCASE_LETTERS_N', $minimumUppercase);
                $isValid            = false;
            }
        }

        if ($minimumLength > 0 && \strlen($password) < $minimumLength) {
            $errors['password'] = Text::plural('COM_J2COMMERCE_PASSWORD_TOO_SHORT_N', $minimumLength);
            $isValid            = false;
        }

        return $isValid;
    }

    // =========================================================================
    // USER GROUP METHODS
    // =========================================================================

    /**
     * Get user group names for a specific user.
     *
     * @param   int  $userId  User ID.
     *
     * @return  array  Associative array of group ID => group title.
     */
    public static function getUserGroupNames(int $userId): array
    {
        if ($userId < 1) {
            return [];
        }

        $user       = self::getUserFactory()->loadUserById($userId);
        $userGroups = $user->getAuthorisedGroups();
        $groupNames = [];

        if (empty($userGroups) || !\is_array($userGroups)) {
            return [];
        }

        $db = self::getDatabase();

        foreach ($userGroups as $groupId) {
            // Skip public group (id=1)
            if ((int) $groupId === 1) {
                continue;
            }

            $id    = (int) $groupId;
            $query = $db->getQuery(true)
                ->select($db->quoteName('title'))
                ->from($db->quoteName('#__usergroups'))
                ->where($db->quoteName('id') . ' = :groupId')
                ->bind(':groupId', $id, ParameterType::INTEGER);

            $db->setQuery($query);
            $title = $db->loadResult();

            if ($title) {
                $groupNames[$id] = $title;
            }
        }

        return $groupNames;
    }

    /**
     * Check if a user belongs to a specific group.
     *
     * @param   int  $userId   User ID.
     * @param   int  $groupId  Group ID.
     */
    public static function userInGroup(int $userId, int $groupId): bool
    {
        if ($userId < 1 || $groupId < 1) {
            return false;
        }

        $user = self::getUserFactory()->loadUserById($userId);

        return \in_array($groupId, $user->getAuthorisedGroups());
    }

    /**
     * Get all user groups as dropdown options.
     *
     * @return  array  Array of group objects with 'id', 'title', 'parent_id'.
     */
    public static function getUserGroups(): array
    {
        $db    = self::getDatabase();
        $query = $db->getQuery(true);

        $query->select($db->quoteName(['id', 'title', 'parent_id']))
            ->from($db->quoteName('#__usergroups'))
            ->order($db->quoteName('lft') . ' ASC');

        $db->setQuery($query);

        return $db->loadObjectList() ?: [];
    }

    // =========================================================================
    // UTILITY METHODS
    // =========================================================================

    /**
     * Get the current logged-in user.
     */
    public static function getCurrentUser(): User
    {
        return Factory::getApplication()->getIdentity() ?? new User();
    }

    /**
     * Check if current user is logged in.
     */
    public static function isLoggedIn(): bool
    {
        $user = self::getCurrentUser();

        return $user->id > 0;
    }

    /**
     * Check if current user is a guest.
     */
    public static function isGuest(): bool
    {
        return !self::isLoggedIn();
    }

    /**
     * Get user by ID.
     *
     * @param   int  $userId  User ID.
     */
    public static function getUserById(int $userId): ?User
    {
        if ($userId < 1) {
            return null;
        }

        $user = self::getUserFactory()->loadUserById($userId);

        return $user->id > 0 ? $user : null;
    }

    /**
     * Get user by email.
     *
     * @param   string  $email  User email.
     */
    public static function getUserByEmail(string $email): ?User
    {
        if (empty($email)) {
            return null;
        }

        $db         = self::getDatabase();
        $query      = $db->getQuery(true);
        $emailValue = $email;

        $query->select($db->quoteName('id'))
            ->from($db->quoteName('#__users'))
            ->where($db->quoteName('email') . ' = :email')
            ->bind(':email', $emailValue);

        $db->setQuery($query);
        $userId = (int) $db->loadResult();

        if ($userId > 0) {
            return self::getUserFactory()->loadUserById($userId);
        }

        return null;
    }

    // =========================================================================
    // PRIVACY CONSENT METHODS
    // =========================================================================

    /**
     * Save Joomla privacy consent for the current user.
     *
     * @param   bool  $consentGiven  Whether consent was given via form input.
     */
    public static function savePrivacyConsent(bool $consentGiven = false): bool
    {
        if (!$consentGiven) {
            $app          = Factory::getApplication();
            $consentGiven = (bool) $app->getInput()->post->getInt('privacyconsent', 0);
        }

        if (!$consentGiven) {
            return false;
        }

        $user = self::getCurrentUser();

        if ($user->id < 1) {
            return false;
        }

        $db     = self::getDatabase();
        $userId = (int) $user->id;

        $ip        = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';

        $consent          = new \stdClass();
        $consent->user_id = $userId;
        $consent->state   = 1;
        $consent->created = Factory::getDate()->toSql();
        $consent->subject = 'PLG_SYSTEM_PRIVACYCONSENT_SUBJECT';
        $consent->body    = Text::sprintf('PLG_SYSTEM_PRIVACYCONSENT_BODY', $ip, $userAgent);
        $consent->remind  = 0;
        $consent->token   = '';

        try {
            $db->insertObject('#__privacy_consents', $consent);
        } catch (\Exception $e) {
            Factory::getApplication()->getLogger()->warning(
                'J2Commerce: Failed to save privacy consent: ' . $e->getMessage()
            );

            return false;
        }

        self::logPrivacyConsentAction($user);

        return true;
    }

    /**
     * Log the privacy consent action via Joomla's action log system.
     *
     * @param   User  $user  User who gave consent.
     */
    private static function logPrivacyConsentAction(User $user): void
    {
        if (!file_exists(JPATH_ADMINISTRATOR . '/components/com_actionlogs/src/Model/ActionlogModel.php')) {
            return;
        }

        try {
            $message = [
                'action'      => 'consent',
                'id'          => $user->id,
                'title'       => $user->name,
                'itemlink'    => 'index.php?option=com_users&task=user.edit&id=' . $user->id,
                'userid'      => $user->id,
                'username'    => $user->username,
                'accountlink' => 'index.php?option=com_users&task=user.edit&id=' . $user->id,
            ];

            $app = Factory::getApplication();

            /** @var \Joomla\Component\Actionlogs\Administrator\Model\ActionlogModel $model */
            $model = $app->bootComponent('com_actionlogs')
                ->getMVCFactory()
                ->createModel('Actionlog', 'Administrator', ['ignore_request' => true]);

            if ($model) {
                $model->addLog([$message], 'PLG_SYSTEM_PRIVACYCONSENT_CONSENT', 'plg_system_privacyconsent', $user->id);
            }
        } catch (\Exception $e) {
            Factory::getApplication()->getLogger()->debug(
                'J2Commerce: Could not log privacy consent action: ' . $e->getMessage()
            );
        }
    }

    /**
     * Check if user has given privacy consent.
     *
     * @param   int  $userId  User ID.
     */
    public static function hasPrivacyConsent(int $userId): bool
    {
        if ($userId < 1) {
            return false;
        }

        $db    = self::getDatabase();
        $query = $db->getQuery(true);
        $id    = $userId;
        $state = 1;

        $query->select('COUNT(*)')
            ->from($db->quoteName('#__privacy_consents'))
            ->where($db->quoteName('user_id') . ' = :userId')
            ->where($db->quoteName('state') . ' = :state')
            ->bind(':userId', $id, ParameterType::INTEGER)
            ->bind(':state', $state, ParameterType::INTEGER);

        $db->setQuery($query);

        return (int) $db->loadResult() > 0;
    }

    /**
     * Get the user's latest privacy consent record.
     *
     * @param   int  $userId  User ID.
     */
    public static function getLatestPrivacyConsent(int $userId): ?object
    {
        if ($userId < 1) {
            return null;
        }

        $db    = self::getDatabase();
        $query = $db->getQuery(true);
        $id    = $userId;
        $state = 1;

        $query->select('*')
            ->from($db->quoteName('#__privacy_consents'))
            ->where($db->quoteName('user_id') . ' = :userId')
            ->where($db->quoteName('state') . ' = :state')
            ->order($db->quoteName('created') . ' DESC')
            ->bind(':userId', $id, ParameterType::INTEGER)
            ->bind(':state', $state, ParameterType::INTEGER);

        $db->setQuery($query, 0, 1);

        return $db->loadObject() ?: null;
    }
}
