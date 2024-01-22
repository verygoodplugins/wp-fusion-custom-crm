# WP Fusion - Custom CRM

Boostrap for connecting WP Fusion to a custom CRM.

## Getting Started

This plugin can be customized to allow [WP Fusion](https://wpfusion.com/) to connect to custom CRM systems. Just add your API calls to the methods provided, stir, and serve!

More info in our [Creating Custom CRM Modules tutorial](https://wpfusion.com/documentation/advanced-developer-tutorials/creating-custom-crm-modules/).

This starter plugin uses `custom` and `Custom` in several places as the CRM slug and name (respectively). You can do a case-sensitive find and replace on these two strings to update it for your CRM.

### Prerequisites

Requires [WP Fusion](https://wpfusion.com/)

### Installing

Upload to your /wp-content/plugins/ directory

## Changelog

### 1.1.6 - 1/22/2024
* Added example for registering CRM-specific settings in the admin

### 1.1.5 - 1/18/2024
* Added example methods for `add_tag()` and `sync_lists()`
* Additional documentation for the `$supports` property
* Updated data storage for custom fields to include `crm_type`

### 1.1.4 - 11/1/2023
* Updated for PHPStan level 5 compatibility

### 1.1.3 - 4/5/2023
* Improved - The OAuth setup fields are now commented out to prevent UI glitches on the setup tab

### 1.1.2 - 4/5/2023
* Added additional inline docs
* Code cleanup

### 1.1.1 - 12/12/2022
* Added examples for `format_post_data()` and `track_event()`

### 1.1.0 - 4/18/2022
* Updated for WP Fusion v3.40.0
* Memoved `$map_meta_fields` in `add_contact()` and `update_contact()` methods

### 1.0.4 - 3/31/2022
* Added example showing OAuth authorization fields and handling in admin class
* Added `set_default_fields()` function in admin class

### 1.0.3 - 8/10/2021
* Added example edit_url
* Added example `format_field_value()`
* PHPCBF and documentation cleanup

### 1.0.2 - 12/26/2020
* Added example for HTTP response error handling
* Improved documentation

### 1.0.1 - 3/25/2020
* Removed show_key_end action and function in WPF_Custom_Admin (moved to core)

### 1.0 - 2/14/2018
* Initial release

## Authors

* **Jack Arturo** - *Initial work* - [Very Good Plugins](https://github.com/verygoodplugins)

## License

This project is licensed under the GPL License - see the [LICENSE.md](LICENSE.md) file for details