<?php

function storychief_migrate_register_uninstall_hook() {
    delete_option('storychief_posts_migrated');
    delete_option('storychief_migrate_completed');
}