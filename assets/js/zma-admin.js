jQuery(document).ready(function($) {
    
    // 1. Tab Switching
    $('.zma-nav li').on('click', function() {
        var tabId = $(this).data('tab');
        $('.zma-nav li').removeClass('active');
        $('.zma-tab-content').removeClass('active');
        $(this).addClass('active');
        $('#tab-' + tabId).addClass('active');
    });

    // 2. Color Pickers
    $('.zma-color-picker').wpColorPicker({
        change: function(event, ui) {
            updateSimplePreview($(event.target), ui.color.toString());
        }
    });

    // 3. Simple Inputs (Color, Width etc)
    $('.zma-live-css').on('input change keyup', function() {
        updateSimplePreview($(this), $(this).val());
    });

    function updateSimplePreview(el, val) {
        var target = el.data('css-target');
        var prop = el.data('css-prop');
        if(target && prop) $(target).css(prop, val);
    }

    // 4. Dimension Controls (Elementor-like Linked Logic)
    
    // Toggle Link Button
    $('.zma-link-btn').on('click', function() {
        var btn = $(this);
        var wrapper = btn.closest('.zma-dim-control');
        var inputHidden = wrapper.find('.zma-linked-val');
        
        if (btn.hasClass('active')) {
            // Unlink
            btn.removeClass('active');
            wrapper.attr('data-linked', '0');
            inputHidden.val('0');
        } else {
            // Link
            btn.addClass('active');
            wrapper.attr('data-linked', '1');
            inputHidden.val('1');
            
            // Sync values to the first input's value
            var firstVal = wrapper.find('.zma-live-dim').first().val();
            wrapper.find('.zma-live-dim').val(firstVal).trigger('input');
        }
    });

    // Handle Dimension Input
    $('.zma-live-dim').on('input', function() {
        var $this = $(this);
        var wrapper = $this.closest('.zma-dim-control');
        var isLinked = wrapper.attr('data-linked') === '1';
        
        if (isLinked) {
            // Update siblings
            wrapper.find('.zma-live-dim').not($this).val($this.val());
        }

        // Update Preview (Construct Shorthand: top right bottom left)
        var top = wrapper.find('[data-side="top"]').val() || '0px';
        var right = wrapper.find('[data-side="right"]').val() || '0px';
        var bottom = wrapper.find('[data-side="bottom"]').val() || '0px';
        var left = wrapper.find('[data-side="left"]').val() || '0px';

        var shorthand = `${top} ${right} ${bottom} ${left}`;
        var target = $this.data('css-target');
        var prop = $this.data('css-prop'); // e.g., 'padding' or 'border-radius'
        
        $(target).css(prop, shorthand);
    });
});
