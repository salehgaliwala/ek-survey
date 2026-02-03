# WordPress Survey Plugin Walkthrough

## Overview
I have created the `EkSurvey` plugin which allows you to run template-based surveys. It comes pre-loaded with the "Project Borehole Monitoring" survey.

## Features
- **Multi-step Form**: Sections are paginated with Next/Back buttons.
- **Progress Bar**: Shows completion status.
- **Database Storage**: Stores survey definitions (`wp_ek_surveys`) and responses (`wp_ek_submissions`).
- **PDF Generation**: Automatically generates a PDF report upon submission using TCPDF.
- **Geolocation**: Field type to capture GPS coordinates.
- **File Uploads**: Supports photos and signatures.

## Installation
1. The plugin files are located in `c:/working/ek`.
2. I have already run `composer install` to download the PDF library.
3. Activate the plugin in WordPress.
4. Upon activation, it will create tables and insert the "Monitoring" survey.

## Usage
To display the survey, use the shortcode on any page:

```
[ek_survey id="1"]
```

## How to Verify
1.  **Activate Plugin**: Go to Plugins and activate "EK Survey".
2.  **Create Page**: Create a new page and add `[ek_survey id="1"]`.
3.  **Test Form**:
    *   Navigate through the 8 sections.
    *   **Geolocation**: In Section 1, click "Get Location". It should change to "Locating..." then "Location Found" and fill the input.
        > **Note**: On Safari (iOS/macOS), check the console for logs if it fails. It strictly requires **HTTPS** and may need valid SSL certificate. If using a local IP (e.g., 192.168.x.x), Safari often blocks Geolocation. Use `localhost` or a real HTTPS server.
    *   **Other Options**: Find a question with "Other (specify)" (e.g., in Section 5 or 6). Select it and verify a text box appears. Type something in it.
    *   **Camera**: In Section 7, click "Choose File" (on mobile this should offer Camera option). Select or capture an image.
    *   **Signature**: In Section 8, use your mouse or touch screen to draw a signature on the canvas.
4.  **Submit**: Click "Submit Survey".
5.  **Check Result**: You should see a success message with a "Download PDF Report" link. The PDF should contain your signature image.

6.  **Admin Dashboard**:
    *   Go to **WP Admin > EK Survey**.
    *   **Filter**: Select "Monitoring Project Borehole" from the dropdown.
    *   **View**: Verify your submission appears in the table with ID, Date, and PDF Link.
    *   **Export**: Click "Export to CSV". Open the file and verify all columns (including "Other", Geolocation, Photos, etc.) are present and correct.

## Database Schema
Two tables are used:
1.  `wp_ek_surveys`: Stores the JSON structure of surveys.
2.  `wp_ek_submissions`: Stores the JSON responses and link to the PDF.

## Future Customization
To add a new survey, you simply insert a new row into `wp_ek_surveys` with your desired JSON structure. No code changes are needed for new forms.
