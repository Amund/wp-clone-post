<?php

/**
 * Plugin name: Clone Post
 * Description: Add a "clone" link to posts and pages.
 * Author: Dimitri Avenel
 * License: GPLv2
 */

if (!defined('ABSPATH')) {
    exit();
}

require_once 'class/WpClonePost.php';

WpClonePost::init();
