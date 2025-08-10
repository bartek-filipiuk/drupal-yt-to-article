# Visual Style Editor Guide

## 🎨 Overview

The Visual Style Editor is a powerful module that allows you to apply Tailwind CSS classes to any element on your Drupal site through a point-and-click interface. No more editing templates - just click and style!

## 🚀 Quick Start

### 1. Enable the Module

```bash
# Enable the module
drush en tailwind_style_editor -y

# Clear cache
drush cr

# Set permissions
drush role:perm:add authenticated "use tailwind style editor"
```

### 2. Activate Style Editor

1. Look for the **"Style Editor"** button in your Drupal toolbar
2. Click it to activate the visual editor
3. Your cursor will change to a crosshair

### 3. Start Styling!

1. **Click any element** on the page to select it
2. The **Style Panel** will slide in from the right
3. **Choose classes** from categories or add custom ones
4. See changes **instantly** on your page
5. **Save** your styles when done

## 📱 Interface Overview

### Toolbar Button
- Located in the admin toolbar
- Click to toggle editor on/off
- Button turns blue when active

### Style Panel Components

#### 1. **Search Box**
- Quick search for any Tailwind class
- Type to filter all categories

#### 2. **Current Classes**
- Shows all classes on selected element
- Click ✕ to remove any class

#### 3. **Category Sections**
- **Layout**: Display types (block, flex, grid)
- **Spacing**: Padding and margin
- **Typography**: Font sizes, weights, colors
- **Colors**: Background and text colors
- **Borders**: Border styles and radius
- **Effects**: Shadows, opacity, transitions
- **And more!**

#### 4. **Custom Classes**
- Add any Tailwind class manually
- Supports multiple classes at once

#### 5. **Actions**
- **Save**: Persist styles to database
- **Export**: Download as CSS file
- **Reset**: Remove all Tailwind classes

## 🎯 Common Tasks

### Styling a Hero Section

1. Click the hero section
2. From **Colors - Background**, select `bg-gradient-to-r`
3. Add custom classes: `from-blue-500 to-purple-600`
4. From **Spacing - Padding**, select `py-24`
5. From **Typography**, add `text-white text-center`

### Making a Card

1. Click your content block
2. From **Colors - Background**, select `bg-white`
3. From **Border Radius**, select `rounded-lg`
4. From **Effects - Shadow**, select `shadow-lg`
5. From **Spacing - Padding**, select `p-6`

### Responsive Design

1. Select your element
2. Add base styles first
3. Add responsive prefixes:
   - `md:` for tablet and up
   - `lg:` for desktop and up
   - Example: `p-4 md:p-6 lg:p-8`

## 💡 Pro Tips

### 1. **Use the Search**
Instead of browsing categories, just search:
- Type "pad" to find all padding classes
- Type "blue" to find blue colors
- Type "hover" to find hover states

### 2. **Keyboard Shortcuts**
- `ESC` - Deselect current element
- `Ctrl+Z` - Undo last change (in browser)

### 3. **Class Order Matters**
Some Tailwind classes override others. The panel shows classes in the order they're applied.

### 4. **Preview Hover States**
Add `hover:` prefix to any class:
- `hover:bg-blue-600`
- `hover:scale-105`
- `hover:shadow-xl`

### 5. **Group Classes**
Style parent and children together:
- Add `group` to parent
- Use `group-hover:` on children

## 🔧 Advanced Features

### Saving Styles

Saved styles are stored per page and persist across sessions:
1. Make your changes
2. Click "Save Styles"
3. Styles auto-load next time you visit

### Exporting Styles

Export your visual edits as CSS:
1. Click "Export" button
2. Downloads `tailwind-styles.css`
3. Can be used in production builds

### Style Inheritance

The editor respects Drupal's render system:
- Changes to fields apply everywhere that field appears
- Changes to blocks affect all instances
- Node-level changes are content-type specific

## ⚙️ Configuration

### Permissions

Two permission levels:
- **Use Tailwind Style Editor**: Can style elements
- **Administer Tailwind Style Editor**: Can configure module

### Excluded Elements

The editor won't select:
- Admin toolbar
- Style panel itself
- System critical elements

## 🚨 Troubleshooting

### Classes Not Applying?
1. Check if element has inline styles (they override classes)
2. Some Drupal elements have !important rules
3. Clear Drupal cache: `drush cr`

### Can't Click Elements?
1. Make sure editor is active (button is blue)
2. Some elements may be covered by invisible overlays
3. Try clicking the parent element instead

### Changes Not Saving?
1. Check you have "use tailwind style editor" permission
2. Make sure to click "Save Styles" button
3. Check browser console for errors

### Panel Not Opening?
1. Check JavaScript errors in console
2. Try disabling other JavaScript-heavy modules
3. Clear browser cache

## 🎉 Examples

### Before:
```html
<div class="node node--type-article">
  <h1>Article Title</h1>
  <div class="content">...</div>
</div>
```

### After Using Style Editor:
```html
<div class="node node--type-article bg-white rounded-xl shadow-lg p-8">
  <h1 class="text-3xl font-bold text-gray-800 mb-4">Article Title</h1>
  <div class="content prose prose-lg">...</div>
</div>
```

## 🚀 Best Practices

1. **Start with Layout**: Get structure right first
2. **Then Spacing**: Add padding/margins
3. **Finally Polish**: Colors, shadows, animations
4. **Test Responsive**: Check all screen sizes
5. **Save Often**: Don't lose your work!

## 📊 Workflow Example

1. **Activate Editor** → Click toolbar button
2. **Select Hero** → Click hero section
3. **Add Background** → Pick from color category
4. **Add Spacing** → Select padding classes
5. **Style Text** → Add typography classes
6. **Make Responsive** → Add breakpoint variants
7. **Save Work** → Click save button
8. **Continue** → Select next element

## 🆘 Need Help?

- Check the Tailwind CSS docs for class details
- Use browser DevTools to inspect applied styles
- Export and review your styled selectors
- Clear caches if changes don't appear

Enjoy styling your Drupal site visually with Tailwind CSS! 🎨✨