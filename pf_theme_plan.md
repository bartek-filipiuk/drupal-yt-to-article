# PF Tech Drupal 11 Theme Plan (Bootstrap 5 Barrio Subtheme)

Goal
Create a modern, tech-focused Drupal 11 theme as a subtheme of Bootstrap Barrio, optimized for API/documentation content and the yt_to_article module UI. Emphasis on typography, long-form readability, code snippets, and a clean, accessible design with dark mode support.

Theme Identity
- Machine name: pf_tech
- Human-readable: PF Tech
- Base: Bootstrap Barrio (Bootstrap 5)
- Visual direction: modern API docs aesthetic, neutral surfaces with vibrant cyan/blue accents, excellent text contrast, refined spacing.

--------------------------------------------------------------------------------
1) Dependencies and Installation

1.1 Composer packages
- Add Bootstrap Barrio (compatible with Drupal 11):
  composer require drupal/bootstrap_barrio:^6 --no-interaction
- Optional for syntax highlighting approach (front-end only; included via libraries in theme):
  - Prism.js or Highlight.js (via CDN or local assets)

1.2 Enable modules/themes
- Enable Barrio and set PF Tech as default theme after subtheme creation:
  drush theme:enable bootstrap_barrio
  drush theme:enable pf_tech
  drush config:set system.theme default pf_tech -y

1.3 Notes
- Barrio 6.x supports Bootstrap 5, integrates with core libraries, and is actively maintained for D11.
- We will create a Barrio subtheme instead of modifying Barrio, to keep updates simple.

--------------------------------------------------------------------------------
2) Theme Scaffolding (pf_tech)

2.1 Theme structure
web/themes/custom/pf_tech/
- pf_tech.info.yml
- pf_tech.libraries.yml
- pf_tech.theme
- theme-settings.php (optional, for dark mode toggle, container width, code style options)
- breakpoints.yml (pf_tech.breakpoints.yml)
- config/ (for theme settings defaults if needed)
- css/
  - base.css
  - components.css
  - layout.css
  - utilities.css
  - dark.css
  - code.css
- scss/ (optional, if adopting SCSS build)
- js/
  - main.js
  - toasts.js
- templates/
  - content/
  - field/
  - form/
  - layout/
  - node/
  - block/
  - menu/
  - status-messages.html.twig
  - page.html.twig
- screenshot.png

2.2 Build strategy
Two options:
A) CDN-only (fastest): load Bootstrap via Barrio and add our own CSS/JS in libraries.
B) SCSS build (recommended for scale): set up npm scripts, include Bootstrap source, compile theme CSS with variables/tokens.

Initial iteration will use CDN-only for speed, leaving a path to SCSS later.

--------------------------------------------------------------------------------
3) Design System

3.1 Color tokens
- Base neutrals:
  --pf-bg: #0f1115 (dark mode base) / #ffffff (light)
  --pf-surface: #151823 (dark) / #f8f9fb (light)
  --pf-border: #232736 (dark) / #e6e8ef (light)
- Accent:
  --pf-primary: #00bcd4 (cyan 500)
  --pf-primary-strong: #00acc1
  --pf-link: #0ea5e9 (sky 500)
  --pf-success: #22c55e
  --pf-warning: #f59e0b
  --pf-danger: #ef4444
- Muted text:
  --pf-muted: #9aa3b2 (dark) / #6b7280 (light)

All colors verified for WCAG AA on body text and interactive elements. Use Bootstrap variants where possible.

3.2 Typography
- UI/Body: Inter or IBM Plex Sans (fallback: system-ui, Segoe UI, Roboto, Helvetica, Arial, sans-serif)
- Code: JetBrains Mono or Fira Code (fallback: SFMono-Regular, Consolas, Menlo, monospace)
- Scales:
  - Base font-size: 16px; long-form content 18px on wide screens
  - Heading scale tuned for documentation readability:
    h1 2.0rem; h2 1.5rem; h3 1.25rem; h4 1.125rem; h5 1rem; h6 .875rem
  - Line height: 1.6 body, 1.3 headings

3.3 Spacing and layout
- Container max-width for content: 800-900px
- Vertical rhythm: 8px scale (8,12,16,24,32,48)
- Generous white space before/after h2/h3
- Sidebars de-emphasized; focus on main content column
- Breadcrumbs subtle and compact

3.4 Dark mode
- Respect prefers-color-scheme with CSS variable toggles
- Theme setting to force light/dark or auto
- Ensure code block and inline code colors adjust

3.5 Components
- Nav: sticky top with slim border, clear active state
- Footer: minimal, links grouped, muted text
- Buttons: primary cyan, ghost and subtle variants
- Cards: very light borders, subtle shadows, hover elevation
- Alerts: informative callouts styled for docs
- Tabs/Pills: used for code samples with language tabs where applicable

--------------------------------------------------------------------------------
4) Drupal Integration

4.1 Regions
- Header
- Primary menu
- Secondary menu
- Hero/banner
- Content (primary)
- Sidebar (optional)
- Footer top
- Footer bottom
- Off-canvas region (mobile nav if needed)

4.2 Twig template overrides
- templates/page.html.twig: wrap content in responsive container with max width modifiers
- templates/node/node--article.html.twig: documentation-friendly article layout
- templates/field/field--*.html.twig: consistent spacing and labels
- templates/form/:
  - form-element.html.twig, input/textarea/select overrides mapped to Bootstrap 5 classes
- templates/status-messages.html.twig: map to Bootstrap alerts and toasts
- templates/menu/menu--main.html.twig: clean nav structure

4.3 Form theming strategy
- Use hook_theme_suggestions_form_alter() and form element templates to apply Bootstrap classes wherever Barrio does not already
- Validation states: is-invalid / is-valid with helpful text
- Ajax throbbers replaced by Bootstrap spinner

4.4 Messages and toasts
- Convert Drupal status/warning/error messages to Bootstrap alerts
- Add optional JS to show toast notifications for ephemeral updates

--------------------------------------------------------------------------------
5) yt_to_article Module UI Enhancement

5.1 Form at /yt-to-article
- Input group for YouTube URL with leading icon and clear hints
- Advanced options as collapsible card (length, style), linked help text
- Bootstrap validation messages inline
- Submit button prominent primary; disabled state during processing

5.2 Progress component
- Replace current wrapper with:
  - Stage timeline badges: connected → transcription → chunks → composition → finished
  - Progress bar syncing to API progress, colored by stage
  - Status messages area styled as a collapsible log (monospace)
  - Connection indicator: live dot with tooltip; reconnect banner if WS drops
- Success state:
  - Display “Download Markdown” button as strong CTA
  - Summary details (word count, duration) if available
- Error state:
  - Alert-danger with retry guidance and link to webhook debug

5.3 Message formatting
- Each message includes timestamp, stage tag, and readable text
- Copy log button to clipboard
- Optional “Clear” button

5.4 Integration
- Use existing library attachment (yt_to_article.websocket) and extend CSS in theme
- Provide theme library pf_tech/yt_article that can be attached from module via hook_page_attachments_alter or template preprocess

--------------------------------------------------------------------------------
6) Code Snippets and Long-form Content

6.1 Syntax highlighting
- Include Prism.js or Highlight.js via theme library
- Minimal sensible theme for light and dark; ensure selection/focus visible
- Enable line numbers, copy-to-clipboard button, and soft-wrap toggle for long lines

6.2 Markdown rendering
- Ensure h1-h6 margins and anchors (CSS-only anchor links via :target or utility icon)
- Tables: responsive with horizontal scroll, zebra striping
- Blockquotes: subtle left border accent
- Inline code: padded background chip with good contrast

6.3 Content utilities
- Callout styles: .pf-callout.info/warn/success
- KBD keys styling
- Preformatted code blocks with max-height and overflow auto; respect prefers-reduced-motion

--------------------------------------------------------------------------------
7) Accessibility and Performance

7.1 Accessibility
- WCAG AA color contrast validated
- Focus states visible and consistent
- Reduced motion: limit transitions/animations
- ARIA for status live regions in progress component
- Skip-to-content link

7.2 Performance
- Aggregate/minify CSS/JS (Drupal performance settings)
- Defer non-critical JS
- Critical CSS for above-the-fold header and typography (phase 2)
- Font loading: font-display: swap; preload primary fonts if self-hosted
- Images: responsive, lazy where appropriate

--------------------------------------------------------------------------------
8) Implementation Steps

8.1 Install Barrio
- composer require drupal/bootstrap_barrio:^6
- drush theme:enable bootstrap_barrio

8.2 Create PF Tech subtheme
- Create web/themes/custom/pf_tech
- Add pf_tech.info.yml with base theme: bootstrap_barrio
- Add pf_tech.libraries.yml with base, components, code, dark, yt_article, toasts
- Add basic templates: page.html.twig, status-messages.html.twig, form overrides
- Add CSS files with variables and base typography
- drush theme:enable pf_tech
- drush cset system.theme default pf_tech -y
- drush cr

8.3 Integrate Prism/Highlight
- Add library definition; attach on node content templates and code-heavy pages
- Test with sample code blocks

8.4 Wire yt_to_article styles
- Ensure module templates attach pf_tech/yt_article library via preprocess or page attachments
- Style form and progress components; verify WS messages and states
- Add toasts for connection and completion

8.5 Theme settings (optional in phase 1)
- theme-settings.php: toggle dark mode auto/light/dark; container width
- Admin UI documentation with examples

--------------------------------------------------------------------------------
9) Deliverables

- PF Tech theme scaffold with Barrio base
- Typography, color tokens, and dark mode CSS
- Template overrides for page, forms, messages
- JS for toasts and optional code utilities
- Documentation in THEME.md explaining structure, settings, and usage
- Applied styling to yt_to_article form and progress components
- Syntax highlighting integration and copy-to-clipboard

--------------------------------------------------------------------------------
10) Timeline and Milestones

- Day 1: Install Barrio, scaffold pf_tech, base typography/colors, page/form/message templates
- Day 2: yt_to_article styling, progress component, toasts, dark mode polish
- Day 3: Syntax highlighting, code utilities, accessibility and performance passes, docs

--------------------------------------------------------------------------------
11) Risks and Mitigations

- Bootstrap/Barrio class conflicts with Drupal core: validate via form templates and minimal form_alter if necessary
- Syntax highlighter CSS clash: scope code styles to .pf-content or pre/code only
- Dark mode contrast issues: manual verification and automated contrast checks

--------------------------------------------------------------------------------
12) Post-Plan Next Actions (Code Mode)

- Add Barrio via Composer
- Scaffold pf_tech directory and files
- Implement base libraries, templates, and CSS variables
- Integrate and test yt_to_article UI styling
- Add Prism/Highlight and code utilities
- Provide THEME.md usage notes and screenshots
