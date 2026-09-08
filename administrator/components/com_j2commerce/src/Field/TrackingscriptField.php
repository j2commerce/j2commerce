<?php

/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace J2Commerce\Component\J2commerce\Administrator\Field;

\defined('_JEXEC') or die;

use J2Commerce\Component\J2commerce\Administrator\Helper\TrackingHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Form\Field\TextareaField;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Uri\Uri;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;
use Joomla\Registry\Registry;

/**
 * Tracking snippet textarea with the substitution tokens listed beside it.
 *
 * The value is emitted verbatim on the storefront, so it is writable only by users who
 * may already publish raw markup site-wide (the capability that also gates template overrides).
 */
class TrackingscriptField extends TextareaField
{
    protected $type = 'Trackingscript';

    /**
     * The gate that counts. A readonly or disabled control is presentation only — Form::validate()
     * consults the XML `disabled` attribute, never the object property — so an unauthorised POST is
     * answered by discarding the submitted value and returning the one already stored.
     */
    public function filter($value, $group = null, ?Registry $input = null)
    {
        if (self::isAuthorised()) {
            return parent::filter($value, $group, $input);
        }

        return $this->getStoredValue($input);
    }

    /**
     * Read from the record, not from the bound form data, which can carry a failed save's input.
     * The API loads its form unbound, so the submitted data is the only place the id appears there.
     */
    private function getStoredValue(?Registry $input): string
    {
        $itemId = (int) ($this->form->getValue('id') ?: ($input?->get('id') ?? 0));

        if ($itemId === 0) {
            return '';
        }

        $db    = Factory::getContainer()->get(DatabaseInterface::class);
        $query = $db->getQuery(true)
            ->select($db->quoteName('params'))
            ->from($db->quoteName('#__menu'))
            ->where($db->quoteName('id') . ' = :id')
            ->bind(':id', $itemId, ParameterType::INTEGER);

        $params = new Registry((string) ($db->setQuery($query)->loadResult() ?? '{}'));

        return (string) $params->get($this->fieldname, '');
    }

    private static function isAuthorised(): bool
    {
        return Factory::getApplication()->getIdentity()?->authorise('core.admin', 'com_j2commerce') ?? false;
    }

    protected function getInput(): string
    {
        $authorised = self::isAuthorised();

        if (!$authorised) {
            // Cosmetic only — filter() is what actually rejects an unauthorised value.
            $this->readonly = true;
        }

        $this->addStyle();

        // Grid, not flex-wrap: the token column has to stay beside the textarea however narrow
        // the surrounding form column is, otherwise it lands below a 12-row box and is scrolled past.
        $html = '<div class="j2c-trackingscript">';
        $html .= '<div>';

        if (!$authorised) {
            $html .= '<div class="alert alert-info">' . Text::_('COM_J2COMMERCE_TRACKING_SCRIPT_NO_PERMISSION') . '</div>';
        }

        $html .= parent::getInput() . $this->getPreviewControl() . '</div>';
        $html .= '<div class="j2c-trackingscript-tokens" tabindex="0" role="group" aria-label="'
            . htmlspecialchars(Text::_('COM_J2COMMERCE_TRACKING_TOKENS_HEADING'), ENT_QUOTES, 'UTF-8') . '">';
        $html .= '<div class="fw-bold mb-1">' . Text::_('COM_J2COMMERCE_TRACKING_TOKENS_HEADING') . '</div>';

        foreach (TrackingHelper::getTokenGroups() as $heading => $tokens) {
            $html .= '<div class="fw-semibold mt-2">' . Text::_($heading) . '</div>';
            $html .= '<ul class="list-unstyled mb-0">';

            foreach ($tokens as $token) {
                $html .= '<li><code>[' . $token . ']</code></li>';
            }

            $html .= '</ul>';
        }

        $html .= '<p class="mt-2 mb-0">' . Text::_('COM_J2COMMERCE_TRACKING_TOKENS_FORMATTED_HINT') . '</p>';
        $html .= '</div></div>';

        return $html;
    }

    private function addStyle(): void
    {
        static $added = false;

        if ($added) {
            return;
        }

        $added = true;

        Factory::getApplication()->getDocument()->getWebAssetManager()->addInlineStyle(
            '.j2c-trackingscript{display:grid;grid-template-columns:minmax(0,1fr) 15rem;gap:1rem;align-items:start}'
            . '.j2c-trackingscript textarea{width:100%;font-family:var(--bs-font-monospace,monospace)}'
            . '.j2c-trackingscript-tokens{position:sticky;top:.5rem;max-height:30rem;overflow:auto;font-size:.875rem}'
            . '@media (max-width:48rem){.j2c-trackingscript{grid-template-columns:minmax(0,1fr)}'
            . '.j2c-trackingscript-tokens{position:static;max-height:none}}'
        );
    }

    /**
     * Opens the newest order's confirmation page with the snippet rendered but not executed,
     * so the merchant can check the resolved values without firing a conversion pixel.
     */
    private function getPreviewControl(): string
    {
        // The link carries an order token, which is a bearer credential for that order's detail:
        // it goes only to someone already trusted with the snippet itself.
        if ((string) $this->element['preview'] !== '1' || !self::isAuthorised()) {
            return '';
        }

        $itemId = (int) ($this->form->getValue('id') ?? 0);
        $order  = $this->getNewestOrder();

        if ($itemId === 0 || $order === null) {
            return '<p class="mt-2 mb-0">'
                . Text::_($itemId === 0 ? 'COM_J2COMMERCE_TRACKING_PREVIEW_SAVE_FIRST' : 'COM_J2COMMERCE_TRACKING_PREVIEW_NO_ORDER')
                . '</p>';
        }

        $url = Uri::root() . 'index.php?' . http_build_query([
            'option'           => 'com_j2commerce',
            'view'             => 'confirmation',
            'order_id'         => $order->order_id,
            'token'            => $order->token,
            'tracking_preview' => 1,
            'Itemid'           => $itemId,
        ]);

        return '<a class="btn btn-secondary mt-2" target="_blank" rel="noopener noreferrer" href="'
            . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '">'
            . Text::_('COM_J2COMMERCE_TRACKING_PREVIEW_BUTTON') . '</a>'
            . '<p class="mt-2 mb-0">' . Text::_('COM_J2COMMERCE_TRACKING_PREVIEW_BUTTON_HINT') . '</p>';
    }

    private function getNewestOrder(): ?object
    {
        $db    = Factory::getContainer()->get(DatabaseInterface::class);
        $query = $db->getQuery(true)
            ->select($db->quoteName(['order_id', 'token']))
            ->from($db->quoteName('#__j2commerce_orders'))
            ->order($db->quoteName('j2commerce_order_id') . ' DESC');

        return $db->setQuery($query, 0, 1)->loadObject();
    }
}
