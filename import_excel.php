<?php
/**
 * Import Script for EK Survey CSV Data
 * Usage: php import_csv.php your_file.csv [survey_id]
 */

// Step 1: Find and Bootstrap WordPress
function find_wordpress_root($start_dir)
{
    $dir = $start_dir;
    while ($dir !== dirname($dir)) {
        if (file_exists($dir . '/wp-load.php')) {
            return $dir;
        }
        $dir = dirname($dir);
    }
    return null;
}

$wp_root = find_wordpress_root(__DIR__);
if (!$wp_root) {
    // Try some hardcoded common paths if automatic search fails
    $alt_paths = [
        'C:/wamp64/www/ek',
        'C:/wamp64/www/marhaba',
        __DIR__ . '/..'
    ];
    foreach ($alt_paths as $path) {
        if (file_exists($path . '/wp-load.php')) {
            $wp_root = $path;
            break;
        }
    }
}

if (!$wp_root) {
    die("Error: Could not find WordPress root (wp-load.php).\n");
}

define('WP_USE_THEMES', false);
require_once($wp_root . '/wp-load.php');

if (!defined('ABSPATH')) {
    die("Error: WordPress failed to load.\n");
}

// Step 2: Load Plugin Classes
// Assuming the script is in the plugin root
$plugin_path = __DIR__;
require_once $plugin_path . '/vendor/autoload.php';
require_once $plugin_path . '/includes/class-pdf-generator.php';

// CSV Helper
function get_csv_rows($filename)
{
    $rows = [];
    if (($handle = fopen($filename, "r")) !== FALSE) {
        while (($data = fgetcsv($handle, 0, ",")) !== FALSE) {
            $rows[] = $data;
        }
        fclose($handle);
    }
    return $rows;
}

function log_message($msg)
{
    echo "[" . date('H:i:s') . "] " . $msg . "\n";
}

if ($argc < 2) {
    die("Usage: php import_csv.php <filename.csv> [survey_id]\n");
}

$filename = $argv[1];
$survey_id = isset($argv[2]) ? intval($argv[2]) : 2; // Default to 2 for baseline

if (!file_exists($filename)) {
    die("Error: File not found: $filename\n");
}

log_message("Loading CSV file: $filename");
$rows = get_csv_rows($filename);
if (empty($rows)) {
    die("Error: Could not read CSV or file is empty.\n");
}

if (count($rows) < 2) {
    die("Error: Excel file has no data rows.\n");
}

$headers = $rows[0];
$data_rows = array_slice($rows, 1);

// Step 3: Map Headers to Question IDs
$header_to_id = [];
foreach ($headers as $index => $header) {
    if (empty($header))
        continue;

    // Extract ID like 1.1, 7a.1, 7a.5_collect, 10.10, etc.
    if (preg_match('/^(\d+[a-z]?\.[\w._]+)/', trim($header), $matches)) {
        $header_to_id[$index] = $matches[1];
    } else {
        $header_to_id[$index] = trim($header);
    }
}

log_message("Starting import of " . count($data_rows) . " rows for Survey ID: $survey_id");

global $wpdb;
$table_name = $wpdb->prefix . 'ek_submissions';

// Known multi-select (checkbox) fields
$multi_select_ids = ['4.5', '7a.1', '9.2', '10.1', '10.11', '10.12'];

foreach ($data_rows as $row_index => $row) {
    $raw_responses = [];
    $file_paths = [];
    $created_at = current_time('mysql');
    $user_id = 0;

    foreach ($row as $col_index => $value) {
        if (!isset($header_to_id[$col_index]))
            continue;

        $id = $header_to_id[$col_index];

        // Handle Meta Columns
        if ($id === 'Submission ID')
            continue;
        if ($id === 'User ID') {
            $user_id = intval($value);
            continue;
        }
        if ($id === 'Date') {
            if ($value) {
                // Try to parse date
                $ts = strtotime($value);
                if ($ts) {
                    $created_at = date('Y-m-d H:i:s', $ts);
                }
            }
            continue;
        }

        // Handle Base64 Images
        if (is_string($value) && strpos($value, 'data:image') === 0) {
            $upload_dir = wp_upload_dir();
            $target_base = 'ek-surveys/' . date('Y/m', strtotime($created_at));
            $base_dir = $upload_dir['basedir'] . '/' . $target_base;
            $base_url = $upload_dir['baseurl'] . '/' . $target_base;

            if (!file_exists($base_dir)) {
                wp_mkdir_p($base_dir);
            }

            $extension = 'png';
            if (strpos($value, 'image/jpeg') !== false || strpos($value, 'image/jpg') !== false) {
                $extension = 'jpeg';
            }

            $image_data = preg_replace('/^data:image\/\w+;base64,/', '', $value);
            $image_data = str_replace(' ', '+', $image_data);
            $decoded = base64_decode($image_data);

            $prefix = ($id == '11.1' || $id == '11.2') ? 'cap_' : 'ek_';
            $file_name = $prefix . uniqid() . '.' . $extension;
            $target_path = $base_dir . '/' . $file_name;
            $target_url = $base_url . '/' . $file_name;

            if (file_put_contents($target_path, $decoded)) {
                $raw_responses[$id] = $target_url;
                $file_paths[$id] = [
                    'path' => $target_path,
                    'url' => $target_url,
                    'name' => 'Imported Image'
                ];
            }
        }
        // Handle Checkboxes
        elseif (in_array($id, $multi_select_ids)) {
            if (!empty($value)) {
                if (is_string($value)) {
                    // Check for common separators
                    $sep = strpos($value, ',') !== false ? ',' : (strpos($value, ';') !== false ? ';' : null);
                    if ($sep) {
                        $raw_responses[$id] = array_map('trim', explode($sep, $value));
                    } else {
                        $raw_responses[$id] = [trim($value)];
                    }
                } else {
                    $raw_responses[$id] = [$value];
                }
            } else {
                $raw_responses[$id] = [];
            }
        }
        // Handle Normal Text/Number
        else {
            $raw_responses[$id] = ($value === null) ? "" : (string) $value;
        }
    }

    // Insert into Database
    $insert_result = $wpdb->insert($table_name, [
        'survey_id' => $survey_id,
        'user_id' => $user_id,
        'response_data' => json_encode($raw_responses),
        'created_at' => $created_at
    ]);

    if ($insert_result === false) {
        log_message("Row " . ($row_index + 1) . ": Database Insert Failed! Error: " . $wpdb->last_error);
        continue;
    }

    $submission_id = $wpdb->insert_id;
    log_message("Row " . ($row_index + 1) . ": Inserted submission $submission_id");

    // Generate PDF
    if (class_exists('Ek_Survey_Pdf_Generator')) {
        // We need to pass $raw_responses and $file_paths
        // Note: Signatures (11.1, 11.2) are handled by Pdf_Generator if they are in $file_paths
        $pdf_url = Ek_Survey_Pdf_Generator::generate($submission_id, $survey_id, $raw_responses, $file_paths);

        if ($pdf_url) {
            $wpdb->update($table_name, ['pdf_url' => $pdf_url], ['id' => $submission_id]);
            log_message("Row " . ($row_index + 1) . ": Generated PDF: $pdf_url");
        } else {
            log_message("Row " . ($row_index + 1) . ": PDF generation failed.");
        }
    } else {
        log_message("Row " . ($row_index + 1) . ": Ek_Survey_Pdf_Generator class not found.");
    }
}

log_message("Import process completed.");
echo "\nDONE!\n";
