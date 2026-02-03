<?php

class Ek_Survey_Admin
{

    public static function init()
    {
        add_action('admin_menu', array(__CLASS__, 'add_admin_menu'));
        add_action('admin_init', array(__CLASS__, 'handle_csv_export'));
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
                            $summary = count($data) . ' fields filled';
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
                    if (isset($section['questions'])) {
                        foreach ($section['questions'] as $q) {
                            $headers[] = $q['id'] . ' ' . $q['label']; // "1.1 Survey Purpose"
                            $question_map[$q['id']] = $q['id']; // Tracking IDs
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
                foreach ($question_map as $q_id) {
                    $answer = isset($data[$q_id]) ? $data[$q_id] : '';
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
