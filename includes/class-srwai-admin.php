<?php
if (!defined('ABSPATH')) exit;
class SRWAI_Admin {
 public static function init(){add_action('admin_menu',array(__CLASS__,'menu'));add_action('admin_enqueue_scripts',array(__CLASS__,'assets'));}
 public static function menu(){
  add_menu_page('SR All Import','SR All Import','manage_options','srwai-import',array(__CLASS__,'import_page'),'dashicons-database-import',26);
  add_submenu_page('srwai-import','Import','Import','manage_options','srwai-import',array(__CLASS__,'import_page'));
  add_submenu_page(null,'Field Mapping','Field Mapping','manage_options','srwai-mapping',array(__CLASS__,'mapping_page'));
  add_submenu_page(null,'Import Progress','Import Progress','manage_options','srwai-progress',array(__CLASS__,'progress_page'));
  add_submenu_page('srwai-import','Export','Export','manage_options','srwai-export',array('SRWAI_Exporter','page'));
  add_submenu_page('srwai-import','Delete Posts','Delete Posts','manage_options','srwai-delete',array('SRWAI_Delete','page'));
  add_submenu_page('srwai-import','Documentation','Documentation','manage_options','srwai-docs',array(__CLASS__,'docs_page'));
 }
 public static function assets($hook){
  if(strpos($hook,'srwai')===false)return;
  wp_enqueue_style('srwai-bootstrap',SRWAI_URL.'admin/css/bootstrap.min.css',array(),SRWAI_VERSION);
  wp_enqueue_style('srwai-admin',SRWAI_URL.'admin/css/admin.css',array('srwai-bootstrap'),SRWAI_VERSION);
  wp_enqueue_script('srwai-admin',SRWAI_URL.'admin/js/admin.js',array('jquery'),SRWAI_VERSION,true);
  wp_localize_script('srwai-admin','SRWAI',array('ajaxurl'=>admin_url('admin-ajax.php'),'nonce'=>wp_create_nonce('srwai_nonce'),'importUrl'=>admin_url('admin.php?page=srwai-import')));
 }
 private static function head($title){echo '<div class="wrap srwai-wrap row"><div class="srwai-hero col-md-8 col-sm-12"><div class="small-brand">All Import, Export & Bulk Deletion Plugin By Sifur Rahaman</div><h1>'.esc_html($title).'</h1><p>Responsive importer and exporter for WordPress data.</p></div><div class="srwai-hero col-md-3 col-sm-12"><div class="small-brand">By Sifur Rahaman</div><h1>Donate Me</h1><a href="https://sifurrahaman.com/donate/" class="btn btn-primary">Donate Me</a></div>';}
 private static function foot(){echo '</div>';}
 private static function page_url($page,$job_id=''){ $url=admin_url('admin.php?page='.$page); if($job_id!=='')$url=add_query_arg('job_id',$job_id,$url); return $url; }
 public static function import_page(){
  if(!current_user_can('manage_options'))return;
  // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- job_id only selects a read-only admin view; no state-changing action occurs here.
  $job_id=sanitize_text_field(wp_unslash($_GET['job_id']??''));
  $job=$job_id?get_option('srwai_job_'.$job_id):false;
  $has_job=is_array($job)&&!in_array($job['status'],array('complete','error'),true);
  self::head('Import Data');
  $types=SRWAI_Data_Importer::post_types();
  ?>
<div class="container-fluid px-0">
	<div class="row g-4">
		<div class="col-12 col-xl-8">
			<div class="card srwai-card">
				<div class="card-body p-4">
				<h2 class="h4">Import Setup</h2>
				<p class="alert alert-success mt-4 mb-0">Continue reads only the header and a small sample. Actual rows are processed later in batches. So the larg file import smoothly without any disconnecting server. Use batch size <b>"20-50"</b> for smoothly import larg file all data.</p>
				<p class="alert alert-danger mt-4 mb-0"><b>Important:</b> If you select post type a time and click to <b>Continue to Fields Mapping</b> button then it is save in your browser cache. It is not automatically removed when you click <b>Back to Import Setup</b> button. So need to clear your browser cache or click <b>SR All Import</b> Menu or any other post type menu. Then your page is refresh and now you can change post type newly.</p>
	<form id="srwai-import-form" enctype="multipart/form-data">
     <?php if($has_job): ?><input type="hidden" name="existing_job_id" value="<?php echo esc_attr($job_id); ?>"><?php endif; ?>
     <input type="hidden" name="action" value="srwai_create_job">
     <input type="hidden" name="nonce" value="<?php echo esc_attr(wp_create_nonce('srwai_nonce')); ?>">
     <div class="row g-3">
		<div class="col-12 col-md-6">
			<label class="form-label fw-semibold">Post Type</label>
			<select class="form-select" name="post_type" required>
				<option value="">Select Post Type</option><?php foreach($types as $t):?><option value="<?php echo esc_attr($t->name);?>" <?php selected($has_job?$job['post_type']:'',$t->name); ?>><?php echo esc_html($t->labels->singular_name.' ('.$t->name.')');?>
				</option>
				<?php endforeach;?>
			</select>
		</div>
      <div class="col-12 col-md-6">
		<label class="form-label fw-semibold">Post Status</label>
		<select class="form-select" name="post_status">
			<option value="publish" <?php selected($has_job?$job['settings']['post_status']:'publish','publish'); ?>>Published</option>
			<option value="draft" <?php selected($has_job?$job['settings']['post_status']:'','draft'); ?>>Draft</option>
			<option value="pending" <?php selected($has_job?$job['settings']['post_status']:'','pending'); ?>>Pending</option>
			<option value="private" <?php selected($has_job?$job['settings']['post_status']:'','private'); ?>>Private</option>
		</select>
	</div>
      <div class="col-12">
		<label class="form-label fw-semibold">Source File</label>
		<input class="form-control" type="file" name="source_file" accept=".csv,.xlsx,.xml,text/csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,text/xml,application/xml" <?php echo $has_job?'':'required'; ?>>
			<div class="form-text">
				<?php if($has_job): ?>
				<strong>Current source:</strong> 
				<?php echo esc_html($job['source_name']); ?> — the uploaded file is already saved for this import job. Leave the file field empty to keep it.<br><?php endif; ?>CSV, XLSX and XML files are supported. URL scraping/import has been removed.
			</div>
		</div>
		<div class="col-12 col-md-4">
			<label class="form-label fw-semibold">Batch Size</label>
			<input class="form-control" type="number" name="batch_size" value="<?php echo esc_attr($has_job?$job['settings']['batch_size']:20); ?>" min="1" max="100">
		</div>
		  <div class="col-12 col-md-8">
			  <div class="form-check mt-md-4">
				  <input class="form-check-input" type="checkbox" name="import_images" value="1" <?php checked($has_job?$job['settings']['import_images']:true); ?> id="srwai-images">
				  <label class="form-check-label" for="srwai-images">Import Images</label>
			  </div>
			  <div class="form-check">
				  <input class="form-check-input" type="checkbox" name="download_images" value="1" <?php checked($has_job?$job['settings']['download_images']:true); ?> id="srwai-download">
				  <label class="form-check-label" for="srwai-download">Download Images to Media Library</label>
			  </div>
			  <div class="form-check">
				  <input class="form-check-input" type="checkbox" name="update_existing" value="1" <?php checked($has_job?$job['settings']['update_existing']:false); ?> id="srwai-update">
				  <label class="form-check-label" for="srwai-update">Update Existing Posts (matched by title)</label>
			  </div>
		  </div>
     </div>
	 <button class="btn btn-success btn-lg mt-4" type="submit">Continue to Field Mapping</button>
	</form>
		</div>
	</div>
</div>
<div class="col-12 col-xl-4">
	<div class="card srwai-card">
		<div class="card-body p-4">
			<h3 class="h5">Workflow</h3>
			<ol class="mb-0 ps-3">
				<li class="mb-2">Select post type</li>
				<li class="mb-2">Upload CSV / XLSX / XML</li>
				<li class="mb-2">Inspect headers + sample only</li>
				<li class="mb-2">Map fields</li>
				<li class="mb-2">Start batch import</li>
				<li>Review results</li>
			</ol>
			<div class="alert alert-success mt-4 mb-0">
				<h3 class="h5">Donate Me</h3>
					<a href="https://sifurrahaman.com/donate/" target="_blank" class="text-white btn btn-success">Donate Me</a>
			</div>
		</div>
	</div>
</div>
</div>
</div>
  <?php self::foot();
 }
 public static function mapping_page(){
  if(!current_user_can('manage_options'))return;
  // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- job_id only selects a read-only admin view; no state-changing action occurs here.
  $id=sanitize_text_field(wp_unslash($_GET['job_id']??''));
  $job=$id?get_option('srwai_job_'.$id):false;
  if(!is_array($job)){self::head('Field Mapping');echo '<div class="alert alert-danger">Import file not found.</div>';self::foot();return;}
  self::head('Field Mapping');
  echo '<div id="srwai-mapping-page" data-job-id="'.esc_attr($id).'"><div class="card srwai-card"><div class="card-body p-4"><div class="text-secondary">Loading field mapping...</div></div></div></div>';
  self::foot();
 }
 public static function progress_page(){
  if(!current_user_can('manage_options'))return;
  // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- job_id only selects a read-only admin view; no state-changing action occurs here.
  $id=sanitize_text_field(wp_unslash($_GET['job_id']??''));
  $job=$id?get_option('srwai_job_'.$id):false;
  if(!is_array($job)){self::head('Import Progress');echo '<div class="alert alert-danger">Import job not found.</div>';self::foot();return;}
  self::head('Import Progress');
  echo '<div id="srwai-progress-page" data-job-id="'.esc_attr($id).'"><div class="srwai-import-progress-card"><div class="srwai-progress-top"><div><div class="small-brand">Sifurrahman DataSync Importer</div><h2 class="h3 mb-1 srwai-progress-title">Ready to import</h2><div class="srwai-progress-percent">0%</div><div class="srwai-progress-count">0 / 0 rows</div></div><div class="srwai-progress-actions"><button type="button" class="button button-secondary" id="srwai-summary-btn">Summary</button><button type="button" class="button button-secondary" id="srwai-failed-btn">Failed Media</button></div></div><div class="progress srwai-progress-bar-wrap" role="progressbar"><div class="progress-bar" id="srwai-progress-bar" style="width:0%">0%</div></div><div class="srwai-progress-meta"><span id="srwai-progress-time">Elapsed: 00:00:00</span><span id="srwai-progress-file"></span></div><div class="srwai-stat-grid"><div><span>PROCESSED</span><strong id="srwai-stat-processed">0</strong></div><div><span>TOTAL RECORDS</span><strong id="srwai-stat-total">0</strong></div><div><span>IMPORTED</span><strong id="srwai-stat-imported">0</strong></div><div><span>UPDATED</span><strong id="srwai-stat-updated">0</strong></div><div><span>SKIPPED</span><strong id="srwai-stat-skipped">0</strong></div><div><span>REMAINING</span><strong id="srwai-stat-remaining">0</strong></div></div><div class="srwai-progress-controls"><button type="button" class="button button-primary button-hero" id="srwai-push-import">Push Import</button></div><div id="srwai-progress-message" class="mt-3"></div></div></div>';
  self::foot();
 }
 public static function docs_page(){if(!current_user_can('manage_options'))return;self::head('Documentation');echo '<div class="row g-4"><div class="col-12 col-xl-8"><div class="card srwai-card"><div class="card-body p-4"><h2 class="h4">Sifurrahaman All Import, Export & Deletion Plugin</h2><p>Structured CSV, XLSX and XML importer/exporter for WordPress post types, custom fields and taxonomies.</p><h3 class="h5 mt-4">Supported mapping</h3><ul><li>Standard WordPress post fields</li><li>Detected post meta from JetEngine, ACF and custom code</li><li>All taxonomies attached to the selected post type</li><li>Featured images and image URLs</li><li>Duplicate matching by normalized title</li></ul><h3 class="h5 mt-4">Features</h3><ul><li>Import from XLSX, CSV, and XML file ✅</li><li>Import custom fields data ✅</li><li>Import JetEngine custom fields data ✅</li><li>Import ACF custom fields data ✅</li><li>Import Taxonomies ✅</li><li>Import Featured images ✅</li><li>Media Library download ✅</li><li>Duplicate detection ✅</li><li>Update existing selected post type data ✅</li><li>Batch import ✅</li><li>Stop/Pause/Resume Import ✅</li><li>AJAX workflow ✅</li><li>Export any post type data ✅</li><li>Bulk delete any post type data ✅</li><li>Back to Setup/Mapping fix ✅</li></ul><h3 class="h5 mt-4">File formats</h3><p>CSV, XLSX and XML are supported. URL scraping/import is intentionally not included.</p><h3 class="h5 mt-4">Large files</h3><p>Continue reads only the header and a small sample. Actual rows are processed later in batches. So the larg file import smoothly without any disconnecting server. Use batch size <b>"20-50"</b> for smoothly import larg file all data.</p><h3 class="h5 mt-4">Delete Posts</h3><p>The Delete Posts page shows the current count and lets an administrator delete a specified number of the oldest or newest posts.</p></div></div></div><div class="col-12 col-xl-4"><div class="card srwai-card"><div class="card-body p-4"><h3 class="h5">Workflow</h3><ol class="mb-0 ps-3"><li class="mb-2">Select post type</li><li class="mb-2">Upload CSV / XLSX / XML</li><li class="mb-2">Inspect headers + sample only</li><li class="mb-2">Map fields</li><li class="mb-2">Start batch import</li><li>Review results</li></ol><div class="alert alert-success mt-4 mb-0"><h3 class="h5">Donate Me</h3><a href="https://sifurrahaman.com/donate/" target="_blank" class="text-white btn btn-success">Donate Me</a></div></div></div></div>';self::foot();}
}
