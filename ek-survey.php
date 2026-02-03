<?php
/**
 * Plugin Name: EK Survey
 * Description: A template-based survey plugin with multi-step forms, progress bars, PDF generation, and database storage.
 * Version: 1.0.0
 * Author: Antigravity
 */

if (!defined('ABSPATH')) {
	exit; // Exit if accessed directly.
}

// Define plugin constants
define('EK_SURVEY_VERSION', '1.0.0');
define('EK_SURVEY_PATH', plugin_dir_path(__FILE__));
define('EK_SURVEY_URL', plugin_dir_url(__FILE__));

// Require Composer autoloader
if (file_exists(EK_SURVEY_PATH . 'vendor/autoload.php')) {
	require_once EK_SURVEY_PATH . 'vendor/autoload.php';
}

// Include required files
require_once EK_SURVEY_PATH . 'includes/class-activator.php';
require_once EK_SURVEY_PATH . 'includes/class-survey-render.php';
require_once EK_SURVEY_PATH . 'includes/class-form-handler.php';
require_once EK_SURVEY_PATH . 'includes/class-pdf-generator.php';
require_once EK_SURVEY_PATH . 'includes/class-admin.php';

/**
 * Activation Hook
 */
function ek_survey_activate()
{
	Ek_Survey_Activator::activate();
}
register_activation_hook(__FILE__, 'ek_survey_activate');

// Init Admin
add_action('init', array('Ek_Survey_Admin', 'init'));

/**
 * Initialize Plugin
 */
function ek_survey_init()
{
	// Register Shortcodes
	add_shortcode('ek_survey', array('Ek_Survey_Render', 'render_shortcode'));

	// Handle AJAX
	add_action('wp_ajax_ek_survey_submit', array('Ek_Survey_Form_Handler', 'handle_submission'));
	add_action('wp_ajax_nopriv_ek_survey_submit', array('Ek_Survey_Form_Handler', 'handle_submission'));

	// Enqueue Scripts & Styles
	add_action('wp_enqueue_scripts', 'ek_survey_enqueue_assets');
}
add_action('init', 'ek_survey_init');

function ek_survey_enqueue_assets()
{
	wp_enqueue_style('ek-survey-style', EK_SURVEY_URL . 'assets/css/style.css', array(), EK_SURVEY_VERSION);
	wp_enqueue_script('ek-survey-script', EK_SURVEY_URL . 'assets/js/script.js', array('jquery'), EK_SURVEY_VERSION, true);

	wp_localize_script('ek-survey-script', 'ekSurveyAjax', array(
		'ajaxurl' => admin_url('admin-ajax.php'),
		'nonce' => wp_create_nonce('ek_survey_nonce'),
	));
}
