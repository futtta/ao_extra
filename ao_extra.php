<?php
/*
Plugin Name: Autoptimize Extra
Plugin URI: http://optimizingmatters.com/autoptimize/
Description: extra perf. optimization options
Author: Optimizing Matters
Version: 0.1.0
Author URI: http://optimizingmatters.com/ 
*/

$ao_extra_options = get_option( 'ao_extra_settings' );

/* disable emojis */
if ($ao_extra_options['ao_extra_checkbox_field_1']) {
    add_action( 'init', 'ao_extra_disable_emojis' );
}

function ao_extra_disable_emojis() {
  // all actions related to emojis
  remove_action( 'admin_print_styles', 'print_emoji_styles' );
  remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
  remove_action( 'admin_print_scripts', 'print_emoji_detection_script' );
  remove_action( 'wp_print_styles', 'print_emoji_styles' );
  remove_filter( 'wp_mail', 'wp_staticize_emoji_for_email' );
  remove_filter( 'the_content_feed', 'wp_staticize_emoji' );
  remove_filter( 'comment_text_rss', 'wp_staticize_emoji' );

  // filter to remove TinyMCE emojis
  add_filter( 'tiny_mce_plugins', 'ao_extra_disable_emojis_tinymce' );
}

function ao_extra_disable_emojis_tinymce( $plugins ) {
  if ( is_array( $plugins ) ) {
    return array_diff( $plugins, array( 'wpemoji' ) );
  } else {
    return array();
  }
}

/* remove version from query string */
if ($ao_extra_options['ao_extra_checkbox_field_0']) {
    add_filter( 'script_loader_src', 'ao_extra_remove_qs', 15, 1 );
    add_filter( 'style_loader_src', 'ao_extra_remove_qs', 15, 1 );
}

function ao_extra_remove_qs( $src ) {
        if ( strpos($src, '?ver=') ) {
                $src = remove_query_arg( 'ver', $src );
        }
        return $src;
}

/* async JS */
if (!empty($ao_extra_options['ao_extra_text_field_3'])) {
    add_filter('autoptimize_filter_js_exclude','ao_extra_async_js',11,1);
}

function ao_extra_async_js($in) {
    global $ao_extra_options;
    
    // get exclusions
    $AO_JSexclArrayIn = array();
    if (!empty($in)) {
        $AO_JSexclArrayIn = array_fill_keys(array_filter(array_map('trim',explode(",",$in))),"");
    }
    
    // get asyncs
    $_fromSetting = $ao_extra_options['ao_extra_text_field_3'];
    $AO_asynced_JS = array_fill_keys(array_filter(array_map('trim',explode(",",$_fromSetting))),"");
    foreach ($AO_asynced_JS as $JSkey => $JSvalue) {
        $AO_asynced_JS[$JSkey] = "async";
    }
    
    // merge exclusions & asyncs in one array and return to AO API
    $AO_excl_w_async = array_merge( $AO_JSexclArrayIn, $AO_asynced_JS );
    return $AO_excl_w_async;
}

/* preconnect */
if ($ao_extra_options['ao_extra_checkbox_field_2']) {
    add_filter('autoptimize_html_after_minify','ao_extra_preconnect');
}
function ao_extra_preconnect($in) {
    // create array with preconnectable domains
    $_ao_preconnectable_domains = array('gravatar.com','wp.com','google-analytics.com','maxcdn.bootstrapcdn.com','fonts.googleapis.com','connect.facebook.net');
    if ( !empty( get_option('autoptimize_cdn_url','') ) ) {
        $_ao_preconnectable_domains[] = get_option('autoptimize_cdn_url');
    }
    $_ao_preconnectable_domains = apply_filters('ao_extra_filter_preconnectable', $_ao_preconnectable_domains);
    
    // extract links from source
    // future: use filter in AO to get all 3rd party links? but then we miss out on links in JS (e.g. google analytics & facebook connect)
    preg_match_all('#(?:href|src)\s?=\s?(?:\'|")([^"\']*?)#U',$in,$_matches);
    
    // build preconnect-string
    foreach ($_matches[1] as $_match) {
        if ( $_match !== str_replace($_ao_preconnectable_domains,'',$_match) ) {
            $_parsed_match = parse_url($_match);
            if ( empty($_parsed_match["scheme"]) ) {
                $_preconnect_domain = "//".$_parsed_match["host"];
            } else {
                $_preconnect_domain = $_parsed_match["scheme"]."://".$_parsed_match["host"];
            }
            $_preconnects[] = "<link rel=\"preconnect\" href=\"".$_preconnect_domain."\">";
        }
    }
    
    // you can overrule
    $_preconnects = apply_filters('ao_extra_filter_preconnects',$_preconnects);
    
    // inject preconnect links in HTML
    $_preconnect_string = implode(array_unique($_preconnects));
    $out = substr_replace($in, $_preconnect_string."<link", strpos($in, "<link"), strlen("<link"));
    return $out;
}

/* admin page */
if ( is_admin() ) {
    add_action( 'admin_menu', 'ao_extra_add_admin_menu' );
    add_action( 'admin_init', 'ao_extra_settings_init' );
    add_filter( 'autoptimize_filter_settingsscreen_tabs','add_aoextra_tab' );
}

function ao_extra_add_admin_menu(  ) { 
	add_submenu_page( null, 'ao_extra', 'ao_extra', 'manage_options', 'ao_extra', 'ao_extra_options_page' );
}

function ao_extra_settings_init(  ) { 
	register_setting( 'ao_extra_settings', 'ao_extra_settings' );
	add_settings_section(
		'ao_extra_pluginPage_section', 
		__( 'Extra Auto-Optimizations', 'autoptimize' ), 
		'ao_extra_settings_section_callback', 
		'ao_extra_settings'
	);
	add_settings_field( 
		'ao_extra_checkbox_field_1', 
		__( 'Remove emojis', 'autoptimize' ), 
		'ao_extra_checkbox_field_1_render', 
		'ao_extra_settings', 
		'ao_extra_pluginPage_section'
	);
	add_settings_field( 
		'ao_extra_checkbox_field_0', 
		__( 'Remove query strings from static resources', 'autoptimize' ), 
		'ao_extra_checkbox_field_0_render', 
		'ao_extra_settings', 
		'ao_extra_pluginPage_section'
	);
   	add_settings_field( 
		'ao_extra_checkbox_field_2', 
		__( 'Preconnect to 3rd party domains', 'autoptimize' ), 
		'ao_extra_checkbox_field_2_render', 
		'ao_extra_settings', 
		'ao_extra_pluginPage_section'
	);
    add_settings_field( 
		'ao_extra_text_field_3', 
		__( 'Async Javascript-files', 'autoptimize' ), 
		'ao_extra_text_field_3_render', 
		'ao_extra_settings', 
		'ao_extra_pluginPage_section'
    );
}

function ao_extra_checkbox_field_0_render() { 
	global $ao_extra_options;
	?>
    <label>
	<input type='checkbox' name='ao_extra_settings[ao_extra_checkbox_field_0]' <?php checked( $ao_extra_options['ao_extra_checkbox_field_0'], 1 ); ?> value='1'>
	<?php
    _e('Removing query strings (or more specificaly the <code>ver</code> parameter) will not improve load time, but might improve performance scores.','autoptimize');
    ?>
    </label>
    <?php
}

function ao_extra_checkbox_field_1_render() { 
	global $ao_extra_options;
	?>
    <label>
	<input type='checkbox' name='ao_extra_settings[ao_extra_checkbox_field_1]' <?php checked( $ao_extra_options['ao_extra_checkbox_field_1'], 1 ); ?> value='1'>
	<?php
    _e('Removes WordPress\' core emojis\' inline CSS, inline JavaScript, and an otherwise un-autoptimized JavaScript file.','autoptimize');
    ?>
    </label>
    <?php
}

function ao_extra_checkbox_field_2_render() { 
	global $ao_extra_options;
	?>
    <label>
	<input type='checkbox' name='ao_extra_settings[ao_extra_checkbox_field_2]' <?php checked( $ao_extra_options['ao_extra_checkbox_field_2'], 1 ); ?> value='1'>
	<?php
    _e('Will try to intelligently add preconnect links for well-known 3rd party domains (somewhat experimental).','autoptimize');
    ?>
    </label>
    <?php
}

function ao_extra_text_field_3_render() { 
   	global $ao_extra_options;
	?>
	<input type='text' style='width:80%' name='ao_extra_settings[ao_extra_text_field_3]' value='<?php echo $ao_extra_options['ao_extra_text_field_3']; ?>'><br />
	<?php
    _e('Comma-separated list of local or 3rd party JS-files that should loaded with the <code>async</code> flag. JS-files from your own site will be automatically excluded if added here.','autoptimize');
}

function ao_extra_settings_section_callback() {
    ?>
    <span id='ao_extra_descr'>
    <?php
	_e( 'The following settings can improve your site\'s performance even more.', 'autoptimize' );
    ?>
    </span>
    <?php
}


function ao_extra_options_page() { 
	?>
    <style>
        #ao_settings_form {background: white;border: 1px solid #ccc;padding: 1px 15px;margin: 15px 10px 10px 0;width:80%;}
        #ao_settings_form .form-table th {font-weight: 100;}
        #ao_extra_descr{font-size: 120%;}
    </style>
    <div class="wrap">
	<h1><?php _e('Autoptimize Settings','autoptimize'); ?></h1>
    <?php echo autoptimizeConfig::ao_admin_tabs(); ?>
	<form id='ao_settings_form' action='options.php' method='post'>
		<?php
		settings_fields( 'ao_extra_settings' );
		do_settings_sections( 'ao_extra_settings' );
		submit_button();
		?>
	</form>
	<?php
}

function add_aoextra_tab($in) {
	$in=array_merge($in,array('ao_extra' => 'Extra'));
	return $in;
}
