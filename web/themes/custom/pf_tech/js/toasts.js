// PF Tech: lightweight UI polish for yt_to_article without changing the module.
// - Enhances form layout with Bootstrap classes if missing
// - Adds input group icon for URL
// - Provides toast helpers for WS connection messages (optional, non-invasive)
(function (Drupal, once) {
  'use strict';

  function ensureClass(el, cls) {
    if (el && !el.classList.contains(cls)) el.classList.add(cls);
  }

  function wrap(el, wrapper) {
    el.parentNode.insertBefore(wrapper, el);
    wrapper.appendChild(el);
  }

  Drupal.behaviors.pfTechYtArticle = {
    attach: function (context) {
      // Target form and progress wrappers rendered by the module templates.
      const forms = once('pf-tech-yt-form', '.yt-to-article-form', context);
      forms.forEach(form => {
        // Add Bootstrap form classes if not present.
        form.querySelectorAll('input[type="text"], input[type="url"], textarea, select').forEach(input => {
          ensureClass(input, 'form-control');
        });

        // Labels
        form.querySelectorAll('label').forEach(label => {
          ensureClass(label, 'form-label');
        });

        // Form items: try to convert to .mb-3 groups
        form.querySelectorAll('.form-item, .form-group, .js-form-item').forEach(group => {
          ensureClass(group, 'mb-3');
        });

        // Submit styling
        form.querySelectorAll('input[type="submit"], button[type="submit"], .form-actions input, .form-actions button').forEach(btn => {
          ensureClass(btn, 'btn');
          ensureClass(btn, 'btn-primary');
        });

        // Enhance YouTube URL field with an input-group and icon if not already wrapped.
        const urlInput = form.querySelector('input[type="url"], input[name*="youtube"], input#edit-youtube-url');
        if (urlInput && !urlInput.closest('.input-group')) {
          const inputGroup = document.createElement('div');
          inputGroup.className = 'input-group';
          const span = document.createElement('span');
          span.className = 'input-group-text';
          span.title = 'YouTube URL';
          span.textContent = 'YT';
          // Wrap existing input
          wrap(urlInput, inputGroup);
          inputGroup.insertBefore(span, urlInput);
        }

        // Help text/descriptions
        form.querySelectorAll('.description, .help-text').forEach(desc => {
          ensureClass(desc, 'form-text');
          desc.style.marginTop = '.25rem';
        });

        // Place the form in a card if not already inside a card
        if (!form.closest('.card')) {
          const card = document.createElement('div');
          card.className = 'card mb-4';
          const body = document.createElement('div');
          body.className = 'card-body';
          wrap(form, card);
          card.appendChild(body);
          body.appendChild(form);
          // Optional header
          const header = document.createElement('div');
          header.className = 'card-header';
          //header.textContent = 'YouTube to Article';
          card.insertBefore(header, body);
        }
      });

      // Progress wrapper enhancements
      const progressWrappers = once('pf-tech-yt-progress', '.yt-to-article-progress-wrapper', context);
      progressWrappers.forEach(wrapper => {
        ensureClass(wrapper, 'pf-card');
        ensureClass(wrapper, 'p-3');
        ensureClass(wrapper, 'mb-4');

        // Stage badges: try to stylize any stage elements by data attributes if present
        wrapper.querySelectorAll('[data-stage], .yt-stage').forEach(stageEl => {
          ensureClass(stageEl, 'badge');
          ensureClass(stageEl, 'pf-stage');
          ensureClass(stageEl, 'bg-primary-subtle');
          ensureClass(stageEl, 'text-primary-emphasis');
          ensureClass(stageEl, 'me-1');
        });

        // Progress bar: if progress element exists, normalize classes
        const progressBar = wrapper.querySelector('.progress-bar');
        if (progressBar) {
          const progress = progressBar.getAttribute('aria-valuenow') || progressBar.style.width.replace('%','') || '0';
          const container = progressBar.closest('.progress') || (function () {
            const p = document.createElement('div');
            p.className = 'progress my-2';
            wrap(progressBar, p);
            return p;
          })();
          ensureClass(container, 'progress');
          ensureClass(progressBar, 'progress-bar');
          progressBar.style.width = progress + '%';
        }

        // Messages area/logs
        const messagesEl = wrapper.querySelector('.yt-to-article-messages, .messages-wrapper, .log-wrapper');
        if (messagesEl) {
          ensureClass(messagesEl, 'pf-log');
          // Add a simple toolbar for copy if not present
          if (!messagesEl.previousElementSibling || !messagesEl.previousElementSibling.classList.contains('pf-code-toolbar')) {
            const toolbar = document.createElement('div');
            toolbar.className = 'pf-code-toolbar';
            const copyBtn = document.createElement('button');
            copyBtn.type = 'button';
            copyBtn.className = 'btn btn-outline-secondary btn-sm';
            copyBtn.textContent = 'Copy log';
            copyBtn.addEventListener('click', () => {
              const text = messagesEl.innerText || messagesEl.textContent || '';
              navigator.clipboard.writeText(text);
              try {
                const event = new CustomEvent('pf-tech-toast', { detail: { text: 'Log copied to clipboard' }});
                window.dispatchEvent(event);
              } catch (e) {}
            });
            toolbar.appendChild(copyBtn);
            messagesEl.parentNode.insertBefore(toolbar, messagesEl);
          }
        }
      });

      // Minimal toast mechanism (no dependency on Bootstrap JS required)
      const toastOnce = once('pf-tech-toast-listener', 'body', context);
      toastOnce.forEach(() => {
        window.addEventListener('pf-tech-toast', (e) => {
          const msg = (e.detail && e.detail.text) || 'Action completed';
          const toast = document.createElement('div');
          toast.className = 'toast align-items-center text-bg-dark border-0 position-fixed bottom-0 end-0 m-3 show';
          toast.setAttribute('role', 'status');
          toast.style.zIndex = 1080;
          toast.innerHTML = '<div class="d-flex"><div class="toast-body">' + msg + '</div><button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button></div>';
          document.body.appendChild(toast);
          setTimeout(() => toast.remove(), 2500);
        });
      });
    }
  };
})(Drupal, once);
