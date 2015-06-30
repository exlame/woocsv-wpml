<?php
/*
Plugin Name: woocommerce csvimport wpml add-on
#Plugin URI: http://allaerd.org/
Description: WPML support
Version: beta
Author: Allaerd Mensonides
License: GPLv2 or later
Author URI: http://allaerd.org
*/

class woocsvWPML
{

	public function __construct()
	{
		global $woocsv_import;
		
		//set number of rows to process to 1 else WPML screws up
		$woocsv_import->options['blocksize'] = 1;
		
		//is wpml active?
		if ( function_exists('icl_object_id') ) {
			//sync the products
			add_action('woocsv_after_save',array($this,'woocsv_after_save'),9999);

			//make sure you have the ID of the product in the main language
			add_filter('woocsv_get_product_id',array($this,'woocsv_get_product_id'),10,3);
		}
		
		//set up menu
		add_action('admin_menu', array($this, 'adminMenu'));
		
		//set up make duplicates
		//add_action('save_post', array($this,'save_post'), 11, 2); 
		
	}
	
	public function woocsv_get_product_id($product_id, $sku) {
		global $wpdb,$sitepress;
		
		//get the ID for the post in the main language
		$product_id = $wpdb->get_var($wpdb->prepare(
			"
			SELECT post_id 
			FROM {$wpdb->prefix}postmeta a, {$wpdb->prefix}posts b, {$wpdb->prefix}icl_translations c
			WHERE a.post_id= b.id 
			and meta_key='_sku' 
			and meta_value=%s 
			and c.element_id = b.ID 
			and c.language_code = %s
			", 
			$sku, 
			$sitepress->get_default_language() 
		));

		if ($product_id) $product['ID'] = $product_id; else $product_id = false;
		
		return $product_id;
	}
	
	public function woocsv_after_save(){
		global $woocsv_product, $woocommerce_wpml,$sitepress,$iclTranslationManagement;

		//make duplicates if products are new
		if ( $woocsv_product->new )  {
			@$iclTranslationManagement->make_duplicates_all($woocsv_product->body['ID']);
			return;
		}
		
		
		//run sync for translations
		$product_id = $woocsv_product->body['ID'];
		$trid = $sitepress->get_element_trid($product_id,'post_product');
		$translations = $sitepress->get_element_translations($trid);

		foreach ($translations as $translation) {
			//if this is the original continue to the next
			if ( $translation->original ==1 )
				continue;

			//ok, we are in a translation....do your thing
			$tr_product_id = $translation->element_id;
			$lang = $translation->language_code;
			
			//sync post_meta
			$woocommerce_wpml->products->duplicate_product_post_meta($product_id,$tr_product_id);
			 
			//duplicate product attrs
			$orig_product_attrs = $woocommerce_wpml->products->get_product_atributes($product_id);
			add_post_meta($tr_product_id,'_product_attributes',$orig_product_attrs);
			 
			//sync default product attrs
			$woocommerce_wpml->products->sync_default_product_attr($product_id, $tr_product_id, $lang);
			 
			//sync media
			$woocommerce_wpml->products->sync_thumbnail_id($product_id, $tr_product_id,$lang);
			$woocommerce_wpml->products->sync_product_gallery($product_id);
			 
			//sync taxonomies
			$woocommerce_wpml->products->sync_product_taxonomies($product_id,$tr_product_id,$lang);
			 
			//duplicate variations
			$woocommerce_wpml->products->sync_product_variations($product_id,$tr_product_id,$lang);

		}
		
	}

	public function save_post($post_id) {
		global $woocsv_product,$iclTranslationManagement;
		
		//check if we are not in the regular save post flow
		if (empty($woocsv_product))
			return;
		
		//remove the action else it will run in a loop!
		remove_action( 'save_post', array( $this, 'save_post' ),11,2 );
		
		//if the product is new, make duplicates
		if ( true == $woocsv_product->new )
			$iclTranslationManagement->make_duplicates_all($post_id);
		

		//add the hook again for the next product
		add_action('save_post', array($this,'save_post'), 11, 2); 

	}
	
	

	public function adminMenu()
	{
		add_submenu_page( 'woocsv_import', 'WPML', 'WPML', 'manage_options', 'woocsvWPML', array($this, 'addToAdmin'));
	}

	function addToAdmin()
	{
?>
		<div class="wrap">
		<h2>WPML support</h2>
		<p>Beta version. Will copy and sync your products to all languages</p>
		</div>
		<?php
	}
}