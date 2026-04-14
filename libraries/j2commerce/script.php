<?php

/**
 * @package     J2Commerce Library
 * @subpackage  lib_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

use Joomla\CMS\Factory;
use Joomla\CMS\Installer\InstallerAdapter;
use Joomla\CMS\Installer\InstallerScriptInterface;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Log\Log;
use Joomla\Filesystem\File;
use Joomla\Filesystem\Folder;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * J2Commerce Library installer script.
 *
 * @since  1.0.0
 */
return new class () implements InstallerScriptInterface {
    private string $minimumJoomla = '4.4.0';

    /**
     * Method to install the library.
     *
     * @param   InstallerAdapter  $adapter  The class calling this method
     *
     * @return  bool  True on success
     *
     * @since   1.0.0
     */
    public function install(InstallerAdapter $adapter): bool
    {
        echo Text::_('LIB_J2COMMERCE_INSTALLER_INSTALL_SUCCESS');

        return $this->installTemplateOverrides();
    }

    /**
     * Method to update the library.
     *
     * @param   InstallerAdapter  $adapter  The class calling this method
     *
     * @return  bool  True on success
     *
     * @since   1.0.0
     */
    public function update(InstallerAdapter $adapter): bool
    {
        echo Text::_('LIB_J2COMMERCE_INSTALLER_UPDATE_SUCCESS');

        return $this->installTemplateOverrides();
    }

    /**
     * Method to uninstall the library.
     *
     * @param   InstallerAdapter  $adapter  The class calling this method
     *
     * @return  bool  True on success
     *
     * @since   1.0.0
     */
    public function uninstall(InstallerAdapter $adapter): bool
    {
        $this->removeTemplateOverrides();

        echo Text::_('LIB_J2COMMERCE_INSTALLER_UNINSTALL_SUCCESS');

        return true;
    }

    public function preflight(string $type, InstallerAdapter $adapter): bool
    {
        if (version_compare(JVERSION, $this->minimumJoomla, '<')) {
            Factory::getApplication()->enqueueMessage(\sprintf(Text::_('JLIB_INSTALLER_MINIMUM_JOOMLA'), $this->minimumJoomla), 'error');
            return false;
        }

        return true;
    }

    public function postflight(string $type, InstallerAdapter $adapter): bool
    {
        return true;
    }

    /**
     * Install template overrides and layouts for multi-select functionality.
     *
     * @return  boolean  True on success
     *
     * @since   1.0.0
     */
    private function installTemplateOverrides()
    {
        $success = true;

        // Template overrides to install
        $overrides = [
            [
                'src'  => JPATH_LIBRARIES . '/j2commerce/layouts/com_users/users/modal_multiselect.php',
                'dest' => JPATH_ADMINISTRATOR . '/templates/atum/html/com_users/users/modal_multiselect.php',
            ],
            [
                'src'  => JPATH_LIBRARIES . '/j2commerce/layouts/com_content/articles/modal_multiselect.php',
                'dest' => JPATH_ADMINISTRATOR . '/templates/atum/html/com_content/articles/modal_multiselect.php',
            ],
            [
                'src'  => JPATH_LIBRARIES . '/j2commerce/layouts/com_contact/contacts/modal_multiselect.php',
                'dest' => JPATH_ADMINISTRATOR . '/templates/atum/html/com_contact/contacts/modal_multiselect.php',
            ],
        ];

        // Check for additional admin templates and add overrides for them too
        $adminTemplates = Folder::folders(JPATH_ADMINISTRATOR . '/templates');

        foreach ($adminTemplates as $template) {
            if ($template !== 'atum') {
                $overrides[] = [
                    'src'  => JPATH_LIBRARIES . '/j2commerce/layouts/com_users/users/modal_multiselect.php',
                    'dest' => JPATH_ADMINISTRATOR . '/templates/' . $template . '/html/com_users/users/modal_multiselect.php',
                ];
                $overrides[] = [
                    'src'  => JPATH_LIBRARIES . '/j2commerce/layouts/com_content/articles/modal_multiselect.php',
                    'dest' => JPATH_ADMINISTRATOR . '/templates/' . $template . '/html/com_content/articles/modal_multiselect.php',
                ];
                $overrides[] = [
                    'src'  => JPATH_LIBRARIES . '/j2commerce/layouts/com_contact/contacts/modal_multiselect.php',
                    'dest' => JPATH_ADMINISTRATOR . '/templates/' . $template . '/html/com_contact/contacts/modal_multiselect.php',
                ];
            }
        }

        foreach ($overrides as $override) {
            try {
                if (is_file($override['src'])) {
                    // Create destination directory if it doesn't exist
                    $destDir = \dirname($override['dest']);

                    if (!is_dir($destDir)) {
                        if (!Folder::create($destDir, 0755)) {
                            Log::add('Failed to create directory: ' . $destDir, Log::WARNING, 'lib_j2commerce');
                            $success = false;
                            continue;
                        }
                    }

                    // Copy the file
                    if (!File::copy($override['src'], $override['dest'])) {
                        Log::add('Failed to copy template override: ' . $override['dest'], Log::WARNING, 'lib_j2commerce');
                        $success = false;
                    } else {
                        Log::add('Installed template override: ' . $override['dest'], Log::INFO, 'lib_j2commerce');
                    }
                }
            } catch (Exception $e) {
                Log::add('Exception installing template override: ' . $e->getMessage(), Log::ERROR, 'lib_j2commerce');
                $success = false;
            }
        }

        return $success;
    }

    /**
     * Remove template overrides and layouts during uninstall.
     *
     * @return  void
     *
     * @since   1.0.0
     */
    private function removeTemplateOverrides()
    {
        // Remove template overrides
        $adminTemplates = Folder::folders(JPATH_ADMINISTRATOR . '/templates');

        foreach ($adminTemplates as $template) {
            // Remove com_users template override
            $userOverrideFile = JPATH_ADMINISTRATOR . '/templates/' . $template . '/html/com_users/users/modal_multiselect.php';

            if (is_file($userOverrideFile)) {
                try {
                    if (File::delete($userOverrideFile)) {
                        Log::add('Removed template override: ' . $userOverrideFile, Log::INFO, 'lib_j2commerce');
                    } else {
                        Log::add('Failed to remove template override: ' . $userOverrideFile, Log::WARNING, 'lib_j2commerce');
                    }
                } catch (Exception $e) {
                    Log::add('Exception removing template override: ' . $e->getMessage(), Log::ERROR, 'lib_j2commerce');
                }
            }

            // Remove com_content template override
            $contentOverrideFile = JPATH_ADMINISTRATOR . '/templates/' . $template . '/html/com_content/articles/modal_multiselect.php';

            if (is_file($contentOverrideFile)) {
                try {
                    if (File::delete($contentOverrideFile)) {
                        Log::add('Removed template override: ' . $contentOverrideFile, Log::INFO, 'lib_j2commerce');
                    } else {
                        Log::add('Failed to remove template override: ' . $contentOverrideFile, Log::WARNING, 'lib_j2commerce');
                    }
                } catch (Exception $e) {
                    Log::add('Exception removing template override: ' . $e->getMessage(), Log::ERROR, 'lib_j2commerce');
                }
            }

            // Remove com_contact template override
            $contactOverrideFile = JPATH_ADMINISTRATOR . '/templates/' . $template . '/html/com_contact/contacts/modal_multiselect.php';

            if (is_file($contactOverrideFile)) {
                try {
                    if (File::delete($contactOverrideFile)) {
                        Log::add('Removed template override: ' . $contactOverrideFile, Log::INFO, 'lib_j2commerce');
                    } else {
                        Log::add('Failed to remove template override: ' . $contactOverrideFile, Log::WARNING, 'lib_j2commerce');
                    }
                } catch (Exception $e) {
                    Log::add('Exception removing template override: ' . $e->getMessage(), Log::ERROR, 'lib_j2commerce');
                }
            }
        }
    }
};
