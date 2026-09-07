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

use J2Commerce\Component\J2commerce\Administrator\Helper\EmailHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Form\Field\TextField;
use Joomla\CMS\Layout\LayoutHelper;

/**
 * Subject field for an email template.
 *
 * A subject the merchant typed is plain text and behaves exactly like the text field it replaces.
 * A subject stored as a [LANG:KEY] token is shown as the wording that key resolves to, with the
 * token itself carried in a hidden input so a save cannot flatten one template's every translation
 * into whichever language the admin happened to be reading. Rewording goes through the override
 * dialog the button opens.
 */
class EmailsubjectField extends TextField
{
    protected $type = 'Emailsubject';

    protected function getInput(): string
    {
        $value = (string) $this->value;

        if (!EmailHelper::isLangToken($value)) {
            return parent::getInput();
        }

        return LayoutHelper::render('field.emailsubject', [
            'id'       => $this->id,
            'name'     => $this->name,
            'token'    => $value,
            'key'      => EmailHelper::extractLangKey($value),
            'resolved' => EmailHelper::resolveLangTokens($value),
            'class'    => (string) $this->class,
            // Super User, matching the server gate on the tasks the dialog calls: the button is
            // not offered to a user who could only be refused by it.
            'readonly' => $this->readonly
                || !Factory::getApplication()->getIdentity()->authorise('core.admin'),
        ]);
    }
}
