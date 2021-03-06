<?php

// fix when required from single-exam.php
if (!get_the_content()) {
	the_post();
	$post = get_post();
}

// cap guard only on exercise page
if (preg_match('/\/exercise\//',$_SERVER['REQUEST_URI'])) {
	ensure_user_cap_on($post);
}

get_header();

$user = wp_get_current_user();

$question_types = wp_get_object_terms(get_the_ID(), 'question_type', array('orderby' => 'id')); $question_type = $question_types[0]; $question_sub_type = $question_types[1];
$question_type_desc = get_posts(array('post_type' => 'question_type_desc', 'posts_per_page' => 1, 'tax_query' => array(
	array(
		'taxonomy' => 'question_type',
		'field' => 'slug',
		'terms' => $question_type->slug
	)
)))[0];
$question_type_main_language = get_term(pll_get_term($question_type->term_id, 'zh') ?: $question_type->term_id);
$marked_exercises = get_user_meta($user->ID, 'marked_exercises') ?: array();
$current_exercise_marked = in_array(get_the_ID(), $marked_exercises);

if (isset($_POST['save_audio_time_point'])) {
    $audio_time_points = implode(',', $_POST['audio_time_point']);
    update_post_meta(get_the_ID(), 'audio_time_points', $audio_time_points);
    header('Location: ' . $_SERVER['HTTP_REFERER']); exit;
}

if ($_POST['comment']) {

	redirect_login();

    $comment_author = $user;

	wp_handle_comment_submission(array(
		'comment_post_ID' => get_the_ID(),
		'author' => $comment_author->display_name,
		'email' => $comment_author->user_email,
        'comment' => $_POST['comment']
    ));

	header('Location: ' . $_SERVER['REQUEST_URI'], true, 303);
	exit;
}

if (isset($_POST['marked'])) {

    $new_marked_exercises = $marked_exercises;

    if ($_POST['marked'] && !$current_exercise_marked) {
        array_push($new_marked_exercises, get_the_ID());
    } elseif (!$_POST['marked'] && $current_exercise_marked) {
        unset($new_marked_exercises[array_search(get_the_ID(), $new_marked_exercises)]);
    }

    sync_user_meta($user->ID, 'marked_exercises', $new_marked_exercises, $marked_exercises);

	header('Location: ' . $_SERVER['REQUEST_URI'], true, 303);
	exit;
}

if ($_GET['random']) {

	$random_query = array(
		'post_type' => 'exercise',
		'posts_per_page' => 1,
		'orderby' => 'rand',
		'post__not_in' => array(get_the_ID())
	);

	if ($_GET['tag']) {
		$random_query['tag'] = $_GET['tag'];
	} else {
		$random_query['tax_query'] = array(
			array(
				'taxonomy' => 'question_type',
				'field' => 'slug',
				'terms' => $question_type->slug
			)
		);
	}

	if ($_GET['marked'] === 'no') {
		$random_query['meta_query'] = [
			[
				'key' => 'marked',
				'compare' => 'NOT EXISTS'
			]
		];
	}

	$random_exercises = get_posts($random_query);

	if ($random_exercises) {
		$random_exercise = $random_exercises[0];
	}
}
elseif (empty($_GET['section'])) {
	$limited_free_term = get_term_by('slug', 'limited-free', 'post_tag');
	$previous_exercise = get_previous_post(true, $limited_free_term->term_id, $_GET['tag'] ? 'post_tag' : 'question_type');
	$next_exercise = get_next_post(true, $limited_free_term->term_id, $_GET['tag'] ? 'post_tag' : 'question_type');
}

get_header(); ?>

<?php get_template_part('content-top-copyright'); ?>

<article class="post single exercise">
	<div class="container">
		<div class="row">
			<div class="col-md-8 main-content">
				<div class="row">
					<div class="col-md-12">
						<div class="entry clearfix">
							<h3 class="single-title fl">
								<span class="post-type-icon"><i class="fa fa-pencil"></i></span>
								<a href="#" class="ln-tr"><?php the_title(); ?></a>
                                <?php if ($rating = (int)get_post_meta(get_the_ID(), 'rating', true)): ?>
                                <div class="rating fr" data-rating="<?=$rating?>">
                                    <?php $star_full = 5; for ($star_index = 0; $star_index < $star_full; $star_index++): ?>
                                    <span class="star<?=$rating === ($star_full - $star_index) ? ' rated' : ''?>"></span>
                                    <?php endfor; ?>
                                </div>
                                <?php endif; ?>
							</h3><!-- End Title -->
							<div class="clearfix"></div>
							<div class="question watermark content<?=$question_type_main_language->slug === 'highlight-incorrect-words' ? ' highlightable' : ''?>">
								<?php if (isset($exam) && $section === 'break') { echo wpautop(get_post_meta(get_the_ID(), 'break_content', true)); } else { the_content(); } ?>
							</div>
						</div><!-- End Entry -->
                        <?php if (in_array($question_type_main_language->slug, array('summarise-spoken-text', 'write-from-dictation', 'intensive-listening', 'ccl-intensive-listening', 'write-essay', 'ca-exam-question', 'swt'))): ?>
						<div class="comment-form answer-form entry">
							<div class="addcomment-title">
								<span class="icon"><i class="fa fa-comments-o"></i></span>
								<span class="text"><?=__('你的回答', 'bingo')?></span>
                                <?php if (in_array($question_type_main_language->slug, array('summarise-spoken-text', 'write-essay', 'ca-exam-question', 'swt'))): ?>
                                <span class="pull-right word-count"><?=__('词数：', 'bingo')?><span class="count">0</span></span>
                                <?php endif; ?>
								<?php if (in_array($question_type_main_language->slug, array('write-from-dictation', 'intensive-listening', 'ccl-intensive-listening'))): ?>
                                    <span class="pull-right word-diff-count"><?=__('正确率：', 'bingo')?><span class="diff-percentage">-</span></span>
								<?php endif; ?>
							</div><!-- End Title -->
							<form method="post" action="/" id="answer-form">
								<div class="row">
									<div class="col-md-12">
										<div class="input">
											<?php if (isset($exam) && $answer): ?>
											<textarea name="answer-area" id="answer-area" placeholder="<?=__('内容', 'bingo')?>" spellcheck="false" disabled><?=$answer[0]?></textarea>
											<?php else: ?>
											<textarea name="answer-area" id="answer-area" placeholder="<?=__('内容', 'bingo')?>" spellcheck="false" class="answer-input"></textarea>
											<?php endif; ?>
                                            <div class="diff-check-result content clearfix" style="white-space:pre-line;display:none"></div>
											<?php if (in_array($question_type_main_language->slug, array('write-from-dictation', 'intensive-listening', 'ccl-intensive-listening'))): ?>
                                            <input type="submit" id="comment-submit" class="diff-check submit-input grad-btn ln-tr" value="<?=__('检查', 'bingo')?>" disabled="disabled">
                                            <input type="submit" id="comment-submit" class="resume-input submit-input grad-btn ln-tr" value="<?=__('返回', 'bingo')?>" style="display:none">
											<?php endif; ?>
										</div>
									</div>
								</div>
							</form><!-- End form -->
						</div><!-- End comment form -->
                        <?php endif; ?>
						<?php if (in_array($question_type_main_language->slug, array('read-aloud', 'repeat-sentence', 'ccl-vocabulary-primary', 'ccl-vocabulary-junior', 'ccl-vocabulary-senior', 'answer-short-question', 'describe-image', 'retell-lecture', 'dialogue-interpreting'))): ?>
						<div class="clearfix" style="margin-top:30px"></div>
						<div class="comment-form answer-form entry">
							<div class="addcomment-title">
								<span class="icon"><i class="fa fa-comments-o"></i></span>
								<span class="text"><?=__('你的回答', 'bingo')?></span>
							</div><!-- End Title -->
							<?php if (isset($exam) && $answer): ?>
							<audio controls src="<?=$answer[0]?>" style="width:100%;"></audio>
							<?php 	if (current_user_can('edit_posts')): ?>
							<a href="<?=$answer[0]?>" target="_blank"><?=__('下载', 'bingo')?></a>
							<?php 	endif; ?>
							<?php else: ?>
							<form method="post" action="/" id="answer-form">
								<div class="row">
									<div class="col-md-12">
										<div class="input">
											<div id="answer-voice-record" class="post-content">
												<div id="top-bar" class="playlist-top-bar">
													<div class="playlist-toolbar">
														<div class="btn-group">
															<span class="btn-record btn btn-info disabled <?=(empty($exam) || isset($manual_record)) ? '' : ' hidden' ?>">
																<i class="fa fa-microphone"></i>
															</span>
															<span class="btn-play btn btn-success<?=(empty($exam) || isset($manual_record)) ? '' : ' hidden' ?>">
																<i class="fa fa-play"></i>
															</span>
															<span class="btn-stop btn<?=(empty($exam) || isset($manual_record)) ? '' : ' hidden' ?> disabled">
																<i class="fa fa-stop"></i>
															</span>
															<span class="btn-clear btn btn-danger<?=(empty($exam) || isset($manual_record)) ? '' : ' hidden' ?>">
																<i class="fa fa-trash"></i>
															</span>
															<span title="Download the current work as Wav file"
																  class="btn btn-download btn-primary hidden">
																<i class="fa fa-download"></i>
															</span>
															<span class="record-time" style="display:none"> <i class="fa fa-clock-o"></i> <span class="time">00:00</span></span>
														</div>
													</div>
												</div>
												<div id="playlist"></div>
											</div>
										</div>
									</div>
								</div>
							</form><!-- End form -->
							<?php endif; ?>
						</div><!-- End comment form -->
						<?php endif; ?>
						<?php if (in_array($question_type_main_language->slug, array('multiple-choice-reading', 'multiple-choice-listening', 'tax-quiz', 'auditing-quiz', 'finance-quiz', 'cap-quiz', 'management-quiz',  'select-missing-word', 'highlight-correct-summary'))): ?>
							<div class="clearfix" style="margin-top:30px"></div>
							<div class="comment-form answer-form entry">
								<div class="addcomment-title">
									<span class="icon"><i class="fa fa-comments-o"></i></span>
									<span class="text"><?=__('你的选择', 'bingo')?></span>
								</div><!-- End Title -->
								<form method="post" action="/" id="answer-form">
									<div class="input content">
										<?php foreach(explode("\n", get_field('choices')) as $index => $choice): $choice = trim($choice); if (!$choice) continue; ?>
											<p>
												<label style="cursor:pointer">
													<?php if (isset($exam) && $answer_choices = $answer
													) { $answer_choices = array_map('trim', $answer_choices); } ?>
													<input name="answer" value="<?=$choice?>"
														type="<?=get_field('multiple')?'checkbox':'radio'?>"
														<?=(isset($answer_choices) && in_array($choice, $answer_choices ?: [])) ? ' checked' : ''?>
														<?=$answer_choices ? ' disabled' : ''?>
														class="answer-input" style="font-size:16px;vertical-align:text-bottom">
													<?=$index+1?>. <?=$choice?>
												</label>
											</p>
										<?php endforeach; ?>
									</div>
								</form><!-- End form -->
							</div><!-- End comment form -->
						<?php endif; ?>
						<?php if (empty($exam) || $review): ?>
                        <div class="comment-form comments-list entry answer">
                            <div class="addcomment-title" style="margin-bottom:20px">
                                <span class="icon"><i class="fa fa-comments-o"></i></span>
                                <span class="text"><?=__('参考答案', 'bingo')?></span>
                                <a href="#" class="toggle grad-btn ln-tr pull-right<?=in_array($question_type_main_language->slug, array('intensive-listening', 'ccl-intensive-listening')) ? ' disabled disable-on-high-diff' : ''?>"><?=__('显示', 'bingo')?></a>
                            </div><!-- End Title -->
                            <div class="row" style="margin-top:20px">
                                <div class="col-md-12">
                                    <div class="input watermark content<?=$question_type_main_language->slug === 'highlight-incorrect-words' ? ' highlightable' : ''?>" style="display:none">
                                        <?=wpautop(do_shortcode(get_post_meta(get_the_ID(), 'answer', true)))?>
                                    </div>
                                </div>
                            </div>
                        </div><!-- End comment form -->
						<?php endif; ?>
                        <?php comments_template('/comments-exercise.php', true); ?>
                        <?php if (comments_open()): ?>
                        <div class="comment-form">
                            <div class="addcomment-title">
                                <span class="icon"><i class="fa fa-comments-o"></i></span>
                                <span class="text"><?=__('留下你的提问', 'bingo')?></span>
                            </div><!-- End Title -->
                            <form method="post" id="comment-form">
                                <div class="row">
                                    <div class="col-md-12">
                                        <div class="input">
                                            <textarea name="comment" id="comment-area"></textarea>
                                            <input type="submit" id="comment-submit" class="submit-input grad-btn ln-tr" value="<?=__('提交审核', 'bingo')?>">
                                        </div>
                                    </div>
                                </div>
                            </form><!-- End form -->
                        </div>
                        <?php endif; ?>
					</div><!-- End col-md-12 -->
				</div><!-- End main content row -->
			</div><!-- End Main Content - LEFT -->
			<div class="col-md-4">
                <div class="sidebar">
                    <?php if (isset($random_exercise)): ?>
					<div class="row">
						<div class="col-md-4" style="padding-right:5px">
							<a href="<?=get_the_permalink($random_exercise) . '?random=yes' . ($_GET['tag'] ? '&tag=' . $_GET['tag'] : '')?>" class="btn primary-btn"><i class="fa fa-random"></i> <?=__('换一题', 'bingo')?></a>
						</div>
						<div class="col-md-4" style="padding-left:5px;padding-right:5px">
							<a href="<?=get_the_permalink($random_exercise) . '?random=yes&marked=no' . ($_GET['tag'] ? '&tag=' . $_GET['tag'] : '')?>" class="btn primary-btn"><i class="fa fa-random"></i> <?=__('没做过的', 'bingo')?></a>
						</div>
						<div class="col-md-4" style="padding-left:5px">
							<form method="post">
								<?php if ($current_exercise_marked): ?>
								<button type="submit" name="marked" value="0" class="btn primary-btn" style="border:none;cursor:pointer"><i class="fa fa-check-square-o"></i> <?=__('已学', 'bingo')?></button>
								<?php else: ?>
								<button type="submit" name="marked" value="1" class="btn primary-btn" style="border:none;cursor:pointer"><i class="fa fa-square-o"></i> <?=__('已学', 'bingo')?></button>
								<?php endif; ?>
							</form>
						</div>
					</div>
					<?php elseif (isset($exam)): ?>
					<div class="row">
						<?php if (!$review): ?>
						<div class="col-md-6" style="padding-left:15px;padding-right:5px">
							<form method="post">
								<button type="submit" disabled class="btn primary-btn" style="border:none;cursor:progress">
									<i class="fa fa-clock-o"></i>
									<span class="section-timer"><?=$section_time_left > 0 ? date('i:s', $section_time_left) : __('已超时', 'bingo')?></span>
									<span style="font-size:10px"><?=ucfirst($section)?></span>
								</button>
							</form>
						</div>
						<div class="col-md-6" style="padding-right:15px;padding-left:5px;<?=$section === 'break' || $section_time_left < 0 || $answer ? 'display:none;':''?>">
							<button type="submit" class="btn primary-btn submit-answer">
								<i class="fa fa-hand-paper-o"></i>
								<span><?=__('提交本题', 'bingo')?></span>
							</button>
						</div>
						<div class="col-md-6 next" style="padding-right:15px;padding-left:5px;<?=$section === 'break' || $section_time_left < 0 || $answer ? '': 'display:none;'?>">
							<form method="post">
								<button type="submit" name="submit" class="btn primary-btn">
									<?php if (!$is_last_exercise_of_section && $section_time_left >= 0): ?>
									<span><?=__('下一题', 'bingo')?> &raquo;</span>
									<?php elseif(!$is_last_section_of_exam): ?>
									<span><?=__('下一部分', 'bingo')?> &raquo;</span>
									<?php else: ?>
									<span><?=__('结束考试', 'bingo')?> &raquo;</span>
									<?php endif; ?>
								</button>
							</form>
						</div>
						<?php else: ?>
						<div class="col-md-6" style="padding-left:15px;padding-right:5px">
							<?php if (isset($previous_exercise_url)): ?>
							<a class="btn primary-btn next-exercise pull-right" href="<?=$previous_exercise_url?>" title="<?=get_the_title($next_exercise)?>">&laquo; <?=__('上一题', 'bingo')?></a>
							<?php elseif (isset($previous_section_url)): ?>
							<a class="btn primary-btn next-section pull-right" href="<?=$previous_section_url?>">&laquo; <?=__('上一部分', 'bingo')?></a>
							<?php endif; ?>
						</div>
						<div class="col-md-6 next" style="padding-right:15px;padding-left:5px">
							<?php if (isset($next_exercise_url)): ?>
							<a class="btn primary-btn next-exercise pull-right" href="<?=$next_exercise_url?>"><?=__('下一题', 'bingo')?> &raquo;</a>
							<?php elseif (isset($next_section_url)): ?>
							<a class="btn primary-btn next-section pull-right" href="<?=$next_section_url?>"><?=__('下一部分', 'bingo')?> &raquo;</a>
							<?php elseif (isset($return_url)): ?>
							<a class="btn primary-btn next-section pull-right" href="<?=$return_url?>"><?=__('返回', 'bingo')?> &raquo;</a>
							<?php endif; ?>
						</div>
						<?php endif; ?>
					</div>
                    <?php else: ?>
                    <div class="row">
                        <div class="col-md-4" style="padding-right:5px">
							<?php if ($previous_exercise): ?><a class="btn primary-btn" href="<?=get_the_permalink($previous_exercise) . ($_GET['tag'] ? '?tag=' . $_GET['tag'] : '')?>" title="<?=get_the_title($previous_exercise)?>">&laquo; <?=__('上一题', 'bingo')?></a><?php endif; ?>
                        </div>
                        <div class="col-md-4" style="padding-left:5px;padding-right:5px">
							<?php if ($next_exercise): ?><a class="btn primary-btn pull-right" href="<?=get_the_permalink($next_exercise) .  ($_GET['tag'] ? '?tag=' . $_GET['tag'] : '')?>" title="<?=get_the_title($next_exercise)?>"><?=__('下一题', 'bingo')?> &raquo;</a><?php endif; ?>
                        </div>
                        <div class="col-md-4" style="padding-left:5px">
                            <form method="post">
								<?php if ($current_exercise_marked): ?>
                                <button type="submit" name="marked" value="0" class="btn primary-btn" style="border:none;cursor:pointer"><i class="fa fa-check-square-o"></i> <?=__('已学', 'bingo')?></button>
                                <?php else: ?>
                                <button type="submit" name="marked" value="1" class="btn primary-btn" style="border:none;cursor:pointer"><i class="fa fa-square-o"></i> <?=__('已学', 'bingo')?></button>
                                <?php endif; ?>
                            </form>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-12">
                            <select class="go-to-exercise" style="width:100%;height:40px;font-size:16px;margin-bottom:10px;background:none">
                                <?php $all_query = array(
									'post_type' => 'exercise',
									'posts_per_page' => -1,
									'order' => 'asc',
									'orderby' => 'ID'
								);

								if ($_GET['tag']) {
									$all_query['tag'] = $_GET['tag'];
									$all_query['orderby'] = 'post_date';
								}
								else {
									$all_query['tax_query'] = array(
										array(
											'taxonomy' => 'question_type',
											'field' => 'slug',
											'terms' => $question_sub_type ? $question_sub_type->slug : $question_type->slug
										)
									);
								}

                                foreach (get_posts($all_query) as $exercise): $rating = (int)get_post_meta($exercise->ID, 'rating', true); ?>
                                <option value="<?=get_the_permalink($exercise) . ($_GET['tag'] ? '?tag=' . $_GET['tag'] : '')?>"<?=$exercise->ID === get_the_ID() ? ' selected' : ''?>>
                                    <?=get_the_title($exercise)?>
                                    <?=str_repeat('★', $rating)?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <?php endif; ?>
                    <div class="sidebar-widget">
                        <span class="widget-icon"><i class="fa fa-clock-o"></i></span>
                        <h5 class="sidebar-widget-title ib"><?=__('计时器', 'bingo')?></h5>
                        <div class="home-skills">
							<?php if(isset($exam) && $time_prepare_exam = get_field('time_prepare_exam', 'question_type_' . $question_type->term_id)): ?>
								<div class="skillbar timer clearfix" data-duration="<?=$time_prepare_exam?>">
									<div class="skillbar-title">
										<span><?=__('准备', 'bingo')?> <span class="seconds-left"><?=date('i:s', $time_prepare_exam)?></span></span>
									</div>
									<div class="skillbar-bar"></div>
									<div class="controls">
										<i class="skip fa fa-step-forward"></i>
									</div>
								</div>
							<?php endif; ?>
                            <div class="skillbar audio-progress clearfix" style="display:none">
                                <div class="skillbar-title">
                                    <span><?=__('音频', 'bingo')?></span>
                                </div>
                                <div class="skillbar-bar"></div>
                                <div class="controls">
									<?php if (in_array($question_type_main_language->slug, array('intensive-listening', 'ccl-intensive-listening', 'tax', 'fin'))): ?>
                                    <i id="rewind-control" class="fa fa-fast-backward"></i>
                                    <i id="fast-forward-control" class="fa fa-fast-forward"></i>
									<?php endif; ?>
									<?php if (empty($exam) || $review): ?>
                                    <i id="play-control" class="fa fa-play" style="display:none;"></i>
                                    <i id="pause-control" class="fa fa-pause" style="display:none"></i>
                                    <i id="replay-control" class="fa fa-refresh"></i>
									<?php endif; ?>
                                </div>
                                <div class="audio-navigation">
                                    <form method="post">
                                        <?php if (current_user_can('edit_paper')): ?>
                                        <a class="btn" id="mark-audio-time-point"><?=__('断句', 'bingo')?></a> <button type="submit" class="btn" id="save-audio-time-point" name="save_audio_time_point"><?=__('保存', 'bingo')?></button>
                                        <?php endif; ?>
										<strong><?=__('断点跳转：', 'bingo')?></strong>
                                        <?php $audio_time_points = get_post_meta(get_the_ID(), 'audio_time_points', true); if ($audio_time_points): $audio_time_points = explode(',', $audio_time_points); foreach ($audio_time_points as $index => $audio_time_point): ?>
                                        <a class="btn jump-to-time-point saved" data-time-point="<?=$audio_time_point?>"><?=$index + 1?></a>
                                        <?php endforeach; endif; ?>
                                    </form>
                                </div>
                            </div>
							<?php if(isset($exam) && $_GET['section'] === 'break'): ?>
								<div class="skillbar timer clearfix" data-duration="600">
									<div class="skillbar-title">
										<span><?=__('休息', 'bingo')?> <span class="seconds-left">10:00</span></span>
									</div>
									<div class="skillbar-bar"></div>
									<div class="controls">
										<i class="skip fa fa-step-forward"></i>
									</div>
								</div>
							<?php endif; ?>
                            <?php if(in_array($question_type_main_language->slug, array('read-aloud'))): ?>
                            <div class="skillbar timer clearfix" data-duration="40">
                                <div class="skillbar-title">
                                    <span><?=__('准备', 'bingo')?> <span class="seconds-left">00:40</span></span>
                                </div>
                                <div class="skillbar-bar"></div>
                                <div class="controls">
									<?php if (empty($exam) || $review || current_user_can('edit_posts')): ?>
                                    <i class="skip fa fa-step-forward"></i>
									<?php endif; ?>
                                </div>
                            </div>
                            <?php endif; ?>
							<?php if(in_array($question_type_main_language->slug, array('retell-lecture'))): ?>
                            <div class="skillbar timer clearfix" data-duration="10" data-wait="previous">
                                <div class="skillbar-title">
                                    <span><?=__('准备', 'bingo')?> <span class="seconds-left">00:10</span></span>
                                </div>
                                <div class="skillbar-bar"></div>
                                <div class="controls">
									<?php if (empty($exam) || $review || current_user_can('edit_posts')): ?>
										<i class="skip fa fa-step-forward"></i>
									<?php endif; ?>
                                </div>
                            </div>
							<?php endif; ?>
							<?php if(in_array($question_type_main_language->slug, array('describe-image'))): ?>
                            <div class="skillbar timer clearfix" data-duration="25">
                                <div class="skillbar-title">
                                    <span><?=__('看图', 'bingo')?> <span class="seconds-left">00:25</span></span>
                                </div>
                                <div class="skillbar-bar"></div>
                                <div class="controls">
									<?php if (empty($exam) || $review || current_user_can('edit_posts')): ?>
										<i class="skip fa fa-step-forward"></i>
									<?php endif; ?>
                                </div>
                            </div>
							<?php endif; ?>
							<?php if(in_array($question_type_main_language->slug, array('repeat-sentence'))): ?>
                                <div class="skillbar timer clearfix" data-wait="previous" data-duration="15" data-is-answer="true">
                                    <div class="skillbar-title">
                                        <span><?=__('复述', 'bingo')?> <span class="seconds-left">00:15</span></span>
                                    </div>
                                    <div class="skillbar-bar"></div>
                                </div>
							<?php endif; ?>
							<?php if(in_array($question_type_main_language->slug, array('ccl-vocabulary-primary', 'ccl-vocabulary-junior', 'ccl-vocabulary-senior'))): ?>
								<div class="skillbar timer clearfix" data-wait="previous" data-duration="3" data-is-answer="true">
									<div class="skillbar-title">
										<span><?=__('翻译', 'bingo')?> <span class="seconds-left">00:03</span></span>
									</div>
									<div class="skillbar-bar"></div>
								</div>
							<?php endif; ?>
							<?php if(in_array($question_type_main_language->slug, array('dialogue-interpreting'))): ?>
								<div class="skillbar timer clearfix" data-wait="previous" data-duration="60" data-is-answer="true">
									<div class="skillbar-title">
										<span><?=__('翻译', 'bingo')?> <span class="seconds-left">00:40</span></span>
									</div>
									<div class="skillbar-bar"></div>
								</div>
							<?php endif; ?>
							<?php if(in_array($question_type_main_language->slug, array('answer-short-question'))): ?>
								<div class="skillbar timer clearfix" data-wait="previous" data-duration="10" data-is-answer="true">
									<div class="skillbar-title">
										<span><?=__('回答', 'bingo')?> <span class="seconds-left">00:10</span></span>
									</div>
									<div class="skillbar-bar"></div>
								</div>
							<?php endif; ?>
							<?php if(in_array($question_type_main_language->slug, array('read-aloud', 'describe-image'))): ?>
                            <div class="skillbar timer clearfix" data-wait="previous" data-duration="40" data-is-answer="true">
                                <div class="skillbar-title">
                                    <span><?=__('说话', 'bingo')?></span>
                                </div>
                                <div class="skillbar-bar"></div>
                            </div>
							<?php endif; ?>
							<?php if(in_array($question_type_main_language->slug, array('retell-lecture'))): ?>
                            <div class="skillbar timer clearfix" data-wait="previous" data-duration="40" data-is-answer="true">
                                <div class="skillbar-title">
                                    <span><?=__('描述', 'bingo')?> <span class="seconds-left">00:40</span></span>
                                </div>
                                <div class="skillbar-bar"></div>
                            </div>
							<?php endif; ?>
							<?php if(in_array($question_type_main_language->slug, array('summarise-spoken-text', 'swt'))): ?>
                            <div class="skillbar timer clearfix" data-duration="600" data-is-answer="true">
                                <div class="skillbar-title">
                                    <span><?=__('时间', 'bingo')?> <span class="seconds-left">10:00</span></span>
                                </div>
                                <div class="skillbar-bar"></div>
                            </div>
							<?php endif; ?>
							<?php if(in_array($question_type_main_language->slug, array('intensive-listening', 'ccl-intensive-listening'))): ?>
                                <div class="skillbar timer clearfix" data-duration="2400">
                                    <div class="skillbar-title">
                                        <span><?=__('时间', 'bingo')?> <span class="seconds-left">40:00</span></span>
                                    </div>
                                    <div class="skillbar-bar"></div>
                                </div>
							<?php endif; ?>
							<?php if(in_array($question_type_main_language->slug, array('intensive-reading'))): ?>
                                <div class="skillbar timer clearfix" data-duration="60">
                                    <div class="skillbar-title">
                                        <span><?=__('时间', 'bingo')?> <span class="seconds-left">1:00</span></span>
                                    </div>
                                    <div class="skillbar-bar"></div>
                                </div>
							<?php endif; ?>
							<?php if(in_array($question_type_main_language->slug, array('write-essay'))): ?>
								<div class="skillbar timer clearfix" data-duration="1200">
									<div class="skillbar-title">
										<span><?=__('时间', 'bingo')?> <span class="seconds-left">20:00</span></span>
									</div>
									<div class="skillbar-bar"></div>
								</div>
							<?php endif; ?>
	                        <?php if(in_array($question_type_main_language->slug, array('ca-exam-question'))): ?>
							  <div class="skillbar timer clearfix" data-duration="3000">
								  <div class="skillbar-title">
									  <span><?=__('时间', 'bingo')?> <span class="seconds-left">50:00</span></span>
								  </div>
								  <div class="skillbar-bar"></div>
							  </div>
	                        <?php endif; ?>
							<?php if(empty($exam) && in_array($question_type_main_language->slug, array('fill-in-the-blanks-i', 'fill-in-the-blanks-ii', 'reorder-paragraph'))): ?>
                                <div class="skillbar timer clearfix" data-duration="180">
                                    <div class="skillbar-title">
                                        <span><?=__('时间', 'bingo')?> <span class="seconds-left">03:00</span></span>
                                    </div>
                                    <div class="skillbar-bar"></div>
                                </div>
							<?php endif; ?>
							<?php if (empty($exam) || !in_array($question_type_main_language->slug, array('repeat-sentence', 'answer-short-question', 'ccl-vocabulary-primary', 'ccl-vocabulary-junior', 'ccl-vocabulary-senior'))): ?>
                            <audio id="ding-sound" preload="auto" src="<?=get_stylesheet_directory_uri()?>/assets/audios/ding.wav" style="display:none"></audio>
							<?php endif; ?>
                        </div>
                    </div>
					<?php if (empty($exam)):
					$uri = $_GET['random'] ? remove_query_arg(array('random'), $wp->request . '/') : add_query_arg(array('random' => 'yes'), $wp->request . '/');
					$uri = $_GET['tag'] ? add_query_arg(array('tag' => $_GET['tag']), $uri) : $uri;
					?>
                    <a class="btn primary-btn" href="<?=home_url($uri);?>"><?=__('切换到', 'bingo')?><?=$_GET['random'] ? __('顺序练习', 'bingo') : __('随机练习', 'bingo')?></a>
					<?php endif; ?>
                    <?php if ($tips = get_post_meta($question_type_desc->ID, 'tips', true)): ?>
                    <div class="sidebar-widget">
                        <span class="widget-icon"><i class="fa fa-info-circle"></i></span>
                        <h5 class="sidebar-widget-title ib"><?=__('提示', 'bingo')?></h5>
                        <div class="content">
                            <?=$tips?>
                        </div>
                    </div>
                    <?php endif; ?>
					<?php if ($notes_id = get_post_meta(get_the_ID(), 'notes', true)): ?>
                    <div class="sidebar-widget">
                        <span class="widget-icon"><i class="fa fa-info-circle"></i></span>
                        <h5 class="sidebar-widget-title ib"><?=__('笔记', 'bingo')?></h5>
                        <small class="fr"><?=__('点击图片放大', 'bingo')?></small>
                        <div class="content" style="overflow:auto;max-height:150px">
                            <a href="<?=wp_get_attachment_url($notes_id)?>"><img src="<?=wp_get_attachment_image_url($notes_id, 'post-thumbnail')?>"></a>
                        </div>
                    </div>
					<?php endif; ?>
                </div>
			</div><!-- End Sidebar - RIGHT -->
		</div><!-- End main row -->
	</div><!-- End container -->
</article><!-- End Single Article -->

<style type="text/css">
    .question.content audio {
        display: none;
    }
</style>

<script type="text/javascript">
jQuery(function($) {

    // exam seciton timer
	var sectionTimer = $('.section-timer');
	if (sectionTimer.length && sectionTimer.text().match(/:/)) {
	    var timeLeft = moment.duration('00:' + sectionTimer.text().trim());
		var timerInterval = setInterval(function () {
		    timeLeft = timeLeft.subtract(1, 'second');
		    if (timeLeft.asSeconds() >= 0) {
                sectionTimer.text(moment().startOf('day').add(timeLeft).format('mm:ss'));
			} else {
		        clearInterval(timerInterval);
		        console.log('Trigger submit answer button for time up.');
		        $('.submit-answer').trigger('click', {force: true, timeup: true});
			}
		}, 1000);
	}

    // toggle answer display
    var answerToggler = $('.comments-list.answer .toggle').click(function(e) {
        e.preventDefault();

        if ($(this).hasClass('disabled')) {
            return;
        }

        if ($(this).text() === '<?=__('显示', 'bingo')?>') {
            $(this).text('<?=__('隐藏', 'bingo')?>');
            $('.comments-list.answer .content').show(300);
        }
        else {
            $(this).text('<?=__('显示', 'bingo')?>');
            $('.comments-list.answer .content').hide(300);
        }
    });

    // timers
    $.fn.startTimer = function () {
        var tick = 0;
        var self = this;
        var duration = $(this).data('duration');

        var timeLeft = moment.duration(duration, 'seconds');
        var timerInterval = setInterval(function () {
            timeLeft = timeLeft.subtract(1, 'second');
            tick ++;
            if (timeLeft.asSeconds() >= 0) {
                $(self).data('time-left', timeLeft.asSeconds());
                $(self).find('.seconds-left').text(moment().startOf('day').add(timeLeft).format('mm:ss'));
                $(self).find('.skillbar-bar').css({width: tick / duration * 100 + '%'});
            } else {
                clearInterval(timerInterval);
                $(self).trigger('time-up');
            }
        }, 1000);

        if ($(this).data('is-answer')) {
            var answerVoiceRecorder = document.querySelector('#answer-voice-record');
            var ding = $('#ding-sound').get(0);
            var playlistContainer = $(answerVoiceRecorder).find('.playlist');

            if (answerVoiceRecorder && ding) {
                ding.play();
			}

            setTimeout(function () {
                var recordStartedAt = moment();
                var recordTimeContainer = $(answerVoiceRecorder).find('.record-time').show();
				var recordTimeInterval = setInterval(function () {
				    var recorded = moment(moment().diff(recordStartedAt));
                    recordTimeContainer.find('.time').text(recorded.format('mm:ss'));
				}, 1000);

                $(playlistContainer).on('recorder.silenced', function () {
                    var recorded = moment(moment().diff(recordStartedAt) - 3000);
                    recordTimeContainer.find('.time').text(recorded.format('mm:ss'));
                    console.log('Trigger submit answer button for silenced.');
                    $('.submit-answer').trigger('click', {force: true, silenced: true}); // click submit-answer button in exam mode
				});

                recordTimeContainer.data('interval', recordTimeInterval);
                $('.btn-record').trigger('click');
            }, answerVoiceRecorder && ding ? 1000 : 0);

			$('.answer-form audio').each(function () {
				this.play();
			});
        }

        $(this).data('interval', timerInterval);

        return timerInterval;
    };

    $.fn.stopTimer = function () {
        var timerInterval = $(this).data('interval');
        clearInterval(timerInterval);
	};

    // auto plays audio in question and show audio timer
    var audioProgress = $('.audio-progress');
    var contentAudio = $('.question.content audio').each(function() {
        var self = this;
        audioProgress.show()
        .on('click', '#pause-control', function () {
            self.pause();
        })
        .on('click', '#play-control', function () {
            self.play();
        })
        .on('click', '#replay-control', function () {
            self.currentTime = 0;
            self.play();
        })
        .on('click', '#rewind-control', function () {
            self.currentTime -= 5;
        })
        .on('click', '#fast-forward-control', function () {
            self.currentTime += 5;
        })
        .on('click', '.jump-to-time-point', function () {
            var timePoint = Number($(this).data('time-point'));
            var nextTimePoint = Number($(this).next('.jump-to-time-point').data('time-point'));

            self.currentTime = timePoint; self.play();

            if (nextTimePoint && nextTimePoint > timePoint) {
				console.log('Will pause after ' + (nextTimePoint - timePoint) + 'seconds.');
				if (window.timePointPlayTimeout) {
				    clearTimeout(window.timePointPlayTimeout);
				}
                window.timePointPlayTimeout = setTimeout(function () {
                    self.pause();
                    console.log('Paused.');
                }, (nextTimePoint - timePoint) * 1000);
            }
        })
        .on('click', '#mark-audio-time-point', function () {
            var timePoint = Number(Math.max(0, self.currentTime - 0.15).toFixed(2));
            audioProgress.find('.audio-navigation>form')
                .find('.btn.saved').remove().end()

                .append($('<a class="btn unsaved jump-to-time-point" data-time-point="' + timePoint + '">' +
                    (audioProgress.find('.audio-navigation').find('.btn.unsaved').length + 1) +
                    '<input type="hidden" name="audio_time_point[]" value="' + timePoint + '"></a>'
                ));
        });

        if (!audioProgress.prev('.timer').length) {
            setTimeout(function () {
                self.play().catch(function(e){
                    console.error(e.message);
                    audioProgress.find('#play-control').show();
	            });
            }, 3000);
		}
    })
    .on('play', function() {
        audioProgress.find('#play-control').hide();
        audioProgress.find('#pause-control').show();
    })
    .on('pause', function() {
        audioProgress.find('#play-control').show();
        audioProgress.find('#pause-control').hide();
    })
    // update audio timer
    .on('timeupdate', function() {
        if (this.currentTime && this.duration) {
            $('.audio-progress .skillbar-bar').css({width: this.currentTime / this.duration * 100 + '%'});
        }
    })
    // trigger next timer on audio ended
    .on('ended', function () {
        var nextTimer = audioProgress.next('.timer');
        if (nextTimer.data('wait') === 'previous') {
            nextTimer.startTimer();
        }
        audioProgress.find('#pause-control').hide();
    });

    // auto start timer
    $('.timer').each(function() {
        var wait = $(this).data('wait') || 0;
        var self = this;

        if (!isNaN(wait)) {
            setTimeout(function(){
                var interval = $(self).startTimer();
                $(self).data('interval', interval);
            }, wait * 1000);
        }
    })
    // trigger next timer on time up
    .on('time-up', function () {
        var nextTimer = $(this).next('.timer');
        var nextAudioProgress = $(this).next('.audio-progress');
        if (nextAudioProgress.length && contentAudio.length) {
            contentAudio.get(0).play();
		}
        if (nextTimer.data('wait') === 'previous') {
            nextTimer.data('interval', nextTimer.startTimer());
        }
        $(this).find('.skip').remove();
        if ($(this).data('is-answer')) {
            var submitAnswer = $('.submit-answer');
            submitAnswer.trigger('click', {force: true});
        }
    })
    .on('click', '.skip', function (e) {
        var self = e.delegateTarget;
        clearInterval($(self).data('interval'));
        $(self).find('.seconds-left').text('00:00');
        $(self).find('.skillbar-bar').css({width: '100%'});
        $(this).trigger('time-up');
    });

    var contentElem = $('.post .entry:not(.comment-form) .content');
    var answerContentElement = $('.answer.entry .content');
    var answerToggleButton = $('.answer.entry .toggle');
    if (answerContentElement.length) {
        var answer = $(answerContentElement).clone().find('sup').remove().end().find('p').map(function (i, p) { return $(p).text().replace(/<?=__('答案', 'bingo')?>/, ''); }).toArray().join(' ');
        var answerTrimmed = answer.toLowerCase().replace(new RegExp('\.(?!\d)','g'), '').replace(/[\'\?\!\-\<\>]/g, '').trim();
        var answerWordCount = answerTrimmed.split(/\s+/).length;
	}

    var answerForm = $('.comment-form.answer-form');
    var wordCountElement = answerForm.find('.word-count>.count');
    var wordDiffCountElement = answerForm.find('.word-diff-count>.diff-percentage');
    var answerArea = answerForm.find('#answer-area');
    var answerCheckButton = answerForm.find('.diff-check');
    var answerResumeButton = answerForm.find('.resume-input');
    var userAnswer = [];
	<?php if ($answer): ?>
    userAnswer = JSON.parse('<?=json_encode($answer)?>');
	<?php endif; ?>

    answerArea.on('keyup', function() {

        // answer word count
        var answerInputValue = $(this).val().trim();
        var answerInputValueTrimmed = answerInputValue.toLowerCase().replace(new RegExp('\.(?!\d)','g'), '').replace(/[\'\?\!\-\<\>]/g, '').trim();
        var wordCount = answerInputValue.split(/\s+/).filter(function(w){ return w; }).length;
        var _self = this;
        wordCountElement.text(wordCount);

        if (wordCount > 70) {
            wordCountElement.addClass('over-words');
        }
        else {
            wordCountElement.removeClass(('over-words'));
        }

        // console.log(answer); console.log(answerInputValue);

        // answer diff rate
        setTimeout(function () {

            var diffWords, diffRate;

            if (!wordDiffCountElement.length || !answerTrimmed) {
                return;
            }

            diffWords = JsDiff.diffWords(answerTrimmed, answerInputValueTrimmed).reduce(function (stat, current) {
                var diffWordCount = current.value.trim().split(/\s+/).length;

                if (current.added && !stat.modified) {
                    stat.diff += diffWordCount;
                }
                else if (current.removed) {
                    stat.diff += diffWordCount;
                    stat.modified += diffWordCount;
                }
                else if (current.added && stat.modified) {
                    stat.modified = 0;
                }

                return stat;

            }, {diff:0, modified:0}).diff;

            diffRate = diffWords / answerWordCount;

            wordDiffCountElement.text((100 - diffRate * 100).toFixed(0) + '%');

            if (diffRate <= 0.25 || ($('.rating').data('rating') >= 4 && diffRate <= 0.3)) {
                answerCheckButton.prop('disabled', false);
                answerToggleButton.removeClass('disabled');
            }
            else {
                answerCheckButton.prop('disabled', true);
                if (answerToggleButton.hasClass('disable-on-high-diff')) {
                    answerToggleButton.addClass('disabled');
                }
            }
        });

    });

    answerArea.trigger('keyup');

    answerCheckButton.on('click', function (e) {
        e.preventDefault();

        var answerInputValue = answerArea.val().trim();
        var diff = JsDiff.diffWords(answer, answerInputValue);

        var resultContainer = answerForm.find('.diff-check-result').html('').show();

        diff.forEach(function (part) {
            var word = $('<span/>').text(part.value);
            if (part.removed) {
                word.addClass('removed');
            }
            if (part.added) {
                word.addClass('added');
            }
            resultContainer.append(word);
        });

        resultContainer.html(resultContainer.html().replace(/\n{2,}/g, "\n"));

        answerArea.hide(); answerCheckButton.hide(); answerResumeButton.show();
    });

    answerResumeButton.on('click', function (e) {
        e.preventDefault();
        answerForm.find('.diff-check-result').hide();
        answerArea.show(); answerCheckButton.show(); answerResumeButton.hide();
    });

    // highlight on click
	<?php if (in_array($question_type_main_language->slug, array('highlight-incorrect-words'))): ?>
    $('.question.content.highlightable blockquote p').each(function () {
        $(this).html($(this).html().split(/\s+/).map(function (word, index) {
            return '<span data-word-index="' + index + '">' + word + '</span>';
        }).join(' '));
    })
    .on('click', 'span', function () {
        $(this).replaceWith('<del class="answer-input" data-answer-value="' + $(this).data('word-index') + '">' + $(this).html() + '</del>');
    })
    .on('click', 'del', function () {
        $(this).replaceWith('<span data-answer-value="' + $(this).data('word-index') + '">' + $(this).html() + '</span>');
    });

    <?php	if (isset($exam) && $answer): ?>
    userAnswer.forEach(function (index) {
        var wordSpan = $('[data-word-index="' + index + '"]');
        wordSpan.replaceWith('<del class="answer-input" data-answer-value="' + wordSpan.data('word-index') + '">' + wordSpan.html() + '</del>');
	});
    <?php 	endif; ?>
    <?php endif; ?>

    $('.go-to-exercise').on('change', function () {
        window.location.href = $(this).val();
    });
	
    // Reading - Fill in the Blanks I
	<?php if (in_array($question_type_main_language->slug, array('fill-in-the-blanks-i', 'fill-in-the-blanks-ii'))): ?>
    contentElem.on('click', '.options .option', function () {
        $(this).parents('.content').find('.option').removeClass('selected');
        $(this).toggleClass('selected');
    }).on('click', '.blank', function () {
        if ($(this).is('.has-options')) {
            $(this).next('select').show().trigger('click');
            $(this).hide();
        }
        else if ($(this).is('.option')) {
            $(this).clone().removeClass('blank').appendTo('.options');
            $(this).removeClass('option').text('');
        }
        else {
            var optionElem = $(this).parents('.content').find('.options>.option.selected');
            var text = optionElem.text();
            if (!text) {
                return;
            }
            optionElem.remove();
            $(this).addClass('option').text(text);
        }
    }).on('change', '.blank+select', function () {
        $(this).prev('.blank').addClass('option').text($(this).val()).show();
        $(this).hide();
    });

    userAnswer && userAnswer.forEach(function (filledValue, index) {
        if (filledValue) {
            $('.blank:eq(' + index + ')').text(filledValue).addClass('option');
            $('.options>[data-option="' + filledValue + '"]').remove();
        }
    });
    <?php endif; ?>

	<?php if ($question_type_main_language->slug === 'fill-in-the-blanks-listening'): ?>
	$('.blank-fib-l.answer-input').each(function (index) {
	    if (userAnswer && userAnswer[index]) {
	        $(this).val(userAnswer[index]);
        }
	});
	<?php endif; ?>

    <?php if ($question_type_main_language->slug === 'reorder-paragraph'): ?>
    var paras = contentElem.children('p');
	var reorderableElem = $('<div class="reorderable" />').appendTo(contentElem);
	var reorderedElem = $('<div class="reordered" />').appendTo(contentElem);
    paras.appendTo(reorderableElem);

    paras.each(function(index, el) {
        $(el).text((index + 1 + '. ') + $(el).text());
        $(el).data('answer-value', index + 1);
    });

    contentElem.on('click', '.reorderable p', function () {
        contentElem.find('.reordered').append($(this));
        $(this).addClass('answer-input');
    });
    contentElem.on('click', '.reordered p', function () {
        contentElem.find('.reorderable').append($(this));
        $(this).removeClass('.answer-input');
    });
    <?php 	if (isset($exam) && $answer): ?>
    reorderedElem = userAnswer.map(function (order) {
        return contentElem.find('.reorderable>:eq(' + (order - 1) + ')');
	});
    reorderedElem.forEach(function (el) {
        contentElem.find('.reordered').append($(el));
        $(this).addClass('answer-input');
	});
	<?php 	endif; ?>
    <?php endif; ?>

	// submit answer in exam
	<?php if (isset ($exam)): ?>
	$('.submit-answer').on('click', function (e, data) {
	    var self = this;

	    if (typeof data === 'undefined') {
		    data = {};
		}
		
	    if (!data.force && !confirm ('<?=__('提交后将无法修改答案，确认提交吗？', 'bingo')?>')) {
	        return;
		}

		if (data.force && data.timeup) {
            alert ('<?=__('时间到，即将提交并转入下一题或下一部分', 'bingo')?>');
        }

        if (data.force && data.silenced && !window.localStorage.getItem('notified.silencedSkip')) {
            alert ('<?=__('3秒没有检测到声音，即将提交并转入下一题或下一部分。（注意：本提示不会再出现）', 'bingo')?>');
            window.localStorage.setItem('notified.silencedSkip', true);
        }

        // stop timer
		var timer = $('.timer');

	    timer.each(function () {
	        $(this).stopTimer();
		});

		// stop recorder if any
        if ($('.btn-stop').click().length) {
            // hide submit button, and show next button
            var nextButton = $(self).parent().siblings('.next').find('.btn');
            console.log('Set next button text data to', nextButton.text());
			nextButton.prop('disabled', true).addClass('disabled').data('text', nextButton.text()).text('<?=__('上传中…', 'bingo')?>');
            $(self).parent().hide()
                .siblings('.next').show();

            // answer save should be done along with upload handling script
            return;
		}

        var answerInputs = $('.answer-input');
	    var currentExerciseTimeLeft = $(timer[timer.length-1]).data('time-left');

		var answers = $.map(answerInputs.filter(function () {
            return !$(this).is('[type="checkbox"],[type="radio"]') || $(this).is(':checked');
		}), function (answerInput) {
            return $(answerInput).val() || $(answerInput).data('answer-value') || $(answerInput).text().trim();
		});
		$.post(window.location.href, {
            answer: answers,
			current_exercise_time_left: currentExerciseTimeLeft
		}, function () {
            // hide submit button and show next button
            // $(self).parent().hide()
            //     .siblings('.next').show();

			// jump to next
            $(self).parent().siblings('.next').find('button').click();

		});
	});
	<?php 	if ($review): ?>
    answerToggler.trigger('click');
	<?php 	endif; ?>
	<?php endif; ?>
});
</script>

<?php

if (!(isset($exam) && $answer) && in_array($question_type_main_language->slug, array('read-aloud', 'repeat-sentence', 'ccl-vocabulary-primary', 'ccl-vocabulary-junior', 'ccl-vocabulary-senior', 'answer-short-question', 'describe-image', 'retell-lecture', 'dialogue-interpreting'))) {
	wp_enqueue_script('waveform');
	wp_enqueue_script('waveform-record');
	wp_enqueue_script('waveform-emitter');
	wp_enqueue_script('mp3-lame-encoder');
}

if (!(isset($exam) && $answer) && in_array($question_type_main_language->slug, array('write-from-dictation', 'intensive-listening', 'ccl-intensive-listening'))) {
	wp_enqueue_script('jsdiff');
}

get_footer();
