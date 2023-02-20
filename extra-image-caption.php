<?php
/*
Plugin Name: Extra Caption for Image block and Featured image
Description: Show the extra media image caption and url on single page of post type using gutanburg image block editor or elementor.
Author: Sal Hakim
Version: 1.0
Author URI: https://salhakim.com/
Text Domain : ecibfi-extra-caption-image
*/

if (!defined('ABSPATH')) exit;

define('ECIBFI_PLUGIN_VERSION',1.0);

if (!defined('ECIBFI_PLUGIN_DIR_PATH'))
	define('ECIBFI_PLUGIN_DIR_PATH', plugin_dir_path(__FILE__));

if (!defined('ECIBFI_PLUGIN_BASENAME'))
	define('ECIBFI_PLUGIN_BASENAME', plugin_basename(__FILE__));

if (!defined('ECIBFI_PLUGIN_URL'))
	define('ECIBFI_PLUGIN_URL', plugins_url() . '/' . basename(dirname(__FILE__)));

$ecibfi_attchments_options = array(
	'image_photo_credit' => array(
		'label'       => __( 'Photo Credit' ),
		'input'       => 'textarea',
		'application' => 'image',
		'exclusions'  => array( 'audio', 'video' )
    ),
    'image_photo_credit_link' => array(
		'label'       => __( 'Link to Photo Credit Author' ),
		'input'       => 'text',
		'application' => 'image',
		'exclusions'  => array( 'audio', 'video' )
    ),
);

if( !class_exists( 'ECIBFI_GWS_Custom_Media_Fields' ) ) {

    Class ECIBFI_GWS_Custom_Media_Fields {
        
        private $ecibfi_media_fields = array();

        function __construct( $ecibfi_attchments_options ) {
            $plugin = plugin_basename(__FILE__);
            $this->ecibfi_media_fields = $ecibfi_attchments_options;
            
            add_action( 'wp_head', array( $this, 'ecibfi_wp_head_callback' ) );
            add_filter( 'attachment_fields_to_edit', array( $this, 'ecibfi_applyFilter' ), 11, 2 );
            add_filter( 'attachment_fields_to_save', array( $this, 'ecibfi_saveFields' ), 11, 2 );
            add_filter( 'post_thumbnail_html', array( $this, 'ecibfi_gws_post_thumbnail_fallback'), 20, 5 );
            add_filter( 'render_block', array( $this, 'ecibfi_gws_gutenberg_gallery_lightbox'), 10, 2 );
            add_filter( 'elementor/widget/render_content', array( $this, 'ecibfi_gws_change_heading_widget_content'), 10, 2 );
            add_action( 'admin_menu', [$this, 'ecibfi_plugin_settings_menu_page']);
            add_action( 'init', [$this, 'ecibfi_get_media_setting_options']);
            add_filter( "plugin_action_links_$plugin", [$this, 'ecibfi_add_plugin_link']);
        }

        

        function ecibfi_wp_head_callback() { ?><style>.gcl-fig { display: flex; justify-content: space-between; }</style>
            <?php
        }

        function ecibfi_add_plugin_link( $links ) {
            
            $setting_link = '<a href="'. admin_url('admin.php?page=ecibfi_extra_caption_settings') .'">' . __( 'Settings', 'email-verification-for-contact-form-7' ) . '</a>';
            array_unshift( $links, $setting_link );
        
            return $links;
        }

        public function ecibfi_applyFilter( $form_fields, $post = null ) {
            
            // If our fields array is not empty 
            if ( ! empty( $this->ecibfi_media_fields ) ) {
                // We browse our set of options 
                foreach ( $this->ecibfi_media_fields as $field => $values ) {
                    // If the field matches the current attachment mime type 
                    // and is not one of the exclusions 
                    if ( preg_match( "/" . $values['application'] . "/", $post->post_mime_type) && ! in_array( $post->post_mime_type, $values['exclusions'] ) ) {
                        // We get the already saved field meta value 
                        $meta = get_post_meta( $post->ID, '_' . $field, true );
                        // Define the input type to 'text' by default 
                        // $values['input'] = 'text';

                        switch ( $values['input'] ) {
                            default:
                            case 'text':
                                $values['input'] = 'text';
                                break;
                            case 'textarea':
                                $values['input'] = 'textarea';
                                break;
                        }
                        // And set it to the field before building it 
                        $values['value'] = $meta;
                        // We add our field into the $form_fields array 
                        $form_fields[$field] = $values;
                    }
                }
            }
            // We return the completed $form_fields array 
            // echo '<pre>'; print_r( $form_fields ); echo '</pre>'; die;

            return $form_fields;
        }

        function ecibfi_saveFields( $post, $attachment ) {
            // If our fields array is not empty 
            if ( ! empty( $this->ecibfi_media_fields ) ) {
                // Browser those fields 
                foreach ( $this->ecibfi_media_fields as $field => $values ) {
                    // If this field has been submitted (is present in the $attachment variable) 
                    if ( isset( $attachment[$field] ) ) {
                        // If submitted field is empty 
                        // We add errors to the post object with the "error_text" parameter we set in the options 
                        if ( strlen( trim( $attachment[$field] ) ) == 0 )
                            $post['errors'][$field]['errors'][] = __( $values['error_text'] );
                        // Otherwise we update the custom field 
                        else
                            update_post_meta( $post['ID'], '_' . $field, $attachment[$field] );
                    }
                    // Otherwise, we delete it if it already existed 
                    else {
                        delete_post_meta( $post['ID'], $field );
                    }
                }
            }
            return $post;
        }

        public function ecibfi_gws_gutenberg_gallery_lightbox( $block_content, $block ) {
            if ( 'core/image' !== $block['blockName'] ) return $block_content;

            $settings_info = $this->ecibfi_get_media_setting_options();
        
            $id= $block['attrs']['id'];
            $photo_credit = get_post_meta( $id, '_image_photo_credit', true );
            $photo_credit_link = get_post_meta( $id, '_image_photo_credit_link', true );
            $author_link = (isset($photo_credit_link) && !empty($photo_credit_link)) ? '<a href="'.$photo_credit_link.'" title="'.$photo_credit.'">'.$photo_credit.'</a>': '';
            
            $photo_credit = (isset($photo_credit) && !empty($photo_credit) && isset($author_link) && !empty($author_link)) ? str_replace($photo_credit,$author_link,$photo_credit) : '';

            if(isset($photo_credit) && !empty($photo_credit) && is_singular() && !empty($settings_info['credit_text']) && is_array($settings_info['post_show']) && in_array( $settings_info['cur_post_type'], $settings_info['post_show'] ) ) {
                $block_content = str_replace(['<figcaption','</figcaption>'], ['<div class="gcl-fig"><figcaption','</figcaption><figcaption>'.$settings_info['credit_text'].' '.$photo_credit .'</figcaption></div>'], $block_content);
            }                
            return $block_content;
        }

        public function ecibfi_gws_post_thumbnail_fallback( $html, $post_id, $post_thumbnail_id, $size, $attr ) {

            $settings_info = $this->ecibfi_get_media_setting_options();            
            
            if( (is_single($post_id) || is_page($post_id)) && ( is_array($settings_info['post_show']) && in_array( 'featured_image', $settings_info['post_show'] ) ) ) {
                
                // return you fallback image either from post of default as html img tag.
                if ( !empty( $html ) ) {
                    
                    $photo_credit = get_post_meta( $post_thumbnail_id, '_image_photo_credit', true );
                    $photo_credit_link = get_post_meta( $post_thumbnail_id, '_image_photo_credit_link', true );
                    $author_link = (isset($photo_credit_link) && !empty($photo_credit_link)) ? '<a href="'.$photo_credit_link.'" title="'.$photo_credit.'">'.$photo_credit.'</a>': '';
                    $default_caption = wp_get_attachment_caption($post_thumbnail_id);
                    if(!empty($default_caption)) $default_caption = "<figcaption>".$default_caption."</figcaption>";
                    
                    $photo_credit = (isset($photo_credit) && !empty($photo_credit) && isset($author_link) && !empty($author_link)) ? str_replace($photo_credit,$author_link,$photo_credit) : '';
                                   
                    if(isset($photo_credit) && !empty($photo_credit) && is_singular() && !empty($settings_info['credit_text']) ){
                        $html = str_replace('>', '><div class="gcl-fig">'.$default_caption.'<figcaption>'.$settings_info['credit_text'].' '.$photo_credit.'</figcaption></div>', $html);
                    }
                }
            }            
            return $html;
        }

        function ecibfi_gws_change_heading_widget_content( $widget_content, $widget ) {
            
            if ( 'image' === $widget->get_name() ) {
                
                $settings = $widget->get_settings();
                $settings_info = $this->ecibfi_get_media_setting_options();

                if(isset($settings["image"]) && !empty($settings["image"])) {
                    $thumbnail_id = $settings["image"]["id"];
                    $show_caption = $settings["caption_source"];
                    if(!empty($show_caption) && $show_caption == "attachment") {

                        $photo_credit = get_post_meta( $thumbnail_id, '_image_photo_credit', true );
                        $photo_credit_link = get_post_meta( $thumbnail_id, '_image_photo_credit_link', true );
                        $author_link = (isset($photo_credit_link) && !empty($photo_credit_link)) ? '<a href="'.$photo_credit_link.'" title="'.$photo_credit.'">'.$photo_credit.'</a>': '';
                        
                        $photo_credit = (isset($photo_credit) && !empty($photo_credit) && isset($author_link) && !empty($author_link)) ? str_replace($photo_credit,$author_link,$photo_credit) : '';

                        if(isset($photo_credit) && !empty($photo_credit) && is_singular() && !empty($settings_info['credit_text']) && is_array($settings_info['post_show']) && in_array( $settings_info['cur_post_type'], $settings_info['post_show'] ) ){
                            $widget_content = str_replace(['<figcaption','</figcaption>'], ['<div class="gcl-fig"><figcaption','</figcaption><figcaption>'.$settings_info['credit_text'].' '.$photo_credit.'</figcaption></div>'], $widget_content);
                        }
                    }
                }
            }        
            return $widget_content;        
        }

        
        // create plugin settings menu
        public function ecibfi_plugin_settings_menu_page() {

            //create new top-level menu
            add_menu_page('Extra Caption Settings', 'Extra Caption Settings', 'manage_options', 'ecibfi_extra_caption_settings', [$this, 'ecibfi_extra_caption_plugin_settings_page'] );

            //call register settings function
            add_action( 'admin_init', [$this, 'ecibfi_register_plugin_caption_settings']);
        }

        public function ecibfi_register_plugin_caption_settings() {
            //register our settings
            register_setting( 'extra-caption-plugin-settings-group', 'post_type_options' );
            register_setting( 'extra-caption-plugin-settings-group', 'photo_credit_text' );
        }

        public function ecibfi_extra_caption_plugin_settings_page() {            
            $args = array(
                'public'   => true               
            );          
            $output = 'names';
            $operator = 'and';           
            $post_types = get_post_types( $args, $output, $operator );       
            $list_show = get_option('post_type_options'); 
        ?>
        <div class="wrap">
            <h1><?php echo esc_attr_e( 'Extra Caption Settings Page', 'ecibfi-extra-caption-image' ); ?></h1>
            <form method="post" action="options.php">
                <?php settings_fields( 'extra-caption-plugin-settings-group' ); ?>
                <?php do_settings_sections( 'extra-caption-plugin-settings-group' ); ?>
                <table class="form-table">                
                    <tr valign="top">
                        <th scope="row"><?php echo esc_attr_e('Show Photography Credit On Images For', 'ecibfi-extra-caption-image'); ?></th>
                        <td> 
                            <p><?php echo esc_attr_e('Select when to show the caption and author credit features.', 'ecibfi-extra-caption-image'); ?></p>
                            <ul>
                                <?php if( isset($list_show) && is_array($list_show) && in_array( 'featured_image', $list_show) ) { $chk = 'checked';  }  else {  $chk = ''; }  ?>
                                <li><input type="checkbox" name="post_type_options[]" value="<?php echo esc_attr( 'featured_image' ); ?>" <?php echo esc_attr( $chk ); ?> /> <span><?php echo esc_attr( 'Featured Images' ); ?></span> </li>
                                <?php if($post_types) : foreach($post_types as $post_type) : 
                                        $exclude = array( 'attachment', 'elementor_library', 'e-landing-page', 'product', 'wpcf7r_action' );
                                        if( TRUE === in_array( $post_type, $exclude ) ) 
                                            continue;
                                        if( isset($list_show) && is_array($list_show) && in_array( $post_type, $list_show) ) { $chk = 'checked';  }  else {  $chk = ''; } 
                                ?>                                
                                    <li>
                                        <input type="checkbox" name="post_type_options[]" value="<?php echo esc_attr( $post_type ); ?>" <?php echo esc_attr( $chk ); ?> /> <span><?php echo esc_attr( ucfirst ($post_type) ); ?></span>
                                    </li>
                                <?php endforeach; endif; ?>                            
                            </ul>
                        </td>
                    </tr>                
                    <tr valign="top">
                        <th scope="row"><?php echo esc_attr_e('Text Display For Photography Credit', 'ecibfi-extra-caption-image'); ?></th>
                        <td>
                            <p><?php echo esc_attr_e('Use this field to change the text before the Author\'s Name.', 'ecibfi-extra-caption-image'); ?></p>
                            <input type="text" name="photo_credit_text" placeholder="<?php echo esc_attr('Photo Credit To'); ?>" value="<?php echo esc_attr( !empty(get_option('photo_credit_text')) ? get_option('photo_credit_text') : 'Photo Credit To' ); ?>" />
                        </td>
                    </tr>                               
                </table>
                <?php submit_button(); ?>
            </form>
            <h2><?php echo esc_attr_e( 'Preview', 'ecibfi-extra-caption-image' ); ?></h2>
            <table class="form-table">                                
                <tr>
                    <td><img src="<?php echo esc_url( ECIBFI_PLUGIN_URL.'/assets/images/image_block_preview.png' ); ?>" alt="Image Block Preview" /></td>
                </tr>
                <tr>
                    <td><img src="<?php echo esc_url( ECIBFI_PLUGIN_URL.'/assets/images/featured_preview.png' ); ?>" alt="Featured Image Preview" /></td>
                </tr>
            </table>
        <?php    
        }

        function ecibfi_get_media_setting_options() {

            $data_arr = []; 
            $data_arr['credit_text'] = get_option('photo_credit_text');        
            $data_arr['post_show'] = get_option('post_type_options'); 
            $data_arr['cur_post_type'] = get_post_type();

            return $data_arr;
        }

    }
    $cmf = new ECIBFI_GWS_Custom_Media_Fields( $ecibfi_attchments_options );
}