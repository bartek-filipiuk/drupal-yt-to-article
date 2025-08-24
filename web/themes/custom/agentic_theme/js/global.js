/**
 * Agentic Theme - Global JavaScript
 * Main theme JavaScript functionality
 */

(function ($, Drupal, once) {
  'use strict';

  /**
   * Agentic Theme behaviors
   */
  Drupal.behaviors.agenticTheme = {
    attach: function (context, settings) {
      // Initialize tooltips
      once('agentic-tooltips', '[data-bs-toggle="tooltip"]', context).forEach(function(element) {
        new bootstrap.Tooltip(element);
      });

      // Initialize popovers
      once('agentic-popovers', '[data-bs-toggle="popover"]', context).forEach(function(element) {
        new bootstrap.Popover(element);
      });

      // Smooth scroll for anchor links
      once('agentic-smooth-scroll', 'a[href^="#"]:not([href="#"])', context).forEach(function(link) {
        link.addEventListener('click', function(e) {
          const targetId = this.getAttribute('href');
          const target = document.querySelector(targetId);
          
          if (target) {
            e.preventDefault();
            const offset = 80; // Account for fixed header
            const targetPosition = target.getBoundingClientRect().top + window.pageYOffset - offset;
            
            window.scrollTo({
              top: targetPosition,
              behavior: 'smooth'
            });
          }
        });
      });

      // Back to top button
      once('agentic-back-to-top', 'body', context).forEach(function() {
        const backToTop = document.createElement('button');
        backToTop.className = 'btn btn-primary btn-back-to-top';
        backToTop.innerHTML = '<i class="bi bi-arrow-up"></i>';
        backToTop.setAttribute('aria-label', 'Back to top');
        document.body.appendChild(backToTop);

        function toggleBackToTop() {
          if (window.pageYOffset > 300) {
            backToTop.classList.add('show');
          } else {
            backToTop.classList.remove('show');
          }
        }

        window.addEventListener('scroll', toggleBackToTop);
        toggleBackToTop();

        backToTop.addEventListener('click', function() {
          window.scrollTo({
            top: 0,
            behavior: 'smooth'
          });
        });
      });

      // Copy code button for code blocks
      once('agentic-copy-code', 'pre', context).forEach(function(pre) {
        const wrapper = document.createElement('div');
        wrapper.className = 'code-toolbar';
        pre.parentNode.insertBefore(wrapper, pre);
        wrapper.appendChild(pre);

        const toolbar = document.createElement('div');
        toolbar.className = 'toolbar';
        wrapper.appendChild(toolbar);

        const copyBtn = document.createElement('button');
        copyBtn.className = 'btn-copy';
        copyBtn.textContent = 'Copy';
        toolbar.appendChild(copyBtn);

        copyBtn.addEventListener('click', function() {
          const code = pre.textContent;
          navigator.clipboard.writeText(code).then(() => {
            copyBtn.textContent = 'Copied!';
            copyBtn.classList.add('copied');
            setTimeout(() => {
              copyBtn.textContent = 'Copy';
              copyBtn.classList.remove('copied');
            }, 2000);
          });
        });
      });

      // Navbar scroll effect
      once('agentic-navbar-scroll', '.navbar', context).forEach(function(navbar) {
        function updateNavbar() {
          if (window.pageYOffset > 50) {
            navbar.classList.add('scrolled');
          } else {
            navbar.classList.remove('scrolled');
          }
        }

        window.addEventListener('scroll', updateNavbar);
        updateNavbar();
      });

      // Form validation feedback
      once('agentic-form-validation', 'form', context).forEach(function(form) {
        form.addEventListener('submit', function(event) {
          if (!form.checkValidity()) {
            event.preventDefault();
            event.stopPropagation();
          }
          form.classList.add('was-validated');
        });
      });

      // Lazy loading for images
      once('agentic-lazy-load', 'img[data-src]', context).forEach(function(img) {
        const imageObserver = new IntersectionObserver((entries, observer) => {
          entries.forEach(entry => {
            if (entry.isIntersecting) {
              const img = entry.target;
              img.src = img.dataset.src;
              img.removeAttribute('data-src');
              imageObserver.unobserve(img);
            }
          });
        });
        imageObserver.observe(img);
      });

      // Table responsive wrapper
      once('agentic-responsive-tables', 'table', context).forEach(function(table) {
        if (!table.closest('.table-responsive')) {
          const wrapper = document.createElement('div');
          wrapper.className = 'table-responsive';
          table.parentNode.insertBefore(wrapper, table);
          wrapper.appendChild(table);
        }
      });

      // Offcanvas menu toggle
      once('agentic-offcanvas', '[data-toggle="offcanvas"]', context).forEach(function(trigger) {
        trigger.addEventListener('click', function(e) {
          e.preventDefault();
          const target = document.querySelector(this.getAttribute('data-target'));
          const backdrop = document.querySelector('.offcanvas-backdrop');
          
          if (target) {
            target.classList.toggle('show');
            if (backdrop) {
              backdrop.classList.toggle('show');
            } else {
              const newBackdrop = document.createElement('div');
              newBackdrop.className = 'offcanvas-backdrop show';
              document.body.appendChild(newBackdrop);
              
              newBackdrop.addEventListener('click', function() {
                target.classList.remove('show');
                this.classList.remove('show');
              });
            }
          }
        });
      });

      // Auto-hide alerts
      once('agentic-auto-hide-alerts', '.alert-dismissible', context).forEach(function(alert) {
        setTimeout(() => {
          const bsAlert = new bootstrap.Alert(alert);
          bsAlert.close();
        }, 5000);
      });

      // YouTube video play button
      once('agentic-youtube-play', '.ratio:has(iframe[src*="youtube"])', context).forEach(function(container) {
        container.classList.add('youtube-video-container');
      });

      // Search form enhancement
      once('agentic-search', '.search-form', context).forEach(function(form) {
        const input = form.querySelector('input[type="search"]');
        if (input) {
          input.addEventListener('focus', function() {
            form.classList.add('focused');
          });
          
          input.addEventListener('blur', function() {
            if (!this.value) {
              form.classList.remove('focused');
            }
          });
        }
      });
    }
  };

  /**
   * Custom theme functions
   */
  Drupal.agenticTheme = {
    /**
     * Show toast notification
     */
    showToast: function(message, type = 'info') {
      const toastContainer = document.getElementById('toast-container') || (() => {
        const container = document.createElement('div');
        container.id = 'toast-container';
        container.className = 'toast-container position-fixed bottom-0 end-0 p-3';
        document.body.appendChild(container);
        return container;
      })();

      const toastEl = document.createElement('div');
      toastEl.className = `toast align-items-center text-white bg-${type} border-0`;
      toastEl.setAttribute('role', 'alert');
      toastEl.setAttribute('aria-live', 'assertive');
      toastEl.setAttribute('aria-atomic', 'true');
      
      toastEl.innerHTML = `
        <div class="d-flex">
          <div class="toast-body">${message}</div>
          <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
        </div>
      `;
      
      toastContainer.appendChild(toastEl);
      const toast = new bootstrap.Toast(toastEl);
      toast.show();
      
      toastEl.addEventListener('hidden.bs.toast', function() {
        this.remove();
      });
    },

    /**
     * Loading overlay
     */
    showLoading: function(element) {
      element.classList.add('loading');
    },
    
    hideLoading: function(element) {
      element.classList.remove('loading');
    }
  };

})(jQuery, Drupal, once);