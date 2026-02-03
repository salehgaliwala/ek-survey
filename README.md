# EK Survey Plugin

A modern, template-based WordPress survey plugin designed for field data collection.

## Features
- **Multi-step Forms**: Organized into sections with progress tracking.
- **Geolocation**: Capture GPS coordinates with ease (requires HTTPS).
- **Signature Capture**: Touch-friendly signature pads for respondents and enumerators.
- **Photos**: Integrated file inputs for capturing photos (supports native camera).
- **PDF Generation**: Automatically generates a PDF report for each submission.
- **Admin Dashboard**:
    - View all submissions.
    - Filter by survey.
    - Export data to CSV.

## Installation
1.  Upload the `ek-survey` folder to `wp-content/plugins/`.
2.  Activate the plugin through the 'Plugins' menu in WordPress.
3.  The database tables will be created automatically.

## Usage
1.  Add the shortcode `[ek_survey id="1"]` to any page or post.
2.  To create new surveys, you can currently use the `seed_data` method or manually insert JSON structure into `wp_ek_surveys`.

## Requirements
- WordPress 5.0+
- PHP 7.2+
- TCPDF (included in vendor)

## License
Proprietary / Custom
