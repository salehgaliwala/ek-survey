<?php

class Ek_Survey_Activator
{

    public static function activate()
    {
        self::create_tables();
        self::seed_data();
    }

    private static function create_tables()
    {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        $sql_surveys = "CREATE TABLE {$wpdb->prefix}ek_surveys (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			title varchar(255) NOT NULL,
			structure longtext NOT NULL,
			created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
			PRIMARY KEY  (id)
		) $charset_collate;";

        $sql_submissions = "CREATE TABLE {$wpdb->prefix}ek_submissions (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			survey_id bigint(20) NOT NULL,
			user_id bigint(20),
			response_data longtext NOT NULL,
			pdf_url varchar(255),
			created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
			PRIMARY KEY  (id)
		) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql_surveys);
        dbDelta($sql_submissions);
    }

    private static function seed_data()
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'ek_surveys';

        // Check if survey already exists
        // For development/testing, we might want to update it if it exists
        // valid check: if title is 'Monitoring Project Borehole' update it

        $files_to_seed = ['baseline_survey.json', 'monitoring_survey_new.json'];

        foreach ($files_to_seed as $file) {
            $path = plugin_dir_path(__FILE__) . '../' . $file;
            if (file_exists($path)) {
                $json_structure = file_get_contents($path);

                if (!$json_structure)
                    continue;

                $survey_data = json_decode($json_structure, true);
                $title = isset($survey_data['title']) ? $survey_data['title'] : 'Survey';

                $existing = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE title = %s", $title));

                if ($existing) {
                    $wpdb->update(
                        $table_name,
                        array('structure' => $json_structure),
                        array('id' => $existing->id)
                    );
                } else {
                    $wpdb->insert(
                        $table_name,
                        array(
                            'title' => $title,
                            'structure' => $json_structure,
                            'created_at' => current_time('mysql')
                        )
                    );
                }
            }
        }
    }
}
