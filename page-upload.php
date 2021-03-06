<?php

if ( ! function_exists( 'wp_handle_upload' ) ) {
	require_once( ABSPATH . 'wp-admin/includes/file.php' );
}

$file = wp_handle_upload($_FILES['file'], array('test_form' => false));

if ( $file && ! isset( $file['error'] ) ) {
	// echo $file['url'];
	// get exercise
	$paper_id = $_GET['paper_id'];
	$section = get_post_meta($paper_id, 'section', true);
	$exercise_index = get_post_meta($paper_id, 'exercise_index', true) ?: 0;
	$exam_id = get_post_meta($paper_id, 'exam', true);
	$section_exercises = get_field($section, $exam_id);
	$exercise = $section_exercises[$exercise_index];

	// save record url to paper
	update_post_meta($paper_id, 'answer_' . $section . '_' . $exercise_index, array($file['url']));
	echo json_encode(get_post_meta($paper_id, 'answer_' . $section . '_' . $exercise_index, true));

} else {
	/**
	 * Error generated by _wp_handle_upload()
	 * @see _wp_handle_upload() in wp-admin/includes/file.php
	 */
	echo $file['error'];
}
