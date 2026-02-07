<?php

class Ek_Survey_Form_Handler
{

    public static function handle_submission()
    {
        check_ajax_referer('ek_survey_nonce', 'nonce');
        error_log('EK Survey: Submission started.');

        $survey_id = intval($_POST['survey_id']);
        $responses = isset($_POST['responses']) ? $_POST['responses'] : array();

        // Merge "Other" values
        foreach ($responses as $key => $val) {
            if (strpos($key, '_other') !== false) {
                $base_key = str_replace('_other', '', $key);
                if (isset($responses[$base_key]) && !empty($val)) {
                    if (is_array($responses[$base_key])) {
                        $responses[$base_key][] = 'Other: ' . $val;
                    } else {
                        $responses[$base_key] .= ' (Other: ' . $val . ')';
                    }
                }
                unset($responses[$key]); // Cleanup
            }
        }
        // Handle Files
        $file_paths = array();
        if (!empty($_FILES)) {
            $upload_dir = wp_upload_dir();
            $base_dir = $upload_dir['basedir'] . '/ek-surveys/' . date('Y/m');
            $base_url = $upload_dir['baseurl'] . '/ek-surveys/' . date('Y/m');

            if (!file_exists($base_dir)) {
                wp_mkdir_p($base_dir);
            }

            foreach ($_FILES as $key => $file) {
                if ($file['error'] === UPLOAD_ERR_OK) {
                    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
                    $filename = uniqid('ek_') . '.' . $ext;
                    $target_path = $base_dir . '/' . $filename;
                    $target_url = $base_url . '/' . $filename;

                    if (move_uploaded_file($file['tmp_name'], $target_path)) {
                        // Extract Question ID (files_7.1 -> 7.1)
                        $q_id = str_replace('files_', '', $key);
                        $q_id = str_replace('_', '.', $q_id); // restore decimal if PHP ruined it

                        // Store both path (for PDF) and URL (for viewing) if needed
                        $file_paths[$q_id] = [
                            'path' => $target_path,
                            'url' => $target_url,
                            'name' => $file['name']
                        ];

                        // Add to responses so it's saved in JSON
                        $responses[$q_id] = $target_url;
                    } else {
                        error_log('EK Survey: Failed to move uploaded file: ' . $file['name']);
                    }
                } else {
                    error_log('EK Survey: File upload error code: ' . $file['error']);
                }
            }
        }

        // Handle Signatures (Base64)
        $upload_dir = wp_upload_dir();
        $base_dir = $upload_dir['basedir'] . '/ek-surveys/' . date('Y/m');
        $base_url = $upload_dir['baseurl'] . '/ek-surveys/' . date('Y/m');

        if (!file_exists($base_dir)) {
            wp_mkdir_p($base_dir);
        }

        foreach ($responses as $q_id => $val) {
            if (!is_string($val)) {
                continue;
            }

            $is_png = strpos($val, 'data:image/png;base64,') === 0;
            $is_jpg = strpos($val, 'data:image/jpeg;base64,') === 0;

            if ($is_png || $is_jpg) {
                $ext = $is_png ? 'png' : 'jpg';
                $prefix = $is_png ? 'data:image/png;base64,' : 'data:image/jpeg;base64,';

                $image_data = str_replace($prefix, '', $val);
                $image_data = str_replace(' ', '+', $image_data);
                $decoded = base64_decode($image_data);

                $filename = uniqid('cap_') . '.' . $ext;
                $target_path = $base_dir . '/' . $filename;
                $target_url = $base_url . '/' . $filename;

                if (file_put_contents($target_path, $decoded)) {
                    $responses[$q_id] = $target_url;
                    $file_paths[$q_id] = [
                        'path' => $target_path,
                        'url' => $target_url,
                        'name' => 'Captured Image'
                    ];
                } else {
                    error_log('EK Survey: Failed to save signature image.');
                }
            }
        }

        // Save to DB
        global $wpdb;
        $table_name = $wpdb->prefix . 'ek_submissions';

        $data = array(
            'survey_id' => $survey_id,
            'user_id' => get_current_user_id(),
            'response_data' => json_encode($responses),
            'created_at' => current_time('mysql')
        );

        $result = $wpdb->insert($table_name, $data);
        if ($result === false) {
            error_log('EK Survey: DB Insert Error: ' . $wpdb->last_error);
            wp_send_json_error('Database error occurred.');
        }
        $submission_id = $wpdb->insert_id;

        // Generate PDF
        $pdf_url = Ek_Survey_Pdf_Generator::generate($submission_id, $survey_id, $responses, $file_paths);

        // Update DB with PDF URL
        $wpdb->update(
            $table_name,
            array('pdf_url' => $pdf_url),
            array('id' => $submission_id)
        );

        wp_send_json_success(array(
            'message' => 'Survey submitted successfully.',
            'pdf_url' => $pdf_url
        ));
    }
}
