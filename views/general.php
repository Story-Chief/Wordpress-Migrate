<?php
/** @var string $api_key */
/** @var string $page_url */
/** @var int $total_posts */
/** @var int $total_completed */
/** @var int $total_percentage */
/** @var bool $completed */
?>
<?php if (false && $completed): ?>
    <div class="wrap sc-migrate">
        <h1>StoryChief Migrate</h1>
        <p>
            We completed migrating all of your posts.
        </p>

        <?php $errors = \StoryChiefMigrate\Admin::get_errors(); ?>
        <?php if (!$errors->have_posts()): ?>
            <h3>Next steps</h3>
            <ul class="sc-list">
                <li>
                    You can deactivate or uninstall the plugin StoryChief Migrate.
                </li>
            </ul>
        <?php else: ?>
            <h3>Issues <span class="dashicons dashicons-warning" style="color: #d63638;"></span></h3>
            <p>
                Sorry, while migrating we encountered some problems with some posts that failed.
            </p>
            <ul class="sc-list">
                <li>
                    Some posts may have failed due the fact, they had broken images or HTML
                </li>
                <li>
                    Below is a list of posts that failed, with a description
                </li>
                <li>
                    Please view "/wp-content/plugins/story-chief/error.log" to find all errors
                </li>
            </ul>

            <h3>Posts (<?php echo $errors->found_posts; ?>)</h3>

            <p>
                Please contact <u>your developer</u> or send StoryChief an email at
                <a href="mailto:support@storychief.io">support@storychief.io</a>
                and we will get back to you as soon as possible.
            </p>

            <table class="wp-list-table widefat fixed striped table-view-list posts">
                <thead>
                <tr>
                    <th>
                        Post
                    </th>
                    <th>
                        Error code
                    </th>
                    <th>
                        Error type
                    </th>
                    <th>
                        Error message
                    </th>
                    <th>
                        Errors
                    </th>
                </tr>
                </thead>
                <tbody>
                    <?php while($errors->have_posts()): $errors->the_post(); ?>

                    <?php $post_error = get_post_meta(get_the_ID(), 'storychief_migrate_error', true); ?>
                    <tr>
                        <td>
                            <a href="<?php the_permalink(); ?>" target="_blank">
                                <?php the_title(); ?>
                            </a>
                        </td>
                        <td>
                            <?php echo esc_html($post_error['code']); ?>
                        </td>
                        <td>
                            <?php echo esc_html($post_error['type']); ?>
                        </td>
                        <td>
                            <?php echo esc_html($post_error['message']); ?>
                        </td>
                        <td>
                            <?php foreach($post_error['errors'] as $errorKey => $errorMessages): ?>
                                <strong><?php echo esc_html($errorKey); ?></strong>

                                <ul class="sc-list">
                                    <?php foreach ($errorMessages as $errorMessage): ?>
                                        <li><?php echo esc_html($errorMessage); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endforeach; ?>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
<?php else: ?>
    <div class="wrap sc-migrate" id="sc-migrate"></div>
    <?php /*
        <section id="sc-step-api_key">
            <h1>StoryChief Migrate</h1>
            <ul class="sc-list">
                <li>
                    You can change the post type <a href="<?= Storychief\Admin::get_page_url(); ?>">here</a>
                </li>
                <li>
                    We recommend running the migration first, through a staging environment if possible
                </li>
                <li>
                    We will copy all of your drafts and published posts
                </li>
                <li>
                    Please keep this tab open, while the migration is running
                </li>
                <li>
                    turn on <a href="<?= Storychief\Admin::get_page_url(); ?>">debug mode</a> on to log any error.
                </li>
            </ul>

            <form action="#" method="post" id="sc-form-api_key">
                <table class="form-table">
                    <tbody>
                    <tr>
                        <th scope="row">
                            <label for="api_key">
                                <?php esc_html_e('Enter your StoryChief API Key', 'storychief-migrate'); ?>
                            </label>
                        </th>
                        <td>
                            <textarea name="api_key" id="api_key" rows="10" autocomplete="off"></textarea>
                        </td>
                    </tr>
                    </tbody>
                </table>

                <div class="sc-error update-message notice inline notice-warning notice-alt" hidden>
                    <p>Sorry, the API-key you entered is incorrect.</p>
                </div>

                <p class="submit">
                    <button type="submit" name="submit" id="submit" class="button button-primary">
                        <?php esc_attr_e('Next', 'storychief-migration'); ?>
                    </button>
                </p>
            </form>
        </section>
        <section id="sc-step-destination_id" hidden>
            <form id="sc-form-destination_id" method="post">
                <h1>StoryChief Migrate</h1>
                <p>
                    Please select your WordPress channel, that matches the current domain you are using.
                </p>
                <table class="form-table">
                    <tbody>
                        <tr>
                            <th scope="row">
                                <label for="destination_id">
                                    <?php esc_html_e('Select a destination', 'storychief-migrate'); ?>
                                </label>
                            </th>
                            <td>
                                <select id="destination_id" name="destination_id">
                                    <option>Please select a destination</option>
                                </select>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <p class="submit">
                    <button type="submit" name="submit" id="submit" class="button button-primary">
                        <?php esc_attr_e('Next', 'storychief-migration'); ?>
                    </button>
                </p>
            </form>
        </section>
        <section id="sc-step-run" hidden>
            <form id="sc-run-form" method="post">
                <h1>StoryChief Migrate</h1>
                <p>
                    You can press the button to start migrating your posts to StoryChief.
                </p>
                <p class="submit">
                    <button type="submit" name="submit" id="submit" class="button button-primary">
                        <?php esc_attr_e('Run migration', 'storychief-migration'); ?>
                    </button>
                </p>
            </form>
            <div id="sc-run-progress" hidden="">
                <h1>StoryChief Migrate <span class="dashicons dashicons-image-rotate"></span></h1>
                <p>
                    Please do not close this tab, while we are migrating your existing stories into StoryChief.
                </p>
                <ul class="sc-list">
                    <li>Total posts: <span id="sc-run-total-posts">?</span></li>
                    <li>Total completed: <span id="sc-run-total-completed">?</span></li>
                    <li>Total success: <span id="sc-run-total-success">?</span></li>
                    <li>Total failed: <span id="sc-run-total-failed">?</span></li>
                </ul>
                <hr>
                <label for="sc-progress-label" id="sc-progress-label">
                    <?= ceil($total_percentage); ?>%
                </label>
                <progress id="sc-progress-bar" max="<?= $total_posts; ?>" value="<?= $total_completed; ?>"></progress>
                <div class="update-message notice inline notice-warning notice-alt" hidden="" id="sc-progress-error"></div>
            </div>
        </section>
    </div>
 */ ?>
<?php endif; ?>