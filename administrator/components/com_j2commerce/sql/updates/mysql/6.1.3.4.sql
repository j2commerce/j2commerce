-- uninstall old tour j2commerce-creating-product

DELETE FROM `#__guidedtour_steps`
WHERE `tour_id` = (SELECT `id` FROM `#__guidedtours` WHERE `uid` = 'j2commerce-creating-product');

DELETE FROM `#__guidedtours`
WHERE `uid` = 'j2commerce-creating-product';
