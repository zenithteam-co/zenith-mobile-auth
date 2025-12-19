jQuery(document).ready(function($) {
    
    // Scoped variables are tricky with multiple instances.
    // We attach data to the container DOM element instead.

    setTimeout(function() {
        $('.zma-phone').prop('disabled', false).first().focus();
    }, 500);

    function showToast(container, msg, type) {
        var x = container.find('.zma-toast');
        x.text(msg).removeClass('error success').addClass('show ' + type);
        setTimeout(function(){ x.removeClass('show'); }, 3000);
    }

    function toEnglish(str) {
        if (!str) return '';
        var persian = ['۰','۱','۲','۳','۴','۵','۶','۷','۸','۹'];
        var arabic  = ['٠','١','٢','٣','٤','٥','٦','٧','٨','٩'];
        for(var i=0; i<10; i++) {
            var regP = new RegExp(persian[i], "g");
            var regA = new RegExp(arabic[i], "g");
            str = str.replace(regP, i).replace(regA, i);
        }
        return str;
    }

    function startTimer(container) {
        let timeLeft = zma_vars.resend_time;
        var resendBtn = container.find('.zma-resend-btn');
        var timerText = container.find('.zma-timer-text');
        var timerSpan = container.find('.zma-timer');

        resendBtn.prop('disabled', true).hide();
        timerText.show();
        timerSpan.text(timeLeft);

        // Clear existing timer if any
        if (container.data('timer')) clearInterval(container.data('timer'));

        var interval = setInterval(function() {
            timeLeft--;
            timerSpan.text(timeLeft);
            if(timeLeft <= 0) {
                clearInterval(interval);
                timerText.hide();
                resendBtn.prop('disabled', false).show();
            }
        }, 1000);
        
        container.data('timer', interval);
    }

    function sendOtp(btn) {
        var container = $(btn).closest('.zma-container');
        var phoneInput = container.find('.zma-phone');
        var phone = toEnglish(phoneInput.val());

        if ($(btn).prop('disabled')) return;

        if(!phone || phone.length < 10) {
            showToast(container, zma_vars.strings.error_phone, 'error');
            return;
        }

        var originalText = $(btn).text();
        $(btn).prop('disabled', true).text(zma_vars.strings.sending);

        $.post(zma_vars.ajax_url, {
            action: 'zma_send_otp',
            phone: phone,
            security: zma_vars.nonce
        }, function(response) {
            if(response.success) {
                container.data('token', response.data.token); // Store token on container
                $(btn).text(originalText); // Restore text
                
                showToast(container, response.data.message, 'success');
                container.find('.zma-phone-display').text(response.data.phone);
                
                container.find('.zma-step-phone').removeClass('active');
                container.find('.zma-step-otp').addClass('active');
                
                container.find('.zma-otp-real').val('').focus();
                
                startTimer(container);
            } else {
                $(btn).prop('disabled', false).text(originalText);
                showToast(container, response.data.message, 'error');
            }
        });
    }

    function verifyOtp(btn) {
        var container = $(btn).closest('.zma-container');
        var otpReal = container.find('.zma-otp-real');
        var otp = toEnglish(otpReal.val());
        var token = container.data('token');

        if(otp.length !== zma_vars.otp_len) {
            showToast(container, zma_vars.strings.error_otp, 'error');
            return;
        }

        if ($(btn).prop('disabled')) return;
        $(btn).prop('disabled', true).text(zma_vars.strings.verifying);

        $.post(zma_vars.ajax_url, {
            action: 'zma_verify_otp',
            otp: otp,
            phone: container.find('.zma-phone-display').text(),
            token: token,
            redirect_to: window.location.href,
            security: zma_vars.nonce
        }, function(response) {
            if(response.success) {
                if ( response.data.new_nonce ) zma_vars.nonce = response.data.new_nonce;

                if ( response.data.require_info ) {
                    container.data('session_token', response.data.session_token); // Store secure session token
                    container.data('redirect', response.data.redirect_to);
                    
                    showToast(container, response.data.message, 'success');
                    container.find('.zma-step-otp').removeClass('active');
                    container.find('.zma-step-info').addClass('active');
                } else {
                    showToast(container, response.data.message, 'success');
                    window.location.href = response.data.redirect_to;
                }
            } else {
                $(btn).prop('disabled', false).text(zma_vars.strings.btn_verify);
                showToast(container, response.data.message, 'error');
                otpReal.val('').trigger('input').focus();
            }
        });
    }

    function saveInfo(btn, isSkip) {
        var container = $(btn).closest('.zma-container');
        var fname = container.find('.zma-fname').val();
        var lname = container.find('.zma-lname').val();
        // Fix for radio buttons: find checked in THIS container
        var gender = container.find('.zma-gender:checked').val();
        
        if ( !isSkip && (!fname || !lname) ) {
            showToast(container, zma_vars.strings.error_name, 'error');
            return;
        }

        var originalText = $(btn).text();
        $(btn).prop('disabled', true).text(zma_vars.strings.saving);

        $.post(zma_vars.ajax_url, {
            action: 'zma_update_user_info',
            fname: fname,
            lname: lname,
            gender: gender,
            session_token: container.data('session_token'),
            redirect_to: container.data('redirect'),
            security: zma_vars.nonce
        }, function(response) {
            if(response.success) {
                showToast(container, response.data.message, 'success');
                window.location.href = response.data.redirect_to;
            } else {
                $(btn).prop('disabled', false).text(originalText);
                showToast(container, 'Error updating profile.', 'error');
            }
        });
    }

    // --- Events (Delegated to handle dynamic elements) ---
    $(document).on('click', '.zma-send-otp-btn', function() { sendOtp(this); });
    $(document).on('click', '.zma-resend-btn', function() { sendOtp(this); });
    $(document).on('click', '.zma-verify-otp-btn', function() { verifyOtp(this); });
    
    $(document).on('click', '.zma-save-info-btn', function() { saveInfo(this, false); });
    $(document).on('click', '.zma-skip-info-btn', function() { saveInfo(this, true); });

    $(document).on('click', '.zma-change-number', function(e){
        e.preventDefault();
        var container = $(this).closest('.zma-container');
        container.find('.zma-step-otp').removeClass('active');
        container.find('.zma-step-phone').addClass('active');
        setTimeout(function(){ container.find('.zma-phone').prop('disabled', false).focus(); }, 100);
    });

    $(document).on('input', '.zma-phone', function() {
        var val = $(this).val();
        var clean = toEnglish(val);
        if (val !== clean) $(this).val(clean);
        if (clean.length === 11) sendOtp( $(this).siblings('.zma-send-otp-btn') ); 
    });

    // Real OTP Input Handler
    $(document).on('input focus blur', '.zma-otp-real', function(e) {
        var val = $(this).val();
        var clean = toEnglish(val);
        var container = $(this).closest('.zma-container');
        
        clean = clean.replace(/\D/g, ''); 
        if (clean.length > zma_vars.otp_len) clean = clean.substring(0, zma_vars.otp_len);
        if (val !== clean) $(this).val(clean);

        var boxes = container.find('.zma-otp-digit');
        boxes.val('').removeClass('active'); 
        
        for (var i = 0; i < zma_vars.otp_len; i++) {
            if(i < clean.length) boxes.eq(i).val(clean[i]);
        }

        if (e.type === 'focus' || e.type === 'input') {
            var activeIndex = clean.length;
            if (activeIndex < zma_vars.otp_len) boxes.eq(activeIndex).addClass('active');
        }

        if (clean.length === zma_vars.otp_len && e.type === 'input') {
            verifyOtp( container.find('.zma-verify-otp-btn') );
        }
    });
});