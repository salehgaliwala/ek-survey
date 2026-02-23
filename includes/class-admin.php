<?php

class Ek_Survey_Admin
{

    public static function init()
    {
        add_action('admin_menu', array(__CLASS__, 'add_admin_menu'));
        add_action('admin_init', array(__CLASS__, 'handle_csv_export'));
        add_action('admin_init', array(__CLASS__, 'handle_excel_import'));
    }

    public static function add_admin_menu()
    {
        add_menu_page(
            'EK Survey Submissions',
            'EK Survey',
            'manage_options',
            'ek-survey-submissions',
            array(__CLASS__, 'render_submissions_page'),
            'dashicons-clipboard',
            25
        );
    }

    public static function render_submissions_page()
    {
        global $wpdb;

        // Get all surveys for filter
        $surveys = $wpdb->get_results("SELECT id, title FROM {$wpdb->prefix}ek_surveys ORDER BY created_at DESC");

        // Determine active survey
        $selected_survey_id = isset($_GET['survey_id']) ? intval($_GET['survey_id']) : 0;
        if ($selected_survey_id === 0 && !empty($surveys)) {
            $selected_survey_id = $surveys[0]->id;
        }

        // Get submissions
        $table_name = $wpdb->prefix . 'ek_submissions';
        $submissions = [];
        if ($selected_survey_id > 0) {
            $submissions = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM $table_name WHERE survey_id = %d ORDER BY created_at DESC",
                $selected_survey_id
            ));
        }

        ?>
        <div class="wrap">
            <h1>EK Survey Submissions</h1>

            <?php settings_errors('ek_survey_import'); ?>

            <div class="tablenav top">
                <div class="alignleft actions">
                    <form method="get">
                        <input type="hidden" name="page" value="ek-survey-submissions">
                        <select name="survey_id">
                            <?php foreach ($surveys as $survey): ?>
                                <option value="<?php echo esc_attr($survey->id); ?>" <?php selected($selected_survey_id, $survey->id); ?>>
                                    <?php echo esc_html($survey->title); ?> (ID:
                                    <?php echo esc_html($survey->id); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <input type="submit" class="button" value="Filter">
                    </form>
                </div>
                <div class="alignleft actions">
                    <?php if ($selected_survey_id > 0): ?>
                        <a href="<?php echo admin_url('admin.php?page=ek-survey-submissions&action=export_csv&survey_id=' . $selected_survey_id); ?>"
                            class="button button-primary">Export to CSV</a>
                    <?php endif; ?>
                </div>
            </div>

            <div class="postbox" style="margin-top: 20px; padding: 15px;">
                <h2>Import Submissions from CSV</h2>
                <form method="post" enctype="multipart/form-data">
                    <input type="hidden" name="ek_survey_import_nonce"
                        value="<?php echo wp_create_nonce('ek_survey_import'); ?>">
                    <input type="hidden" name="survey_id" value="<?php echo esc_attr($selected_survey_id); ?>">
                    <table class="form-table" role="presentation">
                        <tr>
                            <th scope="row"><label for="csv_file">Select CSV File (.csv)</label></th>
                            <td>
                                <input type="file" name="csv_file" id="csv_file" accept=".csv" required>
                                <p class="description">Select the .csv file exported from KoboToolbox or matching the expected
                                    format.</p>
                            </td>
                        </tr>
                    </table>
                    <p class="submit">
                        <input type="submit" name="submit_import" id="submit" class="button button-primary"
                            value="Start Import">
                    </p>
                </form>
            </div>

            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th width="50">ID</th>
                        <th width="150">Date</th>
                        <th>Response Summary</th>
                        <th width="100">PDF</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($submissions)): ?>
                        <?php foreach ($submissions as $sub):
                            $data = json_decode($sub->response_data, true);
                            $field_count = is_array($data) ? count($data) : 0;
                            $summary = $field_count . ' fields filled';
                            // Try to find a name or key field for better summary if possible, but generic is safer for now.
                            ?>
                            <tr>
                                <td>
                                    <?php echo esc_html($sub->id); ?>
                                </td>
                                <td>
                                    <?php echo esc_html($sub->created_at); ?>
                                </td>
                                <td>
                                    <?php echo esc_html($summary); ?>
                                </td>
                                <td>
                                    <?php if (!empty($sub->pdf_url)): ?>
                                        <a href="<?php echo esc_url($sub->pdf_url); ?>" target="_blank" class="button button-small">View
                                            PDF</a>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="4">No submissions found.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    public static function handle_excel_import()
    {
        if (isset($_POST['submit_import']) && isset($_FILES['csv_file'])) {
            if (!current_user_can('manage_options') || !wp_verify_nonce($_POST['ek_survey_import_nonce'], 'ek_survey_import')) {
                wp_die('Permission denied.');
            }

            $survey_id = intval($_POST['survey_id']);
            $file = $_FILES['csv_file'];

            if ($file['error'] !== UPLOAD_ERR_OK) {
                add_settings_error('ek_survey_import', 'file_error', 'File upload failed.', 'error');
                return;
            }

            $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
            if ($extension !== 'csv') {
                add_settings_error('ek_survey_import', 'file_type', 'Invalid file type. Please upload a .csv file.', 'error');
                return;
            }

            try {
                global $wpdb;
                $rows = [];
                $delimiter = ",";
                if (($handle = fopen($file['tmp_name'], "r")) !== FALSE) {
                    // Check for BOM and skip it
                    $bom = fread($handle, 3);
                    if ($bom !== "\xEF\xBB\xBF") {
                        rewind($handle);
                    }

                    // Auto-detect delimiter from first line
                    $firstLine = fgets($handle);
                    if ($firstLine) {
                        $delims = [",", ";", "\t"];
                        $max_count = -1;
                        foreach ($delims as $d) {
                            $count = count(str_getcsv($firstLine, $d));
                            if ($count > $max_count) {
                                $max_count = $count;
                                $delimiter = $d;
                            }
                        }
                        rewind($handle);
                        // Skip BOM again if we rewound
                        $bom = fread($handle, 3);
                        if ($bom !== "\xEF\xBB\xBF") {
                            rewind($handle);
                        }
                    }

                    while (($data = fgetcsv($handle, 0, $delimiter)) !== FALSE) {
                        $rows[] = $data;
                    }
                    fclose($handle);
                }

                if (count($rows) < 2) {
                    throw new Exception('Excel file has no data rows.');
                }

                $headers = $rows[0];
                $data_rows = array_slice($rows, 1);

                // Load survey structure to build a label-to-id map AND detect multi-select fields
                $survey_structure_json = $wpdb->get_var($wpdb->prepare("SELECT structure FROM {$wpdb->prefix}ek_surveys WHERE id = %d", $survey_id));
                $label_to_id_map = [];
                $multi_select_ids = [];
                if ($survey_structure_json) {
                    $struct_data = json_decode($survey_structure_json, true);
                    if (isset($struct_data['sections'])) {
                        foreach ($struct_data['sections'] as $sec) {
                            if (isset($sec['questions'])) {
                                foreach ($sec['questions'] as $q) {
                                    $label_to_id_map[strtolower(trim($q['label']))] = $q['id'];
                                    if (isset($q['type']) && $q['type'] === 'checkbox') {
                                        $multi_select_ids[] = $q['id'];
                                    }
                                }
                            }
                        }
                    }
                }

                // Map Headers to Question IDs
                $header_to_id = [];
                foreach ($headers as $index => $header) {
                    if (empty($header))
                        continue;
                    $header = trim($header);
                    $header_lower = strtolower($header);

                    // 1. Look for ID with dots (e.g., 1.1, 7a.1)
                    if (preg_match('/(\d+[a-z]?\.[\d.]+)/', $header, $matches)) {
                        $header_to_id[$index] = $matches[1];
                    }
                    // 2. Look for ID with underscores (e.g., 1_1, 10_1) -> convert to dots
                    elseif (preg_match('/(\d+[a-z]?_[\d_]+)/', $header, $matches)) {
                        $header_to_id[$index] = str_replace('_', '.', $matches[1]);
                    }
                    // 3. Match by Question Label (fallback)
                    elseif (isset($label_to_id_map[$header_lower])) {
                        $header_to_id[$index] = $label_to_id_map[$header_lower];
                    }
                    // 4. Default: keep the header as is
                    else {
                        $header_to_id[$index] = $header;
                    }
                }

                // Log mapping for debugging
                error_log("EK Import Mapping: " . print_r($header_to_id, true));

                $table_name = $wpdb->prefix . 'ek_submissions';
                $import_count = 0;
                $db_errors = [];

                foreach ($data_rows as $row_index => $row) {
                    $raw_responses = [];
                    $file_paths = [];
                    $created_at = current_time('mysql');
                    $user_id = 0;

                    foreach ($row as $col_index => $value) {
                        if (!isset($header_to_id[$col_index]))
                            continue;
                        $id = $header_to_id[$col_index];

                        if ($id === 'Submission ID')
                            continue;
                        if ($id === 'User ID') {
                            $user_id = intval($value);
                            continue;
                        }
                        if ($id === 'Date') {
                            if ($value) {
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

                            $prefix = ($id == '10.1' || $id == '10.2' || $id == '11.1' || $id == '11.2') ? 'cap_' : 'ek_';
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
                        // Handle External Image URLs (if value is a URL and we expect a file/signature/image)
                        elseif (is_string($value) && (strpos($value, 'http') === 0) && (preg_match('/\.(jpg|jpeg|png|gif|webp)$/i', $value))) {
                            // It's a URL. We should try to treat it as an image for the PDF.
                            $raw_responses[$id] = $value;

                            // To embed in PDF, we ideally want a local path. 
                            // If it's on our own server, we can try to resolve it.
                            $site_url = site_url();
                            if (strpos($value, $site_url) === 0) {
                                $relative_path = str_replace($site_url, '', $value);
                                $local_path = ABSPATH . ltrim($relative_path, '/');
                                if (file_exists($local_path)) {
                                    $file_paths[$id] = [
                                        'path' => $local_path,
                                        'url' => $value,
                                        'name' => 'Local Image'
                                    ];
                                }
                            }
                        }
                        // Handle Checkboxes
                        elseif (in_array($id, $multi_select_ids)) {
                            if (!empty($value)) {
                                if (is_string($value)) {
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

                    // Sanitize all string values to valid UTF-8
                    foreach ($raw_responses as $rk => $rv) {
                        if (is_array($rv)) {
                            foreach ($rv as $ark => $arv) {
                                if (is_string($arv)) {
                                    $raw_responses[$rk][$ark] = @iconv('UTF-8', 'UTF-8//IGNORE', $arv);
                                }
                            }
                        } elseif (is_string($rv)) {
                            $raw_responses[$rk] = @iconv('UTF-8', 'UTF-8//IGNORE', $rv);
                        }
                    }

                    $json_data = json_encode($raw_responses, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE | JSON_PARTIAL_OUTPUT_ON_ERROR);
                    $json_err_code = json_last_error();
                    $json_err_msg = json_last_error_msg();

                    if ($json_data === false || $json_data === null || $json_data === '') {
                        $db_errors[] = "Row " . ($row_index + 1) . ": JSON failed ($json_err_code: $json_err_msg)";
                        continue;
                    }

                    $insert_result = $wpdb->insert(
                        $table_name,
                        array(
                            'survey_id' => $survey_id,
                            'user_id' => $user_id,
                            'response_data' => $json_data,
                            'created_at' => $created_at
                        ),
                        array('%d', '%d', '%s', '%s')
                    );

                    if ($insert_result !== false) {
                        $submission_id = $wpdb->insert_id;
                        $import_count++;

                        // DB verify BEFORE PDF generation
                        $verify_before = '';
                        if ($row_index === 0) {
                            $verify_before = $wpdb->get_var($wpdb->prepare("SELECT response_data FROM $table_name WHERE id = %d", $submission_id));
                        }

                        // Generate PDF
                        if (class_exists('Ek_Survey_Pdf_Generator')) {
                            $pdf_url = Ek_Survey_Pdf_Generator::generate($submission_id, $survey_id, $raw_responses, $file_paths);
                            if ($pdf_url) {
                                $wpdb->update($table_name, ['pdf_url' => $pdf_url], ['id' => $submission_id]);
                            }
                        }

                        // DB verify AFTER PDF generation
                        if ($row_index === 0) {
                            $verify_after = $wpdb->get_var($wpdb->prepare("SELECT response_data FROM $table_name WHERE id = %d", $submission_id));
                        }
                    } else {
                        $db_errors[] = "Row " . ($row_index + 1) . " DB: " . $wpdb->last_error;
                    }

                    // Capture debug info for first row only
                    if ($row_index === 0) {
                        $debug_first_row_keys = array_keys($raw_responses);
                        $debug_first_row_json = $json_data;
                        $debug_first_row_count = count($raw_responses);
                        $debug_json_err = $json_err_code . ': ' . $json_err_msg;
                        $debug_json_len = strlen($json_data);
                        $debug_db_before = isset($verify_before) ? strlen($verify_before) . ' bytes: ' . substr($verify_before, 0, 100) : 'N/A';
                        $debug_db_after = isset($verify_after) ? strlen($verify_after) . ' bytes: ' . substr($verify_after, 0, 100) : 'N/A';
                    }
                }

                // Build detailed feedback message
                $msg = "Import complete: $import_count of " . count($data_rows) . " rows imported.<br>";
                $msg .= "<strong>Delimiter:</strong> " . ($delimiter === "\t" ? "TAB" : "'" . $delimiter . "'") . "<br>";
                $msg .= "<strong>CSV Columns:</strong> " . count($headers) . "<br>";
                $msg .= "<strong>Mapped IDs:</strong> " . implode(', ', $header_to_id) . "<br>";
                if (isset($debug_first_row_count)) {
                    $msg .= "<strong>Fields mapped:</strong> $debug_first_row_count<br>";
                    $msg .= "<strong>Keys:</strong> " . implode(', ', $debug_first_row_keys) . "<br>";
                    $msg .= "<strong>json_encode status:</strong> $debug_json_err (length: $debug_json_len bytes)<br>";
                    $msg .= "<strong>JSON sample:</strong> " . htmlspecialchars(substr($debug_first_row_json, 0, 300)) . "<br>";
                    $msg .= "<strong>DB BEFORE PDF:</strong> " . htmlspecialchars($debug_db_before) . "<br>";
                    $msg .= "<strong>DB AFTER PDF:</strong> " . htmlspecialchars($debug_db_after) . "<br>";
                }
                if (!empty($db_errors)) {
                    $msg .= "<strong>Errors:</strong> " . implode('; ', $db_errors);
                }

                add_settings_error('ek_survey_import', 'import_debug', $msg, $import_count > 0 ? 'updated' : 'error');

            } catch (Exception $e) {
                add_settings_error('ek_survey_import', 'import_error', 'Import failed: ' . $e->getMessage(), 'error');
            }
        }
    }

    public static function handle_csv_export()
    {
        if (isset($_GET['action']) && $_GET['action'] === 'export_csv' && isset($_GET['survey_id'])) {
            // Check permissions
            if (!current_user_can('manage_options')) {
                return;
            }

            global $wpdb;
            $survey_id = intval($_GET['survey_id']);

            // Get Survey Structure (for headers)
            $survey_table = $wpdb->prefix . 'ek_surveys';
            $survey = $wpdb->get_row($wpdb->prepare("SELECT structure, title FROM $survey_table WHERE id = %d", $survey_id));

            if (!$survey) {
                wp_die('Survey not found.');
            }

            $structure = json_decode($survey->structure, true);

            // Build Headers from Questions
            $headers = ['Submission ID', 'Date', 'User ID', 'PDF URL'];
            $question_map = []; // Map ID to Label

            if (isset($structure['sections'])) {
                foreach ($structure['sections'] as $section) {
                    $section_dep = isset($section['dependency']) ? $section['dependency'] : null;
                    if (isset($section['questions'])) {
                        foreach ($section['questions'] as $q) {
                            $headers[] = $q['id'] . ' ' . $q['label'];
                            // Store question dependency or section dependency
                            $q_dep = isset($q['dependency']) ? $q['dependency'] : $section_dep;
                            $question_map[$q['id']] = $q_dep;
                        }
                    }
                }
            }

            // Get Data
            $sub_table = $wpdb->prefix . 'ek_submissions';
            $submissions = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM $sub_table WHERE survey_id = %d ORDER BY created_at DESC",
                $survey_id
            ));

            // Output CSV
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename=survey-' . $survey_id . '-export-' . date('Y-m-d') . '.csv');

            $output = fopen('php://output', 'w');

            // BOM for Excel
            fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

            fputcsv($output, $headers);

            foreach ($submissions as $sub) {
                $data = json_decode($sub->response_data, true);
                $row = [
                    $sub->id,
                    $sub->created_at,
                    $sub->user_id,
                    $sub->pdf_url
                ];

                // Fill in answers based on question map order
                foreach ($question_map as $q_id => $dep) {
                    $answer = isset($data[$q_id]) ? $data[$q_id] : '';

                    if ($dep) {
                        $tQ = $dep['question'];
                        $tV = $dep['value'];
                        $cond = isset($dep['condition']) ? $dep['condition'] : 'equals';
                        $actV = isset($data[$tQ]) ? $data[$tQ] : null;

                        $met = ($cond === 'equals') ? ($actV === $tV) : ($actV !== $tV);
                        if (!$met) {
                            $answer = 'N/A';
                        }
                    }

                    if (is_array($answer)) {
                        $answer = implode(', ', $answer);
                    }
                    $row[] = $answer;
                }

                fputcsv($output, $row);
            }

            fclose($output);
            exit;
        }
    }
}
