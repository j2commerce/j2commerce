--
-- Add "Track Your Order" token block + guest order URL to confirmation templates
--
-- Adds a visible order-token card to the customer order-confirmed email so guests
-- can look up their order without a Joomla account, and swaps the "View Your
-- Order" / "Track Your Package" buttons from [INVOICE_URL] to [GUEST_ORDER_URL]
-- (which carries the order_token + email and auto-seeds the My Profile guest
-- session on click).
--
-- Idempotent: only patches rows whose body still matches the original seed
-- markers and has not already been patched (body NOT LIKE '%Track Your Order%').
-- body_json is reset so the GrapesJS visual editor re-imports from the HTML
-- on next open (otherwise the editor's cached JSON tree would overwrite the
-- new block on the first save).
--

-- Order Confirmed (id 1) — insert the token block before the "View Your Order" button
UPDATE `#__j2commerce_emailtemplates`
   SET `body` = REPLACE(`body`,
        '<!-- View Order Button -->',
        '<!-- Track Your Order -->\r\n<tr>\r\n<td class="mobile-padding" style="padding: 0 20px 16px 20px;">\r\n<table style="background-color: #eff6ff; border: 1px solid #bfdbfe; border-radius: 10px;" border="0" width="100%" cellspacing="0" cellpadding="0">\r\n<tbody>\r\n<tr>\r\n<td style="padding: 14px 18px; border-bottom: 1px solid #dbeafe; font-size: 13px; font-weight: bold; color: #1e3a8a; text-transform: uppercase; letter-spacing: 0.5px;">Track Your Order</td>\r\n</tr>\r\n<tr>\r\n<td style="padding: 14px 18px; font-size: 14px; color: [text_color]; line-height: 1.6;">Use this token to look up your order any time without creating an account &mdash; just keep this email handy.<div style="margin-top: 10px; padding: 12px 14px; background-color: #ffffff; border: 1px solid #bfdbfe; border-radius: 6px; font-family: monospace; font-size: 15px; color: #1e3a8a; word-break: break-all;"><strong>[ORDER_TOKEN]</strong></div><p style="margin: 12px 0 0 0; font-size: 13px; color: #475569;">Enter this token with your email at <a style="color: [accent_color]; text-decoration: none;" href="[MYPROFILE_URL]">your account page</a>, or click "View Your Order" below for one-click access.</p></td>\r\n</tr>\r\n</tbody>\r\n</table>\r\n</td>\r\n</tr>\r\n<!-- View Order Button -->'),
       `body_json` = ''
 WHERE `j2commerce_emailtemplate_id` = 1
   AND `body` LIKE '%<!-- View Order Button -->%'
   AND `body` NOT LIKE '%<!-- Track Your Order -->%';

-- Order Confirmed (id 1) — swap the "View Your Order" button to the guest URL
UPDATE `#__j2commerce_emailtemplates`
   SET `body` = REPLACE(`body`,
        'href="[INVOICE_URL]">View Your Order',
        'href="[GUEST_ORDER_URL]">View Your Order'),
       `body_json` = ''
 WHERE `j2commerce_emailtemplate_id` = 1
   AND `body` LIKE '%href="[INVOICE_URL]">View Your Order%';

-- Order Shipped (id 2) — swap the "Track Your Package" button to the guest URL
UPDATE `#__j2commerce_emailtemplates`
   SET `body` = REPLACE(`body`,
        'href="[INVOICE_URL]" target="_blank" rel="noopener">Track Your Package',
        'href="[GUEST_ORDER_URL]" target="_blank" rel="noopener">Track Your Package'),
       `body_json` = ''
 WHERE `j2commerce_emailtemplate_id` = 2
   AND `body` LIKE '%href="[INVOICE_URL]" target="_blank" rel="noopener">Track Your Package%';

-- Order Shipped (id 2) — swap the "View Order Details" button to the guest URL
UPDATE `#__j2commerce_emailtemplates`
   SET `body` = REPLACE(`body`,
        'href="[INVOICE_URL]" target="_blank" rel="noopener">View Order Details',
        'href="[GUEST_ORDER_URL]" target="_blank" rel="noopener">View Order Details'),
       `body_json` = ''
 WHERE `j2commerce_emailtemplate_id` = 2
   AND `body` LIKE '%href="[INVOICE_URL]" target="_blank" rel="noopener">View Order Details%';

-- Order Confirmed (id 1) — restyle the order-token block to match the Payment
-- Information card (white bg + grey border) and rename the heading to "Order Token"
-- for consistency. Only patches rows that still carry the original blue-card markup.
UPDATE `#__j2commerce_emailtemplates`
   SET `body` = REPLACE(`body`,
        '<!-- Track Your Order -->\r\n<tr>\r\n<td class="mobile-padding" style="padding: 0 20px 16px 20px;">\r\n<table style="background-color: #eff6ff; border: 1px solid #bfdbfe; border-radius: 10px;" border="0" width="100%" cellspacing="0" cellpadding="0">\r\n<tbody>\r\n<tr>\r\n<td style="padding: 14px 18px; border-bottom: 1px solid #dbeafe; font-size: 13px; font-weight: bold; color: #1e3a8a; text-transform: uppercase; letter-spacing: 0.5px;">Track Your Order</td>\r\n</tr>\r\n<tr>\r\n<td style="padding: 14px 18px; font-size: 14px; color: [text_color]; line-height: 1.6;">Use this token to look up your order any time without creating an account &mdash; just keep this email handy.<div style="margin-top: 10px; padding: 12px 14px; background-color: #ffffff; border: 1px solid #bfdbfe; border-radius: 6px; font-family: monospace; font-size: 15px; color: #1e3a8a; word-break: break-all;"><strong>[ORDER_TOKEN]</strong></div><p style="margin: 12px 0 0 0; font-size: 13px; color: #475569;">Enter this token with your email at <a style="color: [accent_color]; text-decoration: none;" href="[MYPROFILE_URL]">your account page</a>, or click "View Your Order" below for one-click access.</p></td>\r\n</tr>\r\n</tbody>\r\n</table>\r\n</td>\r\n</tr>\r\n',
        '<!-- Order Token -->\r\n<tr>\r\n<td class="mobile-padding" style="padding: 0 20px 16px 20px;">\r\n<table style="background-color: #ffffff; border: 1px solid #e5e7eb; border-radius: 10px;" border="0" width="100%" cellspacing="0" cellpadding="0">\r\n<tbody>\r\n<tr>\r\n<td style="padding: 14px 18px; border-bottom: 1px solid #f3f4f6; font-size: 13px; font-weight: bold; color: #6b7280; text-transform: uppercase; letter-spacing: 0.5px;">Order Token</td>\r\n</tr>\r\n<tr>\r\n<td style="padding: 14px 18px; font-size: 14px; color: [text_color];"><strong>Token:</strong> <span style="font-family: monospace; word-break: break-all;">[ORDER_TOKEN]</span><br><span style="font-size: 13px; color: #6b7280;">Use this token with your email to look up your order without an account, or click &quot;View Your Order&quot; below for one-click access.</span></td>\r\n</tr>\r\n</tbody>\r\n</table>\r\n</td>\r\n</tr>\r\n'),
       `body_json` = ''
 WHERE `j2commerce_emailtemplate_id` = 1
   AND `body` LIKE '%<!-- Track Your Order -->%';
