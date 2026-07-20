<?php

/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace J2Commerce\Component\J2commerce\Administrator\Field\Modal;

use Joomla\CMS\Factory;
use Joomla\CMS\Form\Field\ModalSelectField;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\FileLayout;
use Joomla\CMS\Log\Log;
use Joomla\CMS\Session\Session;
use Joomla\CMS\Uri\Uri;
use Joomla\Database\ParameterType;

\defined('_JEXEC') or die;

/**
 * Supports a modal coupon picker with inline create/edit.
 *
 * Lets developers embed the full coupon workflow (select, create, edit, clear) inside
 * their own extension forms without leaving the page:
 *
 * <field
 *     name="coupon_id"
 *     type="modal_coupon"
 *     label="..."
 *     select="true"
 *     new="true"
 *     edit="true"
 *     clear="true"
 *     addfieldprefix="J2Commerce\Component\J2commerce\Administrator\Field"
 * />
 *
 * @since  6.0.6
 */
class CouponField extends ModalSelectField
{
    /**
     * The form field type.
     *
     * @var    string
     *
     * @since  6.0.6
     */
    protected $type = 'Modal_Coupon';

    /**
     * Method to attach a Form object to the field.
     *
     * @param   \SimpleXMLElement  $element  The SimpleXMLElement object representing the `<field>` tag for the form field object.
     * @param   mixed              $value    The form field value to validate.
     * @param   string             $group    The field name group control value.
     *
     * @return  boolean  True on success.
     *
     * @see     FormField::setup()
     *
     * @since   6.0.6
     */
    public function setup(\SimpleXMLElement $element, $value, $group = null)
    {
        // Accept a stored "id:alias" value and keep only the id.
        if ($value && str_contains($value, ':')) {
            [$id]  = explode(':', $value, 2);
            $value = (int) $id;
        }

        $result = parent::setup($element, $value, $group);

        if (!$result) {
            return $result;
        }

        Factory::getApplication()->getLanguage()->load('com_j2commerce', JPATH_ADMINISTRATOR);

        // Select modal → coupons list.
        $linkCoupons = (new Uri())->setPath(Uri::base(true) . '/index.php');
        $linkCoupons->setQuery([
            'option'                => 'com_j2commerce',
            'view'                  => 'coupons',
            'layout'                => 'modal',
            'tmpl'                  => 'component',
            Session::getFormToken() => 1,
        ]);

        // New / Edit modal → single coupon form.
        $linkCoupon = (new Uri())->setPath(Uri::base(true) . '/index.php');
        $linkCoupon->setQuery([
            'option'                => 'com_j2commerce',
            'view'                  => 'coupon',
            'layout'                => 'modal',
            'tmpl'                  => 'component',
            Session::getFormToken() => 1,
        ]);

        // Check-in endpoint used to release the record after an edit.
        $linkCheckin = (new Uri())->setPath(Uri::base(true) . '/index.php');
        $linkCheckin->setQuery([
            'option'                => 'com_j2commerce',
            'task'                  => 'coupons.checkin',
            'format'                => 'json',
            Session::getFormToken() => 1,
        ]);

        $urlEdit = clone $linkCoupon;
        $urlEdit->setVar('task', 'coupon.edit');
        $urlNew  = clone $linkCoupon;
        $urlNew->setVar('task', 'coupon.add');

        $this->urls['select']  = (string) $linkCoupons;
        $this->urls['new']     = (string) $urlNew;
        $this->urls['edit']    = (string) $urlEdit;
        $this->urls['checkin'] = (string) $linkCheckin;

        $this->modalTitles['select'] = Text::_('COM_J2COMMERCE_SELECT_A_COUPON');
        $this->modalTitles['new']    = Text::_('COM_J2COMMERCE_NEW_COUPON');
        $this->modalTitles['edit']   = Text::_('COM_J2COMMERCE_EDIT_COUPON');

        $this->hint = $this->hint ?: Text::_('COM_J2COMMERCE_SELECT_A_COUPON');

        return $result;
    }

    /**
     * Method to retrieve the title of the selected coupon.
     *
     * @return  string
     *
     * @since   6.0.6
     */
    protected function getValueTitle()
    {
        $value = (int) $this->value ?: '';
        $title = '';

        if ($value) {
            try {
                $db    = $this->getDatabase();
                $query = $db->getQuery(true)
                    ->select($db->quoteName(['coupon_name', 'coupon_code']))
                    ->from($db->quoteName('#__j2commerce_coupons'))
                    ->where($db->quoteName('j2commerce_coupon_id') . ' = :value')
                    ->bind(':value', $value, ParameterType::INTEGER);

                $db->setQuery($query);

                $coupon = $db->loadObject();

                if ($coupon) {
                    $title = $coupon->coupon_code !== ''
                        ? $coupon->coupon_name . ' (' . $coupon->coupon_code . ')'
                        : $coupon->coupon_name;
                }
            } catch (\Throwable $e) {
                Log::add($e->getMessage(), Log::ERROR, 'com_j2commerce');
            }
        }

        return $title ?: $value;
    }

    /**
     * Get the renderer.
     *
     * @param   string  $layoutId  Id to load.
     *
     * @return  FileLayout
     *
     * @since   6.0.6
     */
    protected function getRenderer($layoutId = 'default')
    {
        $layout = parent::getRenderer($layoutId);
        $layout->setComponent('com_j2commerce');
        $layout->setClient(1);

        return $layout;
    }
}
