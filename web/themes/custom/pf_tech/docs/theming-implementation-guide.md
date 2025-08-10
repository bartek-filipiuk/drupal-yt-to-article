# PF Tech Theme Implementation Guide

## Overview
This guide provides step-by-step instructions for implementing and extending the PF Tech Drupal theme styling system.

## Theme Structure

### Directory Layout
```
pf_tech/
├── css/
│   ├── base.css              # Foundation styles, CSS variables
│   ├── layout.css            # Page layout and structure
│   ├── components.css        # Reusable UI components
│   ├── code.css             # Code syntax highlighting
│   ├── dark.css             # Dark mode overrides
│   ├── utilities.css        # Utility classes
│   ├── content-types/       # Content type specific styles
│   │   └── youtube-article.css
│   └── fields/              # Field-specific reusable styles
│       ├── field--media.css
│       ├── field--tags.css
│       └── field--metadata.css
├── js/
│   ├── main.js
│   └── toasts.js
├── templates/
│   └── content/
│       └── node--youtube-article.html.twig
├── docs/
│   ├── css-architecture-plan.md
│   └── theming-implementation-guide.md
├── pf_tech.info.yml
├── pf_tech.libraries.yml
└── pf_tech.theme
```

## Styling YouTube Articles

### 1. Template Structure
The YouTube article template (`node--youtube-article.html.twig`) provides these key regions:
- Header section with title and metadata
- Main content area
- Field outputs via `{{ content }}`

### 2. Applied CSS Classes
The theme automatically applies these classes to YouTube articles:
- `.node--type-youtube-article` - Base class from Drupal
- `.yt-article` - Custom class for easier targeting
- `.yt-article--view-mode-[mode]` - View mode specific styling

### 3. Field-Specific Styling
Common YouTube article fields and their styling:

#### Video Embed
```css
/* Applied to video fields */
.field--name-field-youtube-url
.field--name-field-youtube-video
.media-oembed-content
```

#### Tags/Categories
```css
/* Applied to taxonomy fields */
.field--name-field-tags
.field--name-field-categories
```

#### Metadata
```css
/* Applied to metadata fields */
.field--name-created
.field--name-uid
.node__meta
```

## Creating New Content Type Styles

### Step 1: Create CSS File
Create a new file in `css/content-types/[content-type].css`:

```css
/* Example: css/content-types/blog-post.css */
.node--type-blog-post {
  /* Base styles for blog posts */
}

.node--type-blog-post .node__title {
  /* Title styling */
}

.node--type-blog-post .node__content {
  /* Content area styling */
}
```

### Step 2: Define Library
Add to `pf_tech.libraries.yml`:

```yaml
blog_post:
  version: 1.x
  css:
    component:
      css/content-types/blog-post.css: {}
    theme:
      css/components.css: {}
  dependencies:
    - pf_tech/base
```

### Step 3: Attach Library
Add to `pf_tech.theme`:

```php
function pf_tech_preprocess_node(array &$variables): void {
  $node = $variables['node'];
  
  if ($node->bundle() === 'blog_post') {
    $variables['#attached']['library'][] = 'pf_tech/blog_post';
    $variables['attributes']['class'][] = 'blog-post';
  }
}
```

## Using Utility Classes

The theme provides utility classes for quick styling adjustments:

### Spacing
- `.u-mt-[size]` - Margin top (xs, sm, md, lg, xl, 2xl)
- `.u-mb-[size]` - Margin bottom
- `.u-p-[size]` - Padding

### Typography
- `.u-text-[size]` - Font size (xs, sm, base, lg, xl, 2xl, 3xl)
- `.u-text-[color]` - Text color (muted, primary, danger, success)
- `.u-font-[weight]` - Font weight (normal, medium, semibold, bold)

### Layout
- `.u-flex` - Display flex
- `.u-hidden` - Hide element
- `.u-w-full` - Full width
- `.u-max-w-content` - Max width container

### Example Usage
```twig
<div class="field--name-body u-mt-lg u-text-lg">
  {{ content.body }}
</div>

<div class="u-flex u-gap-md u-items-center">
  {{ content.field_tags }}
</div>
```

## Extending Field Styles

### Adding Custom Field Styles
1. Create field-specific CSS in `css/fields/`
2. Use consistent naming: `field--[field-type].css`
3. Target multiple field variations:

```css
/* Target all date fields */
.field--type-datetime,
[class*="field--name-field-"][class*="-date"] {
  /* Styles */
}
```

### Field Template Overrides
To override field templates:
1. Copy from `core/themes/stable/templates/field/`
2. Place in `templates/field/`
3. Name as `field--[field-name].html.twig`

## CSS Variables Reference

### Colors
- `--pf-bg` - Background color
- `--pf-surface` - Surface/card background
- `--pf-border` - Border color
- `--pf-text` - Text color
- `--pf-muted` - Muted text
- `--pf-primary` - Primary brand color
- `--pf-link` - Link color

### Spacing
- `--pf-space-xs` - 0.25rem
- `--pf-space-sm` - 0.5rem
- `--pf-space-md` - 1rem
- `--pf-space-lg` - 1.5rem
- `--pf-space-xl` - 2rem
- `--pf-space-2xl` - 3rem

### Typography
- `--pf-font-size-xs` - 0.75rem
- `--pf-font-size-sm` - 0.875rem
- `--pf-font-size-base` - 1rem
- `--pf-font-size-lg` - 1.125rem
- `--pf-font-size-xl` - 1.25rem

### Other
- `--pf-radius-sm` - Small border radius
- `--pf-radius-md` - Medium border radius
- `--pf-shadow-sm` - Small shadow
- `--pf-shadow-md` - Medium shadow

## Best Practices

### 1. Component-First Approach
- Create reusable styles in `components.css`
- Extract common patterns to utility classes
- Keep content-type specific styles minimal

### 2. Specificity Management
```css
/* Good - Low specificity */
.yt-article__header { }
.field--tags { }

/* Avoid - High specificity */
#node-104 .field--name-field-tags .field__item a { }
```

### 3. Responsive Design
```css
/* Mobile-first approach */
.yt-article__content {
  padding: 1rem;
}

@media (min-width: 768px) {
  .yt-article__content {
    padding: 2rem;
  }
}
```

### 4. Dark Mode Support
```css
/* Use CSS variables that adapt in dark mode */
.my-component {
  background: var(--pf-surface);
  color: var(--pf-text);
  border: 1px solid var(--pf-border);
}
```

## Testing & Debugging

### Clear Caches
After CSS changes, clear caches:
```bash
drush cr
# or via UI: Configuration > Performance > Clear all caches
```

### Browser DevTools
1. Inspect elements to see applied classes
2. Check computed styles
3. Test responsive breakpoints
4. Verify CSS variable values

### Drupal Debugging
Enable Twig debugging in `services.yml`:
```yaml
parameters:
  twig.config:
    debug: true
    auto_reload: true
    cache: false
```

## Common Issues & Solutions

### Styles Not Loading
1. Clear Drupal cache
2. Check library dependencies
3. Verify library is attached in .theme file
4. Check file paths in .libraries.yml

### Specificity Conflicts
1. Use more specific classes
2. Check load order in libraries.yml
3. Use CSS custom properties for variations
4. Avoid !important

### Responsive Issues
1. Test on actual devices
2. Use relative units (rem, %)
3. Check viewport meta tag
4. Test with browser DevTools

## Next Steps

1. **Extend to Other Content Types**: Apply the same pattern to style other content types
2. **Create View Mode Variations**: Add styles for teaser, full, card view modes
3. **Add Interactive Elements**: Enhance with JavaScript for better UX
4. **Performance Optimization**: Implement critical CSS, lazy loading
5. **Accessibility Improvements**: Add focus states, ARIA labels, keyboard navigation