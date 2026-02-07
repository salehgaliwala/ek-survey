jQuery(document).ready(function ($) {
    var $form = $('#ek-survey-form');
    var $sections = $('.ek-survey-section');
    var $progressBar = $('.ek-survey-progress-bar');
    var $progressText = $('.ek-survey-progress-text');
    var currentStep = 0;
    var totalSteps = $sections.length;

    function updateProgress() {
        var progress = ((currentStep + 1) / totalSteps) * 100;
        $progressBar.css('width', progress + '%');
        $progressText.text('Step ' + (currentStep + 1) + ' of ' + totalSteps);
    }

    function showSection(index) {
        $sections.removeClass('active').hide();
        $($sections[index]).addClass('active').fadeIn();
        updateProgress();
        updateSignatureNames(); // Update names when section is shown
        // Scroll to top of form
        $('html, body').animate({
            scrollTop: $('.ek-survey-container').offset().top - 100
        }, 500);
    }

    function updateSignatureNames() {
        // Enumerator Name (1.3) -> Signature 8.2
        var enumName = $('input[name="responses[1.3]"]').val();
        if (enumName) {
            $('#ek-sig-name-display-8\\.2').text('Name: ' + enumName);
        }

        // Respondent Name (2.1) -> Signature 8.1
        var respName = $('input[name="responses[2.1]"]').val();
        if (respName) {
            $('#ek-sig-name-display-8\\.1').text('Name: ' + respName);
        }
    }

    // Initialize
    $sections.hide();
    $($sections[0]).show();
    updateProgress();

    // Next Button
    $('.ek-btn-next').on('click', function () {
        if (validateSection(currentStep)) {
            currentStep++;
            showSection(currentStep);
        }
    });

    // Prev Button
    $('.ek-btn-prev').on('click', function () {
        if (currentStep > 0) {
            currentStep--;
            showSection(currentStep);
        }
    });

    // Validation Logic
    function validateSection(index) {
        var $currentSection = $($sections[index]);
        var valid = true;

        $currentSection.find('.ek-form-group.ek-required').each(function () {
            var $group = $(this);
            var isValid = false;
            var errorMsg = 'This field is required';

            // Remove existing error logic
            $group.removeClass('ek-has-error');
            $group.find('.ek-error-message').remove();

            // Check input types
            if ($group.find('input[type="radio"]').length > 0) {
                if ($group.find('input[type="radio"]:checked').length > 0) {
                    isValid = true;
                }
            } else if ($group.find('input[type="checkbox"]').length > 0) {
                if ($group.find('input[type="checkbox"]:checked').length > 0) {
                    isValid = true;
                }
            } else if ($group.find('input[type="file"]').length > 0) {
                // Check if file is selected (for new uploads)
                if ($group.find('input[type="file"]').val()) {
                    isValid = true;
                }
            } else if ($group.find('input.ek-signature-input').length > 0) {
                if ($group.find('input.ek-signature-input').val()) {
                    isValid = true;
                }
            } else {
                // Text, Email, Number, Date, etc.
                var val = $group.find('input').val();
                if (val && val.trim() !== '') {
                    isValid = true;
                }
            }

            if (!isValid) {
                valid = false;
                $group.addClass('ek-has-error');
                $group.append('<div class="ek-error-message" style="color:red; font-size:12px; margin-top:5px;">' + errorMsg + '</div>');
            }
        });

        if (!valid) {
            // Optional: Scroll to first error
            var $firstError = $currentSection.find('.ek-has-error').first();
            if ($firstError.length > 0) {
                $('html, body').animate({
                    scrollTop: $firstError.offset().top - 100
                }, 500);
            }
        }

        return valid;
    }

    // Toggle "Other" text inputs
    $(document).on('change', 'input[type=radio][name^="responses"]', function () {
        var $group = $(this).closest('.ek-options');
        $group.find('.ek-other-input').hide(); // Hide all "others" in this group first

        if ($(this).hasClass('ek-has-other')) {
            $(this).closest('label').next('.ek-other-input').show();
        }
    });

    $(document).on('change', 'input[type=checkbox][name^="responses"]', function () {
        var $otherInput = $(this).closest('label').next('.ek-other-input');
        if ($(this).hasClass('ek-has-other')) {
            if ($(this).is(':checked')) {
                $otherInput.show();
            } else {
                $otherInput.hide();
            }
        }
    });

    // Geolocation Helper (Fixed)
    $(document).on('click touchstart', '.ek-btn-geo', function (e) {
        // Prevent double firing if both events trigger
        if (e.type === 'touchstart') {
            $(this).data('touched', true);
        } else if (e.type === 'click' && $(this).data('touched')) {
            $(this).data('touched', false);
            return;
        }

        var $wrapper = $(this).closest('.ek-geo-wrapper');
        var $input = $wrapper.find('.ek-input');
        var $btn = $(this);

        console.log('Geo button clicked');

        if (navigator.geolocation) {
            $btn.text('Locating...');

            var options = {
                enableHighAccuracy: true,
                timeout: 10000,
                maximumAge: 0
            };

            navigator.geolocation.getCurrentPosition(function (position) {
                var coords = position.coords.latitude + ', ' + position.coords.longitude;
                console.log('Coords found:', coords);
                $input.val(coords);
                $btn.text('Location Found');
            }, function (error) {
                var errorMsg = "Error getting location: ";
                switch (error.code) {
                    case error.PERMISSION_DENIED:
                        errorMsg += "User denied the request (or Origin insecure).";
                        break;
                    case error.POSITION_UNAVAILABLE:
                        errorMsg += "Location information is unavailable.";
                        break;
                    case error.TIMEOUT:
                        errorMsg += "The request to get user location timed out.";
                        break;
                    case error.UNKNOWN_ERROR:
                        errorMsg += "An unknown error occurred.";
                        break;
                }
                // Check for Safari insecure origin issue
                if (window.location.protocol !== 'https:' && window.location.hostname !== 'localhost') {
                    errorMsg += " (Safari requires HTTPS)";
                }

                alert(errorMsg);
                console.error(errorMsg, error);
                $btn.text('Get Location');
            }, options);
        } else {
            alert("Geolocation is not supported by this browser.");
            $btn.text('Get Location');
        }
    });

    // Signature Pad Logic
    var isDrawing = false;
    var lastX = 0;
    var lastY = 0;

    $('.ek-signature-canvas').each(function () {
        var canvas = this;
        var ctx = canvas.getContext('2d');
        var $hiddenInput = $(this).siblings('.ek-signature-input');

        // Set line style
        ctx.strokeStyle = '#000';
        ctx.lineJoin = 'round';
        ctx.lineCap = 'round';
        ctx.lineWidth = 2;

        function getPos(e) {
            var rect = canvas.getBoundingClientRect();
            var x, y;
            if (e.type.includes('touch')) {
                x = e.originalEvent.touches[0].clientX - rect.left;
                y = e.originalEvent.touches[0].clientY - rect.top;
            } else {
                x = e.clientX - rect.left;
                y = e.clientY - rect.top;
            }
            return { x: x, y: y };
        }

        function startDrawing(e) {
            isDrawing = true;
            var pos = getPos(e);
            lastX = pos.x;
            lastY = pos.y;
            e.preventDefault();
        }

        function draw(e) {
            if (!isDrawing) return;
            var pos = getPos(e);
            ctx.beginPath();
            ctx.moveTo(lastX, lastY);
            ctx.lineTo(pos.x, pos.y);
            ctx.stroke();
            lastX = pos.x;
            lastY = pos.y;
            e.preventDefault();
        }

        function stopDrawing() {
            if (isDrawing) {
                isDrawing = false;
                $hiddenInput.val(canvas.toDataURL());
            }
        }

        $(canvas).on('mousedown touchstart', startDrawing);
        $(canvas).on('mousemove touchmove', draw);
        $(canvas).on('mouseup touchend mouseout', stopDrawing);
    });

    $('.ek-btn-clear-sig').on('click', function (e) {
        e.preventDefault();
        var id = $(this).attr('data-id'); // Use attr to ensure string

        var canvas = document.getElementById('canvas-' + id);
        if (canvas) {
            var ctx = canvas.getContext('2d');
            ctx.clearRect(0, 0, canvas.width, canvas.height);
            ctx.beginPath(); // Reset path to prevent old strokes from reappearing
        }

        var input = document.getElementById('field_' + id);
        if (input) {
            $(input).val('');
        }
    });

    // Form Submission
    $form.on('submit', function (e) {
        e.preventDefault();

        // Validate current step (last step)
        if (!validateSection(currentStep)) {
            return;
        }

        var formData = new FormData(this);
        formData.append('nonce', ekSurveyAjax.nonce);

        var $submitBtn = $('.ek-btn-submit');
        $submitBtn.prop('disabled', true).text('Submitting...');

        $.ajax({
            url: ekSurveyAjax.ajaxurl,
            type: 'POST',
            data: formData,
            contentType: false,
            processData: false,
            success: function (response) {
                if (response.success) {
                    $form.html('<div class="ek-success-message"><h3>Thank you!</h3><p>Your survey has been submitted successfully.</p><p><a href="' + response.data.pdf_url + '" class="ek-btn-download" target="_blank">Download PDF Report</a></p></div>');
                } else {
                    console.error('Submission failed:', response);
                    alert('Error: ' + (response.data || 'Unknown error occurred.'));
                    $submitBtn.prop('disabled', false).text('Submit Survey');
                }
            },
            error: function (jqXHR, textStatus, errorThrown) {
                console.error('AJAX Error:', textStatus, errorThrown, jqXHR.responseText);
                alert('Server error occurred. Please try again. details logged to console.');
                $submitBtn.prop('disabled', false).text('Submit Survey');
            }
        });
    });
});
