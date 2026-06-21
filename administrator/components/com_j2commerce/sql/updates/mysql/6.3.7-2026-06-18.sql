-- Normalize the layout subtemplate token "uikit3" -> "uikit".
-- The component frontend subtemplate folders (carts/checkout/confirmation/myprofile)
-- and their media asset were renamed from uikit3 to uikit in this release. Any stored
-- component or plugin config still holding the old value would otherwise orphan and
-- silently fall back to bootstrap5.
UPDATE `#__extensions`
SET `params` = REPLACE(`params`, '"uikit3"', '"uikit"')
WHERE (`element` = 'com_j2commerce' OR `folder` = 'j2commerce')
  AND `params` LIKE '%"uikit3"%';
