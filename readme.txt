=== WP Fusion - Custom CRM ===
Contributors: verygoodplugins
Tags:
Requires at least: 4.0
Tested up to: 6.6.0
Stable tag: 1.1.7

= 1.1.7 - 5/22/2024 =
* Fixed incorrect filter name for `format_post_data()`, was `wpf_format_post_data` instead of `wpf_crm_post_data`

= 1.1.6 - 1/22/2024 =
* Added example for registering CRM-specific settings in the admin

= 1.1.5 - 1/18/2024 =
* Added example methods for `add_tag()` and `sync_lists()`
* Additional documentation for the `$supports` property
* Updated data storage for custom fields to include `crm_type`

= 1.1.4 - 11/1/2023 =
* Updated for PHPStan level 5 compatibility

= 1.1.3 - 4/5/2023 =
* Improved - The OAuth setup fields are now commented out to prevent UI glitches on the setup tab

= 1.1.2 - 4/5/2023 =
* Added additional inline docs
* Code cleanup

= 1.1.1 - 12/12/2022 =
* Added examples for `format_post_data()` and `track_event()`

= 1.1.0 - 4/18/2022 =
* Updated for WP Fusion v3.40.0
* Memoved `$map_meta_fields` in `add_contact()` and `update_contact()` methods

= 1.0.4 - 3/31/2022 =
* Added example showing OAuth authorization fields and handling in admin class
* Added `set_default_fields()` function in admin class

= 1.0.3 - 8/10/2021 =
* Added example edit_url
* Added example `format_field_value()`
* PHPCBF and documentation cleanup

= 1.0.2 - 12/26/2020 =
* Added example for HTTP response error handling
* Improved documentation

= 1.0.1 - 3/25/2020 =
* Removed show_key_end action and function in WPF_Custom_Admin (moved to core)

= 1.0 =
* Initial release