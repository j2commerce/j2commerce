# J2Commerce Library

A reusable Joomla library providing multi-select form fields for various content types.

## Features

- **UserMultiSelectField**: Multi-select field for choosing multiple users from com_users
- **ArticleMultiSelectField**: Multi-select field for choosing multiple articles from com_content
- **ContactMultiSelectField**: Multi-select field for choosing multiple contacts from com_contact
- **Generic multi-select JavaScript**: Reusable JS framework for multi-selection modals
- **Template overrides**: Automatic installation of modal templates
- **Internationalization**: Full language support

## Installation

1. Install the library package first
2. Install your component that depends on this library

## Usage

### In your component's form XML:

```xml
<fieldset name="basic"
          addfieldprefix="J2Commerce\Library\J2Commerce\Field">
    <field name="selected_users"
           type="Modal_UserMultiselect"
           label="Select Users"
           description="Choose multiple users" />
    <field name="selected_articles"
           type="Modal_ArticleMultiselect"
           label="Select Articles"
           description="Choose multiple articles" />
    <field name="selected_contacts"
           type="Modal_ContactMultiselect"
           label="Select Contacts"
           description="Choose multiple contacts" />
</fieldset>
```

Or add the prefix to individual fields:

```xml
<field name="selected_users"
       type="Modal_UserMultiselect"
       label="Select Users"
       description="Choose multiple users"
       addfieldprefix="J2Commerce\Library\J2Commerce\Field" />
<field name="selected_articles"
       type="Modal_ArticleMultiselect"
       label="Select Articles"
       description="Choose multiple articles"
       addfieldprefix="J2Commerce\Library\J2Commerce\Field" />
<field name="selected_contacts"
       type="Modal_ContactMultiselect"
       label="Select Contacts"
       description="Choose multiple contacts"
       addfieldprefix="J2Commerce\Library\J2Commerce\Field" />
```

### In your component code:

```php
use J2Commerce\Library\J2Commerce\Field\Modal\UserMultiSelectField;
use J2Commerce\Library\J2Commerce\Field\Modal\ArticleMultiSelectField;
use J2Commerce\Library\J2Commerce\Field\Modal\ContactMultiSelectField;
```

## Components

### UserMultiSelectField
- **Namespace**: `J2Commerce\Library\J2Commerce\Field\Modal\UserMultiSelectField`
- **Type**: `Modal_UserMultiselect`
- **Database**: Queries `#__users` table
- **Output**: Comma separated list of user IDs

### ArticleMultiSelectField
- **Namespace**: `J2Commerce\Library\J2Commerce\Field\Modal\ArticleMultiSelectField`
- **Type**: `Modal_ArticleMultiselect`
- **Database**: Queries `#__content` table
- **Output**: Comma separated list of article IDs

### ContactMultiSelectField
- **Namespace**: `J2Commerce\Library\J2Commerce\Field\Modal\ContactMultiSelectField`
- **Type**: `Modal_ContactMultiselect`
- **Database**: Queries `#__contact_details` table
- **Output**: Comma separated list of contact IDs

### JavaScript Framework
- **Files**:
  - `lib_j2commerce/multiselect-field.js` - Generic multi-selection with custom table rendering
  - `lib_j2commerce/modal-content-multiselect-field.js` - Field initialization and modal handling
  - `lib_j2commerce/modal-content-multiselect-list.js` - Modal list enhancement
- **Features**: Complete multi-selection framework
- **Usage**: Automatically loaded by multi-select fields

### Template Overrides
- **Users**: `administrator/templates/{template}/html/com_users/users/modal_multiselect.php`
- **Articles**: `administrator/templates/{template}/html/com_content/articles/modal_multiselect.php`
- **Contacts**: `administrator/templates/{template}/html/com_contact/contacts/modal_multiselect.php`
- **Installation**: Automatic via library installer
- **Cleanup**: Automatic removal on uninstall

It is possible to create overrides of the library layouts for modal-multiselect.

## Language Keys

All language strings use the `LIB_J2COMMERCE_` prefix:

**User Field Keys:**
- `LIB_J2COMMERCE_SELECT_USERS`
- `LIB_J2COMMERCE_SELECTED_USERS`
- `LIB_J2COMMERCE_USER_FIELD_ID`
- `LIB_J2COMMERCE_USER_FIELD_NAME`

**Article Field Keys:**
- `LIB_J2COMMERCE_SELECT_ARTICLES`
- `LIB_J2COMMERCE_SELECTED_ARTICLES`
- `LIB_J2COMMERCE_ARTICLE_FIELD_ID`
- `LIB_J2COMMERCE_ARTICLE_FIELD_NAME`

**Contact Field Keys:**
- `LIB_J2COMMERCE_SELECT_CONTACTS`
- `LIB_J2COMMERCE_SELECTED_CONTACTS`
- `LIB_J2COMMERCE_CONTACT_FIELD_ID`
- `LIB_J2COMMERCE_CONTACT_FIELD_NAME`

## Extending

To create additional multi-select fields:

1. Extend the `ModalMultiSelectField` base class
2. Implement your custom `setup()` method
3. Override `getValueTitles()` for your data source, if the database query needs joins
4. Create custom table rendering in `loadJavaScript()`

## Requirements

- Joomla 5.0+ (requires the joomla.dialog script)
- J2Commerce Component (for base classes)

## License

GNU General Public License version 2 or later