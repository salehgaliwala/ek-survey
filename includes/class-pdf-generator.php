<?php

use TCPDF;

class Ek_Survey_Pdf_Generator
{

    public static function generate($submission_id, $survey_id, $responses, $files)
    {
        global $wpdb;

        // Fetch Survey Structure to get Labels
        $table_name = $wpdb->prefix . 'ek_surveys';
        $survey = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $survey_id));
        $structure = json_decode($survey->structure, true);

        // Initialize TCPDF
        $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

        // Set document information
        $pdf->SetCreator(PDF_CREATOR);
        $pdf->SetAuthor('Ek Survey Plugin');
        $pdf->SetTitle('Survey Submission #' . $submission_id);

        // Remove default header/footer
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);

        // Add a page
        $pdf->AddPage();

        // Title
        $pdf->SetFont('helvetica', 'B', 20);
        $pdf->Cell(0, 15, $structure['title'], 0, 1, 'C');

        $pdf->SetFont('helvetica', '', 10);
        $pdf->Cell(0, 10, 'Submission #' . $submission_id . ' - Date: ' . current_time('mysql'), 0, 1, 'C');
        $pdf->Ln(5);

        // Iterate Sections
        foreach ($structure['sections'] as $section) {
            // Section Header
            $pdf->SetFont('helvetica', 'B', 14);
            $pdf->SetFillColor(240, 240, 240);
            $pdf->Cell(0, 10, $section['title'], 0, 1, 'L', 1);
            $pdf->Ln(2);

            $pdf->SetFont('helvetica', '', 11);

            foreach ($section['questions'] as $question) {
                $q_id = $question['id'];
                $label = $question['label'];
                $answer = isset($responses[$q_id]) ? $responses[$q_id] : '';

                // Format Answer
                if (is_array($answer)) {
                    $answer_text = implode(', ', $answer);
                } else {
                    $answer_text = $answer;
                }

                // Check if this question has a file upload
                // The key in $responses and $question['id'] match usually
                // But we also passed specific $files array for paths
                $is_image = false;
                $image_path = '';

                // Fix for ID matching if needed (files are stored with q_id key)
                // Check if files array contains this ID
                // Note: in form handler we stripped 'files_' prefix
                if (isset($files[$q_id])) {
                    $is_image = true;
                    $image_path = $files[$q_id]['path'];
                }

                // Check if answer points to an image URL (fallback)
                if (!$is_image && (strpos($answer_text, '.jpg') !== false || strpos($answer_text, '.png') !== false)) {
                    // It's likely an image URL, but we need local path for TCPDF usually
                    // For simplicity rely on $files passed from handler
                }

                // Print Question
                $pdf->SetFont('helvetica', 'B', 11);
                $pdf->MultiCell(0, 7, $q_id . ' ' . $label, 0, 'L');

                // Print Answer
                $pdf->SetFont('helvetica', '', 11);
                if ($is_image && file_exists($image_path)) {
                    $pdf->Ln(2);
                    // Calculates height automatically while width is 50.
                    // We need to account for the height of the image to move cursor down.
                    // TCPDF Image() usually moves cursor to bottom if align is not set, but let's be safe.
                    // We can use GetY() or just hardcode a safe spacing if images are standard.
                    // Let's assume max height around 50 for signature/photo thumb.
                    $pdf->Image($image_path, '', '', 50, 0, '', '', 'T', false, 300, '', false, false, 0, false, false, false);
                    $pdf->Ln(45); // Move down enough for the image height
                } else {
                    $pdf->MultiCell(0, 7, 'Answer: ' . ($answer_text ?: 'N/A'), 0, 'L');
                }

                $pdf->Ln(3);
            }
            $pdf->Ln(5);
        }

        // Save PDF
        $upload_dir = wp_upload_dir();
        $base_dir = $upload_dir['basedir'] . '/ek-surveys/' . date('Y/m');
        $base_url = $upload_dir['baseurl'] . '/ek-surveys/' . date('Y/m');

        if (!file_exists($base_dir)) {
            wp_mkdir_p($base_dir);
        }

        $filename = 'submission_' . $submission_id . '.pdf';
        $file_path = $base_dir . '/' . $filename;
        $pdf->Output($file_path, 'F');

        return $base_url . '/' . $filename;
    }
}
