<?php
if (!defined('ABSPATH')) exit;
class SRWAI_Importer {
 public static function init(){
  add_action('wp_ajax_srwai_create_job',array(__CLASS__,'create_job'));
  add_action('wp_ajax_srwai_get_mapping',array(__CLASS__,'get_mapping'));
  add_action('wp_ajax_srwai_save_mapping',array(__CLASS__,'save_mapping'));
  add_action('wp_ajax_srwai_job_status',array(__CLASS__,'job_status'));
  add_action('wp_ajax_srwai_start_job',array(__CLASS__,'start_job'));
  add_action('wp_ajax_srwai_pause_job',array(__CLASS__,'pause_job'));
  add_action('wp_ajax_srwai_reopen_mapping',array(__CLASS__,'reopen_mapping'));
 }
 private static function check(){if(!current_user_can('manage_options'))wp_send_json_error('Unauthorized.',403);check_ajax_referer('srwai_nonce','nonce');}
 private static function post_value($key,$default=''){
 
  if(!isset($_POST[$key]))return $default;
 
  $value=wp_unslash($_POST[$key]);
  return is_scalar($value)?(string)$value:$default;
 }
 private static function uploaded_file($key){
 
  return isset($_FILES[$key])&&is_array($_FILES[$key])?$_FILES[$key]:array();
 }
 private static function sanitize_mapping($mapping,$job){
  if(!is_array($mapping))return array();
  $allowed_standard=array('post_title','post_content','post_excerpt','post_status','post_date','post_author');
  $clean=array('standard'=>array(),'meta'=>array(),'taxonomies'=>array(),'featured_image'=>'');
  foreach($allowed_standard as $key){
   if(isset($mapping['standard'][$key])&&is_scalar($mapping['standard'][$key]))$clean['standard'][$key]=sanitize_text_field((string)$mapping['standard'][$key]);
  }
  $allowed_meta=isset($job['target']['meta'])&&is_array($job['target']['meta'])?$job['target']['meta']:array();
  foreach($allowed_meta as $key){
   $key=(string)$key;
   if(isset($mapping['meta'][$key])&&is_scalar($mapping['meta'][$key]))$clean['meta'][$key]=sanitize_text_field((string)$mapping['meta'][$key]);
  }
  $allowed_tax=isset($job['target']['taxonomies'])&&is_array($job['target']['taxonomies'])?$job['target']['taxonomies']:array();
  foreach($allowed_tax as $key){
   $key=(string)$key;
   if(isset($mapping['taxonomies'][$key])&&is_scalar($mapping['taxonomies'][$key]))$clean['taxonomies'][$key]=sanitize_text_field((string)$mapping['taxonomies'][$key]);
  }
  if(isset($mapping['featured_image'])&&is_scalar($mapping['featured_image']))$clean['featured_image']=sanitize_text_field((string)$mapping['featured_image']);
  return $clean;
 }
 private static function upload_source($file){
  if(empty($file['tmp_name'])||!is_uploaded_file($file['tmp_name']))throw new Exception('Uploaded file was not received correctly.');
  $ext=strtolower(pathinfo($file['name'],PATHINFO_EXTENSION));
  if(!in_array($ext,array('csv','xlsx','xml'),true))throw new Exception('Supported files: CSV, XLSX and XML.');

  // Use WordPress's upload API for WordPress.org compatibility.
  require_once ABSPATH.'wp-admin/includes/file.php';

  $dir=SRWAI_Data_Importer::uploads_dir();
  if(!is_dir($dir)||!wp_is_writable($dir))throw new Exception('Could not save the uploaded source file. Check the uploads directory permissions.');

  $upload_dir_filter=function($uploads)use($dir){
   $uploads['path']=$dir;
   $uploads['url']=trailingslashit($uploads['basedir']).'sifurrahman-datasync-importer';
   $uploads['subdir']='/sifurrahman-datasync-importer';
   $uploads['basedir']=trailingslashit(dirname($dir));
   $uploads['baseurl']=trailingslashit($uploads['baseurl']);
   $uploads['error']=false;
   return $uploads;
  };
  add_filter('upload_dir',$upload_dir_filter);
  $sideload_file=array(
   'name'=>sanitize_file_name($file['name']),
   'type'=>$file['type']??'',
   'tmp_name'=>$file['tmp_name'],
   'error'=>(int)($file['error']??0),
   'size'=>(int)($file['size']??0)
  );
  $result=wp_handle_sideload(
   $sideload_file,
   array(
    'test_form'=>false,
    'test_type'=>false,
    'mimes'=>array(
     'csv'=>'text/csv',
     'xlsx'=>'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
     'xml'=>'application/xml'
    )
   )
  );
  remove_filter('upload_dir',$upload_dir_filter);

  if(!empty($result['error']))throw new Exception('Could not save the uploaded source file: '.(string)$result['error']);
  if(empty($result['file']))throw new Exception('Could not save the uploaded source file. Check the uploads directory permissions.');

  return array($result['file'],$ext,basename($result['file']));
 }
 private static function target_data($type){
  $taxonomy_objects=SRWAI_Data_Importer::taxonomies($type);$taxonomy_names=array();
  foreach((array)$taxonomy_objects as $tax){if(is_object($tax)&&isset($tax->name))$taxonomy_names[]=(string)$tax->name;elseif(is_string($tax)&&$tax!=='')$taxonomy_names[]=$tax;}
  return array('standard'=>array('post_title'=>'Title','post_content'=>'Content','post_excerpt'=>'Excerpt','post_status'=>'Status','post_date'=>'Date','post_author'=>'Author'),'meta'=>array_values(SRWAI_Data_Importer::meta_keys($type)),'taxonomies'=>array_values(array_unique($taxonomy_names)));
 }
 private static function public_job($job){
  return array('job_id'=>$job['id'],'status'=>$job['status'],'position'=>(int)$job['position'],'total'=>$job['total']===null?null:(int)$job['total'],'created'=>(int)$job['created'],'updated'=>(int)$job['updated'],'skipped'=>(int)$job['skipped'],'errors'=>(array)$job['errors'],'source_name'=>$job['source_name']??'','source_type'=>$job['ext']??'','created_at'=>(int)($job['created_at']??0),'started_at'=>(int)($job['started_at']??0),'finished_at'=>(int)($job['finished_at']??0));
 }
 public static function create_job(){self::check();try{
  $existing_id=sanitize_text_field(self::post_value('existing_job_id'));
  $existing=$existing_id?get_option('srwai_job_'.$existing_id):false;
  if($existing_id && !is_array($existing))throw new Exception('Import job not found.');
  if($existing_id && is_array($existing) && in_array($existing['status'],array('complete','error'),true))throw new Exception('This import job is already finished.');

  $type=sanitize_key(self::post_value('post_type'));if(!$type||!post_type_exists($type))throw new Exception('Invalid post type.');
  $settings=array('post_status'=>sanitize_key(self::post_value('post_status','publish')),'batch_size'=>max(1,min(100,absint(self::post_value('batch_size',20)))),'import_images'=>!empty(self::post_value('import_images')),'download_images'=>!empty(self::post_value('download_images')),'update_existing'=>!empty(self::post_value('update_existing')));

  // When returning from Field Mapping, an empty file means: keep the current source and job.
  if($existing_id && empty(self::uploaded_file('source_file')['tmp_name'])){
   $existing['post_type']=$type;$existing['settings']=$settings;
   $existing['target']=self::target_data($type);
   update_option('srwai_job_'.$existing_id,$existing,false);
   wp_send_json_success(array('job_id'=>$existing_id,'redirect'=>admin_url('admin.php?page=srwai-mapping&job_id='.rawurlencode($existing_id))));
  }

  if(empty(self::uploaded_file('source_file')))throw new Exception('Please choose a source file.');
  list($path,$ext,$name)=self::upload_source(self::uploaded_file('source_file'));
  try{$info=SRWAI_Parser::inspect($path,$ext,3);if(empty($info['headers']))throw new Exception('No fields were detected in the source file.');}catch(Throwable $e){wp_delete_file($path);throw new Exception('Source inspection failed: '.sanitize_text_field($e->getMessage()));}

  if($existing_id && is_array($existing)){
   // Replace the source on the same job so Back -> Setup -> new file never leaves stale headers behind.
   if(!empty($existing['source_path']) && $existing['source_path']!==$path && file_exists($existing['source_path']))wp_delete_file($existing['source_path']);
   $existing['post_type']=$type;$existing['source_path']=$path;$existing['source_name']=$name;$existing['ext']=$ext;$existing['settings']=$settings;$existing['status']='mapping';
   $existing['mapping']=array();$existing['source_fields']=$info['headers'];$existing['target']=self::target_data($type);$existing['source_count']=isset($info['count'])?$info['count']:null;
   $existing['position']=0;$existing['total']=null;$existing['created']=0;$existing['updated']=0;$existing['skipped']=0;$existing['errors']=array();$existing['started_at']=0;$existing['finished_at']=0;
   update_option('srwai_job_'.$existing_id,$existing,false);
   wp_send_json_success(array('job_id'=>$existing_id,'redirect'=>admin_url('admin.php?page=srwai-mapping&job_id='.rawurlencode($existing_id))));
  }

  $id=wp_generate_uuid4();$target=self::target_data($type);
  $job=array('id'=>$id,'post_type'=>$type,'source_path'=>$path,'source_name'=>$name,'ext'=>$ext,'status'=>'mapping','settings'=>$settings,'mapping'=>array(),'source_fields'=>$info['headers'],'target'=>$target,'source_count'=>isset($info['count'])?$info['count']:null,'position'=>0,'total'=>null,'created'=>0,'updated'=>0,'skipped'=>0,'errors'=>array(),'created_at'=>time(),'started_at'=>0,'finished_at'=>0);
  update_option('srwai_job_'.$id,$job,false);
  wp_send_json_success(array('job_id'=>$id,'redirect'=>admin_url('admin.php?page=srwai-mapping&job_id='.rawurlencode($id))));
 }catch(Throwable $e){wp_send_json_error('Import setup failed: '.sanitize_text_field($e->getMessage()));}}
 public static function reopen_mapping(){self::check();$id=sanitize_text_field(self::post_value('job_id'));$job=get_option('srwai_job_'.$id);if(!is_array($job))wp_send_json_error('Import job not found.');if(in_array($job['status'],array('complete','error'),true))wp_send_json_error('This import job is already finished.');wp_send_json_success(array('redirect'=>admin_url('admin.php?page=srwai-mapping&job_id='.rawurlencode($id))));}
 public static function get_mapping(){self::check();$id=sanitize_text_field(self::post_value('job_id'));$job=get_option('srwai_job_'.$id);if(!is_array($job))wp_send_json_error('Import job not found.');wp_send_json_success(array('job_id'=>$job['id'],'count'=>$job['source_count'],'source_fields'=>$job['source_fields'],'target'=>$job['target'],'source_type'=>$job['ext'],'status'=>$job['status'],'settings'=>$job['settings']));}
 public static function save_mapping(){self::check();$id=sanitize_text_field(self::post_value('job_id'));$job=get_option('srwai_job_'.$id);if(!is_array($job))wp_send_json_error('Import job not found.');$mapping=json_decode(self::post_value('mapping','{}'),true);$mapping=self::sanitize_mapping($mapping,$job);if(empty($mapping['standard']['post_title']))wp_send_json_error('Please map the Title field before starting.');if(in_array($job['status'],array('complete','error'),true))wp_send_json_error('This import job is already finished.');$job['mapping']=$mapping;$job['status']='ready';update_option('srwai_job_'.$id,$job,false);wp_send_json_success(array('redirect'=>admin_url('admin.php?page=srwai-progress&job_id='.rawurlencode($id))));}
 private static function response_data($job,$created_batch=0,$updated_batch=0,$skipped_batch=0,$errors=array()){$total=(int)$job['total'];$position=(int)$job['position'];return array('position'=>$position,'total'=>$total,'status'=>$job['status'],'created'=>(int)$created_batch,'updated'=>(int)$updated_batch,'skipped'=>(int)$skipped_batch,'total_created'=>(int)$job['created'],'total_updated'=>(int)$job['updated'],'total_skipped'=>(int)$job['skipped'],'remaining'=>max(0,$total-$position),'errors'=>$errors,'source_name'=>$job['source_name']??'','started_at'=>(int)($job['started_at']??0),'finished_at'=>(int)($job['finished_at']??0));}
 public static function job_status(){self::check();$id=sanitize_text_field(self::post_value('job_id'));$job=get_option('srwai_job_'.$id);if(!is_array($job))wp_send_json_error('Import job not found.');wp_send_json_success(self::response_data($job));}
 public static function pause_job(){self::check();$id=sanitize_text_field(self::post_value('job_id'));$job=get_option('srwai_job_'.$id);if(!is_array($job))wp_send_json_error('Import job not found.');if($job['status']==='running'){$job['status']='paused';update_option('srwai_job_'.$id,$job,false);}wp_send_json_success(self::response_data($job));}
 public static function start_job(){
  self::check();$id=sanitize_text_field(self::post_value('job_id'));$job=get_option('srwai_job_'.$id);if(!is_array($job))wp_send_json_error('Import job not found.');
  if(empty($job['mapping']['standard']['post_title']))wp_send_json_error('Please map the Title field before starting.');
  if($job['status']==='complete')wp_send_json_success(self::response_data($job));
  if($job['status']==='error')wp_send_json_error('This import job is in an error state.');
  try{
   if($job['total']===null)$job['total']=SRWAI_Parser::count($job['source_path'],$job['ext']);
   if($job['status']==='paused'||$job['status']==='ready'){$job['status']='running';if(empty($job['started_at']))$job['started_at']=time();}
   if($job['status']!=='running'){$job['status']='running';if(empty($job['started_at']))$job['started_at']=time();}
   $batch=(int)$job['settings']['batch_size'];$rows=SRWAI_Parser::batch($job['source_path'],$job['ext'],(int)$job['position'],$batch);
   $created=0;$updated=0;$skipped=0;$errors=array();
   foreach($rows as $i=>$row){try{$r=self::import_row($job,$row);if($r==='created')$created++;elseif($r==='updated')$updated++;else$skipped++;}catch(Exception $e){$errors[]='Row '.($job['position']+$i+2).': '.sanitize_text_field($e->getMessage());}}
   $job['position']+=(int)count($rows);$job['created']+=$created;$job['updated']+=$updated;$job['skipped']+=$skipped;
   if($errors)$job['errors']=array_slice(array_merge($job['errors'],$errors),-50);
   if($job['position']>=(int)$job['total']||empty($rows)){$job['position']=(int)$job['total'];$job['status']='complete';$job['finished_at']=time();if(file_exists($job['source_path']))wp_delete_file($job['source_path']);}
   update_option('srwai_job_'.$id,$job,false);
   wp_send_json_success(self::response_data($job,$created,$updated,$skipped,$errors));
  }catch(Exception $e){$job['status']='error';$job['errors'][]=$e->getMessage();update_option('srwai_job_'.$id,$job,false);wp_send_json_error(sanitize_text_field($e->getMessage()));}
 }
 private static function value($row,$map,$key){$src=$map[$key]??'';return($src!==''&&array_key_exists($src,$row))?$row[$src]:'';}
 private static function parse_datetime($value){
  $value=trim((string)$value);if($value==='')return '';
  if(is_numeric($value)&&((float)$value>0)&&((float)$value<100000)){$serial=(float)$value;$base=new DateTime('1899-12-30',wp_timezone());$days=floor($serial);$seconds=(int)round(($serial-$days)*86400);if($seconds>=86400){$days++;$seconds-=86400;}$base->modify('+'.$days.' days');if($seconds)$base->modify('+'.$seconds.' seconds');return $base->format('Y-m-d H:i:s');}
  $formats=array('d-m-Y g:i:s A','d-m-Y g:i A','d-m-Y H:i:s','d-m-Y H:i','d/m/Y g:i:s A','d/m/Y g:i A','d/m/Y H:i:s','d/m/Y H:i','d.m.Y g:i:s A','d.m.Y g:i A','d.m.Y H:i:s','d.m.Y H:i','Y-m-d g:i:s A','Y-m-d g:i A','Y-m-d H:i:s','Y-m-d H:i','m-d-Y g:i:s A','m-d-Y g:i A','m-d-Y H:i:s','m-d-Y H:i','m/d/Y g:i:s A','m/d/Y g:i A','m/d/Y H:i:s','m/d/Y H:i','d-m-Y','d/m/Y','Y-m-d','m/d/Y','m-d-Y');$tz=wp_timezone();foreach($formats as $format){$dt=DateTime::createFromFormat('!'.$format,$value,$tz);$errors=DateTime::getLastErrors();if($dt&&($errors===false||($errors['warning_count']===0&&$errors['error_count']===0)))return $dt->format('Y-m-d H:i:s');}$ts=strtotime($value);return $ts!==false?wp_date('Y-m-d H:i:s',$ts,$tz):'';
 }
 private static function number_value($value){if(is_array($value))return ''; $value=trim((string)$value);if($value==='')return '';if(preg_match('/[-+]?(?:\d{1,3}(?:,\d{3})+|\d+)(?:\.\d+)?/',str_replace(' ','',$value),$match)){ $number=str_replace(',','',$match[0]);return is_numeric($number)?$number:'';}return '';}
 private static function is_number_type($type){$type=strtolower((string)$type);return in_array($type,array('number','numeric','decimal','float','integer','int'),true);}
 private static function is_datetime_type($type){$type=strtolower((string)$type);return in_array($type,array('datetime','date_time','date-time','date_time_picker','datetime-local','date_time_local'),true);}
 private static function import_row($job,$row){$m=$job['mapping'];$title=self::value($row,$m['standard']??array(),'post_title');if($title==='')$title=$row['title']??$row['post_title']??'';$title=wp_strip_all_tags((string)$title);if(trim($title)==='')throw new Exception('Title is empty.');$existing=SRWAI_Data_Importer::find_by_title($job['post_type'],$title);if($existing&&!$job['settings']['update_existing'])return 'skipped';$data=array('post_type'=>$job['post_type'],'post_title'=>$title,'post_content'=>wp_kses_post((string)self::value($row,$m['standard']??array(),'post_content')),'post_excerpt'=>wp_kses_post((string)self::value($row,$m['standard']??array(),'post_excerpt')),'post_status'=>$job['settings']['post_status']);$date=self::value($row,$m['standard']??array(),'post_date');if($date){$parsed_date=self::parse_datetime($date);if($parsed_date!==''){if($job['settings']['post_status']==='publish'&&strtotime($parsed_date)>current_time('timestamp'))$parsed_date=current_time('mysql');$data['post_date']=$parsed_date;$data['post_date_gmt']=get_gmt_from_date($parsed_date);}}$author=absint(self::value($row,$m['standard']??array(),'post_author'));if($author)$data['post_author']=$author;if($existing){$data['ID']=$existing;$post_id=wp_update_post(wp_slash($data),true);$result='updated';}else{$post_id=wp_insert_post(wp_slash($data),true);$result='created';}// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- This value is an exception message, not direct browser output; the AJAX response layer handles output serialization.
  if(is_wp_error($post_id)){ // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- This value is used only to construct an exception message, not direct browser output.
   throw new Exception(esc_html($post_id->get_error_message()));
  }$meta_types=SRWAI_Data_Importer::meta_field_types($job['post_type']);foreach((array)($m['meta']??array()) as $key=>$src){if($src==='')continue;$v=$row[$src]??'';$type=$meta_types[$key]??'';if(self::is_number_type($type))$v=self::number_value($v);elseif(self::is_datetime_type($type))$v=self::parse_datetime($v);elseif(is_array($v))$v=wp_json_encode($v);if($v===''&&self::is_number_type($type))update_post_meta($post_id,sanitize_key($key),'');else update_post_meta($post_id,sanitize_key($key),is_scalar($v)?$v:wp_json_encode($v));}foreach((array)($m['taxonomies']??array()) as $tax=>$src){if(!taxonomy_exists($tax)||$src==='')continue;$raw=(string)($row[$src]??'');$parts=preg_split('/[\|,\n]+/',$raw);$ids=array();foreach($parts as $part){$part=trim($part);if($part==='')continue;$term=term_exists($part,$tax);if(!$term)$term=wp_insert_term($part,$tax);if(!is_wp_error($term))$ids[]=(int)$term['term_id'];}wp_set_object_terms($post_id,$ids,$tax,false);}if(!empty($job['settings']['import_images'])&&!empty($job['settings']['download_images'])){$img=self::value($row,$m,'featured_image');if(!$img)$img=$row['featured_image']??$row['image']??$row['image_url']??$row['images']??'';if($img&&!get_post_thumbnail_id($post_id)){$att=SRWAI_Media::maybe_image_value($img,$post_id);if($att)set_post_thumbnail($post_id,$att);}}return $result;}
}
