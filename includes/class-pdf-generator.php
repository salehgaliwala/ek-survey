<?php

require_once dirname(__DIR__) . '/vendor/autoload.php';

use Spipu\Html2Pdf\Html2Pdf;
use Spipu\Html2Pdf\Exception\Html2PdfException;
use Spipu\Html2Pdf\Exception\ExceptionFormatter;

class Ek_Survey_Pdf_Generator
{

    /**
     * Detect what kind of section this is based on question types.
     * Returns: 'photos_consent', 'signatures', or 'standard'
     */
    private static function detect_section_type($section)
    {
        $types = [];
        if (!isset($section['questions']))
            return 'standard';

        foreach ($section['questions'] as $q) {
            $types[] = $q['type'];
        }

        // If section has ONLY signature questions, it's a signature section
        $only_sigs = true;
        foreach ($types as $t) {
            if ($t !== 'signature') {
                $only_sigs = false;
                break;
            }
        }
        if ($only_sigs && count($types) > 0)
            return 'signatures';

        // If section has file/geolocation questions AND radio questions (consent), it's photos_consent
        $has_file = in_array('file', $types) || in_array('geolocation', $types);
        $has_radio = in_array('radio', $types);
        if ($has_file && $has_radio)
            return 'photos_consent';

        return 'standard';
    }

    /**
     * Get an image source from files array or response URL.
     */
    private static function get_image_src($qid, $files, $responses)
    {
        // Try local file first
        if (isset($files[$qid]) && !empty($files[$qid]['path'])) {
            $path = $files[$qid]['path'];
            if (file_exists($path)) {
                return $path;
            }
        }

        // Try response as URL
        if (isset($responses[$qid]) && is_string($responses[$qid]) && strpos($responses[$qid], 'http') === 0) {
            return $responses[$qid];
        }

        return false;
    }

    public static function generate($submission_id, $survey_id, $responses, $files)
    {
        global $wpdb;

        // Fetch Survey Structure to get Labels
        $table_name = $wpdb->prefix . 'ek_surveys';
        $survey = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $survey_id));
        $structure = json_decode($survey->structure, true);

        $is_baseline = ($structure['title'] === 'Baseline Survey');

        // Start HTML Buffering
        ob_start();
        ?>
        <style>
            table {
                width: 100%;
                border-collapse: collapse;
                margin-bottom: 10px;
            }

            th,
            td {
                padding: 5px;
                vertical-align: top;
            }

            .section-title {
                background-color: #f0f0f0;
                font-size: 14pt;
                font-weight: bold;
                padding: 5px;
                margin-top: 10px;
                margin-bottom: 10px;
            }

            .question-label {
                font-weight: bold;
                font-size: 11pt;
                margin-bottom: 2px;
            }

            .answer-text {
                font-size: 11pt;
                margin-bottom: 5px;
            }

            .photo-grid {
                width: 190mm;
                border-collapse: collapse;
            }

            .photo-grid td {
                width: 60mm;
                text-align: center;
                vertical-align: top;
                border: none;
                padding: 2mm;
            }

            .signature-grid {
                width: 190mm;
                border-collapse: collapse;
            }

            .signature-grid td {
                width: 90mm;
                text-align: center;
                vertical-align: top;
                border: none;
                padding: 2mm;
            }

            .photo-label {
                font-weight: bold;
                font-size: 10pt;
                margin-top: 2mm;
            }

            .photo-sub-label {
                font-style: italic;
                font-size: 9pt;
            }

            .img-container {
                text-align: center;
                border: 1px solid #ddd;
                padding: 2mm;
                margin-top: 2mm;
                background-color: #fcfcfc;
            }
        </style>

        <page backtop="7mm" backbottom="7mm" backleft="10mm" backright="10mm">
            <h1 style="text-align: center;"><?php echo esc_html($structure['title']); ?></h1>

            <?php if ($is_baseline): ?>
                <div
                    style="margin-bottom: 20px; padding: 10px; background: #f9f9f9; border: 1px solid #ccc; font-size: 10pt; line-height: 1.4;">
                    <strong>Baseline Household Survey (Pre-intervention)</strong><br>
                    GS Project ID - GS12963<br>
                    Full version (Carbon + SDG modules).<br>
                    For Gold Standard Methodology 429 (Safe Drinking Water Supply).<br><br>
                    <strong>Enumerator guidance:</strong> Ask questions exactly as written. Tick one box unless it says "tick all
                    that apply". If the respondent does not know, tick "Don't know / Not sure". Record numbers only where requested.
                    For monitoring, "before the project" refers to the situation before the borehole was installed/rehabilitated.
                </div>
            <?php else: ?>
                <div
                    style="margin-bottom: 20px; padding: 10px; background: #f9f9f9; border: 1px solid #ccc; font-size: 10pt; line-height: 1.4;">
                    <strong>Monitoring Household Survey (Year y)</strong><br>
                    GS Project ID - GS12963<br>
                    Full version (Carbon + SDG modules).<br>
                    For Gold Standard Methodology 429 (Safe Drinking Water Supply).<br><br>
                    <strong>Enumerator guidance:</strong> Ask questions exactly as written. Tick one box unless it says "tick all
                    that apply". If the respondent does not know, tick "Don't know / Not sure". Record numbers only where requested.
                    For monitoring, "before the project" refers to the situation before the borehole was installed/rehabilitated.
                </div>
            <?php endif; ?>

            <?php foreach ($structure['sections'] as $section):
                // Check section-level dependency
                $section_dep_met = true;
                if (!empty($section['dependency'])) {
                    $dep = $section['dependency'];
                    $tQ = $dep['question'];
                    $tV = $dep['value'];
                    $cond = isset($dep['condition']) ? $dep['condition'] : 'equals';
                    $actV = isset($responses[$tQ]) ? $responses[$tQ] : null;
                    if ($cond === 'equals') {
                        $section_dep_met = ($actV === $tV);
                    } else {
                        $section_dep_met = ($actV !== $tV);
                    }
                }

                // Detect section type dynamically
                $section_type = self::detect_section_type($section);
                ?>
                <div class="section-title"><?php echo esc_html($section['title']); ?></div>

                <?php if ($section_type === 'photos_consent'): ?>
                    <?php
                    // Separate questions by type
                    $text_questions = [];
                    $photo_questions = [];
                    $geo_questions = [];

                    foreach ($section['questions'] as $q) {
                        if ($q['type'] === 'file') {
                            $photo_questions[] = $q;
                        } elseif ($q['type'] === 'geolocation') {
                            $geo_questions[] = $q;
                        } else {
                            $text_questions[] = $q;
                        }
                    }

                    // Render text/radio questions first (consent, etc.)
                    foreach ($text_questions as $q):
                        $qid = $q['id'];
                        $answer = isset($responses[$qid]) ? $responses[$qid] : '';
                        ?>
                        <div class="question-wrapper">
                            <div class="question-label"><?php echo esc_html($qid . ' ' . $q['label']); ?></div>
                            <div class="answer-text">Answer: <?php echo esc_html($answer ?: 'N/A'); ?></div>
                        </div>
                    <?php endforeach; ?>

                    <?php if (!empty($photo_questions)): ?>
                        <!-- Photos in one row -->
                        <table cellspacing="0" cellpadding="0"
                            style="width: 190mm; border-collapse: collapse; margin-top: 3mm;margin-left:-30mm">
                            <tr>
                                <?php foreach ($photo_questions as $q):
                                    $qid = $q['id'];
                                    $img_src = self::get_image_src($qid, $files, $responses);
                                    ?>
                                    <td style="width: 50mm; vertical-align: top; padding: 1mm; text-align: center;">
                                        <div style="font-weight: bold; font-size: 8pt; margin-bottom: 1mm;">
                                            <?php echo esc_html($qid . ' ' . trim($q['label'])); ?>
                                        </div>
                                        <div style="text-align: center;">
                                            <?php if ($img_src): ?>
                                                <img src="<?php echo esc_attr($img_src); ?>" style="width: 40mm;">
                                            <?php else: ?>
                                                <div style="height: 20mm; line-height: 20mm; background: #eee; font-size: 10pt;">[No Image]</div>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                <?php endforeach; ?>
                            </tr>
                        </table>
                    <?php endif; ?>

                    <?php
                    // Render GPS/geolocation questions
                    foreach ($geo_questions as $q):
                        $qid = $q['id'];
                        $answer = isset($responses[$qid]) ? $responses[$qid] : '';
                        ?>
                        <div class="question-wrapper" style="margin-top:10px;">
                            <div class="question-label"><?php echo esc_html($qid . ' ' . $q['label']); ?></div>
                            <div class="answer-text">Answer: <?php echo esc_html($answer ?: 'N/A'); ?></div>
                        </div>
                    <?php endforeach; ?>

                <?php elseif ($section_type === 'signatures'): ?>
                    <!-- Signatures in one row -->
                    <table cellspacing="0" cellpadding="0"
                        style="width: 190mm; border-collapse: collapse; margin-top: 3mm;margin-left:-45mm">
                        <tr>
                            <?php foreach ($section['questions'] as $q):
                                $q_id = $q['id'];
                                $sig_src = self::get_image_src($q_id, $files, $responses);

                                // Try to find name for the signer
                                $name_val = '';
                                $q_label_lower = strtolower($q['label']);
                                if (strpos($q_label_lower, 'respondent') !== false) {
                                    $name_val = isset($responses['3.1']) ? $responses['3.1'] : '';
                                } elseif (strpos($q_label_lower, 'enumerator') !== false) {
                                    $name_val = isset($responses['2.2']) ? $responses['2.2'] : '';
                                }
                                ?>
                                <td style="width: 90mm; vertical-align: top; padding: 2mm; text-align: center;">
                                    <div style="font-weight: bold; font-size: 10pt; margin-bottom: 2mm;">
                                        <?php echo esc_html($q['label']); ?>
                                    </div>
                                    <div style="text-align: center; padding: 2mm;">
                                        <?php if ($sig_src): ?>
                                            <img src="<?php echo esc_attr($sig_src); ?>" style="width: 80mm;">
                                        <?php else: ?>
                                            <div style="padding: 5mm; font-size: 10pt;">[No Signature]</div>
                                        <?php endif; ?>
                                    </div>
                                    <div style="margin-top: 2mm; font-size: 10pt;">
                                        <strong><?php echo esc_html($name_val); ?></strong>
                                    </div>
                                </td>
                            <?php endforeach; ?>
                        </tr>
                    </table>

                <?php else: ?>
                    <!-- Standard Questions -->
                    <?php foreach ($section['questions'] as $q):
                        $q_id = $q['id'];
                        $label = $q['label'];
                        $answer = (isset($responses[$q_id]) && $section_dep_met) ? $responses[$q_id] : 'N/A';
                        if (is_array($answer))
                            $answer = implode(', ', $answer);

                        // Check if this specific question is an image/file type
                        $is_file_type = (isset($q['type']) && ($q['type'] === 'file' || $q['type'] === 'signature'));
                        $img_src = self::get_image_src($q_id, $files, $responses);

                        // Also check if the answer itself is an image URL
                        $is_url_img = (is_string($answer) && strpos($answer, 'http') === 0 && preg_match('/\.(jpg|jpeg|png|gif|webp)$/i', $answer));
                        ?>
                        <div class="question-wrapper">
                            <div class="question-label"><?php echo esc_html($q_id . ' ' . $label); ?></div>
                            <?php if ($img_src): ?>
                                <img src="<?php echo esc_attr($img_src); ?>" style="width: 60mm; margin-top: 2mm;">
                            <?php elseif ($is_url_img): ?>
                                <img src="<?php echo esc_attr($answer); ?>" style="width: 60mm; margin-top: 2mm;">
                            <?php elseif ($is_file_type && !$img_src): ?>
                                <div class="answer-text" style="background: #eee; padding: 10px;">[No Image/Signature]</div>
                            <?php else: ?>
                                <div class="answer-text">Answer: <?php echo esc_html($answer ?: 'N/A'); ?></div>
                            <?php endif; ?>
                        </div>
                        <br>
                    <?php endforeach; ?>
                <?php endif; ?>

            <?php endforeach; ?>
        </page>
        <?php
        $content = ob_get_clean();

        // Debug HTML
        $upload_dir = wp_upload_dir();
        $debug_path = $upload_dir['basedir'] . '/ek-surveys/' . date('Y/m') . '/debug_' . $submission_id . '.html';
        file_put_contents($debug_path, $content);

        // Generate PDF
        try {
            $html2pdf = new Html2Pdf('P', 'A4', 'en');
            $html2pdf->pdf->SetDisplayMode('fullpage');
            $html2pdf->writeHTML($content);

            // Output
            $upload_dir = wp_upload_dir();
            $base_dir = $upload_dir['basedir'] . '/ek-surveys/' . date('Y/m');
            $base_url = $upload_dir['baseurl'] . '/ek-surveys/' . date('Y/m');

            if (!file_exists($base_dir)) {
                wp_mkdir_p($base_dir);
            }

            $filename = 'submission_' . $submission_id . '.pdf';
            $file_path = $base_dir . '/' . $filename;
            $html2pdf->output($file_path, 'F'); // Save to file

            return $base_url . '/' . $filename;

        } catch (Html2PdfException $e) {
            $html2pdf->clean();
            $formatter = new ExceptionFormatter($e);
            echo $formatter->getHtmlMessage();
            return false;
        }
    }
}
