/**
 * ReactifyWP Help System JavaScript
 */

(function ($) {
    'use strict';

    const ReactifyWPHelp = {
        /**
         * Initialize help system
         */
        init() {
            this.bindEvents();
            this.initTooltips();
            this.maybeStartTour();
        },

        /**
         * Bind event handlers
         */
        bindEvents() {
            $(document).on('click', '#reactifywp-help-button', this.openHelpModal.bind(this));
            $(document).on('click', '#reactifywp-help-close', this.closeHelpModal.bind(this));
            $(document).on('click', '.reactifywp-help-nav a', this.switchHelpTopic.bind(this));
            $(document).on('click', '#reactifywp-tour-skip', this.skipTour.bind(this));
            $(document).on('click', '#reactifywp-tour-next', this.nextTourStep.bind(this));
            $(document).on('click', '#reactifywp-tour-prev', this.prevTourStep.bind(this));
            $(document).on('click', '.reactifywp-modal', this.closeModalOnOverlay.bind(this));
            $(document).on('keydown', this.handleKeydown.bind(this));
        },

        /**
         * Initialize tooltips
         */
        initTooltips() {
            // Add tooltip triggers to form elements
            Object.keys(reactifyWPHelp.tooltips).forEach(key => {
                const element = $(`[data-tooltip="${key}"], #reactifywp-${key}`);
                if (element.length) {
                    this.addTooltip(element, reactifyWPHelp.tooltips[key]);
                }
            });

            // Add help icons to form fields
            $('.form-table th').each(function() {
                const $th = $(this);
                const $label = $th.find('label');
                const fieldId = $label.attr('for');
                
                if (fieldId && reactifyWPHelp.tooltips[fieldId.replace('reactifywp-', '')]) {
                    const helpIcon = $('<span class="reactifywp-help-icon dashicons dashicons-editor-help" title="Click for help"></span>');
                    $label.append(helpIcon);
                    
                    helpIcon.on('click', (e) => {
                        e.preventDefault();
                        this.showTooltip(helpIcon, reactifyWPHelp.tooltips[fieldId.replace('reactifywp-', '')]);
                    });
                }
            }.bind(this));
        },

        /**
         * Add tooltip to element
         */
        addTooltip(element, text) {
            element.attr('title', text);
            
            // Enhanced tooltip with custom styling
            element.on('mouseenter', function(e) {
                const tooltip = $(`<div class="reactifywp-tooltip">${text}</div>`);
                $('body').append(tooltip);
                
                const offset = $(this).offset();
                tooltip.css({
                    top: offset.top - tooltip.outerHeight() - 10,
                    left: offset.left + ($(this).outerWidth() / 2) - (tooltip.outerWidth() / 2)
                });
                
                tooltip.fadeIn(200);
            });
            
            element.on('mouseleave', function() {
                $('.reactifywp-tooltip').fadeOut(200, function() {
                    $(this).remove();
                });
            });
        },

        /**
         * Show tooltip
         */
        showTooltip(trigger, text) {
            // Remove existing tooltips
            $('.reactifywp-tooltip-popup').remove();
            
            const tooltip = $(`
                <div class="reactifywp-tooltip-popup">
                    <div class="reactifywp-tooltip-content">
                        ${text}
                        <button type="button" class="reactifywp-tooltip-close">×</button>
                    </div>
                </div>
            `);
            
            $('body').append(tooltip);
            
            const offset = trigger.offset();
            tooltip.css({
                top: offset.top + trigger.outerHeight() + 5,
                left: offset.left
            });
            
            tooltip.fadeIn(200);
            
            // Close tooltip
            tooltip.find('.reactifywp-tooltip-close').on('click', () => {
                tooltip.fadeOut(200, () => tooltip.remove());
            });
            
            // Auto-close after 5 seconds
            setTimeout(() => {
                tooltip.fadeOut(200, () => tooltip.remove());
            }, 5000);
        },

        /**
         * Maybe start guided tour
         */
        maybeStartTour() {
            if (reactifyWPHelp.showTour) {
                setTimeout(() => {
                    this.startTour();
                }, 1000);
            }
        },

        /**
         * Start guided tour
         */
        startTour() {
            this.currentTourStep = 0;
            this.tourSteps = reactifyWPHelp.tourSteps;
            this.showTourStep();
        },

        /**
         * Show current tour step
         */
        showTourStep() {
            if (this.currentTourStep >= this.tourSteps.length) {
                this.finishTour();
                return;
            }

            const step = this.tourSteps[this.currentTourStep];
            const target = $(step.target);

            if (target.length === 0) {
                this.nextTourStep();
                return;
            }

            // Update tour content
            $('#reactifywp-tour-title').text(step.title);
            $('#reactifywp-tour-text').text(step.content);
            $('#reactifywp-tour-step').text(this.currentTourStep + 1);
            $('#reactifywp-tour-total').text(this.tourSteps.length);

            // Show/hide navigation buttons
            $('#reactifywp-tour-prev').toggle(this.currentTourStep > 0);
            $('#reactifywp-tour-next').text(
                this.currentTourStep === this.tourSteps.length - 1 
                    ? reactifyWPHelp.strings.finishTour 
                    : reactifyWPHelp.strings.nextStep
            );

            // Position tooltip
            this.positionTourTooltip(target, step.position);

            // Highlight target element
            this.highlightElement(target);

            // Show tour overlay
            $('#reactifywp-tour-overlay').fadeIn(300);
        },

        /**
         * Position tour tooltip
         */
        positionTourTooltip(target, position) {
            const tooltip = $('.reactifywp-tour-tooltip');
            const overlay = $('#reactifywp-tour-overlay');
            const offset = target.offset();
            const targetWidth = target.outerWidth();
            const targetHeight = target.outerHeight();
            const tooltipWidth = tooltip.outerWidth();
            const tooltipHeight = tooltip.outerHeight();

            let top, left;

            switch (position) {
                case 'top':
                    top = offset.top - tooltipHeight - 20;
                    left = offset.left + (targetWidth / 2) - (tooltipWidth / 2);
                    break;
                case 'bottom':
                    top = offset.top + targetHeight + 20;
                    left = offset.left + (targetWidth / 2) - (tooltipWidth / 2);
                    break;
                case 'left':
                    top = offset.top + (targetHeight / 2) - (tooltipHeight / 2);
                    left = offset.left - tooltipWidth - 20;
                    break;
                case 'right':
                    top = offset.top + (targetHeight / 2) - (tooltipHeight / 2);
                    left = offset.left + targetWidth + 20;
                    break;
                default:
                    top = offset.top + targetHeight + 20;
                    left = offset.left + (targetWidth / 2) - (tooltipWidth / 2);
            }

            // Ensure tooltip stays within viewport
            const windowWidth = $(window).width();
            const windowHeight = $(window).height();
            const scrollTop = $(window).scrollTop();

            if (left < 10) left = 10;
            if (left + tooltipWidth > windowWidth - 10) left = windowWidth - tooltipWidth - 10;
            if (top < scrollTop + 10) top = scrollTop + 10;
            if (top + tooltipHeight > scrollTop + windowHeight - 10) {
                top = scrollTop + windowHeight - tooltipHeight - 10;
            }

            tooltip.css({ top, left });

            // Scroll to target if needed
            $('html, body').animate({
                scrollTop: offset.top - 100
            }, 300);
        },

        /**
         * Highlight target element
         */
        highlightElement(target) {
            // Remove previous highlights
            $('.reactifywp-tour-highlight').removeClass('reactifywp-tour-highlight');
            
            // Add highlight to current target
            target.addClass('reactifywp-tour-highlight');
        },

        /**
         * Next tour step
         */
        nextTourStep() {
            this.currentTourStep++;
            this.showTourStep();
        },

        /**
         * Previous tour step
         */
        prevTourStep() {
            if (this.currentTourStep > 0) {
                this.currentTourStep--;
                this.showTourStep();
            }
        },

        /**
         * Skip tour
         */
        skipTour() {
            this.finishTour();
            this.dismissTour();
        },

        /**
         * Finish tour
         */
        finishTour() {
            $('#reactifywp-tour-overlay').fadeOut(300);
            $('.reactifywp-tour-highlight').removeClass('reactifywp-tour-highlight');
            this.dismissTour();
        },

        /**
         * Dismiss tour permanently
         */
        dismissTour() {
            $.ajax({
                url: reactifyWPHelp.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'reactifywp_dismiss_tour',
                    nonce: reactifyWPHelp.nonce
                }
            });
        },

        /**
         * Open help modal
         */
        openHelpModal(e) {
            e.preventDefault();
            $('#reactifywp-help-modal').fadeIn(300);
            this.loadHelpTopic('overview');
        },

        /**
         * Close help modal
         */
        closeHelpModal(e) {
            e.preventDefault();
            $('#reactifywp-help-modal').fadeOut(300);
        },

        /**
         * Close modal on overlay click
         */
        closeModalOnOverlay(e) {
            if (e.target === e.currentTarget) {
                this.closeHelpModal(e);
            }
        },

        /**
         * Switch help topic
         */
        switchHelpTopic(e) {
            e.preventDefault();
            const topic = $(e.target).data('topic');
            $('.reactifywp-help-nav a').removeClass('active');
            $(e.target).addClass('active');
            this.loadHelpTopic(topic);
        },

        /**
         * Load help topic
         */
        loadHelpTopic(topic) {
            $.ajax({
                url: reactifyWPHelp.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'reactifywp_get_help',
                    nonce: reactifyWPHelp.nonce,
                    topic: topic
                },
                success: (response) => {
                    if (response.success) {
                        const content = response.data.content;
                        $('#reactifywp-help-text').html(content.content);
                        $('#reactifywp-help-video').attr('href', content.video);
                        $('#reactifywp-help-docs').attr('href', content.docs);
                    }
                }
            });
        },

        /**
         * Handle keyboard shortcuts
         */
        handleKeydown(e) {
            // Close modal on Escape
            if (e.keyCode === 27) {
                if ($('#reactifywp-help-modal').is(':visible')) {
                    this.closeHelpModal(e);
                }
                if ($('#reactifywp-tour-overlay').is(':visible')) {
                    this.skipTour();
                }
            }
            
            // Tour navigation with arrow keys
            if ($('#reactifywp-tour-overlay').is(':visible')) {
                if (e.keyCode === 37) { // Left arrow
                    this.prevTourStep();
                } else if (e.keyCode === 39) { // Right arrow
                    this.nextTourStep();
                }
            }
        }
    };

    // Initialize when document is ready
    $(document).ready(() => {
        ReactifyWPHelp.init();
    });

})(jQuery);
