<?php
if (!defined('ABSPATH')) exit;
class SRWAI_Data_Importer {
 public static function init(){SRWAI_Admin::init();SRWAI_Importer::init();SRWAI_Exporter::init();SRWAI_Delete::init();}
 public static function activate(){if(!get_option('srwai_settings'))add_option('srwai_settings',array('batch_size'=>20),'','no');}
 public static function post_types(){return get_post_types(array('show_ui'=>true),'objects');}
 public static function taxonomies($post_type){return get_object_taxonomies($post_type,'objects');}
 public static function meta_keys($post_type){
  global $wpdb;
  $keys=array();
  // Existing values, including underscore-prefixed keys created by plugins.
  // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Meta-key discovery requires a direct aggregate query; caching would risk stale field mappings.
  $db_keys=$wpdb->get_col($wpdb->prepare(
   "SELECT DISTINCT pm.meta_key FROM {$wpdb->postmeta} pm INNER JOIN {$wpdb->posts} p ON p.ID=pm.post_id WHERE p.post_type=%s ORDER BY pm.meta_key",
   $post_type
  ));
  if(is_array($db_keys))$keys=array_merge($keys,$db_keys);
  $reg=get_registered_meta_keys('post',$post_type);
  if(is_array($reg))$keys=array_merge($keys,array_keys($reg));

  // JetEngine registered meta fields. JetEngine exposes these through its public meta-box API.
  if(function_exists('jet_engine')){
    try{
      $engine=jet_engine();
      if(is_object($engine)&&isset($engine->meta_boxes)&&is_object($engine->meta_boxes)&&method_exists($engine->meta_boxes,'get_fields_for_context')){
        $fields=$engine->meta_boxes->get_fields_for_context('post_type',$post_type);
        if(is_array($fields))foreach($fields as $field){
          if(is_array($field)&&!empty($field['name']))$keys[]=(string)$field['name'];
        }
      }
    }catch(Throwable $e){}
  }

  // ACF field groups/fields when ACF is active.
  if(function_exists('acf_get_field_groups')&&function_exists('acf_get_fields')){
    try{
      $groups=acf_get_field_groups(array('post_type'=>$post_type));
      if(is_array($groups))foreach($groups as $group){
        $fields=acf_get_fields($group);
        if(is_array($fields))foreach($fields as $field){
          if(is_array($field)&&!empty($field['name']))$keys[]=(string)$field['name'];
        }
      }
    }catch(Throwable $e){}
  }
  $keys=array_values(array_unique(array_filter(array_map('strval',$keys))));
  sort($keys,SORT_NATURAL|SORT_FLAG_CASE);
  return $keys;
 }
 public static function meta_field_types($post_type){
  $types=array();
  // WordPress registered meta schemas can expose a scalar type.
  $reg=get_registered_meta_keys('post',$post_type);
  if(is_array($reg))foreach($reg as $key=>$args){
   if(!empty($args['type']))$types[(string)$key]=strtolower((string)$args['type']);
  }

  // JetEngine field definitions.
  if(function_exists('jet_engine')){
   try{
    $engine=jet_engine();
    if(is_object($engine)&&isset($engine->meta_boxes)&&is_object($engine->meta_boxes)&&method_exists($engine->meta_boxes,'get_fields_for_context')){
     $fields=$engine->meta_boxes->get_fields_for_context('post_type',$post_type);
     if(is_array($fields))foreach($fields as $field){
      if(is_array($field)&&!empty($field['name'])){
       $name=(string)$field['name'];
       $type='';
       if(isset($field['type']))$type=(string)$field['type'];
       elseif(isset($field['field_type']))$type=(string)$field['field_type'];
       if($type!=='')$types[$name]=strtolower($type);
      }
     }
    }
   }catch(Throwable $e){}
  }

  // ACF field definitions.
  if(function_exists('acf_get_field_groups')&&function_exists('acf_get_fields')){
   try{
    $groups=acf_get_field_groups(array('post_type'=>$post_type));
    if(is_array($groups))foreach($groups as $group){
     $fields=acf_get_fields($group);
     if(is_array($fields))foreach($fields as $field){
      if(is_array($field)&&!empty($field['name'])&&!empty($field['type']))$types[(string)$field['name']]=strtolower((string)$field['type']);
     }
    }
   }catch(Throwable $e){}
  }
  return $types;
 }
 public static function normalize_title($title){$title=wp_strip_all_tags((string)$title);$title=html_entity_decode($title,ENT_QUOTES,get_bloginfo('charset')?:'UTF-8');$title=preg_replace('/\s+/u',' ',trim($title));return function_exists('mb_strtolower')?mb_strtolower($title,'UTF-8'):strtolower($title);}
 public static function find_by_title($type,$title){
  $norm=self::normalize_title($title);
  if($norm==='')return 0;
  $needle=wp_strip_all_tags($title);
  $ids=get_posts(array(
   'post_type'=>$type,
   'post_status'=>'any',
   'posts_per_page'=>100,
   'fields'=>'ids',
   'no_found_rows'=>true,
   'suppress_filters'=>false,
   'search_columns'=>array('post_title'),
   's'=>$needle,
  ));
  foreach($ids as $id){
   $status=get_post_status($id);
   if(in_array($status,array('trash','auto-draft'),true))continue;
   if(self::normalize_title(get_the_title($id))===$norm)return (int)$id;
  }
  return 0;
 }
 public static function uploads_dir(){ $u=wp_upload_dir(); $dir=trailingslashit($u['basedir']).'sifurrahman-datasync-importer'; if(!file_exists($dir))wp_mkdir_p($dir); return $dir; }
}
