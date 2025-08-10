# Tailwind CSS Styling Guide for PF Tech Theme

## 🚀 Quick Start

You can now style your Drupal theme using Tailwind CSS classes directly in templates! No more hunting for CSS files or dealing with complex build processes.

## 🎨 How It Works

### 1. **Instant Styling with Tailwind CDN**
We've integrated Tailwind CSS via CDN for instant development. Just add classes to your templates:

```twig
{# Before - Traditional CSS approach #}
<div class="node-article-wrapper custom-padding shadow-effect">

{# After - Tailwind approach #}
<div class="bg-white p-6 rounded-lg shadow-xl hover:shadow-2xl transition-shadow">
```

### 2. **No Build Process Required**
- ✅ Changes appear instantly
- ✅ No webpack/gulp/npm needed for development
- ✅ Works with Drupal's cache system
- ✅ Full IntelliSense support in modern editors

## 📁 File Structure

```
pf_tech/
├── templates/
│   ├── layout/
│   │   ├── page.html.twig          # Simplified with Tailwind
│   │   └── html.html.twig          # Contains Tailwind CDN
│   ├── content/
│   │   └── node--youtube-article.html.twig  # Beautiful YouTube layout
│   └── field/
│       ├── field.html.twig         # Generic field styling
│       └── field--taxonomy-term-reference.html.twig  # Tag styling
└── docs/
    └── tailwind-styling-guide.md   # This file
```

## 🎯 Common Styling Patterns

### Content Types
```twig
{# Article/Blog Post #}
<article class="bg-white rounded-xl shadow-lg overflow-hidden">
  <header class="px-6 py-8 border-b border-gray-100">
    <h1 class="text-4xl font-bold text-drupal-text">{{ title }}</h1>
  </header>
  <div class="px-6 py-8 prose prose-lg max-w-none">
    {{ content }}
  </div>
</article>
```

### Hero Sections
```twig
{# Hero Banner #}
<section class="bg-gradient-to-r from-drupal-primary to-drupal-secondary py-24 px-6">
  <div class="drupal-container text-center text-white">
    <h1 class="text-5xl font-bold mb-6">{{ title }}</h1>
    <p class="text-2xl mb-8 opacity-90">{{ subtitle }}</p>
    <a href="{{ cta_url }}" class="drupal-button bg-white text-drupal-primary hover:bg-gray-100">
      {{ cta_text }}
    </a>
  </div>
</section>
```

### Cards/Teasers
```twig
{# Content Card #}
<div class="drupal-card hover:shadow-xl transition-all duration-300">
  <img src="{{ image_url }}" alt="{{ image_alt }}" class="w-full h-48 object-cover rounded-t-lg">
  <div class="p-6">
    <h3 class="text-xl font-semibold mb-2">{{ title }}</h3>
    <p class="text-drupal-muted mb-4">{{ summary }}</p>
    <a href="{{ url }}" class="text-drupal-primary hover:text-drupal-secondary">
      Read more →
    </a>
  </div>
</div>
```

### Form Elements
```twig
{# Form Field #}
<div class="mb-6">
  <label class="drupal-field-label">{{ label }}</label>
  <input type="text" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-drupal-primary focus:border-drupal-primary">
  <p class="mt-1 text-sm text-drupal-muted">{{ description }}</p>
</div>
```

## 🛠️ Custom Drupal Classes

We've created custom component classes that you can use:

### Components
- `.drupal-button` - Styled button with hover effects
- `.drupal-card` - Card container with shadow
- `.drupal-field-label` - Consistent field labels
- `.drupal-tag` - Tag/category pill style
- `.drupal-prose` - Styled content typography

### Layout
- `.drupal-container` - Responsive container
- `.drupal-section` - Section spacing

### Colors
- `drupal-primary` - #0678BE
- `drupal-secondary` - #00ACC1
- `drupal-text` - #0F172A
- `drupal-muted` - #6B7280
- `drupal-border` - #E6E8EF
- `drupal-surface` - #F8F9FB

## 📱 Responsive Design

Use Tailwind's responsive prefixes:

```twig
<div class="px-4 md:px-6 lg:px-8">
  <h1 class="text-2xl md:text-3xl lg:text-4xl">
    Responsive Title
  </h1>
</div>
```

### Breakpoints
- `sm:` - 640px and up
- `md:` - 768px and up  
- `lg:` - 1024px and up
- `xl:` - 1280px and up
- `2xl:` - 1536px and up

## 🎯 Field Styling

Fields automatically get Tailwind styling, but you can enhance them:

### Text Fields
```twig
{# Automatically styled with prose classes #}
{{ content.body }}
```

### Tag Fields  
```twig
{# Tags get pill styling automatically #}
{{ content.field_tags }}
```

### Image Fields
```twig
{# Images get rounded corners and shadows #}
{{ content.field_image }}
```

## 💡 Pro Tips

### 1. **Use Tailwind's Built-in Classes**
Instead of creating custom CSS, use Tailwind utilities:
```twig
{# Bad #}
<div class="custom-margin custom-padding">

{# Good #}
<div class="mt-6 p-4">
```

### 2. **Leverage Hover States**
```twig
<a class="text-gray-700 hover:text-drupal-primary transition-colors">
  Link with hover
</a>
```

### 3. **Group Related Classes**
```twig
{# Card with hover effect #}
<div class="group bg-white rounded-lg shadow-md hover:shadow-xl transition-shadow">
  <h3 class="group-hover:text-drupal-primary">Title changes on card hover</h3>
</div>
```

### 4. **Use Prose for Content**
```twig
{# Automatically styles all child elements #}
<div class="prose prose-lg max-w-none">
  {{ content.body }}
</div>
```

## 🔧 Customization

### Adding Custom Colors
Edit `html.html.twig` to add more colors:
```javascript
tailwind.config = {
  theme: {
    extend: {
      colors: {
        'drupal': {
          'primary': '#0678BE',
          'success': '#22C55E',
          // Add more colors here
        }
      }
    }
  }
}
```

### Creating Custom Components
Add to the `<style>` section in `html.html.twig`:
```css
@layer components {
  .drupal-alert {
    @apply p-4 rounded-lg border mb-4;
  }
}
```

## 🚀 Production Build (Future)

For production, we'll implement:
1. PostCSS with Tailwind CLI
2. PurgeCSS to remove unused classes
3. Minification and optimization
4. Critical CSS extraction

But for now, the CDN approach works great for development!

## 📋 Cheat Sheet

### Layout
- `container` - Centered container
- `mx-auto` - Center horizontally
- `flex` - Flexbox container
- `grid` - Grid container
- `hidden` / `block` - Display utilities

### Spacing
- `p-4` - Padding 1rem
- `m-4` - Margin 1rem
- `space-y-4` - Vertical spacing between children
- `gap-4` - Gap in flex/grid

### Typography
- `text-lg` - Large text
- `font-bold` - Bold text
- `text-center` - Center align
- `uppercase` - Uppercase text

### Colors
- `text-white` - White text
- `bg-gray-100` - Light gray background
- `border-gray-300` - Gray border

### Effects
- `shadow-lg` - Large shadow
- `rounded-lg` - Large border radius
- `transition-all` - Smooth transitions
- `hover:scale-105` - Scale on hover

## 🆘 Troubleshooting

### Classes Not Applying?
1. Clear Drupal cache: `drush cr`
2. Check browser DevTools for the class
3. Make sure Tailwind CDN is loaded

### Responsive Not Working?
1. Check viewport meta tag exists
2. Use proper breakpoint prefixes
3. Test in responsive mode

### Custom Classes Not Working?
1. Make sure they're in `@layer components`
2. Check syntax in the style tag
3. Verify Tailwind config is correct

## 🎉 You're Ready!

Start adding Tailwind classes to your templates and see instant results. No more CSS file hunting, no more build processes - just beautiful, modern styling!