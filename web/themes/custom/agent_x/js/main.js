/**
 * Agent X Theme - Main JavaScript
 * Mobile menu, interactions, and enhancements
 */

(function (Drupal, once) {
  'use strict';

  /**
   * Mobile Menu Toggle
   */
  Drupal.behaviors.agentXMobileMenu = {
    attach: function (context) {
      once('agent-mobile-menu', '.agent-mobile-toggle', context).forEach(function (toggle) {
        const menu = document.querySelector('.agent-mobile-menu');
        const body = document.body;

        toggle.addEventListener('click', function (e) {
          e.preventDefault();
          toggle.classList.toggle('is-active');
          if (menu) {
            menu.classList.toggle('is-active');
            body.classList.toggle('menu-open');
          }
        });

        // Close menu when clicking outside
        if (menu) {
          menu.addEventListener('click', function (e) {
            if (e.target === menu) {
              toggle.classList.remove('is-active');
              menu.classList.remove('is-active');
              body.classList.remove('menu-open');
            }
          });
        }

        // Close button in mobile menu
        const closeBtn = document.querySelector('.agent-mobile-menu-close');
        if (closeBtn) {
          closeBtn.addEventListener('click', function () {
            toggle.classList.remove('is-active');
            menu.classList.remove('is-active');
            body.classList.remove('menu-open');
          });
        }
      });
    }
  };

  /**
   * Sticky Header
   */
  Drupal.behaviors.agentXStickyHeader = {
    attach: function (context) {
      once('agent-sticky-header', '.region-header', context).forEach(function (header) {
        let lastScrollTop = 0;
        const headerHeight = header.offsetHeight;

        window.addEventListener('scroll', function () {
          const scrollTop = window.pageYOffset || document.documentElement.scrollTop;

          if (scrollTop > headerHeight) {
            header.classList.add('sticky');
            
            // Hide on scroll down, show on scroll up
            if (scrollTop > lastScrollTop && scrollTop > headerHeight * 2) {
              header.style.transform = 'translateY(-100%)';
            } else {
              header.style.transform = 'translateY(0)';
            }
          } else {
            header.classList.remove('sticky');
            header.style.transform = 'translateY(0)';
          }

          lastScrollTop = scrollTop;
        });
      });
    }
  };

  /**
   * Copy Code Button
   */
  Drupal.behaviors.agentXCopyCode = {
    attach: function (context) {
      once('agent-copy-code', '.agent-code-block', context).forEach(function (codeBlock) {
        const copyBtn = document.createElement('button');
        copyBtn.className = 'agent-copy-btn';
        copyBtn.textContent = 'Copy';
        copyBtn.setAttribute('aria-label', 'Copy code to clipboard');

        const header = codeBlock.querySelector('.agent-code-header');
        if (header) {
          header.appendChild(copyBtn);
        } else {
          codeBlock.insertBefore(copyBtn, codeBlock.firstChild);
          copyBtn.style.position = 'absolute';
          copyBtn.style.top = '8px';
          copyBtn.style.right = '8px';
        }

        copyBtn.addEventListener('click', function () {
          const codeContent = codeBlock.querySelector('pre, code');
          if (codeContent) {
            const text = codeContent.textContent;
            navigator.clipboard.writeText(text).then(function () {
              copyBtn.textContent = 'Copied!';
              copyBtn.classList.add('copied');
              setTimeout(function () {
                copyBtn.textContent = 'Copy';
                copyBtn.classList.remove('copied');
              }, 2000);
            }).catch(function (err) {
              console.error('Failed to copy:', err);
            });
          }
        });
      });
    }
  };

  /**
   * Smooth Scroll for Anchor Links
   */
  Drupal.behaviors.agentXSmoothScroll = {
    attach: function (context) {
      once('agent-smooth-scroll', 'a[href^="#"]', context).forEach(function (link) {
        link.addEventListener('click', function (e) {
          const targetId = this.getAttribute('href');
          if (targetId === '#') return;

          const targetElement = document.querySelector(targetId);
          if (targetElement) {
            e.preventDefault();
            const headerHeight = document.querySelector('.region-header').offsetHeight || 0;
            const targetPosition = targetElement.getBoundingClientRect().top + window.pageYOffset - headerHeight - 20;

            window.scrollTo({
              top: targetPosition,
              behavior: 'smooth'
            });

            // Update URL without jumping
            history.pushState(null, null, targetId);
          }
        });
      });
    }
  };

  /**
   * Table of Contents Active State
   */
  Drupal.behaviors.agentXTocActive = {
    attach: function (context) {
      const toc = document.querySelector('.agent-toc');
      if (!toc) return;

      const tocLinks = toc.querySelectorAll('.agent-toc-link');
      const sections = [];

      tocLinks.forEach(function (link) {
        const targetId = link.getAttribute('href');
        if (targetId && targetId.startsWith('#')) {
          const target = document.querySelector(targetId);
          if (target) {
            sections.push({
              link: link,
              target: target
            });
          }
        }
      });

      if (sections.length > 0) {
        window.addEventListener('scroll', function () {
          const scrollPosition = window.pageYOffset + 100;

          sections.forEach(function (section) {
            section.link.classList.remove('is-active');
          });

          for (let i = sections.length - 1; i >= 0; i--) {
            if (scrollPosition >= sections[i].target.offsetTop) {
              sections[i].link.classList.add('is-active');
              break;
            }
          }
        });
      }
    }
  };

  /**
   * Form Validation Enhancement
   */
  Drupal.behaviors.agentXFormValidation = {
    attach: function (context) {
      once('agent-form-validation', 'form', context).forEach(function (form) {
        // Add validation classes on blur
        const inputs = form.querySelectorAll('input, select, textarea');
        
        inputs.forEach(function (input) {
          input.addEventListener('blur', function () {
            if (input.hasAttribute('required')) {
              if (input.value.trim() === '') {
                input.classList.add('is-invalid');
                input.classList.remove('is-valid');
              } else {
                input.classList.add('is-valid');
                input.classList.remove('is-invalid');
              }
            }
          });

          // Remove validation classes on focus
          input.addEventListener('focus', function () {
            input.classList.remove('is-invalid', 'is-valid');
          });
        });
      });
    }
  };

  /**
   * Tabs Component
   */
  Drupal.behaviors.agentXTabs = {
    attach: function (context) {
      once('agent-tabs', '.agent-tabs', context).forEach(function (tabContainer) {
        const tabs = tabContainer.querySelectorAll('.agent-tab');
        const contents = tabContainer.querySelectorAll('.agent-tab-content');

        tabs.forEach(function (tab, index) {
          tab.addEventListener('click', function (e) {
            e.preventDefault();

            // Remove active class from all tabs and contents
            tabs.forEach(function (t) {
              t.classList.remove('active');
            });
            contents.forEach(function (c) {
              c.style.display = 'none';
            });

            // Add active class to clicked tab and show corresponding content
            tab.classList.add('active');
            if (contents[index]) {
              contents[index].style.display = 'block';
            }
          });
        });

        // Initialize first tab as active
        if (tabs.length > 0 && contents.length > 0) {
          tabs[0].classList.add('active');
          contents[0].style.display = 'block';
        }
      });
    }
  };

  /**
   * Responsive Tables
   */
  Drupal.behaviors.agentXResponsiveTables = {
    attach: function (context) {
      once('agent-responsive-table', 'table', context).forEach(function (table) {
        // Wrap table in responsive container if not already wrapped
        if (!table.parentElement.classList.contains('agent-table-responsive')) {
          const wrapper = document.createElement('div');
          wrapper.className = 'agent-table-responsive';
          table.parentNode.insertBefore(wrapper, table);
          wrapper.appendChild(table);
        }
      });
    }
  };

  /**
   * Accessibility: Skip Links
   */
  Drupal.behaviors.agentXSkipLinks = {
    attach: function (context) {
      once('agent-skip-links', 'body', context).forEach(function () {
        // Create skip to main content link
        const skipLink = document.createElement('a');
        skipLink.href = '#main-content';
        skipLink.className = 'agent-skip-link sr-only';
        skipLink.textContent = 'Skip to main content';
        
        skipLink.addEventListener('focus', function () {
          skipLink.classList.remove('sr-only');
        });
        
        skipLink.addEventListener('blur', function () {
          skipLink.classList.add('sr-only');
        });

        document.body.insertBefore(skipLink, document.body.firstChild);
      });
    }
  };

  /**
   * API Method Badge Colors
   */
  Drupal.behaviors.agentXApiMethods = {
    attach: function (context) {
      once('agent-api-methods', '.agent-method', context).forEach(function (badge) {
        const method = badge.textContent.toLowerCase();
        badge.className = 'agent-method agent-method-' + method;
      });
    }
  };

})(Drupal, once);