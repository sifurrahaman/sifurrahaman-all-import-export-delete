=== Sifurrahaman All Import, Export & Bulk Deletion ===
Contributors: sifurrahaman
Donate link: https://sifurrahaman.com/donate/
Tags: all import, export, csv xlsx xml importer, custom fields, custom post type
Requires at least: 6.0
Requires PHP: 7.4
Tested up to: 7.1
Stable tag: 1.1.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Import and export CSV, XLSX and XML data into WordPress post types with custom fields and taxonomy mapping. Also supports bulk deletion of data for any CPT.

== Description ==

Sifurrahaman All Import, Export & Bulk Deletion helps administrators import structured data into WordPress post types using CSV, XLSX and XML files & bulk post deletion.

The plugin provides flexible field mapping for standard WordPress fields, custom post meta, JetEngine fields, ACF fields and taxonomies. It also supports featured image importing, batch processing, import progress management, data export and bulk post deletion.

The importer detects available fields dynamically based on the selected post type. When JetEngine or ACF is active, the plugin can also detect their registered field definitions for mapping.

Imports are processed in batches to help handle larger datasets. Import jobs can be pushed, paused, stopped and resumed from the administrator interface.

Duplicate and update matching uses normalized post titles. Source URLs are not saved as imported post data.

The plugin does not require JetEngine or ACF and can be used with standard WordPress post types and post meta.

== Features ==

* Import CSV files
* Import XLSX files
* Import XML files
* Standard WordPress field mapping
* Dynamic custom post meta detection
* JetEngine field detection when JetEngine is active
* ACF field detection when ACF is active
* Dynamic taxonomy detection
* Featured image importing from supported image URLs
* Media Library image support
* Batch import processing
* Push Import control
* Pause Import control
* Stop Import control
* Resume Import control
* Saved import progress
* Duplicate detection by normalized post title
* Optional updating of existing posts
* CSV export
* XLSX export
* XML export
* Bulk post deletion
* Imported-image cleanup support during deletion
* Responsive WordPress administrator interface
* Bootstrap-based administrator interface

== Source Code and Development ==

The plugin also includes the Bootstrap stylesheet at `admin/css/bootstrap.min.css`. Bootstrap is a third-party library. Its source code and project documentation are publicly available in the official repository: https://github.com/twbs/bootstrap. Refer to that repository for the original source and build instructions.

The complete source code for this plugin is publicly available on GitHub:

https://github.com/sifurrahaman/sifurrahaman-all-import-export-delete

The plugin's JavaScript and CSS source files are included in the repository in human-readable form for review, study, and modification.

Development contributions and bug reports are welcome.

== Installation ==

1. Install Sifurrahaman All Import, Export & Bulk Deletion from Plugins > Add New, or upload the plugin ZIP through Plugins > Add New > Upload Plugin.
2. Activate the plugin.
3. Open SR All Import from the WordPress admin menu.
4. Select the WordPress post type you want to import into.
5. Select and upload a CSV, XLSX or XML source file.
6. Continue to Field Mapping.
7. Map source fields to the available WordPress fields, custom fields and taxonomies.
8. Configure the import options.
9. Start the import and monitor its progress from the Import Progress page.

== Custom Fields ==

Sifurrahaman All Import, Export & Bulk Deletion uses WordPress post meta APIs for custom field data.

When JetEngine is active, the plugin can detect available JetEngine field definitions associated with the selected post type.

When ACF is active, the plugin can detect available ACF field definitions associated with the selected post type.

JetEngine and ACF are optional. The importer does not require either plugin to import standard WordPress fields and custom post meta.

== Supported Files ==

The plugin supports the following structured data formats:

* CSV
* XLSX
* XML

The plugin can also import supported image URLs as featured images when image importing is enabled.

The plugin does not perform source URL scraping or extract additional post data from remote webpages. CSV, XLSX and XML files are processed as structured data sources.

== Field Mapping ==

During the mapping step, the plugin detects available fields for the selected post type.

Depending on the installed plugins and post type configuration, available mapping targets can include:

* Post title
* Post content
* Post excerpt
* Post date
* Post status
* Author
* Featured image
* Registered post meta
* Custom post meta
* JetEngine fields
* ACF fields
* Registered taxonomies

Field mappings are saved as part of the import job and are used during batch processing.

== XLSX Support ==

For XLSX files, the plugin reads the worksheet structure and header information during field mapping.

A small sample of the worksheet can be inspected to identify available fields before the complete dataset is processed.

The complete import is processed later in batches.

== Images ==

When image importing is enabled, the plugin can download supported image URLs and add them to the WordPress Media Library.

Imported images can be assigned as featured images for the corresponding posts.

The plugin also provides imported-image cleanup support when posts are removed through the plugin's bulk deletion functionality.

== Import Processing ==

Imports are processed in batches.

The Import Progress page provides controls for managing an active import, including:

* Push Import
* Pause Import
* Stop Import
* Resume Import

The plugin stores the import position so that a stopped import can continue from its saved position instead of restarting from the beginning.

Batch processing is designed to make larger imports easier to manage within the WordPress administrator.

== Duplicate and Update Handling ==

The plugin uses normalized post titles when checking for existing posts.

When updating existing posts is enabled, matching posts can be updated during the import.

The source URL is not used as the primary duplicate identifier and source URLs are not saved into imported post data.

== Export ==

CPT Fields Importer can export WordPress post data in the following formats:

* CSV
* XLSX
* XML

Export functionality is available from the plugin's administrator interface.

== Bulk Post Deletion ==

The plugin provides a bulk deletion tool for removing posts from the selected post type.

Administrators can specify the number of posts to delete and choose whether the oldest or newest posts should be removed.

The deletion functionality also includes support for cleaning up imported images when appropriate.

Administrators should review the selected post type and deletion quantity carefully before performing bulk deletion.

== Privacy ==

Sifurrahaman All Import, Export & Bulk Deletion does not send usage, analytics or tracking data to an external service.

Source files uploaded for an import are stored temporarily in the WordPress uploads directory while the import job is being processed.

Temporary source files are removed when the import job completes or fails.

Source URLs are not stored as imported post data or imported post meta.

If image importing is enabled, image URLs provided in the source data may be accessed to download the corresponding images into the site's WordPress Media Library.

The plugin does not collect personal information from users for external analytics or tracking purposes.

== Third-Party Assets ==

The administrator interface includes Bootstrap CSS distributed under the MIT License.

Source: https://github.com/twbs/bootstrap

Bootstrap is used for responsive administrator interface styling.

== Frequently Asked Questions ==

= Does the plugin require JetEngine or ACF? =

No.

Sifurrahaman All Import, Export & Bulk Deletion can work with standard WordPress post types and custom post meta without JetEngine or ACF.

When JetEngine or ACF is active, their available field definitions can also be detected for field mapping.

= Which file formats are supported? =

CSV, XLSX and XML files are supported.

= Can I import featured images? =

Yes.

When image importing is enabled, supported image URLs in the source data can be downloaded to the WordPress Media Library and assigned as featured images.

= Can I update existing posts? =

Yes.

You can enable updating existing posts during the import.

Duplicate matching uses normalized post titles rather than source URLs.

= How does duplicate detection work? =

The importer normalizes post titles when checking for existing posts.

Source URLs are not used as the primary duplicate identifier.

= Can a large import be paused? =

Yes.

Imports are processed in batches and can be paused or stopped. A stopped import can later be resumed from the saved import position.

= Can I export imported posts? =

Yes.

The plugin supports CSV, XLSX and XML export.

= Can I delete posts in bulk? =

Yes.

The plugin includes a bulk deletion tool that allows administrators to specify a deletion quantity and select whether the oldest or newest posts should be deleted.

= Are imported images cleaned up when posts are deleted? =

The bulk deletion functionality includes support for cleaning up images imported by the plugin when appropriate.

= Are source URLs saved in imported posts? =

No.

Source URLs are not saved as imported post data or post meta.

= Does the plugin scrape webpages from source URLs? =

No.

The plugin imports structured data from CSV, XLSX and XML files. It does not scrape webpages or extract additional information from remote webpages.

== Screenshots ==

1. Import data screenshot-1.png. Screenshots are stored in the /assets directory.
2. Custom fields mapping screenshot-2.png. Screenshots are stored in the /assets directory.
3. Start importing screenshot-3.png. Screenshots are stored in the /assets directory.
4. Export data screenshot-4.png. Screenshots are stored in the /assets directory.
5. Bulk post deletion screenshot-5.png. Screenshots are stored in the /assets directory.

== Upgrade Notice ==

= 1.1.1 =

* Reformatted admin/js/admin.js into a readable, multiline JavaScript file.
* Added a Source Code and Development section to readme.txt.
* Documented the official Bootstrap source repository.
* Updated the plugin version and readme stable tag to 1.1.1.
* Verified the ZIP archive integrity and JavaScript syntax.

= 1.1.0 =

* Updated the plugin display name for the current Sifurrahaman All Import, Export & Bulk Deletion release.
* Updated the text domain & slug to sifurrahaman-all-import-export-delete
* Finalized WordPress.org compatibility and code-standard cleanup.
* Improved administrator-facing functionality and documentation.

= 1.0.25 =

* Updated the plugin for the current CPT Fields Importer release.
* Finalized WordPress.org compatibility and code-standard cleanup.
* Improved administrator-facing functionality and documentation.

= 1.0.20 =

* Final Plugin Check security scan cleanup.

= 1.0.19 =

* Final Plugin Check compatibility cleanup for exception handling.

= 1.0.18 =

* Finalized WordPress.org security and code-standard compatibility fixes.

== Changelog ==

= 1.1.1 =

* Distributed the custom administrator JavaScript in human-readable form.
* Documented the JavaScript source location and third-party Bootstrap source repository.

= 1.1.0 =

* Updated the plugin display name, for the current Sifurrahaman All Import, Export & Bulk Deletion release.
* Updated the text domain & slug to sifurrahaman-all-import-export-delete
* Finalized WordPress.org compatibility and code-standard cleanup.
* Improved administrator-facing functionality and documentation.

= 1.0.25 =

* Updated the plugin for the current CPT Fields Importer release.
* Finalized WordPress.org compatibility and code-standard cleanup.
* Improved administrator-facing functionality and documentation.
* Preserved CSV, XLSX and XML import/export, field mapping, batch processing, image handling and bulk deletion functionality.

= 1.0.20 =

* Final Plugin Check security scan cleanup.

= 1.0.19 =

* Final Plugin Check compatibility cleanup for exception handling.

= 1.0.18 =

* Finalized WordPress.org security and code-standard compatibility fixes.

= 1.0.17 =

* Renamed the public plugin to CPT Fields Importer.
* Updated the plugin slug and text domain to cpt-fields-importer.
* Improved input unslashing, sanitization and mapping validation.
* Improved output escaping and binary export handling.
* Replaced discouraged filesystem operations where WordPress APIs are appropriate.
* Improved CSV/XLSX temporary-file handling.
* Improved database access patterns for post and attachment checks.
* Preserved CSV, XLSX, XML import, field mapping, batch import, image, export and delete functionality.

= 1.0.16 =

* Prepared the plugin for WordPress.org submission.
* Updated the public plugin documentation and submission metadata.
* Updated plugin and readme version information to 1.0.16.
* Updated installation, feature, privacy and FAQ documentation.
* Corrected the readme stable tag and changelog information.

= 1.0.15 =

* Fixed Back to Import Setup state restoration and field mapping reload behavior.
* Fixed changing the source file from the setup page so the new file fields are loaded without a page refresh.
* Preserved Push, Stop, Resume and automatic batch import behavior.

= 1.0.11 =

* Fixed XLSX record counting so all data rows are counted instead of every second row.
* Added separate Field Mapping and Import Progress admin pages.
* Added Push Import, Pause Import and Resume Import controls with saved import position.
* Added full-width card override for WordPress admin cards.
* Added progress statistics and elapsed-time display for large imports.

= 1.0.9 =

* Fixed DD-MM-YYYY datetime imports such as 30-09-2027 2:18:00 PM.
* Fixed numeric custom fields receiving values such as 100 KG; number fields now import 100 and remove unit text.
* Added numeric/date-time field type detection for WordPress registered meta, JetEngine and ACF.

= 1.0.8 =

* Rebuilt XLSX field/header parsing using XMLReader so shared-string column names are returned as actual headers.
* Removed source URL scraping/import functionality. CSV files are imported as structured CSV data only.
* Reworked Delete Posts to show the current count, accept a deletion quantity, and delete the oldest or newest posts as selected.
* Preserved safe imported-image cleanup.
* Strengthened the full-width Field Mapping layout.

= 1.0.7 =

* Fixed XLSX shared-string header decoding.
* Improved dynamic field detection and full-width mapping.
