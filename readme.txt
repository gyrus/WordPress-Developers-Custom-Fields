=== Developer's Custom Fields ===
Contributors: gyrus, adriantoll, saurabhshukla
Donate link: http://www.babyloniantimes.co.uk/index.php?page=donate
Tags: admin, administration, custom, meta, page, pages, post, posts, attachments, custom fields, form, user, profile
Requires at least: 3.5
Tested up to: 4.2
Stable tag: 1.2

Provides developers with powerful and flexible tools for managing post and user custom fields.

== Description ==
This plugin is aimed at plugin and theme developers who want a set of tools that allows them to easily and flexibly define custom fields for all post types, and for user profiles.

Full documentation at [http://sltaylor.co.uk/wordpress/developers-custom-fields-docs/](http://sltaylor.co.uk/wordpress/developers-custom-fields-docs/).

Code on [GitHub](https://github.com/gyrus/WordPress-Developers-Custom-Fields).

Issue tracking on [GitHub](https://github.com/gyrus/WordPress-Developers-Custom-Fields/issues). If you're not sure if you've found a genuine issue or not, please start a thread on the [WP forum](http://wordpress.org/tags/developers-custom-fields).

Please note that this plugin isn't suitable for non-developers. It has been intentionally designed without a user interface for defining fields, and some aspects may be "unfriendly" to anyone not comfortable with hands-on WordPress development.

If you think this plugin doesn't quite suit your needs, there is a [comparison chart of similar plugins](https://docs.google.com/spreadsheet/ccc?key=0AoY8IFUX301qdFhBaERLUEUwa3U0YjFYTnBmaU1mbmc#gid=3) to find something that will!

== Installation ==
1. Upload the `developers-custom-fields` directory into the `/wp-content/plugins/` directory
1. Activate the plugin through the 'Plugins' menu in WordPress
1. Register your boxes and fields with `slt_cf_register_box` (full documentation at [sltaylor.co.uk](http://sltaylor.co.uk/wordpress/developers-custom-fields-docs/#functions-boxes-fields)

== Getting started ==

Because this code began life just managing custom fields for posts by defining meta boxes for post edit screens, the basic unit here is "the box". However, a "box" can also refer to a section in the user profile screen, or on media attachment edit screens.

A box is defined containing one of more custom fields, each with their own settings. You set a box to be associated with posts (in the generic sense, including pages and custom post types!), users or attachments using the `type` parameter.

Say you've defined `film` as a Custom Post Type, and you want to create custom fields to set the director and writer for film posts. You would add something like this to your theme's functions.php (or indeed your plugin code):

	<?php

	if ( function_exists( 'slt_cf_register_box') )
		add_action( 'init', 'register_my_custom_fields' );

	function register_my_custom_fields() {
		slt_cf_register_box( array(
			'type'		=> 'post',
			'title'		=> 'Credits',
			'id'		=> 'credits-box',
			'context'	=> 'normal',
			'priority'	=> 'high',
			'fields'	=> array(
				array(
					'name'			=> 'director',
					'label'			=> 'Director',
					'type'			=> 'text',
					'scope'			=> array( 'film' ),
					'capabilities'	=> array( 'edit_posts' )
				),
				array(
					'name'			=> 'writer',
					'label'			=> 'Writer',
					'type'			=> 'text',
					'scope'			=> array( 'film' ),
					'capabilities'	=> array( 'edit_posts' )
				)
			)
		));
	}

	?>

Then, when you want to output these values in a loop:

	<?php

	echo '<p>Director: ' . slt_cf_field_value( "director" ) . '</p>';
	echo '<p>Writer: ' . slt_cf_field_value( "writer" ) . '</p>';

	?>

This is just the beginning! Check the [documentation](http://sltaylor.co.uk/wordpress/developers-custom-fields-docs/) for registering boxes and fields, especially the parameters for fields. The most immediately interesting parameters for fields to check out are: `type`, `scope`, `options_type`.

There are some [option query placeholders](http://sltaylor.co.uk/wordpress/developers-custom-fields-docs/#placeholders), for creating dynamic queries to populate select, radio and checkbox fields.

There are also a few [hooks](http://sltaylor.co.uk/wordpress/developers-custom-fields-docs/#hooks). If the plugin currently lacks something you need, odds are you'll be able to hack it using a hook!

If you create a plugin that is dependent on this plugin, use the `slt_cf_init` hook to intialize your plugin (see [this Trac comment](http://core.trac.wordpress.org/ticket/11308#comment:7)).

Note that the internal Google Maps and file selection functionality is designed to be leveraged by theme options pages and other plugins.

Please raise any issues via [GitHub](https://github.com/gyrus/WordPress-Developers-Custom-Fields/issues). If you're not sure if you've found a genuine issue or not, please start a thread on the [WP forum](http://wordpress.org/tags/developers-custom-fields).

**NOTE:** Some people have found compatibility issues between the [WPMU DEV Events+](http://premium.wpmudev.org/project/events-plus/) plugin and this plugin (see [here](https://github.com/gyrus/WordPress-Developers-Custom-Fields/issues/23) and [here](https://github.com/gyrus/WordPress-Developers-Custom-Fields/issues/24)). If you find an apparent issue with this plugin and you're also using WPMU DEV Events+, please check for incompatibilities before posting an issue.

== Changelog ==
= 1.2 =
* Changed Google maps integration to allow multiple markers (thanks adriantoll!) - please read [the release notes](http://sltaylor.co.uk/blog/developers-custom-fields-1-1/)
* Changed query filtering for Simple Events date fields to use WP 4.1+ syntax to better handle start and end dates
* Added `dcf_query_string_matching` flags for `WP_Query`, to control how multiple values for single query vars are matched
* Added dismissable admin notices based on new version warnings file

= 1.1.1 =
* Fixed issue with max-width: 100% messing up Google controls

= 1.1 =
* Added `slt_cf_file_select_button_enqueue()`, for using file select button outside this plugin
* Added ability to automatically manage query string vars for custom queries, with the `make_query_var` field parameter, and the `dcf_use_query_string` and `dcf_custom_field_query_vars_only` flags for `WP_Query`. Includes integration with Simple Events date field.
* Added `esc_url()` around uses of `add_query_arg()`
* Added management of 'term splitting' for WordPress 4.2 - not activated by default, please read [the release notes](http://sltaylor.co.uk/blog/developers-custom-fields-1-1/)
* Added version-specific update warnings functionality

= 1.0.1 =
* Made plugin translatable
* Added Serbian translation (thanks Ogi Djuraskovic!)

= 1.0 =
* Dropped support for WP < 3.5 (please update WP, or stick with DCF 0.9.1 if you really have to!)
* Updated `file` field type and `slt_cf_file_select_button` function to use WP 3.5+ media upload API
* Added `file_restrict_to_type` parameter for file upload fields
* Added `file_dialog_title` parameter for file upload fields
* Changed `file_removeable` to work server-side instead of client side wiping of hidden input value
* Added `tabindex` parameter
* Added `$field` as parameter for `slt_cf_checkboxes_thumbnail_size` filter
* Added post formats to scoping
* Fixed small bug in all post meta output box, which showed `0` as `null`
* Added Google Maps API key support with `SLT_CF_GMAPS_API_KEY`

= 0.9.1 =
* Fix for conflict with other plugins that add media upload buttons to taxonomies (thanks [juicboxint](https://github.com/gyrus/WordPress-Developers-Custom-Fields/issues/30)!)
* Fix for `wp_get_attachment_image_src()` array elements not able to be referenced directly
* Added `checkboxes_thumbnail` parameter, to include a thumbnail when listing image attachments with multiple checkboxes
* Added `slt_cf_checkboxes_thumbnail_size` filter
* Added `colorpicker` field type, using http://www.eyecon.ro/colorpicker

= 0.9 =
* Fixed issue with sortable checkboxes where new options don't appear in list
* Added `group_by_post_type` parameter, to group multiple post type queries by post type
* Added `attachments_list` field type
* Added `slt_cf_attachments_list_query` filter
* Improved file preview icon handling for file types without an icon
* User fields can now be added to the registration form - see the docs for [the `scope` parameter](http://sltaylor.co.uk/wordpress/developers-custom-fields-docs/#functions-boxes-fields)
* Sortable checkboxes can now set default to `force-all` to just use a field for sorting, not selecting
* Retired "0.7 cleanup" and other redundant database tools
* Fixed "remove old fields" database tool
* Better enqueueing of scripts and styles
* Test to delete empty values changed to exactly match empty strings (numeric 0 gets stored)

= 0.8.4 =
* Added `abbreviate_option_labels` field parameter
* Added `sortable` parameter for multiple checkboxes
* Fixed error in handling default ID, depending on context (admin / front-end) in `slt_cf_default_id()`
* Using LESS for CSS

= 0.8.3.1 =
* Fixed minor bug in new placeholder code that was causing issues with option queries

= 0.8.3 =
* Added `[PARENT_ID]` option query placeholder
* Revamped option query placeholder checking to allow for values inside array parameters

= 0.8.2 =
* Added post meta output (inspired by Post Meta Inspector plugin)

= 0.8.1 =
* Added `slt_cf_strip_prefix()`
* Added more hooks to allow some standard functions to be overtaken
* Updated `slt-cf-gmaps.min.js` (this was omitted from 0.8 and resulted in the geocoder fixes not being active on live sites)

= 0.8 =
* NOTE: The `datepicker_css_url` setting, to account for additional UI elements, is now `ui_css_url`
* NOTE: The defaults for the `ui_css_url` and `css_url` settings have been moved inside the `slt_cf_init` function. If these values are being changed by the `slt_cf_setting` function, this must be hooked to `init` with a priority of 11 or above to ensure they aren't re-set to the defaults. These defaults have been moved inside `slt_cf_init` because they use `plugins_url`. Now they are exposed to any filters that other plugins might attach to the `plugins_url` filter
* Added support for WordPress 3.5 media handling; attachments now accept all custom field types except `file`
* Added `time` and `datetime` field types (thanks saurabhshukla!)
* Added `slt_cf_default_value` filter
* Fixed issue with deleting a cleared text field on an attachment (thanks Sean Hawkridge!)
* Minor fixes to dynamic options data initialization
* Adjusted output of box and field descriptions to fit with user profile screen markup better
* Made geocoder bounds update when map bounds change so only addresses / locations from within the current map display are suggested
* Moved enqueuing of Google maps JS inside the `slt_cf_gmap()` function, so the scripts are only used where necessary. This is made possible by registering them to be included in the footer - see http://scribu.net/wordpress/conditional-script-loading-revisited.html
* Added version numbers to scripts to prevent caching issues in future versions
* Improved PHPDocs for key functions to aid development

= 0.7.2.2 =
* Changed the way the file select JS detects being inside the Media Library overlay, in order to be compatible with the Inline Attachments plugin
* Added the `edit_on_profile` flag, to signal that even if a user doesn't have the right capabilities to edit a user profile field, they can edit it on their own profile (thanks jbalyo!)
* Improved error messages
* Fixed init errors when creating a post and there's no post ID
* Code now on GitHub!

= 0.7.2.1 =
* Fixed a bug in checkbox / select fields where `single` is set to `false` and no value is selected (thanks Dave Kellam!)

= 0.7.2 =
* Made the Gmap 'Find an address' geocoder work with 3.3's inclusion of jQuery UI autocomplete
* Made File Select functionality compatible with new WP 3.3 Plupload interface
* Added `allowed_html` field parameter; text field sanitization is now based on using `wp_kses` and testing for the `unfiltered_html` user capability
* The `allowtags` field parameter is now deprecated
* Fixed bug in `slt_cf_gmap` that failed to initialize properly when called via shortcode in a loop

= 0.7.1 =
* For 3.3 and above, switched inclusion of `wysiwyg` field to use new `wp_editor` function; also included `wysiwyg_settings` parameter to be passed to `wp_editor` (thanks katoen!)
* Changed the way the user ID is set up in `slt_cf_default_id` - now it tries to get ID of author whose archive page is being shown first
* Fixed bug in `slt_cf_init_options`; new version in upgrade wasn't being stored
* Fixed problem with Gmap shortcode not working even when `SLT_CF_USE_GMAPS` is set to `true`

= 0.7 =
* Added `terms` as an `options_type` for populating multiple value fields with taxonomy terms
* Added `autop` parameter to `slt_cf_simple_formatting` function
* Fields are only created when there's a value to enter; when there's an empty value, fields are deleted
* Made the field `scope` parameter default to an empty array, which will apply the field to all items within the box's scope
* Added `notice` field type for text-only notices to the user
* Changed `slt_cf_gmap` so you can pass the name of the field without the prefix, which will be automatically added
* Added an `above-content` setting for the box `context` parameter
* Added `except_posts` and `except_users` options to the `scope` field parameter
* Added admin menu for database clean-up operations
* Added `slt_cf_get_field_names` function
* Added `$multiple_fields` parameter to `slt_cf_all_field_values` function, to deal with values stored in multiple fields
* Added `$file_attach_to_post` parameter for `file` field types
* Set the `gmap` option `scrollwheel` to false (to prevent accidental zooming when scrolling the page)
* Changed internal file naming
* Upgraded jQuery UI Datepicker to 1.8.16
* Included the admin gmap geoencoder field via JS, on the condition that jQuery autocomplete is present (until we switch to suggest)
* Added `SLT_CF_USE_GMAPS` and `SLT_CF_USE_FILE_SELECT`
* Minified JS and CSS (full versions loaded when SCRIPT_DEBUG is true)
* Moved documentation to http://sltaylor.co.uk/wordpress/developers-custom-fields-docs/
* Issue tracking at https://github.com/gyrus/WordPress-Developers-Custom-Fields/issues

= 0.6.1 =
* Fixed bug where fields init check for capability to edit was being applied when a Google Map is displayed on the front end, so anyone not able to edit the field, including anyone not logged in, can't see the map
* Fixed syntax bug in calls to textile formatting function
* Made sure absent (unchecked) single checkboxes have a boolean false value stored, to avoid something that defaults to true getting re-checked after it's been unchecked
* Changed single checkboxes to store value as "1" and "0" instead of "yes" and "no" ("0" evaluates to false, but "no" evaluates to true!)

= 0.6 =
* Added `attachment` as an possible value of the box `type` parameter - custom fields for attachments! (Though accepted field types are limited to text and select for now.) Includes many minor changes to plugin code. Thanks to Frank at http://wpengineer.com/2076/add-custom-field-attachment-in-wordpress/
* Added `slt_cf_init` hook; changed initialization to allow dependent plugins to hook in
* Added `slt_cf_get_current_fields`
* Branched scope checking out from initialization code into separate `slt_cf_check_scope` function
* Added `gmap` field type
* Folded the functionality from the SLT File Select plugin into this plugin's code, leaving the functionality exposed for use in theme options pages etc.
* Added `single` field parameter, to allow storing multiple-choice values in separate postmeta fields instead of in a serialized array - this is for easier `meta_query` matching
* Added `template` option for the field `scope` parameter, to match page templates
* Added ctype functions for better validation
* Added `slt_cf_reverse_date` function
* Added `slt_cf_pre_save` action hook
* Added `$to_timestamp` parameter for `slt_cf_reverse_date` function

= 0.5.2 =
* Adjusted `options_query` default to include `posts_per_page=-1` is included in query if not specified

= 0.5.1 =
* Changed selection of users to use new WP 3.1 `get_users` function
* Made the `scope` parameter for fields required
* Added parameter type validation

= 0.5 =
* Added `slt_cf_get_posts_by_custom_first` function
* Changed behaviour of `required`, so the empty option isn't included for multiple selects
* Added `description` parameter for boxes
* Added `[TERM_IDS]` placeholder
* Added "No options" alert and `no_options` parameter for fields
* Added `exclude_current` parameter for fields
* Changed `file` field type to use SLT File Select plugin (with interface to Media Library)
* Added `file_button_label` and `file_removeable` parameters for fields
* Changed the way `slt_cf_display_post` checks to exclude link and comment screens in case custom post types 'link' or 'comment' are registered

= 0.4.2 =
* Fixed display of block labels when width is set (thanks Daniele!)
* Empty option only displayed for non-static selects (thanks Daniele!)
* A bunch of changes to how input prefixes and suffixes are handled (thanks again Daniele!)
* For clarity, the field settings `prefix` and `suffix` have been renamed `input_prefix` and `input_suffix`

= 0.4.1 =
* Added `required` and `empty_option_text` settings for fields

= 0.4 =
* Decreased priority of `show_user_profile` and `edit_user_profile` actions, to let other plugins (e.g. User Photo) put their bits in the 'About the user'-headed section
* Added `slt_cf_field_key` function
* Added `slt_cf_all_field_values` function
* Added `slt_cf_populate_options` filter for custom option types
* Added `prefix` and `suffix` settings for fields
* Added `slt_cf_default_id` function for better default ID handling
* Changed datepicker formatting setting so it can be overridden on a per-field basis
* Added `OBJECT_ID` placeholder for `options_query`
* Fixed bug that creates an `optgroup` tag when an options value is zero (thanks Daniele!)

= 0.3.1 =
* Fixed an error in `slt_cf_check_scope` handling

= 0.3 =
* Added support for assigning fields to posts with certain taxonomy terms assigned to them
* Added `group_options` setting for fields
* Added jQuery datepicker for date field type
* Altered interaction with AJAX requests to prevent 'undefined function' errors
* Added `slt_cf_check_scope` and `slt_cf_pre_save_value` filter hooks
* New built-in scope matches against post or user IDs
* Multiple values allowed for box type

= 0.2 =
* Added support for user profile custom fields
* Added check for duplicate field names in post meta boxes
* Improved initialization and interaction with hooks
* Added `users` value for `options_type`, to populate a field with users
* Added output options for `slt_cf_field_value`
* Added `css_url` setting to override default styles

= 0.1 =
* First version
