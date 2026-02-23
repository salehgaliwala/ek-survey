jQuery(document).ready(function ($) {
    var $form = $('#ek-survey-form');
    var $sections = $('.ek-survey-section');
    var $progressBar = $('.ek-survey-progress-bar');
    var $progressText = $('.ek-survey-progress-text');
    var currentStep = 0;
    var totalSteps = $sections.length; // Initial count, might change with hidden sections? No, progress bar just skips.

    // Helper to find question ID from input name "responses[1.2]" -> "1.2"
    function getQuestionId(inputName) {
        var match = inputName.match(/responses\[(.*?)\]/);
        return match ? match[1] : null;
    }

    // Dependency Logic
    function checkDependencies() {
        // Check Sections
        $sections.each(function () {
            var $sec = $(this);
            var depData = $sec.data('dependency');
            if (depData) {
                if (isDependencyMet(depData)) {
                    $sec.removeClass('ek-hidden-section');
                } else {
                    $sec.addClass('ek-hidden-section');
                }
            }
        });

        // Check Fields within active sections (or all fields? All fields is safer)
        $('.ek-form-group').each(function () {
            var $group = $(this);
            var depData = $group.data('dependency');
            if (depData) {
                if (isDependencyMet(depData)) {
                    $group.slideDown();
                    $group.removeClass('ek-hidden-field');
                    // If required class exists, ensure input has required prop or validation logic handles it
                    if ($group.hasClass('ek-required-placeholder')) {
                        $group.addClass('ek-required');
                    }
                } else {
                    $group.slideUp();
                    $group.addClass('ek-hidden-field');
                    // Temporarily remove required status to avoid validation blocking
                    if ($group.hasClass('ek-required')) {
                        $group.removeClass('ek-required').addClass('ek-required-placeholder');
                    }
                }
            }
        });
    }

    function isDependencyMet(dep) {
        // dependency format: { question: "1.2", value: "Yes", condition: "equals" (default) }
        var targetQ = dep.question;
        var targetVal = dep.value;
        var condition = dep.condition || 'equals';

        // Find input for target question
        // Name format: responses[ID]
        var $inputs = $('[name="responses[' + targetQ + ']"]');
        var actualVal = null;

        if ($inputs.is(':radio')) {
            if ($inputs.filter(':checked').length > 0) {
                actualVal = $inputs.filter(':checked').val();
            }
        } else if ($inputs.is(':checkbox')) {
            // Checkbox logic might need array handling, but for now specific value check
            // If "value" is present in the checked list
            var checked = [];
            $inputs.filter(':checked').each(function () { checked.push($(this).val()); });
            // Simple check: is targetVal in checked?
            if (checked.includes(targetVal)) {
                actualVal = targetVal;
            }
        } else {
            actualVal = $inputs.val();
        }

        if (condition === 'equals') {
            return actualVal === targetVal;
        } else if (condition === 'not_equals') {
            return actualVal !== targetVal;
        }

        return false;
    }

    // Trigger dependency check on change
    $(document).on('change', 'input, select, textarea', function () {
        checkDependencies();
    });

    // Initial check
    checkDependencies();

    function updateProgress() {
        // Recalculate based on visible sections? 
        // Or just keep simple step count.
        // For accurate progress, we should count visible sections.
        var $visibleSections = $sections.not('.ek-hidden-section');
        var visibleTotal = $visibleSections.length;
        var visibleIndex = $visibleSections.index($sections.eq(currentStep));

        if (visibleIndex === -1) return; // Should not happen if currentStep is handled right

        var progress = ((visibleIndex + 1) / visibleTotal) * 100;
        $progressBar.css('width', progress + '%');
        $progressText.text('Step ' + (visibleIndex + 1) + ' of ' + visibleTotal);
    }

    function showSection(index) {
        // Handle skipped sections
        // If target section is hidden, move to next/prev depending on direction?
        // Navigation buttons handle the index, but we need to ensure we land on a visible one.

        // This function assumes 'index' is valid or we correct it.
        // But preventing recursion/loops is important.

        if (index < 0) return;
        if (index >= $sections.length) return;

        var $target = $($sections[index]);

        // If hidden, find next visible
        if ($target.hasClass('ek-hidden-section')) {
            // We need to know context (Next or Prev). 
            // Ideally the caller determines the valid index.
            // But let's check basic visibility here.
            // Warning: This logic is better handled in Next/Prev click handlers.
        }

        $sections.removeClass('active').hide();
        $target.addClass('active').fadeIn();
        updateProgress();
        updateSignatureNames();

        $('html, body').animate({
            scrollTop: $('.ek-survey-container').offset().top - 100
        }, 500);
    }

    function updateSignatureNames() {
        var enumName = $('input[name="responses[1.3]"]'); // Wait, ID changed? 2.2 in new json?
        // Old JSON: 1.3 Enum Name. New JSON: 2.2 Enumerator ID.
        // We generally look for Name inputs.
        // Let's make this generic or based on specific IDs if they are stable.

        // New Survey 2.2 -> Enumerator
        // New Survey 3.1 -> Respondent
        // Signatures: 10.1 (Resp), 10.2 (Enum)

        var enumVal = $('input[name="responses[2.2]"]:checked').val(); // It's a radio now!
        if (enumVal) {
            $('#ek-sig-name-display-10\\.2').text('Name: ' + enumVal);
        }

        var respName = $('input[name="responses[3.1]"]').val();
        if (respName) {
            $('#ek-sig-name-display-10\\.1').text('Name: ' + respName);
        }
    }

    // Initialize
    $sections.hide(); // Hide all first
    checkDependencies(); // Apply logic which adds hidden classes

    // Find first visible section
    var startIndex = 0;
    while (startIndex < $sections.length && $($sections[startIndex]).hasClass('ek-hidden-section')) {
        startIndex++;
    }
    currentStep = startIndex;
    $($sections[currentStep]).show();
    updateProgress();

    // Next Button
    $('.ek-btn-next').on('click', function () {
        if (validateSection(currentStep)) {
            var nextIndex = currentStep + 1;
            // Find next visible section
            while (nextIndex < $sections.length && $($sections[nextIndex]).hasClass('ek-hidden-section')) {
                nextIndex++;
            }

            if (nextIndex < $sections.length) {
                currentStep = nextIndex;
                showSection(currentStep);
            } else {
                // Submit? No, Submit button is in the last section's HTML.
            }
        }
    });

    // Prev Button
    $('.ek-btn-prev').on('click', function () {
        var prevIndex = currentStep - 1;
        // Find prev visible section
        while (prevIndex >= 0 && $($sections[prevIndex]).hasClass('ek-hidden-section')) {
            prevIndex--;
        }

        if (prevIndex >= 0) {
            currentStep = prevIndex;
            showSection(currentStep);
        }
    });

    // Validation
    function validateSection(index) {
        var $currentSection = $($sections[index]);
        var valid = true;

        // Only validate visible fields
        $currentSection.find('.ek-form-group.ek-required').not('.ek-hidden-field').each(function () {
            var $group = $(this);
            var isValid = false;
            var errorMsg = 'This field is required';

            $group.removeClass('ek-has-error');
            $group.find('.ek-error-message').remove();

            if ($group.find('input[type="radio"]').length > 0) {
                if ($group.find('input[type="radio"]:checked').length > 0) isValid = true;
            } else if ($group.find('input[type="checkbox"]').length > 0) {
                if ($group.find('input[type="checkbox"]:checked').length > 0) isValid = true;
            } else if ($group.find('input[type="file"]').length > 0) {
                if ($group.find('input[type="file"]').val()) isValid = true;
            } else if ($group.find('input.ek-signature-input').length > 0) {
                if ($group.find('input.ek-signature-input').val()) isValid = true;
            } else if ($group.find('input[type="date"]').length > 0) {
                if ($group.find('input').val()) isValid = true;
            } else {
                var val = $group.find('input').val();
                if (val && val.trim() !== '') isValid = true;
            }

            if (!isValid) {
                valid = false;
                $group.addClass('ek-has-error');
                $group.append('<div class="ek-error-message" style="color:red; font-size:12px; margin-top:5px;">' + errorMsg + '</div>');
            }
        });

        if (!valid) {
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
        $group.find('.ek-other-input').hide();

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

    // Geolocation
    $(document).on('click touchstart', '.ek-btn-geo', function (e) {
        if (e.type === 'touchstart') {
            $(this).data('touched', true);
        } else if (e.type === 'click' && $(this).data('touched')) {
            $(this).data('touched', false);
            return;
        }

        var $wrapper = $(this).closest('.ek-geo-wrapper');
        var $input = $wrapper.find('.ek-input');
        var $btn = $(this);

        if (navigator.geolocation) {
            $btn.text('Locating...');
            var options = { enableHighAccuracy: true, timeout: 10000, maximumAge: 0 };
            navigator.geolocation.getCurrentPosition(function (position) {
                var coords = position.coords.latitude + ', ' + position.coords.longitude;
                $input.val(coords);
                $btn.text('Location Found');
            }, function (error) {
                $btn.text('Get Location');
                alert("Error getting location.");
            }, options);
        } else {
            alert("Geolocation not supported.");
        }
    });

    // Signature Pad
    var isDrawing = false;
    var lastX = 0;
    var lastY = 0;

    $('.ek-signature-canvas').each(function () {
        var canvas = this;
        var ctx = canvas.getContext('2d');
        var $hiddenInput = $(this).siblings('.ek-signature-input');

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
        var id = $(this).attr('data-id');
        var canvas = document.getElementById('canvas-' + id);
        if (canvas) {
            var ctx = canvas.getContext('2d');
            ctx.clearRect(0, 0, canvas.width, canvas.height);
            ctx.beginPath();
        }
        var input = document.getElementById('field_' + id);
        if (input) $(input).val('');
    });

    // Sync Submissions
    async function syncSubmissions() {
        if (!navigator.onLine) return;

        try {
            const submissions = await ekOffline.getSubmissions();
            if (submissions.length === 0) return;

            console.log('Syncing ' + submissions.length + ' submissions...');

            for (const submission of submissions) {
                const formData = new FormData();
                formData.append('nonce', ekSurveyAjax.nonce);

                // Reconstruct FormData from stored object
                for (const key in submission) {
                    if (key === 'id' || key === '_timestamp') continue;

                    const value = submission[key];
                    if (Array.isArray(value)) {
                        value.forEach(v => formData.append(key, v));
                    } else {
                        formData.append(key, value);
                    }
                }

                try {
                    await $.ajax({
                        url: ekSurveyAjax.ajaxurl,
                        type: 'POST',
                        data: formData,
                        contentType: false,
                        processData: false
                    });

                    // If successful, delete from DB
                    await ekOffline.deleteSubmission(submission.id);
                    console.log('Synced submission ' + submission.id);
                } catch (e) {
                    console.error('Failed to sync submission ' + submission.id, e);
                }
            }

            alert('Offline submissions have been synced!');
        } catch (e) {
            console.error('Error syncing submissions:', e);
        }
    }

    // Check for pending submissions on load
    if (window.ekOffline) {
        syncSubmissions();
    }

    window.addEventListener('online', syncSubmissions);

    // Form Submission
    $form.on('submit', async function (e) {
        e.preventDefault();
        if (!validateSection(currentStep)) return;

        var formData = new FormData(this);
        formData.append('nonce', ekSurveyAjax.nonce);
        var $submitBtn = $('.ek-btn-submit');
        $submitBtn.prop('disabled', true).text('Submitting...');

        // Check Online Status
        if (!navigator.onLine) {
            if (window.ekOffline) {
                try {
                    await ekOffline.saveSubmission(formData);
                    $form.html('<div class="ek-success-message"><h3>Saved Offline!</h3><p>Your survey has been saved locally. It will be submitted automatically when you are back online.</p><button onclick="window.location.reload()" class="ek-btn-download">Start New Survey</button></div>');
                } catch (err) {
                    alert('Error saving offline: ' + err);
                    $submitBtn.prop('disabled', false).text('Submit Survey');
                }
            } else {
                alert('Offline storage not supported.');
                $submitBtn.prop('disabled', false).text('Submit Survey');
            }
            return;
        }

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
                    alert('Error: ' + (response.data || 'Unknown error.'));
                    $submitBtn.prop('disabled', false).text('Submit Survey');
                }
            },
            error: function () {
                alert('Server error occurred.');
                $submitBtn.prop('disabled', false).text('Submit Survey');
            }
        });
    });
});
