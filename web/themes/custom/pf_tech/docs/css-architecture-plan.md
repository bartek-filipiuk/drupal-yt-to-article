# PF Tech Theme CSS Architecture Plan

## Overview
This document outlines the CSS architecture strategy for the PF Tech Drupal theme, with initial focus on styling the YouTube Article content type while creating reusable components for the entire site.

## CSS Organization Structure

### 1. Base Layer (`css/base/`)
Foundation styles that apply globally:
- `base.css` - CSS custom properties, typography, resets (✓ exists)
- `typography.css` - Extended typography scales and utilities
- `variables.css` - Additional CSS custom properties for spacing, shadows, animations

### 2. Layout Layer (`css/layout/`)
Structural styles for page layout:
- `layout.css` - Grid systems, containers, page structure (✓ exists)
- `regions.css` - Header, footer, sidebar layouts
- `responsive.css` - Breakpoint-based layout adjustments

### 3. Components Layer (`css/components/`)
Reusable UI components:
- `components.css` - General components (buttons, cards, forms) (✓ exists)
- `navigation.css` - Menus, breadcrumbs, pagination
- `media.css` - Images, videos, embeds
- `content-blocks.css` - Reusable content patterns

### 4. Content Type Specific (`css/content-types/`)
Styles specific to content types:
- `youtube-article.css` - YouTube article specific styles
- `page.css` - Basic page styles
- `article.css` - Standard article styles

### 5. Field Components (`css/fields/`)
Individual field styling:
- `field--text.css` - Text field variations
- `field--media.css` - Media field styling
- `field--tags.css` - Taxonomy/tag fields
- `field--metadata.css` - Date, author, metadata fields

### 6. Theme Layer (`css/theme/`)
Visual design and overrides:
- `dark.css` - Dark mode styles (✓ exists)
- `code.css` - Code syntax highlighting (✓ exists)
- `animations.css` - Transitions and animations
- `utilities.css` - Utility classes (needs creation)

## Naming Conventions

### BEM-inspired with Drupal Context
```css
/* Block */
.yt-article { }

/* Element */
.yt-article__header { }
.yt-article__content { }
.yt-article__metadata { }

/* Modifier */
.yt-article--featured { }
.yt-article--video-embed { }

/* Field-specific */
.field--youtube-video { }
.field--youtube-transcript { }

/* State classes */
.is-loading { }
.is-expanded { }
.has-video { }
```

### Drupal-specific Prefixes
- `.node--` for node-level styles
- `.field--` for field-level styles
- `.block--` for block-level styles
- `.region--` for region-level styles
- `.view--` for views-specific styles

## CSS Custom Properties Strategy

### Global Design Tokens
```css
:root {
  /* Existing (from base.css) */
  --pf-bg: #ffffff;
  --pf-primary: #00bcd4;
  
  /* To be added */
  /* Spacing */
  --pf-space-xs: 0.25rem;
  --pf-space-sm: 0.5rem;
  --pf-space-md: 1rem;
  --pf-space-lg: 1.5rem;
  --pf-space-xl: 2rem;
  --pf-space-2xl: 3rem;
  
  /* Typography */
  --pf-font-size-xs: 0.75rem;
  --pf-font-size-sm: 0.875rem;
  --pf-font-size-base: 1rem;
  --pf-font-size-lg: 1.125rem;
  --pf-font-size-xl: 1.25rem;
  --pf-font-size-2xl: 1.5rem;
  --pf-font-size-3xl: 2rem;
  
  /* Shadows */
  --pf-shadow-sm: 0 1px 2px rgba(0,0,0,0.05);
  --pf-shadow-md: 0 4px 6px rgba(0,0,0,0.07);
  --pf-shadow-lg: 0 10px 15px rgba(0,0,0,0.1);
  
  /* Borders */
  --pf-radius-sm: 0.25rem;
  --pf-radius-md: 0.5rem;
  --pf-radius-lg: 0.75rem;
  
  /* Content widths */
  --pf-content-narrow: 65ch;
  --pf-content-wide: 1200px;
}
```

## YouTube Article Styling Approach

### 1. Content Structure
```css
.node--youtube-article {
  /* Article container */
}

.node--youtube-article .yt-header {
  /* Title, metadata section */
}

.node--youtube-article .yt-video-wrapper {
  /* Video embed container with aspect ratio */
}

.node--youtube-article .yt-content {
  /* Main content area */
}

.node--youtube-article .yt-transcript {
  /* Transcript section */
}

.node--youtube-article .yt-metadata {
  /* Tags, categories, related info */
}
```

### 2. Field-Specific Classes
- `.field--name-field-youtube-url`
- `.field--name-field-video-transcript`
- `.field--name-field-video-duration`
- `.field--name-field-tags`
- `.field--name-body`

## Implementation Priority

### Phase 1: Foundation (Week 1)
1. Create missing CSS files (utilities.css)
2. Set up CSS custom properties system
3. Create youtube-article.css with basic structure
4. Style core content areas

### Phase 2: Components (Week 2)
1. Extract reusable components
2. Create field-specific styles
3. Implement responsive design
4. Add interactive states

### Phase 3: Polish (Week 3)
1. Dark mode compatibility
2. Animation and transitions
3. Accessibility improvements
4. Performance optimization

## Best Practices

### 1. CSS Loading Strategy
- Use Drupal's library system for conditional loading
- Create separate libraries for different page types
- Aggregate common styles in base library

### 2. Specificity Management
- Keep specificity low using single classes
- Avoid ID selectors
- Use CSS custom properties for variations
- Leverage CSS cascade properly

### 3. Responsive Design
- Mobile-first approach
- Use CSS Grid and Flexbox for layouts
- Define breakpoints as CSS custom properties
- Test on actual devices

### 4. Performance
- Minimize CSS file size
- Use CSS containment where appropriate
- Lazy-load non-critical styles
- Optimize for Core Web Vitals

## Integration with Drupal

### Libraries Definition
```yaml
# pf_tech.libraries.yml additions
youtube-article:
  version: 1.x
  css:
    component:
      css/content-types/youtube-article.css: {}
      css/fields/field--media.css: {}
      css/fields/field--tags.css: {}
    theme:
      css/utilities.css: {}
  dependencies:
    - pf_tech/base
    - pf_tech/components
```

### Template Class Additions
Add custom classes in templates for better styling hooks:
- Use `addClass()` method in Twig
- Create template suggestions for different display modes
- Add data attributes for JavaScript hooks

## Next Steps
1. Create the missing utilities.css file
2. Implement youtube-article.css with identified components
3. Test with real content
4. Extract patterns for reuse in other content types