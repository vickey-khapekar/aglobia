<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}		
class WC_Advanced_Shipment_Tracking_Actions {

	/**
	 * Instance of this class.
	 *
	 * @var object Class Instance
	 */
	private static $instance;
	
	public function __construct() {
		global $wpdb;
		$this->table = $wpdb->prefix."woo_shippment_provider";
		if( is_multisite() ){
			if ( ! function_exists( 'is_plugin_active_for_network' ) ) {
				require_once( ABSPATH . '/wp-admin/includes/plugin.php' );
			}
			if ( is_plugin_active_for_network( 'woo-advanced-shipment-tracking/woocommerce-advanced-shipment-tracking.php' ) ) {
				$main_blog_prefix = $wpdb->get_blog_prefix(BLOG_ID_CURRENT_SITE);			
				$this->table = $main_blog_prefix."woo_shippment_provider";	
			} else{
				$this->table = $wpdb->prefix."woo_shippment_provider";
			}	
			
		} else{
			$this->table = $wpdb->prefix."woo_shippment_provider";	
		}
	}

	/**
	 * Get the class instance
	 *
	 * @return WC_Advanced_Shipment_Tracking_Actions
	*/
	public static function get_instance() {

		if ( null === self::$instance ) {
			self::$instance = new self;
		}

		return self::$instance;
	}
	
	/**
	 * Get shipping providers from database
	 */
	function get_providers(){
		
		if ( empty( $this->providers ) ) {
			$this->providers = array();

			global $wpdb;
			$wpdb->hide_errors();
			$results = $wpdb->get_results( "SELECT * FROM {$this->table}" );
			

			if ( ! empty( $results ) ) {
				
				foreach ( $results as $row ) {										
					$shippment_providers[ $row->ts_slug ] = array(
						'provider_name'=> $row->provider_name,
						'provider_url' => $row->provider_url,
						'trackship_supported' => $row->trackship_supported,						
					);
				}

				$this->providers = $shippment_providers;
			}
		}
		return $this->providers;
		
	}
	
	/**
	 * Get shipping providers from database for WooCommerce App
	 */
	function get_providers_for_app(){
		
		if ( empty( $this->providers_for_app ) ) {
			$this->providers_for_app = array();

			global $wpdb;
			$WC_Countries = new WC_Countries();
			$wpdb->hide_errors();
			
			$shippment_countries = $wpdb->get_results( "SELECT shipping_country FROM {$this->table} WHERE display_in_order = 1 GROUP BY shipping_country" );
			
			$results = $wpdb->get_results( "SELECT * FROM {$this->table} GROUP BY shipping_country" );
			
			
			foreach($shippment_countries as $s_c){
				
				if($s_c->shipping_country != 'Global'){
					$country_name = esc_attr( $WC_Countries->countries[$s_c->shipping_country] );
				} else{
					$country_name = 'Global';
				}
				$country = $s_c->shipping_country;
				$shippment_providers_by_country = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$this->table} WHERE shipping_country = %s AND display_in_order = 1", $country ) );
								
				$providers_array = array();
				$new_provider = array();
				foreach ( $shippment_providers_by_country as $providers ) {	
					$new_provider = array(
						$providers->provider_name => $providers->provider_url,	
					);	
					$providers_array = array_merge($providers_array,$new_provider);
					
				}
				$shippment_providers[ $country_name ] = $providers_array;				
					
				$this->providers_for_app = $shippment_providers;				
			}						
		}
		return $this->providers_for_app;
		
	}

	/**
	 * Load admin styles.
	 */
	public function admin_styles() {
		$plugin_url  = wc_shipment_tracking()->plugin_url;
		wp_enqueue_style( 'shipment_tracking_styles', $plugin_url . '/assets/css/admin.css' );
		
	}

	/**
	 * Define shipment tracking column in admin orders list.
	 *
	 * @since 1.6.1
	 *
	 * @param array $columns Existing columns
	 *
	 * @return array Altered columns
	 */
	public function shop_order_columns( $columns ) {
		$columns['woocommerce-advanced-shipment-tracking'] = __( 'Shipment Tracking', 'woo-advanced-shipment-tracking' );
		return $columns;
	}

	/**
	 * Render shipment tracking in custom column.
	 *
	 * @since 1.6.1
	 *
	 * @param string $column Current column
	 */
	public function render_shop_order_columns( $column ) {
		global $post;

		if ( 'woocommerce-advanced-shipment-tracking' === $column ) {
			echo $this->get_shipment_tracking_column( $post->ID );
		}
	}

	/**
	 * Get content for shipment tracking column.
	 *
	 * @since 1.6.1
	 *
	 * @param int $order_id Order ID
	 *
	 * @return string Column content to render
	 */
	public function get_shipment_tracking_column( $order_id ) {
		ob_start();

		$tracking_items = $this->get_tracking_items( $order_id );

		if ( count( $tracking_items ) > 0 ) {
			echo '<ul class="wcast-tracking-number-list">';

			foreach ( $tracking_items as $tracking_item ) {
				$formatted = $this->get_formatted_tracking_item( $order_id, $tracking_item );
				$url = str_replace('%number%',$tracking_item['tracking_number'],$formatted['formatted_tracking_link']);
				if($url){
					printf(
						'<li id="tracking-item-%s" class="tracking-item-%s"><div><b>%s</b></div><a href="%s" target="_blank" class=ft11>%s</a><a class="inline_tracking_delete" rel="%s" data-order="%s"><span class="dashicons dashicons-trash"></span></a></li>',
						esc_attr( $tracking_item['tracking_id'] ),
						esc_attr( $tracking_item['tracking_id'] ),
						$formatted['formatted_tracking_provider'],
						esc_url( $url ),
						esc_html( $tracking_item['tracking_number'] ),
						esc_attr( $tracking_item['tracking_id'] ),
						esc_attr( $order_id )
					);
				} else{
					printf(
						'<li id="tracking-item-%s" class="tracking-item-%s"><div><b>%s</b></div>%s<a class="inline_tracking_delete" rel="%s" data-order="%s"><span class="dashicons dashicons-trash"></span></a></li>',
						esc_attr( $tracking_item['tracking_id'] ),
						esc_attr( $tracking_item['tracking_id'] ),
						$formatted['formatted_tracking_provider'],						
						esc_html( $tracking_item['tracking_number'] ),
						esc_attr( $tracking_item['tracking_id'] ),
						esc_attr( $order_id )
					);
				}
			}			
			echo '</ul>';
		} else {
			echo '–';			
		}		
		return apply_filters( 'woocommerce_shipment_tracking_get_shipment_tracking_column', ob_get_clean(), $order_id, $tracking_items );
	}	

	/**
	 * Add the meta box for shipment info on the order page
	 */
	public function add_meta_box() {			
		add_meta_box( 'woocommerce-advanced-shipment-tracking', __( 'Shipment Tracking', 'woo-advanced-shipment-tracking' ), array( $this, 'meta_box' ), 'shop_order', 'side', 'high' );
	}

	/**
	 * Returns a HTML node for a tracking item for the admin meta box
	 */
	public function display_html_tracking_item_for_meta_box( $order_id, $item ) {
			$formatted = $this->get_formatted_tracking_item( $order_id, $item );			
			?>
			<div class="tracking-item" id="tracking-item-<?php echo esc_attr( $item['tracking_id'] ); ?>">
				<div class="tracking-content">
					<div class="tracking-content-div">
						<strong><?php echo esc_html( $formatted['formatted_tracking_provider'] ); ?></strong>						
						<?php if ( strlen( $formatted['formatted_tracking_link'] ) > 0 ) { ?>
							- <?php 
							$url = str_replace('%number%',$item['tracking_number'],$formatted['formatted_tracking_link']);
							echo sprintf( '<a href="%s" target="_blank" title="' . esc_attr( __( 'Track Shipment', 'woo-advanced-shipment-tracking' ) ) . '">' . __( $item['tracking_number'] ) . '</a>', esc_url( $url ) ); ?>
						<?php } else{ ?>
							<span> - <?php echo $item['tracking_number']; ?></span>
						<?php } ?>
					</div>					
					<?php do_action('ast_after_tracking_number',$order_id,$item['tracking_id']); ?>								
					<?php                     
					$this->display_shipment_tracking_info( $order_id, $item );?>
				</div>
				<p class="meta">
					<?php /* translators: 1: shipping date */ ?>
					<?php echo esc_html( sprintf( __( 'Shipped on %s', 'woo-advanced-shipment-tracking' ), date_i18n( get_option( 'date_format' ), $item['date_shipped'] ) ) ); ?>
					<a href="#" class="delete-tracking" rel="<?php echo esc_attr( $item['tracking_id'] ); ?>"><?php _e( 'Delete', 'woocommerce' ); ?></a>                    
				</p>
			</div>
			<?php
	}
	
	/**
	 * Shipment tracking info html in orders details page
	 */
	public function display_shipment_tracking_info( $order_id, $item ){
		$shipment_status = get_post_meta( $order_id, "shipment_status", true);		
		$tracking_id = $item['tracking_id'];
		$tracking_items = $this->get_tracking_items( $order_id );
		$wp_date_format = get_option( 'date_format' );
		if($wp_date_format == 'd/m/Y'){
			$date_format = 'd/m'; 
		} else{
			$date_format = 'm/d';
		}
		if ( count( $tracking_items ) > 0 ) {
			foreach ( $tracking_items as $key => $tracking_item ) {
				if( $tracking_id == $tracking_item['tracking_id'] ){
					if( isset( $shipment_status[$key] )){
						$has_est_delivery = false;
						$data = $shipment_status[$key];						
						
						if(isset($data['pending_status'])){
							$status = $data['pending_status'];
						} else{
							$status = $data['status'];	
						}
						
						$status_date = $data['status_date'];
						if(!empty($data["est_delivery_date"])){
							$est_delivery_date = $data["est_delivery_date"];
						}
						if( $status != 'delivered' && $status != 'return_to_sender' && !empty($est_delivery_date) ){
							$has_est_delivery = true;
						}
						?>	
						<div class="ast-shipment-status-div">	
                        <span class="ast-shipment-status shipment-<?php echo sanitize_title($status)?>"><?php echo apply_filters( "trackship_status_icon_filter", "", $status )?> <strong><?php echo apply_filters("trackship_status_filter",$status)?></strong></span>
						<span class="">on <?php echo date( $date_format, strtotime($status_date))?></span>
                        <br>
                        <?php if( $has_est_delivery ){?>
                            <span class="wcast-shipment-est-delivery ft11">Est. Delivery(<?php echo date( $date_format, strtotime($est_delivery_date))?>)</span>
                        <?php } ?>
						</div>	
                        <?php
					}
				}
			}
		}
	}

	/**
	 * Show the meta box for shipment info on the order page
	 */
	public function meta_box() {
		global $post;
		global $wpdb;				
		
		$WC_Countries = new WC_Countries();
		$countries = $WC_Countries->get_countries();
		
		$woo_shippment_table_name = $wpdb->prefix . 'woo_shippment_provider';
		
		if( is_multisite() ){									
			if ( ! function_exists( 'is_plugin_active_for_network' ) ) {
				require_once( ABSPATH . '/wp-admin/includes/plugin.php' );
			}
			if ( is_plugin_active_for_network( 'woo-advanced-shipment-tracking/woocommerce-advanced-shipment-tracking.php' ) ) {
				$main_blog_prefix = $wpdb->get_blog_prefix(BLOG_ID_CURRENT_SITE);			
				$woo_shippment_table_name = $main_blog_prefix."woo_shippment_provider";	
			} else{
				$woo_shippment_table_name = $wpdb->prefix."woo_shippment_provider";
			}
		} else{
			$woo_shippment_table_name = $wpdb->prefix."woo_shippment_provider";	
		}
		
		$tracking_items = $this->get_tracking_items( $post->ID );
		
		$shippment_countries = $wpdb->get_results( "SELECT shipping_country FROM $woo_shippment_table_name WHERE display_in_order = 1 GROUP BY shipping_country" );
		
		$shippment_providers = $wpdb->get_results( "SELECT * FROM $woo_shippment_table_name" );
		
		$default_provider = get_option("wc_ast_default_provider" );	
		$wc_ast_default_mark_shipped = 	get_option("wc_ast_default_mark_shipped" );
		$wc_ast_status_partial_shipped = get_option('wc_ast_status_partial_shipped');
		$value = 1;
		$cbvalue = '';
		if($wc_ast_default_mark_shipped == 1){
			if($wc_ast_status_partial_shipped){
				$cbvalue = 'change_order_to_shipped';
			} else{
				$cbvalue = 1;	
			}			
		}		
		
		$wc_ast_status_shipped = get_option('wc_ast_status_shipped');
		if($wc_ast_status_shipped == 1){
			$change_order_status_label = __( 'Mark as Shipped?', 'woo-advanced-shipment-tracking' );
			$shipped_label = 'Shipped';
		} else{
			$change_order_status_label = __( 'Mark as Completed?', 'woo-advanced-shipment-tracking' );
			$shipped_label = 'Completed';
		}				
						
		echo '<div id="tracking-items">';
		if ( count( $tracking_items ) > 0 ) {
			foreach ( $tracking_items as $tracking_item ) {				
				$this->display_html_tracking_item_for_meta_box( $post->ID, $tracking_item );
			}
		}
		echo '</div>';
		
		echo '<button class="button button-show-tracking-form" type="button">' . __( 'Add Tracking Info', 'woo-advanced-shipment-tracking' ) . '</button>';
		
		echo '<div id="advanced-shipment-tracking-form">';
		
		echo '<p class="form-field tracking_provider_field"><label for="tracking_provider">' . __( 'Shipping Provider:', 'woo-advanced-shipment-tracking' ) . '</label><br/><select id="tracking_provider" name="tracking_provider" class="chosen_select" style="width:100%;">';	
			echo '<option value="">'.__( 'Select Provider', 'woo-advanced-shipment-tracking' ).'</option>';
		foreach($shippment_countries as $s_c){
			if($s_c->shipping_country != 'Global'){
				$country_name = esc_attr( $WC_Countries->countries[$s_c->shipping_country] );
			} else{
				$country_name = 'Global';
			}
			echo '<optgroup label="' . $country_name . '">';
				$country = $s_c->shipping_country;				
				$shippment_providers_by_country = $wpdb->get_results( "SELECT * FROM $woo_shippment_table_name WHERE shipping_country = '$country' AND display_in_order = 1" );
				foreach ( $shippment_providers_by_country as $providers ) {
					//echo '<pre>';print_r($providers);echo '</pre>';
					$selected = ( $default_provider == esc_attr( $providers->ts_slug )  ) ? 'selected' : '';
					echo '<option value="' . esc_attr( $providers->ts_slug ) . '" '.$selected. '>' . esc_html( $providers->provider_name ) . '</option>';
				}
			echo '</optgroup>';	
		}

		echo '</select> ';
		
		woocommerce_wp_hidden_input( array(
			'id'    => 'wc_shipment_tracking_get_nonce',
			'value' => wp_create_nonce( 'get-tracking-item' ),
		) );

		woocommerce_wp_hidden_input( array(
			'id'    => 'wc_shipment_tracking_delete_nonce',
			'value' => wp_create_nonce( 'delete-tracking-item' ),
		) );

		woocommerce_wp_hidden_input( array(
			'id'    => 'wc_shipment_tracking_create_nonce',
			'value' => wp_create_nonce( 'create-tracking-item' ),
		) );		
	?>
		<p class="form-field tracking_number_field ">
			<label for="tracking_number"><?php _e( 'Tracking number:', 'woo-advanced-shipment-tracking'); ?></label>
			<input type="text" class="short" style="" name="tracking_number" id="tracking_number" value="" autocomplete="off"> 
		</p>
	<?php	
		
		woocommerce_wp_text_input( array(
			'id'          => 'tracking_product_code',
			'label'       => __( 'Product Code:', 'woo-advanced-shipment-tracking' ),
			'placeholder' => '',
			'description' => '',
			'value'       => '',
		) );

		woocommerce_wp_text_input( array(
			'id'          => 'date_shipped',
			'label'       => __( 'Date shipped:', 'woo-advanced-shipment-tracking' ),
			'placeholder' => date_i18n( __( 'Y-m-d', 'woo-advanced-shipment-tracking' ), time() ),
			'description' => '',
			'class'       => 'date-picker-field',
			'value'       => date_i18n( __( 'Y-m-d', 'woo-advanced-shipment-tracking' ), current_time( 'timestamp' ) ),
		) );	
		
		do_action("ast_after_tracking_field", $post->ID);	
		do_action("ast_tracking_form_between_form", $post->ID);
		
		if($wc_ast_status_partial_shipped){
			?>
			<fieldset class="form-field change_order_to_shipped_field" style="margin-bottom: 10px;">
				<span><?php _e( 'Mark order as:', 'woo-advanced-shipment-tracking'); ?></span>
				<ul class="wc-radios">
					<li><label><input name="change_order_to_shipped" value="change_order_to_shipped" type="checkbox" class="select short mark_shipped_checkbox" <?php if($wc_ast_default_mark_shipped == 1){ echo 'checked'; }?>><?php _e( $shipped_label, 'woo-advanced-shipment-tracking'); ?></label></li>
					<li><label><input name="change_order_to_shipped" value="change_order_to_partial_shipped" type="checkbox" class="select short mark_shipped_checkbox"><?php _e( 'Partial Shipped', 'woo-advanced-shipment-tracking'); ?></label></li>
				</ul>
			</fieldset>		
			<?php						
		} else{
			woocommerce_wp_checkbox( array(
				'id'          => 'change_order_to_shipped',
				'label'       => __( $change_order_status_label, 'woo-advanced-shipment-tracking' ),		
				'description' => '',
				'cbvalue'     => $cbvalue,	
				'value'       => $value,
			) );
		}	
		echo '<button class="button button-primary btn_green button-save-form">' . __( 'Save Tracking', 'woo-advanced-shipment-tracking' ) . '</button>';
		echo '<p class="preview_tracking_link">' . __( 'Preview:', 'woo-advanced-shipment-tracking' ) . ' <a href="" target="_blank">' . __( 'Track Shipment', 'woo-advanced-shipment-tracking' ) . '</a></p>';
		
		echo '</div>';
		$provider_array = array();

		foreach ( $shippment_providers as $provider ) {
			$provider_array[ sanitize_title( $provider->provider_name ) ] = urlencode( $provider->provider_url );
		}
		
		$js = "
			jQuery( 'p.custom_tracking_link_field, p.custom_tracking_provider_field ').hide();

			jQuery( 'input#tracking_number, #tracking_provider' ).change( function() {

				var tracking  = jQuery( 'input#tracking_number' ).val();
				var provider  = jQuery( '#tracking_provider' ).val();
				var providers = jQuery.parseJSON( '" . json_encode( $provider_array ) . "' );

				var postcode = jQuery( '#_shipping_postcode' ).val();

				if ( ! postcode.length ) {
					postcode = jQuery( '#_billing_postcode' ).val();
				}

				postcode = encodeURIComponent( postcode );

				var link = '';

				if ( providers[ provider ] ) {
					link = providers[provider];
					link = link.replace( '%25number%25', tracking );
					link = link.replace( '%252%24s', postcode );
					link = decodeURIComponent( link );

					jQuery( 'p.custom_tracking_link_field, p.custom_tracking_provider_field' ).hide();
				} else {
					jQuery( 'p.custom_tracking_link_field, p.custom_tracking_provider_field' ).show();

					link = jQuery( 'input#custom_tracking_link' ).val();
				}

				if ( link ) {
					jQuery( 'p.preview_tracking_link a' ).attr( 'href', link );
					jQuery( 'p.preview_tracking_link' ).show();
				} else {
					jQuery( 'p.preview_tracking_link' ).hide();
				}

			} ).change();";

		if ( function_exists( 'wc_enqueue_js' ) ) {
			wc_enqueue_js( $js );
		} else {
			WC()->add_inline_js( $js );
		}
		
		wp_enqueue_style( 'shipment_tracking_styles',  wc_advanced_shipment_tracking()->plugin_dir_url() . 'assets/css/admin.css', array(), wc_advanced_shipment_tracking()->version );				
		wp_enqueue_script( 'woocommerce-advanced-shipment-tracking-js', wc_advanced_shipment_tracking()->plugin_dir_url() . 'assets/js/admin.js' );
		?>
		<script>
		jQuery(document).on("change", "#tracking_provider", function(){	
			var selected_provider = jQuery(this).val();			
			if(selected_provider == 'nz-couriers' || selected_provider == 'post-haste' || selected_provider == 'castle-parcels' || selected_provider == 'dx-mail' || selected_provider == 'now-couriers'){
				jQuery('.tracking_product_code_field').show();
			} else{
				jQuery('.tracking_product_code_field').hide();
			}			
		});
		</script>
		<?php
		do_action("ast_tracking_form_end_meta_box");
	}	

	/**
	 * Order Tracking Get All Order Items AJAX
	 *
	 * Function for getting all tracking items associated with the order
	 */
	public function get_meta_box_items_ajax() {
		check_ajax_referer( 'get-tracking-item', 'security', true );

		$order_id = wc_clean( $_POST['order_id'] );
		$tracking_items = $this->get_tracking_items( $order_id );

		foreach ( $tracking_items as $tracking_item ) {
			$this->display_html_tracking_item_for_meta_box( $order_id, $tracking_item );
		}

		die();
	}

	/**
	 * Order Tracking Save AJAX
	 *
	 * Function for saving tracking items via AJAX
	 */
	public function save_meta_box_ajax() {
		check_ajax_referer( 'create-tracking-item', 'security', true );
		$tracking_number = str_replace(' ', '', $_POST['tracking_number']);
		$ast_admin = WC_Advanced_Shipment_Tracking_Admin::get_instance();
		if ( isset( $_POST['tracking_number'] ) &&  $_POST['tracking_provider'] != '' && isset( $_POST['tracking_provider'] ) && strlen( $_POST['tracking_number'] ) > 0 ) {
	
			$order_id = wc_clean( $_POST['order_id'] );
			$order = new WC_Order($order_id);
			$tracking_product_code = isset($_POST['tracking_product_code']) ? $_POST['tracking_product_code'] : "";
			
			$args = array(
				'tracking_provider'        => wc_clean($_POST['tracking_provider']),
				'tracking_number'          => wc_clean( $_POST['tracking_number'] ),
				'tracking_product_code'    => wc_clean($tracking_product_code),	
				'date_shipped'             => wc_clean( $_POST['date_shipped'] ),
			);
			
			$args = apply_filters( 'tracking_info_args', $args, $order_id );
			
			$tracking_item = $this->add_tracking_item( $order_id, $args );
			
			if($_POST['change_order_to_shipped'] == 'change_order_to_shipped'){     						
				if('completed' == $order->get_status()){
					WC()->mailer()->emails['WC_Email_Customer_Completed_Order']->trigger( $order_id, $order );	
					$ast_admin->trigger_woocommerce_order_status_completed( $order_id );	
				} else{
					$order->update_status('completed');
				}																
			} elseif($_POST['change_order_to_shipped'] == 'change_order_to_partial_shipped'){				
				$previous_order_status = $order->get_status();
				
				if('partial-shipped' == $previous_order_status){								
					WC()->mailer()->emails['WC_Email_Customer_Partial_Shipped_Order']->trigger( $order_id, $order );	
				}				
				$order->update_status('partial-shipped');					
				$ast_admin->trigger_woocommerce_order_status_completed( $order_id );
			}			
			
			$this->display_html_tracking_item_for_meta_box( $order_id, $tracking_item );
		}

		die();
	}
	
	/**
	 * Order Tracking Save AJAX
	 *
	 * Function for saving tracking items via AJAX
	 */
	public function save_inline_tracking_number() {
		$ast_admin = WC_Advanced_Shipment_Tracking_Admin::get_instance();
		if ( isset( $_POST['tracking_number'] ) &&  $_POST['tracking_provider'] != '' && isset( $_POST['tracking_provider'] ) && strlen( $_POST['tracking_number'] ) > 0 ) {	
			$order_id = wc_clean( $_POST['order_id'] );
			$tracking_product_code = isset($_POST['tracking_product_code']) ? $_POST['tracking_product_code'] : "";
			$args = array(
				'tracking_provider'        => wc_clean($_POST['tracking_provider']),
				'tracking_number'          => wc_clean( $_POST['tracking_number'] ),
				'tracking_product_code'    => wc_clean($tracking_product_code),	
				'date_shipped'             => wc_clean( $_POST['date_shipped'] ),
			);
			
			$args = apply_filters( 'tracking_info_args', $args, $order_id );
			
			$tracking_item = $this->add_tracking_item( $order_id, $args );	
			$order = new WC_Order($order_id);
			
			if($_POST['change_order_to_shipped'] == 'change_order_to_shipped' || $_POST['change_order_to_shipped'] == 'yes'){	
				if('completed' == $order->get_status()){
					WC()->mailer()->emails['WC_Email_Customer_Completed_Order']->trigger( $order_id, $order );	
					$ast_admin->trigger_woocommerce_order_status_completed( $order_id );	
				} else{
					$order->update_status('completed');
				}
			} elseif($_POST['change_order_to_shipped'] == 'change_order_to_partial_shipped'){				
				$previous_order_status = $order->get_status();				
				if('partial-shipped' == $previous_order_status){								
					WC()->mailer()->emails['WC_Email_Customer_Partial_Shipped_Order']->trigger( $order_id, $order );	
				}				
				$order->update_status('partial-shipped');					
				$ast_admin->trigger_woocommerce_order_status_completed( $order_id );
			}							
		}
	}

	/**
	 * Order Tracking Delete
	 *
	 * Function to delete a tracking item
	 */
	public function meta_box_delete_tracking() {
		//check_ajax_referer( 'delete-tracking-item', 'security', true );

		$order_id    = wc_clean( $_POST['order_id'] );
		$tracking_id = wc_clean( $_POST['tracking_id'] );
		$tracking_items = $this->get_tracking_items( $order_id, true );
		
		$api_enabled = get_option( "wc_ast_api_enabled", 0);
		if( $api_enabled ){
			
			foreach($tracking_items as $tracking_item){
				
				if($tracking_item['tracking_id'] == $_POST['tracking_id']){
					
					$tracking_number = $tracking_item['tracking_number'];
					$tracking_provider = $tracking_item['tracking_provider'];					
					$api = new WC_Advanced_Shipment_Tracking_Api_Call;
					$array = $api->delete_tracking_number_from_trackship( $order_id, $tracking_number, $tracking_provider );
				}
				
			}
							
		}
		
		foreach($tracking_items as $tracking_item){
			if($tracking_item['tracking_id'] == $_POST['tracking_id']){
				$formated_tracking_item = $this->get_formatted_tracking_item( $order_id, $tracking_item );
				
				$tracking_number = $tracking_item['tracking_number'];
				$tracking_provider = $formated_tracking_item['formatted_tracking_provider'];
				$order = wc_get_order(  $order_id );
				// The text for the note
				$note = sprintf(__("Tracking info was deleted for tracking provider %s with tracking number %s", 'woo-advanced-shipment-tracking'), $tracking_provider, $tracking_number );
				
				// Add the note
				$order->add_order_note( $note );
			}
		}
		$this->delete_tracking_item( $order_id, $tracking_id );				
	}

	/**
	 * Display Shipment info in the frontend (order view/tracking page).
	 */
	public function show_tracking_info_order( $order_id ) {	
		wc_get_template( 'myaccount/tracking-info.php', array( 'tracking_items' => $this->get_tracking_items( $order_id, true ), 'order_id' => $order_id ), 'woocommerce-advanced-shipment-tracking/', wc_advanced_shipment_tracking()->get_plugin_path() . '/templates/' );
	}
	
	/**
	 * Display Track button in My account orders list
	 */
	public function show_track_actions_in_orders( $actions, $order ) {
		$order_id = $order->get_id();
		$tracking_items = $this->get_tracking_items( $order_id, true );		
		foreach($tracking_items as $item){
			$track_url = $item['formatted_tracking_link'];
			$actions['track_button'] = array(
				'url'  => $track_url,
				'name' => __( 'Track', 'woo-advanced-shipment-tracking' ),
			);
		}
		return $actions;
	}

	/**
	 * Display shipment info in customer emails.
	 *
	 * @version 1.6.8
	 *
	 * @param WC_Order $order         Order object.
	 * @param bool     $sent_to_admin Whether the email is being sent to admin or not.
	 * @param bool     $plain_text    Whether email is in plain text or not.
	 * @param WC_Email $email         Email object.
	 */
	public function email_display( $order, $sent_to_admin, $plain_text = null, $email = null ) {

		
		$wc_ast_unclude_tracking_info = get_option('wc_ast_unclude_tracking_info');	
		
		$order_id = is_callable( array( $order, 'get_id' ) ) ? $order->get_id() : $order->id;		
		$order = wc_get_order( $order_id );
		if($order){			
			$order_status = $order->get_status();			
		
			if ( is_a( $email, 'WC_Email_Customer_Invoice' ) && isset($wc_ast_unclude_tracking_info['show_in_customer_invoice']) && $wc_ast_unclude_tracking_info['show_in_customer_invoice'] == 0){			
				return;	
			}
			
			if ( is_a( $email, 'WC_Email_Customer_Note' ) && isset($wc_ast_unclude_tracking_info['show_in_customer_note']) && $wc_ast_unclude_tracking_info['show_in_customer_note'] == 0){			
				return;	
			}			
						
			if(isset($wc_ast_unclude_tracking_info[$order_status]) && $wc_ast_unclude_tracking_info[$order_status] == 0 && !is_a( $email, 'WC_Email_Customer_Invoice' ) && !is_a( $email, 'WC_Email_Customer_Note' )){
				return;
			}
	
			$tracking_items = $this->get_tracking_items( $order_id, true );					
			
			$local_template	= get_stylesheet_directory().'/woocommerce/emails/tracking-info.php';
			
			if ( true === $plain_text ) {
				if ( file_exists( $local_template ) && is_writable( $local_template )){
					wc_get_template( 'emails/plain/tracking-info.php', array( 'tracking_items' => $this->get_tracking_items( $order_id, true ), 'order_id'=> $order_id ), 'woocommerce-advanced-shipment-tracking/', get_stylesheet_directory() . '/woocommerce/' );
				} else{
					wc_get_template( 'emails/plain/tracking-info.php', array( 'tracking_items' => $this->get_tracking_items( $order_id, true ), 'order_id'=> $order_id ), 'woocommerce-advanced-shipment-tracking/', wc_advanced_shipment_tracking()->get_plugin_path() . '/templates/' );
				}					
			} else {
				if ( file_exists( $local_template ) && is_writable( $local_template )){	
					wc_get_template( 'emails/tracking-info.php', array( 'tracking_items' => $this->get_tracking_items( $order_id, true ), 'order_id'=> $order_id ), 'woocommerce-advanced-shipment-tracking/', get_stylesheet_directory() . '/woocommerce/' );
				} else{
					wc_get_template( 'emails/tracking-info.php', array( 'tracking_items' => $this->get_tracking_items( $order_id, true ), 'order_id'=> $order_id ), 'woocommerce-advanced-shipment-tracking/', wc_advanced_shipment_tracking()->get_plugin_path() . '/templates/' );	
				}				
			}
		}	
	}		
	
	/**
	 * Prevents data being copied to subscription renewals
	 */
	public function woocommerce_subscriptions_renewal_order_meta_query( $order_meta_query, $original_order_id, $renewal_order_id, $new_order_role ) {
		$order_meta_query .= " AND `meta_key` NOT IN ( '_wc_shipment_tracking_items' )";
		return $order_meta_query;
	}

	/*
	 * Works out the final tracking provider and tracking link and appends then to the returned tracking item
	 *
	*/
	public function get_formatted_tracking_item( $order_id, $tracking_item ) {
		$formatted = array();
		$tracking_items   = $this->get_tracking_items( $order_id );
		$trackship_supported = '';	
		foreach($tracking_items as $key=>$item){
			if($item['tracking_id'] == $tracking_item['tracking_id']){
				$shipmet_key = $key;
			}		
		}
		
		$shipment_status = get_post_meta( $order_id, "shipment_status", true);
		
		$status = '';
		
		if(isset($shipment_status[$shipmet_key])){
			if(isset($shipment_status[$shipmet_key]['status'])){
				$status = $shipment_status[$shipmet_key]['status'];	
			}			
		}
		
		if ( version_compare( WC_VERSION, '3.0', '<' ) ) {
			$postcode = get_post_meta( $order_id, '_shipping_postcode', true );
		} else {
			$order    = new WC_Order( $order_id );
			$postcode = $order->get_shipping_postcode();
		}

		$formatted['formatted_tracking_provider'] = '';
		$formatted['formatted_tracking_link']     = '';

		if ( empty( $postcode ) ) {
			$postcode = get_post_meta( $order_id, '_shipping_postcode', true );
		}

		$formatted['formatted_tracking_provider'] = '';
		$formatted['formatted_tracking_link'] = '';
		
		if ( isset( $tracking_item['custom_tracking_provider'] ) &&  !empty( $tracking_item['custom_tracking_provider']) ) {
			$formatted['formatted_tracking_provider'] = $tracking_item['custom_tracking_provider'];
			$formatted['formatted_tracking_link'] = $tracking_item['custom_tracking_link'];
		} else {
			
			$link_format = '';
						
			foreach ( $this->get_providers() as $provider => $format ) {									
				if (  $provider  === $tracking_item['tracking_provider'] ) {
					$link_format = $format['provider_url'];
					$trackship_supported = $format['trackship_supported'];
					$formatted['formatted_tracking_provider'] = $format['provider_name'];
					break;
				}

				if ( $link_format ) {
					break;
				}
			}
				
			$tracking_page = get_option('wc_ast_trackship_page_id');
			$wc_ast_api_key = get_option('wc_ast_api_key');
			$use_tracking_page = get_option('wc_ast_use_tracking_page');
			
			if( $wc_ast_api_key && $use_tracking_page && $trackship_supported == 1 && $status != 'carrier_unsupported'){				
				$order_key = $order->get_order_key();				
				$formatted['formatted_tracking_link'] = get_permalink( $tracking_page ).'?order_id='.$order_id.'&order_key='.$order_key;	
			} else {
				if ( $link_format ) {
					$searchVal = array("%number%", str_replace(' ', '', "%2 $ s") );
					$tracking_number = str_replace(' ', '', $tracking_item['tracking_number']);
					$replaceVal = array( $tracking_number, urlencode( $postcode ) );
					$link_format = str_replace($searchVal, $replaceVal, $link_format); 	
					
					if(isset($tracking_item['tracking_product_code'])){
						$searchnumber2 = array("%number2%", str_replace(' ', '', "%2 $ s") );
						$tracking_product_code = str_replace(' ', '', $tracking_item['tracking_product_code']);					
						$link_format = str_replace($searchnumber2, $tracking_product_code, $link_format); 						
					}
					
					if($order->get_shipping_country() != null){
						$shipping_country = $order->get_shipping_country();	
					} else{
						$shipping_country = $order->get_billing_country();	
					}								
					
					if($shipping_country){												
						
						if($tracking_item['tracking_provider'] == 'jp-post' && $shipping_country != 'JP'){
							$local_en = '&locale=en';
							$link_format = $link_format.$local_en;
						}						
						
						if($tracking_item['tracking_provider'] == 'dhl-ecommerce'){
							$link_format = str_replace('us-en', strtolower($shipping_country).'-en', $link_format); 	
						}
						
						if($tracking_item['tracking_provider'] == 'dhl-freight'){
							$link_format = str_replace('global-en', strtolower($shipping_country).'-en', $link_format);
						}
					}
					
					if($order->get_shipping_postcode() != null){
						$shipping_postal_code = $order->get_shipping_postcode();	
					} else{
						$shipping_postal_code = $order->get_billing_postcode();
					}							
															
					$shipping_country = str_replace(' ', '', $shipping_country);					
					$link_format = str_replace("%country_code%", $shipping_country, $link_format);
															
					if($tracking_item['tracking_provider'] == 'apc-overnight'){	
						$shipping_postal_code = str_replace(' ', '+', $shipping_postal_code);
					} else{
						$shipping_postal_code = str_replace(' ', '', $shipping_postal_code);
					}
					$link_format = str_replace("%postal_code%", $shipping_postal_code, $link_format);
										
					$formatted['formatted_tracking_link'] = $link_format;
				}
			}			
		}

		return $formatted;
	}

	/**
	 * Deletes a tracking item from post_meta array
	 *
	 * @param int    $order_id    Order ID
	 * @param string $tracking_id Tracking ID
	 *
	 * @return bool True if tracking item is deleted successfully
	 */
	public function delete_tracking_item( $order_id, $tracking_id ) {
		$tracking_items = $this->get_tracking_items( $order_id );

		$is_deleted = false;

		if ( count( $tracking_items ) > 0 ) {
			foreach ( $tracking_items as $key => $item ) {
				if ( $item['tracking_id'] == $tracking_id ) {
					unset( $tracking_items[ $key ] );
					$is_deleted = true;
					do_action("fix_shipment_tracking_for_deleted_tracking", $order_id, $key, $item);
					break;
				}
			}
			$this->save_tracking_items( $order_id, $tracking_items );
		}

		return $is_deleted;
	}

	/*
	 * Adds a tracking item to the post_meta array
	 *
	 * @param int   $order_id    Order ID
	 * @param array $tracking_items List of tracking item
	 *
	 * @return array Tracking item
	 */
	public function add_tracking_item( $order_id, $args ) {
		$tracking_item = array();
		
		if(isset($args['tracking_provider'])){
			$tracking_item['tracking_provider'] = wc_clean( $args['tracking_provider'] );
		}
		
		if(isset($args['custom_tracking_provider'])){
			$tracking_item['custom_tracking_provider'] = wc_clean( $args['tracking_provider'] );
		}
		if(isset($args['custom_tracking_link'])){
			$tracking_item['custom_tracking_link'] = wc_clean( $args['custom_tracking_link'] );	
		}
			
		if(isset($args['tracking_number'])){
			$tracking_item['tracking_number'] = wc_clean( $args['tracking_number'] );
		}
		
		if(isset($args['tracking_product_code'])){
			$tracking_item['tracking_product_code'] = wc_clean( $args['tracking_product_code'] );
		}
		
		if(isset($args['date_shipped'])){			
			$date = str_replace("/","-",$args['date_shipped']);			
			$date = date_create($date);
			$date = date_format($date,"d-m-Y");
		
			$tracking_item['date_shipped'] = wc_clean( strtotime( $date ) );
		}
		
		if(isset($args['products_list'])){
			$tracking_item['products_list'] = $args['products_list'];
		}
		
		if(isset($args['status_shipped'])){
			$tracking_item['status_shipped'] = wc_clean( $args['status_shipped'] );
		}
				
		if ( !isset($tracking_item['date_shipped']) ) {
			 $tracking_item['date_shipped'] = time();
		}
		
		if ( 0 == (int) $tracking_item['date_shipped'] ) {
			 $tracking_item['date_shipped'] = time();
		}		

		if ( isset($tracking_item['custom_tracking_provider'] )) {
			$tracking_item['tracking_id'] = md5( "{$tracking_item['custom_tracking_provider']}-{$tracking_item['tracking_number']}" . microtime() );
		} else {
			$tracking_item['tracking_id'] = md5( "{$tracking_item['tracking_provider']}-{$tracking_item['tracking_number']}" . microtime() );
		}
		
		$tracking_item = apply_filters( 'tracking_item_args', $tracking_item, $args, $order_id );
		
		$tracking_items = $this->get_tracking_items( $order_id );
		if($tracking_items){
			$key = $this->seach_tracking_number_in_items($args['tracking_number'], $tracking_items);
			if($key && isset($args['products_list'])){
				array_push($tracking_items[$key]['products_list'],$args['products_list'][0]);
			} else{
				$tracking_items[] = $tracking_item;
			}			
		} else{
			$tracking_items[] = $tracking_item;	
		}			
		//echo '<pre>';print_r($tracking_items);echo '</pre>';
		$this->save_tracking_items( $order_id, $tracking_items );
		
		$status_shipped = (isset($tracking_item["status_shipped"])?$tracking_item["status_shipped"]:"");
		$ast_admin = WC_Advanced_Shipment_Tracking_Admin::get_instance();
		$order = new WC_Order( $order_id );
		
		if( $status_shipped == 1){			
			if('completed' == $order->get_status()){								
				$ast_admin->trigger_woocommerce_order_status_completed( $order_id );	
			} else{
				$order->update_status('completed');
			}			
		}
		
		if( $status_shipped == 2){
			$wc_ast_status_partial_shipped = get_option('wc_ast_status_partial_shipped');
			if($wc_ast_status_partial_shipped){				
				$order->update_status('partial-shipped');
				$ast_admin->trigger_woocommerce_order_status_completed( $order_id );
			}
		}
		
		$formated_tracking_item = $this->get_formatted_tracking_item( $order_id, $tracking_item );
		$tracking_provider = $formated_tracking_item['formatted_tracking_provider'];								
		
		// The text for the note
		$note = sprintf(__("Order was shipped with %s and tracking number is: %s", 'woo-advanced-shipment-tracking'), $tracking_provider, $tracking_item['tracking_number'] );
		
		// Add the note
		$order->add_order_note( $note );
		
		return $tracking_item;
	}
	
	public function seach_tracking_number_in_items($tracking_number, $tracking_items){
		foreach ($tracking_items as $key => $val) {
			if ($val['tracking_number'] === $tracking_number) {
				return $key;
			}
		}
		return null;
	}
	
	/*
	 * Adds a tracking item to the post_meta array from external system programatticaly
	 *
	 * @param int   $order_id    Order ID
	 * @param array $tracking_items List of tracking item
	 *
	 * @return array Tracking item
	 */
	public function insert_tracking_item( $order_id, $args ) {
		$tracking_item = array();
		$tracking_provider = $args['tracking_provider'];
		
		global $wpdb;		
		$woo_shippment_table_name = wc_advanced_shipment_tracking()->table;	
		$shippment_provider = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $woo_shippment_table_name WHERE provider_name=%s",$tracking_provider ) );
		
		if($args['tracking_provider']){
			$tracking_item['tracking_provider']        = wc_clean ( $shippment_provider['0']->ts_slug );
		}		
		if($args['tracking_number']){
			$tracking_item['tracking_number']          = wc_clean( $args['tracking_number'] );
		}
		if($args['date_shipped']){
			$date = str_replace("/","-",$args['date_shipped']);
			$date = date_create($date);
			$date = date_format($date,"d-m-Y");
		
			$tracking_item['date_shipped']             = wc_clean( strtotime( $date ) );
		}
		
		if($args['status_shipped']){
			$tracking_item['status_shipped']           = wc_clean( $args['status_shipped'] );
		}
		
		if ( 0 == (int) $tracking_item['date_shipped'] ) {
			 $tracking_item['date_shipped'] = time();
		}

		$tracking_item['tracking_id'] = md5( "{$tracking_item['tracking_provider']}-{$tracking_item['tracking_number']}" . microtime() );

		$tracking_items   = $this->get_tracking_items( $order_id );
		$tracking_items[] = $tracking_item;
		
		if($tracking_item['tracking_provider']){
			$this->save_tracking_items( $order_id, $tracking_items );
		}
		
		$status_shipped = (isset($tracking_item["status_shipped"])?$tracking_item["status_shipped"]:"");
		$ast_admin = WC_Advanced_Shipment_Tracking_Admin::get_instance();
		$order = new WC_Order( $order_id );
		
		if( $status_shipped == 1){						
			if('completed' == $order->get_status()){								
				$ast_admin->trigger_woocommerce_order_status_completed( $order_id );	
			} else{
				$order->update_status('completed');
			}			
		}		
		
		if( $status_shipped == 2){			
			$order->update_status('partial-shipped');
			$ast_admin->trigger_woocommerce_order_status_completed( $order_id );
		}
		
		$formated_tracking_item = $this->get_formatted_tracking_item( $order_id, $tracking_item );
		$tracking_provider = $formated_tracking_item['formatted_tracking_provider'];								
		
		// The text for the note
		$note = sprintf(__("Order was shipped with %s and tracking number is: %s", 'woo-advanced-shipment-tracking'), $tracking_provider, $tracking_item['tracking_number'] );
		
		// Add the note
		$order->add_order_note( $note );	
		
		return $tracking_item;
	}
	
	

	/**
	 * Saves the tracking items array to post_meta.
	 *
	 * @param int   $order_id       Order ID
	 * @param array $tracking_items List of tracking item
	 */
	public function save_tracking_items( $order_id, $tracking_items ) {
		if ( version_compare( WC_VERSION, '3.0', '<' ) ) {
			update_post_meta( $order_id, '_wc_shipment_tracking_items', $tracking_items );
		} else {			
			$order = new WC_Order( $order_id );			
			$order->update_meta_data( '_wc_shipment_tracking_items', $tracking_items );
			$order->save_meta_data();
		}
	}

	/**
	 * Gets a single tracking item from the post_meta array for an order.
	 *
	 * @param int    $order_id    Order ID
	 * @param string $tracking_id Tracking ID
	 * @param bool   $formatted   Wether or not to reslove the final tracking
	 *                            link and provider in the returned tracking item.
	 *                            Default to false.
	 *
	 * @return null|array Null if not found, otherwise array of tracking item will be returned
	 */
	public function get_tracking_item( $order_id, $tracking_id, $formatted = false ) {
		$tracking_items = $this->get_tracking_items( $order_id, $formatted );

		if ( count( $tracking_items ) ) {
			foreach ( $tracking_items as $item ) {
				if ( $item['tracking_id'] === $tracking_id ) {
					return $item;
				}
			}
		}

		return null;
	}

	/*
	 * Gets all tracking itesm fron the post meta array for an order
	 *
	 * @param int  $order_id  Order ID
	 * @param bool $formatted Wether or not to reslove the final tracking link
	 *                        and provider in the returned tracking item.
	 *                        Default to false.
	 *
	 * @return array List of tracking items
	 */
	public function get_tracking_items( $order_id, $formatted = false ) {
		
		global $wpdb;
		$order = wc_get_order( $order_id );			
		if($order){	
			if ( version_compare( WC_VERSION, '3.0', '<' ) ) {			
				$tracking_items = get_post_meta( $order_id, '_wc_shipment_tracking_items', true );
			} else {						
				$order          = new WC_Order( $order_id );		
				$tracking_items = $order->get_meta( '_wc_shipment_tracking_items', true );			
			}
			
			if ( is_array( $tracking_items ) ) {
				if ( $formatted ) {
					foreach ( $tracking_items as &$item ) {
						$formatted_item = $this->get_formatted_tracking_item( $order_id, $item );
						$item           = array_merge( $item, $formatted_item );
					}
				}
				return $tracking_items;
			} else {
				return array();
			}
		} else {
			return array();
		}
	}

	/**
	* Gets the absolute plugin path without a trailing slash, e.g.
	* /path/to/wp-content/plugins/plugin-directory
	*
	* @return string plugin path
	*/
	public function get_plugin_path() {
		$this->plugin_path = untrailingslashit( plugin_dir_path( dirname( __FILE__ ) ) );
		return $this->plugin_path;
	}
	
	/**
	 * code for check if tracking number in order is delivered or not
	*/
	public function check_tracking_delivered( $order_id ){
		$delivered = true;
		$shipment_status = get_post_meta( $order_id, "shipment_status", true);
		$wc_ast_status_delivered = get_option('wc_ast_status_delivered');						
		
		foreach( (array)$shipment_status as $shipment ){
			$status = $shipment['status'];
			if( $status != 'delivered' ){
				$delivered = false;
			}
		}
		if( count($shipment_status) > 0 && $delivered == true && $wc_ast_status_delivered){
			//trigger order deleivered
			$delivered_enabled = get_option( "wc_ast_status_change_to_delivered", 0);
			if( $delivered_enabled ){
				$order = wc_get_order( $order_id );
				$order_status  = $order->get_status();
				if($order_status == 'completed'){
					$order->update_status('delivered');
				}
			}
		}
	}
	
	/**
	 * validation code add tracking info form
	*/
	public function custom_validation_js(){ ?>
		<script>
		jQuery(document).on("click",".button-save-form",function(e){			
			var error;
			var tracking_provider = jQuery("#tracking_provider");	
			var tracking_number = jQuery("#tracking_number");				
			
			if(tracking_provider.val() == '' ){				
				jQuery( "#select2-tracking_provider-container" ).closest( ".select2-selection" ).css( "border-color", "red" );
				error = true;
			} else {
				jQuery( "#select2-tracking_provider-container" ).closest( ".select2-selection" ).css( "border-color", "" );
			}
			if(tracking_number.val() == '' ){				
				tracking_number.css( "border-color", "red" );
				error = true;
			} else {
				var pattern = /^[0-9a-zA-Z- \b]+$/;	
				if(!pattern.test(tracking_number.val())){			
					tracking_number.css( "border-color", "red" );
					error = true;
				} else{
					tracking_number.css( "border-color", "" );
				}								
			}
						
			if(error == true){
				return false;
			}
		});		
		</script>
	<?php }
	
	/**
	 * code for trigger shipment status email
	*/
	public function trigger_tracking_email($order_id, $old_status, $new_status, $tracking_item, $shipment_status){
		$order = wc_get_order( $order_id );		
		require_once( 'email-manager.php' );		
		
		if( $old_status != $new_status){			
			if($new_status == 'delivered'){
				wc_advanced_shipment_tracking_email_class()->delivered_shippment_status_email_trigger($order_id, $order, $old_status, $new_status, $tracking_item);
			} elseif($new_status == 'failure' || $new_status == 'in_transit' || $new_status == 'on_hold' || $new_status == 'out_for_delivery' || $new_status == 'available_for_pickup' || $new_status == 'return_to_sender'){
				wc_advanced_shipment_tracking_email_class()->shippment_status_email_trigger($order_id, $order, $old_status, $new_status, $tracking_item);
			}	
			do_action( 'ast_trigger_ts_status_change',$order_id, $old_status, $new_status, $tracking_item, $shipment_status );				
		}
	}
	
	/*
	* fix shipment tracking for deleted tracking
	*/
	public function func_fix_shipment_tracking_for_deleted_tracking( $order_id, $key, $item ){
		$shipment_status = get_post_meta( $order_id, "shipment_status", true);
		if( isset( $shipment_status[$key] ) ){
			unset($shipment_status[$key]);
			update_post_meta( $order_id, "shipment_status", $shipment_status);
		}
	}
	
	/*
	* Get formated order id
	*/
	public function get_formated_order_id($order_id){
		if ( is_plugin_active( 'custom-order-numbers-for-woocommerce/custom-order-numbers-for-woocommerce.php' ) ) {
			$alg_wc_custom_order_numbers_enabled = get_option('alg_wc_custom_order_numbers_enabled');
			$alg_wc_custom_order_numbers_prefix = get_option('alg_wc_custom_order_numbers_prefix');
			$new_order_id = str_replace($alg_wc_custom_order_numbers_prefix,'',$order_id);
						
			if($alg_wc_custom_order_numbers_enabled == 'yes'){				
				$args = array(
					'post_type'		=>	'shop_order',			
					'posts_per_page'    => '1',
					'meta_query'        => array(
						'relation' => 'AND', 
						array(
						'key'       => '_alg_wc_custom_order_number',
						'value'     => $new_order_id,
						),
					),
					'post_status' => array_keys( wc_get_order_statuses() ) , 	
				);
				$posts = get_posts( $args );
				$my_query = new WP_Query( $args );				
				
				if( $my_query->have_posts() ) {
					while( $my_query->have_posts()) {
						$my_query->the_post();
						if(get_the_ID()){
							$order_id = get_the_ID();
						}									
					} // end while
				} // end if
				$order_id;
				wp_reset_postdata();	
			}			
		}
		
		if ( is_plugin_active( 'woocommerce-sequential-order-numbers/woocommerce-sequential-order-numbers.php' ) ) {
						
			$s_order_id = wc_sequential_order_numbers()->find_order_by_order_number( $order_id );			
			if($s_order_id){
				$order_id = $s_order_id;
			}
		}
		
		if ( is_plugin_active( 'woocommerce-sequential-order-numbers-pro/woocommerce-sequential-order-numbers-pro.php' ) ) {
			
			// search for the order by custom order number
			$query_args = array(
				'numberposts' => 1,
				'meta_key'    => '_order_number_formatted',
				'meta_value'  => $order_id,
				'post_type'   => 'shop_order',
				'post_status' => 'any',
				'fields'      => 'ids',
			);
			
			$posts = get_posts( $query_args );			
			if(! empty( $posts )){	
				list( $order_id ) = $posts;			
			}			
		}
		
		if ( is_plugin_active( 'woocommerce-jetpack/woocommerce-jetpack.php' ) ) {
			$wcj_order_numbers_enabled = get_option('wcj_order_numbers_enabled');			
			// search for the order by custom order number			
			if($wcj_order_numbers_enabled == 'yes'){
				$query_args = array(
					'numberposts' => 1,
					'meta_key'    => '_wcj_order_number',
					'meta_value'  => $order_id,
					'post_type'   => 'shop_order',
					'post_status' => 'any',
					'fields'      => 'ids',
				);
				
				$posts = get_posts( $query_args );
				if(! empty( $posts )){	
					list( $order_id ) = $posts;			
				}			
			}
		}
		
		if ( is_plugin_active( 'wp-lister-amazon/wp-lister-amazon.php' ) ) {
			$wpla_use_amazon_order_number = get_option( 'wpla_use_amazon_order_number' );
			if($wpla_use_amazon_order_number == 1){
				$args = array(
					'post_type'		=>	'shop_order',			
					'posts_per_page'    => '1',
					'meta_query'        => array(
						'relation' => 'AND', 
						array(
						'key'       => '_wpla_amazon_order_id',
						'value'     => $order_id
						),
					),
					'post_status' => array('wc-pending', 'wc-processing', 'wc-on-hold', 'wc-completed', 'wc-delivered', 'wc-cancelled', 'wc-refunded', 'wc-failed','wc-bit-payment') , 	
				);
				$posts = get_posts( $args );
				$my_query = new WP_Query( $args );				
				
				if( $my_query->have_posts() ) {
					while( $my_query->have_posts()) {
						$my_query->the_post();
						if(get_the_ID()){
							$order_id = get_the_ID();
						}									
					} // end while
				} // end if
				wp_reset_postdata();	
			}			
		}	
		
		if ( is_plugin_active( 'wp-lister/wp-lister.php' ) || is_plugin_active( 'wp-lister-for-ebay/wp-lister.php' )) {
			$args = array(
				'post_type'		=>	'shop_order',			
				'posts_per_page'    => '1',
				'meta_query'        => array(
					'relation' => 'OR', 
					array(
						'key'       => '_ebay_extended_order_id',
						'value'     => $order_id
					),
					array(
						'key'       => '_ebay_order_id',
						'value'     => $order_id
					),					
				),
				'post_status' => array('wc-pending', 'wc-processing', 'wc-on-hold', 'wc-completed', 'wc-delivered', 'wc-cancelled', 'wc-refunded', 'wc-failed','wc-bit-payment') , 	
			);
			$posts = get_posts( $args );
			$my_query = new WP_Query( $args );				
			
			if( $my_query->have_posts() ) {
				while( $my_query->have_posts()) {
					$my_query->the_post();
					if(get_the_ID()){
						$order_id = get_the_ID();
					}									
				} // end while
			} // end if
			wp_reset_postdata();
		}
		
		if ( is_plugin_active( 'yith-woocommerce-sequential-order-number-premium/init.php' ) ) {
			$args = array(
				'post_type'		=>	'shop_order',			
				'posts_per_page'    => '1',
				'meta_query'        => array(
					'relation' => 'AND', 
					array(
					'key'       => '_ywson_custom_number_order_complete',
					'value'     => $order_id
					),
				),
				'post_status' => array('wc-pending', 'wc-processing', 'wc-on-hold', 'wc-completed', 'wc-delivered', 'wc-cancelled', 'wc-refunded', 'wc-failed','wc-bit-payment') , 	
			);
			$posts = get_posts( $args );
			$my_query = new WP_Query( $args );				
			
			if( $my_query->have_posts() ) {
				while( $my_query->have_posts()) {
					$my_query->the_post();
					if(get_the_ID()){
						$order_id = get_the_ID();
					}									
				} // end while
			} // end if
			wp_reset_postdata();			
		}
		
		return $order_id;
	}
	
	/*
	* Get custom order number
	*/
	public function get_custom_order_number($order_id){
		if ( is_plugin_active( 'custom-order-numbers-for-woocommerce/custom-order-numbers-for-woocommerce.php' ) ) {
			$custom_order_number = get_post_meta( $order_id, '_alg_wc_custom_order_number', true );
			if(!empty($custom_order_number)){
				return $custom_order_number;
			}	
		}
		
		if ( is_plugin_active( 'woocommerce-sequential-order-numbers/woocommerce-sequential-order-numbers.php' ) ) {						
			$custom_order_number = get_post_meta( $order_id, '_order_number_formatted', true );
			if(!empty($custom_order_number)){
				return $custom_order_number;
			}
		}
		
		if ( is_plugin_active( 'woocommerce-sequential-order-numbers-pro/woocommerce-sequential-order-numbers-pro.php' ) ) {				
			$custom_order_number = get_post_meta( $order_id, '_order_number_formatted', true );
			if(!empty($custom_order_number)){
				return $custom_order_number;
			}	
		}
		
		if ( is_plugin_active( 'woocommerce-jetpack/woocommerce-jetpack.php' ) ) {			
			$custom_order_number = get_post_meta( $order_id, '_wcj_order_number', true );
			if(!empty($custom_order_number)){
				return $custom_order_number;
			}
		}
		
		if ( is_plugin_active( 'wp-lister-amazon/wp-lister-amazon.php' ) ) {			
			$custom_order_number = get_post_meta( $order_id, '_wpla_amazon_order_id', true );
			if(!empty($custom_order_number)){
				return $custom_order_number;
			}	
		}	
		
		if ( is_plugin_active( 'wp-lister/wp-lister.php' ) || is_plugin_active( 'wp-lister-for-ebay/wp-lister.php' )) {
			$custom_order_number = get_post_meta( $order_id, '_ebay_extended_order_id', true );
			if(empty($custom_order_number)){
				$custom_order_number = get_post_meta( $order_id, '_ebay_order_id', true );
			}
			if(!empty($custom_order_number)){				
				return $custom_order_number;
			}	
		}	
		if ( is_plugin_active( 'yith-woocommerce-sequential-order-number-premium/init.php' ) ) {			
			$custom_order_number = get_post_meta( $order_id, '_ywson_custom_number_order_complete', true );
			if(!empty($custom_order_number)){
				return $custom_order_number;
			}	
		}
		return $order_id;		
	}
	
	public function get_option_value_from_array($array,$key,$default_value){		
		$array_data = get_option($array);	
		$value = '';
		
		if(isset($array_data[$key])){
			$value = $array_data[$key];	
		}					
		
		if($value == ''){
			$value = $default_value;
		}
		return $value;
	}
	
	public function check_if_virtual_order($order_id){
		
		$order = wc_get_order( $order_id );		
		
		if( is_a( $order, 'WC_Order' ) ) {
			
			$order_items = $order->get_items();
			
			foreach( $order_items as $order_item_id => $order_item ) {
				
				$product = wc_get_product( $order_item['product_id'] );
				
				if ( $order_item->get_variation_id() ) {
					if ( 'product_variation' === get_post_type( $order_item->get_variation_id() ) )
						$product = wc_get_product( $order_item->get_variation_id() );
				}
					
				if( $product ) {
					if( $product->is_virtual() || $product->is_downloadable()) return true;						
				}
			}
		}
		return false;
	}
}