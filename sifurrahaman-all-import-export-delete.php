<?php
/**
 * Plugin Name: Sifurrahaman All Import, Export & Bulk Deletion
 * Description: Sifurrahaman All Import, Export & Bulk Deletion plugin for any post type, Import from CSV, XLSX and XML with dynamic post meta and taxonomy mapping. Bulk post deletion with imported image cleanup.
 * Version: 1.1.1
 * Author: Sifur Rahaman
 * Author URI: https://sifurrahaman.com/
 * Plugin URI: https://sifurrahaman.com/sr-plugins/sifurrahaman-all-import-export-delete
 * Text Domain: sifurrahaman-all-import-export-delete
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * License: GPL-2.0-or-later
 */
if (!defined('ABSPATH')) exit;
define('SRWAI_VERSION','1.1.2');
define('SRWAI_FILE',__FILE__);
define('SRWAI_DIR',plugin_dir_path(__FILE__));
define('SRWAI_URL',plugin_dir_url(__FILE__));
foreach (array('class-srwai.php','class-srwai-parser.php','class-srwai-media.php','class-srwai-importer.php','class-srwai-exporter.php','class-srwai-delete.php','class-srwai-admin.php') as $srwai_file) require_once SRWAI_DIR.'includes/'.$srwai_file;
register_activation_hook(__FILE__,array('SRWAI_Data_Importer','activate'));
add_action('plugins_loaded',array('SRWAI_Data_Importer','init'));
