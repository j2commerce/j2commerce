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
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\FileLayout;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Plugin\PluginHelper;

class Payment extends CMSPlugin
{
    /**
     * @var $_element  string  Should always correspond with the plugin's filename,
     *                         forcing it to be unique
     */
    public $_element = '';

    public $_j2version = '';

    public $_base = '';

    public function __construct($subject, $config = [])
    {
        parent::__construct($subject, $config);

        $this->_base = new Base($subject, $config);
    }

    /**
     * Triggered before making the payment
     * You can perform any modification to the order table variables here. Like setting a surcharge
     *
     *
     * @param $order     object order table object
     * @return string   HTML to display. Normally an empty one.
     */
    public function _beforePayment($order)
    {
        // Before the payment
        $html = '';
        return $html;
    }

    /**
     * Prepares the payment form
     * and returns HTML Form to be displayed to the user
     * generally will have a message saying, 'confirm entries, then click complete order'
     *
     * Submit button target for onsite payments & return URL for offsite payments should be:
     * index.php?option=com_j2commerce&view=billing&task=confirmPayment&orderpayment_type=xxxxxx
     * where xxxxxxx = $_element = the plugin's filename
     *
     * @param $data     array       form post data
     * @return string   HTML to display
     */
    public function _prePayment($data)
    {
        // Process the payment

        $vars          = new \stdClass();
        $vars->message = "Preprocessing successful. Double-check your entries.  Then, to complete your order, click Complete Order!";
        $path          = PluginHelper::getLayoutPath($this->_type, $this->_element);
        return (new FileLayout('prepayment', $path))->render($vars);
    }

    /**
     * Processes the payment form
     * and returns HTML to be displayed to the user
     * generally with a success/failed message
     *
     * @param $data     array       form post data
     * @return string   HTML to display
     * @throws Exception
     */
    public function _postPayment($data)
    {
        // Process the payment
        $app     = J2CommerceHelper::platform()->application();
        $paction = $app->input->getString('paction', '');
        $base    = new Base();
        $vars    = new \stdClass();

        switch ($paction) {
            case "display":
                $vars->message = Text::_($this->params->get('onafterpayment', ''));
                $path          = PluginHelper::getLayoutPath($this->_type, $this->_element);
                $html          = (new FileLayout('message', $path))->render($vars);
                $html .= $base->_displayArticle();
                break;
            case "process":
                $vars->message = $this->_process();
                $path          = PluginHelper::getLayoutPath($this->_type, $this->_element);
                $html          = (new FileLayout('message', $path))->render($vars);
                echo $html;
                $app->close();
                break;
            case "cancel":
                $vars->message = Text::_($this->params->get('oncancelpayment', ''));
                $path          = PluginHelper::getLayoutPath($this->_type, $this->_element);
                $html          = (new FileLayout('message', $path))->render($vars);
                break;
            default:
                $vars->message = Text::_($this->params->get('onerrorpayment', ''));
                $path          = PluginHelper::getLayoutPath($this->_type, $this->_element);
                $html          = (new FileLayout('message', $path))->render($vars);
                break;
        }

        return $html;
    }

    /**
     * Prepares the 'view' tmpl layout
     * when viewing a payment record
     *
     * @param $orderPayment     object       a valid TableOrderPayment object
     * @return string   HTML to display
     */
    public function _renderView($orderPayment)
    {
        // Load the payment from _orderpayments and render its html
        $vars = new \stdClass();
        $path = PluginHelper::getLayoutPath($this->_type, $this->_element);
        $html = (new FileLayout('view', $path))->render($vars);
        return $html;
    }

    /**
     * Prepares variables for the payment form
     *
     * @param $data     array       form post data for pre-populating form
     * @return string   HTML to display
     */
    public function _renderForm($data)
    {
        $vars                   = new \stdClass();
        $vars->onselection_text = $this->params->get('onselection', '');
        $path                   = PluginHelper::getLayoutPath($this->_type, $this->_element);
        $html                   = (new FileLayout('form', $path))->render($vars);
        return $html;
    }

    /**
     * Verifies that all the required form fields are completed
     * if any fail verification, set
     * $object->error = true
     * $object->message .= '<li>x item failed verification</li>'
     *
     * @param $submitted_values     array   post data
     * @return stdClass
     */
    public function _verifyForm($submitted_values)
    {
        $vars          = new \stdClass();
        $vars->error   = false;
        $vars->message = '';
        return $vars;
    }

    public function getVersion()
    {

        if (empty($this->_j2version)) {
            $db = Factory::getContainer()->get('DatabaseDriver');
            // Get installed version
            $query = $db->getQuery(true);
            $query->select($db->quoteName('manifest_cache'))->from($db->quoteName('#__extensions'))->where($db->quoteName('element') . ' = ' . $db->quote('com_j2commerce'));
            $db->setQuery($query);
            $manifest_cache   = $db->loadResult();
            $registry         = J2CommerceHelper::platform()->getRegistry($manifest_cache);
            $this->_j2version = $registry->get('version');
        }

        return $this->_j2version;
    }

    public function getCurrency($order, $convert = false)
    {
        $results        = [];
        $currency_code  = $order->currency_code;
        $currency_value = $order->currency_value;

        $results['currency_code']  = $currency_code;
        $results['currency_value'] = $currency_value;
        $results['convert']        = $convert;

        return $results;
    }

    public function generateHash($order)
    {
        $secret_key  = J2CommerceHelper::config()->get('queue_key', '');
        $status      = $this->params->get('payment_status', 4);
        $session     = J2CommerceHelper::platform()->application()->getSession();
        $session_id  = $session->getId();
        $hash_string = $order->order_id . $secret_key . $order->orderpayment_type . $secret_key . $status . $secret_key . $order->user_email . $secret_key . $session_id . $secret_key;
        return md5($hash_string);
    }

    public function validateHash($order)
    {
        $app            = J2CommerceHelper::platform()->application();
        $hash           = $app->input->getString('hash', '');
        $generator_hash = $this->generateHash($order);
        $status         = true;
        if ($hash != $generator_hash) {
            $status = false;
        }
        return $status;
    }

    /**
     * Return url for payment gateway
     */
    public function getReturnUrl()
    {
        $platform = J2CommerceHelper::platform();
        $url      = $platform->getThankyouPageUrl(['orderpayment_type' => $this->_element, 'paction' => 'display']);

        return $url;
    }

    public function _getFormattedTransactionDetails($data)
    {
        return json_encode($data);
    }
}
