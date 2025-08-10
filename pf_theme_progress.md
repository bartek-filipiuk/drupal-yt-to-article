# PF Tech Theme Progress

Phase 1a: Bootstrap Barrio installation
- Status: Completed
- Actions:
  - Installed and enabled Bootstrap Barrio (version 5.5.20) via ddev composer and drush.
- Commands executed:
  - ddev composer require drupal/bootstrap_barrio:^5
  - ddev drush theme:enable bootstrap_barrio -y

Phase 1b: Subtheme scaffold and activation
- Status: Completed
- Actions:
  - Created PF Tech subtheme at web/themes/custom/pf_tech
  - Added core theme files:
    - pf_tech.info.yml
    - pf_tech.libraries.yml
    - css/base.css, css/layout.css, css/components.css, css/dark.css, css/code.css
    - js/main.js
    - templates/page.html.twig, templates/status-messages.html.twig
  - Enabled PF Tech and set it as default theme; rebuilt caches
- Commands executed:
  - ddev drush theme:enable pf_tech -y
  - ddev drush cset system.theme default pf_tech -y
  - ddev drush cr

Phase 2: Base templates and Drupal integration
- Status: Completed
- Actions:
  - Added minimal page.html.twig with containerized layout and regions
  - Added status-messages.html.twig mapping to Bootstrap alerts
  - Verified libraries are referenced via pf_tech.info.yml

Phase 3: yt_to_article UI styling integration (theme-only)
- Status: Completed
- Actions:
  - Added pf_tech.theme with hook_page_attachments() to attach:
    - pf_tech/base, pf_tech/components, pf_tech/code, pf_tech/dark
    - pf_tech/yt_article (globally, per request)
  - Ensures yt_to_article form/progress/message UI inherits PF Tech styling without modifying the module
- Manual verification checklist:
  - Visit /yt-to-article to confirm:
    - Form fields adopt PF Tech spacing, borders, and focus states
    - Progress wrapper and messages adopt PF styles (badges, alerts)
    - Dark mode styling applies with prefers-color-scheme
  - If cache persists old markup: run `ddev drush cr`

Next: Phase 4 – Syntax highlighting and code utilities
- Integrate Prism.js or Highlight.js via pf_tech/code library
- Add copy-to-clipboard and optional line numbers; ensure accessible focus states
- Scope styles to .pf-content to avoid bleed

Phase 5: Accessibility and performance passes; THEME.md
- WCAG AA contrast checks
- Reduced motion adherence
- Document THEME.md with usage, regions, libraries, and dark mode