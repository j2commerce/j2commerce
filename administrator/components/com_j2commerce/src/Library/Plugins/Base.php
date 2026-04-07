<?php

/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace J2Commerce\Component\J2commerce\Administrator\Library\Plugins;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use J2Commerce\Component\J2commerce\Administrator\Helper\J2CommerceHelper;
use J2Commerce\Component\J2commerce\Administrator\Model\CountryModel;
use J2Commerce\Component\J2commerce\Administrator\Model\ZoneModel;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\FileLayout;
use Joomla\CMS\Log\Log;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\Session\Session;
use Joomla\Filesystem\File;
use Joomla\Filesystem\Path;

/**
 * Base Plugin Library
 *
 * @since  6.0.0
 */
class Base extends CMSPlugin
{
    /**
     * @var $_element  string  Should always correspond with the plugin's filename,
     *                         forcing it to be unique
     */
    public $_element = '';

    public $_row = '';

    /**
     * Checks to make sure that this plugin is the one being triggered by the extension
     *
     * @access public
     * @return bool Parameter value
     * @since 5.0
     */
    public function _isMe($row)
    {
        $element = $this->_element;

        $success = false;
        if (\is_object($row) && !empty($row->element) && $row->element == $element) {
            $success = true;
        }

        if (\is_string($row) && $row == $element) {
            $success = true;
        }

        return $success;
    }

    protected function _getMe()
    {
        $plugin = PluginHelper::getPlugin('j2commerce', $this->_element);
        return $plugin;
    }

    /**
     * Prepares variables for the form
     *
     * @return string   HTML to display
     */
    public function _renderForm($data)
    {
        $vars   = new \stdClass();
        $layout = 'form';
        $path   = PluginHelper::getLayoutPath($this->_type, $this->_element);

        $fileLayout = new FileLayout($layout, $path);

        return $fileLayout->render($vars);
    }

    /**
     * Prepares the 'view' tmpl layout
     *
     * @param array
     * @return string   HTML to display
     */
    public function _renderView($options)
    {
        $vars   = new \stdClass();
        $layout = 'view';
        $path   = PluginHelper::getLayoutPath($this->_type, $this->_element);

        return (new FileLayout($layout, $path))->render($vars);

    }

    /**
     * Wraps the given text in the HTML
     *
     * @param string $message
     * @return string
     * @access protected
     */
    public function _renderMessage($message = '')
    {
        $vars          = new \stdClass();
        $vars->message = $message;

        $layout = 'message';
        $path   = PluginHelper::getLayoutPath($this->_type, $this->_element);

        return (new FileLayout($layout, $path))->render($vars);

    }

    /**
     * Gets the parsed layout file
     *
     * @param string $layout The name of  the layout file
     * @param object $vars Variables to assign to
     * @param string $plugin The name of the plugin
     * @param string $group The plugin's group
     * @return string
     * @access protected
     */
    public function _getLayout($layout, $vars = false, $plugin = '', $group = 'j2commerce')
    {

        if (empty($plugin)) {
            $plugin = $this->_element;
        }

        ob_start();
        $layout = $this->_getLayoutPath($plugin, $group, $layout, $vars);
        include($layout);
        $html = ob_get_contents();
        ob_end_clean();

        return $html;
    }


    /**
     * Get the path to a layout file
     *
     * @param string $plugin The name of the plugin file
     * @param string $group The plugin's group
     * @param string $layout The name of the plugin layout file
     * @return  string  The path to the plugin layout file
     * @access protected
     * @throws Exception
     */
    public function _getLayoutPath($plugin, $group, $layout = 'default', $vars = false)
    {
        $app = Factory::getApplication();
        // get the template and default paths for the layout
        $templatePath = JPATH_SITE . '/templates/' . $app->getTemplate() . '/html/plg_' . $group . '_' . $plugin . '/' . $layout . '.php';
        $defaultPath  = JPATH_SITE . '/plugins/' . $group . '/' . $plugin . '/' . $plugin . '/tmpl/' . $layout . '.php';

        // if the site template has a layout override, use it
        if (file_exists($templatePath)) {
            return $templatePath;
        }
        return $defaultPath;

    }

    /**
     * This displays the content article
     * specified in the plugin's params
     *
     * @return string
     */

    public function _displayArticle()
    {
        $html       = '';
        $article_id = (int)$this->params->get('articleid');
        if ($article_id && is_numeric($article_id)) {
            $html = J2CommerceHelper::article()->display($article_id);
        }
        return $html;
    }


    /**
     * Checks for a form token in the request
     * Using a suffix enables multi-step forms
     *
     * @param string $suffix
     * @param string $method
     * @return boolean
     */
    public function _checkToken($suffix = '', $method = 'post')
    {
        $token = Session::getFormToken();
        $token .= "." . strtolower($suffix);
        $app = Factory::getApplication();

        if ($app->input->get($token, '', $method, 'alnum')) {
            return true;
        }

        return false;
    }

    /**
     * Generates an HTML form token and affixes a suffix to the token
     * enabling the form to be identified as a step in a process
     *
     * @param string $suffix
     * @return string HTML
     */
    public function _getToken($suffix = '')
    {
        $token = Session::getFormToken();
        $token .= "." . strtolower($suffix);
        $html = '<input type="hidden" name="' . $token . '" value="1" />';
        $html .= '<input type="hidden" name="tokenSuffix" value="' . $suffix . '" />';
        return $html;
    }

    /**
     * Gets the suffix affixed to the form's token
     * which helps identify which step this is
     * in a multi-step process
     *
     * @return string
     */
    public function _getTokenSuffix($method = 'post')
    {
        $app    = J2CommerceHelper::platform()->application();
        $suffix = $app->input->get('tokenSuffix', '');
        if (!$this->_checkToken($suffix, $method)) {
            // what to do if there isn't this suffix's token in the request?
            // anything?
        }
        return $suffix;
    }



    public function getCountryById($country_id)
    {
        $model = new CountryModel();
        return $model->getItem($country_id);
    }

    public function getZoneById($zone_id)
    {
        $model = new ZoneModel();
        return $model->getItem($zone_id);
    }



    /**
     * Clean text
     */
    public function clean_title($text)
    {
        $text = str_replace(['"', "'"], '', $text);
        return $text;
    }

    /**
     * Gets admins data
     *
     * @return array|boolean
     * @access protected
     * @throws Exception
     */
    public function _getAdmins()
    {
        try {
            $db    = Factory::getContainer()->get('DatabaseDriver');
            $query = $db->getQuery(true);

            $query->select('u.name, u.email');
            $query->from($db->quoteName('#__users', 'u'));
            $query->join('LEFT', $db->quoteName('#__user_usergroup_map', 'ug') . ' ON u.id = ug.user_id');
            $query->where('u.sendEmail = 1');
            $query->where('ug.group_id = 8');

            $db->setQuery($query);
            $admins = $db->loadObjectList();

            return $admins;
        } catch (\Exception $e) {
            Factory::getApplication()->enqueueMessage($e->getMessage(), 'error');
            return false;
        }
    }



    public function _log($text, $type = 'info')
    {
        if ($this->_isLog) {
            // Initialize logger if not already done
            Log::addLogger(
                [
                    'text_file'         => $this->_element . '.log.php',
                    'text_entry_format' => '{DATETIME} {PRIORITY} {MESSAGE}',
                ],
                Log::ALL,
                [$this->_element]
            );

            if (\is_array($text) || \is_object($text)) {
                $text = json_encode($text);
            }

            // Map type to Log priority
            $priority = Log::INFO;
            switch (strtolower($type)) {
                case 'error':
                    $priority = Log::ERROR;
                    break;
                case 'warning':
                    $priority = Log::WARNING;
                    break;
                case 'debug':
                    $priority = Log::DEBUG;
                    break;
            }

            Log::add($text, $priority, $this->_element);
        }
    }
}
