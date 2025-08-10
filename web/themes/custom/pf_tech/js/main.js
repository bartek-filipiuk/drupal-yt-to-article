// PF Tech base interactions
(function (Drupal, drupalSettings) {
  'use strict';

  Drupal.behaviors.pfTechBase = {
    attach: function (context) {
      // Enable Bootstrap tooltips if present
      if (typeof bootstrap !== 'undefined' && bootstrap.Tooltip) {
        const tooltipTriggerList = [].slice.call(context.querySelectorAll('[data-bs-toggle="tooltip"]'));
        tooltipTriggerList.forEach(function (tooltipTriggerEl) {
          new bootstrap.Tooltip(tooltipTriggerEl);
        });
      }
    }
  };
})(Drupal, drupalSettings);