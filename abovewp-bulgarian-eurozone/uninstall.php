<?php
/**
 * Uninstall AboveWP Bulgarian Eurozone
 *
 * @package AboveWP Bulgarian Eurozone
 */

// If uninstall not called from WordPress, exit
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Delete all plugin options
delete_option('abovewp_bge_enabled');
delete_option('abovewp_bge_eur_label');
delete_option('abovewp_bge_eur_position');