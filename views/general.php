<?php
/** @var string $api_key */
/** @var string $page_url */

use StoryChiefMigrate\Admin;

?>
<div class="wrap sc-migrate" id="sc-migrate">
    <h1>StoryChief Migrate</h1>

    <section id="sc-step-api_key">
        <ul class="sc-list">
            <li>
                Please enter your StoryChief API-key,
                if you need help finding your own API-key please click <a href="">here</a>
            </li>
            <li>
                You can change the post type <a href="<?= Storychief\Admin::get_page_url(); ?>">here</a>
            </li>
            <li>
                Please keep this tab open, while the migration is running
            </li>
        </ul>

        <form action="#" method="post" id="sc-form-api_key">
            <table class="form-table">
                <tbody>
                <tr>
                    <th scope="row">
                        <label for="api_key"><?php
                            esc_html_e('Enter your StoryChief API Key', 'storychief-migrate'); ?></label>
                    </th>
                    <td>
                        <textarea name="api_key" id="api_key" rows="10"><?php
                            echo $api_key; ?></textarea>
                    </td>
                </tr>
                </tbody>
            </table>

            <div class="sc-error update-message notice inline notice-warning notice-alt" hidden>
                <p>Sorry, the API-key you entered is incorrect.</p>
            </div>

            <p class="submit">
                <button type="submit" name="submit" id="submit" class="button button-primary">
                    <?php
                    esc_attr_e('Next', 'storychief-migration'); ?>
                </button>
            </p>
        </form>
    </section>
    <section id="sc-step-destination_id" hidden>
        <form id="sc-form-destination_id" method="post">
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
                    <?php
                    esc_attr_e('Next', 'storychief-migration'); ?>
                </button>
            </p>
        </form>
    </section>
    <section id="sc-step-run" hidden>
        <form id="sc-run-form" method="post">
            <p>
                You can press the button to start migrating your posts to StoryChief.
            </p>
            <p class="submit">
                <button type="submit" name="submit" id="submit" class="button button-primary">
                    <?php
                    esc_attr_e('Run migration', 'storychief-migration'); ?>
                </button>
            </p>
        </form>
        <div id="sc-run-progress" hidden="">
            <?php
            $total_posts = Admin::get_total_posts();
            $total_completed = Admin::get_total_completed();
            $total_percentage = $total_posts ? ceil($total_completed / $total_posts * 100) : 0;
            ?>
            <p>
                Please do not close this tab, while we are migrating your existing stories into StoryChief.
            </p>
            <label for="sc-progress" id="sc-progress-label">
                <?= $total_percentage; ?>%
            </label>
            <progress id="sc-progress" max="<?= Admin::get_total_posts(); ?>" value="<?= Admin::get_total_completed(); ?>"></progress>
        </div>
    </section>
    <section id="sc-step-completed" hidden>
        <p>
            We completed migrating all of your stories.
        </p>
    </section>
    <?php /*
    <section id="sc-section-run" hidden>
        <h3>Progress</h3>
        <p>
            Please wait and keep this window / tab open until the migration is done.
        </p>
        <progress id="sc-progress" max="0" value="0"></progress>
        <section id="sc-section-step-3" hidden>
            <p>Success, all stories were migrated to StoryChief.</p>
        </section>
        <section id="sc-section-error" hidden>
            <div class="update-message notice inline notice-warning notice-alt">
                <p>Sorry, and error</p>
            </div>
        </section>
    </section>
 */ ?>
</div>
