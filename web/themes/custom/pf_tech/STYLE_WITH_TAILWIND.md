# 🎨 Tailwind Styling Assistant Prompt

## Purpose
This prompt activates a comprehensive styling workflow for the PF Tech Drupal theme using our Tailwind CSS hybrid approach. The assistant will gather all necessary information before implementing beautiful, modern API documentation-style designs.

## Design System Guidelines
- **Style**: Modern API documentation aesthetic (think Stripe, Vercel, or Tailwind docs)
- **Typography**: Clear hierarchy, readable fonts, proper spacing
- **Colors**: Utilize Drupal primary/secondary colors with neutral grays
- **Layout**: Clean, spacious, with clear content sections
- **Components**: Card-based designs, subtle shadows, smooth transitions
- **Code blocks**: Syntax highlighting with dark themes
- **Interactive**: Hover states, focus indicators, smooth animations

## Activation Prompt

```
I need to style [COMPONENT/PAGE] using our Tailwind approach. 

Before I begin styling, I'll need to gather some information to ensure I create exactly what you're looking for:

### 1. **Target Identification**
- What specific element/page/component needs styling? (e.g., "article page", "user profile block", "search results")
- Do you have a specific node ID or URL path? (e.g., node/104, /api/docs)
- Is this for a specific content type, view, or block?

### 2. **Current State Analysis**
- Can you provide the current template file name? (e.g., node--article.html.twig)
- Do you know which region this appears in?
- Are there any existing CSS classes I should preserve?

### 3. **Visual Requirements**
- Do you have a specific design in mind? (describe or provide reference)
- Which sections need the most attention? (header, body, sidebar, etc.)
- Any specific Tailwind utilities you want to use?

### 4. **Content Structure**
- What fields/elements does this component contain?
- Is there dynamic content that needs special handling?
- Any interactive elements? (forms, buttons, accordions)

### 5. **Responsive Behavior**
- How should it look on mobile vs desktop?
- Any specific breakpoint requirements?
- Should certain elements hide/show at different sizes?

### 6. **Special Features**
- Need any animations or transitions?
- Dark mode considerations?
- Accessibility requirements?

Once I have this information, I'll:
1. Analyze the current structure
2. Create/modify the appropriate template file
3. Apply modern, clean Tailwind styling
4. Add any necessary JavaScript for interactions
5. Ensure responsive behavior
6. Test across different scenarios

The result will be a beautifully styled component that follows modern API documentation aesthetics with clean typography, proper spacing, and a professional technical appearance.
```

## Quick Reference for Common Styling Tasks

### Page/Content Type Styling
- Need: Content type machine name, field list, current template
- Will create: node--[type].html.twig with Tailwind classes

### Region Styling  
- Need: Region machine name, content description
- Will create: region--[name].html.twig or preprocess function

### Block Styling
- Need: Block type, plugin ID, content structure  
- Will create: block--[type].html.twig with modern card design

### View Styling
- Need: View name, display mode, fields shown
- Will create: views-view--[name].html.twig with grid/list layouts

### Field Styling
- Need: Field type, field machine name, display context
- Will create: field--[name].html.twig with proper formatting

## Example Usage

```
User: @STYLE_WITH_TAILWIND.md I need to style the API endpoint documentation page