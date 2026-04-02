-- J2Commerce 6.1.4
-- Update email config defaults for existing installs:
--   send_default_email_template: 1 → 0 (configured templates only)
--   show_thumb_email: 0 → 1 (show product thumbnails in emails)

UPDATE `#__extensions`
SET `params` = JSON_SET(
    JSON_SET(`params`, '$.send_default_email_template', '0'),
    '$.show_thumb_email', '1'
)
WHERE `element` = 'com_j2commerce'
AND `type` = 'component';
