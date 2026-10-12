<?php
if (!defined('ABSPATH')) exit;
class SRWAI_Media {
 public static function sideload($url,$post_id=0){$url=esc_url_raw(trim($url));if(!$url||!wp_http_validate_url($url))return 0;require_once ABSPATH.'wp-admin/includes/file.php';require_once ABSPATH.'wp-admin/includes/media.php';require_once ABSPATH.'wp-admin/includes/image.php';$tmp=download_url($url,30);if(is_wp_error($tmp))return 0;$name=wp_basename(wp_parse_url($url,PHP_URL_PATH));if(!$name)$name='imported-image.jpg';$file=array('name'=>sanitize_file_name($name),'tmp_name'=>$tmp);$id=media_handle_sideload($file,$post_id);if(is_wp_error($id)){wp_delete_file($tmp);return 0;}update_post_meta($id,'_srwai_imported','1');if($post_id)update_post_meta($id,'_srwai_source_post',(string)$post_id);return (int)$id;}
 public static function maybe_image_value($value,$post_id){$first=preg_split('/[\n,|]+/',trim((string)$value))[0]??'';return self::sideload($first,$post_id);}
}
