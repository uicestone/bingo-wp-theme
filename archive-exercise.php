<?php

$ordered_exercises = new WP_Query($query_string. (isset($_GET['tag']) && $_GET['tag'] === 'free-trial' ? '' : '&orderby=ID') ."&order=ASC&posts_per_page=1");

if ($ordered_exercises->have_posts()): $ordered_exercises->the_post();

header('Location: ' . get_the_permalink() . ($_GET['tag'] ? '?tag=' . $_GET['tag'] : ''));

endif;

?>

