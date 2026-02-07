<?php

require_once dirname(__DIR__) . '/vendor/autoload.php';

use Spipu\Html2Pdf\Html2Pdf;
use Spipu\Html2Pdf\Exception\Html2PdfException;
use Spipu\Html2Pdf\Exception\ExceptionFormatter;

class Ek_Survey_Pdf_Generator
{

    public static function generate($submission_id, $survey_id, $responses, $files)
    {
        global $wpdb;

        // Fetch Survey Structure to get Labels
        $table_name = $wpdb->prefix . 'ek_surveys';
        $survey = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $survey_id));
        $structure = json_decode($survey->structure, true);

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
                table-layout: fixed;
                margin-left: -100px;
                width: 100%;
            }

            .photo-grid td {
                width: 33%;
                text-align: center;
                vertical-align: top;
                border: none;
                padding: 5px;
                /* Reduced padding */
            }

            .signature-grid {
                table-layout: fixed;
                margin-left: -150px;
                width: 100%;
            }

            .signature-grid td {
                width: 50%;
                text-align: center;
                vertical-align: top;
                border: none;
                padding: 5px;
            }

            .photo-label {
                font-weight: bold;
                font-size: 10pt;
                margin-top: 5px;
            }

            .photo-sub-label {
                font-style: italic;
                font-size: 9pt;
            }

            .img-container {
                /* Fixed height to ensure alignment */
                /* height: 200px; Remove fixed height to let it shrink to content if user wants it "just below" */
                /* But for alignment row-wise, min-height is better, or just rely on the cell vertical-align top */
                text-align: center;
                border: 1px solid #ddd;
                padding: 5px;
                margin-top: 2px;
                /* Minimal margin */
            }

            img {
                max-width: 100%;
                height: auto;
            }
        </style>

        <page backtop="7mm" backbottom="7mm" backleft="10mm" backright="10mm">
            <h1 style="text-align: center;"><?php echo esc_html($structure['title']); ?></h1>

            <?php foreach ($structure['sections'] as $section): ?>
                <div class="section-title"><?php echo esc_html($section['title']); ?></div>

                <?php if ($section['id'] === 'section_gps_photos'): ?>
                    <?php
                    $photos = [];
                    $gps_question = null;

                    foreach ($section['questions'] as $q) {
                        if (strpos($q['id'], '7.') === 0) {
                            $photos[] = $q;
                        } else {
                            $gps_question = $q;
                        }
                    }

                    // Render GPS
                    if ($gps_question) {
                        $q_id = $gps_question['id'];
                        $label = $gps_question['label'];
                        $answer = isset($responses[$q_id]) ? $responses[$q_id] : '';
                        ?>
                        <div class="question-wrapper">
                            <div class="question-label"><?php echo esc_html($q_id . ' ' . $label); ?></div>
                            <div class="answer-text">Answer: <?php echo esc_html($answer ?: 'N/A'); ?></div>
                        </div>
                    <?php } ?>

                    <!-- Photos Grid -->
                    <?php if (!empty($photos)): ?>
                        <table class="photo-grid" cellspacing="0" cellpadding="0" style="width: 100%; border-collapse: collapse;">
                            <tr>
                                <?php foreach ($photos as $q):
                                    $q_id = $q['id'];
                                    $label = $q['label'];
                                    $sub_label = '';
                                    if (stripos($label, '(not mandatory)') !== false) {
                                        $label = str_ireplace('(not mandatory)', '', $label);
                                        $sub_label = '(not mandatory)';
                                    }

                                    $image_path = '';
                                    if (isset($files[$q_id])) {
                                        $image_path = $files[$q_id]['path'];
                                    }
                                    ?>
                                    <td style="width: 33%; vertical-align: top; padding: 5px;">
                                        <div class="question-label" style="font-weight: bold; margin-bottom: 2px;">
                                            <?php echo esc_html($q_id . ' ' . trim($label)); ?>
                                        </div>
                                        <?php if ($sub_label): ?>
                                            <div class="photo-sub-label" style="font-style: italic; font-size: 9pt; margin-bottom: 2px;">
                                                <?php echo esc_html($sub_label); ?>
                                            </div>
                                        <?php endif; ?>

                                        <!-- Removed <br> here -->

                                        <div class="img-container">
                                            <?php if (file_exists($image_path)): ?>
                                                <img src="<?php echo esc_attr($image_path); ?>"
                                                    style="width: 95%; height: auto; max-height: 200px;">
                                            <?php else: ?>
                                                <div style="height: 100px; line-height: 100px; background: #eee;">[No Image]</div>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                <?php endforeach; ?>
                            </tr>
                        </table>
                    <?php endif; ?>

                <?php elseif ($section['id'] === 'section_8'): ?>
                    <!-- Signatures -->
                    <table class="signature-grid" cellspacing="0" cellpadding="0" style="width: 100%; border-collapse: collapse;">
                        <tr>
                            <?php foreach ($section['questions'] as $q):
                                $q_id = $q['id'];
                                $image_path = '';
                                if (isset($files[$q_id])) {
                                    $image_path = $files[$q_id]['path'];
                                }

                                $name_val = '';
                                if ($q_id === '8.1' && isset($responses['2.1'])) {
                                    $name_val = $responses['2.1'];
                                } elseif ($q_id === '8.2' && isset($responses['1.3'])) {
                                    $name_val = $responses['1.3'];
                                }
                                ?>
                                <td style="width: 50%; vertical-align: top; padding: 5px;">
                                    <div class="question-label"><?php echo esc_html($q['label']); ?></div>
                                    <!-- Removed <br> here -->
                                    <div class="img-container" style="min-height: 80px;">
                                        <?php if (file_exists($image_path)): ?>
                                            <img src="<?php echo esc_attr($image_path); ?>" style="max-height: 100px; max-width: 100%;">
                                        <?php else: ?>
                                            <div style="padding-top: 30px;">[No Signature]</div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="answer-text" style="margin-top: 5px;"><strong><?php echo esc_html($name_val); ?></strong>
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
                        $answer = isset($responses[$q_id]) ? $responses[$q_id] : '';
                        if (is_array($answer))
                            $answer = implode(', ', $answer);

                        $image_path = '';
                        if (isset($files[$q_id])) {
                            $image_path = $files[$q_id]['path'];
                        }
                        ?>
                        <div class="question-wrapper">
                            <div class="question-label"><?php echo esc_html($q_id . ' ' . $label); ?></div>
                            <?php if ($image_path && file_exists($image_path)): ?>
                                <img src="<?php echo esc_attr($image_path); ?>" style="max-width: 200px; margin-top: 2px;">
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
