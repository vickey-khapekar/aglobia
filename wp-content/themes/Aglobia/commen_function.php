<?php
function icare_add_woocommerce_support() {
    add_theme_support( 'woocommerce', array(
        /*'thumbnail_image_width' => 300,
        'single_image_width'    => 600,

        'product_grid'          => array(
            'default_rows'    => 3,
            'min_rows'        => 2,
            'max_rows'        => 8,
            'default_columns' => 4,
            'min_columns'     => 2,
            'max_columns'     => 5,
        ),*/
    ) );
}
add_action( 'after_setup_theme', 'icare_add_woocommerce_support' );

function remove_image_zoom_support() {
    remove_theme_support( 'wc-product-gallery-zoom' );
}
//add_action( 'after_setup_theme', 'remove_image_zoom_support', 100 );

//add_theme_support( 'wc-product-gallery-zoom' );
add_theme_support( 'wc-product-gallery-lightbox' );
add_theme_support( 'wc-product-gallery-slider' );

//https://www.tychesoftwares.com/woocommerce-shop-page-hooks-visual-guide-with-code-snippets/
//https://businessbloomer.com/woocommerce-visual-hook-guide-single-product-page/

function dk_theme_enqueue_styles() {

    $parent_style = 'adcustom-style'; // This is 'adforest-style' for the AdForest theme.

    wp_enqueue_style( $parent_style, get_template_directory_uri() . '/css/custom.css' );
     /*wp_enqueue_style( 'child-style',
        get_stylesheet_directory_uri() . '/custom.css', array( $parent_style ), wp_get_theme()->get('Version')
    );*/
}
add_action( 'wp_enqueue_scripts', 'dk_theme_enqueue_styles', 1);

function dk__update_custom_roles() {
	//remove_role( 'ourpartenr' );
     add_role( 'ourpartner', 'Our Partners', 
        	array( 
					'read' => true,
					// 'create_posts'      => true, // Allows user to create new posts

					// 'edit_posts'        => true, // Allows user to edit their own posts

					//'edit_others_posts' => true, // Allows user to edit others posts too

					// 'publish_posts' => true, // Allows the user to publish posts

					// 'manage_categories' => true, // Allows user to manage post categories
					'level_0' => true
        	) 
        );
     add_role( 'interiordesigner', 'Interior Designer', 
          array( 
          'read' => true,
          // 'create_posts'      => true, // Allows user to create new posts

          // 'edit_posts'        => true, // Allows user to edit their own posts

          //'edit_others_posts' => true, // Allows user to edit others posts too

          // 'publish_posts' => true, // Allows the user to publish posts

          // 'manage_categories' => true, // Allows user to manage post categories
          'level_0' => true
          ) 
        );
       
}
add_action( 'init', 'dk__update_custom_roles' );

/**
 * Register meta boxes.
 */
function fscf_register_meta_boxes() {


    add_meta_box( 'fscf-1', __( 'Select Partner', 'fscf' ), 'fscf_display_callback', 'partners','normal',
  'high');
 add_meta_box( 'fscf-2', __( 'Select Interior Designer', 'fscf' ), 'fscf_display_callback_interior', 'interiordesigners','normal',
  'high');
    
}
add_action( 'add_meta_boxes', 'fscf_register_meta_boxes',10,1 );

function fscf_display_callback($post)
{
	$fscfauthor = get_post_meta(get_the_ID(), 'fscf_author', true);
	$ucargs = array(
    'orderby'                 => 'display_name',
    'order'                   => 'ASC',
    'show_option_none'        => '----Select Partner----',
    'option_none_value'       => -1,
    'multi'                   => false,
    'show'                    => 'display_name',
    'echo'                    => true,
    'selected'                => $fscfauthor,
    'include_selected'        => false,
    'name'                    => 'fscf_author', // string
    'id'                      => null, // integer
    'class'                   => null, // string 
    'blog_id'                 => $GLOBALS['blog_id'],
    'who'                     => null, // string,
    'role'                    => 'ourpartner', // string|array,
);
	 echo '<p><lable>Select Partner</lable></p>';
 wp_dropdown_users( $ucargs );
}

function fscf_display_callback_interior($post)
{
  $fscfauthor = get_post_meta(get_the_ID(), 'fscf_author', true);
  $ucargs = array(
    'orderby'                 => 'display_name',
    'order'                   => 'ASC',
    'show_option_none'        => '----Select Interior----',
    'multi'                   => false,
    'show'                    => 'display_name',
    'echo'                    => true,
    'selected'                => $fscfauthor,
    'include_selected'        => false,
    'name'                    => 'fscf_author', // string
    'id'                      => null, // integer
    'class'                   => null, // string 
    'blog_id'                 => $GLOBALS['blog_id'],
    'who'                     => null, // string,
    'role'                    => 'interiordesigner', // string|array,
);
   //echo '<p><lable>Select Interior Designer</lable></p>';
 wp_dropdown_users( $ucargs );
}
/* @param int $post_id Post ID
 */
function fscf_save_meta_box( $post_id ) 
{
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
    if ( $parent_id = wp_is_post_revision( $post_id ) ) {
        $post_id = $parent_id;
    }
    $fields = [
        'fscf_author',
       // 'fscf_subtitle',
        //'fscf_published_date',
    ];
    foreach ( $fields as $field ) {
        if ( array_key_exists( $field, $_POST ) ) {
            update_post_meta( $post_id, $field, sanitize_text_field( $_POST[$field] ) );
        }
     }
}
add_action( 'save_post', 'fscf_save_meta_box' );

add_action( 'woocommerce_after_shop_loop_item', 'dk_addcustome_btn', 11 );

// temporary hiding customize btn
// function dk_addcustome_btn(){
//      global $product;

//      //echo '<a href="' . esc_url( get_permalink( $product->id ) ) . '">Customize</a>';
//      echo '<a class="button customize" href="' . esc_url( home_url('/customize/?pid='. $product->id ) ) . '">Customize</a>';
// }
/*================================= ======================================*/

function dk_addcustome_btn_productpage()
{
 
   // echo 'CUSTOMIZE CONTENT'.get_post_meta(get_the_ID(), "CUSTOMIZE CONTENT", true);
  
    echo '<a class="button customize" href="' . esc_url( home_url('/customize/?pid='. get_the_ID() ) ) . '">Customize</a>';
}
add_action( 'woocommerce_after_add_to_cart_button', 'dk_addcustome_btn_productpage');

/*================================= ======================================*/
function dkcw_woo_attribute($porudctId)
{
  if($porudctId)
  {
    global $woocommerce, $post, $product;
    $attrlist ='<ul>';
    $attr = get_post_meta($porudctId,'_product_attributes',true);
      if($attr)
      {
        foreach ($attr as $key => $value) 
        {# code...
          if($key!="pa_size")
          {
        $attrlist .='<li><a href="'.get_the_permalink(get_the_ID()).'?pid='.$porudctId.'&key='.$key.'#'.$key.'">'.ucwords(str_replace("pa_"," ",$key)).'</a></li>';
          }
        } //end foreach
        $attrlist .='</ul>';        
        }
        echo $attrlist;
        
      }  
}

//add_action('woocommerce_single_product_summary', 'dkcw_woo_attribute', 25);

/*================================= ======================================*/

function get_woocustome_attributes($porudctId, $attrname,$getattr)
{
    global $woocommerce, $wpdb;
    //$pa_koostis_value = get_post_meta($porudctId,'_product_attributes', true);

  //echo $porudctId.'/'.$attrname.'/'.$attrVal;

      $variable_product1= new WC_Product_Variable( $porudctId );
      $atr_variations = $variable_product1->get_available_variations();
    

              $attr_data=array();
             foreach ( $atr_variations as $attri_bute )
             {

               
            $regular_price = $attri_bute['display_regular_price'];
            $display_price = $attri_bute['display_price'];
            $variation_id = $attri_bute['variation_id'].', ';

            if($display_price!="")
            {
              $saleprice= $display_price;
            }else{
              $saleprice= $regular_price;
            }

            $attributes     = $attri_bute['attributes'];

            //$_subjectsPet   = $attributes['attribute_pa_subjects'];
            $_color     = $attributes['attribute_pa_color'];
            $_size         = $attributes['attribute_pa_size'];
 //print_r($_color);
            //echo $variation_id.' / '.$_color.' / '.$_subjectsPet.' / '. $_size.'<br />';

            $attrimage      = $attri_bute['image'];

            $title          = $attrimage['title'];
            $imgsrc         = $attrimage['src'];
            $thumbnail_100x100 = $attrimage['gallery_thumbnail_src']; //100x100
            $thumb_src300x300 = $attrimage['thumb_src']; //300x300

                    if($attrname=='pa_color')
                    {
                        $attr_data[]= $_color; //attribute_pa_color
                    }else{
                         $attr_data[]= $_size; //attribute_pa_size
                    }
                                        
            }


              //return array_unique($attr_data);
              
              return array_unique($attr_data);

}
/*================================= ======================================*/
function getall_woocustome_attributes($porudctId, $attrname,$getattr)
{
    global $woocommerce, $wpdb;
    //$pa_koostis_value = get_post_meta($porudctId,'_product_attributes', true);

  //echo $porudctId.'/'.$attrname.'/'.$attrVal;

      $variable_product1= new WC_Product_Variable( $porudctId );
      $atr_variations = $variable_product1->get_available_variations();
    

              $attr_data=array();
             foreach ( $atr_variations as $attri_bute )
             {

               
            $regular_price = $attri_bute['display_regular_price'];
            $display_price = $attri_bute['display_price'];
            $variation_id = $attri_bute['variation_id'].', ';

            if($display_price!="")
            {
              $saleprice= $display_price;
            }else{
              $saleprice= $regular_price;
            }

            $attributes     = $attri_bute['attributes'];
            $_subjectsPet   = $attributes['attribute_pa_subjects'];
            $_color     = $attributes['attribute_pa_color'];
            $_size         = $attributes['attribute_pa_size'];

            //echo $variation_id.' / '.$_color.' / '.$_subjectsPet.' / '. $_size.'<br />';

            $attrimage      = $attri_bute['image'];

            $title          = $attrimage['title'];
            $imgsrc         = $attrimage['src'];
            $thumbnail_100x100 = $attrimage['gallery_thumbnail_src']; //100x100
            $thumb_src300x300 = $attrimage['thumb_src']; //300x300

            
             if($attrname=='pa_color')
             {

              
              $attr_data[]= $_color; //attribute_pa_color
               
             }else{

                  if($getattr==$_color && $attributes['attribute_'.$attrname])
                  {
                    $attr_data[]= $attributes['attribute_'.$attrname]; //attribute_pa_color
                  }elseif ($getattr=="") {
                    # code...
                 
                    $attr_data[]= $attributes['attribute_'.$attrname]; //attribute_pa_color
                  }
             }
                  
            }

              //return array_unique($attr_data);
              
              return array_unique($attr_data);

}
/*================================= ======================================*/
function get_woocustome_attributes_price($porudctId, $attrVal_s,$attrVal_p,$attrVal_f)
{
   global $woocommerce, $wpdb;
    //$pa_koostis_value = get_post_meta($porudctId,'_product_attributes', true);

  //echo $porudctId.'/'.$attrname.'/'.$attrVal;

      $variable_product1= new WC_Product_Variable( $porudctId );
      $_variations = $variable_product1->get_available_variations();
           
              $attrdata = "";
             foreach ( $_variations as $attri_bute ) :

                /*echo '<pre>';
                print_r($attri_bute);*/
            $regular_price = $attri_bute['display_regular_price'];
            $display_price = $attri_bute['display_price'];
            $variation_id = $attri_bute['variation_id'];

            if($display_price!="")
            {
              $saleprice= $display_price;
            }else{
              $saleprice= $regular_price;
            }

            $attributes     = $attri_bute['attributes'];
            $_subjectsPet   = $attributes['attribute_pa_subjects'];
            $_color     = $attributes['attribute_pa_color'];
            $_size     = $attributes['attribute_pa_size'];

            if($_subjectsPet == $attrVal_p && $_color == $attrVal_s && $_size==$attrVal_f)
            {
              $attrdata = $saleprice.'@'.$variation_id;

            }/*elseif ($_subjectsPet==$attrVal_p && $_color==$attrVal_s && $_size!=$attrVal_f) {
              # code...
              $attrdata = $saleprice.'@'.$variation_id;
            }*/

              endforeach;

              return $attrdata;

}

/*================================= ======================================*/

function dk_oldnewbutton()
{
 
       // echo 'CUSTOMIZE CONTENT'.get_post_meta(get_the_ID(), "CUSTOMIZE CONTENT", true);
      if (!is_user_logged_in() )
      {
        echo '<a href="javascript:void(0)" class="button loginSignup customize" >Customize</a>';
        echo '<a href="javascript:void(0)" class="button loginSignup customize" >Old To New</a>';

      }else{
        echo '<a class="button customize" href="' . esc_url( home_url('/customize/' ) ) . '">Customize</a>';
        echo '<a class="button customize" href="' . esc_url( home_url('/old-to-new/') ) . '">Old To New</a>';

      }
    
}

add_shortcode( 'OldnewButton', 'dk_oldnewbutton' );


function dk_woonew_arrival_products()
      {
        global $wpdb;
        $args = array(
                'post_type' => 'product',
                'stock' => 1,
                'posts_per_page' => -1,
                'orderby' =>'date',
                'order' => 'DESC' 
              );
      $newArrival = new WP_Query( $args );
      ?>
      <div id="new_arrival_products" class="owl-carousel owl-theme">
      <?php
      while ( $newArrival->have_posts() ) : $newArrival->the_post();
       global $product; 
       ?>
      <div class="item">
      <a id="id-<?php the_id(); ?>" href="<?php echo get_the_permalink($newArrival->post->ID); ?>" title="<?php echo get_the_title($newArrival->post->ID); ?>">
      <?php 
      echo '<div class="product-arv">';
      if (has_post_thumbnail( $newArrival->post->ID ))
      {

        echo get_the_post_thumbnail($newArrival->post->ID, 'medium'); 
      }else{
       echo '<img src="'.woocommerce_placeholder_img_src().'" alt="My Image Placeholder" width="65px" height="115px" />'; 
        }
      echo '</div>';
     ?>
      <h3><?php echo get_the_title($newArrival->post->ID); ?></h3>
      <span class="price"><?php echo $product->get_price_html($newArrival->post->ID); ?></span>
      </a>
      <?php 
      woocommerce_template_loop_add_to_cart( $newArrival->post, $product );
       echo '<a class="button customize" href="' . esc_url( home_url('/customize/?pid='. $newArrival->post->ID ) ) . '">Customize</a>';
      ?>
      </div><!-- /item -->
      <?php endwhile; 
      ?>
      </div>
      <script type="text/javascript">
      jQuery(document).ready(function()
      {
            jQuery.noConflict();
            jQuery('#new_arrival_products').owlCarousel({
            //rtl:true,
                //loop:true,
                margin:10,
                thumbs: true,
                /*thumbsPrerendered: true,*/
                nav:true,
                responsive:{
                0:{
                  items:1
                  },
                600:{
                    items:2
                  },
                1000:{
                  items:4        
                }
            }

            });
      });
    </script>
      <?php
       wp_reset_query();
}

add_shortcode( 'NewArrivalProducts', 'dk_woonew_arrival_products' );

//--------------Customize product main image
function toplevel_mainImage($attr,$porudctId)
{
  if($attr && $porudctId!="")
      {         
        $arcnt = 0;
          foreach ($attr as $key => $value)
          {
             //$array[]=$key;

              $pterms = wc_get_product_terms($porudctId, $key, $args);
              rsort($pterms);
             
                foreach ($pterms as $key1 => $term)
                {  
                   //$array[$key][]=$term->slug;

                        $array[$key][0]=array(
                              'AttributeCode'=>trim($key),
                              'AttributeValue'=>trim($term->slug)
                        );  
                 
                }
          }
          foreach ($array as $key => $value) {
            # code...
                  $arr[]=$value;
          }

     return str_replace(']', '', str_replace('[', '', json_encode($arr)));        
         
      }
}

//*******************************************Order Status
// ---------------------
// 1. Register Order Status
 
add_filter( 'woocommerce_register_shop_order_post_statuses', 'dkwoo_register_custom_order_status' );
 
function dkwoo_register_custom_order_status( $order_statuses ){
    
   // Status must start with "wc-"
   $order_statuses['wc-placed'] = array(                                 
   'label'                     => _x( 'Placed', 'Order status', 'woocommerce' ),
   'public'                    => false,                                 
   'exclude_from_search'       => false,                                 
   'show_in_admin_all_list'    => true,                                 
   'show_in_admin_status_list' => true,                                 
   'label_count'               => _n_noop( 'Placed <span class="count">(%s)</span>', 'Placed <span class="count">(%s)</span>', 'woocommerce' ),                              
   );      
   return $order_statuses;
}
 
// ---------------------
// 2. Show Order Status in the Dropdown @ Single Order and "Bulk Actions" @ Orders
 
add_filter( 'wc_order_statuses', 'dkwoo_show_custom_order_status' );
 
function dkwoo_show_custom_order_status( $order_statuses ) {      
   $order_statuses['wc-placed'] = _x( 'Placed', 'Order status', 'woocommerce' );       
   return $order_statuses;
}
 
add_filter( 'bulk_actions-edit-shop_order', 'dkwoo_get_custom_order_status_bulk' );
 
function dkwoo_get_custom_order_status_bulk( $bulk_actions ) {
   // Note: "mark_" must be there instead of "wc"
   $bulk_actions['mark_placed'] = 'Change status to Placed';
   return $bulk_actions;
}
 
 
// ---------------------
// 3. Set Custom Order Status @ WooCommerce Checkout Process
 
add_action( 'woocommerce_thankyou', 'dkwoo_thankyou_change_order_status' );
 
function dkwoo_thankyou_change_order_status( $order_id ){
   if( ! $order_id ) return;
   $order = wc_get_order( $order_id );
 
   // Status without the "wc-" prefix
   $order->update_status( 'placed' );
}
//******************************************* Order Status End
//$url = 'https://image.icarefurnishers.com/api/Services/GetPrice?skuData=%7B%22companyCode%22%3A%22ICF%22%2C%22storeCode%22%3A%22NGP%22%2C%22SKU%22%3A%22IFC_BUW_SFS_01%22%7D';
function getProductPrice($pricedata)
{
  $url='https://image.icarefurnishers.com/api/Services/GetPrice?skuData='.urlencode( json_encode($pricedata));
  

    $ch = curl_init();

// Set query data here with the URL
curl_setopt($ch, CURLOPT_URL, $url); 

curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($ch, CURLOPT_TIMEOUT, 3);
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
$content = trim(curl_exec($ch));
curl_close($ch);
//print_r($content);

    $jsonArrayResponse = json_decode($content);
   /*echo '<pre>';
    print_r($jsonArrayResponse);*/

    $response = $jsonArrayResponse->Response;
    if($response)
    {
    /*print_r($response);
    exit;*/
	$dataval ="";
    if($response=='Success')
    {
      $dataval = $jsonArrayResponse->Data[0];	 
    }
  }
    return $dataval;

}