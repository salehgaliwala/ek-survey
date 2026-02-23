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
	// Enqueue Offline Storage
	wp_enqueue_script('ek-survey-offline-storage', EK_SURVEY_URL . 'assets/js/offline-storage.js', array(), EK_SURVEY_VERSION, true);

	wp_enqueue_style('ek-survey-style', EK_SURVEY_URL . 'assets/css/style.css', array(), EK_SURVEY_VERSION);
	wp_enqueue_script('ek-survey-script', EK_SURVEY_URL . 'assets/js/script.js', array('jquery', 'ek-survey-offline-storage'), EK_SURVEY_VERSION, true);

	wp_localize_script('ek-survey-script', 'ekSurveyAjax', array(
		'ajaxurl' => admin_url('admin-ajax.php'),
		'nonce' => wp_create_nonce('ek_survey_nonce'),
		'swUrl' => site_url(
			'/?ek_service_worker=1' .
			'&css=' . urlencode(EK_SURVEY_URL . 'assets/css/style.css') .
			'&js=' . urlencode(EK_SURVEY_URL . 'assets/js/script.js') .
			'&offline=' . urlencode(EK_SURVEY_URL . 'assets/js/offline-storage.js')
		)
	));

	// Inline script to register Service Worker
	wp_add_inline_script('ek-survey-script', "
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', function() {
                var swUrl = ekSurveyAjax.swUrl;
                navigator.serviceWorker.register(swUrl, {scope: '/'})
                .then(function(registration) {
                    console.log('ServiceWorker registration successful with scope: ', registration.scope);
                    
                    // Prime the cache with the current page
                    navigator.serviceWorker.ready.then(function() {
                        fetch(window.location.href).catch(function() {
                            // Ignore errors if offline already or fetch fails
                        });
                    });
                }, function(err) {
                    console.log('ServiceWorker registration failed: ', err);
                });
            });
        }
    ");
}

add_action('init', 'ek_survey_serve_sw');
function ek_survey_serve_sw()
{
	if (isset($_GET['ek_service_worker']) && $_GET['ek_service_worker'] == 1) {
		$sw_path = EK_SURVEY_PATH . 'assets/js/service-worker.js';
		if (file_exists($sw_path)) {
			header('Content-Type: application/javascript');
			header('Service-Worker-Allowed: /');
			readfile($sw_path);
			exit;
		}
	}
}
