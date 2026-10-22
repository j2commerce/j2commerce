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

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Joomla\CMS\Access\Access;
use Joomla\CMS\Factory;
use Joomla\CMS\Log\Log;
use Joomla\CMS\Mail\Mail;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\Uri\Uri;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\Exception\ExecutionFailureException;
use Joomla\Database\ParameterType;
use Joomla\Event\Event;
use Joomla\Registry\Registry;

/** Cart reminder emails: which carts are due, the email for each, and the signed links it carries. */
final class CartReminderHelper
{
    public const EMAIL_TYPE = 'marketing';
    public const CONTEXT    = 'cart_reminder';

    private const BATCH = 50;

    // Carts idle longer than the delay plus this many days are never reminded, so switching reminders
    // on does not mail every old abandoned cart.
    private const MAX_AGE_DAYS = 7;

    public static function isEnabled(): bool
    {
        return (int) J2CommerceHelper::config()->get('cart_reminder_enabled', 0) === 1;
    }

    // These links go to third parties, so their host never comes from the request that happened to
    // trigger the run: it is the configured site URL, or the --live-site an operator passed to a
    // console run (without one, ConsoleApplication::populateHttpHost() falls back to joomla.invalid).
    public static function canBuildLinks(): bool
    {
        return EmailHelper::configuredSiteRoot() !== ''
            || (Factory::getApplication()->isClient('cli') && Uri::getInstance()->getHost() !== 'joomla.invalid');
    }

    /** Idle hours before a reminder, held under the cart expiry term so the cart still exists when it is due. */
    public static function delayHours(): int
    {
        $config = J2CommerceHelper::config();
        $value  = max(1, (int) $config->get('cart_reminder_delay', 1));
        $hours  = $config->get('cart_reminder_delay_unit', 'hours') === 'days' ? $value * 24 : $value;
        $expiry = max(1, (int) $config->get('clear_outdated_cart_data_term', 90));

        return min($hours, max(1, ($expiry - 1) * 24));
    }

    /** Sends the reminders that are due and returns how many went out. */
    public static function sendDue(): int
    {
        $app      = Factory::getApplication();
        $fallback = EmailHelper::getTemplateByType(self::EMAIL_TYPE, self::CONTEXT, EmailHelper::siteDefaultLanguage());

        // A reason that holds for the whole store marks no cart, so every reminder is still owed once it is fixed.
        if (!self::canBuildLinks() || !(int) $app->get('mailonline', 1) || $fallback === null || !self::hasBody($fallback)) {
            return 0;
        }

        $db        = Factory::getContainer()->get(DatabaseInterface::class);
        $hours     = self::delayHours();
        $cutoff    = Factory::getDate('now -' . $hours . ' hours')->toSql();
        $oldest    = Factory::getDate('now -' . ($hours + self::MAX_AGE_DAYS * 24) . ' hours')->toSql();
        $reminded  = self::remindedToday($db, $hours);
        $templates = [];
        $sent      = 0;

        PluginHelper::importPlugin('j2commerce');

        foreach (self::dueCarts($db, $cutoff, $oldest) as $cart) {
            $cartId  = (int) $cart->j2commerce_cart_id;
            $mailbox = self::mailboxKey(self::recipient($cart)[0]);

            // One reminder per mailbox per day, however many carts name it. The extra carts are marked
            // so they cannot hold the head of the queue on every run.
            if (isset($reminded[$mailbox])) {
                self::claim($db, $cartId, $cutoff);

                continue;
            }

            try {
                $mailer = self::prepare($db, $cart, $fallback, $templates);
            } catch (\Throwable $e) {
                Log::add('Cart reminder for cart #' . $cartId . ' could not be built: ' . $e->getMessage(), Log::WARNING, 'com_j2commerce');
                $mailer = null;
            }

            // Marked whether it is sent or skipped for good, so it leaves the queue; the mark also loses
            // to an overlapping run and to a shopper who came back after the batch was read.
            if (!self::claim($db, $cartId, $cutoff) || $mailer === null) {
                continue;
            }

            try {
                if ($mailer->Send()) {
                    $sent++;
                    $reminded[$mailbox] = true;
                }
            } catch (\Throwable $e) {
                Log::add('Cart reminder for cart #' . $cartId . ' failed: ' . $e->getMessage(), Log::WARNING, 'com_j2commerce');
            }
        }

        return $sent;
    }

    // Checkout keeps a guest's address in the session only, so a reminder could never reach a guest who
    // leaves after the guest step. Written only while reminders are switched on.
    public static function rememberGuestContact(array $addressData, string $language): void
    {
        $email = (string) ($addressData['email'] ?? '');

        if (!self::isEnabled() || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return;
        }

        // A failure here must never surface as a failed checkout step.
        try {
            $cartId = CartHelper::getInstance()->getCurrentCartId();

            if ($cartId < 1) {
                return;
            }

            $db    = Factory::getContainer()->get(DatabaseInterface::class);
            $query = $db->getQuery(true)
                ->select($db->quoteName('cart_params'))
                ->from($db->quoteName('#__j2commerce_carts'))
                ->where($db->quoteName('j2commerce_cart_id') . ' = :cartId')
                ->bind(':cartId', $cartId, ParameterType::INTEGER);

            $current = (string) $db->setQuery($query)->loadResult();
            $params  = new Registry($current);
            $params->set('reminder_email', $email);
            $params->set('reminder_first_name', (string) ($addressData['first_name'] ?? ''));
            $params->set('reminder_language', $language);
            $stored = $params->toString();

            if ($stored === $current) {
                return;
            }

            $update = $db->getQuery(true)
                ->update($db->quoteName('#__j2commerce_carts'))
                ->set($db->quoteName('cart_params') . ' = :params')
                ->where($db->quoteName('j2commerce_cart_id') . ' = :cartId')
                ->bind(':params', $stored)
                ->bind(':cartId', $cartId, ParameterType::INTEGER);

            $db->setQuery($update)->execute();
        } catch (\Throwable $e) {
            Log::add('Could not record the guest contact for cart reminders: ' . $e->getMessage(), Log::WARNING, 'com_j2commerce');
        }
    }

    /** The cart a restore link names (user_id, created_on), or null when the link does not match it. */
    public static function verifyRestore(int $cartId, string $token): ?object
    {
        $db    = Factory::getContainer()->get(DatabaseInterface::class);
        $query = $db->getQuery(true)
            ->select($db->quoteName(['user_id', 'created_on']))
            ->from($db->quoteName('#__j2commerce_carts'))
            ->where($db->quoteName('j2commerce_cart_id') . ' = :cartId')
            ->bind(':cartId', $cartId, ParameterType::INTEGER);

        $cart = $db->setQuery($query)->loadObject();

        return $cart && self::verify('restore', self::restoreSubject($cartId, (string) $cart->created_on), $token) ? $cart : null;
    }

    /** The address an unsubscribe link names, or '' when the link does not match it. */
    public static function verifyUnsubscribe(string $encodedEmail, string $token): string
    {
        $email = (string) base64_decode(strtr($encodedEmail, '-_', '+/'), true);

        return filter_var($email, FILTER_VALIDATE_EMAIL) && self::verify('unsubscribe', $email, $token) ? $email : '';
    }

    /** Records an opt-out from marketing email; repeating it is harmless. */
    public static function optOut(string $email): void
    {
        $db = Factory::getContainer()->get(DatabaseInterface::class);

        if (self::hasOptedOut($db, $email)) {
            return;
        }

        $email = self::mailboxKey($email);
        $type  = self::EMAIL_TYPE;
        $now   = Factory::getDate()->toSql();
        $query = $db->getQuery(true)
            ->insert($db->quoteName('#__j2commerce_email_optouts'))
            ->columns($db->quoteName(['email', 'email_type', 'created_on']))
            ->values(':email, :type, :now')
            ->bind(':email', $email)
            ->bind(':type', $type)
            ->bind(':now', $now);

        try {
            $db->setQuery($query)->execute();
        } catch (ExecutionFailureException $e) {
            // A second request recorded the same opt-out after the check above. Re-read rather than
            // match the error code, which is 1062 on mysqli but the SQLSTATE 23000 on PDO.
            if (!self::hasOptedOut($db, $email)) {
                throw $e;
            }
        }
    }

    private static function restoreSubject(int $cartId, string $createdOn): string
    {
        return $cartId . '|' . $createdOn;
    }

    private static function sign(string $purpose, string $subject): string
    {
        return hash_hmac('sha256', 'j2commerce.cartreminder.' . $purpose . '|' . $subject, (string) Factory::getApplication()->get('secret', ''));
    }

    private static function verify(string $purpose, string $subject, string $token): bool
    {
        return $token !== ''
            && (string) Factory::getApplication()->get('secret', '') !== ''
            && hash_equals(self::sign($purpose, $subject), $token);
    }

    // Subaddresses (+tag) and, at Gmail, dots in the local part reach the same mailbox, so they share
    // one daily allowance and one opt-out.
    private static function mailboxKey(string $email): string
    {
        $email = strtolower(trim($email));
        $at    = strrpos($email, '@');

        if ($at === false) {
            return $email;
        }

        $local  = explode('+', substr($email, 0, $at), 2)[0];
        $domain = substr($email, $at + 1);

        // A local part that starts with "+" has no base mailbox to fold into.
        if ($local === '') {
            return $email;
        }

        if ($domain === 'gmail.com' || $domain === 'googlemail.com') {
            return str_replace('.', '', $local) . '@gmail.com';
        }

        return $local . '@' . $domain;
    }

    /** @return array<string, true> mailboxes reminded in the last 24 hours */
    private static function remindedToday(DatabaseInterface $db, int $hours): array
    {
        $cartType = 'cart';
        $since    = Factory::getDate('now -24 hours')->toSql();
        // A cart is reminded only inside its due window and modified_on never moves back, so this bound
        // holds for every cart reminded since $since and lets the cart_type/modified_on index do the work.
        $active = Factory::getDate('now -' . ($hours + self::MAX_AGE_DAYS * 24 + 24) . ' hours')->toSql();
        $query  = $db->getQuery(true)
            ->select($db->quoteName(['c.cart_params', 'u.email'], ['cart_params', 'user_email']))
            ->from($db->quoteName('#__j2commerce_carts', 'c'))
            ->join('LEFT', $db->quoteName('#__users', 'u'), $db->quoteName('u.id') . ' = ' . $db->quoteName('c.user_id'))
            ->where($db->quoteName('c.cart_type') . ' = :cartType')
            ->where($db->quoteName('c.modified_on') . ' >= :active')
            ->where($db->quoteName('c.reminder_sent_on') . ' >= :since')
            ->bind(':cartType', $cartType)
            ->bind(':active', $active)
            ->bind(':since', $since);

        $reminded = [];

        foreach ($db->setQuery($query)->loadObjectList() ?: [] as $row) {
            $email = $row->user_email ?: (string) (new Registry((string) $row->cart_params))->get('reminder_email', '');

            if ($email !== '') {
                $reminded[self::mailboxKey($email)] = true;
            }
        }

        return $reminded;
    }

    private static function hasBody(object $template): bool
    {
        return ($template->body_source ?? '') === 'file' || trim((string) ($template->body ?? '')) !== '';
    }

    /** @return object[] */
    private static function dueCarts(DatabaseInterface $db, string $cutoff, string $oldest): array
    {
        $cartType = 'cart';
        // cart_params is compact JSON from Registry::toString(); this matches the key as written there.
        $emailKey = '%"reminder_email":"%';

        $hasItems = $db->getQuery(true)
            ->select('1')
            ->from($db->quoteName('#__j2commerce_cartitems', 'ci'))
            ->where($db->quoteName('ci.cart_id') . ' = ' . $db->quoteName('c.j2commerce_cart_id'))
            ->where($db->quoteName('ci.product_qty') . ' > 0');

        // A cart nobody can be reached about is left out here rather than marked, so it cannot take a
        // batch slot on every run: a guest qualifies only once checkout recorded an address.
        $query = $db->getQuery(true)
            ->select($db->quoteName(['c.j2commerce_cart_id', 'c.user_id', 'c.created_on', 'c.cart_params', 'c.cart_coupon']))
            ->select($db->quoteName(['u.email', 'u.name', 'u.params'], ['user_email', 'user_name', 'user_params']))
            ->from($db->quoteName('#__j2commerce_carts', 'c'))
            ->join(
                'LEFT',
                $db->quoteName('#__users', 'u'),
                $db->quoteName('u.id') . ' = ' . $db->quoteName('c.user_id') . ' AND ' . $db->quoteName('u.block') . ' = 0'
            )
            ->where($db->quoteName('c.cart_type') . ' = :cartType')
            ->where($db->quoteName('c.reminder_sent_on') . ' IS NULL')
            ->where($db->quoteName('c.modified_on') . ' <= :cutoff')
            ->where($db->quoteName('c.modified_on') . ' >= :oldest')
            ->where(
                '(' . $db->quoteName('u.id') . ' IS NOT NULL OR (' . $db->quoteName('c.user_id') . ' = 0 AND '
                . $db->quoteName('c.cart_params') . ' LIKE :emailKey))'
            )
            ->where('EXISTS (' . $hasItems . ')')
            ->order($db->quoteName('c.modified_on') . ' ASC')
            ->bind(':cartType', $cartType)
            ->bind(':cutoff', $cutoff)
            ->bind(':oldest', $oldest)
            ->bind(':emailKey', $emailKey);

        // Placed the way checkout decides it before it clears a cart, and only an order created after the
        // cart's last change counts. The New order the confirm step writes is not placed, so a shopper
        // who left at the payment step is still reminded.
        $placed = array_values(array_filter(array_map('intval', (array) J2CommerceHelper::config()->get('clear_cart_states', []))))
            ?: OrderStatusHelper::idsOfTypes(...OrderStatusHelper::PLACED_TYPES);

        if ($placed !== []) {
            $ordered = $db->getQuery(true)
                ->select('1')
                ->from($db->quoteName('#__j2commerce_orders', 'o'))
                ->where($db->quoteName('o.cart_id') . ' = ' . $db->quoteName('c.j2commerce_cart_id'))
                ->where($db->quoteName('o.created_on') . ' >= ' . $db->quoteName('c.modified_on'))
                ->where($db->quoteName('o.order_state_id') . ' IN (' . implode(',', $query->bindArray($placed, ParameterType::INTEGER)) . ')');

            $query->where('NOT EXISTS (' . $ordered . ')');
        }

        return $db->setQuery($query, 0, self::BATCH)->loadObjectList() ?: [];
    }

    private static function claim(DatabaseInterface $db, int $cartId, string $cutoff): bool
    {
        $now   = Factory::getDate()->toSql();
        $query = $db->getQuery(true)
            ->update($db->quoteName('#__j2commerce_carts'))
            ->set($db->quoteName('reminder_sent_on') . ' = :now')
            ->where($db->quoteName('j2commerce_cart_id') . ' = :cartId')
            ->where($db->quoteName('reminder_sent_on') . ' IS NULL')
            ->where($db->quoteName('modified_on') . ' <= :cutoff')
            ->bind(':now', $now)
            ->bind(':cartId', $cartId, ParameterType::INTEGER)
            ->bind(':cutoff', $cutoff);

        $db->setQuery($query)->execute();

        return $db->getAffectedRows() === 1;
    }

    // The mail for one cart, or null when this cart is skipped for good.
    /** @param array<string, object> $templates per-language template lookups for this run */
    private static function prepare(DatabaseInterface $db, object $cart, object $fallback, array &$templates): ?Mail
    {
        [$email, $firstName, $language] = self::recipient($cart);

        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || self::hasOptedOut($db, $email)) {
            return null;
        }

        $items = self::cartLines($db, (int) $cart->j2commerce_cart_id, (int) $cart->user_id);

        if ($items === []) {
            return null;
        }

        $veto = new Event('onJ2CommerceBeforeSendCartReminder', ['cart' => $cart, 'email' => $email, 'cancel' => false]);
        Factory::getApplication()->getDispatcher()->dispatch($veto->getName(), $veto);

        if ($veto->getArgument('cancel')) {
            return null;
        }

        $helper = EmailHelper::getInstance();
        $data   = (object) ['customer_language' => $language, 'user_email' => $email, 'email_items' => $items];
        $tag    = $helper->orderLanguageTag($data);

        if (!\array_key_exists($tag, $templates)) {
            $template         = EmailHelper::getTemplateByType(self::EMAIL_TYPE, self::CONTEXT, $tag);
            $templates[$tag]  = $template !== null && self::hasBody($template) ? $template : $fallback;
        }

        $cartId         = (int) $cart->j2commerce_cart_id;
        $unsubscribeUrl = self::unsubscribeUrl($email);
        $text           = [
            '[FIRST_NAME]'      => $firstName,
            '[CUSTOMER_NAME]'   => $firstName,
            '[CART_ITEM_COUNT]' => (string) \count($items),
            '[CART_COUPON]'     => (string) $cart->cart_coupon,
            '[CART_URL]'        => self::restoreUrl($cartId, (string) $cart->created_on),
            '[UNSUBSCRIBE_URL]' => $unsubscribeUrl,
        ];

        // Encoded like core's own tag values, delimiters included, so a name typed as "[SITEURL]" is
        // shown as typed instead of being read as template syntax by a later pass.
        $html = array_map(
            static fn (string $v): string => EmailHelper::encodeTagDelimiters(htmlspecialchars($v, ENT_QUOTES, 'UTF-8')),
            $text
        );

        // The subject is plain text, where an entity would show literally, so delimiters are swapped
        // for look-alikes instead: a name typed as "[SITEURL]" must not expand there either.
        $plain = array_map(static fn (string $v): string => str_replace(['[', ']', '{', '}'], ['(', ')', '(', ')'], $v), $text);

        $mailer = $helper->buildMailFromTemplate($templates[$tag], $data, $html, $plain);

        // RFC 8058 one-click unsubscribe, which the large mailbox providers expect on marketing mail.
        // On the mail() transport PHPMailer caps a header line at 63 characters and Q-encodes a
        // longer value, which no List-Unsubscribe reader parses, so the header is left to SMTP and
        // sendmail; the link in the footer works on every transport.
        if ($mailer->Mailer !== 'mail') {
            $mailer->addCustomHeader('List-Unsubscribe', '<' . $unsubscribeUrl . '>');
            $mailer->addCustomHeader('List-Unsubscribe-Post', 'List-Unsubscribe=One-Click');
        }

        $mailer->addRecipient($email);

        return $mailer;
    }

    /** @return array{0: string, 1: string, 2: string} email, first name, language tag */
    private static function recipient(object $cart): array
    {
        if (!empty($cart->user_email)) {
            return [
                (string) $cart->user_email,
                (string) strtok(trim((string) $cart->user_name), ' '),
                (string) (new Registry((string) $cart->user_params))->get('language', ''),
            ];
        }

        $saved = new Registry((string) $cart->cart_params);

        return [
            (string) $saved->get('reminder_email', ''),
            (string) $saved->get('reminder_first_name', ''),
            (string) $saved->get('reminder_language', ''),
        ];
    }

    private static function restoreUrl(int $cartId, string $createdOn): string
    {
        return EmailHelper::getInstance()->siteLink(
            'index.php?option=com_j2commerce&task=carts.restore&cart=' . $cartId
            . '&t=' . self::sign('restore', self::restoreSubject($cartId, $createdOn))
        );
    }

    private static function unsubscribeUrl(string $email): string
    {
        return EmailHelper::getInstance()->siteLink(
            'index.php?option=com_j2commerce&task=carts.unsubscribe&e=' . rtrim(strtr(base64_encode($email), '+/', '-_'), '=')
            . '&t=' . self::sign('unsubscribe', $email)
        );
    }

    private static function hasOptedOut(DatabaseInterface $db, string $email): bool
    {
        $type  = self::EMAIL_TYPE;
        $query = $db->getQuery(true)
            ->select('1')
            ->from($db->quoteName('#__j2commerce_email_optouts'))
            ->whereIn($db->quoteName('email'), array_values(array_unique([strtolower($email), self::mailboxKey($email)])), ParameterType::STRING)
            ->where($db->quoteName('email_type') . ' = :type')
            ->bind(':type', $type);

        return (bool) $db->setQuery($query, 0, 1)->loadResult();
    }

    // The cart's lines in the order-item shape the email's item loop reads, held to the rules the cart
    // page applies (ProductVisibilityHelper::isViewable()) for the cart owner's view levels. Prices are
    // left out: a cart is priced for the shopper's own group, zone and rules at render time, which a
    // scheduled run cannot reproduce.
    /** @return object[] */
    private static function cartLines(DatabaseInterface $db, int $cartId, int $userId): array
    {
        $levels = Access::getAuthorisedViewLevels($userId);
        $now    = Factory::getDate()->toSql();
        $query  = $db->getQuery(true)
            ->select($db->quoteName(['a.product_id', 'a.product_qty', 'a.product_options', 'v.sku', 'ct.title']))
            ->from($db->quoteName('#__j2commerce_cartitems', 'a'))
            ->join(
                'INNER',
                $db->quoteName('#__j2commerce_products', 'p'),
                $db->quoteName('p.j2commerce_product_id') . ' = ' . $db->quoteName('a.product_id')
            )
            ->join(
                'INNER',
                $db->quoteName('#__content', 'ct'),
                $db->quoteName('ct.id') . ' = ' . $db->quoteName('p.product_source_id')
                . ' AND ' . $db->quoteName('p.product_source') . ' = ' . $db->quote('com_content')
            )
            ->join('INNER', $db->quoteName('#__categories', 'cat'), $db->quoteName('cat.id') . ' = ' . $db->quoteName('ct.catid'))
            ->join('LEFT', $db->quoteName('#__j2commerce_variants', 'v'), $db->quoteName('v.j2commerce_variant_id') . ' = ' . $db->quoteName('a.variant_id'))
            ->where($db->quoteName('a.cart_id') . ' = :cartId')
            ->where($db->quoteName('a.product_qty') . ' > 0')
            ->where($db->quoteName('p.enabled') . ' = 1')
            ->where($db->quoteName('ct.state') . ' = 1')
            ->where($db->quoteName('cat.published') . ' = 1')
            ->where('(' . $db->quoteName('ct.publish_up') . ' IS NULL OR ' . $db->quoteName('ct.publish_up') . ' <= :publishUp)')
            ->where('(' . $db->quoteName('ct.publish_down') . ' IS NULL OR ' . $db->quoteName('ct.publish_down') . ' >= :publishDown)')
            ->whereIn($db->quoteName('ct.access'), $levels)
            ->whereIn($db->quoteName('cat.access'), $levels)
            ->bind(':cartId', $cartId, ParameterType::INTEGER)
            ->bind(':publishUp', $now)
            ->bind(':publishDown', $now);

        return array_map(
            static fn (object $row): object => (object) [
                'product_id'           => (int) $row->product_id,
                'orderitem_name'       => (string) $row->title,
                'orderitem_sku'        => (string) ($row->sku ?? ''),
                'orderitem_quantity'   => (int) $row->product_qty,
                'orderitem_attributes' => (string) $row->product_options,
            ],
            $db->setQuery($query)->loadObjectList() ?: []
        );
    }
}
