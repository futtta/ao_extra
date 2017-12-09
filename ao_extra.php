<?php
/*
Plugin Name: Autoptimize Extra
Plugin URI: http://optimizingmatters.com/autoptimize/
Description: extra perf. optimization options
Author: Optimizing Matters
Version: 0.3.0
Author URI: http://optimizingmatters.com/ 
*/

// get option-array
$ao_extra_options = get_option( 'ao_extra_settings' );

// initialize the extra's
if ( check_ao_version() ) {
    add_action('init','ao_extra_init');
}
function ao_extra_init() {
    global $ao_extra_options;
    
    /* disable emojis */
    if ($ao_extra_options['ao_extra_checkbox_field_1']) {
       ao_extra_disable_emojis();
    }
    
    /* remove version from query string */
    if ($ao_extra_options['ao_extra_checkbox_field_0']) {
        add_filter( 'script_loader_src', 'ao_extra_remove_qs', 15, 1 );
        add_filter( 'style_loader_src', 'ao_extra_remove_qs', 15, 1 );
    }

    /* async JS */
    if (!empty($ao_extra_options['ao_extra_text_field_3'])) {
        add_filter('autoptimize_filter_js_exclude','ao_extra_async_js',10,1);
    }

    /* optimize google fonts */
    if ( !empty( $ao_extra_options['ao_extra_radio_field_4'] ) && ( $ao_extra_options['ao_extra_radio_field_4'] != "1" ) ) {
        if ( $ao_extra_options['ao_extra_radio_field_4'] == "2" ) {
            add_filter('autoptimize_filter_css_removables','ao_extra_remove_gfonts',10,1);
        } else {
            add_filter('autoptimize_html_after_minify','ao_extra_gfonts',10,1);
            add_filter('ao_extra_filter_tobepreconn','ao_extra_preconnectgooglefonts',10,1);
        }
    }
    
    /* preconnect */
    if ( !empty($ao_extra_options['ao_extra_text_field_2']) || has_filter('ao_extra_filter_tobepreconn') ) {
        add_filter( 'wp_resource_hints', 'ao_extra_preconnect', 10, 2 );
    }
}

// disable emoji's functions
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

// remove query string function
function ao_extra_remove_qs( $src ) {
        if ( strpos($src, '?ver=') ) {
                $src = remove_query_arg( 'ver', $src );
        }
        return $src;
}

// async function
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

// preconnect function
function ao_extra_preconnect($hints, $relation_type) {
    global $ao_extra_options;

    // get setting and store in array
    $_to_be_preconnected = array_filter(array_map('trim',explode(",",$ao_extra_options['ao_extra_text_field_2'])));
    $_to_be_preconnected = apply_filters( 'ao_extra_filter_tobepreconn', $_to_be_preconnected );

    // walk array, extract domain and add to new array with crossorigin attribute
    foreach ($_to_be_preconnected as $_preconn_single) {
        $_preconn_parsed = parse_url($_preconn_single);
        
        if ( is_array($_preconn_parsed) && empty($_preconn_parsed['scheme']) ) {
            $_preconn_domain = "//".$_preconn_parsed['host'];
        } else if ( is_array($_preconn_parsed) ) {
            $_preconn_domain = $_preconn_parsed['scheme']."://".$_preconn_parsed['host'];
        }
        
        if ( !empty($_preconn_domain) ) {
            $_preconn_hint = array('href' => $_preconn_domain);
            // fonts don't get preconnected unless crossorigin flag is set, non-fonts don't get preconnected if origin flag is set
            // so hardcode fonts.gstatic.com to come with crossorigin and have filter to add other domains if needed
            $_preconn_crossorigin = apply_filters( 'ao_extra_filter_preconn_crossorigin', array('https://fonts.gstatic.com') );
            if ( in_array( $_preconn_domain, $_preconn_crossorigin ) ) {
                $_preconn_hint['crossorigin'] = 'anonymous';
            }
            $_new_hints[] = $_preconn_hint;
        }
    }

    // merge in wordpress' preconnect hints
	if ( 'preconnect' === $relation_type && !empty($_new_hints) ) {
        $hints = array_merge($hints, $_new_hints);	  
    }
    
    return $hints;
}

// google font functions
function ao_extra_remove_gfonts($in) { 
    // simply remove google fonts
    return $in.", fonts.googleapis.com"; 
}

function ao_extra_gfonts($in) {
    global $ao_extra_options;
    
	// extract fonts, partly based on wp rocket's extraction code
	$_without_comments = preg_replace( '/<!--(.*)-->/Uis', '', $in );
    preg_match_all( '#<link(?:\s+(?:(?!href\s*=\s*)[^>])+)?(?:\s+href\s*=\s*([\'"])((?:https?:)?\/\/fonts\.googleapis\.com\/css(?:(?!\1).)+)\1)(?:\s+[^>]*)?>#iU', $_without_comments, $matches );

	$i = 0;
	$fontsCollection = array();
	if ( ! $matches[2] ) {
		return $in;
	}
    
    // store them in $fonts array
	foreach ( $matches[2] as $font ) {
		if ( ! preg_match( '/rel=["\']dns-prefetch["\']/', $matches[0][ $i ] ) ) {
			// Get fonts name.
			// $font = str_replace( array( '%7C', '%7c' ) , '|', $font );
            $font = urldecode($font);
			$font = explode( 'family=', $font );
			$font = ( isset( $font[1] ) ) ? explode( '&', $font[1] ) : array();
			// Add font to $fonts[$i]
		    $fontsCollection[$i]["fonts"] = explode( '|', reset( $font ) );
		    // And add subset if any
			$subset = ( is_array( $font ) ) ? end( $font ) : '';
		    if ( false !== strpos( $subset, 'subset=' ) ) {
				$subset = explode( 'subset=', $subset );
				$fontsCollection[$i]["subsets"] = explode( ',', $subset[1] );
		    }
		    // And remove Google Fonts.
		    $in = str_replace( $matches[0][ $i ], '', $in );
		}
	    $i++;
	}

    if ( $ao_extra_options['ao_extra_radio_field_4'] == "3" ) {
        // as link
        $_fontsString="";
        foreach ($fontsCollection as $font) {
            $_fontsString .= trim( implode( '|' , $font["fonts"] ), '|' );
            if ( !empty( $font["subsets"] ) ) {
                $subsetString .= implode( ',', $font["subsets"] ); 
            }
        }
        
        if (!empty($subsetString)) {
            $_fontsString = $_fontsString."#038;subset=".$subsetString;
        }
        
        if ( ! empty( $_fontsString ) ) {
            $_fontsOut = '<link rel="stylesheet" id="ao_optimized_gfonts" href="https://fonts.googleapis.com/css?family=' . $_fontsString . '" />';
        }
    } else if ( $ao_extra_options['ao_extra_radio_field_4'] == "4" ) {
        // webfont.js impl.
        $_fontsArray = array();
        foreach ($fontsCollection as $_fonts) {
            if ( !empty( $_fonts["subsets"] ) ) {
                $_subset = implode(",",$_fonts["subsets"]);
                foreach ($_fonts["fonts"] as $key => $_one_font) {
                    $_one_font = $_one_font.":".$_subset;
                    $_fonts["fonts"][$key] = $_one_font;
                } 
            }
            $_fontsArray = array_merge($_fontsArray, $_fonts["fonts"]);
        }
        
        $_fontsOut = '<script data-cfasync="false" type="text/javascript">WebFontConfig={google:{families:[\'';
        foreach ($_fontsArray as $_font) {
            $_fontsOut .= $_font."','";
        }
        $_fontsOut = trim(trim($_fontsOut,"'"),",");
        $_fontsOut .= '] },classes:false, events:false, timeout:1500};(function() {var wf = document.createElement(\'script\');wf.src=\'https://ajax.googleapis.com/ajax/libs/webfont/1/webfont.js\';wf.type=\'text/javascript\';wf.async=\'true\';var s=document.getElementsByTagName(\'script\')[0];s.parentNode.insertBefore(wf, s);})();</script>';
    }
 
    // inject in HTML
    $out = substr_replace($in, $_fontsOut."<link", strpos($in, "<link"), strlen("<link"));
	return $out;
}

function ao_extra_preconnectgooglefonts($in) {
    global $ao_extra_options;
    // preconnect to fonts.gstatic.com speed up download of static font-files
    $in[] = "https://fonts.gstatic.com";
    if ( $ao_extra_options['ao_extra_radio_field_4'] == "4" ) {
        // and more preconnects for webfont.js
        $in[] = "https://ajax.googleapis.com/";
        $in[] = "https://fonts.googleapis.com";
    }
    return $in;
}

/* admin page */
if ( is_admin() && check_ao_version() ) {
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
		'ao_extra_radio_field_4', 
		__( 'Google Fonts', 'autoptimize' ), 
		'ao_extra_radio_field_4_render', 
		'ao_extra_settings', 
		'ao_extra_pluginPage_section'
    );
   	add_settings_field( 
		'ao_extra_text_field_2', 
		__( 'Preconnect to 3rd party domains <em>(advanced users)</em>', 'autoptimize' ), 
		'ao_extra_text_field_2_render', 
		'ao_extra_settings', 
		'ao_extra_pluginPage_section'
	);
    add_settings_field( 
		'ao_extra_text_field_3', 
		__( 'Async Javascript-files <em>(advanced users)</em>', 'autoptimize' ), 
		'ao_extra_text_field_3_render', 
		'ao_extra_settings', 
		'ao_extra_pluginPage_section'
    );
}

function add_aoextra_tab($in) {
	$in=array_merge($in,array('ao_extra' => 'Extra'));
	return $in;
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

function ao_extra_text_field_2_render() { 
	global $ao_extra_options;
	?>
    <label>
	<input type='text' style='width:80%' name='ao_extra_settings[ao_extra_text_field_2]' value='<?php echo $ao_extra_options['ao_extra_text_field_2']; ?>'><br />
	<?php
    _e('Add 3rd party domains you want the browser to <a href="https://www.keycdn.com/support/preconnect/#primary" target="_blank">preconnect</a> to, separated by comma\'s. Make sure to include the correct protocol (HTTP or HTTPS).','autoptimize');
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

function ao_extra_radio_field_4_render() { 
   	global $ao_extra_options;
    $_googlef = $ao_extra_options['ao_extra_radio_field_4'];
	?>
    <input type="radio" name="ao_extra_settings[ao_extra_radio_field_4]" value="1" <?php if (!in_array($_googlef,array(2,3,4))) {echo "checked"; }  ?>><?php _e('Leave as is','autoptimize')?><br/>
    <input type="radio" name="ao_extra_settings[ao_extra_radio_field_4]" value="2" <?php checked(2, $_googlef, true); ?>><?php _e('Remove Google Fonts','autoptimize')?><br/>
    <input type="radio" name="ao_extra_settings[ao_extra_radio_field_4]" value="3" <?php checked(3, $_googlef, true); ?>><?php _e('Combine and link in head','autoptimize')?><br/>
    <input type="radio" name="ao_extra_settings[ao_extra_radio_field_4]" value="4" <?php checked(4, $_googlef, true); ?>><?php _e('Combine and load fonts asynchronously with <a href="https://github.com/typekit/webfontloader#readme" target="_blank">webfont.js</a>','autoptimize')?><br/>
    <?php
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
        #ao_settings_form {background: white;border: 1px solid #ccc;padding: 1px 15px;margin: 15px 10px 10px 0;}
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

function check_ao_version() {
    $ao_version = get_option("autoptimize_version","");
    if ( $ao_version && version_compare($ao_version, "2.2.0", ">") ) {
        return false;
    } else {
        return true;
    }
}
