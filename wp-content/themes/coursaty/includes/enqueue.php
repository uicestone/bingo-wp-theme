<?php

add_action('wp', function() {

	wp_register_style('style', get_stylesheet_directory_uri() . '/style.css', array(), '1.0.19');
	wp_register_style('responsive', get_stylesheet_directory_uri() . '/assets/css/responsive.css', array(), '1.0.0');

	wp_register_script('jquery', get_stylesheet_directory_uri() . '/assets/js/vendor/jquery-1.11.2.min.js', array(), '1.11.2', true);
	wp_register_script('bootstrap', get_stylesheet_directory_uri() . '/assets/js/bsmodal.min.js', array('jquery'), '3.3.4', true);
	wp_register_script('easydropdown', get_stylesheet_directory_uri() . '/assets/js/jquery.easydropdown.min.js', array('jquery'), '2.1.4', true);
	wp_register_script('flexslider', get_stylesheet_directory_uri() . '/assets/js/jquery.flexslider-min.js', array('jquery'), '2.2.2', true);
	wp_register_script('isotope', get_stylesheet_directory_uri() . '/assets/js/jquery.isotope.min.js', array('jquery'), '1.5.26', true);
	wp_register_script('revolution', get_stylesheet_directory_uri() . '/assets/js/jquery.themepunch.revolution.min.js', array('jquery'), '4.6.4', true);
	wp_register_script('tools', get_stylesheet_directory_uri() . '/assets/js/jquery.themepunch.tools.min.js', array('jquery'), '1.0.0', true);
	wp_register_script('viewportchecker', get_stylesheet_directory_uri() . '/assets/js/jquery.viewportchecker.min.js', array('jquery'), '1.8.0', true);
	wp_register_script('waypoints', get_stylesheet_directory_uri() . '/assets/js/jquery.waypoints.min.js', array('jquery'), '3.0.0', true);
	wp_register_script('sticky', get_stylesheet_directory_uri() . '/assets/js/jquery.sticky.min.js', array('jquery'), '1.0.4', true);
	wp_register_script('jsdiff', get_stylesheet_directory_uri() . '/assets/js/jsdiff.js', array(), '3.3.0', true);
	wp_register_script('waveform', get_stylesheet_directory_uri() . '/assets/js/vendor/waveform/waveform-playlist.js', array(), '3.0.4', true);
	wp_register_script('waveform-record', get_stylesheet_directory_uri() . '/assets/js/vendor/waveform/record.js', array(), '3.0.4', true);
	wp_register_script('waveform-emitter', get_stylesheet_directory_uri() . '/assets/js/vendor/waveform/emitter.js', array(), '3.0.4', true);
	wp_register_script('scripts', get_stylesheet_directory_uri() . '/assets/js/scripts.js', array('jquery'), '1.0.2', true);

});

add_action('wp_enqueue_scripts', function(){
	wp_enqueue_style('style');
	wp_enqueue_style('responsive');

	wp_enqueue_script('jquery');
	wp_enqueue_script('bootstrap');
	wp_enqueue_script('easydropdown');
	wp_enqueue_script('flexslider');
	wp_enqueue_script('isotope');
	wp_enqueue_script('revolution');
	wp_enqueue_script('tools');
	wp_enqueue_script('viewportchecker');
	wp_enqueue_script('waypoints');
	wp_enqueue_script('sticky');
	wp_enqueue_script('jsdiff');
	wp_enqueue_script('scripts');
});