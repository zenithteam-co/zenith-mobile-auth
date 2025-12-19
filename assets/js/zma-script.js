jQuery(document).ready(function($) {
    
    let timerInterval;
    let currentSessionToken = '';
    let finalRedirectUrl = ''; 

    setTimeout(function() {
        $('#zma-phone').prop('disabled', false).focus();
    }, 500);

    function showToast(msg, type) {
        var x = $('#zma-toast');
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

    function startTimer() {
        let timeLeft = zma_vars.resend_time;
        $('#zma-resend-btn').prop('disabled', true).hide();
        $('#zma-timer-text').show();
        $('#zma-timer').text(timeLeft);

        clearInterval(timerInterval);
        timerInterval = setInterval(function() {
            timeLeft--;
            $('#zma-timer').text(timeLeft);
            if(timeLeft <= 0) {
                clearInterval(timerInterval);
                $('#zma-timer-text').hide();
                $('#zma-resend-btn').prop('disabled', false).show();
            }
        }, 1000);
    }

    function sendOtp(btnClicked) {
        var phone = $('#zma-phone').val();
        phone = toEnglish(phone); 
        var btn = $(btnClicked);
        if (btn.length === 0) btn = $('#zma-send-otp-btn'); 
        if (btn.prop('disabled')) return;
        if(!phone || phone.length < 10) {
            showToast(zma_vars.strings.error_phone, 'error');
            return;
        }
        var originalText = btn.text();
        btn.prop('disabled', true).text(zma_vars.strings.sending);

        $.post(zma_vars.ajax_url, {
            action: 'zma_send_otp',
            phone: phone,
            security: zma_vars.nonce
        }, function(response) {
            if(response.success) {
                currentSessionToken = response.data.token;
                btn.text(originalText);
                showToast(response.data.message, 'success');
                $('#zma-phone-display').text(response.data.phone);
                $('#zma-step-phone').removeClass('active');
                $('#zma-step-otp').addClass('active');
                $('#zma-otp-real').val('').focus();
                startTimer();
            } else {
                btn.prop('disabled', false).text(originalText);
                showToast(response.data.message, 'error');
            }
        });
    }

    function verifyOtp() {
        var otp = toEnglish($('#zma-otp-real').val());
        if(otp.length !== zma_vars.otp_len) {
            showToast(zma_vars.strings.error_otp, 'error');
            return;
        }
        var btn = $('#zma-verify-otp-btn');
        if (btn.prop('disabled')) return;
        btn.prop('disabled', true).text(zma_vars.strings.verifying);

        $.post(zma_vars.ajax_url, {
            action: 'zma_verify_otp',
            otp: otp,
            phone: $('#zma-phone-display').text(),
            token: currentSessionToken,
            redirect_to: window.location.href,
            security: zma_vars.nonce
        }, function(response) {
            if(response.success) {
                // UPDATE NONCE with new one from server
                if ( response.data.new_nonce ) {
                    zma_vars.nonce = response.data.new_nonce;
                }

                if ( response.data.require_info ) {
                    finalRedirectUrl = response.data.redirect_to;
                    showToast(response.data.message, 'success');
                    $('#zma-step-otp').removeClass('active');
                    $('#zma-step-info').addClass('active');
                } else {
                    showToast(response.data.message, 'success');
                    window.location.href = response.data.redirect_to;
                }
            } else {
                btn.prop('disabled', false).text(zma_vars.strings.btn_verify);
                showToast(response.data.message, 'error');
                $('#zma-otp-real').val('').trigger('input');
                $('#zma-otp-real').focus();
            }
        });
    }

    function saveInfo(isSkip = false) {
        var fname = $('#zma-fname').val();
        var lname = $('#zma-lname').val();
        var gender = $('input[name="zma_gender"]:checked').val(); // Get Radio Value

        if ( !isSkip && (!fname || !lname) ) {
            showToast(zma_vars.strings.error_name, 'error');
            return;
        }

        var btn = isSkip ? $('#zma-skip-info-btn') : $('#zma-save-info-btn');
        var originalText = btn.text();
        btn.prop('disabled', true).text(zma_vars.strings.saving);

        $.post(zma_vars.ajax_url, {
            action: 'zma_update_user_info',
            fname: fname,
            lname: lname,
            gender: gender,
            redirect_to: finalRedirectUrl,
            security: zma_vars.nonce // This now uses the NEW valid nonce
        }, function(response) {
            if(response.success) {
                showToast(response.data.message, 'success');
                window.location.href = response.data.redirect_to;
            } else {
                btn.prop('disabled', false).text(originalText);
                showToast('Error updating profile.', 'error');
            }
        });
    }

    // Events
    $('#zma-send-otp-btn').click(function() { sendOtp(this); });
    $('#zma-resend-btn').click(function() { sendOtp(this); });
    $('#zma-verify-otp-btn').click(verifyOtp);
    
    $('#zma-save-info-btn').click(function() { saveInfo(false); });
    $('#zma-skip-info-btn').click(function() { saveInfo(true); });

    $('#zma-change-number').click(function(e){
        e.preventDefault();
        $('#zma-step-otp').removeClass('active');
        $('#zma-step-phone').addClass('active');
        setTimeout(function(){ $('#zma-phone').prop('disabled', false).focus(); }, 100);
    });

    $('#zma-phone').on('input', function() {
        var val = $(this).val();
        var clean = toEnglish(val);
        if (val !== clean) $(this).val(clean);
        if (clean.length === 11) sendOtp($('#zma-send-otp-btn')); 
    });

    $('#zma-otp-real').on('input focus blur', function(e) {
        var val = $(this).val();
        var clean = toEnglish(val);
        clean = clean.replace(/\D/g, ''); 
        if (clean.length > zma_vars.otp_len) clean = clean.substring(0, zma_vars.otp_len);
        if (val !== clean) $(this).val(clean);

        $('.zma-otp-digit').val('').removeClass('active'); 
        for (var i = 0; i < zma_vars.otp_len; i++) {
            if(i < clean.length) $('.zma-otp-digit').eq(i).val(clean[i]);
        }

        if (e.type === 'focus' || e.type === 'input') {
            var activeIndex = clean.length;
            if (activeIndex < zma_vars.otp_len) $('.zma-otp-digit').eq(activeIndex).addClass('active');
        }

        if (clean.length === zma_vars.otp_len && e.type === 'input') {
            verifyOtp();
        }
    });
});
