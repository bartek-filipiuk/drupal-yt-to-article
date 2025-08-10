# Sidebar Implementation in PF Tech Theme

## Overview
The PF Tech theme now includes a flexible sidebar system with responsive grid layouts and modern API documentation styling.

## Region Configuration

### Available Sidebar Regions
- **sidebar_first**: Left sidebar (25% width on desktop)
- **sidebar_second**: Right sidebar (25% width on desktop)

### Grid System
The page layout automatically adjusts based on sidebar content:

| Sidebar Status | Content Width | Sidebar Width | Layout |
|----------------|---------------|---------------|---------|
| No sidebars | 100% | - | Full width |
| One sidebar | 75% | 25% | Side-by-side on desktop |
| Two sidebars | 50% | 25% each | Three columns on desktop |

### Responsive Behavior
- **Mobile (< lg)**: Sidebars stack vertically below content
- **Desktop (≥ lg)**: Sidebars display beside content
- **Sticky positioning**: Sidebar content stays visible while scrolling

## Usage

### Adding Blocks to Sidebar
1. Navigate to Structure > Block layout
2. Find "Sidebar first" region
3. Click "Place block" and choose:
   - Navigation menus
   - Search blocks
   - Custom blocks
   - Views blocks
   - Faceted search

### Recommended Block Types for API Docs
- **Navigation**: Hierarchical menu with sections
- **Table of Contents**: Auto-generated from page headings
- **Search**: Quick content search
- **Version Selector**: API version dropdown
- **Tags/Categories**: Quick filters
- **Related Links**: Context-aware suggestions

## Styling Features

### Automatic Styling Applied
- Clean white cards with subtle shadows
- Hover effects on links
- Active state highlighting
- Nested menu indentation
- Smooth transitions
- Sticky positioning for long content

### CSS Classes Available
```css
.sidebar-api-docs /* Main sidebar container */
.block /* Individual block styling */
.menu-item a.is-active /* Active menu item */
.toc-block /* Table of contents */
.version-selector /* Version dropdown */
```

## Example Block Configuration

### Navigation Menu Block
```yaml
Type: System Menu Block
Menu: Main navigation
Initial visibility level: 3
Display title: API Reference
```

### Custom HTML Block for TOC
```html
<div class="toc-block">
  <div class="toc-title">On this page</div>
  <ul>
    <li><a href="#overview">Overview</a></li>
    <li><a href="#installation">Installation</a></li>
    <li><a href="#api-reference">API Reference</a></li>
  </ul>
</div>
```

### Search Block
```yaml
Type: Search form
Display title: Search documentation
Placeholder text: Search docs...
```

## Advanced Features

### Smooth Scrolling
Anchor links in the sidebar automatically scroll smoothly to page sections.

### Active Section Highlighting
The current visible section is automatically highlighted in the table of contents using Intersection Observer.

### Mobile Sidebar Toggle
On mobile devices, the sidebar can be toggled with a button (implementation pending).

## Testing Your Sidebar

<function_calls>
<invoke name="TodoWrite">
<parameter name="todos">[{"id": "1", "content": "Update pf_tech.info.yml to add sidebar_first region", "status": "completed", "priority": "high"}, {"id": "2", "content": "Clear Drupal cache to register new regions", "status": "completed", "priority": "high"}, {"id": "3", "content": "Create region--sidebar-first.html.twig template with API doc styling", "status": "completed", "priority": "medium"}, {"id": "4", "content": "Test sidebar by adding blocks to sidebar_first region", "status": "completed", "priority": "low"}]