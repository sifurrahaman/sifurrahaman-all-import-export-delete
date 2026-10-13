<?php
if (!defined('ABSPATH')) exit;

class SRWAI_Delete {
    public static function init(){
        add_action('wp_ajax_srwai_delete_count',array(__CLASS__,'count_ajax'));
        add_action('wp_ajax_srwai_delete_batch',array(__CLASS__,'ajax'));
    }

    public static function page(){
        if(!current_user_can('manage_options'))return;
        $types=SRWAI_Data_Importer::post_types();
        echo '<div class="wrap srwai-wrap row"><div class="srwai-hero col-md-8 col-sm-12"><div class="small-brand">SR All Import, Export & Bulk Deletion Plugin By Sifur Rahaman</div><h1>Delete Posts</h1><p>Delete a specific number of the oldest or newest posts. Imported images are deleted only when they are marked by this plugin and are no longer referenced.</p></div><div class="srwai-hero col-md-3 col-sm-12"><div class="small-brand">By Sifur Rahaman</div><h1>Donate Me</h1><a href="https://sifurrahaman.com/donate/" class="btn btn-primary">Donate Me</a></div><div class="container-fluid p-0"><div class="row g-4"><div class="col-12 col-xl-8"><div class="card srwai-card"><div class="card-body p-4"><div class="row g-4">';
        echo '<div class="col-12 col-lg-6"><label class="form-label fw-semibold" for="srwai-delete-type">Post Type</label><select class="form-select" id="srwai-delete-type">';
        foreach($types as $t) echo '<option value="'.esc_attr($t->name).'">'.esc_html($t->labels->name.' ('.$t->name.')').'</option>';
        echo '</select></div>';
        echo '<div class="col-12"><div id="srwai-delete-count" class="alert alert-info mb-0">Loading post count...</div></div>';
        echo '<div class="col-12 col-md-6"><label class="form-label fw-semibold" for="srwai-delete-quantity">How many posts to delete?</label><input type="number" class="form-control" id="srwai-delete-quantity" value="1" min="1" step="1"></div>';
        echo '<div class="col-12 col-md-6"><label class="form-label fw-semibold" for="srwai-delete-direction">Delete From</label><select class="form-select" id="srwai-delete-direction"><option value="old">Old Post</option><option value="new">New Post</option></select></div>';
        echo '<div class="col-12"><div class="form-check"><input class="form-check-input" type="checkbox" id="srwai-delete-images"><label class="form-check-label" for="srwai-delete-images">Delete associated imported images when safe</label></div></div>';
        echo '<div class="col-12"><button class="btn btn-danger btn-lg" id="srwai-delete-start">Delete Selected Number of Posts</button></div>';
        echo '<div class="col-12"><div id="srwai-delete-result"></div></div>';
        echo '</div></div></div></div><div class="col-12 col-xl-4"><div class="card srwai-card"><div class="card-body p-4"><h3 class="h5">Workflow</h3><ol class="mb-0 ps-3"><li class="mb-2">Select post type</li><li class="mb-2">Upload CSV / XLSX / XML</li><li class="mb-2">Inspect headers + sample only</li><li class="mb-2">Map fields</li><li class="mb-2">Start batch import</li><li>Review results</li></ol><div class="alert alert-success mt-4 mb-0"><h3 class="h5">Donate Me</h3><a href="https://sifurrahaman.com/donate/" target="_blank" class="text-white btn btn-success">Donate Me</a></div></div></div></div></div></div>';
    }

    private static function valid_type($type){
        return $type && post_type_exists($type) && current_user_can('manage_options');
    }

    private static function total($type){
        $counts=wp_count_posts($type);
        if(!is_object($counts))return 0;
        $total=0;
        foreach((array)$counts as $status=>$count){
            if(in_array($status,array('trash','auto-draft'),true))continue;
            $total+=(int)$count;
        }
        return $total;
    }

    public static function count_ajax(){
        if(!current_user_can('manage_options'))wp_send_json_error('Unauthorized.',403);
        check_ajax_referer('srwai_nonce','nonce');
        $type=sanitize_key(wp_unslash($_POST['post_type']??''));
        if(!self::valid_type($type))wp_send_json_error('Invalid post type.');
        wp_send_json_success(array('total'=>self::total($type)));
    }

    public static function ajax(){
        if(!current_user_can('manage_options'))wp_send_json_error('Unauthorized.',403);
        check_ajax_referer('srwai_nonce','nonce');
        $type=sanitize_key(wp_unslash($_POST['post_type']??''));
        if(!self::valid_type($type))wp_send_json_error('Invalid post type.');
        $remaining_to_delete=max(0,absint(wp_unslash($_POST['quantity']??0)));
        $direction=sanitize_key(wp_unslash($_POST['direction']??'old'))==='new'?'new':'old';
        $delete_images=!empty(sanitize_text_field(wp_unslash($_POST['delete_images']??''))); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is verified immediately above.
        if($remaining_to_delete<1)wp_send_json_error('Please enter how many posts you want to delete.');

        $total_before=self::total($type);
        if($total_before<1)wp_send_json_error('No posts were found for this post type.');
        if($remaining_to_delete>$total_before)$remaining_to_delete=$total_before;

        $batch_size=min(25,$remaining_to_delete);
        $order=$direction==='old'?'ASC':'DESC';
        $ids=get_posts(array('post_type'=>$type,'post_status'=>'any','posts_per_page'=>$batch_size,'orderby'=>'date ID','order'=>$order,'fields'=>'ids','no_found_rows'=>true,'suppress_filters'=>false));
        if(!$ids)wp_send_json_error('No matching posts were found.');
        $deleted=0;
        foreach($ids as $id){
            $thumb=get_post_thumbnail_id($id);
            if(wp_delete_post($id,true)){
                $deleted++;
                if($delete_images && $thumb && get_post_meta($thumb,'_srwai_imported',true)) self::delete_attachment_safe($thumb);
            }
        }
        $remaining_total=self::total($type);
        wp_send_json_success(array('deleted'=>$deleted,'remaining_total'=>$remaining_total,'requested'=>$remaining_to_delete));
    }

    private static function delete_attachment_safe($id){
        $used=get_posts(array(
            'post_type'=>'any',
            'post_status'=>'any',
            'posts_per_page'=>1,
            'fields'=>'ids',
            'no_found_rows'=>true,
            // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key, WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- This query checks one thumbnail ID at a time while deleting a small bounded batch.
            'meta_key'=>'_thumbnail_id',
            // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- This query checks one thumbnail ID at a time while deleting a small bounded batch.
            'meta_value'=>(string)absint($id),
            'suppress_filters'=>false,
        ));
        if(empty($used))wp_delete_attachment($id,true);
    }
}
