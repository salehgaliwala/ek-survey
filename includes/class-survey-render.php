<?php

class Ek_Survey_Render
{

    public static function render_shortcode($atts)
    {
        $atts = shortcode_atts(array(
            'id' => 0,
        ), $atts);

        if (empty($atts['id'])) {
            return '<p>Error: No survey ID specified.</p>';
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'ek_surveys';
        $survey = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $atts['id']));

        if (!$survey) {
            return '<p>Error: Survey not found.</p>';
        }

        $structure = json_decode($survey->structure, true);

        if (!$structure || empty($structure['sections'])) {
            return '<p>Error: Invalid survey structure.</p>';
        }

        return self::generate_form_html($survey->id, $structure);
    }

    private static function generate_form_html($survey_id, $structure)
    {
        $output = '<div class="ek-survey-container" id="ek-survey-' . esc_attr($survey_id) . '">';
        $output .= '<h2 class="ek-survey-title">' . esc_html($structure['title']) . '</h2>';

        // Progress Bar
        $sections = $structure['sections'];
        $total_sections = count($sections);
        $output .= '<div class="ek-survey-progress">';
        $output .= '<div class="ek-survey-progress-bar" style="width: 0%;"></div>';
        $output .= '<span class="ek-survey-progress-text">Step 1 of ' . $total_sections . '</span>';
        $output .= '</div>';

        $output .= '<form id="ek-survey-form" enctype="multipart/form-data">';
        $output .= '<input type="hidden" name="action" value="ek_survey_submit">';
        $output .= '<input type="hidden" name="survey_id" value="' . esc_attr($survey_id) . '">';

        foreach ($sections as $index => $section) {
            $active_class = ($index === 0) ? 'active' : '';
            $output .= '<div class="ek-survey-section ' . $active_class . '" data-step="' . ($index + 1) . '">';
            $output .= '<h3 class="ek-survey-section-title">' . esc_html($section['title']) . '</h3>';

            foreach ($section['questions'] as $question) {
                $output .= self::render_field($question);
            }

            $output .= '<div class="ek-survey-nav">';
            if ($index > 0) {
                $output .= '<button type="button" class="ek-btn-prev">Back</button>';
            }
            if ($index < $total_sections - 1) {
                $output .= '<button type="button" class="ek-btn-next">Continue</button>';
            } else {
                $output .= '<button type="submit" class="ek-btn-submit">Submit Survey</button>';
            }
            $output .= '</div>'; // .ek-survey-nav

            $output .= '</div>'; // .ek-survey-section
        }

        $output .= '</form>';
        $output .= '</div>'; // .ek-survey-container

        return $output;
    }

    private static function render_field($question)
    {
        $id = esc_attr($question['id']);
        $label = esc_html($question['label']);
        $type = $question['type'];
        $desc = isset($question['description']) ? '<p class="description">' . esc_html($question['description']) . '</p>' : '';

        $is_optional = stripos($label, 'optional') !== false || stripos($label, 'not mandatory') !== false;
        $required_class = $is_optional ? '' : ' ek-required';

        $html = '<div class="ek-form-group field-type-' . esc_attr($type) . $required_class . '">';
        $req_span = $is_optional ? '' : ' <span class="ek-req-star" style="color:red">*</span>';
        $html .= '<label class="ek-label" for="field_' . $id . '">' . $question['id'] . ' ' . $label . $req_span . '</label>';

        switch ($type) {
            case 'text':
            case 'email':
            case 'date':
            case 'number':
                $html .= '<input type="' . esc_attr($type) . '" name="responses[' . $id . ']" id="field_' . $id . '" class="ek-input">';
                break;

            case 'geolocation':
                $html .= '<div class="ek-geo-wrapper">';
                $html .= '<input type="text" name="responses[' . $id . ']" id="field_' . $id . '" class="ek-input" placeholder="Coordinates will appear here">';
                $html .= '<button type="button" class="ek-btn-geo">Get Location</button>';
                $html .= '</div>';
                break;

            case 'radio':
                if (!empty($question['options'])) {
                    $html .= '<div class="ek-options">';
                    foreach ($question['options'] as $idx => $opt) {
                        $opt_val = esc_attr($opt);
                        $is_other = stripos($opt, 'specify') !== false;
                        $html .= '<label class="ek-option">';
                        $html .= '<input type="radio" name="responses[' . $id . ']" value="' . $opt_val . '" ' . ($is_other ? 'class="ek-has-other"' : '') . '> ' . esc_html($opt);
                        $html .= '</label>';
                        if ($is_other) {
                            $html .= '<input type="text" name="responses[' . $id . '_other]" class="ek-other-input" style="display:none; margin-top:5px; margin-left:25px;" placeholder="Please specify...">';
                        }
                    }
                    $html .= '</div>';
                }
                break;

            case 'checkbox':
                if (!empty($question['options'])) {
                    $html .= '<div class="ek-options">';
                    foreach ($question['options'] as $idx => $opt) {
                        $opt_val = esc_attr($opt);
                        $is_other = stripos($opt, 'specify') !== false;
                        $html .= '<label class="ek-option">';
                        $html .= '<input type="checkbox" name="responses[' . $id . '][]" value="' . $opt_val . '" ' . ($is_other ? 'class="ek-has-other"' : '') . '> ' . esc_html($opt);
                        $html .= '</label>';
                        if ($is_other) {
                            $html .= '<input type="text" name="responses[' . $id . '_other]" class="ek-other-input" style="display:none; margin-top:5px; margin-left:25px;" placeholder="Please specify...">';
                        }
                    }
                    $html .= '</div>';
                }
                break;

            case 'file':
                $html .= '<div class="ek-file-wrapper">';
                $html .= '<input type="file" name="files_' . $id . '" id="field_' . $id . '" class="ek-input-file" accept="image/*,.pdf" capture="environment">';
                $html .= '</div>';
                break;

            case 'signature':
                $html .= '<div class="ek-signature-pad-wrapper" id="sig-pad-' . $id . '">';
                $html .= '<canvas class="ek-signature-canvas" width="400" height="200" id="canvas-' . $id . '" style="border:1px solid #ccc; touch-action: none; background: #fff;"></canvas>';
                $html .= '<input type="hidden" name="responses[' . $id . ']" id="field_' . $id . '" class="ek-signature-input">';
                $html .= '<div class="ek-signature-controls">';
                $html .= '<button type="button" class="ek-btn-clear-sig small" data-id="' . $id . '">Clear</button>';
                $html .= '</div>';
                $html .= '<div class="ek-signature-name-display" id="ek-sig-name-display-' . $id . '" style="margin-top: 10px; font-weight: bold; font-family: sans-serif;"></div>';
                $html .= '</div>';
                break;

            default:
                $html .= '<input type="text" name="responses[' . $id . ']" class="ek-input">';
                break;
        }

        $html .= $desc;
        $html .= '</div>';

        return $html;
    }
}
